# GROWBRIDGE CONNECT — n8n & ZAPIER-STYLE INTEGRATION GUIDE

**Platform:** GROWBRIDGE CONNECT  
**Tagline:** "Connect. Engage. Automate. Grow."  
**Protocol:** REST API + Inbound/Outbound Webhooks  

---

## 1. Overview & Connection Architecture

Growbridge Connect seamlessly integrates with **n8n**, **Zapier**, **Make.com**, and custom webhook listeners without requiring complex custom plugins.

```
       ┌───────────────────────────────┐
       │     EXTERNAL EVENT SOURCE     │
       │ (Shopify, CRM, Typeform, etc) │
       └──────────────┬────────────────┘
                      │
                      ▼
               ┌─────────────┐
               │  n8n Node   │
               └──────┬──────┘
                      │
           ┌──────────┴──────────┐
           ▼                     ▼
┌─────────────────────┐ ┌─────────────────────┐
│  HTTP Request Node  │ │ Webhook Intake Node │
│ (Growbridge REST)   │ │ (Outbound Events)   │
└──────────┬──────────┘ └──────────┬──────────┘
           │                       │
           ▼                       ▼
┌─────────────────────────────────────────────┐
│             GROWBRIDGE CONNECT              │
│ WhatsApp • Voice Call • AI • Journey Engine │
└─────────────────────────────────────────────┘
```

---

## 2. Recipe 1: Send WhatsApp Message from n8n

### n8n Node Configuration:
- **Node Type:** `HTTP Request`
- **Method:** `POST`
- **URL:** `https://connect.yourdomain.com/api/v1/messages/send`
- **Authentication:** `Header Auth`
  - **Name:** `Authorization`
  - **Value:** `Bearer gb_live_YOUR_API_TOKEN`
- **Send Body:** `JSON`
  ```json
  {
    "channel": "whatsapp",
    "recipient": "={{ $json.customer_phone }}",
    "message": "Hello {{ $json.customer_name }}, your order #{{ $json.order_id }} is confirmed!",
    "metadata": {
      "source": "n8n_shopify_flow"
    }
  }
  ```

---

## 3. Recipe 2: Trigger AI Voice Call on High-Value Lead

Trigger an automated voice agent call when a high-priority lead enters your CRM:

### n8n Node Configuration:
- **Method:** `POST`
- **URL:** `https://connect.yourdomain.com/api/v1/calls`
- **Headers:**
  - `Authorization: Bearer gb_live_YOUR_API_TOKEN`
  - `Content-Type: application/json`
- **JSON Body:**
  ```json
  {
    "to": "={{ $json.lead_phone }}",
    "customer_name": "={{ $json.lead_name }}",
    "custom_variables": {
      "inquiry_type": "Enterprise Demo Request",
      "assigned_rep": "Priya"
    }
  }
  ```

---

## 4. Recipe 3: Listen for Inbound Growbridge Events in n8n

Stream customer responses and call completion summaries directly into your external database or Notion CRM:

1. In **n8n**, add a **Webhook Trigger Node** (Method: `POST`, Path: `growbridge-events`).
2. Copy the Test/Production Webhook URL.
3. In **Growbridge Connect**, navigate to **Settings > Developer > Webhooks > New Webhook**.
4. Paste the URL and subscribe to `message.received`, `lead.updated`, and `call.completed`.
5. Every inbound message and AI voice summary will immediately stream to n8n for automated CRM synchronization.

---

## 5. Security & Idempotency Best Practices

- **Token Scoping:** In **Settings > Developer > API Keys**, grant only the scopes required for your n8n workflow (e.g. `messages:write` and `contacts:write`).
- **Signature Verification:** Verify `X-Growbridge-Signature` in your n8n Crypto / Code node to prevent unauthorized webhook spoofing.
- **Idempotency:** When sending messages from scheduled polling workflows, pass `Idempotency-Key: ORD_1092` header to guarantee zero duplicate messages are dispatched.
