<?php use zap\facades\Url; ?>
<?php $this->layout('layouts/common'); ?>
<?php
$isEdit = !empty($row);
$typeName = $row['type_name'] ?? '';
$title   = $row['title'] ?? '';
$desc    = $row['description'] ?? '';
$class   = $row['node_type'] ?? '';
$version = $row['version'] ?? '0.0.0';
$sort    = $row['sort_order'] ?? 0;
$status  = $row['status'] ?? 1;
$fields  = $fields ?? [];
$fieldTypes = [
    'text'     => ['label' => '单行文本', 'icon' => 'fa fa-font'],
    'textarea' => ['label' => '多行文本', 'icon' => 'fa fa-align-left'],
    'number'   => ['label' => '数字',     'icon' => 'fa fa-hashtag'],
    'date'     => ['label' => '日期',     'icon' => 'fa fa-calendar'],
    'datetime' => ['label' => '日期时间', 'icon' => 'fa fa-clock'],
    'select'   => ['label' => '下拉选择', 'icon' => 'fa fa-caret-down'],
    'radio'    => ['label' => '单选',     'icon' => 'fa fa-circle-dot'],
    'checkbox' => ['label' => '复选',     'icon' => 'fa fa-check-square'],
    'switch'   => ['label' => '开关',     'icon' => 'fa fa-toggle-on'],
    'image'    => ['label' => '图片',     'icon' => 'fa fa-image'],
];
$optionsTypes = ['select','radio','checkbox']; // 这些类型使用「选项列表」textarea
$groups       = $groups ?? [];
$fieldGroups  = array_column($groups, 'name');
?>

