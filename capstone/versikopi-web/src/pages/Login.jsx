import { useState } from 'react';
import { Navigate, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import logo from '../assets/logo.png';

export default function Login() {
  const { user, login } = useAuth();
  const navigate = useNavigate();

  const [role, setRole] = useState(null); // 'admin' | 'kasir'
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  if (user) {
    return <Navigate to={user.role === 'admin' ? '/admin' : '/kasir'} replace />;
  }

  const handleSelectRole = (selectedRole) => {
    setRole(selectedRole);
    setError('');
    // Otomatis isi email default seeder jika diinginkan untuk kemudahan tes
    setEmail(selectedRole === 'admin' ? 'admin@versikopi.test' : 'kasir@versikopi.test');
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!email || !password) {
      setError('Email dan password wajib diisi.');
      return;
    }
    setError('');
    setLoading(true);
    try {
      const u = await login({ email, password, role });
      navigate(u.role === 'admin' ? '/admin' : '/kasir', { replace: true });
    } catch (err) {
      const res = err.response;
      setError(
        res?.data?.message ||
          (res ? 'Login gagal. Periksa kembali email & password.' : 'Tidak bisa terhubung ke server Laravel.')
      );
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="login-screen">
      <div className="login-blob blob-1"></div>
      <div className="login-blob blob-2"></div>
      <div className="login-blob blob-3"></div>

      <div className="login-card">
        {/* Panel Kiri: Brand & Info Toko */}
        <div className="login-brand-panel">
          <div className="brand-top">
            <div className="logo-ring">
              <img src={logo} alt="Logo" />
            </div>
            <div>
              <div className="eyebrow">Point of Sale</div>
              <div className="brand-name">Versi Kopi</div>
              <div className="brand-tagline">Secangkir cerita di setiap tegukan</div>
            </div>
          </div>

          <div className="brand-copy">
            <h2>Kopi Terbaik, Transaksi Lebih Cepat.</h2>
            <p>Sistem kasir dan manajemen kedai kopi modern terintegrasi.</p>
          </div>

          <div className="brand-features">
            <div className="feature">
              <span className="feature-icon">⚡</span>
              <div>
                <strong>Cepat & Responsif</strong>
                <small>Proses pesanan kasir tanpa lemot</small>
              </div>
            </div>
            <div className="feature">
              <span className="feature-icon">📊</span>
              <div>
                <strong>Laporan Otomatis</strong>
                <small>Pantau stok dan omzet harian secara instan</small>
              </div>
            </div>
          </div>

          <div className="brand-foot">
            <span className="live-dot"></span>
            <span>Sistem POS Versi Kopi v1.0 • Siap Melayani</span>
          </div>
        </div>

        {/* Panel Kanan: Form Pilihan Akses & Login */}
        <div className="login-form-panel">
          <div className="login-heading">
            <h2>{role ? (role === 'admin' ? 'Masuk sebagai Admin' : 'Masuk sebagai Kasir') : 'Selamat Datang 👋'}</h2>
            <p>{role ? 'Masukkan kredensial akunmu di bawah ini' : 'Pilih peran kamu untuk mulai bekerja:'}</p>
          </div>

          {/* Kartu Pemilih Peran */}
          <div className="role-grid">
            <button
              type="button"
              className={`role-card ${role === 'kasir' ? 'is-selected' : ''}`}
              onClick={() => handleSelectRole('kasir')}
            >
              <div className="role-emoji">🧑‍🍳</div>
              <div className="role-text">
                <strong>Kasir (POS)</strong>
                <small>Buat pesanan baru, keranjang & pembayaran</small>
              </div>
              <span className="role-arrow">→</span>
            </button>

            <button
              type="button"
              className={`role-card role-admin ${role === 'admin' ? 'is-selected' : ''}`}
              onClick={() => handleSelectRole('admin')}
            >
              <div className="role-emoji" style={{ color: '#fff' }}>👑</div>
              <div className="role-text">
                <strong>Admin / Owner</strong>
                <small>Laporan omzet, restock stok & kelola menu</small>
              </div>
              <span className="role-arrow">→</span>
            </button>
          </div>

          {role && (
            <>
              <div className="divider">KREDENSIAL LOGIN</div>

              {error && <div className="login-error show">{error}</div>}

              <form onSubmit={handleSubmit} className="login-form">
                <div className="field">
                  <span className="field-label">Alamat Email</span>
                  <div className="input-wrap">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <rect x="3" y="5" width="18" height="14" rx="2" />
                      <path d="m3 7 9 6 9-6" />
                    </svg>
                    <input
                      type="email"
                      required
                      placeholder="nama@versikopi.test"
                      value={email}
                      onChange={(e) => setEmail(e.target.value)}
                    />
                  </div>
                </div>

                <div className="field">
                  <span className="field-label">Password</span>
                  <div className="input-wrap">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <rect x="3" y="11" width="18" height="11" rx="2" />
                      <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                    </svg>
                    <input
                      type={showPassword ? 'text' : 'password'}
                      required
                      placeholder="••••••••"
                      value={password}
                      onChange={(e) => setPassword(e.target.value)}
                    />
                    <button
                      type="button"
                      className="toggle-pass"
                      onClick={() => setShowPassword(!showPassword)}
                    >
                      {showPassword ? '🙈' : '👁️'}
                    </button>
                  </div>
                </div>

                <button
                  type="submit"
                  disabled={loading}
                  className={`btn btn-primary btn-block btn-login ${loading ? 'btn-loading' : ''}`}
                >
                  {loading ? 'Memproses...' : `Masuk Sebagai ${role === 'admin' ? 'Admin' : 'Kasir'}`}
                </button>
              </form>
            </>
          )}

          <div className="demo-hint">
            Default akun login: <strong>admin@versikopi.test</strong> atau <strong>kasir@versikopi.test</strong> (Password: <strong>password</strong>)
          </div>
        </div>
      </div>
    </div>
  );
}