<?php
/**
 * OceanViewFlats OpenAPI 3.1 Specification Definition
 * PHP 8 Attributes Compatible
 */

declare(strict_types=1);

namespace OceanViewFlats\Api;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'OceanViewFlats REST API',
    description: 'REST API documentation for OceanViewFlats beachfront vacation rentals in Playa Salguero, Santa Marta, Colombia (Properties 1606 & 1707). Provides endpoints for real-time calendar availability, authoritative quotations, direct booking requests, Mercado Pago Checkout Bricks payment processing, asynchronous webhooks, statutory Guest Registry verification, gated Guest Guide access, and iCalendar synchronization.',
    contact: new OA\Contact(
        name: 'OceanViewFlats Support',
        email: 'rentals@oceanviewflats.com',
        url: 'https://oceanviewflats.com'
    ),
    license: new OA\License(
        name: 'Proprietary',
        url: 'https://oceanviewflats.com'
    )
)]
#[OA\Server(
    url: '/api',
    description: 'Current Environment / Relative API Server'
)]
#[OA\Server(
    url: 'https://oceanviewflats.com/api',
    description: 'Production API Server'
)]
#[OA\Server(
    url: 'http://localhost:8000/api',
    description: 'Local Development Server'
)]
#[OA\Tag(name: 'Availability', description: 'Real-time calendar availability and iCal feeds synchronization')]
#[OA\Tag(name: 'Quotation', description: 'Authoritative in-process stay quotation and fee calculations')]
#[OA\Tag(name: 'Booking', description: 'Direct booking requests, date validations, and quotation processing')]
#[OA\Tag(name: 'Payments', description: 'Mercado Pago Checkout Bricks and server-to-server transaction processor')]
#[OA\Tag(name: 'Webhooks', description: 'Asynchronous payment status notifications and IPN listener')]
#[OA\Tag(name: 'Guest Registry', description: 'Police guest check-in registration form processor')]
#[OA\Tag(name: 'Contact', description: 'Direct contact form messaging and math challenge generation')]
#[OA\Tag(name: 'Calendar Feed', description: 'Outbound iCalendar (.ics) exports for channel management')]
class OpenApi
{
}

// =============================================================================
// Reusable Schema Definitions
// =============================================================================

#[OA\Schema(
    schema: 'CaptchaChallenge',
    description: 'Mathematical CAPTCHA challenge model for anti-abuse verification',
    properties: [
        new OA\Property(property: 'math_challenge', type: 'string', example: '7 + 4 = ?', description: 'Human-readable arithmetic question'),
        new OA\Property(property: 'captcha_signature', type: 'string', example: 'a1b2c3d4e5f67890abcdef...', description: 'HMAC cryptographic signature of the answer'),
        new OA\Property(property: 'success', type: 'boolean', example: true)
    ]
)]
class CaptchaChallengeSchema {}

#[OA\Schema(
    schema: 'StandardResponse',
    description: 'Standard JSON success response structure',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'message', type: 'string', example: 'Operation completed successfully.')
    ]
)]
class StandardResponseSchema {}

#[OA\Schema(
    schema: 'ErrorResponse',
    description: 'Standard JSON error response structure',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'message', type: 'string', example: 'Invalid parameter provided.'),
        new OA\Property(property: 'error', type: 'string', nullable: true, example: 'Validation Error')
    ]
)]
class ErrorResponseSchema {}

#[OA\Schema(
    schema: 'GuestInfo',
    description: 'Guest identity detail for check-in registry',
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'Juan Perez', description: 'Full legal name of the guest'),
        new OA\Property(property: 'doc_type', type: 'string', enum: ['CC', 'CE', 'PASSPORT', 'DNI', 'TI', 'OTHER'], example: 'PASSPORT', description: 'Identity document type'),
        new OA\Property(property: 'doc_number', type: 'string', example: 'P12345678', description: 'Document or passport number'),
        new OA\Property(property: 'age', type: 'integer', example: 32, description: 'Guest age in years')
    ],
    required: ['name', 'doc_type', 'doc_number', 'age']
)]
class GuestInfoSchema {}

#[OA\Schema(
    schema: 'BookingQuotation',
    description: 'Calculated pricing breakdown for a booking request',
    properties: [
        new OA\Property(property: 'nights', type: 'integer', example: 4),
        new OA\Property(property: 'nightly_rate_cop', type: 'number', format: 'float', example: 350000),
        new OA\Property(property: 'cleaning_fee_cop', type: 'number', format: 'float', example: 120000),
        new OA\Property(property: 'total_price_cop', type: 'number', format: 'float', example: 1520000),
        new OA\Property(property: 'currency', type: 'string', example: 'COP')
    ]
)]
class BookingQuotationSchema {}

