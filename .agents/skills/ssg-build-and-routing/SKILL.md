---
name: ssg-build-and-routing
description: Master custom React Static Site Generator (SSG) compiler, dynamic pages router, sitemaps, and relative path asset-prefix compilation.
---

# Custom Static Site Generator (SSG) & Routing Standards

Architectural compile-time workflow for the OceanViewFlats React 19 Static Site Generator (`render.tsx` -> `dist/`).

## Build Pipeline (`npm run build`)

1. **Clean & Mirror Assets**: Wipes `dist/` and copies raw assets from `public/` to `dist/`.
2. **Compile Pricing Cache**: Parses `public/data/prices.csv` into `public/cache/prices.json` and `dist/cache/prices.json`.
3. **Static HTML Generation (`render.tsx`)**:
   - Loops through every route in `src/config/pages.ts` across all 6 locales (`['en', 'es', 'fr', 'it', 'de', 'ja']`).
   - Computes `assetPrefix` (`./`, `../`, or `/` for `404`) and `apiBase` (`${assetPrefix}api/`).
   - Renders the React component via `renderToStaticMarkup(<PageComponent lang={lang} assetPrefix={assetPrefix} />)`.
   - Extracts the page-scoped `i18n` slice (`getClientDictionaryForPage`) and extra config (`getClientExtraConfigForPage`) and serializes `<script id="ovf-page-config" type="application/json">` inside `src/templates/base.ts` ([ADR 0010](../../../docs/adr/0010-multilingual-page-hydration-seam.md)).
4. **Sitemap Generation**: Writes `dist/sitemap.xml` for indexable public pages (excluding `404`, `registry`, `guide`, `booking-success`, `booking-failure`, and `booking-pending`).
5. **Tailwind CSS v4 & JS Minification**: Runs `vite build` to emit `dist/css/style.css`, followed by `terser` minification for `page-config.js`, `main.js`, `lang-detect.js`, `registry.js`, and `guide.js`.

## Adding or Modifying Routes (`src/config/pages.ts`)

Every route in `pages: PageConfig[]` defines:
- `id`: Route identifier (`'home'`, `'Oceanview1707'`, `'Oceanview1606'`, `'contact'`, `'registry'`, `'guide'`, `'privacy'`, etc.).
- `path`: URL directory segment (`""`, `"Oceanview1707"`, `"privacy"`, etc.).
- `component`: React component accepting `{ lang, assetPrefix }`.
- `filename(lang)`: Output path relative to `dist/` (e.g. `lang === "en" ? "privacy/index.html" : "privacy/" + lang + ".html"`).
- `seoTitle(t)` & `seoDescription(t)`: Localized metadata selectors from `src/i18n/dict.ts`.
- `ogImage`: OpenGraph image path from `src/constants/config.ts`.
- `getStructuredData(t, lang, baseUrl)`: Schema.org JSON-LD builder.
- `scripts`: Client scripts loaded after `js/page-config.js`.
- `clientI18n?(t)`: Optional custom dictionary slice override for `window.t()`.

## Relative Asset Prefix Rule

Always pass `assetPrefix` down to child components and prepend `${assetPrefix}` to all local asset and page links (`<img src={`${assetPrefix}images/...`} />`).
