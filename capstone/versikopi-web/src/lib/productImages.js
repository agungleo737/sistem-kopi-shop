// katalog foto produk hasil crop bersih
export const PRODUCT_IMAGES_CATALOG = [
  { label: 'Kopi Susu Versi (Aren Smooth)', url: '/products/kopi-susu-versi.jpg' },
  { label: 'Kopi Susu Temu (Aren Temu)', url: '/products/aren-temu.jpg' },
  { label: 'Kopi Susu Java Latte', url: '/products/java-latte.jpg' },
  { label: 'Kopi Susu Hazelnut', url: '/products/kopi-hazelnut.jpg' },
  { label: 'Kopi Susu Butterscotch', url: '/products/kopi-butterscotch.jpg' },
  { label: 'Kopi Caramel', url: '/products/kopi-caramel.jpg' },
  { label: 'Versinya Salted Caramel', url: '/products/salted-caramel.jpg' },
  { label: 'Americano', url: '/products/americano.jpg' },
  { label: 'Versi Ramu (Fruity Americano)', url: '/products/versi-ramu.jpg' },
  { label: 'Versi Rindu', url: '/products/versi-rindu.jpg' },
  { label: 'Matcha Coffee', url: '/products/matcha-coffee.jpg' },
  { label: 'Green Tea Latte (Matcha Latte)', url: '/products/green-tea-latte.jpg' },
  { label: 'Ice Black Tea / Lemon Tea / Lychee Tea', url: '/products/ice-black-tea.jpg' },
  { label: 'Cireng Isi Ayam (5 pcs)', url: '/products/cireng-ayam.jpg' },
  { label: 'Dimsum Keju (4 pcs)', url: '/products/dimsum-keju.jpg' },
  { label: 'French Fries (Kentang Kecil)', url: '/products/french-fries.jpg' },
  { label: 'Kentang Potong Gede (Crinkle Cut)', url: '/products/kentang-crinkle.jpg' },
  { label: 'Burger', url: '/products/burger.jpg' },
  { label: 'Paket Hemat Burger + French Fries', url: '/products/paket-burger-kentang.jpg' },
  { label: 'Paket Hemat Lemon Tea / Ice Black Tea', url: '/products/paket-hemat-tea.jpg' },
  { label: 'Paket Hemat Kopi Java', url: '/products/paket-hemat-kopi-java.jpg' },
  { label: 'Bundling 5 Cup Kopi Java Latte', url: '/products/java-latte-5cup.jpg' },
  { label: 'Bundling Kentang + Kopi Temu', url: '/products/paket-kentang-kopi-temu.jpg' },
];

// cari foto produk berdasarkan url atau nama menu
export function getProductImage(product) {
  if (!product) return null;
  if (product.image_url && String(product.image_url).trim() !== '') {
    return product.image_url;
  }

  const name = String(product.name || '').toLowerCase();

  if (name.includes('berlima') || name.includes('5 cup') || name.includes('5 biji') || name.includes('javalatte 5')) {
    return '/products/java-latte-5cup.jpg';
  }
  if (name.includes('kentang') && name.includes('temu')) {
    return '/products/paket-kentang-kopi-temu.jpg';
  }
  if (name.includes('paket') && name.includes('java')) {
    return '/products/paket-hemat-kopi-java.jpg';
  }
  if (name.includes('paket') && (name.includes('tea') || name.includes('lemon') || name.includes('black'))) {
    return '/products/paket-hemat-tea.jpg';
  }
  if (name.includes('paket') && name.includes('burger')) {
    return '/products/paket-burger-kentang.jpg';
  }
  if (name.includes('temu')) return '/products/aren-temu.jpg';
  if (name.includes('aren') || name.includes('susu versi')) return '/products/kopi-susu-versi.jpg';
  if (name.includes('java latte') || name.includes('javalatte')) return '/products/java-latte.jpg';
  if (name.includes('hazelnut')) return '/products/kopi-hazelnut.jpg';
  if (name.includes('butterscotch') || name.includes('butterscooth')) return '/products/kopi-butterscotch.jpg';
  if (name.includes('salted caramel')) return '/products/salted-caramel.jpg';
  if (name.includes('caramel')) return '/products/kopi-caramel.jpg';
  if (name.includes('ramu')) return '/products/versi-ramu.jpg';
  if (name.includes('rindu')) return '/products/versi-rindu.jpg';
  if (name.includes('matcha coffee')) return '/products/matcha-coffee.jpg';
  if (name.includes('americano') || name.includes('espresso')) return '/products/americano.jpg';
  if (name.includes('green tea') || name.includes('matcha')) return '/products/green-tea-latte.jpg';
  if (name.includes('thai')) return '/products/aren-temu.jpg';
  if (name.includes('tea') || name.includes('leci') || name.includes('lychee') || name.includes('lemon')) {
    return '/products/ice-black-tea.jpg';
  }
  if (name.includes('cireng')) return '/products/cireng-ayam.jpg';
  if (name.includes('dimsum') || name.includes('keju') || name.includes('risol')) return '/products/dimsum-keju.jpg';
  if (name.includes('crinkle') || name.includes('gede')) return '/products/kentang-crinkle.jpg';
  if (name.includes('french') || name.includes('fries') || name.includes('kentang')) return '/products/french-fries.jpg';
  if (name.includes('burger')) return '/products/burger.jpg';

  return '/products/kopi-susu-versi.jpg';
}
