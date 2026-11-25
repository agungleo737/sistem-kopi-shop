import axios from 'axios'

// sanitasi input
const sanitizeValue = (val) => {
  if (typeof val === 'string') {
    const withoutControlCharacters = Array.from(val).filter((character) => {
      const code = character.charCodeAt(0)
      return code === 9 || code === 10 || code === 13 || (code > 31 && code !== 127)
    }).join('')
    return withoutControlCharacters.replace(/<\/?[^>]+(>|$)/g, '').trim()
  }
  if (Array.isArray(val)) {
    return val.map(sanitizeValue)
  }
  if (val && typeof val === 'object') {
    const cleaned = {}
    for (const key of Object.keys(val)) {
      cleaned[key] = sanitizeValue(val[key])
    }
    return cleaned
  }
  return val
}

// konfigurasi api
const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api',
  headers: {
    Accept: 'application/json',
  },
})

// sisipkan token auth
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  if (config.data && typeof config.data === 'object' && !(config.data instanceof FormData)) {
    config.data = sanitizeValue(config.data)
  }
  return config
})

// cek sesi berakhir
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401 && !error.config?.url?.includes('/login')) {
      localStorage.removeItem('token')
      localStorage.removeItem('user')
      if (window.location.pathname !== '/login') {
        window.location.href = '/login'
      }
    }
    return Promise.reject(error)
  }
)

export default api
