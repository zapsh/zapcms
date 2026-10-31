<?php $this->extend('layouts.common'); ?>
<div class="row g-2">
    <!-- 左侧标签导航 -->
    <div class="col-md-3 col-lg-2">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="list-group list-group-flush nav nav-pills flex-column" role="tablist">
                    <a class="list-group-item list-group-item-action d-flex align-items-center rounded-0 <?php echo $active_tab === 'account' ? 'active' : ''; ?>"
                       href="<?php echo \zap\facades\Url::action('User@profile', ['tab' => 'account']); ?>">
                        <i class="fa fa-user-circle me-2"></i> 个人资料
                    </a>
                    <a class="list-group-item list-group-item-action d-flex align-items-center rounded-0 <?php echo $active_tab === 'security' ? 'active' : ''; ?>"
                       href="<?php echo \zap\facades\Url::action('User@profile', ['tab' => 'security']); ?>">
                        <i class="fa fa-shield-alt me-2"></i> 安全设置
                    </a>
                    <a class="list-group-item list-group-item-action d-flex align-items-center rounded-0 <?php echo $active_tab === 'activity' ? 'active' : ''; ?>"
                       href="<?php echo \zap\facades\Url::action('User@profile', ['tab' => 'activity']); ?>">
                        <i class="fa fa-history me-2"></i> 操作记录
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- 右侧内容区 -->
    <div class="col-md-9 col-lg-10">
        <div class="tab-content">

            <!-- ==================== 个人资料 ==================== -->
            <?php if ($active_tab === 'account'): ?>
            <div class="tab-pane fade show active">

                <!-- 头像卡片 -->
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-transparent border-bottom-0 pb-1">
                        <h5 class="card-title mb-0"><i class="fa fa-portrait me-2 text-primary"></i>头像设置</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-4 flex-wrap">
                            <div class="position-relative">
                                <img src="<?php echo !empty($user['avatar_url']) ? htmlspecialchars($user['avatar_url']) : base_url('/assets/admin/images/default-avatar.svg'); ?>"
                                     alt="头像" class="rounded-circle border border-2 border-light shadow-sm"
                                     width="96" height="96" style="object-fit: cover;"
                                     id="avatar-preview">
                            </div>
                            <div>
                                <form method="post" enctype="multipart/form-data"
                                      action="<?php echo \zap\facades\Url::action('User@uploadAvatar'); ?>"
                                      class="d-flex align-items-center gap-2 flex-wrap">
                                    <div>
                                        <input type="file" name="avatar" id="avatar-input"
                                               accept="image/*" class="form-control form-control-sm"
                                               style="max-width: 220px;"
                                               onchange="document.getElementById('avatar-file-name').textContent = this.files[0]?.name || '';">
                                        <small id="avatar-file-name" class="text-muted d-block mt-1"></small>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-primary">
                                        <i class="fa fa-upload me-1"></i>上传头像
                                    </button>
                                </form>
                                <small class="text-muted d-block mt-1">支持 JPG / PNG / GIF / WebP，最大 2MB</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 基本资料卡片 -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-transparent border-bottom-0 pb-1">
                        <h5 class="card-title mb-0"><i class="fa fa-id-card me-2 text-primary"></i>基本资料</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="<?php echo \zap\facades\Url::action('User@updateProfile'); ?>">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">用户名</label>
                                    <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                                    <small class="text-muted">用户名不可修改</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">姓名 <span class="text-danger">*</span></label>
                                    <input type="text" name="full_name" class="form-control"
                                           value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">邮箱</label>
                                    <input type="email" name="email" class="form-control"
                                           value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">手机号</label>
                                    <input type="text" name="phone_number" class="form-control"
                                           value="<?php echo htmlspecialchars($user['phone_number'] ?? ''); ?>">
                                </div>
                                <div class="col-12">
                                    <hr>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa fa-save me-1"></i>保存修改
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
            <?php endif; ?>

            <!-- ==================== 安全设置 ==================== -->
            <?php if ($active_tab === 'security'): ?>
            <div class="tab-pane fade show active">

                <!-- 修改密码 -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-transparent border-bottom-0 pb-1">
                        <h5 class="card-title mb-0"><i class="fa fa-lock me-2 text-primary"></i>修改密码</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="<?php echo \zap\facades\Url::action('User@changePassword'); ?>">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">当前密码</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fa fa-key"></i></span>
                                        <input type="password" name="old_password" class="form-control" placeholder="请输入当前密码" required>
                                    </div>
                                </div>
                                <div class="w-100"></div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">新密码</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fa fa-lock"></i></span>
                                        <input type="password" name="new_password" class="form-control" placeholder="至少 6 个字符" minlength="6" required>
                                    </div>
                                </div>
                                <div class="w-100"></div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">确认新密码</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fa fa-lock"></i></span>
                                        <input type="password" name="confirm_password" class="form-control" placeholder="再次输入新密码" minlength="6" required>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <hr>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa fa-key me-1"></i>更新密码
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
            <?php endif; ?>

            <!-- ==================== 操作记录 ==================== -->
            <?php if ($active_tab === 'activity'): ?>
            <div class="tab-pane fade show active">

                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0"><i class="fa fa-history me-2 text-primary"></i>操作记录</h5>
                        <small class="text-muted">共 <?php echo (int)$log_total; ?> 条记录</small>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($logs)): ?>
                            <div class="text-center py-5 text-muted">
                                <i class="fa fa-inbox fa-3x mb-2 d-block"></i>
                                <p>暂无操作记录</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 60px;">#</th>
                                            <th>操作</th>
                                            <th>详情</th>
                                            <th>IP 地址</th>
                                            <th style="width: 160px;">操作时间</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($logs as $i => $log): ?>
                                        <tr>
                                            <td class="text-muted small"><?php echo $i + 1; ?></td>
                                            <td>
                                                <span class="badge bg-<?php
                                                    $badgeColor = 'primary';
                                                    if (mb_strpos($log['title'], '登录') !== false) {
                                                        $badgeColor = 'success';
                                                    } elseif (mb_strpos($log['title'], '密码') !== false) {
                                                        $badgeColor = 'warning';
                                                    } elseif (mb_strpos($log['title'], '退出') !== false) {
                                                        $badgeColor = 'secondary';
                                                    } elseif (mb_strpos($log['title'], '头像') !== false) {
                                                        $badgeColor = 'info';
                                                    }
                                                    echo $badgeColor;
                                                ?>"><?php echo htmlspecialchars($log['title']); ?></span>
                                            </td>
                                            <td class="small">
                                                <?php echo htmlspecialchars($log['content'] ?: '-'); ?>
                                                <?php if (!empty($log['request_url'])): ?>
                                                    <br><code class="text-muted" style="font-size: 11px;"><?php echo htmlspecialchars($log['request_url']); ?></code>
                                                <?php endif; ?>
                                            </td>
                                            <td class="small text-muted"><?php echo htmlspecialchars($log['ipaddress'] ?? '-'); ?></td>
                                            <td class="small text-muted"><?php echo date('Y-m-d H:i:s', (int)$log['request_time']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <?php if ($log_total_pages > 1): ?>
                            <div class="d-flex justify-content-center p-3">
                                <nav>
                                    <ul class="pagination pagination-sm mb-0">
                                        <?php
                                        $baseUrl = \zap\facades\Url::action('User@profile', ['tab' => 'activity']);
                                        for ($p = 1; $p <= $log_total_pages; $p++):
                                            $active = ($p === $log_page) ? ' active' : '';
                                            $url = $baseUrl . '&page=' . $p;
                                        ?>
                                            <li class="page-item<?php echo e($active); ?>">
                                                <a class="page-link" href="<?php echo e($url); ?>"><?php echo e($p); ?></a>
                                            </li>
                                        <?php endfor; ?>
                                    </ul>
                                </nav>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
            <?php endif; ?>

        </div>
    </div>
</div>
