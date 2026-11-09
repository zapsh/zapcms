<?php
defined('IN_ZAPCMS_ADMIN') or die('No permission');

/**
 * 首页设置
 */

use zap\facades\Cache;

// 加载 summernote 富文本编辑器
zapcms\support\Asset::library('summernote');

/**
 * 清除首页设置相关的多级缓存：
 *   - app 容器缓存 key: options_basic_home
 *   - Cache facade key:  _opts_basic_home
 *   - Cache facade md5 key: _opts_<md5(serialize(['basic_home']))>
 */
function clearHomeOptionCache(): void
{
    if (app()->has('options_basic_home')) {
        app()->delete('options_basic_home');
    }
    try { Cache::delete('_opts_basic_home'); } catch (\Throwable $e) {}
    try {
        Cache::delete('_opts_' . md5(serialize(['basic_home'])));
    } catch (\Throwable $e) {}
}

/**
 * 保存配置：存在则 update，不存在则 add（修复 option_name 无唯一索引导致重复插入的 bug）
 */
function saveOption(string $name, string $value): void
{
    \zapcms\services\Option::upsert($name, $value);
}

// ============ 保存逻辑 ============
if (req()->isPost()) {

    // --- 1. 轮播图 ---
    $imgPaths = (array) req()->post('slide_img', []);
    $links    = (array) req()->post('slide_link', []);
    $targets  = (array) req()->post('slide_target', []);
    $slides   = [];
    foreach ($imgPaths as $i => $imgPath) {
        $imgPath = trim((string) $imgPath);
        if ($imgPath === '') {
            continue;
        }
        $target = (string) ($targets[$i] ?? '');
        $slides[] = [
            'img_path' => $imgPath,
            'link'     => trim((string) ($links[$i] ?? '')),
            'target'   => ($target === '_blank' || $target === '_self') ? $target : '_blank',
        ];
    }
    option_del_array('basic_home.slide');
    option_set_array('basic_home.slide', $slides);
    \zapcms\services\Option::remove('basic_home.slide'); // 清掉旧版 JSON blob（若存在）

    // --- 2. 服务项目 ---
    $svcTitle    = trim((string) req()->post('service_title'));
    $svcSubtitle = trim((string) req()->post('service_subtitle'));
    saveOption('basic_home.service_title',    $svcTitle);
    saveOption('basic_home.service_subtitle', $svcSubtitle);

    for ($i = 1; $i <= 6; $i++) {
        $icon   = trim((string) req()->post("service{$i}_icon"));
        $title  = trim((string) req()->post("service{$i}_title"));
        $content = trim((string) req()->post("service{$i}_content"));
        saveOption("basic_home.service{$i}_icon",    $icon);
        saveOption("basic_home.service{$i}_title",   $title);
        saveOption("basic_home.service{$i}_content", $content);
    }

    // --- 3. 关于我们 ---
    saveOption('basic_home.about_us', (string) req()->post('about_us'));

    clearHomeOptionCache();
    // POST-Redirect-GET：保存后跳回设置页，由后台布局的 showFlashMessages() 自动弹出 Toast 通知
    \zap\http\Response::redirect(url_action('theme@settings', ['page' => '_settings']))->with('success', '设置已保存');
}

// ============ 读取当前值 ============
// 轮播图
$defaultSlides = [
    ['img_path' => '/themes/basic/img/banner1.png', 'link' => 'https://zapcms.cn', 'target' => '_blank'],
    ['img_path' => '/themes/basic/img/banner2.png', 'link' => 'https://zapcms.cn', 'target' => '_blank'],
];
$slides = option_get_array('basic_home.slide', $defaultSlides);

// 服务项目
$svcTitle    = option('basic_home.service_title') ?: '服务项目';
$svcSubtitle = option('basic_home.service_subtitle') ?: 'SERVICES';
$services = [];
for ($i = 1; $i <= 6; $i++) {
    $services[$i] = [
        'icon'    => option("basic_home.service{$i}_icon"),
        'title'   => option("basic_home.service{$i}_title"),
        'content' => option("basic_home.service{$i}_content"),
    ];
}

// 关于我们
$aboutUs = option('basic_home.about_us') ?: '';
?>

