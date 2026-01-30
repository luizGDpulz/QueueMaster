<template>
  <!-- 
    Layout simples sem menu lateral
    Usado para páginas de autenticação (login, registro)
  -->
  <q-layout view="hHh lpR fFf">
    <q-page-container>
      <!-- router-view = Aqui será renderizada a página atual -->
      <router-view />
    </q-page-container>
  </q-layout>
</template>

<script>
import { defineComponent } from 'vue'

export default defineComponent({
  name: 'AuthLayout'
})
</script>
