import { CheckCircle2, ExternalLink, Quote, Star } from "lucide-react";
import React from "react";
import { PROPERTY_REVIEWS } from "../constants/config";
import { THEMES } from "../constants/theme";
import { dict } from "../i18n/dict";
import type { Lang } from "../types";
import { formatLocalizedIsoDate } from "../utils/date";

export const ReviewsSection1707 = ({ lang }: { lang: Lang }) => {
	const t = dict[lang];
	const theme = THEMES["1707"];
	const review = PROPERTY_REVIEWS["1707"][0];
	const author = t[review.authorKey];

	return (
		<section className="py-24 px-6 bg-slate-50 border-t border-slate-200/60 relative overflow-hidden">
			{/* Decorative background glow */}
			<div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[500px] bg-[#FF5A5F]/5 rounded-full blur-3xl pointer-events-none" />

			<div className="max-w-4xl mx-auto relative z-10">
				<h2 className="text-3xl font-bold mb-12 text-center text-slate-900 tracking-tight">
					{t.reviewsTitle}
				</h2>

				<div className="bg-white/80 backdrop-blur-md p-10 md:p-14 rounded-3xl shadow-xl shadow-slate-100/50 border border-slate-100 flex flex-col items-center text-center relative max-w-3xl mx-auto">
					<Quote
						className="absolute top-8 left-10 w-16 h-16 text-slate-100/80 -scale-x-100 pointer-events-none select-none"
						aria-hidden="true"
					/>

					<div
						className="flex text-amber-400 mb-6 space-x-1"
						aria-hidden="true"
					>
						<Star className="w-6 h-6 fill-current" />
						<Star className="w-6 h-6 fill-current" />
						<Star className="w-6 h-6 fill-current" />
						<Star className="w-6 h-6 fill-current" />
						<Star className="w-6 h-6 fill-current" />
					</div>

					<p className="text-lg md:text-xl text-slate-600 mb-8 font-light italic leading-relaxed relative z-10">
						{t[review.textKey]}
					</p>

					<div className="flex flex-col items-center">
						<div className="w-14 h-14 bg-slate-100 text-slate-700 rounded-full flex items-center justify-center font-bold text-lg mb-3 shadow-inner border border-slate-200">
							{author ? author.charAt(0) : "K"}
						</div>
						<span className="font-bold text-slate-800 tracking-wide text-base">
							{author}
						</span>
						<div className={theme.reviewMetaRow}>
							<span className={theme.reviewVerifiedBadge}>
								<CheckCircle2
									className={theme.reviewIconSm}
									aria-hidden="true"
								/>
								<span>{t.reviewVerifiedGuest}</span>
							</span>
							<time
								dateTime={review.datePublished}
								className={theme.reviewDate}
							>
								{formatLocalizedIsoDate(review.datePublished, lang)}
							</time>
						</div>
					</div>
				</div>

				<div className={theme.reviewAttributionWrapper}>
					<a
						href={theme.airbnbUrl}
						target="_blank"
						rel="noopener noreferrer"
						className={theme.reviewAttributionLink}
					>
						<span>{t.reviewViewOnAirbnb}</span>
						<ExternalLink className={theme.reviewIconSm} aria-hidden="true" />
					</a>
				</div>
			</div>
		</section>
	);
};
