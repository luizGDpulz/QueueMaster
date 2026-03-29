# QueueMaster Mobile MVP - TODO Mestre

## Contexto real levantado no repo

- Workspace principal: `C:\xampp\htdocs`
- Pasta de contexto do projeto para IA: `C:\xampp\htdocs\.codex`
- App Android atual: `C:\xampp\htdocs\mobile`
- Backend PHP atual: `C:\xampp\htdocs\api`
- Web app atual: `C:\xampp\htdocs\web\queuemaster`
- Designs Stitch:
  - `C:\Users\luizg\Downloads\stitch-main-enter-queue-screen`
  - `C:\Users\luizg\Downloads\stitch-queue-detail`
  - `C:\Users\luizg\Downloads\stitch-profile`

## Achados importantes

- O `mobile` ainda está no template inicial do Android Studio.
- `MainActivity.kt` ainda renderiza apenas `Hello Android`.
- O tema atual do mobile ainda usa a paleta roxa padrão do template Compose.
- O web app já usa identidade neutra em preto, branco e cinza.
- O logo do web app já existe em SVG e pode ser reaproveitado no mobile.
- O backend já possui fluxo de filas relativamente maduro, com:
  - entrada em fila
  - saída da fila
  - status detalhado da fila
  - códigos de acesso por fila
  - SSE
  - proteção de concorrência

## Alinhamentos já confirmados

- O app vai precisar de tela de login.
- O foco inicial é o app base em `mobile`.
- O visual do mobile deve priorizar preto no branco, neutros e gradientes discretos.
- Podemos reaproveitar ícones, logo e assets do web app.
- Não tentar subir emulador; testes visuais serão feitos no seu telefone.
- A implementação deve seguir em blocos pequenos, sem misturar muitas frentes.

## Divergências entre o MVP desejado e o backend atual

### Autenticação

- O MVP narrado sugere abrir o app e já entrar na fila.
- O backend real exige autenticação para quase tudo em `/api/v1`.
- Exceções públicas atuais:
  - `POST /api/v1/auth/google`
  - `POST /api/v1/auth/refresh`
- Portanto, para o mobile atual, o fluxo real precisa considerar login antes de `join`.

### Endpoints

- O prompt sugeria endpoints `client/*`, mas o backend real usa:
  - `GET /api/v1/auth/me`
  - `POST /api/v1/auth/logout`
  - `GET /api/v1/queues`
  - `GET /api/v1/queues/{id}`
  - `GET /api/v1/queues/{id}/status`
  - `POST /api/v1/queues/{id}/join`
  - `POST /api/v1/queues/{id}/leave`
  - `GET /api/v1/users/{id}`
  - `PUT /api/v1/users/{id}`

### Entrada por código manual

- O backend atual não expõe um endpoint "join by code only".
- O fluxo real disponível hoje é:
  1. descobrir ou já saber o `queueId`
  2. chamar `POST /api/v1/queues/{id}/join`
  3. enviar `access_code` no body, se necessário
- Isso significa que precisamos definir como o mobile descobre a fila a partir do código:
  - opção A: QR/manual carregam `queueId + code`
  - opção B: criar endpoint backend para resolver código -> fila
  - opção C: QR usa URL padronizada contendo ambos

## Mapa real da API que impacta o mobile

### Auth

- `POST /api/v1/auth/google`
- `POST /api/v1/auth/refresh`
- `GET /api/v1/auth/me`
- `POST /api/v1/auth/logout`

### Queue

- `GET /api/v1/queues`
- `GET /api/v1/queues/{id}`
- `GET /api/v1/queues/{id}/status`
- `POST /api/v1/queues/{id}/join`
- `POST /api/v1/queues/{id}/leave`

### Dados de usuário

- `GET /api/v1/users/{id}`
- `PUT /api/v1/users/{id}`
- `GET /api/v1/users/{id}/avatar`

### Descoberta relacionada

- `GET /api/v1/establishments`
- `GET /api/v1/establishments/search`
- `GET /api/v1/establishments/{id}/discover`
- `GET /api/v1/businesses/search`

