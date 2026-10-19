import { AIRBNB_URLS, IMAGES } from "./config";

export interface PropertyTheme {
	accentColor: string;
	accentBg: string;
	textColor: string;
	headingColor: string;
	borderColor: string;
	borderFocusColor: string;
	bgHover: string;

	// Centralized images specific to the property
	images: {
		hero: string;
		nomad?: string;
		romantic?: string;
		bedroom: string;
		pool: string;
	};

	// Airbnb target URL
	airbnbUrl: string;

	// Centralized key for bed capacity localization
	bedTextKey: "statsBeds" | "stats1606Beds";

	// AboutAmenities centralized styles
	aboutThemeAccent: string;
	aboutHostAccent: string;
	aboutHostBorder: string;
	aboutHostBg: string;
	aboutHostName: string;

	// BookingSection centralized styles
	bookingSectionBg: string;
	bookingCardBorder: string;
	bookingLeftColBg: string;
	bookingInputContainerBorder: string;
	bookingInputLabel: string;
	bookingInputValSelected: string;
	bookingInputValPlaceholder: string;
	bookingBtn: string;
	bookingFormInput: string;
	bookingCaptchaBoxBg: string;
	bookingConsentBoxBg: string;
	bookingConsentText: string;
	bookingConsentLink: string;

	// CalendarWidget centralized styles
	calendarDayText: string;
	calendarDayHoverBg: string;
	calendarDaySelected: string;
	calendarDayInRange: string;
	calendarDayNamesHeader: string;

	// Hero centralized styles
	heroOverlayGradient: string;
	heroSubtitleColor: string;
	heroBtnTxtColor: string;
	heroIconColor: string;

	// Privacy page centralized styles
	privacyPageWrapper: string;
	privacyHeroOverlay: string;
	privacyHeroBadge: string;
	privacyVersionPill: string;
	privacySectionCard: string;
	privacySubCard: string;
	privacyControllerIconBox: string;
	privacyLegalIconBox: string;
	privacyPurposesIconBox: string;
	privacyMinorsIconBox: string;
	privacyMinorsCallout: string;
	privacyTransmissionsIconBox: string;
	privacyArcoIconBox: string;
	privacyConsultasCard: string;
	privacyReclamosCard: string;
	privacyChannelBanner: string;
	privacyChannelCtaBtn: string;
	privacySecurityIconBox: string;
	privacyRetentionCallout: string;
}

export const PRIVACY_THEME_STYLES = {
	privacyPageWrapper:
		"min-h-screen flex flex-col bg-slate-50 font-sans text-slate-800 scroll-smooth",
	privacyHeroOverlay: "absolute inset-0 bg-slate-900/75 backdrop-blur-[2px]",
	privacyHeroBadge:
		"inline-flex items-center space-x-2 px-4 py-1.5 rounded-full text-xs font-bold bg-emerald-500/15 text-emerald-300 border border-emerald-500/30 mb-4 tracking-wider uppercase backdrop-blur-md",
	privacyVersionPill:
		"mt-6 inline-flex items-center space-x-3 bg-white/10 border border-white/15 rounded-full px-5 py-2 text-xs text-slate-200 font-mono",
	privacySectionCard:
		"bg-white rounded-3xl p-8 md:p-10 shadow-md border border-slate-100",
	privacySubCard: "p-5 rounded-2xl bg-slate-50 border border-slate-100",
	privacyControllerIconBox: "p-3 bg-[#FF5A5F]/10 text-[#FF5A5F] rounded-2xl",
	privacyLegalIconBox: "p-3 bg-indigo-50 text-indigo-600 rounded-2xl",
	privacyPurposesIconBox: "p-3 bg-amber-50 text-amber-600 rounded-2xl",
	privacyMinorsIconBox: "p-3 bg-rose-50 text-rose-600 rounded-2xl",
	privacyMinorsCallout:
		"p-4 rounded-2xl bg-amber-50 border border-amber-200 text-amber-900 text-sm font-medium",
	privacyTransmissionsIconBox: "p-3 bg-sky-50 text-sky-600 rounded-2xl",
	privacyArcoIconBox: "p-3 bg-emerald-50 text-emerald-600 rounded-2xl",
	privacyConsultasCard:
		"p-6 rounded-2xl bg-emerald-50/60 border border-emerald-200",
	privacyReclamosCard:
		"p-6 rounded-2xl bg-indigo-50/60 border border-indigo-200",
	privacyChannelBanner:
		"p-5 rounded-2xl bg-slate-900 text-white flex flex-col md:flex-row md:items-center md:justify-between gap-4 text-sm",
	privacyChannelCtaBtn:
		"inline-flex items-center justify-center bg-[#FF5A5F] hover:bg-[#FF424D] text-white px-5 py-2.5 rounded-xl font-bold text-xs transition-colors shrink-0",
	privacySecurityIconBox: "p-3 bg-purple-50 text-purple-600 rounded-2xl",
	privacyRetentionCallout:
		"p-4 rounded-2xl bg-slate-50 border border-slate-200 text-slate-700 text-sm",
} as const;

