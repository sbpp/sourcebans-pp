/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2024 by SourceBans++ Dev Team

The SourceBans++ Web panel is licensed under a
Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

You should have received a copy of the license along with this
work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.

Vanilla replacements for the MooTools idioms used throughout the panel.
Loaded before sourcebans.js. Exposes a single global `sb` namespace and a
`$` shim so legacy id-based selectors keep working.
*************************************************************************/

(function (global) {
    'use strict';

    const sb = {};

    // ---------------------------------------------------------------
    // Selectors
    // ---------------------------------------------------------------
    sb.$id  = (id) => (typeof id === 'string' ? document.getElementById(id) : id);
    sb.$qs  = (sel, root) => (root || document).querySelector(sel);
    sb.$qsa = (sel, root) => Array.from((root || document).querySelectorAll(sel));

    // ---------------------------------------------------------------
    // Element wrapper that mimics the few MooTools methods we need.
    // ---------------------------------------------------------------
    function wrap(el) {
        if (el == null) return null;
        if (el.__sbWrapped) return el;

        el.setStyle    = (prop, val) => { el.style[camel(prop)] = val; return el; };
        el.setStyles   = (obj) => { for (const k in obj) el.style[camel(k)] = obj[k]; return el; };
        el.getStyle    = (prop) => el.style[camel(prop)] || getComputedStyle(el)[camel(prop)];
        el.setHTML     = (html) => { el.innerHTML = html; return el; };
        el.setProperty = (k, v) => { if (k === 'class') el.className = v; else el.setAttribute(k, v); return el; };
        el.getProperty = (k) => (k === 'class' ? el.className : el.getAttribute(k));
        el.hasClass    = (c) => el.classList.contains(c);
        el.addClass    = (c) => { el.classList.add(c); return el; };
        el.removeClass = (c) => { el.classList.remove(c); return el; };
        el.setOpacity  = (v) => { el.style.opacity = String(v); return el; };
        el.addEvent    = (type, fn) => { el.addEventListener(type === 'domready' ? 'DOMContentLoaded' : type, fn); return el; };
        el.removeEvent = (type, fn) => { el.removeEventListener(type, fn); return el; };
        el.adopt       = (child) => { el.appendChild(child); return el; };
        el.remove      = () => { if (el.parentNode) el.parentNode.removeChild(el); return el; };
        el.getCoordinates = () => {
            const r = el.getBoundingClientRect();
            return { left: r.left, top: r.top, right: r.right, bottom: r.bottom, width: r.width, height: r.height };
        };
        el.__sbWrapped = true;
        return el;
    }

    function camel(prop) {
        return prop.replace(/-([a-z])/g, (_, c) => c.toUpperCase());
    }

    // Compatibility shim so existing code calling `$('id')` still works.
    // Accepts an id string, an element, or returns null.
    global.$ = function (idOrEl) {
        if (idOrEl == null) return null;
        if (typeof idOrEl === 'string') return wrap(document.getElementById(idOrEl));
        return wrap(idOrEl);
    };

    sb.wrap = wrap;

    // ---------------------------------------------------------------
    // DOM ready
    // ---------------------------------------------------------------
    sb.ready = function (fn) {
        if (document.readyState !== 'loading') fn();
        else document.addEventListener('DOMContentLoaded', fn);
    };

    // ---------------------------------------------------------------
    // Visibility / styling helpers
    // ---------------------------------------------------------------
    sb.show    = (el, display) => { el = sb.$id(el); if (el) el.style.display = display || 'block'; };
    sb.hide    = (el) => { el = sb.$id(el); if (el) el.style.display = 'none'; };
    sb.setHTML = (el, html) => { el = sb.$id(el); if (el) el.innerHTML = html; };
    sb.setText = (el, text) => { el = sb.$id(el); if (el) el.textContent = text; };
    sb.setStyle = (el, prop, val) => { el = sb.$id(el); if (el) el.style[camel(prop)] = val; };

    // Escape any string before splicing it into innerHTML or an HTML
    // attribute. Use textContent/setAttribute when you can; reach for this
    // only when you must build a chunk of HTML.
    sb.escapeHtml = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
    }[c]));

    // ---------------------------------------------------------------
    // Animations (CSS-transition based, replaces Fx.Slide / Fx.Style).
    // ---------------------------------------------------------------
    sb.fadeIn = function (el, duration) {
        el = sb.$id(el); if (!el) return Promise.resolve();
        duration = duration || 250;
        el.style.opacity = '0';
        el.style.display = el.style.display === 'none' || !el.style.display ? 'block' : el.style.display;
        return runTransition(el, `opacity ${duration}ms ease-out`, () => { el.style.opacity = '1'; }, duration);
    };

    sb.fadeOut = function (el, duration) {
        el = sb.$id(el); if (!el) return Promise.resolve();
        duration = duration || 250;
        return runTransition(el, `opacity ${duration}ms ease-out`, () => { el.style.opacity = '0'; }, duration)
            .then(() => { el.style.display = 'none'; });
    };

    sb.slideUp = function (el, duration) {
        el = sb.$id(el); if (!el) return Promise.resolve();
        duration = duration || 300;
        const h = el.scrollHeight;
        el.style.overflow = 'hidden';
        el.style.height = h + 'px';
        // force reflow
        // eslint-disable-next-line no-unused-expressions
        el.offsetHeight;
        return runTransition(el, `height ${duration}ms ease-out, opacity ${duration}ms ease-out`,
            () => { el.style.height = '0px'; el.style.opacity = '0'; }, duration)
            .then(() => { el.style.display = 'none'; });
    };

    sb.slideDown = function (el, duration) {
        el = sb.$id(el); if (!el) return Promise.resolve();
        duration = duration || 300;
        el.style.display = '';
        el.style.overflow = 'hidden';
        el.style.height = '0px';
        el.style.opacity = '0';
        // eslint-disable-next-line no-unused-expressions
        el.offsetHeight;
        const target = el.scrollHeight;
        return runTransition(el, `height ${duration}ms ease-out, opacity ${duration}ms ease-out`,
            () => { el.style.height = target + 'px'; el.style.opacity = '1'; }, duration)
            .then(() => { el.style.height = ''; el.style.overflow = ''; });
    };

    /** Animate a single property toward a target value (replaces Fx.Tween). */
    sb.animateTo = function (el, prop, target, duration) {
        el = sb.$id(el); if (!el) return Promise.resolve();
        duration = duration || 500;
        return runTransition(el, `${prop} ${duration}ms ease-out`,
            () => { el.style[camel(prop)] = (typeof target === 'number' ? target + 'px' : target); }, duration);
    };

    function runTransition(el, transition, mutate, duration) {
        return new Promise((resolve) => {
            const prev = el.style.transition;
            el.style.transition = transition;
            mutate();
            const done = () => { el.style.transition = prev; resolve(); };
            setTimeout(done, duration + 50);
        });
    }

    // ---------------------------------------------------------------
    // Modal-style message box (replaces ShowBox / closeMsg).
    //
    // Operates on the existing #dialog-placement / #dialog-title /
    // #dialog-content-text / #dialog-icon / #dialog-control DOM that
    // every theme already renders.
    // ---------------------------------------------------------------
    sb.message = {
        show(title, msg, kind, redir, noclose) {
            const cls = kind === 'red' ? 'error' : kind === 'blue' ? 'info' : kind === 'green' ? 'ok' : (kind || 'info');
            const $title = sb.$id('dialog-title');
            const $icon  = sb.$id('dialog-icon');
            const $text  = sb.$id('dialog-content-text');
            const $ctrl  = sb.$id('dialog-control');
            const $place = sb.$id('dialog-placement');

            if ($title) { $title.className = cls; $title.innerHTML = title; }
            if ($icon)  { $icon.className  = `icon-${cls}`; }
            if ($text)  { $text.innerHTML  = msg; }

            if ($place) sb.fadeIn($place, 250);

            if ($ctrl) {
                $ctrl.innerHTML = '';
                const btn = document.createElement('input');
                btn.type = 'button';
                btn.name = 'dialog-close';
                btn.id   = 'dialog-close';
                btn.className = 'btn ok';
                btn.value = 'OK';
                btn.addEventListener('click', () => sb.message.close(redir || ''));
                $ctrl.appendChild(btn);
                $ctrl.style.display = 'block';
            }

            if (!noclose) {
                if (redir) setTimeout(() => { window.location = redir; }, 5000);
                else setTimeout(() => sb.message.close(''), 5000);
            }
        },
        success(title, msg, redir) { sb.message.show(title, msg, 'green', redir); },
        error(title, msg, redir)   { sb.message.show(title || 'Error', msg, 'red', redir); },
        info(title, msg, redir)    { sb.message.show(title, msg, 'blue', redir); },
        close(redir) {
            if (redir && redir.length > 0 && redir !== 'undefined') {
                window.location = redir;
                return;
            }
            sb.fadeOut('dialog-placement', 250);
        },
    };

    // ---------------------------------------------------------------
    // Tooltips (replaces MooTools `Tips`).
    // Reads `title` of "Header::Body" (or just "Body") and shows a
    // floating tooltip on hover.
    // ---------------------------------------------------------------
    sb.tooltip = function (selector, opts) {
        opts = opts || {};
        const className = opts.className || 'tool-tip';
        sb.$qsa(selector).forEach((el) => {
            const raw = el.getAttribute('title');
            if (!raw) return;
            // Save original title and prevent the browser from showing it.
            el.setAttribute('data-sb-title', raw);
            el.removeAttribute('title');

            const parts = raw.split('::');
            const head  = parts.length > 1 ? parts[0] : '';
            const body  = parts.length > 1 ? parts.slice(1).join('::') : raw;

            let tip = null;
            const show = (e) => {
                if (tip) return;
                tip = document.createElement('div');
                tip.className = className;
                tip.style.position = 'absolute';
                tip.style.zIndex = '10000';
                tip.style.opacity = '0';
                tip.style.pointerEvents = 'none';
                if (head) {
                    const t = document.createElement('span');
                    t.className = 'tool-title';
                    t.textContent = head;
                    tip.appendChild(t);
                }
                const tx = document.createElement('span');
                tx.className = 'tool-text';
                tx.innerHTML = body;
                tip.appendChild(tx);
                document.body.appendChild(tip);
                position(e);
                requestAnimationFrame(() => { tip.style.transition = 'opacity 200ms'; tip.style.opacity = '1'; });
            };
            const move = (e) => { if (tip) position(e); };
            const hide = () => { if (tip) { tip.remove(); tip = null; } };
            const position = (e) => {
                if (!tip) return;
                tip.style.left = (e.pageX + 16) + 'px';
                tip.style.top  = (e.pageY + 16) + 'px';
            };

            el.addEventListener('mouseover', show);
            el.addEventListener('mousemove', move);
            el.addEventListener('mouseout',  hide);
            el.addEventListener('click',     hide);
        });
    };

    // ---------------------------------------------------------------
    // Tabs (replaces ProcessAdminTabs / Accordion-based tab switching)
    // ---------------------------------------------------------------
    sb.tabs = {
        init() {
            const url = window.location.toString();
            const pos = url.indexOf('^') + 1;
            if (pos > 0) {
                const tabNo = url.charAt(pos);
                if (tabNo !== '' && !isNaN(tabNo) && typeof window.swapTab === 'function') {
                    window.swapTab(tabNo);
                }
            }
            const upos = url.indexOf('~') + 1;
            if (upos > 0) {
                const utabType = url.charAt(upos);
                const utabNo   = url.charAt(upos + 1);
                if (utabNo !== '' && !isNaN(utabNo) && typeof window.Swap2ndPane === 'function') {
                    window.Swap2ndPane(utabNo, utabType);
                }
            }
        },
    };

    // ---------------------------------------------------------------
    // Accordion (vanilla replacement for MooTools `Accordion`).
    // Toggles visibility of each `element` when the matching `toggler`
    // is clicked. Only one panel is open at a time unless `alwaysHide`
    // is true (the original behaviour we relied on).
    // ---------------------------------------------------------------
    sb.accordion = function (togglerSel, elementSel, container, openIndex) {
        const root = container ? sb.$id(container) : document;
        const togglers = sb.$qsa(togglerSel, root);
        const elements = sb.$qsa(elementSel, root);
        if (togglers.length === 0 || togglers.length !== elements.length) return null;

        let current = -1;
        const close = (i) => { elements[i].style.display = 'none'; };
        const open  = (i) => { elements[i].style.display = ''; };

        elements.forEach((_, i) => close(i));
        if (typeof openIndex === 'number' && openIndex >= 0 && openIndex < elements.length) {
            open(openIndex);
            current = openIndex;
        }

        togglers.forEach((t, i) => {
            t.style.cursor = 'pointer';
            t.addEventListener('click', () => {
                if (current === i) {
                    close(i);
                    current = -1;
                } else {
                    if (current >= 0) close(current);
                    open(i);
                    current = i;
                }
            });
        });

        return {
            showAll() { elements.forEach((_, i) => open(i)); current = -1; },
            hideAll() { elements.forEach((_, i) => close(i)); current = -1; },
            display(i) { if (current >= 0) close(current); open(i); current = i; },
        };
    };

    // ---------------------------------------------------------------
    // Right-click context menu (replaces contextMenoo.js).
    // ---------------------------------------------------------------
    sb.contextMenu = function (selector, opts) {
        opts = opts || {};
        const items = opts.items || opts.menuItems || [];
        const className = opts.className || 'protoMenu';
        const headline  = opts.headline || '';

        const build = () => {
            const cont = document.createElement('div');
            cont.className = className;
            cont.style.position = 'absolute';
            cont.style.display  = 'none';
            cont.style.zIndex   = '10000';

            if (headline) {
                const h = document.createElement('b');
                h.className = 'head';
                h.innerHTML = headline;
                cont.appendChild(h);
            }
            items.forEach((item) => {
                if (item.separator) {
                    const sep = document.createElement('div');
                    sep.className = 'separator';
                    cont.appendChild(sep);
                    return;
                }
                const a = document.createElement('a');
                a.href = '#';
                a.title = item.name;
                if (item.disabled) a.className = 'disabled';
                a.innerHTML = item.name;
                a.addEventListener('click', (e) => {
                    e.preventDefault();
                    cont.style.display = 'none';
                    if (!item.disabled && typeof item.callback === 'function') item.callback();
                });
                cont.appendChild(a);
            });
            document.body.appendChild(cont);
            return cont;
        };

        let cont = null;
        const hide = () => { if (cont) cont.style.display = 'none'; };
        document.addEventListener('click', hide);
        document.addEventListener('contextmenu', hide);

        sb.$qsa(selector).forEach((el) => {
            el.addEventListener('contextmenu', (e) => {
                e.preventDefault();
                e.stopPropagation();
                if (!cont) cont = build();
                cont.style.left = e.pageX + 'px';
                cont.style.top  = e.pageY + 'px';
                cont.style.display = 'block';
            });
        });
    };

    // Register a context menu by selector — same name as the legacy global.
    global.AddContextMenu = function (select, classNames, fader, headl, oLinks) {
        sb.ready(() => sb.contextMenu(select, {
            className: classNames,
            headline: headl,
            items: oLinks,
            fade: fader,
        }));
    };

    global.sb = sb;
})(typeof window !== 'undefined' ? window : this);
