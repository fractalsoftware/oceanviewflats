import { IMAGE_DIMENSIONS, IMAGES } from "../../constants/config";
import type { DestinationGuideDefinition } from "./types";

export const PLAYA_SALGUERO_VS_EL_RODADERO_GUIDE: DestinationGuideDefinition = {
	slug: "playa-salguero-vs-el-rodadero",
	id: "destination-guide-playa-salguero-vs-el-rodadero",
	categoryIcon: "waves",
	heroImage: IMAGES["1606"].hero,
	heroImageDimensions: IMAGE_DIMENSIONS["1606"].hero,
	ogImage: {
		path: IMAGES["1606"].hero,
		...IMAGE_DIMENSIONS["1606"].hero,
	},
	datePublished: "2026-11-26",
	dateModified: "2026-11-26",
	readingTimeMinutes: 7,
	relatedProperties: ["1606", "1707"],
	relatedGuideSlugs: [
		"day-trips-from-playa-salguero",
		"direct-reservation-guide",
		"digital-nomad-remote-work",
	],
	locales: {
		en: {
			seoTitle: "Playa Salguero vs. El Rodadero Guide | OceanViewFlats",
			seoDescription:
				"Playa Salguero vs. El Rodadero in Santa Marta: compare beach quiet, safety, SMR airport access, and beachfront stays from {portfolioLowCop}.",
			ogImageAlt:
				"Caribbean Sea sunset horizon and beachfront balcony overlooking Playa Salguero in Santa Marta, Colombia",
			heroImageAlt:
				"Unobstructed oceanfront view over Playa Salguero coastline from Edificio Salguero Sunset in Santa Marta",
			breadcrumbLabel: "Playa Salguero vs. El Rodadero",
			badge: "Neighborhood Comparison",
			title: "Playa Salguero vs. El Rodadero: Where to Stay in Santa Marta",
			subtitle:
				"An honest local comparison of beach atmosphere, residential tranquility, family safety, sunset panoramas, and Simón Bolívar Airport (SMR) access.",
			cardSummary:
				"Compare Santa Marta's quiet residential beachfront towers at Playa Salguero with the high-energy El Rodadero promenade before choosing your stay.",
			keyTakeawaysTitle: "Key Takeaways at a Glance",
			keyTakeaways: [
				"Playa Salguero is a modern residential beachfront district ~150 m (1 block) from wide sand, offering far less beach-vendor congestion than central El Rodadero.",
				"Edificio Salguero Sunset sits ~3 km (~5–10 min by car) south of El Rodadero, so Guests enjoy peaceful nights while staying minutes from Rodadero's dining and piers.",
				"Simón Bolívar International Airport (SMR) is only ~10 km (~15–20 min by car) away, avoiding Santa Marta's heavier downtown traffic.",
				"Direct Reservation Nightly Rates at Edificio Salguero Sunset start at {rate1606Cop} for Property 1606 and {rate1707Cop} for Property 1707 ({minStay1606}-night baseline minimum).",
			],
			sections: [
				{
					id: "atmosphere-and-beach",
					heading: "Beach Quality, Crowd Density & Sunset Atmosphere",
					paragraphs: [
						"Travelers planning a Santa Marta coastal stay almost always weigh two adjacent neighborhoods: El Rodadero and Playa Salguero. Although they share the same stretch of Caribbean coastline south of downtown Santa Marta, the daily experience on the sand feels remarkably different.",
						"El Rodadero is Santa Marta's classic high-density resort strip. Its central bay is lined with bustling boardwalks, excursion boats departing for Playa Blanca, loud weekend beach music, and constant vendor foot traffic. Playa Salguero, immediately to the south, developed later as an upscale residential corridor of contemporary high-rise towers with controlled condominium reception desks and significantly quieter shoreline space.",
					],
					bullets: [
						"Wider, calmer sand: Playa Salguero offers open sightlines toward the western Caribbean horizon without densely packed tent rows.",
						"Direct west-facing sunsets: Upper floors at Edificio Salguero Sunset (16th and 17th floors) overlook uninterrupted ocean sunsets year-round.",
						"Sleep-friendly evenings: Residential towers enforce quiet hours and condominium access control, ideal for families, couples, and remote professionals.",
					],
					factCards: [
						{
							label: "Beach Walk",
							value: "~150 m",
							detail: "1 block (~2 min walk) to Playa Salguero sand",
						},
						{
							label: "El Rodadero",
							value: "~5–10 min",
							detail: "~3 km north by taxi or rideshare",
						},
						{
							label: "SMR Airport",
							value: "~15–20 min",
							detail: "~10 km south via coastal corridor",
						},
					],
				},
				{
					id: "side-by-side-comparison",
					heading: "Side-by-Side Comparison: Playa Salguero vs. El Rodadero",
					paragraphs: [
						"Choosing between the two neighborhoods comes down to whether you want nightlife and boardwalk bustle directly beneath your balcony or a serene beachfront home base with fast access to both El Rodadero and the airport.",
					],
					comparisonTable: {
						caption:
							"Neighborhood comparison between Playa Salguero (Edificio Salguero Sunset) and central El Rodadero",
						headers: ["Factor", "Playa Salguero", "Central El Rodadero"],
						rows: [
							[
								"Neighborhood Character",
								"Modern residential beachfront towers, calmer atmosphere",
								"High-density tourist promenade, busy day and night",
							],
							[
								"Beach Experience",
								"Spacious sand, fewer vendors, direct sunset horizon",
								"Crowded bay, excursion piers, active boardwalk trade",
							],
							[
								"Noise & Sleep Quality",
								"Quiet condominium environment with 24/7 security reception",
								"Street music, traffic, and late-night commercial activity",
							],
							[
								"SMR Airport Proximity",
								"~10 km (~15–20 min by car)",
								"~12–13 km (~20–30 min depending on strip traffic)",
							],
							[
								"Dining & Groceries",
								"Beachfront cafés and restaurants nearby; 5–10 min to Rodadero supermarkets",
								"Dense walkable strip of fast-casual restaurants and shops",
							],
							[
								"Parking & Security",
								"Deeded Assigned Parking Spaces (Bay 95 for 1707, Bay 87 for 1606)",
								"Limited street parking; congested weekend access",
							],
						],
					},
				},
				{
					id: "edificio-salguero-sunset",
					heading: "Why Stay at Edificio Salguero Sunset (Calle 26 # 2-80)",
					paragraphs: [
						"Edificio Salguero Sunset places you one block (~150 m) from Playa Salguero beach with 24/7 front-desk security, a swimming pool (open daily except Tuesdays for scheduled maintenance), and deeded Assigned Parking Spaces inside the building.",
						"OceanViewFlats operates two legally registered beachfront residences one floor apart: Property 1707 on the 17th floor (RNT #268781, 2 Bedrooms / 4 Beds, Assigned Parking Space Bay 95, baseline Nightly Rate {rate1707Cop} plus {cleaning1707Cop} Cleaning Fee) and Property 1606 on the 16th floor (RNT #270413, 2 Bedrooms / 3 Beds, Assigned Parking Space Bay 87, baseline Nightly Rate {rate1606Cop} up to {rate1606HighCop} in peak Seasonal Rate Tiers plus {cleaning1606Cop} Cleaning Fee). Both accommodate a Maximum Occupancy of 6 Guests with up to 900 Mbps fiber Wi-Fi.",
					],
					callout: {
						label: "Local Host Tip",
						text: "Plan pool afternoons around Tuesday maintenance closures—Tuesdays are ideal for a Tayrona National Park or Minca excursion, or a quiet sunset walk along Playa Salguero beach. Check-in is 15:00, check-out is 11:00, and the condominium administration collects a {buildingRegistrationFeeCop} per-person Building Registration Fee at reception.",
					},
				},
			],
			faqsTitle: "Frequently Asked Questions: Playa Salguero vs. El Rodadero",
			faqs: [
				{
					id: "faq-ps-1",
					question:
						"Is Playa Salguero safe for families and solo travelers in Santa Marta?",
					answer:
						"Yes. Playa Salguero is one of Santa Marta's most established residential coastal zones, characterized by modern condominium towers with 24/7 security reception desks, well-lit beachfront avenues, and calmer foot traffic than central El Rodadero.",
					defaultOpen: true,
				},
				{
					id: "faq-ps-2",
					question:
						"How far is Edificio Salguero Sunset from El Rodadero and Santa Marta Historic Center?",
					answer:
						"Edificio Salguero Sunset (Calle 26 # 2-80) is ~3 km (~5–10 minutes by car) from El Rodadero, ~8 km (~20–25 minutes by car) from Santa Marta Historic Center and Marina, and ~10 km (~15–20 minutes by car) from Simón Bolívar International Airport (SMR).",
				},
				{
					id: "faq-ps-3",
					question: "Can you walk to Playa Salguero beach from OceanViewFlats?",
					answer:
						"Yes. Playa Salguero beach is approximately 150 meters (1 block, a ~2-minute walk) from the main entrance of Edificio Salguero Sunset.",
				},
				{
					id: "faq-ps-4",
					question:
						"Should we choose Property 1606 or Property 1707 at Edificio Salguero Sunset?",
					answer:
						"Choose Property 1606 (16th floor, 2 Bedrooms / 3 Beds: 1 Queen, 1 Double, 1 Pull-out Sofa Bed, Bay 87, from {rate1606Cop}/night + {cleaning1606Cop} Cleaning Fee) for couples or families seeking a warm sunset terrace. Choose Property 1707 (17th floor, 2 Bedrooms / 4 Beds: 1 Queen, 2 Twins, 1 Pull-out Sofa Bed, Bay 95, from {rate1707Cop}/night + {cleaning1707Cop} Cleaning Fee) if you need four separate beds or a dedicated ergonomic remote-work desk.",
				},
			],
			ctaTitle: "Stay Steps from Playa Salguero Beach",
			ctaSubtitle:
				"Compare live calendars and transparent COP Quotes with 0% OTA commission—starting from {portfolioLowCop} per night.",
		},
		es: {
			seoTitle: "Playa Salguero vs. El Rodadero: Guía | OceanViewFlats",
			seoDescription:
				"Playa Salguero vs. El Rodadero en Santa Marta: compara tranquilidad, seguridad, acceso al aeropuerto SMR y estadías frente al mar desde {portfolioLowCop}.",
			ogImageAlt:
				"Atardecer sobre el mar Caribe desde balcón frente a Playa Salguero en Santa Marta, Colombia",
			heroImageAlt:
				"Vista panorámica frente al mar sobre Playa Salguero desde Edificio Salguero Sunset en Santa Marta",
			breadcrumbLabel: "Playa Salguero vs. El Rodadero",
			badge: "Comparativa de Zonas",
			title: "Playa Salguero vs. El Rodadero: Dónde Hospedarse en Santa Marta",
			subtitle:
				"Comparativa local y honesta sobre ambiente de playa, tranquilidad residencial, seguridad familiar, atardeceres y cercanía al Aeropuerto Simón Bolívar (SMR).",
			cardSummary:
				"Compara las torres residenciales tranquilas de Playa Salguero con el movimiento de El Rodadero antes de elegir tu estadía frente al mar.",
			keyTakeawaysTitle: "Puntos Clave en Resumen",
			keyTakeaways: [
				"Playa Salguero es un sector residencial moderno a ~150 m (1 cuadra) de una playa amplia y mucho menos congestionada que el centro de El Rodadero.",
				"Edificio Salguero Sunset está a ~3 km (~5–10 min en carro) al sur de El Rodadero: disfrutas noches silenciosas a minutos de restaurantes y muelles.",
				"El Aeropuerto Internacional Simón Bolívar (SMR) queda a solo ~10 km (~15–20 min en carro), evitando el tráfico pesado del centro.",
				"Las Tarifas por Noche de Reserva Directa comienzan en {rate1606Cop} en la Propiedad 1606 y {rate1707Cop} en la Propiedad 1707 (estadía mínima base de {minStay1606} noches).",
			],
			sections: [
				{
					id: "atmosphere-and-beach",
					heading: "Calidad de Playa, Densidad y Ambiente al Atardecer",
					paragraphs: [
						"Al planificar un viaje a Santa Marta, la gran decisión de hospedaje suele estar entre El Rodadero y Playa Salguero. Aunque comparten la misma franja del mar Caribe al sur del centro, la experiencia cotidiana cambia por completo entre ambos sectores.",
						"El Rodadero es el corredor turístico clásico de alta densidad, con malecón muy concurrido, embarcaderos hacia Playa Blanca, música frecuente en fines de semana y alto flujo comercial. Playa Salguero, justo al sur, se consolidó como una zona residencial contemporánea de edificios con recepción 24/7 y una franja de arena mucho más serena.",
					],
					bullets: [
						"Arena amplia y despejada: menos saturación de carpas y vendedores ambulantes que en la bahía central de El Rodadero.",
						"Atardeceres frontales al occidente: desde los pisos 16 y 17 del Edificio Salguero Sunset el horizonte marino permanece completamente abierto.",
						"Descanso nocturno real: reglamento de copropiedad y control de ingreso en portería, ideal para familias, parejas y profesionales remotos.",
					],
					factCards: [
						{
							label: "Playa a Pie",
							value: "~150 m",
							detail: "1 cuadra (~2 min caminando) hasta Playa Salguero",
						},
						{
							label: "El Rodadero",
							value: "~5–10 min",
							detail: "~3 km hacia el norte en taxi o plataforma",
						},
						{
							label: "Aeropuerto SMR",
							value: "~15–20 min",
							detail: "~10 km hacia el sur por vía costera",
						},
					],
				},
				{
					id: "side-by-side-comparison",
					heading: "Comparativa Directa: Playa Salguero vs. El Rodadero",
					paragraphs: [
						"Elegir entre ambas zonas depende de si buscas comercio intenso debajo del balcón o una base tranquila frente al mar con conexión rápida hacia El Rodadero, el Centro Histórico y el aeropuerto.",
					],
					comparisonTable: {
						caption:
							"Comparación entre Playa Salguero (Edificio Salguero Sunset) y el centro de El Rodadero",
						headers: ["Criterio", "Playa Salguero", "Centro de El Rodadero"],
						rows: [
							[
								"Carácter del Sector",
								"Torres residenciales modernas y ambiente sereno",
								"Corredor turístico de alta densidad día y noche",
							],
							[
								"Experiencia de Playa",
								"Arena amplia, menor congestión y vista limpia al atardecer",
								"Bahía muy concurrida, muelles de lanchas y alto flujo comercial",
							],
							[
								"Ruido y Descanso",
								"Entorno residencial tranquilo con recepción 24/7",
								"Música en exteriores, tráfico y vida nocturna constante",
							],
							[
								"Cercanía al Aeropuerto SMR",
								"~10 km (~15–20 min en carro)",
								"~12–13 km (~20–30 min según congestión)",
							],
							[
								"Gastronomía y Compras",
								"Cafés y restaurantes costeros cercanos; 5–10 min a supermercados de Rodadero",
								"Alta concentración comercial y restaurantes a pocos pasos",
							],
							[
								"Parqueadero y Seguridad",
								"Parqueadero Asignado privado (Bahía 95 en 1707, Bahía 87 en 1606)",
								"Estacionamiento limitado y tráfico denso en temporada alta",
							],
						],
					},
				},
				{
					id: "edificio-salguero-sunset",
					heading: "Por Qué Elegir Edificio Salguero Sunset (Calle 26 # 2-80)",
					paragraphs: [
						"Edificio Salguero Sunset te ubica a una cuadra (~150 m) de la arena de Playa Salguero, con recepción y vigilancia 24/7, piscina (abierta todos los días excepto los martes por mantenimiento programado) y Parqueadero Asignado dentro del edificio.",
						"OceanViewFlats opera dos Propiedades registradas con RNT vigente a un piso de distancia: Propiedad 1707 en el piso 17 (RNT #268781, 2 Habitaciones / 4 Camas, Bahía 95, Tarifa por Noche base {rate1707Cop} más {cleaning1707Cop} de Tarifa de Limpieza) y Propiedad 1606 en el piso 16 (RNT #270413, 2 Habitaciones / 3 Camas, Bahía 87, Tarifa por Noche base {rate1606Cop} hasta {rate1606HighCop} en Temporada Alta más {cleaning1606Cop} de Tarifa de Limpieza). Ambas tienen Ocupación Máxima de 6 Huéspedes y fibra óptica de hasta 900 Mbps.",
					],
					callout: {
						label: "Consejo de Anfitrión Local",
						text: "Ten en cuenta que la piscina cierra los martes por mantenimiento: es el día perfecto para una excursión al Parque Tayrona o Minca. El check-in es a las 15:00, el check-out a las 11:00 y la administración cobra una Tarifa de Registro del Edificio de {buildingRegistrationFeeCop} por persona en recepción.",
					},
				},
			],
			faqsTitle: "Preguntas Frecuentes: Playa Salguero vs. El Rodadero",
			faqs: [
				{
					id: "faq-ps-1",
					question:
						"¿Es seguro Playa Salguero para familias y viajeros en Santa Marta?",
					answer:
						"Sí. Playa Salguero es una de las zonas residenciales costeras más tranquilas de Santa Marta, con edificios modernos, vigilancia y recepción 24/7, calles iluminadas y mucho menos bullicio que el centro de El Rodadero.",
					defaultOpen: true,
				},
				{
					id: "faq-ps-2",
					question:
						"¿A qué distancia queda Edificio Salguero Sunset de El Rodadero y del Centro Histórico?",
					answer:
						"Edificio Salguero Sunset (Calle 26 # 2-80) está a ~3 km (~5–10 minutos en carro) de El Rodadero, a ~8 km (~20–25 minutos) del Centro Histórico y la Marina de Santa Marta, y a ~10 km (~15–20 minutos) del Aeropuerto Simón Bolívar (SMR).",
				},
				{
					id: "faq-ps-3",
					question: "¿Se puede ir caminando a la playa desde OceanViewFlats?",
					answer:
						"Sí. Playa Salguero se encuentra a unos 150 metros (1 cuadra, ~2 minutos caminando) desde la entrada principal del Edificio Salguero Sunset.",
				},
				{
					id: "faq-ps-4",
					question:
						"¿Conviene más la Propiedad 1606 o la Propiedad 1707 en Edificio Salguero Sunset?",
					answer:
						"La Propiedad 1606 (piso 16, 2 Habitaciones / 3 Camas: 1 Queen, 1 Doble, 1 Sofá Cama, Bahía 87, desde {rate1606Cop}/noche + {cleaning1606Cop} de Limpieza) es ideal para parejas y familias que aman una terraza cálida. La Propiedad 1707 (piso 17, 2 Habitaciones / 4 Camas: 1 Queen, 2 Sencillas, 1 Sofá Cama, Bahía 95, desde {rate1707Cop}/noche + {cleaning1707Cop} de Limpieza) sobresale para trabajo remoto con escritorio dedicado o grupos que prefieren cuatro camas separadas.",
				},
			],
			ctaTitle: "Hospédate a Pasos de Playa Salguero",
			ctaSubtitle:
				"Consulta disponibilidad real y Cotizaciones transparentes en COP sin comisión OTA, desde {portfolioLowCop} por noche.",
		},
		fr: {
			seoTitle: "Playa Salguero vs El Rodadero Santa Marta | OceanViewFlats",
			seoDescription:
				"Playa Salguero vs El Rodadero à Santa Marta : comparez le calme des plages, la sécurité, l'accès aéroport SMR et nos séjours dès {portfolioLowCop}.",
			ogImageAlt:
				"Coucher de soleil sur la mer des Caraïbes face à Playa Salguero à Santa Marta, Colombie",
			heroImageAlt:
				"Vue panoramique sur la côte de Playa Salguero depuis l'Edificio Salguero Sunset à Santa Marta",
			breadcrumbLabel: "Playa Salguero vs. El Rodadero",
			badge: "Comparatif Quartiers",
			title: "Playa Salguero vs. El Rodadero : Où Séjourner à Santa Marta",
			subtitle:
				"Comparatif local entre calme résidentiel, qualité de plage, sécurité familiale, couchers de soleil et proximité de l'aéroport Simón Bolívar (SMR).",
			cardSummary:
				"Comparez les résidences calmes en bord de mer de Playa Salguero avec l'animation d'El Rodadero avant de réserver votre séjour à Santa Marta.",
			keyTakeawaysTitle: "L'Essentiel en Un Coup d'Œil",
			keyTakeaways: [
				"Playa Salguero est un quartier résidentiel moderne à ~150 m (1 pâté de maisons) d'une large plage, bien moins saturée que le centre d'El Rodadero.",
				"L'Edificio Salguero Sunset se trouve à ~3 km (~5–10 min en voiture) au sud d'El Rodadero : nuits calmes tout en restant à quelques minutes des restaurants et embarcadères.",
				"L'aéroport international Simón Bolívar (SMR) n'est qu'à ~10 km (~15–20 min en voiture), sans traverser les embouteillages du centre.",
				"Nos Tarifs par Nuit en Réservation Directe débutent à {rate1606Cop} pour la Propriété 1606 et {rate1707Cop} pour la Propriété 1707 (séjour min. de base : {minStay1606} nuits).",
			],
			sections: [
				{
					id: "atmosphere-and-beach",
					heading: "Qualité de la Plage, Affluence et Couchers de Soleil",
					paragraphs: [
						"Pour un séjour côtier à Santa Marta, le choix se joue presque toujours entre El Rodadero et Playa Salguero. Bien que voisins sur le même littoral caraïbe, les deux quartiers offrent des ambiances très distinctes.",
						"El Rodadero est la station balnéaire historique à forte densité : promenade animée, départs de bateaux vers Playa Blanca, musique sur la plage le week-end et passage commercial constant. Juste au sud, Playa Salguero s'est développé comme un secteur résidentiel contemporain aux tours sécurisées et au rivage nettement plus paisible.",
					],
					bullets: [
						"Plage plus ouverte : moins de vendeurs itinérants et vue dégagée sur l'horizon caraïbe.",
						"Couchers de soleil plein ouest : depuis les 16e et 17e étages de l'Edificio Salguero Sunset, le panorama sur la mer reste totalement dégagé.",
						"Nuits reposantes : règlement de copropriété et conciergerie 24h/24, parfait pour les familles, couples et télétravailleurs.",
					],
					factCards: [
						{
							label: "Accès Plage",
							value: "~150 m",
							detail: "1 bloc (~2 min à pied) jusqu'au sable",
						},
						{
							label: "El Rodadero",
							value: "~5–10 min",
							detail: "~3 km au nord en taxi ou VTC",
						},
						{
							label: "Aéroport SMR",
							value: "~15–20 min",
							detail: "~10 km au sud par l'axe côtier",
						},
					],
				},
				{
					id: "side-by-side-comparison",
					heading: "Comparatif Détaillé : Playa Salguero vs. El Rodadero",
					paragraphs: [
						"Le choix dépend de votre priorité : animation permanente au pied de l'immeuble ou base balnéaire sereine à quelques minutes des commerces d'El Rodadero et de l'aéroport.",
					],
					comparisonTable: {
						caption:
							"Comparatif entre Playa Salguero (Edificio Salguero Sunset) et le centre d'El Rodadero",
						headers: ["Critère", "Playa Salguero", "Centre d'El Rodadero"],
						rows: [
							[
								"Atmosphère",
								"Tours résidentielles modernes et environnement calme",
								"Promenade touristique dense, animée jour et nuit",
							],
							[
								"Plage",
								"Sable large, moins de foule, coucher de soleil direct",
								"Baie très fréquentée, embarcadères et commerce actif",
							],
							[
								"Calme Nocturne",
								"Résidence paisible avec réception sécurisée 24h/24",
								"Animation de rue, circulation et musique tardive",
							],
							[
								"Distance Aéroport SMR",
								"~10 km (~15–20 min en voiture)",
								"~12–13 km (~20–30 min selon la circulation)",
							],
							[
								"Restaurants & Courses",
								"Cafés côtiers à proximité ; 5–10 min des supermarchés d'El Rodadero",
								"Nombreux commerces et restaurants accessibles à pied",
							],
							[
								"Parking & Sécurité",
								"Place de Parking Attribuée (Place 95 pour 1707, Place 87 pour 1606)",
								"Stationnement limité et accès encombré le week-end",
							],
						],
					},
				},
				{
					id: "edificio-salguero-sunset",
					heading: "Séjourner à l'Edificio Salguero Sunset (Calle 26 # 2-80)",
					paragraphs: [
						"L'Edificio Salguero Sunset vous place à ~150 m de Playa Salguero avec réception sécurisée 24h/24, piscine (ouverte tous les jours sauf le mardi pour entretien) et Place de Parking Attribuée dans l'immeuble.",
						"OceanViewFlats propose deux Propriétés enregistrées au RNT à un étage d'intervalle : la Propriété 1707 au 17e étage (RNT #268781, 2 Chambres / 4 Lits, Place 95, Tarif par Nuit dès {rate1707Cop} + {cleaning1707Cop} de Frais de Ménage) et la Propriété 1606 au 16e étage (RNT #270413, 2 Chambres / 3 Lits, Place 87, Tarif par Nuit de {rate1606Cop} jusqu'à {rate1606HighCop} en haute saison + {cleaning1606Cop} de Frais de Ménage). Capacité maximale de 6 Voyageurs et fibre jusqu'à 900 Mbps dans les deux logements.",
					],
					callout: {
						label: "Conseil d'Hôte Local",
						text: "La piscine ferme chaque mardi pour entretien : profitez du mardi pour visiter le Parc Tayrona ou Minca. Arrivée à 15h00, départ à 11h00, et Frais d'Enregistrement de l'Immeuble de {buildingRegistrationFeeCop} par personne à régler à la réception.",
					},
				},
			],
			faqsTitle: "Questions Fréquentes : Playa Salguero vs. El Rodadero",
			faqs: [
				{
					id: "faq-ps-1",
					question:
						"Playa Salguero est-il un quartier sûr pour les familles à Santa Marta ?",
					answer:
						"Oui. Playa Salguero est l'un des quartiers résidentiels côtiers les plus appréciés de Santa Marta, doté de résidences modernes avec conciergerie 24h/24 et d'une ambiance nettement plus calme que le centre d'El Rodadero.",
					defaultOpen: true,
				},
				{
					id: "faq-ps-2",
					question:
						"Quelle est la distance entre l'Edificio Salguero Sunset, El Rodadero et le Centre Historique ?",
					answer:
						"L'Edificio Salguero Sunset (Calle 26 # 2-80) est situé à ~3 km (~5–10 min en voiture) d'El Rodadero, ~8 km (~20–25 min) du Centre Historique et de la Marina, et ~10 km (~15–20 min) de l'aéroport Simón Bolívar (SMR).",
				},
				{
					id: "faq-ps-3",
					question:
						"Peut-on rejoindre la plage de Playa Salguero à pied depuis OceanViewFlats ?",
					answer:
						"Oui. La plage se trouve à environ 150 mètres (1 pâté de maisons, ~2 minutes à pied) de l'entrée de l'Edificio Salguero Sunset.",
				},
				{
					id: "faq-ps-4",
					question:
						"Faut-il choisir la Propriété 1606 ou la Propriété 1707 à l'Edificio Salguero Sunset ?",
					answer:
						"Choisissez la Propriété 1606 (16e étage, 2 Chambres / 3 Lits : 1 Queen, 1 Double, 1 Canapé-lit, Place 87, dès {rate1606Cop}/nuit + {cleaning1606Cop} de Ménage) pour une terrasse chaleureuse en couple ou en famille. Préférez la Propriété 1707 (17e étage, 2 Chambres / 4 Lits : 1 Queen, 2 Lits Simples, 1 Canapé-lit, Place 95, dès {rate1707Cop}/nuit + {cleaning1707Cop} de Ménage) pour 4 couchages distincts ou un bureau ergonomique dédié.",
				},
			],
			ctaTitle: "Séjournez à Deux Pas de Playa Salguero",
			ctaSubtitle:
				"Consultez nos disponibilités et devis transparents en COP sans commission OTA, dès {portfolioLowCop} par nuit.",
		},
		it: {
			seoTitle: "Playa Salguero vs El Rodadero Santa Marta | OceanViewFlats",
			seoDescription:
				"Playa Salguero vs El Rodadero a Santa Marta: confronta spiagge, tranquillità, sicurezza, aeroporto SMR e soggiorni fronte mare da {portfolioLowCop}.",
			ogImageAlt:
				"Tramonto sul Mar dei Caraibi dal balcone fronte mare di Playa Salguero a Santa Marta, Colombia",
			heroImageAlt:
				"Vista panoramica sulla costa di Playa Salguero dall'Edificio Salguero Sunset a Santa Marta",
			breadcrumbLabel: "Playa Salguero vs. El Rodadero",
			badge: "Confronto Quartieri",
			title: "Playa Salguero vs. El Rodadero: Dove Alloggiare a Santa Marta",
			subtitle:
				"Confronto locale onesto su atmosfera in spiaggia, quiete residenziale, sicurezza familiare, tramonti e distanza dall'Aeroporto Simón Bolívar (SMR).",
			cardSummary:
				"Confronta le moderne torri residenziali di Playa Salguero con la vivace passeggiata di El Rodadero prima di scegliere il tuo soggiorno fronte mare.",
			keyTakeawaysTitle: "Punti Chiave in Sintesi",
			keyTakeaways: [
				"Playa Salguero è un quartiere residenziale fronte mare a ~150 m (1 isolato) dalla sabbia, con molta meno folla e venditori rispetto a El Rodadero centrale.",
				"L'Edificio Salguero Sunset si trova ~3 km (~5–10 min in auto) a sud di El Rodadero: notti serene a pochi minuti da ristoranti e moli.",
				"L'Aeroporto Internazionale Simón Bolívar (SMR) dista solo ~10 km (~15–20 min in auto), evitando il traffico del centro.",
				"Le Tariffe Notturne in Prenotazione Diretta partono da {rate1606Cop} per la Proprietà 1606 e {rate1707Cop} per la Proprietà 1707 (soggiorno minimo base {minStay1606} notti).",
			],
			sections: [
				{
					id: "atmosphere-and-beach",
					heading: "Qualità della Spiaggia, Affollamento e Tramonti",
					paragraphs: [
						"Chi organizza una vacanza costiera a Santa Marta confronta quasi sempre due quartieri confinanti: El Rodadero e Playa Salguero. Pur condividendo la stessa costa caraibica, l'esperienza quotidiana in spiaggia è profondamente diversa.",
						"El Rodadero è la storica zona turistica ad alta densità, animata da lungomare affollati, barche per Playa Blanca, musica nei weekend e venditori continui. Playa Salguero, subito a sud, si è sviluppata come area residenziale moderna con torri controllate da reception 24/7 e un arenile molto più tranquillo.",
					],
					bullets: [
						"Sabbia ampia e ordinata: meno affollamento e vista aperta sull'orizzonte caraibico.",
						"Tramonti diretti a ovest: dal 16° e 17° piano dell'Edificio Salguero Sunset il panorama sull'oceano è ininterrotto.",
						"Riposo assicurato: regole condominiali e portineria 24/7, ideali per famiglie, coppie e lavoratori da remoto.",
					],
					factCards: [
						{
							label: "Spiaggia a Piedi",
							value: "~150 m",
							detail: "1 isolato (~2 min a piedi) da Playa Salguero",
						},
						{
							label: "El Rodadero",
							value: "~5–10 min",
							detail: "~3 km a nord in taxi o auto",
						},
						{
							label: "Aeroporto SMR",
							value: "~15–20 min",
							detail: "~10 km a sud lungo la costa",
						},
					],
				},
				{
					id: "side-by-side-comparison",
					heading: "Confronto Diretto: Playa Salguero vs. El Rodadero",
					paragraphs: [
						"La scelta dipende dal ritmo che desideri: movida commerciale sotto casa oppure una base fronte mare silenziosa a pochi minuti da El Rodadero e dall'aeroporto.",
					],
					comparisonTable: {
						caption:
							"Confronto tra Playa Salguero (Edificio Salguero Sunset) e il centro di El Rodadero",
						headers: ["Criterio", "Playa Salguero", "Centro di El Rodadero"],
						rows: [
							[
								"Carattere del Quartiere",
								"Torri residenziali moderne e atmosfera rilassata",
								"Lungomare turistico ad alta densità giorno e notte",
							],
							[
								"Esperienza in Spiaggia",
								"Sabbia ampia, meno venditori, tramonto diretto sul mare",
								"Baia affollata, moli per escursioni e forte passeggio",
							],
							[
								"Silenzio Notturno",
								"Contesto condominiale tranquillo con portineria 24/7",
								"Musica esterna, traffico e attività notturne",
							],
							[
								"Accesso Aeroporto SMR",
								"~10 km (~15–20 min in auto)",
								"~12–13 km (~20–30 min con traffico)",
							],
							[
								"Ristoranti e Spesa",
								"Caffetterie sul mare vicine; 5–10 min dai supermercati di Rodadero",
								"Ampia offerta commerciale e locali raggiungibili a piedi",
							],
							[
								"Parcheggio e Sicurezza",
								"Posto Auto Assegnato (Posto 95 per 1707, Posto 87 per 1606)",
								"Parcheggio stradale limitato e congestionato nel weekend",
							],
						],
					},
				},
				{
					id: "edificio-salguero-sunset",
					heading:
						"Perché Scegliere Edificio Salguero Sunset (Calle 26 # 2-80)",
					paragraphs: [
						"L'Edificio Salguero Sunset si trova a un isolato (~150 m) dalla spiaggia di Playa Salguero, con reception e sicurezza 24/7, piscina (aperta ogni giorno tranne il martedì per manutenzione) e Posto Auto Assegnato interno.",
						"OceanViewFlats gestisce due Proprietà con RNT ufficiale a un piano di distanza: Proprietà 1707 al 17° piano (RNT #268781, 2 Camere / 4 Letti, Posto 95, Tariffa Notturna base {rate1707Cop} più {cleaning1707Cop} di Tariffa di Pulizia) e Proprietà 1606 al 16° piano (RNT #270413, 2 Camere / 3 Letti, Posto 87, Tariffa Notturna da {rate1606Cop} fino a {rate1606HighCop} in alta stagione più {cleaning1606Cop} di Tariffa di Pulizia). Entrambe ospitano fino a 6 Ospiti con fibra fino a 900 Mbps.",
					],
					callout: {
						label: "Consiglio dell'Host Locale",
						text: "Poiché la piscina chiude il martedì per manutenzione, il martedì è perfetto per un'escursione al Parco Tayrona o a Minca. Check-in alle 15:00, check-out alle 11:00 e Tassa di Registrazione dell'Edificio di {buildingRegistrationFeeCop} a persona pagabile in reception.",
					},
				},
			],
			faqsTitle: "Domande Frequenti: Playa Salguero vs. El Rodadero",
			faqs: [
				{
					id: "faq-ps-1",
					question:
						"Playa Salguero è sicura per famiglie e viaggiatori a Santa Marta?",
					answer:
						"Sì. Playa Salguero è una delle aree residenziali costiere più curate di Santa Marta, con edifici moderni dotati di portineria 24/7 e un'atmosfera molto più tranquilla rispetto al centro di El Rodadero.",
					defaultOpen: true,
				},
				{
					id: "faq-ps-2",
					question:
						"Quanto dista l'Edificio Salguero Sunset da El Rodadero e dal Centro Storico?",
					answer:
						"L'Edificio Salguero Sunset (Calle 26 # 2-80) dista ~3 km (~5–10 minuti in auto) da El Rodadero, ~8 km (~20–25 minuti) dal Centro Storico e dalla Marina, e ~10 km (~15–20 minuti) dall'Aeroporto Simón Bolívar (SMR).",
				},
				{
					id: "faq-ps-3",
					question:
						"Si può raggiungere la spiaggia di Playa Salguero a piedi da OceanViewFlats?",
					answer:
						"Sì. La spiaggia di Playa Salguero dista circa 150 metri (1 isolato, ~2 minuti a piedi) dall'ingresso principale dell'Edificio Salguero Sunset.",
				},
				{
					id: "faq-ps-4",
					question:
						"Meglio la Proprietà 1606 o la Proprietà 1707 nell'Edificio Salguero Sunset?",
					answer:
						"Scegli la Proprietà 1606 (16° piano, 2 Camere / 3 Letti: 1 Queen, 1 Matrimoniale, 1 Divano Letto, Posto 87, da {rate1606Cop}/notte + {cleaning1606Cop} di Pulizia) per una terrazza calda per coppie o famiglie. Scegli la Proprietà 1707 (17° piano, 2 Camere / 4 Letti: 1 Queen, 2 Singoli, 1 Divano Letto, Posto 95, da {rate1707Cop}/notte + {cleaning1707Cop} di Pulizia) se cerchi 4 letti distinti o una postazione ergonomica da lavoro.",
				},
			],
			ctaTitle: "Soggiorna a Due Passi da Playa Salguero",
			ctaSubtitle:
				"Verifica date e preventivi trasparenti in COP senza commissioni OTA, a partire da {portfolioLowCop} a notte.",
		},
		de: {
			seoTitle: "Playa Salguero vs El Rodadero Santa Marta | OceanViewFlats",
			seoDescription:
				"Playa Salguero vs. El Rodadero in Santa Marta: Strandruhe, Sicherheit, Flughafen SMR und Strandunterkünfte in COP ab {portfolioLowCop} im Vergleich.",
			ogImageAlt:
				"Karibischer Sonnenuntergang über Playa Salguero in Santa Marta vom Balkon des Edificio Salguero Sunset",
			heroImageAlt:
				"Freier Meerblick über den Strand von Playa Salguero vom Edificio Salguero Sunset in Santa Marta",
			breadcrumbLabel: "Playa Salguero vs. El Rodadero",
			badge: "Stadtteil-Vergleich",
			title: "Playa Salguero vs. El Rodadero: Wo in Santa Marta übernachten?",
			subtitle:
				"Ein praxisnaher Vergleich zu Strandruhe, Wohnatmosphäre, Familiensicherheit, Sonnenuntergängen und Anbindung an den Flughafen Simón Bolívar (SMR).",
			cardSummary:
				"Vergleichen Sie die ruhigen Strandwohnhäuser von Playa Salguero mit der belebten Promenade von El Rodadero vor Ihrer Direktbuchung.",
			keyTakeawaysTitle: "Das Wichtigste im Überblick",
			keyTakeaways: [
				"Playa Salguero ist ein ruhiges modernes Strandviertel ~150 m (1 Block) vom breiten Sandstrand entfernt – deutlich entspannter als das Zentrum von El Rodadero.",
				"Das Edificio Salguero Sunset liegt ~3 km (~5–10 Min. per Auto) südlich von El Rodadero: ruhige Nächte bei schneller Erreichbarkeit von Restaurants und Bootsanlegern.",
				"Der Internationale Flughafen Simón Bolívar (SMR) ist nur ~10 km (~15–20 Min. per Auto) entfernt – ohne den Innenstadtverkehr.",
				"Übernachtungsraten per Direktbuchung starten bei {rate1606Cop} (Unterkunft 1606) bzw. {rate1707Cop} (Unterkunft 1707), Mindestaufenthalt ab {minStay1606} Nächten.",
			],
			sections: [
				{
					id: "atmosphere-and-beach",
					heading: "Strandqualität, Besucheraufkommen & Sonnenuntergänge",
					paragraphs: [
						"Reisende in Santa Marta vergleichen fast immer zwei benachbarte Küstenabschnitte: El Rodadero und Playa Salguero. Obwohl beide an derselben Karibikküste liegen, unterscheidet sich der Alltag am Strand erheblich.",
						"El Rodadero ist die klassische, dicht bebaute Urlaubspromenade mit Ausflugsbooten nach Playa Blanca, Wochenendmusik und lebhaftem Straßenhandel. Direkt südlich davon entstand Playa Salguero als ruhiges Wohnviertel mit modernen Hochhäusern, 24/7-Rezeption und deutlich entspannterem Strandabschnitt.",
					],
					bullets: [
						"Weitläufiger Strand: deutlich weniger Händlerverkehr und freier Blick auf den karibischen Horizont.",
						"Direkte West-Sonnenuntergänge: Vom 16. und 17. Stock des Edificio Salguero Sunset genießen Sie unverstellte Meerblicke.",
						"Erholsame Nachtruhe: Hausordnung und 24/7-Empfang machen das Viertel ideal für Familien, Paare und Remote-Arbeitende.",
					],
					factCards: [
						{
							label: "Zum Strand",
							value: "~150 m",
							detail: "1 Block (~2 Min. zu Fuß) bis Playa Salguero",
						},
						{
							label: "El Rodadero",
							value: "~5–10 Min.",
							detail: "~3 km nördlich per Taxi oder Fahrdienst",
						},
						{
							label: "Flughafen SMR",
							value: "~15–20 Min.",
							detail: "~10 km südlich über die Küstenstraße",
						},
					],
				},
				{
					id: "side-by-side-comparison",
					heading: "Direktvergleich: Playa Salguero vs. El Rodadero",
					paragraphs: [
						"Die Wahl hängt davon ab, ob Sie Trubel direkt vor der Haustür suchen oder eine ruhige Basis am Meer mit kurzer Fahrtzeit nach El Rodadero und zum Flughafen.",
					],
					comparisonTable: {
						caption:
							"Vergleich zwischen Playa Salguero (Edificio Salguero Sunset) und dem Zentrum von El Rodadero",
						headers: ["Kriterium", "Playa Salguero", "Zentrum El Rodadero"],
						rows: [
							[
								"Viertelcharakter",
								"Moderne Strandwohnhäuser, ruhige Atmosphäre",
								"Lebhafte Urlaubspromenade rund um die Uhr",
							],
							[
								"Stranderlebnis",
								"Breiter Sand, weniger Trubel, freier Sonnenuntergang",
								"Volle Bucht, Ausflugsboote, intensiver Strandverkauf",
							],
							[
								"Nachtruhe & Sicherheit",
								"Ruhiges Wohnumfeld mit 24/7-Rezeption",
								"Straßenmusik, Verkehr und Nachtleben",
							],
							[
								"Flughafen SMR",
								"~10 km (~15–20 Min. mit dem Auto)",
								"~12–13 km (~20–30 Min. je nach Verkehr)",
							],
							[
								"Gastronomie & Einkauf",
								"Strandcafés in der Nähe; 5–10 Min. zu Supermärkten in Rodadero",
								"Dichte Auswahl an Restaurants und Läden zu Fuß",
							],
							[
								"Parken",
								"Fester Stellplatz inklusive (Platz 95 für 1707, Platz 87 für 1606)",
								"Begrenzte Straßenparkplätze, voller Wochenendverkehr",
							],
						],
					},
				},
				{
					id: "edificio-salguero-sunset",
					heading: "Vorteile des Edificio Salguero Sunset (Calle 26 # 2-80)",
					paragraphs: [
						"Das Edificio Salguero Sunset liegt einen Block (~150 m) vom Strand Playa Salguero entfernt und bietet 24/7-Sicherheitsrezeption, Pool (täglich geöffnet außer dienstags wegen Wartung) und feste Stellplätze im Gebäude.",
						"OceanViewFlats betreibt zwei offiziell registrierte Strandunterkünfte nur eine Etage voneinander entfernt: Unterkunft 1707 im 17. Stock (RNT #268781, 2 Schlafzimmer / 4 Betten, Stellplatz 95, Basisrate {rate1707Cop} zzgl. {cleaning1707Cop} Reinigungsgebühr) und Unterkunft 1606 im 16. Stock (RNT #270413, 2 Schlafzimmer / 3 Betten, Stellplatz 87, Basisrate {rate1606Cop} bis {rate1606HighCop} in der Hochsaison zzgl. {cleaning1606Cop} Reinigungsgebühr). Beide bieten Platz für max. 6 Gäste und bis zu 900 Mbit/s Glasfaser-WLAN.",
					],
					callout: {
						label: "Tipp vom Gastgeber",
						text: "Da der Pool dienstags wegen Wartung geschlossen bleibt, eignet sich der Dienstag ideal für einen Ausflug in den Tayrona-Nationalpark oder nach Minca. Check-in ab 15:00, Check-out bis 11:00; die Gebäude-Registrierungsgebühr von {buildingRegistrationFeeCop} pro Person wird direkt an der Rezeption bezahlt.",
					},
				},
			],
			faqsTitle: "Häufige Fragen: Playa Salguero vs. El Rodadero",
			faqs: [
				{
					id: "faq-ps-1",
					question:
						"Ist Playa Salguero für Familien und Alleinreisende in Santa Marta sicher?",
					answer:
						"Ja. Playa Salguero zählt zu den ruhigsten modernen Küstenvierteln von Santa Marta mit gepflegten Wohnanlagen, 24/7-Sicherheitsrezeption und deutlich weniger Gedränge als im Zentrum von El Rodadero.",
					defaultOpen: true,
				},
				{
					id: "faq-ps-2",
					question:
						"Wie weit ist das Edificio Salguero Sunset von El Rodadero und der Altstadt entfernt?",
					answer:
						"Das Edificio Salguero Sunset (Calle 26 # 2-80) liegt ~3 km (~5–10 Min. per Auto) von El Rodadero, ~8 km (~20–25 Min.) vom Historischen Zentrum und der Marina sowie ~10 km (~15–20 Min.) vom Flughafen Simón Bolívar (SMR) entfernt.",
				},
				{
					id: "faq-ps-3",
					question:
						"Kann man vom OceanViewFlats zu Fuß an den Strand Playa Salguero gehen?",
					answer:
						"Ja. Der Strand Playa Salguero liegt etwa 150 Meter (1 Block, ca. 2 Gehminuten) vom Haupteingang des Edificio Salguero Sunset entfernt.",
				},
				{
					id: "faq-ps-4",
					question:
						"Sollte ich Unterkunft 1606 oder Unterkunft 1707 im Edificio Salguero Sunset wählen?",
					answer:
						"Unterkunft 1606 (16. Stock, 2 Schlafzimmer / 3 Betten: 1 Queen, 1 Doppelbett, 1 Schlafsofa, Stellplatz 87, ab {rate1606Cop}/Nacht + {cleaning1606Cop} Reinigung) eignet sich ideal für Paare und Familien mit warmer Sonnenuntergangsterrasse. Unterkunft 1707 (17. Stock, 2 Schlafzimmer / 4 Betten: 1 Queen, 2 Einzelbetten, 1 Schlafsofa, Stellplatz 95, ab {rate1707Cop}/Nacht + {cleaning1707Cop} Reinigung) ist optimal für vier getrennte Betten oder konzentriertes Remote Work am eigenen Schreibtisch.",
				},
			],
			ctaTitle: "Wohnen nur Schritte vom Strand Playa Salguero",
			ctaSubtitle:
				"Prüfen Sie freie Termine und transparente COP-Angebote mit 0% OTA-Provision – ab {portfolioLowCop} pro Nacht.",
		},
		ja: {
			seoTitle:
				"プラヤサルグエロ対エルロダデロ比較（サンタマルタ） | OceanViewFlats",
			seoDescription:
				"サンタマルタのプラヤサルグエロとエル・ロダデロを徹底比較。静かなビーチ、治安、SMR空港アクセス、{portfolioLowCop}からの海辺滞在ガイド。",
			ogImageAlt:
				"コロンビア・サンタマルタのプラヤ・サルグエロに沈むカリブ海の夕日と海辺バルコニー",
			heroImageAlt:
				"サンタマルタのエディフィシオ・サルグエロ・サンセットから望むプラヤ・サルグエロ海岸線",
			breadcrumbLabel: "プラヤ・サルグエロ vs エル・ロダデロ",
			badge: "エリア比較",
			title:
				"プラヤ・サルグエロ vs エル・ロダデロ：サンタマルタ滞在エリア徹底比較",
			subtitle:
				"ビーチの静けさ、住宅街の安全性、サンセット眺望、シモン・ボリバル空港（SMR）へのアクセスを現地ホスト目線で比較します。",
			cardSummary:
				"静かな住宅ビーチフロントのプラヤ・サルグエロと賑やかなエル・ロダデロを比較し、滞在スタイルに最適なエリアを選びましょう。",
			keyTakeawaysTitle: "比較のポイント",
			keyTakeaways: [
				"プラヤ・サルグエロは広い砂浜まで約150m（1ブロック）の落ち着いた現代的住宅エリアで、エル・ロダデロ中心部より売り子や混雑が大幅に少ない環境です。",
				"エディフィシオ・サルグエロ・サンセットはエル・ロダデロから南へ約3km（車で約5〜10分）。静かな夜を過ごしつつ飲食店や桟橋へすぐアクセスできます。",
				"シモン・ボリバル国際空港（SMR）まで約10km（車で約15〜20分）と近く、市内中心部の渋滞を避けてスムーズに移動できます。",
				"直接予約の宿泊料金は物件1606が{rate1606Cop}〜、物件1707が{rate1707Cop}〜（基本最低{minStay1606}泊）です。",
			],
			sections: [
				{
					id: "atmosphere-and-beach",
					heading: "ビーチの雰囲気・混雑度・サンセット眺望の違い",
					paragraphs: [
						"サンタマルタで海辺の滞在先を選ぶ際、多くの旅行者が比較するのが隣接するエル・ロダデロとプラヤ・サルグエロです。同じカリブ海沿岸に位置しながら、実際の過ごし心地は大きく異なります。",
						"エル・ロダデロは遊歩道やプラヤ・ブランカ行きボート発着場、飲食店が集まる高密度な観光エリアで、週末は音楽や行商で非常に賑わいます。そのすぐ南にあるプラヤ・サルグエロは24時間有人フロントを備えた現代的なコンドミニアムが並ぶ落ち着いた住宅海岸エリアです。",
					],
					bullets: [
						"開放的な砂浜：パラソル密集や売り子が少なく、カリブ海の水平線をゆったり眺められます。",
						"西向きサンセット：エディフィシオ・サルグエロ・サンセットの16階・17階からは遮るもののない夕景が広がります。",
						"静かな夜間環境：コンドミニアム管理規約と24時間セキュリティにより、家族連れ・カップル・リモートワーカーも安心です。",
					],
					factCards: [
						{
							label: "ビーチまで徒歩",
							value: "約150m",
							detail: "1ブロック（徒歩約2分）で砂浜へ",
						},
						{
							label: "エル・ロダデロ",
							value: "約5〜10分",
							detail: "北へ約3km（タクシー・配車）",
						},
						{
							label: "SMR空港",
							value: "約15〜20分",
							detail: "南へ約10kmの沿岸ルート",
						},
					],
				},
				{
					id: "side-by-side-comparison",
					heading: "プラヤ・サルグエロ vs エル・ロダデロ 比較表",
					paragraphs: [
						"目の前の賑わいを重視するか、静かな海辺の拠点を確保しつつ必要な時だけエル・ロダデロや旧市街へ出かけるかが選択の分かれ目です。",
					],
					comparisonTable: {
						caption:
							"プラヤ・サルグエロ（エディフィシオ・サルグエロ・サンセット）とエル・ロダデロ中心部の比較",
						headers: ["比較項目", "プラヤ・サルグエロ", "エル・ロダデロ中心部"],
						rows: [
							[
								"エリアの雰囲気",
								"現代的な海辺レジデンス中心の落ち着いた環境",
								"昼夜問わず活気のある観光プロムナード",
							],
							[
								"ビーチ環境",
								"広い砂浜・混雑少なめ・正面に沈む夕日",
								"賑やかな湾・ボート桟橋・活発なビーチ販売",
							],
							[
								"静粛性と安全管理",
								"24時間フロント管理の静かな住環境",
								"週末の音楽・交通量・夜間商業の賑わい",
							],
							[
								"SMR空港アクセス",
								"約10km（車で約15〜20分）",
								"約12〜13km（渋滞時20〜30分）",
							],
							[
								"食事・買い物",
								"周辺カフェ・海辺レストラン＋ロダデロのスーパーまで5〜10分",
								"徒歩圏に多数のカジュアル飲食店と商店",
							],
							[
								"駐車スペース",
								"専用駐車区画付き（1707は95番・1606は87番）",
								"路上駐車が限られ週末は混雑",
							],
						],
					},
				},
				{
					id: "edificio-salguero-sunset",
					heading:
						"エディフィシオ・サルグエロ・サンセット（Calle 26 # 2-80）が選ばれる理由",
					paragraphs: [
						"エディフィシオ・サルグエロ・サンセットはプラヤ・サルグエロ・ビーチから約150m（徒歩約2分）。24時間セキュリティフロント、プール（毎週火曜日は定期メンテナンスのため休業）、建物内の専用駐車区画を備えています。",
						"OceanViewFlatsは隣り合う階で2件のRNT登録済み物件を運営しています。17階の物件1707（RNT #268781、2ベッドルーム・4ベッド、駐車区画95番、基本宿泊料金{rate1707Cop}＋清掃料金{cleaning1707Cop}）と、16階の物件1606（RNT #270413、2ベッドルーム・3ベッド、駐車区画87番、基本{rate1606Cop}〜ハイシーズン{rate1606HighCop}＋清掃料金{cleaning1606Cop}）。いずれも最大6名様まで宿泊可能で最大900 Mbps光ファイバーWi-Fiを完備しています。",
					],
					callout: {
						label: "ホストからの現地アドバイス",
						text: "プールは毎週火曜日にメンテナンス休業となるため、火曜日はタイロナ国立公園やミンカへの日帰り旅に充てるのがおすすめです。チェックイン15:00・チェックアウト11:00で、到着時にフロントにて建物登録料（{buildingRegistrationFeeCop}／名）のお支払いが必要です。",
					},
				},
			],
			faqsTitle: "よくある質問：プラヤ・サルグエロ vs エル・ロダデロ",
			faqs: [
				{
					id: "faq-ps-1",
					question: "プラヤ・サルグエロは家族連れや一人旅でも安全ですか？",
					answer:
						"はい。プラヤ・サルグエロは24時間有人フロントを備えた現代的な海辺マンションが集まる落ち着いた住宅エリアで、エル・ロダデロ中心部より人通りや騒音が穏やかです。",
					defaultOpen: true,
				},
				{
					id: "faq-ps-2",
					question:
						"エディフィシオ・サルグエロ・サンセットからエル・ロダデロや旧市街までの距離は？",
					answer:
						"Calle 26 # 2-80からエル・ロダデロまで約3km（車で約5〜10分）、サンタマルタ旧市街・マリーナまで約8km（約20〜25分）、シモン・ボリバル国際空港（SMR）まで約10km（約15〜20分）です。",
				},
				{
					id: "faq-ps-3",
					question:
						"OceanViewFlatsからプラヤ・サルグエロ・ビーチへ徒歩で行けますか？",
					answer:
						"はい。建物の正面玄関から約150m（1ブロック、徒歩約2分）で砂浜にアクセスできます。",
				},
				{
					id: "faq-ps-4",
					question: "物件1606と物件1707はどのように選べばよいですか？",
					answer:
						"カップルやご家族で温かみのあるサンセットテラスを楽しみたい場合は物件1606（16階、2ベッドルーム・3ベッド：クイーン1・ダブル1・ソファベッド1、駐車区画87番、{rate1606Cop}/泊〜＋清掃料金{cleaning1606Cop}）。4台のベッド構成や専用ワークデスクを重視する場合は物件1707（17階、2ベッドルーム・4ベッド：クイーン1・シングル2・ソファベッド1、駐車区画95番、{rate1707Cop}/泊〜＋清掃料金{cleaning1707Cop}）がおすすめです。",
				},
			],
			ctaTitle: "プラヤ・サルグエロの海辺で静かな滞在を",
			ctaSubtitle:
				"OTA手数料0%・COP建ての公式見積もりをリアルタイムで確認（1泊{portfolioLowCop}〜）。",
		},
	},
};
