<?php
// PHPStan-only rule. Autoloaded via the `phpstan/` classmap entry in
// web/composer.json so PHPStan's DI container can wire it up at service
// instantiation time (which happens before `bootstrapFiles` run), but
// excluded from analysis in phpstan.neon and never touched by the runtime
// app — the directory is intentionally outside the project's PSR-4 root.

declare(strict_types=1);

namespace Sbpp\PhpStan;

use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\ShouldNotHappenException;
use staabm\PHPStanDba\DbaException;
use staabm\PHPStanDba\QueryReflection\QueryReflection;
use staabm\PHPStanDba\UnresolvableQueryException;

/**
 * Replacement for {@see \staabm\PHPStanDba\Rules\SyntaxErrorInQueryMethodRule}
 * that understands the project's `:prefix_<table>` placeholder.
 *
 * The upstream rule funnels every query through
 * {@see QueryReflection::validateQueryString()}, which silently bails out on
 * the very first `:foo` token (it can't tell our table-name placeholder from
 * a real PDO bind). Since virtually every query in the codebase contains
 * `:prefix_…`, that means the upstream rule never actually validates anything.
 *
 * Here we (1) pre-substitute `:prefix` to the configured prefix string —
 * matching Database::setPrefix() — and (2) stub any remaining named (`:name`)
 * or positional (`?`) bind placeholders with neutral SQL literals so MariaDB
 * will still parse the statement. We deliberately don't try to type-check the
 * bound values themselves: the project's API splits `query()` and `bind()`
 * into separate calls, so the values live in different scopes that PHPStan
 * can't tie back to the query string. What we get instead is structural
 * checking — table names, column names, and overall syntax — which is
 * enough to catch column renames and the kind of typo this rule was added
 * for (#1100).
 *
 * @implements Rule<CallLike>
 */
final class SbppSyntaxErrorInQueryMethodRule implements Rule
{
    /**
     * @param list<string> $classMethods entries are `Class::method#argIndex`.
     */
    public function __construct(
        private readonly array $classMethods,
        private readonly ReflectionProvider $reflectionProvider,
        private readonly string $prefix,
    ) {
    }

    public function getNodeType(): string
    {
        return CallLike::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if ($node instanceof MethodCall) {
            if (! $node->name instanceof Identifier) {
                return [];
            }
            $methodReflection = $scope->getMethodReflection($scope->getType($node->var), $node->name->toString());
        } elseif ($node instanceof StaticCall) {
            if (! $node->name instanceof Identifier || ! $node->class instanceof Name) {
                return [];
            }
            $methodReflection = $scope->getMethodReflection($scope->resolveTypeByName($node->class), $node->name->toString());
        } else {
            return [];
        }

        if ($methodReflection === null) {
            return [];
        }

        $queryArgPosition = null;
        $supported = false;
        foreach ($this->classMethods as $classMethod) {
            sscanf($classMethod, '%[^::]::%[^#]#%i', $className, $methodName, $queryArgPosition);
            if (! is_string($className) || ! is_string($methodName) || ! is_int($queryArgPosition)) {
                throw new ShouldNotHappenException('Invalid classMethod definition: ' . $classMethod);
            }

            if (
                $methodName === $methodReflection->getName()
                && (
                    $methodReflection->getDeclaringClass()->getName() === $className
                    || (
                        $this->reflectionProvider->hasClass($className)
                        && $methodReflection->getDeclaringClass()->isSubclassOfClass($this->reflectionProvider->getClass($className))
                    )
                )
            ) {
                $supported = true;
                break;
            }
        }

        if (! $supported || $queryArgPosition === null) {
            return [];
        }

        $args = $node->getArgs();
        if (! array_key_exists($queryArgPosition, $args)) {
            return [];
        }

        try {
            $queryReflection = new QueryReflection();
        } catch (DbaException) {
            // The bootstrap intentionally skips reflector setup when the
            // schema-only DB is unreachable or PHPSTAN_DBA_DISABLE=1, so
            // analysis can still run offline. Treat that as "no DBA-level
            // checks" rather than a hard error.
            return [];
        }

        $queryExpr = $args[$queryArgPosition]->value;

        if ($queryReflection->isResolvable($queryExpr, $scope)->no()) {
            return [];
        }

        try {
            $queryStrings = $queryReflection->resolveQueryStrings($queryExpr, $scope);
            foreach ($queryStrings as $queryString) {
                $resolved = $this->resolvePrefix($queryString);

                // Database queries are usually written as
                //   $db->query("… :user …")->bind(':user', $u)->single();
                // — by the time the query reaches us, the bind() values live
                // in a follow-up call that's far out of scope, so we stub the
                // placeholders out with neutral literals and just validate the
                // SQL skeleton: tables exist, columns exist, statement parses.
                // phpstan-dba's own SyntaxErrorInQueryMethodRule short-circuits
                // any time `countPlaceholders()` > 0, which would silently
                // skip every query in the codebase that uses :prefix_<table>.
                $resolved = $this->stubPlaceholders($resolved);

                $error = $queryReflection->validateQueryString($resolved);
                if ($error !== null) {
                    return [
                        RuleErrorBuilder::message($error->asRuleMessage())
                            ->identifier('sbpp.dba.syntaxError')
                            ->line($node->getStartLine())
                            ->build(),
                    ];
                }
            }
        } catch (UnresolvableQueryException $e) {
            return [
                RuleErrorBuilder::message($e->asRuleMessage())
                    ->tip($e::getTip())
                    ->identifier('sbpp.dba.unresolvableQuery')
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }

        return [];
    }

    private function resolvePrefix(string $queryString): string
    {
        return str_replace(':prefix', $this->prefix, $queryString);
    }

    /**
     * Replace any remaining `:name` named placeholders and `?` positional
     * placeholders with neutral SQL literals so MariaDB will at least PARSE
     * the query for us. The substitute value (`1`) is deliberately the
     * narrowest type that fits the most contexts (numeric, boolean, casts to
     * string for VARCHAR comparisons) — false positives where a query is
     * legal at runtime but rejected here are accepted as the cost of seeing
     * any of these queries at all.
     *
     * Both regexes mirror phpstan-dba's QueryReflection patterns:
     * `(["\'])((?:(?!\1)(?s:.))*\1)` matches a quoted string literal so
     * placeholders inside `WHERE x = ':literal'` are NOT rewritten. The
     * `:[a-zA-Z0-9_]+` body comes from PDO's own SQL parser
     * (php-src/ext/pdo/pdo_sql_parser.re).
     */
    private function stubPlaceholders(string $queryString): string
    {
        // Order matters: substitute named placeholders first so an inner `:`
        // doesn't get gobbled by the `?` pass.
        $stubbed = preg_replace_callback(
            '{(["\'])((?:(?!\1)(?s:.))*\1)|(:[a-zA-Z0-9_]+)}',
            static fn (array $m): string => isset($m[3]) && $m[3] !== '' ? '1' : $m[0],
            $queryString
        ) ?? $queryString;

        return preg_replace_callback(
            '{(["\'])((?:(?!\1)(?s:.))*\1)|(\?)}',
            static fn (array $m): string => isset($m[3]) && $m[3] !== '' ? '1' : $m[0],
            $stubbed
        ) ?? $stubbed;
    }
}