#[OA\Schema(
    schema: 'PaymentPayerIdentification',
    description: 'Payer identification details for payment gateway',
    properties: [
        new OA\Property(property: 'type', type: 'string', example: 'CC', description: 'Identification type (e.g., CC, CE, PASSPORT, NIT)'),
        new OA\Property(property: 'number', type: 'string', example: '1020304050', description: 'Identification number')
    ]
)]
class PaymentPayerIdentificationSchema {}

#[OA\Schema(
    schema: 'PaymentPayer',
    description: 'Payer contact and identity details',
    properties: [
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'guest@example.com'),
        new OA\Property(property: 'identification', ref: '#/components/schemas/PaymentPayerIdentification')
    ]
)]
class PaymentPayerSchema {}

#[OA\Schema(
    schema: 'PaymentTransactionResult',
    description: 'Detailed payment gateway processing transaction response',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1234567890, description: 'Mercado Pago transaction identifier'),
        new OA\Property(property: 'status', type: 'string', enum: ['approved', 'in_process', 'pending', 'rejected', 'cancelled'], example: 'approved'),
        new OA\Property(property: 'status_detail', type: 'string', example: 'accredited'),
        new OA\Property(property: 'payment_method_id', type: 'string', example: 'visa'),
        new OA\Property(property: 'transaction_amount', type: 'number', format: 'float', example: 1520000),
        new OA\Property(property: 'external_reference', type: 'string', example: 'RES-1707-ABCD-1234'),
        new OA\Property(property: 'date_approved', type: 'string', format: 'date-time', nullable: true, example: '2026-09-01T14:30:00.000-05:00'),
        new OA\Property(property: 'ticket_url', type: 'string', nullable: true, example: 'https://www.mercadopago.com.co/payments/1234567890/ticket')
    ]
)]
class PaymentTransactionResultSchema {}

#[OA\Schema(
    schema: 'QuoteNightBreakdown',
    description: 'Individual night rate and tier classification breakdown',
    properties: [
        new OA\Property(property: 'date', type: 'string', format: 'date', example: '2026-06-01'),
        new OA\Property(property: 'rateCop', type: 'number', format: 'float', example: 350000),
        new OA\Property(property: 'tier', type: 'string', nullable: true, example: '2026-01-01_2026-12-14')
    ]
)]
class QuoteNightBreakdownSchema {}

#[OA\Schema(
    schema: 'QuoteData',
    description: 'Authoritative calculated stay quotation details',
    properties: [
        new OA\Property(property: 'property_id', type: 'string', enum: ['1606', '1707'], example: '1606'),
        new OA\Property(property: 'check_in', type: 'string', format: 'date', example: '2026-06-01'),
        new OA\Property(property: 'check_out', type: 'string', format: 'date', example: '2026-06-04'),
        new OA\Property(property: 'nights_count', type: 'integer', example: 3),
        new OA\Property(
            property: 'nights',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/QuoteNightBreakdown')
        ),
        new OA\Property(property: 'accommodation_total_cop', type: 'number', format: 'float', example: 1050000),
        new OA\Property(property: 'cleaning_fee_cop', type: 'number', format: 'float', example: 80000),
        new OA\Property(property: 'total_cop', type: 'number', format: 'float', example: 1130000),
        new OA\Property(property: 'minimum_stay_required', type: 'integer', example: 2),
        new OA\Property(property: 'is_valid', type: 'boolean', example: true),
        new OA\Property(property: 'violation_reason', type: 'string', nullable: true, example: null)
    ]
)]
class QuoteDataSchema {}

#[OA\Schema(
    schema: 'QuoteResponse',
    description: 'Successful quotation response envelope',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', ref: '#/components/schemas/QuoteData')
    ]
)]
class QuoteResponseSchema {}

// =============================================================================
// API Endpoints Specification
// =============================================================================

