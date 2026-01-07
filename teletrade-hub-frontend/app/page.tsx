import Link from 'next/link';
import { ArrowRightIcon, TruckIcon, ShieldCheckIcon, CreditCardIcon, PhoneIcon } from '@heroicons/react/24/outline';
import { MainLayout } from '@/components/layout/MainLayout';
import { FeaturedProducts } from '@/components/home/FeaturedProducts';
import { serverFetch } from '@/lib/server-fetch';

// Server-side data fetching
async function getHomeData() {
  try {
    const API_URL = process.env.NEXT_PUBLIC_API_URL || 'https://api.vs-mjrinfotech.com';
    
    // Use Promise.allSettled to handle individual failures
    const [categoriesRes, brandsRes, productsRes] = await Promise.allSettled([
      serverFetch(`${API_URL}/categories?lang=en`, { next: { revalidate: 3600 } })
        .then(res => res.ok ? res.json() : { data: [] })
        .catch(() => ({ data: [] })),
      serverFetch(`${API_URL}/brands?lang=en`, { next: { revalidate: 3600 } })
        .then(res => res.ok ? res.json() : { data: [] })
        .catch(() => ({ data: [] })),
      serverFetch(`${API_URL}/products?lang=en&page=1&limit=4&featured=true`, { next: { revalidate: 300 } })
        .then(res => res.ok ? res.json() : { data: [] })
        .catch(() => ({ data: [] }))
    ]);

    return {
      categories: categoriesRes.status === 'fulfilled' ? (categoriesRes.value.data || []) : [],
      brands: brandsRes.status === 'fulfilled' ? (brandsRes.value.data || []) : [],
      featuredProducts: productsRes.status === 'fulfilled' ? (productsRes.value.data?.products || productsRes.value.data || []) : []
    };
  } catch (error) {
    console.error('Error fetching home data:', error);
    return {
      categories: [],
      brands: [],
      featuredProducts: []
    };
  }
}

