import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import test from "node:test";
import vm from "node:vm";
import {
	BASE_URL,
	getCanonicalUrl,
	LANGUAGES,
	pages,
} from "../../src/config/pages";

const DIST_DIR = path.join(process.cwd(), "dist");
const PUBLIC_DIR = path.join(process.cwd(), "public");

const PUBLIC_PAGES = pages.filter((p) => !p.noIndex);
const NOINDEX_PAGES = pages.filter((p) => p.noIndex);

test("Apache .htaccess enforces multipart hardening first, single-hop index.html stripping via THE_REQUEST, and permanent HTTPS + www canonicalization (#298)", () => {
	const publicHtaccess = fs.readFileSync(
		path.join(PUBLIC_DIR, ".htaccess"),
		"utf-8",
	);
	const distHtaccess = fs.readFileSync(
		path.join(DIST_DIR, ".htaccess"),
		"utf-8",
	);
	assert.equal(
		distHtaccess,
		publicHtaccess,
		"dist/.htaccess must match public/.htaccess",
	);

	// Preserve single <IfModule mod_headers.c> security header block intact
	const headersBlocks = distHtaccess.match(
		/<IfModule\s+mod_headers\.c>[\s\S]*?<\/IfModule>/gi,
	);
	assert.equal(
		headersBlocks?.length,
		1,
		"public/.htaccess must preserve exactly one <IfModule mod_headers.c> block",
	);

	const rewriteMatch = distHtaccess.match(
		/<IfModule\s+mod_rewrite\.c>([\s\S]*?)<\/IfModule>/i,
	);
	assert.ok(
		rewriteMatch,
		"public/.htaccess must define a <IfModule mod_rewrite.c> block",
	);
	const rewriteBlock = rewriteMatch[1];

	assert.match(
		rewriteBlock,
		/^\s*RewriteEngine\s+On\s*$/im,
		"mod_rewrite block must enable RewriteEngine On",
	);

	const multipartIdx = rewriteBlock.search(
		/RewriteCond\s+%\{CONTENT_TYPE\}\s+\^multipart\/form-data\s+\[NC\]/i,
	);
	const multipartRuleIdx = rewriteBlock.search(
		/RewriteRule\s+\^\s+-\s+\[F,L\]/i,
	);
	const indexStripCondMatch = rewriteBlock.match(
		/RewriteCond\s+%\{THE_REQUEST\}\s+(\S+)\s+\[NC\]/i,
	);
	const indexStripIdx = indexStripCondMatch?.index ?? -1;
	const canonicalHostIdx = rewriteBlock.search(
		/RewriteCond\s+%\{HTTPS\}\s+!=on\s+\[OR\]/i,
	);

	assert.ok(multipartIdx >= 0, "multipart/form-data condition must exist");
	assert.ok(
		multipartRuleIdx > multipartIdx,
		"multipart/form-data must reject with [F,L]",
	);
	assert.ok(
		indexStripIdx > multipartRuleIdx,
		"index.html canonical strip must run AFTER multipart/form-data [F,L] rejection",
	);
	assert.ok(
		canonicalHostIdx > multipartRuleIdx,
		"HTTPS + www canonicalization must run AFTER multipart/form-data [F,L] rejection",
	);

	assert.match(
		rewriteBlock,
		/RewriteRule\s+\^\s+https:\/\/www\.oceanviewflats\.com\/%1\s+\[R=301,L\]/i,
		"explicit index.html requests must permanently 301-redirect to canonical trailing-slash https://www.oceanviewflats.com/%1",
	);
	assert.match(
		rewriteBlock,
		/RewriteCond\s+%\{HTTPS\}\s+!=on\s+\[OR\]\s*\n\s*RewriteCond\s+%\{HTTP_HOST\}\s+!\^www\\\.oceanviewflats\\\.com\$\s+\[NC\]\s*\n\s*RewriteRule\s+\^\(\.\*\)\$\s+https:\/\/www\.oceanviewflats\.com\/\$1\s+\[R=301,L\]/i,
		"HTTP and apex host requests must permanently 301-redirect to https://www.oceanviewflats.com/$1",
	);

	// Verify THE_REQUEST regex behavior (prevents DirectoryIndex loops & query-param false positives)
	assert.ok(indexStripCondMatch);
	const theRequestRegex = new RegExp(indexStripCondMatch[1], "i");

	const rootIndexMatch = "GET /index.html HTTP/1.1".match(theRequestRegex);
	assert.ok(rootIndexMatch, "GET /index.html must match strip rule");
	assert.equal(rootIndexMatch[1] ?? "", "");

	const dirIndexMatch = "GET /Oceanview1707/index.html HTTP/1.1".match(
		theRequestRegex,
	);
	assert.ok(dirIndexMatch, "GET /Oceanview1707/index.html must match");
	assert.equal(dirIndexMatch[1], "Oceanview1707/");

	const dirIndexQueryMatch =
		"GET /Oceanview1606/index.html?utm_source=test HTTP/1.1".match(
			theRequestRegex,
		);
	assert.ok(dirIndexQueryMatch, "GET /Oceanview1606/index.html?... must match");
	assert.equal(dirIndexQueryMatch[1], "Oceanview1606/");

	assert.equal(
		"GET / HTTP/1.1".match(theRequestRegex),
		null,
		"Canonical root GET / must NOT match index.html strip rule",
	);
	assert.equal(
		"GET /Oceanview1707/ HTTP/1.1".match(theRequestRegex),
		null,
		"Canonical directory GET /Oceanview1707/ must NOT match index.html strip rule",
	);
	assert.equal(
		"GET /registry/?ref=/index.html HTTP/1.1".match(theRequestRegex),
		null,
		"Query strings containing index.html must NOT trigger strip rule",
	);
});

