{*
    SourceBans++ 2026 — page / page_admin_edit_mod.tpl

    Edit form for a single mod. Pair: Sbpp\View\AdminEditModView +
    web/pages/admin.edit.mod.php (the latter is intentionally NOT in
    this PR's scope — see AdminEditModView's docblock for why).

    Variable contract (matches the $theme->assign() calls in
    web/pages/admin.edit.mod.php):
        - $name           — current mod name
        - $folder         — current mod folder
        - $mod_icon       — current icon filename
        - $steam_universe — int-as-string from PDO

    Submission flow:
        - <form method="post" action=""> POSTs back to the same page.
          admin.edit.mod.php inspects $_POST['name'] to decide whether
          to validate + UPDATE the row.
        - {csrf_field} is required; admin.edit.mod.php currently
          relies on the existing page-level CSRF middleware, so the
          field doubles as the explicit token for any future direct
          POST handler.
        - The icon picker pops pages/admin.uploadicon.php which calls
          window.opener.icon('<filename>') on success; that wires the
          filename into the hidden #icon_hid input that the page
          handler reads as $_POST['icon_hid'].
        - The "Enabled" checkbox is rendered without a `checked`
          attribute on purpose: admin.edit.mod.php emits an inline
          <script> AFTER this template that sets
          $('enabled').checked from the row's `enabled` column. That
          keeps the page handler out of this Phase B PR's scope.

    Testability hooks: every interactive control carries
    data-testid="editmod-<field>" so end-to-end tests can address it.
    HTML form ids stay intact because admin.edit.mod.php's inline
    error-handling script targets them by id (legacy MooTools-style
    `$('name.msg')`).
*}
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
                           value="1">
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
