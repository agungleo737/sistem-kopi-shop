import test from 'node:test';
import assert from 'node:assert/strict';
import * as inventoryHelpers from './inventory.js';
const { calculateInventory, getOrderableForProduct, normalizeQuantity } = inventoryHelpers;

// Recipe fixtures, not responses from a running API.
const drink = {
  id: 1, name: 'Kopi', category: 'Kopi', item_type: 'satuan', stock: 50,
  recipe_summary: [
    { name: 'Milk', amount_m: 100, amount_l: 150, unit: 'ml' },
    { name: 'Coffee', amount_m: 1, amount_l: 2, unit: 'gr' },
  ],
};
const ingredients = (milk, coffee) => [
  { name: 'Milk', current_stock: milk, max_stock: 10000, unit: 'ml' },
  { name: 'Coffee', current_stock: coffee, max_stock: 100, unit: 'gr' },
];
const available = (product, rows, cart = [], size = 'M', products = [product]) => {
  const live = calculateInventory(products, rows, cart);
  return getOrderableForProduct(live.liveProducts.find((p) => p.id === product.id), live.liveIngredients, size, live.liveProducts);
};

test('96 ml milk and 5 gr coffee cannot make a 100 ml / 1 gr portion', () => {
  const { liveProducts: [product] } = calculateInventory([drink], ingredients(96, 5));
  assert.equal(product.stock, 0);
  assert.equal(product.is_locked, true);
  assert.equal(available(drink, ingredients(96, 5)), 0);
});

test('the exact final portion is sellable even below five percent', () => {
  const live = calculateInventory([drink], ingredients(100, 5));
  assert.equal(live.liveProducts[0].is_locked, false);
  assert.equal(live.liveProducts[0].is_warning, true);
  assert.equal(available(drink, ingredients(100, 5)), 1);
  assert.equal(available(drink, ingredients(100, 5), [{ id: 1, size: 'M', qty: 1 }]), 0);
});

test('missing required ingredient means zero availability, not ignored', () => {
  assert.equal(available(drink, ingredients(1000, 100).slice(1)), 0);
});

test('shared ingredients combine M and L cart consumption across products', () => {
  const other = { ...drink, id: 2 };
  const cart = [{ id: 1, size: 'L', qty: 1 }, { id: 2, size: 'M', qty: 1 }];
  assert.equal(available(drink, ingredients(350, 10), cart, 'M', [drink, other]), 1);
  assert.equal(available(drink, ingredients(350, 10), cart, 'L', [drink, other]), 0);
});

test('aggregated bundle recipe consumes all shared component requirements', () => {
  const bundle = { ...drink, id: 3, item_type: 'paketan', bundle_items: [{ product_id: 1, qty: 2 }],
    recipe_summary: [{ name: 'Milk', amount_m: 200, amount_l: 300 }, { name: 'Coffee', amount_m: 2, amount_l: 4 }] };
  assert.equal(available(bundle, ingredients(300, 10), [{ id: 1, size: 'M', qty: 1 }], 'M', [drink, bundle]), 1);
  assert.equal(available(drink, ingredients(300, 10), [{ id: 3, size: 'M', qty: 1 }], 'M', [drink, bundle]), 1);
});

test('only the exhausted size locks; another size can remain available', () => {
  const reversed = { ...drink, recipe_summary: [{ name: 'Milk', amount_m: 200, amount_l: 100 }] };
  const { liveProducts: [product] } = calculateInventory([reversed], ingredients(100, 10));
  assert.equal(product.stock_m, 0);
  assert.equal(product.stock_l, 1);
  assert.equal(product.is_locked_m, true);
  assert.equal(product.is_locked_l, false);
  assert.equal(product.is_locked, false);
});

test('fallback stock is real, shared across sizes and reduced by cart', () => {
  const product = { id: 5, stock: 2, stock_m: 2, stock_l: 2, max_stock: 100, recipe_summary: [] };
  assert.equal(available(product, [], [{ id: 5, size: 'L', qty: 1 }]), 1);
  assert.equal(available(product, [], [{ id: 5, size: 'L', qty: 2 }], 'L'), 0);
  assert.equal(available({ ...product, stock: 0, stock_m: 0, stock_l: 0 }, []), 0);
});

test('desired cart quantity capacity includes its own reserved ingredients', () => {
  const cart = [{ cartItemId: 'm', id: 1, size: 'M', qty: 2 }, { cartItemId: 'l', id: 1, size: 'L', qty: 1 }];
  assert.equal(available(drink, ingredients(450, 10), cart.filter((item) => item.cartItemId !== 'm')), 3);
  assert.equal(available(drink, ingredients(450, 10), cart), 1);
});

test('duplicate recipe ingredient rows are added before portion division', () => {
  const product = { ...drink, recipe_summary: [{ name: 'Milk', amount_m: 60 }, { name: ' milk ', amount_m: 60 }] };
  assert.equal(available(product, ingredients(600, 10)), 5);
});

