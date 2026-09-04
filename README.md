# ASTROPOP

Chat-first Vedic astrology web application.

## Deliverable 1

Foundation for a PHP 8+ application using MySQL, MySQLi, PHP sessions, cURL, HTML5, CSS3 and JavaScript.

The architecture follows the ASTROPOP PDR: secure authentication, birth profiles, server-side VedicAstroAPI integration, normalized astrology data, and a conversational product direction.

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

## Local setup

1. Install XAMPP with PHP 8+ and MySQL.
2. Clone this repository into `C:\xampp\htdocs\astropop`.
3. Create a MySQL database named `astropop`.
4. Import `database/schema.sql`.
5. Copy `.env.example` to `.env` or configure environment variables in Apache/PHP.
6. Set `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, and `VEDIC_API_KEY`.
7. Start Apache and MySQL in XAMPP.
8. Open `http://localhost/astropop/public/`.

## Hostinger

Point the website document root to the application's `public/` directory when possible. Keep `config/` and `includes/` outside the public document root where the hosting layout permits. Configure the required environment/server variables and import the MySQL schema through Hostinger's database tools.

## VedicAstroAPI

The application communicates with VedicAstroAPI from PHP using cURL. The API key is server-side only. Exact endpoint contracts must be verified against the supplied VedicAstroAPI MCP documentation before feature-specific API calls are added.

## Development order

1. Foundation
2. Birth profile and location
3. Kundli and normalized chart data
4. Dashboard/readings
5. Matching
6. AI chat
7. Monetization
8. Administration
