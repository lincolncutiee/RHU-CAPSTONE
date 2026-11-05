<?php

require_once __DIR__ . '/WorkflowStatus.php';
require_once __DIR__ . '/ReportFilters.php';

/**
 * Printable summary of encoder drafts and workflow counts for management reports.
 */
class ReportDraftSummary
{
    public const DRAFT_LIST_LIMIT = 80;

    /**
     * @return array{
     *   workflow_snapshot: list<array{status:string,label:string,count:int}>,
     *   period_encoded: list<array{status:string,label:string,count:int}>,
     *   draft_totals: array{draft:int,returned:int,rejected:int,encoder_queue:int},
     *   draft_rows: list<array<string,mixed>>,
     *   draft_truncated: bool,
     *   period: array{from:string,to:string,from_label:string,to_label:string}
     * }
     */
    public static function build(
        mysqli $conn,
        string $from,
        string $to,
        ?ReportFilters $filters = null,
    ): array {
        WorkflowStatus::ensurePatientsStatusEnum($conn);
        $filters ??= new ReportFilters();

        $fromTs = strtotime($from) ?: strtotime('-29 days');
        $toTs = strtotime($to) ?: time();
        if ($fromTs > $toTs) {
            [$fromTs, $toTs] = [$toTs, $fromTs];
        }
        $from = date('Y-m-d', $fromTs);
        $to = date('Y-m-d', $toTs);

        return [
            'period' => [
                'from'        => $from,
                'to'          => $to,
                'from_label'  => date('M j, Y', $fromTs),
                'to_label'    => date('M j, Y', $toTs),
            ],
            'workflow_snapshot' => self::countsByStatus($conn, $filters, null, null),
            'period_encoded'    => self::countsByStatus($conn, $filters, $from, $to),
            'draft_totals'      => self::draftTotals($conn, $filters),
            'draft_rows'        => self::draftRows($conn, $filters, $from, $to, self::DRAFT_LIST_LIMIT),
            'draft_truncated'   => self::draftRowCount($conn, $filters, $from, $to) > self::DRAFT_LIST_LIMIT,
        ];
    }

    /** @return list<array{status:string,label:string,count:int}> */
    private static function countsByStatus(
        mysqli $conn,
        ReportFilters $filters,
        ?string $from,
        ?string $to,
    ): array {
        $types = [];
        $params = [];
        $demo = $filters->appendSql('p', $types, $params);

        $dateSql = '';
        if ($from !== null && $to !== null) {
            $dateSql = ' AND DATE(p.date_encoded) BETWEEN ? AND ?';
            $types[] = 's';
            $types[] = 's';
            $params[] = $from;
            $params[] = $to;
        }

        $sql = "SELECT p.status AS status_raw, COUNT(*) AS cnt
                FROM patients p
                WHERE p.is_archived = 0{$demo}{$dateSql}
                GROUP BY p.status
                ORDER BY cnt DESC, p.status ASC";

        $stmt = $conn->prepare($sql);
        if ($types !== []) {
            $stmt->bind_param(implode('', $types), ...$params);
        }
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $options = WorkflowStatus::filterOptions();
        $out = [];
        foreach ($rows as $row) {
            $status = WorkflowStatus::normalize((string) ($row['status_raw'] ?? ''));
            $out[] = [
                'status' => $status,
                'label'  => $options[$status] ?? $status,
                'count'  => (int) ($row['cnt'] ?? 0),
            ];
        }
        return $out;
    }

    /** @return array{draft:int,returned:int,rejected:int,encoder_queue:int} */
    private static function draftTotals(mysqli $conn, ReportFilters $filters): array
    {
        $draft = WORKFLOW_DRAFT;
        $returned = WORKFLOW_RETURNED_FOR_CORRECTION;
        $rejected = WORKFLOW_REJECTED;

        $types = ['s', 's', 's'];
        $params = [$draft, $returned, $rejected];
        $demo = $filters->appendSql('p', $types, $params);

        $sql = "SELECT
                    SUM(CASE WHEN p.status = ? THEN 1 ELSE 0 END) AS draft_cnt,
                    SUM(CASE WHEN p.status = ? THEN 1 ELSE 0 END) AS returned_cnt,
                    SUM(CASE WHEN p.status = ? THEN 1 ELSE 0 END) AS rejected_cnt
                FROM patients p
                WHERE p.is_archived = 0{$demo}";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(implode('', $types), ...$params);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();

        $draftCnt = (int) ($row['draft_cnt'] ?? 0);
        $returnedCnt = (int) ($row['returned_cnt'] ?? 0);
        $rejectedCnt = (int) ($row['rejected_cnt'] ?? 0);

        return [
            'draft'          => $draftCnt,
            'returned'       => $returnedCnt,
            'rejected'       => $rejectedCnt,
            'encoder_queue'  => $draftCnt + $returnedCnt + $rejectedCnt,
        ];
    }

