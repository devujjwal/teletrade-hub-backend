import { MainLayout } from '@/components/layout/MainLayout';
import { Input } from '@/components/ui/Input';
import { Button } from '@/components/ui/Button';

export const metadata = {
  title: 'Track Order',
  description: 'Track your order status',
};

export default function TrackOrderPage() {
  return (
    <MainLayout>
      <div className="container mx-auto px-4 py-16">
        <div className="max-w-2xl mx-auto">
          <div className="text-center mb-8">
            <h1 className="text-4xl font-bold mb-4">Track Your Order</h1>
            <p className="text-foreground/70">
              Enter your order number to check the status of your order
            </p>
          </div>

          <div className="p-8 bg-muted rounded-lg border border-border">
            <form className="space-y-6">
              <Input
                label="Order Number"
                placeholder="TT260105ABCDEF"
                required
              />
              <Input
                label="Email Address"
                type="email"
                placeholder="your@email.com"
                required
              />
              <Button
                type="submit"
                variant="primary"
                size="lg"
                className="w-full"
              >
                Track Order
              </Button>
            </form>
          </div>

          <div className="mt-8 p-6 bg-accent/10 rounded-lg border border-accent">
            <h3 className="font-semibold text-lg mb-2">Need Help?</h3>
            <p className="text-foreground/80">
              If you can't find your order number or need assistance, please{' '}
              <a href="/contact" className="text-accent hover:underline">
                contact us
              </a>{' '}
              and we'll be happy to help.
            </p>
          </div>
        </div>
      </div>
    </MainLayout>
  );
}

