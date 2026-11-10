<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS - All Rights Reserved
 * @author Allen
 * @email zapcms@zap.cn
 * @date 2023/12/27 上午11:09
 * @lastModified 2024/08/04
 *
 */

namespace app;

use app\controllers\ZqueryController;
use Twig\Error\Error as TwigError;
use zapcms\models\Node;
use zapcms\services\Catalog;
use zapcms\services\Locale;
use zapcms\services\Translator;
use zap\DB;
use zap\exception\NotFoundException;
use zap\exception\ViewNotFoundException;
use zap\http\Router;
use zap\view\View;

class Startup
{
    /** @var string 前端控制器命名空间 */
    protected string $namespace = '\app\controllers';

    public Router $router;
    public string $currentUri;
    public string $baseUrl;

    private string $controller;
    private string $method;
    private string $controllerClass;
    private bool $notFound = false;
    private bool $hasParams;

    public function __construct()
    {
        $this->controller = 'index';
        $this->method = 'index';
    }

    /**
     * 注册前台路由并初始化 CMS 环境
     *
     * @param Router $router 路由器实例
     */
    public function handle(?Router $router): void
    {
        $this->router = $router;

        // 计算当前请求 URI（去除查询参数，解码中文等字符）
        // CLI / 定时任务等场景下 $_SERVER 中可能不存在 REQUEST_URI、SCRIPT_NAME，使用空字符串兜底
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $this->currentUri = urldecode(strtok($requestUri, '?') ?: '/');
        $this->baseUrl = $scriptName !== '' ? rtrim(dirname($scriptName), '/') : '';

        // ──── ZQuery 统一查询 API ────
        $this->router->match(['GET', 'POST'], '/api/zquery', function () {
            (new ZqueryController())->index();
        });
        $this->router->get('/api/zquery/meta', function () {
            (new ZqueryController())->meta();
        });

        // ──── 后台管理 API（需鉴权：会话或 Bearer Token，复用 RBAC）────
        $this->router->match(['GET', 'POST'], '/api/admin/{any:.*}', function ($any = '') {
            (new \app\controllers\api\ApiDispatch())->admin($any);
        });
        // ──── 模块 API（是否鉴权由模块自身决定）────
        $this->router->match(['GET', 'POST'], '/api/mod/{any:.*}', function ($any = '') {
            (new \app\controllers\api\ApiDispatch())->module($any);
        });

        $router->setNotFound(function () {
            $this->notFound = true;
            (new \app\controllers\NotFoundController())->index();
        });

        // ──── 注册前台兜底路由（匹配所有未被后台匹配的请求）────
        $this->router->any('/{any:.*}', function ($any = '') {
            $this->dispatchFrontend($any);
        });

        // 同时注册根路径
        $this->router->any('/', function () {
            $this->dispatchFrontend('');
        });
    }

