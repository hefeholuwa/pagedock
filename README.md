# ⚓ PageDock

> **Host your HTML. Get a link.**  
> Instant, lightweight static web hosting, live in-browser editor, and zero-backend form capture. Built with pure PHP 8.5, SQLite, and vanilla modern web tech. Zero heavy dependencies.

---

## ✨ Features

- **⚡ Instant Static Hosting**: Upload single `.html` files or `.zip` multi-page websites. Extracts and hosts automatically with secure HTTPS links.
- **📝 Paste & Publish**: Paste raw HTML into the browser or terminal and get a live link immediately.
- **🛠️ In-Browser Split Editor**: Real-time code editor with responsive desktop, tablet, and mobile `iframe` previews.
- **📬 Zero-Backend Forms**: Point any HTML `<form action="/api/forms/YOUR-SLUG" method="POST">` to collect messages and feedback directly in your dashboard.
- **👤 User Accounts & Guest Deploys**: Deploy anonymously or sign up to claim and manage all your sites under one dashboard.
- **🚀 Zero Bloat**: No 500MB `node_modules`, no build steps, runs out of the box with PHP's built-in server or any standard web server.

---

## 🚀 Quick Start

### 1. Run with PHP Built-in Server
```bash
cd pagedock
php -S localhost:8080 -t public/
```
Open your browser at `http://localhost:8080`.

### 2. Deploy via Terminal / cURL
```bash
# Upload a single HTML file:
curl -X POST -F "file=@index.html" http://localhost:8080/api/upload

# Upload a ZIP website archive with custom slug:
curl -X POST -F "file=@mysite.zip" -F "slug=my-portfolio" http://localhost:8080/api/upload
```

---

## 📂 Project Architecture

```
pagedock/
├── config/
│   └── config.php         # App configuration & autoloader
├── public/                # Web root
│   ├── index.php          # Front controller
│   └── assets/            # CSS & JS assets
├── sites/                 # Hosted customer sites (/s/{slug}/)
├── src/                   # Core business logic
│   ├── Database.php       # SQLite connection & schema
│   ├── Auth.php           # User authentication & site claiming
│   ├── SiteManager.php    # Zip extraction & HTML deployer
│   ├── Forms.php          # Form submissions collector
│   └── Router.php         # Clean routing & static file server
└── views/                 # HTML UI layouts & templates
```
