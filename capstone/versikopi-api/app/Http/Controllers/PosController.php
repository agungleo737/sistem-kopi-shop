<?php

namespace App\Http\Controllers;

use App\Services\InventoryCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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

    private function cleanName(string $name): string
    {
        return preg_replace('/\s+/u', ' ', $this->cleanText($name, 120));
    }

    private function namedRecord(string $table, string $name, ?int $exceptId = null): ?object
    {
        $key = mb_strtolower($this->cleanName($name));

        return DB::table($table)->get()->first(fn (object $row): bool => (int) $row->id !== $exceptId && mb_strtolower($this->cleanName($row->name)) === $key);
    }

    private function assertUniqueMenuName(string $name, ?int $exceptId = null): void
    {
        if ($this->namedRecord('products', $name, $exceptId)) {
            throw ValidationException::withMessages(['name' => ['Menu sudah ada. Gunakan menu yang sudah terdaftar.']]);
        }
    }

    private function logoVersion(): ?string
    {
        return DB::table('store_logos')->where('id', 1)->value('version');
    }

    public function getBranding(): JsonResponse
    {
        $branding = DB::table('settings')->whereIn('key', ['store_name', 'tagline'])->pluck('value', 'key');

        return response()->json([
            'store_name' => $branding['store_name'] ?? null,
            'tagline' => $branding['tagline'] ?? null,
            'logo_version' => $this->logoVersion(),
        ]);
    }

    public function getStoreLogo(): Response
    {
        $logo = DB::table('store_logos')->where('id', 1)->first();
        abort_if($logo === null, 404);

        return response(base64_decode($logo->encoded_image, true), 200, [
            'Content-Type' => $logo->mime,
            'Cache-Control' => 'no-cache',
        ]);
    }

    public function getSettings(): JsonResponse
    {
        $settings = DB::table('settings')
            ->whereIn('key', self::ALLOWED_SETTINGS)
            ->pluck('value', 'key');

        return response()->json($settings->put('logo_version', $this->logoVersion()));
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_name' => ['required', 'string', 'max:100'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'service_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'qris_merchant' => ['nullable', 'string', 'max:100'],
            'receipt_footer' => ['nullable', 'string', 'max:255'],
            'custom_note' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024', 'dimensions:max_width=1600,max_height=1600', 'prohibited_if:remove_logo,1'],
            'remove_logo' => ['sometimes', 'boolean'],
        ]);

        $logo = $request->file('logo');
        $removeLogo = $request->boolean('remove_logo');

        DB::transaction(function () use ($validated, $logo, $removeLogo) {
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
            if ($logo !== null) {
                $image = $logo->get();
                DB::table('store_logos')->upsert([[
                    'id' => 1,
                    'mime' => $logo->getMimeType(),
                    'version' => hash('sha256', $image),
                    'encoded_image' => base64_encode($image),
                ]], ['id'], ['mime', 'version', 'encoded_image']);
            } elseif ($removeLogo) {
                DB::table('store_logos')->where('id', 1)->delete();
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan toko berhasil diperbarui.',
            'settings' => DB::table('settings')->pluck('value', 'key')->put('logo_version', $this->logoVersion()),
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
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9_-]+$/'],
            'type' => ['required', 'string', 'in:percent,fixed'],
            'value' => ['required', 'integer', 'min:1', 'max:10000000'],
            'min_subtotal' => ['required', 'integer', 'min:0', 'max:100000000'],
            'max_discount' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'active' => ['nullable', 'boolean'],
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
            'code' => $code,
            'type' => $data['type'],
            'value' => (int) $data['value'],
            'min_subtotal' => (int) $data['min_subtotal'],
            'max_discount' => (int) ($data['max_discount'] ?? 0),
            'active' => isset($data['active']) ? (int) (bool) $data['active'] : 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'id' => $id,
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
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9_-]+$/'],
            'type' => ['required', 'string', 'in:percent,fixed'],
            'value' => ['required', 'integer', 'min:1', 'max:10000000'],
            'min_subtotal' => ['required', 'integer', 'min:0', 'max:100000000'],
            'max_discount' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'active' => ['required', 'boolean'],
        ]);

        $code = strtoupper($this->cleanText($data['code'], 32));
        $duplicate = DB::table('promos')->where('code', $code)->where('id', '!=', $id)->exists();
        if ($duplicate) {
            throw ValidationException::withMessages([
                'code' => ["Kode promo {$code} sudah digunakan promo lain."],
            ]);
        }

        DB::table('promos')->where('id', $id)->update([
            'code' => $code,
            'type' => $data['type'],
            'value' => (int) $data['value'],
            'min_subtotal' => (int) $data['min_subtotal'],
            'max_discount' => (int) ($data['max_discount'] ?? 0),
            'active' => (int) (bool) $data['active'],
            'updated_at' => now(),
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

        $products = DB::table('products')
            ->where('active', 1)
            ->orderBy('id', 'asc')
            ->get();

        return response()->json($this->availableProducts(app(InventoryCalculator::class)->enrich($products)));
    }

    private function availableProducts(Collection $products): Collection
    {
        $activeIds = $products->keyBy('id');

        return $products->filter(fn (array $product): bool => $product['item_type'] !== 'paketan'
            || ($product['bundle_items'] !== [] && collect($product['bundle_items'])->every(function ($item) use ($activeIds): bool {
                $component = $activeIds->get($item['product_id'] ?? 0);

                return $component !== null && in_array($component['item_type'], ['satuan', 'perporsi'], true);
            })))->values();
    }

    public function getManagedProducts(): JsonResponse
    {

        return response()->json(app(InventoryCalculator::class)->enrich(DB::table('products')->orderBy('id')->get()));
    }

    public function updateProductStatus(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['active' => ['required', 'boolean']]);
        abort_unless(DB::table('products')->where('id', $id)->exists(), 404);
        DB::table('products')->where('id', $id)->update(['active' => (bool) $data['active'], 'updated_at' => now()]);

        return response()->json(['active' => (bool) $data['active']]);
    }

    public function getProductPhoto(int $id): Response
    {
        $photo = DB::table('product_photos')->where('product_id', $id)->first();
        abort_if($photo === null, 404);

        return response(base64_decode($photo->encoded_image, true), 200, [
            'Content-Type' => $photo->mime,
            'Cache-Control' => 'no-cache',
        ]);
    }

    private const PRODUCT_PHOTO_RULES = ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:max_width=2400,max_height=2400'];

    private function saveProductPhoto(int $id, UploadedFile $photo): void
    {
        $image = $photo->get();
        DB::table('product_photos')->upsert([[
            'product_id' => $id,
            'mime' => $photo->getMimeType(),
            'encoded_image' => base64_encode($image),
        ]], ['product_id'], ['mime', 'encoded_image']);
        DB::table('products')->where('id', $id)->update([
            'image_url' => "/api/products/{$id}/photo?v=".hash('sha256', $image),
        ]);
    }

    public function storeProduct(Request $request)
    {

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', 'string', 'in:'.implode(',', self::ALLOWED_CATEGORIES)],
            'item_type' => ['nullable', 'string', 'in:satuan,paketan,perporsi'],
            'bundle_items' => ['nullable', 'array'],
            'bundle_items.*.product_id' => ['required_with:bundle_items', 'integer', 'min:1'],
            'bundle_items.*.qty' => ['required_with:bundle_items', 'integer', 'min:1', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
            'price_m' => ['required', 'integer', 'min:0', 'max:10000000'],
            'price_l' => ['required_unless:item_type,perporsi', 'nullable', 'integer', 'min:0', 'max:10000000'],
            'stock' => ['required', 'integer', 'min:0', 'max:100000'],
            'max_stock' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'gradient' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6},#[0-9A-Fa-f]{6}$/'],
            'photo' => self::PRODUCT_PHOTO_RULES,
        ]);

        $actorName = $this->cleanText($request->user()?->name ?? 'Admin', 100);
        $name = $this->cleanName($data['name']);
        $this->assertUniqueMenuName($name);
        $itemType = $data['item_type'] ?? 'satuan';
        $description = $this->cleanText($data['description'] ?? '', 500);
        $gradient = $data['gradient'] ?? '#D8B493,#A67C58';
        $photo = $request->file('photo');
        $stock = (int) $data['stock'];
        $maxStock = max($stock, (int) ($data['max_stock'] ?? max(60, $stock)));

        $cleanBundles = [];
        if ($itemType === 'paketan' && ! empty($data['bundle_items'])) {
            foreach ($data['bundle_items'] as $bi) {
                $comp = DB::table('products')->where('id', (int) $bi['product_id'])->first();
                if ($comp) {
                    $cleanBundles[] = [
                        'product_id' => (int) $comp->id,
                        'name' => $comp->name,
                        'qty' => (int) $bi['qty'],
                    ];
                }
            }
        }

        $id = DB::transaction(function () use ($name, $data, $itemType, $cleanBundles, $description, $stock, $maxStock, $gradient, $photo, $actorName) {
            $productId = DB::table('products')->insertGetId([
                'name' => $name,
                'category' => $itemType === 'paketan' ? 'Bundling' : $data['category'],
                'item_type' => $itemType,
                'description' => $description,
                'bundle_items' => ! empty($cleanBundles) ? json_encode($cleanBundles, JSON_UNESCAPED_UNICODE) : null,
                'price_m' => (int) $data['price_m'],
                'price_l' => (int) ($itemType === 'perporsi' ? $data['price_m'] : $data['price_l']),
                'stock' => $stock,
                'max_stock' => $maxStock,
                'emoji' => $itemType === 'paketan' ? 'PAKET' : 'KOPI',
                'gradient' => $gradient,
                'active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('inventory_logs')->insert([
                'product_id' => $productId,
                'product_name' => $name,
                'type' => 'in',
                'qty' => $stock,
                'stock_after' => $stock,
                'note' => 'Stok awal penambahan menu baru',
                'reference' => 'NEW-ITEM',
                'actor_name' => $actorName,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($photo !== null) {
                $this->saveProductPhoto($productId, $photo);
            }

            return $productId;
        });

        return response()->json([
            'success' => true,
            'id' => $id,
            'message' => 'Menu baru berhasil ditambahkan.',
        ], 201);
    }

    public function updateProduct(Request $request, int $id)
    {

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', 'string', 'in:'.implode(',', self::ALLOWED_CATEGORIES)],
            'item_type' => ['nullable', 'string', 'in:satuan,paketan,perporsi'],
            'bundle_items' => ['nullable', 'array'],
            'bundle_items.*.product_id' => ['required_with:bundle_items', 'integer', 'min:1'],
            'bundle_items.*.qty' => ['required_with:bundle_items', 'integer', 'min:1', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
            'price_m' => ['required', 'integer', 'min:0', 'max:10000000'],
            'price_l' => ['required_unless:item_type,perporsi', 'nullable', 'integer', 'min:0', 'max:10000000'],
            'stock' => ['required_if:item_type,perporsi', 'integer', 'min:0', 'max:100000'],
            'max_stock' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'gradient' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6},#[0-9A-Fa-f]{6}$/'],
            'photo' => self::PRODUCT_PHOTO_RULES,
        ]);

        $product = DB::table('products')->where('id', $id)->first();
        if (! $product) {
            return response()->json(['message' => 'Produk tidak ditemukan.'], 404);
        }

        $itemType = $data['item_type'] ?? ($product->item_type ?? 'satuan');
        $this->assertUniqueMenuName($data['name'], $id);
        $cleanBundles = [];
        if ($itemType === 'paketan' && ! empty($data['bundle_items'])) {
            foreach ($data['bundle_items'] as $bi) {
                $comp = DB::table('products')->where('id', (int) $bi['product_id'])->first();
                if ($comp) {
                    $cleanBundles[] = [
                        'product_id' => (int) $comp->id,
                        'name' => $comp->name,
                        'qty' => (int) $bi['qty'],
                    ];
                }
            }
        }

        DB::transaction(function () use ($request, $id, $data, $itemType, $cleanBundles, $product) {
            DB::table('products')->where('id', $id)->update([
                'name' => $this->cleanName($data['name']),
                'category' => $itemType === 'paketan' ? 'Bundling' : $data['category'],
                'item_type' => $itemType,
                'description' => $this->cleanText($data['description'] ?? '', 500),
                'bundle_items' => $itemType === 'paketan' ? json_encode($cleanBundles, JSON_UNESCAPED_UNICODE) : null,
                'price_m' => (int) $data['price_m'],
                'price_l' => (int) ($itemType === 'perporsi' ? $data['price_m'] : $data['price_l']),
                'max_stock' => isset($data['max_stock']) ? max(1, (int) $data['max_stock']) : ($product->max_stock ?? 60),
                'gradient' => $data['gradient'] ?? $product->gradient,
                'updated_at' => now(),
            ]);
            if ($itemType === 'perporsi' && isset($data['stock'])) {
                $previousStock = (int) DB::table('products')->where('id', $id)->lockForUpdate()->value('stock');
                $newStock = (int) $data['stock'];
                DB::table('products')->where('id', $id)->update(['stock' => $newStock]);
                if ($newStock !== $previousStock) {
                    DB::table('inventory_logs')->insert([
                        'product_id' => $id, 'product_name' => $this->cleanName($data['name']),
                        'type' => $newStock > $previousStock ? 'in' : 'out',
                        'qty' => abs($newStock - $previousStock), 'stock_after' => $newStock,
                        'note' => 'Atur jumlah stok per porsi', 'reference' => 'SET-PORSI',
                        'actor_name' => $this->cleanText($request->user()?->name ?? 'Admin', 100),
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
            if ($request->hasFile('photo')) {
                $this->saveProductPhoto($id, $request->file('photo'));
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Data menu berhasil diperbarui.',
        ]);
    }

    // hapus menu permanen
    public function deleteProduct(int $id)
    {
        $product = DB::table('products')->where('id', $id)->first();
        if (! $product) {
            return response()->json(['message' => 'Produk tidak ditemukan.'], 404);
        }

        DB::transaction(function () use ($id) {
            DB::table('recipes')->where('product_id', $id)->delete();
            DB::table('products')->where('id', $id)->delete();
        });

        return response()->json([
            'success' => true,
            'message' => "Menu \"{$product->name}\" berhasil dihapus permanen.",
        ]);
    }

    public function getIngredients()
    {

        $productsById = DB::table('products')->where('active', 1)->get()->keyBy('id');
        $recipes = DB::table('recipes')->get();
        $usedByMap = [];

        foreach ($recipes as $rec) {
            $prod = $productsById[$rec->product_id] ?? null;
            if (! $prod || ($prod->item_type ?? 'satuan') === 'perporsi') {
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
                        'product_id' => (int) $prod->id,
                        'product_name' => $prod->name,
                        'amount_m' => $amtM,
                        'unit' => $ing['unit'] ?? 'ml',
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
                'id' => (int) $ing->id,
                'name' => $ing->name,
                'unit' => $ing->unit,
                'current_stock' => round($cur, 1),
                'max_stock' => round($max, 1),
                'stock_amount' => round($cur, 1),
                'max_amount' => round($max, 1),
                'stock_percent' => $pct,
                'is_locked' => $cur <= 0,
                'is_warning' => $cur > 0 && $pct <= 20,
                'used_by' => $usedByMap[$key] ?? [],
            ];
        });

        return response()->json(['ingredients' => $rows]);
    }

    public function restockIngredient(Request $request)
    {

        $rawAmt = $request->input('amount', $request->input('qty'));
        $request->merge(['amount' => $rawAmt]);

        $data = $request->validate([
            'ingredient_id' => ['nullable', 'integer', 'min:1', 'exists:ingredients_stock,id'],
            'name' => ['required', 'string', 'max:120'],
            'unit' => ['required', 'string', 'in:'.implode(',', self::ALLOWED_UNITS)],
            'amount' => ['required', 'numeric', 'min:0.1', 'max:1000000'],
            'mode' => ['nullable', 'string', 'in:add,set'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $name = $this->cleanName($data['name']);
        $qty = (float) $data['amount'];
        $mode = $data['mode'] ?? 'add';
        $actorName = $this->cleanText($request->user()?->name ?? 'Admin', 100);
        $note = $this->cleanText($data['note'] ?? '', 255) ?: ($mode === 'set' ? 'Atur stok penuh bahan baku (100%)' : 'Tambah stok bahan baku gudang');

        $existing = $this->namedRecord('ingredients_stock', $name);
        if ($existing && (int) ($data['ingredient_id'] ?? 0) !== (int) $existing->id) {
            throw ValidationException::withMessages([
                'name' => ['Bahan sudah ada. Pilih bahan yang sudah ada untuk restock.'],
            ]);
        }
        if (isset($data['ingredient_id']) && (! $existing || (int) $existing->id !== (int) $data['ingredient_id'])) {
            throw ValidationException::withMessages(['ingredient_id' => ['Bahan yang dipilih tidak sesuai dengan nama bahan.']]);
        }
        if ($existing && $data['unit'] !== $existing->unit) {
            throw ValidationException::withMessages(['unit' => ['Satuan bahan harus sama dengan bahan yang sudah terdaftar.']]);
        }

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
                'max_stock' => $newMax,
                'unit' => $data['unit'],
                'updated_at' => now(),
            ]);
            $ingId = (int) $existing->id;
        } else {
            $newStock = $qty;
            $ingId = DB::table('ingredients_stock')->insertGetId([
                'name' => $name,
                'unit' => $data['unit'],
                'current_stock' => $qty,
                'max_stock' => $qty,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('inventory_logs')->insert([
            'product_id' => $ingId,
            'product_name' => "Bahan: {$name} ({$data['unit']})",
            'type' => 'in',
            'qty' => (int) round($qty),
            'stock_after' => (int) round($newStock),
            'note' => $note,
            'reference' => $mode === 'set' ? 'SET-100%' : 'RESTOCK-BAHAN',
            'actor_name' => $actorName,
            'created_at' => now(),
            'updated_at' => now(),
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

        $rows = DB::table('recipes')->whereIn('product_id',
            DB::table('products')->where('item_type', '!=', 'perporsi')->select('id')
        )->orderBy('product_id', 'asc')->get();
        $recipes = $rows->map(function ($row) {
            $decoded = json_decode($row->ingredients, true);

            return [
                'id' => (int) $row->id,
                'product_id' => (int) $row->product_id,
                'prep_time' => $row->prep_time,
                'ingredients' => is_array($decoded) ? $decoded : [],
                'steps' => $row->steps ?? '',
                'barista_note' => $row->barista_note ?? '',
                'updated_at' => $row->updated_at,
            ];
        });

        return response()->json(['recipes' => $recipes]);
    }

    public function saveRecipe(Request $request)
    {

        $data = $request->validate([
            'recipe_id' => ['nullable', 'integer', 'min:1'],
            'product_id' => ['required', 'integer', 'min:1'],
            'prep_time' => ['nullable', 'string', 'max:50'],
            'ingredients' => ['required', 'array', 'min:1', 'max:40'],
            'ingredients.*.name' => ['required', 'string', 'max:120'],
            'ingredients.*.amount_m' => ['required', 'string', 'max:30'],
            'ingredients.*.amount_l' => ['required', 'string', 'max:30'],
            'ingredients.*.unit' => ['required', 'string', 'in:'.implode(',', self::ALLOWED_UNITS)],
            'steps' => ['nullable', 'string', 'max:2000'],
            'barista_note' => ['nullable', 'string', 'max:255'],
        ]);

        $productId = (int) $data['product_id'];
        $product = DB::table('products')->where('id', $productId)->where('active', 1)->first();

        if (! $product) {
            return response()->json(['message' => 'Produk tidak ditemukan.'], 404);
        }

        if (($product->item_type ?? 'satuan') === 'perporsi') {
            throw ValidationException::withMessages(['product_id' => ['Menu per porsi tidak memakai bahan atau resep.']]);
        }
        $existingRecipe = DB::table('recipes')->where('product_id', $productId)->first();
        if ($existingRecipe && (int) ($data['recipe_id'] ?? 0) !== (int) $existingRecipe->id) {
            throw ValidationException::withMessages(['product_id' => ['Resep sudah ada. Gunakan Ubah Resep untuk memperbaruinya.']]);
        }
        if (! $existingRecipe && isset($data['recipe_id'])) {
            throw ValidationException::withMessages(['recipe_id' => ['Resep yang akan diubah tidak ditemukan untuk menu ini.']]);
        }

        $cleanIngredients = [];
        $ingredientNames = [];
        foreach ($data['ingredients'] as $ing) {
            $name = $this->cleanName($ing['name']);
            if ($name === '') {
                continue;
            }
            $key = mb_strtolower($name);
            if (isset($ingredientNames[$key])) {
                throw ValidationException::withMessages(['ingredients' => ['Bahan sudah ada dalam resep. Gunakan satu baris untuk setiap bahan.']]);
            }
            $ingredientNames[$key] = true;
            $existingIngredient = $this->namedRecord('ingredients_stock', $name);
            if ($existingIngredient && $existingIngredient->unit !== $ing['unit']) {
                throw ValidationException::withMessages(['ingredients' => ['Satuan bahan harus sama dengan bahan yang sudah terdaftar.']]);
            }
            $cleanIngredients[] = [
                'name' => $existingIngredient->name ?? $name,
                'amount_m' => $this->cleanText($ing['amount_m'], 30),
                'amount_l' => $this->cleanText($ing['amount_l'], 30),
                'unit' => $ing['unit'],
            ];

        }

        if (empty($cleanIngredients)) {
            throw ValidationException::withMessages([
                'ingredients' => ['Minimal satu bahan baku wajib diisi dengan benar.'],
            ]);
        }

        $prepTime = $this->cleanText($data['prep_time'] ?? '2 Menit', 50) ?: '2 Menit';
        $steps = $this->cleanText($data['steps'] ?? '', 2000);
        $baristaNote = $this->cleanText($data['barista_note'] ?? '', 255);

        DB::transaction(function () use ($productId, $data, $cleanIngredients, $prepTime, $steps, $baristaNote) {
            $product = DB::table('products')->where('id', $productId)->lockForUpdate()->first();
            if (! $product || $product->item_type === 'perporsi') {
                throw ValidationException::withMessages(['product_id' => ['Menu per porsi tidak memakai bahan atau resep.']]);
            }
            $recipe = DB::table('recipes')->where('product_id', $productId)->first();
            if ($recipe && (int) ($data['recipe_id'] ?? 0) !== (int) $recipe->id) {
                throw ValidationException::withMessages(['product_id' => ['Resep sudah ada. Gunakan Ubah Resep untuk memperbaruinya.']]);
            }
            foreach ($cleanIngredients as $ingredient) {
                if (! $this->namedRecord('ingredients_stock', $ingredient['name'])) {
                    DB::table('ingredients_stock')->insert([
                        'name' => $ingredient['name'], 'unit' => $ingredient['unit'],
                        'current_stock' => 0, 'max_stock' => 1, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
            $values = [
                'prep_time' => $prepTime,
                'ingredients' => json_encode($cleanIngredients, JSON_UNESCAPED_UNICODE),
                'steps' => $steps,
                'barista_note' => $baristaNote,
                'updated_at' => now(),
            ];
            if ($recipe) {
                DB::table('recipes')->where('id', $recipe->id)->update($values);
            } else {
                DB::table('recipes')->insert($values + ['product_id' => $productId, 'created_at' => now()]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => "Resep untuk {$product->name} berhasil disimpan.",
        ]);
    }

    public function deleteRecipe(int $productId)
    {

        DB::table('recipes')->where('product_id', $productId)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Resep berhasil dihapus.',
        ]);
    }

    public function restockProduct(Request $request, int $id)
    {

        $data = $request->validate([
            'qty' => ['required', 'integer', 'min:1', 'max:50000'],
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
                'stock' => $newStock,
                'max_stock' => $newMaxStock,
                'updated_at' => now(),
            ]);

            DB::table('inventory_logs')->insert([
                'product_id' => $id,
                'product_name' => $product->name,
                'type' => 'in',
                'qty' => $qty,
                'stock_after' => $newStock,
                'note' => $note,
                'reference' => 'RESTOCK',
                'actor_name' => $actorName,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $newStock;
        });

        if ($result === null) {
            return response()->json(['message' => 'Produk tidak ditemukan.'], 404);
        }

        return response()->json([
            'success' => true,
            'new_stock' => $result,
            'message' => 'Stok berhasil ditambahkan.',
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
        if (DB::table('products')->where('id', $productId)->value('item_type') === 'perporsi') {
            return;
        }
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
            $amountPerCup = $size === 'L' ? (float) ($ing['amount_l'] ?? $ing['amount_m'] ?? 0) : (float) ($ing['amount_m'] ?? 0);
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
                    'updated_at' => now(),
                ]);

                DB::table('inventory_logs')->insert([
                    'product_id' => (int) $stockRow->id,
                    'product_name' => "Bahan: {$stockRow->name} ({$stockRow->unit})",
                    'type' => 'out',
                    'qty' => (int) round($totalUse),
                    'stock_after' => (int) round($newIngStock),
                    'note' => "Pemakaian {$qty}x {$prodName} ({$size}) • {$orderCode}",
                    'reference' => $orderCode ?: 'ORDER',
                    'actor_name' => $actorName,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function storeOrder(Request $request)
    {

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.id' => ['required', 'integer', 'min:1'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:500'],
            'items.*.size' => ['nullable', 'string', 'in:M,L'],
            'items.*.sugar' => ['nullable', 'string', 'in:'.implode(',', self::ALLOWED_SUGAR)],
            'items.*.note' => ['nullable', 'string', 'max:200'],
            'promo_code' => ['nullable', 'string', 'max:50'],
            'customer_name' => ['nullable', 'string', 'max:100'],
            'order_type' => ['nullable', 'string', 'in:dine_in,takeaway'],
            'table_number' => ['nullable', 'string', 'max:30'],
            'payment_method' => ['required', 'string', 'in:cash,qris'],
            'cash_received' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
        ]);

        $user = $request->user();
        $cashierId = (int) ($user?->id ?? $request->input('cashier_id', 2));
        $cashierName = $this->cleanText($user?->name ?? $request->input('cashier_name', 'Kasir Versi Kopi'), 100);
        $customerName = $this->cleanText($data['customer_name'] ?? '', 100) ?: null;
        $orderType = $data['order_type'] ?? 'dine_in';
        $tableNumber = $this->cleanText($data['table_number'] ?? '', 30) ?: null;

        return DB::transaction(function () use ($data, $cashierId, $cashierName, $customerName, $orderType, $tableNumber) {
            $wib = now()->setTimezone('Asia/Jakarta');
            $prefix = 'KS-'.$wib->format('Ymd');
            $escapedPrefix = addcslashes($prefix, '%_\\');
            $count = DB::table('orders')
                ->where('code', 'like', "{$escapedPrefix}-%")
                ->lockForUpdate()
                ->count();
            $orderCode = $prefix.'-'.str_pad((string) ($count + 1), 3, '0', STR_PAD_LEFT);

            $enrichedMap = $this->availableProducts(app(InventoryCalculator::class)->enrich(
                DB::table('products')->where('active', 1)->lockForUpdate()->get()
            ))->keyBy('id');

            $subtotal = 0;
            $sanitizedItems = [];
            $stockDeductions = [];
            $recipeUsageList = [];

            foreach ($data['items'] as $reqItem) {
                $prodId = (int) $reqItem['id'];
                $qty = (int) $reqItem['qty'];
                $size = $reqItem['size'] ?? 'M';
                $sugar = array_key_exists('sugar', $reqItem) ? $reqItem['sugar'] : null;
                $note = $this->cleanText($reqItem['note'] ?? '', 200);

                $prod = $enrichedMap[$prodId] ?? null;

                if (! $prod) {
                    throw ValidationException::withMessages([
                        'items' => ["Produk dengan ID {$prodId} tidak ditemukan atau sudah tidak aktif."],
                    ]);
                }

                if ($prod['item_type'] === 'perporsi') {
                    $size = 'M';
                    $sugar = null;
                }

                // cek kunci stok
                if ($size === 'L' ? $prod['is_locked_l'] : $prod['is_locked_m']) {
                    throw ValidationException::withMessages([
                        'items' => ["Menu {$prod['name']} ukuran {$size} terkunci karena bahan tidak cukup untuk satu porsi."],
                    ]);
                }

                $alreadyRequested = $stockDeductions[$prodId]['qty'] ?? 0;
                $usableStock = $size === 'L' ? (int) $prod['stock_l'] : (int) $prod['stock_m'];
                if ($qty > $usableStock) {
                    throw ValidationException::withMessages([
                        'items' => ["Stok {$prod['name']} tidak mencukupi (tersedia {$usableStock} porsi yang dapat dipesan)."],
                    ]);
                }

                $unitPrice = $size === 'L' ? (int) $prod['price_l'] : (int) $prod['price_m'];
                $subtotal += $unitPrice * $qty;

                $sanitizedItems[] = [
                    'id' => (int) $prod['id'],
                    'name' => $prod['name'],
                    'category' => $prod['category'],
                    'item_type' => $prod['item_type'],
                    'size' => $size,
                    'sugar' => $sugar,
                    'note' => $note,
                    'unit_price' => $unitPrice,
                    'qty' => $qty,
                    'stock_movements' => [],
                ];

                $stockDeductions[$prodId] = [
                    'product' => $prod,
                    'qty' => $alreadyRequested + $qty,
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
                                'qty' => $prevCompQty + $compQty,
                            ];
                            if ($enrichedMap[$compId]['item_type'] !== 'perporsi') {
                                $recipeUsageList[] = [
                                    'product_id' => $compId, 'size' => $size, 'qty' => $compQty,
                                ];
                            }
                        }
                    }
                } elseif ($prod['item_type'] !== 'perporsi') {
                    $recipeUsageList[] = [
                        'product_id' => $prodId, 'size' => $size, 'qty' => $qty,
                    ];
                }
            }

            foreach ($stockDeductions as $deduction) {
                if ($deduction['product']['item_type'] !== 'paketan' && empty($deduction['product']['recipe_summary']) && $deduction['qty'] > $deduction['product']['raw_stock']) {
                    throw ValidationException::withMessages([
                        'items' => ["Stok {$deduction['product']['name']} tidak cukup untuk gabungan pesanan ini."],
                    ]);
                }
            }

            // validasi total gabungan bahan baku dalam 1 keranjang
            $neededIngredients = [];
            foreach ($recipeUsageList as $usage) {
                $recipe = DB::table('recipes')->where('product_id', $usage['product_id'])->first();
                if (! $recipe) {
                    continue;
                }
                $ingList = json_decode($recipe->ingredients, true);
                if (! is_array($ingList)) {
                    continue;
                }
                foreach ($ingList as $ing) {
                    $name = trim($ing['name'] ?? '');
                    $amtPerCup = $usage['size'] === 'L' ? (float) ($ing['amount_l'] ?? $ing['amount_m'] ?? 0) : (float) ($ing['amount_m'] ?? 0);
                    $useAmt = $amtPerCup * $usage['qty'];
                    if ($name === '' || $useAmt <= 0) {
                        continue;
                    }
                    $key = mb_strtolower($name);
                    if (! isset($neededIngredients[$key])) {
                        $neededIngredients[$key] = ['name' => $name, 'amount' => 0.0];
                    }
                    $neededIngredients[$key]['amount'] += $useAmt;
                }
            }

            foreach ($neededIngredients as $key => $need) {
                $stockRow = DB::table('ingredients_stock')
                    ->whereRaw('LOWER(name) = ?', [$key])
                    ->lockForUpdate()
                    ->first();

                if (! $stockRow) {
                    throw ValidationException::withMessages([
                        'items' => ["Stok bahan {$need['name']} tidak tersedia di gudang."],
                    ]);
                }
                if ($need['amount'] > (float) $stockRow->current_stock) {
                    $avail = round((float) $stockRow->current_stock, 1);
                    $req = round($need['amount'], 1);
                    throw ValidationException::withMessages([
                        'items' => ["Stok bahan {$stockRow->name} tidak cukup untuk gabungan pesanan ini (butuh {$req} {$stockRow->unit}, tersisa {$avail} {$stockRow->unit})."],
                    ]);
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

                if (! $promo) {
                    throw ValidationException::withMessages([
                        'promo_code' => ["Kode promo \"{$code}\" tidak ditemukan atau sudah tidak aktif."],
                    ]);
                }

                if ($subtotal < (int) $promo->min_subtotal) {
                    $minFmt = number_format((int) $promo->min_subtotal, 0, ',', '.');
                    throw ValidationException::withMessages([
                        'promo_code' => ["Minimal belanja untuk promo {$promo->code} adalah Rp {$minFmt}."],
                    ]);
                }

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

            foreach ($sanitizedItems as &$item) {
                $product = $enrichedMap[$item['id']];
                $components = $product['item_type'] === 'paketan' ? $product['bundle_items'] : [['product_id' => $item['id'], 'qty' => 1]];
                foreach ($components as $component) {
                    $componentProduct = $enrichedMap[$component['product_id']];
                    if ($componentProduct['item_type'] === 'perporsi' || empty($componentProduct['recipe_summary'])) {
                        $item['stock_movements'][] = [
                            'product_id' => (int) $component['product_id'],
                            'qty' => max(1, (int) ($component['qty'] ?? 1)) * $item['qty'],
                        ];
                    }
                }
            }
            unset($item);

            $orderId = DB::table('orders')->insertGetId([
                'code' => $orderCode,
                'cashier_id' => $cashierId,
                'cashier_name' => $cashierName,
                'customer_name' => $customerName,
                'order_type' => $orderType,
                'table_number' => $tableNumber,
                'items' => json_encode($sanitizedItems, JSON_UNESCAPED_UNICODE),
                'subtotal' => $subtotal,
                'discount' => $discount,
                'promo_code' => $appliedPromoCode,
                'tax' => $tax,
                'service' => $service,
                'total' => $total,
                'payment_method' => $paymentMethod,
                'cash_received' => $cashReceived,
                'change_amount' => $changeAmount,
                'status' => 'paid',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($stockDeductions as $prodId => $deduction) {
                $prod = $deduction['product'];
                $deductQty = $deduction['qty'];
                if ($prod['item_type'] === 'paketan') {
                    continue;
                }
                $newStock = max(0, (int) $prod['raw_stock'] - $deductQty);

                DB::table('products')->where('id', $prodId)->update([
                    'stock' => $newStock,
                    'updated_at' => now(),
                ]);
                if ($prod['item_type'] === 'perporsi') {
                    DB::table('inventory_logs')->insert([
                        'product_id' => $prodId, 'product_name' => $prod['name'], 'type' => 'out',
                        'qty' => $deductQty, 'stock_after' => $newStock, 'note' => 'Penjualan per porsi',
                        'reference' => $orderCode, 'actor_name' => $cashierName, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }

            // potong bahan gudang
            foreach ($recipeUsageList as $usage) {
                $this->deductRawMaterialsForProduct($usage['product_id'], $usage['size'], $usage['qty'], $orderCode, $cashierName);
            }

            return response()->json([
                'success' => true,
                'order_code' => $orderCode,
                'order_id' => $orderId,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'service' => $service,
                'total' => $total,
                'cash_received' => $cashReceived,
                'change_amount' => $changeAmount,
                'items' => $sanitizedItems,
            ], 201);
        });
    }

    // hapus bahan baku
    public function deleteIngredient(int $id)
    {

        $deleted = DB::table('ingredients_stock')->where('id', $id)->delete();
        if (! $deleted) {
            return response()->json(['message' => 'Bahan baku tidak ditemukan.'], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Bahan baku berhasil dihapus dari gudang.',
        ]);
    }

    // batalkan transaksi dan kembalikan stok
    public function voidOrder(Request $request, int $id)
    {
        $actorName = $this->cleanText($request->user()?->name ?? 'Admin', 100);

        return DB::transaction(function () use ($id, $actorName) {
            $order = DB::table('orders')->where('id', $id)->lockForUpdate()->first();
            if (! $order) {
                return response()->json(['message' => 'Transaksi tidak ditemukan.'], 404);
            }
            if (($order->status ?? 'paid') === 'void') {
                return response()->json(['message' => 'Transaksi ini sudah dibatalkan sebelumnya.'], 422);
            }

            $items = json_decode($order->items ?? '[]', true);
            if (! is_array($items)) {
                $items = [];
            }

            foreach ($items as $item) {
                foreach ($item['stock_movements'] ?? [] as $movement) {
                    $stockProduct = DB::table('products')->where('id', (int) $movement['product_id'])->lockForUpdate()->first();
                    if ($stockProduct) {
                        $restored = (int) $stockProduct->stock + (int) $movement['qty'];
                        DB::table('products')->where('id', $stockProduct->id)->update([
                            'stock' => $restored, 'max_stock' => max($restored, (int) $stockProduct->max_stock), 'updated_at' => now(),
                        ]);
                        DB::table('inventory_logs')->insert([
                            'product_id' => $stockProduct->id, 'product_name' => $stockProduct->name, 'type' => 'in',
                            'qty' => (int) $movement['qty'], 'stock_after' => $restored,
                            'note' => 'Void penjualan stok porsi', 'reference' => $order->code, 'actor_name' => $actorName,
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                }
                $prodId = (int) ($item['id'] ?? 0);
                $qty = max(1, (int) ($item['qty'] ?? 1));
                $size = ($item['size'] ?? 'M') === 'L' ? 'L' : 'M';
                $prod = DB::table('products')->where('id', $prodId)->first();

                if ($prod && ($prod->item_type ?? 'satuan') === 'paketan' && ! empty($prod->bundle_items)) {
                    $bundleItems = json_decode($prod->bundle_items, true) ?: [];
                    foreach ($bundleItems as $bItem) {
                        $compId = (int) ($bItem['product_id'] ?? 0);
                        $compQty = max(1, (int) ($bItem['qty'] ?? 1)) * $qty;
                        $this->restoreRawMaterialsForProduct($compId, $size, $compQty, $order->code, $actorName);
                    }
                } elseif ($prodId > 0) {
                    $this->restoreRawMaterialsForProduct($prodId, $size, $qty, $order->code, $actorName);
                }
            }

            DB::table('orders')->where('id', $id)->update([
                'status' => 'void',
                'updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Transaksi {$order->code} dibatalkan dan stok bahan baku telah dikembalikan.",
            ]);
        });
    }

    // kembalikan bahan baku gudang
    private function restoreRawMaterialsForProduct(int $productId, string $size, int $qty, string $orderCode, string $actorName): void
    {
        if (DB::table('products')->where('id', $productId)->value('item_type') === 'perporsi') {
            return;
        }
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
            $amountPerCup = $size === 'L' ? (float) ($ing['amount_l'] ?? $ing['amount_m'] ?? 0) : (float) ($ing['amount_m'] ?? 0);
            $totalRestore = $amountPerCup * $qty;
            if ($name === '' || $totalRestore <= 0) {
                continue;
            }

            $stockRow = DB::table('ingredients_stock')
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->lockForUpdate()
                ->first();

            if ($stockRow) {
                $newStock = (float) $stockRow->current_stock + $totalRestore;
                $newMax = max((float) $stockRow->max_stock, $newStock);
                DB::table('ingredients_stock')->where('id', $stockRow->id)->update([
                    'current_stock' => $newStock,
                    'max_stock' => $newMax,
                    'updated_at' => now(),
                ]);

                DB::table('inventory_logs')->insert([
                    'product_id' => (int) $stockRow->id,
                    'product_name' => "Bahan: {$stockRow->name} ({$stockRow->unit})",
                    'type' => 'in',
                    'qty' => (int) round($totalRestore),
                    'stock_after' => (int) round($newStock),
                    'note' => "Void {$qty}x {$prodName} ({$size}) • {$orderCode}",
                    'reference' => $orderCode ?: 'VOID',
                    'actor_name' => $actorName,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
