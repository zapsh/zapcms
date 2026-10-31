<?php

use zapcms\support\Asset;
use zap\facades\Url;

Asset::library('jqueryvalidation');
$this->layout('layouts/common');

$this->view->page_title = 'Slug 生成设置';
$this->view->page_subtitle = '配置 URL 别名（Slug）的生成规则';
?>

<form id="zapForm">

    <div class="row g-3">
        <div class="col-12">
            <div class="card card-outline card-success">
                <div class="card-header d-flex align-items-center ps-3 pt-0 pb-0 pe-0">
                    <ul class="nav nav-underline me-auto" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active" id="basic-tab" data-bs-toggle="tab"
                                    data-bs-target="#basic-tab-pane" type="button" role="tab"
                                    aria-controls="basic-tab-pane" aria-selected="true">
                                <i class="fa fa-link me-1"></i>基本设置
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="api-tab" data-bs-toggle="tab"
                                    data-bs-target="#api-tab-pane" type="button" role="tab"
                                    aria-controls="api-tab-pane" aria-selected="false">
                                <i class="fa fa-language me-1"></i>翻译 API
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="preview-tab" data-bs-toggle="tab"
                                    data-bs-target="#preview-tab-pane" type="button" role="tab"
                                    aria-controls="preview-tab-pane" aria-selected="false">
                                <i class="fa fa-eye me-1"></i>效果预览
                            </button>
                        </li>
                    </ul>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool btn-sm" onclick="save()" title="保存">
                            <i class="fa fa-save text-success"></i>
                        </button>
                    </div>
                </div>

                <div class="tab-content card-body p-3">
                    <!-- 基本设置 -->
                    <div class="tab-pane fade show active" id="basic-tab-pane" role="tabpanel"
                         aria-labelledby="basic-tab" tabindex="0">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="slug.separator" class="form-label small">分隔符</label>
                                <select class="form-select form-select-sm" id="slug.separator" name="options[slug.separator]">
                                    <option value="-" <?php if (($slug_separator ?? '-') === '-') echo 'selected'; ?>>中划线 ( - )</option>
                                    <option value="_" <?php if (($slug_separator ?? '-') === '_') echo 'selected'; ?>>下划线 ( _ )</option>
                                </select>
                                <div class="form-text">URL 中单词之间的分隔符，推荐使用中划线，更利于 SEO。</div>
                            </div>

                            <div class="col-md-6">
                                <label for="slug.style" class="form-label small">生成方式</label>
                                <select class="form-select form-select-sm" id="slug.style" name="options[slug.style]">
                                    <option value="default" <?php if (($slug_style ?? 'default') === 'default') echo 'selected'; ?>>保留中文（默认）</option>
                                    <option value="pinyin" <?php if (($slug_style ?? 'default') === 'pinyin') echo 'selected'; ?>>中文转拼音</option>
                                    <option value="translate" <?php if (($slug_style ?? 'default') === 'translate') echo 'selected'; ?>>中文翻译成英文</option>
                                </select>
                                <div class="form-text">
                                    <strong>保留中文</strong>：保持中文字符不变，清理特殊符号。<br>
                                    <strong>中文转拼音</strong>：将中文转换为拼音，如「你好世界」→ ni-hao-shi-jie。<br>
                                    <strong>中文翻译成英文</strong>：通过百度翻译 API 将中文翻译为英文后生成 Slug，如「你好世界」→ hello-world。
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="slug.max_length" class="form-label small">最大长度限制</label>
                                <input type="number" class="form-control form-control-sm" id="slug.max_length"
                                       name="options[slug.max_length]"
                                       placeholder="0 表示不限制"
                                       min="0" max="200"
                                       value="<?php echo e($slug_max_length ?? '0'); ?>" />
                                <div class="form-text">超过限制的 Slug 将被截断。设置为 0 表示不限制长度。</div>
                            </div>

                            <div class="col-12">
                                <div class="alert alert-info mb-0 py-2 px-3 small">
                                    <i class="fa fa-info-circle me-1"></i>
                                    <strong>提示：</strong>修改 Slug 生成方式后，仅对新创建/编辑的内容生效。已有内容的 Slug 不会自动更新。
                                    如使用「中文翻译成英文」模式，请先在 <strong>翻译 API</strong> 标签中配置百度翻译接口参数。
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 翻译 API 设置 -->
                    <div class="tab-pane fade" id="api-tab-pane" role="tabpanel"
                         aria-labelledby="api-tab" tabindex="0">
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="alert alert-warning py-2 px-3 small mb-3">
                                    <i class="fa fa-exclamation-triangle me-1"></i>
                                    使用「中文翻译成英文」功能需要
                                    <a href="https://fanyi-api.baidu.com/product/113" target="_blank" class="alert-link">百度翻译 API</a>
                                    的 APP ID 和密钥。
                                    百度翻译每月提供免费额度，申请即可使用。
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="slug.baidu_appid" class="form-label small">百度翻译 APP ID</label>
                                <input type="text" class="form-control form-control-sm font-monospace" id="slug.baidu_appid"
                                       name="options[slug.baidu_appid]"
                                       placeholder="请输入百度翻译平台的 APP ID"
                                       value="<?php echo htmlspecialchars($slug_baidu_appid ?? ''); ?>" />
                            </div>
                            <div class="col-md-6">
                                <label for="slug.baidu_key" class="form-label small">百度翻译密钥（Key）</label>
                                <input type="password" class="form-control form-control-sm font-monospace" id="slug.baidu_key"
                                       name="options[slug.baidu_key]"
                                       placeholder="请输入百度翻译平台的密钥"
                                       value="<?php echo htmlspecialchars($slug_baidu_key ?? ''); ?>" />
                                <div class="form-text">
                                    <a href="https://api.fanyi.baidu.com/api/trans/product/desktop" target="_blank">如何获取 APP ID 和密钥？</a>
                                </div>
                            </div>
                            <div class="col-12">
                                <button type="button" class="btn btn-outline-primary btn-sm" id="btn-test-translate">
                                    <i class="fa fa-check-circle me-1"></i>测试翻译接口
                                </button>
                                <span id="test-translate-result" class="ms-2 small"></span>
                            </div>
                        </div>
                    </div>

                    <!-- 效果预览 -->
                    <div class="tab-pane fade" id="preview-tab-pane" role="tabpanel"
                         aria-labelledby="preview-tab" tabindex="0">
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="test_title" class="form-label small">输入测试文本</label>
                                <input type="text" class="form-control form-control-sm" id="test_title"
                                       placeholder="例如：你好世界Hello World" />
                            </div>
                            <div class="col-12">
                                <div class="card bg-light">
                                    <div class="card-body py-3">
                                        <table class="table table-sm table-borderless mb-0 small">
                                            <tr>
                                                <td class="text-muted text-end" style="width:110px;">保留中文：</td>
                                                <td><code id="preview_default" class="text-dark">--</code></td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted text-end">中文转拼音：</td>
                                                <td><code id="preview_pinyin" class="text-dark">--</code></td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted text-end">翻译成英文：</td>
                                                <td><code id="preview_translate" class="text-dark">--</code></td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted text-end"><strong>当前设置：</strong></td>
                                                <td><code id="preview_current" class="text-success fw-bold">--</code></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer text-center">
                    <button type="button" class="btn btn-success" onclick="save()">
                        <i class="fa fa-save me-1"></i>保存设置
                    </button>
                </div>
            </div>
        </div>
    </div>

