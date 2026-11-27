import assert from "node:assert/strict";
import crypto from "node:crypto";
import fs from "node:fs";
import path from "node:path";
import test from "node:test";
import vm from "node:vm";
import {
	getPageAnalyticsConfig,
	LANGUAGES,
	type PageAnalyticsConfig,
	pages,
} from "../../src/config/pages";
import { DESTINATION_GUIDES } from "../../src/i18n/destinationGuides";
import { baseTemplate } from "../../src/templates/base";

const DIST_DIR = path.join(process.cwd(), "dist");
const PUBLIC_DIR = path.join(process.cwd(), "public");

const sha256Short = (filePath: string): string =>
	crypto
		.createHash("sha256")
		.update(fs.readFileSync(filePath))
		.digest("hex")
		.slice(0, 8);

interface AnalyticsVmOptions {
	sourcePath?: string;
	pageConfig?: Record<string, unknown>;
	href?: string;
	referrer?: string;
	docLang?: string;
	sessionStore?: Map<string, string>;
	throwingSessionStorage?: boolean;
}

interface OvfAnalyticsApi {
	TRACKER_URL: string;
	SITE_ID: string;
	GOALS: Record<string, number>;
	DIMENSIONS: Record<string, number>;
	FUNNEL_STAGES: Record<string, number>;
	ALLOWED_QUERY_PARAMS: readonly string[];
	ALLOWED_QUERY_PREFIXES: readonly string[];
	LANG_REDIRECT_STORAGE_KEY: string;
	LANG_ACQUISITION_STORAGE_KEY: string;
	FUNNEL_STAGE_STORAGE_KEY: string;
	LANGUAGE_ACQUISITION_MODES: Record<string, string>;
	trackEvent: (
		category: string,
		action: string,
		name?: string,
		value?: number,
	) => boolean;
	trackGoal: (goalId: number | string, customRevenue?: number) => boolean;
	setCustomDimension: (dimensionId: number, value: string) => boolean;
	advanceFunnelStage: (stage: string) => boolean;
	setLanguageAcquisition: (mode: string) => boolean;
	sanitizeUrl: (rawUrl: unknown, baseUrl?: string) => string;
	getContext: () => Record<string, unknown>;
	getFunnelStage: () => string | null;
}

interface AnalyticsVmWindow {
	_paq: unknown[][];
	location: Record<string, string>;
	ovfAnalytics?: OvfAnalyticsApi;
}

function runAnalyticsInVm(options: AnalyticsVmOptions = {}) {
	const pageConfigScript = fs.readFileSync(
		path.join(PUBLIC_DIR, "js", "page-config.js"),
		"utf-8",
	);
	const analyticsScript = fs.readFileSync(
		options.sourcePath ?? path.join(PUBLIC_DIR, "js", "analytics.js"),
		"utf-8",
	);

	const insertedScripts: Array<{ async: boolean; src: string }> = [];
	const existingScript = {
		parentNode: {
			insertBefore: (node: { async: boolean; src: string }, _ref: unknown) => {
				insertedScripts.push(node);
			},
		},
	};

	const serializedConfig = options.pageConfig
		? JSON.stringify(options.pageConfig)
		: null;

	const paq: unknown[][] = [];
	const parsedHref = options.href ?? "https://www.oceanviewflats.com/";
	let locationObj: Record<string, string> = {
		href: parsedHref,
		origin: "https://www.oceanviewflats.com",
		pathname: "/",
		search: "",
		hash: "",
	};
	try {
		const u = new URL(parsedHref);
		locationObj = {
			href: parsedHref,
			origin: u.origin,
			pathname: u.pathname,
			search: u.search,
			hash: u.hash,
		};
	} catch {
		// Allow malformed href testing
	}

	const win: AnalyticsVmWindow = {
		_paq: paq,
		location: locationObj,
	};

	const sessionStore = options.sessionStore ?? new Map<string, string>();
	const sessionStorageMock = options.throwingSessionStorage
		? {
				getItem: () => {
					throw new Error("SecurityError: sessionStorage disabled");
				},
				setItem: () => {
					throw new Error("SecurityError: sessionStorage disabled");
				},
				removeItem: () => {
					throw new Error("SecurityError: sessionStorage disabled");
				},
			}
		: {
				getItem: (key: string) => sessionStore.get(key) ?? null,
				setItem: (key: string, value: string) => {
					sessionStore.set(key, String(value));
				},
				removeItem: (key: string) => {
					sessionStore.delete(key);
				},
			};

	const sandbox = {
		window: win,
		location: locationObj,
		sessionStorage: sessionStorageMock,
		document: {
			documentElement: { lang: options.docLang ?? "en" },
			referrer: options.referrer ?? "",
			getElementById: (id: string) => {
				if (id === "ovf-page-config" && serializedConfig !== null) {
					return { textContent: serializedConfig };
				}
				return null;
			},
			createElement: (tag: string) => {
				if (tag === "script") {
					return { async: false, src: "" };
				}
				return {};
			},
			getElementsByTagName: (tag: string) =>
				tag === "script" ? [existingScript] : [],
			head: {
				appendChild: (node: { async: boolean; src: string }) => {
					insertedScripts.push(node);
				},
			},
		},
		URL,
		URLSearchParams,
		console: {
			error: () => {},
			warn: () => {},
			log: () => {},
		},
	};

	vm.createContext(sandbox);
	vm.runInContext(pageConfigScript, sandbox);
	vm.runInContext(analyticsScript, sandbox);

	return {
		window: sandbox.window,
		paq: JSON.parse(JSON.stringify(paq)) as unknown[][],
		rawPaq: paq,
		insertedScripts,
		sessionStore,
	};
}

