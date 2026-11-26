import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import test from "node:test";
import {
	BUILDING_LOCATION,
	HOME_PROPERTY_COMPARISON_ROWS,
	PLAYA_SALGUERO_LANDMARKS,
} from "../../src/constants/config";

const DIST_DIR = path.join(process.cwd(), "dist");

interface LocaleExpectation {
	code: "en" | "es" | "fr" | "it" | "de" | "ja";
	file: string;
	compareTitle: string;
	locationTitle: string;
	rate1707: string;
	rate1606Base: string;
	rate1606Peak: string;
	buildingFee: string;
	parking1707: string;
	parking1606: string;
	tuesdayToken: string;
	ideal1707Token: string;
	ideal1606Token: string;
	beachLandmarkToken: string;
	airportLandmarkToken: string;
	historicLandmarkToken: string;
	tayronaLandmarkToken: string;
	rodaderoTimeToken: string;
	airportTimeToken: string;
	historicTimeToken: string;
	tayronaTimeToken: string;
}

const LOCALE_EXPECTATIONS: readonly LocaleExpectation[] = [
	{
		code: "en",
		file: "index.html",
		compareTitle: "Compare Our Beachfront Properties",
		locationTitle: "Location &amp; Getting Around Playa Salguero",
		rate1707: "450,000 COP",
		rate1606Base: "350,000 COP",
		rate1606Peak: "550,000 COP",
		buildingFee: "20,000 COP",
		parking1707: "Bay 95",
		parking1606: "Bay 87",
		tuesdayToken: "Tuesdays",
		ideal1707Token: "Remote workers",
		ideal1606Token: "Couples",
		beachLandmarkToken: "Playa Salguero Beach",
		airportLandmarkToken: "Simón Bolívar International Airport (SMR)",
		historicLandmarkToken: "Santa Marta Historic Center",
		tayronaLandmarkToken: "Tayrona National Park",
		rodaderoTimeToken: "5–10 min",
		airportTimeToken: "15–20 min",
		historicTimeToken: "20–25 min",
		tayronaTimeToken: "50–60 min",
	},
	{
		code: "es",
		file: "es.html",
		compareTitle: "Compara Nuestros Apartamentos Frente al Mar",
		locationTitle: "Ubicación y Cómo Moverse en Playa Salguero",
		rate1707: "450.000 COP",
		rate1606Base: "350.000 COP",
		rate1606Peak: "550.000 COP",
		buildingFee: "20.000 COP",
		parking1707: "Bahía 95",
		parking1606: "Bahía 87",
		tuesdayToken: "martes",
		ideal1707Token: "Trabajadores remotos",
		ideal1606Token: "Parejas",
		beachLandmarkToken: "Playa Salguero",
		airportLandmarkToken: "Aeropuerto Internacional Simón Bolívar (SMR)",
		historicLandmarkToken: "Centro Histórico de Santa Marta",
		tayronaLandmarkToken: "Parque Nacional Natural Tayrona",
		rodaderoTimeToken: "5–10 min",
		airportTimeToken: "15–20 min",
		historicTimeToken: "20–25 min",
		tayronaTimeToken: "50–60 min",
	},
	{
		code: "fr",
		file: "fr.html",
		compareTitle: "Comparez Nos Appartements en Bord de Mer",
		locationTitle: "Emplacement et Déplacements à Playa Salguero",
		rate1707: "450 000 COP",
		rate1606Base: "350 000 COP",
		rate1606Peak: "550 000 COP",
		buildingFee: "20 000 COP",
		parking1707: "Place 95",
		parking1606: "Place 87",
		tuesdayToken: "mardi",
		ideal1707Token: "Télétravailleurs",
		ideal1606Token: "Couples",
		beachLandmarkToken: "Plage de Playa Salguero",
		airportLandmarkToken: "Aéroport International Simón Bolívar (SMR)",
		historicLandmarkToken: "Centre Historique de Santa Marta",
		tayronaLandmarkToken: "Parc National Naturel Tayrona",
		rodaderoTimeToken: "5–10 min",
		airportTimeToken: "15–20 min",
		historicTimeToken: "20–25 min",
		tayronaTimeToken: "50–60 min",
	},
	{
		code: "it",
		file: "it.html",
		compareTitle: "Confronta i Nostri Appartamenti Fronte Mare",
		locationTitle: "Posizione e Spostamenti a Playa Salguero",
		rate1707: "450.000 COP",
		rate1606Base: "350.000 COP",
		rate1606Peak: "550.000 COP",
		buildingFee: "20.000 COP",
		parking1707: "Posto 95",
		parking1606: "Posto 87",
		tuesdayToken: "martedì",
		ideal1707Token: "Lavoratori da remoto",
		ideal1606Token: "Coppie",
		beachLandmarkToken: "Spiaggia di Playa Salguero",
		airportLandmarkToken: "Aeroporto Internazionale Simón Bolívar (SMR)",
		historicLandmarkToken: "Centro Storico di Santa Marta",
		tayronaLandmarkToken: "Parco Nazionale Naturale Tayrona",
		rodaderoTimeToken: "5–10 min",
		airportTimeToken: "15–20 min",
		historicTimeToken: "20–25 min",
		tayronaTimeToken: "50–60 min",
	},
	{
		code: "de",
		file: "de.html",
		compareTitle: "Unsere Strand-Apartments im Vergleich",
		locationTitle: "Lage &amp; Mobilität in Playa Salguero",
		rate1707: "450.000 COP",
		rate1606Base: "350.000 COP",
		rate1606Peak: "550.000 COP",
		buildingFee: "20.000 COP",
		parking1707: "Stellplatz 95",
		parking1606: "Stellplatz 87",
		tuesdayToken: "Dienstags",
		ideal1707Token: "Remote-Arbeitende",
		ideal1606Token: "Paare",
		beachLandmarkToken: "Strand Playa Salguero",
		airportLandmarkToken: "Internationaler Flughafen Simón Bolívar (SMR)",
		historicLandmarkToken: "Historisches Zentrum von Santa Marta",
		tayronaLandmarkToken: "Tayrona-Nationalpark",
		rodaderoTimeToken: "5–10 Min.",
		airportTimeToken: "15–20 Min.",
		historicTimeToken: "20–25 Min.",
		tayronaTimeToken: "50–60 Min.",
	},
	{
		code: "ja",
		file: "ja.html",
		compareTitle: "ビーチフロント物件の比較",
		locationTitle: "プラヤ・サルグエロの立地と周辺アクセス",
		rate1707: "450,000 COP",
		rate1606Base: "350,000 COP",
		rate1606Peak: "550,000 COP",
		buildingFee: "20,000 COP",
		parking1707: "95番",
		parking1606: "87番",
		tuesdayToken: "火曜日",
		ideal1707Token: "リモートワーカー",
		ideal1606Token: "カップル",
		beachLandmarkToken: "プラヤ・サルグエロ・ビーチ",
		airportLandmarkToken: "シモン・ボリバル国際空港（SMR）",
		historicLandmarkToken: "サンタマルタ旧市街",
		tayronaLandmarkToken: "タイロナ国立公園",
		rodaderoTimeToken: "5〜10分",
		airportTimeToken: "15〜20分",
		historicTimeToken: "20〜25分",
		tayronaTimeToken: "50〜60分",
	},
];

