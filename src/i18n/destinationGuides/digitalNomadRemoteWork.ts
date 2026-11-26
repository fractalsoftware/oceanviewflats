import { IMAGE_DIMENSIONS, IMAGES } from "../../constants/config";
import type { DestinationGuideDefinition } from "./types";

export const DIGITAL_NOMAD_REMOTE_WORK_GUIDE: DestinationGuideDefinition = {
	slug: "digital-nomad-remote-work",
	id: "destination-guide-digital-nomad-remote-work",
	categoryIcon: "wifi",
	heroImage: IMAGES["1707"].hero,
	heroImageDimensions: IMAGE_DIMENSIONS["1707"].hero,
	ogImage: {
		path: IMAGES["1707"].hero,
		...IMAGE_DIMENSIONS["1707"].hero,
	},
	datePublished: "2026-11-26",
	dateModified: "2026-11-26",
	readingTimeMinutes: 8,
	relatedProperties: ["1707", "1606"],
	relatedGuideSlugs: [
		"playa-salguero-vs-el-rodadero",
		"direct-reservation-guide",
		"day-trips-from-playa-salguero",
	],
	locales: {
		en: {
			seoTitle: "Santa Marta Digital Nomad & Remote Work | OceanViewFlats",
			seoDescription:
				"Remote work in Santa Marta: 900 Mbps fiber Wi-Fi, 17th-floor desk in Playa Salguero, SIM/eSIM, SMR transfers & stays from {rate1707Cop}.",
			ogImageAlt:
				"17th-floor Caribbean oceanfront residence with 900 Mbps fiber Wi-Fi for remote work in Playa Salguero, Santa Marta",
			heroImageAlt:
				"Property 1707 high-floor seaside living space and balcony in Playa Salguero, Santa Marta",
			breadcrumbLabel: "Santa Marta Remote Work Guide",
			badge: "Remote Work & Digital Nomads",
			title: "Digital Nomad & Remote Work Guide to Santa Marta",
			subtitle:
				"900 Mbps symmetrical fiber Wi-Fi, an ergonomic 17th-floor workspace, Colombian SIM/eSIM setup, quiet beachfront cafés, and SMR airport logistics.",
			cardSummary:
				"Everything remote engineers, founders, and distributed teams need for high-uptime beachfront workdays in Playa Salguero, Santa Marta.",
			keyTakeawaysTitle: "Remote Work Essentials at a Glance",
			keyTakeaways: [
				"Property 1707 (17th floor, RNT #268781) combines up to 900 Mbps symmetrical fiber Wi-Fi, an ergonomic remote-work desk, and quiet oceanfront height above street noise.",
				"Colombia Time (COT, UTC-5) never observes daylight saving time, giving clean overlap with US East/Central and European afternoon schedules.",
				"Simón Bolívar International Airport (SMR) sits just ~10 km (~15–20 min by car) from Edificio Salguero Sunset for effortless weekly flights to Bogotá and Medellín.",
				"Direct Reservation Nightly Rates for Property 1707 start at {rate1707Cop} ({minStay1707}-night minimum) plus a flat {cleaning1707Cop} Cleaning Fee with 0% OTA commission.",
			],
			sections: [
				{
					id: "fiber-and-workspace",
					heading: "900 Mbps Fiber Connectivity & 17th-Floor Ergonomics",
					paragraphs: [
						"Many coastal short-term stays in the Colombian Caribbean advertise Wi-Fi that struggles once two people join video calls simultaneously. At Edificio Salguero Sunset in Playa Salguero, both Property 1707 and Property 1606 run high-speed fiber internet up to 900 Mbps, supporting back-to-back Zoom/Meet calls, Docker image pulls, and large cloud uploads without buffering.",
						"Property 1707 on the 17th floor was purposefully outfitted for extended remote work. In addition to its dedicated desk workspace, the residence offers three climate-controlled zones (two air-conditioned bedrooms plus living room) so two teammates or a working couple can take simultaneous calls behind closed doors.",
					],
					bullets: [
						"High-altitude quiet: 17th-floor elevation insulates your calls from beach and avenue noise.",
						"Flexible sleeping layout: 2 Bedrooms / 4 Beds (1 Queen Bed, 2 Twin/Single Beds, 1 Pull-out Sofa Bed; Maximum Occupancy 6 Guests) plus Assigned Parking Space Bay 95.",
						"In-residence autonomy: Full kitchen, washer/dryer laundry setup, and 15:00 smart-lock self check-in via temporal Access Credential.",
					],
					factCards: [
						{
							label: "Fiber Speed",
							value: "Up to 900 Mbps",
							detail: "Built for HD calls & heavy cloud workflows",
						},
						{
							label: "Time Zone",
							value: "COT (UTC-5)",
							detail: "Year-round overlap with Americas & Europe",
						},
						{
							label: "Property 1707 Rate",
							value: "{rate1707Cop}",
							detail:
								"Min. {minStay1707} nights + {cleaning1707Cop} Cleaning Fee",
						},
					],
				},
				{
					id: "connectivity-sim-and-cafes",
					heading: "Colombian SIM/eSIM Backup, Cafés & Coworking Options",
					paragraphs: [
						"Even with 900 Mbps fiber at home, seasoned remote workers keep a local mobile data backup for transit days and day trips. Claro and Movistar deliver the strongest 4G/5G coverage along Playa Salguero, El Rodadero, and the Santa Marta coastal highway; international travelers can activate an eSIM before landing at SMR or pick up a prepaid physical SIM at Arrecife Shopping Center in El Rodadero (~5–10 min away).",
						"When you want a change of scenery between sprint blocks, El Rodadero (~3 km, ~5–10 min by car) offers specialty coffee shops and casual bakeries, while Santa Marta's Historic Center (~8 km, ~20–25 min by car) features boutique cafés around Parque de los Novios and Callejon del Correo.",
					],
					comparisonTable: {
						caption:
							"Remote work setup comparison between Property 1707 and Property 1606 at Edificio Salguero Sunset",
						headers: ["Workspace Factor", "Property 1707", "Property 1606"],
						rows: [
							["Floor & Elevation", "17th floor", "16th floor"],
							["Fiber Wi-Fi", "Up to 900 Mbps Fiber", "Up to 900 Mbps Fiber"],
							[
								"Workstation Setup",
								"Dedicated ergonomic remote-work desk + dining table",
								"Dining workspace + warm sunset balcony",
							],
							[
								"Bed Configuration",
								"2 BR / 4 Beds (1 Queen, 2 Twins, 1 Sofa Bed)",
								"2 BR / 3 Beds (1 Queen, 1 Double, 1 Sofa Bed)",
							],
							[
								"Assigned Parking Space",
								"Bay 95 (included)",
								"Bay 87 (included)",
							],
							[
								"Baseline Nightly Rate",
								"{rate1707Cop} (min. {minStay1707} nights)",
								"{rate1606Cop} (peak {rate1606HighCop}, {minStay1606}–{maxMinStay1606} nights)",
							],
							["Flat Cleaning Fee", "{cleaning1707Cop}", "{cleaning1606Cop}"],
							["Official RNT", "RNT #268781", "RNT #270413"],
						],
					},
				},
				{
					id: "extended-stays-and-logistics",
					heading: "Airport Transfers, Weekly Groceries & Extended Stay Rhythm",
					paragraphs: [
						"Playa Salguero makes multi-week stays remarkably easy to manage. Authorised airport taxis and rideshare pickups from Simón Bolívar International Airport (SMR) take ~15–20 minutes (~10 km) straight up the coastal corridor to Calle 26 # 2-80. Supermarkets (Olímpica and Éxito in El Rodadero) deliver groceries via Rappi directly to the Edificio Salguero Sunset lobby.",
						"After logging off at 17:00 COT, walk one block (~150 m) to Playa Salguero beach for a sunset swim, or enjoy the building pool any day except Tuesday (closed for scheduled maintenance). Planning a longer workation? Direct Reservations in Settlement Currency (COP) save 15–20% compared to external OTA service markups.",
					],
					callout: {
						label: "Workation Logistics Note",
						text: "Remember that every staying Guest must complete the mandatory Guest Registry (TRA/SIRE) before arrival so reception issues building clearance and OceanViewFlats unlocks your private Guest Guide and temporal Access Credential. Reception collects the one-time {buildingFeeCop} per-person Building Registration Fee at check-in (15:00).",
					},
				},
			],
			faqsTitle: "Frequently Asked Questions: Remote Work in Santa Marta",
			faqs: [
				{
					id: "faq-dn-1",
					question:
						"How fast and reliable is the Wi-Fi at OceanViewFlats for video calls?",
					answer:
						"Both Property 1707 and Property 1606 are equipped with fiber-optic Wi-Fi rated up to 900 Mbps, easily supporting simultaneous HD video conferences, VPN tunnels, and large file transfers.",
					defaultOpen: true,
				},
				{
					id: "faq-dn-2",
					question:
						"Which Property is better for remote workers or two colleagues sharing?",
					answer:
						"Property 1707 (17th floor, RNT #268781) is tailored for remote professionals: it includes a dedicated ergonomic desk, 2 Bedrooms / 4 Beds (1 Queen, 2 Twins, 1 Pull-out Sofa Bed), Assigned Parking Space Bay 95, and starts at {rate1707Cop} per night plus a {cleaning1707Cop} Cleaning Fee.",
				},
				{
					id: "faq-dn-3",
					question:
						"How long does it take to reach Simón Bolívar Airport (SMR) from Playa Salguero?",
					answer:
						"Simón Bolívar International Airport (SMR) is approximately 10 km (~15–20 minutes by car) from Edificio Salguero Sunset, with frequent daily direct flights to Bogotá (BOG), Medellín (MDE), and Cali (CLO).",
				},
				{
					id: "faq-dn-4",
					question:
						"Can I book a multi-week Direct Reservation with 0% OTA commission?",
					answer:
						"Yes. Select your Check-in Date and Check-out Date on Property 1707 or Property 1606 to generate an instant COP Quote (Nightly Rates + flat Cleaning Fee, 0% OTA fees) and pay via Credit/Debit Card, PSE, or Cash Voucher through Mercado Pago.",
				},
			],
			ctaTitle: "Upgrade Your Beachfront Remote Office",
			ctaSubtitle:
				"Work from the 17th floor on 900 Mbps fiber—Direct Reservation Quotes in COP from {rate1707Cop} (1707) or {rate1606Cop} (1606).",
		},
		es: {
			seoTitle: "Trabajo Remoto en Santa Marta (900 Mbps) | OceanViewFlats",
			seoDescription:
				"Guía de trabajo remoto en Santa Marta: fibra 900 Mbps, escritorio en piso 17 en Playa Salguero, SIM/eSIM, aeropuerto SMR y estadías desde {rate1707Cop}.",
			ogImageAlt:
				"Residencia frente al mar en el piso 17 con fibra óptica de 900 Mbps para trabajo remoto en Playa Salguero, Santa Marta",
			heroImageAlt:
				"Sala luminosa frente al mar y balcón de la Propiedad 1707 en Playa Salguero, Santa Marta",
			breadcrumbLabel: "Trabajo Remoto en Santa Marta",
			badge: "Trabajo Remoto y Nómadas",
			title: "Guía de Trabajo Remoto y Nómadas Digitales en Santa Marta",
			subtitle:
				"Fibra óptica simétrica de 900 Mbps, escritorio ergonómico en el piso 17, SIM/eSIM en Colombia, cafés tranquilos y logística desde el aeropuerto SMR.",
			cardSummary:
				"Todo lo que necesitan ingenieros, fundadores y equipos remotos para trabajar con máxima estabilidad frente al mar en Playa Salguero.",
			keyTakeawaysTitle: "Claves para Trabajar Remoto en Santa Marta",
			keyTakeaways: [
				"La Propiedad 1707 (piso 17, RNT #268781) combina fibra óptica de hasta 900 Mbps, escritorio ergonómico dedicado y altura libre de ruido callejero.",
				"La hora de Colombia (COT, UTC-5) no cambia por horario de verano, facilitando reuniones con Norteamérica y Europa todo el año.",
				"El Aeropuerto Internacional Simón Bolívar (SMR) está a solo ~10 km (~15–20 min en carro) para vuelos frecuentes a Bogotá y Medellín.",
				"Las Tarifas por Noche en Reserva Directa para la Propiedad 1707 inician en {rate1707Cop} (mínimo {minStay1707} noches) más Tarifa de Limpieza fija de {cleaning1707Cop}, sin comisión OTA.",
			],
			sections: [
				{
					id: "fiber-and-workspace",
					heading: "Fibra Óptica de 900 Mbps y Ergonomía en el Piso 17",
					paragraphs: [
						"Muchos alojamientos costeros en el Caribe colombiano ofrecen Wi-Fi que falla cuando dos personas hacen videollamadas al mismo tiempo. En Edificio Salguero Sunset, tanto la Propiedad 1707 como la Propiedad 1606 cuentan con fibra óptica de hasta 900 Mbps, ideal para videollamadas HD continuas, VPN corporativas y transferencias pesadas en la nube.",
						"La Propiedad 1707 en el piso 17 fue acondicionada pensando en profesionales remotos: cuenta con escritorio ergonómico dedicado y tres ambientes climatizados con aire acondicionado (dos habitaciones más sala) para atender reuniones simultáneas con privacidad.",
					],
					bullets: [
						"Silencio de altura: el piso 17 aísla tus llamadas del sonido de la avenida y la playa.",
						"Distribución versátil: 2 Habitaciones / 4 Camas (1 Queen, 2 Sencillas, 1 Sofá Cama; Ocupación Máxima 6 Huéspedes) y Parqueadero Asignado Bahía 95.",
						"Autonomía total: cocina equipada, zona de lavandería y check-in autónomo desde las 15:00 con Credencial de Acceso temporal.",
					],
					factCards: [
						{
							label: "Fibra Óptica",
							value: "Hasta 900 Mbps",
							detail: "Estabilidad real para videollamadas y nube",
						},
						{
							label: "Zona Horaria",
							value: "COT (UTC-5)",
							detail: "Sin cambio de hora todo el año",
						},
						{
							label: "Propiedad 1707",
							value: "{rate1707Cop}",
							detail: "Mín. {minStay1707} noches + {cleaning1707Cop} Limpieza",
						},
					],
				},
				{
					id: "connectivity-sim-and-cafes",
					heading: "SIM/eSIM de Respaldo, Cafés y Opciones de Coworking",
					paragraphs: [
						"Incluso teniendo fibra de 900 Mbps en casa, conviene contar con datos móviles locales para traslados y excursiones. Claro y Movistar ofrecen la cobertura 4G/5G más sólida en Playa Salguero, El Rodadero y la carretera costera; puedes activar una eSIM antes de aterrizar en SMR o adquirir una SIM prepago en el Centro Comercial Arrecife en El Rodadero (~5–10 min).",
						"Si deseas alternar tu jornada laboral, El Rodadero (~3 km, ~5–10 min en carro) ofrece cafés y panaderías cómodas, mientras el Centro Histórico (~8 km, ~20–25 min) reúne cafés de especialidad alrededor del Parque de los Novios.",
					],
					comparisonTable: {
						caption:
							"Comparación para trabajo remoto entre Propiedad 1707 y Propiedad 1606 en Edificio Salguero Sunset",
						headers: ["Característica", "Propiedad 1707", "Propiedad 1606"],
						rows: [
							["Piso", "Piso 17", "Piso 16"],
							[
								"Fibra Óptica",
								"Hasta 900 Mbps Fibra Óptica",
								"Hasta 900 Mbps Fibra Óptica",
							],
							[
								"Espacio de Trabajo",
								"Escritorio ergonómico dedicado + comedor",
								"Comedor amplio + terraza cálida frente al mar",
							],
							[
								"Camas",
								"2 Hab. / 4 Camas (1 Queen, 2 Sencillas, 1 Sofá Cama)",
								"2 Hab. / 3 Camas (1 Queen, 1 Doble, 1 Sofá Cama)",
							],
							[
								"Parqueadero Asignado",
								"Bahía 95 (incluida)",
								"Bahía 87 (incluida)",
							],
							[
								"Tarifa por Noche Base",
								"{rate1707Cop} (mín. {minStay1707} noches)",
								"{rate1606Cop} (pico {rate1606HighCop}, {minStay1606}–{maxMinStay1606} noches)",
							],
							["Tarifa de Limpieza", "{cleaning1707Cop}", "{cleaning1606Cop}"],
							["RNT Oficial", "RNT #268781", "RNT #270413"],
						],
					},
				},
				{
					id: "extended-stays-and-logistics",
					heading: "Aeropuerto SMR, Mercado Semanal y Ritmo de Estadía Larga",
					paragraphs: [
						"Playa Salguero facilita enormemente las estadías de varias semanas. Desde el Aeropuerto Simón Bolívar (SMR) llegas en ~15–20 minutos (~10 km) por la vía costera hasta Calle 26 # 2-80. Supermercados como Olímpica y Éxito en El Rodadero entregan domicilios por Rappi directamente en la portería del Edificio Salguero Sunset.",
						"Al terminar tu jornada a las 17:00 COT, caminas una cuadra (~150 m) hasta la arena de Playa Salguero para ver el atardecer o disfrutas la piscina cualquier día excepto los martes (cerrada por mantenimiento). Al hacer tu Reserva Directa en COP ahorras el 15–20% de recargos de plataformas externas.",
					],
					callout: {
						label: "Nota para Estadías de Trabajo Remoto",
						text: "Todos los Huéspedes deben diligenciar el Registro de Huéspedes obligatorio (TRA/SIRE) antes de llegar para habilitar el acceso del edificio y desbloquear la Guía de Huéspedes privada y tu Credencial de Acceso temporal. La administración cobra la Tarifa de Registro del Edificio ({buildingFeeCop} por persona) en recepción al hacer check-in (15:00).",
					},
				},
			],
			faqsTitle: "Preguntas Frecuentes: Trabajo Remoto en Santa Marta",
			faqs: [
				{
					id: "faq-dn-1",
					question:
						"¿Qué tan rápido y estable es el Wi-Fi de OceanViewFlats para videollamadas?",
					answer:
						"Tanto la Propiedad 1707 como la Propiedad 1606 cuentan con fibra óptica de hasta 900 Mbps, ideal para videollamadas HD simultáneas, VPN y cargas frecuentes a la nube.",
					defaultOpen: true,
				},
				{
					id: "faq-dn-2",
					question:
						"¿Qué Propiedad conviene más para nómadas digitales o dos colegas trabajando?",
					answer:
						"La Propiedad 1707 (piso 17, RNT #268781) está diseñada para trabajo remoto: incluye escritorio ergonómico dedicado, 2 Habitaciones / 4 Camas (1 Queen, 2 Sencillas, 1 Sofá Cama), Bahía 95, y Tarifa por Noche desde {rate1707Cop} más {cleaning1707Cop} de Limpieza.",
				},
				{
					id: "faq-dn-3",
					question:
						"¿Cuánto toma llegar al Aeropuerto Simón Bolívar (SMR) desde Playa Salguero?",
					answer:
						"El Aeropuerto Internacional Simón Bolívar (SMR) está a ~10 km (~15–20 minutos en carro) del Edificio Salguero Sunset, con múltiples vuelos diarios hacia Bogotá, Medellín y Cali.",
				},
				{
					id: "faq-dn-4",
					question:
						"¿Puedo reservar varias semanas en Reserva Directa sin comisión OTA?",
					answer:
						"Sí. Selecciona tu Fecha de Check-in y Fecha de Check-out en la Propiedad 1707 o 1606 para obtener una Cotización exacta en COP (Tarifas por Noche + Limpieza fija, 0% comisión OTA) y paga por Mercado Pago con Tarjeta, PSE o Efecty.",
				},
			],
			ctaTitle: "Tu Oficina Frente al Mar en Playa Salguero",
			ctaSubtitle:
				"Trabaja desde el piso 17 con fibra de 900 Mbps: Cotizaciones en COP desde {rate1707Cop} (1707) o {rate1606Cop} (1606).",
		},
		fr: {
			seoTitle: "Télétravail & Nomades à Santa Marta | OceanViewFlats",
			seoDescription:
				"Télétravail à Santa Marta : fibre 900 Mbps, bureau au 17e étage à Playa Salguero, SIM/eSIM, aéroport SMR et séjours directs dès {rate1707Cop}.",
			ogImageAlt:
				"Résidence face à la mer au 17e étage avec fibre 900 Mbps pour le télétravail à Playa Salguero, Santa Marta",
			heroImageAlt:
				"Salon vue mer et balcon de la Propriété 1707 au 17e étage à Playa Salguero, Santa Marta",
			breadcrumbLabel: "Télétravail à Santa Marta",
			badge: "Télétravail & Nomades",
			title: "Guide Télétravail et Nomades Digitaux à Santa Marta",
			subtitle:
				"Fibre optique 900 Mbps, bureau ergonomique au 17e étage, SIM/eSIM en Colombie, cafés calmes et transferts depuis l'aéroport SMR.",
			cardSummary:
				"Tout ce dont les professionnels à distance ont besoin pour des journées productives face à la mer des Caraïbes à Playa Salguero.",
			keyTakeawaysTitle: "Essentiels du Télétravail à Santa Marta",
			keyTakeaways: [
				"La Propriété 1707 (17e étage, RNT #268781) associe fibre symétrique jusqu'à 900 Mbps, bureau ergonomique dédié et calme en hauteur.",
				"Le fuseau horaire COT (UTC-5) reste fixe toute l'année, offrant un chevauchement fluide avec les Amériques et l'après-midi européen.",
				"L'aéroport international Simón Bolívar (SMR) se situe à ~10 km (~15–20 min en voiture) de l'Edificio Salguero Sunset.",
				"En Réservation Directe, la Propriété 1707 débute à {rate1707Cop}/nuit (min. {minStay1707} nuits) plus {cleaning1707Cop} de Frais de Ménage fixes, sans commission OTA.",
			],
			sections: [
				{
					id: "fiber-and-workspace",
					heading: "Fibre 900 Mbps et Poste Ergonomique au 17e Étage",
					paragraphs: [
						"Beaucoup d'hébergements côtiers des Caraïbes proposent un Wi-Fi insuffisant dès que deux visioconférences tournent en parallèle. À l'Edificio Salguero Sunset, nos Propriétés 1707 et 1606 disposent d'une connexion fibre jusqu'à 900 Mbps adaptée aux appels vidéo HD, aux VPN et aux transferts cloud volumineux.",
						"La Propriété 1707 au 17e étage a été aménagée pour le télétravail prolongé : bureau dédié et trois pièces climatisées séparées (deux chambres et le séjour) permettant à deux personnes de travailler en appel simultanément.",
					],
					bullets: [
						"Calme en étage élevé : le 17e étage protège vos réunions du bruit de la rue et de la plage.",
						"Agencement spacieux : 2 Chambres / 4 Lits (1 Queen, 2 Lits Simples, 1 Canapé-lit ; max 6 Voyageurs) et Place de Parking Attribuée 95.",
						"Autonomie complète : cuisine équipée, buanderie et arrivée autonome dès 15h00 par serrure connectée.",
					],
					factCards: [
						{
							label: "Débit Fibre",
							value: "Jusqu'à 900 Mbps",
							detail: "Idéal visioconférences HD & cloud",
						},
						{
							label: "Fuseau Horaire",
							value: "COT (UTC-5)",
							detail: "Sans changement d'heure saisonnier",
						},
						{
							label: "Propriété 1707",
							value: "{rate1707Cop}",
							detail: "Min. {minStay1707} nuits + {cleaning1707Cop} Ménage",
						},
					],
				},
				{
					id: "connectivity-sim-and-cafes",
					heading: "SIM/eSIM de Secours, Cafés et Espaces de Coworking",
					paragraphs: [
						"En complément de la fibre 900 Mbps, une ligne mobile locale facilite vos déplacements. Claro et Movistar offrent la meilleure couverture 4G/5G à Playa Salguero et El Rodadero ; vous pouvez activer une eSIM avant votre arrivée à SMR ou acheter une carte prépayée au centre commercial Arrecife (~5–10 min).",
						"Pour varier d'environnement, El Rodadero (~3 km, ~5–10 min en voiture) propose des cafés accessibles, tandis que le Centre Historique (~8 km, ~20–25 min) regroupe des cafés de spécialité autour du Parque de los Novios.",
					],
					comparisonTable: {
						caption:
							"Comparatif télétravail entre la Propriété 1707 et la Propriété 1606 à l'Edificio Salguero Sunset",
						headers: ["Critère", "Propriété 1707", "Propriété 1606"],
						rows: [
							["Étage", "17e étage", "16e étage"],
							[
								"Fibre Optique",
								"Fibre jusqu'à 900 Mbps",
								"Fibre jusqu'à 900 Mbps",
							],
							[
								"Espace Bureau",
								"Bureau ergonomique dédié + table repas",
								"Table repas + terrasse face au coucher de soleil",
							],
							[
								"Couchages",
								"2 Ch. / 4 Lits (1 Queen, 2 Simples, 1 Canapé-lit)",
								"2 Ch. / 3 Lits (1 Queen, 1 Double, 1 Canapé-lit)",
							],
							["Parking Attribué", "Place 95 (incluse)", "Place 87 (incluse)"],
							[
								"Tarif par Nuit",
								"{rate1707Cop} (min. {minStay1707} nuits)",
								"{rate1606Cop} (haute saison {rate1606HighCop}, {minStay1606}–{maxMinStay1606} nuits)",
							],
							["Frais de Ménage", "{cleaning1707Cop}", "{cleaning1606Cop}"],
							["RNT Officiel", "RNT #268781", "RNT #270413"],
						],
					},
				},
				{
					id: "extended-stays-and-logistics",
					heading: "Transferts Aéroport SMR, Courses et Longs Séjours",
					paragraphs: [
						"Playa Salguero simplifie les séjours de plusieurs semaines. Les taxis agréés depuis l'aéroport Simón Bolívar (SMR) rejoignent Calle 26 # 2-80 en ~15–20 minutes (~10 km). Les supermarchés Olímpica et Éxito d'El Rodadero livrent directement à la réception via Rappi.",
						"En fin de journée, la plage de Playa Salguero est à un bloc (~150 m) pour profiter du coucher de soleil, et la piscine reste ouverte tous les jours sauf le mardi (entretien). La Réservation Directe en COP évite les 15–20 % de frais des plateformes tierces.",
					],
					callout: {
						label: "Logistique Long Séjour",
						text: "Chaque Voyageur doit compléter le Registre des Voyageurs légal (TRA/SIRE) avant l'arrivée afin de valider l'accès immeuble et débloquer le Guide Voyageur privé ainsi que votre Code d'Accès temporaire. Les Frais d'Enregistrement de l'Immeuble ({buildingFeeCop}/personne) se règlent à la réception lors du check-in (15h00).",
					},
				},
			],
			faqsTitle: "Questions Fréquentes : Télétravail à Santa Marta",
			faqs: [
				{
					id: "faq-dn-1",
					question:
						"Le Wi-Fi d'OceanViewFlats est-il fiable pour les visioconférences ?",
					answer:
						"Oui. Les Propriétés 1707 et 1606 bénéficient d'une fibre optique jusqu'à 900 Mbps, conçue pour des appels vidéo HD simultanés, des VPN et des envois de fichiers lourds.",
					defaultOpen: true,
				},
				{
					id: "faq-dn-2",
					question:
						"Quelle Propriété choisir pour un nomade digital ou deux collègues ?",
					answer:
						"La Propriété 1707 (17e étage, RNT #268781) est pensée pour le télétravail avec bureau ergonomique dédié, 2 Chambres / 4 Lits (1 Queen, 2 Simples, 1 Canapé-lit), Place 95, dès {rate1707Cop}/nuit + {cleaning1707Cop} de Ménage.",
				},
				{
					id: "faq-dn-3",
					question:
						"Quel est le temps de trajet vers l'aéroport Simón Bolívar (SMR) ?",
					answer:
						"L'aéroport SMR est à ~10 km (~15–20 minutes en voiture) de l'Edificio Salguero Sunset, avec des vols directs quotidiens vers Bogotá, Medellín et Cali.",
				},
				{
					id: "faq-dn-4",
					question:
						"Puis-je réserver plusieurs semaines en direct sans frais OTA ?",
					answer:
						"Oui. Sélectionnez vos dates sur la Propriété 1707 ou 1606 pour obtenir un devis immédiat en COP (Tarifs par Nuit + Ménage fixe, 0 % de commission OTA) payable via Mercado Pago (Carte, PSE ou Bon en espèces).",
				},
			],
			ctaTitle: "Installez Votre Bureau Face aux Caraïbes",
			ctaSubtitle:
				"Télétravaillez au 17e étage en fibre 900 Mbps : devis direct en COP dès {rate1707Cop} (1707) ou {rate1606Cop} (1606).",
		},
		it: {
			seoTitle: "Lavoro da Remoto a Santa Marta (900 Mbps) | OceanViewFlats",
			seoDescription:
				"Lavoro da remoto a Santa Marta: fibra 900 Mbps, postazione al 17° piano a Playa Salguero, SIM/eSIM, aeroporto SMR e soggiorni da {rate1707Cop}.",
			ogImageAlt:
				"Residenza fronte mare al 17° piano con fibra ottica 900 Mbps per lavoro da remoto a Playa Salguero, Santa Marta",
			heroImageAlt:
				"Soggiorno vista mare e balcone della Proprietà 1707 al 17° piano a Playa Salguero, Santa Marta",
			breadcrumbLabel: "Lavoro da Remoto a Santa Marta",
			badge: "Lavoro da Remoto & Nomadi",
			title: "Guida al Lavoro da Remoto e Nomadi Digitali a Santa Marta",
			subtitle:
				"Fibra ottica fino a 900 Mbps, postazione ergonomica al 17° piano, SIM/eSIM in Colombia, caffetterie tranquille e logistica dall'aeroporto SMR.",
			cardSummary:
				"Tutto ciò che serve a sviluppatori, founder e team distribuiti per lavorare con continuità fronte mare a Playa Salguero.",
			keyTakeawaysTitle: "Punti Essenziali per Lavorare da Remoto",
			keyTakeaways: [
				"La Proprietà 1707 (17° piano, RNT #268781) unisce fibra fino a 900 Mbps, scrivania ergonomica dedicata e silenzio d'alta quota.",
				"Il fuso orario COT (UTC-5) non cambia mai durante l'anno, garantendo sovrapposizione pulita con Americhe ed Europa.",
				"L'Aeroporto Internazionale Simón Bolívar (SMR) dista ~10 km (~15–20 min in auto) dall'Edificio Salguero Sunset.",
				"Le Tariffe Notturne in Prenotazione Diretta per la Proprietà 1707 partono da {rate1707Cop} (min. {minStay1707} notti) più {cleaning1707Cop} di Tariffa di Pulizia fissa, con 0% commissioni OTA.",
			],
			sections: [
				{
					id: "fiber-and-workspace",
					heading: "Fibra Ottica 900 Mbps ed Ergonomia al 17° Piano",
					paragraphs: [
						"Molti alloggi costieri nei Caraibi colombiani soffrono di Wi-Fi instabile quando due persone partecipano a videochiamate simultanee. Nell'Edificio Salguero Sunset sia la Proprietà 1707 sia la Proprietà 1606 offrono fibra fino a 900 Mbps, pensata per call HD, VPN aziendali e caricamenti cloud.",
						"La Proprietà 1707 al 17° piano è progettata per il lavoro da remoto prolungato: dispone di postazione ergonomica dedicata e tre ambienti climatizzati indipendenti (due camere e zona giorno) per gestire riunioni contemporanee in tranquillità.",
					],
					bullets: [
						"Quiete al 17° piano: l'altezza isola le tue call dai rumori della strada e della spiaggia.",
						"Configurazione flessibile: 2 Camere / 4 Letti (1 Queen, 2 Singoli, 1 Divano Letto; max 6 Ospiti) e Posto Auto Assegnato 95.",
						"Autonomia completa: cucina attrezzata, lavanderia interna e self check-in dalle 15:00 con Credenziale di Accesso temporanea.",
					],
					factCards: [
						{
							label: "Connessione Fibra",
							value: "Fino a 900 Mbps",
							detail: "Ottimale per call HD e flussi cloud",
						},
						{
							label: "Fuso Orario",
							value: "COT (UTC-5)",
							detail: "Nessun cambio d'ora legale tutto l'anno",
						},
						{
							label: "Proprietà 1707",
							value: "{rate1707Cop}",
							detail: "Min. {minStay1707} notti + {cleaning1707Cop} Pulizia",
						},
					],
				},
				{
					id: "connectivity-sim-and-cafes",
					heading: "SIM/eSIM di Backup, Caffetterie e Coworking",
					paragraphs: [
						"Oltre alla fibra da 900 Mbps, conviene avere dati mobili locali per trasferimenti e gite. Claro e Movistar garantiscono la copertura 4G/5G più affidabile tra Playa Salguero, El Rodadero e la statale costiera; puoi attivare una eSIM prima del volo per SMR o comprare una SIM prepagata al centro commerciale Arrecife (~5–10 min).",
						"Per cambiare scenario tra una call e l'altra, El Rodadero (~3 km, ~5–10 min in auto) offre caffetterie comode, mentre il Centro Storico (~8 km, ~20–25 min) ospita caffetterie di specialità intorno al Parque de los Novios.",
					],
					comparisonTable: {
						caption:
							"Confronto per lavoro da remoto tra Proprietà 1707 e Proprietà 1606 nell'Edificio Salguero Sunset",
						headers: ["Aspetto", "Proprietà 1707", "Proprietà 1606"],
						rows: [
							["Piano", "17° piano", "16° piano"],
							[
								"Fibra Ottica",
								"Fibra fino a 900 Mbps",
								"Fibra fino a 900 Mbps",
							],
							[
								"Postazione",
								"Scrivania ergonomica dedicata + tavolo pranzo",
								"Tavolo pranzo + terrazza calda al tramonto",
							],
							[
								"Letti",
								"2 Camere / 4 Letti (1 Queen, 2 Singoli, 1 Divano Letto)",
								"2 Camere / 3 Letti (1 Queen, 1 Matrimoniale, 1 Divano Letto)",
							],
							[
								"Posto Auto Assegnato",
								"Posto 95 (incluso)",
								"Posto 87 (incluso)",
							],
							[
								"Tariffa Notturna Base",
								"{rate1707Cop} (min. {minStay1707} notti)",
								"{rate1606Cop} (alta stagione {rate1606HighCop}, {minStay1606}–{maxMinStay1606} notti)",
							],
							["Tariffa di Pulizia", "{cleaning1707Cop}", "{cleaning1606Cop}"],
							["RNT Ufficiale", "RNT #268781", "RNT #270413"],
						],
					},
				},
				{
					id: "extended-stays-and-logistics",
					heading: "Trasferimenti SMR, Spesa Settimanale e Ritmo Quotidiano",
					paragraphs: [
						"Playa Salguero rende semplicissimi i soggiorni di più settimane. I taxi autorizzati dall'Aeroporto Simón Bolívar (SMR) raggiungono Calle 26 # 2-80 in ~15–20 minuti (~10 km). I supermercati Olímpica ed Éxito di El Rodadero consegnano la spesa tramite Rappi direttamente alla reception.",
						"Terminato il lavoro alle 17:00 COT, basta una passeggiata di ~150 m fino a Playa Salguero per godersi il tramonto, oppure un tuffo in piscina ogni giorno tranne il martedì (chiusa per manutenzione). Prenotando direttamente in COP eviti il 15–20% di commissioni dei portali esterni.",
					],
					callout: {
						label: "Promemoria per Soggiorni Lunghi",
						text: "Ogni Ospite deve completare il Registro Ospiti obbligatorio (TRA/SIRE) prima dell'arrivo per l'autorizzazione condominiale e lo sblocco della Guida Ospiti privata e della Credenziale di Accesso temporanea. La Tassa di Registrazione dell'Edificio ({buildingFeeCop}/persona) si versa in reception al check-in (15:00).",
					},
				},
			],
			faqsTitle: "Domande Frequenti: Lavoro da Remoto a Santa Marta",
			faqs: [
				{
					id: "faq-dn-1",
					question:
						"Quanto è veloce e stabile il Wi-Fi di OceanViewFlats per le videochiamate?",
					answer:
						"Sia la Proprietà 1707 sia la Proprietà 1606 dispongono di fibra ottica fino a 900 Mbps, ideale per videochiamate HD simultanee, VPN e upload di file pesanti.",
					defaultOpen: true,
				},
				{
					id: "faq-dn-2",
					question:
						"Quale Proprietà è più adatta ai nomadi digitali o a due colleghi?",
					answer:
						"La Proprietà 1707 (17° piano, RNT #268781) è pensata per il lavoro da remoto con scrivania ergonomica dedicata, 2 Camere / 4 Letti (1 Queen, 2 Singoli, 1 Divano Letto), Posto 95, da {rate1707Cop}/notte + {cleaning1707Cop} di Pulizia.",
				},
				{
					id: "faq-dn-3",
					question:
						"Quanto tempo serve per raggiungere l'Aeroporto Simón Bolívar (SMR)?",
					answer:
						"L'aeroporto SMR dista ~10 km (~15–20 minuti in auto) dall'Edificio Salguero Sunset, con collegamenti diretti giornalieri per Bogotá, Medellín e Cali.",
				},
				{
					id: "faq-dn-4",
					question:
						"Posso prenotare più settimane in Prenotazione Diretta senza commissioni OTA?",
					answer:
						"Sì. Scegli le date sulla Proprietà 1707 o 1606 per calcolare subito un preventivo trasparente in COP (Tariffe Notturne + Pulizia fissa, 0% commissioni OTA) tramite Mercado Pago (Carta, PSE o Voucher Contanti).",
				},
			],
			ctaTitle: "Il Tuo Ufficio Fronte Mare a Playa Salguero",
			ctaSubtitle:
				"Lavora dal 17° piano con fibra 900 Mbps: preventivi diretti in COP da {rate1707Cop} (1707) o {rate1606Cop} (1606).",
		},
		de: {
			seoTitle: "Remote Work & Nomaden in Santa Marta | OceanViewFlats",
			seoDescription:
				"Remote Work in Santa Marta: 900 Mbit/s Glasfaser, Arbeitsplatz im 17. Stock in Playa Salguero, SIM/eSIM, SMR-Transfer & Aufenthalte ab {rate1707Cop}.",
			ogImageAlt:
				"Meerblick-Apartment im 17. Stock mit 900 Mbit/s Glasfaser für Remote Work in Playa Salguero, Santa Marta",
			heroImageAlt:
				"Heller Wohnbereich mit Meerblick und Balkon in Unterkunft 1707 im 17. Stock in Playa Salguero, Santa Marta",
			breadcrumbLabel: "Remote Work in Santa Marta",
			badge: "Remote Work & Digitale Nomaden",
			title: "Remote Work & Digitale Nomaden Guide für Santa Marta",
			subtitle:
				"Bis zu 900 Mbit/s Glasfaser-WLAN, ergonomischer Arbeitsplatz im 17. Stock, SIM/eSIM in Kolumbien, ruhige Cafés und SMR-Flughafenlogistik.",
			cardSummary:
				"Alles, was Remote-Teams, Entwickler und Gründer für produktive Arbeitstage direkt am Meer in Playa Salguero brauchen.",
			keyTakeawaysTitle: "Remote-Work-Fakten auf einen Blick",
			keyTakeaways: [
				"Unterkunft 1707 (17. Stock, RNT #268781) vereint bis zu 900 Mbit/s Glasfaser-WLAN, eigenen ergonomischen Schreibtisch und ruhige Höhenlage.",
				"Die Zeitzone COT (UTC-5) kennt keine Sommerzeit und bietet ganzjährig ideale Überschneidung mit Nordamerika und Europa.",
				"Der Flughafen Simón Bolívar (SMR) liegt nur ~10 km (~15–20 Min. per Auto) entfernt – optimal für Flüge nach Bogotá und Medellín.",
				"Übernachtungsraten per Direktbuchung für Unterkunft 1707 beginnen bei {rate1707Cop} (mind. {minStay1707} Nächte) zzgl. {cleaning1707Cop} Reinigungsgebühr bei 0% OTA-Gebühren.",
			],
			sections: [
				{
					id: "fiber-and-workspace",
					heading: "900 Mbit/s Glasfaser & Ergonomie im 17. Stock",
					paragraphs: [
						"Viele Ferienunterkünfte an der kolumbianischen Karibikküste bieten WLAN, das bei parallelen Videokonferenzen einbricht. Im Edificio Salguero Sunset verfügen sowohl Unterkunft 1707 als auch Unterkunft 1606 über Glasfaser-WLAN mit bis zu 900 Mbit/s für stabile HD-Calls, VPNs und Cloud-Uploads.",
						"Unterkunft 1707 im 17. Stock wurde gezielt für längere Workations eingerichtet: Neben dem festen Arbeitsplatz sorgen drei klimatisierte Räume (zwei Schlafzimmer plus Wohnzimmer) dafür, dass zwei Personen gleichzeitig ungestört telefonieren können.",
					],
					bullets: [
						"Ruhe in der Höhe: Der 17. Stock hält Straßen- und Strandgeräusche von Ihren Meetings fern.",
						"Durchdachte Aufteilung: 2 Schlafzimmer / 4 Betten (1 Queen, 2 Einzelbetten, 1 Schlafsofa; max. 6 Gäste) und fester Stellplatz 95.",
						"Volle Unabhängigkeit: Komplette Küche, Waschgelegenheit und Self-Check-in ab 15:00 per temporärem Smart-Lock-Code.",
					],
					factCards: [
						{
							label: "Glasfaser-WLAN",
							value: "Bis 900 Mbit/s",
							detail: "Stabil für HD-Calls & Cloud-Workflows",
						},
						{
							label: "Zeitzone",
							value: "COT (UTC-5)",
							detail: "Ganzjährig ohne Zeitumstellung",
						},
						{
							label: "Unterkunft 1707",
							value: "{rate1707Cop}",
							detail:
								"Mind. {minStay1707} Nächte + {cleaning1707Cop} Reinigung",
						},
					],
				},
				{
					id: "connectivity-sim-and-cafes",
					heading: "SIM/eSIM-Backup, Cafés & Coworking-Optionen",
					paragraphs: [
						"Zusätzlich zum 900 Mbit/s Glasfaseranschluss empfiehlt sich für Ausflüge und Reisetage eine mobile Datenverbindung. Claro und Movistar bieten die stabilste 4G/5G-Abdeckung in Playa Salguero und El Rodadero; eine eSIM lässt sich vorab aktivieren oder als Prepaid-SIM im Einkaufszentrum Arrecife (~5–10 Min.) kaufen.",
						"Für Abwechslung zwischendurch bietet El Rodadero (~3 km, ~5–10 Min. per Auto) entspannte Cafés, während die Altstadt (~8 km, ~20–25 Min.) rund um den Parque de los Novios zahlreiche Spezialitätencafés bereithält.",
					],
					comparisonTable: {
						caption:
							"Remote-Work-Vergleich zwischen Unterkunft 1707 und Unterkunft 1606 im Edificio Salguero Sunset",
						headers: ["Merkmal", "Unterkunft 1707", "Unterkunft 1606"],
						rows: [
							["Etage", "17. Stock", "16. Stock"],
							[
								"Glasfaser-WLAN",
								"Bis zu 900 Mbps Glasfaser",
								"Bis zu 900 Mbps Glasfaser",
							],
							[
								"Arbeitsplatz",
								"Eigener ergonomischer Schreibtisch + Esstisch",
								"Esstisch + warme Sonnenuntergangsterrasse",
							],
							[
								"Bettenaufteilung",
								"2 SZ / 4 Betten (1 Queen, 2 Einzel, 1 Schlafsofa)",
								"2 SZ / 3 Betten (1 Queen, 1 Doppel, 1 Schlafsofa)",
							],
							[
								"Stellplatz",
								"Stellplatz 95 (inklusive)",
								"Stellplatz 87 (inklusive)",
							],
							[
								"Basis-Nachtrate",
								"{rate1707Cop} (mind. {minStay1707} Nächte)",
								"{rate1606Cop} (Hochsaison {rate1606HighCop}, {minStay1606}–{maxMinStay1606} Nächte)",
							],
							["Reinigungsgebühr", "{cleaning1707Cop}", "{cleaning1606Cop}"],
							["Offizielle RNT", "RNT #268781", "RNT #270413"],
						],
					},
				},
				{
					id: "extended-stays-and-logistics",
					heading: "Flughafentransfer SMR, Einkauf & Langzeit-Rhythmus",
					paragraphs: [
						"Playa Salguero macht mehrwöchige Aufenthalte angenehm unkompliziert. Autorisierte Flughafentaxis vom Flughafen Simón Bolívar (SMR) erreichen Calle 26 # 2-80 in ~15–20 Minuten (~10 km). Supermärkte wie Olímpica und Éxito in El Rodadero liefern per Rappi direkt an die Rezeption.",
						"Nach Feierabend um 17:00 COT spazieren Sie einen Block (~150 m) zum Sonnenuntergang an den Strand Playa Salguero oder nutzen den Pool (außer dienstags wegen Wartung). Durch Direktbuchungen in COP sparen Sie 15–20% gegenüber OTA-Servicegebühren.",
					],
					callout: {
						label: "Hinweis für Workations",
						text: "Alle Gäste müssen vor Anreise die gesetzliche Gästeregistrierung (TRA/SIRE) ausfüllen, damit die Hausverwaltung die Freigabe erteilt und Ihr privater Gästeleitfaden samt Zugangscode freigeschaltet wird. Die einmalige Gebäude-Registrierungsgebühr ({buildingFeeCop}/Person) zahlen Sie beim Check-in (15:00) an der Rezeption.",
					},
				},
			],
			faqsTitle: "Häufige Fragen: Remote Work in Santa Marta",
			faqs: [
				{
					id: "faq-dn-1",
					question:
						"Wie schnell und stabil ist das WLAN bei OceanViewFlats für Videokonferenzen?",
					answer:
						"Sowohl Unterkunft 1707 als auch Unterkunft 1606 bieten Glasfaser-WLAN bis zu 900 Mbit/s – ideal für parallele HD-Videocalls, VPNs und große Uploads.",
					defaultOpen: true,
				},
				{
					id: "faq-dn-2",
					question:
						"Welche Unterkunft eignet sich am besten für digitale Nomaden oder zwei Kollegen?",
					answer:
						"Unterkunft 1707 (17. Stock, RNT #268781) ist auf Remote Work ausgelegt: eigener ergonomischer Schreibtisch, 2 Schlafzimmer / 4 Betten (1 Queen, 2 Einzelbetten, 1 Schlafsofa), Stellplatz 95, ab {rate1707Cop}/Nacht + {cleaning1707Cop} Reinigung.",
				},
				{
					id: "faq-dn-3",
					question:
						"Wie lange dauert die Fahrt zum Flughafen Simón Bolívar (SMR)?",
					answer:
						"Der Flughafen SMR liegt ~10 km (~15–20 Minuten per Auto) vom Edificio Salguero Sunset entfernt, mit täglichen Direktflügen nach Bogotá, Medellín und Cali.",
				},
				{
					id: "faq-dn-4",
					question:
						"Kann ich mehrwöchige Aufenthalte direkt ohne OTA-Gebühren buchen?",
					answer:
						"Ja. Wählen Sie Check-in- und Check-out-Datum bei Unterkunft 1707 oder 1606 für ein sofortiges COP-Angebot (Nachtraten + pauschale Reinigungsgebühr, 0% OTA-Provision) über Mercado Pago (Karte, PSE oder Bargeld-Gutschein).",
				},
			],
			ctaTitle: "Ihr Strandbüro im 17. Stock von Playa Salguero",
			ctaSubtitle:
				"Arbeiten mit 900 Mbit/s Glasfaser: transparente COP-Direktangebote ab {rate1707Cop} (1707) oder {rate1606Cop} (1606).",
		},
		ja: {
			seoTitle: "サンタマルタでのリモートワーク＆ノマド滞在 | OceanViewFlats",
			seoDescription:
				"サンタマルタでのリモートワーク完全ガイド：最大900 Mbps光回線、プラヤサルグエロ17階ワークスペース、SIM/eSIM、SMR空港アクセス、{rate1707Cop}〜の直接予約。",
			ogImageAlt:
				"サンタマルタのプラヤ・サルグエロ17階、最大900 Mbps光Wi-Fi付き海辺リモートワーク向けレジデンス",
			heroImageAlt:
				"プラヤ・サルグエロの物件1707（17階）オーシャンフロントのリビングとバルコニー",
			breadcrumbLabel: "サンタマルタ・リモートワークガイド",
			badge: "リモートワーク＆ノマド",
			title: "サンタマルタでのリモートワーク＆デジタルノマド滞在ガイド",
			subtitle:
				"最大900 Mbps光ファイバーWi-Fi、17階の専用ワークスペース、コロンビアSIM/eSIM、落ち着いたカフェ、SMR空港からの移動を詳しく解説します。",
			cardSummary:
				"エンジニア・起業家・リモートチームがプラヤ・サルグエロの海辺で快適に仕事をこなすための実践ガイド。",
			keyTakeawaysTitle: "リモートワーク重要ポイント",
			keyTakeaways: [
				"物件1707（17階・RNT #268781）は最大900 Mbps光ファイバーWi-Fi、専用ワークデスク、街の騒音が届きにくい高層静粛性を備えています。",
				"コロンビア時間（COT・UTC-5）はサマータイムがなく、北米・中南米や欧州午後とのオンライン会議調整が年間を通じて安定しています。",
				"シモン・ボリバル国際空港（SMR）まで約10km（車で約15〜20分）で、ボゴタやメデジンへの国内移動もスムーズです。",
				"物件1707の直接予約は{rate1707Cop}/泊〜（最低{minStay1707}泊）＋清掃料金一律{cleaning1707Cop}、OTA手数料0%です。",
			],
			sections: [
				{
					id: "fiber-and-workspace",
					heading: "最大900 Mbps光回線と17階の快適ワーク環境",
					paragraphs: [
						"コロンビア・カリブ海沿岸の短期滞在施設では、2名同時にビデオ会議を行うと回線が不安定になるケースが少なくありません。エディフィシオ・サルグエロ・サンセットの物件1707・1606はどちらも最大900 Mbpsの光ファイバーWi-Fiを導入しており、HDオンライン会議、VPN接続、大容量アップロードも快適です。",
						"特に17階の物件1707は長期ワーケーション向けに専用デスクを完備。2つの寝室とリビングの計3ゾーンすべてにエアコンがあるため、同僚やパートナー同士が同時刻に別室でミーティングを行うことも可能です。",
					],
					bullets: [
						"17階ならではの静けさ：通りやビーチの雑音を抑え、クリアな音声で会議に参加できます。",
						"ゆとりの間取り：2ベッドルーム・4ベッド（クイーン1・シングル2・ソファベッド1、最大6名）＋専用駐車区画95番。",
						"長期滞在設備：フルキッチン、洗濯設備、15:00からのスマートロック自己チェックイン対応。",
					],
					factCards: [
						{
							label: "光ファイバー",
							value: "最大 900 Mbps",
							detail: "HD会議やクラウド作業も安定",
						},
						{
							label: "タイムゾーン",
							value: "COT (UTC-5)",
							detail: "年間固定・サマータイムなし",
						},
						{
							label: "物件1707料金",
							value: "{rate1707Cop}",
							detail: "最低{minStay1707}泊＋清掃{cleaning1707Cop}",
						},
					],
				},
				{
					id: "connectivity-sim-and-cafes",
					heading: "コロンビアSIM/eSIMのバックアップと周辺カフェ",
					paragraphs: [
						"室内の900 Mbps光回線に加え、移動日や日帰り旅用にローカルモバイル回線を用意しておくと安心です。プラヤ・サルグエロ〜エル・ロダデロ周辺ではClaroとMovistarの4G/5G網が安定しており、SMR到着前にeSIMを有効化するか、エル・ロダデロのArrecifeショッピングセンター（車で約5〜10分）でプリペイドSIMを購入できます。",
						"気分転換にカフェ作業をしたい日は、エル・ロダデロ（約3km・約5〜10分）のカフェや、サンタマルタ旧市街（約8km・約20〜25分）のパルケ・デ・ロス・ノビオス周辺のスペシャルティコーヒー店が便利です。",
					],
					comparisonTable: {
						caption:
							"エディフィシオ・サルグエロ・サンセットにおける物件1707と物件1606のリモートワーク環境比較",
						headers: ["比較項目", "物件1707", "物件1606"],
						rows: [
							["階数", "17階", "16階"],
							[
								"光ファイバーWi-Fi",
								"最大 900 Mbps 光ファイバー",
								"最大 900 Mbps 光ファイバー",
							],
							[
								"ワークスペース",
								"専用ワークデスク＋ダイニングテーブル",
								"ダイニングテーブル＋サンセットテラス",
							],
							[
								"ベッド構成",
								"2BR・4ベッド（クイーン1・シングル2・ソファベッド1）",
								"2BR・3ベッド（クイーン1・ダブル1・ソファベッド1）",
							],
							[
								"専用駐車スペース",
								"駐車区画 95番（無料）",
								"駐車区画 87番（無料）",
							],
							[
								"基本宿泊料金",
								"{rate1707Cop}（最低{minStay1707}泊）",
								"{rate1606Cop}（ハイシーズン{rate1606HighCop}、最低{minStay1606}〜{maxMinStay1606}泊）",
							],
							["清掃料金（一律）", "{cleaning1707Cop}", "{cleaning1606Cop}"],
							["公式RNT登録", "RNT #268781", "RNT #270413"],
						],
					},
				},
				{
					id: "extended-stays-and-logistics",
					heading: "SMR空港移動・スーパー配送・長期滞在のルーティン",
					paragraphs: [
						"プラヤ・サルグエロは数週間単位の滞在でも生活動線が整っています。シモン・ボリバル国際空港（SMR）からは沿岸ルートで約15〜20分（約10km）でCalle 26 # 2-80へ到着。エル・ロダデロのスーパー（Olímpica・Éxito）からはRappiでフロントまで食料品配達が可能です。",
						"17:00 COTに仕事を終えたら徒歩約2分（約150m）のビーチで夕日を眺めたり、火曜（メンテナンス休業）以外の日にプールでリフレッシュできます。COP建ての直接予約ならOTAの15〜20%サービス手数料を節約できます。",
					],
					callout: {
						label: "ワーケーション到着前のご案内",
						text: "ご到着前に全員分の法定ゲスト登録（TRA/SIRE）を完了いただくことで、建物入館承認および非公開ゲストガイド・スマートロック入室コードが発行されます。チェックイン（15:00）時にフロントで建物登録料（{buildingFeeCop}／名）のお支払いが必要です。",
					},
				},
			],
			faqsTitle: "よくある質問：サンタマルタでのリモートワーク",
			faqs: [
				{
					id: "faq-dn-1",
					question: "OceanViewFlatsのWi-Fiはビデオ会議でも安定していますか？",
					answer:
						"はい。物件1707・1606ともに最大900 Mbpsの光ファイバーWi-Fiを備えており、複数台のHDビデオ会議、VPN、大容量ファイル転送を快適にこなせます。",
					defaultOpen: true,
				},
				{
					id: "faq-dn-2",
					question:
						"ノマド滞在や同僚2名でのシェアにはどちらの物件が向いていますか？",
					answer:
						"専用ワークデスクと4ベッド構成（クイーン1・シングル2・ソファベッド1）を備えた17階の物件1707（RNT #268781・駐車区画95番）がおすすめです。宿泊料金は{rate1707Cop}/泊〜＋清掃料金{cleaning1707Cop}です。",
				},
				{
					id: "faq-dn-3",
					question:
						"プラヤ・サルグエロからシモン・ボリバル空港（SMR）までの所要時間は？",
					answer:
						"エディフィシオ・サルグエロ・サンセットから約10km（車で約15〜20分）で、ボゴタ・メデジン・カリ行きの直行便が毎日多数運航しています。",
				},
				{
					id: "faq-dn-4",
					question: "数週間の長期滞在もOTA手数料0%で直接予約できますか？",
					answer:
						"はい。物件1707または1606のカレンダーでチェックイン日・チェックアウト日を選ぶと、COP公式見積もり（宿泊料金＋一律清掃料金・OTA手数料0%）が即時算出され、Mercado Pago（カード・PSE・現金バウチャー）で決済できます。",
				},
			],
			ctaTitle: "17階オーシャンフロントをリモート拠点に",
			ctaSubtitle:
				"900 Mbps光回線＆OTA手数料0%のCOP直接予約（物件1707：{rate1707Cop}〜／物件1606：{rate1606Cop}〜）。",
		},
	},
};
