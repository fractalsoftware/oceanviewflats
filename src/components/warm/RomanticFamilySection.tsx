import { Heart, Users } from "lucide-react";
import React from "react";
import { IMAGE_DIMENSIONS, IMAGES } from "../../constants/config";
import { dict } from "../../i18n/dict";
import type { Lang } from "../../types";

export const RomanticFamilySection = ({
	lang,
	assetPrefix = "./",
}: {
	lang: Lang;
	assetPrefix?: string;
}) => {
	const t = dict[lang];

	return (
		<section className="bg-stone-900 text-white py-24 px-6 relative overflow-hidden">
			{/* Warm abstract background blobs */}
			<div className="absolute top-0 right-0 -mr-20 -mt-20 w-96 h-96 bg-rose-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20"></div>
			<div className="absolute bottom-0 left-0 -ml-20 -mb-20 w-96 h-96 bg-amber-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20"></div>

			<div className="max-w-6xl mx-auto grid md:grid-cols-2 gap-16 items-center relative z-10">
				<div>
					<h2 className="text-3xl md:text-4xl font-bold mb-6 text-stone-50">
						{t.romanticTitle}
					</h2>
					<p className="text-stone-300 text-lg mb-10 leading-relaxed">
						{t.romanticDesc}
					</p>
					<ul className="space-y-6">
						<li className="flex items-start space-x-4">
							<div className="bg-amber-500/20 p-3 rounded-xl">
								<Users className="w-6 h-6 text-amber-400" />
							</div>
							<div>
								<h3 className="font-bold text-lg text-stone-50">
									{t.amFamily}
								</h3>
								<p className="text-stone-400 text-sm">{t.amFamilyDesc}</p>
							</div>
						</li>
						<li className="flex items-start space-x-4">
							<div className="bg-rose-500/20 p-3 rounded-xl">
								<Heart className="w-6 h-6 text-rose-400" />
							</div>
							<div>
								<h3 className="font-bold text-lg text-stone-50">
									{t.amRomantic}
								</h3>
								<p className="text-stone-400 text-sm">{t.amRomanticDesc}</p>
							</div>
						</li>
					</ul>
				</div>
				<div className="relative">
					<img
						src={`${assetPrefix}${IMAGES["1606"].romantic}`}
						alt={t.romanticImgAlt}
						width={IMAGE_DIMENSIONS["1606"].romantic.width}
						height={IMAGE_DIMENSIONS["1606"].romantic.height}
						loading="lazy"
						decoding="async"
						className="rounded-2xl shadow-2xl object-cover h-[400px] w-full border border-stone-700"
					/>
					<div className="absolute -bottom-6 -left-6 bg-white text-stone-800 p-4 rounded-2xl shadow-2xl flex items-center space-x-4 border border-stone-100">
						<div className="bg-rose-100 p-3 rounded-full">
							<Heart className="w-6 h-6 text-rose-600" />
						</div>
						<div>
							<p className="text-sm font-extrabold">{t.romanticBadgeTitle}</p>
							<p className="text-xs text-stone-500 font-medium">
								{t.romanticBadgeSub}
							</p>
						</div>
					</div>
				</div>
			</div>
		</section>
	);
};
