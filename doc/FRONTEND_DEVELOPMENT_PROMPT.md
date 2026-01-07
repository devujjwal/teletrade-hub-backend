# TeleTrade Hub Frontend Development - Complete Prompt

## Project Overview

Develop a complete, production-ready e-commerce frontend for **TeleTrade Hub** using Next.js. The frontend should match the design from `https://github.com/devujjwal/bright-grid-shop.git` and integrate seamlessly with the existing backend API at `https://api.vs-mjrinfotech.com`.

**Owner:** Telecommunication Trading e.K.  
**Backend API:** https://api.vs-mjrinfotech.com  
**Design Reference:** https://github.com/devujjwal/bright-grid-shop.git

---

## Technology Stack Requirements

### Core Framework
- **Next.js 14+** (App Router) with TypeScript
- **React 18+** with Server Components and Client Components
- **Node.js 18+** LTS

### Styling & UI
- **Tailwind CSS** for styling (match design from reference repo)
- **CSS Modules** or **Styled Components** if needed for complex components
- **Responsive Design** - Mobile-first approach
- **Dark Mode Support** (if present in reference design)

### State Management
- **Zustand** or **React Context** for global state (cart, auth, theme)
- **React Query / TanStack Query** for server state management
- **LocalStorage** for cart persistence

### HTTP Client
- **Axios** or **Fetch API** with interceptors
- Error handling and retry logic
- Request/response interceptors for auth tokens

### Image Handling
- **Next.js Image Component** for optimization
- **Image Proxy API Route** - CRITICAL: All product images must be proxied through Next.js API routes
- Images should NOT expose the actual vendor image host to end users
- Implement `/api/images/[...path]` route to proxy images

### Form Handling
- **React Hook Form** for form validation
- **Zod** or **Yup** for schema validation

### Additional Libraries
- **React Hot Toast** or **Sonner** for notifications
- **Heroicons** or **Lucide React** for icons
- **next-themes** for theme management (if dark mode needed)
- **date-fns** for date formatting

---

## Design System & UI Requirements

### 1. Clone Reference Design
```bash
# Clone the reference design repository
git clone https://github.com/devujjwal/bright-grid-shop.git
cd bright-grid-shop
# Study the design, components, colors, typography, spacing
```

### 2. Design Tokens (Extract from Reference)
- **Colors**: Primary, Secondary, Accent, Background, Text, Borders
- **Typography**: Font families, sizes, weights, line heights
- **Spacing**: Consistent spacing scale
- **Breakpoints**: Mobile, Tablet, Desktop
- **Shadows**: Elevation system
- **Border Radius**: Consistent rounding

### 3. Component Library
Create reusable components matching the reference design:
- Buttons (Primary, Secondary, Outline, Ghost)
- Input Fields (Text, Email, Password, Select, Textarea)
- Cards (Product Card, Category Card, Brand Card)
- Badges (Stock Status, Featured, Discount)
- Modals/Dialogs
- Loading States (Skeletons, Spinners)
- Empty States
- Error States

---

## Project Structure

