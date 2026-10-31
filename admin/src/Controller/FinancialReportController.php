<?php

declare(strict_types=1);

namespace OceanViewFlats\Admin\Controller;

use DateTimeImmutable;
use DateTimeZone;
use OceanViewFlats\Admin\Audit\AuditLogger;
use OceanViewFlats\Admin\Http\Request;
use OceanViewFlats\Admin\Http\Response;
use OceanViewFlats\Admin\Views\ViewRenderer;
use OceanViewFlats\Domain\Finance\ColombianStatutoryCalendar;
use OceanViewFlats\Domain\Finance\ExpenseCategory;
use OceanViewFlats\Domain\Finance\FinancialReportEngine;
use OceanViewFlats\Domain\Finance\FinancialReportEngineInterface;
use OceanViewFlats\Domain\Finance\FinancialReportFilter;
use OceanViewFlats\Domain\Finance\InMemoryParafiscalSettlementRepository;
use OceanViewFlats\Domain\Finance\OperationalExpense;
use OceanViewFlats\Domain\Finance\OperationalExpenseRepositoryInterface;
use OceanViewFlats\Domain\Finance\ParafiscalPeriod;
use OceanViewFlats\Domain\Finance\ParafiscalSettlement;
use OceanViewFlats\Domain\Finance\ParafiscalSettlementRepositoryInterface;
use OceanViewFlats\Domain\Quote\PropertyRatesConfig;
use OceanViewFlats\Domain\Reservation\InMemoryMaintenanceBlockSource;
use OceanViewFlats\Domain\Reservation\MaintenanceBlockSourceInterface;
use OceanViewFlats\Domain\Reservation\ReservationRepositoryInterface;

/**
 * Administrative controller for the Financial Report workspace and Operational Expense Ledger CRUD.
 *
 * Enforces strict RBAC restricted to 'admin' and 'superadmin' roles.
 */
final class FinancialReportController
{
    public const AUTHORIZED_ROLES = ['admin', 'superadmin'];

    private readonly FinancialReportEngineInterface $reportEngine;
    private readonly ParafiscalSettlementRepositoryInterface $parafiscalRepository;

    public function __construct(
        private readonly OperationalExpenseRepositoryInterface $expenseRepository,
        private readonly ReservationRepositoryInterface $reservationRepository,
        private readonly ViewRenderer $viewRenderer,
        private readonly AuditLogger $auditLogger,
        private readonly ?DateTimeImmutable $now = null,
        ?FinancialReportEngineInterface $reportEngine = null,
        ?MaintenanceBlockSourceInterface $maintenanceBlockSource = null,
        ?PropertyRatesConfig $ratesConfig = null,
        ?ParafiscalSettlementRepositoryInterface $parafiscalRepository = null
    ) {
        $this->parafiscalRepository = $parafiscalRepository ?? new InMemoryParafiscalSettlementRepository();
        $this->reportEngine = $reportEngine ?? new FinancialReportEngine(
            reservationRepository: $this->reservationRepository,
            maintenanceBlockSource: $maintenanceBlockSource ?? new InMemoryMaintenanceBlockSource(),
            expenseRepository: $this->expenseRepository,
            ratesConfig: $ratesConfig,
            parafiscalRepository: $this->parafiscalRepository
        );
    }

    /**
     * GET /financial-report: Renders full page or HTMX report content partial.
     *
     * @param array<string, mixed> $session
     */
    public function index(Request $request, array &$session): Response
    {
        $authResponse = $this->authorizeOrRespond($request, $session, $currentUser);
        if ($authResponse !== null) {
            return $authResponse;
        }

        $filters = $this->resolveFiltersFromRequest($request);
        $reportContentHtml = $this->renderReportContentHtml($filters, $session, oob: false);

        if ($request->isHtmx() && $request->getHeader('HX-Target') !== 'body') {
            $headerActionsHtml = $this->renderHeaderActionsHtml($filters, oob: true);
            return Response::html($reportContentHtml . "\n" . $headerActionsHtml, 200);
        }

        $headerActionsHtml = $this->renderHeaderActionsHtml($filters, oob: false);

        $fullHtml = $this->viewRenderer->render('financial_report/index.php', [
            'filters' => $filters,
            'headerActionsHtml' => $headerActionsHtml,
            'reportContentHtml' => $reportContentHtml,
            'csrfToken' => (string) ($session['csrf_token'] ?? ''),
            'currentUser' => $currentUser,
            'currentRoute' => '/financial-report',
            'title' => 'Financial Report - Ocean View Flats Admin',
        ]);

        return Response::html($fullHtml, 200);
    }

    /**
     * GET /financial-report/expenses/new: Renders creation modal (#modal-container).
     * Supports ?duplicate_from={id} and ?reservation_uid={uid}.
     *
     * @param array<string, mixed> $session
     */
    public function newExpense(Request $request, array &$session): Response
    {
        $authResponse = $this->authorizeOrRespond($request, $session);
        if ($authResponse !== null) {
            return $authResponse;
        }

        $filters = $this->resolveFiltersFromRequest($request);
        $todayBogota = $this->todayInBogota();
        $defaultProperty = in_array($filters['property_id'], ['1606', '1707'], true)
            ? $filters['property_id']
            : '1606';

        $form = [
            'property_id' => $defaultProperty,
            'category' => ExpenseCategory::CLEANING->value,
            'title' => '',
            'amount' => '',
            'expense_date' => $todayBogota,
            'vendor_name' => '',
            'reference_code' => '',
            'reservation_uid' => '',
            'notes' => '',
        ];
        $isDuplicate = false;

        $duplicateFromRaw = trim((string) $request->getQuery('duplicate_from', ''));
        if ($duplicateFromRaw !== '') {
            $sourceExpense = $this->findExpenseByIdOrUid($duplicateFromRaw);
            if ($sourceExpense === null) {
                return Response::html(
                    '<div class="p-4 text-xs text-rose-600 font-semibold">Operational expense to duplicate was not found.</div>',
                    404
                );
            }

            $isDuplicate = true;
            $form = [
                'property_id' => $sourceExpense->propertyId,
                'category' => $sourceExpense->category->value,
                'title' => $sourceExpense->title,
                'amount' => $this->formatInputAmount($sourceExpense->amount),
                'expense_date' => $todayBogota,
                'vendor_name' => (string) ($sourceExpense->vendorName ?? ''),
                'reference_code' => '',
                'reservation_uid' => '',
                'notes' => (string) ($sourceExpense->notes ?? ''),
            ];
        }

        $reservationUidQuery = trim((string) $request->getQuery('reservation_uid', ''));
        if ($reservationUidQuery !== '') {
            $form['reservation_uid'] = $reservationUidQuery;
            $linkedReservation = $this->reservationRepository->findByUid($reservationUidQuery);
            if ($linkedReservation !== null && in_array($linkedReservation->propertyId, ['1606', '1707'], true)) {
                $form['property_id'] = $linkedReservation->propertyId;
            }
        }

        $modalHtml = $this->viewRenderer->renderPartial('financial_report/_expense_modal.php', [
            'isEdit' => false,
            'isDuplicate' => $isDuplicate,
            'expenseId' => null,
            'expenseUid' => null,
            'form' => $form,
            'errors' => [],
            'fieldErrors' => [],
            'filters' => $filters,
            'csrfToken' => (string) ($session['csrf_token'] ?? ''),
        ]);

        return Response::html($modalHtml, 200);
    }

