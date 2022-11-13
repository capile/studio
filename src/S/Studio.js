/*! capile/studio Studio v1.0 | (c) 2022 Tecnodesign <ti@tecnodz.com> */
(function() {

"use strict";

var _S, _V, _L, _P, _Q, _Qt, _Qq, _ih={'x-studio-action':'api'}, _Studio='/_studio', _title, _otherRoot;

// load authentication info
function startup()
{
    if(!('Studio' in window) || !('Studio_Api' in window)) {
        return setTimeout(startup, 500);
    }
    if(!('plugins' in Studio)) Studio.plugins={};
    if(!('studio' in Studio.plugins)) {
        Studio.plugins.studio={home:'/_studio', options:{}, load:[] };
    }
    if(!('events' in Z)) Studio.events={};
    Studio.events.unloadInterface = [checkInterfaces];

    if(!('modules' in Z)) Studio.modules = {};
    if(('home' in Studio.plugins.studio) && Studio.plugins.studio.home!=_Studio) {
        _Studio = Studio.plugins.studio.home;
    }
    _P = {c:[],s:[]};

    if(('options' in Studio.plugins.studio) && ('interactive' in Studio.plugins.studio.options) && Studio.plugins.studio.options.interactive) {
        //
        Studio.bind(window, 'click', activateStudio);
    }

    if(('options' in Studio.plugins.studio) && ('button' in Studio.plugins.studio.options) && Studio.plugins.studio.options.button) {
        var ui = [
          {e:'div',p:{id:'studio-viewport',className:'s-api-box'},a:{'base-url':_Studio}},
          {e:'div',p:{className:'studio-logo'}}/*,
          {e:'div',p:{className:'studio-menu'},c:[
            {e:'div',p:{className:'studio-box s-right s-api-actions'},c:[
              {e:'a',p:{className:'s-api--search'}},
              {e:'a',p:{className:'s-api--new'   }},
              {e:'a',p:{className:'s-api--update'}},
              {e:'a',p:{className:'s-api--delete'}}
            ]},
          ]}*/
        ];
        _S = document.getElementById('studio');
        if(!_S) {
            _S = Studio.element.call(document.body, {e:'div',p:{id:'studio',className:'studio-interface'},c:ui});
        } else if(_S.className.search(/\bs-active\b/)<0) {
            var b=_S.querySelector('.s-api-box');
            if(b) {
                ui.shift();
                b.id='studio-viewport';
                b=null;
            }
            Studio.element.call(_S, ui);
            trigger(null, true);
        }
        Studio.bind(_S, 'click', trigger);
        Studio.studioInterface = getInterface;
    }
}

function activateStudio(e)
{
    if(!e || e.detail !== 3) {
        return;
    }
    Studio.stopEvent(e);

    var L=[], i, T=e.target;
    if(T.getAttribute('data-studio')) L.push(T.getAttribute('data-studio'));
    while(T.parentNode && (T=Studio.parentNode(T.parentNode,'*[data-studio]'))) {
        L.push(T.getAttribute('data-studio'));
    }

    if(L.length>0) {
       Studio.ajax(_Studio+'/s', JSON.stringify(L), setStudio, null, 'json', _S, {'x-studio-action':'studio','Content-Type':'application/json'});
    }
}

function getViewport()
{
    if(!_V) _V = document.getElementById('studio-viewport');
    if(_V && !_otherRoot) _otherRoot = Studio.setInterfaceRoot(_V);
    var b=_V.querySelector('.s-api-header');
    if(!b) {
        Studio.element.call(_V,{e:'div',a:{'class':'s-api-header','data-overflow':1}});
        b=Studio.element.call(_V,{e:'div',a:{'class':'s-api-body','data-nav':1}});
        _title = document.title;
    }

    return _V;
}

/*!getinterface*/
function getInterface(u)
{
    if(!_V) getViewport();

    //Studio.loadInterface.call(t, u);
    trigger(null, true);
    Studio.loadInterface.call(_V, u);
}

function addInterface(u)
{
    if(!_V) getViewport();
    var p =(u.indexOf(/\?/)>-1) ?u.substr(0, u.indexOf(/\?/)) :u, 
        qs=(u.indexOf(/\?/)>-1) ?u.substr(u.indexOf(/\?/)+1) :'',
        add={
        '.s-api-body':'s-api-app',
        '.s-api-header':'s-api-title'
        }, n,
        P, I,el={e:'div',a:{'class':'s-api-app', 'data-url':p, 'data-qs':qs, 'data-nav':'1'}};

    for(n in add) {
        I=_S.querySelector('.s-api-box '+n+' .'+add[n]+'[data-url="'+encodeURIComponent(p)+'"]');
        if(!I) {
            P=_S.querySelector('.s-api-box '+n);
            el.a.class = add[n];
            I=Studio.element.call(P, el);
        }
    }
    return I;
}

function loadInterface(u)
{
    if(!_V) getViewport();
    if(u) addInterface(u);
    Studio.loadInterface.call(_V, u);
}

function setStudio(d)
{
    if(d && ('data' in d) && ('length' in d.data) && d.data.length>0) {
        if(!_V) getViewport();
        var L=[], b, c;
        for(var i=0;i<d.data.length;i++) {
            if(d.data[i]) {
                c=d.data[i];
                b=_Studio+'/'+c;
                L.push(b);
                addInterface(b);
                break;// only add the closest interface
            }
        }
        if(c) {
            window.location.hash='!'+c;
            Studio.loadInterface.call(_V, L);
            setTimeout(initStudio, 100);
        }
    }
}

function initStudio()
{
    toggle.call(_S, null, true);
    /*
    var T=_V.querySelector('.s-api-header .s-api-title[data-url]'), h = (T) ?T.getAttribute('data-url') :null, qs = (T) ?T.getAttribute('data-qs') :null;
    if(qs) h+= '?'+qs;
    Studio.loadInterface.call(_V, h);
    */
}

function trigger(e, active)
{
    if(e && ('target' in e)) {
        if(e.target.className.search(/\b(studio-(logo|interface))\b/)<0) {
            return false;
        }
    }
    if(arguments.length>1) toggle.call(_S, e, active);
    else toggle.call(_S, e);
    var on=(_S.className.search(/\bs-active\b/)>-1);
    if(!_S.querySelector('.s-api-app')) {
        // no interface, preview current page, if found
        searchInterface({e:{link:window.location.pathname}});
    }

    /*
    if(!_L) {
        getProperties();
    }
    */
}

function searchInterface(s)
{
    var d, u=_Studio+'/site/list';
    if(typeof(s)=='object') {
        d = JSON.stringify({q:s});
    } else {
        u+='?q='+escape(s);
    }

    loadInterface(u);
}

function toggle(e, active)
{
    var on,h=document.querySelector('html');
    if(arguments.length>1) {
        on=active;
    } else {
        on=(this.className.search(/\bs-active\b/)<0);        
    }
    if(on) {
        if(this.className.search(/\bs-active\b/)<0) {
            this.className=String(this.className+' s-active').trim();
        }
        if(h.className.search(/\bstudio-lock\b/)<0) {
            h.className=String(h.className+' studio-lock').trim();
        }
    } else {
        if(this.className.search(/\bs-active\b/)>-1) {
            this.className=this.className.replace(/\s*\bs-active\b/, '').trim();
        }
        if(h.className.search(/\bstudio-lock\b/)>-1) {
            h.className=h.className.replace(/\s*\bstudio-lock\b/, '').trim();
        }
        if(_title && document.title!=_title) document.title=_title;
    }
    h=null;
}

function checkInterfaces()
{
    if(!document.querySelector('.s-api-body .s-api-app')) {
        toggle.call(_S, null, false);
    }
}

function setContent(c)
{
    this.innerHTML = c;
}

startup();

})();