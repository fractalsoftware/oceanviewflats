import crypto from "node:crypto";
import fs from "node:fs";
import path from "node:path";
import React from "react";
import { renderToStaticMarkup } from "react-dom/server";
import {
	BASE_URL,
	getCanonicalUrl,
	getClientDictionaryForPage,
	getClientExtraConfigForPage,
	getHrefLangTags,
	LANGUAGES,
	type PageConfig,
	pages,
} from "./src/config/pages";
import { dict } from "./src/i18n/dict";
import { baseTemplate } from "./src/templates/base";
import type { Lang } from "./src/types";
import {
	buildPricingSummary,
	type SeasonalRateTierRow,
} from "./src/utils/schema";

const distDir = path.join(process.cwd(), "dist");
const publicDir = path.join(process.cwd(), "public");

if (!fs.existsSync(distDir)) {
	fs.mkdirSync(distDir, { recursive: true });
	// Standalone render fallback: copy public assets when vite build did not pre-populate dist/
	if (fs.existsSync(publicDir)) {
		fs.cpSync(publicDir, distDir, { recursive: true });
		console.log("Copied public assets to dist/");
	}
}

const computeFileHash = (filePath: string): string =>
	crypto
		.createHash("sha256")
		.update(fs.readFileSync(filePath))
		.digest("hex")
		.slice(0, 8);

const buildAssetVersions = (): Record<string, string> => {
	const assetPaths = new Set<string>([
		"css/style.css",
		"js/lang-detect.js",
		"js/page-config.js",
	]);
	for (const page of pages) {
		for (const script of page.scripts) {
			assetPaths.add(script);
		}
	}

	const versions: Record<string, string> = {};
	for (const assetPath of assetPaths) {
		const distAssetPath = path.join(distDir, assetPath);
		if (fs.existsSync(distAssetPath)) {
			versions[assetPath] = computeFileHash(distAssetPath);
			continue;
		}
		const fallbackPath =
			assetPath === "css/style.css"
				? path.join(process.cwd(), "src", "style.css")
				: path.join(publicDir, assetPath);
		if (fs.existsSync(fallbackPath)) {
			versions[assetPath] = computeFileHash(fallbackPath);
		}
	}

	return versions;
};

const assetVersions = buildAssetVersions();

// Parse public/data/prices.csv to generate build-time prices.json caches for client usage
// and return parsed tiers for build-time JSON-LD structured data injection.
const parsePricesCsv = (): SeasonalRateTierRow[] => {
	const csvPath = path.join(process.cwd(), "public", "data", "prices.csv");
	const cacheDir = path.join(process.cwd(), "public", "cache");
	const distCacheDir = path.join(distDir, "cache");

	if (!fs.existsSync(cacheDir)) {
		fs.mkdirSync(cacheDir, { recursive: true });
	}
	if (!fs.existsSync(distCacheDir)) {
		fs.mkdirSync(distCacheDir, { recursive: true });
	}

	const prices: SeasonalRateTierRow[] = [];

	if (fs.existsSync(csvPath)) {
		const csvContent = fs.readFileSync(csvPath, "utf-8");
		const lines = csvContent
			.split("\n")
			.map((l) => l.trim())
			.filter((l) => l.length > 0);

		if (lines.length > 1) {
			for (let i = 1; i < lines.length; i++) {
				const cols = lines[i].split(",");
				if (cols.length >= 5) {
					prices.push({
						property_id: cols[0],
						start_date: cols[1],
						end_date: cols[2],
						nightly_rate_cop: parseFloat(cols[3]),
						minimum_stay: parseInt(cols[4], 10),
					});
				}
			}

			const jsonContent = JSON.stringify(prices, null, 2);
			fs.writeFileSync(path.join(cacheDir, "prices.json"), jsonContent);
			fs.writeFileSync(path.join(distCacheDir, "prices.json"), jsonContent);
			console.log("Parsed prices.csv and generated cached prices.json files.");
		}
	} else {
		console.log("Warning: public/data/prices.csv not found to parse.");
	}

	return prices;
};

const parsedSeasonalRateTiers = parsePricesCsv();
const pricingSummary = buildPricingSummary(parsedSeasonalRateTiers);

