<?php
declare(strict_types=1);

namespace Sbpp\PHPStan;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use Sbpp\View\View;

/**
 * Reports mismatches between a concrete `Sbpp\View\View` subclass and its
 * Smarty template:
 *
 * 1. Missing variables — every `{$foo}` / `{$foo.bar}` / `{$foo->x}` /
 *    `{foreach from=$foo …}` / `{if $foo …}` reference in the template
 *    must correspond to a public property on the view.
 * 2. Unused public properties — every public property on the view must be
 *    referenced somewhere in the template tree.
 *
 * Templates use the Smarty default `{ … }` delimiter pair. Views whose
 * template renders with a different pair (e.g. `page_youraccount.tpl` using
 * `-{ … }-`) must override `View::DELIMITERS` to match.
 *
 * `{include file='other.tpl'}` is resolved transitively when the includee
 * can be found on disk; unresolvable includes are skipped silently so a
 * broken include never crashes the analyser.
 *
 * @implements Rule<Node\Stmt\Class_>
 */
final class SmartyTemplateRule implements Rule
{
    /**
     * Cache of variables referenced per template file, keyed by the
     * template's absolute path plus delimiter pair. Scanning each template
     * is cheap but repeated hits (e.g. dashboard transitively pulling in
     * `page_servers.tpl`) add up.
     *
     * @var array<string, array{vars: array<string, true>, includes: list<string>}>
     */
    private array $templateCache = [];

    public function __construct(
        private readonly string $templatesDir,
        private readonly ReflectionProvider $reflectionProvider,
    ) {
    }

    public function getNodeType(): string
    {
        return Node\Stmt\Class_::class;
    }

    /**
     * @param Node\Stmt\Class_ $node
     * @return list<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if ($node->isAbstract() || $node->namespacedName === null) {
            return [];
        }

        $className = $node->namespacedName->toString();
        if (!$this->reflectionProvider->hasClass($className)) {
            return [];
        }
        $reflection = $this->reflectionProvider->getClass($className);
        if (!$reflection->isSubclassOf(View::class)) {
            return [];
        }

        $template = $this->constantStringValue($reflection, 'TEMPLATE');
        if ($template === null || $template === '') {
            return [
                RuleErrorBuilder::message(sprintf(
                    'View %s must declare a non-empty TEMPLATE constant.',
                    $className,
                ))->identifier('sbpp.view.noTemplate')->build(),
            ];
        }

        $delimiters = $this->delimitersFor($reflection);

        $templatePath = rtrim($this->templatesDir, '/') . '/' . $template;
        if (!is_file($templatePath)) {
            return [
                RuleErrorBuilder::message(sprintf(
                    'View %s binds to template "%s" but the file does not exist at %s.',
                    $className,
                    $template,
                    $templatePath,
                ))->identifier('sbpp.view.templateMissing')->build(),
            ];
        }

        $referenced = $this->collectTemplateVars($templatePath, $delimiters, []);

        $declared = [];
        foreach ($reflection->getNativeReflection()->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            if ($prop->isStatic()) {
                continue;
            }
            if ($prop->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }
            $declared[$prop->getName()] = true;
        }

        $errors = [];

        foreach ($referenced as $var => $_) {
            if (!isset($declared[$var])) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Template "%s" references {$%s} but %s has no public $%s property.',
                    $template,
                    $var,
                    $className,
                    $var,
                ))->identifier('sbpp.view.missingProperty')->build();
            }
        }

        foreach ($declared as $var => $_) {
            if (!isset($referenced[$var])) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Property %s::$%s is never referenced in template "%s".',
                    $className,
                    $var,
                    $template,
                ))->identifier('sbpp.view.unusedProperty')->build();
            }
        }

        return $errors;
    }

    private function constantStringValue(
        \PHPStan\Reflection\ClassReflection $reflection,
        string $constantName,
    ): ?string {
        if (!$reflection->hasConstant($constantName)) {
            return null;
        }
        $type = $reflection->getConstant($constantName)->getValueType();
        $strings = $type->getConstantStrings();
        if (count($strings) !== 1) {
            return null;
        }
        return $strings[0]->getValue();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function delimitersFor(\PHPStan\Reflection\ClassReflection $reflection): array
    {
        if (!$reflection->hasConstant('DELIMITERS')) {
            return ['{', '}'];
        }
        $type = $reflection->getConstant('DELIMITERS')->getValueType();
        $arrays = $type->getConstantArrays();
        if (count($arrays) !== 1) {
            return ['{', '}'];
        }
        $values = $arrays[0]->getValueTypes();
        if (count($values) !== 2) {
            return ['{', '}'];
        }
        $left = $values[0]->getConstantStrings();
        $right = $values[1]->getConstantStrings();
        if (count($left) !== 1 || count($right) !== 1) {
            return ['{', '}'];
        }
        $l = $left[0]->getValue();
        $r = $right[0]->getValue();
        if ($l === '' || $r === '') {
            return ['{', '}'];
        }
        return [$l, $r];
    }

    /**
     * Walks a template (plus any {include file='…'} it pulls in) and returns
     * the set of referenced template variables.
     *
     * @param array{0: string, 1: string} $delimiters
     * @param list<string> $visited List of already-processed absolute
     *     template paths so circular includes can't loop forever.
     * @return array<string, true>
     */
    private function collectTemplateVars(string $path, array $delimiters, array $visited): array
    {
        $cacheKey = $path . '|' . $delimiters[0] . $delimiters[1];
        if (!isset($this->templateCache[$cacheKey])) {
            $this->templateCache[$cacheKey] = $this->parseTemplate($path, $delimiters);
        }

        $entry = $this->templateCache[$cacheKey];
        $vars = $entry['vars'];

        if (in_array($path, $visited, true)) {
            return $vars;
        }
        $visited[] = $path;

        foreach ($entry['includes'] as $includeName) {
            $includePath = rtrim($this->templatesDir, '/') . '/' . $includeName;
            if (!is_file($includePath)) {
                continue;
            }
            foreach ($this->collectTemplateVars($includePath, $delimiters, $visited) as $var => $_) {
                $vars[$var] = true;
            }
        }

        return $vars;
    }

