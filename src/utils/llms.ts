import {
	BUILDING_LOCATION,
	BUILDING_REGISTRATION_FEE_COP,
	DATA_CONTROLLER,
	getBusinessSameAsUrls,
	getCanonicalUrl,
	getPropertySameAsUrls,
	LANGUAGES,
	PLAYA_SALGUERO_LANDMARKS,
	PRIVACY_POLICY_VERSION,
	PROPERTY_CLEANING_FEES_COP,
	type PropertyId,
} from "../constants/config";
import {
	buildGuidePricingTokens,
	DESTINATION_GUIDES,
	DESTINATION_GUIDES_HUB_COPY,
	formatCopCurrencyForLocale,
	interpolateGuideText,
} from "../i18n/destinationGuides";
import { dict } from "../i18n/dict";
import type {
	Lang,
	PortfolioPricingSummary,
	PropertyPricingSummary,
	SeasonalRateTierRow,
} from "../types";
import { DEFAULT_PRICING_SUMMARY } from "./schema";

const LANGUAGE_LABELS: Record<Lang, string> = {
	en: "English (EN)",
	es: "Español (ES)",
	fr: "Français (FR)",
	it: "Italiano (IT)",
	de: "Deutsch (DE)",
	ja: "日本語 (JA)",
};

interface LlmsPropertyMetadata {
	readonly id: PropertyId;
	readonly name: string;
	readonly pagePath: "Oceanview1707" | "Oceanview1606";
	readonly floorLevel: string;
	readonly focus: string;
	readonly rnt: string;
	readonly parkingBay: string;
	readonly bedsSummary: string;
	readonly layoutDetail: string;
	readonly highlightSummary: string;
}

const LLMS_PROPERTY_SPECS: Record<PropertyId, LlmsPropertyMetadata> = {
	"1707": {
		id: "1707",
		name: "OceanViewFlats 1707",
		pagePath: "Oceanview1707",
		floorLevel: "17th Floor",
		focus: "Digital Nomads, Remote Workers & Families",
		rnt: DATA_CONTROLLER.rnt1707,
		parkingBay: "Bay 95",
		bedsSummary:
			"2 Bedrooms, 4 Beds (Master Bedroom: 1 Queen Bed; Guest Bedroom: 2 Twin/Single Beds; Living Room: 1 Pull-out Sofa Bed)",
		layoutDetail:
			"2 Bedrooms, 4 Beds (Master Bedroom: 1 Queen Bed; Guest Bedroom: 2 Twin/Single Beds; Living Room: 1 Pull-out Sofa Bed), 2 Bathrooms, private ocean-view balcony, dedicated laptop-friendly workspace, high-speed fiber Wi-Fi (up to 900 Mbps), air conditioning, full kitchen, washer/dryer laundry, smart-lock self check-in.",
		highlightSummary:
			"17th-floor elevation with up to 900 Mbps symmetrical fiber Wi-Fi, an ergonomic laptop-friendly workspace, and a quiet ocean-view balcony suited to video calls, remote professionals, and families.",
	},
	"1606": {
		id: "1606",
		name: "OceanViewFlats 1606",
		pagePath: "Oceanview1606",
		floorLevel: "16th Floor",
		focus: "Couples, Families & Romantic Coastal Escapes",
		rnt: DATA_CONTROLLER.rnt1606,
		parkingBay: "Bay 87",
		bedsSummary:
			"2 Bedrooms, 3 Beds (Master Bedroom: 1 Queen Bed; Guest Bedroom: 1 Double Bed; Living Room: 1 Pull-out Sofa Bed)",
		layoutDetail:
			"2 Bedrooms, 3 Beds (Master Bedroom: 1 Queen Bed; Guest Bedroom: 1 Double Bed; Living Room: 1 Pull-out Sofa Bed), 2 Bathrooms, private Caribbean sunset terrace, high-speed fiber Wi-Fi (up to 900 Mbps), air conditioning, full kitchen, washer/dryer laundry, smart-lock self check-in.",
		highlightSummary:
			"16th-floor beachfront residence one block from Playa Salguero beach, pairing a warm coastal design and private Caribbean sunset terrace with up to 900 Mbps fiber Wi-Fi for couples and families.",
	},
};

const normalizeBaseUrl = (baseUrl: string): string =>
	baseUrl.replace(/\/+$/, "");