    /**
     * POST /financial-report/expenses: Validates and persists a new Operational Expense.
     *
     * @param array<string, mixed> $session
     */
    public function createExpense(Request $request, array &$session): Response
    {
        $authResponse = $this->authorizeOrRespond($request, $session, $currentUser);
        if ($authResponse !== null) {
            return $authResponse;
        }

        if (!$this->verifyCsrf($request, $session)) {
            return Response::html('<div class="p-4 text-xs text-rose-600 font-semibold">Invalid or expired CSRF token.</div>', 403);
        }

        $form = $this->extractExpenseForm($request);
        $filters = $this->resolveFiltersFromMutationRequest($request);

        $validation = $this->validateExpenseForm($form);
        if (!empty($validation['errors'])) {
            return $this->renderModalValidationError(
                isEdit: false,
                expenseId: null,
                expenseUid: null,
                form: $form,
                errors: $validation['errors'],
                fieldErrors: $validation['fieldErrors'],
                filters: $filters,
                session: $session
            );
        }

        $adminUserId = (int) $session['admin_user_id'];
        $adminUserName = (string) ($currentUser['name'] ?? 'Admin');

        $expense = new OperationalExpense(
            propertyId: $form['property_id'],
            category: $form['category'],
            title: $form['title'],
            amount: (float) $form['amount'],
            expenseDate: $form['expense_date'],
            vendorName: $form['vendor_name'],
            referenceCode: $form['reference_code'],
            reservationUid: $form['reservation_uid'],
            notes: $form['notes'],
            createdBy: $adminUserId,
            createdByName: $adminUserName
        );

        $saved = $this->expenseRepository->save($expense);

        $this->auditLogger->record(
            action: 'operational_expense_created',
            entityType: 'operational_expense',
            entityId: $saved->expenseUid,
            before: null,
            after: $saved->toArray(),
            adminUserId: $adminUserId,
            ipAddress: $request->getClientIp(),
            userAgent: (string) $request->getHeader('User-Agent', '')
        );

        $flashMessage = sprintf(
            'Operational expense "%s" (%s) recorded successfully.',
            $saved->title,
            $saved->expenseUid
        );

        return $this->buildMutationSuccessResponse($request, $filters, $session, $flashMessage, 'expenseSaved');
    }

    /**
     * GET /financial-report/expenses/{id}/edit: Renders edit modal for an existing Operational Expense.
     *
     * @param array<string, mixed> $session
     */
    public function editExpense(Request $request, array &$session): Response
    {
        $authResponse = $this->authorizeOrRespond($request, $session);
        if ($authResponse !== null) {
            return $authResponse;
        }

        $rawId = (string) ($request->getAttribute('id') ?? '');
        $expense = $this->findExpenseByIdOrUid($rawId);
        if ($expense === null) {
            return Response::html(
                '<div class="p-4 text-xs text-rose-600 font-semibold">Operational expense not found.</div>',
                404
            );
        }

        $filters = $this->resolveFiltersFromRequest($request);
        $form = [
            'property_id' => $expense->propertyId,
            'category' => $expense->category->value,
            'title' => $expense->title,
            'amount' => $this->formatInputAmount($expense->amount),
            'expense_date' => $expense->expenseDate,
            'vendor_name' => (string) ($expense->vendorName ?? ''),
            'reference_code' => (string) ($expense->referenceCode ?? ''),
            'reservation_uid' => (string) ($expense->reservationUid ?? ''),
            'notes' => (string) ($expense->notes ?? ''),
        ];

        $modalHtml = $this->viewRenderer->renderPartial('financial_report/_expense_modal.php', [
            'isEdit' => true,
            'isDuplicate' => false,
            'expenseId' => $expense->id,
            'expenseUid' => $expense->expenseUid,
            'form' => $form,
            'errors' => [],
            'fieldErrors' => [],
            'filters' => $filters,
            'csrfToken' => (string) ($session['csrf_token'] ?? ''),
        ]);

        return Response::html($modalHtml, 200);
    }

    /**
     * POST /financial-report/expenses/{id}: Validates and updates an existing Operational Expense.
     *
     * @param array<string, mixed> $session
     */
    public function updateExpense(Request $request, array &$session): Response
    {
        $authResponse = $this->authorizeOrRespond($request, $session);
        if ($authResponse !== null) {
            return $authResponse;
        }

        if (!$this->verifyCsrf($request, $session)) {
            return Response::html('<div class="p-4 text-xs text-rose-600 font-semibold">Invalid or expired CSRF token.</div>', 403);
        }

        $rawId = (string) ($request->getAttribute('id') ?? '');
        $existing = $this->findExpenseByIdOrUid($rawId);
        if ($existing === null) {
            return Response::html(
                '<div class="p-4 text-xs text-rose-600 font-semibold">Operational expense not found.</div>',
                404
            );
        }

        $form = $this->extractExpenseForm($request);
        $filters = $this->resolveFiltersFromMutationRequest($request);

        $validation = $this->validateExpenseForm($form);
        if (!empty($validation['errors'])) {
            return $this->renderModalValidationError(
                isEdit: true,
                expenseId: $existing->id,
                expenseUid: $existing->expenseUid,
                form: $form,
                errors: $validation['errors'],
                fieldErrors: $validation['fieldErrors'],
                filters: $filters,
                session: $session
            );
        }

        $beforePayload = $existing->toArray();

        $updated = new OperationalExpense(
            propertyId: $form['property_id'],
            category: $form['category'],
            title: $form['title'],
            amount: (float) $form['amount'],
            expenseDate: $form['expense_date'],
            expenseUid: $existing->expenseUid,
            id: $existing->id,
            vendorName: $form['vendor_name'],
            referenceCode: $form['reference_code'],
            reservationUid: $form['reservation_uid'],
            notes: $form['notes'],
            createdBy: $existing->createdBy,
            createdByName: $existing->createdByName,
            createdAt: $existing->createdAt
        );

        $saved = $this->expenseRepository->save($updated);
        $adminUserId = (int) $session['admin_user_id'];

        $this->auditLogger->record(
            action: 'operational_expense_updated',
            entityType: 'operational_expense',
            entityId: $saved->expenseUid,
            before: $beforePayload,
            after: $saved->toArray(),
            adminUserId: $adminUserId,
            ipAddress: $request->getClientIp(),
            userAgent: (string) $request->getHeader('User-Agent', '')
        );

        // Two-way synchronization: if this OperationalExpense is linked to a ParafiscalSettlement, keep it synchronized
        if ($existing->id !== null) {
            $linkedSettlement = $this->parafiscalRepository->findByOperationalExpenseId($existing->id);
            if ($linkedSettlement !== null) {
                $beforeSettlementPayload = $linkedSettlement->toArray();
                $syncedSettlement = new ParafiscalSettlement(
                    propertyId: $linkedSettlement->propertyId,
                    periodKey: $linkedSettlement->periodKey,
                    periodStart: $linkedSettlement->periodStart,
                    periodEnd: $linkedSettlement->periodEnd,
                    dueDate: $linkedSettlement->dueDate,
                    rntNumber: $linkedSettlement->rntNumber,
                    status: $linkedSettlement->status,
                    settledTaxableBase: $linkedSettlement->settledTaxableBase,
                    settledCalculatedAmount: $linkedSettlement->settledCalculatedAmount,
                    settledAmount: round($saved->amount, 0),
                    paymentDate: $saved->expenseDate,
                    referenceCode: $saved->referenceCode,
                    notes: $saved->notes,
                    operationalExpenseId: $saved->id,
                    settledBy: $linkedSettlement->settledBy ?? $adminUserId,
                    settledByName: $linkedSettlement->settledByName,
                    settledAt: $linkedSettlement->settledAt ?? ($this->now ?? new DateTimeImmutable('now', new DateTimeZone('America/Bogota'))),
                    settlementUid: $linkedSettlement->settlementUid,
                    id: $linkedSettlement->id,
                    createdAt: $linkedSettlement->createdAt
                );
                $savedSettlement = $this->parafiscalRepository->save($syncedSettlement);

                $this->auditLogger->record(
                    action: 'parafiscal_updated',
                    entityType: 'parafiscal_settlement',
                    entityId: $savedSettlement->settlementUid,
                    before: $beforeSettlementPayload,
                    after: $savedSettlement->toArray(),
                    adminUserId: $adminUserId,
                    ipAddress: $request->getClientIp(),
                    userAgent: (string) $request->getHeader('User-Agent', '')
                );
            }
        }

        $flashMessage = sprintf(
            'Operational expense "%s" (%s) updated successfully.',
            $saved->title,
            $saved->expenseUid
        );

        return $this->buildMutationSuccessResponse($request, $filters, $session, $flashMessage, 'expenseSaved');
    }

