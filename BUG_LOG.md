# JAZUVA Bug Log

| ID | Prioritas | Temuan | Status | Perbaikan / tindak lanjut |
|---|---|---|---|---|
| BUG-01 | Tinggi | Pendaftaran publik membuat akun aktif dengan role kasir. | Fixed | `register.php` kini menolak pendaftaran publik dengan HTTP 403; akun staf dibuat melalui administrasi. |
| BUG-02 | Sedang | Jika semua item order dibatalkan, API Kitchen/Barista menandai order `siap`. | Fixed | Status keseluruhan membedakan semua-item-batal dari semua-item-siap. Diuji melalui pembatalan dan pengembalian stok. |
| BUG-03 | Sedang | Kolom stok dan mutasi hanya presisi dua desimal, sedangkan resep memakai empat desimal. | Fixed | Skema dan migrasi mengubah `stock`, `min_stock`, dan nilai mutasi ke empat desimal. Migrasi diterapkan setelah backup. Nilai lama yang sudah membulat tidak dapat dipulihkan. |
| BUG-04 | Sedang | Edit stok bahan mengubah jumlah langsung tanpa riwayat mutasi. | Fixed | Perubahan edit kini atomik dan membuat mutasi `penyesuaian`; riwayat menunjukkan arah naik/turun. Nilai negatif ditolak. |
| BUG-05 | Sedang | Pajak dan service charge dapat disimpan, tetapi tidak masuk tagihan POS dan struk. | Fixed | Tarif 0–100% divalidasi; service charge dihitung dari subtotal, PB1 dari subtotal + service charge. Rincian disimpan pada pembayaran dan dicetak pada struk. |
| BUG-06 | Rendah | Footer landing dan header POS dapat menyebabkan overflow horizontal di layar mobile. | Fixed | Grid footer diringkas pada layar kecil; header POS membungkus kontrol dan tabel POS menyesuaikan lebar. Diuji pada viewport 390 px. |
| BUG-07 | Sedang | Beberapa metrik laporan berpotensi memakai tanggal/order yang berbeda dari tanggal penjualan/pembayaran. | Fixed | Ringkasan, transaksi, menu terlaris, dan CSV memakai periode tanggal pembayaran yang sama dan hanya menghitung pembayaran lunas. |

## Anomali data historis — tidak diubah

- Empat item memiliki `stock_deducted = 1` tanpa catatan pengurangan stok yang cocok.
- Satu pesanan aktif tidak memiliki sesi meja.
- Nilai stok historis yang sudah kehilangan pecahan akibat pembulatan tidak dapat direkonstruksi dengan migrasi.

Anomali tersebut perlu diverifikasi terhadap bukti operasional sebelum koreksi manual.
