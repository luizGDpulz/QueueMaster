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

export default defineRouter(function (/* { store, ssrContext } */) {
  const createHistory = process.env.SERVER
    ? createMemoryHistory
    : process.env.VUE_ROUTER_MODE === 'history'
      ? createWebHistory
      : createWebHashHistory

  const Router = createRouter({
    scrollBehavior: () => ({ left: 0, top: 0 }),
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
    
    next()
  })

  return Router
})
