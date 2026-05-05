{*
    SourceBans++ 2026 — page_admin_servers_add.tpl
    Bound to Sbpp\View\AdminServersAddView (validated by SmartyTemplateRule).

    Add-server form rendered immediately below the server grid by
    web/pages/admin.servers.php. Posts to Actions.ServersAdd via the
    inline script (sbpp2026's chrome doesn't load the legacy
    process_add_server() helper from sourcebans.js, so we wire the
    submit in line). CSRF auto-attaches via sb.api.call's
    X-CSRF-Token header; {csrf_field} is also rendered so a no-JS
    fallback (e.g. an admin posting through devtools) carries the
    same defence.

    The View also declares $modid / $edit_server because the same view
    powers the edit flow at admin.edit.server.php — the form prefills
    those when the legacy template is reused. This template uses both
    even in the add flow (modid = preselected option, edit_server gates
    the submit-button label) to keep the SmartyTemplateRule contract
    happy across the dual-theme matrix.
*}
<section class="page-section mt-6" id="addserver" data-testid="server-add-section" style="max-width:900px">
    {if NOT $permission_addserver}
        <div class="card" data-testid="server-add-denied">
            <div class="card__body">
                <h3 style="margin:0 0 0.25rem">Access Denied</h3>
                <p class="text-sm text-muted m-0">You don't have permission to add servers.</p>
            </div>
        </div>
    {else}
        <form id="addserver-form"
              class="card"
              method="post"
              action="index.php?p=admin&c=servers"
              data-testid="server-add-form"
              autocomplete="off">
            {csrf_field}
            <input type="hidden" name="insert_type" value="add">
            <div class="card__header">
                <div>
                    <h3>{if $edit_server}Edit server{else}Add a server{/if}</h3>
                    <p>Tip: hover the question marks for inline help. RCON is optional but unlocks the live console + per-server admin mapping.</p>
                </div>
            </div>
            <div class="card__body" style="display:grid;gap:1rem">
                <div class="grid gap-4" style="grid-template-columns:repeat(auto-fit,minmax(16rem,1fr))">
                    <label class="block">
                        <span class="label">Server IP / domain</span>
                        <input type="text"
                               id="address"
                               name="address"
                               class="input font-mono"
                               value="{$ip|escape}"
                               placeholder="203.0.113.10"
                               required
                               data-testid="addserver-address">
                        <span class="text-xs text-muted">IPv4 / IPv6 / hostname. The dispatcher validates with PHP's <code class="font-mono">FILTER_VALIDATE_IP</code>.</span>
                    </label>
                    <label class="block">
                        <span class="label">Server port</span>
                        <input type="number"
                               id="port"
                               name="port"
                               class="input font-mono"
                               value="{if $port}{$port|escape}{else}27015{/if}"
                               min="1"
                               max="65535"
                               required
                               data-testid="addserver-port">
                        <span class="text-xs text-muted">Default <code class="font-mono">27015</code>.</span>
                    </label>
                </div>

                <div class="grid gap-4" style="grid-template-columns:repeat(auto-fit,minmax(16rem,1fr))">
                    <label class="block">
                        <span class="label">RCON password</span>
                        <input type="password"
                               id="rcon"
                               name="rcon"
                               class="input font-mono"
                               value="{$rcon|escape}"
                               autocomplete="new-password"
                               data-testid="addserver-rcon">
                        <span class="text-xs text-muted">Found in <code class="font-mono">server.cfg</code> next to <code class="font-mono">rcon_password</code>.</span>
                    </label>
                    <label class="block">
                        <span class="label">Confirm RCON</span>
                        <input type="password"
                               id="rcon2"
                               name="rcon2"
                               class="input font-mono"
                               value="{$rcon|escape}"
                               autocomplete="new-password"
                               data-testid="addserver-rcon2">
                        <span class="text-xs text-muted">Re-enter to avoid typos.</span>
                    </label>
                </div>

                <div class="grid gap-4" style="grid-template-columns:repeat(auto-fit,minmax(16rem,1fr))">
                    <label class="block">
                        <span class="label">Mod</span>
                        <select id="mod"
                                name="mod"
                                class="select"
                                required
                                data-testid="addserver-mod">
                            {if !$edit_server}<option value="-2">Select a mod…</option>{/if}
                            {foreach from=$modlist item=mod}
                                <option value="{$mod.mid}"{if $modid !== '' && $modid == $mod.mid} selected{/if}>{$mod.name|escape}</option>
                            {/foreach}
                        </select>
                    </label>
                    <label class="flex items-center gap-2" style="align-self:end;padding:0.5rem 0">
                        <input type="checkbox"
                               id="enabled"
                               name="enabled"
                               value="1"
                               checked
                               data-testid="addserver-enabled">
                        <span class="text-sm">Enable on the public servers list</span>
                    </label>
                </div>

                <fieldset style="border:1px solid var(--border);border-radius:var(--radius-md);padding:0.75rem 1rem">
                    <legend class="text-xs font-semibold" style="padding:0 0.5rem">Server groups</legend>
                    <p class="text-xs text-muted m-0 mb-2">Pick the SourceMod groups this server inherits admins from.</p>
                    {if $grouplist|@count == 0}
                        <p class="text-xs text-faint m-0">No server groups defined yet — visit Admins → Groups to create one.</p>
                    {else}
                        <div class="grid gap-2" style="grid-template-columns:repeat(auto-fill,minmax(11rem,1fr))">
                            {foreach from=$grouplist item=group}
                                <label class="flex items-center gap-2 p-2"
                                       style="border:1px solid var(--border);border-radius:var(--radius-md)">
                                    <input type="checkbox"
                                           value="{$group.gid}"
                                           id="g_{$group.gid}"
                                           name="groups[]"
                                           data-testid="addserver-group">
                                    <span class="text-xs truncate">{$group.name|escape}</span>
                                </label>
                            {/foreach}
                        </div>
                    {/if}
                </fieldset>
            </div>
            <div class="flex justify-end gap-2"
                 style="border-top:1px solid var(--border);padding:0.75rem 1.25rem">
                <button type="button"
                        class="btn btn--ghost"
                        onclick="history.go(-1)"
                        data-testid="addserver-back">
                    Back
                </button>
                <button type="submit"
                        class="btn btn--primary"
                        id="aserver"
                        data-testid="addserver-submit">
                    {$submit_text|escape}
                </button>
            </div>
            <p id="addserver-error"
               class="text-xs"
               role="alert"
               aria-live="polite"
               data-testid="addserver-error"
               style="margin:0 1.25rem 1rem;color:var(--danger);min-height:1rem"></p>
        </form>
    {/if}
