{* login.tpl — replaces existing login page *}
<div style="min-height:100vh;display:grid;grid-template-columns:1fr 1fr;background:var(--bg-page)">
  <div style="display:flex;align-items:center;justify-content:center;padding:3rem">
    <div style="width:100%;max-width:22rem">
      <div class="flex items-center gap-3 mb-6">
        <div style="width:2.25rem;height:2.25rem;border-radius:var(--radius-lg);background:var(--brand-600);display:grid;place-items:center;color:white;font-weight:700">S</div>
        <div>
          <div class="font-semibold text-sm">{$site_name|default:'SourceBans++'}</div>
          <div class="text-xs text-muted">Admin panel</div>
        </div>
      </div>
      <h1 style="font-size:1.5rem;font-weight:600;margin:0">Sign in</h1>
      <p class="text-sm text-muted mt-2">Use your admin credentials, or sign in with Steam.</p>

      {if $error}<div class="toast" style="margin-top:1rem"><i data-lucide="circle-x" style="color:var(--danger)"></i><div class="text-sm">{$error|escape}</div></div>{/if}

      <a class="btn btn--secondary" href="?p=login&c=steam" style="width:100%;margin-top:2rem">
        <i data-lucide="gamepad-2"></i> Continue with Steam
      </a>

      <div style="position:relative;margin:1.25rem 0">
        <div style="position:absolute;inset:0;display:flex;align-items:center"><div style="width:100%;border-top:1px solid var(--border)"></div></div>
        <div style="position:relative;display:flex;justify-content:center"><span style="background:var(--bg-page);padding:0 0.5rem;font-size:0.625rem;letter-spacing:0.06em;text-transform:uppercase;color:var(--text-faint)">Or with credentials</span></div>
      </div>

      <form method="post" action="?p=login" class="space-y-3">
        <input type="hidden" name="token" value="{$form_token}">
        <div><label class="label">Username</label><input class="input" name="username" required></div>
        <div><label class="label">Password</label><input class="input" type="password" name="password" required></div>
        <label class="flex items-center gap-2 text-xs text-muted"><input type="checkbox" name="remember" value="1" checked> Remember me on this device</label>
        <button class="btn btn--primary" type="submit" style="width:100%;margin-top:0.5rem">Sign in <i data-lucide="arrow-right"></i></button>
      </form>
    </div>
  </div>
  <div class="login-aside" style="background:var(--zinc-900);color:white;padding:3rem;display:flex;align-items:flex-end;position:relative;overflow:hidden">
    <div style="position:absolute;inset:0;opacity:0.3;background:radial-gradient(circle at 20% 30%, #ea580c33 0, transparent 40%), radial-gradient(circle at 80% 70%, #5885a233 0, transparent 40%)"></div>
    <div style="position:relative;max-width:24rem">
      <div style="font-size:0.625rem;text-transform:uppercase;letter-spacing:0.2em;color:var(--brand-400);font-weight:600;margin-bottom:0.75rem">Operator console</div>
      <h2 style="font-size:1.875rem;font-weight:600;margin:0;line-height:1.1">Cleaner servers,<br>fewer headaches.</h2>
      <p style="font-size:0.875rem;color:var(--zinc-400);margin-top:0.75rem">Ban management for Source-engine communities. Built by admins, for admins.</p>
    </div>
  </div>
</div>
<style>@media(max-width:1024px){.login-aside{display:none}body>div{grid-template-columns:1fr!important}}</style>
