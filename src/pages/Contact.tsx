import {
	Calendar,
	Mail,
	MapPin,
	MessageSquare,
	Phone,
	ShieldCheck,
} from "lucide-react";
import React, { useEffect, useState } from "react";
import { Footer } from "../components/Footer";
import { Navigation } from "../components/Navigation";
import {
	getPrivacyPolicyPath,
	PRIVACY_POLICY_VERSION,
} from "../constants/config";
import { dict } from "../i18n/dict";
import type { AppProps } from "../types";

export default function Contact({ lang, assetPrefix = "../" }: AppProps) {
	const t = dict[lang];
	const [isScrolled, setIsScrolled] = useState(false);
	const privacyHref = getPrivacyPolicyPath(lang, assetPrefix);

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
			<header className="relative pt-32 pb-16 px-6 text-center overflow-hidden">
				<div className="absolute inset-0 w-full h-full">
					<img
						src={`${assetPrefix}images/main-entrance.webp`}
						alt="OceanViewFlats Main Entrance"
						className="w-full h-full object-cover"
					/>
					<div className="absolute inset-0 bg-slate-900/50"></div>
				</div>
				<div className="relative z-10 max-w-3xl mx-auto mt-4">
					<h1 className="text-4xl md:text-5xl font-extrabold mb-4 text-white tracking-tight drop-shadow-md">
						{t.contactTitle}
					</h1>
					<p className="text-lg md:text-xl text-slate-200 font-light max-w-2xl mx-auto drop-shadow">
						{t.contactSub}
					</p>
				</div>
			</header>

			<main className="flex-grow w-full py-12 px-6 max-w-6xl mx-auto">
				<div className="grid grid-cols-1 lg:grid-cols-12 gap-12 items-start">
					{/* Contact Details Panel */}
					<div className="lg:col-span-5 space-y-8">
						<div className="bg-white rounded-3xl p-8 shadow-md border border-slate-100 space-y-8">
							<h2 className="text-2xl font-bold text-slate-900 mb-6 flex items-center space-x-2">
								<span>OceanViewFlats</span>
							</h2>

							<div className="flex items-start space-x-4">
								<div className="p-3 bg-[#FF5A5F]/10 rounded-2xl text-[#FF5A5F]">
									<MapPin className="w-6 h-6" />
								</div>
								<div>
									<h3 className="font-bold text-slate-900">
										{t.contactAddressLabel}
									</h3>
									<p className="text-slate-600 mt-1 text-sm leading-relaxed">
										Calle 26 # 2-80, Playa Salguero
										<br />
										Santa Marta, Magdalena, Colombia
									</p>
								</div>
							</div>

							<div className="flex items-start space-x-4">
								<div className="p-3 bg-[#FF5A5F]/10 rounded-2xl text-[#FF5A5F]">
									<Mail className="w-6 h-6" />
								</div>
								<div>
									<h3 className="font-bold text-slate-900">
										{t.contactEmailLabel}
									</h3>
									<p className="text-slate-600 mt-1 text-sm">
										<a
											href="mailto:rentals@oceanviewflats.com"
											className="hover:text-[#FF5A5F] transition-colors"
										>
											rentals@oceanviewflats.com
										</a>
									</p>
								</div>
							</div>

							<div className="flex items-start space-x-4">
								<div className="p-3 bg-[#FF5A5F]/10 rounded-2xl text-[#FF5A5F]">
									<Phone className="w-6 h-6" />
								</div>
								<div>
									<h3 className="font-bold text-slate-900">
										{t.contactPhoneLabel}
									</h3>
									<p className="text-slate-600 mt-1 text-sm leading-relaxed flex flex-col space-y-1">
										<span>{t.contactHostLabel}</span>
										<span className="flex items-center space-x-3">
											<a
												href="tel:+573108155234"
												className="hover:text-[#FF5A5F] transition-colors font-medium"
											>
												+57 (310) 815-5234
											</a>
											<span className="text-slate-300">|</span>
											<a
												id="whatsapp-contact-link"
												href="https://wa.me/573108155234"
												target="_blank"
												rel="noopener noreferrer"
												className="text-emerald-600 hover:text-emerald-700 transition-colors font-semibold flex items-center space-x-1"
											>
												<span>WhatsApp</span>
											</a>
										</span>
									</p>
								</div>
							</div>
						</div>

						{/* Trust badge */}
						<div className="bg-slate-900 text-white rounded-3xl p-8 shadow-md flex items-start space-x-4">
							<div className="p-3 bg-emerald-500/20 text-emerald-400 rounded-2xl">
								<ShieldCheck className="w-6 h-6" />
							</div>
							<div>
								<h3 className="font-bold text-lg">{t.contactSafeLabel}</h3>
								<p className="text-slate-400 mt-2 text-sm leading-relaxed">
									{t.contactSafeDesc}
								</p>
							</div>
						</div>
					</div>

					{/* Contact Form Panel */}
					<div className="lg:col-span-7 bg-white rounded-3xl p-8 md:p-10 shadow-xl border border-slate-100">
						{/* Status Messages */}
						<div
							id="form-message"
							className="hidden p-4 rounded-2xl text-sm font-medium mb-6 transition-all duration-300"
						></div>

						<form
							id="contact-form"
							action={`${assetPrefix}api/contact-processor.php`}
							method="POST"
							className="space-y-6"
							noValidate
						>
							{/* Anti-spam Honeypot field (hidden from users) */}
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

							{/* Full Name */}
							<div className="flex flex-col">
								<label
									htmlFor="name"
									className="text-sm font-semibold text-slate-700 mb-2 flex justify-between"
								>
									<span>{t.contactName}</span>
									<span className="text-[#FF5A5F] text-xs font-bold uppercase tracking-wider">
										{t.contactRequired}
									</span>
								</label>
								<input
									type="text"
									id="name"
									name="name"
									required
									autoComplete="name"
									placeholder={t.contactPlaceholderName}
									className="px-4 py-3 rounded-xl border border-slate-200 focus:border-[#FF5A5F] focus:ring-2 focus:ring-[#FF5A5F]/20 focus:outline-none transition-all text-slate-800 placeholder-slate-400"
								/>
							</div>

							{/* Email Address */}
							<div className="flex flex-col">
								<label
									htmlFor="email"
									className="text-sm font-semibold text-slate-700 mb-2 flex justify-between"
								>
									<span>{t.contactEmail}</span>
									<span className="text-[#FF5A5F] text-xs font-bold uppercase tracking-wider">
										{t.contactRequired}
									</span>
								</label>
								<input
									type="email"
									id="email"
									name="email"
									required
									autoComplete="email"
									placeholder={t.contactPlaceholderEmail}
									className="px-4 py-3 rounded-xl border border-slate-200 focus:border-[#FF5A5F] focus:ring-2 focus:ring-[#FF5A5F]/20 focus:outline-none transition-all text-slate-800 placeholder-slate-400"
								/>
							</div>

							{/* Phone Number with Country Code Dropdown */}
							<div className="flex flex-col">
								<label
									htmlFor="phone-number"
									className="text-sm font-semibold text-slate-700 mb-2 flex justify-between"
								>
									<span>{t.contactPhone}</span>
									<span className="text-[#FF5A5F] text-xs font-bold uppercase tracking-wider">
										{t.contactRequired}
									</span>
								</label>
								<div className="flex rounded-xl border border-slate-200 focus-within:border-[#FF5A5F] focus-within:ring-2 focus-within:ring-[#FF5A5F]/20 transition-all overflow-hidden bg-white">
									<select
										id="phone-country-code"
										name="phone_country_code"
										autoComplete="tel-country-code"
										required
										className="bg-slate-50 border-r border-slate-200 px-3 py-3 text-slate-700 text-sm focus:outline-none cursor-pointer hover:bg-slate-100 transition-colors"
									>
										<option value="+57">🇨🇴 +57</option>
										<option value="+1">🇺🇸 +1</option>
										<option value="+34">🇪🇸 +34</option>
										<option value="+33">🇫🇷 +33</option>
										<option value="+39">🇮🇹 +39</option>
										<option value="+49">🇩🇪 +49</option>
										<option value="+81">🇯🇵 +81</option>
										<option value="+44">🇬🇧 +44</option>
										<option value="+52">🇲🇽 +52</option>
										<option value="+54">🇦🇷 +54</option>
										<option value="+55">🇧🇷 +55</option>
										<option value="+56">🇨🇱 +56</option>
										<option value="+51">🇵🇪 +51</option>
									</select>
									<input
										type="tel"
										id="phone-number"
										name="phone_number"
										required
										autoComplete="tel-national"
										placeholder={t.contactPlaceholderPhone}
										className="flex-grow px-4 py-3 text-slate-800 focus:outline-none placeholder-slate-400"
									/>
								</div>
							</div>

							{/* Optional Date Picker (Check-in & Check-out) */}
							<div className="grid grid-cols-1 md:grid-cols-2 gap-6 bg-slate-50 p-5 rounded-2xl border border-slate-100">
								<div className="flex flex-col">
									<label
										htmlFor="check-in"
										className="text-sm font-semibold text-slate-700 mb-2 flex items-center space-x-1"
									>
										<Calendar className="w-4 h-4 text-[#FF5A5F]" />
										<span>{t.contactCheckIn}</span>
										<span className="text-slate-400 text-xs font-normal">
											({t.contactOptional})
										</span>
									</label>
									<input
										type="date"
										id="check-in"
										name="check_in"
										className="px-4 py-2.5 rounded-xl border border-slate-200 focus:border-[#FF5A5F] focus:outline-none bg-white text-slate-800"
									/>
								</div>

								<div className="flex flex-col">
									<label
										htmlFor="check-out"
										className="text-sm font-semibold text-slate-700 mb-2 flex items-center space-x-1"
									>
										<Calendar className="w-4 h-4 text-[#FF5A5F]" />
										<span>{t.contactCheckOut}</span>
										<span className="text-slate-400 text-xs font-normal">
											({t.contactOptional})
										</span>
									</label>
									<input
										type="date"
										id="check-out"
										name="check_out"
										className="px-4 py-2.5 rounded-xl border border-slate-200 focus:border-[#FF5A5F] focus:outline-none bg-white text-slate-800"
									/>
								</div>
							</div>

							{/* Message / Inquiry */}
							<div className="flex flex-col">
								<label
									htmlFor="message"
									className="text-sm font-semibold text-slate-700 mb-2 flex justify-between"
								>
									<span>{t.contactMessage}</span>
									<span className="text-[#FF5A5F] text-xs font-bold uppercase tracking-wider">
										{t.contactRequired}
									</span>
								</label>
								<textarea
									id="message"
									name="message"
									required
									rows={5}
									placeholder={t.contactPlaceholderMessage}
									className="px-4 py-3 rounded-xl border border-slate-200 focus:border-[#FF5A5F] focus:ring-2 focus:ring-[#FF5A5F]/20 focus:outline-none transition-all text-slate-800 placeholder-slate-400 resize-y min-h-[120px]"
								></textarea>
							</div>

							{/* Anti-spam Math Captcha */}
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

							{/* Personal Data Authorization (Ley 1581 of 2012) */}
							<div className="flex items-start space-x-3 p-5 bg-slate-50 rounded-2xl border border-slate-100">
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
									{t.contactPrivacyConsentPrefix}{" "}
									<a
										href={privacyHref}
										target="_blank"
										rel="noopener noreferrer"
										className="font-semibold text-[#FF5A5F] hover:text-[#FF424D] underline"
									>
										{t.contactPrivacyConsentLink}
									</a>{" "}
									{t.contactPrivacyConsentSuffix}
								</label>
							</div>

							{/* Submit Button */}
							<button
								type="submit"
								id="submit-button"
								className="w-full bg-[#FF5A5F] hover:bg-[#FF424D] text-white py-4 rounded-full font-bold shadow-lg transition-all transform hover:scale-[1.02] focus:ring-4 focus:ring-[#FF5A5F]/35 flex items-center justify-center space-x-2 cursor-pointer"
							>
								<MessageSquare className="w-5 h-5" />
								<span id="submit-text">{t.contactSubmit}</span>
							</button>
						</form>
					</div>
				</div>
			</main>

			<Footer lang={lang} assetPrefix={assetPrefix} />
		</div>
	);
}
