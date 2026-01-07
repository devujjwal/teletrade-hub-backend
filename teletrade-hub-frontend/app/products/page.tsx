import { Suspense } from 'react';
import { MainLayout } from '@/components/layout/MainLayout';
import { ProductFilters } from '@/components/products/ProductFilters';
import { ProductCard } from '@/components/products/ProductCard';
import { Loading } from '@/components/ui/Loading';
import { serverFetch } from '@/lib/server-fetch';

// Server-side data fetching
async function getProductsData(searchParams: any) {
  try {
    const API_URL = process.env.NEXT_PUBLIC_API_URL || 'https://api.vs-mjrinfotech.com';
    
    // Build query string with required API parameters
    const params = new URLSearchParams();
    params.append('lang', 'en');
    params.append('page', searchParams.page || '1');
    params.append('limit', searchParams.limit || '20');
    
    // Add filter parameters
    if (searchParams.category_id) params.append('category_id', searchParams.category_id);
    if (searchParams.brand_id) params.append('brand_id', searchParams.brand_id);
    if (searchParams.search) params.append('search', searchParams.search);
    if (searchParams.min_price) params.append('min_price', searchParams.min_price);
    if (searchParams.max_price) params.append('max_price', searchParams.max_price);
    if (searchParams.sort) params.append('sort', searchParams.sort);

    const [productsRes, categoriesRes, brandsRes] = await Promise.allSettled([
      serverFetch(`${API_URL}/products?${params.toString()}`, { next: { revalidate: 300 } })
        .then(res => {
          if (!res.ok) return { data: [] };
          return res.json();
        })
        .catch((err) => {
          console.error('Products fetch error:', err);
          return { data: [] };
        }),
      serverFetch(`${API_URL}/categories?lang=en`, { next: { revalidate: 3600 } })
        .then(res => res.ok ? res.json() : { data: [] })
        .catch(() => ({ data: [] })),
      serverFetch(`${API_URL}/brands?lang=en`, { next: { revalidate: 3600 } })
        .then(res => res.ok ? res.json() : { data: [] })
        .catch(() => ({ data: [] }))
    ]);

    const productsData = productsRes.status === 'fulfilled' ? productsRes.value : { data: [] };
    console.log('Products API response:', JSON.stringify(productsData).substring(0, 200));
    
    return {
      products: productsData.data?.products || productsData.data || [],
      categories: categoriesRes.status === 'fulfilled' ? (categoriesRes.value.data || []) : [],
      brands: brandsRes.status === 'fulfilled' ? (brandsRes.value.data || []) : []
    };
  } catch (error) {
    console.error('Error fetching products data:', error);
    return {
      products: [],
      categories: [],
      brands: []
    };
  }
}

export default async function ProductsPage({
  searchParams,
}: {
  searchParams: Promise<{ [key: string]: string | undefined }>;
}) {
  const params = await searchParams;
  const { products, categories, brands } = await getProductsData(params);

  return (
    <MainLayout>
      <div className="bg-white dark:bg-[#020c1a] min-h-screen">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          {/* Page Header */}
          <div className="mb-8">
            <h1 className="text-3xl md:text-4xl font-bold text-gray-900 dark:text-white mb-2">
              Products
            </h1>
            <p className="text-gray-600 dark:text-gray-400">
              {products.length} products found
            </p>
          </div>

          <div className="flex flex-col lg:flex-row gap-8">
            {/* Filters Sidebar */}
            <aside className="lg:w-64 flex-shrink-0">
              <ProductFilters categories={categories} brands={brands} />
            </aside>

            {/* Products Grid */}
            <div className="flex-1">
              {/* Sort Controls */}
              <div className="flex items-center justify-between mb-6">
                <div className="text-sm text-gray-600 dark:text-gray-400">
                  Showing {products.length} products
                </div>
                <select
                  className="px-4 py-2 border border-gray-200 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#FDB813]"
                  defaultValue="newest"
                >
                  <option value="newest">Newest</option>
                  <option value="price_asc">Price: Low to High</option>
                  <option value="price_desc">Price: High to Low</option>
                  <option value="name_asc">Name: A to Z</option>
                  <option value="name_desc">Name: Z to A</option>
                </select>
              </div>

              {/* Products Grid */}
              {products.length > 0 ? (
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                  {products.map((product: any) => (
                    <ProductCard key={product.id} product={product} />
                  ))}
                </div>
              ) : (
                <div className="text-center py-12">
                  <div className="text-6xl mb-4">🔍</div>
                  <h3 className="text-xl font-semibold text-gray-900 dark:text-white mb-2">
                    No products found
                  </h3>
                  <p className="text-gray-600 dark:text-gray-400">
                    Try adjusting your filters or search query
                  </p>
                </div>
              )}

              {/* Pagination */}
              {products.length > 0 && (
                <div className="mt-8 flex items-center justify-center gap-2">
                  <button className="px-4 py-2 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 disabled:opacity-50 disabled:cursor-not-allowed">
                    Previous
                  </button>
                  <button className="px-4 py-2 bg-[#FDB813] text-white rounded-lg font-medium">
                    1
                  </button>
                  <button className="px-4 py-2 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800">
                    2
                  </button>
                  <button className="px-4 py-2 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800">
                    3
                  </button>
                  <button className="px-4 py-2 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800">
                    Next
                  </button>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </MainLayout>
  );
}
