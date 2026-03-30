# Queue Entry History Plan

Atualizado em 2026-03-30

## Objetivo

Criar uma arquitetura server-side para o historico de participacao em filas, compartilhada entre:

- mobile do cliente
- web do cliente
- web do estabelecimento
- futuras notificacoes push e inbox

Sem usar a tabela `notifications` como fonte da verdade do fluxo.

## Diagnostico do estado atual

Hoje o repo tem duas pecas importantes:

1. `queue_entries` ja representa uma participacao real em uma fila.
2. `notifications` ja representa uma entrega ou inbox generica.

No codigo atual:

- `C:\xampp\htdocs\api\src\Models\QueueEntry.php`
- `C:\xampp\htdocs\api\src\Services\QueueService.php`
- `C:\xampp\htdocs\api\src\Controllers\QueuesController.php`

ja existe a logica principal de entrada, chamada, atendimento e saida.

Por outro lado:

- `C:\xampp\htdocs\api\src\Models\Notification.php`
- `C:\xampp\htdocs\api\src\Services\NotificationService.php`
- `C:\xampp\htdocs\api\src\Controllers\NotificationsController.php`

tratam notificacao como mensagem entregue ao usuario, com leitura, listagem, filtros e inbox.

Conclusao:

- `queue_entries` deve continuar sendo a entidade principal da participacao na fila
- `notifications` deve continuar sendo a camada de entrega/inbox
- o historico do fluxo da fila precisa de uma camada propria

## Decisao de arquitetura

### Fonte da verdade

Criar uma nova entidade de timeline:

- `queue_entry_events`

Ela sera a fonte da verdade do historico operacional da entrada na fila.

### Agrupador do fluxo

Cada fluxo sera agrupado por uma unica participacao em fila:

- `queue_entries.id` internamente
- `queue_entries.public_id` externamente

Ou seja:

- cada entrada na fila gera um novo fluxo
- se o usuario entrar na mesma fila novamente em outro dia, sera outro fluxo
- as notificacoes e o historico nao serao mais misturados

## Decisao sobre ID publico

Nao vamos expor `id` sequencial de `queue_entries`.

### Proposta

Adicionar em `queue_entries`:

- `public_id CHAR(26) NOT NULL UNIQUE`

Formato sugerido:

- ULID

Motivos:

- nao expoe sequencia interna
- ordena bem por tempo
- fica curto o suficiente para URL, API e debug
- funciona bem para mobile, web e auditoria

### Regra

- o banco continua usando `id` numerico como PK interna
- API, web e mobile passam a usar `public_id`
- qualquer rota externa nova para detalhe de fluxo deve aceitar `public_id`

### Recomendacao

Se quiser deixar ainda mais blindado para o futuro:

- manter `id` interno para joins
- nunca retornar `id` de `queue_entries` nas respostas publicas do cliente

## Modelo de dados alvo

### Tabela `queue_entries`

Continua sendo a raiz da participacao na fila.

Novos campos recomendados:

- `public_id CHAR(26) NOT NULL UNIQUE`
- `final_status VARCHAR(32) NULL` opcional, se quiser leitura rapida do desfecho

Observacao:

`final_status` e opcional. No MVP, o proprio `status` atual + eventos ja resolvem.

### Nova tabela `queue_entry_events`

Campos recomendados:

```sql
CREATE TABLE queue_entry_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  queue_entry_id BIGINT UNSIGNED NOT NULL,
  queue_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  actor_type ENUM('system','client','staff') NOT NULL DEFAULT 'system',
  event_type VARCHAR(50) NOT NULL,
  payload JSON NULL,
  occurred_at TIMESTAMP NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_qee_entry_time (queue_entry_id, occurred_at, id),
  INDEX idx_qee_user_time (user_id, occurred_at, id),
  INDEX idx_qee_queue_time (queue_id, occurred_at, id),
  CONSTRAINT fk_qee_entry FOREIGN KEY (queue_entry_id) REFERENCES queue_entries(id) ON DELETE CASCADE,
  CONSTRAINT fk_qee_queue FOREIGN KEY (queue_id) REFERENCES queues(id) ON DELETE CASCADE,
  CONSTRAINT fk_qee_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_qee_actor_user FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
);
```

### Event types do MVP

Sugestao inicial:

- `joined`
- `next_up`
- `called`
- `serving_started`
- `completed`
- `left`
- `cancelled`
- `no_show`

Possiveis futuros:

- `requeued`
- `priority_changed`
- `position_adjusted`
- `queue_closed_for_entry`

## Regra de negocio principal

Evento nasce de mudanca real de estado, nao de polling.

Isso significa:

- `joined` quando cria a entrada
- `called` quando staff chama
- `serving_started` quando o atendimento realmente comeca
- `completed` quando conclui
- `left` quando o cliente sai da fila
- `cancelled` quando staff cancela
- `no_show` quando staff marca ausencia

`next_up` deve ser emitido por regra de negocio, nao por refresh da tela.

Sugestao de regra:

- emitir uma unica vez quando a entrada ficar com `people_ahead <= 1`

## O que continua fora dessa tabela

### Nao colocar aqui

- badge de lida/nao lida
- configuracao de push do device
- status de entrega FCM/Web Push
- notificacao do navegador
- inbox generica do sistema

Isso continua em camadas separadas.

## Relacao com `notifications`

### Decisao

Nao usar `notifications` como historico de fila.

### Papel correto de `notifications`

`notifications` continua servindo para:

- inbox generica
- notificacoes do sistema
- entregas derivadas de eventos
- marcacao de leitura
- preferencias de notificacao

### Como integrar sem misturar os dominios

`queue_entry_events` gera o fato de negocio.

Depois, se quiser:

- um projector cria notificacoes derivadas em `notifications`
- ou um sender envia push
- ou um stream SSE envia atualizacao em tempo real

Mas o dado original continua em `queue_entry_events`.

### Recomendacao pratica

Na primeira fase:

- nao mexer em `notifications`
- fazer mobile e web lerem o historico diretamente de `queue_entries` + `queue_entry_events`

Na segunda fase:

- criar projecoes opcionais para inbox/push

## Contrato de leitura sugerido

### Cliente autenticado

- `GET /api/v1/queue-entries/current`
- `GET /api/v1/queue-entries/history`
- `GET /api/v1/queue-entries/{publicId}`
- `GET /api/v1/queue-entries/{publicId}/events`

### Estabelecimento / staff

- `GET /api/v1/queues/{queueId}/entries/{entryPublicId}`
- `GET /api/v1/queues/{queueId}/entries/{entryPublicId}/events`

### Join

Ao entrar na fila, `POST /api/v1/queues/join` deve retornar:

- `entry_public_id`
- `queue_public_context` se houver
- estado atual da entrada

Exemplo de resposta minima:

```json
{
  "entry": {
    "public_id": "01JQH8M4E6R1TQ8VK6A9G4YF2N",
    "status": "waiting",
    "queue_public_id": "01JQH8FZX2AW3D0P2W7QPN4Q9B",
    "queue_name": "Walk-in consultation",
    "position": 3,
    "estimated_wait_minutes": 20
  }
}
```

## Contrato de listagem do historico

Para o cliente, `GET /api/v1/queue-entries/history` deve devolver grupos de fluxo, nao uma lista solta de eventos.

Estrutura sugerida:

```json
{
  "items": [
    {
      "entry_public_id": "01JQH8M4E6R1TQ8VK6A9G4YF2N",
      "queue_name": "Walk-in consultation",
      "establishment_name": "Clinica Pulz",
      "status": "completed",
      "joined_at": "2026-03-30T10:00:00Z",
      "ended_at": "2026-03-30T10:43:00Z",
      "last_event_type": "completed",
      "last_event_at": "2026-03-30T10:43:00Z",
      "can_join_again": true
    }
  ]
}
```

Depois, a tela de detalhe chama:

- `GET /api/v1/queue-entries/{publicId}/events`

## Contrato do detalhe do fluxo

Estrutura sugerida:

```json
{
  "entry": {
    "public_id": "01JQH8M4E6R1TQ8VK6A9G4YF2N",
    "queue_name": "Walk-in consultation",
    "establishment_name": "Clinica Pulz",
    "status": "completed",
    "joined_at": "2026-03-30T10:00:00Z",
    "called_at": "2026-03-30T10:31:00Z",
    "served_at": "2026-03-30T10:34:00Z",
    "completed_at": "2026-03-30T10:43:00Z"
  },
  "events": [
    {
      "type": "joined",
      "occurred_at": "2026-03-30T10:00:00Z",
      "payload": {
        "position": 5
      }
    },
    {
      "type": "next_up",
      "occurred_at": "2026-03-30T10:27:00Z",
      "payload": {
        "people_ahead": 1
      }
    },
    {
      "type": "called",
      "occurred_at": "2026-03-30T10:31:00Z",
      "payload": {}
    },
    {
      "type": "serving_started",
      "occurred_at": "2026-03-30T10:34:00Z",
      "payload": {}
    },
    {
      "type": "completed",
      "occurred_at": "2026-03-30T10:43:00Z",
      "payload": {}
    }
  ]
}
```

## Como isso entra no mobile

### Aba `Notifications`

A aba atual do mobile deve migrar de historico local para historico server-side.

Fluxo recomendado:

- lista principal mostra grupos por `entry_public_id`
- cada card mostra o ultimo evento e o contexto da fila
- ao tocar, abre detalhe do fluxo com timeline

### Notificacoes do aparelho

Continuam separadas.

Elas podem ser derivadas de eventos como:

- `next_up`
- `called`
- `serving_started`
- `completed`

Mas o historico exibido no app vem do backend, nao do armazenamento local.

### Beneficio

Mesmo se o app for desinstalado ou trocar de aparelho:

- o historico continua existindo
- a timeline continua consistente

## Como isso entra no web do cliente

Criar uma aba de historico no ambiente do cliente.

Escopo sugerido:

- manter a entrada por codigo no fluxo principal atual
- adicionar uma aba `Historico`
- listar filas ja participadas
- permitir abrir detalhe do fluxo
- opcionalmente mostrar CTA `Entrar novamente` se a fila ainda estiver aberta e o produto permitir esse atalho

Importante:

- o historico nao substitui a entrada por codigo
- ele complementa a experiencia do cliente

## Como isso entra no web do estabelecimento

O estabelecimento precisa ver o fluxo da pessoa dentro da fila.

Escopo sugerido:

- no detalhe de cada entrada da fila, mostrar timeline da participacao
- no painel da fila, indicar ultimo evento relevante
- em entradas concluidas, permitir revisar o historico completo

Beneficios:

- auditoria simples
- atendimento com contexto
- clareza de quando o cliente entrou, foi chamado, iniciou atendimento e saiu

## Especificacao de escrita de eventos

### Pontos de escrita no backend

Os pontos naturais hoje estao em:

- `C:\xampp\htdocs\api\src\Services\QueueService.php`
- `C:\xampp\htdocs\api\src\Controllers\QueuesController.php`

### Recomendacao

Criar um servico dedicado:

- `QueueEntryEventService`

Responsabilidades:

- registrar eventos de maneira padronizada
- receber `queue_entry_id`, `event_type`, `actor_type`, `actor_user_id`, `payload`
- garantir ordem de persistencia dentro da mesma transacao quando necessario

### Regra de implementacao

Sempre que possivel:

- gravar o evento na mesma transacao da mudanca de estado

Exemplos:

- `join` cria `queue_entry` e `joined`
- `callNext` atualiza para `called` e grava `called`
- iniciar atendimento grava `serving_started`
- concluir grava `completed`
- sair grava `left`

## Projecoes futuras

Depois da camada base pronta, podem existir projeções:

### 1. Inbox generica

`queue_entry_events` -> `notifications`

Somente para eventos que realmente merecem alerta.

### 2. Push mobile

`queue_entry_events` -> fila de envio -> FCM

### 3. Browser/web push

`queue_entry_events` -> notificacao navegador

### 4. SSE

`queue_entry_events` -> stream para cliente/staff

## Fases de implementacao recomendadas

## Fase 1 - Schema e IDs publicos

Entregas:

- migration para `queue_entries.public_id`
- migration para `queue_entry_events`
- gerador de ULID no backend
- schema consolidado direto na `0001`

Arquivos provaveis:

- `C:\xampp\htdocs\api\migrations\`
- `C:\xampp\htdocs\api\src\Models\QueueEntry.php`
- novo model `QueueEntryEvent.php`
- novo util de ULID

## Fase 2 - Escrita de eventos

Entregas:

- criar `QueueEntryEventService`
- instrumentar `join`
- instrumentar `callNext`
- instrumentar inicio de atendimento
- instrumentar conclusao
- instrumentar saida/cancelamento/no_show

Arquivos provaveis:

- `C:\xampp\htdocs\api\src\Services\QueueService.php`
- `C:\xampp\htdocs\api\src\Controllers\QueuesController.php`
- novo `C:\xampp\htdocs\api\src\Services\QueueEntryEventService.php`

## Fase 3 - Endpoints de leitura

Entregas:

- `GET /api/v1/queue-entries/current`
- `GET /api/v1/queue-entries/history`
- `GET /api/v1/queue-entries/{publicId}`
- `GET /api/v1/queue-entries/{publicId}/events`
- endpoints staff para detalhe de entrada

Arquivos provaveis:

- novo `QueueEntriesController.php`
- `C:\xampp\htdocs\api\routes\api.php`

## Fase 4 - Web do cliente

Entregas:

- aba `Historico`
- lista agrupada por fluxo
- detalhe da participacao
- CTA de reentrada so se fizer sentido no produto

Arquivos provaveis:

- composables de cliente no web
- paginas de filas do cliente
- possivel reaproveitamento parcial da central atual de notificacoes, mas sem misturar dominio

## Fase 5 - Web do estabelecimento

Entregas:

- timeline no detalhe da entrada
- leitura de fluxo por participacao
- suporte para entradas concluidas

Arquivos provaveis:

- paginas de queue detail / queue flow
- componentes de cards e side panels

## Fase 6 - Mobile

Entregas:

- substituir historico local por feed server-side
- detalhe do fluxo por `entry_public_id`
- manter notificacoes do sistema separadas
- continuar usando push/local como camada de entrega, nao como historico

Arquivos provaveis:

- `mobile/app/src/main/java/.../features/notifications/`
- `mobile/app/src/main/java/.../core/network/`
- `mobile/app/src/main/java/.../core/model/`

## Fase 7 - Projecoes de notificacao

Entregas:

- opcionalmente derivar inbox generica
- opcionalmente derivar push FCM
- deduplicacao entre inbox e push

Isso deve vir depois da timeline server-side pronta.

## Criterios de aceitacao

### Backend

- nenhuma rota publica nova expoe `queue_entries.id`
- cada nova entrada em fila retorna `public_id`
- cada mudanca relevante gera um evento persistido
- reentrar na mesma fila em outro dia cria outro fluxo

### Cliente web/mobile

- o usuario consegue listar filas das quais participou
- o usuario consegue abrir o detalhe do fluxo
- fluxos diferentes nao se misturam

### Staff web

- o estabelecimento consegue ver o fluxo da entrada
- o historico continua disponivel apos conclusao

## Riscos e cuidados

### 1. Eventos duplicados

Precisa haver cuidado para nao gravar `called` duas vezes em retries.

Mitigacao:

- centralizar a escrita
- usar regras de idempotencia por transicao

### 2. Exposicao de ID interno

Algum endpoint antigo pode continuar retornando `entry.id`.

Mitigacao:

- revisar serializers/respostas de fila e padronizar `public_id`

### 3. Mistura entre inbox e historico

Se a equipe reaproveitar a central de notificacoes sem separacao de conceito, o produto volta a ficar confuso.

Mitigacao:

- manter UI e API de historico como produto proprio
- usar `notifications` apenas como entrega derivada

## Branch recomendada

Sim, vale abrir outra branch para isso.

Sugestao:

- `feature/queue-entry-history`

Motivo:

- mexe em schema
- mexe em backend
- mexe em web do cliente
- mexe em web do estabelecimento
- mexe em mobile

Nao e uma continuacao pequena da branch mobile. E um epic novo.

## Ordem recomendada para execucao por IA

1. consolidar schema de `public_id` e `queue_entry_events` na `0001`
2. criar service/model de eventos
3. instrumentar `join`, `call`, `serving`, `complete`, `leave`
4. expor endpoints cliente e staff por `public_id`
5. integrar web cliente
6. integrar web estabelecimento
7. integrar mobile
8. so depois ligar inbox/push derivados

## Decisao executiva

Sim, a abordagem correta e separar:

- historico operacional da fila
- notificacao entregue ao usuario

E sim, o `public_id` unico por entrada na fila e a melhor base para isso.