    /**
     * DELETE /financial-report/expenses/{id} and POST /financial-report/expenses/{id}/delete:
     * Deletes an Operational Expense with CSRF verification and Audit Log recording.
     *
     * @param array<string, mixed> $session
     */
    public function deleteExpense(Request $request, array &$session): Response
    {
        $authResponse = $this->authorizeOrRespond($request, $session);
        if ($authResponse !== null) {
            return $authResponse;
        }

        if (!$this->verifyCsrf($request, $session)) {
            return Response::html('<div class="p-4 text-xs text-rose-600 font-semibold">Invalid or expired CSRF token.</div>', 403);
        }

        $rawId = (string) ($request->getAttribute('id') ?? '');
        $existing = $this->findExpenseByIdOrUid($rawId);
        if ($existing === null || $existing->id === null) {
            return Response::html(
                '<div class="p-4 text-xs text-rose-600 font-semibold">Operational expense not found.</div>',
                404
            );
        }

        $linkedSettlement = $this->parafiscalRepository->findByOperationalExpenseId($existing->id);
        $beforePayload = $existing->toArray();
        $this->expenseRepository->delete($existing->id);

        $adminUserId = (int) $session['admin_user_id'];

        $this->auditLogger->record(
            action: 'operational_expense_deleted',
            entityType: 'operational_expense',
            entityId: $existing->expenseUid,
            before: $beforePayload,
            after: null,
            adminUserId: $adminUserId,
            ipAddress: $request->getClientIp(),
            userAgent: (string) $request->getHeader('User-Agent', '')
        );

        // Two-way synchronization: deleting a FONTUR-linked OperationalExpense reverts its ParafiscalSettlement to pending
        if ($linkedSettlement !== null) {
            $beforeSettlementPayload = $linkedSettlement->toArray();
            $revertedSettlement = new ParafiscalSettlement(
                propertyId: $linkedSettlement->propertyId,
                periodKey: $linkedSettlement->periodKey,
                periodStart: $linkedSettlement->periodStart,
                periodEnd: $linkedSettlement->periodEnd,
                dueDate: $linkedSettlement->dueDate,
                rntNumber: $linkedSettlement->rntNumber,
                status: ParafiscalSettlement::STATUS_PENDING,
                settledTaxableBase: null,
                settledCalculatedAmount: null,
                settledAmount: null,
                paymentDate: null,
                referenceCode: null,
                notes: null,
                operationalExpenseId: null,
                settledBy: null,
                settledByName: null,
                settledAt: null,
                settlementUid: $linkedSettlement->settlementUid,
                id: $linkedSettlement->id,
                createdAt: $linkedSettlement->createdAt
            );
            $savedReverted = $this->parafiscalRepository->save($revertedSettlement);

            $this->auditLogger->record(
                action: 'parafiscal_reverted',
                entityType: 'parafiscal_settlement',
                entityId: $savedReverted->settlementUid,
                before: $beforeSettlementPayload,
                after: $savedReverted->toArray(),
                adminUserId: $adminUserId,
                ipAddress: $request->getClientIp(),
                userAgent: (string) $request->getHeader('User-Agent', '')
            );
        }

        $filters = $this->resolveFiltersFromMutationRequest($request);
        $flashMessage = sprintf(
            'Operational expense "%s" (%s) deleted.',
            $existing->title,
            $existing->expenseUid
        );

        $reportContentHtml = $this->renderReportContentHtml($filters, $session, oob: false, flashMessage: $flashMessage);
        $headerActionsHtml = $this->renderHeaderActionsHtml($filters, oob: true);

        if ($request->isHtmx()) {
            return new Response(
                statusCode: 200,
                headers: [
                    'Content-Type' => 'text/html; charset=UTF-8',
                    'HX-Trigger' => 'expenseDeleted',
                ],
                body: $reportContentHtml . "\n" . $headerActionsHtml
            );
        }

        return Response::redirect('/financial-report?' . http_build_query([
            'property_id' => $filters['property_id'],
            'category' => $filters['category'],
            'tab' => 'expenses',
        ]));
    }

    /**
     * GET /financial-report/parafiscal/{periodKey}/{propertyId}/modal:
     * Renders the HTMX confirmation/edit modal (#modal-container) for a quarterly Parafiscal Period.
     *
     * @param array<string, mixed> $session
     */
    public function parafiscalModal(Request $request, array &$session): Response
    {
        $authResponse = $this->authorizeOrRespond($request, $session);
        if ($authResponse !== null) {
            return $authResponse;
        }

        $periodKey = strtoupper(trim((string) ($request->getAttribute('periodKey') ?? '')));
        $propertyId = trim((string) ($request->getAttribute('propertyId') ?? ''));

        if (!in_array($propertyId, ParafiscalSettlement::VALID_PROPERTIES, true) || !preg_match('/^\d{4}-Q[1-4]$/', $periodKey)) {
            return Response::html(
                '<div class="p-4 text-xs text-rose-600 font-semibold">Invalid Parafiscal period or property identifier.</div>',
                422
            );
        }

        $parafiscalPeriod = $this->reportEngine->evaluateSingleParafiscalPeriod($periodKey, $propertyId, $this->now);
        $filters = $this->resolveFiltersFromRequest($request);
        $todayBogota = $this->todayInBogota();

        $settlement = $parafiscalPeriod->settlement;
        $isEdit = $settlement !== null && $settlement->isSettled();

        $defaultSettledAmount = ($settlement !== null && $settlement->isSettled() && $settlement->settledAmount !== null)
            ? $this->formatInputAmount(round($settlement->settledAmount, 0))
            : $this->formatInputAmount(round($parafiscalPeriod->calculatedContribution, 0));

        $defaultPaymentDate = ($settlement !== null && $settlement->isSettled() && $settlement->paymentDate !== null && $settlement->paymentDate !== '')
            ? $settlement->paymentDate
            : $todayBogota;

        $form = [
            'settled_amount' => $defaultSettledAmount,
            'payment_date' => $defaultPaymentDate,
            'reference_code' => $settlement !== null ? (string) ($settlement->referenceCode ?? '') : '',
            'notes' => $settlement !== null ? (string) ($settlement->notes ?? '') : '',
        ];

        $modalHtml = $this->viewRenderer->renderPartial('financial_report/_parafiscal_modal.php', [
            'parafiscalPeriod' => $parafiscalPeriod,
            'isEdit' => $isEdit,
            'form' => $form,
            'errors' => [],
            'fieldErrors' => [],
            'filters' => $filters,
            'csrfToken' => (string) ($session['csrf_token'] ?? ''),
        ]);

        return Response::html($modalHtml, 200);
    }

