import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import test from "node:test";
import vm from "node:vm";
import React from "react";
import { renderToStaticMarkup } from "react-dom/server";
import {
	BASE_URL,
	getCanonicalUrl,
	LANGUAGES,
	pages,
} from "../../src/config/pages";
import {
	getDestinationGuidePath,
	getDestinationGuidesHubPath,
	getHomePagePath,
	getPropertyPagePath,
	IMAGE_DIMENSIONS,
	IMAGES,
	PROPERTY_CLEANING_FEES_COP,
} from "../../src/constants/config";
import {
	DESTINATION_GUIDES_HUB_COPY,
	type DestinationGuideDefinition,
	formatCopCurrencyForLocale,
} from "../../src/i18n/destinationGuides";
import { dict } from "../../src/i18n/dict";
import DestinationGuideArticle from "../../src/pages/DestinationGuideArticle";
import type { Lang, SeasonalRateTierRow } from "../../src/types";
import {
	buildDestinationGuideArticleStructuredData,
	buildPricingSummary,
} from "../../src/utils/schema";

const DIST_DIR = path.join(process.cwd(), "dist");
const PUBLIC_DIR = path.join(process.cwd(), "public");

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

const createFixtureGuide = (): DestinationGuideDefinition => {
	const makeLocale = (lang: Lang) => ({
		seoTitle: `Santa Marta Fixture (${lang}) | OceanViewFlats`,
		seoDescription: `Playa Salguero fixture (${lang}) from {rate1707Cop} (cleaning {cleaning1707Cop}) and {rate1606Cop} (cleaning {cleaning1606Cop}).`,
		ogImageAlt: `Playa Salguero fixture ocean sunset view (${lang})`,
		heroImageAlt: `Playa Salguero fixture beachfront hero panorama (${lang})`,
		breadcrumbLabel: `Fixture Guide (${lang})`,
		badge: `Fixture Badge (${lang})`,
		title: `Playa Salguero Fixture Title (${lang})`,
		subtitle: `Nightly Rates from {rate1606Cop} with Building Registration Fee {buildingFeeCop}.`,
		cardSummary: `Summary for fixture guide (${lang}).`,
		keyTakeawaysTitle: `Key Takeaways (${lang})`,
		keyTakeaways: [
			`Property 1707 starts at {rate1707Cop} per night ({minStay1707}-night min) plus {cleaning1707Cop} Cleaning Fee.`,
			`Property 1606 starts at {rate1606Cop} per night plus {cleaning1606Cop} Cleaning Fee.`,
		],
		sections: [
			{
				id: "pricing-overview",
				heading: `Pricing & Logistics (${lang})`,
				paragraphs: [
					`Direct Reservations settle in COP with Building Registration Fee {buildingFeeCop} payable at reception.`,
				],
				bullets: [
					`1707 baseline: {rate1707Cop} / Cleaning Fee: {cleaning1707Cop}`,
					`1606 baseline: {rate1606Cop} / Cleaning Fee: {cleaning1606Cop}`,
				],
				factCards: [
					{
						label: `Property 1707 (${lang})`,
						value: "{rate1707Cop}",
						detail: "Cleaning Fee {cleaning1707Cop}",
					},
				],
				comparisonTable: {
					caption: `Comparison (${lang})`,
					headers: ["Attribute", "Property 1707", "Property 1606"],
					rows: [
						["Nightly Rate", "{rate1707Cop}", "{rate1606Cop}"],
						["Cleaning Fee", "{cleaning1707Cop}", "{cleaning1606Cop}"],
					],
				},
				callout: {
					label: `Host Note (${lang})`,
					text: "Pools close Tuesdays; Building Registration Fee is {buildingFeeCop}.",
				},
			},
		],
		faqsTitle: `Frequently Asked Questions (${lang})`,
		faqs: [
			{
				id: "fixture-faq-1",
				question: `What is the baseline rate for Property 1707 (${lang})?`,
				answer: `Property 1707 starts at {rate1707Cop} per night plus a {cleaning1707Cop} Cleaning Fee.`,
				defaultOpen: true,
			},
		],
		ctaTitle: `Book Direct in Playa Salguero (${lang})`,
		ctaSubtitle: `Reserve with 0% OTA commission from {portfolioLowCop}.`,
	});

	return {
		slug: "fixture-santa-marta-guide",
		id: "destination-guide-fixture-santa-marta-guide",
		categoryIcon: "compass",
		heroImage: IMAGES.home.hero,
		heroImageDimensions: IMAGE_DIMENSIONS.homeHero,
		ogImage: {
			path: IMAGES.home.hero,
			...IMAGE_DIMENSIONS.homeHero,
		},
		datePublished: "2026-10-15",
		dateModified: "2026-11-26",
		readingTimeMinutes: 6,
		relatedProperties: ["1707", "1606"],
		locales: {
			en: makeLocale("en"),
			es: makeLocale("es"),
			fr: makeLocale("fr"),
			it: makeLocale("it"),
			de: makeLocale("de"),
			ja: makeLocale("ja"),
		},
	};
};

