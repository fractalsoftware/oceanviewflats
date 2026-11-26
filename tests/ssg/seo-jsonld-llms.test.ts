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
	DATA_CONTROLLER,
	HOME_FAQ_ITEMS,
	IMAGES,
	PLAYA_SALGUERO_LANDMARKS,
	PRIVACY_POLICY_VERSION,
	PROPERTY_FAQ_ITEMS,
	PROPERTY_REVIEWS,
	type PropertyId,
} from "../../src/constants/config";
import { dict } from "../../src/i18n/dict";
import type { Lang } from "../../src/types";
import { buildPricingSummary, type PriceTierRow } from "../../src/utils/schema";

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

const readPricesCsvRows = (): PriceTierRow[] => {
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
			const parsed = parseJsonLdGraph(
				readCompiledHtml(page.filename(lang)),
				label,
			);
			const graph = parsed["@graph"];

			// BreadcrumbList with canonical trailing-slash URLs
			const breadcrumb = findNodeByType(graph, "BreadcrumbList", label);
			assert.equal(breadcrumb["@id"], `${pageUrl}#breadcrumb`);
			assert.equal(breadcrumb.itemListElement.length, 2);
			assert.equal(breadcrumb.itemListElement[0].name, t.schemaBreadcrumbHome);
			assert.equal(breadcrumb.itemListElement[0].item, homeUrl);
			assert.equal(breadcrumb.itemListElement[1].item, pageUrl);
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
					tierOffers[idx].eligibleDuration?.value,
					`P${tier.minimum_stay}D`,
				);
				assert.equal(
					tierOffers[idx].eligibleDuration?.minValue,
					tier.minimum_stay,
				);
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
		const contactGraph = parseJsonLdGraph(
			readCompiledHtml(contactPage.filename(lang)),
			contactLabel,
		)["@graph"];

		const contactBreadcrumb = findNodeByType(
			contactGraph,
			"BreadcrumbList",
			contactLabel,
		);
		assert.equal(contactBreadcrumb["@id"], `${contactUrl}#breadcrumb`);
		assert.equal(contactBreadcrumb.itemListElement[0].item, homeUrl);
		assert.equal(contactBreadcrumb.itemListElement[1].item, contactUrl);

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
		const privacyGraph = parseJsonLdGraph(
			readCompiledHtml(privacyPage.filename(lang)),
			privacyLabel,
		)["@graph"];

		const privacyBreadcrumb = findNodeByType(
			privacyGraph,
			"BreadcrumbList",
			privacyLabel,
		);
		assert.equal(privacyBreadcrumb["@id"], `${privacyUrl}#breadcrumb`);
		assert.equal(privacyBreadcrumb.itemListElement[0].item, homeUrl);
		assert.equal(privacyBreadcrumb.itemListElement[1].item, privacyUrl);

		const privacyNode = findNodeByType(privacyGraph, "WebPage", privacyLabel);
		assert.equal(privacyNode["@id"], `${privacyUrl}#webpage`);
		assert.equal(privacyNode.name, t.privacySeoTitle);
		assert.equal(privacyNode.description, t.privacySeoDescription);
		assert.equal(privacyNode.inLanguage, lang);
		assert.equal(privacyNode.version, PRIVACY_POLICY_VERSION);
		assert.equal(privacyNode.publisher?.["@id"], `${BASE_URL}/#business`);
		assert.equal(privacyNode.author?.["@id"], `${BASE_URL}/#host`);

		const privacyHost = findNodeByType(privacyGraph, "Person", privacyLabel);
		assert.equal(privacyHost["@id"], `${BASE_URL}/#host`);
		assert.equal(privacyHost.name, "Manuel Enrique Herrera");
		assert.equal(privacyHost.email, DATA_CONTROLLER.email);
	}
});

