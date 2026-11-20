<?php

use zapcms\support\Asset;
use zap\facades\Url;

Asset::library('jqueryvalidation');
!IS_AJAX && $this->layout('layouts/common');

$this->view->page_title = '系统设置';
$this->view->page_subtitle = '站点信息 & 统计与嵌入代码 & 邮件配置 & 短信配置 & 文件上传 & 系统 & 缓存';
/**
 * @var array $options
 * @var array $server
 * @var array $cache
 */
?>

<style>
.provider-toggle { justify-content: space-between; }
.provider-toggle[aria-expanded="true"] .chevron { transform: rotate(180deg); }
.provider-toggle .chevron { transition: transform .2s ease; }
.provider-block { scroll-margin-top: 80px; }
</style>

<form id="zapForm">


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
                                <i class="fa fa-code me-1"></i>统计与嵌入代码
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
                            <button class="nav-link" id="sms-tab" data-bs-toggle="tab"
                                    data-bs-target="#sms-tab-pane" type="button" role="tab"
                                    aria-controls="sms-tab-pane" aria-selected="false">
                                <i class="fa fa-comment me-1"></i>短信设置
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
                                <i class="fa fa-sliders me-1"></i>系统
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
                        <div class="alert alert-light border py-2 small mb-3">
                            邮件发送现在支持多服务商并自动故障转移：选择「默认服务商」，可额外填写「备用服务商」（逗号分隔，主用失败自动切换）。各家 API 密钥将以加密形式存储，绝不回显明文。
                            <div class="mt-2 d-flex flex-wrap align-items-center gap-2">
                                <span class="badge text-bg-primary">默认</span><span class="text-muted">当前主用服务商</span>
                                <span class="badge text-bg-warning">备用</span><span class="text-muted">主用失败时的候选</span>
                                <span class="badge text-bg-success">已配置</span><span class="text-muted">已填写凭据（密钥已保存）</span>
                            </div>
                        </div>
                        <?php
                        $mailCfg = [
                            'smtp'     => !empty($options['website.smtp_host']) && !empty($options['website.smtp_user']),
                            'sendgrid' => !empty($options['mail.sendgrid_from']) && !empty($secrets_configured['mail.sendgrid_key']),
                            'mailgun'  => !empty($options['mail.mailgun_domain']) && !empty($options['mail.mailgun_from']) && !empty($secrets_configured['mail.mailgun_key']),
                            'resend'   => !empty($options['mail.resend_from']) && !empty($secrets_configured['mail.resend_key']),
                            'tencent'  => !empty($options['mail.tencent_from']) && !empty($secrets_configured['mail.tencent_secret_id']),
                            'aliyun'   => !empty($options['mail.aliyun_from']) && !empty($secrets_configured['mail.aliyun_access_key']),
                        ];
                        ?>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label for="mail.default" class="form-label small">默认服务商</label>
                                <select class="form-select form-select-sm" id="mail.default" name="options[mail.default]">
                                    <option value="smtp" <?php if(($options['mail.default'] ?? 'smtp') === 'smtp') echo 'selected';?>>SMTP（默认）</option>
                                    <option value="sendgrid" <?php if(($options['mail.default'] ?? '') === 'sendgrid') echo 'selected';?>>SendGrid</option>
                                    <option value="mailgun" <?php if(($options['mail.default'] ?? '') === 'mailgun') echo 'selected';?>>Mailgun</option>
                                    <option value="resend" <?php if(($options['mail.default'] ?? '') === 'resend') echo 'selected';?>>Resend</option>
                                    <option value="tencent" <?php if(($options['mail.default'] ?? '') === 'tencent') echo 'selected';?>>腾讯云邮件推送</option>
                                    <option value="aliyun" <?php if(($options['mail.default'] ?? '') === 'aliyun') echo 'selected';?>>阿里云邮件推送</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="mail.failover" class="form-label small">备用服务商（逗号分隔）</label>
                                <input type="text" class="form-control form-control-sm" id="mail.failover" name="options[mail.failover]"
                                       placeholder="如 smtp,sendgrid" value="<?php echo e($options['mail.failover'] ?? ''); ?>" />
                                <div class="form-text">主用失败时的候选，按顺序尝试（可选值：smtp, sendgrid, mailgun, resend, tencent, aliyun）。</div>
                            </div>
                        </div>
                        <hr class="my-3">
                        <div class="border rounded p-2 bg-body-tertiary provider-block" data-provider="smtp">
                            <h6 class="small fw-semibold mb-2 d-flex align-items-center flex-wrap"><i class="fa fa-envelope me-1"></i>SMTP
                                <span class="badge text-bg-primary ms-1" data-badge="default" hidden>默认</span>
                                <span class="badge text-bg-warning ms-1" data-badge="failover" hidden>备用</span>
                                <span class="badge text-bg-success ms-1" data-badge="configured"<?php echo $mailCfg['smtp'] ? '' : ' hidden';?>>已配置</span>
                            </h6>
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
                                <label for="mail.smtp_pass" class="form-label small">邮箱密码 / 授权码</label>
                                <input type="password" autocomplete="new-password" class="form-control form-control-sm" id="mail.smtp_pass"
                                       name="secrets[mail.smtp_pass]"
                                       placeholder="<?php echo !empty($secrets_configured['mail.smtp_pass']) ? '已配置，留空保持不变' : 'SMTP 授权码（非邮箱登录密码）'; ?>" />
                                <?php if ($crypto_available && !empty($secrets_configured['mail.smtp_pass'])): ?>
                                <label class="form-check form-check-inline small mb-0 mt-1">
                                    <input type="checkbox" class="form-check-input" name="secrets_clear[]" value="mail_smtp">
                                    <span class="form-check-label text-danger">清除已保存的密码</span>
                                </label>
                                <?php elseif (!$crypto_available): ?>
                                <div class="form-text text-warning mt-1">未配置 <code>app_key</code>，密码无法加密保存，请先设置 <code>config/app_key</code>。</div>
                                <?php endif; ?>
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

                        <hr class="my-3">
                        <div class="border rounded p-2 bg-body-tertiary provider-block" data-provider="sendgrid">
                            <h6 class="small fw-semibold mb-2 d-flex align-items-center flex-wrap">SendGrid
                                <span class="badge text-bg-primary ms-1" data-badge="default" hidden>默认</span>
                                <span class="badge text-bg-warning ms-1" data-badge="failover" hidden>备用</span>
                                <span class="badge text-bg-success ms-1" data-badge="configured"<?php echo $mailCfg['sendgrid'] ? '' : ' hidden';?>>已配置</span>
                            </h6>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label for="mail.sendgrid_from" class="form-label small">发件人地址</label>
                                    <input type="email" class="form-control form-control-sm" id="mail.sendgrid_from" name="options[mail.sendgrid_from]" placeholder="noreply@example.com" value="<?php echo e($options['mail.sendgrid_from'] ?? ''); ?>" />
                                </div>
                                <div class="col-md-6">
                                    <label for="secret.mail.sendgrid_key" class="form-label small">API Key</label>
                                    <input type="password" autocomplete="new-password" class="form-control form-control-sm" id="secret.mail.sendgrid_key" name="secrets[mail.sendgrid_key]" placeholder="<?php echo !empty($secrets_configured['mail.sendgrid_key']) ? '已配置，留空保持不变' : 'SendGrid API Key'; ?>" />
                                    <?php if ($crypto_available && !empty($secrets_configured['mail.sendgrid_key'])): ?>
                                    <label class="form-check form-check-inline small mb-0 mt-1">
                                        <input type="checkbox" class="form-check-input" name="secrets_clear[]" value="mail_sendgrid">
                                        <span class="form-check-label text-danger">清除已保存的密钥</span>
                                    </label>
                                    <?php elseif (!$crypto_available): ?>
                                    <div class="form-text text-warning mt-1">未配置 <code>app_key</code>，密钥无法加密保存，请先设置 <code>config/app_key</code>。</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <hr class="my-3">
                        <div class="border rounded p-2 bg-body-tertiary provider-block" data-provider="mailgun">
                            <h6 class="small fw-semibold mb-2 d-flex align-items-center flex-wrap">Mailgun
                                <span class="badge text-bg-primary ms-1" data-badge="default" hidden>默认</span>
                                <span class="badge text-bg-warning ms-1" data-badge="failover" hidden>备用</span>
                                <span class="badge text-bg-success ms-1" data-badge="configured"<?php echo $mailCfg['mailgun'] ? '' : ' hidden';?>>已配置</span>
                            </h6>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label for="mail.mailgun_domain" class="form-label small">Domain</label>
                                    <input type="text" class="form-control form-control-sm" id="mail.mailgun_domain" name="options[mail.mailgun_domain]" placeholder="mg.example.com" value="<?php echo e($options['mail.mailgun_domain'] ?? ''); ?>" />
                                </div>
                                <div class="col-md-3">
                                    <label for="mail.mailgun_region" class="form-label small">区域</label>
                                    <select class="form-select form-select-sm" id="mail.mailgun_region" name="options[mail.mailgun_region]">
                                        <option value="us" <?php if(($options['mail.mailgun_region'] ?? 'us') === 'us') echo 'selected';?>>US</option>
                                        <option value="eu" <?php if(($options['mail.mailgun_region'] ?? '') === 'eu') echo 'selected';?>>EU</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="mail.mailgun_from" class="form-label small">发件人地址</label>
                                    <input type="email" class="form-control form-control-sm" id="mail.mailgun_from" name="options[mail.mailgun_from]" placeholder="noreply@example.com" value="<?php echo e($options['mail.mailgun_from'] ?? ''); ?>" />
                                </div>
                                <div class="col-md-6">
                                    <label for="secret.mail.mailgun_key" class="form-label small">API Key</label>
                                    <input type="password" autocomplete="new-password" class="form-control form-control-sm" id="secret.mail.mailgun_key" name="secrets[mail.mailgun_key]" placeholder="<?php echo !empty($secrets_configured['mail.mailgun_key']) ? '已配置，留空保持不变' : 'Mailgun API Key'; ?>" />
                                    <?php if ($crypto_available && !empty($secrets_configured['mail.mailgun_key'])): ?>
                                    <label class="form-check form-check-inline small mb-0 mt-1">
                                        <input type="checkbox" class="form-check-input" name="secrets_clear[]" value="mail_mailgun">
                                        <span class="form-check-label text-danger">清除已保存的密钥</span>
                                    </label>
                                    <?php elseif (!$crypto_available): ?>
                                    <div class="form-text text-warning mt-1">未配置 <code>app_key</code>，密钥无法加密保存，请先设置 <code>config/app_key</code>。</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <hr class="my-3">
                        <div class="border rounded p-2 bg-body-tertiary provider-block" data-provider="resend">
                            <h6 class="small fw-semibold mb-2 d-flex align-items-center flex-wrap">Resend
                                <span class="badge text-bg-primary ms-1" data-badge="default" hidden>默认</span>
                                <span class="badge text-bg-warning ms-1" data-badge="failover" hidden>备用</span>
                                <span class="badge text-bg-success ms-1" data-badge="configured"<?php echo $mailCfg['resend'] ? '' : ' hidden';?>>已配置</span>
                            </h6>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label for="mail.resend_from" class="form-label small">发件人地址</label>
                                    <input type="email" class="form-control form-control-sm" id="mail.resend_from" name="options[mail.resend_from]" placeholder="noreply@example.com" value="<?php echo e($options['mail.resend_from'] ?? ''); ?>" />
                                </div>
                                <div class="col-md-6">
                                    <label for="secret.mail.resend_key" class="form-label small">API Key</label>
                                    <input type="password" autocomplete="new-password" class="form-control form-control-sm" id="secret.mail.resend_key" name="secrets[mail.resend_key]" placeholder="<?php echo !empty($secrets_configured['mail.resend_key']) ? '已配置，留空保持不变' : 'Resend API Key'; ?>" />
                                    <?php if ($crypto_available && !empty($secrets_configured['mail.resend_key'])): ?>
                                    <label class="form-check form-check-inline small mb-0 mt-1">
                                        <input type="checkbox" class="form-check-input" name="secrets_clear[]" value="mail_resend">
                                        <span class="form-check-label text-danger">清除已保存的密钥</span>
                                    </label>
                                    <?php elseif (!$crypto_available): ?>
                                    <div class="form-text text-warning mt-1">未配置 <code>app_key</code>，密钥无法加密保存，请先设置 <code>config/app_key</code>。</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <hr class="my-3">
                        <div class="border rounded p-2 bg-body-tertiary provider-block" data-provider="tencent">
                            <h6 class="small fw-semibold mb-2 d-flex align-items-center flex-wrap">腾讯云邮件推送（SES）
                                <span class="badge text-bg-primary ms-1" data-badge="default" hidden>默认</span>
                                <span class="badge text-bg-warning ms-1" data-badge="failover" hidden>备用</span>
                                <span class="badge text-bg-success ms-1" data-badge="configured"<?php echo $mailCfg['tencent'] ? '' : ' hidden';?>>已配置</span>
                            </h6>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label for="mail.tencent_from" class="form-label small">发件人地址</label>
                                    <input type="email" class="form-control form-control-sm" id="mail.tencent_from" name="options[mail.tencent_from]" placeholder="noreply@your-verified-domain.com" value="<?php echo e($options['mail.tencent_from'] ?? ''); ?>" />
                                </div>
                                <div class="col-md-3">
                                    <label for="mail.tencent_region" class="form-label small">地域</label>
                                    <input type="text" class="form-control form-control-sm" id="mail.tencent_region" name="options[mail.tencent_region]" placeholder="ap-hongkong" value="<?php echo e($options['mail.tencent_region'] ?? 'ap-hongkong'); ?>" />
                                </div>
                                <div class="col-md-6">
                                    <label for="secret.mail.tencent_secret_id" class="form-label small">SecretId</label>
                                    <input type="password" autocomplete="new-password" class="form-control form-control-sm" id="secret.mail.tencent_secret_id" name="secrets[mail.tencent_secret_id]" placeholder="<?php echo !empty($secrets_configured['mail.tencent_secret_id']) ? '已配置，留空保持不变' : 'SecretId'; ?>" />
                                </div>
                                <div class="col-md-6">
                                    <label for="secret.mail.tencent_secret_key" class="form-label small">SecretKey</label>
                                    <input type="password" autocomplete="new-password" class="form-control form-control-sm" id="secret.mail.tencent_secret_key" name="secrets[mail.tencent_secret_key]" placeholder="<?php echo !empty($secrets_configured['mail.tencent_secret_key']) ? '已配置，留空保持不变' : 'SecretKey'; ?>" />
                                    <?php if ($crypto_available && !empty($secrets_configured['mail.tencent_secret_id'])): ?>
                                    <label class="form-check form-check-inline small mb-0 mt-1">
                                        <input type="checkbox" class="form-check-input" name="secrets_clear[]" value="mail_tencent">
                                        <span class="form-check-label text-danger">清除已保存的密钥</span>
                                    </label>
                                    <?php elseif (!$crypto_available): ?>
                                    <div class="form-text text-warning mt-1">未配置 <code>app_key</code>，密钥无法加密保存，请先设置 <code>config/app_key</code>。</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <hr class="my-3">
                        <div class="border rounded p-2 bg-body-tertiary provider-block" data-provider="aliyun">
                            <h6 class="small fw-semibold mb-2 d-flex align-items-center flex-wrap">阿里云邮件推送（DirectMail）
                                <span class="badge text-bg-primary ms-1" data-badge="default" hidden>默认</span>
                                <span class="badge text-bg-warning ms-1" data-badge="failover" hidden>备用</span>
                                <span class="badge text-bg-success ms-1" data-badge="configured"<?php echo $mailCfg['aliyun'] ? '' : ' hidden';?>>已配置</span>
                            </h6>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label for="mail.aliyun_from" class="form-label small">发信地址（AccountName）</label>
                                    <input type="email" class="form-control form-control-sm" id="mail.aliyun_from" name="options[mail.aliyun_from]" placeholder="noreply@your-verified-domain.com" value="<?php echo e($options['mail.aliyun_from'] ?? ''); ?>" />
                                </div>
                                <div class="col-md-6">
                                    <label for="mail.aliyun_tag" class="form-label small">标签（可选）</label>
                                    <input type="text" class="form-control form-control-sm" id="mail.aliyun_tag" name="options[mail.aliyun_tag]" placeholder="如 register" value="<?php echo e($options['mail.aliyun_tag'] ?? ''); ?>" />
                                </div>
                                <div class="col-md-6">
                                    <label for="secret.mail.aliyun_access_key" class="form-label small">AccessKey ID</label>
                                    <input type="password" autocomplete="new-password" class="form-control form-control-sm" id="secret.mail.aliyun_access_key" name="secrets[mail.aliyun_access_key]" placeholder="<?php echo !empty($secrets_configured['mail.aliyun_access_key']) ? '已配置，留空保持不变' : 'AccessKey ID'; ?>" />
                                </div>
                                <div class="col-md-6">
                                    <label for="secret.mail.aliyun_access_secret" class="form-label small">AccessKey Secret</label>
                                    <input type="password" autocomplete="new-password" class="form-control form-control-sm" id="secret.mail.aliyun_access_secret" name="secrets[mail.aliyun_access_secret]" placeholder="<?php echo !empty($secrets_configured['mail.aliyun_access_secret']) ? '已配置，留空保持不变' : 'AccessKey Secret'; ?>" />
                                    <?php if ($crypto_available && !empty($secrets_configured['mail.aliyun_access_key'])): ?>
                                    <label class="form-check form-check-inline small mb-0 mt-1">
                                        <input type="checkbox" class="form-check-input" name="secrets_clear[]" value="mail_aliyun">
                                        <span class="form-check-label text-danger">清除已保存的密钥</span>
                                    </label>
                                    <?php elseif (!$crypto_available): ?>
                                    <div class="form-text text-warning mt-1">未配置 <code>app_key</code>，密钥无法加密保存，请先设置 <code>config/app_key</code>。</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="upload-tab-pane" role="tabpanel" aria-labelledby="upload-tab"
                         tabindex="0">
                        <div class="row g-2">
                            <div class="col-12"><h6 class="small fw-semibold mb-0">基础设置</h6></div>
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

                            <div class="col-12"><hr class="my-2"><h6 class="small fw-semibold mb-2">存储方式</h6></div>
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
                            <div class="col-md-6 js-both-only">
                                <label for="upload.cloud_driver" class="form-label small">「服务器 + 云存储」使用的云厂商</label>
                                <select class="form-select form-select-sm" id="upload.cloud_driver"
                                        name="options[upload.cloud_driver]">
                                    <option value="oss" <?php if(($options['upload.cloud_driver'] ?? 'oss') === 'oss') echo 'selected';?>>阿里云 OSS（默认）</option>
                                    <option value="cos" <?php if(($options['upload.cloud_driver'] ?? '') === 'cos') echo 'selected';?>>腾讯云 COS</option>
                                    <option value="r2" <?php if(($options['upload.cloud_driver'] ?? '') === 'r2') echo 'selected';?>>Cloudflare R2</option>
                                </select>
                            </div>
                            <div class="col-md-6 js-both-only">
                                <label for="upload.public_base" class="form-label small">双写模式前台使用的地址</label>
                                <select class="form-select form-select-sm" id="upload.public_base"
                                        name="options[upload.public_base]">
                                    <option value="cloud" <?php if(in_array(($options['upload.public_base'] ?? 'cloud'), ['cloud','oss'], true)) echo 'selected';?>>云 / CDN 地址（默认）</option>
                                    <option value="local" <?php if(($options['upload.public_base'] ?? '') === 'local') echo 'selected';?>>服务器本地地址</option>
                                </select>
                            </div>

                            <div class="col-12 js-provider-block" data-provider="oss" style="display:none;">
                                <div class="border rounded p-2 bg-body-tertiary">
                                <h6 class="small fw-semibold mb-2">阿里云 OSS</h6>
                                <div class="row g-2">
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
                            <?php if ($crypto_available): ?>
                            <div class="col-md-6">
                                <label for="secret.oss.access_key_id" class="form-label small">AccessKey ID</label>
                                <input type="password" autocomplete="new-password" class="form-control form-control-sm" id="secret.oss.access_key_id"
                                       name="secrets[storage.oss.access_key_id]"
                                       placeholder="<?php echo !empty($secrets_configured['storage.oss.access_key_id']) ? '已配置，留空保持不变' : 'AccessKey ID'; ?>" />
                            </div>
                            <div class="col-md-6">
                                <label for="secret.oss.access_key_secret" class="form-label small">AccessKey Secret</label>
                                <input type="password" autocomplete="new-password" class="form-control form-control-sm" id="secret.oss.access_key_secret"
                                       name="secrets[storage.oss.access_key_secret]"
                                       placeholder="<?php echo !empty($secrets_configured['storage.oss.access_key_secret']) ? '已配置，留空保持不变' : 'AccessKey Secret'; ?>" />
                            </div>
                            <div class="col-md-6">
                                <label for="secret.oss.security_token" class="form-label small">Security Token（STS 可选）</label>
                                <input type="password" autocomplete="new-password" class="form-control form-control-sm" id="secret.oss.security_token"
                                       name="secrets[storage.oss.security_token]"
                                       placeholder="<?php echo !empty($secrets_configured['storage.oss.security_token']) ? '已配置，留空保持不变' : 'Security Token'; ?>" />
                            </div>
                            <div class="col-12">
                                <label class="form-check form-check-inline small mb-0">
                                    <input type="checkbox" class="form-check-input" name="secrets_clear[]" value="oss">
                                    <span class="form-check-label text-danger">清除已保存的 OSS 密钥</span>
                                </label>
                            </div>
                            <?php endif; ?>
                                </div></div>
                            </div>

                            <div class="col-12 js-provider-block" data-provider="cos" style="display:none;">
                                <div class="border rounded p-2 bg-body-tertiary">
                                <h6 class="small fw-semibold mb-2">腾讯云 COS</h6>
                                <div class="row g-2">
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
                            <?php if ($crypto_available): ?>
                            <div class="col-md-6">
                                <label for="secret.cos.secret_id" class="form-label small">SecretId</label>
                                <input type="password" autocomplete="new-password" class="form-control form-control-sm" id="secret.cos.secret_id"
                                       name="secrets[storage.cos.secret_id]"
                                       placeholder="<?php echo !empty($secrets_configured['storage.cos.secret_id']) ? '已配置，留空保持不变' : 'SecretId'; ?>" />
                            </div>
                            <div class="col-md-6">
                                <label for="secret.cos.secret_key" class="form-label small">SecretKey</label>
                                <input type="password" autocomplete="new-password" class="form-control form-control-sm" id="secret.cos.secret_key"
                                       name="secrets[storage.cos.secret_key]"
                                       placeholder="<?php echo !empty($secrets_configured['storage.cos.secret_key']) ? '已配置，留空保持不变' : 'SecretKey'; ?>" />
                            </div>
                            <div class="col-12">
                                <label class="form-check form-check-inline small mb-0">
                                    <input type="checkbox" class="form-check-input" name="secrets_clear[]" value="cos">
                                    <span class="form-check-label text-danger">清除已保存的 COS 密钥</span>
                                </label>
                            </div>
                            <?php endif; ?>
                                </div></div>
                            </div>

                            <div class="col-12 js-provider-block" data-provider="r2" style="display:none;">
                                <div class="border rounded p-2 bg-body-tertiary">
                                <h6 class="small fw-semibold mb-2">Cloudflare R2</h6>
                                <div class="row g-2">
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
                            <?php if ($crypto_available): ?>
                            <div class="col-md-6">
                                <label for="secret.r2.access_key_id" class="form-label small">Access Key ID</label>
                                <input type="password" autocomplete="new-password" class="form-control form-control-sm" id="secret.r2.access_key_id"
                                       name="secrets[storage.r2.access_key_id]"
                                       placeholder="<?php echo !empty($secrets_configured['storage.r2.access_key_id']) ? '已配置，留空保持不变' : 'Access Key ID'; ?>" />
                            </div>
                            <div class="col-md-6">
                                <label for="secret.r2.secret_access_key" class="form-label small">Secret Access Key</label>
                                <input type="password" autocomplete="new-password" class="form-control form-control-sm" id="secret.r2.secret_access_key"
                                       name="secrets[storage.r2.secret_access_key]"
                                       placeholder="<?php echo !empty($secrets_configured['storage.r2.secret_access_key']) ? '已配置，留空保持不变' : 'Secret Access Key'; ?>" />
                            </div>
                            <div class="col-12">
                                <label class="form-check form-check-inline small mb-0">
                                    <input type="checkbox" class="form-check-input" name="secrets_clear[]" value="r2">
                                    <span class="form-check-label text-danger">清除已保存的 R2 密钥</span>
                                </label>
                            </div>
                            <?php endif; ?>
                                </div></div>
                            </div>

                            <div class="col-12 js-cloud-only">
                                <div class="alert alert-light border py-2 small mb-2">
                                    <?php if ($crypto_available): ?>
                                    密钥可直接在上方各厂商面板填写，会用 <code>config/app_key</code> 派生密钥加密后存入数据库（<code>secret.*</code>，不参与自动加载），读取时解密，绝不回显明文；留空表示保留原值。
                                    <?php else: ?>
                                    <strong>尚未配置加密密钥。</strong>请在 <code>config/config.php</code> 中设置 <code>'app_key' => '随机字符串'</code>（或环境变量 <code>ZAP_APP_KEY</code>），才能在此页面填写并加密存储密钥；在此之前请改用 <code>config/storage.php</code> 或环境变量配置密钥。
                                    <?php endif; ?>
                                    <div class="mt-1 text-muted">各厂商均自签鉴权、无需安装 SDK，且要求 Bucket 公共读。环境变量兜底：OSS 用 <code>OSS_ACCESS_KEY_ID</code>/<code>OSS_ACCESS_KEY_SECRET</code>，COS 用 <code>COS_SECRET_ID</code>/<code>COS_SECRET_KEY</code>，R2 用 <code>R2_ACCESS_KEY_ID</code>/<code>R2_SECRET_ACCESS_KEY</code>。</div>
                                </div>
                                <button type="button" class="btn btn-outline-primary btn-sm" id="storageTestBtn">
                                    <i class="fa fa-plug me-1"></i>测试存储连接
                                </button>
                                <span id="storageTestResult" class="small ms-2 text-muted"></span>
                            </div>
                        </div>
                        <script>
                            $(function () {
                                // 按存储模式显示对应配置：仅当前云厂商的面板可见
                                function updateUploadPanels() {
                                    // 注意：控件 id 含点号（upload.storage_mode），
                                    // jQuery 选择器里会被当成 class，必须用 getElementById 取
                                    var mode = document.getElementById('upload.storage_mode').value;
                                    var driver = document.getElementById('upload.cloud_driver').value || 'oss';
                                    var isBoth = mode === 'both';
                                    var isCloud = isBoth || mode === 'oss' || mode === 'cos' || mode === 'r2';
                                    $('#upload-tab-pane .js-both-only').toggle(isBoth);
                                    $('#upload-tab-pane .js-cloud-only').toggle(isCloud);
                                    $('#upload-tab-pane .js-provider-block').each(function () {
                                        var p = $(this).data('provider');
                                        $(this).toggle(isCloud && (isBoth ? driver === p : mode === p));
                                    });
                                }
                                $(document.getElementById('upload.storage_mode')).on('change', updateUploadPanels);
                                $(document.getElementById('upload.cloud_driver')).on('change', updateUploadPanels);
                                updateUploadPanels();

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
                                <input type="password" autocomplete="new-password" class="form-control form-control-sm" id="cache.redis_password"
                                       name="secrets[cache.redis_password]"
                                       placeholder="<?php echo !empty($secrets_configured['cache.redis_password']) ? '已配置，留空保持不变' : '留空表示无需密码'; ?>" />
                                <?php if ($crypto_available && !empty($secrets_configured['cache.redis_password'])): ?>
                                <label class="form-check form-check-inline small mb-0 mt-1">
                                    <input type="checkbox" class="form-check-input" name="secrets_clear[]" value="cache">
                                    <span class="form-check-label text-danger">清除已保存的密码</span>
                                </label>
                                <?php elseif (!$crypto_available): ?>
                                <div class="form-text text-warning mt-1">未配置 <code>app_key</code>，密码无法加密保存，请先设置 <code>config/app_key</code>。</div>
                                <?php endif; ?>
                            </div>
                            <div class="col-12">
                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="clearCache()">
                                    <i class="fa fa-trash-o me-1"></i>清空缓存
                                </button>
                                <span class="form-text ms-2">清除当前缓存驱动中的所有缓存数据（options、路由等）。</span>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="sms-tab-pane" role="tabpanel" aria-labelledby="sms-tab" tabindex="0">
                        <div class="alert alert-light border py-2 small mb-3">
                            短信为「模板 + 签名」模式：请先在对应云控制台申请签名与模板，再在此填写。配置主/备服务商后，发送失败自动切换。
                            <div class="mt-2 d-flex flex-wrap align-items-center gap-2">
                                <span class="badge text-bg-primary">默认</span><span class="text-muted">当前主用服务商</span>
                                <span class="badge text-bg-warning">备用</span><span class="text-muted">主用失败时的候选</span>
                                <span class="badge text-bg-success">已配置</span><span class="text-muted">已填写凭据（密钥已保存）</span>
                            </div>
                        </div>
                        <?php
                        $smsCfg = [
                            'tencent' => !empty($options['sms.tencent_app_id']) && !empty($options['sms.tencent_sign']) && !empty($secrets_configured['sms.tencent_secret_id']),
                            'aliyun'  => !empty($options['sms.aliyun_sign']) && !empty($secrets_configured['sms.aliyun_access_key']),
                        ];
                        ?>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label for="sms.default" class="form-label small">默认服务商</label>
                                <select class="form-select form-select-sm" id="sms.default" name="options[sms.default]">
                                    <option value="tencent" <?php if(($options['sms.default'] ?? 'tencent') === 'tencent') echo 'selected';?>>腾讯云短信</option>
                                    <option value="aliyun" <?php if(($options['sms.default'] ?? '') === 'aliyun') echo 'selected';?>>阿里云短信</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="sms.failover" class="form-label small">备用服务商（逗号分隔）</label>
                                <input type="text" class="form-control form-control-sm" id="sms.failover" name="options[sms.failover]" placeholder="如 aliyun" value="<?php echo e($options['sms.failover'] ?? ''); ?>" />
                                <div class="form-text">主用失败时的候选，按顺序尝试（可选值：tencent, aliyun）。</div>
                            </div>
                        </div>
                        <hr class="my-3">
                        <div class="border rounded p-2 bg-body-tertiary provider-block" data-provider="tencent">
                            <h6 class="small fw-semibold mb-2 d-flex align-items-center flex-wrap">腾讯云短信
                                <span class="badge text-bg-primary ms-1" data-badge="default" hidden>默认</span>
                                <span class="badge text-bg-warning ms-1" data-badge="failover" hidden>备用</span>
                                <span class="badge text-bg-success ms-1" data-badge="configured"<?php echo $smsCfg['tencent'] ? '' : ' hidden';?>>已配置</span>
                            </h6>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label for="sms.tencent_app_id" class="form-label small">SmsSdkAppId</label>
                                    <input type="text" class="form-control form-control-sm" id="sms.tencent_app_id" name="options[sms.tencent_app_id]" placeholder="1400000000" value="<?php echo e($options['sms.tencent_app_id'] ?? ''); ?>" />
                                </div>
                                <div class="col-md-6">
                                    <label for="sms.tencent_sign" class="form-label small">签名（SignName）</label>
                                    <input type="text" class="form-control form-control-sm" id="sms.tencent_sign" name="options[sms.tencent_sign]" placeholder="如 某某网" value="<?php echo e($options['sms.tencent_sign'] ?? ''); ?>" />
                                </div>
                                <div class="col-md-6">
                                    <label for="sms.tencent_region" class="form-label small">地域</label>
                                    <input type="text" class="form-control form-control-sm" id="sms.tencent_region" name="options[sms.tencent_region]" placeholder="ap-guangzhou" value="<?php echo e($options['sms.tencent_region'] ?? 'ap-guangzhou'); ?>" />
                                </div>
                                <div class="col-md-6">
                                    <label for="secret.sms.tencent_secret_id" class="form-label small">SecretId</label>
                                    <input type="password" autocomplete="new-password" class="form-control form-control-sm" id="secret.sms.tencent_secret_id" name="secrets[sms.tencent_secret_id]" placeholder="<?php echo !empty($secrets_configured['sms.tencent_secret_id']) ? '已配置，留空保持不变' : 'SecretId'; ?>" />
                                </div>
                                <div class="col-md-6">
                                    <label for="secret.sms.tencent_secret_key" class="form-label small">SecretKey</label>
                                    <input type="password" autocomplete="new-password" class="form-control form-control-sm" id="secret.sms.tencent_secret_key" name="secrets[sms.tencent_secret_key]" placeholder="<?php echo !empty($secrets_configured['sms.tencent_secret_key']) ? '已配置，留空保持不变' : 'SecretKey'; ?>" />
                                    <?php if ($crypto_available && !empty($secrets_configured['sms.tencent_secret_id'])): ?>
                                    <label class="form-check form-check-inline small mb-0 mt-1">
                                        <input type="checkbox" class="form-check-input" name="secrets_clear[]" value="sms_tencent">
                                        <span class="form-check-label text-danger">清除已保存的密钥</span>
                                    </label>
                                    <?php elseif (!$crypto_available): ?>
                                    <div class="form-text text-warning mt-1">未配置 <code>app_key</code>，密钥无法加密保存，请先设置 <code>config/app_key</code>。</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <hr class="my-3">
                        <div class="border rounded p-2 bg-body-tertiary provider-block" data-provider="aliyun">
                            <h6 class="small fw-semibold mb-2 d-flex align-items-center flex-wrap">阿里云短信
                                <span class="badge text-bg-primary ms-1" data-badge="default" hidden>默认</span>
                                <span class="badge text-bg-warning ms-1" data-badge="failover" hidden>备用</span>
                                <span class="badge text-bg-success ms-1" data-badge="configured"<?php echo $smsCfg['aliyun'] ? '' : ' hidden';?>>已配置</span>
                            </h6>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label for="sms.aliyun_sign" class="form-label small">签名（SignName）</label>
                                    <input type="text" class="form-control form-control-sm" id="sms.aliyun_sign" name="options[sms.aliyun_sign]" placeholder="如 某某网" value="<?php echo e($options['sms.aliyun_sign'] ?? ''); ?>" />
                                </div>
                                <div class="col-md-6">
                                    <label for="secret.sms.aliyun_access_key" class="form-label small">AccessKey ID</label>
                                    <input type="password" autocomplete="new-password" class="form-control form-control-sm" id="secret.sms.aliyun_access_key" name="secrets[sms.aliyun_access_key]" placeholder="<?php echo !empty($secrets_configured['sms.aliyun_access_key']) ? '已配置，留空保持不变' : 'AccessKey ID'; ?>" />
                                </div>
                                <div class="col-md-6">
                                    <label for="secret.sms.aliyun_access_secret" class="form-label small">AccessKey Secret</label>
                                    <input type="password" autocomplete="new-password" class="form-control form-control-sm" id="secret.sms.aliyun_access_secret" name="secrets[sms.aliyun_access_secret]" placeholder="<?php echo !empty($secrets_configured['sms.aliyun_access_secret']) ? '已配置，留空保持不变' : 'AccessKey Secret'; ?>" />
                                    <?php if ($crypto_available && !empty($secrets_configured['sms.aliyun_access_key'])): ?>
                                    <label class="form-check form-check-inline small mb-0 mt-1">
                                        <input type="checkbox" class="form-check-input" name="secrets_clear[]" value="sms_aliyun">
                                        <span class="form-check-label text-danger">清除已保存的密钥</span>
                                    </label>
                                    <?php elseif (!$crypto_available): ?>
                                    <div class="form-text text-warning mt-1">未配置 <code>app_key</code>，密钥无法加密保存，请先设置 <code>config/app_key</code>。</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <hr class="my-3">
                        <div class="col-12">
                            <div class="d-flex align-items-center gap-2">
                                <div class="flex-grow-1">
                                    <label class="form-label small mb-0">发送测试短信：</label>
                                    <div class="form-text">需先在云控制台准备好模板与签名；验证码类模板参数形如 <code>{"code":"1234"}</code>。</div>
                                </div>
                            </div>
                            <div class="row g-2 mt-1">
                                <div class="col-md-4">
                                    <input type="text" class="form-control form-control-sm" id="test_phone" placeholder="手机号，如 13800138000">
                                </div>
                                <div class="col-md-4">
                                    <input type="text" class="form-control form-control-sm" id="test_template" placeholder="模板 ID，如 SMS_123456">
                                </div>
                                <div class="col-md-4">
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control form-control-sm" id="test_params" placeholder='参数 JSON，如 {"code":"1234"}'>
                                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="sendTestSms()">
                                            <i class="fa fa-paper-plane me-1"></i>发送
                                        </button>
                                    </div>
                                </div>
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
    var smsTestUrl = '<?php echo Url::action('System@smsTest');?>';
    var cacheClearUrl = '<?php echo Url::action('System@cacheClear');?>';

    $(function(){
        $('#zapForm').validate({ignore:''});
        initProviderAccordion('mail');
        initProviderAccordion('sms');
        refreshProviderBadges('mail');
        refreshProviderBadges('sms');
        $('#mail.default, #mail.failover').on('change keyup', function(){ refreshProviderBadges('mail'); });
        $('#sms.default, #sms.failover').on('change keyup', function(){ refreshProviderBadges('sms'); });
    })

    function initProviderAccordion(channel){
        const pane = channel === 'mail' ? '#smtp-tab-pane' : '#sms-tab-pane';
        const def = $(channel === 'mail' ? '#mail.default' : '#sms.default').val();
        $(pane + ' .provider-block').each(function(){
            const block = $(this);
            const p = block.data('provider');
            const h6 = block.children('h6').first();
            if (!h6.length) return;
            const cid = 'prov-' + p;
            const body = block.children('.row.g-2').first();
            if (!body.length || body.parent().hasClass('collapse')) return;
            body.wrap('<div class="collapse" id="' + cid + '"></div>');
            const collapse = body.parent();
            const cfgVisible = block.find('[data-badge="configured"]').not('[hidden]').length > 0;
            const shown = (p === def || cfgVisible);
            if (shown) collapse.addClass('show');
            h6.addClass('provider-toggle')
               .attr('data-bs-toggle','collapse')
               .attr('data-bs-target','#' + cid)
               .attr('role','button')
               .attr('tabindex','0')
               .attr('aria-expanded', shown ? 'true' : 'false')
               .css('cursor','pointer');
            if (!h6.find('i.chevron').length) {
                h6.append(' <i class="fa fa-chevron-down chevron ms-2"></i>');
            }
        });
    }

    function refreshProviderBadges(channel){
        const def = $(channel === 'mail' ? '#mail.default' : '#sms.default').val();
        const fo  = String($(channel === 'mail' ? '#mail.failover' : '#sms.failover').val() || '').split(',').map(function(s){return s.trim();}).filter(Boolean);
        const pane = channel === 'mail' ? '#smtp-tab-pane' : '#sms-tab-pane';
        $(pane + ' .provider-block').each(function(){
            const p = $(this).data('provider');
            const isDefault = (p === def);
            const isFo = fo.indexOf(p) !== -1;
            $(this).find('[data-badge="default"]').toggle(isDefault);
            $(this).find('[data-badge="failover"]').toggle(isFo);
            $(this).toggleClass('border-primary border-2', isDefault);
        });
    }

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

    function sendTestSms(){
        const phone = $('#test_phone').val().trim();
        const template = $('#test_template').val().trim();
        const params = $('#test_params').val().trim() || '{}';
        if (!phone || !template) {
            ZapToast.alert('请填写手机号与模板 ID', {bgColor: bgWarning, position: Toast_Pos_Center});
            return false;
        }
        const load = Zap.loading('正在发送测试短信，请稍后');
        $.ajax({
            url: smsTestUrl,
            method: 'post',
            data: {test_phone: phone, test_template: template, test_params: params},
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
