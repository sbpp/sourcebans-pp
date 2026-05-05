<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * "Email player" surface rendered by `web/pages/admin.email.php` after a
 * submission/protest's contact link is followed — binds to
 * `page_admin_bans_email.tpl`.
 *
 * `$email_js` is the literal JavaScript expression invoked when the
 * "Send email" button is clicked (e.g. `CheckEmail('s', 42)`). The
 * template drops it raw into `onclick="…"`; the `CheckEmail()` helper
 * is installed by the inline page-local script in the template. The
 * value is server-built from the dispatcher's own `$_GET['type']` /
 * `$_GET['id']` after type/integer validation, so no user input flows
 * through unescaped.
 */
final class AdminBansEmailView extends View
{
    public const TEMPLATE = 'page_admin_bans_email.tpl';

    public function __construct(
        public readonly string $email_addr,
        public readonly string $email_js,
    ) {
    }
}
