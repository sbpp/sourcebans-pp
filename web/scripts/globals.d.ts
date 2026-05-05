// Ambient declarations for the SourceBans++ vanilla-JS panel.
//
// The runtime files (sb.js, api.js, contextMenoo.js) ship as classic
// <script> tags rather than ESM modules and stash their public surface
// on `window` from inside an IIFE. tsc --checkJs cannot see those
// assignments without help, so we restate the public contract here.
// Keep this in sync with sb.js / api.js / api-contract.js when the
// runtime contract changes.

/** Element id, raw element, or null (the latter passes through every helper). */
type SbElLike = string | HTMLElement | null;

/**
 * Permissive element type returned by sb.$id / sb.$idRequired. The legacy
 * panel mutates DOM directly through getElementById and treats the result
 * as whatever element it knows it asked for — input, select, textarea,
 * div. We surface the common form-element members as REQUIRED (not
 * optional) so existing call sites compile without a per-site cast.
 *
 * Trade-off: this lets `sb.$id('some-div').value` type-check even though
 * `value` is `undefined` on a `<div>` at runtime. We accept that hazard
 * for the legacy panel because the alternative (optional fields) would
 * require ~hundreds of `if (el.value !== undefined)` narrowings in code
 * that already works. New code should prefer typed selectors
 * (`document.querySelector<HTMLInputElement>(...)`) or per-call casts so
 * `tsc` can catch the wrong-element-kind bug. A follow-up issue should
 * introduce typed `sb.$input(id)` / `sb.$select(id)` helpers and
 * progressively migrate call sites off `SbAnyEl` to tighten the contract.
 */
interface SbAnyEl extends HTMLElement {
    value: string;
    checked: boolean;
    disabled: boolean;
    readOnly: boolean;
    selectedIndex: number;
    options: HTMLOptionsCollection;
    selected: boolean;
    src: string;
    alt: string;
    name: string;
    type: string;
    height: string | number;
    width: string | number;
    insertRow(index?: number): HTMLTableRowElement;
    insertCell(index?: number): HTMLTableCellElement;
    deleteRow(index: number): void;
    rows: HTMLCollectionOf<HTMLTableRowElement>;
    cells: HTMLCollectionOf<HTMLTableCellElement>;
    length: number;
    elements: HTMLFormControlsCollection & Record<string, any>;
    submit(): void;
    reset(): void;
}

/** Wrapped element with the small subset of MooTools-ish methods sb.wrap adds. */
interface SbWrappedElement extends HTMLElement {
    setStyle(prop: string, val: string): SbWrappedElement;
    setStyles(obj: Record<string, string>): SbWrappedElement;
    getStyle(prop: string): string;
    setHTML(html: string): SbWrappedElement;
    setProperty(k: string, v: string): SbWrappedElement;
    getProperty(k: string): string | null;
    hasClass(c: string): boolean;
    addClass(c: string): SbWrappedElement;
    removeClass(c: string): SbWrappedElement;
    setOpacity(v: number | string): SbWrappedElement;
    addEvent(type: string, fn: EventListener): SbWrappedElement;
    removeEvent(type: string, fn: EventListener): SbWrappedElement;
    adopt(child: Node): SbWrappedElement;
    getCoordinates(): { left: number; top: number; right: number; bottom: number; width: number; height: number };
}

/** Contract subset accepted by sb.contextMenu / AddContextMenu. */
interface SbContextMenuItem {
    name?: string;
    callback?: () => void;
    disabled?: boolean;
    separator?: boolean;
}

/** Generic JSON envelope returned by /api.php. */
interface SbApiEnvelope {
    ok?: boolean;
    redirect?: string;
    error?: { code: string; message: string; field?: string };
    /**
     * Per-action response payload. Typed as `any` because the shape varies
     * widely between handlers and the panel doesn't (yet) carry per-action
     * typedefs. Tightening this to a discriminated union keyed off
     * Actions.* is on the table — see #1097/#1098 follow-ups.
     */
    data?: any;
}

/** sb.api surface — see scripts/api.js. */
interface SbApiNamespace {
    endpoint: string;
    call(action: string, params?: object): Promise<SbApiEnvelope>;
    callOrAlert(action: string, params?: object): Promise<SbApiEnvelope>;
}

