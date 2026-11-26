import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import test from "node:test";
import {
	AIRBNB_URLS,
	HOME_EXPERIENCE_CARDS,
	PROPERTY_REVIEWS,
	type PropertyId,
	type PropertyReviewMeta,
} from "../../src/constants/config";
import { dict } from "../../src/i18n/dict";
import type { Lang } from "../../src/types";
import {
	calculateAggregateRatingValue,
	clampReviewRating,
} from "../../src/utils/reviews";
import {
	buildPropertyStructuredData,
	DEFAULT_PRICING_SUMMARY,
} from "../../src/utils/schema";

const DIST_DIR = path.join(process.cwd(), "dist");
const LOCALES: readonly Lang[] = ["en", "es", "fr", "it", "de", "ja"];
const STAR_EMOJI_PATTERN = /[⭐★☆]/u;

const HTML_ENTITY_MAP: Record<string, string> = {
	"&#x27;": "'",
	"&#39;": "'",
	"&quot;": '"',
	"&amp;": "&",
	"&lt;": "<",
	"&gt;": ">",
};

function decodeHtmlEntities(markup: string): string {
	return markup.replace(
		/&(?:#x27|#39|quot|amp|lt|gt);/g,
		(entity) => HTML_ENTITY_MAP[entity] ?? entity,
	);
}

function getPropertyHtml(propertyId: PropertyId, lang: Lang): string {
	const fileName = lang === "en" ? "index.html" : `${lang}.html`;
	return fs.readFileSync(
		path.join(DIST_DIR, `Oceanview${propertyId}`, fileName),
		"utf-8",
	);
}

function getHomeHtml(lang: Lang): string {
	const fileName = lang === "en" ? "index.html" : `${lang}.html`;
	return fs.readFileSync(path.join(DIST_DIR, fileName), "utf-8");
}

function extractSectionById(html: string, id: string): string {
	const match = html.match(
		new RegExp(`<section\\b[^>]*id="${id}"[^>]*>[\\s\\S]*?<\\/section>`, "i"),
	);
	assert.ok(match, `Expected <section id="${id}"> in compiled HTML`);
	return decodeHtmlEntities(match[0]);
}

function extractArticleById(sectionHtml: string, id: string): string {
	const match = sectionHtml.match(
		new RegExp(`<article\\b[^>]*id="${id}"[^>]*>[\\s\\S]*?<\\/article>`, "i"),
	);
	assert.ok(match, `Expected <article id="${id}"> in Guest Reviews section`);
	return match[0];
}

function parseJsonLdGraphNodes(html: string): Array<Record<string, unknown>> {
	const match = html.match(
		/<script type="application\/ld\+json">([\s\S]*?)<\/script>/i,
	);
	assert.ok(match, "Expected JSON-LD script block in compiled HTML");
	const parsed = JSON.parse(match[1]) as {
		"@graph": Array<Record<string, unknown>>;
	};
	return parsed["@graph"];
}

function parseVacationRentalJsonLd(html: string): Record<string, unknown> {
	const rental = parseJsonLdGraphNodes(html).find(
		(node) => node["@type"] === "VacationRental",
	);
	assert.ok(rental, "Expected VacationRental node in JSON-LD @graph");
	return rental;
}

test("#306 calculateAggregateRatingValue computes one-decimal averages for uniform, mixed, fractional, clamped, and empty inputs", () => {
	// Uniform 5-star ratings
	assert.equal(calculateAggregateRatingValue([{ ratingValue: 5 }]), "5.0");
	assert.equal(
		calculateAggregateRatingValue([{ ratingValue: 5 }, { ratingValue: 5 }]),
		"5.0",
	);
	assert.equal(
		calculateAggregateRatingValue([
			{ ratingValue: 5 },
			{ ratingValue: 5 },
			{ ratingValue: 5 },
			{ ratingValue: 5 },
		]),
		"5.0",
	);
	assert.equal(
		calculateAggregateRatingValue([{ ratingValue: 4 }, { ratingValue: 4 }]),
		"4.0",
	);

	// Mixed ratings
	assert.equal(
		calculateAggregateRatingValue([{ ratingValue: 5 }, { ratingValue: 4 }]),
		"4.5",
	);
	assert.equal(
		calculateAggregateRatingValue([{ ratingValue: 5 }, { ratingValue: 3 }]),
		"4.0",
	);
	assert.equal(
		calculateAggregateRatingValue([
			{ ratingValue: 5 },
			{ ratingValue: 4 },
			{ ratingValue: 3 },
			{ ratingValue: 2 },
		]),
		"3.5",
	);

	// Fractional rounding to one decimal place
	assert.equal(
		calculateAggregateRatingValue([
			{ ratingValue: 5 },
			{ ratingValue: 5 },
			{ ratingValue: 4 },
		]),
		"4.7",
	);
	assert.equal(
		calculateAggregateRatingValue([
			{ ratingValue: 5 },
			{ ratingValue: 4 },
			{ ratingValue: 4 },
		]),
		"4.3",
	);
	assert.equal(
		calculateAggregateRatingValue([
			{ ratingValue: 5 },
			{ ratingValue: 4 },
			{ ratingValue: 4 },
			{ ratingValue: 4 },
		]),
		"4.3",
	);
	assert.equal(calculateAggregateRatingValue([{ ratingValue: 4.15 }]), "4.2");

	// Out-of-bounds ratings are clamped to 1–5
	assert.equal(
		calculateAggregateRatingValue([{ ratingValue: 7 }, { ratingValue: 5 }]),
		"5.0",
	);
	assert.equal(
		calculateAggregateRatingValue([{ ratingValue: 0 }, { ratingValue: 3 }]),
		"2.0",
	);

	// Empty and non-finite inputs
	assert.equal(calculateAggregateRatingValue([]), null);
	assert.equal(
		calculateAggregateRatingValue([{ ratingValue: Number.NaN }]),
		null,
	);
});

test("#306 clampReviewRating safely clamps star counts to the 1–5 integer scale", () => {
	assert.equal(clampReviewRating(1), 1);
	assert.equal(clampReviewRating(3), 3);
	assert.equal(clampReviewRating(5), 5);
	assert.equal(clampReviewRating(0), 1);
	assert.equal(clampReviewRating(-4), 1);
	assert.equal(clampReviewRating(6), 5);
	assert.equal(clampReviewRating(99), 5);
	assert.equal(clampReviewRating(4.4), 4);
	assert.equal(clampReviewRating(4.6), 5);
	assert.equal(clampReviewRating(Number.NaN), 1);
	assert.equal(clampReviewRating(Number.POSITIVE_INFINITY), 1);
});

test("#306 buildPropertyStructuredData keeps reviewRating, aggregateRating.ratingValue, and reviewCount consistent on out-of-range and non-finite inputs", () => {
	const mixedReviews: PropertyReviewMeta[] = [
		{
			id: "review-clamped-high",
			authorKey: "review1707_2Author",
			textKey: "review1707_2Text",
			datePublished: "2026-10-11",
			ratingValue: 7,
		},
		{
			id: "review-clamped-low",
			authorKey: "review1707Author",
			textKey: "review1707Text",
			datePublished: "2026-06-29",
			ratingValue: 0,
		},
		{
			id: "review-non-finite",
			authorKey: "review1Author",
			textKey: "review1Text",
			datePublished: "2026-04-06",
			ratingValue: Number.NaN,
		},
	];

	const parsedMixed = JSON.parse(
		buildPropertyStructuredData(
			"1707",
			dict.en,
			"en",
			"https://oceanviewflats.com",
			DEFAULT_PRICING_SUMMARY,
			mixedReviews,
		),
	) as { "@graph": Array<Record<string, unknown>> };
	const mixedRental = parsedMixed["@graph"].find(
		(node) => node["@type"] === "VacationRental",
	);
	assert.ok(mixedRental, "Expected VacationRental node in JSON-LD @graph");

	const mixedAggregate = mixedRental.aggregateRating as {
		ratingValue: string;
		reviewCount: string;
	};
	const mixedReviewNodes = mixedRental.review as Array<{
		reviewRating: { ratingValue: string };
	}>;
	assert.equal(mixedReviewNodes.length, 2);
	assert.deepEqual(
		mixedReviewNodes.map((node) => node.reviewRating.ratingValue),
		["5", "1"],
	);
	assert.equal(mixedAggregate.ratingValue, "3.0");
	assert.equal(mixedAggregate.reviewCount, "2");

	const parsedEmpty = JSON.parse(
		buildPropertyStructuredData(
			"1707",
			dict.en,
			"en",
			"https://oceanviewflats.com",
			DEFAULT_PRICING_SUMMARY,
			[
				{
					id: "review-invalid-only",
					authorKey: "review1707Author",
					textKey: "review1707Text",
					datePublished: "2026-06-29",
					ratingValue: Number.NaN,
				},
			],
		),
	) as { "@graph": Array<Record<string, unknown>> };
	const emptyRental = parsedEmpty["@graph"].find(
		(node) => node["@type"] === "VacationRental",
	);
	assert.ok(emptyRental, "Expected VacationRental node in JSON-LD @graph");
	assert.equal("aggregateRating" in emptyRental, false);
	assert.deepEqual(emptyRental.review, []);
});

test("#306 both Properties render Guest Reviews with semantic markup, localized badges, dynamic stars, Airbnb attribution, and dynamic aggregateRating across all 6 locales", () => {
	for (const propertyId of ["1707", "1606"] as const) {
		const expectedReviews = PROPERTY_REVIEWS[propertyId];
		const expectedAggregateRating =
			calculateAggregateRatingValue(expectedReviews);
		assert.equal(
			expectedAggregateRating,
			"5.0",
			`Property ${propertyId} existing 5-star Guest Reviews must aggregate to "5.0"`,
		);

		for (const lang of LOCALES) {
			const html = getPropertyHtml(propertyId, lang);
			const t = dict[lang];
			const section = extractSectionById(html, "guest-reviews");

			assert.ok(
				section.includes('aria-labelledby="guest-reviews-heading"'),
				`Property ${propertyId} (${lang}) Guest Reviews section must declare aria-labelledby`,
			);
			assert.ok(
				section.includes('<h2 id="guest-reviews-heading"'),
				`Property ${propertyId} (${lang}) Guest Reviews section must render matching h2 heading`,
			);
			assert.ok(
				section.includes(t.reviewVerifiedGuest),
				`Property ${propertyId} (${lang}) Guest Reviews section must render localized Verified Guest badge`,
			);

			const articleMatches = section.match(/<article\b[^>]*>/g) ?? [];
			assert.equal(
				articleMatches.length,
				expectedReviews.length,
				`Property ${propertyId} (${lang}) must render all ${expectedReviews.length} configured Guest Reviews`,
			);

			for (const review of expectedReviews) {
				const article = extractArticleById(section, review.id);
				const starMatches = article.match(/\blucide-star\b/g) ?? [];
				assert.equal(
					starMatches.length,
					clampReviewRating(review.ratingValue),
					`Property ${propertyId} (${lang}) review ${review.id} must render ${clampReviewRating(review.ratingValue)} star icons`,
				);
				assert.ok(
					article.includes(t[review.textKey]),
					`Property ${propertyId} (${lang}) review ${review.id} must render localized review body ${review.textKey}`,
				);
				assert.ok(
					article.includes(t[review.authorKey]),
					`Property ${propertyId} (${lang}) review ${review.id} must render author ${t[review.authorKey]}`,
				);
				assert.ok(
					new RegExp(
						`<time[^>]*\\bdatetime="${review.datePublished}"`,
						"i",
					).test(article),
					`Property ${propertyId} (${lang}) review ${review.id} must render semantic <time datetime="${review.datePublished}">`,
				);
			}

			assert.ok(
				section.includes(AIRBNB_URLS[propertyId]) &&
					section.includes(t.reviewViewOnAirbnb),
				`Property ${propertyId} (${lang}) Guest Reviews section must include Property-specific Airbnb attribution link`,
			);

			// Verify VacationRental JSON-LD aggregateRating matches dynamic helper
			const rental = parseVacationRentalJsonLd(html);
			const aggregateRating = rental.aggregateRating as {
				ratingValue: string;
				reviewCount: string;
			};
			assert.equal(
				aggregateRating.ratingValue,
				expectedAggregateRating,
				`Property ${propertyId} (${lang}) VacationRental.aggregateRating.ratingValue must match calculated aggregate`,
			);
			assert.equal(
				aggregateRating.reviewCount,
				String(expectedReviews.length),
				`Property ${propertyId} (${lang}) VacationRental.aggregateRating.reviewCount must match review count`,
			);
		}
	}
});

test("#307 Property 1707 renders 2 synced Airbnb Guest Reviews most-recent first with exact dates, verbatim Spanish copy, Latin-script authors, stripped star emojis, and matching JSON-LD across all 6 locales", () => {
	const reviews1707 = PROPERTY_REVIEWS["1707"];
	assert.equal(
		reviews1707.length,
		2,
		"Property 1707 must configure exactly 2 Airbnb Guest Reviews",
	);
	assert.deepEqual(
		reviews1707.map((review) => ({
			id: review.id,
			authorKey: review.authorKey,
			textKey: review.textKey,
			datePublished: review.datePublished,
			ratingValue: review.ratingValue,
		})),
		[
			{
				id: "review-1707-2",
				authorKey: "review1707_2Author",
				textKey: "review1707_2Text",
				datePublished: "2026-10-11",
				ratingValue: 5,
			},
			{
				id: "review-1707-1",
				authorKey: "review1707Author",
				textKey: "review1707Text",
				datePublished: "2026-06-29",
				ratingValue: 5,
			},
		],
		"Property 1707 Guest Reviews must be ordered most-recent first with exact Airbnb publication dates",
	);

	// Verbatim Spanish copy for Julian Alfredo with trailing star emojis stripped
	assert.equal(
		dict.es.review1707_2Text,
		'"El apartamento súper confortable, hermoso lugar, los aires muy buenos. Amoblado confortable detalle muy lindo todo. Volvería sin ningún problema. Muchas gracias por todo"',
		"Spanish copy for Julian Alfredo must match Airbnb verbatim with trailing star emojis removed",
	);

	const julianCopies = new Set<string>();

	for (const lang of LOCALES) {
		const t = dict[lang];

		// Latin-script author names across all 6 locales (including ja)
		assert.equal(
			t.review1707_2Author,
			"Julian Alfredo",
			`${lang} review1707_2Author must use original Latin script`,
		);
		assert.equal(
			t.review1707Author,
			"Karol Ximena",
			`${lang} review1707Author must use original Latin script`,
		);
		assert.ok(
			!STAR_EMOJI_PATTERN.test(t.review1707_2Text),
			`${lang} review1707_2Text must not contain trailing star emojis`,
		);
		julianCopies.add(t.review1707_2Text);

		// Compiled Property 1707 HTML assertions
		const propertyHtml = getPropertyHtml("1707", lang);
		const section = extractSectionById(propertyHtml, "guest-reviews");
		const renderedIds = Array.from(
			section.matchAll(/<article\b[^>]*id="([^"]+)"/gi),
			(match) => match[1],
		);
		assert.deepEqual(
			renderedIds,
			["review-1707-2", "review-1707-1"],
			`Property 1707 (${lang}) must render Julian Alfredo before Karol Ximena`,
		);

		const julianArticle = extractArticleById(section, "review-1707-2");
		const karolArticle = extractArticleById(section, "review-1707-1");
		assert.ok(
			julianArticle.includes("Julian Alfredo") &&
				julianArticle.includes(t.review1707_2Text) &&
				!STAR_EMOJI_PATTERN.test(julianArticle) &&
				/<time[^>]*\bdatetime="2026-10-11"/i.test(julianArticle),
			`Property 1707 (${lang}) review-1707-2 must render Julian Alfredo, localized text without star emojis, and 2026-10-11`,
		);
		assert.ok(
			karolArticle.includes("Karol Ximena") &&
				karolArticle.includes(t.review1707Text) &&
				/<time[^>]*\bdatetime="2026-06-29"/i.test(karolArticle),
			`Property 1707 (${lang}) review-1707-1 must render Karol Ximena, localized text, and 2026-06-29`,
		);

		// Compiled Property 1707 VacationRental JSON-LD assertions
		const rental = parseVacationRentalJsonLd(propertyHtml);
		const aggregateRating = rental.aggregateRating as {
			ratingValue: string;
			reviewCount: string;
		};
		assert.equal(aggregateRating.reviewCount, "2");
		assert.equal(aggregateRating.ratingValue, "5.0");

		const jsonLdReviews = rental.review as Array<{
			author: { name: string };
			datePublished: string;
			inLanguage: string;
			reviewBody: string;
			reviewRating: {
				ratingValue: string;
				bestRating: string;
				worstRating: string;
			};
		}>;
		assert.equal(jsonLdReviews.length, 2);
		assert.deepEqual(
			jsonLdReviews.map((item) => ({
				author: item.author.name,
				datePublished: item.datePublished,
				inLanguage: item.inLanguage,
				reviewBody: item.reviewBody,
				ratingValue: item.reviewRating.ratingValue,
				bestRating: item.reviewRating.bestRating,
				worstRating: item.reviewRating.worstRating,
			})),
			[
				{
					author: "Julian Alfredo",
					datePublished: "2026-10-11",
					inLanguage: lang,
					reviewBody: t.review1707_2Text,
					ratingValue: "5",
					bestRating: "5",
					worstRating: "1",
				},
				{
					author: "Karol Ximena",
					datePublished: "2026-06-29",
					inLanguage: lang,
					reviewBody: t.review1707Text,
					ratingValue: "5",
					bestRating: "5",
					worstRating: "1",
				},
			],
			`Property 1707 (${lang}) JSON-LD reviews must match Airbnb order, dates, localized text, and 5-star ratings`,
		);
	}

	assert.equal(
		julianCopies.size,
		LOCALES.length,
		"Julian Alfredo's Guest Review must have distinct localized copy across all 6 locales",
	);
});

