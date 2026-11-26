import {
	AIRBNB_URLS,
	BUILDING_LOCATION,
	DATA_CONTROLLER,
	getCanonicalUrl,
	HOME_FAQ_ITEMS,
	IMAGES,
	LANGUAGES,
	PRIVACY_POLICY_VERSION,
	PROPERTY_FAQ_ITEMS,
	PROPERTY_GALLERY_IMAGES,
	PROPERTY_REVIEWS,
	type PropertyId,
} from "../constants/config";
import type { TranslationDict } from "../i18n/dict";
import type { Lang } from "../types";
import { calculateAggregateRatingValue } from "./reviews";

export interface SeasonalRateTierRow {
	property_id: string;
	start_date: string;
	end_date: string;
	nightly_rate_cop: number;
	minimum_stay: number;
}

export interface PropertyPricingSummary {
	propertyId: PropertyId;
	lowPrice: number;
	highPrice: number;
	baselinePrice: number;
	minStay: number;
	maxMinStay: number;
	currency: "COP";
	tiers: SeasonalRateTierRow[];
}

export interface PortfolioPricingSummary {
	currency: "COP";
	lowPrice: number;
	highPrice: number;
	priceRange: string;
	properties: Record<PropertyId, PropertyPricingSummary>;
}

const DEFAULT_PROPERTY_RATES_COP: Record<PropertyId, number> = {
	"1707": 450000,
	"1606": 350000,
};

const DEFAULT_MIN_STAY = 2;

const MAP_URL = `https://maps.google.com/?q=${BUILDING_LOCATION.latitude},${BUILDING_LOCATION.longitude}`;
const PHONE_E164 = DATA_CONTROLLER.phoneHref.replace(/^tel:/, "");
const CHECKIN_TIME = "15:00";
const CHECKOUT_TIME = "11:00";

interface PropertySchemaSpec {
	pagePath: "Oceanview1707" | "Oceanview1606";
	name: string;
	rntDigits: string;
	rntLabel: string;
	floorLevel: string;
	titleKey: "seoTitle" | "seo1606Title";
	descriptionKey: "seoDescription" | "seo1606Description";
	displayNameKey: "heroTitle" | "hero1606Title";
	parkingFeatureKey: "schemaAmenityParking1707" | "schemaAmenityParking1606";
	signatureAmenityKey: "schemaAmenityWorkspace" | "amRomantic";
	guestBedroomBeds: readonly {
		"@type": "BedDetails";
		typeOfBed: string;
		numberOfBeds: number;
	}[];
}

const PROPERTY_SCHEMA_SPECS: Record<PropertyId, PropertySchemaSpec> = {
	"1707": {
		pagePath: "Oceanview1707",
		name: "OceanViewFlats 1707",
		rntDigits: "268781",
		rntLabel: DATA_CONTROLLER.rnt1707,
		floorLevel: "17",
		titleKey: "seoTitle",
		descriptionKey: "seoDescription",
		displayNameKey: "heroTitle",
		parkingFeatureKey: "schemaAmenityParking1707",
		signatureAmenityKey: "schemaAmenityWorkspace",
		guestBedroomBeds: [
			{ "@type": "BedDetails", typeOfBed: "Single", numberOfBeds: 2 },
		],
	},
	"1606": {
		pagePath: "Oceanview1606",
		name: "OceanViewFlats 1606",
		rntDigits: "270413",
		rntLabel: DATA_CONTROLLER.rnt1606,
		floorLevel: "16",
		titleKey: "seo1606Title",
		descriptionKey: "seo1606Description",
		displayNameKey: "hero1606Title",
		parkingFeatureKey: "schemaAmenityParking1606",
		signatureAmenityKey: "amRomantic",
		guestBedroomBeds: [
			{ "@type": "BedDetails", typeOfBed: "Double", numberOfBeds: 1 },
		],
	},
};

const getPropertySchemaImages = (propertyId: PropertyId): string[] => [
	...Object.values(IMAGES[propertyId]),
	...PROPERTY_GALLERY_IMAGES[propertyId].map((img) => img.src),
];

const formatCopAmount = (amount: number): string =>
	Math.round(amount).toLocaleString("en-US");

const normalizeBaseUrl = (baseUrl: string): string =>
	baseUrl.replace(/\/+$/, "");

