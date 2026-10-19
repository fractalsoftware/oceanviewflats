import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import test from "node:test";
import vm from "node:vm";
import { baseTemplate } from "../../src/templates/base";

test("baseTemplate escapes script tags inside pageConfig JSON", () => {
	const maliciousConfig = {
		lang: "en",
		evil: '</script><script>alert("xss")</script>',
	};
	const html = baseTemplate({
		markup: "<div>Content</div>",
		lang: "en",
		title: "Test",
		description: "Test Desc",
		url: "https://example.com",
		baseUrl: "https://example.com",
		ogImage: "https://example.com/og.jpg",
		hrefLangTags: "",
		structuredData: "{}",
		assetPrefix: "./",
		pageConfig: maliciousConfig,
	});

	assert.ok(
		!html.includes("</script><script>"),
		"HTML must not contain raw closing script tags inside json config",
	);
	assert.ok(html.includes("\\u003c/script>"), "HTML must escape < as \\u003c");

	const match = html.match(
		/<script id="ovf-page-config" type="application\/json">(.*?)<\/script>/,
	);
	assert.ok(match, "#ovf-page-config element must exist");
	const parsed = JSON.parse(match[1]);
	assert.equal(parsed.evil, maliciousConfig.evil);
});

test("SSG output files embed valid #ovf-page-config across locales", () => {
	const distDir = path.join(process.cwd(), "dist");
	const samplePaths = [
		{ file: "index.html", lang: "en", depth: 0 },
		{ file: "es.html", lang: "es", depth: 0 },
		{ file: "fr.html", lang: "fr", depth: 0 },
		{ file: "it.html", lang: "it", depth: 0 },
		{ file: "de.html", lang: "de", depth: 0 },
		{ file: "ja.html", lang: "ja", depth: 0 },
		{ file: "registry/index.html", lang: "en", depth: 1 },
		{ file: "registry/es.html", lang: "es", depth: 1 },
		{ file: "guide/index.html", lang: "en", depth: 1 },
		{ file: "guide/ja.html", lang: "ja", depth: 1 },
	];

	for (const sample of samplePaths) {
		const fullPath = path.join(distDir, sample.file);
		assert.ok(fs.existsSync(fullPath), `File must exist: ${sample.file}`);
		const content = fs.readFileSync(fullPath, "utf-8");

		const match = content.match(
			/<script id="ovf-page-config" type="application\/json">(.*?)<\/script>/,
		);
		assert.ok(match, `#ovf-page-config must exist in ${sample.file}`);

		const parsed = JSON.parse(match[1]);
		assert.equal(parsed.lang, sample.lang);
		const expectedPrefix =
			sample.depth === 0 ? "./" : "../".repeat(sample.depth);
		assert.equal(parsed.assetPrefix, expectedPrefix);
		assert.equal(parsed.apiBase, `${expectedPrefix}api/`);
		assert.equal(typeof parsed.i18n, "object");

		// Verify page-config.js is included
		assert.ok(
			content.includes(
				`<script src="${expectedPrefix}js/page-config.js"></script>`,
			),
			`page-config.js script tag must be included in ${sample.file}`,
		);
	}
});