test("#311 canonical Destination Guide path helpers & buildPricingSummary cleaningFeeCop", () => {
	assert.equal(getDestinationGuidesHubPath("en", "./"), "./santa-marta/");
	assert.equal(
		getDestinationGuidesHubPath("es", "../"),
		"../santa-marta/es.html",
	);
	assert.equal(
		getDestinationGuidesHubPath("ja", "../../"),
		"../../santa-marta/ja.html",
	);
	assert.equal(
		getDestinationGuidePath("digital-nomad-remote-work", "en", "./"),
		"./santa-marta/digital-nomad-remote-work/",
	);
	assert.equal(
		getDestinationGuidePath("digital-nomad-remote-work", "fr", "../../"),
		"../../santa-marta/digital-nomad-remote-work/fr.html",
	);
	assert.equal(
		getCanonicalUrl(BASE_URL, "santa-marta", "en"),
		`${BASE_URL}/santa-marta/`,
	);
	assert.equal(
		getCanonicalUrl(BASE_URL, "santa-marta/digital-nomad-remote-work", "de"),
		`${BASE_URL}/santa-marta/digital-nomad-remote-work/de.html`,
	);

	const summary = buildPricingSummary(readPricesCsvRows());
	assert.equal(
		summary.properties["1707"].cleaningFeeCop,
		PROPERTY_CLEANING_FEES_COP["1707"],
	);
	assert.equal(
		summary.properties["1606"].cleaningFeeCop,
		PROPERTY_CLEANING_FEES_COP["1606"],
	);
});

