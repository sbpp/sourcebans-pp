{*
    SourceBans++ 2026 — page / page_admin_mods_add.tpl

    Second tab of the admin "Mods" page (add a new mod). Pair:
    Sbpp\View\AdminModsAddView + web/pages/admin.mods.php.

    Submission flow (preserved end-to-end from the legacy theme):
        1. <button data-testid="addmod-submit"> calls ProcessMod()
           (web/scripts/sourcebans.js), which validates the inputs and
           POSTs the JSON payload via sb.api.call(Actions.ModsAdd, …).
           api.js handles the CSRF header automatically; the
           {csrf_field} hidden input is included for any future
           server-side fallback (no-JS) submission.
        2. The icon picker pops pages/admin.uploadicon.php in a child
           window. On successful upload the popup calls
           window.opener.icon('<filename>'), which sets the icname
           closure variable that ProcessMod() reads back as the icon
           field. The form's enctype="multipart/form-data" matches the
           upload window's content-type so a future direct upload
           (instead of the popup) drops in without changing markup.

    Variable contract: only $permission_add. The default theme's
    page_admin_mods_add.tpl uses the same name; the dual-theme PHPStan
    matrix (#1123 A2) enforces the join through Sbpp\View\AdminModsAddView.

    Testability hooks: every interactive control carries
    data-testid="addmod-<field>" so end-to-end tests can address it
    without depending on element ids (which double as JS hooks for the
    legacy ProcessMod() function — left intact to keep the wiring
    in this Phase B PR's scope).
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
          onsubmit="ProcessMod(); return false;"
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
                <input type="hidden" id="fromsub" value="">

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
                    <button class="btn btn--secondary btn--sm"
                            type="button"
                            data-testid="addmod-upload"
                            onclick="childWindow=open('pages/admin.uploadicon.php','upload','resizable=yes,width=320,height=160');">
                        <i data-lucide="upload"></i>
                        Upload icon
                    </button>
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
{/if}
</div>