test("client translation helper interpolates tokens and handles fallbacks", () => {
	const pageConfigCode = fs.readFileSync(
		path.join(process.cwd(), "public/js/page-config.js"),
		"utf-8",
	);

	const mockConfig = {
		pageId: "guide",
		lang: "en",
		assetPrefix: "./",
		apiBase: "./api/",
		i18n: {
			guideWelcomeWithGuest:
				"Welcome to your beachside home, {guestName}! We are thrilled to host you.",
			guideWelcomeGeneric:
				"Welcome to your beachside home! We are thrilled to host you.",
		},
	};

	const elContent: string | null = JSON.stringify(mockConfig);
	const sandbox = {
		document: {
			getElementById: (id: string) => {
				if (id === "ovf-page-config" && elContent) {
					return { textContent: elContent };
				}
				return null;
			},
		},
		window: {} as Record<string, any>,
		console: {
			error: () => {},
		},
	};
	vm.createContext(sandbox);
	vm.runInContext(pageConfigCode, sandbox);

	const { getPageConfig, t } = sandbox.window;
	assert.deepEqual(JSON.parse(JSON.stringify(getPageConfig())), mockConfig);

	// Token interpolation test
	const interpolated = t("guideWelcomeWithGuest", {
		guestName: "Sarah Connor",
	});
	assert.equal(
		interpolated,
		"Welcome to your beachside home, Sarah Connor! We are thrilled to host you.",
	);

	// Generic greeting test
	const generic = t("guideWelcomeGeneric");
	assert.equal(
		generic,
		"Welcome to your beachside home! We are thrilled to host you.",
	);

	// Missing key with fallback
	const fallbackResult = t("missing_key", {}, "Default Fallback");
	assert.equal(fallbackResult, "Default Fallback");

	// Missing key without fallback
	const keyResult = t("missing_key");
	assert.equal(keyResult, "missing_key");
});

test("registry.js has excised duplicate dictionaries and uses window.t and apiBase", () => {
	const registryJsPath = path.join(
		process.cwd(),
		"public",
		"js",
		"registry.js",
	);
	const content = fs.readFileSync(registryJsPath, "utf-8");

	// Ensure duplicate dictionaries are eradicated
	assert.ok(
		!content.includes("const errorMsgs ="),
		"registry.js must not contain duplicate errorMsgs dictionary",
	);
	assert.ok(
		!content.includes("const submittingMsgs ="),
		"registry.js must not contain duplicate submittingMsgs dictionary",
	);
	assert.ok(
		!content.includes("const defaultDates ="),
		"registry.js must not contain duplicate defaultDates dictionary",
	);
	assert.ok(
		!content.includes("const defaultProperty ="),
		"registry.js must not contain duplicate defaultProperty dictionary",
	);

	// Ensure window.t is invoked
	assert.ok(
		content.includes("registrySubmitting"),
		"registry.js must reference registrySubmitting translation key",
	);
	assert.ok(
		content.includes("registryGenericError"),
		"registry.js must reference registryGenericError translation key",
	);

	// Ensure apiBase is used instead of procedural form action replacement
	assert.ok(
		content.includes("pageConfig.apiBase"),
		"registry.js must use pageConfig.apiBase",
	);
});

test("guide.js has excised introTemplates and uses canonical greeting separation and pageConfig", () => {
	const guideJsPath = path.join(process.cwd(), "public", "js", "guide.js");
	const content = fs.readFileSync(guideJsPath, "utf-8");

	// Ensure duplicate template dictionary and regex logic are excised
	assert.ok(
		!content.includes("const introTemplates ="),
		"guide.js must not contain duplicate introTemplates dictionary",
	);
	assert.ok(
		!content.includes("function getGenericGreeting"),
		"guide.js must not contain procedural getGenericGreeting function",
	);

	// Ensure canonical keys are referenced
	assert.ok(
		content.includes("guideWelcomeWithGuest"),
		"guide.js must reference guideWelcomeWithGuest translation key",
	);
	assert.ok(
		content.includes("guideWelcomeGeneric"),
		"guide.js must reference guideWelcomeGeneric translation key",
	);

	// Ensure pageConfig assetPrefix and apiBase are used
	assert.ok(
		content.includes("pageConfig.assetPrefix"),
		"guide.js must use pageConfig.assetPrefix",
	);
	assert.ok(
		content.includes("pageConfig.apiBase"),
		"guide.js must use pageConfig.apiBase",
	);
});

