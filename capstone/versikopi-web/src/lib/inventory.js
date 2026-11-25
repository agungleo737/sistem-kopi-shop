export const normalizeName = (name) => (name || '').trim().replace(/\s+/g, ' ').toLowerCase();
export const findDuplicateName = (rows, name, ownId = null) => rows.find((row) =>
  normalizeName(name) && normalizeName(row.name) === normalizeName(name) &&
  (ownId === null || String(row.id) !== String(ownId))
);
export function hasDuplicateNames(rows) {
  const names = rows.map((row) => normalizeName(row.name)).filter(Boolean);
  return new Set(names).size !== names.length;
}
export function apiErrorMessage(error, fallback) {
  const data = error.response?.data;
  const first = Object.values(data?.errors || {}).flat()[0];
  return first || data?.message || fallback;
}
const ingredientKey = normalizeName;
const nonNegative = (value) => Number.isFinite(Number(value)) ? Math.max(0, Number(value)) : 0;
const portions = (stock, need) => Math.floor((nonNegative(stock) + 1e-9) / need);
const summaryFor = (product) => product?.item_type !== 'perporsi' && Array.isArray(product?.recipe_summary) ? product.recipe_summary : [];

function portionNeeds(product) {
  const needs = new Map();
  if (product.item_type === 'perporsi') needs.set(Number(product.id), 1);
  if (product.item_type === 'paketan') {
    for (const row of product.portion_items || []) {
      const id = Number(row.product_id);
      needs.set(id, (needs.get(id) || 0) + nonNegative(row.qty));
    }
  }
  return needs;
}

// API bundle summaries already combine component quantities. Sum repeated
// ingredient names too, so one stock pool cannot be spent twice per portion.
function recipeNeeds(product, size) {
  const needs = new Map();
  for (const row of summaryFor(product)) {
    const need = nonNegative(size === 'L' ? row.amount_l ?? row.amount_m : row.amount_m);
    if (need > 0) {
      const key = ingredientKey(row.name);
      needs.set(key, (needs.get(key) || 0) + need);
    }
  }
  return needs;
}

export function getOrderableForProduct(product, liveIngredients, size = 'M', products = null) {
  if (!product) return 0;
  if (product.item_type === 'perporsi') return Math.floor(nonNegative(product.stock_m ?? product.stock));
  const componentNeeds = portionNeeds(product);
  if (summaryFor(product).length === 0 && componentNeeds.size === 0) {
    return Math.floor(nonNegative(size === 'L' ? product.stock_l ?? product.stock : product.stock_m ?? product.stock));
  }
  const needs = recipeNeeds(product, size);
  if (needs.size === 0 && componentNeeds.size === 0) return 0;
  let available = Infinity;
  if (componentNeeds.size > 0) {
    if (!products) return 0;
    for (const [id, qty] of componentNeeds) {
      const component = products.find((row) => Number(row.id) === id && row.item_type === 'perporsi' && Number(row.active ?? 1) === 1);
      if (!component || qty <= 0) return 0;
      available = Math.min(available, portions(component.stock_m ?? component.stock, qty));
    }
  }
  for (const [key, need] of needs) {
    const ingredient = liveIngredients.find((row) => ingredientKey(row.name) === key);
    if (!ingredient) return 0;
    available = Math.min(available, portions(ingredient.current_stock ?? ingredient.stock_amount, need));
  }
  return available;
}

