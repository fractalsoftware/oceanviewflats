import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import test from "node:test";
import {
	AIRBNB_URLS,
	DATA_CONTROLLER,
	PROPERTY_1606_FAQ_ITEMS,
	PROPERTY_1707_FAQ_ITEMS,
	PROPERTY_FAQ_ITEMS,
	PROPERTY_REVIEWS,
} from "../../src/constants/config";
import { dict } from "../../src/i18n/dict";
import type { Lang } from "../../src/types";

const DIST_DIR = path.join(process.cwd(), "dist");
const LOCALES: readonly Lang[] = ["en", "es", "fr", "it", "de", "ja"];

const decodeHtmlEntities = (markup: string): string =>
	markup
		.replaceAll("&#x27;", "'")
		.replaceAll("&#39;", "'")
		.replaceAll("&quot;", '"')
		.replaceAll("&amp;", "&")
		.replaceAll("&lt;", "<")
		.replaceAll("&gt;", ">");

const getPropertyHtml = (propertyId: "1707" | "1606", lang: Lang): string => {
	const dir = propertyId === "1707" ? "Oceanview1707" : "Oceanview1606";
	const fileName = lang === "en" ? "index.html" : `${lang}.html`;
	const fullPath = path.join(DIST_DIR, dir, fileName);
	assert.ok(fs.existsSync(fullPath), `Expected compiled file: ${fullPath}`);
	return fs.readFileSync(fullPath, "utf-8");
};

const extractMainMarkup = (html: string): string => {
	const match = html.match(/<main[^>]*>([\s\S]*?)<\/main>/);
	assert.ok(match, "Compiled HTML must contain a <main> element");
	return decodeHtmlEntities(match[1]);
};

const extractSectionById = (html: string, id: string): string => {
	const regex = new RegExp(
		`<section[^>]*id="${id}"[^>]*>[\\s\\S]*?<\\/section>`,
	);
	const match = html.match(regex);
	assert.ok(match, `Expected <section id="${id}"> in compiled HTML`);
	return decodeHtmlEntities(match[0]);
};

test("#301 Property 1606 has zero 17th-floor leakage and renders 16th-floor, Bay 87 & reconciled 3-bed details across all 6 locales", () => {
	const forbidden17thFloorPatterns = [
		/17th[- ]floor/i,
		/piso 17/i,
		/17(?:ème|e)\s+étage/i,
		/17°\s*piano/i,
		/17\.\s*(?:Stock|Etage)/i,
		/17階/,
	];

	const forbiddenPluralDoubleBedPatterns = [
		/double beds/i,
		/camas dobles/i,
		/lits doubles/i,
		/letti doppi/i,
		/Doppelbetten/i,
	];

	for (const lang of LOCALES) {
		const html = getPropertyHtml("1606", lang);
		const main = extractMainMarkup(html);
		const t = dict[lang];

		for (const pattern of forbidden17thFloorPatterns) {
			assert.ok(
				!pattern.test(main),
				`Property 1606 (${lang}) <main> must not leak 17th-floor copy (${pattern})`,
			);
		}

		for (const pattern of forbiddenPluralDoubleBedPatterns) {
			assert.ok(
				!pattern.test(main),
				`Property 1606 (${lang}) <main> must not contradict 3-bed layout with plural double beds (${pattern})`,
			);
		}

		assert.ok(
			main.includes(t.about1606Text1),
			`Property 1606 (${lang}) must render about1606Text1`,
		);
		assert.ok(
			main.includes(t.about1606Text2),
			`Property 1606 (${lang}) must render about1606Text2`,
		);
		assert.ok(
			main.includes(t.stats1606Beds),
			`Property 1606 (${lang}) must render stats1606Beds`,
		);
		assert.ok(
			main.includes("87"),
			`Property 1606 (${lang}) must reference Assigned Parking Space Bay 87`,
		);
		assert.ok(
			!main.includes(DATA_CONTROLLER.rnt1707),
			`Property 1606 (${lang}) <main> must not display Property 1707's RNT`,
		);
	}
});

test("#301 Property 1707 renders 17th-floor, Bay 95 & reconciled 4-bed details across all 6 locales", () => {
	for (const lang of LOCALES) {
		const html = getPropertyHtml("1707", lang);
		const main = extractMainMarkup(html);
		const t = dict[lang];

		assert.ok(
			main.includes(t.aboutText1),
			`Property 1707 (${lang}) must render aboutText1`,
		);
		assert.ok(
			main.includes(t.aboutText2),
			`Property 1707 (${lang}) must render aboutText2`,
		);
		assert.ok(
			main.includes(t.statsBeds),
			`Property 1707 (${lang}) must render statsBeds`,
		);
		assert.ok(
			main.includes("95"),
			`Property 1707 (${lang}) must reference Assigned Parking Space Bay 95`,
		);
		assert.ok(
			main.includes("900"),
			`Property 1707 (${lang}) must reference 900 Mbps fiber Wi-Fi`,
		);
		assert.ok(
			!main.includes(DATA_CONTROLLER.rnt1606),
			`Property 1707 (${lang}) <main> must not display Property 1606's RNT`,
		);
	}
});

