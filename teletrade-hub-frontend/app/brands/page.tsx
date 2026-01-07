'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { MainLayout } from '@/components/layout/MainLayout';
import { Loading } from '@/components/ui/Loading';
import { endpoints } from '@/lib/api';
import { ChevronRightIcon } from '@heroicons/react/24/outline';

export default function BrandsPage() {
  const [brands, setBrands] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchBrands();
  }, []);

  const fetchBrands = async () => {
    try {
      setLoading(true);
      const response = await endpoints.brands.list();
      if (response.success) {
        setBrands(response.data.brands);
      }
    } catch (error) {
      console.error('Failed to fetch brands:', error);
    } finally {
      setLoading(false);
    }
  };

  return (
    <MainLayout>
      <div className="container mx-auto px-4 py-8">
        <div className="mb-8">
          <h1 className="text-3xl md:text-4xl font-bold mb-2">Brands</h1>
          <p className="text-foreground/70">
            Shop by your favorite brands
          </p>
        </div>

        {loading ? (
          <Loading />
        ) : (
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            {brands.map((brand) => (
              <Link
                key={brand.id}
                href={`/products?brand_id=${brand.id}`}
                className="group p-6 bg-muted rounded-lg border border-border hover:border-accent transition-all hover:shadow-lg"
              >
                <div className="flex items-center justify-between mb-3">
                  <h3 className="text-xl font-semibold group-hover:text-accent transition-colors">
                    {brand.name}
                  </h3>
                  <ChevronRightIcon className="w-5 h-5 text-foreground/50 group-hover:text-accent group-hover:translate-x-1 transition-all" />
                </div>
                <p className="text-foreground/60">
                  {brand.product_count || 0} products
                </p>
              </Link>
            ))}
          </div>
        )}
      </div>
    </MainLayout>
  );
}

