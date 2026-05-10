{*
    SourceBans++ 2026 — updater.tpl

    Standalone wizard rendered by web/updater/index.php after Updater.php
    has finished applying any pending migrations. View:
    Sbpp\View\UpdaterView (just `$updates`).

    The updater runs in its own bootstrap context — it does NOT go
    through index.php's page-builder, so the chrome from
    `core/header.tpl` + `core/footer.tpl` is intentionally not pulled
    in. This template is therefore a complete <!DOCTYPE html> document
    and links the theme stylesheet directly. Asset paths are relative
    to /web/updater/ (where the script runs from), so `../themes/...`
    points at /web/themes/default/.

    Test hooks: each card header carries a stable
    `data-testid="updater-<step>"` attribute.
*}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Updater | SourceBans++</title>
    <link rel="stylesheet" href="../themes/default/css/theme.css">
</head>
<body style="background:var(--bg-page);color:var(--text);min-height:100vh">

<div class="p-6 space-y-6" style="max-width:48rem;margin:2.5rem auto;width:100%">

    <header class="flex items-center gap-3" data-testid="updater-header">
        {* #1235 — the updater renders in its own bootstrap context
           (web/updater/index.php) which doesn't load core/header.php,
           so the operator-configurable `template.logo` setting and
           the `$theme_url` Smarty global aren't in scope here. The
           favicon path is therefore hardcoded — the updater is an
           internal one-off page that runs once per upgrade, never
           through the regular page-builder lifecycle, so threading
           the setting through `UpdaterView` would be plumbing for a
           single admin-facing screen that doesn't need theming.
           Path is relative to /web/updater/ where the script runs. *}
        <img class="sidebar__brand-mark" src="../themes/default/images/favicon.svg" alt="">
        <div>
            <h1 style="font-size:var(--fs-2xl);font-weight:600;margin:0">SourceBans++ updater</h1>
            <p class="text-sm text-muted m-0 mt-2">Database migration log.</p>
        </div>
    </header>

    <section class="card">
        <div class="card__header" data-testid="updater-progress">
            <div>
                <h3>Progress</h3>
                <p>Each line below is a step from the migration runner.</p>
            </div>
        </div>
        <div class="card__body">
            {if $updates}
                <ol class="font-mono text-sm space-y-3" style="margin:0;padding-left:1.5rem">
                    {* nofilter: every $update line is built inside Updater.php from int versions
                       and static templates (see Updater::update()); no user input flows in. *}
                    {foreach from=$updates item=update}
                        <li>{$update nofilter}</li>
                    {/foreach}
                </ol>
            {else}
                <p class="text-sm text-muted m-0" data-testid="updater-empty">No updates were applied.</p>
            {/if}
        </div>
    </section>

    <section class="card">
        <div class="card__header" data-testid="updater-cleanup">
            <div>
                <h3>Next step</h3>
                <p>Once the run is complete, remove the <code class="font-mono">/updater</code> directory before serving the panel to admins.</p>
            </div>
        </div>
        <div class="card__body">
            <a class="btn btn--primary" href="../index.php" data-testid="updater-return">
                Return to panel
            </a>
        </div>
    </section>

    <footer class="text-xs text-faint" style="text-align:center">
        <a href="https://sbpp.github.io/" target="_blank" rel="noopener" style="color:inherit">SourceBans++</a>
    </footer>

</div>

</body>
</html>
