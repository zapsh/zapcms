// 模块自带脚本示例（由 views/plugin_template/asset_demo.php 通过 register_scripts 入队）
// 仅在示例代码渲染时生效：点击按钮切换样式并提示。
document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('pt-demo-btn');
    if (!btn) {
        return;
    }
    btn.addEventListener('click', function () {
        btn.classList.toggle('btn-success');
        alert('模块 JS 已加载：mod_url() 引用的图片 / CSS / JS 资源工作正常');
    });
});
