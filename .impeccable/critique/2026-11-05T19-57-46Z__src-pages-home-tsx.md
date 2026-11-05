---
target: the homepage
total_score: 21
max_score: 40
na_heuristics: 
p0_count: 1
p1_count: 3
target_identity: "file:/Users/manuel.herrera/Projects/17071606/src/pages/Home.tsx"
target_fingerprint: "sha256:a493b914fd3ec68c8d956fd24e9297377e6feb0855efdff79f82bfc7556e6fc5"
target_path: /Users/manuel.herrera/Projects/17071606/src/pages/Home.tsx
timestamp: 2026-11-05T19-57-46Z
slug: src-pages-home-tsx
---
Method: dual-agent (A: ses_e52bda6ceffeGAoX0nTiyX5INh · B: ses_e52bd8fd4ffeCnpKzkhrs0Pczz)

#### Design Health Score

| # | Heuristic | Score | Key Issue |
|---|-----------|-------|-----------|
| 1 | Visibility of System Status | 2 | Scrolled navbar and current language pill are visible, but property cards show no live availability or starting `COP` rate cue, and `#lang-toggle` lacks `aria-expanded`. |
| 2 | Match System / Real World | 2 | 6-locale parity exists, but `ja` translates location as `ガイラ、マグダレナ` (*Gaira*) on cards/footer (`dict.ts:2881, 2887`) vs. `サンタマルタ` (*Santa Marta*) in hero/FAQ, and the page omits floor levels (`17th` vs `16th`), `Edificio Salguero Sunset`, and `COP` pricing. |
| 3 | User Control and Freedom | 2 | Language dropdown requires a JS click (`public/js/main.js:88-99`), lacks `Escape` key dismissal, and provides no keyboard focus/open path. |
| 4 | Consistency and Standards | 2 | Hardcodes all styling inline in `src/pages/Home.tsx` instead of `src/constants/theme.ts` (`AGENTS.md` Rule 1), uses root-relative `/Oceanview1707` links ignoring `assetPrefix` (`Home.tsx:67, 98`, `Navigation.tsx:29`), and misuses semantic Amber/Emerald/Purple colors (`Home.tsx:158, 182`, `Footer.tsx:42, 95`). |
| 5 | Error Prevention | 2 | Property showcase cards (`Home.tsx:65-125`) omit starting `COP` rates, bathroom counts (`statsBaths`), floor numbers, and `RNT` badges, forcing blind clicks, while `#FF5A5F` small text on light fills fails WCAG AA (`2.8:1–2.9:1`). |
| 6 | Recognition Rather Than Recall | 2 | Comparing Property 1707 vs. 1606 creates a memory bridge: both cards show `Playa Salguero` and `6 Guests` while hiding `17th Floor` vs `16th Floor`, `2 Baths`, `Bay 95` vs `Bay 87`, and workspace vs. sunset terrace distinctions. |
| 7 | Flexibility and Efficiency | 2 | Top `Navigation` (`Navigation.tsx:27-66`) has no direct links to `1707` or `1606`, `showBookButton={false}` hides the navbar CTA on Home (`Home.tsx:34`), and the hero has no quick-jump CTA. |
| 8 | Aesthetic and Minimalist Design | 2 | `"Why Book Direct?"` (`t.directTitle`) is duplicated verbatim in both the kicker pill and `<h2>` (`Home.tsx:133-138`), `line-clamp-2` truncates localized descriptions (`Home.tsx:87, 118`), and 0% fee / 900 Mbps claims repeat across 4 sections. |
| 9 | Error Recovery | 3 | Static SSG page has minimal error surface and `main.js:61-70` preserves query strings across language switches, though root-relative property links (`Home.tsx:67, 98`) fail under non-root `assetPrefix` deployments. |
| 10 | Help and Documentation | 2 | The 5 permanently expanded FAQ cards (`Home.tsx:238-274`) misstate how to book direct (`faqA3` directs users to the Contact inquiry form instead of live checkout on property pages) and omit the mandatory `20,000 COP`/person Building Registration Fee and Law 1581 Guest Registry gate. |
| **Total** | | **21/40** | **Acceptable (52.5%) — Strong beachfront photography and dual-property temperature foundations held back by funnel misdirection, SaaS template clichés, and contrast/i18n gaps.** |

#### Design Specificity Verdict

