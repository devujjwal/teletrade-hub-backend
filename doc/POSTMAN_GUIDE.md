# Postman Collection Import Guide

## 📦 Collection File
**File:** `TeleTrade_Hub_API_Collection.postman_collection.json`

## 🚀 How to Import

### Step 1: Open Postman
1. Launch Postman application
2. Click on **"Import"** button in the top left corner

### Step 2: Import Collection
1. Click **"Upload Files"** or drag and drop the collection file
2. Select: `TeleTrade_Hub_API_Collection.postman_collection.json`
3. Click **"Import"**

### Step 3: Verify Import
- You should see **"TeleTrade Hub API"** collection in your collections list
- The collection includes 40+ pre-configured API requests organized in folders

## 🔧 Configuration

### Collection Variables
The collection comes with pre-configured variables:

| Variable | Default Value | Description |
|----------|---------------|-------------|
| `base_url` | `https://api.vs-mjrinfotech.com` | API base URL |
| `admin_token` | (empty) | Auto-populated after admin login |

### Changing Base URL (if needed)
1. Click on the collection name **"TeleTrade Hub API"**
2. Go to **"Variables"** tab
3. Update the `base_url` value
4. Click **"Save"**

## 📚 Collection Structure

### 1. **Health & System** (2 endpoints)
- Get API Info
- Health Check

### 2. **Authentication** (2 endpoints)
- Customer Register
- Customer Login

### 3. **Products** (3 endpoints)
- Get All Products (with advanced filters)
- Get Product by ID
- Search Products

### 4. **Categories** (2 endpoints)
- Get All Categories
- Get Products by Category

### 5. **Brands** (2 endpoints)
- Get All Brands
- Get Products by Brand

### 6. **Orders** (6 endpoints)
- Create Order (Registered User)
- Create Order (Guest)
- Get Order Details (User)
- Get Order Details (Guest)
- Payment Success Callback
- Payment Failed Callback

### 7. **Admin** (16+ endpoints organized in sub-folders)
- **Authentication**
  - Admin Login (auto-saves token)
- **Dashboard**
  - Get Dashboard Stats
- **Orders**
  - Get All Orders
  - Get Order Detail
  - Update Order Status
- **Products**
  - Get All Products
  - Update Product
- **Pricing**
  - Get Pricing Configuration
  - Update Global Markup
  - Update Category Markup
- **Product Sync**
  - Sync Products
  - Get Sync Status
- **Vendor Operations**
  - Create Sales Order with Vendor

## 🔐 Authentication

### For Admin Endpoints

#### Method 1: Automatic (Recommended)
1. Navigate to: **Admin → Authentication → Admin Login**
2. Update the request body with your credentials
3. Click **"Send"**
4. The token is **automatically saved** to the `admin_token` variable
5. All subsequent admin requests will use this token automatically

#### Method 2: Manual
1. Copy the token from login response
2. Go to Collection Variables
3. Paste it into the `admin_token` variable
4. Save the collection

### For Customer Endpoints
- Most endpoints don't require authentication
- Register/Login endpoints are available for user account management

## 🧪 Testing Workflow

### 1. Start with Health Check
```
GET {{base_url}}/health
```
✅ Verify API is operational

### 2. Browse Products
```
GET {{base_url}}/products?lang=en&page=1&limit=20
```
✅ View available products

### 3. Create an Order
```
POST {{base_url}}/orders
```
✅ Test order creation (use provided sample data)

### 4. Admin Login
```
POST {{base_url}}/admin/login
```
✅ Get admin access (token auto-saved)

### 5. Admin Dashboard
```
GET {{base_url}}/admin/dashboard
```
✅ View statistics (requires admin token)

### 6. Manage Orders
```
GET {{base_url}}/admin/orders
PUT {{base_url}}/admin/orders/{id}/status
```
✅ View and update orders

### 7. Sync Products
```
POST {{base_url}}/admin/sync/products?lang=en
```
✅ Sync products from vendor

