<?php
require_once __DIR__ . '/Model.php';
final class Table extends Model
{
    protected const TABLE = 'restaurant_tables';
    protected const FILLABLE = ['table_number', 'capacity', 'status', 'pos_x', 'pos_y', 'qr_code_file', 'token', 'is_active'];

    public function delete(int $id): bool
    {
        $statement = $this->db->prepare("UPDATE restaurant_tables SET is_active = 0 WHERE id = ?");
        return $statement->execute([$id]);
    }
}
