<?php
defined('IN_ZAPCMS') or die('No permission to access');
$this->extend('layout/default');
$this->beginBlock('content');
/**
 * @var array $node
 */
?>
    <?php echo $this->partial('partials/_breadcrumb'); ?>
    <div class="container">
        <div class="row">
            <?php echo $this->partial('partials/_sidebar'); ?>
            <div class="col-sm-9">
                <div class="content-wrap detail-wrap">
                    <div class="row">
                        <div class="col-sm-12">
                            <article class="single-post">
                                <header class="entry-header">
                                    <h1 class="entry-title"><?php echo htmlspecialchars($node['title']); ?></h1>
                                </header>
                                <div class="entry-content">
                                    <?php echo $node['content']; ?>
                                </div>
                            </article>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php $this->endBlock(); ?>
