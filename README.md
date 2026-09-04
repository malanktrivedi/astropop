# ASTROPOP

Chat-first Vedic astrology web application built with plain PHP.

## Current milestone — Astrology Core Foundation

ASTROPOP currently provides the secure PHP/MySQLi foundation plus the first real astrology-engine workflow:

- User registration/login/logout
- Secure PHP sessions and CSRF protection
- Birth-profile storage
- VedicAstroAPI server-side cURL client
- Documented geo-search integration
- Birth-location selection with latitude/longitude/timezone offset
- Documented planet-details integration
- Documented ascendant-report integration
- Kundli API response caching in MySQL
- Basic dashboard status

The application does **not** invent astrology values. High-level Lagna/Rashi/Nakshatra presentation will be normalized only after the live response contract is tested against the configured API account.

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
6. If upgrading an existing Deliverable 1 database, run `database/migrations/001_astrology_core.sql`.
7. Copy `.env.example` to `.env`.
8. Configure `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, and `VEDIC_API_KEY`.
9. Enable PHP cURL in XAMPP's `php.ini` if it is disabled.
10. Start Apache and MySQL.
11. Open `http://localhost/astropop/public/`.

## Hostinger deployment

Use the same Git repository. Prefer a document root pointing to `public/`, so `config/`, `includes/`, and `database/` are not directly web-accessible.

Create the production database in Hostinger, import `database/schema.sql`, configure the production environment values, enable PHP cURL, and test `/health.php` followed by the authenticated API test.

Do not commit `.env` or the VedicAstroAPI key.

## Verified VedicAstroAPI routes

The supplied VedicAstroAPI documentation MCP was used to verify these contracts before implementation:

- `GET /v3-json/utilities/geo-search` — city search; documented `city` and `lang` parameters; responses expose location names, coordinates and timezone information.
- `GET /v3-json/horoscope/planet-details` — documented birth inputs `dob`, `tob`, `lat`, `lon`, `tz`, `lang`; response documentation covers planetary degree/rashi/zodiac/house/nakshatra details and dasha-related information.
- `GET /v3-json/horoscope/ascendant-report` — documented birth inputs `dob`, `tob`, `lat`, `lon`, `tz`, `lang`; response documentation covers ascendant summary data.

The production PHP application calls these routes directly through cURL. The MCP server is only a development/documentation resource.

## Development order

1. Foundation
2. Birth profile and location
3. Kundli and normalized chart data
4. Dashboard/readings
5. Matching
6. AI chat
7. Monetization
8. Administration
