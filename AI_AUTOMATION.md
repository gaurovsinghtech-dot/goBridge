# GROWBRIDGE CONNECT — AI AUTOMATION & CUSTOMER JOURNEY HANDBOOK

**Platform:** GROWBRIDGE CONNECT  
**Tagline:** "Connect. Engage. Automate. Grow."  

---

## 1. AI Architecture Overview

GROWBRIDGE CONNECT provides a unified AI & Omnichannel Customer Journey subsystem featuring:
1. **Unified Customer Journey & Timeline:** ONE Contact Record → ONE Conversation History → MULTIPLE Channels (WhatsApp, Instagram, Messenger, Email, SMS, Heyo Phone) → ONE AI Brain.
2. **RAG Knowledge Bases:** Upload PDFs and text documents; auto-chunked and embedded for accurate conversational retrieval.
3. **AI Chatbots & AI Voice Agents:** Autonomous multi-channel conversational agents with natural bilingual (English/Hindi/Hinglish) voice support.
4. **Smart Automation Engine:** Multi-step workflows with channel switching, intelligent follow-up safety checks, lead scoring, and loop prevention.

---

## 2. Omnichannel Customer Journey & Timeline

Every customer interaction is unified into a chronological timeline:
- **WhatsApp / Instagram / Messenger Messages:** Inbound queries and AI answers.
- **AI Telephony Calls:** Inbound & Outbound calls via **Heyo Phone**, **Exotel**, **Twilio**, or **Plivo** with audio playback, transcripts, and LLM summaries.
- **Lead Transitions & Scores:** Dynamic scoring updates based on conversation signals.
- **Smart Follow-Ups & Human Handoffs:** Timeline audit trail with instant operator visibility.

---

## 3. Dynamic Lead Scoring (0–100)

Growbridge Connect classifies leads into 4 dynamic tiers:
- **0–30:** Cold
- **31–60:** Warm
- **61–80:** Hot
- **81–100:** Very Hot

### Scoring Modifiers:
- **Intent Signals:** Demo requests (`+30`), Pricing inquiries (`+20`), Consultations (`+20`).
- **Engagement:** `+5` per inbound response.
- **AI Voice Call Outcome:** Qualified / Demo Booked (`+35`), Support Resolved (`+15`), Not Interested (`-25`).
- **Commercial Intent:** Budget or high-value purchase mention (`+25`).
- **Negative Sentiment:** Complaints or frustration (`-20`).

---

## 4. Smart Follow-Up & Opt-Out Safety

### Smart Follow-Up Engine
Before waking from a delayed wait node (`wait 1 day`, `wait 3 days`), the engine checks:
1. Did the customer reply in the meantime?
2. Has the conversation been resolved or handed over to a human?
3. Has the lead converted / won?
4. Did the customer opt out?
*If any condition is true, the automation halts automatically to prevent spamming.*

### Context-Aware AI Follow-Up
Instead of static template spam, the `ai_followup` node reads prior turns and generates a personalized, respectful check-in referencing specific topics discussed.

### Customer Opt-Out Compliance
Detects `STOP`, `UNSUBSCRIBE`, `DO NOT CALL`, `NO MORE MESSAGES`, `REMOVE ME`, `CANCEL`:
- Sets `marketing_opt_out = true`.
- Automatically cancels all active automation runs for that contact.
- Records channel and opt-out timestamp in the contact timeline.

---

## 5. Human Handoff Engine

Automations and AI agents gracefully transition to human operators when:
- Customer requests an agent (*"talk to human"*, *"agent"*, *"help"*).
- Low AI confidence or complex complaint detected.
- Lead score crosses high-value threshold (`>= 80`).
- Conversation status updates to `open`, `assigned_to = 'human'`, and alerts assigned team (Sales / Support / Manager).

---

## 6. Pre-Built Journey Templates (1-Click Install)

Navigate to **Automation > Templates** (`/app/automations/templates`):
1. **New Lead Omnichannel Follow-up:** Instant WhatsApp greeting -> AI qualification -> Heyo Phone AI Call.
2. **WhatsApp Lead Qualification & CRM Scoring:** Autonomous qualification with budget extraction.
3. **Missed Call Instant WhatsApp Recovery:** Converts missed inbound phone calls to active WhatsApp chats.
4. **AI Autonomous Sales & Demo Booking:** Prospect qualification with calendar booking.
5. **24/7 Support with Smart Escalation:** 80% automated resolution with instant human handoff.
6. **Multi-Channel Appointment Reminder:** 24h and 1h reminders with interactive confirmation buttons.
7. **High-Value Quote Follow-up:** Multi-day smart follow-up with contextual check-ins.
8. **Abandoned Lead Re-Engagement:** Re-engages inactive prospects after 7 days.
9. **Post-Purchase CSAT Feedback:** Instant CSAT rating and customer feedback collection.
10. **AI Voice Agent Outbound Follow-up:** Dials prospects, creates AI summary, sends WhatsApp recap.