<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card">
            <div class="card-header bg-white d-flex align-items-center justify-content-between">
                <span class="fw-semibold"><i class="fa fa-cog"></i> <?=$isEdit ? '编辑模型' : '添加模型'?></span>
                <a href="<?= Url::action('Node/types')?>" class="btn btn-outline-secondary btn-sm">
                    <i class="fa fa-arrow-left"></i> 返回列表
                </a>
            </div>
            <div class="card-body">
                <form id="typeForm" method="post" action="<?= Url::action('Node/typesSave')?>">
                    <input type="hidden" name="type_id" value="<?=(int)($id ?? 0)?>">

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">类型标识 <span class="text-danger">*</span></label>
                            <input type="text" name="type_name" value="<?=e($typeName)?>"
                                   class="form-control" placeholder="如: article, product"
                                   required maxlength="50" pattern="[a-z][a-z0-9_]*"
                                   <?=$isEdit ? 'readonly' : ''?>>
                            <div class="form-text">仅允许小写字母、数字、下划线，以字母开头。</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">显示名称 <span class="text-danger">*</span></label>
                            <input type="text" name="title" value="<?=e($title)?>"
                                   class="form-control" placeholder="如: 文章、商品"
                                   required maxlength="100">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">描述</label>
                        <textarea name="description" rows="2" class="form-control"
                                  placeholder="简要描述该内容模型的用途"><?=e($desc)?></textarea>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">排序</label>
                            <input type="number" name="sort_order" value="<?=$sort?>"
                                   class="form-control" min="0" step="1">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">状态</label>
                            <select name="status" class="form-select">
                                <option value="1" <?=$status == 1 ? 'selected' : ''?>>启用</option>
                                <option value="0" <?=$status == 0 ? 'selected' : ''?>>禁用</option>
                            </select>
                        </div>
                    </div>

                    <!-- 高级配置：处理类（继承 AbstractNodeType 的自定义控制器），日常不需要动 -->
                    <div class="mb-3">
                        <details>
                            <summary class="text-muted small" style="cursor:pointer;">
                                <i class="fa fa-gear"></i> 高级配置
                            </summary>
                            <div class="border rounded p-3 mt-2 bg-body-tertiary">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label">处理类（可选）</label>
                                        <input type="text" name="node_type" value="<?=e($class)?>"
                                               class="form-control font-monospace"
                                               placeholder="zapcms\node\controllers\XxxController"
                                               maxlength="200">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">版本</label>
                                        <input type="text" name="version" value="<?=e($version)?>"
                                               class="form-control" placeholder="0.0.0" maxlength="20">
                                    </div>
                                </div>
                                <div class="form-text">
                                    仅在需要自定义内容行为（视图处理不了的逻辑）时填写：
                                    继承 <code>AbstractNodeType</code> 的类，放在
                                    <code>app/zapcms/node/controllers/</code>，命名 <code>驼峰Controller</code>。
                                    类名用单反斜杠（多余的 <code>\</code> 会自动归一化）；
                                    留空 = 默认处理。保存时会校验类存在且继承基类。
                                    <br>提示：未配置时系统也会自动优先使用
                                    <code>node/controllers/</code> 下与类型标识同名的控制器。
                                </div>
                            </div>
                        </details>
                    </div>

                    <hr id="fields">

                    <!-- 字段分组管理 -->
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="mb-0"><i class="fa fa-object-group"></i> 字段分组</h6>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="addGroupRow()">
                            <i class="fa fa-plus-lg"></i> 添加分组
                        </button>
                    </div>
                    <p class="text-muted small">
                        分组用于将自定义字段在节点编辑页拆分为多个 <code>Tab</code>，Tab 标题即为分组名称。
                        未分组的字段显示在默认 Tab 中。
                    </p>
                    <div id="groupList" class="mb-2">
                        <?php if (!empty($groups)): ?>
                            <?php foreach ($groups as $g): ?>
                                <div class="input-group input-group-sm mb-2">
                                    <input type="text" name="field_group[]" class="form-control"
                                           placeholder="分组名称，如 规格参数" value="<?=e($g['name'])?>">
                                    <button type="button" class="btn btn-outline-danger" onclick="removeGroupRow(this)">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <hr>

                    <!-- 自定义字段管理 -->
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="mb-0"><i class="fa fa-list"></i> 自定义字段</h6>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="expandAllRows(true)">
                                <i class="fa fa-arrows-up-down"></i> 全部展开
                            </button>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="addFieldRow()">
                                <i class="fa fa-plus-lg"></i> 添加字段
                            </button>
                        </div>
                    </div>
                    <p class="text-muted small">
                        配置后将在节点编辑表单中动态显示，字段值存入 <code>node_meta</code>。
                        类型为 <code>select / radio / checkbox</code> 时使用「选项列表」；其它类型使用「默认值」。
                    </p>

                    <div id="fieldList">
                        <?php if (!empty($fields)): ?>
                            <?php foreach ($fields as $i => $f): ?>
                                <?php include __DIR__ . '/_field_row.php'; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div id="fieldEmpty" class="text-center text-muted border rounded p-4 mb-2">
                                尚未配置自定义字段，点击右上角“添加字段”。
                            </div>
                        <?php endif; ?>
                    </div>

                    <hr>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-save"></i> 保存
                        </button>
                        <a href="<?= Url::action('Node/types')?>" class="btn btn-outline-secondary">取消</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- 字段行模板（用于 JS 动态追加） -->
