<?php $this->layout('layouts/common');?>

    <div class="row">
        <div class="col-lg-8">
            <!-- 站点地图索引 -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">XML Sitemap 索引</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">
                        Sitemap 帮助搜索引擎发现和抓取网站内容。系统自动根据已发布的内容生成 XML 站点地图。
                    </p>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Sitemap 索引地址</label>
                        <div class="input-group">
                            <input type="text" class="form-control" readonly
                                   value="<?php echo htmlspecialchars($sitemap_index_url); ?>">
                            <a href="<?php echo htmlspecialchars($sitemap_index_url); ?>" target="_blank"
                               class="btn btn-outline-secondary">
                                <i class="fa-solid fa-arrow-up-right-from-square"></i> 查看
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 各类型站点地图 -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">分类型站点地图</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($sitemaps)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fa-solid fa-sitemap fa-3x mb-3 d-block"></i>
                            <p>暂无可生成站点地图的内容</p>
                            <p class="small">发布栏目、文章等内容后，对应的站点地图将在此显示</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>类型</th>
                                        <th>URL 数量</th>
                                        <th>站点地图地址</th>
                                        <th class="text-center">操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sitemaps as $item): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($item['type']); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['count']); ?> 条</td>
                                        <td>
                                            <code class="text-break"><?php echo htmlspecialchars($item['url']); ?></code>
                                        </td>
                                        <td class="text-center">
                                            <a href="<?php echo htmlspecialchars($item['url']); ?>" target="_blank"
                                               class="btn btn-sm btn-outline-secondary">
                                                <i class="fa-solid fa-eye"></i> 查看
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- 说明卡片 -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fa-solid fa-circle-info me-2"></i>关于 Sitemap</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <i class="fa-solid fa-check text-success me-2"></i>
                            系统自动包含所有<strong>"已发布"</strong>状态的节点
                        </li>
                        <li class="mb-2">
                            <i class="fa-solid fa-check text-success me-2"></i>
                            每个链接都包含 <code>lastmod</code>、<code>changefreq</code>、<code>priority</code>
                        </li>
                        <li class="mb-2">
                            <i class="fa-solid fa-check text-success me-2"></i>
                            支持将 Sitemap 地址提交到 Google Search Console 和 Bing Webmaster Tools
                        </li>
                        <li class="mb-2">
                            <i class="fa-solid fa-check text-success me-2"></i>
                            可以打开相应类型的 Sitemap 链接查看完整 XML
                        </li>
                    </ul>
                </div>
            </div>

            <!-- 快速提交卡片 -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fa-solid fa-paper-plane me-2"></i>提交到搜索引擎</h5>
                </div>
                <div class="card-body">
                    <p class="small text-muted">复制下方链接，手动提交到搜索引擎站长平台：</p>
                    <div class="d-grid gap-2">
                        <a href="https://search.google.com/search-console" target="_blank"
                           class="btn btn-outline-primary btn-sm">
                            <i class="fa-brands fa-google me-1"></i> Google Search Console
                        </a>
                        <a href="https://www.bing.com/webmasters" target="_blank"
                           class="btn btn-outline-info btn-sm">
                            <i class="fa-brands fa-microsoft me-1"></i> Bing Webmaster Tools
                        </a>
                        <a href="https://ziyuan.baidu.com/linksubmit/index" target="_blank"
                           class="btn btn-outline-warning btn-sm">
                            <i class="fa-brands fa-baidu me-1"></i> 百度站长平台
                        </a>
                        <a href="https://ziyuan.so.com/site/submit" target="_blank"
                           class="btn btn-outline-success btn-sm">
                            <i class="fa-solid fa-magnifying-glass me-1"></i> 360站长平台
                        </a>
                        <a href="https://www.sogou.com/webmasters" target="_blank"
                           class="btn btn-outline-dark btn-sm">
                            <i class="fa-solid fa-magnifying-glass me-1"></i> 搜狗站长平台
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
