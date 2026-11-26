import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import test from "node:test";
import {
	BASE_URL,
	getCanonicalUrl,
	LANGUAGES,
	pages,
} from "../../src/config/pages";
import {
	BUILDING_REGISTRATION_FEE_COP,
	getDestinationGuidePath,
	getDestinationGuidesHubPath,
	getHomePagePath,
	getPropertyPagePath,
	PROPERTY_CONTEXTUAL_GUIDE_LINKS,
} from "../../src/constants/config";
import {
	buildGuidePricingTokens,
	DESTINATION_GUIDES,
	DESTINATION_GUIDES_HUB_COPY,
	formatCopCurrencyForLocale,
	interpolateGuideText,
} from "../../src/i18n/destinationGuides";
import { dict } from "../../src/i18n/dict";
import type { Lang, SeasonalRateTierRow } from "../../src/types";
import { buildPricingSummary } from "../../src/utils/schema";

const DIST_DIR = path.join(process.cwd(), "dist");
const PUBLIC_DIR = path.join(process.cwd(), "public");
const GUIDES_SOURCE_DIR = path.join(
	process.cwd(),
	"src",
	"i18n",
	"destinationGuides",
);

const EXPECTED_PILLAR_SLUGS = [
	"playa-salguero-vs-el-rodadero",
	"digital-nomad-remote-work",
	"day-trips-from-playa-salguero",
	"direct-reservation-guide",
] as const;

const LOCATION_KEYWORDS: Record<Lang, RegExp> = {
	en: /Santa Marta|Playa Salguero/i,
	es: /Santa Marta|Playa Salguero/i,
	fr: /Santa Marta|Playa Salguero/i,
	it: /Santa Marta|Playa Salguero/i,
	de: /Santa Marta|Playa Salguero/i,
	ja: /サンタマルタ|プラヤサルグエロ/,
};

const HTML_ENTITY_MAP: Record<string, string> = {
	"&#x27;": "'",
	"&#39;": "'",
	"&quot;": '"',
	"&amp;": "&",
	"&lt;": "<",
	"&gt;": ">",
};

