<?php
defined('IN_ZAPCMS') or die('No permission to access');
$this->extend('layout/default');
$this->beginBlock('content');
/**
 * @var array $nodes
 */
?>
    <?php echo $this->partial('partials/_breadcrumb'); ?>
    <div class="container">
        <div class="row">
            <?php echo $this->partial('partials/_sidebar'); ?>
            <div class="col-sm-9">
                <div class="content-wrap faq-page " style="margin-bottom:30px">
                    <!-- 搜索框 & 工具栏 -->
                    <div class="faq-toolbar">
                        <div class="faq-search-wrap">
                            <i class="fa fa-search faq-search-icon"></i>
                            <input type="text" id="faqSearch" class="faq-search-input" placeholder="搜索常见问题…" autocomplete="off" />
                            <span class="faq-search-clear" id="faqSearchClear" style="display:none;">&times;</span>
                        </div>
                        <button type="button" class="faq-toggle-all" id="faqToggleAll" title="展开全部">
                            <i class="fa fa-plus-square-o"></i> 展开全部
                        </button>
                    </div>

                    <!-- 搜索结果提示 -->
                    <div class="faq-no-result" id="faqNoResult" style="display:none;">
                        <i class="fa fa-meh-o"></i>
                        <p>没有找到匹配的问题，请尝试其他关键词</p>
                    </div>

                    <!-- FAQ 列表 -->
                    <div class="faq-list" id="faqList">
                        <?php
                        $lastCategory = null;
                        foreach ($nodes as $index => $node):
                            $catTitle = $node['category_title'] ?? '';
                            $artId = md5($node['title']);
                        ?>
                            <?php if ($catTitle && $catTitle !== $lastCategory): ?>
                                <?php if ($lastCategory !== null): ?>
                                    </div><!-- /.faq-category-body -->
                                <?php endif; ?>
                                <div class="faq-category" data-category="<?php echo htmlspecialchars($catTitle); ?>">
                                    <div class="faq-category-header">
                                        <i class="fa fa-folder-open"></i>
                                        <span><?php echo htmlspecialchars($catTitle); ?></span>
                                    </div>
                                    <div class="faq-category-body">
                            <?php $lastCategory = $catTitle; ?>
                            <?php endif; ?>

                            <div class="faq-item" data-keywords="<?php echo htmlspecialchars($node['title'] . ' ' . strip_tags($node['content'] ?? '')); ?>">
                                <div class="faq-question" role="button" tabindex="0" id="faq-<?php echo $artId; ?>">
                                    <span class="faq-q-icon">Q</span>
                                    <span class="faq-q-text"><?php echo htmlspecialchars($node['title']); ?></span>
                                    <i class="fa fa-chevron-down faq-chevron"></i>
                                </div>
                                <div class="faq-answer" id="faq-answer-<?php echo $artId; ?>">
                                    <div class="faq-answer-inner">
                                        <span class="faq-a-icon">A</span>
                                        <div class="faq-a-content">
                                            <?php echo $node['content']; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($lastCategory !== null): ?>
                                    </div><!-- /.faq-category-body -->
                                </div><!-- /.faq-category -->
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function() {
        var DUR = 300;
        var list = document.getElementById('faqList');
        if (!list) return;

        var search = document.getElementById('faqSearch');
        var clearBtn = document.getElementById('faqSearchClear');
        var noResult = document.getElementById('faqNoResult');
        var toggleAll = document.getElementById('faqToggleAll');

        function each(root, selector, fn) {
            Array.prototype.forEach.call(root.querySelectorAll(selector), fn);
        }

        function isVisible(el) {
            return !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
        }

        // ---- 高度过渡动画（原生 JS，无 jQuery 依赖）----
        function finish(answer) {
            var handler = function(e) {
                if (e && e.target !== answer) return;
                answer.removeEventListener('transitionend', handler);
                clearTimeout(answer._faqTimer);
                answer.style.display = '';
                answer.style.height = '';
                answer.style.overflow = '';
                answer.style.transition = '';
            };
            answer.addEventListener('transitionend', handler);
            clearTimeout(answer._faqTimer);
            answer._faqTimer = setTimeout(handler, DUR + 60);
        }

        function setOpen(item, open, animate) {
            var answer = item.querySelector('.faq-answer');
            if (!answer) return;

            if (!animate) {
                item.classList.toggle('open', open);
                answer.style.display = '';
                answer.style.height = '';
                answer.style.overflow = '';
                answer.style.transition = '';
                return;
            }

            clearTimeout(answer._faqTimer);
            if (open) {
                item.classList.add('open');
                answer.style.display = 'block';
                answer.style.overflow = 'hidden';
                answer.style.height = '0px';
                var target = answer.scrollHeight;
                void answer.offsetHeight;
                answer.style.transition = 'height ' + DUR + 'ms ease';
                answer.style.height = target + 'px';
            } else {
                // 先保持可见再收拢，避免 display:none 打断动画
                answer.style.display = 'block';
                answer.style.overflow = 'hidden';
                answer.style.height = answer.scrollHeight + 'px';
                void answer.offsetHeight;
                item.classList.remove('open');
                answer.style.transition = 'height ' + DUR + 'ms ease';
                answer.style.height = '0px';
            }
            finish(answer);
        }

        function updateToggleBtn() {
            if (!toggleAll) return;
            var total = 0;
            var opened = 0;
            each(list, '.faq-item', function(item) {
                if (!isVisible(item)) return;
                total++;
                if (item.classList.contains('open')) opened++;
            });
            var expanded = total > 0 && opened === total;
            toggleAll.innerHTML = expanded
                ? '<i class="fa fa-minus-square-o"></i> 收起全部'
                : '<i class="fa fa-plus-square-o"></i> 展开全部';
        }

        // ---- 折叠 / 展开单个 ----
        list.addEventListener('click', function(e) {
            var q = e.target.closest('.faq-question');
            if (!q || !list.contains(q)) return;
            var item = q.closest('.faq-item');
            if (!item) return;
            setOpen(item, !item.classList.contains('open'), true);
            updateToggleBtn();
        });

        // ---- 展开 / 收起全部 ----
        if (toggleAll) {
            toggleAll.addEventListener('click', function() {
                var expanded = 0;
                var total = 0;
                each(list, '.faq-item', function(item) {
                    if (!isVisible(item)) return;
                    total++;
                    if (item.classList.contains('open')) expanded++;
                });
                var open = !(total > 0 && expanded === total);
                each(list, '.faq-item', function(item) {
                    if (!isVisible(item)) return;
                    setOpen(item, open, true);
                });
                updateToggleBtn();
            });
        }

        // ---- 搜索过滤 ----
        if (search) {
            search.addEventListener('input', function() {
                var val = this.value.trim().toLowerCase();
                if (clearBtn) clearBtn.style.display = val.length > 0 ? '' : 'none';

                var hasResult = false;
                each(list, '.faq-item', function(item) {
                    var kw = (item.getAttribute('data-keywords') || '').toLowerCase();
                    var matched = kw.indexOf(val) !== -1;
                    item.style.display = matched ? '' : 'none';
                    if (!matched) return;
                    hasResult = true;
                    // 搜索命中时自动展开
                    if (val.length > 0) setOpen(item, true, false);
                });

                // 隐藏 / 显示空分类
                each(list, '.faq-category', function(cat) {
                    var visible = false;
                    each(cat, '.faq-item', function(item) {
                        if (isVisible(item)) visible = true;
                    });
                    cat.style.display = visible ? '' : 'none';
                });

                if (noResult) noResult.style.display = hasResult ? 'none' : '';
                updateToggleBtn();
            });
        }

        if (clearBtn && search) {
            clearBtn.addEventListener('click', function() {
                search.value = '';
                search.dispatchEvent(new Event('input', { bubbles: true }));
                search.focus();
            });
        }

        // ---- URL hash 直接定位到某个 FAQ ----
        function openByHash() {
            var hash = window.location.hash;
            if (!hash || hash.indexOf('#faq-') !== 0) return;
            var target = document.getElementById(hash.slice(1));
            if (!target) return;
            var item = target.closest('.faq-item');
            if (!item) return;
            if (!item.classList.contains('open')) setOpen(item, true, false);
            var top = target.getBoundingClientRect().top + window.pageYOffset - 100;
            window.scrollTo({ top: top, behavior: 'smooth' });
            updateToggleBtn();
        }
        openByHash();
        window.addEventListener('hashchange', openByHash);

        // ---- 键盘支持 ----
        list.addEventListener('keydown', function(e) {
            var q = e.target.closest('.faq-question');
            if (!q) return;
            if (e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar') {
                e.preventDefault();
                q.click();
            }
        });
    })();
    </script>
<?php $this->endBlock(); ?>
