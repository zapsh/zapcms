<?php
/**
 * ZQuery — 统一节点查询类
 *
 * 为前端提供统一的数据查询接口，支持栏目过滤、元数据查询、
 * 分页、排序、搜索等功能，并提供 have_nodes()/the_node() 循环模式。
 *
 * ── 基本用法 ──
 *   $q = new ZQuery(['node_type'=>'article', 'catalog_id'=>3, 'posts_per_page'=>10]);
 *   while($q->have_nodes()){ $q->the_node(); echo $q->title; }
 *
 * ── API 端点用法 ──
 *   POST /api/zquery  { "node_type":"article", "catalog_id":3 }
 */

namespace app;

use zapcms\helpers\ThumbHelper;
use zap\DB;
use zap\db\Query;

class ZQuery
{
    // ═══════════════════════════════════════════════════════════
    //  查询参数
    // ═══════════════════════════════════════════════════════════

    /** @var array 合并后的查询参数 */
    public array $query_vars = [];

    /** @var array 当前页的节点数据 */
    public array $nodes = [];

    /** @var array|null 当前循环中的节点 */
    public $node;

    /** @var int 当前页节点数 */
    public int $node_count = 0;

    /** @var int 符合条件的节点总数 */
    public int $found_nodes = 0;

    /** @var int 最大页数 */
    public int $max_num_pages = 0;

    /** @var int 当前循环索引（-1=未开始） */
    public int $current_node = -1;

    /** @var bool 是否在循环中 */
    public bool $in_the_loop = false;

    /** @var bool 是否是主查询 */
    public bool $is_main_query = false;

    /** @var Query 底层 Query 构建器 */
    protected ?Query $query_builder = null;

    // ═══════════════════════════════════════════════════════════
    //  默认参数
    // ═══════════════════════════════════════════════════════════

    protected array $defaults = [
        'node_type'       => 'article',
        'node_type__in'   => [],
        'node_type__not_in' => [],
        'catalog_id'      => null,      // 栏目ID（查询 node_relation）
        'catalog_id__in'  => [],        // 多个栏目ID
        'posts_per_page'  => 12,
        'paged'           => 1,
        'offset'          => 0,
        'orderby'         => 'date',
        'order'           => 'DESC',
        'status'          => 'publish',
        'status__in'      => [],
        's'               => '',        // 搜索关键词
        'include'         => [],        // 只要这些ID
        'exclude'         => [],        // 排除这些ID
        'slug'            => '',        // 按slug查
        'year'            => null,      // 年份筛选
        'monthnum'        => null,      // 月份筛选
        'day'             => null,      // 日期筛选
        'thumb_only'      => false,     // 只查有封面图的
        'meta_query'      => [],        // 元数据查询
        'no_found_rows'   => false,     // 跳过 COUNT，提升性能
        'ignore_sticky'   => false,
        'fields'          => 'all',     // all | ids | id=>parent
    ];

    // ═══════════════════════════════════════════════════════════
    //  orderby 映射
    // ═══════════════════════════════════════════════════════════

    protected array $orderbyMap = [
        'date'          => 'pub_time',
        'modified'      => 'update_time',
        'title'         => 'title',
        'slug'          => 'slug',
        'id'            => 'id',
        'sort_order'    => 'sort_order',
        'hits'          => 'hits',
        'rand'          => 'RAND()',
        'comment_count' => 'comment_count',
        'none'          => 'id',
    ];

    // ═══════════════════════════════════════════════════════════
    //  构造函数 + 查询执行
    // ═══════════════════════════════════════════════════════════

    /**
     * @param array|string $query 查询参数数组，或字符串 "node_type=article&posts_per_page=10"
     */
    public function __construct($query = [])
    {
        $this->query($query);
    }

    /**
     * 执行查询
     */
    public function query($query = []): void
    {
        if (is_string($query)) {
            parse_str($query, $query);
        }

        $this->query_vars = array_merge($this->defaults, $query);
        $this->reset_loop();

        $this->build_query();
        $this->execute_query();
    }