const getPublicPagePaths = (): string[] => [
	"",
	"Oceanview1707",
	"Oceanview1606",
	"santa-marta",
	...DESTINATION_GUIDES.map((guide) => `santa-marta/${guide.slug}`),
	"contact",
	"privacy",
];

const formatRelativePublicPath = (pagePath: string, lang: Lang): string => {
	const cleanPath = pagePath.replace(/^\/+|\/+$/g, "");
	if (!cleanPath) {
		return lang === "en" ? "/" : `/${lang}.html`;
	}
	return lang === "en" ? `/${cleanPath}/` : `/${cleanPath}/${lang}.html`;
};

const formatTierDescriptor = (
	tier: SeasonalRateTierRow,
	lowPrice: number,
): string => {
	const rateLabel = formatCopCurrencyForLocale(tier.nightly_rate_cop, "en");
	const tierKind =
		tier.nightly_rate_cop > lowPrice ? "peak Seasonal Rate Tier" : "baseline";
	return `\`${rateLabel}\` / night ${tierKind} (${tier.minimum_stay}-night minimum stay, ${tier.start_date} to ${tier.end_date})`;
};

const formatPropertyRatesLine = (
	propPricing: PropertyPricingSummary,
): string => {
	const cleaningFee = formatCopCurrencyForLocale(
		propPricing.cleaningFeeCop ||
			PROPERTY_CLEANING_FEES_COP[propPricing.propertyId],
		"en",
	);
	const tierParts = propPricing.tiers.map((tier) =>
		formatTierDescriptor(tier, propPricing.lowPrice),
	);
	return `${tierParts.join("; ")}; flat \`${cleaningFee}\` Cleaning Fee`;
};

const formatCompactPropertyRateCell = (
	propPricing: PropertyPricingSummary,
): string =>
	propPricing.tiers
		.map((tier) => {
			const rate = formatCopCurrencyForLocale(tier.nightly_rate_cop, "en");
			const tag =
				tier.nightly_rate_cop > propPricing.lowPrice ? "peak" : "base";
			return `${rate}/night (${tag}, ${tier.minimum_stay}-night min, ${tier.start_date}..${tier.end_date})`;
		})
		.join("; ");

