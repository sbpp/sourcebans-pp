{*
    SourceBans++ 2026 — page / page_protestban.tpl

    Public ban-appeal form. Replaces the A1 stub. Pair view:
    Sbpp\View\ProtestBanView, page handler: web/pages/page.protest.php.

    Layout follows the handoff "submit + login two-column" shell
    pattern: a primary form card (the handoff submit.tpl shape) sits
    next to a slim info aside that mirrors login.tpl's right-rail —
    "what happens next" plus the threat-warning blurb the legacy
    page already shipped, so the public-facing copy is preserved
    without leaning on the legacy table chrome.

    Variable parity with the legacy default theme is intentional: the
    paired view declares exactly $steam_id, $ip, $player_name, $reason
    and $player_email so the dual-theme PHPStan matrix (#1123 A2) is
    happy on both legs until D1 collapses it. We DON'T introduce a
    $type property — instead we infer the initially-visible row from
    whether $ip arrived non-empty, which is the only way the legacy
    POST handler can leave $ip populated on a re-render after a
    failed submit.

    Form field name= attributes (Type, SteamID, IP, PlayerName,
    BanReason, EmailAddr, subprotest) are FROZEN — page.protest.php
    reads them straight off $_POST. Test hooks (data-testid="protest-…")
    are additive and stable per the issue's "Testability hooks" rule.
*}
{assign var="ip_first" value=($ip != '')}
<section class="p-6 space-y-6" style="max-width:64rem">
    <header>
        <h1 style="font-size:1.5rem;font-weight:600;margin:0">Appeal a ban</h1>
        <p class="text-sm text-muted m-0 mt-2">
            Think a ban on your account is a mistake? Submit an appeal
            below and the staff team will review it. Confirm the ban first
            on the <a href="index.php?p=banlist" style="color:var(--accent)">ban list</a>.
        </p>
    </header>

    <div class="grid gap-4" style="grid-template-columns:minmax(0,2fr) minmax(0,1fr)">
        <form class="card"
              method="post"
              action="index.php?p=protest"
              data-testid="protest-form"
              data-protest-form>
            <div class="card__header">
                <div>
                    <h3>Your details</h3>
                    <p>Fields marked <span style="color:var(--danger)">*</span> are required.</p>
                </div>
                <i data-lucide="megaphone" style="color:var(--text-faint)"></i>
            </div>
            <div class="card__body space-y-4">
                {csrf_field}
                <input type="hidden" name="subprotest" value="1">

                <div>
                    <label class="label" for="protest-type">Ban type</label>
                    <select id="protest-type"
                            name="Type"
                            class="select"
                            data-testid="protest-type"
                            data-protest-type>
                        <option value="0" {if !$ip_first}selected{/if}>Steam ID</option>
                        <option value="1" {if $ip_first}selected{/if}>IP Address</option>
                    </select>
                </div>

                <div data-protest-row="steam" {if $ip_first}hidden{/if}>
                    <label class="label" for="protest-steam">
                        Your SteamID <span style="color:var(--danger)">*</span>
                    </label>
                    <input id="protest-steam"
                           type="text"
                           name="SteamID"
                           maxlength="64"
                           value="{$steam_id|escape}"
                           class="input font-mono"
                           placeholder="STEAM_0:1:23498765"
                           autocomplete="off"
                           data-testid="protest-steam"
                           {if !$ip_first}required{/if}>
                </div>

                <div data-protest-row="ip" {if !$ip_first}hidden{/if}>
                    <label class="label" for="protest-ip">
                        Your IP <span style="color:var(--danger)">*</span>
                    </label>
                    <input id="protest-ip"
                           type="text"
                           name="IP"
                           maxlength="64"
                           value="{$ip|escape}"
                           class="input font-mono"
                           placeholder="192.0.2.1"
                           autocomplete="off"
                           data-testid="protest-ip"
                           {if $ip_first}required{/if}>
                </div>

                <div class="grid gap-4" style="grid-template-columns:1fr 1fr">
                    <div>
                        <label class="label" for="protest-name">
                            In-game name <span style="color:var(--danger)">*</span>
                        </label>
                        <input id="protest-name"
                               type="text"
                               name="PlayerName"
                               maxlength="70"
                               value="{$player_name|escape}"
                               class="input"
                               required
                               data-testid="protest-name">
                    </div>
                    <div>
                        <label class="label" for="protest-email">
                            Your email <span style="color:var(--danger)">*</span>
                        </label>
                        <input id="protest-email"
                               type="email"
                               name="EmailAddr"
                               maxlength="70"
                               value="{$player_email|escape}"
                               class="input"
                               required
                               data-testid="protest-email">
                    </div>
                </div>

                <div>
                    <label class="label" for="protest-reason">
                        Why should you be unbanned? <span style="color:var(--danger)">*</span>
                    </label>
                    <textarea id="protest-reason"
                              name="BanReason"
                              rows="6"
                              class="textarea"
                              required
                              data-testid="protest-reason"
                              placeholder="Be as descriptive as possible. The more context staff have, the faster your appeal can be reviewed.">{$reason|escape}</textarea>
                </div>
            </div>
            <div class="card__header" style="justify-content:flex-end;border-top:1px solid var(--border);border-bottom:0">
                <button type="submit"
                        class="btn btn--primary"
                        data-testid="protest-submit">
                    <i data-lucide="send-horizontal"></i> Submit appeal
                </button>
            </div>
        </form>

        <aside class="card">
            <div class="card__header">
                <div>
                    <h3>What happens next?</h3>
                    <p>Typical turnaround</p>
                </div>
                <i data-lucide="info" style="color:var(--text-faint)"></i>
            </div>
            <div class="card__body space-y-4 text-sm">
                <p class="m-0 text-muted">
                    The staff team is notified the moment you submit.
                    They review the original ban evidence and the context
                    you provide here.
                </p>
                <p class="m-0 text-muted">
                    You'll receive a reply by email — usually within
                    <span class="font-medium" style="color:var(--text)">24 hours</span>.
                </p>
                <div class="space-y-3"
                     style="border-top:1px solid var(--border);padding-top:1rem">
                    <div class="flex gap-2 items-start">
                        <i data-lucide="alert-triangle" style="color:var(--warning);flex-shrink:0;width:16px;height:16px;margin-top:2px"></i>
                        <p class="m-0 text-xs text-muted">
                            Threats, harassment, or abuse aimed at the
                            staff will not get you unbanned and will get
                            you permanently banned from every service.
                        </p>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</section>

<script>
(function () {
    var form = document.querySelector('[data-protest-form]');
    if (!form) { return; }
    var typeSelect = form.querySelector('[data-protest-type]');
    var steamRow = form.querySelector('[data-protest-row="steam"]');
    var ipRow = form.querySelector('[data-protest-row="ip"]');
    var steamInput = steamRow ? steamRow.querySelector('input') : null;
    var ipInput = ipRow ? ipRow.querySelector('input') : null;
    if (!typeSelect || !steamRow || !ipRow || !steamInput || !ipInput) { return; }
    function sync() {
        var ipPicked = typeSelect.value === '1';
        steamRow.hidden = ipPicked;
        ipRow.hidden = !ipPicked;
        steamInput.required = !ipPicked;
        ipInput.required = ipPicked;
    }
    typeSelect.addEventListener('change', sync);
    sync();
})();
</script>