**LLM assessment**: `src/pages/Home.tsx` is a tale of two halves. Its **Featured Properties** section (`Home.tsx:62-127`) is genuinely specific to OceanViewFlats—honoring **The Property Temperature Rule** (`DESIGN.md`) by pairing Property 1707 with Coral Horizon (`#FF5A5F`) + cool Slate (`bg-slate-50`, `border-slate-100`) and Property 1606 with Salguero Sunset Rose (`text-rose-600`) + warm Travertine Stone (`bg-stone-50`, `border-stone-100`) over real Playa Salguero photography. However, the surrounding sections revert to category-interchangeable SaaS templates:
1. **Generic 4-Card Pastel Icon Grid ("Why Book Direct", `Home.tsx:144-192`)**: Uses four identical cards with Emerald, Amber, Blue, and off-palette Purple icon boxes—violating **The Semantic Trust Rule** (`DESIGN.md`), which reserves Keycard Amber strictly for physical access credentials/warnings and Clearance Emerald for settlement/legal trust.
2. **Duplicated Kicker & Heading (`Home.tsx:133-138`)**: Renders `{t.directTitle}` (*"Why Book Direct?"*) twice in a row—once inside an uppercase pill and immediately again inside the `<h2>`.
3. **Sterile 3-Column Dark Icon Band ("Tailored Experiences", `Home.tsx:197-235`)**: Uses three centered Lucide icons (`Wifi`, `Users`, `Heart`) on `bg-slate-900` instead of showcasing OceanViewFlats' real balcony workspace and sunset photography or introducing host Manuel Enrique (`images/host-image.webp` and `t.hostBio` exist in `dict.ts` across all 6 locales but go completely unused on the homepage).
4. **Shallow, Actionless Hero (`Home.tsx:39-58`)**: Compressed `pt-28 pb-12` banner with generic welcome copy, no location pill, no direct-booking value proposition, and no primary CTA.

**Deterministic scan**:
- **Source scan** (`impeccable detect --json src/pages/Home.tsx src/components/Navigation.tsx src/components/LanguageDropdown.tsx src/components/Footer.tsx`): Exit code `0`, **2 advisory findings** in `src/components/Footer.tsx`:
  - `design-system-font-size` (`Footer.tsx:31`): `font-size: 11px` on `.fractal-badge` is off the `DESIGN.md` type ramp.
  - `design-system-color` (`Footer.tsx:33`): Undocumented `#a1a1aa` text color on `.fractal-badge`.
- **Compiled HTML scan** (`impeccable detect --json dist/index.html`): Exit code `2`, **44 findings** (`37 warning`, `7 advisory`). Where the detector and LLM review agree—and where the detector caught issues the LLM review alone did not quantify:
  - **Caught by both LLM & Detector — `kicker-above-heading` (`Home.tsx:133-138`)**: Flagged `kicker "Why Book Direct?" above h2 "Why Book Direct?"` (a banned pattern in `craft-floor.md` aggravated by literal string duplication).
  - **Quantified by Detector — `low-contrast` WCAG AA failures on Coral Horizon (`#FF5A5F`) & Footer RNT text (10 genuine warnings/advisories)**:
    - `Home.tsx:88`: `text-[#FF5A5F]` (`14px`) on `bg-slate-50` (`#f8fafc`) = **2.9:1** (needs 4.5:1).
    - `Home.tsx:133`: `text-[#FF5A5F]` (`14px`) on `bg-rose-50` (`#fff1f2`) = **2.8:1** (needs 4.5:1).
    - `Home.tsx:299`: `#ffffff` text (`18px` bold) on `bg-[#FF5A5F]` CTA pill = **3.1:1** (just below 18.67px bold large-text cutoff for 3:1, failing strict 4.5:1).
    - `Home.tsx:119` (advisory): `text-rose-600` (`rgb(236, 0, 63)`) on `bg-stone-50` (`#fafaf9`) = **4.3:1** (needs 4.5:1).
    - `LanguageDropdown.tsx:50`: 5 `:hover` states using `hover:text-[#FF5A5F]` on `hover:bg-slate-50` = **2.9:1** (needs 4.5:1).
    - `Footer.tsx:66`: `text-slate-500` (`#62748e`, `12px`) on `bg-slate-900` (`#0f172b`) for the statutory RNT license line = **3.7:1** (needs 4.5:1).
  - **Caught by Detector — `dark-glow` (`Footer.tsx:42, 95`, 2 warnings)**: Zero-offset neon emerald glow (`box-shadow: 0 0 8px #10b981` and `0 0 16px rgba(16, 185, 129, 0.2)`) on `.fractal-badge`, violating both `dark-glow` and `DESIGN.md`'s **Semantic Trust Rule**.
  - **Caught by Detector — `design-system-color` (5 advisories)**: Flagged Tailwind v4's `rgb(236, 0, 63)` (`text-rose-600` vs `#E11D48`), `rgba(43, 127, 255, 0.2)` (`bg-blue-500/20`), `rgba(254, 154, 0, 0.2)` (`bg-amber-500/20`), `rgba(255, 32, 86, 0.2)` (`bg-rose-500/20`), and `rgb(161, 161, 170)` (`#a1a1aa` in `Footer.tsx`).
  - **False Positives Filtered Out (25 of 44 compiled HTML findings)**:
    - `low-contrast` #1–#5 (`#ffffff` / `#e2e8f0` on `#f8fafc`): False positives because the Hero `<img>` and dark scrim (`from-slate-900/80 to-slate-900/40`) are in an `absolute inset-0` sibling `<div>` (`Home.tsx:40-49`), so static DOM parent traversal falls through to the page wrapper's `bg-slate-50`.
    - `cramped-padding` #17–#34 (18 warnings): False positives caused by static analysis against Tailwind v4 `@layer utilities` `calc(var(--spacing) * N)` rules and full-bleed image headers inside `rounded-3xl` cards (`Home.tsx:69, 100`); actual padding is `24px–96px` (`p-6`, `p-8`, `py-20 px-6`).
    - `design-system-font` #35 (`apple color emoji`): False positive from Tailwind v4's system emoji fallback list on `font-sans`.
    - `gradient-text` #43: False positive from an unused utility class in the shared stylesheet bundle (`dist/css/style.css`), not present in `Home.tsx`.

