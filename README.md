# ASTROPOP

Chat-first Vedic astrology web application built with plain PHP.

## Current milestone — Astrology Core + Chat Foundation

ASTROPOP provides the secure PHP/MySQLi foundation plus the first real astrology-engine and chat-commerce architecture:

- User registration/login/logout
- Secure PHP sessions and CSRF protection
- Birth-profile storage
- VedicAstroAPI server-side cURL client
- Documented geo-search integration
- Birth-location selection with latitude/longitude/timezone offset
- Documented planet-details integration
- Documented ascendant-report integration
- Kundli source-data caching in MySQL
- Database-first Kundli, Yoga and Planetary Analysis pages
- Explicit Kundli recalculation instead of API calls on report views
- Canonical astrology context builder for future AI chat
- AI chat usage accounting foundation
- Human advisor chat threads/messages foundation
- ASTRO_COIN wallet and auditable wallet ledger
- Coin packages and payment-order foundation
- Advisor profiles and configurable per-minute/per-message rates

## Technology constraints

- PHP 8+
- MySQL
- MySQLi only
- PHP sessions
- PHP cURL
- HTML5/CSS3/JavaScript
- No Laravel
- No WordPress
- No PDO
- No PHP framework
- No CMS

## Local XAMPP setup

1. Install XAMPP with PHP 8+ and MySQL.
2. Clone the repository into `C:\xampp\htdocs\astropop`.
3. Checkout `develop` for active development.
4. Create a MySQL database named `astropop`.
5. Import `database/schema.sql`.
6. If upgrading an existing database, run migrations in order from `database/migrations/`.
7. Copy `.env.example` to `.env`.
8. Configure `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, and `VEDIC_API_KEY`.
9. Enable PHP cURL in XAMPP's `php.ini` if it is disabled.
10. Start Apache and MySQL.
11. Open `http://localhost/astropop/public/`.

## Hostinger deployment

Use the same Git repository. Prefer a document root pointing to `public/`, so `config/`, `includes/`, and `database/` are not directly web-accessible.

Create the production database in Hostinger, import `database/schema.sql`, run pending migrations, configure the production environment values, enable PHP cURL, and test `/health.php` followed by the authenticated API test.

Do not commit `.env` or the VedicAstroAPI key.

## Verified VedicAstroAPI routes

The supplied VedicAstroAPI documentation MCP was used to verify these contracts before implementation:

- `GET /v3-json/utilities/geo-search` — city search.
- `GET /v3-json/horoscope/planet-details` — documented birth inputs and planetary details.
- `GET /v3-json/horoscope/ascendant-report` — documented birth inputs and ascendant data.
- `GET /v3-json/dashas/maha-dasha` — Maha Dasha timeline.

The production PHP application calls these routes directly through cURL only during controlled source-data acquisition. Report pages reuse the stored result.

## Chat architecture

ASTROPOP has two separate chat products:

### AI Astrology Chat

Use the VedicAstroAPI AI Chat API as the external astrology-aware response provider. The browser never receives the API key. AI requests are made server-side and receive a bounded astrology context generated from the saved Kundli rather than triggering a new Kundli calculation for every message.

The exact AI Chat request/response fields must follow the supplied Postman documentation; undocumented fields must not be invented.

### Human Advisor Chat

ASTROPOP owns the human chat system. Users purchase ASTRO_COIN packages and spend coins on advisor conversations. The current database foundation supports:

- advisor profiles and approval status
- per-minute or per-message pricing
- chat threads and messages
- usage records
- atomic wallet debits
- payment orders
- auditable wallet ledger

The initial human-chat transport can use PHP endpoints with short polling. A real-time WebSocket/SSE layer can be introduced later without changing the billing model.

See `docs/CHAT_ARCHITECTURE.md` for the full design.

## Development order

1. Foundation
2. Birth profile and location
3. Canonical Kundli/source-data storage
4. Deterministic astrology engines
5. AI chat integration + chat UI
6. Human advisor chat + coin billing
7. Payment checkout/webhooks
8. Dasha intelligence and topic interpretation
9. UI redesign and client experience
10. Administration, advisor operations and marketplace features
