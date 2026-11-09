# JAZUVA Test Cases

Pengujian yang mengubah data dilakukan pada salinan QA. Smoke test setelah migrasi pada `jazuva_db` hanya membaca data.

| ID | Area | Skenario | Hasil yang diharapkan | Hasil |
|---|---|---|---|---|
| AUTH-01 | Registrasi | GET/POST publik ke halaman daftar staf | Ditolak HTTP 403; jumlah akun tidak bertambah | PASS |
| AUTH-02 | Role/API | Kitchen membuka API Kasir dan Kasir membuka API Kitchen | Ditolak HTTP 403 | PASS |
| QR-01 | QR meja | Menu dengan token tidak valid atau token hilang | Ditolak HTTP 403 | PASS |
| ORDER-01 | Pemesanan | Buka menu dengan QR valid dan kirim order | Menu terbuka dan order tercatat | PASS |
| ORDER-02 | Pemesanan | Kirim ulang request token yang sama | Order yang sama dikembalikan; tidak membuat duplikat | PASS |
| ORDER-03 | Status | Periksa status order baru melalui API | Status awal `menunggu` | PASS |
| KITCHEN-01 | Kitchen | Proses item dengan resep | Item masuk antrean; stok dan mutasi resep berkurang satu kali | PASS |
| KITCHEN-02 | Pembatalan | Batalkan seluruh item setelah diproses | Stok kembali, mutasi pembatalan tercatat, order berstatus `dibatalkan` | PASS |
| BARISTA-01 | Barista | Proses minuman melalui `diproses` lalu `siap` | Antrean dan transisi status berhasil | PASS |
| POS-01 | Kasir | Lihat subtotal order aktif | Detail POS sama dengan item pesanan | PASS |
| POS-02 | Validasi pembayaran | Bayar tunai kurang dari total | Pembayaran ditolak HTTP 400 | PASS |
| POS-03 | QRIS | Kirim pembayaran QRIS manual tanpa Midtrans | Pembayaran ditolak; QRIS hanya melalui QR dinamis | PASS |
| POS-08 | Input kasir | Polling memperbarui detail meja yang sama, lalu ganti sesi meja | Nominal dipertahankan pada sesi sama dan kosong setelah berpindah sesi | PASS |
| POS-09 | QRIS | Polling daftar meja berjalan saat QR dinamis sedang ditampilkan | QR dan status tetap tampil sampai settlement/kedaluwarsa | NOT TESTED |
| POS-10 | QRIS | Buat QRIS untuk sesi yang sudah memiliki transaksi Midtrans menunggu | Gunakan transaksi/QR yang sama atau pulihkan status; jangan membuat charge duplikat | NOT TESTED |
| POS-11 | QRIS | Status Midtrans settlement, nominal/order/ID salah, atau transaksi expired | Hanya settlement dengan detail yang cocok menutup sesi; transaksi tidak cocok ditolak | NOT TESTED |
| POS-12 | QRIS | Gateway timeout atau menolak charge dengan HTTP definitif | Timeout tetap menunggu untuk dipulihkan; penolakan definitif dibatalkan agar bisa dicoba ulang | NOT TESTED |
| POS-04 | Pembayaran | Bayar tunai lebih dari tagihan | Transaksi lunas, kembalian benar, sesi ditutup, meja tersedia | PASS |
| POS-07 | Input kasir | Buka pembayaran tunai; isi kosong, kurang, pas, lalu lebih dari total | Kolom mulai kosong; kosong/kurang diblokir; pembayaran pas atau lebih diizinkan dan kembalian dihitung | PASS |
| POS-05 | Biaya | Subtotal Rp20.000, service 5%, PB1 10% | Service Rp1.000, PB1 Rp2.100, total Rp23.100 | PASS |
| POS-06 | Struk | Cetak transaksi dengan rincian biaya | Subtotal, service, PB1, total, pembayaran dan kembalian tersimpan/ditampilkan benar | PASS |
| INV-01 | Presisi stok | Proses satu Cappuccino dengan resep 0,018 kg kopi | Stok 4,5000 menjadi 4,4820; mutasi 0,0180 | PASS |
| INV-02 | Penyesuaian stok | Edit stok naik dan turun | Nilai empat desimal dan mutasi bertanda benar tercatat | PASS |
| INV-03 | Validasi stok | Masukkan stok negatif | Ditolak tanpa mengubah stok | PASS |
| REPORT-01 | Laporan | Filter tanggal pembayaran `2026-11-02` | Omzet cocok dengan transaksi lunas pada tanggal itu | PASS |
| REPORT-02 | CSV | Ekspor CSV untuk rentang yang sama | Transaksi yang diekspor sama dengan daftar laporan | PASS |
| DATA-01 | Relasi | Hitung tabel/FK dan orphan order–meja/sesi | 14 tabel, 15 FK, tanpa orphan untuk relasi tersebut | PASS |
| DATA-02 | Audit historis | Cek flag stok dan sesi order aktif | 4 flag tanpa mutasi dan 1 order aktif tanpa sesi teridentifikasi | OBSERVED |
| UI-01 | Landing mobile | Viewport 390 px; periksa gambar favorit dan CTA | Lima gambar termuat/terpusat, CTA “Pesan Sekarang” tidak ada, tanpa overflow | PASS |
| UI-02 | POS mobile | Viewport 390 px; periksa rincian dan header | Rincian biaya tampil, tanpa overflow horizontal, tanpa error JS yang teramati | PASS |
| UI-03 | POS QRIS | Pilih metode QRIS tanpa membuat transaksi | Panel QRIS dan instruksi pembuatan QR tampil | PASS |
| STATIC-01 | PHP | `php -l` seluruh file PHP | 38 file lolos | PASS |
| STATIC-02 | JavaScript | Lint mandiri dengan Node.js | Node.js tidak tersedia; halaman landing/POS yang diuji tidak melaporkan error JS | PARTIAL |
| E2E-01 | Perangkat fisik | Pindai QR memakai kamera ponsel | Belum diuji | NOT TESTED |
| E2E-02 | Gateway pembayaran | Buat QR dan verifikasi settlement Midtrans Sandbox | Butuh Server Key Sandbox; belum dijalankan | NOT TESTED |
| CRUD-01 | Admin/Inventory | CRUD penuh menu, meja, dan akun pada browser | Belum dijalankan end-to-end | NOT TESTED |
