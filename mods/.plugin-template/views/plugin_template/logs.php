<?php
defined('IN_ZAPCMS') or die('No permission to access');
/*
 * “运行日志”页面片段，由 AdminController::Logs() 渲染（admin.pages 中 action 类型示例）
 */
?>
<h6 class="fw-semibold">运行日志（action 控制器示例）</h6>
<p class="text-muted small">本页由插件的控制器动作 <code>AdminController::Logs()</code> 返回，演示“其它控制器功能”如何挂到宿主页。</p>
<table class="table table-sm table-hover align-middle">
    <thead class="table-light">
        <tr><th>时间</th><th>事件</th><th>状态</th></tr>
    </thead>
    <tbody>
        <tr><td>2026-11-05 10:24</td><td>支付回调接收</td><td><span class="badge bg-success bg-opacity-10 text-success">成功</span></td></tr>
        <tr><td>2026-11-05 09:11</td><td>配置更新</td><td><span class="badge bg-success bg-opacity-10 text-success">成功</span></td></tr>
        <tr><td>2026-11-04 22:03</td><td>签名校验失败</td><td><span class="badge bg-danger bg-opacity-10 text-danger">失败</span></td></tr>
    </tbody>
</table>
