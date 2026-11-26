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
	AIRBNB_URLS,
	BUILDING_LOCATION,
	BUILDING_REGISTRATION_FEE_COP,
	DATA_CONTROLLER,
	EXTERNAL_ENTITY_URLS,
	getBusinessSameAsUrls,
	getPropertySameAsUrls,
	HOME_FAQ_ITEMS,
	IMAGES,
	PLAYA_SALGUERO_LANDMARKS,
	PRIVACY_POLICY_VERSION,
	PROPERTY_CLEANING_FEES_COP,
	PROPERTY_FAQ_ITEMS,
	PROPERTY_REVIEWS,
	type PropertyId,
} from "../../src/constants/config";
import {
	buildGuidePricingTokens,
	DESTINATION_GUIDES,
	formatCopCurrencyForLocale,
	interpolateGuideText,
} from "../../src/i18n/destinationGuides";
import { dict } from "../../src/i18n/dict";
import { buildLlmsFullTxt, buildLlmsTxt } from "../../src/utils/llms";
import {
	buildPricingSummary,
	type SeasonalRateTierRow,
} from "../../src/utils/schema";

const DIST_DIR = path.join(process.cwd(), "dist");
const PUBLIC_DIR = path.join(process.cwd(), "public");
const PUBLIC_PAGES = pages.filter((p) => !p.noIndex);

type JsonLdNode = Record<string, any>;

interface ParsedGraph {
	"@context": string;
	"@graph": JsonLdNode[];
}

const readCompiledHtml = (filename: string): string => {
	const fullPath = path.join(DIST_DIR, filename);
	assert.ok(
		fs.existsSync(fullPath),
		`Expected compiled HTML file: ${fullPath}`,
	);
	return fs.readFileSync(fullPath, "utf-8");
};

const parseJsonLdGraph = (html: string, label: string): ParsedGraph => {
	const matches = [
		...html.matchAll(
			/<script\s+type="application\/ld\+json">([\s\S]*?)<\/script>/gi,
		),
	];
	assert.equal(
		matches.length,
		1,
		`${label} must contain exactly one <script type="application/ld+json"> block`,
	);
	const raw = matches[0][1].trim();
	const parsed = JSON.parse(raw) as ParsedGraph;
	assert.equal(
		parsed["@context"],
		"https://schema.org",
		`${label} JSON-LD must declare @context https://schema.org`,
	);
	assert.ok(
		Array.isArray(parsed["@graph"]) && parsed["@graph"].length > 0,
		`${label} JSON-LD must emit a non-empty @graph array`,
	);
	return parsed;
};