test("main.js uses window.t and pageConfig for booking and contact flows", () => {
	const mainJsPath = path.join(process.cwd(), "public", "js", "main.js");
	const content = fs.readFileSync(mainJsPath, "utf-8");

	// Ensure booking keys are referenced via t()
	assert.ok(
		content.includes("dbGatewayError"),
		"main.js must reference dbGatewayError",
	);
	assert.ok(content.includes("dbSubmit"), "main.js must reference dbSubmit");
	assert.ok(
		content.includes("dbDeclined"),
		"main.js must reference dbDeclined",
	);
	assert.ok(
		content.includes("dbVerifying"),
		"main.js must reference dbVerifying",
	);
	assert.ok(content.includes("dbSecured"), "main.js must reference dbSecured");
	assert.ok(
		content.includes("dbFillFields"),
		"main.js must reference dbFillFields",
	);
	assert.ok(
		content.includes("dbNetworkError"),
		"main.js must reference dbNetworkError",
	);

	// Ensure contact keys are referenced via t()
	assert.ok(
		content.includes("contactSuccess"),
		"main.js must reference contactSuccess",
	);
	assert.ok(
		content.includes("contactError"),
		"main.js must reference contactError",
	);
	assert.ok(
		content.includes("contactSubmitting"),
		"main.js must reference contactSubmitting",
	);
	assert.ok(
		content.includes("contactSubmit"),
		"main.js must reference contactSubmit",
	);
	assert.ok(
		content.includes("contactDateError"),
		"main.js must reference contactDateError",
	);

	// Ensure apiBase is used for payments and bookings
	assert.ok(
		content.includes("pageConfig.apiBase"),
		"main.js must reference pageConfig.apiBase",
	);
});

test("obsolete data-msg-* attributes are completely excised across src and public/js", () => {
	const checkDir = (dir: string) => {
		const entries = fs.readdirSync(dir, { withFileTypes: true });
		for (const entry of entries) {
			const fullPath = path.join(dir, entry.name);
			if (entry.isDirectory()) {
				checkDir(fullPath);
			} else if (/\.(tsx?|jsx?|html)$/.test(entry.name)) {
				const content = fs.readFileSync(fullPath, "utf-8");
				assert.ok(
					!content.includes("data-msg-"),
					`File ${fullPath} must not contain deprecated data-msg-* attribute`,
				);
			}
		}
	};

	checkDir(path.join(process.cwd(), "src", "components"));
	checkDir(path.join(process.cwd(), "src", "pages"));
	checkDir(path.join(process.cwd(), "public", "js"));
});

test("Registry page pre-renders 12 guest cards and registry.js honors max_guests override", () => {
	const registryHtml = fs.readFileSync(
		path.join(process.cwd(), "dist", "registry", "index.html"),
		"utf-8",
	);
	for (let num = 1; num <= 12; num++) {
		assert.ok(
			registryHtml.includes(`id="guest-card-${num}"`),
			`Registry HTML must pre-render #guest-card-${num}`,
		);
	}

	const registryJs = fs.readFileSync(
		path.join(process.cwd(), "public", "js", "registry.js"),
		"utf-8",
	);
	assert.ok(
		registryJs.includes("r.max_guests"),
		"registry.js must read r.max_guests from lookup payload",
	);
	assert.ok(
		registryJs.includes("ABSOLUTE_MAX_GUESTS = 12"),
		"registry.js must support up to 12 guest cards",
	);
});