    /**
     * POST /financial-report/parafiscal/{periodKey}/{propertyId}/settle:
     * Validates and settles/updates a quarterly FONTUR Parafiscal Contribution, synchronizing
     * its 1-to-1 taxes_insurance Operational Expense and recording Audit Log transitions.
     *
     * @param array<string, mixed> $session
     */
    public function settleParafiscal(Request $request, array &$session): Response
    {
        $authResponse = $this->authorizeOrRespond($request, $session, $currentUser);
        if ($authResponse !== null) {
            return $authResponse;
        }

        if (!$this->verifyCsrf($request, $session)) {
            return Response::html('<div class="p-4 text-xs text-rose-600 font-semibold">Invalid or expired CSRF token.</div>', 403);
        }

        $periodKey = strtoupper(trim((string) ($request->getAttribute('periodKey') ?? '')));
        $propertyId = trim((string) ($request->getAttribute('propertyId') ?? ''));

        if (!in_array($propertyId, ParafiscalSettlement::VALID_PROPERTIES, true) || !preg_match('/^\d{4}-Q[1-4]$/', $periodKey)) {
            return Response::html(
                '<div class="p-4 text-xs text-rose-600 font-semibold">Invalid Parafiscal period or property identifier.</div>',
                422
            );
        }

        $parafiscalPeriod = $this->reportEngine->evaluateSingleParafiscalPeriod($periodKey, $propertyId, $this->now);
        $existingSettlement = $parafiscalPeriod->settlement;
        $isEdit = $existingSettlement !== null && $existingSettlement->isSettled();

        $form = $this->extractParafiscalForm($request);
        $filters = $this->resolveFiltersFromMutationRequest($request);

        $validation = $this->validateParafiscalForm($form);
        if (!empty($validation['errors'])) {
            return $this->renderParafiscalModalValidationError(
                parafiscalPeriod: $parafiscalPeriod,
                isEdit: $isEdit,
                form: $form,
                errors: $validation['errors'],
                fieldErrors: $validation['fieldErrors'],
                filters: $filters,
                session: $session
            );
        }

        $adminUserId = (int) $session['admin_user_id'];
        $adminUserName = (string) ($currentUser['name'] ?? 'Admin');
        $settledAmount = round((float) $form['settled_amount'], 0);
        $paymentDate = trim($form['payment_date']);
        $referenceCode = trim($form['reference_code']);
        $notes = trim($form['notes']);
        $rntNumber = $parafiscalPeriod->rntNumber;
        $expenseTitle = sprintf('Contribución Parafiscal FONTUR %s (RNT #%s)', $periodKey, $rntNumber);

        $linkedExpenseId = $existingSettlement?->operationalExpenseId;
        $existingLinkedExpense = $linkedExpenseId !== null
            ? $this->expenseRepository->findById($linkedExpenseId)
            : null;

        if ($settledAmount >= 1.0) {
            if ($existingLinkedExpense !== null) {
                $beforeExpensePayload = $existingLinkedExpense->toArray();
                $updatedExpense = new OperationalExpense(
                    propertyId: $propertyId,
                    category: ExpenseCategory::TAXES_INSURANCE,
                    title: $expenseTitle,
                    amount: $settledAmount,
                    expenseDate: $paymentDate,
                    expenseUid: $existingLinkedExpense->expenseUid,
                    id: $existingLinkedExpense->id,
                    vendorName: 'FONTUR',
                    referenceCode: $referenceCode !== '' ? $referenceCode : null,
                    reservationUid: null,
                    notes: $notes !== '' ? $notes : null,
                    createdBy: $existingLinkedExpense->createdBy,
                    createdByName: $existingLinkedExpense->createdByName,
                    createdAt: $existingLinkedExpense->createdAt
                );
                $savedExpense = $this->expenseRepository->save($updatedExpense);
                $linkedExpenseId = $savedExpense->id;

                $this->auditLogger->record(
                    action: 'operational_expense_updated',
                    entityType: 'operational_expense',
                    entityId: $savedExpense->expenseUid,
                    before: $beforeExpensePayload,
                    after: $savedExpense->toArray(),
                    adminUserId: $adminUserId,
                    ipAddress: $request->getClientIp(),
                    userAgent: (string) $request->getHeader('User-Agent', '')
                );
            } else {
                $newExpense = new OperationalExpense(
                    propertyId: $propertyId,
                    category: ExpenseCategory::TAXES_INSURANCE,
                    title: $expenseTitle,
                    amount: $settledAmount,
                    expenseDate: $paymentDate,
                    vendorName: 'FONTUR',
                    referenceCode: $referenceCode !== '' ? $referenceCode : null,
                    reservationUid: null,
                    notes: $notes !== '' ? $notes : null,
                    createdBy: $adminUserId,
                    createdByName: $adminUserName
                );
                $savedExpense = $this->expenseRepository->save($newExpense);
                $linkedExpenseId = $savedExpense->id;

                $this->auditLogger->record(
                    action: 'operational_expense_created',
                    entityType: 'operational_expense',
                    entityId: $savedExpense->expenseUid,
                    before: null,
                    after: $savedExpense->toArray(),
                    adminUserId: $adminUserId,
                    ipAddress: $request->getClientIp(),
                    userAgent: (string) $request->getHeader('User-Agent', '')
                );
            }
        } else {
            // $0 COP declaración en cero: delete any previously linked OperationalExpense
            if ($existingLinkedExpense !== null && $existingLinkedExpense->id !== null) {
                $beforeExpensePayload = $existingLinkedExpense->toArray();
                $this->expenseRepository->delete($existingLinkedExpense->id);

                $this->auditLogger->record(
                    action: 'operational_expense_deleted',
                    entityType: 'operational_expense',
                    entityId: $existingLinkedExpense->expenseUid,
                    before: $beforeExpensePayload,
                    after: null,
                    adminUserId: $adminUserId,
                    ipAddress: $request->getClientIp(),
                    userAgent: (string) $request->getHeader('User-Agent', '')
                );
            }
            $linkedExpenseId = null;
        }

        // Preserve original snapshot of settledTaxableBase & settledCalculatedAmount if already settled,
        // or capture current live calculation on initial settlement
        $snapshotTaxableBase = ($existingSettlement !== null && $existingSettlement->isSettled() && $existingSettlement->settledTaxableBase !== null)
            ? $existingSettlement->settledTaxableBase
            : $parafiscalPeriod->taxableBase;

        $snapshotCalculatedAmount = ($existingSettlement !== null && $existingSettlement->isSettled() && $existingSettlement->settledCalculatedAmount !== null)
            ? $existingSettlement->settledCalculatedAmount
            : $parafiscalPeriod->calculatedContribution;

        $settledAt = ($this->now ?? new DateTimeImmutable('now', new DateTimeZone('America/Bogota')))
            ->setTimezone(new DateTimeZone('America/Bogota'));

        $beforeSettlementPayload = $existingSettlement?->toArray();

        $settlementEntity = new ParafiscalSettlement(
            propertyId: $propertyId,
            periodKey: $periodKey,
            periodStart: $parafiscalPeriod->periodStart,
            periodEnd: $parafiscalPeriod->periodEnd,
            dueDate: $parafiscalPeriod->dueDate,
            rntNumber: $rntNumber,
            status: ParafiscalSettlement::STATUS_SETTLED,
            settledTaxableBase: $snapshotTaxableBase,
            settledCalculatedAmount: $snapshotCalculatedAmount,
            settledAmount: $settledAmount,
            paymentDate: $paymentDate,
            referenceCode: $referenceCode !== '' ? $referenceCode : null,
            notes: $notes !== '' ? $notes : null,
            operationalExpenseId: $linkedExpenseId,
            settledBy: $adminUserId,
            settledByName: $adminUserName,
            settledAt: $settledAt,
            settlementUid: $existingSettlement?->settlementUid,
            id: $existingSettlement?->id,
            createdAt: $existingSettlement?->createdAt
        );

        $savedSettlement = $this->parafiscalRepository->save($settlementEntity);

        $auditAction = $isEdit ? 'parafiscal_updated' : 'parafiscal_settled';
        $this->auditLogger->record(
            action: $auditAction,
            entityType: 'parafiscal_settlement',
            entityId: $savedSettlement->settlementUid,
            before: $beforeSettlementPayload,
            after: $savedSettlement->toArray(),
            adminUserId: $adminUserId,
            ipAddress: $request->getClientIp(),
            userAgent: (string) $request->getHeader('User-Agent', '')
        );

        $flashMessage = $settledAmount >= 1.0
            ? sprintf(
                'FONTUR Parafiscal settlement for %s (Property %s · RNT #%s) recorded ($ %s COP) and synchronized with Operational Expenses.',
                $periodKey,
                $propertyId,
                $rntNumber,
                number_format($settledAmount, 0, ',', '.')
            )
            : sprintf(
                'FONTUR Parafiscal zero declaration (declaración en cero) for %s (Property %s · RNT #%s) recorded.',
                $periodKey,
                $propertyId,
                $rntNumber
            );

        return $this->buildMutationSuccessResponse($request, $filters, $session, $flashMessage, 'parafiscalSettled');
    }