**Visual overlays**: No reliable user-visible overlay is available in this session; fallback signal: `no browser automation tool exposed in harness` (live server and script injection were skipped cleanly per protocol).

#### Overall Impression

The homepage has strong bones where it matters most—the dual-property showcase cards for **1707** and **1606** immediately establish coastal desire and honor the Coral/Slate vs. Rose/Travertine temperature split—but it actively undermines its own business model. Instead of driving visitors into the live `COP` quote calculator and self-service Mercado Pago checkout on `/Oceanview1707` and `/Oceanview1606`, both `faqA3` and the bottom CTA tell guests to *"send an inquiry via our Contact form"* to book direct. Fixing that funnel misdirection while replacing the generic SaaS icon grids with authentic host/property proof is the single highest-leverage opportunity on the site.

#### What's Working

1. **Dual-Property Temperature Differentiation (`src/pages/Home.tsx:65-125`)**: Side-by-side showcase cards for Property 1707 and Property 1606 faithfully execute **The Property Temperature Rule** (`DESIGN.md`), pairing 1707's ocean-work aesthetic with cool Slate (`bg-slate-50`, `border-slate-100`) and 1606's romantic/sunset aesthetic with warm Travertine Stone (`bg-stone-50`, `border-stone-100`).
2. **Zero-Hydration Performance & Image Priority Discipline (`src/pages/Home.tsx:41-47, 72-76, 103-107`)**: Above-the-fold hero imagery uses `fetchPriority="high"` and `loading="eager"`, property card imagery uses `loading="lazy"`, and `public/js/main.js` loads zero external SDKs on the homepage—keeping payloads fast for roaming mobile travelers.
3. **Statutory Attribution & Query-Preserving Locale Switching (`src/components/Footer.tsx:66-77`, `public/js/main.js:48-83`)**: The footer clearly presents both official tourism registrations (`RNT #268781` and `RNT #270413`) and the localized Law 1581 privacy policy link, while `main.js` preserves query parameters when guests switch among the 6 supported languages.

#### Priority Issues

