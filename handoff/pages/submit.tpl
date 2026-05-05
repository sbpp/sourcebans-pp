{* submit.tpl — public ban submission form *}
<div class="p-6" style="max-width:48rem">
  <div class="mb-6">
    <h1 style="font-size:1.5rem;font-weight:600;margin:0">Submit a ban request</h1>
    <p class="text-sm text-muted m-0 mt-2">Report a player for cheating, harassment, or other violations.</p>
  </div>
  <form class="card p-6 space-y-4" method="post" enctype="multipart/form-data" action="?p=submit">
    <input type="hidden" name="token" value="{$form_token}">
    <div class="grid gap-4" style="grid-template-columns:1fr 1fr">
      <div><label class="label">Your Steam name</label><input class="input" name="reporter_name" value="{$form.reporter_name|escape}"></div>
      <div><label class="label">Your email</label><input class="input" type="email" name="reporter_email" value="{$form.reporter_email|escape}"></div>
    </div>
    <div>
      <label class="label">Offender's SteamID or profile URL <span style="color:var(--danger)">*</span></label>
      <input class="input" name="steam" required value="{$form.steam|escape}" placeholder="STEAM_0:1:23498765">
    </div>
    <div class="grid gap-4" style="grid-template-columns:1fr 1fr">
      <div><label class="label">Server *</label>
        <select class="select" name="server" required>
          <option value="">Select…</option>
          {foreach $servers as $s}<option value="{$s.sid}">{$s.name|escape}</option>{/foreach}
        </select>
      </div>
      <div><label class="label">Reason *</label>
        <select class="select" name="reason" required>
          <option value="">Select…</option>
          {foreach $reasons as $r}<option value="{$r}">{$r|escape}</option>{/foreach}
        </select>
      </div>
    </div>
    <div><label class="label">What happened? *</label><textarea class="textarea" name="details" rows="5" required>{$form.details|escape}</textarea></div>
    <div><label class="label">Evidence (demo / screenshots / video URL)</label><input class="input" name="evidence_url" placeholder="https://…"></div>
    {$captcha}
    <div class="flex justify-end gap-2" style="border-top:1px solid var(--border);padding-top:0.75rem">
      <a class="btn btn--ghost" href="?p=banlist">Cancel</a>
      <button class="btn btn--primary" type="submit"><i data-lucide="send-horizontal"></i> Submit report</button>
    </div>
  </form>
</div>
