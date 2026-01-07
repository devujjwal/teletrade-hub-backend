# TeleTrade Hub API Documentation

**Version:** 1.0.0  
**Owner:** Telecommunication Trading e.K.  
**Base URL:** `https://api.vs-mjrinfotech.com`  
**Last Updated:** January 7, 2026

---

## Table of Contents

1. [Introduction](#introduction)
2. [Authentication](#authentication)
3. [Response Format](#response-format)
4. [Error Handling](#error-handling)
5. [Rate Limiting](#rate-limiting)
6. [Public APIs](#public-apis)
   - [Health & System](#health--system)
   - [Authentication](#authentication-endpoints)
   - [Products](#products)
   - [Categories](#categories)
   - [Brands](#brands)
   - [Orders](#orders)
7. [Admin APIs](#admin-apis)
   - [Admin Authentication](#admin-authentication)
   - [Dashboard](#dashboard)
   - [Order Management](#order-management)
   - [Product Management](#product-management)
   - [Pricing Management](#pricing-management)
   - [Product Sync](#product-sync)
8. [Code Examples](#code-examples)

---

## Introduction

The TeleTrade Hub API provides a comprehensive REST interface for managing an e-commerce platform specializing in telecommunication products. This API supports both customer-facing operations and administrative functions.

### API Status
Check the current API status at: [https://api.vs-mjrinfotech.com/health](https://api.vs-mjrinfotech.com/health)

### Key Features
- Product catalog with advanced filtering
- Multi-language support (EN, DE)
- Secure order processing
- Admin dashboard and management
- Real-time inventory sync
- Dynamic pricing rules

---

## Authentication

### Customer Authentication
Customer endpoints do not require authentication for browsing products. However, user registration and login are available for enhanced features.

### Admin Authentication
Admin endpoints require a bearer token in the Authorization header:

```
Authorization: Bearer {admin_token}
```

Obtain the token via the `/admin/login` endpoint.

---

## Response Format

All API responses follow a consistent JSON format:

### Success Response
```json
{
  "success": true,
  "message": "Operation successful",
  "data": {
    // Response data here
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

## Error Handling

### HTTP Status Codes

| Status Code | Description |
|-------------|-------------|
| 200 | Success |
| 201 | Created |
| 400 | Bad Request - Invalid input |
| 401 | Unauthorized - Invalid credentials |
| 403 | Forbidden - Insufficient permissions |
| 404 | Not Found |
| 429 | Too Many Requests - Rate limit exceeded |
| 500 | Internal Server Error |

---

## Rate Limiting

Rate limits are enforced to ensure API stability:

| Endpoint | Limit |
|----------|-------|
| Customer Registration | 3 requests per hour |
| Customer Login | 5 requests per 15 minutes |
| Admin Login | 3 requests per 15 minutes |
| General API | 100 requests per minute |

---

## Public APIs

### Health & System

#### GET `/`
Get API information

**Example Request:**
```bash
curl https://api.vs-mjrinfotech.com/
```

**Response:**
```json
{
  "success": true,
  "message": "Welcome to TeleTrade Hub API",
  "data": {
    "name": "TeleTrade Hub API",
    "version": "1.0.0",
    "owner": "Telecommunication Trading e.K.",
    "status": "operational"
  }
}
```

---

#### GET `/health`
Health check endpoint

**Example Request:**
```bash
curl https://api.vs-mjrinfotech.com/health
```

**Response:**
```json
{
  "success": true,
  "message": "Health check completed",
  "data": {
    "status": "healthy",
    "timestamp": "2026-01-07T09:24:41+01:00",
    "checks": {
      "database": "connected",
      "storage": "writable"
    }
  }
}
```

---

### Authentication Endpoints

#### POST `/auth/register`
Register a new customer account

**Request Body:**
```json
{
  "email": "customer@example.com",
  "password": "SecurePass123!",
  "first_name": "John",
  "last_name": "Doe",
  "phone": "+491234567890"
}
```

**Validation Rules:**
- `email`: Required, valid email format
- `password`: Minimum 8 characters, must contain uppercase, lowercase, number, and special character
- `first_name`: Required
- `last_name`: Required
- `phone`: Optional

**Success Response (201):**
```json
{
  "success": true,
  "message": "Registration successful",
  "data": {
    "user": {
      "id": 1,
      "email": "customer@example.com",
      "first_name": "John",
      "last_name": "Doe",
      "phone": "+491234567890",
      "is_active": 1,
      "created_at": "2026-01-07T10:00:00Z"
    }
  }
}
```

**Error Response (400):**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "password": ["Password must be at least 8 characters and contain uppercase, lowercase, number, and special character"]
  }
}
```

---

#### POST `/auth/login`
Customer login

**Request Body:**
```json
{
  "email": "customer@example.com",
  "password": "SecurePass123!"
}
```

**Success Response:**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": 1,
      "email": "customer@example.com",
      "first_name": "John",
      "last_name": "Doe",
      "phone": "+491234567890"
    }
  }
}
```

---

### Products

#### GET `/products`
Get products list with filters and pagination

**Query Parameters:**

| Parameter | Type | Description | Default |
|-----------|------|-------------|---------|
| `lang` | string | Language code (en, de) | en |
| `page` | integer | Page number | 1 |
| `limit` | integer | Items per page (1-100) | 20 |
| `category_id` | integer | Filter by category ID | - |
| `brand_id` | integer | Filter by brand ID | - |
| `min_price` | float | Minimum price | - |
| `max_price` | float | Maximum price | - |
| `color` | string | Filter by color | - |
| `storage` | string | Filter by storage (e.g., "128GB") | - |
| `ram` | string | Filter by RAM (e.g., "8GB") | - |
| `warranty_id` | integer | Filter by warranty | - |
| `search` | string | Search query | - |
| `is_available` | boolean | 1 for available only | - |
| `is_featured` | boolean | 1 for featured only | - |
| `sort` | string | Sort field (price, name, created_at) | - |
| `order` | string | Sort order (asc, desc) | asc |

**Example Request:**
```bash
curl "https://api.vs-mjrinfotech.com/products?category_id=1&page=1&limit=20&sort=price&order=asc"
```

**Success Response:**
```json
{
  "success": true,
  "data": {
    "products": [
      {
        "id": 1,
        "sku": "IPHONE-15-PRO-MAX-256-TITANIUM",
        "name": "iPhone 15 Pro Max",
        "description": "Latest iPhone model with advanced features",
        "price": 1299.99,
        "original_price": 1199.99,
        "currency": "EUR",
        "brand_id": 1,
        "brand_name": "Apple",
        "category_id": 1,
        "category_name": "Smartphones",
        "image_url": "https://example.com/image.jpg",
        "stock_quantity": 50,
        "is_available": 1,
        "is_featured": 1,
        "color": "Natural Titanium",
        "storage": "256GB",
        "ram": "8GB",
        "warranty_months": 24,
        "created_at": "2026-01-01T00:00:00Z",
        "updated_at": "2026-01-07T00:00:00Z"
      }
    ],
    "pagination": {
      "page": 1,
      "limit": 20,
      "total": 150,
      "pages": 8
    },
    "filters": {
      "colors": ["Black", "White", "Blue", "Natural Titanium"],
      "storage": ["64GB", "128GB", "256GB", "512GB", "1TB"],
      "ram": ["4GB", "6GB", "8GB", "12GB"],
      "price_range": {
        "min": 99.99,
        "max": 1999.99
      }
    }
  }
}
```

---

#### GET `/products/{id}`
Get single product details

**Path Parameters:**
- `id` (integer, required): Product ID

**Query Parameters:**
- `lang` (string, optional): Language code

**Example Request:**
```bash
curl "https://api.vs-mjrinfotech.com/products/1?lang=en"
```

**Success Response:**
```json
{
  "success": true,
  "data": {
    "product": {
      "id": 1,
      "sku": "IPHONE-15-PRO-MAX-256-TITANIUM",
      "name": "iPhone 15 Pro Max",
      "description": "Detailed product description...",
      "price": 1299.99,
      "original_price": 1199.99,
      "currency": "EUR",
      "brand_id": 1,
      "brand_name": "Apple",
      "category_id": 1,
      "category_name": "Smartphones",
      "image_url": "https://example.com/image.jpg",
      "images": [
        "https://example.com/image1.jpg",
        "https://example.com/image2.jpg"
      ],
      "stock_quantity": 50,
      "is_available": 1,
      "is_featured": 1,
      "specifications": {
        "display": "6.7-inch Super Retina XDR",
        "processor": "A17 Pro chip",
        "camera": "48MP Main, 12MP Ultra Wide",
        "battery": "Up to 29 hours video playback"
      },
      "color": "Natural Titanium",
      "storage": "256GB",
      "ram": "8GB",
      "warranty_months": 24
    }
  }
}
```

**Error Response (404):**
```json
{
  "success": false,
  "message": "Product not found"
}
```

---

#### GET `/products/search`
Search products by query

**Query Parameters:**
- `q` (string, required): Search query
- `lang` (string, optional): Language code
- `page` (integer, optional): Page number
- `limit` (integer, optional): Items per page

**Example Request:**
```bash
curl "https://api.vs-mjrinfotech.com/products/search?q=iPhone&page=1&limit=20"
```

**Success Response:**
```json
{
  "success": true,
  "data": {
    "products": [...],
    "pagination": {...},
    "query": "iPhone"
  }
}
```

---

### Categories

#### GET `/categories`
Get all categories with product count

**Query Parameters:**
- `lang` (string, optional): Language code

**Example Request:**
```bash
curl "https://api.vs-mjrinfotech.com/categories?lang=en"
```

**Success Response:**
```json
{
  "success": true,
  "data": {
    "categories": [
      {
        "id": 1,
        "name": "Smartphones",
        "description": "Latest smartphones from top brands",
        "image_url": "https://example.com/category.jpg",
        "product_count": 50,
        "is_active": 1
      },
      {
        "id": 2,
        "name": "Tablets",
        "description": "Tablets and iPads",
        "image_url": "https://example.com/tablets.jpg",
        "product_count": 30,
        "is_active": 1
      }
    ]
  }
}
```

---

#### GET `/categories/{id}/products`
Get products by category

**Path Parameters:**
- `id` (integer, required): Category ID

**Query Parameters:**
- `lang`, `page`, `limit` (same as products list)

**Example Request:**
```bash
curl "https://api.vs-mjrinfotech.com/categories/1/products?page=1&limit=20"
```

**Success Response:**
```json
{
  "success": true,
  "data": {
    "category": {
      "id": 1,
      "name": "Smartphones",
      "description": "Latest smartphones from top brands"
    },
    "products": [...],
    "pagination": {...}
  }
}
```

---

### Brands

#### GET `/brands`
Get all brands with product count

**Example Request:**
```bash
curl "https://api.vs-mjrinfotech.com/brands"
```

**Success Response:**
```json
{
  "success": true,
  "data": {
    "brands": [
      {
        "id": 1,
        "name": "Apple",
        "description": "Apple Inc. products",
        "logo_url": "https://example.com/apple-logo.png",
        "product_count": 25,
        "is_active": 1
      },
      {
        "id": 2,
        "name": "Samsung",
        "description": "Samsung Electronics",
        "logo_url": "https://example.com/samsung-logo.png",
        "product_count": 40,
        "is_active": 1
      }
    ]
  }
}
```

---

#### GET `/brands/{id}/products`
Get products by brand

**Path Parameters:**
- `id` (integer, required): Brand ID

**Query Parameters:**
- `lang`, `page`, `limit` (same as products list)

**Example Request:**
```bash
curl "https://api.vs-mjrinfotech.com/brands/1/products?page=1&limit=20"
```

**Success Response:**
```json
{
  "success": true,
  "data": {
    "brand": {
      "id": 1,
      "name": "Apple",
      "description": "Apple Inc. products",
      "logo_url": "https://example.com/apple-logo.png"
    },
    "products": [...],
    "pagination": {...}
  }
}
```

---

### Orders

#### POST `/orders`
Create a new order

**Request Body:**
```json
{
  "user_id": 1,
  "guest_email": "guest@example.com",
  "cart_items": [
    {
      "product_id": 1,
      "quantity": 2,
      "price": 1299.99
    },
    {
      "product_id": 5,
      "quantity": 1,
      "price": 599.99
    }
  ],
  "billing_address": {
    "first_name": "John",
    "last_name": "Doe",
    "company": "Tech Solutions GmbH",
    "address_line1": "Hauptstraße 123",
    "address_line2": "Apt 4B",
    "city": "Berlin",
    "state": "Berlin",
    "postal_code": "10115",
    "country": "DE",
    "phone": "+491234567890"
  },
  "shipping_address": {
    "first_name": "Jane",
    "last_name": "Doe",
    "company": "",
    "address_line1": "Friedrichstraße 45",
    "address_line2": "",
    "city": "Berlin",
    "state": "Berlin",
    "postal_code": "10117",
    "country": "DE",
    "phone": "+491234567891"
  },
  "payment_method": "credit_card",
  "notes": "Please deliver after 6pm"
}
```

**Field Requirements:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `user_id` | integer | No | Registered user ID (omit for guest) |
| `guest_email` | string | No* | Required if user_id not provided |
| `cart_items` | array | Yes | Array of cart items |
| `billing_address` | object | Yes | Billing address details |
| `shipping_address` | object | No | Shipping address (uses billing if not provided) |
| `payment_method` | string | Yes | Payment method identifier |
| `notes` | string | No | Order notes |

**Success Response (201):**
```json
{
  "success": true,
  "message": "Order created successfully",
  "data": {
    "order": {
      "id": 123,
      "order_number": "ORD-2026-00123",
      "user_id": 1,
      "guest_email": null,
      "total_amount": 3199.97,
      "subtotal": 3199.97,
      "tax_amount": 0.00,
      "shipping_cost": 0.00,
      "discount_amount": 0.00,
      "status": "pending",
      "payment_status": "pending",
      "payment_method": "credit_card",
      "notes": "Please deliver after 6pm",
      "created_at": "2026-01-07T10:30:00Z",
      "items": [
        {
          "product_id": 1,
          "product_name": "iPhone 15 Pro Max",
          "quantity": 2,
          "price": 1299.99,
          "subtotal": 2599.98
        },
        {
          "product_id": 5,
          "product_name": "Samsung Galaxy Tab S9",
          "quantity": 1,
          "price": 599.99,
          "subtotal": 599.99
        }
      ]
    }
  }
}
```

**Error Response (400):**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "billing_address.phone": ["Phone number is required"],
    "cart_items": ["Cart is empty"]
  }
}
```

---

#### GET `/orders/{orderId}`
Get order details

**Path Parameters:**
- `orderId` (string, required): Order number (e.g., ORD-2026-00123)

**Query Parameters:**
- `user_id` (integer, optional): For registered user orders
- `guest_email` (string, optional): For guest orders

**Example Request:**
```bash
curl "https://api.vs-mjrinfotech.com/orders/ORD-2026-00123?user_id=1"
```

**Success Response:**
```json
{
  "success": true,
  "data": {
    "order": {
      "id": 123,
      "order_number": "ORD-2026-00123",
      "user_id": 1,
      "guest_email": null,
      "total_amount": 3199.97,
      "subtotal": 3199.97,
      "tax_amount": 0.00,
      "shipping_cost": 0.00,
      "discount_amount": 0.00,
      "status": "processing",
      "payment_status": "paid",
      "payment_method": "credit_card",
      "transaction_id": "TXN-456789",
      "notes": "Please deliver after 6pm",
      "billing_address": {
        "first_name": "John",
        "last_name": "Doe",
        "company": "Tech Solutions GmbH",
        "address_line1": "Hauptstraße 123",
        "address_line2": "Apt 4B",
        "city": "Berlin",
        "state": "Berlin",
        "postal_code": "10115",
        "country": "DE",
        "phone": "+491234567890"
      },
      "shipping_address": {
        "first_name": "Jane",
        "last_name": "Doe",
        "address_line1": "Friedrichstraße 45",
        "city": "Berlin",
        "postal_code": "10117",
        "country": "DE",
        "phone": "+491234567891"
      },
      "items": [
        {
          "id": 1,
          "product_id": 1,
          "product_name": "iPhone 15 Pro Max",
          "product_sku": "IPHONE-15-PRO-MAX-256",
          "quantity": 2,
          "price": 1299.99,
          "subtotal": 2599.98
        }
      ],
      "created_at": "2026-01-07T10:30:00Z",
      "updated_at": "2026-01-07T11:00:00Z"
    }
  }
}
```

**Error Response (403):**
```json
{
  "success": false,
  "message": "You do not have permission to access this order"
}
```

---

#### POST `/orders/{orderId}/payment-success`
Process successful payment callback

**Path Parameters:**
- `orderId` (string, required): Order number

**Request Body:**
```json
{
  "transaction_id": "TXN-456789"
}
```

**Success Response:**
```json
{
  "success": true,
  "message": "Payment processed successfully",
  "data": {
    "order_id": 123,
    "order_number": "ORD-2026-00123",
    "payment_status": "paid",
    "transaction_id": "TXN-456789"
  }
}
```

---

#### POST `/orders/{orderId}/payment-failed`
Process failed payment callback

**Path Parameters:**
- `orderId` (string, required): Order number

**Request Body:**
```json
{
  "reason": "Insufficient funds"
}
```

**Success Response:**
```json
{
  "success": true,
  "message": "Payment failure processed",
  "data": {
    "order_id": 123,
    "order_number": "ORD-2026-00123",
    "payment_status": "failed",
    "failure_reason": "Insufficient funds"
  }
}
```

---

## Admin APIs

**Note:** All admin endpoints require authentication. Include the bearer token in the Authorization header:

```
Authorization: Bearer {admin_token}
```

### Admin Authentication

#### POST `/admin/login`
Admin login

**Request Body:**
```json
{
  "username": "admin",
  "password": "SecureAdminPass123!"
}
```

**Success Response:**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "admin": {
      "id": 1,
      "username": "admin",
      "email": "admin@teletrade.com",
      "role": "super_admin",
      "last_login_at": "2026-01-07T09:00:00Z"
    },
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "expires_at": "2026-01-08T09:00:00Z"
  }
}
```

**Error Response (401):**
```json
{
  "success": false,
  "message": "Invalid credentials"
}
```

---

### Dashboard

#### GET `/admin/dashboard`
Get dashboard statistics

**Headers:**
```
Authorization: Bearer {admin_token}
```

**Example Request:**
```bash
curl -H "Authorization: Bearer {token}" \
  "https://api.vs-mjrinfotech.com/admin/dashboard"
```

**Success Response:**
```json
{
  "success": true,
  "data": {
    "order_stats": {
      "total_orders": 1250,
      "pending_orders": 45,
      "processing_orders": 30,
      "shipped_orders": 20,
      "delivered_orders": 1150,
      "cancelled_orders": 5,
      "total_revenue": 125000.50,
      "today_revenue": 5420.00,
      "this_month_revenue": 45000.00
    },
    "product_stats": {
      "total_products": 350,
      "available_products": 320,
      "unavailable_products": 30,
      "total_stock": 5000
    },
    "recent_orders": [
      {
        "id": 125,
        "order_number": "ORD-2026-00125",
        "customer_name": "John Doe",
        "total_amount": 1299.99,
        "status": "pending",
        "payment_status": "pending",
        "created_at": "2026-01-07T10:45:00Z"
      }
    ]
  }
}
```

---

### Order Management

#### GET `/admin/orders`
Get all orders with filters

**Headers:**
```
Authorization: Bearer {admin_token}
```

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `page` | integer | Page number |
| `limit` | integer | Items per page (1-100) |
| `status` | string | Filter by status (pending, processing, shipped, delivered, cancelled) |
| `payment_status` | string | Filter by payment status (pending, paid, failed, refunded) |
| `search` | string | Search by order number or customer name/email |

**Example Request:**
```bash
curl -H "Authorization: Bearer {token}" \
  "https://api.vs-mjrinfotech.com/admin/orders?status=pending&page=1&limit=20"
```

**Success Response:**
```json
{
  "success": true,
  "data": {
    "orders": [
      {
        "id": 123,
        "order_number": "ORD-2026-00123",
        "user_id": 1,
        "customer_name": "John Doe",
        "customer_email": "john@example.com",
        "total_amount": 3199.97,
        "status": "pending",
        "payment_status": "pending",
        "payment_method": "credit_card",
        "created_at": "2026-01-07T10:30:00Z"
      }
    ],
    "pagination": {
      "page": 1,
      "limit": 20,
      "total": 45,
      "pages": 3
    }
  }
}
```

---

#### GET `/admin/orders/{id}`
Get order detail

**Headers:**
```
Authorization: Bearer {admin_token}
```

**Path Parameters:**
- `id` (integer, required): Order ID (not order_number)

**Example Request:**
```bash
curl -H "Authorization: Bearer {token}" \
  "https://api.vs-mjrinfotech.com/admin/orders/123"
```

**Success Response:**
```json
{
  "success": true,
  "data": {
    "order": {
      "id": 123,
      "order_number": "ORD-2026-00123",
      "user_id": 1,
      "customer_name": "John Doe",
      "customer_email": "john@example.com",
      "customer_phone": "+491234567890",
      "total_amount": 3199.97,
      "subtotal": 3199.97,
      "tax_amount": 0.00,
      "shipping_cost": 0.00,
      "status": "pending",
      "payment_status": "pending",
      "payment_method": "credit_card",
      "billing_address": {...},
      "shipping_address": {...},
      "items": [...],
      "created_at": "2026-01-07T10:30:00Z",
      "updated_at": "2026-01-07T10:30:00Z"
    }
  }
}
```

---

#### PUT `/admin/orders/{id}/status`
Update order status

**Headers:**
```
Authorization: Bearer {admin_token}
Content-Type: application/json
```

**Path Parameters:**
- `id` (integer, required): Order ID

**Request Body:**
```json
{
  "status": "shipped"
}
```

**Allowed Status Values:**
- `pending`
- `processing`
- `shipped`
- `delivered`
- `cancelled`

**Success Response:**
```json
{
  "success": true,
  "message": "Order status updated",
  "data": {
    "order": {
      "id": 123,
      "order_number": "ORD-2026-00123",
      "status": "shipped",
      "updated_at": "2026-01-07T11:00:00Z"
    }
  }
}
```

---

### Product Management

#### GET `/admin/products`
Get all products (admin view)

**Headers:**
```
Authorization: Bearer {admin_token}
```

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `page` | integer | Page number |
| `limit` | integer | Items per page (1-100) |
| `lang` | string | Language code |
| `is_available` | boolean | Filter by availability (1 or 0) |
| `search` | string | Search products by name or SKU |

**Example Request:**
```bash
curl -H "Authorization: Bearer {token}" \
  "https://api.vs-mjrinfotech.com/admin/products?page=1&limit=50"
```

**Success Response:**
```json
{
  "success": true,
  "data": {
    "products": [
      {
        "id": 1,
        "sku": "IPHONE-15-PRO-MAX-256",
        "name": "iPhone 15 Pro Max",
        "brand_name": "Apple",
        "category_name": "Smartphones",
        "price": 1299.99,
        "original_price": 1199.99,
        "stock_quantity": 50,
        "is_available": 1,
        "is_featured": 1,
        "created_at": "2026-01-01T00:00:00Z",
        "updated_at": "2026-01-07T00:00:00Z"
      }
    ],
    "pagination": {
      "page": 1,
      "limit": 50,
      "total": 350,
      "pages": 7
    }
  }
}
```

---

#### PUT `/admin/products/{id}`
Update product

**Headers:**
```
Authorization: Bearer {admin_token}
Content-Type: application/json
```

**Path Parameters:**
- `id` (integer, required): Product ID

**Request Body:**
```json
{
  "is_available": 1,
  "is_featured": 1,
  "price": 1399.99
}
```

**Updatable Fields:**
- `is_available` (boolean): Product availability
- `is_featured` (boolean): Featured product flag
- `price` (float): Product price

**Success Response:**
```json
{
  "success": true,
  "message": "Product updated",
  "data": {
    "product": {
      "id": 1,
      "sku": "IPHONE-15-PRO-MAX-256",
      "name": "iPhone 15 Pro Max",
      "price": 1399.99,
      "is_available": 1,
      "is_featured": 1,
      "updated_at": "2026-01-07T11:30:00Z"
    }
  }
}
```

---

### Pricing Management

#### GET `/admin/pricing`
Get pricing configuration

**Headers:**
```
Authorization: Bearer {admin_token}
```

**Example Request:**
```bash
curl -H "Authorization: Bearer {token}" \
  "https://api.vs-mjrinfotech.com/admin/pricing"
```

**Success Response:**
```json
{
  "success": true,
  "data": {
    "global_markup": {
      "type": "percentage",
      "value": 15.0
    },
    "rules": [
      {
        "id": 1,
        "category_id": 1,
        "category_name": "Smartphones",
        "markup_type": "percentage",
        "markup_value": 20.0,
        "is_active": 1,
        "created_at": "2026-01-01T00:00:00Z"
      },
      {
        "id": 2,
        "category_id": 2,
        "category_name": "Tablets",
        "markup_type": "fixed",
        "markup_value": 50.0,
        "is_active": 1,
        "created_at": "2026-01-01T00:00:00Z"
      }
    ]
  }
}
```

---

#### PUT `/admin/pricing/global`
Update global markup

**Headers:**
```
Authorization: Bearer {admin_token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "markup_value": 18.5,
  "recalculate": true
}
```

**Parameters:**
- `markup_value` (float, required): Global markup percentage
- `recalculate` (boolean, optional): Recalculate all product prices

**Success Response (with recalculation):**
```json
{
  "success": true,
  "message": "Global markup updated and prices recalculated",
  "data": {
    "markup_value": 18.5,
    "products_updated": 350
  }
}
```

**Success Response (without recalculation):**
```json
{
  "success": true,
  "message": "Global markup updated",
  "data": {
    "markup_value": 18.5
  }
}
```

---

#### PUT `/admin/pricing/category/{id}`
Update category-specific markup

**Headers:**
```
Authorization: Bearer {admin_token}
Content-Type: application/json
```

**Path Parameters:**
- `id` (integer, required): Category ID

**Request Body:**
```json
{
  "markup_value": 25.0,
  "markup_type": "percentage"
}
```

**Parameters:**
- `markup_value` (float, required): Markup value
- `markup_type` (string, optional): Type of markup (`percentage` or `fixed`)

**Success Response:**
```json
{
  "success": true,
  "message": "Category markup updated",
  "data": {
    "category_id": 1,
    "markup_value": 25.0,
    "markup_type": "percentage"
  }
}
```

---

### Product Sync

#### POST `/admin/sync/products`
Sync products from vendor API

**Headers:**
```
Authorization: Bearer {admin_token}
```

**Query Parameters:**
- `lang` (string, optional): Language for product sync (default: 'en')

**Example Request:**
```bash
curl -X POST -H "Authorization: Bearer {token}" \
  "https://api.vs-mjrinfotech.com/admin/sync/products?lang=en"
```

**Success Response:**
```json
{
  "success": true,
  "message": "Product sync completed successfully",
  "data": {
    "products_added": 50,
    "products_updated": 200,
    "products_disabled": 10,
    "categories_synced": 15,
    "brands_synced": 25,
    "sync_duration": "45 seconds",
    "timestamp": "2026-01-07T12:00:00Z"
  }
}
```

**Error Response (500):**
```json
{
  "success": false,
  "message": "Sync failed: Unable to connect to vendor API"
}
```

---

#### GET `/admin/sync/status`
Get last sync status

**Headers:**
```
Authorization: Bearer {admin_token}
```

**Example Request:**
```bash
curl -H "Authorization: Bearer {token}" \
  "https://api.vs-mjrinfotech.com/admin/sync/status"
```

**Success Response:**
```json
{
  "success": true,
  "data": {
    "last_sync": {
      "timestamp": "2026-01-07T08:00:00Z",
      "status": "success",
      "products_synced": 350,
      "products_added": 50,
      "products_updated": 290,
      "products_disabled": 10,
      "duration": "45 seconds"
    }
  }
}
```

---

#### POST `/admin/vendor/create-sales-order`
Create sales order with vendor

**Headers:**
```
Authorization: Bearer {admin_token}
```

**Example Request:**
```bash
curl -X POST -H "Authorization: Bearer {token}" \
  "https://api.vs-mjrinfotech.com/admin/vendor/create-sales-order"
```

**Success Response:**
```json
{
  "success": true,
  "message": "Vendor sales order created successfully",
  "data": {
    "success": true,
    "vendor_order_id": "VO-123456",
    "order_date": "2026-01-07T12:00:00Z",
    "total_items": 150,
    "total_amount": 125000.00
  }
}
```

**Error Response:**
```json
{
  "success": false,
  "message": "Failed to create sales order",
  "errors": {
    "vendor_api": "Connection timeout"
  }
}
```

---

## Code Examples

### JavaScript/TypeScript (Fetch API)

#### Get Products
```javascript
async function getProducts(page = 1, categoryId = null) {
  const params = new URLSearchParams({
    page: page.toString(),
    limit: '20',
    lang: 'en'
  });
  
  if (categoryId) {
    params.append('category_id', categoryId.toString());
  }

  const response = await fetch(
    `https://api.vs-mjrinfotech.com/products?${params}`,
    {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json'
      }
    }
  );

  const data = await response.json();
  
  if (data.success) {
    return data.data;
  } else {
    throw new Error(data.message);
  }
}
```

#### Create Order
```javascript
async function createOrder(orderData) {
  const response = await fetch(
    'https://api.vs-mjrinfotech.com/orders',
    {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(orderData)
    }
  );

  const data = await response.json();
  
  if (data.success) {
    return data.data.order;
  } else {
    throw new Error(data.message);
  }
}

// Usage
const order = await createOrder({
  user_id: 1,
  cart_items: [
    { product_id: 1, quantity: 2, price: 1299.99 }
  ],
  billing_address: {
    first_name: "John",
    last_name: "Doe",
    address_line1: "123 Main St",
    city: "Berlin",
    postal_code: "10115",
    country: "DE",
    phone: "+491234567890"
  },
  payment_method: "credit_card"
});
```

#### Admin Login
```javascript
async function adminLogin(username, password) {
  const response = await fetch(
    'https://api.vs-mjrinfotech.com/admin/login',
    {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ username, password })
    }
  );

  const data = await response.json();
  
  if (data.success) {
    // Store token securely
    localStorage.setItem('admin_token', data.data.token);
    return data.data;
  } else {
    throw new Error(data.message);
  }
}
```

#### Admin Dashboard with Auth
```javascript
async function getAdminDashboard() {
  const token = localStorage.getItem('admin_token');
  
  const response = await fetch(
    'https://api.vs-mjrinfotech.com/admin/dashboard',
    {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`
      }
    }
  );

  const data = await response.json();
  
  if (response.status === 401) {
    // Token expired, redirect to login
    window.location.href = '/admin/login';
    return;
  }
  
  if (data.success) {
    return data.data;
  } else {
    throw new Error(data.message);
  }
}
```

---

### PHP (cURL)

#### Get Products
```php
<?php
function getProducts($page = 1, $categoryId = null) {
    $params = [
        'page' => $page,
        'limit' => 20,
        'lang' => 'en'
    ];
    
    if ($categoryId) {
        $params['category_id'] = $categoryId;
    }
    
    $url = 'https://api.vs-mjrinfotech.com/products?' . http_build_query($params);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if ($httpCode === 200 && $data['success']) {
        return $data['data'];
    } else {
        throw new Exception($data['message']);
    }
}
?>
```

#### Create Order
```php
<?php
function createOrder($orderData) {
    $url = 'https://api.vs-mjrinfotech.com/orders';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($orderData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if ($httpCode === 201 && $data['success']) {
        return $data['data']['order'];
    } else {
        throw new Exception($data['message']);
    }
}
?>
```

---

### Python (Requests)

#### Get Products
```python
import requests

def get_products(page=1, category_id=None):
    params = {
        'page': page,
        'limit': 20,
        'lang': 'en'
    }
    
    if category_id:
        params['category_id'] = category_id
    
    response = requests.get(
        'https://api.vs-mjrinfotech.com/products',
        params=params,
        headers={'Content-Type': 'application/json'}
    )
    
    data = response.json()
    
    if data['success']:
        return data['data']
    else:
        raise Exception(data['message'])
```

#### Admin Operations
```python
import requests

class TeleTradeAdmin:
    def __init__(self, username, password):
        self.base_url = 'https://api.vs-mjrinfotech.com'
        self.token = None
        self.login(username, password)
    
    def login(self, username, password):
        response = requests.post(
            f'{self.base_url}/admin/login',
            json={'username': username, 'password': password}
        )
        
        data = response.json()
        
        if data['success']:
            self.token = data['data']['token']
        else:
            raise Exception(data['message'])
    
    def get_headers(self):
        return {
            'Content-Type': 'application/json',
            'Authorization': f'Bearer {self.token}'
        }
    
    def get_dashboard(self):
        response = requests.get(
            f'{self.base_url}/admin/dashboard',
            headers=self.get_headers()
        )
        
        data = response.json()
        
        if data['success']:
            return data['data']
        else:
            raise Exception(data['message'])
    
    def update_order_status(self, order_id, status):
        response = requests.put(
            f'{self.base_url}/admin/orders/{order_id}/status',
            json={'status': status},
            headers=self.get_headers()
        )
        
        data = response.json()
        
        if data['success']:
            return data['data']
        else:
            raise Exception(data['message'])

# Usage
admin = TeleTradeAdmin('admin', 'password')
dashboard = admin.get_dashboard()
print(f"Total Orders: {dashboard['order_stats']['total_orders']}")
```

---

## Support and Contact

For technical support or questions about the API:

**Email:** support@teletrade.com  
**Website:** https://api.vs-mjrinfotech.com  
**Documentation Version:** 1.0.0  
**Last Updated:** January 7, 2026

---

## Legal

© 2026 Telecommunication Trading e.K. All rights reserved.

This API documentation is confidential and proprietary. Unauthorized use, reproduction, or distribution is prohibited.

