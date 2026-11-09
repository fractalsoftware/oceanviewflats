<?php

declare(strict_types=1);

namespace OceanViewFlats\Admin\Controller;

use DateTimeImmutable;
use InvalidArgumentException;
use OceanViewFlats\Admin\Audit\AuditLogger;
use OceanViewFlats\Admin\Http\Request;
use OceanViewFlats\Admin\Http\Response;
use OceanViewFlats\Admin\Views\ViewRenderer;
use OceanViewFlats\Domain\Quote\PropertyRatesConfig;
use OceanViewFlats\Domain\Quote\RateRepositoryInterface;
use OceanViewFlats\Domain\Quote\RateTier;
use OceanViewFlats\Domain\Support\PathResolver;
use Throwable;

/**
 * Controller handling seasonal pricing rate tier management, HTMX timelines,
 * interval overlap validations (HTTP 422), and audit trail logging.
 */
final class RateController
{
    public function __construct(
        private readonly RateRepositoryInterface $rateRepository,
        private readonly ViewRenderer $viewRenderer,
        private readonly AuditLogger $auditLogger,
        private readonly PropertyRatesConfig $ratesConfig,
        private readonly string $csvPath = 'public/data/prices.csv',
        private readonly ?string $cacheDir = null
    ) {
    }

    /**
     * GET /rates: Lists pricing tiers per property (1606, 1707) with seasonal timeline and gap warnings.
     *
     * @param array<string, mixed> $session
     */
    public function index(Request $request, array &$session): Response
    {
        $propertyId = $this->resolvePropertyId((string) $request->getQuery('property_id', '1606'));
        $year = $this->resolveYear((string) $request->getQuery('year', ''));

        $viewContainerHtml = $this->renderViewContainerHtml($propertyId, $year, session: $session);

        if ($request->isHtmx() && $request->getHeader('HX-Target') !== 'body') {
            $headerActionsHtml = $this->renderHeaderActionsHtml($propertyId, $year, oob: true, session: $session);
            return Response::html($viewContainerHtml . "\n" . $headerActionsHtml, 200);
        }

        $headerActionsHtml = $this->renderHeaderActionsHtml($propertyId, $year, oob: false, session: $session);

        $fullHtml = $this->viewRenderer->render('rates/index.php', [
            'propertyId' => $propertyId,
            'year' => $year,
            'headerActionsHtml' => $headerActionsHtml,
            'viewContainerHtml' => $viewContainerHtml,
            'csrfToken' => (string) ($session['csrf_token'] ?? ''),
            'currentUser' => $this->buildCurrentUser($session),
            'currentRoute' => '/rates',
        ]);

        return Response::html($fullHtml, 200);
    }

    /**
     * GET /rates/new: Renders modal dialog for creating a new seasonal tier.
     *
     * @param array<string, mixed> $session
     */
    public function newTier(Request $request, array &$session): Response
    {
        $propertyId = $this->resolvePropertyId((string) $request->getQuery('property_id', '1606'));
        $startDate = (string) $request->getQuery('start_date', '');
        $endDate = (string) $request->getQuery('end_date', '');
        $year = $this->resolveYear((string) $request->getQuery('year', ''));

        $modalHtml = $this->viewRenderer->renderPartial('rates/_modal_form.php', [
            'isEdit' => false,
            'rate' => [
                'property_id' => $propertyId,
                'season_name' => '',
                'start_date' => $startDate,
                'end_date' => $endDate,
                'price_per_night' => $this->ratesConfig->getDefaultNightlyRate($propertyId),
                'min_stay' => 2,
            ],
            'propertyId' => $propertyId,
            'year' => $year,
            'csrfToken' => (string) ($session['csrf_token'] ?? ''),
            'errorMessage' => null,
            'fieldErrors' => [],
        ]);

        return Response::html($modalHtml, 200);
    }

