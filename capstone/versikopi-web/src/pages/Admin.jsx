import React, { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';
import api from '../lib/api';
import {
  IconChart,
  IconCup,
  IconBox,
  IconSettings,
  IconWallet,
  IconReceipt,
  IconBook,
  IconTag,
  IconSearch,
  IconLock,
  IconAlert,
  IconLayers,
  ProductIllustration,
} from '../components/Icons';
import { getProductImage, PRODUCT_IMAGES_CATALOG } from '../lib/productImages';
import logo from '../assets/logo.png';

const GRADIENTS = [
  { label: 'Karamel Lembut', value: '#D8B493,#A67C58' },
  { label: 'Moka Hangat', value: '#C4A484,#8A684C' },
  { label: 'Matcha Sage', value: '#B7C6A5,#7A9068' },
  { label: 'Rose Berry', value: '#E2A3A3,#B46A74' },
  { label: 'Madu Gandum', value: '#E4BE8E,#B8864B' },
  { label: 'Susu Cokelat', value: '#D2B49C,#98765C' },
];

const CATEGORIES = ['Signature', 'Coffee', 'Non-Coffee', 'Pastry', 'Snack', 'Bundling'];
const RECIPE_UNITS = ['ml', 'gr', 'shot', 'pump', 'pcs', 'sdm', 'sdt'];

export default function Admin() {
  const { user, logout } = useAuth();
  const [activeTab, setActiveTab] = useState('dashboard');

  const [products, setProducts] = useState([]);
  const [ingredients, setIngredients] = useState([]);
  const [promos, setPromos] = useState([]);
  const [recipes, setRecipes] = useState([]);
  const [orders, setOrders] = useState([]);
  const [inventoryLogs, setInventoryLogs] = useState([]);
  const [settings, setSettings] = useState({});
  const [loading, setLoading] = useState(true);
  const [notice, setNotice] = useState(null);

  // filter pencarian admin
  const [dashOrderSearch, setDashOrderSearch] = useState('');
  const [productSearch, setProductSearch] = useState('');
  const [productTypeFilter, setProductTypeFilter] = useState('all');
  const [productCategoryFilter, setProductCategoryFilter] = useState('Semua');
  const [productStockFilter, setProductStockFilter] = useState('all');
  const [promoSearch, setPromoSearch] = useState('');
  const [promoFilterType, setPromoFilterType] = useState('all');
  const [recipeSearch, setRecipeSearch] = useState('');
  const [recipeStatusFilter, setRecipeStatusFilter] = useState('all');
  const [ingSearch, setIngSearch] = useState('');
  const [invLogSearch, setInventoryLogSearch] = useState('');
  const [invLogTypeFilter, setInvLogTypeFilter] = useState('all');

  // form menu
  const [isProductModalOpen, setIsProductModalOpen] = useState(false);
  const [editingProductId, setEditingProductId] = useState(null);
  const [newProductItemType, setNewProductItemType] = useState('satuan');
  const [newProductName, setNewProductName] = useState('');
  const [newProductCategory, setNewProductCategory] = useState(CATEGORIES[0]);
  const [newProductPriceM, setNewProductPriceM] = useState(20000);
  const [newProductPriceL, setNewProductPriceL] = useState(25000);
  const [newProductDesc, setNewProductDesc] = useState('');
  const [newProductGradient, setNewProductGradient] = useState(GRADIENTS[0].value);
  const [newProductImageUrl, setNewProductImageUrl] = useState(PRODUCT_IMAGES_CATALOG[0].url);
  const [newProductBundleItems, setNewProductBundleItems] = useState([
    { product_id: '', name: '', qty: 1 },
  ]);
  const [savingProduct, setSavingProduct] = useState(false);

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

  // form resep
  const [isRecipeModalOpen, setIsRecipeModalOpen] = useState(false);
  const [recipeProductId, setRecipeProductId] = useState('');
  const [recipePrepTime, setRecipePrepTime] = useState('2 Menit');
  const [recipeIngredients, setRecipeIngredients] = useState([
    { name: '', amount_m: '', amount_l: '', unit: 'ml' },
  ]);
  const [recipeSteps, setRecipeSteps] = useState('');
  const [recipeNote, setRecipeNote] = useState('');
  const [savingRecipe, setSavingRecipe] = useState(false);

  // form stok bahan gudang
  const [restockActionMode, setRestockActionMode] = useState('add');
  const [restockIngName, setRestockIngName] = useState('');
  const [restockIngAmount, setRestockIngAmount] = useState('');
  const [restockIngUnit, setRestockIngUnit] = useState('ml');
  const [restockIngNote, setRestockIngNote] = useState('');
  const [savingIngRestock, setSavingIngRestock] = useState(false);

  // form pengaturan toko
  const [settingsForm, setSettingsForm] = useState({
    store_name: 'VERSI KOPI',
    tagline: '',
    phone: '',
    address: '',
    tax_percent: '0',
    service_percent: '0',
    qris_merchant: 'Versi Kopi',
    receipt_footer: '',
  });
  const [savingSettings, setSavingSettings] = useState(false);

  const showNotice = (text, type = 'success') => {
    setNotice({ text, type });
    setTimeout(() => {
      setNotice((prev) => (prev?.text === text ? null : prev));
    }, 4000);
  };

  const loadData = async () => {
    try {
      const [resProd, resIng, resPromo, resRec, resOrd, resSet, resLogs] = await Promise.all([
        api.get('/products').then((r) => r.data).catch(() => []),
        api.get('/ingredients').then((r) => r.data).catch(() => ({ ingredients: [] })),
        api.get('/promos?all=1').then((r) => r.data).catch(() => []),
        api.get('/recipes').then((r) => r.data).catch(() => ({ recipes: [] })),
        api.get('/orders').then((r) => r.data).catch(() => ({ orders: [] })),
        api.get('/settings').then((r) => r.data).catch(() => ({})),
        api.get('/inventory-logs').then((r) => r.data).catch(() => ({ logs: [] })),
      ]);

      const prodList = Array.isArray(resProd) ? resProd : [];
      const ingList = Array.isArray(resIng?.ingredients) ? resIng.ingredients : [];
      setProducts(prodList);
      setIngredients(ingList);
      setPromos(Array.isArray(resPromo) ? resPromo : []);
      setRecipes(Array.isArray(resRec?.recipes) ? resRec.recipes : []);
      setOrders(resOrd.orders || []);
      setInventoryLogs(resLogs.logs || []);
      setSettings(resSet || {});

      setSettingsForm({
        store_name: resSet?.store_name || 'VERSI KOPI',
        tagline: resSet?.tagline || 'Secangkir cerita di setiap tegukan',
        phone: resSet?.phone || '0812-3456-7890',
        address: resSet?.address || 'Jl. Senja Raya No. 17, Bandung',
        tax_percent: String(resSet?.tax_percent ?? '0'),
        service_percent: String(resSet?.service_percent ?? '0'),
        qris_merchant: resSet?.qris_merchant || 'Versi Kopi',
        receipt_footer: resSet?.receipt_footer || 'Terima kasih telah berkunjung ke Versi Kopi',
      });

      if (ingList.length > 0 && !restockIngName) {
        setRestockIngName(ingList[0].name);
        setRestockIngUnit(ingList[0].unit || 'ml');
      }
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadData();
  }, []);

  // statistik dan status stok
  const totalRevenue = orders.reduce((sum, o) => sum + Number(o.total || 0), 0);
  const totalOrders = orders.length;
  const satuanProducts = products.filter((p) => (p.item_type || 'satuan') === 'satuan');
  const paketanProducts = products.filter((p) => p.item_type === 'paketan');

  const lockedProducts = products.filter(
    (p) => p.is_locked || Number(p.stock) <= 3 || Number(p.stock_percent ?? 100) <= 5
  );
  const warningProducts = products.filter(
    (p) =>
      !lockedProducts.some((lp) => lp.id === p.id) &&
      (p.is_warning || Number(p.stock) <= 15 || Number(p.stock_percent ?? 100) <= 20)
  );
  const alertIngredients = ingredients.filter((ing) => ing.is_locked || ing.is_warning);

  // filter pesanan terbaru
  const filteredDashOrders = orders.filter((o) => {
    const q = dashOrderSearch.trim().toLowerCase();
    if (!q) return true;
    return (
      (o.code || '').toLowerCase().includes(q) ||
      (o.cashier_name || '').toLowerCase().includes(q) ||
      (o.payment_method || '').toLowerCase().includes(q)
    );
  });

  // filter daftar menu
  const filteredAdminProducts = products.filter((p) => {
    const pType = p.item_type || 'satuan';
    const matchType = productTypeFilter === 'all' || pType === productTypeFilter;
    const matchCat = productCategoryFilter === 'Semua' || p.category === productCategoryFilter;

    const isLocked = p.is_locked || Number(p.stock) <= 3 || Number(p.stock_percent ?? 100) <= 5;
    const isWarn = !isLocked && (p.is_warning || Number(p.stock) <= 15 || Number(p.stock_percent ?? 100) <= 20);
    const matchStock =
      productStockFilter === 'all' ||
      (productStockFilter === 'locked' && isLocked) ||
      (productStockFilter === 'warning' && (isWarn || isLocked));

    const q = productSearch.trim().toLowerCase();
    const matchSearch =
      !q ||
      p.name.toLowerCase().includes(q) ||
      (p.description || '').toLowerCase().includes(q) ||
      (p.category || '').toLowerCase().includes(q);
    return matchType && matchCat && matchStock && matchSearch;
  });

  // filter promo
  const filteredPromos = promos.filter((p) => {
    const matchType = promoFilterType === 'all' || p.type === promoFilterType;
    const q = promoSearch.trim().toLowerCase();
    const matchSearch = !q || (p.code || '').toLowerCase().includes(q);
    return matchType && matchSearch;
  });

  // filter resep
  const filteredRecipeProducts = products.filter((prod) => {
    const rec = recipes.find((r) => Number(r.product_id) === Number(prod.id));
    if (recipeStatusFilter === 'configured' && !rec) return false;
    if (recipeStatusFilter === 'unconfigured' && rec) return false;

    const q = recipeSearch.trim().toLowerCase();
    if (!q) return true;
    const matchName = prod.name.toLowerCase().includes(q) || (prod.category || '').toLowerCase().includes(q);
    const matchIng = rec?.ingredients?.some((ing) => (ing.name || '').toLowerCase().includes(q));
    const matchSteps = (rec?.steps || '').toLowerCase().includes(q);
    return matchName || matchIng || matchSteps;
  });

  // filter bahan gudang
  const filteredIngredients = ingredients.filter((ing) => {
    const q = ingSearch.trim().toLowerCase();
    if (!q) return true;
    const matchName = (ing.name || '').toLowerCase().includes(q);
    const matchUsedBy = (ing.used_by || []).some((u) => (u.product_name || '').toLowerCase().includes(q));
    return matchName || matchUsedBy;
  });

  // filter riwayat stok
  const filteredInventoryLogs = inventoryLogs.filter((log) => {
    const matchType = invLogTypeFilter === 'all' || log.type === invLogTypeFilter;
    const q = invLogSearch.trim().toLowerCase();
    if (!q) return matchType;
    return (
      matchType &&
      ((log.product_name || '').toLowerCase().includes(q) ||
        (log.note || '').toLowerCase().includes(q) ||
        (log.reference || '').toLowerCase().includes(q) ||
        (log.actor_name || '').toLowerCase().includes(q))
    );
  });

  // kelola menu
  const openAddProductModal = (defaultType = 'satuan') => {
    setEditingProductId(null);
    setNewProductItemType(defaultType);
    setNewProductName('');
    setNewProductCategory(defaultType === 'paketan' ? 'Bundling' : CATEGORIES[0]);
    setNewProductPriceM(defaultType === 'paketan' ? 20000 : 15000);
    setNewProductPriceL(defaultType === 'paketan' ? 23000 : 18000);
    setNewProductDesc('');
    setNewProductGradient(GRADIENTS[0].value);
    setNewProductImageUrl(
      defaultType === 'paketan' ? '/products/paket-burger-kentang.jpg' : '/products/kopi-susu-versi.jpg'
    );
    const firstTwo = satuanProducts.slice(0, 2);
    setNewProductBundleItems(
      firstTwo.length > 0
        ? firstTwo.map((p) => ({ product_id: p.id, name: p.name, qty: 1 }))
        : [{ product_id: '', name: '', qty: 1 }]
    );
    setIsProductModalOpen(true);
  };

  const openEditProductModal = (prod) => {
    setEditingProductId(prod.id);
    setNewProductItemType(prod.item_type || 'satuan');
    setNewProductName(prod.name);
    setNewProductCategory(prod.category);
    setNewProductPriceM(prod.price_m);
    setNewProductPriceL(prod.price_l);
    setNewProductDesc(prod.description || '');
    setNewProductGradient(prod.gradient || GRADIENTS[0].value);
    setNewProductImageUrl(getProductImage(prod) || PRODUCT_IMAGES_CATALOG[0].url);
    setNewProductBundleItems(
      Array.isArray(prod.bundle_items) && prod.bundle_items.length > 0
        ? prod.bundle_items.map((b) => ({
            product_id: b.product_id || '',
            name: b.name || '',
            qty: Number(b.qty || 1),
          }))
        : [{ product_id: satuanProducts[0]?.id || '', name: satuanProducts[0]?.name || '', qty: 1 }]
    );
    setIsProductModalOpen(true);
  };

  const updateBundleRow = (idx, field, val) => {
    setNewProductBundleItems((prev) =>
      prev.map((row, i) => {
        if (i !== idx) return row;
        if (field === 'product_id') {
          const found = satuanProducts.find((sp) => Number(sp.id) === Number(val));
          return {
            ...row,
            product_id: found ? found.id : '',
            name: found ? found.name : row.name,
          };
        }
        return { ...row, [field]: val };
      })
    );
  };

  const addBundleRow = () => {
    const first = satuanProducts[0];
    setNewProductBundleItems((prev) => [
      ...prev,
      { product_id: first?.id || '', name: first?.name || '', qty: 1 },
    ]);
  };

  const removeBundleRow = (idx) => {
    setNewProductBundleItems((prev) => (prev.length > 1 ? prev.filter((_, i) => i !== idx) : prev));
  };

  const handleSaveProduct = async (e) => {
    e.preventDefault();
    setSavingProduct(true);

    const validBundleItems =
      newProductItemType === 'paketan'
        ? newProductBundleItems
            .filter((b) => b.product_id)
            .map((b) => ({
              product_id: Number(b.product_id),
              name: b.name,
              qty: Math.max(1, Number(b.qty || 1)),
            }))
        : [];

    const payload = {
      name: newProductName,
      category: newProductItemType === 'paketan' ? 'Bundling' : newProductCategory,
      item_type: newProductItemType,
      bundle_items: validBundleItems,
      stock: 60,
      max_stock: 60,
      price_m: Number(newProductPriceM),
      price_l: Number(newProductPriceL),
      description: newProductDesc,
      gradient: newProductGradient,
      image_url: newProductImageUrl,
    };

    try {
      if (editingProductId) {
        await api.put(`/products/${editingProductId}`, payload);
        showNotice('Data menu berhasil diperbarui.');
      } else {
        await api.post('/products', payload);
        showNotice('Menu baru berhasil ditambahkan. Stok otomatis mengikuti bahan baku gudang.');
      }
      setIsProductModalOpen(false);
      loadData();
    } catch (err) {
      const msg =
        err.response?.data?.message ||
        Object.values(err.response?.data?.errors || {})[0]?.[0] ||
        'Gagal menyimpan data menu.';
      showNotice(msg, 'error');
    } finally {
      setSavingProduct(false);
    }
  };

  const handleDeleteProduct = async (prod) => {
    if (!window.confirm(`Nonaktifkan menu "${prod.name}" dari daftar kasir?`)) return;
    try {
      await api.delete(`/products/${prod.id}`);
      showNotice(`Menu ${prod.name} telah dinonaktifkan.`);
      loadData();
    } catch {
      showNotice('Gagal menonaktifkan menu.', 'error');
    }
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
      loadData();
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
      loadData();
    } catch {
      showNotice('Gagal mengubah status promo.', 'error');
    }
  };

  const handleDeletePromo = async (promo) => {
    if (!window.confirm(`Hapus kode promo "${promo.code}" secara permanen?`)) return;
    try {
      await api.delete(`/promos/${promo.id}`);
      showNotice(`Promo ${promo.code} berhasil dihapus.`);
      loadData();
    } catch {
      showNotice('Gagal menghapus promo.', 'error');
    }
  };

  // kelola resep
  const openRecipeModalForProduct = (prodId) => {
    const targetId = Number(prodId || products[0]?.id || 0);
    setRecipeProductId(targetId);

    const existing = recipes.find((r) => Number(r.product_id) === targetId);
    if (existing) {
      setRecipePrepTime(existing.prep_time || '2 Menit');
      setRecipeIngredients(
        existing.ingredients?.length > 0
          ? existing.ingredients.map((i) => ({ ...i }))
          : [{ name: '', amount_m: '', amount_l: '', unit: 'ml' }]
      );
      setRecipeSteps(existing.steps || '');
      setRecipeNote(existing.barista_note || '');
    } else {
      setRecipePrepTime('2 Menit');
      setRecipeIngredients([
        { name: 'Espresso House Blend', amount_m: '30', amount_l: '45', unit: 'ml' },
        { name: 'Susu Segar Pasteurisasi', amount_m: '120', amount_l: '160', unit: 'ml' },
      ]);
      setRecipeSteps('');
      setRecipeNote('');
    }

    setIsRecipeModalOpen(true);
  };

  const handleSelectRecipeProductChange = (newProdId) => {
    const idNum = Number(newProdId);
    setRecipeProductId(idNum);
    const existing = recipes.find((r) => Number(r.product_id) === idNum);
    if (existing) {
      setRecipePrepTime(existing.prep_time || '2 Menit');
      setRecipeIngredients(
        existing.ingredients?.length > 0
          ? existing.ingredients.map((i) => ({ ...i }))
          : [{ name: '', amount_m: '', amount_l: '', unit: 'ml' }]
      );
      setRecipeSteps(existing.steps || '');
      setRecipeNote(existing.barista_note || '');
    } else {
      setRecipePrepTime('2 Menit');
      setRecipeIngredients([{ name: '', amount_m: '', amount_l: '', unit: 'ml' }]);
      setRecipeSteps('');
      setRecipeNote('');
    }
  };

  const updateIngredientRow = (index, field, value) => {
    setRecipeIngredients((prev) =>
      prev.map((row, idx) => (idx === index ? { ...row, [field]: value } : row))
    );
  };

  const addIngredientRow = () => {
    setRecipeIngredients((prev) => [...prev, { name: '', amount_m: '', amount_l: '', unit: 'ml' }]);
  };

  const removeIngredientRow = (index) => {
    setRecipeIngredients((prev) => (prev.length > 1 ? prev.filter((_, idx) => idx !== index) : prev));
  };

  const handleSaveRecipe = async (e) => {
    e.preventDefault();
    const validIngredients = recipeIngredients.filter(
      (i) => i.name.trim() && i.amount_m.toString().trim() && i.amount_l.toString().trim()
    );

    if (!recipeProductId || validIngredients.length === 0) {
      showNotice('Pilih produk dan isi minimal satu bahan beserta takaran M & L.', 'error');
      return;
    }

    setSavingRecipe(true);
    try {
      const { data } = await api.post('/recipes', {
        product_id: Number(recipeProductId),
        prep_time: recipePrepTime,
        ingredients: validIngredients,
        steps: recipeSteps,
        barista_note: recipeNote,
      });
      showNotice(data.message || 'Resep berhasil disimpan.');
      setIsRecipeModalOpen(false);
      loadData();
    } catch (err) {
      const msg =
        err.response?.data?.message ||
        Object.values(err.response?.data?.errors || {})[0]?.[0] ||
        'Gagal menyimpan resep.';
      showNotice(msg, 'error');
    } finally {
      setSavingRecipe(false);
    }
  };

  const handleDeleteRecipe = async (productId, productName) => {
    if (!window.confirm(`Hapus resep untuk menu "${productName}"?`)) return;
    try {
      await api.delete(`/recipes/${productId}`);
      showNotice(`Resep ${productName} berhasil dihapus.`);
      loadData();
    } catch {
      showNotice('Gagal menghapus resep.', 'error');
    }
  };

  // kelola stok bahan gudang
  const handleRestockIngredient = async (e) => {
    e?.preventDefault();
    if (!restockIngName.trim() || !restockIngAmount || Number(restockIngAmount) <= 0) {
      showNotice('Masukkan nama bahan dan jumlah stok (gr/ml) yang valid.', 'error');
      return;
    }
    setSavingIngRestock(true);
    try {
      const { data } = await api.post('/ingredients/restock', {
        name: restockIngName.trim(),
        amount: Number(restockIngAmount),
        unit: restockIngUnit,
        mode: restockActionMode,
        note:
          restockIngNote.trim() ||
          (restockActionMode === 'set'
            ? `Atur kapasitas penuh 100% bahan ${restockIngName}`
            : `Tambah stok bahan ${restockIngName} ke gudang`),
      });
      showNotice(data.message || `Stok bahan ${restockIngName} berhasil diperbarui.`);
      setRestockIngAmount('');
      setRestockIngNote('');
      loadData();
    } catch (err) {
      const msg = err.response?.data?.message || 'Gagal memperbarui stok bahan baku gudang.';
      showNotice(msg, 'error');
    } finally {
      setSavingIngRestock(false);
    }
  };

  const handleSaveSettings = async (e) => {
    e.preventDefault();
    setSavingSettings(true);
    try {
      await api.put('/settings', {
        ...settingsForm,
        tax_percent: Number(settingsForm.tax_percent || 0),
        service_percent: Number(settingsForm.service_percent || 0),
      });
      showNotice('Pengaturan toko berhasil disimpan.');
      loadData();
    } catch (err) {
      const msg = err.response?.data?.message || 'Gagal menyimpan pengaturan toko.';
      showNotice(msg, 'error');
    } finally {
      setSavingSettings(false);
    }
  };

  // banner peringatan stok
  const renderStockAlertBanner = () => {
    if (lockedProducts.length === 0 && warningProducts.length === 0 && alertIngredients.length === 0) {
      return null;
    }
    return (
      <div className="stock-alert-panel">
        <div className="stock-alert-head">
          <span className="stock-alert-icon">
            <IconAlert size={18} />
          </span>
          <div>
            <strong>Peringatan Stok Bahan Baku</strong>
            <p>Stok bahan baku</p>
          </div>
          {activeTab !== 'restock' && (
            <button
              type="button"
              className="btn btn-secondary btn-sm"
              style={{ marginLeft: 'auto' }}
              onClick={() => setActiveTab('restock')}
            >
              Isi Ulang Bahan Gudang
            </button>
          )}
        </div>

        <div className="stock-alert-groups">
          {alertIngredients.length > 0 && (
            <div className="stock-alert-group raw">
              <span className="stock-alert-label">Bahan Baku Gudang Menipis / Kritis (gr / ml)</span>
              <div className="stock-alert-pills">
                {alertIngredients.map((ing) => (
                  <span
                    key={ing.id}
                    className={`stock-alert-pill ${ing.is_locked ? 'is-locked' : 'is-warning'}`}
                  >
                    <strong>{ing.name}</strong>: sisa {Number(ing.stock_amount).toLocaleString('id-ID')}{' '}
                    {ing.unit} ({ing.stock_percent}%)
                  </span>
                ))}
              </div>
            </div>
          )}

          {lockedProducts.length > 0 && (
            <div className="stock-alert-group locked">
              <span className="stock-alert-label">
                <IconLock size={13} /> Menu Terkunci di Kasir (≤ 5% atau ≤ 3 porsi)
              </span>
              <div className="stock-alert-pills">
                {lockedProducts.map((p) => (
                  <span key={p.id} className="stock-alert-pill is-locked">
                    <strong>{p.name}</strong> • Sisa {p.stock} porsi ({p.stock_percent ?? 0}%)
                    {p.limiting_reason && <small> — {p.limiting_reason}</small>}
                  </span>
                ))}
              </div>
            </div>
          )}

          {warningProducts.length > 0 && (
            <div className="stock-alert-group warning">
              <span className="stock-alert-label">Menu Peringatan Menipis (≤ 20% atau ≤ 15 porsi)</span>
              <div className="stock-alert-pills">
                {warningProducts.map((p) => (
                  <span key={p.id} className="stock-alert-pill is-warning">
                    <strong>{p.name}</strong> • Sisa {p.stock} porsi ({p.stock_percent ?? 0}%)
                    {p.limiting_reason && <small> — {p.limiting_reason}</small>}
                  </span>
                ))}
              </div>
            </div>
          )}
        </div>
      </div>
    );
  };

  return (
    <div className="app">
      {/* sidebar admin */}
      <aside className="sidebar">
        <div className="sidebar-brand">
          <div className="logo-ring small">
            <img src={logo} alt="Logo" />
          </div>
          <div className="sidebar-brand-text">
            <strong>{settings.store_name || 'VERSI KOPI'}</strong>
            <small>Administrator</small>
          </div>
        </div>

        <nav className="nav">
          <button
            type="button"
            className={`nav-item ${activeTab === 'dashboard' ? 'active' : ''}`}
            onClick={() => setActiveTab('dashboard')}
          >
            <span className="nav-icon">
              <IconChart size={18} />
            </span>
            <span>Ringkasan</span>
          </button>
          <button
            type="button"
            className={`nav-item ${activeTab === 'products' ? 'active' : ''}`}
            onClick={() => setActiveTab('products')}
          >
            <span className="nav-icon">
              <IconCup size={18} />
            </span>
            <span>Kelola Menu</span>
            <span className="nav-badge">{products.length}</span>
          </button>
          <button
            type="button"
            className={`nav-item ${activeTab === 'promos' ? 'active' : ''}`}
            onClick={() => setActiveTab('promos')}
          >
            <span className="nav-icon">
              <IconTag size={18} />
            </span>
            <span>Promo Toko</span>
            <span className="nav-badge">{promos.length}</span>
          </button>
          <button
            type="button"
            className={`nav-item ${activeTab === 'recipes' ? 'active' : ''}`}
            onClick={() => setActiveTab('recipes')}
          >
            <span className="nav-icon">
              <IconBook size={18} />
            </span>
            <span>Resep & Takaran</span>
            <span className="nav-badge">{recipes.length}</span>
          </button>
          <button
            type="button"
            className={`nav-item ${activeTab === 'restock' ? 'active' : ''}`}
            onClick={() => setActiveTab('restock')}
          >
            <span className="nav-icon">
              <IconBox size={18} />
            </span>
            <span>Stok Bahan Gudang</span>
            {(lockedProducts.length > 0 || warningProducts.length > 0 || alertIngredients.length > 0) && (
              <span className="nav-badge">{ingredients.length}</span>
            )}
          </button>
          <button
            type="button"
            className={`nav-item ${activeTab === 'settings' ? 'active' : ''}`}
            onClick={() => setActiveTab('settings')}
          >
            <span className="nav-icon">
              <IconSettings size={18} />
            </span>
            <span>Pengaturan Toko</span>
          </button>
        </nav>

        <div className="sidebar-foot">
          <button className="btn btn-ghost btn-block btn-sm" onClick={logout}>
            Keluar Sesi
          </button>
        </div>
      </aside>

      <main className="app-main">
        <header className="topbar">
          <div className="topbar-title">
            <h1>
              {activeTab === 'dashboard'
                ? 'Ringkasan Operasional'
                : activeTab === 'products'
                ? 'Kelola Menu'
                : activeTab === 'promos'
                ? 'Promo Toko'
                : activeTab === 'recipes'
                ? 'Resep & Takaran'
                : activeTab === 'restock'
                ? 'Stok Bahan Baku'
                : 'Pengaturan Toko'}
            </h1>
            <p>Stok bahan baku</p>
          </div>
          <div className="topbar-actions">
            <div className="user-chip">
              <div className="avatar">{(user?.name || 'A').charAt(0).toUpperCase()}</div>
              <div className="user-chip-text">
                <strong>{user?.name || 'Admin'}</strong>
                <small>Owner Kedai</small>
              </div>
            </div>
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
          {/* ringkasan operasional */}
          {activeTab === 'dashboard' && (
            <div>
              {renderStockAlertBanner()}

              <div className="stat-grid">
                <div className="stat-card">
                  <div className="stat-top">
                    <span className="stat-icon">
                      <IconWallet size={16} />
                    </span>
                    <span className="stat-label">Total Pendapatan</span>
                  </div>
                  <div className="stat-value">Rp {totalRevenue.toLocaleString('id-ID')}</div>
                  <div className="stat-sub">Akumulasi dari {totalOrders} transaksi</div>
                </div>

                <div className="stat-card">
                  <div className="stat-top">
                    <span className="stat-icon">
                      <IconCup size={16} />
                    </span>
                    <span className="stat-label">Menu Satuan & Paketan</span>
                  </div>
                  <div className="stat-value">
                    {satuanProducts.length} / {paketanProducts.length}
                  </div>
                  <div className="stat-sub">
                    {satuanProducts.length} menu satuan • {paketanProducts.length} menu paketan
                  </div>
                </div>

                <div className="stat-card">
                  <div className="stat-top">
                    <span className="stat-icon">
                      <IconBox size={16} />
                    </span>
                    <span className="stat-label">Bahan Baku Gudang</span>
                  </div>
                  <div className="stat-value">{ingredients.length} Bahan</div>
                  <div className="stat-sub">Stok bahan baku</div>
                </div>

                <div className="stat-card">
                  <div className="stat-top">
                    <span className="stat-icon">
                      <IconAlert size={16} />
                    </span>
                    <span className="stat-label">Peringatan / Terkunci</span>
                  </div>
                  <div className="stat-value">
                    {warningProducts.length} / {lockedProducts.length}
                  </div>
                  <div className="stat-sub">
                    {warningProducts.length} menipis (≤20%) • {lockedProducts.length} terkunci (≤5%)
                  </div>
                </div>
              </div>

              {/* transaksi terbaru */}
              <div className="card">
                <div className="card-head" style={{ flexWrap: 'wrap', gap: '16px' }}>
                  <div>
                    <h3>Transaksi Kasir Terbaru</h3>
                    <p>Cari atau pantau catatan penjualan yang masuk ke sistem</p>
                  </div>
                  <div className="head-action" style={{ minWidth: '260px' }}>
                    <div className="search-box">
                      <IconSearch size={16} />
                      <input
                        type="text"
                        placeholder="Cari kode order, kasir, metode..."
                        value={dashOrderSearch}
                        onChange={(e) => setDashOrderSearch(e.target.value)}
                      />
                      {dashOrderSearch && (
                        <button type="button" className="search-clear" onClick={() => setDashOrderSearch('')}>
                          ✕
                        </button>
                      )}
                    </div>
                  </div>
                </div>
                <div className="recent-orders">
                  {filteredDashOrders.length === 0 ? (
                    <div className="empty">
                      <strong>Transaksi Tidak Ditemukan</strong>
                      <p>Belum ada data transaksi yang cocok dengan pencarian.</p>
                    </div>
                  ) : (
                    filteredDashOrders.slice(0, 12).map((o) => (
                      <div key={o.id} className="recent-order">
                        <span className="ro-code">{o.code}</span>
                        <span className="ro-time">{new Date(o.created_at).toLocaleString('id-ID')}</span>
                        <span className={`method-chip ${o.payment_method === 'cash' ? 'method-cash' : 'method-qris'}`}>
                          {o.payment_method?.toUpperCase()}
                        </span>
                        <span className="ro-total">Rp {Number(o.total).toLocaleString('id-ID')}</span>
                      </div>
                    ))
                  )}
                </div>
              </div>
            </div>
          )}

          {/* kelola menu satuan dan paketan */}
          {activeTab === 'products' && (
            <div>
              {renderStockAlertBanner()}

              <div className="section-head">
                <div>
                  <h2>Daftar Menu Satuan & Paketan</h2>
                  <p>Stok bahan baku</p>
                </div>
                <div className="head-action" style={{ display: 'flex', gap: '10px', flexWrap: 'wrap' }}>
                  <button
                    type="button"
                    className="btn btn-secondary"
                    onClick={() => openAddProductModal('paketan')}
                  >
                    + Buat Menu Paketan
                  </button>
                  <button
                    type="button"
                    className="btn btn-primary"
                    onClick={() => openAddProductModal('satuan')}
                  >
                    + Tambah Menu Satuan
                  </button>
                </div>
              </div>

              {/* filter jenis menu */}
              <div className="pos-command-bar" style={{ marginBottom: '20px' }}>
                <div className="pos-command-top">
                  <div className="pos-segmented">
                    <button
                      type="button"
                      className={`pos-seg-btn ${productTypeFilter === 'all' ? 'active' : ''}`}
                      onClick={() => setProductTypeFilter('all')}
                    >
                      <span>Semua Menu</span>
                      <em className="pos-seg-count">{products.length}</em>
                    </button>
                    <button
                      type="button"
                      className={`pos-seg-btn ${productTypeFilter === 'satuan' ? 'active' : ''}`}
                      onClick={() => setProductTypeFilter('satuan')}
                    >
                      <IconCup size={15} />
                      <span>Menu Satuan</span>
                      <em className="pos-seg-count">{satuanProducts.length}</em>
                    </button>
                    <button
                      type="button"
                      className={`pos-seg-btn ${productTypeFilter === 'paketan' ? 'active' : ''}`}
                      onClick={() => setProductTypeFilter('paketan')}
                    >
                      <IconLayers size={15} />
                      <span>Menu Paketan</span>
                      <em className="pos-seg-count">{paketanProducts.length}</em>
                    </button>
                  </div>

                  <div className="pos-search-wrap">
                    <IconSearch size={16} />
                    <input
                      type="text"
                      placeholder="Cari nama menu atau deskripsi..."
                      value={productSearch}
                      onChange={(e) => setProductSearch(e.target.value)}
                    />
                    {productSearch && (
                      <button type="button" className="search-clear" onClick={() => setProductSearch('')}>
                        ✕
                      </button>
                    )}
                  </div>
                </div>

                <div className="pos-cat-strip">
                  {['Semua', ...CATEGORIES].map((cat) => (
                    <button
                      key={cat}
                      type="button"
                      className={`pos-cat-pill ${productCategoryFilter === cat ? 'active' : ''}`}
                      onClick={() => setProductCategoryFilter(cat)}
                    >
                      {cat}
                    </button>
                  ))}
                  <button
                    type="button"
                    className={`pos-cat-pill ${productStockFilter === 'warning' ? 'active' : ''}`}
                    onClick={() => setProductStockFilter((prev) => (prev === 'warning' ? 'all' : 'warning'))}
                  >
                    Menipis ≤20% ({warningProducts.length + lockedProducts.length})
                  </button>
                  <button
                    type="button"
                    className={`pos-cat-pill ${productStockFilter === 'locked' ? 'active' : ''}`}
                    onClick={() => setProductStockFilter((prev) => (prev === 'locked' ? 'all' : 'locked'))}
                  >
                    Terkunci ≤5% ({lockedProducts.length})
                  </button>
                </div>
              </div>

              {filteredAdminProducts.length === 0 ? (
                <div className="card card-pad">
                  <div className="empty">
                    <strong>Menu Tidak Ditemukan</strong>
                    <p>Coba ubah kata kunci pencarian atau filter di atas.</p>
                  </div>
                </div>
              ) : (
                <div className="admin-menu-grid">
                  {filteredAdminProducts.map((p) => {
                    const hasRecipe = recipes.some((r) => Number(r.product_id) === Number(p.id));
                    const pct = Number(p.stock_percent ?? 100);
                    const isLocked = Boolean(p.is_locked || p.stock <= 3 || pct <= 5);
                    const isWarning = !isLocked && Boolean(p.is_warning || p.stock <= 15 || pct <= 20);

                    return (
                      <div
                        key={p.id}
                        className={`admin-menu-card ${isLocked ? 'is-locked' : isWarning ? 'is-warning' : ''}`}
                      >
                        <div className="admin-menu-head">
                          <div
                            className="thumb thumb-photo admin-menu-thumb-photo"
                            style={{
                              background: `linear-gradient(135deg, ${p.gradient || '#D8B493,#A67C58'})`,
                            }}
                          >
                            <img
                              src={getProductImage(p)}
                              alt={p.name}
                              className="thumb-img"
                              loading="lazy"
                            />
                          </div>
                          <div className="admin-menu-info">
                            <div className="admin-menu-badges">
                              <span
                                className={`badge ${p.item_type === 'paketan' ? 'badge-amber' : 'badge-brown'}`}
                              >
                                {p.item_type === 'paketan' ? 'Paketan' : `Satuan • ${p.category}`}
                              </span>
                              <span
                                className={`badge ${
                                  isLocked ? 'badge-red' : isWarning ? 'badge-amber' : 'badge-green'
                                }`}
                              >
                                {isLocked
                                  ? 'Terkunci (≤5% / ≤3)'
                                  : isWarning
                                  ? 'Menipis (≤20% / ≤15)'
                                  : 'Stok Bahan Aman'}
                              </span>
                            </div>
                            <h3 className="admin-menu-title">{p.name}</h3>
                            <p className="admin-menu-desc">{p.description}</p>
                          </div>
                        </div>

                        <div className="admin-menu-stats">
                          <div className="admin-menu-stat-box">
                            <small>Harga (Regular M / Large L)</small>
                            <strong>
                              Rp {Number(p.price_m).toLocaleString('id-ID')}{' '}
                              <span>/ Rp {Number(p.price_l).toLocaleString('id-ID')}</span>
                            </strong>
                          </div>
                          <div className="admin-menu-stat-box">
                            <small>Kapasitas Porsi Gudang</small>
                            <strong className={isLocked ? 'text-red' : isWarning ? 'text-amber' : 'text-green'}>
                              Bisa dibuat: {p.stock} porsi ({pct}%)
                            </strong>
                          </div>
                        </div>

                        <div className="pos-meter-wrap" style={{ marginTop: 0 }}>
                          <div className="pos-meter-labels">
                            <span>{p.limiting_reason || 'Stok bahan baku'}</span>
                            <strong>{pct}%</strong>
                          </div>
                          <div className="pos-meter-track">
                            <div
                              className={`pos-meter-fill ${
                                isLocked ? 'locked' : isWarning ? 'warning' : 'safe'
                              }`}
                              style={{ width: `${Math.min(100, Math.max(4, pct))}%` }}
                            />
                          </div>
                        </div>

                        <div className="admin-menu-foot">
                          {p.item_type === 'paketan' ? (
                            <div className="bundle-items-list">
                              {(p.bundle_items || []).map((bi, i) => (
                                <span key={i} className="bundle-item-chip">
                                  {bi.qty}× {bi.name}
                                </span>
                              ))}
                            </div>
                          ) : (
                            <button
                              type="button"
                              className={`btn btn-sm ${hasRecipe ? 'btn-soft' : 'btn-secondary'}`}
                              onClick={() => openRecipeModalForProduct(p.id)}
                            >
                              {hasRecipe ? 'Takaran gr/ml' : '+ Buat Resep'}
                            </button>
                          )}

                          <div className="admin-menu-actions">
                            <button
                              type="button"
                              className="btn btn-secondary btn-sm"
                              onClick={() => openEditProductModal(p)}
                            >
                              Ubah
                            </button>
                            <button
                              type="button"
                              className="btn btn-danger btn-sm"
                              onClick={() => handleDeleteProduct(p)}
                            >
                              Nonaktifkan
                            </button>
                          </div>
                        </div>
                      </div>
                    );
                  })}
                </div>
              )}
            </div>
          )}

          {/* kelola promo toko */}
          {activeTab === 'promos' && (
            <div>
              <div className="section-head">
                <div>
                  <h2>Manajemen Promo & Voucher Toko</h2>
                  <p>Buat kode promo diskon persentase (%) maupun potongan harga tetap (Rp) untuk kasir.</p>
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
                    <p>Klik "+ Tambah Promo Toko" di kanan atas untuk membuat voucher baru.</p>
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
                        <button
                          type="button"
                          className="btn btn-secondary btn-sm"
                          onClick={() => openEditPromoModal(p)}
                        >
                          Ubah Promo
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

          {/* resep dan takaran */}
          {activeTab === 'recipes' && (
            <div>
              <div className="section-head">
                <div>
                  <h2>Resep & Takaran</h2>
                  <p>Stok bahan baku</p>
                </div>
                <div className="head-action">
                  <button
                    type="button"
                    className="btn btn-primary"
                    onClick={() => {
                      const unreciped = products.find(
                        (p) => !recipes.some((r) => Number(r.product_id) === Number(p.id))
                      );
                      openRecipeModalForProduct(unreciped?.id || products[0]?.id);
                    }}
                  >
                    + Tambah / Atur Resep
                  </button>
                </div>
              </div>

              <div className="filter-bar">
                <div className="search-box">
                  <IconSearch size={16} />
                  <input
                    type="text"
                    placeholder="Cari nama menu, kategori, atau bahan baku..."
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
                  {[
                    { id: 'all', label: `Semua Menu (${products.length})` },
                    { id: 'configured', label: `Sudah Ada Resep (${recipes.length})` },
                    { id: 'unconfigured', label: `Belum Diatur (${Math.max(0, products.length - recipes.length)})` },
                  ].map((f) => (
                    <button
                      key={f.id}
                      type="button"
                      className={`chip ${recipeStatusFilter === f.id ? 'active' : ''}`}
                      onClick={() => setRecipeStatusFilter(f.id)}
                    >
                      {f.label}
                    </button>
                  ))}
                </div>
              </div>

              {filteredRecipeProducts.length === 0 ? (
                <div className="card card-pad">
                  <div className="empty">
                    <strong>Resep Tidak Ditemukan</strong>
                    <p>Tidak ada menu atau resep yang cocok dengan pencarian Anda.</p>
                  </div>
                </div>
              ) : (
                <div className="recipe-grid">
                  {filteredRecipeProducts.map((prod) => {
                    const recipe = recipes.find((r) => Number(r.product_id) === Number(prod.id));
                    return (
                      <div key={prod.id} className={`card recipe-card ${!recipe ? 'recipe-empty-card' : ''}`}>
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
                            <span className="product-cat">{prod.category} • Bisa dibuat: {prod.stock} porsi</span>
                            <h3>{prod.name}</h3>
                          </div>
                          {recipe ? (
                            <span className="recipe-time-pill">{recipe.prep_time}</span>
                          ) : (
                            <span className="badge badge-amber">Belum Diatur</span>
                          )}
                        </div>

                        {recipe ? (
                          <div className="recipe-card-body">
                            <div className="recipe-ing-table-wrap">
                              <table className="recipe-ing-table">
                                <thead>
                                  <tr>
                                    <th>Bahan Baku Gudang</th>
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
                        ) : (
                          <div className="recipe-card-empty">
                            <p>Belum ada takaran bahan baku dan instruksi pembuatan untuk menu ini.</p>
                          </div>
                        )}

                        <div className="recipe-card-foot">
                          <button
                            type="button"
                            className={`btn btn-sm ${recipe ? 'btn-secondary' : 'btn-primary'}`}
                            onClick={() => openRecipeModalForProduct(prod.id)}
                          >
                            {recipe ? 'Ubah Resep' : '+ Tambah Resep'}
                          </button>
                          {recipe && (
                            <button
                              type="button"
                              className="btn btn-danger btn-sm"
                              style={{ marginLeft: 'auto' }}
                              onClick={() => handleDeleteRecipe(prod.id, prod.name)}
                            >
                              Hapus
                            </button>
                          )}
                        </div>
                      </div>
                    );
                  })}
                </div>
              )}
            </div>
          )}

          {/* stok bahan gudang */}
          {activeTab === 'restock' && (
            <div>
              {renderStockAlertBanner()}

              <div className="restock-layout">
                {/* form stok bahan */}
                <div className="card restock-form">
                  <div className="card-head">
                    <div>
                      <h3>Kelola Stok Bahan Baku</h3>
                      <p>Stok bahan baku</p>
                    </div>
                  </div>

                  <div className="card-pad">
                    <div className="chips" style={{ marginBottom: '18px' }}>
                      <button
                        type="button"
                        className={`chip ${restockActionMode === 'add' ? 'active' : ''}`}
                        onClick={() => setRestockActionMode('add')}
                      >
                        + Tambah Stok Masuk
                      </button>
                      <button
                        type="button"
                        className={`chip ${restockActionMode === 'set' ? 'active' : ''}`}
                        onClick={() => setRestockActionMode('set')}
                      >
                        Atur Kapasitas Penuh (100%)
                      </button>
                    </div>

                    <form onSubmit={handleRestockIngredient}>
                      <label className="field">
                        <span className="field-label">Pilih / Ketik Nama Bahan Baku</span>
                        <input
                          type="text"
                          required
                          list="admin-ing-list"
                          className="input"
                          placeholder="Contoh: Espresso House Blend"
                          value={restockIngName}
                          onChange={(e) => {
                            const val = e.target.value;
                            setRestockIngName(val);
                            const found = ingredients.find(
                              (i) => (i.name || '').toLowerCase() === val.trim().toLowerCase()
                            );
                            if (found) setRestockIngUnit(found.unit);
                          }}
                        />
                        <datalist id="admin-ing-list">
                          {ingredients.map((ing) => (
                            <option key={ing.id} value={ing.name}>
                              Sisa: {ing.stock_amount} {ing.unit} ({ing.stock_percent}%)
                            </option>
                          ))}
                        </datalist>
                      </label>

                      <div className="form-grid" style={{ marginTop: '16px' }}>
                        <label className="field">
                          <span className="field-label">
                            {restockActionMode === 'set'
                              ? `Stok 100% Baru (${restockIngUnit})`
                              : `Jumlah Masuk (+${restockIngUnit})`}
                          </span>
                          <input
                            type="number"
                            min="0.1"
                            max="500000"
                            step="any"
                            required
                            className="input"
                            placeholder={restockActionMode === 'set' ? 'Contoh: 12' : 'Contoh: 500'}
                            value={restockIngAmount}
                            onChange={(e) => setRestockIngAmount(e.target.value)}
                          />
                        </label>

                        <label className="field">
                          <span className="field-label">Satuan Takaran</span>
                          <select
                            className="select"
                            value={restockIngUnit}
                            onChange={(e) => setRestockIngUnit(e.target.value)}
                          >
                            {RECIPE_UNITS.map((u) => (
                              <option key={u} value={u}>
                                {u}
                              </option>
                            ))}
                          </select>
                        </label>
                      </div>

                      <label className="field" style={{ marginTop: '16px' }}>
                        <span className="field-label">Catatan Gudang (Opsional)</span>
                        <input
                          type="text"
                          maxLength={200}
                          className="input"
                          placeholder="Contoh: Restock susu & biji kopi pagi"
                          value={restockIngNote}
                          onChange={(e) => setRestockIngNote(e.target.value)}
                        />
                      </label>

                      <button
                        type="submit"
                        disabled={savingIngRestock}
                        className="btn btn-primary btn-block"
                        style={{ marginTop: '22px' }}
                      >
                        {savingIngRestock
                          ? 'Menyimpan...'
                          : restockActionMode === 'set'
                          ? `Simpan & Jadikan 100% (${restockIngUnit})`
                          : `Tambah Stok Bahan (+${restockIngUnit})`}
                      </button>
                    </form>
                  </div>
                </div>

                <div style={{ display: 'grid', gap: '24px' }}>
                  {/* daftar bahan gudang */}
                  <div className="card">
                    <div className="card-head" style={{ flexWrap: 'wrap', gap: '14px' }}>
                      <div>
                        <h3>Daftar Stok Bahan Baku</h3>
                        <p>Stok bahan baku</p>
                      </div>
                      <div className="head-action" style={{ minWidth: '250px' }}>
                        <div className="search-box">
                          <IconSearch size={16} />
                          <input
                            type="text"
                            placeholder="Cari nama bahan..."
                            value={ingSearch}
                            onChange={(e) => setIngSearch(e.target.value)}
                          />
                          {ingSearch && (
                            <button type="button" className="search-clear" onClick={() => setIngSearch('')}>
                              ✕
                            </button>
                          )}
                        </div>
                      </div>
                    </div>

                    <div className="card-pad">
                      {filteredIngredients.length === 0 ? (
                        <div className="empty">
                          <strong>Bahan Baku Tidak Ditemukan</strong>
                        </div>
                      ) : (
                        <div className="ing-stock-grid">
                          {filteredIngredients.map((ing) => (
                            <div
                              key={ing.id}
                              className={`ing-meter-card ${
                                ing.is_locked ? 'is-locked' : ing.is_warning ? 'is-warning' : ''
                              }`}
                            >
                              <div className="ing-meter-top">
                                <strong>{ing.name}</strong>
                                <span
                                  className={`badge ${
                                    ing.is_locked
                                      ? 'badge-red'
                                      : ing.is_warning
                                      ? 'badge-amber'
                                      : 'badge-green'
                                  }`}
                                >
                                  {ing.stock_percent}%
                                </span>
                              </div>

                              <div className="ing-meter-values">
                                <span>
                                  Tersedia: <strong>{Number(ing.stock_amount).toLocaleString('id-ID')}</strong>{' '}
                                  {ing.unit}
                                </span>
                                <small>
                                  100%: {Number(ing.max_amount).toLocaleString('id-ID')} {ing.unit}
                                </small>
                              </div>

                              <div className="ing-progress-track">
                                <div
                                  className={`ing-progress-fill ${
                                    ing.is_locked ? 'locked' : ing.is_warning ? 'warning' : ''
                                  }`}
                                  style={{ width: `${Math.min(100, Math.max(3, ing.stock_percent))}%` }}
                                />
                              </div>

                              <div style={{ display: 'flex', gap: '8px', marginTop: 'auto', paddingTop: '6px' }}>
                                <button
                                  type="button"
                                  className="btn btn-soft btn-sm"
                                  onClick={() => {
                                    setRestockActionMode('add');
                                    setRestockIngName(ing.name);
                                    setRestockIngUnit(ing.unit);
                                    window.scrollTo({ top: 0, behavior: 'smooth' });
                                  }}
                                >
                                  + Tambah {ing.unit}
                                </button>
                                <button
                                  type="button"
                                  className="btn btn-secondary btn-sm"
                                  onClick={() => {
                                    setRestockActionMode('set');
                                    setRestockIngName(ing.name);
                                    setRestockIngUnit(ing.unit);
                                    setRestockIngAmount(String(ing.max_amount));
                                    window.scrollTo({ top: 0, behavior: 'smooth' });
                                  }}
                                >
                                  Atur 100%
                                </button>
                              </div>
                            </div>
                          ))}
                        </div>
                      )}
                    </div>
                  </div>

                  {/* riwayat mutasi stok */}
                  <div className="card">
                    <div className="card-head" style={{ flexWrap: 'wrap', gap: '14px' }}>
                      <div>
                        <h3>Riwayat Mutasi Bahan Baku Gudang (IN / OUT)</h3>
                        <p>Catatan penambahan stok bahan gudang dan pemotongan otomatis dari transaksi kasir</p>
                      </div>
                      <div
                        className="head-action"
                        style={{ display: 'flex', gap: '10px', flexWrap: 'wrap', alignItems: 'center' }}
                      >
                        <div className="search-box" style={{ minWidth: '220px' }}>
                          <IconSearch size={16} />
                          <input
                            type="text"
                            placeholder="Cari bahan, menu, kode order..."
                            value={invLogSearch}
                            onChange={(e) => setInventoryLogSearch(e.target.value)}
                          />
                          {invLogSearch && (
                            <button type="button" className="search-clear" onClick={() => setInventoryLogSearch('')}>
                              ✕
                            </button>
                          )}
                        </div>
                        <div className="chips">
                          {[
                            { id: 'all', label: 'Semua' },
                            { id: 'in', label: 'Masuk (IN)' },
                            { id: 'out', label: 'Keluar (OUT)' },
                          ].map((t) => (
                            <button
                              key={t.id}
                              type="button"
                              className={`chip ${invLogTypeFilter === t.id ? 'active' : ''}`}
                              onClick={() => setInvLogTypeFilter(t.id)}
                            >
                              {t.label}
                            </button>
                          ))}
                        </div>
                      </div>
                    </div>
                    <div className="card-pad">
                      {filteredInventoryLogs.length === 0 ? (
                        <div className="empty">
                          <strong>Catatan Mutasi Tidak Ditemukan</strong>
                        </div>
                      ) : (
                        <div className="inv-list">
                          {filteredInventoryLogs.slice(0, 25).map((log) => (
                            <div key={log.id} className="inv-row">
                              <div className={`inv-icon ${log.type === 'in' ? 'in' : 'out'}`}>
                                {log.type === 'in' ? 'IN' : 'OUT'}
                              </div>
                              <div className="inv-main">
                                <strong>{log.product_name}</strong>
                                <small>
                                  {log.note} • Oleh {log.actor_name || 'Sistem'} (Sisa akhir: {log.stock_after})
                                </small>
                              </div>
                              <div className={`inv-qty ${log.type === 'in' ? 'in' : 'out'}`}>
                                {log.type === 'in' ? `+${log.qty}` : `-${log.qty}`}
                              </div>
                              <div className="inv-time">
                                {log.created_at ? new Date(log.created_at).toLocaleString('id-ID') : '-'}
                              </div>
                            </div>
                          ))}
                        </div>
                      )}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* pengaturan toko */}
          {activeTab === 'settings' && (
            <div className="settings-grid">
              <form className="settings-card" onSubmit={handleSaveSettings}>
                <div className="settings-section">
                  <h4>Identitas & Kontak Kedai</h4>
                  <div className="form-grid">
                    <label className="field">
                      <span className="field-label">Nama Toko</span>
                      <input
                        className="input"
                        required
                        maxLength={100}
                        value={settingsForm.store_name}
                        onChange={(e) => setSettingsForm({ ...settingsForm, store_name: e.target.value })}
                      />
                    </label>
                    <label className="field">
                      <span className="field-label">No. Telepon</span>
                      <input
                        className="input"
                        maxLength={40}
                        value={settingsForm.phone}
                        onChange={(e) => setSettingsForm({ ...settingsForm, phone: e.target.value })}
                      />
                    </label>
                    <label className="field span-2">
                      <span className="field-label">Slogan / Tagline</span>
                      <input
                        className="input"
                        maxLength={160}
                        value={settingsForm.tagline}
                        onChange={(e) => setSettingsForm({ ...settingsForm, tagline: e.target.value })}
                      />
                    </label>
                    <label className="field span-2">
                      <span className="field-label">Alamat Lengkap</span>
                      <input
                        className="input"
                        maxLength={255}
                        value={settingsForm.address}
                        onChange={(e) => setSettingsForm({ ...settingsForm, address: e.target.value })}
                      />
                    </label>
                  </div>
                </div>

                <div className="settings-section">
                  <h4>Pajak, Layanan & Struk</h4>
                  <div className="form-grid">
                    <label className="field">
                      <span className="field-label">Pajak (%)</span>
                      <input
                        type="number"
                        min="0"
                        max="100"
                        step="0.5"
                        className="input"
                        value={settingsForm.tax_percent}
                        onChange={(e) => setSettingsForm({ ...settingsForm, tax_percent: e.target.value })}
                      />
                    </label>
                    <label className="field">
                      <span className="field-label">Biaya Layanan (%)</span>
                      <input
                        type="number"
                        min="0"
                        max="100"
                        step="0.5"
                        className="input"
                        value={settingsForm.service_percent}
                        onChange={(e) => setSettingsForm({ ...settingsForm, service_percent: e.target.value })}
                      />
                    </label>
                    <label className="field span-2">
                      <span className="field-label">Nama Merchant QRIS</span>
                      <input
                        className="input"
                        maxLength={100}
                        value={settingsForm.qris_merchant}
                        onChange={(e) => setSettingsForm({ ...settingsForm, qris_merchant: e.target.value })}
                      />
                    </label>
                    <label className="field span-2">
                      <span className="field-label">Catatan Kaki Struk (Footer)</span>
                      <input
                        className="input"
                        maxLength={255}
                        value={settingsForm.receipt_footer}
                        onChange={(e) => setSettingsForm({ ...settingsForm, receipt_footer: e.target.value })}
                      />
                    </label>
                  </div>

                  <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: '8px' }}>
                    <button type="submit" disabled={savingSettings} className="btn btn-primary">
                      {savingSettings ? 'Menyimpan...' : 'Simpan Pengaturan'}
                    </button>
                  </div>
                </div>
              </form>

              <div className="settings-card">
                <div className="card-head">
                  <div>
                    <h3>Pratinjau Struk Cetak</h3>
                    <p>Tampilan struk berdasarkan pengaturan saat ini</p>
                  </div>
                </div>
                <div className="card-pad">
                  <div
                    className="receipt"
                    style={{
                      background: '#fcfaf7',
                      border: '1px dashed var(--line-strong)',
                      borderRadius: '14px',
                      padding: '20px',
                    }}
                  >
                    <div className="receipt-center">
                      <img src={logo} alt="Logo" className="receipt-logo" />
                      <h4>{settingsForm.store_name || 'VERSI KOPI'}</h4>
                      <div className="rc-sub">{settingsForm.tagline}</div>
                      <div className="rc-sub">{settingsForm.address}</div>
                      {settingsForm.phone && <div className="rc-sub">Telp: {settingsForm.phone}</div>}
                    </div>
                    <div className="dashed"></div>
                    <div className="receipt-row">
                      <span>Contoh Order</span>
                      <span className="r">KS-20261115-001</span>
                    </div>
                    <div className="receipt-row">
                      <span>Kopi Susu Gula Aren (M) ×1</span>
                      <span className="r">Rp 22.000</span>
                    </div>
                    <div className="dashed"></div>
                    <div className="receipt-row receipt-total">
                      <span>TOTAL</span>
                      <span className="r">Rp 22.000</span>
                    </div>
                    <div className="dashed"></div>
                    <div className="receipt-foot">
                      {settingsForm.receipt_footer || 'Terima kasih telah berkunjung ke Versi Kopi'}
                    </div>
                  </div>
                </div>
              </div>
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

      {/* modal menu */}
      {isProductModalOpen && (
        <div className="modal-backdrop" onClick={() => setIsProductModalOpen(false)}>
          <div className="modal-sheet modal-lg" onClick={(e) => e.stopPropagation()}>
            <div className="modal-head">
              <div>
                <h3>{editingProductId ? 'Ubah Data Menu' : 'Tambah Menu Baru'}</h3>
                <p>Stok bahan baku</p>
              </div>
              <button type="button" className="modal-close" onClick={() => setIsProductModalOpen(false)}>
                ✕
              </button>
            </div>
            <form onSubmit={handleSaveProduct}>
              <div className="modal-body">
                <div className="form-grid">
                  <div className="field span-2">
                    <span className="field-label">Jenis Menu di Kasir</span>
                    <div className="chips">
                      <button
                        type="button"
                        className={`chip ${newProductItemType === 'satuan' ? 'active' : ''}`}
                        onClick={() => {
                          setNewProductItemType('satuan');
                          if (newProductCategory === 'Bundling') setNewProductCategory('Signature');
                        }}
                      >
                        Menu Satuan
                      </button>
                      <button
                        type="button"
                        className={`chip ${newProductItemType === 'paketan' ? 'active' : ''}`}
                        onClick={() => {
                          setNewProductItemType('paketan');
                          setNewProductCategory('Bundling');
                        }}
                      >
                        Menu Paketan (Bundling)
                      </button>
                    </div>
                  </div>

                  <label className="field">
                    <span className="field-label">Nama {newProductItemType === 'paketan' ? 'Paket' : 'Menu'}</span>
                    <input
                      className="input"
                      required
                      maxLength={120}
                      placeholder={
                        newProductItemType === 'paketan'
                          ? 'Contoh: Paket Pagi Produktif'
                          : 'Contoh: Caramel Macchiato'
                      }
                      value={newProductName}
                      onChange={(e) => setNewProductName(e.target.value)}
                    />
                  </label>

                  <label className="field">
                    <span className="field-label">Kategori</span>
                    <select
                      className="select"
                      value={newProductCategory}
                      onChange={(e) => setNewProductCategory(e.target.value)}
                    >
                      {CATEGORIES.map((cat) => (
                        <option key={cat} value={cat}>
                          {cat}
                        </option>
                      ))}
                    </select>
                  </label>

                  <label className="field">
                    <span className="field-label">Harga Regular / M (Rp)</span>
                    <input
                      type="number"
                      min="0"
                      required
                      className="input"
                      value={newProductPriceM}
                      onChange={(e) => setNewProductPriceM(e.target.value)}
                    />
                  </label>

                  <label className="field">
                    <span className="field-label">Harga Large / L (Rp)</span>
                    <input
                      type="number"
                      min="0"
                      required
                      className="input"
                      value={newProductPriceL}
                      onChange={(e) => setNewProductPriceL(e.target.value)}
                    />
                  </label>

                  {newProductItemType === 'paketan' && (
                    <div className="field span-2">
                      <div className="recipe-editor-head">
                        <span className="field-label">
                          Isi Komponen Menu Paketan (Otomatis menggunakan bahan baku dari item satuan di dalamnya)
                        </span>
                        <button type="button" className="btn btn-soft btn-sm" onClick={addBundleRow}>
                          + Tambah Item Paket
                        </button>
                      </div>
                      <div className="recipe-ing-rows">
                        {newProductBundleItems.map((bi, idx) => (
                          <div
                            key={idx}
                            style={{ display: 'grid', gridTemplateColumns: '1fr 90px 40px', gap: '10px' }}
                          >
                            <select
                              className="select"
                              value={bi.product_id || ''}
                              onChange={(e) => updateBundleRow(idx, 'product_id', e.target.value)}
                            >
                              <option value="">-- Pilih Menu Satuan --</option>
                              {satuanProducts.map((sp) => (
                                <option key={sp.id} value={sp.id}>
                                  {sp.name} (Bisa dibuat: {sp.stock} porsi)
                                </option>
                              ))}
                            </select>
                            <input
                              type="number"
                              min="1"
                              max="20"
                              className="input"
                              placeholder="Qty"
                              value={bi.qty}
                              onChange={(e) => updateBundleRow(idx, 'qty', e.target.value)}
                            />
                            <button
                              type="button"
                              className="btn btn-danger btn-sm"
                              disabled={newProductBundleItems.length <= 1}
                              onClick={() => removeBundleRow(idx)}
                            >
                              ✕
                            </button>
                          </div>
                        ))}
                      </div>
                    </div>
                  )}

                  <label className="field span-2">
                    <span className="field-label">Deskripsi Singkat</span>
                    <textarea
                      className="textarea"
                      maxLength={500}
                      placeholder="Tuliskan komposisi atau karakter rasa menu..."
                      value={newProductDesc}
                      onChange={(e) => setNewProductDesc(e.target.value)}
                    />
                  </label>

                  <div className="field span-2">
                    <span className="field-label">Foto Produk (Bagian Produk Saja)</span>
                    <div style={{ display: 'flex', gap: '12px', alignItems: 'center' }}>
                      <img
                        src={newProductImageUrl}
                        alt="Preview"
                        style={{
                          width: '56px',
                          height: '56px',
                          borderRadius: '12px',
                          objectFit: 'cover',
                          border: '1px solid var(--line-strong)',
                          flexShrink: 0,
                        }}
                      />
                      <select
                        className="select"
                        value={newProductImageUrl}
                        onChange={(e) => setNewProductImageUrl(e.target.value)}
                      >
                        {PRODUCT_IMAGES_CATALOG.map((img) => (
                          <option key={img.url} value={img.url}>
                            {img.label}
                          </option>
                        ))}
                      </select>
                    </div>
                  </div>

                  <div className="field span-2">
                    <span className="field-label">Nuansa Warna Kartu</span>
                    <div className="grad-swatches">
                      {GRADIENTS.map((g) => (
                        <button
                          key={g.value}
                          type="button"
                          title={g.label}
                          className={`grad-swatch ${newProductGradient === g.value ? 'active' : ''}`}
                          style={{ background: `linear-gradient(135deg, ${g.value})` }}
                          onClick={() => setNewProductGradient(g.value)}
                        />
                      ))}
                    </div>
                  </div>
                </div>
              </div>
              <div className="modal-actions">
                <button
                  type="button"
                  className="btn btn-secondary"
                  onClick={() => setIsProductModalOpen(false)}
                >
                  Batal
                </button>
                <button type="submit" disabled={savingProduct} className="btn btn-primary">
                  {savingProduct ? 'Menyimpan...' : 'Simpan Menu'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* modal resep */}
      {isRecipeModalOpen && (
        <div className="modal-backdrop" onClick={() => setIsRecipeModalOpen(false)}>
          <div className="modal-sheet modal-lg" onClick={(e) => e.stopPropagation()}>
            <div className="modal-head">
              <div>
                <h3>Resep & Takaran</h3>
                <p>Stok bahan baku</p>
              </div>
              <button type="button" className="modal-close" onClick={() => setIsRecipeModalOpen(false)}>
                ✕
              </button>
            </div>
            <form onSubmit={handleSaveRecipe}>
              <div className="modal-body">
                <div className="form-grid">
                  <label className="field">
                    <span className="field-label">Pilih Menu</span>
                    <select
                      className="select"
                      value={recipeProductId}
                      onChange={(e) => handleSelectRecipeProductChange(e.target.value)}
                    >
                      {products.map((p) => (
                        <option key={p.id} value={p.id}>
                          {p.name} ({p.category})
                        </option>
                      ))}
                    </select>
                  </label>

                  <label className="field">
                    <span className="field-label">Estimasi Waktu Seduh</span>
                    <input
                      type="text"
                      className="input"
                      maxLength={40}
                      placeholder="Contoh: 2 Menit"
                      value={recipePrepTime}
                      onChange={(e) => setRecipePrepTime(e.target.value)}
                    />
                  </label>
                </div>

                <div className="recipe-editor-section">
                  <div className="recipe-editor-head">
                    <span className="field-label">Daftar Bahan Baku Gudang & Takaran per Cup (gr / ml)</span>
                    <button type="button" className="btn btn-soft btn-sm" onClick={addIngredientRow}>
                      + Tambah Bahan
                    </button>
                  </div>

                  <div className="recipe-ing-rows">
                    <div className="recipe-ing-header-row">
                      <span>Nama Bahan Baku Gudang</span>
                      <span>Takaran M</span>
                      <span>Takaran L</span>
                      <span>Satuan</span>
                      <span></span>
                    </div>
                    {recipeIngredients.map((ing, idx) => (
                      <div key={idx} className="recipe-ing-row">
                        <input
                          type="text"
                          required
                          maxLength={100}
                          list="ing-stock-names"
                          className="input"
                          placeholder="Contoh: Espresso House Blend"
                          value={ing.name}
                          onChange={(e) => updateIngredientRow(idx, 'name', e.target.value)}
                        />
                        <input
                          type="text"
                          required
                          maxLength={20}
                          className="input"
                          placeholder="M (30)"
                          value={ing.amount_m}
                          onChange={(e) => updateIngredientRow(idx, 'amount_m', e.target.value)}
                        />
                        <input
                          type="text"
                          required
                          maxLength={20}
                          className="input"
                          placeholder="L (45)"
                          value={ing.amount_l}
                          onChange={(e) => updateIngredientRow(idx, 'amount_l', e.target.value)}
                        />
                        <select
                          className="select"
                          value={ing.unit}
                          onChange={(e) => updateIngredientRow(idx, 'unit', e.target.value)}
                        >
                          {RECIPE_UNITS.map((u) => (
                            <option key={u} value={u}>
                              {u}
                            </option>
                          ))}
                        </select>
                        <button
                          type="button"
                          className="btn btn-danger btn-sm"
                          disabled={recipeIngredients.length <= 1}
                          onClick={() => removeIngredientRow(idx)}
                          title="Hapus baris"
                        >
                          ✕
                        </button>
                      </div>
                    ))}
                    <datalist id="ing-stock-names">
                      {ingredients.map((i) => (
                        <option key={i.id} value={i.name} />
                      ))}
                    </datalist>
                  </div>
                </div>

                <div className="form-grid" style={{ marginTop: '20px' }}>
                  <label className="field span-2">
                    <span className="field-label">Langkah Pembuatan / SOP Barista</span>
                    <textarea
                      className="textarea"
                      maxLength={2000}
                      placeholder="1. Ekstrak kopi menjadi espresso...&#10;2. Tambahkan es batu dan susu segar..."
                      value={recipeSteps}
                      onChange={(e) => setRecipeSteps(e.target.value)}
                    />
                  </label>

                  <label className="field span-2">
                    <span className="field-label">Catatan Barista (Opsional)</span>
                    <input
                      type="text"
                      maxLength={255}
                      className="input"
                      placeholder="Contoh: Gunakan double ristretto untuk ukuran Large"
                      value={recipeNote}
                      onChange={(e) => setRecipeNote(e.target.value)}
                    />
                  </label>
                </div>
              </div>
              <div className="modal-actions">
                <button
                  type="button"
                  className="btn btn-secondary"
                  onClick={() => setIsRecipeModalOpen(false)}
                >
                  Batal
                </button>
                <button type="submit" disabled={savingRecipe} className="btn btn-primary">
                  {savingRecipe ? 'Menyimpan...' : 'Simpan Resep'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
