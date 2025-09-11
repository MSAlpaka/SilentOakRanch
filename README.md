# SilentOakRanch

## Project overview & tech stack
Symfony 7.3, PHP 8.2, React, Redux Toolkit, Vite, Vitest, ESLint, i18n.

## Setup
After cloning, run:

```bash
composer install --ignore-platform-req=ext-sodium
npm ci
npm run build
```

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
Some environments lack the `ext-sodium` PHP extension. Use
`composer install --ignore-platform-req=ext-sodium` to bypass the requirement during installation.

## Pferdewaage
The Pferdewaage service guides horse owners from reservation to result:

1. **Booking** - reserve a timeslot online.
2. **Confirmation** - receive a confirmation containing a QR code.
3. **Payment** - complete payment to secure the slot.
4. **Weighing** - on site, scan the QR code for check-in and automatic weight capture.

Flow: booking → confirmation → payment → weighing.

## Release & tagging
git remote add origin <REMOTE_URL>
git push -u origin main
git tag v1.0.0
git push origin v1.0.0
