{*
    SourceBans++ 2026 — page_admin_edit_comms.tpl

    Edit-existing-block form on the admin comms page. Bound to
    Sbpp\View\AdminCommsEditView; SmartyTemplateRule cross-checks
    referenced vars against that DTO.

    Variable contract matches the legacy default theme:
      - $ban_name   — current player name on the comms row.
      - $ban_authid — current authid (Steam2) on the comms row.

    Length / type / current-reason are NOT Smarty vars: the page
    handler emits an inline `<script>selectLengthTypeReason(…)</script>`
    after rendering the form, hydrating the <select>s post-mount.
    This mirrors the legacy convention preserved during the v2.0.0
    rollout window so the same View satisfies SmartyTemplateRule on
    both theme legs of the CI matrix (#1123 A2). Per-handler errors
    from the previous POST are surfaced through the same legacy
    `<script>$('steam.msg')…</script>` block at the end of
    web/pages/admin.edit.comms.php — the inline error containers
    below have IDs the script targets.

    The form POSTs to itself (action="" preserves the URL incl.
    ?id, ?key, ?page); admin.edit.comms.php validates the CSRF token
    and writes the row.

    Testability hooks per the issue's "Testability hooks" rule:
      - data-testid="editcomm-<field>" on every input/select.
      - data-testid="editcomm-submit" / "editcomm-back" on buttons.
*}
<div class="p-6" style="max-width:48rem">
    <div class="mb-6">
        <h1 style="font-size:1.5rem;font-weight:600;margin:0">Edit block</h1>
        <p class="text-sm text-muted m-0 mt-2">Update the player name, authid, type, length, or reason on this block.</p>
    </div>

    <form id="editcomm-form" class="card p-6 space-y-4" method="post" action="" data-testid="editcomm-form">
        {csrf_field}
        <input type="hidden" name="insert_type" value="add">

        <div>
            <label class="label" for="name">Player name</label>
            <input type="text"
                   class="input"
                   id="name"
                   name="name"
                   value="{$ban_name|escape}"
                   data-testid="editcomm-name">
            <div class="text-xs mt-2" id="name.msg" style="color:var(--danger);display:none"></div>
        </div>

        <div>
            <label class="label" for="steam">Steam ID / Community ID</label>
            <input type="text"
                   class="input font-mono"
                   id="steam"
                   name="steam"
                   value="{$ban_authid|escape}"
                   data-testid="editcomm-steam">
            <div class="text-xs mt-2" id="steam.msg" style="color:var(--danger);display:none"></div>
        </div>

        <div class="grid gap-4" style="grid-template-columns:1fr 1fr">
            <div>
                <label class="label" for="type">Block type</label>
                <select class="select" id="type" name="type" data-testid="editcomm-type">
                    <option value="1">Mute (voice)</option>
                    <option value="2">Gag (chat)</option>
                </select>
            </div>
            <div>
                <label class="label" for="banlength">Block length</label>
                <select class="select" id="banlength" name="banlength" data-testid="editcomm-length">
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
                <div class="text-xs mt-2" id="length.msg" style="color:var(--danger);display:none"></div>
            </div>
        </div>

        <div>
            <label class="label" for="listReason">Block reason</label>
            <select class="select"
                    id="listReason"
                    name="listReason"
                    data-testid="editcomm-reason"
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
                <option value="other">Custom</option>
            </select>
            <div id="dreason" class="mt-2" style="display:none">
                <textarea class="textarea"
                          id="txtReason"
                          name="txtReason"
                          rows="4"
                          data-testid="editcomm-reason-custom"></textarea>
            </div>
            <div class="text-xs mt-2" id="reason.msg" style="color:var(--danger);display:none"></div>
        </div>

        <div class="flex justify-end gap-2" style="border-top:1px solid var(--border);padding-top:0.75rem">
            <button type="button"
                    class="btn btn--ghost"
                    data-testid="editcomm-back"
                    onclick="history.go(-1);">Back</button>
            <button type="submit"
                    class="btn btn--primary"
                    id="editcomm-submit"
                    data-testid="editcomm-submit">
                <i data-lucide="save"></i> Save changes
            </button>
        </div>
    </form>
</div>