/** sb.message surface — see scripts/sb.js. */
interface SbMessageNamespace {
    show(title: string, msg: string, kind?: string, redir?: string, noclose?: boolean): void;
    success(title: string, msg: string, redir?: string): void;
    error(title: string, msg: string, redir?: string): void;
    info(title: string, msg: string, redir?: string): void;
    close(redir?: string): void;
}

/** sb.tabs surface — see scripts/sb.js. */
interface SbTabsNamespace {
    init(): void;
}

/** sb.accordion controller returned by sb.accordion(). */
interface SbAccordionController {
    showAll(): void;
    hideAll(): void;
    display(i: number): void;
}

/** Top-level sb namespace assembled across sb.js + api.js. */
interface SbNamespace {
    $id(id: SbElLike): SbAnyEl | null;
    /**
     * Like $id but throws if the element isn't present. Use it where a
     * missing element is a programmer error (e.g. you just rendered the
     * template that owns the id).
     */
    $idRequired(id: string): SbAnyEl;
    $qs(sel: string, root?: ParentNode): SbAnyEl | null;
    $qsa(sel: string, root?: ParentNode): SbAnyEl[];
    wrap(el: HTMLElement | null): SbWrappedElement | null;
    ready(fn: () => void): void;
    show(el: SbElLike, display?: string): void;
    hide(el: SbElLike): void;
    setHTML(el: SbElLike, html: string): void;
    setText(el: SbElLike, text: string): void;
    setStyle(el: SbElLike, prop: string, val: string): void;
    escapeHtml(s: unknown): string;
    fadeIn(el: SbElLike, duration?: number): Promise<void>;
    fadeOut(el: SbElLike, duration?: number): Promise<void>;
    slideUp(el: SbElLike, duration?: number): Promise<void>;
    slideDown(el: SbElLike, duration?: number): Promise<void>;
    animateTo(el: SbElLike, prop: string, target: number | string, duration?: number): Promise<void>;
    message: SbMessageNamespace;
    tooltip(selector: string, opts?: { className?: string }): void;
    tabs: SbTabsNamespace;
    accordion(togglerSel: string, elementSel: string, container?: SbElLike, openIndex?: number): SbAccordionController | null;
    contextMenu(selector: string, opts?: { items?: SbContextMenuItem[]; menuItems?: SbContextMenuItem[]; className?: string; headline?: string }): void;
    api: SbApiNamespace;
}

declare var sb: SbNamespace;
declare var $: (idOrEl: string | HTMLElement | null) => SbWrappedElement | null;

// Actions and Perms are declared in scripts/api-contract.js (generated by
// `composer api-contract`). tsc picks up the literal types from the
// Object.freeze({...}) initialiser there, so we don't need to redeclare
// them — and doing so loosely here would conflict (TS2403).

/** Vanilla replacement for the legacy contextMenoo MooTools class. */
declare var contextMenoo: (opts: {
    selector?: string;
    className?: string;
    pageOffset?: number;
    fade?: boolean;
    headline?: string;
    menuItems?: SbContextMenuItem[];
}) => void;

declare var AddContextMenu: (
    select: string,
    classNames: string | undefined,
    fader: boolean | undefined,
    headl: string | undefined,
    oLinks: SbContextMenuItem[]
) => void;

/**
 * Per-page hooks defined ad-hoc in inline templates (account.php, etc.)
 * or in raw HTML echoed by legacy page handlers. Declared as optional
 * Window properties so call sites narrow with `typeof window.x ===
 * 'function'` rather than crashing tsc.
 *
 * `swapTab` / `Swap2ndPane` used to live in `web/scripts/sourcebans.js`
 * (deleted at #1123 D1). They're still emitted as `onclick="Swap2ndPane(…)"`
 * in raw HTML by some legacy admin pages (`web/pages/admin.bans.php`)
 * and `sb.tabs.init()` (`web/scripts/sb.js`) calls them through the
 * guarded `window.*` lookup so the panel doesn't crash when no inline
 * definition is present. Replacements for those legacy tab navs ship
 * with the new theme's per-page redesigns.
 */
interface Window {
    set_error?: (n: number) => void;
    SwapPane?: (n: number) => void;
    swapTab?: (tabNo: number | string) => void;
    Swap2ndPane?: (n: number | string, type: string) => void;
    demo?: (filename: string, origname: string) => void;
}

/** Per-page accordion handle from InitAccordion(). */
declare var accordion: SbAccordionController | null | undefined;