class AvailabilityEndpoints
{
    #[OA\Get(
        path: '/availability.php',
        operationId: 'getAvailability',
        summary: 'Fetch blocked calendar dates for a property',
        description: 'Fetches, parses, and returns cached blocked calendar dates (from upstream Airbnb iCal feeds and direct reservations) for the specified apartment.',
        tags: ['Availability'],
        parameters: [
            new OA\Parameter(
                name: 'property',
                in: 'query',
                description: 'Property Unit ID (1606 or 1707)',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['1606', '1707'], default: '1606')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of ISO 8601 blocked dates (YYYY-MM-DD)',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(type: 'string', format: 'date', example: '2026-09-01')
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Invalid property ID',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 429,
                description: 'Rate limit exceeded'
            ),
            new OA\Response(
                response: 502,
                description: 'Failed to retrieve calendar feed from upstream provider',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            )
        ]
    )]
    public function getAvailability(): void {}
}

class BookingEndpoints
{
    #[OA\Get(
        path: '/book-request.php',
        operationId: 'getBookingCaptcha',
        summary: 'Generate a CAPTCHA challenge for booking',
        description: 'Returns a newly generated arithmetic challenge and cryptographic signature for booking anti-abuse verification.',
        tags: ['Booking'],
        parameters: [
            new OA\Parameter(
                name: 'action',
                in: 'query',
                description: 'Action trigger must be "captcha"',
                required: true,
                schema: new OA\Schema(type: 'string', enum: ['captcha'])
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Active math challenge and signature',
                content: new OA\JsonContent(ref: '#/components/schemas/CaptchaChallenge')
            )
        ]
    )]
    public function getCaptcha(): void {}

    #[OA\Post(
        path: '/book-request.php',
        operationId: 'submitBookingRequest',
        summary: 'Submit a direct booking inquiry and quotation calculation',
        description: 'Validates requested dates against blocked calendar dates, computes rate breakdown, records the pending reservation in the database with Ley 1581 privacy authorization, and dispatches confirmation emails.',
        tags: ['Booking'],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Booking request parameters (Form-urlencoded or multipart)',
            content: new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    required: ['property_id', 'check_in', 'check_out', 'guest_name', 'guest_email', 'guest_phone', 'privacy_consent', 'captcha_challenge', 'captcha_signature', 'captcha_response'],
                    properties: [
                        new OA\Property(property: 'property_id', type: 'string', enum: ['1606', '1707'], example: '1606'),
                        new OA\Property(property: 'check_in', type: 'string', format: 'date', example: '2026-09-10'),
                        new OA\Property(property: 'check_out', type: 'string', format: 'date', example: '2026-09-15'),
                        new OA\Property(property: 'guest_name', type: 'string', example: 'John Doe'),
                        new OA\Property(property: 'guest_email', type: 'string', format: 'email', example: 'john@example.com'),
                        new OA\Property(property: 'guest_phone', type: 'string', example: '+57 300 123 4567'),
                        new OA\Property(property: 'total_price_cop', type: 'number', format: 'float', example: 1520000),
                        new OA\Property(property: 'lang', type: 'string', enum: ['en', 'es', 'fr', 'it', 'de', 'ja'], default: 'en'),
                        new OA\Property(property: 'privacy_consent', type: 'string', enum: ['1', 'true', 'on'], example: '1', description: 'Explicit Ley 1581 of 2012 Personal Data Authorization'),
                        new OA\Property(property: 'captcha_challenge', type: 'string', example: '5 + 3 = ?'),
                        new OA\Property(property: 'captcha_signature', type: 'string', example: 'f8c9d0...'),
                        new OA\Property(property: 'captcha_response', type: 'string', example: '8'),
                        new OA\Property(property: 'website_url', type: 'string', description: 'Anti-spam honeypot (leave empty)', example: '')
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Booking inquiry submitted successfully',
                content: new OA\JsonContent(ref: '#/components/schemas/StandardResponse')
            ),
            new OA\Response(
                response: 400,
                description: 'Validation error (invalid email, phone, or dates)',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 422,
                description: 'Dates unavailable or overlapping existing reservation',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 429,
                description: 'Rate limit exceeded'
            )
        ]
    )]
    public function submitBooking(): void {}
}

