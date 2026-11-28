import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import test from "node:test";
import vm from "node:vm";
import { LANGUAGES } from "../../src/config/pages";
import { DESTINATION_GUIDES } from "../../src/i18n/destinationGuides";

const DIST_DIR = path.join(process.cwd(), "dist");
const PUBLIC_DIR = path.join(process.cwd(), "public");

type EventListenerFn = (event?: Record<string, unknown>) => void;

interface ScrollListenerEntry {
	fn: EventListenerFn;
	options?: unknown;
}

interface MockStorage {
	getItem: (key: string) => string | null;
	setItem: (key: string, value: string) => void;
	removeItem: (key: string) => void;
}

interface AnalyticsEngagementHarnessOptions {
	analyticsPath?: string;
	includeMainJs?: boolean;
	pageConfig: Record<string, unknown>;
	href?: string;
	referrer?: string;
	docLang?: string;
	sessionStorageInit?: Record<string, string>;
	disableSessionStorage?: boolean;
	throwingSessionStorage?: boolean;
	scrollHeight?: number;
	innerHeight?: number;
	initialDetails?: Array<{
		id: string;
		open: boolean;
		questionText?: string;
	}>;
	elementsById?: Record<string, unknown>;
	querySelectorAllMap?: Record<string, unknown[]>;
}

function createMapStorage(initial?: Record<string, string>) {
	const store = new Map<string, string>(Object.entries(initial ?? {}));
	const api: MockStorage = {
		getItem: (key: string) => store.get(key) ?? null,
		setItem: (key: string, value: string) => {
			store.set(key, String(value));
		},
		removeItem: (key: string) => {
			store.delete(key);
		},
	};
	return { store, api };
}

function runEngagementSandbox(options: AnalyticsEngagementHarnessOptions) {
	const pageConfigScript = fs.readFileSync(
		path.join(PUBLIC_DIR, "js", "page-config.js"),
		"utf-8",
	);
	const analyticsScript = fs.readFileSync(
		options.analyticsPath ?? path.join(PUBLIC_DIR, "js", "analytics.js"),
		"utf-8",
	);
	const mainScript = options.includeMainJs
		? fs.readFileSync(path.join(PUBLIC_DIR, "js", "main.js"), "utf-8")
		: null;

	const winListeners: Record<string, ScrollListenerEntry[]> = {};
	const docListeners: Record<
		string,
		Array<{ fn: EventListenerFn; capture: boolean }>
	> = {};
	const paq: unknown[][] = [];

	const parsedUrl = new URL(options.href ?? "https://www.oceanviewflats.com/");
	const locationObj = {
		href: parsedUrl.href,
		origin: parsedUrl.origin,
		pathname: parsedUrl.pathname,
		search: parsedUrl.search,
		hash: parsedUrl.hash,
	};

	const win: Record<string, unknown> = {
		_paq: paq,
		location: locationObj,
		scrollY: 0,
		innerHeight: options.innerHeight ?? 800,
		addEventListener: (
			evt: string,
			fn: EventListenerFn,
			listenerOpts?: unknown,
		) => {
			winListeners[evt] = winListeners[evt] || [];
			winListeners[evt].push({ fn, options: listenerOpts });
		},
		removeEventListener: (evt: string, fn: EventListenerFn) => {
			if (!winListeners[evt]) return;
			winListeners[evt] = winListeners[evt].filter((entry) => entry.fn !== fn);
		},
	};

	const elements = new Map<string, unknown>(
		Object.entries(options.elementsById ?? {}),
	);
	elements.set("ovf-page-config", {
		textContent: JSON.stringify(options.pageConfig),
	});

	const detailElements = (options.initialDetails ?? []).map((item) => ({
		tagName: "DETAILS",
		id: item.id,
		open: item.open,
		textContent: item.questionText ?? "",
		getAttribute: (name: string) => (name === "id" ? item.id : null),
	}));

	const scriptStub = {
		async: false,
		src: "",
		parentNode: { insertBefore: () => {} },
	};

	const doc = {
		documentElement: {
			lang: options.docLang ?? String(options.pageConfig.lang ?? "en"),
			scrollHeight: options.scrollHeight ?? 2800,
			clientHeight: options.innerHeight ?? 800,
			scrollTop: 0,
		},
		body: {
			scrollHeight: options.scrollHeight ?? 2800,
			style: { overflow: "" },
		},
		activeElement: null,
		referrer: options.referrer ?? "",
		getElementById: (id: string) => elements.get(id) ?? null,
		querySelectorAll: (selector: string) => {
			if (selector === "details[id]") {
				return detailElements;
			}
			return options.querySelectorAllMap?.[selector] ?? [];
		},
		createElement: () => ({ ...scriptStub }),
		getElementsByTagName: (tag: string) =>
			tag === "script" ? [scriptStub] : [],
		head: { appendChild: () => {} },
		addEventListener: (
			evt: string,
			fn: EventListenerFn,
			captureOrOpts?: boolean | { capture?: boolean },
		) => {
			const capture =
				typeof captureOrOpts === "boolean"
					? captureOrOpts
					: Boolean(captureOrOpts?.capture);
			docListeners[evt] = docListeners[evt] || [];
			docListeners[evt].push({ fn, capture });
		},
	};

	const local = createMapStorage();
	const session = createMapStorage(options.sessionStorageInit);

	const throwingStorage: MockStorage = {
		getItem: () => {
			throw new Error("SecurityError: sessionStorage disabled");
		},
		setItem: () => {
			throw new Error("SecurityError: sessionStorage disabled");
		},
		removeItem: () => {
			throw new Error("SecurityError: sessionStorage disabled");
		},
	};

	const sandbox: Record<string, unknown> = {
		window: win,
		location: locationObj,
		document: doc,
		localStorage: local.api,
		URL,
		URLSearchParams,
		setTimeout: (fn: () => void) => {
			fn();
			return 0;
		},
		clearTimeout: () => {},
		HTMLDialogElement: { prototype: { closedBy: true } },
		console: {
			error: () => {},
			warn: () => {},
			log: () => {},
		},
	};

	if (!options.disableSessionStorage) {
		sandbox.sessionStorage = options.throwingSessionStorage
			? throwingStorage
			: session.api;
	}

	vm.createContext(sandbox);
	vm.runInContext(pageConfigScript, sandbox);
	vm.runInContext(analyticsScript, sandbox);
	if (mainScript) {
		vm.runInContext(mainScript, sandbox);
	}

	const triggerDomContentLoaded = () => {
		for (const entry of docListeners.DOMContentLoaded ?? []) {
			entry.fn();
		}
	};

	const dispatchScroll = (scrollY: number) => {
		win.scrollY = scrollY;
		for (const entry of [...(winListeners.scroll ?? [])]) {
			entry.fn();
		}
	};

	const dispatchDocumentClick = (target: unknown) => {
		for (const entry of docListeners.click ?? []) {
			entry.fn({ target });
		}
	};

	const dispatchToggle = (target: unknown) => {
		for (const entry of docListeners.toggle ?? []) {
			if (entry.capture) {
				entry.fn({ target });
			}
		}
	};

	return {
		window: win,
		document: doc,
		paq,
		winListeners,
		docListeners,
		localStore: local.store,
		sessionStore: session.store,
		detailElements,
		triggerDomContentLoaded,
		dispatchScroll,
		dispatchDocumentClick,
		dispatchToggle,
	};
}

