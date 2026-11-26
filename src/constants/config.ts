import type { Lang } from "../types";

export const LANGUAGES: readonly Lang[] = ["en", "es", "fr", "it", "de", "ja"];

export const getCanonicalUrl = (
	baseUrl: string,
	pagePath: string,
	lang: Lang,
): string => {
	const normalizedBase = baseUrl.replace(/\/+$/, "");
	if (pagePath === "404") {
		return lang === "en"
			? `${normalizedBase}/404.html`
			: `${normalizedBase}/404/${lang}.html`;
	}
	const dirBase = pagePath
		? `${normalizedBase}/${pagePath.replace(/^\/+|\/+$/g, "")}`
		: normalizedBase;
	return lang === "en" ? `${dirBase}/` : `${dirBase}/${lang}.html`;
};

export const AIRBNB_URLS = {
	"1707": "https://www.airbnb.com/rooms/1500108514798091235",
	"1606": "https://www.airbnb.com/rooms/1584825560087571592",
};

export const MERCADOPAGO_PUBLIC_KEY =
	process.env.MERCADOPAGO_PUBLIC_KEY ||
	"APP_USR-d820875d-6c1a-4f51-b997-7fc6f15779c6"; // Official MercadoPago Public Key (or Sandbox)

export const PRIVACY_POLICY_VERSION = "2026-10";

export const MINOR_DOC_TYPES = [
	"Tarjeta de Identidad",
	"Registro Civil",
] as const;

export const DATA_CONTROLLER = {
	name: "OceanViewFlats — Manuel Enrique Herrera",
	legalName: "OceanViewFlats — Manuel Enrique Herrera",
	rnt1707: "RNT #268781",
	rnt1606: "RNT #270413",
	rntCombined: "RNT #268781 & RNT #270413",
	address: "Calle 26 # 2-80, Playa Salguero, Santa Marta, Magdalena, Colombia",
	email: "rentals@oceanviewflats.com",
	phone: "+57 (310) 815-5234",
	phoneHref: "tel:+573108155234",
	whatsappHref: "https://wa.me/573108155234",
	consultaSlaBusinessDays: 10,
	reclamoSlaBusinessDays: 15,
	retentionDaysAfterCheckout: 90,
} as const;

export const PRIVACY_POLICY_PATHS: Record<string, string> = {
	en: "privacy/",
	es: "privacy/es.html",
	fr: "privacy/fr.html",
	it: "privacy/it.html",
	de: "privacy/de.html",
	ja: "privacy/ja.html",
};

export const CONTACT_PAGE_PATHS: Record<string, string> = {
	en: "contact/",
	es: "contact/es.html",
	fr: "contact/fr.html",
	it: "contact/it.html",
	de: "contact/de.html",
	ja: "contact/ja.html",
};

export const GUIDE_PAGE_PATHS: Record<string, string> = {
	en: "guide/",
	es: "guide/es.html",
	fr: "guide/fr.html",
	it: "guide/it.html",
	de: "guide/de.html",
	ja: "guide/ja.html",
};

export const REGISTRY_PAGE_PATHS: Record<string, string> = {
	en: "registry/",
	es: "registry/es.html",
	fr: "registry/fr.html",
	it: "registry/it.html",
	de: "registry/de.html",
	ja: "registry/ja.html",
};

export const BOOKING_SUCCESS_PATHS: Record<string, string> = {
	en: "booking-success/",
	es: "booking-success/es.html",
	fr: "booking-success/fr.html",
	it: "booking-success/it.html",
	de: "booking-success/de.html",
	ja: "booking-success/ja.html",
};

export const BOOKING_PENDING_PATHS: Record<string, string> = {
	en: "booking-pending/",
	es: "booking-pending/es.html",
	fr: "booking-pending/fr.html",
	it: "booking-pending/it.html",
	de: "booking-pending/de.html",
	ja: "booking-pending/ja.html",
};

export const HOME_PAGE_PATHS: Record<string, string> = {
	en: "",
	es: "es.html",
	fr: "fr.html",
	it: "it.html",
	de: "de.html",
	ja: "ja.html",
};

export const OCEANVIEW_1707_PATHS: Record<string, string> = {
	en: "Oceanview1707/",
	es: "Oceanview1707/es.html",
	fr: "Oceanview1707/fr.html",
	it: "Oceanview1707/it.html",
	de: "Oceanview1707/de.html",
	ja: "Oceanview1707/ja.html",
};

