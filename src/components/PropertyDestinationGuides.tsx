import { ArrowRight, Compass } from "lucide-react";
import React from "react";
import {
	getDestinationGuidesHubPath,
	PROPERTY_CONTEXTUAL_GUIDE_LINKS,
	type PropertyId,
} from "../constants/config";
import { THEMES } from "../constants/theme";
import {
	getDestinationGuideBySlug,
	resolveDestinationGuideHref,
} from "../i18n/destinationGuides";
import { dict } from "../i18n/dict";
import type { Lang } from "../types";

export const PropertyDestinationGuides = ({
	lang,
	propertyId,
	assetPrefix = "../",
}: {
	lang: Lang;
	propertyId: PropertyId;
	assetPrefix?: string;
}) => {
	const t = dict[lang];
	const theme = THEMES[propertyId];
	const contextualGuides = PROPERTY_CONTEXTUAL_GUIDE_LINKS[propertyId];
	const hubHref = getDestinationGuidesHubPath(lang, assetPrefix);

	return (
		<section
			id="property-destination-guides"
			aria-labelledby="property-destination-guides-heading"
			className={theme.propertyGuidesSection}
		>
			<div className={theme.propertyGuidesContainer}>
				<div className={theme.propertyGuidesCard}>
					<div className={theme.propertyGuidesHeader}>
						<p className={theme.propertyGuidesEyebrow}>
							{t.propertyGuidesEyebrow}
						</p>
						<h2
							id="property-destination-guides-heading"
							className={theme.propertyGuidesTitle}
						>
							{t[theme.propertyGuidesTitleKey]}
						</h2>
						<p className={theme.propertyGuidesSubtitle}>
							{t[theme.propertyGuidesSubtitleKey]}
						</p>
					</div>
					<div className={theme.propertyGuidesLinksRow}>
						{contextualGuides.map((guideRef) => {
							const publishedGuide = getDestinationGuideBySlug(guideRef.slug);
							const label = publishedGuide
								? publishedGuide.locales[lang].breadcrumbLabel
								: t[guideRef.labelKey];
							return (
								<a
									key={guideRef.slug}
									href={resolveDestinationGuideHref(
										guideRef.slug,
										lang,
										assetPrefix,
									)}
									className={theme.propertyGuidesLink}
								>
									<Compass
										className={theme.propertyGuidesIcon}
										aria-hidden="true"
									/>
									<span>{label}</span>
									<ArrowRight
										className={theme.propertyGuidesIcon}
										aria-hidden="true"
									/>
								</a>
							);
						})}
						<a href={hubHref} className={theme.propertyGuidesHubLink}>
							<span>{t.propertyGuidesHubCta}</span>
							<ArrowRight
								className={theme.propertyGuidesIcon}
								aria-hidden="true"
							/>
						</a>
					</div>
				</div>
			</div>
		</section>
	);
};
