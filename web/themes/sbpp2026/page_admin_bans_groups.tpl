{*
    SourceBans++ 2026 — page_admin_bans_groups.tpl
    Bound to Sbpp\View\AdminBansGroupsView (validated by SmartyTemplateRule).

    "Group ban" tab on the admin bans page. Two modes share this surface:
      - Default: a small form to ban a Steam community group by URL.
        Submission goes through the legacy ProcessGroupBan() helper in
        admin.bans.php's tail script (which dispatches to
        Actions.GroupbanCheck via sb.api.call).
      - "From player" mode (?fid=STEAMID): the legacy LoadGetGroups()
        helper enumerates the player's group memberships into the
        #steamGroupsTable list; ticking groups + clicking "Add Group Ban"
        runs CheckGroupBan() to issue Actions.GroupbanCheck for each
        selected group.

    Both helpers (LoadGetGroups, TickSelectAll, CheckGroupBan) live in
    web/scripts/sourcebans.js and aren't loaded by the sbpp2026 chrome;
    that flow remains a default-theme feature for the rollout window.
    DOM ids (groupurl, groupreason, *.msg, agban, aback, gban,
    tickswitch, tickswitchlink, steamGroups, steamGroupsText,
    steamGroupsTable, steamGroupStatus) are preserved so legacy callers
    continue to find them on default.

    `$player_name` is rendered above the group list when reaching this
    tab from a banlist row (?fid=STEAMID&player=…). admin.bans.php
    currently passes an empty string; Phase B/C may wire the lookup
    later, the placeholder is here so SmartyTemplateRule sees the
    template + view are in agreement.
