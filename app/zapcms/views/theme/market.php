<?php
/*
 * 主题市场
 *
 * 数据源：packagemgr V1 市场接口（type=theme）。
 * 安装后主题落在 themes/{name}，无需重启即可在主题列表中看到。
 *
 * @var array|null $result    市场返回结构 plugins/total/total_pages/current_page
 * @var int        $page
 * @var string     $search
 * @var array      $installed 已安装主题目录名
 */
!IS_AJAX && $this->layout('layouts/common');

use zap\facades\Url;

$this->view->page_title  = '主题市场';
$this->view->breadcrumbs = [
    ['title' => '控制台', 'url' => Url::action('Index')],
    ['title' => '外观', 'url' => Url::action('Theme')],
    ['title' => '主题市场'],
];

$list       = is_array($result) ? ($result['plugins'] ?? []) : [];
$total      = (int) ($result['total'] ?? 0);
$totalPages = (int) ($result['total_pages'] ?? 1);
$apiMissing = $result === null;
?>

<div class="container-fluid p-0">
    <div class="card shadow-sm mb-3">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h5 class="card-title mb-0">
                <i class="fa fa-store me-2 text-primary"></i>主题市场
            </h5>
            <div class="d-flex flex-wrap gap-2">
                <form class="d-flex" method="get" action="<?= Url::action('Theme@market') ?>" style="min-width:260px;">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="fa fa-search text-muted"></i></span>
                        <input class="form-control" type="search" name="search" placeholder="搜索主题名称或描述…"
                               value="<?= htmlspecialchars($search) ?>">
                        <button class="btn btn-primary" type="submit">搜索</button>
                    </div>
                </form>
                <a href="<?= Url::action('Theme') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="fa fa-arrow-left me-1"></i>返回主题管理
                </a>
            </div>
        </div>
    </div>

    <?php if ($apiMissing): ?>
        <div class="card shadow-sm">
            <div class="card-body text-center py-5">
                <i class="fa fa-plug-circle-xmark fa-4x text-danger opacity-50 mb-3 d-block"></i>
                <h5 class="text-muted">无法连接到主题市场</h5>
                <p class="text-muted small mb-0">
                    请确认已配置市场接口地址（website.market_api，指向 packagemgr 的
                    <code>/zapcms/v1</code>），且该市场提供 <code>type=theme</code> 的主题包。
                </p>
            </div>
        </div>
    <?php elseif (empty($list)): ?>
        <div class="card shadow-sm">
            <div class="card-body text-center py-5">
                <i class="fa fa-box-open fa-4x text-muted opacity-25 mb-3 d-block"></i>
                <h5 class="text-muted">
                    <?= $search !== '' ? '没有找到匹配「' . htmlspecialchars($search) . '」的主题' : '暂无可用的主题' ?>
                </h5>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($list as $pkg): ?>
                <?php
                $name      = (string) ($pkg['name'] ?? '');
                $title     = (string) ($pkg['title'] ?? $name);
                $installedFlag = $name !== '' && in_array($name, $installed, true);

                $cover   = (string) ($pkg['cover'] ?? '');
                $preview = (string) ($pkg['preview_url'] ?? '');
                $summary = (string) ($pkg['content'] ?? '');
                if ($summary === '') {
                    $summary = (string) ($pkg['description'] ?? '');
                }
                ?>
                <div class="col-md-6 col-xl-4">
                    <div class="card shadow-sm h-100">
                        <div class="position-relative bg-light" style="aspect-ratio:16/10;overflow:hidden;">
                            <?php if ($cover !== ''): ?>
                                <img src="<?= htmlspecialchars($cover) ?>" alt="<?= htmlspecialchars($title) ?>"
                                     class="w-100 h-100" loading="lazy" style="object-fit:cover;">
                            <?php else: ?>
                                <div class="d-flex align-items-center justify-content-center h-100 text-muted opacity-50">
                                    <i class="fa fa-palette fa-2x"></i>
                                </div>
                            <?php endif; ?>
                            <span class="badge bg-dark bg-opacity-75 position-absolute top-0 end-0 m-2">
                                v<?= htmlspecialchars((string) ($pkg['version'] ?? '')) ?>
                            </span>
                        </div>

                        <div class="card-body d-flex flex-column">
                            <div class="mb-1">
                                <div class="fw-semibold text-truncate" title="<?= htmlspecialchars($title) ?>"><?= htmlspecialchars($title) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($name) ?></small>
                            </div>

                            <p class="text-muted small mb-2 flex-grow-1" style="line-height:1.45;">
                                <?= htmlspecialchars(mb_strimwidth($summary, 0, 80, '…', 'UTF-8')) ?>
                            </p>

                            <div class="d-flex align-items-center justify-content-between small text-muted mb-3">
                                <span><i class="fa fa-user me-1"></i><?= htmlspecialchars((string) ($pkg['author'] ?? '')) ?></span>
                                <?php if (($pkg['min_zapcms'] ?? '') !== ''): ?>
                                    <span>需 CMS ≥ <?= htmlspecialchars((string) $pkg['min_zapcms']) ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="d-flex gap-2 mt-auto">
                                <?php if ($preview !== ''): ?>
                                    <a class="btn btn-outline-secondary btn-sm flex-fill" target="_blank" rel="noopener"
                                       href="<?= htmlspecialchars($preview) ?>">
                                        <i class="fa fa-eye me-1"></i>预览
                                    </a>
                                <?php endif; ?>

                                <?php if ($installedFlag): ?>
                                    <button class="btn btn-outline-secondary btn-sm flex-fill" disabled>
                                        <i class="fa fa-check me-1"></i>已安装
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-primary btn-sm flex-fill btn-install"
                                            data-package="<?= htmlspecialchars((string) ($pkg['package_name'] ?? '')) ?>"
                                            data-title="<?= htmlspecialchars($title) ?>">
                                        <i class="fa fa-cloud-download me-1"></i>安装
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-3">
            <small class="text-muted">共 <?= $total ?> 个主题</small>
            <?php if ($totalPages > 1): ?>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link"
                                   href="<?= Url::action('Theme@market') ?>?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
(function () {
    var api = '<?= Url::action('Theme@installMarket') ?>';

    document.querySelectorAll('.btn-install').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var pkg   = btn.getAttribute('data-package');
            var title = btn.getAttribute('data-title') || pkg;
            if (!pkg) { return; }
            if (!confirm('确定安装主题「' + title + '」？')) { return; }

            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>安装中…';

            fetch(api, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({ package: pkg }).toString()
            })
                .then(function (r) { return r.text(); })
                .then(function (t) {
                    var d;
                    try { d = JSON.parse(t); }
                    catch (e) { d = { code: -9, msg: '返回非 JSON：' + String(t).slice(0, 200) }; }
                    if (Number(d.code) === 0) {
                        btn.innerHTML = '<i class="fa fa-check me-1"></i>已安装';
                        btn.className = 'btn btn-outline-secondary btn-sm';
                        alert(d.msg || '安装成功');
                        location.reload();
                    } else {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa fa-cloud-download me-1"></i>安装';
                        alert(d.msg || '安装失败');
                    }
                })
                .catch(function () {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa fa-cloud-download me-1"></i>安装';
                    alert('请求失败');
                });
        });
    });
})();
</script>
