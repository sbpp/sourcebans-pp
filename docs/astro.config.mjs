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
      components: {
        ThemeProvider: './src/components/ThemeProvider.astro',
      },
      head: [
        {
          tag: 'meta',
          attrs: { name: 'theme-color', content: '#ea580c' },
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
