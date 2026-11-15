<?php

namespace App\Http\Controllers;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PosController extends Controller
{
    private const ALLOWED_CATEGORIES = ['Signature', 'Coffee', 'Non-Coffee', 'Pastry', 'Snack', 'Bundling'];
    private const ALLOWED_SUGAR = ['100%', '75%', '50%', '25%', '0%'];
    private const ALLOWED_UNITS = ['ml', 'gr', 'shot', 'pump', 'pcs', 'sdm', 'sdt'];
    private const ALLOWED_SETTINGS = [
        'store_name',
        'tagline',
        'address',
        'phone',
        'tax_percent',
        'service_percent',
        'receipt_footer',
        'custom_note',
        'qris_merchant',
        'auto_print',
        'notification_sound',
    ];

    private function cleanText(?string $value, int $maxLength = 255): string
    {
        if ($value === null) {
            return '';
        }
        $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', strip_tags($value));
        return mb_substr(trim((string) $cleaned), 0, $maxLength);
    }

    private function ensureExtendedSchema(): void
    {
        // cek tabel pengaturan
        if (! Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        // cek tabel produk
        if (! Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('category');
                $table->string('item_type')->default('satuan');
                $table->text('description')->nullable();
                $table->text('bundle_items')->nullable();
                $table->integer('price_m')->default(0);
                $table->integer('price_l')->default(0);
                $table->integer('stock')->default(60);
                $table->integer('max_stock')->default(60);
                $table->string('emoji')->nullable();
                $table->string('gradient')->nullable();
                $table->string('image_url')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        } else {
            Schema::table('products', function (Blueprint $table) {
                if (! Schema::hasColumn('products', 'max_stock')) {
                    $table->integer('max_stock')->default(60)->after('stock');
                }
                if (! Schema::hasColumn('products', 'item_type')) {
                    $table->string('item_type')->default('satuan')->after('category');
                }
                if (! Schema::hasColumn('products', 'bundle_items')) {
                    $table->text('bundle_items')->nullable()->after('description');
                }
                if (! Schema::hasColumn('products', 'image_url')) {
                    $table->string('image_url')->nullable()->after('gradient');
                }
            });

            // sinkronisasi kapasitas awal
            DB::statement('UPDATE products SET max_stock = CASE WHEN stock > 60 THEN stock ELSE 60 END WHERE max_stock IS NULL OR max_stock <= 0');
        }

        // cek tabel promo
        if (! Schema::hasTable('promos')) {
            Schema::create('promos', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('type')->default('percent');
                $table->integer('value')->default(0);
                $table->integer('min_subtotal')->default(0);
                $table->integer('max_discount')->default(0);
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        // cek tabel transaksi
        if (! Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('cashier_name')->nullable();
                $table->string('customer_name')->nullable();
                $table->string('order_type')->default('dine_in');
                $table->string('table_number')->nullable();
                $table->longText('items');
                $table->integer('subtotal')->default(0);
                $table->string('promo_code')->nullable();
                $table->integer('discount')->default(0);
                $table->integer('tax')->default(0);
                $table->integer('service')->default(0);
                $table->integer('total')->default(0);
                $table->string('payment_method')->default('cash');
                $table->integer('cash_received')->default(0);
                $table->integer('change_amount')->default(0);
                $table->timestamps();
            });
        }

        // cek tabel riwayat mutasi stok
        if (! Schema::hasTable('inventory_logs')) {
            Schema::create('inventory_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id')->nullable();
                $table->string('product_name');
                $table->string('type')->default('in');
                $table->string('qty');
                $table->string('stock_after')->nullable();
                $table->string('note')->nullable();
                $table->string('reference')->nullable();
                $table->string('actor_name')->nullable();
                $table->timestamps();
            });
        }

