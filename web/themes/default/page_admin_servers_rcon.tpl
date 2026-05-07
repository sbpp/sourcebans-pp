-{*
    SourceBans++ 2026 — page_admin_servers_rcon.tpl
    Bound to Sbpp\View\AdminServersRconView (validated by SmartyTemplateRule).

    Rendered by web/pages/admin.rcon.php with the custom alternate
    delimiter pair (the legacy template body shipped with literal
    jQuery/JS braces that would otherwise collide with the default
    Smarty markers). The paired view overrides View::DELIMITERS so the
    analyser scans this template with the alternate pair too — see the
    AdminServersRconView class docblock for the literal pair.

    The console talks to Actions.ServersSendRcon (registered in
    web/api/handlers/_register.php with the SM_RCON|SM_ROOT mask). The
    dispatcher enforces the permission flag and the per-server scoping
    re-check; this page additionally hides the UI entirely when
    permission_rcon is false so a stray link can't surface a console an
    admin can't actually use.

    Inline <script> is intentional — sbpp2026's chrome only loads sb.js
    + api.js + theme.js, not the legacy sourcebans.js, so we wire the
    handful of UX behaviours in line rather than depending on a
    helper that won't exist at runtime. CSRF is auto-attached by
    sb.api.call via the meta[name=csrf-token] header.
*}-
<div class="page-section">
-{if NOT $permission_rcon}-
    <section class="card" data-testid="rcon-denied">
        <div class="card__body">
            <h3 style="margin:0 0 0.25rem">Access Denied</h3>
            <p class="text-sm text-muted m-0">You don't have RCON access to this server.</p>
        </div>
    </section>
-{else}-
<section class="card" data-testid="rcon-console" data-sid="-{$id}-">
    <div class="card__header">
        <div>
            <h3>RCON Console</h3>
            <p>Server #-{$id}- · type <code class="font-mono">clr</code> to clear · output is rendered as plain text.</p>
        </div>
    </div>
    <div class="card__body">
        -{*
            Output area is the deterministic rendering target for
            Actions.ServersSendRcon. Keep id="rcon_con" as the container
            (matches the structural contract the legacy theme uses) and
            id="rcon" as the scroll wrapper so the inline script can
            re-scroll on append. data-testid is the new attribute the
            redesign tests target.
        *}-
        <pre id="rcon"
             data-testid="rcon-output"
             style="margin:0;background:var(--bg-page);border:1px solid var(--border);border-radius:var(--radius-md);padding:0.75rem;height:18rem;overflow:auto;font-family:var(--font-mono);font-size:var(--fs-xs);color:var(--text);white-space:pre-wrap;word-break:break-word"><div id="rcon_con">SourceBans++ RCON console
==========================================================
Type your command in the box below and hit Enter.
Type 'clr' to clear the console.
==========================================================
</div></pre>
        <form id="rcon-form" class="flex gap-2 mt-4" autocomplete="off">
            -{csrf_field}-
            <input type="text"
                   id="cmd"
                   name="cmd"
                   class="input font-mono"
                   data-testid="rcon-input"
                   placeholder="status"
                   autocomplete="off"
                   spellcheck="false"
                   style="flex:1">
            <button type="submit"
                    id="rcon_btn"
                    class="btn btn--primary"
                    data-testid="rcon-send">
                Send
            </button>
        </form>
    </div>
</section>
<script>
(function () {
    'use strict';
    var sid = -{$id}-;
    var form = document.getElementById('rcon-form');
    var input = document.getElementById('cmd');
    var btn = document.getElementById('rcon_btn');
    var out = document.getElementById('rcon_con');
    var box = document.getElementById('rcon');
    if (!form || !input || !btn || !out || !box) return;

    function scrollToBottom() { box.scrollTop = box.scrollHeight; }

    function appendLine(prefix, text, kind) {
        var div = document.createElement('div');
        if (kind) div.dataset.kind = kind;
        div.textContent = prefix + (text || '');
        out.appendChild(div);
    }

    function appendOutput(raw) {
        // textContent splits + appendChild guarantees the gameserver-controlled
        // bytes never touch innerHTML — same defensive shape the legacy
        // LoadSendRcon helper uses in web/scripts/sourcebans.js.
        String(raw || '').split('\n').forEach(function (line, i, arr) {
            var span = document.createElement('span');
            span.textContent = line;
            out.appendChild(span);
            if (i < arr.length - 1) out.appendChild(document.createElement('br'));
        });
        out.appendChild(document.createElement('br'));
    }

    function setBusy(busy) {
        input.disabled = busy;
        btn.disabled = busy;
        if (!busy) input.focus();
    }

    function send() {
        var command = input.value;
        if (!command) return;
        setBusy(true);
        var api = window.sb && window.sb.api;
        if (!api || !window.Actions) {
            setBusy(false);
            return;
        }
        api.call(window.Actions.ServersSendRcon, { sid: sid, command: command, output: true }).then(function (r) {
            input.value = '';
            setBusy(false);
            if (!r || !r.ok || !r.data) {
                if (r && r.error && window.SBPP && window.SBPP.showToast) {
                    window.SBPP.showToast({ kind: 'error', title: 'RCON failed', body: r.error.message || 'Unknown error' });
                }
                return;
            }
            var d = r.data;
            if (d.kind === 'clear') { out.innerHTML = ''; return; }
            if (d.kind === 'noop') { return; }
            if (d.kind === 'error') {
                appendLine('> Error: ', d.error || '', 'error');
                out.appendChild(document.createElement('br'));
                scrollToBottom();
                return;
            }
            if (d.kind === 'append') {
                appendLine('-> ', d.command || '', 'cmd');
                appendOutput(d.output);
                scrollToBottom();
            }
        });
    }

    form.addEventListener('submit', function (e) { e.preventDefault(); send(); });
    input.focus();
    scrollToBottom();
})();
</script>
-{/if}-
</div>
