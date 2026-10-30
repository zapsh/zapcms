<?php
$this->layout('layouts/common');
/**
 * @var string $path 当前目录路径
 */
?>
<style>
.zap-dev-container {
    display: flex;
    height: calc(100vh - 190px);
    min-height: 500px;
    margin: -0.5rem;
}
.zap-dev-sidebar {
    width: 260px;
    min-width: 260px;
    border-right: 1px solid var(--bs-border-color);
    background: var(--bs-body-bg);
    display: flex;
    flex-direction: column;
}
.zap-dev-sidebar-header {
    padding: 0.75rem;
    border-bottom: 1px solid var(--bs-border-color);
    flex-shrink: 0;
}
.zap-dev-sidebar-header .input-group-text {
    background: transparent;
    border-right: none;
}
.zap-dev-sidebar-header .form-control {
    border-left: none;
}
.zap-dev-sidebar-header .form-control:focus {
    border-color: var(--bs-border-color);
    box-shadow: none;
}
.zap-dev-file-tree {
    flex: 1;
    overflow-y: auto;
    padding: 0.25rem 0;
}
.zap-dev-file-tree .list-group {
    border-radius: 0;
}
.zap-dev-file-tree .list-group-item {
    border: none;
    border-radius: 0;
    padding: 0.35rem 0.75rem;
    cursor: pointer;
    font-size: 0.875rem;
    color: var(--bs-body-color);
    display: flex;
    align-items: center;
    gap: 0.4rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    user-select: none;
    border-left: 3px solid transparent;
}
.zap-dev-file-tree .list-group-item:hover {
    background: var(--bs-secondary-bg);
}
.zap-dev-file-tree .list-group-item.active {
    background: var(--bs-primary-bg-subtle);
    border-left-color: var(--bs-primary);
    color: var(--bs-primary);
    font-weight: 500;
}
.zap-dev-file-tree .list-group-item .item-icon {
    width: 16px;
    text-align: center;
    flex-shrink: 0;
    font-size: 0.85rem;
}
.zap-dev-file-tree .list-group-item.dir-item {
    font-weight: 500;
}
.zap-dev-file-tree .list-group-item.dir-item .chevron {
    transition: transform 0.2s;
    font-size: 0.7rem;
    margin-left: auto;
    color: var(--bs-secondary-color);
}
.zap-dev-file-tree .list-group-item.dir-item.expanded .chevron {
    transform: rotate(90deg);
}
.zap-dev-file-tree .list-group-item .item-name {
    overflow: hidden;
    text-overflow: ellipsis;
    flex: 1;
}
.zap-dev-file-tree .child-list {
    display: none;
    position: relative;
    margin-left: 1rem; /* 每层递进缩进,嵌套自动累加,支持任意层级 */
    padding-left: 0.3rem;
    border-left: 1px solid var(--bs-border-color); /* 树形引导竖线 */
}
.zap-dev-file-tree .child-list.open {
    display: block;
}
.zap-dev-file-tree .child-list .list-group-item {
    position: relative;
    padding-left: 0.75rem;
}
/* 节点与父级竖线的连接横线 */
.zap-dev-file-tree .child-list .list-group-item::before {
    content: '';
    position: absolute;
    left: -0.3rem;
    top: 50%;
    width: 0.3rem;
    height: 1px;
    background: var(--bs-secondary-bg);
}
.zap-dev-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
    background: var(--bs-body-bg);
}
.zap-dev-tabs {
    display: flex;
    border-bottom: 1px solid var(--bs-border-color);
    background: var(--bs-tertiary-bg);
    overflow-x: auto;
    flex-shrink: 0;
    scrollbar-width: none;
}
.zap-dev-tabs::-webkit-scrollbar {
    display: none;
}
.zap-dev-tabs .os-scrollbar {
    display: none !important;
}
.zap-dev-tab {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.5rem 0.75rem;
    border-right: 1px solid var(--bs-border-color);
    cursor: pointer;
    font-size: 0.82rem;
    white-space: nowrap;
    color: var(--bs-secondary-color);
    background: var(--bs-tertiary-bg);
    min-width: 0;
    transition: background 0.15s;
}
.zap-dev-tab:hover {
    background: var(--bs-secondary-bg);
}
.zap-dev-tab.active {
    background: var(--bs-body-bg);
    color: var(--bs-body-color);
    font-weight: 500;
    border-bottom: 2px solid var(--bs-primary);
    margin-bottom: -1px;
}
.zap-dev-tab .tab-name {
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 120px;
}
.zap-dev-tab .tab-close {
    flex-shrink: 0;
    width: 16px;
    height: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 3px;
    font-size: 0.65rem;
    color: var(--bs-secondary-color);
}
.zap-dev-tab .tab-close:hover {
    background: var(--bs-border-color);
    color: var(--bs-body-color);
}
.zap-dev-tab .tab-modified {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #fd7e14;
    flex-shrink: 0;
}
.zap-dev-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.4rem 0.75rem;
    border-bottom: 1px solid var(--bs-border-color);
    background: var(--bs-tertiary-bg);
    flex-shrink: 0;
    gap: 0.75rem;
}
.zap-dev-toolbar .file-info {
    font-size: 0.8rem;
    color: var(--bs-secondary-color);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    flex: 1;
}
.zap-dev-toolbar .file-info .file-lang {
    display: inline-block;
    padding: 0.1rem 0.4rem;
    background: var(--bs-secondary-bg);
    border-radius: 3px;
    font-size: 0.7rem;
    margin-left: 0.5rem;
    text-transform: uppercase;
}
.zap-dev-toolbar .toolbar-actions {
    display: flex;
    gap: 0.35rem;
    align-items: center;
    flex-shrink: 0;
}
.zap-dev-toolbar .toolbar-actions .btn {
    font-size: 0.8rem;
    padding: 0.25rem 0.6rem;
}
.zap-dev-editor-wrap {
    display: flex;
    flex-direction: column;
    flex: 1;
    position: relative;
    min-height: 0;
}
.zap-dev-editor-wrap .editor-pane {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: none;
}
.zap-dev-editor-wrap .editor-pane.active {
    display: block;
}
.zap-dev-statusbar {
    display: flex;
    align-items: center;
    padding: 0.2rem 0.75rem;
    background: var(--bs-primary);
    color: #fff;
    font-size: 0.75rem;
    flex-shrink: 0;
    gap: 1.5rem;
}
.zap-dev-statusbar span {
    display: flex;
    align-items: center;
    gap: 0.3rem;
}
.zap-dev-welcome {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: var(--bs-secondary-color);
    gap: 0.75rem;
}
.zap-dev-welcome i {
    font-size: 3rem;
}
.zap-dev-welcome .hint {
    font-size: 0.8rem;
    color: #ced4da;
}
.zap-dev-welcome .shortcuts {
    display: flex;
    gap: 1.5rem;
    margin-top: 1rem;
}
.zap-dev-welcome .shortcuts kbd {
    background: var(--bs-tertiary-bg);
    border: 1px solid var(--bs-border-color);
    padding: 0.15rem 0.4rem;
    border-radius: 3px;
    font-size: 0.75rem;
    color: var(--bs-secondary-color);
}
.empty-state {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: var(--bs-secondary-color);
}
.empty-state i {
    font-size: 3rem;
    margin-bottom: 0.75rem;
}
.empty-state p {
    font-size: 0.9rem;
    margin: 0;
}
.loading-spinner {
    padding: 0.25rem 0.75rem;
    font-size: 0.8rem;
    color: var(--bs-secondary-color);
}
</style>

