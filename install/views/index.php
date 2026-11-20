<?php $this->layout('layout'); ?>

<div class="install-card card">
    <div class="card-header">
        <i class="check-pass me-2">&#9432;</i> 使用协议
    </div>
    <div class="card-body">
        <div class="overflow-y-auto text-secondary small" style="max-height: 340px; line-height: 1.8;">
            <p>感谢您选择 <strong>ZAP CMS</strong> 内容管理系统。ZAP CMS 帮助企业快速构建中小型门户网站，基于 PHP + MySQL / SQLite 技术开发。</p>
            <p>本软件以 <strong>MIT 许可协议</strong> 开源发布，您可以自由使用、修改和分发。</p>
            <p>官方网址：<a href="https://www.zap.cn/cms" target="_blank" rel="noopener">https://www.zap.cn/cms</a></p>

            <hr>

            <h6 class="fw-bold">一、您获得的权利</h6>
            <ul>
                <li>可免费用于个人或商业项目，无需支付任何授权费用。</li>
                <li>可自由使用、复制、修改、合并、发布、分发、再许可及销售本软件的副本。</li>
                <li>可将修改后的版本用于闭源项目，或作为独立产品对外分发。</li>
                <li>您拥有使用本软件构建的网站全部内容的所有权，并独立承担相关法律义务。</li>
            </ul>

            <h6 class="fw-bold">二、许可条件</h6>
            <ul>
                <li>在软件的所有副本或实质性部分中，须保留原始版权声明与本许可声明。</li>
                <li>本许可不构成对 ZAP CMS 名称及相关商标的任何授权。</li>
            </ul>

            <h6 class="fw-bold">三、免责声明</h6>
            <ul>
                <li>本软件按"现状"提供，不附带任何明示或默示的担保，包括但不限于适销性、特定用途适用性与非侵权担保。</li>
                <li>作者或版权持有人不对任何索赔、损害或其他责任承担责任，无论其源于合同、侵权或其他行为。</li>
                <li>使用本软件的风险由使用者自行承担；请勿将本系统用于法律法规禁止的用途。</li>
            </ul>

            <p class="mt-3 mb-0">
                <small>完整条款以发布包根目录的 LICENSE 文件为准。Copyright &copy; 2023–<?= date('Y') ?> 嘉兴领格信息技术有限公司 (ZAP.CN)</small>
            </p>
        </div>
    </div>
    <div class="card-footer text-center">
        <a href="index.php?action=check" class="btn btn-success px-4">
            同意协议，继续安装 &rarr;
        </a>
    </div>
</div>
