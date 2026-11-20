<?php
/*
 * 用户编辑表单（Modal 内嵌）
 */
?>
<form>
    <input type="hidden" name="admin_id" value="<?php echo e($user['id'] ?? 0); ?>" />
    <div class="row g-3">
        <div class="col-sm-6">
            <label for="data_username" class="form-label fw-semibold">用户名 <span class="text-danger">*</span></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-user"></i></span>
                <input type="text" class="form-control" id="data_username" name="data[username]"
                       value="<?php echo htmlspecialchars($user['username']); ?>"
                       placeholder="6~20位用户名" required>
            </div>
        </div>
        <div class="col-sm-6">
            <label for="data_full_name" class="form-label fw-semibold">姓名</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-id-card"></i></span>
                <input type="text" class="form-control" id="data_full_name" name="data[full_name]"
                       value="<?php echo htmlspecialchars($user['full_name']); ?>" placeholder="真实姓名">
            </div>
        </div>
        <div class="col-sm-6">
            <label for="data_email" class="form-label fw-semibold">Email</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-envelope"></i></span>
                <input type="email" class="form-control" id="data_email" name="data[email]"
                       value="<?php echo htmlspecialchars($user['email']); ?>" placeholder="邮箱地址">
            </div>
        </div>
        <div class="col-sm-6">
            <label for="data_phone_number" class="form-label fw-semibold">手机号码</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-phone"></i></span>
                <input type="text" class="form-control" id="data_phone_number" name="data[phone_number]"
                       value="<?php echo htmlspecialchars($user['phone_number']); ?>" placeholder="手机号码">
            </div>
        </div>
        <div class="col-sm-6">
            <label for="data_password" class="form-label fw-semibold">密码
                <small class="text-muted fw-normal">(留空不修改)</small>
            </label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-lock"></i></span>
                <input type="password" class="form-control" id="data_password" name="data[password]"
                       value="" placeholder="请输入密码">
            </div>
        </div>
        <div class="col-sm-6">
            <label for="data_new_password" class="form-label fw-semibold">确认密码</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-lock"></i></span>
                <input type="password" class="form-control" id="data_new_password" name="data[new_password]"
                       value="" placeholder="再次输入密码">
            </div>
        </div>

        <div class="col-sm-6">
            <label class="form-label fw-semibold">用户角色</label>
            <div class="border rounded p-3">
                <?php if (empty($roles)): ?>
                <span class="text-muted">暂无可用角色</span>
                <?php else: ?>
                <?php foreach ($roles as $row): ?>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="user_roles[]"
                           <?php echo in_array($row['role_id'], $user_roles) ? 'checked' : ''; ?>
                           id="roles_<?php echo e($row['role_id']); ?>"
                           value="<?php echo e($row['role_id']); ?>">
                    <label class="form-check-label" for="roles_<?php echo e($row['role_id']); ?>">
                        <?php echo htmlspecialchars($row['name']); ?>
                        <?php if (!empty($row['description'])): ?>
                        <small class="text-muted d-block"><?php echo htmlspecialchars($row['description']); ?></small>
                        <?php endif; ?>
                    </label>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-sm-6">
            <label for="data_status" class="form-label fw-semibold">状态</label>
            <select class="form-select" id="data_status" name="data[status]">
                <?php foreach (\zapcms\models\Admin::getStatus() as $key => $title): ?>
                <option value="<?php echo e($key); ?>" <?php echo $key == $user['status'] ? 'selected' : ''; ?>>
                    <?php echo e($title); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</form>
