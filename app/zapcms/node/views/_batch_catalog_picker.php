<?php
/** 批量移动目标栏目搜索选择器（避免一次性渲染整棵栏目树） */

use zap\facades\Url;

$pickerType = strtolower((string) ($node_type ?? ''));
if ($pickerType === 'default') {
    $pickerType = trim((string) ($_GET['type'] ?? ''));
}
?>
<div class="position-relative" style="max-width:280px;">
    <div class="input-group input-group-sm">
        <input type="hidden" id="nodeBatchCatalog" value="0">
        <input type="text" id="nodeBatchCatalogName" class="form-control" autocomplete="off"
               placeholder="搜索移动目标栏目">
        <button type="button" class="btn btn-outline-secondary" id="nodeBatchCatalogClear" title="清除">
            <i class="fa fa-times"></i>
        </button>
    </div>
    <div id="nodeBatchCatalogOptions" class="list-group position-absolute w-100 shadow-sm d-none"
         style="z-index:1050;max-height:240px;overflow:auto;"></div>
</div>
<script>
(function () {
    var input  = document.getElementById('nodeBatchCatalogName');
    var hidden = document.getElementById('nodeBatchCatalog');
    var box    = document.getElementById('nodeBatchCatalogOptions');
    var clear  = document.getElementById('nodeBatchCatalogClear');
    var timer  = null;
    var url    = '<?php echo e(Url::action("Catalog@options")); ?>';
    var type   = '<?php echo e($pickerType); ?>';

    function hide() { if (box) { box.classList.add('d-none'); } }
    function render(rows) {
        if (!box) { return; }
        box.innerHTML = '';
        if (!rows.length) {
            box.innerHTML = '<div class="list-group-item small text-muted">未找到栏目</div>';
            box.classList.remove('d-none');
            return;
        }
        rows.forEach(function (r) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'list-group-item list-group-item-action small';
            btn.textContent = '— '.repeat(Math.max(0, parseInt(r.level || 1, 10) - 1)) + (r.title || '');
            btn.addEventListener('click', function () {
                hidden.value = r.id;
                input.value = r.title;
                hide();
            });
            box.appendChild(btn);
        });
        box.classList.remove('d-none');
    }

    if (input) {
        input.addEventListener('input', function () {
            clearTimeout(timer);
            hidden.value = '0';
            var kw = input.value.trim();
            if (kw === '') { hide(); return; }
            timer = setTimeout(function () {
                fetch(url + '?keyword=' + encodeURIComponent(kw) + '&limit=30' + (type ? '&node_type=' + encodeURIComponent(type) : ''), {credentials: 'same-origin'})
                    .then(function (r) { return r.json(); })
                    .then(function (res) { render(res && res.options ? res.options : []); })
                    .catch(function () { hide(); });
            }, 250);
        });
        input.addEventListener('blur', function () { setTimeout(hide, 180); });
    }
    if (clear) {
        clear.addEventListener('click', function () {
            hidden.value = '0';
            input.value = '';
            hide();
        });
    }
})();
</script>
