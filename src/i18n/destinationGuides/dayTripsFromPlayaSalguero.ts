import { IMAGE_DIMENSIONS, IMAGES } from "../../constants/config";
import type { DestinationGuideDefinition } from "./types";

export const DAY_TRIPS_FROM_PLAYA_SALGUERO_GUIDE: DestinationGuideDefinition = {
	slug: "day-trips-from-playa-salguero",
	id: "destination-guide-day-trips-from-playa-salguero",
	categoryIcon: "compass",
	heroImage: IMAGES.home.hero,
	heroImageDimensions: IMAGE_DIMENSIONS.homeHero,
	ogImage: {
		path: IMAGES.home.hero,
		...IMAGE_DIMENSIONS.homeHero,
	},
	datePublished: "2026-11-26",
	dateModified: "2026-11-26",
	readingTimeMinutes: 8,
	relatedProperties: ["1606", "1707"],
	relatedGuideSlugs: [
		"playa-salguero-vs-el-rodadero",
		"direct-reservation-guide",
		"digital-nomad-remote-work",
	],
	locales: {
		en: {
			seoTitle: "Santa Marta Day Trips from Playa Salguero | OceanViewFlats",
			seoDescription:
				"Day trips from Playa Salguero, Santa Marta: Tayrona (50–60 min), Minca, Historic Center (20–25 min) & El Rodadero. Stays from {portfolioLowCop}.",
			ogImageAlt:
				"Caribbean sunset coast in Playa Salguero, Santa Marta—base camp for Tayrona National Park and Minca day trips",
			heroImageAlt:
				"Panoramic Playa Salguero coastline at sunset in Santa Marta, Colombia",
			breadcrumbLabel: "Day Trips from Playa Salguero",
			badge: "Excursions & Logistics",
			title: "Day Trips from Playa Salguero: Tayrona, Minca & Historic Center",
			subtitle:
				"Realistic door-to-door driving times, departure tips, and return-to-the-beach planning from Edificio Salguero Sunset (Calle 26 # 2-80).",
			cardSummary:
				"Plan effortless day excursions from Playa Salguero to Tayrona National Park, Minca's Sierra Nevada waterfalls, Santa Marta Historic Center, and El Rodadero.",
			keyTakeawaysTitle: "Excursion Planning at a Glance",
			keyTakeaways: [
				"Tayrona National Park (El Zaino main gate) is ~40 km (~50–60 min by car) via the Troncal del Caribe—depart around 06:30–07:00 to beat trail heat and gate queues.",
				"Minca's cool Sierra Nevada coffee farms and waterfalls sit ~45–55 min uphill by car, making an easy half-day or full-day escape from coastal heat.",
				"Santa Marta Historic Center & International Marina are ~8 km (~20–25 min by car), and El Rodadero's boat piers are just ~3 km (~5–10 min by car).",
				"Both Property 1606 (Bay 87, from {rate1606Cop}/night) and Property 1707 (Bay 95, from {rate1707Cop}/night) include a deeded Assigned Parking Space for rental-car explorers.",
			],
			sections: [
				{
					id: "travel-times-matrix",
					heading: "Realistic Travel Times from Edificio Salguero Sunset",
					paragraphs: [
						"One of Playa Salguero's biggest practical advantages is fast highway access. Situated on the southern coastal flank of Santa Marta, Edificio Salguero Sunset connects straight to the Ruta del Sol / Troncal del Caribe ring road without weaving through congested downtown intersections.",
						"Use the table below to sequence your excursions around early-morning departures and golden-hour returns to your beachfront balcony.",
					],
					comparisonTable: {
						caption:
							"Door-to-door distances and driving times from Edificio Salguero Sunset (Calle 26 # 2-80, Playa Salguero)",
						headers: [
							"Destination",
							"Approx. Distance",
							"Driving Time",
							"Best Timing & Highlights",
						],
						rows: [
							[
								"Playa Salguero Beach",
								"~150 m (1 block)",
								"~2 min walk",
								"Sunrise swim or 17:30 sunset stroll",
							],
							[
								"El Rodadero & Piers",
								"~3 km",
								"~5–10 min by car",
								"Morning boats to Playa Blanca & Aquarium; groceries & dining",
							],
							[
								"Santa Marta Historic Center & Marina",
								"~8 km",
								"~20–25 min by car",
								"Late afternoon architecture walk, Marina sunset & dinner",
							],
							[
								"Simón Bolívar Airport (SMR)",
								"~10 km",
								"~15–20 min by car",
								"Direct domestic transfers along coastal avenue",
							],
							[
								"Taganga Fishing Cove",
								"~13 km",
								"~30–35 min by car",
								"Morning PADI diving & snorkeling departures",
							],
							[
								"Minca (Sierra Nevada Foothills)",
								"~22 km",
								"~45–55 min by car",
								"Organic coffee farms, Pozo Azul, Marinka waterfalls & birding",
							],
							[
								"Tayrona National Park (El Zaino)",
								"~40 km",
								"~50–60 min by car",
								"06:30 departure for Cañaveral, Arrecifes & Cabo San Juan",
							],
						],
					},
				},
				{
					id: "tayrona-and-minca",
					heading: "Tayrona National Park & Minca: Coastal-to-Mountain Icons",
					paragraphs: [
						"Tayrona National Park (El Zaino entrance, ~40 km / ~50–60 min by car) is Colombia's flagship coastal rainforest reserve. Leaving Edificio Salguero Sunset by 06:30 lets you enter before midday humidity peaks and hike comfortably from Cañaveral past Arrecifes and La Piscina to Cabo San Juan del Guía. Bring original passports or Colombian IDs for park entry registration, closed-toe walking shoes, biodegradable sunscreen, and plenty of water.",
						"Minca (~45–55 min uphill into the Sierra Nevada de Santa Marta) offers the opposite climate—crisp mountain air, shade-grown organic coffee fincas like La Victoria, swimming holes at Pozo Azul and Cascadas de Marinka, and world-class toucan and hummingbird spotting.",
					],
					bullets: [
						"Check Tayrona's seasonal ecological closure calendar (Respira Tayrona, typically short windows in February, June, and October) before locking excursion dates.",
						"For Minca, hire a private local driver or join a 4x4 tour so steep mountain tracks to remote fincas and viewpoints stay stress-free.",
						"Renting a car? Both OceanViewFlats Properties include free covered parking inside Edificio Salguero Sunset (Bay 87 for Property 1606, Bay 95 for Property 1707).",
					],
					factCards: [
						{
							label: "Tayrona (El Zaino)",
							value: "~50–60 min",
							detail: "~40 km via Troncal del Caribe",
						},
						{
							label: "Minca Foothills",
							value: "~45–55 min",
							detail: "Coffee farms, waterfalls & cooler air",
						},
						{
							label: "Historic Center",
							value: "~20–25 min",
							detail: "~8 km to Marina & Parque de los Novios",
						},
					],
				},
				{
					id: "historic-center-and-rodadero",
					heading: "Santa Marta Historic Center, Marina & El Rodadero",
					paragraphs: [
						"Save your late afternoons and evenings for Santa Marta's Historic Center (~8 km / ~20–25 min by car). Stroll past the whitewashed Cathedral Basilica, browse the Gold Museum (Museo del Oro Tairona) in Casa de la Aduana, watch sailboats at the International Marina, and dine around Parque de los Novios before a quick taxi ride back to peaceful Playa Salguero.",
						"Closer still, El Rodadero (~3 km / ~5–10 min by car) serves as your convenient launchpad for morning boat excursions to Playa Blanca and the Mundo Marino / Rodadero Sea Aquarium, plus quick supermarket runs.",
					],
					callout: {
						label: "Smart Tuesday Itinerary Tip",
						text: "Edificio Salguero Sunset's swimming pool closes every Tuesday for scheduled maintenance—making Tuesday the ideal day to schedule Tayrona National Park or Minca! Return to your air-conditioned beachfront residence with in-unit laundry and 900 Mbps Wi-Fi.",
					},
				},
			],
			faqsTitle: "Frequently Asked Questions: Day Trips from Playa Salguero",
			faqs: [
				{
					id: "faq-dt-1",
					question:
						"How long does it take to get from Playa Salguero to Tayrona National Park?",
					answer:
						"Driving from Edificio Salguero Sunset (Calle 26 # 2-80) to Tayrona National Park's El Zaino main entrance covers ~40 km and takes approximately 50–60 minutes via the Troncal del Caribe highway.",
					defaultOpen: true,
				},
				{
					id: "faq-dt-2",
					question:
						"Can I park a rental car at OceanViewFlats for day trips around Santa Marta?",
					answer:
						"Yes. Every Direct Reservation includes a deeded Assigned Parking Space inside Edificio Salguero Sunset at no extra nightly surcharge: Bay 95 for Property 1707 (from {rate1707Cop}/night + {cleaning1707Cop} Cleaning Fee) and Bay 87 for Property 1606 (from {rate1606Cop}/night + {cleaning1606Cop} Cleaning Fee). Simply enter your vehicle plate in the mandatory Guest Registry before arrival.",
				},
				{
					id: "faq-dt-3",
					question:
						"What is the best day of the week to visit Tayrona or Minca from Edificio Salguero Sunset?",
					answer:
						"Tuesdays are especially practical because the condominium swimming pool closes Tuesdays for scheduled maintenance, and weekday trail crowds at Tayrona and Minca waterfalls are noticeably lighter than weekends.",
				},
				{
					id: "faq-dt-4",
					question:
						"How far is Santa Marta Historic Center and El Rodadero from Playa Salguero?",
					answer:
						"El Rodadero is ~3 km (~5–10 minutes by car) and Santa Marta Historic Center & International Marina are ~8 km (~20–25 minutes by car) from Edificio Salguero Sunset.",
				},
			],
			ctaTitle: "Make Playa Salguero Your Coastal Base Camp",
			ctaSubtitle:
				"Return from Tayrona and Minca to beachfront sunset terraces and Assigned Parking Spaces—from {portfolioLowCop} per night with 0% OTA fees.",
		},
		es: {
			seoTitle: "Excursiones desde Playa Salguero | OceanViewFlats",
			seoDescription:
				"Excursiones desde Playa Salguero, Santa Marta: Parque Tayrona (50–60 min), Minca, Centro Histórico (20–25 min) y El Rodadero. Desde {portfolioLowCop}.",
			ogImageAlt:
				"Costa al atardecer en Playa Salguero, Santa Marta—base ideal para excursiones al Parque Tayrona y Minca",
			heroImageAlt:
				"Costa panorámica de Playa Salguero al atardecer en Santa Marta, Colombia",
			breadcrumbLabel: "Excursiones desde Playa Salguero",
			badge: "Excursiones y Logística",
			title:
				"Excursiones de un Día desde Playa Salguero: Tayrona, Minca y Centro Histórico",
			subtitle:
				"Tiempos reales puerta a puerta, consejos de salida y logística desde Edificio Salguero Sunset (Calle 26 # 2-80).",
			cardSummary:
				"Organiza tus paseos desde Playa Salguero al Parque Nacional Tayrona, las cascadas de Minca, el Centro Histórico de Santa Marta y El Rodadero.",
			keyTakeawaysTitle: "Logística de Excursiones en Resumen",
			keyTakeaways: [
				"El Parque Nacional Tayrona (entrada El Zaino) queda a ~40 km (~50–60 min en carro) por la Troncal del Caribe: sal entre 06:30 y 07:00 para aprovechar el clima fresco.",
				"Minca y sus fincas cafeteras en la Sierra Nevada están a ~45–55 min en carro, una escapada perfecta de medio día o día completo.",
				"El Centro Histórico y la Marina están a ~8 km (~20–25 min en carro), y los embarcaderos de El Rodadero a solo ~3 km (~5–10 min).",
				"Tanto la Propiedad 1606 (Bahía 87, desde {rate1606Cop}/noche) como la Propiedad 1707 (Bahía 95, desde {rate1707Cop}/noche) incluyen Parqueadero Asignado si alquilas vehículo.",
			],
			sections: [
				{
					id: "travel-times-matrix",
					heading: "Tiempos Reales de Traslado desde Edificio Salguero Sunset",
					paragraphs: [
						"Una de las mayores ventajas prácticas de Playa Salguero es su salida ágil hacia la vía principal. Desde Calle 26 # 2-80 te conectas rápidamente con la Troncal del Caribe sin cruzar los embotellamientos del centro de Santa Marta.",
						"Usa esta tabla para combinar salidas tempranas de aventura con atardeceres relajados en tu balcón frente al mar.",
					],
					comparisonTable: {
						caption:
							"Distancias y tiempos puerta a puerta desde Edificio Salguero Sunset (Calle 26 # 2-80, Playa Salguero)",
						headers: [
							"Destino",
							"Distancia aprox.",
							"Tiempo en carro / a pie",
							"Mejor horario y atractivos",
						],
						rows: [
							[
								"Playa Salguero",
								"~150 m (1 cuadra)",
								"~2 min caminando",
								"Baño matutino o caminata al atardecer",
							],
							[
								"El Rodadero y Muelles",
								"~3 km",
								"~5–10 min en carro",
								"Lanchas matutinas a Playa Blanca y Acuario; supermercados",
							],
							[
								"Centro Histórico y Marina",
								"~8 km",
								"~20–25 min en carro",
								"Paseo arquitectónico en la tarde, Marina y cena",
							],
							[
								"Aeropuerto Simón Bolívar (SMR)",
								"~10 km",
								"~15–20 min en carro",
								"Traslados directos por corredor costero",
							],
							[
								"Bahía de Taganga",
								"~13 km",
								"~30–35 min en carro",
								"Salidas de buceo y snorkel por la mañana",
							],
							[
								"Minca (Sierra Nevada)",
								"~22 km",
								"~45–55 min en carro",
								"Fincas cafeteras, Pozo Azul, Cascadas de Marinka y aves",
							],
							[
								"Parque Tayrona (El Zaino)",
								"~40 km",
								"~50–60 min en carro",
								"Salida 06:30 hacia Cañaveral, Arrecifes y Cabo San Juan",
							],
						],
					},
				},
				{
					id: "tayrona-and-minca",
					heading: "Parque Nacional Tayrona y Minca: Del Mar a la Montaña",
					paragraphs: [
						"El Parque Nacional Tayrona (entrada El Zaino, ~40 km / ~50–60 min en carro) es la joya natural del Caribe colombiano. Saliendo de Edificio Salguero Sunset a las 06:30 entras antes del pico de calor para caminar por Cañaveral, Arrecifes, La Piscina y Cabo San Juan del Guía. Lleva documento de identidad o pasaporte original, calzado cerrado, bloqueador biodegradable y suficiente hidratación.",
						"Minca (~45–55 min subiendo hacia la Sierra Nevada de Santa Marta) regala el contraste perfecto: brisa fresca de montaña, fincas de café orgánico como La Victoria, baños naturales en Pozo Azul y Cascadas de Marinka, y avistamiento de tucanes y colibríes.",
					],
					bullets: [
						"Verifica el calendario de cierres ecológicos del Parque Tayrona (Respira Tayrona, habitualmente breves jornadas en febrero, junio y octubre).",
						"Para Minca, contrata transporte local o tour 4x4 si planeas subir a fincas altas y miradores.",
						"¿Viajas en carro alquilado? Ambas Propiedades incluyen Parqueadero Asignado cubierto (Bahía 87 en 1606, Bahía 95 en 1707).",
					],
					factCards: [
						{
							label: "Tayrona (El Zaino)",
							value: "~50–60 min",
							detail: "~40 km por la Troncal del Caribe",
						},
						{
							label: "Minca (Sierra)",
							value: "~45–55 min",
							detail: "Café orgánico, cascadas y clima fresco",
						},
						{
							label: "Centro Histórico",
							value: "~20–25 min",
							detail: "~8 km hasta la Marina y Parque de los Novios",
						},
					],
				},
				{
					id: "historic-center-and-rodadero",
					heading: "Centro Histórico de Santa Marta, Marina y El Rodadero",
					paragraphs: [
						"Reserva las tardes y noches para el Centro Histórico (~8 km / ~20–25 min en carro). Recorre la Catedral Basílica, el Museo del Oro Tairona en la Casa de la Aduana, el malecón de la Marina Internacional y los restaurantes del Parque de los Novios antes de regresar en pocos minutos a la calma de Playa Salguero.",
						"Aún más cerca, El Rodadero (~3 km / ~5–10 min en carro) te conecta con las lanchas matutinas hacia Playa Blanca y el Acuario, además de supermercados y farmacias.",
					],
					callout: {
						label: "Truco Inteligente para el Martes",
						text: "La piscina de Edificio Salguero Sunset cierra los martes por mantenimiento programado: aprovecha ese día para visitar el Parque Tayrona o Minca y regresar a descansar con aire acondicionado, lavandería y Wi-Fi de 900 Mbps.",
					},
				},
			],
			faqsTitle: "Preguntas Frecuentes: Excursiones desde Playa Salguero",
			faqs: [
				{
					id: "faq-dt-1",
					question:
						"¿Cuánto tiempo se tarda desde Playa Salguero al Parque Nacional Tayrona?",
					answer:
						"Desde Edificio Salguero Sunset (Calle 26 # 2-80) hasta la entrada El Zaino del Parque Tayrona son ~40 km, aproximadamente 50–60 minutos en carro por la Troncal del Caribe.",
					defaultOpen: true,
				},
				{
					id: "faq-dt-2",
					question:
						"¿Puedo estacionar un vehículo alquilado en OceanViewFlats para hacer excursiones?",
					answer:
						"Sí. Cada Reserva Directa incluye Parqueadero Asignado dentro del edificio sin costo adicional por noche: Bahía 95 en la Propiedad 1707 (desde {rate1707Cop}/noche + {cleaning1707Cop} de Limpieza) y Bahía 87 en la Propiedad 1606 (desde {rate1606Cop}/noche + {cleaning1606Cop} de Limpieza). Solo registra la placa en el Registro de Huéspedes antes de llegar.",
				},
				{
					id: "faq-dt-3",
					question:
						"¿Cuál es el mejor día de la semana para visitar Tayrona o Minca desde el edificio?",
					answer:
						"El martes es especialmente recomendable porque la piscina del edificio cierra los martes por mantenimiento, y además los senderos de Tayrona y las cascadas de Minca tienen menos visitantes que en fin de semana.",
				},
				{
					id: "faq-dt-4",
					question:
						"¿A qué distancia están el Centro Histórico y El Rodadero desde Playa Salguero?",
					answer:
						"El Rodadero está a ~3 km (~5–10 minutos en carro) y el Centro Histórico y la Marina Internacional a ~8 km (~20–25 minutos en carro) desde Edificio Salguero Sunset.",
				},
			],
			ctaTitle: "Haz de Playa Salguero Tu Base Costera",
			ctaSubtitle:
				"Regresa de Tayrona y Minca a tu terraza frente al mar con Parqueadero Asignado, desde {portfolioLowCop} por noche sin comisión OTA.",
		},
		fr: {
			seoTitle: "Excursions depuis Playa Salguero | OceanViewFlats",
			seoDescription:
				"Excursions depuis Playa Salguero, Santa Marta : Parc Tayrona (50–60 min), Minca, Centre Historique (20–25 min) et El Rodadero. Dès {portfolioLowCop}.",
			ogImageAlt:
				"Côte caraïbe au coucher du soleil à Playa Salguero, Santa Marta—point de départ idéal pour Tayrona et Minca",
			heroImageAlt:
				"Panorama de la côte de Playa Salguero au coucher du soleil à Santa Marta, Colombie",
			breadcrumbLabel: "Excursions depuis Playa Salguero",
			badge: "Excursions & Logistique",
			title:
				"Excursions d'une Journée depuis Playa Salguero : Tayrona, Minca et Centre Historique",
			subtitle:
				"Temps de trajet réels, conseils horaires et organisation pratique depuis l'Edificio Salguero Sunset (Calle 26 # 2-80).",
			cardSummary:
				"Organisez facilement vos sorties vers le Parc National Tayrona, les cascades de Minca, le Centre Historique de Santa Marta et El Rodadero.",
			keyTakeawaysTitle: "Logistique d'Excursions en Un Coup d'Œil",
			keyTakeaways: [
				"Le Parc National Tayrona (entrée El Zaino) se situe à ~40 km (~50–60 min en voiture) via la Troncal del Caribe : partez vers 06h30–07h00 pour éviter chaleur et files d'attente.",
				"Minca et ses plantations de café dans la Sierra Nevada sont à ~45–55 min en voiture, parfait pour une escapade fraîche.",
				"Le Centre Historique et la Marina sont à ~8 km (~20–25 min en voiture), et El Rodadero à seulement ~3 km (~5–10 min).",
				"Les Propriétés 1606 (Place 87, dès {rate1606Cop}/nuit) et 1707 (Place 95, dès {rate1707Cop}/nuit) incluent chacune une Place de Parking Attribuée pour les voyageurs véhiculés.",
			],
			sections: [
				{
					id: "travel-times-matrix",
					heading: "Temps de Trajet Réels depuis l'Edificio Salguero Sunset",
					paragraphs: [
						"Playa Salguero offre un accès rapide aux grands axes routiers. Depuis Calle 26 # 2-80, vous rejoignez la Troncal del Caribe sans traverser les embouteillages du centre-ville.",
						"Appuyez-vous sur ce tableau pour alterner excursions matinales et retours au coucher du soleil face à la mer.",
					],
					comparisonTable: {
						caption:
							"Distances et temps de trajet porte-à-porte depuis l'Edificio Salguero Sunset (Calle 26 # 2-80)",
						headers: [
							"Destination",
							"Distance approx.",
							"Temps de trajet",
							"Meilleur moment & incontournables",
						],
						rows: [
							[
								"Plage de Playa Salguero",
								"~150 m (1 bloc)",
								"~2 min à pied",
								"Baignade matinale ou promenade au coucher du soleil",
							],
							[
								"El Rodadero & Embarcadères",
								"~3 km",
								"~5–10 min en voiture",
								"Bateaux matinaux vers Playa Blanca & Aquarium ; courses",
							],
							[
								"Centre Historique & Marina",
								"~8 km",
								"~20–25 min en voiture",
								"Balade coloniale l'après-midi, Marina et dîner",
							],
							[
								"Aéroport Simón Bolívar (SMR)",
								"~10 km",
								"~15–20 min en voiture",
								"Accès direct par l'avenue côtière",
							],
							[
								"Baie de Taganga",
								"~13 km",
								"~30–35 min en voiture",
								"Départs plongée et snorkeling le matin",
							],
							[
								"Minca (Sierra Nevada)",
								"~22 km",
								"~45–55 min en voiture",
								"Fincas de café bio, Pozo Azul, cascades Marinka & oiseaux",
							],
							[
								"Parc Tayrona (El Zaino)",
								"~40 km",
								"~50–60 min en voiture",
								"Départ 06h30 vers Cañaveral, Arrecifes et Cabo San Juan",
							],
						],
					},
				},
				{
					id: "tayrona-and-minca",
					heading: "Parc National Tayrona et Minca : Entre Plages et Montagnes",
					paragraphs: [
						"Le Parc National Tayrona (entrée El Zaino, ~40 km / ~50–60 min en voiture) est la réserve côtière emblématique de Colombie. En quittant l'Edificio Salguero Sunset vers 06h30, vous marchez confortablement de Cañaveral jusqu'à Cabo San Juan avant la chaleur de midi. Emportez votre passeport original, des chaussures fermées, une crème solaire biodégradable et beaucoup d'eau.",
						"Minca (~45–55 min dans les contreforts de la Sierra Nevada) offre un climat rafraîchissant : plantations de café biologique comme La Victoria, cascades de Pozo Azul et Marinka, et observation d'oiseaux tropicaux.",
					],
					bullets: [
						"Consultez les dates de fermeture écologique du Parc Tayrona (Respira Tayrona, courtes périodes en février, juin et octobre).",
						"Pour Minca, privilégiez un chauffeur local ou un 4x4 afin de rejoindre aisément les fincas en altitude.",
						"En voiture de location ? Nos deux Propriétés incluent un parking couvert gratuit (Place 87 pour 1606, Place 95 pour 1707).",
					],
					factCards: [
						{
							label: "Tayrona (El Zaino)",
							value: "~50–60 min",
							detail: "~40 km via la Troncal del Caribe",
						},
						{
							label: "Minca (Sierra)",
							value: "~45–55 min",
							detail: "Café bio, cascades et air frais",
						},
						{
							label: "Centre Historique",
							value: "~20–25 min",
							detail: "~8 km vers la Marina & Parque de los Novios",
						},
					],
				},
				{
					id: "historic-center-and-rodadero",
					heading: "Centre Historique de Santa Marta, Marina et El Rodadero",
					paragraphs: [
						"Gardez la fin d'après-midi pour le Centre Historique (~8 km / ~20–25 min en voiture) : Cathédrale Basilique, Musée de l'Or Tairona, coucher de soleil sur la Marina Internationale et dîner autour du Parque de los Novios avant de retrouver le calme de Playa Salguero.",
						"Tout près, El Rodadero (~3 km / ~5–10 min) sert d'embarcadère pour Playa Blanca et l'Aquarium ainsi que pour vos courses.",
					],
					callout: {
						label: "Astuce du Mardi",
						text: "La piscine de l'Edificio Salguero Sunset ferme chaque mardi pour entretien : c'est le jour idéal pour partir au Parc Tayrona ou à Minca et revenir profiter de la climatisation, de la buanderie et de la fibre 900 Mbps.",
					},
				},
			],
			faqsTitle: "Questions Fréquentes : Excursions depuis Playa Salguero",
			faqs: [
				{
					id: "faq-dt-1",
					question:
						"Combien de temps faut-il de Playa Salguero au Parc National Tayrona ?",
					answer:
						"Depuis l'Edificio Salguero Sunset (Calle 26 # 2-80), l'entrée principale El Zaino du Parc Tayrona se trouve à ~40 km, soit environ 50 à 60 minutes en voiture.",
					defaultOpen: true,
				},
				{
					id: "faq-dt-2",
					question:
						"Puis-je garer une voiture de location chez OceanViewFlats pour mes excursions ?",
					answer:
						"Oui. Chaque Réservation Directe inclut une Place de Parking Attribuée sans supplément : Place 95 pour la Propriété 1707 (dès {rate1707Cop}/nuit + {cleaning1707Cop} de Ménage) et Place 87 pour la Propriété 1606 (dès {rate1606Cop}/nuit + {cleaning1606Cop} de Ménage). Indiquez simplement votre plaque dans le Registre des Voyageurs.",
				},
				{
					id: "faq-dt-3",
					question:
						"Quel jour choisir pour visiter Tayrona ou Minca depuis l'immeuble ?",
					answer:
						"Le mardi est particulièrement judicieux : la piscine de la résidence est fermée le mardi pour entretien, et les sentiers de Tayrona et Minca sont bien moins fréquentés qu'en week-end.",
				},
				{
					id: "faq-dt-4",
					question:
						"À quelle distance se trouvent le Centre Historique et El Rodadero ?",
					answer:
						"El Rodadero est à ~3 km (~5–10 minutes en voiture) et le Centre Historique ainsi que la Marina à ~8 km (~20–25 minutes en voiture).",
				},
			],
			ctaTitle: "Faites de Playa Salguero Votre Camp de Base Côtier",
			ctaSubtitle:
				"Rentrez de Tayrona et Minca vers votre terrasse face à la mer avec Parking Attribué, dès {portfolioLowCop}/nuit sans frais OTA.",
		},
		it: {
			seoTitle: "Gite da Playa Salguero: Tayrona e Minca | OceanViewFlats",
			seoDescription:
				"Gite in giornata da Playa Salguero, Santa Marta: Tayrona (50–60 min), Minca, Centro Storico (20–25 min) e El Rodadero. Soggiorni da {portfolioLowCop}.",
			ogImageAlt:
				"Tramonto sulla costa caraibica di Playa Salguero, Santa Marta—base ideale per gite a Tayrona e Minca",
			heroImageAlt:
				"Panorama al tramonto sulla costa di Playa Salguero a Santa Marta, Colombia",
			breadcrumbLabel: "Gite da Playa Salguero",
			badge: "Escursioni & Logistica",
			title:
				"Gite in Giornata da Playa Salguero: Tayrona, Minca e Centro Storico",
			subtitle:
				"Tempi di percorrenza reali, orari consigliati e logistica pratica dall'Edificio Salguero Sunset (Calle 26 # 2-80).",
			cardSummary:
				"Organizza escursioni senza stress da Playa Salguero verso il Parco Nazionale Tayrona, le cascate di Minca, il Centro Storico e El Rodadero.",
			keyTakeawaysTitle: "Logistica Escursioni in Sintesi",
			keyTakeaways: [
				"Il Parco Nazionale Tayrona (ingresso El Zaino) dista ~40 km (~50–60 min in auto) sulla Troncal del Caribe: parti verso le 06:30–07:00 per evitare caldo e code.",
				"Minca e le sue fincas di caffè nella Sierra Nevada distano ~45–55 min in auto: clima fresco di montagna a meno di un'ora dal mare.",
				"Il Centro Storico e la Marina distano ~8 km (~20–25 min in auto) e i moli di El Rodadero solo ~3 km (~5–10 min).",
				"Sia la Proprietà 1606 (Posto 87, da {rate1606Cop}/notte) sia la Proprietà 1707 (Posto 95, da {rate1707Cop}/notte) includono Posto Auto Assegnato per chi noleggia un'auto.",
			],
			sections: [
				{
					id: "travel-times-matrix",
					heading: "Tempi Reali di Spostamento dall'Edificio Salguero Sunset",
					paragraphs: [
						"Playa Salguero si collega rapidamente alla circonvallazione costiera senza attraversare il traffico del centro di Santa Marta, riducendo i tempi verso tutte le principali mete.",
						"Usa questa tabella per abbinare escursioni mattutine e tramonti sul tuo balcone fronte mare.",
					],
					comparisonTable: {
						caption:
							"Distanze e tempi porta a porta dall'Edificio Salguero Sunset (Calle 26 # 2-80, Playa Salguero)",
						headers: [
							"Destinazione",
							"Distanza approx.",
							"Tempo di viaggio",
							"Orario ideale e punti salienti",
						],
						rows: [
							[
								"Spiaggia Playa Salguero",
								"~150 m (1 isolato)",
								"~2 min a piedi",
								"Bagno mattutino o passeggiata al tramonto",
							],
							[
								"El Rodadero e Moli",
								"~3 km",
								"~5–10 min in auto",
								"Barche mattutine per Playa Blanca e Acquario; spesa",
							],
							[
								"Centro Storico e Marina",
								"~8 km",
								"~20–25 min in auto",
								"Passeggiata pomeridiana, Marina e cena tipica",
							],
							[
								"Aeroporto Simón Bolívar (SMR)",
								"~10 km",
								"~15–20 min in auto",
								"Collegamento diretto lungo la costa",
							],
							[
								"Baia di Taganga",
								"~13 km",
								"~30–35 min in auto",
								"Immersioni e snorkeling al mattino",
							],
							[
								"Minca (Sierra Nevada)",
								"~22 km",
								"~45–55 min in auto",
								"Caffè biologico, Pozo Azul, cascate Marinka e birdwatching",
							],
							[
								"Parco Tayrona (El Zaino)",
								"~40 km",
								"~50–60 min in auto",
								"Partenza 06:30 per Cañaveral, Arrecifes e Cabo San Juan",
							],
						],
					},
				},
				{
					id: "tayrona-and-minca",
					heading: "Parco Nazionale Tayrona e Minca: Dal Mare alla Montagna",
					paragraphs: [
						"Il Parco Nazionale Tayrona (ingresso El Zaino, ~40 km / ~50–60 min in auto) unisce foresta tropicale e baie caraibiche spettacolari. Partendo alle 06:30 dall'Edificio Salguero Sunset raggiungi comodamente Cañaveral, Arrecifes, La Piscina e Cabo San Juan prima del caldo centrale. Porta passaporto originale, scarpe chiuse, protezione biodegradabile e acqua.",
						"Minca (~45–55 min verso la Sierra Nevada) regala aria fresca, piantagioni di caffè biologico come La Victoria, cascate naturali a Pozo Azul e Marinka e osservazione di tucani e colibrì.",
					],
					bullets: [
						"Controlla il calendario delle chiusure ecologiche del Tayrona (Respira Tayrona, brevi finestre in febbraio, giugno e ottobre).",
						"Per Minca affidati a un autista locale o tour 4x4 per raggiungere comodamente le fincas panoramiche.",
						"Con auto a noleggio? Entrambe le Proprietà includono parcheggio coperto gratuito (Posto 87 per 1606, Posto 95 per 1707).",
					],
					factCards: [
						{
							label: "Tayrona (El Zaino)",
							value: "~50–60 min",
							detail: "~40 km sulla Troncal del Caribe",
						},
						{
							label: "Minca (Sierra)",
							value: "~45–55 min",
							detail: "Caffè bio, cascate e aria fresca",
						},
						{
							label: "Centro Storico",
							value: "~20–25 min",
							detail: "~8 km fino a Marina e Parque de los Novios",
						},
					],
				},
				{
					id: "historic-center-and-rodadero",
					heading: "Centro Storico di Santa Marta, Marina ed El Rodadero",
					paragraphs: [
						"Dedica il tardo pomeriggio e la serata al Centro Storico (~8 km / ~20–25 min in auto): Cattedrale Basilica, Museo dell'Oro Tairona, tramonto alla Marina Internazionale e ristoranti intorno al Parque de los Novios, per poi rientrare nella quiete di Playa Salguero.",
						"Ancora più vicino, El Rodadero (~3 km / ~5–10 min) è il punto d'imbarco per Playa Blanca e l'Acquario oltre che comodo per la spesa.",
					],
					callout: {
						label: "Consiglio Furbo per il Martedì",
						text: "La piscina dell'Edificio Salguero Sunset è chiusa il martedì per manutenzione: il martedì è perfetto per visitare Tayrona o Minca e rientrare nel comfort di aria condizionata, lavanderia e fibra 900 Mbps.",
					},
				},
			],
			faqsTitle: "Domande Frequenti: Gite da Playa Salguero",
			faqs: [
				{
					id: "faq-dt-1",
					question:
						"Quanto tempo serve da Playa Salguero al Parco Nazionale Tayrona?",
					answer:
						"Dall'Edificio Salguero Sunset (Calle 26 # 2-80) all'ingresso principale El Zaino del Parco Tayrona ci sono ~40 km, circa 50–60 minuti in auto lungo la Troncal del Caribe.",
					defaultOpen: true,
				},
				{
					id: "faq-dt-2",
					question:
						"Posso parcheggiare un'auto a noleggio da OceanViewFlats per le escursioni?",
					answer:
						"Sì. Ogni Prenotazione Diretta include un Posto Auto Assegnato senza costi aggiuntivi: Posto 95 per la Proprietà 1707 (da {rate1707Cop}/notte + {cleaning1707Cop} di Pulizia) e Posto 87 per la Proprietà 1606 (da {rate1606Cop}/notte + {cleaning1606Cop} di Pulizia). Inserisci la targa nel Registro Ospiti prima dell'arrivo.",
				},
				{
					id: "faq-dt-3",
					question:
						"Qual è il giorno migliore per visitare Tayrona o Minca dall'edificio?",
					answer:
						"Il martedì è ideale perché la piscina condominiale chiude il martedì per manutenzione e i sentieri di Tayrona e Minca sono molto meno affollati rispetto al weekend.",
				},
				{
					id: "faq-dt-4",
					question:
						"Quanto distano il Centro Storico ed El Rodadero da Playa Salguero?",
					answer:
						"El Rodadero dista ~3 km (~5–10 minuti in auto) e il Centro Storico e la Marina Internazionale ~8 km (~20–25 minuti in auto).",
				},
			],
			ctaTitle: "Fai di Playa Salguero la Tua Base Costiera",
			ctaSubtitle:
				"Rientra da Tayrona e Minca sulla tua terrazza fronte mare con Posto Auto Assegnato, da {portfolioLowCop}/notte senza commissioni OTA.",
		},
		de: {
			seoTitle: "Tagesausflüge ab Playa Salguero & Tayrona | OceanViewFlats",
			seoDescription:
				"Tagesausflüge ab Playa Salguero, Santa Marta: Tayrona (50–60 Min.), Minca, Altstadt (20–25 Min.) & El Rodadero. Strandapartments ab {portfolioLowCop}.",
			ogImageAlt:
				"Karibischer Sonnenuntergang an der Küste von Playa Salguero, Santa Marta – Ausgangspunkt für Tayrona und Minca",
			heroImageAlt:
				"Küstenpanorama von Playa Salguero bei Sonnenuntergang in Santa Marta, Kolumbien",
			breadcrumbLabel: "Tagesausflüge ab Playa Salguero",
			badge: "Ausflüge & Logistik",
			title:
				"Tagesausflüge ab Playa Salguero: Tayrona, Minca & Historisches Zentrum",
			subtitle:
				"Realistische Fahrzeiten, Abfahrtstipps und Logistik direkt ab Edificio Salguero Sunset (Calle 26 # 2-80).",
			cardSummary:
				"Planen Sie entspannte Ausflüge ab Playa Salguero zum Tayrona-Nationalpark, in die Bergwelt von Minca, in die Altstadt von Santa Marta und nach El Rodadero.",
			keyTakeawaysTitle: "Ausflugslogistik auf einen Blick",
			keyTakeaways: [
				"Der Tayrona-Nationalpark (Eingang El Zaino) liegt ~40 km (~50–60 Min. per Auto) über die Troncal del Caribe – starten Sie gegen 06:30–07:00 Uhr vor der Mittagshitze.",
				"Minca mit seinen Bio-Kaffeefincas und Wasserfällen in der Sierra Nevada liegt ~45–55 Min. bergauf – ideal für erfrischende Bergluft.",
				"Santa Martas Historisches Zentrum und die Marina sind ~8 km (~20–25 Min. per Auto), El Rodadero nur ~3 km (~5–10 Min.) entfernt.",
				"Sowohl Unterkunft 1606 (Stellplatz 87, ab {rate1606Cop}/Nacht) als auch Unterkunft 1707 (Stellplatz 95, ab {rate1707Cop}/Nacht) beinhalten einen festen Parkplatz für Mietwagen.",
			],
			sections: [
				{
					id: "travel-times-matrix",
					heading: "Realistische Fahrzeiten ab Edificio Salguero Sunset",
					paragraphs: [
						"Playa Salguero bietet eine schnelle Anbindung an die Küsten-Umgehungsstraße, ohne dass Sie erst durch den Innenstadtverkehr von Santa Marta fahren müssen.",
						"Mit dieser Übersicht kombinieren Sie frühe Entdeckungstouren und entspannte Sonnenuntergänge auf Ihrem Balkon.",
					],
					comparisonTable: {
						caption:
							"Entfernungen und Tür-zu-Tür-Fahrzeiten ab Edificio Salguero Sunset (Calle 26 # 2-80, Playa Salguero)",
						headers: [
							"Ziel",
							"Ca. Entfernung",
							"Fahrt- / Gehzeit",
							"Beste Zeit & Highlights",
						],
						rows: [
							[
								"Strand Playa Salguero",
								"~150 m (1 Block)",
								"~2 Min. zu Fuß",
								"Morgenschwimmen oder Sonnenuntergangsspaziergang",
							],
							[
								"El Rodadero & Bootsanleger",
								"~3 km",
								"~5–10 Min. per Auto",
								"Morgenboote nach Playa Blanca & Aquarium; Einkauf",
							],
							[
								"Altstadt & Marina",
								"~8 km",
								"~20–25 Min. per Auto",
								"Spätnachmittag-Bummel, Marina & Abendessen",
							],
							[
								"Flughafen Simón Bolívar (SMR)",
								"~10 km",
								"~15–20 Min. per Auto",
								"Direkte Küstenanbindung für Inlandsflüge",
							],
							[
								"Fischerbucht Taganga",
								"~13 km",
								"~30–35 Min. per Auto",
								"Morgendliche Tauch- & Schnorchelausfahrten",
							],
							[
								"Minca (Sierra Nevada)",
								"~22 km",
								"~45–55 Min. per Auto",
								"Kaffeefincas, Pozo Azul, Marinka-Wasserfälle & Vogelwelt",
							],
							[
								"Tayrona-Nationalpark (El Zaino)",
								"~40 km",
								"~50–60 Min. per Auto",
								"Abfahrt 06:30 für Cañaveral, Arrecifes & Cabo San Juan",
							],
						],
					},
				},
				{
					id: "tayrona-and-minca",
					heading: "Tayrona-Nationalpark & Minca: Von der Küste in die Berge",
					paragraphs: [
						"Der Tayrona-Nationalpark (Haupteingang El Zaino, ~40 km / ~50–60 Min. per Auto) verbindet tropischen Regenwald mit Traumstränden. Bei Abfahrt um 06:30 Uhr ab Edificio Salguero Sunset wandern Sie angenehm über Cañaveral, Arrecifes und La Piscina bis Cabo San Juan. Nehmen Sie Ihren Original-Reisepass, feste Schuhe, biologisch abbaubaren Sonnenschutz und reichlich Wasser mit.",
						"Minca (~45–55 Min. hinauf in die Sierra Nevada) bietet den perfekten Kontrast: kühle Bergluft, Bio-Kaffeefarmen wie La Victoria, Naturpools am Pozo Azul und den Cascadas de Marinka sowie Tukane und Kolibris.",
					],
					bullets: [
						"Prüfen Sie vorab die saisonalen Naturschutz-Schließungen des Tayrona-Parks (Respira Tayrona, kurze Zeiträume im Februar, Juni und Oktober).",
						"Für abgelegene Fincas in Minca empfiehlt sich ein lokaler Fahrer oder eine 4x4-Tour.",
						"Mit Mietwagen unterwegs? Beide OceanViewFlats-Unterkünfte bieten kostenlose überdachte Stellplätze (Platz 87 für 1606, Platz 95 für 1707).",
					],
					factCards: [
						{
							label: "Tayrona (El Zaino)",
							value: "~50–60 Min.",
							detail: "~40 km über die Troncal del Caribe",
						},
						{
							label: "Minca (Sierra)",
							value: "~45–55 Min.",
							detail: "Kaffee, Wasserfälle & kühle Bergluft",
						},
						{
							label: "Altstadt & Marina",
							value: "~20–25 Min.",
							detail: "~8 km bis Parque de los Novios",
						},
					],
				},
				{
					id: "historic-center-and-rodadero",
					heading: "Historisches Zentrum von Santa Marta, Marina & El Rodadero",
					paragraphs: [
						"Am späten Nachmittag lohnt ein Ausflug ins Historische Zentrum (~8 km / ~20–25 Min. per Auto): Kathedrale, Goldmuseum (Museo del Oro Tairona), Sonnenuntergang an der Internationalen Marina und Abendessen rund um den Parque de los Novios, gefolgt von einer kurzen Rückfahrt ins ruhige Playa Salguero.",
						"Noch näher liegt El Rodadero (~3 km / ~5–10 Min.) mit Bootsverbindungen nach Playa Blanca, Aquarium und Supermärkten.",
					],
					callout: {
						label: "Smarter Dienstags-Tipp",
						text: "Da der Pool im Edificio Salguero Sunset dienstags wegen Wartung geschlossen bleibt, ist Dienstag der perfekte Tag für Tayrona oder Minca – danach warten Klimaanlage, Waschmaschine und 900 Mbit/s WLAN.",
					},
				},
			],
			faqsTitle: "Häufige Fragen: Tagesausflüge ab Playa Salguero",
			faqs: [
				{
					id: "faq-dt-1",
					question:
						"Wie lange fährt man von Playa Salguero zum Tayrona-Nationalpark?",
					answer:
						"Vom Edificio Salguero Sunset (Calle 26 # 2-80) bis zum Haupteingang El Zaino sind es ~40 km bzw. rund 50–60 Minuten Fahrt über die Troncal del Caribe.",
					defaultOpen: true,
				},
				{
					id: "faq-dt-2",
					question:
						"Kann ich einen Mietwagen für Ausflüge bei OceanViewFlats parken?",
					answer:
						"Ja. Jede Direktbuchung beinhaltet einen festen Stellplatz im Gebäude ohne Aufpreis: Stellplatz 95 für Unterkunft 1707 (ab {rate1707Cop}/Nacht + {cleaning1707Cop} Reinigung) und Stellplatz 87 für Unterkunft 1606 (ab {rate1606Cop}/Nacht + {cleaning1606Cop} Reinigung). Tragen Sie einfach das Kennzeichen in der Gästeregistrierung ein.",
				},
				{
					id: "faq-dt-3",
					question: "Welcher Wochentag ist ideal für Tayrona oder Minca?",
					answer:
						"Dienstag eignet sich besonders gut, da der Gebäudepool dienstags wegen Wartung geschlossen ist und die Wege im Tayrona-Park sowie in Minca unter der Woche deutlich ruhiger sind.",
				},
				{
					id: "faq-dt-4",
					question:
						"Wie weit sind Altstadt und El Rodadero von Playa Salguero entfernt?",
					answer:
						"El Rodadero liegt ~3 km (~5–10 Min. per Auto) und das Historische Zentrum samt Marina ~8 km (~20–25 Min. per Auto) entfernt.",
				},
			],
			ctaTitle: "Playa Salguero als Ihr Küsten-Basislager",
			ctaSubtitle:
				"Nach Tayrona und Minca zurück auf die Meerblick-Terrasse inkl. Stellplatz – ab {portfolioLowCop}/Nacht ohne OTA-Gebühren.",
		},
		ja: {
			seoTitle:
				"プラヤサルグエロ発の日帰り旅（タイロナ・ミンカ） | OceanViewFlats",
			seoDescription:
				"プラヤサルグエロ発のサンタマルタ日帰りガイド：タイロナ国立公園（約50〜60分）、ミンカ、旧市街（約20〜25分）、エル・ロダデロ。宿泊{portfolioLowCop}〜。",
			ogImageAlt:
				"タイロナ国立公園やミンカへの拠点となるサンタマルタ・プラヤ・サルグエロの夕暮れ海岸",
			heroImageAlt:
				"コロンビア・サンタマルタのプラヤ・サルグエロ海岸線と夕焼け",
			breadcrumbLabel: "プラヤ・サルグエロ発の日帰り旅",
			badge: "日帰り旅＆移動ガイド",
			title:
				"プラヤ・サルグエロ発の日帰りモデルコース：タイロナ・ミンカ・旧市街",
			subtitle:
				"エディフィシオ・サルグエロ・サンセット（Calle 26 # 2-80）からの実測移動時間、出発のコツ、周辺見どころをまとめました。",
			cardSummary:
				"タイロナ国立公園、シエラネバダのミンカ、サンタマルタ旧市街、エル・ロダデロへ効率よく巡る実践的な日帰りガイド。",
			keyTakeawaysTitle: "日帰り旅のポイント",
			keyTakeaways: [
				"タイロナ国立公園（エル・サイノ入口）は約40km（車で約50〜60分）。暑さと入場列を避けるため06:30〜07:00出発がおすすめです。",
				"シエラネバダ山麓のミンカ（コーヒー農園・滝）までは車で約45〜55分。海辺の暑さから離れて涼しい山間部を楽しめます。",
				"サンタマルタ旧市街＆マリーナは約8km（車で約20〜25分）、エル・ロダデロ桟橋は約3km（約5〜10分）です。",
				"物件1606（駐車区画87番・{rate1606Cop}/泊〜）・物件1707（駐車区画95番・{rate1707Cop}/泊〜）ともに専用駐車スペース付きでレンタカー旅にも便利です。",
			],
			sections: [
				{
					id: "travel-times-matrix",
					heading: "エディフィシオ・サルグエロ・サンセットからの所要時間一覧",
					paragraphs: [
						"プラヤ・サルグエロはサンタマルタ南部沿岸に位置するため、旧市街の渋滞を避けて幹線道路（Troncal del Caribe）へスムーズに合流できるのが大きな利点です。",
						"以下の目安時間をもとに、朝発の自然散策と夕方の海辺リラックスをバランスよく組み合わせてみてください。",
					],
					comparisonTable: {
						caption:
							"エディフィシオ・サルグエロ・サンセット（Calle 26 # 2-80）からの距離・所要時間",
						headers: [
							"目的地",
							"目安距離",
							"所要時間",
							"おすすめの時間帯・見どころ",
						],
						rows: [
							[
								"プラヤ・サルグエロ・ビーチ",
								"約150m（1ブロック）",
								"徒歩約2分",
								"朝泳ぎや夕暮れの海岸散歩",
							],
							[
								"エル・ロダデロ＆桟橋",
								"約3km",
								"車で約5〜10分",
								"プラヤ・ブランカ行き朝便・水族館・スーパー買い物",
							],
							[
								"サンタマルタ旧市街＆マリーナ",
								"約8km",
								"車で約20〜25分",
								"午後の歴史街散策、マリーナの夕日とディナー",
							],
							[
								"シモン・ボリバル空港（SMR）",
								"約10km",
								"車で約15〜20分",
								"沿岸ルートで国内各都市へ直結",
							],
							[
								"タガンガ漁村",
								"約13km",
								"車で約30〜35分",
								"午前発のダイビング・シュノーケリング拠点",
							],
							[
								"ミンカ（シエラネバダ山麓）",
								"約22km",
								"車で約45〜55分",
								"有機コーヒー農園、ポソ・アスル、マリンカの滝、野鳥観察",
							],
							[
								"タイロナ国立公園（エル・サイノ）",
								"約40km",
								"車で約50〜60分",
								"06:30発でカニャベラル〜カボ・サン・ファン散策",
							],
						],
					},
				},
				{
					id: "tayrona-and-minca",
					heading: "タイロナ国立公園＆ミンカ：カリブ海と熱帯山岳の名所",
					paragraphs: [
						"タイロナ国立公園（エル・サイノ入口、約40km／車で約50〜60分）は熱帯雨林と秘境ビーチが交わるコロンビア屈指の自然保護区です。06:30に出発すれば日中の暑さが強まる前にカニャベラルからアレシーフェス、ラ・ピシーナ、カボ・サン・ファンまで快適に歩けます。入園登録用のパスポート原本、歩きやすい靴、生分解性日焼け止め、十分な飲料水をご持参ください。",
						"ミンカ（車で約45〜55分）では一転して爽やかな高原気候を楽しめます。老舗有機コーヒー農園ラ・ビクトリアの見学、ポソ・アスルやマリンカの滝での水浴び、オオハシやハチドリの観察が人気です。",
					],
					bullets: [
						"タイロナ国立公園は環境保全の定期休園期間（例年2月・6月・10月の数日間）があるため事前確認がおすすめです。",
						"ミンカ奥地の農園や展望台へは急坂が多いため、現地ドライバーまたは4WDツアーの利用が快適です。",
						"レンタカー利用時も安心：物件1606は駐車区画87番、物件1707は駐車区画95番を無料でご利用いただけます。",
					],
					factCards: [
						{
							label: "タイロナ（エル・サイノ）",
							value: "約50〜60分",
							detail: "幹線道路経由で約40km",
						},
						{
							label: "ミンカ山麓",
							value: "約45〜55分",
							detail: "コーヒー農園・滝・涼しい気候",
						},
						{
							label: "サンタマルタ旧市街",
							value: "約20〜25分",
							detail: "マリーナ＆ノビオス公園まで約8km",
						},
					],
				},
				{
					id: "historic-center-and-rodadero",
					heading: "サンタマルタ旧市街・国際マリーナ＆エル・ロダデロ",
					paragraphs: [
						"夕方以降はサンタマルタ旧市街（約8km／車で約20〜25分）へ。白亜の大聖堂やタイロナ黄金博物館を見学し、国際マリーナで夕日を眺めたあと、パルケ・デ・ロス・ノビオス周辺でディナーを楽しんでから静かなプラヤ・サルグエロへ戻れます。",
						"より身近なエル・ロダデロ（約3km／車で約5〜10分）はプラヤ・ブランカや水族館行きボートの拠点であり、食料品調達にも便利です。",
					],
					callout: {
						label: "火曜日を賢く使うコツ",
						text: "エディフィシオ・サルグエロ・サンセットのプールは毎週火曜日にメンテナンス休業となるため、火曜日にタイロナ国立公園やミンカの日帰り旅を組むと滞在を最大限に満喫できます。",
					},
				},
			],
			faqsTitle: "よくある質問：プラヤ・サルグエロ発の日帰り旅",
			faqs: [
				{
					id: "faq-dt-1",
					question:
						"プラヤ・サルグエロからタイロナ国立公園までどれくらいかかりますか？",
					answer:
						"エディフィシオ・サルグエロ・サンセット（Calle 26 # 2-80）からメイン入口のエル・サイノまで約40km、車で約50〜60分です。",
					defaultOpen: true,
				},
				{
					id: "faq-dt-2",
					question:
						"日帰り観光用のレンタカーをOceanViewFlatsに駐車できますか？",
					answer:
						"はい。直接予約には建物内の専用駐車スペースが追加料金なしで付帯します（物件1707：駐車区画95番・{rate1707Cop}/泊〜＋清掃{cleaning1707Cop}、物件1606：駐車区画87番・{rate1606Cop}/泊〜＋清掃{cleaning1606Cop}）。到着前のゲスト登録にナンバープレートをご入力ください。",
				},
				{
					id: "faq-dt-3",
					question: "タイロナやミンカへ行くのに最適な曜日はいつですか？",
					answer:
						"建物のプールがメンテナンス休業となる火曜日が特におすすめです。平日で公園や滝の混雑も週末より落ち着いています。",
				},
				{
					id: "faq-dt-4",
					question: "サンタマルタ旧市街とエル・ロダデロまでの距離は？",
					answer:
						"エル・ロダデロまで約3km（車で約5〜10分）、サンタマルタ旧市街・国際マリーナまで約8km（車で約20〜25分）です。",
				},
			],
			ctaTitle: "プラヤ・サルグエロを観光の海辺拠点に",
			ctaSubtitle:
				"タイロナやミンカの帰りに専用駐車場＆海辺テラスへ。OTA手数料0%・{portfolioLowCop}/泊〜。",
		},
	},
};
