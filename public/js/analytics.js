(() => {
	const TRACKER_URL = "https://stats.fractalserver.com/";
	const SITE_ID = "2";

	const GOALS = Object.freeze({
		CONTACT_GENERAL: 1,
		CONTACT_DATED: 2,
		AIRBNB_REDIRECT: 3,
		WHATSAPP_CONTACT: 4,
		DIRECT_RESERVATION_CONFIRMED: 5,
		PENDING_RESERVATION_CREATED: 6,
		GUEST_REGISTRY_COMPLETED: 7,
	});

	const DIMENSIONS = Object.freeze({
		LOCALE: 1,
		PAGE_TYPE: 2,
		PROPERTY: 3,
		LANGUAGE_ACQUISITION: 4,
		FUNNEL_STAGE: 5,
	});

	const FUNNEL_STAGES = Object.freeze({
		discovery: 1,
		property_viewed: 2,
		quote_generated: 3,
		hold_created: 4,
		payment_brick_ready: 5,
		payment_submitted: 6,
		confirmed: 7,
	});

	const ALLOWED_QUERY_PARAMS = Object.freeze([
		"utm_source",
		"utm_medium",
		"utm_campaign",
		"utm_content",
		"utm_term",
	]);

	const ALLOWED_QUERY_PREFIXES = Object.freeze(["mtm_"]);
	const SUPPORTED_LOCALES = Object.freeze(["en", "es", "fr", "it", "de", "ja"]);
	const LANGUAGE_ACQUISITION_MODES = Object.freeze({
		DIRECT_LOCALE: "direct_locale",
		AUTO_REDIRECTED: "auto_redirected",
		MANUAL_SWITCHED: "manual_switched",
	});

	const ALLOWED_QUERY_SET = new Set(ALLOWED_QUERY_PARAMS);
	const VALID_GOAL_IDS = new Set(Object.values(GOALS));
	const VALID_LANGUAGE_MODES = new Set(
		Object.values(LANGUAGE_ACQUISITION_MODES),
	);

	const getPaq = () => {
		window._paq = window._paq || [];
		return window._paq;
	};

	const isAllowedQueryParam = (key) => {
		if (typeof key !== "string" || key.length === 0) {
			return false;
		}
		const lower = key.toLowerCase();
		if (ALLOWED_QUERY_SET.has(lower)) {
			return true;
		}
		return ALLOWED_QUERY_PREFIXES.some(
			(prefix) => lower.startsWith(prefix) && lower.length > prefix.length,
		);
	};

	const buildFilteredQuery = (searchParams) => {
		const filtered = new URLSearchParams();
		searchParams.forEach((value, key) => {
			if (isAllowedQueryParam(key)) {
				filtered.append(key.toLowerCase(), value);
			}
		});
		return filtered.toString();
	};

	const sanitizeUrl = (rawUrl, baseUrl) => {
		if (typeof rawUrl !== "string") {
			return "";
		}
		const trimmed = rawUrl.trim();
		if (!trimmed) {
			return "";
		}

		// Relative path without baseUrl: sanitize pathname + allowlisted query, strip fragment
		if (!baseUrl && trimmed.startsWith("/") && !trimmed.startsWith("//")) {
			try {
				const parsed = new URL(trimmed, "https://ovf.invalid");
				const query = buildFilteredQuery(parsed.searchParams);
				return `${parsed.pathname}${query ? `?${query}` : ""}`;
			} catch (_) {
				const stripped = trimmed.split("#")[0].split("?")[0].trim();
				return stripped.startsWith("/") ? stripped : "";
			}
		}

		try {
			const parsed = baseUrl ? new URL(trimmed, baseUrl) : new URL(trimmed);
			if (parsed.protocol !== "http:" && parsed.protocol !== "https:") {
				return "";
			}
			const query = buildFilteredQuery(parsed.searchParams);
			return `${parsed.origin}${parsed.pathname}${query ? `?${query}` : ""}`;
		} catch (_) {
			const stripped = trimmed.split("#")[0].split("?")[0].trim();
			if (!stripped) {
				return "";
			}
			try {
				const fallbackParsed = baseUrl
					? new URL(stripped, baseUrl)
					: new URL(stripped);
				if (
					fallbackParsed.protocol !== "http:" &&
					fallbackParsed.protocol !== "https:"
				) {
					return "";
				}
				return `${fallbackParsed.origin}${fallbackParsed.pathname}`;
			} catch (_) {
				return "";
			}
		}
	};

	const getRawCurrentUrl = () => {
		const loc = window.location;
		if (!loc) {
			return "";
		}
		if (typeof loc.href === "string" && loc.href.trim() !== "") {
			return loc.href;
		}
		const origin =
			typeof loc.origin === "string" && loc.origin
				? loc.origin
				: "https://www.oceanviewflats.com";
		const pathname = typeof loc.pathname === "string" ? loc.pathname : "/";
		const search = typeof loc.search === "string" ? loc.search : "";
		const hash = typeof loc.hash === "string" ? loc.hash : "";
		return `${origin}${pathname}${search}${hash}`;
	};

	const getSanitizedPath = (sanitizedUrl) => {
		if (sanitizedUrl) {
			try {
				const parsed = new URL(sanitizedUrl);
				return `${parsed.pathname}${parsed.search}`;
			} catch (_) {
				// Fall through to pathname fallback
			}
		}
		const rawPath =
			typeof window.location?.pathname === "string" && window.location.pathname
				? window.location.pathname
				: "/404";
		return rawPath.split("#")[0].split("?")[0] || "/404";
	};

	const resolveContext = () => {
		const pageConfig =
			typeof window.getPageConfig === "function"
				? window.getPageConfig() || {}
				: {};
		const meta =
			pageConfig.analytics && typeof pageConfig.analytics === "object"
				? pageConfig.analytics
				: {};

		const docLang =
			typeof document !== "undefined" ? document.documentElement?.lang : "";
		const lang = SUPPORTED_LOCALES.includes(pageConfig.lang)
			? pageConfig.lang
			: SUPPORTED_LOCALES.includes(docLang)
				? docLang
				: "en";

		const pageId =
			typeof pageConfig.pageId === "string" && pageConfig.pageId
				? pageConfig.pageId
				: "unknown";
		const pageType =
			typeof meta.pageType === "string" && meta.pageType
				? meta.pageType
				: "unknown";
		const propertyId =
			meta.propertyId === "1707" ||
			meta.propertyId === "1606" ||
			meta.propertyId === "portfolio"
				? meta.propertyId
				: "portfolio";
		const enableEngagementTracking = Boolean(meta.enableEngagementTracking);

		const resolved = {
			pageId,
			lang,
			pageType,
			propertyId,
			enableEngagementTracking,
		};

		if (typeof meta.guideSlug === "string" && meta.guideSlug) {
			resolved.guideSlug = meta.guideSlug;
		}

		return resolved;
	};

	const context = resolveContext();
	let currentFunnelStage = null;
	let currentFunnelRank = 0;

	const advanceFunnelStage = (stage) => {
		if (typeof stage !== "string" || !Object.hasOwn(FUNNEL_STAGES, stage)) {
			return false;
		}
		const nextRank = FUNNEL_STAGES[stage];
		if (nextRank <= currentFunnelRank) {
			return false;
		}
		currentFunnelStage = stage;
		currentFunnelRank = nextRank;
		getPaq().push(["setCustomDimension", DIMENSIONS.FUNNEL_STAGE, stage]);
		return true;
	};

	const setLanguageAcquisition = (mode) => {
		if (typeof mode !== "string" || !VALID_LANGUAGE_MODES.has(mode)) {
			return false;
		}
		getPaq().push([
			"setCustomDimension",
			DIMENSIONS.LANGUAGE_ACQUISITION,
			mode,
		]);
		return true;
	};

	const setCustomDimension = (dimensionId, value) => {
		if (
			!Number.isInteger(dimensionId) ||
			dimensionId < 1 ||
			typeof value !== "string" ||
			value.length === 0
		) {
			return false;
		}
		if (dimensionId === DIMENSIONS.FUNNEL_STAGE) {
			return advanceFunnelStage(value);
		}
		getPaq().push(["setCustomDimension", dimensionId, value]);
		return true;
	};

	const trackEvent = (category, action, name, value) => {
		if (
			typeof category !== "string" ||
			category.length === 0 ||
			typeof action !== "string" ||
			action.length === 0
		) {
			return false;
		}
		const cmd = ["trackEvent", category, action];
		const hasNumericValue = typeof value === "number" && Number.isFinite(value);
		const hasName =
			name !== undefined && name !== null && String(name).length > 0;
		if (hasNumericValue) {
			cmd.push(hasName ? String(name) : "");
			cmd.push(value);
		} else if (hasName) {
			cmd.push(String(name));
		}
		getPaq().push(cmd);
		return true;
	};

	const trackGoal = (goalId, customRevenue) => {
		let resolvedId = goalId;
		if (typeof goalId === "string" && Object.hasOwn(GOALS, goalId)) {
			resolvedId = GOALS[goalId];
		}
		if (!Number.isInteger(resolvedId) || !VALID_GOAL_IDS.has(resolvedId)) {
			return false;
		}
		if (
			typeof customRevenue === "number" &&
			Number.isFinite(customRevenue) &&
			customRevenue >= 0
		) {
			getPaq().push(["trackGoal", resolvedId, customRevenue]);
		} else {
			getPaq().push(["trackGoal", resolvedId]);
		}
		return true;
	};

	const getContext = () => ({ ...context });
	const getFunnelStage = () => currentFunnelStage;

	const loadMatomoScript = () => {
		if (
			typeof document === "undefined" ||
			typeof document.createElement !== "function"
		) {
			return;
		}
		const scriptEl = document.createElement("script");
		scriptEl.async = true;
		scriptEl.src = `${TRACKER_URL}matomo.js`;
		const scripts =
			typeof document.getElementsByTagName === "function"
				? document.getElementsByTagName("script")
				: null;
		const firstScript = scripts?.[0];
		if (
			firstScript?.parentNode &&
			typeof firstScript.parentNode.insertBefore === "function"
		) {
			firstScript.parentNode.insertBefore(scriptEl, firstScript);
		} else if (
			document.head &&
			typeof document.head.appendChild === "function"
		) {
			document.head.appendChild(scriptEl);
		}
	};

	const init = () => {
		const paq = getPaq();
		const sanitizedUrl = sanitizeUrl(getRawCurrentUrl());
		const rawReferrer =
			typeof document !== "undefined" && typeof document.referrer === "string"
				? document.referrer
				: "";
		const sanitizedReferrer = sanitizeUrl(rawReferrer);

		paq.push(["disableCookies"]);
		if (sanitizedUrl) {
			paq.push(["setCustomUrl", sanitizedUrl]);
		}
		paq.push(["setReferrerUrl", sanitizedReferrer]);

		// Initial Action-scoped Custom Dimensions (1-3)
		paq.push(["setCustomDimension", DIMENSIONS.LOCALE, context.lang]);
		paq.push(["setCustomDimension", DIMENSIONS.PAGE_TYPE, context.pageType]);
		paq.push(["setCustomDimension", DIMENSIONS.PROPERTY, context.propertyId]);

		// Initial Visit-scoped Custom Dimensions (4-5)
		setLanguageAcquisition(LANGUAGE_ACQUISITION_MODES.DIRECT_LOCALE);
		const initialStage =
			context.pageType === "property" ? "property_viewed" : "discovery";
		advanceFunnelStage(initialStage);

		// Standard Matomo 404 document title before trackPageView
		if (context.pageType === "404") {
			const sanitizedPath = getSanitizedPath(sanitizedUrl);
			paq.push([
				"setDocumentTitle",
				`404/URL = ${encodeURIComponent(sanitizedPath)}/From = ${encodeURIComponent(sanitizedReferrer)}`,
			]);
		}

		paq.push(["trackPageView"]);
		paq.push(["enableLinkTracking"]);
		paq.push(["setTrackerUrl", `${TRACKER_URL}matomo.php`]);
		paq.push(["setSiteId", SITE_ID]);

		loadMatomoScript();
	};

	window.ovfAnalytics = Object.freeze({
		TRACKER_URL,
		SITE_ID,
		GOALS,
		DIMENSIONS,
		FUNNEL_STAGES,
		ALLOWED_QUERY_PARAMS,
		ALLOWED_QUERY_PREFIXES,
		LANGUAGE_ACQUISITION_MODES,
		trackEvent,
		trackGoal,
		setCustomDimension,
		advanceFunnelStage,
		setLanguageAcquisition,
		sanitizeUrl,
		getContext,
		getFunnelStage,
	});

	init();
})();
