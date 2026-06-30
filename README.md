# Feedly OPDS Server

An OPDS catalog server that exposes your Feedly "Read Later" (Saved) articles for offline reading in OPDS-compatible readers like KOReader, Marvin, or Perfect Viewer.

## Features

- **OPDS-2 compliant** - Works with any OPDS reader
- **Token authentication** - Basic Auth or query parameter
- **Caching** - Feedly API and Instaparser responses are cached
- **Full-text articles** - Uses Instaparser API for article extraction

## Requirements

- PHP 8.3+
- Composer
- Feedly developer account with API access
- Instaparser API key (optional, for full-text articles)

## Installation

```bash
composer install
cp .env.example .env
```

## Configuration

Configure these variables in your `.env` file:

```env
# Feedly
FEEDLY_DEVELOPER_TOKEN=your_access_token
FEEDLY_REFRESH_TOKEN=your_refresh_token
FEEDLY_CLIENT_ID=your_client_id
FEEDLY_CLIENT_SECRET=your_client_secret
FEEDLY_USER_ID=your_user_uuid
FEEDLY_SAVED_TAG=saved

# Instaparser (optional)
INSTAPARSER_API_KEY=your_api_key

# OPDS
OPDS_AUTH_TOKEN=your_opds_token
OPDS_TITLE="Feedly Read Later"
OPDS_AUTHOR="Your Name"
OPDS_AUTHOR_URI=https://example.com

# Caching (seconds)
CACHE_FEED_TTL=300
CACHE_ARTICLE_TTL=86400
```

### Getting Feedly Credentials

1. Go to [Feedly Developer Portal](https://cloud.feedly.com/v3/developer)
2. Create a new OAuth client
3. Use the OAuth flow to get refresh token, or use Dev Token for testing

## Usage

Start the development server:

```bash
composer run dev
```

### Endpoints

| Route | Description |
|-------|-------------|
| `/` | Project info page |
| `/opds` | Root navigation feed |
| `/opds/saved` | Acquisition feed of saved articles |
| `/opds/entry/{id}` | Article detail with full-text content |
| `/opds/download/{id}` | HTML download of article |

### Authentication

All OPDS endpoints require authentication. You can use either:

- **Basic Auth**: `Authorization: Basic <base64(user:token)>`
- **Query Parameter**: `?token=your_opds_token`

## Testing

```bash
composer test
```

## License

MIT