    private static function draftRowCount(
        mysqli $conn,
        ReportFilters $filters,
        string $from,
        string $to,
    ): int {
        $types = [];
        $params = [];
        $demo = $filters->appendSql('p', $types, $params);
        $statuses = WorkflowStatus::encoderEditableStatuses();
        $ph = implode(',', array_fill(0, count($statuses), '?'));

        $sql = "SELECT COUNT(*) AS c FROM patients p
                WHERE p.is_archived = 0{$demo}
                  AND p.status IN ($ph)
                  AND (
                    DATE(p.date_encoded) BETWEEN ? AND ?
                    OR DATE(p.updated_at) BETWEEN ? AND ?
                  )";

        foreach ($statuses as $status) {
            $params[] = $status;
            $types[] = 's';
        }
        $params[] = $from;
        $params[] = $to;
        $params[] = $from;
        $params[] = $to;
        array_push($types, 's', 's', 's', 's');

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(implode('', $types), ...$params);
        $stmt->execute();
        $c = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
        $stmt->close();
        return $c;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function draftRows(
        mysqli $conn,
        ReportFilters $filters,
        string $from,
        string $to,
        int $limit,
    ): array {
        $types = [];
        $params = [];
        $demo = $filters->appendSql('p', $types, $params);
        $statuses = WorkflowStatus::encoderEditableStatuses();
        $ph = implode(',', array_fill(0, count($statuses), '?'));

        $sql = "SELECT
                    p.id,
                    p.patient_id,
                    p.last_name,
                    p.first_name,
                    p.middle_name,
                    p.sex,
                    p.birth_date,
                    p.status,
                    p.date_encoded,
                    p.updated_at,
                    p.residential_address,
                    (SELECT COUNT(*) FROM treatment_records tr WHERE tr.patient_id = p.id) AS consult_count,
                    (SELECT tr.diagnosis FROM treatment_records tr
                        WHERE tr.patient_id = p.id
                        ORDER BY COALESCE(tr.date_of_consultation, tr.created_at) DESC, tr.id DESC
                        LIMIT 1) AS latest_diagnosis,
                    (SELECT DATE(COALESCE(tr.date_of_consultation, tr.created_at)) FROM treatment_records tr
                        WHERE tr.patient_id = p.id
                        ORDER BY COALESCE(tr.date_of_consultation, tr.created_at) DESC, tr.id DESC
                        LIMIT 1) AS latest_consult_date
                FROM patients p
                WHERE p.is_archived = 0{$demo}
                  AND p.status IN ($ph)
                  AND (
                    DATE(p.date_encoded) BETWEEN ? AND ?
                    OR DATE(p.updated_at) BETWEEN ? AND ?
                  )
                ORDER BY p.updated_at DESC, p.id DESC
                LIMIT " . (int) $limit;

        foreach ($statuses as $status) {
            $params[] = $status;
            $types[] = 's';
        }
        $params[] = $from;
        $params[] = $to;
        $params[] = $from;
        $params[] = $to;
        array_push($types, 's', 's', 's', 's');

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(implode('', $types), ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $options = WorkflowStatus::filterOptions();
        $out = [];
        foreach ($rows as $row) {
            $status = WorkflowStatus::normalize((string) ($row['status'] ?? ''));
            $first = trim((string) ($row['first_name'] ?? ''));
            $middle = trim((string) ($row['middle_name'] ?? ''));
            $last = trim((string) ($row['last_name'] ?? ''));
            $fullName = trim($first . ($middle !== '' ? ' ' . $middle : '') . ' ' . $last);

            $dx = trim((string) ($row['latest_diagnosis'] ?? ''));
            $consultCount = (int) ($row['consult_count'] ?? 0);
            $contents = [];
            if ($consultCount === 0) {
                $contents[] = 'No consultation saved yet';
            } else {
                $contents[] = $consultCount . ' consult(s)';
                if ($dx !== '') {
                    $contents[] = 'Latest dx: ' . $dx;
                }
            }

            $out[] = [
                'patient_no'   => (string) ($row['patient_id'] ?? ''),
                'full_name'    => $fullName !== '' ? $fullName : '—',
                'sex'          => trim((string) ($row['sex'] ?? '')) ?: '—',
                'age'          => self::ageLabel($row['birth_date'] ?? null),
                'barangay'     => self::shortAddress((string) ($row['residential_address'] ?? '')),
                'status'       => $status,
                'status_label' => $options[$status] ?? $status,
                'date_encoded' => self::fmtDate($row['date_encoded'] ?? null),
                'updated_at'   => self::fmtDateTime($row['updated_at'] ?? null),
                'latest_consult' => self::fmtDate($row['latest_consult_date'] ?? null),
                'contents'     => implode(' · ', $contents),
            ];
        }
        return $out;
    }

    private static function ageLabel(?string $birthDate): string
    {
        if ($birthDate === null || trim($birthDate) === '') {
            return '—';
        }
        $ts = strtotime($birthDate);
        if ($ts === false) {
            return '—';
        }
        $age = (int) date('Y') - (int) date('Y', $ts);
        if (date('md') < date('md', $ts)) {
            $age--;
        }
        return $age >= 0 ? (string) $age : '—';
    }

    private static function shortAddress(string $address): string
    {
        $address = trim(preg_replace('/\s+/', ' ', $address) ?? '');
        if ($address === '') {
            return '—';
        }
        if (strlen($address) > 48) {
            return substr($address, 0, 45) . '…';
        }
        return $address;
    }

    private static function fmtDate(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return '—';
        }
        $ts = strtotime($value);
        return $ts ? date('M j, Y', $ts) : '—';
    }

    private static function fmtDateTime(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return '—';
        }
        $ts = strtotime($value);
        return $ts ? date('M j, Y g:i A', $ts) : '—';
    }
}
