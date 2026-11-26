import { IMAGE_DIMENSIONS, IMAGES } from "../../constants/config";
import type { DestinationGuideDefinition } from "./types";

export const DIRECT_RESERVATION_GUIDE: DestinationGuideDefinition = {
	slug: "direct-reservation-guide",
	id: "destination-guide-direct-reservation-guide",
	categoryIcon: "shield",
	heroImage: IMAGES.mainEntrance,
	heroImageDimensions: IMAGE_DIMENSIONS.mainEntrance,
	ogImage: {
		path: IMAGES.mainEntrance,
		...IMAGE_DIMENSIONS.mainEntrance,
	},
	datePublished: "2026-11-26",
	dateModified: "2026-11-26",
	readingTimeMinutes: 7,
	relatedProperties: ["1707", "1606"],
	relatedGuideSlugs: [
		"playa-salguero-vs-el-rodadero",
		"digital-nomad-remote-work",
		"day-trips-from-playa-salguero",
	],
	locales: {
		en: {
			seoTitle: "Santa Marta Direct Reservation Guide | OceanViewFlats",
			seoDescription:
				"Santa Marta Direct Reservation guide: 0% OTA fees in COP from {portfolioLowCop}, Mercado Pago (Card/PSE/Efecty), RNT #268781/#270413 & Guest Registry.",
			ogImageAlt:
				"Main entrance of Edificio Salguero Sunset in Playa Salguero, Santa Marta for verified OceanViewFlats Direct Reservations",
			heroImageAlt:
				"Edificio Salguero Sunset front entrance at Calle 26 # 2-80 in Playa Salguero, Santa Marta",
			breadcrumbLabel: "Direct Reservation Guide (COP)",
			badge: "Direct Reservation & Compliance",
			title:
				"Santa Marta Direct Reservation Guide: COP Quotes, RNT & Guest Registry",
			subtitle:
				"How to reserve directly at 0% OTA commission in Settlement Currency (COP) via Mercado Pago, verify Colombian RNT compliance, and complete your statutory Guest Registry.",
			cardSummary:
				"Understand how OceanViewFlats Direct Reservations save 15–20% over external OTAs while maintaining full Colombian RNT, TRA/SIRE, and Law 1581 privacy compliance.",
			keyTakeawaysTitle: "Direct Reservation Essentials at a Glance",
			keyTakeaways: [
				"0% OTA service fees: every Direct Reservation Quote settles strictly in Settlement Currency (COP)—itemized Nightly Rates plus one flat Cleaning Fee ({cleaning1707Cop} for Property 1707, {cleaning1606Cop} for Property 1606).",
				"Pay securely via Mercado Pago using Credit/Debit Card (Instant Settlement, 30-min Standard Hold Window), PSE bank transfer (Asynchronous Settlement), or Cash Voucher via Efecty (72-hour Voucher Hold Window).",
				"Both Properties hold active Colombian Ministry of Commerce tourism registrations: RNT #268781 (Property 1707) and RNT #270413 (Property 1606), operated by Data Controller OceanViewFlats — Manuel Enrique Herrera.",
				"Check-in is 15:00, check-out is 11:00, Edificio Salguero Sunset collects a {buildingRegistrationFeeCop} per-person Building Registration Fee at reception, and the swimming pool closes Tuesdays for maintenance.",
			],
			sections: [
				{
					id: "cop-pricing-vs-otas",
					heading: "Transparent COP Quotes vs. 15–20% OTA Service Fees",
					paragraphs: [
						"External Online Travel Agencies typically add 15–20% in guest service fees and foreign-currency markups on top of the host's Nightly Rate. With an OceanViewFlats Direct Reservation, you pay the exact seasonal Nightly Rate for each Night plus a single flat Cleaning Fee—settled transparently in Colombian Pesos (COP), our sole authoritative Settlement Currency.",
						"Rates dynamically reflect our published Seasonal Rate Tiers: Property 1707 starts at {rate1707Cop} per night ({minStay1707}-night minimum) with a {cleaning1707Cop} Cleaning Fee, and Property 1606 starts at {rate1606Cop} per night (up to {rate1606HighCop} in peak Seasonal Rate Tiers, {minStay1606}–{maxMinStay1606} nights minimum) with an {cleaning1606Cop} Cleaning Fee.",
					],
					comparisonTable: {
						caption:
							"Side-by-side comparison of an OceanViewFlats Direct Reservation versus an External OTA channel",
						headers: [
							"Reservation Factor",
							"OceanViewFlats Direct Reservation",
							"External OTA Channel",
						],
						rows: [
							[
								"Platform Service Commission",
								"0% OTA commission",
								"Typically 15–20% guest service fee",
							],
							[
								"Settlement Currency",
								"Colombian Pesos (COP) with zero currency markup",
								"Often converted with extra platform spread",
							],
							[
								"Property 1707 Nightly Rate & Cleaning Fee",
								"{rate1707Cop}/night (min. {minStay1707} nights) + {cleaning1707Cop}",
								"Base rate + OTA surcharge + Cleaning Fee",
							],
							[
								"Property 1606 Nightly Rate & Cleaning Fee",
								"{rate1606Cop}–{rate1606HighCop}/night ({minStay1606}–{maxMinStay1606} nights) + {cleaning1606Cop}",
								"Base rate + OTA surcharge + Cleaning Fee",
							],
							[
								"Payment Methods (Mercado Pago)",
								"Credit/Debit Card, Colombian PSE, or Cash Voucher (Efecty)",
								"Limited mainly to international cards",
							],
							[
								"Assigned Parking Space",
								"Included (Bay 95 for 1707, Bay 87 for 1606)",
								"Same building bay upon vehicle registration",
							],
						],
					},
				},
				{
					id: "payment-methods-and-holds",
					heading: "Mercado Pago Payment Methods & Calendar Hold Windows",
					paragraphs: [
						"All Direct Reservation payments are processed through Mercado Pago with idempotent double-charge protection—never via unverified wire requests.",
						"Depending on your chosen Payment Method, your Pending Reservation protects your dates automatically while payment settles:",
					],
					bullets: [
						"Credit or Debit Card (Instant Settlement): confirms immediately into a Confirmed Reservation and dispatches your Reservation Confirmation email with your Guest Registry link (30-minute Standard Hold Window during checkout).",
						"PSE Colombian Bank Transfer (Asynchronous Settlement): ideal for Colombian bank accounts; calendar dates stay reserved while PSE webhook confirmation clears.",
						"Cash Voucher via Efecty (Asynchronous Settlement): generates an authorized voucher payable in cash across Colombia within the 72-hour Voucher Hold Window.",
					],
					factCards: [
						{
							label: "Property 1707 RNT",
							value: "RNT #268781",
							detail: "17th floor • 2 BR / 4 Beds • Bay 95",
						},
						{
							label: "Property 1606 RNT",
							value: "RNT #270413",
							detail: "16th floor • 2 BR / 3 Beds • Bay 87",
						},
						{
							label: "Building Registration Fee",
							value: "{buildingRegistrationFeeCop}",
							detail: "Per person at reception on arrival",
						},
					],
				},
				{
					id: "rnt-guest-registry-and-privacy",
					heading:
						"Colombian Law 2068 Guest Registry, Law 1581 Privacy & Smart-Lock Access",
					paragraphs: [
						"Colombia regulates short-term accommodations strictly to protect Guests and residential communities. Both Properties are officially registered under the Registro Nacional de Turismo (RNT #268781 and RNT #270413) with Data Controller OceanViewFlats — Manuel Enrique Herrera.",
						"Under Colombian Law 2068 of 2020 (TRA for Colombian residents / SIRE for international travelers) and Edificio Salguero Sunset security rules, every Confirmed Reservation must complete our online Guest Registry before arrival—providing legal IDs, ages, Primary Guest contact details, and optional vehicle plate for Condominium Clearance Sync. Under Colombian Statutory Law 1581 of 2012, submission requires explicit Personal Data Authorization under our published Data Processing Policy, plus a mandatory Minor Guardian Attestation (Law 679 of 2001 / ESCNNA prevention) whenever registering any Guest under 18 years of age.",
						"Once the Guest Registry is submitted, OceanViewFlats automatically sends your Access Dispatch unlocking the private Guest Guide (separate from these public Destination Guides) and your temporal smart-lock Access Credential (0XXXXXX#) valid from 15:00 on your Check-in Date until 11:00 on your Check-out Date.",
					],
					callout: {
						label: "Arrival & House Rules Summary",
						text: "Check-in is 15:00 and check-out is 11:00. Maximum Occupancy is 6 Guests per Property, pets are not permitted, the building swimming pool closes Tuesdays for scheduled maintenance, and Edificio Salguero Sunset collects the statutory {buildingRegistrationFeeCop} per-person Building Registration Fee directly at front-desk reception.",
					},
				},
			],
			faqsTitle: "Frequently Asked Questions: Direct Reservations & Compliance",
			faqs: [
				{
					id: "faq-dr-1",
					question:
						"How can I verify that OceanViewFlats is a legally registered tourism operator in Colombia?",
					answer:
						"Both Properties maintain active Registro Nacional de Turismo (RNT) registrations with Colombia's Ministry of Commerce, Industry and Tourism: RNT #268781 for Property 1707 and RNT #270413 for Property 1606, under Data Controller OceanViewFlats — Manuel Enrique Herrera at Calle 26 # 2-80, Playa Salguero.",
					defaultOpen: true,
				},
				{
					id: "faq-dr-2",
					question:
						"What is included in a Direct Reservation Quote and what is paid at the building?",
					answer:
						"Your online Quote in Settlement Currency (COP) covers each Night's Nightly Rate (starting at {rate1707Cop} for 1707 and {rate1606Cop} for 1606) plus the flat Cleaning Fee ({cleaning1707Cop} for 1707, {cleaning1606Cop} for 1606) with 0% OTA fees. Separately, Edificio Salguero Sunset administration charges a mandatory {buildingRegistrationFeeCop} per-person Building Registration Fee at reception upon check-in.",
				},
				{
					id: "faq-dr-3",
					question:
						"Why must Guests complete the Guest Registry before receiving the smart-lock Access Credential?",
					answer:
						"Colombian Law 2068 (TRA/SIRE) and condominium security require verified identities for every staying Guest (and Minor Guardian Attestation for Guests under 18) prior to occupancy. Submitting the encrypted Guest Registry triggers Condominium Clearance Sync and unlocks your private Guest Guide and temporal Access Credential.",
				},
				{
					id: "faq-dr-4",
					question:
						"What are the check-in, check-out, pool maintenance, and Maximum Occupancy rules?",
					answer:
						"Check-in begins at 15:00 and check-out is by 11:00 via smart-lock self check-in. Standard Maximum Occupancy is 6 Guests per Property, pets are not allowed, and the condominium swimming pool closes every Tuesday for scheduled maintenance.",
				},
			],
			ctaTitle: "Reserve Directly in COP with Zero OTA Markup",
			ctaSubtitle:
				"Select your Property to view live availability, instant COP Quotes from {portfolioLowCop}, and Mercado Pago checkout.",
		},
		es: {
			seoTitle: "Guía de Reserva Directa en Santa Marta | OceanViewFlats",
			seoDescription:
				"Guía de Reserva Directa en Santa Marta: 0% comisión OTA en COP desde {portfolioLowCop}, Mercado Pago (Tarjeta/PSE/Efecty), RNT #268781/#270413 y Registro.",
			ogImageAlt:
				"Entrada principal de Edificio Salguero Sunset en Playa Salguero, Santa Marta para Reservas Directas verificadas de OceanViewFlats",
			heroImageAlt:
				"Fachada y acceso principal de Edificio Salguero Sunset en Calle 26 # 2-80, Playa Salguero, Santa Marta",
			breadcrumbLabel: "Guía de Reserva Directa (COP)",
			badge: "Reserva Directa y Cumplimiento",
			title:
				"Guía de Reserva Directa en Santa Marta: COP, RNT y Registro de Huéspedes",
			subtitle:
				"Cómo reservar directamente con 0% comisión OTA en Moneda de Liquidación (COP) vía Mercado Pago, verificar el RNT oficial y completar el Registro de Huéspedes.",
			cardSummary:
				"Descubre cómo las Reservas Directas en OceanViewFlats ahorran 15–20% frente a plataformas OTA con cumplimiento total de RNT, TRA/SIRE y Ley 1581.",
			keyTakeawaysTitle: "Claves de la Reserva Directa",
			keyTakeaways: [
				"0% comisión OTA: cada Cotización de Reserva Directa se liquida en COP sumando las Tarifas por Noche y una sola Tarifa de Limpieza fija ({cleaning1707Cop} en 1707, {cleaning1606Cop} en 1606).",
				"Paga por Mercado Pago con Tarjeta Crédito/Débito (Liquidación Instantánea, ventana de retención de 30 min), PSE (Liquidación Asíncrona) o Efecty (ventana de 72 horas).",
				"Ambas Propiedades cuentan con registro turístico oficial activo: RNT #268781 (Propiedad 1707) y RNT #270413 (Propiedad 1606), operadas por OceanViewFlats — Manuel Enrique Herrera.",
				"Check-in 15:00, check-out 11:00, Tarifa de Registro del Edificio de {buildingRegistrationFeeCop} por persona en recepción, y piscina cerrada los martes por mantenimiento.",
			],
			sections: [
				{
					id: "cop-pricing-vs-otas",
					heading:
						"Cotizaciones Transparentes en COP vs. 15–20% de Comisiones OTA",
					paragraphs: [
						"Las plataformas OTA externas suelen sumar entre 15% y 20% en comisiones de servicio y diferenciales cambiarios sobre la Tarifa por Noche. En una Reserva Directa con OceanViewFlats pagas únicamente la Tarifa por Noche vigente de cada noche más la Tarifa de Limpieza fija, en Pesos Colombianos (COP) como Moneda de Liquidación oficial.",
						"Las tarifas reflejan dinámicamente nuestros Niveles de Tarifa Estacional: Propiedad 1707 desde {rate1707Cop}/noche (mínimo {minStay1707} noches) más {cleaning1707Cop} de Limpieza, y Propiedad 1606 desde {rate1606Cop}/noche (hasta {rate1606HighCop} en temporada alta, mínimo {minStay1606}–{maxMinStay1606} noches) más {cleaning1606Cop} de Limpieza.",
					],
					comparisonTable: {
						caption:
							"Comparación entre Reserva Directa en OceanViewFlats y reserva a través de una OTA externa",
						headers: [
							"Aspecto",
							"Reserva Directa OceanViewFlats",
							"Canal OTA Externo",
						],
						rows: [
							[
								"Comisión de Plataforma",
								"0% comisión OTA",
								"Habitualmente 15–20% adicional para el huésped",
							],
							[
								"Moneda de Liquidación",
								"Pesos Colombianos (COP) sin sobrecosto cambiario",
								"Conversión con margen adicional de plataforma",
							],
							[
								"Propiedad 1707 (Noche + Limpieza)",
								"{rate1707Cop}/noche (mín. {minStay1707} noches) + {cleaning1707Cop}",
								"Tarifa base + recargo OTA + Limpieza",
							],
							[
								"Propiedad 1606 (Noche + Limpieza)",
								"{rate1606Cop}–{rate1606HighCop}/noche ({minStay1606}–{maxMinStay1606} noches) + {cleaning1606Cop}",
								"Tarifa base + recargo OTA + Limpieza",
							],
							[
								"Métodos de Pago (Mercado Pago)",
								"Tarjeta Crédito/Débito, PSE o Cupón en Efectivo (Efecty)",
								"Principalmente tarjetas internacionales",
							],
							[
								"Parqueadero Asignado",
								"Incluido (Bahía 95 en 1707, Bahía 87 en 1606)",
								"Misma bahía al registrar la placa",
							],
						],
					},
				},
				{
					id: "payment-methods-and-holds",
					heading: "Métodos de Pago Mercado Pago y Ventanas de Retención",
					paragraphs: [
						"Todos los pagos de Reserva Directa se procesan de forma segura a través de Mercado Pago con protección contra cobros duplicados.",
						"Según el Método de Pago elegido, tu Reserva Pendiente protege las fechas mientras se confirma la transacción:",
					],
					bullets: [
						"Tarjeta de Crédito o Débito (Liquidación Instantánea): confirma de inmediato tu Reserva Confirmada y envía tu correo de Confirmación de Reserva con el enlace al Registro de Huéspedes (ventana estándar de 30 minutos durante el checkout).",
						"Transferencia Bancaria PSE (Liquidación Asíncrona): ideal para cuentas bancarias en Colombia; bloquea las fechas mientras llega la confirmación del banco.",
						"Cupón en Efectivo vía Efecty (Liquidación Asíncrona): genera un comprobante pagadero en efectivo en Colombia dentro de la ventana de retención de 72 horas.",
					],
					factCards: [
						{
							label: "RNT Propiedad 1707",
							value: "RNT #268781",
							detail: "Piso 17 • 2 Hab. / 4 Camas • Bahía 95",
						},
						{
							label: "RNT Propiedad 1606",
							value: "RNT #270413",
							detail: "Piso 16 • 2 Hab. / 3 Camas • Bahía 87",
						},
						{
							label: "Registro del Edificio",
							value: "{buildingRegistrationFeeCop}",
							detail: "Por persona en recepción al llegar",
						},
					],
				},
				{
					id: "rnt-guest-registry-and-privacy",
					heading:
						"Registro de Huéspedes (Ley 2068), Privacidad (Ley 1581) y Acceso Inteligente",
					paragraphs: [
						"Colombia regula el alojamiento turístico para proteger a los Huéspedes y a la copropiedad. Ambas Propiedades poseen Registro Nacional de Turismo vigente (RNT #268781 y RNT #270413) bajo el Responsable del Tratamiento OceanViewFlats — Manuel Enrique Herrera.",
						"En cumplimiento de la Ley 2068 de 2020 (TRA para residentes en Colombia / SIRE para extranjeros) y las normas del Edificio Salguero Sunset, toda Reserva Confirmada debe completar el Registro de Huéspedes en línea antes de la llegada (documentos, edades, contacto del Huésped Principal y placa vehicular opcional). Conforme a la Ley 1581 de 2012, el formulario requiere Autorización de Datos Personales expresa bajo nuestra Política de Tratamiento de Datos y Declaración de Patria Potestad / Representación Legal (Ley 679 de 2001 / prevención ESCNNA) cuando viajan menores de 18 años.",
						"Al enviar el Registro de Huéspedes, recibes automáticamente el Despacho de Acceso que habilita la Guía de Huéspedes privada (distinta de estas Guías de Destino públicas) y tu Credencial de Acceso temporal (0XXXXXX#) para la cerradura inteligente desde las 15:00 hasta las 11:00 del check-out.",
					],
					callout: {
						label: "Resumen de Reglas de Llegada y Edificio",
						text: "Check-in desde las 15:00 y check-out hasta las 11:00. Ocupación Máxima de 6 Huéspedes por Propiedad, no se permiten mascotas, la piscina cierra los martes por mantenimiento y la administración recauda la Tarifa de Registro del Edificio de {buildingRegistrationFeeCop} por persona en recepción.",
					},
				},
			],
			faqsTitle: "Preguntas Frecuentes: Reserva Directa y Cumplimiento",
			faqs: [
				{
					id: "faq-dr-1",
					question:
						"¿Cómo verifico que OceanViewFlats opera legalmente con RNT en Colombia?",
					answer:
						"Ambas Propiedades cuentan con Registro Nacional de Turismo activo ante el Ministerio de Comercio, Industria y Turismo: RNT #268781 para la Propiedad 1707 y RNT #270413 para la Propiedad 1606, bajo OceanViewFlats — Manuel Enrique Herrera en Calle 26 # 2-80, Playa Salguero.",
					defaultOpen: true,
				},
				{
					id: "faq-dr-2",
					question:
						"¿Qué incluye la Cotización en línea y qué se paga directamente en el edificio?",
					answer:
						"La Cotización en COP incluye las Tarifas por Noche (desde {rate1707Cop} en 1707 y {rate1606Cop} en 1606) más la Tarifa de Limpieza fija ({cleaning1707Cop} en 1707, {cleaning1606Cop} en 1606), con 0% comisión OTA. Aparte, la administración del Edificio Salguero Sunset cobra la Tarifa de Registro del Edificio ({buildingRegistrationFeeCop} por persona) en recepción al ingresar.",
				},
				{
					id: "faq-dr-3",
					question:
						"¿Por qué debo completar el Registro de Huéspedes antes de recibir el código de entrada?",
					answer:
						"La Ley 2068 (TRA/SIRE) y la seguridad del condominio exigen registrar a cada Huésped (y la declaración de representante legal para menores de 18 años) antes del ingreso. Al enviarlo, se sincroniza la autorización en portería y se desbloquean tu Guía de Huéspedes privada y Credencial de Acceso temporal.",
				},
				{
					id: "faq-dr-4",
					question:
						"¿Cuáles son los horarios de check-in, check-out, piscina y Ocupación Máxima?",
					answer:
						"Check-in a partir de las 15:00 y check-out hasta las 11:00 mediante cerradura inteligente. La Ocupación Máxima estándar es de 6 Huéspedes por Propiedad, no se admiten mascotas y la piscina cierra los martes por mantenimiento.",
				},
			],
			ctaTitle: "Reserva Directo en COP sin Recargos OTA",
			ctaSubtitle:
				"Elige tu Propiedad para ver calendario real, Cotización inmediata en COP desde {portfolioLowCop} y pago seguro con Mercado Pago.",
		},
		fr: {
			seoTitle: "Guide de Réservation Directe Santa Marta | OceanViewFlats",
			seoDescription:
				"Réservation Directe à Santa Marta : 0 % commission OTA en COP dès {portfolioLowCop}, Mercado Pago (Carte/PSE/Efecty), RNT #268781/#270413 & Registre.",
			ogImageAlt:
				"Entrée principale de l'Edificio Salguero Sunset à Playa Salguero, Santa Marta pour les Réservations Directes OceanViewFlats",
			heroImageAlt:
				"Façade et entrée principale de l'Edificio Salguero Sunset à Calle 26 # 2-80, Playa Salguero, Santa Marta",
			breadcrumbLabel: "Guide de Réservation Directe (COP)",
			badge: "Réservation Directe & Conformité",
			title:
				"Guide de Réservation Directe à Santa Marta : COP, RNT et Registre des Voyageurs",
			subtitle:
				"Comment réserver en direct sans commission OTA en Devise de Règlement (COP) via Mercado Pago, vérifier les numéros RNT et compléter le Registre légal.",
			cardSummary:
				"Comprenez comment la Réservation Directe chez OceanViewFlats économise 15–20 % face aux OTA tout en respectant les lois colombiennes RNT, TRA/SIRE et Loi 1581.",
			keyTakeawaysTitle: "Essentiels de la Réservation Directe",
			keyTakeaways: [
				"0 % de commission OTA : chaque Devis se règle en Pesos Colombiens (COP) avec les Tarifs par Nuit et un forfait unique de Frais de Ménage ({cleaning1707Cop} pour 1707, {cleaning1606Cop} pour 1606).",
				"Paiement sécurisé via Mercado Pago : Carte bancaire (règlement instantané, retenue 30 min), virement colombien PSE ou Bon en espèces Efecty (fenêtre de 72 h).",
				"Nos deux Propriétés disposent d'un RNT officiel actif : RNT #268781 (Propriété 1707) et RNT #270413 (Propriété 1606), sous le Responsable de Traitement OceanViewFlats — Manuel Enrique Herrera.",
				"Arrivée 15h00, départ 11h00, Frais d'Enregistrement de l'Immeuble de {buildingRegistrationFeeCop} par personne à la réception, et piscine fermée le mardi pour entretien.",
			],
			sections: [
				{
					id: "cop-pricing-vs-otas",
					heading: "Devis Transparents en COP vs. 15–20 % de Frais OTA",
					paragraphs: [
						"Les plateformes OTA ajoutent généralement 15 à 20 % de frais de service et des marges de change au tarif de la nuit. En Réservation Directe chez OceanViewFlats, vous réglez exactement le Tarif par Nuit saisonnier plus les Frais de Ménage fixes en Pesos Colombiens (COP), notre seule Devise de Règlement officielle.",
						"Les tarifs reflètent nos Paliers Tarifaires Saisonniers publiés : Propriété 1707 dès {rate1707Cop}/nuit (min. {minStay1707} nuits) + {cleaning1707Cop} de Ménage, et Propriété 1606 dès {rate1606Cop}/nuit (jusqu'à {rate1606HighCop} en haute saison, min. {minStay1606}–{maxMinStay1606} nuits) + {cleaning1606Cop} de Ménage.",
					],
					comparisonTable: {
						caption:
							"Comparatif entre une Réservation Directe OceanViewFlats et une plateforme OTA externe",
						headers: [
							"Critère",
							"Réservation Directe OceanViewFlats",
							"Canal OTA Externe",
						],
						rows: [
							[
								"Commission de Plateforme",
								"0 % commission OTA",
								"Généralement 15–20 % de frais voyageur",
							],
							[
								"Devise de Règlement",
								"Pesos Colombiens (COP) sans marge additionnelle",
								"Conversion fréquente avec frais supplémentaires",
							],
							[
								"Propriété 1707 (Nuit + Ménage)",
								"{rate1707Cop}/nuit (min. {minStay1707} nuits) + {cleaning1707Cop}",
								"Tarif de base + commission OTA + Ménage",
							],
							[
								"Propriété 1606 (Nuit + Ménage)",
								"{rate1606Cop}–{rate1606HighCop}/nuit ({minStay1606}–{maxMinStay1606} nuits) + {cleaning1606Cop}",
								"Tarif de base + commission OTA + Ménage",
							],
							[
								"Moyens de Paiement (Mercado Pago)",
								"Carte bancaire, PSE colombien ou Bon en espèces (Efecty)",
								"Surtout cartes internationales",
							],
							[
								"Place de Parking Attribuée",
								"Incluse (Place 95 pour 1707, Place 87 pour 1606)",
								"Même place après déclaration du véhicule",
							],
						],
					},
				},
				{
					id: "payment-methods-and-holds",
					heading: "Paiement Mercado Pago et Fenêtres de Retenue",
					paragraphs: [
						"Tous les règlements passent par Mercado Pago avec protection idempotente contre les doubles débits.",
						"Selon le Moyen de Paiement choisi, votre Réservation en Attente bloque automatiquement vos dates :",
					],
					bullets: [
						"Carte de Crédit / Débit (Règlement Instantané) : confirmation immédiate et envoi de l'e-mail de Confirmation de Réservation avec le lien du Registre des Voyageurs (fenêtre standard de 30 minutes).",
						"Virement Bancaire PSE (Règlement Asynchrone) : adapté aux comptes colombiens ; vos dates restent bloquées jusqu'à confirmation bancaire.",
						"Bon en Espèces Efecty (Règlement Asynchrone) : génère un bon payable en espèces en Colombie dans la fenêtre de 72 heures.",
					],
					factCards: [
						{
							label: "RNT Propriété 1707",
							value: "RNT #268781",
							detail: "17e étage • 2 Ch. / 4 Lits • Place 95",
						},
						{
							label: "RNT Propriété 1606",
							value: "RNT #270413",
							detail: "16e étage • 2 Ch. / 3 Lits • Place 87",
						},
						{
							label: "Frais d'Immeuble",
							value: "{buildingRegistrationFeeCop}",
							detail: "Par personne à la réception à l'arrivée",
						},
					],
				},
				{
					id: "rnt-guest-registry-and-privacy",
					heading:
						"Registre Légal (Loi 2068), Protection des Données (Loi 1581) et Accès Connecté",
					paragraphs: [
						"La Colombie encadre strictement la location touristique. Nos deux Propriétés sont enregistrées au Registro Nacional de Turismo (RNT #268781 et RNT #270413) sous le Responsable de Traitement OceanViewFlats — Manuel Enrique Herrera.",
						"Conformément à la Loi 2068 de 2020 (TRA pour résidents / SIRE pour voyageurs internationaux) et au règlement de l'Edificio Salguero Sunset, chaque Réservation Confirmée doit compléter le Registre des Voyageurs en ligne avant l'arrivée. En vertu de la Loi 1581 de 2012, le formulaire requiert une Autorisation de Traitement des Données Personnelles explicite ainsi qu'une Attestation de Représentation Légale (Loi 679 / prévention ESCNNA) pour tout mineur de moins de 18 ans.",
						"Une fois le Registre validé, vous recevez l'Envoi d'Accès débloquant le Guide Voyageur privé (distinct de ces Guides de Destination publics) et votre Code d'Accès temporaire (0XXXXXX#) actif de 15h00 le jour d'arrivée à 11h00 le jour de départ.",
					],
					callout: {
						label: "Rappel Arrivée & Règlement",
						text: "Arrivée dès 15h00, départ avant 11h00. Capacité maximale de 6 Voyageurs par Propriété, animaux non admis, piscine fermée le mardi pour entretien, et Frais d'Enregistrement de l'Immeuble de {buildingRegistrationFeeCop} par personne à régler à la réception.",
					},
				},
			],
			faqsTitle: "Questions Fréquentes : Réservation Directe & Conformité",
			faqs: [
				{
					id: "faq-dr-1",
					question:
						"Comment vérifier l'enregistrement touristique légal (RNT) d'OceanViewFlats ?",
					answer:
						"Nos deux Propriétés disposent d'un RNT actif auprès du Ministère colombien du Commerce et du Tourisme : RNT #268781 (Propriété 1707) et RNT #270413 (Propriété 1606), sous OceanViewFlats — Manuel Enrique Herrera à Calle 26 # 2-80, Playa Salguero.",
					defaultOpen: true,
				},
				{
					id: "faq-dr-2",
					question:
						"Que comprend le Devis en ligne et que paie-t-on à la réception ?",
					answer:
						"Le Devis en COP inclut les Tarifs par Nuit (dès {rate1707Cop} pour 1707 et {rate1606Cop} pour 1606) plus les Frais de Ménage fixes ({cleaning1707Cop} pour 1707, {cleaning1606Cop} pour 1606), sans commission OTA. À l'arrivée, la copropriété perçoit {buildingRegistrationFeeCop} par personne pour les Frais d'Enregistrement de l'Immeuble.",
				},
				{
					id: "faq-dr-3",
					question:
						"Pourquoi remplir le Registre des Voyageurs avant de recevoir le code d'accès ?",
					answer:
						"La Loi colombienne 2068 (TRA/SIRE) et la sécurité de l'immeuble imposent l'identification préalable de chaque occupant (et l'attestation parentale pour les moins de 18 ans). Sa validation déclenche l'autorisation de réception et débloque votre Guide Voyageur privé et votre Code d'Accès temporaire.",
				},
				{
					id: "faq-dr-4",
					question:
						"Quelles sont les règles d'arrivée, de départ, de piscine et de capacité ?",
					answer:
						"Arrivée à partir de 15h00 et départ jusqu'à 11h00 par serrure connectée. Capacité maximale de 6 Voyageurs par Propriété, animaux non autorisés, et piscine fermée chaque mardi pour entretien.",
				},
			],
			ctaTitle: "Réservez en Direct en COP Sans Frais OTA",
			ctaSubtitle:
				"Choisissez votre Propriété pour consulter le calendrier en direct et votre Devis COP dès {portfolioLowCop}.",
		},
		it: {
			seoTitle: "Guida Prenotazione Diretta Santa Marta | OceanViewFlats",
			seoDescription:
				"Prenotazione Diretta a Santa Marta: 0% commissioni OTA in COP da {portfolioLowCop}, Mercado Pago (Carta/PSE/Efecty), RNT #268781/#270413 e Registro.",
			ogImageAlt:
				"Ingresso principale dell'Edificio Salguero Sunset a Playa Salguero, Santa Marta per Prenotazioni Dirette OceanViewFlats",
			heroImageAlt:
				"Ingresso principale dell'Edificio Salguero Sunset in Calle 26 # 2-80, Playa Salguero, Santa Marta",
			breadcrumbLabel: "Guida Prenotazione Diretta (COP)",
			badge: "Prenotazione Diretta & Conformità",
			title:
				"Guida alla Prenotazione Diretta a Santa Marta: COP, RNT e Registro Ospiti",
			subtitle:
				"Come prenotare direttamente con 0% commissioni OTA in Valuta di Regolamento (COP) via Mercado Pago, verificare l'RNT e completare il Registro Ospiti.",
			cardSummary:
				"Scopri come la Prenotazione Diretta con OceanViewFlats fa risparmiare il 15–20% rispetto alle OTA rispettando RNT, TRA/SIRE e Legge 1581.",
			keyTakeawaysTitle: "Punti Chiave della Prenotazione Diretta",
			keyTakeaways: [
				"0% commissioni OTA: ogni Preventivo si regola in Pesos Colombiani (COP) con Tariffe Notturne più una sola Tariffa di Pulizia fissa ({cleaning1707Cop} per 1707, {cleaning1606Cop} per 1606).",
				"Pagamento sicuro tramite Mercado Pago: Carta di Credito/Debito (conferma immediata, blocco 30 min), bonifico PSE o Voucher Contanti Efecty (finestra 72 ore).",
				"Entrambe le Proprietà hanno registrazione turistica RNT attiva: RNT #268781 (Proprietà 1707) e RNT #270413 (Proprietà 1606), gestite dal Titolare OceanViewFlats — Manuel Enrique Herrera.",
				"Check-in alle 15:00, check-out alle 11:00, Tassa di Registrazione dell'Edificio di {buildingRegistrationFeeCop} a persona in reception, piscina chiusa il martedì per manutenzione.",
			],
			sections: [
				{
					id: "cop-pricing-vs-otas",
					heading:
						"Preventivi Trasparenti in COP vs. 15–20% di Commissioni OTA",
					paragraphs: [
						"I portali OTA esterni applicano solitamente il 15–20% di commissioni di servizio e margini di cambio sulla Tariffa Notturna. Con una Prenotazione Diretta OceanViewFlats paghi esattamente la Tariffa Notturna stagionale più la Tariffa di Pulizia fissa in Pesos Colombiani (COP), unica Valuta di Regolamento ufficiale.",
						"Le tariffe riflettono dinamicamente le nostre Fasce Stagionali: Proprietà 1707 da {rate1707Cop}/notte (min. {minStay1707} notti) + {cleaning1707Cop} di Pulizia, e Proprietà 1606 da {rate1606Cop}/notte (fino a {rate1606HighCop} in alta stagione, min. {minStay1606}–{maxMinStay1606} notti) + {cleaning1606Cop} di Pulizia.",
					],
					comparisonTable: {
						caption:
							"Confronto tra Prenotazione Diretta OceanViewFlats e canale OTA esterno",
						headers: [
							"Elemento",
							"Prenotazione Diretta OceanViewFlats",
							"Canale OTA Esterno",
						],
						rows: [
							[
								"Commissione Piattaforma",
								"0% commissioni OTA",
								"Di norma 15–20% a carico dell'ospite",
							],
							[
								"Valuta di Regolamento",
								"Pesos Colombiani (COP) senza ricarichi di cambio",
								"Spesso convertita con spread extra",
							],
							[
								"Proprietà 1707 (Notte + Pulizia)",
								"{rate1707Cop}/notte (min. {minStay1707} notti) + {cleaning1707Cop}",
								"Tariffa base + ricarico OTA + Pulizia",
							],
							[
								"Proprietà 1606 (Notte + Pulizia)",
								"{rate1606Cop}–{rate1606HighCop}/notte ({minStay1606}–{maxMinStay1606} notti) + {cleaning1606Cop}",
								"Tariffa base + ricarico OTA + Pulizia",
							],
							[
								"Metodi di Pagamento (Mercado Pago)",
								"Carta, bonifico PSE colombiano o Voucher Contanti (Efecty)",
								"Prevalentemente carte internazionali",
							],
							[
								"Posto Auto Assegnato",
								"Incluso (Posto 95 per 1707, Posto 87 per 1606)",
								"Stesso posto previa registrazione targa",
							],
						],
					},
				},
				{
					id: "payment-methods-and-holds",
					heading: "Metodi Mercado Pago e Finestre di Blocco Calendario",
					paragraphs: [
						"Ogni pagamento diretto viene gestito da Mercado Pago con protezione idempotente contro doppi addebiti.",
						"In base al Metodo di Pagamento scelto, la Prenotazione in Sospeso tutela le date fino alla conferma:",
					],
					bullets: [
						"Carta di Credito o Debito (Regolamento Istantaneo): conferma immediata con invio della Conferma di Prenotazione e link al Registro Ospiti (finestra standard di 30 minuti).",
						"Bonifico PSE (Regolamento Asincrono): ideale per conti colombiani; le date restano riservate durante la conferma bancaria.",
						"Voucher Contanti Efecty (Regolamento Asincrono): genera un voucher pagabile in contanti in Colombia entro la finestra di 72 ore.",
					],
					factCards: [
						{
							label: "RNT Proprietà 1707",
							value: "RNT #268781",
							detail: "17° piano • 2 Camere / 4 Letti • Posto 95",
						},
						{
							label: "RNT Proprietà 1606",
							value: "RNT #270413",
							detail: "16° piano • 2 Camere / 3 Letti • Posto 87",
						},
						{
							label: "Tassa Edificio",
							value: "{buildingRegistrationFeeCop}",
							detail: "A persona in reception all'arrivo",
						},
					],
				},
				{
					id: "rnt-guest-registry-and-privacy",
					heading:
						"Registro Ospiti (Legge 2068), Privacy (Legge 1581) e Accesso Smart-Lock",
					paragraphs: [
						"La Colombia disciplina gli affitti turistici per la sicurezza degli Ospiti e dei condomini. Entrambe le Proprietà possiedono Registro Nacional de Turismo attivo (RNT #268781 e RNT #270413) con Titolare OceanViewFlats — Manuel Enrique Herrera.",
						"In base alla Legge 2068 del 2020 (TRA per residenti / SIRE per ospiti internazionali) e al regolamento dell'Edificio Salguero Sunset, ogni Prenotazione Confermata deve completare il Registro Ospiti online prima dell'arrivo. Ai sensi della Legge 1581 del 2012 è richiesta l'Autorizzazione al Trattamento dei Dati Personali esplicita e l'Attestazione del Tutore Legale (Legge 679 / prevenzione ESCNNA) per eventuali minori di 18 anni.",
						"Completato il Registro, ricevi l'Invio di Accesso che sblocca la Guida Ospiti privata (distinta da queste Guide di Destinazione pubbliche) e la tua Credenziale di Accesso temporanea (0XXXXXX#) valida dalle 15:00 del check-in alle 11:00 del check-out.",
					],
					callout: {
						label: "Riepilogo Arrivo e Regole",
						text: "Check-in dalle 15:00 e check-out entro le 11:00. Occupazione Massima di 6 Ospiti per Proprietà, animali non ammessi, piscina chiusa il martedì per manutenzione e Tassa di Registrazione dell'Edificio di {buildingRegistrationFeeCop} a persona pagabile in reception.",
					},
				},
			],
			faqsTitle: "Domande Frequenti: Prenotazione Diretta e Conformità",
			faqs: [
				{
					id: "faq-dr-1",
					question:
						"Come posso verificare la registrazione turistica ufficiale (RNT) di OceanViewFlats?",
					answer:
						"Entrambe le Proprietà sono iscritte al Registro Nacional de Turismo del Ministero del Commercio e Turismo colombiano: RNT #268781 (Proprietà 1707) e RNT #270413 (Proprietà 1606), sotto OceanViewFlats — Manuel Enrique Herrera in Calle 26 # 2-80, Playa Salguero.",
					defaultOpen: true,
				},
				{
					id: "faq-dr-2",
					question:
						"Cosa include il Preventivo online e cosa si paga in reception?",
					answer:
						"Il Preventivo in COP include le Tariffe Notturne (da {rate1707Cop} per 1707 e {rate1606Cop} per 1606) più la Tariffa di Pulizia fissa ({cleaning1707Cop} per 1707, {cleaning1606Cop} per 1606), con 0% commissioni OTA. Separatamente, il condominio riscuote {buildingRegistrationFeeCop} a persona come Tassa di Registrazione dell'Edificio all'arrivo.",
				},
				{
					id: "faq-dr-3",
					question:
						"Perché bisogna compilare il Registro Ospiti prima di ricevere il codice smart-lock?",
					answer:
						"La Legge 2068 (TRA/SIRE) e la sicurezza condominiale richiedono l'identificazione di tutti gli Ospiti (e l'attestazione per i minori di 18 anni) prima dell'accesso. L'invio crittografato sincronizza la portineria e sblocca Guida Ospiti privata e Credenziale di Accesso temporanea.",
				},
				{
					id: "faq-dr-4",
					question:
						"Quali sono gli orari di check-in, check-out, piscina e Occupazione Massima?",
					answer:
						"Check-in dalle 15:00 e check-out entro le 11:00 tramite smart-lock. Occupazione Massima standard di 6 Ospiti per Proprietà, animali non ammessi e piscina chiusa ogni martedì per manutenzione.",
				},
			],
			ctaTitle: "Prenota Direttamente in COP Senza Commissioni OTA",
			ctaSubtitle:
				"Scegli la tua Proprietà per date in tempo reale, preventivi COP da {portfolioLowCop} e checkout Mercado Pago.",
		},
		de: {
			seoTitle: "Direktbuchung in Santa Marta (COP & RNT) | OceanViewFlats",
			seoDescription:
				"Leitfaden zur Direktbuchung in Santa Marta: 0% OTA-Gebühren in COP ab {portfolioLowCop}, Mercado Pago (Karte/PSE/Efecty), RNT #268781/#270413 & TRA/SIRE.",
			ogImageAlt:
				"Haupteingang des Edificio Salguero Sunset in Playa Salguero, Santa Marta für geprüfte OceanViewFlats Direktbuchungen",
			heroImageAlt:
				"Eingang des Edificio Salguero Sunset in Calle 26 # 2-80, Playa Salguero, Santa Marta",
			breadcrumbLabel: "Leitfaden Direktbuchung (COP)",
			badge: "Direktbuchung & Rechtssicherheit",
			title:
				"Leitfaden zur Direktbuchung in Santa Marta: COP, RNT & Gästeregistrierung",
			subtitle:
				"So buchen Sie direkt mit 0% OTA-Provision in Abrechnungswährung COP über Mercado Pago, prüfen die RNT-Nummern und schließen die gesetzliche Gästeregistrierung ab.",
			cardSummary:
				"Erfahren Sie, wie Direktbuchungen bei OceanViewFlats 15–20% gegenüber OTA-Portalen sparen und zugleich RNT-, TRA/SIRE- und Datenschutz-Standards (Gesetz 1581) erfüllen.",
			keyTakeawaysTitle: "Das Wichtigste zur Direktbuchung",
			keyTakeaways: [
				"0% OTA-Provision: Jedes Angebot rechnet transparent in Kolumbianischen Pesos (COP) ab – Übernachtungsraten plus eine pauschale Reinigungsgebühr ({cleaning1707Cop} für 1707, {cleaning1606Cop} für 1606).",
				"Sichere Zahlung über Mercado Pago: Kredit-/Debitkarte (Sofortbestätigung, 30 Min. Haltefenster), kolumbianische PSE-Überweisung oder Efecty Bargeld-Gutschein (72 Std. Haltefenster).",
				"Beide Unterkünfte sind offiziell beim kolumbianischen Tourismusministerium registriert: RNT #268781 (Unterkunft 1707) und RNT #270413 (Unterkunft 1606) unter OceanViewFlats — Manuel Enrique Herrera.",
				"Check-in 15:00, Check-out 11:00, einmalige Gebäude-Registrierungsgebühr von {buildingRegistrationFeeCop} pro Person an der Rezeption, Pool dienstags wegen Wartung geschlossen.",
			],
			sections: [
				{
					id: "cop-pricing-vs-otas",
					heading: "Transparente COP-Angebote vs. 15–20% OTA-Servicegebühren",
					paragraphs: [
						"Externe Buchungsportale schlagen meist 15–20% Servicegebühren und Wechselkursaufschläge auf die Übernachtungsrate auf. Bei einer Direktbuchung über OceanViewFlats zahlen Sie exakt die gültige Saison-Nachtrate plus pauschale Reinigungsgebühr in Kolumbianischen Pesos (COP).",
						"Die Preise stammen direkt aus unseren veröffentlichten Saisontarifen: Unterkunft 1707 ab {rate1707Cop}/Nacht (mind. {minStay1707} Nächte) + {cleaning1707Cop} Reinigung sowie Unterkunft 1606 ab {rate1606Cop}/Nacht (bis {rate1606HighCop} in der Hochsaison, mind. {minStay1606}–{maxMinStay1606} Nächte) + {cleaning1606Cop} Reinigung.",
					],
					comparisonTable: {
						caption:
							"Vergleich zwischen OceanViewFlats Direktbuchung und externem OTA-Portal",
						headers: [
							"Kriterium",
							"OceanViewFlats Direktbuchung",
							"Externes OTA-Portal",
						],
						rows: [
							[
								"Plattform-Provision",
								"0% OTA-Provision",
								"Meist 15–20% Gäste-Servicegebühr",
							],
							[
								"Abrechnungswährung",
								"Kolumbianische Pesos (COP) ohne Aufschlag",
								"Häufig Umrechnung mit Zusatzmarge",
							],
							[
								"Unterkunft 1707 (Nacht + Reinigung)",
								"{rate1707Cop}/Nacht (mind. {minStay1707} Nächte) + {cleaning1707Cop}",
								"Basisrate + OTA-Aufschlag + Reinigung",
							],
							[
								"Unterkunft 1606 (Nacht + Reinigung)",
								"{rate1606Cop}–{rate1606HighCop}/Nacht ({minStay1606}–{maxMinStay1606} Nächte) + {cleaning1606Cop}",
								"Basisrate + OTA-Aufschlag + Reinigung",
							],
							[
								"Zahlungsmethoden (Mercado Pago)",
								"Karte, kolumbianisches PSE oder Bargeld-Gutschein (Efecty)",
								"Überwiegend internationale Karten",
							],
							[
								"Fester Stellplatz",
								"Inklusive (Platz 95 für 1707, Platz 87 für 1606)",
								"Gleicher Platz nach Kennzeichen-Registrierung",
							],
						],
					},
				},
				{
					id: "payment-methods-and-holds",
					heading: "Mercado Pago Zahlungsmethoden & Kalender-Haltefenster",
					paragraphs: [
						"Alle Direktzahlungen laufen über Mercado Pago mit Idempotenz-Schutz gegen Doppelabbuchungen.",
						"Je nach gewählter Zahlungsmethode bleiben Ihre Daten automatisch reserviert:",
					],
					bullets: [
						"Kredit-/Debitkarte (Sofortige Abwicklung): unmittelbare Buchungsbestätigung samt E-Mail-Link zur Gästeregistrierung (30-Minuten-Standardfenster).",
						"PSE-Banküberweisung (Asynchrone Abwicklung): ideal für kolumbianische Konten; Termine bleiben bis zur Bankbestätigung gesperrt.",
						"Bargeld-Gutschein via Efecty (Asynchrone Abwicklung): zahlbar in bar in Kolumbien innerhalb des 72-Stunden-Fensters.",
					],
					factCards: [
						{
							label: "RNT Unterkunft 1707",
							value: "RNT #268781",
							detail: "17. Stock • 2 SZ / 4 Betten • Platz 95",
						},
						{
							label: "RNT Unterkunft 1606",
							value: "RNT #270413",
							detail: "16. Stock • 2 SZ / 3 Betten • Platz 87",
						},
						{
							label: "Gebäudegebühr",
							value: "{buildingRegistrationFeeCop}",
							detail: "Pro Person bei Ankunft an der Rezeption",
						},
					],
				},
				{
					id: "rnt-guest-registry-and-privacy",
					heading:
						"Gästeregistrierung (Gesetz 2068), Datenschutz (Gesetz 1581) & Smart-Lock",
					paragraphs: [
						"Kolumbien reguliert Kurzzeitvermietungen streng zum Schutz von Gästen und Wohngemeinschaften. Beide Unterkünfte tragen aktive Tourismusregistrierungen (RNT #268781 und RNT #270413) unter dem Datenverantwortlichen OceanViewFlats — Manuel Enrique Herrera.",
						"Gemäß Gesetz 2068 von 2020 (TRA für Einheimische / SIRE für internationale Gäste) und der Hausordnung des Edificio Salguero Sunset muss jede bestätigte Reservierung vor Anreise unsere verschlüsselte Online-Gästeregistrierung abschließen. Nach Gesetz 1581 von 2012 erfordert dies eine ausdrückliche Einwilligung zur Datenverarbeitung sowie bei Gästen unter 18 Jahren eine gesetzliche Erziehungsberechtigten-Erklärung (Gesetz 679 / ESCNNA-Prävention).",
						"Nach Abschluss erhalten Sie automatisch Ihren privaten Gästeleitfaden (getrennt von diesen öffentlichen Reiseführern) und Ihren zeitlich begrenzten Smart-Lock-Zugangscode (0XXXXXX#), gültig ab 15:00 am Anreisetag bis 11:00 am Abreisetag.",
					],
					callout: {
						label: "Anreise & Hausregeln kompakt",
						text: "Check-in ab 15:00, Check-out bis 11:00. Maximalbelegung 6 Gäste pro Unterkunft, keine Haustiere, Pool dienstags wegen Wartung geschlossen, und einmalige Gebäude-Registrierungsgebühr von {buildingRegistrationFeeCop} pro Person direkt an der Rezeption.",
					},
				},
			],
			faqsTitle: "Häufige Fragen: Direktbuchung & Vorschriften",
			faqs: [
				{
					id: "faq-dr-1",
					question:
						"Wie kann ich die offizielle kolumbianische RNT-Registrierung von OceanViewFlats prüfen?",
					answer:
						"Beide Unterkünfte führen aktive Einträge im Registro Nacional de Turismo (RNT): RNT #268781 für Unterkunft 1707 und RNT #270413 für Unterkunft 1606, unter OceanViewFlats — Manuel Enrique Herrera, Calle 26 # 2-80, Playa Salguero.",
					defaultOpen: true,
				},
				{
					id: "faq-dr-2",
					question:
						"Was ist im Online-Angebot enthalten und was wird vor Ort im Gebäude bezahlt?",
					answer:
						"Ihr COP-Angebot umfasst die Übernachtungsraten (ab {rate1707Cop} für 1707 bzw. {rate1606Cop} für 1606) plus die pauschale Reinigungsgebühr ({cleaning1707Cop} für 1707, {cleaning1606Cop} für 1606) bei 0% OTA-Provision. Separat erhebt die Hausverwaltung des Edificio Salguero Sunset {buildingRegistrationFeeCop} pro Person als Gebäude-Registrierungsgebühr an der Rezeption.",
				},
				{
					id: "faq-dr-3",
					question:
						"Warum ist die Gästeregistrierung vor Erhalt des Smart-Lock-Codes erforderlich?",
					answer:
						"Gesetz 2068 (TRA/SIRE) und die Gebäudesicherheit verlangen die vorherige Identifikation aller Gäste (sowie die Bestätigung für Minderjährige unter 18). Erst danach erfolgt die Rezeptionsfreigabe und der Versand Ihres privaten Gästeleitfadens samt Zugangscode.",
				},
				{
					id: "faq-dr-4",
					question:
						"Wie lauten die Regeln zu Check-in, Check-out, Pool und Maximalbelegung?",
					answer:
						"Check-in ab 15:00 und Check-out bis 11:00 per Smart-Lock. Standard-Maximalbelegung sind 6 Gäste pro Unterkunft, Haustiere sind nicht gestattet und der Pool bleibt dienstags wegen Wartung geschlossen.",
				},
			],
			ctaTitle: "Direkt in COP ohne OTA-Aufschlag reservieren",
			ctaSubtitle:
				"Wählen Sie Ihre Unterkunft für Live-Verfügbarkeit, COP-Angebote ab {portfolioLowCop} und sicheren Mercado-Pago-Checkout.",
		},
		ja: {
			seoTitle: "サンタマルタCOP直接予約・RNT完全ガイド | OceanViewFlats",
			seoDescription:
				"サンタマルタの直接予約ガイド：OTA手数料0%のCOP公式見積もり（{portfolioLowCop}〜）、Mercado Pago（カード/PSE/Efecty）、RNT #268781/#270413、法定ゲスト登録を解説。",
			ogImageAlt:
				"サンタマルタ・プラヤ・サルグエロのエディフィシオ・サルグエロ・サンセット正面玄関（OceanViewFlats直接予約）",
			heroImageAlt:
				"プラヤ・サルグエロCalle 26 # 2-80のエディフィシオ・サルグエロ・サンセット正面入口",
			breadcrumbLabel: "COP直接予約・RNTガイド",
			badge: "直接予約＆法令遵守",
			title:
				"サンタマルタ直接予約完全ガイド：COP見積もり・RNT確認・法定ゲスト登録",
			subtitle:
				"OTA手数料0%のコロンビアペソ（COP）直接予約、Mercado Pago決済、RNT公式登録、法律2068号ゲスト登録（TRA/SIRE）と入室手順を分かりやすく解説します。",
			cardSummary:
				"外部OTAの15〜20%手数料を節約しつつ、RNT・TRA/SIRE・法律1581号個人情報保護に完全準拠した直接予約の仕組みをご案内します。",
			keyTakeawaysTitle: "直接予約の重要ポイント",
			keyTakeaways: [
				"OTA手数料0%：公式見積もりはすべて決済通貨COPで算出し、宿泊料金＋一律清掃料金（物件1707：{cleaning1707Cop}、物件1606：{cleaning1606Cop}）のみで構成されます。",
				"Mercado Pago決済に対応：クレジットカード／デビットカード（即時確定・30分仮押さえ）、コロンビアPSE銀行振込、Efecty現金バウチャー（72時間仮押さえ）を選択できます。",
				"両物件ともコロンビア商工観光省の公式観光登録を取得済み：RNT #268781（物件1707）・RNT #270413（物件1606）、データ管理者OceanViewFlats — Manuel Enrique Herrera。",
				"チェックイン15:00・チェックアウト11:00、到着時にフロントで建物登録料（{buildingRegistrationFeeCop}／名）をお支払い。プールは毎週火曜メンテナンス休業です。",
			],
			sections: [
				{
					id: "cop-pricing-vs-otas",
					heading: "透明なCOP公式見積もり vs 外部OTAの15〜20%手数料",
					paragraphs: [
						"外部OTA予約サイトでは通常、宿泊料金に加えて15〜20%のゲストサービス手数料や為替上乗せが発生します。OceanViewFlatsの直接予約では、各日程の季節料金と一律清掃料金のみを公式決済通貨であるコロンビアペソ（COP）で透明にお支払いいただけます。",
						"料金は公開中の季節料金カレンダーに連動しています：物件1707は{rate1707Cop}/泊〜（最低{minStay1707}泊）＋清掃料金{cleaning1707Cop}、物件1606は{rate1606Cop}/泊〜（ハイシーズン{rate1606HighCop}、最低{minStay1606}〜{maxMinStay1606}泊）＋清掃料金{cleaning1606Cop}です。",
					],
					comparisonTable: {
						caption: "OceanViewFlats直接予約と外部OTA予約の比較",
						headers: ["比較項目", "OceanViewFlats直接予約", "外部OTAチャネル"],
						rows: [
							[
								"プラットフォーム手数料",
								"OTA手数料 0%",
								"通常15〜20%のサービス手数料",
							],
							[
								"決済通貨",
								"コロンビアペソ（COP）・為替上乗せなし",
								"プラットフォーム換算スプレッドが発生する場合あり",
							],
							[
								"物件1707（宿泊＋清掃）",
								"{rate1707Cop}/泊（最低{minStay1707}泊）＋{cleaning1707Cop}",
								"基本料金＋OTA手数料＋清掃料金",
							],
							[
								"物件1606（宿泊＋清掃）",
								"{rate1606Cop}〜{rate1606HighCop}/泊（{minStay1606}〜{maxMinStay1606}泊）＋{cleaning1606Cop}",
								"基本料金＋OTA手数料＋清掃料金",
							],
							[
								"決済手段（Mercado Pago）",
								"カード・コロンビアPSE・現金バウチャー（Efecty）",
								"主に国際カード限定",
							],
							[
								"専用駐車スペース",
								"無料付属（1707は95番・1606は87番）",
								"車両登録により同一区画を利用",
							],
						],
					},
				},
				{
					id: "payment-methods-and-holds",
					heading: "Mercado Pago決済手段とカレンダー仮押さえ期間",
					paragraphs: [
						"直接予約の決済はすべて二重課金防止（冪等キー）を備えたMercado Pago経由で安全に処理されます。",
						"選択した決済手段に応じて、決済完了までカレンダー日程が自動保護されます。",
					],
					bullets: [
						"クレジットカード／デビットカード（即時決済）：決済完了と同時に予約確定となり、ゲスト登録リンク付きの予約確定メールを送信します（手続き中の標準仮押さえ30分）。",
						"PSE銀行振込（非同期決済）：コロンビアの銀行口座向け。銀行側の決済通知完了まで日程を保持します。",
						"Efecty現金バウチャー（非同期決済）：コロンビア国内の取扱店で現金支払い可能なバウチャーを発行し、72時間日程を保持します。",
					],
					factCards: [
						{
							label: "物件1707 RNT",
							value: "RNT #268781",
							detail: "17階・2BR/4ベッド・駐車95番",
						},
						{
							label: "物件1606 RNT",
							value: "RNT #270413",
							detail: "16階・2BR/3ベッド・駐車87番",
						},
						{
							label: "建物登録料",
							value: "{buildingRegistrationFeeCop}",
							detail: "到着時フロント払い（1名あたり）",
						},
					],
				},
				{
					id: "rnt-guest-registry-and-privacy",
					heading:
						"法律2068号ゲスト登録（TRA/SIRE）・法律1581号個人情報保護・スマートロック入室",
					paragraphs: [
						"コロンビアでは宿泊者とマンション居住者の安全を守るため短期滞在が厳格に法制化されています。両物件は公式の観光登録（RNT #268781・RNT #270413）を維持し、データ管理者OceanViewFlats — Manuel Enrique Herreraが運営しています。",
						"法律2068号（国内居住者向けTRA／外国人旅行者向けSIRE）および建物規約に基づき、予約確定後はご到着前にオンラインのゲスト登録（全員の身分証番号・年齢・代表者連絡先・任意で車両ナンバー）を完了する必要があります。法律1581号に基づき、送信時にはデータ処理ポリシーへの明示的な個人データ利用同意、また18歳未満の宿泊者がいる場合は保護者・法定代理人宣誓（法律679号／ESCNNA防止）が必須となります。",
						"ゲスト登録の送信が完了すると、建物フロントへの入館連携が実行され、非公開ゲストガイド（本ページの公開現地ガイドとは別）およびチェックイン15:00〜チェックアウト11:00まで有効な一時スマートロック暗証番号（0XXXXXX#）が自動送付されます。",
					],
					callout: {
						label: "到着・ハウスルールまとめ",
						text: "チェックイン15:00・チェックアウト11:00。最大定員は各物件6名様、ペット不可、プールは毎週火曜メンテナンス休業。到着時にエディフィシオ・サルグエロ・サンセットのフロントにて建物登録料（{buildingRegistrationFeeCop}／名）のお支払いが必要です。",
					},
				},
			],
			faqsTitle: "よくある質問：直接予約・RNT・ゲスト登録",
			faqs: [
				{
					id: "faq-dr-1",
					question:
						"OceanViewFlatsがコロンビアの正規RNT登録事業者であることを確認できますか？",
					answer:
						"はい。両物件はコロンビア商工観光省のRegistro Nacional de Turismo（RNT）に登録されています：物件1707はRNT #268781、物件1606はRNT #270413（運営者：OceanViewFlats — Manuel Enrique Herrera、Calle 26 # 2-80, Playa Salguero）。",
					defaultOpen: true,
				},
				{
					id: "faq-dr-2",
					question:
						"オンライン見積もりに含まれる費用と現地フロント払いの費用は？",
					answer:
						"COPオンライン見積もりには宿泊料金（物件1707：{rate1707Cop}〜、物件1606：{rate1606Cop}〜）と一律清掃料金（1707：{cleaning1707Cop}、1606：{cleaning1606Cop}）のみが含まれ、OTA手数料は0%です。別途、チェックイン時に建物管理組合へ建物登録料（{buildingRegistrationFeeCop}／名）をフロントでお支払いいただきます。",
				},
				{
					id: "faq-dr-3",
					question:
						"入室用スマートロックコードを受け取る前にゲスト登録が必要な理由は？",
					answer:
						"コロンビア法律2068号（TRA/SIRE）および建物セキュリティ規定により、ご入室前に全宿泊者の本人確認（18歳未満は法定代理人宣誓）が義務付けられています。暗号化されたゲスト登録の送信によりフロント承認と非公開ゲストガイド・一時入室コードが解放されます。",
				},
				{
					id: "faq-dr-4",
					question:
						"チェックイン・チェックアウト・プール休業日・最大定員の規定は？",
					answer:
						"チェックインは15:00から、チェックアウトは11:00まで（スマートロック自己チェックイン）。標準最大定員は各物件6名様、ペット不可、プールは毎週火曜日がメンテナンス休業です。",
				},
			],
			ctaTitle: "OTA手数料0%でCOP直接予約を確定",
			ctaSubtitle:
				"物件を選んで最新空室状況と{portfolioLowCop}〜のCOP公式見積もりをご確認ください。",
		},
	},
};
