<div class="row mb-3">
    <div class="col-sm-5">
        <a href="<?php echo e($parent_url); ?>" id="btn-parent" data-bs-toggle="tooltip" title="上一级" class="btn btn-light"><i class="fa-solid fa-level-up-alt"></i></a>
        <a href="<?php echo e($current_url); ?>" id="btn-refresh" data-bs-toggle="tooltip" title="刷新" class="btn btn-light"><i class="fa-solid fa-rotate"></i></a>
        <button type="button" data-bs-toggle="tooltip" title="Upload" id="button-upload" class="btn btn-primary"><i class="fa-solid fa-upload"></i></button>
        <button type="button" data-bs-toggle="tooltip" title="New Folder" id="button-folder" class="btn btn-light"><i class="fa-solid fa-folder"></i></button>
        <button type="button" data-bs-toggle="tooltip" title="Delete" id="button-delete" class="btn btn-danger"><i class="fa-regular fa-trash-can"></i></button>
        <input type="hidden" name="directory" value="" id="input-directory">
    </div>
    <div class="col-sm-7">
        <div class="input-group">
            <input type="text" name="search" value="" placeholder="Search.." id="input-search" class="form-control">
            <button type="button" id="button-search" data-bs-toggle="tooltip" title="Search" class="btn btn-primary"><i class="fa-solid fa-search"></i></button>
        </div>
    </div>
</div>
<div id="modal-folder" class="row mb-3" style="display: none;">
    <div class="col-sm-12">
        <div class="input-group">
            <div class="input-group">
                <input type="text" name="folder" value="" placeholder="Folder Name" id="input-folder" class="form-control">
                <button type="button" title="New Folder" id="button-create" class="btn btn-primary"><i class="fa-solid fa-plus-circle"></i></button>
            </div>
        </div>
    </div>
</div>
<hr>
<div class="row row-cols-sm-3 row-cols-lg-4 zapFinderFileList" >
    <?php foreach($data as $file){ ?>
        <div class="mb-3">
            <div class="mb-1" style="min-height: 140px;">
                <a href="" class="mb-1"><i class="<?php echo e($file['icon']); ?> fa-9x"></i></a>
            </div>
            <div class="form-check">
                <label for="input-path-0" class="form-check-label"><?php echo e($file['filename']); ?></label>
                <input type="checkbox" name="path[]" value="<?php echo e($file['path']); ?>" id="input-path-0" class="form-check-input">
            </div>
        </div>
    <?php } ?>

</div>
<script>
    $('#btn-refresh').on('click',function(event){
        event.preventDefault()
        event.stopPropagation()
        $('.zapFinderFileList').load($(this).attr('href'));
    })
</script>