# OceanViewFlats

Direct booking platform and guest management system for premium beachfront short-term rental accommodations in Santa Marta, Colombia.

## Language

### Accommodations

**Property**:
A specific physical residential apartment managed by OceanViewFlats and offered for short-term rental. Display names in guest-facing interfaces may be localized to match the target market (e.g., "Apartment", "Apartamento"), but Property remains the canonical domain entity.
_Avoid_: Flat, Apartment, Unit, Condo, Listing

**Assigned Parking Space**:
A designated, numbered vehicular bay within Edificio Salguero Sunset deeded and allocated to a specific Property (Bay 87 for Property 1606, Bay 95 for Property 1707).
_Avoid_: Parking Spot, Garage, Car Stall, Driveway

### Stay Duration

**Check-in Date**:
The calendar date on which Guests are authorized to occupy the Property.
_Avoid_: Arrival Date, Start Date

**Check-out Date**:
The calendar date by which all Guests must vacate the Property.
_Avoid_: Departure Date, End Date

**Night**:
The atomic unit of duration and pricing for a Reservation, spanning from afternoon arrival on a date to morning departure the following day. Every Reservation requires a minimum duration of one Night.
_Avoid_: Day, Stay Unit

### Pricing & Currency

**Nightly Rate**:
The base monetary charge for a single Night at a specific Property, dynamically resolved from the seasonal pricing calendar.
_Avoid_: Daily Price, Base Fee, Night Cost

**Quote**:
The calculated, authoritative monetary assessment for a specific Reservation, consisting of the itemized aggregation of each Night's Nightly Rate, plus the mandatory Cleaning Fee and Resort Fee, settling in Settlement Currency (COP).
_Avoid_: Estimate, Bill, Total Price, Cost

**Cleaning Fee**:
A mandatory, flat per-reservation surcharge assessed on every Direct Reservation to cover property preparation and sanitation between Guest stays, established per Property.
_Avoid_: Sanitation Charge, Maid Fee, Turnover Fee

**Resort Fee**:
A mandatory, flat per-reservation administrative and amenities surcharge assessed on every Direct Reservation for building and common-area facilities maintenance.
_Avoid_: Community Fee, Building Fee, Service Surcharge

**Building Registration Fee**:
The mandatory statutory fee of 20,000 COP per person assessed by the Edificio Salguero Sunset condominium administration for guest registration and common area amenities access, payable directly at the front-desk reception upon arrival.
_Avoid_: Resort Fee (reserved strictly for direct booking platform surcharge), Common Area Surcharge, Reception Tax, Porter Fee

**Settlement Currency**:
The authoritative currency for all pricing, quotations, and financial transactions, which is Colombian Pesos (COP). Any foreign currencies presented in interfaces are non-authoritative reference conversions.
_Avoid_: Base Currency, Currency Code, FX

**Seasonal Rate Tier**:
A contiguous calendar date window assigned a customized Nightly Rate and minimum stay requirement for a specific Property, taking precedence over default baseline pricing.
_Avoid_: Price Tier, Custom Rate, Rate Rule, Season Block, Pricing Period

### Reservations

**Reservation**:
A time-bounded contractual commitment securing a specific Property for an agreed date range between a Check-in Date and a Check-out Date.
_Avoid_: Booking, Booking Request, Order, Rental

**Direct Reservation**:
A Reservation originated and transacted directly through OceanViewFlats, maintaining full Guest identification, payment records, and access fulfillment workflows.
_Avoid_: Internal Booking, Native Reservation, Website Booking, Direct Booking

**Manual Reservation**:
A Reservation originated directly by an administrator without an external payment gateway (e.g. phone inquiry, external bank wire, or owner occupancy), attributed to an administrative payment source and confirmed immediately to secure calendar dates. Identified by a unique `res-man-*` reservation UID, which functions as an authoritative lookup token across both the Guest Registry and Guest Guide. Distinct from statutory *Guest Registration* / *Guest Registry*, which refers solely to Colombian police registration compliance (SIRE/TRA) and building registration fees.
_Avoid_: Offline Booking, Phone Order, Admin Reservation, Walk-in, Manual Registration