1. **[P0] Direct-Booking Funnel Misdirection & Under-Specified Property Cards**
   - **What**:
     - `src/i18n/dict.ts:126-128` (`faqA3` across all 6 locales) and the bottom CTA (`src/pages/Home.tsx:287-302`) instruct guests to book direct by sending a message through the Contact inquiry form, contradicting `PRODUCT.md` and `CONTEXT.md` (where `Inquiry` never holds dates and real direct booking happens via the live calendar + Mercado Pago checkout on `/Oceanview1707` and `/Oceanview1606`).
     - `src/pages/Home.tsx:39-58` has a cramped hero (`pt-28 pb-12`) with no primary CTA, no location chip, and `showBookButton={false}` on `Navigation` (`Home.tsx:34`).
     - Property cards (`Home.tsx:65-125`) lack an explicit CTA affordance, omit bathroom counts (`t.statsBaths`), floor levels (`17th` vs `16th`), `RNT` numbers, and starting `COP` rates, and clip localized descriptions with `line-clamp-2` (`Home.tsx:87, 118`—violating **The Six-Locale Measure Rule** in `DESIGN.md`).
   - **Why it matters**: High-intent visitors either think online booking doesn't exist and abandon/submit manual inquiries, or must open both property pages in separate tabs just to learn basic specs (2 baths, floor level, workspace vs. sunset terrace).
   - **Fix**:
     - Expand the Hero (`Home.tsx:39-58`) with a Playa Salguero / Edificio Salguero Sunset location badge, clear direct-booking value proposition (`0% OTA Fees • Instant COP Quotes`), and a primary CTA scrolling to `#properties`.
     - Enrich both property cards (`Home.tsx:65-125`) with floor + RNT badges (`17th Floor • RNT #268781` / `16th Floor • RNT #270413`), full unclipped descriptions (remove `line-clamp-2`), complete specs (`2 Bedrooms • 4/3 Beds • 2 Baths • Up to 6 Guests`), and an explicit high-contrast CTA footer (`Explore & Book Direct →`).
     - Rewrite `faqA3` in `src/i18n/dict.ts` across all 6 locales to explain that guests can select dates, view an itemized `COP` quote, and pay online via Card, PSE, or Efecty directly on the Property 1707 or 1606 page—while positioning the bottom Contact CTA for pre-booking questions or multi-apartment family inquiries.
   - **Suggested command**: `$impeccable clarify`

2. **[P1] WCAG AA Contrast Failures on Coral Horizon (`#FF5A5F`) Text & Footer Statutory Copy**
   - **What**:
     - `impeccable detect` flagged **2.9:1** contrast on `text-[#FF5A5F]` (`14px`) over `bg-slate-50` (`src/pages/Home.tsx:88`) and `:hover` states in `src/components/LanguageDropdown.tsx:50`, **2.8:1** on `text-[#FF5A5F]` over `bg-rose-50` (`Home.tsx:133`), **3.1:1** on white text over `bg-[#FF5A5F]` (`Home.tsx:299`), **4.3:1** on `text-rose-600` over `bg-stone-50` (`Home.tsx:119`), and **3.7:1** on `text-slate-500` (`12px`) over `bg-slate-900` for the statutory RNT line (`src/components/Footer.tsx:66`).
   - **Why it matters**: Violates `PRODUCT.md`'s commitment to **WCAG 2.1 AA+ Compliance** and **Older-Traveler Readability**, making key property specs and legal RNT numbers difficult to read in bright coastal sunlight.
   - **Fix**:
     - Reserve `#FF5A5F` for large graphical accents, icons, or borders; for small (`12px–14px`) text on light `#F8FAFC`/`#FFF1F2` surfaces (`Home.tsx:88`, `LanguageDropdown.tsx:50`) and Property 1606 stats (`Home.tsx:119`), use a darkened accessible token (`#BE123C` / `text-rose-700` at **6.3:1** or `#B91C1C` at **6.4:1**), and darken solid primary CTA button fills or pair with deep slate where 4.5:1 body text is required.
     - Lighten `Footer.tsx:66` RNT text from `text-slate-500` (`3.7:1`) to `text-slate-300` or `text-slate-400` (`≥ 5.5:1` on `bg-slate-900`).
   - **Suggested command**: `$impeccable audit`

