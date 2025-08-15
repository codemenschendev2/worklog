# Worklog PHP API

A PHP API for managing worklogs using Google Drive and Google Sheets with Service Account authentication.

## Directory Structure

```
worklog-php/
├── credentials/           # Google Service Account credentials
│   └── service-account.json
├── public/               # Public web root
│   ├── index.php        # Main API entry point
│   └── .htaccess        # URL rewriting and security
├── src/                  # Source code
│   ├── DriveHelper.php  # Google Drive/Sheets operations
│   └── WorklogService.php # Worklog business logic
├── vendor/               # Composer dependencies
├── composer.json         # Dependency management
├── .env                  # Environment configuration
├── test_drive.php        # Test Google Drive connection
├── create_sheet_test.php # Test sheet creation
└── README.md            # This file
```

## Setup

1. **Install Composer dependencies**:
   ```bash
   composer install
   ```

2. **Configure environment variables** in `.env`:
   ```
   GOOGLE_APPLICATION_CREDENTIALS=credentials/service-account.json
   ROOT_FOLDER_ID=your_worklogs_folder_id_here
   SHEETS_RANGE=Logs!A:G
   SIGNING_SECRET=your_secret_key_here
   ALLOW_ORIGIN=*
   ```

3. **Place your Google Service Account JSON** in `credentials/service-account.json`

4. **Set up web server** to point to the `public/` directory

## API Endpoints

### POST /worklog/append
Append a new worklog entry to user's spreadsheet.

**Required fields:**
- `user`: User email address

**Optional fields:**
- `date`: Date (YYYY-MM-DD), defaults to today
- `project`: Project name
- `task`: Task description
- `duration_h`: Duration in hours (float)
- `notes`: Additional notes
- `idempotency_key`: Unique identifier (auto-generated if not provided)

### POST /worklog/update
Update an existing worklog entry.

**Required fields:**
- `user`: User email address
- `idempotency_key`: Unique identifier of the entry to update

**Updatable fields:**
- `date`: Date (YYYY-MM-DD)
- `project`: Project name
- `task`: Task description
- `duration_h`: Duration in hours (float)
- `notes`: Additional notes

## Security

All requests require HMAC-SHA256 signature in `X-Signature` header:
```
X-Signature: hash_hmac('sha256', raw_body, SIGNING_SECRET)
```

## Example Requests

### Append Worklog Entry
```bash
curl -s -X POST "https://your-domain.com/worklog/append" \
  -H "Content-Type: application/json" \
  -H "X-Signature: $(echo -n '{"date":"2025-08-15","user":"chi@example.com","project":"Holiday Portal","task":"Research hotel APIs","duration_h":2.5,"notes":"Compared Google Hotels vs scraping","idempotency_key":"2025-08-15-chi-1"}' | openssl dgst -sha256 -hmac "<SECRET>" -binary | xxd -p -c 256)" \
  -d '{"date":"2025-08-15","user":"chi@example.com","project":"Holiday Portal","task":"Research hotel APIs","duration_h":2.5,"notes":"Compared Google Hotels vs scraping","idempotency_key":"2025-08-15-chi-1"}'
```

### Update Worklog Entry
```bash
curl -s -X POST "https://your-domain.com/worklog/update" \
  -H "Content-Type: application/json" \
  -H "X-Signature: $(echo -n '{"user":"chi@example.com","idempotency_key":"2025-08-15-chi-1","notes":"Compared Google Hotels API vs scraping; drafted schema"}' | openssl dgst -sha256 -hmac "<SECRET>" -binary | xxd -p -c 256)" \
  -d '{"user":"chi@example.com","idempotency_key":"2025-08-15-chi-1","notes":"Compared Google Hotels API vs scraping; drafted schema"}'
```

## Testing

### Test Google Drive Connection
```bash
php test_drive.php
```

### Test Sheet Creation
```bash
php create_sheet_test.php
```

## Features

- **Automatic Spreadsheet Creation**: Each user gets their own spreadsheet
- **File Sharing**: Automatic sharing with user email (writer access)
- **Idempotency**: Prevents duplicate entries using unique keys
- **HMAC Authentication**: Secure API access with signature verification
- **CORS Support**: Configurable cross-origin requests
- **Error Handling**: Comprehensive error responses

## Requirements

- PHP 8.0 or higher
- Google Service Account with Drive and Sheets API enabled
- Composer for dependency management
- Apache with mod_rewrite enabled

## Google API Setup

1. Create a Google Cloud Project
2. Enable Google Drive API and Google Sheets API
3. Create a Service Account
4. Download the JSON key file
5. Share your Worklogs folder with the service account email
6. Place the JSON file in `credentials/service-account.json`
