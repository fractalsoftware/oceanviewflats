export type Lang = "en" | "es" | "fr" | "it" | "de" | "ja";

export type ActiveNavPage =
	| "home"
	| "1707"
	| "1606"
	| "guides"
	| "guide-article"
	| "contact"
	| "privacy";

export interface SeasonalRateTierRow {
	property_id: string;
	start_date: string;
	end_date: string;
	nightly_rate_cop: number;
	minimum_stay: number;
}

export interface PropertyPricingSummary {
	propertyId: "1707" | "1606";
	lowPrice: number;
	highPrice: number;
	baselinePrice: number;
	cleaningFeeCop: number;
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
	properties: Record<"1707" | "1606", PropertyPricingSummary>;
}

export interface AppProps {
	lang: Lang;
	assetPrefix?: string;
	pricingSummary?: PortfolioPricingSummary;
}

export interface CalendarWidgetProps {
	lang: Lang;
	checkIn: Date | null;
	checkOut: Date | null;
	onSelectDate: (date: Date) => void;
	propertyId?: "1707" | "1606";
}
