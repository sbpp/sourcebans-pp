{*
    SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
    Licensed under the Elastic License 2.0.
    See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

    "Edit mod" pair: web/pages/admin.edit.mod.php +
    web/includes/View/AdminEditModView.php.

    Submission stays form-POST (admin.edit.mod.php still owns the
    UPDATE round-trip) but error display is now server-side: empty
    `<div id="<field>.msg">` slots paint visible text only when the
    page handler hands a non-empty entry to
    `sbpp_admin_edit_emit_tail_script()`. The icon-picker still pops
    `pages/admin.uploadicon.php`; that handler has been rewritten to
    emit a CSP-friendly inline script that calls `window.opener.icon()`
    via the modernised UploadHandler chrome.

    #1402: the page-tail script below wires `window.opener.icon` so
    the popup's success callback (UploadHandler.php line 187, calling
    `window.opener.icon(<json filename>)`) actually has somewhere
    to land — pre-fix the parent window had no `icon` function
    defined, so the upload popup threw `TypeError: window.opener.icon
    is not a function`, stayed open, and the chosen icon never
    reached the form's hidden `#icon_hid` input.

    Initial checkbox state is server-rendered via the new `$enabled`
    template variable — no MooTools-era `$('enabled').checked = …`
    re-paint script.
*}
<div class="page-section">
<form method="post"
      action=""
      enctype="multipart/form-data"
      autocomplete="off"
      data-testid="editmod-form">
    {csrf_field}
    <div class="card">
        <div class="card__header">
            <div>
                <h3>Edit Mod</h3>
                <p>Update the configuration for this game mod.</p>
            </div>
        </div>
        <div class="card__body space-y-4" style="max-width:42rem">
            <input type="hidden" name="insert_type" value="add">

            {* nofilter: mod metadata is htmlspecialchars(strip_tags($_POST[…]))'d in admin.edit.mod.php before INSERT/UPDATE, so values pulled back out of `:prefix_mods` are already entity-encoded; auto-escaping the value attribute would double-encode (#1113 audit). The id="icon_hid" element is the channel the popup uploader writes into via window.opener.icon(). *}
            <input type="hidden" id="icon_hid" name="icon_hid" value="{$mod_icon nofilter}">

            <div>
                <label class="label" for="name">Mod name</label>
                {* nofilter: see the icon_hid annotation above — name is htmlspecialchars'd on store, double-encoding it in the value attribute would render literal &amp;… to admins (#1108 / #1113 audit). *}
                <input class="input"
                       type="text"
                       id="name"
                       name="name"
                       data-testid="editmod-name"
                       value="{$name nofilter}"
                       required>
                <div id="name.msg"
                     class="text-xs"
                     style="color:var(--danger);display:none;margin-top:0.25rem"></div>
            </div>

            <div>
                <label class="label" for="folder">Mod folder</label>
                {* nofilter: see the icon_hid annotation above — folder is htmlspecialchars'd on store. *}
                <input class="input"
                       type="text"
                       id="folder"
                       name="folder"
                       data-testid="editmod-folder"
                       value="{$folder nofilter}"
                       required>
                <p class="text-xs text-muted" style="margin-top:0.25rem">
                    Folder name on disk (e.g. <span class="font-mono">cstrike</span> for Counter-Strike: Source).
                </p>
                <div id="folder.msg"
                     class="text-xs"
                     style="color:var(--danger);display:none;margin-top:0.25rem"></div>
            </div>

            <div>
                <label class="label" for="steam_universe">Steam universe number</label>
                <input class="input"
                       type="number"
                       id="steam_universe"
                       name="steam_universe"
                       data-testid="editmod-steam_universe"
                       min="0"
                       value="{$steam_universe}"
                       style="max-width:8rem">
                <p class="text-xs text-muted" style="margin-top:0.25rem">
                    First digit (X) of <span class="font-mono">STEAM_X:Y:Z</span> as rendered by this mod.
                </p>
            </div>

            <div>
                <label class="flex items-center gap-2">
                    <input type="checkbox"
                           id="enabled"
                           name="enabled"
                           data-testid="editmod-enabled"
                           value="1"
                           {if $enabled}checked{/if}>
                    <span class="text-sm">Enabled — assignable to bans and servers.</span>
                </label>
            </div>

            <div>
                <label class="label">Mod icon</label>
                <div class="flex items-center gap-3">
                    <button class="btn btn--secondary btn--sm"
                            type="button"
                            data-testid="editmod-upload"
                            onclick="childWindow=open('pages/admin.uploadicon.php','upload','resizable=yes,width=320,height=160');">
                        <i data-lucide="upload"></i>
                        Upload icon
                    </button>
                    {if $mod_icon}
                        <span class="text-xs text-muted">
                            Current:
                            {* nofilter: see the icon_hid annotation above — icon filename is htmlspecialchars'd on store. *}
                            <span class="font-mono" data-testid="editmod-current-icon">{$mod_icon nofilter}</span>
                        </span>
                    {/if}
                </div>
                <div id="icon.msg"
                     class="text-xs"
                     style="color:var(--danger);margin-top:0.25rem"></div>
            </div>

            <div class="flex justify-end gap-2"
                 style="border-top:1px solid var(--border);padding-top:1rem">
                <a class="btn btn--ghost btn--sm"
                   href="javascript:history.go(-1)"
                   data-testid="editmod-cancel">Back</a>
                <button class="btn btn--primary btn--sm"
                        type="submit"
                        id="editmod"
                        data-testid="editmod-submit">
                    <i data-lucide="save"></i>
                    Save changes
                </button>
            </div>
        </div>
    </div>
</form>

{* #1402: wire `window.opener.icon` so admin.uploadicon.php's success
   blob (emitted by Sbpp\Upload\UploadHandler::handle with
   `callback: 'icon'`) can write the filename back into this form. The
   pre-fix shape relied on a `window.icon` definition that lived in the
   pre-v2.0.0 sourcebans.js bulk file (#1123 D1 deleted it), so the
   popup's `window.opener.icon(<filename>)` call threw `TypeError:
   window.opener.icon is not a function`, the popup stayed open, and
   the chosen icon never propagated. The handler also patches the
   visible "Current: …" preview chip so the operator sees what's been
   picked. *}
{literal}
<script>
(function () {
    'use strict';

    // @ts-expect-error - window.icon is the call target for admin.uploadicon.php's
    // window.opener.<cb>() success blob; intentionally untyped on the global.
    window.icon = function (filename) {
        var hid = /** @type {HTMLInputElement|null} */ (document.getElementById('icon_hid'));
        if (hid) hid.value = String(filename || '');
        // Patch the current-icon chip so the operator gets visible feedback
        // without a full form re-render. The chip is rendered conditionally
        // server-side ({if $mod_icon}) so it may not exist on a first-upload;
        // fall through quietly in that case (the hidden input is the
        // load-bearing carrier).
        var label = document.querySelector('[data-testid="editmod-current-icon"]');
        if (label) label.textContent = String(filename || '');
    };
})();
</script>
{/literal}
</div>