<div class="zap-dev-container">
    <!-- 左侧文件树 -->
    <div class="zap-dev-sidebar">
        <div class="zap-dev-sidebar-header">
            <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="fa fa-search"></i></span>
                <input type="text" class="form-control" id="fileSearch" placeholder="搜索文件...">
            </div>
        </div>
        <div class="zap-dev-file-tree" id="fileTreeView">
            <div class="loading-spinner text-center">
                <i class="fa fa-spinner fa-spin"></i> 加载中...
            </div>
        </div>
    </div>

    <!-- 右侧编辑区 -->
    <div class="zap-dev-main">
        <!-- 文件标签栏 -->
        <div class="zap-dev-tabs" id="fileToolbarTabs"></div>

        <!-- 编辑器工具栏 -->
        <div class="zap-dev-toolbar" id="editorToolbar" style="display:none;">
            <div class="file-info">
                <span id="currentFilePath">-</span>
                <span class="file-lang" id="currentFileLang">-</span>
            </div>
            <div class="toolbar-actions">
                <button class="btn btn-sm btn-primary" id="btnSave" title="保存文件 (Ctrl+S)">
                    <i class="fa fa-save"></i> 保存
                </button>
            </div>
        </div>

        <!-- 编辑器面板 -->
        <div class="zap-dev-editor-wrap" id="fileContentTabs">
            <div class="empty-state" id="welcomeScreen">
                <i class="fa fa-code"></i>
                <p>从左侧文件树选择文件开始编辑</p>
                <div class="shortcuts">
                    <span><kbd>Ctrl+S</kbd> 保存文件</span>
                </div>
            </div>
        </div>

        <!-- 状态栏 -->
        <div class="zap-dev-statusbar" id="statusBar">
            <span><i class="fa fa-folder-open"></i> <span id="statusRoot"><?php echo htmlspecialchars($path); ?></span></span>
            <span id="statusInfo">就绪</span>
        </div>
    </div>
