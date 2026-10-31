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
    show:function(params){
        const defaultParams = {
            title:'',
            content:'',
            callback:function(){}
        }
        if(params === undefined){
            params = {}
        }
        params = Object.assign(defaultParams,params);


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
        let buttons = [];
        for (let btnIndex in this.settings.buttons){
            btn = this.settings.buttons[btnIndex];
            btn.class = btn.class || ' class="btn btn-success btn-sm" ';
            btn.title = btn.title || '默认按钮';
            btn.close = btn.close === undefined ? ' data-bs-dismiss="modal" ' :'';
            buttons.push(`<button type="button" data-index="${btnIndex}" class="${btn.class}" ${btn.close}>${btn.title}</button>`)
        }
        let buttonStr = buttons.join();
        return `<div class="modal-footer ${this.settings.footerClass}">${buttonStr}</div>`;
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

Zap.startLoading = function(){

}

Zap.reload=function(settings){
    const defaultSettings = {id:null,url:null,data:null,callback:function(){}}
    if(settings === undefined){
        settings = {}
    }
    settings = Object.assign(defaultSettings,settings);
    if(settings.id === null){
        // 当前后台布局没有 #zContent，退回内容容器 .app-content
        settings.id = document.getElementById('zContent') ? '#zContent' : '.app-content';
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



const loadScript = (fileUrl, async = true, type = "text/javascript") => {
    return new Promise((resolve, reject) => {
        try {
            const scriptEle = document.createElement("script");
            scriptEle.type = type;
            scriptEle.async = async;
            scriptEle.src =fileUrl;

            scriptEle.addEventListener("load", (ev) => {
                resolve({ status: true });
            });

            scriptEle.addEventListener("error", (ev) => {
                reject({
                    status: false,
                    message: `Failed to load the script ${fileUrl}`
                });
            });

            document.head.appendChild(scriptEle);
        } catch (error) {
            reject(error);
        }
    });
};

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

