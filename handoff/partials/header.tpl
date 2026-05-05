{* ============================================================
   header.tpl — SourceBans++ 2026 layout shell
   Replaces web/themes/default/templates/header.tpl
   Renders: <html>, <head>, sidebar, topbar, opens .main + page wrapper.
   Pages should end by including footer.tpl (closes wrappers).
   ============================================================ *}
<!DOCTYPE html>
<html lang="en" class="{if $user.theme_dark}dark{/if}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$title|default:$site_name}</title>
<link rel="icon" href="{$theme_url}/images/favicon.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{$theme_url}/css/theme.css">
<script src="https://unpkg.com/lucide@0.460.0/dist/umd/lucide.min.js" defer></script>
<script src="{$theme_url}/js/theme.js" defer></script>
</head>
<body>
<div class="app">

  {* ---- Sidebar ---- *}
  <aside class="sidebar" id="sidebar" data-mobile-open="false">
    <div class="sidebar__brand">
      <div class="sidebar__brand-mark">S</div>
      <div>
        <div class="font-semibold text-sm">{$site_name|default:'SourceBans++'}</div>
        <div class="text-xs text-faint">{$site_host|default:''}</div>
      </div>
    </div>
    <nav class="sidebar__nav" aria-label="Primary">
      <div class="sidebar__section">
        <div class="sidebar__section-label">Public</div>
        <a class="sidebar__link" href="{$site_url}" {if $tab=='dashboard'}aria-current="page"{/if}>
          <i data-lucide="layout-dashboard"></i> Dashboard
        </a>
        <a class="sidebar__link" href="?p=banlist" {if $tab=='bans'}aria-current="page"{/if}>
          <i data-lucide="ban"></i> Ban list
          {if $bans_count}<span class="sidebar__link-count">{$bans_count|number_format}</span>{/if}
        </a>
        <a class="sidebar__link" href="?p=commslist" {if $tab=='comms'}aria-current="page"{/if}>
          <i data-lucide="mic-off"></i> Comm blocks
        </a>
        <a class="sidebar__link" href="?p=submit" {if $tab=='submit'}aria-current="page"{/if}>
          <i data-lucide="flag"></i> Submit a ban
        </a>
        <a class="sidebar__link" href="?p=appeal" {if $tab=='protest'}aria-current="page"{/if}>
          <i data-lucide="megaphone"></i> Appeals
        </a>
        <a class="sidebar__link" href="?p=servers" {if $tab=='servers'}aria-current="page"{/if}>
          <i data-lucide="server"></i> Servers
        </a>
      </div>

      {if $user}
      <div class="sidebar__section">
        <div class="sidebar__section-label">Admin</div>
        <a class="sidebar__link" href="?p=admin" {if $tab=='admin'}aria-current="page"{/if}>
          <i data-lucide="shield"></i> Admin panel
        </a>
        {if $user.srv_flags|strpos:'d' !== false}
        <a class="sidebar__link" href="?p=admin&c=bans&action=add">
          <i data-lucide="plus-circle"></i> Add ban
        </a>{/if}
        {if $user.srv_flags|strpos:'z' !== false}
        <a class="sidebar__link" href="?p=admin&c=admins"><i data-lucide="users"></i> Admins</a>
        <a class="sidebar__link" href="?p=admin&c=groups"><i data-lucide="shield-check"></i> Groups</a>
        <a class="sidebar__link" href="?p=admin&c=settings"><i data-lucide="settings"></i> Settings</a>
        <a class="sidebar__link" href="?p=admin&c=audit"><i data-lucide="scroll-text"></i> Audit log</a>
        {/if}
      </div>
      {/if}
    </nav>

    {if $user}
    <div style="border-top: 1px solid var(--border); padding: 0.5rem;">
      <a class="sidebar__link" href="?p=admin&c=myaccount">
        {include file="partials/avatar.tpl" name=$user.name size=28}
        <div style="flex:1;min-width:0">
          <div class="font-semibold text-xs truncate">{$user.name|escape}</div>
          <div class="text-faint" style="font-size:0.625rem">{$user.srv_group|escape}</div>
        </div>
      </a>
    </div>
    {/if}
  </aside>

  <div class="main">

    {* ---- Topbar ---- *}
    <header class="topbar">
      <button class="btn--ghost btn--icon" data-mobile-menu aria-label="Open menu" style="display:none">
        <i data-lucide="menu"></i>
      </button>

      <nav class="topbar__breadcrumbs" aria-label="Breadcrumb">
        {foreach $breadcrumbs|default:[$title] as $crumb name=bc}
          {if !$smarty.foreach.bc.first}<i data-lucide="chevron-right" style="width:12px;height:12px;color:var(--text-faint)"></i>{/if}
          <span {if $smarty.foreach.bc.last}aria-current="page"{/if}>{$crumb|escape}</span>
        {/foreach}
      </nav>

      <div style="flex:1"></div>

      <button class="topbar__search" data-palette-open type="button">
        <i data-lucide="search" style="width:14px;height:14px"></i>
        <span>Search players, SteamIDs…</span>
        <kbd>⌘K</kbd>
      </button>

      <button class="btn--ghost btn--icon" data-theme-toggle aria-label="Toggle theme">
        <i data-lucide="sun"></i>
      </button>
    </header>

    {* ---- Flash messages → toasts ---- *}
    {if $messages}
    <div class="toast-stack" id="toast-stack">
      {foreach $messages as $msg}
        {include file="partials/toast.tpl" kind=$msg.type title=$msg.title body=$msg.body}
      {/foreach}
    </div>
    {/if}

    <main class="page" id="page">
{* page content follows *}
