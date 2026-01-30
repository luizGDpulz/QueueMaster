<template>
  <div class="error-page">
    <div class="error-content">
      <div class="error-code">404</div>
      <div class="error-message">Página não encontrada</div>
      <p class="error-description">
        A página que você está procurando não existe ou foi movida.
      </p>
      <q-btn
        class="soft-btn soft-btn-primary q-mt-lg"
        unelevated
        to="/app"
        label="Voltar ao Início"
        no-caps
      />
    </div>
  </div>
</template>

<script>
import { defineComponent } from 'vue'

export default defineComponent({
  name: 'ErrorNotFound',
})
</script>

<style lang="scss" scoped>
.error-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--qm-bg-secondary);
  padding: 2rem;
}

.error-content {
  text-align: center;
}

.error-code {
  font-size: 8rem;
  font-weight: 800;
  color: var(--qm-text-primary);
  line-height: 1;
  opacity: 0.3;
}

.error-message {
  font-size: 1.5rem;
  font-weight: 600;
  color: var(--qm-text-primary);
  margin-top: 1rem;
}

.error-description {
  color: var(--qm-text-muted);
  margin-top: 0.5rem;
}
</style>