test("#340 SSG build minifies, hashes, and emits js/analytics.js in strict order across all 6 locales and 16 pages", () => {
	const publicAnalyticsPath = path.join(PUBLIC_DIR, "js", "analytics.js");
	const distAnalyticsPath = path.join(DIST_DIR, "js", "analytics.js");
	assert.ok(
		fs.existsSync(publicAnalyticsPath),
		"public/js/analytics.js must exist",
	);
	assert.ok(
		fs.existsSync(distAnalyticsPath),
		"dist/js/analytics.js must exist",
	);

	const publicSize = fs.statSync(publicAnalyticsPath).size;
	const distSize = fs.statSync(distAnalyticsPath).size;
	assert.ok(
		distSize < publicSize,
		`dist/js/analytics.js (${distSize} B) must be Terser-minified compared to public/js/analytics.js (${publicSize} B)`,
	);

	const analyticsHash = sha256Short(distAnalyticsPath);
	const pageConfigHash = sha256Short(
		path.join(DIST_DIR, "js", "page-config.js"),
	);
	const mainHash = sha256Short(path.join(DIST_DIR, "js", "main.js"));

	for (const page of pages) {
		for (const lang of LANGUAGES) {
			const filename = page.filename(lang);
			const depth = filename.split("/").length - 1;
			const expectedPrefix =
				page.id === "404" ? "/" : depth === 0 ? "./" : "../".repeat(depth);
			const html = fs.readFileSync(path.join(DIST_DIR, filename), "utf-8");

			const configTagIdx = html.indexOf(
				'<script id="ovf-page-config" type="application/json">',
			);
			const pageConfigScript = `<script src="${expectedPrefix}js/page-config.js?v=${pageConfigHash}"></script>`;
			const analyticsScript = `<script src="${expectedPrefix}js/analytics.js?v=${analyticsHash}"></script>`;
			const mainScript = `<script src="${expectedPrefix}js/main.js?v=${mainHash}"></script>`;

			const pageConfigIdx = html.indexOf(pageConfigScript);
			const analyticsIdx = html.indexOf(analyticsScript);
			const mainIdx = html.indexOf(mainScript);

			assert.ok(configTagIdx > -1, `${filename} must include #ovf-page-config`);
			assert.ok(
				pageConfigIdx > configTagIdx,
				`${filename} must load page-config.js after #ovf-page-config`,
			);
			assert.ok(
				analyticsIdx > pageConfigIdx,
				`${filename} must load analytics.js immediately after page-config.js`,
			);
			assert.ok(
				mainIdx > analyticsIdx,
				`${filename} must load main.js after analytics.js`,
			);

			// Ensure analytics.js appears exactly once
			const analyticsMatches = html.match(/js\/analytics\.js\?v=[0-9a-f]{8}/g);
			assert.equal(
				analyticsMatches?.length,
				1,
				`${filename} must emit js/analytics.js exactly once`,
			);
		}
	}
});