**Manual Reservation Validation & State Preservation Guarantees**:
* **Reactive Submit Lock**: The creation modal submit button is locked (`disabled`, `aria-disabled="true"`, `opacity-50 cursor-not-allowed`) by default and unlocks only when all client validations pass and dates are confirmed available. Dynamic helper messaging surfaces specific unmet requirements in real time.
* **Asynchronous Ledger Availability Gating**: Changing dates or property triggers an asynchronous quote preview that queries `ReservationLedger::getConflictReasons()`, signaling availability via declarative DOM attributes (`data-available="true|false"`) and `availabilityChecked` custom events.
* **Date Range Auto-Synchronization**: Check-out date is dynamically constrained to a minimum of `check_in + 1 day`, auto-advancing if check-in is selected on or after the current check-out.
* **Source-Dependent Validation Matrix**: Airbnb reservations require an external confirmation code (leaving email/phone optional); direct stays (`bank_transfer`, `cash`, `owner_stay`, `manual_override`) require guest name, valid email, and 7–25 character phone.
* **In-Place HTTP 422 State Preservation**: If submission fails backend validation or encounters a concurrent ledger conflict, the server returns HTTP 422 with itemized conflict reasons, preserving 100% of operator input across all form fields.


**External Reservation**:
A Reservation originating from an external Online Travel Agency (e.g. Airbnb) where financial settlement occurs out-of-band on the external platform, but guest identity, legal Guest Registry completion, and Access Credentials fulfillment are managed within OceanViewFlats. Identified by a unique `res-abnb-*` reservation UID and linked to an External Confirmation Code and optional Channel Block UID.
_Avoid_: External Booking, OTA Hold, Airbnb Order


**Pending Reservation**:
A temporary hold on a Property's calendar created during checkout that preserves dates while awaiting payment confirmation, expiring automatically after either the Standard Hold Window or Voucher Hold Window depending on the selected Payment Method.
_Avoid_: Unpaid Booking, Temporary Hold, Cart

**Standard Hold Window**:
The configurable duration (base standard of 30 minutes, overridable by environment configuration) during which interactive digital checkouts (Card, PSE) hold calendar dates before expiring.
_Avoid_: Timeout, Lock Duration, TTL

**Voucher Hold Window**:
The extended 72-hour duration during which a Pending Reservation holding an unredeemed Cash Voucher reserves calendar dates before expiring automatically.
_Avoid_: Long Hold, Extended Grace Period, Ticket Expiration

**Confirmed Reservation**:
A Reservation backed by verified payment that definitively locks the Property's calendar for the reserved dates.
_Avoid_: Paid Booking, Finalized Reservation

**Cancelled Reservation**:
A previously held or confirmed Reservation that has been voided, releasing the dates back to general availability.
_Avoid_: Void Booking, Expired Hold

**Concluded Reservation**:
A Confirmed Reservation whose stay duration has completed past 23:59:59 COT on the Check-out Date. Concluded Reservations permanently invalidate and suppress public Guest Guide credentials and Guest Registry access, redacting all personal guest identity and stay details to prevent data information leaks.
_Avoid_: Ended Reservation, Expired Reservation, Finished Booking, Past Booking

**Resurrection Defense**:
The architectural invariant preventing delayed asynchronous payment webhooks, out-of-order gateway notifications, or duplicate submissions from reactivating, confirming, or altering a Cancelled Reservation or Concluded Reservation.
_Avoid_: Zombie Prevention, Late Webhook Lock, Status Guard

**Cancellation Dry-Run**:
A side-effect-free simulation and financial calculation of a Reservation's Refundable Balance, elapsed days to Check-in Date, and applicable Policy Retention, evaluated prior to executing a cancellation.
_Avoid_: Refund Preview, Cancellation Estimation, Refund Quote

**Primary Guest**:
The individual who initiates and pays for the Reservation, holds legal and financial responsibility for the stay, and is contractually required to be one of the staying occupants.
_Avoid_: Booker, Lead Guest, Main Guest, Customer, Client