class ContactEndpoints
{
    #[OA\Get(
        path: '/contact-processor.php',
        operationId: 'getContactCaptcha',
        summary: 'Generate a CAPTCHA challenge for contact form',
        description: 'Returns a newly generated arithmetic challenge and cryptographic signature for contact form anti-abuse verification.',
        tags: ['Contact'],
        parameters: [
            new OA\Parameter(
                name: 'action',
                in: 'query',
                description: 'Action trigger must be "captcha"',
                required: true,
                schema: new OA\Schema(type: 'string', enum: ['captcha'])
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Active math challenge and signature',
                content: new OA\JsonContent(ref: '#/components/schemas/CaptchaChallenge')
            )
        ]
    )]
    public function getCaptcha(): void {}

    #[OA\Post(
        path: '/contact-processor.php',
        operationId: 'submitContactMessage',
        summary: 'Submit a contact form inquiry',
        description: 'Processes and sanitizes contact inquiries, verifies math CAPTCHA, and sends localized notification emails to host support.',
        tags: ['Contact'],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Contact form fields',
            content: new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    required: ['name', 'email', 'message', 'privacy_consent', 'captcha_challenge', 'captcha_signature', 'captcha_response'],
                    properties: [
                        new OA\Property(property: 'name', type: 'string', example: 'Jane Smith'),
                        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'jane@example.com'),
                        new OA\Property(property: 'phone', type: 'string', example: '+1 555 123 4567'),
                        new OA\Property(property: 'message', type: 'string', example: 'Hello, I have a question about parking availability.'),
                        new OA\Property(property: 'lang', type: 'string', enum: ['en', 'es', 'fr', 'it', 'de', 'ja'], default: 'en'),
                        new OA\Property(property: 'privacy_consent', type: 'string', enum: ['1', 'true', 'on'], example: '1', description: 'Explicit Ley 1581 of 2012 Personal Data Authorization'),
                        new OA\Property(property: 'captcha_challenge', type: 'string', example: '9 + 2 = ?'),
                        new OA\Property(property: 'captcha_signature', type: 'string', example: '9a8b7c...'),
                        new OA\Property(property: 'captcha_response', type: 'string', example: '11'),
                        new OA\Property(property: 'website_url', type: 'string', description: 'Anti-spam honeypot (leave empty)', example: '')
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Message sent successfully',
                content: new OA\JsonContent(ref: '#/components/schemas/StandardResponse')
            ),
            new OA\Response(
                response: 400,
                description: 'Validation or CAPTCHA error',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 429,
                description: 'Rate limit exceeded'
            )
        ]
    )]
    public function submitContact(): void {}
}

class PaymentEndpoints
{
    #[OA\Post(
        path: '/payment.php',
        operationId: 'processPayment',
        summary: 'Process booking payment via Mercado Pago Bricks custom API',
        description: 'Performs server-to-server transaction processing with Mercado Pago (credit cards, PSE bank transfers, Efecty vouchers), validates date availability, locks records, logs to database, and issues confirmation emails.',
        tags: ['Payments'],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Payment transaction payload with Mercado Pago Token and Booking details',
            content: new OA\JsonContent(
                required: ['property_id', 'check_in', 'check_out', 'guest_name', 'guest_email', 'guest_phone', 'payment_method_id', 'transaction_amount', 'payer', 'privacy_consent', 'captcha_challenge', 'captcha_signature', 'captcha_response'],
                properties: [
                    new OA\Property(property: 'property_id', type: 'string', enum: ['1606', '1707'], example: '1606'),
                    new OA\Property(property: 'check_in', type: 'string', format: 'date', example: '2026-10-01'),
                    new OA\Property(property: 'check_out', type: 'string', format: 'date', example: '2026-10-06'),
                    new OA\Property(property: 'guest_name', type: 'string', example: 'Carlos Mendoza'),
                    new OA\Property(property: 'guest_email', type: 'string', format: 'email', example: 'carlos@example.com'),
                    new OA\Property(property: 'guest_phone', type: 'string', example: '+57 310 987 6543'),
                    new OA\Property(property: 'lang', type: 'string', enum: ['en', 'es', 'fr', 'it', 'de', 'ja'], default: 'es'),
                    new OA\Property(property: 'token', type: 'string', description: 'Mercado Pago one-time credit card token', example: '9876543210abcdef'),
                    new OA\Property(property: 'payment_method_id', type: 'string', example: 'visa', description: 'Payment method identifier (e.g. visa, master, pse, efecty)'),
                    new OA\Property(property: 'issuer_id', type: 'string', nullable: true, example: '25'),
                    new OA\Property(property: 'installments', type: 'integer', default: 1, example: 1),
                    new OA\Property(property: 'transaction_amount', type: 'number', format: 'float', example: 1750000),
                    new OA\Property(property: 'payer', ref: '#/components/schemas/PaymentPayer'),
                    new OA\Property(property: 'privacy_consent', type: 'boolean', example: true, description: 'Explicit Ley 1581 of 2012 Personal Data Authorization'),
                    new OA\Property(property: 'captcha_challenge', type: 'string', example: '4 + 6 = ?'),
                    new OA\Property(property: 'captcha_signature', type: 'string', example: '3f2e1d...'),
                    new OA\Property(property: 'captcha_response', type: 'string', example: '10'),
                    new OA\Property(property: 'website_url', type: 'string', example: '')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Payment processed and reservation logged successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'status', type: 'string', example: 'approved'),
                        new OA\Property(property: 'message', type: 'string', example: 'Payment processed successfully.'),
                        new OA\Property(property: 'payment', ref: '#/components/schemas/PaymentTransactionResult')
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Payment rejection or invalid parameters',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 422,
                description: 'Selected dates already booked or unavailable',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 429,
                description: 'Rate limit exceeded'
            ),
            new OA\Response(
                response: 500,
                description: 'Database or payment gateway communication error',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            )
        ]
    )]
    public function processPayment(): void {}
}