test("#340 baseTemplate deduplicates explicit js/analytics.js entries in customScripts", () => {
	const html = baseTemplate({
		markup: "<main>Dedup</main>",
		lang: "en",
		title: "Test",
		description: "Desc",
		url: "https://www.oceanviewflats.com/",
		baseUrl: "https://www.oceanviewflats.com",
		ogImage: "https://www.oceanviewflats.com/images/home-hero.webp",
		hrefLangTags: "",
		structuredData: "{}",
		assetPrefix: "../",
		assetVersions: {
			"js/page-config.js": "11111111",
			"js/analytics.js": "22222222",
			"js/main.js": "33333333",
		},
		customScripts: ["js/analytics.js", "analytics.js", "js/main.js"],
		pageConfig: { pageId: "home", lang: "en" },
	});

	const matches = html.match(/analytics\.js/g);
	assert.equal(matches?.length, 1, "analytics.js must not be duplicated");

	const pageConfigPos = html.indexOf('src="../js/page-config.js?v=11111111"');
	const analyticsPos = html.indexOf('src="../js/analytics.js?v=22222222"');
	const mainPos = html.indexOf('src="../js/main.js?v=33333333"');
	assert.ok(pageConfigPos > -1 && analyticsPos > pageConfigPos);
	assert.ok(mainPos > analyticsPos);
});

test("#340 compiled #ovf-page-config emits exact analytics metadata across all 16 pages and all 6 locales", () => {
	const expectedByPageId: Record<string, PageAnalyticsConfig> = {
		home: {
			pageType: "home",
			propertyId: "portfolio",
			enableEngagementTracking: true,
		},
		Oceanview1707: {
			pageType: "property",
			propertyId: "1707",
			enableEngagementTracking: true,
		},
		Oceanview1606: {
			pageType: "property",
			propertyId: "1606",
			enableEngagementTracking: true,
		},
		contact: {
			pageType: "contact",
			propertyId: "portfolio",
			enableEngagementTracking: true,
		},
		privacy: {
			pageType: "privacy",
			propertyId: "portfolio",
			enableEngagementTracking: true,
		},
		registry: {
			pageType: "registry",
			propertyId: "portfolio",
			enableEngagementTracking: false,
		},
		guide: {
			pageType: "guide",
			propertyId: "portfolio",
			enableEngagementTracking: false,
		},
		"404": {
			pageType: "404",
			propertyId: "portfolio",
			enableEngagementTracking: false,
		},
		"booking-success": {
			pageType: "booking_result",
			propertyId: "portfolio",
			enableEngagementTracking: false,
		},
		"booking-failure": {
			pageType: "booking_result",
			propertyId: "portfolio",
			enableEngagementTracking: false,
		},
		"booking-pending": {
			pageType: "booking_result",
			propertyId: "portfolio",
			enableEngagementTracking: false,
		},
		"destination-guides": {
			pageType: "destination_hub",
			propertyId: "portfolio",
			enableEngagementTracking: true,
		},
	};

	for (const guide of DESTINATION_GUIDES) {
		expectedByPageId[`destination-guide-${guide.slug}`] = {
			pageType: "destination_guide",
			propertyId: "portfolio",
			guideSlug: guide.slug,
			enableEngagementTracking: true,
		};
	}

	assert.equal(
		Object.keys(expectedByPageId).length,
		pages.length,
		"Every configured page must be covered in the analytics metadata matrix",
	);

	for (const page of pages) {
		const expected = expectedByPageId[page.id];
		assert.deepEqual(getPageAnalyticsConfig(page.id), expected);

		for (const lang of LANGUAGES) {
			const filename = page.filename(lang);
			const html = fs.readFileSync(path.join(DIST_DIR, filename), "utf-8");
			const match = html.match(
				/<script id="ovf-page-config" type="application\/json">(.*?)<\/script>/,
			);
			assert.ok(match, `#ovf-page-config must exist in ${filename}`);
			const parsed = JSON.parse(match[1]);

			assert.equal(parsed.lang, lang);
			assert.deepEqual(
				parsed.analytics,
				expected,
				`${filename} analytics metadata mismatch`,
			);

			if (page.id === "registry") {
				assert.ok(
					Array.isArray(parsed.minorDocTypes) &&
						parsed.minorDocTypes.length > 0,
					`${filename} must preserve minorDocTypes alongside analytics`,
				);
			}
		}
	}
});

