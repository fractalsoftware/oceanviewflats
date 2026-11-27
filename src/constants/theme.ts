import {
	AIRBNB_URLS,
	DATA_CONTROLLER,
	IMAGE_DIMENSIONS,
	IMAGES,
} from "./config";

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

	// Exact on-disk image dimensions for CLS prevention
	imageDimensions: {
		hero: { readonly width: number; readonly height: number };
		bedroom: { readonly width: number; readonly height: number };
		pool: { readonly width: number; readonly height: number };
	};

	// Airbnb target URL
	airbnbUrl: string;

	// Centralized keys for property-specific copy & localized image alts
	bedTextKey: "statsBeds" | "stats1606Beds";
	heroTitleKey: "heroTitle" | "hero1606Title";
	heroSubKey: "heroSub" | "hero1606Sub";
	heroImgAltKey: "hero1707ImgAlt" | "hero1606ImgAlt";
	aboutBedroomAltKey: "about1707LivingAlt" | "about1606LivingAlt";
	aboutPoolAltKey: "about1707PoolAlt" | "about1606PoolAlt";

	// Property-specific description & identity keys
	aboutText1Key: "aboutText1" | "about1606Text1";
	aboutText2Key: "aboutText2" | "about1606Text2";
	rntNumber: string;

	// AboutAmenities centralized styles
	aboutThemeAccent: string;
	aboutHostAccent: string;
	aboutHostBorder: string;
	aboutHostBg: string;
	aboutHostName: string;
	aboutHostMetaRow: string;
	aboutHostControllerBadge: string;
	aboutHostRntBadge: string;
	aboutHostComplianceRow: string;
	aboutHostComplianceBadge: string;
	aboutHostComplianceLink: string;
	aboutHostActionsRow: string;
	aboutHostWhatsAppBtn: string;
	aboutHostEmailBtn: string;
	aboutHostIconSm: string;

	// Guest Reviews centralized styles
	reviewSection: string;
	reviewGlow: string;
	reviewContainer: string;
	reviewTitle: string;
	reviewGrid: string;
	reviewListItem: string;
	reviewCard: string;
	reviewQuoteIcon: string;
	reviewStarsRow: string;
	reviewStarIcon: string;
	reviewText: string;
	reviewAuthorRow: string;
	reviewAvatar: string;
	reviewAuthorDetails: string;
	reviewAuthorName: string;
	reviewMetaRow: string;
	reviewVerifiedBadge: string;
	reviewDate: string;
	reviewAttributionWrapper: string;
	reviewAttributionLink: string;
	reviewIconSm: string;

	// PropertyFaq centralized styles & copy keys
	propertyFaqTitleKey: "faq1707Title" | "faq1606Title";
	propertyFaqSubtitleKey: "faq1707Subtitle" | "faq1606Subtitle";
	propertyFaqSection: string;
	propertyFaqContainer: string;
	propertyFaqHeader: string;
	propertyFaqTitle: string;
	propertyFaqSubtitle: string;
	propertyFaqList: string;
	propertyFaqDetails: string;
	propertyFaqSummary: string;
	propertyFaqChevron: string;
	propertyFaqAnswerWrapper: string;
	propertyFaqAnswer: string;

	// PropertyDestinationGuides centralized styles & copy keys
	propertyGuidesTitleKey: "property1707GuidesTitle" | "property1606GuidesTitle";
	propertyGuidesSubtitleKey:
		| "property1707GuidesSubtitle"
		| "property1606GuidesSubtitle";
	propertyGuidesSection: string;
	propertyGuidesContainer: string;
	propertyGuidesCard: string;
	propertyGuidesHeader: string;
	propertyGuidesEyebrow: string;
	propertyGuidesTitle: string;
	propertyGuidesSubtitle: string;
	propertyGuidesLinksRow: string;
	propertyGuidesLink: string;
	propertyGuidesHubLink: string;
	propertyGuidesIcon: string;

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
	bookingMinStayWarning: string;

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
	skipLink:
		"ovf-skip-link fixed top-3 left-3 z-[60] bg-slate-900 text-white px-4 py-2.5 rounded-lg text-sm font-bold shadow-lg focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#FF5A5F]/50",
	nav: {
		top: "fixed w-full z-50 transition-all duration-300 bg-transparent py-5",
		scrolled:
			"fixed w-full z-50 transition-all duration-300 bg-white/90 backdrop-blur-md shadow-sm py-3",
	},
	navContainer:
		"max-w-6xl mx-auto px-3 sm:px-6 flex justify-between items-center gap-2",
	navLogo: {
		top: "font-bold text-sm sm:text-lg md:text-xl tracking-tight inline-flex items-center gap-1.5 sm:gap-2 min-h-[40px] shrink-0 transition-colors rounded-lg text-white hover:text-white/80 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#FF5A5F]/40",
		scrolled:
			"font-bold text-sm sm:text-lg md:text-xl tracking-tight inline-flex items-center gap-1.5 sm:gap-2 min-h-[40px] shrink-0 transition-colors rounded-lg text-coral-horizon-ink hover:text-coral-horizon-ink-deep focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#FF5A5F]/40",
	},
	navLogoIcon: "w-5 h-5 sm:w-6 sm:h-6 fill-current shrink-0",
	navActions: "flex items-center gap-1.5 sm:gap-2 md:gap-5 min-w-0",
	navGuides: {
		top: "hidden md:inline-flex items-center justify-center min-h-[40px] min-w-[40px] px-2.5 text-sm font-semibold transition-colors rounded-lg text-white hover:text-white/80 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#FF5A5F]/40",
		scrolled:
			"hidden md:inline-flex items-center justify-center min-h-[40px] min-w-[40px] px-2.5 text-sm font-semibold transition-colors rounded-lg text-slate-600 hover:text-rose-700 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#FF5A5F]/40",
	},
	navContact: {
		top: "hidden md:inline-flex items-center justify-center min-h-[40px] min-w-[40px] px-2.5 text-sm font-semibold transition-colors rounded-lg text-white hover:text-white/80 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#FF5A5F]/40",
		scrolled:
			"hidden md:inline-flex items-center justify-center min-h-[40px] min-w-[40px] px-2.5 text-sm font-semibold transition-colors rounded-lg text-slate-600 hover:text-rose-700 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#FF5A5F]/40",
	},
	navBookBtn: {
		top: "inline-flex items-center justify-center min-h-[40px] px-2.5 sm:px-4 md:px-5 py-2 text-[11px] sm:text-xs md:text-sm font-bold leading-tight text-center rounded-full bg-coral-horizon-accessible hover:bg-coral-horizon-ink text-white shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#FF5A5F]/40",
		scrolled:
			"inline-flex items-center justify-center min-h-[40px] px-2.5 sm:px-4 md:px-5 py-2 text-[11px] sm:text-xs md:text-sm font-bold leading-tight text-center rounded-full bg-coral-horizon-accessible hover:bg-coral-horizon-ink text-white shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#FF5A5F]/40",
	},
	navMenuToggle: {
		top: "md:hidden inline-flex items-center justify-center min-h-[40px] min-w-[40px] p-2 rounded-lg text-white hover:bg-white/20 transition-colors cursor-pointer shrink-0 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#FF5A5F]/40",
		scrolled:
			"md:hidden inline-flex items-center justify-center min-h-[40px] min-w-[40px] p-2 rounded-lg text-slate-700 hover:bg-slate-100 transition-colors cursor-pointer shrink-0 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#FF5A5F]/40",
	},
	navMenuIcon: "w-5 h-5",
	mobileDrawer:
		"hidden md:hidden absolute top-full inset-x-0 bg-white border-b border-slate-200 shadow-2xl px-4 py-5 max-h-[82dvh] overflow-y-auto",
	mobileDrawerHeader:
		"flex items-center justify-between pb-3 mb-4 border-b border-slate-100",
	mobileDrawerTitle:
		"text-xs font-bold uppercase tracking-wider text-slate-600",
	mobileDrawerCloseBtn:
		"inline-flex items-center justify-center min-h-[40px] min-w-[40px] p-2 rounded-lg text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#FF5A5F]/40",
	mobileDrawerGroups: "space-y-5",
	mobileDrawerGroupHeading:
		"text-xs font-bold uppercase tracking-wider text-slate-500 mb-2 px-2",
	mobileDrawerList: "space-y-1 list-none m-0 p-0",
	mobileDrawerLink:
		"flex items-center justify-between min-h-[44px] px-3 py-2.5 rounded-xl text-sm font-semibold text-slate-800 hover:bg-slate-50 hover:text-[#B91C1C] transition-colors focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#FF5A5F]/40",
	mobileDrawerLinkIcon: "w-4 h-4 text-slate-400 shrink-0",
	langGroup: "relative group shrink-0",
	langToggle: {
		top: "inline-flex items-center justify-center gap-1 sm:gap-1.5 min-h-[40px] min-w-[40px] px-2 sm:px-3.5 py-2 rounded-full text-xs sm:text-sm font-semibold transition-colors cursor-pointer bg-white/20 hover:bg-white/30 text-white backdrop-blur-sm focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#FF5A5F]/40",
		scrolled:
			"inline-flex items-center justify-center gap-1 sm:gap-1.5 min-h-[40px] min-w-[40px] px-2 sm:px-3.5 py-2 rounded-full text-xs sm:text-sm font-semibold transition-colors cursor-pointer bg-slate-100 hover:bg-slate-200 text-slate-700 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#FF5A5F]/40",
	},
	langCodeMobile: "md:hidden font-bold uppercase tracking-wide",
	langLabelDesktop: "hidden md:inline",
	langGlobeIcon: "w-3.5 h-3.5 sm:w-4 sm:h-4 shrink-0",
	langChevronIcon: "w-3 h-3 hidden sm:inline shrink-0",
	langDropdownWrapper:
		"absolute right-0 top-full pt-2 w-36 opacity-0 invisible transition-all duration-200 z-50",
	langDropdownCard: "bg-white rounded-xl shadow-lg border border-slate-100",
	langDropdownList: "py-2",
	langDropdownItem:
		"block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 hover:text-rose-700 transition-colors rounded-lg focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#FF5A5F]/40",
	langDropdownActiveItem:
		"block px-4 py-2 text-sm font-semibold text-rose-700 bg-slate-50 rounded-lg",
	footer: "bg-slate-900 text-slate-300 py-14 px-6 border-t border-slate-800",
	footerContainer: "max-w-6xl mx-auto",
	footerGrid:
		"grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 text-left pb-10 border-b border-slate-800",
	footerGroup: "min-w-0",
	footerGroupHeading:
		"text-xs font-bold uppercase tracking-wider text-white mb-3",
	footerLinkList: "space-y-2.5 list-none m-0 p-0",
	footerLink:
		"inline-block text-sm text-slate-300 hover:text-white underline underline-offset-4 transition-colors rounded-lg py-0.5 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#FF5A5F]/40",
	footerHostText: "text-sm font-medium text-slate-200 mt-3",
	footerRntText:
		"text-xs tracking-wider text-slate-300 uppercase mt-2 leading-relaxed",
	footerPrivacyWrapper: "text-sm mb-2",
	footerPrivacyLink:
		"inline-block text-sm text-slate-300 hover:text-white underline underline-offset-4 transition-colors rounded-lg py-0.5 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#FF5A5F]/40",
	footerGuidesNav: "min-w-0",
	footerGuidesHeading:
		"text-xs font-bold uppercase tracking-wider text-white mb-3",
	footerGuidesList: "space-y-2.5 list-none m-0 p-0",
	footerGuidesLink:
		"inline-block text-sm text-slate-300 hover:text-white underline underline-offset-4 transition-colors rounded-lg py-0.5 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#FF5A5F]/40",
	footerBottom:
		"pt-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-center sm:text-left",
	footerBrandRow: "flex items-center gap-3",
	footerLogo: "w-7 h-7 fill-current text-slate-400 shrink-0",
	footerCopyright: "text-sm text-slate-300",
	footerBadge:
		"group inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-slate-800/80 border border-slate-700 hover:border-slate-500 text-xs font-mono font-semibold text-slate-300 hover:text-white shadow-sm transition-all duration-300 hover:-translate-y-0.5 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#FF5A5F]/40",
	footerBadgeDot: "w-1.5 h-1.5 rounded-full bg-slate-400",
	footerBadgeLogo:
		"w-3.5 h-3.5 fill-current text-slate-300 transition-transform duration-300 group-hover:scale-110",
	footerBadgeBrand: "text-white font-bold tracking-wide",
} as const;

