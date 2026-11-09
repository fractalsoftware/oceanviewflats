---
name: OceanViewFlats
description: Direct booking platform and self-service coastal concierge for beachfront residences in Santa Marta, Colombia.
colors:
  coral-horizon: "#FF5A5F"
  coral-horizon-deep: "#FF424D"
  coral-horizon-ink: "#B91C1C"
  coral-wash: "#FEF2F2"
  salguero-sunset-rose: "#E11D48"
  salguero-sunset-rose-deep: "#BE123C"
  salguero-sunset-rose-light: "#FB7185"
  rose-wash: "#FFF1F2"
  clearance-emerald: "#059669"
  clearance-emerald-deep: "#047857"
  clearance-emerald-light: "#34D399"
  clearance-emerald-tint: "#6EE7B7"
  clearance-emerald-night: "#022C22"
  emerald-wash: "#ECFDF5"
  keycard-amber: "#F59E0B"
  amber-wash: "#FFFBEB"
  nomad-sky: "#0EA5E9"
  nomad-sky-light: "#38BDF8"
  sky-wash: "#F0F9FF"
  pure-white: "#FFFFFF"
  coastal-mist: "#F8FAFC"
  slate-surface: "#F1F5F9"
  slate-border: "#E2E8F0"
  slate-muted: "#94A3B8"
  slate-body: "#475569"
  slate-ink: "#1E293B"
  deep-caribbean-slate: "#0F172A"
  travertine-mist: "#FAFAF9"
  travertine-surface: "#F5F5F4"
  travertine-border: "#E7E5E4"
  travertine-ink: "#292524"
  warm-travertine-stone: "#1C1917"
typography:
  display:
    fontFamily: "Inter, -apple-system, BlinkMacSystemFont, sans-serif"
    fontSize: "3.75rem"
    fontWeight: 800
    lineHeight: 1.15
    letterSpacing: "-0.025em"
  headline:
    fontFamily: "Inter, -apple-system, BlinkMacSystemFont, sans-serif"
    fontSize: "2.25rem"
    fontWeight: 800
    lineHeight: 1.2
    letterSpacing: "-0.025em"
  title:
    fontFamily: "Inter, -apple-system, BlinkMacSystemFont, sans-serif"
    fontSize: "1.5rem"
    fontWeight: 700
    lineHeight: 1.35
    letterSpacing: "-0.015em"
  body:
    fontFamily: "Inter, -apple-system, BlinkMacSystemFont, sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.625
    letterSpacing: "0em"
  label:
    fontFamily: "Inter, -apple-system, BlinkMacSystemFont, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 700
    lineHeight: 1.25
    letterSpacing: "0.06em"
  mono:
    fontFamily: "ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace"
    fontSize: "1.5rem"
    fontWeight: 900
    lineHeight: 1.2
    letterSpacing: "0.08em"
rounded:
  sm: "8px"
  md: "12px"
  lg: "16px"
  xl: "24px"
  full: "9999px"
spacing:
  xs: "4px"
  sm: "8px"
  md: "16px"
  lg: "24px"
  xl: "32px"
  2xl: "48px"
  3xl: "80px"
  4xl: "96px"
components:
  button-primary:
    backgroundColor: "{colors.coral-horizon}"
    textColor: "{colors.pure-white}"
    typography: "{typography.body}"
    rounded: "{rounded.full}"
    padding: "16px 32px"
  button-primary-hover:
    backgroundColor: "{colors.coral-horizon-deep}"
    textColor: "{colors.pure-white}"
  button-property-1606:
    backgroundColor: "{colors.salguero-sunset-rose}"
    textColor: "{colors.pure-white}"
    typography: "{typography.body}"
    rounded: "{rounded.md}"
    padding: "16px 24px"
  button-property-1606-hover:
    backgroundColor: "{colors.salguero-sunset-rose-deep}"
    textColor: "{colors.pure-white}"
  button-settlement:
    backgroundColor: "{colors.clearance-emerald}"
    textColor: "{colors.pure-white}"
    typography: "{typography.body}"
    rounded: "{rounded.md}"
    padding: "16px 24px"
  button-settlement-hover:
    backgroundColor: "{colors.clearance-emerald-deep}"
    textColor: "{colors.pure-white}"
  input-default:
    backgroundColor: "{colors.pure-white}"
    textColor: "{colors.slate-ink}"
    typography: "{typography.body}"
    rounded: "{rounded.md}"
    padding: "12px 14px"
  card-surface:
    backgroundColor: "{colors.pure-white}"
    textColor: "{colors.slate-ink}"
    rounded: "{rounded.xl}"
    padding: "32px"