## Contratos reais úteis para o mobile

### Join queue

- Endpoint: `POST /api/v1/queues/{id}/join`
- Body possível:
  - `access_code`
  - `priority` (staff)
  - `status` (staff)
  - `user_id` (staff)
- Regras reais:
  - usuário autenticado
  - não pode entrar duas vezes na mesma fila ativa
  - fila precisa estar aberta
  - código, se enviado, precisa ser válido

### Queue status

- Endpoint: `GET /api/v1/queues/{id}/status`
- Retorna:
  - `queue`
  - `entries`
  - `entries_serving`
  - `entries_completed`
  - `statistics`
  - `user_entry`
- Para o cliente mobile, o principal é:
  - `queue.name`
  - `queue.establishment_name`
  - `queue.service_name`
  - `statistics.average_wait_time_minutes`
  - `statistics.total_waiting`
  - `user_entry.position`
  - `user_entry.estimated_wait_minutes`

### Leave queue

- Endpoint: `POST /api/v1/queues/{id}/leave`
- Cancela a entrada ativa do usuário autenticado naquela fila.

### Profile

- Melhor fonte inicial para tela Profile:
  - `GET /api/v1/auth/me`
- Se precisarmos editar:
  - avaliar se usamos `PUT /api/v1/users/{id}`
  - validar campos editáveis no backend antes de implementar edição

## Branding e assets reaproveitáveis

### Já existentes no web

- `C:\xampp\htdocs\web\queuemaster\src\assets\logo_dark.svg`
- `C:\xampp\htdocs\web\queuemaster\src\assets\logo_light.svg`
- `C:\xampp\htdocs\web\queuemaster\public\favicon.svg`
- `C:\xampp\htdocs\web\queuemaster\public\icons\*`

### Paleta real observada no web

- Marca padrão light:
  - `--qm-brand: #1a1a1a`
- Fundo principal light:
  - `#ffffff`
- Fundo secundário:
  - `#f5f5f5`
- Texto primário:
  - `#171717`
- Texto secundário:
  - `#525252`
- Borda clara:
  - `#e5e5e5`

### Direção visual para o mobile

- Manter o DNA do web:
  - neutro
  - sofisticado
  - legível
  - contraste alto
- Adaptar o Stitch:
  - manter composição e hierarquia
  - substituir acentos teal por preto, grafite, branco e gradientes discretos
- Evitar:
  - paleta roxa default do Compose
  - visual excessivamente “conceitual”
  - frases de marketing vagas no MVP

## Telas do MVP mobile

### 1. Login

- Tela de entrada
- CTA para autenticação
- estado loading
- estado erro
- persistência de sessão
- decisão futura:
  - Google Sign-In nativo
  - Web flow
  - Custom Tab

### 2. Join Queue

- top bar
- card hero
- CTA escanear QR
- CTA digitar código manualmente
- card informativo
- bottom navigation
- texto mais direto:
  - “Entrar na fila”
  - “Escaneie o QR code ou digite o código”

### 3. Manual Code Entry

- tela ou bottom sheet
- input do código
- validação
- erro amigável
- loading
- navegação para status ao sucesso

### 4. Queue Status

- nome da fila
- estabelecimento
- card principal com posição
- tempo estimado
- pessoas à frente
- detalhes auxiliares
- card/aviso de notificação
- botão sair da fila
- refresh manual
- polling posterior

### 5. Profile

- avatar
- nome
- email
- bloco de dados pessoais
- placeholders de configurações
- logout funcional

## Ordem de desenvolvimento recomendada

## Bloco 0 - Planejamento fechado

### Status 2026-03-29

- [x] estratÃ©gia de autenticaÃ§Ã£o mobile definida com Credential Manager + Sign in with Google
- [ ] resoluÃ§Ã£o de cÃ³digo manual/QR ainda depende do caminho `codigo -> queueId`

- [x] levantar contexto real do repo
- [x] levantar contexto do Stitch
- [x] validar necessidade de login
- [ ] alinhar estratégia de autenticação mobile
- [ ] alinhar estratégia de resolução de código manual/QR

