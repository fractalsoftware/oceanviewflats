import {
	BUILDING_REGISTRATION_FEE_COP,
	getDestinationGuidePath,
	getDestinationGuidesHubPath,
} from "../constants/config";
import type { Lang, PortfolioPricingSummary } from "../types";
import { DAY_TRIPS_FROM_PLAYA_SALGUERO_GUIDE } from "./destinationGuides/dayTripsFromPlayaSalguero";
import { DIGITAL_NOMAD_REMOTE_WORK_GUIDE } from "./destinationGuides/digitalNomadRemoteWork";
import { DIRECT_RESERVATION_GUIDE } from "./destinationGuides/directReservationGuide";
import { PLAYA_SALGUERO_VS_EL_RODADERO_GUIDE } from "./destinationGuides/playaSalgueroVsElRodadero";
import type {
	DestinationGuideDefinition,
	DestinationGuidesHubLocaleContent,
} from "./destinationGuides/types";

export type {
	DestinationGuideCallout,
	DestinationGuideComparisonTable,
	DestinationGuideDefinition,
	DestinationGuideFactCard,
	DestinationGuideFaqItem,
	DestinationGuideIconId,
	DestinationGuideLocaleContent,
	DestinationGuideSection,
	DestinationGuidesHubLocaleContent,
	DestinationGuidesHubTopicPreview,
} from "./destinationGuides/types";

const LOCALE_THOUSANDS_SEPARATOR: Record<Lang, string> = {
	en: ",",
	es: ".",
	fr: " ",
	it: ".",
	de: ".",
	ja: ",",
};

export const formatCopAmountForLocale = (
	amount: number,
	lang: Lang,
): string => {
	const rounded = Math.round(amount);
	const digits = String(Math.abs(rounded));
	const sep = LOCALE_THOUSANDS_SEPARATOR[lang];
	const grouped = digits.replace(/\B(?=(\d{3})+(?!\d))/g, sep);
	return rounded < 0 ? `-${grouped}` : grouped;
};

export const formatCopCurrencyForLocale = (
	amount: number,
	lang: Lang,
): string => `${formatCopAmountForLocale(amount, lang)} COP`;

export const buildGuidePricingTokens = (
	pricingSummary: PortfolioPricingSummary,
	lang: Lang,
): Record<string, string> => {
	const p1707 = pricingSummary.properties["1707"];
	const p1606 = pricingSummary.properties["1606"];
	return {
		rate1707: formatCopAmountForLocale(p1707.lowPrice, lang),
		rate1707Cop: formatCopCurrencyForLocale(p1707.lowPrice, lang),
		rate1707HighCop: formatCopCurrencyForLocale(p1707.highPrice, lang),
		minStay1707: String(p1707.minStay),
		cleaning1707Cop: formatCopCurrencyForLocale(p1707.cleaningFeeCop, lang),
		rate1606: formatCopAmountForLocale(p1606.lowPrice, lang),
		rate1606Cop: formatCopCurrencyForLocale(p1606.lowPrice, lang),
		rate1606HighCop: formatCopCurrencyForLocale(p1606.highPrice, lang),
		minStay1606: String(p1606.minStay),
		maxMinStay1606: String(p1606.maxMinStay),
		cleaning1606Cop: formatCopCurrencyForLocale(p1606.cleaningFeeCop, lang),
		portfolioLowCop: formatCopCurrencyForLocale(pricingSummary.lowPrice, lang),
		portfolioHighCop: formatCopCurrencyForLocale(
			pricingSummary.highPrice,
			lang,
		),
		buildingFeeCop: formatCopCurrencyForLocale(
			BUILDING_REGISTRATION_FEE_COP,
			lang,
		),
	};
};

export const interpolateGuideText = (
	template: string,
	tokens: Readonly<Record<string, string>>,
): string =>
	template.replace(/\{([a-zA-Z0-9_]+)\}/g, (match, key: string) =>
		Object.hasOwn(tokens, key) ? tokens[key] : match,
	);

export const DESTINATION_GUIDES_HUB_COPY: Record<
	Lang,
	DestinationGuidesHubLocaleContent