export type BreadcrumbSurface = "coolBar" | "warmBar" | "inline";

export const BREADCRUMB_THEME = {
	coolBar: {
		nav: "bg-slate-50 border-b border-slate-200/80",
		container: "max-w-6xl mx-auto px-6 py-3",
		list: "flex flex-wrap items-center gap-1.5 text-sm text-slate-600 list-none m-0 p-0",
		item: "inline-flex items-center gap-1.5",
		link: "text-slate-700 hover:text-slate-900 underline-offset-4 hover:underline transition-colors rounded-sm relative focus-visible:z-10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#FF5A5F]",
		separator: "w-4 h-4 text-slate-400 shrink-0",
		current:
			"font-semibold text-slate-900 no-underline forced-colors:underline rounded-sm relative focus-visible:z-10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#FF5A5F]",
	},
	warmBar: {
		nav: "bg-stone-50 border-b border-stone-200/80",
		container: "max-w-6xl mx-auto px-6 py-3",
		list: "flex flex-wrap items-center gap-1.5 text-sm text-stone-600 list-none m-0 p-0",
		item: "inline-flex items-center gap-1.5",
		link: "text-stone-700 hover:text-stone-900 underline-offset-4 hover:underline transition-colors rounded-sm relative focus-visible:z-10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-600",
		separator: "w-4 h-4 text-stone-400 shrink-0",
		current:
			"font-semibold text-stone-900 no-underline forced-colors:underline rounded-sm relative focus-visible:z-10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-600",
	},
	inline: {
		nav: "mb-6",
		container: "w-full",
		list: "flex flex-wrap items-center gap-1.5 text-sm text-slate-600 list-none m-0 p-0",
		item: "inline-flex items-center gap-1.5",
		link: "text-slate-700 hover:text-slate-900 underline-offset-4 hover:underline transition-colors rounded-sm relative focus-visible:z-10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#FF5A5F]",
		separator: "w-4 h-4 text-slate-400 shrink-0",
		current:
			"font-semibold text-slate-900 no-underline forced-colors:underline rounded-sm relative focus-visible:z-10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#FF5A5F]",
	},
} as const;

const BASE_HOME_CARD_THEME = {
	cardImage:
		"absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-700",
	cardHeaderContent: "z-10 p-5 sm:p-6 text-white",
	cardTitle: "text-2xl md:text-3xl font-extrabold text-white text-balance",
	rateRow: "flex flex-col gap-1 mb-4",
	lifestyleStrip:
		"mt-5 grid grid-cols-1 sm:grid-cols-[120px_1fr] gap-4 items-start",
	lifestyleThumbImg: "w-full h-full object-cover",
	highlightsList: "space-y-2.5 list-none m-0 p-0",
	highlightItem: "flex items-start gap-2.5",
	reviewStarsRow: "flex items-center gap-1 text-amber-500 mb-2",
	reviewStarIcon: "w-4 h-4 fill-current",
	reviewFooter: "flex flex-col gap-1.5",
	reviewMetaRow: "flex flex-wrap items-center gap-x-2 gap-y-1 text-xs",
	reviewLinkRow: "flex items-center",
	reviewVerifiedBadge:
		"inline-flex items-center gap-1 font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full",
	reviewIconSm: "w-3.5 h-3.5 shrink-0",
	cardCtaIcon:
		"w-5 h-5 transition-transform duration-300 group-hover:translate-x-1 shrink-0",
} as const;

