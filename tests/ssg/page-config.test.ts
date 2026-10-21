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

test("Guest Registry pages render Personal Data Authorization and hidden disabled Minor Guardian Attestation across all 6 locales", () => {
	const distDir = path.join(process.cwd(), "dist");
	const locales = [
		{
			file: "registry/index.html",
			lang: "en",
			privacyHref: "../privacy/index.html",
		},
		{ file: "registry/es.html", lang: "es", privacyHref: "../privacy/es.html" },
		{ file: "registry/fr.html", lang: "fr", privacyHref: "../privacy/fr.html" },
		{ file: "registry/it.html", lang: "it", privacyHref: "../privacy/it.html" },
		{ file: "registry/de.html", lang: "de", privacyHref: "../privacy/de.html" },
		{ file: "registry/ja.html", lang: "ja", privacyHref: "../privacy/ja.html" },
	];

	for (const item of locales) {
		const content = fs.readFileSync(path.join(distDir, item.file), "utf-8");

		// 1. Personal Data Authorization checkbox
		assert.ok(
			content.includes('id="privacy-consent"') &&
				content.includes('name="privacy_consent"') &&
				content.includes('name="privacy_policy_version" value="2026-10"'),
			`${item.file} must render #privacy-consent checkbox and privacy_policy_version=2026-10`,
		);
		assert.ok(
			!content.includes('id="privacy-consent" checked'),
			`${item.file} #privacy-consent must be unchecked by default`,
		);
		assert.ok(
			content.includes(`href="${item.privacyHref}"`),
			`${item.file} must link to localized privacy policy ${item.privacyHref}`,
		);

		// 2. Minor Guardian Attestation container (hidden by default, checkbox disabled while hidden)
		assert.ok(
			content.includes('id="minor-attestation-container"') &&
				content.includes('id="minor-guardian-consent"') &&
				content.includes('name="minor_guardian_consent"'),
			`${item.file} must render #minor-attestation-container and #minor-guardian-consent`,
		);
		const minorInputMatch = content.match(
			/<input[^>]*id="minor-guardian-consent"[^>]*>/,
		);
		assert.ok(
			minorInputMatch,
			`${item.file} must contain #minor-guardian-consent input tag`,
		);
		assert.ok(
			minorInputMatch[0].includes("disabled"),
			`${item.file} #minor-guardian-consent must be disabled while hidden`,
		);
		assert.ok(
			!minorInputMatch[0].includes("required"),
			`${item.file} #minor-guardian-consent must not be required while hidden`,
		);

		// 3. Page config includes localized consent validation keys and minorDocTypes
		const match = content.match(
			/<script id="ovf-page-config" type="application\/json">(.*?)<\/script>/,
		);
		assert.ok(match, `${item.file} must include #ovf-page-config`);
		const parsed = JSON.parse(match[1]);
		assert.ok(
			parsed.i18n.registryErrPrivacyConsent,
			`${item.file} i18n must include registryErrPrivacyConsent`,
		);
		assert.ok(
			parsed.i18n.registryErrMinorConsent,
			`${item.file} i18n must include registryErrMinorConsent`,
		);
		assert.equal(
			parsed.i18n.regErrPrivacyConsent,
			undefined,
			`${item.file} i18n must not include duplicate alias regErrPrivacyConsent`,
		);
		assert.equal(
			parsed.i18n.regErrMinorGuardianConsent,
			undefined,
			`${item.file} i18n must not include duplicate alias regErrMinorGuardianConsent`,
		);
		assert.deepEqual(
			parsed.minorDocTypes,
			["Tarjeta de Identidad", "Registro Civil"],
			`${item.file} pageConfig must include minorDocTypes`,
		);
	}
});

