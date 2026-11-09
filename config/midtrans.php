<?php

class MidtransApiException extends RuntimeException {
    public function __construct(string $message, public readonly int $httpStatus) {
        parent::__construct($message);
    }
}

function isMidtransConfigured(): bool {
    return trim((string)getenv('MIDTRANS_SERVER_KEY')) !== '';
}

function midtransRequest(string $method, string $path, ?array $payload = null): array {
    $serverKey = trim((string)getenv('MIDTRANS_SERVER_KEY'));
    if ($serverKey === '') {
        throw new RuntimeException('Kunci Server Midtrans belum dikonfigurasi.');
    }

    $isProduction = filter_var(getenv('MIDTRANS_IS_PRODUCTION') ?: 'false', FILTER_VALIDATE_BOOLEAN);
    $apiBaseUrl = $isProduction
        ? 'https://api.midtrans.com'
        : 'https://api.sandbox.midtrans.com';
    $url = $apiBaseUrl . $path;

    $curl = curl_init($url);
    if ($curl === false) {
        throw new RuntimeException('Tidak dapat memulai koneksi ke Midtrans.');
    }

    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode($serverKey . ':'),
    ];
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ];

    if ($method === 'POST') {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_THROW_ON_ERROR);
    } elseif ($method !== 'GET') {
        curl_close($curl);
        throw new InvalidArgumentException('Metode permintaan Midtrans tidak didukung.');
    }

    curl_setopt_array($curl, $options);
    $body = curl_exec($curl);
    $curlError = curl_error($curl);
    $statusCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($body === false) {
        throw new RuntimeException('Koneksi Midtrans gagal: ' . $curlError);
    }

    try {
        $response = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new RuntimeException('Respons Midtrans bukan JSON yang valid.');
    }

    if ($statusCode < 200 || $statusCode >= 300 || !is_array($response)) {
        $message = is_array($response)
            ? ($response['status_message'] ?? $response['error_messages'][0] ?? 'Permintaan ditolak.')
            : 'Permintaan ditolak.';
        throw new MidtransApiException("Midtrans HTTP {$statusCode}: {$message}", $statusCode);
    }

    return $response;
}

function isTrustedMidtransQrUrl(string $url): bool {
    $parts = parse_url($url);
    if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https') {
        return false;
    }

    $host = strtolower($parts['host'] ?? '');
    $isProduction = filter_var(getenv('MIDTRANS_IS_PRODUCTION') ?: 'false', FILTER_VALIDATE_BOOLEAN);
    $allowedHosts = $isProduction
        ? ['api.midtrans.com', 'api.veritrans.co.id']
        : ['api.sandbox.midtrans.com', 'api.sandbox.veritrans.co.id'];

    return in_array($host, $allowedHosts, true);
}

function midtransQrCodeUrl(string $transactionId): string {
    if (!preg_match('/^[a-zA-Z0-9-]+$/', $transactionId)) {
        throw new RuntimeException('ID transaksi Midtrans tidak valid.');
    }

    $isProduction = filter_var(getenv('MIDTRANS_IS_PRODUCTION') ?: 'false', FILTER_VALIDATE_BOOLEAN);
    $apiBaseUrl = $isProduction
        ? 'https://api.midtrans.com'
        : 'https://api.sandbox.midtrans.com';

    return $apiBaseUrl . '/v2/gopay/' . rawurlencode($transactionId) . '/qr-code';
}

function applyMidtransQrisStatus(PDO $pdo, array $payment, array $status): string {
    $transactionNumber = (string)$payment['transaction_number'];
    $expectedAmount = number_format((float)$payment['total_bill'], 2, '.', '');
    $receivedAmount = isset($status['gross_amount']) && is_numeric($status['gross_amount'])
        ? number_format((float)$status['gross_amount'], 2, '.', '')
        : '';
    $expectedTransactionId = (string)($payment['midtrans_transaction_id'] ?? '');
    $receivedTransactionId = (string)($status['transaction_id'] ?? '');

    if (
        ($status['order_id'] ?? '') !== $transactionNumber ||
        $receivedAmount !== $expectedAmount ||
        ($expectedTransactionId !== '' && $receivedTransactionId !== $expectedTransactionId) ||
        !in_array($status['payment_type'] ?? '', ['gopay', 'qris'], true)
    ) {
        throw new RuntimeException('Informasi transaksi QRIS dari Midtrans tidak cocok dengan tagihan.');
    }

    if (
        ($status['transaction_status'] ?? '') === 'settlement' &&
        isset($status['fraud_status']) &&
        $status['fraud_status'] !== 'accept'
    ) {
        throw new RuntimeException('Status verifikasi pembayaran Midtrans tidak diterima.');
    }

    $pdo->beginTransaction();
    try {
        $stmtPayment = $pdo->prepare("SELECT * FROM payments WHERE id = ? FOR UPDATE");
        $stmtPayment->execute([(int)$payment['id']]);
        $lockedPayment = $stmtPayment->fetch();
        if (!$lockedPayment) {
            throw new RuntimeException('Transaksi QRIS tidak ditemukan.');
        }

        if ($lockedPayment['payment_status'] === 'lunas') {
            $pdo->commit();
            return 'lunas';
        }

        $transactionStatus = $status['transaction_status'] ?? '';
        if ($transactionStatus === 'settlement') {
            $stmtSession = $pdo->prepare("SELECT * FROM table_sessions WHERE id = ? FOR UPDATE");
            $stmtSession->execute([(int)$lockedPayment['session_id']]);
            $session = $stmtSession->fetch();
            if (!$session || $session['status'] !== 'active') {
                throw new RuntimeException('Sesi meja tidak aktif untuk menyelesaikan pembayaran QRIS.');
            }

            $pdo->prepare("UPDATE payments
                           SET payment_status = 'lunas', amount_paid = total_bill, change_amount = 0,
                               midtrans_transaction_id = ?, notes = 'Pembayaran QRIS terverifikasi Midtrans.'
                           WHERE id = ?")
                ->execute([
                    $status['transaction_id'] ?? null,
                    (int)$lockedPayment['id']
                ]);
            $pdo->prepare("UPDATE orders SET order_status = 'selesai' WHERE session_id = ?")
                ->execute([(int)$lockedPayment['session_id']]);
            $pdo->prepare("UPDATE order_items oi
                           JOIN orders o ON oi.order_id = o.id
                           SET oi.item_status = 'selesai'
                           WHERE o.session_id = ? AND oi.item_status != 'dibatalkan'")
                ->execute([(int)$lockedPayment['session_id']]);
            $pdo->prepare("UPDATE table_sessions SET status = 'completed', end_time = NOW() WHERE id = ?")
                ->execute([(int)$lockedPayment['session_id']]);
            $pdo->prepare("UPDATE restaurant_tables SET status = 'tersedia' WHERE id = ?")
                ->execute([(int)$session['table_id']]);

            $pdo->commit();
            return 'lunas';
        }

        $localStatus = $lockedPayment['payment_status'];
        if (in_array($transactionStatus, ['cancel', 'deny', 'expire', 'failure'], true)) {
            $pdo->prepare("UPDATE payments SET payment_status = 'batal' WHERE id = ? AND payment_status = 'menunggu'")
                ->execute([(int)$lockedPayment['id']]);
            $localStatus = 'batal';
        }

        $pdo->commit();
        return $localStatus === 'batal' ? 'batal' : 'menunggu';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
