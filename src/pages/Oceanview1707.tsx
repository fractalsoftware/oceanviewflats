import React, { useEffect, useState } from "react";
import { AboutAmenities } from "../components/AboutAmenities";
import { BookingSection } from "../components/BookingSection";
import { Footer } from "../components/Footer";
import { Hero } from "../components/Hero";
import { ImageGallery } from "../components/ImageGallery";
import { Navigation } from "../components/Navigation";
import { NomadSection } from "../components/NomadSection";
import { QuickStats } from "../components/QuickStats";
import { ReviewsSection1707 } from "../components/ReviewsSection1707";
import type { AppProps } from "../types";

export default function Oceanview1707({ lang, assetPrefix = "./" }: AppProps) {
	const [isScrolled, setIsScrolled] = useState(false);

	// We'll keep the scroll effect but in static HTML it will only work if we include this script
	useEffect(() => {
		if (typeof window === "undefined") return;
		const handleScroll = () => setIsScrolled(window.scrollY > 50);
		window.addEventListener("scroll", handleScroll);
		return () => window.removeEventListener("scroll", handleScroll);
	}, []);

	return (
		<div className="min-h-screen bg-slate-50 font-sans text-slate-800 scroll-smooth">
			<Navigation
				isScrolled={isScrolled}
				lang={lang}
				assetPrefix={assetPrefix}
			/>
			<main>
				<Hero lang={lang} propertyId="1707" assetPrefix={assetPrefix} />
				<QuickStats lang={lang} propertyId="1707" />
				<ImageGallery lang={lang} property="1707" assetPrefix={assetPrefix} />
				<NomadSection lang={lang} assetPrefix={assetPrefix} />
				<AboutAmenities
					lang={lang}
					propertyId="1707"
					assetPrefix={assetPrefix}
				/>
				<ReviewsSection1707 lang={lang} />
				<BookingSection
					lang={lang}
					propertyId="1707"
					assetPrefix={assetPrefix}
				/>
			</main>
			<Footer lang={lang} assetPrefix={assetPrefix} />
		</div>
	);
}
