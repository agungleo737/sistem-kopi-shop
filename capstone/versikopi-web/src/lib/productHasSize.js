export const productHasSize = (product, isFood) => Boolean(
  product &&
  !isFood &&
  product.item_type !== 'perporsi' &&
  product.item_type !== 'paketan'
);
