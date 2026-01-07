# TeleTrade Hub - Frontend

**Production-grade e-commerce platform frontend**  
**Owner:** Telecommunication Trading e.K.

## Technology Stack

- **Framework:** Next.js 16 (App Router)
- **Language:** TypeScript
- **Styling:** Tailwind CSS v4
- **State Management:** Zustand
- **HTTP Client:** Axios
- **Icons:** Heroicons
- **Notifications:** React Hot Toast
- **Theme:** next-themes (Light/Dark mode)
- **Deployment:** Vercel

## Features

### Customer Features
- ✅ Product browsing with advanced filtering
- ✅ Product detail pages with image galleries
- ✅ Shopping cart with persistent storage
- ✅ Secure checkout process
- ✅ User authentication and account management
- ✅ Order tracking
- ✅ Responsive mobile-first design
- ✅ Dark/Light theme toggle
- ✅ Search functionality

### Admin Features
- ✅ Secure admin authentication
- ✅ Dashboard with statistics
- ✅ Product synchronization
- ✅ Order management
- ✅ Pricing configuration
- ✅ Real-time updates

### Design Features
- ✅ Black/Yellow/White brand colors
- ✅ Smooth animations and transitions
- ✅ Mobile bottom navigation
- ✅ App-like user experience
- ✅ Loading states and error handling
- ✅ Toast notifications
- ✅ SEO optimized

## Project Structure

```
├── app/                    # Next.js App Router pages
│   ├── admin/             # Admin panel
│   ├── products/          # Product pages
│   ├── cart/              # Shopping cart
│   ├── checkout/          # Checkout flow
│   ├── account/           # User account
│   ├── categories/        # Category browsing
│   ├── brands/            # Brand browsing
│   └── [legal pages]/     # Terms, Privacy, etc.
├── components/            # React components
│   ├── layout/           # Layout components
│   ├── products/         # Product components
│   ├── providers/        # Context providers
│   └── ui/               # UI components
├── lib/                  # Utilities and libraries
│   ├── api.ts           # API client
│   ├── store.ts         # Zustand stores
│   └── utils.ts         # Helper functions
├── public/              # Static assets
└── package.json         # Dependencies
```

## Setup Instructions

### 1. Install Dependencies

```bash
npm install
```

### 2. Environment Configuration

Copy `.env.example` to `.env.local` and configure:

```bash
cp .env.example .env.local
```

Update the following variables:
- `NEXT_PUBLIC_API_URL` - Backend API URL

### 3. Development

```bash
npm run dev
```

Open [http://localhost:3000](http://localhost:3000)

### 4. Build for Production

```bash
npm run build
npm start
```

## Environment Variables

```env
NEXT_PUBLIC_API_URL=https://api.vs-mjrinfotech.com
NEXT_PUBLIC_APP_NAME=TeleTrade Hub
NEXT_PUBLIC_APP_DESCRIPTION=Premium electronics and telecommunications equipment
```

## Pages

### Customer Pages
- `/` - Home page
- `/products` - Product listing with filters
- `/products/[id]` - Product detail
- `/categories` - Categories overview
- `/brands` - Brands overview
- `/cart` - Shopping cart
- `/checkout` - Checkout process
- `/account` - User account

### Admin Pages
- `/admin/login` - Admin authentication
- `/admin/dashboard` - Admin dashboard

### Information Pages
- `/how-to-buy` - Purchasing guide
- `/shipping` - Shipping information
- `/terms` - Terms & Conditions
- `/privacy` - Privacy Policy

## Components

### Layout Components
- `MainLayout` - Main application layout
- `Header` - Navigation header
- `Footer` - Site footer
- `MobileNav` - Mobile bottom navigation

### Product Components
- `ProductCard` - Product listing card
- `ProductFilters` - Filtering sidebar

### UI Components
- `Button` - Customizable button
- `Input` - Form input
- `Loading` - Loading indicators
- `ThemeToggle` - Dark/light mode toggle

## State Management

### Stores (Zustand)

**Cart Store:**
- Add/remove items
- Update quantities
- Calculate totals
- Persistent storage

**User Store:**
- User authentication
- Account information
- Logout functionality

**Admin Store:**
- Admin authentication
- Token management
- Secure session handling

**UI Store:**
- Mobile menu state
- Modal management

## Styling

### Theme Colors
- **Primary:** Black (#000000)
- **Accent:** Yellow (#FDB813)
- **Background:** White/Black (theme-dependent)

### Dark Mode
Automatic dark mode support with system preference detection and manual toggle.

### Responsive Design
- Mobile-first approach
- Breakpoints: sm (640px), md (768px), lg (1024px), xl (1280px)
- Touch-friendly interfaces
- Optimized for all screen sizes

## API Integration

All API calls are centralized in `lib/api.ts`:

```typescript
import { endpoints } from '@/lib/api';

// Products
await endpoints.products.list();
await endpoints.products.get(id);

// Orders
await endpoints.orders.create(data);

// Admin
await endpoints.admin.dashboard();
await endpoints.admin.sync.products();
```

## Deployment

### Vercel (Recommended)

1. Connect repository to Vercel
2. Set environment variables
3. Deploy automatically on push

### Manual Deployment

```bash
npm run build
npm start
```

## Performance Optimizations

- ✅ Server-side rendering (SSR)
- ✅ Static generation where possible
- ✅ Image optimization (Next.js Image)
- ✅ Code splitting
- ✅ Lazy loading
- ✅ Efficient state management
- ✅ Optimized bundle size

## Security

- ✅ Secure authentication
- ✅ Token-based admin access
- ✅ XSS protection
- ✅ CSRF protection
- ✅ Secure API communication
- ✅ Environment-based configuration

## Browser Support

- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)
- Mobile browsers (iOS Safari, Chrome Mobile)

## Scripts

```bash
npm run dev          # Start development server
npm run build        # Build for production
npm start            # Start production server
npm run lint         # Run ESLint
npm run type-check   # TypeScript type checking
```

## Contributing

This is a production application for Telecommunication Trading e.K.

## Support

For technical support or questions, contact the development team.

---

**© 2024 Telecommunication Trading e.K. - All rights reserved**

**Built with ❤️ using Next.js and React**
