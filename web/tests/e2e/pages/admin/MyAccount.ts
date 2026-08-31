import type { Locator, Page } from '@playwright/test';

import { BasePage } from '../_base.ts';

/**
 * Your account (`?p=account`).
 *
 * Pair: `web/pages/page.youraccount.php` -> Sbpp\View\YourAccountView
 * + `page_youraccount.tpl`. The route lives at top-level `?p=account`
 * (not under `?p=admin`); navbar.tpl wires the "Logged in as <user>"
 * link to it. The page header carries `data-testid="account-header"`
 * unconditionally for any logged-in user — that's the mount signal.
 */
export class MyAccountPage extends BasePage {
    constructor(page: Page) {
        super(page);
    }

    readonly path = '/index.php?p=account';

    get pageMounted(): Locator {
        return this.page.locator('[data-testid="account-header"]');
    }

    async goto(): Promise<void> {
        await super.goto(this.path);
    }

    get tokensCard(): Locator {
        return this.page.locator('[data-testid="account-tokens"]');
    }

    get tokenName(): Locator {
        return this.page.locator('[data-testid="account-token-name"]');
    }

    get tokenCreate(): Locator {
        return this.page.locator('[data-testid="account-token-create"]');
    }

    get tokenSecret(): Locator {
        return this.page.locator('[data-testid="account-token-secret"]');
    }
}
