<?php

use zapcms\support\Asset;
use zap\facades\Url;

Asset::library('jqueryvalidation');
$this->layout('layouts/common');

$this->view->page_title = '系统设置';
$this->view->page_subtitle = '站点信息 & 第三方代码 & 邮件配置 & 文件上传 & 服务器 & 缓存';
/**
 * @var array $options
 * @var array $server
 * @var array $cache
 */
?>

<form id="zapForm">

    <!-- <div class="row g-3 mb-3">
        <div class="col-12">
            <div class="alert alert-info d-flex align-items-center mb-0 py-2 px-3 small" role="alert">
                <i class="fa fa-link me-2"></i>
                <div>
                    如需配置 URL 别名（Slug）生成规则，包括分隔符、中文转拼音等，请前往
                    <a href="<?php echo Url::action('System@slugSettings'); ?>" class="alert-link">Slug 生成设置</a> 页面。
                </div>
            </div>
        </div>
    </div> -->

    <div class="row g-3">
        <div class="col-12">
            <div class="card card-outline">
                <div class="card-header d-flex align-items-center ps-3 pt-0 pb-0 pe-0">
                    <ul class="nav nav-underline me-auto" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active" id="general-tab" data-bs-toggle="tab"
                                    data-bs-target="#general-tab-pane" type="button" role="tab"
                                    aria-controls="general-tab-pane" aria-selected="true">
                                <i class="fa fa-cog me-1"></i>站点设置
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="third-party-tab" data-bs-toggle="tab"
                                    data-bs-target="#third-party-tab-pane" type="button" role="tab"
                                    aria-controls="third-party-tab-pane" aria-selected="false">
                                <i class="fa fa-code me-1"></i>第三方代码
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="smtp-tab" data-bs-toggle="tab"
                                    data-bs-target="#smtp-tab-pane" type="button" role="tab"
                                    aria-controls="smtp-tab-pane" aria-selected="false">
                                <i class="fa fa-envelope me-1"></i>邮件设置
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="upload-tab" data-bs-toggle="tab"
                                    data-bs-target="#upload-tab-pane" type="button" role="tab"
                                    aria-controls="upload-tab-pane" aria-selected="false">
                                <i class="fa fa-cloud-upload me-1"></i>文件上传
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="server-tab" data-bs-toggle="tab"
                                    data-bs-target="#server-tab-pane" type="button" role="tab"
                                    aria-controls="server-tab-pane" aria-selected="false">
                                <i class="fa fa-server me-1"></i>服务器
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="cache-tab" data-bs-toggle="tab"
                                    data-bs-target="#cache-tab-pane" type="button" role="tab"
                                    aria-controls="cache-tab-pane" aria-selected="false">
                                <i class="fa fa-database me-1"></i>缓存
                            </button>
                        </li>
                    </ul>
                    <div class="card-tools me-2">
                        
                    </div>
                </div>

                <div class="tab-content card-body p-3">
                    <div class="tab-pane fade show active" id="general-tab-pane" role="tabpanel"
                         aria-labelledby="general-tab" tabindex="0">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label for="website.title" class="form-label small">站点名称 <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm" id="website.title" name="options[website.title]"
                                       placeholder="网站名称" required value="<?php echo e($options['website.title']); ?>" />
                            </div>
                            <div class="col-md-6">
                                <label for="website.slogan" class="form-label small">网站副标题</label>
                                <input type="text" class="form-control form-control-sm" id="website.slogan" name="options[website.slogan]"
                                       placeholder="网站副标题" value="<?php echo e($options['website.slogan']); ?>" />
                            </div>
                            <div class="col-md-6">
                                <label for="website.url" class="form-label small">网站网址</label>
                                <input type="url" class="form-control form-control-sm" id="website.url" name="options[website.url]"
                                       placeholder="https://example.com" value="<?php echo htmlspecialchars($options['website.url'] ?? '', ENT_QUOTES);?>" />
                                <div class="form-text">必须是完整的 <code>http://</code> 或 <code>https://</code> 地址，如 <code>https://example.com</code>；用于站点 URL、固定链接、附件绝对地址等，留空则自动使用当前访问地址。</div>
                            </div>
                            <div class="col-12">
                                <label for="website.keywords" class="form-label small">网站关键词</label>
                                <input type="text" class="form-control form-control-sm" id="website.keywords"
                                       placeholder="网站关键词" name="options[website.keywords]"
                                       value="<?php echo e($options['website.keywords']); ?>" />
                            </div>
                            <div class="col-12">
                                <label for="website.description" class="form-label small">网站简介</label>
                                <input type="text" class="form-control form-control-sm" id="website.description"
                                       placeholder="网站简介 (200字)" name="options[website.description]"
                                       value="<?php echo e($options['website.description']); ?>" />
                            </div>
                            <div class="col-md-6">
                                <label for="website.icp" class="form-label small">ICP备案信息</label>
                                <input type="text" class="form-control form-control-sm" id="website.icp"
                                       placeholder="ICP备案号" name="options[website.icp]"
                                       value="<?php echo e($options['website.icp']); ?>" />
                            </div>
                            <div class="col-md-6">
                                <label for="website.copyright" class="form-label small">版权信息</label>
                                <input type="text" class="form-control form-control-sm" id="website.copyright"
                                       placeholder="网站版权信息" name="options[website.copyright]"
                                       value="<?php echo e($options['website.copyright']); ?>" />
                            </div>
                            <div class="col-md-6">
                                <label for="website.address" class="form-label small">公司地址</label>
                                <input type="text" class="form-control form-control-sm" id="website.address"
                                       placeholder="公司地址" name="options[website.address]"
                                       value="<?php echo e($options['website.address']); ?>" />
                            </div>
                            <div class="col-md-6">
                                <label for="website.tel" class="form-label small">联系电话</label>
                                <input type="text" class="form-control form-control-sm" id="website.tel"
                                       placeholder="联系电话" name="options[website.tel]"
                                       value="<?php echo e($options['website.tel']); ?>" />
                            </div>
                            <div class="col-md-6">
                            <label for="website.market_api" class="form-label small">插件市场数据源</label>
                            <input type="text" class="form-control form-control-sm" id="website.market_api"
                                   placeholder="https://api.zap.cn/zapcms/v1" name="options[website.market_api]"
                                   value="<?php echo e($options['website.market_api'] ?? ''); ?>" />
                            <div class="form-text">市场数据 API 基址（如 api.zap.cn），应指向 packagemgr 的 V1 接口。留空则回退默认镜像（mirrors.zap.cn）的旧接口。插件压缩包下载走 COS（mirrors.zap.cn，默认已放行）；自托管市场无 COS 时，其下载域名需在 config 的 security.allowed_market_hosts 中放行</div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="third-party-tab-pane" role="tabpanel" aria-labelledby="third-party-tab"
                         tabindex="0">
                        <div class="row g-2">
                            <div class="col-12">
                                <label for="website.head_script" class="form-label small">顶部代码</label>
                                <textarea rows="4" class="form-control form-control-sm" id="website.head_script"
                                          name="options[website.head_script]"><?php echo e($options['website.head_script']); ?></textarea>
                                <div class="form-text">代码会放在 <?php echo _e('</head>'); ?> 标签之前</div>
                            </div>
                            <div class="col-12">
                                <label for="website.foot_script" class="form-label small">底部代码</label>
                                <textarea rows="4" class="form-control form-control-sm" id="website.foot_script"
                                          name="options[website.foot_script]"><?php echo e($options['website.foot_script']); ?></textarea>
                                <div class="form-text">代码会放在 <?php echo _e('</body>'); ?> 标签之前</div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="smtp-tab-pane" role="tabpanel" aria-labelledby="smtp-tab"
                         tabindex="0">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label for="website.smtp_host" class="form-label small">SMTP 服务器 <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm" id="website.smtp_host"
                                       name="options[website.smtp_host]"
                                       placeholder="例如 smtp.qq.com"
                                       value="<?php echo e($options['website.smtp_host'] ?? ''); ?>" />
                            </div>
                            <div class="col-md-3">
                                <label for="website.smtp_port" class="form-label small">端口</label>
                                <input type="number" class="form-control form-control-sm" id="website.smtp_port"
                                       name="options[website.smtp_port]"
                                       placeholder="587"
                                       value="<?php echo e($options['website.smtp_port'] ?? '587'); ?>" />
                            </div>
                            <div class="col-md-3">
                                <label for="website.smtp_encryption" class="form-label small">加密方式</label>
                                <select class="form-select form-select-sm" id="website.smtp_encryption"
                                        name="options[website.smtp_encryption]">
                                    <option value="tls" <?php if(($options['website.smtp_encryption'] ?? 'tls') === 'tls') echo 'selected';?>>TLS</option>
                                    <option value="ssl" <?php if(($options['website.smtp_encryption'] ?? '') === 'ssl') echo 'selected';?>>SSL</option>
                                    <option value="none" <?php if(($options['website.smtp_encryption'] ?? '') === 'none') echo 'selected';?>>无加密</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="website.smtp_user" class="form-label small">邮箱账号 <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm" id="website.smtp_user"
                                       name="options[website.smtp_user]"
                                       placeholder="yourname@example.com"
                                       value="<?php echo e($options['website.smtp_user'] ?? ''); ?>" />
                            </div>
                            <div class="col-md-6">
                                <label for="website.smtp_pass" class="form-label small">邮箱密码 / 授权码</label>
                                <input type="password" class="form-control form-control-sm" id="website.smtp_pass"
                                       name="options[website.smtp_pass]"
                                       placeholder="SMTP 授权码（非邮箱登录密码）"
                                       value="<?php echo e($options['website.smtp_pass'] ?? ''); ?>" />
                            </div>
                            <div class="col-md-6">
                                <label for="website.smtp_from" class="form-label small">发件人地址</label>
                                <input type="email" class="form-control form-control-sm" id="website.smtp_from"
                                       name="options[website.smtp_from]"
                                       placeholder="noreply@example.com"
                                       value="<?php echo e($options['website.smtp_from'] ?? ''); ?>" />
                                <div class="form-text">留空则使用邮箱账号作为发件人</div>
                            </div>
                            <div class="col-md-6">
                                <label for="website.smtp_from_name" class="form-label small">发件人名称</label>
                                <input type="text" class="form-control form-control-sm" id="website.smtp_from_name"
                                       name="options[website.smtp_from_name]"
                                       placeholder="例如：网站名称"
                                       value="<?php echo e($options['website.smtp_from_name'] ?? ''); ?>" />
                            </div>
                            <div class="col-12">
                                <hr class="my-2">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="flex-grow-1">
                                        <label for="test_email" class="form-label small mb-0">发送测试邮件到：</label>
                                    </div>
                                    <div>
                                        <div class="input-group input-group-sm">
                                            <input type="email" class="form-control form-control-sm" id="test_email"
                                                   placeholder="输入邮箱地址" style="max-width:260px;" />
                                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="sendTestEmail()">
                                                <i class="fa fa-paper-plane me-1"></i>发送测试邮件
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-text">请先保存 SMTP 配置，再发送测试邮件验证配置是否正确。</div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="upload-tab-pane" role="tabpanel" aria-labelledby="upload-tab"
                         tabindex="0">
                        <div class="row g-2">
                            <div class="col-12">
                                <label for="upload.extensions" class="form-label small">允许上传的文件扩展名</label>
                                <textarea rows="4" class="form-control form-control-sm" id="upload.extensions"
                                          name="options[upload.extensions]"
                                          placeholder="jpg,jpeg,png,zip,pdf..."><?php echo e($options['upload.extensions'] ?? 'jpg,jpeg,png,gif,webp,svg,bmp,ico,zip,rar,7z,tar,gz,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,md,csv,mp3,mp4,avi,mov,wmv,flv,webm,json,xml'); ?></textarea>
                                <div class="form-text">多个扩展名用英文逗号分隔，不需要带点；留空则使用系统默认白名单。</div>
                            </div>
                            <div class="col-md-6">
                                <label for="upload.max_size" class="form-label small">单个文件大小上限（MB）</label>
                                <input type="number" min="1" class="form-control form-control-sm" id="upload.max_size"
                                       name="options[upload.max_size]"
                                       placeholder="20" value="<?php echo e($options['upload.max_size'] ?? '20'); ?>" />
                                <div class="form-text">仅限制单个文件大小，实际受服务器 PHP upload_max_filesize 限制。</div>
                            </div>
                            <div class="col-md-6">
                                <label for="upload.name_rule" class="form-label small">文件名生成规则</label>
                                <select class="form-select form-select-sm" id="upload.name_rule"
                                        name="options[upload.name_rule]">
                                    <option value="random" <?php if(($options['upload.name_rule'] ?? 'random') === 'random') echo 'selected';?>>随机字符串（默认）</option>
                                    <option value="original" <?php if(($options['upload.name_rule'] ?? '') === 'original') echo 'selected';?>>保留原文件名</option>
                                    <option value="date" <?php if(($options['upload.name_rule'] ?? '') === 'date') echo 'selected';?>>日期 + 随机后缀</option>
                                </select>
                                <div class="form-text">「保留原文件名」模式下，同名文件会自动追加数字后缀，不会覆盖已有文件。</div>
                            </div>
                            <div class="col-md-6">
                                <label for="upload.url_mode" class="form-label small">附件 URL 模式</label>
                                <select class="form-select form-select-sm" id="upload.url_mode"
                                        name="options[upload.url_mode]">
                                    <option value="relative" <?php if(($options['upload.url_mode'] ?? 'relative') === 'relative') echo 'selected';?>>相对路径（默认）</option>
                                    <option value="absolute" <?php if(($options['upload.url_mode'] ?? '') === 'absolute') echo 'selected';?>>网站网址 + 路径</option>
                                </select>
                                <div class="form-text">「相对路径」返回 <code>/storage/xxx</code> 形式；「网站网址 + 路径」返回 <code>https://example.com/storage/xxx</code>，「网站网址」留空时自动使用当前访问域名。</div>
                            </div>

                            <div class="col-12"><hr class="my-2"><h6 class="small fw-semibold mb-2">对象存储</h6></div>
                            <div class="col-md-6">
                                <label for="upload.storage_mode" class="form-label small">存储模式</label>
                                <select class="form-select form-select-sm" id="upload.storage_mode"
                                        name="options[upload.storage_mode]">
                                    <option value="local" <?php if(($options['upload.storage_mode'] ?? 'local') === 'local') echo 'selected';?>>仅服务器（默认）</option>
                                    <option value="both" <?php if(($options['upload.storage_mode'] ?? '') === 'both') echo 'selected';?>>服务器 + 云存储</option>
                                    <option value="oss" <?php if(($options['upload.storage_mode'] ?? '') === 'oss') echo 'selected';?>>仅 OSS（阿里云）</option>
                                    <option value="cos" <?php if(($options['upload.storage_mode'] ?? '') === 'cos') echo 'selected';?>>仅 COS（腾讯云）</option>
                                    <option value="r2" <?php if(($options['upload.storage_mode'] ?? '') === 'r2') echo 'selected';?>>仅 R2（Cloudflare）</option>
                                </select>
                                <div class="form-text">「服务器 + 云存储」本地写成功即返回，云同步失败只记日志不影响上传；「仅云」本地不保留原图。</div>
                            </div>
                            <div class="col-md-6">
                                <label for="upload.cloud_driver" class="form-label small">「服务器 + 云存储」使用的云厂商</label>
                                <select class="form-select form-select-sm" id="upload.cloud_driver"
                                        name="options[upload.cloud_driver]">
                                    <option value="oss" <?php if(($options['upload.cloud_driver'] ?? 'oss') === 'oss') echo 'selected';?>>阿里云 OSS（默认）</option>
                                    <option value="cos" <?php if(($options['upload.cloud_driver'] ?? '') === 'cos') echo 'selected';?>>腾讯云 COS</option>
                                    <option value="r2" <?php if(($options['upload.cloud_driver'] ?? '') === 'r2') echo 'selected';?>>Cloudflare R2</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="upload.public_base" class="form-label small">双写模式前台使用的地址</label>
                                <select class="form-select form-select-sm" id="upload.public_base"
                                        name="options[upload.public_base]">
                                    <option value="cloud" <?php if(in_array(($options['upload.public_base'] ?? 'cloud'), ['cloud','oss'], true)) echo 'selected';?>>云 / CDN 地址（默认）</option>
                                    <option value="local" <?php if(($options['upload.public_base'] ?? '') === 'local') echo 'selected';?>>服务器本地地址</option>
                                </select>
                            </div>

                            <div class="col-12"><hr class="my-2"><h6 class="small fw-semibold mb-2 text-muted">阿里云 OSS</h6></div>
                            <div class="col-md-6">
                                <label for="upload.oss_bucket" class="form-label small">Bucket 名称</label>
                                <input type="text" class="form-control form-control-sm" id="upload.oss_bucket"
                                       name="options[upload.oss_bucket]" value="<?php echo e($options['upload.oss_bucket'] ?? ''); ?>" placeholder="my-bucket" />
                            </div>
                            <div class="col-md-6">
                                <label for="upload.oss_endpoint" class="form-label small">Endpoint</label>
                                <input type="text" class="form-control form-control-sm" id="upload.oss_endpoint"
                                       name="options[upload.oss_endpoint]" value="<?php echo e($options['upload.oss_endpoint'] ?? ''); ?>" placeholder="oss-cn-hangzhou.aliyuncs.com" />
                                <div class="form-text">填 <code>http://</code> 开头时按 HTTP 访问（内网/MinIO）。</div>
                            </div>
                            <div class="col-md-6">
                                <label for="upload.oss_base_url" class="form-label small">CDN / 自定义域名（可选）</label>
                                <input type="text" class="form-control form-control-sm" id="upload.oss_base_url"
                                       name="options[upload.oss_base_url]" value="<?php echo e($options['upload.oss_base_url'] ?? ''); ?>" placeholder="https://cdn.example.com" />
                            </div>
                            <div class="col-md-6">
                                <label for="upload.oss_prefix" class="form-label small">对象 Key 前缀（可选）</label>
                                <input type="text" class="form-control form-control-sm" id="upload.oss_prefix"
                                       name="options[upload.oss_prefix]" value="<?php echo e($options['upload.oss_prefix'] ?? ''); ?>" placeholder="cms" />
                            </div>

                            <div class="col-12"><hr class="my-2"><h6 class="small fw-semibold mb-2 text-muted">腾讯云 COS</h6></div>
                            <div class="col-md-6">
                                <label for="upload.cos_bucket" class="form-label small">Bucket 名称</label>
                                <input type="text" class="form-control form-control-sm" id="upload.cos_bucket"
                                       name="options[upload.cos_bucket]" value="<?php echo e($options['upload.cos_bucket'] ?? ''); ?>" placeholder="example-1250000000" />
                                <div class="form-text">含 APPID 的完整桶名，如 <code>example-1250000000</code>。</div>
                            </div>
                            <div class="col-md-6">
                                <label for="upload.cos_region" class="form-label small">地域（Region）</label>
                                <input type="text" class="form-control form-control-sm" id="upload.cos_region"
                                       name="options[upload.cos_region]" value="<?php echo e($options['upload.cos_region'] ?? ''); ?>" placeholder="ap-guangzhou" />
                            </div>
                            <div class="col-md-6">
                                <label for="upload.cos_base_url" class="form-label small">CDN / 自定义域名（可选）</label>
                                <input type="text" class="form-control form-control-sm" id="upload.cos_base_url"
                                       name="options[upload.cos_base_url]" value="<?php echo e($options['upload.cos_base_url'] ?? ''); ?>" placeholder="https://cdn.example.com" />
                            </div>
                            <div class="col-md-6">
                                <label for="upload.cos_prefix" class="form-label small">对象 Key 前缀（可选）</label>
                                <input type="text" class="form-control form-control-sm" id="upload.cos_prefix"
                                       name="options[upload.cos_prefix]" value="<?php echo e($options['upload.cos_prefix'] ?? ''); ?>" placeholder="cms" />
                            </div>
                            <div class="col-md-6">
                                <label for="upload.cos_thumb" class="form-label small">缩略图处理</label>
                                <select class="form-select form-select-sm" id="upload.cos_thumb"
                                        name="options[upload.cos_thumb]">
                                    <option value="none" <?php if(($options['upload.cos_thumb'] ?? 'none') === 'none') echo 'selected';?>>原图（默认）</option>
                                    <option value="ci" <?php if(($options['upload.cos_thumb'] ?? '') === 'ci') echo 'selected';?>>数据万象（CI）</option>
                                </select>
                                <div class="form-text">选「数据万象」需在 COS 控制台开启图片处理，缩略图由云端生成。</div>
                            </div>

                            <div class="col-12"><hr class="my-2"><h6 class="small fw-semibold mb-2 text-muted">Cloudflare R2</h6></div>
                            <div class="col-md-6">
                                <label for="upload.r2_account_id" class="form-label small">Account ID</label>
                                <input type="text" class="form-control form-control-sm" id="upload.r2_account_id"
                                       name="options[upload.r2_account_id]" value="<?php echo e($options['upload.r2_account_id'] ?? ''); ?>" placeholder="your-cloudflare-account-id" />
                            </div>
                            <div class="col-md-6">
                                <label for="upload.r2_bucket" class="form-label small">Bucket 名称</label>
                                <input type="text" class="form-control form-control-sm" id="upload.r2_bucket"
                                       name="options[upload.r2_bucket]" value="<?php echo e($options['upload.r2_bucket'] ?? ''); ?>" placeholder="my-bucket" />
                            </div>
                            <div class="col-md-6">
                                <label for="upload.r2_base_url" class="form-label small">公开访问地址（必填）</label>
                                <input type="text" class="form-control form-control-sm" id="upload.r2_base_url"
                                       name="options[upload.r2_base_url]" value="<?php echo e($options['upload.r2_base_url'] ?? ''); ?>" placeholder="https://pub-xxxx.r2.dev" />
                                <div class="form-text">自定义域名或 <code>https://pub-xxx.r2.dev</code>，用于前台展示。</div>
                            </div>
                            <div class="col-md-6">
                                <label for="upload.r2_prefix" class="form-label small">对象 Key 前缀（可选）</label>
                                <input type="text" class="form-control form-control-sm" id="upload.r2_prefix"
                                       name="options[upload.r2_prefix]" value="<?php echo e($options['upload.r2_prefix'] ?? ''); ?>" placeholder="cms" />
                            </div>
                            <div class="col-md-6">
                                <label for="upload.r2_thumb" class="form-label small">缩略图处理</label>
                                <select class="form-select form-select-sm" id="upload.r2_thumb"
                                        name="options[upload.r2_thumb]">
                                    <option value="none" <?php if(($options['upload.r2_thumb'] ?? 'none') === 'none') echo 'selected';?>>原图（默认）</option>
                                    <option value="resizing" <?php if(($options['upload.r2_thumb'] ?? '') === 'resizing') echo 'selected';?>>Image Resizing</option>
                                </select>
                                <div class="form-text">选「Image Resizing」需公开地址为经 Cloudflare 代理的域名，并开通 Image Resizing。</div>
                            </div>

                            <div class="col-12">
                                <div class="alert alert-light border py-2 small mb-2">
                                    各厂商均自签鉴权、无需安装 SDK。密钥不在此处保存，请配置
                                    <code>config/storage.php</code> 或环境变量：OSS 用
                                    <code>OSS_ACCESS_KEY_ID</code> / <code>OSS_ACCESS_KEY_SECRET</code>（STS 另设 <code>OSS_SECURITY_TOKEN</code>）；
                                    COS 用 <code>COS_SECRET_ID</code> / <code>COS_SECRET_KEY</code>；
                                    R2 用 <code>R2_ACCESS_KEY_ID</code> / <code>R2_SECRET_ACCESS_KEY</code>。
                                    均要求 Bucket 公共读。
                                </div>
                                <button type="button" class="btn btn-outline-primary btn-sm" id="storageTestBtn">
                                    <i class="fa fa-plug me-1"></i>测试存储连接
                                </button>
                                <span id="storageTestResult" class="small ms-2 text-muted"></span>
                            </div>
                        </div>
                        <script>
                            $(function () {
                                $('#storageTestBtn').on('click', function () {
                                    var $btn = $(this), $out = $('#storageTestResult');
                                    $btn.prop('disabled', true);
                                    $out.text('测试中…');
                                    $.getJSON('<?php echo url_action('Upload@storageTest'); ?>', function (res) {
                                        $out.text((res.ok ? '✓ ' : '✗ ') + (res.msg || ''));
                                        $out.removeClass('text-muted text-success text-danger')
                                            .addClass(res.ok ? 'text-success' : 'text-danger');
                                    }).fail(function () {
                                        $out.text('✗ 请求失败').addClass('text-danger');
                                    }).always(function () {
                                        $btn.prop('disabled', false);
                                    });
                                });
                            });
                        </script>
                    </div>

                    <div class="tab-pane fade" id="server-tab-pane" role="tabpanel" aria-labelledby="server-tab"
                         tabindex="0">
                        <div class="alert alert-info d-flex align-items-center mb-3 py-2 px-3 small" role="alert">
                            <i class="fa fa-info-circle me-2"></i>
                            <div>这些选项保存在数据库（<code>options</code> 表）中，保存后立即生效，无需修改任何配置文件。</div>
                        </div>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <div class="border rounded p-3 h-100">
                                    <div class="form-check form-switch">
                                        <input type="hidden" name="server[maintenance]" value="0">
                                        <input class="form-check-input" type="checkbox" role="switch"
                                               id="server.maintenance" name="server[maintenance]" value="1"
                                            <?php echo $server['maintenance'] ? 'checked' : '';?>>
                                        <label class="form-check-label" for="server.maintenance">
                                            维护模式 <span class="text-muted small">Maintenance Mode</span>
                                        </label>
                                    </div>
                                    <div class="form-text mb-0">开启后前台访问显示「系统维护中」（HTTP 503），后台
                                        <code>/<?php echo htmlspecialchars($server['admin_prefix'], ENT_QUOTES); ?></code> 仍可正常访问以关闭维护。</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="border rounded p-3 h-100">
                                    <label for="server.admin_prefix" class="form-label small">
                                        后台路径前缀 <span class="text-muted small">Admin Prefix</span>
                                    </label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">/</span>
                                        <input type="text" class="form-control form-control-sm" id="server.admin_prefix"
                                               name="server[admin_prefix]" placeholder="z-admin"
                                               value="<?php echo htmlspecialchars($server['admin_prefix'], ENT_QUOTES);?>" />
                                    </div>
                                    <div class="form-text mb-0">后台访问地址前缀，例如 <code>/z-admin</code>。修改后请使用新地址访问后台。</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="border rounded p-3 h-100">
                                    <div class="form-check form-switch">
                                        <input type="hidden" name="server[log]" value="0">
                                        <input class="form-check-input" type="checkbox" role="switch"
                                               id="server.log" name="server[log]" value="1"
                                            <?php echo $server['log'] ? 'checked' : '';?>>
                                        <label class="form-check-label" for="server.log">
                                            日志 <span class="text-muted small">Log</span>
                                        </label>
                                    </div>
                                    <div class="form-text mb-0">开启后记录应用运行日志；关闭后不再写入日志文件。</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="border rounded p-3 h-100">
                                    <div class="form-check form-switch">
                                        <input type="hidden" name="server[debug]" value="0">
                                        <input class="form-check-input" type="checkbox" role="switch"
                                               id="server.debug" name="server[debug]" value="1"
                                            <?php echo $server['debug'] ? 'checked' : '';?>>
                                        <label class="form-check-label" for="server.debug">
                                            调试模式 <span class="text-muted small">Debug</span>
                                        </label>
                                    </div>
                                    <div class="form-text mb-0">开启后显示详细错误信息（生产环境请关闭）。</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="cache-tab-pane" role="tabpanel" aria-labelledby="cache-tab"
                         tabindex="0">
                        <div class="alert alert-info d-flex align-items-center mb-3 py-2 px-3 small" role="alert">
                            <i class="fa fa-info-circle me-2"></i>
                            <div>缓存配置保存在数据库（<code>options</code> 表）中，默认使用文件缓存；启用缓存可提升站点性能。</div>
                        </div>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label for="cache.status" class="form-label small">缓存状态</label>
                                <select class="form-select form-select-sm" id="cache.status" name="cache[status]">
                                    <option value="disabled" <?php echo $cache['status'] === 'disabled' ? 'selected' : '';?>>禁用</option>
                                    <option value="enabled" <?php echo $cache['status'] === 'enabled' ? 'selected' : '';?>>启用</option>
                                </select>
                                <div class="form-text mb-0">启用后 options 等数据将缓存到文件或 Redis，减少数据库查询。</div>
                            </div>
                            <div class="col-md-6">
                                <label for="cache.default" class="form-label small">缓存驱动</label>
                                <select class="form-select form-select-sm" id="cache.default" name="cache[default]">
                                    <option value="file" <?php echo $cache['default'] === 'file' ? 'selected' : '';?>>文件缓存 (File)</option>
                                    <option value="redis" <?php echo $cache['default'] === 'redis' ? 'selected' : '';?>>Redis 缓存</option>
                                </select>
                                <div class="form-text mb-0">选择 Redis 时需填写下方连接信息。</div>
                            </div>
                            <div class="col-md-6">
                                <label for="cache.ttl" class="form-label small">缓存过期时间（秒）</label>
                                <input type="number" min="0" class="form-control form-control-sm" id="cache.ttl"
                                       name="cache[ttl]" placeholder="0" value="<?php echo (int)$cache['ttl'];?>" />
                                <div class="form-text mb-0">0 表示使用默认值（10000 秒）。</div>
                            </div>
                        </div>
                        <hr class="my-2">
                        <h6 class="text-muted small mb-2"><i class="fa fa-server me-1"></i>Redis 连接信息</h6>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label for="cache.redis_host" class="form-label small">Redis 主机</label>
                                <input type="text" class="form-control form-control-sm" id="cache.redis_host"
                                       name="cache[redis_host]" placeholder="127.0.0.1"
                                       value="<?php echo htmlspecialchars($cache['redis_host'], ENT_QUOTES);?>" />
                            </div>
                            <div class="col-md-3">
                                <label for="cache.redis_port" class="form-label small">端口</label>
                                <input type="number" min="1" max="65535" class="form-control form-control-sm"
                                       id="cache.redis_port" name="cache[redis_port]" placeholder="6379"
                                       value="<?php echo (int)$cache['redis_port'];?>" />
                            </div>
                            <div class="col-md-3">
                                <label for="cache.redis_database" class="form-label small">数据库</label>
                                <input type="number" min="0" class="form-control form-control-sm"
                                       id="cache.redis_database" name="cache[redis_database]" placeholder="0"
                                       value="<?php echo (int)$cache['redis_database'];?>" />
                            </div>
                            <div class="col-md-6">
                                <label for="cache.redis_password" class="form-label small">密码</label>
                                <input type="password" class="form-control form-control-sm" id="cache.redis_password"
                                       name="cache[redis_password]" placeholder="留空表示无需密码"
                                       value="<?php echo htmlspecialchars($cache['redis_password'], ENT_QUOTES);?>" />
                            </div>
                            <div class="col-12">
                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="clearCache()">
                                    <i class="fa fa-trash-o me-1"></i>清空缓存
                                </button>
                                <span class="form-text ms-2">清除当前缓存驱动中的所有缓存数据（options、路由等）。</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer text-center">
                    <button type="button" class="btn btn-success btn-sm" onclick="save()">
                        <i class="fa fa-save me-1"></i>保存设置
                    </button>
                </div>
            </div>
        </div>
    </div>

