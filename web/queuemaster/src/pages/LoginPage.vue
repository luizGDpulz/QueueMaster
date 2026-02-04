<template>
  <q-page class="login-page">
    
    <!-- Botão toggle tema (canto superior direito) -->
    <button class="theme-toggle" @click="toggleTheme" :title="isDark ? 'Modo claro' : 'Modo escuro'">
      <q-icon :name="isDark ? 'light_mode' : 'dark_mode'" size="20px" />
    </button>
    
    <div class="login-container fade-in">
      
      <div class="login-card soft-card">
        
        <!-- Logo -->
        <div class="login-logo">
          <img src="/icons/logo.svg" alt="QueueMaster" class="logo-image" />
        </div>

        <!-- Header -->
        <div class="login-header">
          <h1 class="login-title">QueueMaster</h1>
          <p class="login-subtitle">Gerencie suas filas de forma inteligente</p>
        </div>

        <!-- Mensagem de erro -->
        <div v-if="errorMessage" class="error-message">
          <q-icon name="error_outline" size="18px" class="q-mr-xs" />
          {{ errorMessage }}
        </div>

        <!-- Botão Google -->
        <div class="google-login-section">
          <q-btn
            @click="handleGoogleLogin"
            class="google-btn"
            size="lg"
            :loading="loading"
            :disable="loading || !googleReady"
            no-caps
            unelevated
          >
            <img src="/icons/google.svg" alt="Google" class="google-icon" />
            <span>Entrar com Google</span>
          </q-btn>
        </div>

        <!-- Info -->
        <div class="login-info">
          <q-icon name="info_outline" size="16px" class="q-mr-xs" />
          <span>Ao entrar, você concorda com nossos termos de uso</span>
        </div>

      </div>

      <!-- Footer -->
      <div class="login-footer">
        <span>© 2026 QueueMaster</span>
      </div>

    </div>
  </q-page>
</template>