---

# Design System: OceanViewFlats

## Overview

**Creative North Star: "The Sunlit Coastal Concierge"**

OceanViewFlats pairs the luminous warmth of 16th- and 17th-floor Caribbean beachfront living at Edificio Salguero Sunset with the calm, unmistakable precision of a private hotel concierge. Every surface balances two responsibilities: inspiring direct bookings through expansive photography and transparent Colombian Peso (`COP`) pricing, and guiding arriving international guests through mandatory statutory check-in (`Guest Registry`) and smart-lock entry (`Guest Guide`) while they are often in transit on mobile devices.

The system is luminous, hospitable, and reassuringly structured. Generous white cards float above soft coastal-mist canvases (`#F8FAFC`), punctuated by deep architectural night bands (`#0F172A` and `#1C1917`) that frame remote-work, family, and sunset experiences. Rather than hiding regulatory requirements or building surcharges in fine print, the visual hierarchy elevates them into clear, high-trust callout cards with semantic color coding—turning Colombian Law 1581 consent, minor guardian attestations, and front-desk clearance into a smooth hospitality ritual.

The visual world explicitly rejects hidden-fee OTA clutter, sterile corporate hotel templates, dark-mode-everywhere SaaS clichés, and kitschy tropical clipart. Every visual element is centralized in `src/constants/theme.ts` so React page templates remain 100% style-agnostic and ship as zero-hydration static HTML across six languages (`en`, `es`, `fr`, `it`, `de`, `ja`).

**Key Characteristics:**
- **Dual-Property Atmospheric Tuning:** Global pages and Property 1707 pair **Coral Horizon** with cool **Deep Caribbean Slate** neutrals, while Property 1606 shifts to **Salguero Sunset Rose** over warm **Travertine Stone** neutrals.
- **Tactile, Travel-Ready Affordances:** Generous pill CTAs (`9999px`), large-radius cards (`24px`), and high-contrast inputs (`12px`) ensure effortless tap accuracy for older travelers and guests checking in from airport taxis or the building lobby.
- **Semantic Fulfillment Signaling:** Color roles map strictly to guest workflow stages—Coral/Rose for exploration and booking intent, **Clearance Emerald** for direct COP settlement and statutory data trust, and **Keycard Amber** for gated access credentials and building rules.
- **Six-Locale Zero-Hydration Resilience:** Fluid grid containers, non-clipping pill badges, and generous vertical rhythm absorb 30%+ text expansion in German, French, Spanish, and Italian alongside Japanese CJK typography without layout shift.

## Colors

The palette pairs sun-warmed coastal accents (Coral Horizon and Salguero Sunset Rose) with semantic concierge status hues (Clearance Emerald, Keycard Amber, and Nomad Sky) anchored by cool Slate and warm Travertine neutral ramps.

### Primary
- **Coral Horizon** (`coral-horizon` / `#FF5A5F`, hover `coral-horizon-deep` / `#FF424D`, high-contrast text/icon ink `coral-horizon-ink` / `#B91C1C`, soft wash `coral-wash` / `#FEF2F2`): The global brand signature and Property 1707 primary accent. Used on the scrolled brand wordmark, primary navigation "Book Direct" pills, homepage property comparison card CTAs (`#B91C1C` on light card surfaces for WCAG AA+ contrast), Property 1707 calendar active date cells, `Guest Registry` submission CTAs, and high-priority `Guest Guide` unlock banners.

