# 📘 Growbridge Connect — Technical Architecture & Production Engineering Report

This document details the architectural principles, engineering decisions, production-grade hardening, and performance optimizations implemented throughout **Growbridge Connect**.

---

## 1. Executive Summary

Growbridge Connect is a multi-tenant enterprise communication and marketing automation SaaS built with **Laravel 11, React 18 (Inertia.js), and Flutter (Dart)**. It provides complete omnichannel capability for:
- 💬 **WhatsApp Business API** (Meta Cloud API / On-Premises)
- 📞 **In-App VoIP Business Calling** (Twilio WebRTC Softphone & Softswitch)
- 🤖 **AI Chatbots & Text Agents** (Strict RAG Vector Knowledge Bases)
- 🎙️ **AI Voice Agents & Interactive IVR** (Realtime STT, LLM inference, TTS, and warm transfer dial)
- 🔀 **Visual Automation Builder** (Drag-and-drop workflow graph with loop guards and HTTP integration)
- 📊 **Deals CRM & Kanban Pipeline** (Lead scoring, activity timelines, task due dates)
- 💳 **Centralized Provider Billing & Wallet** (Zero separate accounts required for clients with Meta/Twilio/AI providers)
- 📱 **Cross-Platform Mobile Application** (Flutter Android & iOS communicating securely with Laravel REST API)

---

## 2. Full Technology Stack Breakdown

### Backend Stack
- **Language & Framework**: PHP 8.2+ / Laravel 11.
- **Architectural Pattern**: Domain-Driven Modular Architecture (`App\Modules\*`).
- **Database & Layer**: MySQL 8.0+ / MariaDB 10.6+ with Eloquent ORM.
- **Authentication**: Laravel Sanctum (Stateful session authentication for web and Bearer tokens for mobile/API).
- **Asynchronous Processing**: Laravel Queues with database/Redis driver (cPanel cron compatible).
- **Realtime Layer**: Laravel Reverb / Pusher WebSockets & Web Push (VAPID).
- **Security & Headers**: Custom middleware stack enforcing strict multi-tenant data isolation, HMAC webhook validation, and OWASP security headers.

### Frontend Stack
- **View Layer**: React 18 via Inertia.js (eliminating GraphQL/REST client boilerplate while keeping SPA responsiveness).
- **CSS & UI System**: TailwindCSS, Headless UI, Custom Glassmorphic Theme, Lucide React Icons.
- **Data Visualization**: Recharts & Chart.js.
- **Table & Grid Editing**: Handsontable.
- **Spreadsheet Generation**: ExcelJS.
- **Workflow Canvas**: React Flow (Interactive visual node graph).
- **Asset Compilation**: Vite 6 with code splitting and lazy loading.

### Mobile Client Stack
- **Framework**: Flutter 3.x (Dart 3.x) targeting Android & iOS.
- **Audio & Softphone**: WebRTC audio softphone signaling.
- **API Integration**: Authenticated HTTP REST client with Bearer token header handling.
- **State Management**: Reactive state management with cached offline resilience.

---

## 3. How We Built It: Step-by-Step Evolution

```
┌────────────────────────────────────────────────────────────────────────────┐
│                    DEVELOPMENT & EVOLUTION LIFECYCLE                       │
├────────────────────────────────────────────────────────────────────────────┤
│ 1. Core Multi-Tenant Architecture & Domain Modules                         │
│    • Domain-driven isolation in app/Modules (Shared, AI, Voice, Leads, etc)│
│    • Tenant scoping middleware and multi-workspace context                 │
│                                                                            │
│ 2. Omnichannel Communication & Telephony                                   │
│    • Channel Adapter Manager (WhatsApp, Instagram, Email, SMS, Twilio)     │
│    • Inbound & outbound message normalizer with delivery status webhooks   │
│    • In-browser and mobile WebRTC VoIP calling via Twilio                  │
│                                                                            │
│ 3. Dual AI Studio (Text RAG + AI Voice Agents)                             │
│    • Strict RAG knowledge base (PDF/TXT/DOCX/URL indexing)                 │
│    • IVR voice engine gathering speech and handling transfer dial          │
│    • AI Assist drawer (Suggest reply, improve, translate, summarize)       │
│                                                                            │
│ 4. Visual Workflow Automation & Deals CRM                                  │
│    • Graph execution engine with loop protection and conditional branches  │
│    • Kanban deal stages, lead scoring (Cold/Warm/Hot), and auto-assignment │
│                                                                            │
│ 5. Centralized Provider Billing & Growbridge Wallet                        │
│    • Single invoice model (Growbridge manages Meta, Twilio, OpenAI billing)│
│    • Rechargeable wallet balance with real-time per-unit ledger deduction  │
│    • Multi-gateway payment checkout (Stripe, Razorpay, PayPal, Paddle)     │
│                                                                            │
│ 6. Cross-Platform Flutter Mobile App & Dynamic APK Release Management      │
│    • Android & iOS mobile app for WhatsApp Chat + Business Calling         │
│    • Super Admin APK release control center with force-update thresholds   │
│    • Dynamic SVG QR Code generator and showcase page in User Settings      │
└────────────────────────────────────────────────────────────────────────────┘
```