    /**
     * 构建查询
     */
    protected function build_query(): void
    {
        $qv = &$this->query_vars;

        // ── 使用 node_relation 还是直接查 node ──
        if ($qv['catalog_id'] || !empty($qv['catalog_id__in'])) {
            $this->query_builder = DB::table('node_relation', 'nr')
                ->leftJoin(['node', 'n'], 'nr.node_id=n.id');
            $tableAlias = 'n';
        } else {
            $this->query_builder = DB::table('node');
            $tableAlias = 'node';
        }

        $q = $this->query_builder;

        // ── 栏目过滤 ──
        if ($qv['catalog_id']) {
            $q->where('nr.catalog_id', '=', (int)$qv['catalog_id']);
        }
        if (!empty($qv['catalog_id__in'])) {
            $q->whereIn('nr.catalog_id', array_map('intval', (array)$qv['catalog_id__in']));
        }

        // ── 节点类型 ──
        $nodeTypes = [];
        if (!empty($qv['node_type'])) {
            $nodeTypes = (array)$qv['node_type'];
        }
        if (!empty($qv['node_type__in'])) {
            $nodeTypes = array_unique(array_merge($nodeTypes, (array)$qv['node_type__in']));
        }
        if (!empty($qv['node_type__not_in'])) {
            $q->whereNotIn($tableAlias . '.node_type', (array)$qv['node_type__not_in']);
        }
        if (!empty($nodeTypes)) {
            if (count($nodeTypes) === 1) {
                $q->where($tableAlias . '.node_type', '=', reset($nodeTypes));
            } else {
                $q->whereIn($tableAlias . '.node_type', $nodeTypes);
            }
        }

        // ── 状态 ──
        if ($qv['status'] && empty($qv['status__in'])) {
            $statusList = is_array($qv['status']) ? $qv['status'] : [$qv['status']];
            if (count($statusList) === 1) {
                $q->where($tableAlias . '.status', '=', reset($statusList));
            } else {
                $q->whereIn($tableAlias . '.status', $statusList);
            }
        }
        if (!empty($qv['status__in'])) {
            $q->whereIn($tableAlias . '.status', (array)$qv['status__in']);
        }

        // ── ID 筛选 ──
        if (!empty($qv['include'])) {
            $includes = array_map('intval', (array)$qv['include']);
            if (count($includes) === 1) {
                $q->where($tableAlias . '.id', '=', reset($includes));
            } else {
                $q->whereIn($tableAlias . '.id', $includes);
            }
        }
        if (!empty($qv['exclude'])) {
            $q->whereNotIn($tableAlias . '.id', array_map('intval', (array)$qv['exclude']));
        }

        // ── Slug ──
        if ($qv['slug']) {
            $q->where($tableAlias . '.slug', '=', $qv['slug']);
        }

        // ── 搜索 ──
        if ($qv['s']) {
            $keyword = '%' . addcslashes($qv['s'], '%_') . '%';
            $q->where(function (Query $sub) use ($tableAlias, $keyword) {
                $sub->where($tableAlias . '.title', 'LIKE', $keyword);
                $sub->orWhere($tableAlias . '.content', 'LIKE', $keyword);
                $sub->orWhere($tableAlias . '.excerpt', 'LIKE', $keyword);
            });
        }

        // ── 时间筛选 ──
        if ($qv['year']) {
            $q->where($tableAlias . '.pub_time', '>=', mktime(0, 0, 0, 1, 1, (int)$qv['year']));
            $nextYear = (int)$qv['year'] + 1;
            $q->where($tableAlias . '.pub_time', '<', mktime(0, 0, 0, 1, 1, $nextYear));
        }
        if ($qv['monthnum']) {
            $year = (int)($qv['year'] ?? date('Y'));
            $month = (int)$qv['monthnum'];
            $q->where($tableAlias . '.pub_time', '>=', mktime(0, 0, 0, $month, 1, $year));
            $endMonth = $month === 12 ? 1 : $month + 1;
            $endYear = $month === 12 ? $year + 1 : $year;
            $q->where($tableAlias . '.pub_time', '<', mktime(0, 0, 0, $endMonth, 1, $endYear));
        }

        // ── 封面图筛选 ──
        if ($qv['thumb_only']) {
            $q->where($tableAlias . '.image', '!=', '');
            $q->whereNotNull($tableAlias . '.image');
        }

        // ── 元数据查询 (meta_query) ──
        if (!empty($qv['meta_query'])) {
            $this->apply_meta_query($q, $qv['meta_query'], $tableAlias);
        }

        // ── 统计总数 ──
        if (!$qv['no_found_rows']) {
            $countCol = ($qv['catalog_id'] || !empty($qv['catalog_id__in'])) ? 'n.id' : 'id';
            $this->found_nodes = (clone $q)->count($countCol);
        }

        // ── 排序 ──
        $this->apply_orderby($q, $tableAlias);

        // ── 分页 ──
        $postsPerPage = (int)$qv['posts_per_page'];
        $postsPerPage = max(1, min($postsPerPage, 100));
        $paged = max(1, (int)$qv['paged']);
        $offset = $qv['offset'] ? (int)$qv['offset'] : ($paged - 1) * $postsPerPage;
        $q->limit($postsPerPage);
        $q->offset($offset);

        // ── 最大页数 ──
        if ($postsPerPage > 0 && !$qv['no_found_rows']) {
            $this->max_num_pages = (int)ceil($this->found_nodes / $postsPerPage);
        }
    }