    /**
     * POST /rates: Validates and creates a new seasonal rate tier.
     *
     * @param array<string, mixed> $session
     */
    public function create(Request $request, array &$session): Response
    {
        $propertyId = $this->resolvePropertyId((string) $request->getPost('property_id', '1606'));
        $year = $this->resolveYear((string) $request->getPost('year', ''));
        $seasonName = trim((string) $request->getPost('season_name', ''));
        $startDate = trim((string) $request->getPost('start_date', ''));
        $endDate = trim((string) $request->getPost('end_date', ''));
        $pricePerNight = (float) $request->getPost('price_per_night', 0.0);
        $minStay = (int) $request->getPost('min_stay', 2);

        $rateData = [
            'property_id' => $propertyId,
            'season_name' => $seasonName,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'price_per_night' => $pricePerNight,
            'min_stay' => $minStay,
        ];

        // 1. Validate Form Fields
        $validation = $this->validateTierInputs($rateData);
        if ($validation['errorMessage'] !== null) {
            return $this->renderModalError(
                isEdit: false,
                rate: $rateData,
                propertyId: $propertyId,
                year: $year,
                errorMessage: $validation['errorMessage'],
                fieldErrors: $validation['fieldErrors'],
                session: $session
            );
        }

        // 2. Validate Interval Collision
        try {
            if ($this->rateRepository->hasOverlap($propertyId, $startDate, $endDate)) {
                return $this->renderModalError(
                    isEdit: false,
                    rate: $rateData,
                    propertyId: $propertyId,
                    year: $year,
                    errorMessage: "The interval {$startDate} to {$endDate} overlaps with an existing seasonal rate tier for Apartment {$propertyId}.",
                    fieldErrors: ['start_date' => 'Date range collides with an existing tier.', 'end_date' => 'Date range collides with an existing tier.'],
                    session: $session
                );
            }
        } catch (InvalidArgumentException $e) {
            return $this->renderModalError(
                isEdit: false,
                rate: $rateData,
                propertyId: $propertyId,
                year: $year,
                errorMessage: $e->getMessage(),
                fieldErrors: ['start_date' => $e->getMessage()],
                session: $session
            );
        }

        // 3. Persist New Seasonal Tier
        $currentUser = $this->buildCurrentUser($session);
        $newTier = new RateTier(
            propertyId: $propertyId,
            startDate: $startDate,
            endDate: $endDate,
            nightlyRateCop: $pricePerNight,
            minimumStay: $minStay,
            seasonName: $seasonName,
            createdBy: $currentUser['id']
        );
        $savedTier = $this->rateRepository->save($newTier, adminUserId: $currentUser['id']);
        $tierId = (int) $savedTier->id;
        $this->syncPricesCache();

        // 4. Audit Trail Recording
        $this->auditLogger->record(
            action: 'rate_tier_created',
            entityType: 'property_rates',
            entityId: (string) $tierId,
            before: null,
            after: array_merge($rateData, ['id' => $tierId]),
            adminUserId: $currentUser['id'],
            ipAddress: $request->getClientIp(),
            userAgent: (string) $request->getHeader('User-Agent', '')
        );

        // 5. Response: close modal and emit trigger for HTMX update
        $flashMessage = "Seasonal tier '{$seasonName}' created successfully.";
        $flashType = 'success';
        $session['rate_flash'] = ['message' => $flashMessage, 'type' => $flashType];

        $viewContainerHtml = $this->renderViewContainerHtml(
            $propertyId,
            $year,
            flashMessage: $flashMessage,
            flashType: $flashType,
            oob: true,
            session: $session
        );
        $headerActionsHtml = $this->renderHeaderActionsHtml(
            $propertyId,
            $year,
            oob: true,
            session: $session
        );

        $responseHtml = $viewContainerHtml . "\n" . $headerActionsHtml . "\n" . '<div id="modal-container" hx-swap-oob="innerHTML"></div>';

        return new Response(
            statusCode: 200,
            headers: [
                'Content-Type' => 'text/html; charset=UTF-8',
                'HX-Trigger' => 'rateUpdated',
            ],
            body: $responseHtml
        );
    }

    /**
     * GET /rates/{id}/edit: Renders modal dialog populated with existing seasonal tier data.
     *
     * @param array<string, mixed> $session
     */
    public function edit(Request $request, array &$session): Response
    {
        $id = (int) $request->getAttribute('id');
        $rateTier = $this->rateRepository->findById($id);

        if ($rateTier === null) {
            return Response::html('<div class="p-4 text-xs text-rose-600 font-semibold">Seasonal rate tier not found.</div>', 404);
        }

        $rate = $rateTier->toArray();
        $propertyId = (string) $rate['property_id'];
        $year = $this->resolveYear((string) substr((string) $rate['start_date'], 0, 4));

        $modalHtml = $this->viewRenderer->renderPartial('rates/_modal_form.php', [
            'isEdit' => true,
            'rate' => $rate,
            'propertyId' => $propertyId,
            'year' => $year,
            'csrfToken' => (string) ($session['csrf_token'] ?? ''),
            'errorMessage' => null,
            'fieldErrors' => [],
        ]);

        return Response::html($modalHtml, 200);
    }

