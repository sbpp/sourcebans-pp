{*
    SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
    Licensed under the Elastic License 2.0.
    See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

    Second tab of the admin "Mods" page (add a new mod). Pair:
    Sbpp\View\AdminModsAddView + web/pages/admin.mods.php.

    Submission flow (#1402):
        1. The form's submit handler intercepts the click, validates
           name + folder client-side (the same shape the v1.x ProcessMod()
           helper in sourcebans.js performed), and POSTs the JSON payload
           via sb.api.call(Actions.ModsAdd, …). api.js handles the
           X-CSRF-Token header automatically; the {csrf_field} hidden
           input is included so a future server-side fallback works without
           markup changes.
        2. The icon picker pops pages/admin.uploadicon.php in a child
           window. On successful upload the popup calls
           window.opener.icon('<filename>'), which (#1402) writes the
           filename into a hidden `#icon_hid` input that the submit
           handler reads back as the `icon` field. Mirrors the shape
           page_admin_edit_mod.tpl uses so the two surfaces agree on
           the icon-callback contract.

    The pre-fix shape called a long-deleted `ProcessMod()` JS helper
    from sourcebans.js (removed at #1123 D1). The form's
    `onsubmit="ProcessMod(); return false;"` swallowed the native
    submit unconditionally, so every click of "Add mod" silently did
    nothing — no console error, no toast, no form POST. Likewise the
    upload popup tried to write into a `window.opener.icon` function
    that no longer existed, so a successful icon upload threw
    `TypeError: window.opener.icon is not a function`, the popup
    stayed open, and the operator's chosen icon never reached the
    form.

    Variable contract: only $permission_add. The default theme's
    page_admin_mods_add.tpl uses the same name; the dual-theme PHPStan
    matrix (#1123 A2) enforces the join through Sbpp\View\AdminModsAddView.

    Testability hooks: every interactive control carries
    data-testid="addmod-<field>" so end-to-end tests can address it
    without depending on element ids. The icon-callback hidden input is
    `#icon_hid` to mirror the edit-mod template.
*}
<div class="page-section">
{if NOT $permission_add}
    <div class="card">
        <div class="card__body">
            <p class="text-muted">Access denied.</p>
        </div>
    </div>
{else}
    <form method="post"
          action=""
          enctype="multipart/form-data"
          autocomplete="off"
          id="addmod-form"
          data-testid="addmod-form">
        {csrf_field}
        <div class="card">
            <div class="card__header">
                <div>
                    <h3>Add Mod</h3>
                    <p>Configure a new game mod that can be assigned to bans and servers.</p>
                </div>
            </div>
            <div class="card__body space-y-4" style="max-width:42rem">
                {* #1402: the legacy `<input id="fromsub">` hidden was a vestigial
                   reference to the v1.x ProcessMod() flow; the new submit handler
                   has no equivalent. Replaced with `#icon_hid` so the upload-icon
                   popup's `window.opener.icon()` callback has a stable target
                   (sister-shape to page_admin_edit_mod.tpl). *}
                <input type="hidden" id="icon_hid" name="icon_hid" value="" data-testid="addmod-icon-hidden">

                <div>
                    <label class="label" for="name">Mod name</label>
                    <input class="input"
                           type="text"
                           id="name"
                           name="name"
                           data-testid="addmod-name"
                           required>
                    <div id="name.msg"
                         class="text-xs"
                         style="color:var(--danger);display:none;margin-top:0.25rem"></div>
                </div>

                <div>
                    <label class="label" for="folder">Mod folder</label>
                    <input class="input"
                           type="text"
                           id="folder"
                           name="folder"
                           data-testid="addmod-folder"
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
                           data-testid="addmod-steam_universe"
                           min="0"
                           value="0"
                           style="max-width:8rem">
                    <p class="text-xs text-muted" style="margin-top:0.25rem">
                        First digit (X) of <span class="font-mono">STEAM_X:Y:Z</span> as rendered by this mod. Default 0.
                    </p>
                </div>

                <div>
                    <label class="flex items-center gap-2">
                        <input type="checkbox"
                               id="enabled"
                               name="enabled"
                               data-testid="addmod-enabled"
                               value="1"
                               checked>
                        <span class="text-sm">Enabled — assignable to bans and servers.</span>
                    </label>
                </div>

                <div>
                    <label class="label">Mod icon</label>
                    <div class="flex items-center gap-3">
                        <button class="btn btn--secondary btn--sm"
                                type="button"
                                data-testid="addmod-upload"
                                onclick="childWindow=open('pages/admin.uploadicon.php','upload','resizable=yes,width=320,height=160');">
                            <i data-lucide="upload"></i>
                            Upload icon
                        </button>
                        <span class="text-xs text-muted" data-testid="addmod-current-icon" hidden>
                            Current: <span class="font-mono" data-testid="addmod-current-icon-name"></span>
                        </span>
                    </div>
                    <p class="text-xs text-muted" style="margin-top:0.25rem">
                        16x16 GIF, PNG or JPG. Opens a popup uploader.
                    </p>
                    <div id="icon.msg"
                         class="text-xs"
                         style="color:var(--danger);margin-top:0.25rem"></div>
                </div>

                <div class="flex justify-end gap-2"
                     style="border-top:1px solid var(--border);padding-top:1rem">
                    <a class="btn btn--ghost btn--sm"
                       href="javascript:history.go(-1)"
                       data-testid="addmod-cancel">Back</a>
                    <button class="btn btn--primary btn--sm"
                            type="submit"
                            id="amod"
                            data-testid="addmod-submit">
                        <i data-lucide="plus"></i>
                        Add mod
                    </button>
                </div>
            </div>
        </div>
    </form>

    {* ============================================================
       #1402 — Add-mod constructive form wiring (inline page-tail JS).

       Replaces the v1.x `ProcessMod()` helper (deleted with
       sourcebans.js at #1123 D1) — pre-fix the form's
       `onsubmit="ProcessMod(); return false;"` swallowed the native
       submit but never dispatched to anything, so the Add-mod button
       was a silent no-op (no console error, no toast, no API call,
       no row created). Also wires `window.opener.icon()` (called by
       admin.uploadicon.php's success blob via
       Sbpp\Upload\UploadHandler::handle's `callback: 'icon'`) into
       the hidden `#icon_hid` input so the chosen icon filename
       actually rides the form submission.

       Constructive-form pattern mirrors `SbppGroupsAdd` in
       page_admin_groups_add.tpl: intercept submit, client-side
       validate, busy-flip the submit button via SBPP.setBusy, fire
       sb.api.call(Actions.ModsAdd, …), branch on the envelope.

       No `// @ts-check` here because the file is rendered by Smarty;
       ts-check only runs against `.js` sources in `web/scripts`.
       ============================================================ *}
    {literal}
    <script>
    (function () {
        'use strict';

        /** @returns {{call: (a:string,p?:object)=>Promise<any>}|null} */
        function api()     { return (window.sb && window.sb.api) || null; }
        /** @returns {Record<string,string>|null} */
        function actions() { return /** @type {any} */ (window).Actions || null; }
        function toast(kind, title, body) {
            var S = /** @type {any} */ (window).SBPP;
            if (S && typeof S.showToast === 'function') {
                S.showToast({ kind: kind, title: title, body: body || '' });
            }
        }
        /**
         * Flip the busy / loading state on the triggered submit button. Calls
         * window.SBPP.setBusy when present (theme.js owns the spinner CSS
         * contract) and falls back to plain `disabled` so third-party themes
         * that strip theme.js still gate against double-clicks.
         * @param {Element|null} btn
         * @param {boolean} [busy] defaults to true
         */
        function setBusy(btn, busy) {
            if (!btn) return;
            var S = /** @type {any} */ (window).SBPP;
            if (S && typeof S.setBusy === 'function') S.setBusy(btn, busy);
            else /** @type {HTMLButtonElement} */ (btn).disabled = busy === undefined ? true : !!busy;
        }

        /** @param {string} id @param {string} msg */
        function showMsg(id, msg) {
            var el = document.getElementById(id);
            if (!el) return;
            el.innerHTML = msg;
            el.style.display = 'block';
        }
        /** @param {string} id */
        function clearMsg(id) {
            var el = document.getElementById(id);
            if (!el) return;
            el.innerHTML = '';
            el.style.display = 'none';
        }
        function clearAllMsgs() {
            ['name.msg', 'folder.msg', 'icon.msg'].forEach(clearMsg);
        }

        // window.opener.icon() — sister shape to the `demo()` callback in
        // admin.bans.php / admin.edit.ban.php. UploadHandler.php JSON-encodes
        // the filename so quotes/angle brackets survive the round-trip; we
        // just plant the value verbatim into the hidden input + update the
        // "Current: …" preview chip so the operator sees what's been picked.
        // @ts-expect-error
        window.icon = function (filename) {
            var hid = /** @type {HTMLInputElement|null} */ (document.getElementById('icon_hid'));
            if (hid) hid.value = String(filename || '');
            var chip  = document.querySelector('[data-testid="addmod-current-icon"]');
            var label = document.querySelector('[data-testid="addmod-current-icon-name"]');
            if (chip && label) {
                if (filename) {
                    label.textContent = String(filename);
                    chip.removeAttribute('hidden');
                } else {
                    label.textContent = '';
                    chip.setAttribute('hidden', '');
                }
            }
        };

        document.addEventListener('submit', function (e) {
            var form = /** @type {Element|null} */ (e.target);
            if (!form || form.id !== 'addmod-form') return;
            e.preventDefault();
            clearAllMsgs();

            var nameEl   = /** @type {HTMLInputElement|null} */ (document.getElementById('name'));
            var folderEl = /** @type {HTMLInputElement|null} */ (document.getElementById('folder'));
            var iconEl   = /** @type {HTMLInputElement|null} */ (document.getElementById('icon_hid'));
            var suEl     = /** @type {HTMLInputElement|null} */ (document.getElementById('steam_universe'));
            var enEl     = /** @type {HTMLInputElement|null} */ (document.getElementById('enabled'));

            var name   = nameEl   ? nameEl.value.trim()   : '';
            var folder = folderEl ? folderEl.value.trim() : '';
            var icon   = iconEl   ? iconEl.value.trim()   : '';
            var su     = suEl     ? Number(suEl.value)    : 0;
            var enabled = enEl ? !!enEl.checked : false;

            var errors = 0;
            if (!name)   { showMsg('name.msg',   'You must type a name for the mod.'); errors++; }
            if (!folder) { showMsg('folder.msg', "You must enter the mod's folder name."); errors++; }
            if (!icon)   { showMsg('icon.msg',   'You must upload an icon for the mod.'); errors++; }
            if (errors > 0) return;

            var submitBtn = /** @type {HTMLButtonElement|null} */ (form.querySelector('[data-testid="addmod-submit"]'));
            setBusy(submitBtn, true);

            var a = api(), A = actions();
            if (!a || !A) {
                setBusy(submitBtn, false);
                showMsg('name.msg', 'JSON dispatcher missing. Refresh the page and try again.');
                return;
            }

            a.call(A.ModsAdd, {
                name: name,
                folder: folder,
                icon: icon,
                steam_universe: Number.isFinite(su) ? su : 0,
                enabled: enabled ? 1 : 0,
            }).then(function (r) {
                if (!r) { setBusy(submitBtn, false); return; }
                if (r.redirect) return; // sb.api.call already navigated
                if (r.ok === false) {
                    setBusy(submitBtn, false);
                    var em = (r.error && r.error.message) || 'Unknown error';
                    var field = (r.error && r.error.field) || 'name';
                    // Map handler field codes to our `.msg` slot ids.
                    var slot = field === 'folder' ? 'folder.msg'
                        : field === 'icon' ? 'icon.msg'
                        : 'name.msg';
                    showMsg(slot, em);
                    toast('error', 'Add mod failed', em);
                    return;
                }
                var data = r.data || {};
                var msg = data.message || {};
                toast('success', msg.title || 'Mod added', msg.body || 'The mod has been added.');
                // Leave the button busy across the navigation so the form
                // can't be re-submitted while the redirect resolves.
                setTimeout(function () {
                    window.location.href = (msg.redir || 'index.php?p=admin&c=mods&section=list');
                }, 800);
            }).catch(function (err) {
                // #1402 adversarial review MEDIUM 4: defensive .catch()
                // arm so a throw escaping the success callback (or a
                // sb.api.call internal failure) doesn't leave the
                // submit button busy forever. Per AGENTS.md "Loading
                // state on action buttons" — setBusy(btn, false) on
                // every non-navigating response branch.
                setBusy(submitBtn, false);
                showMsg('name.msg', String(err && err.message ? err.message : err));
                toast('error', 'Add mod failed', String(err && err.message ? err.message : err));
            });
        });
    })();
    </script>
    {/literal}
{/if}
</div>
