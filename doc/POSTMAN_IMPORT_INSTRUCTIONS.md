# 🚀 Postman Collection - Ready to Import!

## ✅ What Has Been Created

I've created a complete Postman collection with **29 API endpoints** organized and ready to test with your base URL: `https://api.vs-mjrinfotech.com`

### 📁 Files Created

1. **`TeleTrade_Hub_API_Collection.postman_collection.json`**
   - Complete Postman Collection (v2.1 format)
   - 29 pre-configured endpoints
   - Sample request bodies included
   - Auto-authentication for admin endpoints
   - Ready to import directly into Postman

2. **`POSTMAN_GUIDE.md`**
   - Step-by-step import instructions
   - How to use the collection
   - Authentication guide
   - Testing workflow
   - Troubleshooting tips

3. **`API_ENDPOINTS_REFERENCE.md`**
   - Quick reference table of all endpoints
   - cURL examples
   - Query parameters guide
   - Status codes reference

---

## 🎯 Quick Start (3 Steps)

### Step 1: Open Postman
Launch your Postman application

### Step 2: Import Collection
1. Click **"Import"** button (top left)
2. Select file: **`TeleTrade_Hub_API_Collection.postman_collection.json`**
3. Click **"Import"**

### Step 3: Start Testing!
✅ Collection is ready with base URL: `https://api.vs-mjrinfotech.com`

---

## 📦 What's Included in the Collection

### Public APIs (15 endpoints)
- ✅ Health & System (2)
- ✅ Authentication (2)
- ✅ Products with Advanced Filters (3)
- ✅ Categories (2)
- ✅ Brands (2)
- ✅ Orders & Payment (4)

### Admin APIs (14 endpoints) 🔐
- ✅ Admin Login (auto-saves token)
- ✅ Dashboard Statistics
- ✅ Order Management (3)
- ✅ Product Management (2)
- ✅ Pricing Configuration (3)
- ✅ Product Sync (2)
- ✅ Vendor Operations (1)

---

## 🔥 Special Features

### 1. **Auto-Authentication**
- Admin login automatically saves token
- Token is reused for all admin endpoints
- No manual copy/paste needed!

### 2. **Pre-configured Base URL**
- Already set to: `https://api.vs-mjrinfotech.com`
- Change once in collection variables if needed

### 3. **Sample Data Included**
- All POST/PUT requests have example bodies
- Just click "Send" to test
- Modify as needed for your tests

### 4. **Query Parameters Ready**
- All optional parameters documented
- Enable/disable checkboxes as needed
- Descriptions included

### 5. **Organized Structure**
- Logical folder organization
- Easy navigation
- Clear descriptions

---

## 🧪 Recommended Testing Flow

### 1️⃣ Verify API Health
```
GET /health
```
**Expected:** Status 200, API operational

### 2️⃣ Browse Products
```
GET /products?lang=en&page=1&limit=20
```
**Expected:** Product list with pagination

### 3️⃣ Search Products
```
GET /products/search?q=iPhone
```
**Expected:** Filtered product results

### 4️⃣ View Categories & Brands
```
GET /categories
GET /brands
```
**Expected:** Lists with product counts

### 5️⃣ Create Test Order
```
POST /orders
```
**Expected:** Order created with order number

### 6️⃣ Admin Login
```
POST /admin/login
```
**Expected:** Token auto-saved, ready for admin operations

### 7️⃣ View Dashboard
```
GET /admin/dashboard
```
**Expected:** Statistics (orders, revenue, products)

### 8️⃣ Manage Orders
```
GET /admin/orders
PUT /admin/orders/{id}/status
```
**Expected:** Order list and status update

### 9️⃣ Sync Products
```
POST /admin/sync/products?lang=en
```
**Expected:** Product sync results

### 🔟 Product Management
```
GET /admin/products
PUT /admin/products/{id}
GET /admin/pricing
```
**Expected:** Product list, updates, pricing config

---

## 💡 Pro Tips

### Tip 1: Collection Variables
Access via: Collection → Variables tab
- `base_url`: Change if testing different environment
- `admin_token`: Auto-populated, but can be set manually

### Tip 2: Environment Variables (Alternative)
Create different environments for:
- Development: `http://localhost:8000`
- Staging: `https://staging.api.example.com`
- Production: `https://api.vs-mjrinfotech.com`

### Tip 3: Test Scripts
The Admin Login already has a test script. Add more for:
- Response validation
- Data extraction
- Automated workflows

### Tip 4: Bulk Testing
Use Collection Runner to:
- Run all endpoints sequentially
- Test complete workflows
- Generate test reports

### Tip 5: Save Responses
Postman can save example responses:
- Click "Save Response"
- Choose "Save as Example"
- Document API behavior

---

## 🔐 Admin Authentication

### Automatic (Recommended)
1. Open: **Admin → Authentication → Admin Login**
2. Update credentials in body:
```json
{
  "username": "admin",
  "password": "your_actual_password"
}
```
3. Click **"Send"**
4. Token is automatically saved!
5. All admin endpoints now work

### Manual (If needed)
1. Copy token from login response: `data.token`
2. Go to Collection Variables
3. Paste into `admin_token` variable
4. Save

---

## 📊 Endpoint Categories

### Category 1: System (No Auth)
- GET `/` - API Info
- GET `/health` - Health Check

### Category 2: Products (No Auth)
- GET `/products` - List with filters
- GET `/products/{id}` - Single product
- GET `/products/search` - Search

