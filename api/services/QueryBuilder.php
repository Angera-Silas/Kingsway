<?php

namespace App\API\Services;

use App\Database\Database;
use PDO;
use RuntimeException;

/**
 * QueryBuilder - fluent, parameterised-only SQL query builder (Eloquent-inspired,
 * but writes its own parameterised SQL so no pass-through user SQL is ever
 * accepted).
 *
 * AGENTS.md mandate: "Any administrator ad-hoc builder must compile validated
 * metadata to parameterised server-side queries; it must not expose pass-through
 * SQL." This builder is the safe compile target: every value is a bound
 * parameter, and every identifier (table/column) is allow-listed/validated.
 *
 * Fluent API:
 *   (new QueryBuilder())->table('students')->where('status','=','active')
 *       ->orderBy('id','DESC')->limit(10)->get();
 *
 * Terminal methods: get(), first(), value(), pluck(), count(), exists(), sum(),
 * insert(), update(), delete(), paginate().
 *
 * Only one where()/join() grouping is maintained (a flat AND chain). For
 * complex booleans prefer composing sub-queries or the lower-level PDO via the
 * same bound-parameter discipline in the report manager.
 */
class QueryBuilder
{
    /** @var PDO */
    private $pdo;

    private $table = null;
    private $columns = ['*'];
    private $wheres = [];
    private $joins = [];
    private $orderBy = [];
    private $groupBy = [];
    private $having = [];
    private $limit = null;
    private $offset = null;
    private $bindings = [];
    private $lock = false;

