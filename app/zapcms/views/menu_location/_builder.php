<?php
/**
 * WordPress 式菜单管理器（构建器片段）
 * 左栏：添加项（栏目目录树 / 内容 / 自定义链接）；右栏：菜单结构（拖拽排序 / 拖成子菜单 / 改文案 / 删除）。
 * 可在「外观 > 菜单」整页内嵌，也可在抽屉中打开。
 *
 * @var array  $menu       菜单实体 ['id','title',...]
 * @var array  $items
 * @var array  $resolved   [menu_item_id => ['title','url','item_type',...]]
 * @var array  $locations  全部菜单位置（可选）
 */
use zap\facades\Url;

$parentMap = [];
foreach ($items as $it) {
    $parentMap[(int) $it['id']] = (int) $it['parent_id'];
}
?>
<div class="d-flex align-items-center px-2 py-2 border-bottom bg-light gap-2 flex-wrap">
    <button class="btn btn-sm btn-link text-decoration-none px-0 flex-shrink-0" onclick="mlBackToList()" title="返回">
        <i class="fa fa-arrow-left"></i>
    </button>
    <label class="small text-muted mb-0">菜单名称</label>
    <input id="mlMenuTitle" class="form-control form-control-sm d-inline-block" style="width:auto;min-width:180px"
           value="<?php echo e($menu['title'] ?? ''); ?>">
    <button type="button" class="btn btn-success btn-sm" onclick="mlSaveMenuTitle(<?php echo (int) ($menu['id'] ?? 0); ?>)">
        <i class="fa fa-save me-1"></i>保存菜单
    </button>
    <button type="button" class="btn btn-outline-danger btn-sm" onclick="mlDeleteMenu(window.__mlMenuId)">
        <i class="fa fa-trash me-1"></i>删除菜单
    </button>
    <code class="small text-muted ms-1">#<?php echo (int) ($menu['id'] ?? 0); ?></code>
    <script>window.__mlMenuId = <?php echo (int) ($menu['id'] ?? 0); ?>;</script>
</div>

