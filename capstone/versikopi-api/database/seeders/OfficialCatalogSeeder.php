<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OfficialCatalogSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('products')->exists() || DB::table('ingredients_stock')->exists()) {
            return;
        }

        DB::transaction(fn () => $this->seedFreshCatalog());
    }

    private function seedFreshCatalog(): void
    {
        $officialSettings = [
            'store_name' => 'VERSI KOPI',
            'tagline' => 'Bukan mood booster, ini mood reset',
            'address' => 'Jl. Inpres XI No.34, RT.005/RW.008, Larangan Utara, Kec. Larangan, Kota Tangerang, Banten',
            'phone' => '089612993256',
            'receipt_footer' => 'Terima kasih telah berkunjung ke Versi Kopi (@Versi_Kopi)',
            'custom_note' => 'Buka Setiap Hari 13.00 - 23.15 WIB | @Versi_Kopi',
            'qris_merchant' => 'Versi Kopi',
            'catalog_version' => 'versikopi-official-v2',
        ];
        foreach ($officialSettings as $k => $v) {
            DB::table('settings')->insertOrIgnore([
                'key' => $k, 'value' => $v, 'updated_at' => now(), 'created_at' => now(),
            ]);
        }

        $officialIngredients = [
            ['name' => 'Espresso House Blend', 'unit' => 'ml', 'current_stock' => 3000, 'max_stock' => 3000],
            ['name' => 'Susu Segar Pasteurisasi', 'unit' => 'ml', 'current_stock' => 9000, 'max_stock' => 9000],
            ['name' => 'Sirup Gula Aren Organik', 'unit' => 'ml', 'current_stock' => 2500, 'max_stock' => 2500],
            ['name' => 'Sirup Karamel & Butterscotch', 'unit' => 'ml', 'current_stock' => 2000, 'max_stock' => 2000],
            ['name' => 'Sirup Buah & Varian Rasa', 'unit' => 'ml', 'current_stock' => 2000, 'max_stock' => 2000],
            ['name' => 'Simple Syrup', 'unit' => 'ml', 'current_stock' => 2000, 'max_stock' => 2000],
            ['name' => 'Bubuk Pure Matcha Jepang', 'unit' => 'gr', 'current_stock' => 500, 'max_stock' => 500],
            ['name' => 'Seduhan Teh Hitam', 'unit' => 'ml', 'current_stock' => 6000, 'max_stock' => 6000],
            ['name' => 'Air Mineral', 'unit' => 'ml', 'current_stock' => 9000, 'max_stock' => 9000],
            ['name' => 'Es Batu Kristal', 'unit' => 'gr', 'current_stock' => 10000, 'max_stock' => 10000],
            ['name' => 'Kentang & Bumbu Snack', 'unit' => 'gr', 'current_stock' => 5000, 'max_stock' => 5000],
            ['name' => 'Roti Bun & Patty Burger', 'unit' => 'gr', 'current_stock' => 4200, 'max_stock' => 4200],
            ['name' => 'Bahan Cireng & Dimsum', 'unit' => 'gr', 'current_stock' => 4000, 'max_stock' => 4000],
        ];

        foreach ($officialIngredients as $ing) {
            DB::table('ingredients_stock')->insert(array_merge($ing, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        // daftar menu satuan resmi dari daftar harga dan foto produk
        $satuanMenus = [
            // Signature & Coffee
            [
                'name' => 'Kopi Susu Versi (Aren Smooth)',
                'category' => 'Signature',
                'description' => 'Signature blend, creamy & balanced dengan gula aren lembut.',
                'price_m' => 15000,
                'price_l' => 18000,
                'gradient' => '#D8B493,#A67C58',
                'image_url' => '/products/kopi-susu-versi.jpg',
                'prep_time' => '2 Menit',
                'ingredients' => [
                    ['name' => 'Espresso House Blend', 'amount_m' => '30', 'amount_l' => '45', 'unit' => 'ml'],
                    ['name' => 'Susu Segar Pasteurisasi', 'amount_m' => '120', 'amount_l' => '160', 'unit' => 'ml'],
                    ['name' => 'Sirup Gula Aren Organik', 'amount_m' => '25', 'amount_l' => '35', 'unit' => 'ml'],
                    ['name' => 'Es Batu Kristal', 'amount_m' => '90', 'amount_l' => '120', 'unit' => 'gr'],
                ],
                'steps' => "1. Tuang sirup gula aren di dasar cup.\n2. Masukkan es batu dan susu segar dingin.\n3. Tuang shot espresso di bagian atas.",
                'note' => 'Aduk sebelum diminum untuk rasa manis legit yang merata.',
            ],
            [
                'name' => 'Kopi Susu Temu (Aren Temu)',
                'category' => 'Signature',
                'description' => 'Earthy taste, unique & fresh perpaduan espresso dan rempah gula aren.',
                'price_m' => 14000,
                'price_l' => 17000,
                'gradient' => '#C4A484,#8A684C',
                'image_url' => '/products/aren-temu.jpg',
                'prep_time' => '2 Menit',
                'ingredients' => [
                    ['name' => 'Espresso House Blend', 'amount_m' => '30', 'amount_l' => '45', 'unit' => 'ml'],
                    ['name' => 'Susu Segar Pasteurisasi', 'amount_m' => '120', 'amount_l' => '160', 'unit' => 'ml'],
                    ['name' => 'Sirup Gula Aren Organik', 'amount_m' => '25', 'amount_l' => '35', 'unit' => 'ml'],
                    ['name' => 'Es Batu Kristal', 'amount_m' => '90', 'amount_l' => '120', 'unit' => 'gr'],
                ],
                'steps' => "1. Siapkan sirup gula aren khas Temu di dasar cup.\n2. Tambahkan es batu dan susu segar.\n3. Tuang espresso segar.",
                'note' => 'Karakter rasa unik dan segar khas Versi Kopi.',
            ],
            [
                'name' => 'Kopi Susu Java Latte',
                'category' => 'Coffee',
                'description' => 'Stronger taste, smoother — rasa kopi yang pas bikin hari lebih berasa.',
                'price_m' => 12000,
                'price_l' => 15000,
                'gradient' => '#D2B49C,#98765C',
                'image_url' => '/products/java-latte.jpg',
                'prep_time' => '2 Menit',
                'ingredients' => [
                    ['name' => 'Espresso House Blend', 'amount_m' => '35', 'amount_l' => '50', 'unit' => 'ml'],
                    ['name' => 'Susu Segar Pasteurisasi', 'amount_m' => '120', 'amount_l' => '150', 'unit' => 'ml'],
                    ['name' => 'Simple Syrup', 'amount_m' => '15', 'amount_l' => '20', 'unit' => 'ml'],
                    ['name' => 'Es Batu Kristal', 'amount_m' => '90', 'amount_l' => '120', 'unit' => 'gr'],
                ],
                'steps' => "1. Campurkan espresso dengan simple syrup.\n2. Masukkan es batu dan susu segar hingga penuh.",
                'note' => 'Body kopi lebih kuat dengan tekstur akhir yang halus.',
            ],
            [
                'name' => 'Kopi Susu Hazelnut',
                'category' => 'Coffee',
                'description' => 'Sweet, nutty, classic — kopi susu lembut dengan aroma kacang hazelnut.',
                'price_m' => 16000,
                'price_l' => 19000,
                'gradient' => '#DFC39D,#A88558',
                'image_url' => '/products/kopi-hazelnut.jpg',
                'prep_time' => '2 Menit',
                'ingredients' => [
                    ['name' => 'Espresso House Blend', 'amount_m' => '30', 'amount_l' => '45', 'unit' => 'ml'],
                    ['name' => 'Susu Segar Pasteurisasi', 'amount_m' => '120', 'amount_l' => '160', 'unit' => 'ml'],
                    ['name' => 'Sirup Buah & Varian Rasa', 'amount_m' => '25', 'amount_l' => '35', 'unit' => 'ml'],
                    ['name' => 'Es Batu Kristal', 'amount_m' => '90', 'amount_l' => '120', 'unit' => 'gr'],
                ],
                'steps' => "1. Tuang sirup hazelnut ke dalam cup.\n2. Tambahkan es batu dan susu segar.\n3. Tuang shot espresso di atasnya.",
                'note' => 'Aroma nutty hazelnut keluar kuat saat disajikan dingin.',
            ],
            [
                'name' => 'Kopi Susu Butterscotch',
                'category' => 'Coffee',
                'description' => 'Rich, buttery, smooth — kopi susu dengan sensasi butterscotch legit.',
                'price_m' => 16000,
                'price_l' => 19000,
                'gradient' => '#E4BE8E,#B8864B',
                'image_url' => '/products/kopi-butterscotch.jpg',
                'prep_time' => '2.5 Menit',
                'ingredients' => [
                    ['name' => 'Espresso House Blend', 'amount_m' => '30', 'amount_l' => '45', 'unit' => 'ml'],
                    ['name' => 'Susu Segar Pasteurisasi', 'amount_m' => '120', 'amount_l' => '160', 'unit' => 'ml'],
                    ['name' => 'Sirup Karamel & Butterscotch', 'amount_m' => '25', 'amount_l' => '35', 'unit' => 'ml'],
                    ['name' => 'Es Batu Kristal', 'amount_m' => '90', 'amount_l' => '120', 'unit' => 'gr'],
                ],
                'steps' => "1. Beri drizzle saus butterscotch di dinding cup.\n2. Tuang es batu dan susu segar.\n3. Tambahkan espresso di atasnya.",
                'note' => 'Rasa buttery dan manis lembut.',
            ],
            [
                'name' => 'Kopi Caramel',
                'category' => 'Coffee',
                'description' => 'Sweet & bold — kombinasi espresso pekat, susu segar, dan saus karamel.',
                'price_m' => 16000,
                'price_l' => 19000,
                'gradient' => '#D8B493,#A67C58',
                'image_url' => '/products/kopi-caramel.jpg',
                'prep_time' => '2.5 Menit',
                'ingredients' => [
                    ['name' => 'Espresso House Blend', 'amount_m' => '30', 'amount_l' => '45', 'unit' => 'ml'],
                    ['name' => 'Susu Segar Pasteurisasi', 'amount_m' => '120', 'amount_l' => '160', 'unit' => 'ml'],
                    ['name' => 'Sirup Karamel & Butterscotch', 'amount_m' => '25', 'amount_l' => '35', 'unit' => 'ml'],
                    ['name' => 'Es Batu Kristal', 'amount_m' => '90', 'amount_l' => '120', 'unit' => 'gr'],
                ],
                'steps' => "1. Tuang sirup karamel pada dasar dan dinding cup.\n2. Masukkan es batu dan susu segar.\n3. Tuang shot espresso.",
                'note' => 'Manis karamel berpadu dengan karakter espresso yang bold.',
            ],
            [
                'name' => 'Versinya Salted Caramel',
                'category' => 'Signature',
                'description' => 'Perpaduan espresso, susu creamy, dan saus salted caramel gurih manis.',
                'price_m' => 16000,
                'price_l' => 19000,
                'gradient' => '#D2B49C,#98765C',
                'image_url' => '/products/salted-caramel.jpg',
                'prep_time' => '2.5 Menit',
                'ingredients' => [
                    ['name' => 'Espresso House Blend', 'amount_m' => '30', 'amount_l' => '45', 'unit' => 'ml'],
                    ['name' => 'Susu Segar Pasteurisasi', 'amount_m' => '120', 'amount_l' => '160', 'unit' => 'ml'],
                    ['name' => 'Sirup Karamel & Butterscotch', 'amount_m' => '25', 'amount_l' => '35', 'unit' => 'ml'],
                    ['name' => 'Es Batu Kristal', 'amount_m' => '90', 'amount_l' => '120', 'unit' => 'gr'],
                ],
                'steps' => "1. Campurkan salted caramel di dasar cup.\n2. Masukkan es batu, susu segar, dan espresso di bagian atas.",
                'note' => 'Sentuhan gurih garam laut menyeimbangkan manis karamel.',
            ],
            [
                'name' => 'Americano',
                'category' => 'Coffee',
                'description' => 'Simple but never boring — ekstrak espresso segar dengan air mineral dingin.',
                'price_m' => 12000,
                'price_l' => 15000,
                'gradient' => '#B8967A,#7B5B43',
                'image_url' => '/products/americano.jpg',
                'prep_time' => '1.5 Menit',
                'ingredients' => [
                    ['name' => 'Espresso House Blend', 'amount_m' => '30', 'amount_l' => '60', 'unit' => 'ml'],
                    ['name' => 'Air Mineral', 'amount_m' => '150', 'amount_l' => '180', 'unit' => 'ml'],
                    ['name' => 'Es Batu Kristal', 'amount_m' => '80', 'amount_l' => '110', 'unit' => 'gr'],
                ],
                'steps' => "1. Tuang air mineral dingin dan es batu ke dalam cup.\n2. Ekstrak espresso segar di atasnya.",
                'note' => 'Segar, ringan, dan menonjolkan karakter asli biji kopi.',
            ],
            [
                'name' => 'Versi Ramu',
                'category' => 'Signature',
                'description' => 'Americano fruity segar dengan perpaduan sirup anggur hijau, apel & madu.',
                'price_m' => 15000,
                'price_l' => 18000,
                'gradient' => '#B7C6A5,#7A9068',
                'image_url' => '/products/versi-ramu.jpg',
                'prep_time' => '2 Menit',
                'ingredients' => [
                    ['name' => 'Espresso House Blend', 'amount_m' => '30', 'amount_l' => '45', 'unit' => 'ml'],
                    ['name' => 'Sirup Buah & Varian Rasa', 'amount_m' => '25', 'amount_l' => '35', 'unit' => 'ml'],
                    ['name' => 'Air Mineral', 'amount_m' => '120', 'amount_l' => '150', 'unit' => 'ml'],
                    ['name' => 'Es Batu Kristal', 'amount_m' => '90', 'amount_l' => '120', 'unit' => 'gr'],
                ],
                'steps' => "1. Tuang sirup anggur hijau & apel di dasar cup.\n2. Masukkan es batu dan air mineral.\n3. Tuang espresso secara perlahan agar terbentuk gradasi layer.",
                'note' => 'Menu signature baru dengan sensasi fruity menyegarkan.',
            ],
            [
                'name' => 'Versi Rindu',
                'category' => 'Signature',
                'description' => 'Creamy, gurihnya ada — racikan kopi susu spesial yang bikin kangen.',
                'price_m' => 15000,
                'price_l' => 18000,
                'gradient' => '#DFC39D,#A88558',
                'image_url' => '/products/versi-rindu.jpg',
                'prep_time' => '2 Menit',
                'ingredients' => [
                    ['name' => 'Espresso House Blend', 'amount_m' => '30', 'amount_l' => '45', 'unit' => 'ml'],
                    ['name' => 'Susu Segar Pasteurisasi', 'amount_m' => '130', 'amount_l' => '170', 'unit' => 'ml'],
                    ['name' => 'Sirup Karamel & Butterscotch', 'amount_m' => '20', 'amount_l' => '30', 'unit' => 'ml'],
                    ['name' => 'Es Batu Kristal', 'amount_m' => '90', 'amount_l' => '120', 'unit' => 'gr'],
                ],
                'steps' => "1. Racik susu segar dengan sirup gurih creamy.\n2. Tambahkan es batu dan tuang shot espresso di atasnya.",
                'note' => 'Tekstur lebih creamy dan gurih dibanding kopi susu biasa.',
            ],
            [
                'name' => 'Matcha Coffee',
                'category' => 'Signature',
                'description' => 'Layer Matcha Jepang lembut di atas berpadu dengan Americano di bawah.',
                'price_m' => 16000,
                'price_l' => 19000,
                'gradient' => '#B7C6A5,#7A9068',
                'image_url' => '/products/matcha-coffee.jpg',
                'prep_time' => '2.5 Menit',
                'ingredients' => [
                    ['name' => 'Espresso House Blend', 'amount_m' => '30', 'amount_l' => '45', 'unit' => 'ml'],
                    ['name' => 'Bubuk Pure Matcha Jepang', 'amount_m' => '5', 'amount_l' => '7', 'unit' => 'gr'],
                    ['name' => 'Susu Segar Pasteurisasi', 'amount_m' => '100', 'amount_l' => '130', 'unit' => 'ml'],
                    ['name' => 'Es Batu Kristal', 'amount_m' => '90', 'amount_l' => '120', 'unit' => 'gr'],
                ],
                'steps' => "1. Siapkan base espresso dan es batu di bagian bawah cup.\n2. Whisk matcha bersama susu segar lalu tuang perlahan di bagian atas.",
                'note' => 'Tampilan dua layer hijau matcha di atas dan cokelat kopi di bawah.',
            ],

            // Non-Coffee
            [
                'name' => 'Green Tea Latte (Matcha Latte)',
                'category' => 'Non-Coffee',
                'description' => 'Smooth, earthy, mellow — matcha asli dengan rasa lembut & creamy.',
                'price_m' => 15000,
                'price_l' => 18000,
                'gradient' => '#B7C6A5,#7A9068',
                'image_url' => '/products/green-tea-latte.jpg',
                'prep_time' => '2.5 Menit',
                'ingredients' => [
                    ['name' => 'Bubuk Pure Matcha Jepang', 'amount_m' => '5', 'amount_l' => '7', 'unit' => 'gr'],
                    ['name' => 'Susu Segar Pasteurisasi', 'amount_m' => '130', 'amount_l' => '170', 'unit' => 'ml'],
                    ['name' => 'Simple Syrup', 'amount_m' => '15', 'amount_l' => '20', 'unit' => 'ml'],
                    ['name' => 'Es Batu Kristal', 'amount_m' => '90', 'amount_l' => '120', 'unit' => 'gr'],
                ],
                'steps' => "1. Larutkan bubuk matcha dengan air hangat menggunakan chasen.\n2. Tuang es batu, simple syrup, dan susu segar ke dalam cup.\n3. Tuang matcha di bagian atas.",
                'note' => 'Warna hijau alami dengan tekstur susu yang lembut.',
            ],
            [
                'name' => 'Lychee Tea',
                'category' => 'Non-Coffee',
                'description' => 'Segar, manis, ringan — seduhan teh pilihan dengan aroma buah leci.',
                'price_m' => 15000,
                'price_l' => 18000,
                'gradient' => '#E2A3A3,#B46A74',
                'image_url' => '/products/ice-black-tea.jpg',
                'prep_time' => '1.5 Menit',
                'ingredients' => [
                    ['name' => 'Seduhan Teh Hitam', 'amount_m' => '150', 'amount_l' => '200', 'unit' => 'ml'],
                    ['name' => 'Sirup Buah & Varian Rasa', 'amount_m' => '25', 'amount_l' => '35', 'unit' => 'ml'],
                    ['name' => 'Es Batu Kristal', 'amount_m' => '90', 'amount_l' => '120', 'unit' => 'gr'],
                ],
                'steps' => "1. Campurkan teh hitam dingin dengan sirup leci.\n2. Masukkan es batu kristal dan aduk rata.",
                'note' => 'Manis segar dan ringan.',
            ],
            [
                'name' => 'Lemon Tea',
                'category' => 'Non-Coffee',
                'description' => 'Fresh, asamnya pas — teh dingin segar dengan perasan lemon.',
                'price_m' => 8000,
                'price_l' => 11000,
                'gradient' => '#E5C585,#B38E46',
                'image_url' => '/products/ice-black-tea.jpg',
                'prep_time' => '1.5 Menit',
                'ingredients' => [
                    ['name' => 'Seduhan Teh Hitam', 'amount_m' => '150', 'amount_l' => '200', 'unit' => 'ml'],
                    ['name' => 'Sirup Buah & Varian Rasa', 'amount_m' => '20', 'amount_l' => '30', 'unit' => 'ml'],
                    ['name' => 'Es Batu Kristal', 'amount_m' => '90', 'amount_l' => '120', 'unit' => 'gr'],
                ],
                'steps' => "1. Tuang teh hitam dan konsentrat lemon segar.\n2. Tambahkan es batu kristal hingga penuh.",
                'note' => 'Sangat menyegarkan dan cocok dipadukan dengan paket hemat.',
            ],
            [
                'name' => 'Thai Tea',
                'category' => 'Non-Coffee',
                'description' => 'Creamy, wangi, nagih — racikan teh Thailand autentik dengan susu.',
                'price_m' => 15000,
                'price_l' => 18000,
                'gradient' => '#E4BE8E,#B8864B',
                'image_url' => '/products/aren-temu.jpg',
                'prep_time' => '2 Menit',
                'ingredients' => [
                    ['name' => 'Seduhan Teh Hitam', 'amount_m' => '120', 'amount_l' => '160', 'unit' => 'ml'],
                    ['name' => 'Susu Segar Pasteurisasi', 'amount_m' => '60', 'amount_l' => '80', 'unit' => 'ml'],
                    ['name' => 'Simple Syrup', 'amount_m' => '15', 'amount_l' => '20', 'unit' => 'ml'],
                    ['name' => 'Es Batu Kristal', 'amount_m' => '90', 'amount_l' => '120', 'unit' => 'gr'],
                ],
                'steps' => "1. Campurkan seduhan teh pekat dengan susu dan simple syrup.\n2. Tuang ke dalam cup berisi es batu.",
                'note' => 'Wangi rempah teh yang khas dan creamy.',
            ],
            [
                'name' => 'Ice Black Tea',
                'category' => 'Non-Coffee',
                'description' => 'Classic, timeless — teh hitam pilihan dengan rasa yang menyegarkan.',
                'price_m' => 5000,
                'price_l' => 8000,
                'gradient' => '#C89D94,#8D625A',
                'image_url' => '/products/ice-black-tea.jpg',
                'prep_time' => '1 Menit',
                'ingredients' => [
                    ['name' => 'Seduhan Teh Hitam', 'amount_m' => '180', 'amount_l' => '220', 'unit' => 'ml'],
                    ['name' => 'Simple Syrup', 'amount_m' => '15', 'amount_l' => '20', 'unit' => 'ml'],
                    ['name' => 'Es Batu Kristal', 'amount_m' => '90', 'amount_l' => '120', 'unit' => 'gr'],
                ],
                'steps' => "1. Siapkan es batu kristal di dalam cup.\n2. Tuang seduhan teh hitam segar dan simple syrup.",
                'note' => 'Minuman teh klasik paling hemat dan menyegarkan.',
            ],

            // Snack
            [
                'name' => 'Cireng Isi Ayam (5 pcs)',
                'category' => 'Snack',
                'description' => '5 pcs cireng renyah isi ayam suwir gurih lengkap dengan saus sambal.',
                'price_m' => 10000,
                'price_l' => 10000,
                'gradient' => '#E4BE8E,#B8864B',
                'image_url' => '/products/cireng-ayam.jpg',
                'prep_time' => '4 Menit',
                'ingredients' => [
                    ['name' => 'Bahan Cireng & Dimsum', 'amount_m' => '125', 'amount_l' => '125', 'unit' => 'gr'],
                ],
                'steps' => "1. Goreng 5 pcs cireng isi ayam dengan api sedang hingga matang merata.\n2. Tiriskan dan sajikan hangat bersama saus sambal.",
                'note' => 'Sajikan selagi hangat dan renyah.',
            ],
            [
                'name' => 'Dimsum Keju (4 pcs)',
                'category' => 'Snack',
                'description' => '4 pcs kudapan goreng renyah dengan isian keju lumer melimpah & saus.',
                'price_m' => 13000,
                'price_l' => 13000,
                'gradient' => '#E5C585,#B38E46',
                'image_url' => '/products/dimsum-keju.jpg',
                'prep_time' => '4 Menit',
                'ingredients' => [
                    ['name' => 'Bahan Cireng & Dimsum', 'amount_m' => '140', 'amount_l' => '140', 'unit' => 'gr'],
                ],
                'steps' => "1. Goreng 4 pcs hingga kulit keemasan dan keju di dalamnya meleleh.\n2. Sajikan bersama saus cocolan.",
                'note' => 'Keju lumer saat digigit hangat.',
            ],
            [
                'name' => 'French Fries (Kentang Kecil)',
                'category' => 'Snack',
                'description' => 'Kentang goreng potong kecil (shoestring) renyah bertabur bumbu gurih.',
                'price_m' => 8000,
                'price_l' => 10000,
                'gradient' => '#E5C585,#B38E46',
                'image_url' => '/products/french-fries.jpg',
                'prep_time' => '3 Menit',
                'ingredients' => [
                    ['name' => 'Kentang & Bumbu Snack', 'amount_m' => '100', 'amount_l' => '130', 'unit' => 'gr'],
                ],
                'steps' => "1. Goreng kentang shoestring hingga garing keemasan.\n2. Taburi bumbu parsley gurih dan sajikan di paper cup.",
                'note' => 'Potongan kecil (shoestring) ekstra renyah.',
            ],
            [
                'name' => 'Kentang Potong Gede (Crinkle Cut)',
                'category' => 'Snack',
                'description' => 'Kentang goreng potongan besar bergelombang (crinkle cut) garing di luar.',
                'price_m' => 10000,
                'price_l' => 12000,
                'gradient' => '#E4BE8E,#B8864B',
                'image_url' => '/products/kentang-crinkle.jpg',
                'prep_time' => '3.5 Menit',
                'ingredients' => [
                    ['name' => 'Kentang & Bumbu Snack', 'amount_m' => '130', 'amount_l' => '160', 'unit' => 'gr'],
                ],
                'steps' => "1. Goreng kentang crinkle cut hingga keemasan.\n2. Taburi bumbu gurih dan sajikan hangat.",
                'note' => 'Potongan besar lembut di dalam dan renyah di luar.',
            ],
            [
                'name' => 'Burger',
                'category' => 'Snack',
                'description' => 'Roti bun lembut dengan patty krispi, selada segar, timun, dan saus spesial.',
                'price_m' => 10000,
                'price_l' => 13000,
                'gradient' => '#D8B493,#A67C58',
                'image_url' => '/products/burger.jpg',
                'prep_time' => '4 Menit',
                'ingredients' => [
                    ['name' => 'Roti Bun & Patty Burger', 'amount_m' => '140', 'amount_l' => '180', 'unit' => 'gr'],
                ],
                'steps' => "1. Panggang roti bun dengan sedikit butter.\n2. Goreng patty hingga krispi.\n3. Susun roti, selada, timun, patty, saus keju, dan saus sambal.",
                'note' => 'Menu snack favorit pengganjal lapar.',
            ],
        ];

        $productIdsByName = [];
        foreach ($satuanMenus as $m) {
            $prodId = DB::table('products')->insertGetId([
                'name' => $m['name'],
                'category' => $m['category'],
                'item_type' => 'satuan',
                'description' => $m['description'],
                'bundle_items' => null,
                'price_m' => $m['price_m'],
                'price_l' => $m['price_l'],
                'stock' => 60,
                'max_stock' => 60,
                'emoji' => $m['category'] === 'Snack' ? 'SNACK' : 'KOPI',
                'gradient' => $m['gradient'],
                'image_url' => $m['image_url'],
                'active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $productIdsByName[$m['name']] = $prodId;

            DB::table('recipes')->insert([
                'product_id' => $prodId,
                'prep_time' => $m['prep_time'],
                'ingredients' => json_encode($m['ingredients'], JSON_UNESCAPED_UNICODE),
                'steps' => $m['steps'],
                'barista_note' => $m['note'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // daftar menu paketan resmi dari daftar harga
        $burgerId = $productIdsByName['Burger'] ?? null;
        $friesId = $productIdsByName['French Fries (Kentang Kecil)'] ?? null;
        $blackTeaId = $productIdsByName['Ice Black Tea'] ?? null;
        $lemonTeaId = $productIdsByName['Lemon Tea'] ?? null;
        $javaLatteId = $productIdsByName['Kopi Susu Java Latte'] ?? null;
        $arenTemuId = $productIdsByName['Kopi Susu Temu (Aren Temu)'] ?? null;

        $paketanMenus = [
            [
                'name' => 'Paket Hemat Burger + French Fries',
                'description' => 'Burger + Fried French lengkap dengan saus sambal & keju.',
                'price_m' => 15000,
                'price_l' => 18000,
                'gradient' => '#D8B493,#A67C58',
                'image_url' => '/products/paket-burger-kentang.jpg',
                'items' => [
                    ['product_id' => $burgerId, 'name' => 'Burger', 'qty' => 1],
                    ['product_id' => $friesId, 'name' => 'French Fries (Kentang Kecil)', 'qty' => 1],
                ],
            ],
            [
                'name' => 'Paket Hemat Ice Black Tea',
                'description' => '1 Burger + 1 French Fries + 1 Cup Ice Black Tea segar.',
                'price_m' => 20000,
                'price_l' => 23000,
                'gradient' => '#C89D94,#8D625A',
                'image_url' => '/products/paket-hemat-tea.jpg',
                'items' => [
                    ['product_id' => $burgerId, 'name' => 'Burger', 'qty' => 1],
                    ['product_id' => $friesId, 'name' => 'French Fries (Kentang Kecil)', 'qty' => 1],
                    ['product_id' => $blackTeaId, 'name' => 'Ice Black Tea', 'qty' => 1],
                ],
            ],
            [
                'name' => 'Paket Hemat Lemon Tea',
                'description' => '1 Burger + 1 French Fries + 1 Cup Lemon Tea segar.',
                'price_m' => 23000,
                'price_l' => 26000,
                'gradient' => '#E5C585,#B38E46',
                'image_url' => '/products/paket-hemat-tea.jpg',
                'items' => [
                    ['product_id' => $burgerId, 'name' => 'Burger', 'qty' => 1],
                    ['product_id' => $friesId, 'name' => 'French Fries (Kentang Kecil)', 'qty' => 1],
                    ['product_id' => $lemonTeaId, 'name' => 'Lemon Tea', 'qty' => 1],
                ],
            ],
            [
                'name' => 'Paket Hemat Kopi Java',
                'description' => '1 Burger + 1 French Fries + 1 Cup Kopi Susu Java Latte.',
                'price_m' => 27000,
                'price_l' => 30000,
                'gradient' => '#C4A484,#8A684C',
                'image_url' => '/products/paket-hemat-kopi-java.jpg',
                'items' => [
                    ['product_id' => $burgerId, 'name' => 'Burger', 'qty' => 1],
                    ['product_id' => $friesId, 'name' => 'French Fries (Kentang Kecil)', 'qty' => 1],
                    ['product_id' => $javaLatteId, 'name' => 'Kopi Susu Java Latte', 'qty' => 1],
                ],
            ],
            [
                'name' => 'Bundling Kentang + Kopi Temu',
                'description' => '1 porsi French Fries + 1 Cup Kopi Susu Temu (Aren Temu).',
                'price_m' => 22000,
                'price_l' => 25000,
                'gradient' => '#D2B49C,#98765C',
                'image_url' => '/products/paket-kentang-kopi-temu.jpg',
                'items' => [
                    ['product_id' => $friesId, 'name' => 'French Fries (Kentang Kecil)', 'qty' => 1],
                    ['product_id' => $arenTemuId, 'name' => 'Kopi Susu Temu (Aren Temu)', 'qty' => 1],
                ],
            ],
            [
                'name' => 'Versinya Teman Dirumah Berlima (Java Latte 5)',
                'description' => 'Bundling hemat 5 Cup Kopi Susu Java Latte untuk dinikmati bersama.',
                'price_m' => 60000,
                'price_l' => 72000,
                'gradient' => '#D8B493,#A67C58',
                'image_url' => '/products/java-latte-5cup.jpg',
                'items' => [
                    ['product_id' => $javaLatteId, 'name' => 'Kopi Susu Java Latte', 'qty' => 5],
                ],
            ],
        ];

        foreach ($paketanMenus as $b) {
            DB::table('products')->insert([
                'name' => $b['name'],
                'category' => 'Bundling',
                'item_type' => 'paketan',
                'description' => $b['description'],
                'bundle_items' => json_encode($b['items'], JSON_UNESCAPED_UNICODE),
                'price_m' => $b['price_m'],
                'price_l' => $b['price_l'],
                'stock' => 30,
                'max_stock' => 30,
                'emoji' => 'PAKET',
                'gradient' => $b['gradient'],
                'image_url' => $b['image_url'],
                'active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