    /**
     * 应用元数据查询 (meta_query)
     */
    protected function apply_meta_query(Query $q, array $metaQuery, string $tableAlias): void
    {
        if (empty($metaQuery)) {
            return;
        }

        $relation = strtoupper($metaQuery['relation'] ?? 'AND');

        $q->where(function (Query $wrapper) use ($metaQuery, $tableAlias, $relation) {
            $clauses = isset($metaQuery[0]) ? $metaQuery : [$metaQuery];

            foreach ($clauses as $i => $clause) {
                if (!is_array($clause)) continue;

                if (isset($clause['relation'])) {
                    $method = $i === 0 ? 'where' : ($relation === 'OR' ? 'orWhere' : 'where');
                    $wrapper->$method(function (Query $sub) use ($clause, $tableAlias) {
                        $this->apply_meta_query($sub, $clause, $tableAlias);
                    });
                    continue;
                }

                $key     = $clause['key']     ?? null;
                $value   = $clause['value']   ?? null;
                $compare = strtoupper($clause['compare'] ?? '=');
                $type    = strtoupper($clause['type']    ?? 'CHAR');

                if (!$key) continue;

                $method = $i === 0 ? 'where' : ($relation === 'OR' ? 'orWhere' : 'where');

                $wrapper->$method(function (Query $sub) use ($key, $value, $compare, $type) {
                    $sub->from('node_meta', 'meta_q');
                    $sub->whereRaw('meta_q.object_id = ' . $this->getMainTableAlias() . '.id');
                    $sub->where('meta_q.meta_name', '=', $key);

                    switch ($compare) {
                        case 'EXISTS':
                        case 'NOT EXISTS':
                            break;
                        case 'IN':
                            $sub->whereIn('meta_q.meta_value', (array)$value);
                            break;
                        case 'NOT IN':
                            $sub->whereNotIn('meta_q.meta_value', (array)$value);
                            break;
                        case 'BETWEEN':
                            $sub->whereBetween('meta_q.meta_value', (array)$value);
                            break;
                        case 'NOT BETWEEN':
                            $sub->where('meta_q.meta_value', '<', $value[0]);
                            $sub->orWhere('meta_q.meta_value', '>', $value[1]);
                            break;
                        case 'LIKE':
                            $sub->where('meta_q.meta_value', 'LIKE', $value);
                            break;
                        case 'NOT LIKE':
                            $sub->where('meta_q.meta_value', 'NOT LIKE', $value);
                            break;
                        case 'REGEXP':
                            $sub->where('meta_q.meta_value', 'REGEXP', $value);
                            break;
                        case '>=':
                        case '>':
                        case '<=':
                        case '<':
                            if ($type === 'NUMERIC') {
                                $sub->where('meta_q.meta_value + 0', $compare, (float)$value);
                            } else {
                                $sub->where('meta_q.meta_value', $compare, $value);
                            }
                            break;
                        case '=':
                        case '!=':
                        default:
                            if ($compare === '!=') {
                                $sub->where('meta_q.meta_value', '!=', $value);
                            } else {
                                $sub->where('meta_q.meta_value', '=', $value);
                            }
                            break;
                    }
                });
            }
        });
    }

    /**
     * 获取当前主表别名
     */
    protected function getMainTableAlias(): string
    {
        $qv = $this->query_vars;
        return ($qv['catalog_id'] || !empty($qv['catalog_id__in'])) ? 'n' : 'node';
    }

