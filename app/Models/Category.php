<?php
require_once __DIR__ . '/Model.php';
final class Category extends Model
{
    protected const TABLE = 'categories';
    protected const FILLABLE = ['name', 'slug', 'type', 'target_station', 'icon', 'sort_order', 'is_active'];

    public function delete(int $id): bool
    {
        $menus = $this->db->prepare('SELECT COUNT(*) FROM menus WHERE category_id = ?');
        $menus->execute([$id]);
        if ((int)$menus->fetchColumn() > 0) {
            throw new LogicException('Kategori yang masih memiliki menu tidak dapat dihapus.');
        }
        return parent::delete($id);
    }
}
