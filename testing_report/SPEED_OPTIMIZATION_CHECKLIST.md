# ⚡ Growbridge Connect — Speed Optimization Checklist

Covers both **page load speed** (frontend) and **API/backend response speed**, tailored to the current stack: Laravel 11, React 18 + Inertia.js, MySQL/MariaDB, Vite 6.

---

## 🖥️ Backend / API Response Speed

### 1. Caching (biggest single win, usually)
- [ ] Add **Redis** as the cache driver (if not already) for query results, config, and route caching.
- [ ] Cache expensive/repeated queries: dashboard KPI counters, plan entitlements, workspace settings — with short TTLs (30–120s) so data stays near-fresh.
- [ ] Run `php artisan config:cache`, `route:cache`, `view:cache` in production deploy scripts — these are free wins often forgotten.
- [ ] Cache the mobile `/bootstrap` endpoint response per-user for a few seconds; it's called on every app open.

### 2. Query & Database
- [ ] Run `EXPLAIN` on the top 10 slowest queries (use Laravel Telescope's query log or `DB::listen()` temporarily) and confirm the composite indexes you already added are actually being hit.
- [ ] Check for any remaining N+1s in newer feature areas (Automation Builder, CRM Kanban, Smart Search) — these were added after your original N+1 audit.
- [ ] Paginate everything that lists rows (inbox feed, contacts, call logs) — never load full tables.
- [ ] Use `select()` to fetch only needed columns on hot endpoints instead of `SELECT *`.

### 3. Queue Everything Non-Critical
- [ ] Confirm nothing synchronous is blocking an HTTP response that could be queued — e.g. sending AI Assist requests, dispatching outbound WhatsApp messages, generating CSV exports.
- [ ] For the mobile `/chat/{id}/send` endpoint, return success immediately and process delivery status via webhook/queue rather than waiting on the provider API call.

### 4. Response Payload Size
- [ ] Use **Laravel API Resources** (`JsonResource`) to strictly shape mobile/API responses — avoid accidentally serializing entire Eloquent models with unused relations.
- [ ] Enable **gzip/Brotli compression** at the web server level (Nginx/Apache) for all API and page responses.
- [ ] For `/feed` and `/chat/{id}` endpoints, cap message history returned per page (e.g. last 50) with cursor pagination for "load more."

### 5. Realtime Layer
- [ ] Confirm WebSocket (Reverb) connection isn't adding latency to the initial page load — it should connect asynchronously, not block first paint.
- [ ] Debounce/throttle typing-indicator and presence events so they don't flood the queue under high concurrency.

---

## 🌐 Frontend / Page Load Speed

### 1. Bundle Size (you're already doing some of this)
- [ ] Confirm vendor chunk sizes with `npm run build -- --report` (or `vite-bundle-visualizer`) — check `vendor-handsontable`, `vendor-flows`, `vendor-exceljs` aren't loading on pages that don't need them.
- [ ] Lazy-load heavy components: React Flow (Automation Builder), Handsontable (grid editing), ExcelJS (export) — only import them on the routes that use them, via `React.lazy()` + `Suspense`.
- [ ] Audit for duplicate dependencies across chunks (common when multiple libs pull in different versions of the same sub-dependency).

### 2. Images & Assets
- [ ] Serve images in **WebP/AVIF** with fallback, and lazy-load below-the-fold images (contact avatars, chat attachments).
- [ ] Use responsive image sizes for the QR code / APK download page rather than a single large SVG/PNG if not already vector.

### 3. Inertia-Specific
- [ ] Confirm partial reloads (`only: [...]`) are used consistently across all polling/tab-switch interactions, not just some — audit the inbox, CRM board, and dashboard.
- [ ] Use Inertia's **lazy props** (`Inertia::lazy()`) for expensive props (e.g. full analytics data) that aren't needed on initial page render.
- [ ] Preload likely-next pages with Inertia's link prefetching (`<Link prefetch>`) on high-traffic navigation paths (Inbox → Conversation).

### 4. CDN & Caching Headers
- [ ] Serve built JS/CSS assets through a CDN (Cloudflare, or your host's built-in CDN) with long cache lifetimes + hashed filenames (Vite does this by default — just confirm CDN is in front of it).
- [ ] Set proper `Cache-Control` headers on static assets vs API responses (long cache for assets, `no-store` for user-specific API data).
- [ ] If not already, put Cloudflare (or similar) in front of the whole app for edge caching + DDoS protection + faster TLS handshakes globally.

### 5. Critical Rendering Path
- [ ] Defer non-critical JS (analytics scripts, chat widgets) so they don't block first paint.
- [ ] Check font loading strategy — use `font-display: swap` to avoid invisible-text flash.
- [ ] Run a **Lighthouse audit** on the dashboard, inbox, and public landing/login pages to get concrete before/after numbers.

---

## 📊 How to Measure (do this first, before optimizing blindly)

- [ ] Run **Lighthouse** (Chrome DevTools) on your 3 most-visited pages — record baseline scores (Performance, LCP, TBT, CLS).
- [ ] Add **Laravel Telescope** (staging only) to see actual query counts and durations per request.
- [ ] Set up basic APM (even just Sentry Performance monitoring, since Sentry is already integrated) to track p50/p95/p99 response times per endpoint in production.
- [ ] Re-measure after each change — don't optimize based on guesses.

---

## Suggested Order

1. **Measure first** (Lighthouse + Sentry Performance) — 30 minutes, tells you where the real bottleneck is.
2. **Backend caching** (Redis for config/route/query cache) — usually the fastest win for API speed.
3. **Frontend lazy-loading** of heavy libraries (React Flow, Handsontable, ExcelJS) — usually the fastest win for page load.
4. **CDN in front of static assets** — cheap, big impact on global load times.
5. Everything else, guided by what Lighthouse/Sentry actually show as slow.
