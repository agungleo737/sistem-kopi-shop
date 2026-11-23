import { useEffect, useState } from 'react';
import { Navigate, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { IconBolt, IconChart, IconCashier, IconShield, IconEye } from '../components/Icons';
import api from '../lib/api';
import { getStoreLogo } from '../lib/branding';

export default function Login() {
  const { user, login } = useAuth();
  const navigate = useNavigate();

  const [role, setRole] = useState(null); // pilih role
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [branding, setBranding] = useState({});
  const storeName = branding.store_name || 'Versi Kopi';

  // state lupa password
  const [showForgotModal, setShowForgotModal] = useState(false);
  const [forgotStep, setForgotStep] = useState('request');
  const [forgotEmail, setForgotEmail] = useState('');
  const [forgotCode, setForgotCode] = useState('');
  const [forgotPassword, setForgotPassword] = useState('');
  const [forgotPasswordConfirm, setForgotPasswordConfirm] = useState('');
  const [forgotMsg, setForgotMsg] = useState('');
  const [forgotErr, setForgotErr] = useState('');
  const [forgotDevCode, setForgotDevCode] = useState('');
  const [forgotLoading, setForgotLoading] = useState(false);

  const openForgotModal = () => {
    setShowForgotModal(true);
    setForgotStep('request');
    setForgotEmail(email || '');
    setForgotCode('');
    setForgotPassword('');
    setForgotPasswordConfirm('');
    setForgotMsg('');
    setForgotErr('');
    setForgotDevCode('');
  };

  const handleRequestReset = async (e) => {
    e.preventDefault();
    setForgotErr('');
    setForgotMsg('');
    setForgotLoading(true);
    try {
      const { data } = await api.post('/forgot-password', { email: forgotEmail.trim() });
      setForgotMsg(data.message || 'Kode reset berhasil dibuat.');
      setForgotDevCode(data.dev_code || '');
      setForgotStep('confirm');
    } catch (err) {
      setForgotErr(err.response?.data?.message || 'Gagal meminta kode reset password.');
    } finally {
      setForgotLoading(false);
    }
  };

  const handleConfirmReset = async (e) => {
    e.preventDefault();
    setForgotErr('');
    setForgotMsg('');
    if (forgotPassword !== forgotPasswordConfirm) {
      setForgotErr('Konfirmasi password baru tidak sama.');
      return;
    }
    setForgotLoading(true);
    try {
      const { data } = await api.post('/reset-password', {
        email: forgotEmail.trim(),
        code: forgotCode.trim(),
        password: forgotPassword,
        password_confirmation: forgotPasswordConfirm,
      });
      setForgotMsg(data.message || 'Password berhasil diperbarui.');
      setTimeout(() => {
        setShowForgotModal(false);
        setEmail(forgotEmail.trim());
        setPassword('');
      }, 1500);
    } catch (err) {
      const firstErr = Object.values(err.response?.data?.errors || {})[0]?.[0];
      setForgotErr(firstErr || err.response?.data?.message || 'Gagal mengubah password.');
    } finally {
      setForgotLoading(false);
    }
  };

  useEffect(() => {
    const controller = new AbortController();
    api.get('/branding', { signal: controller.signal })
      .then(({ data }) => setBranding(data))
      .catch(() => {});
    return () => controller.abort();
  }, []);

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
              <img src={getStoreLogo(branding.logo_version)} alt="" />
            </div>
            <div>
              <div className="eyebrow">Point of Sale</div>
              <div className="brand-name">{storeName}</div>
              {branding.tagline && <div className="brand-tagline">{branding.tagline}</div>}
            </div>
          </div>

          <div className="brand-copy">
            <h2>Kasir dan Inventaris</h2>
            <p>Catat pesanan, pembayaran, dan stok harian.</p>
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
            <span>{storeName} POS</span>
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

                <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: '-6px', marginBottom: '6px' }}>
                  <button
                    type="button"
                    onClick={openForgotModal}
                    style={{
                      background: 'none',
                      border: 'none',
                      color: 'var(--brand)',
                      fontSize: '0.84rem',
                      fontWeight: 600,
                      cursor: 'pointer',
                      padding: 0,
                    }}
                  >
                    Lupa Password?
                  </button>
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

      {/* modal lupa password */}
      {showForgotModal && (
        <div className="modal-backdrop" onClick={() => setShowForgotModal(false)}>
          <div className="modal-sheet" style={{ maxWidth: '440px' }} onClick={(e) => e.stopPropagation()}>
            <div className="modal-head">
              <div>
                <h3>Reset Password Akun</h3>
                <p>Kasir juga dapat meminta Admin mereset password di menu Kelola Akun.</p>
              </div>
              <button type="button" className="modal-close" onClick={() => setShowForgotModal(false)}>
                ✕
              </button>
            </div>
            <div className="modal-body">
              {forgotErr && <div className="login-error show" style={{ marginBottom: '12px' }}>{forgotErr}</div>}
              {forgotMsg && (
                <div
                  style={{
                    background: '#eef8f1',
                    color: '#1f6f43',
                    border: '1px solid #bfe6cb',
                    borderRadius: '10px',
                    padding: '10px 12px',
                    fontSize: '0.85rem',
                    marginBottom: '12px',
                  }}
                >
                  {forgotMsg}
                  {forgotDevCode && (
                    <div style={{ marginTop: '6px', fontWeight: 700 }}>
                      Kode Reset (Mode Lokal): <span style={{ letterSpacing: '2px' }}>{forgotDevCode}</span>
                    </div>
                  )}
                </div>
              )}

              {forgotStep === 'request' ? (
                <form onSubmit={handleRequestReset}>
                  <label className="field">
                    <span className="field-label">Email Akun Terdaftar</span>
                    <input
                      type="email"
                      required
                      className="input"
                      placeholder="nama@versikopi.test"
                      value={forgotEmail}
                      onChange={(e) => setForgotEmail(e.target.value)}
                    />
                  </label>
                  <button
                    type="submit"
                    disabled={forgotLoading}
                    className="btn btn-primary btn-block"
                    style={{ marginTop: '16px' }}
                  >
                    {forgotLoading ? 'Memproses...' : 'Kirim Kode Reset 6 Digit'}
                  </button>
                </form>
              ) : (
                <form onSubmit={handleConfirmReset}>
                  <label className="field">
                    <span className="field-label">Kode Reset 6 Digit</span>
                    <input
                      type="text"
                      required
                      maxLength={6}
                      className="input"
                      placeholder="Contoh: 123456"
                      value={forgotCode}
                      onChange={(e) => setForgotCode(e.target.value)}
                    />
                  </label>
                  <label className="field" style={{ marginTop: '12px' }}>
                    <span className="field-label">Password Baru (min. 6 karakter)</span>
                    <input
                      type="password"
                      required
                      minLength={6}
                      className="input"
                      placeholder="Masukkan password baru"
                      value={forgotPassword}
                      onChange={(e) => setForgotPassword(e.target.value)}
                    />
                  </label>
                  <label className="field" style={{ marginTop: '12px' }}>
                    <span className="field-label">Ulangi Password Baru</span>
                    <input
                      type="password"
                      required
                      minLength={6}
                      className="input"
                      placeholder="Ketik ulang password baru"
                      value={forgotPasswordConfirm}
                      onChange={(e) => setForgotPasswordConfirm(e.target.value)}
                    />
                  </label>
                  <div style={{ display: 'flex', gap: '8px', marginTop: '16px' }}>
                    <button
                      type="button"
                      className="btn btn-secondary"
                      onClick={() => setForgotStep('request')}
                    >
                      Minta Ulang
                    </button>
                    <button
                      type="submit"
                      disabled={forgotLoading}
                      className="btn btn-primary"
                      style={{ flex: 1 }}
                    >
                      {forgotLoading ? 'Menyimpan...' : 'Simpan Password Baru'}
                    </button>
                  </div>
                </form>
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
