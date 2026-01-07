'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { MainLayout } from '@/components/layout/MainLayout';
import { Loading } from '@/components/ui/Loading';
import { endpoints } from '@/lib/api';
import { ChevronRightIcon } from '@heroicons/react/24/outline';

export default function CategoriesPage() {
  const [categories, setCategories] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchCategories();
  }, []);

  const fetchCategories = async () => {
    try {
      setLoading(true);
      const response = await endpoints.categories.list();
      if (response.success) {
        setCategories(response.data.categories);
      }
    } catch (error) {
      console.error('Failed to fetch categories:', error);
    } finally {
      setLoading(false);
    }
  };

  return (
    <MainLayout>
      <div className="container mx-auto px-4 py-8">
        <div className="mb-8">
          <h1 className="text-3xl md:text-4xl font-bold mb-2">Categories</h1>
          <p className="text-foreground/70">
            Browse products by category
          </p>
        </div>

        {loading ? (
          <Loading />
        ) : (
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            {categories.map((category) => (
              <Link
                key={category.id}
                href={`/products?category_id=${category.id}`}
                className="group p-6 bg-muted rounded-lg border border-border hover:border-accent transition-all hover:shadow-lg"
              >
                <div className="flex items-center justify-between mb-3">
                  <h3 className="text-xl font-semibold group-hover:text-accent transition-colors">
                    {category.name}
                  </h3>
                  <ChevronRightIcon className="w-5 h-5 text-foreground/50 group-hover:text-accent group-hover:translate-x-1 transition-all" />
                </div>
                <p className="text-foreground/60">
                  {category.product_count || 0} products
                </p>
              </Link>
            ))}
          </div>
        )}
      </div>
    </MainLayout>
  );
}

