{*
    SourceBans++ 2026 — admin/admins advanced-search box.

    Pair: web/pages/admin.admins.search.php +
          web/includes/View/AdminAdminsSearchView.php (typed DTO that
          SmartyTemplateRule keeps in lockstep with this template).

    Included via {load_template file="admin.admins.search"} from
    page_admin_admins_list.tpl. Submits as a plain `GET` to
    ?p=admin&c=admins&advSearch=…&advType=…, mirroring the wire
    format admin.admins.php already parses.

    Wire format (kept identical to the legacy box):
        advType=name           advSearch=<text>           (admin login)
        advType=steam|steamid  advSearch=<text>           (partial vs exact)
        advType=admemail       advSearch=<text>           (gated by can_edit_admins)
        advType=webgroup       advSearch=<gid>
        advType=srvadmgroup    advSearch=<group_name>
        advType=srvgroup       advSearch=<gid>
        advType=admwebflag     advSearch=<ADMIN_*>[,<ADMIN_*>…]   (multi)
        advType=admsrvflag     advSearch=<SM_*>[,<SM_*>…]         (multi)
        advType=server         advSearch=<sid>

    Why we drop sourcebans.js' search_admins() global
    -------------------------------------------------
    sourcebans.js disappears at #1123 D1, so `onclick="search_admins()"`
    would `ReferenceError`. Each row gets its own submit button with
    inline {literal}<script>…{/literal}` dispatch — vanilla, no globals.
    The hidden `advType` / `advSearch` inputs are populated from the
    row's source field on click; the form submits natively.

    Multi-select permission flags
    -----------------------------
    The legacy widget used MooTools' `getMultiple(this, 1|2)` to pull
    the full selection from a `<select multiple>` `onblur`. The new
    dispatcher reads `selectedOptions` directly when the user submits
    the row, so the value collected is the full current selection at
    submit time — tight and unsurprising. The wire shape (comma-joined
    `ADMIN_*` constant names) is unchanged so admin.admins.php's
    constant() resolution keeps working.

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
        data-testid="search-admins-form"           outer form
        data-testid="search-admins-name"           login <input>
        data-testid="search-admins-steamid"        SteamID <input>
        data-testid="search-admins-steam-match"    exact / partial match
        data-testid="search-admins-admemail"       email <input>      (gated)
        data-testid="search-admins-webgroup"       web-group <select>
        data-testid="search-admins-srvadmgroup"    SM admin-group <select>
        data-testid="search-admins-srvgroup"       server-group <select>
        data-testid="search-admins-admwebflag"     web-perms multi-select
        data-testid="search-admins-admsrvflag"     server-perms multi-select
        data-testid="search-admins-server"         server <select>
        data-testid="search-admins-submit-<key>"   one per searchable field
*}
<form method="get"
      action="index.php"
      data-testid="search-admins-form"
      class="card"
      style="margin-top:1rem;margin-bottom:1rem">
    <input type="hidden" name="p" value="admin">
    <input type="hidden" name="c" value="admins">
    <input type="hidden" name="advType" value="" data-search-type>
    <input type="hidden" name="advSearch" value="" data-search-value>

    <div class="card__header">
        <div>
            <h3>Advanced search</h3>
            <p>Filter the admin list by login name, group, permission flag, or server access.</p>
        </div>
    </div>

    <div class="card__body space-y-3">
        <div class="grid gap-3" style="grid-template-columns:12rem 1fr auto;align-items:end">
            <label class="label" for="search-admins-name" style="grid-column:1;align-self:end">Login name</label>
            <input class="input"
                   id="search-admins-name"
                   type="text"
                   placeholder="Substring match against the panel login&hellip;"
                   data-testid="search-admins-name"
                   autocomplete="off">
            <button type="submit"
                    class="btn btn--secondary btn--sm"
                    data-testid="search-admins-submit-name"
                    data-search-key="name"
                    data-search-from="search-admins-name">
                <i data-lucide="search" style="width:14px;height:14px"></i> Search
            </button>
        </div>

        <div class="grid gap-3" style="grid-template-columns:12rem 1fr auto;align-items:end">
            <label class="label" for="search-admins-steamid" style="grid-column:1;align-self:end">Steam ID</label>
            <div class="flex gap-2" style="flex-wrap:wrap">
                <input class="input font-mono"
                       id="search-admins-steamid"
                       type="text"
                       placeholder="STEAM_0:0:1234 or [U:1:1234]&hellip;"
                       data-testid="search-admins-steamid"
                       style="flex:1;min-width:14rem"
                       autocomplete="off">
                <select class="select"
                        id="search-admins-steam-match"
                        data-testid="search-admins-steam-match"
                        aria-label="Steam ID match mode"
                        style="width:9rem">
                    <option value="0" selected>Exact match</option>
                    <option value="1">Partial match</option>
                </select>
            </div>
            <button type="submit"
                    class="btn btn--secondary btn--sm"
                    data-testid="search-admins-submit-steamid"
                    data-search-key=""
                    data-search-compose="steam">
                <i data-lucide="search" style="width:14px;height:14px"></i> Search
            </button>
        </div>

        {if $can_editadmin}
            <div class="grid gap-3" style="grid-template-columns:12rem 1fr auto;align-items:end">
                <label class="label" for="search-admins-admemail" style="grid-column:1;align-self:end">E-mail</label>
                <input class="input"
                       id="search-admins-admemail"
                       type="email"
                       placeholder="Substring match against the panel e-mail&hellip;"
                       data-testid="search-admins-admemail"
                       autocomplete="off">
                <button type="submit"
                        class="btn btn--secondary btn--sm"
                        data-testid="search-admins-submit-admemail"
                        data-search-key="admemail"
                        data-search-from="search-admins-admemail">
                    <i data-lucide="search" style="width:14px;height:14px"></i> Search
                </button>
            </div>
        {/if}

        <div class="grid gap-3" style="grid-template-columns:12rem 1fr auto;align-items:end">
            <div>
                <label class="label" for="search-admins-webgroup">Web group</label>
                <select class="select"
                        id="search-admins-webgroup"
                        data-testid="search-admins-webgroup">
                    <option value="">&mdash;</option>
                    {foreach from=$webgroup_list item="webgrp"}
                        <option value="{$webgrp.gid}">{$webgrp.name}</option>
                    {/foreach}
                </select>
            </div>
            <div class="text-xs text-muted">Filter by panel group membership.</div>
            <button type="submit"
                    class="btn btn--secondary btn--sm"
                    data-testid="search-admins-submit-webgroup"
                    data-search-key="webgroup"
                    data-search-from="search-admins-webgroup">
                <i data-lucide="search" style="width:14px;height:14px"></i> Search
            </button>
        </div>

        <div class="grid gap-3" style="grid-template-columns:12rem 1fr auto;align-items:end">
            <div>
                <label class="label" for="search-admins-srvadmgroup">SourceMod admin group</label>
                <select class="select"
                        id="search-admins-srvadmgroup"
                        data-testid="search-admins-srvadmgroup">
                    <option value="">&mdash;</option>
                    {foreach from=$srvadmgroup_list item="srvadmgrp"}
                        <option value="{$srvadmgrp.name}">{$srvadmgrp.name}</option>
                    {/foreach}
                </select>
            </div>
            <div class="text-xs text-muted">Filter by SourceMod admin-group attachment.</div>
            <button type="submit"
                    class="btn btn--secondary btn--sm"
                    data-testid="search-admins-submit-srvadmgroup"
                    data-search-key="srvadmgroup"
                    data-search-from="search-admins-srvadmgroup">
                <i data-lucide="search" style="width:14px;height:14px"></i> Search
            </button>
        </div>

        <div class="grid gap-3" style="grid-template-columns:12rem 1fr auto;align-items:end">
            <div>
                <label class="label" for="search-admins-srvgroup">Server group</label>
                <select class="select"
                        id="search-admins-srvgroup"
                        data-testid="search-admins-srvgroup">
                    <option value="">&mdash;</option>
                    {foreach from=$srvgroup_list item="srvgrp"}
                        <option value="{$srvgrp.gid}">{$srvgrp.name}</option>
                    {/foreach}
                </select>
            </div>
            <div class="text-xs text-muted">Filter by server-group membership.</div>
            <button type="submit"
                    class="btn btn--secondary btn--sm"
                    data-testid="search-admins-submit-srvgroup"
                    data-search-key="srvgroup"
                    data-search-from="search-admins-srvgroup">
                <i data-lucide="search" style="width:14px;height:14px"></i> Search
            </button>
        </div>

        <div class="grid gap-3" style="grid-template-columns:12rem 1fr auto;align-items:end">
            <div>
                <label class="label" for="search-admins-admwebflag">Web permissions</label>
                <select class="select"
                        id="search-admins-admwebflag"
                        data-testid="search-admins-admwebflag"
                        size="6"
                        multiple
                        data-search-multi
                        style="height:auto">
                    {foreach from=$admwebflag_list item="admwebflag"}
                        <option value="{$admwebflag.flag}">{$admwebflag.name}</option>
                    {/foreach}
                </select>
            </div>
            <div class="text-xs text-muted">Hold <kbd data-modkey>Ctrl</kbd> to multi-select. Submits the current selection as a comma-joined list of <code>ADMIN_*</code> constant names.</div>
            <button type="submit"
                    class="btn btn--secondary btn--sm"
                    data-testid="search-admins-submit-admwebflag"
                    data-search-key="admwebflag"
                    data-search-multi-from="search-admins-admwebflag">
                <i data-lucide="search" style="width:14px;height:14px"></i> Search
            </button>
        </div>

        <div class="grid gap-3" style="grid-template-columns:12rem 1fr auto;align-items:end">
            <div>
                <label class="label" for="search-admins-admsrvflag">Server permissions</label>
                <select class="select"
                        id="search-admins-admsrvflag"
                        data-testid="search-admins-admsrvflag"
                        size="6"
                        multiple
                        data-search-multi
                        style="height:auto">
                    {foreach from=$admsrvflag_list item="admsrvflag"}
                        <option value="{$admsrvflag.flag}">{$admsrvflag.name}</option>
                    {/foreach}
                </select>
            </div>
            <div class="text-xs text-muted">Hold <kbd data-modkey>Ctrl</kbd> to multi-select. Submits the current selection as a comma-joined list of <code>SM_*</code> constant names.</div>
            <button type="submit"
                    class="btn btn--secondary btn--sm"
                    data-testid="search-admins-submit-admsrvflag"
                    data-search-key="admsrvflag"
                    data-search-multi-from="search-admins-admsrvflag">
                <i data-lucide="search" style="width:14px;height:14px"></i> Search
            </button>
        </div>

        <div class="grid gap-3" style="grid-template-columns:12rem 1fr auto;align-items:end">
            <div>
                <label class="label" for="search-admins-server">Server</label>
                <select class="select"
                        id="search-admins-server"
                        data-testid="search-admins-server">
                    <option value="">&mdash;</option>
                    {foreach from=$server_list item="server"}
                        <option value="{$server.sid}"
                                data-sid="{$server.sid}"
                                data-ip="{$server.ip}"
                                data-port="{$server.port}"
                                data-server-host>Loading&hellip; ({$server.ip}:{$server.port})</option>
                    {/foreach}
                </select>
            </div>
            <div class="text-xs text-muted">Show admins with explicit access to the selected server. Hostnames load asynchronously.</div>
            <button type="submit"
                    class="btn btn--secondary btn--sm"
                    data-testid="search-admins-submit-server"
                    data-search-key="server"
                    data-search-from="search-admins-server">
                <i data-lucide="search" style="width:14px;height:14px"></i> Search
            </button>
        </div>
    </div>
</form>

{*
    Inline submit dispatcher + LoadServerHost replacement.

    Submit dispatcher: each per-row button declares its search
    criterion via `data-search-key` and either points at a single
    source field via `data-search-from`, a multi-select source via
    `data-search-multi-from`, or composes the SteamID exact/partial
    pair via `data-search-compose="steam"`. On click, the hidden
    `advType` / `advSearch` inputs are populated and the form
    submits natively (no preventDefault on the success path), so the
    browser navigates to the consumer URL with the correct query
    string. Empty selections cancel the submit.

    LoadServerHost replacement: the legacy box appended a
    server-built `<script>LoadServerHost(...)</script>` blob that
    called the (now-gone) sourcebans.js helper per option. We replace
    it with one `sb.api.call(Actions.ServersHostPlayers, {sid})` per
    `<option data-server-host>` option, mirroring B5's pattern in
    page_servers.tpl. Resolved hostnames replace the loading text in
    place; failures fall back to "Offline (ip:port)".
*}
<script>
{literal}
(function () {
    'use strict';
    var form = document.querySelector('[data-testid="search-admins-form"]');
    if (!(form instanceof HTMLFormElement)) return;

    var typeField = form.querySelector('[data-search-type]');
    var valueField = form.querySelector('[data-search-value]');
    if (!(typeField instanceof HTMLInputElement) || !(valueField instanceof HTMLInputElement)) return;

    /**
     * @param {Element} btn
     * @returns {{ key: string, value: string }}
     */
    function readPair(btn) {
        var compose = btn.getAttribute('data-search-compose');
        if (compose === 'steam') {
            var sid = document.getElementById('search-admins-steamid');
            var match = document.getElementById('search-admins-steam-match');
            var sval = (sid instanceof HTMLInputElement) ? sid.value.trim() : '';
            var mval = (match instanceof HTMLSelectElement) ? match.value : '0';
            return { key: (mval === '1' ? 'steam' : 'steamid'), value: sval };
        }
        var multiId = btn.getAttribute('data-search-multi-from');
        if (multiId) {
            var msel = document.getElementById(multiId);
            if (msel instanceof HTMLSelectElement && msel.multiple) {
                var values = [];
                Array.prototype.forEach.call(msel.selectedOptions, function (o) {
                    if (o instanceof HTMLOptionElement && o.value !== '') values.push(o.value);
                });
                return { key: btn.getAttribute('data-search-key') || '', value: values.join(',') };
            }
            return { key: '', value: '' };
        }
        var fromId = btn.getAttribute('data-search-from');
        if (!fromId) return { key: '', value: '' };
        var src = document.getElementById(fromId);
        if (src instanceof HTMLInputElement || src instanceof HTMLSelectElement || src instanceof HTMLTextAreaElement) {
            return { key: btn.getAttribute('data-search-key') || '', value: src.value.trim() };
        }
        return { key: '', value: '' };
    }

    Array.prototype.forEach.call(form.querySelectorAll('button[data-search-compose], button[data-search-from], button[data-search-multi-from]'), function (btn) {
        btn.addEventListener('click', function (ev) {
            var pair = readPair(btn);
            if (pair.key === '' || pair.value === '' || pair.value.replace(/[,]/g, '') === '') {
                ev.preventDefault();
                return;
            }
            typeField.value = pair.key;
            valueField.value = pair.value;
        });
    });

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