test("#311 compiled /santa-marta/ hub compiles cleanly across all 6 locales with SEO meta, semantics, dynamic COP rates, Breadcrumbs & CollectionPage JSON-LD", () => {
	const hubPage = pages.find((p) => p.id === "destination-guides");
	assert.ok(hubPage, "pages registry must include destination-guides hub");
	assert.deepEqual(hubPage.scripts, ["js/main.js"]);

	const summary = buildPricingSummary(readPricesCsvRows());
	const locationKeywords: Record<Lang, RegExp> = {
		en: /Santa Marta|Playa Salguero/i,
		es: /Santa Marta|Playa Salguero/i,
		fr: /Santa Marta|Playa Salguero/i,
		it: /Santa Marta|Playa Salguero/i,
		de: /Santa Marta|Playa Salguero/i,
		ja: /サンタマルタ|プラヤサルグエロ/,
	};

	const seenOgAlts = new Set<string>();

	for (const lang of LANGUAGES) {
		const filename = hubPage.filename(lang);
		const fullPath = path.join(DIST_DIR, filename);
		assert.ok(
			fs.existsSync(fullPath),
			`Expected compiled hub file: ${fullPath}`,
		);

		const html = fs.readFileSync(fullPath, "utf-8");
		const hubCopy = DESTINATION_GUIDES_HUB_COPY[lang];
		const t = dict[lang];

		// SEO title & description length and geo keyword guardrails
		assert.ok(
			hubCopy.seoTitle.length > 0 && hubCopy.seoTitle.length <= 60,
			`Hub seoTitle (${lang}) must be <= 60 chars (got ${hubCopy.seoTitle.length})`,
		);
		assert.ok(
			hubCopy.seoDescription.length > 0 && hubCopy.seoDescription.length <= 160,
			`Hub seoDescription (${lang}) must be <= 160 chars (got ${hubCopy.seoDescription.length})`,
		);
		assert.match(hubCopy.seoTitle, locationKeywords[lang]);
		assert.ok(html.includes(`<title>${hubCopy.seoTitle}</title>`));
		seenOgAlts.add(hubCopy.ogImageAlt);

		// Landmarks & sequential heading hierarchy
		const mainMatch = html.match(/<main\b[^>]*>([\s\S]*?)<\/main>/i);
		assert.ok(mainMatch, `${filename} must contain <main>`);
		assert.ok(
			!mainMatch[1].includes("<header") &&
				html.indexOf("</header>") < html.indexOf("<main"),
			`${filename} must place <header> outside and before <main>`,
		);
		const levels = [...html.matchAll(/<h([1-6])\b[^>]*>/gi)].map((m) =>
			Number(m[1]),
		);
		assert.equal(levels.filter((l) => l === 1).length, 1);
		assert.equal(levels[0], 1);
		for (let i = 1; i < levels.length; i++) {
			assert.ok(
				levels[i] <= levels[i - 1] + 1,
				`${filename} skipped heading tier ${levels[i - 1]} -> ${levels[i]}`,
			);
		}

		// Dynamic build-time COP rates & Cleaning Fees from prices.csv
		const expected1707Rate = formatCopCurrencyForLocale(
			summary.properties["1707"].lowPrice,
			lang,
		);
		const expected1606Rate = formatCopCurrencyForLocale(
			summary.properties["1606"].lowPrice,
			lang,
		);
		const expected1707Cleaning = formatCopCurrencyForLocale(
			summary.properties["1707"].cleaningFeeCop,
			lang,
		);
		const expected1606Cleaning = formatCopCurrencyForLocale(
			summary.properties["1606"].cleaningFeeCop,
			lang,
		);
		const decodedMain = decodeHtmlEntities(mainMatch[1]);
		assert.ok(decodedMain.includes(expected1707Rate));
		assert.ok(decodedMain.includes(expected1606Rate));
		assert.ok(decodedMain.includes(expected1707Cleaning));
		assert.ok(decodedMain.includes(expected1606Cleaning));

		// Property CTA Matomo data attributes & relative depth-1 links
		assert.ok(
			html.includes(
				`href="${getPropertyPagePath("1707", lang, "../")}" data-guide-cta-property="1707" data-guide-slug="hub"`,
			),
			`${filename} must emit relative Property 1707 CTA with Matomo attributes`,
		);
		assert.ok(
			html.includes(
				`href="${getPropertyPagePath("1606", lang, "../")}" data-guide-cta-property="1606" data-guide-slug="hub"`,
			),
			`${filename} must emit relative Property 1606 CTA with Matomo attributes`,
		);

		// JSON-LD @graph verification & 1:1 Breadcrumbs alignment
		const ldMatch = html.match(
			/<script\s+type="application\/ld\+json">([\s\S]*?)<\/script>/i,
		);
		assert.ok(ldMatch, `${filename} must emit JSON-LD`);
		const graph = JSON.parse(ldMatch[1])["@graph"] as Record<string, unknown>[];
		const collectionPage = graph.find((n) => n["@type"] === "CollectionPage") as
			| {
					inLanguage: string;
					isPartOf: { "@id": string };
					about: { "@id": string };
			  }
			| undefined;
		const breadcrumbList = graph.find((n) => n["@type"] === "BreadcrumbList") as
			| { itemListElement: { name: string }[] }
			| undefined;
		const itemList = graph.find((n) => n["@type"] === "ItemList");
		assert.ok(collectionPage, `${filename} must emit CollectionPage`);
		assert.ok(breadcrumbList, `${filename} must emit BreadcrumbList`);
		assert.ok(itemList, `${filename} must emit ItemList`);
		assert.equal(collectionPage.inLanguage, lang);
		assert.equal(collectionPage.isPartOf["@id"], `${BASE_URL}/#website`);
		assert.equal(collectionPage.about["@id"], `${BASE_URL}/#business`);

		const visibleBreadcrumbs = extractVisibleBreadcrumbLabels(
			html,
			t.breadcrumbLabel,
			filename,
		);
		const schemaBreadcrumbs = breadcrumbList.itemListElement.map(
			(item) => item.name,
		);
		assert.deepEqual(visibleBreadcrumbs, [
			t.breadcrumbHome,
			hubCopy.breadcrumbLabel,
		]);
		assert.deepEqual(visibleBreadcrumbs, schemaBreadcrumbs);
	}

	assert.equal(
		seenOgAlts.size,
		LANGUAGES.length,
		"Hub ogImageAlt must be localized across all 6 locales",
	);
});