test("#303 public/llms.txt and dist/llms.txt synchronize canonical trailing-slash URLs, reconciled beds, COP rates, policies & Santa Marta landmarks", () => {
	const publicLlms = fs.readFileSync(
		path.join(PUBLIC_DIR, "llms.txt"),
		"utf-8",
	);
	const distLlms = fs.readFileSync(path.join(DIST_DIR, "llms.txt"), "utf-8");
	assert.equal(
		distLlms,
		publicLlms,
		"dist/llms.txt must match public/llms.txt",
	);

	// Canonical trailing-slash English URLs
	const requiredCanonicalUrls = [
		"https://www.oceanviewflats.com/",
		"https://www.oceanviewflats.com/Oceanview1707/",
		"https://www.oceanviewflats.com/Oceanview1606/",
		"https://www.oceanviewflats.com/contact/",
		"https://www.oceanviewflats.com/privacy/",
		"https://www.oceanviewflats.com/registry/",
		"https://www.oceanviewflats.com/guide/",
	];
	for (const url of requiredCanonicalUrls) {
		assert.ok(
			publicLlms.includes(url),
			`llms.txt must contain canonical trailing-slash URL: ${url}`,
		);
	}

	// Zero non-trailing-slash English directory URLs
	const nonCanonicalAbsMatches = publicLlms.match(
		/https:\/\/www\.oceanviewflats\.com\/(?:Oceanview1707|Oceanview1606|contact|privacy|registry|guide)(?![/\w])/g,
	);
	assert.equal(
		nonCanonicalAbsMatches,
		null,
		`llms.txt must not contain non-trailing-slash absolute English URLs: ${nonCanonicalAbsMatches?.join(", ")}`,
	);
	const nonCanonicalRelMatches = publicLlms.match(
		/`\/(?:Oceanview1707|Oceanview1606|contact|privacy|registry|guide)`/g,
	);
	assert.equal(
		nonCanonicalRelMatches,
		null,
		`llms.txt must not contain non-trailing-slash relative English paths: ${nonCanonicalRelMatches?.join(", ")}`,
	);

	// Reconciled bed counts (and zero stale "2 Double Beds")
	assert.ok(
		!publicLlms.includes("2 Double Beds"),
		"llms.txt must not contain stale '2 Double Beds' layout",
	);
	assert.ok(
		publicLlms.includes("2 Bedrooms, 4 Beds") &&
			publicLlms.includes("2 Twin/Single Beds") &&
			publicLlms.includes("1 Pull-out Sofa Bed"),
		"llms.txt must reflect Property 1707 reconciled 4-bed layout",
	);
	assert.ok(
		publicLlms.includes("2 Bedrooms, 3 Beds") &&
			publicLlms.includes("1 Double Bed"),
		"llms.txt must reflect Property 1606 reconciled 3-bed layout",
	);

	// Authoritative COP rates, Cleaning Fees, Check-in/Check-out, Building Registration Fee & Tuesday pool maintenance
	for (const fact of [
		"450,000 COP",
		"350,000 COP",
		"550,000 COP",
		"2-night",
		"4-night",
		"100,000 COP",
		"80,000 COP",
		"15:00",
		"11:00",
		"20,000 COP",
		"Tuesday",
		"RNT #268781",
		"RNT #270413",
		"Bay 95",
		"Bay 87",
		PRIVACY_POLICY_VERSION,
	]) {
		assert.ok(
			publicLlms.includes(fact),
			`llms.txt must include authoritative fact: ${fact}`,
		);
	}

	// Santa Marta landmark distances matching PLAYA_SALGUERO_LANDMARKS
	for (const landmark of PLAYA_SALGUERO_LANDMARKS) {
		assert.ok(
			publicLlms.includes(landmark.distanceApprox),
			`llms.txt must include landmark distance ${landmark.distanceApprox} for ${landmark.id}`,
		);
		assert.ok(
			publicLlms.includes(landmark.travelTimeApprox),
			`llms.txt must include landmark travel time ${landmark.travelTimeApprox} for ${landmark.id}`,
		);
	}
});