### Category 3: Categories & Brands (No Auth)
- GET `/categories`
- GET `/categories/{id}/products`
- GET `/brands`
- GET `/brands/{id}/products`

### Category 4: Orders (No Auth)
- POST `/orders` - Create
- GET `/orders/{orderId}` - View
- POST `/orders/{orderId}/payment-success`
- POST `/orders/{orderId}/payment-failed`

### Category 5: Admin (Auth Required) 🔒
- POST `/admin/login`
- GET `/admin/dashboard`
- GET `/admin/orders`
- GET `/admin/orders/{id}`
- PUT `/admin/orders/{id}/status`
- GET `/admin/products`
- PUT `/admin/products/{id}`
- GET `/admin/pricing`
- PUT `/admin/pricing/global`
- PUT `/admin/pricing/category/{id}`
- POST `/admin/sync/products`
- GET `/admin/sync/status`
- POST `/admin/vendor/create-sales-order`

---

## ⚠️ Important Notes

### 1. Order ID Confusion
- **Customer endpoints** use `order_number`: `ORD-2026-00123`
- **Admin endpoints** use `id`: `123`
- Don't mix them up!

### 2. Rate Limits
Watch out for:
- Registration: 3/hour
- Login: 5 per 15 min
- Admin Login: 3 per 15 min
- General: 100/minute

### 3. Language Support
Add `?lang=de` for German content on:
- Products
- Categories
- Product Details

### 4. Guest vs User Orders
- **Guest**: Requires `guest_email`
- **Registered**: Requires `user_id`
- Use the correct endpoint variant

### 5. Pagination
Default: 20 items per page
Max: 100 items per page
Always check `pagination` object in response

---

## 🎨 Advanced Product Filtering

The `/products` endpoint supports extensive filtering:

```
GET /products?
  lang=en                    # Language
  &page=1                    # Pagination
  &limit=20                  
  &category_id=1             # Filter by category
  &brand_id=2                # Filter by brand
  &min_price=500             # Price range
  &max_price=2000
  &color=Black               # Specs
  &storage=256GB
  &ram=8GB
  &search=iPhone             # Search
  &is_available=1            # Availability
  &is_featured=1             # Featured
  &sort=price                # Sorting
  &order=asc
```

**Enable what you need in Postman by checking the checkboxes!**

---

## 🐛 Troubleshooting

### Problem: 401 Unauthorized on Admin Endpoints
**Solution:**
1. Run `POST /admin/login`
2. Verify token saved in Variables
3. Try admin endpoint again

### Problem: 404 Not Found
**Solution:**
1. Check base URL is correct: `https://api.vs-mjrinfotech.com`
2. Verify endpoint path (no extra slashes)
3. For parameterized routes, replace `{id}` with actual ID

### Problem: 400 Bad Request
**Solution:**
1. Check request body format (JSON)
2. Verify all required fields present
3. Check field types (string, number, etc.)

### Problem: 500 Internal Server Error
**Solution:**
1. Check if products are synced: `POST /admin/sync/products`
2. Verify database connection: `GET /health`
3. Check backend logs

### Problem: No Response
**Solution:**
1. Verify API is running: `GET /health`
2. Check network connection
3. Confirm base URL is accessible

---

## 📚 Additional Resources

### Documentation Files
1. **`API_DOCUMENTATION.md`**
   - Complete API reference
   - Request/response examples
   - Code samples (PHP, JavaScript, Python)
   - Error codes and handling

2. **`POSTMAN_GUIDE.md`**
   - Detailed import instructions
   - Configuration guide
   - Testing workflows
   - Use cases

3. **`API_ENDPOINTS_REFERENCE.md`**
   - Quick reference table
   - All endpoints at a glance
   - cURL examples
   - Status codes

### Backend Documentation
- `teletrade-hub-backend/README.md`
- `teletrade-hub-backend/TESTING.md`
- `teletrade-hub-backend/DEPLOYMENT_CHECKLIST.md`

---

## 🎯 Success Checklist

Before you start, verify:

- [ ] Postman installed and running
- [ ] Collection file located: `TeleTrade_Hub_API_Collection.postman_collection.json`
- [ ] API is accessible: `https://api.vs-mjrinfotech.com`
- [ ] Admin credentials available
- [ ] Ready to test!

After import, verify:

- [ ] Collection appears in Postman
- [ ] Base URL is correct (`https://api.vs-mjrinfotech.com`)
- [ ] All 29 endpoints visible
- [ ] Folder structure organized
- [ ] Health check works: `GET /health`

---

## 🚀 You're Ready!

**Everything is configured and ready to use!**

1. ✅ **Import:** `TeleTrade_Hub_API_Collection.postman_collection.json`
2. ✅ **Test:** Start with `GET /health`
3. ✅ **Authenticate:** `POST /admin/login` for admin access
4. ✅ **Explore:** All 29 endpoints ready with sample data

### Quick Import Link
```
File Location: 
/Users/Ujjwal.Paul/Desktop/ET_Repos/teletrade-hub/TeleTrade_Hub_API_Collection.postman_collection.json
```

---

## 📞 Need Help?

- **Full API Docs:** See `API_DOCUMENTATION.md`
- **Usage Guide:** See `POSTMAN_GUIDE.md`
- **Quick Reference:** See `API_ENDPOINTS_REFERENCE.md`
- **Backend Issues:** Check `teletrade-hub-backend/README.md`

---

**Happy Testing! 🎉**

Base URL: `https://api.vs-mjrinfotech.com`  
Version: 1.0.0  
Total Endpoints: 29  
Owner: Telecommunication Trading e.K.

