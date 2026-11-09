<?php
require_once __DIR__ . '/Model.php';
final class StockMovement extends Model
{
    protected const TABLE = 'stock_movements';
    protected const FILLABLE = ['ingredient_id', 'movement_type', 'quantity', 'previous_stock', 'current_stock', 'reference_id', 'reference_type', 'notes', 'created_by'];

    public function delete(int $id): bool
    {
        throw new LogicException('Mutasi stok bersifat audit dan tidak dapat dihapus.');
    }
}