    /**
     * POST /rates/{id}: Validates and updates an existing seasonal rate tier.
     *
     * @param array<string, mixed> $session
     */
    public function update(Request $request, array &$session): Response
    {
        $id = (int) $request->getAttribute('id');
        $existingTier = $this->rateRepository->findById($id);

        if ($existingTier === null) {
            return Response::html('<div class="p-4 text-xs text-rose-600 font-semibold">Seasonal rate tier not found.</div>', 404);
        }

        $existing = $existingTier->toArray();
        $propertyId = (string) $existing['property_id'];
        $year = $this->resolveYear((string) $request->getPost('year', (string) substr((string) $existing['start_date'], 0, 4)));
        $seasonName = trim((string) $request->getPost('season_name', ''));
        $startDate = trim((string) $request->getPost('start_date', ''));
        $endDate = trim((string) $request->getPost('end_date', ''));
        $pricePerNight = (float) $request->getPost('price_per_night', 0.0);
        $minStay = (int) $request->getPost('min_stay', 2);

        $rateData = [
            'id' => $id,
            'property_id' => $propertyId,
            'season_name' => $seasonName,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'price_per_night' => $pricePerNight,
            'min_stay' => $minStay,
        ];

        // 1. Validate Form Fields
        $validation = $this->validateTierInputs($rateData);
        if ($validation['errorMessage'] !== null) {
            return $this->renderModalError(
                isEdit: true,
                rate: $rateData,
                propertyId: $propertyId,
                year: $year,
                errorMessage: $validation['errorMessage'],
                fieldErrors: $validation['fieldErrors'],
                session: $session
            );
        }

        // 2. Validate Overlap Collision excluding self ($id)
        try {
            if ($this->rateRepository->hasOverlap($propertyId, $startDate, $endDate, excludeId: $id)) {
                return $this->renderModalError(
                    isEdit: true,
                    rate: $rateData,
                    propertyId: $propertyId,
                    year: $year,
                    errorMessage: "The interval {$startDate} to {$endDate} overlaps with another seasonal rate tier for Apartment {$propertyId}.",
                    fieldErrors: ['start_date' => 'Date range collides with an existing tier.', 'end_date' => 'Date range collides with an existing tier.'],
                    session: $session
                );
            }
        } catch (InvalidArgumentException $e) {
            return $this->renderModalError(
                isEdit: true,
                rate: $rateData,
                propertyId: $propertyId,
                year: $year,
                errorMessage: $e->getMessage(),
                fieldErrors: ['start_date' => $e->getMessage()],
                session: $session
            );
        }

        // 3. Persist Updates
        $updatedTier = new RateTier(
            propertyId: $propertyId,
            startDate: $startDate,
            endDate: $endDate,
            nightlyRateCop: $pricePerNight,
            minimumStay: $minStay,
            id: $id,
            seasonName: $seasonName,
            cleaningFeeCop: (float) ($existing['cleaning_fee'] ?? 0.0),
            createdBy: isset($existing['created_by']) ? (int) $existing['created_by'] : null
        );
        $this->rateRepository->save($updatedTier);
        $this->syncPricesCache();

        // 4. Audit Trail Recording
        $currentUser = $this->buildCurrentUser($session);
        $this->auditLogger->record(
            action: 'rate_tier_updated',
            entityType: 'property_rates',
            entityId: (string) $id,
            before: $existing,
            after: $rateData,
            adminUserId: $currentUser['id'],
            ipAddress: $request->getClientIp(),
            userAgent: (string) $request->getHeader('User-Agent', '')
        );

        // 5. Response: close modal and emit trigger for HTMX update
        $flashMessage = "Seasonal tier '{$seasonName}' updated successfully.";
        $flashType = 'success';
        $session['rate_flash'] = ['message' => $flashMessage, 'type' => $flashType];

        $viewContainerHtml = $this->renderViewContainerHtml(
            $propertyId,
            $year,
            flashMessage: $flashMessage,
            flashType: $flashType,
            oob: true,
            session: $session
        );
        $headerActionsHtml = $this->renderHeaderActionsHtml(
            $propertyId,
            $year,
            oob: true,
            session: $session
        );

        $responseHtml = $viewContainerHtml . "\n" . $headerActionsHtml . "\n" . '<div id="modal-container" hx-swap-oob="innerHTML"></div>';

        return new Response(
            statusCode: 200,
            headers: [
                'Content-Type' => 'text/html; charset=UTF-8',
                'HX-Trigger' => 'rateUpdated',
            ],
            body: $responseHtml
        );
    }