export const OCEANVIEW_1606_PATHS: Record<string, string> = {
	en: "Oceanview1606/",
	es: "Oceanview1606/es.html",
	fr: "Oceanview1606/fr.html",
	it: "Oceanview1606/it.html",
	de: "Oceanview1606/de.html",
	ja: "Oceanview1606/ja.html",
};

const resolveLocalizedPath = (
	paths: Record<string, string>,
	lang: string,
	assetPrefix = "./",
): string => {
	const relPath = paths[lang] || paths.en;
	return `${assetPrefix}${relPath}`;
};

export const getPrivacyPolicyPath = (
	lang: string,
	assetPrefix = "./",
): string => resolveLocalizedPath(PRIVACY_POLICY_PATHS, lang, assetPrefix);

export const getContactPagePath = (lang: string, assetPrefix = "./"): string =>
	resolveLocalizedPath(CONTACT_PAGE_PATHS, lang, assetPrefix);

export const getGuidePagePath = (lang: string, assetPrefix = "./"): string =>
	resolveLocalizedPath(GUIDE_PAGE_PATHS, lang, assetPrefix);

export const getRegistryPagePath = (lang: string, assetPrefix = "./"): string =>
	resolveLocalizedPath(REGISTRY_PAGE_PATHS, lang, assetPrefix);

export const getBookingSuccessPath = (
	lang: string,
	assetPrefix = "./",
): string => resolveLocalizedPath(BOOKING_SUCCESS_PATHS, lang, assetPrefix);

export const getBookingPendingPath = (
	lang: string,
	assetPrefix = "./",
): string => resolveLocalizedPath(BOOKING_PENDING_PATHS, lang, assetPrefix);

export const getHomePagePath = (lang: string, assetPrefix = "./"): string => {
	const relPath = HOME_PAGE_PATHS[lang] ?? HOME_PAGE_PATHS.en;
	return `${assetPrefix}${relPath}`;
};

export const getPropertyPagePath = (
	propertyId: "1707" | "1606",
	lang: string,
	assetPrefix = "./",
): string => {
	const paths =
		propertyId === "1707" ? OCEANVIEW_1707_PATHS : OCEANVIEW_1606_PATHS;
	const relPath = paths[lang] || paths.en;
	return `${assetPrefix}${relPath}`;
};

export const getOceanview1707Path = (
	lang: string,
	assetPrefix = "./",
): string => getPropertyPagePath("1707", lang, assetPrefix);

export const getOceanview1606Path = (
	lang: string,
	assetPrefix = "./",
): string => getPropertyPagePath("1606", lang, assetPrefix);

export interface HomePropertyCardConfig {
	id: "1707" | "1606";
	cardDomId: string;
	rateBadgeDomId: string;
	imageKey: "1707" | "1606";
	imageAltKey: "hero1707ImgAlt" | "hero1606ImgAlt";
	badgeKey: "homeCard1707Badge" | "homeCard1606Badge";
	rateKey: "homeCard1707Rate" | "homeCard1606Rate";
	titleKey: "heroTitle" | "hero1606Title";
	descKey: "nomadDesc" | "romanticDesc";
	bedsKey: "statsBeds" | "stats1606Beds";
	wifiKey: "homeCardWifi";
	parkingKey: "homeCard1707Parking" | "homeCard1606Parking";
}

export const HOME_PROPERTY_CARDS: readonly HomePropertyCardConfig[] = [
	{
		id: "1707",
		cardDomId: "home-card-1707",
		rateBadgeDomId: "home-rate-badge-1707",
		imageKey: "1707",
		imageAltKey: "hero1707ImgAlt",
		badgeKey: "homeCard1707Badge",
		rateKey: "homeCard1707Rate",
		titleKey: "heroTitle",
		descKey: "nomadDesc",
		bedsKey: "statsBeds",
		wifiKey: "homeCardWifi",
		parkingKey: "homeCard1707Parking",
	},
	{
		id: "1606",
		cardDomId: "home-card-1606",
		rateBadgeDomId: "home-rate-badge-1606",
		imageKey: "1606",
		imageAltKey: "hero1606ImgAlt",
		badgeKey: "homeCard1606Badge",
		rateKey: "homeCard1606Rate",
		titleKey: "hero1606Title",
		descKey: "romanticDesc",
		bedsKey: "stats1606Beds",
		wifiKey: "homeCardWifi",
		parkingKey: "homeCard1606Parking",
	},
];

