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

test("Shared chrome across all 6 locales renders accessible #lang-toggle button, >=40px tap targets, relative #nav-logo assetPrefix, and compliant Footer (#237)", () => {
	const distDir = path.join(process.cwd(), "dist");
	const locales = [
		{
			code: "en",
			rootFile: "index.html",
			nestedFile: "Oceanview1707/index.html",
			expectedRootLogoHref: "./",
			expectedNestedLogoHref: "../",
			expectedEngineeredBy: "Engineered by",
		},
		{
			code: "es",
			rootFile: "es.html",
			nestedFile: "Oceanview1707/es.html",
			expectedRootLogoHref: "./es.html",
			expectedNestedLogoHref: "../es.html",
			expectedEngineeredBy: "Desarrollado por",
		},
		{
			code: "fr",
			rootFile: "fr.html",
			nestedFile: "Oceanview1707/fr.html",
			expectedRootLogoHref: "./fr.html",
			expectedNestedLogoHref: "../fr.html",
			expectedEngineeredBy: "Conçu par",
		},
		{
			code: "it",
			rootFile: "it.html",
			nestedFile: "Oceanview1707/it.html",
			expectedRootLogoHref: "./it.html",
			expectedNestedLogoHref: "../it.html",
			expectedEngineeredBy: "Sviluppato da",
		},
		{
			code: "de",
			rootFile: "de.html",
			nestedFile: "Oceanview1707/de.html",
			expectedRootLogoHref: "./de.html",
			expectedNestedLogoHref: "../de.html",
			expectedEngineeredBy: "Entwickelt von",
		},
		{
			code: "ja",
			rootFile: "ja.html",
			nestedFile: "Oceanview1707/ja.html",
			expectedRootLogoHref: "./ja.html",
			expectedNestedLogoHref: "../ja.html",
			expectedEngineeredBy: "開発:",
		},
	];

	for (const locale of locales) {
		const rootHtml = fs.readFileSync(
			path.join(distDir, locale.rootFile),
			"utf-8",
		);
		const nestedHtml = fs.readFileSync(
			path.join(distDir, locale.nestedFile),
			"utf-8",
		);

		for (const [label, html, expectedLogoHref] of [
			[locale.rootFile, rootHtml, locale.expectedRootLogoHref],
			[locale.nestedFile, nestedHtml, locale.expectedNestedLogoHref],
		] as const) {
			// 1. Semantic #lang-toggle button with aria-haspopup="true", aria-expanded="false", and >=40px tap target
			const langToggleMatch = html.match(/<button[^>]*id="lang-toggle"[^>]*>/);
			assert.ok(
				langToggleMatch,
				`${label} must render #lang-toggle as a semantic <button>`,
			);
			assert.ok(
				langToggleMatch[0].includes('type="button"'),
				`${label} #lang-toggle must specify type="button"`,
			);
			assert.ok(
				langToggleMatch[0].includes('aria-haspopup="true"') &&
					langToggleMatch[0].includes('aria-expanded="false"'),
				`${label} #lang-toggle must include aria-haspopup="true" and aria-expanded="false"`,
			);
			assert.ok(
				langToggleMatch[0].includes("min-h-[40px]") &&
					langToggleMatch[0].includes("min-w-[40px]"),
				`${label} #lang-toggle must enforce >= 40px x 40px touch target`,
			);

			// 2. #nav-contact enforces >=40px x 40px touch target
			const navContactMatch = html.match(/<a[^>]*id="nav-contact"[^>]*>/);
			assert.ok(navContactMatch, `${label} must render #nav-contact`);
			assert.ok(
				navContactMatch[0].includes("min-h-[40px]") &&
					navContactMatch[0].includes("min-w-[40px]"),
				`${label} #nav-contact must enforce >= 40px x 40px touch target`,
			);

			// 3. Closed #lang-dropdown has invisible class (visibility: hidden) so collapsed links are not keyboard-focusable, and AA hover contrast
			const dropdownMatch = html.match(/<div[^>]*id="lang-dropdown"[^>]*>/);
			assert.ok(dropdownMatch, `${label} must render #lang-dropdown`);
			assert.ok(
				dropdownMatch[0].includes("invisible") &&
					dropdownMatch[0].includes("opacity-0"),
				`${label} #lang-dropdown must include 'invisible' and 'opacity-0' when collapsed so links are removed from tab order`,
			);
			assert.ok(
				html.includes("hover:text-rose-700") &&
					!html.includes("hover:text-[#FF5A5F]"),
				`${label} language menu items must use WCAG AA hover:text-rose-700 instead of low-contrast hover:text-[#FF5A5F]`,
			);

			// 4. #nav-logo uses relative assetPrefix
			const navLogoMatch = html.match(/<a[^>]*id="nav-logo"[^>]*>/);
			assert.ok(navLogoMatch, `${label} must render #nav-logo`);
			assert.ok(
				navLogoMatch[0].includes(`href="${expectedLogoHref}"`),
				`${label} #nav-logo must use relative assetPrefix href="${expectedLogoHref}", got: ${navLogoMatch[0]}`,
			);

			// 5. Global footer compliance: no <style>, no dark-glow, no 11px/#a1a1aa, RNT line >=4.5:1 (text-slate-300), localized attribution
			const footerMatch = html.match(/<footer[\s\S]*?<\/footer>/);
			assert.ok(footerMatch, `${label} must render <footer>`);
			const footerHtml = footerMatch[0];
			assert.ok(
				!footerHtml.includes("<style") && !footerHtml.includes("style="),
				`${label} footer must not contain inline <style> blocks or inline style attributes`,
			);
			assert.ok(
				!footerHtml.includes("#10b981") &&
					!footerHtml.includes("11px") &&
					!footerHtml.includes("#a1a1aa"),
				`${label} footer must not contain #10b981 neon glow, 11px font-size, or #a1a1aa color`,
			);
			assert.ok(
				!footerHtml.includes("text-slate-500") &&
					footerHtml.includes("text-slate-300"),
				`${label} footer RNT line must use high-contrast text-slate-300 instead of low-contrast text-slate-500`,
			);
			assert.ok(
				footerHtml.includes("268781") && footerHtml.includes("270413"),
				`${label} footer must display both statutory RNT numbers`,
			);
			assert.ok(
				footerHtml.includes(`<span>${locale.expectedEngineeredBy}</span>`),
				`${label} footer must localize developer attribution prefix as "${locale.expectedEngineeredBy}"`,
			);
		}
	}

	// 6. Japanese (ja) municipality standardization: サンタマルタ instead of ガイラ
	const jaHome = fs.readFileSync(path.join(distDir, "ja.html"), "utf-8");
	const ja1707 = fs.readFileSync(
		path.join(distDir, "Oceanview1707", "ja.html"),
		"utf-8",
	);
	assert.ok(
		jaHome.includes("コロンビア、サンタマルタ、プラヤ・サルグエロ") &&
			!jaHome.includes("ガイラ"),
		"dist/ja.html must reference サンタマルタ and never ガイラ",
	);
	assert.ok(
		ja1707.includes("コロンビア、サンタマルタ、プラヤ・サルグエロ") &&
			!ja1707.includes("ガイラ"),
		"dist/Oceanview1707/ja.html must reference サンタマルタ and never ガイラ",
	);

	// 7. Component templates use centralized CHROME_THEME without inline ternaries or style tags
	for (const compFile of [
		"Navigation.tsx",
		"LanguageDropdown.tsx",
		"Footer.tsx",
	]) {
		const compSource = fs.readFileSync(
			path.join(process.cwd(), "src", "components", compFile),
			"utf-8",
		);
		assert.ok(
			compSource.includes("CHROME_THEME"),
			`${compFile} must consume centralized CHROME_THEME from src/constants/theme.ts`,
		);
		assert.ok(
			!compSource.includes("dangerouslySetInnerHTML") &&
				!compSource.includes("style={{"),
			`${compFile} must not embed inline <style> or style={{...}}`,
		);
	}
});

