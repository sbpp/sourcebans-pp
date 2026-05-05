{* admin/settings.tpl — site config, sub-nav per section *}
<div class="p-6" style="max-width:1400px">
  <div class="mb-6">
    <h1 style="font-size:1.5rem;font-weight:600;margin:0">Settings</h1>
    <p class="text-sm text-muted m-0 mt-2">Site-wide configuration. Changes apply immediately.</p>
  </div>
  <div class="grid gap-6" style="grid-template-columns:200px 1fr">
    <nav>
      {foreach [['general','settings','General'],['appearance','palette','Appearance'],['mail','mail','Mail (SMTP)'],['integrations','plug','Integrations'],['security','lock','Security'],['maintenance','wrench','Maintenance']] as $sec}
      <a class="sidebar__link" href="?p=admin&c=settings&section={$sec[0]}" {if $section==$sec[0]}aria-current="page"{/if}>
        <i data-lucide="{$sec[1]}"></i> {$sec[2]}
      </a>
      {/foreach}
    </nav>
    <form method="post" action="?p=admin&c=settings&section={$section}" class="space-y-4" style="max-width:42rem">
      <input type="hidden" name="token" value="{$form_token}">

      {if $section=='general' || !$section}
      <div class="card">
        <div class="card__header"><div><h3>General</h3><p>Public-facing site identity</p></div></div>
        <div class="card__body space-y-4">
          <div><label class="label">Site name</label><input class="input" name="site_name" value="{$cfg.site_name|escape}"></div>
          <div><label class="label">Site URL</label><input class="input" name="site_url" value="{$cfg.site_url|escape}"></div>
          <div><label class="label">Default ban length (minutes; 0=permanent)</label><input class="input" type="number" name="default_length" value="{$cfg.default_length}"></div>
        </div>
      </div>
      {/if}

      {if $section=='appearance'}
      <div class="card">
        <div class="card__header"><div><h3>Appearance</h3><p>Theme and brand color</p></div></div>
        <div class="card__body space-y-4">
          <div><label class="label">Default theme</label>
            <div class="grid gap-2" style="grid-template-columns:repeat(3,1fr)">
              {foreach ['light','dark','system'] as $t}
              <label class="flex items-center gap-2 p-3" style="border:1px solid var(--border);border-radius:var(--radius-md)"><input type="radio" name="default_theme" value="{$t}" {if $cfg.default_theme==$t}checked{/if}> <span class="text-sm">{$t|capitalize}</span></label>
              {/foreach}
            </div>
          </div>
          <div><label class="label">Accent color</label>
            <div class="flex gap-2" style="flex-wrap:wrap">
              {foreach ['#ea580c','#d97706','#dc2626','#2563eb','#0d9488','#7c3aed','#db2777','#52525b'] as $c}
              <label><input type="radio" name="accent" value="{$c}" {if $cfg.accent==$c}checked{/if} style="display:none"><span style="display:block;width:36px;height:36px;border-radius:var(--radius-lg);background:{$c};border:2px solid {if $cfg.accent==$c}var(--text){else}transparent{/if};cursor:pointer"></span></label>
              {/foreach}
            </div>
          </div>
        </div>
      </div>
      {/if}

      {if $section=='mail'}
      <div class="card">
        <div class="card__header"><div><h3>Mail (SMTP)</h3><p>Outgoing email</p></div></div>
        <div class="card__body space-y-4">
          <div class="grid gap-4" style="grid-template-columns:2fr 1fr">
            <div><label class="label">SMTP host</label><input class="input" name="smtp_host" value="{$cfg.smtp_host|escape}"></div>
            <div><label class="label">Port</label><input class="input" name="smtp_port" value="{$cfg.smtp_port}"></div>
          </div>
          <div class="grid gap-4" style="grid-template-columns:1fr 1fr">
            <div><label class="label">Username</label><input class="input" name="smtp_user" value="{$cfg.smtp_user|escape}"></div>
            <div><label class="label">Password</label><input class="input" type="password" name="smtp_pass"></div>
          </div>
          <div><label class="label">From address</label><input class="input" name="smtp_from" value="{$cfg.smtp_from|escape}"></div>
        </div>
      </div>
      {/if}

      {if $section=='security'}
      <div class="card"><div class="card__header"><div><h3>Security</h3></div></div><div class="card__body space-y-3">
        {foreach [['require_2fa','Require 2FA for admins'],['steam_login','Allow Steam OpenID'],['lockout','Lock account after 5 failed attempts'],['audit','Audit all admin actions']] as $s}
        <label class="flex items-center justify-between p-3" style="border:1px solid var(--border);border-radius:var(--radius-md)">
          <span class="text-sm font-medium">{$s[1]}</span>
          <input type="checkbox" name="{$s[0]}" value="1" {if $cfg[$s[0]]}checked{/if}>
        </label>
        {/foreach}
      </div></div>
      {/if}

      <div class="flex justify-end gap-2"><button class="btn btn--primary" type="submit"><i data-lucide="save"></i> Save</button></div>
    </form>
  </div>
</div>
