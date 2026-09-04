# GROWBRIDGE CONNECT — DEVELOPER API & WEBHOOK PLATFORM

**Platform:** GROWBRIDGE CONNECT  
**Tagline:** "Connect. Engage. Automate. Grow."  
**API Version:** `v1`  
**Base URL:** `https://connect.yourbrand.com/api/v1`  
**Authentication:** Bearer Token (`Authorization: Bearer gb_live_...`)  

---

## 1. Getting Started & Authentication

All API endpoints are versioned and strictly multi-tenant isolated. Organization context is automatically derived from the authenticated API token.

### Request Headers:
```http
Authorization: Bearer gb_live_xxxxxxxxxxxxxxxxxxxxxxxx
Content-Type: application/json
Accept: application/json
```

### Granular Scopes:
| Scope | Description |
| :--- | :--- |
| `contacts:read` / `contacts:write` | Search, retrieve, create, and update contacts. |
| `conversations:read` | Read omnichannel chat histories across WhatsApp, Instagram, Messenger, and Email. |
| `messages:write` | Send normalized outbound messages across any supported channel. |
| `campaigns:read` / `campaigns:write` | Manage and trigger broadcast campaigns. |
| `automations:write` | Trigger omnichannel journey workflows and customer sequences. |
| `ai:read` / `ai:write` | Execute AI knowledge base lookups and dynamic chatbot responses. |
| `voice:call` | Initiate AI voice calls via Heyo Phone, Exotel, or Twilio. |
| `webhooks:write` | Manage tenant outbound webhook subscriptions. |

---

## 2. API Endpoints Reference

### 2.1 Contacts API

#### List / Search Contacts
`GET /api/v1/contacts`

**Query Parameters:**
- `search`: Filter by name, email, or phone number.
- `tag`: Filter by contact tag (e.g. `hot_lead`).
- `page`: Page number (default `1`, 25 items/page).

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 142,
      "uuid": "con_881942ab",
      "first_name": "Rahul",
      "last_name": "Sharma",
      "phone_e164": "+919876543210",
      "email": "rahul@example.com",
      "lead_score": 85,
      "lead_temperature": "hot",
      "created_at": "2026-08-22T10:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 25,
    "total": 1
  }
}
```

#### Create / Update Contact
`POST /api/v1/contacts`
```json
{
  "first_name": "Priya",
  "phone": "+919876500000",
  "email": "priya@example.com",
  "tags": ["vip", "web_lead"]
}
```

---

### 2.2 Normalized Messaging API

#### Send Message
`POST /api/v1/messages/send`

Dispatches an outbound message over any channel. The backend automatically handles routing, template validation, and delivery logging.

```json
{
  "channel": "whatsapp",
  "recipient": "+919876543210",
  "message": "Hi Rahul, your appointment is confirmed for tomorrow at 3 PM!",
  "media_url": "https://connect.yourbrand.com/storage/invoice_102.pdf"
}
```

---

### 2.3 AI Voice Telephony API

#### Initiate AI Voice Call
`POST /api/v1/calls`

Initiates an AI-driven telephone call to the recipient using the workspace's active telephony driver (Heyo Phone, Exotel, Twilio).

```json
{
  "to": "+919876543210",
  "customer_name": "Rahul Sharma",
  "custom_variables": {
    "order_id": "ORD-9982",
    "discount_code": "SAVE20"
  }
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "call_uuid": "call_heyo_8899aabb",
    "status": "initiated",
    "provider": "heyo_phone",
    "to": "+919876543210"
  },
  "message": "Call initiated successfully."
}
```

---

### 2.4 Customer Journey Automations API

#### Trigger Automation Flow
`POST /api/v1/automations/{id}/trigger`

Executes a pre-built customer journey workflow for a given contact.

```json
{
  "contact_id": "con_881942ab",
  "trigger_source": "n8n_webhook"
}
```

---

### 2.5 Public Website Lead Capture

#### Public Form Intake (No Private Token Required)
`POST /api/v1/public/leads/{workspace_token}`

Rate-limited public endpoint for external website forms, landing pages, and embeddable widgets.

```json
{
  "name": "Amit Patel",
  "phone": "+919876511223",
  "email": "amit@example.com",
  "message": "Interested in enterprise pricing."
}
```

---

## 3. Outbound Webhook Delivery & Cryptographic Signatures

Growbridge Connect streams real-time events to your external servers, CRMs, or n8n instances.

### Supported Events:
- `contact.created` / `contact.updated`
- `message.received` / `message.sent` / `message.failed`
- `lead.created` / `lead.updated`
- `call.completed` / `call.failed`
- `automation.completed`
- `payment.completed`

### Webhook Signature Verification:
Every HTTP request includes header:
```http
X-Growbridge-Signature: t=1755864000,v1=5d41402abc4b2a76b9719d911017c592
```

**Node.js Verification Example:**
```javascript
const crypto = require('crypto');

function verifyWebhook(payloadString, headerSignature, secret) {
  const [tPart, vPart] = headerSignature.split(',');
  const timestamp = tPart.replace('t=', '');
  const signature = vPart.replace('v1=', '');

  const expectedSignature = crypto
    .createHmac('sha256', secret)
    .update(`${timestamp}.${payloadString}`)
    .digest('hex');

  return crypto.timingSafeEqual(Buffer.from(signature), Buffer.from(expectedSignature));
}
```

---

## 4. Standard Error Responses

Errors follow a standard structure with machine-readable error codes:

```json
{
  "success": false,
  "error": {
    "code": "RATE_LIMIT_EXCEEDED",
    "message": "Too many requests. Rate limit is 60 requests per minute."
  }
}
```

| HTTP Status | Error Code | Description |
| :--- | :--- | :--- |
| `401` | `UNAUTHORIZED` | Invalid or missing API bearer token. |
| `403` | `FORBIDDEN_SCOPE` | Token lacks the required scope for this action. |
| `404` | `NOT_FOUND` | Resource does not exist or belongs to another organization. |
| `422` | `VALIDATION_ERROR` | Required fields are missing or invalid. |
| `429` | `RATE_LIMIT_EXCEEDED` | Request threshold exceeded. Check `Retry-After` header. |
