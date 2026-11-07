<?php

use zapcms\models\Catalog;
use zapcms\models\Node;

/**
 * 获取模块路径
 */
function mod_path(string $mod_name): string
{
    return base_path('mods/' . $mod_name);
}

/**
 * 缓存运行参数快照文件。
 *
 * 「缓存怎么初始化」的配置本身不能存在 Cache facade 里 —— 读它需要先有驱动实例，
 * 形成自举。因此单独用一个 PHP 数组文件做快照：首次请求查 options 表并写入，
 * 之后直接 include，不再访问数据库。后台保存 cache.* 时删掉快照即可重建。
 * 文件以 .php 结尾且只 return 数组，即使被直接访问也不会输出内容。
 */
function app_cache_config_file(): string
{
    return APP_ROOT . '/var/cache_config.php';
}

/**
 * 删除快照，强制下次请求重新从 options 表读取。
 */
function app_cache_config_forget(): void
{
    $file = app_cache_config_file();
    if (is_file($file)) {
        @unlink($file);
    }
}

/**
 * 读取缓存运行参数（必须在任何 Cache:: 调用之前执行）。
 *
 * 原 config/cache.php 的默认值已内联在此，数据库 options 表是唯一权威来源。
 * 返回平铺的 cache.* 键 => 字符串值，并做合法性收敛。
 */
function app_cache_config(): array
{
    $defaults = [
        'default'        => 'file',
        'status'         => 'disabled',
        'ttl'            => '10000',
        'redis_host'     => '127.0.0.1',
        'redis_port'     => '6379',
        'redis_password' => '',
        'redis_database' => '0',
        'redis_prefix'   => 'zapcms:',
    ];

    $file = app_cache_config_file();
    if (is_file($file)) {
        $cached = @include $file;
        if (is_array($cached)) {
            return array_merge($defaults, $cached);
        }
    }

    // 首次（或快照被清除后）：从 options 表读取
    try {
        $rows = \zapcms\services\Option::getArray('cache', 'REGEXP');
    } catch (\Throwable $e) {
        // 数据库不可用：用默认值，且不写快照，避免把异常状态固化下来
        return $defaults;
    }

    $config = $defaults;
    foreach ($rows as $name => $value) {
        $key = (string)$name;
        if (strncmp($key, 'cache.', 6) === 0 && is_scalar($value)) {
            $config[substr($key, 6)] = (string)$value;
        }
    }
    $config['status']  = in_array($config['status'], ['enabled', 'disabled'], true) ? $config['status'] : 'disabled';
    $config['default'] = in_array($config['default'], ['file', 'redis'], true) ? $config['default'] : 'file';
    $config['ttl']     = (string)max(0, (int)$config['ttl']);

    // 原子写快照；写失败不影响本次请求，只退化为每次查库
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $tmp = $file . '.tmp.' . getmypid();
    if (@file_put_contents($tmp, "<?php\nreturn " . var_export($config, true) . ";\n", LOCK_EX) !== false) {
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
        }
    }

    return $config;
}

/**
 * 获取单个配置项（带缓存）
 *
 * @param string $option_name
 * @param mixed  $default
 * @param int|null $ttl
 * @return mixed
 */
function get_option(string $option_name, $default = null, ?int $ttl = null)
{
    return \zap\facades\Cache::get($option_name, function () use ($option_name, $default) {
        return \zapcms\services\Option::get($option_name, $default);
    }, $ttl);
}

/**
 * 批量获取配置项（带缓存）
 *
 * @param string|string[] $option_name 配置名或数组
 * @param string          $type        匹配类型 (REGEXP / LIKE / =)
 * @param int|null        $ttl         缓存秒数，默认取配置 cache.ttl（<=0 或未配置时为 10000）
 * @return array
 */
function get_options($option_name, string $type = '=', ?int $ttl = null): array
{
    if ($ttl === null) {
        $ttl = (int)config('cache.ttl', 0);
        if ($ttl <= 0) {
            $ttl = 10000;
        }
    }
    if (is_array($option_name)) {
        $cacheKey = '_opts_' . md5(serialize($option_name));
        return \zap\facades\Cache::get($cacheKey, function () use ($option_name, $type) {
            $result = [];
            foreach ($option_name as $name) {
                $data = \zapcms\services\Option::getArray($name, $type);
                $result = array_merge($result, $data);
            }
            return $result;
        }, $ttl);
    }
    return \zap\facades\Cache::get('_opts_' . $option_name, function () use ($option_name, $type) {
        return \zapcms\services\Option::getArray($option_name, $type);
    }, $ttl);
}

/**
 * 按点号分隔名取配置值（自动缓存整个分组）
 *
 * @param string $name    配置名，如 'website.title'
 * @param mixed  $default 默认值
 * @return mixed
 *
 * @example option('website.title') → 取 options_website 分组下的 website.title
 */
function option(string $name, $default = null)
{
    $group = strstr($name, '.', true) ?: $name;
    $cacheKey = 'options_' . $group;

    if (!app()->has($cacheKey)) {
        app()->set($cacheKey, get_options($group, 'REGEXP'));
    }

    $options = app()->get($cacheKey);
    return $options[$name] ?? $default;
}