const summarizePropertyPricing = (
	propertyId: PropertyId,
	rows: readonly SeasonalRateTierRow[],
): PropertyPricingSummary => {
	const matching = rows.filter(
		(row) =>
			row.property_id === propertyId &&
			Number.isFinite(row.nightly_rate_cop) &&
			row.nightly_rate_cop > 0,
	);

	if (matching.length === 0) {
		const fallbackRate = DEFAULT_PROPERTY_RATES_COP[propertyId];
		return {
			propertyId,
			lowPrice: fallbackRate,
			highPrice: fallbackRate,
			baselinePrice: fallbackRate,
			minStay: DEFAULT_MIN_STAY,
			maxMinStay: DEFAULT_MIN_STAY,
			currency: "COP",
			tiers: [
				{
					property_id: propertyId,
					start_date: "2026-01-01",
					end_date: "2026-12-31",
					nightly_rate_cop: fallbackRate,
					minimum_stay: DEFAULT_MIN_STAY,
				},
			],
		};
	}

	const rates = matching.map((row) => Math.round(row.nightly_rate_cop));
	const minStays = matching
		.map((row) => row.minimum_stay)
		.filter((days) => Number.isFinite(days) && days > 0);

	const lowPrice = Math.min(...rates);
	const highPrice = Math.max(...rates);
	const minStay =
		minStays.length > 0 ? Math.min(...minStays) : DEFAULT_MIN_STAY;
	const maxMinStay =
		minStays.length > 0 ? Math.max(...minStays) : DEFAULT_MIN_STAY;

	return {
		propertyId,
		lowPrice,
		highPrice,
		baselinePrice: lowPrice,
		minStay,
		maxMinStay,
		currency: "COP",
		tiers: matching.map((row) => ({
			...row,
			nightly_rate_cop: Math.round(row.nightly_rate_cop),
			minimum_stay:
				Number.isFinite(row.minimum_stay) && row.minimum_stay > 0
					? Math.round(row.minimum_stay)
					: DEFAULT_MIN_STAY,
		})),
	};
};

export const buildPricingSummary = (
	rows: readonly SeasonalRateTierRow[],
): PortfolioPricingSummary => {
	const prop1707 = summarizePropertyPricing("1707", rows);
	const prop1606 = summarizePropertyPricing("1606", rows);
	const lowPrice = Math.min(prop1707.lowPrice, prop1606.lowPrice);
	const highPrice = Math.max(prop1707.highPrice, prop1606.highPrice);

	return {
		currency: "COP",
		lowPrice,
		highPrice,
		priceRange: `${formatCopAmount(lowPrice)} - ${formatCopAmount(highPrice)} COP`,
		properties: {
			"1707": prop1707,
			"1606": prop1606,
		},
	};
};

export const DEFAULT_PRICING_SUMMARY: PortfolioPricingSummary =
	buildPricingSummary([]);

const buildPostalAddress = () => ({
	"@type": "PostalAddress",
	streetAddress: `${BUILDING_LOCATION.streetAddress}, ${BUILDING_LOCATION.neighborhood}`,
	addressLocality: BUILDING_LOCATION.city,
	addressRegion: BUILDING_LOCATION.region,
	postalCode: BUILDING_LOCATION.postalCode,
	addressCountry: "CO",
});

const buildGeoCoordinates = () => ({
	"@type": "GeoCoordinates",
	latitude: BUILDING_LOCATION.latitude,
	longitude: BUILDING_LOCATION.longitude,
});

const buildLogoObject = (normalizedBase: string) => ({
	"@type": "ImageObject",
	url: `${normalizedBase}/web-app-manifest-512x512.png`,
	width: 512,
	height: 512,
});

const buildContactPoints = (contactPageUrl: string) => [
	{
		"@type": "ContactPoint",
		contactType: "reservations",
		telephone: PHONE_E164,
		url: DATA_CONTROLLER.whatsappHref,
		availableLanguage: [...LANGUAGES],
		areaServed: "CO",
	},
	{
		"@type": "ContactPoint",
		contactType: "customer support",
		email: DATA_CONTROLLER.email,
		url: contactPageUrl,
		availableLanguage: [...LANGUAGES],
		areaServed: "CO",
	},
];

const buildRntCredentials = () => [
	{
		"@type": "EducationalOccupationalCredential",
		credentialCategory: "Registro Nacional de Turismo (RNT)",
		name: `${DATA_CONTROLLER.rnt1707} — OceanViewFlats 1707`,
		identifier: "268781",
		recognizedBy: {
			"@type": "GovernmentOrganization",
			name: "Ministerio de Comercio, Industria y Turismo de Colombia (RNT)",
		},
	},
	{
		"@type": "EducationalOccupationalCredential",
		credentialCategory: "Registro Nacional de Turismo (RNT)",
		name: `${DATA_CONTROLLER.rnt1606} — OceanViewFlats 1606`,
		identifier: "270413",
		recognizedBy: {
			"@type": "GovernmentOrganization",
			name: "Ministerio de Comercio, Industria y Turismo de Colombia (RNT)",
		},
	},
];