**Guest**:
Any individual authorized to occupy the Property during a confirmed Reservation, including the Primary Guest and accompanying registered companions.
_Avoid_: Occupant, Companion, Visitor, Tenant

**Maximum Occupancy**:
The standard baseline capacity of 6 Guests permitted for a Property on a Reservation without administrative override.
_Avoid_: Guest Cap, Max Headcount, Platform Limit, Property Limit

**Occupancy Override**:
An administrative authorization on a Reservation expanding its permitted Guest capacity beyond the standard Maximum Occupancy, allowing either the Primary Guest via the Guest Registry or an Admin User via the Admin Console to register additional Guests.
_Avoid_: Limit Bypass, Extra Guest Exception, Over-capacity Flag

### Payments & Settlement

**Payment Method**:
The financial transaction rail chosen by the Primary Guest during checkout (Credit/Debit Card, PSE bank transfer, or Cash Voucher).
_Avoid_: Payment Type, Gateway Option, Payment Mode

**Instant Settlement**:
Synchronous payment authorization and capture resulting in immediate transition of a Pending Reservation to Confirmed Reservation.
_Avoid_: Direct Pay, Immediate Capture

**Asynchronous Settlement**:
An out-of-band payment workflow (PSE bank transfer or Cash Voucher) where payment confirmation arrives asynchronously via webhook callback.
_Avoid_: Delayed Payment, Offline Order

**Cash Voucher**:
An offline payment instrument (e.g., Efecty) that generates a physical voucher payable at authorized retail locations within the Voucher Hold Window.
_Avoid_: Ticket, Slip, Cash Receipt

**Idempotency Key**:
A unique client-generated token ensuring a payment transaction is processed exactly once, protecting against double-charges during network retries or duplicate submissions.
_Avoid_: Nonce, Transaction Token, Request Hash

**Gateway Refund**:
An automated monetary reversal executed against the payment gateway (Mercado Pago) returning funds to the Primary Guest's original payment rail.
_Avoid_: Charge reversal, payback, return

**Refundable Balance**:
The remaining net monetary balance of a Reservation eligible for refund, defined strictly as Total Price minus cumulative Refunded Amount.
_Avoid_: Available balance, remaining charge, credit

**Policy Retention**:
The portion of the Quote withheld upon cancellation according to cancellation policy terms rather than refunded.
_Avoid_: Cancellation penalty, forfeit, withheld fee

**External Confirmation Code**:
The authoritative reservation reference or confirmation token assigned to a stay by an external booking platform (e.g., Airbnb confirmation code `HMXXXXXXXX`), recorded on an External Reservation for operator cross-referencing and guest lookup.
_Avoid_: Airbnb Code, Partner ID, Booking Reference

### Channel Synchronization

**Channel Sync**:
The automated or administratively triggered bidirectional exchange of calendar availability between OceanViewFlats and external booking channels using the iCalendar protocol to prevent dual-booking conflicts.
_Avoid_: Calendar Sync, iCal Integration, Availability Mirror

**Channel Block**:
A period of calendar unavailability imported from an external Online Travel Agency (e.g., Airbnb) via calendar sync. Channel Blocks do not create Reservation records or Guest identities automatically, but may be onboarded into an External Reservation by an Admin User, which absorbs the Channel Block on the Reservation Ledger.
_Avoid_: External Reservation, Airbnb Booking, OTA Booking, Blackout Date

**Channel Block UID**:
The unique identifier of an iCalendar event imported from an external channel feed, recorded on an External Reservation to link the registered stay with its source Channel Block and display an onboarded status badge in administrative views.
_Avoid_: iCal ID, Event Token, Block Hash

**Channel Block Absorption**:
The automated subsumption of an imported iCalendar Channel Block by a matching External Reservation (or Direct Reservation) covering the identical date range on the Reservation Ledger, preventing redundant calendar conflicts.
_Avoid_: Block Merge, iCal Override, Calendar Deduplication