/**
 * 获取 JSON 格式的配置值
 *
 * @param string    $name
 * @param mixed     $default
 * @param bool|null $associative json_decode 的第二个参数
 * @return mixed
 */
function option_get_json(string $name, $default = null, ?bool $associative = null)
{
    $raw = option($name);
    if ($raw === null) {
        return $default;
    }
    $value = json_decode((string) $raw, $associative);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $value = json_decode(stripslashes((string) $raw), $associative);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $default;
        }
    }
    return $value;
}

/**
 * 读取「扁平化数组选项」：以 base.* 为前缀检索 options 表，
 * 再把点分式后缀（如 0.img_path）还原成嵌套数组返回（相当于 array_undot）。
 *
 * 在 options 表中扁平定义：
 *   basic_home.slide.0.img_path = "/themes/basic/img/banner1.png"
 *   basic_home.slide.0.link     = "https://zap.cn"
 *   basic_home.slide.1.img_path = "/themes/basic/img/banner2.png"
 *
 * 调用 option_get_array('basic_home.slide') 返回：
 *   [ 0 => ['img_path' => '...', 'link' => '...'], 1 => ['img_path' => '...'] ]
 *
 * 用字面前缀过滤，避免与 basic_home.slideshow 等相近键混淆；
 * 结果按 _opts_arr_<md5(base)> 缓存（写入请用 option_set_array 自动失效）。
 *
 * @param string $base    数组基名，如 'basic_home.slide'
 * @param mixed  $default 无匹配数据时返回
 * @return array
 */
function option_get_array(string $base, $default = [])
{
    $cacheKey = '_opts_arr_' . md5($base);

    return \zap\facades\Cache::get($cacheKey, function () use ($base, $default) {
        $rows   = \zapcms\services\Option::getArray($base, 'REGEXP');
        $prefix = $base . '.';
        $len    = strlen($prefix);
        $result = [];

        foreach ($rows as $optionName => $value) {
            if (strncmp((string)$optionName, $prefix, $len) !== 0) {
                continue;
            }
            $suffix = substr((string)$optionName, $len);
            if ($suffix === '' || $suffix === false) {
                continue;
            }
            $keys = explode('.', $suffix);
            $ref  = &$result;
            foreach ($keys as $k) {
                if (!is_array($ref)) {
                    $ref = [];
                }
                $ref = &$ref[$k];
            }
            $ref = $value;
            unset($ref);
        }

        return $result === [] ? $default : $result;
    });
}

/**
 * 写入「扁平化数组选项」：将嵌套数组按点分键展开写入 options 表，
 * 并清理相关缓存（数组缓存 + 所在分组的 options_<group> 缓存）。
 *
 * 调用 option_set_array('basic_home.slide', $slides) 会把：
 *   [ 0 => ['img_path' => 'a', 'link' => 'b'] ]
 * 展平成 basic_home.slide.0.img_path / basic_home.slide.0.link 写入。
 *
 * 注意：本函数只“增量写入”，不会删除旧子键。若数组结构收缩
 * （如从 3 项变 2 项），请先调用 option_del_array($base) 清理残留子键再写入。
 *
 * @param string $base     数组基名
 * @param array  $data     嵌套数组
 * @param int    $autoload 是否随系统自动加载
 */
function option_set_array(string $base, array $data, ?int $autoload = 1): void
{
    $flat  = [];
    $stack = [[$base, $data]];

    while ($stack) {
        [$path, $node] = array_pop($stack);
        foreach ($node as $k => $v) {
            $key = $path . '.' . $k;
            if (is_array($v)) {
                $stack[] = [$key, $v];
            } else {
                $flat[$key] = (string)$v;
            }
        }
    }

    foreach ($flat as $name => $value) {
        \zapcms\services\Option::add($name, $value, 0, $autoload);
    }

    \zap\facades\Cache::delete('_opts_arr_' . md5($base));
    $group = strstr($base, '.', true) ?: $base;
    \zap\facades\Cache::delete('_opts_' . $group);
    \zap\facades\Cache::delete('options_' . $group);
}

/**
 * 删除「扁平化数组选项」：删除以 base.* 为前缀的所有子键，并清理相关缓存。
 * 数组结构收缩前应先调用本函数清理残留（如从 3 项变 2 项时删掉 2.*）。
 *
 * 仅删除展开后的子键，不会动可能单独存在的 base 标量键。
 *
 * @param string $base 数组基名，如 'basic_home.slide'
 */
function option_del_array(string $base): void
{
    $names  = \zapcms\services\Option::getKeys($base, 'REGEXP');
    $prefix = $base . '.';
    $len    = strlen($prefix);

    foreach ($names as $name) {
        if (strncmp((string)$name, $prefix, $len) === 0) {
            \zapcms\services\Option::remove($name);
        }
    }

    \zap\facades\Cache::delete('_opts_arr_' . md5($base));
    $group = strstr($base, '.', true) ?: $base;
    \zap\facades\Cache::delete('_opts_' . $group);
    \zap\facades\Cache::delete('options_' . $group);
}

