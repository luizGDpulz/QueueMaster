<div align="center">

# 🎫 QueueMaster

### Sistema Híbrido de Gerenciamento de Filas e Agendamentos

[![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![Vue.js](https://img.shields.io/badge/Vue.js-3-4FC08D?style=for-the-badge&logo=vue.js&logoColor=white)](https://vuejs.org)
[![Quasar](https://img.shields.io/badge/Quasar-2-1976D2?style=for-the-badge&logo=quasar&logoColor=white)](https://quasar.dev)
[![MariaDB](https://img.shields.io/badge/MariaDB-10.2+-003545?style=for-the-badge&logo=mariadb&logoColor=white)](https://mariadb.org)
[![JWT](https://img.shields.io/badge/JWT-RS256-000000?style=for-the-badge&logo=jsonwebtokens&logoColor=white)](https://jwt.io)
[![License](https://img.shields.io/badge/License-CC%20BY--SA%204.0-lightgrey?style=for-the-badge&logo=creativecommons)](LICENSE)

*Transforme a experiência de espera dos seus clientes com filas inteligentes e agendamentos integrados*

[🚀 Início Rápido](#-início-rápido) •
[📖 Documentação](#-documentação) •
[🔌 API](#-api-endpoints) •
[🤝 Contribuir](#-contribuindo)

</div>

---

## 💡 O que é o QueueMaster?

O **QueueMaster** é um sistema completo (API + Web App) que resolve um problema comum: **gerenciar filas de espera e agendamentos de forma unificada**.

Imagine uma clínica médica, barbearia ou qualquer estabelecimento que atende tanto clientes que chegam sem hora marcada (walk-in) quanto aqueles com agendamento. O QueueMaster reconcilia ambos os fluxos automaticamente:

- ✨ **Clientes agendados** recebem prioridade no horário marcado
- 🚶 **Walk-ins** preenchem os slots disponíveis
- 📱 **Atualizações em tempo real** via SSE (Server-Sent Events)
- 🔐 **Segurança robusta** com JWT RS256

---

## ✨ Principais Funcionalidades

<table>
<tr>
<td width="50%">

### 🎯 Para Clientes
- Entrar em filas de espera
- Agendar horários com profissionais
- Receber notificações em tempo real
- Acompanhar posição na fila ao vivo
- Fazer check-in de agendamentos

</td>
<td width="50%">

### 🏢 Para Estabelecimentos
- Gerenciar múltiplas filas
- Dashboard com estatísticas
- Chamar próximo cliente
- Controle de profissionais e serviços
- Sistema de prioridades configurável

</td>
</tr>
</table>

---

## 🏗️ Arquitetura

O QueueMaster é um **monorepo** contendo todos os componentes do sistema:

```
QueueMaster/
├── 📁 api/             # Backend PHP (API RESTful)
├── 📁 web/             # Frontend Web (Quasar/Vue 3)
├── 📁 mobile/          # App Mobile (Kotlin) - Futuro
├── 📁 docs/            # Documentação geral
└── 📁 public/          # Entry point unificado
```

| Componente | Tecnologia | Descrição |
|------------|------------|-----------|
| **API** | PHP 8.1+ | API RESTful com JSON |
| **Web App** | Quasar + Vue 3 | SPA responsivo (PWA) |
| **Mobile** | Kotlin + Compose | App Android (futuro) |
| **Banco de Dados** | MariaDB/MySQL | Dados relacionais |
| **Autenticação** | JWT RS256 | Tokens seguros com chaves RSA |
| **Real-time** | SSE | Atualizações instantâneas |
| **Cache** | Redis *(opcional)* | Performance para alta escala |

> 📚 **Quer mais detalhes?** Veja a [Arquitetura Completa](docs/ARCHITECTURE_REFACTORING.md)

---

## 🚀 Início Rápido

### Pré-requisitos

- PHP 8.1+ com extensões: `pdo`, `json`, `openssl`
- MariaDB 10.2+ ou MySQL 5.7+
- Composer
- Node.js 18+ (para o Web App)
- OpenSSL

### Instalação

#### API (Backend)

```bash
# 1. Clone o repositório
git clone https://github.com/yourusername/QueueMaster.git
cd QueueMaster

# 2. Instale as dependências da API
cd api
composer install

# 3. Configure o ambiente
cp .env.example .env
# Edite o .env com suas credenciais

# 4. Execute as migrations
php scripts/migrate.php up

# 5. Volte para a raiz
cd ..
```

#### Web App (Frontend)

```bash
# 1. Entre na pasta web
cd web

# 2. Instale as dependências
npm install

# 3. Inicie em modo de desenvolvimento
npx quasar dev

# Ou para produção
npx quasar build
```

#### Rodando com XAMPP

Configure o Document Root do Apache para `QueueMaster/public/` e acesse:
- 🌐 **Web App:** `http://localhost/`
- 📡 **API:** `http://localhost/api/v1/status`
- 📖 **Swagger:** `http://localhost/swagger/`

> 📚 **Instalação detalhada?** Veja o [Guia de Deploy Local (XAMPP)](docs/LOCAL_DEPLOYMENT_XAMPP.md)

---

## 🔌 API Endpoints

A API segue o padrão REST com versionamento (`/api/v1/`) e respostas JSON padronizadas.

### Principais Recursos

| Recurso | Descrição | Documentação |
|---------|-----------|--------------|
| 🔐 **Auth** | Registro, login, refresh token | [JWT Auth Flow](docs/JWT_AUTH_FLOW.md) |
| 🏢 **Establishments** | CRUD de estabelecimentos | [API Docs](docs/API_DOCUMENTATION.md) |
| 📋 **Queues** | Filas, entrar, sair, chamar próximo | [API Docs](docs/API_DOCUMENTATION.md) |
| 📅 **Appointments** | Agendamentos, check-in, cancelar | [API Docs](docs/API_DOCUMENTATION.md) |
| 👤 **Users** | Gerenciamento de usuários | [CRUD Summary](docs/CRUD_COMPLETE_SUMMARY.md) |
| 🔔 **Notifications** | Sistema de notificações | [API Docs](docs/API_DOCUMENTATION.md) |
| 📡 **Streams** | Eventos em tempo real (SSE) | [API Docs](docs/API_DOCUMENTATION.md) |

### Exemplo de Uso

```bash
# Login
curl -X POST http://localhost:8080/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "cliente@email.com", "password": "senha123"}'

# Entrar na fila (com token)
curl -X POST http://localhost:8080/api/v1/queues/1/join \
  -H "Authorization: Bearer SEU_TOKEN"
```

> 📚 **Documentação completa da API:** [API Documentation](docs/API_DOCUMENTATION.md)  
> 🧪 **Testar com Postman:** [Postman Guide](docs/POSTMAN_GUIDE.md)

---

## 🔐 Segurança

O QueueMaster implementa múltiplas camadas de segurança:

- **JWT RS256** - Tokens assinados com chaves RSA assimétricas
- **Refresh Tokens** - Rotação automática para sessões seguras
- **Rate Limiting** - Proteção contra ataques de força bruta
- **Senhas** - Hash com Argon2id (ou bcrypt como fallback)
- **CORS** - Configuração flexível de origens permitidas

> 📚 **Detalhes de autenticação:** [JWT Auth Flow](docs/JWT_AUTH_FLOW.md) | [Refresh Token Guide](docs/REFRESH_TOKEN_GUIDE.md)

---

## 📂 Estrutura do Projeto

```
QueueMaster/
├── 📁 api/                    # Backend PHP
│   ├── public/                # Entry point da API + Swagger
│   ├── src/
│   │   ├── Controllers/       # Endpoints da API
│   │   ├── Models/            # Entidades (User, Queue...)
│   │   ├── Services/          # Lógica de negócio
│   │   ├── Middleware/        # Auth, Rate Limiting, Roles
│   │   └── Core/              # Router, Database, Request
│   ├── routes/                # Definição de rotas
│   ├── migrations/            # Schema do banco
│   └── tests/                 # Testes PHPUnit
│
├── 📁 web/                    # Frontend Quasar/Vue 3
│   ├── src/
│   │   ├── components/        # Componentes Vue
│   │   ├── pages/             # Páginas da aplicação
│   │   ├── layouts/           # Layouts (Admin, Cliente)
│   │   ├── composables/       # Hooks (useAuth, useQueue)
│   │   └── services/          # API client
│   └── quasar.config.js
│
├── 📁 docs/                   # Documentação
├── 📁 public/                 # Entry point unificado
└── docker-compose.yml
```

> 📚 **Arquitetura detalhada:** [Architecture Refactoring](docs/ARCHITECTURE_REFACTORING.md) | [Models Guide](docs/QUICK_GUIDE_MODELS.md)

---

## 📖 Documentação

| Documento | Descrição |
|-----------|-----------|
| [📘 API Documentation](docs/API_DOCUMENTATION.md) | Referência completa de endpoints |
| [🔐 JWT Auth Flow](docs/JWT_AUTH_FLOW.md) | Fluxo de autenticação detalhado |
| [🔄 Refresh Token Guide](docs/REFRESH_TOKEN_GUIDE.md) | Como funciona a rotação de tokens |
| [🏗️ Architecture](docs/ARCHITECTURE_REFACTORING.md) | Decisões arquiteturais |
| [📦 Models Guide](docs/QUICK_GUIDE_MODELS.md) | Padrão Active Record dos Models |
| [🧪 Postman Guide](docs/POSTMAN_GUIDE.md) | Como testar a API |
| [📋 Swagger Guide](docs/SWAGGER_GUIDE.md) | Documentação interativa |
| [🚀 Deploy XAMPP](docs/LOCAL_DEPLOYMENT_XAMPP.md) | Instalação passo a passo |
| [📄 Proposta (PT-BR)](docs/PROPOSE.md) | Documento de requisitos |
| [📄 Proposal (EN)](docs/PROPOSE_EN.md) | Requirements document |

---

## 🧪 Testes

```bash
# Executar testes da API
cd api
vendor/bin/phpunit

# Com relatório de cobertura
vendor/bin/phpunit --coverage-html coverage/
```

---

## 🤝 Contribuindo

Contribuições são bem-vindas! 

1. Fork o repositório
2. Crie sua branch: `git checkout -b feature/nova-funcionalidade`
3. Commit suas mudanças: `git commit -m 'Adiciona nova funcionalidade'`
4. Push para a branch: `git push origin feature/nova-funcionalidade`
5. Abra um Pull Request

---

## 📄 Licença

Este projeto está licenciado sob a **Creative Commons Attribution-ShareAlike 4.0 International (CC BY-SA 4.0)**.

Isso significa que você pode:
- ✅ **Compartilhar** — copiar e redistribuir o material
- ✅ **Adaptar** — remixar, transformar e criar a partir do material
- ✅ **Uso comercial** — usar para fins comerciais

Desde que:
- 📝 **Atribuição** — dê os devidos créditos ao projeto original
- 🔄 **CompartilhaIgual** — distribua suas contribuições sob a mesma licença

Veja o arquivo [LICENSE](LICENSE) para mais detalhes.

---

<div align="center">

[![CC BY-SA 4.0](https://licensebuttons.net/l/by-sa/4.0/88x31.png)](https://creativecommons.org/licenses/by-sa/4.0/)

[⬆ Voltar ao topo](#-queuemaster)

</div>