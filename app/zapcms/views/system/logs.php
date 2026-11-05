<?php
use zap\facades\Url;
use zapcms\services\CacheManager;

$this->layout('layouts/common');

$isAudit     = ($active_tab === 'audit');
$relativeDir = str_replace(rtrim(str_replace('\\', '/', base_path()), '/') . '/', '', str_replace('\\', '/', $log_dir));
$levels      = ['EMERGENCY', 'ALERT', 'CRITICAL', 'ERROR', 'WARNING', 'NOTICE', 'INFO', 'DEBUG'];
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
        flex: 0 0 145px;
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

    /* 审计日志表格 */
    .log-audit-table { font-size: 12px; }
    .log-audit-table > thead > tr > th { white-space: nowrap; }
    .log-audit-table > tbody > tr > td { vertical-align: middle; }
    .log-audit-table .log-nowrap { white-space: nowrap; }
    .log-audit-table .log-event { min-width: 220px; max-width: 420px; word-break: break-word; }
    .log-audit-table .log-url { max-width: 230px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .log-audit-detail > td { background: var(--bs-tertiary-bg); }
    .log-audit-detail dl { margin-bottom: 0; }
    .log-audit-detail dd { word-break: break-all; margin-bottom: .35rem; }
</style>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa <?php echo $isAudit ? 'fa-shield-halved text-success' : 'fa-file-lines text-primary'; ?> card-header-icon"></i>
                    <?php echo $isAudit ? '审计日志' : '日志查看'; ?>
                </h3>
                <div class="card-tools">
                    <span class="text-secondary small me-2">
                        <code><?php echo $isAudit ? 'admin_logs' : e($relativeDir); ?></code>
                    </span>
                </div>
            </div>
            <div class="card-body">
                <!--begin::两个 tab：点击切换 URL 与显示内容-->
                <ul class="nav nav-tabs mb-3" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo $isAudit ? 'active' : ''; ?>"
                           href="<?php echo e($tab_urls['audit']); ?>">
                            <i class="fa fa-shield-halved me-1"></i>审计日志
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo $isAudit ? '' : 'active'; ?>"
                           href="<?php echo e($tab_urls['file']); ?>">
                            <i class="fa fa-file-lines me-1"></i>系统日志
                        </a>
                    </li>
                </ul>
                <!--end::两个 tab-->

                <!--begin::筛选-->
                <div class="row g-2 mb-3">
                    <?php if ($isAudit): ?>
                        <div class="col-md-3">
                            <select id="logCategory" class="form-select form-select-sm">
                                <option value="">全部分类</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo e($cat); ?>"><?php echo e($cat); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php else: ?>
                        <div class="col-md-5">
                            <select id="logFile" class="form-select form-select-sm">
                                <?php if (empty($log_files)): ?>
                                    <option value="">暂无日志文件</option>
                                <?php endif; ?>
                                <?php foreach ($log_files as $file): ?>
                                    <option value="<?php echo e($file['name']); ?>">
                                        <?php echo e($file['name']); ?>
                                        （<?php echo e(CacheManager::formatBytes($file['size'])); ?>，
                                        <?php echo e(date('m-d H:i', $file['mtime'])); ?>）
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <div class="col-md-2">
                        <select id="logLevel" class="form-select form-select-sm">
                            <option value="">全部级别</option>
                            <?php foreach ($levels as $level): ?>
                                <option value="<?php echo e($level); ?>"><?php echo e($level); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
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
                <div id="logList" class="<?php echo $isAudit ? '' : 'log-viewer '; ?>border rounded bg-body-tertiary"
                     style="max-height: 62vh; overflow: auto;">
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

<script>
    var logDataUrl     = '<?php echo Url::action('System@logsData'); ?>';
    var logDownloadUrl = '<?php echo Url::action('System@logsDownload'); ?>';
    var logClearUrl    = '<?php echo Url::action('System@logsClear'); ?>';

    // 当前 tab 对应的数据源，随 URL 的 ?tab= 由服务端决定
    var logSource = '<?php echo $isAudit ? 'db' : 'file'; ?>';
    var logState  = {page: 1, limit: 100, total: 0, loading: false, source: logSource};
    var logTimer  = null;

    var logLevelClass = {
        EMERGENCY: 'text-danger', ALERT: 'text-danger', CRITICAL: 'text-danger', ERROR: 'text-danger',
        WARNING: 'text-warning', NOTICE: 'text-info', INFO: 'text-success', DEBUG: 'text-secondary'
    };
    var logLevelBadge = {
        EMERGENCY: 'text-bg-danger', ALERT: 'text-bg-danger', CRITICAL: 'text-bg-danger', ERROR: 'text-bg-danger',
        WARNING: 'text-bg-warning', NOTICE: 'text-bg-info', INFO: 'text-bg-secondary', DEBUG: 'text-bg-light'
    };

    function logEscape(text) {
        return String(text == null ? '' : text).replace(/[&<>"']/g, function (c) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c];
        });
    }

    // 文件日志是 ISO8601，审计日志是 Y-m-d H:i:s，统一展示完整日期时间
    function logFormatTime(value) {
        var m = String(value || '').match(/^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2}:\d{2})/);
        return m ? m[1] + ' ' + m[2] : String(value || '');
    }

    function logParams() {
        var p = {
            source: logState.source,
            level: $('#logLevel').val() || '',
            keyword: $('#logKeyword').val() || '',
            page: logState.page
        };
        if (logState.source === 'db') {
            p.category = $('#logCategory').val() || '';
        } else {
            p.file = $('#logFile').val() || '';
        }
        return p;
    }

    function logLoad(scrollBottom) {
        if (logState.loading) {
            return;
        }
        if (logState.source !== 'db' && !$('#logFile').val()) {
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

        if (logState.source === 'db') {
            logRenderAudit(data);
        } else {
            logRenderFile(data);
        }

        var from = data.total === 0 ? 0 : data.offset + 1;
        var to = Math.min(data.offset + data.limit, data.total);
        var prefix = logState.source === 'db'
            ? '共 ' + data.total + ' 条审计记录，当前显示 ' + from + '-' + to
            : '共 ' + data.total + ' 条（读取最近 5000 行内），当前显示 ' + from + '-' + to;
        $('#logMeta').text(prefix);

        var pages = Math.max(1, Math.ceil(data.total / data.limit));
        $('#logPage').text('第 ' + logState.page + ' / ' + pages + ' 页');
        $('#logPrev').prop('disabled', logState.page <= 1);
        $('#logNext').prop('disabled', logState.page >= pages);
    }

    function logRenderFile(data) {
        if (!data.entries.length) {
            $('#logList').html('<div class="log-empty">没有匹配的日志</div>');
            return;
        }
        var html = '';
        data.entries.forEach(function (item) {
            var cls = logLevelClass[item.level] || 'text-secondary';
            html += '<div class="log-line">'
                + '<span class="log-time">' + logEscape(logFormatTime(item.time)) + '</span>'
                + '<span class="log-level ' + cls + '">' + logEscape(item.level || 'LOG') + '</span>'
                + '<span class="log-msg">' + logEscape(item.message) + '</span>'
                + (item.file ? '<span class="log-file">' + logEscape(item.file) + '</span>' : '')
                + '</div>';
        });
        $('#logList').html(html);
    }

    // 审计日志：完整展示 admin_logs 的全部字段，详情行展开 URL / UA / 原始内容
    function logRenderAudit(data) {
        if (!data.entries.length) {
            $('#logList').html('<div class="log-empty">没有匹配的审计记录</div>');
            return;
        }

        var html = '<div class="table-responsive"><table class="table table-sm table-hover mb-0 log-audit-table">'
            + '<thead class="table-light"><tr>'
            + '<th>时间</th><th>级别</th><th>分类</th><th>用户</th><th>事件</th>'
            + '<th>IP</th><th>请求</th><th class="text-end">详情</th>'
            + '</tr></thead><tbody>';

        data.entries.forEach(function (item, i) {
            var badge = logLevelBadge[item.level] || 'text-bg-secondary';
            var detailId = 'logAuditDetail' + i;
            var event = item.title || item.message || '';

            html += '<tr>'
                + '<td class="log-nowrap">' + logEscape(logFormatTime(item.time)) + '</td>'
                + '<td><span class="badge ' + badge + '">' + logEscape(item.level || 'LOG') + '</span></td>'
                + '<td><span class="badge text-bg-light text-secondary">' + logEscape(item.category || '-') + '</span></td>'
                + '<td class="log-nowrap">' + logEscape(item.username || 'system')
                + (item.uid ? ' <span class="text-secondary">#' + logEscape(item.uid) + '</span>' : '') + '</td>'
                + '<td class="log-event">' + logEscape(event) + '</td>'
                + '<td class="log-nowrap">' + logEscape(item.ipaddress || '-') + '</td>'
                + '<td class="log-url" title="' + logEscape(item.request_url || '') + '">'
                + logEscape(item.request_url || '-') + '</td>'
                + '<td class="text-end"><button type="button" class="btn btn-sm btn-light py-0 px-2"'
                + ' data-log-detail="' + detailId + '" title="展开详情">'
                + '<i class="fa fa-chevron-down"></i></button></td>'
                + '</tr>';

            html += '<tr id="' + detailId + '" class="d-none log-audit-detail"><td colspan="8">'
                + '<dl class="row small mb-0">'
                + '<dt class="col-sm-2 col-lg-1">记录 ID</dt><dd class="col-sm-10 col-lg-11">'
                + logEscape(item.id || '-') + '</dd>'
                + '<dt class="col-sm-2 col-lg-1">完整内容</dt><dd class="col-sm-10 col-lg-11">'
                + logEscape(item.content || '-') + '</dd>'
                + '<dt class="col-sm-2 col-lg-1">请求 URL</dt><dd class="col-sm-10 col-lg-11">'
                + logEscape(item.request_url || '-') + '</dd>'
                + '<dt class="col-sm-2 col-lg-1">User-Agent</dt><dd class="col-sm-10 col-lg-11">'
                + logEscape(item.user_agent || '-') + '</dd>'
                + '</dl></td></tr>';
        });

        html += '</tbody></table></div>';
        $('#logList').html(html);
    }

    function logSearch() {
        logState.page = 1;
        logLoad(false);
    }

    $(function () {
        $('#logFile, #logCategory, #logLevel').on('change', logSearch);
        $('#logKeyword').on('keydown', function (e) {
            if (e.key === 'Enter') {
                logSearch();
            }
        });

        $('#logList').on('click', '[data-log-detail]', function () {
            $('#' + $(this).attr('data-log-detail')).toggleClass('d-none');
            $(this).find('i').toggleClass('fa-chevron-down fa-chevron-up');
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
            if (logState.source === 'db') {
                window.location.href = logDownloadUrl + '?source=db';
                return;
            }
            if (!$('#logFile').val()) {
                return;
            }
            window.location.href = logDownloadUrl + '?file=' + encodeURIComponent($('#logFile').val());
        });

        $('#logClear').on('click', function (e) {
            e.preventDefault();

            var file = $('#logFile').val();
            var label = logState.source === 'db' ? '审计日志' : (file ? file : '全部日志');

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
                    data: {source: logState.source, file: file || ''},
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