/**
 * 获取序列化格式的配置值
 *
 * @param string $name
 * @param mixed  $default
 * @return mixed
 */
function option_get_unserialize(string $name, $default = null)
{
    $raw = option($name);
    if ($raw === null) {
        return $default;
    }
    // unserialize 失败返回 false，但 false 也可能是合法值，需二次确认
    $value = unserialize((string) $raw);
    if ($value === false && $raw !== serialize(false)) {
        return $default;
    }
    return $value;
}

/**
 * 页面级状态存储实例
 */
function pageState(): \app\PageState
{
    if (!app()->has('page_state')) {
        app()->page_state = new \app\PageState();
    }
    return app()->page_state;
}

/**
 * 构建绝对 URL（支持变长参数拼接）
 *
 * @param string|array ...$args 路径片段
 * @return string
 *
 * @example url_slug('news', '2024', 'title') → /news/2024/title
 */
function url_slug(...$args): string
{
    $uri = [];
    foreach ($args as $arg) {
        if (empty($arg)) {
            continue;
        }
        $uri[] = is_array($arg) ? implode('/', $arg) : $arg;
    }
    return base_url('/' . implode('/', $uri));
}

/**
 * 获取站点基础地址
 *
 * 优先级：后台「网站网址」(website.url) → config('config.site_url') → 当前请求根路径
 *
 * @return string 不带尾部斜杠，如 https://example.com 或 /zapcms
 */
function get_site_base_url(): string
{
    $site = trim((string)option('website.url', ''));
    if ($site !== '') {
        return rtrim($site, '/');
    }
    $cfg = trim((string)config('config.site_url', ''));
    if ($cfg !== '') {
        return rtrim($cfg, '/');
    }
    // Web 环境下从当前请求自动推导完整域名（协议 + 域名 + 子目录）
    if (PHP_SAPI !== 'cli' && !empty($_SERVER['HTTP_HOST'])) {
        $https  = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? 0) == 443)
            || (($_SERVER['REQUEST_SCHEME'] ?? '') === 'https');
        $scheme = $https ? 'https' : 'http';
        $dir    = rtrim((string)base_url(), '/');
        return $scheme . '://' . $_SERVER['HTTP_HOST'] . $dir;
    }
    return rtrim((string)base_url(), '/');
}

/**
 * 站点 URL（优先使用后台「网站网址」配置）
 */
function site_url(string $path = ''): string
{
    return get_site_base_url() . '/' . ltrim($path, '/');
}

/**
 * 站点首页地址
 *
 * @param string|null $locale 指定语言，默认取当前语言
 */
function home_url(?string $locale = null): string
{
    return get_locale_base_url($locale) ?: '/';
}

/**
 * 获取带语言前缀的站点基础地址
 *
 * 默认语言不加前缀（保持原有 URL 不变），其余语言形如 https://site.com/en。
 * 未启用多语言时恒等于 get_site_base_url()。
 *
 * @param string|null $locale 指定语言，默认取当前语言
 * @return string 不带尾部斜杠
 */
function get_locale_base_url(?string $locale = null): string
{
    $base = get_site_base_url();

    if (!\zapcms\services\Locale::enabled()) {
        return $base;
    }

    $locale = \zapcms\services\Locale::normalize($locale ?: \zapcms\services\Locale::current());

    if ($locale === '' || \zapcms\services\Locale::isDefault($locale)) {
        return $base;
    }

    return rtrim($base, '/') . '/' . $locale;
}

/**
 * 生成带语言前缀的站内 URL
 *
 * @param string      $path
 * @param string|null $locale 指定语言，默认取当前语言
 */
function locale_site_url(string $path = '', ?string $locale = null): string
{
    return get_locale_base_url($locale) . '/' . ltrim($path, '/');
}

/**
 * 把当前页面路径切换到指定语言
 *
 * @param string      $locale 目标语言
 * @param string|null $path   原始路径（不含查询串），默认取当前请求路径
 * @return string
 */
function switch_locale_url(string $locale, ?string $path = null): string
{
    $locale = \zapcms\services\Locale::normalize($locale);
    if ($locale === '') {
        return get_locale_base_url();
    }

    if ($path === null) {
        $path = \zapcms\services\Locale::requestPath();
    }

    // 剥掉已有的语言前缀
    $segments = array_values(array_filter(explode('/', trim((string) $path, '/')), 'strlen'));
    if ($segments !== [] && \zapcms\services\Locale::fromPath(implode('/', $segments)) !== '') {
        array_shift($segments);
    }

    $query = '';
    if (PHP_SAPI !== 'cli') {
        $queryString = $_SERVER['QUERY_STRING'] ?? '';
        if ($queryString !== '') {
            $query = '?' . $queryString;
        }
    }

    return get_locale_base_url($locale) . '/' . implode('/', $segments) . $query;
}

