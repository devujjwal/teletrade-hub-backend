'use client';

import { useEffect, useState } from 'react';
import { ArrowPathIcon } from '@heroicons/react/24/outline';

interface RefreshableSectionProps {
  children: React.ReactNode;
  refreshInterval?: number; // in milliseconds
  onRefresh?: () => Promise<void>;
  showRefreshButton?: boolean;
}

export function RefreshableSection({
  children,
  refreshInterval = 30000, // Default 30 seconds
  onRefresh,
  showRefreshButton = true,
}: RefreshableSectionProps) {
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [lastRefresh, setLastRefresh] = useState(new Date());
  const [isMounted, setIsMounted] = useState(false);

  // Only render time on client to avoid hydration mismatch
  useEffect(() => {
    setIsMounted(true);
  }, []);

  useEffect(() => {
    if (!onRefresh) return;

    const interval = setInterval(async () => {
      await handleRefresh();
    }, refreshInterval);

    return () => clearInterval(interval);
  }, [refreshInterval, onRefresh]);

  const handleRefresh = async () => {
    if (!onRefresh || isRefreshing) return;
    
    setIsRefreshing(true);
    try {
      await onRefresh();
      setLastRefresh(new Date());
    } catch (error) {
      console.error('Error refreshing data:', error);
    } finally {
      setIsRefreshing(false);
    }
  };

  return (
    <div className="relative">
      {showRefreshButton && (
        <div className="absolute top-0 right-0 z-10">
          <button
            onClick={handleRefresh}
            disabled={isRefreshing}
            className="inline-flex items-center gap-2 px-3 py-1.5 text-sm text-gray-600 dark:text-gray-400 hover:text-[#FDB813] transition-colors disabled:opacity-50"
            title={isMounted ? `Last refresh: ${lastRefresh.toLocaleTimeString('en-US', { hour12: false })}` : 'Click to refresh'}
          >
            <ArrowPathIcon
              className={`w-4 h-4 ${isRefreshing ? 'animate-spin' : ''}`}
            />
            {isRefreshing ? 'Refreshing...' : 'Refresh'}
          </button>
        </div>
      )}
      {children}
    </div>
  );
}

