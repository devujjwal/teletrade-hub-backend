# Customer-Facing API Design

## Principle: Seamless Unified Experience

**Customer sees:** One unified order, no distinction between vendor and own products.

**Backend handles:** Separate processing for vendor vs own products transparently.

## Hidden Internal Fields

The following fields are **never exposed** to customers:

### Order Level
- ❌ `fulfillment_status` - Internal tracking
- ❌ `vendor_order_id` - Vendor backend reference
- ❌ `vendor_order_created_at` - Internal timestamp
- ❌ `own_items_fulfilled_at` - Internal timestamp
- ❌ `admin_notes` - Admin-only notes

### Order Items Level
- ❌ `product_source` - Vendor vs own distinction
- ❌ `fulfillment_status` - Item-level fulfillment tracking
- ❌ `reserved_at` - Vendor reservation timestamp
- ❌ `stock_deducted_at` - Own stock deduction timestamp
- ❌ `vendor_article_id` - Internal vendor SKU

## Customer-Facing Order Response

### Order Creation Response
```json
{
  "status": "success",
  "data": {
    "order_id": 123,
    "order_number": "TT241201ABC123",
    "total": 299.99,
    "status": "pending",
    "message": "Order created successfully. Please proceed with payment."
  }
}
```

### Order Details Response (Customer)
```json
{
  "status": "success",
  "data": {
    "order": {
      "id": 123,
      "order_number": "TT241201ABC123",
      "status": "processing",
      "payment_status": "paid",
      "subtotal": 250.00,
      "tax": 47.50,
      "shipping_cost": 9.99,
      "total": 299.99,
      "currency": "EUR",
      "items": [
        {
          "id": 1,
          "product_id": 10,
          "product_name": "iPhone 16 Pro",
          "product_sku": "SKU-001",
          "quantity": 1,
          "price": 150.00,
          "subtotal": 150.00
        },
        {
          "id": 2,
          "product_id": 20,
          "product_name": "Custom Product",
          "product_sku": "OWN-001",
          "quantity": 2,
          "price": 50.00,
          "subtotal": 100.00
        }
      ],
      "billing_address": { ... },
      "shipping_address": { ... },
      "created_at": "2024-12-01T10:00:00Z"
    }
  }
}
```

**Note:** Customer cannot tell which items are vendor vs own - all appear identical.

## Admin-Facing Order Response

Admins see **full details** including:
- ✅ `fulfillment_status` - Overall fulfillment state
- ✅ `product_source` - Vendor vs own for each item
- ✅ `fulfillment_status` per item
- ✅ `vendor_order_id` - Vendor backend reference
- ✅ `reserved_at`, `stock_deducted_at` - Processing timestamps
- ✅ `admin_notes` - Internal notes

## Order Processing Flow (Customer Perspective)

### Customer Experience:
1. **Add to Cart** - All products look the same
2. **Checkout** - Single unified checkout
3. **Payment** - One payment for entire order
4. **Confirmation** - "Order placed successfully"
5. **Tracking** - Single order status (no distinction)

### Backend Processing (Hidden):
1. **Order Created** - Items tagged by source internally
2. **Payment Success**:
   - Vendor items → Reserve via API
   - Own items → Deduct stock directly
3. **Fulfillment**:
   - Vendor items → Create vendor order (batch)
   - Own items → Ready to ship immediately
4. **Shipping**:
   - Vendor items → Wait for vendor shipment
   - Own items → Ship from warehouse
5. **Delivery** - Customer receives all items together

## API Endpoints

### Customer Endpoints (Sanitized)
- `POST /api/orders` - Create order
- `GET /api/orders/{orderNumber}` - Get order (sanitized)
- `POST /api/orders/{orderNumber}/payment-success` - Payment callback

**Response:** No internal fields exposed

### Admin Endpoints (Full Details)
- `GET /api/admin/orders` - List orders (full details)
- `GET /api/admin/orders/{id}` - Get order (full details)
- `PUT /api/admin/orders/{id}/status` - Update status

**Response:** All internal fields included

## Implementation

### OrderService::getOrderDetails()
```php
public function getOrderDetails($orderId, $isAdmin = false)
{
    $order = $this->orderModel->getFullOrder($orderId);
    
    // Sanitize for customers
    if (!$isAdmin) {
        $order = $this->sanitizeOrderForCustomer($order);
    }
    
    return $order;
}
```

### OrderController::show()
```php
// Customer endpoint - sanitized
$fullOrder = $this->orderService->getOrderDetails($order['id'], false);
```

### AdminController::orderDetail()
```php
// Admin endpoint - full details
$order = $this->orderService->getOrderDetails($id, true);
```

## Benefits

✅ **Unified Experience** - Customer sees one seamless order
✅ **No Confusion** - No technical details exposed
✅ **Backend Flexibility** - Process vendor/own differently internally
✅ **Admin Visibility** - Admins see full details for management
✅ **Security** - Internal fields not exposed to customers

## Summary

- **Customer:** Places order → Pays → Receives confirmation → Tracks order
- **Backend:** Handles vendor/own distinction transparently
- **Admin:** Sees full details for order management
- **Result:** Seamless experience, no complexity exposed to customer

