const bgPrimary = 'text-bg-primary';
const bgSecondary = 'text-bg-secondary';
const bgSuccess = 'text-bg-success';
const bgDanger = 'text-bg-danger';
const bgWarning = 'text-bg-warning';
const bgInfo = 'text-bg-info';
const bgDark = 'text-bg-dark';

const Toast_Pos_TopCenter = 'topCenterToast';
const Toast_Pos_TopRight = 'topRightToast';
const Toast_Pos_Center = 'centerToast';

$(function(){
    const navbarSideCollapse = document.querySelector('#navbarSideCollapse');
    if (navbarSideCollapse !== null) {
        navbarSideCollapse.addEventListener('click', () => {
            document.querySelector('.offcanvas-collapse').classList.toggle('open')
        })
    }

    $(document.body).on('click','button[data-zap-toggle=\"image\"]',function(e){
        e.preventDefault();
        const $btn = $(this);
        Zap.finder({
            target:$btn.data('zap-target'),
            callback:$btn.data('zap-callback'),
            size:$btn.data('zap-size')
        });
    });

});



// 各位置容器的默认 class，供页面缺少容器时按需创建
const ToastContainerClass = {
    topCenterToast:'toast-container position-fixed p-3 top-0 start-50 translate-middle-x',
    topRightToast:'toast-container position-fixed p-3 top-0 end-0',
    centerToast:'toast-container position-fixed p-3 top-50 start-50 translate-middle',
    bottomRightToast:'toast-container position-fixed p-3 bottom-0 end-0'
};

/**
 * 全局消息提示（Bootstrap Toast）
 *   ZapToast.notify(msg, {bgColor:bgSuccess, position:Toast_Pos_TopCenter, delay:1500, callback:fn})
 *   ZapToast.success(msg[, params]) / ZapToast.error(msg[, params])
 *   ZapToast.alert(msg[, params])  旧调用方式，等价于 notify
 */
const ZapToast = {
    // 页面没有布局容器时（如 AJAX 载入的表单）自动创建，避免空引用导致提示丢失
    container:function(position){
        let el = document.getElementById(position);
        if(el === null){
            el = document.createElement('div');
            el.id = position;
            el.className = ToastContainerClass[position] || ToastContainerClass[Toast_Pos_TopRight];
            el.setAttribute('data-original-class', el.className);
            document.body.appendChild(el);
        }
        return el;
    },
    notify:function(msg,params){
        // 兼容 alert({title:'msg', bg:...}) 对象调用方式
        if(msg !== null && typeof msg === 'object'){
            const obj = msg;
            msg = obj.title || obj.message || obj.msg || '';
            params = Object.assign({}, obj, params);
            // 映射 bg → bgColor
            if(params.bg && !params.bgColor){
                params.bgColor = params.bg;
            }
        }
        const opt = Object.assign({
            delay:1500,
            bgColor:bgSuccess,
            position:Toast_Pos_TopCenter,
            icon:'',
            title:'',
            callback:null
        }, params || {});

        const el = document.createElement('div');
        el.className = 'toast align-items-center ' + opt.bgColor;
        el.setAttribute('role','alert');
        el.setAttribute('aria-live','assertive');
        el.setAttribute('aria-atomic','true');
        el.innerHTML = '<div class="d-flex"><div class="toast-body">'
            + (opt.icon ? '<i class="' + opt.icon + ' me-2"></i>' : '')
            + (opt.title ? '<strong class="me-2">' + opt.title + '</strong>' : '')
            + (msg === undefined || msg === null ? '' : msg)
            + '</div>'
            + '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>'
            + '</div>';

        // 追加而不是覆盖 innerHTML，连续提示不会互相顶掉
        this.container(opt.position).appendChild(el);

        let finished = false;
        const finish = function(){
            if(finished){ return; }
            finished = true;
            if(el.parentNode){ el.parentNode.removeChild(el); }
            if(typeof opt.callback === 'function'){ opt.callback(); }
        };

        if(typeof bootstrap !== 'undefined' && bootstrap.Toast){
            el.addEventListener('hidden.bs.toast', finish);
            bootstrap.Toast.getOrCreateInstance(el, {delay:opt.delay}).show();
        }else{
            // bootstrap 未加载时退化为静态提示，保证用户仍然看得到反馈
            el.classList.add('show');
            setTimeout(finish, opt.delay);
        }
        return el;
    },
    alert:function(msg,params){
        return this.notify(msg, params);
    },
    success:function(msg,params){
        return this.notify(msg, Object.assign({bgColor:bgSuccess, icon:'fa fa-check-circle'}, params || {}));
    },
    error:function(msg,params){
        return this.notify(msg, Object.assign({bgColor:bgDanger, icon:'fa fa-times-circle'}, params || {}));
    }
};




// ZAP Modal

const ZapModal = {
    loadding:function(color){
        if(color === undefined){
            color = 'text-success';
        }
        return `<div class="spinner-border ${color}" role="status">
              <span class="visually-hidden">Loading...</span>
            </div>`;
    },
    create:function(params,replaced){
        const defaultParams = {
            title:null,
            content:'',
            id:'defaultZapModal',
            callback:function(){},
            keyboard:true,
            focus:true,
            backdrop:'',
            buttons:null,
            url:false,
            dialog_class:'modal-dialog-scrollable modal-dialog-centered modal-lg',
            header_class:'bg-success text-white',
            body_class:'',
        }
        if(replaced === undefined){replaced = false;}
        /**
         *  buttons:[{title:"test",callback:function(){},close:true}]
         */
        if(params === undefined){
            params = {}
        }
        params = Object.assign(defaultParams,params);
        if($('#' + params.id).length > 0 && replaced === false){
            return new bootstrap.Modal('#' + params.id);
        }
        if(!params.backdrop){params.backdrop = ' data-bs-backdrop="static" data-bs-keyboard="false" ';}
        var modalTpl = '<div class="modal fade" id="'+params.id+'" '+params.backdrop+' tabindex="-1" aria-labelledby="'+params.id+'Label" aria-hidden="true">\n' +
            '  <div class="modal-dialog '+params.dialog_class+'">\n' +
            '    <div class="modal-content">\n' ;
        if(params.title !== null){
            modalTpl += '<div class="modal-header '+params.header_class+' pt-2 pb-2">\n' +
                '        <h1 class="modal-title fs-5" id="'+params.id+'Label">'+params.title+'</h1>\n' +
                '        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>\n' +
                '      </div>\n';
        }
        modalTpl += `<div class="modal-body ${params.body_class}">${params.content}</div>` ;
        if(params.buttons !== null){
            modalTpl += '<div class="modal-footer">\n' ;
            for (const button in params.buttons) {
                btn = params.buttons[button];
                index = parseInt(button) + 1;
                if(btn.title === undefined){btn.title = '';}
                if(btn.class === undefined){btn.class = 'btn-primary';}
                if(btn.close){
                    modalTpl += '<button type="button" data-index="'+index+'" class="btn btn-sm '+btn.class+'" data-bs-dismiss="modal">'+btn.title+'</button>\n' ;
                }else{
                    modalTpl += '<button type="button" data-index="'+index+'" class="btn btn-sm '+btn.class+'" >'+btn.title+'</button>\n' ;
                }
            }
        modalTpl +=    '      </div>\n';
        }
        modalTpl +=  '    </div>\n' +
            '  </div>\n' +
            '</div>';
        if(replaced===true){
            $('#' + params.id).remove();
        }
        if($('#' + params.id).length === 0){
            $(document.body).append(modalTpl);
        }
        if(params.url!==false){
            $('#' + params.id + ' .modal-body').load(params.url,params.callback);
        }
        $('#' + params.id + ' .modal-footer > button').on('click',function (e) {
            const btnName = 'btn'+$(e.target).data('index');
            if(params[btnName] !== undefined){
                const callback = params[btnName];
                callback(e);
            }
        });
        return new bootstrap.Modal('#' + params.id);
    },


};

/**
 * 轻量加载提示：固定定位的半透明遮罩 + 转圈
 *
 * 之前用 Bootstrap Modal 实现，Modal 弹出时会给 body 加 .modal-open
 * （overflow:hidden + 补偿滚动条宽度），页面宽度突变、内容重排，
 * 短请求时一闪一闪非常明显。这里只用一层 fixed 覆盖层，
 * 不改动 body、不影响滚动条，布局零位移。
 *
 * 用法不变：
 *   const load = Zap.loading('正在保存，请稍后');
 *   ...ajax... .always(function(){ load.dispose(); });
 */
