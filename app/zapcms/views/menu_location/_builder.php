<?php
/**
 * WordPress 式菜单管理器（抽屉二级）
 * 左栏：添加项（栏目 / 内容 / 自定义链接）；右栏：菜单结构（拖拽排序 / 拖成子菜单 / 改文案 / 删除）。
 *
 * @var string $location_key
 * @var array  $items
 * @var array  $parent_options  [menu_item_id => [可选父级项...]]
 * @var array  $resolved        [menu_item_id => ['title','url','item_type',...]]
 * @var array  $locations       全部菜单位置
 */
use zap\facades\Url;

$parentMap = [];
foreach ($items as $it) {
    $parentMap[(int) $it['id']] = (int) $it['parent_id'];
}
?>
<style>
    .mi-handle { cursor: grab; }
    .mi-handle:active { cursor: grabbing; }
    .mi-dragging-src { display: none !important; }
    .mi-ghost { background: #fff; border: 1px solid #dee2e6; border-radius: .375rem; }
    .mi-drop-before { box-shadow: inset 0 3px 0 #0d6efd; }
    .mi-drop-after  { box-shadow: inset 0 -3px 0 #0d6efd; }
    .mi-drop-child  { box-shadow: inset 0 0 0 2px #198754; }
</style>
<div class="d-flex align-items-center px-2 py-2 border-bottom bg-light gap-2 flex-wrap">
    <button class="btn btn-sm btn-link text-decoration-none px-0 flex-shrink-0" onclick="mlBackToList()" title="位置管理">
        <i class="fa fa-arrow-left"></i>
    </button>
    <label class="small text-muted mb-0">编辑菜单</label>
    <select id="mlmLocation" class="form-select form-select-sm d-inline-block" style="width:auto"
            onchange="mlOpenBuilder(this.value)">
        <?php foreach ($locations as $loc): ?>
            <option value="<?php echo e($loc['key']); ?>" <?php echo $loc['key'] === $location_key ? 'selected' : ''; ?>>
                <?php echo e($loc['title']); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <code class="small text-muted ms-1"><?php echo e($location_key); ?></code>
</div>

<div class="container-fluid py-2">
    <div class="row g-2">
        <!-- 左栏：添加菜单项 -->
        <div class="col-12 col-lg-4 border-lg-end">
            <div class="accordion" id="mlmAccordion">
                <!-- 栏目 -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#mlmBoxCatalog">
                            栏目
                        </button>
                    </h2>
                    <div id="mlmBoxCatalog" class="accordion-collapse collapse" data-bs-parent="#mlmAccordion">
                        <div class="accordion-body small py-2">
                            <div class="input-group input-group-sm mb-2">
                                <input type="text" id="mlmKw_catalog" class="form-control" placeholder="搜索栏目标题 / ID…">
                                <button class="btn btn-outline-secondary" type="button" onclick="mlmSearch('catalog')"><i class="fa fa-search"></i></button>
                            </div>
                            <div id="mlmRes_catalog" class="border rounded mb-2" style="max-height:260px;overflow:auto;">
                                <div class="text-muted p-2">输入关键字搜索后勾选，再点「添加选中」。</div>
                            </div>
                            <button class="btn btn-success btn-sm w-100" type="button" onclick="mlmAddSelected()">
                                <i class="fa fa-plus me-1"></i>添加选中
                            </button>
                        </div>
                    </div>
                </div>
                <!-- 内容(node) -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#mlmBoxNode">
                            内容（node）
                        </button>
                    </h2>
                    <div id="mlmBoxNode" class="accordion-collapse collapse" data-bs-parent="#mlmAccordion">
                        <div class="accordion-body small py-2">
                            <div class="input-group input-group-sm mb-2">
                                <input type="text" id="mlmKw_node" class="form-control" placeholder="搜索内容标题 / ID…">
                                <button class="btn btn-outline-secondary" type="button" onclick="mlmSearch('node')"><i class="fa fa-search"></i></button>
                            </div>
                            <div id="mlmRes_node" class="border rounded mb-2" style="max-height:260px;overflow:auto;">
                                <div class="text-muted p-2">输入关键字搜索后勾选，再点「添加选中」。</div>
                            </div>
                            <button class="btn btn-success btn-sm w-100" type="button" onclick="mlmAddSelected()">
                                <i class="fa fa-plus me-1"></i>添加选中
                            </button>
                        </div>
                    </div>
                </div>
                <!-- 自定义链接 -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#mlmBoxLink">
                            自定义链接
                        </button>
                    </h2>
                    <div id="mlmBoxLink" class="accordion-collapse collapse" data-bs-parent="#mlmAccordion">
                        <div class="accordion-body small py-2">
                            <label class="form-label mb-1">链接地址</label>
                            <input type="text" id="mlmCustomUrl" class="form-control form-control-sm mb-2" placeholder="https://example.com 或 /path">
                            <label class="form-label mb-1">显示文案（可选）</label>
                            <input type="text" id="mlmCustomLabel" class="form-control form-control-sm mb-2" placeholder="留空用链接地址">
                            <button class="btn btn-success btn-sm w-100" type="button" onclick="mlmAddCustom()">
                                <i class="fa fa-plus me-1"></i>添加链接
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 右栏：菜单结构 -->
        <div class="col-12 col-lg-8">
            <div class="d-flex align-items-center mb-2">
                <h6 class="mb-0 small text-muted">菜单结构</h6>
                <span class="ms-2 small text-muted">拖拽 <i class="fa fa-grip-vertical"></i> 排序；拖到某项中部可设为子菜单。</span>
            </div>
            <?php if (empty($items)): ?>
                <div class="text-center text-muted py-4 small border rounded">
                    该菜单还没有项目，从左侧添加栏目 / 内容 / 自定义链接。
                </div>
            <?php else: ?>
                <ul class="list-group mi-list" id="miList">
                    <?php foreach ($items as $it):
                        $res   = $resolved[(int) $it['id']] ?? [];
                        $dTitle = $res['title'] ?? '';
                        $dUrl   = $res['url'] ?? '';
                        $depth = 0;
                        $pid = (int) $it['parent_id'];
                        while ($pid > 0 && isset($parentMap[$pid]) && $depth < 20) {
                            $depth++;
                            $pid = $parentMap[$pid];
                        }
                        $indent = $depth * 18;
                        $isLink = $it['item_type'] === 'custom_link';
                        ?>
                        <li class="list-group-item mi-item py-2"
                            data-id="<?php echo e($it['id']); ?>"
                            data-parent="<?php echo e($it['parent_id']); ?>"
                            style="margin-left:<?php echo $indent; ?>px">
                            <div class="d-flex align-items-center gap-2">
                                <span class="mi-handle text-muted" title="拖拽排序 / 拖到中部设为子菜单"><i class="fa fa-grip-vertical"></i></span>
                                <div class="flex-grow-1" style="min-width:0">
                                    <input type="text" class="form-control form-control-sm mi-title"
                                           value="<?php echo e($it['title'] ?? ''); ?>"
                                           placeholder="<?php echo e($dTitle); ?>"
                                           onchange="mlSaveTitle(<?php echo e($it['id']); ?>, this.value)">
                                    <div class="small text-muted text-truncate mt-1">
                                        <span class="badge text-bg-light mi-type"><?php echo e($it['item_type']); ?></span>
                                        <span title="<?php echo e($dUrl); ?>">
                                            <?php echo $isLink ? e($dUrl) : e($dTitle . ' · ' . $dUrl); ?>
                                        </span>
                                    </div>
                                </div>
                                <select class="form-select form-select-sm mi-parent flex-shrink-0" style="width:auto"
                                        onchange="mlSaveParent(<?php echo e($it['id']); ?>, this.value)">
                                    <option value="0">— 顶级 —</option>
                                    <?php foreach (($parent_options[(int) $it['id']] ?? []) as $cp): ?>
                                        <option value="<?php echo e($cp['id']); ?>"
                                            <?php echo (int) $cp['id'] === (int) $it['parent_id'] ? 'selected' : ''; ?>>
                                            <?php echo e(($resolved[(int) $cp['id']]['title'] ?? '') ?: ('#' . $cp['id'])); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn btn-outline-danger btn-sm py-0 px-1 flex-shrink-0"
                                        onclick="mlRemoveItem(<?php echo e($it['id']); ?>)" title="删除">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </div>
                            <details class="mi-meta mt-2 border-top pt-2">
                                <summary class="small text-muted" style="cursor:pointer">高级（图标 / 徽标 / 样式 / 分栏）</summary>
                                <div class="row g-1 mt-1">
                                    <div class="col-6">
                                        <input type="text" class="form-control form-control-sm" placeholder="图标 class"
                                               value="<?php echo e($it['icon'] ?? ''); ?>"
                                               onchange="mlSaveMeta(<?php echo e($it['id']); ?>,'icon',this.value)">
                                    </div>
                                    <div class="col-6">
                                        <input type="text" class="form-control form-control-sm" placeholder="徽标文字"
                                               value="<?php echo e($it['badge'] ?? ''); ?>"
                                               onchange="mlSaveMeta(<?php echo e($it['id']); ?>,'badge',this.value)">
                                    </div>
                                    <div class="col-6">
                                        <input type="text" class="form-control form-control-sm" placeholder="图片 URL"
                                               value="<?php echo e($it['image'] ?? ''); ?>"
                                               onchange="mlSaveMeta(<?php echo e($it['id']); ?>,'image',this.value)">
                                    </div>
                                    <div class="col-6">
                                        <input type="text" class="form-control form-control-sm" placeholder="自定义 class"
                                               value="<?php echo e($it['css_class'] ?? ''); ?>"
                                               onchange="mlSaveMeta(<?php echo e($it['id']); ?>,'css_class',this.value)">
                                    </div>
                                    <div class="col-12 form-check mt-1">
                                        <input class="form-check-input" type="checkbox"
                                               id="mi_cb_<?php echo e($it['id']); ?>"
                                               <?php echo !empty($it['column_break']) ? 'checked' : ''; ?>
                                               onchange="mlSaveMeta(<?php echo e($it['id']); ?>,'column_break',this.checked?1:0)">
                                        <label class="form-check-label small text-muted" for="mi_cb_<?php echo e($it['id']); ?>">
                                            在此项之前开始新的一列（mega-menu 分栏）
                                        </label>
                                    </div>
                                </div>
                            </details>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>
