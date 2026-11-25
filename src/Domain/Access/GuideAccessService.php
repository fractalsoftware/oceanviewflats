<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Access;

use OceanViewFlats\Domain\Reservation\ReservationRepositoryInterface;
use OceanViewFlats\Domain\Reservation\ReservationStatus;
use OceanViewFlats\Domain\Support\PublicUrlBuilder;

/**
 * Domain service enforcing ADR 0001: Access Credentials and the Guest Guide are strictly
 * withheld until the Primary Guest completes the Guest Registry for all staying Guests.
 */
final class GuideAccessService implements GuideAccessServiceInterface
{
    private readonly PublicUrlBuilder $urlBuilder;

    /**
     * @param array<string, array<string, string>> $translations
     */
    public function __construct(
        private readonly ReservationRepositoryInterface $repository,
        private readonly PropertyCredentialsProviderInterface $credentialsProvider,
        private readonly string $baseUrl = '',
        private readonly array $translations = [],
        ?PublicUrlBuilder $urlBuilder = null
    ) {
        $this->urlBuilder = $urlBuilder ?? new PublicUrlBuilder($this->baseUrl);
    }

    public function verifyAccess(string $reservationUid, string $lang = 'en'): AccessVerificationResult
    {
        $code = trim($reservationUid);
        if ($code === '') {
            return AccessVerificationResult::notFound(
                $this->getMessage($lang, 'err_missing_code', 'Reservation code is required.')
            );
        }

        $reservation = $this->repository->findByUid($code);
        if ($reservation === null) {
            return AccessVerificationResult::notFound(
                $this->getMessage($lang, 'err_not_found', 'No reservation found matching code ' . $code)
            );
        }

        if ($reservation->status !== ReservationStatus::CONFIRMED) {
            if ($reservation->status === ReservationStatus::PENDING_PAYMENT) {
                return AccessVerificationResult::unauthorized(
                    $this->getMessage(
                        $lang,
                        'err_payment_pending',
                        'Reservation payment is pending verification. Access credentials unlock upon confirmed payment and registry completion.'
                    )
                );
            }

            return AccessVerificationResult::unauthorized(
                $this->getMessage($lang, 'err_unauthorized', 'Reservation is not active or has been cancelled.')
            );
        }

        if ($reservation->isConcluded()) {
            return AccessVerificationResult::concluded(
                $this->getMessage(
                    $lang,
                    'msg_concluded',
                    'This reservation has concluded and its details are no longer accessible.'
                )
            );
        }

        if (!$reservation->registryCompleted) {
            $registryUrl = $this->urlBuilder->buildRegistryUrl($lang, [
                'property' => $reservation->propertyId,
                'check_in' => $reservation->checkIn,
                'check_out' => $reservation->checkOut,
                'code' => $reservation->reservationUid,
            ]);

            return AccessVerificationResult::registryRequired(
                reservation: $reservation,
                registryUrl: $registryUrl,
                message: $this->getMessage(
                    $lang,
                    'err_registry_required',
                    'Guest registry must be submitted before access credentials are released (ADR 0001).'
                )
            );
        }

        $credentials = $this->credentialsProvider->getCredentials($reservation->propertyId);
        if ($credentials === null) {
            return AccessVerificationResult::unauthorized('Access credentials could not be resolved for property ' . $reservation->propertyId);
        }

        // If reservation has a dynamic or custom door code, use it over the property default
        if (!empty($reservation->doorCode)) {
            $credentials = new AccessCredentials(
                propertyId: $credentials->propertyId,
                doorCode: $reservation->doorCode,
                wifiSsid: $credentials->wifiSsid,
                wifiPassword: $credentials->wifiPassword,
                parkingSpot: $credentials->parkingSpot
            );
        }

        return AccessVerificationResult::verified(
            reservation: $reservation,
            credentials: $credentials,
            message: $this->getMessage($lang, 'msg_verified', 'Access credentials verified successfully.')
        );
    }

    private function getMessage(string $lang, string $key, string $default): string
    {
        if (isset($this->translations[$lang][$key])) {
            return $this->translations[$lang][$key];
        }
        if (isset($this->translations['en'][$key])) {
            return $this->translations['en'][$key];
        }
        return $default;
    }
}
