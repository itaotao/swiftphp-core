<?php

namespace SwiftPHP\Core\Database\Builder;

use PDO;
use Exception;

class QueryBuilder
{
    protected $table = '';
    protected $columns = ['*'];
    protected $wheres = [];
    protected $bindings = [];
    protected $orders = [];
    protected $groups = [];
    protected $havings = [];
    protected $limit = null;
    protected $offset = null;
    protected $joins = [];
    protected $unions = [];
    protected $pdo;

    public function __construct(string $table = '')
    {
        $this->table = $table;
        $this->initConnection();
    }

    public static function table(string $table): self
    {
        return new self($table);
    }

    protected function initConnection(): void
    {
        $config = config('database') ?: [];
        if (!empty($config)) {
            \SwiftPHP\Core\Database\ConnectionPool::init($config);
        }
    }

    public function select(...$columns): self
    {
        $this->columns = $columns;
        return $this;
    }

    public function column(...$columns): self
    {
        return $this->select(...$columns);
    }

    public function from(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $this->joins[] = [
            'type' => $type,
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
        ];
        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    public function rightJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'RIGHT');
    }

    public function where($column, $operator = null, $value = null, string $type = 'AND'): self
    {
        if (is_array($column)) {
            foreach ($column as $key => $val) {
                $this->where($key, '=', $val, $type);
            }
            return $this;
        }

        if ($value === null && strpos($operator, '=') !== false) {
            $value = $operator;
            $operator = '=';
        }

        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'bool' => $type,
        ];

        $this->bindings[] = $value;
        return $this;
    }

    public function orWhere($column, $operator = null, $value = null): self
    {
        return $this->where($column, $operator, $value, 'OR');
    }

    public function whereNull($column): self
    {
        $this->wheres[] = [
            'type' => 'null',
            'column' => $column,
            'bool' => 'AND',
        ];
        return $this;
    }

    public function whereNotNull($column): self
    {
        $this->wheres[] = [
            'type' => 'notNull',
            'column' => $column,
            'bool' => 'AND',
        ];
        return $this;
    }

    public function whereIn($column, array $values): self
    {
        $this->wheres[] = [
            'type' => 'in',
            'column' => $column,
            'values' => $values,
            'bool' => 'AND',
        ];
        foreach ($values as $value) {
            $this->bindings[] = $value;
        }
        return $this;
    }

    public function whereNotIn($column, array $values): self
    {
        $this->wheres[] = [
            'type' => 'notIn',
            'column' => $column,
            'values' => $values,
            'bool' => 'AND',
        ];
        foreach ($values as $value) {
            $this->bindings[] = $value;
        }
        return $this;
    }

    public function whereBetween($column, $start, $end): self
    {
        $this->wheres[] = [
            'type' => 'between',
            'column' => $column,
            'start' => $start,
            'end' => $end,
            'bool' => 'AND',
        ];
        $this->bindings[] = $start;
        $this->bindings[] = $end;
        return $this;
    }

    public function whereLike($column, string $value, string $type = 'AND'): self
    {
        return $this->where($column, 'LIKE', '%' . $value . '%', $type);
    }

    public function orWhereLike($column, string $value): self
    {
        return $this->whereLike($column, $value, 'OR');
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orders[] = [
            'column' => $column,
            'direction' => strtoupper($direction),
        ];
        return $this;
    }

    public function orderByDesc(string $column): self
    {
        return $this->orderBy($column, 'DESC');
    }

    public function orderByAsc(string $column): self
    {
        return $this->orderBy($column, 'ASC');
    }

    public function latest(string $column = 'created_at'): self
    {
        return $this->orderBy($column, 'DESC');
    }

    public function oldest(string $column = 'created_at'): self
    {
        return $this->orderBy($column, 'ASC');
    }

    public function groupBy(...$columns): self
    {
        $this->groups = array_merge($this->groups, $columns);
        return $this;
    }

    public function having($column, $operator, $value): self
    {
        $this->havings[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
        ];
        $this->bindings[] = $value;
        return $this;
    }

    public function limit(int $value): self
    {
        $this->limit = $value;
        return $this;
    }

    public function offset(int $value): self
    {
        $this->offset = $value;
        return $this;
    }

    public function skip(int $value): self
    {
        return $this->offset($value);
    }

    public function take(int $value): self
    {
        return $this->limit($value);
    }

    public function forPage(int $page, int $perPage = 15): self
    {
        $this->limit($perPage);
        $this->offset(($page - 1) * $perPage);
        return $this;
    }

    public function union(QueryBuilder $builder, string $type = 'UNION'): self
    {
        $this->unions[] = [
            'type' => $type,
            'builder' => $builder,
        ];
        return $this;
    }

    public function unionAll(QueryBuilder $builder): self
    {
        return $this->union($builder, 'UNION ALL');
    }

    public function get(): array
    {
        $sql = $this->buildSelect();
        return $this->fetchAll($sql);
    }

    public function first(): ?array
    {
        $this->limit(1);
        $sql = $this->buildSelect();
        $result = $this->fetch($sql);
        return $result ?: null;
    }

    public function value(string $column)
    {
        $result = $this->select($column)->first();
        return $result[$column] ?? null;
    }

    public function count(string $column = '*'): int
    {
        $result = $this->selectRaw("COUNT({$column}) as count")->first();
        return (int)($result['count'] ?? 0);
    }

    public function sum(string $column): float
    {
        $result = $this->selectRaw("SUM({$column}) as sum")->first();
        return (float)($result['sum'] ?? 0);
    }

    public function avg(string $column): float
    {
        $result = $this->selectRaw("AVG({$column}) as avg")->first();
        return (float)($result['avg'] ?? 0);
    }

    public function max(string $column)
    {
        $result = $this->selectRaw("MAX({$column}) as max")->first();
        return $result['max'] ?? null;
    }

    public function min(string $column)
    {
        $result = $this->selectRaw("MIN({$column}) as min")->first();
        return $result['min'] ?? null;
    }

    public function selectRaw(string $sql, array $bindings = []): self
    {
        $this->columns = [$sql];
        if (!empty($bindings)) {
            $this->bindings = array_merge($this->bindings, $bindings);
        }
        return $this;
    }

    public function insert(array $data): bool
    {
        $columns = array_keys($data);
        $values = array_values($data);
        $placeholders = implode(', ', array_fill(0, count($values), '?'));

        $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") VALUES ({$placeholders})";

        $this->bindings = $values;
        return $this->execute($sql) > 0;
    }

    public function insertGetId(array $data): int
    {
        $this->insert($data);
        return \SwiftPHP\Core\Database\ConnectionPool::lastInsertId();
    }

    public function update(array $data): int
    {
        $sets = [];
        $values = [];

        foreach ($data as $key => $value) {
            $sets[] = "{$key} = ?";
            $values[] = $value;
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets);

        if (!empty($this->wheres)) {
            $sql .= ' ' . $this->buildWhere();
        }

        $this->bindings = array_merge($values, $this->bindings);
        return $this->execute($sql);
    }

    public function increment(string $column, int $amount = 1): int
    {
        $sql = "UPDATE {$this->table} SET {$column} = {$column} + ?";

        $this->bindings = [$amount];
        if (!empty($this->wheres)) {
            $sql .= ' ' . $this->buildWhere();
        }

        return $this->execute($sql);
    }

    public function decrement(string $column, int $amount = 1): int
    {
        $sql = "UPDATE {$this->table} SET {$column} = {$column} - ?";

        $this->bindings = [$amount];
        if (!empty($this->wheres)) {
            $sql .= ' ' . $this->buildWhere();
        }

        return $this->execute($sql);
    }

    public function delete(): int
    {
        $sql = "DELETE FROM {$this->table}";

        if (!empty($this->wheres)) {
            $sql .= ' ' . $this->buildWhere();
        }

        return $this->execute($sql);
    }

    public function truncate(): bool
    {
        $sql = "TRUNCATE TABLE {$this->table}";
        return $this->execute($sql) !== false;
    }

    public function chunk(int $count, callable $callback): bool
    {
        $page = 1;
        do {
            $this->forPage($page, $count);
            $results = $this->get();

            if (empty($results)) {
                break;
            }

            if ($callback($results, $page) === false) {
                return false;
            }

            $page++;
            $this->resetWheres();

        } while (count($results) === $count);

        return true;
    }

    public function paginate(int $perPage = 15, int $page = 1): array
    {
        $total = $this->count();
        $this->forPage($page, $perPage);
        $data = $this->get();

        return [
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => (int)ceil($total / $perPage),
            'data' => $data,
        ];
    }

    protected function buildSelect(): string
    {
        $sql = 'SELECT ' . implode(', ', $this->columns);
        $sql .= ' FROM ' . $this->table;

        if (!empty($this->joins)) {
            foreach ($this->joins as $join) {
                $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
            }
        }

        if (!empty($this->wheres)) {
            $sql .= ' ' . $this->buildWhere();
        }

        if (!empty($this->groups)) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groups);
        }

        if (!empty($this->havings)) {
            $sql .= ' HAVING ';
            $havingParts = [];
            foreach ($this->havings as $having) {
                $havingParts[] = "{$having['column']} {$having['operator']} ?";
            }
            $sql .= implode(' AND ', $havingParts);
        }

        if (!empty($this->orders)) {
            $orderParts = [];
            foreach ($this->orders as $order) {
                $orderParts[] = "{$order['column']} {$order['direction']}";
            }
            $sql .= ' ORDER BY ' . implode(', ', $orderParts);
        }

        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
        }

        if ($this->offset !== null) {
            $sql .= " OFFSET {$this->offset}";
        }

        if (!empty($this->unions)) {
            foreach ($this->unions as $union) {
                $sql .= " {$union['type']} ({$union['builder']->buildSelect()})";
            }
        }

        return $sql;
    }

    protected function buildWhere(): string
    {
        if (empty($this->wheres)) {
            return '';
        }

        $parts = [];
        $bindingsIndex = 0;

        foreach ($this->wheres as $where) {
            $bool = $where['bool'];
            $connector = strtoupper($bool);

            if ($where['type'] === 'basic') {
                $column = $where['column'];
                $operator = $where['operator'];
                $parts[] = "{$connector} {$column} {$operator} ?";
            } elseif ($where['type'] === 'null') {
                $parts[] = "{$connector} {$where['column']} IS NULL";
            } elseif ($where['type'] === 'notNull') {
                $parts[] = "{$connector} {$where['column']} IS NOT NULL";
            } elseif ($where['type'] === 'in') {
                $placeholders = implode(', ', array_fill(0, count($where['values']), '?'));
                $parts[] = "{$connector} {$where['column']} IN ({$placeholders})";
            } elseif ($where['type'] === 'notIn') {
                $placeholders = implode(', ', array_fill(0, count($where['values']), '?'));
                $parts[] = "{$connector} {$where['column']} NOT IN ({$placeholders})";
            } elseif ($where['type'] === 'between') {
                $parts[] = "{$connector} {$where['column']} BETWEEN ? AND ?";
            }
        }

        $result = implode(' ', $parts);
        if (strtoupper($this->wheres[0]['bool'] ?? 'AND') !== 'AND') {
            $result = 'WHERE ' . $result;
        } else {
            $result = 'WHERE ' . substr($result, 3);
        }

        return $result;
    }

    protected function fetchAll(string $sql): array
    {
        $stmt = $this->prepare($sql);
        $stmt->execute($this->bindings);
        return $stmt->fetchAll();
    }

    protected function fetch(string $sql): ?array
    {
        $stmt = $this->prepare($sql);
        $stmt->execute($this->bindings);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    protected function execute(string $sql): int
    {
        $stmt = $this->prepare($sql);
        $stmt->execute($this->bindings);
        return $stmt->rowCount();
    }

    protected function prepare(string $sql)
    {
        $config = config('database') ?: [];
        \SwiftPHP\Core\Database\ConnectionPool::init($config);
        $pdo = \SwiftPHP\Core\Database\ConnectionPool::getConnection();
        return $pdo->prepare($sql);
    }

    protected function resetWheres(): void
    {
        $this->wheres = [];
        $this->bindings = [];
    }

    public function toSql(): string
    {
        return $this->buildSelect();
    }

    public function bindings(): array
    {
        return $this->bindings;
    }

    public function toSqlWithBindings(): string
    {
        $sql = $this->buildSelect();
        if (empty($this->bindings)) {
            return $sql;
        }

        $parts = explode('?', $sql);
        $result = '';
        foreach ($parts as $i => $part) {
            $result .= $part;
            if (isset($this->bindings[$i])) {
                $value = $this->bindings[$i];
                if (is_string($value)) {
                    $result .= "'" . addslashes($value) . "'";
                } elseif (is_null($value)) {
                    $result .= 'NULL';
                } else {
                    $result .= $value;
                }
            }
        }
        return $result;
    }

    public function dump(): self
    {
        echo "\n[SQL] " . $this->toSqlWithBindings() . "\n";
        return $this;
    }

    public function reset(): self
    {
        $this->columns = ['*'];
        $this->wheres = [];
        $this->bindings = [];
        $this->orders = [];
        $this->groups = [];
        $this->havings = [];
        $this->limit = null;
        $this->offset = null;
        $this->joins = [];
        $this->unions = [];
        return $this;
    }

    public function __toString(): string
    {
        return $this->toSql();
    }
}
