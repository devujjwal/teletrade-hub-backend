import { cn } from '@/lib/utils';

interface LoadingProps {
  size?: 'sm' | 'md' | 'lg';
  fullScreen?: boolean;
}

export function Loading({ size = 'md', fullScreen = false }: LoadingProps) {
  const spinnerSize = {
    sm: 'w-6 h-6',
    md: 'w-12 h-12',
    lg: 'w-16 h-16',
  };

  const spinner = (
    <div
      className={cn(
        'border-4 border-accent border-t-transparent rounded-full animate-spin',
        spinnerSize[size]
      )}
    />
  );

  if (fullScreen) {
    return (
      <div className="fixed inset-0 flex items-center justify-center bg-background/80 backdrop-blur-sm z-50">
        {spinner}
      </div>
    );
  }

  return <div className="flex items-center justify-center p-8">{spinner}</div>;
}

export function LoadingDots() {
  return (
    <div className="flex space-x-2">
      <div className="w-2 h-2 bg-accent rounded-full animate-pulse" />
      <div className="w-2 h-2 bg-accent rounded-full animate-pulse delay-75" />
      <div className="w-2 h-2 bg-accent rounded-full animate-pulse delay-150" />
    </div>
  );
}