const buildCoreLlmsSections = (
	baseUrl: string,
	pricingSummary: PortfolioPricingSummary,
	options: { includeFullLink: boolean },
): string => {
	const normalizedBase = normalizeBaseUrl(baseUrl);
	const homeUrl = getCanonicalUrl(normalizedBase, "", "en");
	const contactUrl = getCanonicalUrl(normalizedBase, "contact", "en");
	const privacyUrl = getCanonicalUrl(normalizedBase, "privacy", "en");
	const guidesHubUrl = getCanonicalUrl(normalizedBase, "santa-marta", "en");
	const llmsFullUrl = `${normalizedBase}/llms-full.txt`;
	const publicPagePaths = getPublicPagePaths();
	const tokens = buildGuidePricingTokens(pricingSummary, "en");
	const buildingRegistrationFeeLabel = formatCopCurrencyForLocale(
		BUILDING_REGISTRATION_FEE_COP,
		"en",
	);

	const languageLines = LANGUAGES.map((lang) => {
		const paths = publicPagePaths
			.map((pagePath) => {
				const rel = formatRelativePublicPath(pagePath, lang);
				return lang === "en" && pagePath === ""
					? `\`${rel}\` (default)`
					: `\`${rel}\``;
			})
			.join(", ");
		return `- **${LANGUAGE_LABELS[lang]}**: ${paths}`;
	});

	const propertyBlocks = (["1707", "1606"] as const).map((propertyId) => {
		const spec = LLMS_PROPERTY_SPECS[propertyId];
		const propUrl = getCanonicalUrl(normalizedBase, spec.pagePath, "en");
		const propPricing = pricingSummary.properties[propertyId];
		const sameAsUrls = getPropertySameAsUrls(propertyId);
		const externalLinks = sameAsUrls
			.map((url) => `[${url}](${url})`)
			.join(", ");

		return [
			`### [${spec.name}](${propUrl})`,
			`- **Floor Level**: ${spec.floorLevel} (${BUILDING_LOCATION.name})`,
			`- **Focus**: ${spec.focus}`,
			"- **Maximum Occupancy**: Up to 6 Guests",
			`- **National Tourism Registry (RNT)**: ${spec.rnt}`,
			`- **Assigned Parking Space**: ${spec.parkingBay} (included)`,
			`- **Seasonal Rate Tiers, Nightly Rate & Cleaning Fee**: ${formatPropertyRatesLine(propPricing)}`,
			`- **Layout**: ${spec.layoutDetail}`,
			...(externalLinks
				? [`- **External OTA Channel (Optional)**: ${externalLinks}`]
				: []),
		].join("\n");
	});

	const guideIndexLines = DESTINATION_GUIDES.map((guide) => {
		const locale = guide.locales.en;
		const url = getCanonicalUrl(
			normalizedBase,
			`santa-marta/${guide.slug}`,
			"en",
		);
		const title = interpolateGuideText(locale.title, tokens);
		const summary = interpolateGuideText(locale.cardSummary, tokens);
		return `- [${title}](${url}): ${summary}`;
	});

	const landmarkLines = PLAYA_SALGUERO_LANDMARKS.map(
		(landmark) =>
			`- **${dict.en[landmark.nameKey]}**: ${landmark.distanceApprox}, ${landmark.travelTimeApprox}`,
	);

	const businessSameAsUrls = getBusinessSameAsUrls();
	const extraEntityLinks = businessSameAsUrls.filter(
		(url) =>
			!getPropertySameAsUrls("1707").includes(url) &&
			!getPropertySameAsUrls("1606").includes(url),
	);

	const introLines = [
		"# OceanViewFlats",
		"",
		`OceanViewFlats offers beachfront short-term rental Properties in ${BUILDING_LOCATION.neighborhood}, ${BUILDING_LOCATION.city}, ${BUILDING_LOCATION.region}, ${BUILDING_LOCATION.country} (\`${homeUrl}\`). The platform is a fast, multilingual static site with an authoritative Direct Reservation engine settling in Colombian Pesos (\`COP\`) across 6 languages. Portfolio Nightly Rate range: \`${pricingSummary.priceRange}\`.`,
	];

	if (options.includeFullLink) {
		introLines.push(
			"",
			`- **Comprehensive Brief**: [Full Machine-Readable Documentation (${llmsFullUrl})](${llmsFullUrl})`,
		);
	}

	return [
		introLines.join("\n"),
		"",
		"---",
		"",
		"## Global Multilingual Support",
		"",
		"All public pages (Home, Properties, Destination Guides, Contact, and Data Processing Policy) are available in 6 languages:",
		...languageLines,
		"",
		"---",
		"",
		"## Properties",
		"",
		...propertyBlocks.flatMap((block, idx) =>
			idx === 0 ? [block] : ["", block],
		),
		"",
		"---",
		"",
		"## Santa Marta Destination Guides",
		"",
		`Public, multilingual pre-booking editorial resources published at [${DESTINATION_GUIDES_HUB_COPY.en.heroTitle}](${guidesHubUrl}) (distinct from the private post-registration **Guest Guide**):`,
		"",
		...guideIndexLines,
		"",
		"---",
		"",
		"## Direct Reservations, Check-In & Regulatory Compliance",
		"",
		"- **Settlement Currency**: All Quotes and Direct Reservations (Credit/Debit Card, PSE bank transfer, or Cash Voucher / Efecty via Mercado Pago) settle in Colombian Pesos (`COP`) with 0% OTA commission, saving 15–20% over third-party OTA fees.",
		"- **Check-in / Check-out**: Check-in begins at `15:00` (3:00 PM) and Check-out is by `11:00` (11:00 AM), with smart-lock self check-in. Pets are not permitted.",
		"- **Cancellation Policy**: 100% refund 14 or more days before Check-in Date; 50% refund (50% Policy Retention) 7–13 days prior; non-refundable within 7 days.",
		"- **Mandatory Guest Registry**: Per Colombian hospitality regulations (Law 2068 of 2020 / TRA-SIRE) and Edificio Salguero Sunset security rules, all staying Guests must register identification details (with Law 1581 **Personal Data Authorization** and Law 679 **Minor Guardian Attestation** for Guests under 18) prior to arrival. Submission immediately unlocks the private digital **Guest Guide**, **Assigned Parking Space** clearance, and temporary `0XXXXXX#` smart-lock **Access Credentials**.",
		`- **Building Registration Fee**: ${BUILDING_LOCATION.name} condominium administration assesses a mandatory statutory fee of \`${buildingRegistrationFeeLabel}\` per person payable directly at the front-desk reception upon arrival for security wristbands and common areas.`,
		`- **Pool Maintenance Schedule**: Building swimming pools at ${BUILDING_LOCATION.name} are closed every Tuesday for scheduled maintenance.`,
		`- **Data Processing Policy**: Personal data is processed by Data Controller ${DATA_CONTROLLER.name} under Colombian Statutory Law 1581 of 2012 (*Política de Tratamiento de Datos Personales*, Version \`${PRIVACY_POLICY_VERSION}\`, [${privacyUrl}](${privacyUrl})).`,
		"",
		"---",
		"",
		"## Location, Playa Salguero Proximity & Santa Marta Landmarks",
		"",
		`- **Building & Address**: ${BUILDING_LOCATION.name}, ${BUILDING_LOCATION.streetAddress}, ${BUILDING_LOCATION.neighborhood}, ${BUILDING_LOCATION.city}, ${BUILDING_LOCATION.region} ${BUILDING_LOCATION.postalCode}, ${BUILDING_LOCATION.country} (\`${BUILDING_LOCATION.latitude}, ${BUILDING_LOCATION.longitude}\`)`,
		...landmarkLines,
		"",
		"---",
		"",
		"## Host & Contact",
		"",
		`- **Host / Data Controller**: ${DATA_CONTROLLER.name} (${DATA_CONTROLLER.rntCombined})`,
		`- **Email**: [${DATA_CONTROLLER.email}](mailto:${DATA_CONTROLLER.email})`,
		`- **WhatsApp / Phone**: [${DATA_CONTROLLER.phone}](${DATA_CONTROLLER.whatsappHref})`,
		`- **Contact Page**: [${contactUrl}](${contactUrl})`,
		`- **Data Processing Policy**: [${privacyUrl}](${privacyUrl})`,
		`- **Destination Guides Hub**: [${guidesHubUrl}](${guidesHubUrl})`,
		...(extraEntityLinks.length > 0
			? [
					`- **Verified External Profiles**: ${extraEntityLinks.map((url) => `[${url}](${url})`).join(", ")}`,
				]
			: []),
	].join("\n");
};

