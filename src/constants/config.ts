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
	imageAltKey: "nomadImgAlt" | "romanticImgAlt";
	titleKey: "heroTitle" | "hero1606Title";
	badgeKey: "homeCard1707Badge" | "homeCard1606Badge";
	features: readonly HomeExperienceFeatureConfig[];
	reviewTextKey: "review1707Text" | "review1Text";
	reviewAuthorKey: "review1707Author" | "review1Author";
}

export const HOME_EXPERIENCE_CARDS: readonly HomeExperienceCardConfig[] = [
	{
		propertyId: "1707",
		imageSrc: IMAGES["1707"].nomad,
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
		reviewTextKey: "review1707Text",
		reviewAuthorKey: "review1707Author",
	},
	{
		propertyId: "1606",
		imageSrc: IMAGES["1606"].romantic,
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
		| "homeCompareRowBuildingFee"
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
		| "homeCompareBuildingFeeValue"
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
		| "homeCompareBuildingFeeValue"
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
			id: "building-fee",
			labelKey: "homeCompareRowBuildingFee",
			val1707Key: "homeCompareBuildingFeeValue",
			val1606Key: "homeCompareBuildingFeeValue",
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
