{*
    SourceBans++ 2026 — page_toc.tpl

    Parameterized sticky page-level table of contents — anchor sidebar
    at >=1024px, accordion at <1024px. Lifted from the page-specific
    `admin.admins.toc.tpl` (#1207 ADM-3) per AGENTS.md guidance, so a
    second dense page (admin-bans, #1239) can reuse the same shape.

    #1266 — chrome unified with Pattern A (`core/admin_sidebar.tpl`)
    ----------------------------------------------------------------
    Pre-#1266 this partial rendered plain text rows with no icons and
    no active-state highlight while the sibling Pattern A partial
    (`core/admin_sidebar.tpl`, used by admin-settings / -servers /
    -mods / -groups / -comms) rendered iconed pill rows with a
    dark-pill / brand-orange `aria-current="page"` treatment. Both
    surfaces sit in the same 14rem rail at the same breakpoint, so
    end users perceived them as the same chrome element — the
    divergence read as broken (#1266 Symptom 1).

    The unified contract:

      - The DOM uses BOTH class sets — `.admin-sidebar*` (the new
        single-source chrome) AND `.page-toc*` (kept for backward
        compatibility with any third-party theme that styles by these
        names; the bare CSS rules now consolidate via grouped
        selectors in `theme.css`).
      - Each entry renders as `<a class="sidebar__link admin-sidebar__link page-toc__link">`
        with a Lucide icon — same shape as the Pattern A links — so
        the active-state CSS (`.sidebar__link[aria-current="page"]`)
        is single-source with the main app shell.
      - Active state is JS-driven: an `IntersectionObserver` on the
        `<section class="page-toc-section">` siblings of the host
        page toggles `aria-current="page"` on the matching link as
        the user scrolls. The first link is rendered active by
        default so the page works without JS too.
      - The routing semantics stay different (Pattern A navigates
        between sibling URLs; Pattern B scrolls within one page)
        but the visible chrome no longer diverges.

    Parameters (assigned by the calling View; SmartyTemplateRule
    follows the {include} so both ends agree on the contract):

        $toc_id       Testid prefix unique to the page rendering the
                      ToC (e.g. "admin-admins", "admin-bans"). Drives
                      `data-testid="{$toc_id}-toc"` on the wrapper and
                      `data-testid="{$toc_id}-toc-link-<slug>"` on each
                      anchor — every E2E spec anchors on these.

        $toc_label    aria-label for the <aside> (e.g. "Bans page
                      sections"). Screen readers announce the
                      navigation by this label.

        $toc_entries  list<{slug: string, label: string, icon?: string}>.
                      Permission filtering happens in the page
                      handler — only entries the dispatcher would
                      actually render get passed in, so a ToC click
                      never targets a non-existent anchor. The
                      optional `icon` is a Lucide glyph name (e.g.
                      "list", "flag", "import"); falls back to
                      `circle-dot` so every row has matching visual
                      weight.

    Caller contract:

        - Wraps the page in `<div class="page-toc-shell">` (the grid
          host on desktop). The closing tag lives at the bottom of
          whichever template is rendered last; document the pairing
          with a Smarty comment at each end.
        - Renders `<div class="page-toc-content">` immediately after
          this {include} — that's the content column the sticky
          sidebar pairs with at >=1024px.
        - Each section gets `<section id="{slug}" class="page-toc-section"
          data-testid="{$toc_id}-section-{slug}" aria-labelledby="…-heading">`.
          The shared `.page-toc-section` rule gives every section
          `scroll-margin-top: 4rem` so anchor jumps clear the sticky
          topbar.
*}
<aside class="admin-sidebar page-toc"
       data-testid="{$toc_id}-toc"
       aria-label="{$toc_label}">
    <details class="admin-sidebar__details page-toc__details" open>
        <summary class="admin-sidebar__summary page-toc__summary">
            <span class="admin-sidebar__summary-label page-toc__summary-label">
                <i data-lucide="list" style="width:14px;height:14px"></i>
                On this page
            </span>
            <i data-lucide="chevron-down" class="admin-sidebar__chevron page-toc__chevron" style="width:14px;height:14px"></i>
        </summary>
        {*
            #1266 — flatten the link list to direct <a> children of
            <nav> (matches `core/admin_sidebar.tpl`). The pre-#1266
            shape wrapped each link in `<ul><li>` which the CSS reset
            then had to fight (`list-style: none; padding: 0`). Direct
            children give every link the same gap/padding rhythm as
            Pattern A links without per-template overrides.
        *}
        <nav class="admin-sidebar__nav page-toc__nav" aria-label="{$toc_label}">
            {foreach from=$toc_entries item=entry name=tocLoop}
                <a class="sidebar__link admin-sidebar__link page-toc__link"
                   href="#{$entry.slug}"
                   data-testid="{$toc_id}-toc-link-{$entry.slug}"
                   data-toc-target="{$entry.slug}"
                   {if $smarty.foreach.tocLoop.first}aria-current="page"{/if}>
                    <i data-lucide="{if isset($entry.icon) && $entry.icon}{$entry.icon}{else}circle-dot{/if}"></i>
                    <span>{$entry.label}</span>
                </a>
            {/foreach}
        </nav>
    </details>
</aside>
<script>
{literal}
// @ts-check
//
// #1266 — page-level ToC active-section observer.
//
// Drive `aria-current="page"` on the `[data-toc-target="<slug>"]`
// link whose matching `<section id="<slug>">` is currently the most
// prominently visible inside the viewport. Mirrors the Pattern A
// active-link treatment so users see the same "this is where you
// are" signal whether the chrome is a sub-page sidebar or a
// page-level ToC.
//
// Idempotent: callable once per ToC instance via the
// `data-toc-id` selector. The first link is server-rendered with
// `aria-current="page"` so the page works without JS — this script
// only refines the active link as the user scrolls. Honours
// `prefers-reduced-motion` only by virtue of not animating
// anything (it's a class/attr toggle).
(function () {
    'use strict';
    /** @type {NodeListOf<HTMLElement>} */
    var tocs = document.querySelectorAll('[data-testid$="-toc"]:not([data-toc-init])');
    if (!tocs.length || typeof window.IntersectionObserver !== 'function') return;
    tocs.forEach(function (toc) {
        toc.setAttribute('data-toc-init', '1');
        /** @type {NodeListOf<HTMLAnchorElement>} */
        var links = toc.querySelectorAll('[data-toc-target]');
        if (!links.length) return;
        /** @type {Record<string, HTMLAnchorElement>} */
        var linkBySlug = {};
        /** @type {Element[]} */
        var sections = [];
        links.forEach(function (link) {
            var slug = link.getAttribute('data-toc-target');
            if (!slug) return;
            linkBySlug[slug] = link;
            var section = document.getElementById(slug);
            if (section) sections.push(section);
        });
        if (!sections.length) return;
        /** @type {Record<string, number>} */
        var ratios = {};
        function setActive(slug) {
            links.forEach(function (link) {
                if (link.getAttribute('data-toc-target') === slug) {
                    link.setAttribute('aria-current', 'page');
                } else {
                    link.removeAttribute('aria-current');
                }
            });
        }
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                var id = entry.target.id;
                if (!id) return;
                ratios[id] = entry.isIntersecting ? entry.intersectionRatio : 0;
            });
            // Pick the section with the highest visible ratio; ties
            // resolve to the earliest-in-DOM section (the iteration
            // order over `sections`). When everything is below the
            // viewport (e.g. the user scrolled past the last section)
            // the active link sticks at whatever it last was, which
            // matches the docs-site UX users expect from a ToC.
            var bestSlug = null;
            var bestRatio = 0;
            sections.forEach(function (section) {
                var r = ratios[section.id] || 0;
                if (r > bestRatio) {
                    bestRatio = r;
                    bestSlug = section.id;
                }
            });
            if (bestSlug) setActive(bestSlug);
        }, {
            // 4rem matches the `scroll-margin-top` on `.page-toc-section`
            // (sticky topbar offset), so a section becomes "the active
            // one" the moment its heading clears the topbar.
            rootMargin: '-4rem 0px -50% 0px',
            threshold: [0, 0.05, 0.25, 0.5, 0.75, 1],
        });
        sections.forEach(function (section) { observer.observe(section); });
    });
})();
{/literal}
</script>
