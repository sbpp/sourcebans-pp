<?php

/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2023 by SourceBans++ Dev Team

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

use Log;
use Throwable;

class Mail
{
    public static function send(
        array|string $destinations,
        EmailType $type,
        array $variables = [],
        ?string $customSubject = null): bool
    {
        $mailer = Mailer::create();

        $content = str_replace(
            array_keys($variables),
            array_values($variables),
            self::getTemplateContent($type)
        );

        try {
            $mailer->send(
                destination: $destinations,
                subject: $customSubject ?? $type->subject(),
                body: $content
            );
        } catch (Throwable $e)
        {
            Log::add('e', 'Mail error', $e->getMessage());
            return false;
        }

        return true;
    }

    private static function getTemplateContent(EmailType $type): string
    {
        $path = SB_THEMES . SB_THEME . DIRECTORY_SEPARATOR . 'mails' . DIRECTORY_SEPARATOR . $type->template() . '.html';

        // Check if the custom theme has the email template
        // if not use the default theme emails
        if (SB_THEME !== 'default' && is_readable($path))
        {
            $path = str_replace(SB_THEME, 'default', $path);
        }

        return file_get_contents($path);
    }
}