{*
    SourceBans++ 2026 — page_admin_groups_add.tpl

    "Add a group" tab. Plain card form: name + type + (type-dependent
    extras). Mirrors the legacy add form's behaviour — the lazy-loaded
    flag selector lives on the master-detail editor in the list tab
    (#1123 B12), so this form intentionally stays minimal.

    The form posts via `sb.api.call(Actions.GroupsAdd, …)`; the
    {csrf_field} hidden input is included so an unsupported-JS
    fallback would still ship a valid token if a future change wires
    a server-side POST handler. State updates currently flow through
    the existing JSON API.
*}
{if NOT $permission_addgroup}
    <div class="card"><div class="card__body"><p class="text-muted m-0">Access denied.</p></div></div>
{else}
{* #1228: `id="add-group"` is the anchor target the empty-state CTAs in
   page_admin_groups_list.tpl jump to (`?p=admin&c=groups#add-group`).
   The page renders both `tabcontent` divs simultaneously since #1123 D1
   dropped the `openTab()` JS, so the fragment scrolls the user to the
   Add form regardless of which tab they came from. *}
<div class="p-6" id="add-group" style="max-width:48rem">
    <div class="mb-4">
        <h1 style="font-size:var(--fs-2xl);font-weight:600;margin:0">Create a group</h1>
        <p class="text-sm text-muted m-0 mt-2">Pick a name and a type. You can edit permission flags from the <strong>List groups</strong> tab once the group exists.</p>
    </div>
    <form class="card"
          method="post"
          action="?p=admin&c=groups"
          data-testid="add-group-form"
          onsubmit="return SbppGroupsAdd(event);">
        {csrf_field}
        <div class="card__body space-y-4">
            <div>
                <label class="label" for="add-group-name">Group name</label>
                <input class="input"
                       id="add-group-name"
                       name="name"
                       data-testid="add-group-name"
                       autocomplete="off"
                       placeholder="e.g. Senior Admins"
                       required>
                <p class="text-xs text-muted m-0 mt-2">Must be unique. No commas.</p>
            </div>

            <div>
                <label class="label" for="add-group-type">Group type</label>
                <select class="select"
                        id="add-group-type"
                        name="type"
                        data-testid="add-group-type"
                        onchange="SbppGroupsAddTypeChanged(this);">
                    <option value="0">Please select &hellip;</option>
                    <option value="1">Web admin group</option>
                    <option value="2">Server admin group</option>
                    <option value="3">Server group</option>
                </select>
                <p class="text-xs text-muted m-0 mt-2">Web admin = panel permissions. Server admin = SourceMod char-flags. Server group = grouping of game servers.</p>
            </div>

            <div data-testid="add-group-srvflags-block" id="add-group-srvflags-block" style="display:none">
                <label class="label" for="add-group-srvflags">SourceMod flags &amp; immunity</label>
                <input class="input"
                       id="add-group-srvflags"
                       name="srvflags"
                       data-testid="add-group-srvflags"
                       autocomplete="off"
                       placeholder="e.g. abz#50">
                <p class="text-xs text-muted m-0 mt-2">SourceMod flag string. Append <code>#&lt;immunity&gt;</code> for immunity (defaults to 0).</p>
            </div>
        </div>
        <div class="card__header" style="border-top:1px solid var(--border);border-bottom:0;justify-content:flex-end">
            <div class="flex gap-2">
                <a class="btn btn--ghost" href="?p=admin&c=groups" data-testid="add-group-cancel">Cancel</a>
                <button class="btn btn--primary" type="submit" data-testid="add-group-submit">Create group</button>
            </div>
        </div>
    </form>
</div>

<script>
{literal}
function SbppGroupsAddTypeChanged(sel) {
    var srvBlock = sb.$id('add-group-srvflags-block');
    if (!srvBlock) return;
    srvBlock.style.display = (sel.value === '2') ? 'block' : 'none';
}

function SbppGroupsAdd(event) {
    event.preventDefault();
    var form = event.target;
    var name = form.querySelector('input[name="name"]').value.trim();
    var type = form.querySelector('select[name="type"]').value;
    var srvflagsEl = form.querySelector('input[name="srvflags"]');
    var srvflags = srvflagsEl ? srvflagsEl.value : '';
    sb.api.call(Actions.GroupsAdd, {
        name: name,
        type: type,
        bitmask: 0,
        srvflags: srvflags
    }).then(function (r) {
        // Inlined sourcebans.js helper (#1123 D1 prep): applyApiResponse is removed at D1.
        // sb.api.call already follows r.redirect natively, so we only need to surface
        // the success/error toast. SBPP.showToast (theme.js) takes {kind,title,body};
        // sb.message is the legacy fallback (no-ops under sbpp2026 since #dialog-* is
        // legacy-default-only, but kept so a third-party theme that never wired SBPP
        // still gets some signal).
        if (!r) return;
        if (r.redirect) return;
        if (r.ok === false) {
            var em = (r.error && r.error.message) || 'Unknown error';
            if (window.SBPP && typeof window.SBPP.showToast === 'function') {
                window.SBPP.showToast({ kind: 'error', title: 'Error', body: em });
            } else {
                sb.message.error('Error', em);
            }
            return;
        }
        var data = r.data || {};
        var body = (data.message && data.message.body) || 'Group added.';
        var title = (data.message && data.message.title) || 'Group added';
        if (window.SBPP && typeof window.SBPP.showToast === 'function') {
            window.SBPP.showToast({ kind: 'success', title: title, body: body });
        } else {
            sb.message.success(title, body, data.message ? data.message.redir : '');
        }
        if (data.reload) {
            setTimeout(function () { window.location.reload(); }, 2000);
        }
    });
    return false;
}
{/literal}
</script>
{/if}