const buildHostPersonNode = (
	t: TranslationDict,
	lang: Lang,
	normalizedBase: string,
) => ({
	"@type": "Person",
	"@id": `${normalizedBase}/#host`,
	name: "Manuel Enrique Herrera",
	jobTitle: t.hostDataControllerLabel,
	description: t.hostBio,
	image: `${normalizedBase}/${IMAGES.general.host}`,
	email: DATA_CONTROLLER.email,
	telephone: PHONE_E164,
	url: getCanonicalUrl(normalizedBase, "contact", lang),
	worksFor: {
		"@id": `${normalizedBase}/#business`,
	},
	knowsLanguage: [...LANGUAGES],
});

const buildBreadcrumbList = (
	pageUrl: string,
	items: readonly { name: string; item: string }[],
) => ({
	"@type": "BreadcrumbList",
	"@id": `${pageUrl}#breadcrumb`,
	itemListElement: items.map((entry, idx) => ({
		"@type": "ListItem",
		position: idx + 1,
		name: entry.name,
		item: entry.item,
	})),
});

export const buildHomeStructuredData = (
	t: TranslationDict,
	lang: Lang,
	baseUrl: string,
	pricingSummary: PortfolioPricingSummary = DEFAULT_PRICING_SUMMARY,
): string => {
	const normalizedBase = normalizeBaseUrl(baseUrl);
	const homeUrl = getCanonicalUrl(normalizedBase, "", lang);
	const contactUrl = getCanonicalUrl(normalizedBase, "contact", lang);
	const url1707 = getCanonicalUrl(normalizedBase, "Oceanview1707", lang);
	const url1606 = getCanonicalUrl(normalizedBase, "Oceanview1606", lang);

	return JSON.stringify({
		"@context": "https://schema.org",
		"@graph": [
			{
				"@type": "WebSite",
				"@id": `${normalizedBase}/#website`,
				url: homeUrl,
				name: "OceanViewFlats",
				description: t.schemaWebSiteDescription,
				inLanguage: lang,
				publisher: {
					"@id": `${normalizedBase}/#business`,
				},
			},
			{
				"@type": "WebPage",
				"@id": `${homeUrl}#webpage`,
				url: homeUrl,
				name: t.seoHomeTitle,
				description: t.seoHomeDescription,
				inLanguage: lang,
				isPartOf: {
					"@id": `${normalizedBase}/#website`,
				},
				about: {
					"@id": `${normalizedBase}/#business`,
				},
			},
			{
				"@type": "LodgingBusiness",
				"@id": `${normalizedBase}/#business`,
				name: "OceanViewFlats",
				legalName: DATA_CONTROLLER.legalName,
				description: t.schemaLodgingDescription,
				url: homeUrl,
				logo: buildLogoObject(normalizedBase),
				image: `${normalizedBase}/${IMAGES.home.hero}`,
				telephone: PHONE_E164,
				email: DATA_CONTROLLER.email,
				address: buildPostalAddress(),
				geo: buildGeoCoordinates(),
				hasMap: MAP_URL,
				priceRange: pricingSummary.priceRange,
				currenciesAccepted: "COP",
				paymentAccepted: t.schemaPaymentAccepted,
				knowsLanguage: [...LANGUAGES],
				license: `Registro Nacional de Turismo ${DATA_CONTROLLER.rntCombined}`,
				hasCredential: buildRntCredentials(),
				sameAs: [AIRBNB_URLS["1707"], AIRBNB_URLS["1606"]],
				founder: {
					"@id": `${normalizedBase}/#host`,
				},
				contactPoint: buildContactPoints(contactUrl),
				department: [
					{
						"@type": "VacationRental",
						"@id": `${normalizedBase}/Oceanview1707/#rental`,
						name: "OceanViewFlats 1707",
						url: url1707,
					},
					{
						"@type": "VacationRental",
						"@id": `${normalizedBase}/Oceanview1606/#rental`,
						name: "OceanViewFlats 1606",
						url: url1606,
					},
				],
			},
			buildHostPersonNode(t, lang, normalizedBase),
			{
				"@type": "FAQPage",
				"@id": `${homeUrl}#faq`,
				url: homeUrl,
				inLanguage: lang,
				isPartOf: {
					"@id": `${homeUrl}#webpage`,
				},
				mainEntity: HOME_FAQ_ITEMS.map((item) => ({
					"@type": "Question",
					name: t[item.qKey],
					acceptedAnswer: {
						"@type": "Answer",
						text: t[item.aKey],
					},
				})),
			},
		],
	});
};

