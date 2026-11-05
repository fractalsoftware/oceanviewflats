import React from "react";
import { getPrivacyPolicyPath } from "../constants/config";
import { CHROME_THEME } from "../constants/theme";
import { dict } from "../i18n/dict";
import type { Lang } from "../types";

export const Footer = ({
	lang,
	assetPrefix = "../",
}: {
	lang: Lang;
	assetPrefix?: string;
}) => {
	const t = dict[lang];
	const privacyHref = getPrivacyPolicyPath(lang, assetPrefix);

	return (
		<footer className={CHROME_THEME.footer}>
			<div className={CHROME_THEME.footerContainer}>
				<svg
					viewBox="0 0 96 96"
					className={CHROME_THEME.footerLogo}
					xmlns="http://www.w3.org/2000/svg"
				>
					<path d="M83.52,64.09a38.86,38.86,0,1,0-2.13,4.07c.31-.5.59-1,.87-1.55l.09.06A20.48,20.48,0,0,0,83.52,64.09ZM51,57.24a13.9,13.9,0,1,1,27,4.42c-.11.24-.22.48-.34.72C70.68,59.36,61.28,57.54,51,57.24ZM48,15A33,33,0,0,1,80.88,45.26,20.1,20.1,0,0,0,79,43a19.8,19.8,0,0,0-9.77-5.34V27.2a3,3,0,0,0-6,0V37.29a19.93,19.93,0,0,0-7.08,2L50.5,30.75a3,3,0,1,0-5,3.3l5.7,8.64-.37.34A20.06,20.06,0,0,0,47,48.4l-8.32-3.2a3,3,0,1,0-2.16,5.6l8.71,3.35A19.87,19.87,0,0,0,45,57.23c-10.21.29-19.68,2.09-26.73,5.09A33,33,0,0,1,48,15Zm0,66A33.13,33.13,0,0,1,21.4,67.51c6.89-2.73,16.5-4.32,26.5-4.32,10.17,0,19.77,1.61,26.66,4.38A32.74,32.74,0,0,1,48,81Z" />
				</svg>
				<p className={CHROME_THEME.footerHostText}>{t.footer}</p>
				<p className={CHROME_THEME.footerRntText}>
					{t.rntTitle}: {t.rnt1707} • {t.rnt1606}
				</p>
				<p className={CHROME_THEME.footerPrivacyWrapper}>
					<a
						id="footer-privacy-link"
						href={privacyHref}
						className={CHROME_THEME.footerPrivacyLink}
					>
						{t.footerPrivacyLink}
					</a>
				</p>
				<p className={CHROME_THEME.footerCopyright}>
					© {new Date().getFullYear()} {t.footerRights}
				</p>

				<a
					href="https://fractalsoftware.com"
					target="_blank"
					rel="noopener noreferrer"
					className={CHROME_THEME.footerBadge}
				>
					<span className={CHROME_THEME.footerBadgeDot} />
					<span>{t.footerEngineeredBy}</span>
					<svg
						xmlns="http://www.w3.org/2000/svg"
						viewBox="0 0 202.22 202.22"
						className={CHROME_THEME.footerBadgeLogo}
					>
						<path
							d="m56.47 134.56 31.58-39.4h92.08l-33.87 44.97h-49v29.37h68.87l-33.48 44.72-35.43-.06.03 27.5-40.78 35.8zm31.5 106.47-9.24-4.35-8.76 20.27zm-14.92-7.83-9.85-9.7.03 32.24zm17.6-12.14-7.84 9.5 7.81 4.35zM73.3 204.2l-8.83 11.48 9.1 8.06zm15.71 7.77-8.83-6.98.2 17.55zm25.07-19.73-11.84 15.37h21.99zm-24.43-18.78-26.43-28.83-.03 61.16Zm49.1 21.58h-14.78l6.94 10.18zm-48.14-11.86-10.4 12.91 10.37 8.42zm18.38 4.1-11.72-7.86-.03 23.72zm29.27.82-6.4-9.6-7.1 9.56zM153 176.14l-14.51-.03 6.71 9.99zm-27.65-.06-20.89.02 13.54 9.73zm-34.72-31.56-9.3 9.63 9.31 10.32zm-17.03-20.5-9.37 11.74 9.28 9.92zm15.72 12.46-8.87-10.6-.11 19.28zm48.4-3.22-16.02-24.8-2.74 12.01-15.62 12.98zm-29.37-12.74-23.6-.01 9.64 11.45zm44.26-.13-15.42.01 7.38 10.74zm-36.28-18.35h-18.4l15.22 13.79zm35.85 11.85-6.48-9.46-7.54 9.45zm-62.5-10.2-8.12 10.16 19.15.04zm76.8-1.65H152.3l6.78 10.05zm-27.4 0h-13.04l5.72 9.52z"
							transform="translate(-17.14 -85.2)"
						/>
					</svg>
					<span className={CHROME_THEME.footerBadgeBrand}>FractalSoftware</span>
				</a>
			</div>
		</footer>
	);
};
