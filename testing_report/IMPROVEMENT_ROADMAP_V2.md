# 🚀 Growbridge Connect — Enterprise-Readiness Roadmap (v2)

> Cross-referenced against `ENTERPRISE_FEATURES_IMPLEMENTED.md`. Completed items are checked off with a note on what was done; only genuinely open items remain as actionable tasks below.

---

## ✅ Already Completed (verified against implementation doc)

- [x] Sentry error tracking with tenant-aware context (workspace_id, user_id, client_id)
- [x] Request ID middleware (UUID) for distributed tracing across logs
- [x] Audit logging for client/admin activity
- [x] Health check endpoints (`/up`, `/healthz`)
- [x] 821-test automated suite with standardized fixtures (`createWorkspaceContext()`, `createSuperAdmin()`)
- [x] Docker support (`docker-compose.queues.yml`) alongside existing cPanel deployment path — dual-tier deployment achieved
- [x] Multi-queue priority architecture (high/default/low)
- [x] Composite DB indexing + N+1 elimination
- [x] GDPR data export (CSV) via `DataExportController`
- [x] Ephemeral data lifecycle pruning command
- [x] HMAC webhook validation across all providers + idempotency tokens
- [x] OWASP security headers middleware
- [x] RAG anti-hallucination guardrail (confidence threshold → fallback/escalation)
- [x] API versioning (`/api/v1/...`)

---

## 🔧 Still Open — Priority 1 (do next, closes credibility gaps fast)

- [ ] **Confirm/add a real CI pipeline.** The current doc describes the test suite but not a workflow file. Add `.github/workflows/tests.yml` (or GitLab CI equivalent) that runs `php artisan test` + linting on every PR, and blocks merge to `main` on failure.
- [ ] **Add code coverage reporting.** Enable Xdebug or PCOV, generate a coverage % report in CI, and publish it (e.g. as a badge or in the PR check). "3,246 assertions, 100% pass" is a test-count metric, not a coverage metric — buyers will ask for the latter.
- [ ] **Add static analysis to CI.** PHPStan/Larastan for backend, ESLint + Prettier for frontend, as required checks.
- [ ] **Generate an OpenAPI/Swagger spec** for the `/api/v1/mobile/*` endpoints already built. Versioning exists; formal docs don't yet.

## 🔧 Still Open — Priority 2 (before enterprise sales conversations)

- [ ] **Secrets management upgrade.** Move from static encrypted `.env`/config to a vault-based approach (HashiCorp Vault, AWS Secrets Manager, or Laravel encrypted config with a documented rotation policy).
- [ ] **Backup & Disaster Recovery documentation.** Define backup frequency, RPO/RTO targets, and run (and document) at least one full restore test.
- [ ] **SOC 2 / ISO 27001 readiness checklist.** Doesn't require certification — a documented checklist of controls already in place (audit logs ✅, tenant isolation ✅, encrypted secrets, access reviews) shows enterprise buyers you're tracking toward it.
- [ ] **Mutation testing.** Add Infection PHP to spot-check that the 821 tests actually catch regressions, not just execute code paths — run periodically, not necessarily every PR.

## 🔧 Still Open — Priority 3 (needed once scale/enterprise tenants arrive)

- [ ] **WebSocket horizontal scaling plan.** Reverb is likely still single-node. Document/implement Redis-backed pub/sub across multiple Reverb nodes, or a migration path to a managed provider (Ably/Pusher) at scale.
- [ ] **Load/stress testing.** Run k6 or JMeter against the omnichannel inbox and webhook ingestion endpoints; publish max sustained throughput numbers.
- [ ] **External security validation.** Run an OWASP ZAP automated scan at minimum, or commission a third-party pen test; document findings and remediation.
- [ ] **Read replica support** for MySQL/MariaDB to offload reporting/analytics queries at higher tenant volume.

---

## Suggested Execution Order

1. CI pipeline + coverage reporting (protects the existing test investment, cheap to add).
2. OpenAPI spec (mobile team already depends on these endpoints — formalizing costs little).
3. Secrets management + backup/DR docs (needed before any serious enterprise security review).
4. SOC 2 readiness checklist (doc-only, high credibility signal).
5. Mutation testing, load testing, external security scan (once the above is stable).
6. WebSocket scaling + read replicas (only urgent once real high-volume tenants are onboarding).