export const buildPropertyStructuredData = (
	propertyId: PropertyId,
	t: TranslationDict,
	lang: Lang,
	baseUrl: string,
	pricingSummary: PortfolioPricingSummary = DEFAULT_PRICING_SUMMARY,
): string => {
	const normalizedBase = normalizeBaseUrl(baseUrl);
	const spec = PROPERTY_SCHEMA_SPECS[propertyId];
	const homeUrl = getCanonicalUrl(normalizedBase, "", lang);
	const pageUrl = getCanonicalUrl(normalizedBase, spec.pagePath, lang);
	const rentalId = `${normalizedBase}/${spec.pagePath}/#rental`;
	const propPricing = pricingSummary.properties[propertyId];

	const pageTitle = t[spec.titleKey];
	const pageDescription = t[spec.descriptionKey];
	const propertyDisplayName = t[spec.displayNameKey];
	const parkingFeatureName = t[spec.parkingFeatureKey];
	const guestBedroomBeds = spec.guestBedroomBeds;

	const containsPlace = [
		{
			"@type": "Room",
			name: t.schemaMasterBedroom,
			additionalType: "http://schema.googleapis.com/BedRoom",
			occupancy: { "@type": "QuantitativeValue", maxValue: 2, value: 2 },
			numberOfBedrooms: 1,
			numberOfBathroomsTotal: 1,
			bed: [{ "@type": "BedDetails", typeOfBed: "Queen", numberOfBeds: 1 }],
			amenityFeature: [
				{
					"@type": "LocationFeatureSpecification",
					name: t.amAC,
					value: true,
				},
				{
					"@type": "LocationFeatureSpecification",
					name: "TV",
					value: true,
				},
			],
		},
		{
			"@type": "Room",
			name: t.schemaGuestBedroom,
			additionalType: "http://schema.googleapis.com/BedRoom",
			occupancy: { "@type": "QuantitativeValue", maxValue: 2, value: 2 },
			numberOfBedrooms: 1,
			numberOfBathroomsTotal: 1,
			bed: guestBedroomBeds,
			amenityFeature: [
				{
					"@type": "LocationFeatureSpecification",
					name: t.amAC,
					value: true,
				},
			],
		},
		{
			"@type": "Room",
			name: t.schemaLivingRoom,
			occupancy: { "@type": "QuantitativeValue", maxValue: 2, value: 2 },
			bed: [{ "@type": "BedDetails", typeOfBed: "SofaBed", numberOfBeds: 1 }],
			amenityFeature: [
				{
					"@type": "LocationFeatureSpecification",
					name: t.amAC,
					value: true,
				},
				{
					"@type": "LocationFeatureSpecification",
					name: t.amOcean,
					value: true,
				},
			],
		},
	];

	const amenityFeature = [
		{
			"@type": "LocationFeatureSpecification",
			name: t.homeCompareWifiValue,
			value: true,
		},
		{
			"@type": "LocationFeatureSpecification",
			name: parkingFeatureName,
			value: true,
		},
		{
			"@type": "LocationFeatureSpecification",
			name: t.amSelfCheckIn,
			value: true,
		},
		{
			"@type": "LocationFeatureSpecification",
			name: t.schemaAmenityLaundry,
			value: true,
		},
		{
			"@type": "LocationFeatureSpecification",
			name: t.amKitchen,
			value: true,
		},
		{
			"@type": "LocationFeatureSpecification",
			name: t.amAC,
			value: true,
		},
		{
			"@type": "LocationFeatureSpecification",
			name: t.amBeach,
			value: true,
		},
		{
			"@type": "LocationFeatureSpecification",
			name: t.amOcean,
			value: true,
		},
		{
			"@type": "LocationFeatureSpecification",
			name: t.amPool,
			value: true,
			description: t.homeComparePoolMaintenanceValue,
		},
		{
			"@type": "LocationFeatureSpecification",
			name: t[spec.signatureAmenityKey],
			value: true,
		},
	];

	const tierOffers = propPricing.tiers.map((tier) => ({
		"@type": "Offer",
		price: String(tier.nightly_rate_cop),
		priceCurrency: "COP",
		availability: "https://schema.org/InStock",
		url: pageUrl,
		validFrom: tier.start_date,
		validThrough: tier.end_date,
		eligibleDuration: {
			"@type": "QuantitativeValue",
			value: tier.minimum_stay,
			minValue: tier.minimum_stay,
			unitCode: "DAY",
		},
	}));

	const reviews = PROPERTY_REVIEWS[propertyId];
	const aggregateRatingValue = calculateAggregateRatingValue(reviews);
	const faqItems = PROPERTY_FAQ_ITEMS[propertyId];

	return JSON.stringify({
		"@context": "https://schema.org",
		"@graph": [
			{
				"@type": "WebPage",
				"@id": `${pageUrl}#webpage`,
				url: pageUrl,
				name: pageTitle,
				description: pageDescription,
				inLanguage: lang,
				isPartOf: {
					"@id": `${normalizedBase}/#website`,
				},
				breadcrumb: {
					"@id": `${pageUrl}#breadcrumb`,
				},
				mainEntity: {
					"@id": rentalId,
				},
			},
			buildBreadcrumbList(pageUrl, [
				{ name: t.breadcrumbHome, item: homeUrl },
				{ name: propertyDisplayName, item: pageUrl },
			]),
			{
				"@type": "VacationRental",
				"@id": rentalId,
				url: pageUrl,
				mainEntityOfPage: {
					"@id": `${pageUrl}#webpage`,
				},
				name: spec.name,
				alternateName: propertyDisplayName,
				description: pageDescription,
				identifier: [
					{
						"@type": "PropertyValue",
						name: "Registro Nacional de Turismo (RNT)",
						propertyID: "RNT",
						value: spec.rntDigits,
					},
					{
						"@type": "PropertyValue",
						name: "OceanViewFlats Property ID",
						propertyID: "OVF",
						value: propertyId,
					},
				],
				license: `Registro Nacional de Turismo ${spec.rntLabel}`,
				floorLevel: spec.floorLevel,
				checkinTime: CHECKIN_TIME,
				checkoutTime: CHECKOUT_TIME,
				sameAs: AIRBNB_URLS[propertyId],
				provider: {
					"@type": "LodgingBusiness",
					"@id": `${normalizedBase}/#business`,
					name: "OceanViewFlats",
					url: homeUrl,
					telephone: PHONE_E164,
					email: DATA_CONTROLLER.email,
				},
				containedInPlace: {
					"@type": "ApartmentComplex",
					name: BUILDING_LOCATION.name,
					address: buildPostalAddress(),
					geo: buildGeoCoordinates(),
				},
				hasMap: MAP_URL,
				address: buildPostalAddress(),
				geo: buildGeoCoordinates(),
				image: getPropertySchemaImages(propertyId).map(
					(img) => `${normalizedBase}/${img}`,
				),
				numberOfBedrooms: 2,
				numberOfBathroomsTotal: 2,
				numberOfRooms: 3,
				occupancy: {
					"@type": "QuantitativeValue",
					maxValue: 6,
					value: 6,
				},
				containsPlace,
				amenityFeature,
				petsAllowed: false,
				offers: {
					"@type": "AggregateOffer",
					priceCurrency: "COP",
					lowPrice: String(propPricing.lowPrice),
					highPrice: String(propPricing.highPrice),
					offerCount: String(tierOffers.length),
					url: pageUrl,
					offers: tierOffers,
				},
				...(aggregateRatingValue !== null
					? {
							aggregateRating: {
								"@type": "AggregateRating",
								ratingValue: aggregateRatingValue,
								bestRating: "5",
								worstRating: "1",
								reviewCount: String(reviews.length),
							},
						}
					: {}),
				review: reviews.map((rev) => ({
					"@type": "Review",
					author: { "@type": "Person", name: t[rev.authorKey] },
					datePublished: rev.datePublished,
					inLanguage: lang,
					reviewRating: {
						"@type": "Rating",
						ratingValue: String(rev.ratingValue),
						bestRating: "5",
						worstRating: "1",
					},
					reviewBody: t[rev.textKey],
				})),
			},
			{
				"@type": "FAQPage",
				"@id": `${pageUrl}#faq`,
				url: pageUrl,
				inLanguage: lang,
				isPartOf: {
					"@id": `${pageUrl}#webpage`,
				},
				mainEntity: faqItems.map((item) => ({
					"@type": "Question",
					name: t[item.qKey],
					acceptedAnswer: {
						"@type": "Answer",
						text: t[item.aKey],
					},
				})),
			},
		],
	});
};

