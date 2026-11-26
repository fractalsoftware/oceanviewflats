import type { PropertyReviewMeta } from "../constants/config";

export const MIN_REVIEW_RATING = 1;
export const MAX_REVIEW_RATING = 5;

export const clampReviewRating = (ratingValue: number): number => {
	if (!Number.isFinite(ratingValue)) {
		return MIN_REVIEW_RATING;
	}

	return Math.min(
		MAX_REVIEW_RATING,
		Math.max(MIN_REVIEW_RATING, Math.round(ratingValue)),
	);
};

export const calculateAggregateRatingValue = (
	reviews: readonly Pick<PropertyReviewMeta, "ratingValue">[],
): string | null => {
	const validRatings = reviews
		.map((review) => review.ratingValue)
		.filter((value) => Number.isFinite(value))
		.map((value) =>
			Math.min(MAX_REVIEW_RATING, Math.max(MIN_REVIEW_RATING, value)),
		);

	if (validRatings.length === 0) {
		return null;
	}

	const average =
		validRatings.reduce((sum, rating) => sum + rating, 0) / validRatings.length;
	const rounded = Math.round((average + Number.EPSILON) * 10) / 10;

	return rounded.toFixed(1);
};
