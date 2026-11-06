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

export type ChromeScrollState = "top" | "scrolled";

export const getChromeScrollState = (isScrolled: boolean): ChromeScrollState =>
	isScrolled ? "scrolled" : "top";

export const CHROME_THEME = {
	nav: {
		top: "fixed w-full z-50 transition-all duration-300 bg-transparent py-5",
		scrolled:
			"fixed w-full z-50 transition-all duration-300 bg-white/90 backdrop-blur-md shadow-sm py-3",
	},
	navContainer: "max-w-6xl mx-auto px-6 flex justify-between items-center",
	navLogo: {
		top: "font-bold text-xl tracking-tight flex items-center space-x-2 transition-colors text-white hover:text-white/80",
		scrolled:
			"font-bold text-xl tracking-tight flex items-center space-x-2 transition-colors text-[#FF5A5F] hover:text-[#FF424D]",
	},
	navLogoIcon: "w-6 h-6 fill-current",
	navActions: "flex items-center space-x-6",
	navContact: {
		top: "inline-flex items-center justify-center min-h-[40px] min-w-[40px] px-2 text-sm font-semibold transition-colors text-white hover:text-white/80",
		scrolled:
			"inline-flex items-center justify-center min-h-[40px] min-w-[40px] px-2 text-sm font-semibold transition-colors text-slate-600 hover:text-rose-700",
	},
	navContactIcon: "w-5 h-5 md:hidden",
	navContactText: "hidden md:inline",
	navBookBtn: {
		top: "hidden md:inline-flex items-center justify-center min-h-[40px] px-3 py-2 text-sm font-semibold transition-colors text-white hover:text-white/80",
		scrolled:
			"hidden md:inline-flex items-center justify-center min-h-[40px] px-3 py-2 text-sm font-semibold transition-colors text-slate-600 hover:text-rose-700",
	},
	langGroup: "relative group",
	langToggle: {
		top: "inline-flex items-center justify-center space-x-1.5 min-h-[40px] min-w-[40px] px-3.5 py-2 rounded-full text-sm font-medium transition-colors cursor-pointer bg-white/20 hover:bg-white/30 text-white backdrop-blur-sm",
		scrolled:
			"inline-flex items-center justify-center space-x-1.5 min-h-[40px] min-w-[40px] px-3.5 py-2 rounded-full text-sm font-medium transition-colors cursor-pointer bg-slate-100 hover:bg-slate-200 text-slate-700",
	},
	langGlobeIcon: "w-4 h-4",
	langChevronIcon: "w-3 h-3 ml-1",
	langDropdownWrapper:
		"absolute right-0 top-full pt-2 w-36 opacity-0 invisible transition-all duration-200 z-50",
	langDropdownCard: "bg-white rounded-xl shadow-lg border border-slate-100",
	langDropdownList: "py-2",
	langDropdownItem:
		"block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 hover:text-rose-700 transition-colors",
	langDropdownActiveItem:
		"block px-4 py-2 text-sm font-semibold text-rose-700 bg-slate-50",
	footer:
		"bg-slate-900 text-slate-300 py-12 px-6 text-center border-t border-slate-800",
	footerContainer: "max-w-4xl mx-auto flex flex-col items-center",
	footerLogo: "w-8 h-8 fill-current text-slate-400 mb-4",
	footerHostText: "mb-2 font-medium text-slate-200",
	footerRntText: "text-xs tracking-wider text-slate-300 uppercase mb-2",
	footerPrivacyWrapper: "text-xs mb-4",
	footerPrivacyLink:
		"text-slate-300 hover:text-white underline transition-colors",
	footerCopyright: "text-sm text-slate-300 mb-6",
	footerBadge:
		"group inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-slate-800/80 border border-slate-700 hover:border-slate-500 text-xs font-mono font-semibold text-slate-300 hover:text-white shadow-sm transition-all duration-300 hover:-translate-y-0.5",
	footerBadgeDot: "w-1.5 h-1.5 rounded-full bg-slate-400",
	footerBadgeLogo:
		"w-3.5 h-3.5 fill-current text-slate-300 transition-transform duration-300 group-hover:scale-110",
	footerBadgeBrand: "text-white font-bold tracking-wide",
} as const;

