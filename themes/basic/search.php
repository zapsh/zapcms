<?php defined('IN_ZAPCMS') or die('No permission to access');
$this->extend('layout/default');
$__state = pageState();
$__state['isSearch'] = true;
$this->beginBlock('content');

// ── 数据准备 ────────────────────────────────────────────
$keyword = trim((string)($query ?? ''));
$total   = !empty($page) ? (int)$page->total() : count($data_list ?? []);

// 节点类型中文标签
$typeLabels = [
    'article' => __t('front.search_type_article', '文章'),
    'product' => __t('front.search_type_product', '产品'),
    'faq'     => __t('front.search_type_faq', 'FAQ'),
    'catalog' => __t('front.search_type_catalog', '栏目'),
    'page'    => __t('front.search_type_page', '页面'),
];

// 高亮关键词（先转义 HTML 再替换，避免注入）
$highlight = function (string $text, string $keyword): string {
    $safe  = htmlspecialchars($text, ENT_QUOTES);
    $words = preg_split('/\s+/u', trim($keyword), -1, PREG_SPLIT_NO_EMPTY);
    if (!$words) {
        return $safe;
    }
    foreach ($words as $word) {
        $quoted = preg_quote(htmlspecialchars($word, ENT_QUOTES), '/');
        if ($quoted === '') {
            continue;
        }
        $safe = preg_replace('/(' . $quoted . ')/iu', '<mark class="search-hit">$1</mark>', $safe);
    }
    return $safe;
};

// 智能摘要：优先截取关键词附近上下文，避免生硬截断
$summarize = function (string $content, string $keyword, int $length = 160): string {
    $text = trim(preg_replace('/\s+/u', ' ', strip_tags($content)));
    if ($text === '') {
        return '';
    }
    $words = preg_split('/\s+/u', trim($keyword), -1, PREG_SPLIT_NO_EMPTY);
    $pos   = false;
    foreach ($words as $word) {
        $p = mb_stripos($text, $word);
        if ($p !== false) {
            $pos = $p;
            break;
        }
    }
    $prefix = '';
    if ($pos !== false && $pos > 60) {
        $text   = mb_substr($text, $pos - 60);
        $prefix = '…';
    }
    if (mb_strlen($text) > $length) {
        $text = mb_substr($text, 0, $length) . '…';
    }
    return $prefix . $text;
};

// 结果链接：统一使用 smart_node_url（固定链接结构 + link-url 解析）
$link = function (array $node): string {
    if (function_exists('smart_node_url')) {
        return smart_node_url($node);
    }
    return append_url_suffix(site_url('/' . ($node['slug'] ?? '')));
};
?>
    <?php echo $this->partial('partials/_breadcrumb'); ?>
    <div class="container">
        <div class="row">
            <?php echo $this->partial('partials/_sidebar', [
                'node'           => ['title' => __t('front.search', '搜索')],
                'subCatalogList' => category_nav(),
            ]); ?>
            <div class="col-sm-9">
                <div class="content-wrap search-page">
                    <!-- 搜索框 -->
                    <form class="search-form" method="get" action="<?php echo append_url_suffix(site_url('/search')); ?>">
                        <div class="faq-search-wrap">
                            <i class="fa fa-search faq-search-icon"></i>
                            <input type="text" name="q" class="faq-search-input"
                                   placeholder="<?php echo __t('front.search_placeholder', '请输入关键词，如：网站建设、产品名称…'); ?>"
                                   autocomplete="off"
                                   value="<?php echo htmlspecialchars($keyword); ?>">
                            <?php if ($keyword !== ''): ?>
                            <a class="faq-search-clear" href="<?php echo append_url_suffix(site_url('/search')); ?>" title="<?php echo __t('front.search_clear', '清除搜索词'); ?>">&times;</a>
                            <?php endif; ?>
                        </div>
                        <button class="search-btn" type="submit"><i class="fa fa-search"></i> <?php echo __t('front.search', '搜索'); ?></button>
                    </form>

                    <?php if ($keyword === ''): ?>
                        <!-- 空关键词 -->
                        <div class="search-empty">
                            <i class="fa fa-search"></i>
                            <p><?php echo __t('front.search_prompt', '请输入关键词开始搜索'); ?></p>
                        </div>
                    <?php elseif (empty($data_list)): ?>
                        <!-- 无结果 -->
                        <div class="search-empty">
                            <i class="fa fa-frown-o"></i>
                            <p><?php echo __t('front.search_not_found_a', '未找到与'); ?> “<strong><?php echo htmlspecialchars($keyword); ?></strong>” <?php echo __t('front.search_not_found_b', '相关的内容'); ?></p>
                            <p class="search-suggest"><?php echo __t('front.search_suggest', '建议：检查关键词是否输错，或尝试更换更短、更常见的关键词'); ?></p>
                        </div>
                    <?php else: ?>
                        <!-- 结果统计 -->
                        <div class="search-stats">
                            <?php echo __t('front.search_found_a', '共找到'); ?> <strong><?php echo $total; ?></strong> <?php echo __t('front.search_found_b', '条与'); ?>
                            “<strong><?php echo htmlspecialchars($keyword); ?></strong>” <?php echo __t('front.search_found_c', '相关的结果'); ?>
                        </div>

                        <!-- 结果列表 -->
                        <?php foreach ($data_list as $node): ?>
                        <article class="search-result-item">
                            <h4 class="search-result-title">
                                <a href="<?php echo $link($node); ?>"><?php echo $highlight($node['title'] ?? '', $keyword); ?></a>
                            </h4>
                            <div class="search-meta">
                                <span class="search-tag search-tag--<?php echo e($node['node_type'] ?? ''); ?>">
                                    <?php echo e($typeLabels[$node['node_type'] ?? ''] ?? ($node['node_type'] ?? __t('front.content', '内容'))); ?>
                                </span>
                                <?php if (!empty($node['pub_time'])): ?>
                                <span><i class="fa fa-calendar"></i> <?php echo date('Y-m-d', (int)$node['pub_time']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($node['views'])): ?>
                                <span><i class="fa fa-eye"></i> <?php echo (int)$node['views']; ?> <?php echo __t('front.views', '次浏览'); ?></span>
                                <?php endif; ?>
                            </div>
                            <p class="search-result-desc"><?php echo $highlight($summarize($node['content'] ?? '', $keyword), $keyword); ?></p>
                        </article>
                        <?php endforeach; ?>

                        <?php echo $this->partial('partials/_pagination', ['page' => $page ?? null]); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php $this->endBlock(); ?>
