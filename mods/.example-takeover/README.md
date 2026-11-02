# ZAP CMS Mod 开发指南（示例：`.example-takeover`）

本目录是一个**可直接当作模板**的示例模块，演示如何用最少的代码「零侵入」地接管站点页面。

> 目录以 `.` 开头命名，使其在后台「模块」列表里**隐藏**（仅作示例）。要发布为正式模块，把目录改名（去掉前缀）并更新内部引用即可，见文末。

---

## 1. 这个示例演示了什么

- 通过**统一接管钩子**接管四类页面：商品详情、商品列表、搜索结果、首页；
- 接管后**复用站点 layout**（头部/底部/导航/侧栏），无需自己拼整页；
- mod **自带静态资源**（CSS / JS / 图片）的正确引用方式；
- 安全：mod 的 PHP 文件禁止被 Web 直接执行。

---

## 2. 目录结构

```
mods/.example-takeover/
├─ mod.json                 模块清单（声明 boot_frontend 启动脚本）
├─ boot_frontend.php        启动脚本：注册接管钩子、挂视图目录
├─ assets/                  静态资源（可被 Web 访问）
│  ├─ css/style.css
│  ├─ js/main.js
│  └─ img/logo.svg
├─ views/                   模板目录
│  └─ example_takeover/     视图命名空间（与模块目录名相互独立）
│     ├─ product.php        商品详情接管模板
│     ├─ product_list.php   商品列表接管模板
│     ├─ search.php         搜索结果接管模板
│     └─ home.php           首页接管模板
└─ README.md                本文件
```

> 注意：`views/example_takeover/` 是 **View 命名空间**（决定 `view('example_takeover.xxx')` 如何解析），与模块目录名 `.example-takeover` 相互独立，不必相同。

---

## 3. `mod.json`

```json
{
  "name": ".example-takeover",
  "title": "示例：内容模型页面接管",
  "description": "演示 node_render_* / catalog_render_* / search_render / home_render / not_found_render 接管。",
  "version": "1.0.0",
  "author": "ZAP CMS",
  "status": 1,
  "boot_frontend": "boot_frontend.php"
}
```

| 字段 | 作用 |
| --- | --- |
| `name` | 模块唯一标识，对应 `mods/<name>/` 目录与安装选项键 |
| `boot_frontend` | 前台每次请求初始化时由 `PluginLoader` 自动 `include` 的脚本 |
| `boot_admin` / `boot` | 仅后台 / 前后台都加载（可选） |

**关键约定**：接管钩子**必须**在启动脚本里注册，而不是写在 `Mod::invoke()` 里——后者只在 `/mod/...` 路由时才执行，接管站点页面时根本不会跑到。

---

## 4. 统一接管钩子（核心）

四个 controller 通过 `app/controllers/RenderTakeover.php` trait 在渲染前触发约定式过滤器钩子。mod 在 `boot_frontend.php` 里 `Hook::filter()` 注册即可接管，回调可返回：

- `\zap\view\View` 实例（**推荐**，自动套用主题模板/layout）
- 非空字符串（直接输出，需自行拼整页）
- `callable`（返回上述任意类型）

| 页面 | 钩子（按优先级） | 回调签名 | 说明 |
| --- | --- | --- | --- |
| 内容详情 | `node_render` → `node_render_{mime}` → `node_{mime}_render`（旧别名） | `function($_, $node, $mime, $ctrl)` | 如 `node_render_product`；未知模型类型也会触发，可零侵入新增内容模型 |
| 栏目列表 | `catalog_render_{mime}` → `catalog_product_list_render`（旧别名） | `function($_, $catalog, $mime, $ctrl)` | 如 `catalog_render_product`；在构建列表查询**之前**触发 |
| 搜索结果 | `search_render` → `search_render_{node_type}` | `function($_, $keyword, $searchType, $ctrl)` | 如 `search_render_product`；在构建查询**之前**触发 |
| 首页 | `home_render` | `function($_, $ctrl)` | 自定义首屏 |
| 404 | `not_found_render` | `function($_, $uri, $ctrl)` | 自定义 404 页面（保留 404 状态码） |

命中任一钩子即视为「接管」，框架统一 `emit` 后结束渲染，不再走默认模板。

### `boot_frontend.php` 示例

```php
use zap\view\View;
use zapcms\Hook;

// 1) 把本 mod 的视图目录加入模板搜索路径，使 view('example_takeover.xxx') 可解析
View::paths(APP_ROOT . '/mods/.example-takeover/views');

// 2) 接管商品详情：返回 View（最优雅，自动套用主题 layout）
Hook::filter('node_render_product', function ($_, $node, $mime, $ctrl) {
    return view('example_takeover.product', ['node' => $node, 'mime' => $mime]);
});

// 3) 接管商品列表
Hook::filter('catalog_render_product', function ($_, $catalog, $mime, $ctrl) {
    return view('example_takeover.product_list', ['catalog' => $catalog, 'mime' => $mime]);
});

// 4) 接管搜索结果
Hook::filter('search_render', function ($_, $keyword, $searchType, $ctrl) {
    return view('example_takeover.search', ['keyword' => $keyword, 'mime' => $searchType]);
});

// 5) 接管首页
Hook::filter('home_render', function ($_, $ctrl) {
    return view('example_takeover.home', []);
});

// 6) 接管 404 页面
Hook::filter('not_found_render', function ($_, $uri, $ctrl) {
    return view('example_takeover.404', ['uri' => $uri]);
});
```