test('quantity commits are positive bounded integers; unavailable means zero', () => {
  for (const [value, max, expected] of [
    ['', 8, 1], ['   ', 8, 1], ['NaN', 8, 1], [NaN, 8, 1], [Infinity, 8, 1],
    ['-4', 8, 1], ['0', 8, 1], ['2.9', 8, 2], ['99', 8, 8], ['3', 8, 3],
    ['999', 1000, 500], ['2', 0, 0], ['3', NaN, 0], ['3', 2.5, 2],
  ]) assert.equal(normalizeQuantity(value, max), expected, `value=${value}, max=${max}`);
});

test('portion items ignore legacy recipes and use one integer stock pool', () => {
  const portion = { ...drink, item_type: 'perporsi', stock: 5, stock_m: 5, stock_l: 99 };
  const { liveProducts: [live] } = calculateInventory([portion], [], [{ id: 1, qty: 2, size: 'M' }]);
  assert.equal(live.stock_m, 3);
  assert.equal(live.stock_l, 3);
  assert.equal(getOrderableForProduct(live, [], 'L'), 3);
});

test('all-portion bundles and singles share portion component stock', () => {
  const portion = { id: 10, item_type: 'perporsi', stock: 7, active: 1 };
  const bundle = { id: 20, item_type: 'paketan', stock: 999, portion_items: [{ product_id: 10, qty: 2 }], recipe_summary: [] };
  const { liveProducts } = calculateInventory([portion, bundle], [], [{ id: 10, qty: 1 }, { id: 20, qty: 2 }]);
  assert.equal(liveProducts[0].stock, 2);
  assert.equal(liveProducts[1].stock, 1);
  assert.equal(getOrderableForProduct(liveProducts[1], [], 'M', liveProducts), 1);
  assert.equal(getOrderableForProduct(bundle, [], 'M', [portion, bundle]), 3);
});

test('mixed bundles are limited by both materials and active portion components', () => {
  const portion = { id: 10, item_type: 'perporsi', stock: 5, active: 1 };
  const bundle = { ...drink, id: 20, item_type: 'paketan', portion_items: [{ product_id: 10, qty: 2 }] };
  assert.equal(calculateInventory([portion, bundle], ingredients(1000, 100)).liveProducts[1].stock, 2);
  assert.equal(calculateInventory([portion, bundle], ingredients(100, 100)).liveProducts[1].stock, 1);
  assert.equal(calculateInventory([bundle], ingredients(1000, 100)).liveProducts[0].stock, 0);
  assert.equal(calculateInventory([{ ...portion, active: 0 }, bundle], ingredients(1000, 100)).liveProducts[1].stock, 0);
});

test('repeated portion components aggregate before calculating bundle capacity', () => {
  const portion = { id: 10, item_type: 'perporsi', stock: 5, active: 1 };
  const bundle = { id: 20, item_type: 'paketan', portion_items: [{ product_id: 10, qty: 2 }, { product_id: 10, qty: 1 }] };
  assert.equal(calculateInventory([portion, bundle], []).liveProducts[1].stock, 1);
});

test('duplicate name checks collapse spaces, ignore case and allow own edit', () => {
  assert.equal(typeof inventoryHelpers.findDuplicateName, 'function');
  const rows = [{ id: 1, name: 'Kopi Susu' }, { id: 2, name: 'Teh' }];
  assert.equal(inventoryHelpers.findDuplicateName(rows, ' KOPI   susu ').id, 1);
  assert.equal(inventoryHelpers.findDuplicateName(rows, 'kopi susu', '1'), undefined);
  assert.equal(inventoryHelpers.findDuplicateName(rows, ''), undefined);
});

test('recipe duplicate checks only compare rows, not existing warehouse ingredients', () => {
  assert.equal(typeof inventoryHelpers.hasDuplicateNames, 'function');
  assert.equal(inventoryHelpers.hasDuplicateNames([{ name: 'Milk' }, { name: ' milk ' }]), true);
  assert.equal(inventoryHelpers.hasDuplicateNames([{ name: 'Milk' }, { name: 'Coffee' }]), false);
  assert.equal(inventoryHelpers.hasDuplicateNames([{ name: '' }, { name: ' ' }]), false);
});

test('API validation field errors are shown before generic message', () => {
  assert.equal(typeof inventoryHelpers.apiErrorMessage, 'function');
  assert.equal(inventoryHelpers.apiErrorMessage({ response: { data: { message: 'Invalid data', errors: { name: ['Menu sudah ada.'] } } } }, 'fallback'), 'Menu sudah ada.');
  assert.equal(inventoryHelpers.apiErrorMessage({}, 'fallback'), 'fallback');
});

test('bundle without component collection never falls back to own stock', () => {
  const bundle = { id: 20, item_type: 'paketan', stock: 999, portion_items: [{ product_id: 10, qty: 2 }] };
  assert.equal(getOrderableForProduct(bundle, []), 0);
});

test('portion live view removes old recipes and material limiting reason', () => {
  const portion = { ...drink, stock: 5, item_type: 'perporsi', limiting_reason: 'Milk (10 ml)' };
  const { liveProducts: [live] } = calculateInventory([portion], []);
  assert.deepEqual(live.recipe_summary, []);
  assert.equal(live.limiting_reason, 'Stok per porsi');
});