export interface HomeFaqItemConfig {
	id: string;
	qKey: "faqQ1" | "faqQ2" | "faqQ3" | "faqQ4" | "faqQ5" | "faqQ6";
	aKey: "faqA1" | "faqA2" | "faqA3" | "faqA4" | "faqA5" | "faqA6";
	defaultOpen: boolean;
}

export const HOME_FAQ_ITEMS: readonly HomeFaqItemConfig[] = [
	{ id: "faq-1", qKey: "faqQ1", aKey: "faqA1", defaultOpen: false },
	{ id: "faq-2", qKey: "faqQ2", aKey: "faqA2", defaultOpen: false },
	{ id: "faq-3", qKey: "faqQ3", aKey: "faqA3", defaultOpen: true },
	{ id: "faq-4", qKey: "faqQ4", aKey: "faqA4", defaultOpen: false },
	{ id: "faq-5", qKey: "faqQ5", aKey: "faqA5", defaultOpen: false },
	{ id: "faq-6", qKey: "faqQ6", aKey: "faqA6", defaultOpen: false },
];

export interface RegistryGuestSlotConfig {
	num: number;
	isPrimary: boolean;
	isCompanion: boolean;
	cardClassName: string;
	phoneLabelKey: "registryPhone" | "registryPhoneOpt";
}

export const REGISTRY_GUEST_SLOTS: readonly RegistryGuestSlotConfig[] =
	Array.from({ length: 12 }, (_, idx) => {
		const num = idx + 1;
		const isPrimary = num === 1;
		return {
			num,
			isPrimary,
			isCompanion: !isPrimary,
			cardClassName: isPrimary
				? "p-5 rounded-2xl border transition-all duration-300 bg-slate-50/50 border-slate-200 shadow-sm"
				: "p-5 rounded-2xl border transition-all duration-300 bg-white border-slate-150 shadow-xs hidden",
			phoneLabelKey: isPrimary ? "registryPhone" : "registryPhoneOpt",
		};
	});

export const IMAGES = {
	mainEntrance: "images/main-entrance.webp",
	"1707": {
		hero: "images/1707-main-room-seaside.webp",
		nomad: "images/1707-balcony-working-nomad.webp",
		bedroom: "images/1707-living.webp",
		pool: "images/1707-pool.webp",
	},
	"1606": {
		hero: "images/1606-main-room-seaside.webp",
		romantic: "images/1606-romantic-experiences.webp",
		bedroom: "images/1606-living.webp",
		pool: "images/1606-pool.webp",
	},
	home: {
		hero: "images/home-hero.webp",
	},
	general: {
		host: "images/host-image.webp",
	},
};

export const IMAGE_DIMENSIONS = {
	mainEntrance: { width: 1280, height: 677 },
	homeHero: { width: 1600, height: 1355 },
	host: { width: 600, height: 600 },
	"1707": {
		hero: { width: 1980, height: 1280 },
		nomad: { width: 800, height: 450 },
		bedroom: { width: 500, height: 333 },
		pool: { width: 500, height: 333 },
	},
	"1606": {
		hero: { width: 1980, height: 1280 },
		romantic: { width: 800, height: 450 },
		bedroom: { width: 500, height: 333 },
		pool: { width: 501, height: 333 },
	},
} as const;

export interface HomeExperienceFeatureConfig {
	iconId: "wifi" | "users" | "heart";
	iconThemeKey: "editorialFeatureIconSky" | "editorialFeatureIconRose";
	titleKey:
		| "homeExpNomad"
		| "homeExp1707FamilyTitle"
		| "homeExpRomantic"
		| "homeExpFamily";
	descKey:
		| "homeExpNomadDesc"
		| "homeExp1707FamilyDesc"
		| "homeExpRomanticDesc"
		| "homeExpFamilyDesc";
}

