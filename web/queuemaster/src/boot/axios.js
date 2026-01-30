import { defineBoot } from '#q-app/wrappers'
import axios from 'axios'

// ===== CONFIGURAÇÃO DA API =====
// Cria uma instância do axios já configurada para nossa API
const api = axios.create({
  // URL base da API - todas as chamadas vão usar isso como prefixo
  // Exemplo: api.get('/auth/login') vai chamar http://localhost/api/v1/auth/login
  baseURL: 'http://localhost/api/v1',
  
  // Timeout de 10 segundos
  timeout: 10000,
  
  // Headers padrão
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
})

// ===== INTERCEPTOR DE REQUEST =====
// Executa ANTES de cada requisição
api.interceptors.request.use(
  (config) => {
    // Pega o token do localStorage
    const token = localStorage.getItem('access_token')
    
    // Se tiver token, adiciona no header Authorization
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    
    return config
  },
  (error) => {
    return Promise.reject(error)
  }
)

// ===== INTERCEPTOR DE RESPONSE =====
// Executa DEPOIS de cada resposta
api.interceptors.response.use(
  (response) => {
    // Resposta OK, só retorna
    return response
  },
  (error) => {
    // Se receber 401 (não autorizado), limpa tokens e redireciona para login
    if (error.response?.status === 401) {
      localStorage.removeItem('access_token')
      localStorage.removeItem('refresh_token')
      localStorage.removeItem('user')
      
      // Só redireciona se não estiver já na página de login
      if (window.location.pathname !== '/login') {
        window.location.href = '/login'
      }
    }
    
    return Promise.reject(error)
  }
)

export default defineBoot(({ app }) => {
  // Disponibiliza globalmente para usar como this.$axios e this.$api
  app.config.globalProperties.$axios = axios
  app.config.globalProperties.$api = api
})

export { api }
