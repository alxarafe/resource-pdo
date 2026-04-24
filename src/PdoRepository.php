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
        $metadata = $this->getFieldMetadata();
        $errors = [];
        foreach ($data as $key => $value) {
            if (!isset($metadata[$key])) continue;
            $meta = $metadata[$key];
            if (($meta['required'] ?? false) && ($value === null || $value === '')) {
                $errors[] = "Field '{$key}' is required.";
            } elseif ($value !== null && $value !== '') {
                if (isset($meta['min']) && is_numeric($value) && $value < $meta['min']) {
                    $errors[] = "Field '{$key}': {$value} < min ({$meta['min']}).";
                }
                if (isset($meta['max']) && is_numeric($value) && $value > $meta['max']) {
                    $errors[] = "Field '{$key}': {$value} > max ({$meta['max']}).";
                }
                if (isset($meta['maxlength']) && is_string($value) && mb_strlen((string)$value) > $meta['maxlength']) {
                    $errors[] = "Field '{$key}': length > {$meta['maxlength']}.";
                }
            }
        }
        if (!empty($errors)) {
            throw new \RuntimeException('Validation failed: ' . implode('; ', $errors));
        }

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
        try {
            $stmt = $this->pdo->query("SHOW COLUMNS FROM `{$this->table}`");
            $columns = $stmt->fetchAll(\PDO::FETCH_OBJ);
        } catch (\Throwable) {
            return [];
        }

        $fields = [];
        foreach ($columns as $col) {
            $name = $col->Field;
            $dbType = $col->Type;
            $nullable = $col->Null === 'YES';
            
            $length = null;
            if (preg_match('/\((.*)\)/', $dbType, $m)) {
                $length = $m[1];
            }

            $fields[$name] = [
                'field' => $name,
                'label' => ucfirst(str_replace('_', ' ', $name)),
                'genericType' => self::mapType($dbType),
                'dbType' => $dbType,
                'required' => !$nullable && $col->Default === null && ($col->Key ?? '') !== 'PRI' && $col->Extra !== 'auto_increment',
                'length' => is_numeric($length) ? (int) $length : $length,
                'nullable' => $nullable,
                'default' => $col->Default,
            ];

            $limits = self::computeNumericLimits($dbType);
            $fields[$name] = array_merge($fields[$name], $limits);
        }
        return $fields;
    }

    private static function computeNumericLimits(string $dbType): array
    {
        $t = strtolower($dbType);
        $unsigned = str_contains($t, 'unsigned');

        $intRanges = [
            'tinyint'   => ['signed' => [-128, 127],           'unsigned' => [0, 255]],
            'smallint'  => ['signed' => [-32768, 32767],        'unsigned' => [0, 65535]],
            'mediumint' => ['signed' => [-8388608, 8388607],    'unsigned' => [0, 16777215]],
            'bigint'    => ['signed' => [PHP_INT_MIN, PHP_INT_MAX], 'unsigned' => [0, PHP_INT_MAX]],
            'int'       => ['signed' => [-2147483648, 2147483647], 'unsigned' => [0, 4294967295]],
        ];

        foreach ($intRanges as $type => $ranges) {
            if (str_contains($t, $type)) {
                $range = $unsigned ? $ranges['unsigned'] : $ranges['signed'];
                return ['min' => $range[0], 'max' => $range[1], 'step' => 1, 'unsigned' => $unsigned];
            }
        }

        if (preg_match('/(?:decimal|numeric)\((\d+),(\d+)\)/', $t, $m)) {
            $precision = (int) $m[1];
            $scale = (int) $m[2];
            $maxVal = (float) (str_repeat('9', $precision - $scale) . '.' . str_repeat('9', $scale));
            $minVal = $unsigned ? 0 : -$maxVal;
            $step = $scale > 0 ? (float) ('0.' . str_repeat('0', $scale - 1) . '1') : 1;
            return ['min' => $minVal, 'max' => $maxVal, 'step' => $step,
                    'precision' => $precision, 'scale' => $scale, 'unsigned' => $unsigned];
        }

        if (str_contains($t, 'float') || str_contains($t, 'double')) {
            return ['unsigned' => $unsigned];
        }

        return [];
    }

    private static function mapType(string $t): string
    {
        $t = strtolower($t);
        return match (true) {
            str_contains($t, 'bool'), str_contains($t, 'tinyint') => 'boolean',
            str_contains($t, 'int') => 'integer',
            str_contains($t, 'decimal'), str_contains($t, 'float'), str_contains($t, 'double') => 'decimal',
            str_contains($t, 'datetime'), str_contains($t, 'timestamp') => 'datetime',
            str_contains($t, 'date') => 'date',
            str_contains($t, 'time') => 'time',
            str_contains($t, 'text'), str_contains($t, 'blob') => 'textarea',
            default => 'text',
        };
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