<template id="fieldRowTpl">
    <div class="field-row border rounded mb-2 bg-white shadow-sm">
        <div class="field-row-head d-flex align-items-center px-3 py-2 bg-light-subtle border-bottom">
            <span class="fw-semibold me-2 field-row-title">字段</span>
            <span class="badge bg-secondary-subtle text-secondary field-row-type">text</span>
            <span class="badge bg-warning-subtle text-warning ms-2 d-none field-row-required">必填</span>
            <div class="ms-auto d-flex gap-1">
                <button type="button" class="btn btn-sm btn-outline-secondary field-toggle" title="展开/收起">
                    <i class="fa fa-chevron-down"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger field-remove" title="删除">
                    <i class="fa fa-trash"></i>
                </button>
            </div>
        </div>
        <div class="field-row-body p-3">
            <div class="row g-2 align-items-end mb-2">
                <div class="col-md-3">
                    <label class="form-label small mb-1">字段标识 <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm field-name" data-name="field_name" placeholder="如 price" pattern="[a-z][a-z0-9_]*" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">显示名称</label>
                    <input type="text" class="form-control form-control-sm" data-name="field_label" placeholder="如 产品价格">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">控件类型</label>
                    <select class="form-select form-select-sm field-type" data-name="type">
                        <?php foreach ($fieldTypes as $fk => $fv): ?>
                            <option value="<?=$fk?>"><?=$fv['label']?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label small mb-1">排序</label>
                    <input type="number" class="form-control form-control-sm" data-name="sort_order" value="0" min="0">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">是否必填</label>
                    <select class="form-select form-select-sm" data-name="required">
                        <option value="0">否</option>
                        <option value="1">是</option>
                    </select>
                </div>
            </div>
            <div class="row g-2 mb-2">
                <div class="col-md-6">
                    <label class="form-label small mb-1">占位提示</label>
                    <input type="text" class="form-control form-control-sm" data-name="placeholder" placeholder="如 199.00 / 请输入...">
                </div>
                <div class="col-md-6">
                    <label class="form-label small mb-1">所属分组</label>
                    <select class="form-select form-select-sm" data-name="group_name">
                        <option value="">（无分组，显示在默认 Tab）</option>
                    </select>
                    <div class="form-text">同一分组的字段在节点编辑页放入一个 Tab。</div>
                </div>
            </div>
            <div class="row g-2 mb-2 field-value-row">
                <div class="col-md-12">
                    <label class="form-label small mb-1 field-value-label">默认值</label>
                    <textarea rows="3" class="form-control form-control-sm" data-name="field_value" placeholder=""></textarea>
                    <div class="form-text field-value-help">保存后将作为该字段在节点表单中的默认值。</div>
                </div>
            </div>
            <div class="row g-2">
                <div class="col-md-12">
                    <label class="form-label small mb-1">帮助文字</label>
                    <input type="text" class="form-control form-control-sm" data-name="help" placeholder="选填，节点编辑页字段下方显示的提示">
                </div>
            </div>
        </div>
    </div>
</template>