export const HOME_CARD_THEMES = {
	"1707": {
		...BASE_HOME_CARD_THEME,
		card: "group relative flex flex-col bg-white rounded-3xl shadow-md border border-slate-200 overflow-hidden hover:shadow-2xl hover:border-sky-300 transition-all duration-300",
		cardMedia:
			"relative flex flex-col justify-end h-72 md:h-80 overflow-hidden bg-slate-900",
		cardOverlay:
			"absolute inset-0 bg-gradient-to-t from-slate-950/80 via-slate-900/20 to-transparent pointer-events-none",
		cardBadge:
			"absolute top-4 left-4 z-30 inline-flex items-center px-3.5 py-1.5 rounded-full text-xs font-bold tracking-wider uppercase bg-slate-900/85 text-white border border-sky-400/40 backdrop-blur-md",
		cardHeadingLink:
			"text-white hover:text-sky-100 transition-colors focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-sky-300/60 rounded-lg after:content-[''] after:absolute after:inset-0 after:z-20",
		cardBody:
			"relative z-10 flex-grow flex flex-col justify-between p-6 md:p-8 bg-slate-50/60 border-t border-slate-200/80",
		rateBadge:
			"block text-xl md:text-2xl font-extrabold tracking-tight tabular-nums text-slate-900 leading-snug",
		rateCurrencyNote: "text-xs text-slate-600 leading-relaxed text-pretty",
		cardDesc:
			"text-slate-700 text-base leading-relaxed max-w-[58ch] text-pretty",
		cardSpecBar:
			"mt-5 py-3 border-y border-slate-200/80 flex flex-wrap items-center gap-x-3 gap-y-1.5 text-xs md:text-sm text-slate-700 list-none m-0",
		cardSpecHighlight: "font-bold text-slate-900",
		cardSpecItem: "inline-flex items-center gap-1.5 text-slate-700",
		cardSpecDivider: "text-slate-300 select-none",
		lifestyleThumbWrapper:
			"rounded-2xl overflow-hidden bg-slate-200 aspect-[16/10] sm:aspect-[4/3] shrink-0",
		highlightIcon: "w-4 h-4 mt-0.5 text-sky-700 shrink-0",
		highlightTitle: "text-sm font-bold text-slate-900 leading-snug",
		highlightDesc: "text-xs text-slate-600 leading-relaxed mt-0.5 text-pretty",
		reviewBox: "mt-6 pt-5 border-t border-slate-200/80",
		reviewQuote:
			"text-sm italic text-slate-700 leading-relaxed mb-3 pl-3.5 border-l-2 border-sky-500 text-pretty",
		reviewMetaRow: `${BASE_HOME_CARD_THEME.reviewMetaRow} text-slate-600`,
		reviewAuthorName: "font-bold text-slate-900",
		reviewDate: "text-slate-600 font-medium",
		reviewAirbnbLink:
			"inline-flex items-center gap-1.5 min-h-[44px] py-2 -ml-1 px-1 text-xs font-semibold text-slate-700 hover:text-coral-horizon-ink underline underline-offset-4 rounded-lg transition-colors focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-coral-horizon/40",
		cardCtaBtn:
			"mt-6 inline-flex items-center justify-between w-full min-h-[48px] px-6 py-3 rounded-full bg-slate-900 hover:bg-coral-horizon-ink text-white text-sm md:text-base font-bold shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#FF5A5F]/40",
	},
	"1606": {
		...BASE_HOME_CARD_THEME,
		card: "group relative flex flex-col bg-white rounded-3xl shadow-md border border-stone-200 overflow-hidden hover:shadow-2xl hover:border-rose-300 transition-all duration-300",
		cardMedia:
			"relative flex flex-col justify-end h-72 md:h-80 overflow-hidden bg-stone-900",
		cardOverlay:
			"absolute inset-0 bg-gradient-to-t from-stone-950/80 via-stone-900/20 to-transparent pointer-events-none",
		cardBadge:
			"absolute top-4 left-4 z-30 inline-flex items-center px-3.5 py-1.5 rounded-full text-xs font-bold tracking-wider uppercase bg-stone-900/85 text-white border border-rose-400/40 backdrop-blur-md",
		cardHeadingLink:
			"text-white hover:text-rose-100 transition-colors focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-rose-300/60 rounded-lg after:content-[''] after:absolute after:inset-0 after:z-20",
		cardBody:
			"relative z-10 flex-grow flex flex-col justify-between p-6 md:p-8 bg-stone-50/70 border-t border-stone-200/80",
		rateBadge:
			"block text-xl md:text-2xl font-extrabold tracking-tight tabular-nums text-stone-900 leading-snug",
		rateCurrencyNote: "text-xs text-stone-600 leading-relaxed text-pretty",
		cardDesc:
			"text-stone-700 text-base leading-relaxed max-w-[58ch] text-pretty",
		cardSpecBar:
			"mt-5 py-3 border-y border-stone-200/80 flex flex-wrap items-center gap-x-3 gap-y-1.5 text-xs md:text-sm text-stone-700 list-none m-0",
		cardSpecHighlight: "font-bold text-stone-900",
		cardSpecItem: "inline-flex items-center gap-1.5 text-stone-700",
		cardSpecDivider: "text-stone-300 select-none",
		lifestyleThumbWrapper:
			"rounded-2xl overflow-hidden bg-stone-200 aspect-[16/10] sm:aspect-[4/3] shrink-0",
		highlightIcon: "w-4 h-4 mt-0.5 text-rose-700 shrink-0",
		highlightTitle: "text-sm font-bold text-stone-900 leading-snug",
		highlightDesc: "text-xs text-stone-600 leading-relaxed mt-0.5 text-pretty",
		reviewBox: "mt-6 pt-5 border-t border-stone-200/80",
		reviewQuote:
			"text-sm italic text-stone-700 leading-relaxed mb-3 pl-3.5 border-l-2 border-rose-500 text-pretty",
		reviewMetaRow: `${BASE_HOME_CARD_THEME.reviewMetaRow} text-stone-600`,
		reviewAuthorName: "font-bold text-stone-900",
		reviewDate: "text-stone-600 font-medium",
		reviewAirbnbLink:
			"inline-flex items-center gap-1.5 min-h-[44px] py-2 -ml-1 px-1 text-xs font-semibold text-stone-700 hover:text-salguero-sunset-rose-deep underline underline-offset-4 rounded-lg transition-colors focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-rose-500/40",
		cardCtaBtn:
			"mt-6 inline-flex items-center justify-between w-full min-h-[48px] px-6 py-3 rounded-full bg-stone-900 hover:bg-salguero-sunset-rose-deep text-white text-sm md:text-base font-bold shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-rose-500/40",
	},
} as const;