    /**
     * 应用排序
     */
    protected function apply_orderby(Query $q, string $tableAlias): void
    {
        $qv = $this->query_vars;
        $order = strtoupper($qv['order']);
        if (!in_array($order, ['ASC', 'DESC'], true)) {
            $order = 'DESC';
        }

        $orderby = $qv['orderby'];

        if (is_string($orderby) && str_contains($orderby, ',')) {
            foreach (explode(',', $orderby) as $part) {
                $part = trim($part);
                if (empty($part)) continue;
                $segments = preg_split('/\s+/', $part);
                $col = $segments[0];
                $dir = strtoupper($segments[1] ?? $order);
                $this->add_orderby_clause($q, $col, $dir, $tableAlias);
            }
            return;
        }

        $this->add_orderby_clause($q, $orderby, $order, $tableAlias);
    }

    /**
     * @param string|array $orderby 排序字段名或 ['field' => 'dir', ...]
     */
    protected function add_orderby_clause(Query $q, $orderby, string $order, string $tableAlias): void
    {
        // meta_value / meta_value_num（子查询排序）
        if ($orderby === 'meta_value' || $orderby === 'meta_value_num') {
            $metaKey = $this->query_vars['meta_key'] ?? '';
            if ($metaKey) {
                $selectExpr = $orderby === 'meta_value_num'
                    ? 'meta_ord.meta_value + 0'
                    : 'meta_ord.meta_value';
                $q->orderByRaw(
                    "(SELECT {$selectExpr} FROM node_meta AS meta_ord WHERE meta_ord.object_id = {$tableAlias}.id AND meta_ord.meta_name = :_zq_mk LIMIT 1) {$order}",
                    ['_zq_mk' => $metaKey]
                );
                return;
            }
        }

        // 普通字段：仅接受白名单中的排序键，未知值一律回退到默认排序。
        // 注意：不能把 $orderby 原样透传给 Query::orderBy()——框架的 quoteColumn()
        // 对 SQLite 是直通的（见 ZPDO::quoteColumn 的 default 分支），MySQL 分支也只是
        // 加反引号而不转义内部的反引号，两者都无法阻止 ORDER BY 注入。
        if (!is_string($orderby) || !array_key_exists($orderby, $this->orderbyMap)) {
            $orderby = 'date';
        }
        $col = $this->orderbyMap[$orderby];

        if ($col === 'RAND()') {
            $q->orderByRaw('RAND()');
        } else {
            $q->orderBy($tableAlias . '.' . $col, $order);
        }
    }

    /**
     * 执行查询并填充结果
     */
    protected function execute_query(): void
    {
        if (!$this->query_builder) {
            return;
        }

        $qv = $this->query_vars;
        $fields = $qv['fields'];
        $tableAlias = $this->getMainTableAlias();

        if ($fields === 'ids') {
            $this->nodes = $this->query_builder->select($tableAlias . '.id')->get(FETCH_COLUMN);
        } elseif ($fields === 'id=>parent') {
            $this->nodes = $this->query_builder->get(FETCH_ASSOC);
        } else {
            if ($qv['catalog_id'] || !empty($qv['catalog_id__in'])) {
                $this->nodes = $this->query_builder->select('n.*')->get(FETCH_ASSOC);
            } else {
                $this->nodes = $this->query_builder->get(FETCH_ASSOC);
            }
        }

        $this->node_count = count($this->nodes);
    }

    // ═══════════════════════════════════════════════════════════
    //  循环方法
    // ═══════════════════════════════════════════════════════════

    /**
     * 是否有更多节点
     */
    public function have_nodes(): bool
    {
        if ($this->current_node + 1 < $this->node_count) {
            return true;
        }
        if ($this->current_node + 1 === $this->node_count && $this->node_count > 0) {
            $this->reset_loop();
        }
        return false;
    }

    /**
     * 推进到下一个节点，自动加载 node_meta
     */
    public function the_node(): void
    {
        $this->in_the_loop = true;
        $this->current_node++;
        $this->node = $this->nodes[$this->current_node] ?? null;

        if ($this->node) {
            $this->load_node_meta();
        }
    }

    /**
     * 为当前节点加载 node_meta（仅首次）
     */
    protected function load_node_meta(): void
    {
        if (!$this->node || !empty($this->node['_meta_loaded'])) {
            return;
        }

        $metaRows = DB::table('node_meta')
            ->where('object_id', '=', (int)$this->node['id'])
            ->get(FETCH_ASSOC);

        foreach ($metaRows as $row) {
            $this->node['meta'][$row['meta_name']] = $row['meta_value'];
        }
        $this->node['_meta_loaded'] = true;
    }