<script>
(function () {
    var fieldTypes = <?= json_encode($fieldTypes, JSON_UNESCAPED_UNICODE) ?>;
    var optionsTypes = <?= json_encode($optionsTypes) ?>;
    var fieldGroups = <?= json_encode($fieldGroups, JSON_UNESCAPED_UNICODE) ?>;
    var fieldIndex = <?= count($fields) ?>;

    function fieldName(i, key) { return 'field[' + i + '][' + key + ']'; }

    function applyFieldRowData(rowEl, i, data) {
        rowEl.querySelectorAll('[data-name]').forEach(function (el) {
            var key = el.getAttribute('data-name');
            var val = data && data[key] !== undefined ? data[key] : '';
            if (el.tagName === 'TEXTAREA') {
                el.textContent = val;
            } else {
                el.value = val;
            }
            // 分组下拉的选项可能尚未渲染，暂存值待 syncGroupOptions 恢复
            if (key === 'group_name' && val && el.tagName === 'SELECT' && el.value !== val) {
                el.dataset.pendingGroup = val;
            }
            // 重新打上 name 标记
            el.setAttribute('name', fieldName(i, key));
        });
        updateFieldRowHint(rowEl);
    }

    function updateFieldRowHint(rowEl) {
        var type = rowEl.querySelector('.field-type').value;
        var label = (fieldTypes[type] && fieldTypes[type].label) || type;
        var titleEl = rowEl.querySelector('.field-row-title');
        var typeEl  = rowEl.querySelector('.field-row-type');
        var reqEl   = rowEl.querySelector('.field-row-required');
        var nameEl  = rowEl.querySelector('.field-name');
        var showName = (titleEl && nameEl && nameEl.value) ? nameEl.value : '新字段';
        if (titleEl) titleEl.textContent = showName;
        if (typeEl)  typeEl.textContent  = label;
        var required = rowEl.querySelector('[data-name="required"]').value === '1';
        if (reqEl) reqEl.classList.toggle('d-none', !required);

        // 根据类型切换「默认值/选项」textarea 提示
        var isOptions = optionsTypes.indexOf(type) !== -1;
        var valueLabel = rowEl.querySelector('.field-value-label');
        var valueHelp  = rowEl.querySelector('.field-value-help');
        if (valueLabel) valueLabel.textContent = isOptions ? '选项列表' : '默认值';
        if (valueHelp) valueHelp.textContent = isOptions
            ? '每行一个，格式 "值:标签"（如 1:有货）。2 列以上可在节点表单多选。'
            : '保存后将作为该字段在节点表单中的默认值。';
    }

    function addFieldRow(data) {
        var list = document.getElementById('fieldList');
        var empty = document.getElementById('fieldEmpty');
        if (empty) { empty.remove(); }
        var i = fieldIndex++;
        var tpl = document.getElementById('fieldRowTpl');
        var row = tpl.content.firstElementChild.cloneNode(true);
        list.appendChild(row);
        applyFieldRowData(row, i, data || {});
        syncGroupOptions();
    }

    // ---- 字段分组管理 ----
    function collectGroups() {
        fieldGroups = [];
        document.querySelectorAll('#groupList input[name="field_group[]"]').forEach(function (inp) {
            var v = inp.value.trim();
            if (v && fieldGroups.indexOf(v) === -1) { fieldGroups.push(v); }
        });
        syncGroupOptions();
    }

    function syncGroupOptions() {
        var opts = '<option value="">（无分组，显示在默认 Tab）</option>';
        fieldGroups.forEach(function (g) {
            opts += '<option value="' + g + '">' + g + '</option>';
        });
        document.querySelectorAll('#fieldList [data-name="group_name"]').forEach(function (sel) {
            var old = sel.dataset.pendingGroup !== undefined ? sel.dataset.pendingGroup : sel.value;
            sel.innerHTML = opts;
            if (old && fieldGroups.indexOf(old) !== -1) { sel.value = old; }
            delete sel.dataset.pendingGroup;
        });
    }

    function addGroupRow(name) {
        var list = document.getElementById('groupList');
        var div = document.createElement('div');
        div.className = 'input-group input-group-sm mb-2';
        div.innerHTML =
            '<input type="text" name="field_group[]" class="form-control" placeholder="分组名称，如 规格参数">' +
            '<button type="button" class="btn btn-outline-danger" onclick="removeGroupRow(this)"><i class="fa fa-trash"></i></button>';
        list.appendChild(div);
        var input = div.querySelector('input');
        if (name) { input.value = name; }
        input.focus();
        collectGroups();
    }

    function removeGroupRow(btn) {
        var row = btn.closest('.input-group');
        if (row) { row.remove(); }
        collectGroups();
    }

    function removeFieldRow(btn) {
        var row = btn.closest('.field-row');
        if (!row) { return; }
        row.remove();
        if (!document.querySelectorAll('.field-row').length && !document.getElementById('fieldEmpty')) {
            document.getElementById('fieldList').innerHTML =
                '<div id="fieldEmpty" class="text-center text-muted border rounded p-4 mb-2">尚未配置自定义字段，点击右上角“添加字段”。</div>';
        }
    }

    function toggleFieldRow(btn) {
        var row = btn.closest('.field-row');
        if (!row) { return; }
        var body = row.querySelector('.field-row-body');
        if (!body) { return; }
        var icon = btn.querySelector('i');
        var collapsed = body.style.display === 'none';
        body.style.display = collapsed ? '' : 'none';
        if (icon) { icon.className = collapsed ? 'fa fa-chevron-down' : 'fa fa-chevron-up'; }
    }

    window.expandAllRows = function (expand) {
        document.querySelectorAll('.field-row').forEach(function (row) {
            var body = row.querySelector('.field-row-body');
            var btn = row.querySelector('.field-toggle');
            if (body) { body.style.display = expand ? '' : 'none'; }
            if (btn) {
                var icon = btn.querySelector('i');
                if (icon) { icon.className = expand ? 'fa fa-chevron-down' : 'fa fa-chevron-up'; }
            }
        });
    };

    // 分组输入变化时同步字段行下拉
    document.getElementById('groupList').addEventListener('input', function (e) {
        if (e.target && e.target.name === 'field_group[]') { collectGroups(); }
    });

    // 事件代理
    document.getElementById('fieldList').addEventListener('click', function (e) {
        var t = e.target.closest('button');
        if (!t) { return; }
        if (t.classList.contains('field-remove')) { removeFieldRow(t); }
        else if (t.classList.contains('field-toggle')) { toggleFieldRow(t); }
    });
    document.getElementById('fieldList').addEventListener('input', function (e) {
        var el = e.target;
        if (el.classList.contains('field-name')) {
            var row = el.closest('.field-row');
            if (row) { updateFieldRowHint(row); }
        }
    });
    document.getElementById('fieldList').addEventListener('change', function (e) {
        var el = e.target;
        if (el.classList.contains('field-type') || el.getAttribute('data-name') === 'required') {
            var row = el.closest('.field-row');
            if (row) { updateFieldRowHint(row); }
        }
    });

    // 暴露给 HTML 上的 onclick 使用
    window.addFieldRow = addFieldRow;
    window.removeFieldRow = removeFieldRow;
    window.toggleFieldRow = toggleFieldRow;
    window.addGroupRow = addGroupRow;
    window.removeGroupRow = removeGroupRow;

    // 初始化现有行的 name 与提示
    (function init() {
        // 服务端渲染的行没有 data-name 上的 name，先重新打一遍
        var rows = document.querySelectorAll('#fieldList .field-row');
        rows.forEach(function (row, i) {
            row.querySelectorAll('[data-name]').forEach(function (el) {
                el.setAttribute('name', fieldName(i, el.getAttribute('data-name')));
            });
            updateFieldRowHint(row);
        });
        syncGroupOptions();
    })();

    // 提交
    document.getElementById('typeForm').addEventListener('submit', function (e) {
        e.preventDefault();

        var names = this.querySelectorAll('.field-name');
        var seen = {};
        for (var i = 0; i < names.length; i++) {
            var v = names[i].value.trim();
            if (!/^[a-z][a-z0-9_]*$/.test(v)) {
                Swal.fire({ icon: 'warning', title: '字段标识格式不正确', text: '仅允许小写字母、数字、下划线，且以字母开头。' });
                names[i].focus();
                return;
            }
            if (seen[v]) {
                Swal.fire({ icon: 'warning', title: '字段标识重复', text: '存在相同的字段标识：' + v });
                names[i].focus();
                return;
            }
            seen[v] = true;
        }

        // 合并所有 [data-name] 的实际 name 到 FormData（防止模板克隆时未及时打 name）
        var formEl = this;
        var fd = new FormData(formEl);
        // 重新打 name 一遍以保证最新
        document.querySelectorAll('#fieldList .field-row').forEach(function (row, idx) {
            row.querySelectorAll('[data-name]').forEach(function (el) {
                el.setAttribute('name', fieldName(idx, el.getAttribute('data-name')));
            });
        });
        var formData = new FormData(formEl);

        var btn = this.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> 保存中…';

        fetch(this.action, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.code === 0) {
                Swal.fire({ icon: 'success', title: '保存成功', showConfirmButton: false, timer: 1200 })
                  .then(function () { location.href = '<?= Url::action('Node/types')?>'; });
            } else {
                Swal.fire({ icon: 'error', title: '保存失败', text: res.msg || '未知错误' });
                btn.disabled = false;
                btn.innerHTML = '<i class="fa fa-save"></i> 保存';
            }
        })
        .catch(function () {
            Swal.fire({ icon: 'error', title: '网络错误', text: '请重试' });
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-save"></i> 保存';
        });
    });
})();
</script>