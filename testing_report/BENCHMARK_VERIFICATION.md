# 📊 Speed Optimization — Benchmark Verification

> **Purpose:** Replace the unverified "<50ms API / <1s FCP" claims in `SPEED_OPTIMIZATIONS_IMPLEMENTED.md` with real, measured numbers. Fill in every `___` below with actual data before this doc is considered complete. Re-run after each optimization round to track progress.

---

## 0. How to Run Each Measurement

- **Lighthouse:** Chrome DevTools → Lighthouse tab → run on the deployed (production or staging) URL, not localhost. Run 3x per page and record the median — single runs are noisy.
- **Sentry Performance:** Requires Sentry's performance/tracing feature enabled (separate from error tracking, which is already integrated). Check `config/sentry.php` for `traces_sample_rate` — if `0`, no performance data is being collected yet.
- **API latency (manual):** `curl -w "@curl-format.txt" -o /dev/null -s https://yourapp.com/api/v1/mobile/bootstrap` with a valid Bearer token, run 10x, record median. Or use Postman's built-in timing.

---

## 1. Entitlement Cache — Clarify Scope First

**Before benchmarking, confirm:**
- [ ] Is `EntitlementService::$entitlementsCache` a same-request dedup cache, or intended to persist across requests?
- [ ] What's the PHP execution model in production — standard PHP-FPM (cache resets every request) or Laravel Octane/Swoole (cache persists across requests on a worker)?
- [ ] If cross-request persistence was intended but the app runs PHP-FPM, this needs to move to `Cache::remember()` (Redis) before the "80% query reduction" claim is measured — otherwise you're benchmarking a same-request optimization and calling it something bigger than it is.

**Measured impact (fill in):**
| Metric | Before | After | Method |
|---|---|---|---|
| Queries per request (routes calling `can()` 3+ times) | ___ | ___ | Laravel Telescope / `DB::listen()` count |

---

## 2. Backend API Response Times

Run each endpoint 10x (warm cache) via curl/Postman on production or a production-like staging environment. Record median (p50) and worst-case (p95).

| Endpoint | p50 | p95 | Notes |
|---|---|---|---|
| `GET /api/v1/mobile/bootstrap` | ___ ms | ___ ms | Claimed "<15ms" post-cache — verify |
| `GET /api/v1/mobile/feed` | ___ ms | ___ ms | |
| `GET /api/v1/mobile/chat/{id}` | ___ ms | ___ ms | |
| `POST /api/v1/mobile/chat/{id}/send` | ___ ms | ___ ms | Should return fast if queued correctly |
| `GET /api/v1/mobile/calls` | ___ ms | ___ ms | |
| `GET /api/v1/mobile/contacts-list` | ___ ms | ___ ms | |

**Sentry Performance (production traffic, 7-day window):**
| Metric | Value |
|---|---|
| Overall p50 response time | ___ ms |
| Overall p95 response time | ___ ms |
| Overall p99 response time | ___ ms |
| Slowest endpoint (by p95) | ___ |

---

## 3. Frontend Page Load — Lighthouse Scores

Run on production URLs, mobile + desktop, median of 3 runs.

| Page | Performance Score | LCP | TBT | CLS | FCP |
|---|---|---|---|---|---|
| Login / landing | ___ | ___ s | ___ ms | ___ | ___ s |
| Dashboard | ___ | ___ s | ___ ms | ___ | ___ s |
| Inbox | ___ | ___ s | ___ ms | ___ | ___ s |
| Automation Builder (React Flow page) | ___ | ___ s | ___ ms | ___ | ___ s |
| CRM Kanban board | ___ | ___ s | ___ ms | ___ | ___ s |

*Claimed target: <1s FCP — record actual FCP above and compare.*

---

## 4. Bundle Size — Confirm Lazy-Loading Actually Works

Check the Network tab (not just `vite.config.js` chunk definitions) on a page that does **not** use React Flow/Handsontable/ExcelJS:

- [ ] Open DevTools → Network → filter JS → load the **Contacts** or **Settings** page.
- [ ] Confirm `vendor-flows`, `vendor-handsontable`, and `vendor-exceljs` chunks do **not** appear in the request list.
- [ ] If they do appear, the imports are eager (`import X from 'library'`) rather than lazy (`React.lazy(() => import('library'))`) — chunk splitting alone doesn't defer loading, only dynamic `import()` does.

| Chunk | Appears on non-relevant pages? (Y/N) | Gzipped size |
|---|---|---|
| `vendor-flows` | ___ | ___ kB |
| `vendor-handsontable` | ___ | ___ kB |
| `vendor-exceljs` | ___ | ___ kB |
| `vendor-charts` | ___ | ___ kB |
| Initial page bundle (Contacts/Settings) | — | ___ kB gzip |

*Claimed target: <15 kB gzip for standard pages — record actual and compare.*

---

## 5. Infrastructure Checks

- [ ] Gzip/Brotli compression confirmed active — check response headers for `Content-Encoding: br` or `gzip` on both HTML and API responses.
- [ ] CDN confirmed in front of static assets — check response headers for CDN-specific headers (e.g. `CF-Cache-Status` if Cloudflare) or absent if not yet set up.
- [ ] Redis confirmed as active cache driver in production — check `config/cache.php` default driver and `.env` `CACHE_DRIVER` value.

---

## 6. Summary — Before vs After

Fill in once all sections above are measured:

| Area | Before | After | % Improvement |
|---|---|---|---|
| API p50 response time | ___ | ___ | ___ |
| API p95 response time | ___ | ___ | ___ |
| Dashboard Lighthouse Performance score | ___ | ___ | ___ |
| Dashboard LCP | ___ | ___ | ___ |
| Initial bundle size (gzip) | ___ | ___ | ___ |

**Verdict:** Only update `SPEED_OPTIMIZATIONS_IMPLEMENTED.md`'s executive summary claims once this table is fully populated with real numbers — replace "<50ms API latency" and "<1s FCP" with the actual measured values from this report.
