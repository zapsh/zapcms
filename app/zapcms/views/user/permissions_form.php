<?php
?>
<form>
    <input type="hidden" name="perm_id" value="<?php echo e($data['perm_id'] ?? 0); ?>" />
    <input type="hidden" name="data[pid]" value="<?php echo e($pid ?? 0); ?>" />
    <div class="mb-3">
        <label for="data_title" class="form-label">权限名称</label>
        <input type="text" class="form-control" id="data_title" name="data[title]" value="<?php echo e($data['title']); ?>" placeholder="请输入权限名称" />
    </div>
    <div class="mb-3">
        <label for="data_perm_key" class="form-label">权限值</label>
        <input type="text" class="form-control" id="data_perm_key" name="data[perm_key]" value="<?php echo e($data['perm_key']); ?>" placeholder="权限值 controller/method" />
    </div>
    <div class="mb-3">
        <label class="form-label">扩展</label>
        <div class="d-flex gap-2 mb-2 flex-wrap">
            <button type="button" class="btn btn-sm btn-outline-info" onclick="quickExtras('view','查看')">查看</button>
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="quickExtras('add','新增')">新增</button>
            <button type="button" class="btn btn-sm btn-outline-warning" onclick="quickExtras('edit','编辑')">编辑</button>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="quickExtras('delete','删除')">删除</button>
        </div>
        <div class="input-group mb-3">
            <span class="input-group-text">名称</span>
            <input type="text" class="form-control" placeholder="子权限名称，如：查看" id="extras_title_input">
            <span class="input-group-text">值</span>
            <input type="text" class="form-control" placeholder="子权限标识，如：view" id="extras_key_input">
            <button class="btn btn-outline-secondary" type="button" onclick="addExtras()">添加</button>
        </div>
        <div id="perm-extras-container" class="overflow-y-auto" style="max-height: 200px">
            <?php foreach ($extras as $key => $title){ ?>
                <div class="input-group mb-2">
                    <span class="input-group-text">名称</span>
                    <input type="text" class="form-control" placeholder="请输入名称" name="extras[<?php echo e($key); ?>][title]" value="<?php echo e($title); ?>">
                    <span class="input-group-text">值</span>
                    <input type="text" class="form-control" placeholder="请输入值" name="extras[<?php echo e($key); ?>][key]" value="<?php echo e($key); ?>">
                    <button class="btn btn-outline-secondary" type="button" onclick="$(this).parent().remove()">删除</button>
                </div>
            <?php } ?>
        </div>
    </div>
    <div class="mb-3">
        <label for="data_description" class="form-label">权限描述</label>
        <input type="text" class="form-control" id="data_description" name="data[description]" value="<?php echo \zapcms\support\HtmlXss::clean($data['description']); ?>" placeholder="请输入权限描述" />
    </div>

</form>
<script>
    // 每次新增使用唯一时间戳，避免索引冲突
    function _nextExtrasIndex() {
        return 'e' + Date.now() + '_' + Math.random().toString(36).slice(2, 6);
    }

    function addExtras() {
        var elTitle = document.getElementById('extras_title_input');
        var elKey   = document.getElementById('extras_key_input');
        var title = elTitle.value.trim();
        var key   = elKey.value.trim();
        if (key === '' || title === '') {
            return;
        }

        var idx = _nextExtrasIndex();
        var html = '<div class="input-group mb-2">' +
            '<span class="input-group-text">名称</span>' +
            '<input type="text" class="form-control" placeholder="请输入名称" name="extras[' + idx + '][title]" value="' + _escape(title)  + '">' +
            '<span class="input-group-text">值</span>' +
            '<input type="text" class="form-control" placeholder="请输入值" name="extras[' + idx + '][key]" value="'   + _escape(key)    + '">' +
            '<button class="btn btn-outline-secondary" type="button" onclick="$(this).parent().remove()">删除</button>' +
            '</div>';
        $('#perm-extras-container').prepend(html);
        elTitle.value = '';
        elKey.value   = '';
        elTitle.focus();
    }

    function quickExtras(key, title) {
        document.getElementById('extras_key_input').value   = key;
        document.getElementById('extras_title_input').value = title;
        addExtras();
    }

    function _escape(str) {
        return String(str).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    // 回车快速添加
    document.addEventListener('keydown', function(e) {
        if (e.target && (e.target.id === 'extras_key_input' || e.target.id === 'extras_title_input') && e.key === 'Enter') {
            e.preventDefault();
            addExtras();
        }
    });
</script>
