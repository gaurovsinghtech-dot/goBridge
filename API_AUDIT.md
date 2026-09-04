# API AUDIT & SPECIFICATION

**Project:** GROWBRIDGE CONNECT  
**Base URL Prefix:** `/api/v1/`  
**Authentication:** Laravel Sanctum Bearer Token (`Authorization: Bearer <token>`)  
**Standard Response Format:**
```json
{
  "success": true,
  "data": {},
  "message": "Operation completed successfully"
}
```
**Standard Error Response Format:**
```json
{
  "success": false,
  "message": "Detailed error explanation",
  "errors": {}
}
```

---

## 1. Authentication & Token Management

| Endpoint | Method | Ability / Scope | Description |
| :--- | :--- | :--- | :--- |
| `/api/v1/auth/login` | `POST` | Public (`throttle:10,1`) | Issue Sanctum token for mobile or third-party client |
| `/api/v1/auth/logout` | `POST` | `auth:sanctum` | Revoke current token |
| `/api/v1/auth/me` | `GET` | `auth:sanctum` | Return current user details & workspace info |
| `/api/v1/tokens` | `GET` | `auth:sanctum` | List API tokens created by workspace |
| `/api/v1/tokens` | `POST` | `auth:sanctum` | Generate a new scoped API token |
| `/api/v1/tokens/{id}` | `DELETE` | `auth:sanctum` | Revoke specific API token |
| `/api/v1/token-scopes` | `GET` | `auth:sanctum` | List all available permission scopes |

---

## 2. Contacts & CRM Endpoints

| Endpoint | Method | Ability Required | Description |
| :--- | :--- | :--- | :--- |
| `/api/v1/contacts` | `GET` | `contacts:read` | Paginated list of contacts with search and tag filters |
| `/api/v1/contacts` | `POST` | `contacts:write` | Create new contact (validates phone E.164 and email) |
| `/api/v1/contacts/{id}` | `GET` | `contacts:read` | Retrieve single contact with tags and history |
| `/api/v1/contacts/{id}` | `PATCH` | `contacts:write` | Update contact attributes, tags, and custom fields |
| `/api/v1/contacts/{id}` | `DELETE` | `contacts:write` | Delete contact |
| `/api/v1/segments` | `GET` | `contacts:read` | List dynamic and static contact segments |
| `/api/v1/segments` | `POST` | `contacts:write` | Create a new contact segment |
| `/api/v1/segments/{id}/contacts` | `GET` | `contacts:read` | Retrieve resolved contacts in segment |
| `/api/v1/crm/leads` | `GET` | `contacts:read,leads:read` | Paginated list of CRM leads with stage and score |
| `/api/v1/crm/leads` | `POST` | `contacts:write,leads:write` | Create a new lead in CRM sales pipeline |
| `/api/v1/crm/leads/{id}` | `GET` | `contacts:read,leads:read` | Retrieve 360-degree lead detail with deals & tasks |
| `/api/v1/crm/leads/{id}` | `PUT` | `contacts:write,leads:write` | Update lead properties (deal value, priority, company) |
| `/api/v1/crm/leads/{id}/stage` | `POST` | `contacts:write,leads:write` | Move lead to next pipeline stage with timeline log |
| `/api/v1/crm/pipelines` | `GET` | `contacts:read,leads:read` | List all sales pipelines and customizable stages |
| `/api/v1/crm/deals` | `GET` | `contacts:read,leads:read` | List active deals with probability and expected value |
| `/api/v1/crm/tasks` | `GET` | `contacts:read,leads:read` | List scheduled follow-ups and callbacks |

---

## 3. Omnichannel Messaging & Unified Inbox Endpoints

| Endpoint | Method | Ability Required | Description |
| :--- | :--- | :--- | :--- |
| `/api/v1/conversations` | `GET` | `conversations:read` | List conversations (WhatsApp, Messenger, Instagram, Email) |
| `/api/v1/conversations/{id}/messages` | `GET` | `conversations:read` | Paginated message history for a conversation |
| `/api/v1/messages/send` | `POST` | `messages:write` | Send outbound text, media, or template message |
| `/api/v1/mobile/conversations/{uuid}/reply` | `POST` | `messages:write` | Agent reply to conversation |
| `/api/v1/mobile/conversations/{uuid}/assign` | `PATCH` | `conversations:write` | Assign conversation to agent |
| `/api/v1/mobile/conversations/{uuid}/status` | `PATCH` | `conversations:write` | Change conversation status (open/closed/pending) |
| `/api/v1/mobile/conversations/{uuid}/handover` | `POST` | `conversations:write` | Hand over between AI bot and human agent |

