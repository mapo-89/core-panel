# Security

CorePanel includes package-level security primitives for admin applications.

## Included areas

- policy-driven authorization
- security headers middleware
- permission cache reset for long-lived workers
- tenant context cleanup
- activity logging
- optional 2FA through Fortify

## Security headers

CorePanel can set:

- `X-Frame-Options`
- `X-Content-Type-Options`
- `Referrer-Policy`
- `Strict-Transport-Security`
- `Content-Security-Policy`
- `Permissions-Policy`

## Recommendation

Treat tenant context cleanup and permission cache cleanup as part of the security model, not just operational hygiene.
