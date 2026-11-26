(() => {
	const nav = typeof navigator !== "undefined" ? navigator : null;
	const userAgent = nav?.userAgent || "";
	const isAutomatedCrawler =
		Boolean(nav?.webdriver) ||
		/bot|crawl|spider|slurp|googlebot|bingbot|yandex|baiduspider|duckduckbot|applebot|facebookexternalhit|twitterbot|whatsapp|linkedinbot|slackbot|discordbot|telegrambot|gptbot|oai-searchbot|chatgpt-user|claudebot|claude-searchbot|claude-web|anthropic-ai|perplexitybot|ccbot|headlesschrome|lighthouse|pagespeed|gtmetrix/i.test(
			userAgent,
		);
	if (isAutomatedCrawler) return;

	const supportedLangs = ["es", "fr", "it", "de", "ja"];
	const currentHref = window.location.href;
	const pathName = window.location.pathname;

	// Only run detection on English pages (index.html or root) or any 404 page
	const is404Page = pathName.includes("404") || pathName.includes("404.html");
	const isEnglishPage =
		pathName.endsWith("index.html") ||
		pathName.endsWith("/") ||
		(!pathName.includes(".html") &&
			!supportedLangs.some((l) => pathName.endsWith(`${l}.html`)));

	if (!isEnglishPage && !is404Page) return;

	// Check if user has a stored preference
	let storedPref = null;
	try {
		storedPref =
			typeof localStorage !== "undefined"
				? localStorage.getItem("lang-pref")
				: null;
	} catch (_) {
		storedPref = null;
	}
	if (storedPref === "en") return;

	if (storedPref && supportedLangs.includes(storedPref)) {
		redirectTo(storedPref);
		return;
	}

	// Detect browser language
	const rawBrowserLang = nav?.language || nav?.userLanguage || "";
	const browserLang = rawBrowserLang.toLowerCase().split("-")[0];

	if (supportedLangs.includes(browserLang)) {
		try {
			if (typeof localStorage !== "undefined") {
				localStorage.setItem("lang-pref", browserLang);
			}
		} catch (_) {
			// Ignore storage failures in restricted contexts
		}
		redirectTo(browserLang);
	}

	function redirectTo(lang) {
		const search = window.location.search || "";
		const hash = window.location.hash || "";
		const base = currentHref.split("?")[0].split("#")[0];
		let newHref;
		if (is404Page) {
			newHref = `${window.location.origin}/404/${lang}.html`;
		} else if (pathName.endsWith("index.html")) {
			newHref = `${base.replace(/index\.html$/, `${lang}.html`)}${search}${hash}`;
		} else if (pathName.endsWith("/")) {
			newHref = `${base}${lang}.html${search}${hash}`;
		} else {
			// Handle cases where it's just the folder name like /Oceanview1707
			const slash = base.endsWith("/") ? "" : "/";
			newHref = `${base}${slash}${lang}.html${search}${hash}`;
		}

		if (newHref && newHref !== currentHref) {
			window.location.replace(newHref);
		}
	}
})();