export const HOME_THEME = {
	pageWrapper:
		"min-h-screen flex flex-col bg-slate-50 font-sans text-slate-800 scroll-smooth",
	heroHeader:
		"relative bg-slate-900 pt-24 pb-14 sm:pt-28 sm:pb-18 md:pt-32 md:pb-22 px-4 sm:px-6 text-center overflow-hidden",
	heroMediaWrapper: "absolute inset-0 w-full h-full",
	heroImage: "w-full h-full object-cover object-[center_35%] saturate-[1.1]",
	heroOverlay:
		"absolute inset-0 bg-gradient-to-b from-sky-950/35 via-slate-900/55 to-slate-950/80",
	heroContent:
		"relative z-10 max-w-4xl mx-auto mt-2 sm:mt-4 flex flex-col items-center",
	heroLocationBadge:
		"inline-flex items-center justify-center gap-2 text-white/95 text-xs md:text-sm font-semibold tracking-widest uppercase mb-3 sm:mb-4 drop-shadow-sm",
	heroLocationIcon: "w-4 h-4 text-sky-200 shrink-0",
	heroTitle:
		"text-3xl sm:text-5xl md:text-6xl font-extrabold mb-3 sm:mb-4 text-white tracking-tight leading-tight drop-shadow-md text-balance max-w-4xl",
	heroSubtitle:
		"text-base sm:text-lg md:text-xl text-white/95 font-normal max-w-[56ch] mx-auto mb-3 sm:mb-4 drop-shadow-sm text-pretty leading-relaxed",
	heroValueProp:
		"text-xs sm:text-sm font-semibold text-white/95 tracking-wide max-w-[58ch] mx-auto mb-6 sm:mb-7 drop-shadow-sm text-pretty",
	heroCtaGroup: "flex flex-col items-center gap-3.5 sm:gap-4 w-full",
	heroCtaBtn:
		"inline-flex items-center justify-center gap-2.5 min-h-[48px] bg-coral-horizon-accessible hover:bg-coral-horizon-ink text-white px-7 sm:px-8 py-3.5 sm:py-4 rounded-full font-bold text-base md:text-lg shadow-2xl transition-all duration-300 hover:scale-105 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-white/60",
	heroCtaIcon: "w-5 h-5 text-white shrink-0",
	heroQuickLinksRow:
		"flex flex-wrap items-center justify-center gap-2.5 sm:gap-3",
	heroQuickLinkBtn:
		"inline-flex items-center justify-center gap-1.5 min-h-[44px] px-4 sm:px-5 py-2.5 rounded-full bg-slate-950/60 hover:bg-slate-950/85 border border-white/35 backdrop-blur-md text-white text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-white/50",
	heroReturningGuestCue:
		"mt-5 sm:mt-6 flex flex-wrap items-center justify-center gap-x-2 gap-y-0.5 text-sm text-slate-100 drop-shadow-sm",
	heroReturningGuestPrompt: "text-slate-100 font-normal",
	heroReturningGuestLinks:
		"inline-flex flex-wrap items-center justify-center gap-x-1.5",
	heroReturningGuestLink:
		"inline-flex items-center min-h-[44px] px-1.5 font-semibold text-white underline decoration-white/60 underline-offset-4 hover:decoration-white transition-colors rounded-lg focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-white/50",
	heroReturningGuestDivider: "text-white/60 select-none",
	iconSm: "w-4 h-4 shrink-0",
	iconMd: "w-5 h-5 shrink-0",
	iconLg: "w-6 h-6 shrink-0",
	iconXl: "w-7 h-7 shrink-0",
	main: "flex-grow w-full",
	propertiesSection:
		"py-16 md:py-20 px-4 sm:px-6 max-w-6xl mx-auto scroll-mt-20",
	propertiesHeading:
		"text-3xl md:text-4xl font-extrabold tracking-tight text-slate-900 text-center mb-10 md:mb-12 text-balance",
	propertiesGrid: "grid grid-cols-1 lg:grid-cols-2 gap-8 md:gap-10",
	multiPropertyBanner:
		"mt-10 bg-white rounded-3xl p-6 md:p-8 border border-slate-200 shadow-sm flex flex-col md:flex-row items-start md:items-center justify-between gap-6",
	multiPropertyContent: "flex items-start gap-4",
	multiPropertyIconBox:
		"w-11 h-11 shrink-0 bg-red-50 text-[#B91C1C] rounded-2xl flex items-center justify-center",
	multiPropertyText:
		"text-sm md:text-base text-slate-700 leading-relaxed max-w-[58ch] text-pretty",
	multiPropertyCta:
		"shrink-0 inline-flex items-center justify-center gap-2 min-h-[44px] bg-slate-900 hover:bg-slate-800 text-white px-6 py-3 rounded-full text-sm font-bold shadow-md transition-colors focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#FF5A5F]/40",
	// #302 & #319 Homepage Differentiating Property Comparison Table + Shared Building Rules
	compareSection:
		"py-16 md:py-20 px-4 sm:px-6 bg-white border-t border-slate-200 scroll-mt-20",
	compareContainer: "max-w-6xl mx-auto",
	compareHeader: "text-center max-w-3xl mx-auto mb-10 md:mb-12",
	compareTitle:
		"text-3xl md:text-4xl font-extrabold tracking-tight text-slate-900 mb-4 text-balance",
	compareSubtitle:
		"text-base md:text-lg text-slate-700 leading-relaxed max-w-[58ch] mx-auto text-pretty",
	compareTableWrapper:
		"ovf-compare-wrapper rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#FF5A5F]/40",
	compareTable:
		"ovf-compare-table w-full border-separate border-spacing-0 text-left text-sm md:text-base",
	compareCaption:
		"px-4 sm:px-6 py-3.5 text-left text-xs font-semibold tracking-wide text-slate-600 bg-slate-50 border-b border-slate-200",
	compareThead: "bg-slate-900 text-white",
	compareColHeaderFirst:
		"bg-slate-900 py-4 px-5 md:px-6 text-xs md:text-sm font-bold uppercase tracking-wider text-slate-200 border-b border-slate-800 w-1/4",
	compareColHeader:
		"py-4 px-5 md:px-6 text-sm md:text-base font-bold text-white border-b border-slate-800 w-[37.5%]",
	compareTbody: "bg-white",
	compareRow:
		"group odd:bg-white even:bg-slate-50/70 hover:bg-slate-100/70 transition-colors",
	compareRowHeader:
		"py-4 px-5 md:px-6 font-semibold text-slate-900 align-top border-b border-slate-200",
	compareCell:
		"py-4 px-5 md:px-6 text-slate-700 align-top border-b border-slate-200 leading-relaxed",
	compareRatePrimary: "block font-bold text-slate-900",
	compareRateNote: "block text-xs text-slate-600 mt-1",
	sharedRulesBox:
		"mt-8 rounded-3xl bg-slate-50 border border-slate-200 p-6 md:p-8",
	sharedRulesHeader:
		"flex items-center gap-2.5 pb-4 mb-5 border-b border-slate-200",
	sharedRulesIcon: "w-5 h-5 text-slate-700 shrink-0",
	sharedRulesTitle:
		"text-base md:text-lg font-bold text-slate-900 text-balance",
	sharedRulesGrid:
		"grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-x-6 gap-y-4 list-none m-0 p-0",
	sharedRulesItem: "pt-1",
	sharedRulesTerm:
		"text-xs font-bold uppercase tracking-wider text-slate-600 mb-1",
	sharedRulesValue:
		"text-sm font-semibold text-slate-900 leading-snug text-pretty",
	// #302, #319 & #331 Homepage Playa Salguero Location & Balanced 3x2 Grid
	locationSection: "py-16 md:py-20 px-4 sm:px-6 bg-white scroll-mt-20",
	locationContainer: "max-w-6xl mx-auto",
	locationHeader: "text-center max-w-3xl mx-auto mb-12",
	locationAddressRow:
		"flex flex-wrap items-center justify-center gap-x-3 gap-y-2 mb-5",
	locationAddressBadge:
		"inline-flex items-center justify-center gap-2 bg-slate-100 border border-slate-200 px-3.5 py-1.5 rounded-md text-slate-700 text-xs md:text-sm font-medium",
	locationMapLink:
		"inline-flex items-center justify-center gap-1.5 min-h-[44px] px-4 py-2 rounded-full bg-white hover:bg-slate-100 border border-slate-300 text-slate-900 hover:text-coral-horizon-ink text-xs md:text-sm font-semibold shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-coral-horizon/40",
	locationMapExternalIcon: "w-3.5 h-3.5 shrink-0",
	locationTitle:
		"text-3xl md:text-4xl font-extrabold tracking-tight text-slate-900 mb-4 text-balance",
	locationSubtitle:
		"text-base md:text-lg text-slate-700 leading-relaxed max-w-[58ch] mx-auto text-pretty",
	locationGrid:
		"grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 list-none m-0 p-0",
	locationCard:
		"flex flex-col bg-slate-50 rounded-3xl p-6 md:p-7 border border-slate-200 shadow-sm hover:bg-white hover:shadow-md transition-all duration-300",
	locationCardTop: "flex items-center justify-between gap-3 mb-4",
	locationIconBox:
		"inline-flex items-center justify-center text-coral-horizon-ink",
	locationProximityBadge:
		"inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-slate-100 text-slate-700 border border-slate-200/80",
	locationCardTitle:
		"text-lg md:text-xl font-bold text-slate-900 mb-2 text-balance",
	locationCardDesc: "text-sm text-slate-700 leading-relaxed text-pretty",
	locationGuidesCard:
		"flex flex-col justify-between bg-slate-900 text-white rounded-3xl p-6 md:p-7 border border-slate-800 shadow-sm",
	locationGuidesTop: "flex items-center gap-2.5 mb-3",
	locationGuidesIcon: "w-5 h-5 text-sky-300 shrink-0",
	locationGuidesEyebrow:
		"text-xs font-bold uppercase tracking-wider text-slate-200",
	locationGuidesText: "text-sm text-slate-200 leading-relaxed mb-6 text-pretty",
	locationGuidesCta:
		"inline-flex items-center justify-center gap-2 min-h-[44px] w-full bg-white hover:bg-slate-100 text-slate-900 px-5 py-3 rounded-full text-sm font-bold shadow-md transition-colors focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-white/40",
	cards: HOME_CARD_THEMES,
	card1707: HOME_CARD_THEMES["1707"],
	card1606: HOME_CARD_THEMES["1606"],
	// #319 Editorial Why Book Direct + Trust Strip + Host Card Band
	directSection:
		"py-16 md:py-20 bg-gradient-to-b from-slate-50 to-white border-t border-b border-slate-200 px-4 sm:px-6",
	directContainer: "max-w-6xl mx-auto",
	directHeader: "text-center max-w-3xl mx-auto mb-12",
	directTitle:
		"text-3xl md:text-4xl font-extrabold tracking-tight text-slate-900 mb-4 text-balance",
	directSubtitle:
		"text-base md:text-lg text-slate-700 leading-relaxed max-w-[58ch] mx-auto text-pretty",
	directGrid:
		"grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 md:gap-8 list-none m-0 p-0",
	directEditorialItem:
		"bg-white p-6 md:p-7 rounded-2xl border border-slate-200 shadow-xs flex flex-col",
	directItemHeader: "flex items-center gap-2.5 mb-3",
	directIconEmerald: "w-5 h-5 text-emerald-700 shrink-0",
	directIconCoral: "w-5 h-5 text-[#B91C1C] shrink-0",
	directIconSky: "w-5 h-5 text-sky-700 shrink-0",
	directCardTitle: "text-lg font-bold text-slate-900 leading-snug text-balance",
	directCardDesc: "text-sm text-slate-700 leading-relaxed text-pretty",
	trustStripGrid: "mt-10 grid grid-cols-1 md:grid-cols-2 gap-6",
	trustStripCardEmerald:
		"rounded-2xl p-6 bg-emerald-50/70 border border-emerald-200 flex items-start gap-4",
	trustStripCardSlate:
		"rounded-2xl p-6 bg-slate-50 border border-slate-200 flex items-start gap-4",
	trustStripIconEmerald: "w-6 h-6 text-emerald-700 shrink-0 mt-0.5",
	trustStripIconSlate: "w-6 h-6 text-slate-800 shrink-0 mt-0.5",
	trustStripTitle: "text-base font-bold text-slate-900 mb-1.5 text-balance",
	trustStripDesc:
		"text-sm text-slate-700 leading-relaxed max-w-[58ch] text-pretty",
	hostCard:
		"mt-10 bg-slate-900 rounded-3xl p-6 sm:p-8 md:p-10 border border-slate-800 shadow-lg flex flex-col md:flex-row items-center gap-8 md:gap-10",
	hostAvatarWrapper:
		"w-28 h-28 md:w-40 md:h-40 rounded-full overflow-hidden shrink-0 border-4 border-slate-700 bg-slate-700",
	hostAvatarImg: "w-full h-full object-cover",
	hostBody: "flex-grow text-center md:text-left",
	hostHeaderRow:
		"flex flex-wrap items-center justify-center md:justify-start gap-3 mb-3",
	hostName: "text-2xl md:text-3xl font-bold text-white",
	hostRoleBadge:
		"inline-flex items-center px-3.5 py-1 rounded-full text-xs font-bold bg-slate-800 text-slate-200 border border-slate-700",
	hostBio:
		"text-base text-slate-200 leading-relaxed mb-4 max-w-[58ch] text-pretty",
	hostBadgesRow:
		"flex flex-wrap items-center justify-center md:justify-start gap-2.5",
	hostControllerBadge:
		"inline-flex items-center gap-2 text-xs font-semibold text-slate-200 bg-slate-800 px-4 py-2 rounded-full border border-slate-700",
	hostRntBadge:
		"inline-flex items-center gap-2 text-xs font-semibold text-[#34D399] bg-slate-800 px-4 py-2 rounded-full border border-slate-700",
	hostActionsRow:
		"mt-5 flex flex-wrap items-center justify-center md:justify-start gap-3",
	hostWhatsAppBtn:
		"inline-flex items-center justify-center gap-2 min-h-[44px] px-5 py-2.5 rounded-full text-sm font-semibold bg-emerald-500/15 hover:bg-emerald-500/25 text-[#34D399] border border-[#047857] transition-colors focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-emerald-400/40",
	hostEmailBtn:
		"inline-flex items-center justify-center gap-2 min-h-[44px] px-5 py-2.5 rounded-full text-sm font-semibold bg-slate-800 hover:bg-slate-700 text-slate-100 border border-slate-600 transition-colors focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-white/40",
	faqSection:
		"py-16 md:py-24 px-4 sm:px-6 max-w-4xl mx-auto border-t border-slate-200",
	faqTitle:
		"text-3xl md:text-4xl font-extrabold mb-10 md:mb-12 text-center text-slate-900 text-balance",
	faqList: "space-y-4",
	faqDetails:
		"group bg-white rounded-2xl border border-slate-200 shadow-sm open:shadow-md transition-all",
	faqSummary:
		"flex items-center justify-between gap-4 p-5 sm:p-6 md:p-8 cursor-pointer list-none font-bold text-base sm:text-lg md:text-xl text-slate-900 select-none rounded-2xl focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#FF5A5F]/40",
	faqChevron:
		"w-5 h-5 text-slate-500 shrink-0 transition-transform duration-300 group-open:rotate-180",
	faqAnswerWrapper:
		"px-5 pb-5 sm:px-6 sm:pb-6 md:px-8 md:pb-8 pt-4 border-t border-slate-100",
	faqAnswer: "text-slate-700 leading-relaxed max-w-[58ch] text-pretty",
	faqLinksRow: "mt-4 flex flex-wrap items-center gap-3",
	faqActionLinkPrimary:
		"inline-flex items-center gap-2 min-h-[40px] px-4 py-2 rounded-full bg-slate-900 hover:bg-[#B91C1C] text-white text-xs sm:text-sm font-bold transition-colors focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#FF5A5F]/40",
	faqActionLinkSecondary:
		"inline-flex items-center gap-2 min-h-[40px] px-4 py-2 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-900 border border-slate-300 text-xs sm:text-sm font-bold transition-colors focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#FF5A5F]/40",
	ctaSection: "py-16 md:py-20 px-4 sm:px-6 max-w-6xl mx-auto mb-12",
	ctaCard:
		"bg-slate-900 text-white text-center relative overflow-hidden rounded-3xl py-14 px-6 sm:px-8 md:px-16 shadow-2xl border border-slate-800",
	ctaContent: "relative z-10 max-w-3xl mx-auto flex flex-col items-center",
	ctaTitle:
		"text-3xl md:text-4xl font-extrabold tracking-tight mb-4 text-white text-balance",
	ctaSubtitle:
		"text-base md:text-lg text-slate-200 mb-8 leading-relaxed max-w-[58ch] text-pretty",
	ctaActionsRow:
		"flex flex-col sm:flex-row flex-wrap items-center justify-center gap-4 w-full sm:w-auto",
	ctaPrimaryBtn:
		"w-full sm:w-auto inline-flex items-center justify-center gap-2.5 min-h-[48px] bg-coral-horizon-accessible hover:bg-coral-horizon-ink text-white px-8 py-4 rounded-full font-bold text-base md:text-lg shadow-xl transition-all duration-300 hover:scale-105 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-white/60",
	ctaSecondaryBtn:
		"w-full sm:w-auto inline-flex items-center justify-center gap-2.5 min-h-[48px] bg-slate-800/80 border border-slate-600 text-white hover:bg-slate-800 px-7 py-3.5 rounded-full font-semibold text-base transition-colors focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-white/40",
	ctaWhatsAppBtn:
		"w-full sm:w-auto inline-flex items-center justify-center gap-2.5 min-h-[48px] bg-slate-800/80 border border-[#047857] text-[#34D399] hover:bg-slate-800 px-7 py-3.5 rounded-full font-semibold text-base transition-colors focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-white/40",
} as const;