export const THEMES: Record<"1707" | "1606", PropertyTheme> = {
	"1707": {
		accentColor: "#FF5A5F",
		accentBg: "bg-red-50",
		textColor: "text-slate-800",
		headingColor: "text-slate-900",
		borderColor: "border-slate-100",
		borderFocusColor: "focus:border-slate-400",
		bgHover: "hover:bg-slate-100",

		// Centralized images
		images: {
			hero: IMAGES["1707"].hero,
			nomad: IMAGES["1707"].nomad,
			bedroom: IMAGES["1707"].bedroom,
			pool: IMAGES["1707"].pool,
		},

		// Airbnb target URL
		airbnbUrl: AIRBNB_URLS["1707"],

		// Stats bed capacity key
		bedTextKey: "statsBeds",

		// AboutAmenities
		aboutThemeAccent: "text-[#FF5A5F] bg-red-50",
		aboutHostAccent: "text-[#FF5A5F]",
		aboutHostBorder: "border-slate-700",
		aboutHostBg: "bg-slate-900 font-sans",
		aboutHostName: "text-white",

		// BookingSection
		bookingSectionBg: "bg-slate-100 border-t border-slate-200",
		bookingCardBorder: "border border-slate-200",
		bookingLeftColBg:
			"border-b md:border-b-0 md:border-r border-slate-200 bg-slate-50/50",
		bookingInputContainerBorder: "border-slate-200",
		bookingInputLabel: "text-slate-400",
		bookingInputValSelected: "text-slate-900",
		bookingInputValPlaceholder: "text-slate-300",
		bookingBtn: "bg-[#FF5A5F] hover:bg-[#FF424D]",
		bookingFormInput: "border-slate-200 text-slate-800 focus:border-[#FF5A5F]",
		bookingCaptchaBoxBg: "bg-slate-50 border border-slate-200",
		bookingConsentBoxBg: "bg-slate-50 border border-slate-200",
		bookingConsentText: "text-slate-600",
		bookingConsentLink: "text-[#FF5A5F] hover:text-[#FF424D]",

		// CalendarWidget
		calendarDayText: "text-slate-700",
		calendarDayHoverBg: "hover:bg-slate-100",
		calendarDaySelected: "bg-[#FF5A5F] text-white",
		calendarDayInRange: "bg-[#FF5A5F]/10 text-[#FF5A5F]",
		calendarDayNamesHeader: "text-slate-400",

		// Hero
		heroOverlayGradient:
			"bg-gradient-to-b from-black/60 via-black/30 to-slate-900/80",
		heroSubtitleColor: "text-slate-200",
		heroBtnTxtColor: "text-slate-900",
		heroIconColor: "text-[#FF5A5F]",
		...PRIVACY_THEME_STYLES,
	},
	"1606": {
		accentColor: "rose-600",
		accentBg: "bg-rose-50",
		textColor: "text-stone-800",
		headingColor: "text-stone-900",
		borderColor: "border-stone-100",
		borderFocusColor: "focus:border-stone-400",
		bgHover: "hover:bg-stone-100",

		// Centralized images
		images: {
			hero: IMAGES["1606"].hero,
			romantic: IMAGES["1606"].romantic,
			bedroom: IMAGES["1606"].bedroom,
			pool: IMAGES["1606"].pool,
		},

		// Airbnb target URL
		airbnbUrl: AIRBNB_URLS["1606"],

		// Stats bed capacity key
		bedTextKey: "stats1606Beds",

		// AboutAmenities
		aboutThemeAccent: "text-rose-600 bg-rose-50",
		aboutHostAccent: "text-rose-400",
		aboutHostBorder: "border-stone-700",
		aboutHostBg: "bg-stone-900 font-sans",
		aboutHostName: "text-stone-50",

		// BookingSection
		bookingSectionBg: "bg-stone-100 border-t border-stone-200",
		bookingCardBorder: "border border-stone-200",
		bookingLeftColBg:
			"border-b md:border-b-0 md:border-r border-stone-200 bg-stone-50/50",
		bookingInputContainerBorder: "border-stone-200",
		bookingInputLabel: "text-stone-400",
		bookingInputValSelected: "text-stone-900",
		bookingInputValPlaceholder: "text-stone-300",
		bookingBtn: "bg-rose-600 hover:bg-rose-700",
		bookingFormInput: "border-stone-200 text-stone-800 focus:border-rose-600",
		bookingCaptchaBoxBg: "bg-stone-50 border border-stone-200",
		bookingConsentBoxBg: "bg-stone-50 border border-stone-200",
		bookingConsentText: "text-stone-600",
		bookingConsentLink: "text-rose-600 hover:text-rose-700",

		// CalendarWidget
		calendarDayText: "text-stone-700",
		calendarDayHoverBg: "hover:bg-stone-100",
		calendarDaySelected: "bg-rose-600 text-white",
		calendarDayInRange: "bg-rose-600/10 text-rose-600",
		calendarDayNamesHeader: "text-stone-400",

		// Hero
		heroOverlayGradient:
			"bg-gradient-to-b from-black/60 via-amber-900/30 to-stone-900/80",
		heroSubtitleColor: "text-stone-200",
		heroBtnTxtColor: "text-stone-900",
		heroIconColor: "text-rose-600",
		...PRIVACY_THEME_STYLES,
	},
};