</section>
{* Smarty default delimiters are { and }; the JSDoc + object literals
   below would otherwise be parsed as template tags. {literal}…{/literal}
   keeps the entire script body verbatim. *}
{literal}
<script>
(function () {
    'use strict';
    var form = document.getElementById('addserver-form');
    if (!form) return;
    var submit = document.getElementById('aserver');
    var errEl = document.getElementById('addserver-error');

    function setError(msg) { if (errEl) errEl.textContent = msg || ''; }
    function field(id) {
        var el = document.getElementById(id);
        return el ? el.value : '';
    }
    function checked(id) {
        var el = document.getElementById(id);
        return !!(el && el.checked);
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        setError('');
        if (submit) submit.disabled = true;

        var groups = '';
        var inputs = form.querySelectorAll('input[name="groups[]"]');
        for (var i = 0; i < inputs.length; i++) {
            var cb = inputs[i];
            if (cb.checked) groups += ',' + cb.value;
        }

        var api = window.sb && window.sb.api;
        if (!api || !window.Actions) {
            setError('JS API not loaded; refresh the page.');
            if (submit) submit.disabled = false;
            return;
        }

        api.call(window.Actions.ServersAdd, {
            ip:       field('address'),
            port:     field('port'),
            rcon:     field('rcon'),
            rcon2:    field('rcon2'),
            mod:      Number(field('mod')),
            enabled:  checked('enabled'),
            group:    groups
        }).then(function (r) {
            if (submit) submit.disabled = false;
            if (!r || r.ok === false) {
                var msg = (r && r.error && r.error.message) || 'Could not add server.';
                setError(msg);
                if (window.SBPP && window.SBPP.showToast) {
                    window.SBPP.showToast({ kind: 'error', title: 'Add server failed', body: msg });
                }
                return;
            }
            // The handler returns message.redir; navigate there ourselves
            // since sbpp2026 does not ship the legacy applyApiResponse.
            var d = (r && r.data) || {};
            var redir = d.message && d.message.redir;
            if (window.SBPP && window.SBPP.showToast && d.message) {
                window.SBPP.showToast({ kind: 'success', title: d.message.title || 'Server added', body: d.message.body || '' });
            }
            if (typeof redir === 'string' && redir.length > 0) {
                window.location.href = redir;
            }
        });
    });
})();
</script>
{/literal}
