<?php

declare(strict_types=1);

namespace OceanViewFlats\Infrastructure\Reservation;

use OceanViewFlats\Admin\Service\MercadoPagoRefundClientInterface;
use OceanViewFlats\Domain\Fulfillment\BookingFulfillment;
use OceanViewFlats\Domain\Fulfillment\BookingFulfillmentInterface;
use OceanViewFlats\Domain\Fulfillment\CancellationEmailRendererInterface;
use OceanViewFlats\Domain\Fulfillment\EmailSenderInterface;
use OceanViewFlats\Domain\Fulfillment\GuestLifecycleFulfillmentServiceInterface;
use OceanViewFlats\Domain\Quote\QuoteEngine;
use OceanViewFlats\Domain\Quote\QuoteEngineInterface;
use OceanViewFlats\Domain\Reservation\InMemoryReservationRepository;
use OceanViewFlats\Domain\Reservation\MaintenanceBlockSourceInterface;
use OceanViewFlats\Domain\Reservation\Port\AuditPort;
use OceanViewFlats\Domain\Reservation\Port\LifecycleEventPublisherPort;
use OceanViewFlats\Domain\Reservation\Port\PaymentRefundPort;
use OceanViewFlats\Domain\Reservation\Port\ReservationPersistencePort;
use OceanViewFlats\Domain\Reservation\ReservationLedger;
use OceanViewFlats\Domain\Reservation\ReservationLedgerInterface;
use OceanViewFlats\Domain\Reservation\ReservationLifecycleEngine;
use OceanViewFlats\Domain\Reservation\ReservationLifecycleEngineInterface;
use OceanViewFlats\Domain\Reservation\ReservationRepositoryInterface;
use OceanViewFlats\Infrastructure\Reservation\InMemory\InMemoryAuditAdapter;
use OceanViewFlats\Infrastructure\Reservation\InMemory\InMemoryReservationPersistenceAdapter;
use PDO;

/**
 * Infrastructure factory for composing ReservationLifecycleEngine with production
 * or in-memory adapters, preventing Hexagonal architecture port boundary inversion.
 */
final class ReservationLifecycleEngineFactory
{
    /**
     * Instantiates engine with standard infrastructure adapters and PDO connection.
     *
     * @param ?PDO $pdo
     * @param array<string, mixed> $options
     */
    public static function create(?PDO $pdo = null, array $options = []): ReservationLifecycleEngineInterface
    {
        $repository = isset($options['repository']) && $options['repository'] instanceof ReservationRepositoryInterface
            ? $options['repository']
            : null;

        $persistence = isset($options['persistencePort']) && $options['persistencePort'] instanceof ReservationPersistencePort
            ? $options['persistencePort']
            : ($pdo !== null
                ? new PdoReservationPersistenceAdapter($pdo, $repository)
                : new InMemoryReservationPersistenceAdapter([], $repository));

        $paymentRefund = isset($options['paymentRefundPort']) && $options['paymentRefundPort'] instanceof PaymentRefundPort
            ? $options['paymentRefundPort']
            : new MercadoPagoPaymentRefundAdapter(
                isset($options['mpRefundClient']) && $options['mpRefundClient'] instanceof MercadoPagoRefundClientInterface
                    ? $options['mpRefundClient']
                    : null,
                isset($options['mpAccessToken']) && is_string($options['mpAccessToken'])
                    ? $options['mpAccessToken']
                    : null
            );

        $audit = isset($options['auditPort']) && $options['auditPort'] instanceof AuditPort
            ? $options['auditPort']
            : ($pdo !== null
                ? new PdoAuditAdapter($pdo)
                : new InMemoryAuditAdapter());

        $bookingFulfillment = isset($options['bookingFulfillment']) && $options['bookingFulfillment'] instanceof BookingFulfillmentInterface
            ? $options['bookingFulfillment']
            : BookingFulfillment::createDefault();

        $eventPublisher = isset($options['eventPublisherPort']) && $options['eventPublisherPort'] instanceof LifecycleEventPublisherPort
            ? $options['eventPublisherPort']
            : new TransactionalLifecycleEventPublisherAdapter(
                fulfillmentService: isset($options['fulfillmentService']) && $options['fulfillmentService'] instanceof GuestLifecycleFulfillmentServiceInterface
                    ? $options['fulfillmentService']
                    : null,
                cancellationRenderer: isset($options['cancellationRenderer']) && $options['cancellationRenderer'] instanceof CancellationEmailRendererInterface
                    ? $options['cancellationRenderer']
                    : null,
                emailSender: isset($options['emailSender']) && $options['emailSender'] instanceof EmailSenderInterface
                    ? $options['emailSender']
                    : null,
                bookingFulfillment: $bookingFulfillment,
                auditPort: $audit
            );

        $cacheDir = isset($options['cacheDir']) && is_string($options['cacheDir']) ? $options['cacheDir'] : null;
        $maintenanceSource = isset($options['maintenanceBlockSource']) && $options['maintenanceBlockSource'] instanceof MaintenanceBlockSourceInterface
            ? $options['maintenanceBlockSource']
            : null;

        $ledger = isset($options['ledger']) && $options['ledger'] instanceof ReservationLedgerInterface
            ? $options['ledger']
            : ReservationLedger::createDefault(
                pdo: $pdo,
                cacheDir: $cacheDir,
                maintenanceBlockSource: $maintenanceSource,
                repository: $repository
            );

        $quoteEngine = isset($options['quoteEngine']) && $options['quoteEngine'] instanceof QuoteEngineInterface
            ? $options['quoteEngine']
            : QuoteEngine::createDefault(
                csvPath: isset($options['csvPath']) && is_string($options['csvPath']) ? $options['csvPath'] : null
            );

        return new ReservationLifecycleEngine(
            persistencePort: $persistence,
            paymentRefundPort: $paymentRefund,
            eventPublisherPort: $eventPublisher,
            auditPort: $audit,
            ledger: $ledger,
            quoteEngine: $quoteEngine
        );
    }
}