3. **[P1] Template Slop (`kicker-above-heading`, Duplicated Title, SaaS Icon Grids, & Semantic Color Violations)**
   - **What**:
     - `src/pages/Home.tsx:133-138` triggers `kicker-above-heading` and prints `{t.directTitle}` (*"Why Book Direct?"*) twice in a row.
     - `src/pages/Home.tsx:144-192` ("Why Book Direct") and `197-235` ("Tailored Experiences") use generic 4-column and 3-column SaaS icon grids that violate **The Semantic Trust Rule** (`DESIGN.md`) by using Keycard Amber (`bg-amber-50 text-amber-600`, `bg-amber-500/20 text-amber-400`) and off-palette Purple (`bg-purple-50 text-purple-600`) as decorative marketing fills, while leaving an awkward `mt-10` (`40px`) light gap above the dark section.
     - `src/components/Footer.tsx:18-55, 89-97` triggers `dark-glow`, `design-system-font-size`, and `design-system-color` via an inline `<style>` block with a `#10b981` neon glow dot and `11px` `#a1a1aa` text on `.fractal-badge`.
   - **Why it matters**: Dilutes `"The Sunlit Coastal Concierge"` identity into a generic SaaS landing page and pollutes the semantic meaning of Amber (door PINs / statutory warnings) and Emerald (COP settlement / Law 1581 trust).
   - **Fix**:
     - Remove the duplicate kicker pill above `<h2>{t.directTitle}</h2>` (`Home.tsx:133-135`).
     - Re-anchor "Why Book Direct" and "Tailored Experiences" around authentic OceanViewFlats proof—introducing host Manuel Enrique (`IMAGES.general.host`, `t.hostName`, `t.hostBio`), real workspace/sunset imagery (`IMAGES["1707"].nomad`, `IMAGES["1606"].romantic`), and strict `DESIGN.md` tokens (Coral Horizon, Salguero Sunset Rose, Clearance Emerald, Nomad Sky, and Deep Caribbean Slate).
     - Replace the neon `dark-glow` and off-ramp `11px` `#a1a1aa` styles in `Footer.tsx` with clean Slate tokens from `src/constants/theme.ts`.
   - **Suggested command**: `$impeccable bolder`

