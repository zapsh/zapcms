<?php
/**
 * 后台内容列表筛选栏（WordPress 风格）
 *
 * @var string $_controller
 * @var string $node_type
 * @var int    $catalogId
 * @var array  $catalogPaths
 * @var array  $status_counts
 */

use zap\facades\Url;
use zapcms\services\NodeType;

$filterType    = strtolower((string) ($node_type ?? 'default'));
$activeType    = trim((string) ($_GET['type'] ?? ''));
$selectedType  = $filterType === 'default' ? $activeType : $filterType;
$types         = NodeType::getNodeTypes();
$currentStatus = (string) ($_GET['status'] ?? '');
if (!in_array($currentStatus, ['publish', 'draft', 'trash', 'soft_delete'], true)) {
    $currentStatus = '';
}
$statusKey     = $currentStatus === 'soft_delete' ? 'trash' : $currentStatus;
$withChild     = !empty($_GET['with_child']);
$keyword       = (string) ($_GET['s'] ?? '');
$statusCounts  = $status_counts ?? [];
$pageObj       = $page ?? null;

// 单页入口把 cid 用作“编辑哪一页”，列表筛选改用 filter_cid，避免筛选时误入编辑表单
$listCatalogId = $filterType === 'page' ? (int) ($_GET['filter_cid'] ?? 0) : (int) $catalogId;
$cidParam      = $filterType === 'page' ? 'filter_cid' : 'cid';

$currentCatName = '';
if ($listCatalogId > 0 && !empty($catalogPaths)) {
    $lastPath = end($catalogPaths);
    $currentCatName = (string) ($lastPath['title'] ?? '');
}
if ($filterType === 'page' && $listCatalogId > 0 && $currentCatName === '') {
    $catalogRow = \zapcms\services\Catalog::instance()->get($listCatalogId);
    if ($catalogRow) {
        $currentCatName = (string) ($catalogRow['title'] ?? '');
    }
}

// 卡片内只显示“当前视图”，页面标题/面包屑由布局统一渲染，避免重复“内容管理”
$viewTitle = $selectedType !== '' && isset($types[$selectedType])
    ? (string) ($types[$selectedType]['title'] ?? $selectedType)
    : '全部内容';
if ($listCatalogId > 0 && $currentCatName !== '') {
    $viewTitle = $currentCatName;
}

$addLabels  = [
    'article'  => '添加文章',
    'product'  => '添加产品',
    'page'     => '添加单页',
    'faq'      => '添加 FAQ',
    'video'    => '添加视频',
    'download' => '添加下载',
];
$addLabel = $addLabels[$selectedType] ?? '添加内容';
$addController = ($filterType === 'default' && $selectedType !== '') ? $selectedType : ($_controller ?? 'default');
$addUrl   = Url::action('Node@' . $addController . '/add', ['cid' => $catalogId]);

