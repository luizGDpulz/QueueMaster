<template>
  <teleport to="body">
    <transition name="ctx-menu">
      <div
        v-if="visible"
        ref="menuRef"
        class="context-menu"
        :style="positionStyle"
        @click.stop
      >
        <div class="context-menu-content">
          <!-- Header (optional) -->
          <div v-if="title" class="context-menu-header">
            <span class="context-menu-title">{{ title }}</span>
            <span v-if="subtitle" class="context-menu-subtitle">{{ subtitle }}</span>
          </div>

          <div v-if="title" class="context-menu-divider" />

          <!-- Menu Items -->
          <template v-for="(item, index) in items" :key="item.key || index">
            <div v-if="item.separator" class="context-menu-divider" />
            <div
              v-else-if="!item.hidden"
              class="context-menu-item-wrap"
              @mouseenter="item.children ? openSub(item, $event) : closeSub()"
            >
              <button
                class="context-menu-item"
                :class="{
                  'context-menu-item--danger': item.danger,
                  'context-menu-item--disabled': item.disabled,
                  'context-menu-item--active': activeSubKey === item.key,
                }"
                :disabled="item.disabled"
                @click="item.children ? openSub(item, $event) : onItemClick(item)"
              >
                <q-icon v-if="item.icon" :name="item.icon" size="18px" class="context-menu-item-icon" />
                <span class="context-menu-item-label">{{ item.label }}</span>
                <q-icon v-if="item.children" name="chevron_right" size="16px" class="context-menu-item-arrow" />
                <q-icon v-else-if="item.external" name="open_in_new" size="14px" class="context-menu-item-ext" />
              </button>

              <!-- Submenu -->
              <transition name="ctx-sub">
                <div
                  v-if="item.children && activeSubKey === item.key"
                  ref="subMenuRef"
                  class="context-menu-sub"
                  :style="subStyle"
                >
                  <div class="context-menu-content">
                    <template v-for="(child, ci) in item.children" :key="child.key || ci">
                      <div v-if="child.separator" class="context-menu-divider" />
                      <button
                        v-else-if="!child.hidden"
                        class="context-menu-item"
                        :class="{
                          'context-menu-item--danger': child.danger,
                          'context-menu-item--disabled': child.disabled,
                          'context-menu-item--checked': child.checked,
                        }"
                        :disabled="child.disabled"
                        @click="onItemClick(child)"
                      >
                        <q-icon v-if="child.icon" :name="child.icon" size="18px" class="context-menu-item-icon" />
                        <span class="context-menu-item-label">{{ child.label }}</span>
                        <q-icon v-if="child.checked" name="check" size="16px" class="context-menu-item-check" />
                      </button>
                    </template>
                  </div>
                </div>
              </transition>
            </div>
          </template>
        </div>
      </div>
    </transition>

    <!-- Backdrop -->
    <div v-if="visible" class="context-menu-backdrop" @click="close" @contextmenu.prevent="close" />
  </teleport>
</template>

