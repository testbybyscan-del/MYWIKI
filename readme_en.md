# MYWIKI — Open‑Source Knowledge Base with Full‑Text Search


[![PHP Version](https://img.shields.io/badge/PHP-8.x-777BB4?style=flat&logo=php&logoColor=white)](https://php.net)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-15-4169E1?style=flat&logo=postgresql&logoColor=white)](https://postgresql.org)
[![Docker](https://img.shields.io/badge/Docker-✔-2496ED?style=flat&logo=docker&logoColor=white)](https://docker.com)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)](http://makeapullrequest.com)

> **A lightweight, self‑hosted knowledge base** – store, search and share your technical documentation, notes and guides using Markdown, with powerful full‑text search and Docker‑first deployment.

---

## Why MYWIKI?

Tired of bloated CMS, complex setups, and expensive hosting?  
Need a single place for your team’s documentation, code snippets, API references, and personal notes – all searchable and version‑controlled?

**MYWIKI** was built to solve exactly that:

- **⚡ Instant setup** – one command with Docker, no dependencies.
- **📄 Markdown + tables** – write in pure Markdown, including tables, footnotes and extended syntax.
- **🔍 Smart full‑text search** – fast, relevant search across titles, content, tags and numbers (with Russian language support).
- **🔒 Safe by design** – built‑in sanitizer masks public IPs and high ports before they reach your public repo.
- **🔄 Git‑native** – every page is a `.md` file, edit in any editor, track changes, collaborate via pull requests.
- **🤖 Auto‑update** – GitHub webhook triggers `git pull` + incremental import on every push – your site stays fresh.
- **🐳 Docker‑ready** – runs anywhere (VPS, cloud, local) with persistent data volumes and health checks.

Perfect for **tech docs**, **internal wikis**, **personal knowledge bases**, and even **public documentation hubs**.

---

## 🚀 Key Features at a Glance

| Feature | Benefit |
|---------|---------|
| **Markdown with tables** | Write clean, structured content – tables, lists, footnotes, code blocks. |
| **Full‑text search** | Ranked, stemmed search across all fields – just like a search engine. |
| **Docker containerisation** | Spin up the whole stack in under a minute – no manual PHP/PostgreSQL installation. |
| **Incremental import** | Only new or changed files are processed – saves time on updates. |
| **GitHub webhook** | Push → auto‑pull + import – your site updates automatically. |
| **Sanitisation tool** | Remove real IPs and ports from your files before committing – security first. |
| **Responsive design** | Works on desktop, tablet, and mobile. |
| **One‑click code copy** | Copy code blocks with a single click – great for developers. |
| **Detailed logging** | Full action logs for debugging and monitoring. |

---

## 🛠 Technology Stack

- **Backend:** PHP 8.x (PDO_PGSQL)
- **Database:** PostgreSQL 15 with full‑text search (`tsvector`)
- **Containerisation:** Docker + Docker Compose
- **Markdown parser:** ParsedownExtra (tables, footnotes, extended syntax)
- **Frontend:** Vanilla HTML/CSS/JS – no heavy frameworks, fast loading
- **Automation:** GitHub webhook for continuous deployment

---

## ⚡ Quick Start (5 minutes)

```bash
# Clone the repo
git clone git@github.com:testbybyscan-del/MYWIKI.git
cd MYWIKI

# Configure environment
cp .env.example .env
# Edit .env – set your database password and port

# Launch containers
docker-compose up -d

# Place your .md files into ./pages/ (see naming format below)
# Then import them
docker exec -it <CONTAINER_NAME> php import.php --incremental
```

Open `http://<YOUR_SERVER_IP>:<YOUR_PORT>` in your browser – you’ll see the main page.

---

## 📝 Markdown Tables Support

You can now use tables in your pages:

```markdown
| Header 1 | Header 2 |
|----------|----------|
| Cell 1   | Cell 2   |
| Cell 3   | Cell 4   |
```

**Important:** a blank line must precede the table. Alignment is supported via colons (`:---`, `:---:`, `---:`).

---

## 🔒 Sanitisation – Protect Your Data

The `sanitizer.php` script automatically replaces public IPv4 addresses and ports above 12000 with placeholders. Run it before committing to avoid leaking real IPs or ports:

```bash
docker exec -it <CONTAINER_NAME> php sanitizer.php /var/www/html/pages
```

---

## 🔄 Automatic Updates via Webhook

Configure a GitHub webhook (Payload URL: `https://your-domain/webhook.php`, Secret: set in `webhook.php`). On every `push` to `main`, your site and database are updated automatically.

---

## 📂 Project Structure

```
MYWIKI/
├── docker-compose.yml      # (named volumes, health checks, custom network)
├── Dockerfile              # PHP 8.x + Apache
├── .env                    # Environment variables
├── pages/                  # Your .md files (gitignored, only .gitkeep)
├── import.php              # Console import script
├── index.php               # Main page with search
├── load_file.php           # Page loader (AJAX)
├── sanitizer.php           # IP/port sanitisation
├── webhook.php             # GitHub webhook handler
├── Parsedown.php           # Base Markdown parser
├── ParsedownExtra.php      # Extended parser (tables, footnotes)
├── script.js               # Client‑side logic
├── styles.css              # Responsive design + table styling
└── init-db.sql             # Database initialisation
```

---

## 🔧 Environment Variables (`.env`)

| Variable | Purpose | Default |
|----------|---------|---------|
| `POSTGRES_DB` | Database name | `wiki` |
| `POSTGRES_USER` | Database user | `wiki_user` |
| `POSTGRES_PASSWORD` | Database password | `secure_password_change_me` |
| `WIKI_PORT` | Web interface port | `` |
| `DB_HOST` | Database host (service name) | `db` |
| `DB_NAME` | DB name for PHP | `wiki` |
| `DB_USER` | DB user for PHP | `wiki_user` |
| `DB_PASSWORD` | DB password for PHP | `secure_password_change_me` |

> **Always change default passwords before production.**

---

## 🧪 Logging & Troubleshooting

All actions are logged inside the container at `/tmp/wiki_debug.log`. View them with:

```bash
docker exec <CONTAINER_NAME> cat /tmp/wiki_debug.log
```

Logs help diagnose import issues, DB connectivity, and rendering problems.

---

## 🙏 Acknowledgements

MYWIKI relies on **Parsedown** and **ParsedownExtra** – excellent Markdown parsers by [Emanuil Rusev](https://github.com/erusev).
Special thanks to [hackspace_it](https://t.me/hackspace_it) for inspiration and useful links.

---

## 📄 License

MIT – feel free to use, modify, and distribute in any project.

---

## 📬 Feedback & Support

[GitHub Issues](https://github.com/testbybyscan-del/MYWIKI/issues) – report bugs or suggest improvements.

---

**⭐ Star this repo if you find it useful!**

🚀 **Try MYWIKI today – make your knowledge base truly simple and powerful.**

