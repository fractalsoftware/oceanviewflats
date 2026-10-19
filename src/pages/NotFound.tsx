import { ArrowLeft, Compass, Home, Mail, MapPin } from "lucide-react";
import React, { useEffect, useState } from "react";
import { Footer } from "../components/Footer";
import { Navigation } from "../components/Navigation";
import { IMAGES } from "../constants/config";
import { dict } from "../i18n/dict";
import type { AppProps } from "../types";

export default function NotFound({ lang, assetPrefix = "/" }: AppProps) {
	const t = dict[lang];
	const [isScrolled, setIsScrolled] = useState(false);

	useEffect(() => {
		if (typeof window === "undefined") return;
		const handleScroll = () => setIsScrolled(window.scrollY > 50);
		window.addEventListener("scroll", handleScroll);
		return () => window.removeEventListener("scroll", handleScroll);
	}, []);

	// Compute navigation hrefs
	const homeUrl =
		lang === "en" ? `${assetPrefix}` : `${assetPrefix}${lang}.html`;
	const flat1707Url =
		lang === "en"
			? `${assetPrefix}Oceanview1707/`
			: `${assetPrefix}Oceanview1707/${lang}.html`;
	const flat1606Url =
		lang === "en"
			? `${assetPrefix}Oceanview1606/`
			: `${assetPrefix}Oceanview1606/${lang}.html`;
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

			{/* Main 404 Section */}
			<main className="flex-grow relative flex items-center justify-center pt-28 pb-16 px-6 overflow-hidden">
				{/* Full-bleed Beach Background with blur & overlay */}
				<div className="absolute inset-0 w-full h-full">
					<img
						src={`${assetPrefix}${IMAGES.home.hero}`}
						alt="OceanViewFlats Ocean Backdrop"
						className="w-full h-full object-cover scale-105 motion-safe:animate-[pulse_10s_infinite]"
					/>
					<div className="absolute inset-0 bg-gradient-to-b from-slate-950/80 via-slate-900/60 to-slate-950/90 backdrop-blur-[2px]"></div>
				</div>

				{/* Foreground Content Card */}
				<div className="relative z-10 max-w-2xl w-full text-center">
					{/* Animated Compass / Decorative Icon */}
					<div className="inline-flex p-4 bg-white/10 backdrop-blur-md rounded-3xl border border-white/20 shadow-2xl mb-8 animate-[spin_20s_linear_infinite]">
						<Compass className="w-12 h-12 text-[#FF5A5F]" />
					</div>

					{/* Large Error Indicator */}
					<div className="mb-4">
						<span className="text-8xl md:text-9xl font-extrabold tracking-widest text-transparent bg-clip-text bg-gradient-to-r from-[#FF5A5F] via-rose-400 to-amber-300 drop-shadow-lg">
							404
						</span>
					</div>

					{/* Message Area */}
					<h1 className="text-3xl md:text-4xl font-extrabold mb-4 tracking-tight drop-shadow-md">
						{t.notFoundHeader}
					</h1>
					<p className="text-base md:text-lg text-slate-300 font-light mb-12 max-w-xl mx-auto leading-relaxed drop-shadow">
						{t.notFoundSubtitle}
					</p>

					{/* Navigation Action Links */}
					<div className="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-lg mx-auto">
						{/* Safe Harbor (Home) Link */}
						<a
							href={homeUrl}
							className="flex items-center justify-center space-x-3 bg-[#FF5A5F] hover:bg-[#FF424D] text-white px-6 py-4 rounded-2xl font-bold shadow-lg shadow-rose-950/30 transition-all duration-300 hover:scale-[1.02] hover:shadow-xl hover:shadow-rose-950/40 border border-rose-500/20"
						>
							<Home className="w-5 h-5" />
							<span>{t.notFoundBtnHome}</span>
						</a>

						{/* Contact Host Link */}
						<a
							href={contactUrl}
							className="flex items-center justify-center space-x-3 bg-white/10 hover:bg-white/20 backdrop-blur-md text-white px-6 py-4 rounded-2xl font-bold shadow-lg border border-white/10 transition-all duration-300 hover:scale-[1.02]"
						>
							<Mail className="w-5 h-5 text-rose-300" />
							<span>{t.notFoundBtnContact}</span>
						</a>

						{/* Flat 1707 Link */}
						<a
							href={flat1707Url}
							className="group flex items-center justify-between bg-slate-950/40 hover:bg-slate-900/60 backdrop-blur-md text-slate-200 px-6 py-4 rounded-2xl font-semibold shadow border border-slate-800/80 transition-all duration-300 hover:scale-[1.02]"
						>
							<div className="flex items-center space-x-3">
								<MapPin className="w-5 h-5 text-amber-400 group-hover:text-amber-300 transition-colors" />
								<span className="text-sm">{t.notFoundBtn1707}</span>
							</div>
							<ArrowLeft className="w-4 h-4 rotate-180 text-slate-500 group-hover:text-slate-300 transition-colors" />
						</a>

						{/* Flat 1606 Link */}
						<a
							href={flat1606Url}
							className="group flex items-center justify-between bg-slate-950/40 hover:bg-slate-900/60 backdrop-blur-md text-slate-200 px-6 py-4 rounded-2xl font-semibold shadow border border-slate-800/80 transition-all duration-300 hover:scale-[1.02]"
						>
							<div className="flex items-center space-x-3">
								<MapPin className="w-5 h-5 text-teal-400 group-hover:text-teal-300 transition-colors" />
								<span className="text-sm">{t.notFoundBtn1606}</span>
							</div>
							<ArrowLeft className="w-4 h-4 rotate-180 text-slate-500 group-hover:text-slate-300 transition-colors" />
						</a>
					</div>
				</div>
			</main>

			<Footer lang={lang} assetPrefix={assetPrefix} />
		</div>
	);
}
