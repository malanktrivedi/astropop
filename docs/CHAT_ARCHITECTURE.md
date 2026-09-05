# ASTROPOP Chat Architecture

ASTROPOP has two distinct chat products:

1. **AI Astrology Chat** — powered by the VedicAstroAPI AI Chat API.
2. **Human Advisor Chat** — an ASTROPOP-owned real-time chat and billing system.

They share the same application chat model, wallet/coin ledger, conversation history and UI primitives, but their providers and billing rules remain separate.

## 1. AI Astrology Chat

The VedicAstroAPI documentation supplied for the project is the integration source of truth for the AI Chat request/response contract. The public AI Chat product documentation confirms a single POST-style integration, contextual multi-turn conversation support, custom instructions, structured JSON responses, multilingual operation and provider-side usage credits.

The external AI request must be made **server-side only**. The browser must never receive the VedicAstroAPI key.

### AI request flow

```text
User message
   -> create/load ASTROPOP AI thread
   -> load saved Kundli from MySQL
   -> build bounded AstrologyChatContext
   -> include only required conversation history
   -> call VedicAstroAPI AI Chat endpoint
   -> persist provider response + usage metadata
   -> persist assistant message
   -> optionally debit ASTROPOP coins
```

The astrology calculation API is not called for every AI message. The AI context comes from the saved Kundli calculation and local deterministic engines.

### AI cost controls

VedicAstroAPI currently describes AI Chat as a credit-metered service and publishes different provider-credit ranges for managed and BYOLLM modes. ASTROPOP must not hard-code those external costs into the user wallet. Instead:

- `ai_chat_usage.provider_credits` records the actual provider-side usage when returned/known.
- `ai_chat_usage.user_coins_charged` records ASTROPOP's customer price.
- Pricing can be changed without changing the astrology engine.
- Failed provider calls must not be treated as successful customer usage.
- Provider request IDs and response metadata should be retained for reconciliation where available.

The exact AI Chat endpoint path and request fields must be implemented from the supplied Postman documentation contract; do not invent undocumented fields.

## 2. Human Advisor Chat

Human chat is entirely owned by ASTROPOP.

### Core flow

```text
User
  -> select advisor
  -> see current coin rate
  -> start human chat
  -> reserve/check wallet balance
  -> chat messages
  -> usage meter
  -> periodic atomic coin debit
  -> advisor payout/accounting later
  -> close session
```

The first implementation should use normal PHP endpoints plus short polling for message delivery. A WebSocket/SSE layer can be introduced later without changing the database model.

### Billing model

The schema supports:

- per-minute billing
- per-message billing
- configurable advisor rates
- minimum billable units
- immutable usage records
- atomic wallet debits
- wallet ledger and balance-after snapshots

The initial product recommendation is **per-minute billing** for human chat because it is easier for customers and advisors to understand. The rate is stored per advisor and can be changed by administration without changing application code.

Never calculate the final charge only in JavaScript. The server owns billing timestamps, rate lookup and wallet debits.

## 3. Coins and payments

`ASTRO_COIN` is an internal application billing unit.

Users purchase coin packages using the selected payment provider. A successful payment creates a wallet credit and an auditable ledger entry. Payment provider webhooks, not the browser redirect alone, must be treated as the source of truth for successful payment settlement.

The payment layer is intentionally separated from chat so Razorpay/Cashfree/another provider can be changed without rewriting chat.

## 4. Wallet rules

All balance-changing operations go through the server-side wallet ledger.

Required invariants:

- Never allow negative balance.
- Lock the wallet row while debiting.
- Write the ledger entry in the same database transaction as the balance update.
- Store the balance after every ledger entry.
- Store a reference to the payment, AI request, or human usage event.
- Support reversal/refund entries rather than deleting financial history.

## 5. Data model

### Application wallet

- `wallet_accounts`
- `wallet_ledger`
- `coin_packages`
- `payment_orders`

### Human advisors

- `advisor_profiles`
- `advisor_rates`

### Conversations

- `chat_threads`
- `chat_messages`
- `chat_usage`
- `ai_chat_usage`

### Astrology source

- `birth_profiles`
- `kundli_calculations`

The astrology source data and chat application data remain separate. A chat thread may reference a birth profile and a specific saved Kundli calculation for reproducibility, but chat messages must not require a fresh astrology calculation.

## 6. Chat context and privacy

The AI context builder should pass only the minimum astrology facts needed for the question. It should not blindly serialize the complete database record into every prompt.

Conversation history is persisted by ASTROPOP so users can see their history and administrators can audit usage. Provider-specific storage policies must also be respected; VedicAstroAPI's published privacy material says its AI Chat does not persist user chat messages/full conversation history as a persistent record.

## 7. Future advisor marketplace

Human chat can later expand into:

- advisor verification/KYC
- online/offline presence
- advisor schedules
- queueing
- ratings/reviews
- refunds/disputes
- advisor earnings ledger
- commissions
- payouts
- admin moderation
- voice/video consultation

Those features should build on `advisor_profiles`, `chat_threads`, `chat_usage` and the wallet ledger rather than creating a second billing system.
