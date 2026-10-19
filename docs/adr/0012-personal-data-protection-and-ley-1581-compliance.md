# Personal Data Protection, Cryptographic Redaction, and Ley 1581 Compliance

## Status

Accepted (Amends [ADR 0001](0001-mandatory-registry-before-access.md) regarding deterministic PIN derivation and [ADR 0008](0008-automated-condominium-clearance-sync.md) regarding clearance payload persistence)

## Context

OceanViewFlats operates direct short-term rental accommodations in Santa Marta, Colombia (RNT #268781 and RNT #270413). While Colombian hospitality regulations (Law 1558 of 2012, Law 2068 of 2020, Decree 2590 of 2009) and building security policies require collecting guest identification, ages, nationalities, and vehicle details via the Guest Registry, the platform is simultaneously bound by **Colombian Statutory Law 1581 of 2012** (*Régimen General de Protección de Datos Personales*), **Decree 1377 of 2013** (*Decreto Único 1074 de 2015*), and **Law 679 of 2001 / Law 1336 of 2009** (protection of minors and ESCNNA prevention).

Previously:
1. Public forms (`Contact`, `Direct Checkout`, `Guest Registry`) collected personal data without prior, express, and informed authorization (*Autorización previa, expresa e informada*) or a published *Política de Tratamiento de Datos Personales* (PTDP), and without requiring parental/guardian attestation when registering minors.
2. `DoorCodeGenerator::generate()` derived physical apartment PINs deterministically from the last 6 digits of the Primary Guest's government ID document, creating a bidirectional leak between physical access credentials and personal identification numbers.
3. Occupant PII and unmasked government ID numbers were duplicated in plaintext across four locations: `guest_registries.guests_payload`, `condominium_clearances.request_payload`, `admin_audit_logs.payload_after`, and unencrypted host notification emails (`HostRegistryEmailRenderer`), as well as exported to an external Google Sheets webhook (`GoogleSheetWebhookSync`).
4. Concluded reservations lacked a post-checkout PII redaction mechanism that could purge expired personal identification records while preserving financial and occupancy reporting integrity.

## Decision

We establish a defense-in-depth **Ley 1581 of 2012 Personal Data Protection Architecture** across all public capture points, domain services, persistence adapters, and administrative tools:

### 1. Published Data Processing Policy & Verifiable Personal Data Authorization
- **Data Controller**: OceanViewFlats (Manuel Enrique Herrera, RNT #268781 and RNT #270413, Calle 26 # 2-80, Playa Salguero, Santa Marta, Colombia; `rentals@oceanviewflats.com`; `+57 (310) 815-5234`) is published as the authoritative *Responsable del Tratamiento* on a dedicated multilingual `/privacy` page linked across the site footer and all public forms.
- **Prior, Express, and Informed Consent**: All three public forms (`Contact`, `Direct Checkout`, `Guest Registry`) require an explicit, unchecked-by-default **Personal Data Authorization** checkbox. Backend processors reject unconsented public submissions (`HTTP 400/422`) and persist immutable proof of authorization (`privacy_policy_version`, `privacy_consent_at`, `privacy_consent_ip`) on `reservations` and `guest_registries`.
- **Reactive Minor Guardian Attestation**: Whenever any occupant in the Guest Registry has `age < 18` or a minor document type (`Tarjeta de Identidad`, `Registro Civil`), the UI dynamically reveals and the backend strictly enforces a dedicated **Minor Guardian Attestation** (`minor_guardian_consent`), certifying parental authority (*patria potestad*) or written legal representation in compliance with Law 1581 (Art. 7) and Law 679 of 2001.

### 2. Cryptographically Random Access Credentials (Amending ADR 0001)
- `DoorCodeGenerator::generate()` is decoupled from the Primary Guest's government ID and phone number, generating cryptographically random 6-digit smart-lock codes (`0XXXXXX#` via `random_int(100000, 999999)`). Physical door PINs never encode or expose digits of a guest's identification document.

### 3. Single Encrypted Authoritative Store & Secondary Sink Redaction
- **JSON-Wrapped Encryption at Rest**: `guest_registries.guests_payload` is the single authoritative store for occupant identity records and is encrypted at rest using authenticated symmetric encryption (`sodium_crypto_secretbox` XSalsa20-Poly1305 keyed by `APP_ENCRYPTION_KEY`) serialized inside a valid JSON envelope (`{"_encrypted":true,"v":1,"alg":"xsalsa20-poly1305","nonce":"...","ciphertext":"..."}`), preserving compatibility with MySQL's `JSON NOT NULL` column constraint and supporting transparent dual-read of legacy rows.
- **Secondary Sink Masking**: `admin_audit_logs`, `condominium_clearances.request_payload`, and `HostRegistryEmailRenderer` strictly mask government document numbers (e.g., `****5678`) and contact details prior to persistence or email transmission. Full occupant details are accessible exclusively to authenticated Admin Users within the Admin Console Reservation Dossier.
- **Excision of External Spreadsheet Sync**: `GoogleSheetWebhookSync` and `SpreadsheetSyncInterface` are retired and excised across the fulfillment pipeline, eliminating an unnecessary international third-party cloud transfer surface.

### 4. Financial-Safe PII Redaction & 90-Day Post-Checkout Retention
- After a 90-day post-checkout retention window (or immediately upon an administrative ARCO suppression action in the Admin Console), **PII Redaction** scrubs `guest_registries` (`guests_payload = []`, `car_plates = NULL`, `car_model = NULL`, `ip_address = NULL`), `condominium_clearances` (`request_payload = NULL`, `error_message = NULL`), and `reservations` identity fields (`guest_name = 'Redacted Guest'`, `guest_email = 'redacted@oceanviewflats.local'`, `guest_phone = 'REDACTED'`, `door_code = NULL`, `notes = NULL`), while strictly preserving `total_price`, `refunded_amount`, `check_in`, `check_out`, `property_id`, `source`, and `status` so `FinancialReportEngine` metrics remain 100% intact.
