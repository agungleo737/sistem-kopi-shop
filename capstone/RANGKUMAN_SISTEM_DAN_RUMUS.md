# Rangkuman Sistem, Fitur & Rumus Perhitungan — Versi Kopi POS

Dokumen ini merangkum seluruh arsitektur fitur, keamanan, dan **rumus perhitungan matematika** yang berjalan di sistem **Versi Kopi (Kasir & Admin)**.

---

## 1. Ringkasan Fitur yang Telah Diterapkan

### A. Tampilan & Desain (UI/UX)
1. **Bebas Emoji & Ikon Vektor Bersih**: Seluruh emoji bawaan dihapus dan diganti dengan ikon SVG garis (*clean line icons*) pada [Icons.jsx](file:///c:/Users/Agungleo/capstone/versikopi-web/src/components/Icons.jsx).
2. **Penghapusan Indikator "Kasir Aktif" / "Admin Aktif"**: Teks status aktif beserta bulatan hijau telah dihapus dari halaman Login, Sidebar Kasir, dan Sidebar Admin.
3. **Layout 2 Kolom Kanan-Kiri (Menu Satuan & Paketan)**:
   - Di **Halaman Kasir** ([Kasir.jsx](file:///c:/Users/Agungleo/capstone/versikopi-web/src/pages/Kasir.jsx)): Kartu menu ditampilkan bersebelahan **2 kolom kanan-kiri** (`.pos-menu-grid`).
   - Di **Halaman Admin — Kelola Menu** ([Admin.jsx](file:///c:/Users/Agungleo/capstone/versikopi-web/src/pages/Admin.jsx)): Daftar menu juga ditampilkan **2 kolom kanan-kiri** (`.admin-menu-grid`) agar rapi dan tidak terpotong ke samping.
4. **Pemisahan Menu Satuan vs Menu Paketan (Bundling)**:
   - Tersedia *segmented switcher* untuk berpindah antara **Menu Satuan** dan **Menu Paketan**.
   - **Menu Paketan** berisi gabungan beberapa produk satuan (misal: *1x Kopi Susu Gula Aren + 1x Croissant*).
5. **Sistem Pencarian (Search) Menyeluruh**:
   - Tersedia di semua tab Kasir (Menu Kasir, Riwayat Pesanan, Promo Toko, Panduan Resep) dan semua tab Admin (Ringkasan Transaksi, Kelola Menu, Promo Toko, Resep & Takaran, Stok Bahan Gudang, dan Riwayat Mutasi Stok).

---

## 2. Rumus Perhitungan Stok Bahan Baku Bersama (Shared Ingredient Stock)

Di sistem ini, **100% perhitungan stok menu mengacu pada Stok Bahan Baku Gudang (`ingredients_stock`)** dalam satuan gram (`gr`) dan mililiter (`ml`). Satu bahan baku dipakai bersama oleh banyak menu sekaligus.

### A. Variabel Dasar Bahan Baku
Setiap bahan baku $i$ di gudang memiliki:
- $S_i$ = Stok bahan saat ini (`current_stock`, dalam `gr` atau `ml`)
- $M_i$ = Kapasitas penuh 100% bahan (`max_stock`, dalam `gr` atau `ml`)
- $T_{P, i}$ = Takaran bahan $i$ yang dibutuhkan untuk membuat 1 porsi produk $P$ (ukuran Regular `M` sebagai acuan dasar katalog, atau ukuran Large `L` saat transaksi)

### B. Rumus Persentase Stok Bahan Baku ($\%_i$)
$$\text{Persentase Bahan } (\%_i) = \text{round}\left( \frac{S_i}{M_i} \times 100 \right)$$

### C. Rumus Kapasitas Porsi per Bahan ($K_{P, i}$)
Berapa porsi produk $P$ yang sanggup dibuat oleh bahan $i$ saja:
$$K_{P, i} = \left\lfloor \frac{S_i}{T_{P, i}} \right\rfloor$$
*(Menggunakan pembulatan ke bawah / `floor` karena porsi cup tidak bisa pecahan).*

### D. Rumus Stok Porsi Efektif Produk ($Stok_P$) & Persentase Produk ($\%_P$)
Karena 1 menu membutuhkan beberapa bahan baku sekaligus (misal: Kopi + Susu + Gula Aren), maka jumlah porsi menu yang bisa dibuat ditentukan oleh **bahan yang paling sedikit mencukupi (bahan pembatas / bottleneck)**:

$$Stok_P = \min_{i \in \text{Resep}(P)} \left( \left\lfloor \frac{S_i}{T_{P, i}} \right\rfloor \right)$$

$$\%_P = \min_{i \in \text{Resep}(P)} \left( \%_i \right)$$

---

## 3. Contoh Simulasi Nyata (Sesuai Skenario Kopi A & Kopi B)

Misalkan di **Stok Bahan Gudang** terdapat 2 bahan dengan kondisi awal (100%):
- **Kopi**: $S_{\text{kopi}} = 12\text{ gr}$ (100%)
- **Susu**: $S_{\text{susu}} = 20\text{ ml}$ (100%)

Terdapat 2 menu yang memakai bahan gudang yang sama:
1. **Kopi A** membutuhkan: **2 gr Kopi** + **4 ml Susu**
2. **Kopi B** membutuhkan: **4 gr Kopi** + **4 ml Susu**

### Langkah 1: Perhitungan Stok Awal (Sebelum Ada Pesanan)
- **Kopi A**:
  - Dari Kopi: $\lfloor 12 / 2 \rfloor = 6\text{ porsi}$
  - Dari Susu: $\lfloor 20 / 4 \rfloor = 5\text{ porsi}$
  - **Stok Porsi Kopi A** = $\min(6, 5) = \mathbf{5\text{ porsi}}$ (dibatasi oleh Susu).
- **Kopi B**:
  - Dari Kopi: $\lfloor 12 / 4 \rfloor = 3\text{ porsi}$
  - Dari Susu: $\lfloor 20 / 4 \rfloor = 5\text{ porsi}$
  - **Stok Porsi Kopi B** = $\min(3, 5) = \mathbf{3\text{ porsi}}$ (dibatasi oleh Kopi).

### Langkah 2: Saat Kopi B Dipesan 1 Cup
Ketika kasir memproses pesanan **1x Kopi B**, sistem memotong bahan baku gudang sesuai resep Kopi B (`4 gr kopi` dan `4 ml susu`):
- Sisa **Kopi** di gudang: $12 - 4 = \mathbf{8\text{ gr}}$
- Sisa **Susu** di gudang: $20 - 4 = \mathbf{16\text{ ml}}$

### Langkah 3: Dampaknya ke Stok Kopi A & Kopi B Secara Otomatis
Tanpa Kopi A dipesan sekalipun, stok Kopi A ikut berkurang otomatis karena memakai bahan gudang yang sama:
- **Stok Porsi Kopi A Sekarang**:
  - Dari sisa Kopi: $\lfloor 8 / 2 \rfloor = 4\text{ porsi}$
  - Dari sisa Susu: $\lfloor 16 / 4 \rfloor = 4\text{ porsi}$
  - **Stok Kopi A** = $\min(4, 4) = \mathbf{4\text{ porsi}}$ *(turun dari 5 menjadi 4 porsi)*.
- **Stok Porsi Kopi B Sekarang**:
  - Dari sisa Kopi: $\lfloor 8 / 4 \rfloor = 2\text{ porsi}$
  - Dari sisa Susu: $\lfloor 16 / 4 \rfloor = 4\text{ porsi}$
  - **Stok Kopi B** = $\min(2, 4) = \mathbf{2\text{ porsi}}$ *(turun dari 3 menjadi 2 porsi)*.

---

## 4. Rumus Menu Paketan (Bundling)

Menu Paketan $B$ terdiri dari daftar produk satuan beserta kuantitasnya: $\{(P_1, q_1), (P_2, q_2), \dots\}$.

1. **Akumulasi Kebutuhan Bahan Paket ($T_{B, i}$)**:
   $$T_{B, i} = \sum_{k} \left( q_k \times T_{P_k, i} \right)$$
2. **Kapasitas Porsi Paket ($Stok_B$)**:
   $$Stok_B = \min_{i \in \text{Bahan Paket}} \left( \left\lfloor \frac{S_i}{T_{B, i}} \right\rfloor \right)$$
3. **Pemotongan Saat Paket Dipesan ($Q$ Paket)**:
   Setiap bahan baku $i$ dari seluruh produk di dalam paket langsung dipotong sebesar:
   $$\Delta S_i = Q \times \sum_{k} \left( q_k \times T_{P_k, i} \right)$$

---

## 5. Rumus Peringatan Stok ($\le 20\%$) & Kunci Tombol ($\le 5\%$ / $\le 3$)

Setiap produk dievaluasi secara otomatis di Backend ([PosController.php](file:///c:/Users/Agungleo/capstone/versikopi-api/app/Http/Controllers/PosController.php)) dan secara *real-time* di Frontend ([Kasir.jsx](file:///c:/Users/Agungleo/capstone/versikopi-web/src/pages/Kasir.jsx)):

### A. Status Terkunci Otomatis (`is_locked`)
Tombol tambah (`+`) di Kasir otomatis **terkunci** (berubah menjadi ikon gembok dan tidak bisa diklik) apabila memenuhi **salah satu** syarat berikut:
$$\text{is\_locked} = (Stok_P \le 3) \quad \text{ATAU} \quad (\%_P \le 5\%)$$

### B. Status Peringatan Menipis (`is_warning`)
Peringatan kuning muncul di **Banner Kasir** dan **Banner Admin** apabila menu belum terkunci namun memenuhi **salah satu** syarat berikut:
$$\text{is\_warning} = \neg(\text{is\_locked}) \quad \text{DAN} \quad (\%_P \le 20\%)$$

### C. Rumus Batas Maksimum Porsi yang Bisa Dimasukkan ke Keranjang (`Orderable Qty`)
Agar pesanan di keranjang tidak menghabiskan stok melewati batas kunci ($\le 3$ porsi atau $\le 5\%$), jumlah maksimum cup yang boleh diambil kasir untuk ukuran cup yang dipilih (`M` atau `L`) adalah:
$$\text{Batas Kunci Bahan } i = \max\left( 3, \;\left\lfloor \frac{M_i}{T_{P, i}} \times 0.05 \right\rfloor \right)$$
$$\text{Bisa Dipesan}_P = \min_{i \in \text{Resep}(P)} \left( \max\left(0, \;\left\lfloor \frac{S_i}{T_{P, i}} \right\rfloor - \text{Batas Kunci Bahan } i \right) \right)$$

---

## 6. Rumus Perhitungan Transaksi, Promo Toko, Pajak & Pembayaran

Saat kasir memproses pesanan di keranjang:

1. **Subtotal Pesanan**:
   $$\text{Subtotal} = \sum_{j=1}^{n} (\text{Harga Satuan}_j \times \text{Qty}_j)$$
   *(Harga satuan mengikuti ukuran cup: `price_m` untuk Regular M, `price_l` untuk Large L).*

2. **Potongan Promo Toko ($\text{Diskon}$)**:
   Syarat aktif: `promo.active == 1` dan $\text{Subtotal} \ge \text{min\_subtotal}$.
   - **Jika Tipe Promo = Persentase (`percent`)**:
     $$\text{Diskon Awal} = \text{round}\left( \frac{\text{Subtotal} \times \text{Nilai Persen}}{100} \right)$$
     Jika `max_discount > 0`, maka:
     $$\text{Diskon} = \min(\text{Diskon Awal}, \;\text{max\_discount}, \;\text{Subtotal})$$
   - **Jika Tipe Promo = Potongan Tetap (`fixed`)**:
     $$\text{Diskon} = \min(\text{Nilai Potongan Rp}, \;\text{Subtotal})$$

3. **Dasar Pengenaan Pajak & Layanan ($\text{DPP}$)**:
   $$\text{DPP} = \max(0, \;\text{Subtotal} - \text{Diskon})$$

4. **Pajak & Biaya Layanan**:
   $$\text{Pajak} = \text{round}\left( \frac{\text{DPP} \times \text{tax\_percent}}{100} \right)$$
   $$\text{Layanan} = \text{round}\left( \frac{\text{DPP} \times \text{service\_percent}}{100} \right)$$

5. **Total Tagihan Akhir ($\text{Total}$)**:
   $$\text{Total} = \text{DPP} + \text{Pajak} + \text{Layanan}$$

6. **Uang Kembalian (Khusus Tunai / Cash)**:
   $$\text{Kembalian} = \max(0, \;\text{Uang Diterima} - \text{Total})$$

---

## 7. Ringkasan Keamanan Sistem (Security Layer)

1. **Proteksi Brute-Force Login** ([AuthController.php](file:///c:/Users/Agungleo/capstone/versikopi-api/app/Http/Controllers/AuthController.php)):
   - Menggunakan `RateLimiter` berbasis kombinasi `email + IP address` dengan batas maksimal **5 percobaan gagal per 60 detik** (merespons HTTP `429 Too Many Requests`).
2. **Autentikasi Token & Pemisahan Hak Akses Role (RBAC)** ([RoleMiddleware.php](file:///c:/Users/Agungleo/capstone/versikopi-api/app/Http/Middleware/RoleMiddleware.php) & [api.php](file:///c:/Users/Agungleo/capstone/versikopi-api/routes/api.php)):
   - Menggunakan token `Laravel Sanctum`. Token lama dicabut setiap kali login baru.
   - Endpoint mutasi menu, resep, stok bahan gudang, dan pengaturan toko dikunci khusus role `admin`.
3. **Pencegahan XSS & Null-Byte Injection** ([SecurityHeadersMiddleware.php](file:///c:/Users/Agungleo/capstone/versikopi-api/app/Http/Middleware/SecurityHeadersMiddleware.php) & [api.js](file:///c:/Users/Agungleo/capstone/versikopi-web/src/lib/api.js)):
   - Seluruh input string dibersihkan dari tag HTML (`strip_tags`), karakter kontrol, dan *null-byte* baik di sisi Frontend maupun Middleware Backend.
   - Menambahkan HTTP Security Headers (`X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `X-XSS-Protection: 1; mode=block`, `Referrer-Policy`, `Permissions-Policy`).
4. **Pencegahan SQL Injection & Manipulasi Harga**:
   - Seluruh query menggunakan *parameter binding* dan *whitelist* kolom.
   - Harga menu, diskon promo, pajak, dan ketersediaan stok dihitung ulang sepenuhnya di sisi server di dalam `DB::transaction()` menggunakan `lockForUpdate()` untuk mencegah *race condition* stok minus.

---

## 9. Daftar Menu & Harga Resmi Versi Kopi

Seluruh data menu, harga, foto produk (di-crop khusus bagian produk), serta identitas kedai telah disinkronkan dari aset resmi **Versi Kopi** (`Jl. Inpres XI No.34, RT.005/RW.008, Larangan Utara, Kec. Larangan, Kota Tangerang, Banten` | `13.00 - 23.15 Everyday` | `@Versi_Kopi` | `089612993256`):

### A. Menu Satuan (`21 Menu`)
| No | Nama Menu | Kategori | Harga Regular (M) | Harga Large (L) | Foto Produk (Crop) |
|:--:|:---|:---|---:|---:|:---|
| 1 | **Kopi Susu Versi (Aren Smooth)** | Signature | Rp 15.000 | Rp 18.000 | `/products/kopi-susu-versi.jpg` |
| 2 | **Kopi Susu Temu (Aren Temu)** | Signature | Rp 14.000 | Rp 17.000 | `/products/aren-temu.jpg` |
| 3 | **Kopi Susu Java Latte** | Coffee | Rp 12.000 | Rp 15.000 | `/products/java-latte.jpg` |
| 4 | **Kopi Susu Hazelnut** | Coffee | Rp 16.000 | Rp 19.000 | `/products/kopi-hazelnut.jpg` |
| 5 | **Kopi Susu Butterscotch** | Coffee | Rp 16.000 | Rp 19.000 | `/products/kopi-butterscotch.jpg` |
| 6 | **Kopi Caramel** | Coffee | Rp 16.000 | Rp 19.000 | `/products/kopi-caramel.jpg` |
| 7 | **Versinya Salted Caramel** | Signature | Rp 16.000 | Rp 19.000 | `/products/salted-caramel.jpg` |
| 8 | **Americano** | Coffee | Rp 12.000 | Rp 15.000 | `/products/americano.jpg` |
| 9 | **Versi Ramu** *(New)* | Signature | Rp 15.000 | Rp 18.000 | `/products/versi-ramu.jpg` |
| 10 | **Versi Rindu** | Signature | Rp 15.000 | Rp 18.000 | `/products/versi-rindu.jpg` |
| 11 | **Matcha Coffee** | Signature | Rp 16.000 | Rp 19.000 | `/products/matcha-coffee.jpg` |
| 12 | **Green Tea Latte (Matcha Latte)** | Non-Coffee | Rp 15.000 | Rp 18.000 | `/products/green-tea-latte.jpg` |
| 13 | **Lychee Tea** | Non-Coffee | Rp 15.000 | Rp 18.000 | `/products/ice-black-tea.jpg` |
| 14 | **Lemon Tea** | Non-Coffee | Rp 8.000 | Rp 11.000 | `/products/ice-black-tea.jpg` |
| 15 | **Thai Tea** | Non-Coffee | Rp 15.000 | Rp 18.000 | `/products/aren-temu.jpg` |
| 16 | **Ice Black Tea** | Non-Coffee | Rp 5.000 | Rp 8.000 | `/products/ice-black-tea.jpg` |
| 17 | **Cireng Isi Ayam (5 pcs)** | Snack | Rp 10.000 | Rp 10.000 | `/products/cireng-ayam.jpg` |
| 18 | **Dimsum Keju (4 pcs)** | Snack | Rp 13.000 | Rp 13.000 | `/products/dimsum-keju.jpg` |
| 19 | **French Fries (Kentang Kecil)** | Snack | Rp 8.000 | Rp 10.000 | `/products/french-fries.jpg` |
| 20 | **Kentang Potong Gede (Crinkle Cut)** | Snack | Rp 10.000 | Rp 12.000 | `/products/kentang-crinkle.jpg` |
| 21 | **Burger** *(New)* | Snack | Rp 10.000 | Rp 13.000 | `/products/burger.jpg` |

### B. Menu Paketan / Paket Hemat (`6 Paket`)
| No | Nama Paket | Komponen Paket | Harga Paket | Foto Produk (Crop) |
|:--:|:---|:---|---:|:---|
| 1 | **Paket Hemat Burger + French Fries** | 1× Burger + 1× French Fries | Rp 15.000 | `/products/paket-burger-kentang.jpg` |
| 2 | **Paket Hemat Ice Black Tea** | 1× Burger + 1× French Fries + 1× Ice Black Tea | Rp 20.000 | `/products/paket-hemat-tea.jpg` |
| 3 | **Paket Hemat Lemon Tea** | 1× Burger + 1× French Fries + 1× Lemon Tea | Rp 23.000 | `/products/paket-hemat-tea.jpg` |
| 4 | **Paket Hemat Kopi Java** | 1× Burger + 1× French Fries + 1× Kopi Susu Java Latte | Rp 27.000 | `/products/paket-hemat-kopi-java.jpg` |
| 5 | **Bundling Kentang + Kopi Temu** | 1× French Fries + 1× Kopi Susu Temu (Aren Temu) | Rp 22.000 | `/products/paket-kentang-kopi-temu.jpg` |
| 6 | **Versinya Teman Dirumah Berlima (Java Latte 5)** | 5× Kopi Susu Java Latte | Rp 60.000 | `/products/java-latte-5cup.jpg` |

