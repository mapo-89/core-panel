# API Response Builder

CorePanel provides a consistent JSON response shape for API endpoints.

## Success shape

```json
{
  "success": true,
  "message": "...",
  "data": {},
  "meta": {}
}
```

## Error shape

```json
{
  "success": false,
  "message": "...",
  "errors": {},
  "meta": {}
}
```

## Methods

- `success()`
- `error()`
- `validationError()`
- `paginated()`
- `noContent()`

## Notes

- API resources are supported
- pagination metadata is normalized
- API version metadata is prepared in the response layer
