<?php
declare(strict_types=1);

// Install-wizard step dispatcher.
//
// Each `pages/page.<N>.php` file is a procedural page handler that
// reads its own POST input, runs its work, builds the matching
// `Sbpp\View\Install\Install*View` DTO, and calls
// `Sbpp\View\Renderer::render($theme, $view)`. The dispatcher is the
// single place that keeps the step number ↔ file name mapping
// honest; new steps land in the match below + a new file under
// `pages/`.

/**
 * Dispatch a wizard step to its page handler.
 *
 * The page file itself is responsible for any POST handling, View
 * construction, and Renderer::render() call. Each page renders a
 * complete HTML document (the install wizard runs outside the panel's
 * core/header.tpl chrome — see web/themes/default/install/_chrome.tpl
 * docblock for why), so this dispatcher stays thin.
 */
function sbpp_install_dispatch(int $step, \Smarty\Smarty $theme): void
{
    $page = match ($step) {
        1       => 'page.1.php',
        2       => 'page.2.php',
        3       => 'page.3.php',
        4       => 'page.4.php',
        5       => 'page.5.php',
        6       => 'page.6.php',
        default => 'page.1.php',
    };
    require TEMPLATES_PATH . '/' . $page;
}