    /**
     * 前台请求分发
     */
    protected function dispatchFrontend(string $path): void
    {
        define('IN_ZAPCMS', true);

        // ──── 插件 / 模块统一引导（前台初始化阶段，注册 Hook 与 boot 脚本）────
        \zapcms\support\PluginLoader::bootAll();

        // ──── 加载站点配置 ────
        $website = get_options('website', 'REGEXP');
        app()->set('options_website', $website);

        // ──── 主题预览（仅管理员，不改激活项）────
        // 通过 ?preview_theme=xxx 进入，持久化到 session（30 分钟内有效），便于在预览站内跳转保持；
        // 传空值（?preview_theme= 或 off）可立即退出预览。游客请求忽略，避免越权预览未启用主题。
        $activeTheme  = $website['website.theme'] ?? 'basic';
        $previewTheme = null;
        if (\zapcms\services\Auth::isLogin()) {
            if (isset($_GET['preview_theme'])) {
                $reqTheme = (string) $_GET['preview_theme'];
                if ($reqTheme === '' || $reqTheme === 'off' || $reqTheme === '0') {
                    session()->remove('preview_theme');
                    session()->remove('preview_theme_ts');
                } else {
                    session()->set('preview_theme', $reqTheme);
                    session()->set('preview_theme_ts', time());
                }
            }
            $sessTheme = (string) session()->get('preview_theme', '');
            $sessTs    = (int) session()->get('preview_theme_ts', 0);
            if ($sessTheme !== '' && (time() - $sessTs) < 1800) {
                if ($sessTheme !== $activeTheme
                    && preg_match('/^[a-z0-9_-]{1,255}$/i', $sessTheme) && is_dir(themes_path($sessTheme))) {
                    $previewTheme = $sessTheme;
                }
            } elseif ($sessTheme !== '') {
                // 预览过期：清理，避免长期残留到正常访问网站
                session()->remove('preview_theme');
                session()->remove('preview_theme_ts');
            }
        }
        $theme = $previewTheme ?? $activeTheme;

        // 暴露当前预览主题名给模板（用于在前台显示“预览中”提示条）
        app()->set('preview_theme_name', $previewTheme);

        // 全局注入“预览中”提示条：插到 <body> 之后（文档流顶部），
        // 不依赖具体主题布局，所有主题（含 basic 等 PHP 模板主题）预览都生效；
        // 仅当响应含 <body> 才注入，避免污染 JSON/API 响应。
        if ($previewTheme !== null) {
            ob_start(function (string $html) use ($previewTheme): string {
                $bar = sprintf(
                    '<div id="zPreviewBar" style="background:linear-gradient(90deg,#ff6a00,#ffb020);color:#fff;font-size:14px;padding:9px 16px;display:flex;align-items:center;gap:12px;">'
                    . '<span><i class="fa fa-eye"></i> 正在预览主题 <strong>%s</strong>（非线上正式主题，仅管理员可见）</span>'
                    . '<a href="?preview_theme=off" style="margin-left:auto;background:rgba(255,255,255,.95);color:#c2410c;padding:5px 14px;border-radius:6px;text-decoration:none;font-weight:600;white-space:nowrap;">退出预览</a>'
                    . '</div>',
                    htmlspecialchars($previewTheme, ENT_QUOTES)
                );
                $out = preg_replace('#<body([^>]*)>#i', '<body$1>' . $bar, $html, 1);
                return is_string($out) ? $out : $html;
            });
        }

        config_set('config.theme', $theme);
        if ($theme !== 'basic') {
            View::paths(themes_path('basic'));
        }

        if (is_file(themes_path("{$theme}/functions.php"))) {
            include themes_path("{$theme}/functions.php");
        }

        // ──── 解析 URL（先剥离语言前缀）────
        $path = $this->stripLocaleFromPath($path);
        $this->parseUrlPath($path);

        if (!isset($this->controllerClass) || $this->notFound) {
            $this->initRoute();
        }

        if (!isset($this->controllerClass) || !class_exists($this->controllerClass)) {
            $this->router->trigger404();
            return;
        }

        // ──── 调用控制器 ────
        try {
            app()->controller = new $this->controllerClass();
            call_user_func_array([app()->controller, 'setParams'], ['params' => $this->router->params]);

            if (method_exists(app()->controller, '_invoke')) {
                call_user_func_array([app()->controller, '_invoke'], [
                    'method' => $this->method,
                    'params' => $this->router->params
                ]);
            } else {
                if (method_exists(app()->controller, $this->method)) {
                    call_user_func_array([app()->controller, $this->method], $this->router->params);
                } else {
                    throw new NotFoundException('not found');
                }
            }
        } catch (NotFoundException $e) {
            if (method_exists(app()->controller, '_notfound')) {
                call_user_func_array([app()->controller, '_notfound'], [
                    'method' => $this->method,
                    'params' => $this->router->params
                ]);
            } else {
                $this->router->trigger404();
            }
        } catch (ViewNotFoundException $e) {
            echo $e->getMessage();
        } catch (\Error $e) {
            echo $e->getMessage();
        } catch (\Throwable $e) {
            http_response_code(500);
            if(req()->isAjax()){
                response(['code' => 1, 'error' => $e->getMessage()])->withJson();
            } else {
                echo '[' . get_class($e) . '] ' . $e->getMessage();
            }
        }
    }

