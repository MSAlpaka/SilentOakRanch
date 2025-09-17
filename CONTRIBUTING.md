# Contributing
## Branch naming
- feature/<short-description>
- fix/<short-description>
## Pull requests
- open against `main`
- include description and link to issues
## Testing
- run `vendor/bin/phpunit`, `npm run lint`, `npm test` before pushing
- CI runs `npm run lint` in `frontend` and fails fast on lint errors to protect the pipeline