/**
 * 根据 config('config.suffix') 为站内内容 URL 追加后缀（如 .html）
 *
 * 与框架 UrlHelper::appendSuffix() 保持一致：
 *   - 外部链接（http/https/协议相对/mailto/tel）不追加
 *   - 已是后缀结尾不追加
 *   - 根路径（空或 /）不追加
 *   - 查询参数保留在最后
 *
 * @param string $url 完整 URL 或站内路径
 * @return string
 */
function append_url_suffix(string $url): string
{
    $suffix = (string) config('config.suffix', '');
    if ($suffix === '' || $url === '') {
        return $url;
    }

    // 协议相对/邮件/电话等外部链接不追加
    if (preg_match('#^(//|mailto:|tel:)#i', $url)) {
        return $url;
    }

    $base = get_site_base_url(); // 如 https://localhost 或 /zapcms

    // 分离协议与主机（site_url 返回绝对 URL 时）
    $scheme = '';
    if (preg_match('#^(https?://[^/]+)(/.*)?$#i', $url, $m)) {
        // 绝对 URL：仅当域名与本站一致时才追加后缀，外部域名原样返回
        if (str_starts_with($base, 'http')) {
            $curHost  = parse_url($m[1], PHP_URL_HOST);
            $baseHost = parse_url($base, PHP_URL_HOST);
            if ($curHost !== null && $baseHost !== null && strcasecmp($curHost, $baseHost) !== 0) {
                return $url;
            }
        }
        $scheme = $m[1];
        $url    = $m[2] ?? '';
    }

    // 分离路径和查询参数
    $queryPos = strpos($url, '?');
    $path = $queryPos !== false ? substr($url, 0, $queryPos) : $url;
    $query = $queryPos !== false ? substr($url, $queryPos) : '';

    // 已是后缀结尾则跳过
    if (str_ends_with($path, $suffix)) {
        return $scheme . $url;
    }

    // 根路径不追加
    $path = rtrim($path, '/');
    if ($path === '') {
        return $scheme . $url;
    }

    return $scheme . $path . $suffix . $query;
}

/**
 * 附件/存储文件 URL
 *
 * 按后台「文件上传 → 附件 URL 模式」(upload.url_mode) 返回：
 *   - relative（默认）：相对路径，如 /zapcms/storage/xxx.jpg
 *   - absolute：网站网址 + 路径，如 https://example.com/storage/xxx.jpg
 *
 * @param string $path 相对 storage 目录的路径（可含子目录）
 * @return string
 */
function storage_url(string $path = ''): string
{
    $path = '/' . ltrim($path, '/');
    if ((string)option('upload.url_mode', 'relative') === 'absolute') {
        return get_site_base_url() . '/storage' . $path;
    }
    return base_url('/storage' . $path);
}

/**
 * 获取固定链接结构
 *
 * @return string 如 '/%node_slug%/' 或 '/%year%/%monthnum%/%node_slug%/'
 */
function get_permalink_structure(): string
{
    static $structure = null;
    if ($structure === null) {
        $structure = (string) option('permalink.structure', '/%node_slug%/');
        // 历史兼容：旧数据使用 %postname% 占位符，统一迁移为 %node_slug%
        $structure = str_replace('%postname%', '%node_slug%', $structure);
        // 归一化：基础型（含查询串，如 /?n=%node_id%）只去掉多余斜杠；
        // 路径型统一为 / 开头 / 结尾。兼容历史误存为 /?n=%node_id%/ 的数据。
        if (str_contains($structure, '?')) {
            $structure = '/' . ltrim(rtrim(trim($structure), '/'), '/');
        } else {
            $structure = '/' . trim(trim($structure), '/') . '/';
        }
        if ($structure === '//') {
            $structure = '/%node_slug%/';
        }
    }
    return $structure;
}

/**
 * 获取栏目前缀
 *
 * @return string 如 'catalog'、'shop'，或空字符串（栏目直接以 /{slug} 访问）
 */
function get_catalog_prefix(): string
{
    static $prefix = null;
    if ($prefix === null) {
        // 支持空前缀：此时栏目直接以 /{slug} 形式访问，无需强制回退 catalog
        $prefix = (string) option('permalink.catalog_prefix', '');
    }
    return $prefix;
}

/**
 * 根据固定链接结构生成 Node 内容页链接
 *
 * @param array       $node   { id, slug, node_type, add_time 或 pub_time }
 * @param string|null $locale 指定语言，默认取当前语言
 * @return string
 *
 * 支持标签：%year%, %monthnum%, %day%, %node_slug%, %node_id%, %node_type%
 */
