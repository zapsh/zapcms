<?php
use zap\view\View;

!IS_AJAX && $this->layout('layouts/common');
$this->view->page_title = '主题管理';
/**
 * @var array $themes
 * @var array $website_options
 */
?>

<div class="row mb-3">
    <div class="col-12">
        <h2 class="fs-5 fw-bold mb-0">
            <i class="fa fa-paint-brush me-2 text-info"></i>主题管理
        </h2>
    </div>
</div>

<div id="themeGrid">
    <?php echo View::make('theme._grid', ['themes' => $themes, 'website_options' => $website_options])->fetch(); ?>
</div>

<script>
    function activationTheme(themeName){
        const load = Zap.loading('正在启用主题...');
        $.ajax({
            url:'<?php echo url_action('Theme@activationTheme'); ?>',
            method:'POST',
            dataType:'json',
            data:{theme:themeName},
            success:function(data){
                if(data.code === 0){
                    ZapToast.alert("主题设置成功",{bgColor:bgSuccess});
                    // 服务端返回重新渲染的卡片片段，直接替换容器，效果等同 Vue 的响应式更新
                    if (data.html) {
                        document.getElementById('themeGrid').innerHTML = data.html;
                    } else {
                        Zap.reload();
                    }
                }else{
                    ZapToast.alert("主题设置失败: "+(data.msg||data.message||''),{bgColor:bgDanger});
                }
            },
            error:function(xhr){
                // 服务端 500 时 jQuery 走 error 分支，之前没有处理导致“错误也没弹窗”
                let msg = 'HTTP ' + xhr.status;
                try {
                    const err = JSON.parse(xhr.responseText);
                    msg = err.message || err.msg || msg;
                } catch (e) {
                    if (xhr.responseText) { msg = xhr.responseText.substring(0, 200); }
                }
                ZapToast.alert("主题设置失败: " + msg, {bgColor:bgDanger});
            }
        }).always(function(){ load.dispose(); });
    }
</script>
