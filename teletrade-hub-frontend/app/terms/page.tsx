import { MainLayout } from '@/components/layout/MainLayout';

export const metadata = {
  title: 'Terms & Conditions',
  description: 'Terms and conditions for shopping at TeleTrade Hub',
};

export default function TermsPage() {
  return (
    <MainLayout>
      <div className="container mx-auto px-4 py-16 max-w-4xl">
        <h1 className="text-4xl font-bold mb-8">Terms & Conditions</h1>

        <div className="prose prose-lg max-w-none space-y-8">
          <section>
            <h2 className="text-2xl font-bold mb-4">1. General Information</h2>
            <p className="text-foreground/80 leading-relaxed">
              These Terms and Conditions govern your use of the TeleTrade Hub website and
              the purchase of products from Telecommunication Trading e.K. By using our
              website and placing orders, you agree to be bound by these terms.
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-bold mb-4">2. Products and Pricing</h2>
            <p className="text-foreground/80 leading-relaxed">
              All products are subject to availability. Prices are listed in Euros and
              include VAT where applicable. We reserve the right to modify prices at any
              time without prior notice. However, changes will not affect orders that have
              already been confirmed.
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-bold mb-4">3. Orders and Payment</h2>
            <p className="text-foreground/80 leading-relaxed">
              By placing an order, you make a binding offer to purchase the products.
              We reserve the right to accept or reject orders. Payment must be received
              before orders are processed. We accept credit/debit cards, PayPal, and
              bank transfers.
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-bold mb-4">4. Delivery</h2>
            <p className="text-foreground/80 leading-relaxed">
              Delivery times are estimates and not guaranteed. We are not liable for
              delays caused by circumstances beyond our control. Risk of loss or damage
              transfers to you upon delivery.
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-bold mb-4">5. Returns and Refunds</h2>
            <p className="text-foreground/80 leading-relaxed">
              You have the right to cancel your order within 14 days of receipt without
              giving any reason. Products must be returned in their original condition
              and packaging. Shipping costs for returns are the responsibility of the
              customer unless the product is defective.
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-bold mb-4">6. Warranty</h2>
            <p className="text-foreground/80 leading-relaxed">
              All products come with the manufacturer's warranty as specified. We are
              not responsible for manufacturer defects but will assist with warranty claims.
              Warranty does not cover damage caused by misuse, accidents, or normal wear
              and tear.
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-bold mb-4">7. Liability</h2>
            <p className="text-foreground/80 leading-relaxed">
              Our liability is limited to the purchase price of the product. We are not
              liable for indirect or consequential damages. This does not affect your
              statutory rights as a consumer.
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-bold mb-4">8. Data Protection</h2>
            <p className="text-foreground/80 leading-relaxed">
              Your personal data will be processed in accordance with our{' '}
              <a href="/privacy" className="text-accent hover:underline">Privacy Policy</a>
              {' '}and applicable data protection laws.
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-bold mb-4">9. Governing Law</h2>
            <p className="text-foreground/80 leading-relaxed">
              These Terms and Conditions are governed by German law. Any disputes shall
              be subject to the exclusive jurisdiction of German courts.
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-bold mb-4">10. Contact</h2>
            <p className="text-foreground/80 leading-relaxed">
              Telecommunication Trading e.K.<br />
              Email: <a href="mailto:info@teletrade-hub.com" className="text-accent hover:underline">
                info@teletrade-hub.com
              </a><br />
              Phone: <a href="tel:+491234567890" className="text-accent hover:underline">
                +49 123 456 7890
              </a>
            </p>
          </section>

          <p className="text-sm text-foreground/60 pt-8 border-t border-border">
            Last updated: {new Date().toLocaleDateString()}
          </p>
        </div>
      </div>
    </MainLayout>
  );
}

