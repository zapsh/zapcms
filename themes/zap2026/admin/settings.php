<?php
defined('IN_ZAPCMS_ADMIN') or die('No permission');

/**
 * Zap2026 主题设置页
 *
 * 约定：
 *   - 本文件只负责「渲染表单」，保存统一交给 ThemeController@saveSettings
 *     （POST 字段名形如 settings[KEY]，与 theme.json 顶层 options 的 key 一一对应）。
 *   - 选项归属与读写由 services\Theme 的 ownedOptionKeys / getOptions / saveOptions 统一管理。
 *   - 主题选项统一以「主题名」为前缀（如 zap2026_*）；非前缀键（如全局缩略图尺寸
 *     productThumbWidth 等）不属于本主题，由全局 ThemeSettings 负责，本页不处理。
 *
 * 当前值来自控制器传入的 $options（已用 theme.json 默认值兜底）。
 */

// 轮播图默认值
$slideRaw = $options['zap2026_home.slide'] ?? '[]';
$slides = json_decode($slideRaw, true);
if (!is_array($slides)) {
    $slides = [];
}
if (empty($slides)) {
    $slides = [
        ['img_path' => '/themes/zap2026/img/banner1.png', 'link' => 'https://zap.cn', 'target' => '_blank'],
        ['img_path' => '/themes/zap2026/img/banner2.png', 'link' => 'https://zap.cn', 'target' => '_blank'],
    ];
}

$svcTitle    = $options['zap2026_home.service_title'] ?? '服务项目';
$svcSubtitle = $options['zap2026_home.service_subtitle'] ?? 'SERVICES';
$about       = $options['zap2026_home.about_us'] ?? '';

$get = function (string $k, $default = '') use ($options) {
    return $options[$k] ?? $default;
};
?>
<form id="zapForm" method="post">
    <ul class="nav nav-tabs mb-3" id="zapSettingTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-slide" data-bs-toggle="tab" data-bs-target="#pane-slide" type="button" role="tab">
                <i class="fa fa-images me-1"></i> 轮播图
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-service" data-bs-toggle="tab" data-bs-target="#pane-service" type="button" role="tab">
                <i class="fa fa-concierge-bell me-1"></i> 服务项目
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-about" data-bs-toggle="tab" data-bs-target="#pane-about" type="button" role="tab">
                <i class="fa fa-circle-info me-1"></i> 关于我们
            </button>
        </li>
    </ul>

    <div class="tab-content">
        <!-- ============ 轮播图 ============ -->
        <div class="tab-pane fade show active" id="pane-slide" role="tabpanel">
            <div class="callout callout-info">
                <i class="fa fa-images me-1"></i> 首页轮播图，可自由增删。保存于 <code>zap2026_home.slide</code>
            </div>
            <input type="hidden" name="settings[zap2026_home.slide]" id="zap_slide_json"
                   value="<?php echo htmlspecialchars($slideRaw, ENT_QUOTES); ?>">
            <div id="slideList" class="mb-3">
                <?php foreach ($slides as $s): ?>
                <div class="slide-row card card-body py-2 mb-2 shadow-none">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label small text-muted mb-1">图片路径</label>
                            <div class="input-group">
                                <input type="text" class="form-control slide-img"
                                       value="<?php echo htmlspecialchars((string)($s['img_path'] ?? '')); ?>"
                                       placeholder="/themes/zap2026/img/banner1.png">
                                <button type="button" class="btn btn-outline-primary btn-pick-image" title="选择图片">
                                    <i class="fa fa-folder-open"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted mb-1">跳转链接</label>
                            <input type="text" class="form-control slide-link"
                                   value="<?php echo htmlspecialchars((string)($s['link'] ?? '')); ?>"
                                   placeholder="https://zap.cn">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted mb-1">打开方式</label>
                            <div class="input-group">
                                <select class="form-select slide-target">
                                    <option value="_blank" <?php echo (($s['target'] ?? '_blank') === '_blank') ? 'selected' : ''; ?>>新窗口</option>
                                    <option value="_self" <?php echo (($s['target'] ?? '_blank') === '_self') ? 'selected' : ''; ?>>当前页面</option>
                                </select>
                                <button type="button" class="btn btn-outline-danger btn-remove-slide" title="删除该图">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm btn-add-slide">
                <i class="fa fa-plus me-1"></i> 添加一张图片
            </button>
        </div>

        <!-- ============ 服务项目 ============ -->
        <div class="tab-pane fade" id="pane-service" role="tabpanel">
            <div class="callout callout-info">
                <i class="fa fa-concierge-bell me-1"></i> 服务项目（最多 6 项），保存于 <code>zap2026_home.service*</code>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">主标题</label>
                    <input type="text" class="form-control" name="settings[zap2026_home.service_title]"
                           value="<?php echo htmlspecialchars($svcTitle); ?>" placeholder="服务项目">
                </div>
                <div class="col-md-6">
                    <label class="form-label">副标题（英文）</label>
                    <input type="text" class="form-control" name="settings[zap2026_home.service_subtitle]"
                           value="<?php echo htmlspecialchars($svcSubtitle); ?>" placeholder="SERVICES">
                </div>
            </div>
            <hr>
            <?php for ($i = 1; $i <= 6; $i++): ?>
            <div class="card card-body py-2 mb-2 shadow-none service-row">
                <div class="row g-2 align-items-end">
                    <div class="col-md-1">
                        <label class="form-label small text-muted mb-1">序号</label>
                        <input type="text" class="form-control text-center" value="<?php echo $i; ?>" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">图标</label>
                        <div class="input-group">
                            <input type="text" class="form-control" name="settings[zap2026_home.service<?php echo $i; ?>_icon]"
                                   id="svc_icon_<?php echo $i; ?>"
                                   value="<?php echo htmlspecialchars($get("zap2026_home.service{$i}_icon")); ?>"
                                   placeholder="fa fa-code"
                                   onclick="ZapFaIcons(['#svc_preview_<?php echo $i; ?>','#svc_icon_<?php echo $i; ?>'])">
                            <span class="input-group-text">
                                <i class="<?php echo htmlspecialchars($get("zap2026_home.service{$i}_icon") ?: 'fa fa-icons'); ?>" id="svc_preview_<?php echo $i; ?>"></i>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">标题</label>
                        <input type="text" class="form-control" name="settings[zap2026_home.service<?php echo $i; ?>_title]"
                               value="<?php echo htmlspecialchars($get("zap2026_home.service{$i}_title")); ?>">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small text-muted mb-1">描述</label>
                        <textarea class="form-control" name="settings[zap2026_home.service<?php echo $i; ?>_content]" rows="2"><?php echo htmlspecialchars($get("zap2026_home.service{$i}_content")); ?></textarea>
                    </div>
                </div>
            </div>
            <?php endfor; ?>
        </div>

        <!-- ============ 关于我们（富文本） ============ -->
        <div class="tab-pane fade" id="pane-about" role="tabpanel">
            <div class="callout callout-info">
                <i class="fa fa-circle-info me-1"></i> 富文本编辑器，保存为 HTML，存于 <code>zap2026_home.about_us</code>
            </div>
            <textarea name="settings[zap2026_home.about_us]" id="zap_about_editor"
                      class="form-control summernote"><?php echo htmlspecialchars($about); ?></textarea>
        </div>
    </div>

    <div class="mt-4 d-flex justify-content-end">
        <button type="button" class="btn btn-primary" onclick="buildSlideJson(); syncAboutEditor(); saveSettings();">
            <i class="fa fa-save me-1"></i> 保存设置
        </button>
    </div>
