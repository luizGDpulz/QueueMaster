import { defineRouter } from '#q-app/wrappers'
import {
  createRouter,
  createMemoryHistory,
  createWebHistory,
  createWebHashHistory,
} from 'vue-router'
import routes from './routes'
import { api } from 'boot/axios'

/*
 * If not building with SSR mode, you can
 * directly export the Router instantiation;
 *
 * The function below can be async too; either use
 * async/await or return a Promise which resolves
 * with the Router instance.
 */

// Flag para evitar processar o token múltiplas vezes
let googleTokenProcessed = false

const resolveAccessRole = (user) => {
  if (!user || typeof user !== 'object') return null
  if (user.role === 'admin' || user.effective_role === 'admin') return 'admin'
  return user.effective_role || user.role || null
}

const parseStoredUser = () => {
  const rawUser = localStorage.getItem('user')
  if (!rawUser) return null

  try {
    return JSON.parse(rawUser)
  } catch {
    localStorage.removeItem('user')
    return null
  }
}

const fetchFreshUser = async () => {
  try {
    const response = await api.get('/auth/me')
    const user = response.data?.data?.user || null
    if (user) {
      localStorage.setItem('user', JSON.stringify(user))
    } else {
      localStorage.removeItem('user')
    }
    return user
  } catch {
    localStorage.removeItem('user')
    return null
  }
}

export default defineRouter(function (/* { store, ssrContext } */) {
  const createHistory = process.env.SERVER
    ? createMemoryHistory
    : process.env.VUE_ROUTER_MODE === 'history'
      ? createWebHistory
      : createWebHashHistory

  const Router = createRouter({
    scrollBehavior: (to, from, savedPosition) => {
      if (savedPosition) {
        return savedPosition
      }

      if (to.path === from.path) {
        return false
      }

      return { left: 0, top: 0 }
    },
    routes,

    // Leave this as is and make changes in quasar.conf.js instead!
    // quasar.conf.js -> build -> vueRouterMode
    // quasar.conf.js -> build -> publicPath
    history: createHistory(process.env.VUE_ROUTER_BASE),
  })

  // Navigation guard para capturar Google OAuth redirect
  Router.beforeEach(async (to, from, next) => {
    // Verifica se há id_token no hash (Google OAuth redirect)
    // O hash completo fica em window.location.hash, não em to.hash
    const fullHash = window.location.hash
    
    if (!googleTokenProcessed && fullHash && fullHash.includes('id_token=')) {
      googleTokenProcessed = true
      console.log('Google OAuth redirect interceptado no router')
      
      // Primeiro, redireciona para a página de loading
      // Limpa o hash antes para não ficar feio na URL
      window.history.replaceState(null, '', window.location.pathname + '#/auth/loading')
      
      // Extrair o token - remover #/ ou # do início
      const cleanHash = fullHash.replace(/^#\/?/, '')
      const params = new URLSearchParams(cleanHash)
      const idToken = params.get('id_token')
      
      if (idToken) {
        // Ir para página de loading enquanto autentica
        next('/auth/loading')
        
        try {
          const response = await api.post('/auth/google', {
            id_token: idToken
          })

          console.log('Login sucesso:', response.data)

          const { user } = response.data.data

          // Salvar user (tokens são httpOnly cookies)
          localStorage.setItem('user', JSON.stringify(user))

          // Redirecionar para o app
          Router.push('/app')
          
        } catch (error) {
          console.error('Erro ao autenticar com Google:', error)
          Router.push('/login')
        }
        return
      }
    }
    
    const requiredRoles = Array.isArray(to.meta?.roles) ? to.meta.roles : null

    if (requiredRoles) {
      let user = parseStoredUser()
      let accessRole = resolveAccessRole(user)

      if (!requiredRoles.includes(accessRole)) {
        user = await fetchFreshUser()
        accessRole = resolveAccessRole(user)
      }

      if (!requiredRoles.includes(accessRole)) {
        next(user ? '/app' : '/login')
        return
      }
    }

    next()
  })

  return Router
})