```
teletrade-hub-frontend/
├── app/
│   ├── (auth)/
│   │   ├── login/
│   │   └── register/
│   ├── (shop)/
│   │   ├── page.tsx                    # Home page
│   │   ├── products/
│   │   │   ├── page.tsx                # Products listing
│   │   │   └── [slug]/
│   │   │       └── page.tsx           # Product detail
│   │   ├── categories/
│   │   │   └── [slug]/
│   │   │       └── page.tsx           # Category page
│   │   ├── brands/
│   │   │   └── [slug]/
│   │   │       └── page.tsx           # Brand page
│   │   ├── cart/
│   │   │   └── page.tsx               # Shopping cart
│   │   └── checkout/
│   │       └── page.tsx               # Checkout
│   ├── admin/
│   │   ├── login/
│   │   │   └── page.tsx               # Admin login
│   │   ├── dashboard/
│   │   │   └── page.tsx               # Admin dashboard
│   │   ├── orders/
│   │   │   ├── page.tsx               # Orders list
│   │   │   └── [id]/
│   │   │       └── page.tsx           # Order detail
│   │   ├── products/
│   │   │   ├── page.tsx               # Products management
│   │   │   └── [id]/
│   │   │       └── page.tsx           # Product edit
│   │   ├── pricing/
│   │   │   └── page.tsx               # Pricing configuration
│   │   └── sync/
│   │       └── page.tsx               # Product sync
│   ├── api/
│   │   └── images/
│   │       └── [...path]/
│   │           └── route.ts           # Image proxy route
│   ├── layout.tsx                     # Root layout
│   ├── globals.css                    # Global styles
│   └── loading.tsx                    # Loading UI
├── components/
│   ├── ui/                            # Base UI components
│   │   ├── button.tsx
│   │   ├── input.tsx
│   │   ├── card.tsx
│   │   ├── badge.tsx
│   │   └── ...
│   ├── layout/
│   │   ├── header.tsx                 # Site header
│   │   ├── footer.tsx                 # Site footer
│   │   └── admin-layout.tsx           # Admin layout
│   ├── products/
│   │   ├── product-card.tsx
│   │   ├── product-filters.tsx
│   │   ├── product-gallery.tsx
│   │   └── add-to-cart-button.tsx
│   ├── cart/
│   │   ├── cart-item.tsx
│   │   └── cart-summary.tsx
│   ├── checkout/
│   │   ├── checkout-form.tsx
│   │   └── order-summary.tsx
│   └── admin/
│       ├── dashboard-stats.tsx
│       ├── orders-table.tsx
│       └── ...
├── lib/
│   ├── api/
│   │   ├── client.ts                  # API client setup
│   │   ├── products.ts                # Product endpoints
│   │   ├── categories.ts              # Category endpoints
│   │   ├── brands.ts                  # Brand endpoints
│   │   ├── orders.ts                  # Order endpoints
│   │   ├── auth.ts                    # Auth endpoints
│   │   └── admin.ts                   # Admin endpoints
│   ├── store/
│   │   ├── cart-store.ts              # Cart state (Zustand)
│   │   ├── auth-store.ts              # Auth state
│   │   └── theme-store.ts             # Theme state
│   ├── utils/
│   │   ├── format.ts                  # Formatting utilities
│   │   ├── validation.ts              # Validation schemas
│   │   └── constants.ts               # Constants
│   └── hooks/
│       ├── use-cart.ts
│       ├── use-auth.ts
│       └── use-products.ts
├── types/
│   ├── product.ts
│   ├── order.ts
│   ├── category.ts
│   ├── brand.ts
│   └── api.ts
├── public/
│   ├── images/
│   └── favicon.ico
├── .env.local.example
├── next.config.js
├── tailwind.config.ts
├── tsconfig.json
└── package.json
```

---

## Critical Requirements

### 1. Image Proxy Implementation (CRITICAL)

**Problem**: Product images come from vendor API (e.g., `https://images.triel.sk/...`). End users should NOT see the actual image host.

**Solution**: Create a Next.js API route that proxies images:

```typescript
// app/api/images/[...path]/route.ts
import { NextRequest, NextResponse } from 'next/server';

export async function GET(
  request: NextRequest,
  { params }: { params: { path: string[] } }
) {
  try {
    const imagePath = params.path.join('/');
    const imageUrl = decodeURIComponent(imagePath);
    
    // Validate URL (security check)
    if (!isValidImageUrl(imageUrl)) {
      return new NextResponse('Invalid image URL', { status: 400 });
    }
    
    // Fetch image from vendor
    const response = await fetch(imageUrl, {
      headers: {
        'User-Agent': 'TeleTrade-Hub-ImageProxy/1.0',
      },
    });
    
    if (!response.ok) {
      return new NextResponse('Image not found', { status: 404 });
    }
    
    const imageBuffer = await response.arrayBuffer();
    const contentType = response.headers.get('content-type') || 'image/jpeg';
    
    // Return proxied image
    return new NextResponse(imageBuffer, {
      headers: {
        'Content-Type': contentType,
        'Cache-Control': 'public, max-age=31536000, immutable',
      },
    });
  } catch (error) {
    return new NextResponse('Error fetching image', { status: 500 });
  }
}

function isValidImageUrl(url: string): boolean {
  try {
    const parsed = new URL(url);
    // Whitelist allowed domains
    const allowedDomains = [
      'images.triel.sk',
      // Add other vendor image domains here
    ];
    return allowedDomains.some(domain => parsed.hostname.includes(domain));
  } catch {
    return false;
  }
}
```

