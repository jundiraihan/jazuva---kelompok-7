<?php
declare(strict_types=1);

abstract class Model
{
    protected const TABLE = '';
    protected const FILLABLE = [];
    protected PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->connection();
    }

    public function all(int $limit = 500): array
    {
        $limit = max(1, min($limit, 500));
        return $this->db->query('SELECT * FROM `' . static::TABLE . '` ORDER BY `id` DESC LIMIT ' . $limit)->fetchAll();
    }

    public function find(int $id): ?array
    {
        $statement = $this->db->prepare('SELECT * FROM `' . static::TABLE . '` WHERE id = ? LIMIT 1');
        $statement->execute([$id]);
        return $statement->fetch() ?: null;
    }

    public function create(array $attributes): int
    {
        $values = $this->filter($attributes);
        if (!$values) {
            throw new InvalidArgumentException('Tidak ada kolom yang dapat disimpan.');
        }
        $columns = array_keys($values);
        $sql = 'INSERT INTO `' . static::TABLE . '` (`' . implode('`, `', $columns) . '`) VALUES (' .
            implode(', ', array_fill(0, count($columns), '?')) . ')';
        $statement = $this->db->prepare($sql);
        $statement->execute(array_values($values));
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $attributes): bool
    {
        $values = $this->filter($attributes);
        if (!$values) {
            throw new InvalidArgumentException('Tidak ada kolom yang dapat diperbarui.');
        }
        $set = implode(', ', array_map(static fn(string $column): string => '`' . $column . '` = ?', array_keys($values)));
        $statement = $this->db->prepare('UPDATE `' . static::TABLE . '` SET ' . $set . ' WHERE id = ?');
        return $statement->execute([...array_values($values), $id]);
    }

    public function delete(int $id): bool
    {
        $statement = $this->db->prepare('DELETE FROM `' . static::TABLE . '` WHERE id = ?');
        return $statement->execute([$id]);
    }

    private function filter(array $attributes): array
    {
        return array_intersect_key($attributes, array_flip(static::FILLABLE));
    }
}
