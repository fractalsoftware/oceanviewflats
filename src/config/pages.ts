import { IMAGES, MINOR_DOC_TYPES } from "../constants/config";
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

export const BASE_URL = "https://www.oceanviewflats.com";
export const LANGUAGES: Lang[] = ["en", "es", "fr", "it", "de", "ja"];

export const getHrefLangTags = (
	baseUrl: string,
	path: string,
	languages: Lang[],
) => {
	const basePath = path ? `${baseUrl}/${path}` : baseUrl;
	return (
		languages
			.map((l) => {
				const url = l === "en" ? basePath : `${basePath}/${l}.html`;
				return `    <link rel="alternate" hreflang="${l}" href="${url}" />`;
			})
			.join("\n") +
		`\n    <link rel="alternate" hreflang="x-default" href="${basePath}" />`
	);
};

export interface PageConfig {
	id: string;
	path: string;
	component: any; // React Component
	filename: (lang: Lang) => string;
	getStructuredData: (t: any, lang: Lang, baseUrl: string) => string;
	scripts: string[];
	seoTitle?: (t: any) => string;
	seoDescription?: (t: any) => string;
	ogImage?: string;
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
		ogImage: IMAGES["1707"].hero,
		getStructuredData: (t: any, lang: Lang, baseUrl: string) => {
			const url = lang === "en" ? baseUrl : `${baseUrl}/${lang}.html`;
			return JSON.stringify({
				"@context": "https://schema.org",
				"@graph": [
					{
						"@type": "WebSite",
						"@id": `${url}#website`,
						url: url,
						name: "OceanViewFlats",
						description:
							"Premium beachfront vacation rentals in Santa Marta, Colombia.",
					},
					{
						"@type": "LodgingBusiness",
						"@id": `${url}#business`,
						name: "OceanViewFlats",
						legalName: "OceanViewFlats Santa Marta",
						license: "Registro Nacional de Turismo RNT #268781 & RNT #270413",
						description:
							"Direct beachfront rental apartments in Playa Salguero, Santa Marta.",
						url: url,
						telephone: "+573108155234",
						email: "rentals@oceanviewflats.com",
						address: {
							"@type": "PostalAddress",
							streetAddress: "Calle 26 # 2-80. Playa Salguero",
							addressLocality: "Santa Marta",
							addressRegion: "Magdalena",
							postalCode: "470006",
							addressCountry: "CO",
						},
						geo: {
							"@type": "GeoCoordinates",
							latitude: 11.1876481,
							longitude: -74.2313921,
						},
						priceRange: "$110 - $165 USD",
					},
					{
						"@type": "FAQPage",
						"@id": `${url}#faq`,
						mainEntity: [
							{
								"@type": "Question",
								name: t.faqQ1,
								acceptedAnswer: {
									"@type": "Answer",
									text: t.faqA1,
								},
							},
							{
								"@type": "Question",
								name: t.faqQ2,
								acceptedAnswer: {
									"@type": "Answer",
									text: t.faqA2,
								},
							},
							{
								"@type": "Question",
								name: t.faqQ3,
								acceptedAnswer: {
									"@type": "Answer",
									text: t.faqA3,
								},
							},
							{
								"@type": "Question",
								name: t.faqQ4,
								acceptedAnswer: {
									"@type": "Answer",
									text: t.faqA4,
								},
							},
							{
								"@type": "Question",
								name: t.faqQ5,
								acceptedAnswer: {
									"@type": "Answer",
									text: t.faqA5,
								},
							},
						],
					},
				],
			});
		},
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
		ogImage: IMAGES["1707"].hero,
		getStructuredData: (t: any, lang: Lang, baseUrl: string) => {
			const url =
				lang === "en"
					? `${baseUrl}/Oceanview1707`
					: `${baseUrl}/Oceanview1707/${lang}.html`;
			return JSON.stringify({
				"@context": "https://schema.org",
				"@type": "VacationRental",
				identifier: "1500108514798091235",
				name: "OceanViewFlats 1707",
				description: t.seoDescription,
				provider: {
					"@type": "LodgingBusiness",
					name: "OceanViewFlats",
					url: baseUrl,
					telephone: "+573108155234",
				},
				hasMap: "https://maps.google.com/?q=11.1876481,-74.2313921",
				numberOfBedrooms: 2,
				numberOfBathroomsTotal: 2,
				image: [
					`${baseUrl}/${IMAGES["1707"].hero}`,
					`${baseUrl}/${IMAGES["1707"].nomad}`,
					`${baseUrl}/${IMAGES["1707"].bedroom}`,
					`${baseUrl}/${IMAGES["1707"].pool}`,
					`${baseUrl}/${IMAGES["1606"].hero}`,
					`${baseUrl}/${IMAGES["1606"].romantic}`,
					`${baseUrl}/${IMAGES["1606"].bedroom}`,
					`${baseUrl}/${IMAGES["1606"].pool}`,
				],
				url: url,
				address: {
					"@type": "PostalAddress",
					streetAddress: "Calle 26 # 2-80. Playa Salguero",
					addressLocality: "Santa Marta",
					addressRegion: "Magdalena",
					postalCode: "470006",
					addressCountry: "CO",
				},
				geo: {
					"@type": "GeoCoordinates",
					latitude: 11.1876481,
					longitude: -74.2313921,
				},
				containsPlace: [
					{
						"@type": "Room",
						name: "Master Bedroom",
						additionalType: "http://schema.googleapis.com/BedRoom",
						occupancy: { "@type": "QuantitativeValue", value: 2 },
						numberOfBedrooms: 1,
						numberOfBathroomsTotal: 1,
						bed: [
							{ "@type": "BedDetails", typeOfBed: "Queen", numberOfBeds: 1 },
						],
						amenityFeature: [
							{
								"@type": "LocationFeatureSpecification",
								name: "Air conditioning",
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
						name: "Guest Bedroom",
						additionalType: "http://schema.googleapis.com/BedRoom",
						occupancy: { "@type": "QuantitativeValue", value: 4 },
						numberOfBedrooms: 1,
						numberOfBathroomsTotal: 1,
						bed: [
							{ "@type": "BedDetails", typeOfBed: "Double", numberOfBeds: 2 },
						],
						amenityFeature: [
							{
								"@type": "LocationFeatureSpecification",
								name: "Air conditioning",
								value: true,
							},
						],
					},
				],
				numberOfRooms: 2,
				occupancy: {
					"@type": "QuantitativeValue",
					value: 6,
				},
				offers: {
					"@type": "AggregateOffer",
					priceCurrency: "USD",
					lowPrice: "120",
					highPrice: "165",
					offerCount: "1",
					priceRange: "$120 - $165 USD per night",
					offers: {
						"@type": "Offer",
						price: "120",
						priceCurrency: "USD",
						availability: "https://schema.org/InStock",
						validFrom: "2026-05-25",
					},
				},
				petsAllowed: false,
				amenityFeature: [
					{
						"@type": "LocationFeatureSpecification",
						name: t.amWifi,
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
					},
				],
				aggregateRating: {
					"@type": "AggregateRating",
					ratingValue: "5.0",
					reviewCount: "1",
				},
				review: [
					{
						"@type": "Review",
						author: { "@type": "Person", name: t.review1707Author },
						datePublished: "2026-06-28",
						reviewRating: { "@type": "Rating", ratingValue: "5" },
						reviewBody: t.review1707Text,
					},
				],
			});
		},
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
		ogImage: IMAGES["1606"].hero,
		getStructuredData: (t: any, lang: Lang, baseUrl: string) => {
			const url =
				lang === "en"
					? `${baseUrl}/Oceanview1606`
					: `${baseUrl}/Oceanview1606/${lang}.html`;
			return JSON.stringify({
				"@context": "https://schema.org",
				"@type": "VacationRental",
				identifier: "1584825560087571592",
				name: "OceanViewFlats 1606",
				description: t.seo1606Description,
				provider: {
					"@type": "LodgingBusiness",
					name: "OceanViewFlats",
					url: baseUrl,
					telephone: "+573108155234",
				},
				hasMap: "https://maps.google.com/?q=11.1876481,-74.2313921",
				numberOfBedrooms: 2,
				numberOfBathroomsTotal: 2,
				image: [
					`${baseUrl}/${IMAGES["1606"].hero}`,
					`${baseUrl}/${IMAGES["1606"].romantic}`,
					`${baseUrl}/${IMAGES["1606"].bedroom}`,
					`${baseUrl}/${IMAGES["1606"].pool}`,
					`${baseUrl}/${IMAGES["1707"].hero}`,
					`${baseUrl}/${IMAGES["1707"].nomad}`,
					`${baseUrl}/${IMAGES["1707"].bedroom}`,
					`${baseUrl}/${IMAGES["1707"].pool}`,
				],
				url: url,
				address: {
					"@type": "PostalAddress",
					streetAddress: "Calle 26 # 2-80. Playa Salguero",
					addressLocality: "Santa Marta",
					addressRegion: "Magdalena",
					postalCode: "470006",
					addressCountry: "CO",
				},
				geo: {
					"@type": "GeoCoordinates",
					latitude: 11.1876481,
					longitude: -74.2313921,
				},
				containsPlace: [
					{
						"@type": "Room",
						name: "Master Bedroom",
						additionalType: "http://schema.googleapis.com/BedRoom",
						occupancy: { "@type": "QuantitativeValue", value: 2 },
						numberOfBedrooms: 1,
						numberOfBathroomsTotal: 1,
						bed: [
							{ "@type": "BedDetails", typeOfBed: "Queen", numberOfBeds: 1 },
						],
						amenityFeature: [
							{
								"@type": "LocationFeatureSpecification",
								name: "Air conditioning",
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
						name: "Guest Bedroom",
						additionalType: "http://schema.googleapis.com/BedRoom",
						occupancy: { "@type": "QuantitativeValue", value: 4 },
						numberOfBedrooms: 1,
						numberOfBathroomsTotal: 1,
						bed: [
							{ "@type": "BedDetails", typeOfBed: "Double", numberOfBeds: 2 },
						],
						amenityFeature: [
							{
								"@type": "LocationFeatureSpecification",
								name: "Air conditioning",
								value: true,
							},
						],
					},
				],
				numberOfRooms: 2,
				occupancy: {
					"@type": "QuantitativeValue",
					value: 6,
				},
				offers: {
					"@type": "AggregateOffer",
					priceCurrency: "USD",
					lowPrice: "110",
					highPrice: "160",
					offerCount: "1",
					priceRange: "$110 - $160 USD per night",
					offers: {
						"@type": "Offer",
						price: "110",
						priceCurrency: "USD",
						availability: "https://schema.org/InStock",
						validFrom: "2026-05-25",
					},
				},
				petsAllowed: false,
				amenityFeature: [
					{
						"@type": "LocationFeatureSpecification",
						name: t.amWifi,
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
					},
				],
				aggregateRating: {
					"@type": "AggregateRating",
					ratingValue: "5",
					reviewCount: "2",
				},
				review: [
					{
						"@type": "Review",
						author: { "@type": "Person", name: t.review1Author },
						datePublished: "2026-04-02",
						reviewRating: { "@type": "Rating", ratingValue: "5" },
						reviewBody: t.review1Text,
					},
					{
						"@type": "Review",
						author: { "@type": "Person", name: t.review2Author },
						datePublished: "2026-03-20",
						reviewRating: { "@type": "Rating", ratingValue: "5" },
						reviewBody: t.review2Text,
					},
				],
			});
		},
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
		ogImage: IMAGES["1707"].hero,
		getStructuredData: (t: any, lang: Lang, baseUrl: string) => {
			const url =
				lang === "en"
					? `${baseUrl}/contact`
					: `${baseUrl}/contact/${lang}.html`;
			return JSON.stringify({
				"@context": "https://schema.org",
				"@type": "ContactPage",
				name: "Contact OceanViewFlats",
				description: t.seoContactDescription,
				url: url,
			});
		},
		scripts: ["js/main.js"],
	},
	{
		id: "registry",
		path: "registry",
		component: Registry,
		filename: (lang: Lang) =>
			lang === "en" ? "registry/index.html" : `registry/${lang}.html`,
		seoTitle: (t: any) => t.registryTitle,
		seoDescription: (t: any) => t.registrySubtitle,
		ogImage: IMAGES["1707"].hero,
		getStructuredData: (t: any, lang: Lang, baseUrl: string) => {
			const url =
				lang === "en"
					? `${baseUrl}/registry`
					: `${baseUrl}/registry/${lang}.html`;
			return JSON.stringify({
				"@context": "https://schema.org",
				"@type": "WebPage",
				name: t.registryTitle,
				description: t.registrySubtitle,
				url: url,
			});
		},
		scripts: ["js/main.js", "js/registry.js"],
	},
	{
		id: "guide",
		path: "guide",
		component: Guide,
		filename: (lang: Lang) =>
			lang === "en" ? "guide/index.html" : `guide/${lang}.html`,
		seoTitle: (t: any) => t.guideTitle,
		seoDescription: (t: any) => t.guideHeader,
		ogImage: IMAGES["1707"].hero,
		getStructuredData: (t: any, lang: Lang, baseUrl: string) => {
			const url =
				lang === "en" ? `${baseUrl}/guide` : `${baseUrl}/guide/${lang}.html`;
			return JSON.stringify({
				"@context": "https://schema.org",
				"@type": "WebPage",
				name: t.guideTitle,
				description: t.guideHeader,
				url: url,
			});
		},
		scripts: ["js/main.js", "js/guide.js"],
	},
	{
		id: "404",
		path: "404",
		component: NotFound,
		filename: (lang: Lang) => (lang === "en" ? "404.html" : `404/${lang}.html`),
		seoTitle: (t: any) => t.notFoundTitle,
		seoDescription: (t: any) => t.notFoundSubtitle,
		ogImage: IMAGES["1707"].hero,
		getStructuredData: (t: any, lang: Lang, baseUrl: string) => {
			const url =
				lang === "en" ? `${baseUrl}/404.html` : `${baseUrl}/404/${lang}.html`;
			return JSON.stringify({
				"@context": "https://schema.org",
				"@type": "WebPage",
				name: t.notFoundTitle,
				description: t.notFoundSubtitle,
				url: url,
			});
		},
		scripts: ["js/main.js"],
	},
	{
		id: "booking-success",
		path: "booking-success",
		component: BookingSuccess,
		filename: (lang: Lang) =>
			lang === "en"
				? "booking-success/index.html"
				: `booking-success/${lang}.html`,
		seoTitle: (t: any) => t.dbSuccessTitle,
		seoDescription: (t: any) => t.dbSuccessSubtitle,
		ogImage: IMAGES["1707"].hero,
		getStructuredData: (t: any, lang: Lang, baseUrl: string) => {
			const url =
				lang === "en"
					? `${baseUrl}/booking-success`
					: `${baseUrl}/booking-success/${lang}.html`;
			return JSON.stringify({
				"@context": "https://schema.org",
				"@type": "WebPage",
				name: t.dbSuccessTitle,
				description: t.dbSuccessSubtitle,
				url: url,
			});
		},
		scripts: ["js/main.js"],
	},
	{
		id: "booking-failure",
		path: "booking-failure",
		component: BookingFailure,
		filename: (lang: Lang) =>
			lang === "en"
				? "booking-failure/index.html"
				: `booking-failure/${lang}.html`,
		seoTitle: (t: any) => t.dbFailureTitle,
		seoDescription: (t: any) => t.dbFailureSubtitle,
		ogImage: IMAGES["1707"].hero,
		getStructuredData: (t: any, lang: Lang, baseUrl: string) => {
			const url =
				lang === "en"
					? `${baseUrl}/booking-failure`
					: `${baseUrl}/booking-failure/${lang}.html`;
			return JSON.stringify({
				"@context": "https://schema.org",
				"@type": "WebPage",
				name: t.dbFailureTitle,
				description: t.dbFailureSubtitle,
				url: url,
			});
		},
		scripts: ["js/main.js"],
	},
	{
		id: "booking-pending",
		path: "booking-pending",
		component: BookingPending,
		filename: (lang: Lang) =>
			lang === "en"
				? "booking-pending/index.html"
				: `booking-pending/${lang}.html`,
		seoTitle: (t: any) => t.dbPendingTitle,
		seoDescription: (t: any) => t.dbPendingSubtitle,
		ogImage: IMAGES["1707"].hero,
		getStructuredData: (t: any, lang: Lang, baseUrl: string) => {
			const url =
				lang === "en"
					? `${baseUrl}/booking-pending`
					: `${baseUrl}/booking-pending/${lang}.html`;
			return JSON.stringify({
				"@context": "https://schema.org",
				"@type": "WebPage",
				name: t.dbPendingTitle,
				description: t.dbPendingSubtitle,
				url: url,
			});
		},
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
		ogImage: IMAGES["1707"].hero,
		getStructuredData: (t: any, lang: Lang, baseUrl: string) => {
			const url =
				lang === "en"
					? `${baseUrl}/privacy`
					: `${baseUrl}/privacy/${lang}.html`;
			return JSON.stringify({
				"@context": "https://schema.org",
				"@type": "WebPage",
				name: t.privacySeoTitle,
				description: t.privacySeoDescription,
				url: url,
			});
		},
		scripts: ["js/main.js"],
	},
];
