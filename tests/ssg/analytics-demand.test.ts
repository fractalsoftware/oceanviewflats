import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import test from "node:test";
import vm from "node:vm";

const PUBLIC_DIR = path.join(process.cwd(), "public");
const DIST_DIR = path.join(process.cwd(), "dist");

type MockEvent = {
	preventDefault?: () => void;
	stopPropagation?: () => void;
	target?: unknown;
};

type Listener = (event: MockEvent) => void | Promise<void>;

interface MockClassList {
	add: (...tokens: string[]) => void;
	remove: (...tokens: string[]) => void;
	contains: (token: string) => boolean;
}

interface MockNode {
	id: string;
	tagName: string;
	type: string;
	value: string;
	checked: boolean;
	disabled: boolean;
	textContent: string;
	className: string;
	min: string;
	children: MockNode[];
	attributes: Record<string, string>;
	classList: MockClassList;
	innerHTML: string;
	setAttribute: (name: string, value: string) => void;
	getAttribute: (name: string) => string | null;
	removeAttribute: (name: string) => void;
	addEventListener: (event: string, listener: Listener) => void;
	dispatchEvent: (event: string, payload?: MockEvent) => Promise<void>;
	appendChild: (child: MockNode) => MockNode;
	reset: () => void;
	querySelectorAll: (selector: string) => MockNode[];
}

function createMockNode(
	id: string,
	tagName = "DIV",
	initialClasses: string[] = [],
	initialAttrs: Record<string, string> = {},
): MockNode {
	const classes = new Set<string>(initialClasses);
	const attrs: Record<string, string> = { ...initialAttrs };
	const listeners: Record<string, Listener[]> = {};

	const node: MockNode = {
		id,
		tagName,
		type: "",
		value: "",
		checked: false,
		disabled: false,
		textContent: "",
		className: initialClasses.join(" "),
		min: "",
		children: [],
		attributes: attrs,
		classList: {
			add: (...tokens: string[]) => {
				for (const tok of tokens) classes.add(tok);
				node.className = Array.from(classes).join(" ");
			},
			remove: (...tokens: string[]) => {
				for (const tok of tokens) classes.delete(tok);
				node.className = Array.from(classes).join(" ");
			},
			contains: (token: string) =>
				classes.has(token) || node.className.split(/\s+/).includes(token),
		},
		get innerHTML() {
			return "";
		},
		set innerHTML(val: string) {
			if (val === "") {
				node.children = [];
			}
		},
		setAttribute: (name: string, value: string) => {
			attrs[name] = String(value);
		},
		getAttribute: (name: string) =>
			Object.hasOwn(attrs, name) ? attrs[name] : null,
		removeAttribute: (name: string) => {
			delete attrs[name];
		},
		addEventListener: (event: string, listener: Listener) => {
			listeners[event] = listeners[event] || [];
			listeners[event].push(listener);
		},
		dispatchEvent: async (event: string, payload: MockEvent = {}) => {
			for (const fn of listeners[event] || []) {
				await fn({
					preventDefault: () => {},
					stopPropagation: () => {},
					target: node,
					...payload,
				});
			}
		},
		appendChild: (child: MockNode) => {
			node.children.push(child);
			return child;
		},
		reset: () => {
			node.value = "";
		},
		querySelectorAll: () => node.children,
	};

	return node;
}

function createFixedDateClass(fixedIsoLocal: string): DateConstructor {
	const match = /^(\d{4})-(\d{2})-(\d{2})(?:T(\d{2}):(\d{2}):(\d{2}))?$/.exec(
		fixedIsoLocal,
	);
	assert.ok(match, `Invalid fixed date: ${fixedIsoLocal}`);
	const fixedTime = new Date(
		Number(match[1]),
		Number(match[2]) - 1,
		Number(match[3]),
		Number(match[4] ?? 12),
		Number(match[5] ?? 0),
		Number(match[6] ?? 0),
	).getTime();

	class FixedDate extends Date {
		constructor(...args: unknown[]) {
			if (args.length === 0) {
				super(fixedTime);
			} else if (args.length === 1) {
				super(args[0] as string | number | Date);
			} else {
				super(
					args[0] as number,
					args[1] as number,
					(args[2] as number) ?? 1,
					(args[3] as number) ?? 0,
					(args[4] as number) ?? 0,
					(args[5] as number) ?? 0,
					(args[6] as number) ?? 0,
				);
			}
		}

		static override now(): number {
			return fixedTime;
		}
	}

	return FixedDate as unknown as DateConstructor;
}

const MONTH_NAMES = {
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
	es: [
		"Enero",
		"Febrero",
		"Marzo",
		"Abril",
		"Mayo",
		"Junio",
		"Julio",
		"Agosto",
		"Septiembre",
		"Octubre",
		"Noviembre",
		"Diciembre",
	],
};

const DAY_NAMES = {
	en: ["Su", "Mo", "Tu", "We", "Th", "Fr", "Sa"],
	es: ["Do", "Lu", "Ma", "Mi", "Ju", "Vi", "Sa"],
};

interface CalendarHarnessOptions {
	propertyId?: "1707" | "1606";
	lang?: "en" | "es";
	fixedNow?: string;
	loadAnalytics?: boolean;
	useDist?: boolean;
	blockedDates?: string[];
	fetchHandler?: (url: string) => Promise<{
		ok: boolean;
		status?: number;
		json: () => Promise<unknown>;
	}>;
}

