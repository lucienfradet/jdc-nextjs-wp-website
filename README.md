# SITE WEB - LE JARDIN DES CHEFS

Documentation for the website of [jardindeschefs.ca](https://jardindeschefs.ca)

## Technology Stack

- Frontend: Next.js 13+ (App Router), React 18
- Content Management: Headless WordPress with WooCommerce
- Payment Processing: Stripe
- Styling: CSS Modules
- Deployment: Digital Ocean Droplet with Docker Compose
- Load Balancer: Traefik
- Container Management: Watchtower for automatic updates
- Monitoring: Custom health monitoring with ntfy notifications

### Frontend

- [Next.js](https://nextjs.org)
- [mui](https://mui.com/) components
- [Stripe](https://dashboard.stripe.com/) Payment Gateway integration and Webhooks

### Backend

- [WordPress](https://wordpress.org/) as headless CMS
- MySQL/Prisma local Database
- WordPress/WooCommerce
- Redis for caching and session management

### Deployment & Infrastructure

- **Docker Compose**: Multi-container orchestration
- **Traefik**: Load balancer with health checks and sticky sessions
- **Watchtower**: Automatic container updates with rolling deployments
- **GitHub Actions**: CI/CD pipeline with container registry
- **Digital Ocean Droplet**: Production hosting
- **Automated backups**: Weekly encrypted database backups to Nextcloud

## Architecture Overview

### Production Deployment Stack

The production environment uses a sophisticated deployment stack designed for high availability and zero-downtime updates:

#### Load Balancing & High Availability
- **Traefik Load Balancer**: Routes traffic between two Next.js instances
- **Sticky Sessions**: Ensures user session consistency during updates
- **Health Checks**: Continuous monitoring of service availability
- **Rolling Updates**: Zero-downtime deployments with Watchtower

#### Container Orchestration
- **Two Next.js Instances**: `nextjs-1` and `nextjs-2` for redundancy
- **Database Separation**: WordPress MySQL and Orders MySQL (separate containers)
- **Redis Cache**: Shared caching layer for both Next.js instances
- **Backup Services**: Automated cron jobs for database backups

#### Security Implementation
Security headers are implemented in `middleware.js` and include:
- **Content Security Policy (CSP)**: Comprehensive policy restricting resource loading
- **CORS Configuration**: Restricted to allowed origins with credentials support
- **Security Headers**: XSS protection, clickjacking prevention, MIME sniffing protection
- **HSTS**: Enforced HTTPS in production with preload directive
- **Feature Policy**: Restricted permissions for camera, microphone, geolocation

## Development & Deployment Workflow

### Branch Strategy & Update Process

The site uses a three-branch workflow for safe deployments:

#### 1. Development Branch (`dev`)
- Feature development and initial testing
- Local development environment
- Non-HTTPS fetching allowed for development

#### 2. Test Branch (`test`)
- Pre-production testing
- Local build testing with production-like environment
- Validates container builds before production

#### 3. Main Branch (`main`)
- Production deployments
- Triggers GitHub Actions CI/CD
- Automatic container building and deployment

### Deployment Process

1. **Develop Features**:
   ```bash
   git checkout dev
   # Implement features
   git commit -m "Add new feature"
   git push origin dev
   ```

2. **Test Locally**:
   ```bash
   git checkout test
   git merge dev
   # Test local build
   npm run build
   npm run start -- -H [HOST IP IN next.config.mjs] -p [PORT in next.config.mjs]
   ```

3. **Deploy to Production**:
   ```bash
   git checkout main
   git merge test
   git push origin main
   ```

4. **Monitor Deployment**:
   - GitHub Actions builds and pushes new container to GHCR
   - Watchtower detects new image and performs rolling update
   - Health monitoring ensures successful deployment
   - Notifications sent via ntfy for deployment status

### GitHub Actions CI/CD Pipeline

The CI/CD pipeline (`/.github/workflows/build-and-push.yml`) automatically:

1. **Builds Docker Image**: On push to main branch
2. **Injects Build Arguments**: Environment variables for Next.js build
3. **Pushes to Registry**: GitHub Container Registry (GHCR)
4. **Tags Appropriately**: Latest tag for main branch, PR tags for pull requests

### Watchtower Rolling Updates

Watchtower provides zero-downtime deployments through:

#### **!IMPORTANT**
Watchtower seems to be in a weird state with rolling restart. It works now but
may very well break in future updates. In currently works when doing automatic
update triggers but don't work when running manual `docker exec jdc-watchtower
/watchtower --run-ounce` as it weirdly checks for all services and requires to
have ZERO `depends_on` tags...

- **Health Check Validation**: Ensures other instance is healthy before updating
- **Rolling Restart**: Updates one instance at a time
- **Lifecycle Hooks**: Pre and post-update health verification
- **Failure Recovery**: Automatic rollback if health checks fail
- **Notification Integration**: Status updates via ntfy

## Monitoring & Health Checks

### Automated Health Monitoring

The monitoring system (`/monitoring/health-check.sh`) provides:

- **Service Health Checks**: Monitors Next.js instances and Traefik
- **Failure Detection**: Tracks consecutive failures before alerting
- **Recovery Notifications**: Alerts when services come back online
- **Critical Alerts**: Urgent notifications for complete service outages
- **ntfy Integration**: Real-time notifications to configured channels

### Monitored Services

- **Next.js Instance 1**: `http://nextjs-1:3000/api/health`
- **Next.js Instance 2**: `http://nextjs-2:3000/api/health`
- **Traefik Load Balancer**: `http://traefik:6969/ping`

### Alert Levels

- **Low Priority**: Service startup notifications
- **Default**: Service recovery notifications
- **High Priority**: Individual service failures
- **Urgent**: Critical infrastructure failures (load balancer, all instances down)

## WordPress & Cron Management

### WordPress Cron Bypass

WordPress default cron relies on site visits, which is unreliable. Our implementation bypasses this with:

#### Internal Cron Setup (`/backend/wordpress-cron-setup/`)
- **WP-CLI Integration**: Direct cron execution via WP-CLI
- **Fallback HTTP Method**: Direct HTTP calls to wp-cron.php
- **System Cron**: Linux cron running inside WordPress container
- **Multiple Execution Methods**: Ensures cron reliability

#### Cron Schedule
- **Primary**: WP-CLI cron every 5 minutes
- **Fallback**: HTTP cron every 10 minutes
- **External Backup**: Host-level script available if needed

### Database Backup & Cleanup Cron

The backup system (`/backend/cron/`) provides:

#### Automated Database Backups
- **WordPress Database**: Daily backups at 2 AM EST
- **Compression**: XZ compression for optimal storage
- **Encryption**: GPG encryption with admin@jardindeschefs.ca key
- **Remote Storage**: Automatic upload to Nextcloud
- **Cleanup**: Local files removed after successful upload

#### Cleanup Operations
- **Expired Payment Intents**: Hourly cleanup via Next.js API
- **Old Backup Files**: Weekly cleanup of files older than 7 days
- **Database Optimization**: Regular maintenance tasks

#### Backup Security
- **GPG Encryption**: All backups encrypted before storage
- **Secure Transfer**: Encrypted uploads to Nextcloud
- **Access Control**: Restrictive file permissions (umask 077)

## Environment Variables & Configuration

### Variable Distribution Strategy

Environment variables are strategically distributed across different systems for security and functionality:

#### GitHub Repository Secrets (`Settings > Secrets and variables > Actions`)
Sensitive `NEXT_PUBLIC_*` variables that must be baked into the container build:
- `NEXT_PUBLIC_STRIPE_PUBLISHABLE_KEY`
- `NEXT_PUBLIC_GOOGLE_MAPS_API_KEY`
- `NEXT_PUBLIC_RECAPTCHA_SITE_KEY`

#### GitHub Actions Workflow (`.github/workflows/build-and-push.yml`)
Non-sensitive `NEXT_PUBLIC_*` variables defined directly in the workflow:
```yaml
NEXT_PUBLIC_WORDPRESS_HOSTNAME=wordpress.jardindeschefs.ca
NEXT_PUBLIC_WORDPRESS_BASE_URL=https://wordpress.jardindeschefs.ca/
NEXT_PUBLIC_SITE_URL=https://jardindeschefs.ca
NEXT_PUBLIC_SSL_IGNORE=false
```

#### Backend Environment (`/backend/.env`)
Docker orchestration and sensitive runtime variables:
- Database credentials
- API keys for services
- Redis configuration
- Backup system credentials
- Webhook secrets

NOTE: backend/.env (Real .env for prod, should also include Next.js' private variables!)

### Important Notes on NEXT_PUBLIC Variables

**Critical**: All `NEXT_PUBLIC_*` variables must be available at **build time** and are baked into the container. These cannot be changed at runtime and require a new container build to update.

**Security Consideration**: `NEXT_PUBLIC_*` variables are exposed to the client-side code, so only use them for values that are safe to be public.

## API Documentation

### Products API

```GET /api/products``` - List all products
```GET /api/products/[id]``` - Get a specific product

#### Pickup Locations API

```GET /api/point_de_chute``` - List all pickup locations

#### Tax Calculation API

```POST /api/calculate-taxes``` - Calculate taxes for cart items

```
Request: { items: [], province: "QC", shipping: 15 }
Response: Tax breakdown and totals
```

#### Orders API

```POST /api/orders/create-pending``` - Create a pending order
```POST /api/orders/update-succeeded``` - Update order status to succeeded
```POST /api/orders/update-failed``` - Update order status to failed

#### Stripe API

```POST /api/stripe/create-payment-intent``` - Create a Stripe payment intent
```POST /api/stripe/webhook``` - Handle Stripe webhooks for payment events

#### Health & Maintenance APIs

```GET /api/health``` - Service health check endpoint
```GET /api/cron/cleanup-expired-intents``` - Cleanup expired payment intents

## Local Development

### Development Environment Setup
```bash
# Clone repository
git clone <repository-url>
cd <repository-name>

# Switch to development branch
git checkout dev

# Start development environment
sudo docker compose -f docker-compose.yml -f docker-compose.dev.yml up

# In separate terminal, start Next.js development server
npm run dev

# Start Stripe webhook listener
stripe listen --forward-to localhost:3000/api/stripe/webhook
```

### Database Management

#### Initial Database Setup

Update `package.json` for development environment:
```json
"scripts": {
    "prisma:dev": "dotenv -e .env.development -- npx prisma"
}
```

```bash
# Initialize Prisma (first-time setup)
npm run prisma:dev -- generate
npm run prisma:dev -- migrate dev --name initial_schema
```

#### Database Updates

**IMPORTANT**: When changing schema, data migration is not auto-managed if
fields are **deleted** or **renamed**! Proper data management should be created
manually in `/prisma/migrations/[timestamp]_NAME_OF_THE_CHANGE/migration.sql`

```bash
# Update schema and create migration
npm run prisma:dev -- migrate dev --name NAME_OF_THE_CHANGES --create-only

# !! Edit migration file if needed !!

# Apply the migrations
npm run prisma:dev -- migrate dev

# Regenerate Prisma client
npm run prisma:dev -- generate
```

#### Database Inspection
```bash
# Start Prisma Studio (web interface on port 5555)
npm run prisma:dev -- studio
```

### Production Database Updates
Make sure that all migration files are safe to run and wont result in data loss!
This should also be run at least ounce on production server in order to deploy the db!
```bash
# Deploy migrations to production
npx prisma migrate deploy
```

**Important Differences Between Commands**:
- `migrate dev`: Development - generates new migrations and applies them
- `migrate deploy`: Production - only applies existing migrations
- Always run `migrate dev` in development first, commit migrations, then run `migrate deploy` in production

## Production Deployment

### Server Security
- **Hardened SSHD Configs**
- **UFW**: Uncomplicated Firewall configuration
- **fail2ban**: Intrusion prevention system
- ++

### Clone Repo
- No need for frontend repos as everything will be build using Github Actions

### Point DNS to server and setup Cloudflare
- Make sure that cloudflare caching configs is set to "respect headers" (or similar)

### Setup ngnix reverse proxy
- wordpress.jardindeschefs.ca
- jardindeschefs.ca
- setup SSL with `sudo certbot --nginx`

### Production Environment
```bash
# Start production environment
sudo docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d wordpress db
# Then trigger a github action build for the nextjs app (now having access to wordpress data)
sudo docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d ntfy
# Setup ntfy (see below)
# Update .env file
sudo docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d # deploy the rest
```

#### Using GitHub Actions
- In order to bake NEXT_PUBLIC variables in the build, the keys need to be
inside the Dockerfile or GitHub secrets (available in repo settings) for non
sharable keys

- **IMPORTANT** Prisma is set to `prisma@6` if I ever upgrade to `v7` I will
need to change the version in `frontend/Dockerfile` (upgrading to `prisma@7`
needs full prisma code refactoring!)

### NTFY Deployment & Authentication

#### Prerequisites
- ntfy service already added to docker-compose.yml
- .env file with placeholder tokens

#### Step-by-Step Setup

- 1. Start ntfy Service Only
```bash
docker compose up -d ntfy

# Wait for it to be ready (check health)
docker ps | grep ntfy
```

- 2. Create Admin User (Password Protection)
```bash
# This will prompt you for a password - choose a strong one!
docker exec -it jdc-ntfy ntfy user add --role=admin admin

# Example output:
# password: ******
# confirm: ******
# user admin added with role admin
```

- 3. Generate Access Tokens for Services
```bash
# Token for Watchtower
docker exec -it jdc-ntfy ntfy token add admin watchtower

# Copy the token that starts with: tk_
# Example: tk_AgQdq7mVBoFD...

# Token for Health Monitor
docker exec -it jdc-ntfy ntfy token add admin health-monitor

# Copy this token too
```

- 4. Verify Tokens Were Created
```bash
docker exec -it jdc-ntfy ntfy token list

# Should show:
# user admin
# - watchtower, token tk_..., expires: never, accessed from N/A
# - health-monitor, token tk_..., expires: never, accessed from N/A
```

- 5. Update .env File with Real Tokens
```bash
vim .env

# Replace placeholders:
# NTFY_WATCHTOWER_TOKEN=tk_your_actual_watchtower_token_here
# NTFY_HEALTHMONITOR_TOKEN=tk_your_actual_health_monitor_token_here

# Save and exit (Ctrl+X, Y, Enter)
```

- 5.1 Update the root cron job!
```bash
sudo crontab -e

# Include token in url
# https://jardindeschefs.ca/ntfy/jdc-server?auth=tk_...

# Save and exit
```

- 6. Start Dependent Services
```bash
# Reload the full stack to pick up new token values
docker compose up -d

# Or start specific services:
docker compose up -d watchtower health-monitor
```

- 7. Test Authentication Works
```bash
# Test 1: Unauthenticated request (should FAIL)
curl -d "Test message" https://jardindeschefs.ca/ntfy/jdc-server

# Expected: {"error":"unauthorized","http":401}

# Test 2: Authenticated request (should SUCCEED)
curl -H "Authorization: Bearer tk_your_token_here" \
     -d "Authentication working! 🎉" \
     https://jardindeschefs.ca/ntfy/jdc-server

# Expected: {"id":"...", "time":..., "event":"message"}
```

- 8. Test Services Can Send Notifications
```bash
# Check watchtower can send notifications
docker logs jdc-watchtower --tail 20

# Check health-monitor can send notifications
docker logs jdc-health-monitor --tail 20

# Manually trigger a health-monitor notification
docker exec jdc-health-monitor pkill -HUP bash
```

- 9. Subscribe arch pc (Optional)
```bash
yay -S ntfysh-bin

vim ~/.config/ntfy/client.yml

default-host: https://jardindeschefs.ca/ntfy/

subscribe:
  - topic: jdc-server
    token: tk_one_of_the_token_or_a_new_one
    command: 'notify-send -i notifications "$title" "$message" -u normal -t 10000'
```

- Forgot Admin Password
```bash
# Remove user and recreate
docker exec -it jdc-ntfy ntfy user remove admin
docker exec -it jdc-ntfy ntfy user add --role=admin admin
```

- Token Format in .env
```bash
# Correct:
NTFY_WATCHTOWER_TOKEN=tk_AbCdEf123456...

# Wrong (don't use quotes):
NTFY_WATCHTOWER_TOKEN="tk_AbCdEf123456..."
```

- Token Format in docker-compose
```yaml
# Correct:
- WATCHTOWER_NOTIFICATION_SKIP_TITLE=true
- WATCHTOWER_NOTIFICATION_URL=generic+https://jardindeschefs.ca/ntfy/jdc-server?@authorization=Bearer+${NTFY_WATCHTOWER_TOKEN}
```

#### Security Notes
- Tokens never expire by default
- Keep .env file secured: `chmod 600 .env`
- Never commit tokens to git: add .env to .gitignore
- If token is compromised, remove it and generate a new one
- Default auth mode is deny-all (requires auth for everything)

### File Permissions
```bash
# Secure environment files
chmod 600 .env
```

## Maintenance and Updates

### Automated Dependency Updates (Dependabot)

The project uses GitHub Dependabot for automated dependency monitoring and updates.

#### How It Works

1. **Weekly Scans**: Dependabot checks for npm updates every Monday at 9 AM (Montreal time)
2. **Automatic PRs**: Creates pull requests for available updates
3. **Auto-Merge**: Minor and patch updates are automatically merged after CI passes
4. **Manual Review**: Major version updates require manual approval (email notification)

#### Update Flow
```
Dependabot detects update
        ↓
Creates PR → GitHub Actions builds & tests
        ↓
    ┌───┴───┐
    ↓       ↓
  Patch/Minor   Major
    ↓           ↓
Auto-merge   Comment added, manual review required
    ↓           ↓
Watchtower pulls new image automatically
```

#### Prisma Exception

Prisma is locked to v6.x and excluded from auto-updates. Upgrading to v7 requires code refactoring.
To update Prisma manually:
```bash
npm install prisma@6.x.x @prisma/client@6.x.x
```

#### Grouped Updates
To reduce PR spam, updates are grouped:
- **production-dependencies**: All prod deps (excluding Prisma)
- **development-dependencies**: Dev deps (types, eslint, prettier)

#### Config Files
- `/.github/dependabot.yml` - Dependabot schedule and rules
- `/.github/workflows/dependabot-auto-merge.yml` - Auto-merge workflow

### Container Version Strategy

| Image | Tag | Auto-Update | Manual Update |
|-------|-----|-------------|---------------|
| `nextjs` | `latest` | ✅ Watchtower | — |
| `wordpress` | `6` | ✅ Watchtower | Yearly (major) |
| `traefik` | `v3.3` | ✅ Watchtower | Yearly (minor bump) |
| `mysql` | `8.4` | ❌ | Yearly (with backup) |
| `redis` | `7-alpine` | ❌ | Yearly |
| `ntfy` | `latest` | ✅ Watchtower | — |
| `watchtower` | `latest` | ✅ Watchtower | — |

**Strategy:**
- **Lock to minor version** (e.g., `traefik:v3.3`) → receives patch updates (v3.3.1, v3.3.2) automatically
- **Databases (MySQL, Redis)** → Never auto-update, manual only with backup first
- **Yearly review** → Check for EOL versions and bump minor/major as needed

**MySQL 8.4 LTS** support ends April 2029.

### Container Updates (Bi-Annual)

Twice a year, review and update container versions:
```bash
# Check for available updates
# WordPress: https://hub.docker.com/_/wordpress/tags
# Traefik: https://hub.docker.com/_/traefik/tags
# Redis: https://hub.docker.com/_/redis/tags

# Update docker-compose files with new tags
# Then pull and restart
docker compose -f docker-compose.yml -f docker-compose.prod.yml pull
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
```

### WordPress Plugin Updates

Plugins are set to auto-update, but verify twice a year:
1. Log in to WordPress admin
2. Go to Plugins → Check for pending updates
3. Verify WooCommerce, MailPoet, and ACF are current

### Content Updates

Content is managed through the WordPress backend:
1. Log in to the WordPress admin panel
2. **Update plugins!**
3. Update pages, products, and settings
4. The Next.js frontend will fetch the updated content

### local npm updates (if needed)
1) On your local machine, update the packages:
bash
```bash
cd /path/to/your/frontend
npm audit fix
# Update to big releases
npm install next@latest
# ...
npm run build   # Make sure it works!
```

2. Commit and push:
```bash
git add package.json package-lock.json
git commit -m "Security: update Next.js and fix vulnerabilities"
git push
```

Practical maintenance schedule:
|Frequency      |Action|
|---------------|-------|
|Weekly         |Run npm audit locally, fix critical/high|
|Monthly        |Run npm outdated, update minor versions|
|Quarterly      |Review major version updates|

### Content Updates
Content is managed through the WordPress backend:

1. Log in to the WordPress admin panel
2. **Update plugins!**
3. Update pages, products, and settings
4. The Next.js frontend will fetch the updated content

### Adding New Products

1. Add products in the WooCommerce dashboard
2. Configure product details, pricing, and images
3. Set the shipping class (standard or pickup only)
4. Set tax status (taxable or exempt)

### Adding Pickup Locations

1. In WordPress, edit the "point-de-chute" page
2. Add new pickup locations with addresses and instructions
3. The frontend will fetch and display the updated locations

### Modifying Tax Rates

1. In the WooCommerce dashboard, go to Settings > Tax
2. Update tax rates for different provinces
3. The frontend will use these rates for calculations

## Next.js Cron Integration
Route in `./app/api/cron/cleanup-expired-intents/route.js` cleans up expired
ValidatedPaymentIntent records, called by the cron container using
`CRON_SECRET_KEY`.

## Future Implementations

### Shipping Calculator
The `/lib/shipping/ShippingCalculator` has a basic implementation that checks
for province flat rates and uses a default $15 rate. For full implementation,
discuss requirements with JDC.

## Releases

**Latest production build**: v1.3 (22/01/26)

### Release Process

1. **Feature Development**: Implement in `dev` branch
2. **Testing**: Merge to `test` branch and validate a build locally
3. **Production**: Merge to `main` branch
4. **Automated Deployment**: GitHub Actions + Watchtower handle the rest
5. **Monitoring**: Health checks and notifications ensure successful deployment