**Usage in Components**:
```typescript
// Convert vendor image URL to proxy URL
function getProxiedImageUrl(originalUrl: string): string {
  if (!originalUrl) return '/placeholder-image.jpg';
  const encoded = encodeURIComponent(originalUrl);
  return `/api/images/${encoded}`;
}

// In ProductCard component
<Image
  src={getProxiedImageUrl(product.primary_image)}
  alt={product.name}
  width={400}
  height={400}
/>
```

### 2. Multi-Language Support

- Support languages: `en` (English), `de` (German), `sk`, `fr`, `es`, `ru`, `it`, `tr`, `ro`, `pl`
- Language selector in header
- Store language preference in cookies/localStorage
- Pass `?lang=xx` query parameter to all API calls
- Use `Accept-Language` header where applicable
- Implement language switching without page reload (client-side)

### 3. SEO Optimization

- **Server-Side Rendering (SSR)** for all product/category/brand pages
- **Static Generation (SSG)** for static pages where possible
- **Dynamic Metadata** for product pages:
  - Title: `{Product Name} | TeleTrade Hub`
  - Description: Product description or meta_description
  - Open Graph tags
  - Twitter Card tags
- **Structured Data (JSON-LD)** for products:
  - Product schema
  - BreadcrumbList schema
  - Organization schema
- **Sitemap.xml** generation
- **Robots.txt** configuration
- **Canonical URLs** for all pages
- **Slug-based URLs**: `/products/{slug}` instead of `/products/{id}`

### 4. Performance Requirements

- **Lighthouse Score**: 90+ for Performance, Accessibility, Best Practices, SEO
- **Image Optimization**: Use Next.js Image component with proper sizing
- **Code Splitting**: Automatic route-based splitting
- **Lazy Loading**: Images and below-the-fold content
- **Caching Strategy**:
  - Static pages: ISR with revalidation
  - API data: Cache with appropriate TTL
  - Images: Long-term caching (1 year)
- **Bundle Size**: Keep initial bundle under 200KB (gzipped)

---

## Page Requirements

### 1. Home Page (`/`)

**Features**:
- Hero section with CTA
- Featured products section (4-6 products)
- Shop by Category section (grid of categories)
- Shop by Brand section (brand logos)
- Trust badges / Stats section
- Newsletter signup (optional)

**Data Fetching**:
- Server-side fetch for initial data
- Revalidate every 5 minutes (ISR)
- Featured products can refresh client-side every 60 seconds

### 2. Products Listing (`/products`)

**Features**:
- Grid layout (3-4 columns on desktop, 2 on tablet, 1 on mobile)
- Left sidebar filters:
  - Category filter (collapsible, with counts)
  - Brand filter (collapsible, with counts)
  - Price range slider
  - Color filter (if applicable)
  - Storage filter (if applicable)
  - RAM filter (if applicable)
  - Availability filter (In Stock / Out of Stock)
- Sort dropdown: Newest, Price Low-High, Price High-Low, Name A-Z
- Search bar integration
- Pagination (20 products per page)
- Empty state when no products found
- Loading states (skeleton loaders)