    /**
     * POST /financial-report/parafiscal/{periodKey}/{propertyId}/revert:
     * Reverts a settled ParafiscalSettlement back to pending, deletes its linked
     * OperationalExpense (if any), and records parafiscal_reverted in the Audit Log.
     *
     * @param array<string, mixed> $session
     */
    public function revertParafiscal(Request $request, array &$session): Response
    {
        $authResponse = $this->authorizeOrRespond($request, $session);
        if ($authResponse !== null) {
            return $authResponse;
        }

        if (!$this->verifyCsrf($request, $session)) {
            return Response::html('<div class="p-4 text-xs text-rose-600 font-semibold">Invalid or expired CSRF token.</div>', 403);
        }

        $periodKey = strtoupper(trim((string) ($request->getAttribute('periodKey') ?? '')));
        $propertyId = trim((string) ($request->getAttribute('propertyId') ?? ''));

        if (!in_array($propertyId, ParafiscalSettlement::VALID_PROPERTIES, true) || !preg_match('/^\d{4}-Q[1-4]$/', $periodKey)) {
            return Response::html(
                '<div class="p-4 text-xs text-rose-600 font-semibold">Invalid Parafiscal period or property identifier.</div>',
                422
            );
        }

        $existingSettlement = $this->parafiscalRepository->findByPropertyAndPeriod($propertyId, $periodKey);
        if ($existingSettlement === null) {
            return Response::html(
                '<div class="p-4 text-xs text-rose-600 font-semibold">Parafiscal settlement not found.</div>',
                404
            );
        }

        $adminUserId = (int) $session['admin_user_id'];
        $beforeSettlementPayload = $existingSettlement->toArray();

        if ($existingSettlement->operationalExpenseId !== null) {
            $linkedExpense = $this->expenseRepository->findById($existingSettlement->operationalExpenseId);
            if ($linkedExpense !== null && $linkedExpense->id !== null) {
                $beforeExpensePayload = $linkedExpense->toArray();
                $this->expenseRepository->delete($linkedExpense->id);

                $this->auditLogger->record(
                    action: 'operational_expense_deleted',
                    entityType: 'operational_expense',
                    entityId: $linkedExpense->expenseUid,
                    before: $beforeExpensePayload,
                    after: null,
                    adminUserId: $adminUserId,
                    ipAddress: $request->getClientIp(),
                    userAgent: (string) $request->getHeader('User-Agent', '')
                );
            }
        }

        $revertedSettlement = new ParafiscalSettlement(
            propertyId: $existingSettlement->propertyId,
            periodKey: $existingSettlement->periodKey,
            periodStart: $existingSettlement->periodStart,
            periodEnd: $existingSettlement->periodEnd,
            dueDate: $existingSettlement->dueDate,
            rntNumber: $existingSettlement->rntNumber,
            status: ParafiscalSettlement::STATUS_PENDING,
            settledTaxableBase: null,
            settledCalculatedAmount: null,
            settledAmount: null,
            paymentDate: null,
            referenceCode: null,
            notes: null,
            operationalExpenseId: null,
            settledBy: null,
            settledByName: null,
            settledAt: null,
            settlementUid: $existingSettlement->settlementUid,
            id: $existingSettlement->id,
            createdAt: $existingSettlement->createdAt
        );

        $savedReverted = $this->parafiscalRepository->save($revertedSettlement);

        $this->auditLogger->record(
            action: 'parafiscal_reverted',
            entityType: 'parafiscal_settlement',
            entityId: $savedReverted->settlementUid,
            before: $beforeSettlementPayload,
            after: $savedReverted->toArray(),
            adminUserId: $adminUserId,
            ipAddress: $request->getClientIp(),
            userAgent: (string) $request->getHeader('User-Agent', '')
        );

        $filters = $this->resolveFiltersFromMutationRequest($request);
        $flashMessage = sprintf(
            'FONTUR Parafiscal settlement for %s (Property %s · RNT #%s) reverted to Pending.',
            $periodKey,
            $propertyId,
            $existingSettlement->rntNumber
        );

        $reportContentHtml = $this->renderReportContentHtml($filters, $session, oob: false, flashMessage: $flashMessage);
        $headerActionsHtml = $this->renderHeaderActionsHtml($filters, oob: true);
        $clearModalHtml = '<div id="modal-container" hx-swap-oob="innerHTML"></div>';

        if ($request->isHtmx()) {
            return new Response(
                statusCode: 200,
                headers: [
                    'Content-Type' => 'text/html; charset=UTF-8',
                    'HX-Trigger' => 'parafiscalReverted',
                ],
                body: $reportContentHtml . "\n" . $headerActionsHtml . "\n" . $clearModalHtml
            );
        }

        return Response::redirect('/financial-report?' . http_build_query([
            'property_id' => $filters['property_id'],
            'period' => $filters['period'],
            'date_from' => $filters['date_from'],
            'date_to' => $filters['date_to'],
            'compare' => $filters['compare'],
            'source' => $filters['source'],
            'category' => $filters['category'],
            'tab' => $filters['tab'] ?? 'expenses',
            'search' => $filters['search'],
        ]));
    }

