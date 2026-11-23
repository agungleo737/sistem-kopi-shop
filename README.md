# Versi Kopi — Sistem Point of Sale (POS) & Manajemen Stok Bahan Baku Bersama (Shared Inventory)

> **"Bukan mood booster, ini mood reset"**  
> Sistem Web Kasir (POS) dan Manajemen Inventori Bahan Baku *Real-Time* untuk **Versi Kopi** (`Jl. Inpres XI No.34, Larangan Utara, Kec. Larangan, Kota Tangerang, Banten`).

---

## Fitur Utama

1. **Multi-Role & Keamanan Berlapis (`Admin` & `Kasir`)**
   - Autentikasi berbasis *Bearer Token* (**Laravel Sanctum**).
   - Proteksi *Brute-Force* (*Rate Limiter* maksimal 5 percobaan gagal per menit).
   - Sanitasi input anti-XSS dan *Security Headers* (`X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`).

2. **Sistem Stok Bahan Baku Bersama (*Shared Raw Material Stock* — `gr` & `ml`)**
   - Satu bahan baku di gudang (seperti *Espresso House Blend*, *Susu Segar Pasteurisasi*, *Sirup Gula Aren*, *Kentang*, *Burger Bun & Patty*) dipakai bersama oleh banyak menu sekaligus.
   - Ketika 1 menu dipesan di kasir, stok bahan gudang otomatis terpotong sesuai takaran resep ukuran **Regular (M)** atau **Large (L)**, dan kapasitas porsi seluruh menu lain yang memakai bahan yang sama otomatis ikut terhitung ulang secara *real-time*.

3. **Peringatan Stok Menipis & Kunci Otomatis (*Auto-Lock*)**
   - **Peringatan Kuning (*Warning*)**: Muncul otomatis di Admin & Kasir saat persentase stok `≤ 20%` dan belum terkunci.
   - **Kunci Otomatis (*Locked*)**: Tombol tambah pesanan di Kasir otomatis terkunci saat sisa stok `≤ 5%` atau `≤ 3 porsi`.

4. **Katalog Menu Terbagi Dua (Menu Satuan & Menu Paketan / Bundling)**
   - Menampilkan **21 Menu Satuan** (*Signature, Coffee, Non-Coffee, Snack*) dan **6 Menu Paketan (*Paket Hemat*)** dalam tampilan grid 2 kolom kanan-kiri lengkap dengan foto produk asli dan fitur pencarian (*search*) di setiap halaman.

5. **Manajemen Promo & Voucher Toko**
   - Mendukung diskon **Persentase (`%`)** dengan batas potongan maksimum maupun **Potongan Nominal Tetap (`Rp`)** beserta syarat minimum belanja.

6. **Transaksi Kasir & Cetak Struk Thermal 80mm**
   - Mendukung pembayaran **Tunai (Cash)** dengan hitung kembalian otomatis dan **QRIS**, dilengkapi fitur cetak struk langsung dari browser.

---

## Rumus Perhitungan Sistem

### 1. Kapasitas Porsi Menu Satuan dari Bahan Gudang Bersama
$$\text{Porsi per Bahan } i = \left\lfloor \frac{\text{Stok GudangSaat Ini}_i}{\text{Takaran Resep per Cup}_i} \right\rfloor$$

$$\text{Stok Efektif Menu} = \min_{i \in \text{Resep}} \left( \left\lfloor \frac{\text{Stok GudangSaat Ini}_i}{\text{Takaran Resep}_i} \right\rfloor \right)$$

### 2. Kapasitas Porsi Menu Paketan (Bundling)
Kebutuhan bahan seluruh komponen paket dijumlahkan terlebih dahulu:
$$\text{Total Kebutuhan Bahan } k = \sum_{j \in \text{Komponen Paket}} \left( \text{Takaran Bahan}_{j,k} \times \text{Qty Komponen}_j \right)$$

$$\text{Stok Paket} = \min_{k} \left( \left\lfloor \frac{\text{Stok GudangSaat Ini}_k}{\text{Total Kebutuhan Bahan } k} \right\rfloor \right)$$

### 3. Persentase & Ambang Peringatan / Kunci Otomatis
$$\text{Persentase Bahan } i = \text{round}\left( \frac{\text{Stok Saat Ini}_i}{\text{Kapasitas Penuh (100\%)}_i} \times 100 \right)$$

- **Status Terkunci (*Locked*)**: $\text{Stok Porsi} \le 3 \quad \text{ATAU} \quad \text{Persentase Stok} \le 5\%$
- **Status Menipis (*Warning*)**: $\text{Persentase Stok} \le 20\%$ dan belum terkunci.

---

## Daftar Menu & Harga Resmi Versi Kopi

