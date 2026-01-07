import { notFound } from 'next/navigation';
import Image from 'next/image';
import Link from 'next/link';
import { MainLayout } from '@/components/layout/MainLayout';
import { ProductCard } from '@/components/products/ProductCard';
import { ShoppingCartIcon, ArrowLeftIcon } from '@heroicons/react/24/outline';
import { AddToCartButton } from './AddToCartButton';
import { serverFetch } from '@/lib/server-fetch';

// Server-side data fetching
async function getProductData(id: string) {
  try {
    const API_URL = process.env.NEXT_PUBLIC_API_URL || 'https://api.vs-mjrinfotech.com';
    
    const [productRes, relatedRes] = await Promise.allSettled([
      serverFetch(`${API_URL}/products/${id}?lang=en`, { next: { revalidate: 300 } })
        .then(res => res.ok ? res.json() : null)
        .catch(() => null),
      serverFetch(`${API_URL}/products?lang=en&page=1&limit=4`, { next: { revalidate: 300 } })
        .then(res => res.ok ? res.json() : { data: [] })
        .catch(() => ({ data: [] }))
    ]);

    const product = productRes.status === 'fulfilled' ? productRes.value : null;
    const related = relatedRes.status === 'fulfilled' ? relatedRes.value : { data: [] };

    if (!product) {
      return null;
    }

    return {
      product: product.data,
      relatedProducts: related.data?.products || related.data || []
    };
  } catch (error) {
    console.error('Error fetching product data:', error);
    return null;
  }
}

export default async function ProductDetailPage({
  params,
}: {
  params: { id: string };
}) {
  const data = await getProductData(params.id);

  if (!data || !data.product) {
    notFound();
  }

  const { product, relatedProducts } = data;

  return (
    <MainLayout>
      <div className="bg-white dark:bg-[#020c1a] min-h-screen">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          {/* Back Button */}
          <Link
            href="/products"
            className="inline-flex items-center gap-2 text-gray-600 dark:text-gray-400 hover:text-[#041e42] dark:hover:text-[#ffbd27] mb-6 transition-colors"
          >
            <ArrowLeftIcon className="w-5 h-5" />
            Back to Products
          </Link>

          {/* Product Details */}
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-12 mb-16">
            {/* Product Images */}
            <div className="space-y-4">
              <div className="relative aspect-square bg-gray-100 dark:bg-gray-800 rounded-2xl overflow-hidden">
                {product.is_featured && (
                  <span className="absolute top-4 left-4 z-10 px-4 py-2 bg-[#FDB813] text-white text-sm font-semibold rounded-full">
                    Featured
                  </span>
                )}
                {product.images?.[0] ? (
                  <Image
                    src={product.images[0]}
                    alt={product.title}
                    fill
                    className="object-contain p-8"
                    priority
                  />
                ) : (
                  <div className="w-full h-full flex items-center justify-center text-gray-400">
                    <span className="text-8xl">📦</span>
                  </div>
                )}
              </div>
              {product.images && product.images.length > 1 && (
                <div className="grid grid-cols-4 gap-4">
                  {product.images.slice(0, 4).map((image: string, index: number) => (
                    <div
                      key={index}
                      className="relative aspect-square bg-gray-100 dark:bg-gray-800 rounded-lg overflow-hidden cursor-pointer hover:ring-2 hover:ring-[#FDB813] transition-all"
                    >
                      <Image
                        src={image}
                        alt={`${product.title} - ${index + 1}`}
                        fill
                        className="object-contain p-2"
                      />
                    </div>
                  ))}
                </div>
              )}
            </div>

            {/* Product Info */}
            <div className="space-y-6">
              <div>
                <div className="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 mb-2">
                  <span>{product.brand?.name || 'Unknown Brand'}</span>
                  <span>•</span>
                  <span>{product.category?.name || 'Product'}</span>
                </div>
                <h1 className="text-3xl md:text-4xl font-bold text-gray-900 dark:text-white mb-4">
                  {product.title}
                </h1>
                <p className="text-gray-600 dark:text-gray-400 leading-relaxed">
                  {product.description || 'No description available for this product.'}
                </p>
              </div>

              {/* Price and Stock */}
              <div className="flex items-center gap-4">
                <div className="text-4xl font-bold text-gray-900 dark:text-white">
                  €{product.calculated_price?.toFixed(2) || '0.00'}
                </div>
                <span
                  className={`px-4 py-2 rounded-lg text-sm font-semibold ${
                    product.stock_quantity > 10
                      ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                      : 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400'
                  }`}
                >
                  {product.stock_quantity > 10 ? 'In Stock' : 'Low Stock'}
                </span>
              </div>

              {/* Attributes */}
              {product.attributes && Object.keys(product.attributes).length > 0 && (
                <div className="border-t border-b border-gray-200 dark:border-gray-700 py-6 space-y-4">
                  <h3 className="font-semibold text-gray-900 dark:text-white">
                    Specifications
                  </h3>
                  <div className="grid grid-cols-2 gap-4">
                    {Object.entries(product.attributes).map(([key, value]) => (
                      <div key={key}>
                        <dt className="text-sm text-gray-500 dark:text-gray-400 capitalize">
                          {key.replace(/_/g, ' ')}
                        </dt>
                        <dd className="mt-1 text-sm font-medium text-gray-900 dark:text-white">
                          {String(value)}
                        </dd>
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {/* Add to Cart */}
              <div className="space-y-4">
                <AddToCartButton product={product} />
                <div className="text-sm text-gray-500 dark:text-gray-400">
                  <p>✓ Free shipping on orders over €100</p>
                  <p>✓ 30-day money-back guarantee</p>
                  <p>✓ 1-year warranty included</p>
                </div>
              </div>
            </div>
          </div>

          {/* Related Products */}
          {relatedProducts.length > 0 && (
            <div className="border-t border-gray-200 dark:border-gray-700 pt-16">
              <div className="flex items-center justify-between mb-8">
                <div>
                  <h2 className="text-3xl font-bold text-gray-900 dark:text-white mb-2">
                    Related Products
                  </h2>
                  <p className="text-gray-600 dark:text-gray-400">
                    You might also like these products
                  </p>
                </div>
                <Link
                  href="/products"
                  className="text-[#FDB813] hover:text-[#F59E0B] font-medium transition-colors"
                >
                  View All
                </Link>
              </div>
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                {relatedProducts.map((product: any) => (
                  <ProductCard key={product.id} product={product} />
                ))}
              </div>
            </div>
          )}
        </div>
      </div>
    </MainLayout>
  );
}
