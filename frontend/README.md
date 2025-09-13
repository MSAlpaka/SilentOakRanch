# Frontend

React-Frontend für Kunden, Mitarbeitende und Admins des SilentOakRanch-Portals.

## Entwicklung

```bash
cd frontend
npm install
npm run dev
```

Der Development-Server läuft standardmäßig unter [http://localhost:5173](http://localhost:5173).

## Linting

```bash
npm run lint
```

Dieser Befehl wird ebenfalls in der CI-Pipeline ausgeführt.

## Security Updates

- Run `npm audit` regularly to check for vulnerable dependencies.
- Apply fixes with `npm audit fix`.
- Integrate `npm audit` into the CI pipeline so issues are detected early.
- After updating dependencies, rerun `npm run lint`, `npm test`, and `npm run build` to ensure the project remains stable.

