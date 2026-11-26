import { ArrowRight, Clock, Compass } from "lucide-react";
import { Breadcrumbs } from "../components/Breadcrumbs";
import {
	DestinationGuidePropertyCta,
	GUIDE_ICONS,
} from "../components/DestinationGuidePropertyCta";
import { Footer } from "../components/Footer";
import { Navigation } from "../components/Navigation";
import {
	getDestinationGuidePath,
	getDestinationGuidesHubPath,
	getHeroImageSrcSet,
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
	interpolateGuideText,
} from "../i18n/destinationGuides";
import { dict } from "../i18n/dict";
import type { AppProps } from "../types";
import { DEFAULT_PRICING_SUMMARY } from "../utils/schema";

const HUB_PROPERTY_IDS: readonly PropertyId[] = ["1707", "1606"];

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
											<p className={DESTINATION_GUIDES_THEME.guideCardSummary}>
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

				<DestinationGuidePropertyCta
					sectionId="destination-guides-properties"
					headingId="destination-guides-properties-heading"
					eyebrow={t.heroLocation}
					title={hubCopy.propertiesCtaTitle}
					subtitle={hubCopy.propertiesCtaSubtitle}
					buttonLabel={hubCopy.propertyCtaButton}
					guideSlug="hub"
					propertyIds={HUB_PROPERTY_IDS}
					lang={lang}
					assetPrefix={assetPrefix}
					pricingSummary={pricingSummary}
				/>
			</main>

			<Footer lang={lang} assetPrefix={assetPrefix} />
		</div>
	);
}
