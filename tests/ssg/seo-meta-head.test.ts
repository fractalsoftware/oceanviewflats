import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import test from "node:test";
import { BASE_URL, LANGUAGES, pages } from "../../src/config/pages";
import { IMAGES } from "../../src/constants/config";
import { dict } from "../../src/i18n/dict";
import { baseTemplate } from "../../src/templates/base";

const DIST_DIR = path.join(process.cwd(), "dist");

const PUBLIC_PAGES = [
	{
		id: "home",
		titleKey: "seoHomeTitle",
		descKey: "seoHomeDescription",
		ogAltKey: "ogHomeImageAlt",
		expectedImage: IMAGES.home.hero,
		expectedWidth: "1600",
		expectedHeight: "1355",
	},
	{
		id: "Oceanview1707",
		titleKey: "seoTitle",
		descKey: "seoDescription",
		ogAltKey: "og1707ImageAlt",
		expectedImage: IMAGES["1707"].hero,
		expectedWidth: "1980",
		expectedHeight: "1280",
	},
	{
		id: "Oceanview1606",
		titleKey: "seo1606Title",
		descKey: "seo1606Description",
		ogAltKey: "og1606ImageAlt",
		expectedImage: IMAGES["1606"].hero,
		expectedWidth: "1980",
		expectedHeight: "1280",
	},
	{
		id: "contact",
		titleKey: "seoContactTitle",
		descKey: "seoContactDescription",
		ogAltKey: "ogContactImageAlt",
		expectedImage: IMAGES.mainEntrance,
		expectedWidth: "1280",
		expectedHeight: "677",
	},
	{
		id: "privacy",
		titleKey: "privacySeoTitle",
		descKey: "privacySeoDescription",
		ogAltKey: "ogPrivacyImageAlt",
		expectedImage: IMAGES.mainEntrance,
		expectedWidth: "1280",
		expectedHeight: "677",
	},
] as const;

const LOCATION_KEYWORDS: Record<string, RegExp> = {
	en: /Santa Marta|Playa Salguero/i,
	es: /Santa Marta|Playa Salguero/i,
	fr: /Santa Marta|Playa Salguero/i,
	it: /Santa Marta|Playa Salguero/i,
	de: /Santa Marta|Playa Salguero/i,
	ja: /サンタマルタ|プラヤサルグエロ/,
};

function readDistHtml(filename: string): string {
	const fullPath = path.join(DIST_DIR, filename);
	assert.ok(fs.existsSync(fullPath), `Expected compiled file: ${fullPath}`);
	return fs.readFileSync(fullPath, "utf-8");
}

function splitHeadAndBody(html: string): { head: string; body: string } {
	const headMatch = html.match(/<head>([\s\S]*?)<\/head>/i);
	const bodyMatch = html.match(/<body>([\s\S]*?)<\/body>/i);
	assert.ok(headMatch, "HTML must contain <head>...</head>");
	assert.ok(bodyMatch, "HTML must contain <body>...</body>");
	return { head: headMatch[1], body: bodyMatch[1] };
}

function getMetaContent(
	head: string,
	attr: "name" | "property",
	key: string,
): string | null {
	const escapedKey = key.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
	const pattern = new RegExp(
		`<meta\\s+${attr}="${escapedKey}"\\s+content="([^"]*)"\\s*/?>`,
		"i",
	);
	const match = head.match(pattern);
	return match ? match[1] : null;
}

