<?php

use zap\facades\Url;

IS_AJAX !== true && $this->layout('layouts/common');

/**
 * WordPress 式菜单管理（外观 > 菜单）
 * Tab1 管理菜单：菜单列表（新建 / 删除 / 进入编辑）；
 * Tab2 编辑菜单：菜单构建器（左栏添加项 / 右栏菜单结构拖拽排序）；
 * Tab3 管理位置：位置列表 + 把菜单分配到位置（前台 catalog_menu('key') 输出）。
 *
 * @var array       $menus            全部菜单实体
 * @var array       $locations        全部菜单位置（含 menu_id）
 * @var array|null  $current_menu     当前编辑的菜单
 * @var array       $items            当前菜单的菜单项（树序）
 * @var array       $resolved
 * @var array       $menu_item_counts [menu_id => 菜单项数量]
 * @var array       $menu_locations   [menu_id => [位置标题...]]
 * @var string      $active_tab       list / edit / locations
 */
$menusCount = count($menus);
?>
<style>
.mi-handle { cursor: grab; }
.mi-handle:active { cursor: grabbing; }
.mi-dragging-src { display: none !important; }
.mi-ghost { background: #fff; border: 1px solid #dee2e6; border-radius: .375rem; }
.mi-item .mi-title { min-width: 140px; }
.mi-item.mi-drop-before { box-shadow: inset 0 3px 0 #0d6efd; }
.mi-item.mi-drop-after  { box-shadow: inset 0 -3px 0 #0d6efd; }
.mi-item.mi-drop-child  { box-shadow: inset 0 0 0 2px #198754; }

/* ── 左栏「添加菜单项」面板（WordPress 式） ── */
.mlm-panel .accordion-item { border-color: #e9ecef; }
.mlm-panel .accordion-button { font-weight: 600; font-size: .875rem; padding: .55rem .75rem; color: #212529; }
.mlm-panel .accordion-button:not(.collapsed) { background: #fff; box-shadow: inset 0 -1px 0 #dee2e6; }
.mlm-panel .accordion-body { padding: .6rem .75rem .75rem; }
.mlm-list { border: 1px solid #dee2e6; border-radius: .375rem; }
.mlm-list .list-group-item { padding: .35rem .6rem; border-color: #f1f3f5; }
.mlm-list .list-group-item:hover { background: #f8f9fa; }

/* ── 右栏菜单项 ── */
.mi-item { border-color: #e9ecef; margin-bottom: .35rem; }
.mi-bar { border-radius: .25rem; transition: background .15s; }
.mi-bar:hover { background: #f8f9fa; }
.mi-type { font-weight: 500; }
.mi-type-catalog { background: #e7f1ff; color: #0d6efd; }
.mi-type-node    { background: #e6f4ea; color: #198754; }
.mi-type-custom  { background: #fff3cd; color: #997404; }
</style>
 <ul class="nav nav-tabs" style="gap:.25rem">
     <li class="nav-item">
                <button id="mlTabEdit" class="nav-link py-1 <?php echo $active_tab === 'edit' ? 'active' : ''; ?>" type="button" onclick="mlSwitchTab('edit')">
                    <i class="fa fa-pen-nib me-1"></i>编辑菜单
                </button>
            </li>
            <li class="nav-item">
                <button id="mlTabLoc" class="nav-link py-1 <?php echo $active_tab === 'locations' ? 'active' : ''; ?>" type="button" onclick="mlSwitchTab('locations')">
                    <i class="fa fa-map-marker-alt me-1"></i>管理位置
                </button>
            </li>
        </ul>

<!-- Tab 2：编辑菜单（平铺在卡片外，左添加项 / 右菜单结构，避免卡片内多层嵌套边框） -->
<div id="tabEditMenu" class="<?php echo $active_tab === 'edit' ? '' : 'd-none'; ?>">
    <!-- WordPress 式：选择要编辑的菜单 -->
    <div class="d-flex align-items-center gap-2 flex-wrap bg-white border p-2 mb-2">
        <label class="small text-muted mb-0">选择要编辑的菜单：</label>
        <select id="mlMenuSelect" class="form-select form-select-sm" style="width:auto;min-width:220px">
            <?php foreach ($menus as $m): ?>
                <option value="<?php echo (int) $m['id']; ?>" <?php echo (int) ($current_menu['id'] ?? 0) === (int) $m['id'] ? 'selected' : ''; ?>>
                    <?php echo e($m['title']); ?>（#<?php echo (int) $m['id']; ?>）
                </option>
            <?php endforeach; ?>
        </select>
        <button type="button" class="btn btn-outline-primary btn-sm" onclick="mlEditMenu($('#mlMenuSelect').val())">
            选择
        </button>
        <span class="small text-muted">
            或 <a href="javascript:;" onclick="mlCreateMenu()">创建一个新菜单</a>。不要忘记保存您的更改！
        </span>
    </div>
    <div id="menuEditor">
        <?php if (!empty($current_menu)): ?>
            <?php echo $this->partial('menu_location._builder', [
                'menu'     => $current_menu,
                'items'    => $items,
                'resolved' => $resolved,
            ]); ?>
        <?php else: ?>
            <div class="text-center text-muted py-5 border rounded bg-white">
                <i class="fa fa-list-ul fa-2x d-block mb-2 opacity-50"></i>
                <div>请先在「管理菜单」中创建或选择一个菜单。</div>
                <button type="button" class="btn btn-outline-primary btn-sm mt-2" onclick="mlCreateMenu()">
                    <i class="fa fa-plus me-1"></i>创建第一个菜单
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="mlCardWrap" class="<?php echo $active_tab === 'edit' ? 'd-none' : ''; ?>">
<div class="card ">

    <div class="card-body p-2">

        <!-- Tab 1：管理菜单 -->
        <div id="tabMenuList" class="<?php echo $active_tab === 'list' ? '' : 'd-none'; ?>">
            <div class="d-flex align-items-center mb-2">
                <span class="small text-muted">
                    菜单是独立的导航容器（如「主导航」「页脚链接」），创建后可进入编辑、再分配到位置。
                </span>
                <button type="button" class="btn btn-success btn-sm ms-auto" onclick="mlCreateMenu()">
                    <i class="fa fa-plus me-1"></i>创建新菜单
                </button>
            </div>

            <?php if ($menusCount === 0): ?>
                <div class="text-center text-muted py-5 border rounded">
                    <i class="fa fa-list-ul fa-2x d-block mb-2"></i>
                    <div>还没有菜单，点击右上角「创建新菜单」开始。</div>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr class="table-light">
                                <th>菜单名称</th>
                                <th style="width:90px" class="text-center">项目数</th>
                                <th class="d-none d-md-table-cell">已分配位置</th>
                                <th style="width:130px" class="text-end">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($menus as $m): $mid = (int) $m['id']; ?>
                            <tr>
                                <td class="align-middle">
                                    <strong><?php echo e($m['title']); ?></strong>
                                    <code class="small text-muted ms-2">#<?php echo $mid; ?></code>
                                </td>
                                <td class="text-center align-middle">
                                    <span class="badge text-bg-light"><?php echo (int) ($menu_item_counts[$mid] ?? 0); ?></span>
                                </td>
                                <td class="align-middle d-none d-md-table-cell">
                                    <?php if (!empty($menu_locations[$mid])): ?>
                                        <?php foreach ($menu_locations[$mid] as $locTitle): ?>
                                            <span class="badge text-bg-info text-dark me-1"><?php echo e($locTitle); ?></span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-muted small">— 未分配 —</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end align-middle">
                                    <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2"
                                            onclick="mlEditMenu(<?php echo $mid; ?>)" title="编辑菜单项">
                                        <i class="fa fa-pen"></i> 编辑
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2"
                                            onclick="mlDeleteMenu(<?php echo $mid; ?>)" title="删除菜单">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Tab 3：管理位置 -->
        <div id="tabLocations" class="<?php echo $active_tab === 'locations' ? '' : 'd-none'; ?>">
            <div class="d-flex align-items-center mb-2">
                <span class="small text-muted">
                    把菜单分配到显示位置；前台模板用 <code>catalog_menu('位置key')</code> 输出该位置当前分配的菜单。
                </span>
                <button type="button" class="btn btn-success btn-sm ms-auto" onclick="mlAddOrEdit(0)">
                    <i class="fa fa-plus me-1"></i>添加位置
                </button>
            </div>
            <div class="table-responsive" id="mlLocationsTable">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead>
                        <tr class="table-light">
                            <th>名称</th>
                            <th>标识 (key)</th>
                            <th class="d-none d-md-table-cell">描述</th>
                            <th style="min-width:200px">分配的菜单</th>
                            <th style="width:110px">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($locations as $loc): ?>
                        <tr>
                            <td class="small align-middle"><?php echo e($loc['title']); ?></td>
                            <td class="align-middle"><code class="small"><?php echo e($loc['key']); ?></code></td>
                            <td class="text-muted small align-middle d-none d-md-table-cell"><?php echo e($loc['description'] ?? ''); ?></td>
                            <td class="align-middle">
                                <select class="form-select form-select-sm" style="max-width:260px"
                                        onchange="mlAssign(<?php echo e($loc['id']); ?>, this)">
                                    <option value="0">— 未分配 —</option>
                                    <?php foreach ($menus as $m): ?>
                                        <option value="<?php echo e($m['id']); ?>" <?php echo (int) ($loc['menu_id'] ?? 0) === (int) $m['id'] ? 'selected' : ''; ?>>
                                            <?php echo e($m['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="align-middle">
                                <button type="button" class="btn btn-outline-info btn-sm py-0 px-1 me-1" onclick="mlAddOrEdit(<?php echo e($loc['id']); ?>)" title="编辑位置">
                                    <i class="fa fa-pen"></i>
                                </button>
                                <?php if (empty($loc['is_system'])): ?>
                                    <button type="button" class="btn btn-outline-danger btn-sm py-0 px-1" onclick="mlRemove(<?php echo e($loc['id']); ?>)" title="删除位置">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-outline-danger btn-sm py-0 px-1" disabled title="系统内置位置不可删除">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
    <div class="card-footer small text-muted">
        菜单与位置相互独立：一个菜单可分配到某个位置展示；未分配的菜单可以继续编辑备用。
        主题也可以用 <code>catalog_menu('key')</code> 在任意区域输出对应位置菜单。
    </div>
</div>
</div><!-- /#mlCardWrap -->

<?php echo $this->partial('menu_location._scripts'); ?>
