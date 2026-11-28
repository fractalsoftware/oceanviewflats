import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import test from "node:test";
import vm from "node:vm";
import { LANGUAGES } from "../../src/config/pages";

const DIST_DIR = path.join(process.cwd(), "dist");
const PUBLIC_DIR = path.join(process.cwd(), "public");

type Listener = (event?: Record<string, unknown>) => void | Promise<void>;

interface MockClassList {
	add: (...cls: string[]) => void;
	remove: (...cls: string[]) => void;
	contains: (cls: string) => boolean;
}

interface MockElement {
	id: string;
	tagName: string;
	type: string;
	name: string;
	value: string;
	href: string;
	checked: boolean;
	disabled: boolean;
	required: boolean;
	textContent: string;
	className: string;
	style: Record<string, string>;
	childNodes: Array<{ cloneNode: (deep?: boolean) => unknown }>;
	classList: MockClassList;
	setAttribute: (key: string, value: string) => void;
	removeAttribute: (key: string) => void;
	getAttribute: (key: string) => string | null;
	hasAttribute: (key: string) => boolean;
	addEventListener: (event: string, fn: Listener) => void;
	removeEventListener: (event: string, fn: Listener) => void;
	dispatchEvent: (
		event: string,
		payload?: Record<string, unknown>,
	) => Promise<void>;
	querySelectorAll: (selector: string) => MockElement[];
	scrollIntoView: () => void;
	closest: () => null;
	appendChild: (node: unknown) => void;
	replaceChildren: (...nodes: unknown[]) => void;
}

function createMockElement(
	id: string,
	tagName = "DIV",
	initialClasses: string[] = [],
): MockElement {
	const classes = new Set<string>(initialClasses);
	const attrs = new Map<string, string>();
	const listeners = new Map<string, Listener[]>();

	const el: MockElement = {
		id,
		tagName,
		type: tagName === "INPUT" ? "text" : "",
		name: id,
		value: "",
		href: "",
		checked: false,
		disabled: false,
		required: false,
		textContent: "",
		className: initialClasses.join(" "),
		style: {},
		childNodes: [{ cloneNode: () => ({ cloneNode: () => ({}) }) }],
		classList: {
			add: (...cls: string[]) => {
				for (const c of cls) classes.add(c);
			},
			remove: (...cls: string[]) => {
				for (const c of cls) classes.delete(c);
			},
			contains: (cls: string) => classes.has(cls),
		},
		setAttribute: (key: string, value: string) => {
			attrs.set(key, value);
			if (key === "disabled") el.disabled = true;
			if (key === "required") el.required = true;
		},
		removeAttribute: (key: string) => {
			attrs.delete(key);
			if (key === "disabled") el.disabled = false;
			if (key === "required") el.required = false;
		},
		getAttribute: (key: string) => attrs.get(key) ?? null,
		hasAttribute: (key: string) => attrs.has(key),
		addEventListener: (event: string, fn: Listener) => {
			const list = listeners.get(event) ?? [];
			list.push(fn);
			listeners.set(event, list);
		},
		removeEventListener: (event: string, fn: Listener) => {
			const list = listeners.get(event);
			if (!list) return;
			listeners.set(
				event,
				list.filter((item) => item !== fn),
			);
		},
		dispatchEvent: async (
			event: string,
			payload: Record<string, unknown> = {},
		) => {
			const list = [...(listeners.get(event) ?? [])];
			for (const fn of list) {
				await fn({
					target: el,
					preventDefault: () => {},
					...payload,
				});
			}
		},
		querySelectorAll: () => [],
		scrollIntoView: () => {},
		closest: () => null,
		appendChild: () => {},
		replaceChildren: () => {},
	};

	return el;
}

const flushMicrotasks = async (turns = 6) => {
	for (let i = 0; i < turns; i++) {
		await new Promise((resolve) => setImmediate(resolve));
	}
};

type FetchHandler = (
	url: string,
	init?: Record<string, unknown>,
) => Promise<{
	ok: boolean;
	status: number;
	json: () => Promise<Record<string, unknown>>;
}>;

interface RegistryVmOptions {
	href?: string;
	referrer?: string;
	lang?: string;
	loadAnalytics?: boolean;
	registryScriptPath?: string;
	fetchHandler?: FetchHandler;
}

