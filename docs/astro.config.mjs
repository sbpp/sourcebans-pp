// @ts-check
//
// Astro + Starlight config for the SourceBans++ docs site.
//
// Cross-references:
//   - Issue #1333 (the migration spec)
//   - web/themes/default/css/theme.css   — panel tokens mirrored in src/styles/sbpp.css
//   - .github/workflows/docs-deploy-trigger.yml — fires repository_dispatch into sbpp.github.io
//
// `site` is the org-pages root because sbpp.github.io publishes from `/`.
// Keep `base: '/'` — anything else breaks Pagefind's static index paths.

import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';

// `astro-mermaid` was scaffolded into the initial migration commit but
// no docs page actually uses a fenced ```mermaid block yet. The
// integration adds ~36KB of integration code + ~600KB of Mermaid
// runtime as a Vite chunk family + Dependabot churn for a feature
// nothing pays for today (#1333 review M3). When a diagram surface
// genuinely needs to land — likeliest place is the upgrader / install
// flow — re-add the integration in the same PR as the first
// `mermaid` codeblock so the bundle weight has a paying customer.

export default defineConfig({
  site: 'https://sbpp.github.io/',
  base: '/',
  integrations: [
    starlight({
      title: 'SourceBans++',
      description:
        'SourceBans++ documentation — installing, upgrading, and operating the panel + game plugins.',
      logo: {
        src: './src/assets/logo.svg',
        alt: 'SourceBans++',
        replacesTitle: false,
      },
      favicon: '/favicon.svg',
      // Visual identity: the panel-parity overrides + a tiny inline script
      // that flips the Starlight theme cookie to 'light' the first time
      // a visitor lands. The panel boots light; matching first-paint
      // experience is the whole point of the override (#1333 §2).
      customCss: ['./src/styles/sbpp.css'],
      // Component overrides:
      //   - ThemeProvider: matches the panel's "default to system, paint
      //     light when JS isn't available" first-paint contract.
      //   - Footer: appends a "Support SourceBans++" affordance below
      //     Starlight's stock per-page footer (edit link + last updated
      //     + pagination). The stock sub-components are pulled from
      //     Starlight's `virtual:` namespace so a future Starlight
      //     upgrade that adds new footer chrome picks it up
      //     automatically. The link targets the canonical `/sponsor/`
      //     landing page (issue #1416) rather than a single platform
      //     URL so a future Open Collective / Patreon addition is a
      //     data-only change. See ./src/components/Footer.astro for
      //     the full rationale.
      components: {
        ThemeProvider: './src/components/ThemeProvider.astro',
        Footer: './src/components/Footer.astro',
      },
      head: [
        {
          tag: 'meta',
          attrs: { name: 'theme-color', content: '#ea580c' },
        },
        // Cloudflare Web Analytics — privacy-friendly traffic stats
        // for sbpp.github.io. No cookies, no fingerprinting, no
        // cross-site tracking; the beacon just POSTs an anonymised
        // page-view ping per navigation. The `data-cf-beacon` token
        // is a public site identifier (it has to ship in the HTML
        // for the beacon to work) — it's not a credential and can't
        // be used to read the analytics dashboard.
        {
          tag: 'script',
          attrs: {
            defer: true,
            src: 'https://static.cloudflareinsights.com/beacon.min.js',
            'data-cf-beacon': '{"token": "900ab004c40747e099a22fa226b7fb29"}',
          },
        },
      ],
      // Starlight 0.33 changed `social` from a `Record<KnownPlatform, url>`
      // map to a `[{icon, label, href}]` array (see the changelog at
      // https://github.com/withastro/starlight/blob/main/packages/starlight/CHANGELOG.md#0330).
      // Migrated alongside the @astrojs/starlight ^0.30 → ^0.39 bump
      // in this PR.
      social: [
        {
          icon: 'github',
          label: 'GitHub',
          href: 'https://github.com/sbpp/sourcebans-pp',
        },
        {
          icon: 'discord',
          label: 'Discord',
          href: 'https://discord.gg/tzqYqmAtF5',
        },
        {
          // Heart icon in the topbar social row. Routes to the
          // canonical `/sponsor/` landing page (issue #1416) which
          // lists every funding platform and the sponsor roll —
          // NOT directly to GitHub Sponsors. Adding Open Collective
          // / Patreon / etc. later is a one-line edit on
          // docs/src/data/sponsors.json; this entry stays put.
          icon: 'heart',
          label: 'Support SourceBans++',
          href: '/sponsor/',
        },
      ],
      editLink: {
        // Source of truth lives in sourcebans-pp; the deploy shell is
        // sbpp.github.io. Edit links point back here.
        baseUrl:
          'https://github.com/sbpp/sourcebans-pp/edit/main/docs/',
      },
      lastUpdated: true,
      sidebar: [
        {
          label: 'Getting Started',
          items: [
            { label: 'Overview', slug: 'getting-started/overview' },
            { label: 'Requirements', slug: 'getting-started/prerequisites' },
            { label: 'Quickstart', slug: 'getting-started/quickstart' },
            { label: 'Quickstart (Docker)', slug: 'getting-started/quickstart-docker' },
          ],
        },
        {
          label: 'Setup',
          items: [
            { label: 'Adding a server', slug: 'setup/adding-server' },
            { label: 'Plugin setup', slug: 'setup/plugin-setup' },
            { label: 'Admins & groups', slug: 'setup/admins-and-groups' },
            { label: 'Network ports', slug: 'setup/ports' },
            { label: 'Database setup', slug: 'setup/mariadb' },
          ],
        },
        {
          label: 'Configuring',
          items: [
            { label: 'Project announcements', slug: 'configuring/announcements' },
          ],
        },
        {
          label: 'Updating',
          items: [
            { label: 'Updating SourceBans++', slug: 'updating' },
            { label: 'Upgrading from 1.8.x to 2.0.x', slug: 'updating/1-8-to-2-0' },
          ],
        },
        {
          label: 'Troubleshooting',
          items: [
            { label: "Panel won't load", slug: 'troubleshooting/panel-not-loading' },
            {
              label: 'Driver not found',
              slug: 'troubleshooting/could-not-find-driver',
            },
            { label: 'Database errors', slug: 'troubleshooting/database-errors' },
            {
              label: 'Server connection',
              slug: 'troubleshooting/debugging-connection',
            },
          ],
        },
        {
          label: 'Integrations',
          items: [
            {
              label: 'Discord notifications',
              slug: 'integrations/discord-forward-setup',
            },
          ],
        },
        {
          label: 'Customization',
          items: [
            {
              label: 'Dashboard intro',
              slug: 'customization/removing-default-message',
            },
            { label: 'Translating', slug: 'customization/translating' },
          ],
        },
        {
          label: 'FAQ',
          link: '/faq/',
        },
      ],
    }),
  ],
});
