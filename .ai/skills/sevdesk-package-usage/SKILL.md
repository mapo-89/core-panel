---
name: sevdesk-package-usage
description: "Use this skill when working with the exlo89/laravel-sevdesk-api package in this project. Trigger it when implementing sevDesk connections, validating API tokens, reading sevDesk users, storing provider identifiers such as provider_user_id, running sevDesk syncs, or deciding how to use the package without relying on a global .env token."
---

# Sevdesk Package Usage

Use `exlo89/laravel-sevdesk-api` as a runtime-driven client, not as a global `.env` integration.

## Project Rules

- Whenever sevDesk API calls are needed in this project, use `exlo89/laravel-sevdesk-api` first.
- Do not start with direct `Http::` calls, custom Guzzle clients, or handwritten sevDesk request wrappers unless the package clearly cannot support the required endpoint or behavior.
- Treat the `integrations` table as the source of truth for sevDesk credentials.
- Do not assume `SEVDESK_API_TOKEN` in `.env` is authoritative.
- Do not publish the package config just to make the package work.
- Set the sevDesk token at runtime immediately before a provider call.
- Restore the previous config value after the call when needed.

## Current Project Pattern

For this project, sevDesk credentials live on the `sevDesk` row in `integrations`.

Use:

- `token` for the API token
- `provider_user_id` for the resolved remote user id

Keep provider-specific metadata on the integration record while the data footprint stays small.

## Connection Validation

Validate the token before saving the integration.

Preferred flow:

1. Read the submitted API token from the form request.
2. Inject it into `config('sevdesk-api.api_token')` for the duration of the check.
3. Call `SevdeskApi::make()->user()->all()`.
4. Treat the first returned user's `id` as the verified `provider_user_id`.
5. If the package throws `UnauthorizedException`, add a validation error on `api_token`.
6. If the response is malformed or empty, fail validation with a provider connection message.

Use `SevUser` for validation instead of the version endpoint when the remote user id will be needed later.

## Persistence Pattern

After successful validation:

- store the API token in `integrations.token`
- store the resolved remote user id in `integrations.provider_user_id`
- clear unrelated fields like `username` and `password`

On disconnect:

- clear `token`
- clear `provider_user_id`
- clear any other sevDesk-derived state tied to the connection

## Runtime Wrapper Pattern

Prefer a dedicated service class that wraps temporary config injection.

Always check whether the needed sevDesk operation can be expressed through `exlo89/laravel-sevdesk-api` before introducing any lower-level client code.

Typical shape:

1. Capture the previous `sevdesk-api.api_token` value.
2. Set the integration token into config.
3. Execute the package call.
4. Restore the original config in `finally`.

Keep this logic centralized so later sync actions do not duplicate provider setup code.

## Error Handling

- Map invalid or unauthorized tokens to a validation error the UI can show inline.
- Report unexpected transport or package errors with `report($exception)`.
- Return a friendly provider-specific message for non-auth failures.
- Do not leak raw provider exceptions directly to end users.

## Testing Pattern

Prefer feature tests for the settings flow and mock the validator service.

Test at least:

- valid sevDesk token stores `token` and `provider_user_id`
- invalid sevDesk token returns a validation error and stores nothing
- disconnect clears `token` and `provider_user_id`

Keep direct package calls out of tests unless intentionally writing an integration test with real credentials.

## Config Publishing Guidance

Do not publish the package config unless the project later needs real global defaults unrelated to a specific integration record.

For this project, runtime injection is the intended approach because credentials are per integration, not per deployment.