$statusUrl = static function (string $status) use ($filterType): string {
    $params = $_GET;
    unset($params['page'], $params['status']);
    if ($status !== '') {
        $params['status'] = $status;
    }
    $base = $filterType === 'default' ? Url::action('Node') : Url::action('Node@' . ucfirst($filterType));
    return $base . ($params ? '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986) : '');
};

$perPageOptions = [10, 20, 50, 100];
$currentPerPage = $pageObj ? (int) $pageObj->getLimit() : 20;
if ($currentPerPage > 0 && !in_array($currentPerPage, $perPageOptions, true)) {
    $perPageOptions[] = $currentPerPage;
    sort($perPageOptions, SORT_NUMERIC);
}

if (!function_exists('zap_per_page_selector')) {
    /**
     * 每页条数选择器（放在列表左下角，提交顶部筛选表单）
     */
    function zap_per_page_selector(int $current, array $options = [10, 20, 50, 100]): string
    {
        if ($current > 0 && !in_array($current, $options, true)) {
            $options[] = $current;
            sort($options, SORT_NUMERIC);
        }
        $html = '<label class="d-inline-flex align-items-center gap-1 small text-muted mb-0">每页'
            . '<select name="per_page" id="nodeListPerPage" form="nodeListFilterForm" '
            . 'class="form-select form-select-sm zap-per-page">';
        foreach ($options as $option) {
            $option = (int) $option;
            $html .= '<option value="' . $option . '"' . ($current === $option ? ' selected' : '') . '>'
                . $option . '</option>';
        }
        return $html . '</select></label>';
    }
}

if (!function_exists('zap_list_badge')) {
    function zap_list_badge(string $label, string $tone = 'neutral'): string
    {
        if ($tone === 'soft_delete') {
            $tone = 'trash';
        }
        $classes = [
            'type'    => 'zap-badge zap-badge-type',
            'publish' => 'zap-badge zap-badge-publish',
            'draft'   => 'zap-badge zap-badge-draft',
            'trash'   => 'zap-badge zap-badge-trash',
            'neutral' => 'zap-badge zap-badge-draft',
        ];
        return '<span class="' . ($classes[$tone] ?? $classes['neutral']) . '">' . e($label) . '</span>';
    }
}

if (!function_exists('zap_tag_list')) {
    /**
     * 标签徽章列表（点击可按标签筛选当前列表）
     *
     * @param array $titles 标签标题
     * @param int   $limit  最多显示数量，0 表示全部
     * @return string
     */
    function zap_tag_list(array $titles, int $limit = 5): string
    {
        $titles = array_values(array_filter(array_map('strval', $titles), static function ($t) {
            return trim($t) !== '';
        }));
        if ($titles === []) {
            return '<span class="text-muted">-</span>';
        }
        $shown      = $limit > 0 ? array_slice($titles, 0, $limit) : $titles;
        $activeTag  = trim((string) ($_GET['tag'] ?? ''));
        $baseParams = $_GET;
        unset($baseParams['page'], $baseParams['tag']);
        $baseUrl    = current_url();
        $html       = '';
        foreach ($shown as $title) {
            $params = $baseParams;
            $params['tag'] = $title;
            $url = $baseUrl . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
            $class = 'zap-tag-chip zap-tag-chip-link' . ($activeTag === $title ? ' zap-tag-chip-active' : '');
            $html .= '<a href="' . e($url) . '" class="' . $class . '" title="' . e('按标签「' . $title . '」筛选') . '">'
                . e($title) . '</a>';
        }
        if ($limit > 0 && count($titles) > $limit) {
            $html .= '<span class="zap-tag-more" title="' . e(implode(', ', $titles)) . '">+' . (count($titles) - $limit) . '</span>';
        }
        return $html;
    }
}

if (!function_exists('zap_catalog_path_links')) {
    function zap_catalog_path_links(array $paths, string $baseUrl, string $cidParam = 'cid'): string
    {
        $titles = array_map(static function ($path): string {
            return is_array($path) ? (string) ($path['title'] ?? '') : (string) $path;
        }, $paths);
        if ($titles === []) {
            return '<span class="text-muted">-</span>';
        }
        $short = array_slice($paths, max(0, count($paths) - 2));
        $links = [];
        foreach ($short as $path) {
            $title = is_array($path) ? (string) ($path['title'] ?? '') : (string) $path;
            $id    = is_array($path) ? (int) ($path['id'] ?? 0) : 0;
            if ($id > 0) {
                $separator = str_contains($baseUrl, '?') ? '&' : '?';
                $url = $baseUrl . $separator . $cidParam . '=' . $id;
                $links[] = '<a href="' . e($url) . '" class="text-decoration-none" title="按此栏目筛选">' . e($title) . '</a>';
            } else {
                $links[] = e($title);
            }
        }
        return '<span title="' . e(implode(' / ', $titles)) . '">' . implode(' / ', $links) . '</span>';
    }
}

if (!function_exists('zap_node_title_cell')) {
    /**
     * 列表标题单元格：标题 + 悬浮快捷操作（编辑/快速编辑/回收站/删除）+ 移动端展开详情。
     *
     * @param array $row 当前行数据
     * @param array $ctx controller / catalog_id / paths / list_columns / cid_param
     * @return string 完整的 <td>...</td>
     */
    function zap_node_title_cell(array $row, array $ctx): string
    {
        $controller = strtolower((string)($ctx['controller'] ?? 'default'));
        // 通用内容列表每一行的编辑入口要用行自身的模型控制器
        $editController = strtolower((string)($ctx['edit_controller'] ?? $controller));
        $catalogId  = (int)($ctx['catalog_id'] ?? 0);
        $paths      = (array)($ctx['paths'] ?? []);
        $columns    = (array)($ctx['list_columns'] ?? []);
        $cidParam   = (string)($ctx['cid_param'] ?? 'cid');
        $id         = (int)($row['id'] ?? 0);
        $title      = (string)($row['title'] ?? '');
        $slug       = (string)($row['slug'] ?? '');
        $status     = (string)($row['status'] ?? 'draft');
        $nodeType   = (string)($row['node_type'] ?? $controller);

        // 单页入口的 edit 用 id 定位，不依赖 cid（与单页列表原有链接保持一致）
        $editUrl  = Url::action("Node@{$editController}/edit/{$id}", $editController === 'page' ? [] : ['cid' => $catalogId]);
        $listBase = $controller === 'default'
            ? Url::action('Node')
            : Url::action('Node@' . ucfirst($controller));

        // WordPress 风格：hover 时在标题下方显示文字快捷操作，用竖线分隔
        $quickActions = [
            '<a href="' . e($editUrl) . '" class="zap-title-action">编辑</a>',
            '<button type="button" class="zap-title-action" onclick="zapOpenQuickEdit(this)">快速编辑</button>',
            '<button type="button" class="zap-title-action zap-title-action-trash" onclick="zapTrashNode(this)">移至回收站</button>',
        ];
        if ($slug !== '' && function_exists('smart_node_url')) {
            $quickActions[] = '<a href="' . e(smart_node_url($row)) . '" target="_blank" class="zap-title-action">查看</a>';
        }
        $quickActions[] = '<button type="button" class="zap-title-action zap-title-action-delete" onclick="zapDeleteNode(this)">彻底删除</button>';

        $h  = '<td class="zap-title-cell">';
        $h .= '<div class="zap-title-inner">';
        $h .= '<button type="button" class="btn btn-sm zap-row-toggle d-md-none" onclick="zapToggleRow(this)" aria-label="展开详情"><i class="fa fa-chevron-right"></i></button>';
        $h .= '<div class="zap-title-wrap" data-node-id="' . $id . '" data-node-title="' . e($title) . '" data-node-slug="' . e($slug) . '" data-node-status="' . e($status) . '" data-node-top="' . (!empty($row['is_top']) ? '1' : '0') . '">';
        $h .= '<div class="zap-title-line">';
        $h .= '<a href="' . e($editUrl) . '" class="text-decoration-none fw-semibold node-title"' . ($slug !== '' ? ' title="' . e($slug) . '"' : '') . '>' . e($title) . '</a>';
        if (!empty($row['is_top'])) {
            $h .= '<span class="zap-top-badge" title="已置顶"><i class="fa fa-thumbtack"></i>置顶</span>';
        }
        $h .= '</div>';
        $h .= '<span class="zap-title-actions" role="group" aria-label="快捷操作">'
            . implode('<span class="zap-title-action-sep" aria-hidden="true">|</span>', $quickActions)
            . '</span>';

        // 桌面端由独立列展示这些信息，展开面板只在移动端出现（置于 wrap 内以复用置顶等按钮逻辑）
        $h .= '<div class="zap-row-extra mt-2 pt-2 border-top small text-muted">';
        $h .= '<div class="zap-extra-grid">';
        $h .= '<div><span class="zap-extra-label">栏目</span>' . zap_catalog_path_links($paths, $listBase, $cidParam) . '</div>';
        $h .= '<div><span class="zap-extra-label">类型</span>' . zap_list_badge(\zapcms\models\Node::getTypeTitle($nodeType), 'type') . '</div>';
        $h .= '<div><span class="zap-extra-label">状态</span><span class="zap-row-status-badge" data-node-id="' . $id . '">' . zap_list_badge(\zapcms\models\Node::getStatusTitle($status), $status) . '</span></div>';
        $h .= '<div><span class="zap-extra-label">日期</span>' . date('Y-m-d H:i', (int)($row['pub_time'] ?? $row['add_time'] ?? 0)) . '</div>';
        if (!empty($row['tags'])) {
            $h .= '<div class="zap-extra-tags"><span class="zap-extra-label">标签</span>' . zap_tag_list((array)$row['tags']) . '</div>';
        }
        foreach ($columns as $lc) {
            $field = (string)($lc['field_name'] ?? '');
            $label = (string)($lc['field_label'] ?? $field);
            $mv    = $row['meta'][$field] ?? '';
            $decoded = json_decode((string)$mv, true);
            if (is_array($decoded)) { $mv = implode(', ', $decoded); }
            $h .= '<div><span class="zap-extra-label">' . e($label) . '</span>' . (($mv !== '' && $mv !== null) ? e((string)$mv) : '-') . '</div>';
        }
        $h .= '</div>';

        // 移动端操作按钮组（与桌面列一致）
        $extraActions = '<div class="zap-row-actions mt-2 d-md-none">';
        if ($slug !== '' && function_exists('smart_node_url')) {
            $extraActions .= '<a href="' . e(smart_node_url($row)) . '" target="_blank" class="btn" title="前台查看"><i class="fa fa-eye fa-fw"></i></a>';
        }
        $extraActions .= '<a href="' . e($editUrl) . '" class="btn" title="编辑"><i class="fa fa-edit fa-fw"></i></a>';
        $extraActions .= '<button type="button" class="btn zap-top-action' . (!empty($row['is_top']) ? ' active' : '') . '" onclick="zapToggleTop(this)" title="' . (!empty($row['is_top']) ? '取消置顶' : '置顶') . '"><i class="fa fa-thumbtack fa-fw"></i></button>';
        $extraActions .= '<button type="button" class="btn zap-row-action-delete" onclick="deleteNode(' . $id . ',\'' . htmlspecialchars(addslashes($title), ENT_QUOTES) . '\')" title="删除"><i class="fa fa-trash fa-fw"></i></button>';
        $extraActions .= '</div>';
        $h .= $extraActions;
        $h .= '</div>';

        $h .= '</div>';
        $h .= '</div>';
        $h .= '</td>';
        return $h;
    }
}
?>
<div class="zap-list-toolbar px-3 pt-3 pb-2 border-bottom">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
        <span class="zap-view-title"><?php echo e($viewTitle); ?></span>
        <a href="<?php echo e($addUrl); ?>" class="btn btn-primary btn-sm zap-add-btn">
            <i class="fa fa-plus me-1"></i><?php echo e($addLabel); ?>
        </a>
    </div>

    <form method="get" class="d-flex flex-wrap align-items-center gap-2" id="nodeListFilterForm">
        <?php if ($filterType === 'default' && $selectedType !== ''): ?>
            <input type="hidden" name="type" value="<?php echo e($selectedType); ?>">
        <?php endif; ?>
        <?php if ($currentStatus !== ''): ?>
            <input type="hidden" name="status" value="<?php echo e($currentStatus); ?>">
        <?php endif; ?>
        <?php if ($listCatalogId > 0): ?>
            <input type="hidden" name="<?php echo e($cidParam); ?>" value="<?php echo e($listCatalogId); ?>">
        <?php endif; ?>
        <?php if ($withChild): ?>
            <input type="hidden" name="with_child" value="1">
        <?php endif; ?>

        <ul class="nav zap-status-pills mb-0">
            <?php
            $statusTabs = [
                ['', '全部', 'all'],
                ['publish', '已发布', 'publish'],
                ['draft', '草稿', 'draft'],
                ['trash', '回收站', 'trash'],
            ];
            foreach ($statusTabs as $statusTab):
                [$tabStatus, $tabTitle, $tabCountKey] = $statusTab;
            ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo $statusKey === $tabStatus ? 'active' : ''; ?>"
                       href="<?php echo e($statusUrl($tabStatus)); ?>">
                        <?php echo e($tabTitle); ?>
                        <span class="zap-status-count"><?php echo (int) ($statusCounts[$tabCountKey] ?? 0); ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <div class="input-group input-group-sm zap-search-box">
            <span class="input-group-text bg-white border-end-0"><i class="fa fa-search text-muted"></i></span>
            <input type="search" name="s" class="form-control border-start-0"
                   value="<?php echo htmlspecialchars($keyword, ENT_QUOTES); ?>" placeholder="搜索标题 / 别名">
        </div>

        <?php
        $activeTag = trim((string) ($_GET['tag'] ?? ''));
        if ($activeTag !== ''):
            $tagParams = $_GET;
            unset($tagParams['page'], $tagParams['tag']);
            $tagBase = Url::action($filterType === 'default' ? 'Node' : 'Node@' . ucfirst($filterType));
            $tagClearUrl = $tagBase . ($tagParams ? '?' . http_build_query($tagParams, '', '&', PHP_QUERY_RFC3986) : '');
        ?>
        <a class="zap-tag-chip zap-tag-chip-active text-decoration-none" href="<?php echo e($tagClearUrl); ?>"
           title="清除标签筛选"><i class="fa fa-tag me-1"></i><?php echo e($activeTag); ?><i class="fa fa-times ms-1"></i></a>
        <?php endif; ?>

        <button class="btn btn-primary btn-sm" type="submit">
            <i class="fa fa-filter me-1"></i>筛选
        </button>
        <a class="btn btn-outline-secondary btn-sm"
           href="<?php echo e(Url::action($filterType === 'default' ? 'Node' : 'Node@' . ucfirst($filterType))); ?>">重置</a>

        <div class="d-flex align-items-center gap-2 small text-muted ms-auto zap-list-summary">
            <span><?php echo $pageObj ? (int) $pageObj->total() : 0; ?> 项</span>
            <?php if ($pageObj && $pageObj->totalPages() > 1): ?>
                <span class="list-top-pager"><?php echo $pageObj->render(); ?></span>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- 快速编辑弹窗（列表标题悬浮操作复用） -->
<div class="modal fade" id="zapQuickEditModal" tabindex="-1" aria-labelledby="zapQuickEditTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title fs-6" id="zapQuickEditTitle">快速编辑</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="关闭"></button>
            </div>
            <div class="modal-body py-2">
                <input type="hidden" id="zapQeId">
                <div class="mb-2">
                    <label for="zapQeTitle" class="form-label small mb-1">标题</label>
                    <input type="text" class="form-control form-control-sm" id="zapQeTitle" maxlength="255">
                </div>
                <div class="mb-2">
                    <label for="zapQeSlug" class="form-label small mb-1">别名</label>
                    <input type="text" class="form-control form-control-sm" id="zapQeSlug" maxlength="255">
                </div>
                <div class="mb-0">
                    <label for="zapQeStatus" class="form-label small mb-1">状态</label>
                    <select class="form-select form-select-sm" id="zapQeStatus">
                        <option value="publish">已发布</option>
                        <option value="draft">草稿</option>
                        <option value="trash">回收站</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">取消</button>
                <button type="button" class="btn btn-sm btn-primary" onclick="zapSaveQuickEdit()">保存</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    // 每页选择器在列表底部，脚本执行时尚未渲染，用委托绑定
    document.addEventListener('change', function (e) {
        if (e.target && e.target.id === 'nodeListPerPage') {
            var form = document.getElementById('nodeListFilterForm');
            if (form) { form.submit(); }
        }
    });

    window.applyNodeBatchAction = function () {
        var sel = document.getElementById('nodeBatchAction');
        var action = sel ? sel.value : '';
        if (!action) { if (window.ZapToast) { ZapToast.warning('请选择批量操作'); } return; }
        if (action === 'move') { window.nodeBatchMove(); } else { window.nodeBatch(action); }
    };

    document.addEventListener('change', function (e) {
        if (!e.target || e.target.id !== 'nodeBatchAction') { return; }
        var box = document.getElementById('nodeBatchMoveBox');
        if (box) { box.classList.toggle('d-none', e.target.value !== 'move'); }
    });
})();