const buildComparisonMatrixSection = (
	pricingSummary: PortfolioPricingSummary,
): string => {
	const p1707 = pricingSummary.properties["1707"];
	const p1606 = pricingSummary.properties["1606"];
	const cleaning1707 = formatCopCurrencyForLocale(
		p1707.cleaningFeeCop || PROPERTY_CLEANING_FEES_COP["1707"],
		"en",
	);
	const cleaning1606 = formatCopCurrencyForLocale(
		p1606.cleaningFeeCop || PROPERTY_CLEANING_FEES_COP["1606"],
		"en",
	);
	const buildingRegistrationFee = formatCopCurrencyForLocale(
		BUILDING_REGISTRATION_FEE_COP,
		"en",
	);

	return [
		"## Reconciled Property Comparison Matrix",
		"",
		"| Specification | OceanViewFlats 1707 | OceanViewFlats 1606 |",
		"| --- | --- | --- |",
		`| Floor Level | ${LLMS_PROPERTY_SPECS["1707"].floorLevel} | ${LLMS_PROPERTY_SPECS["1606"].floorLevel} |`,
		`| National Tourism Registry (RNT) | ${DATA_CONTROLLER.rnt1707} | ${DATA_CONTROLLER.rnt1606} |`,
		`| Ideal Guest Profile | ${LLMS_PROPERTY_SPECS["1707"].focus} | ${LLMS_PROPERTY_SPECS["1606"].focus} |`,
		`| Seasonal Rate Tiers (COP) | ${formatCompactPropertyRateCell(p1707)} | ${formatCompactPropertyRateCell(p1606)} |`,
		`| Flat Cleaning Fee (COP) | ${cleaning1707} | ${cleaning1606} |`,
		`| Bedrooms & Beds | ${LLMS_PROPERTY_SPECS["1707"].bedsSummary} | ${LLMS_PROPERTY_SPECS["1606"].bedsSummary} |`,
		"| Bathrooms | 2 Full Bathrooms | 2 Full Bathrooms |",
		"| Maximum Occupancy | 6 Guests | 6 Guests |",
		"| Fiber Wi-Fi | Up to 900 Mbps Fiber | Up to 900 Mbps Fiber |",
		`| Assigned Parking Space | ${LLMS_PROPERTY_SPECS["1707"].parkingBay} (included) | ${LLMS_PROPERTY_SPECS["1606"].parkingBay} (included) |`,
		"| Check-in / Check-out | 15:00 / 11:00 (Smart-lock self check-in) | 15:00 / 11:00 (Smart-lock self check-in) |",
		`| Building Registration Fee | ${buildingRegistrationFee} per person at reception | ${buildingRegistrationFee} per person at reception |`,
		"| Pool Maintenance Schedule | Closed Tuesdays for maintenance | Closed Tuesdays for maintenance |",
		"| Pets Policy | Not permitted | Not permitted |",
		"",
		"Multi-property group stays of 7–12 Guests can reserve both Property 1707 (17th floor) and Property 1606 (16th floor) together, one floor apart inside Edificio Salguero Sunset.",
	].join("\n");
};

