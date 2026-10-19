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
	IMAGES,
	PRIVACY_POLICY_VERSION,
} from "../constants/config";
import { THEMES } from "../constants/theme";
import { dict } from "../i18n/dict";
import type { AppProps } from "../types";

export default function Privacy({ lang, assetPrefix = "../" }: AppProps) {
	const t = dict[lang];
	const theme = THEMES["1707"];
	const [isScrolled, setIsScrolled] = useState(false);

	useEffect(() => {
		if (typeof window === "undefined") return;
		const handleScroll = () => setIsScrolled(window.scrollY > 50);
		window.addEventListener("scroll", handleScroll);
		return () => window.removeEventListener("scroll", handleScroll);
	}, []);

	const contactHref = getContactPagePath(lang, assetPrefix);

	return (
		<div className={theme.privacyPageWrapper}>
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
						src={`${assetPrefix}${IMAGES.mainEntrance}`}
						alt="OceanViewFlats Main Entrance"
						className="w-full h-full object-cover"
					/>
					<div className={theme.privacyHeroOverlay}></div>
				</div>
				<div className="relative z-10 max-w-4xl mx-auto mt-4">
					<span className={theme.privacyHeroBadge}>
						<ShieldCheck className="w-4 h-4" />
						<span>{t.privacyBadge}</span>
					</span>
					<h1 className="text-3xl md:text-5xl font-extrabold mb-4 text-white tracking-tight drop-shadow-md">
						{t.privacyTitle}
					</h1>
					<p className="text-base md:text-lg text-slate-200 font-light max-w-3xl mx-auto drop-shadow leading-relaxed">
						{t.privacySubtitle}
					</p>
					<div className={theme.privacyVersionPill}>
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
				<section id="data-controller" className={theme.privacySectionCard}>
					<div className="flex items-center space-x-3 mb-4">
						<div className={theme.privacyControllerIconBox}>
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
				<section id="legal-framework" className={theme.privacySectionCard}>
					<div className="flex items-center space-x-3 mb-4">
						<div className={theme.privacyLegalIconBox}>
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
				<section id="processing-purposes" className={theme.privacySectionCard}>
					<div className="flex items-center space-x-3 mb-4">
						<div className={theme.privacyPurposesIconBox}>
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
						<div className={theme.privacySubCard}>
							<h3 className="font-bold text-slate-900 mb-2">
								{t.privacyPurpose1Title}
							</h3>
							<p className="text-sm text-slate-600 leading-relaxed">
								{t.privacyPurpose1Desc}
							</p>
						</div>

						<div className={theme.privacySubCard}>
							<h3 className="font-bold text-slate-900 mb-2">
								{t.privacyPurpose2Title}
							</h3>
							<p className="text-sm text-slate-600 leading-relaxed">
								{t.privacyPurpose2Desc}
							</p>
						</div>

						<div className={theme.privacySubCard}>
							<h3 className="font-bold text-slate-900 mb-2">
								{t.privacyPurpose3Title}
							</h3>
							<p className="text-sm text-slate-600 leading-relaxed">
								{t.privacyPurpose3Desc}
							</p>
						</div>

						<div className={theme.privacySubCard}>
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
				<section id="minors-protection" className={theme.privacySectionCard}>
					<div className="flex items-center space-x-3 mb-4">
						<div className={theme.privacyMinorsIconBox}>
							<Users className="w-6 h-6" />
						</div>
						<h2 className="text-xl md:text-2xl font-extrabold text-slate-900">
							{t.privacyMinorsTitle}
						</h2>
					</div>
					<div className="space-y-4 text-sm md:text-base text-slate-600 leading-relaxed">
						<p>{t.privacyMinorsDesc1}</p>
						<p className={theme.privacyMinorsCallout}>{t.privacyMinorsDesc2}</p>
					</div>
				</section>

				{/* 5. Third-Party Transmissions */}
				<section
					id="third-party-transmissions"
					className={theme.privacySectionCard}
				>
					<div className="flex items-center space-x-3 mb-4">
						<div className={theme.privacyTransmissionsIconBox}>
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
						<div className={theme.privacySubCard}>
							<h3 className="font-bold text-slate-900 mb-1">
								{t.privacyTransMercadoPagoTitle}
							</h3>
							<p className="text-sm text-slate-600 leading-relaxed">
								{t.privacyTransMercadoPagoDesc}
							</p>
						</div>

						<div className={theme.privacySubCard}>
							<h3 className="font-bold text-slate-900 mb-1">
								{t.privacyTransCondoTitle}
							</h3>
							<p className="text-sm text-slate-600 leading-relaxed">
								{t.privacyTransCondoDesc}
							</p>
						</div>

						<div className={theme.privacySubCard}>
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
				<section id="arco-rights" className={theme.privacySectionCard}>
					<div className="flex items-center space-x-3 mb-4">
						<div className={theme.privacyArcoIconBox}>
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
						<div className={theme.privacyConsultasCard}>
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

						<div className={theme.privacyReclamosCard}>
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

					<div className={theme.privacyChannelBanner}>
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
						<a href={contactHref} className={theme.privacyChannelCtaBtn}>
							{t.navContact}
						</a>
					</div>
				</section>

				{/* 7. Cryptographic Security & 90-Day Retention */}
				<section
					id="security-and-retention"
					className={theme.privacySectionCard}
				>
					<div className="flex items-center space-x-3 mb-4">
						<div className={theme.privacySecurityIconBox}>
							<Lock className="w-6 h-6" />
						</div>
						<h2 className="text-xl md:text-2xl font-extrabold text-slate-900">
							{t.privacySecurityTitle}
						</h2>
					</div>
					<div className="space-y-3 text-sm md:text-base text-slate-600 leading-relaxed">
						<p>{t.privacySecurityDesc1}</p>
						<p>{t.privacySecurityDesc2}</p>
						<p className={theme.privacyRetentionCallout}>
							{t.privacyRetentionDesc}
						</p>
					</div>
				</section>
			</main>

			<Footer lang={lang} assetPrefix={assetPrefix} />
		</div>
	);
}
