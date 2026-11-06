import {
	ArrowRight,
	Calendar as CalendarIcon,
	ChevronDown,
	Heart,
	MapPin,
	MessageCircle,
	MessageSquare,
	Percent,
	ShieldCheck,
	Sparkles,
	Users,
	Wifi,
} from "lucide-react";
import React from "react";
import { Footer } from "../components/Footer";
import { Navigation } from "../components/Navigation";
import {
	DATA_CONTROLLER,
	getContactPagePath,
	getPropertyPagePath,
	HOME_EXPERIENCE_CARDS,
	HOME_FAQ_ITEMS,
	HOME_PROPERTY_CARDS,
	IMAGES,
} from "../constants/config";
import { HOME_CARD_THEMES, HOME_THEME } from "../constants/theme";
import { dict } from "../i18n/dict";
import type { AppProps } from "../types";

const EXPERIENCE_FEATURE_ICONS = {
	wifi: Wifi,
	users: Users,
	heart: Heart,
} as const;

export default function Home({ lang, assetPrefix = "./" }: AppProps) {
	const t = dict[lang];
	const contactHref = getContactPagePath(lang, assetPrefix);

	return (
		<div className={HOME_THEME.pageWrapper}>
			<Navigation
				isScrolled={false}
				lang={lang}
				showBookButton={true}
				bookHref="#properties"
				bookLabelKey="navBookDirect"
				assetPrefix={assetPrefix}
			/>

			{/* Hero Section */}
			<header className={HOME_THEME.heroHeader}>
				<div className={HOME_THEME.heroMediaWrapper}>
					<img
						src={`${assetPrefix}${IMAGES.home.hero}`}
						alt={t.heroImgAlt}
						className={HOME_THEME.heroImage}
						fetchPriority="high"
						loading="eager"
					/>
					<div className={HOME_THEME.heroOverlay}></div>
				</div>
				<div className={HOME_THEME.heroContent}>
					<div className={HOME_THEME.heroLocationBadge}>
						<MapPin
							className={HOME_THEME.heroLocationIcon}
							aria-hidden="true"
						/>
						<span>{t.heroLocation}</span>
					</div>
					<h1 className={HOME_THEME.heroTitle}>{t.homeWelcome}</h1>
					<p className={HOME_THEME.heroSubtitle}>{t.homeSubtitle}</p>
					<p className={HOME_THEME.heroValueProp}>{t.homeHeroValueProp}</p>
					<a href="#properties" className={HOME_THEME.heroCtaBtn}>
						<CalendarIcon
							className={HOME_THEME.heroCtaIcon}
							aria-hidden="true"
						/>
						<span>{t.homeHeroCta}</span>
					</a>
				</div>
			</header>

			<main className={HOME_THEME.main}>
				{/* Properties Section */}
				<section id="properties" className={HOME_THEME.propertiesSection}>
					<h2 className={HOME_THEME.propertiesHeading}>
						{t.homeFeaturedPropertiesTitle}
					</h2>
					<div className={HOME_THEME.propertiesGrid}>
						{HOME_PROPERTY_CARDS.map((card) => {
							const cardTheme = HOME_CARD_THEMES[card.id];
							const cardHref = getPropertyPagePath(card.id, lang, assetPrefix);
							return (
								<a
									key={card.id}
									id={card.cardDomId}
									href={cardHref}
									className={cardTheme.card}
								>
									<div className={cardTheme.cardMedia}>
										<img
											src={`${assetPrefix}${IMAGES[card.imageKey].hero}`}
											className={cardTheme.cardImage}
											alt={t[card.titleKey]}
											loading="lazy"
										/>
										<div className={cardTheme.cardOverlay}></div>
										<span className={cardTheme.cardBadge}>
											{t[card.badgeKey]}
										</span>
										<div className={cardTheme.cardHeaderContent}>
											<p className={cardTheme.cardLocation}>{t.heroLocation}</p>
											<h3 className={cardTheme.cardTitle}>
												{t[card.titleKey]}
											</h3>
										</div>
									</div>
									<div className={cardTheme.cardBody}>
										<div>
											<span className={cardTheme.rateBadge}>
												{t[card.rateKey]}
											</span>
											<p className={cardTheme.cardDesc}>{t[card.descKey]}</p>
											<div className={cardTheme.cardSpecsList}>
												<span className={cardTheme.cardSpecPill}>
													{t[card.bedsKey]}
												</span>
												<span className={cardTheme.cardSpecPill}>
													{t.statsBaths}
												</span>
												<span className={cardTheme.cardSpecPill}>
													{t.statsGuests}
												</span>
												<span className={cardTheme.cardSpecPill}>
													{t[card.wifiKey]}
												</span>
												<span className={cardTheme.cardSpecHighlight}>
													{t[card.parkingKey]}
												</span>
											</div>
										</div>
										<div className={cardTheme.cardCtaRow}>
											<span>{t.homeCardCta}</span>
											<ArrowRight
												className={cardTheme.cardCtaIcon}
												aria-hidden="true"
											/>
										</div>
									</div>
								</a>
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
								<Users className={HOME_THEME.iconLg} aria-hidden="true" />
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

				{/* Why Book Direct Section */}
				<section id="why-book-direct" className={HOME_THEME.directSection}>
					<div className={HOME_THEME.directContainer}>
						<div className={HOME_THEME.directHeader}>
							<h2 className={HOME_THEME.directTitle}>{t.directTitle}</h2>
							<p className={HOME_THEME.directSubtitle}>{t.directSubtitle}</p>
						</div>

						<div className={HOME_THEME.directGrid}>
							<div className={HOME_THEME.directCard}>
								<div className={HOME_THEME.directIconEmerald}>
									<Percent className={HOME_THEME.iconLg} aria-hidden="true" />
								</div>
								<h3 className={HOME_THEME.directCardTitle}>
									{t.directReason1Title}
								</h3>
								<p className={HOME_THEME.directCardDesc}>
									{t.directReason1Desc}
								</p>
							</div>

							<div className={HOME_THEME.directCard}>
								<div className={HOME_THEME.directIconCoral}>
									<Sparkles className={HOME_THEME.iconLg} aria-hidden="true" />
								</div>
								<h3 className={HOME_THEME.directCardTitle}>
									{t.directReason2Title}
								</h3>
								<p className={HOME_THEME.directCardDesc}>
									{t.directReason2Desc}
								</p>
							</div>

							<div className={HOME_THEME.directCard}>
								<div className={HOME_THEME.directIconSky}>
									<MessageCircle
										className={HOME_THEME.iconLg}
										aria-hidden="true"
									/>
								</div>
								<h3 className={HOME_THEME.directCardTitle}>
									{t.directReason3Title}
								</h3>
								<p className={HOME_THEME.directCardDesc}>
									{t.directReason3Desc}
								</p>
							</div>

							<div className={HOME_THEME.directCard}>
								<div className={HOME_THEME.directIconEmerald}>
									<ShieldCheck
										className={HOME_THEME.iconLg}
										aria-hidden="true"
									/>
								</div>
								<h3 className={HOME_THEME.directCardTitle}>
									{t.directReason4Title}
								</h3>
								<p className={HOME_THEME.directCardDesc}>
									{t.directReason4Desc}
								</p>
							</div>
						</div>
					</div>
				</section>

				{/* Editorial Lifestyle & Host Showcase Section */}
				<section id="experiences" className={HOME_THEME.editorialSection}>
					<div className={HOME_THEME.editorialContainer}>
						<div className={HOME_THEME.editorialHeader}>
							<h2 className={HOME_THEME.editorialTitle}>{t.homeExpTitle}</h2>
						</div>

						<div className={HOME_THEME.editorialGrid}>
							{HOME_EXPERIENCE_CARDS.map((card) => {
								const cardHref = getPropertyPagePath(
									card.propertyId,
									lang,
									assetPrefix,
								);
								return (
									<a
										key={card.propertyId}
										href={cardHref}
										className={HOME_THEME.editorialCard}
									>
										<div className={HOME_THEME.editorialCardMedia}>
											<img
												src={`${assetPrefix}${card.imageSrc}`}
												alt={t[card.imageAltKey]}
												className={HOME_THEME.editorialCardImage}
												loading="lazy"
											/>
											<span className={HOME_THEME.experiencesCardBadge}>
												{t[card.titleKey]} • {t[card.badgeKey]}
											</span>
										</div>
										<div className={HOME_THEME.editorialCardBody}>
											<div className={HOME_THEME.editorialFeatureList}>
												{card.features.map((feature) => {
													const FeatureIcon =
														EXPERIENCE_FEATURE_ICONS[feature.iconId];
													return (
														<div
															key={feature.titleKey}
															className={HOME_THEME.editorialFeatureItem}
														>
															<div className={HOME_THEME[feature.iconThemeKey]}>
																<FeatureIcon
																	className={HOME_THEME.iconMd}
																	aria-hidden="true"
																/>
															</div>
															<div>
																<h3
																	className={HOME_THEME.editorialFeatureTitle}
																>
																	{t[feature.titleKey]}
																</h3>
																<p className={HOME_THEME.editorialFeatureDesc}>
																	{t[feature.descKey]}
																</p>
															</div>
														</div>
													);
												})}
											</div>
											<div className={HOME_THEME.editorialReviewBox}>
												<p className={HOME_THEME.editorialReviewText}>
													{t[card.reviewTextKey]}
												</p>
												<p className={HOME_THEME.editorialReviewAuthor}>
													— {t[card.reviewAuthorKey]}
												</p>
											</div>
											<div className={HOME_THEME.editorialCardCta}>
												<span>{t.homeCardCta}</span>
												<ArrowRight
													className={HOME_THEME.iconSm}
													aria-hidden="true"
												/>
											</div>
										</div>
									</a>
								);
							})}
						</div>

						{/* Host & Data Controller Showcase Card */}
						<div id="home-host-card" className={HOME_THEME.hostCard}>
							<div className={HOME_THEME.hostAvatarWrapper}>
								<img
									src={`${assetPrefix}${IMAGES.general.host}`}
									alt={t.hostName}
									className={HOME_THEME.hostAvatarImg}
									loading="lazy"
								/>
							</div>
							<div>
								<div className={HOME_THEME.hostHeaderRow}>
									<h3 className={HOME_THEME.hostName}>{t.hostName}</h3>
									<span className={HOME_THEME.hostRoleBadge}>
										{t.hostTitle}
									</span>
								</div>
								<p className={HOME_THEME.hostBio}>{t.hostBio}</p>
								<span className={HOME_THEME.hostRntBadge}>
									<ShieldCheck
										className={HOME_THEME.iconSm}
										aria-hidden="true"
									/>
									<span>
										{t.rntTitle}: {t.rnt1707} • {t.rnt1606}
									</span>
								</span>
							</div>
						</div>
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
								</div>
							</details>
						))}
					</div>
				</section>

				{/* Bottom Direct-Reservation & Inquiry CTA Section */}
				<section className={HOME_THEME.ctaSection}>
					<div className={HOME_THEME.ctaCard}>
						<div className={HOME_THEME.ctaContent}>
							<div className={HOME_THEME.ctaIconBox}>
								<MessageSquare
									className={HOME_THEME.iconXl}
									aria-hidden="true"
								/>
							</div>
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
