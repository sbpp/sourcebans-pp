{*
    SourceBans++ install wizard — step 2 (database details).
    Pair view: \Sbpp\View\Install\InstallDatabaseView (web/includes/View/Install/InstallDatabaseView.php).
    Page handler: web/install/pages/page.2.php.
*}
{include file="install/_chrome.tpl"}

<p class="lead">
    Tell the wizard how to reach your MySQL or MariaDB database.
    Create the database first via your hosting control panel
    (phpMyAdmin, cPanel "MySQL Databases", &hellip;), then come back
    here with the credentials.
</p>

{if $error !== ''}
    <div class="install-alert install-alert--error"
         role="alert"
         data-testid="install-database-error"
         style="margin-bottom:1.25rem">
        {$error}
    </div>
{/if}

<form method="post"
      action="?step=2"
      data-testid="install-database-form"
      autocomplete="off"
      novalidate>
    <input type="hidden" name="postd" value="1">

    <div class="install-section">
        <h2>Database connection</h2>
        <div class="install-grid">
            <div>
                <label class="label" for="install-database-server">Hostname</label>
                <input class="input"
                       id="install-database-server"
                       name="server"
                       type="text"
                       value="{$val_server}"
                       placeholder="localhost"
                       data-testid="install-database-server"
                       required>
                <p class="text-xs text-muted">Usually <code>localhost</code> on shared hosting.</p>
            </div>

            <div>
                <label class="label" for="install-database-port">Port</label>
                <input class="input"
                       id="install-database-port"
                       name="port"
                       type="number"
                       min="1"
                       max="65535"
                       value="{$val_port}"
                       data-testid="install-database-port"
                       required>
                <p class="text-xs text-muted">Default for MySQL / MariaDB is <code>3306</code>.</p>
            </div>

            <div>
                <label class="label" for="install-database-username">Username</label>
                <input class="input"
                       id="install-database-username"
                       name="username"
                       type="text"
                       value="{$val_username}"
                       autocomplete="username"
                       data-testid="install-database-username"
                       required>
            </div>

            <div>
                <label class="label" for="install-database-password">Password</label>
                <input class="input"
                       id="install-database-password"
                       name="password"
                       type="password"
                       value="{$val_password}"
                       autocomplete="new-password"
                       data-testid="install-database-password">
                <p class="text-xs text-muted">Leave blank if your DB user has no password set.</p>
            </div>

            <div>
                <label class="label" for="install-database-database">Database name</label>
                <input class="input"
                       id="install-database-database"
                       name="database"
                       type="text"
                       value="{$val_database}"
                       data-testid="install-database-database"
                       required>
                <p class="text-xs text-muted">Must already exist &mdash; the wizard fills it.</p>
            </div>

            <div>
                <label class="label" for="install-database-prefix">Table prefix</label>
                <input class="input"
                       id="install-database-prefix"
                       name="prefix"
                       type="text"
                       value="{$val_prefix}"
                       maxlength="9"
                       pattern="[A-Za-z0-9_]+"
                       data-testid="install-database-prefix"
                       required>
                <p class="text-xs text-muted">Up to 9 letters / digits / underscores. Default: <code>sb</code>.</p>
            </div>
        </div>
    </div>

    <div class="install-section">
        <h2>Optional &mdash; Steam &amp; admin email</h2>
        <div class="install-grid">
            <div>
                <label class="label" for="install-database-apikey">Steam API key</label>
                <input class="input"
                       id="install-database-apikey"
                       name="apikey"
                       type="text"
                       value="{$val_apikey}"
                       data-testid="install-database-apikey">
                <p class="text-xs text-muted">
                    Powers profile lookups + the Steam OpenID login.
                    Get one from
                    <a href="https://steamcommunity.com/dev/apikey"
                       target="_blank" rel="noopener">steamcommunity.com</a>.
                    Can be added later in <em>Admin &rarr; Settings</em>.
                </p>
            </div>

            <div>
                <label class="label" for="install-database-email">SourceBans email</label>
                <input class="input"
                       id="install-database-email"
                       name="sb-email"
                       type="email"
                       value="{$val_email}"
                       data-testid="install-database-email">
                <p class="text-xs text-muted">
                    Sender address for password resets and ban
                    notifications. Can be configured later.
                </p>
            </div>
        </div>
    </div>

    <div class="install-actions">
        <a class="btn btn--ghost" href="?step=1" data-testid="install-database-back">
            Back
        </a>
        <button class="btn btn--primary"
                type="submit"
                data-testid="install-database-continue">
            Continue
        </button>
    </div>
</form>

{include file="install/_chrome_close.tpl"}
