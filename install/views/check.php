<?php
$this->layout('layout');
$items   = $checks['items'];
$allPass = $checks['allPass'];
$passCount = count(array_filter($items, fn($c) => $c['pass']));
$total     = count($items);
?>

<div class="install-card card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><span class="check-pass me-2">&#9881;</span> 服务器环境检测</span>
        <span class="badge <?= $allPass ? 'bg-success' : 'bg-danger' ?>">
            <?= $passCount ?> / <?= $total ?> 通过
        </span>
    </div>
    <div class="card-body p-0">
        <table class="table table-borderless check-table mb-0">
            <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
                <td class="ps-3"><?= htmlspecialchars($item['label']) ?></td>
                <td class="text-secondary small"><?= htmlspecialchars($item['value']) ?></td>
                <td class="text-end pe-3">
                    <?php if ($item['pass']): ?>
                        <span class="check-pass" title="通过">&#10003;</span>
                    <?php else: ?>
                        <span class="check-fail" title="<?= htmlspecialchars($item['failMsg']) ?>">&#10007;</span>
                        <?php if (!empty($item['autofix'])): ?>
                            <div class="mt-1">
                                <button type="button" class="btn btn-sm btn-outline-warning" data-autofix="<?= htmlspecialchars($item['autofix']) ?>">一键生成 .htaccess</button>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer text-center">
        <?php if ($allPass): ?>
            <a href="index.php?action=database" class="btn btn-success px-4">下一步 &rarr;</a>
        <?php else: ?>
            <button class="btn btn-secondary px-4" disabled>
                环境未就绪，请修复后再继续
            </button>
            <div class="text-danger small mt-2">请根据上方标记的 &#10007; 项修复服务器配置后刷新本页</div>
        <?php endif; ?>
    </div>
</div>

<?php if ($allPass === false): ?>
<script>
document.querySelectorAll('[data-autofix]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var url = btn.getAttribute('data-autofix');
        btn.disabled = true;
        btn.textContent = '生成中…';
        fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest'}})
            .then(function (r) { return r.json(); })
            .then(function (d) {
                alert(d.msg || (d.code === 0 ? '已生成' : '操作失败'));
                location.reload();
            })
            .catch(function () { alert('请求失败，请手动创建'); location.reload(); });
    });
});
</script>
<?php endif; ?>
