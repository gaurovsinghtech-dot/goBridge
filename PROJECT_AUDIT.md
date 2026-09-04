# PROJECT AUDIT: WHATSMAIN TO GROWBRIDGE CONNECT

**Project Name:** GROWBRIDGE CONNECT  
**Tagline:** "Connect. Engage. Automate. Grow."  
**Platform Scope:** Lightweight Omnichannel Customer Communication, Automation, AI & AI Voice Agent SaaS  
**Audit Date:** August 2026  
**Audited Codebase:** WhatsMine Multi-Channel Messaging & Automation Platform  

---

## 1. Current Architecture
- **Paradigm:** Monolithic Modular SaaS with Laravel 12 backend and React 19 (Inertia.js v2) single-page frontend.
- **Modularity:** Structured around domain modules located in `app/Modules/`:
  - `AI`: Chatbots, Knowledge Bases, Document Indexing, Vector Embeddings, LLM Gateway (OpenAI, Gemini, Anthropic).
  - `Automation`: Visual Automation Builder, Workflow Generator, Event-driven Rule & Action Engine.
  - `Broadcasting`: Campaigns (WhatsApp, SMS, Email), Audience Personalization, SMS Multi-Driver Manager, SMTP & Email Tracking.
  - `Ecommerce`: Shopify, WooCommerce, BigCommerce stores, Order/Cart Webhooks, Product Sync.
  - `Inbox`: Unified Inbox (WhatsApp, Messenger, Instagram), Canned Replies, Labels, SLA Tracking, Notes, Activities.
  - `Integrations`: Credential resolution, System & Workspace level OAuth/API configurations (Meta, Google, AI, SMS, Telephony).
  - `Leads`: Google Places Scraper, Lead Pipelines (Kanban stages), Activity tracking, Rule-based Scoring.
  - `Shared`: Core domain entities (Contacts, ChannelAccounts, Conversations, Messages, Segments).
  - `Social`: Social media multi-account publishing (Facebook, Instagram, LinkedIn, TikTok, Twitter/X, YouTube) and Calendar.
  - `Whatsapp`: Cloud API client, Embedded Signup, Template management, Inbound/Outbound webhooks, Interactive widgets.
- **Multi-Tenancy:** Single-database multi-tenancy partitioned via `workspace_id` and `client_id` (organization model).

---

## 2. Framework & Version
- **Backend Framework:** Laravel Framework `v12.0` (PHP `^8.2`, active runtime PHP `8.3.33`).
- **SPA Bridge:** Inertia.js Laravel `v2.0` / `@inertiajs/react` `v2.3.16`.
- **API Engine:** Laravel Sanctum `v4.3` with ability-based token scopes (`contacts:read`, `messages:write`, etc.).
- **Realtime Broadcasts:** Laravel Reverb `v1.10` / Pusher JS `v8.5` / Laravel Echo `v2.3.4` (configurable with `log` fallback).
- **API Documentation:** Dedoc Scramble `v0.13.22` (OpenAPI specification auto-generation).

---

## 3. PHP Requirements
- **PHP Version:** `^8.2` (Verified running on PHP `8.3.33`).
- **Core Extensions Required:**
  - `pdo_mysql` / `pdo_sqlite`
  - `mbstring`, `openssl`, `bcmath`, `curl`, `json`, `fileinfo`, `tokenizer`, `xml`, `gd` / `imagick`
  - Optional for high throughput: `redis` (`phpredis`), `pcntl` (CLI queue worker process isolation).

---

## 4. Frontend Technology
- **Library / Core:** React `19.2.4` + React DOM `19.2.4`.
- **Build Tool:** Vite `6.0.11` + `@vitejs/plugin-react` `5.1.4` + `laravel-vite-plugin` `1.2.0`.
- **CSS / Styling:** TailwindCSS `v3.2.1` with custom CSS custom properties (variables) for runtime dynamic theming, forms plugin (`@tailwindcss/forms`), and dark mode (`class` strategy).
- **UI Components:** Headless UI (`@headlessui/react` `2.0.0`), Lucide React icons (`lucide-react` `0.575.0`), Sonner toast notifications (`sonner` `2.0.7`), Recharts `3.8.1` for lightweight dashboard analytics.
- **Workflow & Drag/Drop:** XYFlow (`@xyflow/react` `12.10.2`) for the visual automation graph builder, `@dnd-kit/core` & `@dnd-kit/sortable` for Kanban and list reordering.
- **Internationalization:** `i18next` `25.8.13`, `react-i18next` `16.5.4` with dynamic locale loading from `/i18n/{locale}`.