**Maintenance Block**:
A deliberate administrative unavailability hold applied to a Property's calendar for maintenance, repairs, or private host use. Maintenance Blocks do not create Reservation records, Guest identities, or financial transactions, but are projected onto Outbound Feeds to block external channels.
_Avoid_: Admin Block, Blackout Dates, Owner Hold

**Inbound Feed**:
An external calendar subscription periodically fetched by OceanViewFlats to identify and enforce Channel Blocks against direct checkout requests.
_Avoid_: Calendar Import, External Feed, Inbound iCal

**Outbound Feed**:
The dynamic iCalendar stream generated by OceanViewFlats providing external channels with real-time dates blocked by Direct Reservations and Maintenance Blocks. Outbound Feeds strictly exclude External Reservations whose source matches the target channel (e.g. excluding Airbnb reservations from Airbnb outbound feeds) to prevent circular booking loops.
_Avoid_: Calendar Export, ICS Feed, Availability Export

### Guest Onboarding & Fulfillment

**Guest Registry**:
The mandatory record of legal identification, ages, Primary Guest contact email, and vehicle details for all staying Guests required by building administration and regulatory compliance before Property access is granted. Can be pre-filled via stay parameters in the query string or resolved asynchronously by reservation UID (`code=res-man-*`, `code=res-abnb-*`, or `code=ovf_*`).
_Avoid_: Check-in Form, Registration Card, Guest List

**Guest Guide**:
The digital handbook providing access credentials, arrival instructions, and house rules, unlocked only after the Guest Registry is completed.
_Avoid_: Welcome Pack, House Manual, Instructions Page

**Access Credential**:
The temporal PIN or smart-lock entry code that grants physical entry to the Property during the reserved dates.
_Avoid_: Door Password, Key Code, Room Key

### Communications & Inquiries

**Inquiry**:
A pre-booking question or message submitted by a prospective visitor via the contact form. Inquiries may reference desired stay dates but never create a calendar hold or reservation record; visitors must always complete direct checkout to book.
_Avoid_: Contact Message, Lead, Ticket, Support Request

**Reservation Confirmation**:
The automated transactional email dispatched to the Primary Guest upon payment settlement, containing confirmed stay dates, a payment receipt, and the mandatory link to complete the Guest Registry.
_Avoid_: Booking Receipt, Order Confirmation, Welcome Email

**External Guest Dispatch**:
The administrative generation and delivery of onboarding communications for guests arriving via external booking platforms, featuring a Stage 1 Guest Registry invitation (with a copyable bilingual message for OTA chat inboxes and optional direct email) followed by Stage 2 Access Dispatch once registration compliance is verified under ADR 0001.
_Avoid_: Airbnb Welcome Message, Check-in Email, Code Dispatch

**Registry Report**:
The administrative dispatch generated upon Guest Registry submission containing all registered occupant IDs, ages, and vehicle details, delivered to property management for condominium front-desk clearance.
_Avoid_: Check-in Summary, Guest Roster, Security Notification

**Access Dispatch**:
The communication issued only after the Guest Registry is submitted, delivering the Guest Guide link and temporal Access Credentials for physical property entry.
_Avoid_: Key Code Email, Door PIN Message, Welcome Packet

**Cancellation Notice**:
The automated transactional communication dispatched to the Primary Guest when a Reservation is voided or cancelled, itemizing voided stay dates, financial settlement accounting (Refunded Amount vs. Policy Retention), and customer support channels.
_Avoid_: Cancellation Receipt, Refund Email, Drop Notice

**Condominium Administration Portal**:
The external property management and building reception system (Huésped Manager) utilized by security and front-desk personnel at Edificio Salguero Sunset to verify guest occupancy authorizations, register vehicles, and enforce statutory local building compliance.
_Avoid_: External Tool, Third-party Form, Salguero App, Huesped Manager

**Condominium Clearance Sync**:
The automated or administratively retried integration process that transmits verified Guest Registry stay parameters, vehicle plates, and registered occupant identities to the Condominium Administration Portal upon Guest Registry submission or manual administrative verification under ADR 0001 and ADR 0008.
_Avoid_: Reception Push, Front Desk Export, Building Registration

