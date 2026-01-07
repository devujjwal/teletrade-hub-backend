'use client';

import Link from 'next/link';
import Image from 'next/image';
import { ShoppingCartIcon } from '@heroicons/react/24/outline';
import { useCartStore } from '@/lib/store';
import toast from 'react-hot-toast';

interface Product {
  id: number;
  name?: string;
  name_en?: string;
  title?: string;
  price?: number;
  calculated_price?: number;
  stock_quantity: number;
  available_quantity?: number;
  is_featured?: boolean | number;
  images?: string[];
  primary_image?: string;
  brand_id?: number;
  brand_name?: string;
  brand?: {
    id: number;
    name: string;
  };
  category_id?: number;
  category_name?: string;
  category?: {
    id: number;
    name: string;
  };
  color?: string;
  storage?: string;
  ram?: string;
  attributes?: {
    storage?: string;
    color?: string;
    [key: string]: any;
  };
  sku?: string;
  vendor_article_id?: string;
}

interface ProductCardProps {
  product: Product;
}

export function ProductCard({ product }: ProductCardProps) {
  const addItem = useCartStore((state) => state.addItem);

  // Map API fields to expected format
  const productTitle = product.name_en || product.name || product.title || 'Unnamed Product';
  const productPrice = parseFloat(String(product.price || product.calculated_price || 0));
  const productImage = product.primary_image || product.images?.[0];
  const brandName = product.brand_name || product.brand?.name;
  const categoryName = product.category_name || product.category?.name;
  const isFeatured = product.is_featured === 1 || product.is_featured === true;
  const stockQty = parseInt(String(product.available_quantity || product.stock_quantity || 0));

  const handleAddToCart = (e: React.MouseEvent) => {
    e.preventDefault();
    addItem({
      product_id: product.id,
      name: productTitle,
      price: productPrice,
      image: productImage,
      sku: product.sku || '',
      vendor_article_id: product.vendor_article_id || '',
    });
    toast.success('Added to cart!');
  };

  return (
    <div className="group bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden hover:shadow-lg transition-all">
      <Link href={`/products/${product.id}`} className="block relative aspect-square bg-gray-100 dark:bg-gray-700">
        {isFeatured && (
          <span className="absolute top-3 left-3 z-10 px-3 py-1 bg-[#FDB813] text-white text-xs font-semibold rounded-full">
            Featured
          </span>
        )}
        {productImage ? (
          <Image
            src={productImage}
            alt={productTitle}
            fill
            className="object-contain p-4 group-hover:scale-105 transition-transform"
          />
        ) : (
          <div className="w-full h-full flex items-center justify-center text-gray-400">
            <span className="text-6xl">📦</span>
          </div>
        )}
      </Link>
      <div className="p-4">
        <div className="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400 mb-2">
          <span>{brandName || 'Unknown'}</span>
          <span>•</span>
          <span>{categoryName || 'Product'}</span>
        </div>
        <Link href={`/products/${product.id}`}>
          <h3 className="font-medium text-gray-900 dark:text-white line-clamp-2 mb-2 hover:text-[#FDB813] transition-colors">
            {productTitle}
          </h3>
        </Link>
        {(product.storage || product.color || product.ram) && (
          <div className="flex items-center gap-2 mb-3 flex-wrap">
            {product.storage && (
              <span className="px-2 py-1 bg-gray-100 dark:bg-gray-700 text-xs rounded text-gray-700 dark:text-gray-300">
                {product.storage}
              </span>
            )}
            {product.color && (
              <span className="px-2 py-1 bg-gray-100 dark:bg-gray-700 text-xs rounded text-gray-700 dark:text-gray-300">
                {product.color}
              </span>
            )}
          </div>
        )}
        <div className="flex items-center justify-between mb-3">
          <div>
            <span className="text-xl font-bold text-gray-900 dark:text-white">
              €{productPrice.toFixed(2)}
            </span>
          </div>
          <span
            className={`text-xs px-2 py-1 rounded ${
              stockQty > 10
                ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                : 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400'
            }`}
          >
            {stockQty > 10 ? 'In Stock' : 'Low Stock'}
          </span>
        </div>
        <button
          onClick={handleAddToCart}
          disabled={stockQty < 1}
          className="w-full py-2.5 px-4 bg-[#00a046] hover:bg-[#008037] text-white rounded-lg font-semibold transition-all flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <ShoppingCartIcon className="w-4 h-4" />
          Add to Cart
        </button>
      </div>
    </div>
  );
}
