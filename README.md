# Forge + Cloudflare IP Range Autoupdate

A simple service for Laravel Forge users that periodically checks and updates Cloudflare IP ranges and updates Forge firewall rules accordingly. 

### Configuration

Set the following .env keys:

- `FORGE_API_KEY=YOUR_FORGE_API_KEY_HERE`
- `FORGE_SERVER_ID=YOUR_FORGE_SERVER_ID_HERE`

And configure the command scheduler!
