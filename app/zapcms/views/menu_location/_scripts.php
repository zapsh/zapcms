<?php
/**
 * 菜单管理共享 JS（供「外观 > 菜单」整页与「栏目管理」页抽屉复用）
 *
 * 数据流统一以 menu_id 为准：构建器片段（_builder.php）加载后设置 window.__mlMenuId，
 * 保存类函数均以 window.__mlMenuId 提交。
 *
 * 依赖宿主页提供 jQuery / Bootstrap / ZapModal / ZapToast / Swal 全局对象。
 */
use zap\facades\Url;
?>
<script>
    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    var mlBuilderUrl  = '<?php echo Url::action("MenuLocation@builder"); ?>';
    var mlSaveItemUrl = '<?php echo Url::action("MenuLocation@saveItem"); ?>';
    var mlmCatalogChildrenUrl = '<?php echo Url::action("Catalog@children"); ?>';

    // ───────────────────── 栏目目录树勾选  ─────────────────────
    function mlmCatalogNodeHtml(n, depth) {
        var toggle = n.has_children
            ? '<a href="javascript:;" class="text-muted me-1 mlm-tree-toggle flex-shrink-0" data-id="' + n.id + '" style="text-decoration:none;width:14px;text-align:center;">' +
              '<i class="fa fa-chevron-right"></i></a>'
            : '<span class="d-inline-block me-1 flex-shrink-0" style="width:14px"></span>';
        return '<div class="list-group-item mlm-cat-node d-flex align-items-center" ' +
               'data-cat-id="' + n.id + '" data-depth="' + depth + '" ' +
               'style="padding-left:' + (8 + depth * 16) + 'px">' +
               toggle +
               '<label class="d-flex align-items-center flex-grow-1 mb-0 text-truncate" style="cursor:pointer;min-width:0">' +
               '<input class="form-check-input me-2 mlm-chk flex-shrink-0" type="checkbox" data-type="catalog" data-id="' + n.id + '">' +
               '<span class="text-truncate">' + escapeHtml(n.title) + '</span>' +
               '</label></div>';
    }

    function mlmLoadCatalogTree() {
        $('#mlmTreeBack').addClass('d-none');
        var box = $('#mlmRes_catalog');
        if (!box.length) { return; }
        box.html('<div class="text-muted p-2">正在加载栏目目录…</div>');
        $.getJSON(mlmCatalogChildrenUrl, {parent_id: 0}, function (res) {
            if (res.code !== 0) { box.html('<div class="text-danger p-2">加载失败</div>'); return; }
            var nodes = res.nodes || [];
            if (!nodes.length) {
                box.html('<div class="text-muted p-2">暂无栏目，请先在「内容管理 › 栏目」中创建。</div>');
                return;
            }
            var h = '<div class="list-group list-group-flush">';
            nodes.forEach(function (n) { h += mlmCatalogNodeHtml(n, 0); });
            h += '</div>';
            box.html(h);
        });
    }

    // 懒加载展开子栏目
    $(document).on('click', '.mlm-tree-toggle', function (e) {
        e.preventDefault();
        var $a = $(this);
        var id = parseInt($a.data('id'), 10);
        var $node = $a.closest('.mlm-cat-node');
        var depth = parseInt($node.data('depth'), 10) || 0;
        var $children = $node.next('.mlm-tree-children[data-parent="' + id + '"]');
        if (!$children.length) {
            $children = $('<div class="mlm-tree-children" data-parent="' + id + '"></div>');
            $node.after($children);
        }
        var ic = $a.find('i');
        if ($children.hasClass('mlm-loaded')) {
            $children.toggleClass('d-none');
            ic.toggleClass('fa-chevron-right fa-chevron-down');
            return;
        }
        $children.html('<div class="text-muted small py-1">加载中…</div>')
            .addClass('mlm-loaded').removeClass('d-none');
        ic.removeClass('fa-chevron-right').addClass('fa-chevron-down');
        $.getJSON(mlmCatalogChildrenUrl, {parent_id: id}, function (res) {
            var nodes = res.nodes || [];
            var h = '';
            nodes.forEach(function (n) { h += mlmCatalogNodeHtml(n, depth + 1); });
            $children.html(h || '<div class="text-muted small py-1">无子栏目</div>');
        });
    });

    // 全选（仅作用于栏目目录面板内可见的勾选框）
    function mlmToggleAll(el) {
        $('#mlmRes_catalog .mlm-chk').prop('checked', $(el).prop('checked'));
    }

    // 构建器加载完成后初始化目录树与单页列表
    $(function () {
        if ($('#mlmRes_catalog').length) { mlmLoadCatalogTree(); }
        if ($('#mlmRes_page').length) { mlmSearch('page'); }
    });

    // ───────────────────── 位置 CRUD（抽屉 / 管理位置 tab 共用） ─────────────────────
    function mlAddOrEdit(id){
        id = id || 0;
        var url = '<?php echo Url::action("MenuLocation@form"); ?>' + (id ? '?id=' + id : '');
        var m = ZapModal.create({
            id:'menuLocationModal',
            title: id ? '编辑位置' : '添加位置',
            url: url,
            size:'modal-md',
            buttons:[{close:true,title:'取消'},{title:'保存',class:'btn-success'}],
            btn2:function(){ mlSave(m); }
        }, true);
        m.show();
    }

    function mlSave(m){
        var $btn = $('#menuLocationModal .btn-success');
        if(!$.trim($('#menuLocationForm [name="title"]').val())){
            Swal.fire({icon:'warning',title:'请填写位置名称'});
            return;
        }
        $btn.prop('disabled', true).text('保存中...');
        $.ajax({
            url:'<?php echo Url::action("MenuLocation@save"); ?>',
            method:'post',
            data:$('#menuLocationForm').serialize(),
            success:function (data){
                $btn.prop('disabled', false).text('保存');
                if(data.code !== 0){
                    Swal.fire({icon:'error',title:'保存失败',text:data.msg});
                    return;
                }
                ZapToast.alert(data.msg,{bgColor:bgSuccess,position:Toast_Pos_Center,delay:1200,callback:function(){
                    mlReloadLocations(); m.hide();
                }});
            },
            error:function(xhr){
                $btn.prop('disabled', false).text('保存');
                Swal.fire({icon:'error',title:'保存失败',text:xhr.statusText});
            }
        });
    }

    function mlRemove(id){
        Swal.fire({
            title:'确定删除该位置？',
            text:'菜单本身不会被删除，仅解除该位置的分配。',
            icon:'warning',
            showCancelButton:true,
            confirmButtonText:'删除',
            cancelButtonText:'取消',
            confirmButtonColor:'#d33'
        }).then(function (result){
            if(!result.isConfirmed) return;
            $.ajax({
                url:'<?php echo Url::action("MenuLocation@remove"); ?>',
                method:'post',
                data:{id:id},
                success:function (data){
                    ZapToast.alert(data.msg,{bgColor:data.code===0?bgSuccess:bgDanger,position:Toast_Pos_Center});
                    if(data.code===0){ mlReloadLocations(); }
                }
            });
        });
    }

    function mlReloadLocations(){
        $.get('<?php echo Url::action("MenuLocation@listFragment"); ?>', function (html){
            $('#menuLocationDrawerBody').html(html);
        });
        // 管理位置 tab（整页）同时刷新
        if ($('#mlLocationsTable').length) { window.location.reload(); }
    }

    // ───────────────────── 菜单构建器 ─────────────────────
    // 抽屉内打开（栏目管理页）
    function mlOpenDrawer(keyOrId){
        var el = document.getElementById('menuLocationDrawer');
        if (el && window.bootstrap) {
            bootstrap.Offcanvas.getOrCreateInstance(el).show();
        }
        mlOpenBuilder(keyOrId || 'top');
    }

    function mlBuilderParams(keyOrId){
        return (typeof keyOrId === 'number' || /^\d+$/.test(String(keyOrId)))
            ? {menu_id: keyOrId}
            : {key: keyOrId};
    }

    function mlOpenBuilder(keyOrId){
        $.get(mlBuilderUrl, mlBuilderParams(keyOrId), function (html){
            $('#menuLocationDrawerBody').html(html);
        });
    }

    // 整页（外观 > 菜单）加载编辑器（注意：$.load 传对象会发 POST，这里必须用 GET）
    function mlLoadEditor(menuId){
        window.__mlMenuId = parseInt(menuId, 10) || 0;
        $.get(mlBuilderUrl, {menu_id: window.__mlMenuId}, function (html){
            $('#menuEditor').html(html);
        });
    }

    function mlBackToList(){
        // 抽屉：返回位置列表；整页：回到「管理菜单」列表
        if ($('#menuLocationDrawerBody').length && $('#menuLocationDrawer').hasClass('show')) {
            mlReloadLocations();
        } else {
            window.location.href = '<?php echo Url::action("MenuLocation"); ?>';
        }
    }

    function mlSaveTitle(id, val){
        $.post(mlSaveItemUrl, {id:id, menu_id:window.__mlMenuId, title:val}, function (data){
            if(data.code !== 0){ Swal.fire({icon:'error',title:'保存失败',text:data.msg}); }
        }, 'json');
    }

    // 展开/折叠菜单项编辑面板（WordPress 式 ▼ 切换）
    $(document).on('click', '.mi-toggle', function (e){
        e.preventDefault();
        var $panel = $(this).closest('.mi-item').find('.mi-meta-panel');
        $panel.toggleClass('d-none');
        $(this).find('i').toggleClass('fa-chevron-down fa-chevron-up');
    });

    // 菜单项 mega 字段异步保存（只提交单个字段）
    function mlSaveMeta(id, field, val){
        var d = {id:id, menu_id:window.__mlMenuId};
        d[field] = val;
        $.post(mlSaveItemUrl, d, function (res){
            if(res.code === 0){ ZapToast.alert('已保存',{bgColor:bgSuccess,position:Toast_Pos_Center}); }
            else { Swal.fire({icon:'error',title:'保存失败',text:res.msg}); }
        }, 'json');
    }

    function mlRemoveItem(id){
        Swal.fire({
            title:'确定删除该菜单项？',
            text:'其下级菜单项将一并删除。',
            icon:'warning', showCancelButton:true,
            confirmButtonText:'删除', cancelButtonText:'取消', confirmButtonColor:'#d33'
        }).then(function (result){
            if(!result.isConfirmed) return;
            $.post('<?php echo Url::action("MenuLocation@removeItem"); ?>', {id:id}, function (data){
                ZapToast.alert(data.msg,{bgColor:data.code===0?bgSuccess:bgDanger,position:Toast_Pos_Center});
                if(data.code === 0){ mlReloadBuilder(); }
            }, 'json');
        });
    }

    // 重新加载当前构建器（抽屉 / 整页自适应）
    function mlReloadBuilder(){
        if ($('#menuLocationDrawerBody').length && $('#menuLocationDrawer').hasClass('show')) {
            mlOpenBuilder(window.__mlMenuId);
        } else if (window.__mlMenuId) {
            mlLoadEditor(window.__mlMenuId);
        }
    }

    // ───────────────────── 菜单项拖拽排序（指针拖拽，彻底避免原生 DnD 卡死） ─────────────────────
    function miClearDropMarks(){
        $('.mi-item').removeClass('mi-drop-before mi-drop-after mi-drop-child');
    }

    // 根据各项 data-parent 重算缩进（拖拽后即时反馈）
    function miRefreshIndent(){
        $('#miList .mi-item').each(function (){
            var depth = 0, pid = parseInt($(this).attr('data-parent'), 10) || 0;
            var guard = 0;
            while (pid > 0 && guard++ < 20){
                var $p = $('#miList .mi-item[data-id="' + pid + '"]');
                if (!$p.length) { break; }
                depth++;
                pid = parseInt($p.attr('data-parent'), 10) || 0;
            }
            $(this).css('margin-left', (depth * 18) + 'px');
        });
    }

    // 目标的最后一个子树项（DOM 顺序即树序：后续项缩进大于目标即为其后代）
    function miSubtreeLast($target){
        var tMargin = parseInt($target.css('margin-left'), 10) || 0;
        var $last = $target, $n = $target.next();
        while ($n.length && $n.hasClass('mi-item') && !$n.hasClass('mi-dragging-src')) {
            if ((parseInt($n.css('margin-left'), 10) || 0) <= tMargin) { break; }
            $last = $n; $n = $n.next();
        }
        return $last;
    }

    // 目标是否在被拖项的子树内（防成环）
    function miInSubtree(dragId, targetEl){
        var $t = $(targetEl);
        var guard = 0;
        while ($t.length && guard++ < 100){
            if (parseInt($t.data('id'), 10) === parseInt(dragId, 10)) { return true; }
            var pid = parseInt($t.attr('data-parent'), 10) || 0;
            if (!pid) { return false; }
            $t = $('#miList .mi-item[data-id="' + pid + '"]');
        }
        return false;
    }

    // 条目当前层级（缩进 18px = 一级）
    function miLevel($el){
        return Math.round((parseInt($el.css('margin-left'), 10) || 0) / 18);
    }

    // 从 $target 向后跳过所有比 level 更深的行，返回插入锚点：
    // 「排到某项后面」必须落在其整棵子树之后，否则会把目标的子项挤到新条目名下
    function miSkipDeeper($target, level){
        var $pos = $target, $n = $target.next('.mi-item');
        while ($n.length) {
            if ($n.hasClass('mi-dragging-src')) { $n = $n.next('.mi-item'); continue; } // 被拖原件不参与
            if (miLevel($n) <= level) { break; }
            $pos = $n; $n = $n.next('.mi-item');
        }
        return $pos;
    }

    // 落点上方最近的浅一级条目 = 新父菜单（WordPress 式：父菜单由缩进层级推导，层级 0 = 顶级）
    function miParentAtLevel($from, level, dragId){
        if (level <= 0) { return 0; }
        var $p = $from.prev('.mi-item');
        while ($p.length) {
            if (!$p.hasClass('mi-dragging-src')) {
                if (miLevel($p) < level && !miInSubtree(dragId, $p[0])) {
                    return parseInt($p.data('id'), 10);
                }
            }
            $p = $p.prev('.mi-item');
        }
        return 0;
    }

    // 被拖条目连同其子树的所有行（DOM 顺序）——整块搬家，保证父子不被拆散
    function miSubtreeRows($src){
        var sLevel = miLevel($src), $rows = $src, $n = $src.next('.mi-item');
        while ($n.length) {
            if (miLevel($n) <= sLevel) { break; }
            $rows = $rows.add($n);
            $n = $n.next('.mi-item');
        }
        return $rows;
    }

    var miSrc = null, miGhost = null, miPlaceholder = null, miDropTarget = null, miDropMode = 'after', miDropLevel = 0,
        miDropAnchor = null, miOrigParent = 0, miMoved = false, miStarted = false, miStartX = 0, miStartY = 0,
        miZoneKey = null;

    // 整行（mi-bar）可起拽；点按钮/链接/输入框不触发；禁用文本选择与原生拖拽
    $(document).on('mousedown', '#miList .mi-bar', function (e){
        if (e.which !== 1) { return; } // 仅左键
        if ($(e.target).closest('.mi-toggle, button, a, input, select, textarea, label').length) { return; }
        e.preventDefault();
        var $item = $(this).closest('.mi-item');
        if (!$item.length) { return; }
        miSrc = $item;
        miOrigParent = parseInt($item.attr('data-parent'), 10) || 0;
        miStartX = e.clientX;
        miStartY = e.clientY;
        miMoved = false;
        miStarted = false; // 按下不等于拖拽，移动超阈值才起拽
        $(document).on('mousemove.mi', onMiMove);
        $(document).on('mouseup.mi', onMiUp);
    });

    // 真正开始拖拽：隐藏原件、插入占位、创建跟随幽灵元素
    function miBeginDrag(e){
        if (miStarted || !miSrc || !miSrc.length) { return; }
        miStarted = true;
        var rect = miSrc[0].getBoundingClientRect();

        miPlaceholder = $('<li class="list-group-item mi-placeholder py-2" ' +
            'style="height:' + Math.round(rect.height) + 'px;margin-left:' + (parseInt(miSrc.css('margin-left'), 10) || 0) + 'px;' +
            'border:2px dashed #0d6efd;background:var(--bs-tertiary-bg);"></li>');
        miSrc.after(miPlaceholder);
        miSrc.addClass('mi-dragging-src'); // 隐藏原件（display:none）

        miGhost = miSrc.clone().removeClass('mi-dragging-src').addClass('mi-ghost')
            .css({
                position: 'fixed', zIndex: 9999, width: rect.width + 'px',
                left: (e.clientX + 8) + 'px', top: (e.clientY + 8) + 'px',
                pointerEvents: 'none', opacity: 0.92,
                boxShadow: '0 6px 18px rgba(0,0,0,.25)', listStyle: 'none'
            });
        $('body').append(miGhost);
        miZoneKey = null; // 首次 onMiMove 必然会应用一次落区
        miDropAnchor = null;
    }

    function onMiMove(e){
        // 移动超过 4px 才算开始拖拽：避免"点一下"原条目就跳动
        if (!miStarted) {
            if (Math.abs(e.clientX - miStartX) < 4 && Math.abs(e.clientY - miStartY) < 4) { return; }
            miBeginDrag(e);
        }
        miMoved = true;
        if (miGhost) { miGhost.css({left: (e.clientX + 8) + 'px', top: (e.clientY + 8) + 'px'}); }

        var $items = $('#miList .mi-item').not('.mi-dragging-src');
        if (!$items.length || !miPlaceholder) { return; }

        // 用「虚拟坐标」做落区判定：占位框会把其下方条目顶下去，若直接读真实坐标，
        // 等于用"被占位框自己改变的布局"做判定 → 判定变 → 占位框移动 → 布局又变 → 来回震荡（闪烁的根源）。
        // 把占位框高度从其下方条目的坐标里扣掉后，坐标与占位框位置无关，判定稳定不抖。
        var phRect = miPlaceholder[0].getBoundingClientRect();
        var best = null, bestDist = Infinity, bestTop = 0, bestH = 1, bestLeft = 0;
        $items.each(function (){
            var r = this.getBoundingClientRect();
            var top = r.top, bottom = r.bottom;
            if (top >= phRect.top) { top -= phRect.height; bottom -= phRect.height; }
            var d = Math.abs(e.clientY - (top + (bottom - top) / 2));
            if (d < bestDist) { bestDist = d; best = this; bestTop = top; bestH = bottom - top; bestLeft = r.left; }
        });
        if (!best) { return; }

        var $target = $(best);
        var tLevel = miLevel($target);
        var rel = (e.clientY - bestTop) / bestH;
        var dx  = e.clientX - bestLeft;
        var mode, level;
        if (rel < 0.35) {
            // 目标上半部 → 排到目标前面（与目标同级）
            mode = 'before'; level = tLevel;
        } else if (rel > 0.65 && dx < 32) {
            // 目标下半部靠左 → 排到目标（整棵子树）之后；层级由横向位置决定（WordPress 同款）：
            // 每向左 18px 浅一级，0 = 顶级 → 拖到列表末尾向左一抹即可变回一级菜单，占位框无需瞬移
            mode = 'after';
            level = Math.max(0, Math.min(tLevel, Math.floor((dx + tLevel * 18) / 18)));
        } else {
            // 目标中部 或 下方靠右 → 设为目标的子菜单
            mode = 'child'; level = tLevel + 1;
        }

        // 插入锚点：before → 目标之前；after → 目标子树之后（跳过比落点层级更深的行）；
        // child → 目标子树之后（深一级）。锚点决定占位框位置、层级只决定缩进
        // → 横向移动只改缩进不动位置，从机制上消除占位框跳动
        var $anchor;
        if (mode === 'before') {
            $anchor = $target;
        } else if (mode === 'after') {
            $anchor = miSkipDeeper($target, level);
        } else {
            $anchor = miSubtreeLast($target);
        }

        // 落区（模式+目标+层级）没变就不碰 DOM：占位框只在落区真正变化时更新一次
        var zoneKey = mode + ':' + $target.data('id') + ':' + level;
        if (zoneKey === miZoneKey) { return; }
        miZoneKey = zoneKey;

        miClearDropMarks();
        miDropTarget = $target;
        miDropMode = mode;
        miDropLevel = level;
        miDropAnchor = $anchor;

        $target.addClass(mode === 'before' ? 'mi-drop-before' : (mode === 'after' ? 'mi-drop-after' : 'mi-drop-child'));
        if (mode === 'before') { miPlaceholder.insertBefore($anchor); }
        else { miPlaceholder.insertAfter($anchor); }
        miPlaceholder.css('margin-left', (level * 18) + 'px');
    }

    function onMiUp(){
        $(document).off('mousemove.mi mouseup.mi');
        if (miGhost) { miGhost.remove(); miGhost = null; }
        miClearDropMarks();
        if (!miSrc) { cleanupMi(); return; }
        // 仅点击手柄、从未起拽 → 原条目没动过，直接复位状态即可
        if (!miStarted) {
            miSrc.removeClass('mi-dragging-src');
            if (miPlaceholder) { miPlaceholder.remove(); }
            cleanupMi();
            return;
        }

        var $src = miSrc;
        var dragId = parseInt($src.data('id'), 10);
        var newPid = 0;

        // 锚点落在被拖条目自己的子树里 → 整块搬过去仍是原顺序（原地不动），静默忽略
        var selfDrop = miDropAnchor && miDropAnchor.length
            && miDropAnchor[0] !== $src[0] && miInSubtree(dragId, miDropAnchor[0]);
        if (selfDrop) {
            newPid = miOrigParent;
        } else if (miDropMode === 'child' && miDropTarget && miDropTarget.length) {
            newPid = parseInt(miDropTarget.data('id'), 10);
        } else if (miDropMode === 'before' && miDropTarget && miDropTarget.length) {
            newPid = parseInt(miDropTarget.attr('data-parent'), 10) || 0;
        } else if (miDropMode === 'after' && miPlaceholder && miPlaceholder.length) {
            // after：父菜单由占位框层级向上推导（缩进浅一级的最近前项；层级 0 = 顶级）
            newPid = miParentAtLevel(miPlaceholder, miDropLevel, dragId);
        }

        $src.attr('data-parent', newPid);
        // 整棵子树作为一块搬到占位框位置（父菜单挪动时子菜单跟随，父子永不拆散）
        var $block = miSubtreeRows($src);
        miPlaceholder.after($block);
        $src.removeClass('mi-dragging-src');
        if (miPlaceholder) { miPlaceholder.remove(); }
        miRefreshIndent();
        cleanupMi();
        if (!selfDrop) { mlSaveOrder(); }
    }

    function cleanupMi(){
        miSrc = null; miPlaceholder = null; miDropTarget = null; miDropMode = 'after'; miDropLevel = 0;
        miDropAnchor = null; miOrigParent = 0; miMoved = false; miStarted = false; miZoneKey = null;
        $('.mi-item').removeClass('mi-dragging-src');
    }

    function mlSaveOrder(){
        var items = [];
        $('#miList .mi-item').each(function (idx){
            items.push({
                id:         $(this).data('id'),
                parent_id:  parseInt($(this).attr('data-parent'), 10) || 0,
                sort_order: idx
            });
        });
        $.post('<?php echo Url::action("MenuLocation@reorderItems"); ?>', {items:items}, function (data){
            if(data.code === 0){ mlReloadBuilder(); }
            else { Swal.fire({icon:'error',title:'保存失败',text:data.msg}); }
        }, 'json').fail(function (xhr){
            Swal.fire({icon:'error',title:'保存失败',text:xhr.statusText || '请求失败'});
        });
    }

    // WordPress 式：左栏搜索并添加菜单项（kind: catalog/node/page；sub: 内容模型类型）
    function mlmSearch(kind, sub){
        sub = sub || '';
        var suffix = sub ? '_' + sub : '';
        var kw = $('#mlmKw_' + kind + suffix).val().trim();
        var box = $('#mlmRes_' + kind + suffix);
        box.html('<div class="text-muted p-2">搜索中…</div>');
        var params = {keyword:kw, limit:30};
        // sub：catalog 面板按内容模型过滤栏目；内容/单页面板按 node_type 过滤节点（如 page）
        if (sub) { params.node_type = sub; }
        // 单页面板固定只看 node_type=page（栏目 + 节点），初始加载也生效
        else if (kind === 'page') { params.node_type = 'page'; }
        $.getJSON('<?php echo Url::action("Catalog@searchLinkTarget"); ?>', params, function (resp){
            if(resp.code !== 0){ box.html('<div class="text-danger p-2">搜索失败</div>'); return; }
            var lists;
            if (kind === 'page') {
                // 单页 = node_type=page 的栏目（常见）+ node_type=page 的节点（若有）
                lists = (resp.catalogs||[]).concat(resp.nodes||[]);
            } else {
                lists = kind === 'node' ? (resp.nodes||[]) : (resp.catalogs||[]);
            }
            if(lists.length === 0){ box.html('<div class="text-muted p-2">没有匹配</div>'); return; }
            var h = '<div class="list-group list-group-flush">';
            lists.forEach(function (item){
                var t = item.kind || kind;                          // catalog → 菜单项 item_type=catalog
                h += '<label class="list-group-item d-flex align-items-center" style="cursor:pointer">' +
                     '<input class="form-check-input me-2 mlm-chk" type="checkbox" data-type="' + t + '" data-id="' + item.id + '">' +
                     '<span class="text-truncate">' + escapeHtml(item.path_label || item.title) +
                     ' <span class="text-muted">#' + item.id + '</span></span></label>';
            });
            h += '</div>';
            box.html(h);
            // 栏目搜索后提供返回目录树的入口
            if (kind === 'catalog') { $('#mlmTreeBack').removeClass('d-none'); }
        });
    }

    function mlmAddSelected(){
        var sel = $('.mlm-chk:checked');
        if(!sel.length){ Swal.fire({icon:'info',title:'请先勾选要添加的项'}); return; }
        var arr = [];
        sel.each(function (){ arr.push({ item_type:$(this).data('type'), ref_id:$(this).data('id') }); });
        mlmAddMany(arr);
    }

    function mlmAddCustom(){
        var url = $('#mlmCustomUrl').val().trim();
        if(!url){ Swal.fire({icon:'warning',title:'请填写链接地址'}); return; }
        mlmAddMany([{
            item_type:   'custom_link',
            url:         url,
            title:       $('#mlmCustomLabel').val().trim() || null,
            link_target: $('#mlmLinkTarget').val() || '_self'
        }]);
    }

    function mlmAddMany(arr){
        var pending = arr.length, done = 0, failed = false;
        arr.forEach(function (a){
            var data = { menu_id:window.__mlMenuId, item_type:a.item_type, sort_order: $('#miList .mi-item').length + 1 };
            if(a.item_type === 'custom_link'){ data.url = a.url; }
            else { data.ref_id = a.ref_id; }
            if(a.title !== undefined){ data.title = a.title || null; }
            if(a.link_target){ data.link_target = a.link_target; }
            $.post(mlSaveItemUrl, data, function (res){
                if(res.code !== 0){ failed = true; return; }
                done++;
                if(done >= pending){
                    if(!failed){ mlReloadBuilder(); }
                    else { Swal.fire({icon:'warning',title:'部分添加失败'}); }
                }
            }, 'json');
        });
    }

    // ───────────────────── 菜单实体管理（外观 > 菜单 整页） ─────────────────────
    function mlSwitchTab(tab){
        $('#tabMenuList').toggleClass('d-none', tab !== 'list');
        $('#tabEditMenu').toggleClass('d-none', tab !== 'edit');
        $('#tabLocations').toggleClass('d-none', tab !== 'locations');
        $('#mlTabList').toggleClass('active', tab === 'list');
        $('#mlTabEdit').toggleClass('active', tab === 'edit');
        $('#mlTabLoc').toggleClass('active', tab === 'locations');
        // 编辑菜单平铺在卡片外，其余 tab 显示卡片，避免嵌套边框
        $('#mlCardWrap').toggleClass('d-none', tab === 'edit');
    }

    // 从「管理菜单」列表进入编辑
    function mlEditMenu(id){
        id = parseInt(id, 10) || 0;
        if (id <= 0) { return; }
        mlSwitchTab('edit');
        mlLoadEditor(id);
    }

    function mlCreateMenu(){
        Swal.fire({
            title:'创建新菜单',
            input:'text',
            inputPlaceholder:'菜单名称（如：主导航 / 页脚链接 / 侧边栏菜单）',
            showCancelButton:true,
            confirmButtonText:'创建',
            cancelButtonText:'取消'
        }).then(function (r){
            if(!r.isConfirmed) return;
            var title = (r.value || '').trim();
            if(!title){ return; }
            $.post('<?php echo Url::action("MenuLocation@saveMenu"); ?>', {title:title}, function (res){
                if(res.code === 0){ window.location.href = '<?php echo Url::action("MenuLocation"); ?>' + '?menu_id=' + res.menu_id + '&tab=edit'; }
                else { Swal.fire({icon:'error',title:'创建失败',text:res.msg}); }
            }, 'json');
        });
    }

    function mlSaveMenuTitle(id){
        var title = $.trim($('#mlMenuTitle').val() || '');
        if(!title){ Swal.fire({icon:'warning',title:'请填写菜单名称'}); return; }
        $.post('<?php echo Url::action("MenuLocation@saveMenu"); ?>', {id:id, title:title}, function (res){
            if(res.code === 0){ ZapToast.alert('菜单已保存',{bgColor:bgSuccess,position:Toast_Pos_Center}); }
            else { Swal.fire({icon:'error',title:'保存失败',text:res.msg}); }
        }, 'json');
    }

    function mlDeleteMenu(id){
        Swal.fire({
            title:'确定删除该菜单？',
            text:'其下所有菜单项将一并删除，引用它的位置恢复为未分配。',
            icon:'warning', showCancelButton:true,
            confirmButtonText:'删除', cancelButtonText:'取消', confirmButtonColor:'#d33'
        }).then(function (r){
            if(!r.isConfirmed) return;
            $.post('<?php echo Url::action("MenuLocation@deleteMenu"); ?>', {id:id}, function (res){
                if(res.code === 0){ window.location.href = '<?php echo Url::action("MenuLocation"); ?>'; }
                else { Swal.fire({icon:'error',title:'删除失败',text:res.msg}); }
            }, 'json');
        });
    }

    function mlAssign(locationId, selectEl){
        var menuId = parseInt($(selectEl).val(), 10) || 0;
        $.post('<?php echo Url::action("MenuLocation@assign"); ?>', {location_id:locationId, menu_id:menuId}, function (res){
            ZapToast.alert(res.msg,{bgColor:res.code===0?bgSuccess:bgDanger,position:Toast_Pos_Center});
        }, 'json');
    }
</script>
