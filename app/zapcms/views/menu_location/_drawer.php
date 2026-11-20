<?php
/**
 * 菜单位置抽屉（共享 partial，供「栏目管理」页等宿主页使用）
 *
 *  - 右侧抽屉 HTML（一级：位置列表；二级：菜单构建器）
 *  - 抽屉相关样式
 *  - JS 统一见 _scripts.php
 */
use zap\facades\Url;
?>
<style>
.mi-handle { cursor: grab; }
.mi-handle:active { cursor: grabbing; }
.mi-dragging-src { display: none !important; }
.mi-ghost { background: var(--bs-body-bg); border: 1px solid var(--bs-border-color); border-radius: .375rem; }
.mi-item .mi-title { min-width: 140px; }
.mi-item.mi-drop-before { box-shadow: inset 0 3px 0 #0d6efd; }
.mi-item.mi-drop-after  { box-shadow: inset 0 -3px 0 #0d6efd; }
.mi-item.mi-drop-child  { box-shadow: inset 0 0 0 2px #198754; }
#menuLocationDrawer .offcanvas-body { overflow-x: hidden; }
</style>

<!-- 菜单位置管理：右侧抽屉 -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="menuLocationDrawer" aria-labelledby="menuLocationDrawerLabel" style="width: min(1040px, 96vw);">
    <div class="offcanvas-header bg-body-tertiary border-bottom py-2">
        <h6 class="offcanvas-title mb-0" id="menuLocationDrawerLabel">
            <i class="fa fa-bars me-1 text-success"></i>菜单位置
        </h6>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="关闭"></button>
    </div>
    <div class="offcanvas-body p-0" id="menuLocationDrawerBody">
        <?php echo $this->partial('menu_location._list', ['locations' => \zapcms\services\MenuLocation::instance()->all()]); ?>
    </div>
    <div class="offcanvas-footer border-top p-2 small text-muted bg-body-tertiary">
        选择位置后管理其分配菜单的菜单项（栏目 / 内容 / 自定义链接）；前台用
        <code>catalog_menu('key')</code> 输出对应位置菜单。
    </div>
</div>

<?php echo $this->partial('menu_location._scripts'); ?>
