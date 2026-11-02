<?php
namespace app;

use ArrayObject;
use zapcms\models\Node;
use zapcms\services\Catalog;
use zapcms\services\Locale;
use zapcms\services\Translator;
use zap\facades\Cache;

defined('IN_ZAPCMS') or die('No permission to access');

/**
 * PageState - 页面状态对象
 *
 * 封装当前页面渲染所需的所有状态数据，统一从控制器传递到视图。
 * 通过 pageState() 全局函数访问单例。
 * 
 * @property bool         $isHome          是否首页
 * @property bool         $isSearch        是否搜索页
 * @property bool         $isCatalog       是否栏目页
 * @property bool         $isNode          是否节点页
 * @property bool         $is404           是否 404 页面
 * @property bool         $isDesignMode    是否设计模式
 * @property bool         $isAdmin         是否后台管理页面
 * @property bool         $isMobile        是否移动端访问
 * @property bool         $isAjax          是否 Ajax 请求
 * @property bool         $isPost          是否 POST 请求
 * @property bool         $isGet           是否 GET 请求
 * @property bool         $isPjax          是否 PJAX 请求
 * @property bool         $isPreview       是否预览模式
 * @property bool         $isPreviewMode   是否预览模式（同上）
 * @property bool         $isPreviewAdmin  是否后台预览模式
 * @property bool         $isPreviewFront  是否前台预览模式
 * @property bool         $isPreviewNode   是否节点预览模式
 * @property bool         $isPreviewCatalog 是否栏目预览模式
 * @property bool         $isPreviewSearch 是否搜索预览模式
 * @property bool         $isPreview404    是否 404 预览模式
 * @property bool         $isPreviewAjax   是否 Ajax 预览模式
 * @property bool         $isPreviewPjax   是否 PJAX 预览模式
 * @property bool         $isPreviewPost   是否 POST 预览模式
 * @property bool         $isPreviewGet    是否 GET 预览模式
 * @property bool         $isPreviewMobile 是否移动端预览模式
 * @property string       $pageTitle       页面标题（SEO title）
 * @property string       $pageKeywords    页面关键词（SEO keywords）
 * @property string       $pageDescription 页面描述（SEO description）
 * @property int          $catalogId       当前栏目 ID
 * @property int          $nodeId          当前节点 ID
 * @property string       $nodeType        当前节点类型
 * @property string       $nodeTypeLabel   节点类型中文标签，如「新闻」「产品」
 * @property array        $node            当前节点完整数据
 * @property string|null  $image           节点封面图
 * @property array[]      $catalogList     栏目列表
 * @property array[]      $subCatalogList  子栏目列表
 * @property array[]      $parentIds       父级 ID 列表（用于面包屑）
 * @property array[]      $latestNews      最新动态列表
 * @property string       $latestNewsUrl   最新动态栏目链接
 * @property string       $latestNewsName  最新动态栏目名称
 * @property bool         $designMode      是否处于设计模式
 * @property string       $theme           当前主题名称
 * @property array        $setting         当前主题配置
 * @property string       $canonicalUrl    当前页面的规范化 URL
 * @property array        $localeUrls      各语言的页面地址（locale => url，用于 hreflang）
 * @property array        $catalogPaths    当前栏目路径数组（用于面包屑）
 * @property string       $nodeMimeType    当前节点的 MIME 类型（如 article、product、page 等）
 * @property string       $nodeMimeTypeLabel 当前节点的 MIME 类型中文标签
 * @property string       $nodeSlug        当前节点的 slug（用于生成 URL）
 * @property string       $nodeUrl         当前节点的 URL
 * @property string       $nodeTitle       当前节点的标题
 * @property string       $nodeDescription 当前节点的描述
 * @property string       $nodeKeywords    当前节点的关键词
 * @property string       $nodeImage       当前节点的封面图
 * @property string       $nodeContent     当前节点的内容
 * @property string       $nodeCreatedAt   当前节点的创建时间
 * @property string       $nodeUpdatedAt   当前节点的更新时间
 * @property string       $nodePublishedAt 当前节点的发布时间
 * @property string       $nodeAuthor      当前节点的作者
 * @property string       $nodeStatus      当前节点的状态
 * @property string       $nodeViews       当前节点的浏览量
 * @property array        $catalog         当前节点所属的栏目数据
 * @property array|string|bool $tags       当前节点的标签（数组或字符串）
 * @property array|string|bool $tag        当前节点的单个标签（数组或字符串）
 *
 * @method static PageState instance() 获取单例
 */