    /**
     * DELETE /rates/{id}: Deletes a seasonal rate tier, recording an immutable audit snapshot.
     *
     * @param array<string, mixed> $session
     */
    public function delete(Request $request, array &$session): Response
    {
        $id = (int) $request->getAttribute('id');
        $existingTier = $this->rateRepository->findById($id);

        if ($existingTier === null) {
            return Response::html('<div class="p-4 text-xs text-rose-600 font-semibold">Seasonal rate tier not found.</div>', 404);
        }

        $existing = $existingTier->toArray();
        $propertyId = (string) ($request->getQuery('property_id') ?? $existing['property_id']);
        $year = $this->resolveYear((string) ($request->getQuery('year') ?? substr((string) $existing['start_date'], 0, 4)));

        // 1. Audit Trail Recording before deletion
        $currentUser = $this->buildCurrentUser($session);
        $this->auditLogger->record(
            action: 'rate_tier_deleted',
            entityType: 'property_rates',
            entityId: (string) $id,
            before: $existing,
            after: null,
            adminUserId: $currentUser['id'],
            ipAddress: $request->getClientIp(),
            userAgent: (string) $request->getHeader('User-Agent', '')
        );

        // 2. Delete Record
        $this->rateRepository->delete($id);
        $this->syncPricesCache();

        // 3. Re-render View Container & Header Actions
        $flashMessage = "Seasonal tier '{$existing['season_name']}' deleted successfully. Dates will use property base pricing.";
        $flashType = 'success';
        $session['rate_flash'] = ['message' => $flashMessage, 'type' => $flashType];

        $viewContainerHtml = $this->renderViewContainerHtml(
            $propertyId,
            $year,
            flashMessage: $flashMessage,
            flashType: $flashType,
            oob: false,
            session: $session
        );
        $headerActionsHtml = $this->renderHeaderActionsHtml(
            $propertyId,
            $year,
            oob: true,
            session: $session
        );

        return new Response(
            statusCode: 200,
            headers: [
                'Content-Type' => 'text/html; charset=UTF-8',
                'HX-Trigger' => 'rateUpdated',
            ],
            body: $viewContainerHtml . "\n" . $headerActionsHtml
        );
    }

    /**
     * POST /rates/seed-from-csv: Triggers import from CSV into property_rates.
     *
     * @param array<string, mixed> $session
     */
    public function seedFromCsv(Request $request, array &$session): Response
    {
        $propertyId = $this->resolvePropertyId((string) $request->getPost('property_id', '1606'));
        $year = $this->resolveYear((string) $request->getPost('year', ''));

        $currentUser = $this->buildCurrentUser($session);

        // Resolve absolute or relative CSV path
        $csvFullPath = str_starts_with($this->csvPath, '/')
            ? $this->csvPath
            : (PathResolver::resolveIfExists($this->csvPath) ?? dirname(__DIR__, 3) . '/' . ltrim($this->csvPath, '/'));

        try {
            $seededCount = $this->rateRepository->seedFromCsv($csvFullPath, $currentUser['id']);
            $this->syncPricesCache();

            $this->auditLogger->record(
                action: 'rate_tiers_seeded',
                entityType: 'property_rates',
                entityId: 'csv_seed',
                before: null,
                after: [
                    'seeded_count' => $seededCount,
                    'csv_path' => $this->csvPath,
                ],
                adminUserId: $currentUser['id'],
                ipAddress: $request->getClientIp(),
                userAgent: (string) $request->getHeader('User-Agent', '')
            );

            $flashMessage = $seededCount > 0
                ? "Successfully imported {$seededCount} seasonal rate tiers from prices.csv."
                : "No new rate tiers imported (all CSV tiers already exist or overlap with active tiers).";
            $flashType = 'success';
        } catch (Throwable $e) {
            $flashMessage = "CSV seeding failed: {$e->getMessage()}";
            $flashType = 'error';
        }

        $session['rate_flash'] = ['message' => $flashMessage, 'type' => $flashType];

        $viewContainerHtml = $this->renderViewContainerHtml(
            $propertyId,
            $year,
            flashMessage: $flashMessage,
            flashType: $flashType,
            oob: false,
            session: $session
        );
        $headerActionsHtml = $this->renderHeaderActionsHtml(
            $propertyId,
            $year,
            oob: true,
            session: $session
        );

        return new Response(
            statusCode: 200,
            headers: [
                'Content-Type' => 'text/html; charset=UTF-8',
                'HX-Trigger' => 'rateUpdated',
            ],
            body: $viewContainerHtml . "\n" . $headerActionsHtml
        );
    }