        // cek tabel stok bahan
        if (! Schema::hasTable('ingredients_stock')) {
            Schema::create('ingredients_stock', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('unit')->default('ml');
                $table->float('current_stock')->default(0);
                $table->float('max_stock')->default(1000);
                $table->timestamps();
            });
        }

        // cek tabel resep
        if (! Schema::hasTable('recipes')) {
            Schema::create('recipes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id')->unique();
                $table->string('prep_time')->default('2 Menit');
                $table->longText('ingredients');
                $table->text('steps')->nullable();
                $table->string('barista_note')->nullable();
                $table->timestamps();
            });
        }

        // sinkronisasi daftar menu dan harga resmi versi kopi
        $catalogVersion = DB::table('settings')->where('key', 'catalog_version')->value('value');
        if ($catalogVersion !== 'versikopi-official-v2') {
            $this->syncOfficialVersiKopiCatalog();
        }
    }

    // sinkronisasi katalog menu resmi versi kopi beserta foto dan resep
    private function syncOfficialVersiKopiCatalog(): void
    {
        $officialSettings = [
            'store_name'      => 'VERSI KOPI',
            'tagline'         => 'Bukan mood booster, ini mood reset',
            'address'         => 'Jl. Inpres XI No.34, RT.005/RW.008, Larangan Utara, Kec. Larangan, Kota Tangerang, Banten',
            'phone'           => '089612993256',
            'receipt_footer'  => 'Terima kasih telah berkunjung ke Versi Kopi (@Versi_Kopi)',
            'custom_note'     => 'Buka Setiap Hari 13.00 - 23.15 WIB | @Versi_Kopi',
            'qris_merchant'   => 'Versi Kopi',
            'catalog_version' => 'versikopi-official-v2',
        ];
        foreach ($officialSettings as $k => $v) {
            DB::table('settings')->updateOrInsert(
                ['key' => $k],
                ['value' => $v, 'updated_at' => now(), 'created_at' => now()]
            );
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

        DB::table('ingredients_stock')->delete();
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
                'name'        => 'Kopi Susu Versi (Aren Smooth)',
                'category'    => 'Signature',
                'description' => 'Signature blend, creamy & balanced dengan gula aren lembut.',
                'price_m'     => 15000,
                'price_l'     => 18000,
                'gradient'    => '#D8B493,#A67C58',
                'image_url'   => '/products/kopi-susu-versi.jpg',
                'prep_time'   => '2 Menit',
                'ingredients' => [
                    ['name' => 'Espresso House Blend', 'amount_m' => '30', 'amount_l' => '45', 'unit' => 'ml'],
                    ['name' => 'Susu Segar Pasteurisasi', 'amount_m' => '120', 'amount_l' => '160', 'unit' => 'ml'],
                    ['name' => 'Sirup Gula Aren Organik', 'amount_m' => '25', 'amount_l' => '35', 'unit' => 'ml'],
                    ['name' => 'Es Batu Kristal', 'amount_m' => '90', 'amount_l' => '120', 'unit' => 'gr'],
                ],
                'steps'       => "1. Tuang sirup gula aren di dasar cup.\n2. Masukkan es batu dan susu segar dingin.\n3. Tuang shot espresso di bagian atas.",
                'note'        => 'Aduk sebelum diminum untuk rasa manis legit yang merata.',
            ],
            [
                'name'        => 'Kopi Susu Temu (Aren Temu)',
                'category'    => 'Signature',
                'description' => 'Earthy taste, unique & fresh perpaduan espresso dan rempah gula aren.',
                'price_m'     => 14000,
                'price_l'     => 17000,
                'gradient'    => '#C4A484,#8A684C',
                'image_url'   => '/products/aren-temu.jpg',
                'prep_time'   => '2 Menit',
                'ingredients' => [
                    ['name' => 'Espresso House Blend', 'amount_m' => '30', 'amount_l' => '45', 'unit' => 'ml'],
                    ['name' => 'Susu Segar Pasteurisasi', 'amount_m' => '120', 'amount_l' => '160', 'unit' => 'ml'],
                    ['name' => 'Sirup Gula Aren Organik', 'amount_m' => '25', 'amount_l' => '35', 'unit' => 'ml'],
                    ['name' => 'Es Batu Kristal', 'amount_m' => '90', 'amount_l' => '120', 'unit' => 'gr'],
                ],
                'steps'       => "1. Siapkan sirup gula aren khas Temu di dasar cup.\n2. Tambahkan es batu dan susu segar.\n3. Tuang espresso segar.",
                'note'        => 'Karakter rasa unik dan segar khas Versi Kopi.',
            ],
            [
                'name'        => 'Kopi Susu Java Latte',
                'category'    => 'Coffee',
                'description' => 'Stronger taste, smoother — rasa kopi yang pas bikin hari lebih berasa.',
                'price_m'     => 12000,
                'price_l'     => 15000,
                'gradient'    => '#D2B49C,#98765C',
                'image_url'   => '/products/java-latte.jpg',
                'prep_time'   => '2 Menit',
                'ingredients' => [
                    ['name' => 'Espresso House Blend', 'amount_m' => '35', 'amount_l' => '50', 'unit' => 'ml'],
                    ['name' => 'Susu Segar Pasteurisasi', 'amount_m' => '120', 'amount_l' => '150', 'unit' => 'ml'],
                    ['name' => 'Simple Syrup', 'amount_m' => '15', 'amount_l' => '20', 'unit' => 'ml'],
                    ['name' => 'Es Batu Kristal', 'amount_m' => '90', 'amount_l' => '120', 'unit' => 'gr'],
                ],
                'steps'       => "1. Campurkan espresso dengan simple syrup.\n2. Masukkan es batu dan susu segar hingga penuh.",
                'note'        => 'Body kopi lebih kuat dengan tekstur akhir yang halus.',
            ],
            [
                'name'        => 'Kopi Susu Hazelnut',
                'category'    => 'Coffee',
                'description' => 'Sweet, nutty, classic — kopi susu lembut dengan aroma kacang hazelnut.',
                'price_m'     => 16000,
                'price_l'     => 19000,
                'gradient'    => '#DFC39D,#A88558',
                'image_url'   => '/products/kopi-hazelnut.jpg',
                'prep_time'   => '2 Menit',
                'ingredients' => [
                    ['name' => 'Espresso House Blend', 'amount_m' => '30', 'amount_l' => '45', 'unit' => 'ml'],
                    ['name' => 'Susu Segar Pasteurisasi', 'amount_m' => '120', 'amount_l' => '160', 'unit' => 'ml'],
                    ['name' => 'Sirup Buah & Varian Rasa', 'amount_m' => '25', 'amount_l' => '35', 'unit' => 'ml'],
                    ['name' => 'Es Batu Kristal', 'amount_m' => '90', 'amount_l' => '120', 'unit' => 'gr'],
                ],
                'steps'       => "1. Tuang sirup hazelnut ke dalam cup.\n2. Tambahkan es batu dan susu segar.\n3. Tuang shot espresso di atasnya.",
                'note'        => 'Aroma nutty hazelnut keluar kuat saat disajikan dingin.',
            ],
            [
                'name'        => 'Kopi Susu Butterscotch',
                'category'    => 'Coffee',
                'description' => 'Rich, buttery, smooth — kopi susu dengan sensasi butterscotch legit.',
                'price_m'     => 16000,
                'price_l'     => 19000,
                'gradient'    => '#E4BE8E,#B8864B',
                'image_url'   => '/products/kopi-butterscotch.jpg',
                'prep_time'   => '2.5 Menit',
                'ingredients' => [
                    ['name' => 'Espresso House Blend', 'amount_m' => '30', 'amount_l' => '45', 'unit' => 'ml'],
                    ['name' => 'Susu Segar Pasteurisasi', 'amount_m' => '120', 'amount_l' => '160', 'unit' => 'ml'],
                    ['name' => 'Sirup Karamel & Butterscotch', 'amount_m' => '25', 'amount_l' => '35', 'unit' => 'ml'],
                    ['name' => 'Es Batu Kristal', 'amount_m' => '90', 'amount_l' => '120', 'unit' => 'gr'],
                ],
                'steps'       => "1. Beri drizzle saus butterscotch di dinding cup.\n2. Tuang es batu dan susu segar.\n3. Tambahkan espresso di atasnya.",
                'note'        => 'Rasa buttery dan manis lembut.',
            ],
            [
                'name'        => 'Kopi Caramel',
                'category'    => 'Coffee',
                'description' => 'Sweet & bold — kombinasi espresso pekat, susu segar, dan saus karamel.',
                'price_m'     => 16000,
                'price_l'     => 19000,
                'gradient'    => '#D8B493,#A67C58',
                'image_url'   => '/products/kopi-caramel.jpg',
                'prep_time'   => '2.5 Menit',
                'ingredients' => [
                    ['name' => 'Espresso House Blend', 'amount_m' => '30', 'amount_l' => '45', 'unit' => 'ml'],
                    ['name' => 'Susu Segar Pasteurisasi', 'amount_m' => '120', 'amount_l' => '160', 'unit' => 'ml'],
                    ['name' => 'Sirup Karamel & Butterscotch', 'amount_m' => '25', 'amount_l' => '35', 'unit' => 'ml'],
                    ['name' => 'Es Batu Kristal', 'amount_m' => '90', 'amount_l' => '120', 'unit' => 'gr'],
                ],
                'steps'       => "1. Tuang sirup karamel pada dasar dan dinding cup.\n2. Masukkan es batu dan susu segar.\n3. Tuang shot espresso.",
                'note'        => 'Manis karamel berpadu dengan karakter espresso yang bold.',
            ],
            [
                'name'        => 'Versinya Salted Caramel',
                'category'    => 'Signature',
                'description' => 'Perpaduan espresso, susu creamy, dan saus salted caramel gurih manis.',
                'price_m'     => 16000,
                'price_l'     => 19000,
                'gradient'    => '#D2B49C,#98765C',
                'image_url'   => '/products/salted-caramel.jpg',
                'prep_time'   => '2.5 Menit',
                'ingredients' => [
                    ['name' => 'Espresso House Blend', 'amount_m' => '30', 'amount_l' => '45', 'unit' => 'ml'],
                    ['name' => 'Susu Segar Pasteurisasi', 'amount_m' => '120', 'amount_l' => '160', 'unit' => 'ml'],
                    ['name' => 'Sirup Karamel & Butterscotch', 'amount_m' => '25', 'amount_l' => '35', 'unit' => 'ml'],
                    ['name' => 'Es Batu Kristal', 'amount_m' => '90', 'amount_l' => '120', 'unit' => 'gr'],
                ],
                'steps'       => "1. Campurkan salted caramel di dasar cup.\n2. Masukkan es batu, susu segar, dan espresso di bagian atas.",
                'note'        => 'Sentuhan gurih garam laut menyeimbangkan manis karamel.',
            ],
            [
                'name'        => 'Americano',
                'category'    => 'Coffee',
                'description' => 'Simple but never boring — ekstrak espresso segar dengan air mineral dingin.',
                'price_m'     => 12000,
                'price_l'     => 15000,
                'gradient'    => '#B8967A,#7B5B43',
                'image_url'   => '/products/americano.jpg',
                'prep_time'   => '1.5 Menit',
                'ingredients' => [
                    ['name' => 'Espresso House Blend', 'amount_m' => '30', 'amount_l' => '60', 'unit' => 'ml'],
                    ['name' => 'Air Mineral', 'amount_m' => '150', 'amount_l' => '180', 'unit' => 'ml'],
                    ['name' => 'Es Batu Kristal', 'amount_m' => '80', 'amount_l' => '110', 'unit' => 'gr'],
                ],
                'steps'       => "1. Tuang air mineral dingin dan es batu ke dalam cup.\n2. Ekstrak espresso segar di atasnya.",
                'note'        => 'Segar, ringan, dan menonjolkan karakter asli biji kopi.',
            ],
            [
                'name'        => 'Versi Ramu',
                'category'    => 'Signature',
                'description' => 'Americano fruity segar dengan perpaduan sirup anggur hijau, apel & madu.',
                'price_m'     => 15000,
                'price_l'     => 18000,
                'gradient'    => '#B7C6A5,#7A9068',
                'image_url'   => '/products/versi-ramu.jpg',
                'prep_time'   => '2 Menit',
                'ingredients' => [
                    ['name' => 'Espresso House Blend', 'amount_m' => '30', 'amount_l' => '45', 'unit' => 'ml'],
                    ['name' => 'Sirup Buah & Varian Rasa', 'amount_m' => '25', 'amount_l' => '35', 'unit' => 'ml'],
                    ['name' => 'Air Mineral', 'amount_m' => '120', 'amount_l' => '150', 'unit' => 'ml'],
                    ['name' => 'Es Batu Kristal', 'amount_m' => '90', 'amount_l' => '120', 'unit' => 'gr'],
                ],
                'steps'       => "1. Tuang sirup anggur hijau & apel di dasar cup.\n2. Masukkan es batu dan air mineral.\n3. Tuang espresso secara perlahan agar terbentuk gradasi layer.",
                'note'        => 'Menu signature baru dengan sensasi fruity menyegarkan.',
            ],
            [
                'name'        => 'Versi Rindu',
                'category'    => 'Signature',
                'description' => 'Creamy, gurihnya ada — racikan kopi susu spesial yang bikin kangen.',
                'price_m'     => 15000,
                'price_l'     => 18000,
                'gradient'    => '#DFC39D,#A88558',
                'image_url'   => '/products/versi-rindu.jpg',
                'prep_time'   => '2 Menit',
                'ingredients' => [
                    ['name' => 'Espresso House Blend', 'amount_m' => '30', 'amount_l' => '45', 'unit' => 'ml'],
                    ['name' => 'Susu Segar Pasteurisasi', 'amount_m' => '130', 'amount_l' => '170', 'unit' => 'ml'],
                    ['name' => 'Sirup Karamel & Butterscotch', 'amount_m' => '20', 'amount_l' => '30', 'unit' => 'ml'],
                    ['name' => 'Es Batu Kristal', 'amount_m' => '90', 'amount_l' => '120', 'unit' => 'gr'],
                ],
                'steps'       => "1. Racik susu segar dengan sirup gurih creamy.\n2. Tambahkan es batu dan tuang shot espresso di atasnya.",
                'note'        => 'Tekstur lebih creamy dan gurih dibanding kopi susu biasa.',
            ],
            [
                'name'        => 'Matcha Coffee',
                'category'    => 'Signature',
                'description' => 'Layer Matcha Jepang lembut di atas berpadu dengan Americano di bawah.',
                'price_m'     => 16000,
                'price_l'     => 19000,
                'gradient'    => '#B7C6A5,#7A9068',
                'image_url'   => '/products/matcha-coffee.jpg',
                'prep_time'   => '2.5 Menit',
                'ingredients' => [
                    ['name' => 'Espresso House Blend', 'amount_m' => '30', 'amount_l' => '45', 'unit' => 'ml'],
                    ['name' => 'Bubuk Pure Matcha Jepang', 'amount_m' => '5', 'amount_l' => '7', 'unit' => 'gr'],
                    ['name' => 'Susu Segar Pasteurisasi', 'amount_m' => '100', 'amount_l' => '130', 'unit' => 'ml'],
                    ['name' => 'Es Batu Kristal', 'amount_m' => '90', 'amount_l' => '120', 'unit' => 'gr'],
                ],
                'steps'       => "1. Siapkan base espresso dan es batu di bagian bawah cup.\n2. Whisk matcha bersama susu segar lalu tuang perlahan di bagian atas.",
                'note'        => 'Tampilan dua layer hijau matcha di atas dan cokelat kopi di bawah.',
            ],

            // Non-Coffee
            [
                'name'        => 'Green Tea Latte (Matcha Latte)',
                'category'    => 'Non-Coffee',
                'description' => 'Smooth, earthy, mellow — matcha asli dengan rasa lembut & creamy.',
                'price_m'     => 15000,
                'price_l'     => 18000,
                'gradient'    => '#B7C6A5,#7A9068',
                'image_url'   => '/products/green-tea-latte.jpg',
                'prep_time'   => '2.5 Menit',
                'ingredients' => [
                    ['name' => 'Bubuk Pure Matcha Jepang', 'amount_m' => '5', 'amount_l' => '7', 'unit' => 'gr'],
                    ['name' => 'Susu Segar Pasteurisasi', 'amount_m' => '130', 'amount_l' => '170', 'unit' => 'ml'],
                    ['name' => 'Simple Syrup', 'amount_m' => '15', 'amount_l' => '20', 'unit' => 'ml'],
                    ['name' => 'Es Batu Kristal', 'amount_m' => '90', 'amount_l' => '120', 'unit' => 'gr'],
                ],
                'steps'       => "1. Larutkan bubuk matcha dengan air hangat menggunakan chasen.\n2. Tuang es batu, simple syrup, dan susu segar ke dalam cup.\n3. Tuang matcha di bagian atas.",
                'note'        => 'Warna hijau alami dengan tekstur susu yang lembut.',
            ],
            [
                'name'        => 'Lychee Tea',
                'category'    => 'Non-Coffee',
                'description' => 'Segar, manis, ringan — seduhan teh pilihan dengan aroma buah leci.',
                'price_m'     => 15000,
                'price_l'     => 18000,
                'gradient'    => '#E2A3A3,#B46A74',
                'image_url'   => '/products/ice-black-tea.jpg',
                'prep_time'   => '1.5 Menit',
                'ingredients' => [
                    ['name' => 'Seduhan Teh Hitam', 'amount_m' => '150', 'amount_l' => '200', 'unit' => 'ml'],
                    ['name' => 'Sirup Buah & Varian Rasa', 'amount_m' => '25', 'amount_l' => '35', 'unit' => 'ml'],
                    ['name' => 'Es Batu Kristal', 'amount_m' => '90', 'amount_l' => '120', 'unit' => 'gr'],
                ],
                'steps'       => "1. Campurkan teh hitam dingin dengan sirup leci.\n2. Masukkan es batu kristal dan aduk rata.",
                'note'        => 'Manis segar dan ringan.',
            ],
            [
                'name'        => 'Lemon Tea',
                'category'    => 'Non-Coffee',
                'description' => 'Fresh, asamnya pas — teh dingin segar dengan perasan lemon.',
                'price_m'     => 8000,
                'price_l'     => 11000,
                'gradient'    => '#E5C585,#B38E46',
                'image_url'   => '/products/ice-black-tea.jpg',
                'prep_time'   => '1.5 Menit',
                'ingredients' => [
                    ['name' => 'Seduhan Teh Hitam', 'amount_m' => '150', 'amount_l' => '200', 'unit' => 'ml'],
                    ['name' => 'Sirup Buah & Varian Rasa', 'amount_m' => '20', 'amount_l' => '30', 'unit' => 'ml'],
                    ['name' => 'Es Batu Kristal', 'amount_m' => '90', 'amount_l' => '120', 'unit' => 'gr'],
                ],
                'steps'       => "1. Tuang teh hitam dan konsentrat lemon segar.\n2. Tambahkan es batu kristal hingga penuh.",
                'note'        => 'Sangat menyegarkan dan cocok dipadukan dengan paket hemat.',
            ],
            [
                'name'        => 'Thai Tea',
                'category'    => 'Non-Coffee',
                'description' => 'Creamy, wangi, nagih — racikan teh Thailand autentik dengan susu.',
                'price_m'     => 15000,
                'price_l'     => 18000,
                'gradient'    => '#E4BE8E,#B8864B',
                'image_url'   => '/products/aren-temu.jpg',
                'prep_time'   => '2 Menit',
                'ingredients' => [
                    ['name' => 'Seduhan Teh Hitam', 'amount_m' => '120', 'amount_l' => '160', 'unit' => 'ml'],
                    ['name' => 'Susu Segar Pasteurisasi', 'amount_m' => '60', 'amount_l' => '80', 'unit' => 'ml'],
                    ['name' => 'Simple Syrup', 'amount_m' => '15', 'amount_l' => '20', 'unit' => 'ml'],
                    ['name' => 'Es Batu Kristal', 'amount_m' => '90', 'amount_l' => '120', 'unit' => 'gr'],
                ],
                'steps'       => "1. Campurkan seduhan teh pekat dengan susu dan simple syrup.\n2. Tuang ke dalam cup berisi es batu.",
                'note'        => 'Wangi rempah teh yang khas dan creamy.',
            ],
            [
                'name'        => 'Ice Black Tea',
                'category'    => 'Non-Coffee',
                'description' => 'Classic, timeless — teh hitam pilihan dengan rasa yang menyegarkan.',
                'price_m'     => 5000,
                'price_l'     => 8000,
                'gradient'    => '#C89D94,#8D625A',
                'image_url'   => '/products/ice-black-tea.jpg',
                'prep_time'   => '1 Menit',
                'ingredients' => [
                    ['name' => 'Seduhan Teh Hitam', 'amount_m' => '180', 'amount_l' => '220', 'unit' => 'ml'],
                    ['name' => 'Simple Syrup', 'amount_m' => '15', 'amount_l' => '20', 'unit' => 'ml'],
                    ['name' => 'Es Batu Kristal', 'amount_m' => '90', 'amount_l' => '120', 'unit' => 'gr'],
                ],
                'steps'       => "1. Siapkan es batu kristal di dalam cup.\n2. Tuang seduhan teh hitam segar dan simple syrup.",
                'note'        => 'Minuman teh klasik paling hemat dan menyegarkan.',
            ],

            // Snack
            [
                'name'        => 'Cireng Isi Ayam (5 pcs)',
                'category'    => 'Snack',
                'description' => '5 pcs cireng renyah isi ayam suwir gurih lengkap dengan saus sambal.',
                'price_m'     => 10000,
                'price_l'     => 10000,
                'gradient'    => '#E4BE8E,#B8864B',
                'image_url'   => '/products/cireng-ayam.jpg',
                'prep_time'   => '4 Menit',
                'ingredients' => [
                    ['name' => 'Bahan Cireng & Dimsum', 'amount_m' => '125', 'amount_l' => '125', 'unit' => 'gr'],
                ],
                'steps'       => "1. Goreng 5 pcs cireng isi ayam dengan api sedang hingga matang merata.\n2. Tiriskan dan sajikan hangat bersama saus sambal.",
                'note'        => 'Sajikan selagi hangat dan renyah.',
            ],
            [
                'name'        => 'Dimsum Keju (4 pcs)',
                'category'    => 'Snack',
                'description' => '4 pcs kudapan goreng renyah dengan isian keju lumer melimpah & saus.',
                'price_m'     => 13000,
                'price_l'     => 13000,
                'gradient'    => '#E5C585,#B38E46',
                'image_url'   => '/products/dimsum-keju.jpg',
                'prep_time'   => '4 Menit',
                'ingredients' => [
                    ['name' => 'Bahan Cireng & Dimsum', 'amount_m' => '140', 'amount_l' => '140', 'unit' => 'gr'],
                ],
                'steps'       => "1. Goreng 4 pcs hingga kulit keemasan dan keju di dalamnya meleleh.\n2. Sajikan bersama saus cocolan.",
                'note'        => 'Keju lumer saat digigit hangat.',
            ],
            [
                'name'        => 'French Fries (Kentang Kecil)',
                'category'    => 'Snack',
                'description' => 'Kentang goreng potong kecil (shoestring) renyah bertabur bumbu gurih.',
                'price_m'     => 8000,
                'price_l'     => 10000,
                'gradient'    => '#E5C585,#B38E46',
                'image_url'   => '/products/french-fries.jpg',
                'prep_time'   => '3 Menit',
                'ingredients' => [
                    ['name' => 'Kentang & Bumbu Snack', 'amount_m' => '100', 'amount_l' => '130', 'unit' => 'gr'],
                ],
                'steps'       => "1. Goreng kentang shoestring hingga garing keemasan.\n2. Taburi bumbu parsley gurih dan sajikan di paper cup.",
                'note'        => 'Potongan kecil (shoestring) ekstra renyah.',
            ],
            [
                'name'        => 'Kentang Potong Gede (Crinkle Cut)',
                'category'    => 'Snack',
                'description' => 'Kentang goreng potongan besar bergelombang (crinkle cut) garing di luar.',
                'price_m'     => 10000,
                'price_l'     => 12000,
                'gradient'    => '#E4BE8E,#B8864B',
                'image_url'   => '/products/kentang-crinkle.jpg',
                'prep_time'   => '3.5 Menit',
                'ingredients' => [
                    ['name' => 'Kentang & Bumbu Snack', 'amount_m' => '130', 'amount_l' => '160', 'unit' => 'gr'],
                ],
                'steps'       => "1. Goreng kentang crinkle cut hingga keemasan.\n2. Taburi bumbu gurih dan sajikan hangat.",
                'note'        => 'Potongan besar lembut di dalam dan renyah di luar.',
            ],
            [
                'name'        => 'Burger',
                'category'    => 'Snack',
                'description' => 'Roti bun lembut dengan patty krispi, selada segar, timun, dan saus spesial.',
                'price_m'     => 10000,
                'price_l'     => 13000,
                'gradient'    => '#D8B493,#A67C58',
                'image_url'   => '/products/burger.jpg',
                'prep_time'   => '4 Menit',
                'ingredients' => [
                    ['name' => 'Roti Bun & Patty Burger', 'amount_m' => '140', 'amount_l' => '180', 'unit' => 'gr'],
                ],
                'steps'       => "1. Panggang roti bun dengan sedikit butter.\n2. Goreng patty hingga krispi.\n3. Susun roti, selada, timun, patty, saus keju, dan saus sambal.",
                'note'        => 'Menu snack favorit pengganjal lapar.',
            ],
        ];

        DB::table('recipes')->delete();
        DB::table('products')->delete();

        $productIdsByName = [];
        foreach ($satuanMenus as $m) {
            $prodId = DB::table('products')->insertGetId([
                'name'         => $m['name'],
                'category'     => $m['category'],
                'item_type'    => 'satuan',
                'description'  => $m['description'],
                'bundle_items' => null,
                'price_m'      => $m['price_m'],
                'price_l'      => $m['price_l'],
                'stock'        => 60,
                'max_stock'    => 60,
                'emoji'        => $m['category'] === 'Snack' ? 'SNACK' : 'KOPI',
                'gradient'     => $m['gradient'],
                'image_url'    => $m['image_url'],
                'active'       => 1,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            $productIdsByName[$m['name']] = $prodId;

            DB::table('recipes')->insert([
                'product_id'   => $prodId,
                'prep_time'    => $m['prep_time'],
                'ingredients'  => json_encode($m['ingredients'], JSON_UNESCAPED_UNICODE),
                'steps'        => $m['steps'],
                'barista_note' => $m['note'],
                'created_at'   => now(),
                'updated_at'   => now(),
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
                'name'        => 'Paket Hemat Burger + French Fries',
                'description' => 'Burger + Fried French lengkap dengan saus sambal & keju.',
                'price_m'     => 15000,
                'price_l'     => 18000,
                'gradient'    => '#D8B493,#A67C58',
                'image_url'   => '/products/paket-burger-kentang.jpg',
                'items'       => [
                    ['product_id' => $burgerId, 'name' => 'Burger', 'qty' => 1],
                    ['product_id' => $friesId, 'name' => 'French Fries (Kentang Kecil)', 'qty' => 1],
                ],
            ],
            [
                'name'        => 'Paket Hemat Ice Black Tea',
                'description' => '1 Burger + 1 French Fries + 1 Cup Ice Black Tea segar.',
                'price_m'     => 20000,
                'price_l'     => 23000,
                'gradient'    => '#C89D94,#8D625A',
                'image_url'   => '/products/paket-hemat-tea.jpg',
                'items'       => [
                    ['product_id' => $burgerId, 'name' => 'Burger', 'qty' => 1],
                    ['product_id' => $friesId, 'name' => 'French Fries (Kentang Kecil)', 'qty' => 1],
                    ['product_id' => $blackTeaId, 'name' => 'Ice Black Tea', 'qty' => 1],
                ],
            ],
            [
                'name'        => 'Paket Hemat Lemon Tea',
                'description' => '1 Burger + 1 French Fries + 1 Cup Lemon Tea segar.',
                'price_m'     => 23000,
                'price_l'     => 26000,
                'gradient'    => '#E5C585,#B38E46',
                'image_url'   => '/products/paket-hemat-tea.jpg',
                'items'       => [
                    ['product_id' => $burgerId, 'name' => 'Burger', 'qty' => 1],
                    ['product_id' => $friesId, 'name' => 'French Fries (Kentang Kecil)', 'qty' => 1],
                    ['product_id' => $lemonTeaId, 'name' => 'Lemon Tea', 'qty' => 1],
                ],
            ],
            [
                'name'        => 'Paket Hemat Kopi Java',
                'description' => '1 Burger + 1 French Fries + 1 Cup Kopi Susu Java Latte.',
                'price_m'     => 27000,
                'price_l'     => 30000,
                'gradient'    => '#C4A484,#8A684C',
                'image_url'   => '/products/paket-hemat-kopi-java.jpg',
                'items'       => [
                    ['product_id' => $burgerId, 'name' => 'Burger', 'qty' => 1],
                    ['product_id' => $friesId, 'name' => 'French Fries (Kentang Kecil)', 'qty' => 1],
                    ['product_id' => $javaLatteId, 'name' => 'Kopi Susu Java Latte', 'qty' => 1],
                ],
            ],
            [
                'name'        => 'Bundling Kentang + Kopi Temu',
                'description' => '1 porsi French Fries + 1 Cup Kopi Susu Temu (Aren Temu).',
                'price_m'     => 22000,
                'price_l'     => 25000,
                'gradient'    => '#D2B49C,#98765C',
                'image_url'   => '/products/paket-kentang-kopi-temu.jpg',
                'items'       => [
                    ['product_id' => $friesId, 'name' => 'French Fries (Kentang Kecil)', 'qty' => 1],
                    ['product_id' => $arenTemuId, 'name' => 'Kopi Susu Temu (Aren Temu)', 'qty' => 1],
                ],
            ],
            [
                'name'        => 'Versinya Teman Dirumah Berlima (Java Latte 5)',
                'description' => 'Bundling hemat 5 Cup Kopi Susu Java Latte untuk dinikmati bersama.',
                'price_m'     => 60000,
                'price_l'     => 72000,
                'gradient'    => '#D8B493,#A67C58',
                'image_url'   => '/products/java-latte-5cup.jpg',
                'items'       => [
                    ['product_id' => $javaLatteId, 'name' => 'Kopi Susu Java Latte', 'qty' => 5],
                ],
            ],
        ];

        foreach ($paketanMenus as $b) {
            DB::table('products')->insert([
                'name'         => $b['name'],
                'category'     => 'Bundling',
                'item_type'    => 'paketan',
                'description'  => $b['description'],
                'bundle_items' => json_encode($b['items'], JSON_UNESCAPED_UNICODE),
                'price_m'      => $b['price_m'],
                'price_l'      => $b['price_l'],
                'stock'        => 30,
                'max_stock'    => 30,
                'emoji'        => 'PAKET',
                'gradient'     => $b['gradient'],
                'image_url'    => $b['image_url'],
                'active'       => 1,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }
    }

    // hitung stok bahan bersama
    private function enrichProductsWithStockCalculations($products)
    {
        $this->ensureExtendedSchema();

        $recipesByProdId = DB::table('recipes')->get()->keyBy('product_id');
        $ingredientsMap = DB::table('ingredients_stock')->get()->keyBy(function ($item) {
            return mb_strtolower(trim($item->name));
        });

        // hitung porsi dari kebutuhan bahan
        $calcFromIngredientNeeds = function (array $needsMap) use ($ingredientsMap): array {
            if (empty($needsMap)) {
                return [
                    'stock'           => 50,
                    'max_stock'       => 50,
                    'stock_percent'   => 100,
                    'limiting_reason' => null,
                ];
            }

            $minPortions = null;
            $maxPortionsForLimiting = null;
            $minPercent = null;
            $limitingName = null;

            foreach ($needsMap as $key => $info) {
                $need = (float) ($info['need'] ?? 0);
                if ($need <= 0 || ! isset($ingredientsMap[$key])) {
                    continue;
                }
                $stockRow = $ingredientsMap[$key];
                $curAmt = max(0.0, (float) $stockRow->current_stock);
                $maxAmt = max($curAmt, (float) $stockRow->max_stock, $need);

                $possiblePortions = (int) floor($curAmt / $need);
                $maxPossiblePortions = max(1, (int) floor($maxAmt / $need));
                $ingPct = $maxAmt > 0 ? round(($curAmt / $maxAmt) * 100, 1) : 100.0;

                if ($minPortions === null || $possiblePortions < $minPortions) {
                    $minPortions = $possiblePortions;
                    $maxPortionsForLimiting = $maxPossiblePortions;
                    $limitingName = "{$stockRow->name} (sisa " . round($curAmt, 1) . " {$stockRow->unit})";
                }
                if ($minPercent === null || $ingPct < $minPercent) {
                    $minPercent = $ingPct;
                }
            }

            if ($minPortions === null) {
                return [
                    'stock'           => 50,
                    'max_stock'       => 50,
                    'stock_percent'   => 100,
                    'limiting_reason' => null,
                ];
            }

            return [
                'stock'           => $minPortions,
                'max_stock'       => $maxPortionsForLimiting ?? max(20, $minPortions),
                'stock_percent'   => (int) round($minPercent ?? 100),
                'limiting_reason' => $limitingName,
            ];
        };

        return collect($products)->map(function ($prod) use ($recipesByProdId, $calcFromIngredientNeeds) {
            $itemType = $prod->item_type ?? 'satuan';
            $bundleItems = [];
            if ($itemType === 'paketan' && ! empty($prod->bundle_items)) {
                $decoded = json_decode($prod->bundle_items, true);
                if (is_array($decoded)) {
                    $bundleItems = $decoded;
                }
            }

            $needsMap = [];
            $recipeSummary = [];

            if ($itemType === 'satuan') {
                if (isset($recipesByProdId[$prod->id])) {
                    $ingList = json_decode($recipesByProdId[$prod->id]->ingredients, true);
                    if (is_array($ingList)) {
                        foreach ($ingList as $ing) {
                            $name = trim($ing['name'] ?? '');
                            $key = mb_strtolower($name);
                            $needM = (float) ($ing['amount_m'] ?? 0);
                            $needL = (float) ($ing['amount_l'] ?? $needM);
                            $unit = $ing['unit'] ?? 'ml';
                            if ($key !== '' && $needM > 0) {
                                $needsMap[$key] = [
                                    'need'  => ($needsMap[$key]['need'] ?? 0) + $needM,
                                    'label' => $name,
                                    'unit'  => $unit,
                                ];
                                $recipeSummary[] = [
                                    'name'     => $name,
                                    'amount_m' => $needM,
                                    'amount_l' => $needL,
                                    'unit'     => $unit,
                                ];
                            }
                        }
                    }
                }
            } elseif ($itemType === 'paketan' && ! empty($bundleItems)) {
                // gabung bahan menu paketan
                foreach ($bundleItems as $bItem) {
                    $compId = (int) ($bItem['product_id'] ?? 0);
                    $compQty = max(1, (int) ($bItem['qty'] ?? 1));
                    if (isset($recipesByProdId[$compId])) {
                        $ingList = json_decode($recipesByProdId[$compId]->ingredients, true);
                        if (is_array($ingList)) {
                            foreach ($ingList as $ing) {
                                $name = trim($ing['name'] ?? '');
                                $key = mb_strtolower($name);
                                $needM = (float) ($ing['amount_m'] ?? 0) * $compQty;
                                $needL = (float) ($ing['amount_l'] ?? ($ing['amount_m'] ?? 0)) * $compQty;
                                $unit = $ing['unit'] ?? 'ml';
                                if ($key !== '' && $needM > 0) {
                                    $needsMap[$key] = [
                                        'need'      => ($needsMap[$key]['need'] ?? 0) + $needM,
                                        'need_l'    => ($needsMap[$key]['need_l'] ?? 0) + $needL,
                                        'label'     => $name,
                                        'unit'      => $unit,
                                    ];
                                }
                            }
                        }
                    }
                }
                foreach ($needsMap as $info) {
                    $recipeSummary[] = [
                        'name'     => $info['label'],
                        'amount_m' => $info['need'],
                        'amount_l' => $info['need_l'] ?? $info['need'],
                        'unit'     => $info['unit'],
                    ];
                }
            }

            $calc = $calcFromIngredientNeeds($needsMap);
            $effectiveStock = (int) $calc['stock'];
            $maxStock = (int) $calc['max_stock'];
            $stockPercent = (int) $calc['stock_percent'];
            $limitingReason = $calc['limiting_reason'];

            // batas kunci stok
            $isLocked = ($effectiveStock <= 3) || ($stockPercent <= 5);

            // batas peringatan stok
            $isWarning = ! $isLocked && (($effectiveStock <= 15) || ($stockPercent <= 20));

            $prodObj = (array) $prod;
            $prodObj['item_type'] = $itemType;
            $prodObj['bundle_items'] = $bundleItems;
            $prodObj['recipe_summary'] = $recipeSummary;
            $prodObj['stock'] = $effectiveStock;
            $prodObj['raw_stock'] = $effectiveStock;
            $prodObj['max_stock'] = $maxStock;
            $prodObj['stock_percent'] = $stockPercent;
            $prodObj['is_locked'] = $isLocked;
            $prodObj['is_warning'] = $isWarning;
            $prodObj['limiting_reason'] = $limitingReason ? "Bahan {$limitingReason}" : null;

            return $prodObj;
        })->values();
    }

    public function getSettings()
    {
        $this->ensureExtendedSchema();
        $settings = DB::table('settings')->pluck('value', 'key');
        return response()->json($settings);
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'store_name'      => ['required', 'string', 'max:100'],
            'tagline'         => ['nullable', 'string', 'max:160'],
            'address'         => ['nullable', 'string', 'max:255'],
            'phone'           => ['nullable', 'string', 'max:40'],
            'tax_percent'     => ['nullable', 'numeric', 'min:0', 'max:100'],
            'service_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'qris_merchant'   => ['nullable', 'string', 'max:100'],
            'receipt_footer'  => ['nullable', 'string', 'max:255'],
            'custom_note'     => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated as $key => $val) {
                if (! in_array($key, self::ALLOWED_SETTINGS, true)) {
                    continue;
                }
                $cleanVal = is_numeric($val) ? (string) $val : $this->cleanText((string) $val, 255);
                DB::table('settings')->updateOrInsert(
                    ['key' => $key],
                    ['value' => $cleanVal, 'updated_at' => now(), 'created_at' => now()]
                );
            }
        });

        return response()->json([
            'success'  => true,
            'message'  => 'Pengaturan toko berhasil diperbarui.',
            'settings' => DB::table('settings')->pluck('value', 'key'),
        ]);
    }

    public function getPromos(Request $request)
    {
        $query = DB::table('promos')->orderBy('id', 'desc');
        if (! $request->boolean('all')) {
            $query->where('active', 1);
        }

        return response()->json($query->get());
    }

    public function storePromo(Request $request)
    {
        $data = $request->validate([
            'code'         => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9_-]+$/'],
            'type'         => ['required', 'string', 'in:percent,fixed'],
            'value'        => ['required', 'integer', 'min:1', 'max:10000000'],
            'min_subtotal' => ['required', 'integer', 'min:0', 'max:100000000'],
            'max_discount' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'active'       => ['nullable', 'boolean'],
        ]);

        if ($data['type'] === 'percent' && (int) $data['value'] > 100) {
            throw ValidationException::withMessages([
                'value' => ['Nilai diskon persentase tidak boleh lebih dari 100%.'],
            ]);
        }

        $code = strtoupper($this->cleanText($data['code'], 32));

        $exists = DB::table('promos')->where('code', $code)->exists();
        if ($exists) {
            throw ValidationException::withMessages([
                'code' => ["Kode promo {$code} sudah terdaftar."],
            ]);
        }

        $id = DB::table('promos')->insertGetId([
            'code'         => $code,
            'type'         => $data['type'],
            'value'        => (int) $data['value'],
            'min_subtotal' => (int) $data['min_subtotal'],
            'max_discount' => (int) ($data['max_discount'] ?? 0),
            'active'       => isset($data['active']) ? (int) (bool) $data['active'] : 1,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        return response()->json([
            'success' => true,
            'id'      => $id,
            'message' => "Promo {$code} berhasil ditambahkan.",
        ], 201);
    }

    public function updatePromo(Request $request, int $id)
    {
        $promo = DB::table('promos')->where('id', $id)->first();
        if (! $promo) {
            return response()->json(['message' => 'Promo tidak ditemukan.'], 404);
        }

        $data = $request->validate([
            'code'         => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9_-]+$/'],
            'type'         => ['required', 'string', 'in:percent,fixed'],
            'value'        => ['required', 'integer', 'min:1', 'max:10000000'],
            'min_subtotal' => ['required', 'integer', 'min:0', 'max:100000000'],
            'max_discount' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'active'       => ['required', 'boolean'],
        ]);

        $code = strtoupper($this->cleanText($data['code'], 32));
        $duplicate = DB::table('promos')->where('code', $code)->where('id', '!=', $id)->exists();
        if ($duplicate) {
            throw ValidationException::withMessages([
                'code' => ["Kode promo {$code} sudah digunakan promo lain."],
            ]);
        }

        DB::table('promos')->where('id', $id)->update([
            'code'         => $code,
            'type'         => $data['type'],
            'value'        => (int) $data['value'],
            'min_subtotal' => (int) $data['min_subtotal'],
            'max_discount' => (int) ($data['max_discount'] ?? 0),
            'active'       => (int) (bool) $data['active'],
            'updated_at'   => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Promo {$code} berhasil diperbarui.",
        ]);
    }

    public function deletePromo(int $id)
    {
        DB::table('promos')->where('id', $id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kode promo berhasil dihapus.',
        ]);
    }

    public function getProducts()
    {
        $this->ensureExtendedSchema();

        $products = DB::table('products')
            ->where('active', 1)
            ->orderBy('id', 'asc')
            ->get();

        return response()->json($this->enrichProductsWithStockCalculations($products));
    }

    public function storeProduct(Request $request)
    {
        $this->ensureExtendedSchema();

        $data = $request->validate([
            'name'                      => ['required', 'string', 'max:120'],
            'category'                  => ['required', 'string', 'in:' . implode(',', self::ALLOWED_CATEGORIES)],
            'item_type'                 => ['nullable', 'string', 'in:satuan,paketan'],
            'bundle_items'              => ['nullable', 'array'],
            'bundle_items.*.product_id' => ['required_with:bundle_items', 'integer', 'min:1'],
            'bundle_items.*.qty'        => ['required_with:bundle_items', 'integer', 'min:1', 'max:50'],
            'description'               => ['nullable', 'string', 'max:500'],
            'price_m'                   => ['required', 'integer', 'min:0', 'max:10000000'],
            'price_l'                   => ['required', 'integer', 'min:0', 'max:10000000'],
            'stock'                     => ['required', 'integer', 'min:0', 'max:100000'],
            'max_stock'                 => ['nullable', 'integer', 'min:1', 'max:100000'],
            'gradient'                  => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6},#[0-9A-Fa-f]{6}$/'],
            'image_url'                 => ['nullable', 'string', 'max:255'],
        ]);

        $actorName = $this->cleanText($request->user()?->name ?? 'Admin', 100);
        $name = $this->cleanText($data['name'], 120);
        $itemType = $data['item_type'] ?? 'satuan';
        $description = $this->cleanText($data['description'] ?? '', 500);
        $gradient = $data['gradient'] ?? '#D8B493,#A67C58';
        $imageUrl = isset($data['image_url']) ? $this->cleanText($data['image_url'], 255) : null;
        $stock = (int) $data['stock'];
        $maxStock = max($stock, (int) ($data['max_stock'] ?? max(60, $stock)));

        $cleanBundles = [];
        if ($itemType === 'paketan' && ! empty($data['bundle_items'])) {
            foreach ($data['bundle_items'] as $bi) {
                $comp = DB::table('products')->where('id', (int) $bi['product_id'])->first();
                if ($comp) {
                    $cleanBundles[] = [
                        'product_id' => (int) $comp->id,
                        'name'       => $comp->name,
                        'qty'        => (int) $bi['qty'],
                    ];
                }
            }
        }

        $id = DB::transaction(function () use ($name, $data, $itemType, $cleanBundles, $description, $stock, $maxStock, $gradient, $imageUrl, $actorName) {
            $productId = DB::table('products')->insertGetId([
                'name'         => $name,
                'category'     => $itemType === 'paketan' ? 'Bundling' : $data['category'],
                'item_type'    => $itemType,
                'description'  => $description,
                'bundle_items' => ! empty($cleanBundles) ? json_encode($cleanBundles, JSON_UNESCAPED_UNICODE) : null,
                'price_m'      => (int) $data['price_m'],
                'price_l'      => (int) $data['price_l'],
                'stock'        => $stock,
                'max_stock'    => $maxStock,
                'emoji'        => $itemType === 'paketan' ? 'PAKET' : 'KOPI',
                'gradient'     => $gradient,
                'image_url'    => $imageUrl,
                'active'       => 1,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            DB::table('inventory_logs')->insert([
                'product_id'   => $productId,
                'product_name' => $name,
                'type'         => 'in',
                'qty'          => $stock,
                'stock_after'  => $stock,
                'note'         => 'Stok awal penambahan menu baru',
                'reference'    => 'NEW-ITEM',
                'actor_name'   => $actorName,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            return $productId;
        });

        return response()->json([
            'success' => true,
            'id'      => $id,
            'message' => 'Menu baru berhasil ditambahkan.',
        ], 201);
    }

    public function updateProduct(Request $request, int $id)
    {
        $this->ensureExtendedSchema();

        $data = $request->validate([
            'name'                      => ['required', 'string', 'max:120'],
            'category'                  => ['required', 'string', 'in:' . implode(',', self::ALLOWED_CATEGORIES)],
            'item_type'                 => ['nullable', 'string', 'in:satuan,paketan'],
            'bundle_items'              => ['nullable', 'array'],
            'bundle_items.*.product_id' => ['required_with:bundle_items', 'integer', 'min:1'],
            'bundle_items.*.qty'        => ['required_with:bundle_items', 'integer', 'min:1', 'max:50'],
            'description'               => ['nullable', 'string', 'max:500'],
            'price_m'                   => ['required', 'integer', 'min:0', 'max:10000000'],
            'price_l'                   => ['required', 'integer', 'min:0', 'max:10000000'],
            'max_stock'                 => ['nullable', 'integer', 'min:1', 'max:100000'],
            'gradient'                  => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6},#[0-9A-Fa-f]{6}$/'],
            'image_url'                 => ['nullable', 'string', 'max:255'],
        ]);

        $product = DB::table('products')->where('id', $id)->where('active', 1)->first();
        if (! $product) {
            return response()->json(['message' => 'Produk tidak ditemukan.'], 404);
        }

        $itemType = $data['item_type'] ?? ($product->item_type ?? 'satuan');
        $cleanBundles = [];
        if ($itemType === 'paketan' && ! empty($data['bundle_items'])) {
            foreach ($data['bundle_items'] as $bi) {
                $comp = DB::table('products')->where('id', (int) $bi['product_id'])->first();
                if ($comp) {
                    $cleanBundles[] = [
                        'product_id' => (int) $comp->id,
                        'name'       => $comp->name,
                        'qty'        => (int) $bi['qty'],
                    ];
                }
            }
        }

        DB::table('products')->where('id', $id)->update([
            'name'         => $this->cleanText($data['name'], 120),
            'category'     => $itemType === 'paketan' ? 'Bundling' : $data['category'],
            'item_type'    => $itemType,
            'description'  => $this->cleanText($data['description'] ?? '', 500),
            'bundle_items' => $itemType === 'paketan' ? json_encode($cleanBundles, JSON_UNESCAPED_UNICODE) : null,
            'price_m'      => (int) $data['price_m'],
            'price_l'      => (int) $data['price_l'],
            'max_stock'    => isset($data['max_stock']) ? max(1, (int) $data['max_stock']) : ($product->max_stock ?? 60),
            'gradient'     => $data['gradient'] ?? $product->gradient,
            'image_url'    => array_key_exists('image_url', $data) ? $this->cleanText((string) $data['image_url'], 255) : ($product->image_url ?? null),
            'updated_at'   => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Data menu berhasil diperbarui.',
        ]);
    }

    public function deleteProduct(int $id)
    {
        $product = DB::table('products')->where('id', $id)->where('active', 1)->first();
        if (! $product) {
            return response()->json(['message' => 'Produk tidak ditemukan.'], 404);
        }

        DB::table('products')->where('id', $id)->update([
            'active'     => 0,
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Menu berhasil dinonaktifkan.',
        ]);
    }

    public function getIngredients()
    {
        $this->ensureExtendedSchema();

        $productsById = DB::table('products')->where('active', 1)->get()->keyBy('id');
        $recipes = DB::table('recipes')->get();
        $usedByMap = [];

        foreach ($recipes as $rec) {
            $prod = $productsById[$rec->product_id] ?? null;
            if (! $prod) {
                continue;
            }
            $ingList = json_decode($rec->ingredients, true);
            if (! is_array($ingList)) {
                continue;
            }
            foreach ($ingList as $ing) {
                $key = mb_strtolower(trim($ing['name'] ?? ''));
                $amtM = (float) ($ing['amount_m'] ?? 0);
                if ($key !== '' && $amtM > 0) {
                    $usedByMap[$key][] = [
                        'product_id'   => (int) $prod->id,
                        'product_name' => $prod->name,
                        'amount_m'     => $amtM,
                        'unit'         => $ing['unit'] ?? 'ml',
                    ];
                }
            }
        }

        $rows = DB::table('ingredients_stock')->orderBy('name', 'asc')->get()->map(function ($ing) use ($usedByMap) {
            $max = max(1, (float) $ing->max_stock);
            $cur = max(0, (float) $ing->current_stock);
            $pct = (int) round(($cur / $max) * 100);
            $key = mb_strtolower(trim($ing->name));
            return [
                'id'            => (int) $ing->id,
                'name'          => $ing->name,
                'unit'          => $ing->unit,
                'current_stock' => round($cur, 1),
                'max_stock'     => round($max, 1),
                'stock_amount'  => round($cur, 1),
                'max_amount'    => round($max, 1),
                'stock_percent' => $pct,
                'is_locked'     => $pct <= 5,
                'is_warning'    => $pct > 5 && $pct <= 20,
                'used_by'       => $usedByMap[$key] ?? [],
            ];
        });

        return response()->json(['ingredients' => $rows]);
    }

    public function restockIngredient(Request $request)
    {
        $this->ensureExtendedSchema();

        $rawAmt = $request->input('amount', $request->input('qty'));
        $request->merge(['amount' => $rawAmt]);

        $data = $request->validate([
            'name'   => ['required', 'string', 'max:120'],
            'unit'   => ['required', 'string', 'in:' . implode(',', self::ALLOWED_UNITS)],
            'amount' => ['required', 'numeric', 'min:0.1', 'max:1000000'],
            'mode'   => ['nullable', 'string', 'in:add,set'],
            'note'   => ['nullable', 'string', 'max:255'],
        ]);

        $name = $this->cleanText($data['name'], 120);
        $qty = (float) $data['amount'];
        $mode = $data['mode'] ?? 'add';
        $actorName = $this->cleanText($request->user()?->name ?? 'Admin', 100);
        $note = $this->cleanText($data['note'] ?? '', 255) ?: ($mode === 'set' ? 'Atur stok penuh bahan baku (100%)' : 'Tambah stok bahan baku gudang');

        $existing = DB::table('ingredients_stock')
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing) {
            if ($mode === 'set') {
                $newStock = $qty;
                $newMax = $qty;
            } else {
                $newStock = (float) $existing->current_stock + $qty;
                $newMax = max((float) $existing->max_stock, $newStock);
            }
            DB::table('ingredients_stock')->where('id', $existing->id)->update([
                'current_stock' => $newStock,
                'max_stock'     => $newMax,
                'unit'          => $data['unit'],
                'updated_at'    => now(),
            ]);
            $ingId = (int) $existing->id;
        } else {
            $newStock = $qty;
            $ingId = DB::table('ingredients_stock')->insertGetId([
                'name'          => $name,
                'unit'          => $data['unit'],
                'current_stock' => $qty,
                'max_stock'     => $qty,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }

        DB::table('inventory_logs')->insert([
            'product_id'   => $ingId,
            'product_name' => "Bahan: {$name} ({$data['unit']})",
            'type'         => 'in',
            'qty'          => (int) round($qty),
            'stock_after'  => (int) round($newStock),
            'note'         => $note,
            'reference'    => $mode === 'set' ? 'SET-100%' : 'RESTOCK-BAHAN',
            'actor_name'   => $actorName,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => $mode === 'set'
                ? "Kapasitas 100% bahan {$name} diatur ke {$qty} {$data['unit']}. Seluruh menu yang memakai bahan ini otomatis menyesuaikan!"
                : "Stok bahan baku {$name} (+{$qty} {$data['unit']}) berhasil ditambahkan ke gudang.",
        ]);
    }

    public function getRecipes()
    {
        $this->ensureExtendedSchema();

        $rows = DB::table('recipes')->orderBy('product_id', 'asc')->get();
        $recipes = $rows->map(function ($row) {
            $decoded = json_decode($row->ingredients, true);
            return [
                'id'           => (int) $row->id,
                'product_id'   => (int) $row->product_id,
                'prep_time'    => $row->prep_time,
                'ingredients'  => is_array($decoded) ? $decoded : [],
                'steps'        => $row->steps ?? '',
                'barista_note' => $row->barista_note ?? '',
                'updated_at'   => $row->updated_at,
            ];
        });

        return response()->json(['recipes' => $recipes]);
    }

    public function saveRecipe(Request $request)
    {
        $this->ensureExtendedSchema();

        $data = $request->validate([
            'product_id'             => ['required', 'integer', 'min:1'],
            'prep_time'              => ['nullable', 'string', 'max:50'],
            'ingredients'            => ['required', 'array', 'min:1', 'max:40'],
            'ingredients.*.name'     => ['required', 'string', 'max:120'],
            'ingredients.*.amount_m' => ['required', 'string', 'max:30'],
            'ingredients.*.amount_l' => ['required', 'string', 'max:30'],
            'ingredients.*.unit'     => ['required', 'string', 'in:' . implode(',', self::ALLOWED_UNITS)],
            'steps'                  => ['nullable', 'string', 'max:2000'],
            'barista_note'           => ['nullable', 'string', 'max:255'],
        ]);

        $productId = (int) $data['product_id'];
        $product = DB::table('products')->where('id', $productId)->where('active', 1)->first();

        if (! $product) {
            return response()->json(['message' => 'Produk tidak ditemukan.'], 404);
        }

        $cleanIngredients = [];
        foreach ($data['ingredients'] as $ing) {
            $name = $this->cleanText($ing['name'], 120);
            if ($name === '') {
                continue;
            }
            $cleanIngredients[] = [
                'name'     => $name,
                'amount_m' => $this->cleanText($ing['amount_m'], 30),
                'amount_l' => $this->cleanText($ing['amount_l'], 30),
                'unit'     => $ing['unit'],
            ];

            // tambah bahan baru ke gudang
            $existsIng = DB::table('ingredients_stock')
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->exists();
            if (! $existsIng) {
                $defaultCap = $ing['unit'] === 'ml' ? 5000 : ($ing['unit'] === 'gr' ? 2000 : 200);
                DB::table('ingredients_stock')->insert([
                    'name'          => $name,
                    'unit'          => $ing['unit'],
                    'current_stock' => $defaultCap,
                    'max_stock'     => $defaultCap,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }
        }

        if (empty($cleanIngredients)) {
            throw ValidationException::withMessages([
                'ingredients' => ['Minimal satu bahan baku wajib diisi dengan benar.'],
            ]);
        }

        $prepTime = $this->cleanText($data['prep_time'] ?? '2 Menit', 50) ?: '2 Menit';
        $steps = $this->cleanText($data['steps'] ?? '', 2000);
        $baristaNote = $this->cleanText($data['barista_note'] ?? '', 255);

        DB::table('recipes')->updateOrInsert(
            ['product_id' => $productId],
            [
                'prep_time'    => $prepTime,
                'ingredients'  => json_encode($cleanIngredients, JSON_UNESCAPED_UNICODE),
                'steps'        => $steps,
                'barista_note' => $baristaNote,
                'updated_at'   => now(),
                'created_at'   => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => "Resep untuk {$product->name} berhasil disimpan.",
        ]);
    }

    public function deleteRecipe(int $productId)
    {
        $this->ensureExtendedSchema();

        DB::table('recipes')->where('product_id', $productId)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Resep berhasil dihapus.',
        ]);
    }

    public function restockProduct(Request $request, int $id)
    {
        $this->ensureExtendedSchema();

        $data = $request->validate([
            'qty'  => ['required', 'integer', 'min:1', 'max:50000'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $qty = (int) $data['qty'];
        $note = $this->cleanText($data['note'] ?? '', 255) ?: 'Restock barang masuk gudang';
        $actorName = $this->cleanText($request->user()?->name ?? 'Admin', 100);

        $result = DB::transaction(function () use ($id, $qty, $note, $actorName) {
            $product = DB::table('products')->where('id', $id)->lockForUpdate()->first();

            if (! $product) {
                return null;
            }

            $newStock = (int) $product->stock + $qty;
            $newMaxStock = max((int) ($product->max_stock ?? 60), $newStock);

            DB::table('products')->where('id', $id)->update([
                'stock'      => $newStock,
                'max_stock'  => $newMaxStock,
                'updated_at' => now(),
            ]);

            DB::table('inventory_logs')->insert([
                'product_id'   => $id,
                'product_name' => $product->name,
                'type'         => 'in',
                'qty'          => $qty,
                'stock_after'  => $newStock,
                'note'         => $note,
                'reference'    => 'RESTOCK',
                'actor_name'   => $actorName,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            return $newStock;
        });

        if ($result === null) {
            return response()->json(['message' => 'Produk tidak ditemukan.'], 404);
        }

        return response()->json([
            'success'   => true,
            'new_stock' => $result,
            'message'   => 'Stok berhasil ditambahkan.',
        ]);
    }

    public function getInventoryLogs()
    {
        $logs = DB::table('inventory_logs')
            ->orderBy('id', 'desc')
            ->limit(100)
            ->get();

        return response()->json(['logs' => $logs]);
    }

    public function getOrders()
    {
        $orders = DB::table('orders')
            ->orderBy('id', 'desc')
            ->limit(200)
            ->get();

        return response()->json(['orders' => $orders]);
    }

    // potong bahan baku gudang
    private function deductRawMaterialsForProduct(int $productId, string $size, int $qty, string $orderCode = '', string $actorName = 'Kasir'): void
    {
        $recipe = DB::table('recipes')->where('product_id', $productId)->first();
        if (! $recipe) {
            return;
        }
        $prod = DB::table('products')->where('id', $productId)->first();
        $prodName = $prod->name ?? "Menu #{$productId}";

        $ingredients = json_decode($recipe->ingredients, true);
        if (! is_array($ingredients)) {
            return;
        }

        foreach ($ingredients as $ing) {
            $name = trim($ing['name'] ?? '');
            $amountPerCup = $size === 'L' ? (float) ($ing['amount_l'] ?? 0) : (float) ($ing['amount_m'] ?? 0);
            $totalUse = $amountPerCup * $qty;

            if ($name === '' || $totalUse <= 0) {
                continue;
            }

            $stockRow = DB::table('ingredients_stock')
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->lockForUpdate()
                ->first();

            if ($stockRow) {
                $newIngStock = max(0, (float) $stockRow->current_stock - $totalUse);
                DB::table('ingredients_stock')->where('id', $stockRow->id)->update([
                    'current_stock' => $newIngStock,
                    'updated_at'    => now(),
                ]);

                DB::table('inventory_logs')->insert([
                    'product_id'   => (int) $stockRow->id,
                    'product_name' => "Bahan: {$stockRow->name} ({$stockRow->unit})",
                    'type'         => 'out',
                    'qty'          => (int) round($totalUse),
                    'stock_after'  => (int) round($newIngStock),
                    'note'         => "Pemakaian {$qty}x {$prodName} ({$size}) • {$orderCode}",
                    'reference'    => $orderCode ?: 'ORDER',
                    'actor_name'   => $actorName,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }
        }
    }

    public function storeOrder(Request $request)
    {
        $this->ensureExtendedSchema();

        $data = $request->validate([
            'items'            => ['required', 'array', 'min:1', 'max:100'],
            'items.*.id'       => ['required', 'integer', 'min:1'],
            'items.*.qty'      => ['required', 'integer', 'min:1', 'max:500'],
            'items.*.size'     => ['nullable', 'string', 'in:M,L'],
            'items.*.sugar'    => ['nullable', 'string', 'in:' . implode(',', self::ALLOWED_SUGAR)],
            'items.*.note'     => ['nullable', 'string', 'max:200'],
            'promo_code'       => ['nullable', 'string', 'max:50'],
            'payment_method'   => ['required', 'string', 'in:cash,qris'],
            'cash_received'    => ['nullable', 'numeric', 'min:0', 'max:100000000'],
        ]);

        $user = $request->user();
        $cashierId = (int) ($user?->id ?? $request->input('cashier_id', 2));
        $cashierName = $this->cleanText($user?->name ?? $request->input('cashier_name', 'Kasir Versi Kopi'), 100);

        return DB::transaction(function () use ($data, $cashierId, $cashierName) {
            $wib = now()->setTimezone('Asia/Jakarta');
            $prefix = 'KS-' . $wib->format('Ymd');
            $escapedPrefix = addcslashes($prefix, '%_\\');
            $count = DB::table('orders')
                ->where('code', 'like', "{$escapedPrefix}-%")
                ->lockForUpdate()
                ->count();
            $orderCode = $prefix . '-' . str_pad((string) ($count + 1), 3, '0', STR_PAD_LEFT);

            $enrichedMap = $this->enrichProductsWithStockCalculations(
                DB::table('products')->where('active', 1)->lockForUpdate()->get()
            )->keyBy('id');

            $subtotal = 0;
            $sanitizedItems = [];
            $stockDeductions = [];
            $recipeUsageList = [];

            foreach ($data['items'] as $reqItem) {
                $prodId = (int) $reqItem['id'];
                $qty = (int) $reqItem['qty'];
                $size = $reqItem['size'] ?? 'M';
                $sugar = $reqItem['sugar'] ?? '100%';
                $note = $this->cleanText($reqItem['note'] ?? '', 200);

                $prod = $enrichedMap[$prodId] ?? null;

                if (! $prod) {
                    throw ValidationException::withMessages([
                        'items' => ["Produk dengan ID {$prodId} tidak ditemukan atau sudah tidak aktif."],
                    ]);
                }

                // cek kunci stok
                if ($prod['is_locked']) {
                    throw ValidationException::withMessages([
                        'items' => ["Menu {$prod['name']} terkunci karena stok kritis (tersisa {$prod['stock']} porsi / {$prod['stock_percent']}%)."],
                    ]);
                }

                $alreadyRequested = $stockDeductions[$prodId]['qty'] ?? 0;
                $usableStock = max(0, (int) $prod['stock'] - 3);
                if (($alreadyRequested + $qty) > (int) $prod['stock']) {
                    throw ValidationException::withMessages([
                        'items' => ["Stok {$prod['name']} tidak mencukupi (tersedia {$usableStock} porsi yang dapat dipesan)."],
                    ]);
                }

                $unitPrice = $size === 'L' ? (int) $prod['price_l'] : (int) $prod['price_m'];
                $subtotal += $unitPrice * $qty;

                $sanitizedItems[] = [
                    'id'          => (int) $prod['id'],
                    'name'        => $prod['name'],
                    'category'    => $prod['category'],
                    'item_type'   => $prod['item_type'],
                    'size'        => $size,
                    'sugar'       => $sugar,
                    'note'        => $note,
                    'unit_price'  => $unitPrice,
                    'qty'         => $qty,
                ];

                $stockDeductions[$prodId] = [
                    'product' => $prod,
                    'qty'     => $alreadyRequested + $qty,
                ];

                // potong stok komponen paket
                if ($prod['item_type'] === 'paketan' && ! empty($prod['bundle_items'])) {
                    foreach ($prod['bundle_items'] as $bItem) {
                        $compId = (int) ($bItem['product_id'] ?? 0);
                        $compQty = max(1, (int) ($bItem['qty'] ?? 1)) * $qty;
                        if (isset($enrichedMap[$compId])) {
                            $prevCompQty = $stockDeductions[$compId]['qty'] ?? 0;
                            $stockDeductions[$compId] = [
                                'product' => $enrichedMap[$compId],
                                'qty'     => $prevCompQty + $compQty,
                            ];
                            $recipeUsageList[] = ['product_id' => $compId, 'size' => $size, 'qty' => $compQty];
                        }
                    }
                } else {
                    $recipeUsageList[] = ['product_id' => $prodId, 'size' => $size, 'qty' => $qty];
                }
            }

            $discount = 0;
            $appliedPromoCode = null;
            if (! empty($data['promo_code'])) {
                $code = strtoupper($this->cleanText($data['promo_code'], 50));
                $promo = DB::table('promos')
                    ->where('code', $code)
                    ->where('active', 1)
                    ->first();

                if ($promo && $subtotal >= (int) $promo->min_subtotal) {
                    $appliedPromoCode = $promo->code;
                    if ($promo->type === 'percent') {
                        $discount = (int) round(($subtotal * (int) $promo->value) / 100);
                        if ((int) $promo->max_discount > 0 && $discount > (int) $promo->max_discount) {
                            $discount = (int) $promo->max_discount;
                        }
                    } else {
                        $discount = (int) $promo->value;
                    }
                    $discount = min($discount, $subtotal);
                }
            }

            $settings = DB::table('settings')->pluck('value', 'key');
            $taxPercent = max(0, min(100, (float) ($settings['tax_percent'] ?? 0)));
            $servicePercent = max(0, min(100, (float) ($settings['service_percent'] ?? 0)));

            $taxableAmount = max(0, $subtotal - $discount);
            $tax = (int) round(($taxableAmount * $taxPercent) / 100);
            $service = (int) round(($taxableAmount * $servicePercent) / 100);
            $total = max(0, $taxableAmount + $tax + $service);

            $paymentMethod = $data['payment_method'];
            $cashReceived = $paymentMethod === 'cash'
                ? (int) ($data['cash_received'] ?? 0)
                : $total;

            if ($paymentMethod === 'cash' && $cashReceived < $total) {
                throw ValidationException::withMessages([
                    'cash_received' => ['Nominal uang tunai yang diterima kurang dari total tagihan.'],
                ]);
            }

            $changeAmount = $paymentMethod === 'cash' ? max(0, $cashReceived - $total) : 0;

            $orderId = DB::table('orders')->insertGetId([
                'code'           => $orderCode,
                'cashier_id'     => $cashierId,
                'cashier_name'   => $cashierName,
                'items'          => json_encode($sanitizedItems, JSON_UNESCAPED_UNICODE),
                'subtotal'       => $subtotal,
                'discount'       => $discount,
                'promo_code'     => $appliedPromoCode,
                'tax'            => $tax,
                'service'        => $service,
                'total'          => $total,
                'payment_method' => $paymentMethod,
                'cash_received'  => $cashReceived,
                'change_amount'  => $changeAmount,
                'status'         => 'paid',
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            foreach ($stockDeductions as $prodId => $deduction) {
                $prod = $deduction['product'];
                $deductQty = $deduction['qty'];
                $newStock = max(0, (int) $prod['raw_stock'] - $deductQty);

                DB::table('products')->where('id', $prodId)->update([
                    'stock'      => $newStock,
                    'updated_at' => now(),
                ]);
            }

            // potong bahan gudang
            foreach ($recipeUsageList as $usage) {
                $this->deductRawMaterialsForProduct($usage['product_id'], $usage['size'], $usage['qty'], $orderCode, $cashierName);
            }

            return response()->json([
                'success'       => true,
                'order_code'    => $orderCode,
                'order_id'      => $orderId,
                'subtotal'      => $subtotal,
                'discount'      => $discount,
                'tax'           => $tax,
                'service'       => $service,
                'total'         => $total,
                'cash_received' => $cashReceived,
                'change_amount' => $changeAmount,
                'items'         => $sanitizedItems,
            ], 201);
        });
    }
}