## Bloco 1 - Fundação do mobile

- [x] limpar template padrão do Android Studio
- [x] substituir `Hello Android`
- [x] reorganizar package structure
- [x] criar `navigation/`
- [x] criar `ui/theme/`
- [x] criar `ui/components/`
- [x] criar `core/design/`
- [x] criar `core/model/`
- [x] criar `core/network/`
- [x] criar `core/utils/`
- [x] criar `features/login/`
- [x] criar `features/joinqueue/`
- [x] criar `features/queuestatus/`
- [x] criar `features/profile/`
- [x] criar `features/manualcode/`
- [x] definir convenção de nomes de arquivos Compose
- [x] configurar navegação base
- [x] configurar `Scaffold` base do app
- [x] configurar `WindowInsets`
- [x] configurar preview data helpers

## Bloco 2 - Sistema visual

- [x] remover tema roxo padrão
- [x] criar paleta neutra inspirada no web
- [x] definir superfícies
- [x] definir gradientes discretos
- [x] definir shapes globais
- [x] definir radius dos cards
- [x] definir elevation/shadows
- [x] definir tipografia
- [ ] avaliar fonte do app
- [x] configurar `MaterialTheme`
- [x] criar tokens de spacing
- [ ] criar tokens de icon size
- [x] criar tokens de component heights
- [x] criar helpers de gradiente
- [ ] criar componentização base:
  - [x] `QmPrimaryButton`
  - [x] `QmSecondaryButton`
  - [ ] `QmOutlinedButton`
  - [x] `QmTopBar`
  - [x] `QmBottomBar`
  - [x] `QmCard`
  - [x] `QmInfoCard`
  - [x] `QmStatCard`
  - [ ] `QmAvatar`
  - [x] `QmTextField`
  - [ ] `QmLoadingState`
  - [ ] `QmErrorState`
  - [ ] `QmEmptyState`
  - [ ] `QmSectionTitle`
  - [ ] `QmPill`

## Bloco 3 - Assets

- [ ] importar logo do web para o mobile
- [ ] decidir uso de SVG ou conversão para VectorDrawable
- [ ] criar package/estrutura de assets
- [ ] mapear ícones reaproveitáveis do web
- [ ] definir ícones Material necessários
- [ ] revisar launcher icon
- [ ] revisar splash icon depois

## Bloco 4 - Navegação

- [x] criar `AppRoutes.kt`
- [x] criar `AppNavHost.kt`
- [ ] criar grafo com:
  - [x] `login`
  - [x] `join_queue`
  - [x] `manual_code_entry`
  - [x] `queue_status`
  - [x] `profile`
- [x] criar bottom navigation
- [ ] decidir em quais telas a bottom bar aparece
- [ ] implementar rota inicial baseada em sessão
- [ ] implementar guarda simples:
  - [ ] sem sessão -> login
  - [ ] com sessão -> join queue ou queue status

## Bloco 5 - UI estática

### Login

- [x] reproduzir layout inicial da tela de login
- [x] adaptar para visual QueueMaster
- [x] criar CTA principal
- [x] preparar estado loading
- [x] preparar estado erro

### Join Queue

- [x] reproduzir estrutura do Stitch
- [x] adaptar texto para produto real
- [x] adaptar cor/acento para identidade preta e branca
- [x] criar card hero
- [x] criar CTA de scanner
- [x] criar CTA manual
- [x] criar info card
- [x] encaixar bottom navigation

### Queue Status

- [x] reproduzir estrutura do Stitch
- [x] criar card de posição
- [x] criar bloco de tempo estimado
- [x] criar bloco de pessoas à frente
- [x] criar detalhes adicionais
- [x] criar card de notificação
- [x] criar CTA leave queue
- [x] encaixar bottom navigation

### Profile

- [x] reproduzir estrutura do Stitch
- [x] adaptar avatar
- [x] adaptar cartões de informação
- [x] adaptar seção de settings
- [x] criar CTA sign out
- [x] encaixar bottom navigation

## Bloco 6 - Estado e arquitetura de apresentação

