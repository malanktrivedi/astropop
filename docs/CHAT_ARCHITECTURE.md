# ASTROPOP Chat Architecture

ASTROPOP has two distinct chat products:

1. **AI Astrology Chat** — powered by OpenAI through a server-side provider adapter.
2. **Human Advisor Chat** — an ASTROPOP-owned real-time chat and billing system.

They share the same application chat model, wallet/coin ledger, conversation history and UI primitives, but their providers and billing rules remain separate.

## AI Astrology Chat

OpenAI is the conversational intelligence provider. VedicAstroAPI remains the astrology calculation/source-data provider for Kundli, planetary positions, Dasha and related deterministic astrology services.

The OpenAI request is made server-side only. The browser never receives the OpenAI API key.

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

`AiProviderInterface` isolates the application from the LLM provider. `OpenAIChatProvider` implements the interface using the OpenAI Responses API over server-side cURL. A future provider can be added without changing the chat UI, wallet, or astrology context layer.

### AI cost controls

- `ai_chat_usage.input_tokens` records OpenAI input tokens when returned.
- `ai_chat_usage.output_tokens` records OpenAI output tokens when returned.
- `ai_chat_usage.user_coins_charged` records ASTROPOP's customer price.
- `wallet_ledger` records the customer coin debit.
- `AI_CHAT_COINS_PER_MESSAGE` configures the customer price and defaults to 1 ASTRO_COIN per successful response.
- Provider failures do not count as successful customer usage.
- Provider response IDs and model metadata are retained for reconciliation.

The OpenAI model is configured through `OPENAI_MODEL` and the example defaults to `gpt-5.6-luna`.

## Human Advisor Chat

Human chat is entirely owned by ASTROPOP. The first implementation should use normal PHP endpoints plus short polling for message delivery. A WebSocket/SSE layer can be introduced later without changing the database model.

### Billing model

The schema supports per-minute billing, per-message billing, configurable advisor rates, minimum billable units, immutable usage records, atomic wallet debits and wallet ledger snapshots. The initial product recommendation is per-minute billing for human chat.

Never calculate the final charge only in JavaScript. The server owns billing timestamps, rate lookup and wallet debits.

## Coins and payments

`ASTRO_COIN` is an internal application billing unit. Payment provider webhooks, not the browser redirect alone, must be treated as the source of truth for successful payment settlement.

## Data model

- `wallet_accounts`
- `wallet_ledger`
- `coin_packages`
- `payment_orders`
- `advisor_profiles`
- `advisor_rates`
- `chat_threads`
- `chat_messages`
- `chat_usage`
- `ai_chat_usage`
- `birth_profiles`
- `kundli_calculations`

The astrology source data and chat application data remain separate. A chat thread may reference a birth profile and a specific saved Kundli calculation for reproducibility, but chat messages do not require a fresh astrology calculation.

## Chat context and privacy

The AI context builder passes only the minimum astrology facts needed for the question. Conversation history is persisted by ASTROPOP for user history and usage audit. The LLM provider receives only the server-constructed context and conversation history required for the request.
