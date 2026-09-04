# GROWBRIDGE CONNECT — AI VOICE AGENTS & TELEPHONY GUIDE

**Headline:** "AI Voice Agents Built To Resolve Customer Queries"  
**Platform:** GROWBRIDGE CONNECT  
**Tagline:** "Connect. Engage. Automate. Grow."  

---

## 1. Overview & Capabilities

GROWBRIDGE CONNECT provides a native **AI Voice Agents & Telephony** module designed for seamless deployment on standard hosting environments (cPanel/Apache/PHP 8.2+) using an asynchronous telephony provider abstraction.

### Key Capabilities:
- **Inbound & Outbound Calling:** Handle incoming customer calls 24/7 or trigger automated outbound follow-up calls.
- **Multi-Language & Voice Support:** Conversational support in **English (US/UK/IN)**, **Hindi**, and **Hinglish** (natural Indian bilingual blend).
- **FAQ Resolution:** Instant query resolution powered by linked RAG Knowledge Bases.
- **Lead Qualification & CRM Sync:** Automatically scores leads, logs call summaries, and updates contact activity timelines.
- **Live Human Escalation:** Instant call forwarding to human agents if customer requests or sentiment dictates.
- **Full Call Records:** Automated call duration tracking, audio recordings, and AI-generated call summaries.
- **Multi-Provider Telephony Hub:** First-class support for **Heyo Phone**, **Exotel**, **Twilio**, and **Plivo**.

---

## 2. Telephony Provider Configuration

### A. Heyo Phone (First-Class Integration for Smart Business Calling)
Heyo Phone delivers streamlined business telephony and virtual numbers for Indian SMBs and enterprises.

1. Navigate to **Settings > Integrations > Heyo Phone** (or `/app/integrations/heyo`).
2. Input your credentials:
   - **API Key / Token:** Your Heyo Phone API authentication key.
   - **Account ID / Company ID:** Your Heyo/MyOperator company identifier.
   - **Virtual Number / Caller ID:** Your assigned virtual phone number (e.g. `+919876543210`).
   - **Webhook Secret:** Secret token for authenticating webhook callbacks.
   - **API Base URL:** Default gateway `https://api.myoperator.co/v1`.
3. Click **[Test Connection]** to verify authentication.
4. Set your Webhook URL in the Heyo / MyOperator panel under **Webhooks v2**:
   `https://connect.yourdomain.com/webhooks/voice/heyo/{call_uuid}`

### B. Exotel (India & APAC Enterprise CPaaS)
1. Configure in System Integrations or `.env`:
   ```env
   EXOTEL_KEY="your_api_key"
   EXOTEL_TOKEN="your_api_token"
   EXOTEL_SID="your_account_sid"
   EXOTEL_CALLER_ID="your_exophone_number"
   ```
2. Webhook Callback: `https://connect.yourdomain.com/webhooks/voice/exotel/{call_uuid}`

### C. Twilio (Global Telephony)
1. Configure:
   ```env
   TWILIO_SID="your_account_sid"
   TWILIO_TOKEN="your_auth_token"
   TWILIO_FROM="+1234567890"
   ```
2. Webhook Callback: `https://connect.yourdomain.com/webhooks/voice/twilio/{call_uuid}`

### D. Plivo (Global SIP & Voice)
1. Configure:
   ```env
   PLIVO_AUTH_ID="your_auth_id"
   PLIVO_AUTH_TOKEN="your_auth_token"
   PLIVO_FROM="+1234567890"
   ```

---

## 3. Phone Numbers Management

Navigate to **Voice Agents > Phone Numbers** (`/app/voice/numbers`):
- Add multiple virtual numbers across providers.
- Assign dedicated AI Voice Agents to each number for automatic inbound routing.
- Set default outbound caller IDs.
- Toggle number active/inactive status.

---

## 4. Creating & Configuring a Voice Agent

1. Navigate to **Voice Agents** (`/app/voice`).
2. Click **[ + Create Voice Agent ]**.
3. Configure the tabs:
   - **General:** Agent Name, Provider (**Heyo Phone**, Exotel, Twilio, Plivo), Virtual Caller ID.
   - **Voice & Language:** Select Language (English / Hindi / Hinglish), Tone (Professional / Friendly / Empathetic), Voice Persona (Aditi, Raveena, Kajal, Joanna, Matthew).
   - **AI Instructions:** Set the Greeting Message and detailed System Prompt.
   - **Knowledge Base:** Link a pre-indexed FAQ Knowledge Base for RAG.
   - **Human Escalation:** Enter the human transfer phone number.
4. Click **Create Agent**.

---

## 5. Testing & Automated Workflows

### Live Test Call
- On the **Voice Agents** dashboard, click **Test Call** on any agent card.
- Enter your phone number in E.164 format (e.g. `+919876543210`).
- Growbridge Connect will dial your phone immediately to test the conversational flow.

### Outbound Automated Call Workflows
- In **Automation Builder**, add the **Trigger AI Call** node into any workflow (e.g. When lead score > 80 -> Wait 5 minutes -> Trigger Heyo Phone AI Voice Call).

### Reviewing Call Transcripts & Summaries
- Navigate to **Voice Agents > Call History** to listen to call recordings, read complete transcripts, and view AI-extracted customer outcomes.