    /**
     * 重置循环
     */
    public function reset_loop(): void
    {
        $this->current_node = -1;
        $this->in_the_loop = false;
        $this->node = null;
    }

    /**
     * 循环指针回到开头
     */
    public function rewind_nodes(): void
    {
        $this->reset_loop();
    }

    /**
     * 重置所有数据
     */
    public function reset_nodedata(): void
    {
        $this->reset_loop();
        $this->nodes = [];
        $this->node_count = 0;
        $this->found_nodes = 0;
        $this->max_num_pages = 0;
        $this->query_builder = null;
    }

    // ═══════════════════════════════════════════════════════════
    //  ID
    // ═══════════════════════════════════════════════════════════

    public function get_the_ID(): int
    {
        return (int)($this->node['id'] ?? 0);
    }

    public function the_ID(): void
    {
        echo $this->get_the_ID();
    }

    // ═══════════════════════════════════════════════════════════
    //  标题
    // ═══════════════════════════════════════════════════════════

    public function get_the_title(): string
    {
        return $this->node['title'] ?? '';
    }

    public function the_title(): void
    {
        echo htmlspecialchars($this->get_the_title());
    }

    // ═══════════════════════════════════════════════════════════
    //  正文
    // ═══════════════════════════════════════════════════════════

    public function get_the_content(): string
    {
        return $this->node['content'] ?? '';
    }

    public function the_content(): void
    {
        echo $this->get_the_content();
    }

    // ═══════════════════════════════════════════════════════════
    //  摘要
    // ═══════════════════════════════════════════════════════════

    public function get_the_excerpt(int $length = 200): string
    {
        $text = $this->node['excerpt'] ?? $this->node['content'] ?? '';
        $text = strip_tags($text);
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        return mb_substr($text, 0, $length) . '...';
    }

    public function the_excerpt(int $length = 200): void
    {
        echo htmlspecialchars($this->get_the_excerpt($length));
    }

    // ═══════════════════════════════════════════════════════════
    //  链接（使用固定链接结构）
    // ═══════════════════════════════════════════════════════════

    public function get_permalink(): string
    {
        if (!$this->node) return '';
        return build_permalink($this->node);
    }

    public function the_permalink(): void
    {
        echo $this->get_permalink();
    }

    // ═══════════════════════════════════════════════════════════
    //  时间
    // ═══════════════════════════════════════════════════════════

    public function get_the_date(string $format = 'Y-m-d'): string
    {
        $ts = $this->node['pub_time'] ?? ($this->node['add_time'] ?? 0);
        return $ts ? date($format, (int)$ts) : '';
    }

    public function the_date(string $format = 'Y-m-d'): void
    {
        echo $this->get_the_date($format);
    }

    public function get_the_time(string $format = 'H:i:s'): string
    {
        return $this->get_the_date($format);
    }

    public function the_time(string $format = 'H:i:s'): void
    {
        echo $this->get_the_time($format);
    }

    public function get_the_modified_date(string $format = 'Y-m-d'): string
    {
        $ts = $this->node['update_time'] ?? 0;
        return $ts ? date($format, (int)$ts) : '';
    }

    public function the_modified_date(string $format = 'Y-m-d'): void
    {
        echo $this->get_the_modified_date($format);
    }

    // ═══════════════════════════════════════════════════════════
    //  作者
    // ═══════════════════════════════════════════════════════════

    public function get_the_author_id(): int
    {
        return (int)($this->node['author_id'] ?? 0);
    }

    public function get_the_author(): string
    {
        $authorId = $this->get_the_author_id();
        if (!$authorId) return '';

        static $authors = [];
        if (!isset($authors[$authorId])) {
            $authors[$authorId] = DB::table('user')
                ->select('name')
                ->where('id', '=', $authorId)
                ->value('name') ?? '';
        }
        return $authors[$authorId];
    }

    public function the_author(): void
    {
        echo htmlspecialchars($this->get_the_author());
    }

    // ═══════════════════════════════════════════════════════════
    //  缩略图
    // ═══════════════════════════════════════════════════════════

    public function get_the_thumbnail(int $width = 300, int $height = 200): string
    {
        return ThumbHelper::thumb($this->node['image'] ?? '', $width, $height);
    }

