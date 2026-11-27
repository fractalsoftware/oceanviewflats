# OceanViewFlats — Matomo Analytics Operational Runbook

Operational reference for configuring, verifying, reporting on, and maintaining self-hosted Matomo analytics (`stats.fractalserver.com`, Site ID `2`) for `www.oceanviewflats.com` in alignment with [ADR 0015](../adr/0015-privacy-first-site-visitor-analytics.md), [ADR 0010](../adr/0010-multilingual-page-hydration-seam.md), [ADR 0012](../adr/0012-personal-data-protection-and-ley-1581-compliance.md), and [ADR 0014](../adr/0014-opaque-outbound-feed-uids-and-token-gated-fulfillment.md).

---

## 1. Instance Overview & Architecture ([ADR 0015](../adr/0015-privacy-first-site-visitor-analytics.md))

OceanViewFlats runs a **cookieless, PII-free, 100% client-side** Matomo architecture using core free Matomo features (no paid Funnels/Heatmaps plugins, no Matomo Ecommerce module, no MySQL analytics tables, and no server-side Matomo `token_auth` secrets inside PHP webhooks).

| Parameter | Value |
| :--- | :--- |
| **Matomo Instance** | `https://stats.fractalserver.com/` |
| **Site ID** | `2` |
| **Tracked Public Host** | `https://www.oceanviewflats.com` (all 6 locales: `en`, `es`, `fr`, `it`, `de`, `ja`) |
| **Site Currency** | `COP` — **Settlement Currency** (Colombian Peso) |
| **Site Timezone** | `America/Bogota` (COT, UTC-5) |
| **Client Module** | `public/js/analytics.js` (loaded immediately after `public/js/page-config.js` and before `public/js/main.js`) |
| **Build Metadata Seam** | `<script id="ovf-page-config" type="application/json">` (`pageType`, `propertyId`, `guideSlug`, `enableEngagementTracking`) |
| **CSP Allowance** | `public/.htaccess` permits `https://stats.fractalserver.com` in `script-src`, `img-src`, and `connect-src` |
| **Data Processing Policy** | Version `2026-11` (discloses self-hosted, cookieless Matomo analytics with IP anonymization across all 6 locales) |

### Synchronization Invariant

Matomo Goal IDs (`1`–`7`) and Custom Dimension slot IDs (`1`–`5`) on Site ID `2` are referenced directly by numeric ID in `public/js/analytics.js`. Any addition, deletion, or renumbering on `stats.fractalserver.com` must be synchronized with `public/js/analytics.js` and this runbook.

---

## 2. Dashboard Setup Checklist: Goals 1–7

Before configuring Goals, confirm that Site ID `2` uses **Settlement Currency (COP)**:

1. Open **Administration (gear icon) -> Websites -> Manage**, click **Edit** on Site ID `2` (`www.oceanviewflats.com`).
2. Set **Currency** to **Colombian Peso (COP)** and **Time zone** to **Colombia - Bogota**, then click **Save**.
3. Navigate to **Goals -> Manage Goals** (or **Administration -> Websites -> Goals**) with Site ID `2` selected.

### Canonical 7-Goal Configuration Matrix

All seven Goals use **Goal is triggered: Manually** (`_paq.push(['trackGoal', id, revenue])`).

| Goal ID | Setup Action | Exact Goal Name | Trigger | Allow Multiple Conversions per Visit | Revenue Setting | Trigger Surface & Semantics |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `1` | Verify | `General Contact Inquiry Submitted` | Manually | No | `0` COP | Contact form **Inquiry** submitted without stay dates. |
| `2` | Verify | `Date-Specific Contact Inquiry Submitted` | Manually | Yes | `0` COP | Contact form **Inquiry** submitted with desired stay dates (paired with bucketed **Demand Signal**). |
| `3` | Verify | `Airbnb Redirect Click` | Manually | Yes | `0` COP | **Property** Airbnb CTA (`#btn-book`) outbound click only. Review-verification links emit a separate non-Goal trust event. |
| `4` | Verify | `WhatsApp Contact Click` | Manually | Yes | `0` COP | All public WhatsApp CTAs across Homepage (host card, bottom CTA), **Property** pages (booking panel, host card), and Contact sidebar. |
| `5` | **Rename** | `Direct Reservation Confirmed` | Manually | Yes | Dynamic COP (default `0`) | Fired **exclusively** on **Instant Settlement** (`status === "approved"`), passing the authoritative **Quote** total in **Settlement Currency (COP)** as Goal revenue. |
| `6` | **Create** | `Pending Reservation Created` | Manually | Yes | `0` COP | Fired when `book-request.php` successfully creates a **Pending Reservation** calendar hold before payment submission. |
| `7` | **Create** | `Guest Registry Completed` | Manually | Yes | `0` COP | Fired on successful **Guest Registry** submission (`/registry/`), permanently decoupled from Goal `5`. |