const buildDestinationGuidesFullSection = (
	normalizedBase: string,
	pricingSummary: PortfolioPricingSummary,
): string => {
	const tokens = buildGuidePricingTokens(pricingSummary, "en");
	const hubUrl = getCanonicalUrl(normalizedBase, "santa-marta", "en");

	const guideBlocks = DESTINATION_GUIDES.map((guide) => {
		const locale = guide.locales.en;
		const url = getCanonicalUrl(
			normalizedBase,
			`santa-marta/${guide.slug}`,
			"en",
		);
		const title = interpolateGuideText(locale.title, tokens);
		const subtitle = interpolateGuideText(locale.subtitle, tokens);
		const summary = interpolateGuideText(locale.cardSummary, tokens);
		const relatedProps = guide.relatedProperties
			.map((propId) => `Property ${propId}`)
			.join(", ");

		const lines: string[] = [
			`### [${title}](${url})`,
			`- **Category**: ${locale.badge}`,
			`- **Published / Updated**: ${guide.datePublished} / ${guide.dateModified} (${guide.readingTimeMinutes} min read)`,
			`- **Related Properties**: ${relatedProps}`,
			`- **Summary**: ${subtitle} ${summary}`,
			"",
			`#### ${interpolateGuideText(locale.keyTakeawaysTitle, tokens)}`,
			...locale.keyTakeaways.map(
				(item) => `- ${interpolateGuideText(item, tokens)}`,
			),
		];

		for (const section of locale.sections) {
			lines.push("", `#### ${interpolateGuideText(section.heading, tokens)}`);
			for (const paragraph of section.paragraphs) {
				lines.push("", interpolateGuideText(paragraph, tokens));
			}
			if (section.bullets && section.bullets.length > 0) {
				lines.push("");
				for (const bullet of section.bullets) {
					lines.push(`- ${interpolateGuideText(bullet, tokens)}`);
				}
			}
			if (section.factCards && section.factCards.length > 0) {
				lines.push("");
				for (const card of section.factCards) {
					const label = interpolateGuideText(card.label, tokens);
					const value = interpolateGuideText(card.value, tokens);
					const detail = card.detail
						? ` — ${interpolateGuideText(card.detail, tokens)}`
						: "";
					lines.push(`- **${label}**: ${value}${detail}`);
				}
			}
			if (section.comparisonTable) {
				const table = section.comparisonTable;
				lines.push(
					"",
					`| ${table.headers.map((h) => interpolateGuideText(h, tokens)).join(" | ")} |`,
					`| ${table.headers.map(() => "---").join(" | ")} |`,
					...table.rows.map(
						(row) =>
							`| ${row.map((cell) => interpolateGuideText(cell, tokens)).join(" | ")} |`,
					),
				);
			}
			if (section.callout) {
				lines.push(
					"",
					`> **${interpolateGuideText(section.callout.label, tokens)}**: ${interpolateGuideText(section.callout.text, tokens)}`,
				);
			}
		}

		if (locale.faqs.length > 0) {
			lines.push("", `#### ${interpolateGuideText(locale.faqsTitle, tokens)}`);
			for (const faq of locale.faqs) {
				lines.push(
					`- **Q: ${interpolateGuideText(faq.question, tokens)}**`,
					`  A: ${interpolateGuideText(faq.answer, tokens)}`,
				);
			}
		}

		return lines.join("\n");
	});

	return [
		"## Santa Marta Destination Guides (Full English Reference)",
		"",
		`Hub URL: [${hubUrl}](${hubUrl}) — ${DESTINATION_GUIDES_HUB_COPY.en.introBody}`,
		"",
		...guideBlocks.flatMap((block, idx) => (idx === 0 ? [block] : ["", block])),
	].join("\n");
};

