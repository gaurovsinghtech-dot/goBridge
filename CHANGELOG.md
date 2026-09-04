# CHANGELOG — GROWBRIDGE CONNECT

All notable changes to **GROWBRIDGE CONNECT** are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.0.0] - 2026-08-22

### 🚀 Initial Production Release — "Growbridge Connect"
Tagline: *"Connect. Engage. Automate. Grow."*

### ✨ New Features & Capabilities:
- **Brand & Theme Modernization:**
  - Modernized client navigation with 10 streamlined top-level items in `useClientNav.jsx`.
  - Brand color system: Deep Navy (`#011B40`), Growth Gold (`#FEB51B`), Emerald (`#064E3B`), and Dark Slate.
- **AI Voice Agents & Telephony Engine (Section 49):**
  - First-class **Heyo Phone / MyOperator** integration driver (`HeyoPhoneDriver.php`).
  - Unified multi-provider abstraction for Exotel, Twilio, Plivo, and Heyo Phone.
  - Interactive telephony number management and call logs with recording playback.
- **Omnichannel Customer Journey Automation (Section 50):**
  - Unified customer timeline spanning WhatsApp, Messenger, Instagram, Email, Calls, and CRM.
  - Multi-channel identity resolution with non-destructive contact merging.
  - Dynamic lead scoring engine (0–100) with Cold, Warm, Hot, and Very Hot tiers.
  - Automated opt-out compliance detection (`STOP`, `UNSUBSCRIBE`) with instant sequence cancellation.
  - Smart follow-up safety checks and human handoff escalation service.
  - 10 pre-built SaaS customer journey automation templates.
- **SaaS Billing, Plans & Usage Engine (Section 51):**
  - Centralized feature gating (`FeatureService::can($workspace, 'ai_voice_agents')`).
  - Monthly usage counters with soft warning alerts at 80%, 90%, and 100%.
  - Native Razorpay checkout modal with server-side HMAC-SHA256 signature verification.
  - Safe subscription downgrades preventing quota violations.
  - Automated invoice generation with GST tax calculation.
- **Super Admin Control Center (Section 52):**
  - Platform telemetry & diagnostics (PHP/Laravel versions, MySQL latency, queue, cron heartbeat).
  - Secure tenant impersonation ("Login As Organization") with audit logging.
  - Organization suspension guard (`EnsureWorkspaceNotSuspended`).
  - System-wide announcements manager targeting specific plans or organizations.
  - Centralized masked audit, API, and webhook delivery logs.
- **Production Hardening & Deployment (Sections 53 & 54):**
  - Zero-daemon cPanel architecture (MySQL database queue + 1 single cron runner).
  - Comprehensive backup and disaster recovery playbooks in `BACKUP_RESTORE.md` & `DISASTER_RECOVERY.md`.
  - Dedicated cPanel deployment guide in `CPANEL_DEPLOYMENT.md`.
  - 21-point production deployment guide in `DEPLOYMENT_GUIDE.md`.
  - Automated ephemeral data cleanup command (`php artisan app:cleanup-ephemeral-data`).
  - Unified health check endpoint (`/health`).
