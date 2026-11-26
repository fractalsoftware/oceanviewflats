import {
	ArrowLeft,
	ArrowRight,
	Building2,
	Calendar,
	CheckCircle2,
	ChevronDown,
	Clock,
	Compass,
	MapPin,
	ShieldCheck,
	Sparkles,
	Waves,
	Wifi,
} from "lucide-react";
import type React from "react";
import { Breadcrumbs } from "../components/Breadcrumbs";
import { Footer } from "../components/Footer";
import { Navigation } from "../components/Navigation";
import {
	getDestinationGuidePath,
	getDestinationGuidesHubPath,
	getHeroImageSrcSet,
	getPropertyPagePath,
	HERO_IMAGE_SIZES,
	type PropertyId,
} from "../constants/config";
import { DESTINATION_GUIDES_THEME } from "../constants/theme";
import {
	buildGuidePricingTokens,
	DESTINATION_GUIDES,
	DESTINATION_GUIDES_HUB_COPY,
	type DestinationGuideDefinition,
	type DestinationGuideIconId,
	formatCopCurrencyForLocale,
	interpolateGuideText,
} from "../i18n/destinationGuides";
import { dict } from "../i18n/dict";
import type { AppProps } from "../types";
import { DEFAULT_PRICING_SUMMARY } from "../utils/schema";

const GUIDE_ICONS: Record<
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
		titleKey: "heroTitle" | "hero1606Title";
		badgeKey: "homeCard1707Badge" | "homeCard1606Badge";
		pitchKey: "property1707Pitch" | "property1606Pitch";
	}
> = {
	"1707": {
		titleKey: "heroTitle",
		badgeKey: "homeCard1707Badge",
		pitchKey: "property1707Pitch",
	},
	"1606": {
		titleKey: "hero1606Title",
		badgeKey: "homeCard1606Badge",
		pitchKey: "property1606Pitch",
	},
};

export interface DestinationGuideArticleProps extends AppProps {
	guide: DestinationGuideDefinition;
}