### Secondary
- **Salguero Sunset Rose** (`salguero-sunset-rose` / `#E11D48`, hover `salguero-sunset-rose-deep` / `#BE123C`, dark-surface icon accent `salguero-sunset-rose-light` / `#FB7185`, soft wash `rose-wash` / `#FFF1F2`): The dedicated Property 1606 page primary accent, evoking 16th-floor sunset views over Playa Salguero. Replaces Coral Horizon across `/Oceanview1606` quick-stats icons, amenity badges, interactive calendar selection states, and booking form focus rings, as well as the Property 1606 editorial lifestyle icons on dark slate bands.

### Tertiary
- **Clearance Emerald** (`clearance-emerald` / `#059669`, hover `clearance-emerald-deep` / `#047857`, dark-surface badge/CTA accents `clearance-emerald-light` / `#34D399` and `clearance-emerald-tint` / `#6EE7B7` over `clearance-emerald-night` / `#022C22`, soft wash `emerald-wash` / `#ECFDF5`): Reserved for verified financial settlement, statutory legal trust (Colombian Law 1581 & RNT compliance badges), step-by-step arrival checkmarks, and direct WhatsApp host support actions.
- **Keycard Amber** (`keycard-amber` / `#F59E0B`, soft wash `amber-wash` / `#FFFBEB`): Signals physical access credentials (`0XXXXXX#` smart-lock door PIN container), locked-credential warnings prior to `Guest Registry` completion, reactive `Minor Guardian Attestation` callouts, and mandatory condominium notices (such as the `20,000 COP` per-person `Building Registration Fee` and pool maintenance schedules).
- **Nomad Sky** (`nomad-sky` / `#0EA5E9`, dark-surface icon accent `nomad-sky-light` / `#38BDF8`, soft wash `sky-wash` / `#F0F9FF`): Highlights high-speed fiber Wi-Fi credentials, remote-work features in Property 1707, and unlocked `Guest Guide` navigation actions.

### Neutral
- **Pure White** (`pure-white` / `#FFFFFF`): Primary elevated surface for property showcase cards, booking modules, `Guest Registry` occupant cards, and `Guest Guide` instruction panels.
- **Coastal Mist & Slate Surface** (`coastal-mist` / `#F8FAFC`, `slate-surface` / `#F1F5F9`): Default page canvas (`bg-slate-50`) and secondary recessed container fill (`bg-slate-100`) for global pages, homepage comparison cards, and Property 1707.
- **Slate Border & Muted Label** (`slate-border` / `#E2E8F0`, `slate-muted` / `#94A3B8`): Crisp 1px structural borders (`border-slate-200` on inputs, `border-slate-100` on cards) and uppercase field labels (`text-slate-400`).
- **Slate Body & Slate Ink** (`slate-body` / `#475569`, `slate-ink` / `#1E293B`): Primary reading copy (`text-slate-600` to `text-slate-800`) calibrated to exceed WCAG 2.1 AA+ contrast ratios against light canvases.
- **Deep Caribbean Slate** (`deep-caribbean-slate` / `#0F172A`): High-contrast headings (`text-slate-900`), starting `COP` rate pills on homepage property cards, and full-bleed dark editorial bands (Home Experiences section, Property 1707 Digital Nomad section, Host Bio banner, Legal Trust card, and global Footer).
- **Warm Travertine Stone Ramp** (`travertine-mist` / `#FAFAF9`, `travertine-surface` / `#F5F5F4`, `travertine-border` / `#E7E5E4`, `travertine-ink` / `#292524`, `warm-travertine-stone` / `#1C1917`): Property 1606's dedicated page neutral scale on `/Oceanview1606`, substituting cool slate with sun-baked stone tones across backgrounds, borders, body copy, and dark feature sections.

### Named Rules
**The Property Temperature Rule.** On dedicated property pages, `/Oceanview1707` and global operational surfaces pair **Coral Horizon** with cool **Slate** neutrals, while `/Oceanview1606` expresses the full Property Temperature split by pairing **Salguero Sunset Rose** with warm **Travertine Stone** neutrals. On the homepage (`/`), side-by-side comparison cards (`#home-card-1707` and `#home-card-1606`) intentionally share a unified neutral **Slate** + deep **Coral** (`#B91C1C`) frame so prospective guests compare floor level, bed count, and starting `COP` rates without competing card chrome.