const generateSitemap = (
	pages: PageConfig[],
	baseUrl: string,
	languages: readonly Lang[],
) => {
	const urls: string[] = [];
	const now = new Date().toISOString().split("T")[0];

	pages.forEach((page) => {
		const defaultUrl = getCanonicalUrl(baseUrl, page.path, "en");
		const alternateLinks = [
			...languages.map(
				(altLang) =>
					`    <xhtml:link rel="alternate" hreflang="${altLang}" href="${getCanonicalUrl(baseUrl, page.path, altLang)}" />`,
			),
			`    <xhtml:link rel="alternate" hreflang="x-default" href="${defaultUrl}" />`,
		].join("\n");

		languages.forEach((lang) => {
			const url = getCanonicalUrl(baseUrl, page.path, lang);
			urls.push(`  <url>
    <loc>${url}</loc>
${alternateLinks}
    <lastmod>${now}</lastmod>
    <changefreq>weekly</changefreq>
    <priority>${page.path === "" ? "1.0" : "0.8"}</priority>
  </url>`);
		});
	});

	return `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
${urls.join("\n")}
</urlset>`;
};

pages.forEach((page) => {
	const hrefLangTags = page.noIndex
		? ""
		: getHrefLangTags(BASE_URL, page.path, LANGUAGES);

	LANGUAGES.forEach((lang) => {
		const t = dict[lang];
		const PageComponent = page.component;
		const filename = page.filename(lang);

		const depth = filename.split("/").length - 1;
		const assetPrefix =
			page.id === "404" ? "/" : depth === 0 ? "./" : "../".repeat(depth);

		const rawMarkup = renderToStaticMarkup(
			<PageComponent
				lang={lang}
				assetPrefix={assetPrefix}
				pricingSummary={pricingSummary}
			/>,
		);
		// Strip React 19's auto-injected <link rel="preload" .../> tags from body markup;
		// hero image preload hints are emitted inside <head> via baseTemplate.
		const markup = rawMarkup.replace(
			/<link\b[^>]*\brel=["']preload["'][^>]*\/?>/gi,
			"",
		);

		const url = getCanonicalUrl(BASE_URL, page.path, lang);
		const structuredData = page.getStructuredData(
			t,
			lang,
			BASE_URL,
			pricingSummary,
		);

		const apiBase = page.id === "404" ? "/api/" : `${assetPrefix}api/`;
		const i18nSlice = page.clientI18n
			? page.clientI18n(t)
			: getClientDictionaryForPage(page.id, t);

		const pageConfig = {
			pageId: page.id,
			lang,
			assetPrefix,
			apiBase,
			...getClientExtraConfigForPage(page.id),
			i18n: i18nSlice,
		};

		const html = baseTemplate({
			markup,
			lang,
			title: page.seoTitle
				? page.seoTitle(t, lang, pricingSummary)
				: t.seoTitle,
			description: page.seoDescription
				? page.seoDescription(t, lang, pricingSummary)
				: t.seoDescription,
			url,
			baseUrl: BASE_URL,
			ogImage: {
				url: `${BASE_URL}/${page.ogImage.path}`,
				width: page.ogImage.width,
				height: page.ogImage.height,
				alt: page.ogImage.getAlt(t, lang),
			},
			heroPreloadHref: page.heroImage
				? `${assetPrefix}${page.heroImage}`
				: undefined,
			hrefLangTags,
			structuredData,
			assetPrefix,
			assetVersions,
			noIndex: Boolean(page.noIndex),
			customScripts: page.scripts,
			pageConfig,
		});

		const outputPath = path.join(distDir, filename);

		const outputDir = path.dirname(outputPath);
		if (!fs.existsSync(outputDir)) {
			fs.mkdirSync(outputDir, { recursive: true });
		}

		fs.writeFileSync(outputPath, html);
		console.log(`Generated [${page.id}] -> ${outputPath}`);
	});
});

// Generate and save sitemap
const sitemap = generateSitemap(
	pages.filter((p) => !p.noIndex),
	BASE_URL,
	LANGUAGES,
);
fs.writeFileSync(path.join(distDir, "sitemap.xml"), sitemap);
console.log("Generated sitemap.xml");