test("#308 Property 1606 renders all 4 synced Airbnb Guest Reviews most-recent first with exact dates, verbatim Spanish copy, Latin-script authors, Italian typo fix, and matching JSON-LD across all 6 locales", () => {
	const expectedOrder = [
		{
			id: "review-1606-3",
			author: "Rubiela Rubina",
			datePublished: "2026-10-12",
			textKey: "review3Text" as const,
			authorKey: "review3Author" as const,
			ratingValue: 5,
		},
		{
			id: "review-1606-4",
			author: "Pedro",
			datePublished: "2026-10-07",
			textKey: "review4Text" as const,
			authorKey: "review4Author" as const,
			ratingValue: 5,
		},
		{
			id: "review-1606-1",
			author: "Lina Marcela",
			datePublished: "2026-04-06",
			textKey: "review1Text" as const,
			authorKey: "review1Author" as const,
			ratingValue: 5,
		},
		{
			id: "review-1606-2",
			author: "Sheyla",
			datePublished: "2026-03-23",
			textKey: "review2Text" as const,
			authorKey: "review2Author" as const,
			ratingValue: 5,
		},
	];

	const verbatimRubielaEs = '"Excelente ubicación y anfitrión, volveré"';
	const verbatimPedroEs = '"El apartamento super cómodo, bonito, bien ubicado"';

	assert.equal(dict.es.review3Text, verbatimRubielaEs);
	assert.equal(dict.es.review4Text, verbatimPedroEs);
	assert.equal(PROPERTY_REVIEWS["1606"].length, 4);

	for (const lang of LOCALES) {
		const html = getPropertyHtml("1606", lang);
		const t = dict[lang];
		const section = extractSectionById(html, "guest-reviews");

		// Verify exact most-recent-first DOM order of the 4 review articles
		const renderedIds = Array.from(
			section.matchAll(/<article\b[^>]*id="([^"]+)"/gi),
			(match) => match[1],
		);
		assert.deepEqual(
			renderedIds,
			expectedOrder.map((entry) => entry.id),
			`Property 1606 (${lang}) must render all 4 Guest Reviews in most-recent-first order`,
		);

		// Verify localized badges and Airbnb link
		assert.ok(
			section.includes(t.reviewVerifiedGuest),
			`Property 1606 (${lang}) must render localized Verified Guest badge`,
		);
		assert.ok(
			section.includes(AIRBNB_URLS["1606"]) &&
				section.includes(t.reviewViewOnAirbnb),
			`Property 1606 (${lang}) must include the Airbnb verification link`,
		);

		for (const expected of expectedOrder) {
			assert.equal(
				t[expected.authorKey],
				expected.author,
				`Property 1606 (${lang}) ${expected.authorKey} must be in original Latin script "${expected.author}"`,
			);

			if (
				lang !== "es" &&
				(expected.textKey === "review3Text" ||
					expected.textKey === "review4Text")
			) {
				assert.notEqual(
					t[expected.textKey],
					dict.es[expected.textKey],
					`Property 1606 (${lang}) ${expected.textKey} must be translated into ${lang} rather than duplicating Spanish`,
				);
			}

			const article = extractArticleById(section, expected.id);
			assert.ok(
				article.includes(t[expected.textKey]),
				`Property 1606 (${lang}) ${expected.id} must include localized review text`,
			);
			assert.ok(
				article.includes(expected.author),
				`Property 1606 (${lang}) ${expected.id} must display Latin-script author ${expected.author}`,
			);
			assert.ok(
				new RegExp(
					`<time[^>]*\\bdatetime="${expected.datePublished}"`,
					"i",
				).test(article),
				`Property 1606 (${lang}) ${expected.id} must render <time datetime="${expected.datePublished}">`,
			);
			const starMatches = article.match(/\blucide-star\b/g) ?? [];
			assert.equal(
				starMatches.length,
				expected.ratingValue,
				`Property 1606 (${lang}) ${expected.id} must render ${expected.ratingValue} star icons`,
			);
		}

		// Italian typo fix verification
		if (lang === "it") {
			assert.ok(
				section.includes("Il servizio"),
				'Property 1606 (it) must use corrected Italian spelling "Il servizio"',
			);
			assert.ok(
				!html.includes("Il servicio"),
				'Property 1606 (it) must not contain misspelled "Il servicio"',
			);
		}

		// Japanese Latin-script standardization verification
		if (lang === "ja") {
			assert.ok(
				!html.includes("リナ・マルセラ") && !html.includes("シェイラ"),
				"Property 1606 (ja) must use Latin script for Lina Marcela and Sheyla instead of Katakana",
			);
		}

		// Property 1606 VacationRental JSON-LD verification
		const rental = parseVacationRentalJsonLd(html);
		const aggregateRating = rental.aggregateRating as {
			ratingValue: string;
			reviewCount: string;
		};
		assert.equal(
			aggregateRating.reviewCount,
			"4",
			`Property 1606 (${lang}) JSON-LD aggregateRating.reviewCount must be "4"`,
		);
		assert.equal(
			aggregateRating.ratingValue,
			"5.0",
			`Property 1606 (${lang}) JSON-LD aggregateRating.ratingValue must be "5.0"`,
		);

		const jsonLdReviews = rental.review as Array<{
			author: { name: string };
			datePublished: string;
			reviewBody: string;
			inLanguage: string;
			reviewRating: {
				ratingValue: string;
				bestRating: string;
				worstRating: string;
			};
		}>;
		assert.equal(
			jsonLdReviews.length,
			4,
			`Property 1606 (${lang}) JSON-LD VacationRental.review must contain all 4 reviews`,
		);
		expectedOrder.forEach((expected, index) => {
			const entry = jsonLdReviews[index];
			assert.equal(entry.author.name, expected.author);
			assert.equal(entry.datePublished, expected.datePublished);
			assert.equal(entry.reviewBody, t[expected.textKey]);
			assert.equal(entry.inLanguage, lang);
			assert.equal(entry.reviewRating.ratingValue, "5");
			assert.equal(entry.reviewRating.bestRating, "5");
			assert.equal(entry.reviewRating.worstRating, "1");
		});
	}
});