test("#345 compiled SSG HTML emits data-lang-target on language links, Homepage discovery CTA data attributes, FAQ IDs, and keeps theme-guarded components clean", () => {
	// Verify zero inline className="..." and zero propertyId === ternaries on touched TSX files
	for (const relPath of [
		"src/pages/Home.tsx",
		"src/components/LanguageDropdown.tsx",
	]) {
		const source = fs.readFileSync(path.join(process.cwd(), relPath), "utf-8");
		assert.ok(
			!source.includes('className="'),
			`${relPath} must contain ZERO hardcoded inline className="..." strings`,
		);
		assert.ok(
			!source.includes("propertyId ==="),
			`${relPath} must contain ZERO propertyId === ternaries`,
		);
	}

	for (const lang of LANGUAGES) {
		const homeFile = lang === "en" ? "index.html" : `${lang}.html`;
		const homeHtml = fs.readFileSync(path.join(DIST_DIR, homeFile), "utf-8");

		// 1. LanguageDropdown emits data-lang-target for every other locale
		const dropdownMatch = homeHtml.match(
			/<div[^>]*id="lang-dropdown"[^>]*>([\s\S]*?)<\/ul>/i,
		);
		assert.ok(dropdownMatch, `${homeFile} must render #lang-dropdown`);
		for (const otherLang of LANGUAGES) {
			if (otherLang === lang) continue;
			assert.ok(
				dropdownMatch[1].includes(`data-lang-target="${otherLang}"`),
				`${homeFile} #lang-dropdown must tag ${otherLang} link with data-lang-target="${otherLang}"`,
			);
		}

		// 2. Homepage discovery CTAs carry standardized data-analytics-cta and data-analytics-target
		const expectedCtaPairs = [
			'data-analytics-cta="home_hero_primary" data-analytics-target="properties"',
			'data-analytics-cta="home_hero_quick_link" data-analytics-target="1707"',
			'data-analytics-cta="home_hero_quick_link" data-analytics-target="1606"',
			'data-analytics-cta="home_property_card_title" data-analytics-target="1707"',
			'data-analytics-cta="home_property_card_title" data-analytics-target="1606"',
			'data-analytics-cta="home_property_card" data-analytics-target="1707"',
			'data-analytics-cta="home_property_card" data-analytics-target="1606"',
			'data-analytics-cta="home_multi_property_banner" data-analytics-target="contact"',
			'data-analytics-cta="home_compare_cta" data-analytics-target="1707"',
			'data-analytics-cta="home_compare_cta" data-analytics-target="1606"',
			'data-analytics-cta="home_destination_guides_callout" data-analytics-target="destination_guides_hub"',
			'data-analytics-cta="home_bottom_cta" data-analytics-target="properties"',
			'data-analytics-cta="home_bottom_cta" data-analytics-target="contact"',
		];
		for (const pair of expectedCtaPairs) {
			assert.ok(
				homeHtml.includes(pair),
				`${homeFile} must include discovery CTA attributes: ${pair}`,
			);
		}

		// 3. Home FAQ <details id="faq-1..6">
		for (let i = 1; i <= 6; i++) {
			assert.ok(
				homeHtml.includes(`id="faq-${i}"`),
				`${homeFile} must render FAQ item id="faq-${i}"`,
			);
		}

		// 4. Property FAQ <details id="faq-1707-1..6"> & <details id="faq-1606-1..6">
		for (const propId of ["1707", "1606"]) {
			const propFile =
				lang === "en"
					? `Oceanview${propId}/index.html`
					: `Oceanview${propId}/${lang}.html`;
			const propHtml = fs.readFileSync(path.join(DIST_DIR, propFile), "utf-8");
			assert.ok(
				propHtml.includes(`id="faq-${propId}-1"`),
				`${propFile} must render stable Property FAQ IDs`,
			);
		}

		// 5. Destination Guide FAQ <details id="...">
		const firstGuide = DESTINATION_GUIDES[0];
		const guideFile =
			lang === "en"
				? `santa-marta/${firstGuide.slug}/index.html`
				: `santa-marta/${firstGuide.slug}/${lang}.html`;
		const guideHtml = fs.readFileSync(path.join(DIST_DIR, guideFile), "utf-8");
		assert.ok(
			guideHtml.includes('id="guide-faq"'),
			`${guideFile} must render #guide-faq`,
		);
	}
});

