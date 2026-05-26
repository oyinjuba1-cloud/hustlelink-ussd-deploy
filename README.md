# HustleLink USSD

This repository contains a simple PHP USSD webhook for Africa's Talking.

## Deploy on Render

1. Push this folder to GitHub.
2. In Render, create a new service and connect the repo.
3. Use Docker environment with `render.yaml`.
4. Set Africa's Talking USSD callback URL to:
   `https://<your-service>.onrender.com/hustlelink_ussd.php`

## Included files

- `hustlelink_ussd.php` - USSD endpoint
- `Dockerfile` - starts PHP built-in server
- `render.yaml` - Render service definition