const ZapLoading = {
    styleId:'zapLoadingStyle',
    maskId:'zapLoadingMask',
    // 延迟出现：150ms 内就结束的请求不显示遮罩，避免一闪而过
    delay:150,
    showTimer:null,
    hideTimer:null,
    css:'.zap-loading-mask{position:fixed;top:0;right:0;bottom:0;left:0;z-index:1095;display:flex;'
        + 'align-items:center;justify-content:center;opacity:0;visibility:hidden;transition:opacity .15s linear;'
        + 'background:rgba(255,255,255,.5)}'
        + '.zap-loading-mask.show{opacity:1;visibility:visible}'
        + '[data-bs-theme=dark] .zap-loading-mask{background:rgba(0,0,0,.45)}'
        + '.zap-loading-box{display:flex;flex-direction:column;align-items:center;gap:.5rem;padding:1rem 1.5rem;'
        + 'border-radius:.5rem;background:var(--bs-body-bg,#fff);box-shadow:0 .5rem 1rem rgba(0,0,0,.15)}'
        + '.zap-loading-text{font-size:.875rem;color:var(--bs-body-color,#212529)}',
    ensureStyle:function(){
        if(document.getElementById(this.styleId) !== null){ return; }
        const style = document.createElement('style');
        style.id = this.styleId;
        style.textContent = this.css;
        document.head.appendChild(style);
    },
    mask:function(){
        this.ensureStyle();
        let el = document.getElementById(this.maskId);
        if(el === null){
            el = document.createElement('div');
            el.id = this.maskId;
            el.className = 'zap-loading-mask';
            el.setAttribute('role','status');
            el.setAttribute('aria-live','polite');
            el.innerHTML = '<div class="zap-loading-box">'
                + '<div class="spinner-border text-success" role="status"><span class="visually-hidden">Loading...</span></div>'
                + '<div class="zap-loading-text"></div>'
                + '</div>';
            document.body.appendChild(el);
        }
        return el;
    },
    show:function(title){
        const el = this.mask();
        el.querySelector('.zap-loading-text').textContent = title || '加载中，请稍后...';
        clearTimeout(this.showTimer);
        clearTimeout(this.hideTimer);
        const self = this;
        this.showTimer = setTimeout(function(){
            void el.offsetWidth; // 强制回流，保证 opacity 过渡生效
            el.classList.add('show');
        }, self.delay);
        return {
            element:el,
            dispose:function(){ ZapLoading.hide(); },
            hide:function(){ ZapLoading.hide(); }
        };
    },
    hide:function(){
        clearTimeout(this.showTimer);
        const el = document.getElementById(this.maskId);
        if(el === null){ return; }
        el.classList.remove('show');
        clearTimeout(this.hideTimer);
        // 等淡出结束再移除节点，连续请求时复用同一个遮罩
        this.hideTimer = setTimeout(function(){
            if(el.parentNode){ el.parentNode.removeChild(el); }
        }, 200);
    }
};

var Zap = {
    loading:function(title){
        return ZapLoading.show(title);
    },
    /**
     * 内容管理（节点）表单保存
     *
     *   Zap.saveNodeForm({
     *       isNew: true,                              // 当前是否为新建页
     *       beforeSerialize: function($form){},        // 可选，序列化前的清理（如 cleanGallery）
     *       onSaved: function(data, isNew){}           // 可选，额外后处理
     *   });
     *
     * 保存成功后不再 location.reload()：
     *   1. 新建 → 写入真实 node_id，并用 history.replaceState 把地址切到 edit/{id}
     *      （不发请求、不刷新），之后再次保存就是“修改”，不会重复创建内容；
     *   2. 回写服务端修正过的 slug（重名时补 -id 后缀）、刷新“更新时间”、
     *      把标题从“添加X”改成“修改X”。
     * 这样滚动位置、编辑器光标、已展开的 tab 都不会丢。
     */
    saveNodeForm:function(options){
        options = options || {};
        const f = $(options.form || '#zapForm');
        if(f.length === 0){ ZapToast.error('表单未找到，无法保存'); return; }
        if(!f.valid()){ ZapToast.error(options.invalidMsg || '请修改错误项，重新提交'); return; }
        if(typeof options.beforeSerialize === 'function'){ options.beforeSerialize(f); }
        const l = Zap.loading(options.loadingMsg || '正在保存，请稍后');
        const isNew = options.isNew === true;
        $.ajax({
            // 实时取当前地址：新建成功后地址已被 replaceState 改成 edit/{id}，
            // 若用渲染时写死的地址，再次保存会又打回 add 从而重复创建
            url: window.location.href,
            method:'post', data:f.serialize(), dataType:'json',
            success:function(d){
                if(d && d.code === 0){
                    Zap.applyNodeSaved(d, isNew);
                    if(typeof options.onSaved === 'function'){ options.onSaved(d, isNew); }
                    ZapToast.success(d.msg || '保存成功', {delay:1500});
                }else{
                    ZapToast.error((d && d.msg) || '保存失败，请稍后重试');
                }
            },
            error:function(xhr){ ZapToast.error('保存失败：' + xhr.status); }
        }).always(function(){ l.dispose(); });
    },
    /**
     * 保存成功后就地更新页面状态（saveNodeForm 内部调用，也可单独调用）
     */
    applyNodeSaved:function(d, isNew){
        if(!d){ return; }
        if(d.id){
            $('input[name=node_id]').val(d.id);
        }
        if(isNew && d.redirect_to){
            window.history.replaceState(null, '', d.redirect_to);
        }
        if(d.slug && $('#node_slug').length){
            $('#node_slug').val(d.slug);
            if(typeof updateSlugPreview === 'function'){ updateSlugPreview(); }
        }
        if(d.update_time){
            $('#node_update_time_label').text(Zap.formatTime(d.update_time));
        }
        if(isNew && d.title){
            $('#zapPageTitle').text(d.title);
            $('.breadcrumb .breadcrumb-item.active').last().text(d.title);
        }
    },
    formatTime:function(timestamp){
        const d = new Date(parseInt(timestamp, 10) * 1000);
        if(isNaN(d.getTime())){ return ''; }
        const p = function(n){ return n < 10 ? '0' + n : String(n); };
        return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate())
            + ' ' + p(d.getHours()) + ':' + p(d.getMinutes());
    },
    EnableToolTip:function (){
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]')
        const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl))
    },
    AjaxPost:function(settings){
        settings['method'] = 'post';
        return $.ajax(settings);
    },
    alert:function(msg,title){
        const alert = new ZapDialog({content:msg,title:title,backdrop:false});
        alert.show()
    },
    finder:function(options){
        options = options || {};
        options.title = options.title  || "文件管理器";
        options.path = options.path || '';
        options.target = options.target || '';
        options.size = options.size || '';
        options.callback = options.callback || '';

        // 已有弹窗实例：复用并同步最新目标配置，避免重复加载文件列表
        if(window.zapFinder){
            window.zapFinder.finderConfig = {
                target: options.target,
                callback: options.callback,
                size: options.size
            };
            // 同步外部上下文（如 summernote 编辑器实例），供回调使用
            window.zapFinder.settings.context = options.context;
            window.zapFinder.show();
            return window.zapFinder;
        }

        window.zapFinder = new ZapDialog({
            id:'zapFinder',
            url:ZAP_BASE_URL + '/finder/list?initialize=true&path=' + encodeURIComponent(options.path) +'&target='+encodeURIComponent(options.target) +  '&callback='+encodeURIComponent(options.callback) + '&size='+encodeURIComponent(options.size),
            title:options.title,
            backdrop:false,
            context:options.context,
            dialogClass:'modal-xl'
        });
        // 目标选择器/回调/尺寸统一由弹窗实例持有，finder/list.php 动态读取
        window.zapFinder.finderConfig = {
            target: options.target,
            callback: options.callback,
            size: options.size
        };
        window.zapFinder.show()
        return window.zapFinder;
    }

};