4. **[P1] Broken Relative `assetPrefix` Links, Uncentralized Theme Classes, & Japanese Location Mismatch**
   - **What**:
     - `src/pages/Home.tsx:66-68` and `97-99` hardcode root-relative `/Oceanview1707` and `/Oceanview1606` links instead of prepending `${assetPrefix}`, and `src/components/Navigation.tsx:29` hardcodes `href={lang === "en" ? "/" : \`/${lang}.html\`}` (violating `AGENTS.md` Rule 3).
     - All classes in `src/pages/Home.tsx` are hardcoded inline instead of centralized in `src/constants/theme.ts` (violating `AGENTS.md` Rule 1 and `DESIGN.md` Do #1).
     - In `src/i18n/dict.ts:2881, 2887`, Japanese (`ja`) translates the location as `ガイラ、マグダレナ` (*Gaira, Magdalena*) while `homeSubtitle` (`2925`) and `faqA1` (`2939`) use `サンタマルタ` (*Santa Marta*), and English `dict.ts:12` vs `57` mixes singular `"Oceanview Flat 1707"` with plural `"Oceanview Flats 1606"`.
   - **Why it matters**: Breaks static navigation whenever served from a non-root path, violates repository architecture invariants, and confuses Japanese-speaking guests about whether the properties are in Santa Marta or Gaira.
   - **Fix**:
     - Prepend `${assetPrefix}` to all property links in `Home.tsx` and the logo link in `Navigation.tsx` (using `getHomePagePath(lang, assetPrefix)` from `src/constants/config.ts`).
     - Move homepage theme classes into `src/constants/theme.ts`.
     - Standardize `ja` location strings in `src/i18n/dict.ts` to `コロンビア、サンタマルタ、プラヤ・サルグエロ` and align English property naming (`Oceanview Flat 1707` / `Oceanview Flat 1606`).
   - **Suggested command**: `$impeccable harden`

5. **[P2] Non-Accessible `LanguageDropdown` (`<div>` Trigger), Undersized Mobile Tap Targets, & Non-Collapsible FAQ Wall**
   - **What**:
     - `src/components/LanguageDropdown.tsx:28-40` uses a `<div>` instead of a `<button>` for `#lang-toggle`, lacking `tabIndex`, `role`, `aria-expanded`, `aria-haspopup`, and keyboard `Enter`/`Space`/`Escape` handlers in `public/js/main.js:88-107`.
     - Mobile tap targets on `#nav-contact` (`20px × 20px` icon in `Navigation.tsx:50-55`) and `#lang-toggle` (`~32px` height in `LanguageDropdown.tsx:30`) are smaller than `DESIGN.md`'s `40px × 40px` minimum.
     - The 5 FAQ cards (`Home.tsx:238-274`) render permanently expanded (`space-y-8`) and omit upfront disclosure of the mandatory `20,000 COP`/person Building Registration Fee and pre-arrival Guest Registry requirement (`PRODUCT.md` Principle 1).
   - **Why it matters**: Keyboard and screen-reader users cannot change languages, mobile travelers in transit mis-tap the navbar controls, and scrollers face a long wall of text that still omits key arrival rules.
   - **Fix**:
     - Convert `#lang-toggle` to a semantic `<button type="button" aria-haspopup="true" aria-expanded="false">` with `min-h-[40px]` tap sizing (matching `#nav-contact`) and keyboard `Escape` support in `public/js/main.js`.
     - Convert the FAQ stack into native `<details>/<summary>` accordions and include clear disclosure of the `20,000 COP` Edificio Salguero Sunset reception fee and digital Guest Registry check-in flow.
   - **Suggested command**: `$impeccable polish`

#### Persona Red Flags

**Jordan (First-Timer)**: Lands on a short hero banner with no CTA button and no "Book Now" button in the navbar (`showBookButton={false}`). Scrolls to two property cards that show no price, no bathroom count, and no visible "View / Book" button, then reads `faqA3` (*"use our secure Contact form to send an inquiry directly"*) and concludes online booking does not exist.

**Riley (Deliberate Stress Tester)**: Tabs through the top navbar and discovers `#lang-toggle` (`LanguageDropdown.tsx:32`) is an unfocusable `<div>`, while the 5 hidden language `<a href>` links inside the `opacity-0 invisible` menu still receive keyboard focus invisibly without opening the dropdown. Also spots `"Why Book Direct?"` duplicated twice in a row (`Home.tsx:133-138`) and root-relative `/Oceanview1707` hrefs that ignore `assetPrefix`.

**Casey (Distracted Mobile User)**: Riding in a taxi on a 375px phone, Casey tries to tap the `20px × 20px` mail icon in the navbar (`Navigation.tsx:53`), sees `line-clamp-2` cut off the property descriptions mid-sentence (`Home.tsx:87, 118`), and has to scroll past 14 stacked full-width blocks (2 properties + 4 direct-booking cards + 3 dark experience blocks + 5 expanded FAQ cards) to reach the bottom of the page.

**Elena & Hiroshi (International Remote Workers & Multi-Generational Family Planners in `de` / `ja`)**: Hiroshi views `ja.html` and sees `ガイラ、マグダレナ` (*Gaira*) on the property cards (`dict.ts:2887`) but `サンタマルタ` (*Santa Marta*) in the hero subtitle (`dict.ts:2925`), wondering if the listings are in different municipalities. Elena views `de.html` and has the 175-character German description of Property 1707's balcony workspace clipped by `line-clamp-2`. Neither can tell from the homepage that both apartments are in the same building (**Edificio Salguero Sunset**, 17th and 16th floors) with 2 bathrooms and deeded parking (`Bay 95` & `Bay 87`), making them ideal to rent together for an extended family trip.

#### Minor Observations

- **Unused Host & Building Assets on Homepage**: `IMAGES.general.host` (`images/host-image.webp`), `t.hostName`, `t.hostBio`, and `t.poolNote` are already localized across all 6 languages in `src/i18n/dict.ts`, yet never appear on `Home.tsx`.
- **Dead Client `useEffect` in Zero-Hydration Component (`src/pages/Home.tsx:20-27`)**: `const [isScrolled, setIsScrolled] = useState(false)` and its `window.addEventListener("scroll", ...)` never execute in production because `render.tsx` outputs static HTML without React client hydration (`public/js/main.js:15-45` handles scroll classes at runtime).
- **Generic Hero Image Alt Text (`src/pages/Home.tsx:43`)**: Uses `alt="OceanViewFlats"` instead of the localized descriptive `t.heroImgAlt` (`dict.ts:52`).
- **Untranslated Footer Credit Label (`src/components/Footer.tsx:99`)**: `"Engineered by"` is hardcoded in English across all 6 locales.

#### Questions to Consider

- **Why is the homepage hiding its live `COP` quote engine and instant Mercado Pago checkout?** What if the hero and property cards explicitly celebrated *instant online COP booking with 0% OTA commission* instead of routing guests to the Contact inquiry form?
- **How can the homepage help a traveler choose between Property 1707 and Property 1606 in 5 seconds?** What if each card surfaced its signature distinction right on the face—**1707: 17th Floor • Dedicated Oceanfront Workspace • 2 Bed / 2 Bath • Bay 95** vs. **1606: 16th Floor • Sunset Terrace & Family Lounge • 2 Bed / 2 Bath • Bay 87**?
- **Do "Why Book Direct" and "Tailored Experiences" need to be two separate icon-grid sections?** What if "Tailored Experiences" became an editorial split featuring host Manuel Enrique and real photography linked directly to each residence?
- **What if extended families knew they could book both the 16th and 17th floors in Edificio Salguero Sunset together?**