export interface HomeExperienceCardConfig {
	propertyId: "1707" | "1606";
	imageSrc: string;
	imageWidth: number;
	imageHeight: number;
	imageAltKey: "nomadImgAlt" | "romanticImgAlt";
	titleKey: "heroTitle" | "hero1606Title";
	badgeKey: "homeCard1707Badge" | "homeCard1606Badge";
	features: readonly HomeExperienceFeatureConfig[];
	reviewTextKey: "review1707_2Text" | "review1Text";
	reviewAuthorKey: "review1707_2Author" | "review1Author";
}

export const HOME_EXPERIENCE_CARDS: readonly HomeExperienceCardConfig[] = [
	{
		propertyId: "1707",
		imageSrc: IMAGES["1707"].nomad,
		imageWidth: IMAGE_DIMENSIONS["1707"].nomad.width,
		imageHeight: IMAGE_DIMENSIONS["1707"].nomad.height,
		imageAltKey: "nomadImgAlt",
		titleKey: "heroTitle",
		badgeKey: "homeCard1707Badge",
		features: [
			{
				iconId: "wifi",
				iconThemeKey: "editorialFeatureIconSky",
				titleKey: "homeExpNomad",
				descKey: "homeExpNomadDesc",
			},
			{
				iconId: "users",
				iconThemeKey: "editorialFeatureIconSky",
				titleKey: "homeExp1707FamilyTitle",
				descKey: "homeExp1707FamilyDesc",
			},
		],
		reviewTextKey: "review1707_2Text",
		reviewAuthorKey: "review1707_2Author",
	},
	{
		propertyId: "1606",
		imageSrc: IMAGES["1606"].romantic,
		imageWidth: IMAGE_DIMENSIONS["1606"].romantic.width,
		imageHeight: IMAGE_DIMENSIONS["1606"].romantic.height,
		imageAltKey: "romanticImgAlt",
		titleKey: "hero1606Title",
		badgeKey: "homeCard1606Badge",
		features: [
			{
				iconId: "heart",
				iconThemeKey: "editorialFeatureIconRose",
				titleKey: "homeExpRomantic",
				descKey: "homeExpRomanticDesc",
			},
			{
				iconId: "users",
				iconThemeKey: "editorialFeatureIconRose",
				titleKey: "homeExpFamily",
				descKey: "homeExpFamilyDesc",
			},
		],
		reviewTextKey: "review1Text",
		reviewAuthorKey: "review1Author",
	},
];

// #302 Homepage Property Comparison & Playa Salguero Proximity Configs
export interface HomeComparisonRowConfig {
	id: string;
	labelKey:
		| "homeCompareRowFloor"
		| "homeCompareRowRnt"
		| "homeCompareRowIdealFor"
		| "homeCompareRowRate"
		| "homeCompareRowMinStay"
		| "homeCompareRowBeds"
		| "homeCompareRowOccupancy"
		| "homeCompareRowWifi"
		| "homeCompareRowParking"
		| "homeCompareRowCheckInOut"
		| "homeCompareRowBuildingRegistrationFee"
		| "homeCompareRowPoolMaintenance";
	val1707Key:
		| "homeCompare1707Floor"
		| "rnt1707"
		| "homeCompare1707IdealFor"
		| "homeCompare1707Rate"
		| "homeCompare1707MinStay"
		| "homeCompare1707Beds"
		| "statsGuests"
		| "homeCompareWifiValue"
		| "homeCompare1707Parking"
		| "homeCompareCheckInOutValue"
		| "homeCompareBuildingRegistrationFeeValue"
		| "homeComparePoolMaintenanceValue";
	val1606Key:
		| "homeCompare1606Floor"
		| "rnt1606"
		| "homeCompare1606IdealFor"
		| "homeCompare1606Rate"
		| "homeCompare1606MinStay"
		| "homeCompare1606Beds"
		| "statsGuests"
		| "homeCompareWifiValue"
		| "homeCompare1606Parking"
		| "homeCompareCheckInOutValue"
		| "homeCompareBuildingRegistrationFeeValue"
		| "homeComparePoolMaintenanceValue";
}

