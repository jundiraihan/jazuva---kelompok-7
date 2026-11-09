<?php
require_once __DIR__ . '/Model.php';
final class TableSession extends Model
{
    protected const TABLE = 'table_sessions';
    protected const FILLABLE = ['table_id', 'session_token', 'customer_name', 'status', 'start_time', 'end_time', 'total_amount'];

    public function delete(int $id): bool
    {
        $statement = $this->db->prepare(
            "UPDATE table_sessions SET status = 'cancelled', end_time = CURRENT_TIMESTAMP
             WHERE id = ? AND status = 'active'"
        );
        return $statement->execute([$id]);
    }
}
