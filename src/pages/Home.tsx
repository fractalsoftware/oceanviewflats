import {
	ArrowDown,
	ArrowRight,
	Building2,
	Calendar as CalendarIcon,
	CheckCircle2,
	ChevronDown,
	Compass,
	CreditCard,
	ExternalLink,
	Heart,
	Landmark,
	Mail,
	MapPin,
	MessageCircle,
	Percent,
	Plane,
	ShieldCheck,
	Star,
	Trees,
	Users,
	Waves,
	Wifi,
} from "lucide-react";
import React from "react";
import { Footer } from "../components/Footer";
import { Navigation } from "../components/Navigation";
import {
	BUILDING_MAP_URL,
	DATA_CONTROLLER,
	getContactPagePath,
	getDestinationGuidesHubPath,
	getGuidePagePath,
	getHeroImageSrcSet,
	getPropertyPagePath,
	getRegistryPagePath,
	HERO_IMAGE_SIZES,
	HOME_FAQ_ITEMS,
	HOME_PROPERTY_CARDS,
	HOME_PROPERTY_COMPARISON_ROWS,
	HOME_SHARED_BUILDING_RULES,
	IMAGE_DIMENSIONS,
	IMAGES,
	PLAYA_SALGUERO_LANDMARKS,
} from "../constants/config";
import { HOME_CARD_THEMES, HOME_THEME } from "../constants/theme";
import { dict } from "../i18n/dict";
import type { AppProps } from "../types";
import { formatLocalizedIsoDate } from "../utils/date";
import { clampReviewRating } from "../utils/reviews";

const HIGHLIGHT_ICONS = {
	wifi: Wifi,
	users: Users,
	heart: Heart,
} as const;

const LANDMARK_ICONS = {
	waves: Waves,
	compass: Compass,
	plane: Plane,
	landmark: Landmark,
	trees: Trees,
} as const;

const renderComparisonCell = (
	t: (typeof dict)[keyof typeof dict],
	cell: {
		propertyId: "1707" | "1606";
		valueKey:
			| (typeof HOME_PROPERTY_COMPARISON_ROWS)[number]["val1707Key"]
			| (typeof HOME_PROPERTY_COMPARISON_ROWS)[number]["val1606Key"];
		noteKey?:
			| (typeof HOME_PROPERTY_COMPARISON_ROWS)[number]["note1707Key"]
			| (typeof HOME_PROPERTY_COMPARISON_ROWS)[number]["note1606Key"];
		isLiveRate?: boolean;
	},
) => {
	if (!cell.isLiveRate) {
		return t[cell.valueKey];
	}
	return (
		<>
			<span
				id={`home-compare-rate-${cell.propertyId}`}
				data-compare-rate={cell.propertyId}
				className={HOME_THEME.compareRatePrimary}
			>
				{t[cell.valueKey]}
			</span>
			{cell.noteKey && (
				<span className={HOME_THEME.compareRateNote}>{t[cell.noteKey]}</span>
			)}
		</>
	);
};