## 📝 Quick Tips

### Using Query Parameters
- **Enabled parameters** are sent with the request
- **Disabled parameters** are shown as examples but not sent
- Enable/disable by checking/unchecking the checkbox

### Request Examples
All requests include:
- ✅ Pre-filled sample data in request bodies
- ✅ Proper headers (Content-Type, Authorization)
- ✅ Query parameter descriptions
- ✅ Endpoint descriptions

### Advanced Filtering (Products)
The "Get All Products" endpoint supports extensive filtering:
- Category, Brand, Price Range
- Color, Storage, RAM
- Search, Availability, Featured
- Sorting and Pagination

**Enable the filters you need by checking their checkboxes!**

## 🔄 Auto-Save Token Feature

The **Admin Login** request includes a test script that automatically:
1. Captures the token from the response
2. Saves it to the `admin_token` collection variable
3. Makes it available for all admin endpoints

**You don't need to manually copy/paste tokens!**

## 🎯 Common Use Cases

### Use Case 1: Product Browsing
1. `GET /products` - Browse all products
2. `GET /categories` - View categories
3. `GET /categories/{id}/products` - Filter by category
4. `GET /products/{id}` - View product details

### Use Case 2: Order Flow
1. `POST /auth/register` - Register customer (optional)
2. `POST /orders` - Create order
3. `GET /orders/{orderId}` - Track order
4. `POST /orders/{orderId}/payment-success` - Confirm payment

### Use Case 3: Admin Management
1. `POST /admin/login` - Login (token auto-saved)
2. `GET /admin/dashboard` - View stats
3. `GET /admin/orders` - Manage orders
4. `PUT /admin/orders/{id}/status` - Update status
5. `POST /admin/sync/products` - Sync products

### Use Case 4: Product Management
1. `POST /admin/login` - Login as admin
2. `GET /admin/products` - View all products
3. `PUT /admin/products/{id}` - Update product
4. `GET /admin/pricing` - View pricing rules
5. `PUT /admin/pricing/global` - Update markup

## 🌍 Multi-Language Support

The API supports multiple languages:
- `lang=en` - English (default)
- `lang=de` - German

Add the `lang` query parameter to product/category requests.

## ⚠️ Important Notes

1. **Order ID vs Order Number**
   - Customer endpoints use: `order_number` (e.g., ORD-2026-00123)
   - Admin endpoints use: `id` (e.g., 123)

2. **Rate Limiting**
   - Customer Registration: 3 requests/hour
   - Customer Login: 5 requests/15 minutes
   - Admin Login: 3 requests/15 minutes
   - General API: 100 requests/minute

3. **Guest vs Registered Orders**
   - Guest orders require `guest_email`
   - Registered user orders require `user_id`
   - Use the appropriate endpoint variant

4. **Admin Authentication**
   - All admin endpoints require Bearer token
   - Token is automatically included if you use the login endpoint
   - Token expires after 24 hours

## 🛠️ Troubleshooting

### Problem: "Unauthorized" on Admin Endpoints
**Solution:** Run the "Admin Login" request first to get a valid token

### Problem: "Product not found"
**Solution:** Check if products are synced. Run: `POST /admin/sync/products`

### Problem: "Validation failed"
**Solution:** Check the request body format matches the examples

### Problem: "Rate limit exceeded"
**Solution:** Wait for the rate limit window to reset (see limits above)

## 📧 Support

For API issues or questions:
- **Email:** support@teletrade.com
- **API Status:** https://api.vs-mjrinfotech.com/health
- **Documentation:** See API_DOCUMENTATION.md

---

## 🎉 You're All Set!

Your Postman collection is ready to use with:
- ✅ **Base URL:** https://api.vs-mjrinfotech.com
- ✅ **40+ Pre-configured endpoints**
- ✅ **Sample request bodies**
- ✅ **Auto-authentication for admin**
- ✅ **Organized folder structure**

**Happy Testing! 🚀**

