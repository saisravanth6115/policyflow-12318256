# AI Usage Disclosure

## How AI Was Used

AI assistance was used during development of the PolicyFlow assignment for:

- Planning the Laravel application structure and API endpoints.
- Drafting premium-calculation and mock-insurer service code.
- Drafting model and controller implementations.
- Developing feature tests for request creation, duplicate intake, validation, issuance, and callback idempotency.
- Troubleshooting PHP syntax errors and local application setup.
- Drafting project documentation.

## Developer Verification

The developer executed commands locally, corrected file-formatting issues, started the Laravel development server, exercised API endpoints using PowerShell, and ran the focused feature test suite.

The verified test run reported 5 passing tests and 26 assertions.

## Limitations and Outstanding Verification

AI-generated code may contain defects. The application requires additional review before being considered complete. In particular:

- The assignment-required MySQL 8+ or PostgreSQL configuration has not yet been verified; local development used SQLite.
- Provider reconciliation and retry behavior require further implementation and testing.
- Additional tests for all supported scenarios, invalid inputs, and concurrency are still needed.
- Security, authorization, and production deployment hardening have not been completed.

AI assistance was used as a development aid, not as a substitute for testing or review.
