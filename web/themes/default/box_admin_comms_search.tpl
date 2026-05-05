{*
    SourceBans++ 2026 — public comms (mute / gag) advanced-search box.

    Pair: web/pages/admin.comms.search.php +
          web/includes/View/AdminCommsSearchView.php (typed DTO that
          SmartyTemplateRule keeps in lockstep with this template).

    This box is included via {load_template file="admin.comms.search"}
    from any consumer page that wants the legacy advanced-search
    affordance. The new sbpp2026 page_comms.tpl ships a single search
    bar inline, but we keep this box as a fully-functional, paired-View
    drop-in so future redesigns can re-mount it without re-plumbing the
    form.

    Wire format (kept identical to the legacy box so
    page.commslist.php?advSearch=…&advType=… continues to parse the
    same `$_GET` shape):
        advType=name           advSearch=<text>            (nickname)
        advType=steam|steamid  advSearch=<text>            (partial vs exact)
        advType=reason         advSearch=<text>
        advType=date           advSearch="<dd>,<mm>,<yyyy>"
        advType=length         advSearch="<op>,<minutes>"  op ∈ e|h|l|eh|el
        advType=btype          advSearch=<1|2>             1 = mute, 2 = gag
        advType=admin          advSearch=<aid>
        advType=where_banned   advSearch=<sid>             0 = web ban
        advType=comment        advSearch=<text>            (admin-only)

    Why we drop sourcebans.js' search_blocks() global
    -------------------------------------------------
    sourcebans.js disappears at #1123 D1, so the legacy
    `onclick="search_blocks()"` button would `ReferenceError`
    post-cutover. Each row gets its own submit button, with the
    dispatch logic inlined under {literal}<script>…{/literal}` —
    vanilla, no globals. The hidden `advType` / `advSearch` inputs
    are populated from the row's source field on click and the form
    submits natively; a no-JS browser still gets a working (if
    type-less) form.

    LoadServerHost replacement
    --------------------------
    The legacy `$server_script` payload built inline `<script>` tags
    that called `LoadServerHost('SID', 'id', 'ssSID', '', '', false, 200)`
    per server option to fetch the live hostname. After D1 removes
    sourcebans.js, that helper is gone; the server-side list is now
    plain `<option>`s carrying `data-sid`, and the inline initializer
    below issues one `sb.api.call(Actions.ServersHostPlayers, {sid})`
    per option — same pattern B5 established in page_servers.tpl.

    Testability hooks (per #1123 issue body, "search-<scope>-<…>"):
        data-testid="search-comms-form"           outer form
        data-testid="search-comms-name"           nickname <input>
        data-testid="search-comms-steamid"        SteamID <input>
        data-testid="search-comms-steam-match"    exact / partial match
        data-testid="search-comms-reason"         reason <input>
        data-testid="search-comms-date-day"       date triple, …-month, …-year
        data-testid="search-comms-length-op"      length comparator
        data-testid="search-comms-length"         length <select>
        data-testid="search-comms-other-length"   "other length" <input>
        data-testid="search-comms-btype"          mute / gag <select>
        data-testid="search-comms-admin"          admin <select>
        data-testid="search-comms-server"         server <select>
        data-testid="search-comms-comment"        admin comment <input>
        data-testid="search-comms-submit-<key>"   one per searchable field
*}
<form method="get"
      action="index.php"
      data-testid="search-comms-form"
      class="card"
      style="margin-top:1rem;margin-bottom:1rem">
    <input type="hidden" name="p" value="commslist">
    <input type="hidden" name="advType" value="" data-search-type>
    <input type="hidden" name="advSearch" value="" data-search-value>

    <div class="card__header">
        <div>
            <h3>Advanced search</h3>
            <p>Each row is its own search criterion &mdash; the &laquo;Search&raquo; button on the right submits using just that row's value.</p>
        </div>
    </div>

    <div class="card__body space-y-3">
        <div class="grid gap-3" style="grid-template-columns:10rem 1fr auto;align-items:end">
            <label class="label" for="search-comms-name" style="grid-column:1;align-self:end">Nickname</label>
            <input class="input"
                   id="search-comms-name"
                   type="text"
                   placeholder="Substring match against the player nickname&hellip;"
                   data-testid="search-comms-name"
                   autocomplete="off">
            <button type="submit"
                    class="btn btn--secondary btn--sm"
                    data-testid="search-comms-submit-name"
                    data-search-key="name"
                    data-search-from="search-comms-name">
                <i data-lucide="search" style="width:14px;height:14px"></i> Search
            </button>
        </div>

        <div class="grid gap-3" style="grid-template-columns:10rem 1fr auto;align-items:end">
            <label class="label" for="search-comms-steamid" style="grid-column:1;align-self:end">Steam ID</label>
            <div class="flex gap-2" style="flex-wrap:wrap">
                <input class="input font-mono"
                       id="search-comms-steamid"
                       type="text"
                       placeholder="STEAM_0:0:1234 or [U:1:1234]&hellip;"
                       data-testid="search-comms-steamid"
                       style="flex:1;min-width:14rem"
                       autocomplete="off">
                <select class="select"
                        id="search-comms-steam-match"
                        data-testid="search-comms-steam-match"
                        style="width:9rem">
                    <option value="0" selected>Exact match</option>
                    <option value="1">Partial match</option>
                </select>
            </div>
            <button type="submit"
                    class="btn btn--secondary btn--sm"
                    data-testid="search-comms-submit-steamid"
                    data-search-key=""
                    data-search-compose="steam">
                <i data-lucide="search" style="width:14px;height:14px"></i> Search
            </button>
        </div>

        <div class="grid gap-3" style="grid-template-columns:10rem 1fr auto;align-items:end">
            <label class="label" for="search-comms-reason" style="grid-column:1;align-self:end">Reason</label>
            <input class="input"
                   id="search-comms-reason"
                   type="text"
                   placeholder="Substring match against the recorded reason&hellip;"
                   data-testid="search-comms-reason"
                   autocomplete="off">
            <button type="submit"
                    class="btn btn--secondary btn--sm"
                    data-testid="search-comms-submit-reason"
                    data-search-key="reason"
                    data-search-from="search-comms-reason">
                <i data-lucide="search" style="width:14px;height:14px"></i> Search
            </button>
        </div>

        <div class="grid gap-3" style="grid-template-columns:10rem 1fr auto;align-items:end">
            <span class="label" style="grid-column:1;align-self:end">Date</span>
            <div class="flex items-center gap-2" style="flex-wrap:wrap">
                <input class="input font-mono" style="width:3rem" type="text" maxlength="2" placeholder="DD"
                       data-testid="search-comms-date-day"
                       data-search-date="day"
                       autocomplete="off"
                       inputmode="numeric"
                       pattern="[0-9]*">
                <span class="text-faint">/</span>
                <input class="input font-mono" style="width:3rem" type="text" maxlength="2" placeholder="MM"
                       data-testid="search-comms-date-month"
                       data-search-date="month"
                       autocomplete="off"
                       inputmode="numeric"
                       pattern="[0-9]*">
                <span class="text-faint">/</span>
                <input class="input font-mono" style="width:4.25rem" type="text" maxlength="4" placeholder="YYYY"
                       data-testid="search-comms-date-year"
                       data-search-date="year"
                       autocomplete="off"
                       inputmode="numeric"
                       pattern="[0-9]*">
            </div>
            <button type="submit"
                    class="btn btn--secondary btn--sm"
                    data-testid="search-comms-submit-date"
                    data-search-key="date"
                    data-search-compose="date">
                <i data-lucide="search" style="width:14px;height:14px"></i> Search
            </button>
        </div>

        <div class="grid gap-3" style="grid-template-columns:10rem 1fr auto;align-items:end">
            <span class="label" style="grid-column:1;align-self:end">Length</span>
            <div class="flex items-center gap-2" style="flex-wrap:wrap">
                <select class="select"
                        id="search-comms-length-op"
                        data-testid="search-comms-length-op"
                        style="width:5rem">
                    <option value="e"  title="equal to">=</option>
                    <option value="h"  title="greater">&gt;</option>
                    <option value="l"  title="smaller">&lt;</option>
                    <option value="eh" title="equal to or greater">&gt;=</option>
                    <option value="el" title="equal to or smaller">&lt;=</option>
                </select>
                <select class="select"
                        id="search-comms-length"
                        data-testid="search-comms-length"
                        style="flex:1;min-width:12rem"
                        data-search-length-select>
                    <option value="0">Permanent</option>
                    <optgroup label="Minutes">
                        <option value="1">1 minute</option>
                        <option value="5">5 minutes</option>
                        <option value="10">10 minutes</option>
                        <option value="15">15 minutes</option>
                        <option value="30">30 minutes</option>
                        <option value="45">45 minutes</option>
                    </optgroup>
                    <optgroup label="Hours">
                        <option value="60">1 hour</option>
                        <option value="120">2 hours</option>
                        <option value="180">3 hours</option>
                        <option value="240">4 hours</option>
                        <option value="480">8 hours</option>
                        <option value="720">12 hours</option>
                    </optgroup>
                    <optgroup label="Days">
                        <option value="1440">1 day</option>
                        <option value="2880">2 days</option>
                        <option value="4320">3 days</option>
                        <option value="5760">4 days</option>
                        <option value="7200">5 days</option>
                        <option value="8640">6 days</option>
                    </optgroup>
                    <optgroup label="Weeks">
                        <option value="10080">1 week</option>
                        <option value="20160">2 weeks</option>
                        <option value="30240">3 weeks</option>
                    </optgroup>
                    <optgroup label="Months">
                        <option value="40320">1 month</option>
                        <option value="80640">2 months</option>
                        <option value="120960">3 months</option>
                        <option value="241920">6 months</option>
                        <option value="483840">12 months</option>
                    </optgroup>
                    <option value="other">Other length (minutes)&hellip;</option>
                </select>
                <input type="text"
                       class="input font-mono"
                       id="search-comms-other-length"
                       data-testid="search-comms-other-length"
                       data-search-length-other
                       placeholder="Minutes"
                       hidden
                       inputmode="numeric"
                       pattern="[0-9]*"
                       style="width:7rem">
            </div>
            <button type="submit"
                    class="btn btn--secondary btn--sm"
                    data-testid="search-comms-submit-length"
                    data-search-key="length"
                    data-search-compose="length">
                <i data-lucide="search" style="width:14px;height:14px"></i> Search
            </button>
        </div>

        <div class="grid gap-3" style="grid-template-columns:10rem 1fr auto;align-items:end">
            <div>
                <label class="label" for="search-comms-btype">Type</label>
                <select class="select"
                        id="search-comms-btype"
                        data-testid="search-comms-btype">
                    <option value="1" selected>Mute</option>
                    <option value="2">Gag</option>
                </select>
            </div>
            <div class="text-xs text-muted">Filter by mute (voice) or gag (text) class.</div>
            <button type="submit"
                    class="btn btn--secondary btn--sm"
                    data-testid="search-comms-submit-btype"
                    data-search-key="btype"
                    data-search-from="search-comms-btype">
                <i data-lucide="search" style="width:14px;height:14px"></i> Search
            </button>
        </div>

        {if NOT $hideadminname}
            <div class="grid gap-3" style="grid-template-columns:10rem 1fr auto;align-items:end">
                <div>
                    <label class="label" for="search-comms-admin">Admin</label>
                    <select class="select"
                            id="search-comms-admin"
                            data-testid="search-comms-admin">
                        <option value="">&mdash;</option>
                        {foreach from=$admin_list item="admin"}
                            <option value="{$admin.aid}">{$admin.user}</option>
                        {/foreach}
                    </select>
                </div>
                <div class="text-xs text-muted">Show only blocks issued by the selected admin.</div>
                <button type="submit"
                        class="btn btn--secondary btn--sm"
                        data-testid="search-comms-submit-admin"
                        data-search-key="admin"
                        data-search-from="search-comms-admin">
                    <i data-lucide="search" style="width:14px;height:14px"></i> Search
                </button>
            </div>
        {/if}

        <div class="grid gap-3" style="grid-template-columns:10rem 1fr auto;align-items:end">
            <div>
                <label class="label" for="search-comms-server">Server</label>
                <select class="select"
                        id="search-comms-server"
                        data-testid="search-comms-server">
                    <option value="0" selected>Web Ban</option>
                    {foreach from=$server_list item="server"}
                        <option value="{$server.sid}"
                                data-sid="{$server.sid}"
                                data-ip="{$server.ip}"
                                data-port="{$server.port}"
                                data-server-host>Loading&hellip; ({$server.ip}:{$server.port})</option>
                    {/foreach}
                </select>
            </div>
            <div class="text-xs text-muted">Hostnames load asynchronously; pre-resolution shows ip:port.</div>
            <button type="submit"
                    class="btn btn--secondary btn--sm"
                    data-testid="search-comms-submit-server"
                    data-search-key="where_banned"
                    data-search-from="search-comms-server">
                <i data-lucide="search" style="width:14px;height:14px"></i> Search
            </button>
        </div>

        {if $is_admin}
            <div class="grid gap-3" style="grid-template-columns:10rem 1fr auto;align-items:end">
                <label class="label" for="search-comms-comment" style="grid-column:1;align-self:end">Comment</label>
                <input class="input"
                       id="search-comms-comment"
                       type="text"
                       placeholder="Substring match against admin notes&hellip;"
                       data-testid="search-comms-comment"
                       autocomplete="off">
                <button type="submit"
                        class="btn btn--secondary btn--sm"
                        data-testid="search-comms-submit-comment"
                        data-search-key="comment"
                        data-search-from="search-comms-comment">
                    <i data-lucide="search" style="width:14px;height:14px"></i> Search
                </button>
            </div>
        {/if}
    </div>
</form>

{*
    Inline submit dispatcher + LoadServerHost replacement.

    Submit dispatcher: each per-row button declares its search
    criterion via `data-search-key` and either points at a single
    source field via `data-search-from` or composes a multi-field tuple
    via `data-search-compose` ("steam", "date", "length"). On click,
    the hidden `advType` / `advSearch` inputs are populated and the
    form submits natively (no preventDefault on the success path), so
    the browser navigates to the consumer URL with the correct query
    string. Empty values cancel the submit.

    LoadServerHost replacement: the legacy box appended a
    server-built `<script>LoadServerHost(...)</script>` blob that
    called the (now-gone) sourcebans.js helper per option. We replace
    it with one `sb.api.call(Actions.ServersHostPlayers, {sid})` per
    `<option data-server-host>` option, mirroring B5's pattern in
    page_servers.tpl. Resolved hostnames replace the loading text in
    place; failures fall back to the original "Loading… (ip:port)"
    placeholder, then settle on "Offline" so the operator can still
    pick the row.
*}
<script>
{literal}
(function () {
    'use strict';
    var form = document.querySelector('[data-testid="search-comms-form"]');
    if (!(form instanceof HTMLFormElement)) return;

    var typeField = form.querySelector('[data-search-type]');
    var valueField = form.querySelector('[data-search-value]');
    if (!(typeField instanceof HTMLInputElement) || !(valueField instanceof HTMLInputElement)) return;

    var lengthSelect = form.querySelector('[data-search-length-select]');
    var lengthOther = form.querySelector('[data-search-length-other]');
    if (lengthSelect instanceof HTMLSelectElement && lengthOther instanceof HTMLInputElement) {
        var sync = function () {
            if (lengthSelect.value === 'other') {
                lengthOther.hidden = false;
                lengthOther.focus();
            } else {
                lengthOther.hidden = true;
            }
        };
        lengthSelect.addEventListener('change', sync);
        sync();
    }

    /**
     * @param {Element} btn
     * @returns {{ key: string, value: string }}
     */
    function readPair(btn) {
        var compose = btn.getAttribute('data-search-compose');
        if (compose === 'steam') {
            var sid = document.getElementById('search-comms-steamid');
            var match = document.getElementById('search-comms-steam-match');
            var sval = (sid instanceof HTMLInputElement) ? sid.value.trim() : '';
            var mval = (match instanceof HTMLSelectElement) ? match.value : '0';
            return { key: (mval === '1' ? 'steam' : 'steamid'), value: sval };
        }
        if (compose === 'date') {
            var keys = ['day', 'month', 'year'];
            var parts = keys.map(function (k) {
                var el = form.querySelector('[data-search-date="' + k + '"]');
                return (el instanceof HTMLInputElement) ? el.value : '';
            });
            return { key: 'date', value: parts.join(',') };
        }
        if (compose === 'length') {
            var op = document.getElementById('search-comms-length-op');
            var sel = document.getElementById('search-comms-length');
            var oth = document.getElementById('search-comms-other-length');
            var opv = (op instanceof HTMLSelectElement) ? op.value : 'e';
            var selv = (sel instanceof HTMLSelectElement) ? sel.value : '';
            var lv = (selv === 'other' && oth instanceof HTMLInputElement) ? oth.value : selv;
            return { key: 'length', value: opv + ',' + lv };
        }
        var fromId = btn.getAttribute('data-search-from');
        if (!fromId) return { key: '', value: '' };
        var src = document.getElementById(fromId);
        if (src instanceof HTMLInputElement || src instanceof HTMLSelectElement || src instanceof HTMLTextAreaElement) {
            return { key: btn.getAttribute('data-search-key') || '', value: src.value.trim() };
        }
        return { key: '', value: '' };
    }

    Array.prototype.forEach.call(form.querySelectorAll('button[data-search-compose], button[data-search-from]'), function (btn) {
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
            // d.hostname is htmlspecialchars()'d server-side; assigning to
            // .textContent re-encodes once so what shows in the <option>
            // matches the legacy LoadServerHost behaviour.
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
    AdminCommsSearchView declares `$server_script` so the legacy
    PHPStan leg (which scans `web/themes/default/box_admin_comms_search.tpl`)
    finds it; the sbpp2026 leg would otherwise flag it
    `unusedProperty`. The if-false branch is unreachable at render
    time so no script blob is emitted twice — this template owns the
    DOM and uses its own inline script above. Mirrors the parity-block
    pattern AdminHomeView established in `page_admin.tpl` (#1123 B10).
    D1 deletes the legacy template + the legacy-only `$server_script`
    property; this block leaves with them.
*}
{if false}{$server_script nofilter}{/if}
