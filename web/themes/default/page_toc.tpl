{*
    SourceBans++ 2026 — page_toc.tpl

    Parameterized sticky page-level table of contents — anchor sidebar
    at >=1024px, accordion at <1024px. Lifted from the page-specific
    `admin.admins.toc.tpl` (#1207 ADM-3) per AGENTS.md guidance, so a
    second dense page (admin-bans, #1239) can reuse the same shape.

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

        $toc_entries  list<{slug: string, label: string}>. Permission
                      filtering happens in the page handler — only
                      entries the dispatcher would actually render get
                      passed in, so a ToC click never targets a
                      non-existent anchor.

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
<aside class="page-toc"
       data-testid="{$toc_id}-toc"
       aria-label="{$toc_label}">
    <details class="page-toc__details" open>
        <summary class="page-toc__summary">
            <span class="page-toc__summary-label">
                <i data-lucide="list" style="width:14px;height:14px"></i>
                On this page
            </span>
            <i data-lucide="chevron-down" class="page-toc__chevron" style="width:14px;height:14px"></i>
        </summary>
        <nav class="page-toc__nav">
            <ul class="page-toc__list">
                {foreach from=$toc_entries item=entry}
                    <li>
                        <a href="#{$entry.slug}"
                           class="page-toc__link"
                           data-testid="{$toc_id}-toc-link-{$entry.slug}">{$entry.label}</a>
                    </li>
                {/foreach}
            </ul>
        </nav>
    </details>
</aside>
