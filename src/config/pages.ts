import {
	getCanonicalUrl,
	IMAGE_DIMENSIONS,
	IMAGES,
	LANGUAGES,
	MINOR_DOC_TYPES,
} from "../constants/config";
import type { TranslationDict } from "../i18n/dict";
import BookingFailure from "../pages/BookingFailure";
import BookingPending from "../pages/BookingPending";
import BookingSuccess from "../pages/BookingSuccess";
import Contact from "../pages/Contact";
import Guide from "../pages/Guide";
import Home from "../pages/Home";
import NotFound from "../pages/NotFound";
import Oceanview1606 from "../pages/Oceanview1606";
import Oceanview1707 from "../pages/Oceanview1707";
import Privacy from "../pages/Privacy";
import Registry from "../pages/Registry";
import type { Lang } from "../types";
import {
	buildContactStructuredData,
	buildHomeStructuredData,
	buildPrivacyStructuredData,
	buildPropertyStructuredData,
	buildWebPageStructuredData,
	type PortfolioPricingSummary,
} from "../utils/schema";

export const BASE_URL = "https://www.oceanviewflats.com";
export { getCanonicalUrl, LANGUAGES };

export const getHrefLangTags = (
	baseUrl: string,
	path: string,
	languages: readonly Lang[],
) => {
	const defaultUrl = getCanonicalUrl(baseUrl, path, "en");
	return (
		languages
			.map((l) => {
				const url = getCanonicalUrl(baseUrl, path, l);
				return `    <link rel="alternate" hreflang="${l}" href="${url}" />`;
			})
			.join("\n") +
		`\n    <link rel="alternate" hreflang="x-default" href="${defaultUrl}" />`
	);
};

export interface PageOgImageConfig {
	path: string;
	width: number;
	height: number;
	getAlt: (t: TranslationDict) => string;
}

export interface PageConfig {
	id: string;
	path: string;
	component: any; // React Component
	filename: (lang: Lang) => string;
	getStructuredData: (
		t: TranslationDict,
		lang: Lang,
		baseUrl: string,
		pricingSummary?: PortfolioPricingSummary,
	) => string;
	scripts: string[];
	seoTitle?: (t: any) => string;
	seoDescription?: (t: any) => string;
	ogImage: PageOgImageConfig;
	heroImage?: string;
	noIndex?: boolean;
	clientI18n?: (t: TranslationDict) => Record<string, string>;
}

export const getClientDictionaryForPage = (
	pageId: string,
	t: TranslationDict,
): Record<string, string> => {
	const common: Record<string, string> = {
		dbVerifying: t.dbVerifying || "",
		dbSecured: t.dbSecured || "",
		dbFillFields: t.dbFillFields || "",
		dbErrPrivacyConsent: t.dbErrPrivacyConsent || "",
		dbDeclined: t.dbDeclined || "",
		dbSubmit: t.dbSubmit || "",
		dbNetworkError: t.dbNetworkError || "",
		dbGatewayError: t.dbGatewayError || "",
		contactSuccess: t.contactSuccess || "",
		contactError: t.contactError || "",
		contactErrPrivacyConsent: t.contactErrPrivacyConsent || "",
		contactSubmitting: t.contactSubmitting || "",
		contactSubmit: t.contactSubmit || "",
		contactDateError: t.contactDateError || "",
	};

	if (pageId === "home") {
		return {
			...common,
			homeCardRateFormat: t.homeCardRateFormat || "",
		};
	}

	if (
		pageId === "Oceanview1707" ||
		pageId === "Oceanview1606" ||
		pageId === "oceanview1707" ||
		pageId === "oceanview1606"
	) {
		return {
			...common,
			dbMinStayNotice: t.dbMinStayNotice || "",
			dbNightsFormat: t.dbNightsFormat || "",
		};
	}

	if (pageId === "registry") {
		return {
			...common,
			registryTitle: t.registryTitle || "",
			registrySubtitle: t.registrySubtitle || "",
			registryLookupLoading: t.registryLookupLoading || "",
			registryLookupNotFound: t.registryLookupNotFound || "",
			registryAlreadyCompleted: t.registryAlreadyCompleted || "",
			registryConcluded: t.registryConcluded || "",
			registrySubmitting: t.registrySubmitting || "",
			registryGenericError: t.registryGenericError || "",
			registryNotSpecified: t.registryNotSpecified || "",
			registryDefaultProperty: t.registryDefaultProperty || "",
			registryErrEmail: t.registryErrEmail || "",
			registryErrPhoneRequired: t.registryErrPhoneRequired || "",
			registryErrPrivacyConsent: t.registryErrPrivacyConsent || "",
			registryErrMinorConsent: t.registryErrMinorConsent || "",
			registrySuccess: t.registrySuccess || "",
			registryWarmMsg: t.registryWarmMsg || "",
			registrySubmit: t.registrySubmit || "",
			err_guest_email: t.err_guest_email || "",
			errEmailInvalid: t.errEmailInvalid || "",
		};
	}

	if (pageId === "guide") {
		return {
			...common,
			guideCredentialsLocked: t.guideCredentialsLocked || "",
			guideCredentialsLockedDesc: t.guideCredentialsLockedDesc || "",
			guideActionUnlock: t.guideActionUnlock || "",
			guideCredentialsVerifying: t.guideCredentialsVerifying || "",
			guideNoReservationFound: t.guideNoReservationFound || "",
			guideCopySuccess: t.guideCopySuccess || "",
			guideConcluded: t.guideConcluded || "",
			guideConcludedBadge: t.guideConcludedBadge || "",
			guideParkingLockedNotice: t.guideParkingLockedNotice || "",
			guideParkingPlaceholder: t.guideParkingPlaceholder || "",
			guideWelcomeWithGuest: t.guideWelcomeWithGuest || "",
			guideWelcomeGeneric: t.guideWelcomeGeneric || "",
		};
	}

	if (pageId === "booking-success" || pageId === "booking-pending") {
		return {
			...common,
			guideCopySuccess: t.guideCopySuccess || "",
		};
	}

	return common;
};

