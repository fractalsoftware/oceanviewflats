import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import test from "node:test";
import vm from "node:vm";
import { LANGUAGES } from "../../src/config/pages";

const DIST_DIR = path.join(process.cwd(), "dist");
const PUBLIC_DIR = path.join(process.cwd(), "public");

type EventListenerFn = (event?: Record<string, unknown>) => unknown;

interface MockNode {
	id: string;
	tagName: string;
	value: string;
	checked: boolean;
	disabled: boolean;
	textContent: string;
	innerHTML: string;
	className: string;
	style: Record<string, string>;
	children: MockNode[];
	classList: {
		add: (...tokens: string[]) => void;
		remove: (...tokens: string[]) => void;
		contains: (token: string) => boolean;
	};
	setAttribute: (name: string, val: string) => void;
	getAttribute: (name: string) => string | null;
	removeAttribute: (name: string) => void;
	addEventListener: (evt: string, fn: EventListenerFn) => void;
	dispatchEvent: (evt: string, payload?: Record<string, unknown>) => unknown;
	appendChild: (child: MockNode) => MockNode;
	querySelectorAll: (selector: string) => MockNode[];
	closest: (selector: string) => MockNode | null;
}

function createMockNode(
	id: string,
	tagName = "DIV",
	attrs: Record<string, string> = {},
	parent: MockNode | null = null,
): MockNode {
	const attrMap = new Map<string, string>(Object.entries(attrs));
	const classes = new Set<string>();
	const listeners = new Map<string, EventListenerFn[]>();
	const children: MockNode[] = [];

	const node: MockNode = {
		id,
		tagName,
		value: attrs.value ?? "",
		checked: false,
		disabled: false,
		textContent: "",
		innerHTML: "",
		className: "",
		style: {},
		children,
		classList: {
			add: (...tokens: string[]) => {
				for (const tok of tokens) classes.add(tok);
			},
			remove: (...tokens: string[]) => {
				for (const tok of tokens) classes.delete(tok);
			},
			contains: (token: string) => classes.has(token),
		},
		setAttribute: (name: string, val: string) => {
			attrMap.set(name, String(val));
		},
		getAttribute: (name: string) => attrMap.get(name) ?? null,
		removeAttribute: (name: string) => {
			attrMap.delete(name);
		},
		addEventListener: (evt: string, fn: EventListenerFn) => {
			const list = listeners.get(evt) ?? [];
			list.push(fn);
			listeners.set(evt, list);
		},
		dispatchEvent: (evt: string, payload: Record<string, unknown> = {}) => {
			const list = listeners.get(evt) ?? [];
			let last: unknown;
			for (const fn of list) {
				last = fn({
					preventDefault: () => {},
					stopPropagation: () => {},
					target: node,
					...payload,
				});
			}
			return last;
		},
		appendChild: (child: MockNode) => {
			children.push(child);
			return child;
		},
		querySelectorAll: () => children,
		closest: (selector: string) => {
			const matchAttr = selector.match(/^\[([a-z0-9-]+)\]$/i);
			if (matchAttr) {
				const attrName = matchAttr[1];
				if (attrMap.has(attrName)) return node;
				return parent ? parent.closest(selector) : null;
			}
			return null;
		},
	};
	return node;
}

interface BrickConfig {
	initialization?: Record<string, unknown>;
	callbacks?: {
		onReady?: () => void;
		onSubmit?: (args: {
			selectedPaymentMethod: string;
			formData: Record<string, unknown>;
		}) => Promise<unknown>;
		onError?: (err: unknown) => void;
	};
}

interface FunnelHarnessOptions {
	propertyId?: "1707" | "1606";
	lang?: "en" | "es" | "fr" | "it" | "de" | "ja";
	useDistMain?: boolean;
	loadAnalytics?: boolean;
	withMercadoPago?: boolean;
	failSdkLoad?: boolean;
	brickCreateError?: unknown;
	guestName?: string;
	guestEmail?: string;
	guestPhone?: string;
	captchaValue?: string;
	privacyChecked?: boolean;
	bookRequestHandler?: () => Promise<{
		ok: boolean;
		status?: number;
		json: () => Promise<Record<string, unknown>>;
	}>;
	paymentHandler?: (body: Record<string, unknown>) => Promise<{
		ok: boolean;
		status?: number;
		json: () => Promise<Record<string, unknown>>;
	}>;
}

