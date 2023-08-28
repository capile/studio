/*! capile/studio Api v1.0 | (c) 2023 Tecnodesign <ti@tecnodz.com> */
(function()
{
    "use strict";
    var S,
        _is=false,
        _init,
        _cu='/',
        _i=0,
        _sel='.s-api-app[data-url]',
        _root=document,
        _base,
        _toLoadTimeout,
        _toLoad=[],
        _reload={},
        _loading={},
        _loadingTimeout=2000,
        _ids={},
        _prop={},
        _q=[],
        _last,
        _reStandalone=/\bs-api-standalone\b/,
        _msgs=[];

    function startup(I)
    {
        /*jshint validthis: true */
        if(!S) {
            return init();
        }
        if(!('loadInterface' in S)) {
            S.loadInterface = loadInterface;
            S.setInterface = setInterface;
            // run once
            S.bind(window, 'hashchange', hashChange);
            S.resizeCallback(headerOverflow);
        }
        _init = true;
        var i, L, E, B, J, j, a, b, c;
        if(arguments.length===0) {
            if(!(I=S.node(this))) {
                return startup(_root.querySelectorAll(_sel));
            }
        }
        if(!S.node(I) && ('length' in I)) {
            if(I.length===0) return;
            if(I.length===1) I=I[0];
            else {
                for(i=0;i<I.length;i++) startup(I[i]);
                return;
            }
        }
        if(I.getAttribute('data-startup')) return;
        I.setAttribute('data-startup', '1');

        if(E=S.parentNode(I, '.s-api-box[base-url]')) {
            setRoot(E);
            E = null;
        }
        if(_init) S.init(I);
        getBase();
        var base=I.getAttribute('data-base-url');
        if(!base && _base) {
            base = _base;
        }

        // activate checkbox and radio buttons in lists
        var active=(_reStandalone.test(I.className));
        if(E=I.querySelector('.s-api-list')) {
            active = true;
            L=E.querySelectorAll('input[type=checkbox][value],.s-api-list input[type=radio][value]');
            i=L.length;
            while(i-- > 0) if(!L[i].getAttribute('data-no-callback')) S.bind(L[i], 'change', updateInterfaceDelayed);
            E = null;
            L = null;
        }

        if(_reStandalone.test(I.className)) return true;

        if((B=S.parentNode(I, '.s-api-body')) && (E=B.querySelector(':scope > .s-api-nav')) && !E.getAttribute('data-startup')) {
            E.setAttribute('data-startup', '1');
            L=E.querySelectorAll('a[href]');
            i=L.length;
            while(i-- > 0) if(!L[i].getAttribute('target') && !L[i].getAttribute('download')) S.bind(L[i], 'click', loadInterface);
            L=null;
        }
        B = null;
        E = null;

        // bind links to Interface actions
        L=I.querySelectorAll('a[href^="'+base+'"],.s-api-a,.s-api-link');
        i=L.length;
        while(i-- > 0) if(!L[i].getAttribute('target') && !L[i].getAttribute('download')) S.bind(L[i], 'click', (L[i].getAttribute('data-inline-action'))?loadAction :loadInterface);
        L=null;

        // bind forms
        L=I.querySelectorAll('form[action^="'+base+'"],.s-api-preview form');
        i=L.length;
        while(i-- > 0) S.bind(L[i], 'submit', (L[i].parentNode.getAttribute('data-action-schema')) ?loadAction :loadInterface);
        L=null;

        // bind other actions
        L=I.querySelectorAll('*[data-action-schema]');
        if(L.length==0 && I.getAttribute('data-action-schema')) L=[I];

        var iurl = I.getAttribute('data-action');
        iurl = (!iurl)?(''):('&next='+encodeURIComponent(iurl));
        i=L.length;
        while(i-- > 0) {
            J = L[i].querySelectorAll('*[data-action-scope]');
            j = J.length; 
            a = L[i].getAttribute('data-action-schema');
            b = L[i].getAttribute('data-action-url');
            while(j-- > 0) {
                if(c=J[j].getAttribute('data-action-scope')) J[j].removeAttribute('data-action-scope');
                if(!c || c.substr(0, 1)=='_' || J[j].querySelector('.s-api-app')) {
                    c = null;
                    continue;
                }
                if(J[j].nodeName.toLowerCase()==='button') {
                    J[j].setAttribute('data-url', b+'?scope='+c+iurl);
                    J[j].className = ((J[j].className)?(J[j].className+' '):(''))+'s-api--close';
                    if(('form' in J[j])) {
                        S.bind(J[j], 'click', loadAction);
                        if((E=J[j].form) || (E=S.parentNode(J[j], 'form'))) {
                            E = E.parentNode;
                        } else {
                            continue;
                        }
                    } else {
                        continue;
                    }
                } else {
                    E = J[j];
                }
                S.element.call(E, {e:'a',a:{href:b+'?scope='+c+iurl,'class':'s-button s-api--'+a},t:{click:loadAction}});
                E = null;
                c = null;
            }
            J = null;
            j = null;
            a = null;
            b = null;
        }
        L = null;
        // only full interfaces go beyond this point
        if(I.className.search(/\bs-api-app\b/)===-1 || !I.getAttribute('data-url')) {
            return false;
        }

        if(active) {
            updateInterfaceDelayed();
        } else {
            updateInterface();
        }
        if(_noH) {
            if(_cu==I.getAttribute('data-url')) {
                _is = true;
            }
        }
        if(!_toLoadTimeout) activeInterface(I);
        L=_root.querySelectorAll('.s-api-header .s-api-title');
        i=L.length;
        while(i-- > 0) {
            if(!L[i].getAttribute('data-i')) {
                L[i].setAttribute('data-i', 1);
                S.bind(L[i], 'click', activeInterface);
                S.bind(L[i], 'dblclick', loadInterface);
            }
        }
        L = null;
        i = null;

        if(_noH) {
            if(!loading()) _noH = false;
            else return;
        }

        L = _root.querySelectorAll('.s-api-header .s-api-title.s-i-off');
        i = L.length;
        /*
        while(i-- > 0) {
            S.debug('removing off: ', l[i]);
            l[i].parentNode.removeChild(l[i]);
        }
        */
        parseHash(); // sets _H

        if(!_last) {
            // first run, doesn't need to reload current page if in hash
            // reduce _H with currently loaded interface
            i=_H.length;
            while(i-- > 0) {
                a=_H[i];
                if(a.substr(0,1)=='?') a=_base+a;
                else if(a.substr(0,1)!='/') a = _base+'/'+a;
                if(_root.querySelector('.s-api-app[data-url="'+a+'"]')) {
                    _H.splice(i,1);
                }
            }
        }
        if(!_is && _H.length>0) {
            _noH = true;
            for(i=0;i<_H.length;i++) {
                a=_H[i];
                if(a.substr(0,1)=='?') a=_base+a;
                else if(a.substr(0,1)!='/') a = _base+'/'+a;
                loadInterface(a, true);
                _cu = a.replace(/\?.*/, '');
            }
        } else {
            while(_q.length>0) {
                a=_q.shift();
                a.shift().apply(I, a);
            }
            setHashLink();
            _is = true;
        }

        _last = new Date().getTime();
        //if(I.className.search(/\bs-api-active\b/)>-1) reHash();

        if(I.getAttribute('data-ui') || (I.getAttribute('data-url') in _prop)) {
            metaInterface(I);
        }
        return;
    }

    function setRoot(el)
    {
        var A=_root, a=el.getAttribute('base-url');
        if(!a) {
            if(el=S.parentNode(el, '.s-api-box[base-url]')) {
                a=el.getAttribute('base-url');
            } else {
                return false;
            }
        }
        if(a) {
            _root = el;
            _base = a;
        }
        a=null;

        return A;
    }

    function getBase()
    {
        if(!_base) {
            if(!_root) {
                _root = document.querySelector('.s-api-box[base-url]');
                if(_root) _base = _root.getAttribute('base-url');
                else _root = document;
            }
        }
        return _base;
    }

    var _Ht, _Hd=300;
    function hashChange(e)
    {
        if(_Ht) {
            clearTimeout(_Ht);
            _Ht = null;
        }
        if(arguments.length>0) {
            _Ht = setTimeout(hashChange, _Hd);
            return;
        }

        if(!_reHash || !_checkHash) return;
        if(!getBase()) {
            _Ht = setTimeout(hashChange, _Hd);
            return;
        }
        _checkHash = false;

        parseHash();
        // removes any interface that was unloaded by using backspace or messing with the hash
        var i, L=_root.querySelectorAll('.s-api-header .s-api-title[data-url]'), h, U={}, I, last;
        for(i=0;i<_H.length;i++) {
            h=_H[i];
            if(h.substr(0,1)=='?') h=_base+h;
            else if(h.substr(0,1)!='/') h = _base+'/'+h;
            h=h.replace(/\?.*$/, '');
            if(!last) last = h;
            U[h]=i;
        }

        //S.debug('hashChange: hashes found: ', U);
        //S.debug('hashChange: interfaces found: ', L);
         // why this shortcut?

        if(_H.length<=1 && L.length<=1) {
            if(_H.length==1 && L.length==1 && L[0].getAttribute('data-url')!=_H[0]) {
                // continue
            } else {
                _checkHash = true;
                return;
            }
        }

        i=L.length;
        var ni=i;
        for(i=0;i<L.length;i++) {
            h=L[i].getAttribute('data-url');
            if(h in U) {
                delete(U[h]);
            } else {
                I = L[i].parentNode.parentNode.querySelector('.s-api-app[data-url="'+h+'"]');
                if(I) {
                    ni--;
                    if(!ni && _H.length==0) break;

                    _reHash = false;
                    unloadInterface(I, false);
                    _reHash = true;
                }
            }
        }
        for(h in U) {
            //if(_base && h.substr(0,_base.length+1)==_base+'/') h=h.substr(_base.length+1);
            loadInterface(h, true);
        }
        // checks if active interface is correct
        /*
        if(_H.length>1) {
            if(!_root.querySelector('.s-api-box .s-api-title.s-api-title-active[data-url="'+last+'"]')) {
                _reHash = false;
                activeInterface(last);
                _reHash = true;
            }
        }
        */

        _checkHash = true;
    }
    var _H=[], _noH=false, _reHash=true, _checkHash=true;
    function parseHash()
    {
        var h = window.location + '',p=h.indexOf('#!');
        if(p===-1 || h.length<p+2) {
            _H = [];
            return false;
        }
        h=h.substr(p+2);
        _H = h.split(/\,/g);
        return _H;
    }

    function setHash(h)
    {
        if(!_reHash) return;
        if(_noH) {
            if(!loading()) _noH = false;
            else return;
        }
        //S.debug('setHash', h);
        // remove h from _H
        var update=false;
        if(h) {
            if(h.indexOf(',')>-1) h=h.replace(/,/g, '%2C');
            var i=_H.length, hu=h.replace(/\?.*/, '');
            while(i-- > 0) {
                var pu=_H[i].replace(/\?.*/, '');
                if(pu==hu) {
                    _H.splice(i,1);
                    update=true;
                }
            }
        }
        if(h) {
            _H.push(h);
            update = true;
        }

        if(_H.length==1) {
            var I = _root.querySelector('.s-api-active[data-url]'), ch, p;
            if(I) {
                p=I.getAttribute('data-url');
                if(I && p==window.location.pathname) {
                    /*
                    ch = I.getAttribute('data-qs');
                    if(ch) ch = p+'?'+ch;
                    else */
                    ch = p;
                    if(ch.substr(0,_base.length+1)==_base+'/') ch=ch.substr(_base.length+1);
                }
                if(ch==_H[0]) _H=[];//????
                I=null;
                ch=null;
            }
        }

        var s=(_H.length==0)?(''):('!'+_H.join(','));
        if(window.location.hash.replace(/^\#/, '')!=s) {
            window.location.hash=s;
        }
    }

    function reHash()
    {
        if(!_reHash) return;
        //S.debug('reHash');
        var l=_root.querySelectorAll('.s-api-header .s-api-title[data-url]'), i=0,a,h,I, qs;
        _H=[];
        for(i=0;i<l.length;i++) {
            h=l[i].getAttribute('data-url');
            if(!h) continue;
            if((I=_root.querySelector('.s-api-body .s-api-app[data-url="'+h+'"][data-qs]'))) {
                qs = I.getAttribute('data-qs');
                if(qs) h+='?'+I.getAttribute('data-qs');
            }
            if(h.substr(0,_base.length+1)==_base+'/') h=h.substr(_base.length+1);
            if(l[i].className.indexOf(/\bs-api-title-active\b/)>-1)a=h;
            else _H.push(h);
        }
        if(a) _H.push(a);
        setHash(false);
    }

    function setHashLink()
    {
        var i=_H.length, o, hr;
        //S.debug('setHashLink');
        while(i-- > 0) {
            var pu=_H[i].replace(/\?.*/, '');
            if(pu.substr(0,1)!='/') pu=_base+'/'+pu;

            o=_root.querySelector('a.s-api-title[data-url="'+pu+'"]');
            if(o) {
                hr = o.getAttribute('href');
                if(hr!=_H[i]) o.setAttribute('href', (_H[i].substr(0,1)!='/')?(_base+'/'+_H[i]):(_H[i]));
            }
        }
    }

    function unloadInterface(I, rehash, rI)
    {
        //S.debug('unloadInterface', I);
        var u=I.getAttribute('data-url'),
            b=S.parentNode(I, '.s-api-box');
        if(!b) b=document;
        var T=b.querySelector('.s-api-header .s-api-title[data-url="'+u+'"]');
        if(T) {
            T.parentNode.removeChild(T);
            T=null;
        }
        var B = I.previousSibling;
        if(arguments.length>2) {
            I.parentNode.replaceChild(I, rI);
            B = rI;
        } else {
            B = I.previousSibling;
            I.parentNode.removeChild(I);
        }
       S.event(I, 'unloadInterface');
       I=null;
        if(!(I=b.querySelector('.s-api-active[data-url]'))) {
            if(!B) B=b.querySelector('.s-api-app[data-url]');
            activeInterface(B);
        }
        b=null;
        B=null;
        I=null;
        if(arguments.length<2 || arguments[1]) reHash();

        if(_root.querySelector('.s-api-box .s-api-header[data-overflow]')) setTimeout(function() { headerOverflow(true); }, 200);       
    }

    function loadInterface(e, delayed)
    {
        /*jshint validthis: true */
        //S.debug('loadInterface', e, this);
        _init = true;
        var I, m=false, t, q, urls=[], l, i,u,data,h={'x-studio-action':'api'}, ft, method='get',nav=false;
        if(Object.prototype.toString.call(e)=='[object Array]') {
            urls = e;
        } else if(typeof(e)=='string') {
            urls.push(e);
        } else {
            if(e) S.stopEvent(e);
            if(typeof(this)=='undefined') return false;
            if((I=S.parentNode(this, '.s-api-app'))) {
            } else if ((I=S.parentNode(this, '.s-api-title[data-url]'))) {
                I = _root.querySelector('.s-api-app[data-url="'+I.getAttribute('data-url')+'"]');
                if(!I) return true;
            } else if(!S.parentNode(this, '.s-api-nav')) return true;
            if(this.className.search(/\bs-api--close\b/)>-1) {
                if((u=this.getAttribute('href'))) {
                    activeInterface(u);
                }
                unloadInterface(I);
                return false;
            }

            if(_noH) _noH = false;

            var valid=true;
            if(this.className.search(/\bs-api-a-(many|one)\b/)>-1) {
                valid = false;
                if(this.className.search(/\bs-api-a-many\b/)>-1) {
                    m=true;
                    if(I.matchesSelector('.s-api-list-many')) valid = true;
                }
                if(this.className.search(/\bs-api-a-one\b/)>-1) {
                    if(I.matchesSelector('.s-api-list-one')) valid = true;
                }
                if(!valid) {
                    if (m) {
                        msg(S.t('moreRecord'), 's-error');
                    } else {
                        msg(S.t('noRecordSelected'), 's-error');
                    }
                    return false;
                }
            }
            if((t=this.getAttribute('data-url'))) {
                u=t;
                l=_ids[I.getAttribute('data-url')];
                if((q=this.getAttribute('data-qs'))) {
                    t=t.replace(/\?.*/, '');
                    q=(q) ?'?'+q :'';
                    u += q;
                } else q='';
                if(t.indexOf('{id}')>-1) {
                    i=(l.length && !m)?(1):(l.length);
                    while(i-- > 0) urls.push(t.replace('{id}', l[i])+q);
                } else {
                    if(l.length>0) {
                        q+=(q)?('&'):('?');
                        q+='_uid='+l.join(',');
                    }
                    urls.push(t+q);
                }
            } else if((t=this.getAttribute('action'))) {
                u=t;
                if(this.id) ft=this.id;
                if(this.getAttribute('method').toLowerCase()=='post') {
                    method = 'post';
                    var enc=this.getAttribute('enctype');
                    if(enc=='multipart/form-data') {
                        // usually file uploads
                        if('FormData' in window) {
                            data = new FormData(this);
                        }
                        h['Content-Type']=false;
                    } else {
                        h['Content-Type'] = enc;
                    }
                    if(!data) data = S.formData(this);

                    // set index interface to be reloaded
                    var iu = u.replace(/\/[^/]+\/[^/]+(\?.*)$/, ''),
                        ib = S.parentNode(this, '.s-api-box'),
                        ih = (ib)?(ib.querySelector('.s-api-header .s-api--list[data-url^="'+iu+'"]')):(null);
                    if(ih) {
                        _reload[ih.getAttribute('data-url')]=true;
                    }
                    // set z-interface header to the tab interface
                    if(ib=S.parentNode(this, '.s-api-app[data-url]')) {
                        iu = ib.getAttribute('data-url');
                        if(ib.getAttribute('data-qs')) iu += '?'+ib.getAttribute('data-qs');
                        h['x-studio-api'] = iu;
                    }
                } else {
                    t = t.replace(/\?(.*)$/, '')+'?'+S.formData(this, false);
                }
                urls.push(t);
            } else if((t=this.getAttribute('href'))) {
                urls.push(t);
            }
        }
        i=urls.length;
        var o, H, B,SA=((typeof(I)=='object') && ('className' in I) && _reStandalone.test(I.className));
        while(i--) {
            var url = urls[i].replace(/(\/|\/?\?.*)$/, '');
            t=new Date().getTime();
            if(loading(url)) continue;

            if (SA) {
                h['x-studio-api-mode'] = 'standalone';
                o=I;
                B=I;
            } else {
                o=_root.querySelector('.s-api-app[data-url="'+url+'"]');
                if(!o) {
                    o=S.element.call(_root.querySelector('.s-api-body'), {e:'div',a:{'class':'s-api-app s-api-off','data-url':url}});
                }
                if(!_root.querySelector('.s-api-title[data-url="'+url+'"]')) {
                    if(!H) H = _root.querySelector('.s-api-box .s-api-header');
                    if(H) {
                        S.element.call(H, {e:'a',a:{'class':'s-api-title s-api-off','data-url':url,href:urls[i]}});
                    }
                }
                B = S.parentNode(o, '.s-api-body');
            }

            if(delayed) {
                _toLoad.push(urls[i]);
                continue;
            }
            loading(url, true);
            S.blur(B);
            //S.trace('loadInterface: ajax request');
            if(I) {
                h['x-studio-referer'] = I.getAttribute('data-url');
                if(I.getAttribute('data-qs')) h['x-studio-referer'] += '?'+ I.getAttribute('data-qs');
            }

            if(ft && method==='post') {
                o.setAttribute('data-target-id', ft);
                ft=null;
            }

            var hn=h;
            if(o.getAttribute('data-nav')) hn['x-studio-navigation'] = o.getAttribute('data-nav');
            else if('x-studio-navigation' in hn) delete(hn['x-studio-navigation']);

            urlLoader((urls[i].search(/\?/)>-1)?(urls[i].replace(/\&+$/, '')+'&ajax='+t):(urls[i]+'?ajax='+t), data, setInterface, interfaceError, 'html', o, hn);
            o=null;
        }

        if(delayed && _toLoad.length>0) {
            if(_toLoadTimeout) clearTimeout(_toLoadTimeout);
            _toLoadTimeout=setTimeout(loadToLoad, 500);
        }

        if(_urls) setTimeout(urlLoader, 100);
        return false;
    }

    var _urls=[];
    function urlLoader()
    {
        if(arguments.length>0) {
            _urls.push(arguments);
        } else {
            while(_urls.length>0) {
                S.ajax.apply(this, _urls.shift());
            }
        }
    }

    function loadToLoad()
    {
        //S.debug('loadToLoad', _toLoad);
        if(_toLoadTimeout) clearTimeout(_toLoadTimeout);
        while(_toLoad.length>0) {
            loadInterface(_toLoad.shift());
        }
        _toLoadTimeout = null;
    }

    function loading(url, add)
    {
        var t=(new Date()).getTime(), u=(url) ?url.replace(/\?.*$/, '') :null;
        if(arguments.length==0) {
            for(var u in _loading) {
                if(t-_loading[u]<_loadingTimeout) {
                    return true;
                }
            }
            return false;
        } else if(arguments.length==1) {
            if((u in _loading) && t-_loading[u]<_loadingTimeout) return true;
            return false;
        } else if(add) {
            _loading[u] = t;
        } else if(u in _loading) {
            delete(_loading[u]);
        }
    }


    function loadAction(e)
    {
        /*jshint validthis: true */
        var A, B, C, a, b, c, L, i;
        if(typeof(e)=='object' && ('stopPropagation' in e)) {

            var data=null, method='get', h={'x-studio-action': 'api'};
            e.stopPropagation();
            e.preventDefault();
            c = this.nodeName.toLowerCase();
            if(c==='form') {
                A=S.node(S.parentNode(this, '.s-api-scope-block'), this.parentNode);
                if(this.id) A.setAttribute('data-action-expects', 'form#'+S.slug(this.id));
                a=this.getAttribute('action');

                if(this.getAttribute('method').toLowerCase()==='post') {
                    method = 'post';
                    b=this.getAttribute('enctype');
                    if(b==='multipart/form-data') {
                        // usually file uploads
                        if('FormData' in window) {
                            data = new FormData(this);
                        }
                        h['Content-Type']=false;
                    } else {
                        h['Content-Type'] = b;
                    }
                    b = null;
                    if(!data) data = S.formData(this);
                    if(B=S.parentNode(this, '.s-api-app[data-url]')) {
                        b=B.getAttribute('data-url');
                        if(B.getAttribute('data-qs')) b+='?'+B.getAttribute('data-qs');
                        h['x-studio-api'] = b;
                        b=null;
                        B=null;
                    }
                } else {
                    a = a.replace(/\?(.*)$/, '')+'?'+S.formData(this, false);
                }
            } else if(c==='button') {
                A = S.node(S.parentNode(this.form, '.s-api-scope-block'), this.form.parentNode);
                a = this.getAttribute('data-url');
            } else if(this.getAttribute('data-action-item')) {
                A = this;
                a = this.children[this.children.length -1].getAttribute('href');
            } else {
                A = S.node(S.parentNode(this.parentNode, '.s-api-scope-block'), this.parentNode);
                var ss, sn;
                if(this.href && (L=this.href.match(/[\?\&](scope=[^\&]+)/)) && L.length>0) {
                    b = new RegExp('\b'+L[1].replace('=', '-')+'\b');
                }
                if(!b || A.className.search(b)===-1) {
                    while(A && A.parentNode.className.search(/\bs-api-scope-block\b/)!==-1) {
                        A = A.parentNode;
                        if(b && A.className.search(b)!==-1) break;
                    }
                }
                b = null;
                a = this.getAttribute('href');
            }
            b = new Date().getTime();
            if(B=S.parentNode(A, '.s-api-app[data-url].s-api-active')) {
                h['x-studio-referer'] = B.getAttribute('data-url');
                if(B.getAttribute('data-qs')) h['x-studio-referer'] += '?'+ B.getAttribute('data-qs');
            }
            B = null;

            a=(a.search(/\?/)>-1)?(a.replace(/\&+$/, '')+'&ajax='+b):(a+'?ajax='+b);
            //S.trace('loadAction: ajax request');
            S.blur(A);
            S.ajax(a, data, loadAction, interfaceError, 'html', A, h);
        } else {
            //S.trace('loadAction: ajax response start');
            A = document.createElement('div');
            var expects=this.getAttribute('data-action-expects'), expectsUrl=this.getAttribute('data-action-expects-url');
            A.innerHTML = e;
            B = S.parentNode(this, '.s-api-app');

            if(expects && !A.querySelector(expects)) {
                return setInterface.apply(B, arguments);
            } else if(expectsUrl && !A.querySelector('.s-api-app[data-url="'+expectsUrl+'"]')) {
                return setInterface.apply(B, arguments);
            }

            runActions(A);

            if((C = A.querySelector('.s-api-app[data-url] .s-api-preview'))
                || (C = A.querySelector('.s-api-app[data-url] .s-api-container'))
                || (C = A.querySelector('.s-api-app[data-url]'))) {
                if(B) {
                    L=B.querySelectorAll('.s-api-summary .s-msg,.s-msg[data-message],.s-api-msg[data-message]');
                    B = null;
                    i=L.length;
                    while(i--) {
                        B = L[i];
                        if(B.parentNode.className.search(/\b(td)?s-msg\b/)>-1) B = B.parentNode;
                        S.deleteNode(B);
                        B = null;
                    }
                }
                // get s-api-app only
                if(C.children.length==1) {
                    B=C.children[0];
                    B.className=this.className;
                    if(B.className.search(/\bs-api-scope-block\b/)===-1) {
                        B.className+=' s-api-scope-block';
                    }
                    this.parentNode.replaceChild(B, this);
                } else {
                    B=this.parentNode.insertBefore(document.createElement('div'), this);
                    B.className='s-api-scope-block';
                    this.parentNode.removeChild(this);
                    i=0;
                    while(i<C.children.length) {
                        B.appendChild(C.children[i]);
                        i++;
                    }
                }
                if(expectsUrl) t.setAttribute('data-action-expects-url', expectsUrl);
            }
            C = null;
            if(B) {
                L = A.querySelectorAll('.s-api-summary .s-msg');
                i = L.length;
                C=B;
                while(i--) {
                    L[i].setAttribute('data-message', 1);
                    C.parentNode.insertBefore(L[i], C);
                    C=L[i];
                }
                C = null;
            } else {
                B = S.node(this);
            }
            if(B) {
                startup(B);
                S.focus(B);
                B = null;
            }
        }

        return false;
    }

    function activeInterface(I)
    {
        /*jshint validthis: true */
        var u, qs, H;
        if(!I || typeof(I)=='string' || !S.isNode(I)) {
            if(typeof(I)=='string') {
                u = I;
                if(u.indexOf('?')) {
                    qs = u.substr(u.indexOf('?')+1);
                    u=u.substr(0, u.indexOf('?'));
                }
                I = _root.querySelector('.s-api-app[data-url="'+u+'"]');
            } else {
                if(I && ('stopPropagation' in I)) {
                    I.stopPropagation();
                    I.preventDefault();
                    // click events reload the interface
                    I=S.node(this);
                    if(I && (u=I.getAttribute('data-url')) && (u in _reload)) {
                        delete(_reload[u]);
                        qs = I.getAttribute('data-qs');
                        if(!qs && I.getAttribute('href')) {
                            u=I.getAttribute('href');
                        }
                        I=null;
                    }
                } else {
                    I=S.node(this);
                }
            }
        }
        if(I) {
            u=I.getAttribute('data-url');
            if(u) H = _root.querySelector('.s-api-title[data-url="'+u+'"]');
            if(I==H) I = _root.querySelector('.s-api-app[data-url="'+u+'"]');
        }
        if(!I && !u) {
            // get u from hash?
            return false;
        } else if(!I) {
            //S.debug('activeInterface: '+u);
            loadInterface((qs)?(u+'?'+qs):(u));
            return false;
        } else if(!_reStandalone.test(I.className)) {
            if(!S.isNode(S.parentNode(I, '.s-api-body'))) {
                //S.debug('activeInterface(2): '+u);
                loadInterface((qs)?(u+'?'+qs):(u));
                return false;
            }
            if(I.className.search(/\bs-api-active\b/)===-1) I.className += ' s-api-active';
            if(H && H.className.search(/\bs-off\b/)>-1) H.className = H.className.replace(/\s*\bs-off\b/, '');
            if(H && H.className.search(/\bs-api-title-active\b/)===-1) H.className += ' s-api-title-active';
            if(_is) {
                reHash();
            }
            var R = _root.querySelectorAll('.s-api-title-active,.s-api-active'),i=R.length;
            while(i-- > 0) {
                if(R[i]==H || R[i]==I) continue;
                R[i].className = R[i].className.replace(/\bs-api-(title-)?active\b\s*/g, '').trim();
            }
            var txt = S.text(H);
            if(!txt) {
                for(var i=1;i<3;i++) {
                    if(txt=S.text(I.querySelector('h'+i))) break;
                }
            }
            if(txt && txt.trim()) document.title = txt;
        }

        var N = S.parentNode(I, '.s-api-body').querySelector(':scope > .s-api-nav'), nb;
        if(N && (nb = N.getAttribute('data-base-url'))) {
            R=N.querySelectorAll('a.s-current[href]');
            i=R.length;
            while(i--) R[i].className = R[i].className.replace(/\s*\bs-current\b/g, '');
            if(u && u.substr(0, nb.length+1)==nb+'/') {
                if((i=u.indexOf('/', nb.length+1))) u = u.substr(0, i);
                if((N=N.querySelector('a[href="'+u+'"]'))) N.className = String(N.className+' s-current').trim();
            }
        }


        checkMessages(I);

        updateInterface(I);

        if(_root.querySelector('.s-api-box .s-api-header[data-overflow]')) headerOverflow(true);
        if(I.style) I.removeAttribute('style');
        S.resizeCallback();

        return false;
    }

    function headerOverflow(timeout)
    {
        // flow & reflow tabs
        var He = _root.querySelector('.s-api-box .s-api-header[data-overflow]');
        if(!He) return;

        var box=S.parentNode(He, '.s-api-box'),
            Hs = box.querySelectorAll('.s-api-header > .s-api-title'),
            H =  box.querySelector('.s-api-header > .s-api-title.s-api-title-active'),
            ew, fw=0, ws={}, i, wmax, hw, el;

        i=Hs.length;
        if(H && i) {
            // remove all styles
            hw = He.clientWidth;
            if(el=He.querySelector(':scope > .s-spacer')) hw -= el.clientWidth;

            wmax = hw * 0.5;

            while(i--) {
                if(Hs[i].getAttribute('style')) Hs[i].setAttribute('style', '');
                el = Hs[i].querySelector('.s-text');
                if(!el) el = Hs[i];
                ew = el.clientWidth;
                Hs[i].setAttribute('style', 'max-width: '+ew+'px');
                //if(ew > wmax) ew = wmax;
                fw += ew;
                ws[i] = ew;
            }

            i=Hs.length;
            // check length
            if(i>1 && fw > hw) {
                if(He.className.search(/\bs-overflow\b/)===-1) He.className += ' s-overflow';
                // flex:1 -- only the selected tab should be resized
                el = H.querySelector('.s-text');
                if(!el) el = H;
                ew = el.clientWidth;
                if(ew > wmax) el= wmax;
                H.setAttribute('style', 'flex: 2; width: '+ew+'px; max-width: '+ew+'px');
            } else {
                if(He.className.search(/\bs-overflow\b/)>-1) He.className = He.className.replace(/\s*\bs-overflow\b/g, '');
            }
        }


    }

    function checkMessages(I)
    {
        var A=(!I || !S.isNode(I))?(_root.querySelector('.s-api-active .s-api-summary')):(I.querySelector('.s-api-summary'));
        if(!A) return;
        var i=_msgs.length, now=(new Date()).getTime(), next=0, L=A.querySelectorAll(':scope > .s-msg[data-created],:scope > .s-msg'), timeout=5000, last=(L.length>0)?(L[L.length-1]):(null), el;
        while(i--) {
            if(_msgs[i].e < now || !_msgs[i].n.parentNode) {
                if(_msgs[i].n) {
                    el = _msgs[i].n;
                    if(el.parentNode && el.parentNode.className=='s-msg') el=el.parentNode;
                    S.deleteNode(el);
                }
                _msgs[i].n=null;
                _msgs.splice(i, 1);
            } else if(S.parentNode(_msgs[i].n, '.s-api-summary')!=A) {
                if(last) {
                    last = S.element({e:'div',p:{className:'s-msg'},c:[_msgs[i].n]}, null, last);
                } else {
                    last = S.element.call(A, {e:'div',p:{className:'s-msg'},c:[_msgs[i].n]});
                }
                if(!next || next>_msgs[i].e) next=_msgs[i].e;
            }
        }
        last = null;
 
        i=L.length;
        while(i--) {
            var d=now + timeout;
            L[i].removeAttribute('data-created');
            if(L[i].childNodes.length>0) {
                _msgs.push({e: d, n: L[i]});
                if(!next) next=d;
            } else {
                L[i].parentNode.removeChild(L[i]);
            }
        }

        if(next) {
            setTimeout(checkMessages, next - now + 100);
        }
    }

    function parseResponse(d, req)
    {
        var h=req.getAllResponseHeaders(), c=h.match(/content-type: [^\;]+;\s*charset=([^\s\n]+)/i);
        if(c && c.length>1 && c[1].search(/^utf-?(8|16)$/i)===-1) {
            //S.debug('decode from '+c[1], d, escape(d));
            d =  decodeURIComponent(escape(d));
        }
        return d;
    }

    function setInterface(c)
    {
        /*jshint validthis: true */
        if(!_base) {
            getBase();
        }
        if(c) {
            if(arguments.length>=4 && arguments[1]==200) {
                c=parseResponse(c, arguments[3]);
            }
            var f = document.createElement('div'), O=S.node(this),box=(O)?(S.parentNode(O, '.s-api-box')):(null), ft, I;
            if(!box) box=_root;

            f.innerHTML = c;

            if(O && (ft=O.getAttribute('data-target-id'))) {
                O.removeAttribute('data-target-id');
                var from=document.getElementById(ft), to=f.querySelector('#'+ft), fromI;
                if(from && to && (I=S.parentNode(from, '.s-api-app'))) {
                    from.parentNode.replaceChild(to, from);
                    I.removeAttribute('data-startup');
                    if(O.parentNode) S.deleteNode(O);
                    O=I;
                }
            }

            runActions(f);
            var r, i, mv, L;

            if(!I) I = f.querySelector('.s-api-app');
            if(I && box && !box.querySelector('.s-api-body') && f.querySelector('.s-api-body')) {
                // replace entire box and startup
                S.removeChildren(box);
                if(mv = f.querySelector('.s-api-header')) box.appendChild(mv);
                mv = f.querySelector('.s-api-body');

                box.appendChild(mv);

                parseHash();
                startup(I);
                S.init(box);
                S.focus(mv);
                return;
            } else if(!box.querySelector('.s-api-body .s-api-nav') && (mv=f.querySelector('.s-api-body .s-api-nav'))) {
                //add nav
                var B=box.querySelector('.s-api-body');
                if(B.children.length==0) B.appendChild(mv);
                else B.insertBefore(mv, B.children[0]);
                mv.setAttribute('data-startup', '1');
                L=mv.querySelectorAll('a[href]');
                i=L.length;
                while(i-- > 0) if(!L[i].getAttribute('target') && !L[i].getAttribute('download')) S.bind(L[i], 'click', loadInterface);
                L=null;
                S.initToggleActive(mv);
                L=mv.querySelectorAll('.s-toggle-active');
                i=L.length;
                while(i-- > 0) S.initToggleActive(L[i]);
                mv=f.querySelector('.s-api-header .s-spacer');
                if(mv) {
                    B=box.querySelector('.s-api-header');
                    if(B.children.length==0) B.appendChild(mv);
                    else B.insertBefore(mv, B.children[0]);
                }
            }

            if(!I) {
                if(O) {
                    S.focus(box.querySelector('.s-api-body'));
                } else {
                    S.focus(_root.querySelector('.s-api-body.s-blur'));
                }
                return false;
            }

            var u = I.getAttribute('data-url'), cu=(O)?(O.getAttribute('data-url')):(null), A;

            if(O && (A=O.querySelector('.s-api-summary'))) {
                r=A.querySelectorAll(':scope .s-msg');
                i=r.length;
                while(i--) {
                    S.deleteNode(r[i]);
                }
            }

            if(cu) loading(cu, false);

            if(I!==O) {
                var H = box.querySelector('.s-api-header'),
                    Hs = f.querySelectorAll('.s-api-header > .s-api-title'),
                    h;

                if(u && u.substr(0, _base.length)!=_base) {
                    var rbox=f.querySelector('.s-api-box[base-url]');
                    if(rbox) I.setAttribute('data-base-url', rbox.getAttribute('base-url'));
                    rbox=null;
                }

                // check if requested interface was not returned (but a different one)
                if(cu && (!u || u!=cu)) {
                    // remove cu from body and hash
                    O=box.querySelector('.s-api-app[data-url="'+u+'"]');
                    if(!O) O=this;
                    if(H) {
                        r=H.querySelectorAll('.s-api-title[data-url="'+cu+'"]');
                        i=r.length;
                        while(i--) {
                            r[i].parentNode.removeChild(r[i]);
                        }
                    }
                    r=box.querySelectorAll('.s-api-app[data-url="'+cu+'"]');
                    i=r.length;
                    while(i--) {
                        if(r[i]!=O) {
                            r[i].parentNode.removeChild(r[i]);
                        }
                    }

                    if(_reHash) {
                        var ch=_checkHash;
                        _checkHash=false;
                        reHash();
                        _checkHash=ch;
                    } else {
                        setTimeout(reHash, 500);
                    }
                }
                i = I.attributes.length;
                while(i--) {
                    if(I.attributes[i].name.search(/^data-/)>-1) {
                        O.setAttribute(I.attributes[i].name, I.attributes[i].value);
                    }
                }
                i = Hs.length;
                while(i-- > 0) {
                    cu=Hs[i].getAttribute('data-url');
                    h=H.querySelector('.s-api-title[data-url="'+cu+'"]');
                    //S.bind(Hs[i], 'click', activeInterface);
                    if(!Hs[i].querySelector('*[data-action="close"]')) {
                        S.element.call(Hs[i], {e:'span',a:{'class':'s-api-a s-api--close','data-action':'close'},t:{click:loadInterface}});
                    }
                    if(h) H.replaceChild(Hs[i], h);
                    else if(cu==u) H.appendChild(Hs[i]);
                    h=null;
                }

                if(!O || !O.parentNode) {
                    O=box.querySelector('.s-api-app[data-url="'+u+'"]');
                }
                if(O) {
                    O.parentNode.replaceChild(I, O);
                } else {
                    box.querySelector('.s-api-body').appendChild(I);
                }
            } else {
                // copy elements from summary
                if(S && (r=f.querySelectorAll('.s-api-app .s-api-summary .s-msg'))) {
                    i=r.length;
                    while(i--) {
                        S.appendChild(r[i]);
                    }
                }
                if(!I.parentNode && box) {
                    box.querySelector('.s-api-body').appendChild(I);
                }
            }

            startup(I);
            S.focus(S.parentNode(I, '.s-api-body'));
            S.event(I, 'loadInterface');
        }
        return false;
    }

    function runActions(el)
    {
        var r = el.querySelectorAll('a[data-action]'), i=r.length, ra;
        while(i-- > 0) {
            ra = r[i].getAttribute('data-action');
            if(ra && (ra in _A)) {
                _A[ra].call(this, r[i]);
            }
            if(r[i].parentNode) {
                if(r[i].parentNode.className.search(/\bs-msg\b/)>-1 && r[i].parentNode.children.length==1) r[i].parentNode.parentNode.removeChild(r[i].parentNode);
                else r[i].parentNode.removeChild(r[i]);
            }
        }
    }

    var _A = {
        unload:function(o) {
            var 
              ru = (typeof(o)=='string')?(o):(o.getAttribute('data-url')),
              rn = _root.querySelector('.s-api-box .s-api-header .s-api-title[data-url="'+ru+'"]');
            if(rn) rn.parentNode.removeChild(rn);
            rn = _root.querySelector('.s-api-box .s-api-body .s-api-app[data-url="'+ru+'"]');
            if(rn) rn.parentNode.removeChild(rn);
            S.event(rn, 'unloadInterface');
        },
        status:function(o) {
            var pid = o.getAttribute('data-status');
            if(!pid) return;
            _bkg[pid] = {u:o.getAttribute('data-url'),m:o.getAttribute('data-message')};
            msg(_bkg[pid].m, null, true);
            S.delay(msg, 5000, 'msg');
            S.delay(checkBkg, 2000, 'checkBkg');
        },
        message:function(o) {
            if(o.getAttribute('data-message')) {
                msg(o.getAttribute('data-message'), 's-msg', true);
                S.delay(msg, 10000, 'msg');
            }
        },
        success:function(o) {
            if(o.getAttribute('data-message')) {
                msg(o.getAttribute('data-message'), 's-msg-success', true);
                S.delay(msg, 5000, 'msg');
            }
        },
        error:function(o) {
            if(o.getAttribute('data-message')) {
                msg(o.getAttribute('data-message'), 's-msg-error', true);
                S.delay(msg, 5000, 'msg');
            }
            S.event(o, 'error');
        },
        download:function(o) {
            if(o.getAttribute('data-message')) {
                msg(o.getAttribute('data-message'), null, true);
                S.delay(msg, 5000, 'msg');
            }
            var u = o.getAttribute('data-url') || o.getAttribute('href');
            if(!u) return false;
            var d=o.getAttribute('data-download') || '';

            var link = document.createElement("a");
            link.setAttribute('download',d);
            link.target = "_blank";
            link.href = u;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            link=null;
        },
        load:function(o) {
            if(o.getAttribute('data-message')) {
                msg(o.getAttribute('data-message'), null, true);
                S.delay(msg, 5000, 'msg');
            }
            var u = o.getAttribute('data-url') || o.getAttribute('href');
            if(!u) return false;
            S.setInterface(u);
        },
        redirect:function(o) {
            if(o.getAttribute('data-message')) {
                msg(o.getAttribute('data-message'), null, true);
                S.delay(msg, 5000, 'msg');
            }
            var u = o.getAttribute('data-url') || o.getAttribute('href');
            var su=window.location.pathname+window.location.search+window.location.hash;
            if(u.indexOf('{url}')>-1) u=u.replace(/\{url\}/g, encodeURIComponent(su));
            if(u.indexOf('{surl}')>-1) u=u.replace(/\{surl\}/g, encodeURIComponent(btoa(su)));
            if(!u) return false;
            var t=o.getAttribute('data-target') || o.getAttribute('target');
            if(t) {
                window.open(u, t).focus();
            } else {
                window.location.href=u;
            }
        }
    };

    function msg(s, c, html)
    {
        var M=_root.querySelector('.s-api-app.s-api-active .s-msg');
        if(!M) {
            var I = _root.querySelector('.s-api-active .s-api-summary');
            if(!I) I = _root.querySelector('.s-api-active .s-api-container');
            if(!I) I = _root.querySelector('.s-api-active');
            if(!I) return;
            if(I.children.length>0) M=S.element({e:'div',p:{className:'s-msg'}}, I.children[0]);
            else M=S.element.call(I, {e:'div',p:{className:'s-msg'}});
        }
        if(!c) c='';
        else c+=' ';
        c+='s-msg';
        if(s) {
            c+=' s-m-active';
        } else {
            s=null;
            c+=' s-m-inactive';
        }
        if(M.className!=c)M.className=c;
        if(arguments.length>2 && html) {
            M.innerHTML=s;
        } else {
            M.textContent=s;
        }
        S.init(M);
        //S.element.call(M, {c:s});
    }

    var _bkg={};
    function checkBkg()
    {
        var n;
        for(n in _bkg) {
            S.ajax(_bkg[n].u, null, setInterface, interfaceError, 'html', _root.querySelector('.s-api-app.s-api-active'), {'x-studio-action':'api', 'x-studio-param':n});
            delete(_bkg[n]);
        }

    }

    function interfaceError(d, status, url, x)
    {
        /*jshint validthis: true */
        var mid = 'Error';
        if(status) mid += String(status);
        var m=S.t(mid);
        if(!m || m==mid) m=S.t('Error');
        S.error.call(this, m);
        msg(m, 's-msg-error');
        S.delay(msg, 10000, 'msg');
        S.focus(_root.querySelector('.s-api-body.s-blur'));
        if(this.className.search(/\bs-off\b/)>-1) S.deleteNode(this);
    }

    function updateInterfaceDelayed(e)
    {
        /*jshint validthis: true */
        if(arguments.length>0) e.stopPropagation();
        if(S.isNode(this) && 'checked' in this) S.checkInput(this, null, false);
        S.delay(updateInterface, 100);

    }

    function updateInterface(I)
    {
        var ref=(arguments.length>0 && S.isNode(I)),
            isel='.s-api-list input[name="uid[]"][value]:checked', 
            L,
            i,
            tI,
            id,
            tr,
            cn;

        if(ref && (I.getAttribute('data-id')) && (id=I.getAttribute('data-url'))) {
            _ids[id] = [I.getAttribute('data-id')];
        } else {
            L = _root.querySelectorAll('.s-api-active'+_sel+', .s-api-standalone');
            i=L.length;
            while(i--) {
                id=L[i].getAttribute('data-url');
                _ids[id] = [];
            }

            L = _root.querySelectorAll('.s-api-active'+_sel+' '+isel+', .s-api-standalone '+isel);
            i=L.length;
            while(i--) {
                if(!(tI=S.parentNode(L[i], '.s-api-app'))) continue;
                id=tI.getAttribute('data-url');
                if(!(id in _ids)) _ids[id] = [];
                _ids[id].push(L[i].value);
                if((tr=S.parentNode(L[i], 'tr:not(.on)'))) {
                    tr.className += ' on';
                }
            }
        }

        for(id in _ids) {
            if((tI=_root.querySelector(_sel+'[data-url="'+id+'"]'))) {
                cn=tI.className.replace(/\bs-api-list-(none|one|many)\b\s*/g, '').trim();
                i=_ids[id].length;
                if(i==0 && tI.getAttribute('data-id')) i=1;
                if(i==0) cn += ' s-api-list-none';
                else if(i==1) cn += ' s-api-list-one';
                else if(i>1) cn+= ' s-api-list-many';
                if(tI.className!=cn)tI.className=cn;
            } else {
                delete(_ids[id]);
            }
        }
    }

    function metaInterface(I)
    {
        var u=I.getAttribute('data-url'), s, p;
        if(!u) return;
    }

    function removeDashboard()
    {

    }


    function initAutoRemove()
    {
        if(!this.querySelector('.s-api--close')) {
            var el=S.element.call(this, {e:'i',p:{className:'s-api--close s-api-a s-round'},t:{click:autoRemove}});
            if(el.previousSibling.nodeName.toLowerCase()=='a' && !el.previousSibling.getAttribute('href')) S.bind(el.previousSibling, 'click', autoRemove);
            var P=S.parentNode(this,'.s-api-field,.field');
            if(P) P.className+=' has-auto-remove';
        }
    }

    function autoRemove(e)
    {
        if(e) S.stopEvent(e);
        var P=S.parentNode(this, '.has-auto-remove');
        destroyParents.call(this);
        if(P) P.className = P.className.replace(/\s*\bhas-auto-remove\b/g, '');
    }

    function destroyParents(e)
    {
        if(e) S.stopEvent(e);
        var P=this.parentNode.parentNode, nP;
        this.parentNode.parentNode.removeChild(this.parentNode);
        while(P && P.children.length==0) {
            nP = P.parentNode;
            nP.removeChild(P);
            P=nP;
        }
        return false;
    }

    function init()
    {
        if(!('Studio' in window)) {
            return setTimeout(init, 100);
        }
        if(!S || S!==window.Studio) S=window.Studio;
        S.loadInterface = loadInterface;
        S.setInterface = setInterface;
        S.setInterfaceRoot = setRoot;
        window.Studio_Api = startup;
        window.Studio_Api_AutoRemove = initAutoRemove;
    }

    init();

})();