const buildPropertyFaqsSection = (
	pricingSummary: PortfolioPricingSummary,
): string => {
	const buildingRegistrationFee = formatCopCurrencyForLocale(
		BUILDING_REGISTRATION_FEE_COP,
		"en",
	);

	const propertyFaqBlocks = (["1707", "1606"] as const).map((propertyId) => {
		const spec = LLMS_PROPERTY_SPECS[propertyId];
		const propPricing = pricingSummary.properties[propertyId];
		const ratesLine = formatPropertyRatesLine(propPricing);

		return [
			`### ${spec.name} — Frequently Asked Questions`,
			`- **Q: What makes Property ${propertyId} suited for my stay in Playa Salguero?**`,
			`  A: Located on the ${spec.floorLevel} of ${BUILDING_LOCATION.name} (${spec.rnt}), Property ${propertyId} offers ${spec.highlightSummary}`,
			`- **Q: What are the sleeping arrangements, bed count, and Assigned Parking Space for Property ${propertyId}?**`,
			`  A: Property ${propertyId} accommodates a Maximum Occupancy of 6 Guests across ${spec.bedsSummary}, plus 2 full bathrooms and complimentary Assigned Parking Space ${spec.parkingBay} inside ${BUILDING_LOCATION.name}.`,
			`- **Q: What are the Seasonal Rate Tiers, minimum stay, Cleaning Fee, and cancellation policy for a Direct Reservation at Property ${propertyId}?**`,
			`  A: Current published rates in Settlement Currency (COP) are ${ratesLine}. Direct Reservations settle in COP with 0% OTA commission via Credit/Debit Card, PSE bank transfer, or Cash Voucher (Efecty via Mercado Pago). Cancellation policy: 100% refund 14 or more days before Check-in Date; 50% refund (50% Policy Retention) 7–13 days prior; non-refundable within 7 days.`,
			`- **Q: What are the Check-in and Check-out times at Property ${propertyId}?**`,
			"  A: Check-in begins at 15:00 (3:00 PM) and Check-out is by 11:00 (11:00 AM). Smart-lock self check-in allows seamless arrival once your pre-arrival Guest Registry is verified.",
			`- **Q: How do the Guest Registry and 0XXXXXX# Access Credential unlock work for Property ${propertyId}?**`,
			`  A: Before arrival, the Primary Guest completes the mandatory online Guest Registry (including Colombian Law 1581 Personal Data Authorization and Law 679 Minor Guardian Attestation when traveling with minors). Submission triggers Condominium Clearance Sync and unlocks the private digital Guest Guide, Assigned Parking Space ${spec.parkingBay} clearance, and your temporary cryptographically random 0XXXXXX# smart-lock Access Credential.`,
			`- **Q: Is there a Building Registration Fee or pool closure schedule at ${BUILDING_LOCATION.name}?**`,
			`  A: Yes. ${BUILDING_LOCATION.name} front-desk reception collects a one-time statutory Building Registration Fee of ${buildingRegistrationFee} per person upon arrival for security wristbands and common areas. Building swimming pools are closed every Tuesday for scheduled maintenance.`,
		].join("\n");
	});

	return [
		"## Complete Property FAQs",
		"",
		...propertyFaqBlocks.flatMap((block, idx) =>
			idx === 0 ? [block] : ["", block],
		),
	].join("\n");
};

