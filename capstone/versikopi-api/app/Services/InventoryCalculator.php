<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventoryCalculator
{
    // hitung stok bahan bersama
    public function enrich(Collection $products): Collection
    {

        $recipesByProdId = DB::table('recipes')->get()->keyBy('product_id');
        $ingredientsMap = DB::table('ingredients_stock')->get()->keyBy(function ($item) {
            return mb_strtolower(trim($item->name));
        });

        // hitung porsi dari kebutuhan bahan
        $calcFromIngredientNeeds = function (array $needsMap, string $size, int $fallbackStock, int $fallbackMaxStock) use ($ingredientsMap): array {
            $fallback = [
                'stock' => max(0, $fallbackStock),
                'max_stock' => max(1, $fallbackStock, $fallbackMaxStock),
                'stock_percent' => (int) round(max(0, $fallbackStock) / max(1, $fallbackStock, $fallbackMaxStock) * 100),
                'limiting_reason' => null,
            ];
            if (empty($needsMap)) {
                return $fallback;
            }

            $minPortions = null;
            $maxPortionsForLimiting = null;
            $minPercent = null;
            $limitingName = null;

            foreach ($needsMap as $key => $info) {
                $need = (float) ($size === 'L' ? ($info['need_l'] ?? $info['need'] ?? 0) : ($info['need'] ?? 0));
                if ($need <= 0) {
                    continue;
                }
                if (! isset($ingredientsMap[$key])) {
                    return [
                        'stock' => 0,
                        'max_stock' => 1,
                        'stock_percent' => 0,
                        'limiting_reason' => "{$info['label']} tidak tersedia di gudang",
                    ];
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
                    $limitingName = "{$stockRow->name} (sisa ".round($curAmt, 1)." {$stockRow->unit})";
                }
                if ($minPercent === null || $ingPct < $minPercent) {
                    $minPercent = $ingPct;
                }
            }

            if ($minPortions === null) {
                return $fallback;
            }

            return [
                'stock' => $minPortions,
                'max_stock' => $maxPortionsForLimiting ?? max(20, $minPortions),
                'stock_percent' => (int) round($minPercent ?? 100),
                'limiting_reason' => $limitingName,
            ];
        };

        $productsById = $products->keyBy('id');

        return collect($products)->map(function ($prod) use ($productsById, $recipesByProdId, $calcFromIngredientNeeds) {
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
            $portionItems = [];

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
                            if ($key !== '' && ($needM > 0 || $needL > 0)) {
                                $needsMap[$key] = [
                                    'need' => ($needsMap[$key]['need'] ?? 0) + $needM,
                                    'need_l' => ($needsMap[$key]['need_l'] ?? 0) + $needL,
                                    'label' => $name,
                                    'unit' => $unit,
                                ];
                                $recipeSummary[] = [
                                    'name' => $name,
                                    'amount_m' => $needM,
                                    'amount_l' => $needL,
                                    'unit' => $unit,
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
                    $component = $productsById->get($compId);
                    if ($component && (($component->item_type ?? 'satuan') === 'perporsi' || ! isset($recipesByProdId[$compId]))) {
                        $portionItems[$compId] = [
                            'product_id' => $compId,
                            'qty' => ($portionItems[$compId]['qty'] ?? 0) + $compQty,
                        ];

                        continue;
                    }
                    if (isset($recipesByProdId[$compId])) {
                        $ingList = json_decode($recipesByProdId[$compId]->ingredients, true);
                        if (is_array($ingList)) {
                            foreach ($ingList as $ing) {
                                $name = trim($ing['name'] ?? '');
                                $key = mb_strtolower($name);
                                $needM = (float) ($ing['amount_m'] ?? 0) * $compQty;
                                $needL = (float) ($ing['amount_l'] ?? ($ing['amount_m'] ?? 0)) * $compQty;
                                $unit = $ing['unit'] ?? 'ml';
                                if ($key !== '' && ($needM > 0 || $needL > 0)) {
                                    $needsMap[$key] = [
                                        'need' => ($needsMap[$key]['need'] ?? 0) + $needM,
                                        'need_l' => ($needsMap[$key]['need_l'] ?? 0) + $needL,
                                        'label' => $name,
                                        'unit' => $unit,
                                    ];
                                }
                            }
                        }
                    }
                }
                foreach ($needsMap as $info) {
                    $recipeSummary[] = [
                        'name' => $info['label'],
                        'amount_m' => $info['need'],
                        'amount_l' => $info['need_l'] ?? $info['need'],
                        'unit' => $info['unit'],
                    ];
                }
            }

            $fallbackStock = (int) ($prod->stock ?? 0);
            $fallbackMaxStock = (int) ($prod->max_stock ?? $fallbackStock);
            $calc = $calcFromIngredientNeeds($needsMap, 'M', $fallbackStock, $fallbackMaxStock);
            $calcL = $calcFromIngredientNeeds($needsMap, 'L', $fallbackStock, $fallbackMaxStock);
            if ($portionItems !== []) {
                if ($needsMap === []) {
                    $calc['stock'] = $calcL['stock'] = PHP_INT_MAX;
                    $calc['stock_percent'] = $calcL['stock_percent'] = 100;
                }
                foreach ($portionItems as $portionItem) {
                    $component = $productsById->get($portionItem['product_id']);
                    $count = max(0, (int) $component->stock);
                    $capacity = max(1, $count, (int) ($component->max_stock ?? $count));
                    $available = (int) floor($count / $portionItem['qty']);
                    $maximum = max(1, (int) floor($capacity / $portionItem['qty']));
                    foreach (['M', 'L'] as $size) {
                        $current = $size === 'M' ? $calc : $calcL;
                        if ($available < $current['stock']) {
                            $current['stock'] = $available;
                            $current['max_stock'] = $maximum;
                            $current['limiting_reason'] = "{$component->name} (sisa {$count} porsi)";
                        }
                        $current['stock_percent'] = min($current['stock_percent'], (int) round($count / $capacity * 100));
                        if ($size === 'M') {
                            $calc = $current;
                        } else {
                            $calcL = $current;
                        }
                    }
                }
            }
            $effectiveStock = (int) $calc['stock'];
            $maxStock = (int) $calc['max_stock'];
            $stockPercent = (int) $calc['stock_percent'];
            $limitingReason = $calc['limiting_reason'];

            // batas kunci stok
            $isLocked = $effectiveStock < 1 && (int) $calcL['stock'] < 1;

            // batas peringatan stok
            $isWarning = ! $isLocked && $stockPercent <= 20;

            $prodObj = (array) $prod;
            $prodObj['item_type'] = $itemType;
            $prodObj['bundle_items'] = $bundleItems;
            $prodObj['recipe_summary'] = $recipeSummary;
            $prodObj['portion_items'] = array_values($portionItems);
            if ($itemType === 'perporsi') {
                $prodObj['price_l'] = (int) $prod->price_m;
            }
            $prodObj['stock'] = $effectiveStock;
            $prodObj['stock_m'] = $effectiveStock;
            $prodObj['stock_l'] = (int) $calcL['stock'];
            $prodObj['is_locked_m'] = $effectiveStock < 1;
            $prodObj['is_locked_l'] = (int) $calcL['stock'] < 1;
            $prodObj['raw_stock'] = $effectiveStock;
            $prodObj['max_stock'] = $maxStock;
            $prodObj['stock_percent'] = $stockPercent;
            $prodObj['is_locked'] = $isLocked;
            $prodObj['is_warning'] = $isWarning;
            $prodObj['limiting_reason'] = $limitingReason ? "Bahan {$limitingReason}" : null;

            return $prodObj;
        })->values();
    }
}