test("#301 Direct Reservation calendarSubtitle removes stale Airbnb-redirect copy across all 6 locales", () => {
	const legacyRedirectPatterns = [
		/redirected to Airbnb/i,
		/redirigido de forma segura a Airbnb/i,
		/redirigé en toute sécurité vers Airbnb/i,
		/reindirizzato in modo sicuro su Airbnb/i,
		/sicher zu Airbnb weitergeleitet/i,
		/Airbnbに安全にリダイレクト/,
	];

	for (const propertyId of ["1707", "1606"] as const) {
		for (const lang of LOCALES) {
			const html = decodeHtmlEntities(getPropertyHtml(propertyId, lang));
			const t = dict[lang];

			for (const pattern of legacyRedirectPatterns) {
				assert.ok(
					!pattern.test(html),
					`Property ${propertyId} (${lang}) must not contain legacy Airbnb-redirect wording (${pattern})`,
				);
			}

			assert.ok(
				html.includes(t.calendarSubtitle),
				`Property ${propertyId} (${lang}) must render updated calendarSubtitle`,
			);
			assert.ok(
				t.calendarSubtitle.includes("COP"),
				`calendarSubtitle (${lang}) must mention authoritative COP Quotes`,
			);
			assert.ok(
				t.calendarSubtitle.includes("PSE"),
				`calendarSubtitle (${lang}) must mention PSE payment rail`,
			);
			assert.ok(
				t.calendarSubtitle.includes("Mercado Pago"),
				`calendarSubtitle (${lang}) must mention Mercado Pago checkout`,
			);
			assert.ok(
				/0\s*%/.test(t.calendarSubtitle),
				`calendarSubtitle (${lang}) must mention 0% OTA commission`,
			);
		}
	}
});

test("#301 Per-Property progressive-disclosure FAQ sections render cleanly across all 6 locales and export typed configs for #303", () => {
	assert.equal(PROPERTY_1707_FAQ_ITEMS.length, 6);
	assert.equal(PROPERTY_1606_FAQ_ITEMS.length, 6);
	assert.strictEqual(PROPERTY_FAQ_ITEMS["1707"], PROPERTY_1707_FAQ_ITEMS);
	assert.strictEqual(PROPERTY_FAQ_ITEMS["1606"], PROPERTY_1606_FAQ_ITEMS);

	// Verify PropertyFaq.tsx stays 100% theme-driven (no hardcoded className literals or propertyId ternaries)
	const propertyFaqSource = fs.readFileSync(
		path.join(process.cwd(), "src/components/PropertyFaq.tsx"),
		"utf-8",
	);
	assert.ok(
		!propertyFaqSource.includes('className="'),
		"PropertyFaq.tsx must source all styles from centralized theme tokens",
	);
	assert.ok(
		!propertyFaqSource.includes("propertyId ==="),
		"PropertyFaq.tsx must not contain property-conditional ternaries",
	);

	for (const propertyId of ["1707", "1606"] as const) {
		const expectedItems = PROPERTY_FAQ_ITEMS[propertyId];
		const expectedBay = propertyId === "1707" ? "95" : "87";
		const expectedRnt =
			propertyId === "1707" ? DATA_CONTROLLER.rnt1707 : DATA_CONTROLLER.rnt1606;
		const expectedRatePattern =
			propertyId === "1707" ? /450[.,\s]000/ : /350[.,\s]000/;
		const expectedCleaningPattern =
			propertyId === "1707" ? /100[.,\s]000/ : /80[.,\s]000/;

		for (const lang of LOCALES) {
			const html = getPropertyHtml(propertyId, lang);
			const faqSection = extractSectionById(html, "property-faq");
			const t = dict[lang];

			assert.ok(
				faqSection.includes('aria-labelledby="property-faq-heading"'),
				`Property ${propertyId} (${lang}) FAQ section must declare aria-labelledby`,
			);
			assert.ok(
				faqSection.includes('<h2 id="property-faq-heading"'),
				`Property ${propertyId} (${lang}) FAQ section must have matching h2 heading`,
			);

			const detailsMatches = faqSection.match(/<details\b[^>]*>/g) ?? [];
			const summaryMatches =
				faqSection.match(/<summary\b[^>]*>[\s\S]*?<\/summary>/g) ?? [];
			assert.equal(
				detailsMatches.length,
				expectedItems.length,
				`Property ${propertyId} (${lang}) must render ${expectedItems.length} <details> FAQ items`,
			);
			assert.equal(
				summaryMatches.length,
				expectedItems.length,
				`Property ${propertyId} (${lang}) must render ${expectedItems.length} <summary> triggers`,
			);

			for (const detailsTag of detailsMatches) {
				assert.ok(
					!/\bname=/.test(detailsTag),
					`Property ${propertyId} (${lang}) FAQ <details> should remain non-exclusive`,
				);
			}

			for (const summaryBlock of summaryMatches) {
				assert.ok(
					!/<h[1-6]\b/i.test(summaryBlock),
					`Property ${propertyId} (${lang}) FAQ <summary> must not contain heading tags`,
				);
			}

			for (const item of expectedItems) {
				assert.ok(
					faqSection.includes(`id="${item.id}"`),
					`Property ${propertyId} (${lang}) FAQ must include id="${item.id}"`,
				);
				assert.ok(
					faqSection.includes(t[item.qKey]),
					`Property ${propertyId} (${lang}) FAQ must render ${item.qKey}`,
				);
				assert.ok(
					faqSection.includes(t[item.aKey]),
					`Property ${propertyId} (${lang}) FAQ must render ${item.aKey}`,
				);
			}

			assert.ok(
				faqSection.includes("15:00") && faqSection.includes("11:00"),
				`Property ${propertyId} (${lang}) FAQ must state 15:00 check-in and 11:00 check-out`,
			);
			assert.ok(
				faqSection.includes("0XXXXXX#"),
				`Property ${propertyId} (${lang}) FAQ must explain 0XXXXXX# Access Credential unlock`,
			);
			assert.ok(
				/20[.,\s]000\s*COP/.test(faqSection),
				`Property ${propertyId} (${lang}) FAQ must disclose 20,000 COP Building Registration Fee`,
			);
			assert.ok(
				faqSection.includes(expectedBay),
				`Property ${propertyId} (${lang}) FAQ must disclose Assigned Parking Space Bay ${expectedBay}`,
			);
			assert.ok(
				faqSection.includes(expectedRnt),
				`Property ${propertyId} (${lang}) FAQ must include ${expectedRnt}`,
			);
			assert.ok(
				expectedRatePattern.test(faqSection),
				`Property ${propertyId} (${lang}) FAQ must disclose baseline COP Nightly Rate`,
			);
			assert.ok(
				expectedCleaningPattern.test(faqSection),
				`Property ${propertyId} (${lang}) FAQ must disclose Cleaning Fee`,
			);
		}
	}
});

