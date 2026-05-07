{*
    SourceBans++ 2026 — page_admin_comms_add.tpl

    "Add a block" form on the admin comms page. Bound to
    Sbpp\View\AdminCommsAddView; SmartyTemplateRule cross-checks
    referenced vars against that DTO.

    Variable contract matches the legacy default theme:
      - $permission_addban — gate; ADMIN_OWNER|ADMIN_ADD_BAN
        (precomputed in admin.comms.php via Perms::for($userbank)).

    Submission goes through sb.api.call(Actions.CommsAdd) — the same
    JSON action the legacy theme uses, see
    web/api/handlers/comms.php. The inline ProcessBan() / changeReason()
    helpers live in web/pages/admin.comms.php's tail <script> block
    (legacy convention preserved during the v2.0.0 rollout); this
    template intentionally keeps the legacy DOM ids (nickname, steam,
    type, banlength, listReason, txtReason, dreason, *.msg) so those
    helpers keep working without modification.

    Testability hooks per the issue's "Testability hooks" rule:
      - data-testid="addcomm-<field>" on every input/select.
      - data-testid="addcomm-submit" / "addcomm-back" on buttons.
*}
{if not $permission_addban}
    <div class="card" data-testid="addcomm-denied">
        <div class="card__body">
            <h1 style="font-size:1.25rem;font-weight:600;margin:0">Access denied</h1>
            <p class="text-sm text-muted m-0 mt-2">You don't have permission to add blocks.</p>
        </div>
    </div>
{else}
    {* #1266 — outer `.p-6` keeps the 1.5rem page inset because
       `admin.comms.php` is a *single-section* page (simplified at
       #1239 — no `AdminTabs` call, no `.admin-sidebar-shell`
       wrapper), so this template is rendered directly under the
       chrome's `<main class="page">` which has no padding of its
       own. The `max-width: 48rem` form clamp keeps the form column
       from growing past a readable line length on wide viewports. *}
    <div class="p-6" style="max-width:48rem">
        <div class="mb-6">
            <h1 style="font-size:1.5rem;font-weight:600;margin:0">Add a block</h1>
            <p class="text-sm text-muted m-0 mt-2">Mute, gag, or silence a player on every connected server.</p>
        </div>

        <form id="addcomm-form" class="card p-6 space-y-4" onsubmit="return false;" data-testid="addcomm-form">
            <div>
                <label class="label" for="nickname">Nickname</label>
                <input type="text"
                       class="input"
                       id="nickname"
                       name="nickname"
                       autocomplete="off"
                       data-testid="addcomm-nickname"
                       placeholder="Display name as it appeared in-game">
                <input type="hidden" id="fromsub" value="">
                <div class="text-xs mt-2" id="nick.msg" style="color:var(--danger);display:none"></div>
            </div>

            <div>
                <label class="label" for="steam">Steam ID / Community ID</label>
                <input type="text"
                       class="input font-mono"
                       id="steam"
                       name="steam"
                       autocomplete="off"
                       data-testid="addcomm-steam"
                       placeholder="STEAM_0:1:23498765">
                <div class="text-xs mt-2" id="steam.msg" style="color:var(--danger);display:none"></div>
            </div>

            <div class="grid gap-4" style="grid-template-columns:1fr 1fr">
                <div>
                    <label class="label" for="type">Block type</label>
                    <select class="select" id="type" name="type" data-testid="addcomm-type">
                        <option value="1">Mute (voice)</option>
                        <option value="2">Gag (chat)</option>
                        <option value="3">Silence (chat &amp; voice)</option>
                    </select>
                </div>
                <div>
                    <label class="label" for="banlength">Block length</label>
                    <select class="select" id="banlength" name="banlength" data-testid="addcomm-length">
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
                <label class="label" for="listReason">Block reason</label>
                <select class="select"
                        id="listReason"
                        name="listReason"
                        data-testid="addcomm-reason"
                        onchange="changeReason(this[this.selectedIndex].value);">
                    <option value="" selected>-- Select reason --</option>
                    <optgroup label="Violation">
                        <option value="Obscene language">Obscene language</option>
                        <option value="Insult players">Insult players</option>
                        <option value="Admin disrespect">Admin disrespect</option>
                        <option value="Inappropriate Language">Inappropriate language</option>
                        <option value="Trading">Trading</option>
                        <option value="Spam in chat/voice">Spam</option>
                        <option value="Advertisement">Advertisement</option>
                    </optgroup>
                    <option value="other">Other reason</option>
                </select>
                <div id="dreason" class="mt-2" style="display:none">
                    <textarea class="textarea"
                              id="txtReason"
                              name="txtReason"
                              rows="4"
                              placeholder="Explain in detail why this block is being made."
                              data-testid="addcomm-reason-custom"></textarea>
                </div>
                <div class="text-xs mt-2" id="reason.msg" style="color:var(--danger);display:none"></div>
            </div>

            <div class="flex justify-end gap-2" style="border-top:1px solid var(--border);padding-top:0.75rem">
                <button type="button"
                        class="btn btn--ghost"
                        data-testid="addcomm-back"
                        onclick="history.go(-1);">Back</button>
                <button type="button"
                        class="btn btn--primary"
                        id="addcomm-submit"
                        data-testid="addcomm-submit"
                        onclick="ProcessBan();">
                    <i data-lucide="mic-off"></i> Add block
                </button>
            </div>
        </form>
    </div>
{/if}