<script>
import { defineComponent, ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { api } from 'boot/axios'

export default defineComponent({
  name: 'LoginPage',

  setup() {
    const router = useRouter()

    // State
    const loading = ref(false)
    const errorMessage = ref('')
    const googleReady = ref(false)

    // ===== TEMA DARK/LIGHT =====
    const isDark = ref(false)

    onMounted(() => {
      // Carregar tema
      const savedTheme = localStorage.getItem('theme')
      if (savedTheme) {
        isDark.value = savedTheme === 'dark'
      } else {
        isDark.value = window.matchMedia('(prefers-color-scheme: dark)').matches
      }
      applyTheme()

      // Verificar se há token no hash (redirect do Google OAuth)
      checkForGoogleRedirect()

      // Inicializar Google Identity Services
      initializeGoogle()
    })

    // ===== CAPTURA TOKEN DO REDIRECT =====
    const checkForGoogleRedirect = () => {
      const hash = window.location.hash
      
      // Verifica se o hash contém id_token (formato: #id_token=xxx ou #/id_token=xxx)
      if (hash && hash.includes('id_token=')) {
        console.log('Token detectado no redirect')
        
        // Extrair o token - pode vir como #id_token= ou #/id_token=
        const cleanHash = hash.replace('#/', '#').substring(1)
        const params = new URLSearchParams(cleanHash)
        const idToken = params.get('id_token')
        
        if (idToken) {
          // Limpar o hash da URL para não ficar feio
          window.history.replaceState(null, '', window.location.pathname)
          
          // Autenticar com o backend
          authenticateWithBackend(idToken)
        }
      }
    }

    const applyTheme = () => {
      document.documentElement.setAttribute('data-theme', isDark.value ? 'dark' : 'light')
    }

    const toggleTheme = () => {
      isDark.value = !isDark.value
      localStorage.setItem('theme', isDark.value ? 'dark' : 'light')
      applyTheme()
    }

    // ===== GOOGLE IDENTITY SERVICES =====
    const initializeGoogle = () => {
      // Verificar se já está carregado
      if (window.google?.accounts?.id) {
        setupGoogleClient()
        return
      }

      // Carregar o script do Google
      const script = document.createElement('script')
      script.src = 'https://accounts.google.com/gsi/client'
      script.async = true
      script.defer = true
      script.onload = () => {
        setupGoogleClient()
      }
      script.onerror = () => {
        console.error('Erro ao carregar Google Identity Services')
        errorMessage.value = 'Erro ao carregar serviço de login'
      }
      document.head.appendChild(script)
    }

    const setupGoogleClient = () => {
      const clientId = import.meta.env.VITE_GOOGLE_CLIENT_ID

      if (!clientId) {
        console.error('VITE_GOOGLE_CLIENT_ID não configurado')
        errorMessage.value = 'Configuração de login incompleta'
        return
      }

      try {
        window.google.accounts.id.initialize({
          client_id: clientId,
          callback: handleGoogleCallback,
          auto_select: false,
          cancel_on_tap_outside: true,
          use_fedcm_for_prompt: false, // Desabilita FedCM (causa problemas em localhost)
        })
        
        googleReady.value = true
        console.log('Google Identity Services inicializado')
      } catch (error) {
        console.error('Erro ao inicializar Google:', error)
        errorMessage.value = 'Erro ao configurar login'
      }
    }

    // ===== LOGIN HANDLERS =====
    const handleGoogleLogin = () => {
      if (!googleReady.value) {
        errorMessage.value = 'Aguarde, carregando...'
        return
      }

      errorMessage.value = ''
      
      // Abrir popup do Google
      window.google.accounts.id.prompt((notification) => {
        if (notification.isNotDisplayed()) {
          // Fallback: usar botão renderizado do Google
          console.log('One Tap não disponível, usando popup')
          useGooglePopup()
        } else if (notification.isSkippedMoment()) {
          console.log('Usuário ignorou o prompt')
        } else if (notification.isDismissedMoment()) {
          console.log('Usuário fechou o prompt')
        }
      })
    }

    const useGooglePopup = () => {
      // Usar OAuth redirect como fallback
      // Como usamos hash mode no Vue Router, precisamos redirecionar para a raiz
      // e capturar o token lá
      const clientId = import.meta.env.VITE_GOOGLE_CLIENT_ID
      const redirectUri = window.location.origin + '/' // Redireciona para a raiz
      
      const authUrl = new URL('https://accounts.google.com/o/oauth2/v2/auth')
      authUrl.searchParams.set('client_id', clientId)
      authUrl.searchParams.set('redirect_uri', redirectUri)
      authUrl.searchParams.set('response_type', 'id_token')
      authUrl.searchParams.set('scope', 'openid email profile')
      authUrl.searchParams.set('nonce', generateNonce())
      authUrl.searchParams.set('prompt', 'select_account')
      
      // Redirecionar na mesma janela (mais confiável que popup)
      window.location.href = authUrl.toString()
    }

    const generateNonce = () => {
      const array = new Uint8Array(16)
      crypto.getRandomValues(array)
      return Array.from(array, byte => byte.toString(16).padStart(2, '0')).join('')
    }

    const handleGoogleCallback = async (response) => {
      console.log('Google callback recebido')
      
      if (response.credential) {
        await authenticateWithBackend(response.credential)
      } else {
        errorMessage.value = 'Erro ao obter credenciais do Google'
      }
    }

    const authenticateWithBackend = async (idToken) => {
      loading.value = true
      errorMessage.value = ''

      try {
        const response = await api.post('/auth/google', {
          id_token: idToken
        })

        console.log('Login sucesso:', response.data)

        const { access_token, refresh_token, user, is_new_user } = response.data.data

        // Salvar tokens
        localStorage.setItem('access_token', access_token)
        localStorage.setItem('refresh_token', refresh_token)
        localStorage.setItem('user', JSON.stringify(user))

        // Redirecionar
        if (is_new_user) {
          // Poderia redirecionar para onboarding
          router.push('/app')
        } else {
          router.push('/app')
        }

      } catch (error) {
        console.error('Erro no login:', error)
        
        if (error.response?.status === 403) {
          errorMessage.value = 'Email não verificado pelo Google'
        } else if (error.response?.status === 401) {
          errorMessage.value = 'Token inválido. Tente novamente.'
        } else {
          errorMessage.value = error.response?.data?.message || 'Erro ao fazer login'
        }
      } finally {
        loading.value = false
      }
    }

    return {
      loading,
      errorMessage,
      googleReady,
      isDark,
      toggleTheme,
      handleGoogleLogin
    }
  }
})
</script>

<style lang="scss" scoped>
// ===== LOGIN PAGE - Google OAuth =====

.login-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--qm-bg-secondary);
  padding: 1rem;
  position: relative;
  transition: background-color 0.3s ease;
}

