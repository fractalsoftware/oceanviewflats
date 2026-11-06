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
				!propSectionHtml.includes("bg-rose-50"),
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
			`${locale.file} #faq must have the first <details> item open by default`,
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
