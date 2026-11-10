import { Clock, Copy, ExternalLink, Home } from "lucide-react";
import React from "react";
import { Footer } from "../components/Footer";
import { Navigation } from "../components/Navigation";
import { getHomePagePath, IMAGES } from "../constants/config";
import { BOOKING_PENDING_THEME } from "../constants/theme";
import { dict } from "../i18n/dict";
import type { AppProps } from "../types";

export default function BookingPending({
	lang,
	assetPrefix = "../",
}: AppProps) {
	const t = dict[lang];
	const homeUrl = getHomePagePath(lang, assetPrefix);

	return (
		<div className={BOOKING_PENDING_THEME.pageWrapper}>
			<Navigation
				isScrolled={false}
				lang={lang}
				showBookButton={false}
				assetPrefix={assetPrefix}
			/>

			<main className={BOOKING_PENDING_THEME.main}>
				{/* Ambient Background */}
				<div className={BOOKING_PENDING_THEME.bgWrapper}>
					<img
						src={`${assetPrefix}${IMAGES.home.hero}`}
						alt={t.heroImgAlt}
						className={BOOKING_PENDING_THEME.bgImage}
					/>
					<div className={BOOKING_PENDING_THEME.bgOverlay}></div>
				</div>

				{/* Card */}
				<div className={BOOKING_PENDING_THEME.cardWrapper}>
					<div className={BOOKING_PENDING_THEME.card}>
						{/* Animated Clock/Pending Badge */}
						<div className={BOOKING_PENDING_THEME.badgeWrapper}>
							<Clock className={BOOKING_PENDING_THEME.badgeIcon} />
						</div>

						<h1 className={BOOKING_PENDING_THEME.heading}>
							{t.dbPendingHeader}
						</h1>

						<p className={BOOKING_PENDING_THEME.subtitle}>
							{t.dbPendingSubtitle}
						</p>

						{/* Display Booking Reference Code (un-hidden by client JS when code is resolved) */}
						<div
							id="booking-reference-box"
							className={BOOKING_PENDING_THEME.referenceBox}
						>
							<span className={BOOKING_PENDING_THEME.referenceLabel}>
								{t.dbReferenceCodeLabel}
							</span>
							<div className={BOOKING_PENDING_THEME.referenceRow}>
								<span
									id="display-reservation-code"
									className={BOOKING_PENDING_THEME.referenceCode}
								></span>
								<button
									id="btn-copy-reservation-code"
									type="button"
									className={BOOKING_PENDING_THEME.copyBtn}
									aria-label={t.dbReferenceCodeLabel}
								>
									<Copy className={BOOKING_PENDING_THEME.copyIcon} />
								</button>
								<span
									id="copy-code-feedback"
									className={BOOKING_PENDING_THEME.copyFeedback}
								>
									{t.guideCopySuccess}
								</span>
							</div>
						</div>

						{/* Cash Voucher Details Box (un-hidden by client JS when barcode/verif_code/voucher_url are present) */}
						<div
							id="pending-voucher-box"
							className={BOOKING_PENDING_THEME.voucherBox}
						>
							<h2 className={BOOKING_PENDING_THEME.voucherTitle}>
								{t.dbPendingVoucherTitle}
							</h2>
							<div className={BOOKING_PENDING_THEME.voucherGrid}>
								<div className={BOOKING_PENDING_THEME.voucherItem}>
									<span className={BOOKING_PENDING_THEME.voucherItemLabel}>
										{t.dbPendingBarcodeLabel}
									</span>
									<span
										id="display-voucher-barcode"
										className={BOOKING_PENDING_THEME.voucherItemValue}
									></span>
								</div>
								<div className={BOOKING_PENDING_THEME.voucherItem}>
									<span className={BOOKING_PENDING_THEME.voucherItemLabel}>
										{t.dbPendingVerifCodeLabel}
									</span>
									<span
										id="display-voucher-verif-code"
										className={BOOKING_PENDING_THEME.voucherItemValue}
									></span>
								</div>
							</div>
							<a
								id="btn-print-voucher"
								href="#"
								target="_blank"
								rel="noopener noreferrer"
								className={BOOKING_PENDING_THEME.printVoucherBtn}
							>
								<ExternalLink
									className={BOOKING_PENDING_THEME.printVoucherIcon}
								/>
								<span>{t.dbPendingPrintVoucherBtn}</span>
							</a>
						</div>

						{/* Actions */}
						<div className={BOOKING_PENDING_THEME.actionsWrapper}>
							<a
								id="btn-pending-home"
								href={homeUrl}
								className={BOOKING_PENDING_THEME.homeBtn}
							>
								<Home className={BOOKING_PENDING_THEME.homeIcon} />
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