    /**
     * Validates input fields for a seasonal tier.
     *
     * @param array<string, mixed> $data
     * @return array{errorMessage: string|null, fieldErrors: array<string, string>}
     */
    private function validateTierInputs(array $data): array
    {
        $fieldErrors = [];

        $seasonName = trim((string) ($data['season_name'] ?? ''));
        if ($seasonName === '') {
            $fieldErrors['season_name'] = 'Season name is required.';
        }

        $startDate = (string) ($data['start_date'] ?? '');
        $endDate = (string) ($data['end_date'] ?? '');

        if (!$this->isValidDate($startDate)) {
            $fieldErrors['start_date'] = 'Valid start date (YYYY-MM-DD) is required.';
        }

        if (!$this->isValidDate($endDate)) {
            $fieldErrors['end_date'] = 'Valid end date (YYYY-MM-DD) is required.';
        }

        if (empty($fieldErrors['start_date']) && empty($fieldErrors['end_date'])) {
            if ($startDate >= $endDate) {
                $fieldErrors['start_date'] = 'Start date must be strictly before end date.';
            }
        }

        $price = (float) ($data['price_per_night'] ?? 0.0);
        if ($price <= 0.0) {
            $fieldErrors['price_per_night'] = 'Nightly rate must be greater than zero.';
        }

        $minStay = (int) ($data['min_stay'] ?? 0);
        if ($minStay < 1) {
            $fieldErrors['min_stay'] = 'Minimum stay must be at least 1 night.';
        }

        $errorMessage = !empty($fieldErrors) ? reset($fieldErrors) : null;

        return [
            'errorMessage' => $errorMessage,
            'fieldErrors' => $fieldErrors,
        ];
    }