test("All public pages across all 6 locales enforce <= 60 char titles and <= 160 char descriptions", () => {
	for (const publicPage of PUBLIC_PAGES) {
		const pageConfig = pages.find((p) => p.id === publicPage.id);
		assert.ok(pageConfig, `Page config missing for ${publicPage.id}`);

		for (const lang of LANGUAGES) {
			const html = readDistHtml(pageConfig.filename(lang));
			const { head } = splitHeadAndBody(html);

			const titleMatch = head.match(/<title>([\s\S]*?)<\/title>/i);
			assert.ok(titleMatch, `Missing <title> in ${publicPage.id} (${lang})`);
			const compiledTitle = titleMatch[1];
			const dictTitle = dict[lang][publicPage.titleKey];

			assert.equal(
				compiledTitle,
				dictTitle,
				`Compiled <title> must match dict.${lang}.${publicPage.titleKey}`,
			);
			assert.ok(
				compiledTitle.length > 0 && compiledTitle.length <= 60,
				`Title for ${publicPage.id} (${lang}) exceeds 60 chars (${compiledTitle.length}): "${compiledTitle}"`,
			);

			const compiledDesc = getMetaContent(head, "name", "description");
			assert.ok(
				compiledDesc !== null,
				`Missing <meta name="description"> in ${publicPage.id} (${lang})`,
			);
			const dictDesc = dict[lang][publicPage.descKey];
			assert.equal(
				compiledDesc,
				dictDesc,
				`Compiled description must match dict.${lang}.${publicPage.descKey}`,
			);
			assert.ok(
				compiledDesc.length > 0 && compiledDesc.length <= 160,
				`Description for ${publicPage.id} (${lang}) exceeds 160 chars (${compiledDesc.length}): "${compiledDesc}"`,
			);

			assert.match(
				compiledTitle,
				LOCATION_KEYWORDS[lang],
				`<title> for ${publicPage.id} (${lang}) must include Santa Marta / Playa Salguero geo keyword: "${compiledTitle}"`,
			);
			assert.match(
				`${compiledTitle} ${compiledDesc}`,
				LOCATION_KEYWORDS[lang],
				`Snippet for ${publicPage.id} (${lang}) must reference Santa Marta / Playa Salguero`,
			);
		}
	}
});

test("Every compiled page emits canonical apple-mobile-web-app-title and manifest has OceanViewFlats branding", () => {
	for (const page of pages) {
		for (const lang of LANGUAGES) {
			const html = readDistHtml(page.filename(lang));
			const { head } = splitHeadAndBody(html);

			const appleTitle = getMetaContent(
				head,
				"name",
				"apple-mobile-web-app-title",
			);
			assert.equal(
				appleTitle,
				"OceanViewFlats",
				`apple-mobile-web-app-title must be "OceanViewFlats" in ${page.id} (${lang})`,
			);
		}
	}

	for (const manifestPath of [
		path.join(process.cwd(), "public", "site.webmanifest"),
		path.join(DIST_DIR, "site.webmanifest"),
	]) {
		assert.ok(
			fs.existsSync(manifestPath),
			`Manifest file missing: ${manifestPath}`,
		);
		const raw = fs.readFileSync(manifestPath, "utf-8");
		assert.ok(
			!raw.includes("MyWebSite") && !raw.includes("MySite"),
			`Manifest ${manifestPath} must not contain placeholder branding`,
		);
		const manifest = JSON.parse(raw);
		assert.equal(manifest.name, "OceanViewFlats Santa Marta");
		assert.equal(manifest.short_name, "OceanViewFlats");
	}
});

test("Public pages emit accurate OG and Twitter image URLs, verified dimensions, and localized alt text", () => {
	for (const publicPage of PUBLIC_PAGES) {
		const pageConfig = pages.find((p) => p.id === publicPage.id);
		assert.ok(pageConfig, `Page config missing for ${publicPage.id}`);

		const expectedUrl = `${BASE_URL}/${publicPage.expectedImage}`;
		const seenAlts = new Set<string>();

		for (const lang of LANGUAGES) {
			const html = readDistHtml(pageConfig.filename(lang));
			const { head } = splitHeadAndBody(html);

			const ogImage = getMetaContent(head, "property", "og:image");
			const twitterImage = getMetaContent(head, "name", "twitter:image");
			const ogWidth = getMetaContent(head, "property", "og:image:width");
			const ogHeight = getMetaContent(head, "property", "og:image:height");
			const ogAlt = getMetaContent(head, "property", "og:image:alt");
			const twitterAlt = getMetaContent(head, "name", "twitter:image:alt");

			assert.equal(
				ogImage,
				expectedUrl,
				`og:image mismatch on ${publicPage.id} (${lang})`,
			);
			assert.equal(
				twitterImage,
				expectedUrl,
				`twitter:image mismatch on ${publicPage.id} (${lang})`,
			);
			if (publicPage.id === "home") {
				assert.ok(
					!ogImage?.includes("1707-main-room-seaside.webp"),
					"Homepage og:image must use dedicated home-hero.webp, not Property 1707 hero",
				);
			}

			assert.equal(
				ogWidth,
				publicPage.expectedWidth,
				`og:image:width mismatch on ${publicPage.id} (${lang})`,
			);
			assert.equal(
				ogHeight,
				publicPage.expectedHeight,
				`og:image:height mismatch on ${publicPage.id} (${lang})`,
			);

			const expectedAlt = dict[lang][publicPage.ogAltKey];
			assert.ok(
				expectedAlt && expectedAlt.trim().length > 0,
				`dict.${lang}.${publicPage.ogAltKey} must be non-empty`,
			);
			assert.equal(
				ogAlt,
				expectedAlt,
				`og:image:alt mismatch on ${publicPage.id} (${lang})`,
			);
			assert.equal(
				twitterAlt,
				expectedAlt,
				`twitter:image:alt mismatch on ${publicPage.id} (${lang})`,
			);
			seenAlts.add(ogAlt);
		}

		assert.equal(
			seenAlts.size,
			LANGUAGES.length,
			`og:image:alt on ${publicPage.id} must be localized across all ${LANGUAGES.length} locales`,
		);
	}
});