    public function has_thumbnail(): bool
    {
        return !empty($this->node['image']);
    }

    public function the_thumbnail(int $width = 300, int $height = 200, string $alt = '', string $class = 'img-responsive'): void
    {
        $src = $this->get_the_thumbnail($width, $height);
        if ($src) {
            $alt = $alt ?: htmlspecialchars($this->node['title'] ?? '');
            echo '<img src="' . $src . '" alt="' . $alt . '" class="' . $class . '" loading="lazy">';
        }
    }

    // ═══════════════════════════════════════════════════════════
    //  元数据 (node_meta)
    // ═══════════════════════════════════════════════════════════

    public function get_node_meta(string $key, $default = null)
    {
        return $this->node['meta'][$key] ?? $default;
    }

    public function the_meta(string $key): void
    {
        echo htmlspecialchars((string)$this->get_node_meta($key, ''));
    }

    public function has_meta(string $key): bool
    {
        return isset($this->node['meta'][$key]);
    }

    public function get_all_meta(): array
    {
        return $this->node['meta'] ?? [];
    }

    // ═══════════════════════════════════════════════════════════
    //  栏目 / 分类
    // ═══════════════════════════════════════════════════════════

    /**
     * 获取当前节点所属栏目列表
     * @return array [['catalog_id' => 1, 'catalog_name' => '新闻', 'slug' => 'news'], ...]
     */
    public function get_the_catalogs(): array
    {
        static $cache = [];
        $nodeId = $this->get_the_ID();
        if (!$nodeId) return [];

        $cid = 'cat_' . $nodeId;
        if (!isset($cache[$cid])) {
            $cache[$cid] = DB::table('node_relation', 'nr')
                ->leftJoin(['catalog', 'c'], 'nr.catalog_id=c.id')
                ->where('nr.node_id', '=', $nodeId)
                ->select('c.id AS catalog_id', 'c.title AS catalog_name', 'c.slug')
                ->get(FETCH_ASSOC);
        }
        return $cache[$cid];
    }

    public function the_catalogs(string $sep = ', '): void
    {
        $cats = $this->get_the_catalogs();
        $links = [];
        $catalogPrefix = get_catalog_prefix();
        foreach ($cats as $cat) {
            $slug = $cat['slug'] ?? '';
            $url = $slug ? append_url_suffix(site_url($catalogPrefix . '/' . $slug)) : '';
            $name = htmlspecialchars($cat['catalog_name'] ?? '');
            $links[] = '<a href="' . $url . '">' . $name . '</a>';
        }
        echo implode($sep, $links);
    }

    // ═══════════════════════════════════════════════════════════
    //  关键词 / 标签
    // ═══════════════════════════════════════════════════════════

    public function get_the_tags(): array
    {
        $keywords = $this->node['keywords'] ?? '';
        if (empty(trim($keywords))) return [];
        return array_map('trim', explode(',', $keywords));
    }

    public function the_tags(string $before = '', string $sep = ', ', string $after = ''): void
    {
        $tags = $this->get_the_tags();
        if (empty($tags)) return;
        $html = array_map('htmlspecialchars', $tags);
        echo $before . implode($sep, $html) . $after;
    }

    // ═══════════════════════════════════════════════════════════
    //  状态判断
    // ═══════════════════════════════════════════════════════════

    public function is_published(): bool
    {
        return ($this->node['status'] ?? '') === 'publish';
    }

    public function get_node_type(): string
    {
        return $this->node['node_type'] ?? '';
    }

    public function comments_open(): bool
    {
        return ($this->node['comment_status'] ?? 'open') === 'open';
    }

    public function get_comment_count(): int
    {
        return (int)($this->node['comment_count'] ?? 0);
    }

    // ═══════════════════════════════════════════════════════════
    //  访问量
    // ═══════════════════════════════════════════════════════════

    public function get_hits(): int
    {
        return (int)($this->node['hits'] ?? 0);
    }

    // ═══════════════════════════════════════════════════════════
    //  魔术方法
    // ═══════════════════════════════════════════════════════════

    public function __get(string $name)
    {
        if ($this->node && isset($this->node[$name])) {
            return $this->node[$name];
        }
        if (property_exists($this, $name)) {
            return $this->$name;
        }
        return null;
    }

    public function __isset(string $name): bool
    {
        return ($this->node && isset($this->node[$name]))
            || property_exists($this, $name);
    }