function build_permalink(array $node, ?string $locale = null): string
{
    $structure = get_permalink_structure();
    $slug = $node['slug'] ?? '';
    $id   = (int)($node['id'] ?? 0);
    $hasSlug = !empty($slug) && $slug !== '--zap-link-url';

    // 基础型：/?n={id}（路径部分为根，不能追加 URL 后缀）
    if (str_contains($structure, '?')) {
        return rtrim(locale_site_url('', $locale), '/') . '/?n=' . $id;
    }

    // 如果有 slug 且不是占位符，优先使用 slug 格式 /{slug}/
    // 但只有当结构是简单的 /%node_slug%/ 时才直接用 slug
    if ($hasSlug && $structure === '/%node_slug%/') {
        return append_url_suffix(locale_site_url($slug, $locale));
    }

    // 提取时间信息（pub_time/add_time 兼容 Unix 时间戳与日期字符串两种格式）
    $pubTime = 0;
    if (!empty($node['pub_time'])) {
        $pubTime = is_numeric($node['pub_time']) ? (int)$node['pub_time'] : (int)strtotime($node['pub_time']);
    }
    $addTime = 0;
    if (!empty($node['add_time'])) {
        $addTime = is_numeric($node['add_time']) ? (int)$node['add_time'] : (int)strtotime($node['add_time']);
    }
    $time = max($pubTime, $addTime);
    if ($time <= 0) {
        $time = time();
    }

    // 无 slug 时，依赖 %node_slug% 的结构无法生成确定性路径，
    // 回退为基础型链接 /?n={id}，保证链接可访问（路由端支持 ?n= 解析）
    if (!$hasSlug && str_contains($structure, '%node_slug%')) {
        return rtrim(locale_site_url('', $locale), '/') . '/?n=' . $id;
    }

    // 替换标签
    $replacements = [
        '%year%'      => date('Y', $time),
        '%monthnum%'  => date('m', $time),
        '%day%'       => date('d', $time),
        '%node_slug%'  => $hasSlug ? $slug : $id,
        '%node_id%'   => $id,
        '%node_type%' => $node['node_type'] ?? 'default',
    ];

    $uri = str_replace(array_keys($replacements), array_values($replacements), $structure);

    return append_url_suffix(locale_site_url($uri, $locale));
}

/**
 * 生成 Node 内容页链接
 *
 * 策略：
 *   优先使用固定链接结构（支持自定义格式）
 *   有 slug → /{slug} （文章名称型结构时）
 *   无 slug → 按结构拼接
 *
 * @param int    $nodeId 节点ID
 * @param int         $nodeId 节点 ID
 * @param string      $type   节点类型
 * @param string|null $locale 指定语言，默认取当前语言
 * @return string
 *
 * @example node_url(42)           → /my-article-slug  (文章名称型)
 * @example node_url(42, 'article') → /2024/01/my-article-slug/  (月份和名称型)
 */
function node_url(int $nodeId, string $type = 'default', ?string $locale = null): string
{
    static $cache = [];
    if (isset($cache[$nodeId])) {
        $node = $cache[$nodeId];
    } else {
        $node = Node::createQuery()
            ->select('id', 'slug', 'node_type', 'add_time', 'pub_time')
            ->where('id', $nodeId)
            ->first();
        $cache[$nodeId] = $node ?: false;
    }

    if (!$node) {
        return base_url("node/{$type}?nodeId={$nodeId}");
    }

    $slug = $node['slug'] ?? '';
    $structure = get_permalink_structure();

    // 文章名称型且有 slug：直接用 slug
    if ($structure === '/%node_slug%/' && !empty($slug) && $slug !== '--zap-link-url') {
        return append_url_suffix(locale_site_url($slug, $locale));
    }

    // 使用固定链接结构生成
    $node['node_type'] = $node['node_type'] ?: $type;
    return build_permalink($node, $locale);
}

/**
 * 解析链接：根据 link_type、link_to、link_object 智能判定并返回完整 URL
 *
 * link_type 判定规则：
 *   - catalog  → link_to 存的是 slug，生成 /{slug}
 *   - node     → link_to 存的是 slug，node 的 slug 全局唯一
 *   - custom_link → link_to 是完整 URL 或路径
 *
 * @param array       $linkRow { link_type, link_to, link_object }
 * @param string|null $locale  指定语言，默认取当前语言
 * @return string 完整 URL
 */
function resolve_link_url(array $linkRow, ?string $locale = null): string
{
    $linkType   = $linkRow['link_type']   ?? '';
    $linkTo     = $linkRow['link_to']     ?? '';
    $linkObject = (int) ($linkRow['link_object'] ?? 0);

    switch ($linkType) {
        case 'catalog':
            // link_to 存的是 slug
            $catalogPrefix = get_catalog_prefix();
            if (!empty($linkTo) && $linkTo !== '--zap-link-url') {
                return append_url_suffix(locale_site_url($catalogPrefix . '/' . $linkTo, $locale));
            }
            if ($linkObject > 0) {
                $catalog = Node::createQuery()->select('id', 'slug')->where('id', $linkObject)->first();
                if ($catalog && !empty($catalog['slug']) && $catalog['slug'] !== '--zap-link-url') {
                    return append_url_suffix(locale_site_url($catalogPrefix . '/' . $catalog['slug'], $locale));
                }
                return append_url_suffix(locale_site_url($catalogPrefix . '/' . $linkObject, $locale));
            }
            return home_url($locale);

        case 'node':
            // link_to 存的是节点的 slug
            if ($linkObject > 0) {
                $node = Node::createQuery()
                    ->select('id', 'slug', 'node_type', 'add_time', 'pub_time')
                    ->where('id', $linkObject)
                    ->first();
                if ($node) {
                    return build_permalink($node);
                }
            }
            if (!empty($linkTo) && $linkTo !== '--zap-link-url') {
                $structure = get_permalink_structure();
                if ($structure === '/%node_slug%/') {
                    return append_url_suffix(locale_site_url($linkTo, $locale));
                }
                // 自定义结构：按 slug 反查节点，用完整结构生成链接
                $node = Node::createQuery()
                    ->select('id', 'slug', 'node_type', 'add_time', 'pub_time')
                    ->where('slug', $linkTo)
                    ->first();
                if ($node) {
                    return build_permalink($node, $locale);
                }
                // 反查不到（如多语言 slug）：退回 slug 末段形式，路由仍可解析
                return append_url_suffix(locale_site_url($linkTo, $locale));
            }
            return home_url($locale);

        case 'custom_link':
        default:
            // 外部链接或绝对路径
            if (preg_match('#^(https?://|//|mailto:|tel:)#i', $linkTo)) {
                return $linkTo;
            }
            return append_url_suffix(locale_site_url($linkTo, $locale));
    }
}