### A. Menu Satuan (`21 Menu`)
| No | Nama Menu | Kategori | Regular (M) | Large (L) |
|:--:|:---|:---|---:|---:|
| 1 | **Kopi Susu Versi (Aren Smooth)** | Signature | Rp 15.000 | Rp 18.000 |
| 2 | **Kopi Susu Temu (Aren Temu)** | Signature | Rp 14.000 | Rp 17.000 |
| 3 | **Kopi Susu Java Latte** | Coffee | Rp 12.000 | Rp 15.000 |
| 4 | **Kopi Susu Hazelnut** | Coffee | Rp 16.000 | Rp 19.000 |
| 5 | **Kopi Susu Butterscotch** | Coffee | Rp 16.000 | Rp 19.000 |
| 6 | **Kopi Caramel** | Coffee | Rp 16.000 | Rp 19.000 |
| 7 | **Versinya Salted Caramel** | Signature | Rp 16.000 | Rp 19.000 |
| 8 | **Americano** | Coffee | Rp 12.000 | Rp 15.000 |
| 9 | **Versi Ramu** *(New)* | Signature | Rp 15.000 | Rp 18.000 |
| 10 | **Versi Rindu** | Signature | Rp 15.000 | Rp 18.000 |
| 11 | **Matcha Coffee** | Signature | Rp 16.000 | Rp 19.000 |
| 12 | **Green Tea Latte (Matcha Latte)** | Non-Coffee | Rp 15.000 | Rp 18.000 |
| 13 | **Lychee Tea** | Non-Coffee | Rp 15.000 | Rp 18.000 |
| 14 | **Lemon Tea** | Non-Coffee | Rp 8.000 | Rp 11.000 |
| 15 | **Thai Tea** | Non-Coffee | Rp 15.000 | Rp 18.000 |
| 16 | **Ice Black Tea** | Non-Coffee | Rp 5.000 | Rp 8.000 |
| 17 | **Cireng Isi Ayam (5 pcs)** | Snack | Rp 10.000 | Rp 10.000 |
| 18 | **Dimsum Keju (4 pcs)** | Snack | Rp 13.000 | Rp 13.000 |
| 19 | **French Fries (Kentang Kecil)** | Snack | Rp 8.000 | Rp 10.000 |
| 20 | **Kentang Potong Gede (Crinkle Cut)** | Snack | Rp 10.000 | Rp 12.000 |
| 21 | **Burger** *(New)* | Snack | Rp 10.000 | Rp 13.000 |

### B. Menu Paketan / Paket Hemat (`6 Paket`)
| No | Nama Paket | Komponen Isi Paket | Harga |
|:--:|:---|:---|---:|
| 1 | **Paket Hemat Burger + French Fries** | 1× Burger + 1× French Fries | Rp 15.000 |
| 2 | **Paket Hemat Ice Black Tea** | 1× Burger + 1× French Fries + 1× Ice Black Tea | Rp 20.000 |
| 3 | **Paket Hemat Lemon Tea** | 1× Burger + 1× French Fries + 1× Lemon Tea | Rp 23.000 |
| 4 | **Paket Hemat Kopi Java** | 1× Burger + 1× French Fries + 1× Kopi Susu Java Latte | Rp 27.000 |
| 5 | **Bundling Kentang + Kopi Temu** | 1× French Fries + 1× Kopi Susu Temu (Aren Temu) | Rp 22.000 |
| 6 | **Versinya Teman Dirumah Berlima (Java Latte 5)** | 5× Kopi Susu Java Latte | Rp 60.000 |

---

## Teknologi yang Digunakan

- **Backend API (`capstone/versikopi-api`)**: PHP 8.2+, Laravel 12, Laravel Sanctum, MariaDB / MySQL (`versikopi_db`).
- **Frontend Web (`capstone/versikopi-web`)**: React 19, Vite 8, React Router v7, Axios.
- **Database Schema**: Tersedia migrasi Laravel otomatis serta file SQL siap impor di `capstone/database_mariadb_versikopi.sql`.

---

## Cara Menjalankan Project

### 1. Persiapan Database MariaDB / MySQL
Nyalakan **MariaDB / MySQL** di Laragon atau XAMPP, lalu klik 2x file `capstone/AKTIFKAN_MARIADB.bat` (atau jalankan manual):
```bash
cd capstone/versikopi-api
php artisan migrate --seed --force
php artisan serve --host=127.0.0.1 --port=8000
```

### 2. Menjalankan Frontend Web
```bash
cd capstone/versikopi-web
npm install
npm run dev
```
Buka browser di **`http://localhost:5173`**.

---

## Akun Login Default

| Role | Email | Password |
|:---|:---|:---|
| **Admin / Owner** | `admin@versikopi.test` | `password` |
| **Kasir / Barista** | `kasir@versikopi.test` | `password` |
