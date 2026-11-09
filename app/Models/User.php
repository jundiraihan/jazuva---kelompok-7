<?php
require_once __DIR__ . '/Model.php';
require_once __DIR__ . '/../Core/Security.php';
final class User extends Model
{
    protected const TABLE = 'users';
    protected const FILLABLE = ['role_id', 'username', 'password', 'full_name', 'email', 'phone', 'avatar', 'status'];

    public function create(array $attributes): int
    {
        if (isset($attributes['password'])) {
            $attributes['password'] = Security::hashPassword((string)$attributes['password']);
        }
        return parent::create($attributes);
    }

    public function update(int $id, array $attributes): bool
    {
        if (isset($attributes['password'])) {
            $attributes['password'] = Security::hashPassword((string)$attributes['password']);
        }
        return parent::update($id, $attributes);
    }

    public function delete(int $id): bool
    {
        $statement = $this->db->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
        return $statement->execute([$id]);
    }
}
