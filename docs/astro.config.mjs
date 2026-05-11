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
import mermaid from 'astro-mermaid';

export default defineConfig({
  site: 'https://sbpp.github.io/',
  base: '/',
  // mermaid integration must come BEFORE starlight so its remark plugin
  // sees fenced ```mermaid blocks in Markdown processed by Starlight.
  // The integration is lightweight client-side (no puppeteer); diagrams
  // hydrate per-page when a `mermaid` codeblock is present.
  integrations: [
    mermaid({
      theme: 'default',
      autoTheme: true,
    }),
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
      //   - Footer: appends a "Legacy docs" affordance below Starlight's
      //     stock per-page footer chrome. The legacy section is
      //     intentionally excluded from the main sidebar (#1333 §5);
      //     the footer link is the chrome-level discovery hook.
      components: {
        ThemeProvider: './src/components/ThemeProvider.astro',
        Footer: './src/components/Footer.astro',
      },
      head: [
        {
          tag: 'meta',
          attrs: { name: 'theme-color', content: '#ea580c' },
        },
      ],
      // Starlight 0.30 takes `social` as a record keyed by platform
      // name (the schema is `Record<KnownPlatform, url>` in
      // node_modules/@astrojs/starlight/schemas/social.ts). Newer
      // Starlight (>= 0.32-ish) expanded this to the
      // `[{icon, label, href}]` array shape; bump this when the
      // dependency floor moves.
      social: {
        github: 'https://github.com/sbpp/sourcebans-pp',
        discord: 'https://discord.gg/4Bhj6NU',
      },
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
            { label: 'Quickstart', slug: 'getting-started/quickstart' },
            { label: 'Prerequisites', slug: 'getting-started/prerequisites' },
          ],
        },
        {
          label: 'Setup',
          items: [
            { label: 'Adding a server', slug: 'setup/adding-server' },
            { label: 'Plugin setup', slug: 'setup/plugin-setup' },
            { label: 'Ports', slug: 'setup/ports' },
            { label: 'MariaDB', slug: 'setup/mariadb' },
          ],
        },
        {
          label: 'Updating',
          items: [{ label: 'Updating SourceBans++', slug: 'updating' }],
        },
        {
          label: 'Troubleshooting',
          items: [
            { label: 'Browser freeze', slug: 'troubleshooting/browser-freeze' },
            {
              label: 'Could not find driver',
              slug: 'troubleshooting/could-not-find-driver',
            },
            { label: 'Database errors', slug: 'troubleshooting/database-errors' },
            {
              label: 'Debugging connection',
              slug: 'troubleshooting/debugging-connection',
            },
          ],
        },
        {
          label: 'Integrations',
          items: [
            {
              label: 'Discord forward',
              slug: 'integrations/discord-forward-setup',
            },
          ],
        },
        {
          label: 'Customization',
          items: [
            {
              label: 'Removing default message',
              slug: 'customization/removing-default-message',
            },
            { label: 'Translating', slug: 'customization/translating' },
          ],
        },
        {
          label: 'FAQ',
          items: [
            { label: 'Frequently asked questions', slug: 'faq' },
            { label: 'Common inquiries', slug: 'faq/inquiries' },
          ],
        },
        // Legacy section deliberately omitted from the main sidebar per
        // #1333 §5 — content is unmaintained and discovery happens via
        // the Footer override (src/components/Footer.astro) plus the
        // inline link from the Updating page.
      ],
    }),
  ],
});