function ZapDialog(settings){
    const defaultSettings = {
        id:null,
        title:null,
        closeBtn: true,
        header:true,
        footer:true,
        content:'',
        loading:true,
        replace:false,
        backdrop:true,
        dialogClass:'',
        modalClass:'',
        headerClass:'',
        contentClass:'',
        bodyClass:'',
        footerClass:'',
        url:null,
        buttons:[],
        events:{},
        callback:function(){}
    };
    this.settings = Object.assign(defaultSettings,settings);
    this.target = null;
    if(this.settings.id === null) this.settings.id = Zap.RandID();
    this.modalBody = function(body){
        if(this.settings.url !== null && this.settings.loading === true && body === ''){
            body = `<div class="spinner-border text-success" role="status">
                      <span class="visually-hidden">Loading...</span>
                    </div>`;
        }
        return `<div class="modal-body ${this.settings.bodyClass}">${body}</div>`;
    }
    this.modalFooter = function(){
        if(this.settings.buttons.length === 0) return '';
        const buttons = [];
        for (let btnIndex in this.settings.buttons){
            const btn = this.settings.buttons[btnIndex];
            btn.class = btn.class || 'btn btn-success btn-sm';
            btn.title = btn.title || '默认按钮';
            btn.close = btn.close === undefined ? ' data-bs-dismiss="modal"' : '';
            buttons.push(`<button type="button" data-index="${btnIndex}" class="${btn.class}" ${btn.close}>${btn.title}</button>`);
        }
        return `<div class="modal-footer ${this.settings.footerClass}">${buttons.join('')}</div>`;
    }

    this.modalHeader = function(value){
        if(this.settings.title === null || this.settings.title === undefined) return '';
        let closeBtn = `<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>`;
        if(this.settings.closeBtn === false){
            closeBtn = '';
        }else if(this.settings.closeBtn !== true){
            closeBtn = this.settings.closeBtn;
        }
        return `<div class="modal-header"><h5 class="modal-title">${value}</h5>${closeBtn}</div>`;
    }
    this.render = function(){

        const header = this.modalHeader(this.settings.title)
        const body = this.modalBody(this.settings.content)
        const footer = this.modalFooter()
        const backdrop = this.settings.backdrop === true ? `data-bs-backdrop="static" data-bs-keyboard="false"`:'';
        return `<div class="modal  ${this.settings.modalClass}" tabindex="-1" id="${this.settings.id}" ${backdrop} aria-hidden="true">    
                  <div class="modal-dialog ${this.settings.dialogClass}">
                    <div class="modal-content ${this.settings.contentClass}">
                      ${header}
                      ${body}
                      ${footer}
                    </div>
                  </div>
                </div>`;
    }
    this.create=function(){
        const q = document.querySelector('#'+this.settings.id);
        if(this.settings.replace === true && q !==null){
            q.remove();
        }else if(this.settings.replace === false && q !==null){
            this.modal = new bootstrap.Modal(q)
            return;
        }
        let modalNode = Zap.createElement(this.render());
        document.body.appendChild(modalNode);
        this.modal = new bootstrap.Modal(document.getElementById(this.settings.id))
        for (const eventKey in this.settings.events) {
            modalNode.addEventListener(eventKey,this.settings.events[eventKey]);
        }
        if(this.settings.url !== null){
            $('#'+this.settings.id+' .modal-body').load(this.settings.url,this.settings.callback);
        }
    }
    this.setContent = function (content){
        document.querySelector('#'+this.settings.id+' .modal-body').innerHTML = content;
    }

    this.dispose = function(){
      this.modal.dispose();
        document.querySelector('#'+this.settings.id).remove();
    }

    this.hide = function(){
        this.modal.hide();
    }
    this.show = function(){

        if(this.modal === undefined){
            this.create();
        }
        this.modal.show();
    }
}



Zap.createModal = function(settings){
    return new ZapDialog(settings)
}

Zap.reload=function(settings){
    const defaultSettings = {id:null,url:null,data:null,callback:function(){}}
    if(settings === undefined){
        settings = {}
    }
    settings = Object.assign(defaultSettings,settings);
    if(settings.id === null){
        // 优先只替换内容容器本身（保留外层 container 的左右内边距），最后才退回 .app-content
        if(document.getElementById('zContent')){
            settings.id = '#zContent';
        }else if(document.querySelector('.app-content > .container-fluid')){
            settings.id = '.app-content > .container-fluid';
        }else{
            settings.id = '.app-content';
        }
    }
    if(settings.url === null){
        settings.url = location.href;
    }
    const $target = $(settings.id);
    if(!$target.length){
        location.reload();
        return;
    }
    // AJAX 请求下视图会跳过 layout（!IS_AJAX && layout()），服务端只返回内容片段
    $target.load(settings.url,settings.data,settings.callback);
}

Zap.createElement = function(str){
    let child = document.createElement('div');
    child.innerHTML = str;
    return child.firstChild;
}

Zap.RandString = function(len){
    const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    const charLength = chars.length;
    let result = '';
    for (let i = 0; i < len; i++ ) {
        result += chars.charAt(Math.floor(Math.random() * charLength));
    }
    return result;
}

Zap.RandID = function(){
    const chars = 'abcdefghijklmnopqrstuvwxyz';
    const charLength = chars.length;
    let result = 'ZAP';
    for (let i = 0; i < 5; i++ ) {
        result += chars.charAt(Math.floor(Math.random() * charLength));
    }
    return result + ((new Date()).getTime());
}

Zap.CheckBox_CheckAll = function(el,id){
    $(id).prop('checked', $(el).prop('checked'));
}

function ZapFaIcons(target,callback){
    const m = ZapModal.create({
        id:'faIcons',
        title: 'FA ICONS',
        content:ZapModal.loadding(),
        backdrop:false,
        dialog_class:'modal-dialog-scrollable modal-dialog-centered modal-xl',
        url: ZAP_BASE_URL + '/finder/faicons',
        callback:function(){
            const $callback = callback;
            const $targetArray = typeof target === 'string' ? [target] : target;
            const $body = $('#faIcons .modal-body');
            const $grid = $body.find('.row');
            const $buttons = $grid.find('button');
            const $icons = $buttons.find('i');

            // 为每个图标补充类名/名称/提示信息
            $icons.each(function(){
                const cls = this.className;
                // 取最后一个 fa-xxx 作为图标名（跳过 fa-solid/fa-regular/fa-brands 前缀）
                const name = (cls.match(/fa-[\w-]+/g) || []).pop() || '';
                this.setAttribute('data-icon-name', name);
                this.setAttribute('data-icon-class', cls);
                this.setAttribute('title', cls);
            });

            const total = $icons.length;

            // 顶部工具栏：搜索 + 分类 + 实时预览
            $body.prepend(`
                <div class="fa-icons-toolbar">
                    <div class="input-group input-group-sm mb-2">
                        <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input type="text" id="faIconsSearch" class="form-control"
                               placeholder="搜索图标名称，如 user / home / settings / arrow ..."/>
                        <span class="input-group-text fa-icons-count">${total} 个图标</span>
                    </div>
                    <div class="d-flex gap-1 flex-wrap align-items-center">
                        <div class="btn-group btn-group-sm" role="group" id="faIconsFilter">
                            <button type="button" class="btn btn-outline-primary active" data-type="all">全部</button>
                            <button type="button" class="btn btn-outline-primary" data-type="fa-solid">Solid</button>
                            <button type="button" class="btn btn-outline-primary" data-type="fa-regular">Regular</button>
                            <button type="button" class="btn btn-outline-primary" data-type="fa-brands">Brands</button>
                        </div>
                        <div class="fa-icons-preview ms-auto d-flex align-items-center gap-2 border rounded px-2 py-1">
                            <i class="fa-solid fa-circle-notch"></i>
                            <code class="small text-muted">悬停预览图标类名</code>
                        </div>
                    </div>
                </div>
            `);

            const $search = $('#faIconsSearch');
            const $count = $body.find('.fa-icons-count');
            const $previewIcon = $body.find('.fa-icons-preview i');
            const $previewCode = $body.find('.fa-icons-preview code');

            // 打开时若有已选图标，预览区默认展示当前值
            let currentIcon = '';
            for(const $target of $targetArray){
                if($($target).is('input')){
                    currentIcon = $($target).val();
                }else if($($target).is('i')){
                    currentIcon = $($target).attr('class');
                }
                if(currentIcon && currentIcon.indexOf('fa-') !== -1){ break; }
            }
            if(currentIcon){
                $previewIcon.attr('class', currentIcon);
                $previewCode.text(currentIcon);
            }

            // 过滤：关键字 + 分类
            function applyFilter(){
                const kw = $search.val().trim().toLowerCase();
                const type = $('#faIconsFilter .active').data('type');
                let visible = 0;
                $icons.each(function(){
                    const name = this.getAttribute('data-icon-name');
                    const cls = this.getAttribute('data-icon-class');
                    let show = true;
                    if(type !== 'all' && cls.indexOf(type) !== 0){ show = false; }
                    if(show && kw && name.indexOf(kw) === -1){ show = false; }
                    $(this).closest('.col-1').toggle(show);
                    if(show){ visible++; }
                });
                $count.text(visible + ' / ' + total + ' 个图标');
                if($body.find('.col-1:visible').length === 0){
                    if($('#faIconsEmpty').length === 0){
                        $grid.after('<div id="faIconsEmpty" class="fa-icons-empty text-center"><i class="fa-solid fa-magnifying-glass mb-2 d-block fa-2x"></i>没有找到匹配的图标，换个关键词试试</div>');
                    }
                }else{
                    $('#faIconsEmpty').remove();
                }
            }

            // 搜索输入
            $search.on('input', applyFilter);
            // 分类切换
            $('#faIconsFilter button').on('click', function(){
                $('#faIconsFilter .active').removeClass('active');
                $(this).addClass('active');
                applyFilter();
            });

            // 悬停实时预览
            $buttons.on('mouseenter', function(){
                const i = $(this).find('i')[0];
                if(!i) return;
                $previewIcon.attr('class', i.className);
                $previewCode.text(i.className);
            });

            // 点击选中图标（仅网格内的按钮，避免误伤工具栏的筛选按钮）
            $body.off('click.faIcons').on('click.faIcons','.row button',function(){
                const i = $(this).find('i')[0];
                let className = i ? i.className : 'fa fa-square-poll-horizontal';

                if(typeof $callback === 'function'){
                    $callback($targetArray,className)
                }
                $targetArray.forEach(($target)=>{
                    if($($target).is('input')){
                        $($target).val(className);
                    }else if($($target).is('i')){
                        $($target).prop('class',className);
                    }
                });

                m.hide();
            });

            // 打开后自动聚焦搜索框
            setTimeout(()=>{ $search.trigger('focus'); }, 120);
        }
    },true)
    m.show();

}



