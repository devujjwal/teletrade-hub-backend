'use client';

import { useRouter, useSearchParams } from 'next/navigation';
import { useState } from 'react';
import { ChevronDownIcon, ChevronUpIcon } from '@heroicons/react/24/outline';

interface Category {
  id: number;
  name: string;
  product_count?: number;
}

interface Brand {
  id: number;
  name: string;
}

interface ProductFiltersProps {
  categories: Category[];
  brands: Brand[];
}

export function ProductFilters({ categories, brands }: ProductFiltersProps) {
  const router = useRouter();
  const searchParams = useSearchParams();
  const [categoryOpen, setCategoryOpen] = useState(true);
  const [brandOpen, setBrandOpen] = useState(true);
  const [priceOpen, setPriceOpen] = useState(true);
  
  const [minPrice, setMinPrice] = useState(searchParams.get('min_price') || '0');
  const [maxPrice, setMaxPrice] = useState(searchParams.get('max_price') || '5000');

  const updateFilters = (key: string, value: string | null) => {
    const params = new URLSearchParams(searchParams.toString());
    if (value) {
      params.set(key, value);
    } else {
      params.delete(key);
    }
    router.push(`/products?${params.toString()}`);
  };

  const handlePriceChange = () => {
    const params = new URLSearchParams(searchParams.toString());
    params.set('min_price', minPrice);
    params.set('max_price', maxPrice);
    router.push(`/products?${params.toString()}`);
  };

  const selectedCategory = searchParams.get('category_id');
  const selectedBrand = searchParams.get('brand_id');

  return (
    <div className="space-y-4">
      {/* Category Filter */}
      <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <button
          onClick={() => setCategoryOpen(!categoryOpen)}
          className="w-full px-4 py-3 flex items-center justify-between text-left font-semibold text-gray-900 dark:text-white hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
        >
          <span>Category</span>
          {categoryOpen ? (
            <ChevronUpIcon className="w-5 h-5" />
          ) : (
            <ChevronDownIcon className="w-5 h-5" />
          )}
        </button>
        {categoryOpen && (
          <div className="p-4 space-y-2 border-t border-gray-200 dark:border-gray-700">
            <button
              onClick={() => updateFilters('category_id', null)}
              className={`w-full text-left px-3 py-2 rounded-lg transition-colors ${
                !selectedCategory
                  ? 'bg-[#FDB813]/10 text-[#FDB813] font-medium'
                  : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
              }`}
            >
              All Categories
            </button>
            {Array.isArray(categories) && categories.map((category) => (
              <button
                key={category.id}
                onClick={() => updateFilters('category_id', category.id.toString())}
                className={`w-full text-left px-3 py-2 rounded-lg transition-colors ${
                  selectedCategory === category.id.toString()
                    ? 'bg-[#FDB813]/10 text-[#FDB813] font-medium'
                    : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
                }`}
              >
                <div className="flex items-center justify-between">
                  <span>{category.name}</span>
                  {category.product_count !== undefined && (
                    <span className="text-xs text-gray-500 dark:text-gray-400">
                      ({category.product_count})
                    </span>
                  )}
                </div>
              </button>
            ))}
          </div>
        )}
      </div>

      {/* Brand Filter */}
      <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <button
          onClick={() => setBrandOpen(!brandOpen)}
          className="w-full px-4 py-3 flex items-center justify-between text-left font-semibold text-gray-900 dark:text-white hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
        >
          <span>Brand</span>
          {brandOpen ? (
            <ChevronUpIcon className="w-5 h-5" />
          ) : (
            <ChevronDownIcon className="w-5 h-5" />
          )}
        </button>
        {brandOpen && (
          <div className="p-4 space-y-2 border-t border-gray-200 dark:border-gray-700">
            <button
              onClick={() => updateFilters('brand_id', null)}
              className={`w-full text-left px-3 py-2 rounded-lg transition-colors ${
                !selectedBrand
                  ? 'bg-[#FDB813]/10 text-[#FDB813] font-medium'
                  : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
              }`}
            >
              All Brands
            </button>
            {Array.isArray(brands) && brands.map((brand) => (
              <button
                key={brand.id}
                onClick={() => updateFilters('brand_id', brand.id.toString())}
                className={`w-full text-left px-3 py-2 rounded-lg transition-colors ${
                  selectedBrand === brand.id.toString()
                    ? 'bg-[#FDB813]/10 text-[#FDB813] font-medium'
                    : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
                }`}
              >
                {brand.name}
              </button>
            ))}
          </div>
        )}
      </div>

      {/* Price Range Filter */}
      <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <button
          onClick={() => setPriceOpen(!priceOpen)}
          className="w-full px-4 py-3 flex items-center justify-between text-left font-semibold text-gray-900 dark:text-white hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
        >
          <span>Price Range</span>
          {priceOpen ? (
            <ChevronUpIcon className="w-5 h-5" />
          ) : (
            <ChevronDownIcon className="w-5 h-5" />
          )}
        </button>
        {priceOpen && (
          <div className="p-4 space-y-4 border-t border-gray-200 dark:border-gray-700">
            <div>
              <label className="block text-sm text-gray-600 dark:text-gray-400 mb-2">
                Min Price: €{minPrice}
              </label>
              <input
                type="range"
                min="0"
                max="5000"
                step="10"
                value={minPrice}
                onChange={(e) => setMinPrice(e.target.value)}
                className="w-full accent-[#FDB813]"
              />
            </div>
            <div>
              <label className="block text-sm text-gray-600 dark:text-gray-400 mb-2">
                Max Price: €{maxPrice}
              </label>
              <input
                type="range"
                min="0"
                max="5000"
                step="10"
                value={maxPrice}
                onChange={(e) => setMaxPrice(e.target.value)}
                className="w-full accent-[#FDB813]"
              />
            </div>
            <div className="flex items-center gap-2">
              <input
                type="number"
                value={minPrice}
                onChange={(e) => setMinPrice(e.target.value)}
                className="flex-1 px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
              />
              <span className="text-gray-500">-</span>
              <input
                type="number"
                value={maxPrice}
                onChange={(e) => setMaxPrice(e.target.value)}
                className="flex-1 px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
              />
            </div>
            <button
              onClick={handlePriceChange}
              className="w-full py-2 px-4 bg-[#FDB813] hover:bg-[#F59E0B] text-white rounded-lg font-medium transition-colors"
            >
              Apply
            </button>
          </div>
        )}
      </div>
    </div>
  );
}