export default function Home({ lang, assetPrefix = "./" }: AppProps) {
	const t = dict[lang];
	const contactHref = getContactPagePath(lang, assetPrefix);
	const destinationGuidesHref = getDestinationGuidesHubPath(lang, assetPrefix);
	const property1707Href = getPropertyPagePath("1707", lang, assetPrefix);
	const property1606Href = getPropertyPagePath("1606", lang, assetPrefix);
	const registryHref = getRegistryPagePath(lang, assetPrefix);
	const guideHref = getGuidePagePath(lang, assetPrefix);

	return (
		<div className={HOME_THEME.pageWrapper}>
			<Navigation
				isScrolled={false}
				lang={lang}
				showBookButton={true}
				bookHref="#properties"
				bookLabelKey="navBookDirect"
				assetPrefix={assetPrefix}
				currentPage="home"
			/>

			{/* Sunlit Coastal Hero Section */}
			<header className={HOME_THEME.heroHeader}>
				<div className={HOME_THEME.heroMediaWrapper}>
					<img
						src={`${assetPrefix}${IMAGES.home.hero}`}
						srcSet={getHeroImageSrcSet(IMAGES.home.hero, assetPrefix)}
						sizes={HERO_IMAGE_SIZES}
						alt={t.heroImgAlt}
						width={IMAGE_DIMENSIONS.homeHero.width}
						height={IMAGE_DIMENSIONS.homeHero.height}
						className={HOME_THEME.heroImage}
						fetchPriority="high"
						loading="eager"
					/>
					<div className={HOME_THEME.heroOverlay} aria-hidden="true" />
				</div>
				<div className={HOME_THEME.heroContent}>
					<div id="hero-location" className={HOME_THEME.heroLocationBadge}>
						<MapPin
							className={HOME_THEME.heroLocationIcon}
							aria-hidden="true"
						/>
						<span>{t.heroLocation}</span>
					</div>
					<h1 className={HOME_THEME.heroTitle}>{t.homeWelcome}</h1>
					<p className={HOME_THEME.heroSubtitle}>{t.homeSubtitle}</p>
					<p id="hero-value-prop" className={HOME_THEME.heroValueProp}>
						{t.homeHeroValueProp}
					</p>
					<div className={HOME_THEME.heroCtaGroup}>
						<a href="#properties" className={HOME_THEME.heroCtaBtn}>
							<span>{t.homeHeroCta}</span>
							<ArrowDown
								className={HOME_THEME.heroCtaIcon}
								aria-hidden="true"
							/>
						</a>
						<div className={HOME_THEME.heroQuickLinksRow}>
							<a
								id="hero-direct-1707"
								href={property1707Href}
								className={HOME_THEME.heroQuickLinkBtn}
							>
								<span>{t.homeHeroDirect1707}</span>
								<ArrowRight className={HOME_THEME.iconSm} aria-hidden="true" />
							</a>
							<a
								id="hero-direct-1606"
								href={property1606Href}
								className={HOME_THEME.heroQuickLinkBtn}
							>
								<span>{t.homeHeroDirect1606}</span>
								<ArrowRight className={HOME_THEME.iconSm} aria-hidden="true" />
							</a>
						</div>
					</div>
					<div
						id="hero-returning-guest-cue"
						className={HOME_THEME.heroReturningGuestCue}
					>
						<span className={HOME_THEME.heroReturningGuestPrompt}>
							{t.homeHeroReturningGuestPrompt}
						</span>
						<div className={HOME_THEME.heroReturningGuestLinks}>
							<a
								id="hero-returning-registry-link"
								href={registryHref}
								className={HOME_THEME.heroReturningGuestLink}
							>
								{t.homeHeroReturningGuestRegistry}
							</a>
							<span
								className={HOME_THEME.heroReturningGuestDivider}
								aria-hidden="true"
							>
								·
							</span>
							<a
								id="hero-returning-guide-link"
								href={guideHref}
								className={HOME_THEME.heroReturningGuestLink}
							>
								{t.homeHeroReturningGuestGuide}
							</a>
						</div>
					</div>
				</div>
			</header>

			<main id="main-content" tabIndex={-1} className={HOME_THEME.main}>
				{/* Consolidated Beachfront Properties Showcase + Verified Guest Reviews */}
				<section id="properties" className={HOME_THEME.propertiesSection}>
					<h2 className={HOME_THEME.propertiesHeading}>
						{t.homeFeaturedPropertiesTitle}
					</h2>
					<div className={HOME_THEME.propertiesGrid}>
						{HOME_PROPERTY_CARDS.map((card) => {
							const cardTheme = HOME_CARD_THEMES[card.id];
							const cardHref = getPropertyPagePath(card.id, lang, assetPrefix);
							const review = card.curatedReview;
							const starCount = clampReviewRating(review.ratingValue);

							return (
								<article
									key={card.id}
									id={card.cardDomId}
									className={cardTheme.card}
								>
									<div className={cardTheme.cardMedia}>
										<img
											src={`${assetPrefix}${IMAGES[card.imageKey].hero}`}
											className={cardTheme.cardImage}
											alt={t[card.imageAltKey]}
											width={IMAGE_DIMENSIONS[card.imageKey].hero.width}
											height={IMAGE_DIMENSIONS[card.imageKey].hero.height}
											loading="lazy"
											decoding="async"
										/>
										<div className={cardTheme.cardOverlay} aria-hidden="true" />
										<span className={cardTheme.cardBadge}>
											{t[card.badgeKey]}
										</span>
										<div className={cardTheme.cardHeaderContent}>
											<h3 className={cardTheme.cardTitle}>
												<a
													href={cardHref}
													className={cardTheme.cardHeadingLink}
												>
													{t[card.titleKey]}
												</a>
											</h3>
										</div>
									</div>

									<div className={cardTheme.cardBody}>
										<div>
											<div className={cardTheme.rateRow}>
												<span
													id={card.rateBadgeDomId}
													data-property-rate={card.id}
													className={cardTheme.rateBadge}
												>
													{t[card.rateKey]}
												</span>
												<p className={cardTheme.rateCurrencyNote}>
													{t.homeRateCurrencyNote}
												</p>
											</div>
											<p className={cardTheme.cardDesc}>{t[card.descKey]}</p>

											{/* Scannable Inline Spec Bar */}
											<ul className={cardTheme.cardSpecBar}>
												<li className={cardTheme.cardSpecHighlight}>
													{t[card.bedsKey]}
												</li>
												<li
													className={cardTheme.cardSpecDivider}
													aria-hidden="true"
												>
													•
												</li>
												<li className={cardTheme.cardSpecItem}>
													{t.statsBaths}
												</li>
												<li
													className={cardTheme.cardSpecDivider}
													aria-hidden="true"
												>
													•
												</li>
												<li className={cardTheme.cardSpecItem}>
													{t.statsGuests}
												</li>
												<li
													className={cardTheme.cardSpecDivider}
													aria-hidden="true"
												>
													•
												</li>
												<li className={cardTheme.cardSpecItem}>
													{t[card.wifiKey]}
												</li>
												<li
													className={cardTheme.cardSpecDivider}
													aria-hidden="true"
												>
													•
												</li>
												<li className={cardTheme.cardSpecItem}>
													{t[card.parkingKey]}
												</li>
											</ul>

											{/* Consolidated Lifestyle Photo & Highlights */}
											<div className={cardTheme.lifestyleStrip}>
												<div className={cardTheme.lifestyleThumbWrapper}>
													<img
														src={`${assetPrefix}${card.lifestyleImageSrc}`}
														alt={t[card.lifestyleImageAltKey]}
														width={card.lifestyleImageWidth}
														height={card.lifestyleImageHeight}
														className={cardTheme.lifestyleThumbImg}
														loading="lazy"
														decoding="async"
													/>
												</div>
												<ul className={cardTheme.highlightsList}>
													{card.highlights.map((highlight) => {
														const HighlightIcon =
															HIGHLIGHT_ICONS[highlight.iconId];
														return (
															<li
																key={highlight.titleKey}
																className={cardTheme.highlightItem}
															>
																<HighlightIcon
																	className={cardTheme.highlightIcon}
																	aria-hidden="true"
																/>
																<div>
																	<p className={cardTheme.highlightTitle}>
																		{t[highlight.titleKey]}
																	</p>
																	<p className={cardTheme.highlightDesc}>
																		{t[highlight.descKey]}
																	</p>
																</div>
															</li>
														);
													})}
												</ul>
											</div>

											{/* Verified 5-Star Guest Review with Date & Outbound Airbnb Link */}
											<div id={review.id} className={cardTheme.reviewBox}>
												<div
													className={cardTheme.reviewStarsRow}
													aria-hidden="true"
												>
													{Array.from({ length: starCount }, (_, index) => (
														<Star
															key={`${review.id}-star-${index}`}
															className={cardTheme.reviewStarIcon}
															aria-hidden="true"
														/>
													))}
												</div>
												<blockquote className={cardTheme.reviewQuote}>
													{t[review.textKey]}
												</blockquote>
												<div className={cardTheme.reviewFooter}>
													<div className={cardTheme.reviewMetaRow}>
														<span className={cardTheme.reviewAuthorName}>
															{t[review.authorKey]}
														</span>
														<span className={cardTheme.reviewVerifiedBadge}>
															<CheckCircle2
																className={cardTheme.reviewIconSm}
																aria-hidden="true"
															/>
															<span>{t.reviewVerifiedGuest}</span>
														</span>
														<time
															dateTime={review.datePublished}
															className={cardTheme.reviewDate}
														>
															{formatLocalizedIsoDate(
																review.datePublished,
																lang,
															)}
														</time>
													</div>
													<div className={cardTheme.reviewLinkRow}>
														<a
															href={card.airbnbUrl}
															target="_blank"
															rel="noopener noreferrer"
															className={cardTheme.reviewAirbnbLink}
														>
															<span>{t.reviewViewOnAirbnb}</span>
															<ExternalLink
																className={cardTheme.reviewIconSm}
																aria-hidden="true"
															/>
														</a>
													</div>
												</div>
											</div>
										</div>

										<a href={cardHref} className={cardTheme.cardCtaBtn}>
											<span>{t.homeCardCta}</span>
											<ArrowRight
												className={cardTheme.cardCtaIcon}
												aria-hidden="true"
											/>
										</a>
									</div>
								</article>
							);
						})}
					</div>

					{/* Multi-Property (7–12 Guests) Family Stay Callout */}
					<div
						id="home-multi-property-note"
						className={HOME_THEME.multiPropertyBanner}
					>
						<div className={HOME_THEME.multiPropertyContent}>
							<div className={HOME_THEME.multiPropertyIconBox}>
								<Users className={HOME_THEME.iconMd} aria-hidden="true" />
							</div>
							<p className={HOME_THEME.multiPropertyText}>
								{t.homeMultiPropertyNote}
							</p>
						</div>
						<a href={contactHref} className={HOME_THEME.multiPropertyCta}>
							<span>{t.navContact}</span>
							<ArrowRight className={HOME_THEME.iconSm} aria-hidden="true" />
						</a>
					</div>
				</section>

				{/* Focused Differentiating Comparison Table + Shared Building Rules */}
				<section id="compare-properties" className={HOME_THEME.compareSection}>
					<div className={HOME_THEME.compareContainer}>
						<div className={HOME_THEME.compareHeader}>
							<h2 className={HOME_THEME.compareTitle}>{t.homeCompareTitle}</h2>
							<p className={HOME_THEME.compareSubtitle}>
								{t.homeCompareSubtitle}
							</p>
						</div>

						{/* biome-ignore lint/a11y/useSemanticElements: keep region wrapper as <div> inside outer <section id="compare-properties"> */}
						<div
							// biome-ignore lint/a11y/noNoninteractiveTabindex: WAI scrollable table region requires keyboard focusability on overflow
							tabIndex={0}
							role="region"
							aria-labelledby="home-comparison-caption"
							className={HOME_THEME.compareTableWrapper}
						>
							<table className={HOME_THEME.compareTable}>
								<caption
									id="home-comparison-caption"
									className={HOME_THEME.compareCaption}
								>
									{t.homeCompareCaption}
								</caption>
								<thead className={HOME_THEME.compareThead}>
									<tr>
										<th
											scope="col"
											className={HOME_THEME.compareColHeaderFirst}
										>
											{t.homeCompareColFeature}
										</th>
										<th scope="col" className={HOME_THEME.compareColHeader}>
											{t.heroTitle}
										</th>
										<th scope="col" className={HOME_THEME.compareColHeader}>
											{t.hero1606Title}
										</th>
									</tr>
								</thead>
								<tbody className={HOME_THEME.compareTbody}>
									{HOME_PROPERTY_COMPARISON_ROWS.map((row) => (
										<tr key={row.id} className={HOME_THEME.compareRow}>
											<th scope="row" className={HOME_THEME.compareRowHeader}>
												{t[row.labelKey]}
											</th>
											<td className={HOME_THEME.compareCell}>
												{renderComparisonCell(t, {
													propertyId: "1707",
													valueKey: row.val1707Key,
													noteKey: row.note1707Key,
													isLiveRate: row.isLiveRate,
												})}
											</td>
											<td className={HOME_THEME.compareCell}>
												{renderComparisonCell(t, {
													propertyId: "1606",
													valueKey: row.val1606Key,
													noteKey: row.note1606Key,
													isLiveRate: row.isLiveRate,
												})}
											</td>
										</tr>
									))}
								</tbody>
							</table>
						</div>

						{/* Shared Building Policies Companion Block */}
						<div
							id="home-shared-building-rules"
							className={HOME_THEME.sharedRulesBox}
						>
							<div className={HOME_THEME.sharedRulesHeader}>
								<Building2
									className={HOME_THEME.sharedRulesIcon}
									aria-hidden="true"
								/>
								<h3 className={HOME_THEME.sharedRulesTitle}>
									{t.homeSharedRulesTitle}
								</h3>
							</div>
							<dl className={HOME_THEME.sharedRulesGrid}>
								{HOME_SHARED_BUILDING_RULES.map((rule) => (
									<div key={rule.id} className={HOME_THEME.sharedRulesItem}>
										<dt className={HOME_THEME.sharedRulesTerm}>
											{t[rule.labelKey]}
										</dt>
										<dd className={HOME_THEME.sharedRulesValue}>
											{t[rule.valueKey]}
										</dd>
									</div>
								))}
							</dl>
						</div>

						{/* Direct Action CTAs into Property 1707 & Property 1606 */}
						<div id="home-compare-ctas" className={HOME_THEME.compareCtaRow}>
							<a
								id="home-compare-cta-1707"
								href={property1707Href}
								className={HOME_THEME.compareCta1707}
							>
								<span>{t.homeCompareCta1707}</span>
								<ArrowRight className={HOME_THEME.iconSm} aria-hidden="true" />
							</a>
							<a
								id="home-compare-cta-1606"
								href={property1606Href}
								className={HOME_THEME.compareCta1606}
							>
								<span>{t.homeCompareCta1606}</span>
								<ArrowRight className={HOME_THEME.iconSm} aria-hidden="true" />
							</a>
						</div>
					</div>
				</section>

				{/* Consolidated Proof-Led Direct Reservation & Host Showcase */}
				<section id="why-book-direct" className={HOME_THEME.directSection}>
					<div className={HOME_THEME.directContainer}>
						<div className={HOME_THEME.directHeader}>
							<h2 className={HOME_THEME.directTitle}>{t.directTitle}</h2>
							<p className={HOME_THEME.directSubtitle}>{t.directSubtitle}</p>
						</div>

						<div className={HOME_THEME.directShowcaseGrid}>
							{/* Three Concrete, Non-Redundant Direct Reservation Proof Pillars */}
							<ul id="home-trust-strip" className={HOME_THEME.trustStripGrid}>
								<li className={HOME_THEME.trustStripCard}>
									<Percent
										className={HOME_THEME.trustStripIconEmerald}
										aria-hidden="true"
									/>
									<div>
										<h3 className={HOME_THEME.trustStripTitle}>
											{t.homeDirectPillar1Title}
										</h3>
										<p className={HOME_THEME.trustStripDesc}>
											{t.homeDirectPillar1Desc}
										</p>
									</div>
								</li>
								<li className={HOME_THEME.trustStripCardEmerald}>
									<CreditCard
										className={HOME_THEME.trustStripIconEmerald}
										aria-hidden="true"
									/>
									<div>
										<h3 className={HOME_THEME.trustStripTitle}>
											{t.homeTrustPaymentsTitle}
										</h3>
										<p className={HOME_THEME.trustStripDesc}>
											{t.homeTrustPaymentsDesc}
										</p>
									</div>
								</li>
								<li className={HOME_THEME.trustStripCard}>
									<ShieldCheck
										className={HOME_THEME.trustStripIconSlate}
										aria-hidden="true"
									/>
									<div>
										<h3 className={HOME_THEME.trustStripTitle}>
											{t.homeTrustRntTitle}
										</h3>
										<p className={HOME_THEME.trustStripDesc}>
											{t.homeTrustRntDesc}
										</p>
									</div>
								</li>
							</ul>

							{/* Owner-Hosted Showcase Card with Direct Contact Actions */}
							<div id="home-host-card" className={HOME_THEME.hostCard}>
								<div className={HOME_THEME.hostAvatarWrapper}>
									<img
										src={`${assetPrefix}${IMAGES.general.host}`}
										alt={t.hostImgAlt}
										width={IMAGE_DIMENSIONS.host.width}
										height={IMAGE_DIMENSIONS.host.height}
										className={HOME_THEME.hostAvatarImg}
										loading="lazy"
										decoding="async"
									/>
								</div>
								<div className={HOME_THEME.hostBody}>
									<div className={HOME_THEME.hostHeaderRow}>
										<h3 className={HOME_THEME.hostName}>{t.hostName}</h3>
										<span className={HOME_THEME.hostRoleBadge}>
											{t.hostTitle}
										</span>
									</div>
									<p className={HOME_THEME.hostBio}>{t.hostBio}</p>
									<div className={HOME_THEME.hostBadgesRow}>
										<span className={HOME_THEME.hostOwnerBadge}>
											<CheckCircle2
												className={HOME_THEME.iconSm}
												aria-hidden="true"
											/>
											<span>
												{`${t.homeHostOwnerBadge}: ${DATA_CONTROLLER.name}`}
											</span>
										</span>
										<span className={HOME_THEME.hostRntBadge}>
											<ShieldCheck
												className={HOME_THEME.iconSm}
												aria-hidden="true"
											/>
											<span>
												{`${t.rntTitle}: ${t.rnt1707} • ${t.rnt1606}`}
											</span>
										</span>
									</div>
									<div className={HOME_THEME.hostActionsRow}>
										<a
											id="home-host-whatsapp"
											href={DATA_CONTROLLER.whatsappHref}
											target="_blank"
											rel="noopener noreferrer"
											className={HOME_THEME.hostWhatsAppBtn}
										>
											<MessageCircle
												className={HOME_THEME.iconSm}
												aria-hidden="true"
											/>
											<span>{t.homeCtaWhatsApp}</span>
										</a>
										<a
											id="home-host-email"
											href={`mailto:${DATA_CONTROLLER.email}`}
											className={HOME_THEME.hostEmailBtn}
										>
											<Mail className={HOME_THEME.iconSm} aria-hidden="true" />
											<span>{DATA_CONTROLLER.email}</span>
										</a>
									</div>
								</div>
							</div>
						</div>
					</div>
				</section>

				{/* Playa Salguero Location & Balanced 3x2 Santa Marta Proximity Grid */}
				<section id="location" className={HOME_THEME.locationSection}>
					<div className={HOME_THEME.locationContainer}>
						<div className={HOME_THEME.locationHeader}>
							<div className={HOME_THEME.locationAddressRow}>
								<p className={HOME_THEME.locationAddressBadge}>
									<Building2 className={HOME_THEME.iconSm} aria-hidden="true" />
									<span>{t.homeLocationAddress}</span>
								</p>
								<a
									id="home-location-map-link"
									href={BUILDING_MAP_URL}
									target="_blank"
									rel="noopener noreferrer"
									className={HOME_THEME.locationMapLink}
								>
									<MapPin className={HOME_THEME.iconSm} aria-hidden="true" />
									<span>{t.homeLocationMapLink}</span>
									<ExternalLink
										className={HOME_THEME.locationMapExternalIcon}
										aria-hidden="true"
									/>
								</a>
							</div>
							<h2 className={HOME_THEME.locationTitle}>
								{t.homeLocationTitle}
							</h2>
							<p className={HOME_THEME.locationSubtitle}>
								{t.homeLocationSubtitle}
							</p>
						</div>

						<ul className={HOME_THEME.locationGrid}>
							{PLAYA_SALGUERO_LANDMARKS.map((landmark) => {
								const LandmarkIcon = LANDMARK_ICONS[landmark.iconId];
								return (
									<li key={landmark.id} className={HOME_THEME.locationCard}>
										<div className={HOME_THEME.locationCardTop}>
											<span className={HOME_THEME.locationIconBox}>
												<LandmarkIcon
													className={HOME_THEME.iconMd}
													aria-hidden="true"
												/>
											</span>
											<span className={HOME_THEME.locationProximityBadge}>
												{t[landmark.proximityKey]}
											</span>
										</div>
										<h3 className={HOME_THEME.locationCardTitle}>
											{t[landmark.nameKey]}
										</h3>
										<p className={HOME_THEME.locationCardDesc}>
											{t[landmark.descKey]}
										</p>
									</li>
								);
							})}

							{/* 6th Card: Contextual Santa Marta Destination Guides Hub Callout */}
							<li
								id="home-destination-guides-callout"
								className={HOME_THEME.locationGuidesCard}
							>
								<div>
									<div className={HOME_THEME.locationGuidesTop}>
										<Compass
											className={HOME_THEME.locationGuidesIcon}
											aria-hidden="true"
										/>
										<p className={HOME_THEME.locationGuidesEyebrow}>
											{t.homeLocationGuidesEyebrow}
										</p>
									</div>
									<p className={HOME_THEME.locationGuidesText}>
										{t.homeLocationGuidesDesc}
									</p>
								</div>
								<a
									id="home-destination-guides-link"
									href={destinationGuidesHref}
									className={HOME_THEME.locationGuidesCta}
								>
									<span>{t.homeLocationGuidesCta}</span>
									<ArrowRight
										className={HOME_THEME.iconSm}
										aria-hidden="true"
									/>
								</a>
							</li>
						</ul>
					</div>
				</section>

				{/* Progressive-Disclosure FAQ Section */}
				<section id="faq" className={HOME_THEME.faqSection}>
					<h2 className={HOME_THEME.faqTitle}>{t.faqTitle}</h2>
					<div className={HOME_THEME.faqList}>
						{HOME_FAQ_ITEMS.map((item) => (
							<details
								key={item.id}
								id={item.id}
								className={HOME_THEME.faqDetails}
								open={item.defaultOpen}
							>
								<summary className={HOME_THEME.faqSummary}>
									<span>{t[item.qKey]}</span>
									<ChevronDown
										className={HOME_THEME.faqChevron}
										aria-hidden="true"
									/>
								</summary>
								<div className={HOME_THEME.faqAnswerWrapper}>
									<p className={HOME_THEME.faqAnswer}>{t[item.aKey]}</p>
									{item.showFulfillmentLinks && (
										<div
											id="faq-fulfillment-links"
											className={HOME_THEME.faqLinksRow}
										>
											<a
												id="faq-registry-link"
												href={registryHref}
												className={HOME_THEME.faqActionLinkPrimary}
											>
												<span>{t.faq6RegistryLink}</span>
												<ArrowRight
													className={HOME_THEME.iconSm}
													aria-hidden="true"
												/>
											</a>
											<a
												id="faq-guide-link"
												href={guideHref}
												className={HOME_THEME.faqActionLinkSecondary}
											>
												<span>{t.faq6GuideLink}</span>
												<ArrowRight
													className={HOME_THEME.iconSm}
													aria-hidden="true"
												/>
											</a>
										</div>
									)}
								</div>
							</details>
						))}
					</div>
				</section>

				{/* Bottom Direct-Reservation & Inquiry CTA Section */}
				<section className={HOME_THEME.ctaSection}>
					<div className={HOME_THEME.ctaCard}>
						<div className={HOME_THEME.ctaContent}>
							<h2 className={HOME_THEME.ctaTitle}>{t.homeCtaTitle}</h2>
							<p className={HOME_THEME.ctaSubtitle}>{t.homeCtaSub}</p>
							<div className={HOME_THEME.ctaActionsRow}>
								<a href="#properties" className={HOME_THEME.ctaPrimaryBtn}>
									<CalendarIcon
										className={HOME_THEME.heroCtaIcon}
										aria-hidden="true"
									/>
									<span>{t.homeHeroCta}</span>
								</a>
								<a href={contactHref} className={HOME_THEME.ctaSecondaryBtn}>
									<span>{t.navContact}</span>
									<ArrowRight
										className={HOME_THEME.iconMd}
										aria-hidden="true"
									/>
								</a>
								<a
									href={DATA_CONTROLLER.whatsappHref}
									target="_blank"
									rel="noopener noreferrer"
									className={HOME_THEME.ctaWhatsAppBtn}
								>
									<MessageCircle
										className={HOME_THEME.iconMd}
										aria-hidden="true"
									/>
									<span>{t.homeCtaWhatsApp}</span>
								</a>
							</div>
						</div>
					</div>
				</section>
			</main>

			<Footer lang={lang} assetPrefix={assetPrefix} />
		</div>
	);
}