> 想「全接管」某类页面：直接注册主钩子（如 `node_render`、`search_render`）即可，不必按类型逐个写。

---

## 5. 接管模板怎么写（复用站点 layout）

mod 返回的 `View` 与主题模板是**同一个 `zap\view\View` 对象**，所以只要写一样的「模板继承」语句，就能免费套上当前激活主题的整站外壳——且**主题无关**，换主题即套新主题的 layout。

```php
<?php
defined('IN_ZAPCMS') or die('No permission to access');

// 注册本 mod 自带的样式与脚本（资源队列，由 layout 统一输出）
register_styles(mod_url('.example-takeover', 'assets/css/style.css'), ASSETS_HEAD);
register_scripts(mod_url('.example-takeover', 'assets/js/main.js'), ASSETS_BODY);

// 1) 继承站点 layout（头/底/导航/CSS/JS 都在里面）
$this->extend('layout/default');
// 2) 用 content 区块填本页内容
$this->beginBlock('content');
$node = $node ?? [];
?>
    <?php echo $this->partial('partials/_breadcrumb'); ?>   <!-- 3) 复用主题片段 -->
    <div class="container">
        <h1><?php echo htmlspecialchars($node['title'] ?? ''); ?></h1>
    </div>
<?php $this->endBlock(); ?>
```

- 不写 `extend()` 即「完全自定义整页」，脱离站点 chrome（适合支付回调页等）。
- layout 名称按当前主题自动解析，mod 代码无需改动即适配多主题。

---

## 6. 静态资源（图片 / JS / CSS）处理

### 放置位置
`mods/<name>/assets/` 下（见目录结构）。

### 生成 URL：`mod_url()`
```php
mod_url('.example-takeover', 'assets/img/logo.svg');
// => /mods/.example-takeover/assets/img/logo.svg
```
`mod_url()` 定义在 `app/zapcms/functions.php`，**根目录模式与 public/ 软链模式都通用**（public 模式下 `mods` 被软链进 `public/mods`）。

### JS / CSS：走资源队列（推荐）
```php
register_styles(mod_url('.example-takeover', 'assets/css/style.css'), ASSETS_HEAD);
register_scripts(mod_url('.example-takeover', 'assets/js/main.js'),   ASSETS_BODY);
```
队列由 layout 的 `print_styles()`（`<head>` 内）与 `print_scripts(ASSETS_BODY)`（`<body>` 末）统一输出。前台 `themes/basic/layout/default.php` 已包含这两处调用。

### 图片：直接引用
```php
<img src="<?= mod_url('.example-takeover', 'assets/img/logo.svg') ?>" alt="">
```

### 安全：禁止 Web 直接执行 mod 的 PHP
根 `.htaccess` 已加入（public 模式由 `MakePublic` 生成时也已包含）：
```apache
RewriteRule ^mods/.*\.php$ - [R=403,L]
```
mod 的 `boot_frontend.php` 等只应由框架在启动时 `include`，绝不可经 HTTP 直接执行。

---

## 7. 安装 / 激活 / 隐藏

模块由 `PluginLoader` 读取 `mod.installed.*` 选项启动（**不靠目录扫描**），因此：

- 后台「安装」模块会写入选项 `mod.installed.<name> = {"status":1}`；
- 本示例目录名为 `.example-takeover`，写在选项里的键是**双点**：
  ```
  mod.installed..example-takeover = {"status":1}
  ```
- 后台模块列表会跳过首字符为 `.` 的目录（`$item[0] === '.'`），所以本示例**不在列表显示**，但仍会正常启动——这正是它「仅作示例」的用途。
- 卸载即删除该选项，瞬间恢复主题默认渲染。

---

## 8. 发布为正式模块

1. 把目录从 `.example-takeover` 改名为正式名（如 `mall-takeover`）；
2. 更新以下写死处为新名：
   - `mod.json` 的 `name`；
   - `boot_frontend.php` 中 `View::paths(APP_ROOT . '/mods/<新名>/views')`；
   - 各模板里 `mod_url('<新名>', ...)`；
   - （可选）`views/example_takeover/` 命名空间子目录名。
3. 安装并激活即可。

---

## 9. 局部注入（不接管整页）

若只想在主题原页面里**插入一个区块**而非整页接管，可在主题模板内预留注入点后 `Hook::fire()`，或给布局加 `$this->block('extra')` 区块。这属于「主题侧扩展」，与本文的整页接管互补。