const UNTRANSLATED_ENGLISH_TOKENS = [
	"Compare Our Beachfront Properties",
	"Location &amp; Getting Around Playa Salguero",
	"Floor Level",
	"Ideal Guest Profile",
	"Starting Nightly Rate (COP)",
	"Minimum Stay",
	"Maximum Occupancy",
	"Assigned Parking Space",
	"Building Registration Fee",
	"Pool Maintenance Schedule",
	"Closed Tuesdays for maintenance",
	"Playa Salguero Beach",
	"Simón Bolívar International Airport (SMR)",
	"Santa Marta Historic Center",
	"Tayrona National Park",
	"~2 min walk",
	"by car",
] as const;

test("Homepage renders semantic HTML comparison <table> covering all 12 reconciled attributes across all 6 locales (#302)", () => {
	assert.equal(
		HOME_PROPERTY_COMPARISON_ROWS.length,
		12,
		"HOME_PROPERTY_COMPARISON_ROWS must declare all 12 comparison attributes",
	);

	for (const locale of LOCALE_EXPECTATIONS) {
		const html = fs.readFileSync(path.join(DIST_DIR, locale.file), "utf-8");
		const sectionMatch = html.match(
			/<section[^>]*id="compare-properties"[^>]*>[\s\S]*?<\/section>/,
		);
		assert.ok(
			sectionMatch,
			`${locale.file} must render <section id="compare-properties">`,
		);
		const sectionHtml = sectionMatch[0];

		// Heading and keyboard-accessible scrollable table region
		assert.ok(
			sectionHtml.includes(`<h2`) && sectionHtml.includes(locale.compareTitle),
			`${locale.file} #compare-properties must render <h2> with localized comparison title`,
		);
		assert.ok(
			sectionHtml.includes('tabIndex="0"') ||
				sectionHtml.includes('tabindex="0"'),
			`${locale.file} #compare-properties wrapper must be keyboard-focusable (tabindex="0")`,
		);
		assert.ok(
			sectionHtml.includes('role="region"') &&
				sectionHtml.includes('aria-labelledby="home-comparison-caption"'),
			`${locale.file} #compare-properties wrapper must expose role="region" named by caption`,
		);

		// Semantic table structure: <table>, <caption>, <thead>, <tbody>, scope="col", scope="row"
		assert.ok(
			sectionHtml.includes("<table") &&
				sectionHtml.includes('<caption id="home-comparison-caption"') &&
				sectionHtml.includes("<thead") &&
				sectionHtml.includes("<tbody"),
			`${locale.file} #compare-properties must render semantic <table>, <caption>, <thead>, and <tbody>`,
		);

		const colHeaders = sectionHtml.match(/<th[^>]*scope="col"[^>]*>/g) || [];
		assert.equal(
			colHeaders.length,
			3,
			`${locale.file} comparison table must have 3 <th scope="col"> headers (Spec, 1707, 1606)`,
		);

		const rowHeaders = sectionHtml.match(/<th[^>]*scope="row"[^>]*>/g) || [];
		assert.equal(
			rowHeaders.length,
			12,
			`${locale.file} comparison table must have 12 <th scope="row"> headers`,
		);

		// Verify all 12 reconciled facts appear inside the comparison table
		const requiredFacts = [
			"17",
			"16",
			"268781",
			"270413",
			locale.ideal1707Token,
			locale.ideal1606Token,
			locale.rate1707,
			locale.rate1606Base,
			locale.rate1606Peak,
			"2",
			"4",
			"3",
			"6",
			"900 Mbps",
			locale.parking1707,
			locale.parking1606,
			"15:00",
			"11:00",
			locale.buildingFee,
			locale.tuesdayToken,
		];
		for (const token of requiredFacts) {
			assert.ok(
				sectionHtml.includes(token),
				`${locale.file} comparison table must include reconciled fact "${token}"`,
			);
		}

		// Ensure zero untranslated English copy on non-English locales
		if (locale.code !== "en") {
			for (const englishToken of UNTRANSLATED_ENGLISH_TOKENS) {
				assert.ok(
					!sectionHtml.includes(englishToken),
					`${locale.file} #compare-properties must not contain untranslated English phrase "${englishToken}"`,
				);
			}
		}
	}
});

