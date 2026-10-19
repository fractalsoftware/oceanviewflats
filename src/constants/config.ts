export const AIRBNB_URLS = {
	"1707": "https://www.airbnb.com/rooms/1500108514798091235",
	"1606": "https://www.airbnb.com/rooms/1584825560087571592",
};

export const MERCADOPAGO_PUBLIC_KEY =
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
	en: "privacy/index.html",
	es: "privacy/es.html",
	fr: "privacy/fr.html",
	it: "privacy/it.html",
	de: "privacy/de.html",
	ja: "privacy/ja.html",
};

export const CONTACT_PAGE_PATHS: Record<string, string> = {
	en: "contact/index.html",
	es: "contact/es.html",
	fr: "contact/fr.html",
	it: "contact/it.html",
	de: "contact/de.html",
	ja: "contact/ja.html",
};

export const GUIDE_PAGE_PATHS: Record<string, string> = {
	en: "guide/index.html",
	es: "guide/es.html",
	fr: "guide/fr.html",
	it: "guide/it.html",
	de: "guide/de.html",
	ja: "guide/ja.html",
};

export const HOME_PAGE_PATHS: Record<string, string> = {
	en: "",
	es: "es.html",
	fr: "fr.html",
	it: "it.html",
	de: "de.html",
	ja: "ja.html",
};

export const getPrivacyPolicyPath = (
	lang: string,
	assetPrefix = "./",
): string => {
	const relPath = PRIVACY_POLICY_PATHS[lang] || PRIVACY_POLICY_PATHS.en;
	return `${assetPrefix}${relPath}`;
};

export const getContactPagePath = (
	lang: string,
	assetPrefix = "./",
): string => {
	const relPath = CONTACT_PAGE_PATHS[lang] || CONTACT_PAGE_PATHS.en;
	return `${assetPrefix}${relPath}`;
};

export const getGuidePagePath = (lang: string, assetPrefix = "./"): string => {
	const relPath = GUIDE_PAGE_PATHS[lang] || GUIDE_PAGE_PATHS.en;
	return `${assetPrefix}${relPath}`;
};

export const getHomePagePath = (lang: string, assetPrefix = "./"): string => {
	const relPath = HOME_PAGE_PATHS[lang] ?? HOME_PAGE_PATHS.en;
	return `${assetPrefix}${relPath}`;
};

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