---

## 5. Database Architecture
- **Total Tables:** 82 migration files yielding 52 core operational tables.
- **Key Tables:**
  - `workspaces`, `clients`, `users`, `workspace_user`, `admin_users`
  - `contacts`, `contact_tags`, `segments`, `contact_segment`
  - `channel_accounts`, `conversations`, `messages`, `internal_notes`, `conversation_activities`, `inbox_labels`
  - `whatsapp_business_accounts`, `whatsapp_phone_numbers`, `whatsapp_templates`, `whatsapp_auto_replies`, `whatsapp_widgets`
  - `ai_chatbots`, `ai_knowledge_bases`, `ai_kb_documents`, `ai_kb_chunks`, `ai_provider_configs`, `ai_runs`
  - `automations`, `automation_runs`, `automation_run_logs`
  - `campaigns`, `campaign_recipients`, `workspace_smtp_configs`, `sms_provider_configs`, `usage_meters`
  - `leads`, `lead_pipeline_stages`, `lead_activities`, `lead_scoring_configs`, `lead_scrape_jobs`
  - `integration_configs`, `integration_audit_logs`, `webhook_endpoints`, `webhook_deliveries`
  - `plans`, `subscriptions`, `payment_transactions`, `coupons`, `tax_rates`, `payment_gateway_configs`

---

## 6. Authentication
- **Customer / Client Auth:** Laravel Session auth for Inertia web portal + Laravel Sanctum Bearer tokens for REST API (`/api/v1/`) and Mobile apps.
- **Admin Auth:** Dedicated `admin` guard (`AdminUser` model) with session isolation. Main login route `/login` checks credentials against Admin first, then Client User.
- **Features:** 2FA TOTP (Google Authenticator via `pragmarx/google2fa`), Magic Link login (`magic_links` table), Social OAuth (Google, Facebook via Laravel Socialite), Session invalidation & device management.

---

## 7. Authorization & RBAC
- **Admin RBAC:** Granular role & permission model (`roles`, `permissions`, `role_permission`, `admin_role`) enforced via `RequirePermission` middleware.
- **Client Team Roles:** Role-based access within workspaces (`administrator`, `agent`, `viewer`) enforced via `EnsureClientScope` and `EnsureUserRole` middleware.
- **API Token Scoping:** Ability checks via `CheckApiAbility` (`api.ability:contacts:read`, `api.ability:messages:write`, `api.ability:campaigns:write`, `api.ability:ai:write`, etc.).

---

## 8. API Architecture
- **Endpoint Structure:** Versioned under `/api/v1/`.
- **Response Standardization:** JSON resources with standard structure `{ success: true, data: ..., message: ... }` and RFC-compliant error envelopes.
- **Documentation:** Auto-generated via Scramble `/docs/api` with interactive playground capability.
- **Rate Limiting:** Granular rate limits per tier (`throttle:api`, `throttle:webhooks`, `throttle:60,1`).

---

## 9. Existing Integrations
- **System Integrations:** Configured globally by admin (`integration_configs`):
  - Meta App (WhatsApp Embedded Signup, Messenger, Instagram Graph API)
  - Google (OAuth, Google Places API for lead scraping)
  - AI Providers: OpenAI, Anthropic Claude, Google Gemini
  - SMS Gateways: Twilio, Plivo, Telnyx, Infobip, Msg91, Fast2SMS, MessageBird, BulkSMSBD, etc.
  - Payment Gateways: Stripe, PayPal, Razorpay, Cashfree, Paddle, Paystack, Mollie, MercadoPago, Iyzico, Xendit, Tap, Square, Paymob.
  - Push Notifications: OneSignal, Web Push VAPID.
- **Workspace-Level Overrides:** Custom SMTP servers, BYO AI keys, custom SMS credentials.

---

## 10. WhatsApp Implementation
- **API:** Official WhatsApp Cloud API (Graph API `v20.0` / `v21.0`).
- **Connection Modes:**
  1. Embedded Signup (Meta Co-Branded OAuth with automatic WABA, Phone Number, and System User Token provisioning).
  2. Manual Configuration (WABA ID, Phone Number ID, Permanent Access Token).