**The Semantic Trust Rule.** **Clearance Emerald** and **Keycard Amber** are functional signals, never decorative fills. Emerald appears only when confirming direct COP payment, statutory data protection, or WhatsApp host assistance; Amber appears only on physical access credentials (`0XXXXXX#`), locked-access gates, minor guardian attestations, and mandatory building notices.

## Typography

**Display Font:** Inter (`font-weight: 800`, with `-apple-system, BlinkMacSystemFont, sans-serif` fallbacks)
**Body Font:** Inter (`font-weight: 300, 400, 500, 600, 700`, with system sans-serif fallbacks)
**Label/Mono Font:** System UI Monospace (`ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace`)

**Character:** Clean, humanist-geometric sans-serif typography that pairs bold, high-contrast editorial headlines with open, effortlessly legible body copy and unmistakable monospace access codes for multi-generational and international travelers across six scripts (Latin and Japanese CJK).

### Hierarchy
- **Display** (`800 ExtraBold`, `2.25rem` mobile to `3.75rem` desktop / `36px–60px`, `line-height: 1.15`, `letter-spacing: -0.025em`): Full-bleed hero titles over darkened beachfront photography with subtle drop-shadow reinforcement.
- **Headline** (`700 Bold` to `800 ExtraBold`, `1.875rem` mobile to `2.25rem` desktop / `30px–36px`, `line-height: 1.2`, `letter-spacing: -0.025em`): Major section headings ("Why Book Direct", "About This Place", "Guest Registry").
- **Title** (`700 Bold` to `800 ExtraBold`, `1.25rem` to `1.5rem` / `20px–24px`, `line-height: 1.35`, `letter-spacing: -0.015em`): Card titles, property names on showcase cards, and panel headers inside `/registry` and `/guide`.
- **Body** (`400 Regular` and `500 Medium`, `1rem` / `16px` standard, `1.125rem` / `18px` lead paragraphs, `0.875rem` / `14px` supporting card copy, `line-height: 1.625`, max measure `65ch`): Long-form property descriptions, FAQ answers, arrival instructions, and legal consent clauses.
- **Label** (`700 Bold` to `800 ExtraBold`, `0.75rem` / `12px` standard and `0.625rem` / `10px` micro-badges, `line-height: 1.25`, `letter-spacing: 0.06em`, `uppercase`): Form input headers ("CHECK-IN", "DOCUMENT TYPE"), section eyebrow pills, and required-field indicators.
- **Monospace Credential** (`900 Black` for door codes at `1.5rem` / `24px`, `600 SemiBold` for policy version pills at `0.75rem` / `12px`, `letter-spacing: 0.08em`): Used strictly for temporal smart-lock PINs (`0XXXXXX#`) and immutable policy version stamps (`2026-10`).

### Named Rules
**The Monospace Credential Rule.** Temporal door PINs (`0XXXXXX#`) and statutory policy version tokens must always render in monospace with wide tracking (`0.08em`) inside a dedicated copyable container so digits and `#` terminators are never misread at the apartment door.

**The Six-Locale Measure Rule.** Never constrain headings, buttons, or uppercase labels to fixed pixel heights or `nowrap` truncation (except the horizontally scrollable mobile QuickStats strip). German compounds, French legal phrases, and Japanese characters must wrap naturally with comfortable line-height.

## Layout

OceanViewFlats uses a responsive, centered container architecture that adapts between expansive persuasion layouts on marketing pages and focused, two-column operational split views on booking and fulfillment pages.