class PageState extends ArrayObject
{
    private static ?PageState $instance = null;

    public function __construct()
    {
        parent::__construct([], ArrayObject::STD_PROP_LIST | ArrayObject::ARRAY_AS_PROPS, 'ArrayIterator');
    }

    /**
     * 重写 offsetGet：键不存在时返回 null，避免 PHP 8 下
     * ArrayObject::offsetGet 触发 "Undefined array key" 警告。
     */
    public function offsetGet(mixed $key): mixed
    {
        return $this->offsetExists($key) ? parent::offsetGet($key) : null;
    }

    /**
     * 获取单例实例（自动创建）
     */
    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 重置单例（用于测试或重新初始化）
     */
    public static function reset(): void
    {
        self::$instance = null;
    }

    // ─── 便捷访问方法 ───────────────────────────────────────

    /**
     * 获取主题配置项
     *
     * @param string $key     配置键，如 'basic_home.slide'
     * @param mixed  $default 默认值
     * @return mixed
     */
    public function themeSetting(string $key, $default = null)
    {
        return $this->setting[$key] ?? $default;
    }

    /**
     * 判断是否有设置项
     */
    public function hasThemeSetting(string $key): bool
    {
        return isset($this->setting[$key]) && !empty($this->setting[$key]);
    }

    /**
     * 是否有子栏目
     */
    public function hasSubCatalogs(): bool
    {
        return !empty($this['subCatalogList']);
    }

    /**
     * 是否有最新动态
     */
    public function hasLatestNews(): bool
    {
        return !empty($this['latestNews']);
    }

    /**
     * 获取最新动态列表（带缓存）
     *
     * @param int $limit 条数
     * @return array|null
     */
    public function getLatestNews(int $limit = 4): ?array
    {
        if (empty($this['latestNews'])) {
            $this['latestNews'] = Cache::get('home_latest_news', function () use ($limit) {
                return Node::where('status', Node::STATUS_PUBLISH)
                    ->where('node_type', 'article')
                    ->limit($limit)
                    ->get(FETCH_ASSOC);
            }, 300);
        }
        return $this['latestNews'];
    }

    /**
     * 获取顶级栏目菜单（带缓存）
     */
    public function getCatalogList(): ?array
    {
        if (!$this['catalogList']) {
            // 缓存里存放未翻译的原始树，翻译在每次请求时叠加，避免跨语言污染缓存
            $tree = Cache::get('top_menu', function () {
                return Catalog::instance()->getTreeArray();
            }, 5000);
            $this['catalogList'] = $this->applyMenuTranslation($tree);
        }
        return $this['catalogList'];
    }

    /**
     * 给栏目菜单树叠加当前语言的译文（递归 children）
     *
     * 先一次性取出整棵树用到的译文，避免逐条查询。
     *
     * @param mixed $rows
     * @return mixed
     */
    protected function applyMenuTranslation($rows)
    {
        if (!is_array($rows) || $rows === []) {
            return $rows;
        }

        $locale = Locale::current();
        if ($locale === Locale::defaultLocale()) {
            return $rows;
        }

        $ids = [];
        $this->collectTranslationIds($rows, $ids);
        if ($ids === []) {
            return $rows;
        }

        $map = Translator::getMap(Translator::TYPE_NODE, array_values($ids), $locale);
        if ($map === []) {
            return $rows;
        }

        return $this->translateTreeRows($rows, $map, $locale);
    }