for (const variant of [
	{
		label: "public/js/analytics.js",
		file: path.join(PUBLIC_DIR, "js", "analytics.js"),
	},
	{
		label: "dist/js/analytics.js (minified)",
		file: path.join(DIST_DIR, "js", "analytics.js"),
	},
]) {
	test(`#345 ${variant.label} enables 15s heartbeat + passive one-shot scroll milestones (25/50/75/90) on public pages only and skips them on private/result pages`, () => {
		// 1. Public page (enableEngagementTracking: true)
		const pub = runEngagementSandbox({
			analyticsPath: variant.file,
			pageConfig: {
				pageId: "destination-guide-digital-nomad-remote-work",
				lang: "en",
				analytics: {
					pageType: "destination_guide",
					propertyId: "portfolio",
					guideSlug: "digital-nomad-remote-work",
					enableEngagementTracking: true,
				},
			},
			href: "https://www.oceanviewflats.com/santa-marta/digital-nomad-remote-work/",
			scrollHeight: 2800,
			innerHeight: 800, // scrollable = 2000
		});

		const heartbeatCmds = JSON.parse(
			JSON.stringify(
				pub.paq.filter((cmd) => cmd[0] === "enableHeartBeatTimer"),
			),
		);
		assert.deepEqual(heartbeatCmds, [["enableHeartBeatTimer", 15]]);

		assert.equal(
			pub.winListeners.scroll?.length,
			1,
			"Public page must attach exactly one scroll listener",
		);
		assert.deepEqual(
			JSON.parse(JSON.stringify(pub.winListeners.scroll?.[0]?.options)),
			{ passive: true },
			"Scroll listener must be passive",
		);

		// Below 25% -> no milestone
		pub.dispatchScroll(400); // 20%
		assert.equal(
			pub.paq.filter((c) => c[0] === "trackEvent" && c[1] === "Engagement")
				.length,
			0,
		);

		// Cross 25% (500 / 2000 = 25%) -> emits 25% once
		pub.dispatchScroll(500);
		// Re-scroll around 30% -> no duplicate 25%
		pub.dispatchScroll(600);
		// Cross 50% and 75% in one jump (1500 / 2000 = 75%)
		pub.dispatchScroll(1500);
		// Scroll back up to 10% and down to 60% -> no duplicates
		pub.dispatchScroll(200);
		pub.dispatchScroll(1200);
		// Cross 90% (1820 / 2000 = 91%) -> emits 90% and removes listener
		pub.dispatchScroll(1820);
		pub.dispatchScroll(2000);

		const scrollEvents = JSON.parse(
			JSON.stringify(
				pub.paq.filter((c) => c[0] === "trackEvent" && c[1] === "Engagement"),
			),
		);
		assert.deepEqual(scrollEvents, [
			["trackEvent", "Engagement", "Scroll Depth", "25%", 25],
			["trackEvent", "Engagement", "Scroll Depth", "50%", 50],
			["trackEvent", "Engagement", "Scroll Depth", "75%", 75],
			["trackEvent", "Engagement", "Scroll Depth", "90%", 90],
		]);
		assert.equal(
			pub.winListeners.scroll?.length,
			0,
			"Scroll listener should detach once all 4 milestones have fired",
		);

		// 2. Short public page (scrollHeight <= innerHeight): no NaN, no crash, no bogus events
		const shortPage = runEngagementSandbox({
			analyticsPath: variant.file,
			pageConfig: {
				pageId: "contact",
				lang: "es",
				analytics: {
					pageType: "contact",
					propertyId: "portfolio",
					enableEngagementTracking: true,
				},
			},
			href: "https://www.oceanviewflats.com/contact/es.html",
			scrollHeight: 600,
			innerHeight: 800,
		});
		assert.doesNotThrow(() => {
			shortPage.dispatchScroll(0);
			shortPage.dispatchScroll(100);
		});
		assert.equal(
			shortPage.paq.filter(
				(c) => c[0] === "trackEvent" && c[1] === "Engagement",
			).length,
			0,
			"Short page with scrollHeight <= innerHeight must not emit NaN or bogus scroll milestones",
		);

		// 3. Private fulfillment, checkout result, and 404 pages (enableEngagementTracking: false)
		const privatePages = [
			{ pageId: "registry", pageType: "registry", path: "/registry/" },
			{ pageId: "guide", pageType: "guide", path: "/guide/" },
			{
				pageId: "booking-success",
				pageType: "booking_result",
				path: "/booking-success/",
			},
			{ pageId: "404", pageType: "404", path: "/404.html" },
		];

		for (const page of privatePages) {
			const priv = runEngagementSandbox({
				analyticsPath: variant.file,
				pageConfig: {
					pageId: page.pageId,
					lang: "en",
					analytics: {
						pageType: page.pageType,
						propertyId: "portfolio",
						enableEngagementTracking: false,
					},
				},
				href: `https://www.oceanviewflats.com${page.path}`,
				scrollHeight: 3000,
				innerHeight: 800,
			});

			assert.equal(
				priv.paq.some((c) => c[0] === "enableHeartBeatTimer"),
				false,
				`${page.pageType} must NEVER queue enableHeartBeatTimer`,
			);
			assert.equal(
				priv.winListeners.scroll?.length ?? 0,
				0,
				`${page.pageType} must NEVER attach scroll listeners`,
			);
		}
	});
}

