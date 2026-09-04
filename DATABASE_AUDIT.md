# DATABASE AUDIT & OPTIMIZATION PLAN

**Project:** GROWBRIDGE CONNECT  
**Database Engines Supported:** MySQL 8.0+, MariaDB 10.4+, SQLite 3 (Testing/Development)  
**Migration Count:** 82 Migrations  
**Total Schema Tables:** 52 Core Operational Tables  

---

## 1. Schema Inventory & Entity Relationships

```
+----------------------------------------------------------------------------------------------------+
|                                    CORE MULTI-TENANCY SCHEMA                                       |
+----------------------------------------------------------------------------------------------------+
| clients (id, name, email, country, timezone, client_role, ...)                                     |
|    |                                                                                               |
|    +---> workspaces (id, client_id, name, slug, default_currency, ...)                            |
|             |                                                                                      |
|             +---> users (id, workspace_id, client_id, name, email, password, ...)                  |
|             +---> contacts (id, workspace_id, first_name, last_name, phone_e164, email, ...)      |
|             |        |                                                                             |
|             |        +---> contact_tags (id, contact_id, tag)                                      |
|             |        +---> segment_contact (contact_id, segment_id)                                |
|             |                                                                                      |
|             +---> channel_accounts (id, workspace_id, channel, phone_number_id, page_id, ...)      |
|             |        |                                                                             |
|             |        +---> conversations (id, workspace_id, contact_id, channel_account_id, ...)   |
|             |                 |                                                                    |
|             |                 +---> messages (id, conversation_id, direction, channel, body, ...)  |
|             |                 +---> internal_notes (id, conversation_id, user_id, content)         |
|             |                 +---> conversation_activities (id, conversation_id, type, ...)       |
|             |                                                                                      |
|             +---> ai_chatbots (id, workspace_id, name, system_prompt, ai_kb_id, ...)              |
|             +---> ai_knowledge_bases (id, workspace_id, name, description)                        |
|             |        |                                                                             |
|             |        +---> ai_kb_documents (id, kb_id, filename, file_path, status, ...)          |
|             |        +---> ai_kb_chunks (id, kb_id, document_id, content, embedding)              |
|             |                                                                                      |
|             +---> voice_agents (id, workspace_id, name, language, provider, prompt, ...) [NEW]   |
|             |        |                                                                             |
|             |        +---> voice_calls (id, workspace_id, voice_agent_id, contact_id, ...) [NEW]  |
|             |                                                                                      |
|             +---> automations (id, workspace_id, name, trigger_type, nodes, edges, status)        |
|             |        |                                                                             |
|             |        +---> automation_runs (id, automation_id, contact_id, status, ...)           |
|             |                 |                                                                    |
|             |                 +---> automation_run_logs (id, run_id, node_id, result, output)      |
|             |                                                                                      |
|             +---> campaigns (id, workspace_id, name, channel, status, scheduled_at, ...)          |
|             |        |                                                                             |
|             |        +---> campaign_recipients (id, campaign_id, contact_id, status, sent_at, ...) |
|             |                                                                                      |
|             +---> crm_pipelines (id, workspace_id, name, is_default)                               |
|             |        |                                                                             |
|             |        +---> crm_pipeline_stages (id, workspace_id, pipeline_id, name, probability)  |
|             |                                                                                      |
|             +---> crm_deals (id, workspace_id, contact_id, stage_id, name, value, probability)     |
|             +---> crm_tasks (id, workspace_id, contact_id, title, due_at, priority, status)        |
|             +---> crm_notes (id, workspace_id, contact_id, user_id, content, is_private, mentions)  |
|             +---> crm_teams (id, workspace_id, name, description)                                  |
|                      |                                                                             |
|                      +---> crm_team_user (team_id, user_id, role)                                  |
+----------------------------------------------------------------------------------------------------+
```

---

## 2. Table-by-Table Audit

| Table Name | Primary Purpose | Indexing Status | Tenant Isolation Key | Optimization Needed |
| :--- | :--- | :--- | :--- | :--- |
| `users` | User credentials & profiles | Indexed on `email` | `workspace_id`, `client_id` | Add composite index `(workspace_id, status)` |
| `clients` | Organizations / Accounts | Indexed on `email` | `id` | Well structured |
| `workspaces` | Tenant workspaces | Indexed on `client_id` | `id` | Add unique composite `(client_id, slug)` |
| `contacts` | CRM contacts database | Indexed on `workspace_id` | `workspace_id` | Ensure composite index `(workspace_id, phone_e164)` and `(workspace_id, email)` |
| `conversations` | Multi-channel threads | Indexed on `workspace_id`, `contact_id` | `workspace_id` | Ensure composite index `(workspace_id, status, last_message_at)` |
| `messages` | Chat messages | Indexed on `conversation_id` | Indirect (`conversation_id`) | Ensure composite index `(conversation_id, sent_at)` and unique `provider_message_id` |
| `ai_kb_chunks` | Knowledge base text vectors | Indexed on `kb_id`, `document_id` | Indirect (`kb_id`) | Add MySQL FullText index on `content` for fast pre-filtering |
| `automation_runs` | Workflow execution history | Indexed on `automation_id` | Indirect (`automation_id`) | Ensure index on `(automation_id, status, created_at)` |
| `campaign_recipients` | Broadcast dispatch recipients | Indexed on `campaign_id` | Indirect (`campaign_id`) | Ensure composite index `(campaign_id, status)` for batch job slicing |
| `voice_agents` [NEW] | Voice AI Agent configurations | Indexed on `workspace_id` | `workspace_id` | Add index on `(workspace_id, status)` |
| `voice_calls` [NEW] | Inbound/Outbound call records | Indexed on `workspace_id`, `contact_id` | `workspace_id` | Add composite index `(workspace_id, status, created_at)` |

---

## 3. Query & Index Optimization Directives

### A. Message & Inbox List Optimization
- **Problem:** Inbox list query sorts conversations by `last_message_at DESC` and filters by `workspace_id` and `status`.
- **Optimization:** Create covering composite index:
  ```sql
  CREATE INDEX idx_conversations_workspace_status_last_msg ON conversations (workspace_id, status, last_message_at DESC);
  ```

### B. Contact Lookup Optimization
- **Problem:** Inbound webhooks from WhatsApp, Messenger, and Instagram search contacts by phone number (`phone_e164`) or external ID within the workspace.
- **Optimization:**
  ```sql
  CREATE INDEX idx_contacts_workspace_phone ON contacts (workspace_id, phone_e164);
  ```

### C. AI Knowledge Base Chunk Search
- **Problem:** When searching for relevant chunks in standalone MySQL without Qdrant, doing in-memory cosine comparison across thousands of chunks can cause PHP memory exhaustion.
- **Optimization:** Add FULLTEXT index on `ai_kb_chunks.content` so queries can pre-filter matching candidate chunks before computing vector distance.

---

## 4. Multi-Tenancy Data Isolation Guarantee
- All queries on tenant-scoped tables must explicitly include `where('workspace_id', $workspaceId)`.
- Global query scopes or explicit Eloquent relationship chaining (`$workspace->contacts()->find($id)`) are strictly applied across all controllers and API resources.
- Foreign keys use `ON DELETE CASCADE` appropriately to avoid orphan records while preserving historical financial transaction records (`payment_transactions`, `invoices`).
