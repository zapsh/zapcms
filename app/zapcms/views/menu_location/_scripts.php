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

    // ───────────────────── 栏目目录树勾选（WordPress「分类目录」式） ─────────────────────
    function mlmCatalogNodeHtml(n, depth) {
        var isLink = (n.node_type === 'link-url');
        var toggle = n.has_children
            ? '<a href="javascript:;" class="text-muted me-1 mlm-tree-toggle flex-shrink-0" data-id="' + n.id + '" style="text-decoration:none;width:14px;text-align:center;">' +
              '<i class="fa fa-chevron-right"></i></a>'
            : '<span class="d-inline-block me-1 flex-shrink-0" style="width:14px"></span>';
        var icon = isLink
            ? '<i class="fa fa-link text-muted me-1 flex-shrink-0"></i>'
            : '<i class="far fa-folder text-warning me-1 flex-shrink-0"></i>';
        return '<div class="list-group-item small py-1 mlm-cat-node d-flex align-items-center" ' +
               'data-cat-id="' + n.id + '" data-depth="' + depth + '" ' +
               'style="padding-left:' + (8 + depth * 16) + 'px">' +
               toggle + icon +
               '<label class="d-flex align-items-center flex-grow-1 mb-0 text-truncate" style="cursor:pointer;min-width:0">' +
               '<input class="form-check-input me-2 mlm-chk flex-shrink-0" type="checkbox" data-type="catalog" data-id="' + n.id + '">' +
               '<span class="text-truncate">' + escapeHtml(n.title) + '</span>' +
               (isLink ? ' <span class="badge text-bg-light ms-1 flex-shrink-0">链接</span>' : '') +
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

    // 构建器加载完成后初始化目录树
    $(function () {
        if ($('#mlmRes_catalog').length) { mlmLoadCatalogTree(); }
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

    var miSrc = null, miGhost = null, miPlaceholder = null, miDropTarget = null, miDropMode = 'after', miOrigNext = null, miMoved = false;

    // 仅从左侧 grip 起拽；禁用文本选择与原生拖拽，从根本上解决"拖一点就卡死"
    $(document).on('mousedown', '#miList .mi-handle', function (e){
        if (e.which !== 1) { return; } // 仅左键
        e.preventDefault();
        var $item = $(this).closest('.mi-item');
        if (!$item.length) { return; }
        miSrc = $item;
        miOrigNext = $item.next();
        var rect = $item[0].getBoundingClientRect();

        miPlaceholder = $('<li class="list-group-item mi-placeholder py-2" ' +
            'style="height:' + Math.round(rect.height) + 'px;border:2px dashed #0d6efd;background:#f1f6ff;"></li>');
        $item.after(miPlaceholder);
        $item.addClass('mi-dragging-src'); // 隐藏原件（display:none）

        miGhost = $item.clone().removeClass('mi-dragging-src').addClass('mi-ghost')
            .css({
                position: 'fixed', zIndex: 9999, width: rect.width + 'px',
                left: (e.clientX + 8) + 'px', top: (e.clientY + 8) + 'px',
                pointerEvents: 'none', opacity: 0.92,
                boxShadow: '0 6px 18px rgba(0,0,0,.25)', listStyle: 'none'
            });
        $('body').append(miGhost);

        $(document).on('mousemove.mi', onMiMove);
        $(document).on('mouseup.mi', onMiUp);
    });

    function onMiMove(e){
        miMoved = true;
        if (miGhost) { miGhost.css({left: (e.clientX + 8) + 'px', top: (e.clientY + 8) + 'px'}); }
        miClearDropMarks();
        var $items = $('#miList .mi-item').not('.mi-dragging-src');
        var best = null, bestDist = Infinity, bestRect = null;
        $items.each(function (){
            var r = this.getBoundingClientRect();
            var d = Math.abs(e.clientY - (r.top + r.height / 2));
            if (d < bestDist) { bestDist = d; best = this; bestRect = r; }
        });
        if (!best) {
            $('#miList').append(miPlaceholder);
            miDropTarget = null; miDropMode = 'after';
            return;
        }
        var rel = (e.clientY - bestRect.top) / bestRect.height;
        var dx  = e.clientX - bestRect.left;
        miDropTarget = $(best);
        if (rel < 0.35) {
            miDropMode = 'before'; miDropTarget.addClass('mi-drop-before'); miPlaceholder.insertBefore(miDropTarget);
        } else if (rel > 0.65 && dx < 32) {
            miDropMode = 'after'; miDropTarget.addClass('mi-drop-after'); miPlaceholder.insertAfter(miDropTarget);
        } else {
            // 目标中部 或 下方靠右（WordPress 式「往下并缩进」）→ 设为子菜单
            miDropMode = 'child'; miDropTarget.addClass('mi-drop-child');
            miPlaceholder.insertAfter(miSubtreeLast(miDropTarget));
        }
        // 子菜单模式下占位行随目标缩进，直观预览层级
        if (miDropMode === 'child') {
            miPlaceholder.css('margin-left', ((parseInt(miDropTarget.css('margin-left'), 10) || 0) + 18) + 'px');
        } else {
            miPlaceholder.css('margin-left', '');
        }
    }

    function onMiUp(){
        $(document).off('mousemove.mi mouseup.mi');
        if (miGhost) { miGhost.remove(); miGhost = null; }
        miClearDropMarks();
        if (!miSrc) { cleanupMi(); return; }
        // 仅点击手柄、未发生拖动 → 还原原位，不改变层级、不保存
        if (!miMoved) {
            if (miOrigNext && miOrigNext.length) { miOrigNext.before(miSrc); } else { $('#miList').append(miSrc); }
            miSrc.removeClass('mi-dragging-src');
            if (miPlaceholder) { miPlaceholder.remove(); }
            cleanupMi();
            return;
        }

        var $src = miSrc;
        if (miDropMode === 'child' && miDropTarget && miDropTarget.length) {
            var dragId = parseInt($src.data('id'), 10);
            if (miInSubtree(dragId, miDropTarget[0])) {
                ZapToast.alert('不能将菜单拖入其自身子菜单',{bgColor:bgWarning,position:Toast_Pos_Center});
                if (miOrigNext && miOrigNext.length) { miOrigNext.before($src); } else { $('#miList').append($src); }
                $src.removeClass('mi-dragging-src');
                if (miPlaceholder) { miPlaceholder.remove(); }
                cleanupMi();
                return;
            }
            var newPid = parseInt(miDropTarget.data('id'), 10);
            $src.attr('data-parent', newPid);
            // 插到目标子树的末尾（成为其最后一个子项，与占位预览位置一致）
            miSubtreeLast(miDropTarget).after($src);
        } else if (miDropTarget && miDropTarget.length) {
            var sibPid = parseInt(miDropTarget.attr('data-parent'), 10) || 0;
            $src.attr('data-parent', sibPid);
            miPlaceholder.after($src);
        } else {
            $src.attr('data-parent', 0);
            miPlaceholder.after($src);
        }
        $src.removeClass('mi-dragging-src');
        if (miPlaceholder) { miPlaceholder.remove(); }
        miRefreshIndent();
        cleanupMi();
        mlSaveOrder();
    }

    function cleanupMi(){
        miSrc = null; miPlaceholder = null; miDropTarget = null; miDropMode = 'after'; miOrigNext = null; miMoved = false;
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

    // WordPress 式：左栏搜索并添加菜单项（kind: catalog/node；sub: 内容模型类型）
    function mlmSearch(kind, sub){
        sub = sub || '';
        var suffix = sub ? '_' + sub : '';
        var kw = $('#mlmKw_' + kind + suffix).val().trim();
        var box = $('#mlmRes_' + kind + suffix);
        box.html('<div class="text-muted p-2">搜索中…</div>');
        var params = {keyword:kw, limit:30};
        if (kind === 'catalog' && sub) { params.node_type = sub; }
        $.getJSON('<?php echo Url::action("Catalog@searchLinkTarget"); ?>', params, function (resp){
            if(resp.code !== 0){ box.html('<div class="text-danger p-2">搜索失败</div>'); return; }
            var lists = kind === 'node' ? (resp.nodes||[]) : (resp.catalogs||[]);
            if(lists.length === 0){ box.html('<div class="text-muted p-2">没有匹配</div>'); return; }
            var h = '<div class="list-group list-group-flush">';
            lists.forEach(function (item){
                h += '<label class="list-group-item small py-1 d-flex align-items-center">' +
                     '<input class="form-check-input me-2 mlm-chk" type="checkbox" data-type="' + kind + '" data-id="' + item.id + '">' +
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
        mlmAddMany([{ item_type:'custom_link', url:url, title:$('#mlmCustomLabel').val().trim() }]);
    }

    function mlmAddMany(arr){
        var pending = arr.length, done = 0, failed = false;
        arr.forEach(function (a){
            var data = { menu_id:window.__mlMenuId, item_type:a.item_type, sort_order: $('#miList .mi-item').length + 1 };
            if(a.item_type === 'custom_link'){ data.url = a.url; data.title = a.title || null; }
            else { data.ref_id = a.ref_id; }
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