/* ============================================================
 * 主题外观：Light / Dark / Auto
 * ------------------------------------------------------------
 * - 存储：localStorage['zap-theme'] = light | dark | auto
 * - 生效：<html data-bs-theme="light|dark">（驱动 Bootstrap 5.3 / ZapUI 变量）
 * - auto：跟随系统 prefers-color-scheme，系统切换时实时同步
 * ============================================================ */
(function () {
    const STORAGE_KEY = 'zap-theme';
    const MODES = ['light', 'dark', 'auto'];
    const ICONS = { light: 'fa-sun', dark: 'fa-moon', auto: 'fa-circle-half-stroke' };
    const media = window.matchMedia('(prefers-color-scheme: dark)');

    function readMode() {
        let mode = 'light';
        try {
            mode = localStorage.getItem(STORAGE_KEY) || 'light';
        } catch (e) {
            // 隐私模式等场景下 localStorage 不可用，退回浅色
        }
        return MODES.indexOf(mode) === -1 ? 'light' : mode;
    }

    function resolve(mode) {
        return mode === 'auto' ? (media.matches ? 'dark' : 'light') : mode;
    }

    function paint(mode) {
        const theme = resolve(mode);
        const root = document.documentElement;

        root.setAttribute('data-bs-theme', theme);
        root.setAttribute('data-zap-theme', mode);

        // 顶栏按钮图标跟随所选模式（auto 显示半圆）
        document.querySelectorAll('[data-zap-theme-icon]').forEach(function (el) {
            el.className = 'fa ' + ICONS[mode] + ' zap-theme-icon';
        });

        // 下拉项选中态
        document.querySelectorAll('.zap-theme-option').forEach(function (el) {
            const active = el.getAttribute('data-theme') === mode;
            el.classList.toggle('active', active);
            el.setAttribute('aria-checked', active ? 'true' : 'false');
            const check = el.querySelector('.zap-theme-check');
            if (check) {
                check.classList.toggle('invisible', !active);
            }
        });

        // 通知页面内的组件（如代码编辑器）同步明暗
        window.dispatchEvent(new CustomEvent('zap:theme', { detail: { mode: mode, theme: theme } }));
    }

    function setMode(mode) {
        if (MODES.indexOf(mode) === -1) {
            mode = 'light';
        }
        try {
            localStorage.setItem(STORAGE_KEY, mode);
        } catch (e) {
            // 忽略写入失败，仅当次生效
        }
        paint(mode);
    }

    // 下拉项点击
    document.addEventListener('click', function (e) {
        const el = e.target instanceof Element ? e.target.closest('.zap-theme-option') : null;
        if (!el) {
            return;
        }
        e.preventDefault();
        setMode(el.getAttribute('data-theme'));
    });

    // 系统主题变化：只在 auto 模式跟随
    const onSystemChange = function () {
        if (readMode() === 'auto') {
            paint('auto');
        }
    };
    if (typeof media.addEventListener === 'function') {
        media.addEventListener('change', onSystemChange);
    } else if (typeof media.addListener === 'function') {
        media.addListener(onSystemChange);
    }

    // 脚本在 <head> 中执行，DOM 就绪后再同步控件状态
    $(function () {
        paint(readMode());
    });

    window.ZapTheme = { get: readMode, set: setMode, apply: paint };
})();



/* ============================================================
 * ZUI components (merged from zap-admin.js)
 * ============================================================ */

