import { useState } from 'react';
import { Navigate, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { IconBolt, IconChart, IconCashier, IconShield, IconEye } from '../components/Icons';
import logo from '../assets/logo.png';

export default function Login() {
  const { user, login } = useAuth();
  const navigate = useNavigate();

  const [role, setRole] = useState(null); // pilih role
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
    setEmail(selectedRole === 'admin' ? 'admin@versikopi.test' : 'kasir@versikopi.test');
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!email.trim() || !password) {
      setError('Email dan password wajib diisi.');
      return;
    }
    setError('');
    setLoading(true);
    try {
      const u = await login({ email: email.trim(), password, role });
      navigate(u.role === 'admin' ? '/admin' : '/kasir', { replace: true });
    } catch (err) {
      const res = err.response;
      setError(
        res?.data?.message ||
          (res ? 'Login gagal. Periksa kembali email & password.' : 'Tidak dapat terhubung ke server.')
      );
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="login-screen">
      <div className="login-blob blob-1"></div>
      <div className="login-blob blob-2"></div>

      <div className="login-card">
        {/* panel identitas */}
        <div className="login-brand-panel">
          <div className="brand-top">
            <div className="logo-ring">
              <img src={logo} alt="Versi Kopi" />
            </div>
            <div>
              <div className="eyebrow">Point of Sale</div>
              <div className="brand-name">Versi Kopi</div>
              <div className="brand-tagline">Secangkir cerita di setiap tegukan</div>
            </div>
          </div>

          <div className="brand-copy">
            <h2>Operasional Kedai Lebih Rapi & Terukur.</h2>
            <p>Sistem kasir dan pencatatan inventaris harian untuk pelayanan kedai yang nyaman.</p>
          </div>

          <div className="brand-features">
            <div className="feature">
              <span className="feature-icon">
                <IconBolt size={17} />
              </span>
              <div>
                <strong>Transaksi Ringkas</strong>
                <small>Pencatatan pesanan dan pembayaran terstruktur</small>
              </div>
            </div>
            <div className="feature">
              <span className="feature-icon">
                <IconChart size={17} />
              </span>
              <div>
                <strong>Kontrol Inventaris</strong>
                <small>Pantau ketersediaan stok dan laporan penjualan</small>
              </div>
            </div>
          </div>

          <div className="brand-foot">
            <span>Versi Kopi POS • Sistem Internal Kedai</span>
          </div>
        </div>

        {/* form login */}
        <div className="login-form-panel">
          <div className="login-heading">
            <h2>{role ? (role === 'admin' ? 'Masuk sebagai Admin' : 'Masuk sebagai Kasir') : 'Selamat Datang'}</h2>
            <p>{role ? 'Masukkan kredensial akun Anda untuk melanjutkan' : 'Pilih akses kerja Anda untuk memulai sesi'}</p>
          </div>

          <div className="role-grid">
            <button
              type="button"
              className={`role-card ${role === 'kasir' ? 'is-selected' : ''}`}
              onClick={() => handleSelectRole('kasir')}
            >
              <div className="role-emoji">
                <IconCashier size={20} />
              </div>
              <div className="role-text">
                <strong>Kasir (POS)</strong>
                <small>Pesanan pelanggan, pembayaran & cetak struk</small>
              </div>
              <span className="role-arrow">→</span>
            </button>

            <button
              type="button"
              className={`role-card role-admin ${role === 'admin' ? 'is-selected' : ''}`}
              onClick={() => handleSelectRole('admin')}
            >
              <div className="role-emoji" style={{ color: '#f8efe4' }}>
                <IconShield size={20} />
              </div>
              <div className="role-text">
                <strong>Admin / Owner</strong>
                <small>Ringkasan omzet, stok gudang & manajemen menu</small>
              </div>
              <span className="role-arrow">→</span>
            </button>
          </div>

          {role && (
            <>
              <div className="divider">Kredensial Akun</div>

              {error && <div className="login-error show">{error}</div>}

              <form onSubmit={handleSubmit} className="login-form" noValidate>
                <div className="field">
                  <span className="field-label">Alamat Email</span>
                  <div className="input-wrap">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8">
                      <rect x="3" y="5" width="18" height="14" rx="2" />
                      <path d="m3 7 9 6 9-6" />
                    </svg>
                    <input
                      type="email"
                      required
                      autoComplete="username"
                      placeholder="nama@versikopi.test"
                      value={email}
                      onChange={(e) => setEmail(e.target.value)}
                    />
                  </div>
                </div>

                <div className="field">
                  <span className="field-label">Password</span>
                  <div className="input-wrap">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8">
                      <rect x="3" y="11" width="18" height="11" rx="2" />
                      <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                    </svg>
                    <input
                      type={showPassword ? 'text' : 'password'}
                      required
                      autoComplete="current-password"
                      placeholder="Masukkan password"
                      value={password}
                      onChange={(e) => setPassword(e.target.value)}
                    />
                    <button
                      type="button"
                      className="toggle-pass"
                      aria-label="Tampilkan password"
                      onClick={() => setShowPassword(!showPassword)}
                    >
                      <IconEye size={16} off={showPassword} />
                    </button>
                  </div>
                </div>

                <button
                  type="submit"
                  disabled={loading}
                  className={`btn btn-primary btn-block btn-login ${loading ? 'btn-loading' : ''}`}
                >
                  {loading ? 'Memproses...' : `Masuk sebagai ${role === 'admin' ? 'Admin' : 'Kasir'}`}
                </button>
              </form>
            </>
          )}

          <div className="demo-hint">
            Akun uji: <strong>admin@versikopi.test</strong> atau <strong>kasir@versikopi.test</strong> (Password: <strong>password</strong>)
          </div>
        </div>
      </div>
    </div>
  );
}
