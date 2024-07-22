/*! capile/studio Graph v1.2 | (c) 2024 Tecnodesign <ti@tecnodz.com> */
(function()
{

"use strict";

var S, _cids=0, _iT=0, _df=1;

function init()
{
    if(!('Studio' in window)) {
        return setTimeout(init, 100);
    }
    if(!S) S=Studio;
    S.resizeCallback(resizeCalendars);
    var L=document.querySelectorAll('.s-calendar'), i=L.length;
    while(i--) Calendar.call(L[i]);
}

function Calendar()
{
    if(!this.getAttribute('data-calendar') && this.getAttribute('data-calendar-status')!=='pending') {
        this.setAttribute('data-calendar-status', 'init');
        if(_iT) clearTimeout(_iT);
        _iT = setTimeout(initCalendars, 100);
        return;
    }
    if(!S) S=Studio;
    checkGraph.call(this);
    this.removeAttribute('data-calendar-status');
    var ctrl=this.getAttribute('data-calendar-options'), L, i, C, cw;
    if(!ctrl) ctrl='';

    if(this.className.search(/\bs-c-oneline\b/)>-1 || ctrl.search(/\boneline\b/)>-1) {
        C=this.querySelector('.s-c-month');
        cw = parseFloat(getComputedStyle(C).fontSize)*15;
        i = Math.floor(this.offsetWidth / cw);
        if(i===0) i++;
        this.className = this.className.replace(/\s*\\bs-c-w[0-9]+\b/g, '')+' s-c-w'+i;
        this.setAttribute('data-calendar-w', i);
        if(this.querySelector('.s-c-month:nth-child('+(i+1)+')')) {
            if(!this.getAttribute('data-calendar-offset')) {
                var o=this.getAttribute('data-calendar-starts');
                i = 0;
                if(o) {
                    if(C=this.querySelector('#'+o+'.s-c-month')) {
                        while(C=C.previousSibling) {
                            i++;
                        }
                    }
                    this.removeAttribute('data-calendar-starts');

                }
                this.setAttribute('data-calendar-offset', ''+i);
            }
            if(!this.querySelector(':scope > a.s-c-next')) {
                S.element.call(this, [{e:'a',p:{className:'s-c-previous s-api--left'},t:{click:moveCalendar}},{e:'a',p:{className:'s-c-next s-api--right'},t:{click:moveCalendar}}]);
            }
            setTimeout(moveCalendar, 100);
        }
    }

}

function moveCalendar(e)
{
    var L, i, j;
    if(!e) {
        L=document.querySelectorAll('.s-calendar[data-calendar-offset]');
        i=L.length;
        while(i--) moveCalendar.call(L[i], true);
        return;
    }
    var C=this;
    if(e!==true) {
        C=S.parentNode(this, '.s-calendar');
        if(!C || this.classList.contains('s-disabled')) return;
        // change position
        i=(this.classList.contains('s-c-previous')) ?-1 :1;
        C.setAttribute('data-calendar-offset', i+parseInt(C.getAttribute('data-calendar-offset')));
    }


    var M = C.querySelector('.s-c-month'), I=C.childNodes[0], em, B;
    if(!M) I.removeAttribute('style');
    else {
        i = parseFloat(C.getAttribute('data-calendar-offset'));
        if(i===0) {
            C.querySelector(':scope > a.s-c-previous').classList.add('s-disabled');
        } else if(B=C.querySelector(':scope > a.s-c-previous.s-disabled')) {
            B.classList.remove('s-disabled');
        }
        em = parseFloat(getComputedStyle(C).fontSize);
        em = (-1*i*(M.offsetWidth+em));
        M = C.querySelector('.s-c-month:nth-child('+(i+1)+')');
        if(M) {
            var p=M.offsetLeft * -1;
            if(p!=em) em = p;
        }
        I.setAttribute('style', 'left:'+em+'px');
        i += parseInt(C.getAttribute('data-calendar-w')) +1;
        if(!C.querySelector('.s-c-month:nth-child('+i+')')) {
            C.querySelector(':scope > a.s-c-next').classList.add('s-disabled');
        } else if(B=C.querySelector(':scope > a.s-c-next.s-disabled')) {
            B.classList.remove('s-disabled');
        }
    }

}

function initCalendars()
{
    if(!S) S=Studio;
    var L, i, pending=false, E;
    L=document.querySelectorAll('.s-calendar[data-calendar-status]');
    i=L.length;
    while(i--) {
        checkGraph.call(L[i]);
        if(L[i].offsetWidth>0) {
            L[i].setAttribute('data-calendar', 'c'+(_cids++));
            Calendar.call(L[i]);
        } else if(!S.parentNode(L[i], '.s-api-app:not(.s-api-active)')) { // do not startup inactive api screens
            pending = true;
        }
    }
    if(pending && _df>=5) {
        _df = 1;// let it rest
    } else if(pending) {
        if(_iT) clearTimeout(_iT);
        _iT = setTimeout(initCalendars, 200 * (_df++));
    } else {
        _df = 1;
    }
}

function checkGraph()
{
    var E=S.parentNode(this, '.s-graph:not(.s-active)');
    if(E) E.classList.add('s-active');
}

function resizeCalendars()
{
    var L=document.querySelectorAll('.s-calendar.s-c-oneline:not([data-calendar-status])'),i=L.length, pending=false;
    if(_iT) {
        pending = true;
        clearTimeout(_iT);
    }
    while(i--) {
        L[i].setAttribute('data-calendar-status', 'pending');
        L[i].className = L[i].className.replace(/\s*\bs-c-w[0-9]+\b/g, '');
        pending = true;
    }

    if(pending) {
        _iT = setTimeout(initCalendars, 100);
    }
}

// default modules loaded into Studio
window.Studio_Calendar = Calendar
init();
})();