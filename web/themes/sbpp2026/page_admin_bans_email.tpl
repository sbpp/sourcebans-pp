{*
    SourceBans++ 2026 — page_admin_bans_email.tpl
    Bound to Sbpp\View\AdminBansEmailView (validated by SmartyTemplateRule).

    "Email player" form rendered by web/pages/admin.email.php after a
    submission/protest's contact link is followed. The handler validates
    `?type=` (literal `s` or `p`) and casts `?id=` to int before building
    `$email_js` ("CheckEmail('s', 42)" style); the legacy default theme
    drops the same expression into onclick="…", so this template mirrors
    that behaviour.

    The legacy CheckEmail() helper from web/scripts/sourcebans.js posts
    the actual subject + body via Actions.SystemSendMail — that path
    remains in the default theme. sbpp2026 doesn't load sourcebans.js,
    so the inline literal-block script at the bottom of this file
    installs a window.CheckEmail shim (only when one isn't defined) that
    runs the same validation, dispatches Actions.SystemSendMail through
    sb.api.call, and surfaces success / failure via window.SBPP.showToast
    (theme.js) or sb.message as a fallback. Custom forks that still ship
    sourcebans.js alongside sbpp2026 keep the original CheckEmail.
*}
<section class="p-6" data-testid="banemail-section" style="max-width:48rem">
    <div class="mb-6">
        <h1 style="font-size:1.5rem;font-weight:600;margin:0">Email player</h1>
        <p class="text-sm text-muted m-0 mt-2">
            Sending to <span class="font-mono" data-testid="banemail-addr">{$email_addr|escape}</span>.
        </p>
    </div>
    <form class="card p-6 space-y-4"
          data-testid="banemail-form"
          onsubmit="return false;">
        {csrf_field}
        <div>
            <label class="label" for="subject">Subject</label>
            <input type="text"
                   class="input"
                   id="subject"
                   name="subject"
                   autocomplete="off"
                   data-testid="banemail-subject">
            <div class="text-xs mt-2" id="subject.msg" style="color:var(--danger);display:none"></div>
        </div>
        <div>
            <label class="label" for="message">Message</label>
            <textarea class="textarea"
                      id="message"
                      name="message"
                      rows="8"
                      placeholder="What would you like the player to know?"
                      data-testid="banemail-message"></textarea>
            <div class="text-xs mt-2" id="message.msg" style="color:var(--danger);display:none"></div>
        </div>
        <div class="flex justify-end gap-2"
             style="border-top:1px solid var(--border);padding-top:0.75rem">
            <button type="button"
                    class="btn btn--ghost"
                    onclick="history.go(-1)"
                    data-testid="banemail-back">Back</button>
            {* nofilter: $email_js is server-built ("CheckEmail('s', INT)" or "CheckEmail('p', INT)") in admin.email.php after $_GET['type'] is constrained to the literal 's'/'p' and $_GET['id'] is cast to int — no caller-controlled data flows through. CheckEmail itself comes from sourcebans.js on the default theme; on sbpp2026 the literal-block script below installs a same-name shim that calls Actions.SystemSendMail directly. *}
            <button type="button"
                    class="btn btn--primary"
                    id="aemail"
                    data-testid="banemail-submit"
                    onclick="{$email_js nofilter}">
                Send email
            </button>
        </div>
    </form>
</section>
{* sbpp2026 doesn't ship sourcebans.js, so window.CheckEmail (the helper
   the button's onclick targets) is undefined and the click is a no-op.
   We install a shim only when no CheckEmail is already loaded — custom
   forks bundling sourcebans.js with the new chrome keep the original. *}
{literal}
<script>
(function () {
    'use strict';
    if (typeof window.CheckEmail === 'function') return;
    function api() { return (window.sb && window.sb.api) || null; }
    function actions() { return window.Actions || null; }
    function $id(id) { return document.getElementById(id); }
    function setMsg(id, html) {
        var el = $id(id);
        if (!el) return;
        el.innerHTML = html || '';
        el.style.display = html ? 'block' : 'none';
    }
    function toast(kind, title, body) {
        var SBPP = window.SBPP;
        if (SBPP && typeof SBPP.showToast === 'function') {
            SBPP.showToast({
                kind: kind === 'red' ? 'error' : kind === 'green' ? 'success' : (kind || 'info'),
                title: title,
                body: body || ''
            });
            return;
        }
        if (window.sb && window.sb.message && window.sb.message[kind]) {
            window.sb.message[kind](title, body || '');
        }
    }
    /** @param {string} type @param {number} id */
    window.CheckEmail = function (type, id) {
        var err = 0;
        var subject = $id('subject'), message = $id('message');
        if (!subject || !subject.value) { setMsg('subject.msg', 'You must type a subject for the email.'); err++; }
        else { setMsg('subject.msg', ''); }
        if (!message || !message.value) { setMsg('message.msg', 'You must type a message for the email.'); err++; }
        else { setMsg('message.msg', ''); }
        if (err > 0) return;
        var a = api(), A = actions();
        if (!a || !A) return;
        a.call(A.SystemSendMail, {
            subject: subject.value,
            message: message.value,
            type: type,
            id: id
        }).then(function (r) {
            if (!r || r.ok === false) {
                toast('error', 'Email failed', (r && r.error && r.error.message) || 'Unknown error');
                return;
            }
            if (typeof window.applyApiResponse === 'function') {
                window.applyApiResponse(r);
                return;
            }
            var msg = (r.data && r.data.message) || null;
            toast('success', (msg && msg.title) || 'Email sent', (msg && msg.body) || 'The email has been sent.');
            if (msg && msg.redir) {
                setTimeout(function () { window.location.href = msg.redir; }, 1200);
            }
        });
    };
})();
</script>
{/literal}
