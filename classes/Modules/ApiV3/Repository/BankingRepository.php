<?php

declare(strict_types=1);

namespace Xentral\Modules\ApiV3\Repository;

use Xentral\Components\Database\Database;

final class BankingRepository
{
    /** @var Database */
    private $database;

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    /**
     * @param array<string, mixed> $filters
     * @param array{page:int,per_page:int,offset:int} $pagination
     *
     * @return array{items:array<int, array<string, mixed>>,total:int}
     */
    public function listBankTransactions(array $filters, array $pagination): array
    {
        $where = ['1 = 1'];
        $params = [];

        if (!empty($filters['account_id'])) {
            $where[] = 'konto = :konto';
            $params['konto'] = (string)$filters['account_id'];
        }
        if (!empty($filters['from'])) {
            $where[] = 'buchung >= :buchung_von';
            $params['buchung_von'] = (string)$filters['from'];
        }
        if (!empty($filters['external_id'])) {
            $where[] = 'internebemerkung = :external_id';
            $params['external_id'] = (string)$filters['external_id'];
        }

        $sqlWhere = implode(' AND ', $where);
        $total = (int)$this->database->fetchValue(
            "SELECT COUNT(id) FROM kontoauszuege WHERE {$sqlWhere}",
            $params
        );

        $params['limit'] = $pagination['per_page'];
        $params['offset'] = $pagination['offset'];

        $items = $this->database->fetchAll(
            "SELECT
                id,
                konto,
                buchung,
                soll,
                haben,
                gebuehr,
                buchungstext,
                internebemerkung,
                importdatum
             FROM kontoauszuege
             WHERE {$sqlWhere}
             ORDER BY buchung DESC, id DESC
             LIMIT :limit OFFSET :offset",
            $params
        );

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @return array{ids:string[],texts:string[]}
     */
    public function getExistingTransactionIdentifiers(string $accountId, string $fromDate): array
    {
        $rows = $this->database->fetchAll(
            'SELECT internebemerkung, buchungstext
             FROM kontoauszuege
             WHERE konto = :konto AND buchung >= :buchung_von',
            [
                'konto'       => $accountId,
                'buchung_von' => $fromDate,
            ]
        );

        $ids = [];
        $texts = [];
        foreach ($rows as $row) {
            $identifier = trim((string)($row['internebemerkung'] ?? ''));
            if ($identifier !== '') {
                $ids[] = $identifier;
            } else {
                $text = trim((string)($row['buchungstext'] ?? ''));
                if ($text !== '') {
                    $texts[] = $text;
                }
            }
        }

        return [
            'ids'   => array_values(array_unique($ids)),
            'texts' => array_values(array_unique($texts)),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $transactions
     */
    public function importTransactions(array $transactions): int
    {
        $this->database->beginTransaction();
        try {
            foreach ($transactions as $transaction) {
                $this->database->perform(
                    "INSERT INTO kontoauszuege (
                        konto, buchung, originalbuchung, vorgang, originalvorgang,
                        soll, originalsoll, haben, originalhaben, gebuehr, originalgebuehr,
                        waehrung, originalwaehrung, fertig, datev_abgeschlossen, buchungstext,
                        gegenkonto, belegfeld1, bearbeiter, mailbenachrichtigung, pruefsumme,
                        kostenstelle, importgroup, diff, diffangelegt, internebemerkung,
                        importfehler, parent, sort, doctype, doctypeid, vorauswahltyp,
                        vorauswahlparameter, klaerfall, klaergrund, bezugtyp, bezugparameter,
                        vorauswahlvorschlag, importdatum
                    ) VALUES (
                        :konto, :buchung, '1970-01-01', '', '',
                        :soll, 0.00, :haben, 0.00, :gebuehr, 0.00,
                        'EUR', '', 0, 0, :buchungstext,
                        '', '', '', 0, '',
                        '', NULL, 0.0000, NULL, :internebemerkung,
                        NULL, 0, 0, '', 0, '',
                        '0', 0, '', '', '',
                        0, :importdatum
                    )",
                    $transaction
                );
            }

            $this->database->commit();

            return count($transactions);
        } catch (\Throwable $throwable) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $throwable;
        }
    }
}
