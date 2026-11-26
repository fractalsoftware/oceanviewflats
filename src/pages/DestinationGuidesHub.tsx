import {
	ArrowRight,
	Building2,
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
	IMAGE_DIMENSIONS,
	IMAGES,
	type PropertyId,
} from "../constants/config";
import { DESTINATION_GUIDES_THEME } from "../constants/theme";
import {
	buildGuidePricingTokens,
	DESTINATION_GUIDES,
	DESTINATION_GUIDES_HUB_COPY,
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

const HUB_PROPERTY_CARDS: readonly {
	id: PropertyId;
	titleKey: "heroTitle" | "hero1606Title";
	badgeKey: "homeCard1707Badge" | "homeCard1606Badge";
	pitchKey: "property1707Pitch" | "property1606Pitch";
}[] = [
	{
		id: "1707",
		titleKey: "heroTitle",
		badgeKey: "homeCard1707Badge",
		pitchKey: "property1707Pitch",
	},
	{
		id: "1606",
		titleKey: "hero1606Title",
		badgeKey: "homeCard1606Badge",
		pitchKey: "property1606Pitch",
	},
];

export default function DestinationGuidesHub({
	lang,
	assetPrefix = "../",
	pricingSummary = DEFAULT_PRICING_SUMMARY,
}: AppProps) {
	const t = dict[lang];
	const hubCopy = DESTINATION_GUIDES_HUB_COPY[lang];
	const pricingTokens = buildGuidePricingTokens(pricingSummary, lang);
	const hubHref = getDestinationGuidesHubPath(lang, assetPrefix);

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
						src={`${assetPrefix}${IMAGES.home.hero}`}
						srcSet={getHeroImageSrcSet(IMAGES.home.hero, assetPrefix)}
						sizes={HERO_IMAGE_SIZES}
						alt={hubCopy.heroImageAlt}
						width={IMAGE_DIMENSIONS.homeHero.width}
						height={IMAGE_DIMENSIONS.homeHero.height}
						fetchPriority="high"
						loading="eager"
						className={DESTINATION_GUIDES_THEME.heroImage}
					/>
					<div className={DESTINATION_GUIDES_THEME.heroOverlay}></div>
				</div>
				<div className={DESTINATION_GUIDES_THEME.heroContent}>
					<span className={DESTINATION_GUIDES_THEME.heroBadge}>
						<Compass
							className={DESTINATION_GUIDES_THEME.iconSm}
							aria-hidden="true"
						/>
						<span>{hubCopy.badge}</span>
					</span>
					<h1 className={DESTINATION_GUIDES_THEME.heroTitle}>
						{hubCopy.heroTitle}
					</h1>
					<p className={DESTINATION_GUIDES_THEME.heroSubtitle}>
						{hubCopy.heroSubtitle}
					</p>
				</div>
			</header>

			<main className={DESTINATION_GUIDES_THEME.main}>
				<Breadcrumbs
					lang={lang}
					assetPrefix={assetPrefix}
					currentLabel={hubCopy.breadcrumbLabel}
					currentHref={hubHref}
					surface="inline"
				/>

				<section
					id="destination-guides-intro"
					className={DESTINATION_GUIDES_THEME.introCard}
				>
					<h2 className={DESTINATION_GUIDES_THEME.introHeading}>
						{hubCopy.introTitle}
					</h2>
					<p className={DESTINATION_GUIDES_THEME.introBody}>
						{interpolateGuideText(hubCopy.introBody, pricingTokens)}
					</p>
				</section>

				{DESTINATION_GUIDES.length > 0 ? (
					<section
						id="published-destination-guides"
						aria-labelledby="published-destination-guides-heading"
					>
						<div className={DESTINATION_GUIDES_THEME.sectionHeader}>
							<h2
								id="published-destination-guides-heading"
								className={DESTINATION_GUIDES_THEME.sectionTitle}
							>
								{hubCopy.guidesSectionTitle}
							</h2>
							<p className={DESTINATION_GUIDES_THEME.sectionSubtitle}>
								{hubCopy.guidesSectionSubtitle}
							</p>
						</div>
						<div className={DESTINATION_GUIDES_THEME.guidesGrid}>
							{DESTINATION_GUIDES.map((guide) => {
								const content = guide.locales[lang];
								const Icon = GUIDE_ICONS[guide.categoryIcon];
								const guideHref = getDestinationGuidePath(
									guide.slug,
									lang,
									assetPrefix,
								);
								return (
									<a
										key={guide.slug}
										id={`guide-card-${guide.slug}`}
										href={guideHref}
										className={DESTINATION_GUIDES_THEME.guideCard}
									>
										<div className={DESTINATION_GUIDES_THEME.guideCardMedia}>
											<img
												src={`${assetPrefix}${guide.heroImage}`}
												alt={content.heroImageAlt}
												width={guide.heroImageDimensions.width}
												height={guide.heroImageDimensions.height}
												loading="lazy"
												decoding="async"
												className={DESTINATION_GUIDES_THEME.guideCardImage}
											/>
											<div
												className={DESTINATION_GUIDES_THEME.guideCardOverlay}
											></div>
											<span className={DESTINATION_GUIDES_THEME.guideCardBadge}>
												<Icon
													className={DESTINATION_GUIDES_THEME.iconXs}
													aria-hidden={true}
												/>
												<span>{content.badge}</span>
											</span>
										</div>
										<div className={DESTINATION_GUIDES_THEME.guideCardBody}>
											<div>
												<div className={DESTINATION_GUIDES_THEME.guideCardMeta}>
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
												</div>
												<h3 className={DESTINATION_GUIDES_THEME.guideCardTitle}>
													{interpolateGuideText(content.title, pricingTokens)}
												</h3>
												<p
													className={DESTINATION_GUIDES_THEME.guideCardSummary}
												>
													{interpolateGuideText(
														content.cardSummary,
														pricingTokens,
													)}
												</p>
											</div>
											<div className={DESTINATION_GUIDES_THEME.guideCardCtaRow}>
												<span>{hubCopy.readGuideCta}</span>
												<ArrowRight
													className={DESTINATION_GUIDES_THEME.iconSm}
													aria-hidden="true"
												/>
											</div>
										</div>
									</a>
								);
							})}
						</div>
					</section>
				) : (
					<section
						id="destination-guide-topics"
						aria-labelledby="destination-guide-topics-heading"
					>
						<div className={DESTINATION_GUIDES_THEME.sectionHeader}>
							<h2
								id="destination-guide-topics-heading"
								className={DESTINATION_GUIDES_THEME.sectionTitle}
							>
								{hubCopy.topicPreviewsTitle}
							</h2>
							<p className={DESTINATION_GUIDES_THEME.sectionSubtitle}>
								{hubCopy.topicPreviewsSubtitle}
							</p>
						</div>
						<div className={DESTINATION_GUIDES_THEME.guidesGrid}>
							{hubCopy.topicPreviews.map((topic) => {
								const Icon = GUIDE_ICONS[topic.icon];
								return (
									<article
										key={topic.id}
										id={topic.id}
										className={DESTINATION_GUIDES_THEME.topicCard}
									>
										<div>
											<div className={DESTINATION_GUIDES_THEME.topicCardTop}>
												<div className={DESTINATION_GUIDES_THEME.topicIconBox}>
													<Icon
														className={DESTINATION_GUIDES_THEME.iconMd}
														aria-hidden={true}
													/>
												</div>
												<span className={DESTINATION_GUIDES_THEME.topicBadge}>
													{topic.badge}
												</span>
											</div>
											<h3 className={DESTINATION_GUIDES_THEME.topicTitle}>
												{topic.title}
											</h3>
											<p className={DESTINATION_GUIDES_THEME.topicDesc}>
												{interpolateGuideText(topic.description, pricingTokens)}
											</p>
										</div>
									</article>
								);
							})}
						</div>
					</section>
				)}

				<section
					id="destination-guides-properties"
					aria-labelledby="destination-guides-properties-heading"
					className={DESTINATION_GUIDES_THEME.propertyCtaSection}
				>
					<div className={DESTINATION_GUIDES_THEME.propertyCtaHeader}>
						<p className={DESTINATION_GUIDES_THEME.propertyCtaEyebrow}>
							{t.heroLocation}
						</p>
						<h2
							id="destination-guides-properties-heading"
							className={DESTINATION_GUIDES_THEME.propertyCtaTitle}
						>
							{hubCopy.propertiesCtaTitle}
						</h2>
						<p className={DESTINATION_GUIDES_THEME.propertyCtaSubtitle}>
							{hubCopy.propertiesCtaSubtitle}
						</p>
					</div>
					<div className={DESTINATION_GUIDES_THEME.propertyCtaGrid}>
						{HUB_PROPERTY_CARDS.map((prop) => {
							const propPricing = pricingSummary.properties[prop.id];
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
									key={prop.id}
									className={DESTINATION_GUIDES_THEME.propertyCtaCard}
								>
									<div>
										<span
											className={DESTINATION_GUIDES_THEME.propertyCtaCardBadge}
										>
											{t[prop.badgeKey]}
										</span>
										<h3
											className={DESTINATION_GUIDES_THEME.propertyCtaCardTitle}
										>
											{t[prop.titleKey]}
										</h3>
										<p className={DESTINATION_GUIDES_THEME.propertyCtaCardDesc}>
											{hubCopy[prop.pitchKey]}
										</p>
										<div
											className={DESTINATION_GUIDES_THEME.propertyCtaPriceRow}
										>
											<p
												className={DESTINATION_GUIDES_THEME.propertyCtaRateLine}
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
										href={getPropertyPagePath(prop.id, lang, assetPrefix)}
										data-guide-cta-property={prop.id}
										data-guide-slug="hub"
										className={DESTINATION_GUIDES_THEME.propertyCtaButton}
									>
										<span>{hubCopy.propertyCtaButton}</span>
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
			</main>

			<Footer lang={lang} assetPrefix={assetPrefix} />
		</div>
	);
}