test("Public pages emit canonical trailing-slash URLs, og:url, reciprocal hreflang + x-default, no noindex, and zero index.html internal links across all 6 locales (#298)", () => {
	assert.deepEqual(
		PUBLIC_PAGES.map((p) => p.id),
		["home", "Oceanview1707", "Oceanview1606", "contact", "privacy"],
	);

	for (const page of PUBLIC_PAGES) {
		const expectedDefaultUrl = getCanonicalUrl(BASE_URL, page.path, "en");
		assert.ok(
			expectedDefaultUrl.endsWith("/"),
			`English canonical URL for ${page.id} must end with a trailing slash (got ${expectedDefaultUrl})`,
		);
		assert.ok(
			!expectedDefaultUrl.includes("index.html"),
			`English canonical URL for ${page.id} must not include index.html`,
		);

		for (const lang of LANGUAGES) {
			const filePath = path.join(DIST_DIR, page.filename(lang));
			const html = fs.readFileSync(filePath, "utf-8");
			const expectedUrl = getCanonicalUrl(BASE_URL, page.path, lang);

			assert.ok(
				html.includes(`<link rel="canonical" href="${expectedUrl}">`),
				`${page.filename(lang)} must emit exact canonical URL ${expectedUrl}`,
			);
			assert.ok(
				html.includes(`<meta property="og:url" content="${expectedUrl}">`),
				`${page.filename(lang)} must emit exact og:url ${expectedUrl}`,
			);
			assert.ok(
				!html.includes('<meta name="robots" content="noindex'),
				`Public page ${page.filename(lang)} must NOT emit a noindex robots tag`,
			);

			// Reciprocal hreflang cluster across all 6 locales + x-default
			for (const altLang of LANGUAGES) {
				const altUrl = getCanonicalUrl(BASE_URL, page.path, altLang);
				assert.ok(
					html.includes(
						`<link rel="alternate" hreflang="${altLang}" href="${altUrl}" />`,
					),
					`${page.filename(lang)} must emit hreflang="${altLang}" pointing to ${altUrl}`,
				);
			}
			assert.ok(
				html.includes(
					`<link rel="alternate" hreflang="x-default" href="${expectedDefaultUrl}" />`,
				),
				`${page.filename(lang)} must emit hreflang="x-default" pointing to ${expectedDefaultUrl}`,
			);

			// Zero internal href="...index.html" links anywhere on public pages
			assert.doesNotMatch(
				html,
				/href="[^"]*index\.html(?:\?[^"]*)?"/i,
				`${page.filename(lang)} must not emit any internal href pointing to index.html`,
			);

			// Language switcher on non-English pages links English to "./"
			if (lang !== "en") {
				assert.ok(
					html.includes('href="./"'),
					`Localized page ${page.filename(lang)} language switcher must link English to "./"`,
				);
			}

			// lang-detect.js must load with defer
			assert.match(
				html,
				/<script\s+src="[^"]*js\/lang-detect\.js"\s+defer><\/script>/,
				`${page.filename(lang)} must load lang-detect.js with defer`,
			);
		}
	}
});