export const buildContactStructuredData = (
	t: TranslationDict,
	lang: Lang,
	baseUrl: string,
): string => {
	const normalizedBase = normalizeBaseUrl(baseUrl);
	const homeUrl = getCanonicalUrl(normalizedBase, "", lang);
	const pageUrl = getCanonicalUrl(normalizedBase, "contact", lang);

	return JSON.stringify({
		"@context": "https://schema.org",
		"@graph": [
			buildBreadcrumbList(pageUrl, [
				{ name: t.breadcrumbHome, item: homeUrl },
				{ name: t.navContact, item: pageUrl },
			]),
			{
				"@type": "ContactPage",
				"@id": `${pageUrl}#contactpage`,
				url: pageUrl,
				name: t.schemaContactPageName,
				description: t.seoContactDescription,
				inLanguage: lang,
				isPartOf: {
					"@id": `${normalizedBase}/#website`,
				},
				breadcrumb: {
					"@id": `${pageUrl}#breadcrumb`,
				},
				mainEntity: {
					"@id": `${normalizedBase}/#business`,
				},
			},
			{
				"@type": "LodgingBusiness",
				"@id": `${normalizedBase}/#business`,
				name: "OceanViewFlats",
				legalName: DATA_CONTROLLER.legalName,
				description: t.schemaLodgingDescription,
				url: homeUrl,
				logo: buildLogoObject(normalizedBase),
				telephone: PHONE_E164,
				email: DATA_CONTROLLER.email,
				address: buildPostalAddress(),
				geo: buildGeoCoordinates(),
				hasMap: MAP_URL,
				knowsLanguage: [...LANGUAGES],
				founder: {
					"@id": `${normalizedBase}/#host`,
				},
				contactPoint: buildContactPoints(pageUrl),
			},
		],
	});
};