class WebhookEndpoints
{
    #[OA\Post(
        path: '/mercadopago-webhook.php',
        operationId: 'handleMercadoPagoWebhook',
        summary: 'Mercado Pago asynchronous IPN & Webhook receiver',
        description: 'Receives real-time payment status updates from Mercado Pago, verifies payment status server-to-server, updates database reservation and refund records with Resurrection Defense, and dispatches localized guest confirmation or cancellation emails.',
        tags: ['Webhooks'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'query',
                description: 'Payment ID (IPN fallback)',
                required: false,
                schema: new OA\Schema(type: 'string', example: '1234567890')
            ),
            new OA\Parameter(
                name: 'topic',
                in: 'query',
                description: 'Notification topic (e.g. payment)',
                required: false,
                schema: new OA\Schema(type: 'string', example: 'payment')
            )
        ],
        requestBody: new OA\RequestBody(
            required: false,
            description: 'Webhook JSON payload from Mercado Pago',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'action', type: 'string', example: 'payment.updated'),
                    new OA\Property(property: 'api_version', type: 'string', example: 'v1'),
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'id', type: 'string', example: '1234567890')
                        ]
                    ),
                    new OA\Property(property: 'date_created', type: 'string', format: 'date-time', example: '2026-09-01T15:00:00Z'),
                    new OA\Property(property: 'id', type: 'integer', example: 1234567890),
                    new OA\Property(property: 'type', type: 'string', example: 'payment')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Webhook processed successfully or acknowledged',
                content: new OA\JsonContent(ref: '#/components/schemas/StandardResponse')
            ),
            new OA\Response(
                response: 400,
                description: 'Invalid webhook payload or signature',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 500,
                description: 'Database or configuration error',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            )
        ]
    )]
    public function handleWebhook(): void {}
}

class RegistryEndpoints
{
    #[OA\Post(
        path: '/registry-processor.php',
        operationId: 'submitGuestRegistry',
        summary: 'Submit Guest Check-in Police Registration',
        description: 'Submits guest personal identity details (encrypted at rest via XSalsa20-Poly1305 per ADR 0012), document numbers, companion guest information, vehicle details, Personal Data Authorization, and Minor Guardian Attestation required by Colombian hospitality law and Edificio Salguero Sunset security clearance (ADR 0001, ADR 0008).',
        tags: ['Guest Registry'],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Guest check-in registry form data',
            content: new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    required: ['property', 'check_in', 'check_out', 'guests', 'privacy_consent', 'captcha_challenge', 'captcha_signature', 'captcha_response'],
                    properties: [
                        new OA\Property(property: 'property', type: 'string', enum: ['1606', '1707'], example: '1606'),
                        new OA\Property(property: 'check_in', type: 'string', format: 'date', example: '2026-09-10'),
                        new OA\Property(property: 'check_out', type: 'string', format: 'date', example: '2026-09-15'),
                        new OA\Property(
                            property: 'guests',
                            type: 'array',
                            description: 'Array of registered guests',
                            items: new OA\Items(ref: '#/components/schemas/GuestInfo')
                        ),
                        new OA\Property(property: 'car_plates', type: 'string', nullable: true, example: 'XYZ123'),
                        new OA\Property(property: 'car_model', type: 'string', nullable: true, example: 'Toyota Fortuner White'),
                        new OA\Property(property: 'privacy_consent', type: 'string', enum: ['1', 'true', 'on'], example: '1', description: 'Explicit Ley 1581 of 2012 Personal Data Authorization'),
                        new OA\Property(property: 'minor_guardian_consent', type: 'string', enum: ['1', 'true', 'on'], nullable: true, example: '1', description: 'Mandatory Minor Guardian Attestation when any guest is under 18 or holds a minor ID'),
                        new OA\Property(property: 'lang', type: 'string', enum: ['en', 'es', 'fr', 'it', 'de', 'ja'], default: 'es'),
                        new OA\Property(property: 'captcha_challenge', type: 'string', example: '3 + 8 = ?'),
                        new OA\Property(property: 'captcha_signature', type: 'string', example: '4e5f6a...'),
                        new OA\Property(property: 'captcha_response', type: 'string', example: '11'),
                        new OA\Property(property: 'website_url', type: 'string', example: '')
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Guest registry successfully recorded and logged',
                content: new OA\JsonContent(ref: '#/components/schemas/StandardResponse')
            ),
            new OA\Response(
                response: 400,
                description: 'Validation error (missing guest details or invalid document)',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 429,
                description: 'Rate limit exceeded'
            )
        ]
    )]
    public function submitRegistry(): void {}
}

