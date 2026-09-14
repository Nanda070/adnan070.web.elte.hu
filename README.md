# adnan070.web.elte.hu

Personal student web space on [ELTE Caesar](https://caesar.elte.hu/) hosting — a small collection of static pages, an interactive rubber-duck PWA with developer tools, and a PHP/MySQL backend for the global quack wall.

**Live site:** [https://adnan070.web.elte.hu/](https://adnan070.web.elte.hu/)

---

## What this is

| Area | Summary |
|------|---------|
| **Main page** | *Random Duck* — tap the rubber duck for procedural quacks, a shared **Quack Wall** guestbook backed by `api.php`, and a **Tools** tab ([it-tools.tech](https://it-tools.tech)-style developer utilities) |
| **Student guides** | Unofficial ELTE-focused reference pages (Neptun client install, IT accounts & portals) |
| **About** | Minimal calling card for the site owner |
| **PWA** | Installable web app shell via `manifest.webmanifest` + `sw.js` |

Visual brand: **charcoal** (`#0a0a0b`) + **scarlet** (`#e11d48`).

**Navigation:** fixed bottom bar with **Tools** and **Wall**; mobile **safe-area** padding; floating guide badges (About, Neptun, ELTE IT) hide while modals are open.

---

## Tools tab

Searchable catalog of in-browser utilities (no server round-trip):

| Tool | Purpose |
|------|---------|
| Markdown → HTML | Preview and copy rendered HTML |
| QR Code generator | Encode text/URLs; download PNG |
| Temperature converter | Celsius, Fahrenheit, Kelvin |
| Percentage calculator | Part/whole and change calculations |
| MAC address lookup | Format and OUI vendor lookup |
| Bcrypt | Hash and compare passwords |
| Password strength analyser | Score and feedback on passphrases |
| Encrypt / decrypt text | Symmetric text crypto in the browser |

---

## Pages & files

| File / path | Purpose |
|-------------|---------|
| `index.html` | Main duck sanctuary — interactive quacker, Tools modal, Quack Wall UI, bottom nav |
| `api.php` | JSON API for global quack counter, wall messages, likes, and rate limiting (MySQL on Caesar; SQLite locally) |
| `neptun-elte.html` | Install guide for **NEPTUN Mobile for ELTE** (unofficial Neptun client) — Windows, macOS, iPhone |
| `elte-it.html` | **ELTE Student IT** reference — where to get accounts, passwords, and service portals (unofficial) |
| `about.html` | About **Nanda (Adnan)** — developer calling card |
| `manifest.webmanifest` | PWA manifest (name, theme, icons, start URL) |
| `sw.js` | Service worker — caches static assets; never caches `api.php` |
| `icon.svg` | Site / PWA icon |
| `downloads/` | Placeholder for future hosted release binaries (see `downloads/README.md`) |

The duck page supports **English**, **Russian**, and **Hungarian** UI strings in-page.

---

## Local preview

### Static pages only

Any static file server works for HTML/CSS/JS pages:

```bash
# Python (from repo root)
python -m http.server 8080
```

Open `http://localhost:8080/index.html`, `neptun-elte.html`, `elte-it.html`, or `about.html`.

Without PHP, the duck still quacks locally and **Tools** work fully in-browser; the **Quack Wall** and global counter will not persist or sync.

### Full stack (quack wall + API)

Requires **PHP 8.x** with PDO (MySQL or SQLite).

```bash
php -S localhost:8080
```

- On **Caesar**, `api.php` uses ELTE's `_CS` helper and MySQL automatically.
- **Locally**, `api.php` falls back to `quack_data.sqlite` in the repo root (created on first request).
- No manual DB setup or credentials needed for local testing.

---

## Deploy to ELTE Caesar

1. Upload site files to your Caesar **`public_html`** directory (SFTP / file manager).
2. **Static pages** (`*.html`, `icon.svg`, `manifest.webmanifest`, `sw.js`) — upload and done. **No database changes required.**
3. **`api.php`** — upload only if you want the live Quack Wall and global counter. Caesar provides MySQL via `_CS`; `api.php` creates its own tables on first run (`quack_stats`, `quack_messages`, `quack_rate_limits`). You do **not** need to run SQL migrations manually.
4. Optional: put release binaries under `downloads/` when ready (see `downloads/README.md`).

Do not commit or document database passwords — Caesar injects credentials at runtime.

---

## Tech stack

| Layer | Details |
|-------|---------|
| Frontend | Vanilla HTML, CSS, JavaScript — no build step |
| Audio | Web Audio API (procedural quack synthesis) |
| Backend | PHP 8.2 (`api.php`) |
| Database | MySQL on Caesar; SQLite for local dev |
| PWA | Web App Manifest + Service Worker |
| Hosting | ELTE Caesar student web space |

---

## Owner & contact

**Adnan (Nanda)** is one of two co-founders and the owner of **Cheterin Group**. This site and related work are part of the Cheterin Group portfolio:

- **Cheterin**
- **Cheterin Lookup**
- **ChetMedia**
- **Astra** (Minecraft plugin series)

*RU: Аднан (Nanda) — один из двух основателей и владелец Cheterin Group; проекты выше — часть экосистемы Cheterin.*

| | |
|---|---|
| **Name** | Adnan / Nanda |
| **Email** | [adnan.huseynli1@gmail.com](mailto:adnan.huseynli1@gmail.com) |
| **Discord** | `nandak070` |
| **Telegram** | `@nanda070` |
| **Riot ID** | `Xaosletao#404` |
| **GitHub** | [github.com/Nanda070](https://github.com/Nanda070) |
| **Card** | [nanda.is-a.dev](https://nanda.is-a.dev/) |

---

## Discord webhook (Quack Wall)

New wall posts can notify a Discord channel via a **server-side only** webhook:

1. On Caesar: `cp webhook.secret.php.example webhook.secret.php`
2. Edit `webhook.secret.php` and paste your Discord Incoming Webhook URL (PHP `return 'https://discord.com/api/webhooks/...';`).
3. Do **not** commit `webhook.secret.php` (listed in `.gitignore`). Never put the URL in HTML/JS/CSS.
4. If the secret file is missing or invalid, wall posts still work — Discord notify fails soft.
5. Rate-limited with the same IP cooldown as wall posts; message text is truncated; the webhook URL is never echoed in API responses.

**Security:** If a webhook URL was ever pasted in chat or committed, regenerate it in Discord (Server Settings → Integrations → Webhooks).

---

## Cookies / local prefs

Pages load `cookie-consent.js` (RU/EN/HU). **Accept** sets `quack_cookie_ok=1` (cookie) + choice in localStorage. **Essential only** hides the banner and keeps language (`quack_lang`) in localStorage — no ad trackers, no data selling.

## Custom 404

`404.html` is a branded not-found page (RU/EN/HU). Root `.htaccess` sets:

```apache
ErrorDocument 404 /404.html
```

On Caesar this usually works if the site lives at the web root of your `public_html`. If your space is under a subdirectory, adjust the path accordingly.

---

## Disclaimers

- **NEPTUN Mobile for ELTE** (`neptun-elte.html`) is an **unofficial** third-party client. Not affiliated with ELTE or the official Neptun system. Use at your own discretion; verify information against official ELTE sources.
- **ELTE Student IT** (`elte-it.html`) is an **unofficial** student-maintained reference. Portals, policies, and URLs may change — always confirm with [ELTE IT](https://it.elte.hu/) and official documentation.
- **Random Duck** and the quack wall are a personal side project on student hosting — no warranty, no uptime guarantee.

---

## License

No license file is included yet. Guides and site content are authored by Nanda (Adnan). Third-party trademarks (ELTE, Neptun, etc.) belong to their respective owners.

---

*README for the `adnan070.web.elte.hu` Caesar student site.*