export const HOME_PROPERTY_COMPARISON_ROWS: readonly HomeComparisonRowConfig[] =
	[
		{
			id: "floor",
			labelKey: "homeCompareRowFloor",
			val1707Key: "homeCompare1707Floor",
			val1606Key: "homeCompare1606Floor",
		},
		{
			id: "rnt",
			labelKey: "homeCompareRowRnt",
			val1707Key: "rnt1707",
			val1606Key: "rnt1606",
		},
		{
			id: "ideal-for",
			labelKey: "homeCompareRowIdealFor",
			val1707Key: "homeCompare1707IdealFor",
			val1606Key: "homeCompare1606IdealFor",
		},
		{
			id: "nightly-rate",
			labelKey: "homeCompareRowRate",
			val1707Key: "homeCompare1707Rate",
			val1606Key: "homeCompare1606Rate",
		},
		{
			id: "min-stay",
			labelKey: "homeCompareRowMinStay",
			val1707Key: "homeCompare1707MinStay",
			val1606Key: "homeCompare1606MinStay",
		},
		{
			id: "beds",
			labelKey: "homeCompareRowBeds",
			val1707Key: "homeCompare1707Beds",
			val1606Key: "homeCompare1606Beds",
		},
		{
			id: "max-occupancy",
			labelKey: "homeCompareRowOccupancy",
			val1707Key: "statsGuests",
			val1606Key: "statsGuests",
		},
		{
			id: "wifi",
			labelKey: "homeCompareRowWifi",
			val1707Key: "homeCompareWifiValue",
			val1606Key: "homeCompareWifiValue",
		},
		{
			id: "parking",
			labelKey: "homeCompareRowParking",
			val1707Key: "homeCompare1707Parking",
			val1606Key: "homeCompare1606Parking",
		},
		{
			id: "check-in-out",
			labelKey: "homeCompareRowCheckInOut",
			val1707Key: "homeCompareCheckInOutValue",
			val1606Key: "homeCompareCheckInOutValue",
		},
		{
			id: "building-registration-fee",
			labelKey: "homeCompareRowBuildingRegistrationFee",
			val1707Key: "homeCompareBuildingRegistrationFeeValue",
			val1606Key: "homeCompareBuildingRegistrationFeeValue",
		},
		{
			id: "pool-maintenance",
			labelKey: "homeCompareRowPoolMaintenance",
			val1707Key: "homeComparePoolMaintenanceValue",
			val1606Key: "homeComparePoolMaintenanceValue",
		},
	];

export const BUILDING_LOCATION = {
	name: "Edificio Salguero Sunset",
	streetAddress: "Calle 26 # 2-80",
	neighborhood: "Playa Salguero",
	city: "Santa Marta",
	region: "Magdalena",
	postalCode: "470006",
	country: "Colombia",
	latitude: 11.1876481,
	longitude: -74.2313921,
} as const;

export interface PlayaSalgueroLandmarkConfig {
	id:
		| "playa-salguero-beach"
		| "el-rodadero"
		| "smr-airport"
		| "historic-center"
		| "tayrona-park";
	iconId: "waves" | "compass" | "plane" | "landmark" | "trees";
	nameKey:
		| "homeLocBeachName"
		| "homeLocRodaderoName"
		| "homeLocAirportName"
		| "homeLocHistoricName"
		| "homeLocTayronaName";
	proximityKey:
		| "homeLocBeachProximity"
		| "homeLocRodaderoProximity"
		| "homeLocAirportProximity"
		| "homeLocHistoricProximity"
		| "homeLocTayronaProximity";
	descKey:
		| "homeLocBeachDesc"
		| "homeLocRodaderoDesc"
		| "homeLocAirportDesc"
		| "homeLocHistoricDesc"
		| "homeLocTayronaDesc";
	distanceApprox: string;
	travelTimeApprox: string;
}

export const PLAYA_SALGUERO_LANDMARKS: readonly PlayaSalgueroLandmarkConfig[] =
	[
		{
			id: "playa-salguero-beach",
			iconId: "waves",
			nameKey: "homeLocBeachName",
			proximityKey: "homeLocBeachProximity",
			descKey: "homeLocBeachDesc",
			distanceApprox: "~150 m (1 block)",
			travelTimeApprox: "~2 min walk",
		},
		{
			id: "el-rodadero",
			iconId: "compass",
			nameKey: "homeLocRodaderoName",
			proximityKey: "homeLocRodaderoProximity",
			descKey: "homeLocRodaderoDesc",
			distanceApprox: "~3 km",
			travelTimeApprox: "~5–10 min by car",
		},
		{
			id: "smr-airport",
			iconId: "plane",
			nameKey: "homeLocAirportName",
			proximityKey: "homeLocAirportProximity",
			descKey: "homeLocAirportDesc",
			distanceApprox: "~10 km",
			travelTimeApprox: "~15–20 min by car",
		},
		{
			id: "historic-center",
			iconId: "landmark",
			nameKey: "homeLocHistoricName",
			proximityKey: "homeLocHistoricProximity",
			descKey: "homeLocHistoricDesc",
			distanceApprox: "~8 km",
			travelTimeApprox: "~20–25 min by car",
		},
		{
			id: "tayrona-park",
			iconId: "trees",
			nameKey: "homeLocTayronaName",
			proximityKey: "homeLocTayronaProximity",
			descKey: "homeLocTayronaDesc",
			distanceApprox: "~40 km",
			travelTimeApprox: "~50–60 min by car",
		},
	];

