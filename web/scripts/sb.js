// @ts-check
/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2024 by SourceBans++ Dev Team

The SourceBans++ Web panel is licensed under a
Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

You should have received a copy of the license along with this
work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.

Vanilla replacements for the MooTools idioms used throughout the panel.
Exposes a single global `sb` namespace and a `$` shim so legacy
id-based selectors keep working.
*************************************************************************/

(function (global) {
    'use strict';

    /**
     * Built up incrementally below; cast through `any` so the per-method
     * assignments can grow the object without tsc complaining that the
     * intermediate state is missing required SbNamespace members.
     * @type {SbNamespace}
     */
    const sb = /** @type {any} */ ({});

    // ---------------------------------------------------------------
    // Selectors
    // ---------------------------------------------------------------
    sb.$id  = (id) => /** @type {SbAnyEl | null} */ (typeof id === 'string' ? document.getElementById(id) : id);
    sb.$qs  = (sel, root) => /** @type {SbAnyEl | null} */ ((root || document).querySelector(sel));
    sb.$qsa = (sel, root) => /** @type {SbAnyEl[]} */ (Array.from((root || document).querySelectorAll(sel)));
    /**
     * Same lookup contract as $id but throws when the element is missing.
     * Use this where a missing id would be a template/JS bug we want to
     * surface immediately rather than degrade to a silent no-op.
     */
    sb.$idRequired = (id) => {
        const el = document.getElementById(id);
        if (!el) throw new Error(`sb.$idRequired: #${id} is not in the DOM`);
        return /** @type {SbAnyEl} */ (el);
    };

    // ---------------------------------------------------------------
    // Element wrapper that mimics the few MooTools methods we need.
    // ---------------------------------------------------------------
    /**
     * @param {HTMLElement | null} el
     * @returns {SbWrappedElement | null}
     */
    function wrap(el) {
        if (el == null) return null;
        // The "wrapped" sentinel + extra methods live on the element itself.
        // Casting once up front lets the lambdas pull their parameter types
        // from SbWrappedElement instead of having to reannotate each one.
        const w = /** @type {SbWrappedElement & {__sbWrapped?: boolean}} */ (el);
        if (w.__sbWrapped) return w;

        w.setStyle    = (prop, val) => { setCssProp(w, prop, val); return w; };
        w.setStyles   = (obj) => { for (const k in obj) setCssProp(w, k, obj[k]); return w; };
        w.getStyle    = (prop) => readCssProp(w, prop);
        w.setHTML     = (html) => { w.innerHTML = html; return w; };
        w.setProperty = (k, v) => { if (k === 'class') w.className = v; else w.setAttribute(k, v); return w; };
        w.getProperty = (k) => (k === 'class' ? w.className : w.getAttribute(k));
        w.hasClass    = (c) => w.classList.contains(c);
        w.addClass    = (c) => { w.classList.add(c); return w; };
        w.removeClass = (c) => { w.classList.remove(c); return w; };
        w.setOpacity  = (v) => { w.style.opacity = String(v); return w; };
        w.addEvent    = (type, fn) => { w.addEventListener(type === 'domready' ? 'DOMContentLoaded' : type, fn); return w; };
        w.removeEvent = (type, fn) => { w.removeEventListener(type, fn); return w; };
        w.adopt       = (child) => { w.appendChild(child); return w; };
        w.remove      = () => { if (w.parentNode) w.parentNode.removeChild(w); return w; };
        w.getCoordinates = () => {
            const r = w.getBoundingClientRect();
            return { left: r.left, top: r.top, right: r.right, bottom: r.bottom, width: r.width, height: r.height };
        };
        w.__sbWrapped = true;
        return w;
    }

    /** @param {string} prop */
    function camel(prop) {
        return prop.replace(/-([a-z])/g, (_, c) => c.toUpperCase());
    }

    // CSSStyleDeclaration's indexed signature is read-only in strict mode, so
    // string-keyed mutation via `el.style[name] = value` doesn't type-check.
    // setProperty() takes the un-camelCased name; getPropertyValue() reads
    // the inline value. Wrap both so callers can keep using camelCase ids.
    /**
     * @param {HTMLElement} el
     * @param {string} prop CSS property name (camelCase or kebab-case)
     * @param {string | number} val
     */
    function setCssProp(el, prop, val) {
        const kebab = prop.replace(/[A-Z]/g, (m) => '-' + m.toLowerCase());
        el.style.setProperty(kebab, String(val));
    }

    /**
     * @param {HTMLElement} el
     * @param {string} prop CSS property name (camelCase or kebab-case)
     * @returns {string}
     */
    function readCssProp(el, prop) {
        const kebab = prop.replace(/[A-Z]/g, (m) => '-' + m.toLowerCase());
        return el.style.getPropertyValue(kebab) || getComputedStyle(el).getPropertyValue(kebab);
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
    sb.setStyle = (el, prop, val) => { const target = sb.$id(el); if (target) setCssProp(target, prop, val); };

    // Escape any string before splicing it into innerHTML or an HTML
    // attribute. Use textContent/setAttribute when you can; reach for this
    // only when you must build a chunk of HTML.
    sb.escapeHtml = (s) => {
        /** @type {Record<string, string>} */
        const table = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(s == null ? '' : s).replace(/[&<>"']/g, (c) => table[c] ?? c);
    };

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
        const node = sb.$id(el); if (!node) return Promise.resolve();
        duration = duration || 500;
        return runTransition(node, `${prop} ${duration}ms ease-out`,
            () => { setCssProp(node, prop, typeof target === 'number' ? target + 'px' : target); }, duration);
    };

    /**
     * @param {HTMLElement} el
     * @param {string} transition
     * @param {() => void} mutate
     * @param {number} duration
     * @returns {Promise<void>}
     */
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
                if (redir) setTimeout(() => { window.location.href = redir; }, 5000);
                else setTimeout(() => sb.message.close(''), 5000);
            }
        },
        success(title, msg, redir) { sb.message.show(title, msg, 'green', redir); },
        error(title, msg, redir)   { sb.message.show(title || 'Error', msg, 'red', redir); },
        info(title, msg, redir)    { sb.message.show(title, msg, 'blue', redir); },
        close(redir) {
            if (redir && redir.length > 0 && redir !== 'undefined') {
                window.location.href = redir;
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

            /** @type {HTMLDivElement | null} */
            let tip = null;
            /** @param {MouseEvent} e */
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
                requestAnimationFrame(() => {
                    if (!tip) return;
                    tip.style.transition = 'opacity 200ms';
                    tip.style.opacity = '1';
                });
            };
            /** @param {MouseEvent} e */
            const move = (e) => { if (tip) position(e); };
            const hide = () => { if (tip) { tip.remove(); tip = null; } };
            /** @param {MouseEvent} e */
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
                if (tabNo !== '' && !Number.isNaN(Number(tabNo)) && typeof window.swapTab === 'function') {
                    window.swapTab(tabNo);
                }
            }
            const upos = url.indexOf('~') + 1;
            if (upos > 0) {
                const utabType = url.charAt(upos);
                const utabNo   = url.charAt(upos + 1);
                if (utabNo !== '' && !Number.isNaN(Number(utabNo)) && typeof window.Swap2ndPane === 'function') {
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
        if (!root) return null;
        const togglers = sb.$qsa(togglerSel, root);
        const elements = sb.$qsa(elementSel, root);
        if (togglers.length === 0 || togglers.length !== elements.length) return null;

        let current = -1;
        /** @param {number} i */
        const close = (i) => { elements[i].style.display = 'none'; };
        /** @param {number} i */
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

    // The `sb.contextMenu` / global `AddContextMenu` shims (and the
    // sibling `web/scripts/contextMenoo.js`) were vanilla replacements
    // for the MooTools-era right-click menu the legacy `LoadServerHost`
    // helper wired onto each player row on the public Servers page.
    // `LoadServerHost` was deleted with `sourcebans.js` at #1123 D1
    // and the v2.0.0 `page_servers.tpl` rewrite never re-registered
    // the menu — leaving the helpers as dead code with no call sites
    // anywhere in the panel. #1306 dropped the misleading help text
    // that promised the missing menu and burned the unused helpers
    // here. If a future feature wants a right-click menu, build it
    // from scratch against the current event-delegate pattern (e.g.
    // a single `document.addEventListener('contextmenu', …)` filtered
    // by `closest('[data-context-menu]')`).

    global.sb = sb;
})(typeof window !== 'undefined' ? window : this);