    /**
     * 从 URL 路径解析控制器和方法
     */
    private function parseUrlPath(string $urlPath): void
    {
        $segments = $urlPath === '' ? [] : array_map('urldecode', preg_split('#/#', trim($urlPath, '/'), -1, PREG_SPLIT_NO_EMPTY));

        // ──── 模块前缀路由（配置文件驱动，O(1) 查表，零目录扫描）────
        $prefixes = config('module.prefixes', []);
        if (isset($segments[0], $prefixes[$segments[0]])) {
            $this->dispatchModule($prefixes[$segments[0]], $segments);
            return;
        }

        // ──── 栏目前缀路由：/catalog/xxx 交给 initRoute() 按 slug 解析栏目 ────
        // 栏目前缀可能恰好与控制器同名（如 catalog → CatalogController），
        // 若在此处当作控制器解析，会跳过 initRoute() 导致 pageState 无数据、模板无法渲染。
        // 注意：必须同步设置 router->params，否则 initRoute() 会拿到 /{any:.*} 的原始参数而 404。
        $catalogPrefix = get_catalog_prefix();
        if ($catalogPrefix !== '' && ($segments[0] ?? '') === $catalogPrefix) {
            $this->hasParams = !((count($segments) == 0));
            $this->router->params = array_values($segments);
            return;
        }

        if (isset($segments[0]) && preg_match('/^[a-z]+[-_0-9a-z]+$/i', $segments[0])) {
            $controllerClass = $this->namespace . '\\' . Router::convertToName($segments[0]) . 'Controller';
            if (class_exists($controllerClass)) {
                $this->controllerClass = $controllerClass;
                unset($segments[0]);
            } else {
                $this->notFound = true;
            }
            if (!$this->notFound && isset($segments[1]) && preg_match('/^[a-z][a-z0-9-_]+$/i', $segments[1])) {
                $this->method = lcfirst(Router::convertToName($segments[1]));
                unset($segments[1]);
            }
        }

        $this->hasParams = !((count($segments) == 0));
        $this->router->params = array_values($segments);
    }

    /**
     * 模块前缀路由分发
     *
     * 将前缀 URL 派发给 ModController::_invoke()，由 ModController 统一处理
     * 别名解析、Mod::invoke 入口、控制器查找、视图路径设置。
     *
     *   /{$prefix}/{$controller}/{$action}/{$params...}
     *   → ModController::_invoke($module, [controller, action, params...])
     */
    private function dispatchModule(string $module, array $segments): void
    {
        unset($segments[0]);                            // 去掉 URL 前缀
        // 剩余段原样传给 ModController::_invoke($module, $params)
        $this->controllerClass = '\app\controllers\ModController';
        $this->method          = $module;               // _invoke 的 $module 参数
        $this->router->params  = array_values($segments); // _invoke 的 $params 参数
        $this->hasParams       = !empty($this->router->params);
    }

    /**
    /**
     * 剥离 URL 前面的语言前缀，并设定当前语言
     *
     * 默认语言不带前缀，因此 /xxx 与 /zh-CN/xxx 之外的 / 开头路径都视为默认语言。
     *
     * @param string $path
     * @return string 去掉语言前缀后的路径
     */
    protected function stripLocaleFromPath(string $path): string
    {
        $locale = Locale::fromPath($path);

        if ($locale !== '') {
            Locale::setCurrent($locale);
            $segments = explode('/', trim($path, '/'));
            array_shift($segments);
            return implode('/', $segments);
        }

        // 不带前缀 → 默认语言
        Locale::setCurrent(Locale::defaultLocale());
        return ltrim($path, '/');
    }

