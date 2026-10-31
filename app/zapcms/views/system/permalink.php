<?php
$currentStructure = $current_structure ?? '/%postname%/';
$currentCatalogPrefix = (string) ($current_catalog_prefix ?? '');

// 预设的链接结构
$presets = [
    'plain' => [
        'label'    => '朴素型',
        'structure' => '/?p=%post_id%',
        'example'   => rtrim(home_url(), '/') . '/?p=123',
    ],
    'day_name' => [
        'label'    => '日期和名称型',
        'structure' => '/%year%/%monthnum%/%day%/%postname%/',
        'example'   => rtrim(home_url(), '/') . '/2024/01/15/sample-post/',
    ],
    'month_name' => [
        'label'    => '月份和名称型',
        'structure' => '/%year%/%monthnum%/%postname%/',
        'example'   => rtrim(home_url(), '/') . '/2024/01/sample-post/',
    ],
    'numeric' => [
        'label'    => '数字型',
        'structure' => '/archives/%post_id%',
        'example'   => rtrim(home_url(), '/') . '/archives/123',
    ],
    'post_name' => [
        'label'    => '文章名称型',
        'structure' => '/%postname%/',
        'example'   => rtrim(home_url(), '/') . '/sample-post/',
    ],
];

// 判断当前结构匹配哪个预设
$currentPreset = 'custom';
foreach ($presets as $key => $preset) {
    if ($preset['structure'] === $currentStructure) {
        $currentPreset = $key;
        break;
    }
}

// 可用标签
$structureTags = [
    '%year%'      => '发布年份（如 2024）',
    '%monthnum%'  => '发布月份（如 01）',
    '%day%'       => '发布日期（如 31）',
    '%postname%'  => '文章别名（slug）',
    '%post_id%'   => '文章 ID',
    '%node_type%' => '内容类型（article, product 等）',
];