test("#345 automatic locale redirect handoff: lang-detect.js writes one-time sessionStorage marker (skipping crawlers) and analytics.js consumes it once for Dimension 4 + Auto Redirect event", () => {
	const langDetectVariants = [
		{
			label: "public",
			langDetect: path.join(PUBLIC_DIR, "js", "lang-detect.js"),
			analytics: path.join(PUBLIC_DIR, "js", "analytics.js"),
		},
		{
			label: "dist",
			langDetect: path.join(DIST_DIR, "js", "lang-detect.js"),
			analytics: path.join(DIST_DIR, "js", "analytics.js"),
		},
	];

	const humanUA =
		"Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36";

	for (const pair of langDetectVariants) {
		const langDetectSource = fs.readFileSync(pair.langDetect, "utf-8");

		const runLangDetectWithSession = (opts: {
			href: string;
			userAgent: string;
			language: string;
			webdriver?: boolean;
			storedPref?: string | null;
			includeSessionStorage?: boolean;
			throwOnSessionStorage?: boolean;
		}) => {
			const parsed = new URL(opts.href);
			const local = createMapStorage(
				opts.storedPref ? { "lang-pref": opts.storedPref } : undefined,
			);
			const session = createMapStorage();
			let replacedHref: string | null = null;
			let markerAtReplaceTime: string | null = null;

			const sandbox: Record<string, unknown> = {
				window: {
					location: {
						href: parsed.href,
						origin: parsed.origin,
						pathname: parsed.pathname,
						search: parsed.search,
						hash: parsed.hash,
						replace: (nextHref: string) => {
							markerAtReplaceTime =
								session.store.get("ovf_lang_redirect") ?? null;
							replacedHref = nextHref;
						},
					},
				},
				navigator: {
					userAgent: opts.userAgent,
					language: opts.language,
					webdriver: Boolean(opts.webdriver),
				},
				localStorage: local.api,
			};

			if (opts.includeSessionStorage !== false) {
				sandbox.sessionStorage = opts.throwOnSessionStorage
					? {
							getItem: () => {
								throw new Error("Blocked");
							},
							setItem: () => {
								throw new Error("Blocked");
							},
							removeItem: () => {
								throw new Error("Blocked");
							},
						}
					: session.api;
			}

			vm.runInNewContext(langDetectSource, sandbox);
			return {
				replacedHref,
				markerAtReplaceTime,
				sessionStore: session.store,
			};
		};

		// 1. Human Spanish browser on English root writes marker BEFORE window.location.replace
		const esRedirect = runLangDetectWithSession({
			href: "https://www.oceanviewflats.com/",
			userAgent: humanUA,
			language: "es-CO",
		});
		assert.equal(
			esRedirect.replacedHref,
			"https://www.oceanviewflats.com/es.html",
			`[${pair.label}] must redirect Spanish visitor to /es.html`,
		);
		assert.equal(
			esRedirect.markerAtReplaceTime,
			"en->es",
			`[${pair.label}] must write ovf_lang_redirect="en->es" BEFORE calling window.location.replace`,
		);

		// 2. Stored non-English preference writes marker before redirect
		const frStored = runLangDetectWithSession({
			href: "https://www.oceanviewflats.com/Oceanview1707/",
			userAgent: humanUA,
			language: "en-US",
			storedPref: "fr",
		});
		assert.equal(
			frStored.replacedHref,
			"https://www.oceanviewflats.com/Oceanview1707/fr.html",
		);
		assert.equal(frStored.markerAtReplaceTime, "en->fr");

		// 3. Crawlers and webdriver are completely untouched (no redirect, no sessionStorage marker)
		for (const botOpts of [
			{
				userAgent:
					"Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)",
				webdriver: false,
			},
			{
				userAgent:
					"Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)",
				webdriver: false,
			},
			{ userAgent: humanUA, webdriver: true },
		]) {
			const botRes = runLangDetectWithSession({
				href: "https://www.oceanviewflats.com/",
				userAgent: botOpts.userAgent,
				language: "es-CO",
				webdriver: botOpts.webdriver,
			});
			assert.equal(botRes.replacedHref, null);
			assert.equal(botRes.sessionStore.get("ovf_lang_redirect"), undefined);
		}

		// 4. Safe when sessionStorage is missing or throws
		assert.doesNotThrow(() => {
			const noStorage = runLangDetectWithSession({
				href: "https://www.oceanviewflats.com/",
				userAgent: humanUA,
				language: "de-DE",
				includeSessionStorage: false,
			});
			assert.equal(
				noStorage.replacedHref,
				"https://www.oceanviewflats.com/de.html",
			);

			const throwingStorage = runLangDetectWithSession({
				href: "https://www.oceanviewflats.com/",
				userAgent: humanUA,
				language: "it-IT",
				throwOnSessionStorage: true,
			});
			assert.equal(
				throwingStorage.replacedHref,
				"https://www.oceanviewflats.com/it.html",
			);
		});

		// 5. analytics.js on localized landing page consumes & removes marker, sets Dim 4 = auto_redirected BEFORE trackPageView, emits Language / Auto Redirect
		const landed = runEngagementSandbox({
			analyticsPath: pair.analytics,
			pageConfig: {
				pageId: "home",
				lang: "es",
				analytics: {
					pageType: "home",
					propertyId: "portfolio",
					enableEngagementTracking: true,
				},
			},
			href: "https://www.oceanviewflats.com/es.html",
			sessionStorageInit: {
				ovf_lang_redirect: esRedirect.markerAtReplaceTime ?? "",
			},
		});

		assert.equal(
			landed.sessionStore.has("ovf_lang_redirect"),
			false,
			`[${pair.label}] analytics.js must remove one-time ovf_lang_redirect marker after reading`,
		);

		const dim4Idx = landed.paq.findIndex(
			(cmd) => cmd[0] === "setCustomDimension" && cmd[1] === 4,
		);
		const trackPageViewIdx = landed.paq.findIndex(
			(cmd) => cmd[0] === "trackPageView",
		);
		assert.ok(dim4Idx > -1 && dim4Idx < trackPageViewIdx);
		assert.deepEqual(
			JSON.parse(JSON.stringify(landed.paq[dim4Idx])),
			["setCustomDimension", 4, "auto_redirected"],
			`[${pair.label}] Dimension 4 must be auto_redirected when handoff marker is consumed`,
		);
		assert.equal(
			landed.paq.some(
				(cmd) =>
					cmd[0] === "setCustomDimension" &&
					cmd[1] === 4 &&
					cmd[2] === "direct_locale",
			),
			false,
			`[${pair.label}] must NOT emit direct_locale when auto_redirected`,
		);

		const autoRedirectEvents = JSON.parse(
			JSON.stringify(
				landed.paq.filter(
					(cmd) =>
						cmd[0] === "trackEvent" &&
						cmd[1] === "Language" &&
						cmd[2] === "Auto Redirect",
				),
			),
		);
		assert.deepEqual(autoRedirectEvents, [
			["trackEvent", "Language", "Auto Redirect", "en->es"],
		]);

		// 6. Second pageview in the SAME session preserves auto_redirected (never resets to direct_locale) and emits NO duplicate Auto Redirect event
		assert.equal(
			landed.sessionStore.get("ovf_lang_acquisition"),
			"auto_redirected",
			`[${pair.label}] analytics.js must persist auto_redirected in sessionStorage`,
		);
		const secondLoad = runEngagementSandbox({
			analyticsPath: pair.analytics,
			pageConfig: {
				pageId: "Oceanview1707",
				lang: "es",
				analytics: {
					pageType: "property",
					propertyId: "1707",
					enableEngagementTracking: true,
				},
			},
			href: "https://www.oceanviewflats.com/Oceanview1707/es.html",
			sessionStorageInit: Object.fromEntries(landed.sessionStore.entries()),
		});
		const secondDim4 = secondLoad.paq.find(
			(cmd) => cmd[0] === "setCustomDimension" && cmd[1] === 4,
		);
		const secondTrackIdx = secondLoad.paq.findIndex(
			(cmd) => cmd[0] === "trackPageView",
		);
		const secondDim4Idx = secondLoad.paq.findIndex(
			(cmd) => cmd[0] === "setCustomDimension" && cmd[1] === 4,
		);
		assert.ok(secondDim4Idx > -1 && secondDim4Idx < secondTrackIdx);
		assert.deepEqual(JSON.parse(JSON.stringify(secondDim4)), [
			"setCustomDimension",
			4,
			"auto_redirected",
		]);
		assert.equal(
			secondLoad.paq.some(
				(cmd) => cmd[0] === "trackEvent" && cmd[2] === "Auto Redirect",
			),
			false,
		);

		// 6b. Fresh session (empty sessionStorage) defaults to direct_locale and emits NO Auto Redirect event
		const freshDirect = runEngagementSandbox({
			analyticsPath: pair.analytics,
			pageConfig: {
				pageId: "home",
				lang: "es",
				analytics: {
					pageType: "home",
					propertyId: "portfolio",
					enableEngagementTracking: true,
				},
			},
			href: "https://www.oceanviewflats.com/es.html",
		});
		const freshDim4 = freshDirect.paq.find(
			(cmd) => cmd[0] === "setCustomDimension" && cmd[1] === 4,
		);
		assert.deepEqual(JSON.parse(JSON.stringify(freshDim4)), [
			"setCustomDimension",
			4,
			"direct_locale",
		]);
		assert.equal(
			freshDirect.paq.some(
				(cmd) => cmd[0] === "trackEvent" && cmd[2] === "Auto Redirect",
			),
			false,
		);

		// 7. Tampered / malformed sessionStorage value is cleared and ignored safely
		const tampered = runEngagementSandbox({
			analyticsPath: pair.analytics,
			pageConfig: {
				pageId: "home",
				lang: "es",
				analytics: {
					pageType: "home",
					propertyId: "portfolio",
					enableEngagementTracking: true,
				},
			},
			href: "https://www.oceanviewflats.com/es.html",
			sessionStorageInit: { ovf_lang_redirect: "en->evil<script>" },
		});
		assert.equal(tampered.sessionStore.has("ovf_lang_redirect"), false);
		assert.deepEqual(
			JSON.parse(
				JSON.stringify(
					tampered.paq.find(
						(cmd) => cmd[0] === "setCustomDimension" && cmd[1] === 4,
					),
				),
			),
			["setCustomDimension", 4, "direct_locale"],
		);
		assert.equal(
			tampered.paq.some(
				(cmd) => cmd[0] === "trackEvent" && cmd[2] === "Auto Redirect",
			),
			false,
		);

		// 8. Throwing sessionStorage in analytics.js never breaks initialization
		assert.doesNotThrow(() => {
			const resilient = runEngagementSandbox({
				analyticsPath: pair.analytics,
				pageConfig: {
					pageId: "home",
					lang: "es",
					analytics: {
						pageType: "home",
						propertyId: "portfolio",
						enableEngagementTracking: true,
					},
				},
				href: "https://www.oceanviewflats.com/es.html",
				throwingSessionStorage: true,
			});
			assert.ok(resilient.paq.some((cmd) => cmd[0] === "trackPageView"));
		});
	}
});

