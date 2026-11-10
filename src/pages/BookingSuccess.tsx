import {
	ArrowRight,
	BookOpen,
	CheckCircle,
	Copy,
	FileText,
	Home,
	ShieldCheck,
} from "lucide-react";
import React from "react";
import { Footer } from "../components/Footer";
import { Navigation } from "../components/Navigation";
import {
	getGuidePagePath,
	getHomePagePath,
	getRegistryPagePath,
	IMAGES,
} from "../constants/config";
import { BOOKING_SUCCESS_THEME } from "../constants/theme";
import { dict } from "../i18n/dict";
import type { AppProps } from "../types";

export default function BookingSuccess({
	lang,
	assetPrefix = "../",
}: AppProps) {
	const t = dict[lang];
	const registryUrl = getRegistryPagePath(lang, assetPrefix);
	const guideUrl = getGuidePagePath(lang, assetPrefix);
	const homeUrl = getHomePagePath(lang, assetPrefix);

	return (
		<div className={BOOKING_SUCCESS_THEME.pageWrapper}>
			<Navigation
				isScrolled={false}
				lang={lang}
				showBookButton={false}
				assetPrefix={assetPrefix}
			/>

			<main className={BOOKING_SUCCESS_THEME.main}>
				{/* Ambient Background */}
				<div className={BOOKING_SUCCESS_THEME.bgWrapper}>
					<img
						src={`${assetPrefix}${IMAGES.home.hero}`}
						alt={t.heroImgAlt}
						className={BOOKING_SUCCESS_THEME.bgImage}
					/>
					<div className={BOOKING_SUCCESS_THEME.bgOverlay}></div>
				</div>

				{/* Card */}
				<div className={BOOKING_SUCCESS_THEME.cardWrapper}>
					<div className={BOOKING_SUCCESS_THEME.card}>
						{/* Animated Success Badge */}
						<div className={BOOKING_SUCCESS_THEME.badgeWrapper}>
							<CheckCircle className={BOOKING_SUCCESS_THEME.badgeIcon} />
						</div>

						<h1 className={BOOKING_SUCCESS_THEME.heading}>
							{t.dbSuccessHeader}
						</h1>

						<p className={BOOKING_SUCCESS_THEME.subtitle}>
							{t.dbSuccessSubtitle}
						</p>

						{/* Display Booking Reference Code (un-hidden by client JS when code is resolved) */}
						<div
							id="booking-reference-box"
							className={BOOKING_SUCCESS_THEME.referenceBox}
						>
							<span className={BOOKING_SUCCESS_THEME.referenceLabel}>
								{t.dbReferenceCodeLabel}
							</span>
							<div className={BOOKING_SUCCESS_THEME.referenceRow}>
								<span
									id="display-reservation-code"
									className={BOOKING_SUCCESS_THEME.referenceCode}
								></span>
								<button
									id="btn-copy-reservation-code"
									type="button"
									className={BOOKING_SUCCESS_THEME.copyBtn}
									aria-label={t.dbReferenceCodeLabel}
								>
									<Copy className={BOOKING_SUCCESS_THEME.copyIcon} />
								</button>
								<span
									id="copy-code-feedback"
									className={BOOKING_SUCCESS_THEME.copyFeedback}
								>
									{t.guideCopySuccess}
								</span>
							</div>
						</div>

						{/* Mandatory Guest Registry Notice (ADR 0001 / Law 1581) */}
						<div
							id="booking-registry-notice"
							className={BOOKING_SUCCESS_THEME.registryNoticeBox}
						>
							<ShieldCheck
								className={BOOKING_SUCCESS_THEME.registryNoticeIcon}
							/>
							<p className={BOOKING_SUCCESS_THEME.registryNoticeText}>
								{t.dbSuccessRegistryNotice}
							</p>
						</div>

						{/* Actions */}
						<div className={BOOKING_SUCCESS_THEME.actionsWrapper}>
							<a
								id="btn-booking-registry"
								href={registryUrl}
								className={BOOKING_SUCCESS_THEME.primaryBtn}
							>
								<FileText className={BOOKING_SUCCESS_THEME.btnIcon} />
								<span>{t.dbSuccessRegistryBtn}</span>
								<ArrowRight className={BOOKING_SUCCESS_THEME.btnArrowIcon} />
							</a>

							<div className={BOOKING_SUCCESS_THEME.secondaryActionsRow}>
								<a
									id="btn-booking-guide"
									href={guideUrl}
									className={BOOKING_SUCCESS_THEME.secondaryGuideBtn}
								>
									<BookOpen className={BOOKING_SUCCESS_THEME.btnIcon} />
									<span>{t.dbSuccessGuideBtn}</span>
								</a>

								<a
									id="btn-booking-home"
									href={homeUrl}
									className={BOOKING_SUCCESS_THEME.secondaryHomeBtn}
								>
									<Home className={BOOKING_SUCCESS_THEME.btnIcon} />
									<span>{t.dbSuccessHomeBtn}</span>
								</a>
							</div>
						</div>
					</div>
				</div>
			</main>

			<Footer lang={lang} assetPrefix={assetPrefix} />
		</div>
	);
}
