import {
	ArrowRight,
	Building,
	Calendar,
	Car,
	CheckCircle2,
	FileText,
	Plus,
	ShieldAlert,
	ShieldCheck,
	Trash2,
	User,
	Users,
} from "lucide-react";
import React, { useEffect, useState } from "react";
import { Footer } from "../components/Footer";
import { Navigation } from "../components/Navigation";
import {
	getGuidePagePath,
	getHomePagePath,
	getPrivacyPolicyPath,
	IMAGES,
	PRIVACY_POLICY_VERSION,
	REGISTRY_GUEST_SLOTS,
} from "../constants/config";
import { CONDOMINIUM_PORTAL_COUNTRIES } from "../constants/countries";
import { dict } from "../i18n/dict";
import type { AppProps } from "../types";

export default function Registry({ lang, assetPrefix = "../" }: AppProps) {
	const t = dict[lang];
	const [isScrolled, setIsScrolled] = useState(false);
	const privacyHref = getPrivacyPolicyPath(lang, assetPrefix);
	const guideHref = getGuidePagePath(lang, assetPrefix);
	const homeHref = getHomePagePath(lang, assetPrefix);

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
						src={`${assetPrefix}${IMAGES.mainEntrance}`}
						alt="OceanViewFlats Beachfront Entrance"
						className="w-full h-full object-cover"
					/>
					<div className="absolute inset-0 bg-slate-900/60 backdrop-blur-[2px]"></div>
				</div>
				<div className="relative z-10 max-w-3xl mx-auto mt-4">
					<h1 className="text-4xl md:text-5xl font-extrabold mb-4 text-white tracking-tight drop-shadow-md">
						{t.registryTitle}
					</h1>
					<p className="text-base md:text-lg text-slate-200 font-light max-w-2xl mx-auto drop-shadow-sm leading-relaxed">
						{t.registrySubtitle}
					</p>
				</div>
			</header>

			<main className="flex-grow w-full py-12 px-6 max-w-5xl mx-auto">
				{/* Main layout grid */}
				<div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
					{/* Left Panel: Stay Details & Legal Trust */}
					<div className="lg:col-span-4 space-y-6">
						{/* Stay Summary Card (Populated dynamically on client) */}
						<div className="bg-white rounded-3xl p-6 shadow-md border border-slate-100">
							<h2 className="text-lg font-bold text-slate-900 mb-4 flex items-center space-x-2 border-b border-slate-100 pb-3">
								<Calendar className="w-5 h-5 text-[#FF5A5F]" />
								<span>{t.registryStayDetails}</span>
							</h2>

							<div className="space-y-4">
								<div>
									<span className="text-xs font-semibold uppercase tracking-wider text-slate-400 block">
										{t.registryProperty}
									</span>
									<div className="flex items-center space-x-2 mt-1">
										<Building className="w-4 h-4 text-[#FF5A5F]" />
										<span
											id="display-property"
											className="font-bold text-slate-800 text-sm"
										>
											--
										</span>
									</div>
								</div>

								<div className="grid grid-cols-2 gap-4">
									<div>
										<span className="text-xs font-semibold uppercase tracking-wider text-slate-400 block">
											{t.registryCheckIn}
										</span>
										<span
											id="display-check-in"
											className="font-bold text-slate-700 text-sm mt-1 block"
										>
											--
										</span>
									</div>
									<div>
										<span className="text-xs font-semibold uppercase tracking-wider text-slate-400 block">
											{t.registryCheckOut}
										</span>
										<span
											id="display-check-out"
											className="font-bold text-slate-700 text-sm mt-1 block"
										>
											--
										</span>
									</div>
								</div>
							</div>
						</div>

						{/* Legal Trust Banner */}
						<div className="bg-slate-900 text-white rounded-3xl p-6 shadow-md flex items-start space-x-4">
							<div className="p-3 bg-emerald-500/20 text-emerald-400 rounded-2xl shrink-0">
								<ShieldCheck className="w-6 h-6" />
							</div>
							<div>
								<h3 className="font-bold text-sm uppercase tracking-wide text-emerald-400">
									{t.registryLegalTitle}
								</h3>
								<p className="text-slate-400 mt-2 text-xs leading-relaxed">
									{t.registryLegalDesc}
								</p>
							</div>
						</div>
					</div>

					{/* Right Panel: Dynamic Registry Form */}
					<div className="lg:col-span-8 bg-white rounded-3xl p-6 md:p-8 shadow-xl border border-slate-100">
						{/* Status Message Box */}
						<div
							id="form-message"
							className="hidden p-5 rounded-2xl text-sm font-medium mb-6 transition-all duration-300"
						></div>

						{/* Successful Confirmation Overlay / Message (Hidden initially) */}
						<div
							id="success-overlay"
							className="hidden text-center py-10 px-4 space-y-6"
						>
							<div className="w-20 h-20 bg-emerald-500/10 text-emerald-500 rounded-full flex items-center justify-center mx-auto animate-bounce">
								<CheckCircle2 className="w-12 h-12" />
							</div>
							<h2
								id="success-title"
								className="text-2xl md:text-3xl font-extrabold text-slate-900"
							>
								{t.registrySuccess}
							</h2>
							<p
								id="success-desc"
								className="text-slate-600 max-w-xl mx-auto leading-relaxed text-sm md:text-base font-light"
							>
								{t.registryWarmMsg}
							</p>
							<div className="pt-4 flex flex-col sm:flex-row items-center justify-center gap-3">
								<a
									id="btn-unlocked-guide"
									href={guideHref}
									className="w-full sm:w-auto inline-flex items-center justify-center space-x-2 bg-gradient-to-r from-sky-500 to-blue-600 hover:from-sky-600 hover:to-blue-700 text-white px-8 py-3.5 rounded-full font-bold shadow-lg transition-all transform hover:scale-[1.02]"
								>
									<span>{t.registryBtnViewGuide}</span>
									<ArrowRight className="w-4 h-4 ml-1" />
								</a>
								<a
									href={homeHref}
									className="w-full sm:w-auto inline-block bg-slate-100 hover:bg-slate-200 text-slate-800 px-6 py-3.5 rounded-full font-semibold transition-all"
								>
									Return to Home
								</a>
							</div>
						</div>

						<form
							id="registry-form"
							action={`${assetPrefix}api/registry-processor.php`}
							method="POST"
							className="space-y-6 transition-opacity duration-300"
							noValidate
						>
							{/* Hidden Stay Parameter Fields */}
							<input type="hidden" name="property" id="hidden-property" />
							<input type="hidden" name="check_in" id="hidden-check-in" />
							<input type="hidden" name="check_out" id="hidden-check-out" />
							<input
								type="hidden"
								name="reservation_code"
								id="hidden-reservation-code"
							/>

							{/* Honeypot field (hidden from users) */}
							<div className="sr-only">
								<label htmlFor="website-url">Website URL (leave empty)</label>
								<input
									type="text"
									id="website-url"
									name="website_url"
									tabIndex={-1}
									autoComplete="off"
								/>
							</div>

							{/* Dynamic Guests Grid Container */}
							<div className="space-y-6">
								<h3 className="text-lg font-bold text-slate-800 flex items-center space-x-2 border-b border-slate-100 pb-2">
									<Users className="w-5 h-5 text-[#FF5A5F]" />
									<span>{t.registryGuestInfo}</span>
								</h3>

								{/* Pre-render 12 guest segments (up to Occupancy Override max_guests = 12). Javascript will toggle hidden states */}
								{REGISTRY_GUEST_SLOTS.map((slot) => (
									<div
										key={slot.num}
										id={`guest-card-${slot.num}`}
										className={slot.cardClassName}
									>
										<div className="flex justify-between items-center mb-4">
											<h4 className="font-bold text-slate-700 flex items-center space-x-2">
												<User className="w-4 h-4 text-[#FF5A5F]" />
												<span>
													{t.registryGuestNum.replace(
														"{num}",
														slot.num.toString(),
													)}
												</span>
											</h4>
											{slot.isCompanion && (
												<button
													type="button"
													onClick={() => {}} // Hooked in registry.js
													data-remove-guest={slot.num}
													className="text-slate-400 hover:text-red-500 hover:bg-red-50 p-1.5 rounded-lg transition-all cursor-pointer flex items-center space-x-1 text-xs font-semibold"
												>
													<Trash2 className="w-3.5 h-3.5" />
													<span>{t.registryRemoveGuest}</span>
												</button>
											)}
										</div>

										<div className="grid grid-cols-1 md:grid-cols-2 gap-4">
											{/* First Name */}
											<div className="flex flex-col">
												<label
													htmlFor={`guest-first-name-${slot.num}`}
													className="text-xs font-bold text-slate-500 mb-1 flex justify-between"
												>
													<span>{t.registryFirstName}</span>
													{slot.isPrimary && (
														<span className="text-[#FF5A5F] text-[10px] uppercase font-bold tracking-wider">
															{t.registryRequired}
														</span>
													)}
												</label>
												<input
													type="text"
													id={`guest-first-name-${slot.num}`}
													name={`guest_first_name_${slot.num}`}
													required={slot.isPrimary}
													placeholder={t.registryPlaceholderFirstName}
													className="px-3.5 py-2.5 rounded-xl border border-slate-200 focus:border-[#FF5A5F] focus:ring-2 focus:ring-[#FF5A5F]/15 focus:outline-none transition-all text-sm text-slate-800 placeholder-slate-400 bg-white"
												/>
											</div>

											{/* Last Name */}
											<div className="flex flex-col">
												<label
													htmlFor={`guest-last-name-${slot.num}`}
													className="text-xs font-bold text-slate-500 mb-1 flex justify-between"
												>
													<span>{t.registryLastName}</span>
													{slot.isPrimary && (
														<span className="text-[#FF5A5F] text-[10px] uppercase font-bold tracking-wider">
															{t.registryRequired}
														</span>
													)}
												</label>
												<input
													type="text"
													id={`guest-last-name-${slot.num}`}
													name={`guest_last_name_${slot.num}`}
													required={slot.isPrimary}
													placeholder={t.registryPlaceholderLastName}
													className="px-3.5 py-2.5 rounded-xl border border-slate-200 focus:border-[#FF5A5F] focus:ring-2 focus:ring-[#FF5A5F]/15 focus:outline-none transition-all text-sm text-slate-800 placeholder-slate-400 bg-white"
												/>
											</div>

											{/* Backwards-compatibility hidden name field */}
											<input
												type="hidden"
												id={`guest-name-${slot.num}`}
												name={`guest_name_${slot.num}`}
											/>

											{/* Primary Guest Email (Guest 1 only) */}
											{slot.isPrimary && (
												<div className="flex flex-col">
													<label
														htmlFor="guest-email-1"
														className="text-xs font-bold text-slate-500 mb-1 flex justify-between"
													>
														<span>{t.registryGuestEmail}</span>
														<span className="text-[#FF5A5F] text-[10px] uppercase font-bold tracking-wider">
															{t.registryRequiredBadge ?? t.registryRequired}
														</span>
													</label>
													<input
														type="email"
														id="guest-email-1"
														name="guest_email_1"
														required
														placeholder={t.registryPlaceholderEmail}
														className="px-3.5 py-2.5 rounded-xl border border-slate-200 focus:border-[#FF5A5F] focus:ring-2 focus:ring-[#FF5A5F]/15 focus:outline-none transition-all text-sm text-slate-800 placeholder-slate-400 bg-white"
													/>
												</div>
											)}

											{/* Phone Number */}
											<div className="flex flex-col">
												<label
													htmlFor={`guest-phone-${slot.num}`}
													className="text-xs font-bold text-slate-500 mb-1 flex justify-between"
												>
													<span>{t[slot.phoneLabelKey]}</span>
													{slot.isPrimary && (
														<span className="text-[#FF5A5F] text-[10px] uppercase font-bold tracking-wider">
															{t.registryRequired}
														</span>
													)}
												</label>
												<input
													type="tel"
													id={`guest-phone-${slot.num}`}
													name={`guest_phone_${slot.num}`}
													required={slot.isPrimary}
													placeholder={t.registryPlaceholderPhone}
													className="px-3.5 py-2.5 rounded-xl border border-slate-200 focus:border-[#FF5A5F] focus:ring-2 focus:ring-[#FF5A5F]/15 focus:outline-none transition-all text-sm text-slate-800 placeholder-slate-400 bg-white"
												/>
											</div>

											{/* Country of Nationality / Residence */}
											<div className="flex flex-col">
												<label
													htmlFor={`guest-country-${slot.num}`}
													className="text-xs font-bold text-slate-500 mb-1 flex justify-between"
												>
													<span>{t.registryCountry}</span>
													{slot.isPrimary && (
														<span className="text-[#FF5A5F] text-[10px] uppercase font-bold tracking-wider">
															{t.registryRequired}
														</span>
													)}
												</label>
												<select
													id={`guest-country-${slot.num}`}
													name={`guest_country_${slot.num}`}
													required={slot.isPrimary}
													defaultValue="COLOMBIA"
													className="px-3.5 py-2.5 rounded-xl border border-slate-200 focus:border-[#FF5A5F] focus:outline-none text-sm text-slate-700 bg-white cursor-pointer hover:bg-slate-50/50 transition-colors"
												>
													{CONDOMINIUM_PORTAL_COUNTRIES.map((c) => (
														<option key={c.code} value={c.code}>
															{(t as Record<string, string>)[c.dictKey] ??
																c.code}
														</option>
													))}
												</select>
											</div>

											{/* Age */}
											<div className="flex flex-col">
												<label
													htmlFor={`guest-age-${slot.num}`}
													className="text-xs font-bold text-slate-500 mb-1 flex justify-between"
												>
													<span>{t.registryAge}</span>
													{slot.isPrimary && (
														<span className="text-[#FF5A5F] text-[10px] uppercase font-bold tracking-wider">
															{t.registryRequired}
														</span>
													)}
												</label>
												<input
													type="number"
													id={`guest-age-${slot.num}`}
													name={`guest_age_${slot.num}`}
													min="0"
													max="120"
													required={slot.isPrimary}
													placeholder={t.registryPlaceholderAge}
													className="px-3.5 py-2.5 rounded-xl border border-slate-200 focus:border-[#FF5A5F] focus:ring-2 focus:ring-[#FF5A5F]/15 focus:outline-none transition-all text-sm text-slate-800 placeholder-slate-400 bg-white"
												/>
											</div>

											{/* Document Type */}
											<div className="flex flex-col">
												<label
													htmlFor={`guest-doc-type-${slot.num}`}
													className="text-xs font-bold text-slate-500 mb-1 flex justify-between"
												>
													<span>{t.registryDocType}</span>
													{slot.isPrimary && (
														<span className="text-[#FF5A5F] text-[10px] uppercase font-bold tracking-wider">
															{t.registryRequired}
														</span>
													)}
												</label>
												<select
													id={`guest-doc-type-${slot.num}`}
													name={`guest_doc_type_${slot.num}`}
													required={slot.isPrimary}
													className="px-3.5 py-2.5 rounded-xl border border-slate-200 focus:border-[#FF5A5F] focus:outline-none text-sm text-slate-700 bg-white cursor-pointer hover:bg-slate-50/50 transition-colors"
												>
													<option value="Passport">
														{t.registryDocTypePassport}
													</option>
													<option value="Cédula de Ciudadanía">
														{t.registryDocTypeCedula}
													</option>
													<option value="Tarjeta de Identidad">
														{t.registryDocTypeTarjetaIdentidad}
													</option>
													<option value="Registro Civil">
														{t.registryDocTypeRegistroCivil}
													</option>
													<option value="National ID">
														{t.registryDocTypeNational}
													</option>
													<option value="Driver License">
														{t.registryDocTypeDriver}
													</option>
													<option value="Other ID">
														{t.registryDocTypeOther}
													</option>
												</select>
											</div>

											{/* Document Number */}
											<div className="flex flex-col">
												<label
													htmlFor={`guest-doc-num-${slot.num}`}
													className="text-xs font-bold text-slate-500 mb-1 flex justify-between"
												>
													<span>{t.registryDocNumber}</span>
													{slot.isPrimary && (
														<span className="text-[#FF5A5F] text-[10px] uppercase font-bold tracking-wider">
															{t.registryRequired}
														</span>
													)}
												</label>
												<input
													type="text"
													id={`guest-doc-num-${slot.num}`}
													name={`guest_doc_num_${slot.num}`}
													required={slot.isPrimary}
													placeholder={t.registryPlaceholderDocNumber}
													className="px-3.5 py-2.5 rounded-xl border border-slate-200 focus:border-[#FF5A5F] focus:ring-2 focus:ring-[#FF5A5F]/15 focus:outline-none transition-all text-sm text-slate-800 placeholder-slate-400 bg-white"
												/>
											</div>
										</div>
									</div>
								))}

								{/* Add Guest Button */}
								<button
									type="button"
									id="add-guest-button"
									className="w-full py-3.5 rounded-2xl border-2 border-dashed border-slate-200 hover:border-[#FF5A5F] text-slate-500 hover:text-[#FF5A5F] font-bold text-sm transition-all hover:bg-slate-50/50 flex items-center justify-center space-x-2 cursor-pointer"
								>
									<Plus className="w-4 h-4" />
									<span>{t.registryAddGuest}</span>
								</button>
							</div>

							{/* Dynamic Input for total count */}
							<input
								type="hidden"
								name="guest_count"
								id="guest-count-input"
								defaultValue="1"
							/>

							{/* Optional Car Section */}
							<div className="bg-slate-50/50 rounded-3xl p-5 border border-slate-150 space-y-4">
								<h4 className="font-bold text-slate-800 flex items-center space-x-2">
									<Car className="w-5 h-5 text-slate-500" />
									<span>{t.registryCarOpt}</span>
								</h4>
								<p className="text-xs text-slate-500 leading-relaxed font-light">
									{t.registryCarDesc}
								</p>
								<div className="grid grid-cols-1 md:grid-cols-2 gap-4 pt-1">
									<div className="flex flex-col">
										<label
											htmlFor="car-plates"
											className="text-xs font-bold text-slate-500 mb-1"
										>
											{t.registryCarPlates}
										</label>
										<input
											type="text"
											id="car-plates"
											name="car_plates"
											placeholder={t.registryPlaceholderCarPlates}
											className="px-3.5 py-2.5 rounded-xl border border-slate-200 focus:border-[#FF5A5F] focus:outline-none text-sm text-slate-800 placeholder-slate-400 bg-white"
										/>
									</div>
									<div className="flex flex-col">
										<label
											htmlFor="car-model"
											className="text-xs font-bold text-slate-500 mb-1"
										>
											{t.registryCarModel}
										</label>
										<input
											type="text"
											id="car-model"
											name="car_model"
											placeholder={t.registryPlaceholderCarModel}
											className="px-3.5 py-2.5 rounded-xl border border-slate-200 focus:border-[#FF5A5F] focus:outline-none text-sm text-slate-800 placeholder-slate-400 bg-white"
										/>
									</div>
								</div>
							</div>

							{/* Captcha Block */}
							<div className="flex flex-col p-5 bg-slate-50 rounded-2xl border border-slate-100">
								<label
									htmlFor="captcha-response"
									className="text-sm font-semibold text-slate-700 mb-2 flex justify-between"
								>
									<span id="captcha-label">{t.contactCaptchaLabel}</span>
									<span className="text-[#FF5A5F] text-xs font-bold uppercase tracking-wider">
										{t.contactRequired}
									</span>
								</label>
								<input
									type="number"
									id="captcha-response"
									name="captcha_response"
									required
									placeholder="?"
									className="px-4 py-3 rounded-xl border border-slate-200 focus:border-[#FF5A5F] focus:ring-2 focus:ring-[#FF5A5F]/20 focus:outline-none transition-all text-slate-800 placeholder-slate-400"
								/>
								<input
									type="hidden"
									id="captcha-challenge"
									name="captcha_challenge"
								/>
							</div>

							{/* Reactive Minor Guardian Attestation (Ley 1581 Art. 7 & Ley 679 of 2001) */}
							<div
								id="minor-attestation-container"
								className="hidden p-5 bg-amber-50/80 rounded-2xl border border-amber-200 space-y-3 transition-all duration-300"
							>
								<div className="flex items-center space-x-2 text-amber-800">
									<ShieldAlert className="w-5 h-5 text-amber-600 shrink-0" />
									<div className="flex flex-wrap items-center gap-2">
										<span className="text-xs font-extrabold uppercase tracking-wider px-2.5 py-0.5 rounded-full bg-amber-200/70 text-amber-900">
											{t.registryMinorConsentBadge}
										</span>
										<h4 className="text-sm font-bold text-amber-950">
											{t.registryMinorConsentTitle}
										</h4>
									</div>
								</div>
								<div className="flex items-start space-x-3 pt-1">
									<input
										type="checkbox"
										id="minor-guardian-consent"
										name="minor_guardian_consent"
										value="1"
										disabled
										className="mt-1 h-4 w-4 rounded border-amber-400 text-[#FF5A5F] focus:ring-[#FF5A5F] cursor-pointer shrink-0"
									/>
									<label
										htmlFor="minor-guardian-consent"
										className="text-xs text-amber-900 leading-relaxed cursor-pointer"
									>
										{t.registryMinorConsentText}
									</label>
								</div>
							</div>

							{/* Personal Data Authorization (Ley 1581 of 2012) */}
							<div
								id="privacy-consent-container"
								className="flex items-start space-x-3 p-5 bg-slate-50 rounded-2xl border border-slate-100"
							>
								<input
									type="checkbox"
									id="privacy-consent"
									name="privacy_consent"
									value="1"
									required
									className="mt-1 h-4 w-4 rounded border-slate-300 text-[#FF5A5F] focus:ring-[#FF5A5F] cursor-pointer shrink-0"
								/>
								<input
									type="hidden"
									id="privacy-policy-version"
									name="privacy_policy_version"
									value={PRIVACY_POLICY_VERSION}
								/>
								<label
									htmlFor="privacy-consent"
									className="text-xs text-slate-600 leading-relaxed cursor-pointer"
								>
									{t.registryPrivacyConsentPrefix}{" "}
									<a
										href={privacyHref}
										target="_blank"
										rel="noopener noreferrer"
										className="font-semibold text-[#FF5A5F] hover:text-[#FF424D] underline"
									>
										{t.registryPrivacyConsentLink}
									</a>{" "}
									{t.registryPrivacyConsentSuffix}
								</label>
							</div>

							{/* Submit Button */}
							<button
								type="submit"
								id="submit-button"
								className="w-full bg-[#FF5A5F] hover:bg-[#FF424D] text-white py-4 rounded-full font-bold shadow-lg transition-all transform hover:scale-[1.01] focus:ring-4 focus:ring-[#FF5A5F]/35 flex items-center justify-center space-x-2 cursor-pointer mt-4"
							>
								<FileText className="w-5 h-5" />
								<span id="submit-text">{t.registrySubmit}</span>
							</button>
						</form>
					</div>
				</div>
			</main>

			<Footer lang={lang} assetPrefix={assetPrefix} />
		</div>
	);
}
