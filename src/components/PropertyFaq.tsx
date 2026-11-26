import { ChevronDown } from "lucide-react";
import React from "react";
import { PROPERTY_FAQ_ITEMS, type PropertyId } from "../constants/config";
import { THEMES } from "../constants/theme";
import { dict } from "../i18n/dict";
import type { Lang } from "../types";

export const PropertyFaq = ({
	lang,
	propertyId,
}: {
	lang: Lang;
	propertyId: PropertyId;
}) => {
	const t = dict[lang];
	const theme = THEMES[propertyId];
	const faqItems = PROPERTY_FAQ_ITEMS[propertyId];

	return (
		<section
			id="property-faq"
			aria-labelledby="property-faq-heading"
			className={theme.propertyFaqSection}
		>
			<div className={theme.propertyFaqContainer}>
				<div className={theme.propertyFaqHeader}>
					<h2 id="property-faq-heading" className={theme.propertyFaqTitle}>
						{t[theme.propertyFaqTitleKey]}
					</h2>
					<p className={theme.propertyFaqSubtitle}>
						{t[theme.propertyFaqSubtitleKey]}
					</p>
				</div>
				<div className={theme.propertyFaqList}>
					{faqItems.map((item) => (
						<details
							key={item.id}
							id={item.id}
							className={theme.propertyFaqDetails}
							open={item.defaultOpen}
						>
							<summary className={theme.propertyFaqSummary}>
								<span>{t[item.qKey]}</span>
								<ChevronDown
									className={theme.propertyFaqChevron}
									aria-hidden="true"
								/>
							</summary>
							<div className={theme.propertyFaqAnswerWrapper}>
								<p className={theme.propertyFaqAnswer}>{t[item.aKey]}</p>
							</div>
						</details>
					))}
				</div>
			</div>
		</section>
	);
};
