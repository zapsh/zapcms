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

// ============ 保存（自带 POST 处理，绕过通用 saveSettings，与 basic 主题一致的扁平数组方案） ============
if (req()->isPost()) {
    // 轮播图已迁移到「外观 > 链接管理」（code=home_slide），此处不再处理

    // 2. 服务项目 + 关于我们（标量）
    saveOption('zap2026_home.service_title',     trim((string) req()->post('service_title')));
    saveOption('zap2026_home.service_subtitle',  trim((string) req()->post('service_subtitle')));
    for ($i = 1; $i <= 6; $i++) {
        saveOption("zap2026_home.service{$i}_icon",    trim((string) req()->post("service{$i}_icon")));
        saveOption("zap2026_home.service{$i}_title",   trim((string) req()->post("service{$i}_title")));
        saveOption("zap2026_home.service{$i}_content", trim((string) req()->post("service{$i}_content")));
    }
    saveOption('zap2026_home.about_us', (string) req()->post('about_us'));

    // 3. 清缓存
    \zap\facades\Cache::delete('options_website');
    \zap\facades\Cache::delete('_opts_website');
    \zap\facades\Cache::delete('options_zap2026');
    \zap\facades\Cache::delete('_opts_zap2026');

    // POST-Redirect-GET：由后台布局的 showFlashMessages() 自动弹出 Toast 通知
    // 审计日志统一由 ThemeController::settings() 在接收 POST 时记录（不写在主题视图里）
    \zap\http\Response::redirect(url_action('theme@settings'))->with('success', '设置已保存');
}

// ============ 读取当前值 ============
// 轮播图已迁移到「外观 > 链接管理」，本页不再读取

$svcTitle    = $options['zap2026_home.service_title'] ?? '服务项目';
$svcSubtitle = $options['zap2026_home.service_subtitle'] ?? 'SERVICES';
$about       = $options['zap2026_home.about_us'] ?? '';

$get = function (string $k, $default = '') use ($options) {
    return $options[$k] ?? $default;
};
?>
<form id="zapForm" method="post" action="<?php echo url_action('theme@settings'); ?>" onsubmit="syncAboutEditor(); return true;">
    <ul class="nav nav-tabs mb-3" id="zapSettingTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-service" data-bs-toggle="tab" data-bs-target="#pane-service" type="button" role="tab">
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
        <!-- ============ 服务项目 ============ -->
        <div class="tab-pane fade show active" id="pane-service" role="tabpanel">
            <div class="callout callout-info">
                <i class="fa fa-concierge-bell me-1"></i> 服务项目（最多 6 项），保存于 <code>zap2026_home.service*</code>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">主标题</label>
                    <input type="text" class="form-control" name="service_title"
                           value="<?php echo htmlspecialchars($svcTitle); ?>" placeholder="服务项目">
                </div>
                <div class="col-md-6">
                    <label class="form-label">副标题（英文）</label>
                    <input type="text" class="form-control" name="service_subtitle"
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
                            <input type="text" class="form-control" name="service<?php echo $i; ?>_icon"
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
                        <input type="text" class="form-control" name="service<?php echo $i; ?>_title"
                               value="<?php echo htmlspecialchars($get("zap2026_home.service{$i}_title")); ?>">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small text-muted mb-1">描述</label>
                        <textarea class="form-control" name="service<?php echo $i; ?>_content" rows="2"><?php echo htmlspecialchars($get("zap2026_home.service{$i}_content")); ?></textarea>
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
            <textarea name="about_us" id="zap_about_editor"
                      class="form-control summernote"><?php echo htmlspecialchars($about); ?></textarea>
        </div>
    </div>

    <div class="mt-4 d-flex justify-content-end">
        <button type="submit" class="btn btn-primary">
            <i class="fa fa-save me-1"></i> 保存设置
        </button>
    </div>
</form>

<script>
(function () {
    // 提交前把 Summernote 富文本内容同步回底层 textarea，确保原生表单提交能取到 HTML
    function syncAboutEditor() {
        if (typeof jQuery === 'undefined') return;
        var $t = jQuery('#zap_about_editor');
        if ($t.length && $t.next('.note-editor').length) {
            $t.val($t.summernote('code'));
        }
    }
    window.syncAboutEditor = syncAboutEditor;

    /* 轮播图已迁移到「外观 > 链接管理」，本页不再处理 */
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