    /**
     * 按 slug 解析节点（支持多语言 slug）
     *
     * 优先在当前语言的译文里匹配 slug，匹配不到再回落到主表 slug。
     *
     * @param string $slug
     * @return array|null
     */
    protected function resolveNodeBySlug(string $slug): ?array
    {
        $locale = Locale::current();
        $default = Locale::defaultLocale();

        if ($locale !== $default) {
            $translation = Translator::findBySlug($slug, $locale, Translator::TYPE_NODE);
            if ($translation !== null) {
                $node = Node::where('id', $translation['object_id'])
                    ->where('status', Node::STATUS_PUBLISH)
                    ->fetch(FETCH_ASSOC);
                if ($node) {
                    // 「无译文不显示」策略下无译文返回 null → 路由按未找到处理（404）
                    return Translator::applyDetail($node, $locale, Translator::TYPE_NODE, self::strategyScopeOf($node));
                }
            }
        }

        $node = Node::where('slug', $slug)
            ->where('status', Node::STATUS_PUBLISH)
            ->fetch(FETCH_ASSOC);

        if (!$node) {
            return null;
        }

        return Translator::applyDetail($node, $locale, Translator::TYPE_NODE, self::strategyScopeOf($node));
    }

    /**
     * 节点的无译文策略作用域：栏目节点用栏目策略，其余用内容策略
     */
    private static function strategyScopeOf(array $node): string
    {
        return ($node['node_type'] ?? '') === 'catalog' ? 'catalog' : 'node';
    }

    /**
     * 写入多语言 SEO 数据（canonical + hreflang 备用链接）
     *
     * @param \app\PageState $pageState
     * @param array          $node
     */
    protected function setSeoUrls($pageState, array $node): void
    {
        $pageState->canonicalUrl = smart_node_url($node);
        $pageState->localeUrls   = locale_urls($node);
    }

