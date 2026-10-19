import {
	ArrowRight,
	Building,
	Calendar,
	Car,
	Check,
	CheckCircle2,
	Compass,
	Copy,
	ExternalLink,
	FileText,
	Info,
	Key,
	Lock,
	MapPin,
	Phone,
	ShieldAlert,
	Wifi,
} from "lucide-react";
import React, { useEffect, useState } from "react";
import { Footer } from "../components/Footer";
import { Navigation } from "../components/Navigation";
import { dict } from "../i18n/dict";
import type { AppProps } from "../types";

export default function Guide({ lang, assetPrefix = "../" }: AppProps) {
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

			{/* Hero Header */}
			<header className="relative pt-32 pb-16 px-6 text-center overflow-hidden">
				<div className="absolute inset-0 w-full h-full">
					<img
						src={`${assetPrefix}images/main-entrance.webp`}
						alt="OceanViewFlats Beachfront Entrance"
						className="w-full h-full object-cover"
					/>
					<div className="absolute inset-0 bg-slate-900/65 backdrop-blur-[2px]"></div>
				</div>
				<div className="relative z-10 max-w-3xl mx-auto mt-4">
					<span className="inline-flex items-center px-4 py-1.5 rounded-full text-xs font-bold bg-amber-500/10 text-amber-300 border border-amber-500/20 mb-4 tracking-wider uppercase backdrop-blur-md">
						✨ {t.guideTitle}
					</span>
					<h1 className="text-4xl md:text-5xl font-extrabold mb-4 text-white tracking-tight drop-shadow-md">
						{t.guideHeader}
					</h1>
					<p
						id="guide-greeting-box"
						className="text-base md:text-lg text-slate-200 font-light max-w-2xl mx-auto drop-shadow-sm leading-relaxed"
					>
						{t.guideIntro.replace("{guestName}", "...")}
					</p>
				</div>
			</header>

			<main className="flex-grow w-full py-12 px-6 max-w-6xl mx-auto space-y-8">
				{/* Mandatory Registry Link Button Card - Critical High Priority Callout Banner */}
				<section
					id="guide-registry-banner"
					className="bg-gradient-to-br from-[#FF5A5F] to-[#FF444A] text-white rounded-3xl p-6 md:p-8 shadow-xl border border-[#FF5A5F]/15 relative overflow-hidden flex flex-col md:flex-row md:items-center md:justify-between gap-6"
				>
					<div className="absolute inset-0 bg-radial-gradient from-white/10 to-transparent pointer-events-none"></div>

					<div className="space-y-2 md:max-w-2xl relative z-10">
						<h2 className="text-xl md:text-2xl font-black flex items-center space-x-2.5">
							<FileText className="w-6 h-6 animate-pulse" />
							<span>{t.guideRegistryCardTitle}</span>
						</h2>
						<p className="text-sm text-white/95 leading-relaxed font-light">
							{t.guideRegistryCardDesc}
						</p>
					</div>

					<div className="shrink-0 relative z-10 w-full md:w-auto">
						<a
							id="registry-link"
							href="#"
							className="w-full md:w-auto inline-flex items-center justify-center space-x-2 bg-white hover:bg-slate-100 text-[#FF5A5F] py-4 px-8 rounded-2xl text-sm font-extrabold transition-all shadow-lg group transform hover:scale-[1.02] cursor-pointer"
						>
							<span>{t.guideFormBtn}</span>
							<ArrowRight className="w-4 h-4 transition-transform group-hover:translate-x-1" />
						</a>
					</div>
				</section>

				<div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
					{/* Left / Main Section (8 cols on large screens) */}
					<div className="lg:col-span-8 space-y-8">
						{/* Core Stay Details Panel */}
						<section className="bg-white rounded-3xl p-6 md:p-8 shadow-md border border-slate-100 relative overflow-hidden">
							<div className="absolute top-0 right-0 w-32 h-32 bg-amber-500/5 rounded-full blur-2xl pointer-events-none"></div>

							<h2 className="text-xl md:text-2xl font-extrabold text-slate-900 mb-6 flex items-center space-x-3 border-b border-slate-100 pb-4">
								<span className="p-2 bg-amber-500/10 text-amber-600 rounded-xl">
									<Building className="w-5 h-5" />
								</span>
								<span>{t.guidePropertyTitle}</span>
							</h2>

							<div className="grid grid-cols-1 md:grid-cols-2 gap-6">
								{/* Left Side: Apartment & Address */}
								<div className="space-y-5">
									<div>
										<span className="text-xs font-bold uppercase tracking-wider text-slate-400 block">
											{t.guideApartmentLabel}
										</span>
										<span
											id="display-apartment"
											className="text-2xl font-black text-slate-800 mt-1 block"
										>
											OceanViewFlats --
										</span>
									</div>

									<div>
										<span className="text-xs font-bold uppercase tracking-wider text-slate-400 block">
											{t.guideAddressLabel}
										</span>
										<div className="flex items-start space-x-2 mt-1.5">
											<MapPin className="w-4 h-4 text-rose-500 mt-0.5 shrink-0" />
											<p className="text-sm font-semibold text-slate-600 leading-relaxed">
												{t.guideAddressValue}
											</p>
										</div>
									</div>

									{/* Stay Dates */}
									<div className="grid grid-cols-2 gap-4 pt-2">
										<div className="bg-slate-50 rounded-2xl p-3 border border-slate-100">
											<span className="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">
												{t.guideCheckInLabel}
											</span>
											<div className="flex items-center space-x-1.5 mt-1 text-slate-700">
												<Calendar className="w-3.5 h-3.5 text-amber-500 shrink-0" />
												<span
													id="display-check-in"
													className="text-xs font-bold"
												>
													--
												</span>
											</div>
										</div>

										<div className="bg-slate-50 rounded-2xl p-3 border border-slate-100">
											<span className="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">
												{t.guideCheckOutLabel}
											</span>
											<div className="flex items-center space-x-1.5 mt-1 text-slate-700">
												<Calendar className="w-3.5 h-3.5 text-amber-500 shrink-0" />
												<span
													id="display-check-out"
													className="text-xs font-bold"
												>
													--
												</span>
											</div>
										</div>
									</div>
								</div>

								{/* Right Side: Key Lock Code */}
								<div
									id="door-code-card"
									className="bg-gradient-to-br from-amber-500/10 to-amber-600/[0.03] rounded-3xl p-5 md:p-6 border border-amber-500/15 flex flex-col justify-between"
								>
									<div className="space-y-3">
										<div className="flex justify-between items-center">
											<span className="text-xs font-bold uppercase tracking-wider text-amber-700/80 block">
												{t.guideCodeLabel}
											</span>
											<span
												id="door-lock-icon"
												className="p-1.5 bg-amber-500/15 text-amber-700 rounded-lg shrink-0"
											>
												<Lock className="w-4 h-4" />
											</span>
										</div>

										<div className="flex items-center space-x-3 mt-1 bg-white/70 backdrop-blur-xs rounded-2xl p-3 border border-amber-500/10 shadow-xs">
											<span
												id="display-door-code"
												className="text-2xl font-mono font-black text-slate-800 tracking-wider"
											>
												--
											</span>
											<button
												id="btn-copy-door-code"
												data-copy-target="display-door-code"
												className="p-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl transition-all shadow-xs cursor-pointer flex items-center justify-center shrink-0 ml-auto"
												title="Copy door code"
											>
												<Copy className="w-3.5 h-3.5" />
											</button>
										</div>

										{/* Gated locked credential banner */}
										<div
											id="credential-locked-notice"
											className="hidden p-3.5 bg-amber-500/15 border border-amber-500/30 rounded-2xl space-y-2 mt-2"
										>
											<div className="flex items-center space-x-1.5 text-amber-900 font-bold text-xs">
												<Lock className="w-3.5 h-3.5 text-amber-700 shrink-0" />
												<span id="credential-locked-title">
													{t.guideCredentialsLocked}
												</span>
											</div>
											<p
												id="credential-locked-desc"
												className="text-[11px] text-amber-800/90 leading-relaxed"
											>
												{t.guideCredentialsLockedDesc}
											</p>
											<a
												id="credential-locked-btn"
												href="#guide-registry-banner"
												className="inline-flex items-center space-x-1 text-xs font-bold text-white bg-[#FF5A5F] hover:bg-[#FF444A] px-3.5 py-1.5 rounded-xl transition-all shadow-xs"
											>
												<span>{t.guideActionUnlock}</span>
												<ArrowRight className="w-3 h-3 ml-1" />
											</a>
										</div>
									</div>

									<p
										id="door-code-instructions"
										className="text-xs text-amber-800/80 leading-relaxed font-medium mt-4 bg-amber-500/5 p-3 rounded-xl border border-amber-500/10"
									>
										💡 {t.guideCodeInstructions}
									</p>
								</div>
							</div>

							{/* Get Routes Map Buttons */}
							<div className="mt-8 pt-6 border-t border-slate-100 flex flex-wrap gap-4">
								<a
									href="https://www.google.com/maps/search/?api=1&query=Salguero+Sunset+Santa+Marta"
									target="_blank"
									rel="noopener noreferrer"
									className="flex items-center space-x-2 bg-slate-100 hover:bg-slate-200 text-slate-800 px-5 py-3 rounded-full text-xs font-bold transition-all shadow-xs"
								>
									<MapPin className="w-4 h-4 text-[#EA4335]" />
									<span>{t.guideRouteGoogle}</span>
									<ExternalLink className="w-3.5 h-3.5 text-slate-400" />
								</a>

								<a
									href="https://waze.com/ul?q=Salguero+Sunset+Santa+Marta&navigate=yes"
									target="_blank"
									rel="noopener noreferrer"
									className="flex items-center space-x-2 bg-[#33CCFF]/10 hover:bg-[#33CCFF]/20 text-[#0099FF] px-5 py-3 rounded-full text-xs font-bold transition-all shadow-xs"
								>
									<Compass className="w-4 h-4" />
									<span>{t.guideRouteWaze}</span>
									<ExternalLink className="w-3.5 h-3.5 text-[#33CCFF]" />
								</a>
							</div>
						</section>

						{/* Comprehensive Welcome Instructions List */}
						<section className="bg-white rounded-3xl p-6 md:p-8 shadow-md border border-slate-100">
							<h2 className="text-xl md:text-2xl font-extrabold text-slate-900 mb-6 flex items-center space-x-3 border-b border-slate-100 pb-4">
								<span className="p-2 bg-sky-500/10 text-sky-600 rounded-xl">
									<Key className="w-5 h-5" />
								</span>
								<span>{t.guideInstructionsTitle}</span>
							</h2>

							<div className="space-y-8">
								{/* 1. First Entry */}
								<div className="flex items-start space-x-4">
									<div className="w-8 h-8 rounded-full bg-slate-900 text-white font-bold text-sm flex items-center justify-center shrink-0 mt-0.5 shadow-sm">
										1
									</div>
									<div className="space-y-3 flex-grow">
										<h3 className="font-extrabold text-slate-900 text-base flex items-center">
											{t.guideFirstEntryTitle}
										</h3>
										<ul className="space-y-2.5 text-sm text-slate-600 leading-relaxed font-medium">
											<li className="flex items-start space-x-2">
												<CheckCircle2 className="w-4 h-4 text-emerald-500 mt-1 shrink-0" />
												<span>{t.guideFirstEntryDesc1}</span>
											</li>
											<li className="flex items-start space-x-2">
												<CheckCircle2 className="w-4 h-4 text-emerald-500 mt-1 shrink-0" />
												<span>{t.guideFirstEntryDesc2}</span>
											</li>
										</ul>
									</div>
								</div>

								{/* 2. During Stay */}
								<div className="flex items-start space-x-4 border-t border-slate-50 pt-6">
									<div className="w-8 h-8 rounded-full bg-slate-900 text-white font-bold text-sm flex items-center justify-center shrink-0 mt-0.5 shadow-sm">
										2
									</div>
									<div className="space-y-3 flex-grow">
										<h3 className="font-extrabold text-slate-900 text-base">
											{t.guideDuringStayTitle}
										</h3>
										<ul className="space-y-2.5 text-sm text-slate-600 leading-relaxed font-medium">
											<li className="flex items-start space-x-2">
												<CheckCircle2 className="w-4 h-4 text-emerald-500 mt-1 shrink-0" />
												<span>{t.guideDuringStayDesc1}</span>
											</li>
											<li className="flex items-start space-x-2 text-rose-600 font-bold">
												<ShieldAlert className="w-4 h-4 text-rose-500 mt-1 shrink-0" />
												<span>{t.guideDuringStayDesc2}</span>
											</li>
											<li className="flex items-start space-x-2 bg-slate-50 p-3 rounded-2xl border border-slate-100">
												<Info className="w-4 h-4 text-sky-500 mt-1 shrink-0" />
												<span>{t.guideDuringStayDesc3}</span>
											</li>
										</ul>
									</div>
								</div>

								{/* 3. Check-out */}
								<div className="flex items-start space-x-4 border-t border-slate-50 pt-6">
									<div className="w-8 h-8 rounded-full bg-slate-900 text-white font-bold text-sm flex items-center justify-center shrink-0 mt-0.5 shadow-sm">
										3
									</div>
									<div className="space-y-3 flex-grow">
										<h3 className="font-extrabold text-slate-900 text-base">
											{t.guideCheckoutTitle}
										</h3>
										<ul className="space-y-2.5 text-sm text-slate-600 leading-relaxed font-medium">
											<li className="flex items-start space-x-2">
												<CheckCircle2 className="w-4 h-4 text-emerald-500 mt-1 shrink-0" />
												<span>{t.guideCheckoutDesc1}</span>
											</li>
											<li className="flex items-start space-x-2">
												<CheckCircle2 className="w-4 h-4 text-emerald-500 mt-1 shrink-0" />
												<span>{t.guideCheckoutDesc2}</span>
											</li>
										</ul>
									</div>
								</div>
							</div>
						</section>
					</div>

					{/* Right Section (4 cols on large screens): Wifi, Registry, Support */}
					<div className="lg:col-span-4 space-y-8">
						{/* Wifi details card */}
						<section
							id="wifi-card"
							className="bg-white rounded-3xl p-6 shadow-md border border-slate-100 relative overflow-hidden"
						>
							<div className="absolute top-0 right-0 w-24 h-24 bg-sky-500/5 rounded-full blur-xl pointer-events-none"></div>

							<h2 className="text-lg font-extrabold text-slate-900 mb-4 flex items-center space-x-2 pb-3 border-b border-slate-50">
								<Wifi className="w-5 h-5 text-sky-500" />
								<span>{t.guideWifiTitle}</span>
							</h2>

							<div
								id="wifi-locked-notice"
								className="hidden p-3 mb-4 bg-amber-500/10 border border-amber-500/20 rounded-2xl text-xs text-amber-800 font-medium flex items-center space-x-2"
							>
								<Lock className="w-4 h-4 text-amber-600 shrink-0" />
								<span>{t.guideCredentialsLocked}</span>
							</div>

							<div className="space-y-4">
								<div className="bg-slate-50 rounded-2xl p-4 border border-slate-100 relative group">
									<span className="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">
										{t.guideWifiSSIDLabel}
									</span>
									<div className="flex items-center justify-between mt-1">
										<span
											id="display-wifi-ssid"
											className="font-black text-slate-800 text-sm tracking-wide"
										>
											--
										</span>
										<button
											id="btn-copy-wifi-ssid"
											data-copy-target="display-wifi-ssid"
											className="p-1.5 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-lg transition-all cursor-pointer"
											aria-label={t.guideCopySuccess}
										>
											<Copy className="w-3 h-3" />
										</button>
									</div>
								</div>

								<div className="bg-slate-50 rounded-2xl p-4 border border-slate-100 relative group">
									<span className="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">
										{t.guideWifiPassLabel}
									</span>
									<div className="flex items-center justify-between mt-1">
										<span
											id="display-wifi-password"
											className="font-black text-slate-800 text-sm tracking-wide"
										>
											--
										</span>
										<button
											id="btn-copy-wifi-pass"
											data-copy-target="display-wifi-password"
											className="p-1.5 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-lg transition-all cursor-pointer"
											aria-label={t.guideCopySuccess}
										>
											<Copy className="w-3 h-3" />
										</button>
									</div>
								</div>
							</div>
						</section>

						{/* Assigned Parking Space Card */}
						<section
							id="parking-card"
							className="bg-white rounded-3xl p-6 shadow-md border border-slate-100 relative overflow-hidden"
						>
							<div className="absolute top-0 right-0 w-24 h-24 bg-amber-500/5 rounded-full blur-xl pointer-events-none"></div>

							<h2 className="text-lg font-extrabold text-slate-900 mb-2 flex items-center space-x-2 pb-3 border-b border-slate-50">
								<Car className="w-5 h-5 text-amber-500" />
								<span>{t.guideParkingTitle}</span>
							</h2>

							<p className="text-xs text-slate-500 leading-relaxed font-medium mb-4">
								{t.guideParkingDesc}
							</p>

							<div
								id="parking-locked-notice"
								className="hidden p-3 mb-4 bg-amber-500/10 border border-amber-500/20 rounded-2xl text-xs text-amber-800 font-medium flex items-center space-x-2"
							>
								<Lock className="w-4 h-4 text-amber-600 shrink-0" />
								<span>{t.guideParkingLockedNotice}</span>
							</div>

							<div
								id="parking-spot-container"
								className="bg-slate-50 rounded-2xl p-4 border border-slate-100 relative group"
							>
								<span className="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">
									{t.guideParkingSpotLabel}
								</span>
								<div className="flex items-center justify-between mt-1">
									<span
										id="display-parking-spot"
										className="font-black text-slate-800 text-sm tracking-wide"
									>
										--
									</span>
									<button
										id="btn-copy-parking-spot"
										data-copy-target="display-parking-spot"
										className="p-1.5 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-lg transition-all cursor-pointer"
										aria-label={t.guideCopySuccess}
									>
										<Copy className="w-3 h-3" />
									</button>
								</div>
							</div>
						</section>

						{/* Building Administration Fee Card */}
						<section className="bg-white rounded-3xl p-6 shadow-md border border-slate-100">
							<h2 className="text-base font-extrabold text-slate-900 mb-3 flex items-center space-x-2">
								<Info className="w-4 h-4 text-amber-500 shrink-0" />
								<span>{t.guideAdminFeeTitle}</span>
							</h2>
							<p className="text-xs text-slate-500 leading-relaxed font-medium">
								{t.guideAdminFeeDesc}
							</p>
						</section>

						{/* Hotline Help & Contact lines */}
						<section className="bg-slate-900 text-white rounded-3xl p-6 shadow-md relative overflow-hidden">
							<div className="absolute top-0 right-0 w-20 h-24 bg-emerald-500/10 rounded-full blur-2xl pointer-events-none"></div>

							<h2 className="text-sm font-bold uppercase tracking-wider text-emerald-400 mb-2 flex items-center space-x-2">
								<Phone className="w-4 h-4 shrink-0" />
								<span>{t.guideAssistanceTitle}</span>
							</h2>

							<p className="text-xs text-slate-400 leading-relaxed font-light mb-5">
								{t.guideAssistanceDesc}
							</p>

							<div className="space-y-3">
								<a
									href="tel:+573108155234"
									className="w-full bg-slate-800 hover:bg-slate-700 text-white py-3 px-4 rounded-xl text-xs font-bold transition-all flex items-center justify-center space-x-2 border border-slate-700"
								>
									<Phone className="w-3.5 h-3.5" />
									<span>{t.guideCallSupport} (+57 310 815 5234)</span>
								</a>

								<a
									href="https://wa.me/573108155234"
									target="_blank"
									rel="noopener noreferrer"
									className="w-full bg-emerald-500 hover:bg-emerald-600 text-white py-3 px-4 rounded-xl text-xs font-bold transition-all flex items-center justify-center space-x-2 shadow-sm"
								>
									{/* WhatsApp simple icon using phone styling */}
									<Phone className="w-3.5 h-3.5" />
									<span>{t.guideWhatsAppSupport}</span>
								</a>
							</div>
						</section>
					</div>
				</div>
			</main>

			<Footer lang={lang} assetPrefix={assetPrefix} />

			{/* Global Copy Alert Overlay (rendered dynamically by guide.js) */}
			<div
				id="copy-alert"
				className="fixed bottom-6 right-6 bg-slate-900 text-white px-5 py-3 rounded-2xl text-xs font-bold shadow-xl border border-slate-800 flex items-center space-x-2 transform translate-y-24 opacity-0 transition-all duration-300 pointer-events-none z-50"
			>
				<Check className="w-4 h-4 text-emerald-400" />
				<span id="copy-alert-text">{t.guideCopySuccess}</span>
			</div>
		</div>
	);
}
