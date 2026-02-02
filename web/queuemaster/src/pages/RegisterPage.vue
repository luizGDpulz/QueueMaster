<template>
  <q-page class="register-page">
    
    <!-- Botão toggle tema -->
    <button class="theme-toggle" @click="toggleTheme" :title="isDark ? 'Modo claro' : 'Modo escuro'">
      <q-icon :name="isDark ? 'light_mode' : 'dark_mode'" size="20px" />
    </button>
    
    <div class="register-container fade-in">
      
      <div class="register-card soft-card">
        
        <!-- Header -->
        <div class="register-header">
          <h1 class="register-title">Criar conta</h1>
          <p class="register-subtitle">Preencha seus dados para começar</p>
        </div>

        <!-- Formulário -->
        <q-form @submit.prevent="onSubmit" class="register-form">
          
          <!-- Campo Nome -->
          <div class="input-group">
            <label class="input-label">Nome completo</label>
            <q-input
              v-model="name"
              placeholder="Seu nome"
              outlined
              class="soft-input"
              :rules="[val => !!val || 'Nome é obrigatório']"
              lazy-rules
              hide-bottom-space
            />
          </div>

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
              placeholder="Mínimo 6 caracteres"
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

          <!-- Campo Confirmar Senha -->
          <div class="input-group">
            <label class="input-label">Confirmar senha</label>
            <q-input
              v-model="confirmPassword"
              :type="showPassword ? 'text' : 'password'"
              placeholder="Repita a senha"
              outlined
              class="soft-input"
              :rules="[
                val => !!val || 'Confirme a senha',
                val => val === password || 'Senhas não conferem'
              ]"
              lazy-rules
              hide-bottom-space
            />
          </div>

          <!-- Mensagem de erro -->
          <div v-if="errorMessage" class="error-message">
            <q-icon name="error_outline" size="18px" class="q-mr-xs" />
            {{ errorMessage }}
          </div>

          <!-- Mensagem de sucesso -->
          <div v-if="successMessage" class="success-message">
            <q-icon name="check_circle" size="18px" class="q-mr-xs" />
            {{ successMessage }}
          </div>

          <!-- Botão Submit -->
          <q-btn
            type="submit"
            label="CRIAR CONTA"
            class="soft-btn soft-btn-primary full-width register-btn"
            size="lg"
            :loading="loading"
            :disable="loading"
            no-caps
          />

        </q-form>

        <!-- Link para login -->
        <div class="login-link">
          <span class="text-muted-custom">Já tem uma conta?</span>
          <router-link to="/login" class="link-primary">
            Entrar
          </router-link>
        </div>

      </div>

      <!-- Footer -->
      <div class="register-footer">
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
  name: 'RegisterPage',

  setup() {
    const router = useRouter()

    // Variáveis do formulário
    const name = ref('')
    const email = ref('')
    const password = ref('')
    const confirmPassword = ref('')
    const showPassword = ref(false)
    const loading = ref(false)
    const errorMessage = ref('')
    const successMessage = ref('')

    // ===== TEMA DARK/LIGHT =====
    const isDark = ref(false)

    onMounted(() => {
      const savedTheme = localStorage.getItem('theme')
      if (savedTheme) {
        isDark.value = savedTheme === 'dark'
      } else {
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

    // ===== REGISTRO =====
    const onSubmit = async () => {
      errorMessage.value = ''
      successMessage.value = ''
      loading.value = true

      try {
        const response = await api.post('/auth/register', {
          name: name.value,
          email: email.value,
          password: password.value
        })

        console.log('Registro sucesso:', response.data)
        
        successMessage.value = 'Conta criada com sucesso! Redirecionando...'

        setTimeout(() => {
          router.push('/login')
        }, 2000)

      } catch (error) {
        console.error('Erro no registro:', error)
        errorMessage.value = error.response?.data?.message || 'Erro ao criar conta'
      } finally {
        loading.value = false
      }
    }

    return {
      name,
      email,
      password,
      confirmPassword,
      showPassword,
      loading,
      errorMessage,
      successMessage,
      isDark,
      toggleTheme,
      onSubmit
    }
  }
})
</script>

<style lang="scss" scoped>
// ===== REGISTER PAGE - Neutral Grayscale =====

.register-page {
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
.register-container {
  width: 100%;
  max-width: 420px;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.register-card {
  width: 100%;
  padding: 2.5rem;
}

// ===== HEADER =====
.register-header {
  text-align: center;
  margin-bottom: 2rem;
}

.register-title {
  font-size: 1.75rem;
  font-weight: 700;
  color: var(--qm-text-primary);
  margin: 0 0 0.5rem 0;
}

.register-subtitle {
  font-size: 0.95rem;
  color: var(--qm-text-secondary);
  margin: 0;
}

// ===== FORM =====
.register-form {
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

.success-message {
  display: flex;
  align-items: center;
  padding: 0.75rem 1rem;
  background: rgba(34, 197, 94, 0.1);
  border: 1px solid rgba(34, 197, 94, 0.3);
  border-radius: 0.75rem;
  color: #22c55e;
  font-size: 0.875rem;
}

// ===== BOTÃO =====
.register-btn {
  margin-top: 0.5rem;
  padding: 0.875rem 1.5rem;
  font-size: 0.875rem;
  font-weight: 700;
  letter-spacing: 0.5px;
}

// ===== LINKS =====
.login-link {
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

.register-footer {
  margin-top: 2rem;
  font-size: 0.8rem;
  color: var(--qm-text-muted);
}

// ===== RESPONSIVO =====
@media (max-width: 480px) {
  .register-card {
    padding: 1.5rem;
  }
  
  .register-title {
    font-size: 1.5rem;
  }
  
  .theme-toggle {
    top: 1rem;
    right: 1rem;
  }
}
</style>
