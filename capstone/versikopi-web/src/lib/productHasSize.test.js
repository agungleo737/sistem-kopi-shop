import assert from 'node:assert/strict';
import test from 'node:test';
import { productHasSize } from './productHasSize.js';

const cases = [
  { name: 'Per Porsi non-food tidak memiliki ukuran', product: { item_type: 'perporsi', category: 'Coffee' }, isFood: false, expected: false },
  {
    name: 'Coffee dengan harga M/L sama tetap menawarkan kedua ukuran',
    product: { category: 'Coffee', item_type: 'satuan', price_m: 18000, price_l: 18000 },
    isFood: false,
    expected: true,
  },
  {
    name: 'harga M/L sama berupa string dari API tetap menawarkan ukuran',
    product: { category: 'Coffee', item_type: 'satuan', price_m: '18000.00', price_l: '18000.00' },
    isFood: false,
    expected: true,
  },
  {
    name: 'minuman dengan harga M/L berbeda tetap menawarkan ukuran',
    product: { category: 'Non Coffee', item_type: 'satuan', price_m: 18000, price_l: 22000 },
    isFood: false,
    expected: true,
  },
  {
    name: 'Food tetap tanpa ukuran meskipun harga berbeda',
    product: { category: 'Food', item_type: 'satuan', price_m: 18000, price_l: 22000 },
    isFood: true,
    expected: false,
  },
  {
    name: 'paketan minuman tetap tanpa ukuran meskipun harga berbeda',
    product: { category: 'Coffee', item_type: 'paketan', price_m: 18000, price_l: 22000 },
    isFood: false,
    expected: false,
  },
  {
    name: 'tanpa produk terpilih tidak menawarkan ukuran',
    product: null,
    isFood: false,
    expected: false,
  },
];

for (const { name, product, isFood, expected } of cases) {
  test(name, () => {
    assert.equal(productHasSize(product, isFood), expected);
  });
}
