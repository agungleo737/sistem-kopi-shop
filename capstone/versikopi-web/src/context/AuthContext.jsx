import { useEffect, useState } from 'react'
import AuthContext from './auth-context'
import api from '../lib/api'

export function AuthProvider({ children }) {
  const [user, setUser] = useState(() => {
    try {
      return JSON.parse(localStorage.getItem('user'))
    } catch {
      return null
    }
  })

  // verifikasi sesi aktif ke server
  useEffect(() => {
    const token = localStorage.getItem('token')
    if (!token) return
    api.get('/me')
      .then(({ data }) => {
        if (data?.id) {
          localStorage.setItem('user', JSON.stringify(data))
          setUser(data)
        }
      })
      .catch(() => {
        localStorage.removeItem('token')
        localStorage.removeItem('user')
        setUser(null)
      })
  }, [])

  const login = async ({ email, password, role }) => {
    const { data } = await api.post('/login', { email, password, role })
    localStorage.setItem('token', data.token)
    localStorage.setItem('user', JSON.stringify(data.user))
    setUser(data.user)
    return data.user
  }

  const logout = async () => {
    try {
      await api.post('/logout')
    } catch {
      // Revoke the local session even if the server token has already expired.
    }
    localStorage.removeItem('token')
    localStorage.removeItem('user')
    setUser(null)
  }

  return (
    <AuthContext.Provider value={{ user, login, logout }}>
      {children}
    </AuthContext.Provider>
  )
}