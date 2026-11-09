<?php
require_once __DIR__ . '/Model.php';
final class OrderItem extends Model
{
    protected const TABLE = 'order_items';
    protected const FILLABLE = ['order_id', 'menu_id', 'quantity', 'unit_price', 'subtotal', 'notes', 'station', 'item_status', 'stock_deducted'];

    public function delete(int $id): bool
    {
        throw new LogicException('Detail pesanan dipertahankan sebagai bagian dari riwayat transaksi.');
    }
}
