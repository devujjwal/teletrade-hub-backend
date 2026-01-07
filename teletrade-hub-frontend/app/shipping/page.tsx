import { MainLayout } from '@/components/layout/MainLayout';

export const metadata = {
  title: 'Shipping Information',
  description: 'Shipping and delivery information for TeleTrade Hub',
};

export default function ShippingPage() {
  return (
    <MainLayout>
      <div className="container mx-auto px-4 py-16 max-w-4xl">
        <h1 className="text-4xl font-bold mb-8">Shipping Information</h1>

        <div className="prose prose-lg max-w-none space-y-8">
          <section>
            <h2 className="text-2xl font-bold mb-4">Shipping Costs</h2>
            <p className="text-foreground/80 leading-relaxed">
              Standard shipping within the EU: <strong>€9.99</strong>
            </p>
            <p className="text-foreground/80 leading-relaxed">
              <strong>Free shipping</strong> on orders over <strong>€100</strong>
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-bold mb-4">Delivery Times</h2>
            <div className="space-y-4">
              <div>
                <h3 className="font-bold mb-2">Germany</h3>
                <p className="text-foreground/80">2-3 business days</p>
              </div>
              <div>
                <h3 className="font-bold mb-2">Austria, Switzerland</h3>
                <p className="text-foreground/80">3-5 business days</p>
              </div>
              <div>
                <h3 className="font-bold mb-2">Other EU Countries</h3>
                <p className="text-foreground/80">5-7 business days</p>
              </div>
            </div>
          </section>

          <section>
            <h2 className="text-2xl font-bold mb-4">Tracking</h2>
            <p className="text-foreground/80 leading-relaxed">
              All orders are shipped with tracking. You'll receive a tracking number
              via email once your order has been dispatched. You can track your package
              at any time through our website or the courier's tracking system.
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-bold mb-4">Packaging</h2>
            <p className="text-foreground/80 leading-relaxed">
              All products are carefully packaged to ensure they arrive in perfect condition.
              We use eco-friendly packaging materials whenever possible.
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-bold mb-4">Customs and Duties</h2>
            <p className="text-foreground/80 leading-relaxed">
              For shipments within the EU, there are no additional customs duties or import
              taxes. For non-EU countries (such as Switzerland), customers are responsible
              for any applicable customs duties and taxes.
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-bold mb-4">Lost or Damaged Items</h2>
            <p className="text-foreground/80 leading-relaxed">
              If your order arrives damaged or doesn't arrive at all, please contact us
              immediately at <a href="mailto:info@teletrade-hub.com" className="text-accent hover:underline">
              info@teletrade-hub.com</a>. We'll work with you to resolve the issue as
              quickly as possible.
            </p>
          </section>
        </div>
      </div>
    </MainLayout>
  );
}

