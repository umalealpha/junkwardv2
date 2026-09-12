import axios from 'axios'

const apiClient = axios.create({
  baseURL: import.meta.env.VITE_API_URL + '/api/v1',
  headers: { Accept: 'application/json' },
})

apiClient.interceptors.request.use(config => {
  const token = localStorage.getItem('ace_token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

apiClient.interceptors.response.use(
  r => r,
  err => {
    if (err.response?.status === 401) {
      localStorage.removeItem('ace_token')
      window.location.href = import.meta.env.VITE_GRAPHITE_URL || 'https://graphite-v2-fe.alphadirect.co.bw'
    }
    return Promise.reject(err)
  }
)

export default apiClient
