import { defineBoot } from '#q-app/wrappers'
import axios from 'axios'

// ===== CONFIGURAÇÃO DA API =====
const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://localhost/api/v1',
  timeout: 10000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  },
  // Envia cookies httpOnly automaticamente (access_token + refresh_token)
  withCredentials: true
})

// ===== CONTROLE DE REFRESH =====
let isRefreshing = false
let failedQueue = []

const processQueue = (error) => {
  failedQueue.forEach(prom => {
    if (error) {
      prom.reject(error)
    } else {
      prom.resolve()
    }
  })
  failedQueue = []
}

const redirectToLogin = () => {
  localStorage.removeItem('user')

  if (window.location.pathname !== '/login') {
    window.location.href = '/login'
  }
}

// ===== INTERCEPTOR DE RESPONSE =====
// Refresh automático via httpOnly cookies - nenhum token trafega pelo JS
api.interceptors.response.use(
  (response) => response,
  async (error) => {
    const originalRequest = error.config

    if (error.response?.status === 401 && !originalRequest._retry) {
      // Ignora 401 em rotas de auth que não precisam de refresh (login, refresh, logout)
      const skipRefreshRoutes = ['/auth/google', '/auth/refresh', '/auth/logout']
      if (skipRefreshRoutes.some(route => originalRequest.url?.includes(route))) {
        return Promise.reject(error)
      }

      // Se já está fazendo refresh, enfileira
      if (isRefreshing) {
        return new Promise((resolve, reject) => {
          failedQueue.push({ resolve, reject })
        }).then(() => api(originalRequest))
          .catch(err => Promise.reject(err))
      }

      originalRequest._retry = true
      isRefreshing = true

      try {
        // Refresh: cookies httpOnly são enviados automaticamente pelo browser
        const response = await axios.post(
          `${import.meta.env.VITE_API_URL || 'http://localhost/api/v1'}/auth/refresh`,
          {},
          { withCredentials: true }
        )

        if (response.data?.success) {
          const { user } = response.data.data
          if (user) {
            localStorage.setItem('user', JSON.stringify(user))
          }

          processQueue(null)
          return api(originalRequest)
        } else {
          processQueue(error)
          redirectToLogin()
          return Promise.reject(error)
        }
      } catch (refreshError) {
        processQueue(refreshError)
        redirectToLogin()
        return Promise.reject(refreshError)
      } finally {
        isRefreshing = false
      }
    }

    return Promise.reject(error)
  }
)

export default defineBoot(({ app }) => {
  app.config.globalProperties.$axios = axios
  app.config.globalProperties.$api = api
})

export { api }