test("Non-public transactional and error pages emit noindex, nofollow, omit hreflang clusters, and are excluded from sitemap.xml (#298)", () => {
	assert.deepEqual(
		NOINDEX_PAGES.map((p) => p.id),
		[
			"registry",
			"guide",
			"404",
			"booking-success",
			"booking-failure",
			"booking-pending",
		],
	);

	const sitemapXml = fs.readFileSync(
		path.join(DIST_DIR, "sitemap.xml"),
		"utf-8",
	);

	for (const page of NOINDEX_PAGES) {
		for (const lang of LANGUAGES) {
			const filePath = path.join(DIST_DIR, page.filename(lang));
			const html = fs.readFileSync(filePath, "utf-8");

			assert.ok(
				html.includes('<meta name="robots" content="noindex, nofollow" />'),
				`Non-public page ${page.filename(lang)} must emit <meta name="robots" content="noindex, nofollow" />`,
			);
			assert.ok(
				!html.includes('rel="alternate" hreflang='),
				`Non-public page ${page.filename(lang)} must omit hreflang alternate clusters`,
			);
			assert.match(
				html,
				/<script\s+src="[^"]*js\/lang-detect\.js"\s+defer><\/script>/,
				`${page.filename(lang)} must load lang-detect.js with defer`,
			);

			const canonicalUrl = getCanonicalUrl(BASE_URL, page.path, lang);
			assert.ok(
				!sitemapXml.includes(`<loc>${canonicalUrl}</loc>`),
				`sitemap.xml must exclude noIndex page ${canonicalUrl}`,
			);

			// Regression (#298): Localized 404 pages must switch English cleanly to /404.html instead of /404/ (403 Forbidden)
			if (page.id === "404" && lang !== "en") {
				const dropdownMatch = html.match(
					/<div[^>]*id="lang-dropdown"[^>]*>([\s\S]*?)<\/ul>/i,
				);
				assert.ok(
					dropdownMatch,
					`${page.filename(lang)} must render #lang-dropdown`,
				);
				assert.ok(
					dropdownMatch[1].includes('href="/404.html"'),
					`Localized 404 page ${page.filename(lang)} must link English to "/404.html"`,
				);
				assert.ok(
					!dropdownMatch[1].includes('href="./"'),
					`Localized 404 page ${page.filename(lang)} must NOT link English to "./" (resolves to /404/)`,
				);
			}
		}
	}
});

test("Generated XML sitemap declares xhtml namespace, lists only indexable canonical URLs, and includes reciprocal xhtml:link alternates across all 6 locales + x-default (#298)", () => {
	const sitemapXml = fs.readFileSync(
		path.join(DIST_DIR, "sitemap.xml"),
		"utf-8",
	);

	assert.ok(
		sitemapXml.includes(
			'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">',
		),
		"sitemap.xml must declare both sitemap and xhtml XML namespaces",
	);

	const urlBlocks = [...sitemapXml.matchAll(/<url>([\s\S]*?)<\/url>/g)].map(
		(m) => m[1],
	);
	assert.equal(
		urlBlocks.length,
		PUBLIC_PAGES.length * LANGUAGES.length,
		"sitemap.xml must contain exactly 30 <url> entries (5 public pages × 6 locales)",
	);

	assert.ok(
		!sitemapXml.includes("index.html"),
		"sitemap.xml must never emit index.html URLs",
	);

	for (const forbiddenSegment of [
		"/registry",
		"/guide",
		"/booking-success",
		"/booking-failure",
		"/booking-pending",
		"/404",
	]) {
		assert.ok(
			!sitemapXml.includes(forbiddenSegment),
			`sitemap.xml must not reference excluded route ${forbiddenSegment}`,
		);
	}

	const blockByLoc = new Map<string, string>();
	for (const block of urlBlocks) {
		const locMatch = block.match(/<loc>(.*?)<\/loc>/);
		assert.ok(locMatch, "Every <url> entry must have a <loc>");
		blockByLoc.set(locMatch[1], block);
	}

	for (const page of PUBLIC_PAGES) {
		const expectedDefaultUrl = getCanonicalUrl(BASE_URL, page.path, "en");
		for (const lang of LANGUAGES) {
			const expectedLoc = getCanonicalUrl(BASE_URL, page.path, lang);
			const block = blockByLoc.get(expectedLoc);
			assert.ok(block, `sitemap.xml must contain <loc>${expectedLoc}</loc>`);

			const xhtmlLinks = [...block.matchAll(/<xhtml:link\b[^>]*\/>/g)];
			assert.equal(
				xhtmlLinks.length,
				LANGUAGES.length + 1,
				`sitemap <url> ${expectedLoc} must emit 7 reciprocal <xhtml:link> tags (6 locales + x-default)`,
			);

			for (const altLang of LANGUAGES) {
				const altUrl = getCanonicalUrl(BASE_URL, page.path, altLang);
				assert.ok(
					block.includes(
						`<xhtml:link rel="alternate" hreflang="${altLang}" href="${altUrl}" />`,
					),
					`sitemap <url> ${expectedLoc} must include xhtml:link for ${altLang} -> ${altUrl}`,
				);
			}
			assert.ok(
				block.includes(
					`<xhtml:link rel="alternate" hreflang="x-default" href="${expectedDefaultUrl}" />`,
				),
				`sitemap <url> ${expectedLoc} must include xhtml:link for x-default -> ${expectedDefaultUrl}`,
			);
		}
	}
});

