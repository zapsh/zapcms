<?php
defined('IN_ZAPCMS_ADMIN') or die('No permission');

/**
 * 文章设置
 *
 * 可在此页面添加文章列表页相关的配置项，例如：
 * - 每页显示条数
 * - 摘要长度
 * - 是否显示缩略图
 * - 是否显示日期、作者、阅读量等元数据
 * - 排序方式（按发布时间/更新时间/点击量）
 */

if (req()->isPost()) {
    $settings = themeSettings();
    // TODO: 添加文章相关设置项
    // $settings->set('articlePageSize', intval(req()->post('articlePageSize', 10)));
    $settings->save();
    echo '<div class="alert alert-success alert-dismissible fade show m-3" role="alert">
            <i class="fa-solid fa-circle-check me-1"></i> 设置已保存
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
}
?>

<div class="card card-outline card-success">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fa-solid fa-newspaper me-2"></i>文章设置
        </h5>
    </div>
    <div class="card-body">
        <form method="post" action="<?php echo url_action('theme@settings', ['page' => 'article']); ?>">
            <div class="callout callout-info">
                <h5><i class="fa-solid fa-lightbulb me-1"></i> 提示</h5>
                <p class="mb-0">文章列表的相关配置项将在此处显示。您可以根据需要扩展此页面，添加如每页显示条数、摘要长度、元数据显示控制等选项。</p>
            </div>
        </form>
    </div>
</div>