// #301 per-Property FAQ & verified review configs (shared with #303 JSON-LD)
export type PropertyId = "1707" | "1606";

export type PropertyFaq1707QKey =
	| "faq1707Q1"
	| "faq1707Q2"
	| "faq1707Q3"
	| "faq1707Q4"
	| "faq1707Q5"
	| "faq1707Q6";

export type PropertyFaq1707AKey =
	| "faq1707A1"
	| "faq1707A2"
	| "faq1707A3"
	| "faq1707A4"
	| "faq1707A5"
	| "faq1707A6";

export type PropertyFaq1606QKey =
	| "faq1606Q1"
	| "faq1606Q2"
	| "faq1606Q3"
	| "faq1606Q4"
	| "faq1606Q5"
	| "faq1606Q6";

export type PropertyFaq1606AKey =
	| "faq1606A1"
	| "faq1606A2"
	| "faq1606A3"
	| "faq1606A4"
	| "faq1606A5"
	| "faq1606A6";

export type PropertyFaqQKey = PropertyFaq1707QKey | PropertyFaq1606QKey;
export type PropertyFaqAKey = PropertyFaq1707AKey | PropertyFaq1606AKey;

export interface PropertyFaqItemConfig {
	id: string;
	qKey: PropertyFaqQKey;
	aKey: PropertyFaqAKey;
	defaultOpen: boolean;
}

export const PROPERTY_1707_FAQ_ITEMS: readonly PropertyFaqItemConfig[] = [
	{ id: "faq-1707-1", qKey: "faq1707Q1", aKey: "faq1707A1", defaultOpen: true },
	{
		id: "faq-1707-2",
		qKey: "faq1707Q2",
		aKey: "faq1707A2",
		defaultOpen: false,
	},
	{
		id: "faq-1707-3",
		qKey: "faq1707Q3",
		aKey: "faq1707A3",
		defaultOpen: false,
	},
	{
		id: "faq-1707-4",
		qKey: "faq1707Q4",
		aKey: "faq1707A4",
		defaultOpen: false,
	},
	{
		id: "faq-1707-5",
		qKey: "faq1707Q5",
		aKey: "faq1707A5",
		defaultOpen: false,
	},
	{
		id: "faq-1707-6",
		qKey: "faq1707Q6",
		aKey: "faq1707A6",
		defaultOpen: false,
	},
];

export const PROPERTY_1606_FAQ_ITEMS: readonly PropertyFaqItemConfig[] = [
	{ id: "faq-1606-1", qKey: "faq1606Q1", aKey: "faq1606A1", defaultOpen: true },
	{
		id: "faq-1606-2",
		qKey: "faq1606Q2",
		aKey: "faq1606A2",
		defaultOpen: false,
	},
	{
		id: "faq-1606-3",
		qKey: "faq1606Q3",
		aKey: "faq1606A3",
		defaultOpen: false,
	},
	{
		id: "faq-1606-4",
		qKey: "faq1606Q4",
		aKey: "faq1606A4",
		defaultOpen: false,
	},
	{
		id: "faq-1606-5",
		qKey: "faq1606Q5",
		aKey: "faq1606A5",
		defaultOpen: false,
	},
	{
		id: "faq-1606-6",
		qKey: "faq1606Q6",
		aKey: "faq1606A6",
		defaultOpen: false,
	},
];

export const PROPERTY_FAQ_ITEMS: Record<
	PropertyId,
	readonly PropertyFaqItemConfig[]
> = {
	"1707": PROPERTY_1707_FAQ_ITEMS,
	"1606": PROPERTY_1606_FAQ_ITEMS,
};

