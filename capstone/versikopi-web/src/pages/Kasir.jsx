import { useState, useEffect, useMemo } from 'react';
import { useAuth } from '../context/useAuth';
import api from '../lib/api';
import {
  IconCup,
  IconReceipt,
  IconTag,
  IconBag,
  IconWallet,
  IconQr,
  IconPrinter,
  IconSearch,
  IconBook,
  IconLock,
  IconAlert,
  IconLayers,
  ProductIllustration,
} from '../components/Icons';
import { getProductImage } from '../lib/productImages';
import { getStoreLogo } from '../lib/branding';
import receiptLogo from '../assets/hitamputih.png';
import { getMenuCategories } from '../lib/menuCategories';
import { productHasSize } from '../lib/productHasSize.js';
import { calculateInventory, getOrderableForProduct as getOrderable, normalizeQuantity } from '../lib/inventory.js';
import QuantityInput from '../components/QuantityInput.jsx';

const SUGAR_LEVELS = [
  { value: '100%', name: 'Normal' },
  { value: '75%', name: 'Less' },
  { value: '50%', name: 'Half' },
  { value: '25%', name: 'Light' },
  { value: '0%', name: 'Tanpa Gula' },
];

const FOOD_CATEGORIES = ['snack', 'pastry', 'makanan', 'food'];
const FOOD_KEYWORDS = ['burger', 'kentang', 'fries', 'cireng', 'dimsum', 'roti', 'croissant', 'snack', 'pastry'];
const DRINK_KEYWORDS = ['kopi', 'coffee', 'latte', 'americano', 'matcha', 'tea', 'teh', 'versi', 'caramel', 'hazelnut', 'butterscotch'];

// cek apakah produk termasuk makanan (tanpa opsi gula dan tanpa medium/large)
const isFoodProduct = (prod) => {
  if (!prod) return false;
  const cat = (prod.category || '').trim().toLowerCase();
  if (FOOD_CATEGORIES.includes(cat)) return true;
  if (prod.item_type === 'paketan') return false;
  const name = (prod.name || '').toLowerCase();
  return FOOD_KEYWORDS.some((kw) => name.includes(kw)) && !DRINK_KEYWORDS.some((kw) => name.includes(kw));
};

// cek apakah produk memakai opsi gula (minuman atau paket berisi minuman)
const productHasSugar = (prod) => {
  if (!prod || prod.item_type === 'perporsi' || isFoodProduct(prod)) return false;

  if (prod.item_type === 'paketan') {
    const bundles = Array.isArray(prod.bundle_items) ? prod.bundle_items : [];
    if (bundles.length > 0) {
      return bundles.some((b) => {
        const bName = (b.name || '').toLowerCase();
        return DRINK_KEYWORDS.some((kw) => bName.includes(kw));
      });
    }
    const pName = (prod.name || '').toLowerCase();
    return DRINK_KEYWORDS.some((kw) => pName.includes(kw));
  }

  return true;
};

const parseOrderItems = (rawItems) => {
  if (Array.isArray(rawItems)) return rawItems;
  if (typeof rawItems === 'string') {
    try {
      const parsed = JSON.parse(rawItems);
      return Array.isArray(parsed) ? parsed : [];
    } catch {
      return [];
    }
  }
  return [];
};

