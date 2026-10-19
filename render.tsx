import fs from "fs";
import path from "path";
import React from "react";
import { renderToStaticMarkup } from "react-dom/server";
import {
	BASE_URL,
	getClientDictionaryForPage,
	getClientExtraConfigForPage,
	getHrefLangTags,
	LANGUAGES,
	type PageConfig,
	pages,
} from "./src/config/pages";
import { IMAGES } from "./src/constants/config";
import { dict } from "./src/i18n/dict";
import { baseTemplate } from "./src/templates/base";

const distDir = path.join(process.cwd(), "dist");
const publicDir = path.join(process.cwd(), "public");

if (fs.existsSync(distDir)) {
	fs.rmSync(distDir, { recursive: true, force: true });
	console.log("Cleaned dist/ directory");
}
fs.mkdirSync(distDir, { recursive: true });

// Copy public assets to dist
if (fs.existsSync(publicDir)) {
	fs.cpSync(publicDir, distDir, { recursive: true });
	console.log("Copied public assets to dist/");
}

// Parse public/data/prices.csv to generate build-time prices.json caches for client usage
const parsePricesCsv = () => {
	const csvPath = path.join(process.cwd(), "public", "data", "prices.csv");
	const cacheDir = path.join(process.cwd(), "public", "cache");
	const distCacheDir = path.join(distDir, "cache");

	if (!fs.existsSync(cacheDir)) {
		fs.mkdirSync(cacheDir, { recursive: true });
	}
	if (!fs.existsSync(distCacheDir)) {
		fs.mkdirSync(distCacheDir, { recursive: true });
	}

	if (fs.existsSync(csvPath)) {
		const csvContent = fs.readFileSync(csvPath, "utf-8");
		const lines = csvContent
			.split("\n")
			.map((l) => l.trim())
			.filter((l) => l.length > 0);

		if (lines.length > 1) {
			const prices: {
				property_id: string;
				start_date: string;
				end_date: string;
				nightly_rate_cop: number;
				minimum_stay: number;
			}[] = [];

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
};

parsePricesCsv();

const generateSitemap = (
	pages: PageConfig[],
	baseUrl: string,
	languages: string[],
) => {
	const urls: string[] = [];
	const now = new Date().toISOString().split("T")[0];

	pages.forEach((page) => {
		languages.forEach((lang) => {
			const basePath = page.path ? `${baseUrl}/${page.path}` : baseUrl;
			const url = lang === "en" ? basePath : `${basePath}/${lang}.html`;
			urls.push(`  <url>
    <loc>${url}</loc>
    <lastmod>${now}</lastmod>
    <changefreq>weekly</changefreq>
    <priority>${page.path === "" ? "1.0" : "0.8"}</priority>
  </url>`);
		});
	});

	return `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
${urls.join("\n")}
</urlset>`;
};

pages.forEach((page) => {
	const hrefLangTags = getHrefLangTags(BASE_URL, page.path, LANGUAGES);

	LANGUAGES.forEach((lang) => {
		const t = dict[lang];
		const PageComponent = page.component;
		const filename = page.filename(lang);

		const depth = filename.split("/").length - 1;
		const assetPrefix =
			page.id === "404" ? "/" : depth === 0 ? "./" : "../".repeat(depth);

		const markup = renderToStaticMarkup(
			<PageComponent lang={lang} assetPrefix={assetPrefix} />,
		);

		const basePath = page.path ? `${BASE_URL}/${page.path}` : BASE_URL;
		const url = lang === "en" ? basePath : `${basePath}/${lang}.html`;
		const structuredData = page.getStructuredData(t, lang, BASE_URL);

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
			title: page.seoTitle ? page.seoTitle(t) : t.seoTitle,
			description: page.seoDescription
				? page.seoDescription(t)
				: t.seoDescription,
			url,
			baseUrl: BASE_URL,
			ogImage: `${BASE_URL}/${page.ogImage}`,
			hrefLangTags,
			structuredData,
			assetPrefix,
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
	pages.filter(
		(p) =>
			p.id !== "404" &&
			p.id !== "registry" &&
			p.id !== "guide" &&
			p.id !== "booking-success" &&
			p.id !== "booking-failure" &&
			p.id !== "booking-pending",
	),
	BASE_URL,
	LANGUAGES,
);
fs.writeFileSync(path.join(distDir, "sitemap.xml"), sitemap);
console.log("Generated sitemap.xml");
