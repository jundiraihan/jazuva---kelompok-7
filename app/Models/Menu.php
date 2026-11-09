<?php
require_once __DIR__ . '/Model.php';
final class Menu extends Model
{
    protected const TABLE = 'menus';
    protected const FILLABLE = ['category_id', 'name', 'slug', 'description', 'price', 'cost_price', 'image', 'is_available', 'is_favorite'];

    public function delete(int $id): bool
    {
        $statement = $this->db->prepare("UPDATE menus SET is_available = 0 WHERE id = ?");
        return $statement->execute([$id]);
    }
}
