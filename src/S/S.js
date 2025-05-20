/*! capile/studio v1.3 | (c) 2025 Tecnodesign <ti@tecnodz.com> */
if(!('Studio' in window))window.Z=window.Studio={version:1.3, host:null, altHost:null,uid:'/_me',timeout:0,headers:{},env:'prod',timestamp:null,xhrCredentials:true,xhrHeaders:{'x-requested-with':'XMLHttpRequest'}};
(function(S) {
"use strict";
var _ajax={}, _isReady, _onReady=[], _onResize=[], _got=0, _langs={}, _assetUrl, _assets={}, _pending={}, _wm,
  defaultModules={
    Callback:'*[data-callback]',
    Copy:'a.s-copy[data-target]',
    DisplaySwitch:'*[data-display-switch]',
    ToggleActive:'.s-toggle-active',
    Studio_Form: 'form.s-form,form.z-form',
    Studio_Form_AutoSubmit: 'form.s-auto-submit',
    Studio_Form_CheckLabel:'.i-check-label input[type=radio],.i-check-label input[type=checkbox]',
    Studio_Api: '.s-api-app[data-url]',
    Studio_Api_AutoRemove: '.s-auto-remove',
    Studio_Graph: '.s-graph',
    Studio_Calendar: '.s-calendar',
    Recaptcha: '.s-recaptcha',
    LoadUri: '.s-action[data-load-uri]',
    LanguageSelection: 'link[rel="alternate"][hreflang]',
    Autofocus: 'input[autofocus],textarea[autofocus],select[autofocus]',
    AttributeTemplate: '*[data-attr-template]'
  }, _sTimestamp='';

// load authentication info
var _reWeb=/^https?:\/\//;
function initStudio(d)
{
    S.lang();

    var zh=document.querySelector('*[data-studio-config]');
    if(zh) {
        var zc=zh.getAttribute('data-studio-config'), cfg=JSON.parse(zc.indexOf('{')<0 ?atob(zc) :zc), cn;
        zh.removeAttribute('data-studio-config');
        if(cfg) {
            for(cn in cfg) if((cn in S) && (typeof(S[cn])!='function')) S[cn] = cfg[cn];
        }
    }

    if(!('modules' in S)) {
        S.modules = defaultModules;
    }

    if(!_assetUrl) {
        var e=document.querySelector('script[src*="/s.js"]');
        if(!e) e=document.querySelector('script[src*="/S.js"]');
        if(e) _assetUrl = e.getAttribute('src').replace(/\/s\.js.*/, '/');
        else if((e=document.querySelector('script[src*=".js"]'))) _assetUrl = e.getAttribute('src').replace(/\/[^\/]+\.js.*/, '/');
        else _assetUrl = '/';
        if(_assetUrl.search(/^[a-z0-9]*?\:\/\//)>-1) S.host=_assetUrl.replace(/^([a-z0-9]*?\:\/\/[^\/]+).*/, '$1');
        // defining assets
        var L=document.querySelectorAll('script[src^="'+_assetUrl+'/s-.+\.js"]'), i=L.length;
        while(i--) {
            //S.debug('asset '+L[i].getAttribute('src').replace(/\.js.*/, ''));
            _assets[L[i].getAttribute('src').replace(/\.js.*/, '')]=true;
        }
    }

    var store=true;
    if(!('user' in S)) {
        S.user=null;
        d=S.storage('s-auth');
        if(d && String(d)) {
            if(('token' in d) && d.token) {
                if(!('headers' in S)) S.headers = {};
                S.headers['z-token']=d.token;
            }
            if(String(window.location).search('reload')<0) {
                S.uid=null;
                store = false;
           }
        }
        if(S.uid && (_reWeb.test(window.location.origin) || _reWeb.test(S.uid))) {
            if(S.host && !_reWeb.test(S.uid)) S.uid = S.host + S.uid;
            var ts, qs='', hp=window.location.hash.search(/#@[0-9]+$/);
            if(hp>-1) {
                ts=window.location.hash.substr(hp).replace(/[^0-9]+/g, '');
                S.storage('s-ts', parseInt(ts));
                window.location.hash=window.location.hash.substr(0, hp);
            } else {
                ts=S.storage('s-ts');
            }
            if(ts) qs = '?'+ts;
            S.ajax(S.uid+qs, null, initStudio, null, 'json');
            return;
        }
    }
    if(d) {
        if(!S.timestamp) {
            var sT=document.querySelector('script[src^="'+_assetUrl+'s.js?"]');
            if(sT) _sTimestamp = '?'+encodeURIComponent(sT.getAttribute('src').substr(_assetUrl.length + 5));
        }

        if(Object.prototype.toString.call(d)=='[object Array]') {
            S.user = false;
        } else {
            var n, run=[]; //, start=false;
            if('plugins' in d) {
                if(!('plugins' in S)) S.plugins = {};
                for(n in d.plugins) {
                    if(n in S.plugins) continue;
                    S.plugins[n]=d.plugins[n];
                    if('load' in S.plugins[n]) {
                        S.load.apply(S, d.plugins[n].load);
                    }
                    if('callback' in S.plugins[n]) {
                        if(S.plugins[n].callback in S) run.push(S[S.plugins[n].callback]);
                        else if(S.plugins[n].callback in window) run.push(window[S.plugins[n].callback]);
                    }
                }
                delete(d.plugins);
            }
            S.user = d;
            if(run.length>1) {
                while(run.lengh>0) {
                    run.pop().call(S.user);
                }
            }
            if('updateUserInfo' in S) S.updateUserInfo(S.user);
        }
    } else if(S.uid) {
        return;
    }
    if(!('timeout' in S)) S.timeout = 0;
    if(store && S.timeout) S.storage('s-auth', d, S.timeout);

    S.ready(S.init);
    setTimeout(S.init, 500);
}

S.storage=function(n, v, e)
{
    if(!('localStorage' in window)) return; // add new storage types
    var r=window.localStorage.getItem(n);
    var t=(new Date().getTime())/1000;
    if(arguments.length>1) {
        if(arguments.length<3) e=0;
        else if(e<100000000) e+=t;
        if(v===null) window.localStorage.removeItem(n);
        else window.localStorage.setItem(n, parseInt(e)+','+JSON.stringify(v));
    } else if(r && r.search(/^([0-9]+),.+/)>-1) {
        var a=parseInt(r.substr(0,r.indexOf(',')));
        if(a > 0 && a < t) r=null;
        else r=JSON.parse(r.substr(r.indexOf(',')+1));
    }
    return r;
};

S.init=function(o)
{
    if(!('modules' in S)) {
        S.modules = defaultModules;
    }
    if(!('modules' in S)) return;

    if('ZModules' in window) {
        var fn;
        for(var q in ZModules) {
            if(typeof(ZModules[q])=='function') {
                fn=('name' in ZModules[q])?(ZModules[q].name):(q);
                S.addPlugin(fn, ZModules[q], q);
            }
            ZModules[q]=null;
            delete(ZModules[q]);
        }
        delete(window.ZModules);
    }

    var c=(arguments.length>0)?(S.node(o, this)):(null),n;
    if(!c) {
        c=document;
        n=true;
    }
    for(var i in S.modules) {
        var ifn='init'+i;

        if(!S.modules[i]) continue;
        var L=c.querySelectorAll(S.modules[i]), j=L.length;

        if(!(ifn in S) && j && i.search(/_/)>-1) {
            // must load component, then initialize the object
            var a=i.replace(/^S(tudio)?_/, '').split(/_/);
            if(i.substr(0,7)==='Studio_' && (i in window)) {
                if(typeof(window[i])=='function') {
                    ifn=S.addPlugin(i, window[i], S.modules[i]);
                    window[i]=null;
                    delete(window[i]);
                }
            } else {
                var u='s-'+S.slug(a[0]);
                if(!(u in _assets)) {
                    loadAsset('s-'+S.slug(a[0]), S.init, arguments, c);
                } else if(!n) {
                    if(!(i in _pending)) _pending[i] = [];
                    _pending[i].push(c);
                    setTimeout(initPending, 500);
                }
            }
        }
        if(ifn in S) {
            if(typeof(S.modules[i])=='string') {
                for(j=0;j<L.length;j++) S[ifn].call(L[j]);
                L=null;
                j=null;
            } else if(S.modules[i]) {
                S[ifn](c);
            }
        }
    }
};

function initPending()
{
    for(var i in _pending) {
        if(_pending[i].length>0 && (i in window)) {
            if(typeof(window[i])=='function') {
                S.addPlugin(i, window[i], S.modules[i]);
                window[i]=null;
                delete(window[i]);
                while(_pending[i].length>0) {
                    S.init(_pending[i].shift());
                }
                delete(_pending[i]);
            } else {
                setTimeout(initPending, 500);
            }

        } else {
            delete(_pending[ifn]);
        }
    }
}

var _delayed={};
function loadAsset(f, fn, args, ctx)
{
    //S.debug('loadAsset: '+f);
    var T, o, r, s=((S.env=='dev' && S.timestamp) ?'?'+(new Date().getTime()) :_sTimestamp);
    if(f in _assets) return;
    _assets[f]=true;

    if(f.indexOf('.')<0) {
        if(!('Studio.'+f in window)) window['Studio.'+f] = [ctx];
        else window['Studio.'+f].push(ctx);
        loadAsset(f+'.js'+s, fn, args, ctx);
        loadAsset(f+'.css'+s, fn, args, ctx);
        return;
    }

    if(f.indexOf('/')<0) {
        f=_assetUrl+f;
    }
    var f0 = f.replace(/\?.*/, '');

    if(f.indexOf('.css')>-1) {
        T=document.querySelector('head');
        if(!T.querySelector('link[href^="'+f0+'"]')) {
            o={e:'link',a:{rel:'stylesheet',type:'text/css',href:f}};
        } else {
            T=null;
            r=true;
        }
    } else if(f.indexOf('.js')>-1) {
        T=document.body;
        if(!document.querySelector('script[src^="'+f0+'"]')) {
            o={e:'script',p:{async:true,src:f}};
        } else {
            T=null;
            r=true;
        }
    }

    if(T && o) {
        S.element.call(T, o);
        T=null;
        o=null;
    }

    if(r && (f in _delayed)) {
        var a;
        while(_delayed[f].length>0) {
            a=_delayed[f].shift();
            a[0].apply(a[1], a[2]);
        }
        delete(_delayed[f]);
    }
    if(arguments.length>1 && fn && typeof(fn)!='undefined') {
        if(r) {
            fn.apply(ctx, args);
        } else {
            if(!(f in _delayed)) _delayed[f]=[];
            _delayed[f].push([fn, ctx, args]);
            setTimeout(loadAssetDelayed, 500);
        }

    }
    return r;
}

function loadAssetDelayed()
{
    for(var n in _delayed) loadAsset(n);
}

S.load=function()
{
    //_isReady = true;// fix this
    var i=arguments.length;
    while(i--) {
        loadAsset(arguments[i]);
    }
    i=null;
};

S.addPlugin=function(id, fn, q) {
    var pid = '_'+id;
    if(!('modules' in S)) {
        S.modules = defaultModules;
    }
    if(!(pid in S.modules)) {
        if((id in S.modules) && S.modules[id]==q) {
            pid=id;
        }
        S.modules[pid]=q;
        S['init'+pid]=fn;
        return 'init'+pid;
    }
};

S.get=function(q, o, i)
{
    var r;
    if(!o) { // non-contextual
        if(typeof i ==='undefined') return document.querySelectorAll(q);
        r=document.querySelectorAll(q);
    } else if('length' in o) {
        r=[];
        for(var oi=0;oi<o.length;oi++) {
            var ro=S.get(q, o[oi]);
            if(ro.length>0) {
                for(var roi=0;roi<ro.length;roi++) {
                    r.push(ro[roi]);
                    if(typeof i !=='undefined' && i in r) return r[i];
                }
            }
        }
    } else if(q.search(/^[#\.]?[^\.\s\[\]\:]+$/)) {
        if(q.substr(0,1)=='#') r=[o.getElementById(q.substr(1))];
        else if(q.substr(0,1)=='.') r=o.getElementsByClassName(q.substr(1));
        else r=o.getElementsByTagName(q);
    } else {
        var id=o.getAttribute('id');
        if(!id) {
            id='_n'+(_got++);
            o.setAttribute('id', id);
            r = document.querySelectorAll('#'+id+' '+q);
            o.removeAttribute('id');
        } else {
            r = document.querySelectorAll('#'+id+' '+q);
        }
    }
    if(typeof i !=='undefined') return (r.length>i)?(r[i]):(false);
    return r;
};

S.encodeHtml=function (s) {
    return s.replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/'/g, '&apos;')
            .replace(/"/g, '&quot;');
};
S.decodeHtml=function (s) {
    return s.replace(/&quot;/g, '"')
            .replace(/&apos;/g, '\'')
            .replace(/&gt;/g, '>')
            .replace(/&lt;/g, '<')
            .replace(/&amp;/g, '&');
};
S.cookie=function(name, value, expires, path, domain, secure) {
    if(arguments.length>1) {
        document.cookie = name + "=" + escape(value) + ((arguments.length>2 && expires != null)?("; expires=" + expires.toGMTString()):('')) + ((arguments.length>3 && path)?("; path=" + path):('')) + ((arguments.length>4 && domain)?("; domain=" + domain):('')) + ((arguments.length<5 || secure)?("; secure"):(''));
    } else {
        var a = name + "=", i = 0;
        while (i < document.cookie.length) {
            var j = i + a.length;
            if (document.cookie.substring(i, j) === a) {
                var e = document.cookie.indexOf (';', j);
                if (e === -1) e = document.cookie.length;

                return unescape(document.cookie.substring(j, e));
            } else {
                i = document.cookie.indexOf(' ', i) + 1;
                if (i <= 0) break;
            }
        }
        return null;

    }
    return value;
};

S.slug=function(s)
{
    return String(s).toLowerCase()
      .replace(/[ąàáäâãåæă]/g, 'a')
      .replace(/[ćčĉç]/g, 'c')
      .replace(/[ęèéëê]/g, 'e')
      .replace(/ĝ/g, 'g')
      .replace(/ĥ/g, 'h')
      .replace(/[ìíïî]/g, 'i')
      .replace(/ĵ/g, 'j')
      .replace(/[łľ]/g, 'l')
      .replace(/[ńňñ]/g, 'n')
      .replace(/[òóöőôõðø]/g, 'o')
      .replace(/[śșşšŝ]/g, 's')
      .replace(/[ťțţ]/g, 't')
      .replace(/[ŭùúüűû]/g, 'u')
      .replace(/[ÿý]/g, 'y')
      .replace(/[żźž]/g, 'z')
      .replace(/[^\w\s-]/g, '') // remove non-word [a-z0-9_], non-whitespace, non-hyphen characters
      .replace(/[\s_-]+/g, '-') // swap any length of whitespace, underscore, hyphen characters with a single -
      .replace(/^-+|-+$/g, ''); // remove leading, trailing -
};

S.unique=function(array) {
    var a = array.concat();
    for(var i=0; i<a.length; ++i) {
        for(var j=i+1; j<a.length; ++j) {
            if(a[i] === a[j])
                a.splice(j--, 1);
        }
    }
    return a;
};

S.lang=function(s)
{
    if(s) S.language=s;
    else {
        if(!S.language) {
            S.language = S.cookie('lang');
        }
        S.language = S.cookie('lang');

        if(!S.language) {
            var m=document.querySelector('meta[name="language"]');
            if(m) S.language = m.getAttribute('content');
            else {
                if((m=document.querySelector('html[lang]'))) {
                    S.language = m.getAttribute('lang');
                } else {
                    S.language = 'en';
                }
            }
        }
    }

    if(S.language.length>2 && !(S.language in S.l)) {
        S.language = S.language.substr(0,2);
    }

    if(!(S.language in S.l)) {
        S.language = 'en';
    }
    return S.language;
};

S.langw=function(ctx,before,after)
{
    var h=S.get('link[rel="alternate"][hreflang],meta[name="language"]');
    if(h.length>1) {
        var r={e:'span',a:{'class':'lang s-languages'},c:[]},l='';
        for(var hi=0;hi<h.length;hi++) {
            if(h[hi].nodeName.toLowerCase()=='meta') {
                l=h[hi].getAttribute('content');
                _langs[l]=true;
                r.c.push({e:'a',a:{'class':l+' selected'},c:l});
            } else {
                l=h[hi].getAttribute('hreflang');
                _langs[l]=false;
                r.c.push({e:'a',a:{'class':l,'data-lang':l,href:'#'+l},c:l,t:{trigger:_setLanguage}});
            }
        }
        if(ctx) return S.element.call(((typeof ctx) == 'string')?(S.get(ctx,null,0)):(ctx),r,before,after);
        else return S.element(r,before,after);
    }
    return false;
};

function _setLanguage(l)
{
    /*jshint validthis: true */
    if(typeof l != 'string') {
        if('stopPropagation' in l) {
            l.stopPropagation();
            l.preventDefault();
        }
        l=this.getAttribute('data-lang');
    }
    if(!(l in _langs)) return false;
    S.cookie('lang', l, null, '/');
    window.location.reload();
    return false;
}

S.element=function(o,before,after) {
    var r,n,a=(typeof(o)==='object');
    if(typeof(o)=='string') {
        r=document.createTextNode(o);
        a=false;
    } else if(a && o.e) {
        r=document.createElement(o.e);
        if(o.p) {
            for(n in o.p) {
                r[n]=o.p[n];
                n=null;
            }
        }
        if(o.a) {
            for(n in o.a) {
                r.setAttribute(n,o.a[n]);
                n=null;
            }
        }
        if(o.t) {
            for(n in o.t) {
                if(n=='trigger' || n=='fastTrigger') S[n](r,o.t[n]);
                else S.addEvent(r,n,o.t[n]);
                n=null;
            }
        }
    } else if(S.isNode(o)) {
        r=o;
        o={};
    } else {
        if(o instanceof Array) o={c:o};
        r=document.createDocumentFragment();
    }
    if(a && ('c' in o)) {
        if(typeof(o.c)=='string') {
            if(('x' in o) && o.x) {
                r.innerHTML = o.c;
            } else {
                r.appendChild(document.createTextNode(o.c));
            }
        } else if(o.c instanceof Array) {
            var t=o.c.length,i=0;
            while(i < t) {
                if(typeof(o.c[i])=='string') r.appendChild(document.createTextNode(o.c[i]));
                else S.element.call(r,o.c[i]);
                i++;
            }
            i=null;
            t=null;
        }
    }
    if(a && ('d' in o) && (typeof(o.d)==='object')) {
        S.nodeData(r, o.d);
    }

    if(before) return before.parentNode.insertBefore(r,before);
    else if(after) return after.parentNode.insertBefore(r,after.nextSibling);
    else if(this && typeof(this)==='object' && ('appendChild' in this) && this.appendChild) return this.appendChild(r);
    else return r;
};

S.addEvent=function(o, tg, fn) {
    if (o.addEventListener) {
        o.addEventListener(tg, fn, false);
    } else if (o.attachEvent) {
        o.attachEvent('on'+tg, fn);
    } else {
        o['on'+tg] = fn;
    }
};

S.bind=S.addEvent;

S.removeEvent=function(o, tg, fn) {
    if (o.addEventListener) {
        o.removeEventListener(tg, fn, false);
    } else if (o.detachEvent) {
        o.detachEvent('on'+tg, fn);
    } else if('on'+tg in o) {
        o['on'+tg] = null;
        if('removeAttribute' in o)
            o.removeAttribute('on'+tg);
    }
};

S.unbind=S.removeEvent;
S.fastTrigger=function(o,fn){
    if(o.addEventListener) {
        o.addEventListener('touchstart', fn, false);
        o.addEventListener('mousedown', fn, false);
    } else if(o.attachEvent) {
        o.attachEvent('onclick', fn);
    }
};

S.trigger=function(o,fn){
    if(o.addEventListener) {
        o.addEventListener('tap', fn, false);
        o.addEventListener('click', fn, false);
    } else if(o.attachEvent) {
        o.attachEvent('onclick', fn);
    }
};

S.stopEvent=function(e){
    e.preventDefault();
    e.stopPropagation();
    return false;
};

S.ready=function(fn)
{
    if(arguments.length>0) {
        if(!_isReady) setReady(S.ready);
        _onReady.push(fn);
    }
    if(_isReady) {
        for(var i=0;i<_onReady.length;i++) {
            _onReady[i].call(S);
        }
    }
};

S.isReady=function()
{
    return _isReady;
};

S.isNode=function()
{
    for(var i=0;i<arguments.length;i++) {
        var o=arguments[i];
        if(typeof(o)=='string' && o) {
            return document.querySelector(o);
        }
        if(typeof(o)=='object' && ('jquery' in o || 'nodeName' in o)) {
            if('eq' in o) return o.eq(0);
            return o;
        }
    }
    return false;
};

S.node=function()
{
    for(var i=0;i<arguments.length;i++) {
        var o=arguments[i];
        var t=typeof(o);
        if(t=='undefined' || !o) continue;
        else if(t=='string' && (o=document.querySelector(o))) return o;
        else if(t=='object' && ('nodeName' in o)) return o;
        else if(t=='object' && ('jquery' in o)) return o.get(0);
    }
    return false;
};

S.parentNode=function(p, q)
{
    if(!p || !(p=S.node(p))) return false;
    else if((typeof(q)=='string' && p.matchesSelector(q))||p==q) return p;
    else if(p.nodeName.toLowerCase()!='html') return S.parentNode(p.parentNode, q);
    else return;
};

S.blur=function(o)
{
    if(o && o.className.search(/\bs-blur\b/)<0) {
        o.className += ' s-blur';
    }
};

S.focus=function(o)
{
    if(o && o.className.search(/\bs-blur\b/)>0) {
        o.className = o.className.replace(/\s*\bs-blur\b/, '');
    }
};

S.text=function(o, s)
{
    if(!o) return;
    var n=(arguments.length>1)?(o.querySelector(s)):(o);
    return n.textContent || n.innerText;
};


S.click=function(c)
{
    return S.fire(c, 'click');
};

S.events={};
S.event=function(c, ev)
{
    if(ev in S.events) {
        var L=S.events[ev], i;;
        if(typeof(L)=='function') S.events[ev].call(c);
        else {
            for(i=0;i<L.length;i++) S.events[ev][i].call(c);
        }
    }
}

S.fire=function(c, ev)
{
    if('createEvent' in document) {
        var e=document.createEvent('HTMLEvents');
        e.initEvent(ev, true, true);
        return c.dispatchEvent(e);
    } else {
        return c.fireEvent('on'+ev);
    }
};

S.checkInput=function(e, c, r)
{
    if(arguments.length==1 || c===null) c=e.checked;
    else if(e.checked==c) return;
    if(e.checked!=c) {
        e.checked = c;
        S.fire(e, 'change');
    }
    if(arguments.length<3 || r) S.fire(e, 'click');
    var i=3, p=e.parentNode;
    while(p && i-- > 0) {
        if(p.nodeName.toLowerCase()=='tr' || p.className.search(/\binput\b/)>-1) {
            var on=(p
                .className.search(/\bon\b/)>-1);
            if(c && !on) p.className += (p.className)?(' on'):('on');
            else if(!c && on) p.className = p.className.replace(/\bon\b\s*/, '').trim();
            break;
        }
        p=p.parentNode;
    }

};

var _delayTimers = {};
S.delay = function (fn, ms, uid) {
    if (!uid) uid ='dunno';
    if (uid in _delayTimers) clearTimeout(_delayTimers[uid]);
    _delayTimers[uid] = setTimeout(fn, ms);
};

S.toggleInput=function()
{
    var f, t=(S.isNode(this))?(this.getAttribute('data-target')):(null);
    if(t && this.form) {
        f=this.form.querySelectorAll(t+' input[type="checkbox"],input[type="checkbox"]'+t);
    } else if(this.parentNode) {
        if(this.parentNode.nodeName.toLowerCase()=='th') {
            f=S.parentNode(this,'table').querySelectorAll('td > input[type="checkbox"]');
        } else {
            f=S.parentNode(this,'div').querySelectorAll('input[name][type="checkbox"]');
        }
    }
    if(!f) return;
    var i=f.length, chk=(S.isNode(this))?(this.checked):(false);
    while(i-- > 0) {
        if(f[i]==this) continue;
        S.checkInput(f[i], chk, false);
    }
};

function setReady(fn)
{
    _isReady = (('readyState' in document) && document.readyState=='complete');
    if(_isReady) {
        if(!('time' in S)) S.time = new Date().getTime();
        return fn();
    }
    // Mozilla, Opera, Webkit
    if (document.addEventListener) {
        var _rel=function(){
            document.removeEventListener("DOMContentLoaded", _rel, false);
            _isReady = true;
            fn();
        };
        document.addEventListener( "DOMContentLoaded", _rel, false );
        _rel = null;
    // If IE event model is used
    } else if ( document.attachEvent ) {
        // ensure firing before onload
        var _dev=function(){
            if ( document.readyState === "complete" ) {
                document.detachEvent( "onreadystatechange", _dev);
                _isReady = true;
                fn();
            }
        };
        document.attachEvent("onreadystatechange", _dev);
    }
    // flush if it reached onload event
    window.onload = function() {
        _isReady = true;
        S.ready();
    };
}

var _v=false, _f={};

S.val=function(o, val, fire)
{
    if(typeof(o)=='string') {
        o=document.getElementById(o);
        if(!o) return false;
    }
    var v, t=o.type, f=o.getAttribute('data-format'),e, i, L;
    if(arguments.length==1) val=false;
    if(t && t.substr(0, 6)=='select') {
        v=[];
        for (i=0; i<o.options.length; i++) {
            if(val!==false) {
                if(o.options[i].value==val) o.options[i].selected=true;
                else if(o.options[i].selected) o.options[i].selected=false;
            } else if (o.options[i].selected) v.push(o.options[i].value);
        }
        if(val && fire) S.fire(o, 'change');
        i=null;
    } else if(t && (t=='checkbox' || t=='radio')) {
        var id=o.name;
        if(val!==false) {
            v=(typeof(val)=='string')?(val.split(/[,;]+/g)):(val);
            var vi={};
            i=v.length;
            while(i-- > 0) {
                vi[v[i]]=true;
            }
            L=o.form.querySelectorAll('input[name="'+id+'"]');
            i=L.length;
            while(i-- > 0) {
                if(L[i].getAttribute('value') in vi) {
                    if(!L[i].checked) {
                        L[i].setAttribute('checked','checked');
                        L[i].checked = true;
                        if(fire) S.fire(L[i], 'change');
                    }
                } else {
                    if(L[i].checked) {
                        L[i].removeAttribute('checked');
                        L[i].checked = false;
                        if(fire) S.fire(L[i], 'change');
                    }
                }
            }
            vi=null;
        } else {
            L=o.form.querySelectorAll('input[name="'+id+'"]:checked');
            i=L.length;
            if(i) {
                v=[];
                while(i-- > 0) {
                    v.unshift(L[i].value);
                }
            } else {
                v = '';
            }
        }
        L=null;
        i=null;
    } else if(f=='html' && (!(e=o.getAttribute('data-editor')) || e=='tinymce')) {
        S.fire(o, 'validate');
        v=o.value;
    } else if('value' in o) {
        if(val!==false) {
            o.value=val;
            o.setAttribute('value', val);
            if(fire) S.fire(o, 'change');
        }
        v = o.value;
    } else {
        if(val!==false) {
            o.setAttribute('value', val);
            if(fire) S.fire(o, 'change');
        }
        v=o.getAttribute('value');
    }
    t=null;
    if(v && typeof(v) == 'object' && v.length<2) v=v.join('');
    return v;
};

S.isVisible=function(o)
{
    return o.offsetWidth > 0 && o.offsetHeight > 0;
};

S.formData=function(f, includeEmpty, returnObject)
{
    var d, n;
    if(arguments.length<3) returnObject=false;
    if(arguments.length<2) includeEmpty=true;

    if(('id' in f) && (f.id in _f)) {
        d=_f[f.id];
    } else {
        var v, i, skip={}, nn, nt;
        d={};
        for(i=0;i<f.elements.length;i++) {
            if('name' in f.elements[i] && (n=f.elements[i].name)) {
                if(n in skip) continue;
                nn=f.elements[i].nodeName.toLowerCase();
                nt=(nn=='input')?(f.elements[i].type):(f.elements[i].getAttribute('type'));
                if(nn=='input' && nt=='file') continue;

                v = S.val(f.elements[i]);
                if(nt=='checkbox' || nt=='radio') skip[n]=true;
                if(v!==null && (v || includeEmpty || f.elements[i].getAttribute('data-always-send'))) {
                    if((n in d) && n.substr(-2)=='[]') {
                        if(typeof(d[n])=='string') d[n]=[d[n]];
                        d[n].push(v);
                    } else {
                        d[n] = v;
                    }
                }
            }
        }
    }
    if(returnObject) return d;
    var s='';
    if(d) {
        for(n in d) {
            if(n.substr(-2)=='[]') {
                var a = (typeof(d[n])=='string')?(d[n].split(',')):(d[n]),b=0;
                while(b<a.length) {
                    s += (s)?('&'):('');
                    s += (n+'='+encodeURIComponent(a[b]));
                    b++;
                }
                a=null;
                b=null;
            } else {
                s += (s)?('&'):('');
                s += (n+'='+encodeURIComponent(d[n]));
            }
        }
    }
    return s;
};

S.deleteNode=function(o)
{
    if(o.parentNode) return o.parentNode.removeChild(o);
};

S.initCallback=function(o)
{
    if(!o || !S.node(o)) o=this;
    var fn = o.getAttribute('data-callback'),
        e=o.getAttribute('data-callback-event'),
        nn=o.nodeName.toLowerCase(),
        C,
        noe;
    if(!fn) return;
    if(!e) {
        noe=true;
        e='click';
    } else {
        o.removeAttribute('data-callback-event');
    }

    if(fn in S) {
        C=S[fn];
    } else if(fn in window) {
        C=window[fn];
    } else if(fn.indexOf('.')>-1) {
        var c=fn.substr(0,fn.indexOf('.'));
        C=(c in window)?(window[c]):(null);
        fn = fn.substr(fn.indexOf('.')+1);
        while(C && fn.indexOf('.')>-1) {
            c=fn.substr(0,fn.indexOf('.'));
            fn = fn.substr(fn.indexOf('.')+1);
            C=(c in C)?(C[c]):(null);
        }
        C=(fn in C)?(C[fn]):(null);
    }

    if(!C) return;
    o.removeAttribute('data-callback');
    var f;

    if(noe && ((nn=='input' && o.type!='radio' && o.type!='checkbox' && o.type!='button')||nn=='textarea'||nn=='select')) {
        e='change';
        f=S.val(o);
    } else if(noe && nn=='form') {
        e='submit';
    } else {
        if(nn=='input' && o.checked) {
            f=true;
        }
    }
    S.bind(o, e, C);
    if(f) {
        S.fire(o, e);
    }

};


S.initCopy=function(o)
{
    if(!o || !S.node(o)) o=this;
    if(!o.getAttribute('data-target')) return;
    S.bind(o, 'click', executeAction);
}

S.initDisplaySwitch=function(o)
{
    if(!o || !S.node(o)) o=this;
    if(o.getAttribute('data-display-active')) return;
    o.setAttribute('data-display-active', '1');
    displaySwitch.call(o);
    if(o.nodeName.toLowerCase()=='button') S.bind(o, 'click', displaySwitch);
}

function displaySwitch()
{
    var qs=this.getAttribute('data-display-switch').split(/\s*\|\s*/), a=this.getAttribute('data-display-active');
    if(a=='') return;
    // hide
    a = (a>0) ?1 :0;
    var L=document.querySelectorAll(qs[a]), i=L.length,s;
    while(i--) {
        if(L[i].className.search(/\bi-hidden\b/)<0) {
            L[i].className += ' i-hidden';
        }
    }

    // show
    a = (a>0) ?0 :1;
    var L=document.querySelectorAll(qs[a]), i=L.length,s;
    while(i--) {
        if(L[i].className.search(/\bi-hidden\b/)>-1) {
            L[i].className=L[i].className.replace(/\s*\bi-hidden\b/g, '');
        }
    }

    this.setAttribute('data-display-active', (a>0) ?'1':'0');
    if(arguments.length>0) this.blur();

}

function executeAction(e)
{
    S.stopEvent(e);
    var a = this.getAttribute('data-action');
    if(!a) {
        if(this.className.search(/\bs-copy\b/)>-1) a='copy';
        else return;
    }

    var t=document.querySelector(this.getAttribute('data-target'));

    if(t) {
        var d=t.getAttribute('href');
        if(d && d.search(/^data:/)>-1) {
            if(d.substr(0, 15)=='data:text/plain') d=decodeURIComponent(d.replace(/^data:[^\,]+\,/, ''));
        } else if(!d) d=S.val(t);

        var input = S.element.call(document.body,{e:'textarea',a:{style:'position:absolute;left:-2000px;top:0;'},c:''});
        input.value = d;
        input.select();
        input.setSelectionRange(0, d.length);
        document.execCommand(a);
        S.deleteNode(input);
    }
}


S.removeChildren=function(o)
{
    var i=o.children.length;
    while(i--) {
        S.deleteNode(o.children[i]);
    }
};

S.selectOption=function(e)
{
    var o=this.getAttribute('data-original'), val=S.val(this);
    if(o===null) {
        this.setAttribute('data-original',val);
        if(!e) return;
    } else if(o==val) {
        return;
    } else {
        this.setAttribute('data-original',val);
    }
    var F=this.form, i=this.options.length, j, n, v, t, q, p;
    p=this.getAttribute('name');
    if(p && p.indexOf('[')>-1) {
        p = p.replace(/\[[^\]]+\]$/, '');
    } else {
        p=null;
    }
    while(i--) {
        if(this.options[i].selected) {
            j=this.options[i].attributes.length;
            while(j--) {
                n=this.options[i].attributes[j].nodeName;
                if(n.substr(0,5)=='data-' && (v=this.options[i].getAttribute(n))) {
                    q = (p)?('input[name="'+p+'['+n.substr(5)+']"]'):('input[name="'+n.substr(5)+'"]');
                    if((t=F.querySelector(q))) {
                        var dtp=t.getAttribute('data-datalist-preserve');
                        if(dtp && (dtp=='0'||dtp=='false'||dtp=='off')) dtp=null;
                        if(!dtp || !S.val(t)) {
                            S.val(t,v,true);
                        }
                    }
                }
            }
        }

    }
};

// pt_BR
if(!('l' in S)) S.l={en:{},pt:{}};
S.l.pt.add='Acrescentar';
S.l.pt.del='Excluir';
S.l.pt.Nothing='Nenhuma opção foi encontrada para esta consulta.';
S.l.pt.Error401='É necessário se autenticar para acessar esta página. Por favor experimente se conectar.';
S.l.pt.Error403='Parece que você não possui as credenciais para acessar esta página. Por favor experimente se conectar ou acessar com uma credencial diferente.';
S.l.pt.Error404='O recurso selecionado não existe (erro 404).';
S.l.pt.Error504='O recurso selecionado excedeu o tempo limite da requisição (erro 504).';
S.l.pt.Error='Houve um erro ao processar esta informação. Por favor tente novamente ou entre em contato com o suporte.';
S.l.pt.moreRecord="É necessário selecionar mais de um registro para essa operação.";
S.l.pt.noRecordSelected='Nenhum registro foi selecionado para essa operação.';
S.l.pt.decimalSeparator = ',';
S.l.pt.thousandSeparator = '.';
S.l.pt.UploadSize='O arquivo é maior que o permitido.';
S.l.pt.UploadInvalidFormat='O formato do arquivo não é suportado.';
S.l.pt.EditorLimit='Limite: [n]/[t]';

S.l.en.add='Insert';
S.l.en.del='Remove';
S.l.en.Nothing='No records were found.';
S.l.en.Error401='Authentication is required, and we could not authenticate your request. Please try signing in.';
S.l.en.Error403='Looks like you don\'t have enough credentials to access this page. Please try signing in or accessing it with a different username.';
S.l.en.Error404='The selected resource is not available (404 not found).';
S.l.en.Error504='The selected resource exceeded the response time limit (504 gateway error).';
S.l.en.Error='There was an error while processing this request. Please try again or contact support.';
S.l.en.moreRecord="You need to select more than one record for this action.";
S.l.en.noRecordSelected='No record was selected for this action.';
S.l.en.decimalSeparator = '.';
S.l.en.thousandSeparator = ',';
S.l.en.UploadSize='Uploaded file exceeds the limit of %s.';
S.l.en.UploadInvalidFormat='File format is not supported.';
S.l.en.EditorLimit='Limit: [n]/[t]';

// for timepickers
S.l.en.previousMonth = 'Previous Month';
S.l.en.nextMonth     = 'Next Month';
S.l.en.months        = ['January','February','March','April','May','June','July','August','September','October','November','December'];
S.l.en.weekdays      = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
S.l.en.weekdaysShort = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
S.l.en.midnight      = 'Midnight';
S.l.en.noon          = 'Noon';
S.l.en.dateFormat    ='YYYY-MM-DD';
S.l.en.timeFormat    ='HH:mm';


S.l.pt.previousMonth = 'Anterior';
S.l.pt.nextMonth     = 'Próximo';
S.l.pt.months        = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
S.l.pt.weekdays      = ['Domingo','Segunda','Terça','Quarta','Quinta','Sexta','Sábado'];
S.l.pt.weekdaysShort = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
S.l.pt.midnight      = 'Meia-noite';
S.l.pt.noon          = 'Meio-dia';
S.l.pt.dateFormat    ='DD/MM/YYYY';
S.l.pt.timeFormat    ='HH:mm';
S.l.pt_BR = S.l.pt;

S.error=function(msg)
{
    S.log('ERROR', this);
    for(var i=0;i<arguments.length;i++) {
        S.log(arguments[i]);
    }
};

S.loggr=null;
S.log=function()
{
    var i=0;
    if(S.loggr) {
        while(i < arguments.length) {
            S.element.call(S.loggr, {e:'p',p:{className:'msg log'},c:''+arguments[i]});
        }
        i++;
    }
    if('console' in window) console.log.apply(this, arguments);
};
S.debug=function()
{
    if(S.env!='prod') S.log.apply(this, arguments);
}

S.backwardsCompatible=function()
{
    S.trace=S.log;
    if(!('tdz' in window)) window.tdz = S;

    if (!String.prototype.encodeHTML) {
      String.prototype.encodeHTML = function () {
        return this.replace(/&/g, '&amp;')
                   .replace(/</g, '&lt;')
                   .replace(/>/g, '&gt;')
                   .replace(/"/g, '&quot;')
                   .replace(/'/g, '&apos;');
      };
    }
    S.xmlEscape = function(s) {return s.encodeHTML();};
    if (!String.prototype.decodeHTML) {
      String.prototype.decodeHTML = function () {
        return this.replace(/&apos;/g, "'")
                   .replace(/&quot;/g, '"')
                   .replace(/&gt;/g, '>')
                   .replace(/&lt;/g, '<')
                   .replace(/&amp;/g, '&');
      };
    }
    S.xmlUnescape = function(s) {return s.decodeHTML();};
}

S.initLoadUri=function()
{
    var u=this.getAttribute('data-load-uri');
    if(!u) return;
    if(u.search(/^([a-z0-9]+\:)\/\/([^\/]+)/)>-1) return; // need to enter the allowed hosts

    var t=this.getAttribute('data-target'), T=(t) ?document.querySelector(t) :this;

    S.ajax(u, null, loadHtml, S.error, 'html', T, {'x-studio-action': 'load-uri'});
}

S.initLanguageSelection=function()
{
    if(!document.querySelector('.s-languages')) S.langw(document.body);
}

function loadHtml(html)
{
    if(this && ('innerHTML' in this)) {
        this.innerHTML = html;
        S.init(this);
    }
}

var _ResponseType={arraybuffer:true,blob:true,document:true,json:true,text:true};

S.ajax=function(url, data, success, error, dataType, context, headers)
{
    if( typeof error == 'undefined' || !error ) error = this.error;
    if(!context) context = this;
    if (!window.XMLHttpRequest  || (url in _ajax)) {
        // no support for ajax
        error.apply(context);
        return false;
    }
    _ajax[url] = { r: new XMLHttpRequest(),
        success: success,
        error: error,
        context: context,
        type: dataType
    };
    var qs = (data===true)?(((url.indexOf('?')>-1)?('&'):('?'))+(new Date().getTime())):(''),
        m = (data && data!==true)?('post'):('get'),
        h;

    // make post!!!
    _ajax[url].r.onreadystatechange = ajaxProbe;
    if(dataType in _ResponseType) {
        XMLHttpRequest.responseType = (_ResponseType[dataType]===true)?(dataType):(_ResponseType[dataType]);
    }
    //_ajax[url].r.onload = ajaxOnload;
    _ajax[url].r.open(m, url+qs, true);
    for(h in S.xhrHeaders) _ajax[url].r.setRequestHeader(h, S.xhrHeaders[h]);
    _ajax[url].r.withCredentials = S.xhrCredentials;
    var n, ct;
    if('headers' in S) {
        for(n in S.headers) {
            if(S.headers[n]) {
                _ajax[url].r.setRequestHeader(n, S.headers[n]);
                if(n.toLowerCase()==='content-type') ct=headers[n];
            }
        }
    }
    if(headers) {
        if(m=='post' && data && String(data)=='[object FormData]') {
            ct = true;
        }
        for(n in headers) {
            if(headers[n]) {
                _ajax[url].r.setRequestHeader(n, headers[n]);
                if(n.toLowerCase()==='content-type') ct=headers[n];
            }
        }
    }
    if(m=='post') {
        if(!ct) {
            _ajax[url].r.setRequestHeader('content-type', 'application/x-www-form-urlencoded; charset=utf-8');
        }
        //if(typeof(data)=='string' || 'length' in data) _ajax[url].r.setRequestHeader('Content-Length', data.length);
        //_ajax[url].r.setRequestHeader('Connection', 'close');
        _ajax[url].r.send(data);
    } else {
        _ajax[url].r.send();
    }
};

function ajaxOnload()
{
    //S.log('ajaxOnload', arguments);
    return ajaxProbe();
}


function ajaxProbe(e)
{
    //S.log('ajaxProbe', JSON.stringify(e));
    var u, err;
    for(u in _ajax) {
        /*
        S.log(u+': '+JSON.stringify({
            readyState:_ajax[u].r.readyState,
            withCredentials:_ajax[u].r.withCredentials,
            status:_ajax[u].r.status,
            responseType:_ajax[u].r.responseType,
            response:_ajax[u].r.response}));
        */
        if(_ajax[u].r.readyState==4) {
            var d, R=_ajax[u];
            delete(_ajax[u]);

            if(R.type=='xml' && R.r.responseXML) {
                d=R.r.responseXML;
            } else if(R.type=='json') {
                if(R.r.responseText) {
                    try {
                        d=JSON.parse(R.r.responseText);
                    } catch (e) {
                        S.error(e);
                        err = true;
                        d = e;
                    }
                } else {
                    d=null;
                }
            } else if('responseText' in R.r) {
                d=R.r.responseText;
            } else {
                d=R.r.response;
            }
            if(R.r.status==200 && !err) {
                R.success.apply(R.context, [ d, R.r.status, u, R.r ]);
            } else {
                R.error.apply(R.context, [ d, R.r.status, u, R.r ]);
            }
            d=null;
            if('r' in R) delete(R.r);
            R=null;
        }
    }
}

S.t=function(s, lang)
{
    if(!lang) lang=S.language;
    if((lang in S.l) && (s in S.l[lang])) {
        return S.l[lang][s];
    } else if(lang.indexOf(/[-_]/)>0) {
        return S.t(s, lang.replace(/[-_].*$/, ''));
    }
    return s;
};

S.formatNumber=function(n, d, ds, ts)
{
    if(!d) d=2;
    var x = (n.toFixed(d) + '').split('.');
    var x1 = x[0];
    if(!ds) ds=S.t('decimalSeparator');
    var x2 = x.length > 1 ? ds + x[1] : '';
    var rgx = /(\d+)(\d{3})/;
    while (rgx.test(x1)) {
        if(!ts) ts = S.t('thousandSeparator');
        x1 = x1.replace(rgx, '$1' + ts + '$2')
    }
    return x1 + x2;
};

S.formatBytes=function(s, precision)
{
    if(!precision) precision=2;

    s = parseInt(s);
    if(s>0) {
        var units = [ 'B', 'KB', 'MB', 'GB', 'TB' ],
            pow=Math.round((s>0 ?Math.log(s) :0)/6.93);//Math.log(1024));
        pow = Math.min(pow, units.length -1);
        var b = s / Math.pow(1024, pow);

        return S.formatNumber(b, precision)+' '+units[pow];
    } else {
        return '0';
    }
};

S.initToggleActive=function(o)
{
    o=S.node(this,o);
    var id=o.getAttribute('id'),
        control=S.nodeData(o, 'toggler-options'),
        el=((control && control.indexOf('self')>-1) || o.className.search(/\bs-toggler\b/)>-1) ?o :null,
        sibling=(control && control.indexOf('sibling')<0) ?false :true,
        child=(control && control.indexOf('child')<0) ?false :true,
        toggler=(control && control.indexOf('no-toggler')>-1) ?false :true,
        storage=(control && control.indexOf('storage')>-1) ?true :false,
        drag=(control && control.indexOf('draggable')>-1) ?true :false,
        load=false, a, i, tw;
    if((sibling && o.parentNode.querySelector(':scope > .s-toggler')) || (child && o.querySelector(':scope > .s-toggler'))) {
        return;
    }
    if(!id) {
        storage = false;
        id='_n'+(_got++);
        o.setAttribute('id', id);
    } else if(S.storage('s-toggler/#'+id)) {
        load = true;
    } else if(tw=S.nodeData(o, 'toggler-default')) {
        load = (tw==='on' || (tw>0 && tw<window.innerWidth))
    }

    if(child) {
        for(i=0;i<o.childNodes.length;i++) {
            S.bind(o.childNodes[i], 'click', ToggleActive);
        }
    }
    if(toggler) {
        if(!el) {
            a={e:'a', a:{'data-target':'#'+id}, p:{className:'s-toggler'},t:{click:ToggleActive}};
            if(storage) a.d = {'toggler-options':'storage'};
            if(sibling) el=S.element(a, null, o);
            if(child) el=S.element.call(o, a);
        } else {
            S.bind(el, 'click', ToggleActive);
            if(el.className.search(/\bs-toggler\b/)<0) el.className += ' s-toggler';
        }
        if(drag) {
            el.draggable=true;
            S.bind(el, 'dragstart', toggleDragStart);
            S.bind(el, 'dragend', toggleDragEnd);
            if(o.getAttribute('data-toggler-drag-target')) el.setAttribute('data-drag-target', o.getAttribute('data-toggler-drag-target'));
            if(o.getAttribute('data-toggler-drag')) el.setAttribute('data-drag', o.getAttribute('data-toggler-drag'));
            if(o.getAttribute('data-draggable-default-style')) el.setAttribute('data-draggable-style', o.getAttribute('data-draggable-default-style'));
        }
    }
    if(load) {
        ToggleActive.call(el);
    }
};

var _drag={}, _dragging, _dragW=-1;
function toggleDragStart(e)
{
    var dt = (this.getAttribute('data-drag-target')) ?document.querySelector(this.getAttribute('data-drag-target')) :null,
        id = this.getAttribute('id');
    if(!dt) dt = this.parentNode;
    if(!dt && e) S.stopEvent(e);
    if(!id) {
        id='_n'+(_got++);
        this.setAttribute('id', id);
    }

    var dp=this.getAttribute('data-drag');
    if(!dp) dp = '#'+id;
    _dragging = dp;

    if(!(dp in _drag)) _drag[dp]={};
    _drag[dp].source = this;
    _drag[dp].area = dt
    _drag[dp].target = dp;
    _drag[dp].enable = true;
    _drag[dp].minWidth = 640;

    S.bind(dt, 'dragover', toggleDragOver);
    if(_dragW<0) {
        _dragW = _onResize.length;
        S.resizeCallback(applyDrag);
    }
}

function toggleDragOver(e)
{
    if(!(_dragging in _drag)) return;
    _drag[_dragging].x = e.clientX;
    _drag[_dragging].y = e.clientY;

    // enable timeout?
    applyDrag();
}

function applyDrag(id)
{
    if(!id) {
        for(id in _drag) {
            applyDrag(id);
        }
        return;
    }

    if(!(id in _drag) || !_drag[id].enable) return;

    var r=_drag[id].area.getBoundingClientRect();
    if(('minWidth' in _drag[id]) && window.innerWidth < _drag[id].minWidth) {
        removeDrag(id);
        _drag[id].enable = true;
        return;
    }

    var x = _drag[id].x - r.x, y = _drag[id].y - r.y, w0=100*_drag[id].x/r.width, w1=99.99-w0,
        L=(_drag[id].target) ?(document.querySelectorAll(_drag[id].target)) :[_drag[id].source], i=L.length, el, ds, s;

    while(i--) {
        el = L[i];
        ds = el.getAttribute('data-draggable-style');
        if(!ds) ds = _drag[id].source.getAttribute('data-draggable-style');
        if(!ds) {
            s = 'width: '+w1+'%';
        } else {
            s = ds.replace('{w0}', w0+'%').replace('{w1}', w1+'%').replace('{x}', x+'px').replace('{y}',y+'px');
        }
        el.setAttribute('style',s);
    }
}

S.resizeCallback=function(fn)
{
    if(fn && (typeof(fn)=='function')) {
        if(_onResize.length==0) {
            S.bind(window, 'resize', S.resizeCallback);
        }
        _onResize.push(fn);

    } else {
        var i=0;
        while(i < _onResize.length) {
            _onResize[i]();
            i++;
        }
    }
}

function removeDrag(id)
{
    if(!id) {
        for(id in _drag) {
            if(_drag[id].enable) removeDrag(id);
        }
        return;
    }
    if(!(id in _drag)) return;
    var L=(_drag[id].target) ?(document.querySelectorAll(_drag[id].target)) :[_drag[id].source], i=L.length;

    while(i--) {
        L[i].removeAttribute('style');
    }
    _drag[id].enable = false;
}

function enableAndApplyDrag(id)
{
    if(!id) {
        for(id in _drag) {
            if(!_drag[id].enable) enableAndApplyDrag(id);
        }
        return;
    }

    _drag[id].enable = true;
    applyDrag(id);
}

S.nodeData=function (el, n, d)
{
    if(!_wm) _wm = new WeakMap();
    var p = _wm.get(el), r=null, l=arguments.length;
    if(l==1) return p;
    if(typeof(n)==='object' && l==2) {
        _wm.set(el, n);
        return p;
    }

    if(p && (n in p)) r = p[n];
    else r = el.getAttribute('data-'+n);

    if(l>2) {
        if(!p) p={};
        p[n] = d;
        _wm.set(el, p);
    }

    return r;
}

function toggleDragEnd(e)
{
    var dt = (this.getAttribute('data-drag-target')) ?document.querySelector(this.getAttribute('data-drag-target')) :null;
    if(!dt) dt = this.parentNode;
    if(!dt && e) S.stopEvent(e);
    S.unbind(dt, 'dragover', toggleDragOver);
    S.resizeCallback();
}


function ToggleActive()
{
    var ts=S.nodeData(this, 'target'), o=S.nodeData(this, 'toggler-options'), t, L, i, M, j;
    if(ts) L=document.querySelectorAll(ts);
    else if(o && o.search(/\bself\b/)>-1) t=this;
    else t=this.previousSibling;
    if(!L || L.length==0) {
        if(t) L = [t];
        else return;
    }
    if(!t) t=this;
    var c=t.getAttribute('data-active-class'), d=t.getAttribute('data-inactive-class'),
        drag=(o && o.indexOf('draggable')>-1) ?true :false,
        storage=(o && o.indexOf('storage')>-1) ?ts :null,
        disableSiblings=(o && o.indexOf('disable-siblings')>-1) ?true :false,
        dontDisable=(o && o.indexOf('do-not-disable')>-1) ?true :false;
    if(!c) c='s-active';
    if(!d && disableSiblings) d='s-inactive';
    var re=new RegExp('\\s*\\b'+c+'\\b', 'g'), rd= (d) ?new RegExp('\\s*\\b'+d+'\\b', 'g') :null, k, st='on';
    i=L.length;
    while(i--) {
        t=L[i];
        if(t.className.search(c)>-1) { // disable
            if(dontDisable) continue;
            t.className = t.className.replace(re, '');
            if(d && t.className.search(rd)<0) t.className += ' '+d;
            if(k=t.getAttribute('data-toggler-cookie-disable')) S.cookie(k, true, null, '/');
            if(k=t.getAttribute('data-toggler-cookie-enable'))  S.cookie(k, null, new Date(2000, 1, 1), '/');
            if(storage) S.storage('s-toggler/'+storage, null);
            if(drag) removeDrag();
            st='off';
        } else { // enable
            if(d) t.className = t.className.replace(rd, '');
            t.className += ' '+c;
            if(disableSiblings) {
                M=t.parentNode.childNodes;
                j=M.length;
                while(j--) {
                    if(M[j]!==t) {
                        M[j].className = M[j].className.replace(re, '');
                        if(M[j].className.search(rd)<0) M[j].className  += ' '+d;
                    }
                }
            }
            if(k=t.getAttribute('data-toggler-cookie-disable')) S.cookie(k, null, new Date(2000, 1, 1),'/');
            if(k=t.getAttribute('data-toggler-cookie-enable'))  S.cookie(k, true, null, '/');
            if(storage) S.storage('s-toggler/'+storage, 1);
            if(drag) enableAndApplyDrag();
            st='on';
        }
        if(k=t.getAttribute('data-toggler-attribute-target')) {
            M=document.querySelectorAll(k);
            j=L.length;
            while(j--) M[i].setAttribute('data-toggler', st);
        }
    }
}

S.disableForm=function(F)
{
    if(F.className.search(/\bs-disabled\b/)>-1) return;
    F.className+=' s-disabled';
    var L=F.querySelectorAll('button,input[type="button"],input[type="submit"]'), i=L.length;
    while(i--) {
        if(L[i].className.search(/\bs-no-disable\b/)>-1) continue;
        L[i].setAttribute('disabled', 'disabled');
        L[i].className += ' s-disabled-input';
    }
}

S.enableForm=function(F)
{
    var L=F.querySelectorAll('.s-disabled-input'), i=L.length;
    while(i--) {
        if(L[i].getAttribute('disabled')) L[i].removeAttribute('disabled');
        if(L[i].className.search(/\bs-disabled-input\b/)>-1) L[i].className = L[i].className.replace(/\s*\bs-disabled-input\b/g, '');
    }
    if(F.className.search(/\bs-disabled\b/)>-1) F.className = F.className.replace(/\s*\bs-disabled\b/g, '');
}

S.initRecaptcha = function()
{
    if(this.className.search(/\bs-recaptcha\b/)>-1) this.className = this.className.replace(/\bs-recaptcha\b/g, 'g-recaptcha');
    if(!('grecaptcha' in window)) S.load('https://www.google.com/recaptcha/api.js?hl='+S.lang());
    else grecaptcha.render(this);
}

S.initAutofocus = function()
{
    this.focus();
}

S.encodeBase64Url=function(s)
{
    return btoa(s).replace('+', '-').replace('/', '_').replace('=', '');
}

S.decodeBase64Url=function(s)
{
    return atob(s.replace('-', '+').replace('_', '/') + '='.repeat(s.length() % 4));
}

S.initAttributeTemplate = function()
{
    var a, A, b;
    if((a=this.getAttribute('data-attr-template')) && (A=JSON.parse(a))) {
        this.removeAttribute('data-attr-template');
        var C={'$URL':S.encodeBase64Url(window.location.pathname+window.location.hash)}, c;
        for(b in A) {
            for(c in C) A[b] = A[b].replace(c, C[c]);
            this.setAttribute(b, A[b]);
        }
    }
}


window.requestAnimFrame = (function(){
  return  window.requestAnimationFrame       ||
          window.webkitRequestAnimationFrame ||
          window.mozRequestAnimationFrame    ||
          function( callback ){
            return window.setTimeout(callback, 1000 / 60);
          };
})();

if (!document.querySelectorAll) {
    document.querySelectorAll = function(selector) {
        var doc = document,
        head = doc.documentElement.firstChild,
        styleTag = doc.createElement('STYLE');
        head.appendChild(styleTag);
        doc.__qsaels = [];

        styleTag.styleSheet.cssText = selector + "{x:expression(document.__qsaels.push(this))}";
        window.scrollBy(0, 0);

        return doc.__qsaels;
    };
}

if(window.Element) {
    (function(ElementPrototype) {
        ElementPrototype.matchesSelector = ElementPrototype.matchesSelector ||
        ElementPrototype.mozMatchesSelector ||
        ElementPrototype.msMatchesSelector ||
        ElementPrototype.oMatchesSelector ||
        ElementPrototype.webkitMatchesSelector ||
        function (selector) {
            var node = this, nodes = (node.parentNode || node.document).querySelectorAll(selector), i = -1;
            while (nodes[++i] && nodes[i] != node);
            return !!nodes[i];
        };
    })(Element.prototype);
}

var matchesSelector = function(node, selector) {
    if(!('parentNode' in node) || !node.parentNode) return false;
    return Array.prototype.indexOf.call(node.parentNode.querySelectorAll(selector)) != -1;
};

initStudio();

})(window.Studio);
if (typeof exports !== 'undefined') {
  if (typeof module !== 'undefined' && module.exports) {
    exports = module.exports = Studio;
  }
}
/*! end S */

// https://github.com/lazd/scopedQuerySelectorShim
(function() {
  if (!HTMLElement.prototype.querySelectorAll) {
    throw new Error('rootedQuerySelectorAll: This polyfill can only be used with browsers that support querySelectorAll');
  }

  // A temporary element to query against for elements not currently in the DOM
  // We'll also use this element to test for :scope support
  var container = document.createElement('div');

  // Check if the browser supports :scope
  try {
    // Browser supports :scope, do nothing
    container.querySelectorAll(':scope *');
  }
  catch (e) {
    // Match usage of scope
    var scopeRE = /^\s*:scope/gi;

    // Overrides
    function overrideNodeMethod(prototype, methodName) {
      // Store the old method for use later
      var oldMethod = prototype[methodName];

      // Override the method
      prototype[methodName] = function(query) {
        var nodeList,
            gaveId = false,
            gaveContainer = false;

        if (query.match(scopeRE)) {
          // Remove :scope
          query = query.replace(scopeRE, '');

          if (!this.parentNode) {
            // Add to temporary container
            container.appendChild(this);
            gaveContainer = true;
          }

          var parentNode = this.parentNode;

          if (!this.id) {
            // Give temporary ID
            this.id = 'rootedQuerySelector_id_'+(new Date()).getTime();
            gaveId = true;
          }

          // Find elements against parent node
          nodeList = oldMethod.call(parentNode, '#'+this.id+' '+query);

          // Reset the ID
          if (gaveId) {
            this.id = '';
          }

          // Remove from temporary container
          if (gaveContainer && this.parentNode) {
            container.removeChild(this);
          }

          return nodeList;
        }
        else {
          // No immediate child selector used
          return oldMethod.call(this, query);
        }
      };
    }

    // Browser doesn't support :scope, add polyfill
    overrideNodeMethod(HTMLElement.prototype, 'querySelector');
    overrideNodeMethod(HTMLElement.prototype, 'querySelectorAll');
  }
}());