    /**
     * GET /financial-report/export/expenses.csv:
     * Streams a filter-aware UTF-8 BOM CSV file of Operational Expenses via native fputcsv().
     *
     * @param array<string, mixed> $session
     */
    public function exportExpensesCsv(Request $request, array &$session): Response
    {
        $authResponse = $this->authorizeOrRespond($request, $session);
        if ($authResponse !== null) {
            return $authResponse;
        }

        $filters = $this->resolveFiltersFromRequest($request);
        $filterModel = FinancialReportFilter::fromQuery($filters);
        $reportData = $this->reportEngine->generate($filterModel, $this->now);
        $slice = $reportData->current;

        $headerRow = [
            'expense_uid',
            'expense_date',
            'property_id',
            'category',
            'category_label',
            'title',
            'vendor_name',
            'reference_code',
            'reservation_uid',
            'raw_amount_cop',
            'allocation_factor',
            'allocated_amount_cop',
            'notes',
        ];

        $dataRows = [];
        foreach ($slice->allocatedExpenses as $allocatedRow) {
            $expense = $allocatedRow->expense;
            $dataRows[] = [
                $expense->expenseUid,
                $expense->expenseDate,
                $expense->propertyId,
                $expense->category->value,
                $expense->category->label(),
                $expense->title,
                (string) ($expense->vendorName ?? ''),
                (string) ($expense->referenceCode ?? ''),
                (string) ($expense->reservationUid ?? ''),
                number_format($expense->amount, 2, '.', ''),
                number_format($allocatedRow->allocationFactor, 2, '.', ''),
                number_format($allocatedRow->allocatedAmount, 2, '.', ''),
                (string) ($expense->notes ?? ''),
            ];
        }

        $filename = sprintf(
            'operational-expenses-%s-%s-to-%s.csv',
            $filterModel->propertyId,
            $reportData->periodStart,
            $reportData->periodEnd
        );

        return $this->streamCsvResponse($filename, $headerRow, $dataRows);
    }

    /**
     * GET /financial-report/export/reservations.csv:
     * Streams a filter-aware UTF-8 BOM CSV file of Attributed Reservations via native fputcsv().
     *
     * @param array<string, mixed> $session
     */
    public function exportReservationsCsv(Request $request, array &$session): Response
    {
        $authResponse = $this->authorizeOrRespond($request, $session);
        if ($authResponse !== null) {
            return $authResponse;
        }

        $filters = $this->resolveFiltersFromRequest($request);
        $filterModel = FinancialReportFilter::fromQuery($filters);
        $reportData = $this->reportEngine->generate($filterModel, $this->now);
        $slice = $reportData->current;

        $headerRow = [
            'reservation_uid',
            'property_id',
            'guest_name',
            'booking_source',
            'channel_group',
            'channel_label',
            'status',
            'payment_status',
            'check_in',
            'check_out',
            'total_nights',
            'in_period_nights',
            'proration_factor',
            'total_price_cop',
            'refunded_amount_cop',
            'contract_net_cop',
            'in_period_attributed_revenue_cop',
            'attribution_type',
        ];

        $searchNeedle = strtolower($filterModel->search);
        $dataRows = [];

        foreach ($slice->attributedReservations as $row) {
            if ($searchNeedle !== '') {
                $haystack = strtolower(implode(' ', [
                    $row->reservationUid,
                    $row->propertyId,
                    $row->guestName,
                    $row->source,
                    $row->channelGroup->value,
                    $row->channelGroup->label(),
                ]));
                if (!str_contains($haystack, $searchNeedle)) {
                    continue;
                }
            }

            $dataRows[] = [
                $row->reservationUid,
                $row->propertyId,
                $row->guestName,
                $row->source,
                $row->channelGroup->value,
                $row->channelGroup->label(),
                $row->status,
                (string) ($row->paymentStatus ?? ''),
                $row->checkIn,
                $row->checkOut,
                (string) $row->totalNights,
                (string) $row->inPeriodNights,
                number_format($row->prorationFactor, 4, '.', ''),
                number_format($row->totalPrice, 2, '.', ''),
                number_format($row->refundedAmount, 2, '.', ''),
                number_format($row->contractNet, 2, '.', ''),
                number_format($row->attributedRevenue, 2, '.', ''),
                $row->isRetention ? 'policy_retention' : 'active_stay',
            ];
        }

        $filename = sprintf(
            'attributed-reservations-%s-%s-to-%s.csv',
            $filterModel->propertyId,
            $reportData->periodStart,
            $reportData->periodEnd
        );

        return $this->streamCsvResponse($filename, $headerRow, $dataRows);
    }