/**
 * 生成外部或自定义链接（自动补全站内地址）
 *
 * @param string      $path   链接地址
 * @param string|null $locale 指定语言，默认取当前语言
 * @return string
 */
function url_link(string $path, ?string $locale = null): string
{
    if (preg_match('#^(https?://|//|mailto:|tel:)#i', $path)) {
        return $path;
    }
    return append_url_suffix(locale_site_url($path, $locale));
}

/**
 * 生成栏目链接（<a> 标签）
 *
 * @param string $url   链接地址
 * @param string $title 链接文字
 * @param array  $attrs 额外属性
 * @return string
 */
function catalog_link(string $url, string $title, array $attrs = []): string
{
    $attrStr = '';
    foreach ($attrs as $k => $v) {
        $attrStr .= ' ' . $k . '="' . htmlspecialchars((string) $v, ENT_QUOTES) . '"';
    }
    return '<a href="' . htmlspecialchars($url, ENT_QUOTES) . '"' . $attrStr . '>' . htmlspecialchars($title) . '</a>';
}

/**
 * 从栏目/节点行数据中提取链接地址（兼容前端模板）
 *
 * 根据 link_type / node_type 自动判定：
 *   - link-url 类型栏目 → 调用 resolve_link_url 解析
 *   - 普通节点         → 调用 node_url
 *   - 普通栏目         → site_url(slug)
 *
 * @param array       $row    { id, node_type, slug, link_type, link_to, link_object }
 * @param string|null $locale 指定语言，默认取当前语言
 * @return string 完整 URL
 */
function smart_node_url(array $row, ?string $locale = null): string
{
    $nodeType = $row['node_type'] ?? '';

    // link-url 类型：用 resolve_link_url 解析
    if ($nodeType === 'link-url') {
        return resolve_link_url([
            'link_type'   => $row['link_type']   ?? '',
            'link_to'     => $row['link_to']     ?? '',
            'link_object' => $row['link_object'] ?? 0,
        ], $locale);
    }

    // 普通栏目
    if ($nodeType === 'catalog' || ( isset($row['link_type']) && $row['link_type'] === 'catalog') ) {
        $slug = $row['slug'] ?? '';
        $catalogPrefix = get_catalog_prefix();
        if (!empty($slug) && $slug !== '--zap-link-url') {
            return append_url_suffix(locale_site_url($catalogPrefix . '/' . $slug, $locale));
        }
        return append_url_suffix(locale_site_url($catalogPrefix . '/' . ((int) ($row['id'] ?? 0)), $locale));
    }

    // 普通内容节点：使用固定链接结构
    return build_permalink($row, $locale);
}

/**
 * 当前内容在各语言下的链接（用于 hreflang / 语言切换器）
 *
 * @param array    $node   当前节点（含 id / slug / node_type）
 * @param string   $path   当前路径回退值（未识别到节点时使用）
 * @return array<string,string> locale => url
 */
function locale_urls(array $node = [], ?string $path = null): array
{
    $locales = \zapcms\services\Locale::available();
    $urls = [];

    foreach ($locales as $locale) {
        if (!empty($node['id'])) {
            $urls[$locale] = build_permalink($node, $locale);
            continue;
        }
        $urls[$locale] = switch_locale_url($locale, $path);
    }

    return $urls;
}

/**
 * 渲染语言切换器 HTML（主题可直接调用）
 *
 * @param string $class  最外层 ul 的 class
 * @param bool   $active 是否给当前语言加 .active
 * @return string
 */
