# News Aggregator API

A Laravel 12 backend that pulls articles from three live news sources, stores them locally, and exposes a clean REST API for querying, filtering, and searching.

---

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Database Setup](#database-setup)
- [Running the Application](#running-the-application)
- [Fetching News Articles](#fetching-news-articles)
- [Scheduled Updates](#scheduled-updates)
- [Running Tests](#running-tests)
- [API Reference](#api-reference)
- [Project Structure](#project-structure)

---

## Requirements

| Dependency | Version |
|---|---|
| PHP | >= 8.2 |
| Composer | >= 2.x |
| SQLite / MySQL / PostgreSQL | Any |
| Node.js + npm | >= 18 (for asset build only) |

---

## Installation

**1. Clone the repository**

```bash
git clone https://github.com/kingdonmode/innoscripta.git
cd innoscripta
```

**2. Install PHP dependencies**

```bash
composer install
```

**3. Copy the environment file**

```bash
cp .env.example .env
```

**4. Generate the application key**

```bash
php artisan key:generate
```

---

## Configuration

Open `.env` and fill in the three API keys. Free-tier accounts are sufficient.

```env
# EventRegistry (NewsAPI)
# Register at https://eventregistry.org
NEWSAPI_KEY=your_eventregistry_api_key

# The Guardian
# Register at https://open-platform.theguardian.com
GUARDIAN_API_KEY=your_guardian_api_key

# The New York Times
# Register at https://developer.nytimes.com
NYTIMES_API_KEY=your_nytimes_api_key
```

### Database

The application ships with SQLite by default. No extra setup is required for local development.

```env
DB_CONNECTION=sqlite
# DB_DATABASE is automatically resolved to database/database.sqlite
```

To switch to MySQL or PostgreSQL, update the relevant `DB_*` variables:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=news_aggregator
DB_USERNAME=root
DB_PASSWORD=secret
```

---

## Database Setup

Create the SQLite file (skip if using MySQL/PostgreSQL) and run the migrations:

```bash
touch database/database.sqlite   # SQLite only
php artisan migrate
```

---

## Running the Application

Start the development server:

```bash
php artisan serve
```

The API will be available at `http://localhost:8000/api`.

---

## Fetching News Articles

The `news:fetch` command supports three modes. By default it dispatches a queued job per source so the command returns instantly and each source is processed in parallel by queue workers.

**Available options:**

| Option | Default | Description |
|---|---|---|
| `--query` | `technology` | Keyword to search for (NewsAPI & Guardian) |
| `--section` | `all` | NYTimes section (`world`, `technology`, `business`, `arts`, etc.) |
| `--dry-run` | — | Call the APIs and print counts — nothing is saved |
| `--sync` | — | Fetch and save in the current process without queuing |

### Modes explained

| Mode | Saves data | Blocks terminal | Retries on failure | Use when |
|---|---|---|---|---|
| `--dry-run` | No | Yes | No | Verifying API keys |
| `--sync` | Yes | Yes | No | No queue worker available |
| _(default)_ | Yes | No | Yes (up to 3×) | Production |

**Examples:**

```bash
# Production — dispatches one queued job per source (recommended)
php artisan news:fetch

# Fetch articles on a specific topic
php artisan news:fetch --query="climate change"

# Fetch NYTimes world section
php artisan news:fetch --section=world

# Preview what would be fetched without saving
php artisan news:fetch --dry-run

# Fetch and save immediately without the queue
php artisan news:fetch --sync
```

---

## Queue Worker

When running in the default (queued) mode, a queue worker must be running to process the dispatched jobs.

Start a worker:

```bash
php artisan queue:work
```

Each `FetchSourceArticles` job handles one news source. Failed jobs are **automatically retried up to 3 times** with a 60-second backoff before being moved to the failed jobs table.

View failed jobs:

```bash
php artisan queue:failed
```

Retry a failed job:

```bash
php artisan queue:retry all
```

In production, manage the worker with a process supervisor such as [Supervisor](http://supervisord.org/) to ensure it stays running.

---

## Scheduled Updates

The scheduler is configured to run `news:fetch` (queued mode) **daily** automatically.

To activate it, add a single cron entry to your server:

```cron
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

During local development, use the following command instead of editing cron:

```bash
php artisan schedule:work
```

---

## Running Tests

```bash
php artisan test
```

The test suite uses an in-memory SQLite database — no additional configuration is needed.

```
Tests:  30 passed
```

---

## API Reference

**Base URL:** `http://localhost:8000/api`

All responses are JSON. List endpoints return Laravel's standard paginator envelope.

---

### List Articles

```
GET /api/v1/articles
```

Returns a paginated list of articles. All parameters are optional and combinable.

**Query Parameters:**

| Parameter | Type | Description | Example |
|---|---|---|---|
| `q` | string | Search across title, description, and content | `q=climate` |
| `source` | string | Comma-separated source IDs | `source=guardian,nytimes` |
| `category` | string | Comma-separated category names | `category=Technology,World` |
| `author` | string | Comma-separated exact author names | `author=Jane+Doe,John+Smith` |
| `from` | date | Published on or after this date (`YYYY-MM-DD`) | `from=2024-01-01` |
| `to` | date | Published on or before this date (`YYYY-MM-DD`) | `to=2024-12-31` |
| `per_page` | integer | Results per page (1–100, default 15) | `per_page=25` |
| `page` | integer | Page number (default 1) | `page=2` |
| `sort` | string | Sort column: `published_at` (default) or `title` | `sort=title` |
| `order` | string | Sort direction: `desc` (default) or `asc` | `order=asc` |

**Example request:**

```
GET /api/v1/articles?q=artificial+intelligence&source=guardian&from=2024-06-01&per_page=10
```

**Response:**

```json
{
  "current_page": 1,
  "data": [
    {
      "id": 42,
      "external_id": "a1b2c3d4...",
      "source_id": "guardian",
      "source_name": "The Guardian",
      "title": "AI regulation moves forward in Europe",
      "description": "EU lawmakers have approved...",
      "content": "Full article body...",
      "url": "https://theguardian.com/...",
      "image_url": "https://media.guim.co.uk/...",
      "author": "Alex Hern",
      "category": "Technology",
      "published_at": "2024-06-10T09:30:00.000000Z",
      "created_at": "2024-06-10T10:00:00.000000Z",
      "updated_at": "2024-06-10T10:00:00.000000Z"
    }
  ],
  "per_page": 10,
  "total": 84,
  "last_page": 9,
  ...
}
```

---

### Get a Single Article

```
GET /api/v1/articles/{id}
```

**Response:**

```json
{
  "data": {
    "id": 42,
    "source_id": "guardian",
    "title": "AI regulation moves forward in Europe",
    ...
  }
}
```

Returns `404` if the article does not exist.

---

### List Sources

```
GET /api/v1/articles/sources
```

Returns all news sources that have stored articles, along with an article count.

**Response:**

```json
{
  "data": [
    { "source_id": "guardian",  "source_name": "The Guardian",        "article_count": 25 },
    { "source_id": "newsapi",   "source_name": "News Api",            "article_count": 30 },
    { "source_id": "nytimes",   "source_name": "The New York Times",  "article_count": 40 }
  ]
}
```

---

### List Categories

```
GET /api/v1/articles/categories
```

Returns all distinct categories present in the database.

**Response:**

```json
{
  "data": [
    { "category": "Business", "article_count": 12 },
    { "category": "Technology", "article_count": 31 },
    { "category": "World", "article_count": 27 }
  ]
}
```

---

### List Authors

```
GET /api/v1/articles/authors
```

Returns all distinct authors present in the database.

**Response:**

```json
{
  "data": [
    { "author": "Alex Hern",    "article_count": 3 },
    { "author": "Jane Doe",     "article_count": 7 },
    { "author": "Lynsey Chutel","article_count": 2 }
  ]
}
```

---

## Project Structure

```
app/
├── Contracts/
│   └── NewsFetcherInterface.php      # Contract all fetchers implement
├── Console/
│   └── Commands/
│       └── FetchNewsArticles.php     # php artisan news:fetch (dry-run / sync / queue)
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       └── ArticleController.php
│   └── Requests/
│       └── ArticleFilterRequest.php  # Input validation
├── Jobs/
│   └── FetchSourceArticles.php       # Queued job — one per news source, retries up to 3×
├── Models/
│   └── Article.php                   # Eloquent model with query scopes
├── Providers/
│   └── AppServiceProvider.php        # Wires fetchers into the aggregator
├── Repositories/
│   └── ArticleRepository.php         # All DB reads and writes
└── Services/
    └── News/
        ├── GuardianApiFetcher.php    # The Guardian
        ├── NewsApiFetcher.php        # EventRegistry (NewsAPI)
        ├── NewsAggregatorService.php # Orchestrates all fetchers
        └── NytimesFetcher.php        # New York Times TimesWire

routes/
├── api.php       # REST endpoints
└── console.php   # Scheduler definition

database/
└── migrations/
    └── ..._create_articles_table.php
```

### Data Sources

| Source ID | Source | API |
|---|---|---|
| `newsapi` | News Api | EventRegistry API v1 |
| `guardian` | The Guardian | Guardian Open Platform |
| `nytimes` | The New York Times | Times Newswire API |