export default function DestinationGuideArticle({
	guide,
	lang,
	assetPrefix = "../../",
	pricingSummary = DEFAULT_PRICING_SUMMARY,
}: DestinationGuideArticleProps) {
	const t = dict[lang];
	const hubCopy = DESTINATION_GUIDES_HUB_COPY[lang];
	const content = guide.locales[lang];
	const tokens = buildGuidePricingTokens(pricingSummary, lang);
	const BadgeIcon = GUIDE_ICONS[guide.categoryIcon];

	const hubHref = getDestinationGuidesHubPath(lang, assetPrefix);
	const currentHref = getDestinationGuidePath(guide.slug, lang, assetPrefix);
	const heroSrcSet = getHeroImageSrcSet(guide.heroImage, assetPrefix);

	const resolvedTitle = interpolateGuideText(content.title, tokens);
	const resolvedSubtitle = interpolateGuideText(content.subtitle, tokens);
	const resolvedCtaTitle = interpolateGuideText(content.ctaTitle, tokens);
	const resolvedCtaSubtitle = interpolateGuideText(content.ctaSubtitle, tokens);

	const relatedGuides = (guide.relatedGuideSlugs ?? [])
		.map((slug) => DESTINATION_GUIDES.find((item) => item.slug === slug))
		.filter(
			(item): item is DestinationGuideDefinition =>
				item !== undefined && item.slug !== guide.slug,
		);

	return (
		<div className={DESTINATION_GUIDES_THEME.pageWrapper}>
			<Navigation
				isScrolled={false}
				lang={lang}
				showBookButton={false}
				assetPrefix={assetPrefix}
			/>

			<header className={DESTINATION_GUIDES_THEME.heroHeader}>
				<div className={DESTINATION_GUIDES_THEME.heroMediaWrapper}>
					<img
						src={`${assetPrefix}${guide.heroImage}`}
						srcSet={heroSrcSet}
						sizes={heroSrcSet ? HERO_IMAGE_SIZES : undefined}
						alt={content.heroImageAlt}
						width={guide.heroImageDimensions.width}
						height={guide.heroImageDimensions.height}
						fetchPriority="high"
						loading="eager"
						className={DESTINATION_GUIDES_THEME.heroImage}
					/>
					<div className={DESTINATION_GUIDES_THEME.heroOverlay}></div>
				</div>
				<div className={DESTINATION_GUIDES_THEME.heroContent}>
					<span className={DESTINATION_GUIDES_THEME.heroBadge}>
						<BadgeIcon
							className={DESTINATION_GUIDES_THEME.iconSm}
							aria-hidden={true}
						/>
						<span>{content.badge}</span>
					</span>
					<h1 className={DESTINATION_GUIDES_THEME.heroTitle}>
						{resolvedTitle}
					</h1>
					<p className={DESTINATION_GUIDES_THEME.heroSubtitle}>
						{resolvedSubtitle}
					</p>
					<div className={DESTINATION_GUIDES_THEME.heroMetaRow}>
						<span className={DESTINATION_GUIDES_THEME.heroMetaItem}>
							<Calendar
								className={DESTINATION_GUIDES_THEME.iconXs}
								aria-hidden="true"
							/>
							<span>
								{hubCopy.publishedLabel}:{" "}
								<time dateTime={guide.datePublished}>
									{guide.datePublished}
								</time>
							</span>
						</span>
						<span
							className={DESTINATION_GUIDES_THEME.heroMetaDivider}
							aria-hidden="true"
						>
							•
						</span>
						<span className={DESTINATION_GUIDES_THEME.heroMetaItem}>
							<span>
								{hubCopy.updatedLabel}:{" "}
								<time dateTime={guide.dateModified}>{guide.dateModified}</time>
							</span>
						</span>
						<span
							className={DESTINATION_GUIDES_THEME.heroMetaDivider}
							aria-hidden="true"
						>
							•
						</span>
						<span className={DESTINATION_GUIDES_THEME.heroMetaItem}>
							<Clock
								className={DESTINATION_GUIDES_THEME.iconXs}
								aria-hidden="true"
							/>
							<span>
								{hubCopy.readingTimeFormat.replace(
									"{minutes}",
									String(guide.readingTimeMinutes),
								)}
							</span>
						</span>
					</div>
				</div>
			</header>

			<main className={DESTINATION_GUIDES_THEME.main}>
				<Breadcrumbs
					lang={lang}
					assetPrefix={assetPrefix}
					parents={[{ label: hubCopy.breadcrumbLabel, href: hubHref }]}
					currentLabel={content.breadcrumbLabel}
					currentHref={currentHref}
					surface="inline"
				/>

				<article className={DESTINATION_GUIDES_THEME.articleWrapper}>
					<p className={DESTINATION_GUIDES_THEME.sectionSubtitle}>
						{hubCopy.authorByline}
					</p>

					{content.keyTakeaways.length > 0 && (
						<section
							id="key-takeaways"
							aria-labelledby="key-takeaways-heading"
							className={DESTINATION_GUIDES_THEME.takeawaysCard}
						>
							<h2
								id="key-takeaways-heading"
								className={DESTINATION_GUIDES_THEME.takeawaysTitle}
							>
								{content.keyTakeawaysTitle}
							</h2>
							<ul className={DESTINATION_GUIDES_THEME.takeawaysList}>
								{content.keyTakeaways.map((takeaway) => (
									<li
										key={takeaway}
										className={DESTINATION_GUIDES_THEME.takeawaysItem}
									>
										<CheckCircle2
											className={DESTINATION_GUIDES_THEME.takeawaysIcon}
											aria-hidden="true"
										/>
										<span>{interpolateGuideText(takeaway, tokens)}</span>
									</li>
								))}
							</ul>
						</section>
					)}

					{content.sections.map((section) => (
						<section
							key={section.id}
							id={section.id}
							aria-labelledby={`${section.id}-heading`}
							className={DESTINATION_GUIDES_THEME.articleSectionCard}
						>
							<h2
								id={`${section.id}-heading`}
								className={DESTINATION_GUIDES_THEME.articleSectionHeading}
							>
								{interpolateGuideText(section.heading, tokens)}
							</h2>

							{section.paragraphs.map((paragraph) => (
								<p
									key={paragraph}
									className={DESTINATION_GUIDES_THEME.articleParagraph}
								>
									{interpolateGuideText(paragraph, tokens)}
								</p>
							))}

							{section.bullets && section.bullets.length > 0 && (
								<ul className={DESTINATION_GUIDES_THEME.articleBulletList}>
									{section.bullets.map((bullet) => (
										<li
											key={bullet}
											className={DESTINATION_GUIDES_THEME.articleBulletItem}
										>
											<CheckCircle2
												className={DESTINATION_GUIDES_THEME.articleBulletIcon}
												aria-hidden="true"
											/>
											<span>{interpolateGuideText(bullet, tokens)}</span>
										</li>
									))}
								</ul>
							)}

							{section.factCards && section.factCards.length > 0 && (
								<div className={DESTINATION_GUIDES_THEME.factGrid}>
									{section.factCards.map((fact) => (
										<div
											key={fact.label}
											className={DESTINATION_GUIDES_THEME.factCard}
										>
											<p className={DESTINATION_GUIDES_THEME.factLabel}>
												{interpolateGuideText(fact.label, tokens)}
											</p>
											<p className={DESTINATION_GUIDES_THEME.factValue}>
												{interpolateGuideText(fact.value, tokens)}
											</p>
											{fact.detail && (
												<p className={DESTINATION_GUIDES_THEME.factDetail}>
													{interpolateGuideText(fact.detail, tokens)}
												</p>
											)}
										</div>
									))}
								</div>
							)}

							{section.comparisonTable && (
								/* biome-ignore lint/a11y/useSemanticElements: keep region wrapper as <div> inside outer <section> */
								<div
									// biome-ignore lint/a11y/noNoninteractiveTabindex: WAI scrollable table region requires keyboard focusability on overflow
									tabIndex={0}
									role="region"
									aria-labelledby={`${section.id}-table-caption`}
									className={DESTINATION_GUIDES_THEME.tableWrapper}
								>
									<table className={DESTINATION_GUIDES_THEME.table}>
										<caption
											id={`${section.id}-table-caption`}
											className={DESTINATION_GUIDES_THEME.tableCaption}
										>
											{interpolateGuideText(
												section.comparisonTable.caption,
												tokens,
											)}
										</caption>
										<thead className={DESTINATION_GUIDES_THEME.tableHead}>
											<tr>
												{section.comparisonTable.headers.map((header) => (
													<th
														key={header}
														scope="col"
														className={DESTINATION_GUIDES_THEME.tableHeaderCell}
													>
														{interpolateGuideText(header, tokens)}
													</th>
												))}
											</tr>
										</thead>
										<tbody className={DESTINATION_GUIDES_THEME.tableBody}>
											{section.comparisonTable.rows.map((row) => (
												<tr
													key={row[0]}
													className={DESTINATION_GUIDES_THEME.tableRow}
												>
													{row.map((cell, cellIdx) =>
														cellIdx === 0 ? (
															<th
																key={`${row[0]}-${cellIdx}`}
																scope="row"
																className={
																	DESTINATION_GUIDES_THEME.tableRowHeader
																}
															>
																{interpolateGuideText(cell, tokens)}
															</th>
														) : (
															<td
																key={`${row[0]}-${cellIdx}`}
																className={DESTINATION_GUIDES_THEME.tableCell}
															>
																{interpolateGuideText(cell, tokens)}
															</td>
														),
													)}
												</tr>
											))}
										</tbody>
									</table>
								</div>
							)}

							{section.callout && (
								<div className={DESTINATION_GUIDES_THEME.articleCallout}>
									<span
										className={DESTINATION_GUIDES_THEME.articleCalloutLabel}
									>
										{interpolateGuideText(section.callout.label, tokens)}
									</span>
									<p>{interpolateGuideText(section.callout.text, tokens)}</p>
								</div>
							)}
						</section>
					))}

					{content.faqs.length > 0 && (
						<section
							id="guide-faq"
							aria-labelledby="guide-faq-heading"
							className={DESTINATION_GUIDES_THEME.faqSection}
						>
							<h2
								id="guide-faq-heading"
								className={DESTINATION_GUIDES_THEME.faqTitle}
							>
								{content.faqsTitle}
							</h2>
							<div className={DESTINATION_GUIDES_THEME.faqList}>
								{content.faqs.map((faq) => (
									<details
										key={faq.id}
										id={faq.id}
										className={DESTINATION_GUIDES_THEME.faqDetails}
										open={Boolean(faq.defaultOpen)}
									>
										<summary className={DESTINATION_GUIDES_THEME.faqSummary}>
											<span>{interpolateGuideText(faq.question, tokens)}</span>
											<ChevronDown
												className={DESTINATION_GUIDES_THEME.faqChevron}
												aria-hidden="true"
											/>
										</summary>
										<div className={DESTINATION_GUIDES_THEME.faqAnswerWrapper}>
											<p className={DESTINATION_GUIDES_THEME.faqAnswer}>
												{interpolateGuideText(faq.answer, tokens)}
											</p>
										</div>
									</details>
								))}
							</div>
						</section>
					)}

					<section
						id="guide-property-cta"
						aria-labelledby="guide-property-cta-heading"
						className={DESTINATION_GUIDES_THEME.propertyCtaSection}
					>
						<div className={DESTINATION_GUIDES_THEME.propertyCtaHeader}>
							<p className={DESTINATION_GUIDES_THEME.propertyCtaEyebrow}>
								{hubCopy.articlePropertyCtaEyebrow}
							</p>
							<h2
								id="guide-property-cta-heading"
								className={DESTINATION_GUIDES_THEME.propertyCtaTitle}
							>
								{resolvedCtaTitle}
							</h2>
							<p className={DESTINATION_GUIDES_THEME.propertyCtaSubtitle}>
								{resolvedCtaSubtitle}
							</p>
						</div>
						<div className={DESTINATION_GUIDES_THEME.propertyCtaGrid}>
							{guide.relatedProperties.map((propertyId) => {
								const spec = PROPERTY_CTA_SPECS[propertyId];
								const propPricing = pricingSummary.properties[propertyId];
								const rateLine = interpolateGuideText(
									hubCopy.propertyRateLineTemplate,
									{
										rate: formatCopCurrencyForLocale(
											propPricing.lowPrice,
											lang,
										),
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
											<span
												className={
													DESTINATION_GUIDES_THEME.propertyCtaCardBadge
												}
											>
												{t[spec.badgeKey]}
											</span>
											<h3
												className={
													DESTINATION_GUIDES_THEME.propertyCtaCardTitle
												}
											>
												{t[spec.titleKey]}
											</h3>
											<p
												className={DESTINATION_GUIDES_THEME.propertyCtaCardDesc}
											>
												{hubCopy[spec.pitchKey]}
											</p>
											<div
												className={DESTINATION_GUIDES_THEME.propertyCtaPriceRow}
											>
												<p
													className={
														DESTINATION_GUIDES_THEME.propertyCtaRateLine
													}
												>
													{rateLine}
												</p>
												<p
													className={
														DESTINATION_GUIDES_THEME.propertyCtaCleaningLine
													}
												>
													{cleaningLine}
												</p>
											</div>
										</div>
										<a
											href={getPropertyPagePath(propertyId, lang, assetPrefix)}
											data-guide-cta-property={propertyId}
											data-guide-slug={guide.slug}
											className={DESTINATION_GUIDES_THEME.propertyCtaButton}
										>
											<span>{hubCopy.articlePropertyCtaButton}</span>
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

					<section
						id="related-destination-guides"
						aria-labelledby="related-destination-guides-heading"
						className={DESTINATION_GUIDES_THEME.relatedSection}
					>
						<h2
							id="related-destination-guides-heading"
							className={DESTINATION_GUIDES_THEME.sectionTitle}
						>
							{hubCopy.relatedGuidesTitle}
						</h2>
						<div className={DESTINATION_GUIDES_THEME.relatedLinksRow}>
							<a
								href={hubHref}
								className={DESTINATION_GUIDES_THEME.backToHubLink}
							>
								<ArrowLeft
									className={DESTINATION_GUIDES_THEME.iconSm}
									aria-hidden="true"
								/>
								<span>{hubCopy.backToHubLabel}</span>
							</a>
							{relatedGuides.map((related) => (
								<a
									key={related.slug}
									href={getDestinationGuidePath(
										related.slug,
										lang,
										assetPrefix,
									)}
									className={DESTINATION_GUIDES_THEME.relatedLink}
								>
									<span>{related.locales[lang].breadcrumbLabel}</span>
									<ArrowRight
										className={DESTINATION_GUIDES_THEME.iconSm}
										aria-hidden="true"
									/>
								</a>
							))}
						</div>
					</section>
				</article>
			</main>

			<Footer lang={lang} assetPrefix={assetPrefix} />
		</div>
	);
}
