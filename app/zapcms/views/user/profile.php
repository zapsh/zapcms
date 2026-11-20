<div class="container-fluid">
    <div class="row">
        <div class="col-lg-8">
            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h5 class="card-title"><i class="fas fa-user-edit me-2"></i>编辑个人资料</h5>
                </div>
                <div class="card-body">
                    <form id="profileForm" method="post" action="<?php echo \zap\facades\Url::action('User@profile'); ?>">
                        <?php echo csrf_field(); ?>

                        <div class="row mb-3">
                            <label for="username" class="col-sm-3 col-form-label text-end">用户名</label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control bg-light" id="username"
                                       value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>"
                                       readonly disabled>
                                <small class="text-muted">用户名不可修改</small>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="full_name" class="col-sm-3 col-form-label text-end">
                                姓名 <span class="text-danger">*</span>
                            </label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" id="full_name" name="full_name"
                                       value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>"
                                       required maxlength="50" placeholder="请输入您的姓名">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="email" class="col-sm-3 col-form-label text-end">邮箱</label>
                            <div class="col-sm-9">
                                <input type="email" class="form-control" id="email" name="email"
                                       value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                                       maxlength="100" placeholder="请输入您的邮箱地址">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="phone_number" class="col-sm-3 col-form-label text-end">手机号</label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" id="phone_number" name="phone_number"
                                       value="<?php echo htmlspecialchars($user['phone_number'] ?? ''); ?>"
                                       maxlength="20" placeholder="请输入您的手机号码">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label text-end">上次登录</label>
                            <div class="col-sm-9">
                                <p class="form-control-plaintext">
                                    <?php
                                    $lastIp = $user['last_ip'] ?? '';
                                    $lastTime = !empty($user['last_access_time']) ? date('Y-m-d H:i:s', $user['last_access_time']) : '';
                                    echo htmlspecialchars($lastTime . ($lastIp ? ' (IP: ' . $lastIp . ')' : ''));
                                    ?>
                                </p>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-sm-9 offset-sm-3">
                                <button type="submit" class="btn btn-primary" id="saveProfileBtn">
                                    <i class="fas fa-save me-1"></i>保存修改
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h5 class="card-title"><i class="fas fa-user-circle me-2"></i>账户信息</h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <div class="avatar-circle bg-primary text-white d-inline-flex align-items-center justify-content-center"
                             style="width:80px;height:80px;border-radius:50%;font-size:32px;">
                            <?php echo htmlspecialchars(mb_substr($user['full_name'] ?? $user['username'] ?? 'U', 0, 1)); ?>
                        </div>
                    </div>
                    <table class="table table-sm">
                        <tr>
                            <td class="text-muted">用户名</td>
                            <td class="fw-semibold"><?php echo htmlspecialchars($user['username'] ?? '-'); ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">姓名</td>
                            <td><?php echo htmlspecialchars($user['full_name'] ?? '-'); ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">邮箱</td>
                            <td><?php echo htmlspecialchars($user['email'] ?: '-'); ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">手机号</td>
                            <td><?php echo htmlspecialchars($user['phone_number'] ?: '-'); ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">注册时间</td>
                            <td><?php echo !empty($user['created_at']) ? date('Y-m-d', $user['created_at']) : '-'; ?></td>
                        </tr>
                    </table>
                    <a href="<?php echo \zap\facades\Url::action('User@changePassword'); ?>"
                       class="btn btn-outline-warning btn-sm w-100">
                        <i class="fas fa-lock me-1"></i>修改密码
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(function(){
    $('#profileForm').on('submit', function(){
        $('#saveProfileBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>保存中...');
    });
});
</script>
