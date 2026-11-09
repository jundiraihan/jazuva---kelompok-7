# JAZUVA QA Report

**Tanggal:** 2026-11-02  
**Status:** Alur inti sebelumnya lolos pengujian terkontrol. Integrasi QRIS Midtrans sudah disiapkan, tetapi belum diuji end-to-end karena Server Key Sandbox belum tersedia pada lingkungan pemeriksaan.

## Ringkasan kondisi

Proyek tetap menggunakan struktur PHP/MySQL dan database JAZUVA yang dipindahkan dari laptop sebelumnya. Lingkungan aktif menggunakan PHP 8.2.12 dan database `jazuva_db` dengan 14 tabel serta 15 foreign key. Seluruh 38 file PHP lolos `php -l`.

Pemesanan QR, pemisahan antrean Kitchen/Barista, pembayaran POS, stok berbasis resep, laporan, dan halaman role utama berhasil diuji. Pengujian transaksi yang mengubah data dijalankan pada salinan aplikasi dan database QA, bukan pada data operasional `jazuva_db`. Database aktif hanya menerima migrasi skema aditif setelah dump cadangan dibuat; jumlah baris pembayaran dan bahan baku tetap sama.

## Perbaikan yang diterapkan

- Filter laporan dan CSV menggunakan tanggal pembayaran yang sama, rentang inklusif, dan pembayaran lunas; zona waktu aplikasi diselaraskan ke `Asia/Jakarta`.
- Pendaftaran staf publik dinonaktifkan agar pengunjung anonim tidak dapat memperoleh role kasir.
- Pesanan yang seluruh itemnya dibatalkan kini berstatus `dibatalkan`, bukan `siap`.
- Stok bahan dan catatan mutasi mendukung empat angka desimal; perubahan stok saat edit bahan kini tercatat sebagai penyesuaian yang dapat diaudit.
- Checkout menerapkan service charge pada subtotal, lalu PB1 pada subtotal ditambah service charge. POS, daftar meja, pembayaran, laporan, dan struk menggunakan total yang sama; rincian tagihan disimpan per transaksi.
- Tampilan landing dan POS diperiksa pada lebar mobile; gambar menu favorit tetap di tengah dan CTA “Pesan Sekarang” tidak ditampilkan.
- Dashboard admin memakai data penjualan aktual, bukan angka contoh.
- POS menampilkan QRIS dinamis Midtrans, memverifikasi status dan nominal dari server, dan tidak menganggap QR yang baru dibuat sebagai pembayaran lunas. Timeout tetap dipulihkan sebagai transaksi menunggu; penolakan HTTP definitif ditandai batal agar dapat dicoba ulang.
- Nominal tunai dan metode pembayaran dipertahankan saat detail POS diperbarui, hanya selama sesi meja yang sama. QR yang masih menunggu juga dipertahankan saat polling daftar meja memperbarui detail.

## Validasi

- **Integrasi pada salinan QA:** token QR valid/tidak valid, pembuatan order dan retry idempoten, antrean Kitchen/Barista, transisi status, pemotongan dan pengembalian stok, pembatalan order, validasi kas kurang, pembayaran tunai, kembalian, penutupan sesi meja, serta larangan akses API antar-role.
- **Inventory:** konsumsi 0,0180 kg untuk satu Cappuccino tercatat tepat; perubahan stok naik/turun menghasilkan mutasi audit; input stok negatif ditolak.
- **POS dan tarif:** dengan subtotal Rp20.000, service charge 5% = Rp1.000, PB1 10% dari Rp21.000 = Rp2.100, sehingga total Rp23.100. Pembayaran Rp25.000 menyimpan kembalian Rp1.900; struk menampilkan rincian yang sama.
- **Input pembayaran kasir:** kolom uang diterima dimulai kosong; nominal kurang atau kosong tidak dapat diproses, nominal pas diterima, dan kelebihan dihitung sebagai kembalian. Struk menampilkan jumlah pembayaran dan kembalian.
- **QRIS Midtrans:** alur API memakai Core API `/v2/charge`, mengambil QR resmi, memeriksa status server-ke-server, dan memvalidasi nomor transaksi, ID transaksi, metode, nominal, serta settlement. Lint PHP lolos. Belum ada transaksi Sandbox yang dapat dijalankan karena Server Key belum tersedia pada proses pemeriksaan; settlement nyata belum diverifikasi.
- **Database aktif:** halaman publik, laporan tanggal `2026-11-02`, CSV, API Kitchen/Barista/Kasir, halaman Inventory, dan penolakan pendaftaran staf berjalan setelah migrasi.
- **Browser:** landing dan POS diperiksa di lebar 390 px. Gambar favorit berhasil dimuat dan terpusat; CTA tidak ada; tidak ada overflow horizontal setelah perbaikan; tidak ada error JavaScript pada halaman yang diperiksa.
- **POS polling:** browser mempertahankan nominal Rp70.000 setelah polling pada sesi yang sama dan mengosongkannya saat berpindah ke sesi meja lain. Panel QRIS tampil dengan instruksi yang sesuai; permintaan gateway sengaja tidak dikirim karena kredensial Sandbox belum dikonfirmasi.
- **Migrasi aktif:** dump cadangan dibuat sebelum migrasi. Presisi stok menjadi 4 desimal, rincian pembayaran ditambahkan, dan kolom QRIS Midtrans ditambahkan; jumlah tabel, relasi, bahan baku, pesanan, dan pembayaran tetap.
- **Aktivitas operasional serentak:** selama pemeriksaan browser, daftar POS berubah dari empat menjadi tiga sesi aktif dan jumlah pembayaran bertambah dari dua menjadi tiga. Saya tidak menjalankan pembayaran tunai atau mengoreksi data; perubahan tersebut tidak direkonsiliasi dan riwayat transaksi perlu diperiksa sebelum pengujian live berikutnya.

## Catatan data yang sudah ada

Audit database menemukan **4 item** bertanda stok sudah dipotong tanpa mutasi `pesanan`, serta **1 pesanan aktif** tanpa sesi meja. Data historis tersebut tidak diubah otomatis karena keadaan yang benar tidak dapat dipastikan dari kode saja. Migrasi presisi tidak dapat memulihkan pecahan stok yang sudah terlanjur dibulatkan sebelum migrasi.

## Batas pengujian

- `test_system.php` tidak dijalankan pada database aktif karena skrip tersebut membuat order, memotong stok, dan membayar tanpa rollback.
- Kamera pemindai QR pada perangkat fisik dan settlement QRIS Sandbox/Production belum diuji. Jangan aktifkan pembayaran Midtrans untuk operasional sebelum Server Key Sandbox tersedia dan transaksi uji settlement berhasil.
- Node.js tidak tersedia untuk lint JavaScript mandiri. Pemeriksaan browser pada halaman yang diuji tidak menghasilkan error JavaScript.
- Operasi CRUD menu/meja dan uji lintas perangkat fisik belum dijalankan end-to-end. Jangan menganggap seluruh kemungkinan penggunaan telah tervalidasi hanya berdasarkan smoke test ini.
