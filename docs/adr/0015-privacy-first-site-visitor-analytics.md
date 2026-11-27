# Privacy-First Site Visitor Analytics and Booking Funnel Telemetry

## Status

Accepted (Complements [ADR 0010](0010-multilingual-page-hydration-seam.md), [ADR 0012](0012-personal-data-protection-and-ley-1581-compliance.md), and [ADR 0014](0014-opaque-outbound-feed-uids-and-token-gated-fulfillment.md))

## Context

OceanViewFlats already deploys self-hosted Matomo (`stats.fractalserver.com`, Site ID `2`) on all public pages, but its initial setup only captured raw pageviews, outbound links, and a handful of click events. That setup left major visibility and compliance gaps:

1. **Conversion and Goal Collision**: Both Direct Reservation payment submission (`public/js/main.js`) and Guest Registry completion (`public/js/registry.js`) fired Matomo `Goal 5`, conflating revenue conversions with post-booking fulfillment. In addition, `payment.php` responses triggered conversion tracking for every `success: true` payload—falsely counting abandoned PSE bank redirects and unredeemed Cash Vouchers (`status: "pending"`) as confirmed bookings.
2. **Funnel and Demand Blind Spots**: No signals were recorded for calendar browsing, unavailable-date encounters, Quote generation, minimum-stay rejections, Pending Reservation holds, Payment Brick readiness/declines, engagement depth, `lang-detect.js` automatic locale redirects, or 404 broken URLs.
3. **Privacy and Token Exposure Risks**: Matomo ran with first-party tracking cookies enabled without disclosure in the Data Processing Policy (`/privacy`), transmitted raw URLs and referrers containing `reservation_uid` (`?code=`) or guest names (`?guest=`) on `/guide/` and `/registry/` (weakening the token confidentiality boundary of ADR 0014 and the privacy posture of ADR 0012), and sent raw localized error text and exact Contact Form stay dates as event names.

## Decision

We standardize a **cookieless, PII-free, client-side Matomo analytics architecture** for **Site Visitor** insights using only existing tooling (no new third-party SaaS tools and no MySQL or Admin Console schema changes):

1. **Cookieless, Zero-PII Tracking Contract**:
   - Initialize Matomo with `disableCookies` via a dedicated, unit-testable `public/js/analytics.js` module loaded immediately after `public/js/page-config.js`, reading build-time page context (`pageType`, `propertyId`, `guideSlug`) from `<script id="ovf-page-config">`.
   - Apply a strict query-parameter **allowlist** (`utm_*`, `mtm_*` only) to both `setCustomUrl` and `setReferrerUrl` before `trackPageView`, stripping all sensitive or high-cardinality parameters (`code`, `token`, `guest`, `name`, `id`, `barcode`, `verif_code`, `voucher_url`, `check_in`, `check_out`).
   - Normalize all event categories, actions, and error/outcome names to **locale-independent English machine codes**, and express date-based **Demand Signals** solely as low-cardinality buckets (`YYYY-MM`, lead-time bucket, stay-length bucket) with numeric COP **Quote** amounts as Event Values.
   - Disclose self-hosted, cookieless Matomo analytics in the **Data Processing Policy** (`/privacy`) across all 6 locales and bump `PRIVACY_POLICY_VERSION` to `"2026-11"`.

2. **100% Client-Side Settlement Split & Canonical 7-Goal Map**:
   - Keep conversion tracking strictly client-side (avoiding Matomo server secrets and outbound HTTP dependencies in `public/api/mercadopago-webhook.php`).
   - Standardize Site ID `2` Goals:
     - `Goal 1`: General Contact **Inquiry** Submitted
     - `Goal 2`: Date-Specific Contact **Inquiry** Submitted
     - `Goal 3`: Airbnb Redirect Click (Property booking CTA)
     - `Goal 4`: WhatsApp Contact Click (Home, Property, and Contact surfaces)
     - `Goal 5`: **Direct Reservation Confirmed** (fired only on **Instant Settlement** `status === "approved"`, with COP **Quote** revenue)
     - `Goal 6`: **Pending Reservation** Created (checkout hold secured via `book-request.php`)
     - `Goal 7`: **Guest Registry** Completed (decoupled from Goal `5`)
   - Emit distinct checkout events for **Asynchronous Settlement** (`PSE Redirect Initiated`, `Cash Voucher Issued`) and payment failures; treat the Admin Console **Financial Report** as the authoritative ledger for settled asynchronous payments.

3. **Core-Matomo Custom Dimensions & Engagement Scope**:
   - Configure 5 Custom Dimensions on Site ID `2`: Action-scoped `Locale` (`1`), `Page Type` (`2`), and `Property` (`3`); Visit-scoped `Language Acquisition` (`4`, fed by a one-time `sessionStorage` handoff from `public/js/lang-detect.js`) and `Funnel Stage` (`5`, providing native **Booking Funnel** drop-off reporting on core free Matomo).
   - Enable `enableHeartBeatTimer(15)` and one-shot scroll milestones (`25%`, `50%`, `75%`, `90%`) exclusively on public discovery/marketing pages, keeping private fulfillment pages (`/registry/`, `/guide/`) limited to lightweight friction events.
   - Render unavailable calendar dates with `aria-disabled="true"` rather than native `disabled` so clicks on booked dates emit an `Unavailable Date Clicked` **Demand Signal** while remaining non-selectable and screen-reader accessible.

## Considered Options

- **Server-side Matomo HTTP Tracking API inside `mercadopago-webhook.php`**: Rejected because cookieless browser sessions cannot be stitched cleanly to delayed webhook callbacks, adding `token_auth` secret management and external network latency to payment settlement for minimal attribution gain over the Admin Console Financial Report.
- **Matomo Cookies + Consent Banner**: Rejected to avoid consent-banner UX friction and sample bias across 6 locales while preserving clean alignment with Colombian Law 1581 of 2012.
- **Matomo Ecommerce Module / Paid Funnels Plugin**: Rejected because short-term rental reservations do not map cleanly to multi-item shopping carts, and Visit-scoped `Funnel Stage` + COP Goal revenue deliver full funnel visibility on core free Matomo.
- **MySQL Demand Log in the Admin Console**: Rejected to keep the transactional reservation database and Admin Console scoped strictly to operational and financial records.

## Consequences

- Multi-day returning-visitor stitching in Matomo is less exact under `disableCookies`, which is an explicit trade-off for zero-banner privacy compliance and cleaner session-level funnel diagnostics.
- Any change to Matomo Goal IDs (`1`–`7`) or Custom Dimension slot IDs (`1`–`5`) on `stats.fractalserver.com` must be kept synchronized with the centralized analytics configuration in code and `docs/operations/matomo-analytics-guide.md`.
- Private **Reservation UID** lookup tokens and guest PII are never transmitted to Matomo via URLs, referrers, or event payloads.
