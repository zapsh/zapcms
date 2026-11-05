<?php
defined('IN_ZAPCMS') or die('No permission to access');
/*
 * 插件“基础设置”页面片段（由 Plugin@settings 宿主页嵌入，无需自建左侧菜单）
 *
 * 重要：片段视图【不要】调用 $this->layout()，外壳（侧边子导航、后台框架）由宿主页负责。
 *
 * 本页是 admin.pages 的 view 类型：宿主页在 POST 时会原样重渲染本片段，
 * 所以“保存”逻辑直接写在这里 —— 读 $_POST 并写入 options 表。
 * 若想把保存交给插件自己的控制器，可把本页改成 action 类型（参考“运行日志”页）。
 */
$plugin = $plugin ?? 'plugin_template';
$optKeyA = "plugin.{$plugin}.opt_a";
$optKeyB = "plugin.{$plugin}.opt_b";

$opt_a = \zapcms\services\Option::get($optKeyA, '');
$opt_b = \zapcms\services\Option::get($optKeyB, '1');
$saved  = false;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $opt_a = (string) ($_POST['opt_a'] ?? '');
    $opt_b = (string) ($_POST['opt_b'] ?? '1');
    // Option::add 是 upsert：键不存在时插入，已存在时回退为更新
    \zapcms\services\Option::add($optKeyA, $opt_a, 0, 1);
    \zapcms\services\Option::add($optKeyB, $opt_b, 0, 1);
    $saved = true;
}
?>
<div class="mb-3">
    <h6 class="fw-semibold">模块静态资源引用</h6>
    <p class="text-muted small">图片等静态资源放在 assets/ 下，用 mod_url() 生成 URL：</p>
    <img src="<?php echo mod_url($plugin, 'assets/img/logo.svg'); ?>" alt="logo" height="40" class="mb-2">
</div>

<hr>

<?php if ($saved): ?>
<div class="alert alert-success py-2 mb-3"><i class="fa fa-check me-1"></i>已保存</div>
<?php endif; ?>

<form method="post" action="<?php echo url_action('Plugin@settings', ['plugin' => $plugin, 'page' => 'settings']); ?>">
    <?php echo csrf_field(); ?>
    <div class="mb-3">
        <label class="form-label">示例配置项 A</label>
        <input type="text" name="opt_a" class="form-control" value="<?php echo htmlspecialchars($opt_a, ENT_QUOTES); ?>" placeholder="例如：API Key">
    </div>
    <div class="mb-3">
        <label class="form-label">示例配置项 B</label>
        <select name="opt_b" class="form-select">
            <option value="1" <?php echo $opt_b === '1' ? 'selected' : ''; ?>>开启</option>
            <option value="0" <?php echo $opt_b === '0' ? 'selected' : ''; ?>>关闭</option>
        </select>
    </div>
    <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-save me-1"></i>保存</button>
    <span class="text-muted small ms-2">保存写入 options 表（键名 plugin.&lt;模块名&gt;.opt_a / opt_b）。</span>
</form>