async function createRegistryHarness(options: RegistryVmOptions = {}) {
	const lang = options.lang ?? "en";
	const href =
		options.href ??
		"https://www.oceanviewflats.com/registry/?code=ovf_secret_res_901&property=1707&check_in=2027-01-10&check_out=2027-01-15&utm_source=email";
	const referrer =
		options.referrer ??
		"https://www.oceanviewflats.com/booking-success/?code=ovf_secret_res_901&guest=Maria%20Garcia&utm_medium=transactional#confirmed";

	const parsedUrl = new URL(href);
	const paq: unknown[][] = [];
	const windowEvents: string[] = [];
	const documentEvents: string[] = [];
	const domListeners = new Map<string, Listener[]>();

	const pageConfigPayload = {
		pageId: "registry",
		lang,
		assetPrefix: "../",
		apiBase: "../api/",
		minorDocTypes: ["TI", "RC"],
		analytics: {
			pageType: "registry",
			propertyId: "portfolio",
			enableEngagementTracking: false,
		},
		i18n: {
			registryLookupLoading: "Cargando...",
			registryLookupNotFound: "No se encontró reserva con este código.",
			registryAlreadyCompleted: "El registro ya fue completado.",
			registryConcluded: "Esta reserva ha concluido.",
			registryErrEmail: "Correo electrónico inválido.",
			registryErrPhoneRequired: "Teléfono obligatorio inválido.",
			registryErrPrivacyConsent: "Debe autorizar el tratamiento de datos.",
			registryErrMinorConsent: "Debe confirmar la autorización de menores.",
			registryGenericError: "Por favor revise los campos requeridos.",
			registrySubmitting: "Enviando...",
			registrySubmit: "Completar registro",
		},
	};

	const elements = new Map<string, MockElement>();
	const register = (el: MockElement) => {
		elements.set(el.id, el);
		return el;
	};

	const configEl = register(createMockElement("ovf-page-config", "SCRIPT"));
	configEl.textContent = JSON.stringify(pageConfigPayload);

	const form = register(createMockElement("registry-form", "FORM"));
	form.setAttribute("action", "../api/registry-processor.php");
	register(createMockElement("submit-button", "BUTTON"));
	const submitText = register(createMockElement("submit-text", "SPAN"));
	submitText.textContent = "Complete Guest Registration";
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
	register(createMockElement("add-guest-button", "BUTTON"));
	const guestCountInput = register(
		createMockElement("guest-count-input", "INPUT"),
	);
	guestCountInput.type = "hidden";
	guestCountInput.value = "1";

	const captchaLabel = register(createMockElement("captcha-label", "LABEL"));
	captchaLabel.textContent = "Security Check";
	const captchaChallenge = register(
		createMockElement("captcha-challenge", "INPUT"),
	);
	captchaChallenge.type = "hidden";
	const captchaResponse = register(
		createMockElement("captcha-response", "INPUT"),
	);
	captchaResponse.setAttribute("required", "required");
	captchaResponse.value = "7";

	const privacyConsent = register(
		createMockElement("privacy-consent", "INPUT"),
	);
	privacyConsent.type = "checkbox";
	privacyConsent.name = "privacy_consent";
	privacyConsent.setAttribute("required", "required");
	privacyConsent.checked = true;

	register(createMockElement("minor-attestation-container", "DIV", ["hidden"]));
	const minorConsent = register(
		createMockElement("minor-guardian-consent", "INPUT"),
	);
	minorConsent.type = "checkbox";
	minorConsent.name = "minor_guardian_consent";
	minorConsent.setAttribute("disabled", "disabled");

	const allFormInputs: MockElement[] = [
		captchaResponse,
		privacyConsent,
		minorConsent,
	];

	for (let num = 1; num <= 12; num++) {
		const card = register(
			createMockElement(
				`guest-card-${num}`,
				"DIV",
				num === 1 ? [] : ["hidden"],
			),
		);
		const cardInputs: MockElement[] = [];

		const firstName = register(
			createMockElement(`guest-first-name-${num}`, "INPUT"),
		);
		const lastName = register(
			createMockElement(`guest-last-name-${num}`, "INPUT"),
		);
		const legacyName = register(
			createMockElement(`guest-name-${num}`, "INPUT"),
		);
		legacyName.type = "hidden";
		const phone = register(createMockElement(`guest-phone-${num}`, "INPUT"));
		const country = register(
			createMockElement(`guest-country-${num}`, "INPUT"),
		);
		const age = register(createMockElement(`guest-age-${num}`, "INPUT"));
		const docType = register(
			createMockElement(`guest-doc-type-${num}`, "SELECT"),
		);
		const docNum = register(createMockElement(`guest-doc-num-${num}`, "INPUT"));

		cardInputs.push(
			firstName,
			lastName,
			legacyName,
			phone,
			country,
			age,
			docType,
			docNum,
		);

		if (num === 1) {
			const email = register(createMockElement("guest-email-1", "INPUT"));
			email.value = "maria.garcia@example.com";
			firstName.value = "Maria";
			lastName.value = "Garcia";
			phone.value = "+573001112233";
			country.value = "Colombia";
			age.value = "34";
			docType.value = "CC";
			docNum.value = "1020304050";
			cardInputs.push(email);
		}

		card.querySelectorAll = () => cardInputs;
		allFormInputs.push(...cardInputs);
	}

	form.querySelectorAll = (selector: string) => {
		if (selector.includes("not([disabled])")) {
			return allFormInputs.filter(
				(input) =>
					!input.hasAttribute("disabled") &&
					!input.disabled &&
					input.type !== "hidden",
			);
		}
		return allFormInputs;
	};

	const defaultFetch: FetchHandler = async (url) => {
		if (url.includes("action=captcha")) {
			return {
				ok: true,
				status: 200,
				json: async () => ({ challenge: "3 + 4", signature: "sig_abc" }),
			};
		}
		if (url.includes("registry-lookup.php")) {
			return {
				ok: true,
				status: 200,
				json: async () => ({
					success: true,
					reservation: {
						property_id: "1707",
						check_in: "2027-01-10",
						check_out: "2027-01-15",
						guest_name: "Maria Garcia",
						max_guests: 6,
						registry_completed: false,
					},
				}),
			};
		}
		return {
			ok: true,
			status: 200,
			json: async () => ({
				success: true,
				reservation_code: "ovf_secret_res_901",
				guide_url: "../guide/?code=ovf_secret_res_901",
			}),
		};
	};

	const locationObj = {
		href,
		origin: parsedUrl.origin,
		pathname: parsedUrl.pathname,
		search: parsedUrl.search,
		hash: parsedUrl.hash,
	};

	const existingScript = {
		parentNode: {
			insertBefore: () => {},
		},
	};

	const win: Record<string, unknown> = {
		_paq: paq,
		location: locationObj,
		scrollTo: () => {},
		addEventListener: (evt: string) => {
			windowEvents.push(evt);
		},
	};

	class MockFormData {
		entries(): IterableIterator<[string, string]> {
			const pairs: Array<[string, string]> = [];
			for (const input of allFormInputs) {
				if (!input.disabled && !input.hasAttribute("disabled")) {
					pairs.push([input.name || input.id, input.value]);
				}
			}
			return pairs[Symbol.iterator]();
		}
		[Symbol.iterator]() {
			return this.entries();
		}
	}

	const sandbox = {
		window: win,
		location: locationObj,
		document: {
			documentElement: { lang },
			referrer,
			getElementById: (id: string) => elements.get(id) ?? null,
			createElement: (tag: string) =>
				tag === "script" ? { async: false, src: "" } : createMockElement(""),
			getElementsByTagName: (tag: string) =>
				tag === "script" ? [existingScript] : [],
			head: { appendChild: () => {} },
			addEventListener: (evt: string, fn: Listener) => {
				documentEvents.push(evt);
				const list = domListeners.get(evt) ?? [];
				list.push(fn);
				domListeners.set(evt, list);
			},
		},
		localStorage: {
			getItem: () => null,
			setItem: () => {},
		},
		sessionStorage: {
			getItem: () => null,
			setItem: () => {},
		},
		fetch: options.fetchHandler ?? defaultFetch,
		FormData: MockFormData,
		URL,
		URLSearchParams,
		Intl,
		setTimeout: (fn: () => void) => {
			fn();
			return 0;
		},
		console: {
			error: () => {},
			warn: () => {},
			log: () => {},
		},
	};

	vm.createContext(sandbox);
	vm.runInContext(
		fs.readFileSync(path.join(PUBLIC_DIR, "js", "page-config.js"), "utf-8"),
		sandbox,
	);
	if (options.loadAnalytics !== false) {
		vm.runInContext(
			fs.readFileSync(path.join(PUBLIC_DIR, "js", "analytics.js"), "utf-8"),
			sandbox,
		);
	}
	vm.runInContext(
		fs.readFileSync(
			options.registryScriptPath ?? path.join(PUBLIC_DIR, "js", "registry.js"),
			"utf-8",
		),
		sandbox,
	);

	for (const fn of domListeners.get("DOMContentLoaded") ?? []) {
		await fn({});
	}
	await flushMicrotasks();

	// Simulate the user answering the math CAPTCHA after loadCaptcha() initializes it
	captchaResponse.value = "7";

	return {
		paq,
		elements,
		form,
		windowEvents,
		documentEvents,
		getSnapshot: () => JSON.parse(JSON.stringify(paq)) as unknown[][],
		getEvents: () =>
			(JSON.parse(JSON.stringify(paq)) as unknown[][]).filter(
				(cmd) => cmd[0] === "trackEvent",
			),
		getGoals: () =>
			(JSON.parse(JSON.stringify(paq)) as unknown[][]).filter(
				(cmd) => cmd[0] === "trackGoal",
			),
	};
}

