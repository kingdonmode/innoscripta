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
git clone <repository-url>
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

Articles are fetched from all three sources with a single Artisan command:

```bash
php artisan news:fetch
```

**Available options:**

| Option | Default | Description |
|---|---|---|
| `--query` | `technology` | Keyword to search for (NewsAPI & Guardian) |
| `--section` | `all` | NYTimes section (`world`, `technology`, `business`, `arts`, etc.) |
| `--dry-run` | — | Fetch but do not persist; useful for testing API connectivity |

**Examples:**

```bash
# Fetch the default technology articles
php artisan news:fetch

# Fetch articles on climate change
php artisan news:fetch --query="climate change"

# Preview what would be fetched without saving
php artisan news:fetch --dry-run

# Fetch NYTimes world section specifically
php artisan news:fetch --section=world
```

---

## Scheduled Updates

The scheduler is configured to run `news:fetch` **every hour** automatically.

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
GET /api/articles
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
GET /api/articles?q=artificial+intelligence&source=guardian&from=2024-06-01&per_page=10
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
GET /api/articles/{id}
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
GET /api/articles/sources
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
GET /api/articles/categories
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
GET /api/articles/authors
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
│   └── NewsFetcherInterface.php     # Contract all fetchers implement
├── Console/
│   └── Commands/
│       └── FetchNewsArticles.php    # php artisan news:fetch
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       └── ArticleController.php
│   └── Requests/
│       └── ArticleFilterRequest.php # Input validation
├── Models/
│   └── Article.php                  # Eloquent model with query scopes
├── Providers/
│   └── AppServiceProvider.php       # Wires fetchers into the aggregator
├── Repositories/
│   └── ArticleRepository.php        # Handles DB upserts
└── Services/
    └── News/
        ├── GuardianApiFetcher.php   # The Guardian
        ├── NewsApiFetcher.php       # EventRegistry (NewsAPI)
        ├── NewsAggregatorService.php # Orchestrates all fetchers
        └── NytimesFetcher.php       # New York Times TimesWire

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