test("#311 DestinationGuideArticle & buildDestinationGuideArticleStructuredData render depth-2 articles with COP interpolation, 3-level breadcrumbs, and linked Article + FAQPage @graph across all 6 locales", () => {
	const fixtureGuide = createFixtureGuide();
	const summary = buildPricingSummary(readPricesCsvRows());

	for (const lang of LANGUAGES) {
		const t = dict[lang];
		const hubCopy = DESTINATION_GUIDES_HUB_COPY[lang];
		const localeContent = fixtureGuide.locales[lang];
		const html = renderToStaticMarkup(
			React.createElement(DestinationGuideArticle, {
				guide: fixtureGuide,
				lang,
				assetPrefix: "../../",
				pricingSummary: summary,
			}),
		);

		// Landmarks & sequential headings
		const mainMatch = html.match(/<main\b[^>]*>([\s\S]*?)<\/main>/i);
		assert.ok(mainMatch);
		assert.ok(
			!mainMatch[1].includes("<header") &&
				html.indexOf("</header>") < html.indexOf("<main"),
		);
		const levels = [...html.matchAll(/<h([1-6])\b[^>]*>/gi)].map((m) =>
			Number(m[1]),
		);
		assert.equal(levels.filter((l) => l === 1).length, 1);
		assert.equal(levels[0], 1);
		for (let i = 1; i < levels.length; i++) {
			assert.ok(levels[i] <= levels[i - 1] + 1);
		}

		// Depth-2 assetPrefix propagation (../../)
		assert.ok(html.includes(`src="../../${fixtureGuide.heroImage}"`));
		assert.ok(html.includes(`href="${getHomePagePath(lang, "../../")}"`));
		assert.ok(
			html.includes(`href="${getDestinationGuidesHubPath(lang, "../../")}"`),
		);
		assert.ok(
			html.includes(
				`href="${getDestinationGuidePath(fixtureGuide.slug, lang, "../../")}"`,
			),
		);
		assert.ok(
			html.includes(
				`href="${getPropertyPagePath("1707", lang, "../../")}" data-guide-cta-property="1707" data-guide-slug="${fixtureGuide.slug}"`,
			),
		);
		assert.ok(
			html.includes(
				`href="${getPropertyPagePath("1606", lang, "../../")}" data-guide-cta-property="1606" data-guide-slug="${fixtureGuide.slug}"`,
			),
		);

		// Semantic <time datetime> and native <details>/<summary>
		assert.ok(
			html.includes(`<time dateTime="${fixtureGuide.datePublished}"`) ||
				html.includes(`<time datetime="${fixtureGuide.datePublished}"`),
		);
		assert.ok(
			html.includes(`<time dateTime="${fixtureGuide.dateModified}"`) ||
				html.includes(`<time datetime="${fixtureGuide.dateModified}"`),
		);
		assert.ok(html.includes("<details") && html.includes("<summary"));

		// Dynamic COP interpolation with zero unresolved tokens
		const decodedHtml = decodeHtmlEntities(html);
		const expected1707 = formatCopCurrencyForLocale(
			summary.properties["1707"].lowPrice,
			lang,
		);
		const expected1606 = formatCopCurrencyForLocale(
			summary.properties["1606"].lowPrice,
			lang,
		);
		const expectedCleaning1707 = formatCopCurrencyForLocale(
			summary.properties["1707"].cleaningFeeCop,
			lang,
		);
		assert.ok(decodedHtml.includes(expected1707));
		assert.ok(decodedHtml.includes(expected1606));
		assert.ok(decodedHtml.includes(expectedCleaning1707));
		assert.doesNotMatch(
			decodedHtml,
			/\{rate1707Cop\}|\{cleaning1707Cop\}|\{buildingFeeCop\}/,
		);

		// Structured data @graph verification & 3-level Breadcrumbs 1:1 match
		const rawSchema = buildDestinationGuideArticleStructuredData(
			fixtureGuide,
			t,
			lang,
			BASE_URL,
			summary,
		);
		assert.ok(!rawSchema.includes('"USD"') && !rawSchema.includes("USD"));
		assert.doesNotMatch(rawSchema, /\{rate1707Cop\}|\{cleaning1707Cop\}/);

		const parsed = JSON.parse(rawSchema) as {
			"@graph": Record<string, unknown>[];
		};
		const graph = parsed["@graph"];
		const article = graph.find((n) => n["@type"] === "Article") as
			| {
					inLanguage: string;
					author: { "@id": string };
					publisher: { "@id": string };
					datePublished: string;
					dateModified: string;
					about: { "@id": string }[];
			  }
			| undefined;
		const breadcrumbList = graph.find((n) => n["@type"] === "BreadcrumbList") as
			| { itemListElement: { name: string }[] }
			| undefined;
		const faqPage = graph.find((n) => n["@type"] === "FAQPage") as
			| { mainEntity: { acceptedAnswer: { text: string } }[] }
			| undefined;
		const hostNode = graph.find((n) => n["@type"] === "Person");

		assert.ok(article);
		assert.ok(breadcrumbList);
		assert.ok(faqPage);
		assert.ok(hostNode);
		assert.equal(article.inLanguage, lang);
		assert.equal(article.author["@id"], `${BASE_URL}/#host`);
		assert.equal(article.publisher["@id"], `${BASE_URL}/#business`);
		assert.equal(article.datePublished, fixtureGuide.datePublished);
		assert.equal(article.dateModified, fixtureGuide.dateModified);

		const aboutIds = article.about.map((ref) => ref["@id"]);
		assert.deepEqual(aboutIds, [
			`${BASE_URL}/#business`,
			`${BASE_URL}/Oceanview1707/#rental`,
			`${BASE_URL}/Oceanview1606/#rental`,
		]);

		assert.ok(
			faqPage.mainEntity[0].acceptedAnswer.text.includes(expected1707) &&
				faqPage.mainEntity[0].acceptedAnswer.text.includes(
					expectedCleaning1707,
				),
			"Article FAQPage JSON-LD must interpolate build-time COP rates and Cleaning Fees",
		);

		const visibleBreadcrumbs = extractVisibleBreadcrumbLabels(
			html,
			t.breadcrumbLabel,
			`${fixtureGuide.slug} (${lang})`,
		);
		const schemaBreadcrumbs = breadcrumbList.itemListElement.map(
			(item) => item.name,
		);
		assert.deepEqual(visibleBreadcrumbs, [
			t.breadcrumbHome,
			hubCopy.breadcrumbLabel,
			localeContent.breadcrumbLabel,
		]);
		assert.deepEqual(visibleBreadcrumbs, schemaBreadcrumbs);
	}
});

