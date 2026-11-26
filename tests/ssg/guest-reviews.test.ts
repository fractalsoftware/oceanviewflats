import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import test from "node:test";
import {
	AIRBNB_URLS,
	HOME_EXPERIENCE_CARDS,
	PROPERTY_REVIEWS,
	type PropertyId,
} from "../../src/constants/config";
import { dict } from "../../src/i18n/dict";
import type { Lang } from "../../src/types";
import {
	calculateAggregateRatingValue,
	clampReviewRating,
} from "../../src/utils/reviews";

const DIST_DIR = path.join(process.cwd(), "dist");
const LOCALES: readonly Lang[] = ["en", "es", "fr", "it", "de", "ja"];

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

function parseVacationRentalJsonLd(html: string): Record<string, unknown> {
	const match = html.match(
		/<script type="application\/ld\+json">([\s\S]*?)<\/script>/i,
	);
	assert.ok(match, "Expected JSON-LD script block in compiled HTML");
	const parsed = JSON.parse(match[1]) as {
		"@graph": Array<Record<string, unknown>>;
	};
	const rental = parsed["@graph"].find(
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

test("#306 unified ReviewsSection component is style-agnostic and replaces legacy per-Property review components", () => {
	assert.equal(
		fs.existsSync(
			path.join(process.cwd(), "src/components/ReviewsSection1707.tsx"),
		),
		false,
		"Legacy src/components/ReviewsSection1707.tsx must be removed",
	);
	assert.equal(
		fs.existsSync(
			path.join(process.cwd(), "src/components/warm/ReviewsSection.tsx"),
		),
		false,
		"Legacy src/components/warm/ReviewsSection.tsx must be removed",
	);

	const unifiedSource = fs.readFileSync(
		path.join(process.cwd(), "src/components/ReviewsSection.tsx"),
		"utf-8",
	);
	assert.ok(
		!unifiedSource.includes('className="'),
		"ReviewsSection.tsx must source all styles from centralized THEMES tokens",
	);
	assert.ok(
		!unifiedSource.includes("propertyId ==="),
		"ReviewsSection.tsx must not contain property-conditional ternaries",
	);
	assert.ok(
		!unifiedSource.includes("useState") && !unifiedSource.includes("useEffect"),
		"ReviewsSection.tsx must remain zero-hydration static markup",
	);
});

test("#306 both Properties render Guest Reviews through the unified responsive grid with preserved themes, dynamic stars, and dynamic aggregateRating across all 6 locales", () => {
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
				section.includes("grid-cols-1 md:grid-cols-2"),
				`Property ${propertyId} (${lang}) Guest Reviews must use a responsive 2-column desktop / 1-column mobile grid`,
			);

			if (propertyId === "1707") {
				assert.ok(
					section.includes("bg-slate-50") && section.includes("bg-[#FF5A5F]/5"),
					`Property 1707 (${lang}) must preserve Slate + Coral theme tokens`,
				);
			} else {
				assert.ok(
					section.includes("bg-stone-50") && section.includes("bg-rose-100"),
					`Property 1606 (${lang}) must preserve Warm Stone + Sunset Rose theme tokens`,
				);
			}

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

test("#307 Property 1707 syncs 2 Airbnb Guest Reviews most-recent first, features Julian Alfredo on homepage, and keeps homepage JSON-LD and llms.txt free of review duplication across all 6 locales", () => {
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

	// Explicit homepage curation for Property 1707
	const card1707Config = HOME_EXPERIENCE_CARDS.find(
		(card) => card.propertyId === "1707",
	);
	assert.ok(card1707Config, "HOME_EXPERIENCE_CARDS must include Property 1707");
	assert.equal(card1707Config.reviewTextKey, "review1707_2Text");
	assert.equal(card1707Config.reviewAuthorKey, "review1707_2Author");

	// Verbatim Spanish copy for Julian Alfredo with trailing star emojis stripped
	assert.equal(
		dict.es.review1707_2Text,
		'"El apartamento súper confortable, hermoso lugar, los aires muy buenos. Amoblado confortable detalle muy lindo todo. Volvería sin ningún problema. Muchas gracias por todo"',
		"Spanish copy for Julian Alfredo must match Airbnb verbatim with trailing star emojis removed",
	);

	const julianCopies = new Set<string>();
	const starEmojiPattern = /[⭐★☆]/u;

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
			!starEmojiPattern.test(t.review1707_2Text),
			`${lang} review1707_2Text must not contain trailing star emojis`,
		);
		julianCopies.add(t.review1707_2Text);

		// Compiled Property 1707 HTML assertions
		const propertyHtml = getPropertyHtml("1707", lang);
		const section = extractSectionById(propertyHtml, "guest-reviews");
		const julianPos = section.indexOf('id="review-1707-2"');
		const karolPos = section.indexOf('id="review-1707-1"');
		assert.ok(
			julianPos !== -1 && karolPos !== -1 && julianPos < karolPos,
			`Property 1707 (${lang}) must render Julian Alfredo before Karol Ximena`,
		);

		const julianArticle = extractArticleById(section, "review-1707-2");
		const karolArticle = extractArticleById(section, "review-1707-1");
		assert.ok(
			julianArticle.includes("Julian Alfredo") &&
				julianArticle.includes(t.review1707_2Text) &&
				/<time[^>]*\bdatetime="2026-10-11"/i.test(julianArticle),
			`Property 1707 (${lang}) review-1707-2 must render Julian Alfredo, localized text, and 2026-10-11`,
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
			reviewRating: { ratingValue: string };
		}>;
		assert.equal(jsonLdReviews.length, 2);
		assert.deepEqual(
			jsonLdReviews.map((item) => ({
				author: item.author.name,
				datePublished: item.datePublished,
				inLanguage: item.inLanguage,
				reviewBody: item.reviewBody,
				ratingValue: item.reviewRating.ratingValue,
			})),
			[
				{
					author: "Julian Alfredo",
					datePublished: "2026-10-11",
					inLanguage: lang,
					reviewBody: t.review1707_2Text,
					ratingValue: "5",
				},
				{
					author: "Karol Ximena",
					datePublished: "2026-06-29",
					inLanguage: lang,
					reviewBody: t.review1707Text,
					ratingValue: "5",
				},
			],
			`Property 1707 (${lang}) JSON-LD reviews must match Airbnb order, dates, localized text, and 5-star ratings`,
		);

		// Homepage #experiences features Julian Alfredo for Property 1707 and keeps JSON-LD free of review nodes
		const homeFile = lang === "en" ? "index.html" : `${lang}.html`;
		const homeHtml = fs.readFileSync(path.join(DIST_DIR, homeFile), "utf-8");
		const experiencesSection = extractSectionById(homeHtml, "experiences");
		const cardMatches = experiencesSection.match(
			/<a[^>]*href="[^"]*Oceanview1(707|606)[^"]*"[^>]*>[\s\S]*?<\/a>/g,
		);
		assert.ok(cardMatches && cardMatches.length === 2);
		const card1707Html = cardMatches[0];
		assert.ok(
			card1707Html.includes(t.review1707_2Text) &&
				card1707Html.includes("Julian Alfredo"),
			`Homepage ${homeFile} #experiences Property 1707 card must feature Julian Alfredo's localized Guest Review`,
		);

		const homeJsonLdMatch = homeHtml.match(
			/<script type="application\/ld\+json">([\s\S]*?)<\/script>/i,
		);
		assert.ok(homeJsonLdMatch, `Homepage ${homeFile} must emit JSON-LD`);
		const homeGraph = (
			JSON.parse(homeJsonLdMatch[1]) as {
				"@graph": Array<Record<string, unknown>>;
			}
		)["@graph"];
		for (const node of homeGraph) {
			assert.equal(
				"review" in node,
				false,
				`Homepage ${homeFile} JSON-LD must not duplicate Guest Review bodies`,
			);
			assert.equal(
				"aggregateRating" in node,
				false,
				`Homepage ${homeFile} JSON-LD must not duplicate aggregateRating counts`,
			);
		}
	}

	assert.equal(
		julianCopies.size,
		LOCALES.length,
		"Julian Alfredo's Guest Review must have distinct localized copy across all 6 locales",
	);

	// LLM overview file remains free of duplicated review bodies
	const publicLlms = fs.readFileSync(
		path.join(process.cwd(), "public/llms.txt"),
		"utf-8",
	);
	const distLlms = fs.readFileSync(path.join(DIST_DIR, "llms.txt"), "utf-8");
	assert.equal(distLlms, publicLlms);
	assert.ok(
		!distLlms.includes("Julian Alfredo") && !distLlms.includes("Karol Ximena"),
		"llms.txt must remain free of duplicated Guest Review bodies or authors",
	);
});
