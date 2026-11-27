import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import test from "node:test";
import vm from "node:vm";
import {
	AIRBNB_URLS,
	BUILDING_MAP_URL,
	LANGUAGES,
	PLAYA_SALGUERO_LANDMARKS,
} from "../../src/constants/config";
import {
	CHROME_THEME,
	HOME_CARD_THEMES,
	HOME_THEME,
} from "../../src/constants/theme";
import { dict } from "../../src/i18n/dict";

test("#336 Shared chrome across all 6 locales and depths removes public Guest Registry & Guest Guide links, structures mobile drawer and 3-column Contact & Legal footer, preserves aria-current='page', >=44px tap targets, and declarative data-scrolled CSS tokens", () => {
	const distDir = path.join(process.cwd(), "dist");
	const css = fs.readFileSync(path.join(distDir, "css", "style.css"), "utf-8");

	// 1. Declarative CSS rules for data-scrolled, global Coral Horizon hover, and non-color aria-current="page" indicators
	assert.ok(
		/#navbar\[data-scrolled=["']?true["']?\]/.test(css) &&
			css.includes("backdrop-filter:blur(12px)") &&
			css.includes("var(--color-coral-horizon-ink)") &&
			css.includes("var(--color-slate-body)") &&
			css.includes("var(--color-slate-surface)"),
		"Compiled CSS must style #navbar[data-scrolled='true'] declaratively via registered design-system tokens",
	);

	const styleSource = fs.readFileSync(
		path.join(process.cwd(), "src", "style.css"),
		"utf-8",
	);
	assert.ok(
		/#navbar\[data-scrolled="true"\]\s+#nav-guides:hover[\s\S]*?color:\s*var\(--color-coral-horizon-ink\);/.test(
			styleSource,
		) &&
			!/#navbar\[data-scrolled="true"\][^}]*--color-salguero-sunset-rose/.test(
				styleSource,
			),
		"src/style.css must style #navbar[data-scrolled='true'] link hovers with global --color-coral-horizon-ink (not Property 1606 rose)",
	);
	assert.ok(
		/\[aria-current=["']?page["']?\]/.test(css) &&
			css.includes("border-block-end:") &&
			css.includes("border-inline-start:") &&
			css.includes("footer a[aria-current=") &&
			css.includes("forced-colors:active"),
		"Compiled CSS must provide non-color visual indicators for aria-current='page' in navbar, drawer, and footer with forced-colors adaptation",
	);
	for (const deadSelector of [
		"#nav-checkin-toggle",
		"#nav-checkin-menu",
		".ovf-checkin-chevron",
	]) {
		assert.ok(
			!styleSource.includes(deadSelector) && !css.includes(deadSelector),
			`Dead check-in CSS selector "${deadSelector}" must be removed from src/style.css and dist/css/style.css`,
		);
	}

	// 2. Six-Locale Measure Rule & dead CHROME_THEME token removal
	assert.ok(
		!CHROME_THEME.navGuides.includes("whitespace-nowrap") &&
			!CHROME_THEME.navContact.includes("whitespace-nowrap"),
		"CHROME_THEME desktop nav links must omit whitespace-nowrap per DESIGN.md six-locale measure rule",
	);
	const chromeThemeKeys = Object.keys(CHROME_THEME);
	for (const deadToken of [
		"navCheckinGroup",
		"navCheckinToggle",
		"navCheckinChevron",
		"navCheckinMenuWrapper",
		"navCheckinMenuCard",
		"navCheckinMenuList",
		"navCheckinMenuLink",
		"navCheckinMenuLinkIcon",
		"footerPrivacyWrapper",
		"footerPrivacyLink",
	]) {
		assert.ok(
			!chromeThemeKeys.includes(deadToken),
			`Dead CHROME_THEME token "${deadToken}" must be removed`,
		);
	}
	assert.ok(
		CHROME_THEME.footerGrid.includes("lg:grid-cols-3") &&
			!CHROME_THEME.footerGrid.includes("lg:grid-cols-4"),
		"CHROME_THEME.footerGrid must use a three-column desktop grid (lg:grid-cols-3)",
	);

	// 3. Verify main.js uses declarative data-scrolled, retains bindDisclosure for #lang-dropdown, and removes check-in hooks
	const mainJs = fs.readFileSync(
		path.join(process.cwd(), "public", "js", "main.js"),
		"utf-8",
	);
	const syncScrollBlockMatch = mainJs.match(
		/function syncNavbarScrollState\(\)\s*\{[\s\S]*?\n\}/,
	);
	assert.ok(
		syncScrollBlockMatch,
		"main.js must define syncNavbarScrollState()",
	);
	assert.ok(
		syncScrollBlockMatch[0].includes('setAttribute("data-scrolled"') &&
			!syncScrollBlockMatch[0].includes("classList"),
		"syncNavbarScrollState() must toggle only data-scrolled on #navbar without mutating individual element classLists",
	);
	assert.ok(
		mainJs.includes("function bindDisclosure(") &&
			(mainJs.match(/(?<!function )\bbindDisclosure\(\{/g) || []).length === 1,
		"main.js must retain shared bindDisclosure() for #lang-dropdown with the check-in disclosure binding removed",
	);
	for (const deadJsToken of [
		"nav-checkin-group",
		"nav-checkin-toggle",
		"nav-checkin-menu",
		"navStayingGuestsOpenAriaLabel",
		"navStayingGuestsCloseAriaLabel",
	]) {
		assert.ok(
			!mainJs.includes(deadJsToken),
			`public/js/main.js must not reference removed check-in token "${deadJsToken}"`,
		);
	}

	// 4. Verify all 6 locales across root (depth 0), depth 1, and depth 2 pages
	const locales = [
		{
			code: "en",
			suffix: "",
			homeFile: "index.html",
			notFoundFile: "404.html",
			contactLegalHtml: "Contact &amp; Legal",
			contactLegalText: "Contact & Legal",
			removedStayingGuestsHeading: "Staying Guests",
		},
		{
			code: "es",
			suffix: "es.html",
			homeFile: "es.html",
			notFoundFile: "404/es.html",
			contactLegalHtml: "Contacto y Legal",
			contactLegalText: "Contacto y Legal",
			removedStayingGuestsHeading: "Huéspedes Confirmados",
		},
		{
			code: "fr",
			suffix: "fr.html",
			homeFile: "fr.html",
			notFoundFile: "404/fr.html",
			contactLegalHtml: "Contact &amp; Mentions Légales",
			contactLegalText: "Contact & Mentions Légales",
			removedStayingGuestsHeading: "Voyageurs Confirmés",
		},
		{
			code: "it",
			suffix: "it.html",
			homeFile: "it.html",
			notFoundFile: "404/it.html",
			contactLegalHtml: "Contatti e Note Legali",
			contactLegalText: "Contatti e Note Legali",
			removedStayingGuestsHeading: "Ospiti Confermati",
		},
		{
			code: "de",
			suffix: "de.html",
			homeFile: "de.html",
			notFoundFile: "404/de.html",
			contactLegalHtml: "Kontakt &amp; Rechtliches",
			contactLegalText: "Kontakt & Rechtliches",
			removedStayingGuestsHeading: "Bestätigte Gäste",
		},
		{
			code: "ja",
			suffix: "ja.html",
			homeFile: "ja.html",
			notFoundFile: "404/ja.html",
			contactLegalHtml: "お問い合わせ・法的情報",
			contactLegalText: "お問い合わせ・法的情報",
			removedStayingGuestsHeading: "ご予約済みのお客様",
		},
	] as const;

	// Verify dead shared-chrome translation keys are removed across all 6 locales
	for (const locale of locales) {
		const localeDict = dict[locale.code] as Record<string, string>;
		assert.equal(
			localeDict.footerContactLegalLabel,
			locale.contactLegalText,
			`dict.${locale.code}.footerContactLegalLabel must be localized to "${locale.contactLegalText}"`,
		);
		for (const deadKey of [
			"navGuestGuide",
			"footerStayingGuestsLabel",
			"navStayingGuests",
			"navStayingGuestsOpenAriaLabel",
			"navStayingGuestsCloseAriaLabel",
		]) {
			assert.equal(
				localeDict[deadKey],
				undefined,
				`dict.${locale.code} must remove orphaned shared-chrome key "${deadKey}"`,
			);
		}
	}

	const privateFulfillmentHrefRegex =
		/<a\b[^>]*href=["'][^"']*(?:^|\/)(?:registry|guide)(?:\/|$|\?|#)/i;

	for (const locale of locales) {
		const pageMatrix = [
			{
				file: locale.homeFile,
				prefix: "./",
				activeDesktopId: "nav-logo",
				activeDrawerHrefType: null,
				expectGuidesAncestorActive: false,
				activeFooterId: null,
				activeFooterGuideSlug: null,
			},
			{
				file: `Oceanview1707/${locale.suffix || "index.html"}`,
				prefix: "../",
				activeDesktopId: null,
				activeDrawerHrefType: "1707",
				expectGuidesAncestorActive: false,
				activeFooterId: "footer-property-1707-link",
				activeFooterGuideSlug: null,
			},
			{
				file: `Oceanview1606/${locale.suffix || "index.html"}`,
				prefix: "../",
				activeDesktopId: null,
				activeDrawerHrefType: "1606",
				expectGuidesAncestorActive: false,
				activeFooterId: "footer-property-1606-link",
				activeFooterGuideSlug: null,
			},
			{
				file: `registry/${locale.suffix || "index.html"}`,
				prefix: "../",
				activeDesktopId: null,
				activeDrawerHrefType: null,
				expectGuidesAncestorActive: false,
				activeFooterId: null,
				activeFooterGuideSlug: null,
			},
			{
				file: `guide/${locale.suffix || "index.html"}`,
				prefix: "../",
				activeDesktopId: null,
				activeDrawerHrefType: null,
				expectGuidesAncestorActive: false,
				activeFooterId: null,
				activeFooterGuideSlug: null,
			},
			{
				file: `contact/${locale.suffix || "index.html"}`,
				prefix: "../",
				activeDesktopId: "nav-contact",
				activeDrawerHrefType: "contact",
				expectGuidesAncestorActive: false,
				activeFooterId: "footer-contact-link",
				activeFooterGuideSlug: null,
			},
			{
				file: `privacy/${locale.suffix || "index.html"}`,
				prefix: "../",
				activeDesktopId: null,
				activeDrawerHrefType: null,
				expectGuidesAncestorActive: false,
				activeFooterId: "footer-privacy-link",
				activeFooterGuideSlug: null,
			},
			{
				file: `santa-marta/${locale.suffix || "index.html"}`,
				prefix: "../",
				activeDesktopId: "nav-guides",
				activeDrawerHrefType: "guides",
				expectGuidesAncestorActive: false,
				activeFooterId: "footer-guides-hub-link",
				activeFooterGuideSlug: null,
			},
			{
				file: `santa-marta/digital-nomad-remote-work/${locale.suffix || "index.html"}`,
				prefix: "../../",
				activeDesktopId: null,
				activeDrawerHrefType: null,
				expectGuidesAncestorActive: true,
				activeFooterId: null,
				activeFooterGuideSlug: "digital-nomad-remote-work",
			},
			{
				file: locale.notFoundFile,
				prefix: "/",
				activeDesktopId: null,
				activeDrawerHrefType: null,
				expectGuidesAncestorActive: false,
				activeFooterId: null,
				activeFooterGuideSlug: null,
			},
		] as const;

		for (const page of pageMatrix) {
			const html = fs.readFileSync(path.join(distDir, page.file), "utf-8");
			const navMatch = html.match(/<nav[^>]*id="navbar"[^>]*>[\s\S]*?<\/nav>/);
			assert.ok(navMatch, `${page.file} must render <nav id="navbar">`);
			const navHtml = navMatch[0];

			// data-scrolled initial state
			assert.ok(
				/<nav[^>]*id="navbar"[^>]*data-scrolled="false"/.test(navHtml) ||
					/<nav[^>]*data-scrolled="false"[^>]*id="navbar"/.test(navHtml),
				`${page.file} #navbar must initialize data-scrolled="false"`,
			);

			// Zero ARIA application-menu roles inside primary navigation landmark
			assert.ok(
				!navHtml.includes('role="menu"') &&
					!navHtml.includes('role="menuitem"') &&
					!navHtml.includes('role="menubar"') &&
					!navHtml.includes('aria-haspopup="menu"'),
				`${page.file} <nav id="navbar"> must not use ARIA application-menu roles`,
			);

			// Zero desktop check-in disclosure elements or /registry /guide links in <nav id="navbar">
			for (const removedId of [
				"nav-checkin-group",
				"nav-checkin-toggle",
				"nav-checkin-menu",
				"nav-checkin-registry",
				"nav-checkin-guide",
			]) {
				assert.ok(
					!navHtml.includes(removedId),
					`${page.file} <nav id="navbar"> must not include removed element #${removedId}`,
				);
			}
			assert.ok(
				!privateFulfillmentHrefRegex.test(navHtml),
				`${page.file} <nav id="navbar"> must not contain any link to /registry or /guide`,
			);

			const expectedContactHref = `${page.prefix}contact/${locale.suffix}`;
			const expected1707Href = `${page.prefix}Oceanview1707/${locale.suffix}`;
			const expected1606Href = `${page.prefix}Oceanview1606/${locale.suffix}`;
			const expectedGuidesHref = `${page.prefix}santa-marta/${locale.suffix}`;
			const expectedPrivacyHref = `${page.prefix}privacy/${locale.suffix}`;

			// Mobile drawer orders Properties (1707, 1606) -> Destination Guides -> standalone Contact Us
			const drawerMatch = navHtml.match(
				/<div[^>]*id="mobile-nav-drawer"[^>]*>[\s\S]*<\/nav>/,
			);
			assert.ok(drawerMatch, `${page.file} must render #mobile-nav-drawer`);
			const drawerHtml = drawerMatch[0];
			assert.ok(
				!drawerHtml.includes(locale.removedStayingGuestsHeading) &&
					!privateFulfillmentHrefRegex.test(drawerHtml),
				`${page.file} #mobile-nav-drawer must omit Staying Guests heading and /registry or /guide links`,
			);
			const drawerHeadings = drawerHtml.match(/<p\b[^>]*>[\s\S]*?<\/p>/g) || [];
			assert.equal(
				drawerHeadings.length,
				2,
				`${page.file} #mobile-nav-drawer must render exactly 2 group headings (Properties and Destination Guides; Contact Us is standalone)`,
			);
			const drawer1707Idx = drawerHtml.indexOf(`href="${expected1707Href}"`);
			const drawer1606Idx = drawerHtml.indexOf(`href="${expected1606Href}"`);
			const drawerGuidesIdx = drawerHtml.indexOf(
				`href="${expectedGuidesHref}"`,
			);
			const drawerContactIdx = drawerHtml.indexOf(
				`href="${expectedContactHref}"`,
			);
			assert.ok(
				drawer1707Idx > 0 &&
					drawer1606Idx > drawer1707Idx &&
					drawerGuidesIdx > drawer1606Idx &&
					drawerContactIdx > drawerGuidesIdx,
				`${page.file} #mobile-nav-drawer must order Properties (1707, 1606) -> Destination Guides -> standalone Contact Us`,
			);

			// Mobile drawer links enforce >=44px tap targets and accurate aria-current="page" / data-active="true"
			const drawerAnchors = drawerHtml.match(/<a\b[^>]*>/g) || [];
			assert.equal(
				drawerAnchors.length,
				4,
				`${page.file} #mobile-nav-drawer must contain exactly 4 links (1707, 1606, Destination Guides, Contact Us)`,
			);
			for (const drawerAnchor of drawerAnchors) {
				assert.ok(
					drawerAnchor.includes("min-h-[44px]"),
					`${page.file} every mobile drawer link must enforce min-h-[44px], got: ${drawerAnchor}`,
				);
			}

			const escapeRegex = (value: string) =>
				value.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");

			const drawerLinkTargets = [
				{ key: "1707", href: expected1707Href },
				{ key: "1606", href: expected1606Href },
				{ key: "guides", href: expectedGuidesHref },
				{ key: "contact", href: expectedContactHref },
			] as const;

			for (const target of drawerLinkTargets) {
				const linkMatch = drawerHtml.match(
					new RegExp(`<a[^>]*href="${escapeRegex(target.href)}"[^>]*>`),
				);
				assert.ok(
					linkMatch,
					`${page.file} #mobile-nav-drawer must render ${target.key} link`,
				);
				assert.equal(
					linkMatch[0].includes('aria-current="page"'),
					page.activeDrawerHrefType === target.key,
					`${page.file} mobile drawer ${target.key} link aria-current="page" mismatch`,
				);
				if (target.key === "guides") {
					assert.equal(
						linkMatch[0].includes('data-active="true"'),
						page.expectGuidesAncestorActive,
						`${page.file} mobile drawer guides link data-active="true" must match child article state`,
					);
				}
			}

			// Active aria-current="page" and data-active checks on desktop nav links
			for (const id of ["nav-logo", "nav-guides", "nav-contact"] as const) {
				const linkMatch = navHtml.match(new RegExp(`<a[^>]*id="${id}"[^>]*>`));
				assert.ok(linkMatch, `${page.file} must render #${id}`);
				assert.equal(
					linkMatch[0].includes('aria-current="page"'),
					page.activeDesktopId === id,
					`${page.file} #${id} aria-current="page" mismatch`,
				);
				if (id === "nav-guides") {
					assert.equal(
						linkMatch[0].includes('data-active="true"'),
						page.expectGuidesAncestorActive,
						`${page.file} #nav-guides data-active="true" must indicate ancestor state on child guide articles`,
					);
				}
			}

			// >=44px tap targets across all nav and footer interactive controls (zero legacy min-h-[40px])
			assert.ok(
				!navHtml.includes("min-h-[40px]") && !navHtml.includes("min-w-[40px]"),
				`${page.file} navbar must upgrade all interactive tap targets to >=44px (no min-h-[40px]/min-w-[40px])`,
			);

			const footerMatch = html.match(/<footer[\s\S]*?<\/footer>/);
			assert.ok(footerMatch, `${page.file} must render <footer>`);
			const footerHtml = footerMatch[0];
			assert.ok(
				footerHtml.includes("lg:grid-cols-3") &&
					!footerHtml.includes("lg:grid-cols-4"),
				`${page.file} <footer> must use a three-column desktop grid (lg:grid-cols-3)`,
			);
			assert.ok(
				!footerHtml.includes("footer-registry-link") &&
					!footerHtml.includes("footer-guide-link") &&
					!footerHtml.includes(locale.removedStayingGuestsHeading) &&
					!privateFulfillmentHrefRegex.test(footerHtml),
				`${page.file} <footer> must omit Staying Guests column and any /registry or /guide links`,
			);
			const contactLegalHeadingIdx = footerHtml.indexOf(
				`>${locale.contactLegalHtml}</p>`,
			);
			const footerContactIdx = footerHtml.indexOf(
				`id="footer-contact-link" href="${expectedContactHref}"`,
			);
			const footerPrivacyIdx = footerHtml.indexOf(
				`id="footer-privacy-link" href="${expectedPrivacyHref}"`,
			);
			assert.ok(
				contactLegalHeadingIdx > 0 &&
					footerContactIdx > contactLegalHeadingIdx &&
					footerPrivacyIdx > footerContactIdx,
				`${page.file} <footer> Column 3 must render localized "${locale.contactLegalText}" heading followed by #footer-contact-link and #footer-privacy-link`,
			);

			const footerLinks = footerHtml.match(/<a\b[^>]*>/g) || [];
			assert.equal(
				footerLinks.length,
				10,
				`${page.file} 3-column footer must contain exactly 10 links (2 Properties + 5 Guides + Contact + Privacy + FractalSoftware badge)`,
			);
			for (const footerAnchor of footerLinks) {
				assert.ok(
					footerAnchor.includes("min-h-[44px]"),
					`${page.file} every footer link must enforce min-h-[44px], got: ${footerAnchor}`,
				);
			}

			// Footer aria-current="page" assertions
			const footerCurrentLinks = footerLinks.filter((anchor) =>
				anchor.includes('aria-current="page"'),
			);
			if (page.activeFooterId) {
				assert.equal(
					footerCurrentLinks.length,
					1,
					`${page.file} footer must emit exactly one aria-current="page" link`,
				);
				assert.ok(
					footerCurrentLinks[0].includes(`id="${page.activeFooterId}"`),
					`${page.file} footer aria-current="page" must be on #${page.activeFooterId}`,
				);
			} else if (page.activeFooterGuideSlug) {
				assert.equal(
					footerCurrentLinks.length,
					1,
					`${page.file} footer must emit exactly one aria-current="page" link on the active guide article`,
				);
				assert.ok(
					footerCurrentLinks[0].includes(
						`santa-marta/${page.activeFooterGuideSlug}/${locale.suffix}`,
					),
					`${page.file} footer aria-current="page" must match active destination guide slug ${page.activeFooterGuideSlug}`,
				);
			} else {
				assert.equal(
					footerCurrentLinks.length,
					0,
					`${page.file} footer should have no aria-current="page" when current page is not a footer link`,
				);
			}

			// #ovf-page-config omits removed check-in ARIA keys while retaining mobile drawer ARIA keys
			const configMatch = html.match(
				/<script id="ovf-page-config" type="application\/json">([\s\S]*?)<\/script>/,
			);
			assert.ok(configMatch, `${page.file} must embed #ovf-page-config`);
			const parsedConfig = JSON.parse(configMatch[1]) as {
				i18n: Record<string, string>;
			};
			assert.equal(
				parsedConfig.i18n.navStayingGuestsOpenAriaLabel,
				undefined,
				`${page.file} #ovf-page-config must omit removed navStayingGuestsOpenAriaLabel`,
			);
			assert.equal(
				parsedConfig.i18n.navStayingGuestsCloseAriaLabel,
				undefined,
				`${page.file} #ovf-page-config must omit removed navStayingGuestsCloseAriaLabel`,
			);
			assert.ok(
				Boolean(parsedConfig.i18n.navMenuOpenAriaLabel) &&
					Boolean(parsedConfig.i18n.navMenuCloseAriaLabel),
				`${page.file} #ovf-page-config must preserve localized mobile drawer ARIA labels`,
			);
		}
	}
});

test("#336 main.js runs cleanly with zero check-in DOM elements: language dropdown disclosure, mobile drawer, navbar scroll-state sync, and /booking-success Reservation UID hydration", () => {
	const mainJsCode = fs.readFileSync(
		path.join(process.cwd(), "public", "js", "main.js"),
		"utf-8",
	);

	interface MockEvent {
		target?: MockNode;
		relatedTarget?: MockNode | null;
		key?: string;
		preventDefault?: () => void;
		stopPropagation?: () => void;
	}
	type Listener = (e?: MockEvent) => void;
	interface MockNode {
		id: string;
		tagName: string;
		hidden: boolean;
		href: string;
		textContent: string;
		classList: {
			add: (...cls: string[]) => void;
			remove: (...cls: string[]) => void;
			contains: (cls: string) => boolean;
		};
		getAttribute: (name: string) => string | null;
		setAttribute: (name: string, val: string) => void;
		removeAttribute: (name: string) => void;
		addEventListener: (evt: string, fn: Listener) => void;
		dispatchEvent: (evt: string, payload?: MockEvent) => void;
		focus: () => void;
		isFocused: () => boolean;
		resetFocus: () => void;
		appendChild: (child: MockNode) => MockNode;
		contains: (target: unknown) => boolean;
		querySelector: (sel: string) => MockNode | null;
		querySelectorAll: (sel: string) => MockNode[];
	}

	const createMockNode = (
		id: string,
		tagName: string,
		initialClasses: string[] = [],
		initialAttrs: Record<string, string> = {},
	): MockNode => {
		const classes = new Set<string>(initialClasses);
		const attrs: Record<string, string> = { ...initialAttrs };
		const listeners: Record<string, Listener[]> = {};
		const children: MockNode[] = [];
		let focused = false;
		const collectAll = (predicate: (n: MockNode) => boolean): MockNode[] => {
			const matches: MockNode[] = [];
			for (const child of children) {
				if (predicate(child)) matches.push(child);
				matches.push(...child.querySelectorAll("a").filter(predicate));
			}
			return matches;
		};
		const node: MockNode = {
			id,
			tagName,
			hidden: initialClasses.includes("hidden"),
			href: initialAttrs.href || "",
			textContent: "",
			classList: {
				add: (...cls: string[]) => {
					for (const c of cls) classes.add(c);
				},
				remove: (...cls: string[]) => {
					for (const c of cls) classes.delete(c);
				},
				contains: (cls: string) => classes.has(cls),
			},
			getAttribute: (name: string) => attrs[name] ?? null,
			setAttribute: (name: string, val: string) => {
				attrs[name] = val;
				if (name === "href") node.href = val;
			},
			removeAttribute: (name: string) => {
				delete attrs[name];
			},
			addEventListener: (evt: string, fn: Listener) => {
				listeners[evt] = listeners[evt] || [];
				listeners[evt].push(fn);
			},
			dispatchEvent: (evt: string, payload: MockEvent = {}) => {
				for (const fn of listeners[evt] || []) {
					fn({
						target: node,
						preventDefault: () => {},
						stopPropagation: () => {},
						...payload,
					});
				}
			},
			focus: () => {
				focused = true;
			},
			isFocused: () => focused,
			resetFocus: () => {
				focused = false;
			},
			appendChild: (child: MockNode) => {
				children.push(child);
				return child;
			},
			contains: (target: unknown) => {
				if (target === node) return true;
				return children.some((c) => c === target || c.contains(target));
			},
			querySelector: () => children[0] || null,
			querySelectorAll: (sel: string) =>
				sel === "a" ? collectAll((c) => c.tagName === "A") : [],
		};
		return node;
	};

	const navbar = createMockNode("navbar", "NAV", [], {
		"data-scrolled": "false",
	});

	const langToggleGroup = createMockNode("lang-toggle-group", "DIV");
	const langToggle = createMockNode("lang-toggle", "BUTTON", [], {
		type: "button",
		"aria-expanded": "false",
		"aria-controls": "lang-dropdown",
	});
	const langDropdown = createMockNode("lang-dropdown", "DIV", [
		"opacity-0",
		"invisible",
	]);
	const langOptionEn = createMockNode("lang-opt-en", "A", [], {
		href: "./index.html",
	});
	langDropdown.appendChild(langOptionEn);
	langToggleGroup.appendChild(langToggle);
	langToggleGroup.appendChild(langDropdown);

	const mobileToggle = createMockNode("mobile-nav-toggle", "BUTTON", [], {
		type: "button",
		"aria-expanded": "false",
		"aria-controls": "mobile-nav-drawer",
		"aria-label": "Ouvrir le menu de navigation",
	});
	const mobileDrawer = createMockNode("mobile-nav-drawer", "DIV", ["hidden"], {
		hidden: "",
	});
	const mobileClose = createMockNode("mobile-nav-close", "BUTTON", [], {
		type: "button",
		"aria-label": "Fermer le menu de navigation",
	});
	const drawerContactLink = createMockNode("drawer-contact", "A", [], {
		href: "../contact/fr.html",
	});
	mobileDrawer.appendChild(mobileClose);
	mobileDrawer.appendChild(drawerContactLink);
	navbar.appendChild(langToggleGroup);
	navbar.appendChild(mobileToggle);
	navbar.appendChild(mobileDrawer);

	const bookingRefBox = createMockNode("booking-reference-box", "DIV", [
		"hidden",
	]);
	const displayReservationCode = createMockNode(
		"display-reservation-code",
		"SPAN",
	);
	const btnBookingRegistry = createMockNode("btn-booking-registry", "A", [], {
		href: "../registry/fr.html",
	});
	const btnBookingGuide = createMockNode("btn-booking-guide", "A", [], {
		href: "../guide/fr.html",
	});

	const outsideNode = createMockNode("main-content", "MAIN");

	const elementsById = new Map<string, MockNode>([
		["navbar", navbar],
		["lang-toggle-group", langToggleGroup],
		["lang-toggle", langToggle],
		["lang-dropdown", langDropdown],
		["mobile-nav-toggle", mobileToggle],
		["mobile-nav-drawer", mobileDrawer],
		["mobile-nav-close", mobileClose],
		["booking-reference-box", bookingRefBox],
		["display-reservation-code", displayReservationCode],
		["btn-booking-registry", btnBookingRegistry],
		["btn-booking-guide", btnBookingGuide],
	]);

	const docListeners: Record<string, Listener[]> = {};
	const winListeners: Record<string, Listener[]> = {};
	const scriptStub = {
		async: false,
		src: "",
		parentNode: { insertBefore: () => {} },
	};

	const translations: Record<string, string> = {
		navMenuOpenAriaLabel: "Ouvrir le menu de navigation",
		navMenuCloseAriaLabel: "Fermer le menu de navigation",
	};

	const sessionStore = new Map<string, string>();
	const sandbox = {
		URL,
		URLSearchParams,
		window: {
			_paq: [] as unknown[],
			scrollY: 75,
			location: {
				search: "?code=ovf_test_uid_336",
				pathname: "/booking-success/fr.html",
			},
			addEventListener: (evt: string, fn: Listener) => {
				winListeners[evt] = winListeners[evt] || [];
				winListeners[evt].push(fn);
			},
			dispatchEvent: (evt: string, payload: MockEvent = {}) => {
				for (const fn of winListeners[evt] || []) fn(payload);
			},
			getPageConfig: () => ({
				lang: "fr",
				assetPrefix: "../",
				apiBase: "../api/",
			}),
			t: (key: string, _params?: Record<string, string>, fallback?: string) =>
				translations[key] ?? fallback ?? key,
		},
		document: {
			documentElement: { lang: "fr" },
			createElement: () => ({ ...scriptStub }),
			getElementsByTagName: () => [scriptStub],
			getElementById: (id: string) => elementsById.get(id) || null,
			addEventListener: (evt: string, fn: Listener) => {
				docListeners[evt] = docListeners[evt] || [];
				docListeners[evt].push(fn);
			},
			dispatchEvent: (evt: string, payload: MockEvent = {}) => {
				for (const fn of docListeners[evt] || []) {
					fn({
						preventDefault: () => {},
						stopPropagation: () => {},
						...payload,
					});
				}
			},
		},
		localStorage: { getItem: () => null, setItem: () => {} },
		sessionStorage: {
			getItem: (key: string) => sessionStore.get(key) ?? null,
			setItem: (key: string, value: string) => {
				sessionStore.set(key, value);
			},
		},
		console,
	};

	vm.createContext(sandbox);
	vm.runInContext(mainJsCode, sandbox);
	sandbox.document.dispatchEvent("DOMContentLoaded");

	// 1. data-scrolled syncs on DOMContentLoaded and scroll
	assert.equal(navbar.getAttribute("data-scrolled"), "true");
	sandbox.window.scrollY = 0;
	sandbox.window.dispatchEvent("scroll");
	assert.equal(navbar.getAttribute("data-scrolled"), "false");

	// 2. Language dropdown disclosure: click toggle, Escape focus restoration, outside click, focusout
	langToggle.dispatchEvent("click");
	assert.equal(langToggle.getAttribute("aria-expanded"), "true");
	assert.equal(langDropdown.classList.contains("opacity-100"), true);
	assert.equal(langDropdown.classList.contains("visible"), true);

	langToggle.resetFocus();
	let escapePrevented = false;
	sandbox.document.dispatchEvent("keydown", {
		key: "Escape",
		preventDefault: () => {
			escapePrevented = true;
		},
	});
	assert.equal(escapePrevented, true);
	assert.equal(langToggle.getAttribute("aria-expanded"), "false");
	assert.equal(langDropdown.classList.contains("invisible"), true);
	assert.equal(
		langToggle.isFocused(),
		true,
		"Escape must restore focus to #lang-toggle",
	);

	langToggle.dispatchEvent("click");
	langToggle.resetFocus();
	sandbox.document.dispatchEvent("click", { target: outsideNode });
	assert.equal(langToggle.getAttribute("aria-expanded"), "false");
	assert.equal(langToggle.isFocused(), false);

	langToggle.dispatchEvent("click");
	langToggleGroup.dispatchEvent("focusout", { relatedTarget: langOptionEn });
	assert.equal(langToggle.getAttribute("aria-expanded"), "true");
	langToggleGroup.dispatchEvent("focusout", { relatedTarget: outsideNode });
	assert.equal(langToggle.getAttribute("aria-expanded"), "false");

	// 3. Mobile navigation drawer: open toggle, Escape + focus restoration, close button, outside click
	mobileClose.resetFocus();
	mobileToggle.dispatchEvent("click");
	assert.equal(mobileToggle.getAttribute("aria-expanded"), "true");
	assert.equal(mobileDrawer.classList.contains("hidden"), false);
	assert.equal(mobileDrawer.hidden, false);
	assert.equal(
		mobileToggle.getAttribute("aria-label"),
		"Fermer le menu de navigation",
	);
	assert.equal(mobileClose.isFocused(), true);

	mobileToggle.resetFocus();
	escapePrevented = false;
	sandbox.document.dispatchEvent("keydown", {
		key: "Escape",
		preventDefault: () => {
			escapePrevented = true;
		},
	});
	assert.equal(escapePrevented, true);
	assert.equal(mobileToggle.getAttribute("aria-expanded"), "false");
	assert.equal(mobileDrawer.classList.contains("hidden"), true);
	assert.equal(mobileDrawer.hidden, true);
	assert.equal(
		mobileToggle.getAttribute("aria-label"),
		"Ouvrir le menu de navigation",
	);
	assert.equal(
		mobileToggle.isFocused(),
		true,
		"Escape must restore focus to #mobile-nav-toggle",
	);

	mobileToggle.dispatchEvent("click");
	mobileToggle.resetFocus();
	mobileClose.dispatchEvent("click");
	assert.equal(mobileToggle.getAttribute("aria-expanded"), "false");
	assert.equal(mobileToggle.isFocused(), true);

	mobileToggle.dispatchEvent("click");
	mobileToggle.resetFocus();
	sandbox.document.dispatchEvent("click", { target: outsideNode });
	assert.equal(mobileToggle.getAttribute("aria-expanded"), "false");
	assert.equal(mobileToggle.isFocused(), false);

	// 4. /booking-success Reservation UID hydration preserves token-carrying links & language query propagation
	assert.equal(bookingRefBox.classList.contains("hidden"), false);
	assert.equal(displayReservationCode.textContent, "ovf_test_uid_336");
	assert.equal(
		btnBookingRegistry.getAttribute("href"),
		"../registry/fr.html?code=ovf_test_uid_336",
	);
	assert.equal(
		btnBookingGuide.getAttribute("href"),
		"../guide/fr.html?code=ovf_test_uid_336",
	);
	assert.equal(
		langOptionEn.getAttribute("href"),
		"./index.html?code=ovf_test_uid_336",
	);
});

test("#330 #337 sunlit coastal hero declutter, honest CTA hierarchy, warmer AA coastal coral token, and zero returning-Guest fulfillment cue across all 6 locales", () => {
	const distDir = path.join(process.cwd(), "dist");

	// 1. Verify WCAG AA+ warmer deep coastal coral token math (>= 4.5:1 against #FFFFFF)
	const relativeLuminance = (hex: string): number => {
		const clean = hex.replace("#", "");
		const channels = [0, 2, 4].map((i) => {
			const c = Number.parseInt(clean.slice(i, i + 2), 16) / 255;
			return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
		});
		return 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2];
	};
	const contrastWithWhite = (hex: string): number =>
		(1 + 0.05) / (relativeLuminance(hex) + 0.05);

	assert.ok(
		contrastWithWhite("#C43E29") >= 4.5,
		"coral-horizon-accessible (#C43E29) must meet WCAG AA (>= 4.5:1) with #FFFFFF",
	);
	assert.ok(
		contrastWithWhite("#A8321E") >= 4.5,
		"coral-horizon-ink (#A8321E) must meet WCAG AA (>= 4.5:1) with #FFFFFF",
	);
	assert.ok(
		contrastWithWhite("#8F2716") >= 7.0,
		"coral-horizon-ink-deep (#8F2716) must meet WCAG AAA (>= 7.0:1) with #FFFFFF",
	);

	// 2. Verify token registration in src/style.css, compiled CSS, DESIGN.md, and .impeccable metadata (zero #DC2626 alert-red)
	const styleCss = fs.readFileSync(
		path.join(process.cwd(), "src", "style.css"),
		"utf-8",
	);
	assert.ok(
		styleCss.includes("--color-coral-horizon-accessible: #c43e29;") &&
			styleCss.includes("--color-coral-horizon-ink: #a8321e;") &&
			styleCss.includes("--color-coral-horizon-ink-deep: #8f2716;") &&
			!/#dc2626/i.test(styleCss),
		"src/style.css must register warmer coastal coral tokens (#c43e29 / #a8321e / #8f2716) and remove #dc2626",
	);

	const compiledCss = fs.readFileSync(
		path.join(distDir, "css", "style.css"),
		"utf-8",
	);
	assert.ok(
		/#c43e29/i.test(compiledCss) &&
			/#a8321e/i.test(compiledCss) &&
			!/#dc2626/i.test(compiledCss),
		"dist/css/style.css must compile warmer coastal coral tokens and omit #dc2626",
	);

	for (const docRelPath of [
		"DESIGN.md",
		path.join(".impeccable", "design.json"),
		path.join(".impeccable", "design-full.md"),
	]) {
		const docContent = fs.readFileSync(
			path.join(process.cwd(), docRelPath),
			"utf-8",
		);
		assert.ok(
			/#C43E29/i.test(docContent) &&
				/#A8321E/i.test(docContent) &&
				/#8F2716/i.test(docContent) &&
				!/#DC2626/i.test(docContent),
			`${docRelPath} must sync warmer coastal coral tokens (#C43E29 / #A8321E / #8F2716) and omit #DC2626`,
		);
	}

	// 3. Verify compiled Hero semantics across all 6 locales
	const locales = [
		{ lang: "en" as const, file: "index.html", suffix: "" },
		{ lang: "es" as const, file: "es.html", suffix: "es.html" },
		{ lang: "fr" as const, file: "fr.html", suffix: "fr.html" },
		{ lang: "it" as const, file: "it.html", suffix: "it.html" },
		{ lang: "de" as const, file: "de.html", suffix: "de.html" },
		{ lang: "ja" as const, file: "ja.html", suffix: "ja.html" },
	];

	for (const locale of locales) {
		const html = fs.readFileSync(path.join(distDir, locale.file), "utf-8");
		const headerMatch = html.match(/<header[\s\S]*?<\/header>/);
		assert.ok(headerMatch, `${locale.file} must render Hero <header>`);
		const headerHtml = headerMatch[0];

		// 3a. Sunlit focal framing & lightened upper coastal scrim preserving IMAGES.home.hero
		assert.ok(
			headerHtml.includes('src="./images/home-hero.webp"') &&
				headerHtml.includes("object-[center_35%]") &&
				headerHtml.includes("from-sky-950/35"),
			`${locale.file} Hero must keep IMAGES.home.hero with open coastal framing and lighter upper scrim`,
		);

		// 3b. Static location eyebrow and value-proposition render as clean unboxed typography (no pill borders/fills)
		const locationMatch = headerHtml.match(/<div[^>]*id="hero-location"[^>]*>/);
		assert.ok(locationMatch, `${locale.file} must render #hero-location`);
		assert.ok(
			!locationMatch[0].includes("rounded-full") &&
				!locationMatch[0].includes("border") &&
				!locationMatch[0].includes("backdrop-blur") &&
				!locationMatch[0].includes("bg-slate"),
			`${locale.file} #hero-location must be unboxed typography without pill borders or frosted fills`,
		);

		const valuePropMatch = headerHtml.match(
			/<p[^>]*id="hero-value-prop"[^>]*>/,
		);
		assert.ok(valuePropMatch, `${locale.file} must render #hero-value-prop`);
		assert.ok(
			!valuePropMatch[0].includes("rounded-full") &&
				!valuePropMatch[0].includes("border") &&
				!valuePropMatch[0].includes("backdrop-blur") &&
				!valuePropMatch[0].includes("bg-slate"),
			`${locale.file} #hero-value-prop must be unboxed typography without pill borders or frosted fills`,
		);

		// 3c. Scroll-only Hero CTA omits misleading calendar icon and uses directional scroll icon + warmer AA coral token
		const heroPrimaryCtaMatch = headerHtml.match(
			/<a[^>]*href="#properties"[^>]*>[\s\S]*?<\/a>/,
		);
		assert.ok(
			heroPrimaryCtaMatch,
			`${locale.file} must render scroll-only Hero CTA linking to #properties`,
		);
		assert.ok(
			heroPrimaryCtaMatch[0].includes("bg-coral-horizon-accessible") &&
				!heroPrimaryCtaMatch[0].includes("lucide-calendar") &&
				heroPrimaryCtaMatch[0].includes("lucide-arrow-down"),
			`${locale.file} scroll-only Hero CTA must omit misleading calendar icon, use arrow-down, and apply bg-coral-horizon-accessible`,
		);

		// 3d. Direct quick-entry links to Property 1707 & 1606 remain clickable relative-path pills
		assert.ok(
			headerHtml.includes(
				`id="hero-direct-1707" href="./Oceanview1707/${locale.suffix}"`,
			) &&
				headerHtml.includes(
					`id="hero-direct-1606" href="./Oceanview1606/${locale.suffix}"`,
				),
			`${locale.file} Hero must preserve relative-path #hero-direct-1707 and #hero-direct-1606 links`,
		);

		// 3e. #337 Hero omits returning-Guest check-in cue and any links to private /registry or /guide routes
		assert.ok(
			!headerHtml.includes("hero-returning-guest-cue") &&
				!headerHtml.includes("hero-returning-registry-link") &&
				!headerHtml.includes("hero-returning-guide-link") &&
				!/<a\b[^>]*href=["'][^"']*(?:^|\/)(?:registry|guide)(?:\/|$|\?|#)/i.test(
					headerHtml,
				),
			`${locale.file} Hero must omit #hero-returning-guest-cue and all links to /registry or /guide`,
		);
	}
});

test("#331 Homepage renders editorial tabular-nums Nightly Rates with localized reference-currency cue, muted proximity tags, uniform >=44px Guest Review footers, and verified Location map link across all 6 locales", () => {
	const distDir = path.join(process.cwd(), "dist");
	const htmlEntities: Record<string, string> = {
		"&#x27;": "'",
		"&quot;": '"',
		"&amp;": "&",
	};
	const decodeHtmlEntities = (s: string) =>
		s.replace(
			/&#x27;|&quot;|&amp;/g,
			(entity) => htmlEntities[entity] ?? entity,
		);
	const homeSource = fs.readFileSync(
		path.join(process.cwd(), "src", "pages", "Home.tsx"),
		"utf-8",
	);
	assert.ok(
		!homeSource.includes('className="'),
		"Home.tsx must remain 100% style-agnostic with zero inline className strings",
	);

	for (const lang of LANGUAGES) {
		const homeFile = lang === "en" ? "index.html" : `${lang}.html`;
		const html = fs.readFileSync(path.join(distDir, homeFile), "utf-8");
		const t = dict[lang];

		// 1. Editorial tabular-nums Nightly Rates (no solid dark pill container) + sibling localized reference-currency cue
		for (const property of [
			{
				id: "1707",
				cardId: "home-card-1707",
				badgeId: "home-rate-badge-1707",
				expectedRate: t.homeCard1707Rate,
				airbnbUrl: AIRBNB_URLS["1707"],
				reviewId: "home-review-1707-2",
				author: "Julian Alfredo",
				datePublished: "2026-10-11",
			},
			{
				id: "1606",
				cardId: "home-card-1606",
				badgeId: "home-rate-badge-1606",
				expectedRate: t.homeCard1606Rate,
				airbnbUrl: AIRBNB_URLS["1606"],
				reviewId: "home-review-1606-1",
				author: "Lina Marcela",
				datePublished: "2026-04-06",
			},
		] as const) {
			const cardMatch = html.match(
				new RegExp(
					`<article[^>]*id="${property.cardId}"[^>]*>[\\s\\S]*?<\\/article>`,
				),
			);
			assert.ok(cardMatch, `${homeFile} must render #${property.cardId}`);
			const cardHtml = cardMatch[0];

			const rateSpanMatch = cardHtml.match(
				new RegExp(
					`<span[^>]*id="${property.badgeId}"[^>]*>([\\s\\S]*?)<\\/span>`,
				),
			);
			assert.ok(
				rateSpanMatch,
				`${homeFile} #${property.cardId} must preserve #${property.badgeId} hydration target`,
			);
			const rateSpanTag = rateSpanMatch[0];
			assert.equal(
				rateSpanMatch[1],
				property.expectedRate,
				`${homeFile} #${property.badgeId} innerText must remain exact for live hydration`,
			);
			assert.ok(
				rateSpanTag.includes("tabular-nums"),
				`${homeFile} #${property.badgeId} must use tabular-nums editorial pricing typography`,
			);
			assert.ok(
				!rateSpanTag.includes("bg-slate-900") &&
					!rateSpanTag.includes("bg-stone-900") &&
					!rateSpanTag.includes("rounded-full"),
				`${homeFile} #${property.badgeId} must not use solid dark pill button styling`,
			);
			assert.ok(
				decodeHtmlEntities(cardHtml).includes(t.homeRateCurrencyNote),
				`${homeFile} #${property.cardId} must display localized reference-currency cue`,
			);
			assert.ok(
				!cardHtml.includes("Settlement Currency"),
				`${homeFile} #${property.cardId} must not leak internal jargon "Settlement Currency"`,
			);

			// 2. Uniform 2-row Guest Review footer: metadata row separated from dedicated >=44px Airbnb verification row
			const reviewMatch = cardHtml.match(
				new RegExp(
					`<div[^>]*id="${property.reviewId}"[^>]*>[\\s\\S]*?<\\/blockquote>([\\s\\S]*?)<\\/div><\\/div><\\/div><a href=`,
				),
			);
			assert.ok(
				reviewMatch,
				`${homeFile} #${property.cardId} must render curated review #${property.reviewId}`,
			);
			const reviewFooterHtml = reviewMatch[1];
			assert.ok(
				decodeHtmlEntities(reviewFooterHtml).includes(property.author) &&
					decodeHtmlEntities(reviewFooterHtml).includes(
						t.reviewVerifiedGuest,
					) &&
					new RegExp(
						`<time[^>]*\\bdatetime="${property.datePublished}"`,
						"i",
					).test(reviewFooterHtml),
				`${homeFile} #${property.reviewId} footer must include Latin-script author, Verified Guest badge, and <time datetime>`,
			);

			const airbnbLinkMatch = reviewFooterHtml.match(
				/<a[^>]*target="_blank"[^>]*>[\s\S]*?<\/a>/,
			);
			assert.ok(
				airbnbLinkMatch,
				`${homeFile} #${property.reviewId} must render outbound Airbnb verification link`,
			);
			assert.ok(
				airbnbLinkMatch[0].includes(`href="${property.airbnbUrl}"`) &&
					airbnbLinkMatch[0].includes('rel="noopener noreferrer"') &&
					airbnbLinkMatch[0].includes("min-h-[44px]") &&
					decodeHtmlEntities(airbnbLinkMatch[0]).includes(t.reviewViewOnAirbnb),
				`${homeFile} #${property.reviewId} Airbnb verification link must point to Property ${property.id} listing with >=44px tap target height`,
			);
			// Metadata row and Airbnb link row must be distinct sibling containers (not sharing a single flex-wrap row)
			assert.ok(
				/<time[^>]*>[\s\S]*?<\/time><\/div><div[^>]*><a[^>]*href=/.test(
					reviewFooterHtml,
				),
				`${homeFile} #${property.reviewId} must separate author/date metadata row from the dedicated Airbnb verification link row`,
			);
		}

		// 3. Muted non-button proximity tags + verified BUILDING_MAP_URL link in #location
		const locationMatch = html.match(
			/<section[^>]*id="location"[^>]*>[\s\S]*?<\/section>/,
		);
		assert.ok(locationMatch, `${homeFile} must render #location`);
		const locationHtml = locationMatch[0];

		for (const landmark of PLAYA_SALGUERO_LANDMARKS) {
			const proximityText = t[landmark.proximityKey];
			const escapedProximity = proximityText.replace(
				/[.*+?^${}()|[\]\\]/g,
				"\\$&",
			);
			const badgeMatch = locationHtml.match(
				new RegExp(`<span[^>]*>${escapedProximity}<\\/span>`),
			);
			assert.ok(
				badgeMatch,
				`${homeFile} #location must render proximity tag for ${landmark.id}`,
			);
			assert.ok(
				badgeMatch[0].includes("bg-slate-100") &&
					badgeMatch[0].includes("text-slate-700") &&
					!badgeMatch[0].includes("bg-slate-900") &&
					!badgeMatch[0].includes("rounded-full"),
				`${homeFile} #location proximity tag for ${landmark.id} must be a muted non-button tag (not a solid dark pill)`,
			);
		}

		assert.ok(
			locationHtml.includes('id="home-location-map-link"') &&
				locationHtml.includes(`href="${BUILDING_MAP_URL}"`) &&
				locationHtml.includes(t.homeLocationMapLink),
			`${homeFile} #location must render localized direct map link to BUILDING_MAP_URL`,
		);

		// 4. Zero untranslated English leakage for #331 strings in non-English locales
		if (lang !== "en") {
			assert.ok(
				!html.includes("Paid in COP") && !html.includes("View on Google Maps"),
				`${homeFile} must not leak untranslated English #331 strings`,
			);
		}
	}
});

test("#332 Distilled 3-pillar Direct Reservation & Host showcase, warm Santa Marta host voice, actionable comparison CTAs, arrival-first #faq-6 default, and Safari WebKit marker suppression across all 6 locales", () => {
	const distDir = path.join(process.cwd(), "dist");
	const locales: Array<{
		code: keyof typeof dict;
		file: string;
		suffix: string;
		expectedOwnerBadge: string;
		expectedPillar1Title: string;
		expectedCta1707: string;
		expectedCta1606: string;
		expectedHostLocationToken: string;
	}> = [
		{
			code: "en",
			file: "index.html",
			suffix: "",
			expectedOwnerBadge:
				"Owner &amp; Local Host: OceanViewFlats — Manuel Enrique Herrera",
			expectedPillar1Title: "Direct-Owner Rates — No Platform Markups",
			expectedCta1707: "Check Dates for Oceanview 1707",
			expectedCta1606: "Check Dates for Oceanview 1606",
			expectedHostLocationToken: "Edificio Salguero Sunset",
		},
		{
			code: "es",
			file: "es.html",
			suffix: "es.html",
			expectedOwnerBadge:
				"Propietario y Anfitrión Local: OceanViewFlats — Manuel Enrique Herrera",
			expectedPillar1Title: "Tarifa Directa del Propietario — Sin Recargos",
			expectedCta1707: "Ver Fechas en Oceanview 1707",
			expectedCta1606: "Ver Fechas en Oceanview 1606",
			expectedHostLocationToken: "Edificio Salguero Sunset",
		},
		{
			code: "fr",
			file: "fr.html",
			suffix: "fr.html",
			expectedOwnerBadge:
				"Propriétaire &amp; Hôte Local: OceanViewFlats — Manuel Enrique Herrera",
			expectedPillar1Title:
				"Tarifs Propriétaire en Direct — Sans Frais de Plateforme",
			expectedCta1707: "Voir les Dates — Oceanview 1707",
			expectedCta1606: "Voir les Dates — Oceanview 1606",
			expectedHostLocationToken: "Edificio Salguero Sunset",
		},
		{
			code: "it",
			file: "it.html",
			suffix: "it.html",
			expectedOwnerBadge:
				"Proprietario e Host Locale: OceanViewFlats — Manuel Enrique Herrera",
			expectedPillar1Title: "Tariffe Dirette dal Proprietario — Zero Ricarichi",
			expectedCta1707: "Verifica Date — Oceanview 1707",
			expectedCta1606: "Verifica Date — Oceanview 1606",
			expectedHostLocationToken: "Edificio Salguero Sunset",
		},
		{
			code: "de",
			file: "de.html",
			suffix: "de.html",
			expectedOwnerBadge:
				"Eigentümer &amp; Lokaler Gastgeber: OceanViewFlats — Manuel Enrique Herrera",
			expectedPillar1Title:
				"Direktpreise vom Eigentümer — Ohne Plattformaufschläge",
			expectedCta1707: "Termine prüfen — Oceanview 1707",
			expectedCta1606: "Termine prüfen — Oceanview 1606",
			expectedHostLocationToken: "Edificio Salguero Sunset",
		},
		{
			code: "ja",
			file: "ja.html",
			suffix: "ja.html",
			expectedOwnerBadge:
				"オーナー兼現地ホスト: OceanViewFlats — Manuel Enrique Herrera",
			expectedPillar1Title: "オーナー直接料金・仲介サイト上乗せなし",
			expectedCta1707: "Oceanview 1707 の空室を確認",
			expectedCta1606: "Oceanview 1606 の空室を確認",
			expectedHostLocationToken: "Edificio Salguero Sunset",
		},
	];

	const escapeHtml = (value: string) => value.replace(/&/g, "&amp;");

	for (const locale of locales) {
		const html = fs.readFileSync(path.join(distDir, locale.file), "utf-8");
		const t = dict[locale.code];

		// 1. Consolidated Direct Reservation & Host showcase has exactly 3 proof pillars paired with #home-host-card
		const directMatch = html.match(
			/<section[^>]*id="why-book-direct"[^>]*>[\s\S]*?<\/section>/,
		);
		assert.ok(directMatch, `${locale.file} must render #why-book-direct`);
		const directHtml = directMatch[0];

		const trustStripMatch = directHtml.match(
			/<ul[^>]*id="home-trust-strip"[^>]*>([\s\S]*?)<\/ul>/,
		);
		assert.ok(
			trustStripMatch,
			`${locale.file} #why-book-direct must render <ul id="home-trust-strip">`,
		);
		const pillarItems = trustStripMatch[1].match(/<li\b[^>]*>/g) || [];
		assert.equal(
			pillarItems.length,
			3,
			`${locale.file} #home-trust-strip must contain exactly 3 concrete proof pillars (savings, Mercado Pago, RNT)`,
		);
		assert.ok(
			trustStripMatch[1].includes(locale.expectedPillar1Title) &&
				trustStripMatch[1].includes("Mercado Pago") &&
				trustStripMatch[1].includes("PSE") &&
				trustStripMatch[1].includes("Efecty") &&
				trustStripMatch[1].includes("RNT #268781") &&
				trustStripMatch[1].includes("RNT #270413"),
			`${locale.file} #home-trust-strip must present direct-owner savings, Mercado Pago rails (Card/PSE/Efecty), and Colombian RNT licensing`,
		);

		// Old 4-card boilerplate grid is removed
		const directLists = directHtml.match(/<ul\b[^>]*>/g) || [];
		assert.equal(
			directLists.length,
			1,
			`${locale.file} #why-book-direct must consolidate the 4-card grid and trust strip into one <ul>`,
		);

		// 2. Host card uses warm Santa Marta copy & owner badge without cold "Data Controller" jargon, while keeping hostDataControllerLabel intact for schema/property pages
		const hostCardMatch = directHtml.match(
			/<div[^>]*id="home-host-card"[^>]*>[\s\S]*?<\/section>/,
		);
		assert.ok(hostCardMatch, `${locale.file} must render #home-host-card`);
		const hostCardHtml = hostCardMatch[0];
		assert.ok(
			hostCardHtml.includes(locale.expectedOwnerBadge) &&
				hostCardHtml.includes(locale.expectedHostLocationToken) &&
				hostCardHtml.includes("268781") &&
				hostCardHtml.includes("270413") &&
				hostCardHtml.includes('id="home-host-whatsapp"') &&
				hostCardHtml.includes('id="home-host-email"') &&
				hostCardHtml.includes("bg-slate-700"),
			`${locale.file} #home-host-card must render warm owner attribution, Santa Marta host bio, both RNTs, avatar placeholder, and WhatsApp/Email actions`,
		);
		const visibleHostText = hostCardHtml.replace(/<[^>]+>/g, " ");
		assert.ok(
			!visibleHostText.includes(escapeHtml(t.hostDataControllerLabel)) &&
				!visibleHostText.includes("Data Controller"),
			`${locale.file} #home-host-card must omit cold "Data Controller" phrasing from the visible homepage host card`,
		);
		assert.ok(
			t.hostDataControllerLabel.length > 0,
			`${locale.code} dict must preserve hostDataControllerLabel for JSON-LD and Property pages`,
		);

		// Generic template copy replaced on homepage
		assert.ok(
			!html.includes("Combine productivity with paradise") &&
				!html.includes("perfect blend of luxury and comfort") &&
				!html.includes("With years of experience in hospitality"),
			`${locale.file} must replace generic template copy in property descriptions and host bio`,
		);

		// 3. Actionable localized relative-path Property CTAs directly beneath #compare-properties
		const compareMatch = html.match(
			/<section[^>]*id="compare-properties"[^>]*>[\s\S]*?<\/section>/,
		);
		assert.ok(compareMatch, `${locale.file} must render #compare-properties`);
		const compareHtml = compareMatch[0];
		assert.ok(
			compareHtml.includes(
				`id="home-compare-cta-1707" href="./Oceanview1707/${locale.suffix}"`,
			) &&
				compareHtml.includes(locale.expectedCta1707) &&
				compareHtml.includes(
					`id="home-compare-cta-1606" href="./Oceanview1606/${locale.suffix}"`,
				) &&
				compareHtml.includes(locale.expectedCta1606),
			`${locale.file} #compare-properties must render localized relative-path CTA buttons for Property 1707 and Property 1606`,
		);

		// 4. Arrival-first #faq-6 default-open, #faq-3 collapsed, and Safari WebKit marker suppression on every homepage FAQ summary
		const faqMatch = html.match(
			/<section[^>]*id="faq"[^>]*>[\s\S]*?<\/section>/,
		);
		assert.ok(faqMatch, `${locale.file} must render #faq`);
		const faqHtml = faqMatch[0];
		const faqSummaryTags = faqHtml.match(/<summary[^>]*>/g) || [];
		assert.equal(faqSummaryTags.length, 6);
		for (const summaryTag of faqSummaryTags) {
			assert.ok(
				summaryTag.includes("[&amp;::-webkit-details-marker]:hidden") ||
					summaryTag.includes("[&::-webkit-details-marker]:hidden"),
				`${locale.file} every homepage FAQ <summary> must include WebKit disclosure-marker suppression`,
			);
		}
		const openAttrRegex = /\bopen(?:="")?(?=\s|>)/;
		const faq3OpenTag = faqHtml.match(/<details[^>]*id="faq-3"[^>]*>/)?.[0];
		const faq6OpenTag = faqHtml.match(/<details[^>]*id="faq-6"[^>]*>/)?.[0];
		assert.ok(faq3OpenTag && !openAttrRegex.test(faq3OpenTag));
		assert.ok(faq6OpenTag && openAttrRegex.test(faq6OpenTag));

		// Zero untranslated English #332 copy on non-English locales
		if (locale.code !== "en") {
			for (const enSnippet of [
				"Direct-Owner Rates",
				"Owner &amp; Local Host",
				"Check Dates for Oceanview 1707",
				"Check Dates for Oceanview 1606",
			]) {
				assert.ok(
					!html.includes(enSnippet),
					`${locale.file} must not leak untranslated English string "${enSnippet}"`,
				);
			}
		}
	}

	// 5. Compiled CSS emits ::-webkit-details-marker suppression rule for Safari parity
	const compiledCss = fs.readFileSync(
		path.join(distDir, "css", "style.css"),
		"utf-8",
	);
	assert.ok(
		compiledCss.includes("::-webkit-details-marker"),
		"dist/css/style.css must emit ::-webkit-details-marker suppression for Safari FAQ disclosure parity",
	);
});

test("#333 Homepage renders warm sunlit coastal bottom CTA with Coral Horizon light wash, single-row desktop layout contract, >=44px tap targets, honest icon, and registered token hygiene across all 6 locales", () => {
	const distDir = path.join(process.cwd(), "dist");

	// 1. Registered design-token hygiene: zero ad-hoc raw hex color utilities across CHROME_THEME, HOME_CARD_THEMES, and HOME_THEME
	const rawHexUtilityRegex = /\[#[0-9a-fA-F]{3,8}\]/;
	const inspectThemeTokens = (
		name: string,
		obj: Record<string, unknown>,
	): void => {
		for (const [key, value] of Object.entries(obj)) {
			if (typeof value === "string") {
				assert.ok(
					!rawHexUtilityRegex.test(value),
					`${name}.${key} must use registered @theme tokens instead of raw hex utilities, got: ${value}`,
				);
			} else if (value && typeof value === "object") {
				inspectThemeTokens(`${name}.${key}`, value as Record<string, unknown>);
			}
		}
	};
	inspectThemeTokens("CHROME_THEME", CHROME_THEME);
	inspectThemeTokens("HOME_CARD_THEMES", HOME_CARD_THEMES);
	inspectThemeTokens("HOME_THEME", HOME_THEME);

	// Semantic Trust & Property Temperature rule: no decorative amber-wash or Property 1606 travertine tokens on global HOME_THEME keys
	for (const [key, value] of Object.entries(HOME_THEME)) {
		if (typeof value === "string") {
			assert.ok(
				!value.includes("amber-wash") && !value.includes("travertine-"),
				`HOME_THEME.${key} must not use decorative Keycard Amber or Property 1606 travertine tokens on global homepage elements, got: ${value}`,
			);
		}
	}

	// 2. Verify Home.tsx remains 100% style-agnostic with zero inline className strings and zero React client hooks
	const homeSource = fs.readFileSync(
		path.join(process.cwd(), "src", "pages", "Home.tsx"),
		"utf-8",
	);
	assert.ok(
		!homeSource.includes('className="') &&
			!/\buse(?:State|Effect|Ref|Memo|Callback|Reducer|Context)\b/.test(
				homeSource,
			),
		"Home.tsx must remain 100% style-agnostic with zero inline className strings and zero React hooks",
	);

	// 3. Verify WCAG AA+ contrast math for Coral Horizon light-wash bottom CTA surface & button pairs
	const relativeLuminance = (hex: string): number => {
		const clean = hex.replace("#", "");
		const channels = [0, 2, 4].map((i) => {
			const c = Number.parseInt(clean.slice(i, i + 2), 16) / 255;
			return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
		});
		return 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2];
	};
	const contrastRatio = (fg: string, bg: string): number => {
		const l1 = Math.max(relativeLuminance(fg), relativeLuminance(bg));
		const l2 = Math.min(relativeLuminance(fg), relativeLuminance(bg));
		return (l1 + 0.05) / (l2 + 0.05);
	};
	// Worst-case warm surface stop (#FEF2F2 coral-wash) vs headline (#0F172A) and body (#475569)
	assert.ok(contrastRatio("#0F172A", "#FEF2F2") >= 7.0);
	assert.ok(contrastRatio("#475569", "#FEF2F2") >= 4.5);
	// Primary Coral Horizon button (#FFFFFF on #C43E29), Secondary Contact (#0F172A on #FFFFFF), WhatsApp (#047857 on #FFFFFF and #ECFDF5)
	assert.ok(contrastRatio("#FFFFFF", "#C43E29") >= 4.5);
	assert.ok(contrastRatio("#0F172A", "#FFFFFF") >= 7.0);
	assert.ok(contrastRatio("#047857", "#FFFFFF") >= 4.5);
	assert.ok(contrastRatio("#047857", "#ECFDF5") >= 4.5);

	// 4. Verify compiled bottom CTA surface, single-row desktop layout contract, honest icon, and zero raw hex utilities across all 6 locales
	for (const lang of LANGUAGES) {
		const homeFile = lang === "en" ? "index.html" : `${lang}.html`;
		const suffix = lang === "en" ? "" : `${lang}.html`;
		const html = fs.readFileSync(path.join(distDir, homeFile), "utf-8");
		const t = dict[lang];

		const ctaMatch = html.match(
			/<section[^>]*id="cta"[^>]*>[\s\S]*?<\/section>/,
		);
		assert.ok(ctaMatch, `${homeFile} must render <section id="cta">`);
		const ctaHtml = ctaMatch[0];

		// Warm sunlit coastal surface using Coral Horizon light wash, visually distinct from dark global Footer below
		const ctaCardMatch = ctaHtml.match(/<div[^>]*id="home-cta-card"[^>]*>/);
		assert.ok(ctaCardMatch, `${homeFile} must render #home-cta-card`);
		assert.ok(
			ctaCardMatch[0].includes("bg-pure-white") &&
				ctaCardMatch[0].includes("from-coral-wash") &&
				ctaCardMatch[0].includes("border-coral-horizon/25") &&
				ctaCardMatch[0].includes("text-deep-caribbean-slate") &&
				!ctaCardMatch[0].includes("bg-travertine-mist") &&
				!ctaCardMatch[0].includes("amber-wash") &&
				!ctaCardMatch[0].includes("border-travertine-border") &&
				!ctaCardMatch[0].includes("bg-slate-900") &&
				!ctaCardMatch[0].includes("border-slate-800"),
			`${homeFile} #home-cta-card must render on Coral Horizon light wash distinct from dark Footer without decorative amber-wash or Property 1606 travertine tokens`,
		);

		// Wide enough content wrapper for single balanced desktop row (not cramped max-w-3xl)
		assert.ok(
			ctaHtml.includes("max-w-5xl"),
			`${homeFile} #cta content wrapper must allocate max-w-5xl so all 3 action pills align on a single desktop row`,
		);

		// Actions row & 3 buttons: primary coral CTA (no misleading calendar icon), secondary Contact, secondary WhatsApp
		const primaryBtn = ctaHtml.match(
			/<a[^>]*id="home-cta-primary"[^>]*>[\s\S]*?<\/a>/,
		);
		const contactBtn = ctaHtml.match(
			/<a[^>]*id="home-cta-contact"[^>]*>[\s\S]*?<\/a>/,
		);
		const whatsappBtn = ctaHtml.match(
			/<a[^>]*id="home-cta-whatsapp"[^>]*>[\s\S]*?<\/a>/,
		);
		assert.ok(
			primaryBtn && contactBtn && whatsappBtn,
			`${homeFile} #cta must render #home-cta-primary, #home-cta-contact, and #home-cta-whatsapp`,
		);

		assert.ok(
			primaryBtn[0].includes('href="#properties"') &&
				primaryBtn[0].includes("bg-coral-horizon-accessible") &&
				primaryBtn[0].includes("min-h-[48px]") &&
				!primaryBtn[0].includes("lucide-calendar") &&
				primaryBtn[0].includes("lucide-arrow-up") &&
				primaryBtn[0].includes(t.homeHeroCta),
			`${homeFile} #home-cta-primary must link to #properties on coral-horizon-accessible with >=48px target, arrow-up icon, and zero calendar icon`,
		);

		assert.ok(
			contactBtn[0].includes(`href="./contact/${suffix}"`) &&
				contactBtn[0].includes("bg-pure-white") &&
				contactBtn[0].includes("text-deep-caribbean-slate") &&
				contactBtn[0].includes("min-h-[48px]") &&
				contactBtn[0].includes(t.navContact),
			`${homeFile} #home-cta-contact must use relative localized contact link and high-contrast light pill with >=48px target`,
		);

		assert.ok(
			whatsappBtn[0].includes("bg-pure-white") &&
				whatsappBtn[0].includes("text-clearance-emerald-deep") &&
				whatsappBtn[0].includes("min-h-[48px]") &&
				whatsappBtn[0].includes(t.homeCtaWhatsApp) &&
				!whatsappBtn[0].includes("bg-emerald-700") &&
				!whatsappBtn[0].includes("bg-rose-700"),
			`${homeFile} #home-cta-whatsapp must use registered light-surface Clearance Emerald tokens without bg-emerald-700/bg-rose-700`,
		);

		// Zero arbitrary raw hex color utilities in compiled homepage HTML and zero payment SDK scripts
		assert.ok(
			!rawHexUtilityRegex.test(html),
			`${homeFile} compiled homepage HTML must contain zero arbitrary raw hex color utilities`,
		);
		const homepageScriptSrcs = [
			...html.matchAll(/<script\b[^>]*\bsrc=["']([^"']+)["']/gi),
		].map((match) => match[1]);
		assert.ok(
			homepageScriptSrcs.length > 0,
			`${homeFile} homepage must include local runtime scripts`,
		);
		for (const scriptSrc of homepageScriptSrcs) {
			assert.equal(
				new URL(scriptSrc, "https://oceanviewflats.com/").origin,
				"https://oceanviewflats.com",
				`${homeFile} must only load first-party scripts and never third-party payment SDKs (found ${scriptSrc})`,
			);
		}
	}
});
