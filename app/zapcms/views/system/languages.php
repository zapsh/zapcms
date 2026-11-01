<?php
/**
 * 多语言设置
 *
 * @var array  $locales        [{code,title,native,is_default}]
 * @var string $default_locale
 * @var array  $known_locales  可选语言代码
 * @var bool   $enabled
 */

$knownLocales  = $known_locales ?? [];
$selectedCodes = array_column($locales ?? [], 'code');

// 尚未启用的已知语言
$restLocales = [];
foreach ($knownLocales as $code) {
    if (!in_array($code, $selectedCodes, true)) {
        $restLocales[] = $code;
    }
}

$this->layout('layouts/common');
?>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fa-solid fa-language me-2"></i>启用语言</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">
                        勾选站点支持的语言，并设置默认语言。默认语言的 URL 不带语言前缀（保持现有链接不变），
                        其它语言以前缀形式访问，如 <code>/en/about.html</code>。
                    </p>

                    <form id="languageForm">
                        <div class="row g-2">
                            <?php foreach ($locales as $item): ?>
                                <div class="col-md-4">
                                    <div class="form-check border rounded p-2">
                                        <input class="form-check-input" type="radio" name="default_locale"
                                               id="default_<?php echo e($item['code']); ?>"
                                               value="<?php echo e($item['code']); ?>"
                                               <?php echo $item['is_default'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label w-100" for="default_<?php echo e($item['code']); ?>">
                                            <small class="d-block text-muted">设为默认</small>
                                            <strong><?php echo e($item['title']); ?></strong>
                                            <small class="d-block text-muted"><?php echo e($item['code']); ?></small>
                                        </label>
                                        <input type="hidden" name="locales[]" value="<?php echo e($item['code']); ?>">
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($restLocales !== []): ?>
                            <hr>
                            <label class="form-label fw-semibold">添加语言</label>
                            <div class="row g-2">
                                <?php foreach ($restLocales as $code): ?>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox"
                                                   name="locales[]" value="<?php echo e($code); ?>"
                                                   id="locale_<?php echo e($code); ?>">
                                            <label class="form-check-label" for="locale_<?php echo e($code); ?>">
                                                <?php echo e(\zapcms\services\Locale::name($code)); ?>
                                                <small class="text-muted"><?php echo e($code); ?></small>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <hr>
                        <div class="mb-3">
                            <label for="custom_locale" class="form-label fw-semibold">自定义语言代码</label>
                            <input type="text" class="form-control" id="custom_locale" name="custom_locale"
                                   placeholder="如 pt-BR，留空则不添加">
                            <div class="form-text">支持 BCP-47 写法，如 <code>zh-TW</code>、<code>pt-BR</code></div>
                        </div>
                    </form>

                    <button type="button" id="save-languages" class="btn btn-primary">
                        <i class="fa-solid fa-floppy-disk me-1"></i> 保存设置
                    </button>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fa-solid fa-circle-info me-2"></i>使用说明</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0 small">
                        <li class="mb-2">
                            <i class="fa-solid fa-circle-check text-success me-1"></i>
                            主表 <code>node</code> / <code>catalog</code> 继续保存默认语言内容，
                            其它语言写入翻译子表 <code>translations</code>。
                        </li>
                        <li class="mb-2">
                            <i class="fa-solid fa-language text-info me-1"></i>
                            内容编辑页底部新增「多语言翻译」区块，栏目表单里也有多语言名称输入。
                        </li>
                        <li class="mb-2">
                            <i class="fa-solid fa-link text-info me-1"></i>
                            留空的字段前台会自动回退到默认语言内容，不会出现空白页。
                        </li>
                        <li class="mb-2">
                            <i class="fa-solid fa-sitemap text-info me-1"></i>
                            启用后前台自动输出 <code>hreflang</code> 备用链接，Sitemap 也会带上多语言条目。
                        </li>
                        <li class="mb-2">
                            <i class="fa-solid fa-triangle-exclamation text-danger me-1"></i>
                            只启用一个语言时，多语言相关的前缀与重写逻辑完全关闭，行为与改造前一致。
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var saveBtn = document.getElementById('save-languages');
    if (!saveBtn) {
        return;
    }

    saveBtn.addEventListener('click', function () {
        var btn = this;
        var original = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> 保存中...';

        var form = new FormData(document.getElementById('languageForm'));
        var payload = new URLSearchParams();

        form.getAll('locales[]').forEach(function (value) {
            payload.append('locales[]', value);
        });

        var checked = document.querySelector('input[name="default_locale"]:checked');
        if (checked) {
            payload.append('default_locale', checked.value);
        }

        var custom = document.getElementById('custom_locale');
        if (custom && custom.value.trim() !== '') {
            payload.append('custom_locale', custom.value.trim());
        }

        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: payload.toString()
        })
            .then(function (resp) { return resp.json(); })
            .then(function (data) {
                if (data.code === 0) {
                    window.location.reload();
                } else {
                    alert(data.msg || '保存失败');
                }
            })
            .catch(function () { alert('网络错误，请重试'); })
            .finally(function () {
                btn.disabled = false;
                btn.innerHTML = original;
            });
    });
});
</script>