### Step-by-Step Goal Setup Checklist

- [ ] **Verify Goals `1`–`4`**: Confirm IDs `1`, `2`, `3`, and `4` exist, are set to **Manually** triggered, and match the **Allow multiple conversions per visit** settings above.
- [ ] **Rename Goal `5`**: Edit existing Goal `5`, update its name to `Direct Reservation Confirmed`, confirm trigger is **Manually**, set **Allow Goal to be converted more than once per visit** to **Yes** (supports multi-Property family stays in one session), leave default revenue at `0` (overridden dynamically by the COP **Quote** total), and save.
- [ ] **Create Goal `6`**: Click **Add a new Goal**, name it `Pending Reservation Created`, set trigger to **Manually**, set **Allow Goal to be converted more than once per visit** to **Yes**, leave default revenue at `0`, and save. Confirm the assigned numeric ID in the **Manage Goals** table is `6`.
- [ ] **Create Goal `7`**: Click **Add a new Goal**, name it `Guest Registry Completed`, set trigger to **Manually**, set **Allow Goal to be converted more than once per visit** to **Yes**, leave default revenue at `0`, and save. Confirm the assigned numeric ID in the **Manage Goals** table is `7`.

> **Goal ID Integrity Check**: Matomo auto-increments Goal IDs per site and does not reuse deleted IDs. If creating the new Goals produces IDs other than `6` and `7`, do not assume they match—update the Goal ID constants in `public/js/analytics.js` immediately or recreate cleanly before deploying.

### 2.1 Asynchronous Settlement (PSE & Cash Voucher) vs. Admin Console Financial Report

- **Why Goal `5` excludes PSE and Cash Vouchers**: **Asynchronous Settlement** submissions (`status !== "approved"`)—such as PSE bank redirects and Efecty **Cash Vouchers**—remain **Pending Reservations** until an out-of-band Mercado Pago webhook confirms payment. Firing Goal `5` on checkout submission would falsely count abandoned bank redirects and unredeemed **Cash Vouchers** as **Confirmed Reservations**.
- **How Asynchronous Checkouts Are Tracked**: When a **Primary Guest** submits PSE or a **Cash Voucher**, `Funnel Stage` advances to `payment_submitted` and Matomo records a method-specific pending event (`PSE Redirect Initiated` or `Cash Voucher Issued`) **without** firing Goal `5`.
- **Authoritative Revenue Ledger**: Matomo Goal `5` measures client-side **Instant Settlement** conversion volume and **Quote** value for marketing and funnel optimization. Final realized **Gross Revenue** across **Instant Settlement**, settled **Asynchronous Settlement**, **Manual Reservations**, **External Reservations**, and **Gateway Refunds** is always authoritative in the **Admin Console** **Financial Report** (`/financial-report`).

---

## 3. Dashboard Setup Checklist: Custom Dimensions 1–5

Core free Matomo provides 5 Action-scoped and 5 Visit-scoped Custom Dimension slots by default. Because Matomo assigns `idcustomdimension` sequentially (`1, 2, 3, 4, 5`) across the site in creation order, **create Action Dimensions `1`–`3` first, then Visit Dimensions `4`–`5`** so every numeric slot ID matches `public/js/analytics.js`.

Navigate to **Administration (gear icon) -> Websites -> Custom Dimensions** with Site ID `2` selected.

### Canonical 5-Dimension Configuration Matrix

