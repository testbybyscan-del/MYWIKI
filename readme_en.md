# MYWIKI

[![PHP Version](https://img.shields.io/badge/PHP-8.x-777BB4?style=flat&logo=php&logoColor=white)](https://php.net)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-15-4169E1?style=flat&logo=postgresql&logoColor=white)](https://postgresql.org)
[![Docker](https://img.shields.io/badge/Docker-✔-2496ED?style=flat&logo=docker&logoColor=white)](https://docker.com)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)](http://makeapullrequest.com)

> **A lightweight knowledge base system** – store, search and share your Markdown notes with full‑text search, Docker‑ready, and self‑hosted.

> **Легковесная база знаний,готовая к развёртыванию в Docker** – храните, ищите и публикуйте заметки в Markdown с полнотекстовым поиском.

---

## 🚀 What it does / Что делает

- **Markdown → SQL** – Import `.md` files into PostgreSQL with automatic parsing of metadata (number, language, topic).
- **Full‑text search** – Fast and accurate search across titles, content, tags and numbers with relevance ranking (Russian language support).
- **Web interface** – Clean, responsive UI with live search, code copying and syntax‑highlighted Markdown rendering (via Parsedown).
- **Automatic updates** – GitHub webhook triggers `git pull` + incremental import on every push.
- **Docker‑native** – Run with `docker-compose up -d`, everything is containerised (PHP + PostgreSQL).
- **Sanitisation** – Built‑in script to mask public IPs and high ports from your files before publishing.

---

## ✨ Key features / Ключевые возможности

| Feature | Description |
|---------|-------------|
| 📄 **Markdown pages** | Store pages as `.md` files with naming pattern `[num]_[lang]_[topic].md` |
| 🔍 **Full‑text search** | PostgreSQL `tsvector` with ranking, highlights, and stemming for Russian |
| 🖥️ **Web UI** | List view, live search (AJAX), page preview with code copy button |
| ⚡ **Incremental import** | Only new or changed files are processed |
| 🔄 **Webhook** | Auto‑update on `git push` – no manual steps |
| 🛡️ **Sanitizer** | Remove real IPs and ports from files automatically |
| 🐳 **Docker** | Ready‑to‑use containers with persistent data volume |
| 🔐 **Environment config** | All secrets and ports via `.env` file |

---

## 📦 Quick start / Быстрый старт

```bash
# Clone the repository
git clone git@github.com:testbybyscan-del/MYWIKI.git
cd MYWIKI

# Create environment file
cp .env.example .env
# Edit .env – set your database password and port

# Start containers
docker-compose up -d

# Import your pages (place .md files into ./pages/ first)
docker exec -it <container_name> php import.php --incremental
```

Your wiki will be available at `http://<server_ip>:<WIKI_PORT>` (configured in `.env`).

---

## 🧩 Configuration / Настройка

All settings are stored in `.env` (copy from `.env.example`):

```ini
POSTGRES_DB=wiki
POSTGRES_USER=wiki_user
POSTGRES_PASSWORD=secure_password_change_me
WIKI_PORT=<your_port>
DB_HOST=db
DB_NAME=wiki
DB_USER=wiki_user
DB_PASSWORD=secure_password_change_me
```

> ⚠️ **Change the default password** before deploying to production.

---

## 📂 File naming and import / Именование файлов и импорт

Place your Markdown files in the `pages/` directory with the following naming pattern:

```
[number]_[language]_[topic].md
```

Example: `0001._php._array.md`

- The first line of the file (if starts with `#`) is used as the page title.
- The import script `import.php` supports two modes:
  - `--force` – drop and recreate the table.
  - `--incremental` – add new files and update changed ones (by filename).

Run import manually:

```bash
docker exec -it <container_name> php import.php --incremental
```

---

## 🔧 Additional tools / Дополнительные инструменты

### Sanitizer (`sanitizer.php`)

Recursively scans all `.md` files and replaces:

- Public IPv4 addresses → `X.X.X.X`
- High ports (>12000) → `<ВАШ ПОРТ>`

Run inside the container:

```bash
docker exec -it <container_name> php sanitizer.php /var/www/html/pages
```

### Webhook (`webhook.php`)

Accepts POST requests from GitHub on `push` event. It:

1. Verifies the signature (secret token).
2. Runs `git pull origin main`.
3. Executes `php import.php --incremental`.

Set up a GitHub webhook with:

- **Payload URL:** `https://your-domain/webhook.php`
- **Secret:** same as in `webhook.php`
- **Content type:** `application/json`

---

## 🗄️ Database schema / Схема БД

Table `pages`:

```sql
CREATE TABLE pages (
  id SERIAL PRIMARY KEY,
  filename TEXT UNIQUE NOT NULL,
  title TEXT NOT NULL,
  num TEXT,
  lang TEXT,
  topic TEXT,
  content TEXT NOT NULL,
  search_vector TSVECTOR GENERATED ALWAYS AS (
    setweight(to_tsvector('russian', coalesce(num, '')), 'A') ||
    setweight(to_tsvector('russian', coalesce(lang, '')), 'B') ||
    setweight(to_tsvector('russian', coalesce(topic, '')), 'B') ||
    setweight(to_tsvector('russian', coalesce(title, '')), 'A') ||
    setweight(to_tsvector('russian', coalesce(content, '')), 'C')
  ) STORED
);
```

GIN indexes are created automatically for fast full‑text search.

---

## 📁 Project structure / Структура проекта

```
MYWIKI/
├── docker-compose.yml      # Container orchestration
├── Dockerfile              # PHP + Apache image
├── .env.example            # Environment variables template
├── .dockerignore           # Files excluded from build context
├── init-db.sql             # Initial schema creation
├── import.php              # Console import script
├── index.php               # Main web interface
├── load_file.php           # Page loader (AJAX)
├── sanitizer.php           # IP/port sanitisation tool
├── webhook.php             # GitHub webhook handler
├── Parsedown.php           # Markdown parser (MIT)
├── script.js               # Client‑side logic
├── styles.css              # Responsive styles
├── pages/                  # Your .md files (gitignored content)
│   └── .gitkeep
└── pg_data/                # PostgreSQL data volume (auto‑created)
```

---

## 🌐 About / О проекте

**MYWIKI** was originally built in 2020 as a file‑based indexer. In 2025 it was fully migrated to PostgreSQL with full‑text search, making it a modern, scalable knowledge base. The project is maintained by [@Bybyscan](https://github.com/testbybyscan-del) and uses [Parsedown](https://github.com/erusev/parsedown) for Markdown rendering.

---

## 📄 License / Лицензия

MIT License – see [LICENSE](LICENSE) file for details.

---

## 🤝 Contributing / Вклад

Issues and pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.

---

## 📬 Contact / Контакты

- GitHub Issues: [testbybyscan-del/MYWIKI/issues](https://github.com/testbybyscan-del/MYWIKI/issues)
- Author: [@Bybyscan](https://github.com/testbybyscan-del)

---

**Happy documenting!** 📝