test("robots.txt explicitly allows modern search/AI crawlers and disallows private, API, checkout, and 404 routes (#298)", () => {
	const robotsTxt = fs.readFileSync(path.join(DIST_DIR, "robots.txt"), "utf-8");

	const requiredBots = [
		"*",
		"CCBot",
		"ChatGPT-User",
		"GPTBot",
		"OAI-SearchBot",
		"Google-Extended",
		"Anthropic-ai",
		"Claude-Web",
		"ClaudeBot",
		"Claude-SearchBot",
		"PerplexityBot",
		"Applebot-Extended",
	];
	for (const bot of requiredBots) {
		assert.ok(
			robotsTxt.includes(`User-agent: ${bot}`),
			`robots.txt must declare User-agent: ${bot}`,
		);
	}

	const requiredDisallows = [
		"/api/",
		"/registry/",
		"/guide/",
		"/booking-success/",
		"/booking-failure/",
		"/booking-pending/",
		"/404.html",
		"/404/",
	];

	// Parse robots.txt into rule groups separated by blank lines so both "*" and AI bot groups enforce Disallow rules
	const groups = robotsTxt
		.trim()
		.split(/\n\s*\n/)
		.map((g) => g.trim())
		.filter((g) => g.includes("User-agent:"));

	assert.ok(
		groups.length >= 2,
		"robots.txt must have separate wildcard and explicit AI crawler groups",
	);

	for (const group of groups) {
		assert.ok(
			group.includes("Allow: /"),
			`Every User-agent group must explicitly Allow: /:\n${group}`,
		);
		for (const disallowedPath of requiredDisallows) {
			assert.ok(
				group.includes(`Disallow: ${disallowedPath}`),
				`Every User-agent group must Disallow: ${disallowedPath}:\n${group}`,
			);
		}
	}

	assert.ok(
		robotsTxt.includes("Sitemap: https://www.oceanviewflats.com/sitemap.xml"),
		"robots.txt must point Sitemap to https://www.oceanviewflats.com/sitemap.xml",
	);
});

