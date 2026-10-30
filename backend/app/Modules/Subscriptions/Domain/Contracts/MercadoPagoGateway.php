<?php

namespace App\Modules\Subscriptions\Domain\Contracts;

interface MercadoPagoGateway
{
    /**
     * @param array{
     *     reason: string,
     *     external_reference: string,
     *     payer_email: string,
     *     auto_recurring: array{frequency: int, frequency_type: string, transaction_amount: string, currency_id: string},
     *     back_url: string,
     *     status: string
     * } $request
     * @return array{id: string, init_point: string, status: string}
     */
    public function createPendingPreapproval(array $request): array;

    /**
     * @param array{
     *     reason: string,
     *     auto_recurring: array{frequency: int, frequency_type: string, transaction_amount: string, currency_id: string}
     * } $request
     * @return array{id: string, status: string}
     */
    public function updatePreapproval(string $id, array $request): array;

    /**
     * @return array{id: string, status: string, next_payment_date: ?string}
     */
    public function getPreapproval(string $id): array;

    /**
     * @return array{id: string, preapproval_id: string, status: string, amount: string, currency: string, payment_id: ?string, payment_status: ?string, payment_status_detail: ?string, next_payment_date: ?string}
     */
    public function getAuthorizedPayment(int $id): array;

    /** @return list<int> Invoice IDs with approved payments for this subscription. */
    public function findApprovedAuthorizedPaymentIds(string $preapprovalId): array;

    /**
     * @return array{id: string, status: string}
     */
    public function cancelPreapproval(string $id): array;

    /**
     * @return array{id: string, init_point: string, status: string}|null
     */
    public function findPreapprovalByReference(string $externalReference): ?array;
}
