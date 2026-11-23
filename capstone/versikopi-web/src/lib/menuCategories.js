export function getMenuCategories(products) {
  const categories = products
    .filter((product) => product.item_type !== 'paketan')
    .map((product) => product.category)
    .filter(Boolean);

  return ['Semua', ...new Set(categories)];
}
