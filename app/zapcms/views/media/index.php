<?php

!IS_AJAX && $this->layout('layouts/common');
$this->view->page_title = '媒体库';
/**
 * @var \zap\view\View $this
 */
$uploadExts = trim((string)option('upload.extensions', ''));
if ($uploadExts === '') {
    $uploadExts = 'jpg,jpeg,png,gif,webp,svg,bmp,ico,zip,rar,7z,tar,gz,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,md,csv,mp3,mp4,avi,mov,wmv,flv,webm,json,xml';
}
$uploadExtList = '.' . preg_replace('/[\s,]+/', '|.', $uploadExts);
$uploadMaxSize = max(1, (int)option('upload.max_size', 20));
?>

<style>
    .media-tree {
        padding: 0.2rem 0.25rem;
    }
    .media-tree .tree-item {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.32rem 0.5rem;
        border-radius: 0.4rem;
        cursor: pointer;
        user-select: none;
        white-space: nowrap;
        font-size: 0.85rem;
        color: #495057;
        position: relative;
        transition: background-color 0.15s ease, color 0.15s ease;
    }
    .media-tree .tree-item:hover {
        background: rgba(13, 110, 253, 0.07);
        color: #212529;
    }
    .media-tree .tree-item.active {
        background: var(--bs-primary-bg-subtle, #cfe2ff);
        color: var(--bs-primary, #0d6efd);
        font-weight: 600;
    }
    .media-tree .tree-item.active::before {
        content: '';
        position: absolute;
        left: 0;
        top: 20%;
        bottom: 20%;
        width: 3px;
        border-radius: 0 2px 2px 0;
        background: var(--bs-primary, #0d6efd);
    }
    .media-tree .tree-children {
        margin-left: 1rem;
        padding-left: 0.3rem;
        border-left: 1px solid #e9ecef;
    }
    /* 子项与父级竖线的连接横线（L 形引导线） */
    .media-tree .tree-children .tree-item {
        position: relative;
    }
    .media-tree .tree-children .tree-item::after {
        content: '';
        position: absolute;
        left: -0.3rem;
        top: 50%;
        width: 0.3rem;
        height: 1px;
        background: #e9ecef;
        pointer-events: none;
    }
    .media-tree .tree-toggle {
        width: 0.9rem;
        flex-shrink: 0;
        text-align: center;
        color: #adb5bd;
        font-size: 0.7rem;
        transition: color 0.15s ease;
    }
    .media-tree .tree-item:hover .tree-toggle {
        color: var(--bs-primary, #0d6efd);
    }
    .media-tree .tree-caret {
        transition: transform 0.2s ease;
    }
    .media-tree .tree-caret.expanded {
        transform: rotate(90deg);
    }
    .media-tree .tree-folder-icon {
        width: 1.05rem;
        flex-shrink: 0;
        text-align: center;
        color: #e8a33d;
        transition: color 0.15s ease;
    }
    .media-tree .tree-folder-icon.icon-root {
        color: #868e96;
    }
    .media-tree .tree-item.active .tree-folder-icon {
        color: var(--bs-primary, #0d6efd);
    }
    .media-tree .tree-badge {
        flex-shrink: 0;
        min-width: 1.15rem;
        padding: 0.15rem 0.35rem;
        border-radius: 999px;
        background: #e9ecef;
        color: #6c757d;
        font-size: 0.68rem;
        line-height: 1;
        text-align: center;
    }
    .media-tree .tree-item.active .tree-badge {
        background: rgba(13, 110, 253, 0.14);
        color: var(--bs-primary, #0d6efd);
    }
    .media-file-card {
        transition: box-shadow 0.15s;
    }
    .media-file-card:hover {
        box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.12);
    }
    .media-file-card .media-thumb {
        height: 130px;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        background: #f8f9fa;
        position: relative;
    }
    .media-file-card .media-thumb img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }
    .media-file-card .media-thumb i {
        font-size: 3rem;
        color: #adb5bd;
    }
    .media-file-card.selected {
        outline: 2px solid var(--bs-primary, #0d6efd);
        outline-offset: -2px;
    }
    .media-file-card .form-check {
        position: absolute;
        top: 0.35rem;
        left: 0.35rem;
        z-index: 2;
    }
    .media-file-card .media-actions {
        position: absolute;
        top: 0.35rem;
        right: 0.35rem;
        z-index: 2;
        opacity: 0;
        transition: opacity 0.15s;
    }
    .media-file-card:hover .media-actions {
        opacity: 1;
    }
    .media-list-row:hover td {
        background: rgba(0, 0, 0, 0.03);
    }
    #mediaDropHint { pointer-events: none; }
    #mediaContent.highlight {
        outline: 2px dashed var(--zap-primary, dodgerblue);
        outline-offset: -8px;
        background: rgba(16, 185, 129, 0.05);
        border-radius: 0.5rem;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="fs-5 fw-bold mb-0">
        <i class="fa-regular fa-images me-2 text-info"></i>媒体库
    </h2>
</div>

<div class="row g-3">
    <!-- 左侧：上传 / 新建 / 目录树 -->
    <div class="col-lg-3">
        <div class="card shadow-sm">
            <div class="card-header py-2 bg-white d-flex gap-2 flex-wrap">
                <input type="file" id="mediaUploadInput" multiple style="display:none;">
                <button type="button" id="btn-upload" class="btn btn-primary btn-sm flex-fill">
                    <i class="fa-solid fa-upload me-1"></i>上传文件
                </button>
                <button type="button" id="btn-mkdir" class="btn btn-outline-secondary btn-sm flex-fill">
                    <i class="fa-solid fa-folder-plus me-1"></i>新建文件夹
                </button>
            </div>
            <div class="card-body p-2 overflow-auto media-tree" id="media-tree" style="max-height: 68vh;"></div>
        </div>
    </div>

    <!-- 右侧：工具栏 + 文件区域 -->
    <div class="col-lg-9">
        <div class="card shadow-sm">
            <div class="card-header py-2 bg-white">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0" id="media-crumbs"></ol>
                    </nav>
                    <div class="ms-auto d-flex align-items-center gap-2">
                        <div class="input-group input-group-sm" style="width: 200px;">
                            <input type="text" class="form-control" id="media-search" placeholder="搜索文件名...">
                            <button class="btn btn-outline-secondary" type="button" id="btn-search"><i class="fa-solid fa-magnifying-glass"></i></button>
                        </div>
                        <div class="btn-group btn-group-sm" role="group" aria-label="视图切换">
                            <input type="radio" class="btn-check" name="media-view" id="view-grid" checked>
                            <label class="btn btn-outline-secondary" for="view-grid"><i class="fa-solid fa-table-cells-large"></i></label>
                            <input type="radio" class="btn-check" name="media-view" id="view-list">
                            <label class="btn btn-outline-secondary" for="view-list"><i class="fa-solid fa-list"></i></label>
                        </div>
                        <button type="button" class="btn btn-sm btn-light" id="btn-refresh" title="刷新"><i class="fa-solid fa-rotate"></i></button>
                    </div>
                </div>
            </div>

            <div class="card-body p-3">
                <div id="mediaDropHint" class="text-center text-muted py-2 mb-2">
                    <i class="fa-solid fa-cloud-arrow-down me-1"></i>支持拖拽文件或整个文件夹到此处上传，也可点击「上传文件」选择
                </div>
                <div class="progress mb-2 d-none" style="height: 3px;" id="media-progress-wrap">
                    <div class="progress-bar" id="media-progress" style="width: 0%;"></div>
                </div>
                <div id="mediaContent"></div>
            </div>

            <div class="card-footer py-2 bg-white d-flex justify-content-between align-items-center">
                <span class="text-muted small" id="media-count">加载中...</span>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-move" disabled>
                        <i class="fa-solid fa-arrow-right-arrow-left me-1"></i>移动
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btn-download" disabled>
                        <i class="fa-solid fa-download me-1"></i>下载
                    </button>
                    <button type="button" class="btn btn-sm btn-danger" id="btn-delete" disabled>
                        <i class="fa-regular fa-trash-can me-1"></i>删除
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 新建文件夹 Modal -->
<div class="modal fade" id="mkdirModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title">新建文件夹</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="text" class="form-control" id="mkdir-name" placeholder="如：test1/test2/test3">
                <div class="form-text">支持多级目录，用 <code>/</code> 分隔，例如 <code>test1/test2/test3</code></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">取消</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-mkdir-ok">创建</button>
            </div>
        </div>
    </div>
</div>

<!-- 重命名 Modal -->
<div class="modal fade" id="renameModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title">重命名</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="text" class="form-control" id="rename-name" placeholder="新名称">
                <div class="form-text mt-1" id="rename-old"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">取消</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-rename-ok">保存</button>
            </div>
        </div>
    </div>
</div>

<!-- 移动 Modal -->
<div class="modal fade" id="moveModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title"><i class="fa-solid fa-arrow-right-arrow-left me-1"></i>移动到目录</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <select class="form-select" id="move-target" size="8"></select>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">取消</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-move-ok">移动</button>
            </div>
        </div>
    </div>
</div>

<!-- 图片预览 Modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-dark border-0">
            <div class="modal-header border-secondary py-2">
                <h6 class="modal-title text-white text-truncate" id="preview-title"></h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-0">
                <img src="" id="preview-img" class="img-fluid" style="max-height: 70vh;" alt="预览">
            </div>
            <div class="modal-footer border-secondary py-2 justify-content-center">
                <a href="#" id="preview-original" class="btn btn-sm btn-light" target="_blank"><i class="fa-solid fa-up-right-from-square me-1"></i>新窗口打开</a>
                <button type="button" class="btn btn-sm btn-outline-light" id="preview-copy"><i class="fa-solid fa-link me-1"></i>复制链接</button>
            </div>
        </div>
    </div>
</div>

<script>
    Zap.EnableToolTip();
    const MediaApi = {
        browse: '<?php echo url_action('Media@browse'); ?>',
        tree: '<?php echo url_action('Media@tree'); ?>',
        createDir: '<?php echo url_action('Media@createDir'); ?>',
        rename: '<?php echo url_action('Media@rename'); ?>',
        delete: '<?php echo url_action('Media@delete'); ?>',
        move: '<?php echo url_action('Media@move'); ?>',
        upload: '<?php echo url_action('Upload@file'); ?>'
    };

    let media = {
        path: '',
        search: '',
        view: 'grid',      // grid | list
        dirs: [],
        files: [],
        selected: new Set()
    };

    // ============ 目录树 ============
    function renderTree(nodes, container) {
        container.empty();
        // 根目录节点（children 即整棵树）
        appendTreeItem({name: '根目录', path: '', count: 0, children: nodes}, container);
    }

    function appendTreeItems(nodes, container) {
        nodes.forEach(function (node) {
            appendTreeItem(node, container);
        });
    }

    function appendTreeItem(node, container) {
        const hasChildren = node.children && node.children.length > 0;
        const isRoot = node.path === '';
        const isActive = media.path === node.path;
        // 当前目录的祖先链：自动展开
        const isAncestor = !isActive && node.path !== '' && media.path.indexOf(node.path + '/') === 0;
        const expandedInit = isRoot || isAncestor;
        const iconCls = isRoot
            ? 'fa-house icon-root'
            : (expandedInit ? 'fa-folder-open' : 'fa-folder');

        const item = $('<div>')
            .addClass('tree-item' + (isActive ? ' active' : ''))
            .attr('data-path', node.path)
            .append(
                $('<span>').addClass('tree-toggle').html(
                    hasChildren
                        ? '<i class="fa-solid fa-chevron-right tree-caret' + (expandedInit ? ' expanded' : '') + '"></i>'
                        : '<i class="fa-solid fa-minus text-body-tertiary" style="font-size:0.6rem"></i>'
                ),
                $('<i class="tree-folder-icon fa-solid ' + iconCls + '"></i>'),
                $('<span class="text-truncate flex-fill" title="' + node.name.replace(/"/g, '&quot;') + '">').text(node.name),
                node.count > 0 ? $('<span class="tree-badge">').text(node.count) : ''
            );

        const childrenBox = $('<div class="tree-children"' + (expandedInit ? '' : ' style="display:none"') + '></div>');
        if (hasChildren) {
            appendTreeItems(node.children, childrenBox);
        }
        const box = $('<div>').append(item, childrenBox);
        container.append(box);

        // 展开/折叠（点击行）
        item.on('click', function (e) {
            e.stopPropagation();
            if (media.path === node.path) {
                // 已激活：仅切换展开状态
                if (hasChildren) {
                    const expanded = childrenBox.is(':visible');
                    childrenBox.toggle(150);
                    item.find('.tree-caret').toggleClass('expanded', !expanded);
                    item.find('.tree-folder-icon').toggleClass('fa-folder-open', !expanded).toggleClass('fa-folder', expanded);
                }
                return;
            }
            media.path = node.path;
            media.search = '';
            $('#media-search').val('');
            media.selected.clear();
            $('.tree-item').removeClass('active');
            item.addClass('active');
            loadBrowse();
        });
        // 展开/折叠（箭头）
        item.find('.tree-toggle').on('click', function (e) {
            e.stopPropagation();
            if (hasChildren) {
                childrenBox.toggle(150);
                const expanded = childrenBox.is(':visible');
                item.find('.tree-caret').toggleClass('expanded', expanded);
                item.find('.tree-folder-icon').toggleClass('fa-folder-open', expanded).toggleClass('fa-folder', !expanded);
            }
        });
    }

    // 浏览路径变化后同步树的高亮 / 展开状态，并滚动到可视区
    function syncTreeActive() {
        $('#media-tree .tree-item').each(function () {
            const $item = $(this);
            const path = $item.data('path') || '';
            const isActive = path === media.path;
            $item.toggleClass('active', isActive);
            // 祖先链自动展开
            if (!isActive && path !== '' && media.path.indexOf(path + '/') === 0) {
                const $children = $item.next('.tree-children');
                if ($children.length && !$children.is(':visible')) {
                    $children.show();
                    $item.find('.tree-caret').addClass('expanded');
                    $item.find('.tree-folder-icon').addClass('fa-folder-open').removeClass('fa-folder');
                }
            }
        });
        // 滚动激活节点进入可视区
        const $tree = $('#media-tree');
        const $active = $tree.find('.tree-item.active');
        if ($active.length) {
            const treeTop = $tree.offset().top;
            const activeTop = $active.offset().top;
            if (activeTop < treeTop || activeTop > treeTop + $tree.height()) {
                $tree.scrollTop($tree.scrollTop() + (activeTop - treeTop) - $tree.height() / 2);
            }
        }
    }

    function loadTree() {
        $.get(MediaApi.tree, function (data) {
            if (data && data.code === 0) {
                renderTree(data.data, $('#media-tree'));
            }
        });
    }

    // ============ 文件列表 ============
    function renderCrumbs() {
        const $crumbs = $('#media-crumbs').empty();
        $('<li class="breadcrumb-item"><a href="javascript:;" data-path="">根目录</a></li>').appendTo($crumbs);
        if (media.path !== '') {
            const parts = media.path.split('/');
            let acc = '';
            parts.forEach(function (part, i) {
                acc = acc === '' ? part : acc + '/' + part;
                const last = i === parts.length - 1;
                const li = $('<li class="breadcrumb-item' + (last ? ' active' : '') + '">');
                if (last) {
                    li.text(part);
                } else {
                    li.append($('<a href="javascript:;" data-path="' + acc + '">').text(part));
                }
                $crumbs.append(li);
            });
        }
        $crumbs.find('a[data-path]').on('click', function () {
            media.path = $(this).data('path') || '';
            media.selected.clear();
            loadBrowse();
        });
    }

    function renderContent() {
        renderCrumbs();
        const $content = $('#mediaContent').empty();
        const hasDirs = media.dirs.length > 0;
        const hasFiles = media.files.length > 0;

        if (!hasDirs && !hasFiles) {
            $content.append(
                $('<div class="text-center text-muted py-5">').append(
                    $('<i class="fa-regular fa-folder-open fa-3x d-block mb-2"></i>'),
                    $('<p class="mb-0">该目录为空</p>')
                )
            );
        } else {
            if (media.view === 'grid') {
                const $row = $('<div class="row row-cols-2 row-cols-sm-3 row-cols-lg-4 row-cols-xl-5 g-3"></div>');
                // 文件夹
                media.dirs.forEach(function (dir) {
                    const card = $('<div class="col"></div>').append(
                        $('<div class="media-file-card card h-100 cursor-pointer border position-relative" data-type="dir" data-path="' + dir.path + '" data-name="' + dir.name.replace(/"/g, '&quot;') + '"></div>').append(
                            $('<div class="form-check">').append(
                                $('<input type="checkbox" class="form-check-input media-check" value="' + dir.name.replace(/"/g, '&quot;') + '">')
                            ),
                            $('<div class="media-thumb bg-light">').append(
                                $('<i class="fa-solid fa-folder text-warning"></i>'),
                                $('<span class="badge text-bg-light position-absolute bottom-0 end-0 m-1">' + dir.count + '</span>')
                            ),
                            $('<div class="card-body p-2">').append(
                                $('<div class="text-truncate small fw-semibold" title="' + dir.name.replace(/"/g, '&quot;') + '">').text(dir.name)
                            )
                        )
                    );
                    const $card = card.find('.media-file-card');
                    $card.on('click', function (e) {
                        if ($(e.target).closest('.media-check').length) return;
                        media.path = dir.path;
                        media.selected.clear();
                        loadBrowse();
                    });
                    $card.find('.media-check').on('change', function () {
                        if ($(this).is(':checked')) {
                            media.selected.add(dir.path);
                            $card.addClass('selected');
                        } else {
                            media.selected.delete(dir.path);
                            $card.removeClass('selected');
                        }
                        updateActions();
                    });
                    $row.append(card);
                });
                // 文件
                media.files.forEach(function (file) {
                    const card = $('<div class="col"></div>').append(
                        $('<div class="media-file-card card h-100 border position-relative" data-type="file" data-path="' + file.path + '" data-name="' + file.name.replace(/"/g, '&quot;') + '" data-url="' + file.url + '" data-is-image="' + (file.is_image ? '1' : '0') + '"></div>').append(
                            $('<div class="form-check">').append(
                                $('<input type="checkbox" class="form-check-input media-check" value="' + file.name.replace(/"/g, '&quot;') + '">')
                            ),
                            $('<div class="media-actions btn-group btn-group-sm">').append(
                                $('<button class="btn btn-light btn-preview" title="预览"><i class="fa-solid fa-eye"></i></button>'),
                                $('<button class="btn btn-light btn-rename" title="重命名"><i class="fa-solid fa-pen"></i></button>')
                            ),
                            $('<div class="media-thumb">').append(
                                file.is_image
                                    ? $('<img src="' + file.thumb + '" loading="lazy" alt="' + file.name.replace(/"/g, '&quot;') + '">')
                                    : $('<i class="fa-regular fa-file-lines"></i>')
                            ),
                            $('<div class="card-body p-2">').append(
                                $('<div class="text-truncate small fw-semibold" title="' + file.name.replace(/"/g, '&quot;') + '">').text(file.name),
                                $('<div class="text-muted" style="font-size:0.72rem">').text(file.size + ' · ' + file.date)
                            )
                        )
                    );
                    const $card = card.find('.media-file-card');
                    $card.on('click', function (e) {
                        if ($(e.target).closest('.media-check, .media-actions').length) return;
                        if (file.is_image) {
                            openPreview(file);
                        } else {
                            window.open(file.url, '_blank');
                        }
                    });
                    $card.find('.btn-preview').on('click', function (e) {
                        e.stopPropagation();
                        if (file.is_image) openPreview(file);
                        else window.open(file.url, '_blank');
                    });
                    $card.find('.btn-rename').on('click', function (e) {
                        e.stopPropagation();
                        openRename(file.name);
                    });
                    $card.find('.media-check').on('change', function () {
                        if ($(this).is(':checked')) {
                            media.selected.add(file.path);
                            $card.addClass('selected');
                        } else {
                            media.selected.delete(file.path);
                            $card.removeClass('selected');
                        }
                        updateActions();
                    });
                    $row.append(card);
                });
                $content.append($row);
            } else {
                // 列表视图
                const $table = $('<table class="table table-hover align-middle mb-0"></table>');
                $table.append(
                    $('<thead>').append(
                        $('<tr>').append(
                            $('<th style="width:36px"></th>'),
                            $('<th>名称</th>'),
                            $('<th style="width:100px">类型</th>'),
                            $('<th style="width:110px">大小</th>'),
                            $('<th style="width:150px">修改时间</th>'),
                            $('<th style="width:110px">操作</th>')
                        )
                    )
                );
                const $tbody = $('<tbody></tbody>');
                media.dirs.forEach(function (dir) {
                    const $tr = $('<tr class="media-list-row cursor-pointer" data-type="dir" data-path="' + dir.path + '">').append(
                        $('<td style="width:36px"><input type="checkbox" class="form-check-input media-check" value="' + dir.name.replace(/"/g, '&quot;') + '"></td>'),
                        $('<td><i class="fa-solid fa-folder text-warning me-2"></i>' + dir.name + '</td>'),
                        $('<td><span class="badge text-bg-light">目录</span></td>'),
                        $('<td>-</td>'),
                        $('<td>-</td>'),
                        $('<td><span class="badge text-bg-secondary">' + dir.count + ' 个文件</span></td>')
                    );
                    $tr.on('click', function (e) {
                        if ($(e.target).closest('.media-check').length) return;
                        media.path = dir.path;
                        media.selected.clear();
                        loadBrowse();
                    });
                    $tr.find('.media-check').on('change', function () {
                        if ($(this).is(':checked')) {
                            media.selected.add(dir.path);
                            $tr.addClass('table-primary');
                        } else {
                            media.selected.delete(dir.path);
                            $tr.removeClass('table-primary');
                        }
                        updateActions();
                    });
                    $tbody.append($tr);
                });
                media.files.forEach(function (file) {
                    $tbody.append(
                        $('<tr class="media-list-row" data-type="file" data-path="' + file.path + '" data-url="' + file.url + '" data-is-image="' + (file.is_image ? '1' : '0') + '">').append(
                            $('<td style="width:36px"><input type="checkbox" class="form-check-input media-check" value="' + file.name.replace(/"/g, '&quot;') + '"></td>'),
                            $('<td><i class="' + (file.is_image ? 'fa-regular fa-image text-info' : 'fa-regular fa-file-lines text-secondary') + ' me-2"></i>' + file.name + '</td>'),
                            $('<td><span class="badge text-bg-light">' + (file.ext || '-').toUpperCase() + '</span></td>'),
                            $('<td>' + file.size + '</td>'),
                            $('<td>' + file.date + '</td>'),
                            $('<td class="btn-group btn-group-sm">').append(
                                $('<button class="btn btn-outline-secondary btn-preview" title="预览"><i class="fa-solid fa-eye"></i></button>'),
                                $('<button class="btn btn-outline-secondary btn-rename" title="重命名"><i class="fa-solid fa-pen"></i></button>')
                            )
                        )
                    );
                    const $tr = $tbody.find('tr').last();
                    $tr.find('.btn-preview').on('click', function (e) {
                        e.stopPropagation();
                        if (file.is_image) openPreview(file);
                        else window.open(file.url, '_blank');
                    });
                    $tr.find('.btn-rename').on('click', function (e) {
                        e.stopPropagation();
                        openRename(file.name);
                    });
                    $tr.find('.media-check').on('change', function () {
                        if ($(this).is(':checked')) {
                            media.selected.add(file.path);
                            $tr.addClass('table-primary');
                        } else {
                            media.selected.delete(file.path);
                            $tr.removeClass('table-primary');
                        }
                        updateActions();
                    });
                });
                $table.append($tbody);
                $content.append($table);
            }
        }

        $('#media-count').text('共 ' + media.dirs.length + ' 个文件夹，' + media.files.length + ' 个文件');
        updateActions();
    }

    function loadBrowse() {
        const $content = $('#mediaContent');
        $content.html('<div class="text-center text-muted py-5"><div class="spinner-border spinner-border-sm me-2"></div>加载中...</div>');
        $.post(MediaApi.browse, {path: media.path, search: media.search}, function (data) {
            if (data && data.code === 0) {
                media.dirs = data.dirs;
                media.files = data.files;
                renderContent();
                syncTreeActive();
            } else {
                $content.html('<div class="alert alert-warning mb-0">' + (data && data.msg ? data.msg : '加载失败') + '</div>');
            }
        }, 'json');
    }

    // ============ 选中与批量操作 ============
    function updateActions() {
        const n = media.selected.size;
        $('#btn-move').prop('disabled', n === 0);
        $('#btn-delete').prop('disabled', n === 0);
        $('#btn-download').prop('disabled', n === 0);
    }

    function getSelectedNames() {
        return Array.from(media.selected).map(function (p) {
            return p.split('/').pop();
        });
    }

    // ============ 新建文件夹 ============
    function openMkdir() {
        $('#mkdir-name').val('');
        new bootstrap.Modal('#mkdirModal').show();
        setTimeout(function () { $('#mkdir-name').trigger('focus'); }, 200);
    }
    $('#btn-mkdir').on('click', openMkdir);
    $('#btn-mkdir-ok').on('click', function () {
        // 支持多级目录：统一分隔符、去掉首尾与重复斜杠
        const name = $.trim($('#mkdir-name').val())
            .replace(/\\/g, '/')
            .replace(/\/{2,}/g, '/')
            .replace(/^\/+|\/+$/g, '');
        if (name === '') { ZapToast.alert('请输入文件夹名称', {bgColor: bgWarning}); return; }
        const badSegment = name.split('/').some(function (s) { return s === '' || s === '.' || s === '..'; });
        if (/[:*?"<>|]/.test(name) || badSegment) {
            ZapToast.alert('名称不能包含 : * ? " < > | 等字符，也不能有空层级或 ..', {bgColor: bgWarning});
            return;
        }
        const $btn = $(this);
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>创建中...');
        $.post(MediaApi.createDir, {path: media.path, dir_name: name}, function (data) {
            const isOk = data && data.code === 0;
            ZapToast.alert(data && data.msg ? data.msg : (isOk ? '创建成功' : '创建失败'), {bgColor: isOk ? bgSuccess : bgDanger});
            if (isOk) {
                bootstrap.Modal.getInstance('#mkdirModal').hide();
                loadBrowse();
                loadTree();
            }
        }, 'json').always(function () {
            $btn.prop('disabled', false).html('创建');
        });
    });
    $('#mkdir-name').on('keydown', function (e) { if (e.key === 'Enter') $('#btn-mkdir-ok').trigger('click'); });

    // ============ 重命名 ============
    function openRename(oldName) {
        $('#rename-name').val(oldName);
        $('#rename-old').text('原名称：' + oldName);
        new bootstrap.Modal('#renameModal').show();
        setTimeout(function () {
            const $input = $('#rename-name');
            $input.trigger('focus');
            const dot = $input.val().lastIndexOf('.');
            if (dot > 0) $input.get(0).setSelectionRange(0, dot);
        }, 200);
    }
    $('#btn-rename-ok').on('click', function () {
        const newName = $.trim($('#rename-name').val());
        if (newName === '') { ZapToast.alert('请输入新名称', {bgColor: bgWarning}); return; }
        if (/[\/\\:*?"<>|]/.test(newName)) { ZapToast.alert('名称不能包含 \\ / : * ? " < > | 等字符', {bgColor: bgWarning}); return; }
        const oldName = $.trim($('#rename-old').text().replace('原名称：', ''));
        const $btn = $(this);
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>保存中...');
        $.post(MediaApi.rename, {path: media.path, old_name: oldName, new_name: newName}, function (data) {
            const isOk = data && data.code === 0;
            ZapToast.alert(data && data.msg ? data.msg : (isOk ? '重命名成功' : '重命名失败'), {bgColor: isOk ? bgSuccess : bgDanger});
            if (isOk) {
                bootstrap.Modal.getInstance('#renameModal').hide();
                loadBrowse();
                loadTree();
            }
        }, 'json').always(function () {
            $btn.prop('disabled', false).html('保存');
        });
    });
    $('#rename-name').on('keydown', function (e) { if (e.key === 'Enter') $('#btn-rename-ok').trigger('click'); });

    // ============ 删除 ============
    $('#btn-delete').on('click', function () {
        if (media.selected.size === 0) return;
        const names = getSelectedNames();
        ZapModal.create({
            id: 'mediaDeleteModal',
            title: '确认删除',
            dialog_class: 'modal-dialog-centered modal-sm',
            header_class: 'bg-danger text-white',
            content: '<div class="text-center"><p class="mb-2">确定删除选中的 <b>' + names.length + '</b> 项？</p><span class="text-danger">此操作不可恢复！</span></div>',
            buttons: [
                {title: '取消', class: 'btn-secondary', close: true},
                {title: '删除', class: 'btn-danger'}
            ],
            btn2: function () {
                $.post(MediaApi.delete, {path: media.path, names: names}, function (data) {
                    const isOk = data && data.code === 0;
                    ZapToast.alert(data && data.msg ? data.msg : (isOk ? '删除成功' : '删除失败'), {bgColor: isOk ? bgSuccess : bgDanger});
                    if (isOk) {
                        media.selected.clear();
                        loadBrowse();
                        loadTree();
                        const el = document.getElementById('mediaDeleteModal');
                        if (el && bootstrap.Modal.getInstance(el)) bootstrap.Modal.getInstance(el).hide();
                    }
                }, 'json');
            }
        }, true).show();
    });

    // ============ 移动 ============
    function loadMoveTargets() {
        const $select = $('#move-target').empty();
        $('<option value="">根目录 /</option>').appendTo($select);
        (function walk(nodes, prefix) {
            nodes.forEach(function (node) {
                const val = node.path;
                $('<option value="' + val + '">').text(prefix + node.name + '/').appendTo($select);
                if (node.children && node.children.length) walk(node.children, prefix + '　');
            });
        })(media.treeData || [], '　');
        $('#move-target').val(media.path);
    }
    $('#btn-move').on('click', function () {
        if (media.selected.size === 0) return;
        $.get(MediaApi.tree, function (data) {
            if (data && data.code === 0) {
                media.treeData = data.data;
                loadMoveTargets();
                new bootstrap.Modal('#moveModal').show();
            }
        });
    });
    $('#btn-move-ok').on('click', function () {
        const target = $('#move-target').val();
        const names = getSelectedNames();
        if (target === media.path) {
            ZapToast.alert('目标目录与当前目录相同', {bgColor: bgWarning});
            return;
        }
        const $btn = $(this);
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>移动中...');
        $.post(MediaApi.move, {path: media.path, target: target, names: names}, function (data) {
            const isOk = data && data.code === 0;
            ZapToast.alert(data && data.msg ? data.msg : (isOk ? '移动成功' : '移动失败'), {bgColor: isOk ? bgSuccess : bgDanger});
            if (isOk) {
                bootstrap.Modal.getInstance('#moveModal').hide();
                media.selected.clear();
                loadBrowse();
                loadTree();
            }
        }, 'json').always(function () {
            $btn.prop('disabled', false).html('移动');
        });
    });

    // ============ 下载 ============
    $('#btn-download').on('click', function () {
        if (media.selected.size !== 1) {
            ZapToast.alert('请选择单个文件下载', {bgColor: bgWarning});
            return;
        }
        window.location.href = '<?php echo url_action('Media@download'); ?>?path=' + encodeURIComponent(Array.from(media.selected)[0]);
    });

    // ============ 预览 ============
    function openPreview(file) {
        $('#preview-title').text(file.name);
        $('#preview-img').attr('src', file.url);
        $('#preview-original').attr('href', file.url);
        new bootstrap.Modal('#previewModal').show();
    }
    $('#preview-copy').on('click', function () {
        const url = $('#preview-original').attr('href');
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(function () {
                ZapToast.alert('链接已复制', {bgColor: bgSuccess});
            });
        } else {
            const $tmp = $('<textarea>').val(url).appendTo('body').select();
            document.execCommand('copy');
            $tmp.remove();
            ZapToast.alert('链接已复制', {bgColor: bgSuccess});
        }
    });

    // ============ 搜索 / 刷新 / 视图 ============
    function doSearch() {
        media.search = $.trim($('#media-search').val());
        media.selected.clear();
        loadBrowse();
    }
    $('#btn-search').on('click', doSearch);
    $('#media-search').on('keydown', function (e) { if (e.key === 'Enter') doSearch(); });
    $('#btn-refresh').on('click', function () { loadBrowse(); loadTree(); });
    $('input[name="media-view"]').on('change', function () {
        media.view = this.id === 'view-grid' ? 'grid' : 'list';
        renderContent();
    });

    // ============ 上传 ============
    $('#btn-upload').on('click', function () { $('#mediaUploadInput').trigger('click'); });
    $('#mediaUploadInput').on('change', function () {
        if (this.files.length) uploadFiles(this.files);
        this.value = '';
    });

    // 普通文件列表 → 统一的上传项（dir 为空）
    function uploadFiles(fileList) {
        uploadItems(Array.prototype.map.call(fileList, function (file) {
            return {file: file, dir: ''};
        }));
    }

    /**
     * 逐个上传；item = {file: File, dir: '相对子目录（可为空）'}
     * 目录上传时通过 fullPath 让后端保留目录结构。
     */
    function uploadItems(items) {
        const total = items.length;
        if (total === 0) {
            ZapToast.alert('没有可上传的文件（或文件夹为空）', {bgColor: bgWarning});
            return;
        }
        let done = 0, failed = 0;
        $('#media-progress-wrap').removeClass('d-none');
        $('#media-progress').css('width', '0%');

        function next() {
            if (items.length === 0) {
                $('#media-progress').css('width', '100%');
                setTimeout(function () { $('#media-progress-wrap').addClass('d-none'); }, 500);
                if (failed > 0) {
                    ZapToast.alert('上传完成，成功 ' + (total - failed) + ' / ' + total + '，失败 ' + failed, {bgColor: failed === total ? bgDanger : bgWarning});
                } else {
                    ZapToast.alert('全部上传成功', {bgColor: bgSuccess});
                }
                loadBrowse();
                loadTree();
                return;
            }
            const item = items.shift();
            const fd = new FormData();
            fd.append('file', item.file);
            fd.append('path', media.path);
            if (item.dir) {
                fd.append('fullPath', '/' + item.dir + '/' + item.file.name);
            }
            $.ajax({
                url: MediaApi.upload,
                method: 'post',
                data: fd,
                processData: false,
                contentType: false,
                dataType: 'json',
                xhr: function () {
                    const xhr = $.ajaxSettings.xhr();
                    if (xhr.upload) {
                        xhr.upload.addEventListener('progress', function (e) {
                            if (e.lengthComputable) {
                                const p = Math.round(((done + e.loaded / e.total) / total) * 100);
                                $('#media-progress').css('width', p + '%');
                            }
                        });
                    }
                    return xhr;
                },
                success: function (data) {
                    done++;
                    if (!data || data.code !== 0) failed++;
                },
                error: function () {
                    done++;
                    failed++;
                },
                complete: function () { next(); }
            });
        }
        next();
    }

    // ============ 拖拽上传（支持文件与文件夹） ============
    const $contentBox = $('#mediaContent');
    const dropZone = $contentBox.closest('.card')[0] || $contentBox[0];
    let dragDepth = 0;

    function readAllEntries(dirEntry) {
        return new Promise(function (resolve) {
            const reader = dirEntry.createReader();
            let all = [];
            (function read() {
                reader.readEntries(function (batch) {
                    if (!batch.length) { resolve(all); return; }
                    all = all.concat(Array.prototype.slice.call(batch));
                    read();
                }, function () { resolve(all); });
            })();
        });
    }

    function walkEntry(entry, dir, out) {
        if (entry.isFile) {
            return new Promise(function (resolve) {
                entry.file(function (file) {
                    // 跳过隐藏文件（.DS_Store、Thumbs.db 等）
                    if (file.name.charAt(0) !== '.') {
                        out.push({file: file, dir: dir});
                    }
                    resolve();
                }, function () { resolve(); });
            });
        }
        if (entry.isDirectory) {
            const childDir = dir ? dir + '/' + entry.name : entry.name;
            return readAllEntries(entry).then(function (children) {
                return children.reduce(function (p, child) {
                    return p.then(function () { return walkEntry(child, childDir, out); });
                }, Promise.resolve());
            });
        }
        return Promise.resolve();
    }

    function collectDropItems(dataTransfer) {
        const out = [];
        const items = dataTransfer.items;
        const hasEntries = items && items.length && typeof items[0].webkitGetAsEntry === 'function';
        if (!hasEntries) {
            return Promise.resolve(Array.prototype.map.call(dataTransfer.files || [], function (file) {
                return {file: file, dir: ''};
            }));
        }
        const entries = [];
        for (let i = 0; i < items.length; i++) {
            if (items[i].kind !== 'file') continue;
            const entry = items[i].webkitGetAsEntry();
            if (entry) entries.push(entry);
        }
        return entries.reduce(function (p, entry) {
            return p.then(function () { return walkEntry(entry, '', out); });
        }, Promise.resolve()).then(function () { return out; });
    }

    dropZone.addEventListener('dragenter', function (e) {
        e.preventDefault();
        e.stopPropagation();
        dragDepth++;
        $contentBox.addClass('highlight');
    }, false);

    dropZone.addEventListener('dragover', function (e) {
        e.preventDefault();
        e.stopPropagation();
        if (e.dataTransfer) {
            e.dataTransfer.dropEffect = 'copy';
        }
    }, false);

    dropZone.addEventListener('dragleave', function (e) {
        e.preventDefault();
        e.stopPropagation();
        dragDepth = Math.max(0, dragDepth - 1);
        if (dragDepth === 0) $contentBox.removeClass('highlight');
    }, false);

    dropZone.addEventListener('drop', function (e) {
        e.preventDefault();
        e.stopPropagation();
        dragDepth = 0;
        $contentBox.removeClass('highlight');
        if (!e.dataTransfer) return;
        collectDropItems(e.dataTransfer).then(function (list) {
            if (list.length) {
                uploadItems(list);
            } else {
                ZapToast.alert('没有可上传的文件（或文件夹为空）', {bgColor: bgWarning});
            }
        });
    }, false);

    // 防止拖到窗口其他区域时浏览器直接打开文件
    $(document).on('dragover drop', function (e) { e.preventDefault(); });

    // ============ 初始化 ============
    loadTree();
    loadBrowse();
</script>