- **Capabilities:**
  - Inbound & Outbound messaging: Text, Image, Audio, Video, Document, Location, Contacts, Interactive Buttons, Interactive Lists, Reactions, Orders, Polls.
  - Template Management: Sync from Meta, interactive visual Template Builder (Header, Body, Footer, Quick Reply & CTA Buttons), status change webhooks (`APPROVED`, `REJECTED`, `PAUSED`).
  - 24-Hour Messaging Window tracking (`isWhatsappWindowOpen()`).
  - Quality Rating, Messaging Tier tracking, Display Name change requests.
  - Embeddable WhatsApp Floating Chat Widget with customizable branding.

---

## 11. Facebook Messenger Implementation
- **API:** Meta Graph API Webhooks & Messages API.
- **Connection:** Facebook Page OAuth / Manual Page ID + Page Access Token.
- **Features:** Inbound webhook intake, outbound text/media messages, auto-replies, AI Chatbot reply dispatch, handover protocol.

---

## 12. Instagram Implementation
- **API:** Instagram Messaging API (via Meta Graph API for Professional / Business accounts).
- **Connection:** Instagram Business Account linked to Facebook Page.
- **Features:** Direct Message intake, story replies, media sharing, auto-replies, AI agent automation, human handover.

---

## 13. Email Implementation
- **Sending Protocols:** Workspace custom SMTP servers (`WorkspaceSmtpConfig`), System SMTP (`SmtpConfiguration`), Amazon SES (via Laravel Mail driver).
- **Email Builder:** Drag & drop block visual email template editor (`VisualCanvas.jsx`, `EmailEditor`).
- **Broadcasting:** Email campaigns with batch chunking (`DispatchCampaignChunkJob`), audience variable interpolation (`{{first_name}}`, `{{company}}`), open tracking pixel, signed click tracking, RFC 2369 / RFC 8058 one-click unsubscribe.
- **AI Email Tools:** AI email content generation and subject line optimizer (`EmailAiController`).

---

## 14. AI Implementation
- **LLM Gateway:** Universal provider abstraction (`OpenAiProvider`, `GeminiProvider`, `AnthropicProvider`).
- **RAG & Knowledge Bases:** Document upload (.txt, .pdf, .md, URLs), chunking (`IndexDocumentJob`), vector embeddings.
- **Vector Store:** Dual-mode storage (`EmbeddingStore`) supporting optional Qdrant or lightweight standalone MySQL cosine similarity calculations.
- **Chatbot Runner:** Multi-turn conversational context assembly (system instructions, knowledge retrieval chunks, ecommerce customer order history context, past message turns).

---

## 15. Automation Implementation
- **Engine:** Visual Node Graph Engine (`AutomationEngine`) with asynchronous queue execution (`ExecuteAutomationRunJob`).
- **Triggers:**
  - `message.received` (with keyword filtering)
  - `contact.created`
  - `lead.stage_changed`, `lead.qualified`, `lead.won`, `lead.lost`
  - `order.placed`, `order.fulfilled`, `order.cancelled`, `cart.abandoned`
  - `webhook` (inbound external HTTP POST trigger)
  - Scheduled time triggers
- **Nodes & Actions:**
  - `send_whatsapp`, `send_sms`, `send_email`, `send_template`, `send_media`, `send_sequence`, `quick_replies`, `list_message`
  - `ask_question` (parks run until user replies, stores response into context variable)
  - `wait` (delay in minutes/hours/days)
  - `condition` (multi-field contact and context branching)
  - `add_tag`, `remove_tag`, `update_contact`, `assign_agent`, `add_to_campaign`
  - `ai_reply`, `webhook`, `run_subflow`, `human_handoff`
- **Simulation / Testing:** Built-in safe sandbox test-run generator (`testRun`) allowing zero-cost rule verification without side effects.

---

## 16. Unified Inbox
- **Channels:** WhatsApp, Facebook Messenger, Instagram Direct, Email.
- **UI Architecture:** Responsive 3-pane layout (Channel Filter / Conversation List / Active Chat & Customer Sidebar).
- **Realtime:** Reverb / Pusher WebSockets with fallback polling for message receipts and typing indicators.
- **Agent Features:** Unread counts, assignment, status (open/closed/pending), internal team notes, conversation activity audit timeline, canned replies (`/shortcut`), label tagging, AI auto-reply suggestions, human handover takeover.

---