(function (global, factory) {
    typeof exports === 'object' && typeof module !== 'undefined' ? factory(exports) :
    typeof define === 'function' && define.amd ? define(['exports'], factory) :
    (global = typeof globalThis !== 'undefined' ? globalThis : global || self, factory(global.zapAdmin = {}));
})(this, (function (exports) { 'use strict';

    const domContentLoadedCallbacks = [];
    const onDOMContentLoaded = (callback) => {
        if (document.readyState === 'loading') {
            if (!domContentLoadedCallbacks.length) {
                document.addEventListener('DOMContentLoaded', () => {
                    for (const callback of domContentLoadedCallbacks) {
                        callback();
                    }
                });
            }
            domContentLoadedCallbacks.push(callback);
        }
        else {
            callback();
        }
    };
    const slideUp = (target, duration = 500) => {
        if (duration <= 1) {
            target.style.display = 'none';
            return;
        }
        target.style.transitionProperty = 'height, margin, padding';
        target.style.transitionDuration = `${duration}ms`;
        target.style.boxSizing = 'border-box';
        target.style.height = `${target.offsetHeight}px`;
        target.style.overflow = 'hidden';
        globalThis.setTimeout(() => {
            target.style.height = '0';
            target.style.paddingTop = '0';
            target.style.paddingBottom = '0';
            target.style.marginTop = '0';
            target.style.marginBottom = '0';
        }, 1);
        globalThis.setTimeout(() => {
            target.style.display = 'none';
            target.style.removeProperty('height');
            target.style.removeProperty('padding-top');
            target.style.removeProperty('padding-bottom');
            target.style.removeProperty('margin-top');
            target.style.removeProperty('margin-bottom');
            target.style.removeProperty('overflow');
            target.style.removeProperty('transition-duration');
            target.style.removeProperty('transition-property');
        }, duration);
    };
    const slideDown = (target, duration = 500) => {
        target.style.removeProperty('display');
        let { display } = globalThis.getComputedStyle(target);
        if (display === 'none') {
            display = 'block';
        }
        target.style.display = display;
        if (duration <= 1) {
            return;
        }
        const height = target.offsetHeight;
        target.style.overflow = 'hidden';
        target.style.height = '0';
        target.style.paddingTop = '0';
        target.style.paddingBottom = '0';
        target.style.marginTop = '0';
        target.style.marginBottom = '0';
        globalThis.setTimeout(() => {
            target.style.boxSizing = 'border-box';
            target.style.transitionProperty = 'height, margin, padding';
            target.style.transitionDuration = `${duration}ms`;
            target.style.height = `${height}px`;
            target.style.removeProperty('padding-top');
            target.style.removeProperty('padding-bottom');
            target.style.removeProperty('margin-top');
            target.style.removeProperty('margin-bottom');
        }, 1);
        globalThis.setTimeout(() => {
            target.style.removeProperty('height');
            target.style.removeProperty('overflow');
            target.style.removeProperty('transition-duration');
            target.style.removeProperty('transition-property');
        }, duration);
    };

    const CLASS_NAME_HOLD_TRANSITIONS = 'hold-transition';
    const CLASS_NAME_APP_LOADED = 'app-loaded';
    class Layout {
        _element;
        _holdTransitionTimer;
        constructor(element) {
            this._element = element;
            this._holdTransitionTimer = undefined;
        }
        holdTransition(time = 100) {
            if (this._holdTransitionTimer) {
                clearTimeout(this._holdTransitionTimer);
            }
            document.body.classList.add(CLASS_NAME_HOLD_TRANSITIONS);
            this._holdTransitionTimer = setTimeout(() => {
                document.body.classList.remove(CLASS_NAME_HOLD_TRANSITIONS);
            }, time);
        }
    }
    onDOMContentLoaded(() => {
        const layout = new Layout(document.body);
        window.addEventListener('resize', () => layout.holdTransition(200));
        setTimeout(() => {
            document.body.classList.add(CLASS_NAME_APP_LOADED);
        }, 400);
    });

    const DATA_KEY$4 = 'zui.card-widget';
    const EVENT_KEY$4 = `.${DATA_KEY$4}`;
    const EVENT_COLLAPSED$2 = `collapsed${EVENT_KEY$4}`;
    const EVENT_EXPANDED$2 = `expanded${EVENT_KEY$4}`;
    const EVENT_REMOVE = `remove${EVENT_KEY$4}`;
    const EVENT_MAXIMIZED$1 = `maximized${EVENT_KEY$4}`;
    const EVENT_MINIMIZED$1 = `minimized${EVENT_KEY$4}`;
    const CLASS_NAME_CARD = 'card';
    const CLASS_NAME_COLLAPSED = 'collapsed-card';
    const CLASS_NAME_COLLAPSING = 'collapsing-card';
    const CLASS_NAME_EXPANDING = 'expanding-card';
    const CLASS_NAME_WAS_COLLAPSED = 'was-collapsed';
    const CLASS_NAME_MAXIMIZED = 'maximized-card';
    const SELECTOR_DATA_REMOVE = '[data-zui-toggle="card-remove"]';
    const SELECTOR_DATA_COLLAPSE = '[data-zui-toggle="card-collapse"]';
    const SELECTOR_DATA_MAXIMIZE = '[data-zui-toggle="card-maximize"]';
    const SELECTOR_CARD = `.${CLASS_NAME_CARD}`;
    const SELECTOR_CARD_BODY = '.card-body';
    const SELECTOR_CARD_FOOTER = '.card-footer';
    const Default$1 = {
        animationSpeed: 500,
        collapseTrigger: SELECTOR_DATA_COLLAPSE,
        removeTrigger: SELECTOR_DATA_REMOVE,
        maximizeTrigger: SELECTOR_DATA_MAXIMIZE
    };
    class CardWidget {
        _element;
        _parent;
        _clone;
        _config;
        constructor(element, config) {
            this._element = element;
            this._parent = element.closest(SELECTOR_CARD);
            if (element.classList.contains(CLASS_NAME_CARD)) {
                this._parent = element;
            }
            this._config = { ...Default$1, ...config };
        }
        collapse() {
            const event = new Event(EVENT_COLLAPSED$2);
            if (this._parent) {
                this._parent.classList.add(CLASS_NAME_COLLAPSING);
                const elm = this._parent?.querySelectorAll(`:scope > ${SELECTOR_CARD_BODY}, :scope > ${SELECTOR_CARD_FOOTER}`);
                elm.forEach(el => {
                    if (el instanceof HTMLElement) {
                        slideUp(el, this._config.animationSpeed);
                    }
                });
                setTimeout(() => {
                    if (this._parent) {
                        this._parent.classList.add(CLASS_NAME_COLLAPSED);
                        this._parent.classList.remove(CLASS_NAME_COLLAPSING);
                    }
                }, this._config.animationSpeed);
            }
            this._element?.dispatchEvent(event);
        }
        expand() {
            const event = new Event(EVENT_EXPANDED$2);
            if (this._parent) {
                this._parent.classList.add(CLASS_NAME_EXPANDING);
                const elm = this._parent?.querySelectorAll(`:scope > ${SELECTOR_CARD_BODY}, :scope > ${SELECTOR_CARD_FOOTER}`);
                elm.forEach(el => {
                    if (el instanceof HTMLElement) {
                        slideDown(el, this._config.animationSpeed);
                    }
                });
                setTimeout(() => {
                    if (this._parent) {
                        this._parent.classList.remove(CLASS_NAME_COLLAPSED, CLASS_NAME_EXPANDING);
                    }
                }, this._config.animationSpeed);
            }
            this._element?.dispatchEvent(event);
        }
        remove() {
            const event = new Event(EVENT_REMOVE);
            if (this._parent) {
                slideUp(this._parent, this._config.animationSpeed);
            }
            this._element?.dispatchEvent(event);
        }
        toggle() {
            if (this._parent?.classList.contains(CLASS_NAME_COLLAPSED)) {
                this.expand();
                return;
            }
            this.collapse();
        }
        maximize() {
            const event = new Event(EVENT_MAXIMIZED$1);
            if (this._parent) {
                this._parent.style.height = `${this._parent.offsetHeight}px`;
                this._parent.style.width = `${this._parent.offsetWidth}px`;
                this._parent.style.transition = 'all .15s';
                setTimeout(() => {
                    const htmlTag = document.querySelector('html');
                    if (htmlTag) {
                        htmlTag.classList.add(CLASS_NAME_MAXIMIZED);
                    }
                    if (this._parent) {
                        this._parent.classList.add(CLASS_NAME_MAXIMIZED);
                        if (this._parent.classList.contains(CLASS_NAME_COLLAPSED)) {
                            this._parent.classList.add(CLASS_NAME_WAS_COLLAPSED);
                        }
                    }
                }, 150);
            }
            this._element?.dispatchEvent(event);
        }
        minimize() {
            const event = new Event(EVENT_MINIMIZED$1);
            if (this._parent) {
                this._parent.style.height = 'auto';
                this._parent.style.width = 'auto';
                this._parent.style.transition = 'all .15s';
                setTimeout(() => {
                    const htmlTag = document.querySelector('html');
                    if (htmlTag) {
                        htmlTag.classList.remove(CLASS_NAME_MAXIMIZED);
                    }
                    if (this._parent) {
                        this._parent.classList.remove(CLASS_NAME_MAXIMIZED);
                        if (this._parent?.classList.contains(CLASS_NAME_WAS_COLLAPSED)) {
                            this._parent.classList.remove(CLASS_NAME_WAS_COLLAPSED);
                        }
                    }
                }, 10);
            }
            this._element?.dispatchEvent(event);
        }
        toggleMaximize() {
            if (this._parent?.classList.contains(CLASS_NAME_MAXIMIZED)) {
                this.minimize();
                return;
            }
            this.maximize();
        }
    }

    const DATA_KEY$3 = 'zui.treeview';
    const EVENT_KEY$3 = `.${DATA_KEY$3}`;
    const EVENT_EXPANDED$1 = `expanded${EVENT_KEY$3}`;
    const EVENT_COLLAPSED$1 = `collapsed${EVENT_KEY$3}`;
    const EVENT_LOAD_DATA_API = `load${EVENT_KEY$3}`;
    const CLASS_NAME_MENU_OPEN = 'menu-open';
    const SELECTOR_NAV_ITEM = '.nav-item';
    const SELECTOR_NAV_LINK = '.nav-link';
    const SELECTOR_TREEVIEW_MENU = '.nav-treeview';
    const SELECTOR_DATA_TOGGLE$1 = '[data-zui-toggle="treeview"]';
    const Default = {
        animationSpeed: 300,
        accordion: true
    };
    class Treeview {
        _element;
        _config;
        constructor(element, config) {
            this._element = element;
            this._config = { ...Default, ...config };
        }
        open() {
            const event = new Event(EVENT_EXPANDED$1);
            if (this._config.accordion) {
                const openMenuList = this._element.parentElement?.querySelectorAll(`${SELECTOR_NAV_ITEM}.${CLASS_NAME_MENU_OPEN}`);
                openMenuList?.forEach(openMenu => {
                    if (openMenu !== this._element.parentElement) {
                        openMenu.classList.remove(CLASS_NAME_MENU_OPEN);
                        const childElement = openMenu?.querySelector(SELECTOR_TREEVIEW_MENU);
                        if (childElement) {
                            slideUp(childElement, this._config.animationSpeed);
                        }
                    }
                });
            }
            this._element.classList.add(CLASS_NAME_MENU_OPEN);
            const childElement = this._element?.querySelector(SELECTOR_TREEVIEW_MENU);
            if (childElement) {
                slideDown(childElement, this._config.animationSpeed);
            }
            this._element.dispatchEvent(event);
        }
        close() {
            const event = new Event(EVENT_COLLAPSED$1);
            this._element.classList.remove(CLASS_NAME_MENU_OPEN);
            const childElement = this._element?.querySelector(SELECTOR_TREEVIEW_MENU);
            if (childElement) {
                slideUp(childElement, this._config.animationSpeed);
            }
            this._element.dispatchEvent(event);
        }
        toggle() {
            if (this._element.classList.contains(CLASS_NAME_MENU_OPEN)) {
                this.close();
            }
            else {
                this.open();
            }
        }
    }
    onDOMContentLoaded(() => {
        // 初始展开并发事件（无动画）
        document.querySelectorAll(`${SELECTOR_NAV_ITEM}.${CLASS_NAME_MENU_OPEN}`).forEach(menuItem => {
            const childElement = menuItem.querySelector(SELECTOR_TREEVIEW_MENU);
            if (childElement) {
                slideDown(childElement, 0);
                menuItem.dispatchEvent(new Event(EVENT_LOAD_DATA_API));
            }
        });
    });

    const DATA_KEY$2 = 'zui.direct-chat';
    const EVENT_KEY$2 = `.${DATA_KEY$2}`;
    const EVENT_EXPANDED = `expanded${EVENT_KEY$2}`;
    const EVENT_COLLAPSED = `collapsed${EVENT_KEY$2}`;
    const SELECTOR_DATA_TOGGLE = '[data-zui-toggle="chat-pane"]';
    const SELECTOR_DIRECT_CHAT = '.direct-chat';
    const CLASS_NAME_DIRECT_CHAT_OPEN = 'direct-chat-contacts-open';
    class DirectChat {
        _element;
        constructor(element) {
            this._element = element;
        }
        toggle() {
            if (this._element.classList.contains(CLASS_NAME_DIRECT_CHAT_OPEN)) {
                const event = new Event(EVENT_COLLAPSED);
                this._element.classList.remove(CLASS_NAME_DIRECT_CHAT_OPEN);
                this._element.dispatchEvent(event);
            }
            else {
                const event = new Event(EVENT_EXPANDED);
                this._element.classList.add(CLASS_NAME_DIRECT_CHAT_OPEN);
                this._element.dispatchEvent(event);
            }
        }
    }

    const DATA_KEY$1 = 'zui.fullscreen';
    const EVENT_KEY$1 = `.${DATA_KEY$1}`;
    const EVENT_MAXIMIZED = `maximized${EVENT_KEY$1}`;
    const EVENT_MINIMIZED = `minimized${EVENT_KEY$1}`;
    const SELECTOR_FULLSCREEN_TOGGLE = '[data-zui-toggle="fullscreen"]';
    const SELECTOR_MAXIMIZE_ICON = '[data-zui-icon="maximize"]';
    const SELECTOR_MINIMIZE_ICON = '[data-zui-icon="minimize"]';
    class FullScreen {
        _element;
        _config;
        constructor(element, config) {
            this._element = element;
            this._config = config;
        }
        inFullScreen() {
            const event = new Event(EVENT_MAXIMIZED);
            const iconMaximize = document.querySelector(SELECTOR_MAXIMIZE_ICON);
            const iconMinimize = document.querySelector(SELECTOR_MINIMIZE_ICON);
            void document.documentElement.requestFullscreen();
            if (iconMaximize) {
                iconMaximize.classList.add('d-none');
            }
            if (iconMinimize) {
                iconMinimize.classList.remove('d-none');
            }
            this._element.dispatchEvent(event);
        }
        outFullscreen() {
            const event = new Event(EVENT_MINIMIZED);
            const iconMaximize = document.querySelector(SELECTOR_MAXIMIZE_ICON);
            const iconMinimize = document.querySelector(SELECTOR_MINIMIZE_ICON);
            void document.exitFullscreen();
            if (iconMaximize) {
                iconMaximize.classList.remove('d-none');
            }
            if (iconMinimize) {
                iconMinimize.classList.add('d-none');
            }
            this._element.dispatchEvent(event);
        }
        toggleFullScreen() {
            if (document.fullscreenEnabled) {
                if (document.fullscreenElement) {
                    this.outFullscreen();
                }
                else {
                    this.inFullScreen();
                }
            }
        }
    }
    onDOMContentLoaded(() => {
        // 统一事件委托：根据 data-zui-toggle 分发到对应组件（兼容动态插入的节点）
        document.addEventListener('click', (event) => {
            const t = event.target;
            if (!(t instanceof Element)) {
                return;
            }
            const el = t.closest('[data-zui-toggle]');
            if (!el) {
                return;
            }
            switch (el.getAttribute('data-zui-toggle')) {
                case 'card-collapse':
                    event.preventDefault();
                    new CardWidget(el, Default$1).toggle();
                    break;
                case 'card-remove':
                    event.preventDefault();
                    new CardWidget(el, Default$1).remove();
                    break;
                case 'card-maximize':
                    event.preventDefault();
                    new CardWidget(el, Default$1).toggleMaximize();
                    break;
                case 'treeview': {
                    const item = el.closest(SELECTOR_NAV_ITEM);
                    if (!item || !item.querySelector(SELECTOR_TREEVIEW_MENU)) {
                        return;
                    }
                    if (el.getAttribute('href') === '#') {
                        event.preventDefault();
                    }
                    const config = {
                        accordion: el.dataset.accordion === undefined ? Default.accordion : el.dataset.accordion === 'true',
                        animationSpeed: el.dataset.animationSpeed === undefined ? Default.animationSpeed : Number(el.dataset.animationSpeed)
                    };
                    new Treeview(item, config).toggle();
                    break;
                }
                case 'chat-pane': {
                    const chatPane = el.closest(SELECTOR_DIRECT_CHAT);
                    if (chatPane) {
                        event.preventDefault();
                        new DirectChat(chatPane).toggle();
                    }
                    break;
                }
                case 'fullscreen':
                    event.preventDefault();
                    new FullScreen(el, undefined).toggleFullScreen();
                    break;
            }
        }, false);
    });

    const DATA_KEY = 'zui.push-menu';
    const EVENT_KEY = `.${DATA_KEY}`;
    const EVENT_OPEN = `open${EVENT_KEY}`;
    const EVENT_COLLAPSE = `collapse${EVENT_KEY}`;
    const CLASS_NAME_SIDEBAR_MINI = 'sidebar-mini';
    const CLASS_NAME_SIDEBAR_EXPAND = 'sidebar-expand';
    const CLASS_NAME_SIDEBAR_OVERLAY = 'sidebar-overlay';
    const CLASS_NAME_SIDEBAR_COLLAPSE = 'sidebar-collapse';
    const CLASS_NAME_SIDEBAR_OPEN = 'sidebar-open';
    const SELECTOR_APP_SIDEBAR = '.app-sidebar';
    const SELECTOR_APP_WRAPPER = '.app-wrapper';
    const SELECTOR_SIDEBAR_EXPAND = `[class*="${CLASS_NAME_SIDEBAR_EXPAND}"]`;
    const SELECTOR_SIDEBAR_TOGGLE = '[data-zui-toggle="sidebar"]';
    const STORAGE_KEY_SIDEBAR_STATE = 'zui.sidebar.state';
    const Defaults = {
        sidebarBreakpoint: 992,
        enablePersistence: false
    };
    class PushMenu {
        _element;
        _config;
        constructor(element, config) {
            this._element = element;
            this._config = { ...Defaults, ...config };
        }
        isCollapsed() {
            return document.body.classList.contains(CLASS_NAME_SIDEBAR_COLLAPSE);
        }
        isExplicitlyOpen() {
            return document.body.classList.contains(CLASS_NAME_SIDEBAR_OPEN);
        }
        isMiniMode() {
            return document.body.classList.contains(CLASS_NAME_SIDEBAR_MINI);
        }
        isMobileSize() {
            return globalThis.innerWidth <= this._config.sidebarBreakpoint;
        }
        expand() {
            document.body.classList.remove(CLASS_NAME_SIDEBAR_COLLAPSE);
            if (this.isMobileSize()) {
                document.body.classList.add(CLASS_NAME_SIDEBAR_OPEN);
            }
            this._element.dispatchEvent(new Event(EVENT_OPEN));
        }
        collapse() {
            document.body.classList.remove(CLASS_NAME_SIDEBAR_OPEN);
            document.body.classList.add(CLASS_NAME_SIDEBAR_COLLAPSE);
            this._element.dispatchEvent(new Event(EVENT_COLLAPSE));
        }
        toggle() {
            const isCollapsed = this.isCollapsed();
            if (isCollapsed) {
                this.expand();
            }
            else {
                this.collapse();
            }
            if (this._config.enablePersistence) {
                this.saveSidebarState(isCollapsed ? CLASS_NAME_SIDEBAR_OPEN : CLASS_NAME_SIDEBAR_COLLAPSE);
            }
        }
        setupSidebarBreakPoint() {
            const sidebarExpand = document.querySelector(SELECTOR_SIDEBAR_EXPAND);
            if (!sidebarExpand) {
                return;
            }
            const content = globalThis.getComputedStyle(sidebarExpand, '::before')
                .getPropertyValue('content');
            if (!content || content === 'none') {
                return;
            }
            const breakpointValue = Number(content.replace(/[^\d.-]/g, ''));
            if (Number.isNaN(breakpointValue)) {
                return;
            }
            this._config = { ...this._config, sidebarBreakpoint: breakpointValue };
        }
        updateStateByResponsiveLogic() {
            if (this.isMobileSize()) {
                if (!this.isExplicitlyOpen()) {
                    this.collapse();
                }
            }
            else {
                if (!(this.isMiniMode() && this.isCollapsed())) {
                    this.expand();
                }
            }
        }
        saveSidebarState(state) {
            if (globalThis.localStorage === undefined) {
                return;
            }
            try {
                localStorage.setItem(STORAGE_KEY_SIDEBAR_STATE, state);
            }
            catch {
            }
        }
        loadSidebarState() {
            if (globalThis.localStorage === undefined) {
                return;
            }
            try {
                const storedState = localStorage.getItem(STORAGE_KEY_SIDEBAR_STATE);
                if (storedState === CLASS_NAME_SIDEBAR_COLLAPSE) {
                    this.collapse();
                }
                else if (storedState === CLASS_NAME_SIDEBAR_OPEN) {
                    this.expand();
                }
                else {
                    this.updateStateByResponsiveLogic();
                }
            }
            catch {
                this.updateStateByResponsiveLogic();
            }
        }
        clearSidebarState() {
            if (globalThis.localStorage === undefined) {
                return;
            }
            try {
                localStorage.removeItem(STORAGE_KEY_SIDEBAR_STATE);
            }
            catch {
            }
        }
        init() {
            this.setupSidebarBreakPoint();
            if (!this._config.enablePersistence) {
                this.clearSidebarState();
            }
            if (this._config.enablePersistence && !this.isMobileSize()) {
                this.loadSidebarState();
            }
            else {
                this.updateStateByResponsiveLogic();
            }
        }
    }
    onDOMContentLoaded(() => {
        const sidebar = document?.querySelector(SELECTOR_APP_SIDEBAR);
        if (!sidebar) {
            return;
        }
        const sidebarBreakpointAttr = sidebar.dataset.sidebarBreakpoint;
        const enablePersistenceAttr = sidebar.dataset.enablePersistence;
        const config = {
            sidebarBreakpoint: sidebarBreakpointAttr === undefined ?
                Defaults.sidebarBreakpoint :
                Number(sidebarBreakpointAttr),
            enablePersistence: enablePersistenceAttr === undefined ?
                Defaults.enablePersistence :
                enablePersistenceAttr === 'true'
        };
        const pushMenu = new PushMenu(sidebar, config);
        pushMenu.init();
        window.addEventListener('resize', () => {
            pushMenu.setupSidebarBreakPoint();
            pushMenu.updateStateByResponsiveLogic();
        });
        const sidebarOverlay = document.createElement('div');
        sidebarOverlay.className = CLASS_NAME_SIDEBAR_OVERLAY;
        document.querySelector(SELECTOR_APP_WRAPPER)?.append(sidebarOverlay);
        let overlayTouchMoved = false;
        sidebarOverlay.addEventListener('touchstart', () => {
            overlayTouchMoved = false;
        }, { passive: true });
        sidebarOverlay.addEventListener('touchmove', () => {
            overlayTouchMoved = true;
        }, { passive: true });
        sidebarOverlay.addEventListener('touchend', event => {
            if (!overlayTouchMoved) {
                event.preventDefault();
                pushMenu.collapse();
            }
            overlayTouchMoved = false;
        }, { passive: false });
        sidebarOverlay.addEventListener('click', event => {
            event.preventDefault();
            pushMenu.collapse();
        });
        const fullBtn = document.querySelectorAll(SELECTOR_SIDEBAR_TOGGLE);
        fullBtn.forEach(btn => {
            btn.addEventListener('click', event => {
                event.preventDefault();
                let button = event.currentTarget;
                if (button?.dataset.zuiToggle !== 'sidebar') {
                    button = button?.closest(SELECTOR_SIDEBAR_TOGGLE);
                }
                if (button) {
                    event?.preventDefault();
                    pushMenu.toggle();
                }
            });
        });
    });

    class AccessibilityManager {
        config;
        liveRegion = null;
        focusHistory = [];
        constructor(config = {}) {
            this.config = {
                announcements: true,
                skipLinks: true,
                focusManagement: true,
                keyboardNavigation: true,
                reducedMotion: true,
                ...config
            };
            this.init();
        }
        init() {
            if (this.config.announcements) {
                this.createLiveRegion();
            }
            if (this.config.skipLinks) {
                this.addSkipLinks();
            }
            if (this.config.focusManagement) {
                this.initFocusManagement();
            }
            if (this.config.keyboardNavigation) {
                this.initKeyboardNavigation();
            }
            if (this.config.reducedMotion) {
                this.respectReducedMotion();
            }
            this.initErrorAnnouncements();
            this.initTableAccessibility();
            this.initFormAccessibility();
        }
        createLiveRegion() {
            if (this.liveRegion)
                return;
            this.liveRegion = document.createElement('div');
            this.liveRegion.id = 'live-region';
            this.liveRegion.className = 'live-region';
            this.liveRegion.setAttribute('aria-live', 'polite');
            this.liveRegion.setAttribute('aria-atomic', 'true');
            this.liveRegion.setAttribute('role', 'status');
            document.body.append(this.liveRegion);
        }
        addSkipLinks() {
            const skipLinksContainer = document.createElement('div');
            skipLinksContainer.className = 'skip-links';
            const skipToMain = document.createElement('a');
            skipToMain.href = '#main';
            skipToMain.className = 'skip-link';
            skipToMain.textContent = 'Skip to main content';
            const skipToNav = document.createElement('a');
            skipToNav.href = '#navigation';
            skipToNav.className = 'skip-link';
            skipToNav.textContent = 'Skip to navigation';
            skipLinksContainer.append(skipToMain);
            skipLinksContainer.append(skipToNav);
            document.body.insertBefore(skipLinksContainer, document.body.firstChild);
            this.ensureSkipTargets();
        }
        ensureSkipTargets() {
            const main = document.querySelector('#main, main, [role="main"]');
            if (main && !main.id) {
                main.id = 'main';
            }
            if (main && !main.hasAttribute('tabindex')) {
                main.setAttribute('tabindex', '-1');
            }
            const nav = document.querySelector('#navigation, nav, [role="navigation"]');
            if (nav && !nav.id) {
                nav.id = 'navigation';
            }
            if (nav && !nav.hasAttribute('tabindex')) {
                nav.setAttribute('tabindex', '-1');
            }
        }
        initFocusManagement() {
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Tab') {
                    this.handleTabNavigation(event);
                }
                if (event.key === 'Escape') {
                    this.handleEscapeKey(event);
                }
            });
            this.initModalFocusManagement();
            this.initDropdownFocusManagement();
        }
        handleTabNavigation(event) {
            const focusableElements = this.getFocusableElements();
            const currentIndex = focusableElements.indexOf(document.activeElement);
            if (event.shiftKey) {
                if (currentIndex <= 0) {
                    event.preventDefault();
                    focusableElements.at(-1)?.focus();
                }
            }
            else if (currentIndex >= focusableElements.length - 1) {
                event.preventDefault();
                focusableElements[0]?.focus();
            }
        }
        getFocusableElements() {
            const selector = [
                'a[href]',
                'button:not([disabled])',
                'input:not([disabled])',
                'select:not([disabled])',
                'textarea:not([disabled])',
                '[tabindex]:not([tabindex="-1"])',
                '[contenteditable="true"]'
            ].join(', ');
            return Array.from(document.querySelectorAll(selector));
        }
        handleEscapeKey(event) {
            const activeModal = document.querySelector('.modal.show');
            if (activeModal) {
                return;
            }
            const activeDropdown = document.querySelector('.dropdown-menu.show');
            if (activeDropdown) {
                const toggleButton = document.querySelector('[data-bs-toggle="dropdown"][aria-expanded="true"]');
                toggleButton?.click();
                event.preventDefault();
            }
        }
        initKeyboardNavigation() {
            document.addEventListener('keydown', (event) => {
                const target = event.target;
                if (target.closest('.nav, .navbar-nav, .dropdown-menu')) {
                    this.handleMenuNavigation(event);
                }
                if ((event.key === 'Enter' || event.key === ' ') && target.hasAttribute('role') && target.getAttribute('role') === 'button' && !target.matches('button, input[type="button"], input[type="submit"]')) {
                    event.preventDefault();
                    target.click();
                }
            });
        }
        handleMenuNavigation(event) {
            if (!['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
                return;
            }
            const currentElement = event.target;
            const menuItems = Array.from(currentElement.closest('.nav, .navbar-nav, .dropdown-menu')?.querySelectorAll('a, button') || []);
            const currentIndex = menuItems.indexOf(currentElement);
            let nextIndex;
            switch (event.key) {
                case 'ArrowDown':
                case 'ArrowRight': {
                    nextIndex = currentIndex < menuItems.length - 1 ? currentIndex + 1 : 0;
                    break;
                }
                case 'ArrowUp':
                case 'ArrowLeft': {
                    nextIndex = currentIndex > 0 ? currentIndex - 1 : menuItems.length - 1;
                    break;
                }
                case 'Home': {
                    nextIndex = 0;
                    break;
                }
                case 'End': {
                    nextIndex = menuItems.length - 1;
                    break;
                }
                default: {
                    return;
                }
            }
            event.preventDefault();
            menuItems[nextIndex]?.focus();
        }
        respectReducedMotion() {
            const prefersReducedMotion = globalThis.matchMedia('(prefers-reduced-motion: reduce)').matches;
            if (prefersReducedMotion) {
                document.body.classList.add('reduce-motion');
                document.documentElement.style.scrollBehavior = 'auto';
                const style = document.createElement('style');
                style.textContent = `
        *, *::before, *::after {
          animation-duration: 0.01ms !important;
          animation-iteration-count: 1 !important;
          transition-duration: 0.01ms !important;
        }
      `;
                document.head.append(style);
            }
        }
        initErrorAnnouncements() {
            const observer = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    mutation.addedNodes.forEach((node) => {
                        if (node.nodeType === Node.ELEMENT_NODE) {
                            const element = node;
                            if (element.matches('.alert-danger, .invalid-feedback, .error')) {
                                this.announce(element.textContent || 'Error occurred', 'assertive');
                            }
                            if (element.matches('.alert-success, .success')) {
                                this.announce(element.textContent || 'Success', 'polite');
                            }
                        }
                    });
                });
            });
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }
        initTableAccessibility() {
            document.querySelectorAll('table').forEach((table) => {
                if (!table.hasAttribute('role')) {
                    table.setAttribute('role', 'table');
                }
                table.querySelectorAll('th').forEach((th) => {
                    if (!th.hasAttribute('scope')) {
                        const isInThead = th.closest('thead');
                        const isFirstColumn = th.cellIndex === 0;
                        if (isInThead) {
                            th.setAttribute('scope', 'col');
                        }
                        else if (isFirstColumn) {
                            th.setAttribute('scope', 'row');
                        }
                    }
                });
                if (!table.querySelector('caption') && table.hasAttribute('title')) {
                    const caption = document.createElement('caption');
                    caption.textContent = table.getAttribute('title') || '';
                    table.insertBefore(caption, table.firstChild);
                }
            });
        }
        initFormAccessibility() {
            document.querySelectorAll('input, select, textarea').forEach((input) => {
                const htmlInput = input;
                if (!htmlInput.labels?.length && !htmlInput.hasAttribute('aria-label') && !htmlInput.hasAttribute('aria-labelledby')) {
                    const placeholder = htmlInput.getAttribute('placeholder');
                    if (placeholder) {
                        htmlInput.setAttribute('aria-label', placeholder);
                    }
                }
                if (htmlInput.hasAttribute('required')) {
                    const label = htmlInput.labels?.[0];
                    if (label && !label.querySelector('.required-indicator')) {
                        const indicator = document.createElement('span');
                        indicator.className = 'required-indicator sr-only';
                        indicator.textContent = ' (required)';
                        label.append(indicator);
                    }
                }
                if (!htmlInput.classList.contains('disable-zapadmin-validations')) {
                    htmlInput.addEventListener('invalid', () => {
                        this.handleFormError(htmlInput);
                    });
                }
            });
        }
        handleFormError(input) {
            const errorId = `${input.id || input.name}-error`;
            let errorElement = document.getElementById(errorId);
            if (!errorElement) {
                errorElement = document.createElement('div');
                errorElement.id = errorId;
                errorElement.className = 'invalid-feedback';
                errorElement.setAttribute('role', 'alert');
                input.parentNode?.append(errorElement);
            }
            errorElement.textContent = input.validationMessage;
            input.setAttribute('aria-describedby', errorId);
            input.classList.add('is-invalid');
            this.announce(`Error in ${input.labels?.[0]?.textContent || input.name}: ${input.validationMessage}`, 'assertive');
        }
        initModalFocusManagement() {
            document.addEventListener('shown.bs.modal', (event) => {
                const modal = event.target;
                const focusableElements = modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
                if (focusableElements.length > 0) {
                    focusableElements[0].focus();
                }
                this.focusHistory.push(document.activeElement);
            });
            document.addEventListener('hidden.bs.modal', () => {
                const previousElement = this.focusHistory.pop();
                if (previousElement) {
                    previousElement.focus();
                }
            });
        }
        initDropdownFocusManagement() {
            document.addEventListener('shown.bs.dropdown', (event) => {
                const dropdown = event.target;
                const menu = dropdown.querySelector('.dropdown-menu');
                const firstItem = menu?.querySelector('a, button');
                if (firstItem) {
                    firstItem.focus();
                }
            });
        }
        announce(message, priority = 'polite') {
            if (!this.liveRegion) {
                this.createLiveRegion();
            }
            if (this.liveRegion) {
                this.liveRegion.setAttribute('aria-live', priority);
                this.liveRegion.textContent = message;
                setTimeout(() => {
                    if (this.liveRegion) {
                        this.liveRegion.textContent = '';
                    }
                }, 1000);
            }
        }
        focusElement(selector) {
            const element = document.querySelector(selector);
            if (element) {
                element.focus();
                element.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
        trapFocus(container) {
            const focusableElements = container.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
            const focusableArray = Array.from(focusableElements);
            const firstElement = focusableArray[0];
            const lastElement = focusableArray.at(-1);
            container.addEventListener('keydown', (event) => {
                if (event.key === 'Tab') {
                    if (event.shiftKey) {
                        if (document.activeElement === firstElement) {
                            lastElement?.focus();
                            event.preventDefault();
                        }
                    }
                    else if (document.activeElement === lastElement) {
                        firstElement.focus();
                        event.preventDefault();
                    }
                }
            });
        }
        addLandmarks() {
            const main = document.querySelector('main');
            if (!main) {
                const appMain = document.querySelector('.app-main');
                if (appMain) {
                    appMain.setAttribute('role', 'main');
                    appMain.id = 'main';
                }
            }
            document.querySelectorAll('.navbar-nav, .nav').forEach((nav, index) => {
                if (!nav.hasAttribute('role')) {
                    nav.setAttribute('role', 'navigation');
                }
                if (!nav.hasAttribute('aria-label')) {
                    nav.setAttribute('aria-label', `Navigation ${index + 1}`);
                }
            });
            const searchForm = document.querySelector('form[role="search"], .navbar-search');
            if (searchForm && !searchForm.hasAttribute('role')) {
                searchForm.setAttribute('role', 'search');
            }
        }
    }
    const initAccessibility = (config) => {
        return new AccessibilityManager(config);
    };

    onDOMContentLoaded(() => {
        const accessibilityManager = initAccessibility({
            announcements: true,
            skipLinks: true,
            focusManagement: true,
            keyboardNavigation: true,
            reducedMotion: true
        });
        accessibilityManager.addLandmarks();
    });

    exports.CardWidget = CardWidget;
    exports.DirectChat = DirectChat;
    exports.FullScreen = FullScreen;
    exports.Layout = Layout;
    exports.PushMenu = PushMenu;
    exports.Treeview = Treeview;
    exports.initAccessibility = initAccessibility;

}));

