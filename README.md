<div align="center">

# 🎫 QueueMaster

### Sistema Híbrido de Gerenciamento de Filas e Agendamentos

[![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![Vue.js](https://img.shields.io/badge/Vue.js-3-4FC08D?style=for-the-badge&logo=vue.js&logoColor=white)](https://vuejs.org)
[![Quasar](https://img.shields.io/badge/Quasar-2-1976D2?style=for-the-badge&logo=quasar&logoColor=white)](https://quasar.dev)
[![MariaDB](https://img.shields.io/badge/MariaDB-10.2+-003545?style=for-the-badge&logo=mariadb&logoColor=white)](https://mariadb.org)
[![Google OAuth](https://img.shields.io/badge/Google-OAuth%202.0-4285F4?style=for-the-badge&logo=google&logoColor=white)](https://developers.google.com/identity)
[![License](https://img.shields.io/badge/License-CC%20BY--SA%204.0-lightgrey?style=for-the-badge&logo=creativecommons)](LICENSE)

*Gerencie filas de espera e agendamentos de forma unificada*

[🚀 Início Rápido](#-início-rápido) •
[📖 Documentação](#-documentação) •
[🔌 API](#-api)

</div>

---

## 💡 O Que É

Sistema completo (API + Web App) para gerenciar **filas de espera** e **agendamentos** de forma integrada.

- ✨ **Walk-ins** entram na fila e acompanham posição em tempo real
- 📅 **Agendados** recebem prioridade no horário marcado
- 🔐 **Login com Google** — sem cadastro tradicional
- 📱 **Atualizações em tempo real** via SSE

---

## 🏗️ Estrutura

```
QueueMaster/
├── api/       # Backend PHP (API RESTful + JWT RS256)
├── web/       # Frontend Quasar/Vue 3 (SPA)
├── docs/      # Documentação
└── public/    # Entry point unificado
```

---

## 🚀 Início Rápido

```bash
# Clone
git clone https://github.com/seu-usuario/queuemaster.git
cd queuemaster

# Backend
cd api
composer install
cp .env.example .env     # Configure GOOGLE_CLIENT_ID e SUPER_ADMIN_EMAIL
openssl genrsa -out keys/private.key 2048
openssl rsa -in keys/private.key -pubout -out keys/public.key
php scripts/migrate.php up

# Frontend
cd ../web/queuemaster
npm install
cp .env.example .env     # Configure VITE_GOOGLE_CLIENT_ID
npm run dev
```

📖 **Guia completo:** [Deploy Local (XAMPP)](docs/LOCAL_DEPLOYMENT_XAMPP.md)

---

## 🔐 Autenticação

O QueueMaster usa **Google OAuth 2.0** como único método de login:

1. Configure `SUPER_ADMIN_EMAIL=seu.email@gmail.com` no `.env` **antes** do primeiro login
2. Faça login com essa conta Google
3. Você será automaticamente **admin**

📖 **Detalhes:** [Fluxo Google OAuth](docs/GOOGLE_OAUTH_FLOW.md)

---

## 🔌 API

| Recurso | Endpoints | Descrição |
|---------|-----------|-----------|
| Auth | `/auth/google`, `/auth/refresh` | Autenticação Google OAuth |
| Establishments | CRUD `/establishments` | Estabelecimentos |
| Queues | `/queues`, `/queues/{id}/join` | Filas e entradas |
| Appointments | CRUD `/appointments` | Agendamentos |
| Dashboard | `/dashboard/stats` | Estatísticas |

📖 **Referência completa:** [API Documentation](docs/API_DOCUMENTATION.md)

---

## 📖 Documentação

| Documento | Descrição |
|-----------|-----------|
| [🚀 Deploy XAMPP](docs/LOCAL_DEPLOYMENT_XAMPP.md) | Instalação passo a passo |
| [🔐 Google OAuth Flow](docs/GOOGLE_OAUTH_FLOW.md) | Fluxo de autenticação |
| [📘 API Documentation](docs/API_DOCUMENTATION.md) | Referência de endpoints |
| [🔄 Refresh Token Guide](docs/REFRESH_TOKEN_GUIDE.md) | Rotação de tokens |
| [🧪 Postman Guide](docs/POSTMAN_GUIDE.md) | Como testar a API |
| [🏗️ Architecture](docs/ARCHITECTURE_REFACTORING.md) | Decisões arquiteturais |

---

## 🧪 Testes

```bash
cd api
vendor/bin/phpunit
```

---

## 📄 Licença

**CC BY-SA 4.0** — Pode compartilhar e adaptar, desde que dê créditos e mantenha a mesma licença.

---

<div align="center">

[![CC BY-SA 4.0](https://licensebuttons.net/l/by-sa/4.0/88x31.png)](https://creativecommons.org/licenses/by-sa/4.0/)

[⬆ Voltar ao topo](#-queuemaster)

</div>
