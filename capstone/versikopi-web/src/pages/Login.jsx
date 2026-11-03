import { useState } from 'react'
import { Navigate, useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import logo from '../assets/logo.png'

export default function Login() {
  const { user, login } = useAuth()
  const navigate = useNavigate()

  const [role, setRole] = useState(null) // null | 'kasir' | 'admin'
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)

  if (user) {
    return <Navigate to={user.role === 'admin' ? '/admin' : '/kasir'} replace />
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError('')
    setLoading(true)
    try {
      const u = await login({ email, password, role })
      navigate(u.role === 'admin' ? '/admin' : '/kasir', { replace: true })
    } catch (err) {
      const res = err.response
      setError(
        res?.data?.message ||
          (res ? 'Terjadi kesalahan.' : 'Tidak bisa terhubung ke server.')
      )
    } finally {
      setLoading(false)
    }
  }

  const backToChoice = () => {
    setRole(null)
    setError('')
    setPassword('')
  }

  return (
    <div className="min-h-screen bg-kopi-cream flex items-center justify-center p-4">
      <div className="w-full max-w-sm bg-white rounded-2xl shadow-lg shadow-kopi-dark/5 px-7 py-8">
        {/* Logo */}
        <div className="flex justify-center mb-4">
          <div className="w-20 h-20 rounded-full bg-kopi-cream overflow-hidden flex items-center justify-center shadow">
            <img
              src={logo}
              alt="Versi Kopi"
              className="w-full h-full object-cover"
            />
          </div>
        </div>

        {/* pilih role */}
        {!role && (
          <>
            <h1 className="text-center text-xl font-semibold text-kopi-dark">
              Pilih Akses Login
            </h1>
            <p className="text-center text-xs text-gray-400 mt-1 mb-6">
              Silakan pilih sistem yang ingin Anda akses
            </p>

            <div className="space-y-3">
              <button
                onClick={() => setRole('kasir')}
                className="w-full py-3 rounded-lg bg-kopi-main text-white text-sm font-medium hover:bg-kopi-dark transition cursor-pointer"
              >
                Login sebagai Kasir
              </button>
              <button
                onClick={() => setRole('admin')}
                className="w-full py-3 rounded-lg border border-kopi-main/30 text-kopi-main text-sm font-medium hover:bg-kopi-cream transition cursor-pointer"
              >
                Login sebagai Admin
              </button>
            </div>
          </>
        )}

        {/* form login */}
        {role && (
          <form onSubmit={handleSubmit}>
            <h1 className="text-center text-xl font-semibold text-kopi-dark">
              Login {role === 'admin' ? 'Admin' : 'Kasir'}
            </h1>
            <p className="text-center text-xs text-gray-400 mt-1 mb-6">
              Masukkan email dan password Anda
            </p>

            {error && (
              <div className="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-600 text-xs px-3 py-2">
                {error}
              </div>
            )}

            <label className="block text-xs font-medium text-kopi-dark mb-1">
              Email
            </label>
            <input
              type="email"
              required
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="nama@email.com"
              className="w-full mb-4 px-3 py-2.5 rounded-lg border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-kopi-light"
            />

            <label className="block text-xs font-medium text-kopi-dark mb-1">
              Password
            </label>
            <input
              type="password"
              required
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              placeholder="••••••••"
              className="w-full mb-6 px-3 py-2.5 rounded-lg border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-kopi-light"
            />

            <button
              type="submit"
              disabled={loading}
              className="w-full py-3 rounded-lg bg-kopi-main text-white text-sm font-medium hover:bg-kopi-dark transition disabled:opacity-60 cursor-pointer"
            >
              {loading ? 'Memproses...' : 'Masuk'}
            </button>
            <button
              type="button"
              onClick={backToChoice}
              className="w-full mt-3 py-2 text-xs text-gray-400 hover:text-kopi-main cursor-pointer"
            >
              ← Kembali pilih akses
            </button>
          </form>
        )}
      </div>
    </div>
  )
}