<script>
import { defineComponent, ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue'

export default defineComponent({
  name: 'ContextMenu',

  props: {
    modelValue: { type: Boolean, default: false },
    items: { type: Array, default: () => [] },
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    position: { type: Object, default: () => ({ x: 0, y: 0 }) },
  },

  emits: ['update:modelValue', 'select'],

  setup(props, { emit }) {
    const menuRef = ref(null)
    const subMenuRef = ref(null)
    const adjustedPos = ref({ x: 0, y: 0 })
    const activeSubKey = ref(null)
    const subStyle = ref({})

    const visible = computed({
      get: () => props.modelValue,
      set: (v) => emit('update:modelValue', v),
    })

    const positionStyle = computed(() => ({
      top: `${adjustedPos.value.y}px`,
      left: `${adjustedPos.value.x}px`,
    }))

    const adjustPosition = async () => {
      // Place menu to the right of the click, vertically centered on the click point
      const rawX = props.position.x + 8
      const rawY = props.position.y
      adjustedPos.value = { x: rawX, y: rawY }
      await nextTick()

      if (!menuRef.value) return
      const rect = menuRef.value.getBoundingClientRect()
      const vw = window.innerWidth
      const vh = window.innerHeight
      const margin = 8

      let x = rawX
      let y = rawY - rect.height / 2  // center vertically on click point

      // Clamp horizontal
      if (x + rect.width + margin > vw) {
        x = Math.max(margin, props.position.x - rect.width - 8)
      }
      // Clamp vertical
      if (y < margin) y = margin
      if (y + rect.height + margin > vh) {
        y = Math.max(margin, vh - rect.height - margin)
      }

      adjustedPos.value = { x, y }
    }

    const openSub = async (item, event) => {
      activeSubKey.value = item.key
      await nextTick()

      const btn = event.currentTarget
      const btnRect = btn.getBoundingClientRect()
      const menuRect = menuRef.value?.getBoundingClientRect()
      const vw = window.innerWidth
      const vh = window.innerHeight
      const margin = 8

      // Position sub-menu to the right of the parent menu
      let left = menuRect ? menuRect.width - 6 : btnRect.width

      // Submenu is position:absolute inside .context-menu-item-wrap (position:relative)
      // So top=0 aligns it with the hovered item itself. Use -6 to match padding.
      let top = -6

      // If it would overflow viewport horizontally, show on the left
      if (menuRect && menuRect.right + 200 > vw) {
        left = -(200 - 6)
      }

      // Estimate submenu height (item count * ~36px + padding)
      const childCount = item.children ? item.children.length : 3
      const estimatedSubHeight = childCount * 36 + 16

      // Clamp submenu vertically so it doesn't go below viewport
      const subAbsTop = btnRect.top + top
      if (subAbsTop + estimatedSubHeight + margin > vh) {
        top = top - (subAbsTop + estimatedSubHeight + margin - vh)
      }
      // Also ensure it doesn't go above viewport
      if (btnRect.top + top < margin) {
        top = margin - btnRect.top
      }

      subStyle.value = {
        top: `${top}px`,
        left: `${left}px`,
      }
    }

    const closeSub = () => {
      activeSubKey.value = null
    }

    watch(() => props.modelValue, (v) => {
      if (v) {
        activeSubKey.value = null
        adjustPosition()
      }
    })

    watch(() => props.position, () => {
      if (visible.value) adjustPosition()
    }, { deep: true })

    const close = () => {
      activeSubKey.value = null
      visible.value = false
    }

    const onItemClick = (item) => {
      if (item.disabled) return
      emit('select', item)
      close()
      if (item.action) item.action()
    }

    const handleEsc = (e) => {
      if (e.key === 'Escape' && visible.value) close()
    }

    onMounted(() => document.addEventListener('keydown', handleEsc))
    onUnmounted(() => document.removeEventListener('keydown', handleEsc))

    return { menuRef, subMenuRef, visible, positionStyle, close, onItemClick, activeSubKey, subStyle, openSub, closeSub }
  },
})
</script>

<style lang="scss">
.context-menu {
  position: fixed;
  z-index: 9999;
  min-width: 200px;
  max-width: 280px;
}

.context-menu-content {
  background: var(--qm-surface);
  border: 1px solid var(--qm-border);
  border-radius: 12px;
  box-shadow: var(--qm-shadow-xl);
  padding: 6px;
}

.context-menu-header {
  padding: 10px 12px 6px;
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.context-menu-title {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--qm-text-primary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.context-menu-subtitle {
  font-size: 0.6875rem;
  color: var(--qm-text-muted);
}

.context-menu-divider {
  height: 1px;
  background: var(--qm-border);
  margin: 4px 6px;
}

.context-menu-item-wrap {
  position: relative;
}

.context-menu-item {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 8px 12px;
  border: none;
  background: transparent;
  cursor: pointer;
  border-radius: 8px;
  transition: background 0.15s ease;
  text-align: left;
  font-family: inherit;
  font-size: 0.8125rem;
  color: var(--qm-text-primary);

  &:hover:not(:disabled) {
    background: var(--qm-bg-secondary);
  }

  &--danger {
    color: var(--qm-error) !important;

    .context-menu-item-icon {
      color: var(--qm-error) !important;
    }

    &:hover:not(:disabled) {
      background: rgba(239, 68, 68, 0.08);
    }
  }

  &--disabled {
    opacity: 0.4;
    cursor: not-allowed;
  }

  &--active {
    background: var(--qm-bg-secondary);
  }

  &--checked {
    font-weight: 600;
  }
}

.context-menu-item-icon {
  color: var(--qm-text-muted);
  flex-shrink: 0;
}

.context-menu-item-label {
  flex: 1;
  white-space: nowrap;
  font-weight: 500;
}

.context-menu-item-arrow {
  color: var(--qm-text-muted);
  flex-shrink: 0;
  margin-left: auto;
}

.context-menu-item-check {
  color: var(--qm-brand);
  flex-shrink: 0;
  margin-left: auto;
}

.context-menu-item-ext {
  color: var(--qm-text-muted);
  flex-shrink: 0;
}

.context-menu-sub {
  position: absolute;
  z-index: 10000;
  min-width: 180px;
  max-width: 240px;
}

.context-menu-backdrop {
  position: fixed;
  inset: 0;
  z-index: 9998;
}

// Main transition
.ctx-menu-enter-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}
.ctx-menu-leave-active {
  transition: opacity 0.1s ease, transform 0.1s ease;
}
.ctx-menu-enter-from {
  opacity: 0;
  transform: scale(0.95) translateY(-4px);
}
.ctx-menu-leave-to {
  opacity: 0;
  transform: scale(0.97);
}

// Sub-menu transition
.ctx-sub-enter-active {
  transition: opacity 0.12s ease, transform 0.12s ease;
}
.ctx-sub-leave-active {
  transition: opacity 0.08s ease;
}
.ctx-sub-enter-from {
  opacity: 0;
  transform: translateX(-4px);
}
.ctx-sub-leave-to {
  opacity: 0;
}
</style>
