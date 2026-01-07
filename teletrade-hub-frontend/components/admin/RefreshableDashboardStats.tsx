'use client';

import { useState, useCallback } from 'react';
import { 
  CurrencyDollarIcon, 
  ShoppingCartIcon, 
  CubeIcon,
} from '@heroicons/react/24/outline';
import { RefreshableSection } from '@/components/RefreshableSection';
import { endpoints } from '@/lib/api';

interface DashboardData {
  totalRevenue: number;
  monthlyRevenue: number;
  totalOrders: number;
  pendingOrders: number;
  totalProducts: number;
  availableProducts: number;
  todayRevenue: number;
}

interface RefreshableDashboardStatsProps {
  initialData: DashboardData;
}

export function RefreshableDashboardStats({ initialData }: RefreshableDashboardStatsProps) {
  const [data, setData] = useState(initialData);

  const refreshData = useCallback(async () => {
    try {
      const response = await endpoints.admin.dashboard();
      if (response.data) {
        setData(response.data);
      }
    } catch (error) {
      console.error('Error refreshing dashboard data:', error);
    }
  }, []);

  const stats = [
    {
      title: 'Total Revenue',
      value: `€${data.totalRevenue?.toFixed(2) || '0.00'}`,
      subtitle: `€${data.monthlyRevenue?.toFixed(2) || '0'} this month`,
      icon: CurrencyDollarIcon,
      iconBg: 'bg-green-100 dark:bg-green-900/30',
      iconColor: 'text-green-600 dark:text-green-400',
    },
    {
      title: 'Total Orders',
      value: data.totalOrders || 0,
      subtitle: `${data.pendingOrders || 0} pending`,
      icon: ShoppingCartIcon,
      iconBg: 'bg-blue-100 dark:bg-blue-900/30',
      iconColor: 'text-blue-600 dark:text-blue-400',
    },
    {
      title: 'Products',
      value: data.totalProducts || 0,
      subtitle: `${data.availableProducts || 0} available`,
      icon: CubeIcon,
      iconBg: 'bg-yellow-100 dark:bg-yellow-900/30',
      iconColor: 'text-yellow-600 dark:text-yellow-400',
    },
    {
      title: 'Today Revenue',
      value: `€${data.todayRevenue?.toFixed(2) || '0.00'}`,
      subtitle: 'Real-time tracking',
      icon: CurrencyDollarIcon,
      iconBg: 'bg-purple-100 dark:bg-purple-900/30',
      iconColor: 'text-purple-600 dark:text-purple-400',
    },
  ];

  return (
    <RefreshableSection onRefresh={refreshData} refreshInterval={30000}>
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        {stats.map((stat, index) => {
          const Icon = stat.icon;
          return (
            <div
              key={index}
              className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 hover:shadow-lg transition-shadow"
            >
              <div className="flex items-start justify-between">
                <div className="flex-1">
                  <p className="text-sm text-gray-600 dark:text-gray-400 mb-1">
                    {stat.title}
                  </p>
                  <p className="text-2xl font-bold text-gray-900 dark:text-white mb-1">
                    {stat.value}
                  </p>
                  <p className="text-xs text-gray-500 dark:text-gray-400">
                    {stat.subtitle}
                  </p>
                </div>
                <div className={`p-3 rounded-lg ${stat.iconBg}`}>
                  <Icon className={`w-6 h-6 ${stat.iconColor}`} />
                </div>
              </div>
            </div>
          );
        })}
      </div>
    </RefreshableSection>
  );
}

