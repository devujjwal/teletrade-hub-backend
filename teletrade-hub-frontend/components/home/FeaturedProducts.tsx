'use client';

import { useState, useCallback } from 'react';
import Link from 'next/link';
import { ArrowRightIcon } from '@heroicons/react/24/outline';
import { ProductCard } from '@/components/products/ProductCard';
import { RefreshableSection } from '@/components/RefreshableSection';
import { endpoints } from '@/lib/api';

interface FeaturedProductsProps {
  initialProducts: any[];
}

export function FeaturedProducts({ initialProducts }: FeaturedProductsProps) {
  const [products, setProducts] = useState(initialProducts);

  const refreshProducts = useCallback(async () => {
    try {
      const response = await endpoints.products.list({ lang: 'en', page: 1, limit: 4, featured: true });
      if (response.data) {
        setProducts(response.data.products || response.data);
      }
    } catch (error) {
      console.error('Error refreshing featured products:', error);
    }
  }, []);

  return (
    <section className="py-16 bg-gray-50 dark:bg-[#020c1a]">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <RefreshableSection onRefresh={refreshProducts} refreshInterval={60000}>
          <div className="flex items-center justify-between mb-8">
            <div>
              <h2 className="text-3xl font-bold text-gray-900 dark:text-white mb-2">
                Featured Products
              </h2>
              <p className="text-gray-600 dark:text-gray-400">
                Top picks from our collection
              </p>
            </div>
            <Link
              href="/products"
              className="inline-flex items-center gap-2 text-[#041e42] dark:text-[#ffbd27] hover:text-[#03172f] dark:hover:text-[#e6a615] font-medium transition-colors"
            >
              View All Products
              <ArrowRightIcon className="w-4 h-4" />
            </Link>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            {Array.isArray(products) && products.map((product) => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>
        </RefreshableSection>
      </div>
    </section>
  );
}

