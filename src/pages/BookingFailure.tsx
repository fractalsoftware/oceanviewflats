import { PhoneCall, RefreshCw, XCircle } from "lucide-react";
import React, { useEffect, useState } from "react";
import { Footer } from "../components/Footer";
import { Navigation } from "../components/Navigation";
import { IMAGES } from "../constants/config";
import { dict } from "../i18n/dict";
import type { AppProps } from "../types";

export default function BookingFailure({ lang, assetPrefix = "/" }: AppProps) {
	const t = dict[lang];
	const [isScrolled, setIsScrolled] = useState(false);

	useEffect(() => {
		if (typeof window === "undefined") return;
		const handleScroll = () => setIsScrolled(window.scrollY > 50);
		window.addEventListener("scroll", handleScroll);
		return () => window.removeEventListener("scroll", handleScroll);
	}, []);

	// Compute URLs
	const homeUrl =
		lang === "en" ? `${assetPrefix}` : `${assetPrefix}${lang}.html`;
	const contactUrl =
		lang === "en"
			? `${assetPrefix}contact/`
			: `${assetPrefix}contact/${lang}.html`;

	return (
		<div className="min-h-screen flex flex-col bg-slate-900 font-sans text-slate-100 scroll-smooth">
			<Navigation
				isScrolled={isScrolled}
				lang={lang}
				showBookButton={false}
				assetPrefix={assetPrefix}
			/>

			<main className="flex-grow relative flex items-center justify-center pt-28 pb-16 px-6 overflow-hidden">
				{/* Ambient Background */}
				<div className="absolute inset-0 w-full h-full">
					<img
						src={`${assetPrefix}${IMAGES.home.hero}`}
						alt="OceanViewFlats Ocean Backdrop"
						className="w-full h-full object-cover scale-105"
					/>
					<div className="absolute inset-0 bg-gradient-to-b from-slate-950/85 via-slate-900/70 to-slate-950/90 backdrop-blur-[3px]"></div>
				</div>

				{/* Card */}
				<div className="relative z-10 max-w-xl w-full text-center">
					<div className="bg-slate-950/70 backdrop-blur-xl border border-rose-500/20 rounded-3xl p-8 md:p-12 shadow-2xl shadow-slate-950/50">
						{/* Animated Alert Badge */}
						<div className="inline-flex p-4 bg-rose-500/10 rounded-full border border-rose-500/30 shadow-lg mb-6 animate-[bounce_3s_infinite]">
							<XCircle className="w-16 h-16 text-rose-400" />
						</div>

						<h1 className="text-3xl md:text-4xl font-extrabold mb-4 tracking-tight text-rose-100">
							{t.dbFailureHeader}
						</h1>

						<p className="text-slate-300 font-light mb-10 leading-relaxed">
							{t.dbFailureSubtitle}
						</p>

						{/* Actions */}
						<div className="flex flex-col sm:flex-row gap-4 justify-center">
							<a
								href={homeUrl}
								className="flex items-center justify-center space-x-2 bg-gradient-to-r from-[#FF5A5F] to-rose-600 hover:from-[#FF424D] hover:to-rose-700 text-white px-6 py-4 rounded-2xl font-bold transition-transform duration-300 hover:scale-[1.02] shadow-lg shadow-rose-950/20"
							>
								<RefreshCw className="w-5 h-5 mr-1" />
								<span>{t.dbFailureRetryBtn}</span>
							</a>

							<a
								href={contactUrl}
								className="flex items-center justify-center space-x-2 bg-white/10 hover:bg-white/20 border border-white/10 text-white px-6 py-4 rounded-2xl font-bold transition-transform duration-300 hover:scale-[1.02]"
							>
								<PhoneCall className="w-5 h-5 mr-1 text-rose-300" />
								<span>{t.dbFailureContactBtn}</span>
							</a>
						</div>
					</div>
				</div>
			</main>

			<Footer lang={lang} assetPrefix={assetPrefix} />
		</div>
	);
}