    // ═══════════════════════════════════════════════════════════
    //  结果集访问
    // ═══════════════════════════════════════════════════════════

    public function get_nodes(): array
    {
        return $this->nodes;
    }

    public function found_nodes(): int
    {
        return $this->found_nodes;
    }

    public function max_num_pages(): int
    {
        return $this->max_num_pages;
    }

    public function current_page(): int
    {
        return max(1, (int)$this->query_vars['paged']);
    }

    public function has_next_page(): bool
    {
        return $this->current_page() < $this->max_num_pages;
    }

    public function has_prev_page(): bool
    {
        return $this->current_page() > 1;
    }

    // ═══════════════════════════════════════════════════════════
    //  分页
    // ═══════════════════════════════════════════════════════════

    /**
     * 分页 HTML
     */
    public function pagination(array $options = []): string
    {
        if ($this->max_num_pages <= 1) {
            return '';
        }

        $current  = $this->current_page();
        $total    = $this->max_num_pages;
        $baseUrl  = $options['base_url'] ?? strtok($_SERVER['REQUEST_URI'], '?');
        $prevText = $options['prev_text'] ?? '&laquo; 上一页';
        $nextText = $options['next_text'] ?? '下一页 &raquo;';
        $showAll  = $options['show_all'] ?? false;
        $endSize  = $options['end_size'] ?? 1;
        $midSize  = $options['mid_size'] ?? 2;

        $queryParams = $_GET;
        unset($queryParams['page']);

        $buildUrl = function ($page) use ($baseUrl, $queryParams) {
            $sep = str_contains($baseUrl, '?') ? '&' : '?';
            $queryParams['page'] = $page;
            return $baseUrl . ($queryParams ? $sep . http_build_query($queryParams) : '');
        };

        $html = '<nav aria-label="分页导航"><ul class="pagination">';

        if ($current > 1) {
            $html .= '<li><a href="' . $buildUrl($current - 1) . '" aria-label="上一页">' . $prevText . '</a></li>';
        } else {
            $html .= '<li class="disabled"><span aria-hidden="true">' . $prevText . '</span></li>';
        }

        $pages = $this->get_paginate_links($current, $total, $showAll, $endSize, $midSize);
        foreach ($pages as $page) {
            if ($page === '...') {
                $html .= '<li class="disabled"><span>...</span></li>';
            } elseif ($page == $current) {
                $html .= '<li class="active"><span>' . $page . ' <span class="sr-only">(current)</span></span></li>';
            } else {
                $html .= '<li><a href="' . $buildUrl($page) . '">' . $page . '</a></li>';
            }
        }

        if ($current < $total) {
            $html .= '<li><a href="' . $buildUrl($current + 1) . '" aria-label="下一页">' . $nextText . '</a></li>';
        } else {
            $html .= '<li class="disabled"><span aria-hidden="true">' . $nextText . '</span></li>';
        }

        $html .= '</ul></nav>';
        return $html;
    }

    protected function get_paginate_links(int $current, int $total, bool $showAll, int $endSize, int $midSize): array
    {
        $pages = [];

        if ($showAll || $total <= ($endSize * 2 + $midSize * 2 + 5)) {
            for ($i = 1; $i <= $total; $i++) {
                $pages[] = $i;
            }
            return $pages;
        }

        for ($i = 1; $i <= $endSize; $i++) {
            $pages[] = $i;
        }

        if ($current - $midSize - $endSize > 1) {
            $pages[] = '...';
        }

        $start = max($endSize + 1, $current - $midSize);
        $end   = min($total - $endSize, $current + $midSize);
        for ($i = $start; $i <= $end; $i++) {
            $pages[] = $i;
        }

        if ($current + $midSize + $endSize < $total) {
            $pages[] = '...';
        }

        for ($i = $total - $endSize + 1; $i <= $total; $i++) {
            $pages[] = $i;
        }

        return array_unique($pages);
    }

    // ═══════════════════════════════════════════════════════════
    //  JSON 序列化（API 使用）
    // ═══════════════════════════════════════════════════════════

    public function to_array(): array
    {
        return [
            'nodes'         => $this->nodes,
            'node_count'    => $this->node_count,
            'found_nodes'   => $this->found_nodes,
            'max_num_pages' => $this->max_num_pages,
            'current_page'  => $this->current_page(),
            'query_vars'    => $this->query_vars,
        ];
    }
}