test("Homepage renders localized Playa Salguero & Santa Marta proximity section with Edificio Salguero Sunset and all 5 landmarks across all 6 locales (#302)", () => {
	assert.equal(BUILDING_LOCATION.name, "Edificio Salguero Sunset");
	assert.equal(PLAYA_SALGUERO_LANDMARKS.length, 5);

	for (const locale of LOCALE_EXPECTATIONS) {
		const html = fs.readFileSync(path.join(DIST_DIR, locale.file), "utf-8");
		const sectionMatch = html.match(
			/<section[^>]*id="location"[^>]*>[\s\S]*?<\/section>/,
		);
		assert.ok(
			sectionMatch,
			`${locale.file} must render <section id="location">`,
		);
		const sectionHtml = sectionMatch[0];

		assert.ok(
			sectionHtml.includes("<h2") && sectionHtml.includes(locale.locationTitle),
			`${locale.file} #location must render localized <h2> title`,
		);

		const landmarkHeadings = sectionHtml.match(/<h3[^>]*>/g) || [];
		assert.equal(
			landmarkHeadings.length,
			5,
			`${locale.file} #location must render 5 <h3> landmark cards`,
		);

		const requiredLocationFacts = [
			"Edificio Salguero Sunset",
			"Calle 26 # 2-80",
			"Playa Salguero",
			locale.beachLandmarkToken,
			"150 m",
			"El Rodadero",
			locale.rodaderoTimeToken,
			"3 km",
			locale.airportLandmarkToken,
			"SMR",
			locale.airportTimeToken,
			"10 km",
			locale.historicLandmarkToken,
			locale.historicTimeToken,
			"8 km",
			locale.tayronaLandmarkToken,
			"El Zaino",
			locale.tayronaTimeToken,
			"40 km",
		];
		for (const token of requiredLocationFacts) {
			assert.ok(
				sectionHtml.includes(token),
				`${locale.file} #location must include verifiable proximity fact "${token}"`,
			);
		}

		// Ensure zero untranslated English copy on non-English locales
		if (locale.code !== "en") {
			for (const englishToken of UNTRANSLATED_ENGLISH_TOKENS) {
				assert.ok(
					!sectionHtml.includes(englishToken),
					`${locale.file} #location must not contain untranslated English phrase "${englishToken}"`,
				);
			}
		}
	}
});

