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
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

class Mailer
{

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
     * @param string $subject
     * @param string $body
     * @param array|null $files
     * @return bool
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

        return $mailer->send($mail) !== null;
    }

    /**
     * @return ?Mailer
     */
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
        $from = "SourceBans <{$config[1]}>";

        return new Mailer($config[0], $config[1], $config[2], $from, $port, $verifyPeer);
    }
}
