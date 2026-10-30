# ZAP CMS

##  Requirements
- PHP 8.2+
- MYSQL5.5+
- Sqlite3 
- PDO Driver (MySQL、Sqlite3)


### URL Rewrite

#### Apache
````apacheconf
Options +FollowSymLinks -Indexes
RewriteEngine On
 
RewriteCond %{HTTP:Authorization} .
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
 
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php [QSA,PT,L]
````


#### Nginx Rewrite
````text
location / {
    try_files $uri $uri/ /index.php$is_args$query_string;
}
````

## Backend Theme

- 源码目录：`resources/admin/`
  - `scss/zap-admin.scss` —— 后台主题入口（Bootstrap + ZapUI + ZAP 定制）
  - `scss/_variables.scss` —— 主题变量（覆盖 Bootstrap / ZapUI 默认值）
  - `scss/_components.scss` —— ZAP 组件与页面定制样式
  - `scss/bootstrap.scss` —— 安装向导使用的最小 Bootstrap 样式
  - `js/bootstrap.js` —— Bootstrap JS（暴露 `window.bootstrap`）
  - `js/editor.js` —— 代码编辑器入口（CodeMirror 6，按需加载，语言包自动分包）
  - `vendor/zap-ui/scss/` —— 内置的 ZapUI 基础样式源码（后台 UI 基座，含 LICENSE）
- 依赖：`bootstrap` 5.3.x、`codemirror` 6.x、`vite`、`sass-embedded`（见 `package.json`）

````bash
npm install     # 首次安装
npm run dev     # 监听编译（开发）
npm run build   # 生产构建（压缩）
````