function render_language_switcher(string $class = 'list-inline float-end m-0', bool $active = true): string
{
    if (!\zapcms\services\Locale::enabled()) {
        return '';
    }

    $current = \zapcms\services\Locale::current();
    $locales = \zapcms\services\Locale::all();

    $html = '<ul class="' . htmlspecialchars($class, ENT_QUOTES) . '">' . "\n";
    foreach ($locales as $item) {
        $isCurrent = $item['code'] === $current;
        $html .= '  <li class="list-inline-item'
            . ($active && $isCurrent ? ' active' : '')
            . '"><a href="' . htmlspecialchars(switch_locale_url($item['code']), ENT_QUOTES) . '"'
            . ' hreflang="' . htmlspecialchars($item['code'], ENT_QUOTES) . '"'
            . ($isCurrent ? ' aria-current="page"' : '')
            . '>' . htmlspecialchars($item['title']) . '</a></li>' . "\n";
    }
    $html .= '</ul>';

    return $html;
}

/**
 * 主题资源 URL
 */
function theme_url(?string $path = null): string
{
    // 优先用当前生效主题（config.theme）：正常请求等于激活主题；预览时等于被预览主题。
    // 后台将 config.theme 设为 false，此时回落到 option('website.theme')，行为不变。
    $theme = config('config.theme') ?: option('website.theme', 'basic');
    return site_url("themes/{$theme}/{$path}");
}

/**
 * 模块（mod）资源 URL
 *
 * mod_url('example-takeover', 'assets/js/main.js')
 *   => /mods/example-takeover/assets/js/main.js
 *
 * 两种部署模式（根目录 / public/ 软链）下均可用：public 模式中 mods 被软链进
 * public/mods，URL 形态保持一致；base_url() 返回基于入口脚本目录、不带语言前缀的相对 URL。
 */
function mod_url(string $mod, string $path = ''): string
{
    return base_url("/mods/{$mod}/" . ltrim($path, '/'));
}

/**
 * 输出主题资源 URL（CSS/JS/图片等）
 * 用法: theme_asset('css/style.css'), theme_asset('img/logo.svg')
 */
function theme_asset(string $path): void
{
    echo theme_url($path);
}

/**
 * 主题资源文件系统路径
 */
function theme_path(?string $path = null): string
{
    $theme = config('config.theme') ?: option('website.theme', 'basic');
    return base_path("themes/{$theme}/{$path}");
}

/**
 * 检查主题文件是否存在（自动尝试多种扩展名）
 *
 * @param string               $file    文件名（不含扩展名）
 * @param string|string[]|null $extList 扩展名列表，默认 ['.php', '.twig']
 * @return bool
 */
function theme_file_is_exists(string $file, $extList = null): bool
{
    if ($extList === null) {
        $extList = ['.php', '.twig'];
    }
    if (!is_array($extList)) {
        $extList = [$extList];
    }
    foreach ($extList as $ext) {
        if (is_file(theme_path($file . $ext))) {
            return true;
        }
    }
    return false;
}

/**
 * 美化输出数组（短数组语法）
 *
 * @param mixed $expression
 * @param bool  $return     是否返回字符串而非直接输出
 * @return string
 */
function zap_var_export($expression, bool $return = false): string
{
    $export = var_export($expression, true);
    $patterns = [
        "/array \(/"                => '[',
        "/^([ ]*)\)(,?)$/m"         => '$1]$2',
        "/=>[ ]?\n[ ]+\[/"          => '=> [',
        "/([ ]*)(\'[^\']+\') => ([\[\'])/" => '$1$2 => $3',
    ];
    $export = preg_replace(array_keys($patterns), array_values($patterns), $export);
    if (!$return) {
        echo $export;
    }
    return $export;
}

/**
 * 条件为真时输出（简化模板 if 判断）
 *
 * @param mixed  $expr
 * @param string $str
 */
function if_echo($expr, string $str): void
{
    if ($expr) {
        echo $str;
    }
}

/**
 * 三目输出（简化模板 if-else 判断）
 *
 * @param mixed  $expr
 * @param string $str
 * @param string $str2
 */
function if_else_echo($expr, string $str, string $str2): void
{
    if ($expr) {
        echo $str;
    } else {
        echo $str2;
    }
}

/**
 * 条件为真时返回值
 *
 * @param mixed  $expr
 * @param string $str
 * @return string|null
 */
function if_return($expr, string $str): ?string
{
    return $expr ? $str : null;
}

/**
 * 获取当前主题名称
 *
 * @return string|false
 */
function get_theme_name()
{
    $theme = config('config.theme', 'basic');
    return $theme === false ? false : $theme;
}

/**
 * 获取主题文件系统路径（兼容旧函数，推荐使用 theme_path）
 *
 * @param string|null $path
 * @return string|false
 */
function get_theme_path(?string $path = null)
{
    $theme = config('config.theme', 'basic');
    if ($theme === false) {
        return false;
    }
    return $path === null
        ? base_path("themes/{$theme}")
        : base_path("themes/{$theme}/{$path}");
}

/**
 * 获取主题资源 URL（兼容旧函数，推荐使用 theme_url）
 *
 * @param string|null $path
 * @return string|false
 */
function get_theme_url(?string $path = null)
{
    $theme = config('config.theme', 'basic');
    if ($theme === false) {
        return false;
    }
    return $path === null
        ? base_url("themes/{$theme}")
        : base_url("themes/{$theme}/{$path}");
}

