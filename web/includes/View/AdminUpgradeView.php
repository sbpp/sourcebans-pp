<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * Admin → Upgrade page (`?p=admin&c=upgrade`).
 *
 * Wraps the same {@see \Sbpp\Migrator\Migrator} pipeline the CLI uses so
 * an operator can preview the dry-run diff and apply it without shelling
 * into the host. Gated to `ADMIN_OWNER` by the router.
 */
final class AdminUpgradeView extends View
{
    public const TEMPLATE = 'page_admin_upgrade.tpl';

    /**
     * @param array{
     *     ok: bool,
     *     total: int,
     *     tables: list<array{name: string, sql: string}>,
     *     columns: list<array{table: string, column: string, sql: string}>,
     *     settings: list<array{key: string, value: string}>,
     * } $plan
     */
    public function __construct(
        public readonly bool $permission_owner,
        public readonly array $plan,
        public readonly ?string $plan_error,
    ) {
    }
}
