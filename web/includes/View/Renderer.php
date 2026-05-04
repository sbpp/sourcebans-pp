<?php
declare(strict_types=1);

namespace Sbpp\View;

use Smarty\Smarty;

/**
 * Copies every public property of a {@see View} onto a Smarty instance and
 * optionally renders the view's bound template.
 *
 * Use {@see Renderer::render()} for the common "assign + display" case, or
 * {@see Renderer::assign()} when the page still needs to emit extra template
 * variables or display more than one template off the same data.
 */
final class Renderer
{
    /**
     * Assign every public property of $view onto the Smarty instance and
     * display the template identified by the view's TEMPLATE constant.
     */
    public static function render(Smarty $theme, View $view): void
    {
        self::assign($theme, $view);
        $template = static::templateOf($view);
        if ($template === '') {
            throw new \LogicException(sprintf(
                'View %s has no TEMPLATE set; override the constant to bind it to a .tpl file.',
                $view::class,
            ));
        }
        $theme->display($template);
    }

    /**
     * Copy every public property of $view onto the Smarty instance via
     * $theme->assign(). No template is rendered.
     */
    public static function assign(Smarty $theme, View $view): void
    {
        foreach (get_object_vars($view) as $name => $value) {
            $theme->assign($name, $value);
        }
    }

    /**
     * Return the TEMPLATE constant for a given view instance.
     */
    public static function templateOf(View $view): string
    {
        /** @var string $template */
        $template = $view::TEMPLATE;
        return $template;
    }
}
