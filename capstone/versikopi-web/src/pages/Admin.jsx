import React, { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';
import logo from '../assets/logo.png';

const GRADIENTS = [
  { label: 'Karamel', value: '#C08552,#6F4E37' },
  { label: 'Cokelat Pekat', value: '#8B5E3C,#3E2723' },
  { label: 'Matcha', value: '#A3B18A,#4F6F52' },
  { label: 'Berry', value: '#E67E7E,#A93F55' },
  { label: 'Keemasan', value: '#E0A96D,#B5651D' },
  { label: 'Cokelat Susu', value: '#B08968,#5C3D2E' }
];
const EMOJIS = ['☕', '🧋', '🥛', '🍵', '🍫', '🍓', '🥐', '🍩', '🍟'];
const CATEGORIES = ['Signature', 'Coffee', 'Non-Coffee', 'Pastry', 'Snack'];

export default function Admin() {
  const { user, logout } = useAuth();
  const [activeTab, setActiveTab] = useState('dashboard'); // 'dashboard' | 'products' | 'restock' | 'settings'

  const [products, setProducts] = useState([]);
  const [orders, setOrders] = useState([]);
  const [settings, setSettings] = useState({});
  const [loading, setLoading] = useState(true);

  // Form State Tambah Menu
  const [isProductModalOpen, setIsProductModalOpen] = useState(false);
  const [newProductName, setNewProductName] = useState('');
  const [newProductCategory, setNewProductCategory] = useState(CATEGORIES[0]);
  const [newProductStock, setNewProductStock] = useState(20);
  const [newProductPriceM, setNewProductPriceM] = useState(20000);
  const [newProductPriceL, setNewProductPriceL] = useState(25000);
  const [newProductDesc, setNewProductDesc] = useState('');
  const [newProductEmoji, setNewProductEmoji] = useState('☕');
  const [newProductGradient, setNewProductGradient] = useState(GRADIENTS[0].value);

  // Form State Restock
  const [restockProduct, setRestockProduct] = useState(null);
  const [restockQty, setRestockQty] = useState('');

  const loadData = async () => {
    try {
      const [resProd, resOrd, resSet] = await Promise.all([
        fetch('http://127.0.0.1:8000/api/products').then((r) => r.json()).catch(() => []),
        fetch('http://127.0.0.1:8000/api/orders').then((r) => r.json()).catch(() => ({ orders: [] })),
        fetch('http://127.0.0.1:8000/api/settings').then((r) => r.json()).catch(() => ({}))
      ]);
      setProducts(Array.isArray(resProd) ? resProd : []);
      setOrders(resOrd.orders || []);
      setSettings(resSet || {});
      if (Array.isArray(resProd) && resProd.length > 0) {
        setRestockProduct(resProd[0]);
      }
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadData();
  }, []);

  // Hitungan Statistik Dashboard
  const totalRevenue = orders.reduce((sum, o) => sum + Number(o.total || 0), 0);
  const totalOrders = orders.length;
  const lowStockProducts = products.filter((p) => p.stock <= 10);

  // Submit Menu Baru
  const handleSaveProduct = async (e) => {
    e.preventDefault();
    const payload = {
      name: newProductName,
      category: newProductCategory,
      stock: Number(newProductStock),
      price_m: Number(newProductPriceM),
      price_l: Number(newProductPriceL),
      description: newProductDesc,
      emoji: newProductEmoji,
      gradient: newProductGradient,
      active: 1
    };

    try {
      await fetch('http://127.0.0.1:8000/api/products', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      alert('Menu berhasil disimpan!');
      setIsProductModalOpen(false);
      loadData();
    } catch {
      alert('Gagal menyimpan menu');
    }
  };

  // Submit Restock
  const handleRestock = async () => {
    if (!restockProduct || !restockQty || Number(restockQty) <= 0) {
      alert('Masukkan jumlah restock yang valid!');
      return;
    }
    try {
      await fetch(`http://127.0.0.1:8000/api/products/${restockProduct.id}/restock`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ qty: Number(restockQty) })
      });
      alert(`Stok ${restockProduct.name} berhasil ditambahkan!`);
      setRestockQty('');
      loadData();
    } catch {
      alert('Gagal memperbarui stok');
    }
  };

  return (
    <div className="app">
      {/* SIDEBAR ADMIN */}
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

        <div className="shift-card">
          <span className="shift-live">● Mode Owner</span>
          <strong>{user?.name || 'Admin'}</strong>
          <small>Akses Penuh Manajemen</small>
        </div>

        <nav className="nav">
          <button
            type="button"
            className={`nav-item ${activeTab === 'dashboard' ? 'active' : ''}`}
            onClick={() => setActiveTab('dashboard')}
          >
            <span className="nav-icon">📊</span>
            <span>Dashboard</span>
          </button>
          <button
            type="button"
            className={`nav-item ${activeTab === 'products' ? 'active' : ''}`}
            onClick={() => setActiveTab('products')}
          >
            <span className="nav-icon">☕</span>
            <span>Kelola Menu</span>
          </button>
          <button
            type="button"
            className={`nav-item ${activeTab === 'restock' ? 'active' : ''}`}
            onClick={() => setActiveTab('restock')}
          >
            <span className="nav-icon">📦</span>
            <span>Restock Barang</span>
          </button>
          <button
            type="button"
            className={`nav-item ${activeTab === 'settings' ? 'active' : ''}`}
            onClick={() => setActiveTab('settings')}
          >
            <span className="nav-icon">⚙️</span>
            <span>Pengaturan Toko</span>
          </button>
        </nav>

        <div className="sidebar-foot">
          <button className="btn btn-ghost btn-block btn-sm" onClick={logout}>
            Keluar (Logout)
          </button>
        </div>
      </aside>

      <main className="app-main">
        <header className="topbar">
          <div className="topbar-title">
            <h1>{activeTab === 'dashboard' ? 'Ringkasan Toko' : activeTab === 'products' ? 'Kelola Daftar Menu' : activeTab === 'restock' ? 'Restock Gudang' : 'Pengaturan Toko'}</h1>
            <p>Dashboard Monitoring & Inventaris</p>
          </div>
          <div className="topbar-actions">
            <div className="user-chip">
              <div className="avatar">A</div>
              <div className="user-chip-text">
                <strong>{user?.name || 'Admin'}</strong>
                <small>Owner Kedai</small>
              </div>
            </div>
          </div>
        </header>

        <div className="view">
          {/* TAB 1: DASHBOARD STATS */}
          {activeTab === 'dashboard' && (
            <div>
              <div className="stat-grid">
                <div className="stat-card">
                  <div className="stat-top">
                    <span className="stat-icon">💰</span>
                    <span className="stat-label">Total Omzet</span>
                  </div>
                  <div className="stat-value">Rp {totalRevenue.toLocaleString('id-ID')}</div>
                  <div className="stat-sub">{totalOrders} Transaksi Selesai</div>
                </div>

                <div className="stat-card">
                  <div className="stat-top">
                    <span className="stat-icon">🧾</span>
                    <span className="stat-label">Pesanan Kasir</span>
                  </div>
                  <div className="stat-value">{totalOrders}</div>
                  <div className="stat-sub">Semua Transaksi Masuk</div>
                </div>

                <div className="stat-card">
                  <div className="stat-top">
                    <span className="stat-icon">☕</span>
                    <span className="stat-label">Total Menu</span>
                  </div>
                  <div className="stat-value">{products.length}</div>
                  <div className="stat-sub">Menu Aktif Dijual</div>
                </div>

                <div className="stat-card">
                  <div className="stat-top">
                    <span className="stat-icon">📦</span>
                    <span className="stat-label">Stok Kritis</span>
                  </div>
                  <div className="stat-value">{lowStockProducts.length}</div>
                  <div className="stat-sub">{lowStockProducts.length ? 'Perlu Restock Segera' : 'Semua Stok Aman'}</div>
                </div>
              </div>

              {/* LIST RECENT ORDERS */}
              <div className="card">
                <div className="card-head">
                  <div>
                    <h3>Transaksi Kasir Terbaru</h3>
                    <p>Daftar pesanan terakhir</p>
                  </div>
                </div>
                <div className="recent-orders">
                  {orders.length === 0 ? (
                    <div className="empty">
                      <strong>Belum Ada Transaksi</strong>
                    </div>
                  ) : (
                    orders.slice(0, 5).map((o) => (
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

          {/* TAB 2: MANAJEMEN MENU */}
          {activeTab === 'products' && (
            <div>
              <div className="section-head">
                <div>
                  <h2>Daftar Menu Kopi</h2>
                  <p>Kelola menu yang tampil di kasir.</p>
                </div>
                <div className="head-action">
                  <button className="btn btn-primary" onClick={() => setIsProductModalOpen(true)}>
                    + Tambah Menu Baru
                  </button>
                </div>
              </div>

              <div className="table-wrap">
                <table className="table">
                  <thead>
                    <tr>
                      <th>Menu</th>
                      <th>Kategori</th>
                      <th>Harga (M)</th>
                      <th>Harga (L)</th>
                      <th>Stok</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    {products.map((p) => (
                      <tr key={p.id}>
                        <td>
                          <div className="cell-main">
                            <div className="thumb" style={{ background: `linear-gradient(135deg, ${p.gradient || '#C08552,#6F4E37'})` }}>
                              {p.emoji || '☕'}
                            </div>
                            <div>
                              <div className="cell-name">{p.name}</div>
                              <div className="cell-sub">{p.description}</div>
                            </div>
                          </div>
                        </td>
                        <td>{p.category}</td>
                        <td className="cell-price">Rp {Number(p.price_m).toLocaleString('id-ID')}</td>
                        <td className="cell-price">Rp {Number(p.price_l).toLocaleString('id-ID')}</td>
                        <td>
                          <span className={`stock-pill ${p.stock <= 10 ? 'warn' : ''}`}>
                            {p.stock} pcs
                          </span>
                        </td>
                        <td>
                          <span className="badge badge-green">Aktif</span>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}

          {/* TAB 3: RESTOCK BARANG */}
          {activeTab === 'restock' && (
            <div className="restock-layout">
              <div className="card restock-form">
                <div className="card-head">
                  <h3>Form Restock Barang</h3>
                </div>
                <div className="card-pad">
                  <label className="field">
                    <span className="field-label">Pilih Menu</span>
                    <select
                      className="select"
                      value={restockProduct?.id || ''}
                      onChange={(e) => {
                        const found = products.find((p) => p.id === Number(e.target.value));
                        setRestockProduct(found);
                      }}
                    >
                      {products.map((p) => (
                        <option key={p.id} value={p.id}>
                          {p.name} (Stok: {p.stock})
                        </option>
                      ))}
                    </select>
                  </label>

                  <label className="field" style={{ marginTop: '14px' }}>
                    <span className="field-label">Jumlah Masuk (pcs)</span>
                    <input
                      type="number"
                      className="input"
                      placeholder="Contoh: 20"
                      value={restockQty}
                      onChange={(e) => setRestockQty(e.target.value)}
                    />
                  </label>

                  <button className="btn btn-primary btn-block" style={{ marginTop: '16px' }} onClick={handleRestock}>
                    Simpan Stok Masuk
                  </button>
                </div>
              </div>

              <div className="card">
                <div className="card-head">
                  <h3>Daftar Ketersediaan Stok</h3>
                </div>
                <div className="card-pad">
                  <div className="restock-list">
                    {products.map((p) => (
                      <div key={p.id} className="restock-row">
                        <div className="thumb" style={{ background: `linear-gradient(135deg, ${p.gradient || '#C08552,#6F4E37'})` }}>
                          {p.emoji || '☕'}
                        </div>
                        <div className="rr-info">
                          <strong>{p.name}</strong>
                          <small>{p.category} • Stok: {p.stock} pcs</small>
                        </div>
                        <button
                          className="btn btn-soft btn-sm"
                          onClick={() => {
                            setRestockProduct(p);
                            window.scrollTo({ top: 0, behavior: 'smooth' });
                          }}
                        >
                          Pilih
                        </button>
                      </div>
                    ))}
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* TAB 4: SETTINGS TOKO */}
          {activeTab === 'settings' && (
            <div className="settings-grid">
              <div className="settings-card">
                <div className="settings-section">
                  <h4>Identitas Toko</h4>
                  <div className="form-grid">
                    <div className="field">
                      <span className="field-label">Nama Toko</span>
                      <input className="input" defaultValue={settings.store_name || 'VERSI KOPI'} readOnly />
                    </div>
                    <div className="field">
                      <span className="field-label">No. Telepon</span>
                      <input className="input" defaultValue={settings.phone || '0812-3456-7890'} readOnly />
                    </div>
                    <div className="field span-2">
                      <span className="field-label">Alamat</span>
                      <input className="input" defaultValue={settings.address || 'Jl. Senja Raya No. 17, Bandung'} readOnly />
                    </div>
                  </div>
                </div>
              </div>

              <div className="settings-card">
                <div className="card-head">
                  <h3>Pratinjau Struk Cetak</h3>
                </div>
                <div className="card-pad">
                  <div className="receipt" style={{ background: '#fffdf9', border: '1px dashed var(--line-strong)', padding: '14px' }}>
                    <div className="receipt-center">
                      <img src={logo} alt="Logo" className="receipt-logo" />
                      <h4>{settings.store_name || 'VERSI KOPI'}</h4>
                      <div className="rc-sub">{settings.address}</div>
                    </div>
                    <div className="dashed"></div>
                    <div className="receipt-row"><span>Contoh Order</span><span className="r">KS-20261108-001</span></div>
                    <div className="receipt-row"><span>Kopi Susu Gula Aren</span><span className="r">Rp 22.000</span></div>
                    <div className="dashed"></div>
                    <div className="receipt-row receipt-total"><span>TOTAL</span><span className="r">Rp 22.000</span></div>
                    <div className="dashed"></div>
                    <div className="receipt-foot">{settings.receipt_footer || 'Terima kasih sudah ngopi! ☕'}</div>
                  </div>
                </div>
              </div>
            </div>
          )}
        </div>
      </main>

      {/* MODAL TAMBAH MENU BARU */}
      {isProductModalOpen && (
        <div className="modal-backdrop" onClick={() => setIsProductModalOpen(false)}>
          <div className="modal-sheet modal-lg" onClick={(e) => e.stopPropagation()}>
            <div className="modal-head">
              <h3>Tambah Menu Baru</h3>
              <button className="modal-close" onClick={() => setIsProductModalOpen(false)}>✕</button>
            </div>
            <form onSubmit={handleSaveProduct}>
              <div className="modal-body">
                <div className="form-grid">
                  <label className="field span-2">
                    <span className="field-label">Nama Menu</span>
                    <input
                      className="input"
                      required
                      placeholder="Contoh: Caramel Macchiato"
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
                        <option key={cat} value={cat}>{cat}</option>
                      ))}
                    </select>
                  </label>

                  <label className="field">
                    <span className="field-label">Stok Awal</span>
                    <input
                      type="number"
                      className="input"
                      value={newProductStock}
                      onChange={(e) => setNewProductStock(e.target.value)}
                    />
                  </label>

                  <label className="field">
                    <span className="field-label">Harga Size M (Rp)</span>
                    <input
                      type="number"
                      className="input"
                      value={newProductPriceM}
                      onChange={(e) => setNewProductPriceM(e.target.value)}
                    />
                  </label>

                  <label className="field">
                    <span className="field-label">Harga Size L (Rp)</span>
                    <input
                      type="number"
                      className="input"
                      value={newProductPriceL}
                      onChange={(e) => setNewProductPriceL(e.target.value)}
                    />
                  </label>

                  <label className="field span-2">
                    <span className="field-label">Deskripsi Menu</span>
                    <textarea
                      className="textarea"
                      placeholder="Tuliskan komposisi singkat..."
                      value={newProductDesc}
                      onChange={(e) => setNewProductDesc(e.target.value)}
                    />
                  </label>

                  <div className="field span-2">
                    <span className="field-label">Pilih Emoji</span>
                    <div className="emoji-picker">
                      {EMOJIS.map((em) => (
                        <button
                          key={em}
                          type="button"
                          className={`emoji-choice ${newProductEmoji === em ? 'active' : ''}`}
                          onClick={() => setNewProductEmoji(em)}
                        >
                          {em}
                        </button>
                      ))}
                    </div>
                  </div>

                  <div className="field span-2">
                    <span className="field-label">Pilih Gradasi Warna Kartu</span>
                    <div className="grad-swatches">
                      {GRADIENTS.map((g) => (
                        <button
                          key={g.value}
                          type="button"
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
                <button type="button" className="btn btn-secondary" onClick={() => setIsProductModalOpen(false)}>
                  Batal
                </button>
                <button type="submit" className="btn btn-primary">
                  Simpan Menu
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}