| Slot ID | Scope | Exact Name | Active | Allowed Values | Population Source & Semantics |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `1` | **Action** | `Locale` | Yes | `en`, `es`, `fr`, `it`, `de`, `ja` | Set from `#ovf-page-config` before `trackPageView` on every page. |
| `2` | **Action** | `Page Type` | Yes | `home`, `property`, `destination_guide`, `destination_hub`, `contact`, `privacy`, `registry`, `guide`, `booking_result`, `404` | Build-time classification emitted for every static page across all 6 locales. |
| `3` | **Action** | `Property` | Yes | `1707`, `1606`, `portfolio` | `1707` or `1606` on **Property** pages; `portfolio` on Homepage, **Destination Guides**, Contact, Privacy, and non-Property pages. |
| `4` | **Visit** | `Language Acquisition` | Yes | `direct_locale`, `auto_redirected`, `manual_switched` | Starts as `direct_locale`; set to `auto_redirected` when consuming the one-time `sessionStorage` handoff from `public/js/lang-detect.js`; updated to `manual_switched` on manual language-switcher clicks. |
| `5` | **Visit** | `Funnel Stage` | Yes | `discovery`, `property_viewed`, `quote_generated`, `hold_created`, `payment_brick_ready`, `payment_submitted`, `confirmed` | Strictly monotonic within a session (`discovery` < `property_viewed` < `quote_generated` < `hold_created` < `payment_brick_ready` < `payment_submitted` < `confirmed`); backwards UI resets never downgrade the furthest stage reached. |

### Step-by-Step Custom Dimension Checklist

For each row in the table above (in exact order `1` through `5`):

- [ ] Click **Configure a new dimension** under the corresponding section (**Action Dimensions** for `1`–`3`, then **Visit Dimensions** for `4`–`5`).
- [ ] Enter the **Exact Name** (`Locale`, `Page Type`, `Property`, `Language Acquisition`, `Funnel Stage`).
- [ ] Check **Active**.
- [ ] Leave **Extractions** (URL/Title regex rules) completely empty—values are pushed explicitly by `public/js/analytics.js` via `setCustomDimension`.
- [ ] Click **Create** and verify the resulting **ID** column reads `1`, `2`, `3`, `4`, and `5` in exact alignment with the table above.

---

## 4. Privacy & Cookieless Verification Checklist ([ADR 0012](../adr/0012-personal-data-protection-and-ley-1581-compliance.md), [ADR 0014](../adr/0014-opaque-outbound-feed-uids-and-token-gated-fulfillment.md))

OceanViewFlats runs Matomo without tracking cookies to collect behavioral insights cleanly under Colombian Statutory Law 1581 of 2012 without cookie-banner friction across six locales. Verify both server-side Matomo privacy settings and live browser payloads.

### 4.1 Self-Hosted Matomo Privacy Settings

Open **Administration (gear icon) -> Privacy** on `stats.fractalserver.com`:

- [ ] **IP Anonymization (`Privacy -> Anonymize data`)**:
  - Enable **Anonymize Visitors' IP addresses**.
  - Set **Select how many bytes of the visitors' IPs should be masked** to **2 bytes** minimum (e.g., `192.168.xxx.xxx`; **3 bytes** is acceptable for stricter masking).
  - Check **Also use the Anonymized IP addresses when enriching visits** (masks IPs prior to visit storage and geolocation lookup so raw IP addresses are never stored).
- [ ] **Cookieless Client Compatibility (`Privacy -> Anonymize data`)**:
  - Client scripts queue `_paq.push(["disableCookies"])` prior to `trackPageView`.
  - Confirm no Matomo setting forces cookie creation (optionally enable **Force tracking without cookies** at the site/instance level for defense-in-depth).
  - *Expected trade-off ([ADR 0015](../adr/0015-privacy-first-site-visitor-analytics.md))*: Multi-day returning-visitor stitching is approximate under cookieless tracking because Matomo correlates visits using its daily rotating configuration hash rather than persistent `_pk_id` cookies.
- [ ] **DoNotTrack Support (`Privacy -> Users opt-out`)**:
  - Enable **Support Do Not Track preference** so Matomo respects browser DNT headers automatically.
