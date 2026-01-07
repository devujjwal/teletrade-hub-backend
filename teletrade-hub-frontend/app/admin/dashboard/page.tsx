import { 
  ClockIcon,
  TruckIcon,
  CheckCircleIcon,
  XCircleIcon,
  ArchiveBoxIcon,
  ShoppingCartIcon
} from '@heroicons/react/24/outline';
import Link from 'next/link';
import { AdminLayout } from '@/components/layout/AdminLayout';
import { RefreshableDashboardStats } from '@/components/admin/RefreshableDashboardStats';
import { serverFetch } from '@/lib/server-fetch';

// Server-side data fetching
async function getDashboardData() {
  try {
    const API_URL = process.env.NEXT_PUBLIC_API_URL || 'https://api.vs-mjrinfotech.com';
    
    // In production, this would use the admin token from the request
    const response = await serverFetch(`${API_URL}/admin/dashboard`, {
      next: { revalidate: 60 },
      headers: {
        'Authorization': `Bearer ${process.env.ADMIN_TOKEN || ''}`,
      },
    });

    if (response.ok) {
      const data = await response.json();
      return data.data;
    }

    return {
      totalRevenue: 0,
      monthlyRevenue: 0,
      totalOrders: 0,
      pendingOrders: 0,
      totalProducts: 0,
      availableProducts: 0,
      todayRevenue: 0,
      ordersByStatus: {
        pending: 0,
        processing: 0,
        shipped: 0,
        delivered: 0,
        cancelled: 0,
      },
      recentOrders: [],
    };
  } catch (error) {
    console.error('Error fetching dashboard data:', error);
    return {
      totalRevenue: 0,
      monthlyRevenue: 0,
      totalOrders: 0,
      pendingOrders: 0,
      totalProducts: 0,
      availableProducts: 0,
      todayRevenue: 0,
      ordersByStatus: {
        pending: 0,
        processing: 0,
        shipped: 0,
        delivered: 0,
        cancelled: 0,
      },
      recentOrders: [],
    };
  }
}

export default async function AdminDashboardPage() {
  const data = await getDashboardData();

  const orderStatuses = [
    {
      label: 'Pending',
      count: data.ordersByStatus?.pending || 0,
      icon: ClockIcon,
      color: 'text-orange-500',
      bg: 'bg-orange-50 dark:bg-orange-900/20',
    },
    {
      label: 'Processing',
      count: data.ordersByStatus?.processing || 0,
      icon: ArchiveBoxIcon,
      color: 'text-blue-500',
      bg: 'bg-blue-50 dark:bg-blue-900/20',
    },
    {
      label: 'Shipped',
      count: data.ordersByStatus?.shipped || 0,
      icon: TruckIcon,
      color: 'text-indigo-500',
      bg: 'bg-indigo-50 dark:bg-indigo-900/20',
    },
    {
      label: 'Delivered',
      count: data.ordersByStatus?.delivered || 0,
      icon: CheckCircleIcon,
      color: 'text-green-500',
      bg: 'bg-green-50 dark:bg-green-900/20',
    },
    {
      label: 'Cancelled',
      count: data.ordersByStatus?.cancelled || 0,
      icon: XCircleIcon,
      color: 'text-red-500',
      bg: 'bg-red-50 dark:bg-red-900/20',
    },
  ];

  return (
    <AdminLayout>
      <div className="space-y-8">
        {/* Page Header */}
        <div>
          <h1 className="text-3xl font-bold text-gray-900 dark:text-white mb-2">
            Dashboard
          </h1>
          <p className="text-gray-600 dark:text-gray-400">
            Welcome back! Here's your store overview.
          </p>
        </div>

        {/* Stats Grid - Client-side refreshable */}
        <RefreshableDashboardStats initialData={{
          totalRevenue: data.totalRevenue || 0,
          monthlyRevenue: data.monthlyRevenue || 0,
          totalOrders: data.totalOrders || 0,
          pendingOrders: data.pendingOrders || 0,
          totalProducts: data.totalProducts || 0,
          availableProducts: data.availableProducts || 0,
          todayRevenue: data.todayRevenue || 0,
        }} />

        {/* Order Status Overview */}
        <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
          <div className="mb-6">
            <h2 className="text-xl font-bold text-gray-900 dark:text-white mb-1">
              Order Status Overview
            </h2>
            <p className="text-sm text-gray-600 dark:text-gray-400">
              Current distribution of orders by status
            </p>
          </div>
          <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
            {orderStatuses.map((status, index) => {
              const Icon = status.icon;
              return (
                <div
                  key={index}
                  className={`${status.bg} rounded-xl p-6 text-center`}
                >
                  <div className="flex justify-center mb-3">
                    <Icon className={`w-8 h-8 ${status.color}`} />
                  </div>
                  <p className="text-2xl font-bold text-gray-900 dark:text-white mb-1">
                    {status.count}
                  </p>
                  <p className="text-sm text-gray-600 dark:text-gray-400">
                    {status.label}
                  </p>
                </div>
              );
            })}
          </div>
        </div>

        {/* Recent Orders */}
        <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
          <div className="flex items-center justify-between mb-6">
            <div>
              <h2 className="text-xl font-bold text-gray-900 dark:text-white mb-1">
                Recent Orders
              </h2>
              <p className="text-sm text-gray-600 dark:text-gray-400">
                Latest orders from your store
              </p>
            </div>
            <Link
              href="/admin/orders"
              className="text-[#FDB813] hover:text-[#F59E0B] font-medium text-sm transition-colors"
            >
              View All
            </Link>
          </div>

          {data.recentOrders && data.recentOrders.length > 0 ? (
            <div className="space-y-4">
              {data.recentOrders.map((order: any) => (
                <div
                  key={order.id}
                  className="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                >
                  <div>
                    <p className="font-semibold text-gray-900 dark:text-white">
                      #{order.order_number}
                    </p>
                    <span
                      className={`inline-block mt-1 px-2 py-1 text-xs rounded-full ${
                        order.status === 'pending'
                          ? 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400'
                          : order.status === 'processing'
                          ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
                          : order.status === 'shipped'
                          ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400'
                          : order.status === 'delivered'
                          ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                          : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
                      }`}
                    >
                      {order.status}
                    </span>
                  </div>
                  <div className="text-right">
                    <p className="font-semibold text-gray-900 dark:text-white">
                      €{order.total_amount?.toFixed(2)}
                    </p>
                    <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                      {new Date(order.created_at).toLocaleDateString()}
                    </p>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <div className="text-center py-12">
              <ShoppingCartIcon className="w-12 h-12 mx-auto text-gray-400 mb-4" />
              <p className="text-gray-600 dark:text-gray-400">
                No orders yet
              </p>
            </div>
          )}
        </div>
      </div>
    </AdminLayout>
  );
}
