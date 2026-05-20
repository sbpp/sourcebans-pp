<?php

namespace Sbpp\Auth\Handler;

use LightOpenID;
use Sbpp\Auth\Auth;
use Sbpp\Auth\Host;
use Sbpp\Config;
use Sbpp\Db\Database;

final class SteamAuthHandler
{
    public function __construct(
        private LightOpenID $openid,
        private Database $dbs
    ) {
        if ($this->openid->validate()) {
            $steamid = $this->validate();
            if ($steamid) {
                $this->check($steamid);
            }
        } elseif (!$this->openid->mode) {
            $this->login();
        }
    }

    private function login(): void
    {
        $this->openid->identity = 'https://steamcommunity.com/openid';
        header("Location: ".$this->openid->authUrl());
    }

    private function validate(): string|false
    {
        // #1423 follow-up #4 — tightened from `7[0-9]{15,25}+` to
        // exactly 17 digits (`\d{16}` after the literal `7`) to match
        // `SteamID::ID_PATTERNS`'s `^\d{17}$D` shape. Pre-fix a
        // 16-digit OR 18-25-digit OpenID claim slipped past this
        // regex but then failed `SteamID::toSteam2()` in `check()`
        // (which routes through the library's `\d{17}` gate), the
        // exception escaped the constructor unhandled, and the
        // operator landed on a 500 mid-Steam-login round-trip
        // (silent failure mode — there's no `try/catch` here and the
        // chrome's `PageDie()` doesn't run on a callback redirect).
        // Steam in practice always returns 17-digit Steam64 IDs in
        // the claimed_id URL; this regex now matches the library
        // contract byte-for-byte so a future Steam-side change that
        // emits a 16-digit ID (or a 24-digit one for some hypothetical
        // future user range) surfaces here as a clean false return
        // (operator sees the login-failed message), not as a 500.
        $pattern = "/^https:\/\/steamcommunity\.com\/openid\/id\/(7\d{16}+)$/D";

        // Issue #1273: $this->openid->data is $_POST / $_GET (mixed), and
        // PHPStan can't see that LightOpenID::validate() guarantees
        // openid_claimed_id is set on a real Steam round-trip — cast at
        // the call site to keep the bounded-diff strategy from openid.php.
        if (!preg_match($pattern, (string) ($this->openid->data['openid_claimed_id'] ?? '')))
            return false;

        preg_match($pattern, $this->openid->identity, $match);

        return (!empty($match[1])) ? $match[1] : false;
    }

    private function check(string $steamid): void
    {
        // Defense-in-depth: `validate()` already gates the input
        // through the strict 17-digit regex, but the library's
        // `toSteam2()` raises a generic `\Exception` on any input that
        // fails `isValidID()`. The exception would escape the
        // constructor's call site unhandled (LightOpenID's mid-flow
        // redirect leaves no chrome to catch it), so the gate here is
        // load-bearing belt-and-suspenders.
        if (!\SteamID\SteamID::isValidID($steamid)) {
            header("Location: ".Host::complete()."/index.php?p=login&m=steam_failed");
            return;
        }
        $steamid = \SteamID\SteamID::toSteam2($steamid);

        $this->dbs->query('SELECT aid FROM `:prefix_admins` WHERE authid = :authid');
        $this->dbs->bind(':authid', $steamid);
        $result = $this->dbs->single();

        if (!empty($result['aid'])) {
            $maxlife = Config::get('auth.maxlife.steam') * 60;
            Auth::login($result['aid'], $maxlife);
            header("Location: ".Host::complete());
            return;
        }

        header("Location: ".Host::complete()."/index.php?p=login&m=steam_failed");
    }
}

// Issue #1290 phase B: legacy global-name shim. Procedural code keeps
// using `\SteamAuthHandler` until the call-site sweep PR.
class_alias(\Sbpp\Auth\Handler\SteamAuthHandler::class, 'SteamAuthHandler');
