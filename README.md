# ZAP CMS

轻量级 PHP 内容管理系统

- 当前版本：`2.0.5`
- 许可协议：[MIT](LICENSE)

## 特性

- **数据库**：MySQL 8+ 与 SQLite3 、支持PostgreSQL
- **主题机制**：`themes/` 下每个目录即一个完整主题（支持PHP 、 Twig 模版）
- **模块扩展**：`mods/` 存放可插拔模块，支持独立路由、自动加载与后台入口
- **一键安装**：Web 安装向导或命令行 `zapcms:Install` 两种方式
- **多语言**：内置语言包与可配置的默认语言 / 回退语言
- **后台工具链**：代码编辑器、媒体上传、队列、数据库备份、AI 能力、API Token 签发
- **静态分析**：PHPStan 配置与基线已就绪

## 环境要求

- PHP **8.2+**
- PHP 扩展：`pdo`、`pdo_mysql` 或 `pdo_sqlite`、`gd`、`json`、`mbstring`、`openssl`、`curl`、`dom`、`simplexml`、`xml`、`zip`、`ctype`、`session`、`exif`
- 数据库：MySQL 8+ 或 SQLite3
- Web 服务器：Apache（含 `mod_rewrite`）或 Nginx rewrite
- 前端构建（仅修改后台样式/脚本时需要）：Node.js 22.12+（Vite 7 要求）

## 快速开始

#### 方式一：Web 安装向导

将站点根目录指向本项目根目录（`index.php` 所在处），浏览器访问：

```
http://your-domain/install/
```

向导依次完成：许可协议 → 环境检测 → 数据库配置 → 导入基础数据。安装完成后会生成 `var/install.lock` 与 `config/database.php`，再次访问 `/install/` 会自动跳转首页。

#### 方式二：命令行安装

```bash
# 使用现有配置安装（或全新库）
php console zapcms:Install

# MySQL
php console zapcms:Install --db-type mysql --db-host 127.0.0.1 --db-port 3306 \
    --db-name zapcms --db-user root --db-pass root --db-prefix zap_

# SQLite
php console zapcms:Install --db-type sqlite

# 指定应用密钥与管理员凭据
php console zapcms:Install --app-key <64位hex> -a '你的密码' -e admin@example.com

# 重建全部数据表并重装（危险：清空现有数据）
php console zapcms:Install -f
```

### 4. 访问后台

默认后台入口为 `/z-admin`（可在后台「基础设置 > 服务器」中修改，值保存在 `options` 表的 `server.admin_prefix`）。

## 配置

运行期配置位于 `config/`，其中三项为**本地文件、不入版本库**，需由安装流程或手动从样例复制：

| 文件 | 说明 |
|---|---|
| `config/config.php` | URL 后缀、多语言、`app_key`，以及可选的 `theme` 覆盖（见 `config.sample.php`） |
| `config/database.php` | 数据库连接与表前缀（见 `database.sample.php`） |
| `config/security.php` | 安全相关配置（见 `security.sample.php`） |

其余配置按用途拆分：`route.php`（路由注册顺序：后台优先、前台兜底）、`log.php`、`storage.php`、`twig.php`、`module.php`、`ai.php`、`backup.database.php`。

`app_key` 用于加密存储云存储密钥、SMTP 密码、Redis 密码等敏感项，安装向导会自动生成随机值，也可用环境变量 `ZAP_APP_KEY` 覆盖：

```bash
php -r "echo bin2hex(random_bytes(32));"
```

> 维护模式、调试开关、日志级别、后台前缀等运行参数存放在数据库 `options` 表（`server.*`），不要写入 `config.php`，否则会与后台设置冲突。

## 命令行

`console` 是 CLI 入口，仅在命令行模式下可用：

```bash
php console <command> [options]
```

| 命令 | 说明 |
|---|---|
| `zapcms:Install` | 一键安装：写入配置、建表、导入基础数据 |
| `zapcms:Migrate` | 执行数据库迁移；`-s` 查看状态，`-m` 标记全部为已执行 |
| `zapcms:CreateSchema` | 创建数据表结构 |
| `zapcms:DropTable` | 删除数据表 |
| `zapcms:UpdateBaseData` | 更新基础数据 |
| `zapcms:ResetPassword` | 重置管理员密码 |
| `zapcms:IssueApiToken` | 为管理员签发 / 吊销 API Bearer Token |
| `zapcms:QueueWorker` | 启动队列消费进程 |
| `zapcms:SyncModuleRoutes` | 同步模块路由 |
| `zapcms:MakePublic` | 生成 `public/` 部署模式 |

代码静态分析：

```bash
composer analyse
```

## URL 重写