test("#345 manual language switcher sets Language Acquisition = manual_switched and emits Language / Manual Switch (<from>-><to>)", () => {
	const createLangLink = (href: string, targetLang: string) => {
		const attrs: Record<string, string> = {
			href,
			"data-lang-target": targetLang,
		};
		const listeners: Record<string, EventListenerFn[]> = {};
		return {
			getAttribute: (name: string) => attrs[name] ?? null,
			setAttribute: (name: string, val: string) => {
				attrs[name] = val;
			},
			addEventListener: (evt: string, fn: EventListenerFn) => {
				listeners[evt] = listeners[evt] || [];
				listeners[evt].push(fn);
			},
			click: () => {
				for (const fn of listeners.click ?? []) fn({});
			},
		};
	};

	// Case 1: English page switching to French
	const frLink = createLangLink("fr.html", "fr");
	const langGroup = {
		querySelectorAll: (sel: string) => (sel === "a" ? [frLink] : []),
		querySelector: () => null,
		addEventListener: () => {},
		contains: () => false,
	};
	const langToggle = {
		getAttribute: () => "false",
		setAttribute: () => {},
		addEventListener: () => {},
		contains: () => false,
	};
	const langDropdown = {
		classList: {
			contains: () => false,
			add: () => {},
			remove: () => {},
		},
		addEventListener: () => {},
		contains: () => false,
	};

	const enRun = runEngagementSandbox({
		includeMainJs: true,
		pageConfig: {
			pageId: "Oceanview1707",
			lang: "en",
			analytics: {
				pageType: "property",
				propertyId: "1707",
				enableEngagementTracking: true,
			},
		},
		href: "https://www.oceanviewflats.com/Oceanview1707/",
		elementsById: {
			"lang-toggle-group": langGroup,
			"lang-toggle": langToggle,
			"lang-dropdown": langDropdown,
		},
	});

	enRun.triggerDomContentLoaded();
	const beforeClickLen = enRun.paq.length;
	frLink.click();

	assert.equal(enRun.localStore.get("lang-pref"), "fr");
	assert.equal(
		enRun.sessionStore.get("ovf_lang_acquisition"),
		"manual_switched",
		"Manual language switch must persist manual_switched in sessionStorage",
	);
	const afterClick = JSON.parse(
		JSON.stringify(enRun.paq.slice(beforeClickLen)),
	);
	assert.deepEqual(afterClick, [
		["setCustomDimension", 4, "manual_switched"],
		["trackEvent", "Language", "Manual Switch", "en->fr"],
	]);

	// Destination localized page in the same session MUST preserve manual_switched (never reset to direct_locale)
	const destFrRun = runEngagementSandbox({
		pageConfig: {
			pageId: "Oceanview1707",
			lang: "fr",
			analytics: {
				pageType: "property",
				propertyId: "1707",
				enableEngagementTracking: true,
			},
		},
		href: "https://www.oceanviewflats.com/Oceanview1707/fr.html",
		sessionStorageInit: Object.fromEntries(enRun.sessionStore.entries()),
	});
	const destDim4Idx = destFrRun.paq.findIndex(
		(cmd) => cmd[0] === "setCustomDimension" && cmd[1] === 4,
	);
	const destTrackIdx = destFrRun.paq.findIndex(
		(cmd) => cmd[0] === "trackPageView",
	);
	assert.ok(destDim4Idx > -1 && destDim4Idx < destTrackIdx);
	assert.deepEqual(JSON.parse(JSON.stringify(destFrRun.paq[destDim4Idx])), [
		"setCustomDimension",
		4,
		"manual_switched",
	]);
	assert.equal(
		destFrRun.paq.some(
			(cmd) =>
				cmd[0] === "setCustomDimension" &&
				cmd[1] === 4 &&
				cmd[2] === "direct_locale",
		),
		false,
		"Destination localized page after manual switch must NOT overwrite Dimension 4 with direct_locale",
	);

	// Case 2: Localized 404 (/404/es.html) switching back to English (/404.html) via data-lang-target="en"
	const en404Link = createLangLink("/404.html", "en");
	const es404Group = {
		querySelectorAll: (sel: string) => (sel === "a" ? [en404Link] : []),
		querySelector: () => null,
		addEventListener: () => {},
		contains: () => false,
	};

	const es404Run = runEngagementSandbox({
		includeMainJs: true,
		pageConfig: {
			pageId: "404",
			lang: "es",
			analytics: {
				pageType: "404",
				propertyId: "portfolio",
				enableEngagementTracking: false,
			},
		},
		href: "https://www.oceanviewflats.com/404/es.html",
		elementsById: {
			"lang-toggle-group": es404Group,
			"lang-toggle": langToggle,
			"lang-dropdown": langDropdown,
		},
	});

	es404Run.triggerDomContentLoaded();
	const before404Len = es404Run.paq.length;
	en404Link.click();

	assert.equal(es404Run.localStore.get("lang-pref"), "en");
	assert.deepEqual(
		JSON.parse(JSON.stringify(es404Run.paq.slice(before404Len))),
		[
			["setCustomDimension", 4, "manual_switched"],
			["trackEvent", "Language", "Manual Switch", "es->en"],
		],
	);
});