<div class="card card-outline card-success">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fa-solid fa-home me-2"></i>首页设置
        </h5>
        <div class="card-tools">
            <button type="submit" form="homeSettingsForm" class="btn btn-sm btn-success">
                <i class="fa-solid fa-floppy-disk me-1"></i>保存设置
            </button>
        </div>
    </div>
    <div class="card-body">
        <form id="homeSettingsForm" method="post" action="<?php echo url_action('theme@settings', ['page' => '_settings']); ?>">

            <ul class="nav nav-tabs" id="homeSettingTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tab-slide" data-bs-toggle="tab" data-bs-target="#pane-slide" type="button" role="tab">
                        <i class="fa-solid fa-images me-1"></i>轮播图
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-service" data-bs-toggle="tab" data-bs-target="#pane-service" type="button" role="tab">
                        <i class="fa-solid fa-concierge-bell me-1"></i>服务项目
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-about" data-bs-toggle="tab" data-bs-target="#pane-about" type="button" role="tab">
                        <i class="fa-solid fa-circle-info me-1"></i>关于我们
                    </button>
                </li>
            </ul>

            <div class="tab-content border border-top-0 rounded-bottom p-3 bg-white">

                <!-- ============ Tab 1：轮播图 ============ -->
                <div class="tab-pane fade show active" id="pane-slide" role="tabpanel">
                    <div class="callout callout-info">
                        <h5><i class="fa-solid fa-images me-1"></i> 首页轮播图（BlazeSlider）</h5>
                        <p class="mb-0">每张卡片对应一张轮播图，可自由增删。配置保存于 options 表：<code>basic_home.slide</code></p>
                    </div>

                    <div id="slideList" class="mb-3">
                        <?php foreach ($slides as $index => $slide): ?>
                        <div class="slide-row card card-body py-2 mb-2 shadow-none">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-5">
                                    <label class="form-label small text-muted mb-1">图片路径</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="slide_img[]"
                                               id="slide_img_<?php echo $index; ?>"
                                               placeholder="/themes/basic/img/banner1.png"
                                               value="<?php echo htmlspecialchars((string) ($slide['img_path'] ?? '')); ?>">
                                        <button type="button" class="btn btn-outline-primary btn-pick-image"
                                                data-target="slide_img_<?php echo $index; ?>" title="选择图片">
                                            <i class="fa-solid fa-folder-open"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small text-muted mb-1">跳转链接</label>
                                    <input type="text" class="form-control" name="slide_link[]"
                                           placeholder="https://zapcms.cn"
                                           value="<?php echo htmlspecialchars((string) ($slide['link'] ?? '')); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small text-muted mb-1">打开方式</label>
                                    <div class="input-group">
                                        <select class="form-select" name="slide_target[]">
                                            <option value="_blank" <?php echo (($slide['target'] ?? '_blank') === '_blank') ? 'selected' : ''; ?>>新窗口打开</option>
                                            <option value="_self" <?php echo (($slide['target'] ?? '_blank') === '_self') ? 'selected' : ''; ?>>当前页面</option>
                                        </select>
                                        <button type="button" class="btn btn-outline-danger btn-remove-slide" title="删除该图">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <button type="button" class="btn btn-outline-primary btn-sm btn-add-slide">
                        <i class="fa-solid fa-plus me-1"></i>添加一张图片
                    </button>
                </div>

                <!-- ============ Tab 2：服务项目 ============ -->
                <div class="tab-pane fade" id="pane-service" role="tabpanel">
                    <div class="callout callout-info">
                        <h5><i class="fa-solid fa-concierge-bell me-1"></i> 服务项目（最多 6 项）</h5>
                        <p class="mb-0">所有字段保存在 options 表，键名：<code>basic_home.service_title</code> / <code>basic_home.service{n}_{icon|title|content}</code></p>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">主标题</label>
                            <input type="text" class="form-control" name="service_title"
                                   value="<?php echo htmlspecialchars($svcTitle); ?>" placeholder="服务项目">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">副标题（英文/拼音）</label>
                            <input type="text" class="form-control" name="service_subtitle"
                                   value="<?php echo htmlspecialchars($svcSubtitle); ?>" placeholder="SERVICES">
                        </div>
                    </div>

                    <hr>

                    <?php for ($i = 1; $i <= 6; $i++): $s = $services[$i]; ?>
                    <div class="card card-body py-2 mb-2 shadow-none service-row">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-1">
                                <label class="form-label small text-muted mb-1">序号</label>
                                <input type="text" class="form-control text-center" value="<?php echo $i; ?>" readonly>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted mb-1">图标</label>
                                <div class="input-group">
                                    <input type="text" class="form-control service-icon-input" name="service<?php echo $i; ?>_icon"
                                           id="service<?php echo $i; ?>_icon"
                                           value="<?php echo htmlspecialchars($s['icon']); ?>"
                                           placeholder="点击选择图标" readonly
                                           onclick="ZapFaIcons(['#icon_preview_<?php echo $i; ?>','#service<?php echo $i; ?>_icon']);">
                                    <span class="input-group-text" title="点击选择图标">
                                        <i class="<?php echo htmlspecialchars($s['icon'] ?: 'fa fa-icons'); ?>" id="icon_preview_<?php echo $i; ?>"></i>
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted mb-1">标题</label>
                                <input type="text" class="form-control" name="service<?php echo $i; ?>_title"
                                       value="<?php echo htmlspecialchars($s['title']); ?>" placeholder="软件开发与定制服务">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small text-muted mb-1">描述</label>
                                <textarea class="form-control" name="service<?php echo $i; ?>_content" rows="2"
                                          placeholder="服务项目的简要说明"><?php echo htmlspecialchars($s['content']); ?></textarea>
                            </div>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>

                <!-- ============ Tab 3：关于我们 ============ -->
                <div class="tab-pane fade" id="pane-about" role="tabpanel">
                    <div class="callout callout-info">
                        <h5><i class="fa-solid fa-circle-info me-1"></i> 关于我们</h5>
                        <p class="mb-0">保存为 HTML，存于 options 表：<code>basic_home.about_us</code></p>
                    </div>
                    <textarea id="about_us_editor" name="about_us" class="form-control"
                              style="min-height: 320px;"><?php echo htmlspecialchars($aboutUs); ?></textarea>
                </div>

            </div>

        </form>
    </div>