test("#311 Navigation, Footer, Home, and Property pages provide 1-click relative Destination Guide links and enforce strict theme centralization", () => {
	for (const lang of LANGUAGES) {
		const homeFile = lang === "en" ? "index.html" : `${lang}.html`;
		const homeHtml = fs.readFileSync(path.join(DIST_DIR, homeFile), "utf-8");
		const expectedDepth0Hub = getDestinationGuidesHubPath(lang, "./");
		assert.ok(
			homeHtml.includes(`id="nav-guides" href="${expectedDepth0Hub}"`),
			`${homeFile} Navigation must include #nav-guides pointing to ${expectedDepth0Hub}`,
		);
		assert.ok(
			homeHtml.includes(
				`id="footer-guides-hub-link" href="${expectedDepth0Hub}"`,
			),
			`${homeFile} Footer must include #footer-guides-hub-link pointing to ${expectedDepth0Hub}`,
		);
		assert.ok(
			homeHtml.includes(
				`id="home-destination-guides-link" href="${expectedDepth0Hub}"`,
			),
			`${homeFile} Home #location must include contextual Destination Guide hub link`,
		);

		const expectedDepth1Hub = getDestinationGuidesHubPath(lang, "../");
		for (const propDir of ["Oceanview1707", "Oceanview1606"] as const) {
			const propFile = lang === "en" ? "index.html" : `${lang}.html`;
			const propHtml = fs.readFileSync(
				path.join(DIST_DIR, propDir, propFile),
				"utf-8",
			);
			assert.ok(
				propHtml.includes(`id="nav-guides" href="${expectedDepth1Hub}"`),
				`${propDir}/${propFile} Navigation must include #nav-guides pointing to ${expectedDepth1Hub}`,
			);
			assert.ok(
				propHtml.includes(
					`id="footer-guides-hub-link" href="${expectedDepth1Hub}"`,
				),
				`${propDir}/${propFile} Footer must include #footer-guides-hub-link`,
			);
			assert.ok(
				propHtml.includes('id="property-destination-guides"') &&
					propHtml.includes(`href="${expectedDepth1Hub}"`),
				`${propDir}/${propFile} must render #property-destination-guides section with relative links`,
			);
		}
	}

	// AGENTS.md Rule 1: Zero hardcoded inline className="..." and zero propertyId ternaries
	const themeGuardedFiles = [
		"src/components/Breadcrumbs.tsx",
		"src/components/Navigation.tsx",
		"src/components/Footer.tsx",
		"src/components/PropertyDestinationGuides.tsx",
		"src/pages/Home.tsx",
		"src/pages/DestinationGuidesHub.tsx",
		"src/pages/DestinationGuideArticle.tsx",
	];
	for (const relPath of themeGuardedFiles) {
		const source = fs.readFileSync(path.join(process.cwd(), relPath), "utf-8");
		assert.ok(
			!source.includes('className="'),
			`${relPath} must contain ZERO hardcoded inline className="..." strings`,
		);
		assert.ok(
			!source.includes("propertyId ==="),
			`${relPath} must not contain propertyId conditional ternaries`,
		);
	}
});