## 17. Campaign System
- **Omnichannel Broadcasting:** WhatsApp Template Broadcasts, SMS Gateway Campaigns, Email Newsletters.
- **Audience Filtering:** Dynamic Segment resolution or manual Contact selection.
- **Chunking & Rate Limiting:** Queued batch dispatches (`DispatchCampaignChunkJob`, `SendCampaignMessageJob`) to avoid provider rate limiting.
- **Analytics:** Delivery funnels (Sent, Delivered, Read, Clicked, Failed) with retry failed recipients capability.

---

## 18. Webhooks
- **Inbound Webhooks (Zero-CSRF Public Endpoints):**
  - WhatsApp Cloud API (`/webhooks/whatsapp/global`, `/webhooks/whatsapp/{token}`)
  - Meta Messenger / Instagram (`/webhooks/meta/{token}`)
  - SMS Status callbacks (`/webhooks/sms/{provider}`)
  - Payment Gateways (Stripe, Razorpay, PayPal, Cashfree, etc.)
  - Automation triggers (`/webhooks/automation/{trigger_token}`)
  - Ecommerce stores (Shopify, WooCommerce, BigCommerce)
- **Outbound Webhooks:** Tenant-configured endpoints (`WebhookEndpoint`, `WebhookDelivery`) with HMAC-SHA256 signature verification and automatic exponential backoff retries.

---

## 19. Queue System
- **Primary Driver:** Laravel Database Queue (`jobs`, `failed_jobs` tables) — 100% cPanel friendly, no mandatory Redis.
- **Redis Driver:** Optional drop-in when available.
- **Job Queues:** `default`, `whatsapp`, `automation`, `campaigns`, `media`, `social`.
- **Admin Queue Monitor:** Built-in failed job inspection, retry, and flush console in the Admin panel.

---

## 20. Cron / Scheduler
- **Mechanism:** Standard Laravel Scheduler (`php artisan schedule:run`).
- **Scheduled Tasks:**
  - Scheduled campaign launches (`LaunchScheduledCampaignsJob`)
  - Social media scheduled post dispatch (`DispatchScheduledPostsJob`)
  - Automation delayed wakeups
  - Token refresh (Instagram, Facebook long-lived tokens)
  - Daily/weekly usage reset and digest emails
  - Lead rescoring & cleanup routines

---

## 21. Realtime System
- **Architecture:** Laravel Broadcasting with Echo.
- **Drivers:** Laravel Reverb (self-hosted PHP WebSocket daemon), Pusher Channels, or `log` / HTTP polling fallback.
- **Presence & Private Channels:** Scoped to workspace and conversation (`private-workspace.{id}`, `private-conversation.{uuid}`).

---

## 22. Storage System
- **Manager:** `StorageManager` service supporting `local`, `public`, `s3`, and S3-compatible cloud storage (Cloudflare R2, MinIO, Wasabi, DigitalOcean Spaces).
- **Security:** Private signed URL generation for exported CSVs and uploaded internal documents.

---

## 23. Payment System
- **Architecture:** Gateway-agnostic driver registry (`BillingGatewayRegistry`).
- **Supported Gateways (14+):** Stripe, Razorpay, Cashfree, PayPal, Paddle, Paystack, MercadoPago, Mollie, Iyzico, Xendit, Tap, Paymob, MyFatoorah, Square.
- **Features:** Automated webhook invoice creation, coupon discounts, customizable tax rates, PDF invoice downloads via DomPDF.

---

## 24. Subscription & Billing System
- **Tier Enforcements:** Per-plan feature limits (`whatsapp_messages_per_month`, `campaigns_per_month`, `contacts_limit`, `ai_tokens_per_month`, `team_members_limit`, etc.) enforced via `EnforceLimit` middleware.
- **Lifecycle:** Trialing, Active, Past Due, Cancelled, Expired states with automated notifications.

---

## 25. Admin Panel
- **Access:** `/admin` guarded by `auth:admin` and RBAC permissions.
- **Management Screens:**
  - Clients & Workspace management (with Tenant Impersonation)
  - Plans & Pricing Tiers
  - Subscriptions & Payment Transactions
  - Global Integration Credential Hub
  - System Settings, White-label Branding & Font Customizer
  - Queue Monitor & Cron Setup Assistant
  - Language & Locale Manager with AI Auto-Translation
  - CMS Pages & Landing Page Editor
  - Admin Users, Roles & Permissions

---

