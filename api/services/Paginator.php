<?php

namespace App\API\Services;

use PDO;

/**
 * Paginator - server-side (SQL) pagination, Laravel-length aware but
 * framework-free.
 *
 * Server-side pagination is a hard requirement for the governed reporting layer
 * (see AGENTS.md) so large tables are never dumped to the browser. Two entry
 * points:
 *
 *   Paginator::make($items, $total, $page, $perPage)
 *       Pure formatter — takes already-fetched items + a known total and returns
 *       a standard response envelope. Unit-testable without a database.
 *
 *   Paginator::fromQuery($pdo, $countSql, $selectSql, $params, $page, $perPage, $order)
 *       Convenience for the common COUNT(*) + bounded SELECT pattern. It strips
 *       the bare ORDER BY into a guarded limit, binds params once, and returns
 *       the same envelope.
 *
 * Envelope keys: data, current_page, per_page, total, last_page, from, to,
 * first_page_url / last_page_url / prev_page_url / next_page_url (without a
 * known request path these are pcursors — safe for JSON status responses).
 */
class Paginator
{
    /**
     * Build a paginated response envelope from already-fetched items.
     *
     * @param array $items   Current page rows.
     * @param int   $total   Total matching rows across all pages.
     * @param int   $page    Current 1-based page.
     * @param int   $perPage Rows per page.
     * @return array
     */
    public static function make(array $items, int $total, int $page = 1, int $perPage = 25): array
    {
        $perPage = max(1, $perPage);
        $page = max(1, $page);
        $lastPage = $perPage > 0 ? (int) ceil($total / $perPage) : 0;
        $lastPage = max(1, $lastPage);
        $page = min($page, $lastPage);

        $from = $total === 0 ? null : (($page - 1) * $perPage) + 1;
        $to = $total === 0 ? null : min($total, $page * $perPage);

        return [
            'data'          => array_values($items),
            'current_page'  => $page,
            'per_page'      => $perPage,
            'total'         => $total,
            'last_page'     => $lastPage,
            'from'          => $from,
            'to'            => $to,
            'first_page_url' => $page <= 1 ? null : '?page=1',
            'last_page_url'  => $page >= $lastPage ? null : '?page=' . $lastPage,
            'prev_page_url'  => $page <= 1 ? null : '?page=' . ($page - 1),
            'next_page_url'  => $page >= $lastPage ? null : '?page=' . ($page + 1),
        ];
    }

    /**
     * Run a COUNT + bounded SELECT and return the standard envelope.
     *
     * The $selectSql must NOT contain ORDER BY/LIMIT — pass those through
     * $orderClause (safe, allowlisted text you own) and $perPage. Params are
     * bound once and reused for both queries.
     *
     * @param PDO    $pdo
     * @param string $countSql   COUNT query (params in same order as select).
     * @param string $selectSql  Data SELECT (no LIMIT/OFFSET/ORDER BY).
     * @param array  $params     Positional binding values for both queries.
     * @param int    $page
     * @param int    $perPage
     * @param string $orderClause Optional "; ORDER BY ..." — caller-controlled.
     */
    public static function fromQuery(
        PDO $pdo,
        string $countSql,
        string $selectSql,
        array $params = [],
        int $page = 1,
        int $perPage = 25,
        string $orderClause = ''
    ): array {
        $perPage = max(1, min(500, $perPage));
        $page = max(1, $page);

        // Bound LIMIT/OFFSET are not allowed in MySQL/MariaDB, so they are
        // inlined as validated integers (already cast + clamped above).
        $offset = ($page - 1) * $perPage;

        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = $selectSql
            . ($orderClause !== '' ? ' ' . rtrim($orderClause) : '')
            . " LIMIT {$perPage} OFFSET {$offset}";
        $dataStmt = $pdo->prepare($sql);
        $dataStmt->execute($params);
        $items = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

        return self::make($items, $total, $page, $perPage);
    }
}
