import {
	ArrowRight,
	Building2,
	Compass,
	MapPin,
	ShieldCheck,
	Sparkles,
	Waves,
	Wifi,
} from "lucide-react";
import type React from "react";
import { getPropertyPagePath, type PropertyId } from "../constants/config";
import { DESTINATION_GUIDES_THEME } from "../constants/theme";
import {
	DESTINATION_GUIDES_HUB_COPY,
	type DestinationGuideIconId,
	formatCopCurrencyForLocale,
	interpolateGuideText,
} from "../i18n/destinationGuides";
import { dict } from "../i18n/dict";
import type { Lang, PortfolioPricingSummary } from "../types";

export const GUIDE_ICONS: Record<
	DestinationGuideIconId,
	React.ComponentType<{ className?: string; "aria-hidden"?: boolean }>
> = {
	waves: Waves,
	wifi: Wifi,
	compass: Compass,
	shield: ShieldCheck,
	"map-pin": MapPin,
	sparkles: Sparkles,
	building: Building2,
};

const PROPERTY_CTA_SPECS: Record<
	PropertyId,
	{
		titleProp: "heroTitle" | "hero1606Title";
		badgeProp: "homeCard1707Badge" | "homeCard1606Badge";
		pitchProp: "property1707Pitch" | "property1606Pitch";
	}
> = {
	"1707": {
		titleProp: "heroTitle",
		badgeProp: "homeCard1707Badge",
		pitchProp: "property1707Pitch",
	},
	"1606": {
		titleProp: "hero1606Title",
		badgeProp: "homeCard1606Badge",
		pitchProp: "property1606Pitch",
	},
};

export interface DestinationGuidePropertyCtaProps {
	sectionId: string;
	headingId: string;
	eyebrow: string;
	title: string;
	subtitle: string;
	buttonLabel: string;
	guideSlug: string;
	propertyIds: readonly PropertyId[];
	lang: Lang;
	assetPrefix: string;
	pricingSummary: PortfolioPricingSummary;
}

export function DestinationGuidePropertyCta({
	sectionId,
	headingId,
	eyebrow,
	title,
	subtitle,
	buttonLabel,
	guideSlug,
	propertyIds,
	lang,
	assetPrefix,
	pricingSummary,
}: DestinationGuidePropertyCtaProps) {
	const t = dict[lang];
	const hubCopy = DESTINATION_GUIDES_HUB_COPY[lang];

	return (
		<section
			id={sectionId}
			aria-labelledby={headingId}
			className={DESTINATION_GUIDES_THEME.propertyCtaSection}
		>
			<div className={DESTINATION_GUIDES_THEME.propertyCtaHeader}>
				<p className={DESTINATION_GUIDES_THEME.propertyCtaEyebrow}>{eyebrow}</p>
				<h2
					id={headingId}
					className={DESTINATION_GUIDES_THEME.propertyCtaTitle}
				>
					{title}
				</h2>
				<p className={DESTINATION_GUIDES_THEME.propertyCtaSubtitle}>
					{subtitle}
				</p>
			</div>
			<div className={DESTINATION_GUIDES_THEME.propertyCtaGrid}>
				{propertyIds.map((propertyId) => {
					const spec = PROPERTY_CTA_SPECS[propertyId];
					const propPricing = pricingSummary.properties[propertyId];
					const rateLine = interpolateGuideText(
						hubCopy.propertyRateLineTemplate,
						{
							rate: formatCopCurrencyForLocale(propPricing.lowPrice, lang),
							minStay: String(propPricing.minStay),
						},
					);
					const cleaningLine = interpolateGuideText(
						hubCopy.propertyCleaningLineTemplate,
						{
							cleaningFee: formatCopCurrencyForLocale(
								propPricing.cleaningFeeCop,
								lang,
							),
						},
					);
					return (
						<div
							key={propertyId}
							className={DESTINATION_GUIDES_THEME.propertyCtaCard}
						>
							<div>
								<span className={DESTINATION_GUIDES_THEME.propertyCtaCardBadge}>
									{t[spec.badgeProp]}
								</span>
								<h3 className={DESTINATION_GUIDES_THEME.propertyCtaCardTitle}>
									{t[spec.titleProp]}
								</h3>
								<p className={DESTINATION_GUIDES_THEME.propertyCtaCardDesc}>
									{hubCopy[spec.pitchProp]}
								</p>
								<div className={DESTINATION_GUIDES_THEME.propertyCtaPriceRow}>
									<p className={DESTINATION_GUIDES_THEME.propertyCtaRateLine}>
										{rateLine}
									</p>
									<p
										className={DESTINATION_GUIDES_THEME.propertyCtaCleaningLine}
									>
										{cleaningLine}
									</p>
								</div>
							</div>
							<a
								href={getPropertyPagePath(propertyId, lang, assetPrefix)}
								data-guide-cta-property={propertyId}
								data-guide-slug={guideSlug}
								className={DESTINATION_GUIDES_THEME.propertyCtaButton}
							>
								<span>{buttonLabel}</span>
								<ArrowRight
									className={DESTINATION_GUIDES_THEME.iconSm}
									aria-hidden="true"
								/>
							</a>
						</div>
					);
				})}
			</div>
		</section>
	);
}
