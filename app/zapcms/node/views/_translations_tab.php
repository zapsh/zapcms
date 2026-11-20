<?php
/** 多语言翻译 Tab 按钮（Node 表单公用 partial） */

use zapcms\services\Locale;

$langsEnabled = Locale::enabled();
$langsList    = array_values(array_filter(Locale::all(), static fn ($item) => !$item['is_default']));
if (!$langsEnabled || empty($langsList)) {
    return;
}
?>
<li class="nav-item" id="translationTabBtn">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-lang" type="button">
        <i class="fa fa-language me-1"></i>多语言
        <span class="badge text-bg-secondary ms-1"><?php echo count($langsList); ?></span>
    </button>
</li>
