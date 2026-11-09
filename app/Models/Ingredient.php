<?php
require_once __DIR__ . '/Model.php';
final class Ingredient extends Model
{
    protected const TABLE = 'ingredients';
    protected const FILLABLE = ['name', 'category', 'stock', 'unit', 'min_stock', 'cost_per_unit'];

    public function delete(int $id): bool
    {
        $history = $this->db->prepare(
            'SELECT (EXISTS(SELECT 1 FROM recipe_details WHERE ingredient_id = ?) OR
                     EXISTS(SELECT 1 FROM stock_movements WHERE ingredient_id = ?))'
        );
        $history->execute([$id, $id]);
        if ((int)$history->fetchColumn() !== 0) {
            throw new LogicException('Bahan yang terhubung ke resep atau audit stok tidak dapat dihapus.');
        }
        return parent::delete($id);
    }
}
