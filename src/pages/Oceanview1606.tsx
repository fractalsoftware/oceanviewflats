import React, { useEffect, useState } from "react";
import { AboutAmenities } from "../components/AboutAmenities";
import { BookingSection } from "../components/BookingSection";
import { Footer } from "../components/Footer";
// Shared Unified Components
import { Hero } from "../components/Hero";
import { ImageGallery } from "../components/ImageGallery";
import { Navigation } from "../components/Navigation";
import { QuickStats } from "../components/QuickStats";
import { ReviewsSection } from "../components/warm/ReviewsSection";

// Unique Theme Components
import { RomanticFamilySection } from "../components/warm/RomanticFamilySection";
import type { AppProps } from "../types";

export default function Oceanview1606({ lang, assetPrefix = "./" }: AppProps) {
	const [isScrolled, setIsScrolled] = useState(false);

	useEffect(() => {
		if (typeof window === "undefined") return;
		const handleScroll = () => setIsScrolled(window.scrollY > 50);
		window.addEventListener("scroll", handleScroll);
		return () => window.removeEventListener("scroll", handleScroll);
	}, []);

	return (
		<div className="min-h-screen bg-stone-50 font-sans text-stone-800 scroll-smooth">
			<Navigation
				isScrolled={isScrolled}
				lang={lang}
				assetPrefix={assetPrefix}
			/>
			<main>
				<Hero lang={lang} propertyId="1606" assetPrefix={assetPrefix} />
				<QuickStats lang={lang} propertyId="1606" />
				<ImageGallery lang={lang} property="1606" assetPrefix={assetPrefix} />
				<RomanticFamilySection lang={lang} assetPrefix={assetPrefix} />
				<AboutAmenities
					lang={lang}
					propertyId="1606"
					assetPrefix={assetPrefix}
				/>
				<ReviewsSection lang={lang} />
				<BookingSection
					lang={lang}
					propertyId="1606"
					assetPrefix={assetPrefix}
				/>
			</main>
			<Footer lang={lang} assetPrefix={assetPrefix} />
		</div>
	);
}
