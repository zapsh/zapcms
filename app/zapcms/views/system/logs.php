<?php
use zap\facades\Url;
use zapcms\services\CacheManager;

$this->layout('layouts/common');

$relativeDir = str_replace(rtrim(str_replace('\\', '/', base_path()), '/') . '/', '', str_replace('\\', '/', $log_dir));
$levels = ['EMERGENCY', 'ALERT', 'CRITICAL', 'ERROR', 'WARNING', 'NOTICE', 'INFO', 'DEBUG'];
?>

<style>
    .log-viewer {
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace;
        font-size: 12px;
        line-height: 1.6;
    }

    .log-viewer .log-line {
        display: flex;
        gap: 8px;
        padding: 4px 10px;
        border-bottom: 1px solid var(--bs-border-color-translucent);
    }

    .log-viewer .log-line:last-child {
        border-bottom: 0;
    }

    .log-viewer .log-time {
        flex: 0 0 58px;
        color: var(--bs-secondary-color);
        white-space: nowrap;
    }

    .log-viewer .log-level {
        flex: 0 0 82px;
        text-align: center;
        font-weight: 600;
    }

    .log-viewer .log-msg {
        flex: 1 1 auto;
        word-break: break-word;
        white-space: pre-wrap;
        color: var(--bs-body-color);
    }

    .log-viewer .log-file {
        flex: 0 0 auto;
        color: var(--bs-secondary-color);
        white-space: nowrap;
    }

    .log-viewer .log-empty {
        padding: 30px;
        text-align: center;
        color: var(--bs-secondary-color);
    }
</style>

<!--begin::Main Row-->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa fa-file-lines card-header-icon text-primary"></i> 日志查看
                </h3>
                <div class="card-tools">
                    <span class="text-secondary small me-2">
                        <code><?php echo htmlspecialchars($relativeDir); ?></code>
                    </span>
                </div>
            </div>
            <div class="card-body">
                <!--begin::筛选-->
                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <select id="logFile" class="form-select form-select-sm">
                            <?php if (empty($log_files)): ?>
                                <option value="">暂无日志文件</option>
                            <?php endif; ?>
                            <?php foreach ($log_files as $file): ?>
                                <option value="<?php echo htmlspecialchars($file['name']); ?>">
                                    <?php echo htmlspecialchars($file['name']); ?>
                                    （<?php echo CacheManager::formatBytes($file['size']); ?>，
                                    <?php echo date('m-d H:i', $file['mtime']); ?>）
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select id="logLevel" class="form-select form-select-sm">
                            <option value="">全部级别</option>
                            <?php foreach ($levels as $level): ?>
                                <option value="<?php echo $level; ?>"><?php echo $level; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="text" id="logKeyword" class="form-control form-control-sm"
                               placeholder="关键字（回车搜索）">
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-primary btn-sm w-100" onclick="logSearch()">
                            <i class="fa fa-magnifying-glass me-1"></i>搜索
                        </button>
                    </div>
                </div>
                <!--end::筛选-->

                <!--begin::工具条-->
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span id="logMeta" class="small text-secondary">—</span>
                    <span class="small">
                        <label class="me-3 text-secondary" for="logAuto">
                            <input type="checkbox" id="logAuto"> 自动刷新（5 秒）
                        </label>
                        <a href="#" id="logDownload" class="me-2"><i class="fa fa-download me-1"></i>下载</a>
                        <a href="#" id="logClear" class="text-danger"><i class="fa fa-trash-can me-1"></i>清空</a>
                    </span>
                </div>
                <!--end::工具条-->

                <!--begin::日志列表-->
                <div id="logList" class="log-viewer border rounded bg-body-tertiary" style="max-height: 62vh; overflow: auto;">
                    <div class="log-empty">正在加载…</div>
                </div>
                <!--end::日志列表-->

                <!--begin::分页-->
                <div class="d-flex justify-content-between align-items-center mt-2">
                    <button type="button" id="logPrev" class="btn btn-sm btn-outline-secondary" disabled>
                        <i class="fa fa-chevron-left me-1"></i>上一页
                    </button>
                    <span id="logPage" class="small text-secondary">—</span>
                    <button type="button" id="logNext" class="btn btn-sm btn-outline-secondary" disabled>
                        下一页<i class="fa fa-chevron-right ms-1"></i>
                    </button>
                </div>
                <!--end::分页-->
            </div>
        </div>
    </div>
</div>
<!--end::Main Row-->

