<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS
 */

register_scripts(base_url('/assets/plugins/zapuploader.js'));
!IS_AJAX && $this->layout('layouts/common');
$this->view->page_title = '主题设置';
?>

<div class="row mb-3">
    <div class="col-12">
        <h2 class="fs-5 fw-bold mb-0">
            <i class="fa fa-sliders-h me-2 text-info"></i>主题设置
        </h2>
    </div>
</div>

<?php
// 当前主题的文件不存在时，自动回退到 basic 主题
$view = theme_path('admin/settings.php');

if (is_file($view)) {
    include $view;
} else{
    echo "<div class='alert alert-danger'>当前主题的设置页面不存在，请检查主题目录下的 admin/settings.php 文件是否存在</div>";
}
?>

<script>
    function saveSettings() {
        const load = Zap.loading('正在保存...');
        $.ajax({
            url: '<?php echo url_action('Theme@saveSettings'); ?>',
            method: 'POST',
            dataType: 'json',
            data: $('#zapForm').serialize(),
            success: function (data) {
                if (data.code === 0) {
                    ZapToast.alert("主题设置成功", {bgColor: bgSuccess});
                    Zap.reload({callback: typeof PageReload === 'undefined' ? function(){} : PageReload});
                } else {
                    ZapToast.alert("主题设置失败: " + data.msg, {bgColor: bgDanger});
                }
            }
        }).always(function(){ load.dispose(); });
    }
</script>
