<?php

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

<div class="row g-3">
    <?php foreach ($themes as $theme): ?>
    <div class="col-6 col-sm-4 col-lg-3">
        <div class="border rounded bg-white h-100 d-flex flex-column">
            <div class="position-relative">
                <img src="<?php echo base_url("/themes/{$theme['name']}/{$theme['screenshot']}"); ?>"
                     class="w-100 rounded-top" alt="<?php echo $theme['title'] ?? ''; ?>" style="aspect-ratio:16/10;object-fit:cover;">
                <?php if ($website_options['website.theme'] === $theme['dirname']): ?>
                <span class="badge bg-success position-absolute top-0 end-0 m-1">当前</span>
                <?php endif; ?>
            </div>
            <div class="px-2 pt-2 d-flex flex-column flex-fill">
                <h5 class="fs-6 fw-bold mb-1 text-truncate" title="<?php echo $theme['title'] ?? ''; ?>"><?php echo $theme['title'] ?? ''; ?></h5>
                <p class="text-muted small mb-1" style="line-height:1.3;">
                    <?php echo \zap\util\Str::truncate($theme['description'] ?? '', 60); ?>
                </p>
                <small class="text-muted mt-auto mb-2">v<?php echo $theme['version'] ?? ''; ?></small>
            </div>
            <div class="px-2 pb-2 mt-auto">
                <?php if ($website_options['website.theme'] === $theme['dirname']): ?>
                <a href="<?php echo url_action('Theme@settings'); ?>"
                   class="btn btn-outline-info btn-sm w-100">
                    <i class="fa fa-cog me-1"></i>设置
                </a>
                <?php else: ?>
                <button class="btn btn-outline-success btn-sm w-100"
                        onclick="activationTheme('<?php echo $theme['dirname']; ?>')" type="button">
                    <i class="fa fa-check me-1"></i>启用
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
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
                    Zap.reload();
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