// ── 标题悬浮快捷操作：编辑 / 快速编辑 / 回收站 / 彻底删除 + 移动端展开 ──
(function () {
    <?php $actionController = strtolower((string)($_controller ?? 'default')); ?>
    var ctl = <?php echo json_encode($actionController, JSON_HEX_TAG | JSON_HEX_AMP); ?>;
    var actions = {
        trash: <?php echo json_encode(Url::action("Node@{$actionController}/trash"), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?>,
        quickEdit: <?php echo json_encode(Url::action("Node@{$actionController}/quickEdit"), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?>,
        top: <?php echo json_encode(Url::action("Node@{$actionController}/top"), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?>
    };
    window.ZAP_NODE_ACTIONS = actions;

    function wrapOf(btn) {
        if (!btn) { return null; }
        return btn.closest('.zap-title-wrap')
            || (btn.closest('tr') && btn.closest('tr').querySelector('.zap-title-wrap'));
    }
    function modalInstance() {
        var el = document.getElementById('zapQuickEditModal');
        return (window.bootstrap && bootstrap.Modal && el) ? bootstrap.Modal.getOrCreateInstance(el) : null;
    }

    window.zapToggleRow = function (btn) {
        var tr = btn ? btn.closest('tr') : null;
        if (tr) { tr.classList.toggle('is-expanded'); }
    };

    window.zapDeleteNode = function (btn) {
        var w = wrapOf(btn);
        if (!w || typeof window.deleteNode !== 'function') { return; }
        window.deleteNode(parseInt(w.dataset.nodeId, 10), w.dataset.nodeTitle || '');
    };

    // 置顶 / 取消置顶：就地更新 badge 与图标，不刷新整页
    window.zapToggleTop = function (btn) {
        var w = wrapOf(btn);
        if (!w) { return; }
        var id = parseInt(w.dataset.nodeId, 10);
        var next = w.dataset.nodeTop === '1' ? 0 : 1;
        var load = Zap.loading('正在保存...');
        $.ajax({
            url: actions.top, method: 'post', data: {id: id, is_top: next}, dataType: 'json',
            success: function (res) {
                if (res.code !== 0) { Swal.fire({title:'操作失败', text:res.msg||'操作失败', icon:'error'}); return; }
                w.dataset.nodeTop = String(next);
                var wrap = w;
                var line = wrap && wrap.querySelector(':scope > .zap-title-line');
                var badge = line && line.querySelector(':scope > .zap-top-badge');
                if (next) {
                    if (!badge && line) {
                        badge = document.createElement('span');
                        badge.className = 'zap-top-badge';
                        badge.title = '已置顶';
                        badge.innerHTML = '<i class="fa fa-thumbtack"></i>置顶';
                        line.appendChild(badge);
                    }
                } else if (badge) { badge.remove(); }
                $('.zap-top-action', btn.closest('tr')).toggleClass('active', !!next)
                    .attr('title', next ? '取消置顶' : '置顶');
                if (window.ZapToast) { ZapToast.success(res.msg || (next ? '已置顶' : '已取消置顶')); }
            },
            error: function () { Swal.fire({title:'操作失败', text:'网络异常，请稍后重试', icon:'error'}); }
        }).always(function () { load.dispose(); });
    };

    window.zapTrashNode = function (btn) {
        var w = wrapOf(btn);
        if (!w) { return; }
        var id = parseInt(w.dataset.nodeId, 10);
        var title = w.dataset.nodeTitle || '';
        Swal.fire({
            title: '移到回收站',
            html: '确定把 <strong>"' + title + '"</strong> 移到回收站吗？<br><span class="small text-muted">可在「回收站」筛选中找回</span>',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="fa fa-trash-can me-1"></i>移到回收站',
            cancelButtonText: '取消',
            reverseButtons: true
        }).then(function (r) {
            if (!r.isConfirmed) { return; }
            var load = Zap.loading('正在处理...');
            $.ajax({
                url: actions.trash, method: 'post', data: {id: id}, dataType: 'json',
                success: function (res) {
                    if (res.code === 0) {
                        Swal.fire({title: '已移到回收站', text: res.msg || '', icon: 'success', timer: 1500, showConfirmButton: false})
                            .then(function () { location.reload(); });
                    } else {
                        Swal.fire({title: '操作失败', text: res.msg || '操作失败', icon: 'error'});
                    }
                },
                error: function () { Swal.fire({title: '操作失败', text: '网络异常，请稍后重试', icon: 'error'}); }
            }).always(function () { load.dispose(); });
        });
    };

    window.zapOpenQuickEdit = function (btn) {
        var w = wrapOf(btn);
        if (!w) { return; }
        var modal = modalInstance();
        if (!modal) {
            var link = w.querySelector('.node-title');
            if (link) { location.href = link.href; }
            return;
        }
        document.getElementById('zapQeId').value = w.dataset.nodeId || '';
        document.getElementById('zapQeTitle').value = w.dataset.nodeTitle || '';
        document.getElementById('zapQeSlug').value = w.dataset.nodeSlug || '';
        document.getElementById('zapQeStatus').value = w.dataset.nodeStatus || 'draft';
        modal.show();
    };

    window.zapSaveQuickEdit = function () {
        var id = parseInt(document.getElementById('zapQeId').value, 10);
        var title = document.getElementById('zapQeTitle').value.trim();
        var slug = document.getElementById('zapQeSlug').value.trim();
        var status = document.getElementById('zapQeStatus').value;
        if (!title) { if (window.ZapToast) { ZapToast.warning('请输入标题'); } return; }
        var load = Zap.loading('正在保存...');
        $.ajax({
            url: actions.quickEdit, method: 'post',
            data: {id: id, title: title, slug: slug, status: status}, dataType: 'json',
            success: function (res) {
                if (res.code !== 0) {
                    Swal.fire({title: '保存失败', text: res.msg || '操作失败', icon: 'error'});
                    return;
                }
                var wrap = document.querySelector('.zap-title-wrap[data-node-id="' + id + '"]');
                if (wrap) {
                    wrap.dataset.nodeTitle = res.title;
                    wrap.dataset.nodeSlug = slug;
                    wrap.dataset.nodeStatus = res.status;
                    var link = wrap.querySelector('.node-title');
                    if (link) { link.textContent = res.title; }
                }
                document.querySelectorAll('.zap-row-status-badge[data-node-id="' + id + '"]').forEach(function (badge) {
                    var titles = {publish: '已发布', draft: '草稿', trash: '回收站', soft_delete: '软删除'};
                    badge.textContent = titles[res.status] || res.status;
                    badge.className = 'zap-row-status-badge zap-badge ' + (res.status === 'publish' ? 'zap-badge-publish' : res.status === 'trash' ? 'zap-badge-trash' : 'zap-badge-draft');
                });
                var modal = modalInstance();
                if (modal) { modal.hide(); }
                if (window.ZapToast) { ZapToast.success(res.msg || '保存成功'); }
            },
            error: function () { Swal.fire({title: '保存失败', text: '网络异常，请稍后重试', icon: 'error'}); }
        }).always(function () { load.dispose(); });
    };

    document.addEventListener('keydown', function (e) {
        if (e.target && e.target.id === 'zapQeTitle' && e.key === 'Enter') {
            e.preventDefault();
            zapSaveQuickEdit();
        }
    });
})();
</script>
<style>
/* 内容管理列表：只隐藏重复的页面标题/面包屑，保留顶部栏目导航 */
.app-main > .app-content-header > .container-fluid > .row { display: none !important; }
.app-main > .app-content-header { padding: 0 !important; border-bottom: 0 !important; }
.app-main > .app-content-header .zap-top-catalog-nav {
    padding: .35rem 0; margin-top: 0; border-top: 0;
}
.zap-list-toolbar { background: #fff; }
.zap-view-title { font-size: 1rem; font-weight: 600; color: #343a40; }
.zap-add-btn { box-shadow: 0 1px 2px rgba(13, 110, 253, .18); }

.zap-status-pills .nav-link {
    padding: .25rem .7rem;
    margin-right: .25rem;
    border: 1px solid transparent;
    border-radius: 999px;
    background: #f5f6f8;
    color: #6c757d;
    font-size: .8125rem;
}
.zap-status-pills .nav-link:hover { background: #eceff3; color: #495057; }
.zap-status-pills .nav-link.active {
    background: rgba(13, 110, 253, .10);
    border-color: rgba(13, 110, 253, .18);
    color: #0d6efd;
    font-weight: 600;
}
.zap-status-count { margin-left: .35rem; font-size: .75rem; opacity: .75; }

.zap-search-box { width: 230px; }
.zap-per-page { width: 72px; }
.zap-list-summary { white-space: nowrap; }

.list-top-pager .pagination { margin: 0; gap: 2px; }
.list-top-pager .page-link {
    padding: .25rem .55rem;
    font-size: .8125rem;
    line-height: 1.4;
    border-radius: .375rem;
}
.list-top-pager .page-item.active .page-link {
    background: #0d6efd;
    border-color: #0d6efd;
    color: #fff;
}
.card-footer .pagination .page-link { border-radius: .375rem; margin: 0 1px; }
.card-footer .pagination .page-item.active .page-link {
    background: #0d6efd;
    border-color: #0d6efd;
    color: #fff;
}

.zap-badge {
    display: inline-block;
    padding: .28em .6em;
    font-size: .75rem;
    font-weight: 500;
    line-height: 1;
    border: 1px solid transparent;
    border-radius: 999px;
}
.zap-badge-type { background: #f0f3f7; color: #5b6b7f; border-color: #e1e7ee; }
.zap-badge-publish { background: #eef7f1; color: #4f7a5f; border-color: #d8e9de; }
.zap-badge-draft { background: #f4f5f7; color: #6c757d; border-color: #e3e6ea; }
.zap-badge-trash { background: #fbf0f0; color: #a46565; border-color: #f0dede; }

.zap-node-list .table > :not(caption) > * > * { padding: .7rem .75rem; vertical-align: middle; }
.zap-node-list .table thead th { color: #6c757d; font-size: .75rem; font-weight: 600; border-bottom: 1px solid #e9ecef; }
.zap-node-list .node-title { font-size: .8125rem; }
.zap-node-list .node-sub { color: #98a1aa; font-size: .75rem; }

/* 标签徽章（可点击筛选） */
.zap-tag-chip {
    display: inline-block; margin: 0 .25rem .2rem 0; padding: .12em .5em;
    font-size: .72rem; line-height: 1.4; color: #55606b;
    background: #f1f3f5; border: 1px solid #e6e9ed; border-radius: 999px;
}
.zap-tag-chip-link { text-decoration: none; transition: background-color .15s ease, border-color .15s ease, color .15s ease; }
.zap-tag-chip-link:hover { color: #0d6efd; background: #e7f1ff; border-color: #b6d4fe; }
.zap-tag-chip-active { color: #0d6efd; background: #e7f1ff; border-color: #0d6efd; }
.zap-tag-more { font-size: .72rem; color: #98a1aa; }
[data-bs-theme="dark"] .zap-tag-chip { color: #c9d1d9; background: #2a3138; border-color: #3a424b; }
[data-bs-theme="dark"] .zap-tag-chip-link:hover,
[data-bs-theme="dark"] .zap-tag-chip-active { color: #8ab4f8; background: #23384f; border-color: #3d6ea8; }
.zap-extra-tags { grid-column: 1 / -1; }

.zap-row-actions .btn { padding: .25rem .45rem; color: #7a8591; border-color: transparent; background: transparent; }
.zap-row-actions .btn:hover { background: #f1f3f5; color: #0d6efd; }
.zap-row-actions .zap-row-action-delete:hover { color: #c64545; background: rgba(198, 69, 69, .08); }
.zap-row-actions .zap-top-action.active { color: #d97706; }
.zap-row-actions .zap-top-action:hover { color: #d97706; background: rgba(217, 119, 6, .08); }
.zap-row-actions .fa { font-size: .9rem; }

/* 标题旁的置顶标记 */
.zap-node-list .zap-top-badge {
    display: inline-flex; align-items: center; gap: .2rem;
    margin-left: .4rem; padding: .1em .45em;
    font-size: .68rem; font-weight: 500; line-height: 1.3;
    color: #b45309; background: #fef3c7; border-radius: 999px;
    vertical-align: middle;
}
[data-bs-theme="dark"] .zap-node-list .zap-top-badge { color: #fcd34d; background: rgba(217, 119, 6, .2); }
[data-bs-theme="dark"] .zap-row-actions .zap-top-action.active { color: #fcd34d; }

/* ── 标题悬浮快捷操作（WordPress 风格：标题下方文字链接） ── */
.zap-node-list .zap-title-cell { min-width: 0; }
.zap-node-list .zap-title-inner { display: flex; align-items: flex-start; gap: .35rem; }
.zap-node-list .zap-row-toggle {
    flex: 0 0 auto; padding: .15rem .35rem; line-height: 1;
    color: #98a1aa; border-color: transparent; background: transparent;
}
.zap-node-list .zap-row-toggle i { transition: transform .15s ease; display: inline-block; }
.zap-node-list .zap-title-wrap { display: block; min-width: 0; }
/* 标题行：标题在左、置顶 badge 靠右上角，同一行不换行 */
.zap-node-list .zap-title-line { display: flex; align-items: flex-start; gap: .4rem; min-width: 0; }
/* 标题与操作链接统一行高，保证两行左对齐且不因 hover 改变布局 */
.zap-node-list .zap-title-line .node-title { flex: 0 1 auto; min-width: 0; max-width: 100%; line-height: 1.35; }
.zap-node-list .zap-title-line .zap-top-badge { flex: 0 0 auto; margin-left: auto; }
/* 用 visibility 切换而非 display，始终占位：行高固定，hover 不会抖动 */
.zap-node-list .zap-title-actions {
    display: block; margin-top: .25rem; min-height: 1.1rem; white-space: nowrap;
    opacity: 0; visibility: hidden; pointer-events: none;
    transition: opacity .12s ease;
}
.zap-node-list .zap-node-row:hover .zap-title-actions,
.zap-node-list .zap-node-row:focus-within .zap-title-actions {
    opacity: 1; visibility: visible; pointer-events: auto;
}
.zap-node-list .zap-title-actions .zap-title-action {
    padding: 0; border: 0; background: transparent;
    color: #0d6efd; font-size: .75rem; line-height: 1.4;
    text-decoration: none; cursor: pointer; vertical-align: baseline;
}
.zap-node-list .zap-title-actions .zap-title-action:hover { color: #0a58ca; text-decoration: underline; }
.zap-node-list .zap-title-actions .zap-title-action-trash,
.zap-node-list .zap-title-actions .zap-title-action-delete { color: #b32d2e; }
.zap-node-list .zap-title-actions .zap-title-action-trash:hover,
.zap-node-list .zap-title-actions .zap-title-action-delete:hover { color: #8f1d1d; }
.zap-node-list .zap-title-action-sep { margin: 0 .4rem; color: #c7cbd1; font-size: .7rem; }

/* ── 移动端展开详情 ── */
.zap-node-list .zap-row-extra { display: none; }
.zap-node-list .zap-extra-grid {
    display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .35rem .75rem;
}
.zap-node-list .zap-extra-grid > div { min-width: 0; overflow-wrap: anywhere; }
.zap-node-list .zap-extra-label { display: inline-block; min-width: 2.5rem; margin-right: .25rem; color: #98a1aa; }

.zap-batch-bar { background: #f8fbff !important; border-color: #d9e7ff !important; }
.zap-empty-state { padding: 3.5rem 1rem; }
.zap-empty-icon {
    width: 64px; height: 64px; margin: 0 auto 1rem;
    border-radius: 50%; background: #f4f6f9;
    display: flex; align-items: center; justify-content: center;
    color: #9aa4b0; font-size: 1.75rem;
}
.zap-empty-title { font-size: 1rem; font-weight: 600; color: #495057; margin-bottom: .35rem; }
.zap-empty-desc { color: #98a1aa; font-size: .85rem; margin-bottom: 1rem; }

@media (max-width: 767.98px) {
    .zap-list-toolbar { padding: .75rem !important; }
    .zap-view-title { font-size: .95rem; }
    .zap-add-btn { padding: .3rem .6rem; font-size: .8rem; }

    .zap-list-toolbar .nav-tabs,
    .zap-status-pills {
        flex-wrap: nowrap;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        width: 100%;
        padding-bottom: .25rem;
    }
    .zap-list-toolbar .nav-tabs .nav-item,
    .zap-status-pills .nav-item { flex: 0 0 auto; }

    #nodeListFilterForm { align-items: stretch !important; }
    .zap-search-box { width: 100% !important; }
    .zap-with-child { flex: 0 0 auto; }
    .zap-list-summary {
        width: 100%;
        margin-left: 0 !important;
        justify-content: space-between;
    }
    .list-top-pager .pagination { flex-wrap: wrap; justify-content: flex-end; }

    .zap-node-list .table { font-size: .8125rem; }
    .zap-node-list .table > :not(caption) > * > * { padding: .55rem .45rem; }
    .zap-node-list .node-title {
        display: block;
        max-width: 100%;
        white-space: normal;
        word-break: break-word;
        line-height: 1.35;
    }
    .zap-node-list .table td:last-child { white-space: nowrap; }
    .zap-row-actions .btn { padding: .2rem .3rem; }
    .zap-row-actions .fa { font-size: .8rem; }

    /* 移动端没有 hover：快捷操作常驻，行可点击展开隐藏列信息 */
    .zap-node-list .zap-title-wrap .node-title { display: block; }
    .zap-node-list .zap-title-actions { opacity: 1; visibility: visible; pointer-events: auto; }
    /* 日期列、操作列折叠进展开面板 */
    .zap-node-list .zap-col-date, .zap-node-list .zap-col-actions { display: none; }
    .zap-node-list .zap-node-row.is-expanded .zap-row-extra { display: block; }
    .zap-node-list .zap-node-row.is-expanded .zap-row-toggle i { transform: rotate(90deg); }
    .zap-node-list .zap-row-extra .zap-row-actions .btn { padding: .3rem .55rem; }

    .zap-batch-bar { gap: .4rem !important; }
    .zap-batch-bar .form-select { width: auto; }
    .zap-empty-state { padding: 2.5rem 1rem; }
}
@media (max-width: 575.98px) {
    .zap-list-toolbar .nav-tabs .nav-link { padding: .35rem .6rem; font-size: .8rem; }
}
[data-bs-theme="dark"] .zap-title-actions .zap-title-action { color: #6ea8fe; }
[data-bs-theme="dark"] .zap-title-actions .zap-title-action:hover { color: #9ecbff; }
[data-bs-theme="dark"] .zap-title-actions .zap-title-action-trash,
[data-bs-theme="dark"] .zap-title-actions .zap-title-action-delete { color: #ff8a8a; }
[data-bs-theme="dark"] .zap-title-action-sep { color: #59636e; }
[data-bs-theme="dark"] .zap-extra-label { color: #8995a2; }
</style>
