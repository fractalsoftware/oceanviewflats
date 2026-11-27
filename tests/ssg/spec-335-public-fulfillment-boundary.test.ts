import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import test from "node:test";
import { pages } from "../../src/config/pages";
import {
	getGuidePagePath,
	getRegistryPagePath,
	HOME_FAQ_ITEMS,
	LANGUAGES,
} from "../../src/constants/config";
import { HOME_THEME } from "../../src/constants/theme";
import { DESTINATION_GUIDES } from "../../src/i18n/destinationGuides";
import { dict } from "../../src/i18n/dict";

const PRIVATE_FULFILLMENT_HREF_RE = /(?:^|\/)(?:registry|guide)(?:\/|$|\?|#)/i;

const REMOVED_PUBLIC_FULFILLMENT_IDS = [
	"nav-checkin-group",
	"nav-checkin-toggle",
	"nav-checkin-menu",
	"nav-checkin-registry",
	"nav-checkin-guide",
	"footer-registry-link",
	"footer-guide-link",
	"hero-returning-guest-cue",
	"hero-returning-registry-link",
	"hero-returning-guide-link",
	"faq-fulfillment-links",
	"faq-registry-link",
	"faq-guide-link",
] as const;

const extractAnchorHrefs = (html: string): string[] => {
	const hrefs: string[] = [];
	for (const match of html.matchAll(
		/<a\b[^>]*\bhref\s*=\s*(?:"([^"]*)"|'([^']*)')/gi,
	)) {
		hrefs.push(match[1] ?? match[2] ?? "");
	}
	return hrefs;
};

const findPrivateFulfillmentHrefs = (html: string): string[] =>
	extractAnchorHrefs(html).filter((href) =>
		PRIVATE_FULFILLMENT_HREF_RE.test(href),
	);

test("#335 #337 Zero public browsing pages across all 6 locales and URL depths emit <a href> links to /registry or /guide or any removed fulfillment markup", () => {
	const distDir = path.join(process.cwd(), "dist");
	const transactionalFulfillmentIds = new Set([
		"booking-success",
		"registry",
		"guide",
	]);

	// Verify all 11 public browsing page families (+ booking-failure & booking-pending) are registered in pages config
	const expectedPublicBrowsingIds = [
		"home",
		"Oceanview1707",
		"Oceanview1606",
		"destination-guides",
		...DESTINATION_GUIDES.map((guide) => `destination-guide-${guide.slug}`),
		"contact",
		"privacy",
		"404",
	];
	const nonFulfillmentPages = pages.filter(
		(page) => !transactionalFulfillmentIds.has(page.id),
	);

	for (const expectedId of expectedPublicBrowsingIds) {
		assert.ok(
			nonFulfillmentPages.some((page) => page.id === expectedId),
			`Expected public browsing page "${expectedId}" to be present in SSG page config`,
		);
	}

	let checkedFileCount = 0;
	for (const page of nonFulfillmentPages) {
		for (const lang of LANGUAGES) {
			const relFile = page.filename(lang);
			const fullPath = path.join(distDir, relFile);
			assert.ok(
				fs.existsSync(fullPath),
				`Compiled HTML artifact must exist for ${relFile}`,
			);
			const html = fs.readFileSync(fullPath, "utf-8");
			checkedFileCount++;

			const leakedHrefs = findPrivateFulfillmentHrefs(html);
			assert.deepEqual(
				leakedHrefs,
				[],
				`${relFile} must contain zero <a href> links pointing to /registry or /guide`,
			);

			for (const removedId of REMOVED_PUBLIC_FULFILLMENT_IDS) {
				assert.ok(
					!html.includes(`id="${removedId}"`),
					`${relFile} must not contain removed fulfillment element #${removedId}`,
				);
			}
		}
	}

	assert.ok(
		checkedFileCount >= expectedPublicBrowsingIds.length * LANGUAGES.length,
		`Expected to verify at least ${expectedPublicBrowsingIds.length * LANGUAGES.length} compiled public HTML files, checked ${checkedFileCount}`,
	);
});

