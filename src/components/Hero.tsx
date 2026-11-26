import { Calendar as CalendarIcon, MapPin } from "lucide-react";
import React from "react";
import { getHeroImageSrcSet, HERO_IMAGE_SIZES } from "../constants/config";
import { THEMES } from "../constants/theme";
import { dict } from "../i18n/dict";
import type { Lang } from "../types";

export const Hero = ({
	lang,
	propertyId = "1707",
	assetPrefix = "./",
}: {
	lang: Lang;
	propertyId?: "1707" | "1606";
	assetPrefix?: string;
}) => {
	const t = dict[lang];
	const theme = THEMES[propertyId];

	return (
		<header className="relative h-[85vh] flex items-center justify-center pt-20">
			<div className="absolute inset-0 w-full h-full">
				<img
					src={`${assetPrefix}${theme.images.hero}`}
					srcSet={getHeroImageSrcSet(theme.images.hero, assetPrefix)}
					sizes={HERO_IMAGE_SIZES}
					alt={t[theme.heroImgAltKey]}
					width={theme.imageDimensions.hero.width}
					height={theme.imageDimensions.hero.height}
					className="w-full h-full object-cover"
					fetchPriority="high"
					loading="eager"
				/>
				<div className={`absolute inset-0 ${theme.heroOverlayGradient}`}></div>
			</div>
			<div className="relative z-10 text-center px-6 max-w-4xl mx-auto mt-10">
				<div className="inline-flex items-center space-x-2 bg-white/20 backdrop-blur-md px-4 py-1.5 rounded-full text-white text-sm font-medium mb-6">
					<MapPin className="w-4 h-4" />
					<span>{t.heroLocation}</span>
				</div>
				<h1 className="text-4xl md:text-6xl font-extrabold text-white mb-6 leading-tight drop-shadow-lg">
					{t[theme.heroTitleKey]}
				</h1>
				<p
					className={`text-lg md:text-2xl ${theme.heroSubtitleColor} mb-10 font-light drop-shadow`}
				>
					{t[theme.heroSubKey]}
				</p>
				<a
					href="#booking"
					className={`inline-flex items-center space-x-2 bg-white ${theme.heroBtnTxtColor} px-8 py-4 rounded-full font-bold text-lg hover:bg-slate-100 transition-colors shadow-2xl`}
				>
					<CalendarIcon className={`w-5 h-5 ${theme.heroIconColor}`} />
					<span>{t.navBook}</span>
				</a>
			</div>
		</header>
	);
};
