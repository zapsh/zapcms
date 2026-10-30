<?php
defined('IN_ZAPCMS') or die('No permission to access');
$this->extend('layout/default');
$__state = pageState();
$__state['is404'] = true;
$this->beginBlock('content');
$catalogMenu = pageState()->getCatalogList();
?>
<style>
    .error-404-page {
        padding: 80px 0 100px;
        text-align: center;
    }
    .error-404-code {
        font-size: 180px;
        line-height: 1;
        font-weight: 700;
        letter-spacing: 8px;
        background: linear-gradient(135deg, #1e6bff 0%, #24d4ff 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin: 0;
    }
    .error-404-icon {
        font-size: 40px;
        color: #1e6bff;
        margin-bottom: 20px;
    }
    .error-404-title {
        font-size: 28px;
        color: #333;
        font-weight: 600;
        margin: 0 0 12px;
    }
    .error-404-desc {
        font-size: 16px;
        color: #888;
        margin-bottom: 30px;
    }
    .error-404-search {
        max-width: 480px;
        margin: 0 auto 30px;
    }
    .error-404-search .input-group {
        width: 100%;
    }
    .error-404-actions .btn {
        min-width: 140px;
        margin: 5px 8px;
        padding: 10px 24px;
        font-size: 15px;
    }
    .error-404-actions .btn-back {
        background: linear-gradient(135deg, #1e6bff 0%, #0a3fa8 100%);
        border-color: transparent;
        color: #fff;
    }
    .error-404-actions .btn-back:hover {
        color: #fff;
        opacity: .9;
    }
    .error-404-links {
        margin-top: 45px;
    }
    .error-404-links h4 {
        font-size: 15px;
        color: #999;
        font-weight: 400;
        margin-bottom: 15px;
    }
    .error-404-links a {
        display: inline-block;
        margin: 4px 6px;
        padding: 6px 18px;
        border: 1px solid rgba(30, 107, 255, 0.18);
        border-radius: 20px;
        color: #1e6bff;
        font-size: 14px;
        background: #fff;
    }
    .error-404-links a:hover {
        border-color: #1e6bff;
        color: #0a3fa8;
        background: #eef5ff;
        text-decoration: none;
    }
    @media (max-width: 768px) {
        .error-404-code { font-size: 110px; }
        .error-404-title { font-size: 22px; }
    }
</style>
<main class="site-main">
    <div class="container">
        <div class="error-404-page">
            <div class="error-404-icon"><i class="fa fa-frown-o"></i></div>
            <h1 class="error-404-code">404</h1>
            <h2 class="error-404-title">页面走丢了</h2>
            <p class="error-404-desc">您访问的页面不存在、已被移除或网址有误，请检查后重试。</p>

            <form class="error-404-search" action="<?php echo append_url_suffix(site_url('/search')); ?>" method="get">
                <div class="input-group">
                    <input autocomplete="off" type="text" name="q" class="form-control" placeholder="搜索您想找的内容…">
                    <button class="btn btn-outline-secondary" type="submit"><i class="fa fa-search"></i></button>
                </div>
            </form>

            <div class="error-404-actions">
                <a href="<?php echo home_url(); ?>" class="btn btn-back"><i class="fa fa-home"></i> 返回首页</a>
                <button type="button" class="btn btn-outline-secondary" onclick="history.back()">
                    <i class="fa fa-arrow-left"></i> 返回上一页
                </button>
            </div>

            <?php if (!empty($catalogMenu)): ?>
            <div class="error-404-links">
                <h4>您可能还想访问：</h4>
                <div>
                    <?php foreach ($catalogMenu as $menu): ?>
                    <a href="<?php echo smart_node_url($menu); ?>">
                        <?php echo htmlspecialchars($menu['title']); ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>
<?php $this->endBlock(); ?>