export const SHARED_BOOKING_STATUS_THEME = {
	pageWrapper:
		"min-h-screen flex flex-col bg-slate-900 font-sans text-slate-100 scroll-smooth",
	main: "flex-grow relative flex items-center justify-center pt-28 pb-16 px-6 overflow-hidden",
	bgWrapper: "absolute inset-0 w-full h-full",
	bgImage: "w-full h-full object-cover scale-105",
	bgOverlay:
		"absolute inset-0 bg-gradient-to-b from-slate-950/85 via-slate-900/70 to-slate-950/90 backdrop-blur-[3px]",
	cardWrapper: "relative z-10 max-w-xl w-full text-center",
	referenceBox:
		"hidden bg-slate-900/90 border border-slate-800 rounded-2xl p-4 mb-6 text-center",
	referenceLabel:
		"text-xs font-bold text-slate-400 tracking-wider uppercase block mb-1",
	referenceRow: "flex items-center justify-center space-x-3",
	copyBtn:
		"p-1.5 hover:bg-slate-800 rounded-lg text-slate-400 hover:text-slate-200 transition-colors cursor-pointer",
	copyIcon: "w-4 h-4",
} as const;

export const BOOKING_SUCCESS_THEME = {
	...SHARED_BOOKING_STATUS_THEME,
	card: "bg-slate-950/70 backdrop-blur-xl border border-teal-500/20 rounded-3xl p-8 md:p-12 shadow-2xl shadow-slate-950/50",
	badgeWrapper:
		"inline-flex p-4 bg-teal-500/10 rounded-full border border-teal-500/30 shadow-lg mb-6 animate-[pulse_2s_infinite]",
	badgeIcon: "w-16 h-16 text-teal-400",
	heading: "text-3xl md:text-4xl font-extrabold mb-4 tracking-tight",
	subtitle: "text-slate-300 font-light mb-6 leading-relaxed",
	referenceCode: "text-xl font-mono font-bold text-teal-300 select-all",
	copyFeedback: "hidden text-xs text-teal-400 font-semibold",
	registryNoticeBox:
		"bg-teal-500/10 border border-teal-500/25 rounded-2xl p-4 mb-8 text-left flex items-start space-x-3",
	registryNoticeIcon: "w-5 h-5 text-teal-400 shrink-0 mt-0.5",
	registryNoticeText: "text-xs md:text-sm text-slate-200 leading-relaxed",
	actionsWrapper: "flex flex-col gap-3 justify-center",
	primaryBtn:
		"flex items-center justify-center space-x-2 bg-gradient-to-r from-teal-500 to-emerald-500 hover:from-teal-600 hover:to-emerald-600 text-white px-6 py-4 rounded-2xl font-bold transition-transform duration-300 hover:scale-[1.02] shadow-lg shadow-teal-950/20",
	secondaryActionsRow: "flex flex-col sm:flex-row gap-3 justify-center",
	secondaryGuideBtn:
		"flex-1 flex items-center justify-center space-x-2 bg-white/10 hover:bg-white/20 border border-white/15 text-white px-5 py-3.5 rounded-2xl font-bold transition-transform duration-300 hover:scale-[1.02]",
	secondaryHomeBtn:
		"flex-1 flex items-center justify-center space-x-2 bg-white/5 hover:bg-white/15 border border-white/10 text-slate-200 px-5 py-3.5 rounded-2xl font-semibold transition-transform duration-300 hover:scale-[1.02]",
	btnIcon: "w-5 h-5",
	btnArrowIcon: "w-4 h-4 ml-1",
} as const;