- **Container Widths:**
  - **Wide Showcase Container** (`max-w-6xl` / `1152px`, `px-6` / `24px` horizontal padding): Used for the global Navigation bar, Home property grid, "Why Book Direct" 4-column feature grid, Property 1707/1606 About & Amenities sections, Image Galleries, and the `Guest Guide` workspace.
  - **Focused Transaction Container** (`max-w-5xl` / `1024px`, `px-6` / `24px`): Used for the split-column `BookingSection` (interactive calendar on the left, quote breakdown and direct checkout form on the right) and the `Guest Registry` workspace.
  - **Reading & FAQ Container** (`max-w-4xl` / `896px` to `max-w-3xl` / `768px`, `px-6` / `24px`): Used for hero text stacks, the Sticky QuickStats bar, FAQ accordions/cards, and `/privacy` legal documentation.
- **Grid Topologies:**
  - **Home & Property Showcases:** 2-column equal splits (`grid-cols-1 md:grid-cols-2`, `gap-10` / `40px` on Home; `gap-16` / `64px` on About/Amenities and Nomad/Romantic sections) and 4-column value-proposition grids (`grid-cols-1 md:grid-cols-4`, `gap-8` / `32px`).
  - **Fulfillment Split View (`/registry` & `/guide`):** 12-column asymmetric desktop grid (`grid-cols-1 lg:grid-cols-12`, `gap-8` / `32px`). On `/registry`, a 4-column context rail (Stay Summary + Legal Trust card) anchors the left while the 8-column dynamic occupant form occupies the right. On `/guide`, an 8-column primary instruction column sits beside a 4-column utility rail (Wi-Fi, Assigned Parking Space, Building Registration Fee, and 24/7 Support).
- **Vertical Rhythm:** Marketing sections breathe with `80px` to `96px` vertical padding (`py-20` to `py-24`), while operational workspaces (`/registry`, `/guide`) use tighter `48px` vertical padding (`py-12`) and `24px–32px` card stacks (`space-y-6` to `space-y-8`) so critical tasks stay above the fold.

## Elevation & Depth

The visual system is **layered with buoyant lift**. At rest, white cards (`#FFFFFF`) sit over cool Coastal Mist (`#F8FAFC`) or warm Travertine (`#F5F5F4`) canvases, separated primarily by crisp `1px` structural borders (`#F1F5F9` or `#E2E8F0`) and soft ambient shadows. When a visitor hovers over an interactive property card, primary CTA, or calendar control—or scrolls the fixed navigation bar over page content—surfaces respond with a subtle upward lift (`-translate-y-1` or `scale-[1.02]`), expanded shadow diffusion, or frosted glass translucency (`backdrop-blur-md`).

### Shadow Vocabulary
- **Subtle Input & Inner-Tile Rest** (`shadow-xs` / `0 1px 2px 0 rgba(15, 23, 42, 0.05)` and `shadow-sm` / `0 1px 3px 0 rgba(15, 23, 42, 0.08)`): Used on date-display tiles, secondary guest cards, copy buttons, and scrolled navbar edges.
- **Standard Card Elevation** (`shadow-md` / `0 4px 6px -1px rgba(15, 23, 42, 0.08), 0 2px 4px -2px rgba(15, 23, 42, 0.06)`): Default resting depth for property cards, `/guide` operational panels, `/privacy` section cards, and stay summary modules.
- **Interactive & Transaction Lift** (`shadow-xl` / `0 20px 25px -5px rgba(15, 23, 42, 0.10), 0 8px 10px -6px rgba(15, 23, 42, 0.06)`): Applied to the main `BookingSection` container, the primary `Guest Registry` form panel, and feature cards on hover.
- **Hero & Floating Showcase Depth** (`shadow-2xl` / `0 25px 50px -12px rgba(15, 23, 42, 0.25)`): Reserved for hero primary CTA pills, floating photo badges on dark editorial bands, and property showcase cards on hover.
- **Coral Brand Glow** (`0 10px 25px -5px rgba(255, 90, 95, 0.28)`): Accent-tinted shadow beneath primary Coral Horizon CTA pills on dark callout banners.

### Named Rules
**The Border-Plus-Shadow Rule.** Never rely on a drop shadow alone to separate a white card from a light `#F8FAFC` background. Every elevated card pairs its ambient shadow with an explicit `1px` border (`border-slate-100`, `border-slate-200`, or `border-stone-100`) so boundaries remain crisp on low-contrast mobile screens in bright sunlight.