export default function Kasir() {
  const { user, logout } = useAuth();
  const [activeTab, setActiveTab] = useState('menu');
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [cartOpen, setCartOpen] = useState(false);

  const [products, setProducts] = useState([]);
  const [ingredients, setIngredients] = useState([]);
  const [recipes, setRecipes] = useState([]);
  const [promos, setPromos] = useState([]);
  const [orders, setOrders] = useState([]);
  const [settings, setSettings] = useState({});
  const [loading, setLoading] = useState(true);
  const [notice, setNotice] = useState(null);
  const [showAlertDetails, setShowAlertDetails] = useState(false);

  // filter menu kasir
  const [menuType, setMenuType] = useState('satuan');
  const [category, setCategory] = useState('Semua');
  const [search, setSearch] = useState('');

  // filter riwayat pesanan
  const [historySearch, setHistorySearch] = useState('');
  const [historyMethod, setHistoryMethod] = useState('all');

  // filter promo
  const [promoSearch, setPromoSearch] = useState('');
  const [promoFilterType, setPromoFilterType] = useState('all');

  // form promo toko
  const [isPromoModalOpen, setIsPromoModalOpen] = useState(false);
  const [editingPromoId, setEditingPromoId] = useState(null);
  const [promoFormCode, setPromoFormCode] = useState('');
  const [promoFormType, setPromoFormType] = useState('percent');
  const [promoFormValue, setPromoFormValue] = useState('15');
  const [promoFormMinSubtotal, setPromoFormMinSubtotal] = useState('40000');
  const [promoFormMaxDiscount, setPromoFormMaxDiscount] = useState('15000');
  const [promoFormActive, setPromoFormActive] = useState(true);
  const [savingPromo, setSavingPromo] = useState(false);

  // filter resep
  const [recipeSearch, setRecipeSearch] = useState('');
  const [recipeCategory, setRecipeCategory] = useState('Semua');

  // state keranjang
  const [cart, setCart] = useState([]);
  const [justAddedId, setJustAddedId] = useState(null);
  const [selectedProduct, setSelectedProduct] = useState(null);
  const [selectedSize, setSelectedSize] = useState('M');
  const [selectedSugar, setSelectedSugar] = useState('100%');
  const [productQty, setProductQty] = useState(1);
  const [productNote, setProductNote] = useState('');

  // state pembayaran
  const [isCheckoutOpen, setIsCheckoutOpen] = useState(false);
  const [paymentMethod, setPaymentMethod] = useState('cash');
  const [cashReceived, setCashReceived] = useState('');
  const [promoCodeInput, setPromoCodeInput] = useState('');
  const [promoError, setPromoError] = useState('');
  const [appliedPromo, setAppliedPromo] = useState(null);
  const [successOrder, setSuccessOrder] = useState(null);
  const [selectedReceipt, setSelectedReceipt] = useState(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [checkoutError, setCheckoutError] = useState('');

  const showNotice = (text, type = 'success') => {
    setNotice({ text, type });
    setTimeout(() => {
      setNotice((prev) => (prev?.text === text ? null : prev));
    }, 3500);
  };

  const refreshData = async () => {
    try {
      const [resProd, resIng, resRec, resPromo, resSet, resOrd] = await Promise.all([
        api.get('/products').then((r) => r.data).catch(() => []),
        api.get('/ingredients').then((r) => r.data).catch(() => ({ ingredients: [] })),
        api.get('/recipes').then((r) => r.data).catch(() => ({ recipes: [] })),
        api.get('/promos?all=1').then((r) => r.data).catch(() => []),
        api.get('/settings').then((r) => r.data).catch(() => ({})),
        api.get('/orders').then((r) => r.data).catch(() => ({ orders: [] })),
      ]);
      setProducts(Array.isArray(resProd) ? resProd : []);
      setIngredients(Array.isArray(resIng?.ingredients) ? resIng.ingredients : []);
      setRecipes(Array.isArray(resRec?.recipes) ? resRec.recipes : []);
      setPromos(Array.isArray(resPromo) ? resPromo : []);
      setSettings(resSet || {});
      setOrders(resOrd.orders || []);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    refreshData();
  }, []);

  // Semua ukuran dan paket memakai stok bahan bersama, tanpa cadangan porsi.
  const { liveProducts, liveIngredients } = useMemo(
    () => calculateInventory(products, ingredients, cart),
    [products, ingredients, cart]
  );
  const getOrderableForProduct = (prod, size = 'M') => getOrderable(prod, liveIngredients, size, liveProducts);
  const applicableSizes = (prod) => productHasSize(prod, isFoodProduct(prod)) ? ['M', 'L'] : ['M'];
  const availableSizes = (prod) => applicableSizes(prod).filter((size) => getOrderableForProduct(prod, size) > 0);

  // pisah menu satuan dan paketan
  const satuanProducts = liveProducts.filter((p) => p.item_type !== 'paketan');
  const paketanProducts = liveProducts.filter((p) => p.item_type === 'paketan');

  const activeTypeProducts = menuType === 'paketan' ? paketanProducts : satuanProducts;
  const categories = getMenuCategories(liveProducts);

  // peringatan dan kunci stok
  const lockedProducts = liveProducts.filter((p) => availableSizes(p).length === 0);
  const warningProducts = liveProducts.filter(
    (p) =>
      !lockedProducts.some((lp) => lp.id === p.id) &&
      p.is_warning
  );
  const alertIngredients = liveIngredients.filter((ing) => ing.is_locked || ing.is_warning);

  // filter menu kasir
  const filteredProducts = activeTypeProducts.filter((p) => {
    const matchCat = menuType === 'paketan' || category === 'Semua' || p.category === category;
    const q = search.trim().toLowerCase();
    const matchBundle =
      Array.isArray(p.bundle_items) &&
      p.bundle_items.some((b) => (b.name || '').toLowerCase().includes(q));
    const matchSearch =
      !q ||
      p.name.toLowerCase().includes(q) ||
      (p.description || '').toLowerCase().includes(q) ||
      (p.category || '').toLowerCase().includes(q) ||
      matchBundle;
    return matchCat && matchSearch;
  });

  // filter riwayat transaksi
  const filteredOrders = orders.filter((o) => {
    const matchMethod = historyMethod === 'all' || o.payment_method === historyMethod;
    const q = historySearch.trim().toLowerCase();
    if (!q) return matchMethod;
    const orderItems = parseOrderItems(o.items);
    const matchCode = (o.code || '').toLowerCase().includes(q);
    const matchCashier = (o.cashier_name || '').toLowerCase().includes(q);
    const matchItem = orderItems.some((it) => (it.name || '').toLowerCase().includes(q));
    return matchMethod && (matchCode || matchCashier || matchItem);
  });

  // filter promo
  const filteredPromos = promos.filter((p) => {
    const matchType = promoFilterType === 'all' || p.type === promoFilterType;
    const q = promoSearch.trim().toLowerCase();
    const matchSearch = !q || (p.code || '').toLowerCase().includes(q);
    return matchType && matchSearch;
  });

  // filter resep
  const recipedProducts = liveProducts.filter((prod) =>
    prod.item_type !== 'perporsi' && recipes.some((r) => Number(r.product_id) === Number(prod.id))
  );
  const filteredRecipeProducts = recipedProducts.filter((prod) => {
    const matchCat = recipeCategory === 'Semua' || prod.category === recipeCategory;
    const q = recipeSearch.trim().toLowerCase();
    if (!q) return matchCat;
    const rec = recipes.find((r) => Number(r.product_id) === Number(prod.id));
    const matchName = prod.name.toLowerCase().includes(q);
    const matchIng = rec?.ingredients?.some((ing) => (ing.name || '').toLowerCase().includes(q));
    const matchSteps = (rec?.steps || '').toLowerCase().includes(q);
    return matchCat && (matchName || matchIng || matchSteps);
  });

  const triggerAddedFeedback = (prodId) => {
    setJustAddedId(prodId);
    setTimeout(() => {
      setJustAddedId((prev) => (prev === prodId ? null : prev));
    }, 850);
  };

  const openProductModal = (prod) => {
    const [size] = availableSizes(prod);
    if (!size) return;

    setSelectedProduct(prod);
    setSelectedSize(size);
    setSelectedSugar(productHasSugar(prod) ? '100%' : null);
    setProductQty(1);
    setProductNote('');
  };

  const addToCartFromModal = () => {
    if (!selectedProduct) return;
    const liveProd = liveProducts.find((p) => p.id === selectedProduct.id) || selectedProduct;
    const availableToAdd = getOrderableForProduct(liveProd, selectedSize);
    if (availableToAdd <= 0) {
      setSelectedProduct(null);
      return;
    }

    const hasSugar = productHasSugar(selectedProduct);
    const finalSugar = hasSugar ? (selectedSugar || '100%') : null;
    const cleanNote = productNote.replace(/<\/?[^>]+(>|$)/g, '').trim();
    const unitPrice = selectedSize === 'M' ? Number(selectedProduct.price_m) : Number(selectedProduct.price_l);
    const cartItemId = `${selectedProduct.id}-${selectedSize}-${finalSugar || 'none'}-${cleanNote}`;
    const existingIndex = cart.findIndex((item) => item.cartItemId === cartItemId);
    const limit = Math.min(availableToAdd, 500 - (existingIndex > -1 ? cart[existingIndex].qty : 0));
    const qtyToAdd = normalizeQuantity(productQty, limit);
    if (qtyToAdd < 1) return;
    if (existingIndex > -1) {
      const updated = [...cart];
      updated[existingIndex].qty += qtyToAdd;
      setCart(updated);
    } else {
      setCart([
        ...cart,
        {
          cartItemId,
          id: selectedProduct.id,
          name: selectedProduct.name,
          category: selectedProduct.category,
          item_type: selectedProduct.item_type || 'satuan',
          bundle_items: selectedProduct.bundle_items || [],
          gradient: selectedProduct.gradient,
          size: selectedSize,
          sugar: finalSugar,
          note: cleanNote,
          unit_price: unitPrice,
          qty: qtyToAdd,
        },
      ]);
    }
    triggerAddedFeedback(selectedProduct.id);
    setSelectedProduct(null);
  };

  const getCartQtyLimit = (item, currentCart = cart) => {
    // Reclaim THIS row before computing a desired total, not just an increment.
    const inventory = calculateInventory(products, ingredients, currentCart.filter((row) => row.cartItemId !== item.cartItemId));
    const product = inventory.liveProducts.find((row) => row.id === item.id);
    return Math.min(500, getOrderable(product, inventory.liveIngredients, item.size, inventory.liveProducts));
  };

  const setCartQty = (cartItemId, desired, delta = null) => {
    setCart((prev) => {
      const targetItem = prev.find((i) => i.cartItemId === cartItemId);
      if (!targetItem) return prev;
      if (delta === -1 && targetItem.qty <= 1) {
        return prev.filter((item) => item.cartItemId !== cartItemId);
      }
      const next = normalizeQuantity(delta === null ? desired : targetItem.qty + delta, getCartQtyLimit(targetItem, prev));
      return prev.flatMap((item) => item.cartItemId !== cartItemId ? [item] : next > 0 ? [{ ...item, qty: next }] : []);
    });
  };
  const updateCartQty = (cartItemId, delta) => setCartQty(cartItemId, null, delta);

  const subtotal = cart.reduce((acc, item) => acc + item.unit_price * item.qty, 0);

  let discount = 0;
  if (appliedPromo && subtotal >= Number(appliedPromo.min_subtotal || 0)) {
    if (appliedPromo.type === 'percent') {
      discount = Math.round((subtotal * Number(appliedPromo.value)) / 100);
      if (Number(appliedPromo.max_discount) > 0 && discount > Number(appliedPromo.max_discount)) {
        discount = Number(appliedPromo.max_discount);
      }
    } else {
      discount = Number(appliedPromo.value);
    }
    discount = Math.min(discount, subtotal);
  }

  const taxPercent = Number(settings.tax_percent || 0);
  const servicePercent = Number(settings.service_percent || 0);
  const taxable = Math.max(0, subtotal - discount);
  const tax = Math.round((taxable * taxPercent) / 100);
  const service = Math.round((taxable * servicePercent) / 100);
  const total = Math.max(0, taxable + tax + service);

  const handleApplyPromo = (directCode = null) => {
    setPromoError('');
    const code = (typeof directCode === 'string' ? directCode : promoCodeInput).trim().toUpperCase();
    if (!code) return;
    const promo = promos.find((p) => p.code.toUpperCase() === code && p.active);
    if (!promo) {
      setPromoError('Kode promo tidak ditemukan atau sedang nonaktif.');
      return;
    }
    if (subtotal < Number(promo.min_subtotal)) {
      setPromoError(`Min. belanja Rp ${Number(promo.min_subtotal).toLocaleString('id-ID')}`);
      return;
    }
    setAppliedPromo(promo);
    setPromoCodeInput('');
  };

  // kelola promo toko
  const openAddPromoModal = () => {
    setEditingPromoId(null);
    setPromoFormCode('');
    setPromoFormType('percent');
    setPromoFormValue('15');
    setPromoFormMinSubtotal('40000');
    setPromoFormMaxDiscount('15000');
    setPromoFormActive(true);
    setIsPromoModalOpen(true);
  };

  const openEditPromoModal = (promo) => {
    setEditingPromoId(promo.id);
    setPromoFormCode(promo.code);
    setPromoFormType(promo.type || 'percent');
    setPromoFormValue(String(promo.value ?? 0));
    setPromoFormMinSubtotal(String(promo.min_subtotal ?? 0));
    setPromoFormMaxDiscount(String(promo.max_discount ?? 0));
    setPromoFormActive(Boolean(promo.active));
    setIsPromoModalOpen(true);
  };

  const handleSavePromo = async (e) => {
    e.preventDefault();
    if (!promoFormCode.trim()) return;
    setSavingPromo(true);
    const payload = {
      code: promoFormCode.trim().toUpperCase(),
      type: promoFormType,
      value: Number(promoFormValue || 0),
      min_subtotal: Number(promoFormMinSubtotal || 0),
      max_discount: promoFormType === 'percent' ? Number(promoFormMaxDiscount || 0) : 0,
      active: promoFormActive,
    };

    try {
      if (editingPromoId) {
        const { data } = await api.put(`/promos/${editingPromoId}`, payload);
        showNotice(data.message || 'Promo toko berhasil diperbarui.');
      } else {
        const { data } = await api.post('/promos', payload);
        showNotice(data.message || 'Promo toko baru berhasil ditambahkan.');
      }
      setIsPromoModalOpen(false);
      refreshData();
    } catch (err) {
      const msg =
        err.response?.data?.message ||
        Object.values(err.response?.data?.errors || {})[0]?.[0] ||
        'Gagal menyimpan promo toko.';
      showNotice(msg, 'error');
    } finally {
      setSavingPromo(false);
    }
  };

  const handleTogglePromoActive = async (promo) => {
    try {
      await api.put(`/promos/${promo.id}`, {
        code: promo.code,
        type: promo.type,
        value: Number(promo.value),
        min_subtotal: Number(promo.min_subtotal),
        max_discount: Number(promo.max_discount || 0),
        active: !promo.active,
      });
      showNotice(`Promo ${promo.code} ${!promo.active ? 'diaktifkan' : 'dinonaktifkan'}.`);
      refreshData();
    } catch {
      showNotice('Gagal mengubah status promo.', 'error');
    }
  };

  const handleDeletePromo = async (promo) => {
    if (!window.confirm(`Hapus kode promo "${promo.code}" secara permanen?`)) return;
    try {
      await api.delete(`/promos/${promo.id}`);
      showNotice(`Promo ${promo.code} berhasil dihapus.`);
      refreshData();
    } catch {
      showNotice('Gagal menghapus promo.', 'error');
    }
  };

  const handleProcessOrder = async () => {
    if (cart.length === 0) return;
    setCheckoutError('');

    if (paymentMethod === 'cash' && Number(cashReceived) < total) {
      setCheckoutError('Nominal uang tunai yang diterima kurang dari total pembayaran.');
      return;
    }

    setIsSubmitting(true);
    try {
      const payload = {
        items: cart.map((c) => ({
          id: c.id,
          qty: c.qty,
          size: productHasSize(c, isFoodProduct(c)) ? c.size : 'M',
          sugar: productHasSugar(c) ? c.sugar : null,
          note: c.note,
        })),
        promo_code: appliedPromo ? appliedPromo.code : null,
        payment_method: paymentMethod,
        cash_received: paymentMethod === 'cash' ? Number(cashReceived) : total,
      };

      const { data } = await api.post('/orders', payload);

      if (data.success) {
        const finishedOrder = {
          code: data.order_code,
          cashier_name: user?.name || 'Kasir Versi Kopi',
          items: data.items || cart,
          subtotal: data.subtotal ?? subtotal,
          discount: data.discount ?? discount,
          tax: data.tax ?? tax,
          service: data.service ?? service,
          total: data.total ?? total,
          payment_method: paymentMethod,
          cash_received: data.cash_received ?? (paymentMethod === 'cash' ? Number(cashReceived) : total),
          change_amount: data.change_amount ?? (paymentMethod === 'cash' ? Number(cashReceived) - total : 0),
          created_at: new Date().toISOString(),
        };
        setSuccessOrder(finishedOrder);
        setCart([]);
        setAppliedPromo(null);
        setCashReceived('');
        setIsCheckoutOpen(false);
        refreshData();
      }
    } catch (err) {
      const msg =
        err.response?.data?.message ||
        Object.values(err.response?.data?.errors || {})[0]?.[0] ||
        'Gagal memproses transaksi. Silakan coba lagi.';
      setCheckoutError(msg);
    } finally {
      setIsSubmitting(false);
    }
  };

  const activeReceipt = successOrder || selectedReceipt;
  const activeReceiptItems = activeReceipt ? parseOrderItems(activeReceipt.items) : [];
  const selectedLiveProduct = selectedProduct
    ? liveProducts.find((p) => p.id === selectedProduct.id) || selectedProduct
    : null;
  const selectedProductRecipe = selectedLiveProduct && selectedLiveProduct.item_type !== 'perporsi'
    ? recipes.find((r) => Number(r.product_id) === Number(selectedLiveProduct.id))
    : null;
  const getModalMaxAdd = (product, size) => {
    if (!product) return 0;
    const sugar = productHasSugar(product) ? (selectedSugar || '100%') : 'none';
    const note = productNote.replace(/<\/?[^>]+(>|$)/g, '').trim();
    const existing = cart.find((item) => item.cartItemId === `${product.id}-${size}-${sugar}-${note}`);
    return Math.min(getOrderableForProduct(product, size), 500 - (existing?.qty || 0));
  };
  const selectedProductMaxAdd = getModalMaxAdd(selectedLiveProduct, selectedSize);
  const selectedQuantity = normalizeQuantity(productQty, selectedProductMaxAdd);

  const selectedProductHasSugar = productHasSugar(selectedLiveProduct);
  const selectedProductIsFood = isFoodProduct(selectedLiveProduct);
  const selectedProductHasSize = productHasSize(selectedLiveProduct, selectedProductIsFood);
  const totalCartItems = cart.reduce((a, b) => a + b.qty, 0);

  return (
    <div className="app">
      {/* scrim sidebar mobile */}
      <div
        className={`sidebar-scrim ${sidebarOpen ? 'show' : ''}`}
        onClick={() => setSidebarOpen(false)}
      />

      {/* sidebar kasir */}
      <aside className={`sidebar ${sidebarOpen ? 'open' : ''}`}>
        <div className="sidebar-brand">
          <div className="logo-ring small">
            <img src={getStoreLogo(settings.logo_version)} alt="" />
          </div>
          <div className="sidebar-brand-text">
            <strong>{settings.store_name || 'VERSI KOPI'}</strong>
            <small>Point of Sale</small>
          </div>
          <button
            type="button"
            className="sidebar-close"
            aria-label="Tutup menu"
            onClick={() => setSidebarOpen(false)}
          >
            ✕
          </button>
        </div>

        <nav className="nav">
          <button
            type="button"
            className={`nav-item ${activeTab === 'menu' ? 'active' : ''}`}
            onClick={() => {
              setActiveTab('menu');
              setSidebarOpen(false);
            }}
          >
            <span className="nav-icon">
              <IconCup size={18} />
            </span>
            <span>Menu Kasir</span>
            {(lockedProducts.length > 0 || warningProducts.length > 0) && (
              <span className="nav-badge">{lockedProducts.length + warningProducts.length}</span>
            )}
          </button>
          <button
            type="button"
            className={`nav-item ${activeTab === 'history' ? 'active' : ''}`}
            onClick={() => {
              setActiveTab('history');
              setSidebarOpen(false);
            }}
          >
            <span className="nav-icon">
              <IconReceipt size={18} />
            </span>
            <span>Riwayat Pesanan</span>
          </button>
          <button
            type="button"
            className={`nav-item ${activeTab === 'promos' ? 'active' : ''}`}
            onClick={() => {
              setActiveTab('promos');
              setSidebarOpen(false);
            }}
          >
            <span className="nav-icon">
              <IconTag size={18} />
            </span>
            <span>Promo Toko</span>
            <span className="nav-badge">{promos.filter((p) => p.active).length}</span>
          </button>
          <button
            type="button"
            className={`nav-item ${activeTab === 'recipes' ? 'active' : ''}`}
            onClick={() => {
              setActiveTab('recipes');
              setSidebarOpen(false);
            }}
          >
            <span className="nav-icon">
              <IconBook size={18} />
            </span>
            <span>Panduan Resep</span>
          </button>
        </nav>

        <div className="sidebar-foot">
          <button className="btn btn-ghost btn-block btn-sm" onClick={logout}>
            Keluar Sesi
          </button>
        </div>
      </aside>

      {/* konten utama */}
      <main className="app-main">
        <header className="topbar">
          <button
            type="button"
            className="hamburger"
            aria-label="Buka navigasi"
            onClick={() => setSidebarOpen(true)}
          >
            <span />
            <span />
            <span />
          </button>
          <div className="topbar-title">
            <h1>
              {activeTab === 'menu'
                ? 'Kasir & Pemesanan'
                : activeTab === 'history'
                ? 'Riwayat Transaksi'
                : activeTab === 'promos'
                ? 'Promo Toko'
                : 'Resep & Takaran'}
            </h1>
            <p>Stok bahan baku</p>
          </div>
        </header>

        {notice && (
          <div className="toast-root">
            <div className={`toast ${notice.type}`}>
              <div className="toast-content">
                <strong>{notice.type === 'error' ? 'Perhatian' : 'Berhasil'}</strong>
                <small>{notice.text}</small>
              </div>
            </div>
          </div>
        )}

        <div className="view">
          {/* menu kasir dan keranjang */}
          {activeTab === 'menu' && (
            <div className="cashier-layout">
              <div className="pos-catalog-col">
                {/* info peringatan stok */}
                {(lockedProducts.length > 0 || warningProducts.length > 0 || alertIngredients.length > 0) && (
                  <div className="pos-alert-bar">
                    <div className="pos-alert-summary">
                      <span className="pos-alert-badge-icon">
                        <IconAlert size={16} />
                      </span>
                      <div className="pos-alert-text">
                        <strong>Status Stok Bahan Gudang:</strong>
                        {lockedProducts.length > 0 && (
                          <span className="pos-alert-chip locked">
                            <IconLock size={12} /> {lockedProducts.length} Menu Terkunci (Tidak cukup 1 porsi)
                          </span>
                        )}
                        {warningProducts.length > 0 && (
                          <span className="pos-alert-chip warning">
                            {warningProducts.length} Menu Menipis (≤20%)
                          </span>
                        )}
                      </div>
                      <button
                        type="button"
                        className="pos-alert-toggle"
                        onClick={() => setShowAlertDetails((v) => !v)}
                      >
                        {showAlertDetails ? 'Sembunyikan Rincian' : 'Lihat Rincian Bahan'}
                      </button>
                    </div>

                    {showAlertDetails && (
                      <div className="pos-alert-drawer">
                        {lockedProducts.length > 0 && (
                          <div className="pos-alert-section">
                            <small>Terkunci Otomatis (Bahan tidak cukup untuk 1 porsi):</small>
                            <div className="pos-alert-pill-list">
                              {lockedProducts.map((p) => (
                                <span key={p.id} className="pos-mini-alert locked">
                                  <strong>{p.name}</strong> ({p.stock} porsi • {p.stock_percent}%)
                                  {p.limiting_reason ? ` — ${p.limiting_reason}` : ''}
                                </span>
                              ))}
                            </div>
                          </div>
                        )}
                        {warningProducts.length > 0 && (
                          <div className="pos-alert-section">
                            <small>Peringatan Stok Menipis (Sisa ≤ 20%):</small>
                            <div className="pos-alert-pill-list">
                              {warningProducts.map((p) => (
                                <span key={p.id} className="pos-mini-alert warning">
                                  <strong>{p.name}</strong> ({p.stock} porsi • {p.stock_percent}%)
                                  {p.limiting_reason ? ` — ${p.limiting_reason}` : ''}
                                </span>
                              ))}
                            </div>
                          </div>
                        )}
                      </div>
                    )}
                  </div>
                )}

                {/* filter jenis menu dan pencarian */}
                <div className="pos-command-bar">
                  <div className="pos-command-top">
                    <div className="pos-segmented" role="tablist" aria-label="Jenis Menu">
                      <button
                        type="button"
                        role="tab"
                        aria-selected={menuType === 'satuan'}
                        className={`pos-seg-btn ${menuType === 'satuan' ? 'active' : ''}`}
                        onClick={() => setMenuType('satuan')}
                      >
                        <IconCup size={16} />
                        <span>Menu Satuan</span>
                        <em className="pos-seg-count">{satuanProducts.length}</em>
                      </button>
                      <button
                        type="button"
                        role="tab"
                        aria-selected={menuType === 'paketan'}
                        className={`pos-seg-btn ${menuType === 'paketan' ? 'active' : ''}`}
                        onClick={() => setMenuType('paketan')}
                      >
                        <IconLayers size={16} />
                        <span>Menu Paketan</span>
                        <em className="pos-seg-count">{paketanProducts.length}</em>
                      </button>
                    </div>

                    <div className="pos-search-wrap">
                      <IconSearch size={16} />
                      <input
                        type="text"
                        placeholder={
                          menuType === 'paketan'
                            ? 'Cari nama paket atau isi bundling...'
                            : 'Cari kopi, matcha, pastry, atau bahan...'
                        }
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                      />
                      {search && (
                        <button type="button" className="search-clear" onClick={() => setSearch('')}>
                          ✕
                        </button>
                      )}
                    </div>
                  </div>

                  {menuType === 'satuan' && (
                    <div className="pos-cat-strip">
                      {categories.map((cat) => (
                        <button
                          key={cat}
                          type="button"
                          className={`pos-cat-pill ${category === cat ? 'active' : ''}`}
                          onClick={() => setCategory(cat)}
                        >
                          {cat === 'Semua' ? 'Semua Kategori' : cat}
                        </button>
                      ))}
                    </div>
                  )}
                </div>

                {/* daftar kartu menu */}
                {loading ? (
                  <div className="loading-state">
                    <div className="spinner"></div>
                    <p>Memuat katalog menu...</p>
                  </div>
                ) : filteredProducts.length === 0 ? (
                  <div className="card card-pad">
                    <div className="empty">
                      <strong>Menu Tidak Ditemukan</strong>
                      <p>
                        Tidak ada {menuType === 'paketan' ? 'menu paketan' : 'menu satuan'} yang cocok dengan kata
                        kunci "{search}".
                      </p>
                    </div>
                  </div>
                ) : (
                  <div className="pos-menu-grid">
                    {filteredProducts.map((p) => {
                      const [g1, g2] = p.gradient ? p.gradient.split(',') : ['#D8B493', '#A67C58'];
                      const qtyInCart = cart
                        .filter((item) => item.id === p.id)
                        .reduce((sum, item) => sum + item.qty, 0);
                      const isAdded = justAddedId === p.id;
                      const stockPct = Number(p.stock_percent ?? 100);
                      const remainingOrderable = Math.max(...applicableSizes(p).map((size) => getOrderableForProduct(p, size)));
                      const isLockedProduct = remainingOrderable < 1;
                      const isWarningProduct =
                        !isLockedProduct && p.is_warning;
                      const isButtonLocked = isLockedProduct;

                      return (
                        <article
                          key={p.id}
                          className={`pos-card ${isLockedProduct ? 'is-locked' : ''} ${
                            isWarningProduct ? 'is-warning' : ''
                          } ${qtyInCart > 0 ? 'in-cart' : ''}`}
                          onClick={() => {
                            if (!isButtonLocked) openProductModal(p);
                          }}
                        >
                          {/* header kartu */}
                          <div
                            className="pos-card-stage"
                            style={{ background: `linear-gradient(135deg, ${g1}, ${g2})` }}
                          >
                            <div className="pos-stage-top">
                              <span className="pos-stage-cat">
                                {p.item_type === 'paketan' ? 'Bundling' : p.category}
                              </span>
                              <span
                                className={`pos-stage-stock ${
                                  isLockedProduct ? 'locked' : isWarningProduct ? 'warning' : 'safe'
                                }`}
                              >
                                <i className="pos-stock-ind" />
                                {isLockedProduct
                                  ? `Terkunci • 0 porsi (${stockPct}%)`
                                  : `${remainingOrderable} porsi • ${stockPct}%`}
                              </span>
                            </div>

                            <div className="pos-stage-photo-wrap">
                              <img
                                src={getProductImage(p)}
                                alt={p.name}
                                className="pos-stage-photo"
                                loading="lazy"
                              />
                            </div>

                            {qtyInCart > 0 && (
                              <span className="pos-stage-cart-pill">{qtyInCart}× di pesanan</span>
                            )}
                          </div>

                          {/* isi kartu menu */}
                          <div className="pos-card-body">
                            <h3 className="pos-card-title">{p.name}</h3>
                            <p className="pos-card-desc">{p.description}</p>

                            {/* info paket atau resep */}
                            {p.item_type === 'paketan' &&
                            Array.isArray(p.bundle_items) &&
                            p.bundle_items.length > 0 ? (
                              <div className="pos-bundle-box">
                                {p.bundle_items.map((bi, idx) => (
                                  <span key={idx} className="pos-bundle-tag">
                                    <strong>{bi.qty}×</strong> {bi.name}
                                  </span>
                                ))}
                              </div>
                            ) : (
                              Array.isArray(p.recipe_summary) &&
                              p.recipe_summary.length > 0 && (
                                <div className="pos-recipe-line">
                                  {p.recipe_summary.slice(0, 3).map((ing, idx) => (
                                    <span key={idx}>
                                      {ing.amount_m}
                                      {ing.unit} {ing.name.replace(' Pasteurisasi', '').replace(' Organik', '')}
                                    </span>
                                  ))}
                                </div>
                              )
                            )}

                            {/* bar stok bahan */}
                            <div className="pos-meter-wrap">
                              <div className="pos-meter-labels">
                                <span>
                                  {p.item_type === 'perporsi' ? 'Stok per porsi' : p.limiting_reason
                                    ? `Bahan: ${p.limiting_reason}`
                                    : 'Stok bahan baku'}
                                </span>
                                <strong>{stockPct}%</strong>
                              </div>
                              <div className="pos-meter-track">
                                <div
                                  className={`pos-meter-fill ${
                                    isLockedProduct ? 'locked' : isWarningProduct ? 'warning' : 'safe'
                                  }`}
                                  style={{ width: `${Math.min(100, Math.max(4, stockPct))}%` }}
                                />
                              </div>
                            </div>

                            {/* harga dan tombol tambah */}
                            <div className="pos-card-foot">
                              <div className="pos-price-block">
                                <small>
                                  {p.item_type === 'paketan'
                                    ? 'Harga Paket'
                                    : isFoodProduct(p) || p.item_type === 'perporsi'
                                    ? 'Harga Porsi'
                                    : 'Regular (M)'}
                                </small>
                                <strong>Rp {Number(p.price_m).toLocaleString('id-ID')}</strong>
                              </div>

                              <button
                                type="button"
                                className={`add-btn ${isAdded ? 'is-added' : ''} ${
                                  isButtonLocked ? 'is-locked-btn' : ''
                                }`}
                                disabled={isButtonLocked}
                                title={
                                  isLockedProduct
                                    ? p.item_type === 'perporsi' ? 'Stok porsi habis' : 'Stok tidak cukup untuk 1 porsi pada ukuran yang tersedia'
                                    : `Tambah ${p.name}`
                                }
                                aria-label={`Tambah ${p.name}`}
                              >
                                {isButtonLocked ? (
                                  <IconLock size={15} />
                                ) : isAdded ? (
                                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <polyline points="20 6 9 17 4 12" />
                                  </svg>
                                ) : (
                                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <line x1="12" y1="5" x2="12" y2="19" />
                                    <line x1="5" y1="12" x2="19" y2="12" />
                                  </svg>
                                )}
                              </button>
                            </div>
                          </div>
                        </article>
                      );
                    })}
                  </div>
                )}
              </div>

              {/* scrim keranjang tablet/hp */}
              <div
                className={`cart-scrim ${cartOpen ? 'open' : ''}`}
                onClick={() => setCartOpen(false)}
              />

              {/* keranjang pesanan */}
              <aside className={`cart-panel ${cartOpen ? 'open' : ''}`}>
                <div className="cart-head">
                  <h3>Ringkasan Pesanan</h3>
                  <span className="cart-count">{totalCartItems} item</span>
                  {cart.length > 0 && (
                    <button
                      type="button"
                      className="cart-clear"
                      onClick={() => {
                        setCart([]);
                        setAppliedPromo(null);
                        setPromoError('');
                      }}
                    >
                      Bersihkan
                    </button>
                  )}
                  <button
                    type="button"
                    className="cart-close-mobile"
                    aria-label="Tutup keranjang"
                    onClick={() => setCartOpen(false)}
                  >
                    ✕
                  </button>
                </div>

                <div className="cart-items">
                  {cart.length === 0 ? (
                    <div className="cart-empty">
                      <span className="empty-icon">
                        <IconBag size={28} />
                      </span>
                      <strong>Belum Ada Pesanan</strong>
                      <p>Pilih menu Satuan atau Paketan di samping untuk mulai mencatat pesanan.</p>
                    </div>
                  ) : (
                    cart.map((item) => {
                      const maxQty = getCartQtyLimit(item);
                      const canIncrement = item.qty < maxQty;

                      return (
                        <div key={item.cartItemId} className="cart-item">
                          <div className="ci-emoji">
                            <ProductIllustration
                              category={item.item_type === 'paketan' ? 'Paketan' : item.category}
                              size={18}
                            />
                          </div>
                          <div className="ci-info">
                            <div className="ci-name">
                              {item.name}
                              {item.item_type === 'paketan' && <span className="ci-type-badge">Paket</span>}
                            </div>
                            <div className="ci-meta">
                              {[
                                productHasSize(item, isFoodProduct(item)) ? `Ukuran ${item.size}` : null,
                                item.sugar ? `Gula ${item.sugar}` : null,
                                item.note || null,
                              ]
                                .filter(Boolean)
                                .join(' • ') || (item.item_type === 'perporsi' ? 'Per Porsi' : isFoodProduct(item) ? 'Makanan' : 'Paket')}
                            </div>
                            <div className="ci-bottom">
                              <span className="ci-price">
                                Rp {(item.unit_price * item.qty).toLocaleString('id-ID')}
                              </span>
                              <div className="qty-control" style={{ marginLeft: 'auto' }}>
                                <button
                                  type="button"
                                  className="qty-btn"
                                  aria-label={`Kurangi jumlah ${item.name} ${item.size}`}
                                  onClick={() => updateCartQty(item.cartItemId, -1)}
                                >
                                  −
                                </button>
                                <QuantityInput
                                  key={`${item.qty}-${maxQty}`}
                                  value={item.qty}
                                  max={maxQty}
                                  label={`Jumlah ${item.name} ${item.size}`}
                                  onCommit={(qty) => setCartQty(item.cartItemId, qty)}
                                />
                                <button
                                  type="button"
                                  className="qty-btn"
                                  aria-label={`Tambah jumlah ${item.name} ${item.size}`}
                                  disabled={!canIncrement}
                                  title={!canIncrement ? 'Mencapai stok tersedia atau batas 500 item' : 'Tambah jumlah'}
                                  onClick={() => updateCartQty(item.cartItemId, 1)}
                                >
                                  +
                                </button>
                              </div>
                            </div>
                          </div>
                        </div>
                      );
                    })
                  )}
                </div>

                {cart.length > 0 && (
                  <div className="cart-summary">
                    <div className="promo-box">
                      {appliedPromo ? (
                        <div className="promo-applied">
                          <span>
                            Promo <strong>{appliedPromo.code}</strong> (-Rp {discount.toLocaleString('id-ID')})
                          </span>
                          <button type="button" onClick={() => setAppliedPromo(null)}>
                            Hapus
                          </button>
                        </div>
                      ) : (
                        <>
                          <div className="promo-row">
                            <input
                              type="text"
                              placeholder="Kode Promo"
                              value={promoCodeInput}
                              onChange={(e) => {
                                setPromoCodeInput(e.target.value);
                                setPromoError('');
                              }}
                            />
                            <button
                              type="button"
                              className="btn btn-secondary btn-sm"
                              onClick={() => handleApplyPromo()}
                            >
                              Terapkan
                            </button>
                          </div>
                          {promoError && (
                            <div className="promo-hint" style={{ color: 'var(--red)' }}>
                              {promoError}
                            </div>
                          )}
                        </>
                      )}
                    </div>

                    <div className="summary-row">
                      <span>Subtotal</span>
                      <span>Rp {subtotal.toLocaleString('id-ID')}</span>
                    </div>
                    {discount > 0 && (
                      <div className="summary-row discount">
                        <span>Potongan Promo</span>
                        <span>-Rp {discount.toLocaleString('id-ID')}</span>
                      </div>
                    )}
                    {tax > 0 && (
                      <div className="summary-row">
                        <span>Pajak ({taxPercent}%)</span>
                        <span>Rp {tax.toLocaleString('id-ID')}</span>
                      </div>
                    )}
                    {service > 0 && (
                      <div className="summary-row">
                        <span>Layanan ({servicePercent}%)</span>
                        <span>Rp {service.toLocaleString('id-ID')}</span>
                      </div>
                    )}
                    <div className="summary-row total">
                      <span>Total Tagihan</span>
                      <span>Rp {total.toLocaleString('id-ID')}</span>
                    </div>

                    <button
                      type="button"
                      className="checkout-btn"
                      onClick={() => {
                        setCheckoutError('');
                        setCartOpen(false);
                        setIsCheckoutOpen(true);
                      }}
                    >
                      <span>Lanjut Pembayaran</span>
                      <span className="btn-cart-total">Rp {total.toLocaleString('id-ID')}</span>
                    </button>
                  </div>
                )}
              </aside>

              {/* tombol keranjang mengambang untuk ipad/hp */}
              <button
                type="button"
                className="floating-cart"
                onClick={() => setCartOpen(true)}
              >
                <IconBag size={17} />
                <span>Pesanan</span>
                <span className="fc-badge">{totalCartItems}</span>
                {total > 0 && (
                  <span className="fc-total">Rp {total.toLocaleString('id-ID')}</span>
                )}
              </button>
            </div>
          )}

          {/* riwayat transaksi */}
          {activeTab === 'history' && (
            <div>
              <div className="section-head">
                <div>

                  <p>Cari pesanan berdasarkan nomor struk, nama menu yang dipesan, atau metode pembayaran.</p>
                </div>
              </div>

              <div className="filter-bar">
                <div className="search-box">
                  <IconSearch size={16} />
                  <input
                    type="text"
                    placeholder="Cari kode KS-..., nama menu, atau kasir..."
                    value={historySearch}
                    onChange={(e) => setHistorySearch(e.target.value)}
                  />
                  {historySearch && (
                    <button type="button" className="search-clear" onClick={() => setHistorySearch('')}>
                      ✕
                    </button>
                  )}
                </div>
                <div className="chips">
                  {[
                    { id: 'all', label: 'Semua Metode' },
                    { id: 'cash', label: 'Tunai (Cash)' },
                    { id: 'qris', label: 'QRIS' },
                  ].map((m) => (
                    <button
                      key={m.id}
                      type="button"
                      className={`chip ${historyMethod === m.id ? 'active' : ''}`}
                      onClick={() => setHistoryMethod(m.id)}
                    >
                      {m.label}
                    </button>
                  ))}
                </div>
                <div className="filter-summary">
                  Menampilkan <strong>{filteredOrders.length}</strong> dari {orders.length} transaksi
                </div>
              </div>

              {filteredOrders.length === 0 ? (
                <div className="card card-pad">
                  <div className="empty">
                    <div className="empty-icon">
                      <IconReceipt size={30} />
                    </div>
                    <strong>Transaksi Tidak Ditemukan</strong>
                    <p>Tidak ada riwayat pesanan yang sesuai dengan pencarian Anda.</p>
                  </div>
                </div>
              ) : (
                <div className="order-list">
                  {filteredOrders.map((o) => {
                    const orderItems = parseOrderItems(o.items);
                    return (
                      <div key={o.id} className="order-card">
                        <div className="order-card-head">
                          <span className="order-code">{o.code}</span>
                          <span className="order-time">{new Date(o.created_at).toLocaleString('id-ID')}</span>
                          <span className={`method-chip ${o.payment_method === 'cash' ? 'method-cash' : 'method-qris'}`}>
                            {o.payment_method?.toUpperCase()}
                          </span>
                          <div className="order-total" style={{ marginLeft: 'auto' }}>
                            Rp {Number(o.total).toLocaleString('id-ID')}
                          </div>
                        </div>
                        {orderItems.length > 0 && (
                          <div className="order-card-body">
                            <div className="order-items-preview">
                              {orderItems.map((it, idx) => (
                                <span key={idx} className="order-item-pill">
                                  {it.name} <span>×{it.qty}</span>
                                </span>
                              ))}
                            </div>
                          </div>
                        )}
                        <div className="order-card-foot">
                          <span className="order-cashier">Kasir: {o.cashier_name}</span>
                          <button
                            type="button"
                            className="btn btn-secondary btn-sm"
                            style={{ marginLeft: 'auto' }}
                            onClick={() => setSelectedReceipt(o)}
                          >
                            <IconPrinter size={15} />
                            <span>Lihat Struk</span>
                          </button>
                        </div>
                      </div>
                    );
                  })}
                </div>
              )}
            </div>
          )}

          {/* promo toko */}
          {activeTab === 'promos' && (
            <div>
              <div className="section-head">
                <div>

                  <p>Tambahkan kode voucher baru, atur diskon persen atau potongan harga, dan terapkan ke pesanan.</p>
                </div>
                <div className="head-action">
                  <button type="button" className="btn btn-primary" onClick={openAddPromoModal}>
                    + Tambah Promo Toko
                  </button>
                </div>
              </div>

              <div className="filter-bar">
                <div className="search-box">
                  <IconSearch size={16} />
                  <input
                    type="text"
                    placeholder="Cari kode promo (misal: NGOPI15, HEMAT10K)..."
                    value={promoSearch}
                    onChange={(e) => setPromoSearch(e.target.value)}
                  />
                  {promoSearch && (
                    <button type="button" className="search-clear" onClick={() => setPromoSearch('')}>
                      ✕
                    </button>
                  )}
                </div>
                <div className="chips">
                  {[
                    { id: 'all', label: 'Semua Promo' },
                    { id: 'percent', label: 'Diskon Persen (%)' },
                    { id: 'fixed', label: 'Potongan Harga (Rp)' },
                  ].map((t) => (
                    <button
                      key={t.id}
                      type="button"
                      className={`chip ${promoFilterType === t.id ? 'active' : ''}`}
                      onClick={() => setPromoFilterType(t.id)}
                    >
                      {t.label}
                    </button>
                  ))}
                </div>
              </div>

              {filteredPromos.length === 0 ? (
                <div className="card card-pad">
                  <div className="empty">
                    <strong>Promo Tidak Ditemukan</strong>
                    <p>Belum ada promo yang sesuai pencarian. Klik "+ Tambah Promo Toko" untuk membuat promo baru.</p>
                  </div>
                </div>
              ) : (
                <div className="promo-grid">
                  {filteredPromos.map((p) => (
                    <div key={p.id} className={`promo-card ${!p.active ? 'promo-inactive' : ''}`}>
                      <span className={`promo-stamp ${!p.active ? 'off' : ''}`}>
                        {p.active ? 'Aktif' : 'Nonaktif'}
                      </span>
                      <span className="promo-code">{p.code}</span>
                      <div className="promo-value">
                        {p.type === 'percent' ? `${p.value}%` : `Rp ${Number(p.value).toLocaleString('id-ID')}`}
                        <small>{p.type === 'percent' ? ' diskon' : ' potongan'}</small>
                      </div>
                      <div className="promo-meta">
                        Minimum belanja Rp {Number(p.min_subtotal).toLocaleString('id-ID')}
                        {Number(p.max_discount) > 0 &&
                          ` • Maks. potongan Rp ${Number(p.max_discount).toLocaleString('id-ID')}`}
                      </div>
                      <div className="promo-card-actions">
                        {p.active && cart.length > 0 && (
                          <button
                            type="button"
                            className="btn btn-primary btn-sm"
                            onClick={() => {
                              handleApplyPromo(p.code);
                              setActiveTab('menu');
                            }}
                          >
                            Pakai Promo
                          </button>
                        )}
                        <button
                          type="button"
                          className="btn btn-secondary btn-sm"
                          onClick={() => openEditPromoModal(p)}
                        >
                          Ubah
                        </button>
                        <button
                          type="button"
                          className="btn btn-soft btn-sm"
                          onClick={() => handleTogglePromoActive(p)}
                        >
                          {p.active ? 'Nonaktifkan' : 'Aktifkan'}
                        </button>
                        <button
                          type="button"
                          className="btn btn-danger btn-sm"
                          onClick={() => handleDeletePromo(p)}
                        >
                          Hapus
                        </button>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          )}

          {/* panduan resep */}
          {activeTab === 'recipes' && (
            <div>
              <div className="section-head">
                <div>

                  <p>Stok bahan baku</p>
                </div>
              </div>

              <div className="filter-bar">
                <div className="search-box">
                  <IconSearch size={16} />
                  <input
                    type="text"
                    placeholder="Cari nama menu atau bahan (misal: espresso, susu, gula aren)..."
                    value={recipeSearch}
                    onChange={(e) => setRecipeSearch(e.target.value)}
                  />
                  {recipeSearch && (
                    <button type="button" className="search-clear" onClick={() => setRecipeSearch('')}>
                      ✕
                    </button>
                  )}
                </div>
                <div className="chips">
                  {categories.map((cat) => (
                    <button
                      key={cat}
                      type="button"
                      className={`chip ${recipeCategory === cat ? 'active' : ''}`}
                      onClick={() => setRecipeCategory(cat)}
                    >
                      {cat}
                    </button>
                  ))}
                </div>
              </div>

              {filteredRecipeProducts.length === 0 ? (
                <div className="card card-pad">
                  <div className="empty">
                    <strong>Resep Tidak Ditemukan</strong>
                    <p>Tidak ada panduan resep yang cocok dengan pencarian Anda.</p>
                  </div>
                </div>
              ) : (
                <div className="recipe-grid">
                  {filteredRecipeProducts.map((prod) => {
                    const recipe = recipes.find((r) => Number(r.product_id) === Number(prod.id));
                    if (!recipe) return null;
                    return (
                      <div key={prod.id} className="card recipe-card">
                        <div className="recipe-card-head">
                          <div
                            className="thumb thumb-photo"
                            style={{
                              background: `linear-gradient(135deg, ${prod.gradient || '#D8B493,#A67C58'})`,
                            }}
                          >
                            <img src={getProductImage(prod)} alt={prod.name} className="thumb-img" loading="lazy" />
                          </div>
                          <div className="recipe-head-info">
                            <span className="product-cat">{prod.category}</span>
                            <h3>{prod.name}</h3>
                          </div>
                          <span className="recipe-time-pill">{recipe.prep_time}</span>
                        </div>
                        <div className="recipe-card-body">
                          <div className="recipe-ing-table-wrap">
                            <table className="recipe-ing-table">
                              <thead>
                                <tr>
                                  <th>Bahan Baku</th>
                                  <th>Regular (M)</th>
                                  <th>Large (L)</th>
                                </tr>
                              </thead>
                              <tbody>
                                {recipe.ingredients.map((ing, i) => (
                                  <tr key={i}>
                                    <td>{ing.name}</td>
                                    <td>
                                      <strong>{ing.amount_m}</strong> {ing.unit}
                                    </td>
                                    <td>
                                      <strong>{ing.amount_l}</strong> {ing.unit}
                                    </td>
                                  </tr>
                                ))}
                              </tbody>
                            </table>
                          </div>
                          {recipe.steps && (
                            <div className="recipe-steps-box">
                              <span className="recipe-box-label">Langkah Penyajian</span>
                              <p>{recipe.steps}</p>
                            </div>
                          )}
                          {recipe.barista_note && (
                            <div className="recipe-note-box">
                              <strong>Catatan Barista:</strong> {recipe.barista_note}
                            </div>
                          )}
                        </div>
                      </div>
                    );
                  })}
                </div>
              )}
            </div>
          )}
        </div>
      </main>

      {/* modal promo */}
      {isPromoModalOpen && (
        <div className="modal-backdrop" onClick={() => setIsPromoModalOpen(false)}>
          <div className="modal-sheet" onClick={(e) => e.stopPropagation()}>
            <div className="modal-head">
              <div>
                <h3>{editingPromoId ? 'Ubah Promo Toko' : 'Tambah Promo Toko Baru'}</h3>
                <p>Atur kode voucher, tipe potongan harga, dan syarat minimum belanja</p>
              </div>
              <button type="button" className="modal-close" onClick={() => setIsPromoModalOpen(false)}>
                ✕
              </button>
            </div>
            <form onSubmit={handleSavePromo}>
              <div className="modal-body">
                <div className="form-grid">
                  <label className="field">
                    <span className="field-label">Kode Promo</span>
                    <input
                      type="text"
                      required
                      maxLength={32}
                      className="input"
                      placeholder="Contoh: KOPIHEMAT20"
                      value={promoFormCode}
                      onChange={(e) => setPromoFormCode(e.target.value.toUpperCase())}
                    />
                  </label>

                  <label className="field">
                    <span className="field-label">Tipe Potongan</span>
                    <select
                      className="select"
                      value={promoFormType}
                      onChange={(e) => setPromoFormType(e.target.value)}
                    >
                      <option value="percent">Diskon Persentase (%)</option>
                      <option value="fixed">Potongan Nominal Tetap (Rp)</option>
                    </select>
                  </label>

                  <label className="field">
                    <span className="field-label">
                      {promoFormType === 'percent' ? 'Besar Diskon (%)' : 'Nominal Potongan (Rp)'}
                    </span>
                    <input
                      type="number"
                      min="1"
                      max={promoFormType === 'percent' ? '100' : '10000000'}
                      required
                      className="input"
                      value={promoFormValue}
                      onChange={(e) => setPromoFormValue(e.target.value)}
                    />
                  </label>

                  <label className="field">
                    <span className="field-label">Minimum Belanja (Rp)</span>
                    <input
                      type="number"
                      min="0"
                      required
                      className="input"
                      value={promoFormMinSubtotal}
                      onChange={(e) => setPromoFormMinSubtotal(e.target.value)}
                    />
                  </label>

                  {promoFormType === 'percent' && (
                    <label className="field span-2">
                      <span className="field-label">Maksimum Potongan Rp (Isi 0 jika tanpa batas)</span>
                      <input
                        type="number"
                        min="0"
                        className="input"
                        value={promoFormMaxDiscount}
                        onChange={(e) => setPromoFormMaxDiscount(e.target.value)}
                      />
                    </label>
                  )}

                  <label className="field span-2" style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                    <input
                      type="checkbox"
                      checked={promoFormActive}
                      onChange={(e) => setPromoFormActive(e.target.checked)}
                      style={{ width: '16px', height: '16px', accentColor: 'var(--coffee-700)' }}
                    />
                    <span className="field-label" style={{ margin: 0 }}>
                      Aktifkan promo ini agar langsung bisa digunakan di kasir
                    </span>
                  </label>
                </div>
              </div>
              <div className="modal-actions">
                <button type="button" className="btn btn-secondary" onClick={() => setIsPromoModalOpen(false)}>
                  Batal
                </button>
                <button type="submit" disabled={savingPromo} className="btn btn-primary">
                  {savingPromo ? 'Menyimpan...' : 'Simpan Promo'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* modal varian menu */}
      {selectedLiveProduct && (
        <div className="modal-backdrop" onClick={() => setSelectedProduct(null)}>
          <div className="modal-sheet" onClick={(e) => e.stopPropagation()}>
            <div className="modal-head">
              <div>
                <h3>Detail Pesanan {selectedLiveProduct.item_type === 'paketan' ? 'Paketan' : 'Menu'}</h3>
                <p>
                  {selectedProductHasSugar
                    ? 'Sesuaikan ukuran, tingkat kemanisan, dan catatan khusus'
                    : 'Sesuaikan porsi dan catatan khusus pesanan'}
                </p>
              </div>
              <button type="button" className="modal-close" onClick={() => setSelectedProduct(null)}>
                ✕
              </button>
            </div>
            <div className="modal-body">
              <div className="pm-hero">
                <div
                  className="pm-visual pm-visual-photo"
                  style={{
                    background: `linear-gradient(135deg, ${
                      selectedLiveProduct.gradient ? selectedLiveProduct.gradient.split(',')[0] : '#D8B493'
                    }, ${selectedLiveProduct.gradient ? selectedLiveProduct.gradient.split(',')[1] : '#A67C58'})`,
                  }}
                >
                  <img
                    src={getProductImage(selectedLiveProduct)}
                    alt={selectedLiveProduct.name}
                    className="pm-photo-img"
                  />
                </div>
                <div>
                  <h4 className="pm-title">{selectedLiveProduct.name}</h4>
                  <p className="pm-desc">{selectedLiveProduct.description}</p>
                  <div className="pm-stock-info">
                    {selectedProductHasSize ? `Stok ukuran ${selectedSize}` : 'Stok porsi'}: <strong>{getOrderableForProduct(selectedLiveProduct, selectedSize)} porsi ({selectedLiveProduct.stock_percent ?? 100}%)</strong> •
                    Dapat dipesan maks. <strong>{selectedProductMaxAdd} porsi</strong> lagi
                  </div>
                </div>
              </div>

              {selectedLiveProduct.item_type === 'paketan' &&
                Array.isArray(selectedLiveProduct.bundle_items) &&
                selectedLiveProduct.bundle_items.length > 0 && (
                  <div className="option-group">
                    <div className="option-label">Komponen Isi Paketan</div>
                    <div className="order-items-preview">
                      {selectedLiveProduct.bundle_items.map((bi, idx) => (
                        <span key={idx} className="order-item-pill">
                          <strong>{bi.qty}×</strong> {bi.name}
                        </span>
                      ))}
                    </div>
                  </div>
                )}

              {selectedProductHasSize ? (
                <div className="option-group">
                  <div className="option-label">Ukuran Cup</div>
                  <div className="size-grid">
                    <button
                      type="button"
                      className={`size-option ${selectedSize === 'M' ? 'active' : ''}`}
                      disabled={getOrderableForProduct(selectedLiveProduct, 'M') < 1}
                      onClick={() => {
                        setSelectedSize('M');
                        setProductQty(normalizeQuantity(productQty, getModalMaxAdd(selectedLiveProduct, 'M')));
                      }}
                    >
                      <span className="size-letter">M</span>
                      <span className="size-text">
                        <strong>Regular (M)</strong>
                        <small>Rp {Number(selectedLiveProduct.price_m).toLocaleString('id-ID')}</small>
                      </span>
                    </button>
                    <button
                      type="button"
                      className={`size-option ${selectedSize === 'L' ? 'active' : ''}`}
                      disabled={getOrderableForProduct(selectedLiveProduct, 'L') < 1}
                      onClick={() => {
                        setSelectedSize('L');
                        setProductQty(normalizeQuantity(productQty, getModalMaxAdd(selectedLiveProduct, 'L')));
                      }}
                    >
                      <span className="size-letter">L</span>
                      <span className="size-text">
                        <strong>Large (L)</strong>
                        <small>Rp {Number(selectedLiveProduct.price_l).toLocaleString('id-ID')}</small>
                      </span>
                    </button>
                  </div>
                </div>
              ) : (
                <div className="option-group">
                  <div className="option-label">
                    {selectedLiveProduct.item_type === 'paketan' ? 'Harga Paket' : 'Harga Porsi'}
                  </div>
                  <div className="pm-price" style={{ marginTop: 0 }}>
                    Rp {Number(selectedLiveProduct.price_m).toLocaleString('id-ID')}
                  </div>
                </div>
              )}

              {selectedProductRecipe && (
                <div className="option-group">
                  <div className="option-label">
                    Pemakaian Bahan Baku Gudang (
                    {selectedProductHasSize ? (selectedSize === 'M' ? 'Regular M' : 'Large L') : 'Standar'} × {selectedQuantity})
                  </div>
                  <div className="order-items-preview">
                    {selectedProductRecipe.ingredients.map((ing, i) => (
                      <span key={i} className="order-item-pill">
                        {ing.name}:{' '}
                        <strong>
                          {(Number(selectedSize === 'M' ? ing.amount_m : ing.amount_l) || 0) * selectedQuantity}{' '}
                          {ing.unit}
                        </strong>
                      </span>
                    ))}
                  </div>
                </div>
              )}

              {selectedProductHasSugar && (
                <div className="option-group">
                  <div className="option-label">Takaran Gula</div>
                  <div className="sugar-grid">
                    {SUGAR_LEVELS.map((sug) => (
                      <button
                        key={sug.value}
                        type="button"
                        className={`sugar-option ${selectedSugar === sug.value ? 'active' : ''}`}
                        onClick={() => setSelectedSugar(sug.value)}
                      >
                        <span className="sugar-value">{sug.value}</span>
                        <span className="sugar-name">{sug.name}</span>
                      </button>
                    ))}
                  </div>
                </div>
              )}

              <div className="qty-and-note">
                <div>
                  <div className="option-label">Jumlah</div>
                  <div className="qty-large">
                    <button
                      type="button"
                      aria-label="Kurangi jumlah pesanan"
                      disabled={selectedQuantity <= 1}
                      onClick={() => setProductQty(normalizeQuantity(selectedQuantity - 1, selectedProductMaxAdd))}
                    >
                      −
                    </button>
                    <QuantityInput
                      key={`${selectedSize}-${selectedQuantity}-${selectedProductMaxAdd}`}
                      value={selectedQuantity}
                      max={selectedProductMaxAdd}
                      label="Jumlah pesanan"
                      onCommit={setProductQty}
                    />
                    <button
                      type="button"
                      aria-label="Tambah jumlah pesanan"
                      disabled={selectedQuantity >= selectedProductMaxAdd}
                      onClick={() => setProductQty(normalizeQuantity(selectedQuantity + 1, selectedProductMaxAdd))}
                    >
                      +
                    </button>
                  </div>
                </div>
                <div className="note-field">
                  <div className="option-label">Catatan Pesanan</div>
                  <input
                    type="text"
                    maxLength={120}
                    placeholder={
                      selectedProductHasSugar
                        ? 'Contoh: Less ice, pisah es'
                        : 'Contoh: Saus pedas, tanpa bawang'
                    }
                    value={productNote}
                    onChange={(e) => setProductNote(e.target.value)}
                  />
                </div>
              </div>
            </div>
            <div className="modal-actions">
              <button type="button" className="btn btn-secondary" onClick={() => setSelectedProduct(null)}>
                Batal
              </button>
              <button
                type="button"
                className="btn btn-primary"
                disabled={selectedProductMaxAdd <= 0}
                onClick={addToCartFromModal}
              >
                Tambah ke Pesanan
              </button>
            </div>
          </div>
        </div>
      )}

      {/* modal pembayaran */}
      {isCheckoutOpen && (
        <div className="modal-backdrop" onClick={() => setIsCheckoutOpen(false)}>
          <div className="modal-sheet" onClick={(e) => e.stopPropagation()}>
            <div className="modal-head">
              <div>
                <h3>Penyelesaian Pembayaran</h3>
                <p>Pilih metode pembayaran dan konfirmasi transaksi</p>
              </div>
              <button type="button" className="modal-close" onClick={() => setIsCheckoutOpen(false)}>
                ✕
              </button>
            </div>
            <div className="modal-body">
              {checkoutError && (
                <div className="login-error show" style={{ marginBottom: '16px' }}>
                  {checkoutError}
                </div>
              )}

              <div className="pay-methods">
                <button
                  type="button"
                  className={`pay-method ${paymentMethod === 'cash' ? 'active' : ''}`}
                  onClick={() => setPaymentMethod('cash')}
                >
                  <span className="pay-icon">
                    <IconWallet size={18} />
                  </span>
                  <div>
                    <span className="pay-name">Tunai (Cash)</span>
                    <span className="pay-desc">Pembayaran uang tunai di kasir</span>
                  </div>
                </button>
                <button
                  type="button"
                  className={`pay-method ${paymentMethod === 'qris' ? 'active' : ''}`}
                  onClick={() => setPaymentMethod('qris')}
                >
                  <span className="pay-icon">
                    <IconQr size={18} />
                  </span>
                  <div>
                    <span className="pay-name">QRIS</span>
                    <span className="pay-desc">Scan kode QR dompet digital / m-banking</span>
                  </div>
                </button>
              </div>

              {paymentMethod === 'cash' ? (
                <div className="cash-section">
                  <div className="option-label">Nominal Uang Diterima (Rp)</div>
                  <input
                    type="number"
                    min="0"
                    className="input"
                    placeholder="Masukkan nominal uang pelanggan"
                    value={cashReceived}
                    onChange={(e) => setCashReceived(e.target.value)}
                  />
                  <div className="quick-cash">
                    {[total, 50000, 100000].map((amt, i) => (
                      <button key={i} type="button" onClick={() => setCashReceived(String(amt))}>
                        {amt === total ? 'Uang Pas' : `Rp ${amt.toLocaleString('id-ID')}`}
                      </button>
                    ))}
                  </div>

                  {Number(cashReceived) >= total && (
                    <div className="change-box">
                      <span>Uang Kembalian</span>
                      <strong>Rp {(Number(cashReceived) - total).toLocaleString('id-ID')}</strong>
                    </div>
                  )}
                </div>
              ) : (
                <div className="qris-box">
                  <span className="qris-label">{settings.qris_merchant || 'VERSI KOPI QRIS'}</span>
                  <div className="qris-amount">Rp {total.toLocaleString('id-ID')}</div>
                  <img
                    src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=VERSIKOPI_PAYMENT"
                    alt="QRIS"
                  />
                  <span className="qris-sub">Silakan arahkan pemindai QR pelanggan ke kode di atas</span>
                </div>
              )}
            </div>
            <div className="modal-actions">
              <button type="button" className="btn btn-secondary" onClick={() => setIsCheckoutOpen(false)}>
                Batal
              </button>
              <button
                type="button"
                className="btn btn-primary"
                disabled={isSubmitting || (paymentMethod === 'cash' && Number(cashReceived) < total)}
                onClick={handleProcessOrder}
              >
                {isSubmitting ? 'Memproses...' : 'Konfirmasi Pembayaran'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* modal struk */}
      {activeReceipt && (
        <div className="modal-backdrop">
          <div className="modal-sheet modal-sm">
            <div className="modal-head no-print">
              <div>
                <h3>Struk Transaksi</h3>
                <p>Bukti pembayaran resmi pelanggan</p>
              </div>
              <button
                type="button"
                className="modal-close"
                onClick={() => {
                  setSuccessOrder(null);
                  setSelectedReceipt(null);
                }}
              >
                ✕
              </button>
            </div>
            <div className="modal-body">
              <div className="receipt">
                <div className="receipt-center">
                  <img src={receiptLogo} alt="" className="receipt-logo" />
                  <h4>{settings.store_name || 'VERSI KOPI'}</h4>
                  <div className="rc-sub">{settings.tagline}</div>
                  <div className="rc-sub">{settings.address}</div>
                  {settings.phone && <div className="rc-sub">Telp: {settings.phone}</div>}
                </div>
                <div className="dashed"></div>
                <div className="receipt-row">
                  <span>No. Pesanan</span>
                  <span className="r">{activeReceipt.code}</span>
                </div>
                <div className="receipt-row">
                  <span>Waktu</span>
                  <span className="r">
                    {activeReceipt.created_at
                      ? new Date(activeReceipt.created_at).toLocaleString('id-ID')
                      : '-'}
                  </span>
                </div>
                <div className="receipt-row">
                  <span>Kasir</span>
                  <span className="r">{activeReceipt.cashier_name || user?.name || 'Kasir'}</span>
                </div>
                <div className="receipt-row">
                  <span>Metode</span>
                  <span className="r">{activeReceipt.payment_method?.toUpperCase()}</span>
                </div>

                {activeReceiptItems.length > 0 && (
                  <>
                    <div className="dashed"></div>
                    {activeReceiptItems.map((it, idx) => (
                      <div key={idx} className="receipt-item">
                        <div className="receipt-row">
                          <span className="receipt-item-name">
                            {it.name}
                            {productHasSize(it, isFoodProduct(it)) ? ` (${it.size || 'M'})` : ''} ×{it.qty}
                          </span>
                          <span className="r">
                            Rp {(Number(it.unit_price || 0) * Number(it.qty || 1)).toLocaleString('id-ID')}
                          </span>
                        </div>
                        {(it.sugar || it.note) && (
                          <div className="receipt-item-meta">
                            {[it.sugar ? `Gula ${it.sugar}` : null, it.note || null].filter(Boolean).join(' • ')}
                          </div>
                        )}
                      </div>
                    ))}
                  </>
                )}

                <div className="dashed"></div>
                {Number(activeReceipt.discount) > 0 && (
                  <div className="receipt-row">
                    <span>Diskon</span>
                    <span className="r">-Rp {Number(activeReceipt.discount).toLocaleString('id-ID')}</span>
                  </div>
                )}
                {Number(activeReceipt.tax) > 0 && (
                  <div className="receipt-row">
                    <span>Pajak</span>
                    <span className="r">Rp {Number(activeReceipt.tax).toLocaleString('id-ID')}</span>
                  </div>
                )}
                <div className="receipt-row receipt-total">
                  <span>TOTAL</span>
                  <span className="r">Rp {Number(activeReceipt.total).toLocaleString('id-ID')}</span>
                </div>
                {activeReceipt.payment_method === 'cash' && Number(activeReceipt.cash_received) > 0 && (
                  <>
                    <div className="receipt-row">
                      <span>Tunai</span>
                      <span className="r">Rp {Number(activeReceipt.cash_received).toLocaleString('id-ID')}</span>
                    </div>
                    <div className="receipt-row">
                      <span>Kembali</span>
                      <span className="r">Rp {Number(activeReceipt.change_amount || 0).toLocaleString('id-ID')}</span>
                    </div>
                  </>
                )}
                <div className="dashed"></div>
                <div className="receipt-foot">
                  {settings.receipt_footer || 'Terima kasih telah berkunjung'}
                </div>
              </div>
            </div>
            <div className="modal-actions no-print">
              <button type="button" className="btn btn-primary" onClick={() => window.print()}>
                <IconPrinter size={16} />
                <span>Cetak Struk</span>
              </button>
              <button
                type="button"
                className="btn btn-secondary"
                onClick={() => {
                  setSuccessOrder(null);
                  setSelectedReceipt(null);
                }}
              >
                Tutup
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
