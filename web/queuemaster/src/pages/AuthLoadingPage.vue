<template>
  <div class="auth-loading">
    <!-- Logo animado -->
    <div class="auth-loading-logo">
      <img src="../assets/logo.svg" alt="QueueMaster" class="logo-pulse" />
    </div>
    
    <!-- Skeleton do layout do app -->
    <div class="skeleton-app">
      <!-- Sidebar skeleton -->
      <div class="skeleton-sidebar">
        <div class="skeleton-item skeleton-logo"></div>
        <div class="skeleton-item skeleton-nav"></div>
        <div class="skeleton-item skeleton-nav short"></div>
        <div class="skeleton-item skeleton-nav"></div>
        <div class="skeleton-item skeleton-nav short"></div>
        <div class="skeleton-spacer"></div>
        <div class="skeleton-item skeleton-user"></div>
      </div>
      
      <!-- Content skeleton -->
      <div class="skeleton-content">
        <!-- Header -->
        <div class="skeleton-header">
          <div class="skeleton-item skeleton-title"></div>
          <div class="skeleton-item skeleton-avatar"></div>
        </div>
        
        <!-- Cards -->
        <div class="skeleton-cards">
          <div class="skeleton-card">
            <div class="skeleton-item skeleton-card-icon"></div>
            <div class="skeleton-item skeleton-card-text"></div>
            <div class="skeleton-item skeleton-card-number"></div>
          </div>
          <div class="skeleton-card">
            <div class="skeleton-item skeleton-card-icon"></div>
            <div class="skeleton-item skeleton-card-text"></div>
            <div class="skeleton-item skeleton-card-number"></div>
          </div>
          <div class="skeleton-card">
            <div class="skeleton-item skeleton-card-icon"></div>
            <div class="skeleton-item skeleton-card-text"></div>
            <div class="skeleton-item skeleton-card-number"></div>
          </div>
        </div>
        
        <!-- Table skeleton -->
        <div class="skeleton-table">
          <div class="skeleton-item skeleton-table-header"></div>
          <div class="skeleton-item skeleton-table-row"></div>
          <div class="skeleton-item skeleton-table-row"></div>
          <div class="skeleton-item skeleton-table-row"></div>
        </div>
      </div>
    </div>
    
    <!-- Loading text -->
    <div class="auth-loading-text">
      <span>Autenticando</span>
      <span class="dots"><span>.</span><span>.</span><span>.</span></span>
    </div>
  </div>
</template>

<script>
import { defineComponent } from 'vue'

export default defineComponent({
  name: 'AuthLoadingPage'
})
</script>

<style lang="scss" scoped>
.auth-loading {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: var(--qm-bg-primary);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  z-index: 9999;
}

// ===== LOGO ANIMADO =====
.auth-loading-logo {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  z-index: 10;
  
  .logo-pulse {
    width: 80px;
    height: 80px;
    animation: pulse 1.5s ease-in-out infinite;
  }
}

@keyframes pulse {
  0%, 100% {
    transform: scale(1);
    opacity: 1;
  }
  50% {
    transform: scale(1.1);
    opacity: 0.8;
  }
}

// ===== SKELETON DO APP =====
.skeleton-app {
  width: 100%;
  height: 100%;
  display: flex;
  opacity: 0.4;
}

.skeleton-sidebar {
  width: 260px;
  height: 100%;
  background: var(--qm-surface);
  padding: 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
  border-right: 1px solid var(--qm-border);
}

.skeleton-content {
  flex: 1;
  padding: 2rem;
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

// ===== SKELETON ITEMS =====
.skeleton-item {
  background: linear-gradient(
    90deg,
    var(--qm-bg-tertiary) 25%,
    var(--qm-border) 50%,
    var(--qm-bg-tertiary) 75%
  );
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: 0.5rem;
}

@keyframes shimmer {
  0% {
    background-position: 200% 0;
  }
  100% {
    background-position: -200% 0;
  }
}

// Sidebar items
.skeleton-logo {
  width: 100%;
  height: 48px;
  margin-bottom: 1rem;
}

.skeleton-nav {
  width: 100%;
  height: 40px;
  
  &.short {
    width: 70%;
  }
}

.skeleton-spacer {
  flex: 1;
}

.skeleton-user {
  width: 100%;
  height: 56px;
}

// Header
.skeleton-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.skeleton-title {
  width: 200px;
  height: 32px;
}

.skeleton-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
}

// Cards
.skeleton-cards {
  display: flex;
  gap: 1.5rem;
}

.skeleton-card {
  flex: 1;
  background: var(--qm-surface);
  border: 1px solid var(--qm-border);
  border-radius: 1rem;
  padding: 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.skeleton-card-icon {
  width: 40px;
  height: 40px;
  border-radius: 0.5rem;
}

.skeleton-card-text {
  width: 60%;
  height: 16px;
}

.skeleton-card-number {
  width: 40%;
  height: 28px;
}

// Table
.skeleton-table {
  background: var(--qm-surface);
  border: 1px solid var(--qm-border);
  border-radius: 1rem;
  padding: 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
  flex: 1;
}

.skeleton-table-header {
  width: 100%;
  height: 40px;
}

.skeleton-table-row {
  width: 100%;
  height: 48px;
}

// ===== LOADING TEXT =====
.auth-loading-text {
  position: absolute;
  bottom: 2rem;
  font-weight: 700;
  font-size: 0.9rem;
  color: var(--qm-text-muted);
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.dots span {
  animation: blink 1.4s infinite;
  
  &:nth-child(2) {
    animation-delay: 0.2s;
  }
  
  &:nth-child(3) {
    animation-delay: 0.4s;
  }
}

@keyframes blink {
  0%, 100% {
    opacity: 0.2;
  }
  50% {
    opacity: 1;
  }
}

// ===== RESPONSIVO =====
@media (max-width: 768px) {
  .skeleton-sidebar {
    display: none;
  }
  
  .skeleton-cards {
    flex-direction: column;
  }
  
  .auth-loading-logo .logo-pulse {
    width: 64px;
    height: 64px;
  }
}
</style>
