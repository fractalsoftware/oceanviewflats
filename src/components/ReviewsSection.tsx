import { CheckCircle2, ExternalLink, Quote, Star } from "lucide-react";
import React from "react";
import { PROPERTY_REVIEWS, type PropertyId } from "../constants/config";
import { THEMES } from "../constants/theme";
import { dict } from "../i18n/dict";
import type { Lang } from "../types";
import { formatLocalizedIsoDate } from "../utils/date";
import { clampReviewRating } from "../utils/reviews";

export const ReviewsSection = ({
	lang,
	propertyId,
}: {
	lang: Lang;
	propertyId: PropertyId;
}) => {
	const t = dict[lang];
	const theme = THEMES[propertyId];
	const reviews = PROPERTY_REVIEWS[propertyId];

	return (
		<section
			id="guest-reviews"
			aria-labelledby="guest-reviews-heading"
			className={theme.reviewSection}
		>
			<div className={theme.reviewGlow} aria-hidden="true" />

			<div className={theme.reviewContainer}>
				<h2 id="guest-reviews-heading" className={theme.reviewTitle}>
					{t.reviewsTitle}
				</h2>

				<ol role="list" className={theme.reviewGrid}>
					{reviews.map((review) => {
						const author = t[review.authorKey];
						const starCount = clampReviewRating(review.ratingValue);

						return (
							<li key={review.id} className={theme.reviewListItem}>
								<article id={review.id} className={theme.reviewCard}>
									<Quote className={theme.reviewQuoteIcon} aria-hidden="true" />
									<div className={theme.reviewStarsRow} aria-hidden="true">
										{Array.from({ length: starCount }, (_, index) => (
											<Star
												key={`${review.id}-star-${index}`}
												className={theme.reviewStarIcon}
												aria-hidden="true"
											/>
										))}
									</div>
									<p className={theme.reviewText}>{t[review.textKey]}</p>
									<div className={theme.reviewAuthorRow}>
										<div className={theme.reviewAvatar} aria-hidden="true">
											{author.charAt(0)}
										</div>
										<div className={theme.reviewAuthorDetails}>
											<span className={theme.reviewAuthorName}>{author}</span>
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
								</article>
							</li>
						);
					})}
				</ol>

				<div className={theme.reviewAttributionWrapper}>
					<a
						href={theme.airbnbUrl}
						target="_blank"
						rel="noopener noreferrer"
						data-analytics-airbnb-review="property_reviews"
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