test("#345 Property photo gallery lightbox tracks opens (thumbnail vs view_all) and unique photo views per Property without duplicate counts", () => {
	const createInteractiveNode = (attrs: Record<string, string> = {}) => {
		const listeners: Record<string, EventListenerFn[]> = {};
		return {
			attrs,
			src: "",
			alt: "",
			textContent: "",
			onload: null as (() => void) | null,
			classList: {
				add: () => {},
				remove: () => {},
				contains: () => false,
			},
			getAttribute: (name: string) => attrs[name] ?? null,
			addEventListener: (evt: string, fn: EventListenerFn) => {
				listeners[evt] = listeners[evt] || [];
				listeners[evt].push(fn);
			},
			dispatch: (evt: string, payload: Record<string, unknown> = {}) => {
				for (const fn of listeners[evt] ?? []) {
					fn({ preventDefault: () => {}, ...payload });
				}
			},
			focus: () => {},
			showModal: () => {},
			close: () => {},
			querySelectorAll: () => [],
		};
	};

	const triggers = [
		createInteractiveNode({
			"data-index": "0",
			"data-src": "../images/1707-1.webp",
			"data-alt": "Vista al mar",
			"data-caption": "Balcón privado frente al mar",
		}),
		createInteractiveNode({
			"data-index": "1",
			"data-src": "../images/1707-2.webp",
			"data-alt": "Sala principal",
			"data-caption": "Sala con ventanales",
		}),
		createInteractiveNode({
			"data-index": "2",
			"data-src": "../images/1707-3.webp",
			"data-alt": "Habitación principal",
			"data-caption": "Cama King frente al mar",
		}),
		createInteractiveNode({
			"data-index": "3",
			"data-src": "../images/1707-4.webp",
			"data-alt": "Cocina equipada",
			"data-caption": "Cocina moderna",
		}),
	];

	const dialog = createInteractiveNode();
	const btnViewAll = createInteractiveNode();
	const imgActive = createInteractiveNode();
	const txtCounter = createInteractiveNode();
	const txtCaption = createInteractiveNode();
	const btnClose = createInteractiveNode();
	const btnPrev = createInteractiveNode();
	const btnNext = createInteractiveNode();
	const announcer = createInteractiveNode();

	const run = runEngagementSandbox({
		includeMainJs: true,
		pageConfig: {
			pageId: "Oceanview1707",
			lang: "es",
			analytics: {
				pageType: "property",
				propertyId: "1707",
				enableEngagementTracking: true,
			},
		},
		href: "https://www.oceanviewflats.com/Oceanview1707/es.html",
		elementsById: {
			"lightbox-dialog": dialog,
			"btn-view-all-photos": btnViewAll,
			"lightbox-active-img": imgActive,
			"lightbox-counter": txtCounter,
			"lightbox-caption": txtCaption,
			"lightbox-close": btnClose,
			"lightbox-prev": btnPrev,
			"lightbox-next": btnNext,
			"lightbox-announcer": announcer,
		},
		querySelectorAllMap: {
			".gallery-trigger": triggers,
		},
	});

	run.triggerDomContentLoaded();
	const baseline = run.paq.length;

	// 1. Open from thumbnail index 2 -> thumbnail open + unique photo #3 (count 1)
	triggers[2].dispatch("click");
	// 2. Next -> index 3 -> unique photo #4 (count 2)
	btnNext.dispatch("click");
	// 3. Prev -> back to index 2 -> already seen, ZERO duplicate Photo Viewed
	btnPrev.dispatch("click");
	// 4. ArrowRight -> back to index 3 -> already seen, ZERO duplicate Photo Viewed
	dialog.dispatch("keydown", { key: "ArrowRight" });
	// 5. ArrowRight -> wraps to index 0 -> unique photo #1 (count 3)
	dialog.dispatch("keydown", { key: "ArrowRight" });
	// 6. Close and reopen via #btn-view-all-photos (index 0 already seen, emits view_all open only)
	btnClose.dispatch("click");
	btnViewAll.dispatch("click");
	// 7. Next -> index 1 -> unique photo #2 (count 4)
	btnNext.dispatch("click");

	const galleryEvents = JSON.parse(
		JSON.stringify(
			run.paq
				.slice(baseline)
				.filter((cmd) => cmd[0] === "trackEvent" && cmd[1] === "Gallery"),
		),
	);

	assert.deepEqual(galleryEvents, [
		["trackEvent", "Gallery", "Lightbox Open", "1707:thumbnail"],
		["trackEvent", "Gallery", "Photo Viewed", "1707:photo_3", 1],
		["trackEvent", "Gallery", "Photo Viewed", "1707:photo_4", 2],
		["trackEvent", "Gallery", "Photo Viewed", "1707:photo_1", 3],
		["trackEvent", "Gallery", "Lightbox Open", "1707:view_all"],
		["trackEvent", "Gallery", "Photo Viewed", "1707:photo_2", 4],
	]);

	// Verify localized caption/alt strings never appear in Matomo queue
	const serializedPaq = JSON.stringify(galleryEvents);
	for (const localized of ["Balcón", "Vista al mar", "Habitación", "Cocina"]) {
		assert.ok(
			!serializedPaq.includes(localized),
			`Gallery events must never leak localized caption text "${localized}"`,
		);
	}
});

