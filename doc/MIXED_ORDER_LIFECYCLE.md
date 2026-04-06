# Mixed Order Lifecycle Design

## Problem Statement

**Vendor Products Lifecycle:**
1. Create order
2. Payment success
3. **Reserve** via vendor API
4. Create **vendor sales order** (batch at end of day)
5. Vendor ships

**Own Products Lifecycle:**
1. Create order
2. Payment success
3. **Direct stock deduction** (no reservation)
4. **Immediate fulfillment** (no vendor order)
5. Ship from own warehouse

**Mixed Orders Challenge:**
- Order contains both vendor and own products
- Different processing workflows
- Need to handle partial fulfillment
- Order status must reflect mixed state

## Solution: Separate Processing by Product Source

### Order Status Flow for Mixed Orders

```
pending → [reserved | processing] → [vendor_ordered | fulfilled | partially_fulfilled | cancelled]
```

### Key Design Principles

1. **Separate vendor items from own items** at order creation
2. **Reserve vendor products immediately** when the order is placed successfully
3. **Hold own stock immediately** for own products
4. **Process vendor orders separately** (batch at end of day)
5. **Track fulfillment status** per item type
6. **Release both vendor and own stock** when an order is cancelled before shipment

## Implementation Strategy

### Phase 1: Order Creation
- Capture `product_source` in order_items
- Separate items into vendor vs own groups
- Validate stock for both types

### Phase 2: Payment Success
- No longer the reservation trigger for bank-transfer / admin-review orders.
- Reservation and own-stock hold now happen during successful order placement.

### Phase 2: Order Placement Success
- **Vendor products:** Reserve via API immediately
- **Own products:** Hold stock locally immediately
- Update order and fulfillment status based on whether the order is vendor-only, own-only, or mixed

### Phase 3: Fulfillment
- **Vendor products:** Create vendor order (batch)
- **Own products:** Mark as ready to ship
- Track fulfillment per item type

### Phase 4: Cancellation
- **Vendor products:** Unreserve vendor reservations
- **Own products:** Restore held stock locally
- Update order and order-item fulfillment statuses to `cancelled`

### Phase 5: Shipping
- **Vendor products:** Wait for vendor shipment
- **Own products:** Ship immediately
- Update order status accordingly

## Database Changes Needed

### Orders Table
- Add `fulfillment_status` ENUM('pending', 'vendor_pending', 'own_fulfilled', 'vendor_fulfilled', 'fulfilled', 'partially_fulfilled')
- Track which items are fulfilled

### Order Items Table
- Already has `product_source` ✅
- Add `fulfillment_status` ENUM('pending', 'reserved', 'vendor_ordered', 'shipped', 'fulfilled')
- Track individual item fulfillment

## Workflow Examples

### Example 1: Pure Vendor Order
```
Order Created → Reserve → Vendor Order → Shipped
```

### Example 2: Pure Own Order
```
Order Created → Own Stock Held → Ready to Ship → Shipped
```

### Example 3: Mixed Order
```
Order Created → 
  ├─ Vendor Items: Reserve → Vendor Order → Shipped
  └─ Own Items: Stock Held → Ready to Ship → Shipped
  
Order Status: partially_fulfilled → fulfilled
```

### Example 4: Mixed Order Cancelled by Admin
```
Order Created →
  ├─ Vendor Items: Reserve
  └─ Own Items: Stock Held
Cancel Order →
  ├─ Vendor Items: Unreserve
  └─ Own Items: Restore stock

Order Status: cancelled
```
