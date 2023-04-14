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