test("#307 #308 Homepage #experiences features curated quotes for both Properties and keeps homepage JSON-LD and llms.txt free of duplicated Guest Reviews across all 6 locales", () => {
	// Explicit homepage curation config for both Properties
	const card1707Config = HOME_EXPERIENCE_CARDS.find(
		(card) => card.propertyId === "1707",
	);
	assert.ok(card1707Config, "HOME_EXPERIENCE_CARDS must include Property 1707");
	assert.equal(card1707Config.reviewTextKey, "review1707_2Text");
	assert.equal(card1707Config.reviewAuthorKey, "review1707_2Author");

	const card1606Config = HOME_EXPERIENCE_CARDS.find(
		(card) => card.propertyId === "1606",
	);
	assert.ok(card1606Config, "HOME_EXPERIENCE_CARDS must include Property 1606");
	assert.equal(card1606Config.reviewTextKey, "review1Text");
	assert.equal(card1606Config.reviewAuthorKey, "review1Author");

	for (const lang of LOCALES) {
		const homeFile = lang === "en" ? "index.html" : `${lang}.html`;
		const html = getHomeHtml(lang);
		const t = dict[lang];
		const experiencesSection = extractSectionById(html, "experiences");

		const card1707Match = experiencesSection.match(
			/<a[^>]*href="[^"]*Oceanview1707[^"]*"[^>]*>[\s\S]*?<\/a>/i,
		);
		assert.ok(
			card1707Match,
			`Homepage ${homeFile} #experiences must include the Property 1707 card`,
		);
		const card1707Html = card1707Match[0];
		assert.ok(
			card1707Html.includes(t.review1707_2Text) &&
				card1707Html.includes("Julian Alfredo") &&
				!STAR_EMOJI_PATTERN.test(card1707Html),
			`Homepage ${homeFile} #experiences Property 1707 card must feature Julian Alfredo's localized Guest Review without star emojis`,
		);

		const card1606Match = experiencesSection.match(
			/<a[^>]*href="[^"]*Oceanview1606[^"]*"[^>]*>[\s\S]*?<\/a>/i,
		);
		assert.ok(
			card1606Match,
			`Homepage ${homeFile} #experiences must include the Property 1606 card`,
		);
		const card1606Html = card1606Match[0];
		assert.ok(
			card1606Html.includes(t.review1Text),
			`Homepage ${homeFile} Property 1606 card must retain Lina Marcela's curated review body`,
		);
		assert.ok(
			card1606Html.includes("Lina Marcela"),
			`Homepage ${homeFile} Property 1606 card must display author "Lina Marcela" in Latin script`,
		);
		assert.ok(
			!card1606Html.includes("Rubiela Rubina") &&
				!card1606Html.includes("Pedro"),
			`Homepage ${homeFile} Property 1606 card must not replace Lina Marcela with brief October reviews`,
		);

		if (lang === "it") {
			assert.ok(
				card1606Html.includes("Il servizio") && !html.includes("Il servicio"),
				'Homepage (it) Property 1606 card must reflect corrected Italian spelling "Il servizio"',
			);
		}
		if (lang === "ja") {
			assert.ok(
				!html.includes("リナ・マルセラ"),
				"Homepage (ja) must not contain Katakana transliteration for Lina Marcela",
			);
		}

		// Homepage JSON-LD must remain free of duplicated reviews or aggregate ratings
		const homeGraph = parseJsonLdGraphNodes(html);
		for (const node of homeGraph) {
			assert.equal(
				"review" in node,
				false,
				`Homepage ${homeFile} JSON-LD node ${String(node["@type"])} must not duplicate Guest Reviews`,
			);
			assert.equal(
				"aggregateRating" in node,
				false,
				`Homepage ${homeFile} JSON-LD node ${String(node["@type"])} must not duplicate aggregateRating`,
			);
		}
	}

	// Generated LLM discovery files must remain free of duplicated Guest Review bodies or authors across all 6 reviews
	const distLlms = fs.readFileSync(path.join(DIST_DIR, "llms.txt"), "utf-8");
	const distLlmsFull = fs.readFileSync(
		path.join(DIST_DIR, "llms-full.txt"),
		"utf-8",
	);
	assert.ok(distLlms.length > 0, "Expected generated dist/llms.txt");
	assert.ok(distLlmsFull.length > 0, "Expected generated dist/llms-full.txt");
	for (const [label, content] of [
		["dist/llms.txt", distLlms],
		["dist/llms-full.txt", distLlmsFull],
	] as const) {
		for (const forbidden of [
			"Julian Alfredo",
			"Karol Ximena",
			"Rubiela Rubina",
			"Pedro",
			"Lina Marcela",
			"Sheyla",
			"El apartamento súper confortable",
			"Excelente ubicación y anfitrión",
			"El apartamento super cómodo",
		]) {
			assert.ok(
				!content.includes(forbidden),
				`${label} must not duplicate Guest Review content ("${forbidden}")`,
			);
		}
	}
});
