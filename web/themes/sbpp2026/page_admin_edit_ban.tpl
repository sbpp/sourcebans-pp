{*
    SourceBans++ 2026 — page_admin_edit_ban.tpl
    Bound to Sbpp\View\AdminBansEditView (validated by SmartyTemplateRule).

    Edit-existing-ban form — the sister of page_admin_bans_add.tpl
    (B13). The legacy default theme's template uses Smarty's custom
    `-{ … }-` delimiter pair (a SourceBans-1.x oddity preserved on the
    edit-ban page only); the sbpp2026 redesign drops that override and
    renders with the standard `{ … }` pair. The page handler picks one
    delimiter set unconditionally — see web/pages/admin.edit.ban.php
    for the "no per-theme conditional" rationale (#1123 B13 follow-up).

    DOM ids (`name`, `type`, `steam`, `ip`, `listReason`, `txtReason`,
    `dreason`, `banlength`, `did`, `dname`, `nick.msg`, `steam.msg`,
    `ip.msg`, `reason.msg`, `length.msg`, `demo.msg`, `editban`,
    `back`) match the legacy template exactly so the page handler's
    tail script (`changeReason`, `selectLengthTypeReason`, `demo`) can
    target them by `document.getElementById(...)` without a per-theme
    fork. The popup window opened by `pages/admin.uploaddemo.php` also
    calls `window.opener.demo(id, name)` against those same ids.

    sbpp2026 doesn't load `web/scripts/sourcebans.js`, so the legacy
    `selectLengthTypeReason` / `ShowBox` helpers are unavailable. The
    handler emits a vanilla replacement script after Renderer::render
    (defined inline against window.SBPP.showToast / sb.message as a
    fallback). That keeps THIS template purely declarative — no
    inline {literal} block needed for client-side glue beyond the
    server-emitted tail.

    Form posts back to action="" (preserves the URL incl. ?id, ?key,
    ?page); admin.edit.ban.php validates the CSRF token and writes the
    row. Server-side validation errors (per field) are pushed into the
    `*.msg` divs below by the handler's tail script (vanilla DOM, no
    MooTools).

    Testability hooks per the issue's "Testability hooks" rule:
      - data-testid="editban-<field>" on every input/select.
      - data-testid="editban-submit" / "editban-cancel" on buttons.

    Permission gate: $can_edit_ban is precomputed in admin.edit.ban.php
    (row-aware: ADMIN_OWNER | ADMIN_EDIT_ALL_BANS, or own/group ban
    with the matching flag). The handler also early-PageDie's on
    failure, so this template gate is defense-in-depth for the case
    where the View is ever instantiated without that upstream check.
*}
{if NOT $can_edit_ban}
    <div class="card" data-testid="editban-denied">
        <div class="card__body">
            <h1 style="font-size:1.25rem;font-weight:600;margin:0">Access denied</h1>
            <p class="text-sm text-muted m-0 mt-2">You don't have permission to edit this ban.</p>
        </div>
    </div>
{else}
    <section class="p-6" data-testid="editban-section" style="max-width:48rem">
        <div class="mb-6">
            <h1 style="font-size:1.5rem;font-weight:600;margin:0">Edit ban</h1>
            <p class="text-sm text-muted m-0 mt-2">
                Update the player name, identifier, length, or reason on this ban.
            </p>
        </div>

        <form id="editban-form"
              class="card p-6 space-y-4"
              method="post"
              action=""
              data-testid="editban-form"
              autocomplete="off">
            {csrf_field}
            <input type="hidden" name="insert_type" value="add">

            <div>
                <label class="label" for="name">Player name</label>
                <input type="text"
                       class="input"
                       id="name"
                       name="name"
                       value="{$ban_name|escape}"
                       data-testid="editban-name">
                <div class="text-xs mt-2"
                     id="name.msg"
                     style="color:var(--danger);display:none"></div>
            </div>

            <div class="grid gap-4" style="grid-template-columns:repeat(auto-fit,minmax(14rem,1fr))">
                <div>
                    <label class="label" for="type">Ban type</label>
                    <select class="select"
                            id="type"
                            name="type"
                            data-testid="editban-type">
                        <option value="0">Steam ID</option>
                        <option value="1">IP Address</option>
                    </select>
                </div>
                <div>
                    <label class="label" for="banlength">Ban length</label>
                    <select class="select"
                            id="banlength"
                            name="banlength"
                            data-testid="editban-length">
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
                            <option value="43200">1 month</option>
                            <option value="86400">2 months</option>
                            <option value="129600">3 months</option>
                            <option value="259200">6 months</option>
                            <option value="518400">12 months</option>
                        </optgroup>
                    </select>
                    <div class="text-xs mt-2"
                         id="length.msg"
                         style="color:var(--danger);display:none"></div>
                </div>
            </div>

            <div>
                <label class="label" for="steam">Steam ID / Community ID</label>
                <input type="text"
                       class="input font-mono"
                       id="steam"
                       name="steam"
                       value="{$ban_authid|escape}"
                       data-testid="editban-steam"
                       placeholder="STEAM_0:1:23498765">
                <div class="text-xs mt-2"
                     id="steam.msg"
                     style="color:var(--danger);display:none"></div>
            </div>

            <div>
                <label class="label" for="ip">IP address</label>
                <input type="text"
                       class="input font-mono"
                       id="ip"
                       name="ip"
                       value="{$ban_ip|escape}"
                       data-testid="editban-ip"
                       placeholder="203.0.113.10">
                <div class="text-xs mt-2"
                     id="ip.msg"
                     style="color:var(--danger);display:none"></div>
            </div>

            <div>
                <label class="label" for="listReason">Ban reason</label>
                <select class="select"
                        id="listReason"
                        name="listReason"
                        data-testid="editban-reason"
                        onchange="changeReason(this[this.selectedIndex].value);">
                    <option value="" selected>-- Select reason --</option>
                    <optgroup label="Hacking">
                        <option value="Aimbot">Aimbot</option>
                        <option value="Antirecoil">Antirecoil</option>
                        <option value="Wallhack">Wallhack</option>
                        <option value="Spinhack">Spinhack</option>
                        <option value="Multi-Hack">Multi-Hack</option>
                        <option value="No Smoke">No Smoke</option>
                        <option value="No Flash">No Flash</option>
                    </optgroup>
                    <optgroup label="Behavior">
                        <option value="Team Killing">Team Killing</option>
                        <option value="Team Flashing">Team Flashing</option>
                        <option value="Spamming Mic/Chat">Spamming Mic/Chat</option>
                        <option value="Inappropriate Spray">Inappropriate Spray</option>
                        <option value="Inappropriate Language">Inappropriate Language</option>
                        <option value="Inappropriate Name">Inappropriate Name</option>
                        <option value="Ignoring Admins">Ignoring Admins</option>
                        <option value="Team Stacking">Team Stacking</option>
                    </optgroup>
                    {if $customreason}
                        <optgroup label="Custom">
                            {foreach from=$customreason item="creason"}
                                {* nofilter: bans.customreasons round-trips through htmlspecialchars in admin.settings.php before serialize() into sb_settings, so the value is already entity-encoded; auto-escaping would double-encode. *}
                                <option value="{$creason nofilter}">{$creason nofilter}</option>
                            {/foreach}
                        </optgroup>
                    {/if}
                    <option value="other">Other reason</option>
                </select>
                <div id="dreason" class="mt-2" style="display:none">
                    <textarea class="textarea"
                              id="txtReason"
                              name="txtReason"
                              rows="4"
                              placeholder="Explain in detail why this ban is being made."
                              data-testid="editban-reason-custom"></textarea>
                </div>
                <div class="text-xs mt-2"
                     id="reason.msg"
                     style="color:var(--danger);display:none"></div>
            </div>

            <div>
                <label class="label">Demo upload</label>
                <button type="button"
                        class="btn btn--secondary btn--sm"
                        id="uploaddemo"
                        data-testid="editban-demo"
                        onclick="childWindow=open('pages/admin.uploaddemo.php','upload','resizable=no,width=300,height=130');">
                    Upload a demo
                </button>
                <input type="hidden" name="did" id="did" value="">
                <input type="hidden" name="dname" id="dname" value="">
                {* nofilter: $ban_demo is empty-or `Uploaded: <b>` + htmlspecialchars($res['dname'], ENT_QUOTES, 'UTF-8') + `</b>` built in admin.edit.ban.php. The `<b>…</b>` literals are server-controlled; the user-supplied dname is escaped on store-side per #1113 so dropping it raw here is safe. *}
                <div class="text-xs mt-2" id="demo.msg" style="color:#cc0000">{$ban_demo nofilter}</div>
            </div>

            <div class="flex justify-end gap-2"
                 style="border-top:1px solid var(--border);padding-top:0.75rem">
                <button type="button"
                        class="btn btn--ghost"
                        id="back"
                        data-testid="editban-cancel"
                        onclick="history.go(-1);">Back</button>
                <button type="submit"
                        class="btn btn--primary"
                        id="editban"
                        data-testid="editban-submit">
                    Save changes
                </button>
            </div>
        </form>
    </section>
{/if}