test("Multilingual Data Processing Policy (/privacy) pages are generated across all 6 locales with canonical Data Controller and ARCO SLAs", () => {
	const distDir = path.join(process.cwd(), "dist");
	const privacyFiles = [
		{ file: "privacy/index.html", lang: "en" },
		{ file: "privacy/es.html", lang: "es" },
		{ file: "privacy/fr.html", lang: "fr" },
		{ file: "privacy/it.html", lang: "it" },
		{ file: "privacy/de.html", lang: "de" },
		{ file: "privacy/ja.html", lang: "ja" },
	];

	for (const item of privacyFiles) {
		const fullPath = path.join(distDir, item.file);
		assert.ok(
			fs.existsSync(fullPath),
			`Privacy policy file must exist: ${item.file}`,
		);
		const content = fs.readFileSync(fullPath, "utf-8");

		assert.ok(
			content.includes(`lang="${item.lang}"`),
			`${item.file} must have html lang="${item.lang}"`,
		);
		assert.ok(
			content.includes("OceanViewFlats — Manuel Enrique Herrera"),
			`${item.file} must display canonical Data Controller name`,
		);
		assert.ok(
			content.includes("RNT #268781") && content.includes("RNT #270413"),
			`${item.file} must display both RNT registration numbers`,
		);
		assert.ok(
			content.includes("Calle 26 # 2-80, Playa Salguero, Santa Marta"),
			`${item.file} must display physical domicile address`,
		);
		assert.ok(
			content.includes("rentals@oceanviewflats.com"),
			`${item.file} must display Habeas Data email`,
		);
		assert.ok(
			content.includes("+57 (310) 815-5234"),
			`${item.file} must display contact phone`,
		);
		assert.ok(
			content.includes("2026-10"),
			`${item.file} must display PRIVACY_POLICY_VERSION 2026-10`,
		);
		assert.ok(
			content.includes("Mercado Pago") &&
				content.includes("Edificio Salguero Sunset"),
			`${item.file} must disclose third-party recipients`,
		);
		assert.ok(
			content.includes("10") && content.includes("15"),
			`${item.file} must display 10 and 15 business day ARCO SLAs`,
		);
		assert.ok(
			content.includes('id="footer-privacy-link"'),
			`${item.file} must contain the global footer privacy link`,
		);
	}
});

test("Contact and Direct Booking forms render unchecked-by-default required privacy_consent checkboxes linking to localized /privacy", () => {
	const distDir = path.join(process.cwd(), "dist");

	const contactEn = fs.readFileSync(
		path.join(distDir, "contact", "index.html"),
		"utf-8",
	);
	assert.ok(
		contactEn.includes('id="privacy-consent"') &&
			contactEn.includes('name="privacy_consent"') &&
			contactEn.includes('name="privacy_policy_version" value="2026-10"'),
		"Contact page must render #privacy-consent checkbox and privacy_policy_version=2026-10",
	);
	assert.ok(
		!contactEn.includes('id="privacy-consent" checked'),
		"Contact #privacy-consent must be unchecked by default",
	);
	assert.ok(
		contactEn.includes('href="../privacy/index.html"'),
		"English Contact page must link to ../privacy/index.html",
	);

	const contactEs = fs.readFileSync(
		path.join(distDir, "contact", "es.html"),
		"utf-8",
	);
	assert.ok(
		contactEs.includes('href="../privacy/es.html"'),
		"Spanish Contact page must link to ../privacy/es.html",
	);

	for (const prop of ["Oceanview1707", "Oceanview1606"]) {
		const propEn = fs.readFileSync(
			path.join(distDir, prop, "index.html"),
			"utf-8",
		);
		assert.ok(
			propEn.includes('id="booking-privacy-consent"') &&
				propEn.includes('name="privacy_consent"') &&
				propEn.includes('name="privacy_policy_version" value="2026-10"'),
			`${prop} must render #booking-privacy-consent checkbox and privacy_policy_version=2026-10`,
		);
		assert.ok(
			!propEn.includes('id="booking-privacy-consent" checked'),
			`${prop} #booking-privacy-consent must be unchecked by default`,
		);
		assert.ok(
			propEn.includes('href="../privacy/index.html"'),
			`${prop} English page must link to ../privacy/index.html`,
		);
	}

	const homeEs = fs.readFileSync(path.join(distDir, "es.html"), "utf-8");
	assert.ok(
		homeEs.includes('id="footer-privacy-link"') &&
			homeEs.includes('href="./privacy/es.html"'),
		"Root Spanish home page footer must link to ./privacy/es.html",
	);
});
