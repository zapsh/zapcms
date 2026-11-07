<?php

use zap\facades\Url;

IS_AJAX !== true && $this->layout('layouts/common');

/**
 * 小工具管理（外观 > 小工具）
 *
 * @var array $areas      可用区域 [key => title]
 * @var array $registered 已注册小工具 [key => {title,description,icon,defaults,fields}]
 */
?>
<style>
.widget-area-btn { border-left: 3px solid transparent; }
.widget-area-btn.active { border-left-color: #0d6efd; }
.wg-card { border: 1px solid #e9ecef; border-radius: .375rem; }
.wg-card:hover { border-color: #dee2e6; }
.wg-icon { width: 2rem; height: 2rem; display: inline-flex; align-items: center; justify-content: center; background: #f1f5ff; color: #0d6efd; border-radius: .375rem; }
.wg-empty { border: 2px dashed #dee2e6; border-radius: .5rem; }
</style>

<div class="container-fluid p-3">
    <div class="row g-3">
        <!-- 左：区域 -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-white py-2">
                    <h6 class="mb-0">小工具区域</h6>
                    <div class="small text-muted">主题 theme.json 声明，可在此为各区域放置小工具。</div>
                </div>
                <div class="list-group list-group-flush" id="wgAreas">
                    <?php $first = array_key_first($areas); ?>
                    <?php foreach ($areas as $key => $title): ?>
                        <button type="button" class="list-group-item list-group-item-action widget-area-btn <?php echo $key === $first ? 'active' : ''; ?>"
                                data-area="<?php echo e($key); ?>" data-title="<?php echo e($title); ?>">
                            <i class="fa fa-th-large me-2 text-muted"></i><?php echo e($title); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- 右：该区域的小工具 -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-white py-2 d-flex align-items-center">
                    <h6 class="mb-0"><i class="fa fa-puzzle-piece me-1 text-muted"></i><span id="wgAreaTitle">-</span></h6>
                    <button type="button" class="btn btn-primary btn-sm ms-auto" onclick="widgetOpenAdd()">
                        <i class="fa fa-plus me-1"></i>添加小工具
                    </button>
                </div>
                <div class="card-body p-2" id="wgList">
                    <div class="text-muted p-3">选择左侧区域查看小工具</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 新增 / 编辑弹窗 -->
<div class="modal fade" id="wgModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="wgForm" onsubmit="return false;">
                <div class="modal-header py-2">
                    <h6 class="modal-title" id="wgModalTitle">添加小工具</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="wgId" value="0">
                    <input type="hidden" id="wgArea" value="">
                    <div class="mb-2">
                        <label class="form-label mb-1">小工具类型</label>
                        <select id="wgKey" class="form-select form-select-sm" onchange="widgetTypeChange()"></select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-1">标题（可选）</label>
                        <input type="text" id="wgTitle" class="form-control form-control-sm" placeholder="留空则不显示标题">
                    </div>
                    <div id="wgFields"></div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">取消</button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="widgetSave()">保存</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
window.__WIDGET_REGISTERED = <?php echo json_encode($registered, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
window.__WIDGET_URLS = {
    list: '<?php echo Url::action('Widget@areaList'); ?>',
    save: '<?php echo Url::action('Widget@save'); ?>',
    reorder: '<?php echo Url::action('Widget@reorder'); ?>',
    remove: '<?php echo Url::action('Widget@remove'); ?>',
};
</script>
<script>
(function () {
    var currentArea = null;
    var currentRows = [];
    var currentId = 0;

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
        });
    }

    function widgetSetConfig(config) {
        config = config || {};
        var key = $('#wgKey').val();
        var def = window.__WIDGET_REGISTERED[key] || {};
        window.__WIDGET_CONFIG = Object.assign({}, def.defaults || {}, config);
    }

    window.widgetTypeChange = function () {
        widgetSetConfig(null);
        widgetRenderFields();
    };

    window.widgetRenderFields = function () {
        var key = $('#wgKey').val();
        var def = window.__WIDGET_REGISTERED[key] || {};
        var cfg = window.__WIDGET_CONFIG || {};
        var html = '';
        (def.fields || []).forEach(function (f) {
            var v = cfg[f.name] !== undefined && cfg[f.name] !== null ? cfg[f.name] : '';
            html += '<div class="mb-2"><label class="form-label mb-1">' + esc(f.label) + '</label>';
            if (f.type === 'textarea') {
                html += '<textarea class="form-control form-control-sm wg-field" data-name="' + esc(f.name) + '" rows="3">' + esc(v) + '</textarea>';
            } else if (f.type === 'select') {
                html += '<select class="form-select form-select-sm wg-field" data-name="' + esc(f.name) + '">';
                var opts = f.options || {};
                Object.keys(opts).forEach(function (ov) {
                    html += '<option value="' + esc(ov) + '"' + (String(v) === String(ov) ? ' selected' : '') + '>' + esc(opts[ov]) + '</option>';
                });
                html += '</select>';
            } else if (f.type === 'checkbox') {
                html += '<div class="form-check"><input type="checkbox" class="form-check-input wg-field" data-name="' + esc(f.name) + '"' + (v ? ' checked' : '') + '></div>';
            } else {
                var type = f.type === 'number' ? 'number' : 'text';
                html += '<input type="' + type + '" class="form-control form-control-sm wg-field" data-name="' + esc(f.name) + '" value="' + esc(v) + '"' + (f.placeholder ? ' placeholder="' + esc(f.placeholder) + '"' : '') + '>';
            }
            html += '</div>';
        });
        $('#wgFields').html(html);
    };

    window.widgetOpenAdd = function () {
        if (!currentArea) { Swal.fire({icon: 'info', title: '请先选择左侧区域'}); return; }
        currentId = 0;
        $('#wgModalTitle').text('添加小工具');
        $('#wgId').val(0);
        $('#wgArea').val(currentArea);
        $('#wgTitle').val('');
        var sel = $('#wgKey').prop('disabled', false).empty();
        Object.keys(window.__WIDGET_REGISTERED).forEach(function (key) {
            sel.append('<option value="' + esc(key) + '">' + esc(window.__WIDGET_REGISTERED[key].title) + '</option>');
        });
        widgetSetConfig(null);
        widgetRenderFields();
        $('#wgModal').modal('show');
    };

    window.widgetOpenEdit = function (id) {
        var row = null;
        currentRows.forEach(function (r) { if (String(r.id) === String(id)) { row = r; } });
        if (!row) { return; }
        currentId = row.id;
        $('#wgModalTitle').text('编辑小工具');
        $('#wgId').val(row.id);
        $('#wgArea').val(currentArea);
        $('#wgTitle').val(row.title || '');
        var d = window.__WIDGET_REGISTERED[row.widget_key] || {};
        var sel = $('#wgKey').prop('disabled', true).empty();
        sel.append('<option value="' + esc(row.widget_key) + '">' + esc(d.title || row.widget_key) + '</option>');
        widgetSetConfig(row.config);
        widgetRenderFields();
        $('#wgModal').modal('show');
    };

    window.widgetSave = function () {
        var key = $('#wgKey').val();
        var config = {};
        $('#wgFields .wg-field').each(function () {
            var name = $(this).data('name');
            var type = $(this).attr('type');
            if (type === 'checkbox') { config[name] = $(this).is(':checked') ? 1 : 0; }
            else { config[name] = $(this).val(); }
        });
        $.post(window.__WIDGET_URLS.save, {
            id: $('#wgId').val(),
            widget_key: key,
            area: $('#wgArea').val(),
            title: $('#wgTitle').val(),
            config: config
        }, function (res) {
            if (res.code !== 0) { Swal.fire({icon: 'warning', title: res.msg || '保存失败'}); return; }
            $('#wgModal').modal('hide');
            widgetLoadArea(currentArea);
        }, 'json');
    };

    window.widgetRemove = function (id) {
        Swal.fire({icon: 'warning', title: '删除该小工具？', showCancelButton: true, confirmButtonText: '删除', cancelButtonText: '取消'}).then(function (r) {
            if (!r.isConfirmed) { return; }
            $.post(window.__WIDGET_URLS.remove, {id: id}, function (res) {
                if (res.code !== 0) { Swal.fire({icon: 'warning', title: res.msg || '删除失败'}); return; }
                widgetLoadArea(currentArea);
            }, 'json');
        });
    };

    window.widgetMove = function (id, dir) {
        var idx = -1;
        currentRows.forEach(function (r, i) { if (String(r.id) === String(id)) { idx = i; } });
        var target = idx + dir;
        if (idx < 0 || target < 0 || target >= currentRows.length) { return; }
        var tmp = currentRows[idx];
        currentRows[idx] = currentRows[target];
        currentRows[target] = tmp;
        widgetRenderList();
        $.post(window.__WIDGET_URLS.reorder, {area: currentArea, ids: currentRows.map(function (r) { return r.id; })}, function () {}, 'json');
    };

    function widgetRenderList() {
        var box = $('#wgList');
        if (!currentRows.length) {
            box.html('<div class="wg-empty text-center text-muted py-5"><i class="fa fa-puzzle-piece fa-2x d-block mb-2 opacity-50"></i>该区域还没有小工具，点右上角「添加小工具」。</div>');
            return;
        }
        var html = '';
        currentRows.forEach(function (r) {
            html += '<div class="wg-card p-2 mb-2 d-flex align-items-center gap-2" data-id="' + r.id + '">';
            html += '<span class="wg-icon"><i class="' + esc(r.widget_icon) + '"></i></span>';
            html += '<div class="flex-grow-1 text-truncate" style="min-width:0">';
            html += '<div class="fw-semibold">' + esc(r.title || r.widget_title) + '</div>';
            html += '<div class="small text-muted">' + esc(r.widget_title) + '</div>';
            html += '</div>';
            html += '<div class="btn-group btn-group-sm">';
            html += '<button class="btn btn-outline-secondary" title="上移" onclick="widgetMove(' + r.id + ',-1)"><i class="fa fa-arrow-up"></i></button>';
            html += '<button class="btn btn-outline-secondary" title="下移" onclick="widgetMove(' + r.id + ',1)"><i class="fa fa-arrow-down"></i></button>';
            html += '</div>';
            html += '<button class="btn btn-sm btn-outline-primary" onclick="widgetOpenEdit(' + r.id + ')"><i class="fa fa-pen"></i></button>';
            html += '<button class="btn btn-sm btn-outline-danger" onclick="widgetRemove(' + r.id + ')"><i class="fa fa-trash"></i></button>';
            html += '</div>';
        });
        box.html(html);
    }

    function widgetLoadArea(area) {
        currentArea = area;
        $.getJSON(window.__WIDGET_URLS.list, {area: area}, function (res) {
            if (res.code !== 0) { return; }
            currentRows = res.data || [];
            widgetRenderList();
        });
    }

    $('#wgAreas').on('click', '.widget-area-btn', function () {
        $('#wgAreas .widget-area-btn').removeClass('active');
        $(this).addClass('active');
        $('#wgAreaTitle').text($(this).data('title'));
        widgetLoadArea($(this).data('area'));
    });

    $(function () {
        var first = $('#wgAreas .widget-area-btn').first();
        if (first.length) {
            first.addClass('active');
            $('#wgAreaTitle').text(first.data('title'));
            widgetLoadArea(first.data('area'));
        }
    });
})();
</script>
