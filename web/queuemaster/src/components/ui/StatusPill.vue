<template>
  <span
    class="status-pill"
    :class="[
      `status-pill--${variant}`,
      { 'status-pill--clickable': clickable }
    ]"
    @click="onClick"
  >
    <span v-if="dot" class="status-pill__dot" />
    <slot>{{ label }}</slot>
  </span>
</template>

<script>
import { defineComponent } from 'vue'

/**
 * StatusPill — Reusable badge/pill component.
 *
 * Props:
 *  - label: string  — display text
 *  - variant: string — color theme key (positive, negative, warning, info, grey, primary, orange)
 *  - dot: boolean    — show colored dot before label
 *  - clickable: boolean — show pointer cursor and hover effect
 *
 * Emits: click
 */
export default defineComponent({
  name: 'StatusPill',

  props: {
    label: { type: String, default: '' },
    variant: { type: String, default: 'grey' },
    dot: { type: Boolean, default: false },
    clickable: { type: Boolean, default: false },
  },

  emits: ['click'],

  setup(_, { emit }) {
    const onClick = (e) => emit('click', e)
    return { onClick }
  },
})
</script>

<style lang="scss">
.status-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 10px;
  border-radius: 999px;
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.2px;
  line-height: 1.4;
  white-space: nowrap;
  user-select: none;
  transition: filter 0.15s, box-shadow 0.15s;

  &--clickable {
    cursor: pointer;
    &:hover {
      filter: brightness(0.92);
      box-shadow: 0 0 0 3px rgba(0,0,0,0.06);
    }
  }

  // ── Dot ──
  &__dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
  }

  // ── Variants ──
  &--positive {
    background: rgba(34, 197, 94, 0.14);
    color: #16a34a;
    .status-pill__dot { background: #16a34a; }
  }

  &--negative {
    background: rgba(239, 68, 68, 0.14);
    color: #dc2626;
    .status-pill__dot { background: #dc2626; }
  }

  &--warning {
    background: rgba(245, 158, 11, 0.14);
    color: #d97706;
    .status-pill__dot { background: #d97706; }
  }

  &--info {
    background: rgba(59, 130, 246, 0.14);
    color: #2563eb;
    .status-pill__dot { background: #2563eb; }
  }

  &--grey {
    background: rgba(107, 114, 128, 0.14);
    color: #6b7280;
    .status-pill__dot { background: #6b7280; }
  }

  &--primary {
    background: var(--qm-brand-light, rgba(99, 102, 241, 0.14));
    color: var(--qm-brand, #6366f1);
    .status-pill__dot { background: var(--qm-brand, #6366f1); }
  }

  &--orange {
    background: rgba(249, 115, 22, 0.14);
    color: #ea580c;
    .status-pill__dot { background: #ea580c; }
  }
}

// Dark theme adjustments
[data-theme="dark"] {
  .status-pill {
    &--positive { background: rgba(34, 197, 94, 0.18); color: #4ade80; .status-pill__dot { background: #4ade80; } }
    &--negative { background: rgba(239, 68, 68, 0.18); color: #f87171; .status-pill__dot { background: #f87171; } }
    &--warning  { background: rgba(245, 158, 11, 0.18); color: #fbbf24; .status-pill__dot { background: #fbbf24; } }
    &--info     { background: rgba(59, 130, 246, 0.18); color: #60a5fa; .status-pill__dot { background: #60a5fa; } }
    &--grey     { background: rgba(156, 163, 175, 0.18); color: #9ca3af; .status-pill__dot { background: #9ca3af; } }
    &--orange   { background: rgba(249, 115, 22, 0.18); color: #fb923c; .status-pill__dot { background: #fb923c; } }

    &--clickable:hover {
      filter: brightness(1.1);
      box-shadow: 0 0 0 3px rgba(255,255,255,0.08);
    }
  }
}
</style>
