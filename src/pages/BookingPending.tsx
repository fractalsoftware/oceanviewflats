import { Clock, Copy, Home } from "lucide-react";
import React, { useEffect, useState } from "react";
import { Footer } from "../components/Footer";
import { Navigation } from "../components/Navigation";
import { IMAGES } from "../constants/config";
import { dict } from "../i18n/dict";
import type { AppProps } from "../types";

export default function BookingPending({ lang, assetPrefix = "/" }: AppProps) {
	const t = dict[lang];
	const [isScrolled, setIsScrolled] = useState(false);
	const [reservationUid, setReservationUid] = useState<string | null>(null);
	const [copied, setCopied] = useState(false);

	useEffect(() => {
		if (typeof window === "undefined") return;
		const handleScroll = () => setIsScrolled(window.scrollY > 50);
		window.addEventListener("scroll", handleScroll);

		// Retrieve external_reference from query parameters
		const params = new URLSearchParams(window.location.search);
		const ref = params.get("external_reference") || params.get("preference_id");
		if (ref) {
			setReservationUid(ref);
		}

		return () => window.removeEventListener("scroll", handleScroll);
	}, []);

	const handleCopy = () => {
		if (!reservationUid || typeof window === "undefined") return;
		navigator.clipboard.writeText(reservationUid);
		setCopied(true);
		setTimeout(() => setCopied(false), 2000);
	};

	// Compute URLs
	const homeUrl =
		lang === "en" ? `${assetPrefix}` : `${assetPrefix}${lang}.html`;

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
					<div className="bg-slate-950/70 backdrop-blur-xl border border-amber-500/20 rounded-3xl p-8 md:p-12 shadow-2xl shadow-slate-950/50">
						{/* Animated Clock/Pending Badge */}
						<div className="inline-flex p-4 bg-amber-500/10 rounded-full border border-amber-500/30 shadow-lg mb-6 animate-[pulse_2.5s_infinite]">
							<Clock className="w-16 h-16 text-amber-400" />
						</div>

						<h1 className="text-3xl md:text-4xl font-extrabold mb-4 tracking-tight text-amber-100">
							{t.dbPendingHeader}
						</h1>

						<p className="text-slate-300 font-light mb-8 leading-relaxed">
							{t.dbPendingSubtitle}
						</p>

						{/* Display Booking Reference Code */}
						{reservationUid && (
							<div className="bg-slate-900/90 border border-slate-800 rounded-2xl p-4 mb-8 text-center">
								<span className="text-xs font-bold text-slate-400 tracking-wider uppercase block mb-1">
									Reservation Reference Code
								</span>
								<div className="flex items-center justify-center space-x-3">
									<span className="text-xl font-mono font-bold text-amber-300 select-all">
										{reservationUid}
									</span>
									<button
										onClick={handleCopy}
										className="p-1.5 hover:bg-slate-800 rounded-lg text-slate-400 hover:text-slate-200 transition-colors"
										title="Copy Code"
									>
										<Copy className="w-4 h-4" />
									</button>
									{copied && (
										<span className="text-xs text-amber-400 font-semibold animate-fade-in">
											{t.guideCopySuccess}
										</span>
									)}
								</div>
							</div>
						)}

						{/* Actions */}
						<div className="flex justify-center">
							<a
								href={homeUrl}
								className="flex items-center justify-center space-x-2 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white px-8 py-4 rounded-2xl font-bold transition-transform duration-300 hover:scale-[1.02] shadow-lg shadow-amber-950/20"
							>
								<Home className="w-5 h-5 mr-1" />
								<span>{t.dbPendingHomeBtn}</span>
							</a>
						</div>
					</div>
				</div>
			</main>

			<Footer lang={lang} assetPrefix={assetPrefix} />
		</div>
	);
}
