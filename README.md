# SITE WEB - LE JARDIN DES CHEFS

Documentation pour le site web de la compagnie [jardindeschefs.ca](https://jardindeschefs.ca)

## Technology Stack

- Frontend: Next.js 13+ (App Router), React 18
- Content Management: Headless WordPress with WooCommerce
- Payment Processing: Stripe
- Styling: CSS Modules
- Deployment: Digital Ocean Droplet (planned)

### Frontend

- [Next.js](https://nextjs.org)
- [mui](https://mui.com/) components
- [Stripe](https://dashboard.stripe.com/) Payment Gateway integration and Weebhooks

### Backend

- [Wordpress](https://wordpress.org/) as headless CMS
- MySQL/Prisma local Database
- WordPress/WooCommerce

### Deployment

- Digital Ocean Droplet
- Weekly backups

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

## Deployment

### Development
```bash
sudo docker compose -f docker-compose.yml -f docker-compose.dev.yml up

npm run dev

stripe listen --forward-to localhost:3000/api/stripe/webhook
```

#### How to create local Database

In order to use .env.development instead of the default .env, update ```package.js``` with this:
```json
"scripts": {
    "prisma:dev": "dotenv -e .env.development -- npx prisma"
},
```
Then you can use these commands:
```bash
# Initialize Prisma (first-time setup)
npm run prisma:dev -- generate

npm run prisma:dev -- migrate dev --name initial_schema
```

#### How to update local Database

__IMPORTANT__

When changing schema, data migration is not auto-managed if fields are __deleted__ or __renamed__!
Proper data management should be created manually in ```/prisma/migrations/[timestamp]_NAME_OF_THE_CHANGE/migration.sql```

```bash
# In dev mode "npx prisma" should be replaced by
npm run prisma:dev -- [REST OF COMMAND]
# as I defined it in node settings
```

```bash
# Update schema and migrate changes (If changes made to prisma/shema.prisma)
npx prisma migrate dev --name NAME_OF_THE_CHANGES --create-only

# !! Edit migration file if needed !!

# Apply the migrations
# (Prisma detects that there's a pending (unapplied) migration and applies it.
# It doesn't create a new migration file.)
npx prisma migrate dev

# regenerate the prisma client
npm run prisma:dev -- generate
```

#### Inspecting the local Database
```bash
# Starts a web server (usually on port 5555) http://localhost:5555
npx prisma studio

# or

npm run prisma:dev -- studio
```

### Production
```bash
sudo docker compose -f docker-compose.yml -f docker-compose.prod.yml up
```

#### Update local Database in production

Make sure that all migration files are safe to run and wont result in data loss!
This should also be run at least ounce on production server in order to deploy the db!
```bash
npx prisma migrate deploy
```

##### Important Differences Between Commands

- ```migrate dev``` is for development: it generates new migrations and applies them
- ```migrate deploy``` is for production: it only applies existing migrations
- Always run ```migrate dev``` in development first, commit the migrations, then run ```migrate deploy``` in production

### .env files
- frontend/.env
- frontend/.env.development
- frontend/.env.production
- backend/.env

## Maintenance and Updates

### Content Updates
Content is managed through the WordPress backend:

1. Log in to the WordPress admin panel
2. Update pages, products, and settings
3. The Next.js frontend will fetch the updated content

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

## Important Security Checks

### File permisisons
```
chmod 600 .env*
```

### Server security
- ufw
- fail2ban

### Shipping and shop future implementations
The /lib/shipping/ShippingCalculator has a basic implementation.
Right now, it checks for the province flat rate and use that. If no flat rate found, it uses the default 15$
For real implementation, would need much more, but should discuss the need with JDC.

### nextjs cron
There is a route in ```./app/api/cron/cleanup-expired-intents/route.js``` that
is used to cleanup ValidatedPaymentIntent that have expired

It is being called using `CRON_SECRET_KEY` in the header by the cron container.

### Deployment

#### Using GitHub Actions
- In order to bake NEXT_PUBLIC variables in the build, the keys need to be
inside the Dockerfile or GitHub secrets (available in repo settings) for non
sharable keys

## Relases
Latest production build v1.1 31/05/25