- [x] definir padrão de `UiState`
- [ ] definir padrão de `UiEvent`
- [ ] definir padrão de `UiAction` se necessário
- [x] criar models de domínio iniciais
- [ ] criar DTOs separados do domínio, se necessário
- [x] criar ViewModels por feature
- [x] mockar dados locais primeiro
- [x] manter telas navegáveis sem backend

### Models iniciais

- [x] `AuthenticatedUser`
- [x] `UserProfile`
- [x] `QueueSummary`
- [x] `QueueStatus`
- [x] `QueueUserEntry`
- [x] `QueueStatistics`
- [x] `JoinQueueRequest`
- [x] `JoinQueueResult`
- [x] `ManualCodePayload`

### UiStates

- [x] `LoginUiState`
- [x] `JoinQueueUiState`
- [x] `ManualCodeUiState`
- [x] `QueueStatusUiState`
- [x] `ProfileUiState`

## Bloco 7 - Login real

### Status 2026-03-29

- [x] login Google real implementado com Credential Manager
- [x] backend real `POST /auth/google` integrado com `id_token`
- [x] `LoginViewModel` sem mock, usando API real
- [x] logout chama backend e limpa o estado do provedor Google
- [x] refresh automÃ¡tico de sessÃ£o jÃ¡ estÃ¡ ligado na camada de rede
- [x] falhas de autenticaÃ§Ã£o e bloqueio de usuÃ¡rio jÃ¡ retornam mensagem para a UI
- [x] persistÃªncia de sessÃ£o entre cold starts com cookies locais
- [ ] sessÃ£o expirada ainda precisa fechar o ciclo completo de redirecionamento global

- [ ] escolher estratégia técnica de login no Android
- [ ] validar compatibilidade com backend atual `POST /auth/google`
- [ ] mapear payload exigido (`id_token`)
- [ ] implementar client de auth
- [ ] salvar sessão
- [ ] restaurar sessão ao abrir app
- [ ] implementar refresh se necessário
- [ ] implementar logout
- [ ] tratar falhas de autenticação
- [ ] tratar usuário bloqueado
- [ ] tratar sessão expirada

## Bloco 8 - Entrada manual por código

- [x] decidir formato do input aceito
- [ ] decidir se o usuário digita:
  - [x] só código
  - [ ] código + identificador da fila
  - [ ] URL completa
- [x] implementar parser
- [x] implementar validação local
- [x] implementar estado de loading
- [x] implementar navegação ao sucesso
- [ ] implementar mensagens:
  - [ ] código inválido
  - [ ] fila fechada
  - [ ] já está na fila
  - [ ] sessão expirada
- [ ] publicar no servidor a rota `POST /api/v1/queues/join` sem `queueId`

## Bloco 9 - Integração de fila

- [x] configurar cliente HTTP do mobile
- [x] configurar base URL por ambiente
- [x] configurar serialização JSON
- [x] configurar interceptors/autorização
- [x] configurar tratamento de erro padronizado

### Integração mínima MVP

- [ ] `GET /api/v1/auth/me`
- [ ] `POST /api/v1/queues/{id}/join`
- [ ] `GET /api/v1/queues/{id}/status`
- [ ] `POST /api/v1/queues/{id}/leave`
- [ ] `POST /api/v1/auth/logout`

### Parsing de respostas

- [ ] modelar envelope `success/data/error`
- [ ] mapear `entry`
- [ ] mapear `queue`
- [ ] mapear `statistics`
- [ ] mapear `user_entry`
- [ ] mapear mensagens de erro

## Bloco 10 - Queue Status real

- [x] carregar status ao abrir a tela
- [x] exibir posição atual
- [x] exibir tempo estimado
- [x] exibir pessoas à frente
- [x] exibir nome da fila
- [x] exibir estabelecimento
- [x] exibir serviço, se houver
- [x] exibir CTA leave queue
- [x] tratar ausência de fila ativa
- [x] navegar de volta para join queue se necessário

## Bloco 11 - Profile real

