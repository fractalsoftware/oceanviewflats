import {
	Heart,
	MessageCircle,
	MessageSquare,
	Percent,
	ShieldCheck,
	Sparkles,
	Users,
	Wifi,
} from "lucide-react";
import React, { useEffect, useState } from "react";
import { Footer } from "../components/Footer";
import { Navigation } from "../components/Navigation";
import { IMAGES } from "../constants/config";
import { dict } from "../i18n/dict";
import type { AppProps } from "../types";

export default function Home({ lang, assetPrefix = "./" }: AppProps) {
	const t = dict[lang];
	const [isScrolled, setIsScrolled] = useState(false);

	useEffect(() => {
		if (typeof window === "undefined") return;
		const handleScroll = () => setIsScrolled(window.scrollY > 50);
		window.addEventListener("scroll", handleScroll);
		return () => window.removeEventListener("scroll", handleScroll);
	}, []);

	return (
		<div className="min-h-screen flex flex-col bg-slate-50 font-sans text-slate-800 scroll-smooth">
			<Navigation
				isScrolled={isScrolled}
				lang={lang}
				showBookButton={false}
				assetPrefix={assetPrefix}
			/>

			{/* Hero Section */}
			<header className="relative pt-28 pb-12 px-6 text-center overflow-hidden">
				<div className="absolute inset-0 w-full h-full">
					<img
						src={`${assetPrefix}${IMAGES.home.hero}`}
						alt="OceanViewFlats"
						className="w-full h-full object-cover"
						fetchPriority="high"
						loading="eager"
					/>
					<div className="absolute inset-0 bg-gradient-to-b from-slate-900/80 to-slate-900/40"></div>
				</div>
				<div className="relative z-10 max-w-3xl mx-auto mt-4">
					<h1 className="text-3xl md:text-5xl font-extrabold mb-4 text-white tracking-tight drop-shadow-md">
						{t.homeWelcome}
					</h1>
					<p className="text-lg md:text-xl text-slate-200 font-light max-w-2xl mx-auto drop-shadow">
						{t.homeSubtitle}
					</p>
				</div>
			</header>

			<main className="flex-grow w-full">
				{/* Properties Section */}
				<section className="py-20 px-6 max-w-6xl mx-auto">
					<h2 className="sr-only">Our Featured Properties</h2>
					<div className="grid grid-cols-1 md:grid-cols-2 gap-10">
						<a
							href={
								lang === "en" ? "/Oceanview1707" : `/Oceanview1707/${lang}.html`
							}
							className="group block bg-white rounded-3xl shadow-md border border-slate-100 overflow-hidden hover:shadow-2xl hover:-translate-y-1 transition-all duration-300"
						>
							<div className="h-80 relative overflow-hidden">
								<img
									src={`${assetPrefix}${IMAGES["1707"].hero}`}
									className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700"
									alt={t.heroTitle}
									loading="lazy"
								/>
								<div className="absolute inset-0 bg-gradient-to-t from-slate-900/60 to-transparent"></div>
								<div className="absolute bottom-6 left-6 text-white">
									<p className="text-sm font-semibold tracking-wider uppercase mb-1 opacity-90">
										{t.heroLocation}
									</p>
									<h3 className="text-3xl font-bold">{t.heroTitle}</h3>
								</div>
							</div>
							<div className="p-6 bg-slate-50 border-t border-slate-100 group-hover:bg-white transition-colors">
								<p className="text-slate-600 line-clamp-2">{t.nomadDesc}</p>
								<div className="mt-4 flex items-center space-x-4 text-sm font-medium text-[#FF5A5F]">
									<span>{t.statsBeds}</span>
									<span>•</span>
									<span>{t.statsGuests}</span>
								</div>
							</div>
						</a>

						<a
							href={
								lang === "en" ? "/Oceanview1606" : `/Oceanview1606/${lang}.html`
							}
							className="group block bg-white rounded-3xl shadow-md border border-stone-100 overflow-hidden hover:shadow-2xl hover:-translate-y-1 transition-all duration-300"
						>
							<div className="h-80 relative overflow-hidden">
								<img
									src={`${assetPrefix}${IMAGES["1606"].hero}`}
									className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700"
									alt={t.hero1606Title}
									loading="lazy"
								/>
								<div className="absolute inset-0 bg-gradient-to-t from-stone-900/60 to-transparent"></div>
								<div className="absolute bottom-6 left-6 text-white">
									<p className="text-sm font-semibold tracking-wider uppercase mb-1 opacity-90">
										{t.heroLocation}
									</p>
									<h3 className="text-3xl font-bold">{t.hero1606Title}</h3>
								</div>
							</div>
							<div className="p-6 bg-stone-50 border-t border-stone-100 group-hover:bg-white transition-colors">
								<p className="text-stone-600 line-clamp-2">{t.romanticDesc}</p>
								<div className="mt-4 flex items-center space-x-4 text-sm font-medium text-rose-600">
									<span>{t.stats1606Beds}</span>
									<span>•</span>
									<span>{t.statsGuests}</span>
								</div>
							</div>
						</a>
					</div>
				</section>

				{/* Why Book Direct Section */}
				<section className="py-20 bg-gradient-to-b from-white to-slate-50 border-t border-b border-slate-100 px-6">
					<div className="max-w-6xl mx-auto">
						<div className="text-center max-w-3xl mx-auto mb-16">
							<span className="text-[#FF5A5F] text-sm font-extrabold uppercase tracking-widest bg-rose-50 px-4 py-1.5 rounded-full">
								{t.directTitle}
							</span>
							<h2 className="text-3xl md:text-4xl font-extrabold tracking-tight text-slate-900 mt-4 mb-4">
								{t.directTitle}
							</h2>
							<p className="text-lg text-slate-600 font-light">
								{t.directSubtitle}
							</p>
						</div>

						<div className="grid grid-cols-1 md:grid-cols-4 gap-8">
							<div className="bg-white p-8 rounded-3xl border border-slate-100 shadow-sm hover:shadow-xl transition-all duration-300">
								<div className="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center mb-6">
									<Percent className="w-6 h-6" aria-hidden="true" />
								</div>
								<h3 className="text-xl font-bold mb-3 text-slate-900">
									{t.directReason1Title}
								</h3>
								<p className="text-sm text-slate-600 leading-relaxed">
									{t.directReason1Desc}
								</p>
							</div>

							<div className="bg-white p-8 rounded-3xl border border-slate-100 shadow-sm hover:shadow-xl transition-all duration-300">
								<div className="w-12 h-12 bg-amber-50 text-amber-600 rounded-2xl flex items-center justify-center mb-6">
									<Sparkles className="w-6 h-6" aria-hidden="true" />
								</div>
								<h3 className="text-xl font-bold mb-3 text-slate-900">
									{t.directReason2Title}
								</h3>
								<p className="text-sm text-slate-600 leading-relaxed">
									{t.directReason2Desc}
								</p>
							</div>

							<div className="bg-white p-8 rounded-3xl border border-slate-100 shadow-sm hover:shadow-xl transition-all duration-300">
								<div className="w-12 h-12 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center mb-6">
									<MessageCircle className="w-6 h-6" aria-hidden="true" />
								</div>
								<h3 className="text-xl font-bold mb-3 text-slate-900">
									{t.directReason3Title}
								</h3>
								<p className="text-sm text-slate-600 leading-relaxed">
									{t.directReason3Desc}
								</p>
							</div>

							<div className="bg-white p-8 rounded-3xl border border-slate-100 shadow-sm hover:shadow-xl transition-all duration-300">
								<div className="w-12 h-12 bg-purple-50 text-purple-600 rounded-2xl flex items-center justify-center mb-6">
									<ShieldCheck className="w-6 h-6" aria-hidden="true" />
								</div>
								<h3 className="text-xl font-bold mb-3 text-slate-900">
									{t.directReason4Title}
								</h3>
								<p className="text-sm text-slate-600 leading-relaxed">
									{t.directReason4Desc}
								</p>
							</div>
						</div>
					</div>
				</section>

				{/* Experiences Section */}
				<section className="bg-slate-900 text-white py-24 px-6 mt-10">
					<div className="max-w-6xl mx-auto">
						<h2 className="text-3xl md:text-4xl font-bold mb-16 text-center">
							{t.homeExpTitle}
						</h2>

						<div className="grid grid-cols-1 md:grid-cols-3 gap-12">
							<div className="text-center">
								<div className="w-16 h-16 mx-auto bg-blue-500/20 text-blue-400 rounded-2xl flex items-center justify-center mb-6">
									<Wifi className="w-8 h-8" aria-hidden="true" />
								</div>
								<h3 className="text-xl font-bold mb-4">{t.homeExpNomad}</h3>
								<p className="text-slate-400 leading-relaxed">
									{t.homeExpNomadDesc}
								</p>
							</div>

							<div className="text-center">
								<div className="w-16 h-16 mx-auto bg-amber-500/20 text-amber-400 rounded-2xl flex items-center justify-center mb-6">
									<Users className="w-8 h-8" aria-hidden="true" />
								</div>
								<h3 className="text-xl font-bold mb-4">{t.homeExpFamily}</h3>
								<p className="text-slate-400 leading-relaxed">
									{t.homeExpFamilyDesc}
								</p>
							</div>

							<div className="text-center">
								<div className="w-16 h-16 mx-auto bg-rose-500/20 text-rose-400 rounded-2xl flex items-center justify-center mb-6">
									<Heart className="w-8 h-8" aria-hidden="true" />
								</div>
								<h3 className="text-xl font-bold mb-4">{t.homeExpRomantic}</h3>
								<p className="text-slate-400 leading-relaxed">
									{t.homeExpRomanticDesc}
								</p>
							</div>
						</div>
					</div>
				</section>

				{/* FAQ Section */}
				<section className="py-24 px-6 max-w-4xl mx-auto border-t border-slate-100">
					<h2 className="text-3xl font-bold mb-12 text-center text-slate-900">
						{t.faqTitle}
					</h2>
					<div className="space-y-8">
						<div className="bg-white p-8 rounded-2xl border border-slate-100 shadow-sm hover:shadow-md transition-shadow">
							<h3 className="text-xl font-bold mb-4 text-slate-900">
								{t.faqQ1}
							</h3>
							<p className="text-slate-600 leading-relaxed">{t.faqA1}</p>
						</div>
						<div className="bg-white p-8 rounded-2xl border border-slate-100 shadow-sm hover:shadow-md transition-shadow">
							<h3 className="text-xl font-bold mb-4 text-slate-900">
								{t.faqQ2}
							</h3>
							<p className="text-slate-600 leading-relaxed">{t.faqA2}</p>
						</div>
						<div className="bg-white p-8 rounded-2xl border border-slate-100 shadow-sm hover:shadow-md transition-shadow">
							<h3 className="text-xl font-bold mb-4 text-slate-900">
								{t.faqQ3}
							</h3>
							<p className="text-slate-600 leading-relaxed">{t.faqA3}</p>
						</div>
						<div className="bg-white p-8 rounded-2xl border border-slate-100 shadow-sm hover:shadow-md transition-shadow">
							<h3 className="text-xl font-bold mb-4 text-slate-900">
								{t.faqQ4}
							</h3>
							<p className="text-slate-600 leading-relaxed">{t.faqA4}</p>
						</div>
						<div className="bg-white p-8 rounded-2xl border border-slate-100 shadow-sm hover:shadow-md transition-shadow">
							<h3 className="text-xl font-bold mb-4 text-slate-900">
								{t.faqQ5}
							</h3>
							<p className="text-slate-600 leading-relaxed">{t.faqA5}</p>
						</div>
					</div>
				</section>

				{/* Contact CTA Section */}
				<section className="py-20 px-6 max-w-6xl mx-auto mb-12">
					<div className="bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 text-white text-center relative overflow-hidden rounded-3xl py-16 px-8 md:px-16 shadow-2xl border border-slate-800/50">
						{/* Decorative background glow blobs for a premium look */}
						<div className="absolute -top-24 -left-24 w-72 h-72 bg-[#FF5A5F]/10 rounded-full blur-3xl pointer-events-none"></div>
						<div className="absolute -bottom-24 -right-24 w-72 h-72 bg-blue-500/10 rounded-full blur-3xl pointer-events-none"></div>

						<div className="relative z-10 max-w-2xl mx-auto flex flex-col items-center">
							<div className="w-14 h-14 bg-[#FF5A5F]/15 text-[#FF5A5F] rounded-2xl flex items-center justify-center mb-6 shadow-lg shadow-[#FF5A5F]/10">
								<MessageSquare className="w-7 h-7" aria-hidden="true" />
							</div>
							<h2 className="text-3xl md:text-4xl font-extrabold tracking-tight mb-4 text-white">
								{t.contactTitle}
							</h2>
							<p className="text-lg text-slate-300 font-light mb-8 leading-relaxed">
								{t.contactSub}
							</p>
							<a
								href={
									lang === "en"
										? `${assetPrefix}contact/index.html`
										: `${assetPrefix}contact/${lang}.html`
								}
								className="inline-flex items-center space-x-2.5 bg-[#FF5A5F] hover:bg-[#FF424D] text-white px-8 py-4 rounded-full font-bold text-lg shadow-xl shadow-[#FF5A5F]/20 transition-all duration-300 hover:scale-105 hover:shadow-2xl hover:shadow-[#FF5A5F]/35 active:scale-[0.98]"
							>
								<span>{t.navContact}</span>
							</a>
						</div>
					</div>
				</section>
			</main>

			<Footer lang={lang} assetPrefix={assetPrefix} />
		</div>
	);
}