- [ ] **User ID & Ecommerce Tracking**:
  - Confirm Matomo **User ID** tracking is unused and **Ecommerce** tracking remains disabled for Site ID `2`.
- [ ] **Referrer Handling**:
  - Client-side `setReferrerUrl` enforces the strict query-parameter allowlist (`utm_*`, `mtm_*` only) before every pageview so sensitive parameters on `/booking-success/`, `/registry/`, or `/guide/` never reach Matomo. Keep Matomo's **Anonymize Referrer** at *"Do not anonymize"* if full landing-page campaign attribution is needed, or set to *"Remove query parameters from referrer URL"* for stricter defense-in-depth.
- [ ] **Raw Log Data Retention (`Privacy -> Anonymize data -> Regularly delete old raw data`)**:
  - Enable **Delete old raw data** with a retention window of **90–180 days** (aligned with the 90-day post-checkout **PII Redaction** cadence in `scripts/redact-expired-pii.php`).
  - Keep **Delete old aggregated report data** **disabled** so multi-year seasonal **Demand Signal** and **Booking Funnel** trend reports remain intact.

### 4.2 Post-Deployment Verification & Zero-PII Audit

After deploying changes to `www.oceanviewflats.com`, run this live verification pass in a private browser window (with ad-blockers paused and DoNotTrack off for testing):

- [ ] **Cookieless Check**: Open browser DevTools -> **Application -> Cookies** on `https://www.oceanviewflats.com/`. Confirm zero `_pk_id.*` or `_pk_ses.*` cookies exist.
- [ ] **Network & CSP Check**: Open DevTools -> **Network** (filter `matomo.php`). Confirm `HTTP 200` / `204` tracking beacons to `https://stats.fractalserver.com/matomo.php` with `idsite=2`, `dimension1`–`dimension5` populated, and zero CSP violations in the console.
- [ ] **URL & Referrer Sanitization Audit**: Visit a test URL with sensitive query parameters (e.g., `/registry/?code=ovf_test123&guest=Test&check_in=2026-12-01&utm_source=email`) and navigate to `/guide/?code=ovf_test123&token=secret`. Open Matomo **Visitors -> Visits Log** and **Behaviour -> Pages**:
  - Confirm `utm_source=email` is preserved where present.
  - Confirm **zero** occurrences of `code=`, `token=`, `guest=`, `name=`, `id=`, `barcode=`, `verif_code=`, `voucher_url=`, `check_in=`, `check_out=`, or any `ovf_*` / `res-abnb-*` / `res-man-*` **Reservation UID** in either Page URLs or Referrer URLs.
- [ ] **Zero-PII Event Audit**: Inspect **Behaviour -> Events** after triggering validation errors, **Quotes**, **Guest Registry** submission, and **Guest Guide** credential copy clicks. Confirm **zero** localized UI error messages, email addresses, phone numbers, guest names, exact stay date pairs, or **Access Credentials** (`0XXXXXX#`) appear in any event label.
- [ ] **Policy Disclosure Check**: Confirm `/privacy/` (and localized variants `es.html`, `fr.html`, `it.html`, `de.html`, `ja.html`) displays the cookieless Matomo analytics disclosure and policy version `2026-11`.

---

## 5. Practical Reporting Guide (Core Free Matomo)

All key operational questions are answerable directly in core free Matomo without paid plugins.

### 5.1 Inspecting the Booking Funnel via Visit-Scoped `Funnel Stage`

1. Open **Visitors -> Custom Dimensions -> Funnel Stage** (Dimension `5`).
2. Because `Funnel Stage` is **Visit-scoped** and advances **monotonically**, Matomo assigns each Visit to the **furthest stage** reached in that session.
3. To compute true **cumulative stage reach** and **step-to-step conversion**, sum each stage with all downstream stages below it:

| Stage Order | `Funnel Stage` Value | Meaning | Cumulative Reach Formula | Primary Drop-Off Diagnosis |
| :--- | :--- | :--- | :--- | :--- |
| 1 | `discovery` | Viewed Homepage, **Destination Guides**, Contact, or Privacy without opening a **Property**. | All visits (`1` through `7`) | Weak Homepage-to-**Property** routing or informational **Destination Guide** read. |
| 2 | `property_viewed` | Opened **Property** `1707` or `1606` without generating a valid **Quote**. | Sum of stages `2`–`7` | Calendar unavailability (`Unavailable Date Clicked`), `min_stay_blocked`, or photo/FAQ hesitation. |
| 3 | `quote_generated` | Selected valid dates and received a calculated **Quote**. | Sum of stages `3`–`7` | Price sensitivity, exit to Airbnb (Goal `3`) or WhatsApp (Goal `4`), or form/consent friction. |
| 4 | `hold_created` | Secured a **Pending Reservation** hold (Goal `6`). | Sum of stages `4`–`7` | Mercado Pago SDK load error or Payment Brick initialization failure. |
| 5 | `payment_brick_ready` | Mercado Pago Payment Brick mounted and ready for payment input. | Sum of stages `5`–`7` | **Primary Guest** abandoned before submitting card/PSE/**Cash Voucher** details. |
| 6 | `payment_submitted` | Submitted payment via Credit/Debit Card, PSE, or **Cash Voucher**. | Sum of stages `6`–`7` | Card decline (`payment_declined`) or **Asynchronous Settlement** (`PSE Redirect Initiated` / `Cash Voucher Issued`) awaiting webhook confirmation. |
| 7 | `confirmed` | **Instant Settlement** approved (Goal `5` with COP revenue). | Stage `7` count | Completed instant **Direct Reservation**. |

**Segmentation Tip**: Create reusable Matomo **Segments** (top bar -> **All Visits -> Add new segment**) to slice the **Booking Funnel** by:
- **Market / Language**: `Locale is es` vs. `Locale is en` (Dimension `1`)
- **Property**: `Property is 1707` vs. `Property is 1606` (Dimension `3`)
- **Locale Acquisition**: `Language Acquisition is auto_redirected` vs. `direct_locale` (Dimension `4`)

### 5.2 Analyzing Bucketed Demand Signals

Open **Behaviour -> Events** and pivot by **Event Category**, **Event Action**, and **Event Name** (enable the **Event Value** and **Avg. Event Value** columns).

#### Canonical Demand Signal Buckets

| Signal Dimension | Allowed Buckets / Values | Analytical Use |
| :--- | :--- | :--- |
| **Check-in Month** | `YYYY-MM` (e.g., `2026-12`, `2027-01`) | Identifies seasonal demand peaks without logging exact stay dates. |
| **Lead-Time Bucket** | `0-7d`, `8-30d`, `31-90d`, `90d+` | Reveals booking-window advance planning per locale and season. |
| **Stay-Length Bucket** | `1n`, `2-3n`, `4-6n`, `7-13n`, `14n+` | Distinguishes weekend trips (`2-3n`) from extended remote-work stays (`14n+`). |
| **Quote Source** | `authoritative` \| `fallback` | `authoritative` uses live `/api/quote.php` **Seasonal Rate Tiers**; spikes in `fallback` indicate API errors or timeouts. |
| **Event Value (COP)** | Rounded numeric **Quote** total in COP | Measures average quoted basket size (**Avg. Event Value**) and total quoted demand (**Total Event Value**) per **Property** or month. |

#### Key Demand Reports to Review Weekly

- **Quoted Demand & Basket Value**: Filter **Behaviour -> Events** for valid **Quote** events. Sort by **Total Events** and **Avg. Event Value (COP)** across `YYYY-MM`, lead-time, and stay-length buckets to evaluate pricing resonance per **Property**.
- **Minimum-Stay Friction (`min_stay_blocked`)**: Filter for `min_stay_blocked` events (tagged with **Property**, `YYYY-MM`, stay-length bucket, and required minimum **Nights**). Repeated rejections in a low-occupancy month signal an overly restrictive **Seasonal Rate Tier** minimum stay in the **Admin Console**.
- **Unmet Availability (`Unavailable Date Clicked`)**: Clicking booked calendar dates (`aria-disabled="true"`) emits `Unavailable Date Clicked` with **Property** and `YYYY-MM`. Compare alongside calendar month-navigation events to identify sold-out months where **Nightly Rates** can be raised or cross-selling between `1707` and `1606` should be prioritized.
- **Date-Specific Inquiries**: Review Goal `2` alongside normalized Contact **Inquiry** bucketed **Demand Signals** (`YYYY-MM`, lead-time bucket, stay-length bucket) to align pre-booking questions with calendar demand.

### 5.3 Automatic Locale Redirection & Multilingual Performance

- Open **Visitors -> Custom Dimensions -> Language Acquisition** (Dimension `4`) to compare `direct_locale`, `auto_redirected`, and `manual_switched` sessions.
- Inspect **Behaviour -> Events** for `Language / Auto Redirect` and `Language / Manual Switch` (`<from>-><to>`, e.g., `en->es`).
- High `manual_switched` volume from a specific landing locale indicates external links (such as Google Business Profile or Instagram) should point directly to the audience's localized URL (e.g., `/es.html`).
- Note that search-engine crawlers and headless bots are excluded from `lang-detect.js` redirection and do not inflate `auto_redirected` counts.

### 5.4 Discovery Engagement Depth & Content Attribution

- **Reading Time & Scroll Depth**: On public pages (`enableEngagementTracking: true`), `enableHeartBeatTimer(15)` keeps single-page visits accurate in **Behaviour -> Pages** (**Avg. Time on Page**), while one-shot scroll milestones (`25`, `50`, `75`, `90`) appear in **Behaviour -> Events**.
- **Visual & Pre-Booking Friction**: Review **Property** photo gallery lightbox opens / unique photo views, FAQ accordion opens (keyed by stable FAQ ID such as `faq-1707-3`), Homepage discovery CTAs, and **Destination Guide** -> **Property** CTAs (`DestinationGuide / PropertyCTA`), segmented by `Page Type` (Dimension `2`) and `Property` (Dimension `3`).

### 5.5 404 Broken Links Report

1. Open **Behaviour -> Page Titles**.
2. Filter or search for `404/URL =`.
3. Every 404 landing reports Matomo's standard format `404/URL = <sanitized-path>/From = <sanitized-referrer>`, surfacing broken internal links, outdated external directory links, or malformed campaign URLs without leaking query-string tokens.

### 5.6 Post-Booking Fulfillment Friction (Guest Registry & Guest Guide)

- **Guest Registry (`/registry/`, `Page Type = registry`)**:
  - Track overall completion volume in **Goals -> Goal 7 (`Guest Registry Completed`)** and registered guest counts via the numeric **Event Value** on submission success.
  - Monitor **Behaviour -> Events** for lookup failures (`not_found`, `concluded`, `already_completed`), client-side consent/validation blocks (`privacy_consent`, `minor_guardian_consent`, `email_or_phone`), and server submission failures (`error_code`).
- **Guest Guide (`/guide/`, `Page Type = guide`)**:
  - Inspect `Access State` outcomes (`unlocked`, `registry_required`, `concluded`, `not_found`), `Credential Copied` events (`door_code`, `wifi_ssid`, `wifi_password`, `parking_spot` — credential type only, never secret values), and `Unlock Registry Click`.
  - *Expected metric*: Single-page visits on `/registry/` and `/guide/` show `0s` time-on-page because `enableEngagementTracking` is intentionally `false` (no heartbeat timer or scroll listeners on private fulfillment pages).

---

## 6. Canonical UTM Tagging Reference

### 6.1 Strict Query-Parameter Allowlist

`public/js/analytics.js` sanitizes both `setCustomUrl` and `setReferrerUrl` before every pageview:

- **Preserved parameters (allowlisted)**: `utm_source`, `utm_medium`, `utm_campaign`, `utm_content`, `utm_term`, and `mtm_*` (Matomo-native campaign parameters such as `mtm_source`, `mtm_medium`, `mtm_campaign`, `mtm_content`, `mtm_keyword`).
- **Stripped parameters (dropped automatically)**: All other query parameters—including `code`, `token`, `guest`, `name`, `id`, `barcode`, `verif_code`, `voucher_url`, `check_in`, `check_out`, `fbclid`, `gclid`, and arbitrary custom query keys—plus URL `#fragments`.

### 6.2 Parameter Taxonomy & Formatting Rules

| Parameter | Matomo Equivalent | Required | Formatting Rule & Purpose |
| :--- | :--- | :--- | :--- |
| `utm_source` | `mtm_source` | Yes | Originating platform (`google_business_profile`, `instagram`, `whatsapp`, `email`). |
| `utm_medium` | `mtm_medium` | Yes | Marketing channel type (`organic_local`, `social`, `messaging`, `email`). |
| `utm_campaign` | `mtm_campaign` | Yes | Initiative or seasonal campaign (`profile_website`, `direct_reservation_bio`, `high_season_2026`, `property_1707_spotlight`). |
| `utm_content` | `mtm_content` | Recommended | Specific placement, creative, or **Property** target (`website_btn`, `bio_link`, `reel_1707`, `chat_reply_1606`, `host_signature`). |
| `utm_term` | `mtm_keyword` | Optional | Audience language or keyword tag (`es_colombia`, `en_remote_work`) when segmenting paid/targeted placements. |

**Tagging Standards**:
1. Use **lowercase ASCII `snake_case`** only (no spaces, uppercase letters, or accented characters) so Matomo campaign reports never split across casing variants.
2. Never place guest names, emails, phone numbers, stay dates, or **Reservation UIDs** inside any UTM/MTM parameter.
3. Always link to the canonical `https://www.oceanviewflats.com/...` host (with `www.`) to avoid unnecessary apex-to-`www` 301 redirects.
4. For Spanish-speaking channels, link directly to localized Spanish URLs (e.g., `https://www.oceanviewflats.com/es.html` or `https://www.oceanviewflats.com/Oceanview1707/es.html`) so **Site Visitors** land directly with `Language Acquisition = direct_locale`.

### 6.3 Channel Matrix & Ready-to-Copy Link Examples

| Channel | `utm_source` | `utm_medium` | `utm_campaign` Examples | `utm_content` Examples |
| :--- | :--- | :--- | :--- | :--- |
| **Google Business Profile** | `google_business_profile` | `organic_local` | `profile_website`, `gbp_post_2026_12` | `website_btn`, `reserve_btn_1707`, `reserve_btn_1606` |
| **Instagram** | `instagram` | `social` | `always_on_bio`, `reel_playa_salguero`, `story_availability` | `bio_link`, `story_cta_1707`, `story_cta_1606`, `dm_reply` |
| **WhatsApp** | `whatsapp` | `messaging` | `business_profile`, `direct_inquiry_reply`, `seasonal_broadcast` | `profile_link`, `reply_1707`, `reply_1606`, `guide_direct_reservation` |
| **Email** | `email` | `email` | `host_signature`, `pre_stay_guides`, `repeat_guest_2026_12` | `footer_home`, `guide_day_trips`, `cta_1707`, `cta_1606` |

#### Ready-to-Copy Examples

```text
# Google Business Profile — Main Website Button (Spanish landing)
https://www.oceanviewflats.com/es.html?utm_source=google_business_profile&utm_medium=organic_local&utm_campaign=profile_website&utm_content=website_btn

# Google Business Profile — Property 1707 Direct Reservation Link (English)
https://www.oceanviewflats.com/Oceanview1707/?utm_source=google_business_profile&utm_medium=organic_local&utm_campaign=profile_website&utm_content=reserve_btn_1707

# Instagram — Bio Link (Portfolio)
https://www.oceanviewflats.com/?utm_source=instagram&utm_medium=social&utm_campaign=always_on_bio&utm_content=bio_link

# Instagram — Story CTA to Property 1606 (Spanish)
https://www.oceanviewflats.com/Oceanview1606/es.html?utm_source=instagram&utm_medium=social&utm_campaign=story_availability&utm_content=story_cta_1606

# WhatsApp — Business Profile Website Link
https://www.oceanviewflats.com/es.html?utm_source=whatsapp&utm_medium=messaging&utm_campaign=business_profile&utm_content=profile_link

# WhatsApp — Direct Chat Reply Sharing Property 1707
https://www.oceanviewflats.com/Oceanview1707/es.html?utm_source=whatsapp&utm_medium=messaging&utm_campaign=direct_inquiry_reply&utm_content=reply_1707

# Email — Pre-Stay Destination Guide Recommendation (English)
https://www.oceanviewflats.com/santa-marta/day-trips-from-playa-salguero/?utm_source=email&utm_medium=email&utm_campaign=pre_stay_guides&utm_content=guide_day_trips

# Email — Repeat-Guest Seasonal Outreach to Property 1606 (Spanish)
https://www.oceanviewflats.com/Oceanview1606/es.html?utm_source=email&utm_medium=email&utm_campaign=repeat_guest_2026_12&utm_content=cta_1606
```

> **Private Fulfillment Links Note**: Keep transactional **Guest Registry** (`/registry/?code=...`) and **Guest Guide** (`/guide/?code=...`) links focused on operational fulfillment. Even if `utm_*` parameters are appended, `public/js/analytics.js` will always strip `code`, `token`, `guest`, and date parameters before calling Matomo.

---

## 7. Troubleshooting Quick Reference

| Symptom | Cause | Resolution |
| :--- | :--- | :--- |
| Goal `5` shows `$0` revenue or wrong currency symbol | Site ID `2` currency is not set to `COP`, or the checkout used **Asynchronous Settlement** (PSE/**Cash Voucher**). | Set Site ID `2` currency to **Colombian Peso (COP)** under **Administration -> Websites -> Manage -> Edit**. Remember Goal `5` fires only on **Instant Settlement** (`status === "approved"`). |
| Goal `5` conversion counts appear inflated by **Guest Registry** completions | Stale browser cache serving pre-migration `dist/js/registry.js`, or Matomo Goal `7` ID was not created at slot `7`. | Confirm the latest build is deployed with cache-busted `js/registry.js?v=...` and verify **Guest Registry Completed** is assigned to Goal ID `7` in **Goals -> Manage Goals**. |
| Settled PSE or Efecty **Cash Voucher** reservation does not appear in Matomo Goal `5` | Expected behavior under [ADR 0015](../adr/0015-privacy-first-site-visitor-analytics.md); **Asynchronous Settlement** never fires client-side Goal `5`. | Verify `PSE Redirect Initiated` or `Cash Voucher Issued` in **Behaviour -> Events**, and use the **Admin Console Financial Report** (`/financial-report`) as the authoritative financial ledger. |
| Custom Dimensions `1`–`5` show *"Value not defined"* or mismatched labels | Slot IDs in Matomo do not match `1`–`5` (`Locale`, `Page Type`, `Property`, `Language Acquisition`, `Funnel Stage`), or a dimension is marked Inactive. | Open **Administration -> Websites -> Custom Dimensions**, verify exact numeric IDs `1`–`5`, scope (Action `1`–`3`, Visit `4`–`5`), and **Active** status. |
| Test visits do not appear in **Visitors -> Visits Log** | Browser ad-blocker or DoNotTrack header is active, or `stats.fractalserver.com` is unreachable. | Retest in a private window with ad-blocking disabled and DoNotTrack off; inspect DevTools **Network** for `matomo.php` requests and console CSP warnings. |
| Custom query parameters disappear from Matomo Page URLs | Expected behavior of the strict allowlist in `public/js/analytics.js`, which drops all non-`utm_*` / non-`mtm_*` parameters and `#fragments`. | Rename campaign tags to canonical `utm_source`, `utm_medium`, `utm_campaign`, `utm_content`, `utm_term` (or `mtm_*`) parameters. |
| `/registry/` and `/guide/` visits report `0s` average time on page | Expected behavior: `enableEngagementTracking` is `false` on private fulfillment and `booking_result` pages (no heartbeat timer or scroll milestones). | No action required; monitor fulfillment health via Goal `7` and **Behaviour -> Events** friction codes instead. |
| Spikes in `fallback` quote source inside **Demand Signals** | `/api/quote.php` returned errors or timed out, causing the calendar widget to fall back to client-side baseline pricing. | Inspect cPanel PHP error logs, verify MySQL connectivity (`property_rates`), and test `GET /api/quote.php` directly. |
| High `404/URL =` volume in **Behaviour -> Page Titles** | Broken external directory link, mistyped campaign URL, or missing localized route. | Read the `From = <sanitized-referrer>` segment in the 404 page title to identify the referring source and fix the inbound link or add a redirect rule. |
