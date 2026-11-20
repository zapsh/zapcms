<?php
defined('IN_ZAPCMS_ADMIN') or die('No permission');

/**
 * 首页设置
 */

use zap\facades\Cache;

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

    // 轮播图已迁移到「外观 > 链接管理」（code=home_slide），此处不再处理

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
// 轮播图已迁移到「外观 > 链接管理」，本页不再读取

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
        <form id="homeSettingsForm" method="post" onsubmit="if(window.syncAboutEditor)window.syncAboutEditor();" action="<?php echo url_action('theme@settings', ['page' => '_settings']); ?>">

            <ul class="nav nav-tabs" id="homeSettingTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tab-service" data-bs-toggle="tab" data-bs-target="#pane-service" type="button" role="tab">
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

                <!-- ============ Tab 1：服务项目 ============ -->
                <div class="tab-pane fade show active" id="pane-service" role="tabpanel">
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
                    <textarea id="about_us_editor" name="about_us"
                              class="form-control summernote"><?php echo htmlspecialchars($aboutUs); ?></textarea>
                </div>

            </div>

        </form>
    </div>
</div>

<script>
(function () {
    /* ==== 提交前把 Summernote 内容同步回底层 textarea，确保表单提交取到 HTML ==== */
    function syncAboutEditor() {
        if (typeof jQuery === 'undefined') return;
        var $t = jQuery('#about_us_editor');
        if ($t.length && $t.next('.note-editor').length) {
            $t.val($t.summernote('code'));
        }
    }
    window.syncAboutEditor = syncAboutEditor;

    /* 轮播图已迁移到「外观 > 链接管理」，本页不再处理 */
})();
</script>

<?php
// 富文本编辑器（Summernote）：复用系统 Editor 服务，与内容编辑页保持一致
\zapcms\support\Asset::library('summernote');
\zapcms\services\Editor::instance()->create('.summernote', [
    'image_upload' => 'zapSendFile',
    'upload_url'   => url_action('Upload@image'),
]);
?>
