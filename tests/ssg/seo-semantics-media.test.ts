import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import test from "node:test";
import { getHomePagePath } from "../../src/constants/config";
import { dict } from "../../src/i18n/dict";
import type { Lang } from "../../src/types";

const DIST_DIR = path.join(process.cwd(), "dist");
const PUBLIC_DIR = path.join(process.cwd(), "public");
const LOCALES: readonly Lang[] = ["en", "es", "fr", "it", "de", "ja"];

const PUBLIC_PAGES = [
	{ id: "home", dir: "" },
	{ id: "Oceanview1707", dir: "Oceanview1707" },
	{ id: "Oceanview1606", dir: "Oceanview1606" },
	{ id: "contact", dir: "contact" },
	{ id: "privacy", dir: "privacy" },
] as const;

const NON_PUBLIC_PAGES = [
	{ id: "registry", dir: "registry" },
	{ id: "guide", dir: "guide" },
	{ id: "404", dir: "404" },
	{ id: "booking-success", dir: "booking-success" },
	{ id: "booking-pending", dir: "booking-pending" },
	{ id: "booking-failure", dir: "booking-failure" },
] as const;

function resolveDistFile(pageId: string, dir: string, lang: Lang): string {
	if (pageId === "404") {
		return lang === "en" ? "404.html" : `404/${lang}.html`;
	}
	const fileName = lang === "en" ? "index.html" : `${lang}.html`;
	return dir ? `${dir}/${fileName}` : fileName;
}