- [ ] carregar `auth/me`
- [ ] popular avatar
- [ ] popular nome
- [ ] popular email
- [ ] popular role summary se for útil
- [ ] implementar logout real
- [ ] decidir o que fica como placeholder no MVP
- [ ] adiar edição de perfil se o backend ainda não estiver bem mapeado

## Bloco 12 - Estado persistido local

- [x] lembrar usuário autenticado
- [x] lembrar última fila ativa, se fizer sentido
- [x] decidir se guarda `queueId` atual localmente
- [x] limpar estado local ao logout
- [x] restaurar fluxo corretamente ao reabrir app

## Bloco 13 - Polling / atualização

- [x] implementar refresh manual
- [x] implementar polling simples
- [x] definir intervalo inicial
- [x] pausar polling fora da tela
- [x] evitar múltiplos polls concorrentes
- [x] tratar erro silencioso sem quebrar tela
- [x] atualizar posição/tempo sem flicker exagerado

## Bloco 14 - QR code

- [x] escolher lib de scanner
- [x] pedir permissão de câmera
- [x] abrir scanner
- [x] ler conteúdo do QR
- [x] parsear payload
- [x] transformar payload em ação de join
- [ ] tratar QR inválido
- [ ] tratar QR expirado
- [ ] tratar fila indisponível

### Formatos possíveis de QR

- [ ] `QMQUEUE:{queueId}:{code}`
- [ ] URL com query params
- [ ] JSON compacto

## Bloco 15 - Revisão do backend antes da fase API

- [ ] revisar de novo `api/routes/api.php`
- [ ] revisar `QueuesController::join`
- [ ] revisar `QueuesController::status`
- [ ] revisar `QueuesController::leave`
- [ ] revisar `AuthController`
- [ ] revisar contrato de cookies/tokens
- [ ] revisar CORS para chamadas do mobile
- [ ] revisar se Android usará cookie, bearer ou ambos
- [ ] revisar se existe endpoint adequado para resolver código -> fila
- [ ] decidir se precisamos alterar backend antes do QR/manual ficar ideal

## Bloco 16 - Textos de produto

- [ ] trocar copy do Stitch por texto de produto real
- [ ] revisar português padrão do app
- [ ] evitar termos conceituais demais
- [ ] padronizar:
  - [ ] entrar na fila
  - [ ] código manual
  - [ ] sair da fila
  - [ ] tempo estimado
  - [ ] pessoas à frente
  - [ ] atualizar

## Bloco 17 - Estados de UX

- [ ] loading inicial
- [ ] loading de botão
- [ ] erro de rede
- [ ] erro de autenticação
- [ ] fila fechada
- [ ] já está na fila
- [ ] código inválido
- [ ] nenhuma fila ativa
- [ ] sem internet
- [ ] sessão expirada
- [ ] empty state de profile parcial

## Bloco 18 - Qualidade e consistência

- [ ] revisar paddings globais
- [ ] revisar tipografia
- [ ] revisar contraste
- [ ] revisar tamanhos de toque
- [ ] revisar edge-to-edge
- [ ] revisar navegação back
- [ ] revisar estados disabled
- [ ] revisar loading placeholders
- [ ] revisar consistência dos ícones
- [ ] revisar previews Compose

## Bloco 19 - Testes locais que eu posso fazer sem emulador

- [ ] compilar o projeto
- [ ] validar imports e build scripts
- [ ] validar navegação sem device
- [ ] validar tipos e erros de compilação
- [ ] validar estrutura de recursos
- [ ] evitar qualquer tentativa de abrir emulador

## Bloco 20 - Perguntas em aberto

- [ ] qual estratégia de login você prefere para o Android?
- [ ] como o mobile vai resolver `codigo manual -> queueId` no backend atual?
- [ ] vamos manter profile apenas leitura no MVP?
- [ ] o app será 100% cliente final ou também terá algum fluxo de staff depois?

## Próxima etapa sugerida

- Próximo passo ideal: executar apenas o `Bloco 1 + Bloco 2 + Bloco 4`.
- Tradução prática:
  - limpar template
  - estruturar pacotes
  - criar tema preto/branco
  - criar navegação base
  - criar componentes reutilizáveis
- Sem API ainda.
