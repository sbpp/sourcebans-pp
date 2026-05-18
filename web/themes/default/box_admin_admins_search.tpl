{*
    SourceBans++ 2026 — admin/admins advanced-search box.

    Pair: web/pages/admin.admins.search.php +
          web/includes/View/AdminAdminsSearchView.php (typed DTO that
          SmartyTemplateRule keeps in lockstep with this template).

    Included via {load_template file="admin.admins.search"} from
    page_admin_admins_list.tpl. Submits as a plain `GET` to
    ?p=admin&c=admins with one parameter per populated filter.

    Wire format (#1207 ADM-4 redesign — single submit, AND semantics;
    extended in #1231 to give every text filter its own match-mode select):
        name=<text>                login
        name_match=0|1             0 = exact, 1 = partial (default 1)
        steamid=<text>             Steam ID
        steam_match=0|1            0 = exact, 1 = partial (default 0)
        admemail=<text>            E-mail (gated by can_edit_admins)
        admemail_match=0|1         0 = exact, 1 = partial (default 1)
        webgroup=<gid>             Panel group
        srvadmgroup=<group_name>   SourceMod admin group
        srvgroup=<gid>             Server group
        admwebflag[]=ADMIN_*       Web permission flags (multi)
        admsrvflag[]=SM_*          Server permission flags (multi)
        server=<sid>               Server access

    Match-mode defaults are asymmetric on purpose:
        - SteamID defaults to exact (0) — typical use is "find one
          admin by their full Steam ID".
        - Login / E-mail default to partial (1) — preserves the
          pre-#1231 substring behaviour so existing bookmarks and
          legacy `advType=…&advSearch=…` URLs keep narrowing the
          way admins expect.

    Multiple non-empty filters are combined with AND on the server side
    (admin.admins.php). The legacy single-filter shape
    (`?advType=name&advSearch=foo`) still works: admin.admins.php
    translates it into the modern shape on entry so existing bookmarks
    and external links keep narrowing the list.

    Why we drop the per-row Search buttons
    --------------------------------------
    The pre-fix shape had eight `<button data-search-key=…>` elements
    populating hidden `advType` / `advSearch` inputs and submitting
    with one filter at a time. Audit (#1207 ADM-4) called this out as
    unusual — typical search UIs combine inputs with one submit at the
    bottom. The new form drops the hidden proxies, gives every input
    its native `name=` attribute, and renders one Search submit + one
    Reset link.

    LoadServerHost replacement
    --------------------------
    The legacy `$server_script` payload built inline `<script>` tags
    that called `LoadServerHost('SID', 'id', 'ssSID', '', '', false, 200)`
    per server option. After D1 removes sourcebans.js, that helper is
    gone; the server-side list is now plain `<option>`s with
    `data-sid` / `data-ip` / `data-port`, and the inline initializer
    below issues one `sb.api.call(Actions.ServersHostPlayers, {sid})`
    per option — same pattern B5 established in page_servers.tpl.

    Testability hooks (per #1123 issue body, "search-<scope>-<…>"):
        data-testid="search-admins-disclosure"     <details> outer wrapper (#1303)
        data-testid="search-admins-toggle"         <summary> click target  (#1303)
        data-testid="search-admins-active-count"   "N active" badge        (#1303; only when count > 0)
        data-testid="search-admins-form"           outer form
        data-testid="search-admins-name"           login <input>
        data-testid="search-admins-name-match"     login exact / partial match (#1231)
        data-testid="search-admins-steamid"        SteamID <input>
        data-testid="search-admins-steam-match"    SteamID exact / partial match
        data-testid="search-admins-admemail"       email <input>      (gated)
        data-testid="search-admins-admemail-match" email exact / partial match (#1231; gated)
        data-testid="search-admins-webgroup"       web-group <select>
        data-testid="search-admins-srvadmgroup"    SM admin-group <select>
        data-testid="search-admins-srvgroup"       server-group <select>
        data-testid="search-admins-admwebflag"     web-perms multi-select
        data-testid="search-admins-admsrvflag"     server-perms multi-select
        data-testid="search-admins-server"         server <select>
        data-testid="search-admins-submit"         the (one) submit button
        data-testid="search-admins-reset"          reset / clear-filters link

    #1303 — collapsible disclosure
    ------------------------------
    The form sits inside a `<details class="card filters-details">`
    default-collapsed wrapper so the unfiltered admin list paints
    above the fold. The disclosure auto-opens on a post-submit paint
    when `$has_active_filters` is true so the filter chrome (and the
    Clear-filters affordance) stays visible while the user is iterating.
    The `<summary>` mirrors the visual vocabulary `core/admin_sidebar.tpl`
    uses for its mobile accordion (label + chevron + 180° rotation
    on `[open]`, `prefers-reduced-motion: reduce` collapses the
    transition). The count badge ("Filters · N active") rides
    `$active_filter_count` from the View — only emitted when N > 0.
*}
<details class="card filters-details"
         data-testid="search-admins-disclosure"
         data-active-filter-count="{$active_filter_count}"
         {if $has_active_filters}open{/if}
         style="margin-top:1rem;margin-bottom:1rem">
    <summary class="filters-details__summary"
             data-testid="search-admins-toggle"
             aria-controls="search-admins-form-body">
        <span class="filters-details__summary-label">
            <i data-lucide="filter" style="width:14px;height:14px"></i>
            <span>Advanced search</span>
            {if $active_filter_count > 0}
                <span class="filters-details__count"
                      data-testid="search-admins-active-count"
                      aria-label="{$active_filter_count} active filter{if $active_filter_count != 1}s{/if}">
                    &middot; {$active_filter_count} active
                </span>
            {/if}
        </span>
        <i data-lucide="chevron-down" class="filters-details__chevron" style="width:14px;height:14px"></i>
    </summary>

<form method="get"
      action="index.php"
      data-testid="search-admins-form"
      id="search-admins-form-body"
      class="filters-details__form">
    <input type="hidden" name="p" value="admin">
    <input type="hidden" name="c" value="admins">
    {* #1275 — Pattern A. Carry the active section so the post-submit URL
       matches the surface the user is on; otherwise the handler would
       fall through to the default-section guard, which lands on the
       same `admins` slug today but only by coincidence. *}
    <input type="hidden" name="section" value="admins">

    {*
        #1303 — drop the redundant `<h3>Advanced search</h3>` that the
        pre-disclosure `card__header` carried; the `<summary>` above
        already paints the title (+ chevron + count badge), so emitting
        it again here would stack two identical headings the moment a
        user opens the disclosure. Keep the explanatory paragraph that
        documents the AND-semantics contract — that's the load-bearing
        copy the audit (#1207 ADM-4) called out, and it has no analog
        in the summary.
    *}
    <div class="card__header filters-details__header">
        <div>
            <p>Combine any of the filters below — the server narrows the admin list to rows matching <strong>every</strong> populated filter (AND semantics).</p>
        </div>
    </div>

    <div class="card__body space-y-3">
        <div class="grid gap-3" style="grid-template-columns:12rem 1fr;align-items:end">
            <label class="label" for="search-admins-name" style="grid-column:1;align-self:end">Login name</label>
            <div class="flex gap-2" style="flex-wrap:wrap">
                <input class="input"
                       id="search-admins-name"
                       name="name"
                       type="text"
                       placeholder="Match against the panel login&hellip;"
                       data-testid="search-admins-name"
                       value="{$active_filter_name|escape}"
                       style="flex:1;min-width:14rem"
                       autocomplete="off">
                <select class="select"
                        id="search-admins-name-match"
                        name="name_match"
                        data-testid="search-admins-name-match"
                        aria-label="Login name match mode"
                        style="width:9rem">
                    <option value="0"{if $active_filter_name_match == '0'} selected{/if}>Exact match</option>
                    <option value="1"{if $active_filter_name_match != '0'} selected{/if}>Partial match</option>
                </select>
            </div>
        </div>

        <div class="grid gap-3" style="grid-template-columns:12rem 1fr;align-items:end">
            <label class="label" for="search-admins-steamid" style="grid-column:1;align-self:end">Steam ID</label>
            <div class="flex gap-2" style="flex-wrap:wrap">
                <input class="input font-mono"
                       id="search-admins-steamid"
                       name="steamid"
                       type="text"
                       placeholder="STEAM_0:0:1234 or [U:1:1234]&hellip;"
                       data-testid="search-admins-steamid"
                       value="{$active_filter_steamid|escape}"
                       style="flex:1;min-width:14rem"
                       autocomplete="off">
                <select class="select"
                        id="search-admins-steam-match"
                        name="steam_match"
                        data-testid="search-admins-steam-match"
                        aria-label="Steam ID match mode"
                        style="width:9rem">
                    <option value="0"{if $active_filter_steam_match != '1'} selected{/if}>Exact match</option>
                    <option value="1"{if $active_filter_steam_match == '1'} selected{/if}>Partial match</option>
                </select>
            </div>
        </div>

        {if $can_editadmin}
            <div class="grid gap-3" style="grid-template-columns:12rem 1fr;align-items:end">
                <label class="label" for="search-admins-admemail" style="grid-column:1;align-self:end">E-mail</label>
                <div class="flex gap-2" style="flex-wrap:wrap">
                    <input class="input"
                           id="search-admins-admemail"
                           name="admemail"
                           type="email"
                           placeholder="Match against the panel e-mail&hellip;"
                           data-testid="search-admins-admemail"
                           value="{$active_filter_admemail|escape}"
                           style="flex:1;min-width:14rem"
                           autocomplete="off">
                    <select class="select"
                            id="search-admins-admemail-match"
                            name="admemail_match"
                            data-testid="search-admins-admemail-match"
                            aria-label="E-mail match mode"
                            style="width:9rem">
                        <option value="0"{if $active_filter_admemail_match == '0'} selected{/if}>Exact match</option>
                        <option value="1"{if $active_filter_admemail_match != '0'} selected{/if}>Partial match</option>
                    </select>
                </div>
            </div>
        {/if}

        <div class="grid gap-3" style="grid-template-columns:12rem 1fr;align-items:end">
            <div>
                <label class="label" for="search-admins-webgroup">Web group</label>
                <select class="select"
                        id="search-admins-webgroup"
                        name="webgroup"
                        data-testid="search-admins-webgroup">
                    <option value="">&mdash;</option>
                    {foreach from=$webgroup_list item="webgrp"}
                        <option value="{$webgrp.gid}"{if $active_filter_webgroup == $webgrp.gid && $active_filter_webgroup != ''} selected{/if}>{$webgrp.name}</option>
                    {/foreach}
                </select>
            </div>
            <div class="text-xs text-muted">Filter by panel group membership.</div>
        </div>

        <div class="grid gap-3" style="grid-template-columns:12rem 1fr;align-items:end">
            <div>
                <label class="label" for="search-admins-srvadmgroup">SourceMod admin group</label>
                <select class="select"
                        id="search-admins-srvadmgroup"
                        name="srvadmgroup"
                        data-testid="search-admins-srvadmgroup">
                    <option value="">&mdash;</option>
                    {foreach from=$srvadmgroup_list item="srvadmgrp"}
                        <option value="{$srvadmgrp.name}"{if $active_filter_srvadmgroup == $srvadmgrp.name && $active_filter_srvadmgroup != ''} selected{/if}>{$srvadmgrp.name}</option>
                    {/foreach}
                </select>
            </div>
            <div class="text-xs text-muted">Filter by SourceMod admin-group attachment.</div>
        </div>

        <div class="grid gap-3" style="grid-template-columns:12rem 1fr;align-items:end">
            <div>
                <label class="label" for="search-admins-srvgroup">Server group</label>
                <select class="select"
                        id="search-admins-srvgroup"
                        name="srvgroup"
                        data-testid="search-admins-srvgroup">
                    <option value="">&mdash;</option>
                    {foreach from=$srvgroup_list item="srvgrp"}
                        <option value="{$srvgrp.gid}"{if $active_filter_srvgroup == $srvgrp.gid && $active_filter_srvgroup != ''} selected{/if}>{$srvgrp.name}</option>
                    {/foreach}
                </select>
            </div>
            <div class="text-xs text-muted">Filter by server-group membership.</div>
        </div>

        <div class="grid gap-3" style="grid-template-columns:12rem 1fr;align-items:end">
            <div>
                <label class="label" for="search-admins-admwebflag">Web permissions</label>
                <select class="select"
                        id="search-admins-admwebflag"
                        name="admwebflag[]"
                        data-testid="search-admins-admwebflag"
                        size="6"
                        multiple
                        style="height:auto">
                    {foreach from=$admwebflag_list item="admwebflag"}
                        <option value="{$admwebflag.flag}"{if in_array($admwebflag.flag, $active_filter_admwebflag)} selected{/if}>{$admwebflag.name}</option>
                    {/foreach}
                </select>
            </div>
            <div class="text-xs text-muted">Hold <kbd data-modkey>Ctrl</kbd> to multi-select. Submits the current selection as repeated <code>admwebflag[]=ADMIN_*</code> parameters.</div>
        </div>

        <div class="grid gap-3" style="grid-template-columns:12rem 1fr;align-items:end">
            <div>
                <label class="label" for="search-admins-admsrvflag">Server permissions</label>
                <select class="select"
                        id="search-admins-admsrvflag"
                        name="admsrvflag[]"
                        data-testid="search-admins-admsrvflag"
                        size="6"
                        multiple
                        style="height:auto">
                    {foreach from=$admsrvflag_list item="admsrvflag"}
                        <option value="{$admsrvflag.flag}"{if in_array($admsrvflag.flag, $active_filter_admsrvflag)} selected{/if}>{$admsrvflag.name}</option>
                    {/foreach}
                </select>
            </div>
            <div class="text-xs text-muted">Hold <kbd data-modkey>Ctrl</kbd> to multi-select. Submits the current selection as repeated <code>admsrvflag[]=SM_*</code> parameters.</div>
        </div>

        <div class="grid gap-3" style="grid-template-columns:12rem 1fr;align-items:end">
            <div>
                <label class="label" for="search-admins-server">Server</label>
                <select class="select"
                        id="search-admins-server"
                        name="server"
                        data-testid="search-admins-server">
                    <option value="">&mdash;</option>
                    {foreach from=$server_list item="server"}
                        <option value="{$server.sid}"
                                data-sid="{$server.sid}"
                                data-ip="{$server.ip}"
                                data-port="{$server.port}"
                                data-server-host{if $active_filter_server == $server.sid && $active_filter_server != ''} selected{/if}>Loading&hellip; ({$server.ip}:{$server.port})</option>
                    {/foreach}
                </select>
            </div>
            <div class="text-xs text-muted">Show admins with explicit access to the selected server. Hostnames load asynchronously.</div>
        </div>

        <div class="flex gap-2 justify-end" style="border-top:1px solid var(--border);padding-top:0.75rem">
            <a class="btn btn--ghost btn--sm"
               href="?p=admin&amp;c=admins&amp;section=admins"
               data-testid="search-admins-reset">Clear filters</a>
            <button type="submit"
                    class="btn btn--primary btn--sm"
                    data-testid="search-admins-submit">
                <i data-lucide="search" style="width:14px;height:14px"></i> Search
            </button>
        </div>
    </div>
</form>
</details>

{*
    LoadServerHost replacement (vanilla, post-#1123 D1).

    The legacy box appended a server-built `<script>LoadServerHost(...)</script>`
    blob that called the (now-gone) sourcebans.js helper per option. We
    replace it with one `sb.api.call(Actions.ServersHostPlayers, {sid})`
    per `<option data-server-host>` option, mirroring B5's pattern in
    page_servers.tpl. Resolved hostnames replace the loading text in
    place; failures fall back to "Offline (ip:port)".
*}
<script>
{literal}
(function () {
    'use strict';
    var form = document.querySelector('[data-testid="search-admins-form"]');
    if (!(form instanceof HTMLFormElement)) return;

    if (typeof sb === 'undefined' || !sb || !sb.api || typeof Actions === 'undefined') {
        return;
    }

    Array.prototype.forEach.call(form.querySelectorAll('option[data-server-host]'), function (opt) {
        var sid = Number(opt.getAttribute('data-sid'));
        var ip = opt.getAttribute('data-ip') || '';
        var port = opt.getAttribute('data-port') || '';
        if (!sid) return;
        sb.api.call(Actions.ServersHostPlayers, { sid: sid, trunchostname: 70 }).then(function (r) {
            if (!r || !r.ok || !r.data) {
                opt.textContent = 'Offline (' + ip + ':' + port + ')';
                return;
            }
            var d = r.data;
            if (d.error === 'connect') {
                opt.textContent = 'Offline (' + ip + ':' + port + ')';
                return;
            }
            opt.textContent = (d.hostname || (ip + ':' + port)) + ' (' + ip + ':' + port + ')';
        }, function () {
            opt.textContent = 'Offline (' + ip + ':' + port + ')';
        });
    });
})();
{/literal}
</script>

{*
    Parity reference for the legacy default-theme `$server_script` blob.
    AdminAdminsSearchView declares `$server_script` so the legacy
    PHPStan leg (which scans `web/themes/default/box_admin_admins_search.tpl`)
    finds it; the sbpp2026 leg would otherwise flag it
    `unusedProperty`. The if-false branch is unreachable at render
    time so no script blob is emitted twice — this template owns the
    DOM and uses its own inline script above. Mirrors the parity-block
    pattern AdminHomeView established in `page_admin.tpl` (#1123 B10).
    D1 deletes the legacy template + the legacy-only `$server_script`
    property; this block leaves with them.
*}
{if false}{$server_script nofilter}{/if}
