# Authentication

CorePanel uses Fortify for application authentication and Passport for external API authentication.

## Fortify responsibilities

- login
- registration
- password reset
- email verification
- profile updates
- two-factor authentication

## API authentication

- Passport personal access tokens for external API consumers
- Passport OAuth2 clients for authorization code, client credentials, and related flows

## Related docs

- [passport.md](./passport.md)
- [permissions.md](./permissions.md)

## Social login

Socialite support is optional and is documented as part of the security and auth surface, but it does not replace the main Fortify flow.