interface GuideVmOptions {
	href?: string;
	referrer?: string;
	lang?: string;
	loadAnalytics?: boolean;
	guideScriptPath?: string;
	fetchHandler?: FetchHandler;
}

async function createGuideHarness(options: GuideVmOptions = {}) {
	const lang = options.lang ?? "en";
	const href =
		options.href ??
		"https://www.oceanviewflats.com/guide/?code=ovf_secret_res_777&token=tok_secret_888&guest=Carlos%20Lopez&property=1606&check_in=2027-02-01&check_out=2027-02-05&mtm_source=mailer";
	const referrer =
		options.referrer ??
		"https://www.oceanviewflats.com/registry/?code=ovf_secret_res_777&guest=Carlos%20Lopez&check_in=2027-02-01&check_out=2027-02-05&utm_source=email#done";

	const parsedUrl = new URL(href);
	const paq: unknown[][] = [];
	const copiedValues: string[] = [];
	const windowEvents: string[] = [];
	const documentEvents: string[] = [];
	const domListeners = new Map<string, Listener[]>();

	const pageConfigPayload = {
		pageId: "guide",
		lang,
		assetPrefix: "../",
		apiBase: "../api/",
		analytics: {
			pageType: "guide",
			propertyId: "portfolio",
			enableEngagementTracking: false,
		},
		i18n: {
			guideWelcomeWithGuest: "Welcome, {guestName}!",
			guideWelcomeGeneric: "Welcome to OceanViewFlats!",
			guideCredentialsLocked: "Access Locked",
			guideCredentialsLockedDesc: "Complete the Guest Registry to unlock.",
			guideActionUnlock: "Complete Registry",
			guideCredentialsVerifying: "Verifying...",
			guideNoReservationFound: "No reservation found.",
			guideCopySuccess: "Copied!",
			guideConcluded: "This reservation has concluded.",
			guideConcludedBadge: "Stay Concluded",
			guideParkingPlaceholder: "--",
		},
	};

	const elements = new Map<string, MockElement>();
	const register = (el: MockElement) => {
		elements.set(el.id, el);
		return el;
	};

	const configEl = register(createMockElement("ovf-page-config", "SCRIPT"));
	configEl.textContent = JSON.stringify(pageConfigPayload);

	register(createMockElement("guide-greeting-box", "P"));
	register(createMockElement("display-apartment", "SPAN"));
	register(createMockElement("display-check-in", "SPAN"));
	register(createMockElement("display-check-out", "SPAN"));
	register(createMockElement("display-door-code", "SPAN"));
	register(createMockElement("display-wifi-ssid", "SPAN"));
	register(createMockElement("display-wifi-password", "SPAN"));
	register(createMockElement("display-parking-spot", "SPAN"));

	const btnCopyDoor = register(
		createMockElement("btn-copy-door-code", "BUTTON"),
	);
	btnCopyDoor.setAttribute("data-copy-target", "display-door-code");
	const btnCopyWifiSsid = register(
		createMockElement("btn-copy-wifi-ssid", "BUTTON"),
	);
	btnCopyWifiSsid.setAttribute("data-copy-target", "display-wifi-ssid");
	const btnCopyWifiPass = register(
		createMockElement("btn-copy-wifi-pass", "BUTTON"),
	);
	btnCopyWifiPass.setAttribute("data-copy-target", "display-wifi-password");
	const btnCopyParking = register(
		createMockElement("btn-copy-parking-spot", "BUTTON"),
	);
	btnCopyParking.setAttribute("data-copy-target", "display-parking-spot");

	register(createMockElement("guide-registry-banner", "SECTION"));
	register(createMockElement("registry-link", "A"));
	register(createMockElement("credential-locked-notice", "DIV", ["hidden"]));
	register(createMockElement("credential-locked-title", "SPAN"));
	register(createMockElement("credential-locked-desc", "P"));
	register(createMockElement("credential-locked-btn", "A"));
	register(createMockElement("wifi-locked-notice", "DIV", ["hidden"]));
	register(createMockElement("parking-locked-notice", "DIV", ["hidden"]));
	register(createMockElement("parking-card", "DIV"));
	register(createMockElement("copy-alert", "DIV"));
	register(createMockElement("copy-alert-text", "SPAN"));
	register(createMockElement("door-code-card", "DIV"));

	const defaultFetch: FetchHandler = async () => ({
		ok: true,
		status: 200,
		json: async () => ({
			verified: true,
			status: "verified",
			credentials: {
				door_code: "084729#",
				wifi_ssid: "OVF-Secret-WiFi-1606",
				wifi_password: "UltraSecretWifiPass!99",
				parking_spot: "412",
			},
			reservation: {
				guest_name: "Carlos Lopez",
				check_in: "2027-02-01",
				check_out: "2027-02-05",
				property_id: "1606",
			},
		}),
	});

	const locationObj = {
		href,
		origin: parsedUrl.origin,
		pathname: parsedUrl.pathname,
		search: parsedUrl.search,
		hash: parsedUrl.hash,
	};

	const existingScript = {
		parentNode: {
			insertBefore: () => {},
		},
	};

	const win: Record<string, unknown> = {
		_paq: paq,
		location: locationObj,
		addEventListener: (evt: string) => {
			windowEvents.push(evt);
		},
	};

	const sandbox = {
		window: win,
		location: locationObj,
		document: {
			documentElement: { lang },
			referrer,
			getElementById: (id: string) => elements.get(id) ?? null,
			createElement: (tag: string) =>
				tag === "script" ? { async: false, src: "" } : createMockElement(""),
			createElementNS: () => createMockElement("svg"),
			getElementsByTagName: (tag: string) =>
				tag === "script" ? [existingScript] : [],
			head: { appendChild: () => {} },
			addEventListener: (evt: string, fn: Listener) => {
				documentEvents.push(evt);
				const list = domListeners.get(evt) ?? [];
				list.push(fn);
				domListeners.set(evt, list);
			},
		},
		navigator: {
			clipboard: {
				writeText: async (text: string) => {
					copiedValues.push(text);
				},
			},
		},
		fetch: options.fetchHandler ?? defaultFetch,
		URL,
		URLSearchParams,
		Intl,
		setTimeout: () => 0,
		console: {
			error: () => {},
			warn: () => {},
			log: () => {},
		},
	};

	vm.createContext(sandbox);
	vm.runInContext(
		fs.readFileSync(path.join(PUBLIC_DIR, "js", "page-config.js"), "utf-8"),
		sandbox,
	);
	if (options.loadAnalytics !== false) {
		vm.runInContext(
			fs.readFileSync(path.join(PUBLIC_DIR, "js", "analytics.js"), "utf-8"),
			sandbox,
		);
	}
	vm.runInContext(
		fs.readFileSync(
			options.guideScriptPath ?? path.join(PUBLIC_DIR, "js", "guide.js"),
			"utf-8",
		),
		sandbox,
	);

	for (const fn of domListeners.get("DOMContentLoaded") ?? []) {
		await fn({});
	}
	await flushMicrotasks();

	return {
		paq,
		elements,
		copiedValues,
		windowEvents,
		documentEvents,
		getSnapshot: () => JSON.parse(JSON.stringify(paq)) as unknown[][],
		getEvents: () =>
			(JSON.parse(JSON.stringify(paq)) as unknown[][]).filter(
				(cmd) => cmd[0] === "trackEvent",
			),
		getGoals: () =>
			(JSON.parse(JSON.stringify(paq)) as unknown[][]).filter(
				(cmd) => cmd[0] === "trackGoal",
			),
	};
}

