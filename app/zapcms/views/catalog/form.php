<?php
/**
 * @var array $catalog
 */

use zapcms\services\Catalog;
use zapcms\services\NodeType;

?>
<form id="catalogForm">
    <input type="hidden" name="catalog_id" value="<?php echo e($catalog['id'] ?? 0); ?>" />

    <div class="row g-2">
        <div class="col-md-6">
            <label for="catalog_pid" class="form-label small">上级栏目</label>
            <select class="form-select form-select-sm" id="catalog_pid" name="catalog[pid]">
                <option value="0">- 无 -</option>
                <?php
                Catalog::instance()->forEachAll(function ($row) use ($catalog) {
                    ?>
                    <option value="<?php echo e($row['id']); ?>"
                        <?php echo $catalog['pid'] == $row['id'] ? 'selected' : ''; ?>
                        <?php echo !empty($catalog['path']) && \zap\util\Str::startsWith($row['path'], $catalog['path']) ? 'disabled' : null; ?>>
                        <?php echo str_repeat('— ', max(0, intval($row['level']) - 1)); ?><?php echo e($row['title']); ?>
                    </option>
                <?php
                });
                ?>
            </select>
        </div>
        <div class="col-md-6">
            <label for="catalog_node_type" class="form-label small">内容模型</label>
            <select class="form-select form-select-sm" id="catalog_node_type" name="catalog[node_type]" onchange="chNodeType(this)">
                <?php foreach (NodeType::getNodeTypes() as $key => $row): ?>
                    <option value="<?php echo e($row['type_name']); ?>" <?php echo $row['type_name'] == $catalog['node_type'] ? 'selected' : null; ?>>
                        <?php echo e($row['title']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-6">
            <label for="catalog_title" class="form-label small">栏目名称</label>
            <input type="text" class="form-control form-control-sm" id="catalog_title" name="catalog[title]"
                   value="<?php echo htmlspecialchars($catalog['title'] ?? '', ENT_QUOTES); ?>" required>
        </div>

        <div class="col-md-6 <?php if_echo('link-url' === ($catalog['node_type'] ?? ''), 'd-none'); ?>" id="node_slug_id">
            <label for="catalog_slug" class="form-label small">别名</label>
            <input type="text" class="form-control form-control-sm" id="catalog_slug" name="catalog[slug]"
                   value="<?php echo htmlspecialchars($catalog['slug'] ?? '', ENT_QUOTES); ?>" placeholder="字母、中文、数字和-_">
        </div>

        <div class="col-md-6">
            <label for="catalog_sort_order" class="form-label small">排序</label>
            <input type="number" class="form-control form-control-sm" id="catalog_sort_order" name="catalog[sort_order]"
                   value="<?php echo e($catalog['sort_order'] ?? 0); ?>" placeholder="数值越小越靠前">
        </div>

        <div class="col-12">
            <label class="form-label small d-block">显示位置</label>
            <?php
            $positions = explode(',', $catalog['show_position'] ?? '');
            foreach (Catalog::getPositions() as $id => $title):
                ?>
                <div class="form-check form-check-inline">
                    <input class="form-check-input form-check-input-sm" type="checkbox"
                           name="catalog[show_position][<?php echo e($id); ?>]"
                        <?php echo in_array($id, $positions) ? 'checked' : ''; ?>
                           id="catalog_show_position<?php echo e($id); ?>" value="<?php echo e($id); ?>">
                    <label class="form-check-label small" for="catalog_show_position<?php echo e($id); ?>"><?php echo e($title); ?></label>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div id="extras_panel" class="<?php if_echo('link-url' !== ($catalog['node_type'] ?? ''), 'd-none'); ?>">
        <hr class="my-2">
        <div class="row g-2">
            <div class="col-md-6">
                <label for="catalog_link_type" class="form-label small">链接类型</label>
                <select class="form-select form-select-sm" id="catalog_link_type" name="catalog[link_type]" onchange="chLinkType(this)">
                    <option value="catalog" <?php if_echo(($catalog['link_type'] ?? '') === 'catalog', 'selected'); ?>>栏目（站内）</option>
                    <option value="node" <?php if_echo(($catalog['link_type'] ?? '') === 'node', 'selected'); ?>>内容（站内）</option>
                    <option value="custom_link" <?php if_echo(($catalog['link_type'] ?? '') === 'custom_link', 'selected'); ?>>自定义链接</option>
                </select>
            </div>
            <div class="col-md-6">
                <label for="catalog_link_target" class="form-label small">打开方式</label>
                <select class="form-select form-select-sm" id="catalog_link_target" name="catalog[link_target]">
                    <option value="_self" <?php if_echo(($catalog['link_target'] ?? '') === '_self', 'selected'); ?>>当前页面</option>
                    <option value="_blank" <?php if_echo(($catalog['link_target'] ?? '') === '_blank', 'selected'); ?>>新页面</option>
                </select>
            </div>

            <!-- ============== 内容选择器（link_type = catalog / node） ============== -->
            <div class="col-12" id="link_object_panel" style="display:none;">
                <label class="form-label small d-flex justify-content-between align-items-center">
                    <span>目标内容</span>
                    <button type="button" class="btn btn-outline-success btn-sm py-0" onclick="openLinkPicker()">
                        <i class="fa fa-search me-1"></i>选择内容
                    </button>
                </label>
                <div id="link_object_preview" class="alert alert-secondary py-2 px-2 mb-2 small d-flex align-items-center">
                    <i class="fa fa-info-circle me-2"></i>
                    <span id="link_object_label">暂未选择内容</span>
                    <button type="button" class="btn btn-link btn-sm text-danger ms-auto p-0" id="link_object_clear" style="display:none;" onclick="clearLinkObject()">
                        <i class="fa fa-times"></i>清除
                    </button>
                </div>
                <input type="hidden" name="catalog[link_object]" id="link_object_value" value="<?php echo (int) ($catalog['link_object'] ?? 0); ?>">
                <input type="hidden" name="catalog[link_to]" id="link_to_value" value="<?php echo htmlspecialchars($catalog['link_to'] ?? '', ENT_QUOTES); ?>">
            </div>

            <!-- ============== 自定义链接（link_type = custom_link） ============== -->
            <div class="col-12" id="link_custom_panel" style="display:none;">
                <label for="catalog_link_to_custom" class="form-label small">链接地址</label>
                <input type="text" class="form-control form-control-sm" id="catalog_link_to_custom"
                       placeholder="https://example.com 或 /path" value="<?php echo htmlspecialchars(($catalog['link_type'] ?? '') === 'custom_link' ? ($catalog['link_to'] ?? '') : '', ENT_QUOTES); ?>">
                <div class="form-text">外部链接以 http:// 开头；站内路径以 / 开头</div>
            </div>
        </div>
    </div>
</form>

<script>
    // ===================== 面板切换 =====================
    function chNodeType(el) {
        if (el.value === 'link-url') {
            $('#extras_panel').removeClass('d-none');
            $('#node_slug_id').addClass('d-none');
            chLinkType(document.getElementById('catalog_link_type'));
        } else {
            $('#extras_panel').addClass('d-none');
            $('#node_slug_id').removeClass('d-none');
        }
    }

    function chLinkType(el) {
        var v = el.value;
        if (v === 'catalog' || v === 'node') {
            $('#link_object_panel').show();
            $('#link_custom_panel').hide();
            // 如果已选对象 kind 与当前 link_type 不一致，重新生成 link_to
            syncLinkToValue();
        } else {
            // custom_link
            $('#link_object_panel').hide();
            $('#link_custom_panel').show();
            $('#link_to_value').val($('#catalog_link_to_custom').val());
        }
    }

    $('#catalog_link_to_custom').on('input', function () {
        $('#link_to_value').val($(this).val());
    });

    // ===================== 链接 URL 生成规则 =====================
    /**
     * 根据对象属性（kind/slug/node_type/id）生成 link_to
     *
     * 规则：
     *   - 栏目 (catalog)：link_to = slug        → 前端渲染为 /{parentPath}/{slug}
     *   - 内容 (node)：   link_to = slug        → 前端渲染为 /{parentPath}/{slug}，Router 根据 slug 查 node 表
     *
     * 唯一性：slug 在 node 表中全局唯一，router 通过 slug 定位。
     *
     * @param {string} kind       'catalog' | 'node'
     * @param {string} slug       对象 slug
     * @param {string} nodeType   对象 node_type
     * @param {int}    id         对象 ID
     * @returns {string} link_to 值
     */
    function buildLinkTo(kind, slug, nodeType, id) {
        // slug 为空时回退到 node_type/id 组合
        if (!slug || slug === '--zap-link-url') {
            if (kind === 'catalog') {
                return 'catalog/' + id;
            }
            return (nodeType || 'page') + '/' + id;
        }
        return slug;
    }

    /**
     * 生成预览用的显示 URL（用于 UI 展示，非实际存储值）
     */
    function buildPreviewUrl(kind, slug, nodeType, id) {
        if (kind === 'catalog') {
            return siteUrlBase + (slug ? '/' + slug : '/catalog/' + id);
        }
        return siteUrlBase + '/' + (slug || nodeType + '/' + id);
    }

    // ===================== 选中对象回填 =====================
    function selectLinkTarget(item) {
        var id       = item.id;
        var kind     = item.kind;
        var slug     = item.slug || '';
        var nodeType = item.node_type || '';
        var title    = item.title;
        var linkTo   = buildLinkTo(kind, slug, nodeType, id);
        var preview  = buildPreviewUrl(kind, slug, nodeType, id);
        var kindLabel = kind === 'catalog' ? '[栏目] ' : '[内容] ';

        // 自动更新 link_type 与对象类型一致
        $('#catalog_link_type').val(kind);

        $('#link_object_value').val(id).data({
            'link-to':   linkTo,
            'slug':      slug,
            'node-type': nodeType,
            'kind':      kind,
            'label':     kindLabel + title
        });
        $('#link_to_value').val(linkTo);

        $('#link_object_label')
            .html(kindLabel + '<strong>' + escapeHtml(title) + '</strong>' +
                  ' <span class="text-muted">→ ' + escapeHtml(preview) + '</span>')
            .removeClass('text-muted');
        $('#link_object_clear').show();
    }

    function clearLinkObject() {
        $('#link_object_value').val(0).removeData('link-to slug node-type kind label');
        $('#link_to_value').val('');
        $('#link_object_label').text('暂未选择内容').addClass('text-muted');
        $('#link_object_clear').hide();
    }

    function syncLinkToValue() {
        var linkTo = $('#link_object_value').data('link-to');
        if (linkTo) {
            $('#link_to_value').val(linkTo);
        }
    }

    // ===================== 搜索弹窗 =====================
    function openLinkPicker() {
        var picker = ZapModal.create({
            id: 'linkObjectPicker',
            title: '选择链接目标',
            content: renderPickerContent(),
            backdrop: true,
            size: 'modal-lg',
            buttons: [{close: true, title: '取消'}],
        }, true);
        picker.show();
        bindPickerEvents(picker);
    }

    function renderPickerContent() {
        return '<div id="linkPickerBox">' +
            '  <div class="input-group input-group-sm mb-2">' +
            '    <input type="text" id="linkPickerKeyword" class="form-control" placeholder="搜索栏目或内容标题 / slug / ID..." autofocus>' +
            '    <button class="btn btn-outline-secondary" type="button" id="linkPickerSearch"><i class="fa fa-search"></i> 搜索</button>' +
            '  </div>' +
            '  <div class="d-flex gap-2 mb-2 small">' +
            '    <span class="text-muted">筛选:</span>' +
            '    <div class="form-check form-check-inline">' +
            '      <input class="form-check-input" type="radio" name="linkPickerKind" id="kp_all" value="all" checked>' +
            '      <label class="form-check-label" for="kp_all">全部</label>' +
            '    </div>' +
            '    <div class="form-check form-check-inline">' +
            '      <input class="form-check-input" type="radio" name="linkPickerKind" id="kp_catalog" value="catalog">' +
            '      <label class="form-check-label" for="kp_catalog">栏目</label>' +
            '    </div>' +
            '    <div class="form-check form-check-inline">' +
            '      <input class="form-check-input" type="radio" name="linkPickerKind" id="kp_node" value="node">' +
            '      <label class="form-check-label" for="kp_node">内容</label>' +
            '    </div>' +
            '  </div>' +
            '  <div id="linkPickerResult" class="border rounded" style="max-height:400px;overflow-y:auto;">' +
            '    <div class="text-center text-muted p-4"><i class="fa fa-spinner fa-spin"></i> 正在加载...</div>' +
            '  </div>' +
            '</div>';
    }

    function bindPickerEvents(picker) {
        var keywordInput = $('#linkPickerKeyword');
        var searchBtn = $('#linkPickerSearch');
        var resultBox = $('#linkPickerResult');

        function doSearch() {
            var keyword = keywordInput.val().trim();
            var kind = $('input[name="linkPickerKind"]:checked').val();
            resultBox.html('<div class="text-center text-muted p-4"><i class="fa fa-spinner fa-spin"></i> 正在搜索...</div>');
            $.ajax({
                url: '<?php echo url_action('Catalog@searchLinkTarget'); ?>',
                method: 'GET',
                data: {keyword: keyword, limit: 30},
                dataType: 'json',
                success: function (resp) {
                    if (resp.code !== 0) {
                        resultBox.html('<div class="text-danger p-3">' + (resp.msg || '搜索失败') + '</div>');
                        return;
                    }
                    renderResults(resp, kind, resultBox, picker);
                },
                error: function () {
                    resultBox.html('<div class="text-danger p-3">请求失败</div>');
                }
            });
        }

        searchBtn.on('click', doSearch);
        keywordInput.on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                doSearch();
            }
        });

        // 初始加载
        doSearch();
    }

    function renderResults(resp, kind, box, picker) {
        var catalogs = (resp.catalogs || []);
        var nodes = (resp.nodes || []);

        // 额外支持按 slug / id 本地筛选（服务端已做 title 筛选，这里补 slug/id）
        var keyword = ($('#linkPickerKeyword').val() || '').trim().toLowerCase();
        if (keyword) {
            var kwNum = parseInt(keyword, 10);
            catalogs = catalogs.filter(function(item) {
                if (item.id === kwNum) return true;
                if ((item.slug || '').toLowerCase().indexOf(keyword) !== -1) return true;
                return true; // title 已由服务端筛选
            });
            nodes = nodes.filter(function(item) {
                if (item.id === kwNum) return true;
                if ((item.slug || '').toLowerCase().indexOf(keyword) !== -1) return true;
                return true;
            });
        }

        if (kind === 'catalog') nodes = [];
        if (kind === 'node') catalogs = [];

        if (catalogs.length === 0 && nodes.length === 0) {
            box.html('<div class="text-center text-muted p-4"><i class="fa fa-inbox"></i> 没有匹配的内容</div>');
            return;
        }

        var html = '<table class="table table-hover table-sm mb-0">' +
            '<thead><tr class="table-light">' +
            '<th class="ps-2">名称 / Slug</th>' +
            '<th style="width:80px">类型</th>' +
            '<th style="width:80px" class="text-end pe-2">操作</th>' +
            '</tr></thead><tbody>';

        catalogs.forEach(function (item) {
            var slugDisplay = (item.slug && item.slug !== '--zap-link-url') ?
                '<br><small class="text-muted">slug: ' + escapeHtml(item.slug) + '</small>' : '';
            html += '<tr>' +
                '<td class="ps-2"><i class="fa fa-folder text-warning me-1"></i>' +
                escapeHtml(item.path_label || item.title) + slugDisplay +
                '<br><small class="text-muted">#' + item.id + ' · ' + escapeHtml(item.node_type || 'catalog') + '</small></td>' +
                '<td><span class="badge text-bg-light">栏目</span></td>' +
                '<td class="text-end pe-2">' +
                '<button type="button" class="btn btn-success btn-sm py-0 pick-item"' +
                ' data-json=\'' + JSON.stringify({id:item.id, kind:'catalog', slug:item.slug||'', node_type:item.node_type||'', title:item.title}) + '\'>' +
                '<i class="fa fa-check"></i> 选中</button>' +
                '</td></tr>';
        });
        nodes.forEach(function (item) {
            var slugDisplay = item.slug ?
                '<br><small class="text-muted">slug: ' + escapeHtml(item.slug) + '</small>' : '';
            html += '<tr>' +
                '<td class="ps-2"><i class="fa fa-file text-info me-1"></i>' +
                escapeHtml(item.path_label || item.title) + slugDisplay +
                '<br><small class="text-muted">#' + item.id + ' · ' + escapeHtml(item.node_type) + '</small></td>' +
                '<td><span class="badge text-bg-light">内容</span></td>' +
                '<td class="text-end pe-2">' +
                '<button type="button" class="btn btn-success btn-sm py-0 pick-item"' +
                ' data-json=\'' + JSON.stringify({id:item.id, kind:'node', slug:item.slug||'', node_type:item.node_type, title:item.title}) + '\'>' +
                '<i class="fa fa-check"></i> 选中</button>' +
                '</td></tr>';
        });

        html += '</tbody></table>';
        box.html(html);

        box.find('.pick-item').on('click', function () {
            var item = JSON.parse($(this).attr('data-json'));
            selectLinkTarget(item);
            picker.hide();
        });
    }

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    // ===================== 初始化：还原已选内容预览 =====================
    var siteUrlBase = '<?php echo get_site_base_url(); ?>';

    (function initLinkObjectPreview() {
        var hiddenObj  = parseInt($('#link_object_value').val(), 10) || 0;
        var hiddenTo   = $('#link_to_value').val();
        var hiddenType = $('#catalog_link_type').val();
        if (hiddenObj > 0 && hiddenTo && (hiddenType === 'catalog' || hiddenType === 'node')) {
            $.get('<?php echo url_action('Catalog@searchLinkTarget'); ?>', {keyword: '', limit: 50}, function (resp) {
                if (resp.code !== 0) return;
                var all = (resp.catalogs || []).concat(resp.nodes || []);
                var found = all.find(function (x) { return x.id === hiddenObj; });
                if (found) {
                    // 补充 data attribute，以便切换时能同步 link_to
                    var kindLabel = found.kind === 'catalog' ? '[栏目] ' : '[内容] ';
                    var preview = buildPreviewUrl(found.kind, found.slug || '', found.node_type || '', found.id);
                    $('#link_object_value').data({
                        'link-to':   hiddenTo,
                        'slug':      found.slug || '',
                        'node-type': found.node_type || '',
                        'kind':      found.kind,
                        'label':     kindLabel + found.title
                    });
                    $('#link_object_label')
                        .html(kindLabel + '<strong>' + escapeHtml(found.title) + '</strong>' +
                              ' <span class="text-muted">→ ' + escapeHtml(preview) + '</span>')
                        .removeClass('text-muted');
                    $('#link_object_clear').show();
                }
            }, 'json');
        }
    })();

    // 触发 link_type 初始状态
    $(function () {
        chLinkType(document.getElementById('catalog_link_type'));
    });
</script>