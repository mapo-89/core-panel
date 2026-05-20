# Passport

Passport is the API authentication layer in CorePanel.

## Use Passport when

- you need OAuth2
- third-party clients must authenticate against your application
- you need authorization code or client credentials flow

## Supported capabilities

- OAuth client management UI
- authorization code flow
- client credentials flow
- scopes
- refresh tokens
- revocation

## Notes

- installer setup generates Passport keys and ensures a personal access client
- tenant-aware client isolation must still be enforced on the server side