test("#301 E-E-A-T host credentials, statutory compliance badges & dated verified reviews render consistently on both Properties across all 6 locales", () => {
	for (const propertyId of ["1707", "1606"] as const) {
		const expectedRnt =
			propertyId === "1707" ? DATA_CONTROLLER.rnt1707 : DATA_CONTROLLER.rnt1606;
		const expectedAirbnbUrl = AIRBNB_URLS[propertyId];
		const expectedReviews = PROPERTY_REVIEWS[propertyId];

		for (const lang of LOCALES) {
			const html = getPropertyHtml(propertyId, lang);
			const main = extractMainMarkup(html);
			const t = dict[lang];

			// Host / Data Controller credentials & direct contact channels
			assert.ok(
				main.includes("Manuel Enrique Herrera"),
				`Property ${propertyId} (${lang}) must display Data Controller full name Manuel Enrique Herrera`,
			);
			assert.ok(
				main.includes(expectedRnt),
				`Property ${propertyId} (${lang}) must display official ${expectedRnt} badge`,
			);
			assert.ok(
				main.includes(DATA_CONTROLLER.whatsappHref),
				`Property ${propertyId} (${lang}) must include direct WhatsApp link`,
			);
			assert.ok(
				main.includes(`mailto:${DATA_CONTROLLER.email}`),
				`Property ${propertyId} (${lang}) must include direct email link`,
			);

			// Colombian statutory compliance disclosures (Law 1581, Law 2068, Law 679)
			assert.ok(
				main.includes(t.complianceLaw1581Badge),
				`Property ${propertyId} (${lang}) must display Law 1581 badge`,
			);
			assert.ok(
				main.includes(t.complianceLaw2068Badge),
				`Property ${propertyId} (${lang}) must display Law 2068 badge`,
			);
			assert.ok(
				main.includes(t.complianceLaw679Badge),
				`Property ${propertyId} (${lang}) must display Law 679 badge`,
			);

			// Verified reviews with localized badge, semantic <time datetime>, and Airbnb attribution
			assert.ok(
				main.includes(t.reviewVerifiedGuest),
				`Property ${propertyId} (${lang}) must render localized Verified Guest label`,
			);
			if (lang !== "en") {
				assert.ok(
					!main.includes(">Verified Guest<"),
					`Property ${propertyId} (${lang}) must not hardcode English "Verified Guest"`,
				);
			}

			for (const review of expectedReviews) {
				assert.ok(
					new RegExp(
						`<time[^>]*\\bdatetime="${review.datePublished}"`,
						"i",
					).test(main),
					`Property ${propertyId} (${lang}) must render semantic <time datetime="${review.datePublished}">`,
				);
				assert.ok(
					main.includes(t[review.authorKey]),
					`Property ${propertyId} (${lang}) must render review author ${t[review.authorKey]}`,
				);
			}

			assert.ok(
				main.includes(expectedAirbnbUrl) && main.includes(t.reviewViewOnAirbnb),
				`Property ${propertyId} (${lang}) must include verified external OTA channel attribution link`,
			);
		}
	}
});
