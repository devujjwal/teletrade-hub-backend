# TeleTrade Hub - Backend API

**Production-grade e-commerce platform backend**  
**Owner:** Telecommunication Trading e.K.

## Technology Stack

- **PHP:** 8.3
- **Database:** MySQL (via PDO)
- **Architecture:** MVC with service layer
- **API Style:** REST
- **Authentication:** JWT tokens
- **Hosting:** cPanel with Git auto-deploy

## Project Structure

```
├── public/              # Web root (document root points here)
│   ├── index.php       # Entry point
│   └── .htaccess       # Apache configuration
├── app/
│   ├── Config/         # Configuration files
│   ├── Controllers/    # Request handlers
│   ├── Services/       # Business logic
│   ├── Models/         # Data models
│   ├── Middlewares/    # Request/response filters
│   └── Utils/          # Helper utilities
├── routes/
│   └── api.php         # Route definitions
├── database/
│   └── migrations.sql  # Database schema
├── storage/
│   └── logs/           # Application logs
├── .env.example        # Environment template
└── README.md
```

## Setup Instructions

### 1. Environment Configuration

Copy `.env.example` to `.env` and configure:

```bash
cp .env.example .env
```

Update the following variables:
- `DB_PASSWORD` - Your database password
- `JWT_SECRET` - Generate a strong secret key
- `VENDOR_API_KEY` - TRIEL API key
- `CORS_ALLOWED_ORIGINS` - Frontend URL

### 2. Database Setup

Import the database schema:

```bash
mysql -u vsmjr110_ujjwal -p vsmjr110_api < database/migrations.sql
```

### 3. Permissions

Ensure `storage/logs` is writable:

```bash
chmod -R 775 storage/logs
```

### 4. cPanel Configuration

- **Document Root:** Point to `/public` directory
- **Git Deployment:** Connected to repository
- **PHP Version:** 8.3

## API Endpoints

### Public Endpoints

- `GET /` - API information
- `GET /health` - Health check
- `GET /products` - List products with filters
- `GET /products/{id}` - Product details
- `GET /categories` - List categories
- `GET /brands` - List brands
- `POST /orders` - Create order
- `GET /orders/{id}` - Order details
- `POST /auth/login` - Customer login
- `POST /auth/register` - Customer registration

### Admin Endpoints (Require Auth)

- `POST /admin/login` - Admin login
- `GET /admin/dashboard` - Dashboard statistics
- `GET /admin/orders` - List all orders
- `PUT /admin/orders/{id}/status` - Update order status
- `GET /admin/products` - List products
- `PUT /admin/products/{id}` - Update product
- `GET /admin/pricing` - Get pricing configuration
- `PUT /admin/pricing/global` - Update global markup
- `POST /admin/sync/products` - Sync vendor products
- `POST /admin/vendor/create-sales-order` - Create vendor sales order

## Security Features

- ✅ Prepared statements (SQL injection prevention)
- ✅ Input validation and sanitization
- ✅ JWT authentication
- ✅ CORS configuration
- ✅ Rate limiting (recommended via server)
- ✅ Secure headers
- ✅ Environment-based configuration
- ✅ Error logging

## Vendor Integration (TRIEL)

The platform integrates with TRIEL B2B API:

- **Stock Sync:** Periodic synchronization of product catalog
- **Reservations:** Reserve articles during checkout
- **Sales Orders:** Daily consolidated order submission
- **Multi-language:** Support for DE, EN, SK

### Sync Schedule

- **Auto Sync:** Daily at 02:00 (cron job)
- **Manual Sync:** Admin panel trigger

## Database Schema

Key tables:
- `products` - Product catalog
- `categories` - Product categories
- `brands` - Product brands
- `orders` - Customer orders
- `order_items` - Order line items
- `reservations` - Vendor reservations
- `users` - Customer accounts
- `pricing_rules` - Markup configuration

## Deployment

### Automatic Deployment (cPanel)

Push to the repository triggers automatic deployment:

```bash
git add .
git commit -m "Your commit message"
git push origin main
```

### Manual Sync

If needed, manually pull in cPanel:

```bash
cd /home/vsmjr110/public_html/api
git pull origin main
```

## Logging

Logs are stored in `storage/logs/`:
- `app.log` - Application logs
- `php_errors.log` - PHP errors

## Support

For issues or questions, contact the development team.

---

**© 2024 Telecommunication Trading e.K. - All rights reserved**

