# PolicyFlow — Fault-Tolerant Insurance Issuance Engine

## Overview

PolicyFlow is a Laravel REST API prototype for travel insurance policy requests, premium calculation, mock insurer issuance, callback processing, and audit history.

The insurer is simulated. No real insurance policies or payments are created.

## Technology Stack

- PHP 8.5 (Laravel 11 application)
- Laravel REST API
- Eloquent ORM and database migrations
- SQLite for current local development
- PHPUnit/Pest-compatible Laravel testing infrastructure

**Database note:** The assignment requires MySQL 8+ or PostgreSQL. The current local setup uses SQLite; configure and verify a compliant database before claiming full database compliance.

## Features

- Policy request validation
- Idempotent intake using agent ID and client request ID
- Premium calculation with a stored premium snapshot
- Mock insurer submission scenarios
- Unique policy numbers
- Callback event deduplication
- Policy status history and issuance attempts
- Request filtering and pagination
- Automated feature tests

## Premium Rules

| Plan | Daily base premium |
|---|---:|
| BASIC | ₹12 |
| PLUS | ₹20 |
| PREMIUM | ₹30 |

Age multipliers at trip start:

- Age 0–17: 1.10
- Age 18–60: 1.00
- Age 61–70: 1.50
- Age 71+: 2.00

Destination multipliers:

- DOMESTIC: 1.00
- INTERNATIONAL: 1.80

Coverage multipliers:

- ₹100,000: 1.00
- ₹300,000: 1.25
- ₹500,000: 1.50

Travel duration includes both the start and end dates.

## API Endpoints

| Method | Endpoint | Purpose |
|---|---|---|
| POST | `/api/policy-requests` | Create or retrieve a duplicate request |
| GET | `/api/policy-requests` | List requests with pagination and filters |
| GET | `/api/policy-requests/{id}` | View request, attempts, and audit history |
| POST | `/api/policy-requests/{id}/submit` | Submit to mock insurer |
| POST | `/api/mock-insurer/callback` | Process an insurer callback |

Submission scenarios: `SUCCESS`, `TEMPORARY_FAILURE`, `TIMEOUT_AFTER_ISSUE`, `DELAYED_CALLBACK`, and `DUPLICATE_CALLBACK`.

## Local Setup

1. Install PHP and Composer.
2. Install dependencies:

   ```bash
   composer install
   ```

3. Configure `.env` and your database connection.
4. Generate the application key:

   ```bash
   php artisan key:generate
   ```

5. Run migrations:

   ```bash
   php artisan migrate
   ```

6. Start the development server:

   ```bash
   php artisan serve
   ```

7. Run tests:

   ```bash
   php artisan test
   ```

## Example Request

POST `/api/policy-requests`, with JSON:

```json
{
  "client_request_id": "REQ-001",
  "agent_id": "AGENT-001",
  "customer_name": "Test Customer",
  "date_of_birth": "1999-05-15",
  "destination": "DOMESTIC",
  "trip_start": "2026-11-10",
  "trip_end": "2026-11-12",
  "plan_code": "BASIC",
  "sum_insured": 100000
}
```

## Test Results

The focused feature test suite passed 5 tests with 26 assertions in the current development environment.

Run `php artisan test` to execute the complete suite.

## Limitations

This is an assignment prototype. The mock insurer does not connect to a real provider. Provider-status reconciliation, concurrency behavior, production security, and MySQL/PostgreSQL compatibility must be independently verified before production use.