const SHARED_HOME_CARD_THEME = {
	card: "group flex flex-col bg-white rounded-3xl shadow-md border border-slate-100 overflow-hidden hover:shadow-2xl hover:-translate-y-1 transition-all duration-300",
	cardMedia: "h-80 relative overflow-hidden bg-slate-900",
	cardImage:
		"w-full h-full object-cover group-hover:scale-105 transition-transform duration-700",
	cardOverlay:
		"absolute inset-0 bg-gradient-to-t from-slate-900/80 via-slate-900/25 to-transparent",
	cardBadge:
		"absolute top-5 left-5 inline-flex items-center px-3.5 py-1.5 rounded-full text-xs font-bold tracking-wider uppercase bg-slate-900/80 text-white border border-white/20 backdrop-blur-md",
	cardHeaderContent: "absolute bottom-6 left-6 right-6 text-white",
	cardLocation:
		"text-xs md:text-sm font-semibold tracking-wider uppercase mb-1 text-slate-200",
	cardTitle: "text-2xl md:text-3xl font-bold text-white",
	cardBody:
		"flex-grow flex flex-col justify-between p-6 md:p-8 bg-slate-50 border-t border-slate-100 group-hover:bg-white transition-colors",
	rateBadge:
		"inline-flex items-center px-3.5 py-1.5 rounded-full text-xs md:text-sm font-bold bg-slate-900 text-white mb-4",
	cardDesc: "text-slate-700 text-base leading-relaxed",
	cardSpecsList:
		"mt-6 flex flex-wrap items-center gap-2 text-xs md:text-sm font-semibold text-slate-700",
	cardSpecPill:
		"inline-flex items-center px-3 py-1 rounded-full bg-white border border-slate-200 text-slate-700",
	cardSpecHighlight:
		"inline-flex items-center px-3 py-1 rounded-full bg-white border border-slate-200 text-slate-700",
	cardCtaRow:
		"mt-6 pt-5 border-t border-slate-200/80 flex items-center justify-between text-base font-bold text-[#B91C1C] group-hover:text-slate-900 transition-colors",
	cardCtaIcon:
		"w-5 h-5 transition-transform duration-300 group-hover:translate-x-1 shrink-0",
} as const;

export const HOME_CARD_THEMES = {
	"1707": SHARED_HOME_CARD_THEME,
	"1606": SHARED_HOME_CARD_THEME,
} as const;