// ===== TOGGLE TEMA =====
.theme-toggle {
  position: absolute;
  top: 1.5rem;
  right: 1.5rem;
  width: 44px;
  height: 44px;
  border-radius: 50%;
  border: 1px solid var(--qm-border);
  background: var(--qm-surface);
  color: var(--qm-text-secondary);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
  box-shadow: var(--qm-shadow-sm);
  
  &:hover {
    background: var(--qm-bg-tertiary);
    transform: scale(1.05);
  }
}

// ===== CONTAINER =====
.login-container {
  width: 100%;
  max-width: 420px;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.login-card {
  width: 100%;
  padding: 2.5rem;
  text-align: center;
}

// ===== LOGO =====
.login-logo {
  margin-bottom: 1.5rem;
  
  .logo-image {
    width: 80px;
    height: 80px;
  }
}

// ===== HEADER =====
.login-header {
  margin-bottom: 2rem;
}

.login-title {
  font-size: 1.75rem;
  font-weight: 700;
  color: var(--qm-text-primary);
  margin: 0 0 0.5rem 0;
}

.login-subtitle {
  font-size: 0.95rem;
  color: var(--qm-text-secondary);
  margin: 0;
}

// ===== GOOGLE BUTTON =====
.google-login-section {
  margin-bottom: 1.5rem;
}

.google-btn {
  width: 100%;
  height: 52px;
  background: var(--qm-surface) !important;
  border: 1px solid var(--qm-border) !important;
  border-radius: 0.75rem !important;
  color: var(--qm-text-primary) !important;
  font-size: 1rem;
  font-weight: 500;
  transition: all 0.2s ease;
  
  &:hover:not(:disabled) {
    background: var(--qm-bg-tertiary) !important;
    border-color: var(--qm-text-muted) !important;
    transform: translateY(-1px);
    box-shadow: var(--qm-shadow-md);
  }
  
  &:disabled {
    opacity: 0.6;
  }
  
  .google-icon {
    width: 20px;
    height: 20px;
    margin-right: 12px;
  }
}

// ===== MENSAGENS =====
.error-message {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0.75rem 1rem;
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.3);
  border-radius: 0.75rem;
  color: #ef4444;
  font-size: 0.875rem;
  margin-bottom: 1.5rem;
}

// ===== INFO =====
.login-info {
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.8rem;
  color: var(--qm-text-muted);
}

// ===== FOOTER =====
.login-footer {
  margin-top: 2rem;
  font-size: 0.8rem;
  color: var(--qm-text-muted);
}

// ===== RESPONSIVO =====
@media (max-width: 480px) {
  .login-card {
    padding: 1.5rem;
  }
  
  .login-title {
    font-size: 1.5rem;
  }
  
  .theme-toggle {
    top: 1rem;
    right: 1rem;
  }
  
  .login-logo .logo-image {
    width: 64px;
    height: 64px;
  }
}
</style>
