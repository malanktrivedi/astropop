# ASTROPOP Architecture

## Core principle

VedicAstroAPI is a source-data calculator, not a runtime dependency for every screen. Paid/source API calls happen at controlled ingestion points. Canonical astrology data is persisted in MySQL and reused by all deterministic engines and report pages.

## Data flow

```text
Birth Profile
    -> source calculation (VedicAstroAPI)
    -> normalization/validation
    -> kundli_calculations (canonical D1 + Ascendant + houses + Dasha + raw audit payload)
    -> local deterministic engines
       - Navamsa / Vargas
       - planetary analysis
       - yoga detection
       - dasha intelligence
       - topic analysis
    -> chat context
    -> UI/report/chat responses
```

## API call policy

Allowed source calls:

1. Birth-place resolution when the user creates/changes a location.
2. Explicit Kundli generation when no valid source calculation exists.
3. Explicit Kundli recalculation requested by the user.
4. A future background refresh only when a product requirement explicitly needs it.

Report pages must not call the astrology API:

- Kundli
- Yoga Report
- Planetary Analysis
- D1/D9/Varga charts
- Dasha intelligence
- Topic reports
- Chat context retrieval

## Cache identity

`kundli_calculations.calculation_hash` identifies the source chart using birth profile ID, date, time, coordinates, timezone and API version. Local engine version is deliberately excluded. A local algorithm/UI update must never create a paid API request merely because the application version changed.

## Canonical vs derived data

### Canonical source data

Stored in `kundli_calculations`:

- Lagna
- Rashi
- Nakshatra
- Planetary positions and metadata
- House occupancy
- Ascendant/chart payload
- Vimshottari Maha Dasha
- Raw API response for audit/debugging
- Calculation hash and source/version metadata

### Derived data

Calculated locally from canonical data:

- D9/Navamsa
- Divisional charts
- House lordships
- Aspects
- Dignity/context
- Vargottama
- Yoga formation
- Dasha activation
- Topic-specific interpretation

Derived data may later be cached separately if profiling shows a measurable performance benefit, but it must never require a new source API call.

## Chat architecture direction

Chat should consume a compact, structured astrology context generated from the saved calculation rather than fetching the API for each message.

A future chat context builder should assemble only the facts required for the user's question, for example:

```text
User question
    -> intent/topic detection
    -> load canonical calculation from MySQL
    -> run relevant local calculators
    -> build bounded astrology context
    -> response generation
    -> save conversation + message + context/version metadata
```

Conversation history is application data and should be persisted independently from astrology source data. This allows the chat UI, history, summaries, usage controls and AI provider to evolve without coupling them to VedicAstroAPI.

## UI architecture direction

The UI should consume application services/repositories rather than calling external providers directly. Pages should be thin presentation layers. Business rules belong in `includes/` services/calculators, and source data access belongs in repositories.

This separation lets ASTROPOP expand beyond Kundli into chat, topic reports, user history, subscriptions/usage controls and other products without multiplying API calls.
