import { ArrowRight, ChevronDown, Menu, X } from "lucide-react";
import React from "react";
import {
	getContactPagePath,
	getDestinationGuidesHubPath,
	getGuidePagePath,
	getHomePagePath,
	getPropertyPagePath,
	getRegistryPagePath,
} from "../constants/config";
import { CHROME_THEME } from "../constants/theme";
import { dict } from "../i18n/dict";
import type { Lang } from "../types";
import { LanguageDropdown } from "./LanguageDropdown";

export type ActiveNavPage =
	| "home"
	| "1707"
	| "1606"
	| "guides"
	| "contact"
	| "registry"
	| "guide"
	| "privacy";

interface NavigationProps {
	isScrolled: boolean;
	lang: Lang;
	showBookButton?: boolean;
	bookHref?: string;
	bookLabelKey?: "navBook" | "navBookDirect";
	assetPrefix?: string;
	englishHref?: string;
	currentPage?: ActiveNavPage;
}

export const Navigation = ({
	isScrolled,
	lang,
	showBookButton = true,
	bookHref = "#booking",
	bookLabelKey = "navBook",
	assetPrefix = "./",
	englishHref = "./",
	currentPage,
}: NavigationProps) => {
	const t = dict[lang];
	const homeHref = getHomePagePath(lang, assetPrefix);
	const guidesHref = getDestinationGuidesHubPath(lang, assetPrefix);
	const contactHref = getContactPagePath(lang, assetPrefix);
	const property1707Href = getPropertyPagePath("1707", lang, assetPrefix);
	const property1606Href = getPropertyPagePath("1606", lang, assetPrefix);
	const registryHref = getRegistryPagePath(lang, assetPrefix);
	const guideHref = getGuidePagePath(lang, assetPrefix);
	const isCheckinActive = currentPage === "registry" || currentPage === "guide";

	return (
		<>
			<a
				id="skip-to-content"
				href="#main-content"
				className={CHROME_THEME.skipLink}
			>
				{t.skipToMainContent}
			</a>
			<nav
				aria-label={t.navPrimaryLabel}
				className={CHROME_THEME.nav}
				id="navbar"
				data-scrolled={isScrolled ? "true" : "false"}
			>
				<div className={CHROME_THEME.navContainer}>
					<a
						href={homeHref}
						className={CHROME_THEME.navLogo}
						id="nav-logo"
						aria-current={currentPage === "home" ? "page" : undefined}
					>
						<svg
							viewBox="0 0 96 96"
							className={CHROME_THEME.navLogoIcon}
							xmlns="http://www.w3.org/2000/svg"
							aria-hidden="true"
						>
							<path d="M83.52,64.09a38.86,38.86,0,1,0-2.13,4.07c.31-.5.59-1,.87-1.55l.09.06A20.48,20.48,0,0,0,83.52,64.09ZM51,57.24a13.9,13.9,0,1,1,27,4.42c-.11.24-.22.48-.34.72C70.68,59.36,61.28,57.54,51,57.24ZM48,15A33,33,0,0,1,80.88,45.26,20.1,20.1,0,0,0,79,43a19.8,19.8,0,0,0-9.77-5.34V27.2a3,3,0,0,0-6,0V37.29a19.93,19.93,0,0,0-7.08,2L50.5,30.75a3,3,0,1,0-5,3.3l5.7,8.64-.37.34A20.06,20.06,0,0,0,47,48.4l-8.32-3.2a3,3,0,1,0-2.16,5.6l8.71,3.35A19.87,19.87,0,0,0,45,57.23c-10.21.29-19.68,2.09-26.73,5.09A33,33,0,0,1,48,15Zm0,66A33.13,33.13,0,0,1,21.4,67.51c6.89-2.73,16.5-4.32,26.5-4.32,10.17,0,19.77,1.61,26.66,4.38A32.74,32.74,0,0,1,48,81Z" />
						</svg>
						<span>OceanViewFlats</span>
					</a>
					<div className={CHROME_THEME.navActions}>
						<a
							id="nav-guides"
							href={guidesHref}
							className={CHROME_THEME.navGuides}
							aria-label={t.navGuidesAriaLabel}
							aria-current={currentPage === "guides" ? "page" : undefined}
						>
							<span>{t.navGuides}</span>
						</a>
						<div
							id="nav-checkin-group"
							className={CHROME_THEME.navCheckinGroup}
						>
							<button
								id="nav-checkin-toggle"
								type="button"
								className={CHROME_THEME.navCheckinToggle}
								aria-expanded="false"
								aria-controls="nav-checkin-menu"
								aria-label={t.navStayingGuestsOpenAriaLabel}
								data-active={isCheckinActive ? "true" : undefined}
							>
								<span>{t.navStayingGuests}</span>
								<ChevronDown
									className={CHROME_THEME.navCheckinChevron}
									aria-hidden="true"
								/>
							</button>
							<div
								id="nav-checkin-menu"
								className={CHROME_THEME.navCheckinMenuWrapper}
								hidden
							>
								<div className={CHROME_THEME.navCheckinMenuCard}>
									<ul className={CHROME_THEME.navCheckinMenuList}>
										<li>
											<a
												id="nav-checkin-registry"
												href={registryHref}
												className={CHROME_THEME.navCheckinMenuLink}
												aria-current={
													currentPage === "registry" ? "page" : undefined
												}
											>
												<span>{t.registryTitle}</span>
												<ArrowRight
													className={CHROME_THEME.navCheckinMenuLinkIcon}
													aria-hidden="true"
												/>
											</a>
										</li>
										<li>
											<a
												id="nav-checkin-guide"
												href={guideHref}
												className={CHROME_THEME.navCheckinMenuLink}
												aria-current={
													currentPage === "guide" ? "page" : undefined
												}
											>
												<span>{t.navGuestGuide}</span>
												<ArrowRight
													className={CHROME_THEME.navCheckinMenuLinkIcon}
													aria-hidden="true"
												/>
											</a>
										</li>
									</ul>
								</div>
							</div>
						</div>
						<a
							id="nav-contact"
							href={contactHref}
							className={CHROME_THEME.navContact}
							aria-current={currentPage === "contact" ? "page" : undefined}
						>
							<span>{t.navContact}</span>
						</a>
						<LanguageDropdown currentLang={lang} englishHref={englishHref} />
						{showBookButton && (
							<a
								id="nav-book"
								href={bookHref}
								className={CHROME_THEME.navBookBtn}
							>
								{t[bookLabelKey]}
							</a>
						)}
						<button
							id="mobile-nav-toggle"
							type="button"
							className={CHROME_THEME.navMenuToggle}
							aria-expanded="false"
							aria-controls="mobile-nav-drawer"
							aria-label={t.navMenuOpenAriaLabel}
						>
							<Menu className={CHROME_THEME.navMenuIcon} aria-hidden="true" />
						</button>
					</div>
				</div>

				{/* Accessible Collapsible Mobile Navigation Drawer */}
				<div
					id="mobile-nav-drawer"
					className={CHROME_THEME.mobileDrawer}
					hidden
				>
					<div className={CHROME_THEME.mobileDrawerHeader}>
						<span className={CHROME_THEME.mobileDrawerTitle}>
							{t.navMenuLabel}
						</span>
						<button
							id="mobile-nav-close"
							type="button"
							className={CHROME_THEME.mobileDrawerCloseBtn}
							aria-label={t.navMenuCloseAriaLabel}
						>
							<X className={CHROME_THEME.navMenuIcon} aria-hidden="true" />
						</button>
					</div>
					<div className={CHROME_THEME.mobileDrawerGroups}>
						<div>
							<p className={CHROME_THEME.mobileDrawerGroupHeading}>
								{t.footerPropertiesLabel}
							</p>
							<ul className={CHROME_THEME.mobileDrawerList}>
								<li>
									<a
										href={property1707Href}
										className={CHROME_THEME.mobileDrawerLink}
										aria-current={currentPage === "1707" ? "page" : undefined}
									>
										<span>{t.heroTitle}</span>
										<ArrowRight
											className={CHROME_THEME.mobileDrawerLinkIcon}
											aria-hidden="true"
										/>
									</a>
								</li>
								<li>
									<a
										href={property1606Href}
										className={CHROME_THEME.mobileDrawerLink}
										aria-current={currentPage === "1606" ? "page" : undefined}
									>
										<span>{t.hero1606Title}</span>
										<ArrowRight
											className={CHROME_THEME.mobileDrawerLinkIcon}
											aria-hidden="true"
										/>
									</a>
								</li>
							</ul>
						</div>
						<div>
							<p className={CHROME_THEME.mobileDrawerGroupHeading}>
								{t.footerGuidesLabel}
							</p>
							<ul className={CHROME_THEME.mobileDrawerList}>
								<li>
									<a
										href={guidesHref}
										className={CHROME_THEME.mobileDrawerLink}
										aria-current={currentPage === "guides" ? "page" : undefined}
									>
										<span>{t.footerGuidesHubLink}</span>
										<ArrowRight
											className={CHROME_THEME.mobileDrawerLinkIcon}
											aria-hidden="true"
										/>
									</a>
								</li>
							</ul>
						</div>
						<div>
							<p className={CHROME_THEME.mobileDrawerGroupHeading}>
								{t.footerStayingGuestsLabel}
							</p>
							<ul className={CHROME_THEME.mobileDrawerList}>
								<li>
									<a
										href={registryHref}
										className={CHROME_THEME.mobileDrawerLink}
										aria-current={
											currentPage === "registry" ? "page" : undefined
										}
									>
										<span>{t.registryTitle}</span>
										<ArrowRight
											className={CHROME_THEME.mobileDrawerLinkIcon}
											aria-hidden="true"
										/>
									</a>
								</li>
								<li>
									<a
										href={guideHref}
										className={CHROME_THEME.mobileDrawerLink}
										aria-current={currentPage === "guide" ? "page" : undefined}
									>
										<span>{t.navGuestGuide}</span>
										<ArrowRight
											className={CHROME_THEME.mobileDrawerLinkIcon}
											aria-hidden="true"
										/>
									</a>
								</li>
								<li>
									<a
										href={contactHref}
										className={CHROME_THEME.mobileDrawerLink}
										aria-current={
											currentPage === "contact" ? "page" : undefined
										}
									>
										<span>{t.navContact}</span>
										<ArrowRight
											className={CHROME_THEME.mobileDrawerLinkIcon}
											aria-hidden="true"
										/>
									</a>
								</li>
							</ul>
						</div>
					</div>
				</div>
			</nav>
		</>
	);
};
