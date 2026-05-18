{*
    SourceBans++ 2026 — page_404.tpl

    "Page not found" page slot. Pair: web/pages/page.404.php +
    web/includes/View/NotFoundView.php (#1207 ADM-1).

    The chrome (sidebar + topbar + footer) renders normally — only
    the page slot is the error message, so the user can navigate
    away without backtracking. The HTTP 404 status is set in
    route() before the chrome renders, so crawlers / monitoring
    see the correct signal.

    No View properties are declared because the message is static.
    SmartyTemplateRule guards the template against accidentally
    growing untyped variable references.

    Testability hooks:
      - data-testid="page-404"     — outer wrapper (E2E spec target)
      - data-testid="page-404-home" — home link CTA
*}
<div class="not-found" data-testid="page-404">
    <div class="card not-found__card">
        <div class="card__body" style="padding:2rem;text-align:center">
            <div class="not-found__icon" aria-hidden="true">
                <i data-lucide="circle-help" style="width:2rem;height:2rem"></i>
            </div>
            <h1 class="not-found__title">Page not found</h1>
            <p class="text-muted">
                The page you&rsquo;re looking for doesn&rsquo;t exist or has been moved.
                Check the URL, or jump back to a page you know.
            </p>
            <div class="flex justify-center gap-2 mt-6">
                <a class="btn btn--primary"
                   href="index.php"
                   data-testid="page-404-home">
                    <i data-lucide="home"></i> Back to dashboard
                </a>
                <a class="btn btn--secondary"
                   href="javascript:history.back();"
                   data-testid="page-404-back">
                    <i data-lucide="arrow-left"></i> Go back
                </a>
            </div>
        </div>
    </div>
</div>

{literal}
<style>
    .not-found { display:flex; align-items:flex-start; justify-content:center; padding:3rem 1rem; }
    .not-found__card { width:100%; max-width:32rem; }
    .not-found__icon {
        width: 3.5rem; height: 3.5rem; border-radius: var(--radius-xl);
        background: var(--bg-muted); color: var(--text-muted);
        display: grid; place-items: center; margin: 0 auto 1rem;
    }
    .not-found__title {
        font-size: var(--fs-2xl); font-weight: 600; letter-spacing: -0.02em;
        color: var(--text); margin: 0 0 0.5rem;
    }
</style>
{/literal}
