import { Waves, Wifi } from "lucide-react";
import React from "react";
import { IMAGE_DIMENSIONS, IMAGES } from "../constants/config";
import { dict } from "../i18n/dict";
import type { Lang } from "../types";

export const NomadSection = ({
	lang,
	assetPrefix = "./",
}: {
	lang: Lang;
	assetPrefix?: string;
}) => {
	const t = dict[lang];

	return (
		<section className="bg-slate-900 text-white py-24 px-6 relative overflow-hidden">
			{/* Abstract background blobs */}
			<div className="absolute top-0 right-0 -mr-20 -mt-20 w-96 h-96 bg-blue-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20"></div>
			<div className="absolute bottom-0 left-0 -ml-20 -mb-20 w-96 h-96 bg-teal-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20"></div>

			<div className="max-w-6xl mx-auto grid md:grid-cols-2 gap-16 items-center relative z-10">
				<div>
					<h2 className="text-3xl md:text-4xl font-bold mb-6">
						{t.nomadTitle}
					</h2>
					<p className="text-slate-300 text-lg mb-10 leading-relaxed">
						{t.nomadDesc}
					</p>
					<ul className="space-y-6">
						<li className="flex items-start space-x-4">
							<div className="bg-blue-500/20 p-3 rounded-xl">
								<Wifi className="w-6 h-6 text-blue-400" />
							</div>
							<div>
								<h3 className="font-bold text-lg">{t.amWifi}</h3>
								<p className="text-slate-400 text-sm">{t.nomadWifiDesc}</p>
							</div>
						</li>
						<li className="flex items-start space-x-4">
							<div className="bg-teal-500/20 p-3 rounded-xl">
								<Waves className="w-6 h-6 text-teal-400" />
							</div>
							<div>
								<h3 className="font-bold text-lg">{t.amOcean}</h3>
								<p className="text-slate-400 text-sm">{t.nomadOceanDesc}</p>
							</div>
						</li>
					</ul>
				</div>
				<div className="relative">
					<img
						src={`${assetPrefix}${IMAGES["1707"].nomad}`}
						alt={t.nomadImgAlt}
						width={IMAGE_DIMENSIONS["1707"].nomad.width}
						height={IMAGE_DIMENSIONS["1707"].nomad.height}
						loading="lazy"
						decoding="async"
						className="rounded-2xl shadow-2xl object-cover h-[400px] w-full border border-slate-700"
					/>
					<div className="absolute -bottom-6 -left-6 bg-white text-slate-800 p-4 rounded-2xl shadow-2xl flex items-center space-x-4 border border-slate-100">
						<div className="bg-green-100 p-3 rounded-full">
							<Wifi className="w-6 h-6 text-green-600" />
						</div>
						<div>
							<p className="text-sm font-extrabold">{t.nomadBadgeTitle}</p>
							<p className="text-xs text-slate-500 font-medium">
								{t.nomadBadgeSub}
							</p>
						</div>
					</div>
				</div>
			</div>
		</section>
	);
};
