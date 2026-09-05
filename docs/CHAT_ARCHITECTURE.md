# ASTROPOP Chat Architecture

ASTROPOP has two distinct chat products:

1. **AI Astrology Chat** — powered by OpenAI through a server-side provider adapter.
2. **Human Advisor Chat** — an ASTROPOP-owned real-time chat and billing system.

They share the same application chat model, wallet/coin ledger, conversation history and UI primitives, but their providers and billing rules remain separate.

## 1. AI Astrology Chat

OpenAI is the conversational intelligence provider. VedicAstroAPI remains the astrology calculation/source-data provider for Kundli, planetary positions, Dasha and related deterministic astrology services. ASTROPOP does not ask the LLM to calculate missing astronomical data when authoritative chart data is already available.

The OpenAI request is made **server-side only**. The browser must never receive the OpenAI API key.

### AI request flow

```text
User message
   -> create/load ASTROPOP AI thread
   -> load saved Kundli from MySQL
   -> build bounded AstrologyChatContext
   -> include required conversation history
   -> call OpenAI Responses API
   -> persist provider response + token usage metadata
   -> persist assistant message
   -> debit ASTROPOP coins after successful response
```

The astrology calculation API is not called for every AI message. The AI context comes from the saved Kundli calculation and local deterministic engines.

### Provider boundary

`AiProviderInterface` isolates the application from the LLM provider. `OpenAIChatProvider` currently implements the interface using the OpenAI Responses API over server-side cURL. A future provider can be added without changing the chat UI, wallet, or astrology context layer.

### AI cost controls

ASTROPOP customer billing is deliberately separate from OpenAI token billing:

- `ai_chat_usage.input_tokens` records OpenAI input tokens when returned.
- `ai_chat_usage.output_tokens` records OpenAI output tokens when returned.
- `ai_chat_usage.user_coins_charged` records ASTROPOP's customer price.
- `wallet_ledger` records the actual customer coin debit.
- The current MVP price is configurable through `AI_CHAT_COINS_PER_MESSAGE` and defaults to **1 ASTRO_COIN per successful response**.
- OpenAI provider failures do not count as successful customer usage.
- Provider response IDs and model metadata are retained for reconciliation.

The OpenAI model is configured through `OPENAI_MODEL`; the current example defaults to `gpt-5.6-luna`.

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

Conversation history is persisted by ASTROPOP so users can see their history and administrators can audit usage. The LLM provider receives only the server-constructed context and conversation history required for the request.

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
