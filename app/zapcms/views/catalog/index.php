<?php

use zap\facades\Url;

IS_AJAX !== true && $this->layout('layouts/common');

// 栏目树改为前端懒加载（见下方 <script>）：初始只加载根节点，展开时按父级取子级。
// 位置归属徽标由 Catalog@children 接口随节点返回，无需在此预取。
?>

<div class="card card-outline card-success">
    <div class="card-header p-2 d-flex align-items-center">
        <h6 class="card-title mb-0">
            <i class="fa fa-th-list me-1 text-success"></i>栏目列表
            <?php if (!empty($menu)): ?>
            <span class="badge text-bg-success ms-1 rounded-pill"><?php echo e($catalog_count); ?></span>
            <?php endif; ?>
        </h6>
        <div class="card-tools ms-auto">
            <div class="d-flex gap-2 align-items-center">
                <button type="button" class="btn btn-outline-success btn-sm" onclick="save()">
                    <i class="fa fa-save me-1"></i>保存
                </button>
                <button type="button" class="btn btn-success btn-sm" onclick="addOrEdit(0,0)">
                    <i class="fa fa-plus me-1"></i>添加栏目
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="mlOpenDrawer()" title="打开菜单构建器（默认顶部菜单）">
                    <i class="fa fa-bars me-1"></i>菜单位置
                </button>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <script>
            function checkAll(el) {
                $('.zap_catalog').prop('checked', $(el).prop('checked'));
                updateSelectedCount();
            }
        </script>
        <form action="post" id="reqForm">
            <div class="table-responsive">
                <table class="table table-hover table-striped text-nowrap align-middle mb-0 catalog-table">
                    <thead>
                        <tr class="table-light">
                            <th scope="col" style="width:40px" class="text-center">
                                <input class="form-check-input" type="checkbox" onclick="checkAll(this)"/>
                            </th>
                            <th scope="col" style="width:60px">排序</th>
                            <th scope="col" class="w-100">栏目名称</th>
                            <th scope="col" class="d-none d-md-table-cell">SEO</th>
                            <th scope="col" class="d-none d-lg-table-cell">模型</th>
                            <th scope="col" class="d-none d-md-table-cell">位置</th>
                            <th scope="col" style="width:120px" class="text-center">操作</th>
                        </tr>
                    </thead>
                    <tbody id="treeBody">
                        <tr id="treeLoading">
                            <td colspan="7" class="text-center py-4 text-muted">
                                <i class="fa fa-spinner fa-spin me-1"></i>加载中…
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="card-footer p-2 bg-white border-top d-flex align-items-center">
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="remove()">
                    <i class="fa fa-trash me-1"></i>删除所选栏目
                </button>
                <span id="selectedCount" class="ms-2 text-muted small">已选 0 项</span>
            </div>
        </form>
    </div>
</div>

<!-- 菜单位置管理：右侧抽屉 -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="menuLocationDrawer" aria-labelledby="menuLocationDrawerLabel" style="width: min(1040px, 96vw);">
    <div class="offcanvas-header bg-light border-bottom py-2">
        <h6 class="offcanvas-title mb-0" id="menuLocationDrawerLabel">
            <i class="fa fa-bars me-1 text-success"></i>菜单位置
        </h6>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="关闭"></button>
    </div>
    <div class="offcanvas-body p-0" id="menuLocationDrawerBody">
        <?php echo $this->partial('menu_location._list', ['locations' => \zapcms\services\MenuLocation::instance()->all()]); ?>
    </div>
    <div class="offcanvas-footer border-top p-2 small text-muted bg-light">
        顶部 tab 可切换位置管理菜单项（栏目 / 内容 / 自定义链接）；前台用
        <code>catalog_menu('key')</code> 输出对应位置菜单。
    </div>
</div>

