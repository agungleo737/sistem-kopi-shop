import { useAuth } from '../context/AuthContext'

export default function Kasir() {
  const { user, logout } = useAuth()
  return (
    <div className="p-8">
      <h1 className="text-2xl font-bold text-kopi-dark">Halaman Kasir</h1>
      <p className="mt-2 text-gray-600">Halo, {user.name}</p>
      <button
        onClick={logout}
        className="mt-4 px-4 py-2 rounded-lg bg-kopi-main text-white text-sm cursor-pointer"
      >
        Logout
      </button>
    </div>
  )
}