test("baseTemplate escapes HTML attribute special characters in og:image:alt and twitter:image:alt", () => {
	const html = baseTemplate({
		markup: "<div>Body</div>",
		lang: "en",
		title: "Test Title",
		description: "Test Description",
		url: "https://www.oceanviewflats.com/",
		baseUrl: "https://www.oceanviewflats.com",
		ogImage: {
			url: "https://www.oceanviewflats.com/images/home-hero.webp",
			width: 1600,
			height: 1355,
			alt: 'OceanView "Sunset" & <Caribbean> View',
		},
		heroPreloadHref: "./images/home-hero.webp",
		hrefLangTags: "",
		structuredData: "{}",
		assetPrefix: "./",
	});

	const { head } = splitHeadAndBody(html);
	const expectedEscaped =
		"OceanView &quot;Sunset&quot; &amp; &lt;Caribbean&gt; View";
	assert.equal(
		getMetaContent(head, "property", "og:image:alt"),
		expectedEscaped,
	);
	assert.equal(
		getMetaContent(head, "name", "twitter:image:alt"),
		expectedEscaped,
	);
});

test("Compiled HTML emits Google Fonts preconnect and hero preload in <head> with zero preloads in <body>", () => {
	for (const page of pages) {
		for (const lang of LANGUAGES) {
			const filename = page.filename(lang);
			const html = readDistHtml(filename);
			const { head, body } = splitHeadAndBody(html);

			const preconnectApisIdx = head.indexOf(
				'<link rel="preconnect" href="https://fonts.googleapis.com" />',
			);
			const preconnectGstaticIdx = head.indexOf(
				'<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />',
			);
			const googleFontsCssIdx = head.indexOf(
				'href="https://fonts.googleapis.com/css2?',
			);

			assert.ok(
				preconnectApisIdx !== -1,
				`Missing fonts.googleapis.com preconnect in ${filename}`,
			);
			assert.ok(
				preconnectGstaticIdx !== -1,
				`Missing fonts.gstatic.com preconnect in ${filename}`,
			);
			assert.ok(
				googleFontsCssIdx !== -1,
				`Missing Google Fonts stylesheet in ${filename}`,
			);
			assert.ok(
				preconnectApisIdx < googleFontsCssIdx &&
					preconnectGstaticIdx < googleFontsCssIdx,
				`Google Fonts preconnect links must precede font stylesheet in ${filename}`,
			);

			const depth = filename.split("/").length - 1;
			const assetPrefix =
				page.id === "404" ? "/" : depth === 0 ? "./" : "../".repeat(depth);
			assert.ok(
				page.heroImage,
				`Page ${page.id} should define heroImage for <head> preload`,
			);
			const expectedHeroHref = `${assetPrefix}${page.heroImage}`;
			const expectedPreloadTag = `<link rel="preload" as="image" href="${expectedHeroHref}" fetchpriority="high" />`;
			assert.ok(
				head.includes(expectedPreloadTag),
				`Missing hero preload "${expectedPreloadTag}" in <head> of ${filename}`,
			);

			assert.ok(
				!/<link\b[^>]*\brel=["']preload["'][^>]*>/i.test(body),
				`Found disallowed <link rel="preload"> inside <body>/#root of ${filename}`,
			);
		}
	}
});