test("#337 Homepage hero omits returning-Guest cue, FAQ #6 stays open as localized out-of-band prose across all 6 locales, and orphaned tokens/keys/config flags are removed", () => {
	const distDir = path.join(process.cwd(), "dist");

	// 1. Orphaned theme tokens, FAQ config flag, and 6-locale dictionary keys are removed
	const homeThemeRecord = HOME_THEME as Record<string, unknown>;
	for (const removedThemeKey of [
		"heroReturningGuestCue",
		"heroReturningGuestPrompt",
		"heroReturningGuestLinks",
		"heroReturningGuestLink",
		"heroReturningGuestDivider",
		"faqLinksRow",
		"faqActionLinkPrimary",
		"faqActionLinkSecondary",
	]) {
		assert.equal(
			homeThemeRecord[removedThemeKey],
			undefined,
			`HOME_THEME must omit orphaned token "${removedThemeKey}"`,
		);
	}

	for (const item of HOME_FAQ_ITEMS) {
		assert.equal(
			"showFulfillmentLinks" in item,
			false,
			`HOME_FAQ_ITEMS entry "${item.id}" must omit obsolete showFulfillmentLinks property`,
		);
	}
	const faq6Config = HOME_FAQ_ITEMS.find((item) => item.id === "faq-6");
	assert.ok(faq6Config, "HOME_FAQ_ITEMS must include faq-6");
	assert.equal(
		faq6Config.defaultOpen,
		true,
		"HOME_FAQ_ITEMS faq-6 must preserve defaultOpen: true",
	);

	for (const lang of LANGUAGES) {
		const dictRecord = dict[lang] as Record<string, unknown>;
		for (const removedDictKey of [
			"faq6RegistryLink",
			"faq6GuideLink",
			"homeHeroReturningGuestPrompt",
			"homeHeroReturningGuestRegistry",
			"homeHeroReturningGuestGuide",
		]) {
			assert.equal(
				dictRecord[removedDictKey],
				undefined,
				`dict.${lang} must omit unused translation key "${removedDictKey}"`,
			);
		}
	}

	// 2. Per-locale compiled Homepage verification (Hero, default-open prose-only FAQ #6, and FAQPage JSON-LD)
	const localeExpectations = [
		{
			lang: "en" as const,
			file: "index.html",
			confirmationPhrase: "Reservation Confirmation",
			dispatchPhrase: "External Guest Dispatch",
			contactPhrase: "Contact Us",
			poolDay: "Tuesday",
		},
		{
			lang: "es" as const,
			file: "es.html",
			confirmationPhrase: "Confirmación de Reserva",
			dispatchPhrase: "Envío para Huéspedes Externos",
			contactPhrase: "Contáctenos",
			poolDay: "martes",
		},
		{
			lang: "fr" as const,
			file: "fr.html",
			confirmationPhrase: "Confirmation de Réservation",
			dispatchPhrase: "Envoi d&#x27;Accueil Externe",
			contactPhrase: "Contactez-nous",
			poolDay: "mardis",
		},
		{
			lang: "it" as const,
			file: "it.html",
			confirmationPhrase: "Conferma di Prenotazione",
			dispatchPhrase: "Invio per Ospiti Esterni",
			contactPhrase: "Contattaci",
			poolDay: "martedì",
		},
		{
			lang: "de" as const,
			file: "de.html",
			confirmationPhrase: "Reservierungsbestätigung",
			dispatchPhrase: "Versand für externe Gäste",
			contactPhrase: "Kontaktieren Sie uns",
			poolDay: "Dienstag",
		},
		{
			lang: "ja" as const,
			file: "ja.html",
			confirmationPhrase: "予約確認メール",
			dispatchPhrase: "外部サイト予約者向け案内",
			contactPhrase: "お問い合わせ",
			poolDay: "火曜日",
		},
	];

	for (const locale of localeExpectations) {
		const html = fs.readFileSync(path.join(distDir, locale.file), "utf-8");

		// Hero contains no returning-Guest cue or /registry / /guide links
		const headerMatch = html.match(/<header[\s\S]*?<\/header>/);
		assert.ok(headerMatch, `${locale.file} must render Hero <header>`);
		assert.equal(
			findPrivateFulfillmentHrefs(headerMatch[0]).length,
			0,
			`${locale.file} Hero must contain zero links to /registry or /guide`,
		);
		assert.ok(
			!headerMatch[0].includes("hero-returning-guest-cue") &&
				!headerMatch[0].includes("hero-returning-registry-link") &&
				!headerMatch[0].includes("hero-returning-guide-link"),
			`${locale.file} Hero must omit returning-Guest cue markup`,
		);

		// FAQ #6 stays open by default, renders prose-only (zero links), and localizes out-of-band delivery + Contact Us recovery
		const faq6Match = html.match(
			/<details[^>]*id="faq-6"[^>]*>[\s\S]*?<\/details>/,
		);
		assert.ok(faq6Match, `${locale.file} must render #faq-6`);
		const faq6Html = faq6Match[0];
		const faq6OpenTag = faq6Html.match(/<details[^>]*>/)?.[0] ?? "";
		assert.ok(
			/\bopen(?:\s*=\s*["'][^"']*["'])?[\s>]/.test(faq6OpenTag),
			`${locale.file} #faq-6 must remain open by default`,
		);
		assert.ok(
			!faq6Html.includes('id="faq-fulfillment-links"') &&
				!faq6Html.includes('id="faq-registry-link"') &&
				!faq6Html.includes('id="faq-guide-link"') &&
				!/<a\b/i.test(faq6Html),
			`${locale.file} #faq-6 must render prose-only without fulfillment buttons or anchor tags`,
		);

		for (const requiredToken of [
			"1.",
			"2.",
			"3.",
			"1581",
			"679",
			"0XXXXXX#",
			"20,000 COP",
			locale.confirmationPhrase,
			locale.dispatchPhrase,
			locale.contactPhrase,
			locale.poolDay,
		]) {
			assert.ok(
				faq6Html.includes(requiredToken),
				`${locale.file} #faq-6 must include "${requiredToken}"`,
			);
		}

		if (locale.lang !== "en") {
			for (const englishLeak of [
				"Reservation Confirmation",
				"External Guest Dispatch",
				"Contact Us",
				"Guest Registry",
				"Guest Guide",
				"Personal Data Authorization",
				"Minor Guardian Attestation",
				"Building Registration Fee",
			]) {
				assert.ok(
					!faq6Html.includes(englishLeak),
					`${locale.file} #faq-6 must not leak English phrase "${englishLeak}"`,
				);
			}
		}

		// Homepage FAQPage JSON-LD automatically mirrors updated localized faqA6 copy
		const jsonLdMatches = [
			...html.matchAll(
				/<script[^>]*type="application\/ld\+json"[^>]*>([\s\S]*?)<\/script>/gi,
			),
		];
		assert.ok(
			jsonLdMatches.length >= 1,
			`${locale.file} must include JSON-LD structured data`,
		);
		const combinedJsonLd = jsonLdMatches.map((m) => m[1]).join("\n");
		assert.ok(
			combinedJsonLd.includes(JSON.stringify(dict[locale.lang].faqA6)),
			`${locale.file} FAQPage JSON-LD must mirror updated localized faqA6 copy`,
		);
	}
});