async function createFunnelHarness(options: FunnelHarnessOptions = {}) {
	const propertyId = options.propertyId ?? "1707";
	const lang = options.lang ?? "en";
	const loadAnalytics = options.loadAnalytics ?? true;
	const withMercadoPago = options.withMercadoPago ?? true;

	const pageConfigScript = fs.readFileSync(
		path.join(PUBLIC_DIR, "js", "page-config.js"),
		"utf-8",
	);
	const analyticsScript = fs.readFileSync(
		path.join(PUBLIC_DIR, "js", "analytics.js"),
		"utf-8",
	);
	const mainScript = fs.readFileSync(
		options.useDistMain
			? path.join(DIST_DIR, "js", "main.js")
			: path.join(PUBLIC_DIR, "js", "main.js"),
		"utf-8",
	);

	const pageConfigObj = {
		pageId: `Oceanview${propertyId}`,
		lang,
		assetPrefix: "../",
		apiBase: "../api/",
		analytics: {
			pageType: "property",
			propertyId,
			enableEngagementTracking: true,
		},
		i18n: {},
	};

	const calendarWidget = createMockNode("calendar-widget", "DIV", {
		"data-lang": lang,
		"data-month-names": JSON.stringify({ [lang]: Array(12).fill("Month") }),
		"data-day-names": JSON.stringify({ [lang]: Array(7).fill("D") }),
	});
	const directBookingForm = createMockNode("direct-booking-form", "FORM", {
		"data-mp-public-key": "TEST-MP-KEY",
	});
	const guestNameInput = createMockNode("booking-guest-name", "INPUT", {
		value: options.guestName ?? "Maria Gomez",
	});
	const guestEmailInput = createMockNode("booking-guest-email", "INPUT", {
		value: options.guestEmail ?? "maria.gomez@example.com",
	});
	const guestPhoneInput = createMockNode("booking-guest-phone", "INPUT", {
		value: options.guestPhone ?? "+573001112233",
	});
	const captchaResponseInput = createMockNode(
		"booking-captcha-response",
		"INPUT",
		{
			value: options.captchaValue ?? "9",
		},
	);
	const privacyConsentInput = createMockNode(
		"booking-privacy-consent",
		"INPUT",
	);
	privacyConsentInput.checked = options.privacyChecked ?? true;

	const formCheckIn = createMockNode("form-check-in-date", "INPUT", {
		value: "2027-02-10",
	});
	const formCheckOut = createMockNode("form-check-out-date", "INPUT", {
		value: "2027-02-14",
	});
	const formTotalPriceCop = createMockNode("form-total-price-cop", "INPUT", {
		value: "1800000",
	});
	const btnBook = createMockNode("btn-book", "A", {
		"data-airbnb-url": `https://www.airbnb.com/rooms/${propertyId}`,
		"data-text-ready": "Ready",
		"data-text-default": "Default",
		"aria-disabled": "true",
	});
	const msgBox = createMockNode("booking-form-message", "DIV");
	const btnSubmit = createMockNode("btn-direct-submit", "BUTTON");
	const btnSubmitText = createMockNode("btn-direct-submit-text", "SPAN");
	const calendarGrid = createMockNode("calendar-grid", "DIV");
	const btnNextMonth = createMockNode("btn-next-month", "BUTTON");

	const nodesById = new Map<string, MockNode>([
		[
			"ovf-page-config",
			Object.assign(createMockNode("ovf-page-config", "SCRIPT"), {
				textContent: JSON.stringify(pageConfigObj),
			}),
		],
		["calendar-widget", calendarWidget],
		["btn-prev-month", createMockNode("btn-prev-month", "BUTTON")],
		["btn-next-month", btnNextMonth],
		["month-label", createMockNode("month-label", "SPAN")],
		["calendar-grid", calendarGrid],
		["check-in-display", createMockNode("check-in-display", "DIV")],
		["check-out-display", createMockNode("check-out-display", "DIV")],
		["btn-book", btnBook],
		["btn-book-text", createMockNode("btn-book-text", "SPAN")],
		["direct-booking-form", directBookingForm],
		["booking-guest-name", guestNameInput],
		["booking-guest-email", guestEmailInput],
		["booking-guest-phone", guestPhoneInput],
		["booking-captcha-response", captchaResponseInput],
		["booking-privacy-consent", privacyConsentInput],
		["form-check-in-date", formCheckIn],
		["form-check-out-date", formCheckOut],
		["form-total-price-cop", formTotalPriceCop],
		["booking-form-message", msgBox],
		["btn-direct-submit", btnSubmit],
		["btn-direct-submit-text", btnSubmitText],
		[
			"payment-brick-container",
			createMockNode("payment-brick-container", "DIV"),
		],
	]);

	let capturedBrickConfig: BrickConfig | null = null;
	const docListeners: Record<string, EventListenerFn[]> = {};
	const paq: unknown[][] = [];
	const sessionStore = new Map<string, string>();
	const scriptStub = {
		async: false,
		src: "",
		onload: null as null | (() => void),
		onerror: null as null | (() => void),
		parentNode: { insertBefore: () => {} },
	};

	const locationObj = {
		href: `https://www.oceanviewflats.com/Oceanview${propertyId}/${lang === "en" ? "" : `${lang}.html`}`,
		origin: "https://www.oceanviewflats.com",
		pathname: `/Oceanview${propertyId}/${lang === "en" ? "index.html" : `${lang}.html`}`,
		search: "",
		hash: "",
	};

	const win: Record<string, unknown> = {
		_paq: paq,
		scrollY: 0,
		location: locationObj,
		addEventListener: () => {},
	};

	if (withMercadoPago) {
		win.MercadoPago = function mockMercadoPago() {
			return {
				bricks: () => ({
					create: async (
						_type: string,
						_container: string,
						cfg: BrickConfig,
					) => {
						if (options.brickCreateError) {
							throw options.brickCreateError;
						}
						capturedBrickConfig = cfg;
						return { unmount: async () => {} };
					},
				}),
			};
		};
	}

	const sandbox = {
		window: win,
		location: locationObj,
		document: {
			documentElement: { lang },
			referrer: "",
			head: {
				appendChild: (node: {
					src?: string;
					onerror?: (() => void) | null;
					onload?: (() => void) | null;
				}) => {
					if (
						options.failSdkLoad &&
						node.src === "https://sdk.mercadopago.com/js/v2"
					) {
						queueMicrotask(() => {
							if (typeof node.onerror === "function") node.onerror();
						});
					}
				},
			},
			createElement: (tag: string) =>
				tag.toLowerCase() === "script"
					? { ...scriptStub }
					: createMockNode("", tag.toUpperCase()),
			getElementsByTagName: (tag: string) =>
				tag === "script" ? [scriptStub] : [],
			querySelector: () => null,
			querySelectorAll: () => [],
			getElementById: (id: string) => nodesById.get(id) ?? null,
			addEventListener: (evt: string, fn: EventListenerFn) => {
				docListeners[evt] = docListeners[evt] ?? [];
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
		fetch: async (url: string, init?: { body?: string }) => {
			if (url.includes("book-request.php")) {
				if (options.bookRequestHandler) {
					return options.bookRequestHandler();
				}
				return {
					ok: true,
					status: 200,
					json: async () => ({
						success: true,
						reservation_uid: `ovf_${propertyId}_hold`,
						total_price: 1950000,
						payment_token: "tok_hold_1",
					}),
				};
			}
			if (url.includes("payment.php")) {
				const parsedBody = init?.body
					? (JSON.parse(init.body) as Record<string, unknown>)
					: {};
				if (options.paymentHandler) {
					return options.paymentHandler(parsedBody);
				}
				return {
					ok: true,
					status: 200,
					json: async () => ({
						success: true,
						status: "approved",
						payment_id: "mp_12345",
						reservation_code: `ovf_${propertyId}_hold`,
					}),
				};
			}
			if (url.includes("quote.php")) {
				return {
					ok: true,
					status: 200,
					json: async () => ({
						success: true,
						data: {
							nights_count: 4,
							accommodation_total_cop: 1750000,
							cleaning_fee_cop: 200000,
							total_cop: 1950000,
							minimum_stay_required: 2,
							is_valid: true,
						},
					}),
				};
			}
			return { ok: true, status: 200, json: async () => [] };
		},
		Intl,
		Date,
		URL,
		URLSearchParams,
		FormData: class {},
		console: { log: () => {}, warn: () => {}, error: () => {} },
	};

	vm.createContext(sandbox);
	vm.runInContext(pageConfigScript, sandbox);
	if (loadAnalytics) {
		vm.runInContext(analyticsScript, sandbox);
	}
	vm.runInContext(mainScript, sandbox);

	for (const fn of docListeners.DOMContentLoaded ?? []) {
		fn();
	}
	await new Promise((r) => setTimeout(r, 15));

	const selectValidDates = async () => {
		btnNextMonth.dispatchEvent("click");
		const enabledDays = calendarGrid
			.querySelectorAll("*")
			.filter((n) => !n.disabled && Boolean(n.getAttribute("data-date")));
		assert.ok(enabledDays.length >= 6, "Expected enabled calendar days");
		enabledDays[enabledDays.length - 6].dispatchEvent("click");
		enabledDays[enabledDays.length - 2].dispatchEvent("click");
		await new Promise((r) => setTimeout(r, 15));
	};

	const submitHoldForm = async () => {
		directBookingForm.dispatchEvent("submit");
		await new Promise((r) => setTimeout(r, 25));
	};

	const dispatchDocumentClick = (target: MockNode) => {
		for (const fn of docListeners.click ?? []) {
			fn({ target, preventDefault: () => {}, stopPropagation: () => {} });
		}
	};

	return {
		paq,
		getSnapshot: () => JSON.parse(JSON.stringify(paq)) as unknown[][],
		clearPaq: () => {
			paq.length = 0;
		},
		window: sandbox.window,
		location: locationObj,
		nodesById,
		btnBook,
		guestNameInput,
		guestEmailInput,
		guestPhoneInput,
		captchaResponseInput,
		privacyConsentInput,
		selectValidDates,
		submitHoldForm,
		dispatchDocumentClick,
		getBrickConfig: () => capturedBrickConfig,
	};
}

test("#343 SSG HTML emits semantic WhatsApp and Airbnb review-verification data attributes across all 6 locales", () => {
	for (const lang of LANGUAGES) {
		const homeFile = lang === "en" ? "index.html" : `${lang}.html`;
		const homeHtml = fs.readFileSync(path.join(DIST_DIR, homeFile), "utf-8");

		assert.match(
			homeHtml,
			/<a[^>]*id="home-host-whatsapp"[^>]*data-analytics-whatsapp="home_host"|<a[^>]*data-analytics-whatsapp="home_host"[^>]*id="home-host-whatsapp"/,
			`${homeFile} #home-host-whatsapp must emit data-analytics-whatsapp="home_host"`,
		);
		assert.match(
			homeHtml,
			/<a[^>]*id="home-cta-whatsapp"[^>]*data-analytics-whatsapp="home_cta"|<a[^>]*data-analytics-whatsapp="home_cta"[^>]*id="home-cta-whatsapp"/,
			`${homeFile} #home-cta-whatsapp must emit data-analytics-whatsapp="home_cta"`,
		);
		assert.ok(
			homeHtml.includes('data-analytics-airbnb-review="home_card_1707"'),
			`${homeFile} must tag Property 1707 Airbnb review link`,
		);
		assert.ok(
			homeHtml.includes('data-analytics-airbnb-review="home_card_1606"'),
			`${homeFile} must tag Property 1606 Airbnb review link`,
		);

		for (const propId of ["1707", "1606"] as const) {
			const propFile =
				lang === "en"
					? `Oceanview${propId}/index.html`
					: `Oceanview${propId}/${lang}.html`;
			const propHtml = fs.readFileSync(path.join(DIST_DIR, propFile), "utf-8");

			assert.match(
				propHtml,
				/<a[^>]*id="whatsapp-booking-link"[^>]*data-analytics-whatsapp="property_booking"|<a[^>]*data-analytics-whatsapp="property_booking"[^>]*id="whatsapp-booking-link"/,
				`${propFile} #whatsapp-booking-link must emit data-analytics-whatsapp="property_booking"`,
			);
			assert.ok(
				propHtml.includes('data-analytics-whatsapp="property_host"'),
				`${propFile} host card WhatsApp link must emit data-analytics-whatsapp="property_host"`,
			);
			assert.ok(
				propHtml.includes('data-analytics-airbnb-review="property_reviews"'),
				`${propFile} reviews Airbnb attribution link must emit data-analytics-airbnb-review="property_reviews"`,
			);
			const btnBookMatch = propHtml.match(/<a[^>]*id="btn-book"[^>]*>/);
			assert.ok(btnBookMatch, `${propFile} must render #btn-book`);
			assert.ok(
				!btnBookMatch[0].includes("data-analytics-airbnb-review"),
				`${propFile} #btn-book must remain Goal 3 booking CTA, not a review verification link`,
			);
		}

		const contactFile =
			lang === "en" ? "contact/index.html" : `contact/${lang}.html`;
		const contactHtml = fs.readFileSync(
			path.join(DIST_DIR, contactFile),
			"utf-8",
		);
		assert.match(
			contactHtml,
			/<a[^>]*id="whatsapp-contact-link"[^>]*data-analytics-whatsapp="contact_sidebar"|<a[^>]*data-analytics-whatsapp="contact_sidebar"[^>]*id="whatsapp-contact-link"/,
			`${contactFile} #whatsapp-contact-link must emit data-analytics-whatsapp="contact_sidebar"`,
		);
	}
});

test("#343 Pending Reservation hold creation fires Goal 6, advances Funnel Stage to hold_created, and emits per-Property event", async () => {
	for (const propertyId of ["1707", "1606"] as const) {
		const harness = await createFunnelHarness({
			propertyId,
			useDistMain: propertyId === "1606",
		});
		await harness.selectValidDates();
		harness.clearPaq();

		await harness.submitHoldForm();
		const commands = harness.getSnapshot();

		assert.deepEqual(
			commands.find((c) => c[0] === "setCustomDimension" && c[1] === 5),
			["setCustomDimension", 5, "hold_created"],
			`Property ${propertyId} hold creation must advance Funnel Stage to hold_created`,
		);
		assert.deepEqual(
			commands.find(
				(c) =>
					c[0] === "trackEvent" &&
					c[1] === "Booking Funnel" &&
					c[2] === "Pending Reservation Created",
			),
			[
				"trackEvent",
				"Booking Funnel",
				"Pending Reservation Created",
				propertyId,
			],
		);
		assert.deepEqual(
			commands.find((c) => c[0] === "trackGoal"),
			["trackGoal", 6],
			`Property ${propertyId} hold creation must fire Goal 6 without revenue`,
		);
		assert.equal(
			commands.some((c) => c[0] === "trackGoal" && c[1] === 5),
			false,
			"Hold creation must never fire Goal 5",
		);
	}
});

test("#343 Hold creation failure paths emit locale-independent English machine codes with zero PII or localized messages", async () => {
	// 1. Missing required fields -> validation_error
	{
		const harness = await createFunnelHarness({ guestName: "" });
		await harness.selectValidDates();
		harness.clearPaq();
		await harness.submitHoldForm();
		const cmds = harness.getSnapshot();
		assert.deepEqual(cmds, [
			["trackEvent", "Booking Funnel", "Hold Failed", "validation_error"],
		]);
	}

	// 2. Missing Personal Data Authorization -> privacy_consent
	{
		const harness = await createFunnelHarness({ privacyChecked: false });
		await harness.selectValidDates();
		harness.clearPaq();
		await harness.submitHoldForm();
		const cmds = harness.getSnapshot();
		assert.deepEqual(cmds, [
			["trackEvent", "Booking Funnel", "Hold Failed", "privacy_consent"],
		]);
	}

	// 3. Localized Spanish CAPTCHA error -> captcha_error (no localized string / PII)
	{
		const harness = await createFunnelHarness({
			lang: "es",
			bookRequestHandler: async () => ({
				ok: false,
				status: 400,
				json: async () => ({
					success: false,
					error:
						"Respuesta incorrecta a la pregunta de verificación de seguridad.",
				}),
			}),
		});
		await harness.selectValidDates();
		harness.clearPaq();
		await harness.submitHoldForm();
		const cmds = harness.getSnapshot();
		assert.deepEqual(cmds, [
			["trackEvent", "Booking Funnel", "Hold Failed", "captcha_error"],
		]);
		assert.equal(JSON.stringify(cmds).includes("Respuesta"), false);
	}

	// 4. Localized Airbnb overlap with date -> date_conflict (no raw date or localized string)
	{
		const harness = await createFunnelHarness({
			lang: "es",
			bookRequestHandler: async () => ({
				ok: true,
				status: 200,
				json: async () => ({
					success: false,
					error:
						"Las fechas seleccionadas coinciden con una reserva de Airbnb existente (2027-02-11). Por favor, elija otras fechas.",
				}),
			}),
		});
		await harness.selectValidDates();
		harness.clearPaq();
		await harness.submitHoldForm();
		const cmds = harness.getSnapshot();
		assert.deepEqual(cmds, [
			["trackEvent", "Booking Funnel", "Hold Failed", "date_conflict"],
		]);
		assert.equal(JSON.stringify(cmds).includes("2027-02-11"), false);
	}

	// 5. Structured server error_code -> normalized English code
	{
		const harness = await createFunnelHarness({
			bookRequestHandler: async () => ({
				ok: false,
				status: 422,
				json: async () => ({
					success: false,
					error_code: "MINIMUM_STAY_VIOLATED",
					error: "Minimum stay is 3 nights for Maria Gomez",
				}),
			}),
		});
		await harness.selectValidDates();
		harness.clearPaq();
		await harness.submitHoldForm();
		const cmds = harness.getSnapshot();
		assert.deepEqual(cmds, [
			["trackEvent", "Booking Funnel", "Hold Failed", "min_stay_error"],
		]);
		assert.equal(JSON.stringify(cmds).includes("Maria"), false);
	}

	// 6. Network error -> network_error (never raw exception message)
	{
		const harness = await createFunnelHarness({
			bookRequestHandler: async () => {
				throw new Error("ECONNRESET for maria.gomez@example.com");
			},
		});
		await harness.selectValidDates();
		harness.clearPaq();
		await harness.submitHoldForm();
		const cmds = harness.getSnapshot();
		assert.deepEqual(cmds, [
			["trackEvent", "Booking Funnel", "Hold Failed", "network_error"],
		]);
		assert.equal(JSON.stringify(cmds).includes("maria.gomez"), false);
	}
});

test("#343 Mercado Pago Payment Brick lifecycle tracks SDK load error, onReady, onError, and create failure cleanly", async () => {
	// 1. SDK load failure
	{
		const harness = await createFunnelHarness({
			withMercadoPago: false,
			failSdkLoad: true,
		});
		await harness.selectValidDates();
		harness.clearPaq();
		await harness.submitHoldForm();
		const cmds = harness.getSnapshot();

		assert.ok(
			cmds.some(
				(c) =>
					c[0] === "trackEvent" &&
					c[1] === "Booking Funnel" &&
					c[2] === "Payment Brick Error" &&
					c[3] === "sdk_load_error",
			),
			"SDK load failure must emit sdk_load_error",
		);
		assert.equal(
			cmds.some(
				(c) => c[0] === "setCustomDimension" && c[2] === "payment_brick_ready",
			),
			false,
		);
		assert.equal(
			cmds.some((c) => c[0] === "trackEvent" && c[2] === "Hold Failed"),
			false,
			"SDK failure after hold creation must not misreport Hold Failed",
		);
	}

	// 2. Brick onReady & onError (machine code vs fallback)
	{
		const harness = await createFunnelHarness({ propertyId: "1707" });
		await harness.selectValidDates();
		await harness.submitHoldForm();
		const brickCfg = harness.getBrickConfig();
		assert.ok(brickCfg?.callbacks?.onReady && brickCfg?.callbacks?.onError);

		harness.clearPaq();
		brickCfg.callbacks.onReady();
		brickCfg.callbacks.onError({
			type: "critical",
			cause: "fields_setup_failed",
			message: "Raw localized error with maria.gomez@example.com",
		});
		brickCfg.callbacks.onError({
			message: "Unexpected failure for Maria Gomez",
		});

		const cmds = harness.getSnapshot();
		assert.deepEqual(cmds, [
			["setCustomDimension", 5, "payment_brick_ready"],
			["trackEvent", "Booking Funnel", "Payment Brick Ready", "1707"],
			[
				"trackEvent",
				"Booking Funnel",
				"Payment Brick Error",
				"fields_setup_failed",
			],
			[
				"trackEvent",
				"Booking Funnel",
				"Payment Brick Error",
				"brick_init_error",
			],
		]);
		assert.equal(JSON.stringify(cmds).includes("Maria"), false);
		assert.equal(JSON.stringify(cmds).includes("maria.gomez"), false);
	}

	// 3. bricksBuilder.create() rejection
	{
		const harness = await createFunnelHarness({
			brickCreateError: new Error("Mount failed for Maria Gomez"),
		});
		await harness.selectValidDates();
		harness.clearPaq();
		await harness.submitHoldForm();
		const cmds = harness.getSnapshot();

		assert.ok(
			cmds.some(
				(c) =>
					c[0] === "trackEvent" &&
					c[1] === "Booking Funnel" &&
					c[2] === "Payment Brick Error" &&
					c[3] === "brick_init_error",
			),
		);
		assert.equal(
			cmds.some((c) => c[0] === "trackEvent" && c[2] === "Hold Failed"),
			false,
			"Brick creation error after hold must not trigger Hold Failed",
		);
		assert.equal(JSON.stringify(cmds).includes("Maria"), false);
	}
});

test("#343 Instant Settlement (status === approved) advances to confirmed and fires Goal 5 with authoritative COP revenue", async () => {
	const harness = await createFunnelHarness({
		propertyId: "1707",
		bookRequestHandler: async () => ({
			ok: true,
			status: 200,
			json: async () => ({
				success: true,
				reservation_uid: "ovf_inst_1707",
				total_price: 2150000,
				payment_token: "tok_inst",
			}),
		}),
		paymentHandler: async () => ({
			ok: true,
			status: 200,
			json: async () => ({
				success: true,
				status: "approved",
				payment_id: "mp_approved_1",
				reservation_code: "ovf_inst_1707",
			}),
		}),
	});

	await harness.selectValidDates();
	await harness.submitHoldForm();
	const brickCfg = harness.getBrickConfig();
	assert.ok(brickCfg?.callbacks?.onReady && brickCfg?.callbacks?.onSubmit);
	brickCfg.callbacks.onReady();
	harness.clearPaq();

	await brickCfg.callbacks.onSubmit({
		selectedPaymentMethod: "credit_card",
		formData: { token: "card_tok_abc", installments: 1 },
	});

	const cmds = harness.getSnapshot();
	assert.deepEqual(cmds, [
		["setCustomDimension", 5, "payment_submitted"],
		["trackEvent", "Booking Funnel", "Payment Submitted", "credit_card"],
		["setCustomDimension", 5, "confirmed"],
		[
			"trackEvent",
			"Booking Funnel",
			"Direct Reservation Confirmed",
			"1707:credit_card",
			2150000,
		],
		["trackGoal", 5, 2150000],
	]);

	const serialized = JSON.stringify(cmds);
	assert.equal(serialized.includes("ovf_inst_1707"), false);
	assert.equal(serialized.includes("Maria"), false);
	assert.equal(serialized.includes("maria.gomez"), false);
});

test("#343 Asynchronous Settlement (PSE redirect, Efecty Cash Voucher, and generic pending) NEVER fire Goal 5 or advance to confirmed", async () => {
	// 1. PSE Redirect
	{
		const harness = await createFunnelHarness({
			propertyId: "1707",
			paymentHandler: async () => ({
				ok: true,
				status: 200,
				json: async () => ({
					success: true,
					status: "pending",
					payment_id: "mp_pse_1",
					reservation_code: "ovf_pse_1",
					external_resource_url:
						"https://www.mercadopago.com.co/payments/pse/redirect?id=123",
				}),
			}),
		});
		await harness.selectValidDates();
		await harness.submitHoldForm();
		const brickCfg = harness.getBrickConfig();
		assert.ok(brickCfg?.callbacks?.onSubmit);
		harness.clearPaq();

		await brickCfg.callbacks.onSubmit({
			selectedPaymentMethod: "pse",
			formData: { financial_institution: "1007" },
		});

		const cmds = harness.getSnapshot();
		assert.deepEqual(cmds, [
			["setCustomDimension", 5, "payment_submitted"],
			["trackEvent", "Booking Funnel", "Payment Submitted", "pse"],
			["trackEvent", "Booking Funnel", "PSE Redirect Initiated", "1707"],
		]);
		assert.equal(
			cmds.some((c) => c[0] === "trackGoal" && c[1] === 5),
			false,
			"PSE redirect must NEVER fire Goal 5",
		);
		assert.equal(
			cmds.some((c) => c[0] === "setCustomDimension" && c[2] === "confirmed"),
			false,
			"PSE redirect must NEVER advance Funnel Stage to confirmed",
		);
	}

	// 2. Efecty Cash Voucher
	{
		const harness = await createFunnelHarness({
			propertyId: "1606",
			paymentHandler: async () => ({
				ok: true,
				status: 200,
				json: async () => ({
					success: true,
					status: "pending",
					payment_id: "mp_efecty_1",
					reservation_code: "ovf_efecty_1",
					barcode: "1234567890",
					verification_code: "9988",
					printable_voucher_url: "https://www.mercadopago.com.co/voucher/123",
				}),
			}),
		});
		await harness.selectValidDates();
		await harness.submitHoldForm();
		const brickCfg = harness.getBrickConfig();
		assert.ok(brickCfg?.callbacks?.onSubmit);
		harness.clearPaq();

		await brickCfg.callbacks.onSubmit({
			selectedPaymentMethod: "efecty",
			formData: {},
		});

		const cmds = harness.getSnapshot();
		assert.deepEqual(cmds, [
			["setCustomDimension", 5, "payment_submitted"],
			["trackEvent", "Booking Funnel", "Payment Submitted", "efecty"],
			["trackEvent", "Booking Funnel", "Cash Voucher Issued", "1606"],
		]);
		assert.equal(
			cmds.some((c) => c[0] === "trackGoal" && c[1] === 5),
			false,
			"Cash Voucher must NEVER fire Goal 5",
		);
		assert.equal(
			cmds.some((c) => c[0] === "setCustomDimension" && c[2] === "confirmed"),
			false,
			"Cash Voucher must NEVER advance Funnel Stage to confirmed",
		);
	}

	// 3. Generic pending checkout fallback
	{
		const harness = await createFunnelHarness({
			propertyId: "1707",
			paymentHandler: async () => ({
				ok: true,
				status: 200,
				json: async () => ({
					success: true,
					status: "in_process",
					payment_id: "mp_review_1",
					reservation_code: "ovf_review_1",
				}),
			}),
		});
		await harness.selectValidDates();
		await harness.submitHoldForm();
		const brickCfg = harness.getBrickConfig();
		assert.ok(brickCfg?.callbacks?.onSubmit);
		harness.clearPaq();

		await brickCfg.callbacks.onSubmit({
			selectedPaymentMethod: "debit_card",
			formData: { token: "deb_tok" },
		});

		const cmds = harness.getSnapshot();
		assert.deepEqual(cmds, [
			["setCustomDimension", 5, "payment_submitted"],
			["trackEvent", "Booking Funnel", "Payment Submitted", "debit_card"],
			["trackEvent", "Booking Funnel", "Payment Pending", "1707:debit_card"],
		]);
		assert.equal(
			cmds.some((c) => c[0] === "trackGoal" && c[1] === 5),
			false,
			"Pending fallback must NEVER fire Goal 5",
		);
	}
});

test("#343 Payment decline, API error, and network error emit machine codes without Goal 5 or raw localized text", async () => {
	// 1. Declined with Mercado Pago status_detail
	{
		const harness = await createFunnelHarness({
			paymentHandler: async () => ({
				ok: false,
				status: 400,
				json: async () => ({
					success: false,
					status: "rejected",
					status_detail: "cc_rejected_insufficient_amount",
					error: "Fondos insuficientes en la tarjeta de Maria Gomez",
				}),
			}),
		});
		await harness.selectValidDates();
		await harness.submitHoldForm();
		const onSubmit = harness.getBrickConfig()?.callbacks?.onSubmit;
		assert.ok(onSubmit);
		harness.clearPaq();

		await assert.rejects(() =>
			onSubmit({
				selectedPaymentMethod: "credit_card",
				formData: { token: "tok_declined" },
			}),
		);

		const cmds = harness.getSnapshot();
		assert.deepEqual(cmds, [
			["setCustomDimension", 5, "payment_submitted"],
			["trackEvent", "Booking Funnel", "Payment Submitted", "credit_card"],
			[
				"trackEvent",
				"Booking Funnel",
				"Payment Failed",
				"cc_rejected_insufficient_amount",
			],
		]);
		assert.equal(JSON.stringify(cmds).includes("Fondos"), false);
		assert.equal(JSON.stringify(cmds).includes("Maria"), false);
	}

	// 2. Declined without status_detail -> payment_declined
	{
		const harness = await createFunnelHarness({
			paymentHandler: async () => ({
				ok: false,
				status: 400,
				json: async () => ({
					success: false,
					status: "rejected",
					error: "Tu pago fue rechazado. Intenta con otra tarjeta.",
				}),
			}),
		});
		await harness.selectValidDates();
		await harness.submitHoldForm();
		const onSubmit = harness.getBrickConfig()?.callbacks?.onSubmit;
		assert.ok(onSubmit);
		harness.clearPaq();

		await assert.rejects(() =>
			onSubmit({
				selectedPaymentMethod: "credit_card",
				formData: { token: "tok_declined_2" },
			}),
		);

		const cmds = harness.getSnapshot();
		assert.deepEqual(cmds, [
			["setCustomDimension", 5, "payment_submitted"],
			["trackEvent", "Booking Funnel", "Payment Submitted", "credit_card"],
			["trackEvent", "Booking Funnel", "Payment Failed", "payment_declined"],
		]);
		assert.equal(JSON.stringify(cmds).includes("rechazado"), false);
	}

	// 3. Network error during payment.php fetch -> network_error (single failure event)
	{
		const harness = await createFunnelHarness({
			paymentHandler: async () => {
				throw new Error("Socket hang up for maria.gomez@example.com");
			},
		});
		await harness.selectValidDates();
		await harness.submitHoldForm();
		const onSubmit = harness.getBrickConfig()?.callbacks?.onSubmit;
		assert.ok(onSubmit);
		harness.clearPaq();

		await assert.rejects(() =>
			onSubmit({
				selectedPaymentMethod: "pse",
				formData: {},
			}),
		);

		const cmds = harness.getSnapshot();
		assert.deepEqual(cmds, [
			["setCustomDimension", 5, "payment_submitted"],
			["trackEvent", "Booking Funnel", "Payment Submitted", "pse"],
			["trackEvent", "Booking Funnel", "Payment Failed", "network_error"],
		]);
		assert.equal(JSON.stringify(cmds).includes("maria.gomez"), false);
	}
});

test("#343 Airbnb booking redirect fires Goal 3 while Airbnb review-verification clicks emit non-Goal Trust events", async () => {
	const harness = await createFunnelHarness({ propertyId: "1707" });

	// Disabled #btn-book click does not fire Goal 3
	harness.clearPaq();
	harness.btnBook.dispatchEvent("click");
	assert.deepEqual(harness.getSnapshot(), []);

	// Enabled #btn-book click after date selection fires Goal 3
	await harness.selectValidDates();
	harness.clearPaq();
	harness.btnBook.dispatchEvent("click");
	assert.deepEqual(harness.getSnapshot(), [
		["trackEvent", "Booking", "Redirect to Airbnb", "1707"],
		["trackGoal", 3],
	]);

	// Airbnb review-verification clicks emit Trust event and NEVER Goal 3
	for (const placement of [
		"home_card_1707",
		"home_card_1606",
		"property_reviews",
	]) {
		harness.clearPaq();
		const link = createMockNode("", "A", {
			"data-analytics-airbnb-review": placement,
		});
		const innerSpan = createMockNode("", "SPAN", {}, link);
		harness.dispatchDocumentClick(innerSpan);

		const cmds = harness.getSnapshot();
		assert.deepEqual(cmds, [
			["trackEvent", "Trust", "Airbnb Review Verification Click", placement],
		]);
		assert.equal(
			cmds.some((c) => c[0] === "trackGoal"),
			false,
			`Review verification click (${placement}) must never fire any Matomo Goal`,
		);
	}
});

test("#343 Unified delegated WhatsApp tracking fires Goal 4 with standardized placement labels across all 5 public surfaces", async () => {
	const harness = await createFunnelHarness({
		propertyId: "1606",
		useDistMain: true,
	});

	const placements = [
		"home_host",
		"home_cta",
		"property_booking",
		"property_host",
		"contact_sidebar",
	];

	for (const placement of placements) {
		harness.clearPaq();
		const link = createMockNode("", "A", {
			"data-analytics-whatsapp": placement,
		});
		const iconChild = createMockNode("", "SVG", {}, link);
		harness.dispatchDocumentClick(iconChild);

		const cmds = harness.getSnapshot();
		assert.deepEqual(cmds, [
			["trackEvent", "Contact", "WhatsApp Click", placement],
			["trackGoal", 4],
		]);
	}
});

test("#343 main.js remains null-safe when window.ovfAnalytics is absent (standalone vm harnesses)", async () => {
	const harness = await createFunnelHarness({
		propertyId: "1707",
		loadAnalytics: false,
	});
	await harness.selectValidDates();
	await harness.submitHoldForm();
	const brickCfg = harness.getBrickConfig();
	assert.ok(brickCfg?.callbacks?.onReady && brickCfg?.callbacks?.onSubmit);
	brickCfg.callbacks.onReady();
	await brickCfg.callbacks.onSubmit({
		selectedPaymentMethod: "credit_card",
		formData: { token: "tok_standalone" },
	});
	harness.btnBook.dispatchEvent("click");

	const waLink = createMockNode("", "A", {
		"data-analytics-whatsapp": "home_host",
	});
	harness.dispatchDocumentClick(waLink);

	const reviewLink = createMockNode("", "A", {
		"data-analytics-airbnb-review": "home_card_1707",
	});
	harness.dispatchDocumentClick(reviewLink);

	assert.ok(
		harness.location.href.includes("booking-success"),
		"Checkout routing must continue functioning cleanly without window.ovfAnalytics",
	);
});