class ICalEndpoints
{
    #[OA\Get(
        path: '/ical.php',
        operationId: 'exportICalFeed',
        summary: 'Export booked calendar dates in iCalendar (.ics) format',
        description: 'Generates a standard RFC 5545 iCalendar stream containing all confirmed and active holds for the specified apartment to synchronize with Airbnb, Booking.com, and VRBO calendar channel managers.',
        tags: ['Calendar Feed'],
        parameters: [
            new OA\Parameter(
                name: 'property',
                in: 'query',
                description: 'Property Unit ID (1606 or 1707)',
                required: true,
                schema: new OA\Schema(type: 'string', enum: ['1606', '1707'], example: '1606')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Valid iCalendar file stream (text/calendar)',
                content: new OA\MediaType(
                    mediaType: 'text/calendar',
                    schema: new OA\Schema(
                        type: 'string',
                        example: "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//OceanViewFlats//Direct Booking Sync//EN\r\nCALSCALE:GREGORIAN\r\nMETHOD:PUBLISH\r\nBEGIN:VEVENT\r\nUID:RES-1606-1234@oceanviewflats.com\r\nDTSTART;VALUE=DATE:20260910\r\nDTEND;VALUE=DATE:20260915\r\nSUMMARY:Blocked - OceanViewFlats Direct Booking\r\nEND:VEVENT\r\nEND:VCALENDAR"
                    )
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Invalid or missing property parameter'
            ),
            new OA\Response(
                response: 429,
                description: 'Rate limit exceeded'
            ),
            new OA\Response(
                response: 500,
                description: 'Database or server configuration error'
            )
        ]
    )]
    public function exportICal(): void {}
}

class QuoteEndpoints
{
    #[OA\Get(
        path: '/quote.php',
        operationId: 'getQuote',
        summary: 'Calculate authoritative stay quotation',
        description: 'Computes night-by-night seasonal pricing, enforces strict multi-tier maximum minimum stays, and includes centralized cleaning fees per ADR 0004.',
        tags: ['Quotation'],
        parameters: [
            new OA\Parameter(
                name: 'property_id',
                in: 'query',
                description: 'Property identifier (1606 or 1707)',
                required: true,
                schema: new OA\Schema(type: 'string', enum: ['1606', '1707'], example: '1606')
            ),
            new OA\Parameter(
                name: 'check_in',
                in: 'query',
                description: 'Check-in date in YYYY-MM-DD format',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'date', example: '2026-06-01')
            ),
            new OA\Parameter(
                name: 'check_out',
                in: 'query',
                description: 'Check-out date in YYYY-MM-DD format',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'date', example: '2026-06-04')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Authoritative quotation calculation result',
                content: new OA\JsonContent(ref: '#/components/schemas/QuoteResponse')
            ),
            new OA\Response(
                response: 400,
                description: 'Invalid date parameters, inverted date span, or unknown property',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 405,
                description: 'Method Not Allowed'
            ),
            new OA\Response(
                response: 429,
                description: 'Rate limit exceeded'
            ),
            new OA\Response(
                response: 500,
                description: 'Server configuration or calculation error'
            )
        ]
    )]
    public function calculateQuote(): void {}
}