/**
 * 获取主题配置对象（用于后台主题设置页面的读写）
 *
 * @return \zapcms\services\ThemeSettings
 */
function themeSettings(): \zapcms\services\ThemeSettings
{
    return new \zapcms\services\ThemeSettings();
}

/**
 * 获取栏目/节点菜单项的前端链接地址
 *
 * - link_type = custom_link → 直接使用 link_to（外部链接 / 站内路径）；
 * - 其余（link-url / 普通栏目 / 普通节点）统一走 smart_node_url 解析。
 *
 * @param array       $item   栏目/节点行数据
 * @param string|null $locale 指定语言
 * @return string
 */
function catalog_item_url(array $item, ?string $locale = null): string
{
    if (($item['link_type'] ?? '') === 'custom_link') {
        return url_link($item['link_to'] ?? '#', $locale);
    }
    return smart_node_url($item, $locale);
}

/**
 * 渲染指定菜单位置的导航（前端模板调用）
 *
 * 用法：<?php echo catalog_menu('about-left'); ?>
 * 返回 Bootstrap nav 结构的 HTML 字符串；该位置无菜单时返回空串。
 *
 * @param string $key     菜单位置 key（如 left / about-left）
 * @param array  $options ['active_catalog_id' => int, 'ul_class' => string]
 * @return string
 */
function catalog_menu(string $key, array $options = []): string
{
    $activeCatalogId = (int) ($options['active_catalog_id'] ?? ($GLOBALS['catalog_id'] ?? 0));
    $ulClass         = $options['ul_class'] ?? 'nav flex-column catalog-menu';

    $tree = \zapcms\services\Catalog::instance()->getPositionMenu($key);
    if (empty($tree)) {
        return '';
    }

    $html = '<ul class="' . htmlspecialchars($ulClass, ENT_QUOTES) . '">';
    $html .= catalog_menu_render($tree, $activeCatalogId);
    $html .= '</ul>';

    return $html;
}

/**
 * 递归渲染菜单位置导航树
 */
function catalog_menu_render(array $items, int $activeCatalogId, int $level = 0): string
{
    $html = '';
    foreach ($items as $item) {
        $id          = (int) ($item['id'] ?? 0);
        $hasChildren = !empty($item['children']);
        $isActive    = $id === $activeCatalogId;
        // 优先使用 menu_items 已解析的 url，否则回退到按 link_type 推导
        $url         = $item['url'] ?? catalog_item_url($item);
        $targetAttr  = (!empty($item['target']) && $item['target'] === '_blank')
            ? ' target="_blank" rel="noopener"' : '';

        // mega-menu 富字段：css_class 挂到 <li>，icon 前置、badge 后置到链接文字
        $liClass = 'nav-item' . (!empty($item['css_class']) ? ' ' . $item['css_class'] : '');
        $linkClass = 'nav-link' . ($isActive ? ' active' : '');

        $iconHtml = !empty($item['icon'])
            ? '<i class="' . htmlspecialchars($item['icon'], ENT_QUOTES) . '"></i> '
            : '';
        $badgeHtml = !empty($item['badge'])
            ? ' <span class="badge text-bg-danger ms-1">' . htmlspecialchars($item['badge'], ENT_QUOTES) . '</span>'
            : '';

        $html .= '<li class="' . htmlspecialchars($liClass, ENT_QUOTES) . '">';
        $html .= '<a href="' . htmlspecialchars($url, ENT_QUOTES) . '"' . $targetAttr . ' class="' . $linkClass . '">';
        $html .= $iconHtml . htmlspecialchars($item['title'] ?? '', ENT_QUOTES) . $badgeHtml;
        if ($hasChildren) {
            $html .= ' <i class="nav-arrow fas fa-angle-left right"></i>';
        }
        $html .= '</a>';

        if ($hasChildren) {
            $childActive = catalog_menu_has_active($item['children'], $activeCatalogId);
            $html .= '<ul class="nav nav-treeview"' . ($childActive ? ' style="display:block"' : '') . '>';
            $html .= catalog_menu_render($item['children'], $activeCatalogId, $level + 1);
            $html .= '</ul>';
        }
        $html .= '</li>';
    }
    return $html;
}

/**
 * 判断子树中是否包含当前激活栏目（用于展开父级）
 */
function catalog_menu_has_active(array $items, int $activeCatalogId): bool
{
    foreach ($items as $item) {
        if ((int) ($item['id'] ?? 0) === $activeCatalogId) {
            return true;
        }
        if (!empty($item['children']) && catalog_menu_has_active($item['children'], $activeCatalogId)) {
            return true;
        }
    }
    return false;
}

/**
 * 输出某个小工具区域的所有小工具
 *
 * @param string $area 区域 key（主题 theme.json 的 widget_areas 声明，如 sidebar / footer）
 * @return string HTML
 */
function widget(string $area): string
{
    return \zapcms\services\WidgetManager::instance()->renderArea($area);
}

/**
 * 返回可用的小工具区域 [key => title]
 *
 * @return array<string,string>
 */
function widget_areas(): array
{
    return \zapcms\services\WidgetManager::instance()->areas();
}
