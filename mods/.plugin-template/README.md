# 示例插件（plugin-template）

本目录是一个**插件 / 模块脚手架模板**，复制为 `mods/<你的模块名>/` 后即可开始开发。
结构说明见 `plugin.json`、`boot.php` 及各脚本注释。

## 目录结构

```
mods/<your-module>/
├─ assets/                # 模块自带静态资源（对外可访问，无需注册路由）
│  ├─ img/logo.svg       #   图片放这里
│  ├─ css/style.css      #   CSS（可用相对路径 ../img/ 引用同模块图片）
│  └─ js/main.js         #   JS
├─ views/                # 视图目录（View::paths 会自动注册）
│  └─ plugin_template/
│     └─ asset_demo.php   #   资源引用示例页
├─ boot.php              # 引导脚本（前后台各 include 一次，注册 Hook）
├─ install.php           # 安装脚本
├─ uninstall.php         # 卸载脚本
├─ update.php            # 更新脚本
└─ plugin.json           # 模块元信息
```

## 模块里包含图片，怎么展示？

图片属于**静态资源**，框架已内置约定，不需要自己写路由读取文件 —— 直接当静态文件由
Web 服务器提供即可：

1. 把图片放进 `assets/img/`（如 `assets/img/logo.svg`）。
2. 在视图里用 `mod_url('<模块目录名>', 'assets/img/logo.svg')` 生成 URL：

   ```php
   <img src="<?php echo mod_url('example-plugin', 'assets/img/logo.svg'); ?>" alt="logo">
   ```

   `mod_url()` 会输出类似 `/mods/example-plugin/assets/img/logo.svg` 的地址。

3. CSS / JS 通过资产队列注册，由主题 layout 统一输出：

   ```php
   register_styles(mod_url('example-plugin', 'assets/css/style.css'), ASSETS_HEAD);
   register_scripts(mod_url('example-plugin', 'assets/js/main.js'), ASSETS_BODY);
   ```

### 为什么 `/mods/...` 能直接访问

`mod_url()` 只生成一条与部署模式无关的标准 URL。文件能否被浏览器拿到取决于部署模式：

- **根目录模式**（dev 常用，docroot = 项目根）：`/mods/...` 就是真实的静态文件路径，直接可访问。
- **public 模式**（`public/index.php` 入口）：运行 `php console zapcms:MakePublic`，
  命令会把 `mods`、`themes`、`storage`、`assets` 软链接到 `public/` 下，URL 形态保持一致。

> 安全：public 模式的 `.htaccess` 已禁止 `mods/`、`storage/` 下执行 `.php/.phtml/.phar`，
> 所以放进 `assets/` 的图片/静态资源是安全的，但**不要**把任何可执行脚本丢进 `assets/`。

### 快速预览示例页

`boot.php` 末尾挂了一个带 `?pt_demo=1` 守卫的 `home_render` 钩子，访问首页并附加该参数即可
看到 `views/plugin_template/asset_demo.php` 的演示（正常访问首页不受影响）。
