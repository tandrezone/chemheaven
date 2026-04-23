# ChemHeaven

A privacy-first, security-hardened e-commerce storefront built with vanilla PHP and MySQL. ChemHeaven powers an online shop with product browsing, variant selection, session-based cart management, checkout via OxoPay, and a full admin panel for managing products, categories, tags, vendors, and orders.

## Features

- **Product catalogue** — browse, search, and filter products by category with weight-variant selection
- **Session-based cart** — add/update/remove items without requiring user accounts
- **OxoPay checkout** — payment integration with callback verification (sandbox & production modes)
- **Admin panel** — manage products, categories, vendors, tags, orders, and admin users; CSV import/export
- **Security hardened** — CSRF protection, prepared statements (no raw SQL), Content-Security-Policy, HSTS, secure session handling, rate-limited admin login, safe redirects, and HTML output escaping
- **Privacy-first** — no external scripts or trackers; assets served from the same origin

## Project Structure

```
chemheaven/
├── config/             # Application & database configuration
│   ├── config.php      # Central settings (DB, OxoPay, sessions, etc.)
│   └── database.php    # PDO singleton connection
├── database/           # SQL files
│   ├── schema.sql      # Full database schema
│   └── seed.sql        # Sample seed data
├── includes/           # Shared PHP includes
│   ├── functions.php   # Helpers (session, CSRF, cart, products, security headers)
│   ├── header.php      # Public page header
│   ├── footer.php      # Public page footer
│   ├── admin-auth.php  # Admin authentication guard
│   ├── admin-header.php
│   └── admin-footer.php
├── public/             # Document root served by Apache
│   ├── index.php       # Shop home — product grid with search & category filter
│   ├── product.php     # Single product detail page
│   ├── cart.php         # View & manage the shopping cart
│   ├── cart-action.php  # Cart add/update/remove handler
│   ├── checkout.php     # Checkout & payment initiation
│   ├── payment-callback.php  # OxoPay server-to-server callback
│   ├── payment-return.php    # Post-payment customer redirect
│   ├── admin/          # Admin panel pages
│   └── assets/         # CSS, images, and static assets
├── tests/              # Lightweight test suite
│   ├── run.php         # Test runner
│   └── HelpersTest.php # Unit tests for helper/cart functions
├── .htaccess           # Root Apache config (rewrites, security headers, HTTPS)
└── .gitignore
```

## Requirements

- **PHP** 8.1 or later
- **MySQL** 5.7+ or **MariaDB** 10.3+
- **Apache** with `mod_rewrite` and `mod_headers` enabled

## Getting Started

### 1. Clone the repository

```bash
git clone https://github.com/tandrezone/chemheaven.git
cd chemheaven
```

### 2. Create the database

```bash
mysql -u root -p < database/schema.sql
mysql -u root -p < database/seed.sql   # optional sample data
```

### 3. Configure the application

Copy the default config and fill in your local credentials:

```bash
cp config/config.php config/config.local.php
```

Edit `config/config.local.php` to set your database credentials, OxoPay keys, and `APP_URL`. Alternatively, set them as environment variables (`DB_HOST`, `DB_USER`, `DB_PASS`, etc.).

> **Note:** `config/config.local.php` is git-ignored and must never be committed.

### 4. Point Apache at the project

Set your Apache virtual host's `DocumentRoot` to the repository root. The root `.htaccess` automatically rewrites all traffic into the `public/` directory and enforces HTTPS for non-localhost hosts.

### 5. Open the site

Visit `http://localhost` (or your configured `APP_URL`) to browse the shop.

## Admin Panel

Access the admin area at `/admin/login.php`. Admin credentials are stored in the `admin_users` table with bcrypt-hashed passwords. The admin panel provides:

- Product CRUD with variant management
- Category, vendor, and tag management
- Order viewing
- CSV import/export for bulk product operations
- Admin user management

Login is rate-limited to prevent brute-force attacks (lockout after 5 failed attempts for 15 minutes).

## Tests

Run the lightweight helper and cart unit tests:

```bash
php tests/run.php
```

## Security

ChemHeaven applies defence-in-depth at every layer:

| Layer | Measure |
|-------|---------|
| SQL | Real prepared statements (`PDO::ATTR_EMULATE_PREPARES = false`) |
| XSS | All dynamic output escaped via `h()` (`htmlspecialchars`) |
| CSRF | Per-session token verified on every state-changing POST |
| Sessions | `HttpOnly`, `SameSite=Lax`, `Secure` (over HTTPS), strict mode, periodic ID rotation |
| Headers | CSP, HSTS, `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy` |
| Apache | Directory listing disabled, dot-file access blocked, sensitive directories denied, ETags removed |
| Admin | Bcrypt password hashing, rate-limited login, separate session namespace |

## License

This project is proprietary. All rights reserved.