</form>

<script>
(function () {
    // 提交前把轮播图多行输入汇总为 JSON，写入隐藏域
    function buildSlideJson() {
        var imgs = document.querySelectorAll('#slideList .slide-img');
        var links = document.querySelectorAll('#slideList .slide-link');
        var targets = document.querySelectorAll('#slideList .slide-target');
        var slides = [];
        imgs.forEach(function (el, i) {
            var p = (el.value || '').trim();
            if (!p) return;
            var t = targets[i] ? targets[i].value : '_blank';
            slides.push({
                img_path: p,
                link: ((links[i] ? links[i].value : '') || '').trim(),
                target: (t === '_self' ? '_self' : '_blank')
            });
        });
        var hidden = document.getElementById('zap_slide_json');
        if (hidden) hidden.value = JSON.stringify(slides);
    }
    window.buildSlideJson = buildSlideJson;

    // 提交前把 Summernote 富文本内容同步回底层 textarea，确保 serialize 能取到 HTML
    function syncAboutEditor() {
        if (typeof jQuery === 'undefined') return;
        var $t = jQuery('#zap_about_editor');
        if ($t.length && $t.next('.note-editor').length) {
            $t.val($t.summernote('code'));
        }
    }
    window.syncAboutEditor = syncAboutEditor;

    var seq = <?php echo (int) count($slides); ?>;
    function slideRowHtml() {
        var id = 'slide_img_' + (seq++);
        return '<div class="slide-row card card-body py-2 mb-2 shadow-none">' +
            '<div class="row g-2 align-items-end">' +
            '<div class="col-md-5"><label class="form-label small text-muted mb-1">图片路径</label><div class="input-group">' +
            '<input type="text" class="form-control slide-img" id="' + id + '" placeholder="/themes/zap2026/img/banner1.png">' +
            '<button type="button" class="btn btn-outline-primary btn-pick-image" title="选择图片"><i class="fa fa-folder-open"></i></button></div></div>' +
            '<div class="col-md-4"><label class="form-label small text-muted mb-1">跳转链接</label><input type="text" class="form-control slide-link" placeholder="https://zap.cn"></div>' +
            '<div class="col-md-3"><label class="form-label small text-muted mb-1">打开方式</label><div class="input-group">' +
            '<select class="form-select slide-target"><option value="_blank">新窗口</option><option value="_self">当前页面</option></select>' +
            '<button type="button" class="btn btn-outline-danger btn-remove-slide" title="删除该图"><i class="fa fa-trash"></i></button></div></div>' +
            '</div></div>';
    }

    var list = document.getElementById('slideList');
    var addBtn = document.querySelector('.btn-add-slide');
    if (list && addBtn) {
        addBtn.addEventListener('click', function () {
            list.insertAdjacentHTML('beforeend', slideRowHtml());
        });
        list.addEventListener('click', function (e) {
            var pick = e.target.closest('.btn-pick-image');
            if (pick) {
                if (window.Zap && typeof Zap.finder === 'function') {
                    var row = pick.closest('.slide-row');
                    var target = row ? row.querySelector('.slide-img') : null;
                    Zap.finder({target: target ? '#' + target.id : '', size: 'original', title: '选择轮播图片'});
                }
                return;
            }
            var btn = e.target.closest('.btn-remove-slide');
            if (btn) {
                var row2 = btn.closest('.slide-row');
                if (row2) row2.parentNode.removeChild(row2);
            }
        });
    }
})();
</script>

<?php
// 富文本编辑器（Summernote），与 node 等内容编辑页保持一致
\zapcms\support\Asset::library('summernote');
\zapcms\services\Editor::instance()->create('.summernote', [
    'image_upload' => 'zapSendFile',
    'upload_url'   => url_action('Upload@image'),
]);
?>