</form>

<script>
    var mailTestUrl = '<?php echo Url::action('System@mailTest');?>';
    var cacheClearUrl = '<?php echo Url::action('System@cacheClear');?>';

    $(function(){
        $('#zapForm').validate({ignore:''});
    })

    function clearCache(){
        const load = Zap.loading('正在清空缓存，请稍后');
        $.ajax({
            url: cacheClearUrl,
            method: 'post',
            dataType: 'json',
            success: function (data) {
                if (data.code === 0) {
                    ZapToast.alert(data.msg, {bgColor: bgSuccess, position: Toast_Pos_Center});
                } else {
                    ZapToast.alert(data.msg, {bgColor: bgDanger, position: Toast_Pos_Center});
                }
            }
        }).always(function () {
            load.dispose()
        });
    }

    function save(){
        const zapForm = $('#zapForm');
        if (!zapForm.valid()) {
            ZapToast.alert('必填项不能为空', {bgColor: bgDanger, position: Toast_Pos_Center});
            return false;
        }
        const load = Zap.loading('正在保存，请稍后');
        $.ajax({
            url: '<?php echo Url::current();?>',
            method: 'post',
            data: zapForm.serialize(),
            dataType: 'json',
            success: function (data) {
                if (data.code === 0) {
                    ZapToast.alert(data.msg, {bgColor: bgSuccess, position: Toast_Pos_Center});
                } else {
                    ZapToast.alert(data.msg, {bgColor: bgDanger, position: Toast_Pos_Center});
                }
            }
        }).always(function () {
            load.dispose()
        });
    }

    function sendTestEmail(){
        const testEmail = $('#test_email').val().trim();
        if (!testEmail) {
            ZapToast.alert('请输入测试邮箱地址', {bgColor: bgWarning, position: Toast_Pos_Center});
            return false;
        }
        // 简单邮箱校验
        const emailReg = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailReg.test(testEmail)) {
            ZapToast.alert('请输入有效的邮箱地址', {bgColor: bgWarning, position: Toast_Pos_Center});
            return false;
        }
        const load = Zap.loading('正在发送测试邮件，请稍后');
        $.ajax({
            url: mailTestUrl,
            method: 'post',
            data: {test_email: testEmail},
            dataType: 'json',
            success: function (data) {
                if (data.code === 0) {
                    ZapToast.alert(data.msg, {bgColor: bgSuccess, position: Toast_Pos_Center});
                } else {
                    ZapToast.alert(data.msg, {bgColor: bgDanger, position: Toast_Pos_Center});
                }
            }
        }).always(function () {
            load.dispose()
        });
    }
</script>
