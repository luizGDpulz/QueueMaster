<template>
  <q-page class="login-page">
    
    <!-- Botão toggle tema (canto superior direito) -->
    <button class="theme-toggle" @click="toggleTheme" :title="isDark ? 'Modo claro' : 'Modo escuro'">
      <q-icon :name="isDark ? 'light_mode' : 'dark_mode'" size="20px" />
    </button>
    
    <div class="login-container fade-in">
      
      <div class="login-card soft-card">
        
        <!-- Header -->
        <div class="login-header">
          <h1 class="login-title">Bem-vindo de volta</h1>
          <p class="login-subtitle">Entre com seu email e senha para continuar</p>
        </div>

        <!-- Formulário -->
        <q-form @submit.prevent="onSubmit" class="login-form">
          
          <!-- Campo Email -->
          <div class="input-group">
            <label class="input-label">Email</label>
            <q-input
              v-model="email"
              type="email"
              placeholder="seu@email.com"
              outlined
              class="soft-input"
              :rules="[
                val => !!val || 'Email é obrigatório',
                val => /.+@.+\..+/.test(val) || 'Email inválido'
              ]"
              lazy-rules
              hide-bottom-space
            />
          </div>

          <!-- Campo Senha -->
          <div class="input-group">
            <label class="input-label">Senha</label>
            <q-input
              v-model="password"
              :type="showPassword ? 'text' : 'password'"
              placeholder="••••••••"
              outlined
              class="soft-input"
              :rules="[
                val => !!val || 'Senha é obrigatória',
                val => val.length >= 6 || 'Mínimo 6 caracteres'
              ]"
              lazy-rules
              hide-bottom-space
            >
              <template #append>
                <q-icon
                  :name="showPassword ? 'visibility_off' : 'visibility'"
                  class="cursor-pointer icon-muted"
                  size="20px"
                  @click="showPassword = !showPassword"
                />
              </template>
            </q-input>
          </div>

          <!-- Lembrar-me -->
          <div class="remember-row">
            <q-checkbox
              v-model="rememberMe"
              label="Lembrar-me"
              dense
              class="checkbox-custom"
            />
          </div>

          <!-- Mensagem de erro -->
          <div v-if="errorMessage" class="error-message">
            <q-icon name="error_outline" size="18px" class="q-mr-xs" />
            {{ errorMessage }}
          </div>

          <!-- Botão Submit -->
          <q-btn
            type="submit"
            label="ENTRAR"
            class="soft-btn soft-btn-primary full-width login-btn"
            size="lg"
            :loading="loading"
            :disable="loading"
            no-caps
          />

        </q-form>

        <!-- Link para registro -->
        <div class="register-link">
          <span class="text-muted-custom">Não tem uma conta?</span>
          <router-link to="/register" class="link-primary">
            Cadastre-se
          </router-link>
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

    // Variáveis do formulário
    const email = ref('')
    const password = ref('')
    const showPassword = ref(false)
    const rememberMe = ref(false)
    const loading = ref(false)
    const errorMessage = ref('')

    // ===== TEMA DARK/LIGHT =====
    const isDark = ref(false)

    // Carrega preferência salva ou detecta do sistema
    onMounted(() => {
      const savedTheme = localStorage.getItem('theme')
      if (savedTheme) {
        isDark.value = savedTheme === 'dark'
      } else {
        // Detecta preferência do sistema
        isDark.value = window.matchMedia('(prefers-color-scheme: dark)').matches
      }
      applyTheme()
    })

    const applyTheme = () => {
      document.documentElement.setAttribute('data-theme', isDark.value ? 'dark' : 'light')
    }

    const toggleTheme = () => {
      isDark.value = !isDark.value
      localStorage.setItem('theme', isDark.value ? 'dark' : 'light')
      applyTheme()
    }

    // ===== LOGIN =====
    const onSubmit = async () => {
      errorMessage.value = ''
      loading.value = true

      try {
        const response = await api.post('/auth/login', {
          email: email.value,
          password: password.value
        })

        console.log('Login sucesso:', response.data)

        const { access_token, refresh_token, user } = response.data.data
        localStorage.setItem('access_token', access_token)
        localStorage.setItem('refresh_token', refresh_token)
        localStorage.setItem('user', JSON.stringify(user))

        router.push('/app')

      } catch (error) {
        console.error('Erro no login:', error)
        errorMessage.value = error.response?.data?.message || 'Email ou senha incorretos'
      } finally {
        loading.value = false
      }
    }

    return {
      email,
      password,
      showPassword,
      rememberMe,
      loading,
      errorMessage,
      isDark,
      toggleTheme,
      onSubmit
    }
  }
})
</script>

<style lang="scss" scoped>
// ===== LOGIN PAGE - Neutral Grayscale =====

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
}

// ===== HEADER =====
.login-header {
  text-align: center;
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

// ===== FORM =====
.login-form {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.input-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.input-label {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--qm-text-primary);
}

.icon-muted {
  color: var(--qm-text-muted);
}

.remember-row {
  display: flex;
  align-items: center;
}

.checkbox-custom {
  color: var(--qm-text-secondary);
}

// ===== MENSAGENS =====
.error-message {
  display: flex;
  align-items: center;
  padding: 0.75rem 1rem;
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.3);
  border-radius: 0.75rem;
  color: #ef4444;
  font-size: 0.875rem;
}

// ===== BOTÃO =====
.login-btn {
  margin-top: 0.5rem;
  padding: 0.875rem 1.5rem;
  font-size: 0.875rem;
  font-weight: 700;
  letter-spacing: 0.5px;
}

// ===== LINKS =====
.register-link {
  text-align: center;
  margin-top: 1.5rem;
  font-size: 0.9rem;
  
  .link-primary {
    color: var(--qm-text-primary);
    font-weight: 600;
    text-decoration: none;
    margin-left: 0.25rem;
    transition: opacity 0.2s ease;
    
    &:hover {
      opacity: 0.7;
      text-decoration: underline;
    }
  }
}

.text-muted-custom {
  color: var(--qm-text-muted);
}

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
}
</style>
