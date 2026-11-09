<?php
require_once __DIR__ . '/Model.php';
final class Order extends Model
{
    protected const TABLE = 'orders';
    protected const FILLABLE = ['order_number', 'table_id', 'session_id', 'customer_name', 'total_amount', 'discount_amount', 'tax_amount', 'final_amount', 'request_token', 'order_status', 'notes'];

    public function delete(int $id): bool
    {
        throw new LogicException('Pesanan merupakan riwayat transaksi; ubah statusnya alih-alih menghapus.');
    }
}
