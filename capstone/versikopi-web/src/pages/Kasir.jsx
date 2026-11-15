import React, { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';
import logo from '../assets/logo.png';

const SUGAR_LEVELS = [
  { value: '100%', name: 'Normal' },
  { value: '75%', name: 'Less' },
  { value: '50%', name: 'Half' },
  { value: '25%', name: 'Sedikit' },
  { value: '0%', name: 'No Sugar' }
];

export default function Kasir() {
  const { user, logout } = useAuth();
  const [activeTab, setActiveTab] = useState('menu'); // 'menu' | 'history' | 'promos'

  const [products, setProducts] = useState([]);
  const [promos, setPromos] = useState([]);
  const [orders, setOrders] = useState([]);
  const [settings, setSettings] = useState({});
  const [loading, setLoading] = useState(true);

  // Filter & Search Menu
  const [category, setCategory] = useState('Semua');
  const [search, setSearch] = useState('');

  // Cart
  const [cart, setCart] = useState([]);
  const [selectedProduct, setSelectedProduct] = useState(null);
  const [selectedSize, setSelectedSize] = useState('M');
  const [selectedSugar, setSelectedSugar] = useState('100%');
  const [productQty, setProductQty] = useState(1);
  const [productNote, setProductNote] = useState('');

  // Checkout
  const [isCheckoutOpen, setIsCheckoutOpen] = useState(false);
  const [paymentMethod, setPaymentMethod] = useState('cash');
  const [cashReceived, setCashReceived] = useState('');
  const [promoCodeInput, setPromoCodeInput] = useState('');
  const [appliedPromo, setAppliedPromo] = useState(null);
  const [successOrder, setSuccessOrder] = useState(null);
  const [selectedReceipt, setSelectedReceipt] = useState(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  // Ambil Data dari API
  const refreshData = async () => {
    try {
      const [resProd, resPromo, resSet, resOrd] = await Promise.all([
        fetch('http://127.0.0.1:8000/api/products').then((r) => r.json()).catch(() => []),
        fetch('http://127.0.0.1:8000/api/promos').then((r) => r.json()).catch(() => []),
        fetch('http://127.0.0.1:8000/api/settings').then((r) => r.json()).catch(() => ({})),
        fetch('http://127.0.0.1:8000/api/orders').then((r) => r.json()).catch(() => ({ orders: [] }))
      ]);
      setProducts(Array.isArray(resProd) ? resProd : []);
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

  const categories = ['Semua', ...new Set(products.map((p) => p.category))];

  const filteredProducts = products.filter((p) => {
    const matchCat = category === 'Semua' || p.category === category;
    const matchSearch = p.name.toLowerCase().includes(search.toLowerCase());
    return matchCat && matchSearch;
  });

  const openProductModal = (prod) => {
    if (prod.stock <= 0) return;
    setSelectedProduct(prod);
    setSelectedSize('M');
    setSelectedSugar('100%');
    setProductQty(1);
    setProductNote('');
  };

  const addToCartFromModal = () => {
    if (!selectedProduct) return;
    const unitPrice = selectedSize === 'M' ? selectedProduct.price_m : selectedProduct.price_l;
    const cartItemId = `${selectedProduct.id}-${selectedSize}-${selectedSugar}-${productNote}`;

    const existingIndex = cart.findIndex((item) => item.cartItemId === cartItemId);
    if (existingIndex > -1) {
      const updated = [...cart];
      updated[existingIndex].qty += productQty;
      setCart(updated);
    } else {
      setCart([
        ...cart,
        {
          cartItemId,
          id: selectedProduct.id,
          name: selectedProduct.name,
          emoji: selectedProduct.emoji || '☕',
          gradient: selectedProduct.gradient,
          size: selectedSize,
          sugar: selectedSugar,
          note: productNote,
          unit_price: unitPrice,
          qty: productQty,
          stock: selectedProduct.stock
        }
      ]);
    }
    setSelectedProduct(null);
  };

  const updateCartQty = (cartItemId, delta) => {
    setCart((prev) =>
      prev
        .map((item) => {
          if (item.cartItemId === cartItemId) {
            const next = item.qty + delta;
            return next > 0 ? { ...item, qty: Math.min(next, item.stock) } : null;
          }
          return item;
        })
        .filter(Boolean)
    );
  };

  const subtotal = cart.reduce((acc, item) => acc + item.unit_price * item.qty, 0);

  let discount = 0;
  if (appliedPromo) {
    if (appliedPromo.type === 'percent') {
      discount = (subtotal * appliedPromo.value) / 100;
      if (appliedPromo.max_discount > 0 && discount > appliedPromo.max_discount) {
        discount = appliedPromo.max_discount;
      }
    } else {
      discount = appliedPromo.value;
    }
  }

  const taxPercent = Number(settings.tax_percent || 0);
  const tax = Math.round(((subtotal - discount) * taxPercent) / 100);
  const total = Math.max(0, subtotal - discount + tax);

  const handleApplyPromo = () => {
    const promo = promos.find((p) => p.code.toUpperCase() === promoCodeInput.trim().toUpperCase());
    if (!promo) {
      alert('Kode promo tidak valid!');
      return;
    }
    if (subtotal < promo.min_subtotal) {
      alert(`Minimal belanja Rp ${promo.min_subtotal.toLocaleString('id-ID')} untuk promo ini!`);
      return;
    }
    setAppliedPromo(promo);
  };

  const handleProcessOrder = async () => {
    if (cart.length === 0) return;
    if (paymentMethod === 'cash' && Number(cashReceived) < total) {
      alert('Uang tunai yang diterima masih kurang!');
      return;
    }

    setIsSubmitting(true);
    try {
      const payload = {
        cashier_id: user?.id || 2,
        cashier_name: user?.name || 'Kasir Versi Kopi',
        items: cart,
        subtotal,
        discount,
        promo_code: appliedPromo ? appliedPromo.code : null,
        tax,
        total,
        payment_method: paymentMethod,
        cash_received: paymentMethod === 'cash' ? Number(cashReceived) : total,
        change_amount: paymentMethod === 'cash' ? Number(cashReceived) - total : 0
      };

      const res = await fetch('http://127.0.0.1:8000/api/orders', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();

      if (data.success) {
        const finishedOrder = { ...payload, code: data.order_code, created_at: new Date().toISOString() };
        setSuccessOrder(finishedOrder);
        setCart([]);
        setAppliedPromo(null);
        setIsCheckoutOpen(false);
        refreshData();
      }
    } catch {
      alert('Gagal memproses transaksi!');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="app">
      {/* SIDEBAR PERSIS TEMENMU */}
      <aside className="sidebar">
        <div className="sidebar-brand">
          <div className="logo-ring small">
            <img src={logo} alt="Logo" />
          </div>
          <div className="sidebar-brand-text">
            <strong>{settings.store_name || 'VERSI KOPI'}</strong>
            <small>Point of Sale</small>
          </div>
        </div>

        <div className="shift-card">
          <span className="shift-live">● Kasir Aktif</span>
          <strong>{user?.name || 'Kasir'}</strong>
          <small>Shift Sedang Berjalan</small>
        </div>

        <nav className="nav">
          <button
            type="button"
            className={`nav-item ${activeTab === 'menu' ? 'active' : ''}`}
            onClick={() => setActiveTab('menu')}
          >
            <span className="nav-icon">☕</span>
            <span>Menu Kasir</span>
          </button>
          <button
            type="button"
            className={`nav-item ${activeTab === 'history' ? 'active' : ''}`}
            onClick={() => setActiveTab('history')}
          >
            <span className="nav-icon">🧾</span>
            <span>Riwayat Pesanan</span>
          </button>
          <button
            type="button"
            className={`nav-item ${activeTab === 'promos' ? 'active' : ''}`}
            onClick={() => setActiveTab('promos')}
          >
            <span className="nav-icon">🎁</span>
            <span>Promo Toko</span>
          </button>
        </nav>

        <div className="sidebar-foot">
          <button className="btn btn-ghost btn-block btn-sm" onClick={logout}>
            Keluar (Logout)
          </button>
        </div>
      </aside>

      {/* KONTEN UTAMA */}
      <main className="app-main">
        <header className="topbar">
          <div className="topbar-title">
            <h1>{activeTab === 'menu' ? 'Menu Kasir' : activeTab === 'history' ? 'Riwayat Transaksi' : 'Promo Spesial'}</h1>
            <p>Sistem Kasir & Pesanan Toko Kopi</p>
          </div>

          <div className="topbar-actions">
            <div className="user-chip">
              <div className="avatar">K</div>
              <div className="user-chip-text">
                <strong>{user?.name || 'Kasir'}</strong>
                <small>Petugas Kasir</small>
              </div>
            </div>
          </div>
        </header>

        <div className="view">
          {/* TAB 1: MENU KASIR & KERANJANG */}
          {activeTab === 'menu' && (
            <div className="cashier-layout">
              <div>
                <section className="menu-hero">
                  <div>
                    <h2>Halo, {user?.name?.split(' ')[0] || 'Kasir'} ☕</h2>
                    <p>Pilih menu favorit pelanggan, atur varian gula & ukuran, lalu selesaikan transaksi.</p>
                  </div>
                  <div className="hero-stats">
                    <div className="hero-stat">
                      <strong>{products.length}</strong>
                      <small>Menu Aktif</small>
                    </div>
                  </div>
                </section>

                <div className="menu-toolbar">
                  <div className="search-box">
                    <input
                      type="text"
                      placeholder="Cari kopi, tea, snack..."
                      value={search}
                      onChange={(e) => setSearch(e.target.value)}
                    />
                  </div>
                  <div className="chips">
                    {categories.map((cat) => (
                      <button
                        key={cat}
                        className={`chip ${category === cat ? 'active' : ''}`}
                        onClick={() => setCategory(cat)}
                      >
                        {cat}
                      </button>
                    ))}
                  </div>
                </div>

                {loading ? (
                  <div className="loading-state">
                    <div className="spinner"></div>
                    <p>Memuat menu...</p>
                  </div>
                ) : (
                  <div className="product-grid">
                    {filteredProducts.map((p) => {
                      const [g1, g2] = p.gradient ? p.gradient.split(',') : ['#C08552', '#6F4E37'];
                      return (
                        <div
                          key={p.id}
                          className={`product-card ${p.stock <= 0 ? 'sold-out' : ''}`}
                          onClick={() => openProductModal(p)}
                        >
                          <div
                            className="product-visual"
                            style={{ background: `linear-gradient(135deg, ${g1}, ${g2})` }}
                          >
                            <span className="product-emoji">{p.emoji || '☕'}</span>
                            <span className={`product-stock ${p.stock <= 10 ? (p.stock <= 0 ? 'out' : 'low') : ''}`}>
                              {p.stock <= 0 ? 'Habis' : `Stok: ${p.stock}`}
                            </span>
                          </div>
                          <div className="product-info">
                            <span className="product-cat">{p.category}</span>
                            <span className="product-name">{p.name}</span>
                            <span className="product-desc">{p.description}</span>
                            <div className="product-foot">
                              <div className="price">
                                <small>Mulai</small>
                                Rp {Number(p.price_m).toLocaleString('id-ID')}
                              </div>
                              <button className="add-btn" disabled={p.stock <= 0}>+</button>
                            </div>
                          </div>
                        </div>
                      );
                    })}
                  </div>
                )}
              </div>

              {/* KERANJANG DI SISI KANAN */}
              <aside className="cart-panel">
                <div className="cart-head">
                  <h3>Pesanan Saat Ini</h3>
                  <span className="cart-count">{cart.reduce((a, b) => a + b.qty, 0)} item</span>
                  {cart.length > 0 && (
                    <button className="cart-clear" onClick={() => setCart([])}>Kosongkan</button>
                  )}
                </div>

                <div className="cart-items">
                  {cart.length === 0 ? (
                    <div className="cart-empty">
                      <span className="empty-icon">🛒</span>
                      <strong>Keranjang Kosong</strong>
                      <p>Pilih menu di sebelah kiri untuk menambahkan pesanan.</p>
                    </div>
                  ) : (
                    cart.map((item) => (
                      <div key={item.cartItemId} className="cart-item">
                        <div className="ci-emoji">{item.emoji}</div>
                        <div className="ci-info">
                          <div className="ci-name">{item.name}</div>
                          <div className="ci-meta">
                            Size {item.size} • Gula {item.sugar} {item.note && `(${item.note})`}
                          </div>
                          <div className="ci-bottom">
                            <span className="ci-price">
                              Rp {(item.unit_price * item.qty).toLocaleString('id-ID')}
                            </span>
                            <div className="qty-control" style={{ marginLeft: 'auto' }}>
                              <button className="qty-btn" onClick={() => updateCartQty(item.cartItemId, -1)}>−</button>
                              <span className="qty-value">{item.qty}</span>
                              <button className="qty-btn" disabled={item.qty >= item.stock} onClick={() => updateCartQty(item.cartItemId, 1)}>+</button>
                            </div>
                          </div>
                        </div>
                      </div>
                    ))
                  )}
                </div>

                {cart.length > 0 && (
                  <div className="cart-summary">
                    <div className="promo-box">
                      {appliedPromo ? (
                        <div className="promo-applied">
                          <span>🎁 {appliedPromo.code} (-Rp {discount.toLocaleString('id-ID')})</span>
                          <button onClick={() => setAppliedPromo(null)}>Hapus</button>
                        </div>
                      ) : (
                        <div className="promo-row">
                          <input
                            type="text"
                            placeholder="Kode Promo"
                            value={promoCodeInput}
                            onChange={(e) => setPromoCodeInput(e.target.value)}
                          />
                          <button className="btn btn-secondary btn-sm" onClick={handleApplyPromo}>Pakai</button>
                        </div>
                      )}
                    </div>

                    <div className="summary-row">
                      <span>Subtotal</span>
                      <span>Rp {subtotal.toLocaleString('id-ID')}</span>
                    </div>
                    {discount > 0 && (
                      <div className="summary-row discount">
                        <span>Diskon</span>
                        <span>-Rp {discount.toLocaleString('id-ID')}</span>
                      </div>
                    )}
                    <div className="summary-row total">
                      <span>Total Bayar</span>
                      <span>Rp {total.toLocaleString('id-ID')}</span>
                    </div>

                    <button className="checkout-btn" onClick={() => setIsCheckoutOpen(true)}>
                      Bayar Sekarang
                      <span className="btn-cart-total">Rp {total.toLocaleString('id-ID')}</span>
                    </button>
                  </div>
                )}
              </aside>
            </div>
          )}

          {/* TAB 2: RIWAYAT TRANSAKSI */}
          {activeTab === 'history' && (
            <div>
              <div className="section-head">
                <div>
                  <h2>Riwayat Transaksi</h2>
                  <p>Semua pesanan yang telah diproses di kasir.</p>
                </div>
              </div>

              {orders.length === 0 ? (
                <div className="card card-pad">
                  <div className="empty">
                    <div className="empty-icon">🧾</div>
                    <strong>Belum Ada Transaksi</strong>
                    <p>Pesanan yang selesai akan otomatis tercatat di sini.</p>
                  </div>
                </div>
              ) : (
                <div className="order-list">
                  {orders.map((o) => (
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
                      <div className="order-card-foot">
                        <span className="order-cashier">Kasir: {o.cashier_name}</span>
                        <button
                          className="btn btn-secondary btn-sm"
                          style={{ marginLeft: 'auto' }}
                          onClick={() => setSelectedReceipt(o)}
                        >
                          Cetak Struk
                        </button>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          )}

          {/* TAB 3: PROMO */}
          {activeTab === 'promos' && (
            <div>
              <div className="section-head">
                <div>
                  <h2>Daftar Promo Tersedia</h2>
                  <p>Gunakan kode promo ini saat checkout pesanan kasir.</p>
                </div>
              </div>
              <div className="promo-grid">
                {promos.map((p) => (
                  <div key={p.id} className="promo-card">
                    <span className="promo-stamp">Aktif</span>
                    <span className="promo-code">🎁 {p.code}</span>
                    <div className="promo-value">
                      {p.type === 'percent' ? `${p.value}%` : `Rp ${Number(p.value).toLocaleString('id-ID')}`}
                      <small>{p.type === 'percent' ? ' diskon' : ' potongan'}</small>
                    </div>
                    <div className="promo-meta">
                      Min. belanja Rp {Number(p.min_subtotal).toLocaleString('id-ID')}
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
      </main>

      {/* MODAL VARIAN MENU */}
      {selectedProduct && (
        <div className="modal-backdrop" onClick={() => setSelectedProduct(null)}>
          <div className="modal-sheet" onClick={(e) => e.stopPropagation()}>
            <div className="modal-head">
              <h3>Kustomisasi Menu</h3>
              <button className="modal-close" onClick={() => setSelectedProduct(null)}>✕</button>
            </div>
            <div className="modal-body">
              <div className="pm-hero">
                <div
                  className="pm-visual"
                  style={{
                    background: `linear-gradient(135deg, ${
                      selectedProduct.gradient ? selectedProduct.gradient.split(',')[0] : '#C08552'
                    }, ${selectedProduct.gradient ? selectedProduct.gradient.split(',')[1] : '#6F4E37'})`
                  }}
                >
                  {selectedProduct.emoji || '☕'}
                </div>
                <div>
                  <h4 className="pm-title">{selectedProduct.name}</h4>
                  <p className="pm-desc">{selectedProduct.description}</p>
                </div>
              </div>

              <div className="option-group">
                <div className="option-label">Pilih Ukuran</div>
                <div className="size-grid">
                  <button
                    type="button"
                    className={`size-option ${selectedSize === 'M' ? 'active' : ''}`}
                    onClick={() => setSelectedSize('M')}
                  >
                    <span className="size-letter">M</span>
                    <span className="size-text">
                      <strong>Medium</strong>
                      <small>Rp {Number(selectedProduct.price_m).toLocaleString('id-ID')}</small>
                    </span>
                  </button>
                  <button
                    type="button"
                    className={`size-option ${selectedSize === 'L' ? 'active' : ''}`}
                    onClick={() => setSelectedSize('L')}
                  >
                    <span className="size-letter">L</span>
                    <span className="size-text">
                      <strong>Large</strong>
                      <small>Rp {Number(selectedProduct.price_l).toLocaleString('id-ID')}</small>
                    </span>
                  </button>
                </div>
              </div>

              <div className="option-group">
                <div className="option-label">Level Gula</div>
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

              <div className="qty-and-note">
                <div>
                  <div className="option-label">Jumlah</div>
                  <div className="qty-large">
                    <button type="button" disabled={productQty <= 1} onClick={() => setProductQty((q) => q - 1)}>
                      −
                    </button>
                    <span>{productQty}</span>
                    <button type="button" disabled={productQty >= selectedProduct.stock} onClick={() => setProductQty((q) => q + 1)}>
                      +
                    </button>
                  </div>
                </div>
                <div className="note-field">
                  <div className="option-label">Catatan Tambahan</div>
                  <input
                    type="text"
                    placeholder="Contoh: Less ice, jangan terlalu manis"
                    value={productNote}
                    onChange={(e) => setProductNote(e.target.value)}
                  />
                </div>
              </div>
            </div>
            <div className="modal-actions">
              <button className="btn btn-secondary" onClick={() => setSelectedProduct(null)}>Batal</button>
              <button className="btn btn-primary" onClick={addToCartFromModal}>Tambahkan ke Pesanan</button>
            </div>
          </div>
        </div>
      )}

      {/* MODAL CHECKOUT PEMBAYARAN */}
      {isCheckoutOpen && (
        <div className="modal-backdrop" onClick={() => setIsCheckoutOpen(false)}>
          <div className="modal-sheet" onClick={(e) => e.stopPropagation()}>
            <div className="modal-head">
              <h3>Pembayaran</h3>
              <button className="modal-close" onClick={() => setIsCheckoutOpen(false)}>✕</button>
            </div>
            <div className="modal-body">
              <div className="pay-methods">
                <button
                  type="button"
                  className={`pay-method ${paymentMethod === 'cash' ? 'active' : ''}`}
                  onClick={() => setPaymentMethod('cash')}
                >
                  <span className="pay-icon">💵</span>
                  <div>
                    <span className="pay-name">Tunai (Cash)</span>
                    <span className="pay-desc">Bayar langsung di kasir</span>
                  </div>
                </button>
                <button
                  type="button"
                  className={`pay-method ${paymentMethod === 'qris' ? 'active' : ''}`}
                  onClick={() => setPaymentMethod('qris')}
                >
                  <span className="pay-icon">📱</span>
                  <div>
                    <span className="pay-name">QRIS</span>
                    <span className="pay-desc">Scan barcode semua e-wallet</span>
                  </div>
                </button>
              </div>

              {paymentMethod === 'cash' ? (
                <div className="cash-section">
                  <div className="option-label">Uang Diterima (Rp)</div>
                  <input
                    type="number"
                    className="input"
                    placeholder="Masukkan nominal uang"
                    value={cashReceived}
                    onChange={(e) => setCashReceived(e.target.value)}
                  />
                  <div className="quick-cash">
                    {[total, 50000, 100000].map((amt) => (
                      <button key={amt} type="button" onClick={() => setCashReceived(String(amt))}>
                        {amt === total ? 'Uang Pas' : `Rp ${amt.toLocaleString('id-ID')}`}
                      </button>
                    ))}
                  </div>

                  {Number(cashReceived) >= total && (
                    <div className="change-box">
                      <span>Kembalian:</span>
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
                  <span className="qris-sub">Arahkan kamera aplikasi e-wallet pelanggan ke QR code</span>
                </div>
              )}
            </div>
            <div className="modal-actions">
              <button className="btn btn-secondary" onClick={() => setIsCheckoutOpen(false)}>Batal</button>
              <button
                className="btn btn-primary"
                disabled={isSubmitting || (paymentMethod === 'cash' && Number(cashReceived) < total)}
                onClick={handleProcessOrder}
              >
                {isSubmitting ? 'Memproses...' : 'Selesaikan Pembayaran'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* STRUK PRINT MODAL */}
      {(successOrder || selectedReceipt) && (
        <div className="modal-backdrop">
          <div className="modal-sheet modal-sm">
            <div className="modal-head no-print">
              <h3>Struk Transaksi</h3>
              <button
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
                  <img src={logo} alt="Logo" className="receipt-logo" />
                  <h4>{settings.store_name || 'VERSI KOPI'}</h4>
                  <div className="rc-sub">{settings.tagline}</div>
                  <div className="rc-sub">{settings.address}</div>
                </div>
                <div className="dashed"></div>
                <div className="receipt-row">
                  <span>No. Pesanan:</span>
                  <span className="r">{(successOrder || selectedReceipt).code}</span>
                </div>
                <div className="receipt-row">
                  <span>Metode:</span>
                  <span className="r">{(successOrder || selectedReceipt).payment_method?.toUpperCase()}</span>
                </div>
                <div className="dashed"></div>
                <div className="receipt-row receipt-total">
                  <span>TOTAL</span>
                  <span className="r">Rp {Number((successOrder || selectedReceipt).total).toLocaleString('id-ID')}</span>
                </div>
                <div className="dashed"></div>
                <div className="receipt-foot">{settings.receipt_footer || 'Terima kasih sudah berkunjung! ☕'}</div>
              </div>
            </div>
            <div className="modal-actions no-print">
              <button className="btn btn-primary" onClick={() => window.print()}>🖨️ Cetak Struk</button>
              <button
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