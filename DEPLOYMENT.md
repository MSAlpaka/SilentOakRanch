# 🐴 Silent Oak Ranch – Deployment Guide

This document explains the automated deployment workflow for Silent Oak Ranch.

> ℹ️ Für Architektur- und Security-Details des hybriden Stacks siehe [`docs/hybrid-setup.md`](docs/hybrid-setup.md).

## 📍 Server Details
- **Host:** 188.34.158.53
- **User:** stallapp
- **App Directory:** /srv/stallapp
- **Domain:** app.silent-oak-ranch.de

## ⚙️ How it works
When you push changes to the `main` branch of
[MSAlpaka/SilentOakRanch](https://github.com/MSAlpaka/SilentOakRanch),
GitHub Actions automatically:
1. Connects to the server via SSH.
2. Runs `/srv/stallapp/deploy.sh`.
3. The script fetches the latest code, rebuilds the Docker images, restarts the
   stack with `docker compose`, and runs database migrations.

## 🛡️ SSH Setup
Add these secrets in your GitHub repository settings:

| Secret Name | Example Value |
|--------------|---------------|
| `SOR_SERVER_HOST` | 188.34.158.53 |
| `SOR_SERVER_USER` | stallapp |
| `SOR_SERVER_SSH_KEY` | *(contents of your private key)* |

The public key must already exist on the server at:
```
/home/stallapp/.ssh/authorized_keys
```

## 🪶 Manual deployment (if needed)
You can trigger deployment manually via SSH:

```bash
ssh stallapp@188.34.158.53
cd /srv/stallapp
./deploy.sh
```

## 🧱 Rollback
In case something goes wrong, simply revert to the previous commit:
```bash
cd /srv/stallapp
git reset --hard HEAD~1
```

## 🌐 Subdomain setup
Point `app.silent-oak-ranch.de` in your DNS to `188.34.158.53`
and configure your webserver’s DocumentRoot to `/srv/stallapp/public`
(or `/srv/stallapp` if you serve static PHP).

## ✅ Notes
- The deployment automatically rebuilds the backend and frontend Docker images
  and runs database migrations so that every push to `main` is released.
- Ensure the server user has permission to run Docker and that the `.env`
  configuration is up to date before deploying.