test("lang-detect.js blocks automatic redirection for search/AI crawlers and webdriver while preserving human locale redirection (#298)", () => {
	const scriptPaths = [
		path.join(PUBLIC_DIR, "js", "lang-detect.js"),
		path.join(DIST_DIR, "js", "lang-detect.js"),
	];

	interface RunLangDetectOptions {
		scriptPath: string;
		href: string;
		userAgent: string;
		language?: string;
		webdriver?: boolean;
		storedPref?: string | null;
	}

	const runLangDetect = ({
		scriptPath,
		href,
		userAgent,
		language,
		webdriver = false,
		storedPref = null,
	}: RunLangDetectOptions) => {
		const scriptSource = fs.readFileSync(scriptPath, "utf-8");
		const parsedUrl = new URL(href);
		const storage = new Map<string, string>();
		if (storedPref !== null) {
			storage.set("lang-pref", storedPref);
		}
		let replacedHref: string | null = null;

		const sandbox = {
			window: {
				location: {
					href: parsedUrl.href,
					origin: parsedUrl.origin,
					pathname: parsedUrl.pathname,
					search: parsedUrl.search,
					hash: parsedUrl.hash,
					replace: (nextHref: string) => {
						replacedHref = nextHref;
					},
				},
			},
			navigator: {
				userAgent,
				language,
				webdriver,
			},
			localStorage: {
				getItem: (key: string) => storage.get(key) ?? null,
				setItem: (key: string, value: string) => {
					storage.set(key, value);
				},
			},
		};

		vm.runInNewContext(scriptSource, sandbox);
		return {
			replacedHref,
			storedLangPref: storage.get("lang-pref") ?? null,
		};
	};

	const crawlerUserAgents = [
		"Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)",
		"Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)",
		"Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; PerplexityBot/1.0; +https://perplexity.ai/perplexitybot)",
		"Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)",
		"Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; OAI-SearchBot/1.0; +https://openai.com/searchbot)",
		"Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15 (Applebot/0.1; +http://www.apple.com/go/applebot)",
		"Mozilla/5.0 (compatible; Claude-SearchBot/1.0; +https://anthropic.com)",
		"Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)",
	];

	const humanUserAgent =
		"Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36";

	for (const scriptPath of scriptPaths) {
		// 1. All crawlers stay on canonical English URL even with Spanish Accept-Language or stored pref
		for (const ua of crawlerUserAgents) {
			const res = runLangDetect({
				scriptPath,
				href: "https://www.oceanviewflats.com/Oceanview1707/",
				userAgent: ua,
				language: "es-CO",
				storedPref: "fr",
			});
			assert.equal(
				res.replacedHref,
				null,
				`Crawler UA "${ua}" must never be redirected (${path.basename(scriptPath)})`,
			);
		}

		// 2. Automated webdriver sessions are never redirected
		const webdriverRes = runLangDetect({
			scriptPath,
			href: "https://www.oceanviewflats.com/",
			userAgent: humanUserAgent,
			language: "es-ES",
			webdriver: true,
		});
		assert.equal(
			webdriverRes.replacedHref,
			null,
			"navigator.webdriver === true must not redirect",
		);

		// 3. Normal human browser with Spanish language redirects from English trailing-slash URL
		const humanEsRoot = runLangDetect({
			scriptPath,
			href: "https://www.oceanviewflats.com/",
			userAgent: humanUserAgent,
			language: "es-CO",
		});
		assert.equal(
			humanEsRoot.replacedHref,
			"https://www.oceanviewflats.com/es.html",
		);
		assert.equal(humanEsRoot.storedLangPref, "es");

		// 4. Normal human browser preserves query string and hash when redirecting from directory URL
		const humanFrProp = runLangDetect({
			scriptPath,
			href: "https://www.oceanviewflats.com/Oceanview1707/?utm_source=ig#booking",
			userAgent: humanUserAgent,
			language: "fr-FR",
		});
		assert.equal(
			humanFrProp.replacedHref,
			"https://www.oceanviewflats.com/Oceanview1707/fr.html?utm_source=ig#booking",
		);
		assert.equal(humanFrProp.storedLangPref, "fr");

		// 5. Explicit English preference stays on English page
		const humanEnPref = runLangDetect({
			scriptPath,
			href: "https://www.oceanviewflats.com/Oceanview1606/",
			userAgent: humanUserAgent,
			language: "es-CO",
			storedPref: "en",
		});
		assert.equal(humanEnPref.replacedHref, null);

		// 6. Stored non-English preference redirects human visitor
		const humanStoredPref = runLangDetect({
			scriptPath,
			href: "https://www.oceanviewflats.com/contact/",
			userAgent: humanUserAgent,
			language: "en-US",
			storedPref: "ja",
		});
		assert.equal(
			humanStoredPref.replacedHref,
			"https://www.oceanviewflats.com/contact/ja.html",
		);

		// 7. Localized page never redirects human visitor
		const humanAlreadyLocalized = runLangDetect({
			scriptPath,
			href: "https://www.oceanviewflats.com/Oceanview1707/es.html",
			userAgent: humanUserAgent,
			language: "de-DE",
		});
		assert.equal(humanAlreadyLocalized.replacedHref, null);

		// 8. Missing navigator.language does not throw
		const missingLangRes = runLangDetect({
			scriptPath,
			href: "https://www.oceanviewflats.com/",
			userAgent: humanUserAgent,
			language: undefined,
		});
		assert.equal(missingLangRes.replacedHref, null);
	}
});
