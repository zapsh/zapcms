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
            <?php
            // 分类上万时不渲染整棵树：初始只给顶层 + 当前祖先链，其余通过下方搜索按需加载
            $currentPid   = (int) ($catalog['pid'] ?? ($pid ?? 0));
            $parentOpts   = Catalog::instance()->getSelectOptions(
                (int) ($catalog['id'] ?? 0), (string) ($catalog['path'] ?? '')
            );
            $excludePath  = (string) ($catalog['path'] ?? '');
            ?>
            <select class="form-select form-select-sm" id="catalog_pid" name="catalog[pid]"
                    data-exclude-path="<?php echo e($excludePath); ?>">
                <option value="0">- 无 -</option>
                <?php foreach ($parentOpts as $row): ?>
                    <option value="<?php echo e($row['id']); ?>"
                        <?php echo $currentPid === (int) $row['id'] ? 'selected' : ''; ?>>
                        <?php echo str_repeat('— ', max(0, (int) $row['level'] - 1)); ?><?php echo e($row['title']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="input-group input-group-sm mt-1">
                <input type="text" class="form-control" id="catalog_pid_search" placeholder="搜索更多上级栏目（输入名称关键词）">
                <button class="btn btn-outline-secondary" type="button" id="catalog_pid_search_btn">搜索</button>
            </div>
            <div class="form-text small text-muted">仅加载匹配的栏目，支持上万级分类；不能选自己及其子级。</div>
        </div>
        <div class="col-md-6">
            <label for="catalog_node_type" class="form-label small">内容模型</label>
            <select class="form-select form-select-sm" id="catalog_node_type" name="catalog[node_type]">
                <?php foreach (NodeType::getNodeTypes() as $key => $row): ?>
                    <option value="<?php echo e($row['type_name']); ?>" <?php echo $row['type_name'] == ($catalog['node_type'] ?? '') ? 'selected' : null; ?>>
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

        <div class="col-md-6" id="node_slug_id">
            <label for="catalog_slug" class="form-label small">别名 <span class="text-muted small">(slug)</span></label>
            <div class="input-group input-group-sm">
                <input type="text" class="form-control form-control-sm font-monospace" id="catalog_slug" name="catalog[slug]"
                       value="<?php echo htmlspecialchars($catalog['slug'] ?? '', ENT_QUOTES); ?>" placeholder="自动生成">
                <button class="btn btn-outline-secondary" type="button" onclick="generateCatalogSlug()" title="根据栏目名称自动生成">
                    <i class="fa fa-magic"></i>
                </button>
            </div>
            <div id="catalog_slug_preview" class="form-text small text-success" style="display:none;">
                <i class="fa fa-link me-1"></i><span></span>
            </div>
        </div>

        <div class="col-md-6">
            <label for="catalog_sort_order" class="form-label small">排序</label>
            <input type="number" class="form-control form-control-sm" id="catalog_sort_order" name="catalog[sort_order]"
                   value="<?php echo e($catalog['sort_order'] ?? 0); ?>" placeholder="数值越小越靠前">
        </div>
    </div>

    <?php if (\zapcms\services\Locale::enabled() && !empty($catalog['id'])): ?>
    <hr class="my-2">
    <div class="row g-2">
        <div class="col-12">
            <label class="form-label small mb-0"><i class="fa fa-language me-1"></i>多语言翻译</label>
            <div class="form-text">留空的前台会自动回退到默认语言（<?php echo e(\zapcms\services\Locale::name(\zapcms\services\Locale::defaultLocale())); ?>）</div>
        </div>
        <?php foreach (\zapcms\services\Locale::all() as $item):
            if ($item['is_default']) { continue; }
            $code   = $item['code'];
            $anchor = str_replace('-', '_', $code);
            $row    = $translations[$code] ?? [];
        ?>
            <div class="col-md-6">
                <label class="form-label small" for="trn_title_<?php echo $anchor; ?>">
                    <?php echo e($item['title']); ?> 栏目名称 <small class="text-muted"><?php echo e($code); ?></small>
                </label>
                <input type="text" class="form-control form-control-sm" id="trn_title_<?php echo $anchor; ?>"
                       name="translation[<?php echo e($code); ?>][title]"
                       value="<?php echo e($row['title'] ?? ''); ?>" placeholder="留空则回退默认语言">
            </div>
            <div class="col-md-6">
                <label class="form-label small" for="trn_slug_<?php echo $anchor; ?>">
                    <?php echo e($item['title']); ?> 别名 <small class="text-muted">(slug)</small>
                </label>
                <input type="text" class="form-control form-control-sm font-monospace" id="trn_slug_<?php echo $anchor; ?>"
                       name="translation[<?php echo e($code); ?>][slug]"
                       value="<?php echo e($row['slug'] ?? ''); ?>" placeholder="留空则沿用默认语言别名">
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</form>

<script>
    // ===================== 别名自动生成 =====================
    var catalogSlugConfig = {
        separator: '<?php echo option('slug.separator', '-'); ?>',
        style: '<?php echo option('slug.style', 'default'); ?>',
        ajaxUrl: '<?php echo url_action('System@ajaxSlug'); ?>'
    };

    function generateCatalogSlug() {
        var title = $('#catalog_title').val().trim();
        if (!title) return;
        var sep = catalogSlugConfig.separator;
        if (catalogSlugConfig.style === 'pinyin' || catalogSlugConfig.style === 'translate') {
            $.getJSON(catalogSlugConfig.ajaxUrl, { title: title }, function (res) {
                if (res.code === 0 && res.slug) {
                    $('#catalog_slug').val(res.slug);
                    updateCatalogSlugPreview();
                }
            });
        } else {
            var slug = title.toLowerCase();
            slug = slug.replace(/[^\w\u4e00-\u9fa5]+/g, sep);
            slug = slug.replace(new RegExp('^' + sep.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '+|' + sep.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '+$', 'g'), '');
            $('#catalog_slug').val(slug);
            updateCatalogSlugPreview();
        }
    }

    function updateCatalogSlugPreview() {
        var slug = $('#catalog_slug').val().trim();
        var preview = $('#catalog_slug_preview');
        if (slug) {
            preview.find('span').text('<?php echo site_url('/'); ?>' + slug + '<?php echo htmlspecialchars((string) @config('config.suffix', '')); ?>');
            preview.show();
        } else {
            preview.hide();
        }
    }

    // ===================== 上级栏目异步搜索（分类上万时不渲染整棵树） =====================
    function searchCatalogParents() {
        var keyword = $('#catalog_pid_search').val().trim();
        var $sel = $('#catalog_pid');
        if (keyword === '') return;
        $.getJSON('<?php echo url_action('Catalog@options'); ?>', {
            keyword: keyword,
            limit: 100,
            exclude_path: $sel.data('exclude-path') || ''
        }, function (resp) {
            if (resp.code !== 0) return;
            (resp.options || []).forEach(function (row) {
                if ($sel.find('option[value="' + row.id + '"]').length) return;
                var indent = '— '.repeat(Math.max(0, (row.level || 1) - 1));
                $('<option>').val(row.id).text(indent + row.title).appendTo($sel);
            });
        });
    }

    $(function () {
        $('#catalog_pid_search_btn').on('click', searchCatalogParents);
        $('#catalog_pid_search').on('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); searchCatalogParents(); }
        });
        $('#catalog_slug').on('input', updateCatalogSlugPreview);
        // 别名留空时，失焦后按栏目名称自动生成
        $('#catalog_title').on('change', function () {
            if (!$('#catalog_slug').val().trim()) {
                generateCatalogSlug();
            }
        });
        updateCatalogSlugPreview();
    });
</script>