<?php

declare(strict_types=1);

namespace Xentral\Modules\ApiV3\Domain;

use Xentral\Modules\ApiV3\Http\ApiV3Exception;
use Xentral\Modules\ApiV3\Repository\BankingRepository;

final class BankingService
{
    /** @var BankingRepository */
    private $banking;

    public function __construct(BankingRepository $banking)
    {
        $this->banking = $banking;
    }

    /**
     * @param array<string, mixed> $filters
     * @param array{page:int,per_page:int,offset:int} $pagination
     *
     * @return array{items:array<int, array<string, mixed>>,total:int}
     */
    public function listTransactions(array $filters, array $pagination): array
    {
        return $this->banking->listBankTransactions($filters, $pagination);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function importTransactions(array $payload): array
    {
        $accountId = trim((string)($payload['account_id'] ?? ''));
        if ($accountId === '') {
            throw new ApiV3Exception(422, 'missing_account_id', 'A bank account identifier is required.');
        }

        $fromDate = trim((string)($payload['from'] ?? date('Y-m-d', strtotime('-30 days'))));
        $transactions = isset($payload['transactions']) && is_array($payload['transactions'])
            ? $payload['transactions']
            : [];

        if (empty($transactions)) {
            throw new ApiV3Exception(422, 'missing_transactions', 'At least one transaction must be supplied.');
        }

        $existing = $this->banking->getExistingTransactionIdentifiers($accountId, $fromDate);
        $knownIds = array_fill_keys($existing['ids'], true);
        $knownTexts = array_fill_keys($existing['texts'], true);

        $importRows = [];
        $skipped = 0;

        foreach ($transactions as $transaction) {
            if (!is_array($transaction)) {
                continue;
            }

            $externalId = trim((string)($transaction['external_id'] ?? ''));
            $bookingText = trim((string)($transaction['booking_text'] ?? ''));
            if ($externalId !== '' && isset($knownIds[$externalId])) {
                $skipped++;
                continue;
            }
            if ($externalId === '' && $bookingText !== '' && isset($knownTexts[$bookingText])) {
                $skipped++;
                continue;
            }

            $amount = (float)($transaction['amount'] ?? 0);
            $importRows[] = [
                'konto'            => $accountId,
                'buchung'          => (string)($transaction['booking_date'] ?? date('Y-m-d')),
                'soll'             => $amount < 0 ? abs($amount) : 0.0,
                'haben'            => $amount > 0 ? $amount : 0.0,
                'gebuehr'          => (float)($transaction['fee'] ?? 0),
                'buchungstext'     => $bookingText,
                'internebemerkung' => $externalId !== '' ? $externalId : $bookingText,
                'importdatum'      => (string)($transaction['imported_at'] ?? date('Y-m-d H:i:s')),
            ];
        }

        $inserted = empty($importRows) ? 0 : $this->banking->importTransactions($importRows);

        return [
            'account_id' => $accountId,
            'from'       => $fromDate,
            'inserted'   => $inserted,
            'skipped'    => $skipped,
            'total'      => count($transactions),
        ];
    }
}