const buildCancellationAndComplianceSection = (
	normalizedBase: string,
): string => {
	const privacyUrl = getCanonicalUrl(normalizedBase, "privacy", "en");

	return [
		"## Cancellation Policy & Direct Reservation Settlement",
		"",
		"- **Authoritative Quotes in COP**: Every Direct Reservation Quote equals the itemized Nightly Rate for each Night (resolved from published Seasonal Rate Tiers) plus the Property's flat Cleaning Fee, settled strictly in Settlement Currency (`COP`) with 0% OTA commission.",
		"- **Payment Methods (Mercado Pago)**:",
		"  - **Credit / Debit Card (Instant Settlement)**: Confirms immediately into a Confirmed Reservation with a 30-minute Standard Hold Window during checkout.",
		"  - **PSE Colombian Bank Transfer (Asynchronous Settlement)**: Reserves calendar dates while bank webhook confirmation settles.",
		"  - **Cash Voucher via Efecty (Asynchronous Settlement)**: Holds calendar dates for the 72-hour Voucher Hold Window while cash settlement completes.",
		"  - All checkouts enforce client-generated Idempotency Keys to prevent duplicate charges.",
		"- **Cancellation Policy & Gateway Refunds**:",
		"  - **14 or more days before Check-in Date**: 100% refund of the Refundable Balance (0% Policy Retention).",
		"  - **7 to 13 days before Check-in Date**: 50% refund of the Refundable Balance (50% Policy Retention).",
		"  - **Fewer than 7 days before Check-in Date**: Non-refundable (100% Policy Retention).",
		"  - Approved Gateway Refunds are returned to the Primary Guest's original Payment Method through Mercado Pago.",
		"",
		"---",
		"",
		"## Colombian Regulatory & Data Protection Compliance Summary",
		"",
		`- **Data Controller**: ${DATA_CONTROLLER.name} (${DATA_CONTROLLER.rntCombined}), physical domicile ${DATA_CONTROLLER.address}, Habeas Data contact [${DATA_CONTROLLER.email}](mailto:${DATA_CONTROLLER.email}).`,
		`- **Colombian Statutory Law 1581 of 2012 & Decree 1377 of 2013 (Habeas Data)**: Governed by the published Data Processing Policy (*Política de Tratamiento de Datos Personales*, Version \`${PRIVACY_POLICY_VERSION}\`, [${privacyUrl}](${privacyUrl})). Prior, express, and informed **Personal Data Authorization** is recorded with timestamp, policy version, and client IP. Data Subjects exercise free ARCO rights (Know, Update, Rectify, Suppress) via ${DATA_CONTROLLER.email}: Consultations (*Consultas*, Art. 14) are answered within ${DATA_CONTROLLER.consultaSlaBusinessDays} business days; Claims (*Reclamos*, Art. 15) are resolved within ${DATA_CONTROLLER.reclamoSlaBusinessDays} business days.`,
		`- **Encryption & PII Redaction**: Guest Registry payloads are encrypted at rest using authenticated symmetric cryptography (XSalsa20-Poly1305), PII is masked across secondary logs and notification emails, smart-lock Access Credentials use cryptographically random \`0XXXXXX#\` digits, and personal identity records for Concluded Reservations undergo automatic **PII Redaction** ${DATA_CONTROLLER.retentionDaysAfterCheckout} days after Check-out Date.`,
		`- **Colombian Law 2068 of 2020, Law 1558 of 2012 & Decree 2590 of 2009 (Tourism & TRA-SIRE)**: Both Properties maintain active Registro Nacional de Turismo registrations (${DATA_CONTROLLER.rnt1707} for Property 1707; ${DATA_CONTROLLER.rnt1606} for Property 1606). Every Confirmed Reservation requires pre-arrival **Guest Registry** completion and **Condominium Clearance Sync** with the **Condominium Administration Portal** at ${BUILDING_LOCATION.name} before private **Guest Guide** and **Access Credential** release.`,
		"- **Colombian Law 679 of 2001 & Law 1336 of 2009 (ESCNNA Prevention)**: Zero tolerance for sexual exploitation and commercial abuse of children and adolescents in tourism. Any Guest Registry including a Guest under 18 years of age or bearing a minor identification document (`Tarjeta de Identidad`, `Registro Civil`) requires an explicit **Minor Guardian Attestation** confirming parental authority (*patria potestad*) or written legal representation.",
	].join("\n");
};

export const buildLlmsTxt = (
	baseUrl: string,
	pricingSummary: PortfolioPricingSummary = DEFAULT_PRICING_SUMMARY,
): string =>
	`${buildCoreLlmsSections(baseUrl, pricingSummary, {
		includeFullLink: true,
	})}\n`;

export const buildLlmsFullTxt = (
	baseUrl: string,
	pricingSummary: PortfolioPricingSummary = DEFAULT_PRICING_SUMMARY,
): string => {
	const normalizedBase = normalizeBaseUrl(baseUrl);
	return `${[
		buildCoreLlmsSections(normalizedBase, pricingSummary, {
			includeFullLink: false,
		}),
		"---",
		buildComparisonMatrixSection(pricingSummary),
		"---",
		buildDestinationGuidesFullSection(normalizedBase, pricingSummary),
		"---",
		buildPropertyFaqsSection(pricingSummary),
		"---",
		buildCancellationAndComplianceSection(normalizedBase),
	].join("\n\n")}\n`;
};
