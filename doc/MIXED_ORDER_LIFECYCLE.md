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
pending → paid → processing → [vendor_ordered | fulfilled | partially_fulfilled]
```

### Key Design Principles

1. **Separate vendor items from own items** at order creation
2. **Reserve only vendor products** after payment
3. **Deduct stock immediately** for own products
4. **Process vendor orders separately** (batch at end of day)
5. **Track fulfillment status** per item type

## Implementation Strategy

### Phase 1: Order Creation
- Capture `product_source` in order_items
- Separate items into vendor vs own groups
- Validate stock for both types

### Phase 2: Payment Success
- **Vendor products:** Reserve via API
- **Own products:** Deduct stock immediately
- Update order status based on results

### Phase 3: Fulfillment
- **Vendor products:** Create vendor order (batch)
- **Own products:** Mark as ready to ship
- Track fulfillment per item type

### Phase 4: Shipping
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
Order Created → Payment → Reserve → Vendor Order → Shipped
```

### Example 2: Pure Own Order
```
Order Created → Payment → Stock Deducted → Ready to Ship → Shipped
```

### Example 3: Mixed Order
```
Order Created → Payment → 
  ├─ Vendor Items: Reserve → Vendor Order → Shipped
  └─ Own Items: Stock Deducted → Ready to Ship → Shipped
  
Order Status: partially_fulfilled → fulfilled
```