export const buildPrivacyStructuredData = (
	t: TranslationDict,
	lang: Lang,
	baseUrl: string,
): string => {
	const normalizedBase = normalizeBaseUrl(baseUrl);
	const homeUrl = getCanonicalUrl(normalizedBase, "", lang);
	const pageUrl = getCanonicalUrl(normalizedBase, "privacy", lang);

	return JSON.stringify({
		"@context": "https://schema.org",
		"@graph": [
			buildBreadcrumbList(pageUrl, [
				{ name: t.breadcrumbHome, item: homeUrl },
				{ name: t.breadcrumbPrivacy, item: pageUrl },
			]),
			{
				"@type": "WebPage",
				"@id": `${pageUrl}#webpage`,
				url: pageUrl,
				name: t.privacySeoTitle,
				description: t.privacySeoDescription,
				inLanguage: lang,
				version: PRIVACY_POLICY_VERSION,
				isPartOf: {
					"@id": `${normalizedBase}/#website`,
				},
				breadcrumb: {
					"@id": `${pageUrl}#breadcrumb`,
				},
				publisher: {
					"@id": `${normalizedBase}/#business`,
				},
				author: {
					"@id": `${normalizedBase}/#host`,
				},
			},
			{
				"@type": "LodgingBusiness",
				"@id": `${normalizedBase}/#business`,
				name: "OceanViewFlats",
				legalName: DATA_CONTROLLER.legalName,
				description: t.schemaLodgingDescription,
				url: homeUrl,
				email: DATA_CONTROLLER.email,
				telephone: PHONE_E164,
				license: `Registro Nacional de Turismo ${DATA_CONTROLLER.rntCombined}`,
				address: buildPostalAddress(),
			},
			buildHostPersonNode(t, lang, normalizedBase),
		],
	});
};

export const buildWebPageStructuredData = (
	pagePath: string,
	name: string,
	description: string,
	lang: Lang,
	baseUrl: string,
): string => {
	const normalizedBase = normalizeBaseUrl(baseUrl);
	const pageUrl = getCanonicalUrl(normalizedBase, pagePath, lang);

	return JSON.stringify({
		"@context": "https://schema.org",
		"@graph": [
			{
				"@type": "WebPage",
				"@id": `${pageUrl}#webpage`,
				url: pageUrl,
				name,
				description,
				inLanguage: lang,
				isPartOf: {
					"@id": `${normalizedBase}/#website`,
				},
			},
		],
	});
};
