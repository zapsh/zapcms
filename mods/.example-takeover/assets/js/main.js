// example-takeover 示例 mod 自带脚本
document.addEventListener('DOMContentLoaded', function () {
    var badge = document.querySelector('.et-badge');
    if (badge) {
        badge.addEventListener('click', function () {
            alert('example-takeover 的 JS 已成功加载并接管页面');
        });
    }
});
