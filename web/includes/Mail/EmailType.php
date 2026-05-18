<?php

// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under Creative Commons Attribution-NonCommercial-ShareAlike 3.0.
// See LICENSE.md for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Mail;

enum EmailType {
    case PasswordResetSuccess;
    case BanSubmission;
    case PasswordReset;
    case BanProtest;
    case BanAdded;
    case Custom;

    public function template(): string
    {
        return match ($this)
        {
            EmailType::PasswordResetSuccess => 'pass_reset_successful',
            EmailType::BanSubmission => 'ban_submission',
            EmailType::PasswordReset => 'pass_reset',
            EmailType::BanProtest => 'ban_protest',
            EmailType::BanAdded => 'ban_added',
            EmailType::Custom => 'contact_custom'
        };
    }

    public function subject(): string
    {
        return match ($this)
        {
            EmailType::PasswordResetSuccess,
            EmailType::PasswordReset => 'Password reset',
            EmailType::BanSubmission => 'Ban submission',
            EmailType::BanProtest => 'Ban protest',
            EmailType::BanAdded => 'Ban added',
            EmailType::Custom => 'Contact'
        };
    }
}