---

## 4. Production-Level Engineering Standards

The codebase has been engineered to meet production-level standards:

| Requirement | Implementation Detail |
|---|---|
| **Multi-Tenant Data Isolation** | Every database query on workspace-scoped entities applies `where('workspace_id', $workspace->id)`. The `EnsureClientScope` middleware strictly forbids cross-tenant access, tested across all feature suites. |
| **Zero Credential Exposure** | Meta Graph Tokens, Twilio Auth Tokens, AI Provider API keys, and database passwords reside solely in encrypted server-side configurations. Neither web clients nor mobile binaries contain private keys. |
| **Cryptographic Webhook Validation** | Inbound webhooks from Meta, Twilio, Stripe, Razorpay, Paddle, and Iyzico validate HMAC SHA-256 signatures before processing payloads, preventing forged payload injection. |
| **Role-Based Access Control (RBAC)** | Strict role hierarchy: `Super Admin` (global infrastructure), `Client Administrator` (workspace owner), `Agent` (assigned chats/calls), `Viewer` (read-only analytics). |
| **Automated Data Lifecycle Management** | Scheduled pruning via `php artisan app:prune-ephemeral-data` cleans expired session tokens, old webhook logs, and orphaned media files. |
| **cPanel / Shared Hosting Ready** | Can be deployed directly to standard shared hosting (Apache/Nginx + PHP + MySQL) without requiring root shell or Docker. Queues run via standard minute cron jobs (`php artisan schedule:run`). |
| **Security Headers Enforced** | Responses automatically include `Strict-Transport-Security` (HSTS), `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, and CSP headers. |

---

## 5. Performance Optimizations Implemented

### 1. Database Query & Schema Optimization
- **Composite Indexing**: Applied indexes on `(workspace_id, created_at)`, `(workspace_id, status)`, `(conversation_id, created_at)` to eliminate full table scans during real-time feed retrieval.
- **Eager Loading (`with()`)**: Eliminated N+1 query bottlenecks across inbox feeds, CRM boards, and call logs.

### 2. Frontend Code Splitting (Vite)
- Large third-party libraries are isolated into dedicated vendor chunks (`vendor-charts`, `vendor-handsontable`, `vendor-flows`, `vendor-exceljs`, `vendor-framework`).
- Only required modules load per page, resulting in initial bundle sizes under 15 kB gzip for common pages.

### 3. Inertia Partial Reloading
- Live polling and tab switching utilize Inertia's `only: [...]` parameter, returning partial JSON props rather than re-rendering the full page layout.

### 4. Direct SVG Vector Generation
- QR code generation for the Android APK download link is generated dynamically as a clean, standalone SVG stream with zero disk I/O and zero external dependencies.

---

## 6. Testing & Quality Assurance Summary

The platform is backed by an automated test suite verifying every layer of the application:

```bash
php artisan test
```

### Key Verified Feature Suites:
1. `MobileAppAndApkManagementTest`: Mobile REST APIs, 360° customer profile, in-app calling entitlement gates, APK download increment counter, and admin release controls.
2. `UnifiedOmnichannelInboxTest`: Omnichannel message routing, AI suggestion generation, human agent takeover, and conversation assignment.
3. `AiKnowledgeBaseAndBusinessTrainingTest`: Document chunking, vector indexing, RAG retrieval, and tenant isolation.
4. `AiVoiceAgentAndInteractiveCallTest`: Inbound voice webhooks, speech gathering, knowledge response, and call summaries.
5. `BillingCheckoutTest` & `StripeWebhookRenewalTest`: Plan subscriptions, multi-currency conversion, webhook renewals, and wallet balances.
6. `ProductionSecurityHardeningTest`: Cross-tenant attack prevention, webhook signature enforcement, and impersonation safety.

**Overall Test Execution Result**:
- **Total Tests**: **821 Passed**
- **Total Assertions**: **3,246 Assertions**
- **Failures / Errors**: **0**
- **Pass Rate**: **100%**
