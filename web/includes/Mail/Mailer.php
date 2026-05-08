<?php

/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2024 by SourceBans++ Dev Team

The SourceBans++ Web panel is licensed under a
Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

You should have received a copy of the license along with this
work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.

This program is based off work covered by the following copyright(s):
SourceBans 1.4.11
Copyright © 2007-2014 SourceBans Team - Part of GameConnect
Licensed under CC-BY-NC-SA 3.0
Page: <http://www.sourcebans.net/> - <http://www.gameconnect.net/>
 *************************************************************************/

declare(strict_types=1);

namespace Sbpp\Mail;

use Config;
use Log;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

final class Mailer
{
    /** Default From Name used when both `config.mail.from_name` and SB_EMAIL fallback yield no display name. */
    public const DEFAULT_FROM_NAME = 'SourceBans++';

    /** Latched so we only emit one deprecation warning per process when the legacy SB_EMAIL fallback is used. */
    private static bool $sbEmailDeprecationLogged = false;

    public function __construct(
        private readonly string $host,
        private readonly string $user,
        private readonly string $password,
        private readonly string $from,
        private readonly ?int $port = null,
        private readonly bool $verifyPeer = true
    ) {}

    /**
     * @param string|string[] $destination
     * @param array<int, string>|null $files
     * @throws TransportExceptionInterface
     */
    public function send(array|string $destination,
                         string $subject, string $body,
                         ?array $files = null
    ): bool
    {
        $encodedUser = urlencode($this->user);
		$encodedPassword = urlencode($this->password);
		$dsn = "smtp://$encodedUser:$encodedPassword@$this->host:$this->port";

        if ($this->port != null)
            $dsn .= ":$this->port";

        if(!$this->verifyPeer)
            $dsn .= '?verify_peer=false';

        $mailer = Transport::fromDsn($dsn);

        $mail = $this->buildMessage($destination, $subject, $body, $files);

        return $mailer->send($mail) !== null;
    }

    /**
     * Build the `Email` payload that {@see send()} hands to the transport.
     * Public so tests can capture the envelope (notably the `From` header)
     * without binding a real SMTP server. The transport is intentionally
     * left out — adding it would require a live socket.
     *
     * @param string|string[] $destination
     * @param array<int, string>|null $files
     */
    public function buildMessage(array|string $destination,
                                 string $subject,
                                 string $body,
                                 ?array $files = null): Email
    {
        $mail = (new Email())
            ->from($this->from)
            ->subject($subject)
            ->html($body);

        if (is_array($destination)) {
            $mail->to(...$destination);
        } else {
            $mail->to($destination);
        }

        if ($files)
            foreach ($files as $file)
                $mail->attachFromPath($file);

        return $mail;
    }

    /** Resolved sender (`"Name" <email>` or just `email`) the mailer was constructed with. */
    public function from(): string
    {
        return $this->from;
    }

    public static function create(): ?Mailer
    {
        $config = Config::getMulti([
            'smtp.host', 'smtp.user',
            'smtp.pass', 'smtp.port', 'smtp.verify_peer'
        ]);

        if (empty($config[0]) || empty($config[1]) || empty($config[2]))
            return null;

        $port = empty($config[3]) ? null : (int) $config[3];
        $verifyPeer = boolval((int) $config[4]);
        $from = self::resolveFrom();

        return new Mailer($config[0], $config[1], $config[2], $from, $port, $verifyPeer);
    }

    /**
     * Compose the `From` header for outgoing mail.
     *
     * Preference order:
     *   1. `config.mail.from_email` + `config.mail.from_name` from `sb_settings`.
     *   2. The legacy `SB_EMAIL` constant (with a deprecation warning logged once
     *      per process so legacy installs don't black-hole mail before they hit
     *      the migrator).
     *
     * Returns `"Name" <email>` when both name and email are available, or an
     * empty string when neither is configured (in which case the caller should
     * expect Symfony to throw on send because the message would have no sender).
     */
    public static function resolveFrom(): string
    {
        $email = trim((string) Config::get('config.mail.from_email'));
        $name = trim((string) Config::get('config.mail.from_name'));

        if ($email === '') {
            $sbEmail = defined('SB_EMAIL') ? trim((string) SB_EMAIL) : '';
            if ($sbEmail !== '') {
                if (!self::$sbEmailDeprecationLogged) {
                    self::$sbEmailDeprecationLogged = true;
                    Log::add(
                        'w',
                        'Mail config deprecated',
                        'Falling back to the legacy SB_EMAIL constant for the From header. '
                        . 'Set config.mail.from_email in Admin → Settings; SB_EMAIL will be removed in a future release.'
                    );
                }
                $email = $sbEmail;
            }
        }

        if ($email === '') {
            return '';
        }

        if ($name === '') {
            $name = self::DEFAULT_FROM_NAME;
        }

        return self::formatFrom($name, $email);
    }

    /**
     * Test-only seam to clear the once-per-process latch on the SB_EMAIL
     * deprecation warning so each test that exercises the fallback path
     * sees a fresh log entry.
     */
    public static function resetDeprecationLatch(): void
    {
        self::$sbEmailDeprecationLogged = false;
    }

    private static function formatFrom(string $name, string $email): string
    {
        return sprintf('"%s" <%s>', addcslashes($name, '"\\'), $email);
    }
}