**Condominium Clearance Number**:
The authoritative external numeric tracking sequence (`consecutivo` / `last_id`) issued by the Condominium Administration Portal upon successful reservation and guest registration, recorded on the reservation audit dossier for front-desk cross-referencing and verification.
_Avoid_: Registration ID, External Consecutivo, Booking Voucher Number

### Administration & Operations

**Admin Console**:
The isolated administrative web application (`admin.oceanviewflats.com`) through which Admin Users manage Reservations, Seasonal Rate Tiers, Maintenance Blocks, Channel Sync, and Audit Logs.
_Avoid_: Admin Platform, Admin Portal, Backoffice, Control Panel

**Admin User**:
An authorized internal operator possessing authenticated credentials to manage Reservations, Access Credentials, rates, and calendar blocks.
_Avoid_: Staff, Employee, Operator, Superuser

**Audit Log**:
An immutable administrative record capturing operational actions (such as door PIN overrides, cancellations, refunds, rate updates, or webhook settlements), attributing the change to an Admin User or System actor with timestamps and before/after payload diffs. Access to the Audit Log interface is restricted strictly to Admin Users with admin or superadmin roles.
_Avoid_: History, Activity Feed, Event Log, Change Trail

**Audit Log Inspector**:
The slide-over administrative inspection drawer that displays before-and-after state transitions, actor attribution, client IP and user agent metadata, and deep links to audited domain entities.
_Avoid_: Detail Modal, Log Popup, Change Viewer

**Reservation Editor**:
The administrative modal interface consolidating a Confirmed Reservation's contractual details, Occupancy Override capacity, vehicle registration, and Guest Registry occupant roster for unified consultation and modification by an Admin User.
_Avoid_: Edit Booking Popup, Guest List Editor, Reservation Form

**Property Filter**:
The administrative UI selector used across operational interfaces (rates, calendar holds, reservations) to scope records by Property. Always labeled "Property" in administrative views in strict adherence to canonical entity language.
_Avoid_: Unit Filter, Unit Tab, Apartment Switcher, Listing Selector

**Dashboard Hub**:
The central administrative landing view (`/`) aggregating critical real-time operational information: 7-day arrivals and departures, operational alerts, active Property rates and seasonal rate tier transitions, and scheduled calendar blocks across all managed properties.
_Avoid_: Admin Home, Overview Page, Control Center, Portal, Landing Page

**Operational Alert**:
A high-priority actionable indicator surfaced on the Dashboard Hub for active or upcoming stays (prior to the Check-out Date and not administratively dismissed), specifically highlighting un-onboarded Channel Blocks requiring External Reservation creation, Confirmed Reservations with upcoming arrival dates whose Guest Registry has not yet been submitted, or Confirmed Reservations whose Condominium Clearance Sync has failed.
_Avoid_: Warning, System Notification, Todo, Action Item

**Occupancy Heatmap**:
The high-density, multi-month calendar matrix on the Dashboard Hub rendering daily occupancy levels, turnarounds, and reservation statuses across managed properties over a seven-month horizon.
_Avoid_: Contribution Graph, Activity Calendar, Booking Grid, Availability Table

**Occupancy Level**:
The quantitative measurement of property capacity utilized on a given calendar date (e.g. 0/2 vacant, 1/2 partial, 2/2 full occupancy across all properties, or binary occupancy when filtered to a single Property).
_Avoid_: Heat Level, Fill Rate, Booking Score

**Turnover Indicator**:
A visual operational marker indicating that a Check-out Date and Check-in Date coincide on the same calendar date for a given Property, requiring same-day turnaround cleaning and inspection.
_Avoid_: Same-day Switch, Turnaround Flag, Double Day

**Operational Warning Indicator**:
A visual operational marker displayed on a current or future Occupancy Heatmap date cell signifying an active, non-dismissed Operational Alert (such as an upcoming Confirmed Reservation with an incomplete Guest Registry or failed Condominium Clearance Sync).
_Avoid_: Warning Dot, Error Badge, Alert Pin



