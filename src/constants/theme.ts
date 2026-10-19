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
}

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
	},
};