    private function isValidDate(string $date): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }
        $d = DateTimeImmutable::createFromFormat('Y-m-d', $date);
        return $d !== false && $d->format('Y-m-d') === $date;
    }

    /**
     * @param array<string, mixed> $rate
     * @param array<string, string> $fieldErrors
     * @param array<string, mixed> $session
     */
    private function renderModalError(
        bool $isEdit,
        array $rate,
        string $propertyId,
        int $year,
        string $errorMessage,
        array $fieldErrors,
        array $session
    ): Response {
        $modalHtml = $this->viewRenderer->renderPartial('rates/_modal_form.php', [
            'isEdit' => $isEdit,
            'rate' => $rate,
            'propertyId' => $propertyId,
            'year' => $year,
            'csrfToken' => (string) ($session['csrf_token'] ?? ''),
            'errorMessage' => $errorMessage,
            'fieldErrors' => $fieldErrors,
        ]);

        return new Response(
            statusCode: 422,
            headers: [
                'Content-Type' => 'text/html; charset=UTF-8',
                'HX-Retarget' => '#modal-container',
                'HX-Reswap' => 'innerHTML',
            ],
            body: $modalHtml
        );
    }

    /**
     * @param array<string, mixed> $session
     */
    private function renderHeaderActionsHtml(
        string $propertyId,
        int $year,
        bool $oob = false,
        array $session = []
    ): string {
        $csrfToken = (string) ($session['csrf_token'] ?? '');

        return $this->viewRenderer->renderPartial('rates/_header_actions.php', [
            'propertyId' => $propertyId,
            'year' => $year,
            'csrfToken' => $csrfToken,
            'oob' => $oob,
        ]);
    }

    /**
     * @param array<string, mixed> $session
     */
    private function renderViewContainerHtml(
        string $propertyId,
        int $year,
        ?string $flashMessage = null,
        ?string $flashType = null,
        bool $oob = false,
        array &$session = []
    ): string {
        if ($flashMessage === null && isset($session['rate_flash']) && is_array($session['rate_flash'])) {
            $flashMessage = (string) ($session['rate_flash']['message'] ?? '');
            $flashType = (string) ($session['rate_flash']['type'] ?? 'success');
            unset($session['rate_flash']);
        }

        $contentHtml = $this->renderContentHtml(
            $propertyId,
            $year,
            flashMessage: $flashMessage,
            flashType: $flashType,
            session: $session
        );
        $csrfToken = (string) ($session['csrf_token'] ?? '');

        return $this->viewRenderer->renderPartial('rates/_rates_view.php', [
            'propertyId' => $propertyId,
            'year' => $year,
            'contentHtml' => $contentHtml,
            'csrfToken' => $csrfToken,
            'oob' => $oob,
        ]);
    }

    /**
     * @param array<string, mixed> $session
     */
    private function renderContentHtml(
        string $propertyId,
        int $year,
        ?string $flashMessage = null,
        ?string $flashType = null,
        array $session = []
    ): string {
        $tiers = $this->rateRepository->getRatesForProperty($propertyId, $year);
        $gaps = $this->rateRepository->detectGaps($propertyId, $year);
        $fallbackRate = $this->ratesConfig->getDefaultNightlyRate($propertyId);
        $csrfToken = (string) ($session['csrf_token'] ?? '');

        $timelineHtml = $this->viewRenderer->renderPartial('rates/_timeline.php', [
            'propertyId' => $propertyId,
            'year' => $year,
            'tiers' => $tiers,
            'gaps' => $gaps,
            'fallbackRate' => $fallbackRate,
        ]);

        $tableHtml = $this->viewRenderer->renderPartial('rates/_rates_table.php', [
            'propertyId' => $propertyId,
            'year' => $year,
            'tiers' => $tiers,
            'csrfToken' => $csrfToken,
        ]);

        return $this->viewRenderer->renderPartial('rates/_content.php', [
            'propertyId' => $propertyId,
            'year' => $year,
            'flashMessage' => $flashMessage,
            'flashType' => $flashType,
            'timelineHtml' => $timelineHtml,
            'tableHtml' => $tableHtml,
        ]);
    }

    private function resolvePropertyId(string $propertyId): string
    {
        return $this->ratesConfig->isValidProperty($propertyId) ? $propertyId : '1606';
    }

    private function resolveYear(string $year): int
    {
        $val = (int) $year;
        return ($val >= 2020 && $val <= 2040) ? $val : (int) date('Y');
    }

    /**
     * Atomically writes active seasonal rate tiers to prices.json in the shared cache directory.
     */
    private function syncPricesCache(): void
    {
        try {
            $targetCacheDir = $this->cacheDir ?? PathResolver::resolveDirectory('cache');
            if (!is_dir($targetCacheDir)) {
                mkdir($targetCacheDir, 0755, true);
            }

            $prices = [];
            foreach (['1606', '1707'] as $propId) {
                $tiers = $this->rateRepository->getRatesForProperty($propId);
                foreach ($tiers as $tier) {
                    $prices[] = [
                        'property_id' => $tier->propertyId,
                        'start_date' => $tier->startDate,
                        'end_date' => $tier->endDate,
                        'nightly_rate_cop' => (int) round($tier->nightlyRateCop),
                        'minimum_stay' => $tier->minimumStay,
                        'season_name' => $tier->seasonName,
                    ];
                }
            }

            $json = json_encode($prices, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($json === false) {
                return;
            }

            $targetFile = rtrim($targetCacheDir, '/\\') . '/prices.json';
            $tmpFile = $targetFile . '.tmp.' . uniqid('', true);
            if (file_put_contents($tmpFile, $json, LOCK_EX) !== false) {
                rename($tmpFile, $targetFile);
            }
        } catch (Throwable $e) {
            error_log('[RateController Cache Sync Warning] ' . $e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $session
     * @return array{id: int|null, name: string, email: string, role: string}
     */
    private function buildCurrentUser(array $session): array
    {
        return [
            'id' => isset($session['admin_user_id']) ? (int) $session['admin_user_id'] : null,
            'name' => (string) ($session['admin_user_name'] ?? 'Admin'),
            'email' => (string) ($session['admin_user_email'] ?? ''),
            'role' => (string) ($session['admin_user_role'] ?? 'admin'),
        ];
    }
}