**URL Parameters**:
- `?page=1`
- `?category=smartphones`
- `?brand=apple`
- `?min_price=100&max_price=2000`
- `?search=iPhone`
- `?sort=price&order=asc`
- `?lang=en`

**Data Fetching**:
- Server-side fetch with URL parameters
- Client-side filtering for instant feedback (optional)
- Revalidate every 5 minutes

### 3. Product Detail Page (`/products/[slug]`)

**Features**:
- Breadcrumb navigation
- Product image gallery (main image + thumbnails)
- Product information:
  - Brand and category badges
  - Product title
  - Price (with currency)
  - Stock status badge
  - Specifications table (storage, RAM, color, etc.)
  - Description
  - Warranty information
- Quantity selector
- Add to Cart button
- Trust badges (Free Shipping, Secure Payment, etc.)
- Related products section (4 products from same category/brand)
- Share buttons (optional)
- Reviews section (if implemented)

**Data Fetching**:
- Server-side fetch by slug
- Generate static paths for popular products (optional)
- Revalidate every 5 minutes

**Metadata**:
- Dynamic title and description
- Open Graph tags
- Product structured data (JSON-LD)

### 4. Category Page (`/categories/[slug]`)

**Features**:
- Category banner/hero
- Category description
- Products grid (same as products listing)
- Filters sidebar (pre-filtered by category)
- Breadcrumb navigation

### 5. Brand Page (`/brands/[slug]`)

**Features**:
- Brand logo and description
- Products grid (pre-filtered by brand)
- Filters sidebar (pre-filtered by brand)
- Breadcrumb navigation

### 6. Shopping Cart (`/cart`)

**Features**:
- Cart items list:
  - Product image (proxied)
  - Product name (link to product page)
  - Price per unit
  - Quantity controls (+/-)
  - Subtotal per item
  - Remove button
- Order summary sidebar:
  - Subtotal
  - Shipping cost (if applicable)
  - Tax (if applicable)
  - Total
  - Proceed to Checkout button
- Empty cart state
- Continue shopping link
- Trust badges

**State Management**:
- Zustand store with localStorage persistence
- Sync with server on checkout (optional)

### 7. Checkout Page (`/checkout`)

**Features**:
- Contact information form:
  - Full name
  - Email
  - Phone
- Shipping address form:
  - Address line 1
  - Address line 2 (optional)
  - City
  - State/Province
  - Postal code
  - Country (dropdown)
- Billing address (same as shipping checkbox)
- Payment method selection (if multiple methods)
- Order summary (read-only)
- Place Order button
- Form validation
- Loading state during submission
- Error handling
- Success redirect to order confirmation

**API Integration**:
- POST `/orders` endpoint
- Handle guest orders (no user_id required)
- Handle registered user orders (if auth implemented)

### 8. Order Confirmation (`/orders/[orderId]`)

**Features**:
- Order number display
- Order summary
- Items list
- Shipping address
- Payment status
- Estimated delivery (if available)
- Print order button
- Continue shopping button

### 9. Admin Login (`/admin/login`)

**Features**:
- Centered login card
- Username field
- Password field (with show/hide toggle)
- Sign In button
- Error message display
- Loading state
- Redirect to dashboard on success
- Token storage (httpOnly cookie recommended)

**Design**: Match reference design exactly

### 10. Admin Dashboard (`/admin/dashboard`)

**Features**:
- Sidebar navigation:
  - Dashboard (active)
  - Orders
  - Products
  - Pricing
  - Sync
  - Settings
  - Logout
- Stats cards (4):
  - Total Revenue
  - Total Orders
  - Total Products
  - Today's Revenue
- Order status overview (cards for each status)
- Recent orders table
- Quick actions
- Auto-refresh stats every 30 seconds (optional)

**Data Fetching**:
- Server-side fetch with auth check
- Client-side refresh for stats

### 11. Admin Orders (`/admin/orders`)

**Features**:
- Orders table with:
  - Order number
  - Customer name/email
  - Date
  - Status badge
  - Payment status badge
  - Total amount
  - Actions (View, Update Status)