export interface PropertyReviewMeta {
	id: string;
	authorKey:
		| "review1707_2Author"
		| "review1707Author"
		| "review1Author"
		| "review2Author";
	textKey:
		| "review1707_2Text"
		| "review1707Text"
		| "review1Text"
		| "review2Text";
	datePublished: string;
	ratingValue: number;
}

export const PROPERTY_REVIEWS: Record<
	PropertyId,
	readonly PropertyReviewMeta[]
> = {
	"1707": [
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
	"1606": [
		{
			id: "review-1606-1",
			authorKey: "review1Author",
			textKey: "review1Text",
			datePublished: "2026-04-02",
			ratingValue: 5,
		},
		{
			id: "review-1606-2",
			authorKey: "review2Author",
			textKey: "review2Text",
			datePublished: "2026-03-20",
			ratingValue: 5,
		},
	],
};

export type GalleryAltKey =
	| "gallery1707_1"
	| "gallery1707_2"
	| "gallery1707_3"
	| "gallery1707_4"
	| "gallery1707_5"
	| "gallery1707_6"
	| "gallery1707_7"
	| "gallery1707_8"
	| "gallery1707_9"
	| "gallery1707_10"
	| "gallery1707_11"
	| "gallery1707_12"
	| "gallery1707_13"
	| "gallery1707_14"
	| "gallery1606_1"
	| "gallery1606_2"
	| "gallery1606_3"
	| "gallery1606_4"
	| "gallery1606_5"
	| "gallery1606_6"
	| "gallery1606_7"
	| "gallery1606_8"
	| "gallery1606_9"
	| "gallery1606_10"
	| "gallery1606_11"
	| "gallery1606_12"
	| "gallery1606_13"
	| "gallery1606_14";

export interface GalleryImageConfig {
	src: string;
	thumb: string;
	altKey: GalleryAltKey;
	thumbWidth: number;
	thumbHeight: number;
}

export const PROPERTY_GALLERY_IMAGES: Record<
	PropertyId,
	readonly GalleryImageConfig[]
> = {
	"1707": [
		{
			src: "images/1707/balcony-1.webp",
			thumb: "images/1707/thumbs/balcony-1.webp",
			thumbWidth: 480,
			thumbHeight: 270,
			altKey: "gallery1707_1",
		},
		{
			src: "images/1707/balcony-2.webp",
			thumb: "images/1707/thumbs/balcony-2.webp",
			thumbWidth: 480,
			thumbHeight: 345,
			altKey: "gallery1707_2",
		},
		{
			src: "images/1707/livingroom-1.webp",
			thumb: "images/1707/thumbs/livingroom-1.webp",
			thumbWidth: 480,
			thumbHeight: 653,
			altKey: "gallery1707_3",
		},
		{
			src: "images/1707/livingroom-2.webp",
			thumb: "images/1707/thumbs/livingroom-2.webp",
			thumbWidth: 480,
			thumbHeight: 299,
			altKey: "gallery1707_4",
		},
		{
			src: "images/1707/dinningroom-1.webp",
			thumb: "images/1707/thumbs/dinningroom-1.webp",
			thumbWidth: 480,
			thumbHeight: 723,
			altKey: "gallery1707_5",
		},
		{
			src: "images/1707/dinningroom-2.webp",
			thumb: "images/1707/thumbs/dinningroom-2.webp",
			thumbWidth: 480,
			thumbHeight: 696,
			altKey: "gallery1707_6",
		},
		{
			src: "images/1707/kitchen-1.webp",
			thumb: "images/1707/thumbs/kitchen-1.webp",
			thumbWidth: 480,
			thumbHeight: 270,
			altKey: "gallery1707_7",
		},
		{
			src: "images/1707/room1-1.webp",
			thumb: "images/1707/thumbs/room1-1.webp",
			thumbWidth: 480,
			thumbHeight: 325,
			altKey: "gallery1707_8",
		},
		{
			src: "images/1707/room1-2.webp",
			thumb: "images/1707/thumbs/room1-2.webp",
			thumbWidth: 480,
			thumbHeight: 284,
			altKey: "gallery1707_9",
		},
		{
			src: "images/1707/room1-3.webp",
			thumb: "images/1707/thumbs/room1-3.webp",
			thumbWidth: 480,
			thumbHeight: 270,
			altKey: "gallery1707_10",
		},
		{
			src: "images/1707/room2-1.webp",
			thumb: "images/1707/thumbs/room2-1.webp",
			thumbWidth: 480,
			thumbHeight: 269,
			altKey: "gallery1707_11",
		},
		{
			src: "images/1707/bathroom1-1.webp",
			thumb: "images/1707/thumbs/bathroom1-1.webp",
			thumbWidth: 480,
			thumbHeight: 664,
			altKey: "gallery1707_12",
		},
		{
			src: "images/1707/bathroom2-1.webp",
			thumb: "images/1707/thumbs/bathroom2-1.webp",
			thumbWidth: 480,
			thumbHeight: 341,
			altKey: "gallery1707_13",
		},
		{
			src: "images/1707/pulloutcouch-1.webp",
			thumb: "images/1707/thumbs/pulloutcouch-1.webp",
			thumbWidth: 480,
			thumbHeight: 309,
			altKey: "gallery1707_14",
		},
	],
	"1606": [
		{
			src: "images/1606/balcony-1.webp",
			thumb: "images/1606/thumbs/balcony-1.webp",
			thumbWidth: 480,
			thumbHeight: 304,
			altKey: "gallery1606_1",
		},
		{
			src: "images/1606/livingroom-1.webp",
			thumb: "images/1606/thumbs/livingroom-1.webp",
			thumbWidth: 480,
			thumbHeight: 286,
			altKey: "gallery1606_2",
		},
		{
			src: "images/1606/livingroom-2.webp",
			thumb: "images/1606/thumbs/livingroom-2.webp",
			thumbWidth: 480,
			thumbHeight: 586,
			altKey: "gallery1606_3",
		},
		{
			src: "images/1606/dinningroom-1.webp",
			thumb: "images/1606/thumbs/dinningroom-1.webp",
			thumbWidth: 480,
			thumbHeight: 768,
			altKey: "gallery1606_4",
		},
		{
			src: "images/1606/kitchen-1.webp",
			thumb: "images/1606/thumbs/kitchen-1.webp",
			thumbWidth: 480,
			thumbHeight: 270,
			altKey: "gallery1606_5",
		},
		{
			src: "images/1606/entertainment-2.webp",
			thumb: "images/1606/thumbs/entertainment-2.webp",
			thumbWidth: 480,
			thumbHeight: 280,
			altKey: "gallery1606_6",
		},
		{
			src: "images/1606/room1-1.webp",
			thumb: "images/1606/thumbs/room1-1.webp",
			thumbWidth: 480,
			thumbHeight: 286,
			altKey: "gallery1606_7",
		},
		{
			src: "images/1606/room1-2.webp",
			thumb: "images/1606/thumbs/room1-2.webp",
			thumbWidth: 480,
			thumbHeight: 283,
			altKey: "gallery1606_8",
		},
		{
			src: "images/1606/room2-1.webp",
			thumb: "images/1606/thumbs/room2-1.webp",
			thumbWidth: 480,
			thumbHeight: 276,
			altKey: "gallery1606_9",
		},
		{
			src: "images/1606/room2-2.webp",
			thumb: "images/1606/thumbs/room2-2.webp",
			thumbWidth: 480,
			thumbHeight: 248,
			altKey: "gallery1606_10",
		},
		{
			src: "images/1606/bathroom1-1.webp",
			thumb: "images/1606/thumbs/bathroom1-1.webp",
			thumbWidth: 480,
			thumbHeight: 270,
			altKey: "gallery1606_11",
		},
		{
			src: "images/1606/bathroom1-2.webp",
			thumb: "images/1606/thumbs/bathroom1-2.webp",
			thumbWidth: 480,
			thumbHeight: 722,
			altKey: "gallery1606_12",
		},
		{
			src: "images/1606/bathroom2-1.webp",
			thumb: "images/1606/thumbs/bathroom2-1.webp",
			thumbWidth: 480,
			thumbHeight: 283,
			altKey: "gallery1606_13",
		},
		{
			src: "images/1606/pulloutcouch-1.webp",
			thumb: "images/1606/thumbs/pulloutcouch-1.webp",
			thumbWidth: 480,
			thumbHeight: 593,
			altKey: "gallery1606_14",
		},
	],
};
