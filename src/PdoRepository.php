<?php

declare(strict_types=1);

namespace Alxarafe\ResourcePdo;

use Alxarafe\ResourceController\Contracts\RepositoryContract;
use Alxarafe\ResourceController\Contracts\QueryContract;

/**
 * PdoRepository — RepositoryContract implementation using native PDO.
 */
class PdoRepository implements RepositoryContract
{
    private \PDO $pdo;
    private string $table;
    private string $primaryKey;

    public function __construct(\PDO $pdo, string $table, string $primaryKey = 'id')
    {
        $this->pdo = $pdo;
        $this->table = $table;
        $this->primaryKey = $primaryKey;
    }

    public function query(): QueryContract
    {
        return new PdoQuery($this->pdo, $this->table);
    }

    public function find(string|int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function newRecord(): array
    {
        return [];
    }

    public function save(string|int|null $id, array $data): array
    {
        unset($data[$this->primaryKey]); // Ensure PK isn't blindly inserted/updated
        
        if ($id !== null && $id !== 'new') {
            // Update
            if (empty($data)) {
                return $this->find($id) ?? [];
            }
            
            $sets = [];
            $params = [];
            foreach ($data as $k => $v) {
                $sets[] = "`{$k}` = ?";
                $params[] = $v;
            }
            $params[] = $id;
            
            $sql = "UPDATE `{$this->table}` SET " . implode(', ', $sets) . " WHERE `{$this->primaryKey}` = ?";
            $this->pdo->prepare($sql)->execute($params);
            
            return $this->find($id) ?? [];
        }

        // Insert
        if (empty($data)) {
            throw new \RuntimeException('Cannot insert empty data');
        }
        
        $cols = array_keys($data);
        $placeholders = array_fill(0, count($cols), '?');
        $sql = "INSERT INTO `{$this->table}` (`" . implode('`, `', $cols) . "`) VALUES (" . implode(', ', $placeholders) . ")";
        
        $this->pdo->prepare($sql)->execute(array_values($data));
        $newId = $this->pdo->lastInsertId();
        return $this->find($newId) ?? [];
    }

    public function delete(string|int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM `{$this->table}` WHERE `{$this->primaryKey}` = ?");
        return $stmt->execute([$id]);
    }

    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }

    public function getFieldMetadata(): array
    {
        return [];
    }

    public function storageExists(): bool
    {
        try {
            $stmt = $this->pdo->query("SELECT 1 FROM `{$this->table}` LIMIT 1");
            return $stmt !== false;
        } catch (\Throwable) {
            return false;
        }
    }
}
