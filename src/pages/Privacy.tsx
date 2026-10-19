import {
	Building,
	CheckCircle2,
	Clock,
	FileText,
	Lock,
	Mail,
	MapPin,
	Phone,
	Scale,
	ShieldCheck,
	Users,
} from "lucide-react";
import React, { useEffect, useState } from "react";
import { Footer } from "../components/Footer";
import { Navigation } from "../components/Navigation";
import {
	DATA_CONTROLLER,
	getContactPagePath,
	PRIVACY_POLICY_VERSION,
} from "../constants/config";
import { dict } from "../i18n/dict";
import type { AppProps } from "../types";

export default function Privacy({ lang, assetPrefix = "../" }: AppProps) {
	const t = dict[lang];
	const [isScrolled, setIsScrolled] = useState(false);

	useEffect(() => {
		if (typeof window === "undefined") return;
		const handleScroll = () => setIsScrolled(window.scrollY > 50);
		window.addEventListener("scroll", handleScroll);
		return () => window.removeEventListener("scroll", handleScroll);
	}, []);

	const contactHref = getContactPagePath(lang, assetPrefix);

	return (
		<div className="min-h-screen flex flex-col bg-slate-50 font-sans text-slate-800 scroll-smooth">
			<Navigation
				isScrolled={isScrolled}
				lang={lang}
				showBookButton={false}
				assetPrefix={assetPrefix}
			/>

			{/* Hero Header */}
			<header className="relative pt-32 pb-16 px-6 text-center overflow-hidden">
				<div className="absolute inset-0 w-full h-full">
					<img
						src={`${assetPrefix}images/main-entrance.webp`}
						alt="OceanViewFlats Main Entrance"
						className="w-full h-full object-cover"
					/>
					<div className="absolute inset-0 bg-slate-900/75 backdrop-blur-[2px]"></div>
				</div>
				<div className="relative z-10 max-w-4xl mx-auto mt-4">
					<span className="inline-flex items-center space-x-2 px-4 py-1.5 rounded-full text-xs font-bold bg-emerald-500/15 text-emerald-300 border border-emerald-500/30 mb-4 tracking-wider uppercase backdrop-blur-md">
						<ShieldCheck className="w-4 h-4" />
						<span>{t.privacyBadge}</span>
					</span>
					<h1 className="text-3xl md:text-5xl font-extrabold mb-4 text-white tracking-tight drop-shadow-md">
						{t.privacyTitle}
					</h1>
					<p className="text-base md:text-lg text-slate-200 font-light max-w-3xl mx-auto drop-shadow leading-relaxed">
						{t.privacySubtitle}
					</p>
					<div className="mt-6 inline-flex items-center space-x-3 bg-white/10 border border-white/15 rounded-full px-5 py-2 text-xs text-slate-200 font-mono">
						<span>
							{t.privacyVersionLabel}: <strong>{PRIVACY_POLICY_VERSION}</strong>
						</span>
						<span>•</span>
						<span>{t.privacyEffectiveDate}</span>
					</div>
				</div>
			</header>

			<main className="flex-grow w-full py-12 px-6 max-w-5xl mx-auto space-y-10">
				{/* 1. Data Controller Identification */}
				<section
					id="data-controller"
					className="bg-white rounded-3xl p-8 md:p-10 shadow-md border border-slate-100"
				>
					<div className="flex items-center space-x-3 mb-4">
						<div className="p-3 bg-[#FF5A5F]/10 text-[#FF5A5F] rounded-2xl">
							<Building className="w-6 h-6" />
						</div>
						<h2 className="text-xl md:text-2xl font-extrabold text-slate-900">
							{t.privacyControllerTitle}
						</h2>
					</div>
					<p className="text-slate-600 text-sm md:text-base leading-relaxed mb-6">
						{t.privacyControllerIntro}
					</p>

					<div className="grid grid-cols-1 md:grid-cols-2 gap-4 bg-slate-50 p-6 rounded-2xl border border-slate-100 text-sm">
						<div className="space-y-1">
							<span className="text-xs font-bold uppercase tracking-wider text-slate-400 block">
								{t.privacyControllerNameLabel}
							</span>
							<p className="font-bold text-slate-900 text-base">
								{DATA_CONTROLLER.name}
							</p>
						</div>

						<div className="space-y-1">
							<span className="text-xs font-bold uppercase tracking-wider text-slate-400 block">
								{t.privacyControllerRntLabel}
							</span>
							<p className="font-bold text-slate-900 text-base">
								{DATA_CONTROLLER.rntCombined} (1707 & 1606)
							</p>
						</div>

						<div className="space-y-1 md:col-span-2">
							<span className="text-xs font-bold uppercase tracking-wider text-slate-400 block">
								{t.privacyControllerAddressLabel}
							</span>
							<p className="font-semibold text-slate-700 flex items-center space-x-2">
								<MapPin className="w-4 h-4 text-[#FF5A5F] shrink-0" />
								<span>{DATA_CONTROLLER.address}</span>
							</p>
						</div>

						<div className="space-y-1">
							<span className="text-xs font-bold uppercase tracking-wider text-slate-400 block">
								{t.privacyControllerEmailLabel}
							</span>
							<p className="font-semibold text-slate-700 flex items-center space-x-2">
								<Mail className="w-4 h-4 text-[#FF5A5F] shrink-0" />
								<a
									href={`mailto:${DATA_CONTROLLER.email}`}
									className="text-[#FF5A5F] hover:underline"
								>
									{DATA_CONTROLLER.email}
								</a>
							</p>
						</div>

						<div className="space-y-1">
							<span className="text-xs font-bold uppercase tracking-wider text-slate-400 block">
								{t.privacyControllerPhoneLabel}
							</span>
							<p className="font-semibold text-slate-700 flex items-center space-x-2">
								<Phone className="w-4 h-4 text-[#FF5A5F] shrink-0" />
								<a
									href={DATA_CONTROLLER.phoneHref}
									className="hover:text-[#FF5A5F] transition-colors"
								>
									{DATA_CONTROLLER.phone}
								</a>
							</p>
						</div>
					</div>
				</section>

				{/* 2. Applicable Legal Framework */}
				<section
					id="legal-framework"
					className="bg-white rounded-3xl p-8 md:p-10 shadow-md border border-slate-100"
				>
					<div className="flex items-center space-x-3 mb-4">
						<div className="p-3 bg-indigo-50 text-indigo-600 rounded-2xl">
							<Scale className="w-6 h-6" />
						</div>
						<h2 className="text-xl md:text-2xl font-extrabold text-slate-900">
							{t.privacyLegalTitle}
						</h2>
					</div>
					<p className="text-slate-600 text-sm md:text-base leading-relaxed mb-6">
						{t.privacyLegalIntro}
					</p>
					<ul className="space-y-4 text-sm text-slate-700 leading-relaxed">
						<li className="flex items-start space-x-3 bg-slate-50 p-4 rounded-2xl border border-slate-100">
							<CheckCircle2 className="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" />
							<span>{t.privacyLegalLaw1581}</span>
						</li>
						<li className="flex items-start space-x-3 bg-slate-50 p-4 rounded-2xl border border-slate-100">
							<CheckCircle2 className="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" />
							<span>{t.privacyLegalLaw2068}</span>
						</li>
						<li className="flex items-start space-x-3 bg-slate-50 p-4 rounded-2xl border border-slate-100">
							<CheckCircle2 className="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" />
							<span>{t.privacyLegalLaw679}</span>
						</li>
					</ul>
				</section>

				{/* 3. Purposes of Processing */}
				<section
					id="processing-purposes"
					className="bg-white rounded-3xl p-8 md:p-10 shadow-md border border-slate-100"
				>
					<div className="flex items-center space-x-3 mb-4">
						<div className="p-3 bg-amber-50 text-amber-600 rounded-2xl">
							<FileText className="w-6 h-6" />
						</div>
						<h2 className="text-xl md:text-2xl font-extrabold text-slate-900">
							{t.privacyPurposesTitle}
						</h2>
					</div>
					<p className="text-slate-600 text-sm md:text-base leading-relaxed mb-6">
						{t.privacyPurposesIntro}
					</p>

					<div className="grid grid-cols-1 md:grid-cols-2 gap-6">
						<div className="p-5 rounded-2xl bg-slate-50 border border-slate-100">
							<h3 className="font-bold text-slate-900 mb-2">
								{t.privacyPurpose1Title}
							</h3>
							<p className="text-sm text-slate-600 leading-relaxed">
								{t.privacyPurpose1Desc}
							</p>
						</div>

						<div className="p-5 rounded-2xl bg-slate-50 border border-slate-100">
							<h3 className="font-bold text-slate-900 mb-2">
								{t.privacyPurpose2Title}
							</h3>
							<p className="text-sm text-slate-600 leading-relaxed">
								{t.privacyPurpose2Desc}
							</p>
						</div>

						<div className="p-5 rounded-2xl bg-slate-50 border border-slate-100">
							<h3 className="font-bold text-slate-900 mb-2">
								{t.privacyPurpose3Title}
							</h3>
							<p className="text-sm text-slate-600 leading-relaxed">
								{t.privacyPurpose3Desc}
							</p>
						</div>

						<div className="p-5 rounded-2xl bg-slate-50 border border-slate-100">
							<h3 className="font-bold text-slate-900 mb-2">
								{t.privacyPurpose4Title}
							</h3>
							<p className="text-sm text-slate-600 leading-relaxed">
								{t.privacyPurpose4Desc}
							</p>
						</div>
					</div>
				</section>

				{/* 4. Protection of Minors */}
				<section
					id="minors-protection"
					className="bg-white rounded-3xl p-8 md:p-10 shadow-md border border-slate-100"
				>
					<div className="flex items-center space-x-3 mb-4">
						<div className="p-3 bg-rose-50 text-rose-600 rounded-2xl">
							<Users className="w-6 h-6" />
						</div>
						<h2 className="text-xl md:text-2xl font-extrabold text-slate-900">
							{t.privacyMinorsTitle}
						</h2>
					</div>
					<div className="space-y-4 text-sm md:text-base text-slate-600 leading-relaxed">
						<p>{t.privacyMinorsDesc1}</p>
						<p className="p-4 rounded-2xl bg-amber-50 border border-amber-200 text-amber-900 text-sm font-medium">
							{t.privacyMinorsDesc2}
						</p>
					</div>
				</section>

				{/* 5. Third-Party Transmissions */}
				<section
					id="third-party-transmissions"
					className="bg-white rounded-3xl p-8 md:p-10 shadow-md border border-slate-100"
				>
					<div className="flex items-center space-x-3 mb-4">
						<div className="p-3 bg-sky-50 text-sky-600 rounded-2xl">
							<ShieldCheck className="w-6 h-6" />
						</div>
						<h2 className="text-xl md:text-2xl font-extrabold text-slate-900">
							{t.privacyTransmissionsTitle}
						</h2>
					</div>
					<p className="text-slate-600 text-sm md:text-base leading-relaxed mb-6">
						{t.privacyTransmissionsIntro}
					</p>

					<div className="space-y-4">
						<div className="p-5 rounded-2xl bg-slate-50 border border-slate-100">
							<h3 className="font-bold text-slate-900 mb-1">
								{t.privacyTransMercadoPagoTitle}
							</h3>
							<p className="text-sm text-slate-600 leading-relaxed">
								{t.privacyTransMercadoPagoDesc}
							</p>
						</div>

						<div className="p-5 rounded-2xl bg-slate-50 border border-slate-100">
							<h3 className="font-bold text-slate-900 mb-1">
								{t.privacyTransCondoTitle}
							</h3>
							<p className="text-sm text-slate-600 leading-relaxed">
								{t.privacyTransCondoDesc}
							</p>
						</div>

						<div className="p-5 rounded-2xl bg-slate-50 border border-slate-100">
							<h3 className="font-bold text-slate-900 mb-1">
								{t.privacyTransAuthoritiesTitle}
							</h3>
							<p className="text-sm text-slate-600 leading-relaxed">
								{t.privacyTransAuthoritiesDesc}
							</p>
						</div>
					</div>
				</section>

				{/* 6. Habeas Data (ARCO) Rights & SLAs */}
				<section
					id="arco-rights"
					className="bg-white rounded-3xl p-8 md:p-10 shadow-md border border-slate-100"
				>
					<div className="flex items-center space-x-3 mb-4">
						<div className="p-3 bg-emerald-50 text-emerald-600 rounded-2xl">
							<Clock className="w-6 h-6" />
						</div>
						<h2 className="text-xl md:text-2xl font-extrabold text-slate-900">
							{t.privacyArcoTitle}
						</h2>
					</div>
					<p className="text-slate-600 text-sm md:text-base leading-relaxed mb-3">
						{t.privacyArcoIntro}
					</p>
					<p className="text-slate-600 text-sm md:text-base leading-relaxed mb-6">
						{t.privacyArcoRightsList}
					</p>

					<div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
						<div className="p-6 rounded-2xl bg-emerald-50/60 border border-emerald-200">
							<h3 className="font-extrabold text-slate-900 mb-1">
								{t.privacyArcoConsultasTitle}
							</h3>
							<p className="text-xs font-bold uppercase tracking-wider text-emerald-700 mb-3">
								{t.privacyArcoConsultasSla} (
								{DATA_CONTROLLER.consultaSlaBusinessDays})
							</p>
							<p className="text-sm text-slate-700 leading-relaxed">
								{t.privacyArcoConsultasDesc}
							</p>
						</div>

						<div className="p-6 rounded-2xl bg-indigo-50/60 border border-indigo-200">
							<h3 className="font-extrabold text-slate-900 mb-1">
								{t.privacyArcoReclamosTitle}
							</h3>
							<p className="text-xs font-bold uppercase tracking-wider text-indigo-700 mb-3">
								{t.privacyArcoReclamosSla} (
								{DATA_CONTROLLER.reclamoSlaBusinessDays})
							</p>
							<p className="text-sm text-slate-700 leading-relaxed">
								{t.privacyArcoReclamosDesc}
							</p>
						</div>
					</div>

					<div className="p-5 rounded-2xl bg-slate-900 text-white flex flex-col md:flex-row md:items-center md:justify-between gap-4 text-sm">
						<div>
							<span className="text-slate-400 block text-xs uppercase font-bold tracking-wider mb-1">
								{t.privacyArcoChannelDesc}
							</span>
							<a
								href={`mailto:${DATA_CONTROLLER.email}`}
								className="font-bold text-emerald-400 hover:underline mr-4"
							>
								{DATA_CONTROLLER.email}
							</a>
							<span className="text-slate-300">{DATA_CONTROLLER.phone}</span>
						</div>
						<a
							href={contactHref}
							className="inline-flex items-center justify-center bg-[#FF5A5F] hover:bg-[#FF424D] text-white px-5 py-2.5 rounded-xl font-bold text-xs transition-colors shrink-0"
						>
							{t.navContact}
						</a>
					</div>
				</section>

				{/* 7. Cryptographic Security & 90-Day Retention */}
				<section
					id="security-and-retention"
					className="bg-white rounded-3xl p-8 md:p-10 shadow-md border border-slate-100"
				>
					<div className="flex items-center space-x-3 mb-4">
						<div className="p-3 bg-purple-50 text-purple-600 rounded-2xl">
							<Lock className="w-6 h-6" />
						</div>
						<h2 className="text-xl md:text-2xl font-extrabold text-slate-900">
							{t.privacySecurityTitle}
						</h2>
					</div>
					<div className="space-y-3 text-sm md:text-base text-slate-600 leading-relaxed">
						<p>{t.privacySecurityDesc1}</p>
						<p>{t.privacySecurityDesc2}</p>
						<p className="p-4 rounded-2xl bg-slate-50 border border-slate-200 text-slate-700 text-sm">
							{t.privacyRetentionDesc}
						</p>
					</div>
				</section>
			</main>

			<Footer lang={lang} assetPrefix={assetPrefix} />
		</div>
	);
}
