import { MainLayout } from '@/components/layout/MainLayout';

export const metadata = {
  title: 'How to Buy',
  description: 'Learn how to purchase products from TeleTrade Hub',
};

export default function HowToBuyPage() {
  return (
    <MainLayout>
      <div className="container mx-auto px-4 py-16 max-w-4xl">
        <h1 className="text-4xl font-bold mb-8">How to Buy</h1>

        <div className="prose prose-lg max-w-none space-y-8">
          <section>
            <h2 className="text-2xl font-bold mb-4">1. Browse Products</h2>
            <p className="text-foreground/80 leading-relaxed">
              Browse our extensive catalog of electronics and telecommunications equipment.
              Use filters to find exactly what you need by category, brand, price range,
              or specifications.
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-bold mb-4">2. Add to Cart</h2>
            <p className="text-foreground/80 leading-relaxed">
              Once you find products you want to purchase, click the "Add to Cart" button.
              You can adjust quantities in your shopping cart before proceeding to checkout.
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-bold mb-4">3. Checkout</h2>
            <p className="text-foreground/80 leading-relaxed">
              Review your order and proceed to checkout. You'll need to provide:
            </p>
            <ul className="list-disc list-inside space-y-2 text-foreground/80 ml-4">
              <li>Contact information (email, phone)</li>
              <li>Billing address</li>
              <li>Shipping address (if different)</li>
              <li>Payment method selection</li>
            </ul>
          </section>

          <section>
            <h2 className="text-2xl font-bold mb-4">4. Payment</h2>
            <p className="text-foreground/80 leading-relaxed">
              We accept the following payment methods:
            </p>
            <ul className="list-disc list-inside space-y-2 text-foreground/80 ml-4">
              <li>Credit/Debit Cards (Visa, Mastercard, American Express)</li>
              <li>PayPal</li>
              <li>Bank Transfer</li>
            </ul>
          </section>

          <section>
            <h2 className="text-2xl font-bold mb-4">5. Order Confirmation</h2>
            <p className="text-foreground/80 leading-relaxed">
              After successful payment, you'll receive an order confirmation email with
              your order details and tracking information. You can also track your order
              status in your account.
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-bold mb-4">6. Delivery</h2>
            <p className="text-foreground/80 leading-relaxed">
              Your order will be processed and shipped according to our delivery times.
              You'll receive updates via email as your order progresses.
            </p>
          </section>

          <section className="p-6 bg-accent/10 rounded-lg border border-accent">
            <h3 className="text-xl font-bold mb-3">Need Help?</h3>
            <p className="text-foreground/80">
              If you have any questions about the ordering process, please don't hesitate
              to <a href="/contact" className="text-accent hover:underline">contact us</a>.
              Our customer service team is here to help.
            </p>
          </section>
        </div>
      </div>
    </MainLayout>
  );
}

