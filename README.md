# SilentOakRanch

## Project overview & tech stack
Symfony 7.3, PHP 8.2, React, Redux Toolkit, Vite, Vitest, ESLint, i18n.

## Backend setup
```bash
cd backend && composer install --ignore-platform-req=ext-sodium
```

## Frontend setup
```bash
cd frontend && npm ci
```

## Dev commands
```bash
composer install
vendor/bin/phpunit
npm run lint
npm test
npm run build
```

## Workaround note for `ext-sodium`
Some environments lack the `ext-sodium` PHP extension. Use `composer install --ignore-platform-req=ext-sodium` to bypass the requirement during installation.