---

## 4. AI Agents & Knowledge Base Endpoints

| Endpoint | Method | Ability Required | Description |
| :--- | :--- | :--- | :--- |
| `/api/v1/ai/chatbots` | `GET` | `ai:read` | List configured AI Agents |
| `/api/v1/ai/chatbots/{id}/chat` | `POST` | `ai:write` | Send message to AI Agent and receive AI response |
| `/api/v1/ai/knowledge-bases` | `GET` | `ai:read` | List Knowledge Bases |
| `/api/v1/ai/knowledge-bases` | `POST` | `ai:write` | Create a new Knowledge Base |
| `/api/v1/ai/knowledge-bases/{id}/documents` | `POST` | `ai:write` | Upload and index document into Knowledge Base |
| `/api/v1/ai/knowledge-bases/{id}/documents/{docId}` | `DELETE` | `ai:write` | Delete document and remove vector chunks |

---

## 5. AI Voice Agents Endpoints (NEW)

| Endpoint | Method | Ability Required | Description |
| :--- | :--- | :--- | :--- |
| `/api/v1/voice/agents` | `GET` | `voice:read` | List configured Voice Agents |
| `/api/v1/voice/agents` | `POST` | `voice:write` | Create / configure AI Voice Agent |
| `/api/v1/voice/agents/{id}` | `GET` | `voice:read` | Get Voice Agent configuration & stats |
| `/api/v1/voice/agents/{id}` | `PATCH` | `voice:write` | Update Voice Agent instructions & tools |
| `/api/v1/voice/calls/dispatch` | `POST` | `voice:write` | Trigger outbound AI voice call to contact |
| `/api/v1/voice/calls` | `GET` | `voice:read` | List call logs, durations, recordings & summaries |
| `/api/v1/voice/calls/{id}` | `GET` | `voice:read` | Get single call transcript and AI summary |

---

## 6. Automations & Campaigns Endpoints

| Endpoint | Method | Ability Required | Description |
| :--- | :--- | :--- | :--- |
| `/api/v1/automations` | `GET` | `automations:write` | List workflows |
| `/api/v1/automations/{id}/trigger` | `POST` | `automations:write` | Programmatically fire an automation workflow |
| `/api/v1/campaigns` | `GET` | `campaigns:read` | List marketing campaigns |
| `/api/v1/campaigns` | `POST` | `campaigns:write` | Create a new campaign |
| `/api/v1/campaigns/{id}/launch` | `POST` | `campaigns:write` | Launch campaign |
| `/api/v1/campaigns/{id}/pause` | `POST` | `campaigns:write` | Pause active campaign |
| `/api/v1/campaigns/{id}/recipients` | `GET` | `campaigns:read` | View delivery funnel & recipient status |

---

## 7. Webhook Ingestion & Outbound Webhooks

| Endpoint | Method | Security | Description |
| :--- | :--- | :--- | :--- |
| `/webhooks/whatsapp/global` | `GET`/`POST` | Meta Verify Token / Hub Signature | Global WhatsApp Cloud API intake |
| `/webhooks/whatsapp/{token}` | `GET`/`POST` | Per-WABA Token Verification | Dedicated WABA WhatsApp intake |
| `/webhooks/meta/{token}` | `GET`/`POST` | Meta Hub Verify Token | Messenger & Instagram webhook intake |
| `/webhooks/voice/{provider}` | `POST` | Signature / Provider Token | Exotel / Twilio / Plivo call webhook |
| `/api/v1/webhooks` | `GET` | `webhooks:write` | List tenant outbound webhook endpoints |
| `/api/v1/webhooks` | `POST` | `webhooks:write` | Register new tenant outbound webhook URL |
| `/api/v1/webhooks/{id}` | `DELETE` | `webhooks:write` | Remove tenant outbound webhook |