test("#311 main.js syncs #nav-guides on scroll and tracks Destination Guide -> Property CTA clicks in Matomo safely", () => {
	const mainJsSource = fs.readFileSync(
		path.join(PUBLIC_DIR, "js", "main.js"),
		"utf-8",
	);

	type Listener = (event?: Record<string, unknown>) => void;

	const createClassList = (initial: string[]) => {
		const set = new Set<string>(initial);
		return {
			add: (...cls: string[]) => {
				for (const c of cls) set.add(c);
			},
			remove: (...cls: string[]) => {
				for (const c of cls) set.delete(c);
			},
			contains: (cls: string) => set.has(cls),
		};
	};

	const nav = { classList: createClassList(["bg-transparent", "py-5"]) };
	const logo = {
		classList: createClassList(["text-white", "hover:text-white/80"]),
	};
	const toggle = {
		classList: createClassList([
			"bg-white/20",
			"hover:bg-white/30",
			"text-white",
			"backdrop-blur-sm",
		]),
	};
	const guides = {
		classList: createClassList(["text-white", "hover:text-white/80"]),
	};
	const contact = {
		classList: createClassList(["text-white", "hover:text-white/80"]),
	};

	const elementsById = new Map<string, unknown>([
		["navbar", nav],
		["nav-logo", logo],
		["lang-toggle", toggle],
		["nav-guides", guides],
		["nav-contact", contact],
	]);

	const winListeners: Record<string, Listener[]> = {};
	const docListeners: Record<string, Listener[]> = {};
	const scriptStub = {
		async: false,
		src: "",
		parentNode: { insertBefore: () => {} },
	};

	const paq: unknown[][] = [];
	const sandbox = {
		window: {
			_paq: paq,
			scrollY: 0,
			location: { search: "", pathname: "/santa-marta/" },
			addEventListener: (evt: string, fn: Listener) => {
				winListeners[evt] = winListeners[evt] || [];
				winListeners[evt].push(fn);
			},
		},
		document: {
			documentElement: { lang: "en" },
			createElement: () => ({ ...scriptStub }),
			getElementsByTagName: () => [scriptStub],
			getElementById: (id: string) => elementsById.get(id) ?? null,
			addEventListener: (evt: string, fn: Listener) => {
				docListeners[evt] = docListeners[evt] || [];
				docListeners[evt].push(fn);
			},
		},
		localStorage: {
			getItem: () => null,
			setItem: () => {},
		},
		console,
	};

	vm.createContext(sandbox);
	vm.runInContext(mainJsSource, sandbox);

	// 1. Scroll down > 50px -> #nav-guides transitions to dark scrolled palette
	sandbox.window.scrollY = 120;
	for (const fn of winListeners.scroll || []) fn();
	assert.equal(guides.classList.contains("text-slate-600"), true);
	assert.equal(guides.classList.contains("hover:text-rose-700"), true);
	assert.equal(guides.classList.contains("text-white"), false);

	// 2. Scroll back to top -> #nav-guides restores white top palette
	sandbox.window.scrollY = 0;
	for (const fn of winListeners.scroll || []) fn();
	assert.equal(guides.classList.contains("text-white"), true);
	assert.equal(guides.classList.contains("text-slate-600"), false);

	// 3. Delegated click on nested child inside Destination Guide CTA pushes Matomo trackEvent
	const ctaAnchor = {
		getAttribute: (name: string) => {
			if (name === "data-guide-cta-property") return "1707";
			if (name === "data-guide-slug") return "digital-nomad-remote-work";
			return null;
		},
	};
	const nestedSpanTarget = {
		closest: (selector: string) =>
			selector === "[data-guide-cta-property][data-guide-slug]"
				? ctaAnchor
				: null,
	};

	for (const fn of docListeners.click || []) {
		fn({ target: nestedSpanTarget });
	}

	const trackEvents = JSON.parse(
		JSON.stringify(paq.filter((entry) => entry[0] === "trackEvent")),
	);
	assert.deepEqual(trackEvents, [
		[
			"trackEvent",
			"DestinationGuide",
			"PropertyCTA",
			"digital-nomad-remote-work:1707",
		],
	]);

	// 4. Click on mock target without .closest() does not throw and emits no extra event
	assert.doesNotThrow(() => {
		for (const fn of docListeners.click || []) {
			fn({ target: {} });
		}
	});
	assert.equal(paq.filter((entry) => entry[0] === "trackEvent").length, 1);
});
