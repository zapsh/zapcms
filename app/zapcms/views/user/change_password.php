<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card card-warning card-outline">
                <div class="card-header">
                    <h5 class="card-title"><i class="fas fa-lock me-2"></i>修改密码</h5>
                </div>
                <div class="card-body">
                    <form id="changePasswordForm" method="post" action="<?php echo \zap\facades\Url::action('User@changePassword'); ?>">
                        <?php echo csrf_field(); ?>

                        <div class="mb-3">
                            <label for="old_password" class="form-label">
                                当前密码 <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-key"></i></span>
                                <input type="password" class="form-control" id="old_password"
                                       name="old_password" required
                                       placeholder="请输入当前密码">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="new_password" class="form-label">
                                新密码 <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="new_password"
                                       name="new_password" required minlength="6"
                                       placeholder="请输入新密码（至少6个字符）">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="confirm_password" class="form-label">
                                确认新密码 <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-check-circle"></i></span>
                                <input type="password" class="form-control" id="confirm_password"
                                       name="confirm_password" required minlength="6"
                                       placeholder="请再次输入新密码">
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-warning" id="savePwdBtn">
                                <i class="fas fa-save me-1"></i>修改密码
                            </button>
                            <a href="<?php echo \zap\facades\Url::action('User@profile'); ?>"
                               class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-1"></i>返回个人资料
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card card-info card-outline">
                <div class="card-header">
                    <h5 class="card-title"><i class="fas fa-info-circle me-2"></i>密码安全提示</h5>
                </div>
                <div class="card-body">
                    <ul class="mb-0 small">
                        <li>密码长度至少为 <strong>6</strong> 个字符</li>
                        <li>建议使用字母、数字和特殊字符的组合</li>
                        <li>请勿使用与其他网站相同的密码</li>
                        <li>修改密码后需要重新登录</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(function(){
    $('#changePasswordForm').on('submit', function(){
        var newPwd = $('#new_password').val();
        var confirmPwd = $('#confirm_password').val();

        if(newPwd !== confirmPwd){
            ZapToast.alert('两次输入的新密码不一致', {bgColor: bgDanger, position: Toast_Pos_Center});
            return false;
        }

        if(newPwd.length < 6){
            ZapToast.alert('新密码至少需要6个字符', {bgColor: bgDanger, position: Toast_Pos_Center});
            return false;
        }

        $('#savePwdBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>提交中...');
    });
});
</script>
