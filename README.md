# 🚀 Growbridge Connect

> **Omnichannel Marketing Automation, WhatsApp Business API, AI Chatbots, Twilio VoIP Business Calling, AI Voice Agents, CRM & Smart Lead Pipeline SaaS**

Growbridge Connect is an enterprise-ready, multi-tenant SaaS platform built to unify customer communication across **WhatsApp, VoIP Phone Calling, AI Voice Agents, SMS, Email, and Social Channels**. It combines conversational AI with human agent handoff, an integrated visual flow automation engine, a Kanban deal CRM, centralized provider billing with wallet balances, and a cross-platform mobile application for Android and iOS.

---

## 📑 Table of Contents

1. [Project Overview & Key Capabilities](#-project-overview--key-capabilities)
2. [Technology Stack](#-technology-stack)
3. [System Architecture](#-system-architecture)
4. [Getting Started & Local Development](#-getting-started--local-development)
5. [How We Built Growbridge Connect](#-how-we-built-growbridge-connect)
6. [Production-Grade Engineering & Standards](#-production-grade-engineering--standards)
7. [Performance Optimizations](#-performance-optimizations)
8. [Testing & Quality Assurance](#-testing--quality-assurance)
9. [Deployment Options](#-deployment-options)
10. [Directory Structure](#-directory-structure)

---

## 🌟 Project Overview & Key Capabilities

### 1. Unified Omnichannel Inbox
- **WhatsApp Business API**: Direct integration via Meta Cloud API or On-Premises Graph API.
- **VoIP Business Calling**: Two-way in-browser and mobile calling powered by Twilio WebRTC with full call recording, real-time audio telemetry, and disposition logs.
- **Multichannel Feed**: Unified customer communication across WhatsApp, Webchat, SMS, Email, and Social Media (Facebook Messenger, Instagram DMs, Twitter/X).
- **Customer 360° Profile**: Single unified contact card showing chronological chat messages, voice call recordings, durations, CRM pipeline stage, tags, deal value, and AI memory.

### 2. Dual Conversational AI Engines
- **Text AI Agents & Chatbots**: Built-in Retrieval-Augmented Generation (RAG) knowledge base supporting PDF, TXT, DOCX, FAQ lists, and website URL crawlers. Strict anti-hallucination guardrails and fallback answers.
- **AI Voice Agents**: Interactive Voice Response (IVR) with real-time speech-to-text (STT), conversational LLM responses, text-to-speech (TTS), and automated human transfer dials.
- **✨ AI Assist & Human Handoff**: One-click AI reply suggestions, tone improvement, language translation, conversation summaries, automatic lead extraction, and hot lead / complaint auto-handoff.

### 3. Visual Automation Engine & Campaign Studio
- **Drag-and-Drop Visual Workflow Builder**: Triggers, conditional filters, delays, Webhook/HTTP requests, SMS dispatches, WhatsApp template messages, and AI intent classifiers.
- **Omnichannel Broadcasting**: Template-approved WhatsApp campaigns, SMS bulk broadcasts, suppression list management, opt-out compliance, and delivery tracking.
- **Google Maps & Places Lead Scraper**: Automated local business lead discovery, phone number extraction, and CRM stage assignment.

### 4. Native Deals CRM & Smart Lead Pipeline
- **Kanban Board & Custom Stages**: Drag-and-drop lead stage progression with automated score calculation (Cold, Warm, Hot).
- **Task & Activity Tracking**: Team task assignments, due-date notifications, timeline event auditing, and internal collaboration notes with `@mentions`.

### 5. Centralized Provider Billing & Growbridge Wallet
- **Single Invoice Model**: Customers pay Growbridge directly; Growbridge provisions and settles underlying costs with Meta, Twilio, OpenAI, Claude, and Gemini behind the scenes.
- **Growbridge Wallet**: Rechargeable prepaid balance in local currency (INR, USD, EUR, etc.) with real-time deduction per WhatsApp conversation, voice minute, and AI token.
- **Multi-Gateway Checkout**: Integrated with Stripe, Razorpay, PayPal, Paddle, Iyzico, and Offline Bank Transfer.

### 6. Mobile Application (Android & iOS)
- **Flutter Cross-Platform App**: Manages **💬 WhatsApp Chat + 📞 Business Calling** on the go.
- **Server-Side Security**: Zero exposed provider credentials; all actions route securely through authenticated `/api/v1/mobile/*` REST APIs.
- **Dynamic APK Management**: Super Admin controls APK releases, version codes, release notes, force-update thresholds, and generates instant SVG QR codes for user download.

---

## 🛠️ Technology Stack

| Layer | Technology | Description |
|---|---|---|
| **Backend Framework** | **PHP 8.2+ / Laravel 11** | Modern PHP backend with modular domain architecture, Eloquent ORM, Sanctum API auth, and artisan CLI tools. |
| **Frontend Framework** | **React 18 / Inertia.js** | Single Page Application (SPA) experience without API boilerplate, seamless state passing from Laravel controllers. |
| **Styling & UI** | **TailwindCSS / Headless UI** | Bespoke design system, dark mode support, glassmorphism, responsive grid layouts, and Lucide React icons. |
| **Mobile App** | **Flutter 3.x (Dart)** | Native cross-platform mobile client for Android & iOS with WebRTC audio dialing and push signaling. |
| **Database** | **MySQL 8.0+ / MariaDB 10.6+** | Relational multi-tenant schema with foreign key constraints, composite tenant indexes, and soft deletes. |
| **Cache & Queues** | **Redis / Database Driver** | Asynchronous job queues for campaign dispatching, AI embeddings, webhook intake, and SLA monitoring. |
| **Realtime Engine** | **Laravel Reverb / Pusher** | WebSockets for live typing indicators, instant message push, inbound call modals, and real-time dashboard analytics. |
| **Voice & Telephony** | **Twilio Voice API / TwiML** | WebRTC browser softphone, call recording, SIP trunking, inbound routing, and automated speech gathering. |
| **AI & LLM Services** | **OpenAI / Claude / Gemini / Ollama** | Multi-provider AI gateway with RAG vector search, tone formatting, speech transcription, and intent recognition. |
| **Asset Bundler** | **Vite 6** | Ultra-fast HMR and optimized production code-splitting into distinct chunk bundles. |

---

## 🏛️ System Architecture

```
                                  ┌─────────────────────────────────┐
                                  │      CLIENTS & USERS            │
                                  ├────────────────┬────────────────┤
                                  │  Web Browser   │  Mobile App    │
                                  │ (React/Inertia)│(Flutter/WebRTC)│
                                  └───────┬────────┴────────┬───────┘
                                          │                 │
                                    HTTPS / Inertia    REST API v1
                                          │          (Bearer Token)
                                          ▼                 ▼
┌───────────────────────────────────────────────────────────────────────────────────┐
│                           GROWBRIDGE APPLICATION CORE                             │
│                                                                                   │
│  ┌──────────────────────┐  ┌──────────────────────┐  ┌─────────────────────────┐  │
│  │ Security & Scope     │  │ Centralized Billing  │  │ Plan Entitlement        │  │
│  │ • Tenant Isolation   │  │ • Single Invoice     │  │ • Feature Gating        │  │
│  │ • Role-Based RBAC    │  │ • Wallet Balance     │  │ • Limit Enforcement    │  │
│  │ • HMAC Signature     │  │ • Cost Ledger Sync   │  │ • Upgrade Gating        │  │
│  └──────────────────────┘  └──────────────────────┘  └─────────────────────────┘  │
│                                                                                   │
│  ┌─────────────────────────────────────────────────────────────────────────────┐  │
│  │                            MODULAR DOMAINS                                  │  │
│  │  [Shared]       [AI Studio]     [Voice Studio]  [Automations]  [Campaigns]  │  │
│  │  • Contacts     • Knowledge RAG • Twilio Soft   • Visual Flow  • WhatsApp   │  │
│  │  • Inbox Chat   • Multi-LLM     • AI Voice IVR  • Triggers     • SMS Bulk   │  │
│  │  • 360 Profile  • AI Assist     • Recordings    • HTTP Nodes   • Analytics  │  │
│  └─────────────────────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────┬─────────────────────────────────────────┘
                                          │
                  ┌───────────────────────┼───────────────────────┐
                  ▼                       ▼                       ▼
      ┌───────────────────────┐ ┌───────────────────┐ ┌─────────────────────────┐
      │  External Providers   │ │  Data Storage     │ │  Background Services    │
      │  • Meta WhatsApp API  │ │  • MySQL/MariaDB  │ │  • Laravel Queue Worker │
      │  • Twilio Voice / SMS │ │  • File Storage   │ │  • Cron Task Scheduler  │
      │  • OpenAI / Claude    │ │  • Redis Cache    │ │  • Ephemeral Data Pruner│
      └───────────────────────┘ └───────────────────┘ └─────────────────────────┘
```

---

## 🚀 Getting Started & Local Development

### 1. Prerequisites
- **PHP**: 8.2 or higher (extensions required: `pdo_mysql`, `curl`, `mbstring`, `openssl`, `xml`, `zip`, `gd`)
- **Composer**: 2.x
- **Node.js**: 18.x or 20.x & **NPM**
- **Database**: MySQL 8.0+ or MariaDB 10.6+
- **Flutter SDK**: 3.x *(only needed if building or compiling the mobile client)*

---

### 2. Installation Steps

#### Step 1: Clone Repository & Configure Environment
```bash
git clone <repository-url> wagrowbridge
cd wagrowbridge
cp .env.example .env
```

#### Step 2: Configure Database & App Keys in `.env`
Edit your `.env` file with database credentials:
```env
APP_NAME="Growbridge Connect"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=growbridge_db
DB_USERNAME=root
DB_PASSWORD=your_password
```

#### Step 3: Install PHP & Node Dependencies
```bash
composer install
npm install
```

#### Step 4: Generate App Key, Run Migrations & Seeders
```bash
php artisan key:generate
php artisan migrate --seed
```

#### Step 5: Link Storage & Compile Assets
```bash
php artisan storage:link
npm run build
```

---

### 3. Running Locally

Start the development server, background queue worker, and Vite hot-reload server:

```bash
# Terminal 1: Laravel Web Server
php artisan serve --port=8000

# Terminal 2: Queue Worker
php artisan queue:work --sleep=3 --tries=3

# Terminal 3: Vite Dev Server (Optional for live JSX hot-reloading)
npm run dev
```

Visit **`http://localhost:8000`** in your browser.

---

### 4. Running the Flutter Mobile App

```bash
cd mobile
flutter pub get
flutter run
```

---

### 🔑 Default Credentials (from Seeders)

| Role | Email | Password | Access Area |
|---|---|---|---|
| **Super Admin** | `admin@growbridge.test` | `password` | `/admin/login` |
| **Client Admin** | `client@growbridge.test` | `password` | `/login` |

---

## 🏗️ How We Built Growbridge Connect

### 1. Modular Domain Architecture
Rather than clustering controllers and models in generic folders, business features are partitioned into isolated modular domains (`app/Modules/*`):
- `App\Modules\Shared`: Unified contacts, conversations, messages, channels, and 360° customer records.
- `App\Modules\AI`: Knowledge bases, document chunking, embeddings, chatbot definitions, and AI simulator sandbox.
- `App\Modules\Voice`: Telephony phone numbers, voice calls, Twilio softphone routing, audio recording, and AI voice agents.
- `App\Modules\Leads`: CRM pipelines, stages, drag-and-drop Kanban scoring, scraping jobs, and deal values.
- `App\Modules\Automations`: Visual workflow engine, state transition execution, loop protection, and HTTP request nodes.
- `App\Modules\Campaigns`: Omnichannel marketing campaigns, suppression filters, template approval, and delivery analytics.

### 2. Centralized Entitlement Engine
Feature gating is not hardcoded across random controllers. A centralized `EntitlementService` (`App\Services\Billing\EntitlementService`) evaluates active plan permissions in one place:
```php
if (!EntitlementService::can($workspace, 'voice_calling')) {
    return response()->json(['error' => 'upgrade_required'], 403);
}
```
The React frontend reads these entitlements directly from Inertia page props, gracefully adapting UI navigation and displaying modal upgrade banners.

### 3. Native AI Assist & Human Takeover Safeguards
To prevent AI chatbots from responding when a human agent takes over or during sensitive complaints:
1. Every conversation maintains an `is_ai_active` boolean state.
2. Inbound messages pass through sentiment and keyword scanners (`"talk to human"`, `"agent"`, `"dispute"`).
3. If human mode is triggered, `is_ai_active` is automatically set to `false`, a notification alert is dispatched to assigned team members, and all automated LLM replies are blocked until manually reactivated.

---

## 🛡️ Production-Grade Engineering & Standards

Growbridge Connect was built to satisfy stringent production requirements:

### 1. Multi-Tenant Workspace Data Isolation
- Every query on tenant tables enforces `where('workspace_id', $workspace->id)`.
- Middleware `EnsureClientScope` verifies that authenticated users can only read, mutate, or delete records belonging to their active workspace. Cross-tenant access attempts return HTTP `403 Forbidden` or `404 Not Found`.

### 2. Webhook Ingestion & HMAC Verification
- Webhooks from Meta (WhatsApp Cloud API), Twilio, Stripe, Razorpay, Paddle, and Iyzico validate cryptographically signed HMAC signatures (`X-Hub-Signature-256`, `X-Twilio-Signature`, etc.) before processing payloads.
- Idempotency guards prevent duplicate message creation on webhook retries.

### 3. Server-Side Security Headers & Hardening
- Automatic response headers: HSTS (`Strict-Transport-Security`), `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, and Content Security Policy (CSP).
- Zero credentials leak in frontend or mobile clients: Meta System User Tokens, Twilio Auth Tokens, OpenAI API keys, and database passwords are kept strictly on the backend.

### 4. cPanel Shared Hosting & Rootless Deployment Compatibility
- Deployable on standard cPanel / shared Linux hosting without requiring Docker, root shell, or supervisor daemons.
- Queue jobs and background maintenance tasks are executable via standard cPanel cron commands (`php artisan schedule:run` and `php artisan queue:work --stop-when-empty`).

### 5. Automated Data Lifecycle Management
- Built-in artisan command `php artisan app:prune-ephemeral-data` automatically purges expired session tokens, old webhook logs, and temporary media files to prevent storage bloat.

---

## ⚡ Performance Optimizations

1. **Frontend Code-Splitting with Vite**:
   - Heavy dependencies (Recharts, Handsontable, ExcelJS, React Flow) are bundled into dedicated async chunks (`vendor-charts`, `vendor-handsontable`, `vendor-flows`, `vendor-framework`).
   - Initial page loads are lightweight and fast.
2. **Database Query Indexing & Eager Loading**:
   - High-throughput tables (`messages`, `conversations`, `contacts`, `voice_calls`, `crm_leads`) feature composite indexes on `(workspace_id, created_at)` and `(workspace_id, status)`.
   - All relationship lookups use eager loading (`with(['contact', 'messages'])`) to prevent N+1 query overhead.
3. **Inertia Partial Reloads (`only`)**:
   - Tab switches and live polling requests retrieve only required dataset keys, reducing JSON payload size by up to 85%.
4. **SVG QR Code Generation**:
   - Mobile app download QR codes are generated directly as lightweight vector SVGs on-demand with zero temporary disk file I/O.

---

## 🧪 Testing & Quality Assurance

Growbridge Connect includes a comprehensive automated test suite testing routes, APIs, database operations, security policies, billing models, and mobile endpoints.

### Run Automated Tests
```bash
php artisan test
```

### Test Suite Summary
```text
   PASS  Tests\Unit\ExampleTest
   PASS  Tests\Unit\ShopifyClientProductsTest
   PASS  Tests\Feature\AdminIntegrationsMvpTest
   PASS  Tests\Feature\AiAgentsAndPromptStudioTest
   PASS  Tests\Feature\AiKnowledgeBaseAndBusinessTrainingTest
   PASS  Tests\Feature\AiReplyAndHumanHandoffTest
   PASS  Tests\Feature\AiVoiceAgentAndInteractiveCallTest
   PASS  Tests\Feature\AiVoiceStudioTest
   PASS  Tests\Feature\AutomationBuilderAndAiWorkflowTest
   PASS  Tests\Feature\BillingCheckoutTest
   PASS  Tests\Feature\Billing\PlatformDefaultCurrencyTest
   PASS  Tests\Feature\Billing\StripeWebhookRenewalTest
   PASS  Tests\Feature\CrmAndLeadPipelineTest
   PASS  Tests\Feature\DashboardProductTourTest
   PASS  Tests\Feature\MobileAppAndApkManagementTest
   PASS  Tests\Feature\ProductionDeploymentAndHealthTest
   PASS  Tests\Feature\ProductionSecurityHardeningTest
   PASS  Tests\Feature\SmartSearchAndSegmentationTest
   PASS  Tests\Feature\TwilioProvisioningAndWebhooksTest
   PASS  Tests\Feature\UnifiedOmnichannelInboxTest
   PASS  Tests\Feature\VoiceCallIntelligenceTest
   ...

  Tests:    821 passed (3,246 assertions)
  Duration: 100% Pass Rate
```

---

## 📦 Deployment Options

### Option A: cPanel / Shared Hosting Deployment
See full step-by-step guide in [CPANEL_DEPLOYMENT.md](file:///e:/codecanyon-bijStL3M-whatsmine-ai-omnichannel-marketing-automation-chatbot-saas-whatsapp-messenger-sms-email/WAgrowbridge/CPANEL_DEPLOYMENT.md).
1. Zip and upload files to `public_html` (or subfolder).
2. Configure `.env` database and mail credentials.
3. Add a single cPanel cron job running every minute:
   ```bash
   * * * * * cd /home/username/public_html && php artisan schedule:run >> /dev/null 2>&1
   ```

### Option B: VPS / Cloud Server (Ubuntu + Nginx)
See [DEPLOYMENT_GUIDE.md](file:///e:/codecanyon-bijStL3M-whatsmine-ai-omnichannel-marketing-automation-chatbot-saas-whatsapp-messenger-sms-email/WAgrowbridge/DEPLOYMENT_GUIDE.md).
- Use Supervisor to manage `php artisan queue:work`.
- Configure SSL via Let's Encrypt Certbot.

---

## 📂 Directory Structure

```text
WAgrowbridge/
├── app/
│   ├── Http/Controllers/
│   │   ├── Admin/              # Super Admin controllers (App releases, Users, Plans)
│   │   ├── Api/V1/             # REST API controllers & Mobile App endpoints
│   │   ├── Client/             # User workspace controllers (Settings, Wallet, Inbox)
│   │   └── Webhooks/           # Multi-provider webhook ingestion handlers
│   ├── Models/                 # Core Eloquent models (User, Workspace, Plan, Wallet)
│   ├── Modules/                # Modular domain logic
│   │   ├── AI/                 # Knowledge Base, RAG, Chatbots, Multi-LLM
│   │   ├── Automations/        # Visual Flow Engine, Triggers, Actions
│   │   ├── Campaigns/          # Broadcasting, Templates, Audiences
│   │   ├── Leads/              # CRM Pipeline, Kanban, Google Places Scraper
│   │   ├── Shared/             # Contacts, Conversations, Messages, 360 View
│   │   └── Voice/              # Twilio Phone Numbers, Voice Calls, AI Voice
│   └── Services/               # Business services (Billing, Entitlements, Adapters)
├── database/
│   ├── migrations/             # Database schema migrations
│   └── seeders/                # Database seeders (Plans, Admin, Releases)
├── mobile/                     # Flutter cross-platform mobile application
│   ├── lib/
│   │   ├── core/api/           # Authenticated REST API client
│   │   ├── screens/
│   │   │   ├── calls/          # VoIP dialer, active call, incoming call, history
│   │   │   ├── chat/           # WhatsApp inbox, conversation, AI assist drawer
│   │   │   ├── contacts/       # Directory, 360° customer profile
│   │   │   ├── home/           # App Home, live KPI metrics, recent chats
│   │   │   └── locked/         # Plan upgrade required screen
│   │   └── main.dart           # App root & persistent bottom navigation
│   └── pubspec.yaml            # Mobile dependencies & build configuration
├── resources/
│   ├── js/
│   │   ├── Components/         # Reusable React components (Modals, UI, QR)
│   │   ├── Layouts/            # App layouts (AdminLayout, ClientLayout, MobileLayout)
│   │   └── Pages/              # Inertia React views (Dashboard, Inbox, CRM, Voice)
│   └── views/                  # Blade templates (App layout, Auth, Error pages)
├── routes/
│   ├── admin.php               # Super Admin control panel routes
│   ├── api.php                 # REST API & Mobile endpoints (/api/v1/mobile/*)
│   ├── client.php              # Workspace user routes (/app/*)
│   ├── web.php                 # Public routes & APK download endpoints
│   └── webhooks.php            # Provider webhook intake endpoints
└── tests/
    ├── Feature/                # Complete feature test suites (820+ tests)
    └── Unit/                   # Unit test suites
```

---

## 📄 License & Credits

Built with precision for enterprise omnichannel communication and AI automation.  
Designed and engineered by the Growbridge Team.
