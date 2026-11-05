import { Mail } from "lucide-react";
import React from "react";
import { getContactPagePath, getHomePagePath } from "../constants/config";
import { CHROME_THEME, getChromeScrollState } from "../constants/theme";
import { dict } from "../i18n/dict";
import type { Lang } from "../types";
import { LanguageDropdown } from "./LanguageDropdown";

interface NavigationProps {
	isScrolled: boolean;
	lang: Lang;
	showBookButton?: boolean;
	assetPrefix?: string;
}

export const Navigation = ({
	isScrolled,
	lang,
	showBookButton = true,
	assetPrefix = "./",
}: NavigationProps) => {
	const t = dict[lang];
	const scrollState = getChromeScrollState(isScrolled);
	const homeHref = getHomePagePath(lang, assetPrefix);
	const contactHref = getContactPagePath(lang, assetPrefix);

	return (
		<nav className={CHROME_THEME.nav[scrollState]} id="navbar">
			<div className={CHROME_THEME.navContainer}>
				<a
					href={homeHref}
					className={CHROME_THEME.navLogo[scrollState]}
					id="nav-logo"
				>
					<svg
						viewBox="0 0 96 96"
						className={CHROME_THEME.navLogoIcon}
						xmlns="http://www.w3.org/2000/svg"
					>
						<path d="M83.52,64.09a38.86,38.86,0,1,0-2.13,4.07c.31-.5.59-1,.87-1.55l.09.06A20.48,20.48,0,0,0,83.52,64.09ZM51,57.24a13.9,13.9,0,1,1,27,4.42c-.11.24-.22.48-.34.72C70.68,59.36,61.28,57.54,51,57.24ZM48,15A33,33,0,0,1,80.88,45.26,20.1,20.1,0,0,0,79,43a19.8,19.8,0,0,0-9.77-5.34V27.2a3,3,0,0,0-6,0V37.29a19.93,19.93,0,0,0-7.08,2L50.5,30.75a3,3,0,1,0-5,3.3l5.7,8.64-.37.34A20.06,20.06,0,0,0,47,48.4l-8.32-3.2a3,3,0,1,0-2.16,5.6l8.71,3.35A19.87,19.87,0,0,0,45,57.23c-10.21.29-19.68,2.09-26.73,5.09A33,33,0,0,1,48,15Zm0,66A33.13,33.13,0,0,1,21.4,67.51c6.89-2.73,16.5-4.32,26.5-4.32,10.17,0,19.77,1.61,26.66,4.38A32.74,32.74,0,0,1,48,81Z" />
					</svg>
					<span>OceanViewFlats</span>
				</a>
				<div className={CHROME_THEME.navActions}>
					<a
						id="nav-contact"
						href={contactHref}
						className={CHROME_THEME.navContact[scrollState]}
						aria-label={t.navContact}
					>
						<Mail className={CHROME_THEME.navContactIcon} />
						<span className={CHROME_THEME.navContactText}>{t.navContact}</span>
					</a>
					<LanguageDropdown currentLang={lang} isScrolled={isScrolled} />
					{showBookButton && (
						<a href="#booking" className={CHROME_THEME.navBookBtn}>
							{t.navBook}
						</a>
					)}
				</div>
			</div>
		</nav>
	);
};
