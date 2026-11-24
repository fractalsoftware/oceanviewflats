(function () {
	const supportedLangs = ["es", "fr", "it", "de", "ja"];
	const currentHref = window.location.href;
	const pathName = window.location.pathname;

	// Only run detection on English pages (index.html or root) or any 404 page
	const is404Page = pathName.includes("404") || pathName.includes("404.html");
	const isEnglishPage =
		pathName.endsWith("index.html") ||
		pathName.endsWith("/") ||
		(!pathName.includes(".html") &&
			!supportedLangs.some((l) => pathName.endsWith(l + ".html")));

	if (!isEnglishPage && !is404Page) return;

	// Check if user has a stored preference
	const storedPref = localStorage.getItem("lang-pref");
	if (storedPref === "en") return;

	if (storedPref && supportedLangs.includes(storedPref)) {
		redirectTo(storedPref);
		return;
	}

	// Detect browser language
	const browserLang = (navigator.language || navigator.userLanguage).split(
		"-",
	)[0];

	if (supportedLangs.includes(browserLang)) {
		localStorage.setItem("lang-pref", browserLang);
		redirectTo(browserLang);
	}

	function redirectTo(lang) {
		let newHref;
		if (is404Page) {
			newHref = window.location.origin + "/404/" + lang + ".html";
		} else if (pathName.endsWith("index.html")) {
			newHref = currentHref.replace("index.html", lang + ".html");
		} else if (pathName.endsWith("/")) {
			newHref = currentHref + lang + ".html";
		} else {
			// Handle cases where it's just the folder name like /Oceanview1707
			const base = currentHref.split("?")[0].split("#")[0];
			newHref = base + (base.endsWith("/") ? "" : "/") + lang + ".html";
		}

		if (newHref && newHref !== currentHref) {
			window.location.replace(newHref);
		}
	}
})();