- Filters:
  - Status filter
  - Payment status filter
  - Date range filter
  - Search by order number/customer
- Pagination
- Export to CSV (optional)

### 12. Admin Order Detail (`/admin/orders/[id]`)

**Features**:
- Order information card
- Customer information
- Items list
- Shipping address
- Billing address
- Order timeline/status history
- Update status dropdown
- Notes section
- Print invoice button

### 13. Admin Products (`/admin/products`)

**Features**:
- Products table with:
  - Image thumbnail
  - SKU
  - Name
  - Category
  - Brand
  - Price
  - Stock quantity
  - Availability status
  - Actions (Edit, Toggle Availability)
- Filters and search
- Pagination
- Bulk actions (optional)

### 14. Admin Pricing (`/admin/pricing`)

**Features**:
- Global markup configuration:
  - Markup percentage input
  - Recalculate all prices button
- Category-specific markup:
  - List of categories
  - Markup percentage per category
  - Save button
- Preview of price calculation
- Bulk update confirmation

### 15. Admin Sync (`/admin/sync`)

**Features**:
- Last sync status display
- Sync statistics:
  - Products synced
  - Products added
  - Products updated
  - Products disabled
- Manual sync button
- Sync progress indicator (if long-running)
- Sync logs (optional)

---

## API Integration

### API Client Setup

```typescript
// lib/api/client.ts
import axios from 'axios';

const apiClient = axios.create({
  baseURL: process.env.NEXT_PUBLIC_API_URL || 'https://api.vs-mjrinfotech.com',
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
  },
});

// Request interceptor for auth token
apiClient.interceptors.request.use((config) => {
  const token = getAuthToken(); // From cookie or localStorage
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Response interceptor for error handling
apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      // Handle unauthorized - redirect to login
      redirectToLogin();
    }
    return Promise.reject(error);
  }
);

export default apiClient;
```

### API Endpoints to Implement

**Products**:
- `GET /products` - List products with filters
- `GET /products/{slug}` - Get product by slug
- `GET /products/search?q=query` - Search products

**Categories**:
- `GET /categories?lang=en` - List categories
- `GET /categories/{slug}/products` - Get products by category

**Brands**:
- `GET /brands` - List brands
- `GET /brands/{slug}/products` - Get products by brand

**Orders**:
- `POST /orders` - Create order
- `GET /orders/{orderId}` - Get order details
- `POST /orders/{orderId}/payment-success` - Payment success callback
- `POST /orders/{orderId}/payment-failed` - Payment failed callback

**Auth**:
- `POST /auth/register` - Register customer
- `POST /auth/login` - Customer login
- `POST /admin/login` - Admin login

**Admin**:
- `GET /admin/dashboard` - Dashboard stats
- `GET /admin/orders` - List orders
- `GET /admin/orders/{id}` - Order detail
- `PUT /admin/orders/{id}/status` - Update order status
- `GET /admin/products` - List products
- `PUT /admin/products/{id}` - Update product
- `GET /admin/pricing` - Get pricing config
- `PUT /admin/pricing/global` - Update global markup
- `PUT /admin/pricing/category/{id}` - Update category markup
- `POST /admin/sync/products` - Sync products
- `GET /admin/sync/status` - Get sync status

---

## State Management

### Cart Store (Zustand)

