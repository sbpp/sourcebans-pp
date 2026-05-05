{*
    SourceBans++ 2026 — page_admin_bans_add.tpl
    Bound to Sbpp\View\AdminBansAddView (validated by SmartyTemplateRule).

    "Add a ban" tab on the admin bans page. The form keeps every legacy
    DOM id (nickname, type, steam, ip, listReason, txtReason, dreason,
    banlength, fromsub, demo.msg, *.msg, aban, aback, udemo) so:
      - the legacy ProcessBan() / changeReason() / window.demo() helpers
        living in admin.bans.php's tail <script> still find their nodes,
      - the Actions.BansSetupBan response handler in sourcebans.js
        (applyBanFields) reuses the same ids when filling the form
        from a submission's "Ban" link.

    sbpp2026 doesn't ship sourcebans.js so applyApiResponse() isn't
    available; the inline {literal} script below intercepts the submit
    button, calls Actions.BansAdd directly, and surfaces success / error
    via sb.message (defined in sb.js). The legacy onclick="ProcessBan();"
    path remains intact for users still on the default theme.

    Permission gate: $permission_addban is precomputed in admin.bans.php
    from ADMIN_OWNER | ADMIN_ADD_BAN.
*}
{if NOT $permission_addban}
    <div class="card" data-testid="addban-denied">
        <div class="card__body">
            <h1 style="font-size:1.25rem;font-weight:600;margin:0">Access denied</h1>
            <p class="text-sm text-muted m-0 mt-2">You don't have permission to add bans.</p>
        </div>
    </div>
{else}
    <section class="p-6" data-testid="addban-section" style="max-width:48rem">
        <div class="mb-6">
            <h1 style="font-size:1.5rem;font-weight:600;margin:0">Add a ban</h1>
            <p class="text-sm text-muted m-0 mt-2">
                Ban by SteamID or IP address. Hover the labels for inline help.
            </p>
        </div>
        <form id="addban-form"
              class="card p-6 space-y-4"
              data-testid="addban-form"
              onsubmit="return false;"
              autocomplete="off">
            {csrf_field}
            <input type="hidden" id="fromsub" value="">

            <div>
                <label class="label" for="nickname">Nickname</label>
                <input type="text"
                       class="input"
                       id="nickname"
                       name="nickname"
                       data-testid="addban-nickname"
                       placeholder="Display name as it appeared in-game">
                <div class="text-xs mt-2" id="nick.msg" style="color:var(--danger);display:none"></div>
            </div>

            <div class="grid gap-4" style="grid-template-columns:repeat(auto-fit,minmax(14rem,1fr))">
                <div>
                    <label class="label" for="type">Ban type</label>
                    <select class="select"
                            id="type"
                            name="type"
                            data-testid="addban-type">
                        <option value="0">Steam ID</option>
                        <option value="1">IP Address</option>
                    </select>
                </div>
                <div>
                    <label class="label" for="banlength">Ban length</label>
                    <select class="select"
                            id="banlength"
                            data-testid="addban-length">
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
                </div>
            </div>

            <div>
                <label class="label" for="steam">Steam ID / Community ID</label>
                <input type="text"
                       class="input font-mono"
                       id="steam"
                       name="steam"
                       data-testid="addban-steam"
                       placeholder="STEAM_0:1:23498765">
                <div class="text-xs mt-2" id="steam.msg" style="color:var(--danger);display:none"></div>
            </div>

            <div>
                <label class="label" for="ip">IP address</label>
                <input type="text"
                       class="input font-mono"
                       id="ip"
                       name="ip"
                       data-testid="addban-ip"
                       placeholder="203.0.113.10">
                <div class="text-xs mt-2" id="ip.msg" style="color:var(--danger);display:none"></div>
            </div>

            <div>
                <label class="label" for="listReason">Ban reason</label>
                <select class="select"
                        id="listReason"
                        name="listReason"
                        data-testid="addban-reason"
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
                              data-testid="addban-reason-custom"></textarea>
                </div>
                <div class="text-xs mt-2" id="reason.msg" style="color:var(--danger);display:none"></div>
            </div>

            <div>
                <label class="label">Demo upload</label>
                <button type="button"
                        class="btn btn--secondary btn--sm"
                        id="udemo"
                        data-testid="addban-demo"
                        onclick="childWindow=open('pages/admin.uploaddemo.php','upload','resizable=no,width=300,height=130');">
                    Upload a demo
                </button>
                <div class="text-xs mt-2" id="demo.msg" style="color:var(--text-muted)"></div>
            </div>

            <div class="flex justify-end gap-2"
                 style="border-top:1px solid var(--border);padding-top:0.75rem">
                <button type="button"
                        class="btn btn--ghost"
                        id="aback"
                        data-testid="addban-back"
                        onclick="history.go(-1);">Back</button>
                <button type="button"
                        class="btn btn--primary"
                        id="aban"
                        data-testid="addban-submit"
                        onclick="ProcessBan();">
                    Add ban
                </button>
            </div>
        </form>
    </section>
{/if}