export default async function HomePage() {
  const { categories, brands, featuredProducts } = await getHomeData();

  return (
    <MainLayout>
      {/* Hero Section - Navy Background */}
      <section className="relative overflow-hidden bg-[#041e42] min-h-[500px] md:min-h-[550px]">
        {/* Background gradient overlay */}
        <div className="absolute inset-0 bg-gradient-to-br from-[#041e42] via-[#041e42]/95 to-[#041e42]/80" />
        
        {/* Animated background elements */}
        <div className="absolute inset-0 overflow-hidden pointer-events-none">
          <div className="absolute top-20 left-10 w-72 h-72 bg-[#ffbd27]/20 rounded-full blur-[100px] animate-pulse" />
          <div className="absolute bottom-20 right-20 w-96 h-96 bg-[#0070dc]/10 rounded-full blur-[120px]" />
          <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-[#ffbd27]/5 rounded-full blur-[150px]" />
        </div>

        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 py-20 md:py-28">
          <div className="max-w-3xl mx-auto text-center text-white">
            <div className="inline-flex items-center gap-2 px-4 py-2 bg-[#ffbd27]/20 backdrop-blur-sm rounded-full border border-[#ffbd27]/30 mb-6">
              <span className="w-2 h-2 bg-[#ffbd27] rounded-full animate-pulse" />
              <span className="text-sm font-medium text-[#ffbd27]">Premium Telecom Products</span>
            </div>
            
            <h1 className="text-4xl md:text-5xl lg:text-6xl xl:text-7xl font-bold mb-6 leading-[1.1]">
              <span className="block">Premium Telecom</span>
              <span className="block text-[#ffbd27]">Products</span>
            </h1>
            
            <p className="text-lg md:text-xl text-white/70 mb-10 max-w-xl mx-auto leading-relaxed">
              Discover the latest smartphones, tablets, and accessories from top brands
            </p>
            
            <div className="flex flex-wrap gap-4 justify-center mb-12">
              <Link
                href="/products"
                className="inline-flex items-center gap-2 px-8 py-4 bg-[#ffbd27] hover:bg-[#e6a615] text-[#041e42] rounded-lg font-semibold text-base shadow-lg transition-all"
              >
                Shop Now
                <ArrowRightIcon className="w-5 h-5" />
              </Link>
              <Link
                href="/categories"
                className="inline-flex items-center gap-2 px-8 py-4 border-2 border-white/30 bg-transparent hover:bg-white/10 text-white rounded-lg font-semibold text-base transition-all"
              >
                View Categories
              </Link>
            </div>

            {/* Trust badges */}
            <div className="flex flex-wrap gap-8 justify-center text-white/60 text-sm">
              <div className="flex items-center gap-2">
                <TruckIcon className="w-5 h-5 text-[#ffbd27]" />
                <span>Free Shipping</span>
              </div>
              <div className="flex items-center gap-2">
                <ShieldCheckIcon className="w-5 h-5 text-[#ffbd27]" />
                <span>2 Year Warranty</span>
              </div>
              <div className="flex items-center gap-2">
                <CreditCardIcon className="w-5 h-5 text-[#ffbd27]" />
                <span>Secure Payment</span>
              </div>
              <div className="flex items-center gap-2">
                <PhoneIcon className="w-5 h-5 text-[#ffbd27]" />
                <span>24/7 Support</span>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Shop by Category */}
      <section className="py-16 bg-white dark:bg-[#020c1a]">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex items-center justify-between mb-8">
            <div>
              <h2 className="text-3xl font-bold text-gray-900 dark:text-white mb-2">
                Shop by Category
              </h2>
              <p className="text-gray-600 dark:text-gray-400">Find exactly what you're looking for</p>
            </div>
            <Link
              href="/categories"
              className="inline-flex items-center gap-2 text-gray-600 hover:text-[#041e42] dark:text-gray-400 dark:hover:text-[#ffbd27] font-medium transition-colors"
            >
              View All
              <ArrowRightIcon className="w-4 h-4" />
            </Link>
          </div>

          <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
            {Array.isArray(categories) && categories.slice(0, 6).map((category: any) => (
              <Link
                key={category.id}
                href={`/products?category_id=${category.id}`}
                className="group p-6 bg-white dark:bg-[#041e42] rounded-xl border border-gray-200 dark:border-[#052545] hover:border-[#041e42] dark:hover:border-[#ffbd27] hover:shadow-lg transition-all text-center"
              >
                <div className="w-16 h-16 mx-auto mb-4 rounded-xl bg-gray-100 dark:bg-[#052545] flex items-center justify-center text-4xl group-hover:scale-110 transition-transform">
                  {category.name === 'Phone' && '📱'}
                  {category.name === 'Headphone' && '🎧'}
                  {category.name === 'Gaming console' && '🎮'}
                  {!['Phone', 'Headphone', 'Gaming console'].includes(category.name) && '📦'}
                </div>
                <h3 className="font-semibold text-sm mb-1">{category.name}</h3>
                <p className="text-xs text-gray-500 dark:text-gray-400">
                  {category.product_count || 0} products
                </p>
              </Link>
            ))}
          </div>
        </div>
      </section>

      {/* Featured Products - Client-side refreshable */}
      <FeaturedProducts initialProducts={featuredProducts} />

      {/* Shop by Brand */}
      <section className="py-16 bg-white dark:bg-[#020c1a]">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex items-center justify-between mb-8">
            <div>
              <h2 className="text-3xl font-bold text-gray-900 dark:text-white mb-2">
                Shop by Brand
              </h2>
              <p className="text-gray-600 dark:text-gray-400">Explore products from top brands</p>
            </div>
            <Link
              href="/brands"
              className="inline-flex items-center gap-2 text-gray-600 hover:text-[#041e42] dark:text-gray-400 dark:hover:text-[#ffbd27] font-medium transition-colors"
            >
              View All Brands
              <ArrowRightIcon className="w-4 h-4" />
            </Link>
          </div>

          <div className="flex gap-8 overflow-x-auto pb-4">
            {Array.isArray(brands) && brands.slice(0, 8).map((brand: any) => (
              <Link
                key={brand.id}
                href={`/products?brand_id=${brand.id}`}
                className="flex-shrink-0 group"
              >
                <div className="w-32 h-16 bg-white dark:bg-[#041e42] border border-gray-200 dark:border-[#052545] rounded-lg flex items-center justify-center group-hover:border-[#041e42] dark:group-hover:border-[#ffbd27] transition-colors">
                  <span className="font-semibold text-gray-600 dark:text-gray-400 group-hover:text-[#041e42] dark:group-hover:text-[#ffbd27] transition-colors">
                    {brand.name}
                  </span>
                </div>
              </Link>
            ))}
          </div>
        </div>
      </section>

      {/* Stats Section - Navy Background */}
      <section className="py-16 bg-[#041e42] text-white">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="grid grid-cols-2 lg:grid-cols-4 gap-8 text-center">
            <div>
              <div className="text-4xl md:text-5xl font-bold mb-2">10K+</div>
              <p className="text-white/80">Products Available</p>
            </div>
            <div>
              <div className="text-4xl md:text-5xl font-bold mb-2">50+</div>
              <p className="text-white/80">Top Brands</p>
            </div>
            <div>
              <div className="text-4xl md:text-5xl font-bold mb-2">24/7</div>
              <p className="text-white/80">Customer Support</p>
            </div>
            <div>
              <div className="text-4xl md:text-5xl font-bold mb-2">100%</div>
              <p className="text-white/80">Secure Payments</p>
            </div>
          </div>
        </div>
      </section>

      {/* CTA Section */}
      <section className="py-20 bg-white dark:bg-[#020c1a]">
        <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="bg-white dark:bg-[#041e42] rounded-2xl p-8 md:p-12 text-center border border-gray-200 dark:border-[#052545]">
            <h2 className="text-3xl md:text-4xl font-bold text-gray-900 dark:text-white mb-4">
              Ready to upgrade your tech?
            </h2>
            <p className="text-lg text-gray-600 dark:text-gray-300 mb-8 max-w-2xl mx-auto">
              Browse our extensive collection of premium telecommunication products from the world's leading brands.
            </p>
            <Link
              href="/products"
              className="inline-flex items-center gap-2 px-8 py-4 bg-[#041e42] dark:bg-[#ffbd27] text-white dark:text-[#041e42] rounded-lg font-semibold hover:bg-[#03172f] dark:hover:bg-[#e6a615] transition-all shadow-lg"
            >
              Start Shopping
              <ArrowRightIcon className="w-5 h-5" />
            </Link>
          </div>
        </div>
      </section>
    </MainLayout>
  );
}
