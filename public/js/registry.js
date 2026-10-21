// Guest Registry Form Interactive Logic
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('registry-form');
    if (!form) return;

    const btnSubmit = document.getElementById('submit-button');
    const txtSubmit = document.getElementById('submit-text');
    const msgBox = document.getElementById('form-message');
    const successOverlay = document.getElementById('success-overlay');
    
    const displayProperty = document.getElementById('display-property');
    const displayCheckIn = document.getElementById('display-check-in');
    const displayCheckOut = document.getElementById('display-check-out');

    const hiddenProperty = document.getElementById('hidden-property');
    const hiddenCheckIn = document.getElementById('hidden-check-in');
    const hiddenCheckOut = document.getElementById('hidden-check-out');
    const hiddenReservationCode = document.getElementById('hidden-reservation-code');
    const btnUnlockedGuide = document.getElementById('btn-unlocked-guide');
    const successTitle = document.getElementById('success-title');
    const successDesc = document.getElementById('success-desc');

    const pageConfig = (window.getPageConfig && window.getPageConfig()) || {};
    const lang = pageConfig.lang || document.documentElement.lang || 'en';
    const t = (key, params, fallback) => (window.t ? window.t(key, params, fallback) : (fallback !== undefined ? fallback : key));

    const msgLoading = t('registryLookupLoading', {}, 'Loading...');
    const msgNotFound = t('registryLookupNotFound', {}, 'No reservation found matching this code.');
    const msgAlreadyCompleted = t('registryAlreadyCompleted', {}, 'A Guest Registry has already been completed for this reservation.');
    const msgConcluded = t('registryConcluded', {}, 'This reservation has concluded. Access to guest registration is no longer active.');
    const msgErrEmail = t('registryErrEmail', {}, '');
    const msgErrPhone = t('registryErrPhoneRequired', {}, '');
    const msgErrPrivacyConsent = t('registryErrPrivacyConsent', {}, '');
    const msgErrMinorConsent = t('registryErrMinorConsent', {}, '');

    const addGuestBtn = document.getElementById('add-guest-button');
    const guestCountInput = document.getElementById('guest-count-input');
    const captchaLabel = document.getElementById('captcha-label');
    const captchaChallenge = document.getElementById('captcha-challenge');
    const captchaResponse = document.getElementById('captcha-response');
    const privacyConsentCheckbox = document.getElementById('privacy-consent') || document.getElementById('registry-privacy-consent');
    const minorAttestationContainer = document.getElementById('minor-attestation-container');
    const minorGuardianCheckbox = document.getElementById('minor-guardian-consent');

    // Safely sanitize URLs before assigning to href
    function sanitizeUrl(url) {
        if (!url || typeof url !== 'string') return '#';
        const trimmed = url.trim();
        if (trimmed.startsWith('/') || trimmed.startsWith('./') || trimmed.startsWith('../')) {
            return trimmed;
        }
        try {
            const parsed = new URL(trimmed, window.location.origin);
            if (parsed.protocol === 'http:' || parsed.protocol === 'https:') {
                return parsed.href;
            }
        } catch (_) {
            return '#';
        }
        return '#';
    }

    const defaultErrorMsg = t('registryGenericError', {}, 'Something went wrong. Please check the fields and try again.');
    const submittingMsg = t('registrySubmitting', {}, 'Registering...');
    const defaultDateMsg = t('registryNotSpecified', {}, 'Not Specified');
    const defaultPropMsg = t('registryDefaultProperty', {}, 'OceanViewFlats (Not Specified)');

    // 1. Read URL query params and populate stay details
    const urlParams = new URLSearchParams(window.location.search);
    const checkInVal = urlParams.get('check_in') || '';
    const checkOutVal = urlParams.get('check_out') || '';
    const propertyVal = urlParams.get('property') || '';
    const reservationCodeVal = urlParams.get('code') || urlParams.get('reservation_code') || '';

    function getTodayCotDateString() {
        try {
            const formatter = new Intl.DateTimeFormat('en-CA', {
                timeZone: 'America/Bogota',
                year: 'numeric',
                month: '2-digit',
                day: '2-digit'
            });
            return formatter.format(new Date());
        } catch (_) {
            return new Date().toISOString().slice(0, 10);
        }
    }

    function displayConcludedState(message) {
        if (displayProperty) displayProperty.textContent = '--';
        if (displayCheckIn) displayCheckIn.textContent = '--';
        if (displayCheckOut) displayCheckOut.textContent = '--';
        if (hiddenProperty) hiddenProperty.value = '';
        if (hiddenCheckIn) hiddenCheckIn.value = '';
        if (hiddenCheckOut) hiddenCheckOut.value = '';
        if (hiddenReservationCode) hiddenReservationCode.value = '';

        const guest1NameInput = document.getElementById('guest-name-1');
        if (guest1NameInput) guest1NameInput.value = '';
        const guest1FnInput = document.getElementById('guest-first-name-1');
        if (guest1FnInput) guest1FnInput.value = '';
        const guest1LnInput = document.getElementById('guest-last-name-1');
        if (guest1LnInput) guest1LnInput.value = '';

        if (form) form.classList.add('hidden');
        if (successOverlay) successOverlay.classList.add('hidden');

        if (msgBox) {
            msgBox.textContent = message || msgConcluded;
            msgBox.className = 'p-5 rounded-2xl text-sm font-medium mb-6 bg-amber-50 text-amber-800 border border-amber-200';
            msgBox.classList.remove('hidden');
        }
    }

    const todayCot = getTodayCotDateString();
    const isUrlCheckOutConcluded = Boolean(checkOutVal && /^\d{4}-\d{2}-\d{2}$/.test(checkOutVal) && checkOutVal < todayCot);

    if (isUrlCheckOutConcluded) {
        displayConcludedState(msgConcluded);
        return;
    }

    // Show details to user and populate hidden form fields
    if (hiddenReservationCode && reservationCodeVal) {
        hiddenReservationCode.value = reservationCodeVal;
    }

    if (propertyVal) {
        displayProperty.textContent = `OceanViewFlats ${propertyVal}`;
        hiddenProperty.value = propertyVal;
    } else if (reservationCodeVal) {
        displayProperty.textContent = msgLoading;
        hiddenProperty.value = "";
    } else {
        displayProperty.textContent = defaultPropMsg;
        hiddenProperty.value = "";
    }

    if (checkInVal) {
        displayCheckIn.textContent = checkInVal;
        hiddenCheckIn.value = checkInVal;
    } else if (reservationCodeVal) {
        displayCheckIn.textContent = "...";
        hiddenCheckIn.value = "";
    } else {
        displayCheckIn.textContent = defaultDateMsg;
        hiddenCheckIn.value = "";
    }

    if (checkOutVal) {
        displayCheckOut.textContent = checkOutVal;
        hiddenCheckOut.value = checkOutVal;
    } else if (reservationCodeVal) {
        displayCheckOut.textContent = "...";
        hiddenCheckOut.value = "";
    } else {
        displayCheckOut.textContent = defaultDateMsg;
        hiddenCheckOut.value = "";
    }

    // Async lookup if reservation code is present
    async function lookupReservation() {
        if (!reservationCodeVal) return;

        const apiBase = pageConfig.apiBase || `${pageConfig.assetPrefix || ''}api/`;
        const lookupUrl = `${apiBase}registry-lookup.php?code=${encodeURIComponent(reservationCodeVal)}&lang=${encodeURIComponent(lang)}`;

        try {
            const res = await fetch(lookupUrl, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();

            if (res.ok && data.success && data.reservation) {
                const r = data.reservation;
                if (r.property_id) {
                    displayProperty.textContent = `OceanViewFlats ${r.property_id}`;
                    hiddenProperty.value = r.property_id;
                }
                if (r.check_in) {
                    displayCheckIn.textContent = r.check_in;
                    hiddenCheckIn.value = r.check_in;
                }
                if (r.check_out) {
                    displayCheckOut.textContent = r.check_out;
                    hiddenCheckOut.value = r.check_out;
                }
                const guest1NameInput = document.getElementById('guest-name-1');
                const guest1FnInput = document.getElementById('guest-first-name-1');
                const guest1LnInput = document.getElementById('guest-last-name-1');
                if (r.guest_name) {
                    if (guest1NameInput && !guest1NameInput.value) {
                        guest1NameInput.value = r.guest_name;
                    }
                    if (guest1FnInput && !guest1FnInput.value && guest1LnInput && !guest1LnInput.value) {
                        const parts = r.guest_name.trim().split(/\s+/);
                        if (parts.length > 1) {
                            guest1FnInput.value = parts.slice(0, -1).join(' ');
                            guest1LnInput.value = parts[parts.length - 1];
                        } else if (parts.length === 1) {
                            guest1FnInput.value = parts[0];
                        }
                    }
                }

                if (typeof r.max_guests === 'number' || (typeof r.max_guests === 'string' && r.max_guests !== '')) {
                    const parsedMax = parseInt(r.max_guests, 10);
                    if (!isNaN(parsedMax) && parsedMax >= 1 && parsedMax <= ABSOLUTE_MAX_GUESTS) {
                        maxGuests = parsedMax;
                        if (currentGuestCount > maxGuests) {
                            currentGuestCount = maxGuests;
                        }
                        updateGuestCards();
                    }
                }

                if (r.registry_completed) {
                    isRegistryCompleted = true;
                    if (btnSubmit) {
                        btnSubmit.disabled = true;
                    }
                    if (btnUnlockedGuide) {
                        const pageName = lang === 'en' ? 'index.html' : `${lang}.html`;
                        const assetPrefix = pageConfig.assetPrefix || (apiBase ? apiBase.replace(/api\/?$/, '') : '');
                        const guideBase = `${assetPrefix}guide/${pageName}`;
                        const rawGuideUrl = data.guide_url || `${guideBase}?code=${encodeURIComponent(reservationCodeVal)}`;
                        btnUnlockedGuide.href = sanitizeUrl(rawGuideUrl);
                    }
                    if (successDesc) {
                        successDesc.textContent = msgAlreadyCompleted;
                    }
                    form.classList.add('hidden');
                    successOverlay.classList.remove('hidden');
                }
            } else if ((res.status === 403 || !data.success) && data.status === 'concluded') {
                displayConcludedState(data.message || msgConcluded);
            } else if (res.status === 404 || res.status === 403) {
                if (!hiddenCheckIn.value) displayCheckIn.textContent = defaultDateMsg;
                if (!hiddenCheckOut.value) displayCheckOut.textContent = defaultDateMsg;
                if (!hiddenProperty.value) displayProperty.textContent = defaultPropMsg;

                msgBox.textContent = data.message || msgNotFound;
                msgBox.className = 'p-5 rounded-2xl text-sm font-medium mb-6 bg-amber-50 text-amber-800 border border-amber-200';
                msgBox.classList.remove('hidden');
            }
        } catch (err) {
            console.error('Error looking up reservation details:', err);
            if (!hiddenCheckIn.value) displayCheckIn.textContent = defaultDateMsg;
            if (!hiddenCheckOut.value) displayCheckOut.textContent = defaultDateMsg;
            if (!hiddenProperty.value) displayProperty.textContent = defaultPropMsg;
        }
    }

    if (reservationCodeVal) {
        lookupReservation();
    }

    // 2. Manage Dynamic Guest Cards (up to maxGuests guests; default 6, max 12)
    const ABSOLUTE_MAX_GUESTS = 12;
    const minorDocTypes = Array.isArray(pageConfig.minorDocTypes) ? pageConfig.minorDocTypes : [];
    let maxGuests = 6;
    let currentGuestCount = 1;
    let isRegistryCompleted = false;

    function isGuestCardMinor(num) {
        const ageInput = document.getElementById(`guest-age-${num}`);
        const docTypeSelect = document.getElementById(`guest-doc-type-${num}`);

        if (ageInput) {
            const rawAge = String(ageInput.value || '').trim();
            if (rawAge !== '') {
                const parsedAge = parseInt(rawAge, 10);
                if (!isNaN(parsedAge) && parsedAge >= 0 && parsedAge < 18) {
                    return true;
                }
            }
        }

        if (docTypeSelect) {
            const docType = String(docTypeSelect.value || '').trim();
            if (minorDocTypes.includes(docType)) {
                return true;
            }
        }

        return false;
    }

    function evaluateMinorPresence() {
        let hasMinor = false;
        for (let num = 1; num <= currentGuestCount; num++) {
            if (isGuestCardMinor(num)) {
                hasMinor = true;
                break;
            }
        }

        if (minorAttestationContainer && minorGuardianCheckbox) {
            if (hasMinor) {
                minorAttestationContainer.classList.remove('hidden');
                minorGuardianCheckbox.disabled = false;
                minorGuardianCheckbox.required = true;
                minorGuardianCheckbox.removeAttribute('disabled');
                minorGuardianCheckbox.setAttribute('required', 'required');
            } else {
                minorAttestationContainer.classList.add('hidden');
                minorGuardianCheckbox.checked = false;
                minorGuardianCheckbox.disabled = true;
                minorGuardianCheckbox.required = false;
                minorGuardianCheckbox.setAttribute('disabled', 'disabled');
                minorGuardianCheckbox.removeAttribute('required');
            }
        }

        return hasMinor;
    }

    // Helper to update dynamic guest card input states (enable/disable for submission correctness)
    function updateGuestCards() {
        for (let num = 1; num <= ABSOLUTE_MAX_GUESTS; num++) {
            const card = document.getElementById(`guest-card-${num}`);
            if (!card) continue;

            const inputs = card.querySelectorAll('input, select');
            if (num <= currentGuestCount) {
                // Show card
                card.classList.remove('hidden');
                // Enable inputs & make them required (companion phone is optional, hidden legacy name is not required)
                inputs.forEach(input => {
                    input.removeAttribute('disabled');
                    const isCompanionPhone = input.id && input.id.startsWith('guest-phone-') && num > 1;
                    const isHidden = input.type === 'hidden';
                    if (!isCompanionPhone && !isHidden) {
                        input.setAttribute('required', 'required');
                    } else {
                        input.removeAttribute('required');
                    }
                });
            } else {
                // Hide card
                card.classList.add('hidden');
                // Disable inputs so they are not sent in POST, and clear them
                inputs.forEach(input => {
                    input.setAttribute('disabled', 'disabled');
                    input.removeAttribute('required');
                    if (input.tagName === 'INPUT' && input.type !== 'hidden') input.value = '';
                });
            }
        }

        // Set hidden guest count input value
        guestCountInput.value = currentGuestCount;

        // Hide add button if maximum (maxGuests) reached
        if (currentGuestCount >= maxGuests) {
            addGuestBtn.classList.add('hidden');
        } else {
            addGuestBtn.classList.remove('hidden');
        }

        evaluateMinorPresence();
    }

    // Add guest button handler
    addGuestBtn.addEventListener('click', () => {
        if (isRegistryCompleted) return;
        if (currentGuestCount < maxGuests) {
            currentGuestCount++;
            updateGuestCards();
            // Smooth scroll to the newly added guest card
            const newCard = document.getElementById(`guest-card-${currentGuestCount}`);
            if (newCard) {
                newCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }
    });

    // Remove guest handlers (attached using event delegation on the form)
    form.addEventListener('click', (e) => {
        const removeBtn = e.target.closest('[data-remove-guest]');
        if (!removeBtn) return;

        const numToRemove = parseInt(removeBtn.getAttribute('data-remove-guest'), 10);
        if (numToRemove && numToRemove > 1) {
            for (let i = numToRemove; i < currentGuestCount; i++) {
                const currentFirstName = document.getElementById(`guest-first-name-${i}`);
                const nextFirstName = document.getElementById(`guest-first-name-${i+1}`);
                const currentLastName = document.getElementById(`guest-last-name-${i}`);
                const nextLastName = document.getElementById(`guest-last-name-${i+1}`);
                const currentName = document.getElementById(`guest-name-${i}`);
                const nextName = document.getElementById(`guest-name-${i+1}`);
                const currentPhone = document.getElementById(`guest-phone-${i}`);
                const nextPhone = document.getElementById(`guest-phone-${i+1}`);
                const currentCountry = document.getElementById(`guest-country-${i}`);
                const nextCountry = document.getElementById(`guest-country-${i+1}`);
                const currentAge = document.getElementById(`guest-age-${i}`);
                const nextAge = document.getElementById(`guest-age-${i+1}`);
                const currentDocType = document.getElementById(`guest-doc-type-${i}`);
                const nextDocType = document.getElementById(`guest-doc-type-${i+1}`);
                const currentDocNum = document.getElementById(`guest-doc-num-${i}`);
                const nextDocNum = document.getElementById(`guest-doc-num-${i+1}`);

                if (currentFirstName && nextFirstName) currentFirstName.value = nextFirstName.value;
                if (currentLastName && nextLastName) currentLastName.value = nextLastName.value;
                if (currentName && nextName) currentName.value = nextName.value;
                if (currentPhone && nextPhone) currentPhone.value = nextPhone.value;
                if (currentCountry && nextCountry) currentCountry.value = nextCountry.value;
                if (currentAge && nextAge) currentAge.value = nextAge.value;
                if (currentDocType && nextDocType) currentDocType.value = nextDocType.value;
                if (currentDocNum && nextDocNum) currentDocNum.value = nextDocNum.value;
            }

            // Decrement and refresh UI states
            currentGuestCount--;
            updateGuestCards();
        }
    });

    // Initialize the guest cards correct disabled states
    updateGuestCards();

    // Re-evaluate minor attestation whenever age or document type changes on any active guest card
    form.addEventListener('input', (e) => {
        const target = e.target;
        if (target && target.id && (target.id.startsWith('guest-age-') || target.id.startsWith('guest-doc-type-'))) {
            evaluateMinorPresence();
        }
    });
    form.addEventListener('change', (e) => {
        const target = e.target;
        if (target && target.id && (target.id.startsWith('guest-age-') || target.id.startsWith('guest-doc-type-'))) {
            evaluateMinorPresence();
        }
    });

    // 3. Dynamic Math Captcha fetch
    let captchaSignature = '';
    async function loadCaptcha() {
        try {
            const actionPath = form.getAttribute('action') || 'api/registry-processor.php';
            const processorBase = actionPath.replace('registry-processor.php', '');
            
            // Re-use the captcha endpoint from contact-processor.php since it has CAPTCHA_SECRET config!
            const currentLang = document.documentElement.lang || 'en';
            const response = await fetch(processorBase + 'contact-processor.php?action=captcha&lang=' + currentLang);
            if (response.ok) {
                const data = await response.json();
                const originalText = captchaLabel.getAttribute('data-original') || captchaLabel.textContent;
                if (!captchaLabel.getAttribute('data-original')) {
                    captchaLabel.setAttribute('data-original', originalText);
                }
                captchaLabel.textContent = `${originalText} (${data.challenge})`;
                captchaChallenge.value = data.challenge;
                captchaSignature = data.signature;
                captchaResponse.value = '';
            }
        } catch (err) {
            console.error('Error loading captcha:', err);
        }
    }

    // Initial load of Captcha
    loadCaptcha();

    // Helper to display error messages
    function showMsg(message, isSuccess = false) {
        msgBox.className = isSuccess 
            ? 'p-4 rounded-2xl text-sm font-medium mb-6 bg-emerald-50 text-emerald-800 border border-emerald-200' 
            : 'p-4 rounded-2xl text-sm font-medium mb-6 bg-rose-50 text-rose-800 border border-rose-200';
        msgBox.textContent = message;
        msgBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    // 4. Form Submit Handler
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (isRegistryCompleted) {
            return;
        }

        // Clear messages
        msgBox.className = 'hidden';
        msgBox.textContent = '';

        // Re-evaluate minor presence before validation
        const hasMinorOccupant = evaluateMinorPresence();

        // Front-end Validation (check visible inputs)
        const visibleInputs = form.querySelectorAll('input:not([disabled]):not([type="hidden"]), select:not([disabled])');
        let isValid = true;
        let missingPrivacyConsent = false;
        let missingMinorConsent = false;
        visibleInputs.forEach(input => {
            const isInvalid = input.type === 'checkbox'
                ? (input.hasAttribute('required') && !input.checked)
                : (input.hasAttribute('required') && !input.value.trim());
            if (isInvalid) {
                if (input.id === 'privacy-consent' || input.id === 'registry-privacy-consent' || input.name === 'privacy_consent') {
                    missingPrivacyConsent = true;
                } else if (input.id === 'minor-guardian-consent' || input.name === 'minor_guardian_consent') {
                    missingMinorConsent = true;
                } else {
                    isValid = false;
                }
                input.classList.add('border-red-400');
                const evtName = input.type === 'checkbox' ? 'change' : 'input';
                input.addEventListener(evtName, function removeRed() {
                    input.classList.remove('border-red-400');
                    input.removeEventListener(evtName, removeRed);
                });
            }
        });

        if (!isValid) {
            showMsg(defaultErrorMsg, false);
            return;
        }

        // Validate Primary Guest Email (guest_email_1)
        const emailInput = document.getElementById('guest-email-1');
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailInput || !emailInput.value.trim() || !emailRegex.test(emailInput.value.trim())) {
            if (emailInput) {
                emailInput.classList.add('border-red-400');
                emailInput.addEventListener('input', function removeRed() {
                    emailInput.classList.remove('border-red-400');
                    emailInput.removeEventListener('input', removeRed);
                });
            }
            showMsg(msgErrEmail, false);
            return;
        }

        // Validate Primary Guest Phone (guest_phone_1)
        const phoneInput = document.getElementById('guest-phone-1');
        if (!phoneInput || !phoneInput.value.trim() || phoneInput.value.trim().length < 6) {
            if (phoneInput) {
                phoneInput.classList.add('border-red-400');
                phoneInput.addEventListener('input', function removeRed() {
                    phoneInput.classList.remove('border-red-400');
                    phoneInput.removeEventListener('input', removeRed);
                });
            }
            showMsg(msgErrPhone || defaultErrorMsg, false);
            return;
        }

        // Validate Companion Phones if provided (optional, but if filled must be >= 6 chars)
        for (let num = 2; num <= currentGuestCount; num++) {
            const cPhone = document.getElementById(`guest-phone-${num}`);
            if (cPhone && cPhone.value.trim() && cPhone.value.trim().length < 6) {
                cPhone.classList.add('border-red-400');
                cPhone.addEventListener('input', function removeRed() {
                    cPhone.classList.remove('border-red-400');
                    cPhone.removeEventListener('input', removeRed);
                });
                showMsg(msgErrPhone || defaultErrorMsg, false);
                return;
            }
        }

        // Validate Personal Data Authorization (Ley 1581 of 2012)
        if (missingPrivacyConsent || !privacyConsentCheckbox || !privacyConsentCheckbox.checked) {
            showMsg(msgErrPrivacyConsent || defaultErrorMsg, false);
            return;
        }

        // Validate Minor Guardian Attestation when any active occupant is a minor (Ley 1581 Art. 7 & Ley 679 of 2001)
        if (missingMinorConsent || (hasMinorOccupant && (!minorGuardianCheckbox || !minorGuardianCheckbox.checked))) {
            showMsg(msgErrMinorConsent || defaultErrorMsg, false);
            return;
        }

        // Backwards compatibility: populate hidden guest_name_${num}
        for (let num = 1; num <= currentGuestCount; num++) {
            const fn = document.getElementById(`guest-first-name-${num}`);
            const ln = document.getElementById(`guest-last-name-${num}`);
            const legacyName = document.getElementById(`guest-name-${num}`);
            if (fn && ln && legacyName) {
                legacyName.value = `${fn.value.trim()} ${ln.value.trim()}`.trim();
            }
        }

        // Prepare Form Data (encoded as application/x-www-form-urlencoded to avoid multipart/form-data)
        const formData = new URLSearchParams(new FormData(form));
        formData.append('lang', document.documentElement.lang || 'en');
        formData.append('captcha_signature', captchaSignature);

        // Submitting State
        btnSubmit.disabled = true;
        txtSubmit.textContent = submittingMsg;
        btnSubmit.classList.add('opacity-75', 'cursor-not-allowed');

        try {
            const actionUrl = form.getAttribute('action') || 'api/registry-processor.php';
            const response = await fetch(actionUrl, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const result = await response.json();

            if (response.ok && result.success) {
                // Mark registration as completed for this specific reservation (property, check-in, check-out)
                try {
                    const cleanPropNo = propertyVal.replace(/\D/g, '');
                    const checkInParam = urlParams.get('check_in') || urlParams.get('checkin') || 'unspecified';
                    const checkOutParam = urlParams.get('check_out') || urlParams.get('checkout') || 'unspecified';
                    const stayKey = `stay_reg_${cleanPropNo || '1606'}_${checkInParam.replace(/\s+/g, '_')}_${checkOutParam.replace(/\s+/g, '_')}`;
                    localStorage.setItem(stayKey, 'completed');

                    const effectiveCode = result.reservation_code || reservationCodeVal;
                    if (effectiveCode) {
                        localStorage.setItem(`stay_reg_code_${effectiveCode}`, 'completed');
                    }
                } catch (err) {
                    console.error('Error saving stay registration status:', err);
                }

                // Update Unlocked Guide button URL if provided
                if (btnUnlockedGuide) {
                    if (result.guide_url) {
                        btnUnlockedGuide.href = result.guide_url;
                    } else {
                        const effectiveCode = result.reservation_code || reservationCodeVal;
                        const pageName = lang === 'en' ? 'index.html' : `${lang}.html`;
                        const guideBase = `guide/${pageName}`;
                        const query = effectiveCode ? `?code=${encodeURIComponent(effectiveCode)}` : '';
                        btnUnlockedGuide.href = `${guideBase}${query}`;
                    }
                }

                // Success: Hide form and show success message
                form.classList.add('opacity-0');
                setTimeout(() => {
                    form.classList.add('hidden');
                    successOverlay.classList.remove('hidden');
                    successOverlay.classList.add('animate-fade-in');
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }, 300);

                // Send a custom Matomo conversion event if available
                if (window._paq) {
                    window._paq.push(['trackEvent', 'Guest Registry', 'Submission Success', `Property: ${propertyVal}, Guests: ${currentGuestCount}`]);
                    window._paq.push(['trackGoal', 5]); // Registry Completed Goal
                }
            } else {
                const errorVal = result.message || defaultErrorMsg;
                showMsg(errorVal, false);

                if (window._paq) {
                    window._paq.push(['trackEvent', 'Guest Registry', 'Submission Failure', errorVal]);
                }

                // Reset submit button state
                btnSubmit.disabled = false;
                txtSubmit.textContent = btnSubmit.getAttribute('data-original-text') || t('registrySubmit', {}, 'Complete Guest Registration');
                btnSubmit.classList.remove('opacity-75', 'cursor-not-allowed');
                loadCaptcha();
            }
        } catch (err) {
            console.error('Error submitting form:', err);
            showMsg(defaultErrorMsg, false);

            btnSubmit.disabled = false;
            txtSubmit.textContent = btnSubmit.getAttribute('data-original-text') || t('registrySubmit', {}, 'Complete Guest Registration');
            btnSubmit.classList.remove('opacity-75', 'cursor-not-allowed');
            loadCaptcha();
        }
    });

    // Store original submit button text
    btnSubmit.setAttribute('data-original-text', txtSubmit.textContent);
});