**The Frosted Chrome Rule.** Translucent `backdrop-blur-md` surfaces are reserved strictly for the scrolled fixed Navigation bar (`bg-white/90`), hero location/status badges (`bg-white/20` or `bg-amber-500/10`), and dark hero photo overlays (`backdrop-blur-[2px]`). Never apply backdrop blur to form cards or reading panels.

## Shapes

OceanViewFlats uses a warm, rounded geometric form language that softens dense financial and legal workflows without sacrificing structural alignment:

- **Outer Section & Feature Cards (`24px` / `rounded-3xl`):** Every major content card—property showcases, the split booking module, `Guest Registry` panels, `Guest Guide` cards, and dark editorial callout banners—uses a generous `24px` corner radius with `overflow-hidden` to clip full-bleed imagery and ambient corner blurs cleanly.
- **Nested Sub-Cards & Alert Callouts (`16px` / `rounded-2xl`):** Inner occupant slots (`guest-card-1..12`), itemized COP quote breakdown boxes, Wi-Fi/parking credential wells, and statutory consent callouts step down to `16px` so nested curvature stays concentric inside `24px` parent cards.
- **Form Controls & Action Buttons (`12px` / `rounded-xl`):** Text inputs, selects, date inputs, copy icon buttons, and full-width form buttons inside cards use a structured `12px` radius.
- **Navigation, Hero CTAs & Calendar Cells (`9999px` / `rounded-full`):** Standalone primary CTAs (Hero "Book Now", Navigation "Book Now", `Guest Registry` submit button, Google Maps/Waze route buttons), eyebrow badges, and interactive 32px–40px calendar day cells are full pills or circles.

## Components

Every component is tactile, generous, and travel-ready—engineered with unmistakable tap targets, explicit state transitions, and zero style conditionals inside React view files.

### Buttons
- **Shape:** Full pill (`9999px` radius) for standalone hero, navigation, and registry-submission CTAs; structured rounded rectangle (`12px` radius) for full-width actions inside booking and support cards.
- **Primary (Coral Horizon / Sunset Rose):** `#FF5A5F` background (`#E11D48` on Property 1606) with `#FFFFFF` bold text (`font-weight: 700`), `16px 32px` padding (`py-4 px-8`) on hero/primary pills or `8px 20px` (`py-2 px-5`) in the top navbar.
- **Direct Settlement CTA (Clearance Emerald):** `#059669` background with `#FFFFFF` bold text (`18px`), `16px` vertical padding (`py-4`), `12px` radius (`rounded-xl`), used exclusively for the final Direct Reservation checkout submission (`#btn-direct-submit`).
- **Hover / Focus:** Primary buttons darken to `#FF424D` (`#BE123C` on Property 1606; `#047857` on Emerald), scale subtly (`hover:scale-[1.02]` or `hover:scale-105` on nav/hero pills), and display a `4px` translucent focus ring (`rgba(255, 90, 95, 0.35)`).
- **Secondary / Ghost / Dashed:**
  - *Hero Inverted Pill:* `#FFFFFF` background with `#0F172A` (`#1C1917` on 1606) text and a leading `#FF5A5F` / `#E11D48` icon, shifting to `#F1F5F9` on hover.
  - *WhatsApp Secondary CTA:* Soft emerald wash (`#ECFDF5`) with `1px solid #A7F3D0` border and `#065F46` semibold text (`12px` radius).
  - *Add Companion Slot Button:* Full-width `16px`-radius button with `2px dashed #E2E8F0` border and `#64748B` bold text, shifting to `#FF5A5F` border and text on hover.