</form>

<script>
    var ajaxUrl = '<?php echo Url::current();?>';
    var slugAjaxUrl = '<?php echo url_action('System@ajaxSlug'); ?>';

    $(function(){
        $('#zapForm').validate({ignore:''});
    })

    function save(){
        var zapForm = $('#zapForm');
        if (!zapForm.valid()) {
            ZapToast.alert('请检查表单输入', {bgColor: bgWarning, position: Toast_Pos_Center});
            return false;
        }
        var load = Zap.loading('正在保存，请稍后');
        $.ajax({
            url: ajaxUrl,
            method: 'post',
            data: zapForm.serialize(),
            dataType: 'json',
            success: function (data) {
                if (data.code === 0) {
                    ZapToast.alert(data.msg, {bgColor: bgSuccess, position: Toast_Pos_Center});
                    updatePreview();
                } else {
                    ZapToast.alert(data.msg, {bgColor: bgDanger, position: Toast_Pos_Center});
                }
            }
        }).always(function () {
            load.dispose()
        });
    }

    $('#test_title').on('input', function() {
        updatePreview();
    });

    $('#slug\\.separator, #slug\\.style').on('change', function() {
        updatePreview();
    });

    function updatePreview() {
        var text = $('#test_title').val().trim();
        if (!text) {
            $('#preview_default').text('--');
            $('#preview_pinyin').text('--');
            $('#preview_translate').text('--');
            $('#preview_current').text('--');
            return;
        }

        var separator = $('#slug\\.separator').val() || '-';
        var style = $('#slug\\.style').val() || 'default';

        // 四行预览，各行独立 key，互不干扰
        getServerSlug(text, 'default', separator, 'default', function(slug) {
            $('#preview_default').text(slug || '(空)');
        });

        getServerSlug(text, 'pinyin', separator, 'pinyin', function(slug) {
            $('#preview_pinyin').text(slug || '(空)');
        });

        getServerSlug(text, 'translate', separator, 'translate', function(slug) {
            $('#preview_translate').text(slug || '(空)');
        });

        getServerSlug(text, style, separator, 'current', function(slug) {
            $('#preview_current').text(slug || '(空)');
        });
    }

    var _slugTimers = {};
    var _slugSeq = {};
    function getServerSlug(title, style, separator, label, callback) {
        var key = label;
        _slugSeq[key] = (_slugSeq[key] || 0) + 1;
        var currentSeq = _slugSeq[key];

        if (_slugTimers[key]) {
            clearTimeout(_slugTimers[key]);
        }
        _slugTimers[key] = setTimeout(function() {
            delete _slugTimers[key];
            $.getJSON(slugAjaxUrl, {
                title: title,
                style: style,
                separator: separator
            }, function(res) {
                if (_slugSeq[key] !== currentSeq) return;
                if (res.code === 0) {
                    callback(res.slug);
                } else {
                    callback('--');
                }
            });
        }, 250);
    }

    updatePreview();

    // 测试翻译接口
    $('#btn-test-translate').on('click', function() {
        var appid = $('#slug\\.baidu_appid').val().trim();
        var key   = $('#slug\\.baidu_key').val().trim();
        var $btn  = $(this);
        var $res  = $('#test-translate-result');

        if (!appid || !key) {
            $res.html('<span class="text-danger">请先填写 APP ID 和密钥</span>');
            return;
        }

        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i>测试中...');
        $res.html('');

        $.getJSON(slugAjaxUrl, {
            title: '你好世界',
            style: 'translate'
        }, function(data) {
            if (data.code === 0 && data.slug !== '你好世界' && data.slug !== 'post') {
                $res.html('<span class="text-success"><i class="fa fa-check-circle me-1"></i>翻译成功！示例：「你好世界」 → <strong>' + data.slug + '</strong></span>');
            } else if (data.slug === '你好世界') {
                $res.html('<span class="text-danger"><i class="fa fa-times-circle me-1"></i>翻译失败，返回了原文。请检查 APP ID 和密钥是否正确。</span>');
            } else {
                $res.html('<span class="text-warning"><i class="fa fa-question-circle me-1"></i>返回了意外结果：「' + data.slug + '」，请检查 API 配置。</span>');
            }
        }).fail(function() {
            $res.html('<span class="text-danger"><i class="fa fa-times-circle me-1"></i>请求失败，请检查网络和 API 地址。</span>');
        }).always(function() {
            $btn.prop('disabled', false).html('<i class="fa fa-check-circle me-1"></i>测试翻译接口');
        });
    });
</script>