export const getClientExtraConfigForPage = (
	pageId: string,
): Record<string, unknown> => {
	if (pageId === "registry") {
		return {
			minorDocTypes: [...MINOR_DOC_TYPES],
		};
	}

	return {};
};

export const pages: PageConfig[] = [
	{
		id: "home",
		path: "",
		component: Home,
		filename: (lang: Lang) => (lang === "en" ? "index.html" : `${lang}.html`),
		seoTitle: (t: any) => t.seoHomeTitle,
		seoDescription: (t: any) => t.seoHomeDescription,
		ogImage: {
			path: IMAGES.home.hero,
			...IMAGE_DIMENSIONS.homeHero,
			getAlt: (t: TranslationDict) => t.ogHomeImageAlt,
		},
		heroImage: IMAGES.home.hero,
		getStructuredData: (t, lang, baseUrl, pricingSummary) =>
			buildHomeStructuredData(t, lang, baseUrl, pricingSummary),
		scripts: ["js/main.js"],
	},
	{
		id: "Oceanview1707",
		path: "Oceanview1707",
		component: Oceanview1707,
		filename: (lang: Lang) =>
			lang === "en" ? "Oceanview1707/index.html" : `Oceanview1707/${lang}.html`,
		seoTitle: (t: any) => t.seoTitle,
		seoDescription: (t: any) => t.seoDescription,
		ogImage: {
			path: IMAGES["1707"].hero,
			...IMAGE_DIMENSIONS["1707"].hero,
			getAlt: (t: TranslationDict) => t.og1707ImageAlt,
		},
		heroImage: IMAGES["1707"].hero,
		getStructuredData: (t, lang, baseUrl, pricingSummary) =>
			buildPropertyStructuredData("1707", t, lang, baseUrl, pricingSummary),
		scripts: ["js/main.js"],
	},
	{
		id: "Oceanview1606",
		path: "Oceanview1606",
		component: Oceanview1606,
		filename: (lang: Lang) =>
			lang === "en" ? "Oceanview1606/index.html" : `Oceanview1606/${lang}.html`,
		seoTitle: (t: any) => t.seo1606Title,
		seoDescription: (t: any) => t.seo1606Description,
		ogImage: {
			path: IMAGES["1606"].hero,
			...IMAGE_DIMENSIONS["1606"].hero,
			getAlt: (t: TranslationDict) => t.og1606ImageAlt,
		},
		heroImage: IMAGES["1606"].hero,
		getStructuredData: (t, lang, baseUrl, pricingSummary) =>
			buildPropertyStructuredData("1606", t, lang, baseUrl, pricingSummary),
		scripts: ["js/main.js"],
	},
	{
		id: "contact",
		path: "contact",
		component: Contact,
		filename: (lang: Lang) =>
			lang === "en" ? "contact/index.html" : `contact/${lang}.html`,
		seoTitle: (t: any) => t.seoContactTitle,
		seoDescription: (t: any) => t.seoContactDescription,
		ogImage: {
			path: IMAGES.mainEntrance,
			...IMAGE_DIMENSIONS.mainEntrance,
			getAlt: (t: TranslationDict) => t.ogContactImageAlt,
		},
		heroImage: IMAGES.mainEntrance,
		getStructuredData: (t, lang, baseUrl) =>
			buildContactStructuredData(t, lang, baseUrl),
		scripts: ["js/main.js"],
	},
	{
		id: "registry",
		path: "registry",
		noIndex: true,
		component: Registry,
		filename: (lang: Lang) =>
			lang === "en" ? "registry/index.html" : `registry/${lang}.html`,
		seoTitle: (t: any) => t.registryTitle,
		seoDescription: (t: any) => t.registrySubtitle,
		ogImage: {
			path: IMAGES.mainEntrance,
			...IMAGE_DIMENSIONS.mainEntrance,
			getAlt: (t: TranslationDict) => t.ogEntranceImageAlt,
		},
		heroImage: IMAGES.mainEntrance,
		getStructuredData: (t, lang, baseUrl) =>
			buildWebPageStructuredData(
				"registry",
				t.registryTitle,
				t.registrySubtitle,
				lang,
				baseUrl,
			),
		scripts: ["js/main.js", "js/registry.js"],
	},
	{
		id: "guide",
		path: "guide",
		noIndex: true,
		component: Guide,
		filename: (lang: Lang) =>
			lang === "en" ? "guide/index.html" : `guide/${lang}.html`,
		seoTitle: (t: any) => t.guideTitle,
		seoDescription: (t: any) => t.guideHeader,
		ogImage: {
			path: IMAGES.mainEntrance,
			...IMAGE_DIMENSIONS.mainEntrance,
			getAlt: (t: TranslationDict) => t.ogEntranceImageAlt,
		},
		heroImage: IMAGES.mainEntrance,
		getStructuredData: (t, lang, baseUrl) =>
			buildWebPageStructuredData(
				"guide",
				t.guideTitle,
				t.guideHeader,
				lang,
				baseUrl,
			),
		scripts: ["js/main.js", "js/guide.js"],
	},
	{
		id: "404",
		path: "404",
		noIndex: true,
		component: NotFound,
		filename: (lang: Lang) => (lang === "en" ? "404.html" : `404/${lang}.html`),
		seoTitle: (t: any) => t.notFoundTitle,
		seoDescription: (t: any) => t.notFoundSubtitle,
		ogImage: {
			path: IMAGES.home.hero,
			...IMAGE_DIMENSIONS.homeHero,
			getAlt: (t: TranslationDict) => t.ogHomeImageAlt,
		},
		heroImage: IMAGES.home.hero,
		getStructuredData: (t, lang, baseUrl) =>
			buildWebPageStructuredData(
				"404",
				t.notFoundTitle,
				t.notFoundSubtitle,
				lang,
				baseUrl,
			),
		scripts: ["js/main.js"],
	},
	{
		id: "booking-success",
		path: "booking-success",
		noIndex: true,
		component: BookingSuccess,
		filename: (lang: Lang) =>
			lang === "en"
				? "booking-success/index.html"
				: `booking-success/${lang}.html`,
		seoTitle: (t: any) => t.dbSuccessTitle,
		seoDescription: (t: any) => t.dbSuccessSubtitle,
		ogImage: {
			path: IMAGES.home.hero,
			...IMAGE_DIMENSIONS.homeHero,
			getAlt: (t: TranslationDict) => t.ogHomeImageAlt,
		},
		heroImage: IMAGES.home.hero,
		getStructuredData: (t, lang, baseUrl) =>
			buildWebPageStructuredData(
				"booking-success",
				t.dbSuccessTitle,
				t.dbSuccessSubtitle,
				lang,
				baseUrl,
			),
		scripts: ["js/main.js"],
	},
	{
		id: "booking-failure",
		path: "booking-failure",
		noIndex: true,
		component: BookingFailure,
		filename: (lang: Lang) =>
			lang === "en"
				? "booking-failure/index.html"
				: `booking-failure/${lang}.html`,
		seoTitle: (t: any) => t.dbFailureTitle,
		seoDescription: (t: any) => t.dbFailureSubtitle,
		ogImage: {
			path: IMAGES.home.hero,
			...IMAGE_DIMENSIONS.homeHero,
			getAlt: (t: TranslationDict) => t.ogHomeImageAlt,
		},
		heroImage: IMAGES.home.hero,
		getStructuredData: (t, lang, baseUrl) =>
			buildWebPageStructuredData(
				"booking-failure",
				t.dbFailureTitle,
				t.dbFailureSubtitle,
				lang,
				baseUrl,
			),
		scripts: ["js/main.js"],
	},
	{
		id: "booking-pending",
		path: "booking-pending",
		noIndex: true,
		component: BookingPending,
		filename: (lang: Lang) =>
			lang === "en"
				? "booking-pending/index.html"
				: `booking-pending/${lang}.html`,
		seoTitle: (t: any) => t.dbPendingTitle,
		seoDescription: (t: any) => t.dbPendingSubtitle,
		ogImage: {
			path: IMAGES.home.hero,
			...IMAGE_DIMENSIONS.homeHero,
			getAlt: (t: TranslationDict) => t.ogHomeImageAlt,
		},
		heroImage: IMAGES.home.hero,
		getStructuredData: (t, lang, baseUrl) =>
			buildWebPageStructuredData(
				"booking-pending",
				t.dbPendingTitle,
				t.dbPendingSubtitle,
				lang,
				baseUrl,
			),
		scripts: ["js/main.js"],
	},
	{
		id: "privacy",
		path: "privacy",
		component: Privacy,
		filename: (lang: Lang) =>
			lang === "en" ? "privacy/index.html" : `privacy/${lang}.html`,
		seoTitle: (t: any) => t.privacySeoTitle,
		seoDescription: (t: any) => t.privacySeoDescription,
		ogImage: {
			path: IMAGES.mainEntrance,
			...IMAGE_DIMENSIONS.mainEntrance,
			getAlt: (t: TranslationDict) => t.ogPrivacyImageAlt,
		},
		heroImage: IMAGES.mainEntrance,
		getStructuredData: (t, lang, baseUrl) =>
			buildPrivacyStructuredData(t, lang, baseUrl),
		scripts: ["js/main.js"],
	},
];