export function calculateInventory(products, ingredients, cart = []) {
  const ingMap = new Map(ingredients.map((ingredient) => {
    const current = nonNegative(ingredient.current_stock ?? ingredient.stock_amount);
    return [ingredientKey(ingredient.name), {
      ...ingredient,
      current_stock: current,
      max_stock: nonNegative(ingredient.max_stock ?? ingredient.max_amount ?? current),
    }];
  }));
  const usedProducts = new Map();
  for (const item of cart) {
    const product = products.find((row) => Number(row.id) === Number(item.id));
    if (!product) continue;
    const qty = nonNegative(item.qty);
    usedProducts.set(Number(product.id), (usedProducts.get(Number(product.id)) || 0) + qty);
    if (product.item_type === 'paketan') for (const [id, count] of portionNeeds(product)) {
      usedProducts.set(id, (usedProducts.get(id) || 0) + count * qty);
    }
    for (const [key, need] of recipeNeeds(product, item.size)) {
      const ingredient = ingMap.get(key);
      if (ingredient) ingredient.current_stock = Math.max(0, ingredient.current_stock - need * qty);
    }
  }
  const liveIngredients = [...ingMap.values()].map((ingredient) => {
    const pct = ingredient.max_stock > 0 ? ingredient.current_stock / ingredient.max_stock * 100 : 0;
    return {
      ...ingredient,
      stock_amount: ingredient.current_stock,
      max_amount: ingredient.max_stock,
      stock_percent: Math.round(pct),
      is_locked: ingredient.current_stock <= 0,
      is_warning: ingredient.current_stock > 0 && pct <= 20,
    };
  });
  const remainingProducts = products.map((product) => product.item_type === 'perporsi' ? {
    ...product,
    recipe_summary: [],
    stock: Math.max(0, Math.floor(nonNegative(product.stock_m ?? product.stock)) - (usedProducts.get(Number(product.id)) || 0)),
    stock_m: Math.max(0, Math.floor(nonNegative(product.stock_m ?? product.stock)) - (usedProducts.get(Number(product.id)) || 0)),
  } : product);
  const liveProducts = products.map((product) => {
    const hasRecipe = summaryFor(product).length > 0;
    const hasComponents = portionNeeds(product).size > 0;
    const used = usedProducts.get(Number(product.id)) || 0;
    const stockM = product.item_type === 'perporsi' ? remainingProducts.find((p) => p.id === product.id).stock
      : hasRecipe || hasComponents ? getOrderableForProduct(product, liveIngredients, 'M', remainingProducts)
      : Math.max(0, Math.floor(nonNegative(product.stock_m ?? product.stock)) - used);
    const stockL = product.item_type === 'perporsi' ? stockM
      : hasRecipe || hasComponents ? getOrderableForProduct(product, liveIngredients, 'L', remainingProducts)
      : Math.max(0, Math.floor(nonNegative(product.stock_l ?? product.stock)) - used);
    const required = new Set([...recipeNeeds(product, 'M').keys(), ...recipeNeeds(product, 'L').keys()]);
    let pct;
    let reason = product.limiting_reason;
    if (hasRecipe) {
      pct = 100;
      for (const key of required) {
        const ingredient = ingMap.get(key);
        const ingredientPct = ingredient?.max_stock > 0 ? ingredient.current_stock / ingredient.max_stock * 100 : 0;
        if (ingredientPct <= pct) {
          pct = ingredientPct;
          reason = ingredient ? `${ingredient.name} (${ingredient.current_stock} ${ingredient.unit || ''})` : `Bahan tidak tersedia: ${key}`;
        }
      }
    } else {
      pct = nonNegative(product.max_stock) > 0 ? stockM / Number(product.max_stock) * 100 : 0;
    }
    return {
      ...product,
      ...(product.item_type === 'perporsi' ? { recipe_summary: [] } : {}),
      stock: stockM,
      raw_stock: stockM,
      stock_m: stockM,
      stock_l: stockL,
      stock_percent: Math.round(pct),
      is_locked_m: stockM < 1,
      is_locked_l: stockL < 1,
      is_locked: stockM < 1 && stockL < 1,
      is_warning: (stockM > 0 || stockL > 0) && pct <= 20,
      limiting_reason: product.item_type === 'perporsi' ? 'Stok per porsi' : reason,
    };
  });
  return { liveProducts, liveIngredients };
}

// Zero is returned only if nothing is available; callers disable/remove that
// row instead of ever committing an invalid positive-quantity order item.
export function normalizeQuantity(value, max) {
  const limit = Math.min(500, Math.floor(nonNegative(max)));
  if (limit < 1) return 0;
  const parsed = Number(value);
  return Math.min(limit, Math.max(1, Number.isFinite(parsed) ? Math.floor(parsed) : 1));
}
