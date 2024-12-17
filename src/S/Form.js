/*! capile/studio Form v1.0 | (c) 2022 Tecnodesign <ti@tecnodz.com> */
(function()
{

"use strict";

var S, _Tl,_L=[], _eids=0;

function checkLabel(e)
{
    /*jshint validthis: true */
    if(_Tl) clearTimeout(_Tl);
    if(arguments.length>0) {
        if(S.node(this)) {
            var nn=this.nodeName.toLowerCase(),E;
            if(nn=='input') {
                _L.push(this);
            } else {
                E=this.querySelector(S.Form.checkSelector);
                if(E) {
                    _L.push(E);
                }
                if(nn=='label') E=null;
            }
            if(E) {
                if(E.checked) {
                    E.checked = false;
                    E.removeAttribute('checked');
                } else {
                    E.checked = true;
                    E.setAttribute('checked', 'checked');
                }
                _L.push(E);
            } else {
                _L.push(this);
            }
        }
        _Tl=setTimeout(checkLabel, 50);
        return;
    }
    var L=('Studio_Form_CheckLabel' in S.modules) ?document.querySelectorAll(S.modules['Studio_Form_CheckLabel']) :[], i=L.length, P, cn;
    if(!i && _L.length>0) {
        L = _L;
        i=L.length;
        _L=[];
    }
    while(i--) {
        P=S.parentNode(L[i], 'label');
        if(!P) P=L[i].parentNode;
        cn=P.className;

        if(L[i].checked) {
            if(!L[i].getAttribute('checked')) L[i].setAttribute('checked','checked');
            if(cn.search(/\bon\b/)<0) cn += ' on';
            if(cn.search(/\boff\b/)>-1) cn = cn.replace(/\s*\boff\b/g, '');
            L[i].setAttribute('data-switch', 'on');
        } else {
            if(L[i].getAttribute('checked')) L[i].removeAttribute('checked');
            if(cn.search(/\boff\b/)<0) cn += ' off';
            if(cn.search(/\bon\b/)>-1) cn = cn.replace(/\s*\bon\b/g, '');
            L[i].setAttribute('data-switch', 'off');
        }
        cn=cn.trim();
        if(P.className!=cn) P.className=cn;
        P=null;
    }
}

function initCheckLabel(e)
{
    /*jshint validthis: true */
    if(!this.getAttribute('data-check-label')) {
        this.setAttribute('data-check-label',1);
        var l=S.parentNode(this, 'label');
        if(!l) l=this.parentNode;
        S.bind(l, 'click', checkLabel);
        checkLabel(true);
    }
}

function initAutoSubmit(e)
{
    /*jshint validthis: true */
    if(!this.getAttribute('data-auto-submit')) {
        this.setAttribute('data-auto-submit',1);
        var L=this.querySelectorAll(S.Form.inputSelector), i=L.length,found=false,t, nn, a;
        while(i--) {
            t=L[i].getAttribute('type');
            nn=L[i].nodeName.toLowerCase();
            if(t && (t==='submit' || t==='button')) continue;
            S.bind(L[i], (nn==='select' || t==='checkbox' || t==='radio') ?'input' :'change', autoSubmit);
            found=true;
        }
        if(!found) return;
        this.className+=' s-no-button';
    }
}

function autoSubmit(e)
{
    /*jshint validthis: true */
    S.stopEvent(e);
    if(!this.form || this.form.getAttribute('data-do-not-submit')) return false;
    else if(this.form.className.search(/\bs-form-reload\b/)>-1) formReload.call(this, e);
    else if(this.form) this.form.submit(e);
    else if(this.submit) this.submit(e);
    return false;
}


function formReload(e)
{
    /*jshint validthis: true */
    S.stopEvent(e);
    if(this.form && !this.form.getAttribute('data-do-not-submit')) {
        var data=S.formData(this.form, true);
        S.ajax(this.form.getAttribute('action'), data, formReloadData, S.error, 'html', this, {'x-studio-action':'Form.Validate'});
    }
    return false;
}

function formReloadData(d)
{
    var F=(this.form) ?this.form :this, R=document.createElement('div');
    R.innerHTML=d;
    R=R.querySelector('form');
    if(!F.parentNode || !R) return;
    var l=this.getAttribute('data-do-not-reload'), L=(l) ?l.split(/\s*\,\s*/g) :[], i=L.length, S, T;
    while(i--) {
        if((S=F.querySelector('#'+L[i])) && (T=R.querySelector('#'+L[i]))) {
            T.parentNode.replaceChild(S, T);
        }
    }
    F.parentNode.replaceChild(R, F);
    R.setAttribute('data-do-not-submit', '1');
    S.init(R.parentNode);
    R.removeAttribute('data-do-not-submit');
}

function initCleanup(o)
{
    /*jshint validthis: true */
    if(!o || !S.node(o)) o=this;
    S.bind(o, 'click', cleanup);
}

function cleanup(e)
{
    /*jshint validthis: true */
    if(this.className.search(/\bcleanup\b/)>-1) S.clearForm(this.form);
}

function clearForm(f)
{
    for (i = 0; i < f.elements.length; i++)
    {
        t = f.elements[i].type.toLowerCase();
        switch (t)
        {
        case "radio":
        case "checkbox":
            if (f.elements[i].checked)
            {
                f.elements[i].checked = false;
            }
            break;
        case "select-one":
        case "select-multi":
            f.elements[i].selectedIndex = -1;
            break;
        default:
            f.elements[i].value = "";
            break;
        }
    }
}

/**
 * Datalist options
 */
function initDatalist(o)
{
    /*jshint validthis: true */
    var t=S.node(this, o);
    if(!t || !('nodeName' in t) || t.getAttribute('data-datalist-t')) return false;
    t.setAttribute('data-datalist-t', 1);
    t.setAttribute('data-datalist-q', S.val(t));
    if(t.nodeName.toLowerCase()=='input') {
        t.setAttribute('autocomplete', 'off');
        //S.bind(t, 'keypress', tdz.delayedChange);
        S.bind(t, 'keydown', datalistKeypress);
        S.bind(t, 'focus', datalistQuery);
        S.bind(t, 'blur', datalistBlurTimeout);
    }
    S.bind(t, 'change', datalistQueryTimeout);
    t.parentNode.className += ' s-input-search';
    if(t.getAttribute('data-datalist-visible')) {
        datalistQuery.call(t);
    }
}

function datalistKeypress(e)
{
    /*jshint validthis: true */
    e = e || window.event;

    var m=0,t=false;

    var o=S.node(this);
    if(!o && e && ('target' in e)) o=S.node(e.target);

    if (e.keyCode == '38' || e.keyCode == '37') {
        // up arrow or left arrow
        m=-1;
    } else if (e.keyCode == '40' || e.keyCode == '39') {
        // down arrow or right arrow
        m=1;
    } else if(e.keyCode == '13' || e.keyCode=='9') {
        e.preventDefault();
        t = true;
    } else if(e.keyCode=='27') {
        // escape
        e.preventDefault();
        return datalistClear.apply(o);
    } else {
        return datalistQueryTimeout.call(o);
    }
    var c=o.parentNode.querySelector('ul.s-datalist'), s=(c)?(c.querySelector('.s-selected')):(null);
    if(!s) {
        if(!c || c.children.length==0) return;
        s = c.children[0];
        m=0;
    } else {
        s.className = s.className.replace(/\s*\bs-selected\b/, '');
    }
    if(m>0) {
        while(m-- > 0) {
            if(s.nextSibling) s=s.nextSibling;
            else break;
        }
    } else if(m<0) {
        while(m++ < 0) {
            if(s.previousSibling) s=s.previousSibling;
            else break;
        }
    }
    s.className += ' s-selected';
    if('scrollIntoView' in s) s.scrollIntoView({block:'nearest',behavior:'smooth'});
    if(t) {
        datalistOption.apply(s);
    }
}



var _dq=null;
function datalistQueryTimeout()
{
    /*jshint validthis: true */
    var o=this;
    if(_dq) clearTimeout(_dq);
    _dq = setTimeout(function(){ datalistQuery.apply(o); }, 500);
}

function datalistQuery(e)
{
    /*jshint validthis: true */
    var o=this, v=datalistVal(o), t=new Date().getTime(), focus=(e && ('type' in e) && e.type=='focus');
    if(_dq) clearTimeout(_dq);
    if(o.getAttribute('data-datalist-visible') && !o.parentNode.querySelector('.s-datalist-container')) {
    } else if(v==o.getAttribute('data-datalist-q') || o.getAttribute('data-datalist-t')>t) {
        if(!focus || o.getAttribute('data-datalist-visible')) {
            return;
        }
    }

    var x;
    if(o.getAttribute('id').search(/^q__/)>-1) {
        x=o.getAttribute('id').replace(/^q__/, '');
        var T=o.form.querySelector('#'+x);
        if(!focus && T && S.val(T)!='') {
            S.val(T, '');
            S.fire(T, 'change');
        }
    } else {
        x = o.getAttribute('id');
    }
    if(!v) {
        datalistClear.apply(o);
    }

    var u=o.getAttribute('data-datalist-api'), api=(u!=''), h={accept:'application/json'};
    if(u) {
        var m=u.match(/\$[a-z0-9\-\_]+/ig), i=(m)?(m.length):(0), n;
        if(u.substr(0,1)!='/' && u.substr(0,4)!='http') {
            u=window.location.pathname+'/'+u;
        }
        while(i-- > 0) {
            n=o.form.querySelector('#'+m[i].substr(1));
            if(n) u=u.replace(m[i], encodeURIComponent(S.val(n)));
        }
        u += ((u.indexOf('?')>-1)?('&'):('?'))+'q='+encodeURIComponent(v);
    } else {
        //u = formUrl(o.parents('form'));
        //if(('form' in o) && (o.form.getAttribute('method')+'').toLowerCase()=='get') u=o.form.action;
        if('form' in o) u=o.form.action;
        else u=window.location.href;
        h['x-studio-action']='choices';
        h['x-studio-target']=encodeURIComponent(x);
        h['x-studio-term']=encodeURIComponent(v);
    }
    if(u===false || u===true) u=window.location.href;
    if(u.search(/\#/)>-1) u=u.replace(/\#.+$/, '');
    u=(u.search(/\?/)>-1)?(u.replace(/\&+(\bajax\b(=[^\&]*)?|$)/, '')+'&'):(u+'?');
    u+='ajax='+encodeURIComponent(x+'/'+v);

    o.setAttribute('data-datalist-q', v);
    S.ajax(u, null, datalistRender, S.error, 'json', o, h);
}

function datalistVal(o, v, fire)
{
    var s=o.getAttribute('data-datalist-multiple'), a=S.val(o);
    if(s) {
        if(s=='1' || s=='true') s=';';
        var si=a.split(s);
        if(v) {
            si[si.length -1] = v;
        } else if(arguments.length>1) {
            si.pop();
        }
        v = si.join(s)+s;
        a = si.pop();
    }
    if(arguments.length>1) {
        var dtp=o.getAttribute('data-datalist-preserve');
        if(dtp && (dtp=='0'||dtp=='false'||dtp=='off')) dtp=null;
        else if(dtp && S.val(o)) return a;
        S.val(o, v, fire);
    }
    return a;
}

var _db=null;
function datalistBlurTimeout()
{
    /*jshint validthis: true */
    var o=this;
    if(_db) clearTimeout(_db);
    _db = setTimeout(function(){ datalistBlur.apply(o); }, 200);
}

function datalistBlur(e)
{
    /*jshint validthis: true */
    if(!this.getAttribute('data-datalist-visible') && document.activeElement && !S.parentNode(document.activeElement, this.parentNode)) {
        datalistClear.apply(this);
    }
}

function datalistClear()
{
    /*jshint validthis: true */
    var o=this, v=datalistVal(o), t=new Date().getTime()+500, c=o.parentNode.querySelector('.s-datalist-container');
    o.setAttribute('data-datalist-q', v);
    o.setAttribute('data-datalist-t', t);
    if(c) c.parentNode.removeChild(c);
}

var _D={};
function datalistRender(d)
{
    /*jshint validthis: true */
    var r=this.getAttribute('data-datalist-renderer');
    if(r && (r in S)) {
        _D = S[r].call(this, d, datalistOption);
        return _D;
    }
    var o=this, c=o.parentNode.querySelector('ul.s-datalist'), n, p;
    if(!c) c=S.element.call(o.parentNode,{e:'span',p:{className:'s-datalist-container'},c:[{e:'ul',p:{className:'s-datalist'},a:{'data-target':o.getAttribute('id')}}]}).children[0];
    else c.innerHTML=''; // remove child nodes
    var id=o.getAttribute('id'), prefix = o.getAttribute('data-prefix');
    _D[id]={};
    for(n in d) {
        if(d.hasOwnProperty(n)) {
            p={e:'li',p:{className:'s-option'},a:{'data-value':n},t:{click:datalistOption}};
            if(typeof(d[n])=='string') {
                p.c=d[n];
            } else if('label' in d[n]) {
                if('value' in d[n]) p.a['data-value'] = d[n].value;
                if('group' in d[n]) {
                    p.c = [ {e:'em', c: d[n].group }, {e:'span',c:' '+d[n].label}];
                } else {
                    p.c=[{e:'span',c:d[n].label}];
                }
                if(('className' in d[n]) && d[n].className) {
                    p.p.className += ' '+d[n].className;
                }
            } else {
                p.c=[];
                for (var s in d[n]) {
                    if(d[n].hasOwnProperty(s) && d[n][s]) {
                        p.c.push({e:'span', a:{'data-attr':s},p:{className:'s-attr-'+s}, c: d[n][s] });
                    }
                }
            }
            if (prefix != undefined ) {
                p.a['data-prefix'] = prefix;
            }

            _D[id][p.a['data-value']]=d[n];

            S.element.call(c,p);
        }
    }
    if(!p) {
        p={e:'li',p:{className:'s-msg s-alert'},c:S.t('Nothing')};
        S.element.call(c,p);
    }
    return _D;
}

function datalistOption()
{
    /*jshint validthis: true */
    var id=this.parentNode.getAttribute('data-target'), o=this.parentNode.parentNode.parentNode.querySelector('#'+id);
    if(!o) return;
    o.setAttribute('data-datalist-t', new Date().getTime() + 1000);
    var v=this.getAttribute('data-value'),p=_D[id][v],b=this.getAttribute('data-prefix'), s=false,fo=o.form, e, n;
    var url=o.getAttribute('data-datalist-link');
    if(url) {
        var m=url.match(/\$[a-z0-9\-]+/), mi=m.length, mp;
        while(mi--) {
            mp=m[mi].substr(1);
            if(mp in p) {
                url = url.replace(m[mi], encodeURIComponent(p[mp]));
            }
        }
        window.location.href=url;
        return;
    }
    var dts=o.getAttribute('data-datalist-target'), dt={};
    if(dts) {
        var L=dts.split(/[\s\,\;]+/g), i=L.length;
        while(i-- >0) {
            if(L[i]) dt[L[i]] = true;
        }
        L=null;
        i=null;
        dts=null;
    }
    if(!b)b='';
    if(p) {
        if(typeof(p)=='string') {
            o.setAttribute('data-datalist-q', p);
            datalistVal(o, p);
            if(id.search(/^q__/)>-1) {
                e=fo.querySelector('#'+id.replace(/^q__/, ''));
                if(e) {
                    datalistVal(e, v, true);
                }
            }
        } else {
            s=false;
            if(id.search(/^q__/)>-1) {
                e=fo.querySelector('#'+id.replace(/^q__/, ''));
                if(e) {
                    for(n in p) {
                        datalistVal(e, p[n], true);
                        delete(p[n]);
                        break;
                    }
                }
            }
            for(n in p) {
                if(p[n]!=null && p[n].hasOwnProperty) {
                    if(!s) {
                        s=true;
                        o.setAttribute('data-datalist-q', p[n]);
                        datalistVal(o, p[n]);
                    }
                    e=fo.querySelector('#'+b+n);
                    if(!e) e=fo.querySelector('*[name="'+b+n+'"]');
                    if(e && (n in dt)) {
                        datalistVal(e, p[n], (id!=n) && n.substr(0,3)!='q__');
                    }
                }
            }
        }
    }
    datalistClear.call(o);
}
//S.datalistOption = datalistOption;

var _Picker={}, _Pickerc=0, _PickerT=0, _P18n;
function initDatepicker()
{
    /*jshint validthis: true */
    if(!('datepicker' in S) && ('Pikaday' in window)) S.datepicker = 'Pikaday';
    if(!('datepicker' in S) || this.getAttribute('data-datepicker')) return;

    var id='p'+(_Pickerc++);
    this.setAttribute('data-datepicker', id);
    this.setAttribute('autocomplete', 'off');

    if(S.datepicker=='Pikaday') {
        if(!_P18n) _P18n = {
            previousMonth:S.t('previousMonth'),
            nextMonth:S.t('nextMonth'),
            months:S.t('months'),
            weekdays:S.t('weekdays'),
            weekdaysShort:S.t('weekdaysShort'),
            midnight:S.t('midnight'),
            noon:S.t('noon'),
            dateFormat:S.t('dateFormat'),
            timeFormat:S.t('timeFormat')
        };
        var t=this.getAttribute('data-type'), cfg={ field: this, i18n: _P18n, format:S.t('dateFormat'), showTime: false }, D, d;
        if(!t) t=this.getAttribute('type');
        if(t && t.search(/time/)>-1) {
            cfg.showTime = true;
            cfg.use24Hour = true;
            cfg.format+= ' '+S.t('timeFormat');
        }
        if(this.value) {
            if('moment' in window) {
                D = moment(this.value, cfg.format);
                if(!D.valueOf() || D.valueOf()=='NaN') {
                    D = moment(this.value);
                }
                if(D.valueOf() && D.valueOf()!='NaN') {
                    cfg.defaultDate = new Date(D.valueOf());
                    if(d=D.format(cfg.format)) this.value = d;
                    cfg.setDefaultDate = true;
                }
                D=null;
                d=null;
            }
        }
        _Picker[id] = new Pikaday(cfg);
    }
    if(_PickerT) clearTimeout(_PickerT);
    _PickerT = setTimeout(cleanupDatepicker, 500);
}

function cleanupDatepicker()
{
    if(_PickerT) clearTimeout(_PickerT);
    _PickerT=0;
    for(var n in _Picker) {
        if(!document.querySelector('*[data-datepicker="'+n+'"]')) {
            _Picker[n].destroy();
            delete(_Picker[n]);
        }
        n=null;
    }
}

function initRequiredField(e)
{
    /*jshint validthis: true */
    var f=S.parentNode(this, '.field');
    if(f) {
        f.className += ' required';
    }
}

function initUploader(o)
{
    /*jshint validthis: true */
    var f=S.node(this, o);
    if(f.getAttribute('type')!='file' || f.getAttribute('data-status') || !('FileReader' in window)) return;
    f.setAttribute('data-status','ready');

    //S.bind(f, 'input', preUpload);
    S.bind(f, 'change', preUpload);
}

var _Uploads={};
function preUpload(e)
{
    /*jshint validthis: true */
    S.stopEvent(e);
    if(this.getAttribute('data-status')!='ready') return;
    this.setAttribute('data-status','uploading');
    var i=this.files.length, U={target:this,size:0,loaded:0,url:this.form.action,id:'upl'+((new Date()).getTime())};
    _Uploads[this.id] = U;

    U.progress = this.parentNode.querySelector('.s-progress');
    if(!U.progress) U.progress = S.element({e:'div',p:{className:'s-progress'},c:[{e:'div',p:{className:'s-progress-bar'}}]}, null, this);
    var s=this.getAttribute('data-size'),a=this.getAttribute('accept'),ff, err=[], valid;
    if(a) a=','+a+',';
    clearMsg(this.parentNode);
    while(i--) {
        // check file size and accepted formats
        if(s && s<this.files[i].size) {
            err.push(S.t('UploadSize').replace('%s', S.formatBytes(s))+' ');
        }
        if(a) {
            valid = false;
            ff = this.files[i].type;
            if(ff) {
                if(a.indexOf(','+ff+',')>-1) {
                    valid=true;
                } else if(ff.indexOf('/')>-1 && a.indexOf(','+ff.replace(/\/.*/, '/*')+',')>-1) {
                    valid = true;
                }
            }
            if(!valid && (ff=this.files[i].name.replace(/.*(\.[^\.]+$)/, '$1')) && a.indexOf(','+ff+',')>-1) {
                valid = true;
            }
            if(!valid) {
                err.push(S.t('UploadInvalidFormat')+' ');
            }
        }
    }
    if(err.length>0) {
        errorMsg(this.parentNode, err);
        this.setAttribute('data-status','ready');
        return false;
    }
    i=this.files.length;
    while(i--) {
        uploadFile(this.files[i], U);
    }
}

function errorMsg(o, m)
{
    return S.element.call(o, {e:'div',p:{className:'s-msg s-error'},c:m});
}

function clearMsg(o)
{
    var L=o.querySelectorAll('.s-msg'), i=L.length;
    while(i--) {
        L[i].parentNode.removeChild(L[i]);
    }
}

function clearFileInput(el) {
  try {
    el.value = null;
    return el;
  } catch(ex) { }
  if (el.value) {
    return el.parentNode.replaceChild(el.cloneNode(true), el);
  }
}

function uploadFile(file, U)
{
    var loaded = 0;
    var step = 700000;//1024*1024;
    var total = file.size;
    var i=0;
    var ajax = [];
    var H = { 'x-studio-action': 'Upload', 'Content-Type': 'application/json' };
    var workers = 2;
    var retries = 5;
    U.size += total;
    //var progress = document.getElementById(file.name).nextSibling.nextSibling;

    var reader = new FileReader();

    var uploadProgress = function(d)
    {
        if('size' in d) {
            U.loaded += d.size;
            if(U.loaded > U.size) {
                U.loaded = U.size;
            }
            workers++;
        }
        var w=(U.loaded*100/U.size);

        U.progress.querySelector('.s-progress-bar').setAttribute('style','width:'+w+'%');

        var el = this;
        if('id' in d) {
            var b=el.parentNode.querySelector('span.text');
            if(!el.previousSibling || el.previousSibling.nodeName.toLowerCase()!='input') {
                S.element({e:'input',a:{type:'hidden',name:el.name,id:el.id,value:d.value}},el);
            } else {
                el.previousSibling.value = d.value;
            }
            var t={e:'a',p:{className:'s-upload s-auto-remove'},t:{click:removeUpload},c:d.file+' '};
            if(!b) {
                b=S.element({e:'span',p:{className:'text'},c:[t]},el.previousSibling);
            } else {
                S.removeChildren(b, ':not(.s-upload)');
                S.element.call(b, t);
            }
            b.className += ' s-f-file';
            S.init(b);
            el.setAttribute('data-status', 'ready');
            el = clearFileInput(el);
            S.deleteNode(U.progress);
            S.enableForm(el.form);
            //var v=d.value;
        } else if(el.form.className.search(/\bs-disabled\b/)<0) {
            S.disableForm(el.form);
        }

        if(workers--) {
            if(ajax.length > 0) {
                S.ajax.apply(el, ajax.shift());
            }
        }
    };

    var uploadError = function(d, status, url, req)
    {
        if(retries && retryUpload.call(this, url)) return;

        // remove any error messages within this form field
        var M=this.parentNode.querySelectorAll('.s-msg,.s-progress'), i=M.length, err=(d && ('message' in d)) ?d.message :'There was an error in the file upload.';
        if(err) {
            while(i--) M[i].parentNode.removeChild(M[i]);
            S.element({e:'div',p:{className:'s-msg s-error'},c:err}, null, this);
        }

        this.setAttribute('data-status', 'ready');
        if(('retry' in d) && d.retry && retries--) {
            preUpload.call(this);
        } else {
            clearFileInput(this);
        }
        if(this.form) S.enableForm(this.form);
        //workers++;
    };

    var retryUpload = function(url)
    {
        if(!(this.id in _Uploads)) return false;

        var U=_Uploads[this.id], m=url.match(/\&_index=([0-9]+)$/), i=(m && m[1]) ?parseInt(m[1]) :null;
        if(i===null) return false;
        var loaded = i * step;

        var d = { _upload: { id: U.target.id, uid: U.id, uploader: U.target.getAttribute('data-uploader-id'), file: file.name, start: loaded, end: loaded+step, total: total, data: null, index: i }  };
        if(loaded + step > total) {
            d._upload.end = total;
            d._upload.last = true;
        }
        if(U.target.name.indexOf('[')) {
            var n=U.target.name, t=d, p=n.indexOf('['), m;
            while(p>-1) {
                m=n.substr(0,p+1).replace(/[\[\]]+/, '');
                n=n.substr(p+1);
                p=n.indexOf('[');
                t[m]={};
                t=t[m];
                if(p<=-1) {
                    m = n.substr(0, n.length -1);
                    t[m]=0;
                    t=null;
                    break;
                }
            }
        }

        var errorReader = new FileReader();
        errorReader.onload = function (e) {
            d._upload.data = e.target.result;
            var data = JSON.stringify(d);
            d = null;
            S.ajax(url+'&_retry='+retries--, data, uploadProgress, uploadError, 'json', U.target, H);
        };

        var blob = file.slice(loaded,d._upload.end);
        errorReader.readAsDataURL(blob);

        return true;
    }

    reader.onload = function(e) {
        var d = { _upload: { id: U.target.id, uid: U.id, uploader: U.target.getAttribute('data-uploader-id'), file: file.name, start: loaded, end: loaded+step, total: total, data: e.target.result, index: i++ }  };
        loaded += step;
        if(U.target.name.indexOf('[')) {
            var n=U.target.name, t=d, p=n.indexOf('['), m;
            while(p>-1) {
                m=n.substr(0,p+1).replace(/[\[\]]+/, '');
                n=n.substr(p+1);
                p=n.indexOf('[');
                t[m]={};
                t=t[m];
                if(p<=-1) {
                    m = n.substr(0, n.length -1);
                    t[m]=0;
                    t=null;
                    break;
                }
            }
        }
        //progress.value = (loaded/total) * 100;
        if(loaded <= total) {
            blob = file.slice(loaded,loaded+step);
            reader.readAsDataURL(blob);
        } else {
            d._upload.end = loaded = total;
            d._upload.last = true;
        }
        var data = JSON.stringify(d), u = U.url;
        if(u.indexOf('?')>-1) u+='&_index='+d._upload.index;
        else u+='?_index='+d._upload.index;
        d = null;
        if(workers) {
            S.ajax(u, data, uploadProgress, uploadError, 'json', U.target, H);
            workers--;
        } else {
            ajax.push([u, data, uploadProgress, uploadError, 'json', U.target, H]);
        }
    };
    var blob = file.slice(loaded,step);
    reader.readAsDataURL(blob);
}

function removeUpload(e)
{
    /*jshint validthis: true */
    var el = this.parentNode.parentNode.querySelector('input[type="hidden"]');
    el.value='';// remove only this upload
    el=null;
    this.parentNode.className = this.parentNode.className.replace(/\s*\bs-f-file\b/g, '');
    this.parentNode.removeChild(this);
}


function initFilters()
{
    /*jshint validthis: true */
    var t=this;
    if(this.className.search(/\bs-a-filters\b/)>-1) return;
    //S.bind(this, 'input', formFilters);
    var fn=(this.getAttribute('data-query-filter')) ?queryFilters :formFilters;
    S.bind(this, 'change', fn);
    fn.call(this);
}

var _FF={};

function enableField(on)
{
    /*jshint validthis: true */
    if(arguments.length==0) on=true;
    var cn = this.className,an='readonly', L, i;
    if(on) {
        if(cn.search(/\bs-f-disable\b/)>-1) cn=cn.replace(/\s*\bs-f-disable\b/g, '');
        if(cn.search(/\bs-f-enable\b/)<0) cn+=' s-f-enable';
        L=this.querySelectorAll('input['+an+'],select['+an+'],textarea['+an+']');
        i=L.length;
        while(i--) L[i].removeAttribute(an);
    } else {
        if(cn.search(/\bs-f-enable\b/)>-1) cn=cn.replace(/\s*\bs-f-enable\b/g, '');
        if(cn.search(/\bs-f-disable\b/)<0) cn+=' s-f-disable';
        L=this.querySelectorAll('input:not(['+an+']),select:not(['+an+']),textarea:not(['+an+'])');
        i=L.length;
        while(i--) L[i].setAttribute(an, an);
    }
    cn=cn.trim();
    if(cn!=this.className) this.className = cn;
}

function displayField(on)
{
    /*jshint validthis: true */
    if(arguments.length==0) on=true;
    var cn = this.className,an='readonly', L, i;
    if(on) {
        if(cn.search(/\bi-hidden\b/)>-1) cn=cn.replace(/\s*\bi-hidden\b/g, '');
        L=this.querySelectorAll('input['+an+'],select['+an+'],textarea['+an+']');
        i=L.length;
        while(i--) L[i].removeAttribute(an);
    } else {
        if(cn.search(/\bi-hidden\b/)<0) cn+=' i-hidden';
        L=this.querySelectorAll('input:not(['+an+']),select:not(['+an+']),textarea:not(['+an+'])');
        i=L.length;
        while(i--) L[i].setAttribute(an, an);
    }
    cn=cn.trim();
    if(cn!=this.className) this.className = cn;
}

/**
 * Query Filters
 *
 * Just add the attribute data-quey-filters with the DOM query that should be enabled when selected.
 * By default will disable all .field elements (or @data-query-filter-disable) that are sibling to this one
 */
 function queryFilters(e)
{
    /*jshint validthis: true */
    var a=this.getAttribute('data-query-filter');
    if(!a) {
        S.log('[INFO] Nothing to be queried...');
        return;
    }
    var reset=(this.className.search(/\bs-a-filters\b/)<0);
    if(reset) this.className += ' s-a-filters';

    var b=this.getAttribute('data-query-filtered');
    var c = ':scope > '+b.replace(/,/g, ', :scope > ');
    var P=(b) ?S.parentNode(this, b) :null;
    if(!P) P=S.parentNode(this, S.Form.fieldSelector);
    if(!P) {
        S.log('[ERROR] Could not find parent filtering node');
        return;
    }

    var v=S.val(this);
    if(typeof(v)=="string" && v) {
        v = v.split(/\s*\,\s*/g);
    } else if(typeof(v)!='object') {
        v = [];
    }
    var k=v.length, q='', re, L=P.parentNode.querySelectorAll(c),i=L.length, fn=(this.getAttribute('data-filter-action')=='disable') ?enableField :displayField;
    while(k--) {
        q += (q) ?'|' :'';
        q += a.replace(/\$|\{\}/, v[k]);
    }

    if(q) {
        re = new RegExp('\\b('+q+')\\b');
    }

    while(i--) {
        if(!q || !re.test(L[i].className)) fn.call(L[i], false);
        else fn.call(L[i], true);
    }

    return;
}

/**
 * Form filters
 */
function formFilters(e)
{
    /*jshint validthis: true */
    var a=this.getAttribute('data-filters');
    if(!a) return;
    var reset=(this.className.search(/\bs-a-filters\b/)<0);
    if(reset) this.className += ' s-a-filters';

    var t=(a.indexOf(',')>-1)?(a.split(',')):([a]), i=t.length, nn=this.getAttribute('name'), fa=this.getAttribute('data-filter-action'),
      tn, ltn, tp='', L, l, T, s, v=S.val(this), tv, O,sel,A,fn,P, fid=(this.form.id)?(this.form.id + '.'):(''), fk, n, F=S.parentNode(this, '.item,form');
    if(!F) {
        console.log('[INFO] Could not apply form filters on:', this);
        return;
    }
    if(v && this.getAttribute('data-filter-value')) {
        var av=this.getAttribute('data-filter-value').split(/\s*\,\s*/g), avi=av.length,avf;
        while(avi--) {
            if(v==av[avi]) {
                avf=v;
                break;
            }
        }
        if(!avf) v=null;
    }
    if(nn.indexOf('[')>-1) {
        nn=nn.replace(/.*\[([^\[]+)\]$/, '$1');
        tp = this.id.substr(0, this.id.length - nn.length);
    }
    while(i--) {
        tn = tp+t[i];
        ltn = (tn.search(/\[[^\]]+/i)>-1)?(tn.replace(/^.*\[([^\]]+)\](\[\])?$/, '$1')+' '):(tn);
        fk = fid+tn;
        if(fa) {
            if((T=F.querySelector('#f__'+tn.replace(/[\[\]\-]+/g, '_')+',.if--'+ltn))) {
                if(fa=='enable' || fa=='disable') {
                    enableField.call(T, (fa=='enable')?(v):(!v));
                } if(fa=='show' || fa=='display' || fa=='hide') {
                    displayField.call(T, (fa!='hide')?(v):(!v));
                }
            }
        } else if((T=F.querySelector('select#'+tn))) {
            L = T.querySelectorAll('option');
            if(!(fk in _FF)) {
                _FF[fk]={o:[], v:{}, f:{}};
                for(l=0;l<L.length;l++) {
                    if(L[l].selected) _FF[fk].v[L[l].value]=true;
                    A=L[l].attributes;
                    n=A.length;
                    P={};
                    while(n--) {
                        if(A[n].name!='selected') {
                            P[A[n].name]=A[n].value;
                        }
                    }
                    P.label = L[l].label;//innerHTML;//S.text(L[l]);
                    _FF[fk].o.push(P);
                }
            } else {
                _FF[fk].v = {};
                for(l=0;l<L.length;l++) {
                    if(L[l].selected) _FF[fk].v[L[l].value]=true;
                }
            }

            if(reset || !(nn in _FF[fk].f) || v!=_FF[fk].f[nn]) {
                _FF[fk].f[nn] = v;
                O = [];
                L=_FF[fk].o;
                for(l=0;l<L.length;l++) {
                    sel = (L[l].value in _FF[fk].v);
                    tv=true;
                    if(L[l].value) {
                        for(fn in _FF[fk].f) {
                            // make do for multiple source filters
                            if(!('data-'+fn in L[l]) || L[l]['data-'+fn]!=_FF[fk].f[fn]) {
                                tv=false;
                                break;
                            }
                        }
                    }
                    if(tv) O.push({e:'option',a:L[l],p:{'selected':sel},c:L[l].label});
                }
                S.removeChildren(T);
                S.element.call(T,O);
                if(T.getAttribute('data-filters')) {
                    S.fire(T, 'change');
                }
            }
        } else if((T=F.querySelector('#f__'+tn.replace(/-/g, '_')))) {
            L = T.querySelectorAll('input[type="radio"],input[type="checkbox"]');
            if(!(fk in _FF)) {
                _FF[fk]={c:{}, v:{}, f:{}};
            }
            for(l=0;l<L.length;l++) {
                if(L[l].checked) _FF[fk].v[L[l].value]=true;
                _FF[fk].c[L[l].id]=L[l].value;
            }

            if(reset || !(nn in _FF[fk].f) || v!=_FF[fk].f[nn]) {
                _FF[fk].f[nn] = v;
                O = [];
                L=_FF[fk].c;
                var E, Pn;
                for(n in L) {
                    sel = (L[l] in _FF[fk].v);
                    tv=true;
                    if(L[l]) {
                        E = T.querySelector('input#'+n);
                        for(fn in _FF[fk].f) {
                            // make do for multiple source filters
                            if(!(a=E.getAttribute('data-'+fn)) || a!=_FF[fk].f[fn]) {
                                tv=false;
                                break;
                            }
                        }
                    }
                    if(!(Pn=S.parentNode('label'))) {
                        Pn = E.parentNode;
                    }
                    if(tv) {
                        if(Pn.className.search(/\bi-hidden\b/)>-1) {
                            Pn.className = Pn.className.replace(/\s*\bi-hidden\b/g, '');
                        }
                        E.checked = sel;
                        E.setAttribute('checked', 'checked');
                    } else {
                        if(Pn.className.search(/\bi-hidden\b/)<0) {
                            Pn.className += ' i-hidden';
                        }
                        E.checked = false;
                        E.removeAttribute('checked');
                    }
                }
                /*
                if(E.getAttribute('data-filters')) {
                    S.fire(E, 'change');
                }
                */
            }
        }
        //@TODO: search, checkbox and radio
    }
}

/**
 * Subform/multiple input inclusion
 */
function initSubform(o)
{
    /*jshint validthis: true */
    if(!o || !S.node(o)) o=this;
    var btns=[{e:'a',a:{title:'-','class':'s-button-del'},t:{click:subformDel}},{e:'a',a:{title:'+','class':'s-button-add'},t:{click:subformAdd}}];

    // items
    var id=o.getAttribute('id');
    if(!id) {
        id='_sid'+(_eids++);
        o.setAttribute('id', id);
    }
    var L=document.querySelectorAll('#'+id+'>.item'), i=L.length, fmin=o.getAttribute('data-min'), fmax=o.getAttribute('data-max'), cb;
    // add minimum fields
    if(fmin && i < fmin) {
        while(i++ < fmin) {
            subformAdd(o);
        }
        L=o.querySelectorAll('#'+id+'>.item');
        i=L.length;
    }
    S.parentNode(o, S.Form.fieldSelector).setAttribute('data-count', i);

    // buttons: add, add(contextual), remove(contextual)
    if(!fmax || fmax!=i || fmax!=fmin) {
        var b=o.parentNode.parentNode.querySelector('div.s-subform-buttons');
        if(!b) {
            var t=S.node(o.parentNode.parentNode.querySelector('dt,.label')), bd={e:'div',p:{className:'s-subform-buttons s-buttons'},c:[btns[1]]};
            if(t) b = S.element.call(t, bd);
            else b = S.element(bd, o.parentNode);
        }
        while(i-- > 0) {
            if(fmax && i > fmax) {
                S.deleteNode(L[i]);
            } else if(!(cb=L[i].querySelector('.s-buttons')) || cb.parentNode!=L[i]) {
                if(cb) {
                    // might be sub-subforms, check if there's the button
                    var cL=L[i].querySelectorAll('.s-buttons'), ci=cL.length;
                    cb=null;
                    while(ci--) {
                        if(cL[ci].parentNode==L[i]) {
                            cb=cL[ci];
                            break;
                        }
                    }
                    if(cb) continue;
                }

                var xx=S.element.call(L[i], {e:'div',p:{className:'s-buttons'},c:btns});
            }
        }
    }
    // minimun
    /*
    if(fmin && sf.length<=fmin && !bdel.hasClass('disabled')) bdel.addClass('disabled');
    else if(fmin && sf.length>fmin && bdel.hasClass('disabled')) bdel.removeClass('disabled');
    */
}

var _subformPos='§';
function subformAdd(e)
{
    /*jshint validthis: true */
    var el, o;

    if((o=S.node(e))) {
        if(arguments.length>1) el=S.node(arguments[1]);
    } else {
        S.stopEvent(e);
        S.tg = this;

        if(el = S.parentNode(this, S.Form.fieldSelector)) {
            o = el.querySelector('.items[data-template]');
            el = S.parentNode(this, '.item');
            if(el && S.parentNode(el, '.items[data-template]')!=o) el=null;
        }
    }
    if(!o) return false;
    var tpl=o.getAttribute('data-template'),
        prefix=o.getAttribute('data-prefix'),
        _prefix=prefix.replace(/[\[\]\_]+/g, '_').replace(/_+$/, ''),
        sf=o.querySelectorAll('.item'),
        i=sf.length,
        fmax=o.getAttribute('data-max'),
        n,
        re;

    if(!(fmax && sf.length>=fmax)) {
        if(i>0){
            var ne=sf[i-1].querySelector('*[name]');
            if(ne) {
                n=ne.getAttribute('name');
                re=new RegExp(prefix.replace(/([^a-z0-9])/i, '\\\$1')+'[_\\\[]([0-9]*)[\\\]_].*');
                n=n.replace(re, '$1');
                if(n) {
                    if(n.substr(0,3)=='q__') n=n.substr(3);
                    i=parseInt(n)+1;
                }
                while(o.querySelector('*[name^="'+prefix+'['+i+']"]') && i < 999) {
                    i++;
                }
            }
        }
        re=new RegExp((prefix+'\[§\]').replace(/([^a-z0-9])/gi, '\\\$1'), 'gi');
        var ri=new RegExp((_prefix+'_§_').replace(/([^a-z0-9])/gi, '\\\$1'), 'gi');
        var c=document.createElement('div');
        c.innerHTML = tpl.replace(re, prefix+'['+i+']').replace(ri, _prefix+'_'+i+'_');
        c=c.children[0];
        if(el) {
            if(el.nextSibling) {
                el.parentNode.insertBefore(c, el.nextSibling);
                c=null;
            }
        } else if(sf.length>0) {
            el = sf[0];
            el.parentNode.insertBefore(c, el);
            c=null;
        }
        if(c) {
            o.appendChild(c);
        }
        S.init(o.parentNode);
    }
    /*
    tdz.subform(o);
    tdz.initForm(o.parents('form').eq(0));
    */
    return false;
}

function subformDel(e)
{
    /*jshint validthis: true */
    S.stopEvent(e);
    var el, o;

    /*
    if(el = S.parentNode(this, S.Form.fieldSelector)) {
        o = el.querySelector('.items[data-template]');
        el = null;
    }
    */

    if(this.parentNode.parentNode.className.search(/\bitem\b/)>-1) {
        el=this.parentNode.parentNode;
        o=el.parentNode;
    } else {
        o = this.parentNode.nextSibling;
    }

    var sf=o.querySelectorAll(':scope > .item'),fmin=o.getAttribute('data-min'), i=sf.length;

    if(!(fmin && sf.length<=fmin)) {
        el.parentNode.removeChild(el);
        i--;
    }
    S.parentNode(o, S.Form.fieldSelector).setAttribute('data-count', i);


    //S.subform(o);
    return false;
}

var _omnibar, _omnibarProperties={};
function initOmnibar(o)
{
    /*jshint validthis: true */
    if(!o || !S.node(o)) o=this;
    _omnibar = true;

    var id=o.getAttribute('data-omnibar'), L=o.form.querySelectorAll('input,select,textarea'), i=L.length, n, tag, N, nn;
    _omnibarProperties[id]={_default: id};
    while(i--) {
        if(!L[i].getAttribute('data-omnibar-alias') && !L[i].getAttribute('data-omnibar') && (n=L[i].getAttribute('name'))) {
            L[i].setAttribute('data-omnibar-alias', S.slug(n));
        }
        if(tag=L[i].getAttribute('data-omnibar-alias')) {
            _omnibarProperties[id][tag] = tag;
            if((n=L[i].getAttribute('name')) && n!=tag) {
                _omnibarProperties[id][n] = tag;
            }
            nn = L[i].nodeName.toLowerCase();
            if(!(nn==='input' && (L[i].type=='radio' || L[i].type=='checkbox')) && (N=S.parentNode(L[i], 'label')) && (n=S.text(N)) && n!=tag) {
                _omnibarProperties[id][n.toLowerCase()] = tag;
            }
        }
    }
    S.bind(o, 'change', omnibarFormField);
    S.bind(o.form, 'change', omnibarForm);
    omnibarForm.apply(o.form);
}

function omnibarForm(e)
{
    if(e) S.stopEvent(e);
    if(!_omnibar) return;
    var d = S.formData(this, false, true),
        o=this.querySelector('input[data-omnibar]'),
        oid=(o) ?o.getAttribute('data-omnibar').split(/\s*\,\s*/g) :[],
        i=0,
        n,
        el,
        s='',
        a,
        v,
        nn,
        L;

    while(i<oid.length) {
        if(oid[i] in d) {
            s += (s)?' ' :'';
            s += omnibarValue(d[oid[i]], false);
            delete(d[oid[i]]);
        }
        i++;
    }

    for(n in d) {
        s += (s)?' ' :'';
        el=this.querySelector('*[name="'+n+'"]');
        if(!el || !(a=el.getAttribute('data-omnibar-alias'))) {
            a = n;
        }
        v = d[n];
        if(el) {
            nn = el.nodeName.toLowerCase();
            if(nn==='select') {
                for(i=0;i<el.options.length;i++) {
                    if(el.options[i].value==v) {
                        v = el.options[i].label;
                        break;
                    }
                }
            } else if(nn=='input' && (el.type=='radio' || el.type=='checkbox')) {
                L = this.querySelectorAll('*[name="'+n+'"]:checked');
                if(L.length>0) v=[];
                for(i=0;i<L.length;i++) {
                    if(el=S.parentNode(L[i], 'label')) {
                        v.push(S.text(el));
                    } else {
                        v.push(L[i].value);
                    }
                }
            }
        }
        s += omnibarValue(v, a);
    }
    if(s) s+=' ';

    S.val(o, s);
}

function omnibarValue(v, prop)
{
    var s='';
    if(prop) {
        prop=S.slug(prop);
        if(typeof(v)=='object' && ('length' in v)) {
            var i=0;
            while(i < v.length) {
                s += (s) ?' ' :'';
                s += prop+':';
                s += (v[i].search(/[\s\:]/)>-1) ?'"'+v[i].replace(/["\s]+/g, ' ')+'"' :v[i];
                i++;
            }
        } else {
            s += prop+':';
            s += (v.search(/[\s\:]/)>-1) ?'"'+v.replace(/["\s]+/g, ' ')+'"' :v;
        }
        return s;
    } else {
        if (typeof(v)=='object') v = v.join(' ');
        if(v.indexOf(':')>-1) v = '"'+v.replace(/[\s\"]+/, ' ').trim()+'"';
        return v;
    }
}

function omnibarFormField(e)
{
    if(e) S.stopEvent(e);
    if(!_omnibar) return;
    _omnibar = false;

    var s = S.val(this).trim(), t={_default:this.getAttribute('data-omnibar')},
        p=null,
        d='',
        tag,
        i,
        a={};

    while(s) {
        p=null;
        i=s.search(/[\s\:\"]/);
        if(i>-1) {
            if(s.substr(i, 1)===':') {
                p = omnibarFields.call(this, (tag=s.substr(0, i)), t, true);
                s = s.substr(i+1).trim();
                if(!p) {
                    d += (d) ?' ' :'';
                    d += tag;
                    continue;
                }
            }
            if(p) {
                if(s.substr(0, 1)==='"' && (i=s.substr(1).indexOf('"'))) {
                    omnibarApply.call(this, s.substr(1, i), p, t, !(p in a));
                    a[p] = true;
                    s = s.substr(i+2).trim();
                } else if((i=s.search(/\s/)) && i>-1) {
                    omnibarApply.call(this, s.substr(0, i), p, t, !(p in a));
                    a[p] = true;
                    s = s.substr(i+1).trim();
                } else {
                    omnibarApply.call(this, s, p, t, !(p in a));
                    a[p] = true;
                    s = '';
                    break;
                }
            } else {
                if(i>0) {
                    d += (d) ?' ' :'';
                    d += s.substr(0, i);
                    s = s.substr(i).trim();
                } else {
                    s = s.trim();
                }
                if(s.substr(0, 1)==='"') {
                    if((i=s.substr(1).indexOf('"'))) {
                        d += (d) ?' ' :'';
                        d += s.substr(0, i+2);
                        s = s.substr(i+2).trim();
                    } else {
                        d += (d) ?' ' :'';
                        d += s.substr(0, 1);
                        s = s.substr(1).trim();
                    }
                }
            }
        } else {
            d += (d) ?' ' :'';
            d += s;
            s = '';
            break;
        }
    }

    if(d) {
        a._default = true;
        a[t._default] = true;
        omnibarApply.call(this, d, '_default', t);
    }

    var L=this.form.querySelectorAll('input[data-omnibar-alias],select[data-omnibar-alias],textarea[data-omnibar-alias]');
    i=L.length;
    while(i--) {
        p=L[i].getAttribute('data-omnibar-alias');
        if(p && !(p in a)) {
            a[p]=true;
            omnibarApply.call(this, '', p, t, true);
        }
    }

    _omnibar = true;
}

function omnibarFields(prop, t, check)
{
    if(!prop) prop='_default';
    var id=this.getAttribute('data-omnibar'), F=this.form, lprop=prop.toLowerCase();

    if(id && (id in _omnibarProperties)) {
        if(prop in _omnibarProperties[id]) {
            if(prop!=_omnibarProperties[id][prop]) {
                prop=_omnibarProperties[id][prop];
            }
        } else if(lprop in _omnibarProperties[id]) {
            prop = _omnibarProperties[id][lprop];
        }
    }

    var i=0, L, j=0;
    if(prop in t) {
        if(typeof(t[prop])==='string') {
            var tg = t[prop].split(/\s*\,\s*/g);
            t[prop] = [];
            for(i=0;i<tg.length;i++) {
                if(tg[i]) {
                    L=F.querySelectorAll('input[name="'+tg[i]+'"],select[name="'+tg[i]+'"],textarea[name="'+tg[i]+'"]');
                    for(j=0;j<L.length;j++) {
                        t[prop].push(L[j]);
                    }
                }
            }
        }
    } else {
        t[prop] = F.querySelectorAll('input[data-omnibar-alias="'+prop+'"],select[data-omnibar-alias="'+prop+'"],textarea[data-omnibar-alias="'+prop+'"]');
    }
    if(check) {
        return (t[prop] && t[prop].length>0) ?prop :null;
    }
    return t[prop];
}

function omnibarApply(v, prop, t, clear)
{
    var fo=(typeof(prop)!='object') ?omnibarFields.call(this, prop, t) :prop, F=this.form;
    var i=0, L, j=0;

    i=fo.length;
    var nn, lv=v.toLowerCase(), label;
    while(i--) {
        nn=fo[i].nodeName.toLowerCase();
        if(nn==='textarea' || (nn==='input' && fo[i].type!=='checkbox' && fo[i].type!=='radio')) {
            S.val(fo[i], v);
        } else if(nn==='select') {
            L=fo[i].options;
            for(j=0;j<L.length;j++) {
                if(L[j].value.toLowerCase()==lv || (L[j].label.toLowerCase()==lv)) {
                    L[j].selected = true;
                } else if(clear && L[j].selected) {
                    L[j].selected = false;
                }
            }
        } else {
            label=S.parentNode(fo[i], 'label');
            if(fo[i].value.toLowerCase()==lv || (label && S.text(label).toLowerCase()==lv)) {
                fo[i].checked = true;
            } else if(clear) {
                fo[i].checked = false;
            }
        }
    }
}


function initTypeToggler()
{
    if(!this.getAttribute('data-toggler')) {
        this.setAttribute('data-toggler',1);
        var T=S.parentNode(this, S.Form.fieldSelector);
        if(T) T=T.querySelector('.label, dt, label');
        if(!T) T=this.parentNode;
        S.element.call(T, {e:'a',a:{'class':'s-type-toggler s-api--toggle','data-toggler-option':'0','data-toggler':'#'+this.id+'[data-alt-type]'},t:{click:toggleType},c:[{e:'i',p:{className:'s-api--'+this.getAttribute('type')+' i-toggler-0'}},{e:'i',p:{className:'s-api--'+this.getAttribute('data-alt-type')+' i-toggler-1'}}]});
    }
}

function toggleType(e)
{
    if(e) S.stopEvent(e);

    var t=this.getAttribute('data-toggler'),T;
    if(T=this.previousElementSibling) {
        if(!T.getAttribute('data-alt-type')) T=null;
    }
    if(!T)T=document.querySelector(t);
    if(!T) return;
    var nt=T.getAttribute('data-alt-type'), ct=T.getAttribute('type');
    if(nt && ct) {
        this.setAttribute('data-toggler-option', this.getAttribute('data-toggler-option')==1 ?'0' :'1');
        T.setAttribute('type', nt);
        T.setAttribute('data-alt-type', ct);
    }
}

function initHtmlEditor()
{
    if(this.getAttribute('data-html-editor')) return;
    var a=(this.getAttribute('data-editor')), Editor, elcontainer;
    var limit = this.getAttribute('maxlength') ?? 0;
    limit = parseInt(limit);

    if(!a) {
        if('Quill' in window) a = 'Quill';
        else if('pell' in window) a = 'pell';
    }
    if(!(a in window)) return;
    this.setAttribute('data-html-editor', a);

    if (limit > 0) {
        var elcounter = S.element({e:'div',p:{id:'s-editor-counter-'+this.id, className:'s-html-editor-counter'}},null,this);
        elcounter.innerHTML = '<p>'+S.l[S.language].EditorLimit.replace('[n]','<span id="s-editor-counter-length-'+this.id+'" class="s-html-editor-length">0</span>').replace('[t]',limit)+'</p>';

        var elcounter_length = elcounter.querySelector('span#s-editor-counter-length-'+this.id);
    }

    var selfel = this;
    elcontainer = S.element({e:'div',p:{id:'s-editor-'+this.id, className:'s-html-editor'}}, this);
    S.debug(a);

    if(a=='pell') {
        Editor = pell.init({
          element: elcontainer,
          onChange: html => this.value = html,
          // Instructs the editor which element to inject via the return key
          defaultParagraphSeparator: 'p',
          styleWithCSS: false,
          // Choose your custom class names
          classes: {
            actionbar: 's-editor-actionbar',
            button: 's-editor-button',
            content: 's-editor-content s-input s-textarea',
            selected: 's-editor-button-active'
          }
        });
        Editor.content.innerHTML = this.value;
    } else if (a=='Quill') {
        var toolbarOptions = [
          ['bold', 'italic', 'underline', 'strike',        // toggled buttons
          'blockquote', 'code-block', 'link'],

          [{ 'list': 'ordered'}, { 'list': 'bullet' }],
          [{ 'script': 'sub'}, { 'script': 'super' }],      // superscript/subscript
          [{ 'indent': '-1'}, { 'indent': '+1' }],          // outdent/indent
          [{ 'direction': 'rtl' }],                         // text direction

          [{ 'size': ['small', false, 'large', 'huge'] }],  // custom dropdown
          [{ 'header': [1, 2, 3, 4, 5, 6, false] }],

          [{ 'color': [] }, { 'background': [] }],          // dropdown with defaults from theme
          [{ 'font': [] }],
          [{ 'align': [] }],

          ['clean']                                         // remove formatting button
        ];

        var options = {
            modules: {
                toolbar: toolbarOptions
            },
            //placeholder: 'Compose an epic...',
            theme: 'snow'
        };

        var totalchar = 0;

        Editor = new Quill(elcontainer, options);
        Editor.container.firstChild.innerHTML = this.value;
        Editor.on('text-change', function(delta, oldDelta, source) {
            if (source == 'user') {
                selfel.value = Editor.root.innerHTML;
                if (selfel.type == 'textarea') {
                    selfel.innerText = Editor.root.innerHTML;
                } else {
                    selfel.value = Editor.root.innerHTML;
                }

                totalchar = Editor.getLength() ?? 0;

                /* TODO
                if (totalchar > limit && limit > 0) {
                    var diffchar = (totalchar-limit);
                    Editor.deleteText(limit, diffchar);
                }*/
            }

            elcounter_length.innerText = totalchar;
            if (totalchar > limit) {
                elcounter_length.classList.add('exceed');
            } else if (totalchar < limit) {
                elcounter_length.classList.remove('exceed');
            }

            if (totalchar > (limit-((limit*5)/100)) && totalchar < limit) {
                elcounter_length.classList.remove('exceed');
                elcounter_length.classList.add('closing');
            } else if (totalchar < (limit-((limit*5)/100)))
                elcounter_length.classList.remove('closing');{
            }
        });
    }
}

function initChoicesJs()
{
    if(!('Choices' in window)) return;
    if(this.getAttribute('data-choices-js')) return;
    this.setAttribute('data-choices-js',1);
    new Choices(this, {
        silent: true,
        duplicateItemsAllowed: false,
        removeItems: true,
        removeItemButton: true,
        searchEnabled: true,
        classNames: {
          containerOuter: 's-input-choices',
          containerInner: 's-choices-inner',
          input: 's-choices-input',
          inputCloned: 's-choices-input--cloned',
          list: 's-choices-list',
          listItems: 's-choices-list--multiple',
          listSingle: 's-choices-list--single',
          listDropdown: 's-choices-list--dropdown',
          item: 's-choices-item',
          itemSelectable: 's-choices-item--selectable',
          itemDisabled: 's-disabled-input',
          itemChoice: 's-choices-item--choice',
          placeholder: 's-choices-placeholder',
          group: 's-choices-group',
          groupHeading: 's-choices-heading',
          button: 's-choices-button',
          activeState: 'is-active',
          focusState: 'is-focused',
          openState: 'is-open',
          disabledState: 'is-disabled',
          highlightedState: 'is-highlighted',
          selectedState: 'is-selected',
          flippedState: 'is-flipped',
          loadingState: 'is-loading',
          noResults: 'has-no-results',
          noChoices: 'has-no-choices'
        },
    });
}

var _skipLogic=false;
function initLogic()
{
    // adds trigger events on watched fields, applies current logic if necessary
    if(this.getAttribute('data-logic-init')) return;
    this.setAttribute('data-logic-init', Date.now());

    if(_skipLogic) return;
    var id=this.getAttribute('id');
    if(!id) {
        id='_sid'+(_eids++);
        this.setAttribute('id', id);
    }
    var L=JSON.parse(this.getAttribute('data-logic')), T=this, i, P=S.parentNode(this, '.s-api-field'), W, C, I, j, fn, s, ss={}, v, a, F=function(e){return checkLogic(e, id);};

    if(P) P=P.parentNode;
    else return false;

    if(!(L instanceof Array)) L=[L];
    i=L.length;
    while(i--) {
        if(L[i] instanceof Array) s=L[i][0];
        else if('s' in L[i]) s=L[i].s;
        else if('source' in L[i]) s=L[i].source;
        else break;
        if(s in ss) break;
        ss[s]=true;
        C=P.querySelector(fn=S.Form.fieldSelector.replace(/\s*\,/g, '.if--'+s+',')+'.if--'+s);
        if(C) {
            I=C.querySelectorAll('input,textarea,select');
            j=I.length;
            while(j--) {
                S.bind(I[j], 'input', F);
                checkLogic.call(T, {target:I[j]});
            }
        }
    }
}

function checkLogic(e)
{
    var C=(arguments.length>1) ?document.getElementById(arguments[1]) :this;
    if(!S.parentNode(C, S.Form.fieldSelector)) return; // not in DOM
    var L=JSON.parse(C.getAttribute('data-logic')), i, j, s, ts, v, a, A=[], E=e.target, val=S.val(E), add, del, O, P, T, V, D;
    if(!(L instanceof Array)) L=[L];
    i=L.length;
    while(i--) {
        ts=null;
        if(L[i] instanceof Array) {
            s=L[i][0];
            if(L[i].length===2) {
                if(val in L[i][1]) {
                    v=val;
                    a=L[i][1][v];
                }
            } else {
                v = L[i][1];
                a = L[i][2];
            }
        } else if('s' in L[i]) {
            s=L[i].s;
            v = ('v' in L[i]) ?L[i].v :null;
            a = ('a' in L[i]) ?L[i].a :null;
        } else if('source' in L[i]) {
            s=L[i].source;
            v = ('value' in L[i]) ?L[i].value :null;
            a = ('action' in L[i]) ?L[i].action :null;
        } else {
            break;
        }
        if(s===E.id || (E.id.substr(-1*(s.length+1))==='_'+s && E.getAttribute('name').substr(-1*(s.length+2))==='['+s+']')) {
            if(v==val || ((v instanceof Array) && v.indexOf(val)>-1)) {
                if(a) A.push(a);
            }
        }
    }
    if(A.length>0) {
        // apply actions: show | hide | remove | disable | enable | template:<template-name>
        for(j=0;j<A.length;j++) {
            a=A[j];
            add=del=null;
            if(a=='remove') {
                S.deleteNode(C);
                C=null;
                return;
            } else if(a==='show') {
                add = 's-visible';
                del = 's-hidden';
            } else if(a==='hide') {
                add = 's-hidden';
                del = 's-visible';
            } else if(a==='disable') {
                add = 's-disabled-input';
                L=C.querySelectorAll(S.Form.inputSelector.replace(/\s*\,/g, ':not(:disabled),')+':not(:disabled)');
                i = L.length;
                while(i--) L[i].disabled=true;
            } else if(a==='enable') {
                del = 's-disabled-input';
                L=C.querySelectorAll(S.Form.inputSelector.replace(/\s*\,/g, ':disabled,')+':disabled');
                i = L.length;
                while(i--) L[i].disabled=false;
            } else if(a.substr(0, 9)==='template:') {
                O = S.parentNode(C, S.Form.fieldSelector);
                if((P=O.parentNode) && (T=P.querySelector('template.s-template.'+a.substr(9)))) {
                    if(T && T.content) T = T.content.firstElementChild.cloneNode(true);
                    // @TODO: dump contents and load in new field?
                    if(C.getAttribute('data-logic-init') && (D = T.querySelector('*[data-logic]'))) {
                        D.id = C.id;
                        D.setAttribute('data-logic-init', C.getAttribute('data-logic-init'));
                        D=null;
                    }
                    P.replaceChild(T, O);
                    _skipLogic = true;
                    S.init(T)
                    _skipLogic = false;
                }
            }
            if(del && E.classList.contains(del)) E.classList.remove(del);
            if(add && !E.classList.contains(add)) E.classList.add(add);
        }
    }
}

function Form(o)
{
    var q='Studio_Form';
    if(!('initDatePicker' in S)) S.initDatepicker = initDatepicker;
    if(q in S.modules) {
        delete(S.modules[q]);
        S.load('s-form.css');
        S.addPlugin('Datepicker', initDatepicker, 'input[data-type^=date],input[type^=date],.s-datepicker');
        S.addPlugin('RequiredField', initRequiredField, '.field > .input > *[required]');
        S.addPlugin('Datalist', initDatalist, '*[data-datalist-api],*[data-datalist]');
        S.addPlugin('Uploader', initUploader, 'input[data-uploader]');
        S.addPlugin('TypeToggler', initTypeToggler, '.app-enable-type-toggler input[data-alt-type]');
        S.addPlugin('Filters', initFilters, 'input[data-filters],select[data-filters]');
        S.addPlugin('QueryFilters', initFilters, 'input[data-query-filter],select[data-query-filter]');
        S.addPlugin('Subform', initSubform, 'div.subform[data-template],div.items[data-template]');
        S.addPlugin('Cleanup', initCleanup, 'button.cleanup');
        S.addPlugin('Omnibar', initOmnibar, 'input[data-omnibar]');
        S.addPlugin('HtmlEditor', initHtmlEditor, 'textarea[data-format="html"]');
        S.addPlugin('ChoicesJs', initChoicesJs, 'select.s-choices-js,.s-choices-js select');
        S.addPlugin('Logic', initLogic, '*[data-logic]');
        S.clearForm=clearForm;
    }
    var n=S.node(o, this);
    if(n) S.init(n);
}

function init()
{
    if(!('Studio' in window)) {
        return setTimeout(init, 500);
    }
    if(!S) S=window.Studio;
    S.Form = {
        fieldSelector: '.s-api-field,.field',
        checkSelector: 'input[type="radio"],input[type="checkbox"]',
        inputSelector: 'input,select,textarea'
    };
}

window.Studio_Form = Form;
window.Studio_Form_CheckLabel = initCheckLabel;
window.Studio_Form_AutoSubmit = initAutoSubmit;
window.Studio_Form_Reload = formReload;
init();

if('Studio.s-form' in window) {
    var i=window['Studio.s-form'].length;
    while(i--) Form(window['Studio.s-form'][i]);
    delete(window['Studio.s-form']);
}

})();