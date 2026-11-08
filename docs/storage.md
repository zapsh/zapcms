# 存储层接入说明

ZAP CMS 通过统一存储抽象层管理本地磁盘与对象存储（阿里云 OSS / 腾讯云 COS / Cloudflare R2），
上传、URL 生成、文件浏览、重命名、删除、移动、下载全部走同一套接口，媒体库与文件选择器（Finder）
不再直接依赖本地文件系统语义。

## 目录与命名规范

- 目录全小写：`app/zapcms/services/storage/`、`.../storage/drivers/`
- 类名保持大写（PSR-4）：`StorageInterface`、`StorageManager`、`StorageResult`、`StorageException`
- 驱动：`LocalStorage`、`OssStorage`、`CosStorage`、`R2Storage`
- 命名空间：`zapcms\services\storage\`（对应 `zapcms\` → `app/zapcms/` 的 PSR-4 映射）

## 核心接口

`StorageInterface` 定义统一契约，key 一律为相对 storage 根目录的路径（如 `images/a.jpg`）：

| 方法 | 说明 |
| --- | --- |
| `put()` | 写入文件（本地临时文件 → 存储），返回 `StorageResult` |
| `delete()` | 删除文件或目录（目录递归删除其下所有对象） |
| `exists()` / `url()` / `thumbUrl()` | 存在性 / 对外 URL / 缩略图 URL |
| `list($prefix)` | 列举目录，返回 `['dirs'=>[...], 'files'=>[...]]` |
| `makeDirectory()` / `rename()` / `download()` | 建目录 / 改名（移动）/ 下载到本地文件 |
| `keyFromUrl()` | 对外 URL 反解为相对 key（删除、缩略图逆解析依赖它） |
| `isConfigured()` | 驱动是否配置完整 |

对象存储没有真正的“目录”，用 **key 前缀 + 尾斜杠占位对象** 模拟：
`makeDirectory("a/b")` 写 0 字节对象 `a/b/`；`list("a")` 按 `delimiter=/` 返回下一层。

## 存储模式（`upload.storage_mode`）

- `local`（默认）：只写本地，行为与改造前一致
- `oss` / `cos` / `r2`：只写对应云厂商，前台 URL 走云/CDN
- `both`：本地 + 云双写；云由 `upload.cloud_driver` 指定（默认 `oss`）。云同步失败只记日志，不阻塞上传

文件管理（浏览/改名/删除）在 `both` 模式下以**本地为源**，同时尽力同步到云。

## 密钥与环境变量

密钥只放 `config/storage.php` 或环境变量，**不写入** options 表（避免随 autoload 泄漏）。

| 厂商 | config/storage.php 段 | 环境变量 |
| --- | --- | --- |
| OSS | `storage.oss` | `OSS_ACCESS_KEY_ID` / `OSS_ACCESS_KEY_SECRET`（STS 另设 `OSS_SECURITY_TOKEN`） |
| COS | `storage.cos` | `COS_SECRET_ID` / `COS_SECRET_KEY` |
| R2 | `storage.r2` | `R2_ACCESS_KEY_ID` / `R2_SECRET_ACCESS_KEY` |

非敏感项（bucket / endpoint / 域名 / 前缀 / 模式）在后台「系统设置 → 文件上传」配置，键名 `upload.*`。

## 各厂商配置

**阿里云 OSS**（Signature V1 自签）

- `upload.oss_bucket`：桶名
- `upload.oss_endpoint`：`oss-cn-hangzhou.aliyuncs.com`（`http://` 开头走内网/MinIO）
- `upload.oss_base_url`：CDN / 自定义域名（可选）
- `upload.oss_prefix`：对象 key 前缀（可选）

**腾讯云 COS**（经典 `q-sign-algorithm=sha1` 自签）

- `upload.cos_bucket`：含 APPID 的完整桶名，如 `example-1250000000`
- `upload.cos_region`：地域，如 `ap-guangzhou`
- `upload.cos_base_url`：CDN / 自定义域名（可选）
- `upload.cos_prefix`：对象 key 前缀（可选）

**Cloudflare R2**（AWS Signature V4 自签，S3 兼容）

- `upload.r2_account_id`：Account ID
- `upload.r2_bucket`：桶名
- `upload.r2_endpoint`：S3 端点（可选，默认 `{account_id}.r2.cloudflarestorage.com`）
- `upload.r2_base_url`：公开访问地址（必填，自定义域名或 `https://pub-xxx.r2.dev`）
- `upload.r2_prefix`：对象 key 前缀（可选）

> 三厂商均要求 **Bucket 公共读**。私有桶的签名 URL 会过期，不能写入数据库。

## 缩略图策略

| 模式 | 策略 |
| --- | --- |
| `local` / `both` | 本地 GD 生成 `storage/thumbs/` 缓存 |
| `oss` | OSS 图片处理参数 `x-oss-process=image/resize`（云端，无需 GD） |
| `cos` | 默认返回原图；`upload.cos_thumb=ci` 时走数据万象 `imageMogr2/thumbnail/!WxHr` |
| `r2` | 默认返回原图；`upload.r2_thumb=resizing` 时走 `/cdn-cgi/image/width=...,height=...,fit=scale-down/` |

- COS「数据万象」需在 COS 控制台为 bucket 开启图片处理。
- R2「Image Resizing」需 `r2_base_url` 为经 Cloudflare 代理的自定义域名，并在 Cloudflare 开启 Image Resizing。

## URL 逆解析

`StorageManager::keyFromUrl()` 会依次尝试：本地 `/storage/` URL → OSS/COS/R2/CDN 域名，用于
`storage_key()`、`ThumbHelper`、删除与同步工具还原相对 key。数据库里存的 URL（相对路径 / 站点绝对 /
云 CDN 混杂）都能正确反解。

## 扩展点（供 cmsdev / mall 等模块复用）

```php
Hook::filter('storage.driver', fn($driver, $name) => ...);   // 替换/包装驱动实例
Hook::filter('storage.url',    fn($url, $key, $mode) => ...); // 改写对外 URL
Hook::on('storage.after_put', fn(StorageResult $r) => ...);   // 上传后回调
Hook::on('storage.after_delete', fn($key, $mode) => ...);     // 删除后回调
```

## 常见问题

- **上传报“云同步失败”**：`both` 模式下本地已写入成功，仅云镜像失败，检查云配置与日志。
- **缩略图不显示**：确认对应厂商缩略图开关已开启、bucket 已开通图片处理能力。
- **删除/改名不生效**：对象存储按前缀操作，确认 `keyFromUrl` 能正确反解当前域名（含前缀）。
