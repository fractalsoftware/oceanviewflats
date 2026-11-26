import {
	ChevronLeft,
	ChevronRight,
	LayoutGrid,
	Sparkles,
	X,
} from "lucide-react";
import React from "react";
import { PROPERTY_GALLERY_IMAGES } from "../constants/config";
import { dict } from "../i18n/dict";
import type { Lang } from "../types";

interface ImageGalleryProps {
	lang: Lang;
	property: "1707" | "1606";
	assetPrefix?: string;
}

export const ImageGallery = ({
	lang,
	property,
	assetPrefix = "./",
}: ImageGalleryProps) => {
	const t = dict[lang];

	// Configure images from centralized PROPERTY_GALLERY_IMAGES (14 items each)
	const images = PROPERTY_GALLERY_IMAGES[property].map((img) => ({
		...img,
		caption: t[img.altKey],
		alt: t[img.altKey],
	}));

	// Theme styling based on cool/warm properties
	const theme = {
		ring: property === "1707" ? "focus:ring-teal-500" : "focus:ring-rose-500",
		text: property === "1707" ? "text-slate-900" : "text-stone-900",
		subtext: property === "1707" ? "text-slate-500" : "text-stone-500",
		buttonColor:
			property === "1707"
				? "bg-slate-900 text-white hover:bg-slate-800"
				: "bg-stone-900 text-white hover:bg-stone-800",
		primaryIcon: property === "1707" ? "text-teal-600" : "text-rose-600",
		containerBg: property === "1707" ? "bg-slate-50" : "bg-stone-50",
		shadow: property === "1707" ? "shadow-slate-200/50" : "shadow-stone-200/50",
		badgeBg:
			property === "1707"
				? "bg-teal-50 text-teal-700"
				: "bg-rose-50 text-rose-700",
	};

	return (
		<section
			id="gallery-section"
			className={`py-12 md:py-16 ${theme.containerBg} overflow-hidden`}
			aria-labelledby="gallery-title"
		>
			<div className="max-w-6xl mx-auto px-6">
				{/* Gallery Header */}
				<div className="flex flex-col md:flex-row md:items-end justify-between mb-8">
					<div>
						<h2
							id="gallery-title"
							className={`text-3xl md:text-4xl font-extrabold tracking-tight ${theme.text}`}
						>
							{t.galleryTitle}
						</h2>
						<p
							className={`mt-2 text-base md:text-lg font-light ${theme.subtext}`}
						>
							{t.gallerySubtitle}
						</p>
					</div>

					{/* Swipe Indicator for Mobile */}
					<div className="flex md:hidden items-center space-x-1.5 mt-4 text-xs font-medium text-slate-500 animate-pulse">
						<Sparkles className={`w-3.5 h-3.5 ${theme.primaryIcon}`} />
						<span>Swipe thumbnails or click to open</span>
					</div>
				</div>

				{/* Gallery Grid/Slider Component */}
				<div className="relative">
					{/* 1. Desktop Premium 5-Photo Mosaic Layout */}
					<div className="hidden md:grid grid-cols-4 grid-rows-2 gap-3 h-[460px] relative rounded-3xl overflow-hidden shadow-2xl">
						{/* Main large image (Left) */}
						<button
							className={`gallery-trigger group relative col-span-2 row-span-2 w-full h-full overflow-hidden outline-none ${theme.ring} focus:ring-2 focus:ring-offset-2 cursor-pointer`}
							data-index="0"
							data-src={`${assetPrefix}${images[0].src}`}
							data-alt={images[0].alt}
							data-caption={images[0].caption}
							aria-label={`Open photo 1: ${images[0].alt}`}
						>
							<img
								src={`${assetPrefix}${images[0].thumb}`}
								alt={images[0].alt}
								width={images[0].thumbWidth}
								height={images[0].thumbHeight}
								loading="lazy"
								decoding="async"
								className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 ease-out"
							/>
							<div className="absolute inset-0 bg-black/10 group-hover:bg-black/20 transition-colors duration-300"></div>
						</button>

						{/* Photo 2 (Top middle) */}
						<button
							className={`gallery-trigger group relative col-span-1 row-span-1 w-full h-full overflow-hidden outline-none ${theme.ring} focus:ring-2 focus:ring-offset-2 cursor-pointer`}
							data-index="1"
							data-src={`${assetPrefix}${images[1].src}`}
							data-alt={images[1].alt}
							data-caption={images[1].caption}
							aria-label={`Open photo 2: ${images[1].alt}`}
						>
							<img
								src={`${assetPrefix}${images[1].thumb}`}
								alt={images[1].alt}
								width={images[1].thumbWidth}
								height={images[1].thumbHeight}
								loading="lazy"
								decoding="async"
								className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 ease-out"
							/>
							<div className="absolute inset-0 bg-black/10 group-hover:bg-black/20 transition-colors duration-300"></div>
						</button>

						{/* Photo 3 (Bottom middle) */}
						<button
							className={`gallery-trigger group relative col-span-1 row-span-1 w-full h-full overflow-hidden outline-none ${theme.ring} focus:ring-2 focus:ring-offset-2 cursor-pointer`}
							data-index="2"
							data-src={`${assetPrefix}${images[2].src}`}
							data-alt={images[2].alt}
							data-caption={images[2].caption}
							aria-label={`Open photo 3: ${images[2].alt}`}
						>
							<img
								src={`${assetPrefix}${images[2].thumb}`}
								alt={images[2].alt}
								width={images[2].thumbWidth}
								height={images[2].thumbHeight}
								loading="lazy"
								decoding="async"
								className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 ease-out"
							/>
							<div className="absolute inset-0 bg-black/10 group-hover:bg-black/20 transition-colors duration-300"></div>
						</button>

						{/* Photo 4 (Top right) */}
						<button
							className={`gallery-trigger group relative col-span-1 row-span-1 w-full h-full overflow-hidden outline-none ${theme.ring} focus:ring-2 focus:ring-offset-2 cursor-pointer`}
							data-index="3"
							data-src={`${assetPrefix}${images[3].src}`}
							data-alt={images[3].alt}
							data-caption={images[3].caption}
							aria-label={`Open photo 4: ${images[3].alt}`}
						>
							<img
								src={`${assetPrefix}${images[3].thumb}`}
								alt={images[3].alt}
								width={images[3].thumbWidth}
								height={images[3].thumbHeight}
								loading="lazy"
								decoding="async"
								className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 ease-out"
							/>
							<div className="absolute inset-0 bg-black/10 group-hover:bg-black/20 transition-colors duration-300"></div>
						</button>

						{/* Photo 5 (Bottom right) */}
						<button
							className={`gallery-trigger group relative col-span-1 row-span-1 w-full h-full overflow-hidden outline-none ${theme.ring} focus:ring-2 focus:ring-offset-2 cursor-pointer`}
							data-index="4"
							data-src={`${assetPrefix}${images[4].src}`}
							data-alt={images[4].alt}
							data-caption={images[4].caption}
							aria-label={`Open photo 5: ${images[4].alt}`}
						>
							<img
								src={`${assetPrefix}${images[4].thumb}`}
								alt={images[4].alt}
								width={images[4].thumbWidth}
								height={images[4].thumbHeight}
								loading="lazy"
								decoding="async"
								className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 ease-out"
							/>
							<div className="absolute inset-0 bg-black/10 group-hover:bg-black/20 transition-colors duration-300"></div>
						</button>

						{/* Float button: View All Photos */}
						<button
							id="btn-view-all-photos"
							className="absolute bottom-4 right-4 bg-white/95 hover:bg-white text-slate-800 text-xs md:text-sm font-bold py-2.5 px-4 rounded-xl shadow-lg border border-slate-200/60 flex items-center space-x-2 transition-all cursor-pointer z-10 hover:scale-[1.02] active:scale-[0.98] backdrop-blur-md outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2"
							aria-label="Open the full-screen photo gallery slideshow"
						>
							<LayoutGrid className="w-4 h-4 text-slate-700" />
							<span>{t.viewAllPhotos}</span>
						</button>
					</div>

					{/* 2. Mobile Fluid Touch Scroll-Snap Gallery Slider */}
					<div className="flex md:hidden overflow-x-auto snap-x snap-mandatory scrollbar-none gap-3 pb-4 -mx-6 px-6">
						{images.map((img, index) => (
							<button
								key={index}
								className="gallery-trigger relative w-[80vw] h-[260px] shrink-0 snap-center rounded-2xl overflow-hidden shadow-lg border border-slate-100 outline-none focus:ring-2 focus:ring-teal-500"
								data-index={index}
								data-src={`${assetPrefix}${img.src}`}
								data-alt={img.alt}
								data-caption={img.caption}
								aria-label={`Open photo ${index + 1}: ${img.alt}`}
							>
								<img
									src={`${assetPrefix}${img.thumb}`}
									alt={img.alt}
									width={img.thumbWidth}
									height={img.thumbHeight}
									loading="lazy"
									decoding="async"
									className="w-full h-full object-cover"
								/>
								<div className="absolute inset-0 bg-gradient-to-t from-black/50 via-transparent to-transparent"></div>
								<div className="absolute bottom-3.5 left-4 right-4 text-left">
									<p className="text-white text-xs font-semibold drop-shadow line-clamp-1 opacity-95">
										{img.caption}
									</p>
								</div>
								<span className="absolute top-3 right-3 bg-black/60 backdrop-blur-sm text-white text-[10px] font-bold px-2 py-0.5 rounded-full">
									{index + 1} / {images.length}
								</span>
							</button>
						))}
					</div>
				</div>
			</div>

			{/* 3. Fully Accessible Native HTML5 <dialog> Lightbox Modal */}
			{/* closedby="any" handles clicking backdrop to close modal natively in modern browsers */}
			<dialog
				id="lightbox-dialog"
				closedby="any"
				className="fixed inset-0 w-full h-full m-0 max-w-none max-h-none bg-black/98 text-white flex flex-col z-50 overflow-hidden outline-none opacity-0 pointer-events-none transition-all duration-300 backdrop:bg-black/98"
				aria-modal="true"
				role="dialog"
				aria-label="Photo Gallery Lightbox"
			>
				{/* Visually Hidden Screen Reader Announcements (ARIA Live Region) */}
				<div
					id="lightbox-announcer"
					className="sr-only"
					aria-live="polite"
				></div>

				{/* Lightbox Header Controls */}
				<div className="flex items-center justify-between w-full px-6 py-4 md:py-5 border-b border-white/5 bg-black/20 relative z-10">
					<div className="flex flex-col">
						<span
							id="lightbox-counter"
							className="text-xs md:text-sm font-semibold tracking-wider text-slate-400"
						>
							Image -- of --
						</span>
					</div>
					<button
						id="lightbox-close"
						className="w-10 h-10 md:w-11 md:h-11 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-colors cursor-pointer outline-none focus:ring-2 focus:ring-white/50 focus:ring-offset-2 focus:ring-offset-black"
						aria-label={t.closeGallery}
					>
						<X className="w-5 h-5 md:w-6 md:h-6" />
					</button>
				</div>

				{/* Lightbox Main Stage Container */}
				<div className="flex-grow flex items-center justify-between relative px-4 md:px-16 overflow-hidden">
					{/* Navigation: Previous Button (Hidden/Disabled if first image, handled via Javascript) */}
					<button
						id="lightbox-prev"
						className="absolute left-4 md:left-8 w-11 h-11 md:w-14 md:h-11 rounded-xl bg-white/10 hover:bg-white/20 disabled:opacity-20 disabled:cursor-not-allowed text-white flex items-center justify-center transition-all cursor-pointer z-10 outline-none focus:ring-2 focus:ring-white/50"
						aria-label={t.prevPhoto}
					>
						<ChevronLeft className="w-6 h-6 md:w-7 md:h-7" />
					</button>

					{/* Active Image Canvas */}
					<div className="w-full h-full max-h-[70vh] flex items-center justify-center p-2 relative select-none">
						<img
							id="lightbox-active-img"
							src={`${assetPrefix}${images[0].thumb}`}
							alt={images[0].alt}
							width={images[0].thumbWidth}
							height={images[0].thumbHeight}
							loading="lazy"
							decoding="async"
							fetchPriority="low"
							className="max-h-full max-w-full md:max-w-[80vw] object-contain rounded-lg shadow-2xl scale-95 opacity-0 transition-all duration-300 ease-out"
						/>
					</div>

					{/* Navigation: Next Button (Hidden/Disabled if last image, handled via Javascript) */}
					<button
						id="lightbox-next"
						className="absolute right-4 md:right-8 w-11 h-11 md:w-14 md:h-11 rounded-xl bg-white/10 hover:bg-white/20 disabled:opacity-20 disabled:cursor-not-allowed text-white flex items-center justify-center transition-all cursor-pointer z-10 outline-none focus:ring-2 focus:ring-white/50"
						aria-label={t.nextPhoto}
					>
						<ChevronRight className="w-6 h-6 md:w-7 md:h-7" />
					</button>
				</div>

				{/* Lightbox Footer Captions */}
				<div className="w-full px-6 py-6 md:py-8 border-t border-white/5 bg-black/20 text-center relative z-10">
					<p
						id="lightbox-caption"
						className="max-w-2xl mx-auto text-sm md:text-base font-light text-slate-200 tracking-wide drop-shadow-md leading-relaxed"
					>
						--
					</p>
				</div>
			</dialog>
		</section>
	);
};
