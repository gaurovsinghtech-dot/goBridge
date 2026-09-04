# GROWBRIDGE CONNECT — INTEGRATIONS GUIDE

**Platform:** GROWBRIDGE CONNECT  
**Tagline:** "Connect. Engage. Automate. Grow."  

Growbridge Connect features a centralized **Integrations Hub** enabling omnichannel communication, payment processing, AI engines, and telephony.

---

## 1. Meta Integrations (WhatsApp, Instagram, Messenger)

### A. WhatsApp Cloud API (Embedded Signup & Manual)
1. **Meta App Setup:**
   - Create a Business App on [Meta for Developers](https://developers.facebook.com/).
   - Add **WhatsApp** and **Facebook Login for Business** products.
   - Configure OAuth Redirect URI: `https://connect.yourdomain.com/auth/meta/callback`.
2. **Embedded Signup:**
   - In Admin Panel > **System Settings > Meta Integration**, input `Meta App ID`, `Client Secret`, and `Configuration ID`.
   - Clients can connect their WhatsApp Business Numbers via co-branded Facebook popup with zero manual token pasting.
3. **Manual Cloud API Connection:**
   - Direct entry of `WABA ID`, `Phone Number ID`, and `Permanent System User Access Token`.

### B. Facebook Messenger & Instagram Direct
- Uses standard Meta Graph API permissions: `pages_messaging`, `instagram_basic`, `instagram_manage_messages`.
- Inbound Direct Messages, mentions, and story replies route automatically to the **Unified Inbox**.

---

## 2. AI Providers (LLM Gateway)

Growbridge Connect supports bringing your own API keys at either the System (Admin) or Workspace (Tenant) level:

| Provider | Supported Capabilities | Models |
| :--- | :--- | :--- |
| **OpenAI** | Chat Completions, Text Embeddings, Tool Calling | `gpt-4o`, `gpt-4o-mini`, `text-embedding-3-small` |
| **Google Gemini** | Chat Completions, Embeddings | `gemini-1.5-flash`, `gemini-1.5-pro`, `text-embedding-004` |
| **Anthropic Claude** | Conversational Chat | `claude-3-5-sonnet-20241022`, `claude-3-haiku` |

---

## 3. Telephony & AI Voice Providers

Connect telephony gateways for Inbound / Outbound AI Voice Agents:
- **Exotel:** Recommended for India & APAC. Uses `API Key`, `API Token`, and `Account SID`.
- **Twilio:** Recommended for Global coverage. Uses `Account SID`, `Auth Token`, and `Caller ID`.
- **Plivo:** Low-latency SIP/Voice provider. Uses `Auth ID` and `Auth Token`.

---

## 4. SMS Gateways (15+ Providers)

Supports multi-gateway failover:
- **Twilio SMS**, **Plivo**, **Telnyx**, **MessageBird**, **Infobip**
- **Msg91** (DLT Template compliant for India), **Fast2SMS**, **SMSAlert**
- **BulkSMSBD** (Bangladesh), **AfricasTalking** (Africa)

---

## 5. Payment Gateways (14+ Supported)

Configure any of the following gateways with zero code:
- **Stripe**, **PayPal**, **Razorpay**, **Cashfree**, **Paddle**, **Paystack**, **MercadoPago**, **Mollie**, **Iyzico**, **Xendit**, **Tap Payments**, **Paymob**, **Square**, **MyFatoorah**.