<script>
    var logDataUrl = '<?php echo Url::action('System@logsData'); ?>';
    var logDownloadUrl = '<?php echo Url::action('System@logsDownload'); ?>';
    var logClearUrl = '<?php echo Url::action('System@logsClear'); ?>';

    var logState = {page: 1, limit: 100, total: 0, loading: false};
    var logTimer = null;

    var logLevelClass = {
        EMERGENCY: 'text-danger',
        ALERT: 'text-danger',
        CRITICAL: 'text-danger',
        ERROR: 'text-danger',
        WARNING: 'text-warning',
        NOTICE: 'text-info',
        INFO: 'text-success',
        DEBUG: 'text-secondary'
    };

    function logEscape(text) {
        return String(text).replace(/[&<>"']/g, function (c) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c];
        });
    }

    function logParams() {
        return {
            file: $('#logFile').val() || '',
            level: $('#logLevel').val() || '',
            keyword: $('#logKeyword').val() || '',
            page: logState.page
        };
    }

    function logLoad(scrollBottom) {
        if (logState.loading) {
            return;
        }

        if (!$('#logFile').val()) {
            $('#logList').html('<div class="log-empty">暂无日志文件</div>');
            $('#logMeta').text('—');
            return;
        }

        logState.loading = true;

        $.ajax({
            url: logDataUrl,
            method: 'post',
            dataType: 'json',
            data: logParams(),
            success: function (res) {
                if (res.code !== 0) {
                    $('#logList').html('<div class="log-empty">' + logEscape(res.msg) + '</div>');
                    return;
                }

                logRender(res.data);

                var box = document.getElementById('logList');
                box.scrollTop = scrollBottom ? box.scrollHeight : 0;
            },
            error: function (xhr) {
                $('#logList').html('<div class="log-empty">请求失败：' + xhr.status + '</div>');
            }
        }).always(function () {
            logState.loading = false;
        });
    }

    function logRender(data) {
        logState.total = data.total;

        var html = '';

        if (!data.entries.length) {
            html = '<div class="log-empty">没有匹配的日志</div>';
        } else {
            data.entries.forEach(function (item) {
                var time = item.time ? item.time.substring(11, 19) : '';
                var cls = logLevelClass[item.level] || 'text-secondary';

                html += '<div class="log-line">'
                    + '<span class="log-time">' + logEscape(time) + '</span>'
                    + '<span class="log-level ' + cls + '">' + logEscape(item.level || 'LOG') + '</span>'
                    + '<span class="log-msg">' + logEscape(item.message) + '</span>'
                    + (item.file ? '<span class="log-file">' + logEscape(item.file) + '</span>' : '')
                    + '</div>';
            });
        }

        $('#logList').html(html);

        var from = data.total === 0 ? 0 : data.offset + 1;
        var to = Math.min(data.offset + data.limit, data.total);
        $('#logMeta').text('共 ' + data.total + ' 条（读取最近 5000 行内），当前显示 ' + from + '-' + to);

        var pages = Math.max(1, Math.ceil(data.total / data.limit));
        $('#logPage').text('第 ' + logState.page + ' / ' + pages + ' 页');
        $('#logPrev').prop('disabled', logState.page <= 1);
        $('#logNext').prop('disabled', logState.page >= pages);
    }

    function logSearch() {
        logState.page = 1;
        logLoad(false);
    }

    $(function () {
        $('#logFile, #logLevel').on('change', logSearch);
        $('#logKeyword').on('keydown', function (e) {
            if (e.key === 'Enter') {
                logSearch();
            }
        });

        $('#logPrev').on('click', function () {
            if (logState.page > 1) {
                logState.page--;
                logLoad(false);
            }
        });

        $('#logNext').on('click', function () {
            if (logState.page * logState.limit < logState.total) {
                logState.page++;
                logLoad(true);
            }
        });

        $('#logDownload').on('click', function (e) {
            e.preventDefault();

            if (!$('#logFile').val()) {
                return;
            }

            window.location.href = logDownloadUrl + '?file=' + encodeURIComponent($('#logFile').val());
        });

        $('#logClear').on('click', function (e) {
            e.preventDefault();

            var file = $('#logFile').val();
            var label = file ? file : '全部日志';

            Swal.fire({
                title: '确认清空' + label + '？',
                text: '日志内容将被清空且不可恢复。',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '立即清空',
                cancelButtonText: '取消',
                confirmButtonColor: '#dc3545'
            }).then(function (result) {
                if (!result.isConfirmed) {
                    return;
                }

                $.ajax({
                    url: logClearUrl,
                    method: 'post',
                    dataType: 'json',
                    data: {file: file || ''},
                    success: function (res) {
                        ZapToast.alert(res.msg, {
                            bgColor: res.code === 0 ? bgSuccess : bgDanger,
                            position: Toast_Pos_Center
                        });

                        if (res.code === 0) {
                            logSearch();
                        }
                    }
                });
            });
        });

        $('#logAuto').on('change', function () {
            if (this.checked) {
                logTimer = setInterval(function () {
                    if (logState.page === 1) {
                        var box = document.getElementById('logList');
                        var atBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 40;
                        logLoad(atBottom);
                    }
                }, 5000);
            } else {
                clearInterval(logTimer);
                logTimer = null;
            }
        });

        logLoad(false);
    });
</script>
