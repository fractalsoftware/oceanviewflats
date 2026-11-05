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
	getContactPagePath,
	getPropertyPagePath,
	IMAGES,
} from "../constants/config";
import { HOME_THEME } from "../constants/theme";
import { dict } from "../i18n/dict";
import type { AppProps } from "../types";

export default function Home({ lang, assetPrefix = "./" }: AppProps) {
	const t = dict[lang];
	const property1707Href = getPropertyPagePath("1707", lang, assetPrefix);
	const property1606Href = getPropertyPagePath("1606", lang, assetPrefix);
	const contactHref = getContactPagePath(lang, assetPrefix);
	const faqItems = [
		{ q: t.faqQ1, a: t.faqA1, defaultOpen: true },
		{ q: t.faqQ2, a: t.faqA2, defaultOpen: false },
		{ q: t.faqQ3, a: t.faqA3, defaultOpen: false },
		{ q: t.faqQ4, a: t.faqA4, defaultOpen: false },
		{ q: t.faqQ5, a: t.faqA5, defaultOpen: false },
		{ q: t.faqQ6, a: t.faqA6, defaultOpen: false },
	];

	return (
		<div className={HOME_THEME.pageWrapper}>
			<Navigation
				isScrolled={false}
				lang={lang}
				showBookButton={true}
				bookHref="#properties"
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
					<h2 className={HOME_THEME.propertiesSrHeading}>
						Our Featured Properties
					</h2>
					<div className={HOME_THEME.propertiesGrid}>
						<a
							id="home-card-1707"
							href={property1707Href}
							className={HOME_THEME.card1707.card}
						>
							<div className={HOME_THEME.card1707.cardMedia}>
								<img
									src={`${assetPrefix}${IMAGES["1707"].hero}`}
									className={HOME_THEME.card1707.cardImage}
									alt={t.heroTitle}
									loading="lazy"
								/>
								<div className={HOME_THEME.card1707.cardOverlay}></div>
								<span className={HOME_THEME.card1707.cardBadge}>
									{t.homeCard1707Badge}
								</span>
								<div className={HOME_THEME.card1707.cardHeaderContent}>
									<p className={HOME_THEME.card1707.cardLocation}>
										{t.heroLocation}
									</p>
									<h3 className={HOME_THEME.card1707.cardTitle}>
										{t.heroTitle}
									</h3>
								</div>
							</div>
							<div className={HOME_THEME.card1707.cardBody}>
								<div>
									<p className={HOME_THEME.card1707.cardDesc}>{t.nomadDesc}</p>
									<div className={HOME_THEME.card1707.cardSpecsList}>
										<span className={HOME_THEME.card1707.cardSpecPill}>
											{t.statsBeds}
										</span>
										<span className={HOME_THEME.card1707.cardSpecPill}>
											{t.statsBaths}
										</span>
										<span className={HOME_THEME.card1707.cardSpecPill}>
											{t.statsGuests}
										</span>
										<span className={HOME_THEME.card1707.cardSpecHighlight}>
											{t.homeCard1707Parking}
										</span>
									</div>
								</div>
								<div className={HOME_THEME.card1707.cardCtaRow}>
									<span>{t.homeCardCta}</span>
									<ArrowRight
										className={HOME_THEME.card1707.cardCtaIcon}
										aria-hidden="true"
									/>
								</div>
							</div>
						</a>

						<a
							id="home-card-1606"
							href={property1606Href}
							className={HOME_THEME.card1606.card}
						>
							<div className={HOME_THEME.card1606.cardMedia}>
								<img
									src={`${assetPrefix}${IMAGES["1606"].hero}`}
									className={HOME_THEME.card1606.cardImage}
									alt={t.hero1606Title}
									loading="lazy"
								/>
								<div className={HOME_THEME.card1606.cardOverlay}></div>
								<span className={HOME_THEME.card1606.cardBadge}>
									{t.homeCard1606Badge}
								</span>
								<div className={HOME_THEME.card1606.cardHeaderContent}>
									<p className={HOME_THEME.card1606.cardLocation}>
										{t.heroLocation}
									</p>
									<h3 className={HOME_THEME.card1606.cardTitle}>
										{t.hero1606Title}
									</h3>
								</div>
							</div>
							<div className={HOME_THEME.card1606.cardBody}>
								<div>
									<p className={HOME_THEME.card1606.cardDesc}>
										{t.romanticDesc}
									</p>
									<div className={HOME_THEME.card1606.cardSpecsList}>
										<span className={HOME_THEME.card1606.cardSpecPill}>
											{t.stats1606Beds}
										</span>
										<span className={HOME_THEME.card1606.cardSpecPill}>
											{t.statsBaths}
										</span>
										<span className={HOME_THEME.card1606.cardSpecPill}>
											{t.statsGuests}
										</span>
										<span className={HOME_THEME.card1606.cardSpecHighlight}>
											{t.homeCard1606Parking}
										</span>
									</div>
								</div>
								<div className={HOME_THEME.card1606.cardCtaRow}>
									<span>{t.homeCardCta}</span>
									<ArrowRight
										className={HOME_THEME.card1606.cardCtaIcon}
										aria-hidden="true"
									/>
								</div>
							</div>
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
									<Percent className="w-6 h-6" aria-hidden="true" />
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
									<Sparkles className="w-6 h-6" aria-hidden="true" />
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
									<MessageCircle className="w-6 h-6" aria-hidden="true" />
								</div>
								<h3 className={HOME_THEME.directCardTitle}>
									{t.directReason3Title}
								</h3>
								<p className={HOME_THEME.directCardDesc}>
									{t.directReason3Desc}
								</p>
							</div>

							<div className={HOME_THEME.directCard}>
								<div className={HOME_THEME.directIconRose}>
									<ShieldCheck className="w-6 h-6" aria-hidden="true" />
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
							<a href={property1707Href} className={HOME_THEME.editorialCard}>
								<div className={HOME_THEME.editorialCardMedia}>
									<img
										src={`${assetPrefix}${IMAGES["1707"].nomad}`}
										alt={t.homeExpNomad}
										className={HOME_THEME.editorialCardImage}
										loading="lazy"
									/>
								</div>
								<div className={HOME_THEME.editorialCardBody}>
									<div className={HOME_THEME.editorialFeatureList}>
										<div className={HOME_THEME.editorialFeatureItem}>
											<div className={HOME_THEME.editorialFeatureIconSky}>
												<Wifi className="w-5 h-5" aria-hidden="true" />
											</div>
											<div>
												<h3 className={HOME_THEME.editorialFeatureTitle}>
													{t.homeExpNomad}
												</h3>
												<p className={HOME_THEME.editorialFeatureDesc}>
													{t.homeExpNomadDesc}
												</p>
											</div>
										</div>
									</div>
									<div className={HOME_THEME.editorialCardCta}>
										<span>{t.homeCardCta}</span>
										<ArrowRight
											className="w-4 h-4 shrink-0"
											aria-hidden="true"
										/>
									</div>
								</div>
							</a>

							<a href={property1606Href} className={HOME_THEME.editorialCard}>
								<div className={HOME_THEME.editorialCardMedia}>
									<img
										src={`${assetPrefix}${IMAGES["1606"].romantic}`}
										alt={t.homeExpRomantic}
										className={HOME_THEME.editorialCardImage}
										loading="lazy"
									/>
								</div>
								<div className={HOME_THEME.editorialCardBody}>
									<div className={HOME_THEME.editorialFeatureList}>
										<div className={HOME_THEME.editorialFeatureItem}>
											<div className={HOME_THEME.editorialFeatureIconRose}>
												<Heart className="w-5 h-5" aria-hidden="true" />
											</div>
											<div>
												<h3 className={HOME_THEME.editorialFeatureTitle}>
													{t.homeExpRomantic}
												</h3>
												<p className={HOME_THEME.editorialFeatureDesc}>
													{t.homeExpRomanticDesc}
												</p>
											</div>
										</div>
										<div className={HOME_THEME.editorialFeatureItem}>
											<div className={HOME_THEME.editorialFeatureIconEmerald}>
												<Users className="w-5 h-5" aria-hidden="true" />
											</div>
											<div>
												<h3 className={HOME_THEME.editorialFeatureTitle}>
													{t.homeExpFamily}
												</h3>
												<p className={HOME_THEME.editorialFeatureDesc}>
													{t.homeExpFamilyDesc}
												</p>
											</div>
										</div>
									</div>
									<div className={HOME_THEME.editorialCardCta}>
										<span>{t.homeCardCta}</span>
										<ArrowRight
											className="w-4 h-4 shrink-0"
											aria-hidden="true"
										/>
									</div>
								</div>
							</a>
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
										className="w-4 h-4 shrink-0"
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
						{faqItems.map((item, idx) => (
							<details
								key={idx}
								className={HOME_THEME.faqDetails}
								open={item.defaultOpen}
							>
								<summary className={HOME_THEME.faqSummary}>
									<span>{item.q}</span>
									<ChevronDown
										className={HOME_THEME.faqChevron}
										aria-hidden="true"
									/>
								</summary>
								<div className={HOME_THEME.faqAnswerWrapper}>
									<p className={HOME_THEME.faqAnswer}>{item.a}</p>
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
								<MessageSquare className="w-7 h-7" aria-hidden="true" />
							</div>
							<h2 className={HOME_THEME.ctaTitle}>{t.contactTitle}</h2>
							<p className={HOME_THEME.ctaSubtitle}>{t.contactSub}</p>
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
									<ArrowRight className="w-5 h-5 shrink-0" aria-hidden="true" />
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