</div>

<script>
const fileTreeView = $('#fileTreeView');
const editorToolbar = $('#editorToolbar');
const welcomeScreen = $('#welcomeScreen');
const fileToolbarTabs = $('#fileToolbarTabs');
const fileContentTabs = $('#fileContentTabs');
const statusInfo = $('#statusInfo');
const editorInstances = {};
let activeFilePath = null;
let editorSeq = 0;

// 编辑器按需加载：首次打开文件时才下载 CodeMirror 6（assets/admin/js/editor.js）
let zapEditorPromise = null;
function loadZapEditor() {
    if (window.ZapEditor) {
        return Promise.resolve(window.ZapEditor);
    }
    if (zapEditorPromise) {
        return zapEditorPromise;
    }
    zapEditorPromise = new Promise(function(resolve, reject) {
        const script = document.createElement('script');
        script.type = 'module';
        script.src = (window.ZAP_ASSETS_URL || '') + '/js/editor.js';
        script.onload = function() {
            window.ZapEditor ? resolve(window.ZapEditor) : reject(new Error('编辑器初始化失败'));
        };
        script.onerror = function() {
            reject(new Error('编辑器加载失败'));
        };
        document.head.appendChild(script);
    }).catch(function(err) {
        // 允许下次打开文件时重试
        zapEditorPromise = null;
        throw err;
    });
    return zapEditorPromise;
}

