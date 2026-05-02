# Mythic Fox

Personal admin tool for the Mythic Fox Games TCGPlayer storefront. Single-operator Laravel app — order import from TCGPlayer CSV/PDF exports, inventory + pricing management, packing-slip rendering, and a small public marketing site.

See [docs/saas-design.md](docs/saas-design.md) for the full design.

## Local development

Requirements:

- PHP 8.3 + Composer
- Node 20+
- PostgreSQL 15+ (recommended: [DBngin](https://dbngin.com/) on macOS)

Setup:

```sh
composer install
npm ci
cp .env.example .env
php artisan key:generate
```

Create the dev and test databases (defaults to `postgres` user, no password):

```sh
createdb -h 127.0.0.1 -U postgres mythicfox
createdb -h 127.0.0.1 -U postgres mythicfox_test
php artisan migrate
```

Run the dev stack (server + queue + logs + Vite):

```sh
composer dev
```

## Tests

```sh
composer test
```

Pest runs against the `mythicfox_test` database (configured in [phpunit.xml](phpunit.xml)).