    /**
     * Parse a single template file and return:
     *   - `vars`: set of variable names (excluding loop/assign locals)
     *   - `includes`: sub-template filenames pulled in via
     *     `{include file='…'}` that need their own variable check.
     *
     * @param array{0: string, 1: string} $delimiters
     * @return array{vars: array<string, true>, includes: list<string>}
     */
    private function parseTemplate(string $path, array $delimiters): array
    {
        $source = @file_get_contents($path);
        if ($source === false) {
            return ['vars' => [], 'includes' => []];
        }

        $source = preg_replace('#\{literal\}.*?\{/literal\}#s', '', $source) ?? $source;

        $left = preg_quote($delimiters[0], '#');
        $right = preg_quote($delimiters[1], '#');
        $tagRegex = sprintf('#%s([^%s]*?)%s#s', $left, preg_quote($delimiters[1][0], '#'), $right);

        if (!preg_match_all($tagRegex, $source, $matches, PREG_SET_ORDER)) {
            return ['vars' => [], 'includes' => []];
        }

        $vars = [];
        $includes = [];
        $locals = [];

        foreach ($matches as $match) {
            $body = trim($match[1]);
            if ($body === '' || $body[0] === '*' || $body[0] === '/' || $body[0] === '#') {
                if ($body !== '' && str_starts_with($body, '/')) {
                    continue;
                }
                continue;
            }

            if (preg_match('/^foreach\b/i', $body)) {
                $this->handleForeach($body, $vars, $locals);
                continue;
            }

            if (preg_match('/^assign\b/i', $body)) {
                $this->handleAssign($body, $vars, $locals);
                continue;
            }

            if (preg_match('/^include\b/i', $body)) {
                $this->handleInclude($body, $includes, $vars, $locals);
                continue;
            }

            if (preg_match('/^(if|elseif)\b(.*)$/is', $body, $parts)) {
                $this->collectVarRefs($parts[2], $vars, $locals);
                continue;
            }

            $this->collectVarRefs($body, $vars, $locals);
        }

        return ['vars' => $vars, 'includes' => $includes];
    }

    /**
     * @param array<string, true> $vars
     * @param array<string, true> $locals
     */
    private function handleForeach(string $body, array &$vars, array &$locals): void
    {
        // Modern: {foreach $source as $item}  or {foreach $source as $k => $v}
        if (preg_match('/^foreach\s+\$([a-zA-Z_][a-zA-Z0-9_]*)\b(.*)$/i', $body, $m)) {
            if (!isset($locals[$m[1]])) {
                $vars[$m[1]] = true;
            }
            $tail = $m[2];
            if (preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)/i', $tail, $locals_m)) {
                foreach ($locals_m[1] as $name) {
                    $locals[$name] = true;
                }
            }
            return;
        }
        // Legacy: {foreach from=$source item=x key=y name=z}
        if (preg_match('/from\s*=\s*\$([a-zA-Z_][a-zA-Z0-9_]*)/i', $body, $m)) {
            if (!isset($locals[$m[1]])) {
                $vars[$m[1]] = true;
            }
        }
        // item=foo, item="foo", item='foo'
        foreach (['item', 'key'] as $attr) {
            if (preg_match('/\b' . $attr . '\s*=\s*(?:"([a-zA-Z_][a-zA-Z0-9_]*)"|\'([a-zA-Z_][a-zA-Z0-9_]*)\'|([a-zA-Z_][a-zA-Z0-9_]*))/i', $body, $m)) {
                $name = $m[1] !== '' ? $m[1] : ($m[2] !== '' ? $m[2] : $m[3]);
                $locals[$name] = true;
            }
        }
    }

    /**
     * @param array<string, true> $vars
     * @param array<string, true> $locals
     */
    private function handleAssign(string $body, array &$vars, array &$locals): void
    {
        // {assign var=x value=...}  →  x becomes local; value may reference vars.
        if (preg_match('/var\s*=\s*"?([a-zA-Z_][a-zA-Z0-9_]*)"?/i', $body, $m)) {
            $locals[$m[1]] = true;
        }
        $this->collectVarRefs($body, $vars, $locals);
    }

    /**
     * @param list<string>        $includes
     * @param array<string, true> $vars
     * @param array<string, true> $locals
     */
    private function handleInclude(string $body, array &$includes, array &$vars, array &$locals): void
    {
        if (preg_match('/file\s*=\s*(["\'])([^"\']+)\1/i', $body, $m)) {
            $includes[] = $m[2];
        }
        $this->collectVarRefs($body, $vars, $locals);
    }

    /**
     * Scan a tag body for `$name` references (including chained access like
     * `$name.field` or `$name->foo()`), excluding any names currently
     * considered loop/assign locals.
     *
     * @param array<string, true> $vars
     * @param array<string, true> $locals
     */
    private function collectVarRefs(string $body, array &$vars, array $locals): void
    {
        if (!preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)/', $body, $m)) {
            return;
        }
        foreach ($m[1] as $name) {
            if (str_starts_with($name, 'smarty')) {
                continue;
            }
            if (isset($locals[$name])) {
                continue;
            }
            $vars[$name] = true;
        }
    }
}