// 文件搜索
let searchTimer = null;
$('#fileSearch').on('input', function() {
    clearTimeout(searchTimer);
    const query = $(this).val().toLowerCase();
    searchTimer = setTimeout(function() {
        fileTreeView.find('.list-group-item.file-item').each(function() {
            const name = $(this).find('.item-name').text().toLowerCase();
            if (query === '' || name.includes(query)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
        // 展开包含匹配结果的目录
        if (query !== '') {
            fileTreeView.find('.child-list').addClass('open');
            fileTreeView.find('.dir-item').addClass('expanded');
        }
    }, 300);
});

$(function() {
    getDir('<?php echo $path;?>', null);

    // 文件树点击事件
    fileTreeView.on('click', '.list-group-item', function(e) {
        e.preventDefault();
        e.stopPropagation();

        const type = $(this).data('type');
        const path = $(this).data('path');

        if (type === 'dir') {
            // 展开/折叠目录
            $(this).toggleClass('expanded');
            $(this).next('.child-list').toggleClass('open');

            // 如果还没加载子目录内容
            if (!$(this).data('loaded')) {
                getDir(path, this);
                $(this).data('loaded', true);
            }
        } else if (type === 'file') {
            // 如果文件已打开，切换到对应标签
            if (editorInstances[path]) {
                activateTab(path);
            } else {
                getDir(path, this);
            }

            // 高亮当前文件
            fileTreeView.find('.list-group-item').removeClass('active');
            $(this).addClass('active');
        }
    });

    // 标签点击
    fileToolbarTabs.on('click', '.zap-dev-tab', function(e) {
        if ($(e.target).hasClass('tab-close')) return;
        const path = $(this).data('path');
        activateTab(path);
    });

    // 标签关闭
    fileToolbarTabs.on('click', '.tab-close', function(e) {
        e.stopPropagation();
        const path = $(this).closest('.zap-dev-tab').data('path');
        closeTab(path);
    });

    // 保存按钮
    $('#btnSave').on('click', function() {
        saveCurrentFile();
    });

    // Ctrl+S 保存
    $(document).on('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            saveCurrentFile();
        }
    });
});

function getDir(path, pEl) {
    if (!path) {
        path = '/';
    }

    const iconEl = pEl ? $(pEl).find('.item-icon:first') : null;
    const iconClass = iconEl ? iconEl.attr('class') : '';

    if (iconEl) {
        iconEl.attr('class', 'fa-solid fa-spinner fa-spin item-icon');
    }
    if (pEl) {
        $(pEl).addClass('loading');
    }

    $.ajax({
        url: '<?php echo url_action('Development@getDir'); ?>?path=' + encodeURIComponent(path),
        success: function(resp) {
            if (resp.type === 'list') {
                renderFileList(resp.data, pEl);
            } else if (resp.type === 'content') {
                openFile(resp.path, resp.filename, resp.content).catch(function(err) {
                    showToast(err.message || '编辑器加载失败', 'error');
                });
            }
        },
        error: function() {
            showToast('加载失败', 'error');
        }
    }).always(function() {
        if (iconEl) {
            iconEl.attr('class', iconClass);
        }
        if (pEl) {
            $(pEl).removeClass('loading');
        }
    });
}

function renderFileList(data, pEl) {
    let container;
    if (pEl && $(pEl).hasClass('dir-item')) {
        // 在目录项后面创建子列表
        let childList = $(pEl).next('.child-list');
        if (childList.length === 0) {
            childList = $('<div class="child-list open"></div>').insertAfter(pEl);
        }
        childList.empty();
        container = childList;
    } else {
        // 根级别 - 先清除加载提示
        fileTreeView.find('.loading-spinner').remove();
        const existingList = fileTreeView.find('> .list-group');
        if (existingList.length > 0) {
            existingList.remove();
        }
        container = $('<div class="list-group list-group-flush"></div>').appendTo(fileTreeView);
    }

    for (const f of data) {
        const isDir = f.type === 'dir';
        const hasChildren = isDir;

        const item = $(`<div class="list-group-item ${isDir ? 'dir-item' : 'file-item'}"
            data-filename="${escapeHtml(f.filename)}"
            data-path="${escapeHtml(f.path)}"
            data-type="${escapeHtml(f.type)}">
            <i class="${escapeHtml(f.icon)} item-icon"></i>
            <span class="item-name">${escapeHtml(f.filename)}</span>
            ${isDir ? '<i class="fa fa-chevron-right chevron"></i>' : ''}
        </div>`);

        container.append(item);
    }
}

async function openFile(path, filename, content) {
    // 如果已打开，更新内容并激活
    if (editorInstances[path]) {
        editorInstances[path].originalContent = content;
        editorInstances[path].editor.setValue(content);
        editorInstances[path].editor.resetUndo();
        editorInstances[path].dirty = false;
        updateTabDirty(path, false);
        activateTab(path);
        return;
    }

    // 首次打开文件时才下载编辑器
    const ZapEditor = await loadZapEditor();

    const uId = 'zap-editor-' + (++editorSeq);

    // 创建编辑器面板
    const editorPane = $(`<div class="editor-pane active" id="${uId}-pane"></div>`).appendTo(fileContentTabs);
    const editorDiv = $(`<div id="${uId}" style="width:100%;height:100%;"></div>`).appendTo(editorPane);

    // 隐藏欢迎页
    welcomeScreen.hide();

    // 创建标签
    const tabEl = $(`<div class="zap-dev-tab active" data-path="${escapeHtml(path)}">
        <span class="tab-name" title="${escapeHtml(path)}">${escapeHtml(filename)}</span>
        <span class="tab-modified" style="display:none;"></span>
        <span class="tab-close"><i class="fa fa-times"></i></span>
    </div>`);

    // 移除其他标签的激活状态
    fileToolbarTabs.find('.zap-dev-tab').removeClass('active');
    fileContentTabs.find('.editor-pane').removeClass('active');

    fileToolbarTabs.append(tabEl);

    // 初始化编辑器（CodeMirror 6，语言包按文件后缀异步加载）
    const editor = ZapEditor.create(editorDiv[0], {
        filename: filename,
        content: content,
        onChange: function() {
            const inst = editorInstances[path];
            if (!inst) {
                return;
            }
            // 与打开时内容比对，撤回后能正确清除脏标记
            const dirty = editor.getValue() !== inst.originalContent;
            if (inst.dirty !== dirty) {
                inst.dirty = dirty;
                updateTabDirty(path, dirty);
            }
        },
    });

    editorInstances[path] = {
        id: uId,
        editor: editor,
        tab: tabEl,
        pane: editorPane,
        filename: filename,
        path: path,
        originalContent: content,
        dirty: false,
        type: 'editor'
    };

    activeFilePath = path;

    // 显示工具栏
    editorToolbar.show();
    updateToolbar(path, filename);

    // 显示编辑器（CodeMirror 需要面板可见后再测量尺寸）
    editorPane.addClass('active');
    editor.resize();
    updateStatusBar('已打开: ' + filename);

    // 高亮左侧文件树
    fileTreeView.find('.list-group-item').removeClass('active');
    fileTreeView.find(`.list-group-item[data-path="${escapeAttr(path)}"]`).addClass('active');
}

function activateTab(path) {
    if (!editorInstances[path]) return;

    // 切换所有标签和面板的激活状态
    fileToolbarTabs.find('.zap-dev-tab').removeClass('active');
    fileContentTabs.find('.editor-pane').removeClass('active');

    editorInstances[path].tab.addClass('active');
    editorInstances[path].pane.addClass('active');
    editorInstances[path].editor.resize();
    activeFilePath = path;

    updateToolbar(path, editorInstances[path].filename);
    updateStatusBar('已打开: ' + editorInstances[path].filename);

    // 高亮左侧文件树
    fileTreeView.find('.list-group-item').removeClass('active');
    fileTreeView.find(`.list-group-item[data-path="${escapeAttr(path)}"]`).addClass('active');
}

function closeTab(path) {
    if (!editorInstances[path]) return;

    const inst = editorInstances[path];

    // 检查是否有未保存的修改
    if (inst.dirty) {
        if (!confirm(`文件 "${inst.filename}" 有未保存的修改，确定关闭吗？`)) {
            return;
        }
    }

    // 销毁编辑器
    inst.editor.destroy();
    inst.tab.remove();
    inst.pane.remove();

    delete editorInstances[path];

    if (activeFilePath === path) {
        // 激活最后一个标签
        const paths = Object.keys(editorInstances);
        if (paths.length > 0) {
            const lastPath = paths[paths.length - 1];
            activateTab(lastPath);
        } else {
            activeFilePath = null;
            editorToolbar.hide();
            welcomeScreen.show();
            updateToolbar(null, null);
            updateStatusBar('就绪');
        }
    }
}

function saveCurrentFile() {
    if (!activeFilePath || !editorInstances[activeFilePath]) {
        return;
    }

    const inst = editorInstances[activeFilePath];
    const content = inst.editor.getValue();

    $('#btnSave').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> 保存中...');

    $.ajax({
        url: '<?php echo url_action('Development@saveFile'); ?>',
        type: 'POST',
        data: {
            path: activeFilePath,
            content: content
        },
        success: function(resp) {
            if (resp.code === 0) {
                inst.originalContent = content;
                inst.dirty = false;
                inst.editor.resetUndo();
                updateTabDirty(activeFilePath, false);
                showToast('文件保存成功', 'success');
                updateStatusBar('已保存: ' + inst.filename + ' (' + formatSize(resp.size) + ')');
            } else {
                showToast(resp.msg || '保存失败', 'error');
            }
        },
        error: function() {
            showToast('保存失败，网络错误', 'error');
        },
        complete: function() {
            $('#btnSave').prop('disabled', false).html('<i class="fa fa-save"></i> 保存');
        }
    });
}

function updateTabDirty(path, dirty) {
    if (!editorInstances[path]) return;
    const dot = editorInstances[path].tab.find('.tab-modified');
    if (dirty) {
        dot.show();
    } else {
        dot.hide();
    }
}

function updateToolbar(path, filename) {
    if (!path) {
        $('#currentFilePath').text('-');
        $('#currentFileLang').text('-');
        return;
    }
    $('#currentFilePath').text(path);
    const ext = filename.split('.').pop().toUpperCase();
    $('#currentFileLang').text(ext);
}

function updateStatusBar(msg) {
    statusInfo.text(msg);
}

function showToast(msg, type) {
    // 使用已有的 toast 系统
    const container = $('#bottomRightToast');
    const icon = type === 'success' ? 'fa-check-circle text-success' : 'fa-exclamation-circle text-danger';
    const toastEl = $(`<div class="toast align-items-center text-bg-white border-0 shadow-sm" role="alert" data-bs-delay="2000">
        <div class="d-flex">
            <div class="toast-body"><i class="fa ${icon} me-2"></i>${msg}</div>
            <button type="button" class="btn-close btn-close-sm me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>`);
    container.append(toastEl);
    const toast = new bootstrap.Toast(toastEl[0]);
    toast.show();
    toastEl.on('hidden.bs.toast', function() {
        toastEl.remove();
    });
}

function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function escapeAttr(str) {
    return str.replace(/"/g, '\\"');
}
</script>
