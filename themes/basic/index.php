<?php
defined('IN_ZAPCMS') or die('No permission to access');
$this->extend('layout/default');
$banners = option_get_json('basic_home.slide','[]',true);
$latestNews = pageState()->getLatestNews();

?>
<main class="site-main">
    <!-- Slider main container -->
    <div class="swiper">
        <!-- Additional required wrapper -->
        <div class="swiper-wrapper">
            <?php foreach($banners as $banner){ ?>
            <div class="swiper-slide " >
                <?php $slideTarget = (($banner['target'] ?? '_self') === '_blank') ? '_blank' : '_self'; ?>
                <a href="<?php echo $banner['link'] ?? '#';?>" target="<?php echo $slideTarget; ?>" <?php echo $slideTarget === '_blank' ? 'rel="noopener"' : ''; ?> style="background: url('<?php echo $banner['img_path'];?>') no-repeat center center; background-size: cover; ">
                </a>
            </div>
            <?php } ?>


        </div>
        <div class="swiper-pagination"></div>

        <div class="swiper-button-prev"></div>
        <div class="swiper-button-next"></div>

    </div>

    <?php if(option("basic_home.about_us")): ?>
    <section class="about-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-12 text-center">
                    <h2 class="section-title">关于我们</h2>
                    <p class="desc">About Us</p>
                    <div class="about-content">
                        <?php echo option("basic_home.about_us"); ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if(option('basic_home.service_title')): ?>
    <section class="services">
        <div class="container">
            <h2 class="section-title"><?php echo option('basic_home.service_title'); ?></h2>
            <p class="desc"><?php echo option('basic_home.service_subtitle'); ?></p>
            <div class="row">
                <?php 
                $serviceCount = 0;
                for ($i = 1; $i < 7; $i++) { 
                    if(option("basic_home.service{$i}_title")) {
                        $serviceCount++;
                    }
                }
                $colClass = $serviceCount <= 3 ? 4 : ($serviceCount <= 4 ? 3 : 4);
                ?>
                <?php for ($i = 1; $i < 7; $i++) { 
                    if(!option("basic_home.service{$i}_title")) continue;
                ?>
                <div class="col-lg-<?php echo $colClass; ?> col-md-6 col-sm-12">
                    <div class="service-card">
                        <?php if(option("basic_home.service{$i}_icon")): ?>
                        <div class="service-icon">
                            <i class="<?php echo option("basic_home.service{$i}_icon"); ?>"></i>
                        </div>
                        <?php endif; ?>
                        <h3 class="service-title"><?php echo option("basic_home.service{$i}_title"); ?></h3>
                        <p class="service-desc"><?php echo option("basic_home.service{$i}_content"); ?></p>
                    </div>
                </div>
                <?php } ?>
            </div>
        </div>
    </section>
    <?php endif; ?>
    
    <?php if(!empty($latestNews)): ?>
    <section class="home-area">
        <div class="container">
            <div class="section-header">
                <h2 class="sub_title">最新动态</h2>
                <p class="section-desc">Latest News</p>
            </div>
            <div class="home_list">
                <div class="row">
                    <?php foreach ($latestNews as $news){ ?>
                    <div class="col-lg-6 col-md-6 col-sm-12">
                        <div class="news-card">
                            <a href="<?php echo smart_node_url($news); ?>" class="news-link">
                                <div class="news-image">
                                    <img src="<?php echo \zapcms\helpers\ThumbHelper::thumb($news['image'], (int)\zapcms\services\NodeType::getConfig($news['node_type'] ?? 'article', 'list_image_width', 400), (int)\zapcms\services\NodeType::getConfig($news['node_type'] ?? 'article', 'list_image_height', 300)); ?>" alt="<?php echo $news['title']; ?>">
                                    <div class="news-overlay">
                                        <i class="fa fa-link"></i>
                                    </div>
                                </div>
                                <div class="news-content">
                                    <span class="news-date"><i class="fa fa-calendar"></i> <?php echo date('Y-m-d', $news['pub_time']); ?></span>
                                    <h3 class="news-title"><?php echo $news['title']; ?></h3>
                                    <p class="news-excerpt"><?php echo \zap\util\Str::truncate(strip_tags($news['content']),80); ?></p>
                                </div>
                            </a>
                        </div>
                    </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>
</main>

<script>
    const swiper = new Swiper('.swiper', {
        loop: true,
        speed: 1000,
        autoplay: {
            delay: 5000,
            disableOnInteraction: false,
        },
        effect: 'fade',
        fadeEffect: {
            crossFade: true
        },
        pagination: {
            el: '.swiper-pagination',
            clickable: true,
        },
        navigation: {
            nextEl: '.swiper-button-next',
            prevEl: '.swiper-button-prev',
        },
    });
</script>