test("#337 Post-booking transactional pages preserve Reservation UID fulfillment links while keeping shared navigation and footer free of /registry and /guide links across all 6 locales", () => {
	const distDir = path.join(process.cwd(), "dist");

	for (const lang of LANGUAGES) {
		const suffix = lang === "en" ? "" : `${lang}.html`;
		const file = lang === "en" ? "index.html" : `${lang}.html`;
		const expectedRegistryHref = getRegistryPagePath(lang, "../");
		const expectedGuideHref = getGuidePagePath(lang, "../");
		assert.equal(expectedRegistryHref, `../registry/${suffix}`);
		assert.equal(expectedGuideHref, `../guide/${suffix}`);

		// 1. /booking-success retains #btn-booking-registry and #btn-booking-guide inside <main>
		const successHtml = fs.readFileSync(
			path.join(distDir, "booking-success", file),
			"utf-8",
		);
		const successMain = successHtml.match(/<main[\s\S]*?<\/main>/)?.[0] ?? "";
		const successNav =
			successHtml.match(/<nav[^>]*id="navbar"[\s\S]*?<\/nav>/)?.[0] ?? "";
		const successFooter =
			successHtml.match(/<footer[\s\S]*?<\/footer>/)?.[0] ?? "";

		assert.ok(
			successMain.includes(
				`id="btn-booking-registry" href="${expectedRegistryHref}"`,
			) &&
				successMain.includes(
					`id="btn-booking-guide" href="${expectedGuideHref}"`,
				),
			`booking-success/${file} must preserve #btn-booking-registry and #btn-booking-guide inside <main>`,
		);
		assert.deepEqual(
			findPrivateFulfillmentHrefs(successNav),
			[],
			`booking-success/${file} shared <nav> must omit /registry and /guide links`,
		);
		assert.deepEqual(
			findPrivateFulfillmentHrefs(successFooter),
			[],
			`booking-success/${file} shared <footer> must omit /registry and /guide links`,
		);

		// 2. /registry retains #btn-unlocked-guide cross-link inside <main>
		const registryHtml = fs.readFileSync(
			path.join(distDir, "registry", file),
			"utf-8",
		);
		const registryMain = registryHtml.match(/<main[\s\S]*?<\/main>/)?.[0] ?? "";
		const registryNav =
			registryHtml.match(/<nav[^>]*id="navbar"[\s\S]*?<\/nav>/)?.[0] ?? "";
		const registryFooter =
			registryHtml.match(/<footer[\s\S]*?<\/footer>/)?.[0] ?? "";

		assert.ok(
			registryMain.includes(
				`id="btn-unlocked-guide" href="${expectedGuideHref}"`,
			),
			`registry/${file} must preserve #btn-unlocked-guide link to localized Guest Guide inside <main>`,
		);
		assert.deepEqual(
			findPrivateFulfillmentHrefs(registryNav),
			[],
			`registry/${file} shared <nav> must omit /registry and /guide links`,
		);
		assert.deepEqual(
			findPrivateFulfillmentHrefs(registryFooter),
			[],
			`registry/${file} shared <footer> must omit /registry and /guide links`,
		);

		// 3. /guide retains #guide-registry-banner and #registry-link inside <main>
		const guideHtml = fs.readFileSync(
			path.join(distDir, "guide", file),
			"utf-8",
		);
		const guideMain = guideHtml.match(/<main[\s\S]*?<\/main>/)?.[0] ?? "";
		const guideNav =
			guideHtml.match(/<nav[^>]*id="navbar"[\s\S]*?<\/nav>/)?.[0] ?? "";
		const guideFooter = guideHtml.match(/<footer[\s\S]*?<\/footer>/)?.[0] ?? "";

		assert.ok(
			guideMain.includes('id="guide-registry-banner"') &&
				guideMain.includes('id="registry-link"'),
			`guide/${file} must preserve #guide-registry-banner and #registry-link inside <main>`,
		);
		assert.deepEqual(
			findPrivateFulfillmentHrefs(guideNav),
			[],
			`guide/${file} shared <nav> must omit /registry and /guide links`,
		);
		assert.deepEqual(
			findPrivateFulfillmentHrefs(guideFooter),
			[],
			`guide/${file} shared <footer> must omit /registry and /guide links`,
		);
	}
});

