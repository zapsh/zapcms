<?php defined('IN_ZAPCMS') or die('No permission to access'); ?>
<?php
/**
 * 图文列表项：左图右文（catalog 的 list_image / mixed 共用）
 *
 * 版式参考资讯站列表：图片在左，右侧依次为标题、两行摘要，
 * 底部一行左侧日期、右侧标签（无标签时只显示日期）。
 *
 * @var array $node
 */
$nodeData = $node ?? [];
$nodeUrl  = smart_node_url($nodeData);
$imgW = \zapcms\services\NodeType::getConfig($nodeData['node_type'] ?? 'article', 'list_image_width', 270);
$imgH = \zapcms\services\NodeType::getConfig($nodeData['node_type'] ?? 'article', 'list_image_height', 210);
$desc = trim(strip_tags((string)($nodeData['list_excerpt'] ?? $nodeData['description'] ?? $nodeData['excerpt'] ?? '')));
$tags = array_slice(array_values(array_filter((array)($nodeData['tags'] ?? []))), 0, 3);
$pubTime = intval($nodeData['pub_time'] ?? 0);
?>
<article class="zap-article-item">
    <a class="zap-article-thumb" href="<?php echo $nodeUrl; ?>" tabindex="-1" aria-hidden="true">
        <img class="zap-list-thumb" src="<?php echo \zapcms\helpers\ThumbHelper::thumb($nodeData['image'] ?? '', $imgW, $imgH); ?>" alt="<?php echo htmlspecialchars((string)($nodeData['title'] ?? '')); ?>" loading="lazy" />
    </a>
    <div class="zap-article-body">
        <h3 class="zap-article-title">
            <a href="<?php echo $nodeUrl; ?>">
                <?php if (!empty($nodeData['is_top'])): ?><span class="badge bg-warning text-dark me-1 align-middle"><?php echo __t('front.top', '置顶'); ?></span><?php endif; ?>
                <?php echo htmlspecialchars((string)($nodeData['title'] ?? '')); ?>
            </a>
        </h3>
        <?php if ($desc !== ''): ?>
        <p class="zap-article-desc"><?php echo htmlspecialchars(mb_substr($desc, 0, 120)); ?></p>
        <?php endif; ?>
        <div class="zap-article-foot">
            <time class="zap-article-date" datetime="<?php echo $pubTime ? date('Y-m-d', $pubTime) : ''; ?>">
                <i class="fa fa-calendar me-1"></i><?php echo date('Y-m-d', $pubTime); ?>
            </time>
            <?php if ($tags): ?>
            <span class="zap-article-tags">
                <?php foreach ($tags as $tag): ?>
                <span class="zap-article-tag"><?php echo htmlspecialchars((string)$tag); ?></span>
                <?php endforeach; ?>
            </span>
            <?php endif; ?>
        </div>
    </div>
</article>
