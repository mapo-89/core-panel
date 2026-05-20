# Permissions

CorePanel uses role and permission management for admin authorization.

## Included capabilities

- role CRUD
- permission CRUD
- assign roles to users
- tenant-aware permission handling where applicable

## Policy approach

Authorization is enforced through Laravel policies and gates rather than frontend-only checks.

## UI behavior

The roles page supports:

- role editing
- permission assignment
- user role assignment

Wayfinder is used for endpoint wiring.

## Operational note

If you run under Octane, permission cache reset matters. CorePanel includes reset logic to reduce cross-request permission leakage.
