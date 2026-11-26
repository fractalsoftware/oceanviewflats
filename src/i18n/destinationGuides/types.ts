import type { PropertyId } from "../../constants/config";
import type { Lang } from "../../types";

export type DestinationGuideIconId =
	| "waves"
	| "wifi"
	| "compass"
	| "shield"
	| "map-pin"
	| "sparkles"
	| "building";

export interface DestinationGuideComparisonTable {
	readonly caption: string;
	readonly headers: readonly string[];
	readonly rows: readonly (readonly string[])[];
}

export interface DestinationGuideFactCard {
	readonly label: string;
	readonly value: string;
	readonly detail?: string;
}

export interface DestinationGuideCallout {
	readonly label: string;
	readonly text: string;
}

export interface DestinationGuideSection {
	readonly id: string;
	readonly heading: string;
	readonly paragraphs: readonly string[];
	readonly bullets?: readonly string[];
	readonly callout?: DestinationGuideCallout;
	readonly comparisonTable?: DestinationGuideComparisonTable;
	readonly factCards?: readonly DestinationGuideFactCard[];
}

export interface DestinationGuideFaqItem {
	readonly id: string;
	readonly question: string;
	readonly answer: string;
	readonly defaultOpen?: boolean;
}

export interface DestinationGuideLocaleContent {
	readonly seoTitle: string;
	readonly seoDescription: string;
	readonly ogImageAlt: string;
	readonly heroImageAlt: string;
	readonly breadcrumbLabel: string;
	readonly badge: string;
	readonly title: string;
	readonly subtitle: string;
	readonly cardSummary: string;
	readonly keyTakeawaysTitle: string;
	readonly keyTakeaways: readonly string[];
	readonly sections: readonly DestinationGuideSection[];
	readonly faqsTitle: string;
	readonly faqs: readonly DestinationGuideFaqItem[];
	readonly ctaTitle: string;
	readonly ctaSubtitle: string;
}

export interface DestinationGuideDefinition {
	readonly slug: string;
	readonly id: string;
	readonly categoryIcon: DestinationGuideIconId;
	readonly heroImage: string;
	readonly heroImageDimensions: {
		readonly width: number;
		readonly height: number;
	};
	readonly ogImage: {
		readonly path: string;
		readonly width: number;
		readonly height: number;
	};
	readonly datePublished: string;
	readonly dateModified: string;
	readonly readingTimeMinutes: number;
	readonly relatedProperties: readonly PropertyId[];
	readonly relatedGuideSlugs?: readonly string[];
	readonly locales: Record<Lang, DestinationGuideLocaleContent>;
}

export interface DestinationGuidesHubLocaleContent {
	readonly seoTitle: string;
	readonly seoDescription: string;
	readonly ogImageAlt: string;
	readonly heroImageAlt: string;
	readonly breadcrumbLabel: string;
	readonly badge: string;
	readonly heroTitle: string;
	readonly heroSubtitle: string;
	readonly introTitle: string;
	readonly introBody: string;
	readonly guidesSectionTitle: string;
	readonly guidesSectionSubtitle: string;
	readonly readGuideCta: string;
	readonly readingTimeFormat: string;
	readonly publishedLabel: string;
	readonly updatedLabel: string;
	readonly authorByline: string;
	readonly propertiesCtaTitle: string;
	readonly propertiesCtaSubtitle: string;
	readonly property1707Pitch: string;
	readonly property1606Pitch: string;
	readonly propertyRateLineTemplate: string;
	readonly propertyCleaningLineTemplate: string;
	readonly propertyCtaButton: string;
	readonly guidePropertyCtaEyebrow: string;
	readonly guidePropertyCtaButton: string;
	readonly relatedGuidesTitle: string;
	readonly backToHubLabel: string;
}
