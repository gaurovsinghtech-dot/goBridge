# 🚀 Growbridge Connect — Enterprise Upgrade Roadmap

**Purpose of this document:** Concrete, actionable engineering tasks to close the gap between "solid mid-market SaaS" and "enterprise-grade platform." Each item includes what to build, where it likely goes in the codebase, and why it matters. Written so an AI coding agent can pick up each section and implement it directly.

---

## Priority 0 — Observability (do this first, low effort/high signal)

- [ ] **Add Sentry (or Bugsnag) for error tracking**
  - Install `sentry/sentry-laravel` via Composer
  - Configure `SENTRY_LARAVEL_DSN` in `.env` / `config/sentry.php`
  - Wrap queue job failures and webhook processing exceptions with context (tenant/workspace_id, provider name)
  - Add Sentry SDK to the React frontend (`@sentry/react`) and Flutter app (`sentry_flutter`)

- [ ] **Enable Laravel Telescope for local/staging debugging**
  - `composer require laravel/telescope --dev`
  - Restrict to `Super Admin` role only in production if enabled there

- [ ] **Add Laravel Horizon if using Redis queues**
  - Gives real-time queue depth, failed jobs, and throughput dashboards
  - Critical since the platform relies heavily on async webhook/message processing

- [ ] **Centralized structured logging**
  - Switch default log channel to JSON formatted (`config/logging.php`)
  - Include `workspace_id`, `request_id`, and `user_id` in every log line via a logging middleware
  - Ship to a log aggregator (self-hosted: Grafana Loki; managed: Papertrail/Logtail)

---

## Priority 1 — CI/CD Pipeline

- [ ] **GitHub Actions (or GitLab CI) workflow**
  - Trigger on every PR: `composer install`, `php artisan test`, `npm run build`
  - Fail the PR if any of the 821 tests fail or coverage drops below a set threshold
  - Add a badge to `README.md` showing build status

- [ ] **Add test coverage reporting**
  - `phpunit --coverage-clover` or Pest's coverage flag
  - Publish to Codecov or Coveralls; show % coverage, not just pass count

- [ ] **Staging environment + deploy gate**
  - Auto-deploy `main` branch to a staging server on merge
  - Require manual approval step before production deploy
  - Document rollback procedure (`php artisan down`, DB migration rollback plan)

---

## Priority 2 — Infrastructure & Deployment Maturity

- [ ] **Add Docker support alongside existing cPanel deployment**
  - `Dockerfile` for the Laravel app (PHP-FPM + Nginx)
  - `docker-compose.yml` for local dev: app, MySQL, Redis, Reverb
  - Keep cPanel deployment docs as-is for SMB tier — document both paths explicitly in `DEPLOYMENT.md`

- [ ] **Document a cloud-native deployment path**
  - Kubernetes manifests or a Helm chart (even a minimal starting point)
  - Horizontal scaling story for the queue workers and Reverb WebSocket nodes

- [ ] **Fix the single-node WebSocket bottleneck**
  - Configure Laravel Reverb to run behind Redis pub/sub so multiple Reverb instances can share state
  - Document the migration path to a managed provider (Pusher/Ably) for very large tenants
  - Load test current Reverb setup to find the actual concurrent-connection ceiling

- [ ] **Database scaling plan**
  - Add read replica support notes (Eloquent read/write connection split)
  - Document a sharding or per-large-tenant-database strategy if any single workspace could outgrow shared tables

---

## Priority 3 — Security & Compliance

- [ ] **Secrets management upgrade**
  - Move from `.env`-only encrypted config to a proper secrets manager (AWS Secrets Manager, HashiCorp Vault, or Laravel's encrypted config with rotation policy)
  - Document key rotation procedure for Meta/Twilio/AI provider tokens

- [ ] **Encryption at rest**
  - Confirm and document MySQL encryption at rest (or note it as a to-do for the hosting layer)
  - Ensure sensitive columns (tokens, PII) use Laravel's `encrypted` Eloquent cast

- [ ] **Compliance roadmap section (add to PROJECT_OVERVIEW.md)**
  - GDPR: data residency options, right-to-erasure endpoint, data export endpoint
  - SOC 2: note current status ("roadmap" is fine if not certified yet) — access logging, change management, incident response policy
  - Add a `SECURITY.md` with responsible disclosure policy

- [ ] **Rate limiting & abuse protection**
  - Confirm Laravel's built-in throttle middleware is applied to all public API routes (webhooks excluded, but signed)
  - Add per-workspace API rate limits, not just global

---

## Priority 4 — AI/RAG Pipeline Hardening

- [ ] **Document the actual vector store**
  - Specify pgvector / Pinecone / Weaviate / Milvus — whichever is in use
  - Add chunking strategy details (chunk size, overlap, embedding model used)

- [ ] **Add retrieval evaluation**
  - Build a small eval set of Q&A pairs per knowledge base
  - Track retrieval precision/recall before/after chunking or embedding model changes

- [ ] **Hallucination guardrails**
  - Confirm "Strict RAG" means responses are constrained to retrieved context only (no free generation fallback)
  - Add a confidence threshold — if retrieval score is below X, fall back to "I don't know, escalate to human agent"
  - Log low-confidence responses for review

---

## Priority 5 — API & Integration Maturity

- [ ] **Generate OpenAPI/Swagger spec**
  - Use `dedoc/scramble` or `darkaonline/l5-swagger` to auto-generate from existing Laravel routes
  - Publish at `/docs/api` behind auth for partners

- [ ] **API versioning**
  - Prefix routes with `/api/v1/` if not already done
  - Document deprecation policy for future breaking changes

- [ ] **Webhook retry & dead-letter handling**
  - Confirm failed webhook processing jobs retry with backoff
  - Add a dead-letter queue/table for webhooks that fail after max retries, with an admin UI to inspect/replay them

---

## Priority 6 — Documentation Polish

- [ ] **Rewrite test results section to avoid "marketing scoreboard" framing**
  - Replace "100% Pass Rate, 0 Failures" with actual coverage %, CI badge, and a note on flaky test handling
  - Link to the CI dashboard instead of hardcoding numbers in the doc

- [ ] **Add an "Infrastructure & Compliance Roadmap" section to `PROJECT_OVERVIEW.md`**
  - Frames current gaps (shared hosting, no SOC 2 yet, single-node WS) as forward-looking plans rather than omissions
  - Include target dates/milestones if available

---

## Suggested Execution Order for an AI Agent

1. Priority 0 (observability) — fastest wins, no architecture changes
2. Priority 1 (CI/CD) — protects everything built afterward
3. Priority 3 (security basics: secrets, encryption) — low effort, high trust signal
4. Priority 5 (API docs/versioning) — mechanical, agent-friendly
5. Priority 2 (infra/Docker/K8s) — bigger lift, do after the above are stable
6. Priority 4 (RAG hardening) — needs product/data input, not pure engineering
7. Priority 6 (doc polish) — do last, once the above are actually true