test("#346 compiled registry and guide pages disable engagement tracking across all 6 locales and strip sensitive URL/referrer params without heartbeat or scroll listeners", async () => {
	for (const pageDir of ["registry", "guide"] as const) {
		for (const lang of LANGUAGES) {
			const relPath =
				lang === "en" ? `${pageDir}/index.html` : `${pageDir}/${lang}.html`;
			const html = fs.readFileSync(path.join(DIST_DIR, relPath), "utf-8");
			const match = html.match(
				/<script id="ovf-page-config" type="application\/json">(.*?)<\/script>/,
			);
			assert.ok(match, `${relPath} must emit #ovf-page-config`);
			const parsed = JSON.parse(match[1]);

			assert.equal(parsed.analytics.pageType, pageDir);
			assert.equal(parsed.analytics.propertyId, "portfolio");
			assert.equal(
				parsed.analytics.enableEngagementTracking,
				false,
				`${relPath} must keep enableEngagementTracking strictly false`,
			);

			const analyticsIdx = html.indexOf("js/analytics.js?v=");
			const scriptIdx = html.indexOf(`js/${pageDir}.js?v=`);
			assert.ok(analyticsIdx > -1, `${relPath} must load analytics.js`);
			assert.ok(
				scriptIdx > analyticsIdx,
				`${relPath} must load ${pageDir}.js after analytics.js`,
			);
		}
	}

	// Runtime verification for both registry and guide sandboxes
	const regRun = await createRegistryHarness();
	const guideRun = await createGuideHarness();

	for (const [label, run] of [
		["registry", regRun],
		["guide", guideRun],
	] as const) {
		const snapshot = run.getSnapshot();
		assert.equal(
			snapshot.some((cmd) => cmd[0] === "enableHeartBeatTimer"),
			false,
			`${label} must never queue enableHeartBeatTimer`,
		);
		assert.equal(
			run.windowEvents.includes("scroll"),
			false,
			`${label} must never attach window scroll listeners`,
		);
		assert.equal(
			run.documentEvents.includes("scroll"),
			false,
			`${label} must never attach document scroll listeners`,
		);

		const customUrlCmd = snapshot.find((cmd) => cmd[0] === "setCustomUrl");
		const referrerCmd = snapshot.find((cmd) => cmd[0] === "setReferrerUrl");
		assert.ok(customUrlCmd && referrerCmd);
		const urlsCombined = `${String(customUrlCmd[1])} ${String(referrerCmd[1])}`;

		for (const forbidden of [
			"code=",
			"token=",
			"guest=",
			"check_in=",
			"check_out=",
			"ovf_secret",
			"tok_secret",
			"Maria",
			"Carlos",
		]) {
			assert.ok(
				!urlsCombined.includes(forbidden),
				`${label} sanitized URL/referrer must strip ${forbidden}: ${urlsCombined}`,
			);
		}
	}
});

