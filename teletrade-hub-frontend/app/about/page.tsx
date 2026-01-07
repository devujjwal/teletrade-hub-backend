import { MainLayout } from '@/components/layout/MainLayout';

export const metadata = {
  title: 'About Us',
  description: 'Learn more about TeleTrade Hub and Telecommunication Trading e.K.',
};

export default function AboutPage() {
  return (
    <MainLayout>
      <div className="container mx-auto px-4 py-16 max-w-4xl">
        <h1 className="text-4xl font-bold mb-8">About TeleTrade Hub</h1>

        <div className="prose prose-lg max-w-none space-y-8">
          <section>
            <h2 className="text-2xl font-bold mb-4">Who We Are</h2>
            <p className="text-foreground/80 leading-relaxed">
              TeleTrade Hub is operated by <strong>Telecommunication Trading e.K.</strong>,
              a trusted name in premium electronics and telecommunications equipment.
              Based in Germany, we specialize in providing authentic, high-quality products
              from the world's leading technology brands.
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-bold mb-4">Our Mission</h2>
            <p className="text-foreground/80 leading-relaxed">
              Our mission is to provide customers across Europe with access to the latest
              smartphones, tablets, accessories, and telecommunications equipment at
              competitive prices. We are committed to authenticity, quality, and
              exceptional customer service.
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-bold mb-4">Why Choose Us</h2>
            <ul className="list-disc list-inside space-y-2 text-foreground/80 ml-4">
              <li>100% authentic products from authorized distributors</li>
              <li>Competitive pricing with transparent markup</li>
              <li>Fast and reliable shipping across Europe</li>
              <li>Manufacturer warranties on all products</li>
              <li>Secure payment processing</li>
              <li>Responsive customer support</li>
              <li>Easy returns and refunds</li>
            </ul>
          </section>

          <section>
            <h2 className="text-2xl font-bold mb-4">Our Product Range</h2>
            <p className="text-foreground/80 leading-relaxed">
              We offer an extensive catalog of premium electronics, including:
            </p>
            <ul className="list-disc list-inside space-y-2 text-foreground/80 ml-4">
              <li>Smartphones from Apple, Samsung, Xiaomi, and more</li>
              <li>Tablets and e-readers</li>
              <li>Smart accessories and wearables</li>
              <li>Telecommunications equipment</li>
              <li>Audio devices and headphones</li>
            </ul>
          </section>

          <section>
            <h2 className="text-2xl font-bold mb-4">Quality Guarantee</h2>
            <p className="text-foreground/80 leading-relaxed">
              All products sold through TeleTrade Hub are sourced from authorized
              distributors and come with full manufacturer warranties. We guarantee
              the authenticity and quality of every item we sell.
            </p>
          </section>

          <section className="p-6 bg-accent/10 rounded-lg border border-accent">
            <h3 className="text-xl font-bold mb-3">Get Started Today</h3>
            <p className="text-foreground/80 mb-4">
              Browse our catalog and experience the TeleTrade Hub difference.
            </p>
            <a
              href="/products"
              className="inline-block px-6 py-3 bg-primary text-white rounded-lg hover:bg-primary-dark transition-colors"
            >
              Shop Now
            </a>
          </section>
        </div>
      </div>
    </MainLayout>
  );
}

