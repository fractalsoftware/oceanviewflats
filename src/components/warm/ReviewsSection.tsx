import { CheckCircle2, ExternalLink, Quote, Star } from "lucide-react";
import React from "react";
import { PROPERTY_REVIEWS } from "../../constants/config";
import { THEMES } from "../../constants/theme";
import { dict } from "../../i18n/dict";
import type { Lang } from "../../types";
import { formatLocalizedIsoDate } from "../../utils/date";

export const ReviewsSection = ({ lang }: { lang: Lang }) => {
	const t = dict[lang];
	const theme = THEMES["1606"];
	const reviews = PROPERTY_REVIEWS["1606"];

	return (
		<section className="py-24 px-6 bg-stone-50 border-t border-stone-200">
			<div className="max-w-6xl mx-auto">
				<h2 className="text-3xl font-bold mb-12 text-center text-stone-900">
					{t.reviewsTitle}
				</h2>
				<div className="grid md:grid-cols-2 gap-8">
					{reviews.map((review) => {
						const author = t[review.authorKey];
						return (
							<div
								key={review.id}
								className="bg-white p-8 rounded-2xl shadow-sm border border-stone-100 flex flex-col relative"
							>
								<Quote
									className="absolute top-6 right-6 w-8 h-8 text-stone-100"
									aria-hidden="true"
								/>
								<div className="flex text-amber-400 mb-4" aria-hidden="true">
									<Star className="w-5 h-5 fill-current" />
									<Star className="w-5 h-5 fill-current" />
									<Star className="w-5 h-5 fill-current" />
									<Star className="w-5 h-5 fill-current" />
									<Star className="w-5 h-5 fill-current" />
								</div>
								<p className="text-stone-600 mb-6 flex-grow italic leading-relaxed">
									{t[review.textKey]}
								</p>
								<div className="flex items-center space-x-3">
									<div className="w-10 h-10 bg-rose-100 rounded-full flex items-center justify-center text-rose-600 font-bold shrink-0">
										{author.charAt(0)}
									</div>
									<div>
										<span className="font-semibold text-stone-900 block">
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
							</div>
						);
					})}
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
