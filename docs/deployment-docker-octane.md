# Deployment: Docker and Octane

CorePanel ships a Docker scaffold aimed at Laravel Octane with FrankenPHP.

## Provided files

- `Dockerfile`
- `docker-compose.yml`
- `docker-compose.dev.yml`
- `docker-compose.prod.yml`
- `.docker/php/php.ini`
- `.docker/php/opcache.ini`
- `.docker/supervisor/*.conf`

## Core services

- `app`
- `app-test`
- `horizon`
- `scheduler`
- `postgres`
- `redis`
- `mailpit`

## Start development stack

```bash
docker compose -f docker-compose.dev.yml up -d --build
```

## Install inside the container

```bash
docker compose -f docker-compose.dev.yml exec app php artisan core-panel:install --force
```

## Run tests

Always use the test container:

```bash
docker compose -f docker-compose.dev.yml exec app-test php artisan test --compact
```

## Octane safety

CorePanel includes resetters for:

- tenant context
- permission cache
- database connections
- media-related request state

That reduces state leakage across Octane requests.

## Production notes

- run behind HTTPS
- use Redis for queues and cache
- enable Horizon if queue throughput matters
- keep `horizon:snapshot` scheduled
- run frontend build during image creation