<style>
.table {
    font-size: 0.875rem;
}
.table thead th {
    font-weight: 600;
    font-size: 0.8rem;
    padding: 0.75rem 0.5rem;
    white-space: nowrap;
    vertical-align: middle;
}
.table tbody td {
    padding: 0.625rem 0.5rem;
    vertical-align: middle;
}
.table tbody td.title-cell {
    padding-left: 0;
}
.catalog-table tbody tr { transition: background-color .15s ease; }
.catalog-table tbody tr:hover { background-color: #f1fdf6; }
.catalog-title {
    display: flex;
    align-items: center;
    flex-wrap: nowrap;
    gap: 0rem;
}
.catalog-title .title-input {
    max-width: 140px;
    flex-shrink: 0;
}
.catalog-title .menu-icon {
    color: #10b981;
    width: 20px;
    text-align: center;
    flex-shrink: 0;
}
.catalog-title .menu-id {
    color: #9ca3af;
    font-size: 0.75rem;
    flex-shrink: 0;
}
.sort-input {
    width: 50px !important;
    text-align: center;
    padding: 0.25rem 0.25rem !important;
}
.seo-cell {
    max-width: 120px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.btn-group .btn { border-color: #dee2e6; }
.btn-group .btn:hover { z-index: 2; }
@media (max-width: 767px) {
    .table { font-size: 0.8rem; }
    .table th, .table td { padding: 0.5rem 0.35rem; }
    .sort-input { width: 40px !important; font-size: 0.7rem; }
    .catalog-title .title-input { max-width: 100px; font-size: 0.8rem; }
    .catalog-title .menu-icon { width: 16px; font-size: 0.9rem; }
    .seo-cell { display: none; }
}
.mi-handle { cursor: grab; }
.mi-item.dragging { opacity: .5; }
.mi-item .mi-title { min-width: 140px; }
.mi-item.mi-drop-before { box-shadow: inset 0 3px 0 #198754; }
.mi-item.mi-drop-after  { box-shadow: inset 0 -3px 0 #198754; }
.mi-item.mi-drop-child  { box-shadow: inset 3px 0 0 #198754; background: #f0fff4; }
#menuLocationDrawer .offcanvas-body { overflow-x: hidden; }
</style>

<script>
    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    // ───────────────────── 栏目树（懒加载 / 可展开） ─────────────────────
    var childrenUrl = '<?php echo Url::action("Catalog@children"); ?>';

    // 展开/折叠：点开才 AJAX 取子级
    $(document).on('click', '.tree-toggle', function () {
        toggleBranch(parseInt($(this).data('id'), 10));
    });

    function toggleBranch(id) {
        var $row = $('#treeBody .catalog-row[data-id="' + id + '"]');
        if (!$row.length) return;
        var depth = parseInt($row.data('depth'), 10);
        var $ic = $row.find('.tree-toggle i');
        if ($ic.hasClass('fa-chevron-down')) {
            setDescendantsVisible($row, depth, false);
            $ic.removeClass('fa-chevron-down').addClass('fa-chevron-right');
        } else if ($row.data('loaded')) {
            setDescendantsVisible($row, depth, true);
            $ic.removeClass('fa-chevron-right').addClass('fa-chevron-down');
        } else {
            loadChildren(id, $row);
        }
    }

    // 显示/隐藏某节点下所有后代行
    function setDescendantsVisible($row, depth, visible) {
        var $sib = $row.next();
        while ($sib.length && parseInt($sib.data('depth') || 0, 10) > depth) {
            $sib.toggle(visible);
            $sib = $sib.next();
        }
    }

    // 按父级取子级并插入（pid=0 为根；$afterRow 为 null 时追加到树根）
    function loadChildren(pid, $afterRow) {
        $.getJSON(childrenUrl, {parent_id: pid}, function (res) {
            if (pid === 0) { $('#treeLoading').remove(); }
            if (res.code !== 0 || !res.nodes || !res.nodes.length) {
                if (pid === 0) {
                    $('#treeBody').append(
                        '<tr><td colspan="7" class="text-center py-5 text-muted">' +
                        '<i class="fa fa-inbox" style="font-size:2rem;display:block;"></i>' +
                        '<small>暂无栏目数据</small></td></tr>'
                    );
                }
                if ($afterRow && $afterRow.length) {
                    $afterRow.data('loaded', true);
                    var $ie = $afterRow.find('.tree-toggle i');
                    if ($ie.length) { $ie.removeClass('fa-chevron-right').addClass('fa-chevron-down'); }
                }
                return;
            }
            var baseDepth = ($afterRow && $afterRow.length)
                ? (parseInt($afterRow.data('depth'), 10) + 1)
                : 0;
            var html = '';
            res.nodes.forEach(function (n) { html += renderRow(n, baseDepth); });
            if ($afterRow && $afterRow.length) {
                $afterRow.after(html);
                $afterRow.data('loaded', true);
                $afterRow.find('.tree-toggle i').removeClass('fa-chevron-right').addClass('fa-chevron-down');
            } else {
                $('#treeBody').append(html);
            }
            updateSelectedCount();
        });
    }

    // 渲染单个栏目行（与旧缩进表格列一致）
    function renderRow(node, depth) {
        var paddingLeft = (depth * 1.5).toFixed(2);
        var toggle = node.has_children
            ? '<button type="button" class="btn btn-sm btn-link text-secondary py-0 px-1 tree-toggle" data-id="' + node.id + '">' +
              '<i class="fa fa-chevron-right"></i></button>'
            : '<span class="tree-toggle-spacer d-inline-block" style="width:26px"></span>';
        var icon = node.icon ? '<i class="' + escapeHtml(node.icon) + ' menu-icon"></i>' : '';
        var ext = node.node_type === 'link-url'
            ? '<i class="fa fa-external-link-alt text-muted" title="快捷链接"></i>' : '';
        var locHtml = '';
        if (node.locations && node.locations.length) {
            node.locations.forEach(function (l) {
                locHtml += '<a href="javascript:;" class="badge text-bg-success loc-badge" ' +
                    'onclick="mlOpenDrawer(\'' + escapeHtml(l.key) + '\')" ' +
                    'title="点击打开「' + escapeHtml(l.title) + '」菜单构建器">' + escapeHtml(l.title) + '</a> ';
            });
        } else {
            locHtml = '<span class="text-muted small">—</span>';
        }
        var seo = node.slug === '--zap-link-url' ? (node.link_to || '') : (node.slug || '');
        return '' +
            '<tr class="catalog-row" data-id="' + node.id + '" data-pid="' + node.pid + '" data-depth="' + depth + '">' +
                '<td class="text-center">' +
                    '<input name="catalog[' + node.id + '][id]" value="' + node.id + '" ' +
                    'class="form-check-input zap_catalog" type="checkbox" onchange="updateSelectedCount()">' +
                '</td>' +
                '<td>' +
                    '<input name="catalog[' + node.id + '][sort_order]" value="' + escapeHtml(node.sort_order) + '" ' +
                    'class="form-control form-control-sm sort-input" data-bs-toggle="tooltip" data-bs-placement="right" ' +
                    'data-bs-title="数值越小越靠前">' +
                '</td>' +
                '<td class="align-middle title-cell">' +
                    '<div class="catalog-title" style="padding-left:' + paddingLeft + 'rem;">' +
                        toggle + icon +
                        '<input name="catalog[' + node.id + '][title]" value="' + escapeHtml(node.title) + '" ' +
                        'class="d-inline form-control form-control-sm title-input">' +
                        '<small class="menu-id d-none d-sm-inline">#' + node.id + '</small>' + ext +
                    '</div>' +
                '</td>' +
                '<td class="align-middle d-none d-md-table-cell">' +
                    '<small class="text-muted seo-cell">' + escapeHtml(seo) + '</small>' +
                '</td>' +
                '<td class="align-middle d-none d-lg-table-cell">' +
                    '<span class="badge text-bg-light">' + escapeHtml(node.node_type_title || node.node_type) + '</span>' +
                '</td>' +
                '<td class="align-middle d-none d-md-table-cell loc-cell" style="max-width:220px">' + locHtml + '</td>' +
                '<td class="align-middle text-center">' +
                    '<div class="btn-group btn-group-sm" role="group">' +
                        '<button type="button" class="btn btn-outline-secondary" title="编辑" onclick="addOrEdit(' + node.id + ',0)"><i class="fa fa-edit"></i></button>' +
                        '<button type="button" class="btn btn-outline-secondary" title="添加子栏目" onclick="addOrEdit(0,' + node.id + ')"><i class="fa fa-plus"></i></button>' +
                        '<button type="button" class="btn btn-outline-danger" title="删除" onclick="removeOne(' + node.id + ')"><i class="fa fa-trash"></i></button>' +
                    '</div>' +
                '</td>' +
            '</tr>';
    }

    $(function (){
        Zap.EnableToolTip();
        updateSelectedCount();
        loadChildren(0, null); // 初始只加载根栏目，展开时按需取子级
    })

    function save(){
        $.ajax({
            url:'<?php echo Url::action("Catalog@save");?>',
            method:'post',
            data:$('#reqForm').serialize(),
            success:function (data){
                ZapToast.alert(data.msg,{
                    bgColor:data.code===0?bgSuccess:bgDanger,
                    position:Toast_Pos_Center
                });
            }
        })
    }

    function confirmDelete(msg, callback){
        if (typeof Swal === 'undefined') {
            if (window.confirm(msg)) { callback(); }
            return;
        }
        Swal.fire({
            title: '确认删除',
            text: msg,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '删除',
            cancelButtonText: '取消',
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d'
        }).then(function (result) {
            if (result.isConfirmed) { callback(); }
        });
    }

    function remove(){
        const checked = $('.zap_catalog:checked');
        if(checked.length === 0){
            ZapToast.alert('请选择需要删除的栏目',{bgColor:bgWarning,position:Toast_Pos_Center});
            return;
        }
        confirmDelete('确定要删除选中的 ' + checked.length + ' 个栏目吗？其子栏目将一并删除，且不可恢复。', function(){
            $.ajax({
                url:'<?php echo Url::action("Catalog@remove");?>',
                method:'post',
                data:checked.serialize(),
                success:function (data){
                    ZapToast.alert(data.msg,{bgColor:data.code===0?bgSuccess:bgDanger,position:Toast_Pos_Center});
                    Zap.reload();
                }
            });
        });
    }

    function removeOne(id){
        confirmDelete('确定删除该栏目吗？其子栏目将一并删除，且不可恢复。', function(){
            var payload = {};
            payload['catalog[' + id + '][id]'] = id;
            $.ajax({
                url:'<?php echo Url::action("Catalog@remove");?>',
                method:'post',
                data:payload,
                success:function (data){
                    ZapToast.alert(data.msg,{bgColor:data.code===0?bgSuccess:bgDanger,position:Toast_Pos_Center});
                    Zap.reload();
                }
            });
        });
    }

    function updateSelectedCount(){
        var n = $('.zap_catalog:checked').length;
        $('#selectedCount').text('已选 ' + n + ' 项');
    }

    function addOrEdit(cid, pid){
        cid = cid || 0;
        pid = pid || 0;
        m = ZapModal.create({
            id:'addCatalog',
            title: cid ? '修改栏目' : (pid ? '添加子栏目' : '添加栏目'),
            content:ZapModal.loadding(),
            backdrop:false,
            url:'<?php echo Url::action("Catalog@form");?>?modalId=addCatalog&pid='+pid+'&cid='+cid,
            buttons:[{close:true,title:"关闭"},{title:"保存",class:'btn-success'}],
            btn2:function (){
                $.ajax({
                    url:'<?php echo Url::action("Catalog@saveCatalog");?>',
                    method:'post',
                    data:$('#addCatalog form').serialize(),
                    success:function (data){
                        ZapToast.alert(data.msg,{bgColor:data.code===0?bgSuccess:bgDanger});
                        Zap.reload();
                    }
                }).always(function(){
                    m.hide();
                })
            }
        },true)
        m.show();
    }

    // ───────────────────── 菜单位置抽屉 ─────────────────────
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
                    mlReload(); m.hide();
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
            text:'已勾选该位置的栏目不会被删除，仅不再归属此位置。',
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
                    if(data.code===0){ mlReload(); }
                }
            });
        });
    }

    function mlReload(){
        $.get('<?php echo Url::action("MenuLocation@listFragment"); ?>', function (html){
            $('#menuLocationDrawerBody').html(html);
        });
    }

    // ───────────────────── 菜单构建器（抽屉二级） ─────────────────────
    function mlOpenDrawer(key){
        var el = document.getElementById('menuLocationDrawer');
        if (el && window.bootstrap) {
            bootstrap.Offcanvas.getOrCreateInstance(el).show();
        }
        mlOpenBuilder(key || 'top');
    }

    function mlOpenBuilder(key){
        window.__mlLocationKey = key;
        $.get('<?php echo Url::action("MenuLocation@builder"); ?>', {key:key}, function (html){
            $('#menuLocationDrawerBody').html(html);
        });
    }

    function mlBackToList(){
        mlReload();
    }

    function mlSaveTitle(id, val){
        $.post('<?php echo Url::action("MenuLocation@saveItem"); ?>', {
            id:id, location_key:window.__mlLocationKey, title:val
        }, function (data){
            if(data.code !== 0){ Swal.fire({icon:'error',title:'保存失败',text:data.msg}); }
        }, 'json');
    }

    function mlSaveParent(id, val){
        $.post('<?php echo Url::action("MenuLocation@saveItem"); ?>', {
            id:id, location_key:window.__mlLocationKey, parent_id:val
        }, function (data){
            if(data.code === 0){ mlOpenBuilder(window.__mlLocationKey); }
            else { Swal.fire({icon:'error',title:'保存失败',text:data.msg}); }
        }, 'json');
    }

    // 菜单项 mega 字段异步保存（只提交单个字段）
    function mlSaveMeta(id, field, val){
        var d = {id:id, location_key:window.__mlLocationKey};
        d[field] = val;
        $.post('<?php echo Url::action("MenuLocation@saveItem"); ?>', d, function (res){
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
                if(data.code === 0){ mlOpenBuilder(window.__mlLocationKey); }
            }, 'json');
        });
    }

    // ───────────────────── 菜单项拖拽排序（指针拖拽，彻底避免原生 DnD 卡死） ─────────────────────
    function miClearDropMarks(){
        $('.mi-item').removeClass('mi-drop-before mi-drop-after mi-drop-child');
    }

    // 根据各项父级 select 重算缩进（拖拽后即时反馈）
    function miRefreshIndent(){
        $('#miList .mi-item').each(function (){
            var depth = 0, pid = parseInt($(this).find('.mi-parent').val(), 10) || 0;
            var guard = 0;
            while (pid > 0 && guard++ < 20){
                var $p = $('#miList .mi-item[data-id="' + pid + '"]');
                if (!$p.length) { break; }
                depth++;
                pid = parseInt($p.find('.mi-parent').val(), 10) || 0;
            }
            $(this).css('margin-left', (depth * 18) + 'px');
        });
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
        miDropTarget = $(best);
        if (rel < 0.33) {
            miDropMode = 'before'; miDropTarget.addClass('mi-drop-before'); miPlaceholder.insertBefore(miDropTarget);
        } else if (rel > 0.66) {
            miDropMode = 'after'; miDropTarget.addClass('mi-drop-after'); miPlaceholder.insertAfter(miDropTarget);
        } else {
            miDropMode = 'child'; miDropTarget.addClass('mi-drop-child'); miPlaceholder.insertAfter(miDropTarget);
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
            $src.find('.mi-parent').val(newPid);
            miPlaceholder.after($src);
        } else if (miDropTarget && miDropTarget.length) {
            var sibPid = parseInt(miDropTarget.attr('data-parent'), 10) || 0;
            $src.attr('data-parent', sibPid);
            $src.find('.mi-parent').val(sibPid);
            miPlaceholder.after($src);
        } else {
            $src.attr('data-parent', 0);
            $src.find('.mi-parent').val(0);
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
                parent_id:  parseInt($(this).find('.mi-parent').val(), 10) || 0,
                sort_order: idx
            });
        });
        $.post('<?php echo Url::action("MenuLocation@reorderItems"); ?>', {items:items}, function (data){
            if(data.code === 0){ mlOpenBuilder(window.__mlLocationKey); }
            else { Swal.fire({icon:'error',title:'保存失败',text:data.msg}); }
        }, 'json').fail(function (xhr){
            Swal.fire({icon:'error',title:'保存失败',text:xhr.statusText || '请求失败'});
        });
    }

    // WordPress 式：左栏搜索并添加菜单项
    function mlmSearch(kind){
        var kw = $('#mlmKw_' + kind).val().trim();
        var box = $('#mlmRes_' + kind);
        box.html('<div class="text-muted p-2">搜索中…</div>');
        $.getJSON('<?php echo Url::action("Catalog@searchLinkTarget"); ?>', {keyword:kw, limit:30}, function (resp){
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
        var key = window.__mlLocationKey;
        var pending = arr.length, done = 0, failed = false;
        arr.forEach(function (a){
            var data = { location_key:key, item_type:a.item_type, sort_order: $('#miList .mi-item').length + 1 };
            if(a.item_type === 'custom_link'){ data.url = a.url; data.title = a.title || null; }
            else { data.ref_id = a.ref_id; }
            $.post('<?php echo Url::action("MenuLocation@saveItem"); ?>', data, function (res){
                if(res.code !== 0){ failed = true; return; }
                done++;
                if(done >= pending){
                    if(!failed){ mlOpenBuilder(key); }
                    else { Swal.fire({icon:'warning',title:'部分添加失败'}); }
                }
            }, 'json');
        });
    }
</script>
