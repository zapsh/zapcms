<?php $this->layout('layout'); ?>

<div class="install-card card">
    <div class="card-header">
        <i class="check-pass me-2">&#9432;</i> 使用协议
    </div>
    <div class="card-body">
        <div class="overflow-y-auto text-secondary small" style="max-height: 340px; line-height: 1.8;">
            <p>感谢您选择 <strong>ZAP CMS</strong> 内容管理系统。ZAP CMS 帮助企业快速构建中小型门户网站，基于 PHP + MySQL / SQLite 技术开发，全部源码开放。</p>
            <p>官方网址：<a href="https://www.zap.cn/cms" target="_blank" rel="noopener">https://www.zap.cn/cms</a></p>

            <hr>

            <h6 class="fw-bold">一、协议许可的权利</h6>
            <ul>
                <li>您可以在遵守本协议的基础上，将本软件用于非商业用途，无需支付版权授权费用。</li>
                <li>您可以在协议约定的范围内修改 ZAP CMS 源代码或界面风格以适应您的网站需求。</li>
                <li>您拥有使用本软件构建的网站全部内容的所有权，并独立承担相关法律义务。</li>
                <li>获得商业授权后，可将本软件应用于商业用途，并享有对应级别的技术支持。</li>
            </ul>

            <h6 class="fw-bold">二、协议约束与限制</h6>
            <ul>
                <li>未获商业授权前，必须保留网站底部 ZAP CMS 版权信息，不得删除或隐藏，否则视为侵权。</li>
                <li>未经官方许可，不得对本软件或关联商业授权进行出租、出售。</li>
                <li>禁止在 ZAP CMS 的基础上开发派生版本用于重新分发。</li>
                <li>如您未能遵守协议条款，授权将被终止，所被许可的权利将被收回。</li>
            </ul>

            <h6 class="fw-bold">三、有限担保和免责声明</h6>
            <ul>
                <li>本软件按"现状"提供，不附带任何明示或默示的担保。</li>
                <li>用户自愿使用本软件，须自行承担使用风险。</li>
                <li>禁止将本系统用于法律法规禁止的行业。</li>
            </ul>

            <p class="mt-3 mb-0">
                <small>版权所有 &copy; 2014–<?= date('Y') ?> ZAP.CN，保留所有权利。</small>
            </p>
        </div>
    </div>
    <div class="card-footer text-center">
        <a href="index.php?action=check" class="btn btn-success px-4">
            同意协议，继续安装 &rarr;
        </a>
    </div>
</div>