test("#340 legacy inline Matomo bootstrap is removed from public/js/main.js without breaking standalone execution", () => {
	const mainJsSource = fs.readFileSync(
		path.join(PUBLIC_DIR, "js", "main.js"),
		"utf-8",
	);

	assert.ok(
		!mainJsSource.includes("stats.fractalserver.com"),
		"main.js must not contain legacy inline Matomo tracker URL",
	);
	assert.ok(
		!mainJsSource.includes("setSiteId"),
		"main.js must not call setSiteId directly",
	);
	assert.ok(
		!mainJsSource.includes("trackPageView"),
		"main.js must not call trackPageView directly",
	);

	const insertedScripts: unknown[] = [];
	const paq: unknown[][] = [];
	const sandbox = {
		window: {
			_paq: paq,
			scrollY: 0,
			location: { search: "", pathname: "/" },
			addEventListener: () => {},
		},
		document: {
			documentElement: { lang: "en" },
			createElement: () => ({ async: false, src: "" }),
			getElementsByTagName: () => [
				{
					parentNode: {
						insertBefore: (n: unknown) => insertedScripts.push(n),
					},
				},
			],
			getElementById: () => null,
			addEventListener: () => {},
		},
		localStorage: { getItem: () => null, setItem: () => {} },
		console: { error: () => {}, warn: () => {}, log: () => {} },
	};

	vm.createContext(sandbox);
	assert.doesNotThrow(() => {
		vm.runInContext(mainJsSource, sandbox);
	});
	assert.equal(
		paq.length,
		0,
		"Running main.js standalone must not queue Matomo bootstrap commands",
	);
	assert.equal(
		insertedScripts.length,
		0,
		"Running main.js standalone must not inject matomo.js script tags",
	);
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
	test(`#340 ${variant.label} queues cookieless init, sanitized URLs, Dimensions 1-5 before trackPageView, and loads matomo.js async`, () => {
		const { window, paq, insertedScripts } = runAnalyticsInVm({
			sourcePath: variant.file,
			pageConfig: {
				pageId: "Oceanview1707",
				lang: "es",
				assetPrefix: "../",
				apiBase: "../api/",
				analytics: {
					pageType: "property",
					propertyId: "1707",
					enableEngagementTracking: true,
				},
				i18n: {},
			},
			href: "https://www.oceanviewflats.com/Oceanview1707/es.html?utm_source=google&utm_medium=cpc&mtm_campaign=santa_marta&fbclid=leak123#booking",
			referrer:
				"https://www.oceanviewflats.com/es.html?utm_campaign=hero&code=ovf_secret#top",
		});

		const api = window.ovfAnalytics;
		assert.ok(api, "window.ovfAnalytics must be defined");
		assert.equal(api.TRACKER_URL, "https://stats.fractalserver.com/");
		assert.equal(api.SITE_ID, "2");
		assert.deepEqual(JSON.parse(JSON.stringify(api.GOALS)), {
			CONTACT_GENERAL: 1,
			CONTACT_DATED: 2,
			AIRBNB_REDIRECT: 3,
			WHATSAPP_CONTACT: 4,
			DIRECT_RESERVATION_CONFIRMED: 5,
			PENDING_RESERVATION_CREATED: 6,
			GUEST_REGISTRY_COMPLETED: 7,
		});
		assert.deepEqual(JSON.parse(JSON.stringify(api.DIMENSIONS)), {
			LOCALE: 1,
			PAGE_TYPE: 2,
			PROPERTY: 3,
			LANGUAGE_ACQUISITION: 4,
			FUNNEL_STAGE: 5,
		});
		assert.deepEqual(JSON.parse(JSON.stringify(api.FUNNEL_STAGES)), {
			discovery: 1,
			property_viewed: 2,
			quote_generated: 3,
			hold_created: 4,
			payment_brick_ready: 5,
			payment_submitted: 6,
			confirmed: 7,
		});

		const idx = (cmd: string) => paq.findIndex((entry) => entry[0] === cmd);
		const trackPageViewIdx = idx("trackPageView");
		assert.ok(trackPageViewIdx > 0, "trackPageView must be queued");

		// Order guarantees before trackPageView
		assert.equal(paq[0][0], "disableCookies");
		assert.ok(idx("disableCookies") < trackPageViewIdx);
		assert.ok(idx("setCustomUrl") < trackPageViewIdx);
		assert.ok(idx("setReferrerUrl") < trackPageViewIdx);

		// Verify exact initial command sequence
		assert.deepEqual(paq, [
			["disableCookies"],
			[
				"setCustomUrl",
				"https://www.oceanviewflats.com/Oceanview1707/es.html?utm_source=google&utm_medium=cpc&mtm_campaign=santa_marta",
			],
			[
				"setReferrerUrl",
				"https://www.oceanviewflats.com/es.html?utm_campaign=hero",
			],
			["setCustomDimension", 1, "es"],
			["setCustomDimension", 2, "property"],
			["setCustomDimension", 3, "1707"],
			["setCustomDimension", 4, "direct_locale"],
			["setCustomDimension", 5, "property_viewed"],
			["trackPageView"],
			["enableLinkTracking"],
			["enableHeartBeatTimer", 15],
			["setTrackerUrl", "https://stats.fractalserver.com/matomo.php"],
			["setSiteId", "2"],
		]);

		// Non-404 pages must not emit setDocumentTitle
		assert.equal(idx("setDocumentTitle"), -1);

		// Async matomo.js script injection
		assert.equal(insertedScripts.length, 1);
		assert.equal(insertedScripts[0].async, true);
		assert.equal(
			insertedScripts[0].src,
			"https://stats.fractalserver.com/matomo.js",
		);
	});
}

test("#340 strict URL and referrer allowlist strips all sensitive parameters, fragments, credentials, and handles malformed referrers", () => {
	const sensitiveQuery = [
		"code=ovf_secret_uid_999",
		"token=tok_abc123",
		"guest=Jane%20Doe",
		"name=Jane",
		"id=42",
		"barcode=7701234567890",
		"verif_code=987654",
		"voucher_url=https%3A%2F%2Fefecty.example%2Fv%2F123",
		"check_in=2026-12-10",
		"check_out=2026-12-15",
		"email=jane%40example.com",
		"gclid=abc",
		"utm_source=email",
		"utm_medium=transactional",
		"utm_campaign=arrival_guide",
		"utm_content=unlock_btn",
		"utm_term=santa_marta",
		"mtm_campaign=post_booking",
		"mtm_kwd=beachfront",
		"mtm_source=mailer",
		"UTM_SOURCE=upper_kept",
	].join("&");

	const { window, paq } = runAnalyticsInVm({
		pageConfig: {
			pageId: "guide",
			lang: "en",
			assetPrefix: "../",
			apiBase: "../api/",
			analytics: {
				pageType: "guide",
				propertyId: "portfolio",
				enableEngagementTracking: false,
			},
			i18n: {},
		},
		href: `https://www.oceanviewflats.com/guide/?${sensitiveQuery}#credentials-panel`,
		referrer: `https://www.oceanviewflats.com/registry/?code=ovf_secret_uid_999&guest=Jane&check_in=2026-12-10&check_out=2026-12-15&utm_source=email&mtm_medium=flow#step-2`,
	});

	const customUrlCmd = paq.find((e) => e[0] === "setCustomUrl");
	const referrerCmd = paq.find((e) => e[0] === "setReferrerUrl");
	assert.ok(customUrlCmd, "setCustomUrl must be queued");
	assert.ok(referrerCmd, "setReferrerUrl must be queued");

	const customUrl = String(customUrlCmd[1]);
	const referrerUrl = String(referrerCmd[1]);

	// Preserves allowlisted utm_* and mtm_*
	assert.equal(
		customUrl,
		"https://www.oceanviewflats.com/guide/?utm_source=email&utm_medium=transactional&utm_campaign=arrival_guide&utm_content=unlock_btn&utm_term=santa_marta&mtm_campaign=post_booking&mtm_kwd=beachfront&mtm_source=mailer&utm_source=upper_kept",
	);
	assert.equal(
		referrerUrl,
		"https://www.oceanviewflats.com/registry/?utm_source=email&mtm_medium=flow",
	);

	// Zero sensitive params or fragments remain
	for (const forbidden of [
		"code=",
		"ovf_secret",
		"token=",
		"guest=",
		"Jane",
		"name=",
		"id=",
		"barcode=",
		"verif_code=",
		"voucher_url=",
		"check_in=",
		"check_out=",
		"email=",
		"gclid=",
		"#",
	]) {
		assert.ok(
			!customUrl.includes(forbidden),
			`setCustomUrl must not contain ${forbidden}: ${customUrl}`,
		);
		assert.ok(
			!referrerUrl.includes(forbidden),
			`setReferrerUrl must not contain ${forbidden}: ${referrerUrl}`,
		);
	}

	// Direct sanitizeUrl edge cases
	const { sanitizeUrl } = window.ovfAnalytics;
	assert.equal(sanitizeUrl(""), "");
	assert.equal(sanitizeUrl(null), "");
	assert.equal(sanitizeUrl("javascript:alert(1)?utm_source=x"), "");
	assert.equal(
		sanitizeUrl(
			"https://user:pass@www.oceanviewflats.com/contact/?id=5&utm_source=gbp#form",
		),
		"https://www.oceanviewflats.com/contact/?utm_source=gbp",
	);
	assert.equal(
		sanitizeUrl("/registry/?code=ovf_123&mtm_source=qr#top"),
		"/registry/?mtm_source=qr",
	);
	assert.equal(
		sanitizeUrl(
			"https://www.oceanviewflats.com/guide/?mtm_=empty_suffix&mtm_cid=42",
		),
		"https://www.oceanviewflats.com/guide/?mtm_cid=42",
	);
	assert.equal(sanitizeUrl("not a valid url?code=ovf_secret#frag"), "");
	assert.equal(sanitizeUrl("https://bad host/path?code=ovf_secret#frag"), "");

	// Malformed document.referrer safely queues empty setReferrerUrl without throwing
	const malformedRun = runAnalyticsInVm({
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
		referrer: "http://[malformed/referrer?code=ovf_secret&guest=Leak#frag",
	});
	const malformedRefCmd = malformedRun.paq.find(
		(e) => e[0] === "setReferrerUrl",
	);
	assert.deepEqual(malformedRefCmd, ["setReferrerUrl", ""]);
});

test("#340 Funnel Stage (Custom Dimension 5) advances strictly monotonically and never downgrades", () => {
	const { window, rawPaq } = runAnalyticsInVm({
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

	const api = window.ovfAnalytics;
	assert.equal(api.getFunnelStage(), "discovery");

	// Duplicate current stage -> ignored
	assert.equal(api.advanceFunnelStage("discovery"), false);

	// Forward progression through all stages
	const expectedOrder = [
		"property_viewed",
		"quote_generated",
		"hold_created",
		"payment_brick_ready",
		"payment_submitted",
		"confirmed",
	];

	for (const stage of expectedOrder) {
		assert.equal(api.advanceFunnelStage(stage), true);
		assert.equal(api.getFunnelStage(), stage);
		// Repeating the same stage is a no-op
		assert.equal(api.advanceFunnelStage(stage), false);
		// Attempting to downgrade to discovery is rejected
		assert.equal(api.advanceFunnelStage("discovery"), false);
	}

	// Attempting to downgrade from confirmed via either helper is rejected
	assert.equal(api.advanceFunnelStage("hold_created"), false);
	assert.equal(api.advanceFunnelStage("payment_submitted"), false);
	assert.equal(api.setCustomDimension(5, "property_viewed"), false);
	assert.equal(api.advanceFunnelStage("unknown_stage"), false);
	assert.equal(api.getFunnelStage(), "confirmed");

	const dim5Values = rawPaq
		.filter((entry) => entry[0] === "setCustomDimension" && entry[1] === 5)
		.map((entry) => entry[2]);

	assert.deepEqual(dim5Values, ["discovery", ...expectedOrder]);
});

test("#340 404 pages emit Matomo standard 404/URL = <sanitized-path>/From = <sanitized-referrer> document title before trackPageView", () => {
	const { paq } = runAnalyticsInVm({
		pageConfig: {
			pageId: "404",
			lang: "fr",
			assetPrefix: "/",
			apiBase: "/api/",
			analytics: {
				pageType: "404",
				propertyId: "portfolio",
				enableEngagementTracking: false,
			},
		},
		href: "https://www.oceanviewflats.com/missing/flat?code=ovf_secret&guest=Marie&utm_source=newsletter&mtm_campaign=spring#broken",
		referrer:
			"https://www.oceanviewflats.com/Oceanview1606/fr.html?token=tok_secret&utm_medium=social#top",
	});

	const titleIdx = paq.findIndex((entry) => entry[0] === "setDocumentTitle");
	const trackIdx = paq.findIndex((entry) => entry[0] === "trackPageView");
	assert.ok(titleIdx > -1, "404 page must emit setDocumentTitle");
	assert.ok(
		titleIdx < trackIdx,
		"setDocumentTitle must be queued before trackPageView on 404 pages",
	);

	const docTitle = String(paq[titleIdx][1]);
	assert.match(docTitle, /^404\/URL = .+\/From = .+$/);

	// Verify zero sensitive leakage in 404 title
	for (const forbidden of [
		"ovf_secret",
		"Marie",
		"tok_secret",
		"#broken",
		"#top",
	]) {
		assert.ok(
			!docTitle.includes(forbidden),
			`404 document title must not leak ${forbidden}`,
		);
	}

	const match = docTitle.match(/^404\/URL = ([^/]+)\/From = (.*)$/);
	assert.ok(
		match,
		"404 title must match Matomo 404/URL = .../From = ... structure",
	);
	assert.equal(
		decodeURIComponent(match[1]),
		"/missing/flat?utm_source=newsletter&mtm_campaign=spring",
	);
	assert.equal(
		decodeURIComponent(match[2]),
		"https://www.oceanviewflats.com/Oceanview1606/fr.html?utm_medium=social",
	);
});

test("#340 public facade helpers (trackEvent, trackGoal, setCustomDimension, setLanguageAcquisition, getContext) validate inputs cleanly", () => {
	const { window, rawPaq } = runAnalyticsInVm({
		pageConfig: {
			pageId: "destination-guide-digital-nomad-remote-work",
			lang: "de",
			analytics: {
				pageType: "destination_guide",
				propertyId: "portfolio",
				guideSlug: "digital-nomad-remote-work",
				enableEngagementTracking: true,
			},
		},
		href: "https://www.oceanviewflats.com/santa-marta/digital-nomad-remote-work/de.html",
	});

	const api = window.ovfAnalytics;
	const ctx = api.getContext();
	assert.deepEqual(JSON.parse(JSON.stringify(ctx)), {
		pageId: "destination-guide-digital-nomad-remote-work",
		lang: "de",
		pageType: "destination_guide",
		propertyId: "portfolio",
		guideSlug: "digital-nomad-remote-work",
		enableEngagementTracking: true,
	});
	// Mutating returned copy must not mutate internal context
	ctx.propertyId = "1707";
	assert.equal(api.getContext().propertyId, "portfolio");

	const baselineLen = rawPaq.length;

	// trackEvent
	assert.equal(api.trackEvent("", "Click"), false);
	assert.equal(api.trackEvent("Booking", ""), false);
	assert.equal(
		api.trackEvent("Booking", "QuoteGenerated", "1707:2026-12", 1450000),
		true,
	);
	assert.equal(api.trackEvent("Navigation", "FaqToggle", "faq-1"), true);
	assert.equal(api.trackEvent("Gallery", "Open"), true);

	// trackGoal
	assert.equal(api.trackGoal(0), false);
	assert.equal(api.trackGoal(8), false);
	assert.equal(api.trackGoal("NON_EXISTENT"), false);
	assert.equal(api.trackGoal(5, 1450000), true);
	assert.equal(api.trackGoal("PENDING_RESERVATION_CREATED"), true);
	assert.equal(api.trackGoal(7), true);

	// setLanguageAcquisition
	assert.equal(api.setLanguageAcquisition("invalid_mode"), false);
	assert.equal(api.setLanguageAcquisition("manual_switched"), true);

	// setCustomDimension
	assert.equal(api.setCustomDimension(0, "x"), false);
	assert.equal(api.setCustomDimension(3, ""), false);
	assert.equal(api.setCustomDimension(3, "1606"), true);

	const appended = JSON.parse(JSON.stringify(rawPaq.slice(baselineLen)));
	assert.deepEqual(appended, [
		["trackEvent", "Booking", "QuoteGenerated", "1707:2026-12", 1450000],
		["trackEvent", "Navigation", "FaqToggle", "faq-1"],
		["trackEvent", "Gallery", "Open"],
		["trackGoal", 5, 1450000],
		["trackGoal", 6],
		["trackGoal", 7],
		["setCustomDimension", 4, "manual_switched"],
		["setCustomDimension", 3, "1606"],
	]);
});

test("#339 Funnel Stage (Dimension 5) persists monotonically in sessionStorage across navigations and never downgrades", () => {
	const sharedSession = new Map<string, string>();

	// 1. Landing on homepage starts at discovery
	const homeRun = runAnalyticsInVm({
		sessionStore: sharedSession,
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
	assert.equal(homeRun.window.ovfAnalytics?.getFunnelStage(), "discovery");
	assert.equal(sharedSession.get("ovf_funnel_stage"), "discovery");

	// 2. Navigating to Property 1707 upgrades to property_viewed and advances to confirmed
	const propRun = runAnalyticsInVm({
		sessionStore: sharedSession,
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
	});
	assert.equal(
		propRun.window.ovfAnalytics?.getFunnelStage(),
		"property_viewed",
	);
	assert.equal(
		propRun.window.ovfAnalytics?.advanceFunnelStage("confirmed"),
		true,
	);
	assert.equal(sharedSession.get("ovf_funnel_stage"), "confirmed");

	// 3. Navigating to booking-success, booking-pending, registry, guide, or home MUST keep confirmed before trackPageView
	const postCheckoutPages = [
		{
			pageId: "booking-success",
			pageType: "booking_result",
			href: "https://www.oceanviewflats.com/booking-success/",
		},
		{
			pageId: "booking-pending",
			pageType: "booking_result",
			href: "https://www.oceanviewflats.com/booking-pending/",
		},
		{
			pageId: "registry",
			pageType: "registry",
			href: "https://www.oceanviewflats.com/registry/",
		},
		{
			pageId: "guide",
			pageType: "guide",
			href: "https://www.oceanviewflats.com/guide/",
		},
		{
			pageId: "home",
			pageType: "home",
			href: "https://www.oceanviewflats.com/",
		},
	];

	for (const next of postCheckoutPages) {
		const navRun = runAnalyticsInVm({
			sessionStore: sharedSession,
			pageConfig: {
				pageId: next.pageId,
				lang: "en",
				analytics: {
					pageType: next.pageType,
					propertyId: "portfolio",
					enableEngagementTracking: next.pageType === "home",
				},
			},
			href: next.href,
		});

		const dim5Idx = navRun.paq.findIndex(
			(cmd) => cmd[0] === "setCustomDimension" && cmd[1] === 5,
		);
		const trackIdx = navRun.paq.findIndex((cmd) => cmd[0] === "trackPageView");
		assert.ok(
			dim5Idx > -1 && dim5Idx < trackIdx,
			`${next.pageId} must queue Dimension 5 before trackPageView`,
		);
		assert.deepEqual(navRun.paq[dim5Idx], [
			"setCustomDimension",
			5,
			"confirmed",
		]);
		assert.equal(
			navRun.window.ovfAnalytics?.getFunnelStage(),
			"confirmed",
			`${next.pageId} must never downgrade Funnel Stage back to discovery`,
		);
		assert.equal(
			navRun.window.ovfAnalytics?.advanceFunnelStage("property_viewed"),
			false,
		);
	}

	// 4. Tampered sessionStorage value and throwing sessionStorage degrade safely
	const tamperedSession = new Map<string, string>([
		["ovf_funnel_stage", "invalid_stage"],
	]);
	const tamperedRun = runAnalyticsInVm({
		sessionStore: tamperedSession,
		pageConfig: {
			pageId: "Oceanview1606",
			lang: "en",
			analytics: {
				pageType: "property",
				propertyId: "1606",
				enableEngagementTracking: true,
			},
		},
		href: "https://www.oceanviewflats.com/Oceanview1606/",
	});
	assert.equal(
		tamperedRun.window.ovfAnalytics?.getFunnelStage(),
		"property_viewed",
	);
	assert.equal(tamperedSession.get("ovf_funnel_stage"), "property_viewed");

	assert.doesNotThrow(() => {
		const throwingRun = runAnalyticsInVm({
			throwingSessionStorage: true,
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
		assert.equal(
			throwingRun.window.ovfAnalytics?.getFunnelStage(),
			"discovery",
		);
	});
});

test("#339 US 35 public/js/main.js and public/js/registry.js contain zero duplicated numeric Goal ID maps", () => {
	const mainSource = fs.readFileSync(
		path.join(PUBLIC_DIR, "js", "main.js"),
		"utf-8",
	);
	const registrySource = fs.readFileSync(
		path.join(PUBLIC_DIR, "js", "registry.js"),
		"utf-8",
	);

	assert.ok(
		!mainSource.includes("fallbackGoals"),
		"public/js/main.js must not duplicate numeric Goal IDs in a fallbackGoals map",
	);
	assert.ok(
		!/CONTACT_GENERAL\s*:\s*1/.test(mainSource),
		"public/js/main.js must not hardcode numeric Goal ID mappings",
	);
	assert.ok(
		!/GUEST_REGISTRY_COMPLETED[\s\S]{0,80}:\s*7/.test(registrySource),
		"public/js/registry.js must not hardcode a numeric : 7 Goal fallback",
	);
	assert.ok(
		registrySource.includes('trackGoal("GUEST_REGISTRY_COMPLETED")'),
		"public/js/registry.js must pass the canonical goal key to window.ovfAnalytics.trackGoal",
	);
});
