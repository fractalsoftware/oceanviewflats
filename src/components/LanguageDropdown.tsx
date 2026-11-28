import { ChevronDown, Globe } from "lucide-react";
import React from "react";
import { CHROME_THEME } from "../constants/theme";
import { dict } from "../i18n/dict";
import type { Lang } from "../types";

interface LanguageDropdownProps {
	currentLang: Lang;
	englishHref?: string;
}

const LANGUAGES: {
	code: Lang;
	shortCode: string;
	label: string;
	link: string;
}[] = [
	{ code: "en", shortCode: "EN", label: "English", link: "./" },
	{ code: "es", shortCode: "ES", label: "Español", link: "es.html" },
	{ code: "fr", shortCode: "FR", label: "Français", link: "fr.html" },
	{ code: "it", shortCode: "IT", label: "Italiano", link: "it.html" },
	{ code: "de", shortCode: "DE", label: "Deutsch", link: "de.html" },
	{ code: "ja", shortCode: "JA", label: "日本語", link: "ja.html" },
];

export const LanguageDropdown = ({
	currentLang,
	englishHref = "./",
}: LanguageDropdownProps) => {
	const t = dict[currentLang];
	const currentLangObj =
		LANGUAGES.find((l) => l.code === currentLang) || LANGUAGES[0];
	const otherLangs = LANGUAGES.filter((l) => l.code !== currentLang);

	return (
		<div className={CHROME_THEME.langGroup} id="lang-toggle-group">
			<button
				type="button"
				className={CHROME_THEME.langToggle}
				id="lang-toggle"
				aria-label={`${t.navLangAriaLabel}: ${currentLangObj.label}`}
				aria-haspopup="true"
				aria-expanded="false"
				aria-controls="lang-dropdown"
			>
				<Globe className={CHROME_THEME.langGlobeIcon} aria-hidden="true" />
				<span className={CHROME_THEME.langCodeMobile} aria-hidden="true">
					{currentLangObj.shortCode}
				</span>
				<span className={CHROME_THEME.langLabelDesktop}>
					{currentLangObj.label}
				</span>
				<ChevronDown
					className={CHROME_THEME.langChevronIcon}
					aria-hidden="true"
				/>
			</button>

			{/* Dropdown Menu */}
			<div id="lang-dropdown" className={CHROME_THEME.langDropdownWrapper}>
				<div className={CHROME_THEME.langDropdownCard}>
					<ul className={CHROME_THEME.langDropdownList}>
						{otherLangs.map((lang) => (
							<li key={lang.code}>
								<a
									href={lang.code === "en" ? englishHref : lang.link}
									className={CHROME_THEME.langDropdownItem}
									data-lang-target={lang.code}
								>
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