const decodeHtmlEntities = (value: string): string =>
	value.replace(
		/&(?:#x27|#39|quot|amp|lt|gt);/g,
		(entity) => HTML_ENTITY_MAP[entity] ?? entity,
	);

const extractTextContent = (htmlFragment: string): string => {
	let result = "";
	let insideTag = false;
	for (let i = 0; i < htmlFragment.length; i++) {
		const ch = htmlFragment[i];
		if (ch === "<") {
			insideTag = true;
		} else if (ch === ">") {
			insideTag = false;
		} else if (!insideTag) {
			result += ch;
		}
	}
	return result;
};

const escapeHtmlAttr = (value: string): string =>
	value
		.replace(/&/g, "&amp;")
		.replace(/"/g, "&quot;")
		.replace(/'/g, "&#x27;")
		.replace(/</g, "&lt;")
		.replace(/>/g, "&gt;");

const readPricesCsvRows = (): SeasonalRateTierRow[] => {
	const csvPath = path.join(PUBLIC_DIR, "data", "prices.csv");
	const csv = fs.readFileSync(csvPath, "utf-8");
	return csv
		.split("\n")
		.map((line) => line.trim())
		.filter((line) => line.length > 0)
		.slice(1)
		.map((line) => {
			const [
				property_id,
				start_date,
				end_date,
				nightly_rate_cop,
				minimum_stay,
			] = line.split(",");
			return {
				property_id,
				start_date,
				end_date,
				nightly_rate_cop: parseFloat(nightly_rate_cop),
				minimum_stay: parseInt(minimum_stay, 10),
			};
		});
};

const getMetaContent = (
	head: string,
	attr: "name" | "property",
	key: string,
): string | null => {
	const escapedKey = key.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
	const pattern = new RegExp(
		`<meta\\s+${attr}="${escapedKey}"\\s+content="([^"]*)"\\s*/?>`,
		"i",
	);
	const match = head.match(pattern);
	return match ? decodeHtmlEntities(match[1]) : null;
};

const extractVisibleBreadcrumbLabels = (
	html: string,
	ariaLabel: string,
	label: string,
): string[] => {
	const escapedAttr = escapeHtmlAttr(ariaLabel).replace(
		/[.*+?^${}()|[\]\\]/g,
		"\\$&",
	);
	const navMatch = html.match(
		new RegExp(
			`<nav\\b[^>]*aria-label="${escapedAttr}"[^>]*>([\\s\\S]*?)<\\/nav>`,
		),
	);
	assert.ok(navMatch, `${label} must render visible breadcrumb <nav>`);
	const anchorMatches = [
		...navMatch[1].matchAll(/<a\b[^>]*>([\s\S]*?)<\/a>/gi),
	];
	return anchorMatches.map((m) =>
		decodeHtmlEntities(extractTextContent(m[1])).trim(),
	);
};

const resolveRelativeTarget = (
	htmlRelPath: string,
	rawRef: string,
): string | null => {
	if (
		rawRef.startsWith("http://") ||
		rawRef.startsWith("https://") ||
		rawRef.startsWith("mailto:") ||
		rawRef.startsWith("tel:") ||
		rawRef.startsWith("#") ||
		rawRef.startsWith("data:")
	) {
		return null;
	}
	const cleanRef = rawRef.split("#")[0].split("?")[0];
	if (!cleanRef) return null;
	const baseDir = path.dirname(path.join(DIST_DIR, htmlRelPath));
	const resolved = path.resolve(baseDir, cleanRef);
	return cleanRef.endsWith("/") ? path.join(resolved, "index.html") : resolved;
};

test("#312 registers all 4 launch pillar Destination Guides with zero hardcoded COP rates in source files", () => {
	assert.deepEqual(
		DESTINATION_GUIDES.map((g) => g.slug),
		[...EXPECTED_PILLAR_SLUGS],
		"DESTINATION_GUIDES must register all 4 launch pillar slugs in canonical order",
	);

	// Guard against hardcoded COP Property rates, Cleaning Fees, or Building Registration Fee in guide source prose
	const forbiddenHardcodedCopPattern =
		/\b(?:350|450|550|100|80|20)[.,\s]000\b(?:\s*COP)?/i;

	for (const file of fs.readdirSync(GUIDES_SOURCE_DIR)) {
		if (!file.endsWith(".ts") || file === "types.ts") continue;
		const source = fs.readFileSync(path.join(GUIDES_SOURCE_DIR, file), "utf-8");
		assert.doesNotMatch(
			source,
			forbiddenHardcodedCopPattern,
			`Guide source ${file} must use build-time {token} pricing interpolation rather than hardcoded COP rates`,
		);
		assert.doesNotMatch(
			source,
			/\bUSD\b/,
			`Guide source ${file} must never emit USD pricing`,
		);
	}
});

test("#312 all 30 /santa-marta/ pages (hub + 4 pillars × 6 locales) compile with valid SEO meta, sitemap xhtml alternates, relative links, landmarks, and JSON-LD @graph", () => {
	const summary = buildPricingSummary(readPricesCsvRows());
	const sitemapXml = fs.readFileSync(
		path.join(DIST_DIR, "sitemap.xml"),
		"utf-8",
	);

	const santaMartaPages = pages.filter(
		(p) =>
			p.id === "destination-guides" || p.id.startsWith("destination-guide-"),
	);
	assert.equal(
		santaMartaPages.length,
		5,
		"Registry must expose 5 /santa-marta/ pages (1 hub + 4 pillars)",
	);

	let compiledPageCount = 0;

	for (const page of santaMartaPages) {
		const isHub = page.id === "destination-guides";
		const guide = isHub
			? null
			: DESTINATION_GUIDES.find(
					(g) => `destination-guide-${g.slug}` === page.id,
				);
		if (!isHub) {
			assert.ok(guide, `Missing DestinationGuideDefinition for ${page.id}`);
		}

		const seenOgAlts = new Set<string>();

		for (const lang of LANGUAGES) {
			const relPath = page.filename(lang);
			const fullPath = path.join(DIST_DIR, relPath);
			assert.ok(
				fs.existsSync(fullPath),
				`Expected compiled Destination Guide page: ${relPath}`,
			);
			compiledPageCount += 1;

			const html = fs.readFileSync(fullPath, "utf-8");
			assert.ok(
				html.includes(`<html lang="${lang}">`),
				`${relPath} must declare <html lang="${lang}">`,
			);

			const headMatch = html.match(/<head>([\s\S]*?)<\/head>/i);
			const mainMatch = html.match(/<main\b[^>]*>([\s\S]*?)<\/main>/i);
			assert.ok(headMatch, `${relPath} must contain <head>`);
			assert.ok(mainMatch, `${relPath} must contain <main>`);
			const head = headMatch[1];
			const mainHtml = mainMatch[1];

			// 1. <title> <= 60 chars and <meta name="description"> <= 160 chars with Santa Marta / Playa Salguero geo keywords
			const titleMatch = head.match(/<title>([\s\S]*?)<\/title>/i);
			assert.ok(titleMatch, `${relPath} must contain <title>`);
			const title = decodeHtmlEntities(titleMatch[1]).trim();
			assert.ok(
				title.length > 0 && title.length <= 60,
				`${relPath} <title> exceeds 60 chars (${title.length}): "${title}"`,
			);
			assert.match(
				title,
				LOCATION_KEYWORDS[lang],
				`${relPath} <title> must include Santa Marta / Playa Salguero keyword: "${title}"`,
			);

			const description = getMetaContent(head, "name", "description");
			assert.ok(description, `${relPath} must emit <meta name="description">`);
			assert.ok(
				description.length > 0 && description.length <= 160,
				`${relPath} <meta name="description"> exceeds 160 chars (${description.length}): "${description}"`,
			);
			assert.match(
				description,
				LOCATION_KEYWORDS[lang],
				`${relPath} description must include Santa Marta / Playa Salguero keyword: "${description}"`,
			);

			const ogAlt = getMetaContent(head, "property", "og:image:alt");
			assert.ok(
				ogAlt && ogAlt.trim().length > 0,
				`${relPath} needs og:image:alt`,
			);
			seenOgAlts.add(ogAlt);

			// 2. Canonical URL & sitemap.xml xhtml alternates
			const expectedCanonical = getCanonicalUrl(BASE_URL, page.path, lang);
			const expectedDefaultUrl = getCanonicalUrl(BASE_URL, page.path, "en");
			assert.ok(
				html.includes(`<link rel="canonical" href="${expectedCanonical}">`),
				`${relPath} must emit canonical ${expectedCanonical}`,
			);
			assert.ok(
				sitemapXml.includes(`<loc>${expectedCanonical}</loc>`),
				`dist/sitemap.xml must include ${expectedCanonical}`,
			);
			for (const altLang of LANGUAGES) {
				const altUrl = getCanonicalUrl(BASE_URL, page.path, altLang);
				assert.ok(
					sitemapXml.includes(
						`<xhtml:link rel="alternate" hreflang="${altLang}" href="${altUrl}" />`,
					),
					`dist/sitemap.xml must include xhtml alternate ${altLang} for ${page.path}`,
				);
			}
			assert.ok(
				sitemapXml.includes(
					`<xhtml:link rel="alternate" hreflang="x-default" href="${expectedDefaultUrl}" />`,
				),
				`dist/sitemap.xml must include x-default alternate for ${page.path}`,
			);

			// 3. Depth-accurate relative assetPrefix (../ at hub, ../../ at articles) & zero broken relative assets/links
			const expectedPrefix = isHub ? "../" : "../../";
			assert.ok(
				html.includes(`href="${expectedPrefix}css/style.css?v=`),
				`${relPath} must reference CSS via ${expectedPrefix}`,
			);
			assert.ok(
				html.includes(`src="${expectedPrefix}js/main.js?v=`),
				`${relPath} must reference main.js via ${expectedPrefix}`,
			);
			assert.ok(
				html.includes(`src="${expectedPrefix}js/page-config.js?v=`),
				`${relPath} must reference page-config.js via ${expectedPrefix}`,
			);
			assert.ok(
				html.includes(`src="${expectedPrefix}js/lang-detect.js?v=`),
				`${relPath} must reference lang-detect.js via ${expectedPrefix}`,
			);

			const configMatch = html.match(
				/<script id="ovf-page-config" type="application\/json">([\s\S]*?)<\/script>/i,
			);
			assert.ok(configMatch, `${relPath} must emit #ovf-page-config`);
			const pageConfig = JSON.parse(configMatch[1]) as {
				assetPrefix: string;
				apiBase: string;
				lang: string;
			};
			assert.equal(pageConfig.assetPrefix, expectedPrefix);
			assert.equal(pageConfig.apiBase, `${expectedPrefix}api/`);
			assert.equal(pageConfig.lang, lang);

			// Validate every relative href and src attribute resolves inside dist/
			for (const attrMatch of html.matchAll(/\b(?:href|src)="([^"]+)"/gi)) {
				const target = resolveRelativeTarget(relPath, attrMatch[1]);
				if (!target) continue;
				assert.ok(
					fs.existsSync(target),
					`${relPath} contains broken relative link/asset "${attrMatch[1]}" -> ${target}`,
				);
			}

			// 4. Semantic landmarks & strictly sequential heading outline
			assert.ok(
				!mainHtml.includes("<header") &&
					html.indexOf("</header>") < html.indexOf("<main"),
				`${relPath} must place <header> outside and before <main>`,
			);
			const headingLevels = [...html.matchAll(/<h([1-6])\b[^>]*>/gi)].map((m) =>
				Number(m[1]),
			);
			assert.equal(
				headingLevels.filter((l) => l === 1).length,
				1,
				`${relPath} must contain exactly one <h1>`,
			);
			assert.equal(
				headingLevels[0],
				1,
				`${relPath} must start outline at <h1>`,
			);
			for (let i = 1; i < headingLevels.length; i++) {
				assert.ok(
					headingLevels[i] <= headingLevels[i - 1] + 1,
					`${relPath} skipped heading level ${headingLevels[i - 1]} -> ${headingLevels[i]}`,
				);
			}

			// 5. Zero unresolved interpolation tokens and zero USD anywhere
			const decodedHtml = decodeHtmlEntities(html);
			assert.doesNotMatch(
				decodedHtml,
				/\{(?:rate1707|rate1707Cop|rate1707HighCop|minStay1707|cleaning1707Cop|rate1606|rate1606Cop|rate1606HighCop|minStay1606|maxMinStay1606|cleaning1606Cop|portfolioLowCop|portfolioHighCop|buildingFeeCop)\}/,
				`${relPath} must not contain unresolved pricing interpolation tokens`,
			);
			assert.doesNotMatch(
				decodedHtml,
				/\bUSD\b/,
				`${relPath} must never emit USD pricing`,
			);

			// 6. JSON-LD @graph & UI <-> BreadcrumbList parity
			const ldMatch = html.match(
				/<script\s+type="application\/ld\+json">([\s\S]*?)<\/script>/i,
			);
			assert.ok(ldMatch, `${relPath} must emit JSON-LD`);
			const rawLd = ldMatch[1];
			assert.ok(!rawLd.includes("USD"), `${relPath} JSON-LD must not emit USD`);

			const graph = JSON.parse(rawLd)["@graph"] as Record<string, unknown>[];
			const breadcrumbList = graph.find(
				(n) => n["@type"] === "BreadcrumbList",
			) as { itemListElement: { name: string; item: string }[] } | undefined;
			assert.ok(breadcrumbList, `${relPath} must emit BreadcrumbList`);

			const t = dict[lang];
			const hubCopy = DESTINATION_GUIDES_HUB_COPY[lang];
			const visibleBreadcrumbs = extractVisibleBreadcrumbLabels(
				html,
				t.breadcrumbLabel,
				relPath,
			);
			const schemaBreadcrumbs = breadcrumbList.itemListElement.map(
				(item) => item.name,
			);
			assert.deepEqual(
				visibleBreadcrumbs,
				schemaBreadcrumbs,
				`${relPath} visible breadcrumbs must match JSON-LD BreadcrumbList 1:1`,
			);

			const expectedRate1707 = formatCopCurrencyForLocale(
				summary.properties["1707"].lowPrice,
				lang,
			);
			const expectedRate1606 = formatCopCurrencyForLocale(
				summary.properties["1606"].lowPrice,
				lang,
			);
			const expectedCleaning1707 = formatCopCurrencyForLocale(
				summary.properties["1707"].cleaningFeeCop,
				lang,
			);
			const expectedCleaning1606 = formatCopCurrencyForLocale(
				summary.properties["1606"].cleaningFeeCop,
				lang,
			);
			const expectedBuildingFee = formatCopCurrencyForLocale(
				BUILDING_REGISTRATION_FEE_COP,
				lang,
			);
			const decodedMain = decodeHtmlEntities(mainHtml);

			if (isHub) {
				assert.deepEqual(visibleBreadcrumbs, [
					t.breadcrumbHome,
					hubCopy.breadcrumbLabel,
				]);
				const itemList = graph.find((n) => n["@type"] === "ItemList") as
					| { numberOfItems: number; itemListElement: { url: string }[] }
					| undefined;
				assert.ok(itemList, `${relPath} must emit ItemList`);
				assert.equal(
					itemList.numberOfItems,
					4,
					`${relPath} ItemList must list all 4 published Destination Guides`,
				);
				for (const slug of EXPECTED_PILLAR_SLUGS) {
					const expectedGuideHref = getDestinationGuidePath(slug, lang, "../");
					assert.ok(
						html.includes(
							`id="guide-card-${slug}" href="${expectedGuideHref}"`,
						),
						`${relPath} must render published guide card for ${slug}`,
					);
				}
			} else if (guide) {
				const localeContent = guide.locales[lang];
				const tokens = buildGuidePricingTokens(summary, lang);

				assert.deepEqual(visibleBreadcrumbs, [
					t.breadcrumbHome,
					hubCopy.breadcrumbLabel,
					localeContent.breadcrumbLabel,
				]);

				// Article + FAQPage JSON-LD assertions
				const articleNode = graph.find((n) => n["@type"] === "Article") as
					| {
							headline: string;
							inLanguage: string;
							datePublished: string;
							dateModified: string;
							author: { "@id": string };
							publisher: { "@id": string };
					  }
					| undefined;
				const faqNode = graph.find((n) => n["@type"] === "FAQPage") as
					| { mainEntity: { name: string; acceptedAnswer: { text: string } }[] }
					| undefined;
				assert.ok(articleNode, `${relPath} must emit Article node`);
				assert.ok(faqNode, `${relPath} must emit FAQPage node`);
				assert.equal(articleNode.inLanguage, lang);
				assert.equal(articleNode.datePublished, guide.datePublished);
				assert.equal(articleNode.dateModified, guide.dateModified);
				assert.equal(articleNode.author["@id"], `${BASE_URL}/#host`);
				assert.equal(articleNode.publisher["@id"], `${BASE_URL}/#business`);
				assert.equal(
					faqNode.mainEntity.length,
					localeContent.faqs.length,
					`${relPath} FAQPage must match authored FAQs`,
				);

				// Semantic table & FAQ markup
				assert.ok(
					mainHtml.includes("<table") &&
						mainHtml.includes('scope="col"') &&
						mainHtml.includes('scope="row"'),
					`${relPath} must render an accessible comparison table`,
				);
				const summaryMatches = [
					...mainHtml.matchAll(/<summary\b[^>]*>([\s\S]*?)<\/summary>/gi),
				];
				assert.equal(
					summaryMatches.length,
					localeContent.faqs.length,
					`${relPath} visible <summary> count must match FAQPage count`,
				);
				localeContent.faqs.forEach((faq, idx) => {
					const expectedQuestion = interpolateGuideText(faq.question, tokens);
					assert.equal(
						decodeHtmlEntities(
							extractTextContent(summaryMatches[idx][1]),
						).trim(),
						expectedQuestion,
					);
					assert.equal(faqNode.mainEntity[idx].name, expectedQuestion);
				});

				// Relative home, hub, and Matomo Property CTA links
				assert.ok(html.includes(`href="${getHomePagePath(lang, "../../")}"`));
				assert.ok(
					html.includes(
						`href="${getDestinationGuidesHubPath(lang, "../../")}"`,
					),
				);
				for (const propId of guide.relatedProperties) {
					assert.ok(
						html.includes(
							`href="${getPropertyPagePath(propId, lang, "../../")}" data-guide-cta-property="${propId}" data-guide-slug="${guide.slug}"`,
						),
						`${relPath} must emit Property ${propId} CTA with Matomo attributes`,
					);
				}

				// Dynamic build-time COP rate parity against public/data/prices.csv
				assert.ok(
					decodedMain.includes(expectedRate1707),
					`${relPath} must display interpolated Property 1707 rate (${expectedRate1707})`,
				);
				assert.ok(
					decodedMain.includes(expectedRate1606),
					`${relPath} must display interpolated Property 1606 rate (${expectedRate1606})`,
				);
				assert.ok(
					decodedMain.includes(expectedCleaning1707),
					`${relPath} must display interpolated Property 1707 Cleaning Fee (${expectedCleaning1707})`,
				);
				assert.ok(
					decodedMain.includes(expectedCleaning1606),
					`${relPath} must display interpolated Property 1606 Cleaning Fee (${expectedCleaning1606})`,
				);
				if (guide.slug !== "day-trips-from-playa-salguero") {
					assert.ok(
						decodedMain.includes(expectedBuildingFee),
						`${relPath} must display Building Registration Fee (${expectedBuildingFee})`,
					);
				}
			}
		}

		assert.equal(
			seenOgAlts.size,
			LANGUAGES.length,
			`${page.id} must have distinct localized og:image:alt across all 6 locales`,
		);
	}

	assert.equal(
		compiledPageCount,
		30,
		"Must verify all 30 compiled /santa-marta/ pages (hub + 4 pillars × 6 locales)",
	);
});

test("#312 enforces CONTEXT.md ubiquitous language, reconciled Property facts, and live Footer/Property cross-links", () => {
	const forbiddenEnglishSynonyms = [
		/\bBuilding Fee\b/i,
		/\bResort Fee\b/i,
		/\bPrice Tier\b/i,
		/\bDirect Booking\b/i,
		/\bListing\b/i,
		/\bWelcome Pack\b/i,
		/\bHouse Manual\b/i,
		/\bCheck-in Form\b/i,
		/\bParking Spot\b/i,
		/\bDaily Price\b/i,
		/\bMaid Fee\b/i,
		/\bDoor Password\b/i,
		/\bGuest Cap\b/i,
		/\bTerms Acceptance\b/i,
	];

	const englishFiles = [
		"santa-marta/index.html",
		...EXPECTED_PILLAR_SLUGS.map((slug) => `santa-marta/${slug}/index.html`),
	];

	for (const relPath of englishFiles) {
		const html = fs.readFileSync(path.join(DIST_DIR, relPath), "utf-8");
		const mainMatch = html.match(/<main\b[^>]*>([\s\S]*?)<\/main>/i);
		assert.ok(mainMatch);
		const plainText = decodeHtmlEntities(extractTextContent(mainMatch[1]));

		for (const pattern of forbiddenEnglishSynonyms) {
			assert.doesNotMatch(
				plainText,
				pattern,
				`${relPath} must not contain CONTEXT.md forbidden synonym (${pattern})`,
			);
		}
	}

	// Verify reconciled Property & compliance facts in Direct Reservation Guide (en)
	const directGuideHtml = decodeHtmlEntities(
		fs.readFileSync(
			path.join(DIST_DIR, "santa-marta/direct-reservation-guide/index.html"),
			"utf-8",
		),
	);
	for (const requiredFact of [
		"RNT #268781",
		"RNT #270413",
		"Bay 95",
		"Bay 87",
		"15:00",
		"11:00",
		"Tuesday",
		"6 Guests",
		"Mercado Pago",
		"PSE",
		"Cash Voucher",
		"Guest Registry",
		"Personal Data Authorization",
		"Minor Guardian Attestation",
		"Building Registration Fee",
	]) {
		assert.ok(
			directGuideHtml.includes(requiredFact),
			`direct-reservation-guide (en) must include reconciled fact: ${requiredFact}`,
		);
	}

	// Verify Property pages & Footer now link directly to published depth-2 Destination Guide URLs
	for (const lang of LANGUAGES) {
		const homeFile = lang === "en" ? "index.html" : `${lang}.html`;
		const homeHtml = fs.readFileSync(path.join(DIST_DIR, homeFile), "utf-8");
		for (const slug of EXPECTED_PILLAR_SLUGS) {
			assert.ok(
				homeHtml.includes(
					`href="${getDestinationGuidePath(slug, lang, "./")}"`,
				),
				`${homeFile} Footer must link to published Destination Guide ${slug}`,
			);
		}

		for (const propId of ["1707", "1606"] as const) {
			const propDir = propId === "1707" ? "Oceanview1707" : "Oceanview1606";
			const propFile = lang === "en" ? "index.html" : `${lang}.html`;
			const propHtml = fs.readFileSync(
				path.join(DIST_DIR, propDir, propFile),
				"utf-8",
			);
			for (const ref of PROPERTY_CONTEXTUAL_GUIDE_LINKS[propId]) {
				const expectedHref = getDestinationGuidePath(ref.slug, lang, "../");
				assert.ok(
					propHtml.includes(`href="${expectedHref}"`),
					`${propDir}/${propFile} must link contextually to published guide ${ref.slug}`,
				);
			}
		}
	}
});