test("#337 Crawler and AI discovery exclusions (robots.txt, noindex meta, sitemap.xml, llms.txt) remain intact for /registry and /guide", () => {
	const distDir = path.join(process.cwd(), "dist");

	// 1. robots.txt disallows /registry/ and /guide/
	const robotsTxt = fs.readFileSync(path.join(distDir, "robots.txt"), "utf-8");
	assert.ok(
		robotsTxt.includes("Disallow: /registry/") &&
			robotsTxt.includes("Disallow: /guide/"),
		"dist/robots.txt must disallow /registry/ and /guide/",
	);

	// 2. Compiled /registry and /guide pages emit noindex meta directives across all 6 locales
	for (const lang of LANGUAGES) {
		const file = lang === "en" ? "index.html" : `${lang}.html`;
		for (const route of ["registry", "guide"] as const) {
			const html = fs.readFileSync(path.join(distDir, route, file), "utf-8");
			assert.ok(
				/<meta\s+name="robots"\s+content="noindex,\s*nofollow"\s*\/?>/i.test(
					html,
				),
				`dist/${route}/${file} must retain noindex, nofollow meta robots directive`,
			);
		}
	}

	// 3. sitemap.xml excludes /registry and /guide
	const sitemapXml = fs.readFileSync(
		path.join(distDir, "sitemap.xml"),
		"utf-8",
	);
	assert.ok(
		!sitemapXml.includes("/registry") && !sitemapXml.includes("/guide"),
		"dist/sitemap.xml must exclude /registry and /guide",
	);

	// 4. llms.txt and llms-full.txt exclude /registry/ and /guide/
	for (const llmsFile of ["llms.txt", "llms-full.txt"] as const) {
		const content = fs.readFileSync(path.join(distDir, llmsFile), "utf-8");
		assert.ok(
			!content.includes("/registry/") && !content.includes("/guide/"),
			`dist/${llmsFile} must exclude /registry/ and /guide/`,
		);
	}
});
