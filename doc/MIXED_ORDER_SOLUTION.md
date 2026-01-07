# Mixed Order Lifecycle Solution

## Problem Solved ✅

**Challenge:** Vendor products require reservation → vendor order workflow, while own products need direct stock management. Mixed orders complicate this.

**Solution:** Separate processing by product source with clear fulfillment tracking.

## How It Works

### 1. Order Creation

**What Happens:**
- Order items are tagged with `product_source` ('vendor' or 'own')
- Items are separated into vendor vs own groups
- Order `fulfillment_status` is set based on order composition

**Code:**
```php
// OrderService::createOrder()
- Captures product_source for each item
- Separates vendorItems[] and ownItems[]
- Sets fulfillment_status: 'pending', 'vendor_pending', or 'pending'
```

### 2. Payment Success Processing

**Vendor Products:**
1. Reserve via vendor API
2. Update item `fulfillment_status` to 'reserved'
3. Create reservation record

**Own Products:**
1. Deduct stock immediately (no reservation)
2. Update item `fulfillment_status` to 'stock_deducted'
3. Mark `stock_deducted_at` timestamp

**Mixed Orders:**
- Both processes run in parallel
- If vendor reservation fails, own stock deductions are rolled back
- Order status reflects mixed state

**Code:**
```php
// OrderService::processPaymentSuccess()
- Separates items by product_source
- Vendor: reserveOrderProducts() → reservations
- Own: reserveStock() → stock_deducted
- Updates fulfillment_status accordingly
```

### 3. Vendor Order Creation (Batch)

**What Happens:**
- Only processes vendor items
- Creates vendor sales order via API
- Updates vendor item `fulfillment_status` to 'vendor_ordered'
- Updates order `fulfillment_status`

**Code:**
```php
// OrderService::createVendorSalesOrder()
- Filters to vendor items only
- prepareVendorOrderData() excludes own items
- Updates fulfillment_status: 'vendor_fulfilled' or 'partially_fulfilled'
```

### 4. Fulfillment Status Tracking

**Order Fulfillment Status:**
- `pending` - Initial state
- `vendor_pending` - Vendor items reserved, waiting for vendor order
- `own_fulfilled` - Own items fulfilled, no vendor items
- `vendor_fulfilled` - Vendor items ordered, no own items
- `partially_fulfilled` - Mixed order, one type fulfilled
- `fulfilled` - All items fulfilled

**Item Fulfillment Status:**
- `pending` - Initial state
- `reserved` - Vendor product reserved
- `stock_deducted` - Own product stock deducted
- `vendor_ordered` - Vendor product ordered
- `shipped` - Item shipped
- `fulfilled` - Item delivered

## Database Schema Updates

### Orders Table
```sql
fulfillment_status ENUM(...) -- Tracks overall fulfillment
own_items_fulfilled_at TIMESTAMP -- When own items were fulfilled
```

### Order Items Table
```sql
fulfillment_status ENUM(...) -- Individual item status
reserved_at TIMESTAMP -- Vendor reservation time
stock_deducted_at TIMESTAMP -- Own stock deduction time
shipped_at TIMESTAMP -- Shipping time
```

## Workflow Examples

### Example 1: Pure Vendor Order
```
Order Created (fulfillment_status: 'vendor_pending')
  ↓
Payment Success
  ↓
Reserve Products (fulfillment_status: 'vendor_pending')
  ↓
Create Vendor Order (fulfillment_status: 'vendor_fulfilled')
  ↓
Shipped
```

### Example 2: Pure Own Order
```
Order Created (fulfillment_status: 'pending')
  ↓
Payment Success
  ↓
Deduct Stock (fulfillment_status: 'own_fulfilled')
  ↓
Ready to Ship
  ↓
Shipped
```

### Example 3: Mixed Order
```
Order Created (fulfillment_status: 'pending')
  ↓
Payment Success
  ├─ Vendor Items: Reserve (item status: 'reserved')
  └─ Own Items: Deduct Stock (item status: 'stock_deducted')
  ↓
(fulfillment_status: 'partially_fulfilled')
  ↓
Create Vendor Order (vendor items: 'vendor_ordered')
  ↓
(fulfillment_status: 'fulfilled' when both complete)
```

## Key Protection Mechanisms

### 1. Source Separation
- Items tagged at order creation
- Processing logic checks `product_source`
- No cross-contamination

### 2. Transaction Safety
- Vendor reservation failure → rollback own stock
- Database transactions ensure consistency
- Partial failures handled gracefully

### 3. Status Tracking
- Order-level fulfillment status
- Item-level fulfillment status
- Clear visibility into order state

### 4. API Isolation
- Vendor API only called for vendor products
- Own products never touch vendor API
- Clear separation of concerns

## Benefits

✅ **Clear Separation** - Vendor and own products handled separately
✅ **Mixed Order Support** - Handles orders with both types seamlessly
✅ **Status Tracking** - Clear visibility into fulfillment state
✅ **Transaction Safety** - Rollback on failures
✅ **No Complexity** - Each product type follows its own simple workflow
✅ **Backward Compatible** - Existing vendor-only orders work as before

## Testing Checklist

✅ **Pure Vendor Order**
- Creates order
- Reserves products
- Creates vendor order
- Updates status correctly

✅ **Pure Own Order**
- Creates order
- Deducts stock immediately
- No vendor API calls
- Updates status correctly

✅ **Mixed Order**
- Creates order with both types
- Reserves vendor products
- Deducts own stock
- Creates vendor order (vendor items only)
- Updates fulfillment status correctly
- Handles partial failures

✅ **Error Handling**
- Vendor reservation failure → rollback own stock
- Own stock failure → rollback vendor reservations
- Partial failures handled gracefully

## Summary

The solution elegantly handles mixed orders by:
1. **Separating** items by source at creation
2. **Processing** each type independently
3. **Tracking** fulfillment status at order and item level
4. **Protecting** against cross-contamination
5. **Maintaining** transaction safety

**Result:** Mixed orders work seamlessly without complexity! 🎉