for (const variant of [
	{
		label: "public/js/registry.js",
		file: path.join(PUBLIC_DIR, "js", "registry.js"),
	},
	{
		label: "dist/js/registry.js (minified)",
		file: path.join(DIST_DIR, "js", "registry.js"),
	},
]) {
	test(`#346 ${variant.label} fires Goal 7 (never Goal 5) on completion with normalized Property and numeric guest count as Event Value`, async () => {
		const harness = await createRegistryHarness({
			registryScriptPath: variant.file,
			// Omit property from URL to verify server-resolved hiddenProperty is normalized
			href: "https://www.oceanviewflats.com/registry/?code=ovf_secret_res_901",
		});

		// Add a second guest so currentGuestCount === 2
		const addBtn = harness.elements.get("add-guest-button");
		assert.ok(addBtn);
		await addBtn.dispatchEvent("click");

		const fill = (id: string, val: string) => {
			const el = harness.elements.get(id);
			assert.ok(el, `missing element ${id}`);
			el.value = val;
		};
		fill("guest-first-name-2", "Juan");
		fill("guest-last-name-2", "Perez");
		fill("guest-country-2", "Colombia");
		fill("guest-age-2", "30");
		fill("guest-doc-type-2", "CC");
		fill("guest-doc-num-2", "99887766");

		await harness.form.dispatchEvent("submit");
		await flushMicrotasks();

		const events = harness.getEvents();
		const goals = harness.getGoals();

		assert.deepEqual(events, [
			["trackEvent", "Guest Registry", "Submission Success", "1707", 2],
		]);
		assert.deepEqual(goals, [["trackGoal", 7]]);
		assert.equal(
			goals.some((cmd) => cmd[1] === 5),
			false,
			"Guest Registry completion must NEVER fire Matomo Goal 5",
		);

		// Verify zero PII or reservation code anywhere in _paq
		const serialized = JSON.stringify(harness.getSnapshot());
		for (const pii of [
			"ovf_secret_res_901",
			"Maria",
			"Garcia",
			"Juan",
			"Perez",
			"maria.garcia@example.com",
			"+573001112233",
			"1020304050",
			"99887766",
		]) {
			assert.ok(
				!serialized.includes(pii),
				`Registry completion telemetry must not leak ${pii}`,
			);
		}
	});
}

test("#346 Guest Registry normalizes 1606 and unknown property values to '1606' / 'portfolio'", async () => {
	const run1606 = await createRegistryHarness({
		href: "https://www.oceanviewflats.com/registry/?property=OceanViewFlats%201606%20secret",
	});
	await run1606.form.dispatchEvent("submit");
	await flushMicrotasks();
	assert.deepEqual(run1606.getEvents(), [
		["trackEvent", "Guest Registry", "Submission Success", "1606", 1],
	]);
	assert.deepEqual(run1606.getGoals(), [["trackGoal", 7]]);

	const runPortfolio = await createRegistryHarness({
		href: "https://www.oceanviewflats.com/registry/?property=ArbitraryUntrustedValue",
	});
	await runPortfolio.form.dispatchEvent("submit");
	await flushMicrotasks();
	assert.deepEqual(runPortfolio.getEvents(), [
		["trackEvent", "Guest Registry", "Submission Success", "portfolio", 1],
	]);
	assert.deepEqual(runPortfolio.getGoals(), [["trackGoal", 7]]);
});

