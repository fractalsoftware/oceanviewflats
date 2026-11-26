// Guest Welcome Guide Dynamic Interactive Logic & Secure Server-Gated Credential Island
document.addEventListener("DOMContentLoaded", () => {
	// 1. Core DOM Elements
	const greetingBox = document.getElementById("guide-greeting-box");
	const displayApartment = document.getElementById("display-apartment");
	const displayCheckIn = document.getElementById("display-check-in");
	const displayCheckOut = document.getElementById("display-check-out");
	const displayDoorCode = document.getElementById("display-door-code");
	const displayWifiSSID = document.getElementById("display-wifi-ssid");
	const displayWifiPassword = document.getElementById("display-wifi-password");
	const displayParkingSpot = document.getElementById("display-parking-spot");
	const btnCopyDoorCode = document.getElementById("btn-copy-door-code");
	const btnCopyWifiSSID = document.getElementById("btn-copy-wifi-ssid");
	const btnCopyWifiPass = document.getElementById("btn-copy-wifi-pass");
	const btnCopyParkingSpot = document.getElementById("btn-copy-parking-spot");
	const registryBanner = document.getElementById("guide-registry-banner");
	const registryLink = document.getElementById("registry-link");
	const credentialLockedNotice = document.getElementById(
		"credential-locked-notice",
	);
	const credentialLockedTitle = document.getElementById(
		"credential-locked-title",
	);
	const credentialLockedDesc = document.getElementById(
		"credential-locked-desc",
	);
	const credentialLockedBtn = document.getElementById("credential-locked-btn");
	const wifiLockedNotice = document.getElementById("wifi-locked-notice");
	const parkingLockedNotice = document.getElementById("parking-locked-notice");
	const parkingCard = document.getElementById("parking-card");
	const copyAlert = document.getElementById("copy-alert");
	const copyAlertText = document.getElementById("copy-alert-text");
	const doorCodeCard = document.getElementById("door-code-card");

	// 2. Language & Localized Strings from Page Config or DOM Data Attributes
	const pageConfig = (window.getPageConfig && window.getPageConfig()) || {};
	const lang = pageConfig.lang || document.documentElement.lang || "en";
	const assetPrefix = pageConfig.assetPrefix || "../";
	const apiBase = pageConfig.apiBase || `${assetPrefix}api/`;
	const t = (key, params, fallback) =>
		window.t
			? window.t(key, params, fallback)
			: fallback !== undefined
				? fallback
				: key;

	const msgLocked = t(
		"guideCredentialsLocked",
		{},
		"Access Locked (Registry Required)",
	);
	const msgLockedDesc = t(
		"guideCredentialsLockedDesc",
		{},
		"Per building security and Colombian regulations, door codes and Wi-Fi credentials are only released after submitting the Guest Registry.",
	);
	const msgActionUnlock = t(
		"guideActionUnlock",
		{},
		"Complete Registry to Unlock",
	);
	const msgVerifying = t(
		"guideCredentialsVerifying",
		{},
		"Verifying access permissions...",
	);
	const msgNotFound = t(
		"guideNoReservationFound",
		{},
		"Please provide a valid reservation code or link from your confirmation email.",
	);
	const msgCopied = t("guideCopySuccess", {}, "Copied!");
	const msgConcluded = t(
		"guideConcluded",
		{},
		"This reservation has concluded. Property access credentials and guide details are no longer active.",
	);
	const msgConcludedBadge = t("guideConcludedBadge", {}, "Stay Concluded");
	const msgParkingPlaceholder = t("guideParkingPlaceholder", {}, "--");

	function getTodayCotDateString() {
		try {
			const formatter = new Intl.DateTimeFormat("en-CA", {
				timeZone: "America/Bogota",
				year: "numeric",
				month: "2-digit",
				day: "2-digit",
			});
			return formatter.format(new Date());
		} catch (_) {
			return new Date().toISOString().slice(0, 10);
		}
	}

	// 3. Read Query Parameters (Strictly reservation identifiers, no sensitive plaintext passwords)
	const urlParams = new URLSearchParams(window.location.search);
	const reservationCode = (
		urlParams.get("code") ||
		urlParams.get("token") ||
		""
	).trim();
	const rawPropertyParam = (
		urlParams.get("property") ||
		urlParams.get("prop") ||
		urlParams.get("apt") ||
		""
	).trim();
	const hasPropertyContext = Boolean(rawPropertyParam || reservationCode);
	const propertyNumber =
		(rawPropertyParam || "1606").replace(/\D/g, "") || "1606";
	const rawGuestName = urlParams.get("guest") || urlParams.get("name") || "";
	const rawCheckIn =
		urlParams.get("check_in") || urlParams.get("checkin") || "";
	const rawCheckOut =
		urlParams.get("check_out") || urlParams.get("checkout") || "";

	// Relative asset path calculation for endpoints and cross-links (guide views are located in /guide/)
	const registryPageName =
		lang === "en" ? "registry/" : `registry/${lang}.html`;

	// Safely sanitize URLs before assigning to href (blocks javascript:, data:, and protocol-relative // URLs)
	function sanitizeUrl(url) {
		if (!url || typeof url !== "string") return "#";
		const trimmed = url.trim();
		if (
			(trimmed.startsWith("/") &&
				!trimmed.startsWith("//") &&
				!trimmed.startsWith("/\\")) ||
			trimmed.startsWith("./") ||
			trimmed.startsWith("../")
		) {
			return trimmed;
		}
		try {
			const parsed = new URL(trimmed, window.location.origin);
			if (parsed.protocol === "http:" || parsed.protocol === "https:") {
				return parsed.href;
			}
		} catch (_) {
			return "#";
		}
		return "#";
	}

	// Construct default prefilled registry link
	function buildRegistryUrl(codeOverride) {
		const regParams = new URLSearchParams();
		regParams.set("property", propertyNumber);
		if (codeOverride || reservationCode) {
			regParams.set("code", codeOverride || reservationCode);
		}
		if (rawCheckIn) {
			regParams.set("check_in", rawCheckIn);
		}
		if (rawCheckOut) {
			regParams.set("check_out", rawCheckOut);
		}
		return `${assetPrefix}${registryPageName}?${regParams.toString()}`;
	}

	const defaultRegistryUrl = buildRegistryUrl();

	// 4. Helper Functions for UI State

	function setInitialDisplayDetails(name, checkIn, checkOut, prop) {
		if (displayApartment) {
			displayApartment.textContent = `OceanViewFlats ${prop || propertyNumber}`;
		}
		if (displayCheckIn && checkIn) {
			displayCheckIn.textContent = checkIn;
		}
		if (displayCheckOut && checkOut) {
			displayCheckOut.textContent = checkOut;
		}
		if (greetingBox) {
			if (name) {
				greetingBox.textContent = t("guideWelcomeWithGuest", {
					guestName: name,
				});
			} else {
				greetingBox.textContent = t("guideWelcomeGeneric");
			}
		}
	}

	function setLockedState(targetRegistryUrl, reasonMsg) {
		if (displayDoorCode) {
			displayDoorCode.textContent = "••••••";
		}
		if (displayWifiSSID) {
			displayWifiSSID.textContent = "••••••";
		}
		if (displayWifiPassword) {
			displayWifiPassword.textContent = "••••••";
		}
		if (displayParkingSpot) {
			displayParkingSpot.textContent = hasPropertyContext
				? "••••••"
				: msgParkingPlaceholder;
		}

		// Disable copy actions
		[
			btnCopyDoorCode,
			btnCopyWifiSSID,
			btnCopyWifiPass,
			btnCopyParkingSpot,
		].forEach((btn) => {
			if (btn) {
				btn.disabled = true;
				btn.setAttribute("aria-disabled", "true");
				btn.classList.add("opacity-40", "cursor-not-allowed");
			}
		});

		// Show locked notices
		if (credentialLockedNotice) {
			credentialLockedNotice.classList.remove("hidden");
		}
		if (wifiLockedNotice) {
			wifiLockedNotice.classList.remove("hidden");
		}
		if (parkingLockedNotice) {
			if (hasPropertyContext) {
				parkingLockedNotice.classList.remove("hidden");
			} else {
				parkingLockedNotice.classList.add("hidden");
			}
		}
		if (credentialLockedDesc && reasonMsg) {
			credentialLockedDesc.textContent = reasonMsg;
		}

		const effectiveRegistryUrl = sanitizeUrl(
			targetRegistryUrl || defaultRegistryUrl,
		);
		if (registryLink) {
			registryLink.href = effectiveRegistryUrl;
		}
		if (credentialLockedBtn) {
			credentialLockedBtn.href = effectiveRegistryUrl;
		}
		if (registryBanner) {
			registryBanner.style.display = "";
		}
	}

	function setUnlockedState(credentials, reservation) {
		if (displayDoorCode && credentials && credentials.door_code) {
			const doorCode = credentials.door_code;
			displayDoorCode.textContent = doorCode.endsWith("#")
				? doorCode
				: `${doorCode}#`;
		}
		if (displayWifiSSID && credentials && credentials.wifi_ssid) {
			displayWifiSSID.textContent = credentials.wifi_ssid;
		}
		if (displayWifiPassword && credentials && credentials.wifi_password) {
			displayWifiPassword.textContent = credentials.wifi_password;
		}
		if (displayParkingSpot) {
			if (credentials && credentials.parking_spot) {
				const spot = credentials.parking_spot;
				displayParkingSpot.textContent = spot.startsWith("#")
					? spot
					: `#${spot}`;
				if (btnCopyParkingSpot) {
					btnCopyParkingSpot.disabled = false;
					btnCopyParkingSpot.removeAttribute("aria-disabled");
					btnCopyParkingSpot.classList.remove(
						"opacity-40",
						"cursor-not-allowed",
					);
				}
			} else {
				displayParkingSpot.textContent = msgParkingPlaceholder;
				if (btnCopyParkingSpot) {
					btnCopyParkingSpot.disabled = true;
					btnCopyParkingSpot.setAttribute("aria-disabled", "true");
					btnCopyParkingSpot.classList.add("opacity-40", "cursor-not-allowed");
				}
			}
		}

		// Enable copy actions
		[btnCopyDoorCode, btnCopyWifiSSID, btnCopyWifiPass].forEach((btn) => {
			if (btn) {
				btn.disabled = false;
				btn.removeAttribute("aria-disabled");
				btn.classList.remove("opacity-40", "cursor-not-allowed");
			}
		});

		// Hide locked notices
		if (credentialLockedNotice) {
			credentialLockedNotice.classList.add("hidden");
		}
		if (wifiLockedNotice) {
			wifiLockedNotice.classList.add("hidden");
		}
		if (parkingLockedNotice) {
			parkingLockedNotice.classList.add("hidden");
		}

		// Hide registry callout banner since guest registry is already completed
		if (registryBanner) {
			registryBanner.style.display = "none";
		}

		// Update reservation details if returned
		if (reservation) {
			setInitialDisplayDetails(
				reservation.guest_name,
				reservation.check_in,
				reservation.check_out,
				reservation.property_id,
			);
		}
	}

	function displayConcludedState(message) {
		const doorCard = doorCodeCard;
		const concludedBadge = (msgConcludedBadge || "").trim();
		const concludedText = message || msgConcluded;

		// Mask/reset stay dates and placeholders to --
		if (displayCheckIn) displayCheckIn.textContent = "--";
		if (displayCheckOut) displayCheckOut.textContent = "--";
		if (displayApartment) displayApartment.textContent = "OceanViewFlats --";
		if (displayDoorCode) displayDoorCode.textContent = "--";
		if (displayWifiSSID) displayWifiSSID.textContent = "--";
		if (displayWifiPassword) displayWifiPassword.textContent = "--";
		if (displayParkingSpot) displayParkingSpot.textContent = "--";

		// Fall back to generic greeting (suppress prefilling guest name)
		if (greetingBox) {
			greetingBox.textContent = t("guideWelcomeGeneric");
		}

		// Hide mandatory registry banner
		if (registryBanner) {
			registryBanner.style.display = "none";
		}

		// Hide locked notices if open
		if (credentialLockedNotice) credentialLockedNotice.classList.add("hidden");
		if (wifiLockedNotice) wifiLockedNotice.classList.add("hidden");
		if (parkingLockedNotice) parkingLockedNotice.classList.add("hidden");

		// Disable copy triggers/buttons
		[
			btnCopyDoorCode,
			btnCopyWifiSSID,
			btnCopyWifiPass,
			btnCopyParkingSpot,
		].forEach((btn) => {
			if (btn) {
				btn.disabled = true;
				btn.setAttribute("aria-disabled", "true");
				btn.classList.add("opacity-40", "cursor-not-allowed");
			}
		});

		// Render distinct amber/slate concluded notification in place of credentials card via safe DOM nodes
		if (doorCodeCard) {
			doorCodeCard.className =
				"bg-gradient-to-br from-amber-500/10 via-slate-900/5 to-slate-900/10 rounded-3xl p-5 md:p-6 border border-amber-500/20 flex flex-col justify-between text-slate-800 shadow-sm";

			const topGroup = document.createElement("div");
			topGroup.className = "space-y-3";

			const headerRow = document.createElement("div");
			headerRow.className =
				"flex items-center space-x-2 text-amber-800 font-bold text-xs uppercase tracking-wider";

			const iconWrap = document.createElement("span");
			iconWrap.className =
				"p-1.5 bg-amber-500/15 text-amber-700 rounded-lg shrink-0";
			const svgNs = "http://www.w3.org/2000/svg";
			const calSvg = document.createElementNS(svgNs, "svg");
			calSvg.setAttribute("width", "16");
			calSvg.setAttribute("height", "16");
			calSvg.setAttribute("viewBox", "0 0 24 24");
			calSvg.setAttribute("fill", "none");
			calSvg.setAttribute("stroke", "currentColor");
			calSvg.setAttribute("stroke-width", "2");
			calSvg.setAttribute("stroke-linecap", "round");
			calSvg.setAttribute("stroke-linejoin", "round");
			const rect = document.createElementNS(svgNs, "rect");
			rect.setAttribute("width", "18");
			rect.setAttribute("height", "18");
			rect.setAttribute("x", "3");
			rect.setAttribute("y", "4");
			rect.setAttribute("rx", "2");
			rect.setAttribute("ry", "2");
			calSvg.appendChild(rect);
			for (const [x1, x2, y1, y2] of [
				["16", "16", "2", "6"],
				["8", "8", "2", "6"],
				["3", "21", "10", "10"],
			]) {
				const line = document.createElementNS(svgNs, "line");
				line.setAttribute("x1", x1);
				line.setAttribute("x2", x2);
				line.setAttribute("y1", y1);
				line.setAttribute("y2", y2);
				calSvg.appendChild(line);
			}
			iconWrap.appendChild(calSvg);

			const badgeEl = document.createElement("span");
			badgeEl.id = "concluded-badge-text";
			badgeEl.textContent = concludedBadge || concludedText;
			headerRow.appendChild(iconWrap);
			headerRow.appendChild(badgeEl);

			const bodyCard = document.createElement("div");
			bodyCard.className =
				"p-4 bg-slate-900 text-slate-100 rounded-2xl border border-slate-800";
			const bodyEl = document.createElement("p");
			bodyEl.id = "concluded-body-text";
			bodyEl.className = "text-xs leading-relaxed text-slate-300 font-medium";
			bodyEl.textContent = concludedText;
			bodyCard.appendChild(bodyEl);

			topGroup.appendChild(headerRow);
			topGroup.appendChild(bodyCard);

			const footerRow = document.createElement("div");
			footerRow.className =
				"mt-4 pt-3 border-t border-amber-500/15 text-[11px] text-slate-500 font-semibold flex items-center justify-between";
			const brandSpan = document.createElement("span");
			brandSpan.textContent = "OceanViewFlats";
			const footerBadgeEl = document.createElement("span");
			footerBadgeEl.id = "concluded-footer-badge";
			footerBadgeEl.className = "text-amber-700/80";
			if (concludedBadge) footerBadgeEl.textContent = `• ${concludedBadge}`;
			footerRow.appendChild(brandSpan);
			footerRow.appendChild(footerBadgeEl);

			doorCodeCard.replaceChildren(topGroup, footerRow);
		}
	}

	// 5. Check if URL check-out parameter indicates a concluded reservation in COT (America/Bogota)
	const todayCot = getTodayCotDateString();
	const isUrlCheckOutConcluded = Boolean(
		rawCheckOut &&
			/^\d{4}-\d{2}-\d{2}$/.test(rawCheckOut) &&
			rawCheckOut < todayCot,
	);

	if (isUrlCheckOutConcluded) {
		// Defense-in-depth: Suppress prefilling guest name into greeting, mask stay dates, hide registry banner, disable copy triggers, and render concluded notification
		displayConcludedState(msgConcluded);
		return;
	}

	// Populate initial display from URL parameters
	setInitialDisplayDetails(
		rawGuestName,
		rawCheckIn,
		rawCheckOut,
		propertyNumber,
	);

	// Initial state: locked by default until verified
	setLockedState(
		defaultRegistryUrl,
		reservationCode ? msgVerifying : msgNotFound,
	);

	// 6. Asynchronous Verification against Server-Gated Endpoint (ADR 0001)
	if (!reservationCode) {
		// No reservation code provided: access remains locked
		setLockedState(defaultRegistryUrl, msgNotFound);
	} else {
		const apiUrl = `${apiBase}guide-access.php?code=${encodeURIComponent(reservationCode)}&lang=${encodeURIComponent(lang)}`;

		fetch(apiUrl, {
			method: "GET",
			headers: {
				"X-Requested-With": "XMLHttpRequest",
				Accept: "application/json",
			},
		})
			.then((response) => {
				return response.json().then((data) => ({
					status: response.status,
					data: data,
				}));
			})
			.then(({ status, data }) => {
				if (status === 403 && data.status === "concluded") {
					displayConcludedState(data.message || msgConcluded);
				} else if (status === 200 && data.verified && data.credentials) {
					// Access granted: Registry verified and reservation confirmed
					setUnlockedState(data.credentials, data.reservation);
				} else if (data.status === "registry_required") {
					// Reservation valid and confirmed, but Guest Registry not yet submitted
					if (data.reservation) {
						setInitialDisplayDetails(
							data.reservation.guest_name,
							data.reservation.check_in,
							data.reservation.check_out,
							data.reservation.property_id,
						);
					}
					let dynamicRegUrl = defaultRegistryUrl;
					if (data.registry_url) {
						if (
							data.registry_url.startsWith("http://") ||
							data.registry_url.startsWith("https://")
						) {
							dynamicRegUrl = data.registry_url;
						} else {
							dynamicRegUrl = `${assetPrefix}${data.registry_url.replace(/^\//, "")}`;
						}
					}
					setLockedState(dynamicRegUrl, data.message || msgLockedDesc);
				} else {
					// Unauthorized / cancelled / not found
					setLockedState(defaultRegistryUrl, data.message || msgNotFound);
				}
			})
			.catch((err) => {
				console.error("Error verifying guide access:", err);
				// On network failure, retain safe locked state
				setLockedState(defaultRegistryUrl, msgLockedDesc);
			});
	}

	// 7. Clipboard Copy Functionality
	const copyButtons = [
		btnCopyDoorCode,
		btnCopyWifiSSID,
		btnCopyWifiPass,
		btnCopyParkingSpot,
	];

	copyButtons.forEach((btn) => {
		if (!btn) return;
		btn.addEventListener("click", (e) => {
			e.preventDefault();
			if (btn.disabled || btn.classList.contains("cursor-not-allowed")) {
				return;
			}

			const targetId = btn.getAttribute("data-copy-target");
			const targetEl = document.getElementById(targetId);
			if (!targetEl) return;

			const textToCopy = targetEl.textContent.trim();
			if (
				textToCopy === "--" ||
				textToCopy === "••••••" ||
				textToCopy === msgParkingPlaceholder
			) {
				return;
			}

			navigator.clipboard
				.writeText(textToCopy)
				.then(() => {
					if (copyAlert) {
						copyAlertText.textContent = msgCopied;
						copyAlert.classList.remove("opacity-0", "translate-y-24");
						copyAlert.classList.add("opacity-100", "translate-y-0");

						const originalNodes = Array.from(btn.childNodes).map((n) =>
							n.cloneNode(true),
						);
						const checkSvg = document.createElementNS(
							"http://www.w3.org/2000/svg",
							"svg",
						);
						checkSvg.setAttribute("width", "14");
						checkSvg.setAttribute("height", "14");
						checkSvg.setAttribute("viewBox", "0 0 24 24");
						checkSvg.setAttribute("fill", "none");
						checkSvg.setAttribute("stroke", "currentColor");
						checkSvg.setAttribute("stroke-width", "3");
						checkSvg.setAttribute("stroke-linecap", "round");
						checkSvg.setAttribute("stroke-linejoin", "round");
						checkSvg.setAttribute("class", "text-emerald-400");
						const poly = document.createElementNS(
							"http://www.w3.org/2000/svg",
							"polyline",
						);
						poly.setAttribute("points", "20 6 9 17 4 12");
						checkSvg.appendChild(poly);
						btn.replaceChildren(checkSvg);

						setTimeout(() => {
							copyAlert.classList.remove("opacity-100", "translate-y-0");
							copyAlert.classList.add("opacity-0", "translate-y-24");
							btn.replaceChildren(...originalNodes);
						}, 2000);
					}
				})
				.catch((err) => {
					console.error("Failed to copy text: ", err);
				});
		});
	});
});