test("Home.tsx preserves strict SSG invariants: centralized HOME_THEME, zero inline className, 6 FAQ summaries, sequential headings, and intact live rate badges (#302)", () => {
	const homeSource = fs.readFileSync(
		path.join(process.cwd(), "src", "pages", "Home.tsx"),
		"utf-8",
	);

	assert.ok(
		!homeSource.includes('className="'),
		"Home.tsx must contain ZERO hardcoded inline className strings",
	);
	assert.ok(
		!homeSource.includes("useState") && !homeSource.includes("useEffect"),
		"Home.tsx must contain ZERO React client hooks",
	);
	assert.ok(
		!homeSource.includes("blur-3xl"),
		"Home.tsx must contain ZERO blur-3xl blobs",
	);
	assert.ok(
		homeSource.includes("HOME_THEME") &&
			homeSource.includes("HOME_PROPERTY_COMPARISON_ROWS") &&
			homeSource.includes("PLAYA_SALGUERO_LANDMARKS"),
		"Home.tsx must consume centralized HOME_THEME, HOME_PROPERTY_COMPARISON_ROWS, and PLAYA_SALGUERO_LANDMARKS",
	);

	for (const locale of LOCALE_EXPECTATIONS) {
		const html = fs.readFileSync(path.join(DIST_DIR, locale.file), "utf-8");

		// Exactly 6 <summary> tags on the homepage
		const summaries = html.match(/<summary[^>]*>/g) || [];
		assert.equal(
			summaries.length,
			6,
			`${locale.file} must keep exactly 6 <summary> tags (do not add <details>/<summary> outside #faq)`,
		);

		// Live rate badges untouched
		assert.ok(
			html.includes('id="home-rate-badge-1707"') &&
				html.includes('id="home-rate-badge-1606"'),
			`${locale.file} must preserve #home-rate-badge-1707 and #home-rate-badge-1606`,
		);

		// Sequential heading levels (exactly 1 <h1>, no skipped heading tiers)
		const headingMatches = [...html.matchAll(/<h([1-6])\b/g)].map((m) =>
			Number(m[1]),
		);
		assert.equal(
			headingMatches.filter((lvl) => lvl === 1).length,
			1,
			`${locale.file} must have exactly one <h1>`,
		);
		for (let i = 1; i < headingMatches.length; i += 1) {
			assert.ok(
				headingMatches[i] <= headingMatches[i - 1] + 1,
				`${locale.file} must not skip heading levels (found h${headingMatches[i - 1]} -> h${headingMatches[i]})`,
			);
		}
	}
});