### Chips
- **Style:** Pill-shaped eyebrow and status badges (`9999px` radius, `6px 16px` padding, `12px` uppercase bold text with `0.06em` letter-spacing).
- **State:**
  - *Hero Location Chip:* Translucent white (`rgba(255, 255, 255, 0.20)`) with `backdrop-blur-md` and `#FFFFFF` text over beachfront photography.
  - *Section Eyebrow Chip:* Soft rose wash (`#FFF1F2`) with `#FF5A5F` text on light sections.
  - *Statutory / Guide Status Chip:* Frosted emerald (`rgba(16, 185, 129, 0.15)` fill, `1px solid rgba(16, 185, 129, 0.30)`, `#6EE7B7` text) on `/privacy` or frosted amber (`rgba(245, 158, 11, 0.10)` fill, `1px solid rgba(245, 158, 11, 0.20)`, `#FCD34D` text) on `/guide`.

### Cards / Containers
- **Corner Style:** `24px` (`rounded-3xl`) for primary cards and dark feature banners; `16px` (`rounded-2xl`) for nested occupant slots, price breakdowns, and credential wells.
- **Background:** `#FFFFFF` for primary cards; `#F8FAFC` (`#FAFAF9` on 1606) for recessed sub-cards; `#0F172A` (`#1C1917` on 1606) for high-contrast trust and editorial panels.
- **Shadow Strategy:** `shadow-md` at rest for standard cards, `shadow-xl` for primary interactive workspaces (`BookingSection`, `Registry` form), lifting to `shadow-2xl` with `-4px` vertical translation (`hover:-translate-y-1`) on clickable property showcase cards.
- **Border:** `1px solid #F1F5F9` (`#F5F5F4` on 1606) on standard cards; `1px solid #E2E8F0` (`#E7E5E4` on 1606) on booking and primary occupant containers.
- **Internal Padding:** `24px` (`p-6`) on compact utility cards and mobile views, expanding to `32px–40px` (`p-8` to `p-10`) on desktop workspaces.

### Inputs / Fields
- **Style:** `12px` radius (`rounded-xl`), `1px solid #E2E8F0` (`#E7E5E4` on 1606) border, `#FFFFFF` background, `10px 14px` (`py-2.5 px-3.5`) or `12px` (`p-3`) padding, `14px` (`text-sm`) `#1E293B` text with `#94A3B8` placeholders, paired with a `12px` bold uppercase label above (`#94A3B8` or `#64748B`) and an inline `10px` `#FF5A5F` `REQUIRED` badge when mandatory.
- **Focus:** Border shifts immediately to `#FF5A5F` (`#E11D48` on Property 1606) with `outline: none` and a soft `2px` outer glow (`rgba(255, 90, 95, 0.15)`).
- **Error / Disabled:** Past calendar dates and locked inputs use `cursor-not-allowed` with muted `#CBD5E1` text; reactive statutory callouts (such as the `Minor Guardian Attestation` container when a guest is under 18 or holds a minor ID) reveal smoothly in an `#FFFBEB` (`border-amber-200`) container.

### Navigation
- **Top Navigation Bar (`#navbar`):** Fixed full-width header (`z-50`, `max-w-6xl` inner container) that transitions smoothly (`300ms ease`) from transparent (`py-5`, `#FFFFFF` brand logo and links) over hero photography to a frosted white bar (`bg-white/90`, `backdrop-blur-md`, `shadow-sm`, `py-3`) once scrolled past `50px`, switching the brand mark to `#FF5A5F` and links to `#475569`. Includes the SVG sun-and-waves mark, contact link (icon-only on mobile, text on desktop), 6-locale `LanguageDropdown` pill, and optional desktop "Book Now" pill.
- **Sticky Property QuickStats Bar:** Positioned immediately below the navbar (`sticky top-[58px] md:top-[72px] z-40`), rendering 4 key property metrics (Guests, Beds, Baths, Oceanfront) separated by `1px` vertical dividers (`h-5 bg-slate-200`) in a horizontally scrollable single row on mobile (`scrollbar-none`) and a centered 4-column bar on desktop.