项目根目录的 `.htaccess` 已包含 Apache 规则（屏蔽源码目录与敏感文件、转发到 `index.php`），启用 `mod_rewrite` 即可。

Nginx 需自行配置等价规则：

```nginx
root /path/to/zapcms;
index index.php;

location / {
    try_files $uri $uri/ /index.php$is_args$query_string;
}

# 上传目录禁止解析 PHP
location ~ /storage/.*\.php$ {
    deny all;
}

# 拒绝直接访问源码与敏感文件
location ~* ^/(app|config|mods|vendor|var)/ {
    deny all;
}
location ~* (composer\.(json|lock)|Dockerfile|console|README\.md|LICENSE|CHANGELOG\.md|\.env) {
    deny all;
}
```

## Docker

`Dockerfile` 基于 Alpine 3.18 打包 PHP 8.2-FPM + Nginx，`vendor/` 随源码复制（因为 `composer install` 无法解析本地 `path` 依赖）。

```bash
docker build -t zapcms .
docker run -d -p 8080:80 zapcms
```

镜像内的 PHP 配置已包含上传限制、Session 安全项与 `storage`/`var` 属主设置。`config/database.php`、`config/config.php`、`config/security.php`、`var/install.lock` 被 `.dockerignore` 排除，需在容器启动后完成安装。

## 目录结构

```
app/
├── bootstrap.php            # 应用引导入口（index.php 唯一依赖）
├── Startup.php              # 前台路由处理器
├── ZQuery.php               # 查询辅助
├── controllers/             # 全局控制器
└── zapcms/
    ├── Bootstrap.php        # 后台路由注册
    ├── controllers/         # 后台控制器
    ├── models/              # 数据模型
    ├── migrations/          # 数据库迁移
    ├── services/            # 业务服务
    ├── support/             # 建表 / 基础数据等支撑类
    ├── commands/            # console 命令
    ├── views/               # 后台视图
    ├── languages/           # 语言包
    └── ai/ auth/ extensions/ helpers/ node/ traits/ widgets/
assets/                      # 前端静态资源（含后台构建产物）
config/                      # 配置（route / database / twig / log ...）
install/                     # Web 安装向导
mods/                        # 可插拔模块
resources/                   # 后台主题源码（SCSS / JS 入口）
storage/                     # 上传文件与运行时存储
themes/                      # 前台主题（basic、zap2026）
var/                         # 缓存、日志、SQLite 数据库等运行时产物
vendor/                      # Composer 依赖
console                      # CLI 入口
index.php                    # Web 入口
```

## 主题与前端构建

### 前台主题

`themes/` 下每个子目录为一个主题，使用 Twig 模板（`*.twig`）与 PHP 片段（`*.php`）混合组织。当前内置 `basic` 与 `zap2026` 两个主题。

主题的生效值存放在数据库 `options` 表的 `website.theme`（缺省 `basic`），在后台「基础设置」中切换。`config/config.php` 的 `theme` 可作为覆盖项；设为 `false` 时回落到数据库中的设置。

### 后台主题源码

- `resources/admin/scss/zap-admin.scss` —— 后台主题入口（Bootstrap + ZapUI + ZAP 定制）
- `resources/admin/scss/_variables.scss` —— 主题变量（覆盖 Bootstrap / ZapUI 默认值）
- `resources/admin/scss/_components.scss` —— ZAP 组件与页面定制样式
- `resources/admin/scss/bootstrap.scss` —— 安装向导使用的最小 Bootstrap 样式
- `resources/admin/js/bootstrap.js` —— Bootstrap JS（暴露 `window.bootstrap`）
- `resources/admin/js/editor.js` —— 代码编辑器入口（CodeMirror 6，按需加载，语言包自动分包）
- `resources/admin/vendor/zap-ui/scss/` —— 内置 ZapUI 基础样式源码（后台 UI 基座，含 LICENSE）

依赖：`bootstrap` 5.3.x、`codemirror` 6.x、`vite`、`sass-embedded`（见 `package.json`）。

```bash
npm install       # 首次安装
npm run dev       # 监听编译（开发）
npm run build     # 生产构建（压缩）
npm run build:debug
```

构建产物输出到 `assets/admin/`（CSS 与 JS 均带内容 hash，并生成 `.vite/manifest.json` 供 PHP 侧解析）。该目录同时包含手工维护的 `admin.js`、`zap-admin.js` 与图片资源，因此构建不清空输出目录。产物与源码一并提交，部署环境无需 Node.js。

## 许可

本项目基于 [MIT 许可协议](LICENSE) 开源。

Copyright (c) 2023-2026 嘉兴领格信息技术有限公司 (ZAP.CN)
