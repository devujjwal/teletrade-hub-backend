import { MainLayout } from '@/components/layout/MainLayout';

export const metadata = {
  title: 'Returns & Refunds',
  description: 'Returns and refunds policy for TeleTrade Hub',
};

export default function ReturnsPage() {
  return (
    <MainLayout>
      <div className="container mx-auto px-4 py-16 max-w-4xl">
        <h1 className="text-4xl font-bold mb-8">Returns & Refunds</h1>

        <div className="prose prose-lg max-w-none space-y-8">
          <section>
            <h2 className="text-2xl font-bold mb-4">14-Day Return Policy</h2>
            <p className="text-foreground/80 leading-relaxed">
              You have the right to cancel your order within 14 days of receiving
              your product, without giving any reason. To exercise your right of
              withdrawal, please contact us.
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-bold mb-4">Return Conditions</h2>
            <p className="text-foreground/80 leading-relaxed mb-4">
              To be eligible for a return, your item must be:
            </p>
            <ul className="list-disc list-inside space-y-2 text-foreground/80 ml-4">
              <li>In its original condition and packaging</li>
              <li>Unused and undamaged</li>
              <li>Complete with all accessories and documentation</li>
              <li>Returned within 14 days of receipt</li>
            </ul>
          </section>

          <section>
            <h2 className="text-2xl font-bold mb-4">Return Process</h2>
            <ol className="list-decimal list-inside space-y-3 text-foreground/80 ml-4">
              <li>
                <strong>Contact Us:</strong> Email us at{' '}
                <a href="mailto:info@teletrade-hub.com" className="text-accent hover:underline">
                  info@teletrade-hub.com
                </a>{' '}
                with your order number
              </li>
              <li>
                <strong>Get Authorization:</strong> We'll provide a return authorization
                and shipping instructions
              </li>
              <li>
                <strong>Ship the Item:</strong> Pack the product securely and ship to
                our returns address
              </li>
              <li>
                <strong>Refund Processing:</strong> Once we receive and inspect your
                return, we'll process your refund
              </li>
            </ol>
          </section>

          <section>
            <h2 className="text-2xl font-bold mb-4">Refund Timeline</h2>
            <p className="text-foreground/80 leading-relaxed">
              Refunds are typically processed within 5-7 business days after we receive
              your return. The refund will be issued to your original payment method.
              Please allow additional time for your bank to process the refund.
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-bold mb-4">Return Shipping Costs</h2>
            <p className="text-foreground/80 leading-relaxed">
              Return shipping costs are the responsibility of the customer, unless the
              product is defective or we made an error in your order. In such cases,
              we will cover the return shipping costs.
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-bold mb-4">Non-Returnable Items</h2>
            <p className="text-foreground/80 leading-relaxed mb-4">
              The following items cannot be returned:
            </p>
            <ul className="list-disc list-inside space-y-2 text-foreground/80 ml-4">
              <li>Products with broken seals or tampered packaging</li>
              <li>Items marked as final sale or clearance</li>
              <li>Products damaged by customer misuse</li>
            </ul>
          </section>

          <section>
            <h2 className="text-2xl font-bold mb-4">Defective Products</h2>
            <p className="text-foreground/80 leading-relaxed">
              If you receive a defective product, please contact us immediately at{' '}
              <a href="mailto:info@teletrade-hub.com" className="text-accent hover:underline">
                info@teletrade-hub.com
              </a>
              . We will arrange for a replacement or full refund, including return
              shipping costs.
            </p>
          </section>

          <section className="p-6 bg-accent/10 rounded-lg border border-accent">
            <h3 className="text-xl font-bold mb-3">Need Help?</h3>
            <p className="text-foreground/80">
              For any questions about returns or refunds, please contact our customer
              service team at{' '}
              <a href="mailto:info@teletrade-hub.com" className="text-accent hover:underline">
                info@teletrade-hub.com
              </a>{' '}
              or call{' '}
              <a href="tel:+491234567890" className="text-accent hover:underline">
                +49 123 456 7890
              </a>
            </p>
          </section>
        </div>
      </div>
    </MainLayout>
  );
}

