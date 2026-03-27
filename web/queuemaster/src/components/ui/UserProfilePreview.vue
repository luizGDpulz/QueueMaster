<template>
  <teleport to="body">
    <transition name="profile-preview">
      <div v-if="visible" class="profile-preview-backdrop" @click="close">
        <div class="profile-preview" :style="posStyle" @click.stop>
          <!-- Loading -->
          <div v-if="loadingProfile" class="profile-preview__loading">
            <q-spinner-dots color="primary" size="28px" />
          </div>

          <template v-else-if="profile">
            <!-- Header -->
            <div class="profile-preview__header">
              <div class="profile-preview__avatar">
                <img v-if="avatarUrl" :src="avatarUrl" alt="" @error="avatarError = true" />
                <span v-else class="profile-preview__initials">{{ initials }}</span>
              </div>
              <div class="profile-preview__identity">
                <span class="profile-preview__name">{{ profile.name }}</span>
                <span class="profile-preview__email">{{ profile.email }}</span>
              </div>
              <q-btn flat round dense icon="close" size="sm" class="profile-preview__close" @click="close" />
            </div>

            <!-- Body -->
            <div class="profile-preview__body">
              <div v-if="profile.role" class="profile-preview__row">
                <q-icon name="badge" size="16px" />
                <span>{{ roleLabel }}</span>
              </div>
              <div v-if="profile.phone" class="profile-preview__row">
                <q-icon name="phone" size="16px" />
                <span>{{ profile.phone }}</span>
              </div>
              <div v-if="profile.created_at" class="profile-preview__row">
                <q-icon name="calendar_today" size="16px" />
                <span>Membro desde {{ formatDate(profile.created_at) }}</span>
              </div>
            </div>
          </template>

          <!-- Error -->
          <div v-else class="profile-preview__error">
            <q-icon name="error_outline" size="24px" />
            <span>Perfil não encontrado</span>
          </div>
        </div>
      </div>
    </transition>
  </teleport>
</template>

<script>
import { defineComponent, ref, computed, watch } from 'vue'
import { api } from 'boot/axios'
import { resolveUserAvatarUrl } from 'src/utils/userAvatar'

/**
 * UserProfilePreview — Floating card with brief user profile info.
 *
 * Props:
 *  - modelValue: boolean   — visibility (v-model)
 *  - userId: number|string — user ID to fetch
 *  - userData: object      — pre-loaded user data (skip fetch)
 *  - position: {x, y}     — anchor position for the card
 *
 * Emits: update:modelValue
 */
export default defineComponent({
  name: 'UserProfilePreview',

  props: {
    modelValue: { type: Boolean, default: false },
    userId: { type: [Number, String], default: null },
    userData: { type: Object, default: null },
    position: { type: Object, default: () => ({ x: 0, y: 0 }) },
  },

  emits: ['update:modelValue'],

  setup(props, { emit }) {
    const profile = ref(null)
    const loadingProfile = ref(false)
    const avatarError = ref(false)

    const visible = computed({
      get: () => props.modelValue,
      set: (v) => emit('update:modelValue', v),
    })

    const posStyle = computed(() => {
      const { x, y } = props.position
      // Position near click, clamped to viewport
      const maxX = Math.min(x, window.innerWidth - 320)
      const maxY = Math.min(y, window.innerHeight - 260)
      return {
        top: `${Math.max(8, maxY)}px`,
        left: `${Math.max(8, maxX)}px`,
      }
    })

    const initials = computed(() => {
      const name = profile.value?.name || ''
      return name.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase() || '?'
    })

    const avatarUrl = computed(() => {
      if (avatarError.value) return null
      return resolveUserAvatarUrl(profile.value) || null
    })

    const roleLabel = computed(() => {
      const roles = { admin: 'Administrador', manager: 'Gerente', professional: 'Profissional', client: 'Cliente' }
      return roles[profile.value?.role] || profile.value?.role || ''
    })

    const formatDate = (d) => d ? new Date(d).toLocaleDateString('pt-BR') : '-'

    const close = () => { visible.value = false }

    const fetchProfile = async () => {
      if (props.userData) {
        profile.value = props.userData
        return
      }
      if (!props.userId) return
      loadingProfile.value = true
      avatarError.value = false
      try {
        const { data } = await api.get(`/users/${props.userId}`)
        if (data?.success) {
          profile.value = data.data?.user || data.data || null
        }
      } catch {
        profile.value = null
      } finally {
        loadingProfile.value = false
      }
    }

    watch(() => props.modelValue, (v) => {
      if (v) fetchProfile()
    })

    return { visible, profile, loadingProfile, avatarError, posStyle, initials, avatarUrl, roleLabel, formatDate, close }
  },
})
</script>

<style lang="scss">
.profile-preview-backdrop {
  position: fixed;
  inset: 0;
  z-index: 9998;
}

.profile-preview {
  position: fixed;
  z-index: 9999;
  width: 300px;
  background: var(--qm-surface);
  border: 1px solid var(--qm-border);
  border-radius: 14px;
  box-shadow: var(--qm-shadow-xl, 0 20px 40px rgba(0,0,0,0.15));
  overflow: hidden;

  &__loading,
  &__error {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    gap: 0.5rem;
    color: var(--qm-text-muted);
    font-size: 0.8125rem;
  }

  &__header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 1rem 0.75rem;
    position: relative;
  }

  &__close {
    position: absolute;
    top: 8px;
    right: 8px;
    opacity: 0.5;
    &:hover { opacity: 1; }
  }

  &__avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: var(--qm-brand-light);
    color: var(--qm-brand);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    overflow: hidden;

    img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
  }

  &__initials {
    font-weight: 700;
    font-size: 0.875rem;
  }

  &__identity {
    display: flex;
    flex-direction: column;
    min-width: 0;
    padding-right: 24px;
  }

  &__name {
    font-weight: 600;
    font-size: 0.9375rem;
    color: var(--qm-text-primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  &__email {
    font-size: 0.75rem;
    color: var(--qm-text-muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  &__body {
    padding: 0.5rem 1rem 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    border-top: 1px solid var(--qm-border);
  }

  &__row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.8125rem;
    color: var(--qm-text-secondary);

    .q-icon { color: var(--qm-text-muted); }
  }
}

// Transition
.profile-preview-enter-active,
.profile-preview-leave-active {
  transition: opacity 0.15s ease;
  .profile-preview { transition: transform 0.15s ease, opacity 0.15s ease; }
}
.profile-preview-enter-from,
.profile-preview-leave-to {
  opacity: 0;
  .profile-preview { transform: scale(0.95); opacity: 0; }
}
</style>
