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
    </style>
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
            <div class="row justify-content-center mt-2">
                <div class="col-12">
                    <?php echo e($pagination); ?>
                </div>
            </div>
        </div>
        <?php if ($initialize) { ?>
    </div>
    <script>
        Zap.EnableToolTip();
        const FinderUrl = '<?php echo url_action('finder@list') ?>?path=';
        // 首次加载由服务端注入，作为兜底；弹窗复用后以 window.zapFinder.finderConfig 为准
        let TARGET_LIST = <?php echo json_encode(array_values(array_filter((array)$target))); ?>;
        const IMG_SIZE = <?php echo json_encode((string)$size); ?>;
        const zapFinderFileList = $('#finderContent');
        const progressBar = $('#zapFinderFileList .progress-bar');

        function getFinderConfig() {
            const cfg = (window.zapFinder && window.zapFinder.finderConfig) ? window.zapFinder.finderConfig : null;
            return cfg || {target: TARGET_LIST.join('|'), callback: '', size: IMG_SIZE};
        }
        function reloadFileList() {
            zapFinderFileList.load(FinderUrl + $('#cur-path').val(), loadCallback);
        }

        $('#btn-refresh').on('click', function (event) {
            event.preventDefault()
            reloadFileList();
        });
        $('#btn-parent').on('click', function (event) {
            event.preventDefault()
            zapFinderFileList.load(FinderUrl + $('#parent-path').val(), loadCallback);
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
            zapFinderFileList.load(FinderUrl + $('#cur-path').val() + '&search=' + encodeURIComponent($('#input-search').val()), loadCallback);
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
        zapFinderFileList.on('click', 'nav a', function (event) {
            event.preventDefault()
            event.stopPropagation()
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
            if (status === "error") {
                ZapToast.alert(response, {bgColor: bgDanger})
            }
        }

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
