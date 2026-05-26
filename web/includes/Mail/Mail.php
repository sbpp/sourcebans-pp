<?php

// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Mail;

use LogType;
use Sbpp\Log;
use Throwable;

final class Mail
{
    public static function send(
        array|string $destinations,
        EmailType $type,
        array $variables = [],
        ?string $customSubject = null): bool
    {
        $mailer = Mailer::create();
        if ($mailer === null) {
            // #1269: Mailer::create() returns null when smtp.host /
            // smtp.user / smtp.pass aren't configured — exactly the
            // state a freshly-upgraded 1.x panel is in (the legacy
            // PHP-mail() flow doesn't carry SMTP credentials forward).
            // Log the actionable cause once instead of letting the
            // dispatch below throw "Call to a member function send()
            // on null" which then gets caught as a generic mail
            // failure.
            Log::add(LogType::Error, 'Mail not configured', 'SMTP host / user / password are empty in sb_settings; configure them under Admin → Settings before sending mail.');
            return false;
        }

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
            Log::add(LogType::Error, 'Mail error', $e->getMessage());
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