const findNodeByType = (
	graph: JsonLdNode[],
	type: string,
	label: string,
): JsonLdNode => {
	const node = graph.find((item) => item["@type"] === type);
	assert.ok(node, `${label} @graph must include an @type="${type}" node`);
	return node;
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

const readPricesCsvRows = (): SeasonalRateTierRow[] => {
	const csvPath = path.join(PUBLIC_DIR, "data", "prices.csv");
	const csv = fs.readFileSync(csvPath, "utf-8");
	return csv
		.split("\n")
		.map((l) => l.trim())
		.filter((l) => l.length > 0)
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

test("#303 buildPricingSummary derives COP summary from prices.csv and provides safe COP fallback defaults", () => {
	const csvRows = readPricesCsvRows();
	const summary = buildPricingSummary(csvRows);

	assert.equal(summary.currency, "COP");
	assert.equal(summary.lowPrice, 350000);
	assert.equal(summary.highPrice, 550000);
	assert.equal(summary.priceRange, "350,000 - 550,000 COP");
	assert.equal(summary.properties["1707"].lowPrice, 450000);
	assert.equal(summary.properties["1707"].highPrice, 450000);
	assert.equal(summary.properties["1707"].minStay, 2);
	assert.equal(summary.properties["1606"].lowPrice, 350000);
	assert.equal(summary.properties["1606"].highPrice, 550000);
	assert.equal(summary.properties["1606"].minStay, 2);
	assert.equal(summary.properties["1606"].maxMinStay, 4);

	const fallback = buildPricingSummary([]);
	assert.equal(fallback.currency, "COP");
	assert.equal(fallback.properties["1707"].baselinePrice, 450000);
	assert.equal(fallback.properties["1606"].baselinePrice, 350000);
	assert.equal(fallback.properties["1707"].minStay, 2);
	assert.equal(fallback.properties["1606"].minStay, 2);
	assert.equal(fallback.priceRange, "350,000 - 450,000 COP");
});

test("#303 every public page across all 6 locales emits valid localized @graph JSON-LD with zero USD pricing", () => {
	const enWebSiteDesc = dict.en.schemaWebSiteDescription;
	const enLodgingDesc = dict.en.schemaLodgingDescription;
	const enContactName = dict.en.schemaContactPageName;

	for (const page of PUBLIC_PAGES) {
		for (const lang of LANGUAGES) {
			const label = `${page.id} (${lang})`;
			const html = readCompiledHtml(page.filename(lang));
			const parsed = parseJsonLdGraph(html, label);
			const serialized = JSON.stringify(parsed);

			assert.ok(
				!serialized.includes('"USD"') && !serialized.includes("USD"),
				`${label} JSON-LD must not contain stale USD pricing`,
			);

			const nodesWithInLanguage = parsed["@graph"].filter(
				(n) => n.inLanguage === lang,
			);
			assert.ok(
				nodesWithInLanguage.length > 0,
				`${label} JSON-LD must declare localized inLanguage="${lang}"`,
			);

			if (lang !== "en") {
				assert.ok(
					!serialized.includes(enWebSiteDesc),
					`${label} JSON-LD must not leak untranslated English WebSite description`,
				);
				assert.ok(
					!serialized.includes(enLodgingDesc),
					`${label} JSON-LD must not leak untranslated English LodgingBusiness description`,
				);
				assert.ok(
					!serialized.includes(enContactName),
					`${label} JSON-LD must not leak untranslated English ContactPage name`,
				);
			}
		}
	}
});

test("#303 homepage JSON-LD @graph connects WebSite, LodgingBusiness, Person (#host), both VacationRentals, RNT credentials, COP priceRange & FAQPage across all 6 locales", () => {
	const homePage = pages.find((p) => p.id === "home");
	assert.ok(homePage);

	const expectedSummary = buildPricingSummary(readPricesCsvRows());

	for (const lang of LANGUAGES) {
		const label = `home (${lang})`;
		const t = dict[lang];
		const homeUrl = getCanonicalUrl(BASE_URL, "", lang);
		const parsed = parseJsonLdGraph(
			readCompiledHtml(homePage.filename(lang)),
			label,
		);
		const graph = parsed["@graph"];

		const website = findNodeByType(graph, "WebSite", label);
		assert.equal(website["@id"], `${BASE_URL}/#website`);
		assert.equal(website.url, homeUrl);
		assert.equal(website.inLanguage, lang);
		assert.equal(website.description, t.schemaWebSiteDescription);
		assert.equal(website.publisher?.["@id"], `${BASE_URL}/#business`);

		const business = findNodeByType(graph, "LodgingBusiness", label);
		assert.equal(business["@id"], `${BASE_URL}/#business`);
		assert.equal(business.legalName, DATA_CONTROLLER.legalName);
		assert.equal(business.url, homeUrl);
		assert.equal(business.description, t.schemaLodgingDescription);
		assert.equal(business.image, `${BASE_URL}/${IMAGES.home.hero}`);
		assert.equal(business.logo?.["@type"], "ImageObject");
		assert.equal(
			business.logo?.url,
			`${BASE_URL}/web-app-manifest-512x512.png`,
		);
		assert.equal(business.priceRange, expectedSummary.priceRange);
		assert.equal(business.currenciesAccepted, "COP");
		assert.equal(business.paymentAccepted, t.schemaPaymentAccepted);
		assert.deepEqual(business.knowsLanguage, [...LANGUAGES]);
		assert.equal(
			business.hasMap,
			`https://maps.google.com/?q=${BUILDING_LOCATION.latitude},${BUILDING_LOCATION.longitude}`,
		);
		assert.deepEqual(business.sameAs, [
			AIRBNB_URLS["1707"],
			AIRBNB_URLS["1606"],
		]);
		assert.equal(business.founder?.["@id"], `${BASE_URL}/#host`);

		const credentialIds = (business.hasCredential ?? []).map(
			(c: JsonLdNode) => c.identifier,
		);
		assert.deepEqual(credentialIds, ["268781", "270413"]);

		const deptIds = (business.department ?? []).map(
			(d: JsonLdNode) => d["@id"],
		);
		assert.deepEqual(deptIds, [
			`${BASE_URL}/Oceanview1707/#rental`,
			`${BASE_URL}/Oceanview1606/#rental`,
		]);
		assert.equal(
			business.department[0].url,
			getCanonicalUrl(BASE_URL, "Oceanview1707", lang),
		);
		assert.equal(
			business.department[1].url,
			getCanonicalUrl(BASE_URL, "Oceanview1606", lang),
		);

		const host = findNodeByType(graph, "Person", label);
		assert.equal(host["@id"], `${BASE_URL}/#host`);
		assert.equal(host.name, "Manuel Enrique Herrera");
		assert.equal(host.jobTitle, t.hostDataControllerLabel);
		assert.equal(host.email, DATA_CONTROLLER.email);
		assert.equal(host.worksFor?.["@id"], `${BASE_URL}/#business`);

		const faq = findNodeByType(graph, "FAQPage", label);
		assert.equal(faq["@id"], `${homeUrl}#faq`);
		assert.equal(faq.inLanguage, lang);
		assert.equal(faq.mainEntity?.length, HOME_FAQ_ITEMS.length);
		HOME_FAQ_ITEMS.forEach((item, idx) => {
			assert.equal(faq.mainEntity[idx].name, t[item.qKey]);
			assert.equal(faq.mainEntity[idx].acceptedAnswer.text, t[item.aKey]);
		});
	}
});

test("#303 Property 1707 & 1606 JSON-LD @graph enforce isolated photos, COP CSV offers, floor levels, reconciled beds, parking bays, RNT, BreadcrumbList & FAQPage across all 6 locales", () => {
	const csvSummary = buildPricingSummary(readPricesCsvRows());
	const propertySpecs: {
		pageId: "Oceanview1707" | "Oceanview1606";
		propertyId: PropertyId;
		otherId: PropertyId;
		floorLevel: string;
		rntDigits: string;
		otherRntDigits: string;
		parkingBay: string;
		expectedTotalBeds: number;
		guestBedType: string;
		guestBedCount: number;
	}[] = [
		{
			pageId: "Oceanview1707",
			propertyId: "1707",
			otherId: "1606",
			floorLevel: "17",
			rntDigits: "268781",
			otherRntDigits: "270413",
			parkingBay: "95",
			expectedTotalBeds: 4,
			guestBedType: "Single",
			guestBedCount: 2,
		},
		{
			pageId: "Oceanview1606",
			propertyId: "1606",
			otherId: "1707",
			floorLevel: "16",
			rntDigits: "270413",
			otherRntDigits: "268781",
			parkingBay: "87",
			expectedTotalBeds: 3,
			guestBedType: "Double",
			guestBedCount: 1,
		},
	];

	for (const spec of propertySpecs) {
		const page = pages.find((p) => p.id === spec.pageId);
		assert.ok(page);
		const propPricing = csvSummary.properties[spec.propertyId];

		for (const lang of LANGUAGES) {
			const label = `${spec.pageId} (${lang})`;
			const t = dict[lang];
			const homeUrl = getCanonicalUrl(BASE_URL, "", lang);
			const pageUrl = getCanonicalUrl(BASE_URL, page.path, lang);
			const html = readCompiledHtml(page.filename(lang));
			const parsed = parseJsonLdGraph(html, label);
			const graph = parsed["@graph"];

			// BreadcrumbList with canonical trailing-slash URLs and exact UI parity
			const breadcrumb = findNodeByType(graph, "BreadcrumbList", label);
			assert.equal(breadcrumb["@id"], `${pageUrl}#breadcrumb`);
			assert.equal(breadcrumb.itemListElement.length, 2);
			assert.equal(breadcrumb.itemListElement[0].name, t.breadcrumbHome);
			assert.equal(breadcrumb.itemListElement[0].item, homeUrl);
			assert.equal(breadcrumb.itemListElement[1].item, pageUrl);
			assert.deepEqual(
				extractVisibleBreadcrumbLabels(html, t.breadcrumbLabel, label),
				breadcrumb.itemListElement.map((item: JsonLdNode) => item.name),
				`${label} visible <Breadcrumbs /> labels must match JSON-LD BreadcrumbList`,
			);
			if (lang === "en") {
				assert.ok(breadcrumb.itemListElement[0].item.endsWith("/"));
				assert.ok(breadcrumb.itemListElement[1].item.endsWith("/"));
			}

			// VacationRental
			const rental = findNodeByType(graph, "VacationRental", label);
			assert.equal(rental["@id"], `${BASE_URL}/${page.path}/#rental`);
			assert.equal(rental.url, pageUrl);
			assert.equal(rental.floorLevel, spec.floorLevel);
			assert.equal(rental.checkinTime, "15:00");
			assert.equal(rental.checkoutTime, "11:00");
			assert.equal(rental.sameAs, AIRBNB_URLS[spec.propertyId]);
			assert.equal(rental.provider?.["@id"], `${BASE_URL}/#business`);
			assert.equal(rental.containedInPlace?.name, BUILDING_LOCATION.name);
			assert.equal(rental.occupancy?.maxValue, 6);

			// RNT identifier without cross-property RNT leakage
			const identifiers: JsonLdNode[] = rental.identifier ?? [];
			const rntEntry = identifiers.find((id) => id.propertyID === "RNT");
			assert.equal(rntEntry?.value, spec.rntDigits);
			assert.ok(
				!JSON.stringify(rental).includes(spec.otherRntDigits),
				`${label} VacationRental must not reference other Property's RNT`,
			);

			// Strict Property-isolated image URLs (zero cross-property leakage + on-disk check)
			assert.ok(
				Array.isArray(rental.image) && rental.image.length >= 4,
				`${label} VacationRental must include Property images`,
			);
			for (const imgUrl of rental.image as string[]) {
				assert.ok(
					imgUrl.startsWith(`${BASE_URL}/images/`),
					`${label} image URL must be absolute: ${imgUrl}`,
				);
				assert.ok(
					imgUrl.includes(spec.propertyId),
					`${label} image URL must belong to Property ${spec.propertyId}: ${imgUrl}`,
				);
				assert.ok(
					!imgUrl.includes(spec.otherId),
					`${label} image URL must NOT leak Property ${spec.otherId}: ${imgUrl}`,
				);
				const relPath = imgUrl.replace(`${BASE_URL}/`, "");
				assert.ok(
					fs.existsSync(path.join(DIST_DIR, relPath)),
					`${label} image asset must exist in dist/: ${relPath}`,
				);
			}

			// Reconciled bedroom/bed containsPlace matching #301/#302
			const rooms: JsonLdNode[] = rental.containsPlace ?? [];
			assert.equal(
				rooms.length,
				3,
				`${label} must model Master, Guest & Living Room`,
			);
			const totalBeds = rooms.reduce((sum, room) => {
				const beds: JsonLdNode[] = room.bed ?? [];
				return sum + beds.reduce((s, b) => s + Number(b.numberOfBeds || 0), 0);
			}, 0);
			assert.equal(
				totalBeds,
				spec.expectedTotalBeds,
				`${label} total bed count must be ${spec.expectedTotalBeds}`,
			);
			assert.equal(rooms[0].bed[0].typeOfBed, "Queen");
			assert.equal(rooms[0].bed[0].numberOfBeds, 1);
			assert.equal(rooms[1].bed[0].typeOfBed, spec.guestBedType);
			assert.equal(rooms[1].bed[0].numberOfBeds, spec.guestBedCount);
			assert.equal(rooms[2].bed[0].typeOfBed, "SofaBed");
			assert.equal(rooms[2].bed[0].numberOfBeds, 1);

			// Comprehensive amenityFeature (900 Mbps Wi-Fi, Assigned Parking Bay, Tuesday pool note)
			const amenities: JsonLdNode[] = rental.amenityFeature ?? [];
			const amenityNames = amenities.map((a) => String(a.name));
			assert.ok(
				amenityNames.some((n) => n.includes("900")),
				`${label} amenityFeature must include 900 Mbps Fiber Wi-Fi`,
			);
			assert.ok(
				amenityNames.some((n) => n.includes(spec.parkingBay)),
				`${label} amenityFeature must include Assigned Parking Space Bay ${spec.parkingBay}`,
			);
			const poolAmenity = amenities.find((a) => a.name === t.amPool);
			assert.ok(
				poolAmenity,
				`${label} amenityFeature must include swimming pool`,
			);
			assert.equal(
				poolAmenity.description,
				t.homeComparePoolMaintenanceValue,
				`${label} pool amenity must include localized Tuesday maintenance note`,
			);

			// COP Offers synced with public/data/prices.csv
			assert.equal(rental.offers?.["@type"], "AggregateOffer");
			assert.equal(rental.offers?.priceCurrency, "COP");
			assert.equal(rental.offers?.lowPrice, String(propPricing.lowPrice));
			assert.equal(rental.offers?.highPrice, String(propPricing.highPrice));
			const tierOffers: JsonLdNode[] = rental.offers?.offers ?? [];
			assert.equal(tierOffers.length, propPricing.tiers.length);
			propPricing.tiers.forEach((tier, idx) => {
				assert.equal(tierOffers[idx].priceCurrency, "COP");
				assert.equal(tierOffers[idx].price, String(tier.nightly_rate_cop));
				assert.equal(tierOffers[idx].validFrom, tier.start_date);
				assert.equal(tierOffers[idx].validThrough, tier.end_date);
				assert.equal(
					tierOffers[idx].eligibleDuration?.["@type"],
					"QuantitativeValue",
				);
				assert.equal(
					tierOffers[idx].eligibleDuration?.value,
					tier.minimum_stay,
				);
				assert.equal(
					tierOffers[idx].eligibleDuration?.minValue,
					tier.minimum_stay,
				);
				assert.equal(tierOffers[idx].eligibleDuration?.unitCode, "DAY");
			});

			// Verified reviews synced with PROPERTY_REVIEWS
			const expectedReviews = PROPERTY_REVIEWS[spec.propertyId];
			assert.equal(rental.review?.length, expectedReviews.length);
			assert.equal(
				rental.aggregateRating?.reviewCount,
				String(expectedReviews.length),
			);
			expectedReviews.forEach((rev, idx) => {
				assert.equal(rental.review[idx].author?.name, t[rev.authorKey]);
				assert.equal(rental.review[idx].datePublished, rev.datePublished);
				assert.equal(rental.review[idx].reviewBody, t[rev.textKey]);
				assert.equal(rental.review[idx].inLanguage, lang);
			});

			// Property-specific FAQPage reusing PROPERTY_FAQ_ITEMS
			const faq = findNodeByType(graph, "FAQPage", label);
			const expectedFaqs = PROPERTY_FAQ_ITEMS[spec.propertyId];
			assert.equal(faq["@id"], `${pageUrl}#faq`);
			assert.equal(faq.inLanguage, lang);
			assert.equal(faq.mainEntity?.length, expectedFaqs.length);
			expectedFaqs.forEach((item, idx) => {
				assert.equal(faq.mainEntity[idx].name, t[item.qKey]);
				assert.equal(faq.mainEntity[idx].acceptedAnswer.text, t[item.aKey]);
			});
		}
	}
});

test("#303 Contact and Data Processing Policy JSON-LD @graph include BreadcrumbList, ContactPoints, policy version & Data Controller linkage across all 6 locales", () => {
	const contactPage = pages.find((p) => p.id === "contact");
	const privacyPage = pages.find((p) => p.id === "privacy");
	assert.ok(contactPage && privacyPage);

	for (const lang of LANGUAGES) {
		const t = dict[lang];
		const homeUrl = getCanonicalUrl(BASE_URL, "", lang);

		// Contact page
		const contactLabel = `contact (${lang})`;
		const contactUrl = getCanonicalUrl(BASE_URL, "contact", lang);
		const contactHtml = readCompiledHtml(contactPage.filename(lang));
		const contactGraph = parseJsonLdGraph(contactHtml, contactLabel)["@graph"];

		const contactBreadcrumb = findNodeByType(
			contactGraph,
			"BreadcrumbList",
			contactLabel,
		);
		assert.equal(contactBreadcrumb["@id"], `${contactUrl}#breadcrumb`);
		assert.equal(contactBreadcrumb.itemListElement[0].name, t.breadcrumbHome);
		assert.equal(contactBreadcrumb.itemListElement[0].item, homeUrl);
		assert.equal(contactBreadcrumb.itemListElement[1].name, t.navContact);
		assert.equal(contactBreadcrumb.itemListElement[1].item, contactUrl);
		assert.deepEqual(
			extractVisibleBreadcrumbLabels(
				contactHtml,
				t.breadcrumbLabel,
				contactLabel,
			),
			contactBreadcrumb.itemListElement.map((item: JsonLdNode) => item.name),
			`${contactLabel} visible <Breadcrumbs /> labels must match JSON-LD BreadcrumbList`,
		);

		const contactNode = findNodeByType(
			contactGraph,
			"ContactPage",
			contactLabel,
		);
		assert.equal(contactNode["@id"], `${contactUrl}#contactpage`);
		assert.equal(contactNode.name, t.schemaContactPageName);
		assert.equal(contactNode.description, t.seoContactDescription);
		assert.equal(contactNode.inLanguage, lang);
		assert.equal(contactNode.mainEntity?.["@id"], `${BASE_URL}/#business`);

		const contactBusiness = findNodeByType(
			contactGraph,
			"LodgingBusiness",
			contactLabel,
		);
		assert.equal(contactBusiness["@id"], `${BASE_URL}/#business`);
		assert.equal(contactBusiness.legalName, DATA_CONTROLLER.legalName);
		assert.ok(
			Array.isArray(contactBusiness.contactPoint) &&
				contactBusiness.contactPoint.length >= 2,
			`${contactLabel} must include structured ContactPoint entries`,
		);
		assert.ok(
			contactBusiness.contactPoint.some(
				(cp: JsonLdNode) => cp.url === DATA_CONTROLLER.whatsappHref,
			),
			`${contactLabel} must include WhatsApp ContactPoint`,
		);
		assert.ok(
			contactBusiness.contactPoint.some(
				(cp: JsonLdNode) => cp.email === DATA_CONTROLLER.email,
			),
			`${contactLabel} must include email ContactPoint`,
		);
		for (const cp of contactBusiness.contactPoint as JsonLdNode[]) {
			assert.deepEqual(cp.availableLanguage, [...LANGUAGES]);
		}

		// Data Processing Policy (Privacy) page
		const privacyLabel = `privacy (${lang})`;
		const privacyUrl = getCanonicalUrl(BASE_URL, "privacy", lang);
		const privacyHtml = readCompiledHtml(privacyPage.filename(lang));
		const privacyGraph = parseJsonLdGraph(privacyHtml, privacyLabel)["@graph"];

		const privacyBreadcrumb = findNodeByType(
			privacyGraph,
			"BreadcrumbList",
			privacyLabel,
		);
		assert.equal(privacyBreadcrumb["@id"], `${privacyUrl}#breadcrumb`);
		assert.equal(privacyBreadcrumb.itemListElement[0].name, t.breadcrumbHome);
		assert.equal(privacyBreadcrumb.itemListElement[0].item, homeUrl);
		assert.equal(
			privacyBreadcrumb.itemListElement[1].name,
			t.breadcrumbPrivacy,
		);
		assert.equal(privacyBreadcrumb.itemListElement[1].item, privacyUrl);
		assert.deepEqual(
			extractVisibleBreadcrumbLabels(
				privacyHtml,
				t.breadcrumbLabel,
				privacyLabel,
			),
			privacyBreadcrumb.itemListElement.map((item: JsonLdNode) => item.name),
			`${privacyLabel} visible <Breadcrumbs /> labels must match JSON-LD BreadcrumbList`,
		);

		const privacyNode = findNodeByType(privacyGraph, "WebPage", privacyLabel);
		assert.equal(privacyNode["@id"], `${privacyUrl}#webpage`);
		assert.equal(privacyNode.name, t.privacySeoTitle);
		assert.equal(privacyNode.description, t.privacySeoDescription);
		assert.equal(privacyNode.inLanguage, lang);
		assert.equal(privacyNode.version, PRIVACY_POLICY_VERSION);
		assert.equal(privacyNode.publisher?.["@id"], `${BASE_URL}/#business`);
		assert.equal(privacyNode.author?.["@id"], `${BASE_URL}/#host`);

		const privacyBusiness = findNodeByType(
			privacyGraph,
			"LodgingBusiness",
			privacyLabel,
		);
		assert.equal(privacyBusiness["@id"], `${BASE_URL}/#business`);
		assert.equal(privacyBusiness.legalName, DATA_CONTROLLER.legalName);

		const privacyHost = findNodeByType(privacyGraph, "Person", privacyLabel);
		assert.equal(privacyHost["@id"], `${BASE_URL}/#host`);
		assert.equal(privacyHost.name, "Manuel Enrique Herrera");
		assert.equal(privacyHost.email, DATA_CONTROLLER.email);
	}
});

test("#313 build-time dist/llms.txt and dist/llms-full.txt derive COP rates from prices.csv, include /santa-marta/ Destination Guides, omit disallowed routes, and obey CONTEXT.md", () => {
	assert.equal(
		fs.existsSync(path.join(PUBLIC_DIR, "llms.txt")),
		false,
		"Static public/llms.txt must be removed so dist/llms.txt has a single build-time source of truth",
	);

	const csvRows = readPricesCsvRows();
	const csvSummary = buildPricingSummary(csvRows);
	const distLlmsPath = path.join(DIST_DIR, "llms.txt");
	const distLlmsFullPath = path.join(DIST_DIR, "llms-full.txt");

	assert.ok(fs.existsSync(distLlmsPath), "Expected generated dist/llms.txt");
	assert.ok(
		fs.existsSync(distLlmsFullPath),
		"Expected generated dist/llms-full.txt",
	);

	const distLlms = fs.readFileSync(distLlmsPath, "utf-8");
	const distLlmsFull = fs.readFileSync(distLlmsFullPath, "utf-8");

	assert.equal(
		distLlms,
		buildLlmsTxt(BASE_URL, csvSummary),
		"dist/llms.txt must match deterministic buildLlmsTxt(BASE_URL, csvSummary)",
	);
	assert.equal(
		distLlmsFull,
		buildLlmsFullTxt(BASE_URL, csvSummary),
		"dist/llms-full.txt must match deterministic buildLlmsFullTxt(BASE_URL, csvSummary)",
	);
	assert.ok(
		distLlms.includes(`${BASE_URL}/llms-full.txt`),
		"dist/llms.txt must link to canonical llms-full.txt",
	);
	assert.ok(
		distLlmsFull.length > distLlms.length,
		"dist/llms-full.txt must extend dist/llms.txt with comprehensive reference content",
	);

	const requiredCanonicalUrls = [
		`${BASE_URL}/`,
		`${BASE_URL}/Oceanview1707/`,
		`${BASE_URL}/Oceanview1606/`,
		`${BASE_URL}/santa-marta/`,
		...DESTINATION_GUIDES.map((g) => `${BASE_URL}/santa-marta/${g.slug}/`),
		`${BASE_URL}/contact/`,
		`${BASE_URL}/privacy/`,
	];

	const disallowedRoutes = [
		`${BASE_URL}/registry/`,
		`${BASE_URL}/guide/`,
		`${BASE_URL}/booking-success/`,
		`${BASE_URL}/booking-failure/`,
		`${BASE_URL}/booking-pending/`,
		`${BASE_URL}/404.html`,
		`${BASE_URL}/404/`,
		"/registry/",
		"/guide/",
		"/booking-success/",
		"/booking-failure/",
		"/booking-pending/",
		"/404.html",
		"/404/",
		"/api/",
	];

	const forbiddenContextSynonyms = [
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

	const reviewAuthorsAndBodies = [
		...PROPERTY_REVIEWS["1707"],
		...PROPERTY_REVIEWS["1606"],
	].flatMap((rev) => [dict.en[rev.authorKey], dict.en[rev.textKey]]);

	const expectedDynamicFacts = [
		formatCopCurrencyForLocale(csvSummary.properties["1707"].lowPrice, "en"),
		formatCopCurrencyForLocale(csvSummary.properties["1606"].lowPrice, "en"),
		formatCopCurrencyForLocale(csvSummary.properties["1606"].highPrice, "en"),
		`${csvSummary.properties["1707"].minStay}-night`,
		`${csvSummary.properties["1606"].minStay}-night`,
		`${csvSummary.properties["1606"].maxMinStay}-night`,
		formatCopCurrencyForLocale(PROPERTY_CLEANING_FEES_COP["1707"], "en"),
		formatCopCurrencyForLocale(PROPERTY_CLEANING_FEES_COP["1606"], "en"),
		formatCopCurrencyForLocale(BUILDING_REGISTRATION_FEE_COP, "en"),
		"15:00",
		"11:00",
		"Tuesday",
		DATA_CONTROLLER.rnt1707,
		DATA_CONTROLLER.rnt1606,
		"Bay 95",
		"Bay 87",
		PRIVACY_POLICY_VERSION,
	];

	for (const [label, content] of [
		["dist/llms.txt", distLlms],
		["dist/llms-full.txt", distLlmsFull],
	] as const) {
		for (const url of requiredCanonicalUrls) {
			assert.ok(
				content.includes(url),
				`${label} must contain canonical trailing-slash URL: ${url}`,
			);
		}

		for (const lang of LANGUAGES) {
			const expectedHubPath =
				lang === "en" ? "`/santa-marta/`" : `\`/santa-marta/${lang}.html\``;
			assert.ok(
				content.includes(expectedHubPath),
				`${label} must include localized hub path ${expectedHubPath}`,
			);
			for (const guide of DESTINATION_GUIDES) {
				const expectedGuidePath =
					lang === "en"
						? `\`/santa-marta/${guide.slug}/\``
						: `\`/santa-marta/${guide.slug}/${lang}.html\``;
				assert.ok(
					content.includes(expectedGuidePath),
					`${label} must include localized Destination Guide path ${expectedGuidePath}`,
				);
			}
		}

		for (const disallowedUrl of disallowedRoutes) {
			assert.ok(
				!content.includes(disallowedUrl),
				`${label} must NOT advertise disallowed URL or path: ${disallowedUrl}`,
			);
		}

		assert.ok(
			content.includes("Guest Registry") &&
				content.includes("Guest Guide") &&
				content.includes("Destination Guide"),
			`${label} must document Guest Registry, Guest Guide, and Destination Guide textually`,
		);
		assert.ok(
			content.includes("External OTA Channel (Optional)") &&
				!content.includes("External OTA Listing"),
			`${label} must use canonical domain term 'External OTA Channel (Optional)'`,
		);

		// Zero non-trailing-slash English directory URLs or paths
		const nonCanonicalAbsMatches = content.match(
			/https:\/\/www\.oceanviewflats\.com\/(?:Oceanview1707|Oceanview1606|contact|privacy|registry|guide|santa-marta(?:\/[a-z0-9-]+)?)(?![/\w.-])/g,
		);
		assert.equal(
			nonCanonicalAbsMatches,
			null,
			`${label} must not contain non-trailing-slash absolute English URLs: ${nonCanonicalAbsMatches?.join(", ")}`,
		);
		const nonCanonicalRelMatches = content.match(
			/`\/(?:Oceanview1707|Oceanview1606|contact|privacy|registry|guide|santa-marta(?:\/[a-z0-9-]+)?)`/g,
		);
		assert.equal(
			nonCanonicalRelMatches,
			null,
			`${label} must not contain non-trailing-slash relative English paths: ${nonCanonicalRelMatches?.join(", ")}`,
		);

		// Reconciled bed counts (and zero stale "2 Double Beds")
		assert.ok(
			!content.includes("2 Double Beds"),
			`${label} must not contain stale '2 Double Beds' layout`,
		);
		assert.ok(
			content.includes("2 Bedrooms, 4 Beds") &&
				content.includes("2 Twin/Single Beds") &&
				content.includes("1 Pull-out Sofa Bed"),
			`${label} must reflect Property 1707 reconciled 4-bed layout`,
		);
		assert.ok(
			content.includes("2 Bedrooms, 3 Beds") &&
				content.includes("1 Double Bed"),
			`${label} must reflect Property 1606 reconciled 3-bed layout`,
		);

		for (const fact of expectedDynamicFacts) {
			assert.ok(
				content.includes(fact),
				`${label} must include authoritative fact: ${fact}`,
			);
		}

		for (const tier of csvRows) {
			assert.ok(
				content.includes(tier.start_date) && content.includes(tier.end_date),
				`${label} must reflect CSV Seasonal Rate Tier window ${tier.start_date}..${tier.end_date}`,
			);
		}

		for (const landmark of PLAYA_SALGUERO_LANDMARKS) {
			assert.ok(
				content.includes(landmark.distanceApprox),
				`${label} must include landmark distance ${landmark.distanceApprox} for ${landmark.id}`,
			);
			assert.ok(
				content.includes(landmark.travelTimeApprox),
				`${label} must include landmark travel time ${landmark.travelTimeApprox} for ${landmark.id}`,
			);
		}

		assert.doesNotMatch(
			content,
			/\bUSD\b/,
			`${label} must never emit USD pricing`,
		);

		for (const pattern of forbiddenContextSynonyms) {
			assert.doesNotMatch(
				content,
				pattern,
				`${label} must not contain CONTEXT.md forbidden synonym (${pattern})`,
			);
		}

		for (const reviewSnippet of reviewAuthorsAndBodies) {
			assert.ok(
				!content.includes(reviewSnippet),
				`${label} must NOT duplicate Guest Review authors/bodies: ${reviewSnippet}`,
			);
		}
	}

	// Comprehensive dist/llms-full.txt checks: comparison matrix, 4 Destination Guides, FAQs, cancellation, Law 1581/2068/679
	assert.ok(
		distLlmsFull.includes(
			"| Specification | OceanViewFlats 1707 | OceanViewFlats 1606 |",
		),
		"dist/llms-full.txt must include reconciled Property comparison matrix",
	);

	const enTokens = buildGuidePricingTokens(csvSummary, "en");
	for (const guide of DESTINATION_GUIDES) {
		const enLocale = guide.locales.en;
		assert.ok(
			distLlmsFull.includes(interpolateGuideText(enLocale.title, enTokens)),
			`dist/llms-full.txt must include Destination Guide title for ${guide.slug}`,
		);
		for (const takeaway of enLocale.keyTakeaways) {
			assert.ok(
				distLlmsFull.includes(interpolateGuideText(takeaway, enTokens)),
				`dist/llms-full.txt must include interpolated key takeaway from ${guide.slug}`,
			);
		}
	}
	assert.doesNotMatch(
		distLlmsFull,
		/\{(?:rate1707|rate1707Cop|rate1707HighCop|minStay1707|cleaning1707Cop|rate1606|rate1606Cop|rate1606HighCop|minStay1606|maxMinStay1606|cleaning1606Cop|portfolioLowCop|portfolioHighCop|buildingRegistrationFeeCop)\}/,
		"dist/llms-full.txt must not contain unresolved pricing interpolation tokens",
	);

	for (const fullBriefFact of [
		"## Complete Property FAQs",
		"### OceanViewFlats 1707 — Frequently Asked Questions",
		"### OceanViewFlats 1606 — Frequently Asked Questions",
		"100% refund",
		"50% Policy Retention",
		"Law 1581 of 2012",
		"Law 2068 of 2020",
		"Law 679 of 2001",
		"Personal Data Authorization",
		"Minor Guardian Attestation",
		"XSalsa20-Poly1305",
		`${DATA_CONTROLLER.consultaSlaBusinessDays} business days`,
		`${DATA_CONTROLLER.reclamoSlaBusinessDays} business days`,
		`${DATA_CONTROLLER.retentionDaysAfterCheckout} days`,
	]) {
		assert.ok(
			distLlmsFull.includes(fullBriefFact),
			`dist/llms-full.txt must include full brief section/fact: ${fullBriefFact}`,
		);
	}
});

test("#313 buildLlmsTxt and buildLlmsFullTxt react dynamically to custom Seasonal Rate Tiers without manual price duplication", () => {
	const customSummary = buildPricingSummary([
		{
			property_id: "1707",
			start_date: "2027-02-01",
			end_date: "2027-11-30",
			nightly_rate_cop: 495000,
			minimum_stay: 3,
		},
		{
			property_id: "1606",
			start_date: "2027-02-01",
			end_date: "2027-11-30",
			nightly_rate_cop: 385000,
			minimum_stay: 2,
		},
		{
			property_id: "1606",
			start_date: "2027-12-01",
			end_date: "2028-01-15",
			nightly_rate_cop: 620000,
			minimum_stay: 5,
		},
	]);

	for (const [label, output] of [
		["buildLlmsTxt", buildLlmsTxt(BASE_URL, customSummary)],
		["buildLlmsFullTxt", buildLlmsFullTxt(BASE_URL, customSummary)],
	] as const) {
		for (const expected of [
			"495,000 COP",
			"385,000 COP",
			"620,000 COP",
			"3-night",
			"5-night",
			"2027-02-01",
			"2028-01-15",
		]) {
			assert.ok(
				output.includes(expected),
				`${label} must dynamically reflect updated tier value: ${expected}`,
			);
		}
		for (const stale of ["450,000 COP", "350,000 COP", "550,000 COP"]) {
			assert.ok(
				!output.includes(stale),
				`${label} must not retain hardcoded stale COP rate: ${stale}`,
			);
		}
	}
});

test("#313 EXTERNAL_ENTITY_URLS, getBusinessSameAsUrls, and getPropertySameAsUrls filter blank/non-HTTPS values and emit valid https:// URLs in JSON-LD sameAs", () => {
	assert.deepEqual(Object.keys(EXTERNAL_ENTITY_URLS).sort(), [
		"appleBusinessConnect",
		"googleBusinessProfile",
		"instagram",
		"tripadvisor",
	]);

	const defaultBusinessSameAs = getBusinessSameAsUrls();
	assert.deepEqual(defaultBusinessSameAs, [
		AIRBNB_URLS["1707"],
		AIRBNB_URLS["1606"],
	]);
	for (const url of defaultBusinessSameAs) {
		assert.ok(
			url.startsWith("https://"),
			`Business sameAs URL must use https://: ${url}`,
		);
	}

	const customBusinessSameAs = getBusinessSameAsUrls({
		googleBusinessProfile: "  https://maps.google.com/?cid=987654321  ",
		appleBusinessConnect: "   ",
		instagram: "http://instagram.com/insecure-ignored",
		tripadvisor: "https://www.tripadvisor.com/Hotel_Review-oceanviewflats",
	});
	assert.deepEqual(customBusinessSameAs, [
		AIRBNB_URLS["1707"],
		AIRBNB_URLS["1606"],
		"https://maps.google.com/?cid=987654321",
		"https://www.tripadvisor.com/Hotel_Review-oceanviewflats",
	]);

	// Deduplicates identical entries and rejects invalid/non-https protocols
	const dedupedBusinessSameAs = getBusinessSameAsUrls({
		googleBusinessProfile: AIRBNB_URLS["1707"],
		appleBusinessConnect: "javascript:alert(1)",
		instagram: "not-a-url",
		tripadvisor: "https://instagram.com/oceanviewflats",
	});
	assert.deepEqual(dedupedBusinessSameAs, [
		AIRBNB_URLS["1707"],
		AIRBNB_URLS["1606"],
		"https://instagram.com/oceanviewflats",
	]);

	assert.deepEqual(getPropertySameAsUrls("1707"), [AIRBNB_URLS["1707"]]);
	assert.deepEqual(getPropertySameAsUrls("1606"), [AIRBNB_URLS["1606"]]);
	assert.deepEqual(
		getPropertySameAsUrls("1707", [
			"",
			"   ",
			"http://insecure.example.com/1707",
			AIRBNB_URLS["1707"],
			"https://www.tripadvisor.com/VacationRentalReview-1707",
		]),
		[
			AIRBNB_URLS["1707"],
			"https://www.tripadvisor.com/VacationRentalReview-1707",
		],
	);
});
