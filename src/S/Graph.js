/*! capile/studio Graph v1.0 | (c) 2022 Tecnodesign <ti@tecnodz.com> */
(function()
{

"use strict";

var Z, _G={}, _gids=0, _gT, _c;

function init()
{
    if(!('Studio' in window)) {
        return setTimeout(init, 500);
    }
    if(!Z) Z=Studio;
}
function Graph(o)
{
    var n=Z.node(o, this), d, D, id;
    if(!n || n.className.search(/\bs-active\b/)>-1) return;
    n.className += ' s-active';
    if(!(id=n.id)) {
        id='_gid'+_gids++;
        n.id=id;
    }
    _G[id]=null;
    if(_gT) clearTimeout(_gT);
    _gT=setTimeout(buildGraph, 100);
}

function buildGraph(id)
{
    if(!id) {
        for(var s in _G) {
            if(_G[s]) _G[s]=null;
            buildGraph(s);
        }
        return;
    }

    var n=document.getElementById(id), d=(n) ?n.getAttribute('data-g') :null, D=(d) ?JSON.parse(atob(d)) :null;

    if(!D) return;
  	D.bindto = '#'+id;
    if('data' in D) D.data.onclick = graphInteraction;
    if('format' in D) {
    	if(!('axis' in D)) D.axis={};
    	if(!('y' in D.axis)) D.axis.y={};
    	if(!('tick' in D.axis.y)) D.axis.y.tick={};
    	D.axis.y.tick.format = d3.format(D.format);
    }
    _G[id]=bb.generate(D);
    if(!_c) {
        Z.resizeCallback(checkGraph);
        _c = true;
    }
}

function checkGraph()
{
    var n, el, I;
    for(n in _G) {
        if(_G[n] && ('element' in _G[n]) && (el=_G[n].element) && (I=Z.parentNode(el, '.s-api-app'))) {
            if(I.className.search(/\bs-api-active\b/)>-1) {
                _G[n].flush();
            }
        } else if(_G[n] && ('resize' in _G[n]) && typeof(_G[n].resize)==='function') {
            _G[n].resize();
        }
    }
}

function graphInteraction(d, el)
{
    if(Z.parentNode(el, '.s-api-app.s-api-active')) {
        return graphInterface(d, el);
    }
}

function graphInterface(d, el)
{
    var I=Z.parentNode(el, '.s-api-app.s-api-active'), O=I.querySelector('#omnibar'), G=Z.parentNode(el, '.s-graph[data-title]'), n=('name' in d) ?d.name :null;
    if(!O || !G || !n) return;

    var t=Z.slug(G.getAttribute('data-title')),m=null;
    if((t in O.form)) {
        if('multiple' in O.form[t]) m=O.form[t].multiple;
    } else {
        return;
    }
    if(t) {
        t+=':';
    }

    if(n.indexOf(' ')>-1) n = '"'+n.replace(/\"/g, '\\\"')+'"';
    t+=n;

    if(O.value.indexOf(t)<0) {
        if(!m && O.value) O.value+=' '+t;
        else O.value = t;
        Z.fire(O, 'change');
        Z.fire(O.form, 'submit');
    }
}

// default modules loaded into Z
window.Studio_Graph = Graph
init();

})();
