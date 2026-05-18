{*
    SourceBans++ 2026 — page_admin_overrides.tpl

    SourceMod command/group overrides editor. Pair: Sbpp\View\AdminOverridesView.

    Behavior parity with web/themes/default/page_admin_overrides.tpl:
      - Existing rows show editable type/name/flags + a hidden `override_id[]`.
      - Blanking out the name on an existing row deletes it on POST
        (parity with the legacy handler in admin.overrides.php).
      - The bottom "add" row maps to `new_override_*` POST fields.
      - {csrf_field} is required because the dispatcher invokes
        CSRF::rejectIfInvalid() on every POST.

    #1275 — Pattern A `?section=…` routing
    --------------------------------------
    Pre-#1275 this template lived inside the cross-template
    `.page-toc-shell` opened by page_admin_admins_list.tpl, with two
    `<section id="…">` anchor targets ("overrides" and "add-override")
    that the page-level ToC scrolled between. #1275 unifies on
    Pattern A: this template renders by itself when the URL is
    `?section=overrides`, so the section is the whole page. The
    "add an override" form sits inline below the editable table —
    one `<form>`, one Save button, both rows submit together.
*}
<div data-testid="admin-admins-section-overrides">
{if not $permission_addadmin}
    <div class="card">
        <div class="card__body">
            <p class="text-muted m-0">Access denied.</p>
        </div>
    </div>
{else}
    <div class="mb-4">
        <h1 style="font-size:var(--fs-xl);font-weight:600;margin:0">Command &amp; group overrides</h1>
        <p class="text-sm text-muted m-0 mt-2">Override the flags required to run any SourceMod command, globally or per-group.</p>
    </div>

    {if $overrides_error != ""}
        <div class="card mb-4" role="alert" style="border-color:var(--danger);background:var(--danger-bg)">
            <div class="card__body" style="color:#b91c1c">
                <strong>Error</strong>
                {* nofilter: $overrides_error is built by the handler from Exception::getMessage(); any user-controlled override name embedded in it is run through htmlspecialchars(addslashes(...)) in admin.overrides.php before concatenation, and the surrounding wrapper is server-built static HTML — same provenance as the legacy ShowBox() path *}
                <div class="mt-2 text-sm" data-testid="overrides-error">{$overrides_error nofilter}</div>
            </div>
        </div>
    {/if}
    {if $overrides_save_success}
        <div class="card mb-4" role="status" style="border-color:var(--success);background:var(--success-bg)">
            <div class="card__body" style="color:#047857" data-testid="overrides-success">
                <strong>Saved.</strong> The override changes have been applied.
            </div>
        </div>
    {/if}

    <form method="post" action="index.php?p=admin&amp;c=admins&amp;section=overrides" data-testid="overrides-form">
        {csrf_field}

        <div class="card mb-4">
            <div class="card__header">
                <div>
                    <h3>Existing overrides</h3>
                    <p>
                        Blank out a name to delete that override on save.
                        See <a href="https://wiki.alliedmods.net/Overriding_Command_Access_%28SourceMod%29"
                              target="_blank" rel="noopener noreferrer">overriding command access</a>
                        in the AlliedModders wiki for a flag reference.
                    </p>
                </div>
            </div>
            <div class="card__body">
                <table class="table" data-testid="overrides-table">
                    <thead>
                        <tr>
                            <th style="width:8rem">Type</th>
                            <th>Name</th>
                            <th style="width:14rem">Flags</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach from=$overrides_list item=override}
                            <tr data-testid="override-row" data-id="{$override.oid}">
                                <td>
                                    <select class="select" name="override_type[]" aria-label="Override type">
                                        <option value="command"{if $override.type == "command"} selected="selected"{/if}>Command</option>
                                        <option value="group"{if $override.type == "group"} selected="selected"{/if}>Group</option>
                                    </select>
                                    <input type="hidden" name="override_id[]" value="{$override.oid}" />
                                </td>
                                <td>
                                    <input class="input font-mono" name="override_name[]"
                                           value="{$override.command_or_group}"
                                           aria-label="Override name (blank to delete)" />
                                </td>
                                <td>
                                    <input class="input font-mono" name="override_flags[]"
                                           value="{$override.flags}"
                                           aria-label="Override flags" />
                                </td>
                            </tr>
                        {foreachelse}
                            <tr data-testid="overrides-empty">
                                <td colspan="3" class="text-muted text-sm" style="text-align:center;padding:1.5rem">
                                    No overrides configured yet — add one below.
                                </td>
                            </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mb-4" data-testid="admin-admins-section-add-override">
            <div class="card__header">
                <div>
                    <h3>Add an override</h3>
                    <p>Pick a type, give it a command/group name and the flags admins need to run it.</p>
                </div>
            </div>
            <div class="card__body">
                <div class="grid gap-3" style="grid-template-columns:8rem 1fr 14rem">
                    <div>
                        <label class="label" for="addoverride-type">Type</label>
                        <select class="select" id="addoverride-type" name="new_override_type"
                                data-testid="addoverride-type">
                            <option value="command">Command</option>
                            <option value="group">Group</option>
                        </select>
                    </div>
                    <div>
                        <label class="label" for="addoverride-name">Name</label>
                        <input class="input font-mono" id="addoverride-name" name="new_override_name"
                               data-testid="addoverride-name" autocomplete="off" />
                    </div>
                    <div>
                        <label class="label" for="addoverride-flags">Flags</label>
                        <input class="input font-mono" id="addoverride-flags" name="new_override_flags"
                               data-testid="addoverride-flags" autocomplete="off" />
                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a class="btn btn--ghost" href="javascript:history.go(-1);" data-testid="overrides-back">Back</a>
            <button class="btn btn--primary" type="submit" data-testid="overrides-save">Save changes</button>
        </div>
    </form>
{/if}
</div>
