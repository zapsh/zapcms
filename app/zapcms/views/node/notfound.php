<?php

use zap\facades\Url;

$this->layout('layouts/common');
?>


<main class="container">

    <div class="row p-0">
        <div class="col pt-2 pb-0">
            <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?php echo Url::action('Node') ?>">内容管理</a></li>
                    <li class="breadcrumb-item active" aria-current="page">系统错误</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="my-3 p-3 bg-body rounded shadow-sm">
        <div class="alert alert-warning" role="alert">
            <h4 class="alert-heading border-bottom"><i class="fa fa-warning"></i> 系统错误!</h4>
            <p><?php echo e($error); ?></p>
            <hr/>
            <a href="<?php echo url_action('Node') ;?>" class="btn btn-outline-dark">返回</a>
        </div>


    </div>


</main>