```typescript
// lib/store/cart-store.ts
import { create } from 'zustand';
import { persist } from 'zustand/middleware';

interface CartItem {
  product_id: number;
  product_name: string;
  product_image: string;
  price: number;
  quantity: number;
  sku: string;
}

interface CartStore {
  items: CartItem[];
  addItem: (item: CartItem) => void;
  removeItem: (productId: number) => void;
  updateQuantity: (productId: number, quantity: number) => void;
  clearCart: () => void;
  getTotal: () => number;
  getItemCount: () => number;
}

export const useCartStore = create<CartStore>()(
  persist(
    (set, get) => ({
      items: [],
      addItem: (item) => {
        const existingItem = get().items.find(i => i.product_id === item.product_id);
        if (existingItem) {
          set({
            items: get().items.map(i =>
              i.product_id === item.product_id
                ? { ...i, quantity: i.quantity + item.quantity }
                : i
            ),
          });
        } else {
          set({ items: [...get().items, item] });
        }
      },
      removeItem: (productId) => {
        set({ items: get().items.filter(i => i.product_id !== productId) });
      },
      updateQuantity: (productId, quantity) => {
        if (quantity <= 0) {
          get().removeItem(productId);
        } else {
          set({
            items: get().items.map(i =>
              i.product_id === productId ? { ...i, quantity } : i
            ),
          });
        }
      },
      clearCart: () => set({ items: [] }),
      getTotal: () => {
        return get().items.reduce((sum, item) => sum + item.price * item.quantity, 0);
      },
      getItemCount: () => {
        return get().items.reduce((sum, item) => sum + item.quantity, 0);
      },
    }),
    {
      name: 'cart-storage',
    }
  )
);
```

### Auth Store

```typescript
// lib/store/auth-store.ts
import { create } from 'zustand';
import { persist } from 'zustand/middleware';

interface AuthStore {
  token: string | null;
  user: User | null;
  isAdmin: boolean;
  login: (token: string, user: User, isAdmin?: boolean) => void;
  logout: () => void;
}

export const useAuthStore = create<AuthStore>()(
  persist(
    (set) => ({
      token: null,
      user: null,
      isAdmin: false,
      login: (token, user, isAdmin = false) => {
        set({ token, user, isAdmin });
        // Also set httpOnly cookie if possible
      },
      logout: () => {
        set({ token: null, user: null, isAdmin: false });
        // Clear cookie
      },
    }),
    {
      name: 'auth-storage',
    }
  )
);
```

---

## Environment Variables

Create `.env.local`:

```env
# API Configuration
NEXT_PUBLIC_API_URL=https://api.vs-mjrinfotech.com

# Image Proxy Configuration
NEXT_PUBLIC_IMAGE_PROXY_ENABLED=true
ALLOWED_IMAGE_DOMAINS=images.triel.sk

# App Configuration
NEXT_PUBLIC_APP_NAME=TeleTrade Hub
NEXT_PUBLIC_APP_URL=https://teletrade-hub.com

# Feature Flags
NEXT_PUBLIC_ENABLE_DARK_MODE=true
NEXT_PUBLIC_ENABLE_MULTI_LANGUAGE=true
```

---

## Testing Requirements

### Unit Tests
- Component tests with React Testing Library
- Utility function tests
- Store tests

### Integration Tests
- API integration tests
- Form submission tests
- Cart functionality tests

### E2E Tests (Optional)
- Playwright or Cypress
- Critical user flows:
  - Browse products → Add to cart → Checkout
  - Admin login → View dashboard → Update order status

---

## Deployment Checklist

- [ ] Environment variables configured
- [ ] API URL set correctly
- [ ] Image proxy tested
- [ ] SEO metadata verified
- [ ] Sitemap generated
- [ ] Robots.txt configured
- [ ] Analytics integrated (if needed)
- [ ] Error tracking (Sentry, etc.)
- [ ] Performance monitoring
- [ ] SSL certificate configured
- [ ] CDN configured (if applicable)
- [ ] Image optimization verified
- [ ] Mobile responsiveness tested
- [ ] Cross-browser testing completed
- [ ] Accessibility audit passed
- [ ] Lighthouse scores met

---

## Development Workflow

1. **Setup Project**:
   ```bash
   npx create-next-app@latest teletrade-hub-frontend --typescript --tailwind --app
   cd teletrade-hub-frontend
   ```

2. **Install Dependencies**:
   ```bash
   npm install axios zustand react-hook-form zod @hookform/resolvers
   npm install react-hot-toast @heroicons/react date-fns
   npm install -D @types/node
   ```

