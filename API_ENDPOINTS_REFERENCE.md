# TeleTrade Hub API - Quick Reference

**Base URL:** `https://api.vs-mjrinfotech.com`

## 📋 All Endpoints Summary

### 🏥 Health & System

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/` | Get API information | ❌ |
| GET | `/health` | Health check | ❌ |

### 🔐 Authentication

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| POST | `/auth/register` | Register customer account | ❌ |
| POST | `/auth/login` | Customer login | ❌ |

### 📦 Products

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/products` | Get all products with filters | ❌ |
| GET | `/products/{id}` | Get product by ID | ❌ |
| GET | `/products/search` | Search products | ❌ |

### 🏷️ Categories

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/categories` | Get all categories | ❌ |
| GET | `/categories/{id}/products` | Get products by category | ❌ |

### 🏢 Brands

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/brands` | Get all brands | ❌ |
| GET | `/brands/{id}/products` | Get products by brand | ❌ |

### 🛒 Orders (Customer)

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| POST | `/orders` | Create new order | ❌ |
| GET | `/orders/{orderId}` | Get order details | ❌ |
| POST | `/orders/{orderId}/payment-success` | Process payment success | ❌ |
| POST | `/orders/{orderId}/payment-failed` | Process payment failure | ❌ |

### 👨‍💼 Admin - Authentication

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| POST | `/admin/login` | Admin login | ❌ |

### 📊 Admin - Dashboard

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/admin/dashboard` | Get dashboard statistics | ✅ Admin |

### 📋 Admin - Orders

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/admin/orders` | Get all orders with filters | ✅ Admin |
| GET | `/admin/orders/{id}` | Get order detail by ID | ✅ Admin |
| PUT | `/admin/orders/{id}/status` | Update order status | ✅ Admin |

### 📦 Admin - Products

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/admin/products` | Get all products (admin view) | ✅ Admin |
| PUT | `/admin/products/{id}` | Update product | ✅ Admin |

### 💰 Admin - Pricing

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/admin/pricing` | Get pricing configuration | ✅ Admin |
| PUT | `/admin/pricing/global` | Update global markup | ✅ Admin |
| PUT | `/admin/pricing/category/{id}` | Update category markup | ✅ Admin |

### 🔄 Admin - Product Sync

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| POST | `/admin/sync/products` | Sync products from vendor | ✅ Admin |
| GET | `/admin/sync/status` | Get last sync status | ✅ Admin |

### 🏪 Admin - Vendor Operations

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| POST | `/admin/vendor/create-sales-order` | Create sales order with vendor | ✅ Admin |

---

## 🔑 Authentication Details

### Admin Token Usage
```
Authorization: Bearer {admin_token}
```

All endpoints marked with "✅ Admin" require this header.

---

## 📊 Total Endpoints Count

- **Public Endpoints:** 15
- **Admin Endpoints:** 14
- **Total:** 29 endpoints

---

## 🎯 Quick Start Examples

### 1. Check API Health
```bash
curl https://api.vs-mjrinfotech.com/health
```

### 2. Get Products
```bash
curl "https://api.vs-mjrinfotech.com/products?lang=en&page=1&limit=20"
```

### 3. Search Products
```bash
curl "https://api.vs-mjrinfotech.com/products/search?q=iPhone"
```

### 4. Admin Login
```bash
curl -X POST https://api.vs-mjrinfotech.com/admin/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"your_password"}'
```

### 5. Get Dashboard (with token)
```bash
curl https://api.vs-mjrinfotech.com/admin/dashboard \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

### 6. Create Order
```bash
curl -X POST https://api.vs-mjrinfotech.com/orders \
  -H "Content-Type: application/json" \
  -d '{
    "guest_email": "customer@example.com",
    "cart_items": [{"product_id": 1, "quantity": 1, "price": 1299.99}],
    "billing_address": {
      "first_name": "John",
      "last_name": "Doe",
      "address_line1": "Street 123",
      "city": "Berlin",
      "postal_code": "10115",
      "country": "DE",
      "phone": "+491234567890"
    },
    "payment_method": "credit_card"
  }'
```

---

## 📖 Advanced Query Parameters

### Products Endpoint (`/products`)
```
?lang=en              # Language (en, de)
&page=1               # Page number
&limit=20             # Items per page (1-100)
&category_id=1        # Filter by category
&brand_id=1           # Filter by brand
&min_price=100        # Minimum price
&max_price=2000       # Maximum price
&color=Black          # Filter by color
&storage=256GB        # Filter by storage
&ram=8GB              # Filter by RAM
&search=iPhone        # Search query
&is_available=1       # Available only
&is_featured=1        # Featured only
&sort=price           # Sort by (price, name, created_at)
&order=asc            # Order (asc, desc)
```

### Admin Orders Endpoint (`/admin/orders`)
```
?page=1               # Page number
&limit=20             # Items per page
&status=pending       # Filter by status
&payment_status=paid  # Filter by payment status
&search=ORD-2026      # Search by order number or customer
```

---

## 🔄 Order Status Values

| Status | Description |
|--------|-------------|
| `pending` | Order created, awaiting processing |
| `processing` | Order being prepared |
| `shipped` | Order shipped to customer |
| `delivered` | Order delivered |
| `cancelled` | Order cancelled |

## 💳 Payment Status Values

| Status | Description |
|--------|-------------|
| `pending` | Payment not yet processed |
| `paid` | Payment successful |
| `failed` | Payment failed |
| `refunded` | Payment refunded |

---

## ⚡ Rate Limits

| Endpoint Type | Limit |
|---------------|-------|
| Customer Registration | 3 requests per hour |
| Customer Login | 5 requests per 15 minutes |
| Admin Login | 3 requests per 15 minutes |
| General API | 100 requests per minute |

---

## 🌐 Supported Languages

- `en` - English (default)
- `de` - German (Deutsch)

Add `?lang=de` to any product/category endpoint for German content.

---

## ✅ Response Format

### Success Response
```json
{
  "success": true,
  "message": "Operation successful",
  "data": {
    // Response data
  }
}
```

### Error Response
```json
{
  "success": false,
  "message": "Error description",
  "errors": {
    "field_name": ["Error message"]
  }
}
```

---

## 📱 HTTP Status Codes

| Code | Meaning |
|------|---------|
| 200 | Success |
| 201 | Created |
| 400 | Bad Request |
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Not Found |
| 429 | Rate Limit Exceeded |
| 500 | Internal Server Error |

---

## 🎯 Testing in Postman

1. **Import Collection:** `TeleTrade_Hub_API_Collection.postman_collection.json`
2. **Set Base URL:** Already configured as `https://api.vs-mjrinfotech.com`
3. **Test Health:** Start with `GET /health`
4. **Admin Login:** Use `POST /admin/login` (token auto-saves)
5. **Explore:** All endpoints are ready with sample data!

---

## 📚 Full Documentation

For detailed information, request/response examples, and code samples, see:
- `API_DOCUMENTATION.md` - Complete API documentation
- `POSTMAN_GUIDE.md` - Postman collection usage guide

---

**Owner:** Telecommunication Trading e.K.  
**Version:** 1.0.0  
**Last Updated:** January 7, 2026