$this->layout('layouts/common');
?>

    <div class="row">
        <div class="col-lg-8">
            <!-- 内容固定链接设置 -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fa-solid fa-link me-2"></i>内容固定链接</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">
                        选择或自定义内容（文章、页面、产品等）的 URL 结构。不同的结构会影响链接的美观性和 SEO 效果。
                    </p>

                    <div class="permalink-options">
                        <!-- 预设结构 -->
                        <?php foreach ($presets as $key => $preset): ?>
                        <div class="form-check permalink-option mb-3">
                            <input class="form-check-input permalink-radio" type="radio"
                                   name="permalink_preset" id="preset_<?php echo e($key); ?>"
                                   value="<?php echo e($key); ?>"
                                   <?php echo $currentPreset === $key ? 'checked' : ''; ?>>
                            <label class="form-check-label w-100" for="preset_<?php echo e($key); ?>">
                                <strong><?php echo htmlspecialchars($preset['label']); ?></strong>
                                <code class="ms-2 text-muted"><?php echo htmlspecialchars($preset['structure']); ?></code>
                                <br>
                                <small class="text-muted">示例：<code class="text-success"><?php echo htmlspecialchars($preset['example']); ?></code></small>
                            </label>
                        </div>
                        <?php endforeach; ?>

                        <!-- 自定义结构 -->
                        <div class="form-check permalink-option mb-3">
                            <input class="form-check-input permalink-radio" type="radio"
                                   name="permalink_preset" id="preset_custom"
                                   value="custom"
                                   <?php echo $currentPreset === 'custom' ? 'checked' : ''; ?>>
                            <label class="form-check-label w-100" for="preset_custom">
                                <strong>自定义结构</strong>
                            </label>
                        </div>

                        <div class="mb-3 ms-4" id="custom_structure_box" style="<?php echo $currentPreset === 'custom' ? '' : 'display:none;'; ?>">
                            <div class="input-group">
                                <span class="input-group-text"><?php echo htmlspecialchars(rtrim(home_url(), '/')); ?></span>
                                <input type="text" class="form-control font-monospace"
                                       id="custom_structure" name="custom_structure"
                                       value="<?php echo htmlspecialchars($currentPreset === 'custom' ? $currentStructure : '/%postname%/'); ?>"
                                       placeholder="/%postname%/">
                            </div>
                            <div class="form-text">请以 <code>/</code> 开头，并使用下方支持的标签</div>
                        </div>

                        <!-- 可用标签 -->
                        <div class="ms-4 mt-3">
                            <p class="small fw-semibold mb-2">可用标签：</p>
                            <div class="row g-2">
                                <?php foreach ($structureTags as $tag => $desc): ?>
                                <div class="col-md-6">
                                    <button type="button" class="btn btn-sm btn-outline-secondary tag-btn w-100 text-start"
                                            data-tag="<?php echo e($tag); ?>">
                                        <code><?php echo e($tag); ?></code>
                                        <small class="d-block text-muted"><?php echo e($desc); ?></small>
                                    </button>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 栏目前缀设置 -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fa-solid fa-folder-tree me-2"></i>栏目前缀</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">
                        设置栏目（Catalog）URL 的前缀，留空则栏目直接以 <code>/slug</code> 形式访问。
                        例如，栏目 "产品中心" 的 slug 为 <code>products</code> 时，完整链接为：
                    </p>

                    <div class="mb-3">
                        <label for="catalog_prefix" class="form-label fw-semibold">栏目前缀</label>
                        <div class="input-group">
                            <span class="input-group-text" id="catalog_prefix_url"><?php echo htmlspecialchars(rtrim(home_url(), '/')); ?>/</span>
                            <input type="text" class="form-control" id="catalog_prefix"
                                   name="catalog_prefix" value="<?php echo htmlspecialchars($currentCatalogPrefix); ?>"
                                   placeholder="留空则直接以 /slug 访问">
                        </div>
                        <div class="form-text">只能使用字母、数字、下划线和连字符；留空则栏目直接以 <code>/slug</code> 访问</div>
                    </div>

                    <div id="catalog_preview" class="alert alert-info mb-0">
                        <i class="fa-solid fa-eye me-1"></i>
                        预览：<code id="catalog_preview_url"><?php echo htmlspecialchars(rtrim(home_url(), '/') . ($currentCatalogPrefix !== '' ? '/' . $currentCatalogPrefix . '/products' : '/products')); ?></code>
                    </div>
                </div>
            </div>

            <!-- 保存按钮 -->
            <div class="mt-3">
                <button type="button" id="save-permalink" class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk me-1"></i> 保存设置
                </button>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- 说明卡片 -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fa-solid fa-circle-info me-2"></i>关于固定链接</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0 small">
                        <li class="mb-2">
                            <i class="fa-solid fa-star text-warning me-1"></i>
                            <strong>推荐使用"文章名称型"</strong>，简洁明了，对 SEO 最友好。
                        </li>
                        <li class="mb-2">
                            <i class="fa-solid fa-triangle-exclamation text-danger me-1"></i>
                            <strong>修改链接结构后</strong>，已有的链接将发生变化，请谨慎操作。
                        </li>
                        <li class="mb-2">
                            <i class="fa-solid fa-lightbulb text-info me-1"></i>
                            如果内容已有 <code>slug</code>（别名），系统会优先使用 slug 生成链接。
                        </li>
                        <li class="mb-2">
                            <i class="fa-solid fa-lightbulb text-info me-1"></i>
                            栏目前缀可以改为 <code>shop</code>、<code>category</code> 等任意名称，留空则栏目直接以 <code>/slug</code> 访问。
                        </li>
                        <li class="mb-2">
                            <i class="fa-solid fa-lightbulb text-info me-1"></i>
                            支持 <code>%node_type%</code> 标签，可以在 URL 中包含内容类型（如 <code>/article/my-post/</code>）。
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 预设结构映射
    var presets = <?php echo json_encode(array_column($presets, 'structure', 'key')); ?>;
    // 重新映射为 key=>structure
    var presetMap = {
        'plain': '<?php echo e($presets['plain']['structure']); ?>',
        'day_name': '<?php echo e($presets['day_name']['structure']); ?>',
        'month_name': '<?php echo e($presets['month_name']['structure']); ?>',
        'numeric': '<?php echo e($presets['numeric']['structure']); ?>',
        'post_name': '<?php echo e($presets['post_name']['structure']); ?>'
    };

    var radios = document.querySelectorAll('.permalink-radio');
    var customBox = document.getElementById('custom_structure_box');
    var customInput = document.getElementById('custom_structure');
    var catalogInput = document.getElementById('catalog_prefix');
    var catalogPreviewUrl = document.getElementById('catalog_preview_url');
    var siteHome = '<?php echo rtrim(home_url(), '/'); ?>';

    // 切换自定义输入框显示
    radios.forEach(function(radio) {
        radio.addEventListener('change', function() {
            if (this.value === 'custom') {
                customBox.style.display = '';
                customInput.focus();
            } else {
                customBox.style.display = 'none';
                // 自动填入预设值
                customInput.value = presetMap[this.value] || '/%postname%/';
            }
        });
    });

    // 点击标签按钮插入到自定义输入框
    document.querySelectorAll('.tag-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var tag = this.getAttribute('data-tag');
            var input = customInput;
            var cursorPos = input.selectionStart;
            var textBefore = input.value.substring(0, cursorPos);
            var textAfter = input.value.substring(cursorPos);
            input.value = textBefore + tag + textAfter;
            input.focus();
            input.setSelectionRange(cursorPos + tag.length, cursorPos + tag.length);
        });
    });

    // 栏目前缀实时预览（允许留空，此时直接以 /slug 访问）
    catalogInput.addEventListener('input', function() {
        var prefix = this.value.replace(/[^a-zA-Z0-9_-]/g, '');
        this.value = prefix;
        catalogPreviewUrl.textContent = siteHome + (prefix ? '/' + prefix + '/products' : '/products');
    });

    // 保存按钮
    document.getElementById('save-permalink').addEventListener('click', function() {
        var btn = this;
        var origText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> 保存中...';

        // 获取当前选中的结构
        var selectedPreset = document.querySelector('.permalink-radio:checked').value;
        var structure = selectedPreset === 'custom'
            ? customInput.value
            : presetMap[selectedPreset];

        // 确保以 / 开头和结尾
        structure = '/' + structure.replace(/^\/+|\/+$/g, '') + '/';

        var catalogPrefix = catalogInput.value.replace(/[^a-zA-Z0-9_-]/g, '');

        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: 'permalink_structure=' + encodeURIComponent(structure) +
                  '&catalog_prefix=' + encodeURIComponent(catalogPrefix)
        })
        .then(function(resp) { return resp.json(); })
        .then(function(data) {
            if (data.code === 0) {
                // 显示成功提示
                showToast('success', '设置已保存');
                // 更新页面上的预览
                catalogPreviewUrl.textContent = siteHome + (catalogPrefix ? '/' + catalogPrefix + '/products' : '/products');
            } else {
                showToast('danger', data.msg || '保存失败');
            }
        })
        .catch(function() {
            showToast('danger', '网络错误，请重试');
        })
        .finally(function() {
            btn.disabled = false;
            btn.innerHTML = origText;
        });
    });

    // Toast 提示
    function showToast(type, message) {
        var toast = document.createElement('div');
        toast.className = 'alert alert-' + type + ' alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3';
        toast.style.zIndex = '9999';
        toast.innerHTML = message +
            '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        document.body.appendChild(toast);
        setTimeout(function() { toast.remove(); }, 3000);
    }
});
</script>

<style>
.permalink-option {
    padding: 12px 16px;
    border: 1px solid var(--bs-border-color);
    border-radius: 6px;
    transition: border-color 0.2s;
}
.permalink-option:hover {
    border-color: #adb5bd;
}
.permalink-option:has(input:checked) {
    border-color: var(--bs-primary);
    background-color: #f0f6ff;
}
.form-check-input:checked {
    background-color: var(--bs-primary);
    border-color: var(--bs-primary);
}
.tag-btn {
    margin-bottom: 4px;
}
</style>


