---
name: multi-language-workflow
description: Guides the end-to-end integration of new multi-language features across both frontend React components and backend PHP processor scripts. Use when translating UI text, adding form fields, or handling dynamic emails.
---

# Multi-Language (i18n) Workflow Guide

OceanViewFlats supports 6 locales across the entire stack: English (`en`), Spanish (`es`), French (`fr`), Italian (`it`), German (`de`), and Japanese (`ja`).

## 1. Frontend & Client Interactivity (`src/i18n/dict.ts` -> `#ovf-page-config`)

1. **Define Strings in `src/i18n/dict.ts`**:
   Add the key to `TranslationDict` and provide translations across all 6 locales (`en`, `es`, `fr`, `it`, `de`, `ja`).
2. **Static JSX Rendering**:
   Read translations in React components via `const t = dict[lang] || dict.en;`.
3. **Client-Side Vanilla JS ([ADR 0010](../../../docs/adr/0010-multilingual-page-hydration-seam.md))**:
   - If a string is needed at runtime in `public/js/*.js`, include its key in `getClientDictionaryForPage(pageId, t)` inside `src/config/pages.ts`.
   - Access it in JS via `window.t('keyName', { param: 'value' })` and read `lang`, `assetPrefix`, and `apiBase` via `window.getPageConfig()` (`public/js/page-config.js`).

```javascript
const pageConfig = window.getPageConfig();
const lang = pageConfig.lang || document.documentElement.lang || 'en';
const apiBase = pageConfig.apiBase || 'api/';
const message = window.t('guideWelcomeWithGuest', { guestName: 'Maria' });
```

## 2. Backend REST API & Transactional Emails (`public/api/translations.php`)

1. **Define Backend Strings**:
   Add module keys (`booking`, `contact`, `registry`, `webhook`) for all 6 locales inside `public/api/translations.php`.
2. **Resolve Validated Locale**:
   In `public/api/*.php`, resolve the request locale via `get_validated_lang()` (`public/api/utils.php`):
   ```php
   $lang = get_validated_lang();
   $t = $all_translations[$lang]['registry'] ?? $all_translations['en']['registry'];
   ```
3. **Asynchronous Lifecycle Emails**:
   Persist `$lang` on `reservations.lang` during checkout so asynchronous webhook settlements (`mercadopago-webhook.php`), Guest Registry dispatches, and cancellation notices render in the Primary Guest's preferred language.

## 3. Verification

Run `npm test && npm run typecheck && npm run build` to verify all 6 locale dictionaries and `#ovf-page-config` slices compile cleanly.