export const HOME_THEME = {
	pageWrapper:
		"min-h-screen flex flex-col bg-slate-50 font-sans text-slate-800 scroll-smooth",
	heroHeader:
		"relative bg-slate-900 pt-32 pb-28 md:pt-36 md:pb-32 px-6 text-center overflow-hidden",
	heroMediaWrapper: "absolute inset-0 w-full h-full",
	heroImage: "w-full h-full object-cover",
	heroOverlay:
		"absolute inset-0 bg-gradient-to-b from-slate-900/80 via-slate-900/55 to-slate-900/80",
	heroContent:
		"relative z-10 max-w-4xl mx-auto mt-4 flex flex-col items-center",
	heroLocationBadge:
		"inline-flex items-center space-x-2 bg-white/20 backdrop-blur-md px-4 py-1.5 rounded-full text-white text-xs md:text-sm font-semibold tracking-wider uppercase mb-6",
	heroLocationIcon: "w-4 h-4 shrink-0",
	heroTitle:
		"text-4xl md:text-6xl font-extrabold mb-4 text-white tracking-tight leading-tight drop-shadow-md",
	heroSubtitle:
		"text-lg md:text-2xl text-slate-200 font-light max-w-2xl mx-auto mb-6 drop-shadow",
	heroValueProp:
		"inline-flex items-center justify-center bg-slate-900/70 border border-white/20 backdrop-blur-md px-5 py-2 rounded-full text-xs md:text-sm font-semibold text-slate-100 tracking-wide mb-8",
	heroCtaBtn:
		"inline-flex items-center justify-center space-x-2.5 min-h-[48px] mb-8 md:mb-12 bg-white text-slate-900 hover:bg-slate-100 px-8 py-4 rounded-full font-bold text-base md:text-lg shadow-2xl transition-all duration-300 hover:scale-105",
	heroCtaIcon: "w-5 h-5 text-[#B91C1C] shrink-0",
	iconSm: "w-4 h-4 shrink-0",
	iconMd: "w-5 h-5 shrink-0",
	iconLg: "w-6 h-6",
	iconXl: "w-7 h-7",
	main: "flex-grow w-full",
	propertiesSection: "py-20 px-6 max-w-6xl mx-auto scroll-mt-20",
	propertiesHeading:
		"text-3xl md:text-4xl font-extrabold tracking-tight text-slate-900 text-center mb-12",
	propertiesGrid: "grid grid-cols-1 md:grid-cols-2 gap-10",
	multiPropertyBanner:
		"mt-10 bg-white rounded-3xl p-6 md:p-8 border border-slate-200 shadow-sm flex flex-col md:flex-row items-center justify-between gap-6",
	multiPropertyContent: "flex items-start gap-4",
	multiPropertyIconBox:
		"w-12 h-12 shrink-0 bg-red-50 text-[#B91C1C] rounded-2xl flex items-center justify-center",
	multiPropertyText: "text-sm md:text-base text-slate-700 leading-relaxed",
	multiPropertyCta:
		"shrink-0 inline-flex items-center justify-center space-x-2 min-h-[44px] bg-slate-900 hover:bg-slate-800 text-white px-6 py-3 rounded-full text-sm font-bold shadow-md transition-colors",
	cards: HOME_CARD_THEMES,
	card1707: HOME_CARD_THEMES["1707"],
	card1606: HOME_CARD_THEMES["1606"],
	directSection:
		"py-20 bg-gradient-to-b from-white to-slate-50 border-t border-b border-slate-200 px-6",
	directContainer: "max-w-6xl mx-auto",
	directHeader: "text-center max-w-3xl mx-auto mb-16",
	directTitle:
		"text-3xl md:text-4xl font-extrabold tracking-tight text-slate-900 mb-4",
	directSubtitle: "text-lg text-slate-700 font-light",
	directGrid: "grid grid-cols-1 md:grid-cols-4 gap-8",
	directCard:
		"bg-white p-8 rounded-3xl border border-slate-200 shadow-sm hover:shadow-xl transition-all duration-300",
	directIconEmerald:
		"w-12 h-12 bg-emerald-50 text-emerald-700 rounded-2xl flex items-center justify-center mb-6",
	directIconCoral:
		"w-12 h-12 bg-red-50 text-[#B91C1C] rounded-2xl flex items-center justify-center mb-6",
	directIconSky:
		"w-12 h-12 bg-sky-50 text-sky-700 rounded-2xl flex items-center justify-center mb-6",
	directIconRose:
		"w-12 h-12 bg-rose-50 text-rose-700 rounded-2xl flex items-center justify-center mb-6",
	directCardTitle: "text-xl font-bold mb-3 text-slate-900",
	directCardDesc: "text-sm text-slate-700 leading-relaxed",
	editorialSection: "bg-slate-900 text-white py-24 px-6",
	editorialContainer: "max-w-6xl mx-auto",
	editorialHeader: "max-w-3xl mx-auto text-center mb-16",
	editorialTitle:
		"text-3xl md:text-4xl font-extrabold tracking-tight text-white",
	editorialGrid: "grid grid-cols-1 md:grid-cols-2 gap-10 mb-16",
	editorialCard:
		"group flex flex-col bg-slate-800 rounded-3xl border border-slate-700 overflow-hidden hover:border-slate-500 transition-all duration-300",
	editorialCardMedia: "h-64 relative overflow-hidden bg-slate-900",
	editorialCardImage:
		"w-full h-full object-cover group-hover:scale-105 transition-transform duration-700",
	experiencesCardBadge:
		"absolute top-4 left-4 inline-flex items-center px-3.5 py-1.5 rounded-full text-xs font-bold tracking-wider uppercase bg-slate-900/85 text-white border border-slate-700 backdrop-blur-md",
	editorialCardBody: "p-8 flex-grow flex flex-col justify-between",
	editorialFeatureList: "space-y-4 mb-6",
	editorialFeatureItem: "flex items-start gap-4",
	editorialFeatureIconSky:
		"w-10 h-10 shrink-0 bg-slate-900 text-[#38BDF8] border border-slate-700 rounded-xl flex items-center justify-center mt-0.5",
	editorialFeatureIconRose:
		"w-10 h-10 shrink-0 bg-slate-900 text-[#FB7185] border border-slate-700 rounded-xl flex items-center justify-center mt-0.5",
	editorialFeatureTitle: "text-xl font-bold text-white mb-1",
	editorialFeatureDesc: "text-sm text-slate-200 leading-relaxed",
	editorialCardCta:
		"inline-flex items-center space-x-2 text-sm font-bold text-white group-hover:text-slate-200",
	hostCard:
		"bg-slate-800 rounded-3xl p-8 md:p-12 border border-slate-700 flex flex-col md:flex-row items-center gap-10",
	hostAvatarWrapper:
		"w-32 h-32 md:w-44 md:h-44 rounded-full overflow-hidden shrink-0 border-4 border-slate-700",
	hostAvatarImg: "w-full h-full object-cover",
	hostHeaderRow: "flex flex-wrap items-center gap-3 mb-4",
	hostName: "text-2xl md:text-3xl font-bold text-white",
	hostRoleBadge:
		"inline-flex items-center px-3.5 py-1 rounded-full text-xs font-bold bg-slate-900 text-slate-200 border border-slate-700",
	hostBio: "text-base md:text-lg text-slate-200 leading-relaxed mb-4",
	hostRntBadge:
		"inline-flex items-center space-x-2 text-xs font-semibold text-[#34D399] bg-slate-900 px-4 py-2 rounded-full border border-slate-700",
	faqSection: "py-24 px-6 max-w-4xl mx-auto border-t border-slate-200",
	faqTitle:
		"text-3xl md:text-4xl font-extrabold mb-12 text-center text-slate-900",
	faqList: "space-y-4",
	faqDetails:
		"group bg-white rounded-2xl border border-slate-200 shadow-sm open:shadow-md transition-all",
	faqSummary:
		"flex items-center justify-between gap-4 p-6 md:p-8 cursor-pointer list-none font-bold text-lg md:text-xl text-slate-900 select-none",
	faqChevron:
		"w-5 h-5 text-slate-500 shrink-0 transition-transform duration-300 group-open:rotate-180",
	faqAnswerWrapper: "px-6 pb-6 md:px-8 md:pb-8 pt-4 border-t border-slate-100",
	faqAnswer: "text-slate-700 leading-relaxed",
	ctaSection: "py-20 px-6 max-w-6xl mx-auto mb-12",
	ctaCard:
		"bg-slate-900 text-white text-center relative overflow-hidden rounded-3xl py-16 px-8 md:px-16 shadow-2xl border border-slate-800",
	ctaContent: "relative z-10 max-w-3xl mx-auto flex flex-col items-center",
	ctaIconBox:
		"w-14 h-14 bg-slate-800 text-white rounded-2xl flex items-center justify-center mb-6",
	ctaTitle:
		"text-3xl md:text-4xl font-extrabold tracking-tight mb-4 text-white",
	ctaSubtitle: "text-lg text-slate-200 font-light mb-8 leading-relaxed",
	ctaActionsRow:
		"flex flex-col sm:flex-row flex-wrap items-center justify-center gap-4 w-full sm:w-auto",
	ctaPrimaryBtn:
		"w-full sm:w-auto inline-flex items-center justify-center space-x-2.5 min-h-[48px] bg-white text-slate-900 hover:bg-slate-100 px-8 py-4 rounded-full font-bold text-base md:text-lg shadow-xl transition-all duration-300 hover:scale-105",
	ctaSecondaryBtn:
		"w-full sm:w-auto inline-flex items-center justify-center space-x-2.5 min-h-[48px] bg-slate-800/80 border border-slate-600 text-white hover:bg-slate-800 px-7 py-3.5 rounded-full font-semibold text-base transition-colors",
	ctaWhatsAppBtn:
		"w-full sm:w-auto inline-flex items-center justify-center space-x-2.5 min-h-[48px] bg-slate-800/80 border border-[#047857] text-[#34D399] hover:bg-slate-800 px-7 py-3.5 rounded-full font-semibold text-base transition-colors",
} as const;

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
