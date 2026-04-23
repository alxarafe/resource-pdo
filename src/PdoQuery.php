<?php

declare(strict_types=1);

namespace Alxarafe\ResourcePdo;

use Alxarafe\ResourceController\Contracts\QueryContract;
use Alxarafe\ResourceController\Result\PaginatedResult;

/**
 * PdoQuery — QueryContract implementation wrapping native PDO.
 */
class PdoQuery implements QueryContract
{
    private \PDO $pdo;
    private string $table;
    
    /** @var string[] */
    private array $wheres = [];
    
    /** @var array<int, mixed> */
    private array $params = [];
    
    /** @var string[] */
    private array $orders = [];

    public function __construct(\PDO $pdo, string $table)
    {
        $this->pdo = $pdo;
        $this->table = $table;
    }

    public function where(string $field, string $operator, mixed $value): static
    {
        $this->wheres[] = "`{$field}` {$operator} ?";
        $this->params[] = $value;
        return $this;
    }

    public function whereNull(string $field): static
    {
        $this->wheres[] = "`{$field}` IS NULL";
        return $this;
    }

    public function whereNotNull(string $field): static
    {
        $this->wheres[] = "`{$field}` IS NOT NULL";
        return $this;
    }

    public function whereIn(string $field, array $values): static
    {
        if (empty($values)) {
            $this->wheres[] = '1=0';
            return $this;
        }
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $this->wheres[] = "`{$field}` IN ({$placeholders})";
        $this->params = array_merge($this->params, array_values($values));
        return $this;
    }

    public function whereNotIn(string $field, array $values): static
    {
        if (empty($values)) {
            return $this;
        }
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $this->wheres[] = "`{$field}` NOT IN ({$placeholders})";
        $this->params = array_merge($this->params, array_values($values));
        return $this;
    }

    public function search(array $fields, string $term): static
    {
        if (empty($fields) || $term === '') {
            return $this;
        }
        $clauses = [];
        foreach ($fields as $f) {
            $clauses[] = "LOWER(`{$f}`) LIKE LOWER(?)";
            $this->params[] = "%{$term}%";
        }
        $this->wheres[] = '(' . implode(' OR ', $clauses) . ')';
        return $this;
    }

    public function with(array $relations): static
    {
        // PDO adapter is a basic implementation and doesn't support auto-eager loading.
        return $this;
    }

    public function orderBy(string $field, string $direction = 'ASC'): static
    {
        $dir = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orders[] = "`{$field}` {$dir}";
        return $this;
    }

    public function paginate(int $limit, int $offset = 0): PaginatedResult
    {
        $total = $this->count();
        $whereSql = empty($this->wheres) ? '' : 'WHERE ' . implode(' AND ', $this->wheres);
        $orderSql = empty($this->orders) ? '' : 'ORDER BY ' . implode(', ', $this->orders);
        
        $sql = "SELECT * FROM `{$this->table}` {$whereSql} {$orderSql} LIMIT {$limit} OFFSET {$offset}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->params);
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return new PaginatedResult($items ?: [], $total, $limit, $offset);
    }

    public function count(): int
    {
        $whereSql = empty($this->wheres) ? '' : 'WHERE ' . implode(' AND ', $this->wheres);
        $sql = "SELECT COUNT(*) FROM `{$this->table}` {$whereSql}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->params);
        return (int) $stmt->fetchColumn();
    }

    public function whereGroup(callable $callback): static
    {
        // For simplicity, isolated group handling is skipped in this basic adapter.
        return $this;
    }
}