*}
{if NOT $permission_addban}
    <div class="card" data-testid="groupban-denied">
        <div class="card__body">
            <h1 style="font-size:1.25rem;font-weight:600;margin:0">Access denied</h1>
            <p class="text-sm text-muted m-0 mt-2">You don't have permission to add bans.</p>
        </div>
    </div>
{else}
    {if NOT $groupbanning_enabled}
        <div class="card" data-testid="groupban-disabled">
            <div class="card__body">
                <h1 style="font-size:1.25rem;font-weight:600;margin:0">Feature disabled</h1>
                <p class="text-sm text-muted m-0 mt-2">
                    Group banning is turned off in
                    <strong>config.enablegroupbanning</strong>.
                    Re-enable it from the Settings tab to use this surface.
                </p>
            </div>
        </div>
    {else}
        <section class="p-6" data-testid="groupban-section" style="max-width:48rem">
            <div class="mb-6">
                <h1 style="font-size:1.5rem;font-weight:600;margin:0">Group ban</h1>
                <p class="text-sm text-muted m-0 mt-2">
                    Ban every member of a Steam community group at once.
                </p>
            </div>

            {if NOT $list_steam_groups}
                <form id="groupban-form"
                      class="card p-6 space-y-4"
                      data-testid="groupban-form"
                      onsubmit="return false;">
                    {csrf_field}
                    <div>
                        <label class="label" for="groupurl">Steam community group URL</label>
                        <input type="text"
                               class="input font-mono"
                               id="groupurl"
                               name="groupurl"
                               data-testid="groupban-url"
                               placeholder="http://steamcommunity.com/groups/interwavestudios">
                        <div class="text-xs mt-2" id="groupurl.msg" style="color:var(--danger);display:none"></div>
                    </div>
                    <div>
                        <label class="label" for="groupreason">Group ban reason</label>
                        <textarea class="textarea"
                                  id="groupreason"
                                  name="groupreason"
                                  rows="4"
                                  placeholder="Why is this group being banned?"
                                  data-testid="groupban-reason"></textarea>
                        <div class="text-xs mt-2" id="groupreason.msg" style="color:var(--danger);display:none"></div>
                    </div>
                    <div class="flex justify-end gap-2"
                         style="border-top:1px solid var(--border);padding-top:0.75rem">
                        <button type="button"
                                class="btn btn--ghost"
                                id="aback"
                                data-testid="groupban-back"
                                onclick="history.go(-1);">Back</button>
                        <button type="button"
                                class="btn btn--primary"
                                id="agban"
                                data-testid="groupban-submit"
                                onclick="ProcessGroupBan();">
                            Add group ban
                        </button>
                    </div>
                </form>
            {else}
                <div class="card p-6 space-y-4" data-testid="groupban-from-player">
                    <p class="text-sm text-muted m-0">
                        {if $player_name}
                            Groups <strong class="font-medium">{$player_name|escape}</strong> belongs to are loaded below.
                        {else}
                            All groups the player is a member of are listed here.
                        {/if}
                        Tick the ones you want to ban.
                    </p>

                    <div id="steamGroupsText"
                         name="steamGroupsText"
                         class="text-sm text-muted">Loading the groups…</div>

                    <div id="steamGroups"
                         name="steamGroups"
                         style="display:none">
                        <table id="steamGroupsTable"
                               name="steamGroupsTable"
                               class="table"
                               style="margin-bottom:1rem">
                            <thead>
                                <tr>
                                    <th style="width:2.5rem">
                                        <button type="button"
                                                id="tickswitch"
                                                name="tickswitch"
                                                class="btn btn--secondary btn--sm btn--icon"
                                                onclick="TickSelectAll();"
                                                title="Select all">+</button>
                                    </th>
                                    <th>Group</th>
                                </tr>
                            </thead>
                        </table>
                        <a class="btn btn--ghost btn--sm"
                           href="#"
                           onclick="TickSelectAll();return false;"
                           name="tickswitchlink"
                           id="tickswitchlink">Select all</a>

                        <div class="mt-4">
                            <label class="label" for="groupreason">Group ban reason</label>
                            <textarea class="textarea"
                                      id="groupreason"
                                      name="groupreason"
                                      rows="4"
                                      placeholder="Why is this group being banned?"
                                      data-testid="groupban-reason"></textarea>
                            <div class="text-xs mt-2" id="groupreason.msg" style="color:var(--danger);display:none"></div>
                        </div>

                        <div class="flex justify-end gap-2 mt-4">
                            <button type="button"
                                    class="btn btn--primary"
                                    id="gban"
                                    name="gban"
                                    data-testid="groupban-bulk-submit"
                                    onclick="CheckGroupBan();">
                                Add group ban
                            </button>
                        </div>
                    </div>

                    <div id="steamGroupStatus"
                         name="steamGroupStatus"
                         class="text-sm" style="width:100%"></div>
                </div>
                {* $list_steam_groups is the literal value of $_GET['fid'] dropped into a JS string argument.
                   Smarty's default HTML escape (init.php's setEscapeHtml(true)) renders `'` as the literal
                   six-character sequence `&#039;` — inside a <script> element the browser does NOT decode
                   character references, so the JS string parser sees those six characters as part of the
                   string contents and never terminates early. Matches the legacy default theme's behaviour.
                   DO NOT add `nofilter` here without an alternative escape (e.g. json_encode in the View);
                   doing so re-opens a reflected XSS via `?p=admin&c=bans&fid=…');alert(1);//`. *}
                <script>
                {literal}
                // Inlined sourcebans.js helper (#1123 D1 prep): the legacy LoadGetGroups lives in
                // sourcebans.js, which sbpp2026 doesn't load. Vanilla replacement against
                // Actions.BansGetGroups; mirrors the legacy DOM-ops shape so #steamGroupsTable
                // rows stay structurally identical (TickSelectAll / CheckGroupBan keep working).
                (function (friendid) {
                    sb.ready(function () {
                        sb.api.call(Actions.BansGetGroups, { friendid: friendid }).then(function (r) {
                            if (!r || !r.ok || !r.data) return;
                            var groups = r.data.groups || [];
                            var tbl = sb.$id('steamGroupsTable');
                            if (!tbl) return;
                            if (groups.length === 0) {
                                sb.message.error('Error', "There was an error retrieving the group data. Maybe the player isn't member of any group or his profile is private?", 'index.php?p=banlist');
                                var txt = sb.$id('steamGroupsText');
                                if (txt) txt.innerHTML = '<i>No groups...</i>';
                                return;
                            }
                            groups.forEach(function (g, i) {
                                var safeUrl = encodeURIComponent(String(g.url || ''));
                                var tr = tbl.insertRow();
                                var td1 = tr.insertCell();
                                td1.style.padding = '0px';
                                td1.style.width = '3px';
                                var cb = document.createElement('input');
                                cb.type = 'checkbox';
                                cb.id = 'chkb_' + i;
                                cb.value = String(g.url || '');
                                td1.appendChild(cb);
                                var td2 = tr.insertCell();
                                var a = document.createElement('a');
                                a.href = 'http://steamcommunity.com/groups/' + safeUrl;
                                a.target = '_blank';
                                a.rel = 'noopener noreferrer';
                                a.textContent = String(g.name || '');
                                td2.appendChild(a);
                                td2.appendChild(document.createTextNode(' ('));
                                var span = document.createElement('span');
                                span.id = 'membcnt_' + i;
                                span.setAttribute('value', String(g.member_count || 0));
                                span.textContent = String(g.member_count || 0);
                                td2.appendChild(span);
                                td2.appendChild(document.createTextNode(' Members)'));
                            });
                            sb.hide('steamGroupsText');
                            sb.show('steamGroups');
                        });
                    });
                })({/literal}'{$list_steam_groups}'{literal});
                {/literal}
                </script>
            {/if}
        </section>
    {/if}
{/if}