test("registry.js dynamically toggles Minor Guardian Attestation when any active guest is under 18 or holds a minor ID document", () => {
	const registryJsCode = fs.readFileSync(
		path.join(process.cwd(), "public/js/registry.js"),
		"utf-8",
	);

	type Listener = (e?: any) => void;
	const createMockElement = (
		id: string,
		tagName = "INPUT",
		initialClasses: string[] = [],
	) => {
		const classes = new Set<string>(initialClasses);
		const attrs = new Map<string, string>();
		const listeners: Record<string, Listener[]> = {};
		const el: any = {
			id,
			tagName,
			type: "text",
			name: id,
			value: "",
			checked: false,
			disabled: false,
			required: false,
			textContent: "",
			className: initialClasses.join(" "),
			classList: {
				add: (...cls: string[]) => {
					for (const c of cls) classes.add(c);
				},
				remove: (...cls: string[]) => {
					for (const c of cls) classes.delete(c);
				},
				contains: (c: string) => classes.has(c),
			},
			setAttribute: (k: string, v: string) => {
				attrs.set(k, v);
				if (k === "disabled") el.disabled = true;
				if (k === "required") el.required = true;
			},
			removeAttribute: (k: string) => {
				attrs.delete(k);
				if (k === "disabled") el.disabled = false;
				if (k === "required") el.required = false;
			},
			getAttribute: (k: string) => attrs.get(k) || null,
			hasAttribute: (k: string) => attrs.has(k),
			addEventListener: (evt: string, fn: Listener) => {
				listeners[evt] = listeners[evt] || [];
				listeners[evt].push(fn);
			},
			removeEventListener: () => {},
			dispatchEvent: (evt: string, payload: any = {}) => {
				for (const fn of listeners[evt] || []) {
					fn({ target: el, preventDefault: () => {}, ...payload });
				}
			},
			querySelectorAll: () => [],
			scrollIntoView: () => {},
			closest: () => null,
		};
		return el;
	};

	const elements = new Map<string, any>();
	const register = (el: any) => {
		elements.set(el.id, el);
		return el;
	};

	const form = register(createMockElement("registry-form", "FORM"));
	form.setAttribute("action", "../api/registry-processor.php");
	register(createMockElement("submit-button", "BUTTON"));
	register(createMockElement("submit-text", "SPAN"));
	register(createMockElement("form-message", "DIV", ["hidden"]));
	register(createMockElement("success-overlay", "DIV", ["hidden"]));
	register(createMockElement("display-property", "SPAN"));
	register(createMockElement("display-check-in", "SPAN"));
	register(createMockElement("display-check-out", "SPAN"));
	register(createMockElement("hidden-property", "INPUT"));
	register(createMockElement("hidden-check-in", "INPUT"));
	register(createMockElement("hidden-check-out", "INPUT"));
	register(createMockElement("hidden-reservation-code", "INPUT"));
	register(createMockElement("btn-unlocked-guide", "A"));
	register(createMockElement("success-title", "H2"));
	register(createMockElement("success-desc", "P"));
	const addGuestBtn = register(createMockElement("add-guest-button", "BUTTON"));
	register(createMockElement("guest-count-input", "INPUT"));
	register(createMockElement("captcha-label", "SPAN"));
	register(createMockElement("captcha-challenge", "INPUT"));
	register(createMockElement("captcha-response", "INPUT"));

	const privacyConsent = register(
		createMockElement("privacy-consent", "INPUT"),
	);
	privacyConsent.type = "checkbox";
	privacyConsent.name = "privacy_consent";
	privacyConsent.value = "1";
	privacyConsent.setAttribute("required", "required");

	const minorContainer = register(
		createMockElement("minor-attestation-container", "DIV", ["hidden"]),
	);
	const minorCheckbox = register(
		createMockElement("minor-guardian-consent", "INPUT"),
	);
	minorCheckbox.type = "checkbox";
	minorCheckbox.name = "minor_guardian_consent";
	minorCheckbox.value = "1";
	minorCheckbox.setAttribute("disabled", "disabled");

	for (let num = 1; num <= 12; num++) {
		const card = register(
			createMockElement(
				`guest-card-${num}`,
				"DIV",
				num === 1 ? [] : ["hidden"],
			),
		);
		const age = register(createMockElement(`guest-age-${num}`, "INPUT"));
		age.value = num === 1 ? "30" : "";
		const docType = register(
			createMockElement(`guest-doc-type-${num}`, "SELECT"),
		);
		docType.value = "Passport";
		card.querySelectorAll = () => [age, docType];
	}

	let domReadyCallback: Listener | null = null;
	const sandbox = {
		document: {
			documentElement: { lang: "en" },
			addEventListener: (evt: string, fn: Listener) => {
				if (evt === "DOMContentLoaded") domReadyCallback = fn;
			},
			getElementById: (id: string) => elements.get(id) || null,
		},
		window: {
			location: {
				search: "?property=1606&check_in=2027-01-10&check_out=2027-01-15",
				origin: "https://oceanviewflats.com",
			},
			getPageConfig: () => ({
				lang: "en",
				assetPrefix: "../",
				apiBase: "../api/",
				minorDocTypes: ["Tarjeta de Identidad", "Registro Civil"],
			}),
			t: (key: string, _params?: any, fallback?: string) => fallback || key,
			scrollTo: () => {},
		},
		URLSearchParams,
		URL,
		Intl,
		Date,
		FormData: class {},
		fetch: async () => ({
			ok: true,
			json: async () => ({ challenge: "2 + 3", signature: "sig" }),
		}),
		console: { error: () => {} },
		setTimeout,
	};

	vm.createContext(sandbox);
	vm.runInContext(registryJsCode, sandbox);
	assert.ok(
		domReadyCallback,
		"registry.js must register DOMContentLoaded listener",
	);
	(domReadyCallback as Listener)();

	// 1. Initially Guest 1 is age 30 with Passport -> minor container is hidden & checkbox disabled
	assert.equal(minorContainer.classList.contains("hidden"), true);
	assert.equal(minorCheckbox.disabled, true);
	assert.equal(minorCheckbox.required, false);

	// 2. Guest 1 age changed to 14 -> reveals container and enables/requires checkbox
	const age1 = elements.get("guest-age-1");
	age1.value = "14";
	form.dispatchEvent("input", { target: age1 });
	assert.equal(minorContainer.classList.contains("hidden"), false);
	assert.equal(minorCheckbox.disabled, false);
	assert.equal(minorCheckbox.required, true);

	// 3. Guest 1 age changed back to 30 -> hides container and resets checkbox
	minorCheckbox.checked = true;
	age1.value = "30";
	form.dispatchEvent("input", { target: age1 });
	assert.equal(minorContainer.classList.contains("hidden"), true);
	assert.equal(minorCheckbox.disabled, true);
	assert.equal(minorCheckbox.required, false);
	assert.equal(minorCheckbox.checked, false);

	// 4. Guest 1 doc_type changed to 'Tarjeta de Identidad' (even at age 30) -> reveals container
	const docType1 = elements.get("guest-doc-type-1");
	docType1.value = "Tarjeta de Identidad";
	form.dispatchEvent("change", { target: docType1 });
	assert.equal(minorContainer.classList.contains("hidden"), false);
	assert.equal(minorCheckbox.disabled, false);
	assert.equal(minorCheckbox.required, true);

	// 5. Guest 1 doc_type back to 'Passport', add Guest 2 with 'Registro Civil' -> reveals container
	docType1.value = "Passport";
	form.dispatchEvent("change", { target: docType1 });
	assert.equal(minorContainer.classList.contains("hidden"), true);

	addGuestBtn.dispatchEvent("click");
	const docType2 = elements.get("guest-doc-type-2");
	docType2.value = "Registro Civil";
	form.dispatchEvent("change", { target: docType2 });
	assert.equal(minorContainer.classList.contains("hidden"), false);
	assert.equal(minorCheckbox.disabled, false);
	assert.equal(minorCheckbox.required, true);
});

test("registry.js and main.js encode form submissions via URLSearchParams to avoid multipart/form-data (CVE-2026-109618)", () => {
	const registryJs = fs.readFileSync(
		path.join(process.cwd(), "public/js/registry.js"),
		"utf-8",
	);
	const mainJs = fs.readFileSync(
		path.join(process.cwd(), "public/js/main.js"),
		"utf-8",
	);

	assert.ok(
		registryJs.includes("new URLSearchParams(new FormData(form))"),
		"registry.js must wrap FormData in URLSearchParams so fetch sends application/x-www-form-urlencoded",
	);
	assert.ok(
		mainJs.includes("new URLSearchParams(new FormData(directFormElement))"),
		"main.js direct booking form must wrap FormData in URLSearchParams",
	);
	assert.ok(
		mainJs.includes("new URLSearchParams(new FormData(form))"),
		"main.js contact form must wrap FormData in URLSearchParams",
	);
});