3. **Clone Reference Design**:
   ```bash
   git clone https://github.com/devujjwal/bright-grid-shop.git temp-reference
   # Study the design, extract colors, components, layouts
   ```

4. **Setup Tailwind Config**:
   - Extract colors from reference design
   - Configure custom theme
   - Setup dark mode (if needed)

5. **Create Base Components**:
   - Start with UI primitives (Button, Input, Card)
   - Build layout components (Header, Footer)
   - Create product components

6. **Implement Pages**:
   - Start with Home page
   - Products listing
   - Product detail
   - Cart and Checkout
   - Admin pages

7. **Implement Image Proxy**:
   - Create API route
   - Test with various image URLs
   - Add error handling and fallbacks

8. **Add State Management**:
   - Setup cart store
   - Setup auth store
   - Integrate with components

9. **SEO Optimization**:
   - Add metadata to all pages
   - Generate sitemap
   - Add structured data

10. **Testing & Optimization**:
    - Test all features
    - Optimize images
    - Check performance
    - Fix accessibility issues

---

## Design Matching Checklist

- [ ] Colors match reference design exactly
- [ ] Typography matches (font family, sizes, weights)
- [ ] Spacing matches (margins, padding, gaps)
- [ ] Component styles match (buttons, inputs, cards)
- [ ] Layout structure matches
- [ ] Navigation matches
- [ ] Product cards match
- [ ] Forms match
- [ ] Admin panel matches
- [ ] Responsive breakpoints match
- [ ] Animations/transitions match (if any)
- [ ] Icons match

---

## Additional Features (Optional)

- **Wishlist**: Save products for later
- **Product Comparison**: Compare multiple products
- **Reviews & Ratings**: Customer reviews
- **Live Chat**: Customer support chat
- **Newsletter**: Email subscription
- **Social Sharing**: Share products on social media
- **Recently Viewed**: Track viewed products
- **Recommendations**: "You may also like" section
- **Quick View**: Modal product preview
- **Bulk Order**: Order multiple quantities easily

---

## Support & Documentation

- **API Documentation**: Reference `API_ENDPOINTS_REFERENCE.md`
- **Postman Collection**: Use `TeleTrade_Hub_API_Collection.postman_collection.json` for testing
- **Design Reference**: Study `https://github.com/devujjwal/bright-grid-shop.git`
- **Backend Docs**: Check `teletrade-hub-backend/doc/` folder

---

## Success Criteria

✅ **Functional**:
- All pages render correctly
- All API endpoints integrated
- Cart functionality works
- Checkout flow completes
- Admin panel functional
- Image proxy working
- Multi-language support working

✅ **Design**:
- Matches reference design pixel-perfect
- Responsive on all devices
- Dark mode works (if implemented)
- Smooth animations/transitions

✅ **Performance**:
- Lighthouse score 90+
- Fast page loads (< 2s)
- Optimized images
- Efficient bundle size

✅ **SEO**:
- All pages have proper metadata
- Structured data implemented
- Sitemap generated
- SEO-friendly URLs

✅ **Code Quality**:
- TypeScript with no `any` types
- Clean, readable code
- Proper error handling
- Accessible components

---

## Notes

- **Image Proxy is CRITICAL**: End users must never see vendor image URLs directly
- **Slug-based URLs**: Use slugs instead of IDs for SEO
- **Server-Side Rendering**: Use SSR for initial page loads, ISR for caching
- **Error Handling**: Graceful error handling with user-friendly messages
- **Loading States**: Show loading indicators during data fetching
- **Empty States**: Handle empty states (no products, empty cart, etc.)
- **Form Validation**: Client-side and server-side validation
- **Security**: Sanitize user inputs, validate API responses

---

**Start Development**: Begin with project setup, then implement pages one by one, testing each thoroughly before moving to the next.

**Questions?** Refer to backend API documentation or test endpoints using the Postman collection.