    /**
     * 递归收集树里的节点 ID
     */
    protected function collectTranslationIds(array $rows, array &$ids): void
    {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = $id;
            }
            if (!empty($row['children']) && is_array($row['children'])) {
                $this->collectTranslationIds($row['children'], $ids);
            }
        }
    }

    /**
     * 按预取的译文映射递归改写树
     */
    protected function translateTreeRows(array $rows, array $map, string $locale): array
    {
        foreach ($rows as $key => $row) {
            if (!is_array($row)) {
                continue;
            }
            $translation = $map[(int) ($row['id'] ?? 0)] ?? null;
            if ($translation !== null) {
                $row = Translator::applyWith($row, $translation, $locale);
            }
            if (!empty($row['children']) && is_array($row['children'])) {
                $row['children'] = $this->translateTreeRows($row['children'], $map, $locale);
            }
            $rows[$key] = $row;
        }

        return $rows;
    }

    /**
     * 获取当前栏目详情
     */
    public function getCatalog($key = null)
    {
        if (!$this['catalog'] && $this['nodeType'] === 'catalog') {
            $this['catalog'] = Catalog::instance()->get($this['nodeId']);
        }
        return $key ? ($this['catalog'][$key] ?? null) : $this['catalog'];
    }

    /**
     * 获取搜索页侧边栏菜单
     */
    public function getSearchSidebarMenu(): array
    {
        $types = ['article', 'product'];
        return Catalog::instance()->getTreeArray([['node_type', 'IN', $types]]);
    }

    /**
     * 第一个子栏目（用于侧边栏标题）
     */
    public function firstSubCatalog(): ?array
    {
        if (!$this->hasSubCatalogs()) {
            return null;
        }
        $list = $this['subCatalogList'];
        return reset($list) ?: null;
    }

    /**
     * 智能节点链接（根据类型自动选择规则）
     */
    public function nodeUrl(array $node): string
    {
        return smart_node_url($node);
    }

    public function setPageTitle(string $title): void
    {
        $this['pageTitle'] = $title;
    }

    // ─── SEO 输出 ──────────────────────────────────────────

    /**
     * 输出 SEO meta 标签 HTML
     */
    public function printMeta(): string
    {
        $html = '';
        if (!empty($this['pageTitle'])) {
            $html .= '<title>' . htmlspecialchars($this['pageTitle']) . '</title>' . "\n";
        }
        if (!empty($this['pageKeywords'])) {
            $html .= '<meta name="keywords" content="' . htmlspecialchars($this['pageKeywords']) . '">' . "\n";
        }
        if (!empty($this['pageDescription'])) {
            $html .= '<meta name="description" content="' . htmlspecialchars($this['pageDescription']) . '">' . "\n";
        }
        if (!empty($this['canonicalUrl'])) {
            $html .= '<link rel="canonical" href="' . htmlspecialchars($this['canonicalUrl']) . '">' . "\n";
        }
        $html .= $this->printAlternateLinks();
        return $html;
    }

    /**
     * 输出多语言 hreflang 备用链接
     *
     * @return string
     */
    public function printAlternateLinks(): string
    {
        $urls = $this['localeUrls'];
        if (empty($urls) || !is_array($urls) || !Locale::enabled()) {
            return '';
        }

        $html = '';
        foreach ($urls as $locale => $url) {
            if ($url === '' || $url === null) {
                continue;
            }
            $html .= '<link rel="alternate" hreflang="' . htmlspecialchars((string) $locale, ENT_QUOTES) . '"'
                . ' href="' . htmlspecialchars((string) $url, ENT_QUOTES) . '">' . "\n";
        }

        // 未匹配到语言时指向的兜底地址
        $default = Locale::defaultLocale();
        if (isset($urls[$default])) {
            $html .= '<link rel="alternate" hreflang="x-default"'
                . ' href="' . htmlspecialchars((string) $urls[$default], ENT_QUOTES) . '">' . "\n";
        }

        return $html;
    }
}

if (!function_exists('pageState')) {
    /**
     * 获取 PageState 单例
     *
     * @return PageState
     */
    function pageState(): PageState
    {
        return PageState::instance();
    }
}
