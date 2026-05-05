{*
    SourceBans++ 2026 — page_submitban.tpl

    Public "Submit a ban / Report a player" form. Pair:
    web/pages/page.submit.php → web/includes/View/SubmitBanView.php.

    Form-input name= keys (SteamID, BanIP, PlayerName, BanReason,
    SubmitName, EmailAddr, server, demo_file) and the `subban=1` marker
    are LOCKED by the page handler's $_POST reads and by the
    :prefix_submissions schema; only the visual layout differs from
    web/themes/default/page_submitban.tpl. Both templates render the
    same set of Smarty vars on SubmitBanView so the dual-theme
    SmartyTemplateRule (#1123 A2) is happy.

    CSRF: {csrf_field} is required because this is a state-changing
    POST (creates a row in :prefix_submissions). The token plugin
    already escapes its values; auto-escape is on globally.

    Testability hooks (per #1123 "Testability hooks") — every
    interactive surface gets data-testid="submitban-<field>" so the
    forthcoming Playwright suite has stable selectors:
      submitban-steam, submitban-ip, submitban-name, submitban-reason,
      submitban-reporter-name, submitban-reporter-email,
      submitban-server, submitban-demo, submitban-submit, submitban-cancel.

    Captcha / honeypot: none in the legacy flow, so nothing to
    preserve here. If captcha lands later, drop it above the action
    row and gate the submit button on it.
*}
<section class="p-6" style="max-width:48rem">
    <header class="mb-6">
        <h1 style="font-size:var(--fs-2xl);font-weight:600;margin:0">Submit a ban request</h1>
        <p class="text-sm text-muted m-0 mt-2">
            Report a player for cheating, harassment, or other rule violations. Fill in as much detail as
            you can &mdash; demos and screenshots help admins act faster.
        </p>
    </header>

    <form class="card"
          method="post"
          enctype="multipart/form-data"
          action="index.php?p=submit"
          aria-labelledby="submitban-heading"
          novalidate>
        {csrf_field}
        <input type="hidden" name="subban" value="1">

        <div class="card__body space-y-4">
            <div>
                <h2 id="submitban-heading" class="m-0" style="font-size:var(--fs-base);font-weight:600">
                    Offender details
                </h2>
                <p class="text-xs text-muted m-0 mt-2">
                    Provide either a Steam ID or an IP address (or both). Nickname and a clear
                    description of the incident are required.
                </p>
            </div>

            <div>
                <label class="label" for="submitban-steam">Player&rsquo;s Steam ID</label>
                <input type="text"
                       class="input font-mono"
                       id="submitban-steam"
                       name="SteamID"
                       maxlength="64"
                       value="{$STEAMID}"
                       placeholder="STEAM_0:1:23498765"
                       autocomplete="off"
                       data-testid="submitban-steam">
            </div>

            <div>
                <label class="label" for="submitban-ip">Player&rsquo;s IP address</label>
                <input type="text"
                       class="input font-mono"
                       id="submitban-ip"
                       name="BanIP"
                       maxlength="64"
                       value="{$ban_ip}"
                       placeholder="203.0.113.42"
                       autocomplete="off"
                       data-testid="submitban-ip">
            </div>

            <div>
                <label class="label" for="submitban-name">
                    Player&rsquo;s nickname <span style="color:var(--danger)" aria-hidden="true">*</span>
                </label>
                <input type="text"
                       class="input"
                       id="submitban-name"
                       name="PlayerName"
                       maxlength="70"
                       value="{$player_name}"
                       required
                       aria-required="true"
                       data-testid="submitban-name">
            </div>

            <div>
                <label class="label" for="submitban-reason">
                    Comments <span style="color:var(--danger)" aria-hidden="true">*</span>
                </label>
                <textarea class="textarea"
                          id="submitban-reason"
                          name="BanReason"
                          rows="5"
                          required
                          aria-required="true"
                          aria-describedby="submitban-reason-help"
                          data-testid="submitban-reason">{$ban_reason}</textarea>
                <p id="submitban-reason-help" class="text-xs text-muted m-0 mt-2">
                    Be specific. &ldquo;hacking&rdquo; is not enough &mdash; describe what you saw,
                    when, and on which server.
                </p>
            </div>
        </div>

        <div class="card__body space-y-4" style="border-top:1px solid var(--border)">
            <div>
                <h2 class="m-0" style="font-size:var(--fs-base);font-weight:600">
                    Your details
                </h2>
                <p class="text-xs text-muted m-0 mt-2">
                    So an admin can reach out for follow-up questions.
                </p>
            </div>

            <div class="grid gap-4" style="grid-template-columns:1fr 1fr">
                <div>
                    <label class="label" for="submitban-reporter-name">Your name</label>
                    <input type="text"
                           class="input"
                           id="submitban-reporter-name"
                           name="SubmitName"
                           maxlength="70"
                           value="{$subplayer_name}"
                           autocomplete="name"
                           data-testid="submitban-reporter-name">
                </div>
                <div>
                    <label class="label" for="submitban-reporter-email">
                        Your email <span style="color:var(--danger)" aria-hidden="true">*</span>
                    </label>
                    <input type="email"
                           class="input"
                           id="submitban-reporter-email"
                           name="EmailAddr"
                           maxlength="70"
                           value="{$player_email}"
                           required
                           aria-required="true"
                           autocomplete="email"
                           data-testid="submitban-reporter-email">
                </div>
            </div>

            <div>
                <label class="label" for="submitban-server">
                    Server <span style="color:var(--danger)" aria-hidden="true">*</span>
                </label>
                <select class="select"
                        id="submitban-server"
                        name="server"
                        required
                        aria-required="true"
                        data-testid="submitban-server">
                    <option value="-1">&mdash; Select server &mdash;</option>
                    {foreach from=$server_list item="server"}
                        <option value="{$server.sid}"{if $server_selected == $server.sid} selected{/if}>{$server.hostname}</option>
                    {/foreach}
                    <option value="0"{if $server_selected == 0} selected{/if}>Other server / Not listed here</option>
                </select>
            </div>

            <div>
                <label class="label" for="submitban-demo">Upload demo or evidence</label>
                <input type="file"
                       class="input"
                       id="submitban-demo"
                       name="demo_file"
                       accept=".dem,.zip,.rar,.7z,.bz2,.gz"
                       data-testid="submitban-demo">
                <p class="text-xs text-muted m-0 mt-2">
                    Optional. Allowed formats: <code class="font-mono">.dem</code>,
                    <code class="font-mono">.zip</code>, <code class="font-mono">.rar</code>,
                    <code class="font-mono">.7z</code>, <code class="font-mono">.bz2</code>,
                    <code class="font-mono">.gz</code>.
                </p>
            </div>
        </div>

        <div class="card__body flex items-center justify-between gap-2"
             style="border-top:1px solid var(--border)">
            <p class="text-xs text-muted m-0">
                <span style="color:var(--danger)" aria-hidden="true">*</span> Required field
            </p>
            <div class="flex gap-2">
                <a class="btn btn--ghost"
                   href="index.php?p=banlist"
                   data-testid="submitban-cancel">Cancel</a>
                <button class="btn btn--primary"
                        type="submit"
                        data-testid="submitban-submit">
                    <i data-lucide="send-horizontal" aria-hidden="true"></i>
                    Submit report
                </button>
            </div>
        </div>
    </form>
</section>
