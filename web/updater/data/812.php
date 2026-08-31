<?php

// Personal Access Tokens for the external REST API (`/api/v1`).
// SHA-256 hashes only. The plaintext secret is shown once at create time.
//
// `$this` is supplied by Updater::update(), which loads this file inside
// the Updater instance scope; PHPStan can't see that, so `$this->dbs`
// reads below are suppressed inline.

// @phpstan-ignore variable.undefined
$this->dbs->query(
    'CREATE TABLE IF NOT EXISTS `:prefix_api_tokens` ('
    . '`id` int(10) UNSIGNED NOT NULL auto_increment,'
    . '`aid` int(6) NOT NULL,'
    . '`name` varchar(64) NOT NULL,'
    . '`token_hash` char(64) NOT NULL,'
    . '`token_prefix` varchar(16) NOT NULL,'
    . '`created` int(11) NOT NULL,'
    . '`last_used` int(11) NULL default NULL,'
    . '`expires_at` int(11) NULL default NULL,'
    . '`revoked_at` int(11) NULL default NULL,'
    . 'PRIMARY KEY (`id`),'
    . 'UNIQUE KEY `token_hash` (`token_hash`),'
    . 'KEY `aid` (`aid`)'
    . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);
// @phpstan-ignore variable.undefined
$this->dbs->execute();

return true;
