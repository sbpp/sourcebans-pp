// @ts-check
//
// Content collection config for the docs site. We use Starlight's
// stock `docs` schema verbatim; no custom front-matter fields, no
// validators on top. Keeping this file minimal means Starlight version
// bumps don't need a paired schema edit here.

import { defineCollection } from 'astro:content';
import { docsLoader } from '@astrojs/starlight/loaders';
import { docsSchema } from '@astrojs/starlight/schema';

export const collections = {
  docs: defineCollection({
    loader: docsLoader(),
    schema: docsSchema(),
  }),
};