test("#346 Guest Registry emits PII-free machine-coded Lookup Failure events for not_found, concluded, already_completed, and network_error", async () => {
	// 1. Concluded via URL check_out cutoff
	const urlConcluded = await createRegistryHarness({
		href: "https://www.oceanviewflats.com/registry/?code=ovf_secret_old&check_out=2020-01-01",
	});
	assert.deepEqual(urlConcluded.getEvents(), [
		["trackEvent", "Guest Registry", "Lookup Failure", "concluded"],
	]);

	// 2. Concluded via server response
	const serverConcluded = await createRegistryHarness({
		href: "https://www.oceanviewflats.com/registry/?code=ovf_secret_concluded",
		fetchHandler: async (url) => {
			if (url.includes("registry-lookup.php")) {
				return {
					ok: false,
					status: 403,
					json: async () => ({
						success: false,
						status: "concluded",
						message: "Esta reserva ha concluido para ovf_secret_concluded.",
					}),
				};
			}
			return {
				ok: true,
				status: 200,
				json: async () => ({ challenge: "1+1", signature: "s" }),
			};
		},
	});
	assert.deepEqual(serverConcluded.getEvents(), [
		["trackEvent", "Guest Registry", "Lookup Failure", "concluded"],
	]);
	assert.ok(
		!JSON.stringify(serverConcluded.getSnapshot()).includes(
			"ovf_secret_concluded",
		),
	);

	// 3. Already completed
	const alreadyCompleted = await createRegistryHarness({
		href: "https://www.oceanviewflats.com/registry/?code=ovf_secret_done",
		fetchHandler: async (url) => {
			if (url.includes("registry-lookup.php")) {
				return {
					ok: true,
					status: 200,
					json: async () => ({
						success: true,
						reservation: {
							property_id: "1707",
							check_in: "2027-01-10",
							check_out: "2027-01-15",
							guest_name: "Maria Garcia",
							registry_completed: true,
						},
					}),
				};
			}
			return {
				ok: true,
				status: 200,
				json: async () => ({ challenge: "1+1", signature: "s" }),
			};
		},
	});
	assert.deepEqual(alreadyCompleted.getEvents(), [
		["trackEvent", "Guest Registry", "Lookup Failure", "already_completed"],
	]);

	// 4. Not found (404 with localized message)
	const notFound = await createRegistryHarness({
		href: "https://www.oceanviewflats.com/registry/?code=ovf_secret_missing",
		lang: "es",
		fetchHandler: async (url) => {
			if (url.includes("registry-lookup.php")) {
				return {
					ok: false,
					status: 404,
					json: async () => ({
						success: false,
						status: "not_found",
						message: "No se encontró ninguna reserva con este código.",
					}),
				};
			}
			return {
				ok: true,
				status: 200,
				json: async () => ({ challenge: "1+1", signature: "s" }),
			};
		},
	});
	assert.deepEqual(notFound.getEvents(), [
		["trackEvent", "Guest Registry", "Lookup Failure", "not_found"],
	]);
	assert.ok(!JSON.stringify(notFound.getSnapshot()).includes("No se encontró"));

	// 5. Network error
	const networkError = await createRegistryHarness({
		href: "https://www.oceanviewflats.com/registry/?code=ovf_secret_net",
		fetchHandler: async (url) => {
			if (url.includes("registry-lookup.php")) {
				throw new Error("ECONNRESET ovf_secret_net");
			}
			return {
				ok: true,
				status: 200,
				json: async () => ({ challenge: "1+1", signature: "s" }),
			};
		},
	});
	assert.deepEqual(networkError.getEvents(), [
		["trackEvent", "Guest Registry", "Lookup Failure", "network_error"],
	]);
	assert.ok(
		!JSON.stringify(networkError.getSnapshot()).includes("ovf_secret_net"),
	);
});

test("#346 Guest Registry emits PII-free machine-coded Validation Error events for missing_required, email_or_phone (email, primary phone, companion phone), privacy_consent, and minor_guardian_consent", async () => {
	// 1. missing_required
	{
		const harness = await createRegistryHarness({
			href: "https://www.oceanviewflats.com/registry/?property=1707",
			lang: "es",
		});
		const fn = harness.elements.get("guest-first-name-1");
		assert.ok(fn);
		fn.value = "";
		await harness.form.dispatchEvent("submit");
		assert.deepEqual(harness.getEvents(), [
			["trackEvent", "Guest Registry", "Validation Error", "missing_required"],
		]);
		assert.deepEqual(harness.getGoals(), []);
	}

	// 2. email_or_phone — invalid primary email
	{
		const harness = await createRegistryHarness({
			href: "https://www.oceanviewflats.com/registry/?property=1707",
			lang: "es",
		});
		const email = harness.elements.get("guest-email-1");
		assert.ok(email);
		email.value = "invalid-email-pii";
		await harness.form.dispatchEvent("submit");
		assert.deepEqual(harness.getEvents(), [
			["trackEvent", "Guest Registry", "Validation Error", "email_or_phone"],
		]);
		assert.ok(
			!JSON.stringify(harness.getSnapshot()).includes("invalid-email-pii"),
		);
	}

	// 3. email_or_phone — invalid primary phone
	{
		const harness = await createRegistryHarness({
			href: "https://www.oceanviewflats.com/registry/?property=1707",
			lang: "fr",
		});
		const phone = harness.elements.get("guest-phone-1");
		assert.ok(phone);
		phone.value = "123";
		await harness.form.dispatchEvent("submit");
		assert.deepEqual(harness.getEvents(), [
			["trackEvent", "Guest Registry", "Validation Error", "email_or_phone"],
		]);
	}

	// 4. email_or_phone — invalid companion phone
	{
		const harness = await createRegistryHarness({
			href: "https://www.oceanviewflats.com/registry/?property=1707",
			lang: "de",
		});
		const addBtn = harness.elements.get("add-guest-button");
		assert.ok(addBtn);
		await addBtn.dispatchEvent("click");
		const setVal = (id: string, val: string) => {
			const el = harness.elements.get(id);
			assert.ok(el);
			el.value = val;
		};
		setVal("guest-first-name-2", "Lukas");
		setVal("guest-last-name-2", "Weber");
		setVal("guest-country-2", "Germany");
		setVal("guest-age-2", "29");
		setVal("guest-doc-type-2", "PA");
		setVal("guest-doc-num-2", "DE999888");
		setVal("guest-phone-2", "45");
		await harness.form.dispatchEvent("submit");
		assert.deepEqual(harness.getEvents(), [
			["trackEvent", "Guest Registry", "Validation Error", "email_or_phone"],
		]);
		assert.ok(!JSON.stringify(harness.getSnapshot()).includes("Lukas"));
	}

	// 5. privacy_consent
	{
		const harness = await createRegistryHarness({
			href: "https://www.oceanviewflats.com/registry/?property=1707",
			lang: "es",
		});
		const consent = harness.elements.get("privacy-consent");
		assert.ok(consent);
		consent.checked = false;
		await harness.form.dispatchEvent("submit");
		assert.deepEqual(harness.getEvents(), [
			["trackEvent", "Guest Registry", "Validation Error", "privacy_consent"],
		]);
	}

	// 6. minor_guardian_consent
	{
		const harness = await createRegistryHarness({
			href: "https://www.oceanviewflats.com/registry/?property=1707",
			lang: "it",
		});
		const addBtn = harness.elements.get("add-guest-button");
		assert.ok(addBtn);
		await addBtn.dispatchEvent("click");
		const setVal = (id: string, val: string) => {
			const el = harness.elements.get(id);
			assert.ok(el);
			el.value = val;
		};
		setVal("guest-first-name-2", "Sofia");
		setVal("guest-last-name-2", "Rossi");
		setVal("guest-country-2", "Italy");
		setVal("guest-age-2", "11");
		setVal("guest-doc-type-2", "TI");
		setVal("guest-doc-num-2", "TI123456");
		const minorCheckbox = harness.elements.get("minor-guardian-consent");
		assert.ok(minorCheckbox);
		minorCheckbox.checked = false;

		await harness.form.dispatchEvent("submit");
		assert.deepEqual(harness.getEvents(), [
			[
				"trackEvent",
				"Guest Registry",
				"Validation Error",
				"minor_guardian_consent",
			],
		]);
		assert.ok(!JSON.stringify(harness.getSnapshot()).includes("Sofia"));
	}
});