<div class="container-fluid px-0 py-2">
    <div class="row g-2">
        <!-- 左栏：添加菜单项 -->
        <div class="col-12 col-lg-4 border-lg-end">
        
            <div class="accordion accordion-flush mlm-panel" id="mlmAccordion">
                <!-- 栏目（WordPress「分类目录」式树形勾选） -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button py-2" type="button" data-bs-toggle="collapse" data-bs-target="#mlmBoxCatalog">
                            <i class="fa fa-folder-tree me-2 text-primary"></i>栏目
                        </button>
                    </h2>
                    <div id="mlmBoxCatalog" class="accordion-collapse collapse show" data-bs-parent="#mlmAccordion">
                        <div class="accordion-body small py-2">
                            <a href="javascript:;" class="small text-decoration-none d-none mb-1 d-inline-block"
                               id="mlmTreeBack" onclick="mlmLoadCatalogTree()">
                                <i class="fa fa-angle-left me-1"></i>返回栏目树
                            </a>
                            <div class="input-group input-group-sm mb-2">
                                <input type="text" id="mlmKw_catalog" class="form-control" placeholder="搜索栏目 / ID…"
                                       onkeydown="if(event.key==='Enter'){mlmSearch('catalog');}">
                                <button class="btn btn-outline-secondary" type="button" onclick="mlmSearch('catalog')"><i class="fa fa-search"></i></button>
                            </div>
                            <div id="mlmRes_catalog" class="mlm-list mb-2" style="max-height:300px;overflow:auto;">
                                <div class="text-muted p-2">正在加载栏目…</div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" id="mlmTreeAll" onchange="mlmToggleAll(this)">
                                    <label class="form-check-label small text-muted" for="mlmTreeAll">全选</label>
                                </div>
                                <button class="btn btn-primary btn-sm ms-auto" type="button" onclick="mlmAddSelected()">
                                    <i class="fa fa-plus me-1"></i>添加至菜单
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- 内容(node) -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#mlmBoxNode">
                            <i class="fa fa-cube me-2 text-success"></i>内容
                        </button>
                    </h2>
                    <div id="mlmBoxNode" class="accordion-collapse collapse" data-bs-parent="#mlmAccordion">
                        <div class="accordion-body small py-2">
                            <div class="input-group input-group-sm mb-2">
                                <input type="text" id="mlmKw_node" class="form-control" placeholder="搜索内容标题 / ID…"
                                       onkeydown="if(event.key==='Enter'){mlmSearch('node');}">
                                <button class="btn btn-outline-secondary" type="button" onclick="mlmSearch('node')"><i class="fa fa-search"></i></button>
                            </div>
                            <div id="mlmRes_node" class="mlm-list mb-2" style="max-height:260px;overflow:auto;">
                                <div class="text-muted p-2">输入关键字搜索后勾选，再点「添加选中」。</div>
                            </div>
                            <button class="btn btn-primary btn-sm w-100" type="button" onclick="mlmAddSelected()">
                                <i class="fa fa-plus me-1"></i>添加选中
                            </button>
                        </div>
                    </div>
                </div>
                <!-- 单页（node_type=page） -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#mlmBoxPage">
                            <i class="fa fa-file-lines me-2 text-warning"></i>单页
                        </button>
                    </h2>
                    <div id="mlmBoxPage" class="accordion-collapse collapse" data-bs-parent="#mlmAccordion">
                        <div class="accordion-body small py-2">
                            <div class="input-group input-group-sm mb-2">
                                <input type="text" id="mlmKw_page" class="form-control" placeholder="搜索单页标题 / ID…"
                                       onkeydown="if(event.key==='Enter'){mlmSearch('page');}">
                                <button class="btn btn-outline-secondary" type="button" onclick="mlmSearch('page')"><i class="fa fa-search"></i></button>
                            </div>
                            <div id="mlmRes_page" class="mlm-list mb-2" style="max-height:260px;overflow:auto;">
                                <div class="text-muted p-2">正在加载单页…</div>
                            </div>
                            <button class="btn btn-primary btn-sm w-100" type="button" onclick="mlmAddSelected()">
                                <i class="fa fa-plus me-1"></i>添加选中
                            </button>
                        </div>
                    </div>
                </div>
                <!-- 自定义链接 -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#mlmBoxLink">
                            <i class="fa fa-link me-2 text-secondary"></i>自定义链接
                        </button>
                    </h2>
                    <div id="mlmBoxLink" class="accordion-collapse collapse" data-bs-parent="#mlmAccordion">
                        <div class="accordion-body small py-2">
                            <label class="form-label mb-1">链接地址</label>
                            <input type="text" id="mlmCustomUrl" class="form-control form-control-sm mb-2" placeholder="https://example.com 或 /path">
                            <label class="form-label mb-1">显示文案（可选）</label>
                            <input type="text" id="mlmCustomLabel" class="form-control form-control-sm mb-2" placeholder="留空用链接地址">
                            <label class="form-label mb-1">打开方式</label>
                            <select id="mlmLinkTarget" class="form-select form-select-sm mb-2">
                                <option value="_self">当前页面</option>
                                <option value="_blank">新页面</option>
                            </select>
                            <button class="btn btn-primary btn-sm w-100" type="button" onclick="mlmAddCustom()">
                                <i class="fa fa-plus me-1"></i>添加链接
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 右栏：菜单结构（WordPress 式：单行条目 + ▼ 展开编辑） -->
        <div class="col-12 col-lg-8">
            <div class="card">
                <div class="card-header bg-body-tertiary py-2 d-flex align-items-center flex-wrap">
                    <h6 class="mb-0">菜单结构</h6>
                    <span class="ms-2 small text-muted">
                        按住条目直接拖动排序；拖到某项中部、或拖到它下方并靠右，可设为它的子菜单；点 <i class="fa fa-chevron-down"></i> 编辑。
                    </span>
                </div>
                <div class="card-body p-2">
            <?php if (empty($items)): ?>
                <div class="text-center text-muted py-5 border rounded">
                    <i class="fa fa-list-ul fa-2x d-block mb-2 opacity-50"></i>
                    <div>该菜单还没有项目，从左侧添加栏目 / 内容 / 单页 / 自定义链接。</div>
                </div>
            <?php else: ?>
                <?php
                $typeLabels = ['catalog' => '栏目', 'node' => '内容', 'custom_link' => '自定义链接'];
                $typeClass  = ['catalog' => 'mi-type-catalog', 'node' => 'mi-type-node', 'custom_link' => 'mi-type-custom'];
                ?>
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
                        $indent    = $depth * 18;
                        $typeLabel = $typeLabels[$it['item_type']] ?? ($it['item_type'] ?: '—');
                        $barTitle  = ($it['title'] ?? '') !== '' ? $it['title'] : ($dTitle ?: '(未命名)');
                        $itemId    = (int) $it['id'];
                        ?>
                        <li class="list-group-item mi-item py-1"
                            data-id="<?php echo e($itemId); ?>"
                            data-parent="<?php echo e($it['parent_id']); ?>"
                            style="margin-left:<?php echo $indent; ?>px">
                            <!-- 单行条目栏：手柄 + 标题 + 子项目标识 + 类型 + 展开箭头 -->
                            <div class="mi-bar d-flex align-items-center gap-2 py-1">
                                <span class="mi-handle text-muted flex-shrink-0" title="拖拽排序 / 拖到中部设为子菜单"><i class="fa fa-grip-vertical"></i></span>
                                <div class="flex-grow-1 text-truncate" style="min-width:0">
                                    <strong><?php echo e($barTitle); ?></strong>
                                    <?php if ($depth > 0): ?>
                                        <span class="text-muted small ms-1 fst-italic">子项目</span>
                                    <?php endif; ?>
                                    <?php if ($dUrl !== ''): ?>
                                        <div class="small text-muted text-truncate"><?php echo e($dUrl); ?></div>
                                    <?php endif; ?>
                                </div>
                                <span class="badge mi-type flex-shrink-0 <?php echo e($typeClass[$it['item_type']] ?? ''); ?>"><?php echo e($typeLabel); ?></span>
                                <button type="button" class="btn btn-sm btn-link text-muted py-0 px-1 mi-toggle flex-shrink-0"
                                        title="展开编辑">
                                    <i class="fa fa-chevron-down"></i>
                                </button>
                            </div>
                            <!-- 展开后的编辑面板（默认折叠） -->
                            <div class="mi-meta-panel d-none border-top pt-2 mt-1">
                                <div class="mb-2">
                                    <label class="form-label small mb-1">导航标签</label>
                                    <input type="text" class="form-control form-control-sm mi-title"
                                           value="<?php echo e($it['title'] ?? ''); ?>"
                                           placeholder="<?php echo e($dTitle); ?>"
                                           onchange="mlSaveTitle(<?php echo e($itemId); ?>, this.value)">
                                    <div class="small text-muted text-truncate mt-1" title="<?php echo e($dUrl); ?>">
                                        <?php echo e($dUrl ?: '—'); ?>
                                    </div>
                                </div>
                                <div class="row g-1">
                                    <div class="col-6">
                                        <input type="text" class="form-control form-control-sm" placeholder="图标 class"
                                               value="<?php echo e($it['icon'] ?? ''); ?>"
                                               onchange="mlSaveMeta(<?php echo e($itemId); ?>,'icon',this.value)">
                                    </div>
                                    <div class="col-6">
                                        <input type="text" class="form-control form-control-sm" placeholder="徽标文字"
                                               value="<?php echo e($it['badge'] ?? ''); ?>"
                                               onchange="mlSaveMeta(<?php echo e($itemId); ?>,'badge',this.value)">
                                    </div>
                                    <div class="col-6">
                                        <input type="text" class="form-control form-control-sm" placeholder="图片 URL"
                                               value="<?php echo e($it['image'] ?? ''); ?>"
                                               onchange="mlSaveMeta(<?php echo e($itemId); ?>,'image',this.value)">
                                    </div>
                                    <div class="col-6">
                                        <input type="text" class="form-control form-control-sm" placeholder="自定义 class"
                                               value="<?php echo e($it['css_class'] ?? ''); ?>"
                                               onchange="mlSaveMeta(<?php echo e($itemId); ?>,'css_class',this.value)">
                                    </div>
                                    <div class="col-12 form-check mt-1">
                                        <input class="form-check-input" type="checkbox"
                                               id="mi_cb_<?php echo e($itemId); ?>"
                                               <?php echo !empty($it['column_break']) ? 'checked' : ''; ?>
                                               onchange="mlSaveMeta(<?php echo e($itemId); ?>,'column_break',this.checked?1:0)">
                                        <label class="form-check-label small text-muted" for="mi_cb_<?php echo e($itemId); ?>">
                                            在此项之前开始新的一列（mega-menu 分栏）
                                        </label>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <a href="javascript:;" class="link-danger small text-decoration-none"
                                       onclick="mlRemoveItem(<?php echo e($itemId); ?>)">
                                        <i class="fa fa-trash me-1"></i>移除
                                    </a>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
                </div><!-- /.card-body -->
            </div><!-- /.card -->
        </div>
    </div>
</div>

<script>
    // 构建器片段被 AJAX 注入（抽屉 / 整页）后加载栏目目录树；
    // jQuery 对 html() 注入的 <script> 会求值，因此这里直接触发即可。
    if (window.jQuery && typeof mlmLoadCatalogTree === 'function' && $('#mlmRes_catalog').length) {
        mlmLoadCatalogTree();
    }
    // 单页面板默认加载 node_type=page 的内容列表
    if (window.jQuery && typeof mlmSearch === 'function' && $('#mlmRes_node_page').length) {
        mlmSearch('node', 'page');
    }
</script>