test("#345 FAQ <details> capture-phase toggle tracks opens strictly by stable details.id and skips initial default-open on load", () => {
	const run = runEngagementSandbox({
		includeMainJs: true,
		pageConfig: {
			pageId: "home",
			lang: "ja",
			analytics: {
				pageType: "home",
				propertyId: "portfolio",
				enableEngagementTracking: true,
			},
		},
		href: "https://www.oceanviewflats.com/ja.html",
		initialDetails: [
			{ id: "faq-1", open: false, questionText: "チェックイン時間は？" },
			{ id: "faq-6", open: true, questionText: "直接予約の利点は？" },
			{ id: "faq-1707-3", open: false, questionText: "Wi-Fiの速度は？" },
			{ id: "faq-ps-2", open: false, questionText: "ビーチまでの距離は？" },
		],
	});

	// Verify capture-phase listener registered for toggle
	assert.ok(
		run.docListeners.toggle?.some((entry) => entry.capture === true),
		"FAQ tracking must register a capture-phase toggle listener on document",
	);

	run.triggerDomContentLoaded();
	const baseline = run.paq.length;

	const [faq1, faq6, faq1707_3, faqPs2] = run.detailElements;

	// 1. Spurious load-time toggle on already default-open faq-6 -> skipped
	run.dispatchToggle(faq6);
	assert.equal(
		run.paq.slice(baseline).filter((c) => c[1] === "FAQ").length,
		0,
		"Initial default-open <details> must not emit FAQ Open on load",
	);

	// 2. Closing faq-6 -> not tracked; re-opening faq-6 -> tracked once
	faq6.open = false;
	run.dispatchToggle(faq6);
	faq6.open = true;
	run.dispatchToggle(faq6);

	// 3. Opening initially closed Home, Property, and Destination Guide FAQs -> tracked by stable ID
	faq1.open = true;
	run.dispatchToggle(faq1);
	faq1707_3.open = true;
	run.dispatchToggle(faq1707_3);
	faqPs2.open = true;
	run.dispatchToggle(faqPs2);

	// 4. Closing faq-1 -> ignored
	faq1.open = false;
	run.dispatchToggle(faq1);

	// 5. Non-<details> or missing-id element -> ignored safely
	run.dispatchToggle({ tagName: "DIV", id: "not-a-details", open: true });
	run.dispatchToggle({ tagName: "DETAILS", id: "", open: true });

	const faqEvents = JSON.parse(
		JSON.stringify(
			run.paq
				.slice(baseline)
				.filter((cmd) => cmd[0] === "trackEvent" && cmd[1] === "FAQ"),
		),
	);

	assert.deepEqual(faqEvents, [
		["trackEvent", "FAQ", "Open", "faq-6"],
		["trackEvent", "FAQ", "Open", "faq-1"],
		["trackEvent", "FAQ", "Open", "faq-1707-3"],
		["trackEvent", "FAQ", "Open", "faq-ps-2"],
	]);

	// Zero localized question text in Matomo events
	const serialized = JSON.stringify(faqEvents);
	assert.ok(!serialized.includes("チェックイン"));
	assert.ok(!serialized.includes("直接予約"));
});

