<?php
require_once __DIR__ . '/Model.php';
final class Payment extends Model
{
    protected const TABLE = 'payments';
    protected const FILLABLE = ['order_id', 'session_id', 'transaction_number', 'payment_method', 'subtotal', 'service_charge_amount', 'tax_amount', 'midtrans_transaction_id', 'qris_qr_url', 'total_bill', 'amount_paid', 'change_amount', 'payment_status', 'cashier_id', 'notes'];

    public function delete(int $id): bool
    {
        throw new LogicException('Catatan pembayaran bersifat audit dan tidak dapat dihapus.');
    }
}
