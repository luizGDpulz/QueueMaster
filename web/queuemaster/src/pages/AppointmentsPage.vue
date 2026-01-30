<template>
  <q-page class="page-placeholder">
    <div class="page-header">
      <h1 class="page-title">Agendamentos</h1>
    </div>
    <div class="placeholder-content soft-card">
      <q-icon name="event" size="64px" class="placeholder-icon" />
      <h2 class="placeholder-title">Gerenciamento de Agendamentos</h2>
      <p class="placeholder-text">Em breve você poderá gerenciar todos os agendamentos aqui.</p>
    </div>
  </q-page>
</template>

<script>
import { defineComponent } from 'vue'

export default defineComponent({
  name: 'AppointmentsPage'
})
</script>

<style lang="scss" scoped>
.page-placeholder {
  padding: 0 1.5rem 1.5rem;
}

.page-header {
  margin-bottom: 1.5rem;
}

.page-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--qm-text-primary);
  margin: 0;
}

.placeholder-content {
  padding: 4rem 2rem;
  text-align: center;
}

.placeholder-icon {
  color: var(--qm-text-disabled);
  margin-bottom: 1.5rem;
}

.placeholder-title {
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--qm-text-primary);
  margin: 0 0 0.5rem;
}

.placeholder-text {
  color: var(--qm-text-muted);
  margin: 0;
}
</style>