## 26. User / Client Panel
- **Access:** `/app` guarded by `auth`, `client-app` middleware.
- **Key Modules:** Dashboard, Unified Inbox, Contacts & Segments, WhatsApp Tools, Campaigns, Automations, AI Chatbots & Knowledge Bases, Leads & Pipelines, Social Media, Settings, API Tokens, Webhooks.

---

## 27. Performance Problems Identified
1. **AI Embedding Similarity in PHP:** Standalone MySQL fallback loads all vectors for a knowledge base into PHP memory to compute cosine distances. *Fix:* Add MySQL fulltext pre-filtering and chunk limits.
2. **Conversation List Eager Loading:** Inboxes with thousands of messages risk N+1 queries if relations (`contact`, `channelAccount`, `labels`, `lastMessage`) are not strictly eager-loaded. *Fix:* Enforce scoped eager loading and indexed `last_message_at` pagination.
3. **Contact Import Memory Consumption:** Bulk importing 50,000+ CSV contacts in a single request can exceed memory limits. *Fix:* Stream with `league/csv` into database chunks in background jobs.

---

## 28. Security Problems & Mitigations
1. **Tenant Isolation:** Ensure every query filters by `workspace_id` (enforce global Eloquent scopes or repository bounds).
2. **Credential Exposure:** Confirm all third-party API keys, tokens, and SMTP passwords are cast to `'encrypted'` in Eloquent and scrubbed from frontend props.
3. **Webhook Verification:** Verify all inbound webhooks (Meta, WhatsApp, Stripe, Razorpay) validate cryptographic signatures or tokens before payload parsing.

---

## 29. Duplicate Code Areas
1. **Meta OAuth & Token Refresh:** Similar token refresh logic exists in both `Social` and `Inbox` modules. *Fix:* Centralize in `Integrations/Services/Credentials/MetaCredentials`.
2. **Auto-Reply Keyword Matching:** Logic in `AutoReplyListener` mirrors condition matching in `AutomationEngine`. *Fix:* Shared trait for condition evaluation.

---

## 30. Unused Dependencies
- `smalot/pdfparser` is used in document indexing; verify no extraneous dev packages are shipped to production.
- Keep dependencies minimal to ensure lightning-fast Composer install on cPanel.

---

## 31. Unused Files & Redundant Assets
- Old placeholder branding assets (`public/whatsmine-icon.svg`, `.branding` default green palette).
- Legacy demo seed templates that reference obsolete brands.

---

## 32. Database Optimizations Required
- Ensure composite indexes on:
  - `messages(conversation_id, sent_at)`
  - `conversations(workspace_id, status, last_message_at)`
  - `contacts(workspace_id, phone_e164)`
  - `campaign_recipients(campaign_id, status)`

---

## 33. UI / UX Improvements for Growbridge Connect
- Rebrand the UI to Growbridge Connect design tokens:
  - Primary Navy: `#011B40`
  - Accent Golden Yellow: `#FEB51B`
  - Secondary Green: `#064E3B`
  - Clean Slate/Light Backgrounds: `#FFFFFF`, `#F1F5F9`
  - Text: `#0F172A`, `#64748B`
- Simplify navigation to 10 clean top-level items:
  1. Dashboard
  2. Inbox
  3. Contacts
  4. Campaigns
  5. Automation
  6. AI Agents
  7. Voice Agents (NEW)
  8. Analytics
  9. Integrations
  10. Settings

---

## 34. Hosting Requirements
- **Target Environments:** cPanel Shared Hosting, VPS, Dedicated Apache/Nginx.
- **Stack:** PHP `8.2+`, MySQL `8.0+` or MariaDB `10.4+`, Apache `mod_rewrite`, SSL Certificate.
- **Zero Mandatory Daemons:** Runs completely without Redis, without Docker, without Node/Python services, using standard cPanel cron (`* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1`) and Database queue.

---

## 35. Recommended Changes & Strategic Roadmap
1. **Brand Transformation:** Complete rebranding to Growbridge Connect ("Connect. Engage. Automate. Grow.").
2. **AI Voice Agents Architecture:** Add native Voice Agent module with provider abstraction (Exotel, Twilio, Plivo) for inbound/outbound call workflows, call transcription summaries, and CRM sync.
3. **Streamlined Navigation:** Consolidate cluttered menus into the 10 core SaaS navigation items.
4. **Onboarding Wizard:** Build a step-by-step 8-stage zero-friction onboarding wizard for non-technical business owners.
5. **API Cleanliness & Developer Portal:** Ensure all client and developer interactions through clean `/api/v1/` endpoints.