    /**
     * 按 ID 解析节点（已发布，支持多语言译文）
     *
     * @param int $id
     * @return array|null
     */
    protected function resolveNodeById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $node = Node::where('id', $id)
            ->where('status', Node::STATUS_PUBLISH)
            ->fetch(FETCH_ASSOC);
        return $node ? Translator::applyDetail($node, Locale::current(), Translator::TYPE_NODE, self::strategyScopeOf($node)) : null;
    }

    /**
     * 将固定链接结构编译为路径正则
     *
     * 支持 %year% / %monthnum% / %day% / %node_slug% / %node_id% / %node_type%，
     * 未识别的 %tag% 宽松匹配为任意非 / 段。
     *
     * @param string $structure
     * @return string|null
     */
    private function permalinkToRegex(string $structure): ?string
    {
        $tags = [
            '%year%'      => '(?P<year>\d{4})',
            '%monthnum%'  => '(?P<monthnum>\d{1,2})',
            '%day%'       => '(?P<day>\d{1,2})',
            '%node_slug%'  => '(?P<node_slug>[^/]+)',
            '%node_id%'   => '(?P<node_id>\d+)',
            '%node_type%' => '(?P<node_type>[^/]+)',
        ];
        $regex = preg_quote(rtrim($structure, '/'), '#');
        foreach ($tags as $tag => $pattern) {
            $regex = str_replace($tag, $pattern, $regex);
        }
        $regex = preg_replace('/%[a-z_0-9]+%/', '[^/]+', $regex);
        return '#^' . $regex . '/?$#';
    }

    /**
     * 按固定链接结构解析路径（日期和名称型 / 月份和名称型 / 数字型 / 自定义）
     *
     * - 结构含 %node_id%：按节点 ID 解析
     * - 结构含 %node_slug%：按 slug（末段）解析
     * - 纯 slug 结构与基础型（查询串）返回 null，走默认解析
     *
     * @param array $params URL 路径段
     * @return array|null
     */
    protected function resolveNodeByPermalink(array $params): ?array
    {
        if (!$params) {
            return null;
        }
        $structure = get_permalink_structure();
        if (str_contains($structure, '?') || rtrim($structure, '/') === '/%node_slug%') {
            return null;
        }

        $path  = '/' . implode('/', $params);
        $regex = $this->permalinkToRegex($structure);
        if ($regex === null || !preg_match($regex, $path, $m)) {
            return null;
        }

        if (!empty($m['node_id'])) {
            return $this->resolveNodeById((int) $m['node_id']);
        }
        if (!empty($m['node_slug'])) {
            return $this->resolveNodeBySlug($m['node_slug']);
        }
        return null;
    }

    /**
     * 基础型固定链接：/?n={node_id}
     *
     * 仅在路径为空时生效；若当前结构并非基础型，301 跳转到该节点的规范固定链接。
     *
     * @return array|null
     */
    protected function resolveNodeFromQuery(): ?array
    {
        if ($this->router->params) {
            return null;
        }
        $id = (int) req()->get('n', 0);
        if ($id <= 0) {
            return null;
        }
        $node = $this->resolveNodeById($id);
        if (!$node) {
            return null;
        }
        if (!str_contains(get_permalink_structure(), '?')) {
            // 非基础型结构：旧 ID 链接 301 到规范固定链接，利于 SEO
            header('Location: ' . build_permalink($node), true, 301);
            exit;
        }
        return $node;
    }

    /**
     * 命中节点后的统一处理（栏目附加数据 / SEO / pageState / 控制器分发）
     *
     * @param \app\PageState $pageState
     * @param array          $node
     */
    private function serveNode($pageState, array $node): void
    {
        $isTerm = in_array($node['node_type'], ['category', 'tag'], true);

        // 分类项/标签项（taxonomy term）自身就是 node，直接作为 pageState->catalog 供面包屑/SEO 使用
        if ($isTerm) {
            $pageState->catalog = Translator::apply((array) $node, Locale::current());
        }

        // 多语言 SEO：canonical + hreflang
        $this->setSeoUrls($pageState, $node);

        $pageState->node = $node;
        $pageState->nodeId = $node['id'];
        $pageState->nodeType = $node['node_type'];
        $pageState->nodeMimeType = $node['mime_type'];

        if ($isTerm) {
            // 分类/标签归档页：交给 Catalog 控制器，按 nodeMimeType 过滤内容
            if (!$this->resetRoute('catalog', 'index')) {
                $this->resetRoute('node', $node['node_type']);
            }
        } else if (!$this->resetRoute($node['node_type'], empty($node['mime_type']) ? 'index' : $node['mime_type'])) {
            DB::update('node', ['hits' => DB::raw('hits+1')], ['id' => $node['id']]);
            $this->resetRoute('node', $node['node_type']);
        }
        $pageState->isNode = !$isTerm;
        $pageState->isCatalog = $isTerm;
    }

    /**
     * 初始化 CMS 路由（根据参数确定控制器）
     */
    private function initRoute(): void
    {
        $pageState = pageState();

        // 语言备用链接（hreflang），识别到节点后会被覆盖为更精确的链接
        $pageState->localeUrls = locale_urls([], implode('/', (array) $this->router->params));

        if (($segment = current($this->router->params)) !== false) {
            
            // Sitemap 路由匹配（必须在 switch 外部，避免 PHP switch 宽松比较 bug）
            if (preg_match('/^sitemap([A-Za-z0-9_-]+)?.xml$/i', $segment)) {
                $this->resetRoute('Sitemap', 'generate');
                return;
            }

            switch ($segment) {
                case 'tags':
                    $pageState->tags = true;
                    $this->resetRoute('node', 'tags');
                    return;
                case 'tag':
                    $pageState->tag = true;
                    $this->resetRoute('node', 'tag');
                    return;
            }
        }

        $slug = end($this->router->params);

        // ──── 基础型固定链接：/?n={node_id} ────
        $queryNode = $this->resolveNodeFromQuery();
        if ($queryNode !== null) {
            $this->serveNode($pageState, $queryNode);
            return;
        }

        // ──── 固定链接结构感知解析（日期型 / 数字型 / 自定义），失败回退末段 slug ────
        $node = $this->resolveNodeByPermalink((array) $this->router->params)
            ?? ($slug ? $this->resolveNodeBySlug((string) $slug) : null);

        if ($node) {
            $this->serveNode($pageState, $node);
            return;
        }

        if ($slug) {
            $this->router->trigger404();
        }

        // 首页
        $pageState->isHome = true;
        $this->resetRoute('index', 'index');
    }

    /**
     * 根据控制器名和方法名构建完整的控制器类名
     */
    private function resetRoute(string $controller, string $action): bool
    {
        $this->controller = $controller;
        $this->method = $action;
        $this->controllerClass = $this->namespace . '\\' . Router::convertToName($this->controller) . 'Controller';
        return class_exists($this->controllerClass);
    }
}