test("#346 Guest Registry emits only allowlisted English machine codes on Submission Failure and strips all localized text and PII", async () => {
	const cases: Array<{
		expectedCode: string;
		status?: number;
		body?: Record<string, unknown>;
		throwError?: boolean;
		forbiddenFragment: string;
	}> = [
		{
			expectedCode: "captcha_error",
			status: 400,
			body: {
				success: false,
				message:
					"Respuesta incorrecta a la pregunta de verificación de seguridad.",
			},
			forbiddenFragment: "Respuesta incorrecta",
		},
		{
			expectedCode: "captcha_error",
			status: 400,
			body: {
				success: false,
				error:
					"Security check failed. Please refresh the calendar section and try again.",
			},
			forbiddenFragment: "Security check failed",
		},
		{
			expectedCode: "validation_error",
			status: 400,
			body: {
				success: false,
				message:
					"El correo maria.garcia@example.com del huésped Maria Garcia es inválido.",
				errors: ["Invalid field for doc 1020304050"],
			},
			forbiddenFragment: "maria.garcia@example.com",
		},
		{
			expectedCode: "already_completed",
			status: 400,
			body: {
				success: false,
				message:
					"Guest registry has already been completed for this reservation.",
				errors: [
					"Guest registry has already been completed for this reservation.",
				],
			},
			forbiddenFragment: "already been completed for this",
		},
		{
			expectedCode: "concluded",
			status: 400,
			body: {
				success: false,
				message: "Cannot submit guest registry for a concluded reservation.",
				errors: ["Cannot submit guest registry for a concluded reservation."],
			},
			forbiddenFragment: "Cannot submit guest registry",
		},
		{
			expectedCode: "not_found",
			status: 400,
			body: {
				success: false,
				message:
					"No confirmed reservation matches the provided booking details.",
				errors: [
					"No confirmed reservation matches the provided booking details.",
				],
			},
			forbiddenFragment: "No confirmed reservation",
		},
		{
			expectedCode: "rate_limited",
			status: 429,
			body: {
				success: false,
				message:
					"Demasiadas solicitudes. Por favor espere unos minutos e intente de nuevo.",
			},
			forbiddenFragment: "Demasiadas solicitudes",
		},
		{
			expectedCode: "server_error",
			status: 500,
			body: {
				success: false,
				message: "Internal server error processing guest registration.",
			},
			forbiddenFragment: "Internal server error",
		},
		{
			expectedCode: "network_error",
			throwError: true,
			forbiddenFragment: "ECONNREFUSED",
		},
	];

	for (const tc of cases) {
		const harness = await createRegistryHarness({
			href: "https://www.oceanviewflats.com/registry/?property=1707",
			lang: "es",
			fetchHandler: async (url) => {
				if (url.includes("action=captcha")) {
					return {
						ok: true,
						status: 200,
						json: async () => ({ challenge: "2 + 5", signature: "sig" }),
					};
				}
				if (tc.throwError) {
					throw new Error("ECONNREFUSED secret_endpoint");
				}
				return {
					ok: false,
					status: tc.status ?? 400,
					json: async () => tc.body ?? { success: false },
				};
			},
		});

		await harness.form.dispatchEvent("submit");
		await flushMicrotasks();

		assert.deepEqual(harness.getEvents(), [
			["trackEvent", "Guest Registry", "Submission Failure", tc.expectedCode],
		]);
		assert.deepEqual(harness.getGoals(), []);

		const serialized = JSON.stringify(harness.getSnapshot());
		assert.ok(
			!serialized.includes(tc.forbiddenFragment),
			`Submission Failure (${tc.expectedCode}) must not emit raw localized/PII text: ${tc.forbiddenFragment}`,
		);
	}
});

