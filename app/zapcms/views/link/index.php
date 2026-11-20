<?php

use zap\facades\Url;

// 媒体库弹窗（Zap.finder）的拖拽上传依赖 ZAPUploader，需在本页注册
register_scripts(base_url('/assets/plugins/zapuploader.js'));

IS_AJAX !== true && $this->layout('layouts/common');

/**
 * 链接/广告位管理（外观 > 链接管理）
 *
 * @var array $groups       分组列表 [{code,name,type,status,sort}]
 * @var array $types        类型 [key => title]
 * @var array $itemsByGroup 各组条目 [code => [item,...]]
 */
?>
<style>
/* 分组列表项：覆盖 Bootstrap .list-group-item.active 的白字，改用主题前景色，兼容 dark 模式 */
.lk-group-btn { border-left: 3px solid transparent; color: var(--bs-body-color); }
.lk-group-btn.active,
.lk-group-btn.active:hover,
.lk-group-btn.active:focus {
    border-left-color: var(--bs-primary);
    background: rgba(var(--bs-primary-rgb), .10);
    color: var(--bs-body-color);
}
.lk-group-btn .lk-name { color: var(--bs-body-color); }
.lk-group-btn.active .lk-name { color: var(--bs-primary); font-weight: 600; }
.lk-group-icon { color: var(--bs-secondary-color, #6c757d); }
.lk-group-btn.active .lk-group-icon { color: var(--bs-primary); }
.lk-code { font-family: monospace; font-size: 12px; color: var(--bs-secondary-color, #6c757d); }
.lk-item-row input, .lk-item-row select { font-size: .875rem; }
.lk-empty { border: 2px dashed var(--bs-border-color, #dee2e6); border-radius: .5rem; }
/* 图片预览：固定 16:9 缩略框，轮播大图完整缩放显示（不裁切），点击看原图 */
.lk-img-thumb {
    position: relative;
    width: 100%;
    margin-top: 6px;
    aspect-ratio: 16 / 9;
    border: 1px solid var(--bs-border-color, #dee2e6);
    border-radius: .375rem;
    background: #fff;
    overflow: hidden;
    cursor: zoom-in;
}
.lk-img-thumb img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: contain;
    background: #fff;
}
.lk-img-thumb:not(.is-empty):hover { border-color: var(--bs-primary); }
/* 无图 / 加载失败：虚线空态（压低高度，避免空行过于抢眼） */
.lk-img-thumb.is-empty {
    aspect-ratio: auto;
    height: 40px;
    cursor: default;
    border-style: dashed;
    background: var(--bs-tertiary-bg, #f8f9fa);
}
.lk-img-thumb.is-empty img { display: none; }
.lk-img-thumb .lk-img-empty {
    position: absolute;
    inset: 0;
    display: none;
    align-items: center;
    justify-content: center;
    color: var(--bs-secondary-color, #adb5bd);
    font-size: 12px;
}
.lk-img-thumb.is-empty .lk-img-empty { display: flex; }
/* 条目：卡片式行布局，字段按宽度自适应换行，避免表格列挤压错位 */
.lk-items { display: flex; flex-direction: column; gap: .5rem; }
.lk-item-row {
    display: grid;
    grid-template-columns: 150px minmax(0, 1fr) auto;
    gap: .75rem;
    padding: .65rem .75rem;
    border: 1px solid var(--bs-border-color, #dee2e6);
    border-radius: .5rem;
    background: var(--bs-card-bg, #fff);
    transition: border-color .15s ease, box-shadow .15s ease;
}
.lk-item-row:hover {
    border-color: rgba(var(--bs-primary-rgb), .45);
    box-shadow: 0 1px 3px rgba(0, 0, 0, .05);
}
.lk-item-media { min-width: 0; }
.lk-item-fields {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: .5rem .75rem;
    align-content: start;
}
.lk-field { min-width: 0; }
.lk-field-wide { grid-column: 1 / -1; }
.lk-field-label {
    display: block;
    margin-bottom: .15rem;
    font-size: 12px;
    line-height: 1.4;
    color: var(--bs-secondary-color, #6c757d);
}
.lk-item-side {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: .5rem;
    padding-top: .1rem;
}
@media (max-width: 767.98px) {
    .lk-item-row { grid-template-columns: 1fr; }
    .lk-item-side { flex-direction: row; justify-content: flex-end; }
}
@media (max-width: 575.98px) {
    .lk-item-fields { grid-template-columns: 1fr; }
}
</style>

<div class="container-fluid p-3">
    <div class="d-flex align-items-center mb-3">
        <h5 class="mb-0"><i class="fa-solid fa-link me-2"></i>链接管理</h5>
        <div class="ms-auto d-flex gap-2">
            <button type="button" class="btn btn-success btn-sm" onclick="lkSave()">
                <i class="fa-solid fa-floppy-disk me-1"></i>保存全部
            </button>
        </div>
    </div>

    <div class="alert alert-info py-2 small">
        <i class="fa-solid fa-circle-info me-1"></i>
        分组用于前台调用：<b>轮播</b>类型可作首页大图，<b>文字/图标链接</b>类型可作友情链接。前台通过分组 code 调用（如 <code>ad_items('分组code')</code>），可设置生效时间段实现定时上下线。
    </div>

    <div class="row g-3">
        <!-- 左：分组 -->
        <div class="col-lg-3">
            <div class="card">
                <div class="card-header bg-white py-2 d-flex align-items-center">
                    <h6 class="mb-0">分组</h6>
                    <button type="button" class="btn btn-primary btn-sm ms-auto" onclick="lkAddGroup()">
                        <i class="fa fa-plus me-1"></i>新增
                    </button>
                </div>
                <div class="list-group list-group-flush" id="lkGroups"></div>
            </div>
        </div>

        <!-- 右：当前分组的条目 -->
        <div class="col-lg-9">
            <div class="card">
                <div class="card-header bg-white py-2 d-flex align-items-center flex-wrap gap-2">
                    <h6 class="mb-0"><span id="lkCurGroupName">-</span></h6>
                    <span class="lk-code" id="lkCurGroupCode"></span>
                    <div class="ms-auto d-flex gap-2 align-items-center">
                        <label class="form-label small mb-0 text-muted" for="lkGroupType">类型</label>
                        <select id="lkGroupType" class="form-select form-select-sm" style="width:auto">
                            <?php foreach ($types as $k => $title): ?>
                            <option value="<?php echo e($k); ?>"><?php echo e($title); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label class="form-label small mb-0 text-muted">
                            <input type="checkbox" id="lkGroupStatus" checked> 启用
                        </label>
                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="lkRemoveGroup()" title="删除该分组及其条目">
                            <i class="fa fa-trash"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body p-2">
                    <div id="lkItems" class="lk-items mb-2"></div>
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="lkAddItem()">
                        <i class="fa fa-plus me-1"></i>添加一行
                    </button>
                    <div class="form-text">描述可留空；生效时间按 <code>开始~结束</code> 填写，留空表示长期有效。</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 新增分组弹窗（Bootstrap Modal，自动适配 dark 模式） -->
<div class="modal fade" id="lkAddGroupModal" tabindex="-1" aria-labelledby="lkAddGroupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="lkAddGroupModalLabel"><i class="fa-solid fa-folder-plus me-2"></i>新增分组</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="关闭"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="lkGroupNameInput">分组名称 <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="lkGroupNameInput" maxlength="60"
                           placeholder="如：首页轮播 / 友情链接">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="lkGroupCodeInput">调用标识 code</label>
                    <input type="text" class="form-control" id="lkGroupCodeInput" maxlength="40"
                           placeholder="英文小写/数字/下划线；首页轮播请填 home_slide">
                    <div class="form-text">留空自动生成；前台通过 <code>ad_items('code')</code> 调用该分组。</div>
                </div>
                <div>
                    <label class="form-label" for="lkGroupTypeInput">类型</label>
                    <select class="form-select" id="lkGroupTypeInput">
                        <?php foreach ($types as $k => $title): ?>
                        <option value="<?php echo e($k); ?>" <?php echo $k === 'link' ? 'selected' : ''; ?>><?php echo e($title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="invalid-feedback d-block mt-2" id="lkGroupErr" style="display:none"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                <button type="button" class="btn btn-primary" id="lkGroupSubmitBtn">
                    <i class="fa fa-check me-1"></i>确定
                </button>
            </div>
        </div>
    </div>
</div>

<script>
window.__LK_DATA = <?php echo json_encode([
    'groups' => array_values($groups),
    'items'  => (object)$itemsByGroup,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
window.__LK_URLS = {
    save:        '<?php echo Url::action('Link@save'); ?>',
    addGroup:    '<?php echo Url::action('Link@addGroup'); ?>',
    removeGroup: '<?php echo Url::action('Link@removeGroup'); ?>',
};
</script>
<script>
(function () {
    var groups = JSON.parse(JSON.stringify(window.__LK_DATA.groups || []));
    var items  = Object.assign({}, window.__LK_DATA.items || {});
    var urls   = window.__LK_URLS;
    var current = groups.length ? groups[0].code : null;

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
        });
    }
    function uid() { return '__new_' + Date.now() + '_' + Math.floor(Math.random()*1000); }
    function groupBy(code) {
        for (var i=0;i<groups.length;i++) if (groups[i].code === code) return groups[i];
        return null;
    }

    window.lkRenderGroups = function () {
        var html = '';
        if (!groups.length) html = '<div class="p-3 text-muted small">暂无分组，点「新增」创建。</div>';
        groups.forEach(function (g) {
            html += '<button type="button" class="list-group-item list-group-item-action lk-group-btn '
                + (g.code === current ? 'active' : '') + '" data-code="' + esc(g.code) + '" onclick="lkSelect(\'' + esc(g.code) + '\')">'
                + '<i class="fa ' + (g.type === 'slide' ? 'fa-images' : 'fa-link') + ' me-2 lk-group-icon"></i>'
                + '<span class="lk-name">' + esc(g.name) + '</span>'
                + (String(g.status) !== '1' ? ' <span class="badge bg-secondary ms-1">停用</span>' : '')
                + '<div class="lk-code">' + esc(g.code) + ' · ' + esc(g.type) + '</div>'
                + '</button>';
        });
        document.getElementById('lkGroups').innerHTML = html;
    };

    window.lkSelect = function (code) {
        current = code;
        var g = groupBy(code);
        if (!g) return;
        document.getElementById('lkCurGroupName').textContent = g.name;
        document.getElementById('lkCurGroupCode').textContent = g.code;
        document.getElementById('lkGroupType').value = g.type || 'link';
        document.getElementById('lkGroupStatus').checked = String(g.status) !== '0';
        lkRenderGroups();
        lkRenderItems();
    };

    function itemRow(it, idx) {
        it = it || {};
        var checked = String(it.status) !== '0' ? 'checked' : '';
        var target = it.target === '_blank' ? '_blank' : '_self';
        return '<div class="lk-item-row" data-idx="' + idx + '">'
            + '<div class="lk-item-media">'
            + '<div class="lk-img-thumb is-empty">'
            + '<img alt="">'
            + '<span class="lk-img-empty"><i class="fa-solid fa-image me-1"></i>无图</span>'
            + '</div>'
            + '<div class="input-group input-group-sm mt-1">'
            + '<input type="text" id="lk_img_' + idx + '" class="form-control lk-img" value="' + esc(it.img_path) + '" placeholder="/storage/..." title="' + esc(it.img_path) + '">'
            + '<button type="button" class="btn btn-outline-secondary lk-pick" data-target="lk_img_' + idx + '" title="选择图片"><i class="fa fa-folder-open"></i></button>'
            + '</div>'
            + '</div>'
            + '<div class="lk-item-fields">'
            + itemField('标题', '<input type="text" class="form-control form-control-sm lk-title" value="' + esc(it.title) + '">')
            + itemField('链接', '<input type="text" class="form-control form-control-sm lk-link" value="' + esc(it.link) + '" placeholder="https://">')
            + itemField('打开方式', '<select class="form-select form-select-sm lk-target">'
                + '<option value="_self"' + (target === '_self' ? ' selected' : '') + '>当前窗口</option>'
                + '<option value="_blank"' + (target === '_blank' ? ' selected' : '') + '>新窗口</option>'
                + '</select>')
            + itemField('生效时间', '<input type="text" class="form-control form-control-sm lk-time" value="' + esc(it.start_time ? it.start_time + '~' + (it.end_time || '') : '') + '" placeholder="开始~结束，留空长期有效">')
            + itemField('描述', '<input type="text" class="form-control form-control-sm lk-desc" value="' + esc(it.desc) + '" placeholder="可选，留空不显示">', true)
            + '</div>'
            + '<div class="lk-item-side">'
            + '<label class="lk-field-label mb-0" title="启用">启用</label>'
            + '<input type="checkbox" class="form-check-input lk-status m-0" ' + checked + ' title="启用">'
            + '<button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="lkRemoveItem(' + idx + ')" title="删除"><i class="fa fa-trash"></i></button>'
            + '</div>'
            + '</div>';
    }

    function itemField(label, control, wide) {
        return '<div class="lk-field' + (wide ? ' lk-field-wide' : '') + '">'
            + '<label class="lk-field-label">' + label + '</label>'
            + control
            + '</div>';
    }

    window.lkRenderItems = function () {
        var rows = items[current] || [];
        var html = rows.map(function (it, i) { return itemRow(it, i); }).join('');
        if (!html) html = '<div class="lk-empty text-muted text-center py-4">该分组暂无条目，点「添加一行」。</div>';
        document.getElementById('lkItems').innerHTML = html;
        bindPickers();
        syncPreviews();
    };

    // 规范化图片地址：/storage/x、/themes/x、http(s):// 原样使用，纯相对路径补 /
    function previewSrc(v) {
        v = String(v == null ? '' : v).trim();
        if (!v) return '';
        if (/^(https?:)?\/\//i.test(v) || v.charAt(0) === '/') return v;
        return '/' + v.replace(/^\.?\//, '');
    }

    function syncPreview(tr) {
        if (!tr) return;
        var input = tr.querySelector('.lk-img');
        var thumb = tr.querySelector('.lk-img-thumb');
        if (!input || !thumb) return;
        var img = thumb.querySelector('img');
        input.title = input.value.trim();

        var src = previewSrc(input.value);
        if (!src) {
            thumb.classList.add('is-empty');
            thumb.removeAttribute('title');
            img.removeAttribute('src');
            return;
        }
        thumb.classList.remove('is-empty');
        thumb.setAttribute('title', '点击查看原图');
        // 地址无效（404 / 非图片）时按无图处理，避免显示破图
        img.onerror = function () {
            thumb.classList.add('is-empty');
            thumb.removeAttribute('title');
            this.removeAttribute('src');
        };
        if (img.getAttribute('src') !== src) img.src = src;
    }

    function syncPreviews() {
        document.querySelectorAll('#lkItems .lk-item-row').forEach(syncPreview);
    }

    // 输入框内容变化（含 finder 写值后触发的 change）即时刷新预览
    var lkItemsBody = document.getElementById('lkItems');
    lkItemsBody.addEventListener('input', function (e) {
        if (e.target && e.target.classList && e.target.classList.contains('lk-img')) {
            syncPreview(e.target.closest('.lk-item-row'));
        }
    });
    lkItemsBody.addEventListener('change', function (e) {
        if (e.target && e.target.classList && e.target.classList.contains('lk-img')) {
            syncPreview(e.target.closest('.lk-item-row'));
        }
    });
    // 点击缩略图查看原图
    lkItemsBody.addEventListener('click', function (e) {
        var thumb = e.target && e.target.closest ? e.target.closest('.lk-img-thumb') : null;
        if (!thumb || thumb.classList.contains('is-empty')) return;
        var img = thumb.querySelector('img');
        if (img && img.getAttribute('src')) window.open(img.src, '_blank');
    });

    function bindPickers() {
        document.querySelectorAll('#lkItems .lk-pick').forEach(function (btn) {
            btn.onclick = function () {
                var target = btn.getAttribute('data-target');
                if (window.Zap && typeof window.Zap.finder === 'function') {
                    // 销毁可能残留的旧弹窗实例（避免复用时停留在 thumbs 等禁止上传的目录）
                    if (window.zapFinder && typeof window.zapFinder.dispose === 'function') {
                        try { window.zapFinder.dispose(); } catch (e) {}
                        window.zapFinder = null;
                    }
                    window.Zap.finder({target: '#' + target, size: 'original', path: '', title: '选择图片'});
                } else {
                    var input = document.getElementById(target);
                    var url = prompt('请输入图片路径', input.value);
                    if (url !== null) {
                        input.value = url;
                        syncPreview(input.closest('.lk-item-row'));
                    }
                }
            };
        });
    }

    window.lkAddItem = function () {
        if (!current) { alert('请先新增一个分组'); return; }
        if (!items[current]) items[current] = [];
        items[current].push({});
        lkRenderItems();
    };
    window.lkRemoveItem = function (idx) {
        (items[current] || []).splice(idx, 1);
        lkRenderItems();
    };

    // 采集右侧编辑区到内存（每次切换分组/保存前调用）
    function collectCurrent() {
        if (!current) return;
        var rows = [];
        document.querySelectorAll('#lkItems .lk-item-row').forEach(function (el) {
            var time = (el.querySelector('.lk-time').value || '').trim();
            var parts = time.split('~');
            rows.push({
                img_path: el.querySelector('.lk-img').value.trim(),
                title:    el.querySelector('.lk-title').value.trim(),
                link:     el.querySelector('.lk-link').value.trim(),
                target:   el.querySelector('.lk-target').value,
                start_time: (parts[0] || '').trim(),
                end_time:   (parts[1] || '').trim(),
                status:   el.querySelector('.lk-status').checked ? '1' : '0',
                desc:     (el.querySelector('.lk-desc').value || '').trim()
            });
        });
        items[current] = rows;
    }

    // ── 新增分组：Bootstrap Modal ──
    // 后台布局把 bootstrap.bundle 放在页面底部、晚于本脚本执行，
    // 因此不能在初始化时立即 new bootstrap.Modal，改为点击时惰性获取（那会儿已加载）。
    function getAddGroupModal() {
        if (!window.bootstrap || !bootstrap.Modal) return null;
        if (!window.__lkAddGroupModal) {
            var el = document.getElementById('lkAddGroupModal');
            if (!el) return null;
            window.__lkAddGroupModal = new bootstrap.Modal(el);
        }
        return window.__lkAddGroupModal;
    }

    window.lkAddGroup = function () {
        var addGroupModal = getAddGroupModal();
        // 兜底：极端情况下 bootstrap 未加载时，退回原生输入，避免点击无反应
        if (!addGroupModal) {
            var n = prompt('分组名称（如：首页轮播 / 友情链接）');
            if (!n || !n.trim()) return;
            var c = prompt('调用标识 code（留空自动生成，首页轮播填 home_slide）', '');
            if (c === null) return;
            collectCurrent();
            jQuery.post(urls.addGroup, {name: n.trim(), type: 'link', code: c.trim()}, function (res) {
                if (res.code !== 0) { alert(res.msg || '添加失败'); return; }
                groups.push(res.data);
                items[res.data.code] = [];
                current = res.data.code;
                lkRenderGroups();
                _select(current);
            }, 'json');
            return;
        }
        document.getElementById('lkGroupNameInput').value = '';
        document.getElementById('lkGroupCodeInput').value = '';
        document.getElementById('lkGroupTypeInput').value = 'link';
        var err = document.getElementById('lkGroupErr');
        err.style.display = 'none';
        err.textContent = '';
        addGroupModal.show();
        setTimeout(function () { document.getElementById('lkGroupNameInput').focus(); }, 200);
    };

    function lkSubmitAddGroup() {
        var nameInput = document.getElementById('lkGroupNameInput');
        var codeInput = document.getElementById('lkGroupCodeInput');
        var typeInput = document.getElementById('lkGroupTypeInput');
        var err = document.getElementById('lkGroupErr');
        var name = nameInput.value.trim();
        var code = codeInput.value.trim();
        var type = typeInput.value;

        if (!name) {
            err.textContent = '请输入分组名称';
            err.style.display = 'block';
            nameInput.focus();
            return;
        }
        if (code && !/^[a-z0-9_]{1,40}$/.test(code)) {
            err.textContent = 'code 只能包含小写字母、数字、下划线';
            err.style.display = 'block';
            codeInput.focus();
            return;
        }
        err.style.display = 'none';

        var btn = document.getElementById('lkGroupSubmitBtn');
        btn.disabled = true;
        collectCurrent();
        jQuery.post(urls.addGroup, {name: name, type: type, code: code}, function (res) {
            btn.disabled = false;
            if (res.code !== 0) {
                err.textContent = res.msg || '添加失败';
                err.style.display = 'block';
                return;
            }
            var m = getAddGroupModal();
            if (m) m.hide();
            groups.push(res.data);
            items[res.data.code] = [];
            current = res.data.code;
            lkRenderGroups();
            _select(current);
        }, 'json').fail(function () {
            btn.disabled = false;
            err.textContent = '网络错误，请重试';
            err.style.display = 'block';
        });
    }

    document.getElementById('lkGroupSubmitBtn').addEventListener('click', lkSubmitAddGroup);
    // 回车快速提交
    ['lkGroupNameInput', 'lkGroupCodeInput'].forEach(function (id) {
        document.getElementById(id).addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); lkSubmitAddGroup(); }
        });
    });

    window.lkRemoveGroup = function () {
        if (!current) return;
        if (!confirm('确认删除该分组及其下全部条目？')) return;
        jQuery.post(urls.removeGroup, {code: current}, function (res) {
            var removed = current;
            groups = groups.filter(function (g){ return g.code !== removed; });
            delete items[removed];
            current = groups.length ? groups[0].code : null;
            lkRenderGroups();
            if (current) _select(current); else { document.getElementById('lkCurGroupName').textContent='-'; document.getElementById('lkCurGroupCode').textContent=''; document.getElementById('lkItems').innerHTML=''; }
        }, 'json');
    };

    window.lkSave = function () {
        collectCurrent();
        // 同步当前分组的元信息
        var g = groupBy(current);
        if (g) {
            g.type = document.getElementById('lkGroupType').value;
            g.status = document.getElementById('lkGroupStatus').checked ? '1' : '0';
        }
        var payload = { groups: groups, items: items };
        jQuery.post(urls.save, payload, function (res) {
            if (res.code === 0) {
                if (window.ZapToast) ZapToast.success('保存成功');
                else alert('保存成功');
                location.reload();
            } else {
                alert(res.msg || '保存失败');
            }
        }, 'json');
    };

    // 切换分组前先采集当前编辑内容
    var _select = window.lkSelect;
    window.lkSelect = function (code) { if (code !== current) collectCurrent(); _select(code); };

    lkRenderGroups();
    if (current) lkSelect(current);
})();
</script>
