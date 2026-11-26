import {
	Car,
	Coffee,
	Globe,
	Lock,
	MapPin,
	Waves,
	Wifi,
	Wind,
} from "lucide-react";
import React from "react";
import { IMAGE_DIMENSIONS, IMAGES } from "../constants/config";
import { THEMES } from "../constants/theme";
import { dict } from "../i18n/dict";
import type { Lang } from "../types";

export const AboutAmenities = ({
	lang,
	propertyId,
	assetPrefix = "./",
}: {
	lang: Lang;
	propertyId: "1707" | "1606";
	assetPrefix?: string;
}) => {
	const t = dict[lang];
	const theme = THEMES[propertyId];

	return (
		<section className="py-24 px-6 max-w-6xl mx-auto">
			<div className="grid md:grid-cols-2 gap-16">
				<div>
					<h2 className={`text-3xl font-bold mb-6 ${theme.headingColor}`}>
						{t.aboutTitle}
					</h2>
					<div className={`prose prose-lg ${theme.textColor} mb-8`}>
						<p className="mb-4 leading-relaxed">{t.aboutText1}</p>
						<p className="leading-relaxed">{t.aboutText2}</p>
					</div>
					<div className="grid grid-cols-2 gap-4">
						<img
							src={`${assetPrefix}${theme.images.bedroom}`}
							alt={t[theme.aboutBedroomAltKey]}
							width={theme.imageDimensions.bedroom.width}
							height={theme.imageDimensions.bedroom.height}
							className="rounded-xl shadow-md h-48 w-full object-cover"
							loading="lazy"
							decoding="async"
						/>
						<img
							src={`${assetPrefix}${theme.images.pool}`}
							alt={t[theme.aboutPoolAltKey]}
							width={theme.imageDimensions.pool.width}
							height={theme.imageDimensions.pool.height}
							className="rounded-xl shadow-md h-48 w-full object-cover"
							loading="lazy"
							decoding="async"
						/>
					</div>
				</div>
				<div>
					<h2 className={`text-3xl font-bold mb-8 ${theme.headingColor}`}>
						{t.amenitiesTitle}
					</h2>
					<div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
						{[
							{ icon: <Wifi className="w-6 h-6" />, text: t.amWifi },
							{ icon: <Wind className="w-6 h-6" />, text: t.amAC },
							{ icon: <Waves className="w-6 h-6" />, text: t.amBeach },
							{ icon: <Coffee className="w-6 h-6" />, text: t.amKitchen },
							{ icon: <Globe className="w-6 h-6" />, text: t.amPool },
							{ icon: <Car className="w-6 h-6" />, text: t.amParking },
							{ icon: <Lock className="w-6 h-6" />, text: t.amSelfCheckIn },
							{ icon: <MapPin className="w-6 h-6" />, text: t.amOcean },
						].map((item, index) => (
							<div
								key={index}
								className={`flex items-center space-x-4 p-4 bg-white rounded-xl shadow-sm border ${theme.borderColor} hover:shadow-md transition-shadow`}
							>
								<div className={`${theme.aboutThemeAccent} p-2 rounded-lg`}>
									{item.icon}
								</div>
								<span className={`font-semibold ${theme.textColor}`}>
									{item.text}
								</span>
							</div>
						))}
					</div>
				</div>
			</div>

			<div
				className={`mt-20 p-8 md:p-12 ${theme.aboutHostBg} rounded-3xl text-white flex flex-col md:flex-row items-center gap-10`}
			>
				<div
					className={`w-32 h-32 md:w-48 md:h-48 rounded-full overflow-hidden flex-shrink-0 border-4 ${theme.aboutHostBorder}`}
				>
					<img
						src={`${assetPrefix}${IMAGES.general.host}`}
						alt={t.hostImgAlt}
						width={IMAGE_DIMENSIONS.host.width}
						height={IMAGE_DIMENSIONS.host.height}
						className="w-full h-full object-cover"
						loading="lazy"
						decoding="async"
					/>
				</div>
				<div>
					<p
						className={`text-sm font-bold uppercase tracking-widest ${theme.aboutHostAccent} mb-2`}
					>
						{t.hostTitle}
					</p>
					<h2 className={`text-3xl font-bold mb-4 ${theme.aboutHostName}`}>
						{t.hostName}
					</h2>
					<p className="text-lg text-slate-300 leading-relaxed max-w-2xl">
						{t.hostBio}
					</p>
				</div>
			</div>
		</section>
	);
};