    /**
     * @param array<string, string> $form
     * @return array{errors: list<string>, fieldErrors: array<string, string>}
     */
    private function validateExpenseForm(array $form): array
    {
        $errors = [];
        $fieldErrors = [];

        $propertyId = trim($form['property_id']);
        if (!in_array($propertyId, OperationalExpense::VALID_PROPERTIES, true)) {
            $msg = 'Property must be 1606, 1707, or shared.';
            $errors[] = $msg;
            $fieldErrors['property_id'] = $msg;
        }

        $category = trim($form['category']);
        if (ExpenseCategory::tryFrom($category) === null) {
            $msg = 'Please select a valid operational expense category.';
            $errors[] = $msg;
            $fieldErrors['category'] = $msg;
        }

        $title = trim($form['title']);
        if ($title === '') {
            $msg = 'Expense title is required.';
            $errors[] = $msg;
            $fieldErrors['title'] = $msg;
        } elseif (mb_strlen($title) > OperationalExpense::MAX_TITLE_LENGTH) {
            $msg = sprintf('Expense title cannot exceed %d characters.', OperationalExpense::MAX_TITLE_LENGTH);
            $errors[] = $msg;
            $fieldErrors['title'] = $msg;
        }

        $amountRaw = trim($form['amount']);
        if ($amountRaw === '' || !is_numeric($amountRaw) || (float) $amountRaw <= 0.0) {
            $msg = 'Expense amount must be a positive number strictly greater than 0.00 COP.';
            $errors[] = $msg;
            $fieldErrors['amount'] = $msg;
        }

        $expenseDate = trim($form['expense_date']);
        if (!OperationalExpense::isValidDate($expenseDate)) {
            $msg = 'Expense date must be a valid date in YYYY-MM-DD format.';
            $errors[] = $msg;
            $fieldErrors['expense_date'] = $msg;
        }

        $reservationUid = trim($form['reservation_uid']);
        if ($reservationUid !== '') {
            if ($propertyId === 'shared') {
                $msg = 'Stay-linked expenses cannot be assigned to Shared Portfolio. Please select Property 1606 or 1707.';
                $errors[] = $msg;
                $fieldErrors['reservation_uid'] = $msg;
                $fieldErrors['property_id'] = $msg;
            } else {
                $reservation = $this->reservationRepository->findByUid($reservationUid);
                if ($reservation === null) {
                    $msg = sprintf('Reservation UID "%s" was not found in the ledger.', $reservationUid);
                    $errors[] = $msg;
                    $fieldErrors['reservation_uid'] = $msg;
                } elseif (in_array($propertyId, ['1606', '1707'], true) && $reservation->propertyId !== $propertyId) {
                    $msg = sprintf(
                        'Reservation "%s" belongs to Property %s, not Property %s.',
                        $reservationUid,
                        $reservation->propertyId,
                        $propertyId
                    );
                    $errors[] = $msg;
                    $fieldErrors['reservation_uid'] = $msg;
                }
            }
        }

        return [
            'errors' => $errors,
            'fieldErrors' => $fieldErrors,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function extractParafiscalForm(Request $request): array
    {
        return [
            'settled_amount' => trim((string) $request->getPost('settled_amount', '')),
            'payment_date' => trim((string) $request->getPost('payment_date', '')),
            'reference_code' => (string) $request->getPost('reference_code', ''),
            'notes' => (string) $request->getPost('notes', ''),
        ];
    }

    /**
     * @param array<string, string> $form
     * @return array{errors: list<string>, fieldErrors: array<string, string>}
     */
    private function validateParafiscalForm(array $form): array
    {
        $errors = [];
        $fieldErrors = [];

        $settledAmountRaw = trim($form['settled_amount']);
        if ($settledAmountRaw === '' || !is_numeric($settledAmountRaw) || (float) $settledAmountRaw < 0.0) {
            $msg = 'Settled amount must be a number greater than or equal to 0 COP.';
            $errors[] = $msg;
            $fieldErrors['settled_amount'] = $msg;
        }

        $paymentDate = trim($form['payment_date']);
        if (!OperationalExpense::isValidDate($paymentDate)) {
            $msg = 'Payment / filing date must be a valid date in YYYY-MM-DD format.';
            $errors[] = $msg;
            $fieldErrors['payment_date'] = $msg;
        }

        $referenceCode = trim($form['reference_code']);
        if (mb_strlen($referenceCode) > 120) {
            $msg = 'FONTUR reference code cannot exceed 120 characters.';
            $errors[] = $msg;
            $fieldErrors['reference_code'] = $msg;
        }

        return [
            'errors' => $errors,
            'fieldErrors' => $fieldErrors,
        ];
    }

    /**
     * @param array<string, string> $form
     * @param list<string> $errors
     * @param array<string, string> $fieldErrors
     * @param array<string, string> $filters
     * @param array<string, mixed> $session
     */
    private function renderParafiscalModalValidationError(
        ParafiscalPeriod $parafiscalPeriod,
        bool $isEdit,
        array $form,
        array $errors,
        array $fieldErrors,
        array $filters,
        array $session
    ): Response {
        $modalHtml = $this->viewRenderer->renderPartial('financial_report/_parafiscal_modal.php', [
            'parafiscalPeriod' => $parafiscalPeriod,
            'isEdit' => $isEdit,
            'form' => $form,
            'errors' => $errors,
            'fieldErrors' => $fieldErrors,
            'filters' => $filters,
            'csrfToken' => (string) ($session['csrf_token'] ?? ''),
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
     * @param array<string, string> $form
     * @param list<string> $errors
     * @param array<string, string> $fieldErrors
     * @param array<string, string> $filters
     * @param array<string, mixed> $session
     */
    private function renderModalValidationError(
        bool $isEdit,
        ?int $expenseId,
        ?string $expenseUid,
        array $form,
        array $errors,
        array $fieldErrors,
        array $filters,
        array $session
    ): Response {
        $modalHtml = $this->viewRenderer->renderPartial('financial_report/_expense_modal.php', [
            'isEdit' => $isEdit,
            'isDuplicate' => false,
            'expenseId' => $expenseId,
            'expenseUid' => $expenseUid,
            'form' => $form,
            'errors' => $errors,
            'fieldErrors' => $fieldErrors,
            'filters' => $filters,
            'csrfToken' => (string) ($session['csrf_token'] ?? ''),
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
     * @param array<string, string> $filters
     * @param array<string, mixed> $session
     */
    private function buildMutationSuccessResponse(
        Request $request,
        array $filters,
        array $session,
        string $flashMessage,
        string $triggerName
    ): Response {
        $reportContentHtml = $this->renderReportContentHtml($filters, $session, oob: true, flashMessage: $flashMessage);
        $headerActionsHtml = $this->renderHeaderActionsHtml($filters, oob: true);
        $clearModalHtml = '<div id="modal-container" hx-swap-oob="innerHTML"></div>';

        if ($request->isHtmx()) {
            return new Response(
                statusCode: 200,
                headers: [
                    'Content-Type' => 'text/html; charset=UTF-8',
                    'HX-Trigger' => $triggerName,
                ],
                body: $reportContentHtml . "\n" . $headerActionsHtml . "\n" . $clearModalHtml
            );
        }

        return Response::redirect('/financial-report?' . http_build_query([
            'property_id' => $filters['property_id'],
            'period' => $filters['period'],
            'date_from' => $filters['date_from'],
            'date_to' => $filters['date_to'],
            'compare' => $filters['compare'],
            'source' => $filters['source'],
            'category' => $filters['category'],
            'tab' => $filters['tab'] ?? 'expenses',
            'search' => $filters['search'],
        ]));
    }

    /**
     * @param array<string, string> $filters
     * @param array<string, mixed> $session
     */
    private function renderReportContentHtml(
        array $filters,
        array $session,
        bool $oob = false,
        ?string $flashMessage = null
    ): string {
        $filterModel = FinancialReportFilter::fromArray($filters);
        $reportData = $this->reportEngine->generate($filterModel, $this->now);
        $currentSlice = $reportData->current;

        return $this->viewRenderer->renderPartial('financial_report/_report_content.php', [
            'report' => $reportData,
            'slice' => $currentSlice,
            'expenses' => $currentSlice->expenses,
            'filters' => $filterModel->toArray($this->now),
            'summary' => [
                'direct_expenses' => $currentSlice->directExpenses,
                'allocated_shared_expenses' => $currentSlice->allocatedSharedExpenses,
                'total_expenses' => $currentSlice->totalExpenses,
            ],
            'categoryTotals' => $currentSlice->byCategory,
            'csrfToken' => (string) ($session['csrf_token'] ?? ''),
            'oob' => $oob,
            'flashMessage' => $flashMessage,
        ]);
    }

    /**
     * @param array<string, string> $filters
     */
    private function renderHeaderActionsHtml(array $filters, bool $oob = false): string
    {
        return $this->viewRenderer->renderPartial('financial_report/_header_actions.php', [
            'filters' => $filters,
            'oob' => $oob,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function extractExpenseForm(Request $request): array
    {
        return [
            'property_id' => trim((string) $request->getPost('property_id', '')),
            'category' => trim((string) $request->getPost('category', '')),
            'title' => (string) $request->getPost('title', ''),
            'amount' => trim((string) $request->getPost('amount', '')),
            'expense_date' => trim((string) $request->getPost('expense_date', '')),
            'vendor_name' => (string) $request->getPost('vendor_name', ''),
            'reference_code' => (string) $request->getPost('reference_code', ''),
            'reservation_uid' => (string) $request->getPost('reservation_uid', ''),
            'notes' => (string) $request->getPost('notes', ''),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function resolveFiltersFromRequest(Request $request): array
    {
        $filterModel = FinancialReportFilter::fromQuery([
            'property_id' => (string) $request->getQuery('property_id', 'all'),
            'period' => (string) $request->getQuery('period', ''),
            'date_from' => (string) ($request->getQuery('date_from', $request->getQuery('start_date', ''))),
            'date_to' => (string) ($request->getQuery('date_to', $request->getQuery('end_date', ''))),
            'compare' => (string) $request->getQuery('compare', 'prev_period'),
            'source' => (string) $request->getQuery('source', 'all'),
            'category' => (string) $request->getQuery('category', 'all'),
            'tab' => (string) $request->getQuery('tab', 'expenses'),
            'search' => (string) $request->getQuery('search', ''),
        ]);

        return $filterModel->toArray($this->now);
    }

    /**
     * @return array<string, string>
     */
    private function resolveFiltersFromMutationRequest(Request $request): array
    {
        $filterModel = FinancialReportFilter::fromQuery([
            'property_id' => (string) $request->getPost('current_property_id', (string) $request->getQuery('property_id', 'all')),
            'period' => (string) $request->getPost('current_period', (string) $request->getQuery('period', '')),
            'date_from' => (string) $request->getPost('current_date_from', (string) $request->getQuery('date_from', '')),
            'date_to' => (string) $request->getPost('current_date_to', (string) $request->getQuery('date_to', '')),
            'compare' => (string) $request->getPost('current_compare', (string) $request->getQuery('compare', 'prev_period')),
            'source' => (string) $request->getPost('current_source', (string) $request->getQuery('source', 'all')),
            'category' => (string) $request->getPost('current_category', (string) $request->getQuery('category', 'all')),
            'tab' => (string) $request->getPost('current_tab', (string) $request->getQuery('tab', 'expenses')),
            'search' => (string) $request->getPost('current_search', (string) $request->getQuery('search', '')),
        ]);

        return $filterModel->toArray($this->now);
    }

    private function findExpenseByIdOrUid(string $rawId): ?OperationalExpense
    {
        $trimmed = trim($rawId);
        if ($trimmed === '') {
            return null;
        }
        if (ctype_digit($trimmed)) {
            $found = $this->expenseRepository->findById((int) $trimmed);
            if ($found !== null) {
                return $found;
            }
        }
        return $this->expenseRepository->findByUid($trimmed);
    }

    private function todayInBogota(): string
    {
        $clock = $this->now ?? new DateTimeImmutable('now', new DateTimeZone('America/Bogota'));
        return $clock->setTimezone(new DateTimeZone('America/Bogota'))->format('Y-m-d');
    }

    private function formatInputAmount(float $amount): string
    {
        if (floor($amount) === $amount) {
            return (string) (int) $amount;
        }
        return number_format($amount, 2, '.', '');
    }

    /**
     * @param array<string, mixed> $session
     * @param array<string, mixed>|null $currentUser
     * @param-out array<string, mixed> $currentUser
     */
    private function authorizeOrRespond(Request $request, array $session, ?array &$currentUser = null): ?Response
    {
        $currentUser = $this->buildCurrentUser($session);

        if (empty($session['admin_user_id'])) {
            return $request->isHtmx()
                ? Response::htmxUnauthorized('/login')
                : Response::redirect('/login', 302);
        }

        if (!$this->isAuthorized($currentUser)) {
            return $this->forbiddenResponse($request, $currentUser, $session);
        }

        return null;
    }

    /**
     * @param list<string> $headerRow
     * @param list<list<string>> $dataRows
     */
    private function streamCsvResponse(string $filename, array $headerRow, array $dataRows): Response
    {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            return Response::html('Unable to initialize CSV export stream.', 500);
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, $headerRow, ',', '"', '');

        foreach ($dataRows as $row) {
            fputcsv($stream, $row, ',', '"', '');
        }

        rewind($stream);
        $csvBody = (string) stream_get_contents($stream);
        fclose($stream);

        return new Response(
            statusCode: 200,
            body: $csvBody,
            headers: [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]
        );
    }

    /**
     * @param array<string, mixed> $session
     */
    private function verifyCsrf(Request $request, array $session): bool
    {
        $sessionToken = (string) ($session['csrf_token'] ?? '');
        if ($sessionToken === '') {
            return false;
        }

        $clientToken = (string) (
            $request->getHeader('HX-CSRF-TOKEN')
            ?? $request->getHeader('X-CSRF-TOKEN')
            ?? $request->getPost('csrf_token')
            ?? ''
        );

        return $clientToken !== '' && hash_equals($sessionToken, $clientToken);
    }

    /**
     * @param array<string, mixed> $currentUser
     */
    private function isAuthorized(array $currentUser): bool
    {
        $role = (string) ($currentUser['role'] ?? '');
        return in_array($role, self::AUTHORIZED_ROLES, true);
    }

    /**
     * @param array<string, mixed> $currentUser
     * @param array<string, mixed> $session
     */
    private function forbiddenResponse(Request $request, array $currentUser, array $session): Response
    {
        if ($request->isHtmx()) {
            $html = '
                <div class="p-6 text-center">
                    <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-rose-100 text-rose-600 mb-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <p class="text-sm font-semibold text-rose-800">Access Restricted</p>
                    <p class="text-xs text-gray-500 mt-1">Financial reports and operational expenses require Administrator privileges.</p>
                </div>';
            return Response::html($html, 403);
        }

        $html = $this->viewRenderer->render('errors/403.php', [
            'currentUser' => $currentUser,
            'csrfToken' => (string) ($session['csrf_token'] ?? ''),
            'currentRoute' => '/financial-report',
            'title' => '403 Forbidden - Ocean View Flats Admin',
        ]);

        return Response::html($html, 403);
    }

    /**
     * @param array<string, mixed> $session
     * @return array<string, mixed>
     */
    private function buildCurrentUser(array $session): array
    {
        return [
            'id' => isset($session['admin_user_id']) ? (int) $session['admin_user_id'] : null,
            'name' => $session['admin_user_name'] ?? 'Admin',
            'email' => $session['admin_user_email'] ?? ($session['admin_email'] ?? 'admin@oceanviewflats.com'),
            'role' => $session['admin_user_role'] ?? ($session['admin_role'] ?? 'admin'),
        ];
    }
}