</div>

<script>
(function () {
    /* ==== Tab 切换：切换到关于我们时初始化 summernote ==== */
    var aboutTabBtn = document.getElementById('tab-about');
    var editorInited = false;
    if (aboutTabBtn) {
        aboutTabBtn.addEventListener('shown.bs.tab', function () {
            if (editorInited) return;
            if (window.jQuery && jQuery('#about_us_editor').length) {
                jQuery('#about_us_editor').summernote({
                    lang: 'zh-CN',
                    height: 320,
                    minHeight: 200,
                    placeholder: '请输入关于我们的详细内容……',
                    toolbar: [
                        ['style', ['style']],
                        ['font', ['bold', 'italic', 'underline', 'clear']],
                        ['fontsize', ['fontsize']],
                        ['color', ['color']],
                        ['para', ['ul', 'ol', 'paragraph']],
                        ['height', ['height']],
                        ['table', ['table']],
                        ['insert', ['link', 'picture', 'video']],
                        ['view', ['fullscreen', 'codeview', 'help']]
                    ],
                    buttons: {
                        snfinder: function (context) {
                            var ui = jQuery.summernote.ui;
                            return ui.button({
                                contents: '<i class="fa-solid fa-image"></i>',
                                tooltip: '插入/管理图片',
                                click: function () {
                                    if (window.Zap && typeof Zap.finder === 'function') {
                                        Zap.finder({
                                            target: '#about_us_editor',
                                            size: 'original',
                                            mode: 'insert',
                                            title: '选择并插入图片'
                                        });
                                    }
                                }
                            });
                        }
                    },
                    callbacks: {
                        onImageUpload: function (files) {
                            // 直接走 Zap.finder 即可，此处保留回调防止用户手动拖入
                        }
                    }
                });
                editorInited = true;
            }
        });
    }

    /* ==== 轮播图：增 / 删 / Zapfinder 选图 ==== */
    var seq = <?php echo (int) count($slides); ?>;
    function slideRowHtml() {
        var id = 'slide_img_' + (seq++);
        return '<div class="slide-row card card-body py-2 mb-2 shadow-none">' +
            '<div class="row g-2 align-items-end">' +
            '<div class="col-md-5">' +
            '<label class="form-label small text-muted mb-1">图片路径</label>' +
            '<div class="input-group">' +
            '<input type="text" class="form-control" name="slide_img[]" id="' + id + '" placeholder="/themes/basic/img/banner1.png">' +
            '<button type="button" class="btn btn-outline-primary btn-pick-image" data-target="' + id + '" title="选择图片">' +
            '<i class="fa-solid fa-folder-open"></i></button>' +
            '</div></div>' +
            '<div class="col-md-4">' +
            '<label class="form-label small text-muted mb-1">跳转链接</label>' +
            '<input type="text" class="form-control" name="slide_link[]" placeholder="https://zapcms.cn">' +
            '</div>' +
            '<div class="col-md-3">' +
            '<label class="form-label small text-muted mb-1">打开方式</label>' +
            '<div class="input-group">' +
            '<select class="form-select" name="slide_target[]">' +
            '<option value="_blank">新窗口打开</option>' +
            '<option value="_self">当前页面</option>' +
            '</select>' +
            '<button type="button" class="btn btn-outline-danger btn-remove-slide" title="删除该图">' +
            '<i class="fa-solid fa-trash"></i></button>' +
            '</div></div>' +
            '</div></div>';
    }

    var list = document.getElementById('slideList');
    var addBtn = document.querySelector('.btn-add-slide');
    if (list) {
        addBtn && addBtn.addEventListener('click', function () {
            list.insertAdjacentHTML('beforeend', slideRowHtml());
        });

        list.addEventListener('click', function (e) {
            var pick = e.target.closest('.btn-pick-image');
            if (pick) {
                if (window.Zap && typeof Zap.finder === 'function') {
                    Zap.finder({target: '#' + pick.dataset.target, size: 'original', title: '选择轮播图片'});
                }
                return;
            }
            var btn = e.target.closest('.btn-remove-slide');
            if (btn) {
                var row = btn.closest('.slide-row');
                if (row) {
                    row.parentNode.removeChild(row);
                }
            }
        });
    }
})();
</script>
