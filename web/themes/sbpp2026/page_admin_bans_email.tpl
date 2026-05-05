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
    the actual subject + body via xajax — that path remains in the
    default theme. sbpp2026 doesn't load sourcebans.js, so an inline
    {literal} script attempts the modern Actions.BansContact JSON path
    when available; otherwise it falls back to the legacy onclick string
    for full backwards-compat with custom forks that still ship
    sourcebans.js alongside the new chrome.
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
            {* nofilter: $email_js is server-built ("CheckEmail('s', INT)" or "CheckEmail('p', INT)") in admin.email.php after $_GET['type'] is constrained to the literal 's'/'p' and $_GET['id'] is cast to int — no caller-controlled data flows through. *}
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
