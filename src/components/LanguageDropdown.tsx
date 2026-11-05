import { ChevronDown, Globe } from "lucide-react";
import React from "react";
import { CHROME_THEME, getChromeScrollState } from "../constants/theme";
import type { Lang } from "../types";

interface LanguageDropdownProps {
	currentLang: Lang;
	isScrolled: boolean;
}

const LANGUAGES: { code: Lang; label: string; link: string }[] = [
	{ code: "en", label: "English", link: "index.html" },
	{ code: "es", label: "Español", link: "es.html" },
	{ code: "fr", label: "Français", link: "fr.html" },
	{ code: "it", label: "Italiano", link: "it.html" },
	{ code: "de", label: "Deutsch", link: "de.html" },
	{ code: "ja", label: "日本語", link: "ja.html" },
];

export const LanguageDropdown = ({
	currentLang,
	isScrolled,
}: LanguageDropdownProps) => {
	const currentLangObj =
		LANGUAGES.find((l) => l.code === currentLang) || LANGUAGES[0];
	const otherLangs = LANGUAGES.filter((l) => l.code !== currentLang);
	const scrollState = getChromeScrollState(isScrolled);

	return (
		<div className={CHROME_THEME.langGroup} id="lang-toggle-group">
			<button
				type="button"
				className={CHROME_THEME.langToggle[scrollState]}
				id="lang-toggle"
				aria-haspopup="true"
				aria-expanded="false"
				aria-controls="lang-dropdown"
			>
				<Globe className={CHROME_THEME.langGlobeIcon} />
				<span>{currentLangObj.label}</span>
				<ChevronDown className={CHROME_THEME.langChevronIcon} />
			</button>

			{/* Dropdown Menu */}
			<div id="lang-dropdown" className={CHROME_THEME.langDropdownWrapper}>
				<div className={CHROME_THEME.langDropdownCard}>
					<ul className={CHROME_THEME.langDropdownList}>
						{otherLangs.map((lang) => (
							<li key={lang.code}>
								<a href={lang.link} className={CHROME_THEME.langDropdownItem}>
									{lang.label}
								</a>
							</li>
						))}
					</ul>
				</div>
			</div>
		</div>
	);
};
