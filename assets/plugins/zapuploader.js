/**
 * ZAPUploader
 * 文件 / 目录拖拽上传插件（零依赖、内置样式，无需额外引入 CSS）
 *
 * 示例:
 *   var upload = new ZAPUploader('#drop-area', {
 *       url: 'upload.php',
 *       allowedExtensions: '.jpg|.png|.jpeg',
 *       directoryUpload: true
 *   });
 *
 * @author zap
 * @param {string|HTMLElement} id    绑定的元素（#id / .class / 元素对象）
 * @param {object} options           配置项
 * @constructor
 */
(function (window, document) {
    'use strict';

    var VERSION = '2.0.1';

    /* ================================================================
     * 内置样式：首次实例化时自动注入 <style>，无需再引入 zapuploader.css
     * 若不需要可通过 options.injectCSS = false 关闭
     * ================================================================ */
    var ZAP_CSS = [
        '.zapUploader { border: 2px dashed #ccc; border-radius: 20px; width: 480px; margin: 50px auto; padding: 20px; }',
        '.zapUploader.highlight { border-color: dodgerblue; }',
        '.zapUploader input[type="file"] { display: none; }',
        '.zap-preview-template { display: none; }',
        '.zapUploader .zap-preview { text-align: left; }',
        '.zap-thumbnails { width: 100px; height: 100px; overflow: hidden; text-align: center; vertical-align: middle; }',
        '.zap-thumbnails img { width: 100px; position: relative; top: 50%; left: 50%; transform: translate(-50%, -50%); }',
        '.zap-none { display: none; }',
        '.zap-msg-error { font-weight: bold; color: red; }',
        '.zap-msg-success { font-weight: bold; color: green; }',
        '.zap-file-progress { font-weight: bold; color: green; }',
        '.zap-file-success-mark, .zap-file-error-mark { display: none; }'
    ].join('\n');

    var cssInjected = false;
    var bodyDefaultsBound = false; // body 级监听全局只绑定一次

    function injectCss() {
        if (cssInjected) return;
        var style = document.createElement('style');
        style.type = 'text/css';
        style.id = 'zapuploader-style';
        style.textContent = ZAP_CSS;
        (document.head || document.documentElement).appendChild(style);
        cssInjected = true;
    }

    /**
     * @param {string|HTMLElement} id
     * @param {object} options
     * @constructor
     */
    function ZAPUploader(id, options) {
        var $$this = this;

        /* ----------------------- 默认配置 ----------------------- */
        this.options = {
            url: null,                    // 上传地址
            method: 'post',               // 请求方法
            uploadMultiple: false,
            directoryUpload: false,       // 是否支持拖拽上传目录
            chunking: false,              // 预留：分片上传（暂未实现）
            chunkSize: 5000000,
            maxFileSize: null,            // 单文件大小上限，单位 MB，null 不限制
            previewContainer: null,       // 预览容器（选择器字符串或元素）
            messageContainer: null,       // 消息容器（选择器字符串或元素）
            maxFiles: 0,                  // 最大上传文件数量，0 表示不限制
            headers: {},                  // 自定义请求头
            customFormData: {},           // 附加表单字段
            acceptedFiles: null,          // 允许的 MIME 类型，如 'image/png,image/jpeg'
            allowedExtensions: '.*',      // 允许的扩展名，如 '.jpg|.png'
            autoUpload: true,             // 选择/拖拽后是否自动上传
            ignoreBadFiles: false,        // 忽略不合格文件（不报错）
            skipInvalidFile: false,
            queueSize: 5,                 // 同时上传的并发数
            dragoverClass: 'highlight',   // 拖拽经过时的高亮 class
            previewTemplate: null,        // 预览模板
            injectCSS: true,              // 是否自动注入内置样式
            withCredentials: false,       // 跨域是否携带凭证
            progress: function () {},
            processing: function () {},
            success: function () {},
            uploadStart: function () {
                var progress = $$this.dropArea.querySelector('.zap-progress');
                if (progress) progress.style.display = '';
            },
            complete: function () {},
            error: function (id, msg) {
                if (!$$this.messageContainer) return;
                if (id === null || id === undefined) {
                    $$this.messageContainer.innerHTML = '';
                    return;
                }
                var strong = document.createElement('strong');
                strong.className = 'zap-msg-error d-block';
                strong.textContent = msg;
                $$this.messageContainer.appendChild(strong);
            },
            addedfile: function () {},
            addfile: function () {},
            preview: function () {}
        };

        if (options && typeof options === 'object') {
            for (var key in options) {
                if (Object.prototype.hasOwnProperty.call(options, key)) {
                    this.options[key] = options[key];
                }
            }
        }

        if (this.options.injectCSS) injectCss();

        /* ----------------------- 解析绑定元素 ----------------------- */
        if (typeof id === 'string' && id.charAt(0) === '#') {
            this.dropArea = document.querySelector(id);
        } else if (typeof id === 'string' && id.charAt(0) === '.') {
            var nodeList = document.querySelectorAll(id);
            nodeList.forEach(function (value, index) {
                if (index === 0) {
                    $$this.dropArea = value;
                } else {
                    new ZAPUploader(value, options);
                }
            });
        } else if (id && typeof id === 'object') {
            this.dropArea = id;
        }

        if (!this.dropArea) {
            throw new Error('绑定元素 "' + id + '" 失败!');
        }

        /* ----------------------- 预览模板 ----------------------- */
        if (this.options.previewTemplate === null) {
            this.options.previewTemplate = this.createElement(
                '<div class="zap-file-details">' +
                '  <img class="zap-file-thumb" style="width: 100%"/>' +
                '  <span class="zap-file-name"></span><br/>' +
                '  <span class="zap-file-size"></span><br/>' +
                '  <span class="zap-file-progress"></span>' +
                '  <div class="zap-file-success-mark"><span>✔</span></div>' +
                '  <div class="zap-file-error-mark"><span>✘</span></div>' +
                '</div>'
            );
        } else if (typeof this.options.previewTemplate === 'string') {
            this.options.previewTemplate = this.createElement(this.options.previewTemplate);
        }

        /* ----------------------- 内部状态 ----------------------- */
        this.progressPercent = 0;
        this.uploadProgress = [];
        this.fileNumber = 0;          // 总文件数
        this.handlerFileNumber = 0;   // 成功数
        this._errorCount = 0;         // 失败数
        this._queueIndex = 0;         // 并发队列游标
        this._pending = 0;            // 进行中的请求数
        this._pendingReads = 0;       // 目录读取计数
        this._batchId = 0;            // 上传批次号，用于隔离新旧批次
        this.dragCounter = 0;         // 拖拽层级计数
        this.fileData = [];
        this.previewItems = [];
        this._listeners = [];         // 已绑定监听器（供 destroy 清理）

        /* ----------------------- DOM 元素 ----------------------- */
        this.inputFileElement = this.dropArea.querySelector('input[type="file"]');
        if (!this.inputFileElement) {
            // 容错：自动创建隐藏 input，保证 change / startUpload 可用
            this.inputFileElement = document.createElement('input');
            this.inputFileElement.type = 'file';
            this.inputFileElement.multiple = true;
            this.dropArea.appendChild(this.inputFileElement);
        }
        this.messageContainer = this.options.messageContainer === null
            ? this.dropArea.querySelector('.zap-message')
            : (typeof this.options.messageContainer === 'string'
                ? document.querySelector(this.options.messageContainer)
                : this.options.messageContainer);
        this.previewContainer = this.options.previewContainer === null
            ? this.dropArea.querySelector('.zap-preview')
            : (typeof this.options.previewContainer === 'string'
                ? document.querySelector(this.options.previewContainer)
                : this.options.previewContainer);
        this.progressBar = this.dropArea.querySelector('.zap-progress-bar');

        // 设置 accept 属性（只有未手动设置时才自动生成）
        if (!this.inputFileElement.hasAttribute('accept')) {
            var accept = this.buildAccept(this.options.allowedExtensions);
            if (accept) this.inputFileElement.setAttribute('accept', accept);
        }

        /* ======================= 拖拽事件 ======================= */
        var preventDefaults = function (e) {
            e.preventDefault();
            e.stopPropagation();
        };
        // body 级监听全局只注册一次，避免多实例重复绑定
        if (!bodyDefaultsBound) {
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(function (eventName) {
                document.body.addEventListener(eventName, preventDefaults, false);
            });
            bodyDefaultsBound = true;
        }

        // 统一绑定并记录监听器，方便 destroy() 时清理
        var on = function (el, type, fn) {
            el.addEventListener(type, fn, false);
            $$this._listeners.push([el, type, fn, false]);
        };

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(function (eventName) {
            on($$this.dropArea, eventName, preventDefaults);
        });

        // 使用计数器避免拖拽到子元素时高亮闪烁
        on($$this.dropArea, 'dragenter', function () {
            $$this.dragCounter++;
            $$this.dropArea.classList.add($$this.options.dragoverClass);
        });

        on($$this.dropArea, 'dragover', function () {
            $$this.dropArea.classList.add($$this.options.dragoverClass);
        });

        on($$this.dropArea, 'dragleave', function () {
            if (--$$this.dragCounter <= 0) {
                $$this.dragCounter = 0;
                $$this.dropArea.classList.remove($$this.options.dragoverClass);
            }
        });

        /* ======================= 选择文件 ======================= */
        var changeHandler = function (e) {
            if (!this.files || !this.files.length) return;
            $$this.reset();
            $$this.options.addfile(e);
            if ($$this.options.maxFiles !== 0 && this.files.length > $$this.options.maxFiles) {
                $$this.options.error(4, '文件上传数量超过最大限制');
                return;
            }
            for (var i = 0; i < this.files.length; i++) {
                if ($$this.checkFile(this.files[i])) {
                    $$this.addFile({ file: this.files[i], path: undefined });
                }
            }
            this.value = ''; // 允许重复选择同一文件
            $$this.startUpload();
        };
        on(this.inputFileElement, 'change', changeHandler);

        /* ======================= 拖拽上传 ======================= */
        var dropHandler = function (e) {
            $$this.dragCounter = 0;
            $$this.dropArea.classList.remove($$this.options.dragoverClass);
            $$this.reset();
            $$this.options.addfile(e);

            // 目录上传：遍历 webkitGetAsEntry
            if ($$this.options.directoryUpload && e.dataTransfer && e.dataTransfer.items) {
                var items = e.dataTransfer.items;
                var entryFound = false;
                $$this._pendingReads = 0;
                for (var k = 0; k < items.length; k++) {
                    var entry = items[k].webkitGetAsEntry ? items[k].webkitGetAsEntry() : null;
                    if (entry) {
                        entryFound = true;
                        $$this.addDirectory(entry);
                    }
                }
                if (entryFound) return; // 目录读取完成后自动触发 startUpload
            }

            var files = e.dataTransfer.files;
            if (!files || !files.length) {
                $$this.options.error(2, '没有可上传的文件');
                return;
            }
            if ($$this.options.maxFiles !== 0 && files.length > $$this.options.maxFiles) {
                $$this.options.error(4, '文件上传数量超过最大限制');
                return;
            }
            for (var j = 0; j < files.length; j++) {
                if (!files[j].type && !$$this.options.skipInvalidFile) {
                    $$this.options.error(1, '不支持上传目录');
                    $$this.clearPreviewContainer();
                    return;
                }
                if ($$this.checkFile(files[j])) {
                    $$this.addFile({ file: files[j], path: undefined });
                }
            }
            $$this.startUpload();
        };
        on(this.dropArea, 'drop', dropHandler);

        // 登记实例，便于统一管理
        ZAPUploader.instances.push(this);
    }

    /* ================================================================
     * 原型方法
     * ================================================================ */
    ZAPUploader.prototype = {

        constructor: ZAPUploader,

        /** 重置状态并清空预览（开始新一批上传前调用） */
        reset: function () {
            this.clear();
            this.initProgress();
            this.clearPreviewContainer();
        },

        /** 清空内部状态 */
        clear: function () {
            this.progressPercent = 0;
            this.uploadProgress = [];
            this.fileNumber = 0;
            this.handlerFileNumber = 0;
            this._errorCount = 0;
            this._queueIndex = 0;
            this._pending = 0;
            this._pendingReads = 0;
            this.fileData.length = 0;
            this.previewItems.length = 0;
        },

        /** 进度条整体更新 */
        progress: function (total, fileNumber, percent, previewItem) {
            if (this.progressBar) {
                if (this.progressBar.nodeName === 'PROGRESS') {
                    this.progressBar.value = total;
                } else {
                    this.progressBar.style.width = total + '%';
                }
            }
            this.options.progress(total, fileNumber, percent, previewItem);
        },

        /** 添加文件到队列并生成预览 */
        addFile: function (fileItem) {
            this.fileData.push(fileItem);
            var index = this.fileData.length - 1;
            var file = fileItem.file;
            var $$this = this;

            this.options.addedfile({ file: file, name: file.name, size: file.size }, index);

            if (!this.previewContainer) return;

            var previewFile = this.options.previewTemplate.querySelector('.zap-file-details').cloneNode(true);
            previewFile.classList.add('zap-filenumber-' + index);

            previewFile.querySelectorAll('.zap-file-name').forEach(function (el) {
                el.textContent = file.name; // textContent 防 XSS
            });
            previewFile.querySelectorAll('.zap-file-size').forEach(function (el) {
                el.textContent = $$this.humanFileSize(file.size);
            });
            previewFile.querySelectorAll('.zap-file-thumb').forEach(function (el) {
                // 仅图片生成缩略图，避免大文件 / 非图片产生内存开销
                if (file.type && file.type.indexOf('image/') === 0) {
                    var reader = new FileReader();
                    reader.onload = function () {
                        el.src = reader.result;
                    };
                    reader.readAsDataURL(file);
                } else {
                    el.style.display = 'none';
                }
            });

            this.previewItems[index] = previewFile;
            this.previewContainer.appendChild(previewFile);
        },

        createDiv: function (str, attributes) {
            var div = document.createElement('div');
            for (var prop in attributes) {
                if (Object.prototype.hasOwnProperty.call(attributes, prop)) {
                    div.setAttribute(prop, attributes[prop]);
                }
            }
            if (typeof str === 'string') {
                div.innerHTML = str;
            } else {
                div.appendChild(str);
            }
            return div;
        },

        createElement: function (str) {
            var div = document.createElement('div');
            div.innerHTML = str;
            return div.childNodes[0];
        },

        initProgress: function () {
            var progress = this.dropArea.querySelector('.zap-progress');
            if (progress) progress.style.display = 'none';
            if (this.progressBar) {
                if (this.progressBar.nodeName === 'PROGRESS') {
                    this.progressBar.value = 0;
                } else {
                    this.progressBar.style.width = '0%';
                }
            }
        },

        clearPreviewContainer: function () {
            if (this.previewContainer !== null && this.previewContainer !== undefined) {
                this.previewContainer.innerHTML = '';
            }
        },

        /** 文件大小格式化 */
        humanFileSize: function (bytes) {
            if (!bytes || bytes < 0) return '0 B';
            var units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB'];
            var e = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
            var value = bytes / Math.pow(1024, e);
            return (e === 0 ? value : value.toFixed(2)) + ' ' + units[e];
        },

        /** 根据扩展名生成 accept 属性值 */
        buildAccept: function (ext) {
            if (!ext || ext === '.*' || ext === '*.*') return '';
            return ext.split('|')
                .map(function (s) { return s.trim(); })
                .filter(Boolean)
                .join(',');
        },

        /** 递归读取目录（Chrome 每次 readEntries 最多返回 100 条，需循环读取） */
        addDirectory: function (item) {
            var $$this = this;
            if (item.isDirectory) {
                var reader = item.createReader();
                var readBatch = function () {
                    reader.readEntries(function (entries) {
                        if (!entries.length) return;
                        entries.forEach(function (entry) {
                            $$this.addDirectory(entry);
                        });
                        readBatch();
                    });
                };
                readBatch();
            } else {
                $$this._pendingReads++;
                item.file(function (file) {
                    if ($$this.checkFile(file)) {
                        $$this.addFile({ file: file, path: item.fullPath });
                    }
                    if (--$$this._pendingReads <= 0 && $$this.options.autoUpload) {
                        $$this.startUpload();
                    }
                });
            }
        },

        startUpload: function () {
            if (this.options.autoUpload && this.fileData.length) {
                this.processQueue();
            }
        },

        /** 按 queueSize 并发上传队列 */
        processQueue: function () {
            if (!this.fileData.length) return;
            this.options.uploadStart();
            this.uploadProgress = new Array(this.fileData.length).fill(0);
            this.fileNumber = this.fileData.length;
            this.handlerFileNumber = 0;
            this._errorCount = 0;
            this._queueIndex = 0;
            this._pending = 0;
            var batchId = ++this._batchId;

            var maxConcurrent = Math.max(1, parseInt(this.options.queueSize, 10) || 1);
            var startCount = Math.min(maxConcurrent, this.fileData.length);
            for (var i = 0; i < startCount; i++) {
                this._uploadNext(batchId);
            }
        },

        _uploadNext: function (batchId) {
            if (this._queueIndex >= this.fileData.length) return;
            var i = this._queueIndex++;
            this._pending++;
            this.uploadFile(this.fileData[i].file, i, this.fileData[i].path, batchId);
        },

        /** 单个文件上传进度更新 */
        updateProgress: function (fileNumber, percent) {
            this.uploadProgress[fileNumber] = percent;
            var total = 0;
            if (this.uploadProgress.length) {
                total = this.uploadProgress.reduce(function (t, c) { return t + c; }, 0) / this.uploadProgress.length;
            }
            this.progressPercent = total;

            var previewItem = this.previewItems[fileNumber];
            if (previewItem) {
                previewItem.querySelectorAll('.zap-file-progress').forEach(function (zfp) {
                    if (zfp.nodeName === 'SPAN') {
                        if (percent === 100) {
                            zfp.classList.add('zap-msg-success');
                            zfp.textContent = '100%';
                            var successMark = previewItem.querySelector('.zap-file-success-mark');
                            if (successMark) successMark.style.display = 'inline';
                        } else {
                            zfp.textContent = percent.toFixed(2) + '%';
                        }
                    } else if (zfp.classList.contains('progress-bar')) {
                        if (percent !== 100 && zfp.parentElement.classList.contains('zap-none')) {
                            zfp.parentElement.classList.remove('zap-none');
                        }
                        zfp.style.width = percent + '%';
                        if (percent === 100) {
                            zfp.parentElement.classList.add('zap-none');
                        }
                    } else if (zfp.nodeName === 'PROGRESS') {
                        zfp.value = percent;
                    }
                });
            }
            this.progress(total, fileNumber, percent, previewItem);
        },

        /** 标记单个文件上传失败 */
        markError: function (fileNumber) {
            var previewItem = this.previewItems[fileNumber];
            if (!previewItem) return;
            var errorMark = previewItem.querySelector('.zap-file-error-mark');
            if (errorMark) errorMark.style.display = 'inline';
            previewItem.querySelectorAll('.zap-file-progress').forEach(function (zfp) {
                if (zfp.nodeName === 'SPAN') {
                    zfp.classList.add('zap-msg-error');
                    zfp.textContent = '上传失败';
                }
            });
        },

        /** 全部上传完成（成功或失败）后触发 */
        complete: function (responseText, data) {
            if (this.progressBar) {
                this.progressBar.classList.add('zap-none');
            }
            this.options.complete(responseText, data);
        },

        /** 上传单个文件 */
        uploadFile: function (file, i, fullPath, batchId) {
            var $$this = this;
            var xhr = new XMLHttpRequest();
            var formData = new FormData();

            xhr.open((this.options.method || 'post').toUpperCase(), this.options.url, true);
            xhr.withCredentials = !!this.options.withCredentials;
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            for (var headerName in this.options.headers) {
                if (Object.prototype.hasOwnProperty.call(this.options.headers, headerName)) {
                    xhr.setRequestHeader(headerName, this.options.headers[headerName]);
                }
            }

            xhr.upload.addEventListener('progress', function (e) {
                if (e.lengthComputable) {
                    $$this.updateProgress(i, (e.loaded * 100.0 / e.total) || 100);
                }
            });

            xhr.addEventListener('readystatechange', function () {
                if (xhr.readyState !== 4) return;
                // 过期批次（上传过程中又重新拖入/选择了文件）的结果直接忽略
                if (batchId !== $$this._batchId) return;
                $$this._pending--;
                $$this._uploadNext(batchId); // 释放一个并发槽位

                if (xhr.status === 200) {
                    $$this.updateProgress(i, 100);
                    $$this.handlerFileNumber++;
                    $$this.options.success(i, xhr.responseText);
                } else {
                    $$this._errorCount++;
                    $$this.markError(i);
                    $$this.options.error(xhr.status, '文件上传错误 ' + xhr.responseText);
                }

                if ($$this.handlerFileNumber + $$this._errorCount === $$this.fileNumber) {
                    $$this.complete(xhr.responseText, { fileNumber: $$this.fileNumber });
                    $$this.clear();
                }
            });

            formData.append('file', file);
            if (typeof fullPath !== 'undefined') {
                formData.append('fullPath', fullPath);
            }
            // 调用 sending 回调，允许在发送前追加自定义表单字段（如当前目录 path）
            if (typeof this.options.sending === 'function') {
                this.options.sending(file, xhr, formData);
            }
            for (var name in this.options.customFormData) {
                if (Object.prototype.hasOwnProperty.call(this.options.customFormData, name)) {
                    formData.append(name, this.options.customFormData[name]);
                }
            }

            xhr.send(formData);
            this.options.processing(file, i);
        },

        /** 校验文件（扩展名 / MIME / 大小） */
        checkFile: function (file) {
            var fileName = file.name;
            var fileExtRegex = '(' + this.options.allowedExtensions + ')$';
            if (!fileName.match(new RegExp(fileExtRegex, 'gi'))) {
                if (!this.options.ignoreBadFiles) {
                    this.options.error(3, fileName + ' 文件格式不支持!');
                }
                return false;
            }
            if (this.options.acceptedFiles !== null) {
                var fileMimeType = file.type;
                var fileMimeTypeRegex = '(' + this.options.acceptedFiles.replace(/,/g, '|') + ')$';
                if (!fileMimeType.match(new RegExp(fileMimeTypeRegex, 'gi'))) {
                    if (!this.options.ignoreBadFiles) {
                        this.options.error(5, fileName + ' 文件格式不支持!');
                    }
                    return false;
                }
            }
            // 校验文件大小（MB）
            var fileSize = file.size / 1024 / 1024;
            if (this.options.maxFileSize !== null && fileSize > this.options.maxFileSize) {
                if (!this.options.ignoreBadFiles) {
                    this.options.error(4, fileName + ' 文件超出最大 ' + this.options.maxFileSize + 'MB 限制!');
                }
                return false;
            }
            return true;
        },

        /** 销毁实例：移除监听器、清空状态并从实例管理中移除 */
        destroy: function () {
            for (var i = 0; i < this._listeners.length; i++) {
                var item = this._listeners[i];
                item[0].removeEventListener(item[1], item[2], item[3]);
            }
            this._listeners.length = 0;
            this.clear();
            this.clearPreviewContainer();

            var index = ZAPUploader.instances.indexOf(this);
            if (index !== -1) ZAPUploader.instances.splice(index, 1);
            this.destroyed = true;
        }
    };

    ZAPUploader.version = VERSION;
    ZAPUploader.instances = []; // 当前页面所有实例，便于统一管理

    // 暴露全局
    window.ZAPUploader = ZAPUploader;
})(window, document);
