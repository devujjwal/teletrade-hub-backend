# UPS Shipping Integration

This document describes the UPS shipping integration implemented in TeleTrade Hub.

## Overview

The integration includes:
- UPS MCP Server connection for AI-assisted shipping operations
- UPS Tracking API integration for package tracking
- Shipping service for managing tracking numbers and shipping information
- API endpoints for tracking packages and managing shipping

## Components

### 1. ShippingService (`app/Services/ShippingService.php`)

Main service class that handles UPS API interactions:

- **`trackPackage($trackingNumber)`** - Track a UPS package
- **`updateOrderTracking($orderId, $trackingNumber, $carrier)`** - Update order with tracking info
- **`getOrderTracking($orderId)`** - Get tracking info for an order
- **`calculateShippingCost(...)`** - Calculate shipping costs (placeholder for Rate API)

### 2. ShippingController (`app/Controllers/ShippingController.php`)

API endpoints for shipping operations:

- `GET /api/shipping/track/{trackingNumber}` - Track any UPS package
- `GET /api/shipping/orders/{orderId}/tracking` - Get tracking for an order
- `POST /api/shipping/calculate` - Calculate shipping cost
- `POST /api/admin/shipping/orders/{orderId}/tracking` - Update order tracking (Admin)

### 3. Database Changes

Migration file: `database/add_tracking_number.sql`

Adds to `orders` table:
- `tracking_number` VARCHAR(100) - UPS tracking number
- `shipping_carrier` VARCHAR(50) - Carrier name (default: UPS)
- `shipped_at` TIMESTAMP - When order was shipped

## Setup

### 1. Database Migration

Run the migration to add tracking fields:

```bash
mysql -u your_user -p your_database < database/add_tracking_number.sql
```

### 2. Environment Configuration

Add to `.env`:

```env
# UPS Shipping Configuration
UPS_CLIENT_ID=your_ups_client_id
UPS_CLIENT_SECRET=your_ups_client_secret
UPS_ENVIRONMENT=test
```

### 3. MCP Server Configuration

See `UPS_MCP_SETUP.md` for MCP server setup instructions.

## API Usage

### Track a Package

```bash
GET /api/shipping/track/1Z999AA10123456784
```

Response:
```json
{
  "success": true,
  "data": {
    "tracking_number": "1Z999AA10123456784",
    "status": "in_transit",
    "status_description": "In Transit",
    "carrier": "UPS",
    "estimated_delivery": "2024-01-15",
    "delivered": false,
    "activities": [
      {
        "date": "2024-01-10",
        "time": "10:30",
        "location": "Atlanta, GA",
        "description": "In Transit",
        "type": "I"
      }
    ]
  }
}
```

### Get Order Tracking

```bash
GET /api/shipping/orders/123/tracking
```

Requires authentication. Returns tracking info for the order if available.

### Update Order Tracking (Admin)

```bash
POST /api/admin/shipping/orders/123/tracking
Content-Type: application/json

{
  "tracking_number": "1Z999AA10123456784",
  "carrier": "UPS"
}
```

This will:
- Update the order with tracking number
- Set order status to "shipped"
- Set `shipped_at` timestamp

## Integration with Order Flow

### When to Add Tracking

1. **Own Products**: When order is fulfilled and ready to ship
2. **Vendor Products**: When vendor provides tracking information
3. **Manual Entry**: Admin can add tracking via API or admin panel

### Order Status Updates

When tracking is added via `updateOrderTracking()`:
- Order status changes to `shipped`
- `shipped_at` timestamp is set
- Tracking number is stored

## Future Enhancements

### UPS Rate API Integration

The `calculateShippingCost()` method currently returns a default value. Future implementation should:

1. Integrate UPS Rate API for real-time shipping quotes
2. Support multiple service types (Ground, Express, etc.)
3. Calculate based on package weight and dimensions
4. Return multiple shipping options with costs

### Shipping Label Creation

Future implementation could include:
- UPS Shipping API integration
- Automatic label generation
- Label printing/download
- Return label creation

### Webhooks

UPS webhook integration for:
- Delivery confirmation
- Exception notifications
- Status updates

## Testing

### Test Tracking Number

UPS provides a test tracking number: `1Z999AA10123456784`

Use this for testing the tracking API.

### Test Credentials

For testing, use UPS test environment credentials from the UPS Developer Portal.

## Error Handling

The service handles:
- Missing credentials
- Invalid tracking numbers
- API errors
- Network failures

Errors are logged and returned as user-friendly messages.

## Security

- Tracking endpoints require authentication
- Admin endpoints require admin privileges
- Credentials stored in environment variables
- No sensitive data in logs

## Resources

- [UPS Developer Portal](https://developer.ups.com)
- [UPS Tracking API Docs](https://developer.ups.com/api/reference)
- [UPS MCP GitHub](https://github.com/UPS-API/ups-mcp)
