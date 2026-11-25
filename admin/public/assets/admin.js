(() => {
	const SVG_NS = "http://www.w3.org/2000/svg";

	function createSvgElement(attrs, paths) {
		const svg = document.createElementNS(SVG_NS, "svg");
		for (const [key, val] of Object.entries(attrs)) {
			svg.setAttribute(key, val);
		}
		for (const pathAttrs of paths) {
			const path = document.createElementNS(SVG_NS, "path");
			for (const [key, val] of Object.entries(pathAttrs)) {
				path.setAttribute(key, val);
			}
			svg.appendChild(path);
		}
		return svg;
	}

	function copyTextWithFallback(text, onSuccess) {
		if (navigator.clipboard?.writeText) {
			navigator.clipboard
				.writeText(text)
				.then(() => {
					if (typeof onSuccess === "function") onSuccess();
				})
				.catch(() => {
					fallbackCopyText(text, onSuccess);
				});
			return;
		}
		fallbackCopyText(text, onSuccess);
	}

	function fallbackCopyText(text, onSuccess) {
		const textArea = document.createElement("textarea");
		textArea.value = text;
		textArea.style.position = "fixed";
		textArea.style.left = "-9999px";
		document.body.appendChild(textArea);
		textArea.focus();
		textArea.select();
		try {
			document.execCommand("copy");
			if (typeof onSuccess === "function") onSuccess();
		} catch (err) {
			console.error("Unable to copy", err);
		}
		document.body.removeChild(textArea);
	}

	function clearModalContainer() {
		const container = document.getElementById("modal-container");
		if (container) {
			container.replaceChildren();
		}
	}

	function syncMinEndDate(checkInInput, checkOutInput, clearMinWhenEmpty) {
		if (!checkInInput || !checkOutInput) return;
		if (!checkInInput.value) {
			if (clearMinWhenEmpty) {
				checkOutInput.removeAttribute("min");
			}
			return;
		}
		const parts = checkInInput.value.split("-");
		if (parts.length !== 3) return;
		const start = new Date(
			Number.parseInt(parts[0], 10),
			Number.parseInt(parts[1], 10) - 1,
			Number.parseInt(parts[2], 10),
		);
		if (Number.isNaN(start.getTime())) return;

		const minEnd = new Date(start);
		minEnd.setDate(minEnd.getDate() + 1);
		const y = minEnd.getFullYear();
		const m = String(minEnd.getMonth() + 1).padStart(2, "0");
		const d = String(minEnd.getDate()).padStart(2, "0");
		const minEndStr = `${y}-${m}-${d}`;
		checkOutInput.min = minEndStr;

		if (checkOutInput.value && checkOutInput.value <= checkInInput.value) {
			checkOutInput.value = minEndStr;
		}
	}

	function applyFormValidationState(btnId, helperId, unmetRequirement) {
		const btn = document.getElementById(btnId);
		const helper = document.getElementById(helperId);

		if (unmetRequirement) {
			if (btn) {
				btn.disabled = true;
				btn.setAttribute("aria-disabled", "true");
				btn.classList.add("opacity-50", "cursor-not-allowed");
				btn.classList.remove("cursor-pointer");
			}
			if (helper) {
				helper.textContent = unmetRequirement;
				helper.classList.remove("hidden");
			}
			return false;
		}

		if (btn) {
			btn.disabled = false;
			btn.setAttribute("aria-disabled", "false");
			btn.classList.remove("opacity-50", "cursor-not-allowed");
			btn.classList.add("cursor-pointer");
		}
		if (helper) {
			helper.textContent = "";
			helper.classList.add("hidden");
		}
		return true;
	}

	// =========================================================================
	// 1. Drawer, Dispatch, and Clipboard Helpers
	// =========================================================================
	function closeDrawer(basePath) {
		const container = document.getElementById("drawer-container");
		if (container) {
			container.replaceChildren();
		} else {
			window.location.href = basePath;
			return;
		}
		if (window.location.pathname.startsWith(`${basePath}/`)) {
			window.history.pushState(null, "", basePath);
		}
	}

	function copyAuditJson(btnId, targetId) {
		const elem = document.getElementById(targetId);
		if (!elem) return;
		const text = elem.textContent || "";
		copyTextWithFallback(text, () => {
			const btn = document.getElementById(btnId);
			if (!btn) return;
			const orig = btn.textContent || "Copy JSON";
			btn.textContent = "Copied!";
			btn.classList.add("text-emerald-600");
			setTimeout(() => {
				btn.textContent = orig;
				btn.classList.remove("text-emerald-600");
			}, 2000);
		});
	}

	function switchDispatchLang(uid, lang) {
		const ta = document.getElementById(`dispatch-snippet-${uid}`);
		const btnEs = document.getElementById(`btn-lang-es-${uid}`);
		const btnEn = document.getElementById(`btn-lang-en-${uid}`);
		const copyText = document.getElementById(`copy-text-${uid}`);
		if (!ta) return;

		if (lang === "es") {
			ta.value = ta.getAttribute("data-text-es") || "";
			ta.setAttribute("data-current-lang", "es");
			if (btnEs) {
				btnEs.className =
					"px-2.5 py-1 rounded-md transition cursor-pointer bg-white text-gray-900 shadow-2xs font-bold";
			}
			if (btnEn) {
				btnEn.className =
					"px-2.5 py-1 rounded-md transition cursor-pointer text-gray-500 hover:text-gray-900";
			}
			if (copyText) copyText.textContent = "Copy ES Message";
		} else {
			ta.value = ta.getAttribute("data-text-en") || "";
			ta.setAttribute("data-current-lang", "en");
			if (btnEn) {
				btnEn.className =
					"px-2.5 py-1 rounded-md transition cursor-pointer bg-white text-gray-900 shadow-2xs font-bold";
			}
			if (btnEs) {
				btnEs.className =
					"px-2.5 py-1 rounded-md transition cursor-pointer text-gray-500 hover:text-gray-900";
			}
			if (copyText) copyText.textContent = "Copy EN Message";
		}
	}

	function copyDispatchSnippet(uid) {
		const ta = document.getElementById(`dispatch-snippet-${uid}`);
		const copyText = document.getElementById(`copy-text-${uid}`);
		if (!ta) return;

		copyTextWithFallback(ta.value, () => {
			if (!copyText) return;
			const orig = copyText.textContent || "";
			copyText.textContent = "Copied to Clipboard!";
			setTimeout(() => {
				copyText.textContent = orig;
			}, 2000);
		});
	}

	function showInviteCopyFeedback(button) {
		const originalNodes = Array.from(button.childNodes);
		const label = document.createElement("span");
		label.textContent = "✓ Copied!";
		button.replaceChildren(label);
		button.classList.add("bg-emerald-600", "text-white");
		button.classList.remove("bg-indigo-600");
		setTimeout(() => {
			button.replaceChildren(...originalNodes);
			button.classList.remove("bg-emerald-600");
			button.classList.add("bg-indigo-600");
		}, 2000);
	}

	function copyRegistryInvite(button) {
		if (!button) return;
		const inviteUrl = button.getAttribute("data-invite-url") || "";
		const guestName = button.getAttribute("data-guest-name") || "Guest";
		const propId = button.getAttribute("data-prop-id") || "";

		let textToCopy = inviteUrl;
		if (inviteUrl && guestName) {
			textToCopy = `Hello ${guestName},\n\nPlease complete your guest registration for Apartment ${propId} prior to arrival using this secure link:\n${inviteUrl}\n\nThank you!\nOcean View Flats Team`;
		}

		copyTextWithFallback(textToCopy, () => {
			showInviteCopyFeedback(button);
		});
	}

	// =========================================================================
	// 2. Global HTMX 422 Swap Handler & Async Lifecycle Manager
	// =========================================================================
	document.body.addEventListener("htmx:beforeSwap", (evt) => {
		if (evt.detail?.xhr?.status === 422) {
			evt.detail.shouldSwap = true;
			evt.detail.isError = false;
		}
	});

	// Unified HTMX async loading indicator, button mutation locking, and error resiliency
	(() => {
		let activeRequests = 0;
		let debounceTimer = null;
		let trickleTimers = [];
		let resetTimer = null;
		let errorHoldTimer = null;
		let errorResetTimer = null;
		let lockedElements = [];

		function getProgressBar() {
			return document.getElementById("global-progress-bar");
		}

		function getAlertContainer() {
			return document.getElementById("global-alert-container");
		}

		function clearTrickleTimers() {
			for (let i = 0; i < trickleTimers.length; i++) {
				clearTimeout(trickleTimers[i]);
			}
			trickleTimers = [];
		}

		function showProgressBar() {
			const bar = getProgressBar();
			if (!bar || bar.classList.contains("bg-rose-500")) return;

			if (resetTimer) {
				clearTimeout(resetTimer);
				resetTimer = null;
			}
			clearTrickleTimers();

			bar.classList.add("loading");
			bar.classList.remove("opacity-0");
			bar.style.width = "25%";

			// Advance progress smoothly while requests remain active (>150ms)
			trickleTimers.push(
				setTimeout(() => {
					if (activeRequests > 0 && !bar.classList.contains("bg-rose-500")) {
						bar.style.width = "60%";
					}
				}, 250),
			);

			trickleTimers.push(
				setTimeout(() => {
					if (activeRequests > 0 && !bar.classList.contains("bg-rose-500")) {
						bar.style.width = "85%";
					}
				}, 600),
			);
		}

		function finishProgressBar() {
			const bar = getProgressBar();
			if (!bar || bar.classList.contains("bg-rose-500")) return;

			clearTrickleTimers();
			bar.style.width = "100%";

			resetTimer = setTimeout(() => {
				if (activeRequests > 0 || bar.classList.contains("bg-rose-500")) return;
				bar.classList.add("opacity-0");

				resetTimer = setTimeout(() => {
					if (activeRequests > 0 || bar.classList.contains("bg-rose-500")) {
						return;
					}
					bar.style.width = "0%";
					bar.classList.remove("loading");
					resetTimer = null;
				}, 300);
			}, 250);
		}

		function flashErrorProgressBar() {
			const bar = getProgressBar();
			if (!bar) return;

			if (errorHoldTimer) {
				clearTimeout(errorHoldTimer);
				errorHoldTimer = null;
			}
			if (errorResetTimer) {
				clearTimeout(errorResetTimer);
				errorResetTimer = null;
			}

			clearTrickleTimers();
			bar.classList.remove("loading");
			bar.classList.remove("bg-indigo-600");
			bar.classList.add("bg-rose-500");
			bar.classList.remove("opacity-0");
			bar.style.width = "100%";

			// Hold for ~1.2s, then fade out and reset color back to bg-indigo-600 and width to 0%
			errorHoldTimer = setTimeout(() => {
				bar.classList.add("opacity-0");
				errorResetTimer = setTimeout(() => {
					bar.style.width = "0%";
					bar.classList.remove("bg-rose-500");
					bar.classList.add("bg-indigo-600");
					errorHoldTimer = null;
					errorResetTimer = null;
				}, 300);
			}, 1200);
		}

		function showFloatingAlert(customMessage) {
			const container = getAlertContainer();
			if (!container) return;

			const defaultMsg = container.getAttribute("data-msg-error") || "";
			const dismissMsg = container.getAttribute("data-msg-dismiss") || "";
			const messageText = customMessage || defaultMsg;

			const toast = document.createElement("div");
			toast.className =
				"bg-rose-50 border border-rose-200 text-rose-800 text-xs sm:text-sm p-3.5 rounded-lg shadow-lg flex items-center justify-between space-x-3 pointer-events-auto transition-opacity duration-300";

			const content = document.createElement("div");
			content.className = "flex items-center space-x-2.5";

			const errorIcon = createSvgElement(
				{
					class: "w-4 h-4 text-rose-600 flex-shrink-0",
					fill: "currentColor",
					viewBox: "0 0 20 20",
				},
				[
					{
						"fill-rule": "evenodd",
						d: "M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z",
						"clip-rule": "evenodd",
					},
				],
			);
			const messageSpan = document.createElement("span");
			messageSpan.textContent = messageText;
			content.appendChild(errorIcon);
			content.appendChild(messageSpan);

			const closeBtn = document.createElement("button");
			closeBtn.type = "button";
			closeBtn.className =
				"text-rose-500 hover:text-rose-700 p-1 rounded-md focus:outline-none focus:ring-2 focus:ring-rose-400";
			closeBtn.setAttribute("aria-label", dismissMsg);
			closeBtn.appendChild(
				createSvgElement(
					{
						class: "w-4 h-4",
						fill: "none",
						stroke: "currentColor",
						viewBox: "0 0 24 24",
					},
					[
						{
							"stroke-linecap": "round",
							"stroke-linejoin": "round",
							"stroke-width": "2",
							d: "M6 18L18 6M6 6l12 12",
						},
					],
				),
			);

			let dismissTimer = null;
			function dismissToast() {
				if (dismissTimer) {
					clearTimeout(dismissTimer);
					dismissTimer = null;
				}
				toast.classList.add("opacity-0");
				setTimeout(() => {
					if (toast.parentNode) {
						toast.parentNode.removeChild(toast);
					}
				}, 300);
			}

			closeBtn.addEventListener("click", dismissToast);
			toast.appendChild(content);
			toast.appendChild(closeBtn);
			container.appendChild(toast);

			// Automatically fades out and dismisses after 4000ms
			dismissTimer = setTimeout(dismissToast, 4000);
		}

		function lockTarget(el) {
			if (!el) return;
			el.classList.add("htmx-request");
			el.setAttribute("aria-disabled", "true");
			el.setAttribute("data-mutation-locked", "true");
			if (!lockedElements.includes(el)) {
				lockedElements.push(el);
			}
		}

		function unlockTarget(el) {
			if (!el) return;
			el.classList.remove("htmx-request");
			el.removeAttribute("aria-disabled");
			el.removeAttribute("data-mutation-locked");
		}

		function unlockAllButtons() {
			for (const el of lockedElements) {
				unlockTarget(el);
			}
			lockedElements = [];
			const lingering = document.querySelectorAll(
				'[data-mutation-locked="true"], .htmx-request:is(button, [type="submit"], a)',
			);
			for (const el of lingering) {
				unlockTarget(el);
			}
		}

		function isMutatingVerb(verb) {
			if (!verb) return false;
			const v = String(verb).toLowerCase();
			return v === "post" || v === "delete" || v === "put";
		}

		function isMutatingElement(el) {
			if (!el) return false;
			if (
				el.hasAttribute &&
				(el.hasAttribute("hx-post") ||
					el.hasAttribute("hx-delete") ||
					el.hasAttribute("hx-put"))
			) {
				return true;
			}
			if (el.tagName === "FORM") {
				const m = (el.getAttribute("method") || "").toLowerCase();
				return (
					isMutatingVerb(m) ||
					el.hasAttribute("hx-post") ||
					el.hasAttribute("hx-delete") ||
					el.hasAttribute("hx-put")
				);
			}
			if (
				(el.type === "submit" || el.getAttribute("type") === "submit") &&
				el.form
			) {
				return isMutatingElement(el.form);
			}
			return false;
		}

		function isMutatingRequest(detail) {
			if (!detail) return false;
			if (detail.requestConfig && isMutatingVerb(detail.requestConfig.verb)) {
				return true;
			}
			return isMutatingElement(detail.elt);
		}

		function lockMutatingTriggers(elt) {
			if (!elt) return;
			const submitSelector =
				'button[type="submit"], input[type="submit"], button:not([type]), [hx-post], [hx-delete], [hx-put]';
			if (elt.tagName === "FORM") {
				lockTarget(elt);
				for (const btn of elt.querySelectorAll(submitSelector)) {
					lockTarget(btn);
				}
			} else {
				lockTarget(elt);
				if (elt.form) {
					lockTarget(elt.form);
					for (const btn of elt.form.querySelectorAll(submitSelector)) {
						lockTarget(btn);
					}
				}
				if (elt.querySelectorAll) {
					for (const btn of elt.querySelectorAll(submitSelector)) {
						lockTarget(btn);
					}
				}
			}
		}

		function unlockMutatingTriggers(elt) {
			if (!elt) {
				unlockAllButtons();
				return;
			}
			const unlockSelector =
				'[data-mutation-locked="true"], button[type="submit"], input[type="submit"], button:not([type])';
			unlockTarget(elt);
			if (elt.form) {
				unlockTarget(elt.form);
				for (const btn of elt.form.querySelectorAll(unlockSelector)) {
					unlockTarget(btn);
					const sIdx = lockedElements.indexOf(btn);
					if (sIdx !== -1) lockedElements.splice(sIdx, 1);
				}
			}
			if (elt.querySelectorAll) {
				for (const btn of elt.querySelectorAll(unlockSelector)) {
					unlockTarget(btn);
					const idx = lockedElements.indexOf(btn);
					if (idx !== -1) lockedElements.splice(idx, 1);
				}
			}
			const selfIdx = lockedElements.indexOf(elt);
			if (selfIdx !== -1) {
				lockedElements.splice(selfIdx, 1);
			}
		}

		function decrementActiveRequests(evt) {
			if (evt?.detail) {
				if (evt.detail._activeCounted) return false;
				evt.detail._activeCounted = true;
			}
			activeRequests = Math.max(0, activeRequests - 1);
			if (activeRequests === 0) {
				if (debounceTimer) {
					clearTimeout(debounceTimer);
					debounceTimer = null;
				}
				const bar = getProgressBar();
				if (
					bar &&
					(!bar.classList.contains("opacity-0") ||
						Number.parseFloat(bar.style.width || "0") > 0)
				) {
					finishProgressBar();
				}
			}
			return true;
		}

		function handleAsyncError() {
			flashErrorProgressBar();
			showFloatingAlert();
			unlockAllButtons();
		}

		// Intercept click and submit events for 0ms locking before network latency
		document.body.addEventListener("click", (evt) => {
			const btn = evt.target?.closest?.(
				'button, [type="submit"], a[hx-post], a[hx-delete], a[hx-put]',
			);
			if (!btn) return;
			if (
				btn.hasAttribute("hx-confirm") ||
				btn.form?.hasAttribute("hx-confirm")
			) {
				return;
			}
			if (
				btn.form &&
				typeof btn.form.checkValidity === "function" &&
				!btn.form.checkValidity()
			) {
				return;
			}
			if (isMutatingElement(btn)) {
				lockTarget(btn);
			}
		});

		document.body.addEventListener("submit", (evt) => {
			const form = evt.target;
			if (form && form.tagName === "FORM" && isMutatingElement(form)) {
				if (typeof form.checkValidity === "function" && !form.checkValidity()) {
					return;
				}
				lockMutatingTriggers(form);
			}
		});

		document.addEventListener(
			"invalid",
			() => {
				unlockAllButtons();
			},
			true,
		);

		document.body.addEventListener("htmx:beforeRequest", (evt) => {
			activeRequests++;

			if (activeRequests === 1) {
				if (resetTimer) {
					clearTimeout(resetTimer);
					resetTimer = null;
				}
				// 150ms debounce threshold to prevent flashing on fast requests (<150ms)
				debounceTimer = setTimeout(() => {
					if (activeRequests > 0) {
						showProgressBar();
					}
				}, 150);
			}

			if (isMutatingRequest(evt.detail)) {
				lockMutatingTriggers(evt.detail?.elt);
			}
		});

		document.body.addEventListener("htmx:afterRequest", (evt) => {
			decrementActiveRequests(evt);
			unlockMutatingTriggers(evt.detail?.elt);
		});

		document.body.addEventListener("htmx:sendError", (evt) => {
			decrementActiveRequests(evt);
			handleAsyncError();
		});

		document.body.addEventListener("htmx:responseError", (evt) => {
			decrementActiveRequests(evt);
			const status = evt.detail?.xhr ? evt.detail.xhr.status : 0;
			if (status >= 500 || status === 0 || (status >= 400 && status !== 422)) {
				handleAsyncError();
			} else {
				unlockAllButtons();
			}
		});
	})();

	// =========================================================================
	// 3. Mobile Hamburger Navigation Toggle
	// =========================================================================
	(() => {
		const menuBtn = document.getElementById("mobile-menu-button");
		const mobileMenu = document.getElementById("mobile-menu");
		const iconOpen = document.getElementById("mobile-menu-icon-open");
		const iconClose = document.getElementById("mobile-menu-icon-close");
		if (!menuBtn || !mobileMenu) return;

		function setMobileMenuOpen(isOpen) {
			menuBtn.setAttribute("aria-expanded", isOpen ? "true" : "false");
			if (isOpen) {
				mobileMenu.classList.remove("hidden");
				if (iconOpen) iconOpen.classList.add("hidden");
				if (iconClose) iconClose.classList.remove("hidden");
			} else {
				mobileMenu.classList.add("hidden");
				if (iconOpen) iconOpen.classList.remove("hidden");
				if (iconClose) iconClose.classList.add("hidden");
			}
		}

		menuBtn.addEventListener("click", () => {
			const expanded = menuBtn.getAttribute("aria-expanded") === "true";
			setMobileMenuOpen(!expanded);
		});

		document.addEventListener("keydown", (evt) => {
			if (
				evt.key === "Escape" &&
				menuBtn.getAttribute("aria-expanded") === "true"
			) {
				setMobileMenuOpen(false);
			}
		});

		document.addEventListener("click", (evt) => {
			if (menuBtn.getAttribute("aria-expanded") !== "true") return;
			const header = menuBtn.closest("header");
			if (header && !header.contains(evt.target)) {
				setMobileMenuOpen(false);
			}
		});
	})();

	// =========================================================================
	// 4. Declarative Delegated Event Handlers ([data-action])
	// =========================================================================
	// Capture-phase click listener ensures form/filter state mutations execute
	// BEFORE target-phase HTMX listeners serialize and dispatch hx-get requests.
	document.addEventListener(
		"click",
		(evt) => {
			const el = evt.target?.closest?.("[data-action]");
			if (!el) return;

			const action = el.getAttribute("data-action");
			if (action === "set-value") {
				const targetId = el.getAttribute("data-target-id") || "";
				const targetVal = el.getAttribute("data-target-value") ?? "";
				const targetEl = document.getElementById(targetId);
				if (targetEl) {
					targetEl.value = targetVal;
				}
			} else if (action === "reset-financial-filters") {
				const form = el.closest("form");
				if (form) {
					form.reset();
					const defaults = {
						"property-filter-select": "all",
						"period-select": "this_month",
						"compare-select": "prev_period",
						"source-select": "all",
						"category-select": "all",
						"date-from-input": "",
						"date-to-input": "",
						"expense-search-input": "",
						"financial-report-tab-input": "expenses",
					};
					for (const [id, value] of Object.entries(defaults)) {
						const field = document.getElementById(id);
						if (field) field.value = value;
					}
				}
			}
		},
		true,
	);

	document.addEventListener("click", (evt) => {
		const el = evt.target?.closest?.("[data-action]");
		if (!el) return;

		const action = el.getAttribute("data-action");
		switch (action) {
			case "close-modal":
				clearModalContainer();
				break;
			case "close-modal-backdrop":
				if (evt.target === el) {
					clearModalContainer();
				}
				break;
			case "close-reservation-drawer":
				closeDrawer("/reservations");
				break;
			case "close-audit-drawer":
				closeDrawer("/audit-logs");
				break;
			case "dismiss-parent":
				if (el.parentElement) {
					el.parentElement.remove();
				}
				break;
			case "copy-text": {
				const text = el.getAttribute("data-copy-text") || "";
				const feedback = el.getAttribute("data-copy-feedback") || "Copied!";
				const labelEl = el.querySelector("[data-copy-label]") || el;
				const defaultLabel =
					el.getAttribute("data-copy-default") || labelEl.textContent || "";
				copyTextWithFallback(text, () => {
					labelEl.textContent = feedback;
					setTimeout(() => {
						labelEl.textContent = defaultLabel;
					}, 2000);
				});
				break;
			}
			case "copy-target-value": {
				const targetId = el.getAttribute("data-copy-target") || "";
				const input = document.getElementById(targetId);
				if (!input) break;
				const feedback = el.getAttribute("data-copy-feedback") || "Copied!";
				const defaultLabel =
					el.getAttribute("data-copy-default") || el.textContent || "";
				copyTextWithFallback(input.value || "", () => {
					el.textContent = feedback;
					setTimeout(() => {
						el.textContent = defaultLabel;
					}, 2000);
				});
				break;
			}
			case "copy-audit-json": {
				const targetId = el.getAttribute("data-copy-target") || "";
				copyAuditJson(el.id, targetId);
				break;
			}
			case "switch-dispatch-lang": {
				const uid = el.getAttribute("data-reservation-uid") || "";
				const lang = el.getAttribute("data-lang") || "es";
				switchDispatchLang(uid, lang);
				break;
			}
			case "copy-dispatch-snippet": {
				const uid = el.getAttribute("data-reservation-uid") || "";
				copyDispatchSnippet(uid);
				break;
			}
			case "copy-registry-invite":
				copyRegistryInvite(el);
				break;
			case "apply-calculated-quote": {
				const amount = el.getAttribute("data-quote-amount") || "";
				const priceEl = document.getElementById("edit-total-price");
				if (priceEl && amount !== "") {
					priceEl.value = amount;
					priceEl.dispatchEvent(new Event("input", { bubbles: true }));
				}
				break;
			}
			default:
				break;
		}
	});

	// Capture-phase change listener ensures dependent inputs (date ranges, period
	// presets, and check-out minimums) mutate BEFORE HTMX serializes requests.
	document.addEventListener(
		"change",
		(evt) => {
			const target = evt.target;
			if (!target) return;

			if (target.id === "create-check-in") {
				syncMinEndDate(
					target,
					document.getElementById("create-check-out"),
					true,
				);
			} else if (target.id === "edit-check-in") {
				syncMinEndDate(target, document.getElementById("edit-check-out"), true);
			} else if (target.id === "block-start-date") {
				syncMinEndDate(
					target,
					document.getElementById("block-end-date"),
					false,
				);
			}

			const el = target.closest?.("[data-action]");
			if (!el) return;

			const action = el.getAttribute("data-action");
			if (action === "cancel-refund-type") {
				const partialContainer = document.getElementById(
					"partial-amount-container",
				);
				const partialInput = document.getElementById("partial-refund-amount");
				const isPartial = el.value === "partial";
				if (partialContainer) {
					partialContainer.classList.toggle("hidden", !isPartial);
				}
				if (partialInput) {
					partialInput.disabled = !isPartial;
					partialInput.required = isPartial;
				}
			} else if (action === "financial-period-change") {
				if (el.value !== "custom") {
					const df = document.getElementById("date-from-input");
					const dt = document.getElementById("date-to-input");
					if (df) df.value = "";
					if (dt) dt.value = "";
				}
			} else if (action === "financial-custom-date") {
				const pe = document.getElementById("period-select");
				if (pe && el.value) {
					pe.value = "custom";
				}
			}
		},
		true,
	);

	// =========================================================================
	// 5. Occupancy Heatmap Popover Tooltip (DOM-safe, zero innerHTML)
	// =========================================================================
	(() => {
		if (window._heatmapTooltipInitialized) return;
		window._heatmapTooltipInitialized = true;

		function getPopover() {
			return document.getElementById("heatmap-popover");
		}

		function showPopover(cell) {
			const popover = getPopover();
			if (!popover) return;

			const raw = cell.getAttribute("data-popover");
			if (!raw) return;

			let data;
			try {
				data = JSON.parse(raw);
			} catch {
				return;
			}

			popover.replaceChildren();

			// Header row
			const headerRow = document.createElement("div");
			headerRow.className =
				"font-bold text-slate-100 flex items-center justify-between border-b border-slate-700/60 pb-1.5 mb-1.5";
			const dateSpan = document.createElement("span");
			dateSpan.textContent = String(data.date || "");
			headerRow.appendChild(dateSpan);

			if (data.isToday) {
				const todayBadge = document.createElement("span");
				todayBadge.className =
					"text-[10px] bg-indigo-500/30 text-indigo-300 border border-indigo-400/40 px-1.5 py-0.5 rounded font-medium";
				todayBadge.textContent = "Today";
				headerRow.appendChild(todayBadge);
			}
			popover.appendChild(headerRow);

			// Nightly rate row
			const rateRow = document.createElement("div");
			rateRow.className = "text-[11px] text-slate-400 mb-2";
			rateRow.appendChild(document.createTextNode("Nightly Rate: "));
			const rateVal = document.createElement("span");
			rateVal.className = "text-emerald-400 font-semibold";
			rateVal.textContent = `$${String(data.rate ?? "")}`;
			rateRow.appendChild(rateVal);
			const rateSuffix =
				data.propertyFilter === "all" ? " / night (Combined)" : " / night";
			rateRow.appendChild(document.createTextNode(rateSuffix));
			popover.appendChild(rateRow);

			// Property cards
			const propContainer = document.createElement("div");
			propContainer.className = "space-y-1.5";
			const propList = Array.isArray(data.properties)
				? data.properties
				: Array.isArray(data.flats)
					? data.flats
					: [];

			for (const property of propList) {
				const card = document.createElement("div");
				card.className =
					"p-1.5 rounded bg-slate-800/80 border border-slate-700/50";

				const topRow = document.createElement("div");
				topRow.className = "flex items-center justify-between text-[11px]";
				const labelSpan = document.createElement("span");
				labelSpan.className = "font-semibold text-slate-200";
				labelSpan.textContent = String(
					property.label ||
						`Property ${property.property_id}: ${property.status}`,
				);
				const propertyRateSpan = document.createElement("span");
				propertyRateSpan.className = "text-slate-400 font-mono text-[10px]";
				propertyRateSpan.textContent = `$${String(property.rate ?? "")}/nt`;
				topRow.appendChild(labelSpan);
				topRow.appendChild(propertyRateSpan);
				card.appendChild(topRow);

				if (property.guest) {
					const guestRow = document.createElement("div");
					guestRow.className =
						"text-[11px] text-slate-300 mt-0.5 flex items-center justify-between";
					const guestName = document.createElement("span");
					guestName.className = "truncate max-w-[170px]";
					guestName.title = String(property.guest);
					guestName.textContent = String(property.guest);
					guestRow.appendChild(guestName);

					if (property.movement) {
						const movementBadge = document.createElement("span");
						movementBadge.className =
							"text-[9px] px-1 py-0.5 rounded bg-slate-700 text-slate-200 font-medium";
						movementBadge.textContent = String(property.movement);
						guestRow.appendChild(movementBadge);
					}
					card.appendChild(guestRow);
				}

				propContainer.appendChild(card);
			}
			popover.appendChild(propContainer);

			// Warnings
			if (Array.isArray(data.warnings) && data.warnings.length > 0) {
				const warningsBox = document.createElement("div");
				warningsBox.className =
					"mt-2 pt-2 border-t border-slate-700/60 space-y-1";
				for (const warning of data.warnings) {
					const warnRow = document.createElement("div");
					warnRow.className =
						"text-[10px] text-rose-300 flex items-start gap-1 font-medium leading-tight";
					const icon = document.createElement("span");
					icon.className = "shrink-0 text-rose-400";
					icon.textContent = "⚠️";
					const text = document.createElement("span");
					text.textContent = String(warning);
					warnRow.appendChild(icon);
					warnRow.appendChild(text);
					warningsBox.appendChild(warnRow);
				}
				popover.appendChild(warningsBox);
			}

			// Turnover indicator
			if (data.hasTurnover) {
				const turnoverRow = document.createElement("div");
				turnoverRow.className =
					"mt-2 pt-1.5 border-t border-slate-700/60 text-[10px] text-blue-300 flex items-center gap-1.5 font-medium";
				const dot = document.createElement("span");
				dot.className = "w-1.5 h-1.5 rounded-full bg-blue-400 shrink-0";
				const text = document.createElement("span");
				text.textContent = "Same-day Check-out & Check-in";
				turnoverRow.appendChild(dot);
				turnoverRow.appendChild(text);
				popover.appendChild(turnoverRow);
			}

			popover.classList.remove("hidden");

			const rect = cell.getBoundingClientRect();
			const popWidth = popover.offsetWidth || 280;
			const popHeight = popover.offsetHeight || 120;

			let left = rect.left + rect.width / 2 - popWidth / 2;
			const minLeft = 12;
			const maxLeft = window.innerWidth - popWidth - 12;
			left = Math.max(minLeft, Math.min(left, maxLeft));

			let top = rect.top - popHeight - 8;
			if (top < 12) {
				top = rect.bottom + 8;
			}
			if (top + popHeight > window.innerHeight - 12) {
				top = window.innerHeight - popHeight - 12;
			}

			popover.style.left = `${left}px`;
			popover.style.top = `${top}px`;
		}

		function hidePopover() {
			const popover = getPopover();
			if (popover) {
				popover.classList.add("hidden");
			}
		}

		document.addEventListener("mouseover", (e) => {
			const cell = e.target?.closest?.("[data-heatmap-cell]");
			if (cell) {
				showPopover(cell);
			}
		});

		document.addEventListener("mouseout", (e) => {
			const cell = e.target?.closest?.("[data-heatmap-cell]");
			if (cell) {
				const related = e.relatedTarget?.closest?.("[data-heatmap-cell]");
				if (related !== cell) {
					hidePopover();
				}
			}
		});

		document.addEventListener("click", (e) => {
			if (e.target?.closest?.("[data-heatmap-cell]")) {
				hidePopover();
			}
		});

		window.addEventListener("scroll", hidePopover, { passive: true });
		window.addEventListener("resize", hidePopover, { passive: true });
	})();

	// =========================================================================
	// 6. Dynamic Modal & Fragment Initializers
	// =========================================================================
	function initCalendarBlockModal() {
		const startInput = document.getElementById("block-start-date");
		const endInput = document.getElementById("block-end-date");
		if (!startInput || !endInput || startInput.dataset.adminBound === "1") {
			return;
		}
		startInput.dataset.adminBound = "1";

		if (startInput.value) {
			syncMinEndDate(startInput, endInput, false);
		}
	}

	function initCreateReservationModal() {
		const form = document.getElementById("create-reservation-form");
		if (!form || form.dataset.adminBound === "1") return;
		form.dataset.adminBound = "1";

		const checkInInput = document.getElementById("create-check-in");
		const checkOutInput = document.getElementById("create-check-out");
		const propertySelect = document.getElementById("create-property-id");
		const sourceSelect = document.getElementById("create-source");
		const airbnbCodeInput = document.getElementById("create-airbnb-code");
		const priceInput = document.getElementById("manual-total-price");
		const guestNameInput = document.getElementById("create-guest-name");
		const guestEmailInput = document.getElementById("create-guest-email");
		const guestPhoneInput = document.getElementById("create-guest-phone");
		const submitBtn = document.getElementById("create-submit-btn");
		const submitBtnText = document.getElementById("create-submit-text");

		let isAvailable = false;
		let isCheckingAvailability = false;

		function syncAvailabilityFromDom() {
			const resultEl = document.getElementById("quote-preview-result");
			if (resultEl) {
				isAvailable = resultEl.getAttribute("data-available") === "true";
			} else {
				isAvailable = false;
			}
		}

		function getSubmitDefaultText() {
			if (sourceSelect && sourceSelect.value === "airbnb") {
				return "Onboard & Generate Dispatch";
			}
			return "Create Reservation";
		}

		function getUnmetRequirement() {
			if (isCheckingAvailability) {
				return "Checking availability...";
			}

			const propVal = propertySelect ? propertySelect.value : "";
			if (propVal !== "1606" && propVal !== "1707") {
				return "Select a valid property";
			}

			const checkInVal = checkInInput ? checkInInput.value : "";
			const checkOutVal = checkOutInput ? checkOutInput.value : "";
			if (!checkInVal || !checkOutVal) {
				return "Select available dates to enable creation";
			}
			if (checkOutVal <= checkInVal) {
				return "Check-out date must be after check-in date";
			}

			if (!isAvailable) {
				return "Resolve date conflict above to proceed";
			}

			const priceVal = priceInput ? priceInput.value.trim() : "";
			if (
				priceVal === "" ||
				Number.isNaN(Number(priceVal)) ||
				Number(priceVal) < 0
			) {
				if (
					priceVal !== "" &&
					!Number.isNaN(Number(priceVal)) &&
					Number(priceVal) < 0
				) {
					return "Total price cannot be negative";
				}
				return "Total price must be zero or greater";
			}

			const maxGuestsInput = document.getElementById("create-max-guests");
			if (maxGuestsInput) {
				const maxGuestsVal = maxGuestsInput.value.trim();
				const maxGuestsNum = Number(maxGuestsVal);
				if (
					maxGuestsVal === "" ||
					Number.isNaN(maxGuestsNum) ||
					!Number.isInteger(maxGuestsNum) ||
					maxGuestsNum < 1 ||
					maxGuestsNum > 12
				) {
					return "Max guests must be between 1 and 12";
				}
			}

			const nameVal = guestNameInput ? guestNameInput.value.trim() : "";
			if (!nameVal) {
				return "Guest name is required";
			}

			const currentSource = sourceSelect
				? sourceSelect.value
				: "manual_override";
			const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
			const emailVal = guestEmailInput ? guestEmailInput.value.trim() : "";

			if (currentSource === "airbnb") {
				const airbnbCode = airbnbCodeInput ? airbnbCodeInput.value.trim() : "";
				if (!airbnbCode) {
					return "Airbnb confirmation code required";
				}
				const sendEmailCheckbox = document.getElementById("create-send-email");
				if (sendEmailCheckbox?.checked) {
					if (!emailVal || !emailRegex.test(emailVal)) {
						return "Valid guest email required when sending confirmation";
					}
				}
			} else {
				const phoneVal = guestPhoneInput ? guestPhoneInput.value.trim() : "";
				if (!emailVal || !emailRegex.test(emailVal)) {
					return "Guest name and email required";
				}
				if (phoneVal.length < 7 || phoneVal.length > 25) {
					return "Guest phone number must be 7-25 characters";
				}
			}

			return null;
		}

		function validateForm() {
			return applyFormValidationState(
				"create-submit-btn",
				"create-submit-helper",
				getUnmetRequirement(),
			);
		}

		if (priceInput) {
			priceInput.addEventListener("input", () => {
				priceInput.dataset.autoFilled = "false";
			});
		}

		if (sourceSelect) {
			sourceSelect.addEventListener("change", () => {
				const airbnbContainer = document.getElementById(
					"airbnb-fields-container",
				);
				if (airbnbContainer) {
					airbnbContainer.classList.toggle(
						"hidden",
						sourceSelect.value !== "airbnb",
					);
				}

				const text = getSubmitDefaultText();
				if (submitBtn) {
					submitBtn.dataset.defaultText = text;
					if (sourceSelect.value === "airbnb") {
						submitBtn.classList.remove("bg-indigo-600", "hover:bg-indigo-700");
						submitBtn.classList.add("bg-[#FF385C]", "hover:bg-[#E00B41]");
					} else {
						submitBtn.classList.remove("bg-[#FF385C]", "hover:bg-[#E00B41]");
						submitBtn.classList.add("bg-indigo-600", "hover:bg-indigo-700");
					}
				}
				if (
					submitBtnText &&
					submitBtnText.textContent !== "Checking availability..."
				) {
					submitBtnText.textContent = text;
				}
				validateForm();
			});
		}

		if (checkInInput) {
			checkInInput.addEventListener("input", () => {
				syncMinEndDate(checkInInput, checkOutInput, true);
				validateForm();
			});
			if (checkInInput.value) {
				syncMinEndDate(checkInInput, checkOutInput, true);
			}
		}

		if (checkOutInput) {
			checkOutInput.addEventListener("change", validateForm);
			checkOutInput.addEventListener("input", validateForm);
		}

		function isQuotePreviewRequest(evt) {
			const target = evt.detail?.target;
			const quoteContainer = document.getElementById("quote-preview-container");
			if (
				target &&
				(target.id === "quote-preview-container" || target === quoteContainer)
			) {
				return true;
			}
			const path =
				evt.detail?.requestConfig?.path ||
				evt.detail?.pathInfo?.requestPath ||
				evt.detail?.url;
			return (
				typeof path === "string" &&
				path.indexOf("/reservations/quote-preview") !== -1
			);
		}

		function setCheckingState() {
			isCheckingAvailability = true;
			const btn = document.getElementById("create-submit-btn");
			const btnText = document.getElementById("create-submit-text");
			const helper = document.getElementById("create-submit-helper");
			if (!btn) return;

			btn.disabled = true;
			btn.setAttribute("aria-disabled", "true");
			btn.classList.add("opacity-50", "cursor-not-allowed");
			btn.classList.remove("cursor-pointer");
			if (btnText) {
				btnText.textContent = "Checking availability...";
			}
			if (helper) {
				helper.textContent = "Checking availability...";
				helper.classList.remove("hidden");
			}
		}

		function removeCheckingState() {
			isCheckingAvailability = false;
			const btn = document.getElementById("create-submit-btn");
			const btnText = document.getElementById("create-submit-text");
			if (!btn) return;

			if (btnText) {
				btnText.textContent = btn.dataset.defaultText || getSubmitDefaultText();
			}
			validateForm();
		}

		form.addEventListener("htmx:beforeRequest", (evt) => {
			if (isQuotePreviewRequest(evt)) {
				setCheckingState();
			}
		});

		form.addEventListener("htmx:afterRequest", (evt) => {
			if (isQuotePreviewRequest(evt)) {
				removeCheckingState();
			}
		});

		form.addEventListener("htmx:sendError", (evt) => {
			if (isQuotePreviewRequest(evt)) {
				removeCheckingState();
			}
		});

		form.addEventListener("htmx:responseError", (evt) => {
			if (isQuotePreviewRequest(evt)) {
				removeCheckingState();
			}
		});

		form.addEventListener("input", validateForm);
		form.addEventListener("change", validateForm);

		form.addEventListener("availabilityChecked", (evt) => {
			if (evt.detail && typeof evt.detail.available === "boolean") {
				isAvailable = evt.detail.available;
			} else {
				syncAvailabilityFromDom();
			}
			validateForm();
		});

		form.addEventListener("htmx:afterSwap", () => {
			syncAvailabilityFromDom();
			validateForm();
		});

		form.addEventListener("keydown", (evt) => {
			if (evt.key === "Enter") {
				if (evt.target?.tagName === "TEXTAREA") {
					return;
				}
				if (!validateForm()) {
					evt.preventDefault();
				}
			}
		});

		form.addEventListener("submit", (evt) => {
			if (!validateForm()) {
				evt.preventDefault();
				evt.stopPropagation();
			}
		});

		syncAvailabilityFromDom();
		validateForm();
	}

	function initEditReservationModal() {
		const form = document.getElementById("edit-reservation-form");
		if (!form || form.dataset.adminBound === "1") return;
		form.dataset.adminBound = "1";

		const ABSOLUTE_MAX_GUESTS = 12;
		const checkInInput = document.getElementById("edit-check-in");
		const checkOutInput = document.getElementById("edit-check-out");
		const propertySelect = document.getElementById("edit-property-id");
		const sourceInput = document.getElementById("edit-source");
		const externalCodeInput = document.getElementById("edit-external-code");
		const priceInput = document.getElementById("edit-total-price");
		const maxGuestsInput = document.getElementById("edit-max-guests");
		const maxGuestsHint = document.getElementById("edit-max-guests-hint");
		const registeredGuestsInput = document.getElementById(
			"edit-registered-guest-count",
		);
		const guestCountInput = document.getElementById("edit-guest-count");
		const resetRegistryInput = document.getElementById(
			"edit-reset-guest-registry",
		);
		const guestNameInput = document.getElementById("edit-guest-name");
		const guestEmailInput = document.getElementById("edit-guest-email");
		const guestPhoneInput = document.getElementById("edit-guest-phone");
		const carPlatesInput = document.getElementById("edit-car-plates");
		const carModelInput = document.getElementById("edit-car-model");
		const addGuestBtn = document.getElementById("edit-add-guest-btn");
		const clearRegistryBtn = document.getElementById("edit-clear-registry-btn");
		const emptyStateEl = document.getElementById("edit-registry-empty-state");
		const rosterCountBadge = document.getElementById("edit-roster-count-badge");
		const clearanceModifiedIndicator = document.getElementById(
			"clearance-modified-indicator",
		);
		const hasSyncedClearance =
			form.getAttribute("data-has-synced-clearance") === "true";

		let currentGuestCount = guestCountInput
			? Number.parseInt(guestCountInput.value, 10) || 0
			: 0;
		let isAvailable = true;

		function markRegistryModified() {
			if (hasSyncedClearance && clearanceModifiedIndicator) {
				clearanceModifiedIndicator.classList.remove("hidden");
			}
		}

		function updateGuestCards() {
			for (let num = 1; num <= ABSOLUTE_MAX_GUESTS; num++) {
				const card = document.getElementById(`edit-guest-card-${num}`);
				if (!card) continue;
				const inputs = card.querySelectorAll("input, select");
				if (num <= currentGuestCount) {
					card.classList.remove("hidden");
					for (const inp of inputs) {
						inp.removeAttribute("disabled");
					}
				} else {
					card.classList.add("hidden");
					for (const inp of inputs) {
						inp.setAttribute("disabled", "disabled");
						if (inp.tagName === "INPUT") {
							inp.value = "";
						}
					}
				}
			}

			if (guestCountInput) {
				guestCountInput.value = String(currentGuestCount);
			}
			if (registeredGuestsInput) {
				registeredGuestsInput.value = String(currentGuestCount);
			}

			const minCap = Math.max(1, currentGuestCount);
			if (maxGuestsInput) {
				maxGuestsInput.min = String(minCap);
				let currentMax = Number.parseInt(maxGuestsInput.value, 10) || 6;
				if (currentGuestCount > currentMax) {
					maxGuestsInput.value = String(currentGuestCount);
					currentMax = currentGuestCount;
				}
				if (rosterCountBadge) {
					rosterCountBadge.textContent = `${currentGuestCount} / ${currentMax} Guests`;
				}
			}

			if (maxGuestsHint) {
				maxGuestsHint.textContent =
					currentGuestCount > 0
						? `1–12 (min ${currentGuestCount} registered)`
						: "1–12";
			}

			if (emptyStateEl) {
				emptyStateEl.classList.toggle("hidden", currentGuestCount !== 0);
			}

			if (addGuestBtn) {
				addGuestBtn.classList.toggle(
					"hidden",
					currentGuestCount >= ABSOLUTE_MAX_GUESTS,
				);
			}

			if (clearRegistryBtn) {
				const hasVehicle =
					(carPlatesInput && carPlatesInput.value.trim() !== "") ||
					(carModelInput && carModelInput.value.trim() !== "");
				clearRegistryBtn.classList.toggle(
					"hidden",
					currentGuestCount === 0 && !hasVehicle,
				);
			}
		}

		if (addGuestBtn) {
			addGuestBtn.addEventListener("click", () => {
				if (currentGuestCount >= ABSOLUTE_MAX_GUESTS) return;
				const wasEmpty = currentGuestCount === 0;
				currentGuestCount++;
				if (resetRegistryInput) {
					resetRegistryInput.value = "0";
				}
				updateGuestCards();

				if (wasEmpty) {
					const primaryName = guestNameInput ? guestNameInput.value.trim() : "";
					const primaryEmail = guestEmailInput
						? guestEmailInput.value.trim()
						: "";
					const primaryPhone = guestPhoneInput
						? guestPhoneInput.value.trim()
						: "";
					const fn1 = document.getElementById("edit-guest-first-name-1");
					const ln1 = document.getElementById("edit-guest-last-name-1");
					const full1 = document.getElementById("edit-guest-name-1");
					const em1 = document.getElementById("edit-guest-email-1");
					const ph1 = document.getElementById("edit-guest-phone-1");

					if (primaryName && fn1 && !fn1.value && ln1 && !ln1.value) {
						const parts = primaryName.split(/\s+/);
						if (parts.length > 1) {
							fn1.value = parts.slice(0, -1).join(" ");
							ln1.value = parts[parts.length - 1];
						} else if (parts.length === 1) {
							fn1.value = parts[0];
						}
						if (full1) full1.value = primaryName;
					}
					if (primaryEmail && em1 && !em1.value) {
						em1.value = primaryEmail;
					}
					if (
						primaryPhone &&
						primaryPhone.toUpperCase() !== "N/A" &&
						ph1 &&
						!ph1.value
					) {
						ph1.value = primaryPhone;
					}
				}

				markRegistryModified();
				validateForm();
			});
		}

		if (clearRegistryBtn) {
			clearRegistryBtn.addEventListener("click", () => {
				currentGuestCount = 0;
				if (resetRegistryInput) {
					resetRegistryInput.value = "1";
				}
				if (carPlatesInput) carPlatesInput.value = "";
				if (carModelInput) carModelInput.value = "";
				for (let n = 1; n <= ABSOLUTE_MAX_GUESTS; n++) {
					const fn = document.getElementById(`edit-guest-first-name-${n}`);
					const ln = document.getElementById(`edit-guest-last-name-${n}`);
					const nm = document.getElementById(`edit-guest-name-${n}`);
					const ph = document.getElementById(`edit-guest-phone-${n}`);
					const ag = document.getElementById(`edit-guest-age-${n}`);
					const dn = document.getElementById(`edit-guest-doc-num-${n}`);
					if (fn) fn.value = "";
					if (ln) ln.value = "";
					if (nm) nm.value = "";
					if (ph) ph.value = "";
					if (ag) ag.value = "";
					if (dn) dn.value = "";
				}
				const em1 = document.getElementById("edit-guest-email-1");
				if (em1) em1.value = "";
				updateGuestCards();
				markRegistryModified();
				validateForm();
			});
		}

		form.addEventListener("click", (e) => {
			const removeBtn = e.target?.closest?.("[data-remove-guest]");
			if (!removeBtn) return;
			const numToRemove = Number.parseInt(
				removeBtn.getAttribute("data-remove-guest") || "",
				10,
			);
			if (!numToRemove || numToRemove < 1 || numToRemove > currentGuestCount) {
				return;
			}

			for (let i = numToRemove; i < currentGuestCount; i++) {
				const curFn = document.getElementById(`edit-guest-first-name-${i}`);
				const nxtFn = document.getElementById(`edit-guest-first-name-${i + 1}`);
				const curLn = document.getElementById(`edit-guest-last-name-${i}`);
				const nxtLn = document.getElementById(`edit-guest-last-name-${i + 1}`);
				const curNm = document.getElementById(`edit-guest-name-${i}`);
				const nxtNm = document.getElementById(`edit-guest-name-${i + 1}`);
				const curPh = document.getElementById(`edit-guest-phone-${i}`);
				const nxtPh = document.getElementById(`edit-guest-phone-${i + 1}`);
				const curCo = document.getElementById(`edit-guest-country-${i}`);
				const nxtCo = document.getElementById(`edit-guest-country-${i + 1}`);
				const curAg = document.getElementById(`edit-guest-age-${i}`);
				const nxtAg = document.getElementById(`edit-guest-age-${i + 1}`);
				const curDt = document.getElementById(`edit-guest-doc-type-${i}`);
				const nxtDt = document.getElementById(`edit-guest-doc-type-${i + 1}`);
				const curDn = document.getElementById(`edit-guest-doc-num-${i}`);
				const nxtDn = document.getElementById(`edit-guest-doc-num-${i + 1}`);

				if (curFn && nxtFn) curFn.value = nxtFn.value;
				if (curLn && nxtLn) curLn.value = nxtLn.value;
				if (curNm && nxtNm) curNm.value = nxtNm.value;
				if (curPh && nxtPh) {
					const nxtPhoneVal = nxtPh.value.trim();
					if (i === 1 && !nxtPhoneVal) {
						const primaryPhoneVal = guestPhoneInput
							? guestPhoneInput.value.trim()
							: "";
						curPh.value =
							primaryPhoneVal.toUpperCase() !== "N/A" ? primaryPhoneVal : "";
					} else {
						curPh.value = nxtPh.value;
					}
				}
				if (curCo && nxtCo) curCo.value = nxtCo.value;
				if (curAg && nxtAg) curAg.value = nxtAg.value;
				if (curDt && nxtDt) curDt.value = nxtDt.value;
				if (curDn && nxtDn) curDn.value = nxtDn.value;
			}

			if (numToRemove === 1 && currentGuestCount > 1) {
				const em1 = document.getElementById("edit-guest-email-1");
				if (em1) {
					em1.value = guestEmailInput ? guestEmailInput.value.trim() : "";
				}
			}

			currentGuestCount--;
			updateGuestCards();
			markRegistryModified();
			validateForm();
		});

		function syncAvailabilityFromDom() {
			const resultEl = document.getElementById("quote-preview-result");
			if (resultEl?.hasAttribute("data-available")) {
				isAvailable = resultEl.getAttribute("data-available") === "true";
			} else {
				isAvailable = true;
			}
		}

		function getUnmetRequirement() {
			const propVal = propertySelect ? propertySelect.value : "";
			if (propVal !== "1606" && propVal !== "1707") {
				return "Select a valid property";
			}

			const checkInVal = checkInInput ? checkInInput.value : "";
			const checkOutVal = checkOutInput ? checkOutInput.value : "";
			if (!checkInVal || !checkOutVal) {
				return "Select valid stay dates";
			}
			if (checkOutVal <= checkInVal) {
				return "Check-out date must be after check-in date";
			}

			if (!isAvailable) {
				return "Resolve date conflict above to proceed";
			}

			const priceVal = priceInput ? priceInput.value.trim() : "";
			if (
				priceVal === "" ||
				Number.isNaN(Number(priceVal)) ||
				Number(priceVal) < 0
			) {
				return "Total price must be zero or greater";
			}

			if (maxGuestsInput) {
				const maxGuestsVal = maxGuestsInput.value.trim();
				const maxGuestsNum = Number(maxGuestsVal);
				if (
					maxGuestsVal === "" ||
					Number.isNaN(maxGuestsNum) ||
					!Number.isInteger(maxGuestsNum) ||
					maxGuestsNum < 1 ||
					maxGuestsNum > 12
				) {
					return "Max guests must be between 1 and 12";
				}
				if (currentGuestCount > 0 && maxGuestsNum < currentGuestCount) {
					return `Cannot lower Max Guests below ${currentGuestCount} currently registered guests`;
				}
				if (rosterCountBadge) {
					rosterCountBadge.textContent = `${currentGuestCount} / ${maxGuestsNum} Guests`;
				}
			}

			const nameVal = guestNameInput ? guestNameInput.value.trim() : "";
			if (!nameVal) {
				return "Guest name is required";
			}

			const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
			const currentSource = sourceInput
				? sourceInput.value.toLowerCase()
				: "web";
			if (currentSource === "airbnb") {
				const extCode = externalCodeInput ? externalCodeInput.value.trim() : "";
				if (!extCode) {
					return "Airbnb confirmation code is required";
				}
			} else {
				const emailVal = guestEmailInput ? guestEmailInput.value.trim() : "";
				const phoneVal = guestPhoneInput ? guestPhoneInput.value.trim() : "";
				if (!emailVal || !emailRegex.test(emailVal)) {
					return "Valid guest email is required";
				}
				if (phoneVal.length < 7 || phoneVal.length > 25) {
					return "Guest phone number must be 7-25 characters";
				}
			}

			for (let g = 1; g <= currentGuestCount; g++) {
				const gFn = document.getElementById(`edit-guest-first-name-${g}`);
				const gLn = document.getElementById(`edit-guest-last-name-${g}`);
				const gNm = document.getElementById(`edit-guest-name-${g}`);
				const gDn = document.getElementById(`edit-guest-doc-num-${g}`);
				const gAg = document.getElementById(`edit-guest-age-${g}`);
				const gCo = document.getElementById(`edit-guest-country-${g}`);
				const gPh = document.getElementById(`edit-guest-phone-${g}`);

				const fnVal = gFn ? gFn.value.trim() : "";
				const lnVal = gLn ? gLn.value.trim() : "";
				if (!fnVal || !lnVal) {
					return `Guest #${g} requires both First Name and Last Name`;
				}
				if (gNm) {
					gNm.value = `${fnVal} ${lnVal}`.trim();
				}

				const dnVal = gDn ? gDn.value.trim() : "";
				if (dnVal.length < 2 || dnVal.length > 50) {
					return `Guest #${g} document number must be 2–50 characters`;
				}

				const agVal = gAg ? gAg.value.trim() : "";
				const agNum = Number(agVal);
				if (
					agVal === "" ||
					Number.isNaN(agNum) ||
					!Number.isInteger(agNum) ||
					agNum < 0 ||
					agNum > 120
				) {
					return `Guest #${g} age must be between 0 and 120`;
				}

				const coVal = gCo ? gCo.value.trim() : "";
				if (!coVal) {
					return `Guest #${g} requires a country`;
				}

				const phVal = gPh ? gPh.value.trim() : "";
				if (g === 1) {
					const gEm1 = document.getElementById("edit-guest-email-1");
					const em1Val = gEm1 ? gEm1.value.trim() : "";
					if (!em1Val || !emailRegex.test(em1Val)) {
						return "Guest #1 requires a valid email address";
					}
					if (phVal.length < 6 || phVal.length > 30) {
						return "Guest #1 phone must be 6–30 characters";
					}
				} else if (phVal !== "" && (phVal.length < 6 || phVal.length > 30)) {
					return `Guest #${g} phone must be 6–30 characters`;
				}
			}

			return null;
		}

		function validateForm() {
			return applyFormValidationState(
				"edit-submit-btn",
				"edit-submit-helper",
				getUnmetRequirement(),
			);
		}

		if (checkInInput) {
			checkInInput.addEventListener("input", () => {
				syncMinEndDate(checkInInput, checkOutInput, true);
				validateForm();
			});
			if (checkInInput.value) {
				syncMinEndDate(checkInInput, checkOutInput, true);
			}
		}

		if (checkOutInput) {
			checkOutInput.addEventListener("change", validateForm);
			checkOutInput.addEventListener("input", validateForm);
		}

		if (carPlatesInput) {
			carPlatesInput.addEventListener("input", markRegistryModified);
		}
		if (carModelInput) {
			carModelInput.addEventListener("input", markRegistryModified);
		}

		const cardsContainer = document.getElementById(
			"edit-guest-cards-container",
		);
		if (cardsContainer) {
			cardsContainer.addEventListener("input", markRegistryModified);
			cardsContainer.addEventListener("change", markRegistryModified);
		}

		form.addEventListener("input", validateForm);
		form.addEventListener("change", validateForm);

		form.addEventListener("availabilityChecked", (evt) => {
			if (evt.detail && typeof evt.detail.available === "boolean") {
				isAvailable = evt.detail.available;
			} else {
				syncAvailabilityFromDom();
			}
			validateForm();
		});

		form.addEventListener("htmx:afterSwap", () => {
			syncAvailabilityFromDom();
			validateForm();
		});

		updateGuestCards();
		syncAvailabilityFromDom();
		validateForm();
	}

	function processQuotePreview() {
		const resultEl = document.getElementById("quote-preview-result");
		if (!resultEl || resultEl.dataset.quoteProcessed === "1") return;
		resultEl.dataset.quoteProcessed = "1";

		const autoFillPrice = resultEl.getAttribute("data-auto-fill-price");
		if (autoFillPrice !== null && autoFillPrice !== "") {
			const priceInput = document.getElementById("manual-total-price");
			if (
				priceInput &&
				(!priceInput.value || priceInput.dataset.autoFilled === "true")
			) {
				priceInput.value = autoFillPrice;
				priceInput.dataset.autoFilled = "true";
			}
		}

		const rawEvent = resultEl.getAttribute("data-quote-event");
		if (rawEvent) {
			try {
				const detail = JSON.parse(rawEvent);
				const form =
					document.getElementById("create-reservation-form") ||
					document.getElementById("edit-reservation-form");
				const target = form || document;
				target.dispatchEvent(
					new CustomEvent("availabilityChecked", {
						bubbles: true,
						detail,
					}),
				);
			} catch {
				// Ignore malformed event metadata
			}
		}
	}

	function initDynamicFragments() {
		initCalendarBlockModal();
		initCreateReservationModal();
		initEditReservationModal();
		processQuotePreview();
	}

	document.body.addEventListener("htmx:load", initDynamicFragments);
	document.body.addEventListener("htmx:afterSwap", initDynamicFragments);
	initDynamicFragments();

	// =========================================================================
	// 7. Unobtrusive PWA Pass-Through Service Worker Registration
	// =========================================================================
	if ("serviceWorker" in navigator) {
		window.addEventListener("load", () => {
			navigator.serviceWorker
				.register("/sw.js", { scope: "/" })
				.catch(() => {});
		});
	}
})();