test("#345 Homepage discovery CTAs and Destination Guide -> Property CTAs route cleanly through window.ovfAnalytics", () => {
	const run = runEngagementSandbox({
		includeMainJs: true,
		pageConfig: {
			pageId: "home",
			lang: "en",
			analytics: {
				pageType: "home",
				propertyId: "portfolio",
				enableEngagementTracking: true,
			},
		},
		href: "https://www.oceanviewflats.com/",
	});

	// Wrap window.ovfAnalytics.trackEvent with a spy while preserving delegation to the real facade
	const realAnalytics = run.window.ovfAnalytics as {
		trackEvent: (c: string, a: string, n?: string, v?: number) => boolean;
	};
	const facadeCalls: unknown[][] = [];
	run.window.ovfAnalytics = {
		...realAnalytics,
		trackEvent: (
			category: string,
			action: string,
			name?: string,
			value?: number,
		) => {
			facadeCalls.push([category, action, name]);
			return realAnalytics.trackEvent(category, action, name, value);
		},
	};

	const baseline = run.paq.length;

	const clickDiscoveryCta = (placement: string, target: string) => {
		const anchor = {
			getAttribute: (name: string) => {
				if (name === "data-analytics-cta") return placement;
				if (name === "data-analytics-target") return target;
				return null;
			},
		};
		const nestedSpan = {
			closest: (selector: string) =>
				selector === "[data-analytics-cta][data-analytics-target]"
					? anchor
					: null,
		};
		run.dispatchDocumentClick(nestedSpan);
	};

	const clickGuideCta = (slug: string, propertyId: string) => {
		const anchor = {
			getAttribute: (name: string) => {
				if (name === "data-guide-slug") return slug;
				if (name === "data-guide-cta-property") return propertyId;
				return null;
			},
		};
		const nestedSpan = {
			closest: (selector: string) =>
				selector === "[data-guide-cta-property][data-guide-slug]"
					? anchor
					: null,
		};
		run.dispatchDocumentClick(nestedSpan);
	};

	// Trigger Homepage discovery CTAs
	clickDiscoveryCta("home_hero_primary", "properties");
	clickDiscoveryCta("home_hero_quick_link", "1707");
	clickDiscoveryCta("home_hero_quick_link", "1606");
	clickDiscoveryCta("home_property_card_title", "1707");
	clickDiscoveryCta("home_property_card", "1606");
	clickDiscoveryCta("home_compare_cta", "1707");
	clickDiscoveryCta("home_compare_cta", "1606");
	clickDiscoveryCta("home_multi_property_banner", "contact");
	clickDiscoveryCta(
		"home_destination_guides_callout",
		"destination_guides_hub",
	);
	clickDiscoveryCta("home_bottom_cta", "properties");
	clickDiscoveryCta("home_bottom_cta", "contact");

	// Trigger Destination Guide -> Property CTA
	clickGuideCta("playa-salguero-vs-el-rodadero", "1606");

	const expectedFacadeCalls = [
		["Discovery", "CTA Click", "home_hero_primary:properties"],
		["Discovery", "CTA Click", "home_hero_quick_link:1707"],
		["Discovery", "CTA Click", "home_hero_quick_link:1606"],
		["Discovery", "CTA Click", "home_property_card_title:1707"],
		["Discovery", "CTA Click", "home_property_card:1606"],
		["Discovery", "CTA Click", "home_compare_cta:1707"],
		["Discovery", "CTA Click", "home_compare_cta:1606"],
		["Discovery", "CTA Click", "home_multi_property_banner:contact"],
		[
			"Discovery",
			"CTA Click",
			"home_destination_guides_callout:destination_guides_hub",
		],
		["Discovery", "CTA Click", "home_bottom_cta:properties"],
		["Discovery", "CTA Click", "home_bottom_cta:contact"],
		["DestinationGuide", "PropertyCTA", "playa-salguero-vs-el-rodadero:1606"],
	];

	assert.deepEqual(
		facadeCalls,
		expectedFacadeCalls,
		"All Homepage discovery CTAs and Destination Guide CTAs must route through window.ovfAnalytics.trackEvent",
	);

	const emittedTrackEvents = JSON.parse(
		JSON.stringify(
			run.paq.slice(baseline).filter((cmd) => cmd[0] === "trackEvent"),
		),
	);
	assert.deepEqual(
		emittedTrackEvents,
		expectedFacadeCalls.map((args) => ["trackEvent", ...args]),
	);
});
