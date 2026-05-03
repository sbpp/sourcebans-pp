/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2024 by SourceBans++ Dev Team

The SourceBans++ Web panel is licensed under a
Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

You should have received a copy of the license along with this
work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.

Vanilla replacement for the original MooTools-based contextMenoo. Keeps
the same `contextMenoo` constructor + `AddContextMenu()` global so that
existing template wiring (admin.bans / admin.comms server tooltips)
keeps working without changes.
*************************************************************************/

(function (global) {
    'use strict';

    function contextMenoo(opts) {
        const options = Object.assign({
            selector: '.contextmenu',
            className: 'protoMenu',
            pageOffset: 25,
            fade: false,
            headline: 'Menu',
            menuItems: [],
        }, opts || {});

        let cont = null;

        const build = () => {
            const c = document.createElement('div');
            c.className = options.className;
            c.style.position = 'absolute';
            c.style.display = 'none';
            c.style.zIndex = '10000';
            c.style.opacity = '0';
            if (options.headline) {
                const head = document.createElement('b');
                head.className = 'head';
                head.innerHTML = options.headline;
                c.appendChild(head);
            }
            options.menuItems.forEach((item) => {
                if (item.separator) {
                    const sep = document.createElement('div');
                    sep.className = 'separator';
                    c.appendChild(sep);
                    return;
                }
                const a = document.createElement('a');
                a.href = '#';
                a.title = item.name;
                if (item.disabled) a.className = 'disabled';
                a.innerHTML = item.name;
                a.addEventListener('click', (e) => {
                    e.preventDefault();
                    hide();
                    if (!item.disabled && typeof item.callback === 'function') item.callback();
                });
                c.appendChild(a);
            });
            document.body.appendChild(c);
            return c;
        };

        const hide = () => { if (cont) cont.style.opacity = '0'; if (cont) cont.style.display = 'none'; };

        document.addEventListener('click', hide);
        document.addEventListener('contextmenu', (e) => {
            // Only hide if the contextmenu happened outside one of our targets.
            if (!e.target.closest(options.selector)) hide();
        });

        document.querySelectorAll(options.selector).forEach((el) => {
            el.addEventListener('contextmenu', (e) => {
                e.preventDefault();
                e.stopPropagation();
                if (!cont) cont = build();
                const w = window.innerWidth;
                const h = window.innerHeight;
                const r = cont.getBoundingClientRect();
                const left = (e.pageX + r.width + options.pageOffset > w + window.scrollX)
                    ? (w + window.scrollX - r.width - options.pageOffset)
                    : e.pageX;
                const top = (e.pageY - window.scrollY + r.height > h && (e.pageY - window.scrollY) > r.height)
                    ? (e.pageY - r.height)
                    : e.pageY;
                cont.style.left = left + 'px';
                cont.style.top  = top  + 'px';
                cont.style.display = 'block';
                if (options.fade) {
                    requestAnimationFrame(() => { cont.style.transition = 'opacity 200ms'; cont.style.opacity = '1'; });
                } else {
                    cont.style.opacity = '1';
                }
            });
        });
    }

    global.contextMenoo = contextMenoo;

    global.AddContextMenu = function (select, classNames, fader, headl, oLinks) {
        const wire = () => new contextMenoo({
            selector: select,
            className: classNames,
            fade: fader,
            menuItems: oLinks,
            headline: headl,
        });
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', wire);
        } else {
            wire();
        }
    };
})(typeof window !== 'undefined' ? window : this);
