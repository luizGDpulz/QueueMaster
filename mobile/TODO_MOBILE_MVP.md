# QueueMaster Mobile MVP - Pendencias Reais

Atualizado em 2026-03-29

## Fechado no app

- base Android em `mobile` com Compose + Material 3
- login Google real
- sessao com cookies persistidos
- fluxo real de entrada na fila por QR
- leitura de QR dentro do app
- entrada manual por codigo
- status real da fila com polling
- saida real da fila
- profile real com avatar vindo da API
- `Settings` na bottom bar
- `Notifications` na bottom bar
- `Profile` como tela interna
- modo claro, escuro e seguir sistema
- notificacoes in-app agrupadas por fluxo
- notificacoes locais do aparelho
- restauracao de sessao e fila ativa
- restauracao da fila ativa consultando o backend
- bloqueio de apenas uma fila ativa por usuario
- header principal com logo + nome + avatar
- logos light/dark alinhados ao web
- botoes principais e badges alinhados aos tokens do web
- fluxos de notificacao separados por entrada real da fila

## Pendencias reais do mobile

### Fluxo e sessao

- fechar o redirecionamento global quando a sessao expirar
- revisar estados de erro de auth para nao deixar tela presa
- validar reabertura do app no telefone em todos os cenarios principais

### Fila

- validar no servidor publicado o `POST /api/v1/queues/join` sem `queueId`
- revisar mensagens finais de:
  - codigo invalido
  - fila fechada
  - ja esta na fila
  - sessao expirada
- tratar visualmente QR invalido ou expirado

### Notificacoes

- decidir se a preferencia de notificacoes tambem sobe para o backend
- implementar push real com FCM
- registrar token do device no backend
- atualizar/remover token quando necessario
- evitar duplicidade entre push remoto e inbox local

### Tema e branding

- validar light/dark no telefone em todas as telas principais
- revisar launcher icon e splash
- preparar, depois, a troca dinamica da cor principal da marca como no web

### UX final

- revisar copy final em portugues
- revisar paddings, contrastes e tamanhos de toque
- revisar navegacao back
- revisar estados sem internet e erro de rede

## Pendencias que podem exigir backend/deploy

- publicar no servidor a versao mais recente da API se o `join` sem `queueId` ainda nao estiver la
- revisar fluxo global de sessao expirada no backend, se houver comportamento inconsistente
- definir o contrato final para push notifications por device