for (const variant of [
	{
		label: "public/js/guide.js",
		file: path.join(PUBLIC_DIR, "js", "guide.js"),
	},
	{
		label: "dist/js/guide.js (minified)",
		file: path.join(DIST_DIR, "js", "guide.js"),
	},
]) {
	test(`#346 ${variant.label} emits Access State 'unlocked', all 4 Credential Copied codes without leaking secret values, and Unlock Registry Click placement labels`, async () => {
		const harness = await createGuideHarness({
			guideScriptPath: variant.file,
		});

		assert.deepEqual(harness.getEvents(), [
			["trackEvent", "Guest Guide", "Access State", "unlocked"],
		]);

		// Click all 4 credential copy buttons
		for (const btnId of [
			"btn-copy-door-code",
			"btn-copy-wifi-ssid",
			"btn-copy-wifi-pass",
			"btn-copy-parking-spot",
		]) {
			const btn = harness.elements.get(btnId);
			assert.ok(btn, `missing ${btnId}`);
			await btn.dispatchEvent("click");
		}
		await flushMicrotasks();

		// Click both unlock registry CTAs
		const bannerLink = harness.elements.get("registry-link");
		const cardBtn = harness.elements.get("credential-locked-btn");
		assert.ok(bannerLink && cardBtn);
		await bannerLink.dispatchEvent("click");
		await cardBtn.dispatchEvent("click");

		assert.deepEqual(harness.getEvents(), [
			["trackEvent", "Guest Guide", "Access State", "unlocked"],
			["trackEvent", "Guest Guide", "Credential Copied", "door_code"],
			["trackEvent", "Guest Guide", "Credential Copied", "wifi_ssid"],
			["trackEvent", "Guest Guide", "Credential Copied", "wifi_password"],
			["trackEvent", "Guest Guide", "Credential Copied", "parking_spot"],
			["trackEvent", "Guest Guide", "Unlock Registry Click", "banner"],
			["trackEvent", "Guest Guide", "Unlock Registry Click", "credential_card"],
		]);

		// Clipboard actually copied the real credentials
		assert.deepEqual(harness.copiedValues, [
			"084729#",
			"OVF-Secret-WiFi-1606",
			"UltraSecretWifiPass!99",
			"#412",
		]);

		// Zero secrets, reservation codes, URLs, or guest PII in _paq
		const serialized = JSON.stringify(harness.getSnapshot());
		for (const secret of [
			"084729",
			"OVF-Secret-WiFi-1606",
			"UltraSecretWifiPass!99",
			"412",
			"ovf_secret_res_777",
			"tok_secret_888",
			"Carlos",
			"Lopez",
		]) {
			assert.ok(
				!serialized.includes(secret),
				`Guest Guide telemetry must NEVER leak ${secret}`,
			);
		}
	});
}

test("#346 Guest Guide emits PII-free Access State events for registry_required, concluded (URL & server), not_found (missing code & 404/403), and network_error, and ignores disabled copy buttons", async () => {
	// 1. registry_required + unlock CTA clicks + disabled copy button ignored
	{
		const harness = await createGuideHarness({
			fetchHandler: async () => ({
				ok: true,
				status: 200,
				json: async () => ({
					verified: false,
					status: "registry_required",
					registry_url: "registry/?code=ovf_secret_res_777",
					message: "Debe completar el registro primero.",
				}),
			}),
		});

		// Attempt to click locked door code copy button -> must NOT emit Credential Copied
		const doorCopyBtn = harness.elements.get("btn-copy-door-code");
		assert.ok(doorCopyBtn);
		await doorCopyBtn.dispatchEvent("click");
		await flushMicrotasks();

		const bannerLink = harness.elements.get("registry-link");
		const cardBtn = harness.elements.get("credential-locked-btn");
		assert.ok(bannerLink && cardBtn);
		await bannerLink.dispatchEvent("click");
		await cardBtn.dispatchEvent("click");

		assert.deepEqual(harness.getEvents(), [
			["trackEvent", "Guest Guide", "Access State", "registry_required"],
			["trackEvent", "Guest Guide", "Unlock Registry Click", "banner"],
			["trackEvent", "Guest Guide", "Unlock Registry Click", "credential_card"],
		]);
		assert.equal(harness.copiedValues.length, 0);
		assert.ok(
			!JSON.stringify(harness.getSnapshot()).includes("ovf_secret_res_777"),
		);
	}

	// 2. concluded via URL check_out cutoff
	{
		const harness = await createGuideHarness({
			href: "https://www.oceanviewflats.com/guide/?code=ovf_old&check_out=2020-01-01",
		});
		assert.deepEqual(harness.getEvents(), [
			["trackEvent", "Guest Guide", "Access State", "concluded"],
		]);
	}

	// 3. concluded via server 403
	{
		const harness = await createGuideHarness({
			fetchHandler: async () => ({
				ok: false,
				status: 403,
				json: async () => ({
					verified: false,
					status: "concluded",
					message: "Estancia finalizada.",
				}),
			}),
		});
		assert.deepEqual(harness.getEvents(), [
			["trackEvent", "Guest Guide", "Access State", "concluded"],
		]);
	}

	// 4. not_found via missing code
	{
		const harness = await createGuideHarness({
			href: "https://www.oceanviewflats.com/guide/",
		});
		assert.deepEqual(harness.getEvents(), [
			["trackEvent", "Guest Guide", "Access State", "not_found"],
		]);
	}

	// 5. not_found via server 404 / 403 unauthorized
	{
		const harness = await createGuideHarness({
			fetchHandler: async () => ({
				ok: false,
				status: 404,
				json: async () => ({
					verified: false,
					status: "not_found",
					message: "Reserva no encontrada.",
				}),
			}),
		});
		assert.deepEqual(harness.getEvents(), [
			["trackEvent", "Guest Guide", "Access State", "not_found"],
		]);
	}

	// 6. network_error via rejected fetch
	{
		const harness = await createGuideHarness({
			fetchHandler: async () => {
				throw new Error("Network offline ovf_secret_res_777");
			},
		});
		assert.deepEqual(harness.getEvents(), [
			["trackEvent", "Guest Guide", "Access State", "network_error"],
		]);
		assert.ok(
			!JSON.stringify(harness.getSnapshot()).includes("ovf_secret_res_777"),
		);
	}
});

test("#346 registry.js and guide.js fail safely when window.ovfAnalytics is absent in isolated unit tests", async () => {
	const regRun = await createRegistryHarness({
		loadAnalytics: false,
		href: "https://www.oceanviewflats.com/registry/?property=1707",
	});
	await assert.doesNotReject(async () => {
		await regRun.form.dispatchEvent("submit");
		await flushMicrotasks();
	});
	assert.equal(regRun.getSnapshot().length, 0);

	const guideRun = await createGuideHarness({
		loadAnalytics: false,
	});
	const doorCopyBtn = guideRun.elements.get("btn-copy-door-code");
	const bannerLink = guideRun.elements.get("registry-link");
	assert.ok(doorCopyBtn && bannerLink);
	await assert.doesNotReject(async () => {
		await doorCopyBtn.dispatchEvent("click");
		await bannerLink.dispatchEvent("click");
		await flushMicrotasks();
	});
	assert.equal(guideRun.getSnapshot().length, 0);
});