function escapeHtmlAttr(value: string): string {
	return value
		.replace(/&/g, "&amp;")
		.replace(/"/g, "&quot;")
		.replace(/'/g, "&#x27;")
		.replace(/</g, "&lt;")
		.replace(/>/g, "&gt;");
}

const HTML_ENTITY_MAP: Record<string, string> = {
	"&#x27;": "'",
	"&#39;": "'",
	"&quot;": '"',
	"&amp;": "&",
	"&lt;": "<",
	"&gt;": ">",
};

function unescapeHtmlEntities(value: string): string {
	return value.replace(
		/&(?:#x27|#39|quot|amp|lt|gt);/g,
		(entity) => HTML_ENTITY_MAP[entity] ?? entity,
	);
}

function getWebpDimensions(
	filePath: string,
): { width: number; height: number } | null {
	const buf = fs.readFileSync(filePath);
	if (
		buf.toString("ascii", 0, 4) !== "RIFF" ||
		buf.toString("ascii", 8, 12) !== "WEBP"
	) {
		return null;
	}
	const chunk = buf.toString("ascii", 12, 16);
	if (chunk === "VP8X") {
		const width = 1 + (buf[24] | (buf[25] << 8) | (buf[26] << 16));
		const height = 1 + (buf[27] | (buf[28] << 8) | (buf[29] << 16));
		return { width, height };
	}
	if (chunk === "VP8 ") {
		const width = buf.readUInt16LE(26) & 0x3fff;
		const height = buf.readUInt16LE(28) & 0x3fff;
		return { width, height };
	}
	if (chunk === "VP8L") {
		const bits = buf.readUInt32LE(21);
		const width = (bits & 0x3fff) + 1;
		const height = ((bits >> 14) & 0x3fff) + 1;
		return { width, height };
	}
	return null;
}

function extractTagAttr(tag: string, attrName: string): string | null {
	const regex = new RegExp(`\\b${attrName}="([^"]*)"`, "i");
	const match = tag.match(regex);
	return match ? match[1] : null;
}

test("All public pages across all 6 locales render exactly one <h1>, place <header> outside <main>, and maintain strictly sequential heading tiers with zero skipped levels (#300)", () => {
	for (const page of PUBLIC_PAGES) {
		for (const lang of LOCALES) {
			const relPath = resolveDistFile(page.id, page.dir, lang);
			const html = fs.readFileSync(path.join(DIST_DIR, relPath), "utf-8");

			// 1. Landmark placement: <header> must exist and must NOT be nested inside <main>
			const mainMatch = html.match(/<main\b[^>]*>([\s\S]*?)<\/main>/i);
			assert.ok(mainMatch, `${relPath} must render a <main> landmark`);
			const headerMatch = html.match(/<header\b[^>]*>[\s\S]*?<\/header>/i);
			assert.ok(headerMatch, `${relPath} must render a <header> landmark`);
			assert.ok(
				!mainMatch[1].includes("<header"),
				`${relPath} must place <header> outside <main>`,
			);
			assert.ok(
				html.indexOf("</header>") < html.indexOf("<main"),
				`${relPath} must render <header> before <main>`,
			);

			// 2. Single <h1> and strictly sequential heading hierarchy (no skipped tiers)
			const headingMatches = [...html.matchAll(/<h([1-6])\b[^>]*>/gi)];
			assert.ok(
				headingMatches.length >= 2,
				`${relPath} must contain a document heading outline`,
			);

			const levels = headingMatches.map((m) => Number(m[1]));
			const h1Count = levels.filter((lvl) => lvl === 1).length;
			assert.equal(
				h1Count,
				1,
				`${relPath} must render exactly one <h1> (found ${h1Count})`,
			);
			assert.equal(
				levels[0],
				1,
				`${relPath} document outline must begin with <h1> (found <h${levels[0]}>)`,
			);

			for (let i = 1; i < levels.length; i++) {
				const prev = levels[i - 1];
				const curr = levels[i];
				assert.ok(
					curr <= prev + 1,
					`${relPath} skipped heading tier from <h${prev}> to <h${curr}> at heading index ${i} (outline: ${levels.join(",")})`,
				);
			}
		}
	}

	// Verify specific fixed sections on Property 1707 and 1606
	for (const prop of ["Oceanview1707", "Oceanview1606"] as const) {
		const html = fs.readFileSync(
			path.join(DIST_DIR, `${prop}/index.html`),
			"utf-8",
		);
		const bookingMatch = html.match(
			/<section[^>]*id="booking"[^>]*>([\s\S]*?)<\/section>/i,
		);
		assert.ok(bookingMatch, `${prop} must render #booking section`);
		const bookingLevels = [
			...bookingMatch[1].matchAll(/<h([1-6])\b[^>]*>/gi),
		].map((m) => Number(m[1]));
		assert.deepEqual(
			bookingLevels,
			[2, 3, 3],
			`${prop} #booking section must use <h2> followed by two <h3> subheadings`,
		);

		// Host eyebrow must be <p>, host name must be <h2>
		assert.ok(
			html.includes(`<p class="text-sm font-bold uppercase tracking-widest`),
			`${prop} host eyebrow must render as <p>, not a heading`,
		);
		assert.ok(
			/<h2\b[^>]*>Manuel Enrique<\/h2>/.test(html),
			`${prop} host name must render as <h2>`,
		);
	}
});

test("Subpages (Property 1707, Property 1606, Contact, Data Processing Policy) render localized accessible breadcrumb <nav> trails linking home via relative asset prefixes (#300)", () => {
	const subpages = [
		{
			id: "Oceanview1707",
			dir: "Oceanview1707",
			labelKey: "heroTitle" as const,
		},
		{
			id: "Oceanview1606",
			dir: "Oceanview1606",
			labelKey: "hero1606Title" as const,
		},
		{ id: "contact", dir: "contact", labelKey: "navContact" as const },
		{ id: "privacy", dir: "privacy", labelKey: "breadcrumbPrivacy" as const },
	];

	for (const subpage of subpages) {
		for (const lang of LOCALES) {
			const t = dict[lang];
			const relPath = resolveDistFile(subpage.id, subpage.dir, lang);
			const html = fs.readFileSync(path.join(DIST_DIR, relPath), "utf-8");

			// Primary nav must have its own distinct localized aria-label
			assert.ok(
				html.includes(`<nav aria-label="${escapeHtmlAttr(t.navPrimaryLabel)}"`),
				`${relPath} primary <nav> must declare localized aria-label="${t.navPrimaryLabel}"`,
			);

			// Breadcrumb nav must exist with localized aria-label
			const breadcrumbLabelAttr = escapeHtmlAttr(t.breadcrumbLabel);
			const breadcrumbMatch = html.match(
				new RegExp(
					`<nav\\b[^>]*aria-label="${breadcrumbLabelAttr.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")}"[^>]*>([\\s\\S]*?)<\\/nav>`,
				),
			);
			assert.ok(
				breadcrumbMatch,
				`${relPath} must render breadcrumb <nav aria-label="${t.breadcrumbLabel}">`,
			);
			const breadcrumbHtml = breadcrumbMatch[1];

			// Must use <ol> with <li> items
			assert.ok(
				breadcrumbHtml.includes("<ol") && breadcrumbHtml.includes("</ol>"),
				`${relPath} breadcrumbs must use an ordered list <ol>`,
			);
			const listItems = breadcrumbHtml.match(/<li\b[^>]*>[\s\S]*?<\/li>/g);
			assert.equal(
				listItems?.length,
				2,
				`${relPath} breadcrumbs must render 2 hierarchy items (Home -> Current Page)`,
			);

			// Home link must use relative assetPrefix path from getHomePagePath(lang, "../")
			const expectedHomeHref = getHomePagePath(lang, "../");
			assert.ok(
				listItems[0].includes(`href="${expectedHomeHref}"`) &&
					listItems[0].includes(`>${escapeHtmlAttr(t.breadcrumbHome)}<`),
				`${relPath} first breadcrumb item must link to relative home "${expectedHomeHref}" with localized label "${t.breadcrumbHome}"`,
			);

			// Current page link must declare aria-current="page" and localized label
			assert.ok(
				listItems[1].includes('aria-current="page"') &&
					listItems[1].includes(escapeHtmlAttr(t[subpage.labelKey])),
				`${relPath} final breadcrumb item must mark current page with aria-current="page" and label "${t[subpage.labelKey]}"`,
			);

			// Decorative separator icon must be hidden from assistive tech
			assert.ok(
				listItems[1].includes('aria-hidden="true"'),
				`${relPath} breadcrumb divider icon must be marked aria-hidden="true"`,
			);
		}
	}

	// Homepage must NOT render breadcrumbs
	for (const lang of LOCALES) {
		const relPath = resolveDistFile("home", "", lang);
		const html = fs.readFileSync(path.join(DIST_DIR, relPath), "utf-8");
		assert.ok(
			!html.includes(
				`aria-label="${escapeHtmlAttr(dict[lang].breadcrumbLabel)}"`,
			),
			`${relPath} homepage must not render subpage breadcrumb navigation`,
		);
	}

	// Architectural guard: Breadcrumbs component uses centralized theme constants exclusively (zero inline className strings)
	const breadcrumbSource = fs.readFileSync(
		path.join(process.cwd(), "src/components/Breadcrumbs.tsx"),
		"utf-8",
	);
	assert.ok(
		!breadcrumbSource.includes('className="'),
		"src/components/Breadcrumbs.tsx must style exclusively via centralized theme constants with zero hardcoded className strings",
	);

	// Architectural guard: Hero and AboutAmenities remain free of propertyId conditional ternaries
	for (const relComponent of [
		"src/components/Hero.tsx",
		"src/components/AboutAmenities.tsx",
	]) {
		const source = fs.readFileSync(
			path.join(process.cwd(), relComponent),
			"utf-8",
		);
		assert.ok(
			!source.includes("propertyId ==="),
			`${relComponent} must route Property-specific copy/alts via THEMES keys without propertyId ternaries`,
		);
	}
});

test('All rendered <img> elements declare exact on-disk width/height, lazy images declare decoding="async", and every public alt attribute is descriptive and localized across all 6 locales (#300)', () => {
	const forbiddenGenericAlts = new Set([
		"",
		"Bedroom",
		"Pool",
		"Living area",
		"Habitación",
		"Piscina",
		"Chambre",
		"Camera da letto",
		"Schlafzimmer",
		"Schwimmbad",
		"ベッドルーム",
		"プール",
		"Balcony Ocean View",
		"OceanViewFlats Main Entrance",
		"OceanViewFlats Beachfront Entrance",
		"OceanViewFlats Ocean Backdrop",
		"Active representation of property",
		"Oceanview Flat 1707",
		"Oceanview Flat 1606",
		"Manuel Enrique",
	]);

	const englishSpecificAlts = new Set([
		dict.en.heroImgAlt,
		dict.en.hero1707ImgAlt,
		dict.en.hero1606ImgAlt,
		dict.en.about1707LivingAlt,
		dict.en.about1707PoolAlt,
		dict.en.about1606LivingAlt,
		dict.en.about1606PoolAlt,
		dict.en.hostImgAlt,
		dict.en.mainEntranceImgAlt,
		dict.en.nomadImgAlt,
		dict.en.romanticImgAlt,
	]);

	const allPages = [...PUBLIC_PAGES, ...NON_PUBLIC_PAGES];

	for (const page of allPages) {
		const isPublic = PUBLIC_PAGES.some((p) => p.id === page.id);
		for (const lang of LOCALES) {
			const relPath = resolveDistFile(page.id, page.dir, lang);
			const html = fs.readFileSync(path.join(DIST_DIR, relPath), "utf-8");
			const imgTags = html.match(/<img\b[^>]*>/gi) ?? [];

			assert.ok(
				imgTags.length > 0,
				`${relPath} must render at least one <img>`,
			);

			for (const imgTag of imgTags) {
				const src = extractTagAttr(imgTag, "src");
				const widthAttr = extractTagAttr(imgTag, "width");
				const heightAttr = extractTagAttr(imgTag, "height");
				const loadingAttr = extractTagAttr(imgTag, "loading");
				const decodingAttr = extractTagAttr(imgTag, "decoding");
				const fetchPriorityAttr = extractTagAttr(imgTag, "fetchpriority");
				const rawAlt = extractTagAttr(imgTag, "alt");

				assert.ok(src, `${relPath} <img> missing src: ${imgTag}`);
				assert.ok(
					widthAttr && /^\d+$/.test(widthAttr) && Number(widthAttr) > 0,
					`${relPath} <img> (${src}) must declare numeric width attribute`,
				);
				assert.ok(
					heightAttr && /^\d+$/.test(heightAttr) && Number(heightAttr) > 0,
					`${relPath} <img> (${src}) must declare numeric height attribute`,
				);

				// Verify declared width & height match actual on-disk WebP dimensions
				const normalizedAssetPath = src.replace(/^(\.\.\/|\.\/|\/)+/, "");
				const diskPath = path.join(PUBLIC_DIR, normalizedAssetPath);
				assert.ok(
					fs.existsSync(diskPath),
					`${relPath} <img> references missing asset on disk: ${normalizedAssetPath}`,
				);
				const diskDims = getWebpDimensions(diskPath);
				assert.ok(diskDims, `Unable to read WebP dimensions for ${diskPath}`);
				assert.equal(
					Number(widthAttr),
					diskDims.width,
					`${relPath} <img> (${src}) width=${widthAttr} does not match on-disk width=${diskDims.width}`,
				);
				assert.equal(
					Number(heightAttr),
					diskDims.height,
					`${relPath} <img> (${src}) height=${heightAttr} does not match on-disk height=${diskDims.height}`,
				);

				// Lazy images must include decoding="async" and never fetchpriority="high"
				if (loadingAttr === "lazy") {
					assert.equal(
						decodingAttr,
						"async",
						`${relPath} lazy <img> (${src}) must declare decoding="async"`,
					);
					assert.notEqual(
						fetchPriorityAttr,
						"high",
						`${relPath} lazy <img> (${src}) must not combine loading="lazy" with fetchpriority="high"`,
					);
				}

				// Below-the-fold images must be lazy-loaded
				if (
					src.includes("balcony-working-nomad.webp") ||
					src.includes("romantic-experiences.webp") ||
					imgTag.includes('id="lightbox-active-img"')
				) {
					assert.equal(
						loadingAttr,
						"lazy",
						`${relPath} below-the-fold <img> (${src}) must declare loading="lazy"`,
					);
					assert.equal(
						decodingAttr,
						"async",
						`${relPath} below-the-fold <img> (${src}) must declare decoding="async"`,
					);
				}

				// Alt text quality & localization checks
				assert.notEqual(
					rawAlt,
					null,
					`${relPath} <img> (${src}) missing alt attribute`,
				);
				const alt = unescapeHtmlEntities(rawAlt ?? "").trim();
				assert.ok(
					!forbiddenGenericAlts.has(alt),
					`${relPath} <img> (${src}) uses forbidden generic/hardcoded alt="${alt}"`,
				);

				if (isPublic) {
					assert.ok(
						alt.length >= 12,
						`${relPath} <img> (${src}) alt text is too terse to be descriptive: "${alt}"`,
					);
					if (lang !== "en") {
						assert.ok(
							!englishSpecificAlts.has(alt),
							`${relPath} (${lang}) <img> (${src}) leaked untranslated English alt="${alt}"`,
						);
					}
				}
			}
		}
	}

	// Verify Property 1707 and Property 1606 use distinct Property-specific hero/living/pool alts across all 6 locales
	for (const lang of LOCALES) {
		const html1707 = fs.readFileSync(
			path.join(
				DIST_DIR,
				resolveDistFile("Oceanview1707", "Oceanview1707", lang),
			),
			"utf-8",
		);
		const html1606 = fs.readFileSync(
			path.join(
				DIST_DIR,
				resolveDistFile("Oceanview1606", "Oceanview1606", lang),
			),
			"utf-8",
		);
		const t = dict[lang];

		assert.ok(
			html1707.includes(`alt="${escapeHtmlAttr(t.hero1707ImgAlt)}"`) &&
				html1707.includes(`alt="${escapeHtmlAttr(t.about1707LivingAlt)}"`) &&
				html1707.includes(`alt="${escapeHtmlAttr(t.about1707PoolAlt)}"`),
			`Oceanview1707 (${lang}) must render Property 1707-specific localized hero, living, and pool alts`,
		);
		assert.ok(
			html1606.includes(`alt="${escapeHtmlAttr(t.hero1606ImgAlt)}"`) &&
				html1606.includes(`alt="${escapeHtmlAttr(t.about1606LivingAlt)}"`) &&
				html1606.includes(`alt="${escapeHtmlAttr(t.about1606PoolAlt)}"`),
			`Oceanview1606 (${lang}) must render Property 1606-specific localized hero, living, and pool alts`,
		);
		assert.notEqual(t.hero1707ImgAlt, t.hero1606ImgAlt);
		assert.notEqual(t.about1707LivingAlt, t.about1606LivingAlt);
		assert.notEqual(t.about1707PoolAlt, t.about1606PoolAlt);
	}
});
