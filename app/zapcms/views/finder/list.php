<?php if ($initialize) { ?>
<?php
// 读取后台「文件上传」配置，供前端 ZAPUploader 校验使用
$uploadExts = trim((string)option('upload.extensions', ''));
if ($uploadExts === '') {
    $uploadExts = 'jpg,jpeg,png,gif,webp,svg,bmp,ico,zip,rar,7z,tar,gz,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,md,csv,mp3,mp4,avi,mov,wmv,flv,webm,json,xml';
}
$uploadExtList = '.' . preg_replace('/[\s,]+/', '|.', $uploadExts);
$uploadMaxSize = max(1, (int)option('upload.max_size', 20));
?>
<form id="zapFinderForm">
    <div class="row mb-3">
        <div class="col-sm-5">
            <button id="btn-parent" data-bs-toggle="tooltip" title="上一级"
                    class="btn btn-light"><i class="fa-solid fa-level-up-alt"></i></button>

            <button id="btn-refresh" data-bs-toggle="tooltip" title="刷新"
                    class="btn btn-light"><i class="fa-solid fa-rotate"></i></button>
            <button type="button" data-bs-toggle="tooltip" title="上传文件" id="btn-upload" class="btn btn-primary"
                    onclick="document.getElementById('zapUploadFinder').click()">
                <i class="fa-solid fa-upload"></i></button>
            <button type="button" data-bs-toggle="tooltip" title="创建目录" id="btn-folder" class="btn btn-light"><i
                        class="fa-solid fa-folder"></i></button>
            <button type="button" data-bs-toggle="tooltip" title="重命名" id="btn-rename" class="btn btn-light"><i
                        class="fa-solid fa-pen"></i></button>
            <button type="button" data-bs-toggle="tooltip" title="删除" id="btn-delete" class="btn btn-danger"><i
                        class="fa-regular fa-trash-can"></i></button>

        </div>
        <div class="col-sm-7">
            <div class="input-group">
                <input type="text" name="search" value="" placeholder="Search.." id="input-search" class="form-control">
                <button type="button" id="button-search" data-bs-toggle="tooltip" title="Search"
                        class="btn btn-primary"><i
                            class="fa-solid fa-search"></i></button>
            </div>
        </div>
    </div>
    <div class="finderMkdir mb-3" style="display: none; max-width: 380px;">
        <div class="input-group">
            <input type="text" name="create-folder" value="" placeholder="文件夹名称" id="input-folder"
                   class="form-control">
            <button type="button" title="创建文件夹" id="button-create" class="btn btn-primary"><i
                        class="fa-solid fa-plus-circle"></i></button>
        </div>
    </div>
    <div class="zap-message mb-2"></div>
    <style>
        /* 拖拽上传时的视觉反馈（ZAPUploader 通过 id 绑定 dropArea，与 .zapUploader 类解耦，
           因此这里使用 id 选择器替代被移除的内置 .zapUploader.highlight） */
        #zapFinderFileList.highlight {
            outline: 2px dashed var(--zap-primary, dodgerblue);
            outline-offset: -8px;
            background: rgba(16, 185, 129, 0.05);
        }
        /* 拖拽提示条不允许成为 drop 目标，保证事件落到列表容器 */
        #zapFinderDropHint { pointer-events: none; }
        #zapFinderFileList.zap-upload-disabled.highlight {
            outline-color: var(--bs-danger, #dc3545);
            background: rgba(220, 53, 69, 0.05);
        }
        /* 左侧目录树 + 右侧文件列表 */
        .zap-finder-layout { display: flex; align-items: stretch; min-height: 320px; }
        .zap-finder-tree-pane {
            flex: 0 0 218px; max-width: 218px; padding-right: .5rem;
            border-right: 1px solid var(--bs-border-color);
            display: flex; flex-direction: column;
        }
        .zap-finder-tree-head {
            display: flex; align-items: center; justify-content: space-between;
            font-size: .8rem; font-weight: 600; color: var(--bs-secondary-color);
            padding: 0 .35rem .5rem;
        }
        #zapFinderTree { flex: 1 1 auto; overflow: auto; min-height: 240px; max-height: 430px; padding: 0 .25rem; }
        .zap-finder-main { flex: 1 1 auto; min-width: 0; padding-left: .85rem; }
        #zapFinderFileList { position: relative; }
        #zapFinderTree .zf-tree-item {
            display: flex; align-items: center; gap: .35rem;
            padding: .3rem .4rem; border-radius: .35rem; cursor: pointer;
            user-select: none; white-space: nowrap; font-size: .82rem;
            color: var(--bs-body-color);
            transition: background-color .15s ease, color .15s ease;
        }
        #zapFinderTree .zf-tree-item:hover { background: rgba(13, 110, 253, .07); }
        #zapFinderTree .zf-tree-item.active {
            background: var(--bs-primary-bg-subtle, #cfe2ff);
            color: var(--bs-primary); font-weight: 600;
        }
        #zapFinderTree .zf-tree-item.zf-system .zf-tree-icon { color: var(--bs-secondary-color); }
        #zapFinderTree .zf-tree-toggle { width: .85rem; flex-shrink: 0; text-align: center; font-size: .65rem; color: var(--bs-secondary-color); }
        #zapFinderTree .zf-tree-toggle i { transition: transform .2s ease; }
        #zapFinderTree .zf-tree-toggle i.zf-expanded { transform: rotate(90deg); }
        #zapFinderTree .zf-tree-toggle .fa-minus { opacity: .35; font-size: .55rem; }
        #zapFinderTree .zf-tree-icon { width: 1rem; flex-shrink: 0; text-align: center; color: #e8a33d; }
        #zapFinderTree .zf-tree-item .fa-house { color: #868e96; }
        #zapFinderTree .zf-tree-item.active .zf-tree-icon { color: var(--bs-primary); }
        #zapFinderTree .zf-tree-badge {
            flex-shrink: 0; min-width: 1.1rem; padding: .12rem .3rem; border-radius: 999px;
            background: var(--bs-secondary-bg); color: var(--bs-secondary-color);
            font-size: .65rem; line-height: 1; text-align: center;
        }
        #zapFinderTree .zf-tree-children { margin-left: .85rem; padding-left: .3rem; border-left: 1px solid var(--bs-border-color); }
        /* 右键菜单必须浮在 Bootstrap modal(z-index 1055) 之上 */
        .zap-finder-ctx { position: fixed !important; z-index: 2000 !important; display: none; min-width: 168px; padding: .35rem 0; }
        .zap-finder-ctx .dropdown-item { display: flex; align-items: center; gap: .55rem; padding: .4rem .85rem; font-size: .85rem; cursor: pointer; }
        .zap-finder-ctx .dropdown-item i { width: 1rem; text-align: center; }
        .zap-finder-ctx .dropdown-item.disabled { opacity: .4; pointer-events: none; }
        @media (max-width: 767.98px) {
            .zap-finder-tree-pane { flex-basis: 160px; max-width: 160px; }
            .zap-finder-main { padding-left: .5rem; }
        }
    </style>
    <div class="zap-finder-layout">
        <aside class="zap-finder-tree-pane">
            <div class="zap-finder-tree-head">
                <span><i class="fa-solid fa-folder-tree me-1"></i>目录</span>
                <button type="button" id="btn-tree-refresh" class="btn btn-sm btn-light py-0 px-1"
                        title="刷新目录树" data-bs-toggle="tooltip"><i class="fa-solid fa-rotate"></i></button>
            </div>
            <div id="zapFinderTree"></div>
        </aside>
        <div class="zap-finder-main">
    <div id="zapFinderFileList">
        <input type="file" id="zapUploadFinder" multiple style="display: none;">
        <div class="progress zap-progress mb-2" style="height: 2px;position: absolute;top: 0;width: 100%;left: 0;">
            <div class="progress-bar zap-progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0"
                 aria-valuemin="0" aria-valuemax="100"></div>
        </div>
        <div id="zapFinderDropHint" class="text-center text-muted py-2 mb-3">
            <i class="fa-solid fa-cloud-arrow-down"></i> 支持拖拽文件到此处上传
        </div>
        <?php } ?>
        <div id="finderContent">
            <div class="row row-cols-2 row-cols-sm-3 row-cols-lg-5 zapFinderFileList">

                <input type="hidden" name="path" value="<?php echo e($path); ?>" id="cur-path">
                <input type="hidden" name="parent_path" value="<?php echo e($parent_path); ?>" id="parent-path">
                <?php
                foreach ($data as $i => $file) { ?>
                    <div class="col mb-3">
                        <div class="mb-1" style="min-height: 140px;">
                            <a href="<?php echo url_action('finder@list', ['path' => $file['path']]); ?>"
                               class="mb-1 dirList"
                               data-is-image="<?php echo $file['is_image'] ? 'true' : 'false'; ?>"
                               data-type="<?php echo e($file['type']); ?>"
                               data-ext="<?php echo e($file['ext']); ?>"
                               data-original="<?php echo storage_url($file['path']); ?>"
                            >
                                <?php if ($file['is_image']) { ?>
                                    <img src="<?php echo e($file['thumb_url']); ?>" alt="<?php echo e($file['filename']); ?>"
                                         title="<?php echo e($file['filename']); ?>" class="img-thumbnail"
                                         data-original="<?php echo storage_url($file['path']); ?>"
                                    >
                                <?php } else { ?>
                                    <i class="<?php echo e($file['icon']); ?> fa-9x"></i>
                                <?php } ?>
                            </a>
                        </div>
                        <div class="form-check">
                            <label for="input-path-<?php echo e($i); ?>" class="form-check-label"
                                   style="overflow-wrap: anywhere;"><?php echo e($file['filename']); ?></label>
                            <input type="checkbox" name="finder_item[]" value="<?php echo e($file['filename']); ?>"
                                   id="input-path-<?php echo e($i); ?>"
                                   class="form-check-input">
                        </div>
                    </div>
                <?php } ?>
                <br/>

            </div>
            <?php if ($pagination !== '') { ?>
            <div class="row justify-content-center mt-2">
                <div class="col-12">
                    <?php // Pagination::render() 内部已对 URL / class 做 htmlspecialchars，此处直接输出，不能再次转义 ?>
                    <?php echo $pagination; ?>
                </div>
            </div>
            <?php } ?>
        </div>
        <?php if ($initialize) { ?>
    </div>
        </div>
    </div>
    <div id="zapFinderContextMenu" class="dropdown-menu zap-finder-ctx" role="menu">
        <button type="button" class="dropdown-item" data-act="create" role="menuitem">
            <i class="fa-solid fa-folder-plus"></i>新建目录</button>
        <button type="button" class="dropdown-item" data-act="rename" role="menuitem">
            <i class="fa-solid fa-pen"></i>重命名</button>
        <div class="dropdown-divider"></div>
        <button type="button" class="dropdown-item text-danger" data-act="delete" role="menuitem">
            <i class="fa-regular fa-trash-can"></i>删除</button>
        <div class="dropdown-divider"></div>
        <button type="button" class="dropdown-item" data-act="refresh" role="menuitem">
            <i class="fa-solid fa-rotate"></i>刷新</button>
    </div>
    <script>
        Zap.EnableToolTip();
        const FinderUrl = '<?php echo url_action('finder@list') ?>?path=';
        // 首次加载由服务端注入，作为兜底；弹窗复用后以 window.zapFinder.finderConfig 为准
        let TARGET_LIST = <?php echo json_encode(array_values(array_filter((array)$target))); ?>;
        const IMG_SIZE = <?php echo json_encode((string)$size); ?>;
        const zapFinderFileList = $('#finderContent');
        const progressBar = $('#zapFinderFileList .progress-bar');
        const FinderTreeUrl = '<?php echo url_action('Finder@tree'); ?>';
        const FinderApi = {
            createDir: '<?php echo url_action('Finder@createDir'); ?>',
            rename: '<?php echo url_action('Finder@rename'); ?>',
            remove: '<?php echo url_action('Finder@delete'); ?>'
        };
        let finderTreeData = [];
        // 已展开的目录路径集合，重绘目录树时用于还原展开状态
        const finderExpanded = new Set(['']);
        const $finderContextMenu = $('#zapFinderContextMenu');
        let finderContextTarget = null;

        function getFinderConfig() {
            const cfg = (window.zapFinder && window.zapFinder.finderConfig) ? window.zapFinder.finderConfig : null;
            return cfg || {target: TARGET_LIST.join('|'), callback: '', size: IMG_SIZE};
        }
        function reloadFileList() {
            openPath(currentPath());
        }

        $('#btn-refresh').on('click', function (event) {
            event.preventDefault()
            reloadFileList();
        });
        $('#btn-parent').on('click', function (event) {
            event.preventDefault()
            openPath($('#parent-path').val());
        })

        $('#btn-folder').on('click', function (event) {
            event.preventDefault()
            $('#input-folder').val('');
            $('.finderMkdir').slideToggle(200, function () {
                if ($('.finderMkdir').is(':visible')) {
                    $('#input-folder').trigger('focus');
                }
            });
        });
        $('#input-folder').on('keydown', function (e) {
            if (e.which === 13) {
                $('#button-create').trigger('click');
            }
        })

        $('#button-create').on('click', function (e) {
            const $btn = $(this);
            const dirName = $.trim($('#input-folder').val());
            // 前端快速校验，减少无效请求
            if (dirName === '') {
                ZapToast.alert('请输入目录名称', {delay: 2500, bgColor: bgWarning});
                $('#input-folder').trigger('focus');
                return;
            }
            if (/[\/\\:*?"<>|]/.test(dirName)) {
                ZapToast.alert('目录名不能包含 \\ / : * ? " < > | 等字符', {delay: 2500, bgColor: bgWarning});
                $('#input-folder').trigger('focus').select();
                return;
            }
            // 按钮进入 loading 状态，避免重复提交并给出操作反馈
            const originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>创建中...');
            $.ajax({
                url: '<?php echo url_action('Finder@createDir'); ?>',
                method: 'post',
                data: {dir_name: dirName, path: $('#cur-path').val()},
                dataType: 'json',
                success: function (data) {
                    const isOk = data && data.code === 0;
                    try {
                        ZapToast.alert(data && data.msg ? data.msg : (isOk ? '创建成功' : '创建失败'), {
                            delay: 2500,
                            bgColor: isOk ? bgSuccess : bgDanger
                        });
                    } catch (err) { /* 提示失败不影响刷新流程 */ }
                    if (isOk) {
                        $('#input-folder').val('');
                        $('.finderMkdir').slideUp(200);
                        reloadFileList();
                    } else {
                        $('#input-folder').trigger('focus').select();
                    }
                },
                complete: function () {
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        })

        $('#btn-delete').on('click', function (e) {
            const $checked = $('#zapFinderForm input[name="finder_item[]"]:checked');
            const count = $checked.length;
            if (count === 0) {
                ZapToast.alert('请先勾选要删除的文件或目录', {bgColor: bgWarning});
                return;
            }
            // 统计文件/目录数量，让确认提示更明确
            const dirCount = $checked.closest('.col').has('a[data-type="dir"]').length;
            const fileCount = count - dirCount;
            const parts = [];
            if (fileCount > 0) parts.push(fileCount + ' 个文件');
            if (dirCount > 0) parts.push(dirCount + ' 个目录');

            // replaced=true：每次重建弹窗，确保按钮回调绑定最新内容
            ZapModal.create({
                id: 'zapFinderDeleteModal',
                title: '确认删除',
                dialog_class: 'modal-dialog-centered modal-sm',
                header_class: 'bg-danger text-white',
                content: `<div class="text-center">
                    <p class="mb-2">确定删除选中的 <b>${count}</b> 项（${parts.join('、')}）？</p>
                    <span class="text-danger">此操作不可恢复！</span>
                </div>`,
                buttons: [
                    {title: '取消', class: 'btn-secondary', close: true},
                    {title: '删除', class: 'btn-danger'}
                ],
                btn2: function (event) {
                    const $modalBtn = $(event.target).closest('button');
                    const $outerBtn = $('#btn-delete');
                    // 弹窗内删除按钮与顶部删除按钮都进入 loading 状态，防止重复提交
                    const originalHtml = $modalBtn.html();
                    $modalBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>删除中...');
                    $outerBtn.prop('disabled', true);
                    $.ajax({
                        url: '<?php echo url_action('Finder@delete'); ?>',
                        method: 'post',
                        data: $('#zapFinderForm').serialize(),
                        dataType: 'json',
                        success: function (data) {
                            const isOk = data && data.code === 0;
                            try {
                                ZapToast.alert(data && data.msg ? data.msg : (isOk ? '删除成功' : '删除失败'), {
                                    delay: 2500,
                                    bgColor: isOk ? bgSuccess : bgDanger
                                });
                            } catch (err) { /* 提示失败不影响刷新流程 */ }
                            if (isOk) {
                                reloadFileList();
                                const modalEl = document.getElementById('zapFinderDeleteModal');
                                if (modalEl && bootstrap.Modal.getInstance(modalEl)) {
                                    bootstrap.Modal.getInstance(modalEl).hide();
                                }
                            }
                        },
                        complete: function () {
                            $modalBtn.prop('disabled', false).html(originalHtml);
                            $outerBtn.prop('disabled', false);
                        }
                    });
                }
            }, true).show();
        })
        $('#btn-rename').on('click', function (e) {
            const $checked = $('#zapFinderForm input[name="finder_item[]"]:checked');
            if ($checked.length === 0) {
                ZapToast.alert('请先勾选要重命名的文件或目录', {delay: 2500, bgColor: bgWarning});
                return;
            }
            if ($checked.length > 1) {
                ZapToast.alert('每次只能重命名一个文件或目录', {delay: 2500, bgColor: bgWarning});
                return;
            }
            const oldName = $checked.val();
            ZapModal.create({
                id: 'zapFinderRenameModal',
                title: '重命名',
                dialog_class: 'modal-dialog-centered modal-sm',
                header_class: 'bg-primary text-white',
                content: `
                    <input type="text" class="form-control" id="input-rename" value="${oldName.replace(/"/g, '&quot;')}" placeholder="输入新名称">
                    <div class="form-text mt-2">原名称：<code>${oldName.replace(/"/g, '&quot;')}</code></div>
                `,
                buttons: [
                    {title: '取消', class: 'btn-secondary', close: true},
                    {title: '保存', class: 'btn-primary'}
                ],
                btn2: function (event) {
                    const $modalBtn = $(event.target).closest('button');
                    const newName = $.trim($('#input-rename').val());
                    if (newName === '') {
                        ZapToast.alert('请输入新名称', {delay: 2500, bgColor: bgWarning});
                        $('#input-rename').trigger('focus');
                        return;
                    }
                    if (/[\/\\:*?"<>|]/.test(newName)) {
                        ZapToast.alert('名称不能包含 \\ / : * ? " < > | 等字符', {delay: 2500, bgColor: bgWarning});
                        $('#input-rename').trigger('focus').select();
                        return;
                    }
                    const originalHtml = $modalBtn.html();
                    $modalBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>保存中...');
                    $.ajax({
                        url: '<?php echo url_action('Finder@rename'); ?>',
                        method: 'post',
                        data: {old_name: oldName, new_name: newName, path: $('#cur-path').val()},
                        dataType: 'json',
                        success: function (data) {
                            const isOk = data && data.code === 0;
                            try {
                                ZapToast.alert(data && data.msg ? data.msg : (isOk ? '重命名成功' : '重命名失败'), {
                                    delay: 2500,
                                    bgColor: isOk ? bgSuccess : bgDanger
                                });
                            } catch (err) { /* 提示失败不影响刷新流程 */ }
                            if (isOk) {
                                reloadFileList();
                                const modalEl = document.getElementById('zapFinderRenameModal');
                                if (modalEl && bootstrap.Modal.getInstance(modalEl)) {
                                    bootstrap.Modal.getInstance(modalEl).hide();
                                }
                            }
                        },
                        complete: function () {
                            $modalBtn.prop('disabled', false).html(originalHtml);
                        }
                    });
                }
            }, true).show();
            // 弹窗显示后聚焦并选中输入框（去掉扩展名部分，方便直接改名）
            setTimeout(function () {
                const $input = $('#input-rename');
                if ($input.length) {
                    $input.trigger('focus');
                    const dot = $input.val().lastIndexOf('.');
                    if (dot > 0) {
                        $input.get(0).setSelectionRange(0, dot);
                    }
                }
            }, 200);
        });
        function doSearch() {
            zapFinderFileList.load(FinderUrl + encodeURIComponent(currentPath()) + '&search=' + encodeURIComponent($('#input-search').val()), loadCallback);
        }
        $('#button-search').on('click', function () {
            doSearch();
        });
        // 搜索框回车执行搜索，避免触发表单默认提交跳到 storage 根目录
        $('#input-search').on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                doSearch();
            }
        });
        // 兜底：表单内任何回车都不允许默认 GET 提交
        $('#zapFinderForm').on('submit', function (e) {
            e.preventDefault();
        });

        zapFinderFileList.on('click', 'a.dirList', function (event) {
            event.preventDefault()
            event.stopPropagation()
            const $item = $(this);
            if ($item.data('type') === 'dir') {
                zapFinderFileList.load($item.attr('href'), loadCallback);
            }
            if ($item.data('type') === 'file') {
                const cfg = getFinderConfig();
                // 仅允许安全的全局函数名作为回调，防止 URL 参数注入
                const cbName = cfg.callback;
                if (cbName && /^[a-zA-Z_$][a-zA-Z0-9_$]*$/.test(cbName) && typeof window[cbName] === 'function') {
                    window[cbName](event);
                }
                const targets = cfg.target ? cfg.target.split('|').map(s => s.trim()).filter(Boolean) : TARGET_LIST;
                const imgSize = cfg.size || IMG_SIZE;
                const original = $item.data('original');
                targets.forEach((value) => {
                    const $target = $(value);
                    if ($target[0] !== undefined && $target[0].nodeName === 'IMG') {
                        if (imgSize === 'original') {
                            $target.prop('src', original)
                        } else {
                            $target.prop('src', $item.find('img').attr('src'))
                        }
                        $target.attr('data-original', original)
                    } else if ($target[0] !== undefined && $target[0].nodeName === 'INPUT') {
                        $target.val(original)
                    }
                })
                if (window.zapFinder) {
                    window.zapFinder.hide();
                }
            }
        })
        // 分页链接局部刷新：#finderContent 内容会被 .load() 整体替换，
        // 因此必须用事件委托；跳过当前页与无效链接，避免重复请求。
        zapFinderFileList.on('click', 'nav a', function (event) {
            event.preventDefault()
            event.stopPropagation()
            const $item = $(this).closest('li');
            if ($item.hasClass('active') || $item.hasClass('disabled')) {
                return;
            }
            const href = $(this).attr('href');
            if (href && href !== '#') {
                zapFinderFileList.load(href, loadCallback);
            }
        });

        // 缩略图缓存目录（thumbs）禁止上传：隐藏上传按钮、切换拖拽提示、阻止拖拽 drop
        function updateUploadState() {
            const path = String($('#cur-path').val() || '').replace(/\\/g, '/');
            const forbidden = path === 'thumbs' || path.indexOf('thumbs/') === 0;
            $('#btn-upload').toggle(!forbidden);
            $('#zapFinderFileList').toggleClass('zap-upload-disabled', forbidden);
            const $hint = $('#zapFinderDropHint');
            if (forbidden) {
                $hint.removeClass('text-muted').addClass('text-warning')
                    .html('<i class="fa-solid fa-ban"></i> 该目录为系统缩略图缓存，禁止上传文件');
            } else {
                $hint.removeClass('text-warning').addClass('text-muted')
                    .html('<i class="fa-solid fa-cloud-arrow-down"></i> 支持拖拽文件到此处上传');
            }
        }
        updateUploadState();

        // capture 阶段拦截 thumbs 目录的拖拽放置，防止触发上传
        $('#zapFinderFileList')[0].addEventListener('drop', function (event) {
            if ($('#zapFinderFileList').hasClass('zap-upload-disabled')) {
                event.preventDefault();
                event.stopPropagation();
            }
        }, true);

        function loadCallback(response, status, xhr) {
            updateUploadState();
            // 分页 / 目录跳转后同步左侧目录树的高亮与展开状态
            syncFinderTree();
            if (status === "error") {
                ZapToast.alert(response, {bgColor: bgDanger})
            }
        }

        /* ==================== 左侧目录树 ==================== */
        function currentPath() {
            return String($('#cur-path').val() || '');
        }

        function parentPathOf(path) {
            const p = String(path || '');
            const i = p.lastIndexOf('/');
            return i < 0 ? '' : p.substring(0, i);
        }

        // 展开目标路径及其所有祖先，便于跳转到深层目录时自动展开
        function finderExpandAncestors(path) {
            finderExpanded.add('');
            let cur = '';
            String(path || '').split('/').filter(Boolean).forEach(function (seg) {
                cur = cur ? cur + '/' + seg : seg;
                finderExpanded.add(cur);
            });
        }

        function buildFinderTreeNode(node, isRoot) {
            const hasChildren = Array.isArray(node.children) && node.children.length > 0;
            const nodePath = String(node.path || '');
            const name = isRoot ? '全部文件' : String(node.name || '');
            const expanded = finderExpanded.has(nodePath);

            // 全部用 jQuery 的 .text()/.attr() 写入，避免目录名中的特殊字符造成 XSS
            const $item = $('<div>').addClass('zf-tree-item')
                .attr({'data-path': nodePath, 'data-name': isRoot ? '' : name})
                .attr('data-system', node.system ? '1' : '0')
                .toggleClass('active', currentPath() === nodePath)
                .toggleClass('zf-system', !!node.system);
            $item.append($('<span class="zf-tree-toggle">').html(hasChildren
                ? '<i class="fa-solid fa-chevron-right' + (expanded ? ' zf-expanded' : '') + '"></i>'
                : '<i class="fa-solid fa-minus"></i>'));
            $item.append($('<i>').addClass('zf-tree-icon fa-solid ' +
                (isRoot ? 'fa-house' : (expanded && hasChildren ? 'fa-folder-open' : 'fa-folder'))));
            $item.append($('<span class="zf-tree-label text-truncate flex-fill">').text(name).attr('title', name));
            if (node.count > 0) {
                $item.append($('<span class="zf-tree-badge">').text(node.count));
            }

            const $children = $('<div class="zf-tree-children">');
            if (hasChildren) {
                node.children.forEach(function (child) {
                    $children.append(buildFinderTreeNode(child, false));
                });
            }
            if (!expanded) {
                $children.hide();
            }
            return $('<div class="zf-tree-node">').append($item, $children);
        }

        function renderFinderTree() {
            const $tree = $('#zapFinderTree');
            if (!$tree.length) return;
            $tree.empty().append(buildFinderTreeNode({
                name: '全部文件', path: '', count: 0, children: finderTreeData
            }, true));
        }

        function reloadFinderTree() {
            $.getJSON(FinderTreeUrl).done(function (res) {
                if (!res || res.code !== 0) return;
                finderTreeData = Array.isArray(res.data) ? res.data : [];
                renderFinderTree();
            });
        }

        function syncFinderTree() {
            finderExpandAncestors(currentPath());
            renderFinderTree();
        }

        function openPath(path) {
            zapFinderFileList.load(FinderUrl + encodeURIComponent(String(path || '')), loadCallback);
        }

        $('#zapFinderTree').on('click', '.zf-tree-toggle', function (e) {
            e.stopPropagation();
            const $node = $(this).closest('.zf-tree-node');
            const $children = $node.children('.zf-tree-children');
            if (!$children.children().length) return;
            const $item = $node.children('.zf-tree-item');
            const path = String($item.attr('data-path') || '');
            const opening = !$children.is(':visible');
            $children.toggle(opening);
            $item.find('.zf-tree-toggle i').toggleClass('zf-expanded', opening);
            $item.find('.zf-tree-icon').toggleClass('fa-folder-open', opening).toggleClass('fa-folder', !opening);
            if (opening) {
                finderExpanded.add(path);
            } else {
                finderExpanded.delete(path);
            }
        });

        $('#zapFinderTree').on('click', '.zf-tree-item', function () {
            const path = String($(this).attr('data-path') || '');
            finderExpandAncestors(path);
            renderFinderTree();
            openPath(path);
        });

        $('#btn-tree-refresh').on('click', function (e) {
            e.preventDefault();
            reloadFinderTree();
        });

        /* ==================== 右键菜单 ==================== */
        // dir      = 父目录，用于重命名/删除
        // createIn = 新建目录的落点（右键点中的目录本身）
        function finderTreeTarget($item) {
            const path = String($item.attr('data-path') || '');
            const name = String($item.attr('data-name') || '');
            return {
                dir: name ? parentPathOf(path) : '',
                createIn: path,
                name: name,
                nodePath: path,
                isDir: true,
                system: $item.attr('data-system') === '1',
                label: name || '根目录'
            };
        }

        function finderGridTarget($a) {
            const name = String($a.closest('.col').find('.form-check-input').val() || '');
            const cur = currentPath();
            const isDir = $a.data('type') === 'dir';
            // 文件没有 nodePath，删除/改名时不会误判为「当前所在目录」
            const nodePath = name && isDir ? (cur ? cur + '/' + name : name) : '';
            return {
                dir: cur,
                createIn: isDir && name ? nodePath : cur,
                name: name,
                nodePath: nodePath,
                isDir: isDir,
                system: name === 'thumbs' && cur === '',
                label: name || '当前目录'
            };
        }

        function finderBlankTarget() {
            return {
                dir: currentPath(), createIn: currentPath(), name: '',
                nodePath: '', isDir: true, system: false, label: '当前目录'
            };
        }

        function showFinderContextMenu(e, target) {
            finderContextTarget = target;
            const canEdit = !!target.name && !target.system;
            $finderContextMenu.find('[data-act="rename"], [data-act="delete"]').toggleClass('disabled', !canEdit);
            $finderContextMenu.css({display: 'block', left: 0, top: 0});
            const w = $finderContextMenu.outerWidth();
            const h = $finderContextMenu.outerHeight();
            const left = Math.max(4, Math.min(e.clientX, window.innerWidth - w - 8));
            const top = Math.max(4, Math.min(e.clientY, window.innerHeight - h - 8));
            $finderContextMenu.css({left: left + 'px', top: top + 'px'});
            e.preventDefault();
        }

        function hideFinderContextMenu() {
            $finderContextMenu.hide();
            finderContextTarget = null;
        }

        $('#zapFinderTree').on('contextmenu', '.zf-tree-item', function (e) {
            showFinderContextMenu(e, finderTreeTarget($(this)));
        });
        $('#zapFinderTree').on('contextmenu', function (e) {
            if (!$(e.target).closest('.zf-tree-item').length) {
                showFinderContextMenu(e, finderBlankTarget());
            }
        });
        zapFinderFileList.on('contextmenu', 'a.dirList', function (e) {
            showFinderContextMenu(e, finderGridTarget($(this)));
        });
        zapFinderFileList.on('contextmenu', function (e) {
            if (!$(e.target).closest('a.dirList').length) {
                showFinderContextMenu(e, finderBlankTarget());
            }
        });
        // 弹窗可能被重建，先解绑再绑定，避免同名 handler 重复累积
        $(document).off('mousedown.zapFinderCtx').on('mousedown.zapFinderCtx', function (e) {
            if (!$(e.target).closest('#zapFinderContextMenu').length) hideFinderContextMenu();
        });
        $(document).off('keydown.zapFinderCtx').on('keydown.zapFinderCtx', function (e) {
            if (e.key === 'Escape') hideFinderContextMenu();
        });

        function finderToast(data) {
            const ok = !!(data && data.code === 0);
            try {
                ZapToast.alert(data && data.msg ? data.msg : (ok ? '操作成功' : '操作失败'), {
                    delay: 2500, bgColor: ok ? bgSuccess : bgDanger
                });
            } catch (err) { /* 提示失败不影响后续刷新 */ }
            return ok;
        }

        function finderPathInside(path, nodePath) {
            return !!nodePath && (path === nodePath || path.indexOf(nodePath + '/') === 0);
        }

        function finderPrompt(title, value, okText, onOk) {
            const id = 'zapFinderInputModal';
            const inputHtml = $('<input>', {
                type: 'text', 'class': 'form-control', id: id + '-input', value: value || ''
            })[0].outerHTML;
            ZapModal.create({
                id: id,
                title: title,
                dialog_class: 'modal-dialog-centered modal-sm',
                header_class: 'bg-primary text-white',
                content: inputHtml + '<div class="form-text mt-2">名称不能包含 \\ / : * ? " &lt; &gt; |</div>',
                buttons: [{title: '取消', class: 'btn-secondary', close: true}, {title: okText, class: 'btn-primary'}],
                btn2: function () {
                    const v = $.trim($('#' + id + '-input').val());
                    if (v === '') {
                        ZapToast.alert('名称不能为空', {delay: 2500, bgColor: bgWarning});
                        return;
                    }
                    if (/[\/\\:*?"<>|]/.test(v)) {
                        ZapToast.alert('名称包含非法字符 \\ / : * ? " < > |', {delay: 2500, bgColor: bgWarning});
                        return;
                    }
                    const modalEl = document.getElementById(id);
                    if (modalEl && bootstrap.Modal.getInstance(modalEl)) {
                        bootstrap.Modal.getInstance(modalEl).hide();
                    }
                    onOk(v);
                }
            }, true).show();
            setTimeout(function () {
                $('#' + id + '-input').trigger('focus').select();
            }, 200);
        }

        function finderCreateDir(dir) {
            finderPrompt('新建目录', '', '创建', function (name) {
                $.ajax({
                    url: FinderApi.createDir, method: 'post',
                    data: {dir_name: name, path: dir}, dataType: 'json',
                    success: function (res) {
                        if (!finderToast(res)) return;
                        finderExpandAncestors(dir || '');
                        reloadFinderTree();
                        if ((dir || '') === currentPath()) reloadFileList();
                    }
                });
            });
        }

        function finderRenameItem(t) {
            finderPrompt('重命名', t.name, '保存', function (name) {
                $.ajax({
                    url: FinderApi.rename, method: 'post',
                    data: {old_name: t.name, new_name: name, path: t.dir}, dataType: 'json',
                    success: function (res) {
                        if (!finderToast(res)) return;
                        const cur = currentPath();
                        if (t.isDir && finderPathInside(cur, t.nodePath)) {
                            // 当前所在目录（或其祖先）被改名，跳转到改名后的路径
                            const rest = cur.substring(t.nodePath.length);
                            const nextPath = (t.dir ? t.dir + '/' : '') + name + rest;
                            finderExpandAncestors(nextPath);
                            reloadFinderTree();
                            openPath(nextPath);
                        } else {
                            finderExpandAncestors(t.dir || '');
                            reloadFinderTree();
                            reloadFileList();
                        }
                    }
                });
            });
        }

        function finderDeleteItem(t) {
            const id = 'zapFinderCtxDeleteModal';
            ZapModal.create({
                id: id, title: '确认删除',
                dialog_class: 'modal-dialog-centered modal-sm',
                header_class: 'bg-danger text-white',
                content: '<div class="text-center"><p class="mb-2">确定删除 <b class="zf-del-name"></b>？</p>' +
                    '<span class="text-danger">此操作不可恢复！目录会被递归删除。</span></div>',
                buttons: [{title: '取消', class: 'btn-secondary', close: true}, {title: '删除', class: 'btn-danger'}],
                btn2: function () {
                    const modalEl = document.getElementById(id);
                    if (modalEl && bootstrap.Modal.getInstance(modalEl)) {
                        bootstrap.Modal.getInstance(modalEl).hide();
                    }
                    $.ajax({
                        url: FinderApi.remove, method: 'post',
                        // 后端 req()->post('finder_item') 按数组读取
                        data: {path: t.dir, 'finder_item[]': t.name}, dataType: 'json',
                        success: function (res) {
                            if (!finderToast(res)) return;
                            const cur = currentPath();
                            if (t.isDir && finderPathInside(cur, t.nodePath)) {
                                // 当前所在目录（或其祖先）被删除，退回父目录
                                finderExpandAncestors(t.dir || '');
                                reloadFinderTree();
                                openPath(t.dir || '');
                            } else {
                                finderExpandAncestors(t.dir || '');
                                reloadFinderTree();
                                reloadFileList();
                            }
                        }
                    });
                }
            }, true).show();
            $('#' + id + ' .zf-del-name').text(t.name);
        }

        $finderContextMenu.on('click', '[data-act]', function (e) {
            e.preventDefault();
            const act = $(this).attr('data-act');
            const t = finderContextTarget;
            hideFinderContextMenu();
            if (!t) return;
            if (act === 'create') {
                finderCreateDir(t.createIn);
            } else if (act === 'rename' && t.name && !t.system) {
                finderRenameItem(t);
            } else if (act === 'delete' && t.name && !t.system) {
                finderDeleteItem(t);
            } else if (act === 'refresh') {
                reloadFinderTree();
                reloadFileList();
            }
        });

        finderExpandAncestors(currentPath());
        reloadFinderTree();

        var upload = new ZAPUploader('#zapFinderFileList', {
            // 支持拖拽上传整个目录（插件通过 webkitGetAsEntry 遍历子目录并保留结构）
            directoryUpload: true,
            // 老浏览器不支持 entry API 时，目录条目无 type，直接跳过而非报错
            skipInvalidFile: true,
            allowedExtensions: '<?php echo $uploadExtList ?>',
            maxFileSize: <?php echo $uploadMaxSize ?>,
            url: '<?php echo url_action('Upload@file') ?>',
            messageContainer: '#zapFinderForm .zap-message',
            error: function (id, msg) {
                if (id === undefined || id === null) {
                    $('#zapFinderForm .zap-message').empty();
                    return;
                }
                // 带关闭按钮的错误提示，点击可手动关闭
                const $alert = $('<div class="alert alert-danger alert-dismissible fade show py-2 mb-2" role="alert"></div>');
                $('<button type="button" class="btn-close" aria-label="关闭"></button>')
                    .on('click', function () { $alert.remove(); })
                    .appendTo($alert);
                $('<span></span>').text(msg).appendTo($alert);
                $('#zapFinderForm .zap-message').append($alert);
            },
            sending:function(file,xhr,formData){
                formData.append('path',$('#cur-path').val());
            },
            progress:function(percent){
                progressBar.css('width',percent + '%');
                if(percent === 100){
                    reloadFileList();
                }
            }
        });

    </script>
    <?php } ?>
</form>