    /**
     * @param PDO|null $pdo Falls back to the shared Database connection.
     */
    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance()->getConnection();
    }

    public function table(string $table): self
    {
        $this->table = $this->validateIdentifier($table);
        return $this;
    }

    public function select(...$columns): self
    {
        $this->columns = array_map([$this, 'validateIdentifier'], $columns);
        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $type = strtoupper($type);
        if (!in_array($type, ['INNER', 'LEFT', 'RIGHT', 'LEFT OUTER', 'RIGHT OUTER'], true)) {
            throw new RuntimeException("QueryBuilder: invalid join type '{$type}'");
        }
        // only allow the first operand to carry an alias; second is an identifier
        $this->joins[] = [
            'type'   => $type,
            'table'  => $this->validateIdentifier($table),
            'first'  => $this->validateIdentifier($first),
            'op'     => $this->validateOperator($operator),
            'second' => $this->validateIdentifier($second),
        ];
        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    public function where(string $column, $operator = null, $value = null): self
    {
        // Support both where($col, $value) and where($col, $op, $value).
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        if (in_array(strtolower($this->validateOperator($operator)), ['in', 'not in'], true)) {
            return $this->whereIn($column, (array) $value, strtolower($operator) === 'not in');
        }
        $this->wheres[] = ' AND ';
        $this->wheres[] = $this->validateIdentifier($column) . ' ' . $this->validateOperator($operator) . ' ?';
        $this->bind(sanitizeBindValue($value));
        return $this;
    }

    public function orWhere(string $column, $operator = null, $value = null): self
    {
        // Support both orWhere($col, $value) and orWhere($col, $op, $value).
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        $this->wheres[] = ' OR ';
        $this->wheres[] = $this->validateIdentifier($column) . ' ' . $this->validateOperator($operator) . ' ?';
        $this->bind(sanitizeBindValue($value));
        return $this;
    }

    public function whereIn(string $column, array $values, bool $negate = false): self
    {
        $values = array_values($values);
        $ph = implode(',', array_fill(0, count($values), '?'));
        $op = $negate ? 'NOT IN' : 'IN';
        $this->wheres[] = ' AND ';
        $this->wheres[] = $this->validateIdentifier($column) . ' ' . $op . ' (' . $ph . ')';
        foreach ($values as $v) {
            $this->bind(sanitizeBindValue($v));
        }
        return $this;
    }

    public function whereNull(string $column, bool $negate = false): self
    {
        $this->wheres[] = ' AND ';
        $this->wheres[] = $this->validateIdentifier($column) . ($negate ? ' IS NOT NULL' : ' IS NULL');
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction);
        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            $direction = 'ASC';
        }
        $this->orderBy[] = $this->validateIdentifier($column) . ' ' . $direction;
        return $this;
    }

    public function groupBy(string ...$columns): self
    {
        foreach ($columns as $c) {
            $this->groupBy[] = $this->validateIdentifier($c);
        }
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = max(0, $limit);
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = max(0, $offset);
        return $this;
    }

    public function lock(): self
    {
        $this->lock = true;
        return $this;
    }

    // ---- terminal methods ----

    public function toSql(): string
    {
        $sql = 'SELECT ' . implode(', ', $this->columns)
            . ' FROM ' . $this->requireTable();
        $sql .= $this->joinSql();
        $sql .= $this->whereSql();
        if ($this->groupBy) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupBy);
        }
        if ($this->having) {
            $sql .= ' HAVING ' . implode(' AND ', $this->having);
        }
        if ($this->orderBy) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orderBy);
        }
        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
            if ($this->offset !== null) {
                $sql .= ' OFFSET ' . $this->offset;
            }
        }
        if ($this->lock) {
            $sql .= ' FOR UPDATE';
        }
        return $sql;
    }

    /**
     * Ordered list of bound parameters matching the '?' placeholders, in order.
     * Useful for debugging and SQL-building unit tests (DB-free).
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    public function get(): array
    {
        $stmt = $this->pdo->prepare($this->toSql());
        $stmt->execute($this->bindings);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function first(): ?array
    {
        $this->limit(1);
        $rows = $this->get();
        return $rows[0] ?? null;
    }

    public function value(string $column): ?array
    {
        $row = $this->first();
        return $row === null ? null : $row[$this->validateIdentifier($column)] ?? null;
    }

    public function pluck(string $column): array
    {
        $col = $this->validateIdentifier($column);
        $rows = $this->get();
        return array_map(function ($r) use ($col) {
            return $r[$col] ?? null;
        }, $rows);
    }

    public function count(): int
    {
        $sql = $this->aggregateSql('COUNT(*)');
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->bindings);
        return (int) $stmt->fetchColumn();
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function sum(string $column)
    {
        $sql = $this->aggregateSql('COALESCE(SUM(' . $this->validateIdentifier($column) . '),0)');
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->bindings);
        return (float) $stmt->fetchColumn();
    }

    /**
     * Fluent pagination that returns the Paginator envelope.
     */
    public function paginate(int $page = 1, int $perPage = 25): array
    {
        $perPage = max(1, min(500, $perPage));
        $page = max(1, $page);

        $countSql = $this->aggregateSql('COUNT(*)');
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($this->bindings);
        $total = (int) $countStmt->fetchColumn();

        $this->offset(($page - 1) * $perPage)->limit($perPage);
        $items = $this->get();

        return Paginator::make($items, $total, $page, $perPage);
    }

    public function insert(array $data): int
    {
        if (empty($data)) {
            throw new RuntimeException('QueryBuilder: insert requires at least one column');
        }
        $columns = array_map([$this, 'validateIdentifier'], array_keys($data));
        $values = array_values($data);
        $ph = implode(',', array_fill(0, count($columns), '?'));
        $sql = 'INSERT INTO ' . $this->requireTable()
            . ' (' . implode(', ', $columns) . ') VALUES (' . $ph . ')';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_map('sanitizeBindValue', $values));
        return (int) $this->pdo->lastInsertId();
    }

    public function update(array $data): int
    {
        if (empty($data)) {
            throw new RuntimeException('QueryBuilder: update requires at least one column');
        }
        $set = [];
        $bind = $this->bindings;
        foreach ($data as $col => $val) {
            $set[] = $this->validateIdentifier($col) . ' = ?';
            $bind[] = sanitizeBindValue($val);
        }
        $sql = 'UPDATE ' . $this->requireTable()
            . ' SET ' . implode(', ', $set)
            . $this->whereSql();
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bind);
        return $stmt->rowCount();
    }

    public function delete(): int
    {
        $sql = 'DELETE FROM ' . $this->requireTable() . $this->whereSql();
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->bindings);
        return $stmt->rowCount();
    }

    // ---- internals ----

    private function requireTable(): string
    {
        if ($this->table === null) {
            throw new RuntimeException('QueryBuilder: no table selected');
        }
        return $this->table;
    }

    private function joinSql(): string
    {
        $sql = '';
        foreach ($this->joins as $j) {
            $clause = $j['type'] === 'INNER'
                ? 'JOIN'
                : (strpos($j['type'], 'LEFT') === false ? ($j['type'] === 'RIGHT' ? 'RIGHT JOIN' : 'JOIN') : 'LEFT JOIN');
            $sql .= ' ' . $clause . ' ' . $j['table']
                . ' ON ' . $j['first'] . ' ' . $j['op'] . ' ' . $j['second'];
        }
        return $sql;
    }

    private function whereSql(): string
    {
        if (empty($this->wheres)) {
            return '';
        }
        $body = ltrim(implode('', $this->wheres), ' AND ');
        return ' WHERE ' . $body;
    }

    private function aggregateSql(string $expr): string
    {
        $sql = 'SELECT ' . $expr . ' FROM ' . $this->requireTable();
        $sql .= $this->joinSql();
        $sql .= $this->whereSql();
        if ($this->groupBy) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupBy);
        }
        return $sql;
    }

    private function bind($value): void
    {
        $this->bindings[] = $value;
    }

    /**
     * Allow-list table/column identifiers. Bare alnum/underscore names are used
     * unquoted; anything with a schema/alias is validated strictly to prevent
     * injection via the passed name.
     */
    private function validateIdentifier(string $name): string
    {
        $name = trim($name);
        if ($name === '*') {
            return '*';
        }
        // Allow optional alias "table.column" or "table AS alias" — validate
        // each token strictly.
        if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)(\.([A-Za-z_][A-Za-z0-9_]*))?$/', $name, $m)) {
            return $name;
        }
        if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s+AS\s+([A-Za-z_][A-Za-z0-9_]*)$/i', $name, $m)) {
            return $m[1] . ' AS ' . $m[2];
        }
        throw new RuntimeException("QueryBuilder: invalid identifier '{$name}'");
    }

    private function validateOperator(string $operator): string
    {
        $op = strtoupper(trim($operator));
        $allowed = ['=', '!=', '<>', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'ILIKE'];
        if (!in_array($op, $allowed, true)) {
            throw new RuntimeException("QueryBuilder: invalid operator '{$operator}'");
        }
        return $op;
    }
}

/**
 * Normalise a scalar value for binding (PDO handles most, but guard arrays).
 */
function sanitizeBindValue($value)
{
    if (is_array($value)) {
        throw new RuntimeException('QueryBuilder: cannot bind an array as a scalar');
    }
    if (is_bool($value)) {
        return $value ? 1 : 0;
    }
    return $value;
}