async function createCalendarHarness(options: CalendarHarnessOptions = {}) {
	const propertyId = options.propertyId ?? "1707";
	const lang = options.lang ?? "en";
	const loadAnalytics = options.loadAnalytics ?? true;
	const baseDir = options.useDist ? DIST_DIR : PUBLIC_DIR;

	const pageConfigCode = fs.readFileSync(
		path.join(baseDir, "js", "page-config.js"),
		"utf-8",
	);
	const analyticsCode = fs.readFileSync(
		path.join(baseDir, "js", "analytics.js"),
		"utf-8",
	);
	const mainCode = fs.readFileSync(
		path.join(baseDir, "js", "main.js"),
		"utf-8",
	);

	const calendarWidget = createMockNode("calendar-widget", "DIV", [], {
		"data-lang": lang,
		"data-month-names": JSON.stringify(MONTH_NAMES),
		"data-day-names": JSON.stringify(DAY_NAMES),
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
		"data-airbnb-url": `https://airbnb.com/rooms/${propertyId}`,
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
	const minStayWarning = createMockNode("min-stay-warning", "DIV", ["hidden"]);
	const btnDirectSubmit = createMockNode("btn-direct-submit", "BUTTON");
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
	const convertedCurrencyBox = createMockNode("converted-currency-box", "DIV", [
		"hidden",
	]);
	const convertedCurrencyValue = createMockNode(
		"converted-currency-value",
		"SPAN",
	);

	const pageConfigPayload = {
		pageId: `Oceanview${propertyId}`,
		lang,
		assetPrefix: "../",
		apiBase: "../api/",
		analytics: {
			pageType: "property",
			propertyId,
			enableEngagementTracking: true,
		},
		i18n: {
			dbMinStayNotice: "Minimum stay is {minStay} nights.",
			dbNightsFormat: "({nights} nights)",
		},
	};
	const configEl = createMockNode("ovf-page-config", "SCRIPT");
	configEl.textContent = JSON.stringify(pageConfigPayload);

	const elementsById = new Map<string, MockNode>([
		["ovf-page-config", configEl],
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
		["min-stay-warning", minStayWarning],
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

	const paq: unknown[][] = [];
	const docListeners: Record<string, Listener[]> = {};
	const scriptStub = {
		async: false,
		src: "",
		onload: null as (() => void) | null,
		onerror: null as (() => void) | null,
		addEventListener: () => {},
		parentNode: { insertBefore: () => {} },
	};

	const storage = new Map<string, string>();
	const pathname = `/Oceanview${propertyId}/${lang === "en" ? "" : `${lang}.html`}`;
	const href = `https://www.oceanviewflats.com${pathname}`;
	const FixedDate = createFixedDateClass(
		options.fixedNow ?? "2026-11-27T12:00:00",
	);

	const win: Record<string, unknown> = {
		_paq: paq,
		scrollY: 0,
		MercadoPago: class {
			bricks() {
				return { create: async () => ({ unmount: async () => {} }) };
			}
		},
		location: {
			href,
			origin: "https://www.oceanviewflats.com",
			pathname,
			search: "",
			hash: "",
		},
		addEventListener: () => {},
		scrollTo: () => {},
	};

	const sandbox = {
		window: win,
		location: win.location,
		document: {
			documentElement: { lang },
			referrer: "",
			head: { appendChild: () => {} },
			createElement: (tag: string) => {
				if (tag.toLowerCase() === "script") {
					return { ...scriptStub };
				}
				return createMockNode("", tag.toUpperCase());
			},
			getElementsByTagName: (tag: string) =>
				tag === "script" ? [scriptStub] : [],
			querySelector: () => null,
			querySelectorAll: () => [],
			getElementById: (id: string) => elementsById.get(id) ?? null,
			addEventListener: (evt: string, fn: Listener) => {
				docListeners[evt] = docListeners[evt] || [];
				docListeners[evt].push(fn);
			},
			dispatchEvent: async (evt: string, payload: MockEvent = {}) => {
				for (const fn of docListeners[evt] || []) {
					await fn({
						preventDefault: () => {},
						stopPropagation: () => {},
						...payload,
					});
				}
			},
		},
		localStorage: {
			getItem: (k: string) => storage.get(k) ?? null,
			setItem: (k: string, v: string) => {
				storage.set(k, String(v));
			},
		},
		fetch: async (url: string) => {
			if (options.fetchHandler) {
				return options.fetchHandler(url);
			}
			if (url.includes("availability.php")) {
				return {
					ok: true,
					status: 200,
					json: async () => options.blockedDates ?? [],
				};
			}
			if (url.includes("prices.json")) {
				return { ok: true, status: 200, json: async () => [] };
			}
			if (url.includes("open.er-api.com")) {
				return {
					ok: true,
					status: 200,
					json: async () => ({
						rates: { USD: 0.00025, EUR: 0.00023, JPY: 0.038 },
					}),
				};
			}
			return { ok: false, status: 404, json: async () => ({}) };
		},
		Intl,
		Date: FixedDate,
		URL,
		URLSearchParams,
		FormData: class {},
		console: { log: () => {}, warn: () => {}, error: () => {} },
	};

	vm.createContext(sandbox);
	vm.runInContext(pageConfigCode, sandbox);
	if (loadAnalytics) {
		vm.runInContext(analyticsCode, sandbox);
	}
	vm.runInContext(mainCode, sandbox);
	await sandbox.document.dispatchEvent("DOMContentLoaded");
	await new Promise((resolve) => setTimeout(resolve, 15));

	const getDayButton = (dateStr: string): MockNode => {
		const found = calendarGrid.children.find(
			(child) => child.getAttribute("data-date") === dateStr,
		);
		assert.ok(
			found,
			`Day button for ${dateStr} must exist in ${monthLabel.textContent}`,
		);
		return found;
	};

	const getPaq = () => JSON.parse(JSON.stringify(paq)) as unknown[][];

	return {
		paq,
		getPaq,
		window: win,
		btnPrevMonth,
		btnNextMonth,
		monthLabel,
		calendarGrid,
		checkInDisplay,
		checkOutDisplay,
		formCheckIn,
		formCheckOut,
		minStayWarning,
		convertedCurrencyValue,
		getDayButton,
	};
}

interface ContactHarnessOptions {
	lang?: "en" | "es" | "ja";
	fixedNow?: string;
	loadAnalytics?: boolean;
	useDist?: boolean;
	i18nOverrides?: Record<string, string>;
	postHandler?: (
		url: string,
		init?: { method?: string; body?: unknown },
	) => Promise<{
		ok: boolean;
		status: number;
		json: () => Promise<unknown>;
	}>;
}

async function createContactHarness(options: ContactHarnessOptions = {}) {
	const lang = options.lang ?? "en";
	const loadAnalytics = options.loadAnalytics ?? true;
	const baseDir = options.useDist ? DIST_DIR : PUBLIC_DIR;

	const pageConfigCode = fs.readFileSync(
		path.join(baseDir, "js", "page-config.js"),
		"utf-8",
	);
	const analyticsCode = fs.readFileSync(
		path.join(baseDir, "js", "analytics.js"),
		"utf-8",
	);
	const mainCode = fs.readFileSync(
		path.join(baseDir, "js", "main.js"),
		"utf-8",
	);

	const form = createMockNode("contact-form", "FORM");
	const submitButton = createMockNode("submit-button", "BUTTON");
	const submitText = createMockNode("submit-text", "SPAN");
	const formMessage = createMockNode("form-message", "DIV", ["hidden"]);
	const nameInput = createMockNode("name", "INPUT");
	const emailInput = createMockNode("email", "INPUT");
	const phoneInput = createMockNode("phone-number", "INPUT");
	const messageInput = createMockNode("message", "TEXTAREA");
	const checkInInput = createMockNode("check-in", "INPUT");
	const checkOutInput = createMockNode("check-out", "INPUT");
	const privacyConsent = createMockNode("privacy-consent", "INPUT");
	const captchaLabel = createMockNode("captcha-label", "LABEL");
	captchaLabel.textContent = "Security check";
	const captchaChallenge = createMockNode("captcha-challenge", "INPUT");
	const captchaResponse = createMockNode("captcha-response", "INPUT");

	const pageConfigPayload = {
		pageId: "contact",
		lang,
		assetPrefix: "../",
		apiBase: "../api/",
		analytics: {
			pageType: "contact",
			propertyId: "portfolio",
			enableEngagementTracking: true,
		},
		i18n: {
			contactSuccess: "Message sent",
			contactError: "Por favor completa todos los campos obligatorios.",
			contactErrPrivacyConsent:
				"Debes aceptar la Política de Tratamiento de Datos Personales.",
			contactSubmitting: "Enviando...",
			contactSubmit: "Enviar consulta",
			contactDateError:
				"La fecha de salida debe ser posterior a la fecha de entrada.",
			...(options.i18nOverrides ?? {}),
		},
	};
	const configEl = createMockNode("ovf-page-config", "SCRIPT");
	configEl.textContent = JSON.stringify(pageConfigPayload);

	const elementsById = new Map<string, MockNode>([
		["ovf-page-config", configEl],
		["contact-form", form],
		["submit-button", submitButton],
		["submit-text", submitText],
		["form-message", formMessage],
		["name", nameInput],
		["email", emailInput],
		["phone-number", phoneInput],
		["message", messageInput],
		["check-in", checkInInput],
		["check-out", checkOutInput],
		["privacy-consent", privacyConsent],
		["captcha-label", captchaLabel],
		["captcha-challenge", captchaChallenge],
		["captcha-response", captchaResponse],
	]);

	const paq: unknown[][] = [];
	const docListeners: Record<string, Listener[]> = {};
	const scriptStub = {
		async: false,
		src: "",
		parentNode: { insertBefore: () => {} },
	};
	const FixedDate = createFixedDateClass(
		options.fixedNow ?? "2026-11-27T12:00:00",
	);

	const pathname = `/contact/${lang === "en" ? "" : `${lang}.html`}`;
	const win: Record<string, unknown> = {
		_paq: paq,
		scrollY: 0,
		location: {
			href: `https://www.oceanviewflats.com${pathname}`,
			origin: "https://www.oceanviewflats.com",
			pathname,
			search: "",
			hash: "",
		},
		addEventListener: () => {},
		scrollTo: () => {},
	};

	class MockFormData {
		entries: Array<[string, string]> = [];
		constructor() {
			this.entries = [
				["name", nameInput.value],
				["email", emailInput.value],
				["phone_number", phoneInput.value],
				["message", messageInput.value],
				["check_in", checkInInput.value],
				["check_out", checkOutInput.value],
				["captcha_challenge", captchaChallenge.value],
				["captcha_response", captchaResponse.value],
				["privacy_consent", privacyConsent.checked ? "1" : ""],
			];
		}
		[Symbol.iterator]() {
			return this.entries[Symbol.iterator]();
		}
	}

	const sandbox = {
		window: win,
		location: win.location,
		document: {
			documentElement: { lang },
			referrer: "",
			head: { appendChild: () => {} },
			createElement: (tag: string) =>
				tag.toLowerCase() === "script"
					? { ...scriptStub }
					: createMockNode("", tag.toUpperCase()),
			getElementsByTagName: (tag: string) =>
				tag === "script" ? [scriptStub] : [],
			querySelector: () => null,
			querySelectorAll: () => [],
			getElementById: (id: string) => elementsById.get(id) ?? null,
			addEventListener: (evt: string, fn: Listener) => {
				docListeners[evt] = docListeners[evt] || [];
				docListeners[evt].push(fn);
			},
			dispatchEvent: async (evt: string, payload: MockEvent = {}) => {
				for (const fn of docListeners[evt] || []) {
					await fn({
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
		fetch: async (url: string, init?: { method?: string; body?: unknown }) => {
			if (url.includes("action=captcha")) {
				return {
					ok: true,
					status: 200,
					json: async () => ({
						challenge: "4 + 5 = ?",
						signature: "sig_test_123",
					}),
				};
			}
			if (options.postHandler) {
				return options.postHandler(url, init);
			}
			return {
				ok: true,
				status: 200,
				json: async () => ({ success: true, message: "Message sent" }),
			};
		},
		Intl,
		Date: FixedDate,
		URL,
		URLSearchParams,
		FormData: MockFormData,
		console: { log: () => {}, warn: () => {}, error: () => {} },
	};

	vm.createContext(sandbox);
	vm.runInContext(pageConfigCode, sandbox);
	if (loadAnalytics) {
		vm.runInContext(analyticsCode, sandbox);
	}
	vm.runInContext(mainCode, sandbox);
	await sandbox.document.dispatchEvent("DOMContentLoaded");
	await new Promise((resolve) => setTimeout(resolve, 10));

	const fillValidBaseFields = () => {
		nameInput.value = "Maria Camila";
		emailInput.value = "maria.camila@example.com";
		phoneInput.value = "3001234567";
		messageInput.value = "We would love to stay at Playa Salguero in December.";
		captchaResponse.value = "9";
		privacyConsent.checked = true;
	};

	const getPaq = () => JSON.parse(JSON.stringify(paq)) as unknown[][];

	return {
		paq,
		getPaq,
		window: win,
		form,
		nameInput,
		emailInput,
		phoneInput,
		messageInput,
		checkInInput,
		checkOutInput,
		privacyConsent,
		captchaResponse,
		formMessage,
		fillValidBaseFields,
	};
}

test("#344 valid authoritative Quote emits bucketed Demand Signal with rounded COP Event Value, advances Funnel Stage to quote_generated, and deduplicates background exchange-rate reloads", async () => {
	let resolveExchangeRates!: (val: {
		ok: boolean;
		status: number;
		json: () => Promise<unknown>;
	}) => void;
	const exchangeRatesPromise = new Promise<{
		ok: boolean;
		status: number;
		json: () => Promise<unknown>;
	}>((resolve) => {
		resolveExchangeRates = resolve;
	});

	const harness = await createCalendarHarness({
		propertyId: "1707",
		fixedNow: "2026-11-27T12:00:00",
		fetchHandler: async (url: string) => {
			if (url.includes("availability.php")) {
				return { ok: true, status: 200, json: async () => [] };
			}
			if (url.includes("prices.json")) {
				return { ok: true, status: 200, json: async () => [] };
			}
			if (url.includes("open.er-api.com")) {
				return exchangeRatesPromise;
			}
			if (url.includes("quote.php")) {
				return {
					ok: true,
					status: 200,
					json: async () => ({
						success: true,
						data: {
							nights_count: 5,
							accommodation_total_cop: 2250000,
							cleaning_fee_cop: 100000,
							total_cop: 2350000.4,
							minimum_stay_required: 2,
							is_valid: true,
							violation_reason: null,
						},
					}),
				};
			}
			return { ok: false, status: 404, json: async () => ({}) };
		},
	});

	const ovfAnalytics = harness.window.ovfAnalytics as {
		getFunnelStage: () => string;
	};
	assert.equal(ovfAnalytics.getFunnelStage(), "property_viewed");

	// Move to December 2026 and select 2026-12-10 -> 2026-12-15 (lead = 13d -> 8-30d, stay = 5n -> 4-6n)
	await harness.btnNextMonth.dispatchEvent("click");
	await harness.getDayButton("2026-12-10").dispatchEvent("click");
	await harness.getDayButton("2026-12-15").dispatchEvent("click");
	await new Promise((resolve) => setTimeout(resolve, 20));

	assert.equal(ovfAnalytics.getFunnelStage(), "quote_generated");
	assert.ok(
		harness.paq.some(
			(cmd) =>
				cmd[0] === "setCustomDimension" &&
				cmd[1] === 5 &&
				cmd[2] === "quote_generated",
		),
		"Valid Quote must advance Visit-scoped Funnel Stage dimension (slot 5) to quote_generated",
	);

	const quoteEvents = () =>
		harness
			.getPaq()
			.filter(
				(cmd) =>
					cmd[0] === "trackEvent" &&
					cmd[1] === "Demand" &&
					cmd[2] === "Quote Generated",
			);
	assert.equal(quoteEvents().length, 1);
	assert.deepEqual(quoteEvents()[0], [
		"trackEvent",
		"Demand",
		"Quote Generated",
		"1707:2026-12:8-30d:4-6n:authoritative",
		2350000,
	]);

	// Resolve background exchange-rate reload (triggers renderQuoteDetails(lastQuoteDetails)) -> MUST NOT double-fire
	resolveExchangeRates({
		ok: true,
		status: 200,
		json: async () => ({ rates: { USD: 0.00025 } }),
	});
	await new Promise((resolve) => setTimeout(resolve, 20));
	assert.ok(
		harness.convertedCurrencyValue.textContent.includes("USD"),
		"Background exchange-rate reload should still update converted currency display",
	);
	assert.equal(
		quoteEvents().length,
		1,
		"Background exchange-rate re-render must NOT double-fire Quote Generated",
	);

	// Selecting a different date range (2026-12-18 -> 2026-12-20) emits a fresh Quote Generated event
	await harness.getDayButton("2026-12-18").dispatchEvent("click");
	await harness.getDayButton("2026-12-20").dispatchEvent("click");
	await new Promise((resolve) => setTimeout(resolve, 20));
	assert.equal(quoteEvents().length, 2);
});

test("#344 fallback Quote emits fallback source with rounded COP Event Value and deduplicates delayed prices.json reloads", async () => {
	let resolvePrices!: (val: {
		ok: boolean;
		status: number;
		json: () => Promise<unknown>;
	}) => void;
	const pricesPromise = new Promise<{
		ok: boolean;
		status: number;
		json: () => Promise<unknown>;
	}>((resolve) => {
		resolvePrices = resolve;
	});

	const harness = await createCalendarHarness({
		propertyId: "1606",
		fixedNow: "2026-11-27T12:00:00",
		fetchHandler: async (url: string) => {
			if (url.includes("availability.php")) {
				return { ok: true, status: 200, json: async () => [] };
			}
			if (url.includes("prices.json")) {
				return pricesPromise;
			}
			if (url.includes("open.er-api.com")) {
				return { ok: false, status: 503, json: async () => ({}) };
			}
			if (url.includes("quote.php")) {
				return {
					ok: false,
					status: 500,
					json: async () => ({ success: false }),
				};
			}
			return { ok: false, status: 404, json: async () => ({}) };
		},
	});

	// Navigate 4 months forward to March 2027 (lead > 90d -> 90d+), select 2027-03-05 -> 2027-03-19 (14 nights -> 14n+)
	for (let step = 0; step < 4; step++) {
		await harness.btnNextMonth.dispatchEvent("click");
	}
	await harness.getDayButton("2027-03-05").dispatchEvent("click");
	await harness.getDayButton("2027-03-19").dispatchEvent("click");
	await new Promise((resolve) => setTimeout(resolve, 20));

	const quoteEvents = () =>
		harness
			.getPaq()
			.filter(
				(cmd) =>
					cmd[0] === "trackEvent" &&
					cmd[1] === "Demand" &&
					cmd[2] === "Quote Generated",
			);
	assert.equal(quoteEvents().length, 1);
	// Property 1606 fallback: 14 * 350,000 + 80,000 = 4,980,000 COP
	assert.deepEqual(quoteEvents()[0], [
		"trackEvent",
		"Demand",
		"Quote Generated",
		"1606:2027-03:90d+:14n+:fallback",
		4980000,
	]);

	// Resolve delayed prices.json (re-renders fallbackDetails) -> MUST NOT double-fire
	resolvePrices({
		ok: true,
		status: 200,
		json: async () => [
			{
				property_id: "1606",
				start_date: "2027-03-01",
				end_date: "2027-03-31",
				nightly_rate_cop: 380000,
				minimum_stay: 2,
			},
		],
	});
	await new Promise((resolve) => setTimeout(resolve, 20));
	assert.equal(
		quoteEvents().length,
		1,
		"Background prices.json reload must NOT double-fire fallback Quote Generated",
	);
});

test("#344 minimum-stay rule violation emits deduplicated min_stay_blocked Demand Signal WITHOUT advancing Funnel Stage to quote_generated", async () => {
	let resolveExchangeRates!: (val: {
		ok: boolean;
		status: number;
		json: () => Promise<unknown>;
	}) => void;
	const exchangeRatesPromise = new Promise<{
		ok: boolean;
		status: number;
		json: () => Promise<unknown>;
	}>((resolve) => {
		resolveExchangeRates = resolve;
	});

	const harness = await createCalendarHarness({
		propertyId: "1707",
		fixedNow: "2026-11-27T12:00:00",
		fetchHandler: async (url: string) => {
			if (url.includes("availability.php")) {
				return { ok: true, status: 200, json: async () => [] };
			}
			if (url.includes("prices.json")) {
				return { ok: true, status: 200, json: async () => [] };
			}
			if (url.includes("open.er-api.com")) {
				return exchangeRatesPromise;
			}
			if (url.includes("quote.php")) {
				return {
					ok: true,
					status: 200,
					json: async () => ({
						success: true,
						data: {
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
			return { ok: false, status: 404, json: async () => ({}) };
		},
	});

	const ovfAnalytics = harness.window.ovfAnalytics as {
		getFunnelStage: () => string;
	};

	await harness.btnNextMonth.dispatchEvent("click");
	await harness.getDayButton("2026-12-24").dispatchEvent("click");
	await harness.getDayButton("2026-12-26").dispatchEvent("click");
	await new Promise((resolve) => setTimeout(resolve, 20));

	assert.equal(
		ovfAnalytics.getFunnelStage(),
		"property_viewed",
		"Minimum-stay violation must NOT advance Funnel Stage to quote_generated",
	);
	assert.equal(
		harness.paq.some(
			(cmd) =>
				cmd[0] === "setCustomDimension" &&
				cmd[1] === 5 &&
				cmd[2] === "quote_generated",
		),
		false,
	);
	assert.equal(
		harness.paq.some(
			(cmd) => cmd[0] === "trackEvent" && cmd[2] === "Quote Generated",
		),
		false,
	);

	const blockedEvents = () =>
		harness
			.getPaq()
			.filter(
				(cmd) =>
					cmd[0] === "trackEvent" &&
					cmd[1] === "Demand" &&
					cmd[2] === "min_stay_blocked",
			);
	assert.equal(blockedEvents().length, 1);
	assert.deepEqual(blockedEvents()[0], [
		"trackEvent",
		"Demand",
		"min_stay_blocked",
		"1707:2026-12:2-3n:min_4n",
	]);

	// Background exchange-rate reload must NOT double-fire min_stay_blocked
	resolveExchangeRates({
		ok: true,
		status: 200,
		json: async () => ({ rates: { USD: 0.00025 } }),
	});
	await new Promise((resolve) => setTimeout(resolve, 20));
	assert.equal(blockedEvents().length, 1);
});

test("#344 calendar prev/next month browsing emits Demand Signal with Property and target YYYY-MM without 31st-of-month rollover bugs", async () => {
	const harness = await createCalendarHarness({
		propertyId: "1606",
		fixedNow: "2027-01-31T12:00:00",
	});

	await harness.btnNextMonth.dispatchEvent("click");
	await harness.btnNextMonth.dispatchEvent("click");
	await harness.btnPrevMonth.dispatchEvent("click");

	const browseEvents = harness
		.getPaq()
		.filter(
			(cmd) =>
				cmd[0] === "trackEvent" &&
				cmd[1] === "Demand" &&
				cmd[2] === "Calendar Month Browsed",
		);
	assert.deepEqual(browseEvents, [
		["trackEvent", "Demand", "Calendar Month Browsed", "1606:2027-02"],
		["trackEvent", "Demand", "Calendar Month Browsed", "1606:2027-03"],
		["trackEvent", "Demand", "Calendar Month Browsed", "1606:2027-02"],
	]);
});

test("#344 booked calendar dates use aria-disabled='true', preserve accessibility & visual classes, keep dates unselected, and emit Unavailable Date Clicked", async () => {
	const harness = await createCalendarHarness({
		propertyId: "1707",
		fixedNow: "2026-11-27T12:00:00",
		blockedDates: ["2026-12-10", "2026-12-15", "2026-12-16"],
	});

	// Past date in current month remains natively disabled
	const pastBtn = harness.getDayButton("2026-11-10");
	assert.equal(pastBtn.disabled, true);
	assert.equal(pastBtn.getAttribute("aria-disabled"), null);

	// Navigate to December 2026
	await harness.btnNextMonth.dispatchEvent("click");
	const bookedBtn = harness.getDayButton("2026-12-10");

	assert.equal(bookedBtn.disabled, false);
	assert.equal(bookedBtn.getAttribute("aria-disabled"), "true");
	assert.equal(bookedBtn.getAttribute("title"), "Already Booked");
	assert.equal(bookedBtn.getAttribute("aria-label"), "10 December, Booked");
	assert.ok(bookedBtn.className.includes("cursor-not-allowed"));
	assert.ok(bookedBtn.className.includes("line-through"));

	// Click booked date before selecting any check-in
	await bookedBtn.dispatchEvent("click");
	assert.equal(harness.formCheckIn.value, "");
	assert.equal(harness.formCheckOut.value, "");
	assert.equal(harness.checkInDisplay.textContent, "Add date");

	// Select valid check-in (Dec 12), then click booked date Dec 16 -> leaves check-out unselected
	await harness.getDayButton("2026-12-12").dispatchEvent("click");
	assert.equal(harness.formCheckIn.value, "");
	assert.notEqual(harness.checkInDisplay.textContent, "Add date");
	assert.equal(harness.checkOutDisplay.textContent, "Add date");

	const bookedAfterBtn = harness.getDayButton("2026-12-16");
	assert.equal(bookedAfterBtn.disabled, false);
	assert.equal(bookedAfterBtn.getAttribute("aria-disabled"), "true");
	await bookedAfterBtn.dispatchEvent("click");
	assert.equal(harness.formCheckOut.value, "");
	assert.equal(harness.checkOutDisplay.textContent, "Add date");

	const unavailableEvents = harness
		.getPaq()
		.filter(
			(cmd) =>
				cmd[0] === "trackEvent" &&
				cmd[1] === "Demand" &&
				cmd[2] === "Unavailable Date Clicked",
		);
	assert.deepEqual(unavailableEvents, [
		["trackEvent", "Demand", "Unavailable Date Clicked", "1707:2026-12"],
		["trackEvent", "Demand", "Unavailable Date Clicked", "1707:2026-12"],
	]);

	// Funnel stage must remain at property_viewed (never advanced to quote_generated)
	const ovfAnalytics = harness.window.ovfAnalytics as {
		getFunnelStage: () => string;
	};
	assert.equal(ovfAnalytics.getFunnelStage(), "property_viewed");
});

test("#344 Contact Form emits Goal 1 (general) and Goal 2 (date-specific) with exact lead-time and stay-length bucket boundaries and zero raw dates", async () => {
	// Fixed date: 2026-11-27
	const cases: Array<{
		checkIn: string;
		checkOut: string;
		expectedSignal: string;
	}> = [
		// Lead-time boundaries: 0d, 7d -> 0-7d; 8d, 30d -> 8-30d; 31d, 90d -> 31-90d; 91d -> 90d+
		// Stay-length boundaries: 1n -> 1n; 2n, 3n -> 2-3n; 4n, 6n -> 4-6n; 7n, 13n -> 7-13n; 14n, 20n -> 14n+
		{
			checkIn: "2026-11-27",
			checkOut: "2026-11-28",
			expectedSignal: "2026-11:0-7d:1n",
		},
		{
			checkIn: "2026-12-04",
			checkOut: "2026-12-06",
			expectedSignal: "2026-12:0-7d:2-3n",
		},
		{
			checkIn: "2026-12-05",
			checkOut: "2026-12-08",
			expectedSignal: "2026-12:8-30d:2-3n",
		},
		{
			checkIn: "2026-12-27",
			checkOut: "2026-12-31",
			expectedSignal: "2026-12:8-30d:4-6n",
		},
		{
			checkIn: "2026-12-28",
			checkOut: "2027-01-03",
			expectedSignal: "2026-12:31-90d:4-6n",
		},
		{
			checkIn: "2027-02-25",
			checkOut: "2027-03-04",
			expectedSignal: "2027-02:31-90d:7-13n",
		},
		{
			checkIn: "2027-02-26",
			checkOut: "2027-03-11",
			expectedSignal: "2027-02:90d+:7-13n",
		},
		{
			checkIn: "2027-03-10",
			checkOut: "2027-03-24",
			expectedSignal: "2027-03:90d+:14n+",
		},
		{
			checkIn: "2027-04-01",
			checkOut: "2027-04-21",
			expectedSignal: "2027-04:90d+:14n+",
		},
	];

	const harness = await createContactHarness({
		lang: "en",
		fixedNow: "2026-11-27T12:00:00",
	});

	// 1. General Inquiry (no dates) -> Goal 1
	harness.fillValidBaseFields();
	harness.checkInInput.value = "";
	harness.checkOutInput.value = "";
	await harness.form.dispatchEvent("submit");

	assert.ok(
		harness.paq.some(
			(cmd) =>
				cmd[0] === "trackEvent" &&
				cmd[1] === "Contact Form" &&
				cmd[2] === "General Inquiry Submitted",
		),
	);
	assert.ok(harness.paq.some((cmd) => cmd[0] === "trackGoal" && cmd[1] === 1));

	// 2. Date-Specific Inquiries across every lead-time and stay-length bucket boundary -> Goal 2
	for (const tc of cases) {
		const beforeLen = harness.paq.length;
		harness.fillValidBaseFields();
		harness.checkInInput.value = tc.checkIn;
		harness.checkOutInput.value = tc.checkOut;
		await harness.form.dispatchEvent("submit");

		const emitted = harness.getPaq().slice(beforeLen);
		assert.deepEqual(emitted, [
			[
				"trackEvent",
				"Contact Form",
				"Date Inquiry Submitted",
				tc.expectedSignal,
			],
			["trackGoal", 2],
		]);
		const serialized = JSON.stringify(emitted);
		assert.ok(
			!serialized.includes(tc.checkIn) && !serialized.includes(tc.checkOut),
			`Raw dates (${tc.checkIn} / ${tc.checkOut}) must never appear in Matomo payload`,
		);
	}

	// Verify zero PII from form inputs ever reached window._paq
	const allSerialized = JSON.stringify(harness.paq);
	assert.ok(!allSerialized.includes("Maria Camila"));
	assert.ok(!allSerialized.includes("maria.camila@example.com"));
	assert.ok(!allSerialized.includes("3001234567"));
	assert.ok(!allSerialized.includes("Playa Salguero"));
});

test("#344 Contact Form emits locale-independent English machine codes for all client validation, server rejection, and network errors", async () => {
	const serverResponses: Array<
		| {
				ok: boolean;
				status: number;
				body: unknown;
		  }
		| { throwNetwork: true }
	> = [
		// HTTP 400 CAPTCHA error in Spanish
		{
			ok: false,
			status: 400,
			body: {
				success: false,
				error:
					"Respuesta incorrecta a la pregunta de verificación de seguridad.",
			},
		},
		// HTTP 400 Privacy consent error in Spanish
		{
			ok: false,
			status: 400,
			body: {
				success: false,
				error: "Debes autorizar el tratamiento de datos personales.",
			},
		},
		// HTTP 429 Rate limit in Japanese
		{
			ok: false,
			status: 429,
			body: {
				success: false,
				error: "リクエストが多すぎます。しばらくしてから再試行してください。",
			},
		},
		// HTTP 200 field validation rejection in Spanish
		{
			ok: false,
			status: 200,
			body: {
				success: false,
				error: "El correo electrónico proporcionado no es válido.",
			},
		},
		// HTTP 500 server failure
		{
			ok: false,
			status: 500,
			body: {
				success: false,
				error: "Error interno al enviar el correo electrónico.",
			},
		},
		// Network error (fetch rejects)
		{ throwNetwork: true },
	];

	let responseIndex = 0;
	const harness = await createContactHarness({
		lang: "es",
		fixedNow: "2026-11-27T12:00:00",
		postHandler: async () => {
			const next = serverResponses[responseIndex++];
			assert.ok(next, "Unexpected extra POST request");
			if ("throwNetwork" in next) {
				throw new Error("TypeError: Failed to fetch (localized stack)");
			}
			return {
				ok: next.ok,
				status: next.status,
				json: async () => next.body,
			};
		},
	});

	const startLen = harness.paq.length;

	// 1. Missing required fields -> validation_error
	harness.nameInput.value = "";
	harness.emailInput.value = "";
	harness.phoneInput.value = "";
	harness.messageInput.value = "";
	harness.captchaResponse.value = "";
	harness.privacyConsent.checked = true;
	await harness.form.dispatchEvent("submit");

	// 2. Missing Personal Data Authorization -> privacy_consent
	harness.fillValidBaseFields();
	harness.privacyConsent.checked = false;
	await harness.form.dispatchEvent("submit");

	// 3. Invalid date range (check-out <= check-in) -> invalid_date_range
	harness.fillValidBaseFields();
	harness.checkInInput.value = "2026-12-20";
	harness.checkOutInput.value = "2026-12-18";
	await harness.form.dispatchEvent("submit");

	// 4..9. Server rejections & network failure
	harness.checkInInput.value = "";
	harness.checkOutInput.value = "";
	for (let i = 0; i < serverResponses.length; i++) {
		harness.fillValidBaseFields();
		await harness.form.dispatchEvent("submit");
	}

	const emitted = harness.getPaq().slice(startLen);
	assert.deepEqual(emitted, [
		["trackEvent", "Contact Form", "Validation Error", "validation_error"],
		["trackEvent", "Contact Form", "Validation Error", "privacy_consent"],
		["trackEvent", "Contact Form", "Validation Error", "invalid_date_range"],
		["trackEvent", "Contact Form", "Submission Failure", "captcha_error"],
		["trackEvent", "Contact Form", "Submission Failure", "privacy_consent"],
		["trackEvent", "Contact Form", "Submission Failure", "rate_limited"],
		["trackEvent", "Contact Form", "Submission Failure", "validation_error"],
		["trackEvent", "Contact Form", "Submission Failure", "server_error"],
		["trackEvent", "Contact Form", "Submission Error", "network_error"],
	]);

	// Confirm zero localized strings or raw error text leaked into window._paq
	const serialized = JSON.stringify(emitted);
	for (const forbidden of [
		"Por favor",
		"Debes",
		"verificación",
		"リクエスト",
		"correo",
		"Failed to fetch",
		"2026-12-20",
		"2026-12-18",
	]) {
		assert.ok(
			!serialized.includes(forbidden),
			`Forbidden localized/raw fragment "${forbidden}" leaked into Matomo queue`,
		);
	}
});

test("#344 standalone main.js (without analytics.js) and minified dist/js/main.js emit identical Demand Signals safely", async () => {
	// Standalone main.js without window.ovfAnalytics must never throw and still records events via window._paq fallback
	const standalone = await createCalendarHarness({
		propertyId: "1707",
		fixedNow: "2026-11-27T12:00:00",
		loadAnalytics: false,
		blockedDates: ["2026-12-10"],
	});
	await standalone.btnNextMonth.dispatchEvent("click");
	await standalone.getDayButton("2026-12-10").dispatchEvent("click");
	assert.deepEqual(standalone.getPaq(), [
		["trackEvent", "Demand", "Calendar Month Browsed", "1707:2026-12"],
		["trackEvent", "Demand", "Unavailable Date Clicked", "1707:2026-12"],
	]);

	// Minified dist/js/analytics.js + dist/js/main.js parity check
	const minified = await createCalendarHarness({
		propertyId: "1707",
		fixedNow: "2026-11-27T12:00:00",
		loadAnalytics: true,
		useDist: true,
		blockedDates: ["2026-12-10"],
	});
	await minified.btnNextMonth.dispatchEvent("click");
	await minified.getDayButton("2026-12-10").dispatchEvent("click");
	assert.ok(
		minified.paq.some(
			(cmd) =>
				cmd[0] === "trackEvent" &&
				cmd[1] === "Demand" &&
				cmd[2] === "Calendar Month Browsed" &&
				cmd[3] === "1707:2026-12",
		),
	);
	assert.ok(
		minified.paq.some(
			(cmd) =>
				cmd[0] === "trackEvent" &&
				cmd[1] === "Demand" &&
				cmd[2] === "Unavailable Date Clicked" &&
				cmd[3] === "1707:2026-12",
		),
	);
});

test("#339 Contact Form classifies EN and DE HTTP 400 privacy-consent rejections as privacy_consent (never captcha_error)", async () => {
	const cases = [
		{
			status: 400,
			body: {
				success: false,
				error: "You must authorize personal data processing to continue.",
			},
			expectedCode: "privacy_consent",
		},
		{
			status: 400,
			body: {
				success: false,
				error:
					"Please accept the Personal Data Processing Policy before sending your inquiry.",
			},
			expectedCode: "privacy_consent",
		},
		{
			status: 400,
			body: {
				success: false,
				error:
					"Sie müssen der Verarbeitung personenbezogener Daten zustimmen, um fortzufahren.",
			},
			expectedCode: "privacy_consent",
		},
		{
			status: 400,
			body: {
				success: false,
				error:
					"Bitte akzeptieren Sie die Richtlinie zur Verarbeitung personenbezogener Daten, bevor Sie Ihre Anfrage senden.",
			},
			expectedCode: "privacy_consent",
		},
		{
			status: 400,
			body: {
				success: false,
				error:
					"Security check failed. Please refresh the calendar section and try again.",
			},
			expectedCode: "captcha_error",
		},
		{
			status: 400,
			body: {
				success: false,
				error: "Falsche Antwort auf die Sicherheitsfrage.",
			},
			expectedCode: "captcha_error",
		},
		{
			status: 400,
			body: {
				success: false,
				error: "Invalid request payload.",
			},
			expectedCode: "validation_error",
		},
	];

	let idx = 0;
	const harness = await createContactHarness({
		lang: "en",
		fixedNow: "2026-11-27T12:00:00",
		postHandler: async () => {
			const next = cases[idx++];
			assert.ok(next, "Unexpected extra POST request");
			return {
				ok: false,
				status: next.status,
				json: async () => next.body,
			};
		},
	});

	const startLen = harness.paq.length;
	for (let i = 0; i < cases.length; i++) {
		harness.fillValidBaseFields();
		await harness.form.dispatchEvent("submit");
	}

	const emitted = harness.getPaq().slice(startLen);
	assert.deepEqual(
		emitted,
		cases.map((tc) => [
			"trackEvent",
			"Contact Form",
			"Submission Failure",
			tc.expectedCode,
		]),
	);
});