test("main.js #lang-toggle opens menu with aria-expanded='true', closes and restores focus on Escape, and closes on outside click (#237)", () => {
	const mainJsCode = fs.readFileSync(
		path.join(process.cwd(), "public", "js", "main.js"),
		"utf-8",
	);

	interface MockEvent {
		target?: MockNode;
		key?: string;
		preventDefault?: () => void;
		stopPropagation?: () => void;
	}
	type Listener = (e?: MockEvent) => void;
	interface MockNode {
		id: string;
		tagName: string;
		classList: {
			add: (...cls: string[]) => void;
			remove: (...cls: string[]) => void;
			contains: (c: string) => boolean;
		};
		getAttribute: (k: string) => string | null;
		setAttribute: (k: string, v: string) => void;
		addEventListener: (evt: string, fn: Listener) => void;
		dispatchEvent: (evt: string, payload?: MockEvent) => void;
		focus: () => void;
		blur: () => void;
		isFocused: () => boolean;
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
		const attrs = new Map<string, string>(Object.entries(initialAttrs));
		const listeners: Record<string, Listener[]> = {};
		const children: MockNode[] = [];
		let focused = false;

		const node: MockNode = {
			id,
			tagName,
			classList: {
				add: (...cls: string[]) => {
					for (const c of cls) classes.add(c);
				},
				remove: (...cls: string[]) => {
					for (const c of cls) classes.delete(c);
				},
				contains: (c: string) => classes.has(c),
			},
			getAttribute: (k: string) => attrs.get(k) ?? null,
			setAttribute: (k: string, v: string) => {
				attrs.set(k, v);
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
			blur: () => {
				focused = false;
			},
			isFocused: () => focused,
			appendChild: (child: MockNode) => {
				children.push(child);
				return child;
			},
			contains: (target: unknown) => {
				if (target === node) return true;
				return children.some((c) => c === target || c.contains?.(target));
			},
			querySelector: (sel: string) => {
				if (sel === ".absolute.right-0.top-full") {
					return children.find((c) => c.id === "lang-dropdown") || null;
				}
				return null;
			},
			querySelectorAll: (sel: string) => {
				if (sel === "a") {
					return children.filter((c) => c.tagName === "A");
				}
				return [];
			},
		};
		return node;
	};

	const langToggleGroup = createMockNode("lang-toggle-group", "DIV", [
		"relative",
		"group",
	]);
	const langToggle = createMockNode("lang-toggle", "BUTTON", ["min-h-[40px]"], {
		type: "button",
		"aria-haspopup": "true",
		"aria-expanded": "false",
	});
	const langDropdown = createMockNode("lang-dropdown", "DIV", [
		"absolute",
		"right-0",
		"top-full",
		"opacity-0",
		"invisible",
	]);
	const esLink = createMockNode("lang-link-es", "A", [], { href: "es.html" });
	langDropdown.appendChild(esLink);
	langToggleGroup.appendChild(langToggle);
	langToggleGroup.appendChild(langDropdown);
	langToggleGroup.querySelectorAll = () => [esLink];

	const outsideNode = createMockNode("outside", "DIV");

	const elementsById = new Map<string, MockNode>([
		["lang-toggle-group", langToggleGroup],
		["lang-toggle", langToggle],
		["lang-dropdown", langDropdown],
	]);

	const docListeners: Record<string, Listener[]> = {};
	const scriptStub = {
		async: false,
		src: "",
		parentNode: { insertBefore: () => {} },
	};

	const sandbox = {
		window: {
			_paq: [] as unknown[],
			scrollY: 0,
			location: { search: "?property=1707", pathname: "/index.html" },
			addEventListener: () => {},
		},
		document: {
			documentElement: { lang: "en" },
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
		localStorage: {
			getItem: () => null,
			setItem: () => {},
		},
		console,
	};

	vm.createContext(sandbox);
	vm.runInContext(mainJsCode, sandbox);

	// Trigger DOMContentLoaded
	sandbox.document.dispatchEvent("DOMContentLoaded");

	// Verify query string propagation on language links
	assert.equal(esLink.getAttribute("href"), "es.html?property=1707");

	// 1. Initial closed state: aria-expanded="false" and invisible/opacity-0 classes present
	assert.equal(langToggle.getAttribute("aria-expanded"), "false");
	assert.equal(langDropdown.classList.contains("invisible"), true);
	assert.equal(langDropdown.classList.contains("opacity-0"), true);

	// 2. Click #lang-toggle -> opens menu and sets aria-expanded="true"
	langToggle.dispatchEvent("click");
	assert.equal(langToggle.getAttribute("aria-expanded"), "true");
	assert.equal(langDropdown.classList.contains("invisible"), false);
	assert.equal(langDropdown.classList.contains("opacity-100"), true);
	assert.equal(langDropdown.classList.contains("visible"), true);

	// 3. Press Escape -> closes menu, sets aria-expanded="false", and returns focus to #lang-toggle
	langToggle.blur();
	assert.equal(langToggle.isFocused(), false);
	sandbox.document.dispatchEvent("keydown", { key: "Escape" });
	assert.equal(langToggle.getAttribute("aria-expanded"), "false");
	assert.equal(langDropdown.classList.contains("invisible"), true);
	assert.equal(langDropdown.classList.contains("opacity-0"), true);
	assert.equal(
		langToggle.isFocused(),
		true,
		"Escape key must restore focus to #lang-toggle",
	);

	// 4. Re-open and click outside -> closes menu and sets aria-expanded="false"
	langToggle.dispatchEvent("click");
	assert.equal(langToggle.getAttribute("aria-expanded"), "true");
	assert.equal(langDropdown.classList.contains("invisible"), false);

	sandbox.document.dispatchEvent("click", { target: outsideNode });
	assert.equal(langToggle.getAttribute("aria-expanded"), "false");
	assert.equal(langDropdown.classList.contains("invisible"), true);
	assert.equal(langDropdown.classList.contains("opacity-0"), true);
});

test("Homepage Hero and Property 1707 / 1606 showcase cards render localized value proposition, floor/RNT/parking specs, relative assetPrefix links, and WCAG AA contrast across all 6 locales (#238)", () => {
	const distDir = path.join(process.cwd(), "dist");
	const locales = [
		{
			code: "en",
			file: "index.html",
			expectedNavBookDirect: "Book Direct",
			forbiddenNavAirbnb: "Book on Airbnb",
			expected1707Href: "./Oceanview1707/index.html",
			expected1606Href: "./Oceanview1606/index.html",
			expectedHeroAlt: "Balcony Ocean View",
			expected1707Title: "Oceanview Flat 1707",
			expected1606Title: "Oceanview Flat 1606",
			expected1707Badge: "17th Floor • RNT #268781",
			expected1606Badge: "16th Floor • RNT #270413",
			expected1707Parking: "Private Parking Included",
			expected1606Parking: "Private Parking Included",
			expectedBaths: "2 Baths",
		},
		{
			code: "es",
			file: "es.html",
			expectedNavBookDirect: "Reservar Directo",
			forbiddenNavAirbnb: "Reservar en Airbnb",
			expected1707Href: "./Oceanview1707/es.html",
			expected1606Href: "./Oceanview1606/es.html",
			expectedHeroAlt: "Vista al mar desde el balcón",
			expected1707Title: "Apartamento Vista al Mar 1707",
			expected1606Title: "Apartamento Vista al Mar 1606",
			expected1707Badge: "Piso 17 • RNT #268781",
			expected1606Badge: "Piso 16 • RNT #270413",
			expected1707Parking: "Parqueadero Privado Incluido",
			expected1606Parking: "Parqueadero Privado Incluido",
			expectedBaths: "2 Baños",
		},
		{
			code: "fr",
			file: "fr.html",
			expectedNavBookDirect: "Réserver en Direct",
			forbiddenNavAirbnb: "Réserver sur Airbnb",
			expected1707Href: "./Oceanview1707/fr.html",
			expected1606Href: "./Oceanview1606/fr.html",
			expectedHeroAlt: "Vue sur l&#x27;océan depuis le balcon",
			expected1707Title: "Appartement Vue Mer 1707",
			expected1606Title: "Appartement Vue Mer 1606",
			expected1707Badge: "17e Étage • RNT #268781",
			expected1606Badge: "16e Étage • RNT #270413",
			expected1707Parking: "Parking Privé Inclus",
			expected1606Parking: "Parking Privé Inclus",
			expectedBaths: "2 Salles de bain",
		},
		{
			code: "it",
			file: "it.html",
			expectedNavBookDirect: "Prenota Diretto",
			forbiddenNavAirbnb: "Prenota su Airbnb",
			expected1707Href: "./Oceanview1707/it.html",
			expected1606Href: "./Oceanview1606/it.html",
			expectedHeroAlt: "Vista oceano dal balcone",
			expected1707Title: "Appartamento Vista Mare 1707",
			expected1606Title: "Appartamento Vista Mare 1606",
			expected1707Badge: "17° Piano • RNT #268781",
			expected1606Badge: "16° Piano • RNT #270413",
			expected1707Parking: "Parcheggio Privato Incluso",
			expected1606Parking: "Parcheggio Privato Incluso",
			expectedBaths: "2 Bagni",
		},
		{
			code: "de",
			file: "de.html",
			expectedNavBookDirect: "Direkt Buchen",
			forbiddenNavAirbnb: "Auf Airbnb buchen",
			expected1707Href: "./Oceanview1707/de.html",
			expected1606Href: "./Oceanview1606/de.html",
			expectedHeroAlt: "Meerblick vom Balkon",
			expected1707Title: "Apartment mit Meerblick 1707",
			expected1606Title: "Apartment mit Meerblick 1606",
			expected1707Badge: "17. Etage • RNT #268781",
			expected1606Badge: "16. Etage • RNT #270413",
			expected1707Parking: "Privater Parkplatz Inklusive",
			expected1606Parking: "Privater Parkplatz Inklusive",
			expectedBaths: "2 Bäder",
		},
		{
			code: "ja",
			file: "ja.html",
			expectedNavBookDirect: "直接予約",
			forbiddenNavAirbnb: "Airbnbで予約",
			expected1707Href: "./Oceanview1707/ja.html",
			expected1606Href: "./Oceanview1606/ja.html",
			expectedHeroAlt: "バルコニーからのオーシャンビュー",
			expected1707Title: "オーシャンビューフラット 1707",
			expected1606Title: "オーシャンビューフラット 1606",
			expected1707Badge: "17階 • RNT #268781",
			expected1606Badge: "16階 • RNT #270413",
			expected1707Parking: "専用駐車場付き",
			expected1606Parking: "専用駐車場付き",
			expectedBaths: "2 バスルーム",
		},
	];

	for (const locale of locales) {
		const html = fs.readFileSync(path.join(distDir, locale.file), "utf-8");

		// 1. Navbar & Hero CTA both link to #properties, and Navbar uses "Book Direct" (never "Book on Airbnb")
		const propertiesAnchors = html.match(/<a[^>]*href="#properties"[^>]*>/g);
		assert.ok(
			propertiesAnchors && propertiesAnchors.length >= 2,
			`${locale.file} must render both Navbar and Hero CTAs linking to href="#properties"`,
		);
		const navMatch = html.match(/<nav[\s\S]*?<\/nav>/);
		assert.ok(navMatch, `${locale.file} must render <nav>`);
		assert.ok(
			navMatch[0].includes(locale.expectedNavBookDirect) &&
				!navMatch[0].includes(locale.forbiddenNavAirbnb),
			`${locale.file} navbar must display "${locale.expectedNavBookDirect}" instead of "${locale.forbiddenNavAirbnb}"`,
		);

		// 2. Hero image uses localized alt text (t.heroImgAlt) and Hero names Edificio Salguero Sunset
		assert.ok(
			html.includes(`alt="${locale.expectedHeroAlt}"`) &&
				!html.includes('alt="OceanViewFlats"'),
			`${locale.file} hero image must use localized alt="${locale.expectedHeroAlt}"`,
		);
		const headerMatch = html.match(/<header[\s\S]*?<\/header>/);
		assert.ok(
			headerMatch && headerMatch[0].includes("Edificio Salguero Sunset"),
			`${locale.file} Hero header must visibly display "Edificio Salguero Sunset"`,
		);

		// 3. Featured Properties section has id="properties"
		const propSectionMatch = html.match(
			/<section[^>]*id="properties"[^>]*>[\s\S]*?<\/section>/,
		);
		assert.ok(
			propSectionMatch,
			`${locale.file} must render <section id="properties">`,
		);
		const propSectionHtml = propSectionMatch[0];

		// 4. Relative assetPrefix links on both property cards (never root-relative /Oceanview1707 or /Oceanview1606)
		assert.ok(
			propSectionHtml.includes(`href="${locale.expected1707Href}"`) &&
				!propSectionHtml.includes('href="/Oceanview1707'),
			`${locale.file} Property 1707 card must use relative href="${locale.expected1707Href}"`,
		);
		assert.ok(
			propSectionHtml.includes(`href="${locale.expected1606Href}"`) &&
				!propSectionHtml.includes('href="/Oceanview1606'),
			`${locale.file} Property 1606 card must use relative href="${locale.expected1606Href}"`,
		);

		// 5. Standardized singular English naming ("Oceanview Flat 1606", never plural "Oceanview Flats 1606" in card title)
		assert.ok(
			propSectionHtml.includes(locale.expected1707Title) &&
				propSectionHtml.includes(locale.expected1606Title) &&
				!propSectionHtml.includes("Oceanview Flats 1606"),
			`${locale.file} must render canonical titles "${locale.expected1707Title}" and "${locale.expected1606Title}"`,
		);

		// 6. Floor + RNT badges, bath counts, assigned parking bays (Bay 95 & Bay 87), and 7–12 multi-property disclosure
		assert.ok(
			propSectionHtml.includes(locale.expected1707Badge) &&
				propSectionHtml.includes(locale.expected1606Badge),
			`${locale.file} must display floor and statutory RNT badges on both cards`,
		);
		assert.ok(
			propSectionHtml.includes(locale.expected1707Parking) &&
				propSectionHtml.includes(locale.expected1606Parking),
			`${locale.file} must display assigned parking bays (95 and 87) on the property cards`,
		);
		assert.ok(
			propSectionHtml.includes(locale.expectedBaths),
			`${locale.file} must display bathroom count (${locale.expectedBaths}) on the property cards`,
		);
		assert.ok(
			propSectionHtml.includes('id="home-multi-property-note"') &&
				propSectionHtml.includes("7–12") &&
				propSectionHtml.includes("Edificio Salguero Sunset"),
			`${locale.file} #properties must display the 7–12 Guest multi-property family stay note in Edificio Salguero Sunset`,
		);

		// 7. No line-clamp-2 truncation on localized descriptions, and unified 1707/1606 card color scheme + WCAG AA contrast tokens used inside #properties
		assert.ok(
			!propSectionHtml.includes("line-clamp-2"),
			`${locale.file} property cards must not truncate descriptions with line-clamp-2`,
		);
		assert.ok(
			propSectionHtml.includes("text-[#B91C1C]") &&
				!propSectionHtml.includes("text-[#FF5A5F]") &&
				!propSectionHtml.includes("text-rose-600") &&
				!propSectionHtml.includes("bg-stone-50"),
			`${locale.file} property cards must share the 1707 color scheme with text-[#B91C1C] CTA and neutral spec pills`,
		);
	}

	// 8. Verify Home.tsx source uses HOME_THEME, HOME_PROPERTY_CARDS, getPropertyPagePath, zero inline class strings, and no useState/useEffect
	const homeSource = fs.readFileSync(
		path.join(process.cwd(), "src", "pages", "Home.tsx"),
		"utf-8",
	);
	assert.ok(
		homeSource.includes("HOME_THEME") &&
			homeSource.includes("HOME_PROPERTY_CARDS") &&
			homeSource.includes("getPropertyPagePath"),
		"Home.tsx must consume centralized HOME_THEME, HOME_PROPERTY_CARDS, and getPropertyPagePath",
	);
	assert.ok(
		!homeSource.includes('className="'),
		"Home.tsx must not contain any hardcoded inline className strings",
	);
	assert.ok(
		!homeSource.includes("useState") && !homeSource.includes("useEffect"),
		"Home.tsx must not contain dead client-side useState/useEffect hooks in zero-hydration SSG",
	);
});

test("Homepage Why Book Direct, Editorial Lifestyle & Host Showcase, Progressive-Disclosure FAQ (<details>/<summary>), and Direct-Checkout Funnel align across all 6 locales (#239)", () => {
	const distDir = path.join(process.cwd(), "dist");
	const locales = [
		{
			code: "en",
			file: "index.html",
			expectedContactHref: "./contact/index.html",
			expectedDirectTitle: "Why Book Direct?",
		},
		{
			code: "es",
			file: "es.html",
			expectedContactHref: "./contact/es.html",
			expectedDirectTitle: "¿Por qué Reservar Directamente?",
		},
		{
			code: "fr",
			file: "fr.html",
			expectedContactHref: "./contact/fr.html",
			expectedDirectTitle: "Pourquoi Réserver Directement ?",
		},
		{
			code: "it",
			file: "it.html",
			expectedContactHref: "./contact/it.html",
			expectedDirectTitle: "Perché Prenotare Direttamente?",
		},
		{
			code: "de",
			file: "de.html",
			expectedContactHref: "./contact/de.html",
			expectedDirectTitle: "Warum Direkt Buchen?",
		},
		{
			code: "ja",
			file: "ja.html",
			expectedContactHref: "./contact/ja.html",
			expectedDirectTitle: "公式サイトから直接予約する理由",
		},
	];

	for (const locale of locales) {
		const html = fs.readFileSync(path.join(distDir, locale.file), "utf-8");

		// 1. Why Book Direct section has no duplicate kicker pill above <h2>{t.directTitle}</h2> and no amber/purple icons
		const directMatch = html.match(
			/<section[^>]*id="why-book-direct"[^>]*>[\s\S]*?<\/section>/,
		);
		assert.ok(
			directMatch,
			`${locale.file} must render <section id="why-book-direct">`,
		);
		const directHtml = directMatch[0];
		const directTitleOccurrences =
			directHtml.split(locale.expectedDirectTitle).length - 1;
		assert.equal(
			directTitleOccurrences,
			1,
			`${locale.file} must render directTitle exactly once in #why-book-direct (no duplicate kicker pill)`,
		);
		assert.ok(
			!directHtml.includes("bg-amber-50") &&
				!directHtml.includes("bg-purple-50"),
			`${locale.file} #why-book-direct must use cohesive palette without amber/purple icon boxes`,
		);

		// 2. Editorial Lifestyle & Host Showcase section renders real photography and Host Manuel Enrique with RNT badges
		const expMatch = html.match(
			/<section[^>]*id="experiences"[^>]*>[\s\S]*?<\/section>/,
		);
		assert.ok(
			expMatch,
			`${locale.file} must render <section id="experiences">`,
		);
		const expHtml = expMatch[0];
		assert.ok(
			expHtml.includes("./images/1707-balcony-working-nomad.webp") &&
				expHtml.includes("./images/1606-romantic-experiences.webp") &&
				expHtml.includes("./images/host-image.webp"),
			`${locale.file} #experiences must showcase 1707-balcony-working-nomad.webp, 1606-romantic-experiences.webp, and host-image.webp`,
		);
		assert.ok(
			expHtml.includes('id="home-host-card"') &&
				expHtml.includes("Manuel Enrique") &&
				expHtml.includes("268781") &&
				expHtml.includes("270413"),
			`${locale.file} #experiences must render #home-host-card with Manuel Enrique and both RNT numbers`,
		);
		assert.ok(
			!expHtml.includes("mt-10"),
			`${locale.file} #experiences must not have an exposed mt-10 gap`,
		);

		// 3. Progressive-Disclosure FAQ section uses 6 native <details>/<summary> accordions (first open) with COP, 20,000 COP, and 0XXXXXX# disclosures
		const faqMatch = html.match(
			/<section[^>]*id="faq"[^>]*>[\s\S]*?<\/section>/,
		);
		assert.ok(faqMatch, `${locale.file} must render <section id="faq">`);
		const faqHtml = faqMatch[0];
		const detailsMatches = faqHtml.match(/<details/g) || [];
		const summaryMatches = faqHtml.match(/<summary/g) || [];
		assert.equal(
			detailsMatches.length,
			6,
			`${locale.file} #faq must render 6 <details> accordion items`,
		);
		assert.equal(
			summaryMatches.length,
			6,
			`${locale.file} #faq must render 6 <summary> headers`,
		);
		assert.ok(
			/<details[^>]*open/.test(faqHtml),
			`${locale.file} #faq must have a <details> item open by default`,
		);
		assert.ok(
			faqHtml.includes("20,000 COP") &&
				faqHtml.includes("0XXXXXX#") &&
				faqHtml.includes("1581") &&
				faqHtml.includes("Efecty"),
			`${locale.file} #faq must disclose 20,000 COP building fee, 0XXXXXX# door PIN, Law 1581 Guest Registry, and direct checkout payment rails`,
		);

		// 4. FAQPage JSON-LD structured data includes all 6 Questions
		assert.ok(
			html.includes('"@type":"FAQPage"') && html.includes("20,000 COP"),
			`${locale.file} FAQPage JSON-LD must include the 6th FAQ item`,
		);

		// 5. Bottom CTA section provides #properties primary direct-checkout anchor, localized contact link, and WhatsApp link without dark-glow blur-3xl blobs
		assert.ok(
			html.includes(`href="${locale.expectedContactHref}"`) &&
				html.includes('href="https://wa.me/573108155234"') &&
				html.includes("COP"),
			`${locale.file} bottom CTA must link to ${locale.expectedContactHref} and WhatsApp while referencing COP direct checkout`,
		);
		assert.ok(
			!html.includes("blur-3xl"),
			`${locale.file} must not contain decorative blur-3xl dark-glow blobs`,
		);
	}
});

test("Compiled CSS binds --font-sans to Inter and main.js syncs scrolled navbar on DOMContentLoaded and closes #lang-dropdown on focusout (#242)", () => {
	// 1. Verify compiled dist/css/style.css binds --font-sans to Inter without Apple Color Emoji
	const compiledCssPath = path.join(process.cwd(), "dist", "css", "style.css");
	assert.ok(
		fs.existsSync(compiledCssPath),
		"Compiled dist/css/style.css must exist",
	);
	const compiledCss = fs.readFileSync(compiledCssPath, "utf-8");
	const fontSansMatch = compiledCss.match(/--font-sans:\s*([^;}\n]+)/);
	assert.ok(fontSansMatch, "dist/css/style.css must define --font-sans");
	assert.ok(
		fontSansMatch[1].includes("Inter") &&
			fontSansMatch[1].includes("-apple-system") &&
			fontSansMatch[1].includes("BlinkMacSystemFont") &&
			fontSansMatch[1].includes("sans-serif") &&
			!fontSansMatch[1].includes("Apple Color Emoji"),
		`--font-sans must bind to Inter stack without Apple Color Emoji, got: ${fontSansMatch[1]}`,
	);

	// 2. Verify main.js in node:vm syncs navbar scroll state immediately on DOMContentLoaded when window.scrollY > 50, and closes #lang-dropdown on focusout outside #lang-toggle-group
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
		classList: {
			add: (...cls: string[]) => void;
			remove: (...cls: string[]) => void;
			contains: (c: string) => boolean;
		};
		getAttribute: (k: string) => string | null;
		setAttribute: (k: string, v: string) => void;
		addEventListener: (evt: string, fn: Listener) => void;
		dispatchEvent: (evt: string, payload?: MockEvent) => void;
		focus: () => void;
		blur: () => void;
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
		const attrs = new Map<string, string>(Object.entries(initialAttrs));
		const listeners: Record<string, Listener[]> = {};
		const children: MockNode[] = [];

		const node: MockNode = {
			id,
			tagName,
			classList: {
				add: (...cls: string[]) => {
					for (const c of cls) classes.add(c);
				},
				remove: (...cls: string[]) => {
					for (const c of cls) classes.delete(c);
				},
				contains: (c: string) => classes.has(c),
			},
			getAttribute: (k: string) => attrs.get(k) ?? null,
			setAttribute: (k: string, v: string) => {
				attrs.set(k, v);
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
			focus: () => {},
			blur: () => {},
			appendChild: (child: MockNode) => {
				children.push(child);
				return child;
			},
			contains: (target: unknown) => {
				if (target === node) return true;
				return children.some((c) => c === target || c.contains?.(target));
			},
			querySelector: (sel: string) => {
				if (sel === ".absolute.right-0.top-full") {
					return children.find((c) => c.id === "lang-dropdown") || null;
				}
				return null;
			},
			querySelectorAll: (sel: string) => {
				if (sel === "a") {
					return children.filter((c) => c.tagName === "A");
				}
				return [];
			},
		};
		return node;
	};

	const navbar = createMockNode("navbar", "NAV", ["bg-transparent", "py-5"]);
	const navLogo = createMockNode("nav-logo", "A", [
		"text-white",
		"hover:text-white/80",
	]);
	const langToggleGroup = createMockNode("lang-toggle-group", "DIV", [
		"relative",
		"group",
	]);
	const langToggle = createMockNode(
		"lang-toggle",
		"BUTTON",
		["bg-white/20", "hover:bg-white/30", "text-white", "backdrop-blur-sm"],
		{
			type: "button",
			"aria-haspopup": "true",
			"aria-expanded": "false",
		},
	);
	const navContact = createMockNode("nav-contact", "A", [
		"text-white",
		"hover:text-white/80",
	]);
	const navBook = createMockNode("nav-book", "A", [
		"text-white",
		"hover:text-white/80",
	]);
	const langDropdown = createMockNode("lang-dropdown", "DIV", [
		"absolute",
		"right-0",
		"top-full",
		"opacity-0",
		"invisible",
	]);
	const esLink = createMockNode("lang-link-es", "A", [], { href: "es.html" });
	langDropdown.appendChild(esLink);
	langToggleGroup.appendChild(langToggle);
	langToggleGroup.appendChild(langDropdown);
	langToggleGroup.querySelectorAll = () => [esLink];

	const outsideFocusableNode = createMockNode("hero-cta", "A");

	const elementsById = new Map<string, MockNode>([
		["navbar", navbar],
		["nav-logo", navLogo],
		["lang-toggle-group", langToggleGroup],
		["lang-toggle", langToggle],
		["nav-contact", navContact],
		["nav-book", navBook],
		["lang-dropdown", langDropdown],
	]);

	const docListeners: Record<string, Listener[]> = {};
	const winListeners: Record<string, Listener[]> = {};
	const scriptStub = {
		async: false,
		src: "",
		parentNode: { insertBefore: () => {} },
	};

	const sandbox = {
		window: {
			_paq: [] as unknown[],
			scrollY: 120, // Pre-scrolled past 50px at page load (e.g. /#faq anchor)
			location: { search: "", pathname: "/index.html" },
			addEventListener: (evt: string, fn: Listener) => {
				winListeners[evt] = winListeners[evt] || [];
				winListeners[evt].push(fn);
			},
			dispatchEvent: (evt: string, payload: MockEvent = {}) => {
				for (const fn of winListeners[evt] || []) {
					fn(payload);
				}
			},
		},
		document: {
			documentElement: { lang: "en" },
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
		localStorage: {
			getItem: () => null,
			setItem: () => {},
		},
		console,
	};

	vm.createContext(sandbox);
	vm.runInContext(mainJsCode, sandbox);

	// Trigger DOMContentLoaded while window.scrollY === 120 (> 50) without firing a scroll event
	sandbox.document.dispatchEvent("DOMContentLoaded");

	assert.equal(
		navbar.classList.contains("bg-white/90") &&
			navbar.classList.contains("backdrop-blur-md") &&
			navbar.classList.contains("shadow-sm") &&
			navbar.classList.contains("py-3") &&
			!navbar.classList.contains("bg-transparent") &&
			!navbar.classList.contains("py-5"),
		true,
		"#navbar must immediately receive scrolled classes on DOMContentLoaded when window.scrollY > 50",
	);
	assert.equal(
		navLogo.classList.contains("text-[#FF5A5F]") &&
			!navLogo.classList.contains("text-white"),
		true,
		"#nav-logo must immediately receive scrolled classes on DOMContentLoaded when window.scrollY > 50",
	);
	assert.equal(
		langToggle.classList.contains("bg-slate-100") &&
			langToggle.classList.contains("text-slate-700") &&
			!langToggle.classList.contains("text-white"),
		true,
		"#lang-toggle must immediately receive scrolled classes on DOMContentLoaded when window.scrollY > 50",
	);
	assert.equal(
		navContact.classList.contains("text-slate-600") &&
			!navContact.classList.contains("text-white"),
		true,
		"#nav-contact must immediately receive scrolled classes on DOMContentLoaded when window.scrollY > 50",
	);
	assert.equal(
		navBook.classList.contains("text-slate-600") &&
			!navBook.classList.contains("text-white"),
		true,
		"#nav-book must immediately receive scrolled classes on DOMContentLoaded when window.scrollY > 50",
	);

	// Verify scrolling back to top (scrollY = 0) restores transparent classes
	sandbox.window.scrollY = 0;
	sandbox.window.dispatchEvent("scroll");
	assert.equal(navbar.classList.contains("bg-transparent"), true);
	assert.equal(navLogo.classList.contains("text-white"), true);

	// 3. Verify #lang-toggle-group focusout closes #lang-dropdown when relatedTarget is outside the group
	langToggle.dispatchEvent("click");
	assert.equal(langToggle.getAttribute("aria-expanded"), "true");
	assert.equal(langDropdown.classList.contains("visible"), true);
	assert.equal(langDropdown.classList.contains("invisible"), false);

	// Focus moving inside the group (e.g., from #lang-toggle to esLink) must keep dropdown open
	langToggleGroup.dispatchEvent("focusout", { relatedTarget: esLink });
	assert.equal(langToggle.getAttribute("aria-expanded"), "true");
	assert.equal(langDropdown.classList.contains("invisible"), false);

	// Focus tabbing out of #lang-toggle-group to an external element must close dropdown and reset aria-expanded="false"
	langToggleGroup.dispatchEvent("focusout", {
		relatedTarget: outsideFocusableNode,
	});
	assert.equal(
		langToggle.getAttribute("aria-expanded"),
		"false",
		"focusout outside #lang-toggle-group must reset aria-expanded='false'",
	);
	assert.equal(
		langDropdown.classList.contains("invisible") &&
			langDropdown.classList.contains("opacity-0") &&
			!langDropdown.classList.contains("visible") &&
			!langDropdown.classList.contains("opacity-100"),
		true,
		"focusout outside #lang-toggle-group must hide #lang-dropdown with opacity-0 and invisible",
	);
});

test("Homepage renders visible #properties heading, starting COP nightly rates, 900 Mbps Wi-Fi pills, badged & balanced 2-item #experiences cards, default-open #faq-3, and primary/secondary bottom CTA hierarchy across all 6 locales (#243)", () => {
	const distDir = path.join(process.cwd(), "dist");
	const locales = [
		{
			code: "en",
			file: "index.html",
			expectedPropHeading: "Our Featured Beachfront Properties",
			expected1707Rate: "From 450,000 COP / night",
			expected1606Rate: "From 350,000 COP / night",
			expectedWifi: "900 Mbps Wi-Fi",
			expectedExp1707Badge: "Oceanview Flat 1707 • 17th Floor • RNT #268781",
			expectedExp1606Badge: "Oceanview Flat 1606 • 16th Floor • RNT #270413",
			expectedExp1707FamilyTitle: "Spacious 4-Bed Family &amp; Group Comfort",
		},
		{
			code: "es",
			file: "es.html",
			expectedPropHeading: "Nuestras Propiedades Frente al Mar",
			expected1707Rate: "Desde 450.000 COP / noche",
			expected1606Rate: "Desde 350.000 COP / noche",
			expectedWifi: "Wi-Fi 900 Mbps",
			expectedExp1707Badge:
				"Apartamento Vista al Mar 1707 • Piso 17 • RNT #268781",
			expectedExp1606Badge:
				"Apartamento Vista al Mar 1606 • Piso 16 • RNT #270413",
			expectedExp1707FamilyTitle: "Comodidad de 4 Camas para Familias y Grupos",
		},
		{
			code: "fr",
			file: "fr.html",
			expectedPropHeading: "Nos Propriétés en Bord de Mer",
			expected1707Rate: "À partir de 450 000 COP / nuit",
			expected1606Rate: "À partir de 350 000 COP / nuit",
			expectedWifi: "Wi-Fi 900 Mbps",
			expectedExp1707Badge:
				"Appartement Vue Mer 1707 • 17e Étage • RNT #268781",
			expectedExp1606Badge:
				"Appartement Vue Mer 1606 • 16e Étage • RNT #270413",
			expectedExp1707FamilyTitle: "Confort 4 Lits pour Familles et Groupes",
		},
		{
			code: "it",
			file: "it.html",
			expectedPropHeading: "Le Nostre Proprietà Fronte Mare",
			expected1707Rate: "Da 450.000 COP / notte",
			expected1606Rate: "Da 350.000 COP / notte",
			expectedWifi: "Wi-Fi 900 Mbps",
			expectedExp1707Badge:
				"Appartamento Vista Mare 1707 • 17° Piano • RNT #268781",
			expectedExp1606Badge:
				"Appartamento Vista Mare 1606 • 16° Piano • RNT #270413",
			expectedExp1707FamilyTitle: "Comfort a 4 Letti per Famiglie e Gruppi",
		},
		{
			code: "de",
			file: "de.html",
			expectedPropHeading: "Unsere Strandunterkünfte",
			expected1707Rate: "Ab 450.000 COP / Nacht",
			expected1606Rate: "Ab 350.000 COP / Nacht",
			expectedWifi: "900 Mbps WLAN",
			expectedExp1707Badge:
				"Apartment mit Meerblick 1707 • 17. Etage • RNT #268781",
			expectedExp1606Badge:
				"Apartment mit Meerblick 1606 • 16. Etage • RNT #270413",
			expectedExp1707FamilyTitle:
				"Großzügiger 4-Betten-Komfort für Familien &amp; Gruppen",
		},
		{
			code: "ja",
			file: "ja.html",
			expectedPropHeading: "おすすめのビーチフロント物件",
			expected1707Rate: "1泊 450,000 COP〜",
			expected1606Rate: "1泊 350,000 COP〜",
			expectedWifi: "900 Mbps Wi-Fi",
			expectedExp1707Badge:
				"オーシャンビューフラット 1707 • 17階 • RNT #268781",
			expectedExp1606Badge:
				"オーシャンビューフラット 1606 • 16階 • RNT #270413",
			expectedExp1707FamilyTitle: "家族やグループに最適なベッド4台の広々空間",
		},
	];

	for (const locale of locales) {
		const html = fs.readFileSync(path.join(distDir, locale.file), "utf-8");

		// 1. #properties has a visible <h2> heading (not sr-only), canonical 450k/350k COP rate cues, and 900 Mbps Wi-Fi pills on both property cards
		const propSectionMatch = html.match(
			/<section[^>]*id="properties"[^>]*>[\s\S]*?<\/section>/,
		);
		assert.ok(propSectionMatch, `${locale.file} must render #properties`);
		const propSectionHtml = propSectionMatch[0];
		const propHeadingMatch = propSectionHtml.match(/<h2[^>]*>([\s\S]*?)<\/h2>/);
		assert.ok(
			propHeadingMatch &&
				!propHeadingMatch[0].includes("sr-only") &&
				propHeadingMatch[1].includes(locale.expectedPropHeading),
			`${locale.file} #properties must render a visible <h2> heading without sr-only`,
		);

		const card1707Match = propSectionHtml.match(
			/<a[^>]*id="home-card-1707"[^>]*>[\s\S]*?<\/a>/,
		);
		const card1606Match = propSectionHtml.match(
			/<a[^>]*id="home-card-1606"[^>]*>[\s\S]*?<\/a>/,
		);
		assert.ok(
			card1707Match && card1606Match,
			`${locale.file} must render both #home-card-1707 and #home-card-1606`,
		);
		assert.ok(
			card1707Match[0].includes(locale.expected1707Rate) &&
				card1707Match[0].includes("900 Mbps") &&
				card1707Match[0].includes(locale.expectedWifi),
			`${locale.file} #home-card-1707 must display "${locale.expected1707Rate}" and "${locale.expectedWifi}"`,
		);
		assert.ok(
			card1606Match[0].includes(locale.expected1606Rate) &&
				card1606Match[0].includes("900 Mbps") &&
				card1606Match[0].includes(locale.expectedWifi),
			`${locale.file} #home-card-1606 must display "${locale.expected1606Rate}" and "${locale.expectedWifi}"`,
		);

		// 2. #experiences cards display target Property identity badges (1707 and 1606) and render 2 balanced feature items per card
		const expMatch = html.match(
			/<section[^>]*id="experiences"[^>]*>[\s\S]*?<\/section>/,
		);
		assert.ok(expMatch, `${locale.file} must render #experiences`);
		const expHtml = expMatch[0];
		assert.ok(
			expHtml.includes(locale.expectedExp1707Badge) &&
				expHtml.includes(locale.expectedExp1606Badge),
			`${locale.file} #experiences must display Property identity badges "${locale.expectedExp1707Badge}" and "${locale.expectedExp1606Badge}"`,
		);
		assert.ok(
			expHtml.includes(locale.expectedExp1707FamilyTitle),
			`${locale.file} #experiences 1707 card must include 2nd family/group feature "${locale.expectedExp1707FamilyTitle}"`,
		);
		const expCardMatches = expHtml.match(
			/<a[^>]*href="[^"]*Oceanview1(707|606)[^"]*"[^>]*>[\s\S]*?<\/a>/g,
		);
		assert.ok(
			expCardMatches && expCardMatches.length === 2,
			`${locale.file} #experiences must render 2 property lifestyle cards`,
		);
		for (const expCardHtml of expCardMatches) {
			const h3Matches = expCardHtml.match(/<h3[^>]*>/g) || [];
			assert.equal(
				h3Matches.length,
				2,
				`${locale.file} each #experiences card must render 2 balanced feature items (<h3> headings)`,
			);
		}

		// 3. #faq pre-opens #faq-3 (faqQ3) by default and leaves #faq-1 closed
		const faq1Match = html.match(/<details[^>]*id="faq-1"[^>]*>/);
		const faq3Match = html.match(/<details[^>]*id="faq-3"[^>]*>/);
		assert.ok(
			faq1Match && faq3Match,
			`${locale.file} #faq must render #faq-1 and #faq-3`,
		);
		const openAttrRegex = /\bopen(?:="")?(?=\s|>)/;
		assert.ok(
			!openAttrRegex.test(faq1Match[0]),
			`${locale.file} #faq-1 must NOT be open by default`,
		);
		assert.ok(
			openAttrRegex.test(faq3Match[0]),
			`${locale.file} #faq-3 must have the open attribute by default`,
		);

		// 4. Bottom CTA buttons use primary solid white pill + secondary bordered dark slate pills
		assert.ok(
			!html.includes("bg-rose-700") && !html.includes("bg-emerald-700"),
			`${locale.file} bottom CTA must not render competing solid rose or emerald pills`,
		);
	}

	// 5. Verify src/constants/theme.ts has no duplicate paired color classes
	const themeSource = fs.readFileSync(
		path.join(process.cwd(), "src", "constants", "theme.ts"),
		"utf-8",
	);
	for (const duplicatePair of [
		"text-red-700 text-[#B91C1C]",
		"bg-rose-700 bg-[#BE123C]",
		"bg-emerald-700 bg-[#047857]",
		"text-sky-400 text-[#38BDF8]",
		"text-rose-400 text-[#FB7185]",
		"text-emerald-400 text-[#34D399]",
	]) {
		assert.ok(
			!themeSource.includes(duplicatePair),
			`src/constants/theme.ts must not contain duplicate color utility pair "${duplicatePair}"`,
		);
	}
});

test("Homepage aligns #why-book-direct Card 4 with Clearance Emerald, discloses pool/reception fee in #faq-2, chunks #faq-6, and surfaces verified guest testimonials + descriptive alts in #experiences across all 6 locales (#246)", () => {
	const distDir = path.join(process.cwd(), "dist");
	const locales = [
		{
			code: "en",
			file: "index.html",
			expectedFaq2Fee: "20,000 COP",
			expectedFaq2Day: "Tuesday",
			expectedNomadAlt: "Digital Nomad Workspace",
			expectedRomanticAlt: "Romantic Sunset Terrace",
			expected1707ReviewSnippet: "excellent view, beautiful sunsets",
			expected1707ReviewAuthor: "Karol Ximena",
			expected1606ReviewSnippet: "ideal for relaxation and family time",
			expected1606ReviewAuthor: "Lina Marcela",
		},
		{
			code: "es",
			file: "es.html",
			expectedFaq2Fee: "20.000 COP",
			expectedFaq2Day: "martes",
			expectedNomadAlt: "Espacio de trabajo para Nómadas Digitales",
			expectedRomanticAlt: "Terraza Privada al Atardecer",
			expected1707ReviewSnippet: "excelente vista, atardeceres hermosos",
			expected1707ReviewAuthor: "Karol Ximena",
			expected1606ReviewSnippet:
				"ideal para el descanso y compartir en familia",
			expected1606ReviewAuthor: "Lina Marcela",
		},
		{
			code: "fr",
			file: "fr.html",
			expectedFaq2Fee: "20 000 COP",
			expectedFaq2Day: "mardi",
			expectedNomadAlt: "Espace de travail pour Nomades Digitaux",
			expectedRomanticAlt: "Terrasse Privée au Coucher du Soleil",
			expected1707ReviewSnippet:
				"excellente vue, de magnifiques couchers de soleil",
			expected1707ReviewAuthor: "Karol Ximena",
			expected1606ReviewSnippet:
				"idéal pour la détente et les moments en famille",
			expected1606ReviewAuthor: "Lina Marcela",
		},
		{
			code: "it",
			file: "it.html",
			expectedFaq2Fee: "20.000 COP",
			expectedFaq2Day: "martedì",
			expectedNomadAlt: "Spazio di lavoro per Nomadi Digitali",
			expectedRomanticAlt: "Terrazza Privata al Tramonto",
			expected1707ReviewSnippet: "tramonti bellissimi",
			expected1707ReviewAuthor: "Karol Ximena",
			expected1606ReviewSnippet: "ideale per il relax e il tempo in famiglia",
			expected1606ReviewAuthor: "Lina Marcela",
		},
		{
			code: "de",
			file: "de.html",
			expectedFaq2Fee: "20.000 COP",
			expectedFaq2Day: "dienstags",
			expectedNomadAlt: "Arbeitsplatz für Digitale Nomaden",
			expectedRomanticAlt: "Private Terrasse bei Sonnenuntergang",
			expected1707ReviewSnippet:
				"hervorragenden Aussicht, wunderschönen Sonnenuntergängen",
			expected1707ReviewAuthor: "Karol Ximena",
			expected1606ReviewSnippet: "ideal für Entspannung und Familienzeit",
			expected1606ReviewAuthor: "Lina Marcela",
		},
		{
			code: "ja",
			file: "ja.html",
			expectedFaq2Fee: "20,000 COP",
			expectedFaq2Day: "火曜日",
			expectedNomadAlt: "デジタルノマドのワークスペース",
			expectedRomanticAlt: "サンセットを望むプライベートテラス",
			expected1707ReviewSnippet: "素晴らしい景色と美しい夕日",
			expected1707ReviewAuthor: "Karol Ximena",
			expected1606ReviewSnippet: "リラックスや家族との時間に最適な場所",
			expected1606ReviewAuthor: "リナ・マルセラ",
		},
	];

	for (const locale of locales) {
		const html = fs.readFileSync(path.join(distDir, locale.file), "utf-8");

		// 1. #why-book-direct Card 4 uses Clearance Emerald (bg-emerald-50 text-emerald-700) and never decorative Rose
		const directMatch = html.match(
			/<section[^>]*id="why-book-direct"[^>]*>[\s\S]*?<\/section>/,
		);
		assert.ok(directMatch, `${locale.file} must render #why-book-direct`);
		const directHtml = directMatch[0];
		const emeraldBadges =
			directHtml.match(/bg-emerald-50 text-emerald-700/g) || [];
		assert.equal(
			emeraldBadges.length,
			2,
			`${locale.file} #why-book-direct must use Clearance Emerald (bg-emerald-50 text-emerald-700) on both Card 1 and Card 4`,
		);
		assert.ok(
			!directHtml.includes("bg-rose-50 text-rose-700"),
			`${locale.file} #why-book-direct Card 4 must not use decorative Rose (bg-rose-50 text-rose-700)`,
		);

		// 2. #faq-2 discloses the per-person reception fee and Tuesday pool maintenance, and #faq-6 is structured into numbered steps
		const faq2Match = html.match(
			/<details[^>]*id="faq-2"[^>]*>[\s\S]*?<\/details>/,
		);
		assert.ok(faq2Match, `${locale.file} must render #faq-2`);
		const faq2Html = faq2Match[0];
		assert.ok(
			faq2Html.includes(locale.expectedFaq2Fee) &&
				faq2Html.includes(locale.expectedFaq2Day),
			`${locale.file} #faq-2 must disclose "${locale.expectedFaq2Fee}" reception fee and "${locale.expectedFaq2Day}" pool maintenance`,
		);

		const faq6Match = html.match(
			/<details[^>]*id="faq-6"[^>]*>[\s\S]*?<\/details>/,
		);
		assert.ok(faq6Match, `${locale.file} must render #faq-6`);
		const faq6Html = faq6Match[0];
		assert.ok(
			faq6Html.includes("1.") &&
				faq6Html.includes("2.") &&
				faq6Html.includes("3.") &&
				faq6Html.includes("1581") &&
				faq6Html.includes("679") &&
				faq6Html.includes("0XXXXXX#") &&
				faq6Html.includes("20,000 COP"),
			`${locale.file} #faq-6 must structure check-in, Guest Registry (Law 1581 & Law 679), 0XXXXXX# PIN, and 20,000 COP reception fee into numbered steps`,
		);

		// 3. #experiences renders verified guest testimonials (Karol Ximena on 1707, Lina Marcela on 1606) and descriptive image alts
		const expMatch = html.match(
			/<section[^>]*id="experiences"[^>]*>[\s\S]*?<\/section>/,
		);
		assert.ok(expMatch, `${locale.file} must render #experiences`);
		const expHtml = expMatch[0];
		const expCardMatches = expHtml.match(
			/<a[^>]*href="[^"]*Oceanview1(707|606)[^"]*"[^>]*>[\s\S]*?<\/a>/g,
		);
		assert.ok(
			expCardMatches && expCardMatches.length === 2,
			`${locale.file} #experiences must render 2 property lifestyle cards`,
		);
		const [card1707Html, card1606Html] = expCardMatches;

		assert.ok(
			card1707Html.includes(`alt="${locale.expectedNomadAlt}"`),
			`${locale.file} #experiences 1707 card image must use descriptive alt="${locale.expectedNomadAlt}"`,
		);
		assert.ok(
			card1707Html.includes(locale.expected1707ReviewSnippet) &&
				card1707Html.includes(locale.expected1707ReviewAuthor) &&
				card1707Html.includes("border-t border-slate-700/80"),
			`${locale.file} #experiences 1707 card must render Karol Ximena's verified review`,
		);

		assert.ok(
			card1606Html.includes(`alt="${locale.expectedRomanticAlt}"`),
			`${locale.file} #experiences 1606 card image must use descriptive alt="${locale.expectedRomanticAlt}"`,
		);
		assert.ok(
			card1606Html.includes(locale.expected1606ReviewSnippet) &&
				card1606Html.includes(locale.expected1606ReviewAuthor) &&
				card1606Html.includes("border-t border-slate-700/80"),
			`${locale.file} #experiences 1606 card must render Lina Marcela's verified review`,
		);
	}
});

test("Homepage highlights bed-capacity pills on #home-card-1707 and #home-card-1606, exposes mobile #nav-book, enforces focus-visible:ring-4 on interactive controls across all 6 locales, and includes prefers-reduced-motion in dist/css/style.css (#247)", () => {
	const distDir = path.join(process.cwd(), "dist");
	const locales = [
		{
			code: "en",
			file: "index.html",
			expected1707Beds: "2 Bedrooms • 4 Beds",
			expected1606Beds: "2 Bedrooms • 3 Beds",
		},
		{
			code: "es",
			file: "es.html",
			expected1707Beds: "2 Habitaciones • 4 Camas",
			expected1606Beds: "2 Habitaciones • 3 Camas",
		},
		{
			code: "fr",
			file: "fr.html",
			expected1707Beds: "2 Chambres • 4 Lits",
			expected1606Beds: "2 Chambres • 3 Lits",
		},
		{
			code: "it",
			file: "it.html",
			expected1707Beds: "2 Camere • 4 Letti",
			expected1606Beds: "2 Camere • 3 Letti",
		},
		{
			code: "de",
			file: "de.html",
			expected1707Beds: "2 Schlafzimmer • 4 Betten",
			expected1606Beds: "2 Schlafzimmer • 3 Betten",
		},
		{
			code: "ja",
			file: "ja.html",
			expected1707Beds: "2 寝室 • 4 ベッド",
			expected1606Beds: "2 寝室 • 3 ベッド",
		},
	];

	for (const locale of locales) {
		const html = fs.readFileSync(path.join(distDir, locale.file), "utf-8");

		// 1. #home-card-1707 and #home-card-1606 render cardSpecHighlight (bg-rose-50 border-rose-200 text-slate-900 font-bold) on the bedroom/bed count pill
		const card1707Match = html.match(
			/<a[^>]*id="home-card-1707"[^>]*>[\s\S]*?<\/a>/,
		);
		const card1606Match = html.match(
			/<a[^>]*id="home-card-1606"[^>]*>[\s\S]*?<\/a>/,
		);
		assert.ok(
			card1707Match && card1606Match,
			`${locale.file} must render #home-card-1707 and #home-card-1606`,
		);
		const card1707Html = card1707Match[0];
		const card1606Html = card1606Match[0];

		const highlightPillRegex1707 = new RegExp(
			`<span[^>]*class="[^"]*bg-rose-50[^"]*border-rose-200[^"]*text-slate-900[^"]*font-bold[^"]*"[^>]*>${locale.expected1707Beds}<\\/span>`,
		);
		const highlightPillRegex1606 = new RegExp(
			`<span[^>]*class="[^"]*bg-rose-50[^"]*border-rose-200[^"]*text-slate-900[^"]*font-bold[^"]*"[^>]*>${locale.expected1606Beds}<\\/span>`,
		);
		assert.ok(
			highlightPillRegex1707.test(card1707Html),
			`${locale.file} #home-card-1707 must highlight "${locale.expected1707Beds}" with bg-rose-50 border-rose-200 text-slate-900 font-bold`,
		);
		assert.ok(
			highlightPillRegex1606.test(card1606Html),
			`${locale.file} #home-card-1606 must highlight "${locale.expected1606Beds}" with bg-rose-50 border-rose-200 text-slate-900 font-bold`,
		);

		// 2. #nav-book is visible on mobile viewports (uses inline-flex without hidden md:inline-flex) and includes focus-visible:ring-4
		const navBookMatch = html.match(/<a[^>]*id="nav-book"[^>]*>/);
		assert.ok(navBookMatch, `${locale.file} must render #nav-book`);
		assert.ok(
			navBookMatch[0].includes("inline-flex") &&
				!navBookMatch[0].includes("hidden md:inline-flex") &&
				navBookMatch[0].includes("focus-visible:ring-4"),
			`${locale.file} #nav-book must use inline-flex without hidden md:inline-flex and include focus-visible:ring-4`,
		);

		// 3. Primary interactive homepage elements include focus-visible:ring-4, and Hero CTA icon uses text-[#FF5A5F]
		const card1707OpenTag = card1707Html.match(/^<a[^>]*>/)?.[0] || "";
		const card1606OpenTag = card1606Html.match(/^<a[^>]*>/)?.[0] || "";
		assert.ok(
			card1707OpenTag.includes("focus-visible:ring-4") &&
				card1606OpenTag.includes("focus-visible:ring-4"),
			`${locale.file} #home-card-1707 and #home-card-1606 must include focus-visible:ring-4`,
		);

		const headerMatch = html.match(/<header[\s\S]*?<\/header>/);
		assert.ok(headerMatch, `${locale.file} must render <header>`);
		const heroCtaMatch = headerMatch[0].match(
			/<a[^>]*href="#properties"[^>]*>/,
		);
		assert.ok(
			heroCtaMatch?.[0].includes("focus-visible:ring-4"),
			`${locale.file} Hero CTA must include focus-visible:ring-4`,
		);
		assert.ok(
			headerMatch[0].includes("text-[#FF5A5F]"),
			`${locale.file} Hero CTA icon must use text-[#FF5A5F] per DESIGN.md`,
		);

		const multiPropMatch = html.match(
			/<div[^>]*id="home-multi-property-note"[^>]*>[\s\S]*?<\/div>\s*<\/section>/,
		);
		assert.ok(
			multiPropMatch?.[0].includes("focus-visible:ring-4"),
			`${locale.file} #home-multi-property-note CTA must include focus-visible:ring-4`,
		);

		const expMatch = html.match(
			/<section[^>]*id="experiences"[^>]*>[\s\S]*?<\/section>/,
		);
		assert.ok(expMatch, `${locale.file} must render #experiences`);
		const expCardTags =
			expMatch[0].match(/<a[^>]*href="[^"]*Oceanview1(707|606)[^"]*"[^>]*>/g) ||
			[];
		assert.equal(expCardTags.length, 2);
		for (const tag of expCardTags) {
			assert.ok(
				tag.includes("focus-visible:ring-4"),
				`${locale.file} #experiences card must include focus-visible:ring-4`,
			);
		}

		const faqSummaries = html.match(/<summary[^>]*>/g) || [];
		assert.equal(faqSummaries.length, 6);
		for (const summaryTag of faqSummaries) {
			assert.ok(
				summaryTag.includes("focus-visible:ring-4"),
				`${locale.file} FAQ <summary> must include focus-visible:ring-4`,
			);
		}

		const whatsappBtnMatch = html.match(
			/<a[^>]*href="https:\/\/wa\.me\/573108155234"[^>]*>/,
		);
		assert.ok(
			whatsappBtnMatch?.[0].includes("focus-visible:ring-4"),
			`${locale.file} Bottom WhatsApp CTA must include focus-visible:ring-4`,
		);
	}

	// 4. Compiled dist/css/style.css includes @media (prefers-reduced-motion: reduce) safeguard
	const compiledCss = fs.readFileSync(
		path.join(distDir, "css", "style.css"),
		"utf-8",
	);
	assert.ok(
		/prefers-reduced-motion:\s*reduce/.test(compiledCss) &&
			/scroll-behavior:\s*auto\s*!important/.test(compiledCss) &&
			/transition-duration:\s*0?\.01ms\s*!important/.test(compiledCss),
		"dist/css/style.css must include @media (prefers-reduced-motion: reduce) with scroll-behavior: auto !important and transition-duration safeguard",
	);
});

test("Property 1707 and 1606 pages render 'Book with us' header links and place 'Check Selected Dates on Airbnb' below WhatsApp CTA enabled only when a date range is selected", () => {
	const distDir = path.join(process.cwd(), "dist");
	const locales = [
		{
			code: "en",
			suffix: "index.html",
			expectedNavBook: "Book with us",
			forbiddenNavAirbnb: "Book on Airbnb",
			expectedAirbnbCta: "Check Selected Dates on Airbnb",
			forbiddenDefaultAirbnbCta: "Check Availability on Airbnb",
		},
		{
			code: "es",
			suffix: "es.html",
			expectedNavBook: "Reservar con nosotros",
			forbiddenNavAirbnb: "Reservar en Airbnb",
			expectedAirbnbCta: "Ver Fechas en Airbnb",
			forbiddenDefaultAirbnbCta: "Ver Disponibilidad en Airbnb",
		},
		{
			code: "fr",
			suffix: "fr.html",
			expectedNavBook: "Réserver avec nous",
			forbiddenNavAirbnb: "Réserver sur Airbnb",
			expectedAirbnbCta: "Voir les dates sur Airbnb",
			forbiddenDefaultAirbnbCta: "Voir la disponibilité sur Airbnb",
		},
		{
			code: "it",
			suffix: "it.html",
			expectedNavBook: "Prenota con noi",
			forbiddenNavAirbnb: "Prenota su Airbnb",
			expectedAirbnbCta: "Verifica Date Selezionate su Airbnb",
			forbiddenDefaultAirbnbCta: "Verifica Disponibilità su Airbnb",
		},
		{
			code: "de",
			suffix: "de.html",
			expectedNavBook: "Bei uns buchen",
			forbiddenNavAirbnb: "Auf Airbnb buchen",
			expectedAirbnbCta: "Ausgewählte Daten auf Airbnb prüfen",
			forbiddenDefaultAirbnbCta: "Verfügbarkeit auf Airbnb prüfen",
		},
		{
			code: "ja",
			suffix: "ja.html",
			expectedNavBook: "当サイトで予約",
			forbiddenNavAirbnb: "Airbnbで予約",
			expectedAirbnbCta: "Airbnbで選択した日付を確認",
			forbiddenDefaultAirbnbCta: "Airbnbで空室状況を確認",
		},
	];

	for (const prop of ["Oceanview1707", "Oceanview1606"]) {
		for (const locale of locales) {
			const relFile = `${prop}/${locale.suffix}`;
			const html = fs.readFileSync(path.join(distDir, relFile), "utf-8");

			// 1. Navbar (#nav-book) and Hero header CTA both display "Book with us" (expectedNavBook) and never "Book on Airbnb"
			const navMatch = html.match(/<nav[\s\S]*?<\/nav>/);
			const headerMatch = html.match(/<header[\s\S]*?<\/header>/);
			assert.ok(
				navMatch && headerMatch,
				`${relFile} must render <nav> and <header>`,
			);
			assert.ok(
				navMatch[0].includes(locale.expectedNavBook) &&
					!navMatch[0].includes(locale.forbiddenNavAirbnb),
				`${relFile} <nav> must display "${locale.expectedNavBook}"`,
			);
			assert.ok(
				headerMatch[0].includes(locale.expectedNavBook) &&
					!headerMatch[0].includes(locale.forbiddenNavAirbnb),
				`${relFile} <header> must display "${locale.expectedNavBook}"`,
			);

			// 2. #btn-book appears AFTER #whatsapp-booking-link and is disabled by default with "Check Selected Dates on Airbnb"
			const whatsappIndex = html.indexOf('id="whatsapp-booking-link"');
			const btnBookIndex = html.indexOf('id="btn-book"');
			assert.ok(
				whatsappIndex !== -1 &&
					btnBookIndex !== -1 &&
					btnBookIndex > whatsappIndex,
				`${relFile} #btn-book must be placed below #whatsapp-booking-link`,
			);

			const btnBookMatch = html.match(
				/<a[^>]*id="btn-book"[^>]*>[\s\S]*?<\/a>/,
			);
			assert.ok(btnBookMatch, `${relFile} must render #btn-book`);
			const btnBookHtml = btnBookMatch[0];
			assert.ok(
				btnBookHtml.includes('aria-disabled="true"') &&
					btnBookHtml.includes('tabindex="-1"') &&
					!btnBookHtml.includes("href=") &&
					btnBookHtml.includes("pointer-events-none") &&
					btnBookHtml.includes("opacity-50") &&
					btnBookHtml.includes(locale.expectedAirbnbCta) &&
					!btnBookHtml.includes(locale.forbiddenDefaultAirbnbCta),
				`${relFile} #btn-book must be disabled by default and display "${locale.expectedAirbnbCta}"`,
			);

			// 3. #price-breakdown-card renders Accommodation and Cleaning Fee only, without Lobby Register / Resort Fee
			assert.ok(
				html.includes('id="rate-breakdown-label"') &&
					html.includes('id="cleaning-fee-label"') &&
					!html.includes('id="resort-fee-label"') &&
					!html.includes('id="resort-fee-value"'),
				`${relFile} #price-breakdown-card must omit #resort-fee-label and #resort-fee-value`,
			);
		}
	}
});

test("main.js #calendar-widget fetches blocked dates via pageConfig.apiBase and enables same-day Turnover Check-out selection (#252)", async () => {
	const mainJsCode = fs.readFileSync(
		path.join(process.cwd(), "public", "js", "main.js"),
		"utf-8",
	);

	interface MockEvent {
		target?: MockNode;
		preventDefault?: () => void;
		stopPropagation?: () => void;
	}
	type Listener = (e?: MockEvent) => void;
	interface MockNode {
		id: string;
		tagName: string;
		textContent: string;
		innerHTML: string;
		className: string;
		value: string;
		href: string;
		disabled: boolean;
		children: MockNode[];
		classList: {
			add: (...cls: string[]) => void;
			remove: (...cls: string[]) => void;
			contains: (c: string) => boolean;
		};
		getAttribute: (k: string) => string | null;
		setAttribute: (k: string, v: string) => void;
		removeAttribute: (k: string) => void;
		addEventListener: (evt: string, fn: Listener) => void;
		dispatchEvent: (evt: string, payload?: MockEvent) => void;
		appendChild: (child: MockNode) => MockNode;
	}

	const createMockNode = (
		id: string,
		tagName: string,
		initialClasses: string[] = [],
		initialAttrs: Record<string, string> = {},
	): MockNode => {
		const classes = new Set<string>(initialClasses);
		const attrs = new Map<string, string>(Object.entries(initialAttrs));
		const listeners: Record<string, Listener[]> = {};
		let children: MockNode[] = [];
		let innerHTMLVal = "";

		const node: MockNode = {
			id,
			tagName,
			textContent: "",
			get innerHTML() {
				return innerHTMLVal;
			},
			set innerHTML(val: string) {
				innerHTMLVal = val;
				if (val === "") {
					children = [];
				}
			},
			className: initialClasses.join(" "),
			value: "",
			href: "",
			disabled: false,
			get children() {
				return children;
			},
			classList: {
				add: (...cls: string[]) => {
					for (const c of cls) classes.add(c);
				},
				remove: (...cls: string[]) => {
					for (const c of cls) classes.delete(c);
				},
				contains: (c: string) => classes.has(c),
			},
			getAttribute: (k: string) => attrs.get(k) ?? null,
			setAttribute: (k: string, v: string) => {
				attrs.set(k, v);
			},
			removeAttribute: (k: string) => {
				attrs.delete(k);
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
			appendChild: (child: MockNode) => {
				children.push(child);
				return child;
			},
		};
		return node;
	};

	const monthNames = {
		en: [
			"January",
			"February",
			"March",
			"April",
			"May",
			"June",
			"July",
			"August",
			"September",
			"October",
			"November",
			"December",
		],
	};
	const dayNames = {
		en: ["Su", "Mo", "Tu", "We", "Th", "Fr", "Sa"],
	};

	const calendarWidget = createMockNode("calendar-widget", "DIV", [], {
		"data-lang": "en",
		"data-month-names": JSON.stringify(monthNames),
		"data-day-names": JSON.stringify(dayNames),
	});
	const btnPrevMonth = createMockNode("btn-prev-month", "BUTTON");
	const btnNextMonth = createMockNode("btn-next-month", "BUTTON");
	const monthLabel = createMockNode("month-label", "SPAN");
	const calendarGrid = createMockNode("calendar-grid", "DIV");
	const checkInDisplay = createMockNode("check-in-display", "DIV", [], {
		"data-text-add-date": "Add date",
	});
	const checkOutDisplay = createMockNode("check-out-display", "DIV", [], {
		"data-text-add-date": "Add date",
	});
	const btnBook = createMockNode("btn-book", "A", [], {
		"data-airbnb-url": "https://airbnb.com/rooms/1606",
		"data-text-ready": "Check Selected Dates on Airbnb",
		"data-text-default": "Check Selected Dates on Airbnb",
	});
	const btnBookText = createMockNode("btn-book-text", "SPAN");
	const priceBreakdownCard = createMockNode("price-breakdown-card", "DIV", [
		"hidden",
	]);
	const directBookingForm = createMockNode("direct-booking-form", "FORM", [
		"hidden",
	]);
	const formCheckIn = createMockNode("form-check-in-date", "INPUT");
	const formCheckOut = createMockNode("form-check-out-date", "INPUT");
	const formTotalPrice = createMockNode("form-total-price-cop", "INPUT");
	const rateBreakdownValue = createMockNode(
		"rate-breakdown-value",
		"SPAN",
		[],
		{
			"data-nights-format": "({nights} nights)",
		},
	);
	const cleaningFeeValue = createMockNode("cleaning-fee-value", "SPAN");
	const totalCopValue = createMockNode("total-cop-value", "SPAN");

	const elementsById = new Map<string, MockNode>([
		["calendar-widget", calendarWidget],
		["btn-prev-month", btnPrevMonth],
		["btn-next-month", btnNextMonth],
		["month-label", monthLabel],
		["calendar-grid", calendarGrid],
		["check-in-display", checkInDisplay],
		["check-out-display", checkOutDisplay],
		["btn-book", btnBook],
		["btn-book-text", btnBookText],
		["price-breakdown-card", priceBreakdownCard],
		["direct-booking-form", directBookingForm],
		["form-check-in-date", formCheckIn],
		["form-check-out-date", formCheckOut],
		["form-total-price-cop", formTotalPrice],
		["rate-breakdown-value", rateBreakdownValue],
		["cleaning-fee-value", cleaningFeeValue],
		["total-cop-value", totalCopValue],
	]);

	// Compute next month dynamically so all dates in the target month are in the future relative to Date()
	const now = new Date();
	const targetMonthDate = new Date(now.getFullYear(), now.getMonth() + 1, 1);
	const ym = `${targetMonthDate.getFullYear()}-${String(targetMonthDate.getMonth() + 1).padStart(2, "0")}`;
	const blockedList = [`${ym}-10`, `${ym}-15`, `${ym}-16`];

	const fetchedUrls: string[] = [];
	const docListeners: Record<string, Listener[]> = {};
	const scriptStub = {
		async: false,
		src: "",
		onload: null as (() => void) | null,
		onerror: null as (() => void) | null,
		addEventListener: () => {},
		parentNode: { insertBefore: () => {} },
	};

	const sandbox = {
		window: {
			_paq: [] as unknown[],
			scrollY: 0,
			MercadoPago: () => {},
			location: {
				search: "",
				pathname: "/Oceanview1606/index.html",
			},
			getPageConfig: () => ({
				pageId: "Oceanview1606",
				lang: "en",
				assetPrefix: "../",
				apiBase: "../api/",
				i18n: {},
			}),
			t: (key: string, _params?: Record<string, unknown>, fallback?: string) =>
				fallback ?? key,
			addEventListener: () => {},
		},
		document: {
			documentElement: { lang: "en" },
			head: { appendChild: () => {} },
			createElement: (tag: string) => {
				if (tag.toLowerCase() === "script") {
					return { ...scriptStub };
				}
				return createMockNode("", tag.toUpperCase());
			},
			getElementsByTagName: () => [scriptStub],
			querySelector: () => null,
			querySelectorAll: () => [],
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
		localStorage: {
			getItem: () => null,
			setItem: () => {},
		},
		fetch: async (url: string) => {
			fetchedUrls.push(url);
			if (url.includes("availability.php")) {
				return {
					ok: true,
					json: async () => blockedList,
				};
			}
			return {
				ok: true,
				json: async () => [],
			};
		},
		Intl,
		Date,
		URLSearchParams,
		FormData: class {},
		console: {
			log: () => {},
			warn: () => {},
			error: () => {},
		},
	};

	vm.createContext(sandbox);
	vm.runInContext(mainJsCode, sandbox);
	sandbox.document.dispatchEvent("DOMContentLoaded");

	// Allow async fetchBlockedDates() promise microtasks to settle
	await new Promise((resolve) => setTimeout(resolve, 10));

	// 1. Verify availability endpoint was resolved via pageConfig.apiBase ("../api/availability.php?property=1606")
	assert.ok(
		fetchedUrls.includes("../api/availability.php?property=1606"),
		`Expected "../api/availability.php?property=1606" in fetched URLs, got: ${JSON.stringify(fetchedUrls)}`,
	);

	// Navigate to next month so all days are in the future
	btnNextMonth.dispatchEvent("click");

	const getDayButton = (dayNum: number): MockNode => {
		const dateStr = `${ym}-${String(dayNum).padStart(2, "0")}`;
		const found = calendarGrid.children.find(
			(child) => child.getAttribute("data-date") === dateStr,
		);
		assert.ok(found, `Day button for ${dateStr} must exist`);
		return found;
	};

	// 2. In Check-in selection mode: blocked dates (10, 15, 16) are disabled and have line-through
	assert.equal(getDayButton(10).disabled, true);
	assert.ok(getDayButton(10).className.includes("line-through"));
	assert.equal(getDayButton(12).disabled, false);
	assert.equal(getDayButton(15).disabled, true);
	assert.ok(getDayButton(15).className.includes("line-through"));
	assert.equal(getDayButton(16).disabled, true);
	assert.equal(getDayButton(17).disabled, false);

	// 3. Click day 12 as Check-in Date -> enters Check-out selection mode
	getDayButton(12).dispatchEvent("click");
	assert.ok(getDayButton(12).className.includes("bg-[#FF5A5F]"));

	// Blocked date <= checkIn (day 10) remains disabled
	assert.equal(getDayButton(10).disabled, true);
	// Available days 13 and 14 are enabled
	assert.equal(getDayButton(13).disabled, false);
	assert.equal(getDayButton(14).disabled, false);
	// First blocked Night after checkIn (day 15) is enabled as same-day Turnover Check-out candidate without line-through
	assert.equal(
		getDayButton(15).disabled,
		false,
		"First blocked Night after checkIn (day 15) must be enabled for same-day Turnover check-out",
	);
	assert.ok(
		!getDayButton(15).className.includes("line-through"),
		"Turnover check-out candidate must not render line-through while in Check-out selection mode",
	);
	// Dates strictly after firstBlockedAfterCheckIn (days 16, 17, 18) are disabled
	assert.equal(getDayButton(16).disabled, true);
	assert.equal(
		getDayButton(17).disabled,
		true,
		"Unblocked date beyond firstBlockedAfterCheckIn (day 17) must be disabled in Check-out selection mode",
	);
	assert.equal(getDayButton(18).disabled, true);

	// 4. Click day 15 (the same-day Turnover candidate) -> sets checkOut to day 15
	getDayButton(15).dispatchEvent("click");
	assert.equal(formCheckIn.value, `${ym}-12`);
	assert.equal(formCheckOut.value, `${ym}-15`);
	assert.ok(
		getDayButton(15).className.includes(
			"bg-[#FF5A5F] text-white font-medium",
		) && !getDayButton(15).className.includes("line-through"),
		"Selected Turnover checkOut date must render bg-[#FF5A5F] text-white font-medium without strikethrough",
	);
	assert.ok(
		getDayButton(13).className.includes("bg-[#FF5A5F]/10") &&
			getDayButton(14).className.includes("bg-[#FF5A5F]/10"),
		"Intermediate nights 13 and 14 must render in-range highlight",
	);
});

test("Home Page across all 6 locales renders 450k (1707) and 350k (1606) baseline COP rates inside #home-rate-badge-1707 and #home-rate-badge-1606 and hydrates live rates via quote.php?action=rates (#255)", async () => {
	const distDir = path.join(process.cwd(), "dist");
	const locales = [
		{
			code: "en",
			file: "index.html",
			expected1707: "From 450,000 COP / night",
			expected1606: "From 350,000 COP / night",
			expectedFormat: "From {rate} COP / night",
			hydrated1707: "From 520,000 COP / night",
			hydrated1606: "From 390,000 COP / night",
		},
		{
			code: "es",
			file: "es.html",
			expected1707: "Desde 450.000 COP / noche",
			expected1606: "Desde 350.000 COP / noche",
			expectedFormat: "Desde {rate} COP / noche",
			hydrated1707: "Desde 520.000 COP / noche",
			hydrated1606: "Desde 390.000 COP / noche",
		},
		{
			code: "fr",
			file: "fr.html",
			expected1707: "À partir de 450 000 COP / nuit",
			expected1606: "À partir de 350 000 COP / nuit",
			expectedFormat: "À partir de {rate} COP / nuit",
			hydrated1707: "À partir de 520 000 COP / nuit",
			hydrated1606: "À partir de 390 000 COP / nuit",
		},
		{
			code: "it",
			file: "it.html",
			expected1707: "Da 450.000 COP / notte",
			expected1606: "Da 350.000 COP / notte",
			expectedFormat: "Da {rate} COP / notte",
			hydrated1707: "Da 520.000 COP / notte",
			hydrated1606: "Da 390.000 COP / notte",
		},
		{
			code: "de",
			file: "de.html",
			expected1707: "Ab 450.000 COP / Nacht",
			expected1606: "Ab 350.000 COP / Nacht",
			expectedFormat: "Ab {rate} COP / Nacht",
			hydrated1707: "Ab 520.000 COP / Nacht",
			hydrated1606: "Ab 390.000 COP / Nacht",
		},
		{
			code: "ja",
			file: "ja.html",
			expected1707: "1泊 450,000 COP〜",
			expected1606: "1泊 350,000 COP〜",
			expectedFormat: "1泊 {rate} COP〜",
			hydrated1707: "1泊 520,000 COP〜",
			hydrated1606: "1泊 390,000 COP〜",
		},
	];

	const pageConfigCode = fs.readFileSync(
		path.join(process.cwd(), "public", "js", "page-config.js"),
		"utf-8",
	);
	const mainJsCode = fs.readFileSync(
		path.join(process.cwd(), "public", "js", "main.js"),
		"utf-8",
	);

	for (const locale of locales) {
		const html = fs.readFileSync(path.join(distDir, locale.file), "utf-8");

		// 1. Never renders outdated 300,000 / 300.000 / 300 000 COP rate
		assert.ok(
			!html.includes("300,000") &&
				!html.includes("300.000") &&
				!html.includes("300 000"),
			`${locale.file} must never render outdated 300,000 COP starting rate`,
		);

		// 2. Renders #home-rate-badge-1707 and #home-rate-badge-1606 with 450k and 350k baseline rates
		const badge1707Match = html.match(
			/<span[^>]*id="home-rate-badge-1707"[^>]*>([\s\S]*?)<\/span>/,
		);
		const badge1606Match = html.match(
			/<span[^>]*id="home-rate-badge-1606"[^>]*>([\s\S]*?)<\/span>/,
		);
		assert.ok(
			badge1707Match && badge1707Match[1] === locale.expected1707,
			`${locale.file} #home-rate-badge-1707 must equal "${locale.expected1707}"`,
		);
		assert.ok(
			badge1606Match && badge1606Match[1] === locale.expected1606,
			`${locale.file} #home-rate-badge-1606 must equal "${locale.expected1606}"`,
		);

		// 3. Page config includes homeCardRateFormat in i18n slice
		const configMatch = html.match(
			/<script id="ovf-page-config" type="application\/json">(.*?)<\/script>/,
		);
		assert.ok(configMatch, `${locale.file} must embed #ovf-page-config`);
		const parsedConfig = JSON.parse(configMatch[1]);
		assert.equal(parsedConfig.i18n.homeCardRateFormat, locale.expectedFormat);

		// 4. VM execution hydrates #home-rate-badge-1707 and #home-rate-badge-1606 via quote.php?action=rates
		const badge1707Node = {
			id: "home-rate-badge-1707",
			textContent: locale.expected1707,
		};
		const badge1606Node = {
			id: "home-rate-badge-1606",
			textContent: locale.expected1606,
		};
		const fetchedUrls: string[] = [];
		const docListeners: Record<string, Array<() => void>> = {};
		const scriptStub = {
			async: false,
			src: "",
			parentNode: { insertBefore: () => {} },
		};

		const sandbox = {
			window: {
				_paq: [] as unknown[],
				scrollY: 0,
				location: { search: "", pathname: `/${locale.file}` },
				addEventListener: () => {},
			} as Record<string, unknown>,
			document: {
				documentElement: { lang: locale.code },
				createElement: () => ({ ...scriptStub }),
				getElementsByTagName: () => [scriptStub],
				getElementById: (id: string) => {
					if (id === "ovf-page-config") return { textContent: configMatch[1] };
					if (id === "home-rate-badge-1707") return badge1707Node;
					if (id === "home-rate-badge-1606") return badge1606Node;
					return null;
				},
				addEventListener: (evt: string, fn: () => void) => {
					docListeners[evt] = docListeners[evt] || [];
					docListeners[evt].push(fn);
				},
			},
			localStorage: {
				getItem: () => null,
				setItem: () => {},
			},
			fetch: async (url: string) => {
				fetchedUrls.push(url);
				return {
					ok: true,
					json: async () => ({
						success: true,
						data: {
							properties: {
								"1707": {
									property_id: "1707",
									current_nightly_rate_cop: 520000,
								},
								"1606": {
									property_id: "1606",
									current_nightly_rate_cop: 390000,
								},
							},
						},
					}),
				};
			},
			console: { error: () => {}, warn: () => {}, log: () => {} },
		};

		vm.createContext(sandbox);
		vm.runInContext(pageConfigCode, sandbox);
		vm.runInContext(mainJsCode, sandbox);
		for (const fn of docListeners.DOMContentLoaded || []) {
			fn();
		}
		await new Promise((resolve) => setTimeout(resolve, 10));

		assert.ok(
			fetchedUrls.includes("./api/quote.php?action=rates"),
			`${locale.file} must fetch "./api/quote.php?action=rates", got: ${JSON.stringify(fetchedUrls)}`,
		);
		assert.equal(badge1707Node.textContent, locale.hydrated1707);
		assert.equal(badge1606Node.textContent, locale.hydrated1606);
	}
});

test("Property Page #calendar-widget fetches authoritative Quote from quote.php via pageConfig.apiBase, enforces minimum_stay_required with localized #min-stay-warning, and enables checkout when valid (#255)", async () => {
	const distDir = path.join(process.cwd(), "dist");
	const propHtmlEn = fs.readFileSync(
		path.join(distDir, "Oceanview1707", "index.html"),
		"utf-8",
	);
	assert.ok(
		propHtmlEn.includes('id="min-stay-warning"'),
		"Oceanview1707/index.html must render #min-stay-warning inside #price-breakdown-card",
	);
	const configMatch = propHtmlEn.match(
		/<script id="ovf-page-config" type="application\/json">(.*?)<\/script>/,
	);
	assert.ok(
		configMatch,
		"Oceanview1707/index.html must embed #ovf-page-config",
	);
	const parsedConfig = JSON.parse(configMatch[1]);
	assert.equal(
		parsedConfig.i18n.dbMinStayNotice,
		"A minimum stay of {minStay} nights is required for the selected dates.",
	);
	assert.equal(parsedConfig.i18n.dbNightsFormat, "{nights} nights");

	const homeHtmlEn = fs.readFileSync(path.join(distDir, "index.html"), "utf-8");
	const homeConfigMatch = homeHtmlEn.match(
		/<script id="ovf-page-config" type="application\/json">(.*?)<\/script>/,
	);
	assert.ok(homeConfigMatch, "index.html must embed #ovf-page-config");
	assert.equal(
		JSON.parse(homeConfigMatch[1]).i18n.dbMinStayNotice,
		undefined,
		"dbMinStayNotice must be scoped to property pages and omitted from common/home i18n slice",
	);

	const pageConfigCode = fs.readFileSync(
		path.join(process.cwd(), "public", "js", "page-config.js"),
		"utf-8",
	);
	const mainJsCode = fs.readFileSync(
		path.join(process.cwd(), "public", "js", "main.js"),
		"utf-8",
	);

	interface MockEvent {
		target?: MockNode;
		preventDefault?: () => void;
		stopPropagation?: () => void;
	}
	type Listener = (e?: MockEvent) => void;
	interface MockNode {
		id: string;
		tagName: string;
		textContent: string;
		innerHTML: string;
		className: string;
		value: string;
		href: string;
		disabled: boolean;
		children: MockNode[];
		classList: {
			add: (...cls: string[]) => void;
			remove: (...cls: string[]) => void;
			contains: (c: string) => boolean;
		};
		getAttribute: (k: string) => string | null;
		setAttribute: (k: string, v: string) => void;
		removeAttribute: (k: string) => void;
		addEventListener: (evt: string, fn: Listener) => void;
		dispatchEvent: (evt: string, payload?: MockEvent) => void;
		appendChild: (child: MockNode) => MockNode;
	}

	const createMockNode = (
		id: string,
		tagName: string,
		initialClasses: string[] = [],
		initialAttrs: Record<string, string> = {},
	): MockNode => {
		const classes = new Set<string>(initialClasses);
		const attrs = new Map<string, string>(Object.entries(initialAttrs));
		const listeners: Record<string, Listener[]> = {};
		let children: MockNode[] = [];
		let innerHTMLVal = "";

		const node: MockNode = {
			id,
			tagName,
			textContent: "",
			get innerHTML() {
				return innerHTMLVal;
			},
			set innerHTML(val: string) {
				innerHTMLVal = val;
				if (val === "") {
					children = [];
				}
			},
			className: initialClasses.join(" "),
			value: "",
			href: "",
			disabled: false,
			get children() {
				return children;
			},
			classList: {
				add: (...cls: string[]) => {
					for (const c of cls) classes.add(c);
				},
				remove: (...cls: string[]) => {
					for (const c of cls) classes.delete(c);
				},
				contains: (c: string) => classes.has(c),
			},
			getAttribute: (k: string) => attrs.get(k) ?? null,
			setAttribute: (k: string, v: string) => {
				attrs.set(k, v);
			},
			removeAttribute: (k: string) => {
				attrs.delete(k);
				if (k === "href") node.href = "";
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
			appendChild: (child: MockNode) => {
				children.push(child);
				return child;
			},
		};
		return node;
	};

	const monthNames = {
		en: [
			"January",
			"February",
			"March",
			"April",
			"May",
			"June",
			"July",
			"August",
			"September",
			"October",
			"November",
			"December",
		],
	};
	const dayNames = {
		en: ["Su", "Mo", "Tu", "We", "Th", "Fr", "Sa"],
	};

	const calendarWidget = createMockNode("calendar-widget", "DIV", [], {
		"data-lang": "en",
		"data-month-names": JSON.stringify(monthNames),
		"data-day-names": JSON.stringify(dayNames),
	});
	const btnPrevMonth = createMockNode("btn-prev-month", "BUTTON");
	const btnNextMonth = createMockNode("btn-next-month", "BUTTON");
	const monthLabel = createMockNode("month-label", "SPAN");
	const calendarGrid = createMockNode("calendar-grid", "DIV");
	const checkInDisplay = createMockNode("check-in-display", "DIV", [], {
		"data-text-add-date": "Add date",
	});
	const checkOutDisplay = createMockNode("check-out-display", "DIV", [], {
		"data-text-add-date": "Add date",
	});
	const btnBook = createMockNode("btn-book", "A", [], {
		"data-airbnb-url": "https://airbnb.com/rooms/1707",
		"data-text-ready": "Check Selected Dates on Airbnb",
		"data-text-default": "Check Selected Dates on Airbnb",
	});
	const btnBookText = createMockNode("btn-book-text", "SPAN");
	const priceBreakdownCard = createMockNode("price-breakdown-card", "DIV", [
		"hidden",
	]);
	const minStayWarning = createMockNode("min-stay-warning", "DIV", ["hidden"]);
	const directBookingForm = createMockNode("direct-booking-form", "FORM", [
		"hidden",
	]);
	const btnDirectSubmit = createMockNode("btn-direct-submit", "BUTTON");
	const formCheckIn = createMockNode("form-check-in-date", "INPUT");
	const formCheckOut = createMockNode("form-check-out-date", "INPUT");
	const formTotalPrice = createMockNode("form-total-price-cop", "INPUT");
	const rateBreakdownValue = createMockNode(
		"rate-breakdown-value",
		"SPAN",
		[],
		{
			"data-nights-format": "{nights} nights",
		},
	);
	const cleaningFeeValue = createMockNode("cleaning-fee-value", "SPAN");
	const totalCopValue = createMockNode("total-cop-value", "SPAN");
	const convertedCurrencyBox = createMockNode("converted-currency-box", "DIV", [
		"hidden",
	]);
	const convertedCurrencyValue = createMockNode(
		"converted-currency-value",
		"SPAN",
	);

	const elementsById = new Map<string, MockNode>([
		["calendar-widget", calendarWidget],
		["btn-prev-month", btnPrevMonth],
		["btn-next-month", btnNextMonth],
		["month-label", monthLabel],
		["calendar-grid", calendarGrid],
		["check-in-display", checkInDisplay],
		["check-out-display", checkOutDisplay],
		["btn-book", btnBook],
		["btn-book-text", btnBookText],
		["price-breakdown-card", priceBreakdownCard],
		["min-stay-warning", minStayWarning],
		["direct-booking-form", directBookingForm],
		["btn-direct-submit", btnDirectSubmit],
		["form-check-in-date", formCheckIn],
		["form-check-out-date", formCheckOut],
		["form-total-price-cop", formTotalPrice],
		["rate-breakdown-value", rateBreakdownValue],
		["cleaning-fee-value", cleaningFeeValue],
		["total-cop-value", totalCopValue],
		["converted-currency-box", convertedCurrencyBox],
		["converted-currency-value", convertedCurrencyValue],
	]);

	const now = new Date();
	const targetMonthDate = new Date(now.getFullYear(), now.getMonth() + 1, 1);
	const ym = `${targetMonthDate.getFullYear()}-${String(targetMonthDate.getMonth() + 1).padStart(2, "0")}`;

	const fetchedUrls: string[] = [];
	const docListeners: Record<string, Listener[]> = {};
	const scriptStub = {
		async: false,
		src: "",
		onload: null as (() => void) | null,
		onerror: null as (() => void) | null,
		addEventListener: () => {},
		parentNode: { insertBefore: () => {} },
	};

	const sandbox = {
		window: {
			_paq: [] as unknown[],
			scrollY: 0,
			MercadoPago: () => {},
			location: {
				search: "",
				pathname: "/Oceanview1707/index.html",
			},
			addEventListener: () => {},
		} as Record<string, unknown>,
		document: {
			documentElement: { lang: "en" },
			head: { appendChild: () => {} },
			createElement: (tag: string) => {
				if (tag.toLowerCase() === "script") {
					return { ...scriptStub };
				}
				return createMockNode("", tag.toUpperCase());
			},
			getElementsByTagName: () => [scriptStub],
			querySelector: () => null,
			querySelectorAll: () => [],
			getElementById: (id: string) => {
				if (id === "ovf-page-config") {
					return { textContent: configMatch[1] };
				}
				return elementsById.get(id) || null;
			},
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
		localStorage: {
			getItem: (key: string) => {
				if (key === "cop_exchange_rates")
					return JSON.stringify({ USD: 0.00025 });
				if (key === "cop_exchange_rates_time") return String(Date.now());
				return null;
			},
			setItem: () => {},
		},
		fetch: async (url: string) => {
			fetchedUrls.push(url);
			if (url.includes("availability.php")) {
				return {
					ok: true,
					json: async () => [],
				};
			}
			if (url.includes("quote.php") && url.includes(`check_out=${ym}-12`)) {
				// 2-night stay in a 4-night minimum stay seasonal tier -> invalid!
				return {
					ok: true,
					json: async () => ({
						success: true,
						data: {
							property_id: "1707",
							check_in: `${ym}-10`,
							check_out: `${ym}-12`,
							nights_count: 2,
							accommodation_total_cop: 1100000,
							cleaning_fee_cop: 100000,
							total_cop: 1200000,
							minimum_stay_required: 4,
							is_valid: false,
							violation_reason: "min_stay",
						},
					}),
				};
			}
			if (url.includes("quote.php") && url.includes(`check_out=${ym}-14`)) {
				// 4-night stay in a 4-night minimum stay seasonal tier -> valid!
				return {
					ok: true,
					json: async () => ({
						success: true,
						data: {
							property_id: "1707",
							check_in: `${ym}-10`,
							check_out: `${ym}-14`,
							nights_count: 4,
							accommodation_total_cop: 2200000,
							cleaning_fee_cop: 100000,
							total_cop: 2300000,
							minimum_stay_required: 4,
							is_valid: true,
							violation_reason: null,
						},
					}),
				};
			}
			return {
				ok: true,
				json: async () => [],
			};
		},
		Intl,
		Date,
		URLSearchParams,
		FormData: class {},
		console: { log: () => {}, warn: () => {}, error: () => {} },
	};

	vm.createContext(sandbox);
	vm.runInContext(pageConfigCode, sandbox);
	vm.runInContext(mainJsCode, sandbox);
	sandbox.document.dispatchEvent("DOMContentLoaded");
	await new Promise((resolve) => setTimeout(resolve, 10));

	// Navigate to next month
	btnNextMonth.dispatchEvent("click");

	const getDayButton = (dayNum: number): MockNode => {
		const dateStr = `${ym}-${String(dayNum).padStart(2, "0")}`;
		const found = calendarGrid.children.find(
			(child) => child.getAttribute("data-date") === dateStr,
		);
		assert.ok(found, `Day button for ${dateStr} must exist`);
		return found;
	};

	// 1. Select 2-night range (day 10 to day 12) which violates minimum_stay_required = 4
	getDayButton(10).dispatchEvent("click");
	getDayButton(12).dispatchEvent("click");
	assert.equal(
		directBookingForm.classList.contains("hidden"),
		true,
		"#direct-booking-form must not flash visible before authoritative quote resolves",
	);
	await new Promise((resolve) => setTimeout(resolve, 15));

	assert.ok(
		fetchedUrls.includes(
			`../api/quote.php?property_id=1707&check_in=${ym}-10&check_out=${ym}-12`,
		),
		`Expected "../api/quote.php?property_id=1707&check_in=${ym}-10&check_out=${ym}-12" in ${JSON.stringify(fetchedUrls)}`,
	);
	assert.equal(priceBreakdownCard.classList.contains("hidden"), false);
	assert.equal(String(formTotalPrice.value), "1200000");
	assert.ok(rateBreakdownValue.textContent.includes("2 nights"));
	assert.ok(totalCopValue.textContent.includes("COP"));
	assert.ok(convertedCurrencyValue.textContent.includes("300.00 USD"));

	// Minimum-stay warning is visible, checkout form is hidden/disabled, Airbnb button is disabled
	assert.equal(
		minStayWarning.classList.contains("hidden"),
		false,
		"#min-stay-warning must be visible when is_valid === false",
	);
	assert.equal(
		minStayWarning.textContent,
		"A minimum stay of 4 nights is required for the selected dates.",
	);
	assert.equal(
		directBookingForm.classList.contains("hidden"),
		true,
		"#direct-booking-form must remain hidden when is_valid === false",
	);
	assert.equal(btnDirectSubmit.disabled, true);
	assert.equal(btnBook.getAttribute("aria-disabled"), "true");
	assert.equal(btnBook.classList.contains("pointer-events-none"), true);

	// 2. Select valid 4-night range (day 10 to day 14) -> warning clears and checkout form + Airbnb button enable
	getDayButton(10).dispatchEvent("click");
	getDayButton(14).dispatchEvent("click");
	await new Promise((resolve) => setTimeout(resolve, 15));

	assert.ok(
		fetchedUrls.includes(
			`../api/quote.php?property_id=1707&check_in=${ym}-10&check_out=${ym}-14`,
		),
		`Expected "../api/quote.php?property_id=1707&check_in=${ym}-10&check_out=${ym}-14" in ${JSON.stringify(fetchedUrls)}`,
	);
	assert.equal(String(formTotalPrice.value), "2300000");
	assert.ok(rateBreakdownValue.textContent.includes("4 nights"));
	assert.ok(convertedCurrencyValue.textContent.includes("575.00 USD"));
	assert.equal(
		minStayWarning.classList.contains("hidden"),
		true,
		"#min-stay-warning must hide when is_valid === true",
	);
	assert.equal(minStayWarning.textContent, "");
	assert.equal(
		directBookingForm.classList.contains("hidden"),
		false,
		"#direct-booking-form must be revealed when is_valid === true",
	);
	assert.equal(btnDirectSubmit.disabled, false);
	assert.equal(btnBook.getAttribute("aria-disabled"), "false");
	assert.equal(btnBook.classList.contains("pointer-events-none"), false);
});

test("BookingSuccess 6-locale zero-hydration SSG markup, centralized BOOKING_SUCCESS_THEME & getRegistryPagePath, approved checkout redirect + sessionStorage persistence, and reservation code DOM hydration (#264)", async () => {
	const { getRegistryPagePath } = await import("../../src/constants/config");
	assert.equal(getRegistryPagePath("en", "../"), "../registry/index.html");
	assert.equal(getRegistryPagePath("es", "../"), "../registry/es.html");
	assert.equal(getRegistryPagePath("fr", "../"), "../registry/fr.html");
	assert.equal(getRegistryPagePath("it", "../"), "../registry/it.html");
	assert.equal(getRegistryPagePath("de", "../"), "../registry/de.html");
	assert.equal(getRegistryPagePath("ja", "../"), "../registry/ja.html");
	assert.equal(getRegistryPagePath("en"), "./registry/index.html");

	// 1. Verify BookingSuccess.tsx is 100% style-agnostic without inline class strings or React state/effect hooks
	const bookingSuccessSource = fs.readFileSync(
		path.join(process.cwd(), "src", "pages", "BookingSuccess.tsx"),
		"utf-8",
	);
	assert.ok(
		bookingSuccessSource.includes("BOOKING_SUCCESS_THEME") &&
			bookingSuccessSource.includes("getRegistryPagePath") &&
			bookingSuccessSource.includes("getGuidePagePath") &&
			bookingSuccessSource.includes("getHomePagePath"),
		"BookingSuccess.tsx must consume BOOKING_SUCCESS_THEME, getRegistryPagePath, getGuidePagePath, and getHomePagePath",
	);
	assert.ok(
		!bookingSuccessSource.includes('className="'),
		"BookingSuccess.tsx must not contain any hardcoded inline className strings",
	);
	assert.ok(
		!bookingSuccessSource.includes("useState") &&
			!bookingSuccessSource.includes("useEffect"),
		"BookingSuccess.tsx must not contain client-side useState/useEffect hooks in zero-hydration SSG",
	);

	// 2. Verify compiled 6-locale booking-success HTML files
	const distDir = path.join(process.cwd(), "dist");
	const locales = [
		{
			code: "en",
			file: "booking-success/index.html",
			expectedRegistryHref: "../registry/index.html",
			expectedGuideHref: "../guide/index.html",
			expectedHomeHref: "../",
			expectedRegistryBtn: "Complete Guest Registry",
			expectedRefLabel: "Reservation Reference Code",
		},
		{
			code: "es",
			file: "booking-success/es.html",
			expectedRegistryHref: "../registry/es.html",
			expectedGuideHref: "../guide/es.html",
			expectedHomeHref: "../es.html",
			expectedRegistryBtn: "Completar Registro de Huéspedes",
			expectedRefLabel: "Código de Referencia de la Reserva",
		},
		{
			code: "fr",
			file: "booking-success/fr.html",
			expectedRegistryHref: "../registry/fr.html",
			expectedGuideHref: "../guide/fr.html",
			expectedHomeHref: "../fr.html",
			expectedRegistryBtn: "Compléter le Registre des Invités",
			expectedRefLabel: "Code de Référence de la Réservation",
		},
		{
			code: "it",
			file: "booking-success/it.html",
			expectedRegistryHref: "../registry/it.html",
			expectedGuideHref: "../guide/it.html",
			expectedHomeHref: "../it.html",
			expectedRegistryBtn: "Completa il Registro degli Ospiti",
			expectedRefLabel: "Codice di Riferimento della Prenotazione",
		},
		{
			code: "de",
			file: "booking-success/de.html",
			expectedRegistryHref: "../registry/de.html",
			expectedGuideHref: "../guide/de.html",
			expectedHomeHref: "../de.html",
			expectedRegistryBtn: "Gästeregistrierung Abschließen",
			expectedRefLabel: "Reservierungs-Referenzcode",
		},
		{
			code: "ja",
			file: "booking-success/ja.html",
			expectedRegistryHref: "../registry/ja.html",
			expectedGuideHref: "../guide/ja.html",
			expectedHomeHref: "../ja.html",
			expectedRegistryBtn: "宿泊者名簿（ゲスト登録）を完了する",
			expectedRefLabel: "予約参照コード",
		},
	];

	for (const locale of locales) {
		const html = fs.readFileSync(path.join(distDir, locale.file), "utf-8");

		assert.ok(
			html.includes('id="booking-reference-box"') &&
				html.includes('id="display-reservation-code"') &&
				html.includes('id="btn-copy-reservation-code"') &&
				html.includes('id="copy-code-feedback"') &&
				html.includes('id="booking-registry-notice"') &&
				html.includes('id="btn-booking-registry"') &&
				html.includes('id="btn-booking-guide"') &&
				html.includes('id="btn-booking-home"'),
			`${locale.file} must pre-render deterministic confirmation DOM IDs`,
		);
		assert.ok(
			html.includes(locale.expectedRefLabel) &&
				html.includes(locale.expectedRegistryBtn) &&
				html.includes("1581") &&
				html.includes("0XXXXXX#"),
			`${locale.file} must render localized reference label, Guest Registry CTA, and ADR 0001 / Law 1581 notice`,
		);
		assert.ok(
			html.includes(
				`id="btn-booking-registry" href="${locale.expectedRegistryHref}"`,
			) ||
				html.includes(
					`href="${locale.expectedRegistryHref}" id="btn-booking-registry"`,
				),
			`${locale.file} #btn-booking-registry must link to ${locale.expectedRegistryHref}`,
		);
		assert.ok(
			html.includes(
				`id="btn-booking-guide" href="${locale.expectedGuideHref}"`,
			) ||
				html.includes(
					`href="${locale.expectedGuideHref}" id="btn-booking-guide"`,
				),
			`${locale.file} #btn-booking-guide must link to ${locale.expectedGuideHref}`,
		);
		assert.ok(
			html.includes(
				`id="btn-booking-home" href="${locale.expectedHomeHref}"`,
			) ||
				html.includes(
					`href="${locale.expectedHomeHref}" id="btn-booking-home"`,
				),
			`${locale.file} #btn-booking-home must link to ${locale.expectedHomeHref}`,
		);

		const configMatch = html.match(
			/<script id="ovf-page-config" type="application\/json">(.*?)<\/script>/,
		);
		assert.ok(configMatch, `${locale.file} must embed #ovf-page-config`);
		const parsedConfig = JSON.parse(configMatch[1]);
		assert.ok(
			typeof parsedConfig.i18n.guideCopySuccess === "string" &&
				parsedConfig.i18n.guideCopySuccess.length > 0,
			`${locale.file} page config must expose guideCopySuccess in i18n slice`,
		);
	}

	// 3. Verify main.js approved checkout saves sessionStorage context and redirects to localized booking-success route
	const pageConfigCode = fs.readFileSync(
		path.join(process.cwd(), "public", "js", "page-config.js"),
		"utf-8",
	);
	const mainJsCode = fs.readFileSync(
		path.join(process.cwd(), "public", "js", "main.js"),
		"utf-8",
	);

	interface MockNode {
		id: string;
		tagName: string;
		textContent: string;
		innerHTML: string;
		className: string;
		value: string;
		href: string;
		disabled: boolean;
		checked: boolean;
		style: Record<string, string>;
		classList: {
			add: (...cls: string[]) => void;
			remove: (...cls: string[]) => void;
			contains: (c: string) => boolean;
		};
		getAttribute: (k: string) => string | null;
		setAttribute: (k: string, v: string) => void;
		removeAttribute: (k: string) => void;
		addEventListener: (evt: string, fn: (e?: unknown) => void) => void;
		dispatchEvent: (evt: string, payload?: Record<string, unknown>) => void;
		appendChild: (child: MockNode) => MockNode;
		querySelector: (sel: string) => MockNode | null;
		querySelectorAll: (sel: string) => MockNode[];
	}

	const createNode = (
		id: string,
		tagName = "DIV",
		initialClasses: string[] = [],
		initialAttrs: Record<string, string> = {},
	): MockNode => {
		const classes = new Set<string>(initialClasses);
		const attrs = new Map<string, string>(Object.entries(initialAttrs));
		const listeners: Record<string, Array<(e?: unknown) => void>> = {};
		const children: MockNode[] = [];

		const node: MockNode = {
			id,
			tagName,
			textContent: "",
			innerHTML: "",
			className: initialClasses.join(" "),
			value: initialAttrs.value || "",
			href: initialAttrs.href || "",
			disabled: false,
			checked: false,
			style: {},
			classList: {
				add: (...cls: string[]) => {
					for (const c of cls) classes.add(c);
				},
				remove: (...cls: string[]) => {
					for (const c of cls) classes.delete(c);
				},
				contains: (c: string) => classes.has(c),
			},
			getAttribute: (k: string) => attrs.get(k) ?? null,
			setAttribute: (k: string, v: string) => {
				attrs.set(k, v);
				if (k === "href") node.href = v;
			},
			removeAttribute: (k: string) => {
				attrs.delete(k);
			},
			addEventListener: (evt: string, fn: (e?: unknown) => void) => {
				listeners[evt] = listeners[evt] || [];
				listeners[evt].push(fn);
			},
			dispatchEvent: (evt: string, payload: Record<string, unknown> = {}) => {
				for (const fn of listeners[evt] || []) {
					fn({
						target: node,
						preventDefault: () => {},
						stopPropagation: () => {},
						...payload,
					});
				}
			},
			appendChild: (child: MockNode) => {
				children.push(child);
				return child;
			},
			querySelector: () => null,
			querySelectorAll: () => children,
		};
		return node;
	};

	const runApprovedPaymentRedirectTest = async (langCode: "en" | "es") => {
		let capturedBrickConfig: {
			callbacks?: {
				onSubmit?: (args: {
					selectedPaymentMethod: string;
					formData: Record<string, unknown>;
				}) => Promise<unknown>;
			};
		} | null = null;
		const sessionStore = new Map<string, string>();

		const calendarWidget = createNode("calendar-widget", "DIV", [], {
			"data-lang": langCode,
			"data-month-names": JSON.stringify({
				[langCode]: Array(12).fill("Month"),
			}),
			"data-day-names": JSON.stringify({
				[langCode]: Array(7).fill("D"),
			}),
		});
		const directBookingForm = createNode("direct-booking-form", "FORM", [], {
			"data-mp-public-key": "TEST-KEY",
		});
		const guestNameInput = createNode("booking-guest-name", "INPUT", [], {
			value: "Mateo Restrepo",
		});
		const guestEmailInput = createNode("booking-guest-email", "INPUT", [], {
			value: "mateo@example.com",
		});
		const guestPhoneInput = createNode("booking-guest-phone", "INPUT", [], {
			value: "+573001112233",
		});
		const captchaResponseInput = createNode(
			"booking-captcha-response",
			"INPUT",
			[],
			{ value: "7" },
		);
		const privacyConsentInput = createNode("booking-privacy-consent", "INPUT");
		privacyConsentInput.checked = true;
		const formCheckIn = createNode("form-check-in-date", "INPUT", [], {
			value: "2026-12-10",
		});
		const formCheckOut = createNode("form-check-out-date", "INPUT", [], {
			value: "2026-12-14",
		});
		const btnBook = createNode("btn-book", "A", [], {
			"data-airbnb-url": "https://airbnb.com/rooms/1707",
			"data-text-ready": "Ready",
			"data-text-default": "Default",
		});

		const nodesById = new Map<string, MockNode>([
			["calendar-widget", calendarWidget],
			["btn-prev-month", createNode("btn-prev-month", "BUTTON")],
			["btn-next-month", createNode("btn-next-month", "BUTTON")],
			["month-label", createNode("month-label", "SPAN")],
			["calendar-grid", createNode("calendar-grid", "DIV")],
			["check-in-display", createNode("check-in-display", "DIV")],
			["check-out-display", createNode("check-out-display", "DIV")],
			["btn-book", btnBook],
			["btn-book-text", createNode("btn-book-text", "SPAN")],
			["direct-booking-form", directBookingForm],
			["booking-guest-name", guestNameInput],
			["booking-guest-email", guestEmailInput],
			["booking-guest-phone", guestPhoneInput],
			["booking-captcha-response", captchaResponseInput],
			["booking-privacy-consent", privacyConsentInput],
			["form-check-in-date", formCheckIn],
			["form-check-out-date", formCheckOut],
			["payment-brick-container", createNode("payment-brick-container", "DIV")],
		]);

		const docListeners: Record<string, Array<() => void>> = {};
		const scriptStub = {
			async: false,
			src: "",
			parentNode: { insertBefore: () => {} },
		};

		const sandbox = {
			window: {
				_paq: [] as unknown[],
				scrollY: 0,
				MercadoPago: function () {
					return {
						bricks: () => ({
							create: async (
								_type: string,
								_container: string,
								cfg: typeof capturedBrickConfig,
							) => {
								capturedBrickConfig = cfg;
								return { unmount: async () => {} };
							},
						}),
					};
				},
				location: {
					search: "",
					pathname: `/Oceanview1707/${langCode === "en" ? "index.html" : `${langCode}.html`}`,
					href: "",
				},
				getPageConfig: () => ({
					pageId: "Oceanview1707",
					lang: langCode,
					assetPrefix: "../",
					apiBase: "../api/",
					i18n: {},
				}),
				t: (k: string, _p?: unknown, fb?: string) => fb ?? k,
				addEventListener: () => {},
			},
			document: {
				documentElement: { lang: langCode },
				head: { appendChild: () => {} },
				createElement: (tag: string) =>
					tag.toLowerCase() === "script"
						? { ...scriptStub }
						: createNode("", tag.toUpperCase()),
				getElementsByTagName: () => [scriptStub],
				querySelector: () => null,
				querySelectorAll: () => [],
				getElementById: (id: string) => nodesById.get(id) || null,
				addEventListener: (evt: string, fn: () => void) => {
					docListeners[evt] = docListeners[evt] || [];
					docListeners[evt].push(fn);
				},
			},
			sessionStorage: {
				getItem: (k: string) => sessionStore.get(k) ?? null,
				setItem: (k: string, v: string) => {
					sessionStore.set(k, v);
				},
			},
			localStorage: {
				getItem: () => null,
				setItem: () => {},
			},
			fetch: async (url: string) => {
				if (url.includes("book-request.php")) {
					return {
						ok: true,
						json: async () => ({
							success: true,
							total_price: 1900000,
							reservation_uid: "ovf_res_264",
						}),
					};
				}
				if (url.includes("payment.php")) {
					return {
						ok: true,
						json: async () => ({
							success: true,
							status: "approved",
							payment_id: "mp_pay_777",
							reservation_code: "ovf_res_264",
						}),
					};
				}
				return { ok: true, json: async () => [] };
			},
			Intl,
			Date,
			URLSearchParams,
			FormData: class {},
			console: { log: () => {}, warn: () => {}, error: () => {} },
		};

		vm.createContext(sandbox);
		vm.runInContext(mainJsCode, sandbox);
		for (const fn of docListeners.DOMContentLoaded || []) fn();
		await new Promise((r) => setTimeout(r, 15));

		// Select two future dates on the calendar so checkIn and checkOut are set
		const nextMonthBtn = nodesById.get("btn-next-month");
		assert.ok(nextMonthBtn);
		nextMonthBtn.dispatchEvent("click");
		const calendarGridNode = nodesById.get("calendar-grid");
		assert.ok(calendarGridNode);
		const gridChildren = calendarGridNode.querySelectorAll("*");
		gridChildren[10].dispatchEvent("click");
		gridChildren[14].dispatchEvent("click");
		formCheckIn.value = "2026-12-10";
		formCheckOut.value = "2026-12-14";

		// Submit direct booking form to initialize Payment Brick
		directBookingForm.dispatchEvent("submit");
		await new Promise((r) => setTimeout(r, 20));
		const onSubmit = capturedBrickConfig?.callbacks?.onSubmit;
		assert.ok(onSubmit);

		await onSubmit({
			selectedPaymentMethod: "credit_card",
			formData: { token: "tok_123" },
		});

		const expectedTarget =
			langCode === "en"
				? "../booking-success/index.html?id=mp_pay_777&code=ovf_res_264&status=approved"
				: `../booking-success/${langCode}.html?id=mp_pay_777&code=ovf_res_264&status=approved`;
		assert.equal(sandbox.window.location.href, expectedTarget);
		assert.equal(sessionStore.get("ovf_last_reservation_code"), "ovf_res_264");
		const savedContext = JSON.parse(
			sessionStore.get("ovf_checkout_context") || "{}",
		);
		assert.deepEqual(savedContext, {
			code: "ovf_res_264",
			guest_name: "Mateo Restrepo",
			guest_email: "mateo@example.com",
			guest_phone: "+573001112233",
			property_id: "1707",
			check_in: formCheckIn.value,
			check_out: formCheckOut.value,
		});
	};

	await runApprovedPaymentRedirectTest("en");
	await runApprovedPaymentRedirectTest("es");

	// 4. Verify DOMContentLoaded hydration on booking-success (via URL query string AND via sessionStorage fallback)
	const runConfirmationHydrationTest = async (
		locationSearch: string,
		initialSessionCode: string | null,
	) => {
		const esHtml = fs.readFileSync(
			path.join(distDir, "booking-success", "es.html"),
			"utf-8",
		);
		const configMatchEs = esHtml.match(
			/<script id="ovf-page-config" type="application\/json">(.*?)<\/script>/,
		);
		assert.ok(configMatchEs);
		const configJson = configMatchEs[1];

		const refBox = createNode("booking-reference-box", "DIV", ["hidden"]);
		const codeDisplay = createNode("display-reservation-code", "SPAN");
		const copyBtn = createNode("btn-copy-reservation-code", "BUTTON");
		const copyFeedback = createNode("copy-code-feedback", "SPAN", ["hidden"]);
		const registryBtn = createNode("btn-booking-registry", "A", [], {
			href: "../registry/es.html",
		});
		const guideBtn = createNode("btn-booking-guide", "A", [], {
			href: "../guide/es.html",
		});
		const homeBtn = createNode("btn-booking-home", "A", [], {
			href: "../es.html",
		});

		const langGroup = createNode("lang-toggle-group", "DIV");
		const langEnLink = createNode("lang-en", "A", [], { href: "index.html" });
		const langFrLink = createNode("lang-fr", "A", [], { href: "fr.html" });
		langGroup.appendChild(langEnLink);
		langGroup.appendChild(langFrLink);

		const mapById = new Map<string, MockNode>([
			["booking-reference-box", refBox],
			["display-reservation-code", codeDisplay],
			["btn-copy-reservation-code", copyBtn],
			["copy-code-feedback", copyFeedback],
			["btn-booking-registry", registryBtn],
			["btn-booking-guide", guideBtn],
			["btn-booking-home", homeBtn],
			["lang-toggle-group", langGroup],
		]);

		let copiedText = "";
		const sessionStore = new Map<string, string>();
		if (initialSessionCode) {
			sessionStore.set("ovf_last_reservation_code", initialSessionCode);
		}
		const docListeners: Record<string, Array<() => void>> = {};
		const scriptStub = {
			async: false,
			src: "",
			parentNode: { insertBefore: () => {} },
		};

		const sandbox = {
			window: {
				_paq: [] as unknown[],
				scrollY: 0,
				location: {
					search: locationSearch,
					pathname: "/booking-success/es.html",
				},
				addEventListener: () => {},
			} as Record<string, unknown>,
			navigator: {
				clipboard: {
					writeText: async (txt: string) => {
						copiedText = txt;
					},
				},
			},
			document: {
				documentElement: { lang: "es" },
				createElement: () => ({ ...scriptStub }),
				getElementsByTagName: () => [scriptStub],
				getElementById: (id: string) => {
					if (id === "ovf-page-config") return { textContent: configJson };
					return mapById.get(id) || null;
				},
				addEventListener: (evt: string, fn: () => void) => {
					docListeners[evt] = docListeners[evt] || [];
					docListeners[evt].push(fn);
				},
			},
			sessionStorage: {
				getItem: (k: string) => sessionStore.get(k) ?? null,
				setItem: (k: string, v: string) => {
					sessionStore.set(k, v);
				},
			},
			localStorage: {
				getItem: () => null,
				setItem: () => {},
			},
			URLSearchParams,
			setTimeout,
			console: { log: () => {}, warn: () => {}, error: () => {} },
		};

		vm.createContext(sandbox);
		vm.runInContext(pageConfigCode, sandbox);
		vm.runInContext(mainJsCode, sandbox);
		for (const fn of docListeners.DOMContentLoaded || []) fn();

		assert.equal(
			refBox.classList.contains("hidden"),
			false,
			"#booking-reference-box must be revealed when reservation code is resolved",
		);
		assert.equal(codeDisplay.textContent, "ovf_res_264");
		assert.equal(sessionStore.get("ovf_last_reservation_code"), "ovf_res_264");
		assert.equal(
			registryBtn.getAttribute("href"),
			"../registry/es.html?code=ovf_res_264",
		);
		assert.equal(
			guideBtn.getAttribute("href"),
			"../guide/es.html?code=ovf_res_264",
		);
		assert.equal(homeBtn.getAttribute("href"), "../es.html");
		assert.ok(
			langEnLink.getAttribute("href")?.includes("code=ovf_res_264") &&
				langFrLink.getAttribute("href")?.includes("code=ovf_res_264"),
			"Language switcher links must preserve code=ovf_res_264",
		);

		copyBtn.dispatchEvent("click");
		await new Promise((r) => setTimeout(r, 10));
		assert.equal(copiedText, "ovf_res_264");
		assert.equal(copyFeedback.classList.contains("hidden"), false);
		assert.equal(copyFeedback.textContent, "¡Copiado!");
	};

	await runConfirmationHydrationTest(
		"?id=mp_pay_777&code=ovf_res_264&status=approved",
		null,
	);
	await runConfirmationHydrationTest("", "ovf_res_264");
});