> = {
	en: {
		seoTitle: "Santa Marta & Playa Salguero Guides | OceanViewFlats",
		seoDescription:
			"Local Destination Guides for Playa Salguero & Santa Marta: beaches, 900 Mbps remote work, Tayrona day trips, and 0% OTA fee Direct Reservations.",
		ogImageAlt:
			"Caribbean Sea sunset horizon over Playa Salguero, Santa Marta from OceanViewFlats beachfront balconies",
		heroImageAlt:
			"Playa Salguero beachfront coastline and Caribbean Sea sunset view in Santa Marta, Colombia",
		breadcrumbLabel: "Santa Marta Guides",
		badge: "Local Destination Guides",
		heroTitle: "Santa Marta & Playa Salguero Destination Guides",
		heroSubtitle:
			"Practical, host-verified guidance on Playa Salguero beaches, 900 Mbps remote work, Tayrona and Minca day trips, and 0% OTA commission Direct Reservations.",
		introTitle: "Plan Your Beachfront Stay in Playa Salguero",
		introBody:
			"Edificio Salguero Sunset sits one block (~150 m) from Playa Salguero beach—a quieter, residential Caribbean stretch just 5–10 minutes south of El Rodadero and 15–20 minutes from Simón Bolívar International Airport (SMR). Explore our Destination Guides below to choose the right neighborhood, plan coastal excursions, and reserve directly in Settlement Currency (COP).",
		guidesSectionTitle: "Published Destination Guides",
		guidesSectionSubtitle:
			"Deep local resources written from our beachfront base at Calle 26 # 2-80, Playa Salguero.",
		readGuideCta: "Read Destination Guide",
		readingTimeFormat: "{minutes} min read",
		publishedLabel: "Published",
		updatedLabel: "Updated",
		authorByline:
			"By Manuel Enrique Herrera • OceanViewFlats (RNT #268781 & #270413)",
		topicPreviewsTitle: "Core Playa Salguero & Santa Marta Topics",
		topicPreviewsSubtitle:
			"Everything prospective Guests need before booking a Direct Reservation at Property 1707 or Property 1606.",
		topicPreviews: [
			{
				id: "topic-neighborhood",
				slug: "playa-salguero-vs-el-rodadero",
				icon: "waves",
				badge: "Neighborhood",
				title: "Playa Salguero vs. El Rodadero",
				description:
					"Compare beach tranquility, walking access (~150 m to Playa Salguero), sunset views, dining options, and family safety between Santa Marta's two coastal districts.",
				relatedProperty: "1606",
			},
			{
				id: "topic-remote-work",
				slug: "digital-nomad-remote-work",
				icon: "wifi",
				badge: "Remote Work",
				title: "Remote Work & Digital Nomads in Santa Marta",
				description:
					"900 Mbps symmetrical fiber Wi-Fi, ergonomic workspaces, power stability, SMR airport transfers, and extended beachfront stays on the 17th floor.",
				relatedProperty: "1707",
			},
			{
				id: "topic-day-trips",
				slug: "day-trips-from-playa-salguero",
				icon: "compass",
				badge: "Excursions",
				title: "Day Trips from Playa Salguero",
				description:
					"Realistic travel times and logistics to Tayrona National Park (El Zaino, ~50–60 min), Santa Marta Historic Center (~20–25 min), Minca, and Taganga.",
				relatedProperty: "1606",
			},
			{
				id: "topic-direct-reservation",
				slug: "direct-reservation-guide",
				icon: "shield",
				badge: "Direct Reservation",
				title: "Direct Reservation Guide (COP, RNT & Guest Registry)",
				description:
					"How 0% OTA commission Quotes in COP work via Card, PSE, or Cash Voucher, plus statutory RNT compliance, Building Registration Fee ({buildingFeeCop}), and smart-lock check-in.",
				relatedProperty: "1707",
			},
		],
		propertiesCtaTitle: "Choose Your Playa Salguero Beachfront Property",
		propertiesCtaSubtitle:
			"Both Properties sit one floor apart at Edificio Salguero Sunset with 900 Mbps fiber Wi-Fi, swimming pool access, and Assigned Parking Spaces.",
		property1707Pitch:
			"17th floor • 2 Bedrooms, 4 Beds (max 6 Guests) • Dedicated remote-work desk & Assigned Parking Space Bay 95 • RNT #268781.",
		property1606Pitch:
			"16th floor • 2 Bedrooms, 3 Beds (max 6 Guests) • Warm romantic & family sunset terrace & Assigned Parking Space Bay 87 • RNT #270413.",
		propertyRateLineTemplate: "From {rate} / night ({minStay}-night min)",
		propertyCleaningLineTemplate: "Flat Cleaning Fee: {cleaningFee}",
		propertyCtaButton: "Explore & Book Direct",
		articlePropertyCtaEyebrow: "Recommended Beachfront Stay",
		articlePropertyCtaButton: "Check Dates & COP Quote",
		relatedGuidesTitle: "More Santa Marta Destination Guides",
		backToHubLabel: "All Santa Marta Destination Guides",
	},
	es: {
		seoTitle: "Guías de Santa Marta y Playa Salguero | OceanViewFlats",
		seoDescription:
			"Guías de Playa Salguero y Santa Marta: playas, trabajo remoto 900 Mbps, excursiones a Tayrona y Reservas Directas en COP sin comisión OTA.",
		ogImageAlt:
			"Horizonte del atardecer en el mar Caribe sobre Playa Salguero, Santa Marta desde los balcones de OceanViewFlats",
		heroImageAlt:
			"Costa frente a la playa en Playa Salguero y atardecer sobre el mar Caribe en Santa Marta, Colombia",
		breadcrumbLabel: "Guías de Santa Marta",
		badge: "Guías Locales de Destino",
		heroTitle: "Guías de Destino en Santa Marta y Playa Salguero",
		heroSubtitle:
			"Información práctica verificada por anfitriones sobre playas en Playa Salguero, trabajo remoto con fibra de 900 Mbps, excursiones a Tayrona y Reservas Directas sin comisión OTA.",
		introTitle: "Planifica Tu Estadía Frente al Mar en Playa Salguero",
		introBody:
			"El Edificio Salguero Sunset está a una cuadra (~150 m) de Playa Salguero, un sector residencial tranquilo a solo 5–10 minutos al sur de El Rodadero y a 15–20 minutos del Aeropuerto Internacional Simón Bolívar (SMR). Explora nuestras Guías de Destino para elegir zona, organizar excursiones y reservar directamente en COP.",
		guidesSectionTitle: "Guías de Destino Publicadas",
		guidesSectionSubtitle:
			"Recursos locales detallados escritos desde nuestra ubicación frente al mar en Calle 26 # 2-80, Playa Salguero.",
		readGuideCta: "Leer Guía de Destino",
		readingTimeFormat: "{minutes} min de lectura",
		publishedLabel: "Publicado",
		updatedLabel: "Actualizado",
		authorByline:
			"Por Manuel Enrique Herrera • OceanViewFlats (RNT #268781 y #270413)",
		topicPreviewsTitle: "Temas Clave de Playa Salguero y Santa Marta",
		topicPreviewsSubtitle:
			"Todo lo que necesitas saber antes de hacer una Reserva Directa en la Propiedad 1707 o la Propiedad 1606.",
		topicPreviews: [
			{
				id: "topic-neighborhood",
				slug: "playa-salguero-vs-el-rodadero",
				icon: "waves",
				badge: "Zona",
				title: "Playa Salguero vs. El Rodadero",
				description:
					"Compara tranquilidad en la playa (~150 m a Playa Salguero), atardeceres, gastronomía y ambiente familiar entre las dos zonas costeras de Santa Marta.",
				relatedProperty: "1606",
			},
			{
				id: "topic-remote-work",
				slug: "digital-nomad-remote-work",
				icon: "wifi",
				badge: "Trabajo Remoto",
				title: "Trabajo Remoto y Nómadas Digitales en Santa Marta",
				description:
					"Fibra óptica de 900 Mbps, espacio de trabajo ergonómico, traslados desde el aeropuerto SMR y estadías prolongadas frente al mar en el piso 17.",
				relatedProperty: "1707",
			},
			{
				id: "topic-day-trips",
				slug: "day-trips-from-playa-salguero",
				icon: "compass",
				badge: "Excursiones",
				title: "Excursiones de un Día desde Playa Salguero",
				description:
					"Tiempos reales y logística hacia el Parque Nacional Tayrona (El Zaino, ~50–60 min), Centro Histórico (~20–25 min), Minca y Taganga.",
				relatedProperty: "1606",
			},
			{
				id: "topic-direct-reservation",
				slug: "direct-reservation-guide",
				icon: "shield",
				badge: "Reserva Directa",
				title: "Guía de Reserva Directa (COP, RNT y Registro de Huéspedes)",
				description:
					"Cómo funcionan las cotizaciones en COP sin comisión OTA con Tarjeta, PSE o Efecty, cumplimiento RNT, Tarifa de Registro del Edificio ({buildingFeeCop}) y acceso con cerradura inteligente.",
				relatedProperty: "1707",
			},
		],
		propertiesCtaTitle: "Elige Tu Propiedad Frente al Mar en Playa Salguero",
		propertiesCtaSubtitle:
			"Ambas Propiedades están a un piso de distancia en Edificio Salguero Sunset con fibra óptica de 900 Mbps, piscina y Parqueadero Asignado.",
		property1707Pitch:
			"Piso 17 • 2 Habitaciones, 4 Camas (máx. 6 Huéspedes) • Escritorio para trabajo remoto y Bahía 95 • RNT #268781.",
		property1606Pitch:
			"Piso 16 • 2 Habitaciones, 3 Camas (máx. 6 Huéspedes) • Terraza cálida de atardeceres para parejas y familias y Bahía 87 • RNT #270413.",
		propertyRateLineTemplate: "Desde {rate} / noche (mín. {minStay} noches)",
		propertyCleaningLineTemplate: "Tarifa fija de Limpieza: {cleaningFee}",
		propertyCtaButton: "Explorar y Reservar Directo",
		articlePropertyCtaEyebrow: "Estadía Frente al Mar Recomendada",
		articlePropertyCtaButton: "Ver Fechas y Cotización en COP",
		relatedGuidesTitle: "Más Guías de Destino en Santa Marta",
		backToHubLabel: "Todas las Guías de Santa Marta",
	},
	fr: {
		seoTitle: "Guides Santa Marta & Playa Salguero | OceanViewFlats",
		seoDescription:
			"Guides locaux pour Playa Salguero et Santa Marta : plages, télétravail 900 Mbps, excursions Tayrona et Réservations Directes en COP sans frais OTA.",
		ogImageAlt:
			"Coucher de soleil sur la mer des Caraïbes à Playa Salguero, Santa Marta depuis les balcons d'OceanViewFlats",
		heroImageAlt:
			"Plage de Playa Salguero et coucher de soleil sur la mer des Caraïbes à Santa Marta, Colombie",
		breadcrumbLabel: "Guides Santa Marta",
		badge: "Guides de Destination",
		heroTitle: "Guides de Destination à Santa Marta et Playa Salguero",
		heroSubtitle:
			"Conseils locaux vérifiés sur les plages de Playa Salguero, le télétravail en fibre 900 Mbps, les excursions à Tayrona et la Réservation Directe en COP sans commission OTA.",
		introTitle: "Préparez Votre Séjour en Bord de Mer à Playa Salguero",
		introBody:
			"L'Edificio Salguero Sunset se trouve à un pâté de maisons (~150 m) de la plage de Playa Salguero, un quartier résidentiel calme à 5–10 minutes au sud d'El Rodadero et 15–20 minutes de l'aéroport international Simón Bolívar (SMR). Parcourez nos Guides de Destination pour choisir votre quartier et réserver directement en COP.",
		guidesSectionTitle: "Guides de Destination Publiés",
		guidesSectionSubtitle:
			"Ressources locales détaillées rédigées depuis notre résidence face à la mer à Calle 26 # 2-80, Playa Salguero.",
		readGuideCta: "Lire le Guide de Destination",
		readingTimeFormat: "{minutes} min de lecture",
		publishedLabel: "Publié",
		updatedLabel: "Mis à jour",
		authorByline:
			"Par Manuel Enrique Herrera • OceanViewFlats (RNT #268781 & #270413)",
		topicPreviewsTitle: "Thèmes Essentiels à Playa Salguero et Santa Marta",
		topicPreviewsSubtitle:
			"L'essentiel à connaître avant votre Réservation Directe dans la Propriété 1707 ou la Propriété 1606.",
		topicPreviews: [
			{
				id: "topic-neighborhood",
				slug: "playa-salguero-vs-el-rodadero",
				icon: "waves",
				badge: "Quartier",
				title: "Playa Salguero vs. El Rodadero",
				description:
					"Comparez le calme de la plage (~150 m à pied), les couchers de soleil, la restauration et l'ambiance familiale entre les deux zones côtières.",
				relatedProperty: "1606",
			},
			{
				id: "topic-remote-work",
				slug: "digital-nomad-remote-work",
				icon: "wifi",
				badge: "Télétravail",
				title: "Télétravail et Nomades Digitaux à Santa Marta",
				description:
					"Fibre optique 900 Mbps, espace bureau dédié, transferts depuis l'aéroport SMR et longs séjours face à la mer au 17e étage.",
				relatedProperty: "1707",
			},
			{
				id: "topic-day-trips",
				slug: "day-trips-from-playa-salguero",
				icon: "compass",
				badge: "Excursions",
				title: "Excursions d'une Journée depuis Playa Salguero",
				description:
					"Temps de trajet réels et logistique vers le Parc National Tayrona (El Zaino, ~50–60 min), le Centre Historique (~20–25 min), Minca et Taganga.",
				relatedProperty: "1606",
			},
			{
				id: "topic-direct-reservation",
				slug: "direct-reservation-guide",
				icon: "shield",
				badge: "Réservation Directe",
				title: "Guide de Réservation Directe (COP, RNT & Registre)",
				description:
					"Comment fonctionnent les devis en COP sans commission OTA (Carte, PSE, Efecty), la conformité RNT, les frais d'enregistrement de l'immeuble ({buildingFeeCop}) et la serrure connectée.",
				relatedProperty: "1707",
			},
		],
		propertiesCtaTitle: "Choisissez Votre Propriété Face à la Mer",
		propertiesCtaSubtitle:
			"Nos deux Propriétés sont à un étage d'intervalle dans l'Edificio Salguero Sunset avec fibre 900 Mbps, piscine et Place de Parking Attribuée.",
		property1707Pitch:
			"17e étage • 2 Chambres, 4 Lits (max 6 Voyageurs) • Espace télétravail dédié et Place 95 • RNT #268781.",
		property1606Pitch:
			"16e étage • 2 Chambres, 3 Lits (max 6 Voyageurs) • Terrasse chaleureuse pour couples et familles et Place 87 • RNT #270413.",
		propertyRateLineTemplate: "Dès {rate} / nuit (min. {minStay} nuits)",
		propertyCleaningLineTemplate: "Frais de Ménage fixes : {cleaningFee}",
		propertyCtaButton: "Explorer et Réserver en Direct",
		articlePropertyCtaEyebrow: "Séjour Face à la Mer Recommandé",
		articlePropertyCtaButton: "Voir les Dates et le Devis COP",
		relatedGuidesTitle: "Autres Guides de Destination à Santa Marta",
		backToHubLabel: "Tous les Guides Santa Marta",
	},
	it: {
		seoTitle: "Guide Santa Marta e Playa Salguero | OceanViewFlats",
		seoDescription:
			"Guide locali per Playa Salguero e Santa Marta: spiagge, lavoro da remoto 900 Mbps, gite a Tayrona e Prenotazioni Dirette in COP senza commissioni.",
		ogImageAlt:
			"Tramonto sul Mar dei Caraibi a Playa Salguero, Santa Marta dai balconi fronte mare di OceanViewFlats",
		heroImageAlt:
			"Costa di Playa Salguero e tramonto sul Mar dei Caraibi a Santa Marta, Colombia",
		breadcrumbLabel: "Guide di Santa Marta",
		badge: "Guide di Destinazione",
		heroTitle: "Guide di Destinazione a Santa Marta e Playa Salguero",
		heroSubtitle:
			"Consigli locali verificati sulle spiagge di Playa Salguero, lavoro da remoto con fibra 900 Mbps, escursioni a Tayrona e Prenotazioni Dirette in COP senza commissioni OTA.",
		introTitle: "Pianifica il Tuo Soggiorno Fronte Mare a Playa Salguero",
		introBody:
			"L'Edificio Salguero Sunset si trova a un isolato (~150 m) dalla spiaggia di Playa Salguero, zona residenziale tranquilla a 5–10 minuti a sud di El Rodadero e 15–20 minuti dall'Aeroporto Internazionale Simón Bolívar (SMR). Esplora le nostre Guide di Destinazione per organizzare il viaggio e prenotare direttamente in COP.",
		guidesSectionTitle: "Guide di Destinazione Pubblicate",
		guidesSectionSubtitle:
			"Approfondimenti locali redatti dalla nostra sede fronte mare in Calle 26 # 2-80, Playa Salguero.",
		readGuideCta: "Leggi la Guida di Destinazione",
		readingTimeFormat: "{minutes} min di lettura",
		publishedLabel: "Pubblicato",
		updatedLabel: "Aggiornato",
		authorByline:
			"Di Manuel Enrique Herrera • OceanViewFlats (RNT #268781 e #270413)",
		topicPreviewsTitle: "Temi Principali su Playa Salguero e Santa Marta",
		topicPreviewsSubtitle:
			"Tutto ciò che serve sapere prima di una Prenotazione Diretta nella Proprietà 1707 o nella Proprietà 1606.",
		topicPreviews: [
			{
				id: "topic-neighborhood",
				slug: "playa-salguero-vs-el-rodadero",
				icon: "waves",
				badge: "Quartiere",
				title: "Playa Salguero vs. El Rodadero",
				description:
					"Confronta tranquillità della spiaggia (~150 m a piedi), tramonti, ristoranti e atmosfera familiare tra le due zone costiere di Santa Marta.",
				relatedProperty: "1606",
			},
			{
				id: "topic-remote-work",
				slug: "digital-nomad-remote-work",
				icon: "wifi",
				badge: "Lavoro da Remoto",
				title: "Lavoro da Remoto e Nomadi Digitali a Santa Marta",
				description:
					"Fibra ottica da 900 Mbps, postazione dedicata, trasferimenti dall'aeroporto SMR e soggiorni prolungati fronte mare al 17° piano.",
				relatedProperty: "1707",
			},
			{
				id: "topic-day-trips",
				slug: "day-trips-from-playa-salguero",
				icon: "compass",
				badge: "Escursioni",
				title: "Gite in Giornata da Playa Salguero",
				description:
					"Tempi reali e logistica verso il Parco Nazionale Tayrona (El Zaino, ~50–60 min), Centro Storico (~20–25 min), Minca e Taganga.",
				relatedProperty: "1606",
			},
			{
				id: "topic-direct-reservation",
				slug: "direct-reservation-guide",
				icon: "shield",
				badge: "Prenotazione Diretta",
				title: "Guida alla Prenotazione Diretta (COP, RNT e Registro)",
				description:
					"Come funzionano i preventivi in COP con 0% commissioni OTA (Carta, PSE, Efecty), conformità RNT, Tassa di Registrazione dell'Edificio ({buildingFeeCop}) e check-in smart-lock.",
				relatedProperty: "1707",
			},
		],
		propertiesCtaTitle: "Scegli la Tua Proprietà Fronte Mare",
		propertiesCtaSubtitle:
			"Entrambe le Proprietà si trovano a un piano di distanza nell'Edificio Salguero Sunset con fibra 900 Mbps, piscina e Posto Auto Assegnato.",
		property1707Pitch:
			"17° piano • 2 Camere, 4 Letti (max 6 Ospiti) • Postazione lavoro da remoto e Posto 95 • RNT #268781.",
		property1606Pitch:
			"16° piano • 2 Camere, 3 Letti (max 6 Ospiti) • Terrazza romantica e familiare al tramonto e Posto 87 • RNT #270413.",
		propertyRateLineTemplate: "Da {rate} / notte (min. {minStay} notti)",
		propertyCleaningLineTemplate: "Tariffa fissa di Pulizia: {cleaningFee}",
		propertyCtaButton: "Esplora e Prenota Direttamente",
		articlePropertyCtaEyebrow: "Soggiorno Fronte Mare Consigliato",
		articlePropertyCtaButton: "Verifica Date e Preventivo COP",
		relatedGuidesTitle: "Altre Guide di Destinazione a Santa Marta",
		backToHubLabel: "Tutte le Guide di Santa Marta",
	},
	de: {
		seoTitle: "Santa Marta & Playa Salguero Tipps | OceanViewFlats",
		seoDescription:
			"Lokale Guides für Playa Salguero & Santa Marta: Strände, 900 Mbit/s Remote Work, Tayrona-Ausflüge und Direktbuchungen in COP ohne OTA-Gebühren.",
		ogImageAlt:
			"Karibischer Sonnenuntergang über Playa Salguero, Santa Marta von den Balkonen der OceanViewFlats",
		heroImageAlt:
			"Strandküste von Playa Salguero und Sonnenuntergang über dem Karibischen Meer in Santa Marta, Kolumbien",
		breadcrumbLabel: "Santa Marta Reiseführer",
		badge: "Lokale Reiseführer",
		heroTitle: "Santa Marta & Playa Salguero Reiseführer",
		heroSubtitle:
			"Praxisnahe, vom Gastgeber geprüfte Tipps zu Stränden in Playa Salguero, 900 Mbit/s Remote Work, Tayrona-Ausflügen und Direktbuchungen in COP ohne OTA-Provision.",
		introTitle: "Planen Sie Ihren Strandaufenthalt in Playa Salguero",
		introBody:
			"Das Edificio Salguero Sunset liegt nur einen Block (~150 m) vom Strand Playa Salguero entfernt – einem ruhigen Wohnabschnitt 5–10 Minuten südlich von El Rodadero und 15–20 Minuten vom Internationalen Flughafen Simón Bolívar (SMR). Nutzen Sie unsere Reiseführer für Ausflugsplanung und transparente Direktbuchungen in COP.",
		guidesSectionTitle: "Veröffentlichte Reiseführer",
		guidesSectionSubtitle:
			"Ausführliche lokale Guides direkt aus unserem Standort in Calle 26 # 2-80, Playa Salguero.",
		readGuideCta: "Reiseführer lesen",
		readingTimeFormat: "{minutes} Min. Lesezeit",
		publishedLabel: "Veröffentlicht",
		updatedLabel: "Aktualisiert",
		authorByline:
			"Von Manuel Enrique Herrera • OceanViewFlats (RNT #268781 & #270413)",
		topicPreviewsTitle: "Wichtige Themen für Playa Salguero & Santa Marta",
		topicPreviewsSubtitle:
			"Alle wichtigen Infos vor Ihrer Direktbuchung für Unterkunft 1707 oder Unterkunft 1606.",
		topicPreviews: [
			{
				id: "topic-neighborhood",
				slug: "playa-salguero-vs-el-rodadero",
				icon: "waves",
				badge: "Stadtteil",
				title: "Playa Salguero vs. El Rodadero",
				description:
					"Vergleichen Sie Strandruhe (~150 m bis Playa Salguero), Sonnenuntergänge, Restaurants und Familienfreundlichkeit der beiden Küstenviertel.",
				relatedProperty: "1606",
			},
			{
				id: "topic-remote-work",
				slug: "digital-nomad-remote-work",
				icon: "wifi",
				badge: "Remote Work",
				title: "Remote Work & Digitale Nomaden in Santa Marta",
				description:
					"900 Mbit/s Glasfaser-WLAN, eigener Arbeitsplatz, Flughafentransfer (SMR) und längere Strandaufenthalte im 17. Stock.",
				relatedProperty: "1707",
			},
			{
				id: "topic-day-trips",
				slug: "day-trips-from-playa-salguero",
				icon: "compass",
				badge: "Ausflüge",
				title: "Tagesausflüge ab Playa Salguero",
				description:
					"Realistische Fahrtzeiten und Logistik zum Tayrona-Nationalpark (El Zaino, ~50–60 Min.), Historischen Zentrum (~20–25 Min.), Minca und Taganga.",
				relatedProperty: "1606",
			},
			{
				id: "topic-direct-reservation",
				slug: "direct-reservation-guide",
				icon: "shield",
				badge: "Direktbuchung",
				title: "Leitfaden zur Direktbuchung (COP, RNT & Gästeregistrierung)",
				description:
					"Wie Angebote in COP mit 0% OTA-Gebühren (Karte, PSE, Bargeld-Gutschein), RNT-Nachweis, Gebäuderegistrierungsgebühr ({buildingFeeCop}) und Smart-Lock funktionieren.",
				relatedProperty: "1707",
			},
		],
		propertiesCtaTitle: "Wählen Sie Ihre Strandunterkunft in Playa Salguero",
		propertiesCtaSubtitle:
			"Beide Unterkünfte liegen nur eine Etage voneinander entfernt im Edificio Salguero Sunset mit 900 Mbit/s WLAN, Pool und festem Parkplatz.",
		property1707Pitch:
			"17. Stock • 2 Schlafzimmer, 4 Betten (max. 6 Gäste) • Remote-Work-Arbeitsplatz & Stellplatz 95 • RNT #268781.",
		property1606Pitch:
			"16. Stock • 2 Schlafzimmer, 3 Betten (max. 6 Gäste) • Warme Sonnenuntergangsterrasse für Paare & Familien & Stellplatz 87 • RNT #270413.",
		propertyRateLineTemplate:
			"Ab {rate} / Nacht (Mindestaufenthalt {minStay} Nächte)",
		propertyCleaningLineTemplate: "Pauschale Reinigungsgebühr: {cleaningFee}",
		propertyCtaButton: "Entdecken & Direkt Buchen",
		articlePropertyCtaEyebrow: "Empfohlene Strandunterkunft",
		articlePropertyCtaButton: "Termine & COP-Angebot prüfen",
		relatedGuidesTitle: "Weitere Santa Marta Reiseführer",
		backToHubLabel: "Alle Santa Marta Reiseführer",
	},
	ja: {
		seoTitle: "サンタマルタ・プラヤサルグエロ現地ガイド | OceanViewFlats",
		seoDescription:
			"プラヤサルグエロ・サンタマルタの現地ガイド：ビーチ比較、900 Mbpsリモートワーク環境、タイロナ国立公園への日帰り旅、OTA手数料0%のCOP直接予約をご案内します。",
		ogImageAlt:
			"コロンビア・サンタマルタのプラヤ・サルグエロに沈むカリブ海の夕日とOceanViewFlatsの海辺バルコニー",
		heroImageAlt:
			"コロンビア・サンタマルタのプラヤ・サルグエロ海岸線とカリブ海の夕景",
		breadcrumbLabel: "サンタマルタ現地ガイド",
		badge: "現地ディスティネーションガイド",
		heroTitle: "サンタマルタ＆プラヤ・サルグエロ現地ガイド",
		heroSubtitle:
			"プラヤ・サルグエロのビーチ環境、900 Mbps光回線リモートワーク、タイロナ国立公園への日帰り旅、OTA手数料0%のCOP直接予約をホスト目線で詳しくご案内します。",
		introTitle: "プラヤ・サルグエロ海辺の滞在を計画する",
		introBody:
			"エディフィシオ・サルグエロ・サンセットはプラヤ・サルグエロ・ビーチまで徒歩約2分（約150m）。エル・ロダデロから南へ車で約5〜10分、シモン・ボリバル国際空港（SMR）から約15〜20分の静かな住宅海岸エリアです。以下の現地ガイドで周辺エリアや移動手段を確認し、COP建ての直接予約にお役立てください。",
		guidesSectionTitle: "公開中の現地ガイド",
		guidesSectionSubtitle:
			"プラヤ・サルグエロ（Calle 26 # 2-80）のビーチフロント拠点から発信する実践的な旅行ガイドです。",
		readGuideCta: "現地ガイドを読む",
		readingTimeFormat: "読了目安 {minutes}分",
		publishedLabel: "公開日",
		updatedLabel: "更新日",
		authorByline:
			"執筆：Manuel Enrique Herrera • OceanViewFlats（RNT #268781・#270413）",
		topicPreviewsTitle: "プラヤ・サルグエロ＆サンタマルタ主要トピック",
		topicPreviewsSubtitle:
			"物件1707または物件1606の直接予約前に確認しておきたい4つのテーマ。",
		topicPreviews: [
			{
				id: "topic-neighborhood",
				slug: "playa-salguero-vs-el-rodadero",
				icon: "waves",
				badge: "エリア比較",
				title: "プラヤ・サルグエロ vs エル・ロダデロ",
				description:
					"ビーチの静けさ（徒歩約150m）、サンセット眺望、周辺レストラン、ファミリー向けの過ごしやすさをサンタマルタの2大海岸エリアで比較します。",
				relatedProperty: "1606",
			},
			{
				id: "topic-remote-work",
				slug: "digital-nomad-remote-work",
				icon: "wifi",
				badge: "リモートワーク",
				title: "サンタマルタでのリモートワーク＆ノマド滞在",
				description:
					"900 Mbps光回線Wi-Fi、専用ワークスペース、SMR空港アクセス、17階からの海辺長期滞在のポイントを解説します。",
				relatedProperty: "1707",
			},
			{
				id: "topic-day-trips",
				slug: "day-trips-from-playa-salguero",
				icon: "compass",
				badge: "日帰り旅",
				title: "プラヤ・サルグエロ発の日帰りモデルコース",
				description:
					"タイロナ国立公園（エル・サイノ入口まで約50〜60分）、サンタマルタ旧市街（約20〜25分）、ミンカ、タガンガへの移動時間と実践ガイド。",
				relatedProperty: "1606",
			},
			{
				id: "topic-direct-reservation",
				slug: "direct-reservation-guide",
				icon: "shield",
				badge: "直接予約",
				title: "COP直接予約・RNT登録・ゲスト登録ガイド",
				description:
					"OTA手数料0%のCOP公式見積もり（カード・PSE・現金バウチャー）、RNT法令遵守、建物登録料（{buildingFeeCop}）、スマートロック入室の流れ。",
				relatedProperty: "1707",
			},
		],
		propertiesCtaTitle: "プラヤ・サルグエロの海辺物件を選ぶ",
		propertiesCtaSubtitle:
			"両物件ともエディフィシオ・サルグエロ・サンセットの隣り合う階に位置し、900 Mbps光Wi-Fi・プール・専用駐車スペースを備えています。",
		property1707Pitch:
			"17階 • 2ベッドルーム・4ベッド（最大6名）• リモートワーク向けデスク＆専用駐車スペース95番 • RNT #268781。",
		property1606Pitch:
			"16階 • 2ベッドルーム・3ベッド（最大6名）• カップル・家族向けサンセットテラス＆専用駐車スペース87番 • RNT #270413。",
		propertyRateLineTemplate: "{rate} / 泊〜（最低{minStay}泊）",
		propertyCleaningLineTemplate: "清掃料金（一律）：{cleaningFee}",
		propertyCtaButton: "詳細を見る・直接予約",
		articlePropertyCtaEyebrow: "おすすめのビーチフロント滞在",
		articlePropertyCtaButton: "空室とCOP見積もりを確認",
		relatedGuidesTitle: "その他のサンタマルタ現地ガイド",
		backToHubLabel: "サンタマルタ現地ガイド一覧へ",
	},
};

/**
 * Registry of published Destination Guide articles rendered at `/santa-marta/<slug>/`.
 * `src/config/pages.ts`, `render.tsx`, `DestinationGuidesHub.tsx`, `Footer.tsx`, and
 * `PropertyDestinationGuides.tsx` consume this registry automatically.
 */
export const DESTINATION_GUIDES: readonly DestinationGuideDefinition[] = [
	PLAYA_SALGUERO_VS_EL_RODADERO_GUIDE,
	DIGITAL_NOMAD_REMOTE_WORK_GUIDE,
	DAY_TRIPS_FROM_PLAYA_SALGUERO_GUIDE,
	DIRECT_RESERVATION_GUIDE,
];

export const getDestinationGuideBySlug = (
	slug: string,
): DestinationGuideDefinition | undefined =>
	DESTINATION_GUIDES.find((guide) => guide.slug === slug);

export const resolveDestinationGuideHref = (
	slug: string,
	lang: Lang,
	assetPrefix = "./",
): string =>
	getDestinationGuideBySlug(slug)
		? getDestinationGuidePath(slug, lang, assetPrefix)
		: getDestinationGuidesHubPath(lang, assetPrefix);