### Interactive Booking & Quote Module (`BookingSection` & `CalendarWidget`)
- **Split-Card Architecture:** A `24px`-radius card divided into a recessed left calendar column (`bg-slate-50/50`, `border-r border-slate-200`) and a white right checkout column.
- **Calendar Day Cells:** `32px × 32px` mobile / `40px × 40px` desktop circular buttons (`rounded-full`). Selected Check-in/Check-out dates fill with solid `#FF5A5F` (`#E11D48` on 1606) and white text; intermediate stay nights fill with a `10%` tint (`bg-[#FF5A5F]/10 text-[#FF5A5F]` or `bg-rose-600/10 text-rose-600`).
- **Authoritative COP Quote Breakdown (`#price-breakdown-card`):** Recessed `16px`-radius card itemizing Nightly Rate × Nights and Cleaning Fee in `COP`, separated by a `1px` horizontal rule above an `ExtraBold 20px` total in `COP` and a muted `12px` non-authoritative USD reference estimate with an `ⓘ` disclaimer tooltip.

### Registry-Gated Access Credential Card (`#door-code-card`)
- **Structure:** Located on `/guide`, styled with a warm amber gradient tint (`from-amber-500/10 to-amber-600/[0.03]`), `24px` radius, and `1px solid rgba(245, 158, 11, 0.15)` border.
- **Credential Well:** Displays the temporal smart-lock PIN (`0XXXXXX#`) in `24px Black Monospace` inside a translucent white well (`bg-white/70`, `16px` radius) alongside a high-contrast `#0F172A` one-click copy button (`#btn-copy-door-code`).
- **Locked Gate State (`#credential-locked-notice`):** When the `Guest Registry` has not yet been completed (or after a stay becomes a `Concluded Reservation`), the credential well masks the PIN (`--`) and reveals an amber warning banner with a direct `#FF5A5F` action button scrolling to the mandatory `Guest Registry` banner.

## Do's and Don'ts

### Do:
- **Do** centralize every new color class, border token, and property-specific variant in `src/constants/theme.ts` (`THEMES["1707"]`, `THEMES["1606"]`, `PRIVACY_THEME_STYLES`) so React components in `src/components/` and `src/pages/` remain 100% style-agnostic.
- **Do** honor the **Property Temperature Rule**: use Coral Horizon (`#FF5A5F`) + Cool Slate (`#0F172A` / `#F8FAFC`) on global pages and Property 1707, and Salguero Sunset Rose (`#E11D48`) + Warm Travertine Stone (`#1C1917` / `#FAFAF9`) on Property 1606.
- **Do** display Settlement Currency (`COP`) as the primary, largest typographic value in every quote and financial summary, treating foreign currency (`USD`) strictly as a secondary `12px` muted estimate (`text-slate-500`) with an explicit disclaimer.
- **Do** keep interactive tap targets at or above `40px × 40px` (`py-2.5` to `py-4` on inputs and buttons, `w-10 h-10` on desktop calendar cells) and maintain WCAG 2.1 AA+ contrast ratios so older travelers and mobile guests in transit can complete check-in effortlessly.
- **Do** pair every white card on a light canvas with both an explicit `1px` border (`border-slate-100` or `border-slate-200`) and a soft shadow (`shadow-sm` to `shadow-xl`).

### Don't:
- **Don't** embed property-conditional layout or style ternaries (e.g., `propertyId === "1707" ? "bg-red-50" : "bg-rose-50"`) directly inside React component JSX; resolve them through `THEMES[propertyId]`.
- **Don't** hardcode UI copy or inline translation maps in components or Vanilla JS (`public/js/`); define all strings across all 6 locales (`en`, `es`, `fr`, `it`, `de`, `ja`) in `src/i18n/dict.ts` and read them on the client exclusively via `window.t()`.
- **Don't** use **Clearance Emerald** (`#059669`) or **Keycard Amber** (`#F59E0B`) as generic decorative colors; preserve their strict semantic association with payment settlement/legal trust and gated physical access credentials/warnings.
- **Don't** load heavy third-party scripts (such as the Mercado Pago SDK) in the global HTML head or introduce client-side React hydration; preserve zero-hydration static HTML delivery for low-bandwidth roaming connections.
- **Don't** use fixed-height text containers or single-line clipping (`truncate` / `whitespace-nowrap`) on localized headings, legal attestations, or CTA buttons that must expand across German, French, Spanish, Italian, and Japanese.