export const BOOKING_PENDING_THEME = {
	...SHARED_BOOKING_STATUS_THEME,
	card: "bg-slate-950/70 backdrop-blur-xl border border-amber-500/20 rounded-3xl p-8 md:p-12 shadow-2xl shadow-slate-950/50",
	badgeWrapper:
		"inline-flex p-4 bg-amber-500/10 rounded-full border border-amber-500/30 shadow-lg mb-6 animate-[pulse_2.5s_infinite]",
	badgeIcon: "w-16 h-16 text-amber-400",
	heading:
		"text-3xl md:text-4xl font-extrabold mb-4 tracking-tight text-amber-100",
	subtitle: "text-slate-300 font-light mb-8 leading-relaxed",
	referenceCode: "text-xl font-mono font-bold text-amber-300 select-all",
	copyFeedback: "hidden text-xs text-amber-400 font-semibold",
	voucherBox:
		"hidden bg-amber-500/10 border border-amber-500/25 rounded-2xl p-5 mb-8 text-left space-y-3",
	voucherTitle: "text-sm font-bold text-amber-200 uppercase tracking-wider",
	voucherGrid: "grid grid-cols-1 sm:grid-cols-2 gap-3",
	voucherItem: "bg-slate-900/80 border border-slate-800 rounded-xl p-3",
	voucherItemLabel:
		"text-xs font-semibold text-slate-400 uppercase tracking-wide block mb-1",
	voucherItemValue:
		"text-sm font-mono font-bold text-amber-300 break-all select-all",
	printVoucherBtn:
		"hidden w-full items-center justify-center space-x-2 bg-amber-500/20 hover:bg-amber-500/30 border border-amber-400/40 text-amber-100 px-5 py-3 rounded-xl font-bold text-sm transition-colors",
	printVoucherIcon: "w-4 h-4",
	actionsWrapper: "flex justify-center",
	homeBtn:
		"flex items-center justify-center space-x-2 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white px-8 py-4 rounded-2xl font-bold transition-transform duration-300 hover:scale-[1.02] shadow-lg shadow-amber-950/20",
	homeIcon: "w-5 h-5 mr-1",
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
		imageDimensions: {
			hero: IMAGE_DIMENSIONS["1707"].hero,
			bedroom: IMAGE_DIMENSIONS["1707"].bedroom,
			pool: IMAGE_DIMENSIONS["1707"].pool,
		},

		// Airbnb target URL
		airbnbUrl: AIRBNB_URLS["1707"],

		// Centralized keys for property-specific copy & localized image alts
		bedTextKey: "statsBeds",
		heroTitleKey: "heroTitle",
		heroSubKey: "heroSub",
		heroImgAltKey: "hero1707ImgAlt",
		aboutBedroomAltKey: "about1707LivingAlt",
		aboutPoolAltKey: "about1707PoolAlt",

		// Property-specific description & identity keys
		aboutText1Key: "aboutText1",
		aboutText2Key: "aboutText2",
		rntNumber: DATA_CONTROLLER.rnt1707,

		// AboutAmenities
		aboutThemeAccent: "text-[#FF5A5F] bg-red-50",
		aboutHostAccent: "text-[#FF5A5F]",
		aboutHostBorder: "border-slate-700",
		aboutHostBg: "bg-slate-900 font-sans",
		aboutHostName: "text-white",
		aboutHostMetaRow: "mt-5 flex flex-wrap items-center gap-2.5",
		aboutHostControllerBadge:
			"inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-semibold bg-slate-800 text-slate-200 border border-slate-700",
		aboutHostRntBadge:
			"inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-bold bg-slate-800 text-emerald-300 border border-emerald-500/30",
		aboutHostComplianceRow: "mt-4 flex flex-wrap items-center gap-2",
		aboutHostComplianceBadge:
			"inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-slate-800/80 text-slate-200 border border-slate-700",
		aboutHostComplianceLink:
			"inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold text-slate-200 hover:text-white underline underline-offset-2 transition-colors",
		aboutHostActionsRow: "mt-6 flex flex-wrap items-center gap-3",
		aboutHostWhatsAppBtn:
			"inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold bg-emerald-500/15 hover:bg-emerald-500/25 text-emerald-300 border border-emerald-500/30 transition-colors focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-emerald-400/40",
		aboutHostEmailBtn:
			"inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold bg-slate-800 hover:bg-slate-700 text-slate-100 border border-slate-700 transition-colors focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-white/30",
		aboutHostIconSm: "w-4 h-4 shrink-0",

		// Guest Reviews
		reviewSection:
			"py-24 px-6 bg-slate-50 border-t border-slate-200/60 relative overflow-hidden",
		reviewGlow:
			"absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[500px] bg-[#FF5A5F]/5 rounded-full blur-3xl pointer-events-none",
		reviewContainer: "max-w-6xl mx-auto relative z-10",
		reviewTitle:
			"text-3xl font-bold mb-12 text-center text-slate-900 tracking-tight",
		reviewGrid: "grid grid-cols-1 md:grid-cols-2 gap-8 list-none p-0 m-0",
		reviewListItem: "flex",
		reviewCard:
			"bg-white/90 backdrop-blur-md p-8 rounded-2xl shadow-sm border border-slate-200/70 flex flex-col relative w-full",
		reviewQuoteIcon:
			"absolute top-6 right-6 w-8 h-8 text-slate-200/80 pointer-events-none select-none",
		reviewStarsRow: "flex text-amber-400 mb-4 space-x-1",
		reviewStarIcon: "w-5 h-5 fill-current",
		reviewText:
			"text-slate-600 mb-6 flex-grow italic leading-relaxed relative z-10",
		reviewAuthorRow: "flex items-center space-x-3",
		reviewAvatar:
			"w-10 h-10 bg-slate-100 text-slate-700 border border-slate-200 rounded-full flex items-center justify-center font-bold shrink-0",
		reviewAuthorDetails: "min-w-0",
		reviewAuthorName: "font-semibold text-slate-900 block",
		reviewMetaRow: "mt-1 flex flex-wrap items-center gap-2",
		reviewVerifiedBadge:
			"inline-flex items-center gap-1 text-xs font-semibold uppercase tracking-wider text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 rounded-full",
		reviewDate: "text-xs font-medium text-slate-500",
		reviewAttributionWrapper: "mt-10 flex justify-center",
		reviewAttributionLink:
			"inline-flex items-center gap-2 text-sm font-semibold text-slate-700 hover:text-rose-700 bg-white px-5 py-2.5 rounded-full border border-slate-200 shadow-xs hover:shadow-sm transition-all focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#FF5A5F]/30",
		reviewIconSm: "w-3.5 h-3.5 shrink-0",

		// PropertyFaq
		propertyFaqTitleKey: "faq1707Title",
		propertyFaqSubtitleKey: "faq1707Subtitle",
		propertyFaqSection: "py-24 px-6 bg-slate-50 border-t border-slate-200/80",
		propertyFaqContainer: "max-w-4xl mx-auto",
		propertyFaqHeader: "text-center max-w-2xl mx-auto mb-12",
		propertyFaqTitle:
			"text-3xl md:text-4xl font-extrabold tracking-tight text-slate-900 mb-3",
		propertyFaqSubtitle: "text-base md:text-lg text-slate-600 leading-relaxed",
		propertyFaqList: "space-y-4",
		propertyFaqDetails:
			"group bg-white rounded-2xl border border-slate-200 shadow-xs open:shadow-md transition-shadow",
		propertyFaqSummary:
			"flex items-center justify-between gap-4 p-6 md:p-7 cursor-pointer list-none [&::-webkit-details-marker]:hidden font-bold text-base md:text-lg text-slate-900 select-none rounded-2xl focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#FF5A5F]/40",
		propertyFaqChevron:
			"w-5 h-5 text-slate-500 shrink-0 motion-safe:transition-transform motion-safe:duration-300 group-open:rotate-180",
		propertyFaqAnswerWrapper:
			"px-6 pb-6 md:px-7 md:pb-7 pt-4 border-t border-slate-100",
		propertyFaqAnswer: "text-slate-700 leading-relaxed",

		// PropertyDestinationGuides
		propertyGuidesTitleKey: "property1707GuidesTitle",
		propertyGuidesSubtitleKey: "property1707GuidesSubtitle",
		propertyGuidesSection: "py-16 px-6 bg-white border-t border-slate-200/80",
		propertyGuidesContainer: "max-w-5xl mx-auto",
		propertyGuidesCard:
			"bg-slate-50 rounded-3xl p-8 md:p-10 border border-slate-200 shadow-xs",
		propertyGuidesHeader: "mb-6",
		propertyGuidesEyebrow:
			"text-xs font-bold uppercase tracking-wider text-slate-600 mb-2",
		propertyGuidesTitle:
			"text-2xl md:text-3xl font-extrabold tracking-tight text-slate-900 mb-2",
		propertyGuidesSubtitle:
			"text-sm md:text-base text-slate-700 leading-relaxed",
		propertyGuidesLinksRow: "flex flex-wrap items-center gap-3",
		propertyGuidesLink:
			"inline-flex items-center gap-2 min-h-[44px] px-5 py-2.5 rounded-full bg-white border border-slate-300 text-sm font-semibold text-slate-900 hover:border-slate-900 hover:bg-slate-900 hover:text-white transition-colors focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#FF5A5F]/40",
		propertyGuidesHubLink:
			"inline-flex items-center gap-2 min-h-[44px] px-5 py-2.5 rounded-full bg-slate-900 text-white hover:bg-slate-800 text-sm font-bold transition-colors focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#FF5A5F]/40",
		propertyGuidesIcon: "w-4 h-4 shrink-0",

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
		bookingMinStayWarning:
			"hidden mt-4 p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-xs font-semibold leading-relaxed",

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
		imageDimensions: {
			hero: IMAGE_DIMENSIONS["1606"].hero,
			bedroom: IMAGE_DIMENSIONS["1606"].bedroom,
			pool: IMAGE_DIMENSIONS["1606"].pool,
		},

		// Airbnb target URL
		airbnbUrl: AIRBNB_URLS["1606"],

		// Centralized keys for property-specific copy & localized image alts
		bedTextKey: "stats1606Beds",
		heroTitleKey: "hero1606Title",
		heroSubKey: "hero1606Sub",
		heroImgAltKey: "hero1606ImgAlt",
		aboutBedroomAltKey: "about1606LivingAlt",
		aboutPoolAltKey: "about1606PoolAlt",

		// Property-specific description & identity keys
		aboutText1Key: "about1606Text1",
		aboutText2Key: "about1606Text2",
		rntNumber: DATA_CONTROLLER.rnt1606,

		// AboutAmenities
		aboutThemeAccent: "text-rose-600 bg-rose-50",
		aboutHostAccent: "text-rose-400",
		aboutHostBorder: "border-stone-700",
		aboutHostBg: "bg-stone-900 font-sans",
		aboutHostName: "text-stone-50",
		aboutHostMetaRow: "mt-5 flex flex-wrap items-center gap-2.5",
		aboutHostControllerBadge:
			"inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-semibold bg-stone-800 text-stone-200 border border-stone-700",
		aboutHostRntBadge:
			"inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-bold bg-stone-800 text-emerald-300 border border-emerald-500/30",
		aboutHostComplianceRow: "mt-4 flex flex-wrap items-center gap-2",
		aboutHostComplianceBadge:
			"inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-stone-800/80 text-stone-200 border border-stone-700",
		aboutHostComplianceLink:
			"inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold text-stone-200 hover:text-white underline underline-offset-2 transition-colors",
		aboutHostActionsRow: "mt-6 flex flex-wrap items-center gap-3",
		aboutHostWhatsAppBtn:
			"inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold bg-emerald-500/15 hover:bg-emerald-500/25 text-emerald-300 border border-emerald-500/30 transition-colors focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-emerald-400/40",
		aboutHostEmailBtn:
			"inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold bg-stone-800 hover:bg-stone-700 text-stone-100 border border-stone-700 transition-colors focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-white/30",
		aboutHostIconSm: "w-4 h-4 shrink-0",

		// Guest Reviews
		reviewSection: "py-24 px-6 bg-stone-50 border-t border-stone-200",
		reviewGlow: "hidden",
		reviewContainer: "max-w-6xl mx-auto",
		reviewTitle: "text-3xl font-bold mb-12 text-center text-stone-900",
		reviewGrid: "grid grid-cols-1 md:grid-cols-2 gap-8 list-none p-0 m-0",
		reviewListItem: "flex",
		reviewCard:
			"bg-white p-8 rounded-2xl shadow-sm border border-stone-100 flex flex-col relative w-full",
		reviewQuoteIcon:
			"absolute top-6 right-6 w-8 h-8 text-stone-100 pointer-events-none select-none",
		reviewStarsRow: "flex text-amber-400 mb-4 space-x-1",
		reviewStarIcon: "w-5 h-5 fill-current",
		reviewText:
			"text-stone-600 mb-6 flex-grow italic leading-relaxed relative z-10",
		reviewAuthorRow: "flex items-center space-x-3",
		reviewAvatar:
			"w-10 h-10 bg-rose-100 text-rose-700 rounded-full flex items-center justify-center font-bold shrink-0",
		reviewAuthorDetails: "min-w-0",
		reviewAuthorName: "font-semibold text-stone-900 block",
		reviewMetaRow: "mt-1 flex flex-wrap items-center gap-2",
		reviewVerifiedBadge:
			"inline-flex items-center gap-1 text-xs font-semibold uppercase tracking-wider text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 rounded-full",
		reviewDate: "text-xs font-medium text-stone-500",
		reviewAttributionWrapper: "mt-10 flex justify-center",
		reviewAttributionLink:
			"inline-flex items-center gap-2 text-sm font-semibold text-stone-700 hover:text-rose-700 bg-white px-5 py-2.5 rounded-full border border-stone-200 shadow-xs hover:shadow-sm transition-all focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-rose-500/30",
		reviewIconSm: "w-3.5 h-3.5 shrink-0",

		// PropertyFaq
		propertyFaqTitleKey: "faq1606Title",
		propertyFaqSubtitleKey: "faq1606Subtitle",
		propertyFaqSection: "py-24 px-6 bg-stone-50 border-t border-stone-200/80",
		propertyFaqContainer: "max-w-4xl mx-auto",
		propertyFaqHeader: "text-center max-w-2xl mx-auto mb-12",
		propertyFaqTitle:
			"text-3xl md:text-4xl font-extrabold tracking-tight text-stone-900 mb-3",
		propertyFaqSubtitle: "text-base md:text-lg text-stone-600 leading-relaxed",
		propertyFaqList: "space-y-4",
		propertyFaqDetails:
			"group bg-white rounded-2xl border border-stone-200 shadow-xs open:shadow-md transition-shadow",
		propertyFaqSummary:
			"flex items-center justify-between gap-4 p-6 md:p-7 cursor-pointer list-none [&::-webkit-details-marker]:hidden font-bold text-base md:text-lg text-stone-900 select-none rounded-2xl focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-rose-500/40",
		propertyFaqChevron:
			"w-5 h-5 text-stone-500 shrink-0 motion-safe:transition-transform motion-safe:duration-300 group-open:rotate-180",
		propertyFaqAnswerWrapper:
			"px-6 pb-6 md:px-7 md:pb-7 pt-4 border-t border-stone-100",
		propertyFaqAnswer: "text-stone-700 leading-relaxed",

		// PropertyDestinationGuides
		propertyGuidesTitleKey: "property1606GuidesTitle",
		propertyGuidesSubtitleKey: "property1606GuidesSubtitle",
		propertyGuidesSection: "py-16 px-6 bg-white border-t border-stone-200/80",
		propertyGuidesContainer: "max-w-5xl mx-auto",
		propertyGuidesCard:
			"bg-stone-50 rounded-3xl p-8 md:p-10 border border-stone-200 shadow-xs",
		propertyGuidesHeader: "mb-6",
		propertyGuidesEyebrow:
			"text-xs font-bold uppercase tracking-wider text-stone-600 mb-2",
		propertyGuidesTitle:
			"text-2xl md:text-3xl font-extrabold tracking-tight text-stone-900 mb-2",
		propertyGuidesSubtitle:
			"text-sm md:text-base text-stone-700 leading-relaxed",
		propertyGuidesLinksRow: "flex flex-wrap items-center gap-3",
		propertyGuidesLink:
			"inline-flex items-center gap-2 min-h-[44px] px-5 py-2.5 rounded-full bg-white border border-stone-300 text-sm font-semibold text-stone-900 hover:border-rose-700 hover:bg-rose-700 hover:text-white transition-colors focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-rose-500/40",
		propertyGuidesHubLink:
			"inline-flex items-center gap-2 min-h-[44px] px-5 py-2.5 rounded-full bg-stone-900 text-white hover:bg-stone-800 text-sm font-bold transition-colors focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-rose-500/40",
		propertyGuidesIcon: "w-4 h-4 shrink-0",

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
		bookingMinStayWarning:
			"hidden mt-4 p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-xs font-semibold leading-relaxed",

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

export const DESTINATION_GUIDES_THEME = {
	pageWrapper:
		"min-h-screen flex flex-col bg-slate-50 font-sans text-slate-800 scroll-smooth",
	heroHeader:
		"relative bg-slate-900 pt-32 pb-16 md:pt-36 md:pb-20 px-6 text-center overflow-hidden",
	heroMediaWrapper: "absolute inset-0 w-full h-full",
	heroImage: "w-full h-full object-cover",
	heroOverlay:
		"absolute inset-0 bg-gradient-to-b from-slate-900/80 via-slate-900/65 to-slate-900/90",
	heroContent:
		"relative z-10 max-w-4xl mx-auto mt-4 flex flex-col items-center",
	heroBadge:
		"inline-flex items-center gap-2 bg-white/15 border border-white/20 backdrop-blur-md px-4 py-1.5 rounded-full text-white text-xs font-bold tracking-wider uppercase mb-5",
	heroTitle:
		"text-3xl md:text-5xl font-extrabold mb-4 text-white tracking-tight leading-tight drop-shadow-md",
	heroSubtitle:
		"text-base md:text-xl text-slate-200 font-light max-w-3xl mx-auto leading-relaxed drop-shadow",
	heroMetaRow:
		"mt-6 flex flex-wrap items-center justify-center gap-x-4 gap-y-2 text-xs md:text-sm text-slate-200 bg-slate-900/65 border border-white/15 backdrop-blur-md px-5 py-2.5 rounded-full",
	heroMetaItem: "inline-flex items-center gap-1.5",
	heroMetaDivider: "text-slate-400",
	main: "flex-grow w-full py-12 md:py-16 px-6 max-w-5xl mx-auto space-y-12",
	introCard:
		"bg-white rounded-3xl p-8 md:p-10 shadow-sm border border-slate-200",
	introHeading:
		"text-2xl md:text-3xl font-extrabold tracking-tight text-slate-900 mb-4",
	introBody: "text-base md:text-lg text-slate-700 leading-relaxed",
	sectionHeader: "mb-8",
	sectionTitle:
		"text-2xl md:text-3xl font-extrabold tracking-tight text-slate-900 mb-2",
	sectionSubtitle: "text-base text-slate-600 leading-relaxed",
	guidesGrid: "grid grid-cols-1 md:grid-cols-2 gap-8",
	guideCard:
		"group flex flex-col bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden hover:shadow-xl hover:-translate-y-1 transition-all duration-300 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#FF5A5F]/40",
	guideCardMedia: "h-56 relative overflow-hidden bg-slate-900",
	guideCardImage:
		"w-full h-full object-cover group-hover:scale-105 transition-transform duration-700",
	guideCardOverlay:
		"absolute inset-0 bg-gradient-to-t from-slate-900/75 via-slate-900/20 to-transparent",
	guideCardBadge:
		"absolute top-4 left-4 inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-bold tracking-wider uppercase bg-slate-900/85 text-white border border-white/20 backdrop-blur-md",
	guideCardBody: "flex-grow flex flex-col justify-between p-7",
	guideCardMeta:
		"flex items-center gap-3 text-xs font-semibold text-slate-500 mb-2",
	guideCardTitle:
		"text-xl md:text-2xl font-bold text-slate-900 mb-3 group-hover:text-rose-700 transition-colors",
	guideCardSummary: "text-sm md:text-base text-slate-700 leading-relaxed mb-6",
	guideCardCtaRow:
		"pt-4 border-t border-slate-100 flex items-center justify-between text-sm font-bold text-rose-700 group-hover:text-slate-900 transition-colors",
	guideWrapper: "space-y-10",
	takeawaysCard:
		"bg-slate-900 text-white rounded-3xl p-8 md:p-10 border border-slate-800 shadow-md",
	takeawaysTitle: "text-xl md:text-2xl font-extrabold text-white mb-5",
	takeawaysList: "space-y-3",
	takeawaysItem:
		"flex items-start gap-3 text-sm md:text-base text-slate-200 leading-relaxed",
	takeawaysIcon: "w-5 h-5 text-[#FF5A5F] shrink-0 mt-0.5",
	sectionCard:
		"bg-white rounded-3xl p-8 md:p-10 shadow-sm border border-slate-200 space-y-5",
	sectionHeading:
		"text-2xl md:text-3xl font-extrabold tracking-tight text-slate-900",
	sectionParagraph: "text-base text-slate-700 leading-relaxed",
	sectionBulletList: "space-y-3 pt-1",
	sectionBulletItem:
		"flex items-start gap-3 text-sm md:text-base text-slate-700 leading-relaxed",
	sectionBulletIcon: "w-5 h-5 text-rose-700 shrink-0 mt-0.5",
	sectionCallout:
		"p-5 rounded-2xl bg-slate-50 border border-slate-200 text-slate-800 text-sm md:text-base leading-relaxed",
	sectionCalloutLabel:
		"block text-xs font-bold uppercase tracking-wider text-rose-700 mb-1",
	factGrid: "grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 pt-2",
	factCard: "p-5 rounded-2xl bg-slate-50 border border-slate-200",
	factLabel: "text-xs font-bold uppercase tracking-wider text-slate-500 mb-1",
	factValue: "text-lg font-extrabold text-slate-900",
	factDetail: "text-xs text-slate-600 mt-1 leading-relaxed",
	tableWrapper:
		"overflow-x-auto rounded-2xl border border-slate-200 bg-white focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#FF5A5F]/40",
	table:
		"w-full min-w-[540px] border-separate border-spacing-0 text-left text-sm md:text-base",
	tableCaption:
		"px-5 py-3 text-left text-xs font-semibold tracking-wide text-slate-600 bg-slate-50 border-b border-slate-200",
	tableHead: "bg-slate-900 text-white",
	tableHeaderCell:
		"py-3.5 px-5 text-xs md:text-sm font-bold uppercase tracking-wider text-white border-b border-slate-800",
	tableBody: "bg-white",
	tableRow: "odd:bg-white even:bg-slate-50",
	tableRowHeader:
		"py-3.5 px-5 font-semibold text-slate-900 align-top border-b border-slate-200",
	tableCell:
		"py-3.5 px-5 text-slate-700 align-top border-b border-slate-200 leading-relaxed",
	faqSection: "space-y-6",
	faqTitle: "text-2xl md:text-3xl font-extrabold tracking-tight text-slate-900",
	faqList: "space-y-4",
	faqDetails:
		"group bg-white rounded-2xl border border-slate-200 shadow-xs open:shadow-md transition-shadow",
	faqSummary:
		"flex items-center justify-between gap-4 p-6 cursor-pointer list-none [&::-webkit-details-marker]:hidden font-bold text-base md:text-lg text-slate-900 select-none rounded-2xl focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#FF5A5F]/40",
	faqChevron:
		"w-5 h-5 text-slate-500 shrink-0 motion-safe:transition-transform motion-safe:duration-300 group-open:rotate-180",
	faqAnswerWrapper: "px-6 pb-6 pt-4 border-t border-slate-100",
	faqAnswer: "text-slate-700 leading-relaxed",
	propertyCtaSection:
		"bg-slate-900 text-white rounded-3xl p-8 md:p-10 border border-slate-800 shadow-xl space-y-6",
	propertyCtaHeader: "max-w-3xl",
	propertyCtaEyebrow:
		"text-xs font-bold uppercase tracking-wider text-rose-300 mb-2",
	propertyCtaTitle:
		"text-2xl md:text-3xl font-extrabold tracking-tight text-white mb-2",
	propertyCtaSubtitle: "text-sm md:text-base text-slate-200 leading-relaxed",
	propertyCtaGrid: "grid grid-cols-1 md:grid-cols-2 gap-6",
	propertyCtaCard:
		"flex flex-col justify-between bg-slate-800/90 rounded-2xl p-6 border border-slate-700",
	propertyCtaCardBadge:
		"inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-slate-900 text-slate-200 border border-slate-700 mb-3 self-start",
	propertyCtaCardTitle: "text-xl font-bold text-white mb-2",
	propertyCtaCardDesc: "text-sm text-slate-200 leading-relaxed mb-4",
	propertyCtaPriceRow: "space-y-1 mb-5",
	propertyCtaRateLine: "text-sm font-bold text-emerald-300",
	propertyCtaCleaningLine: "text-xs text-slate-300",
	propertyCtaButton:
		"inline-flex items-center justify-center gap-2 min-h-[44px] w-full bg-white hover:bg-slate-100 text-slate-900 px-5 py-3 rounded-full text-sm font-bold shadow-md transition-colors focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-white/40",
	relatedSection: "space-y-6 pt-4 border-t border-slate-200",
	relatedLinksRow: "flex flex-wrap items-center gap-3",
	relatedLink:
		"inline-flex items-center gap-2 min-h-[44px] px-5 py-2.5 rounded-full bg-white border border-slate-300 text-sm font-semibold text-slate-900 hover:bg-slate-900 hover:text-white hover:border-slate-900 transition-colors focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#FF5A5F]/40",
	backToHubLink:
		"inline-flex items-center gap-2 min-h-[44px] px-5 py-2.5 rounded-full bg-slate-900 text-white hover:bg-slate-800 text-sm font-bold transition-colors focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#FF5A5F]/40",
	iconXs: "w-3.5 h-3.5 shrink-0",
	iconSm: "w-4 h-4 shrink-0",
	iconMd: "w-5 h-5 shrink-0",
} as const;
