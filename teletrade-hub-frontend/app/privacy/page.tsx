import { MainLayout } from '@/components/layout/MainLayout';

export const metadata = {
  title: 'Privacy Policy',
  description: 'Privacy policy for TeleTrade Hub',
};

export default function PrivacyPage() {
  return (
    <MainLayout>
      <div className="container mx-auto px-4 py-16 max-w-4xl">
        <h1 className="text-4xl font-bold mb-8">Privacy Policy</h1>

        <div className="prose prose-lg max-w-none space-y-8">
          <section>
            <h2 className="text-2xl font-bold mb-4">1. Data Controller</h2>
            <p className="text-foreground/80 leading-relaxed">
              The data controller for this website is:<br />
              Telecommunication Trading e.K.<br />
              Email: <a href="mailto:info@teletrade-hub.com" className="text-accent hover:underline">
                info@teletrade-hub.com
              </a>
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-bold mb-4">2. Data Collection</h2>
            <p className="text-foreground/80 leading-relaxed">
              We collect and process the following types of personal data:
            </p>
            <ul className="list-disc list-inside space-y-2 text-foreground/80 ml-4">
              <li>Contact information (name, email, phone number)</li>
              <li>Billing and shipping addresses</li>
              <li>Order history and preferences</li>
              <li>Payment information (processed securely by payment providers)</li>
              <li>Website usage data (cookies, IP address, browser information)</li>
            </ul>
          </section>

          <section>
            <h2 className="text-2xl font-bold mb-4">3. Purpose of Data Processing</h2>
            <p className="text-foreground/80 leading-relaxed">
              We use your personal data for the following purposes:
            </p>
            <ul className="list-disc list-inside space-y-2 text-foreground/80 ml-4">
              <li>Processing and fulfilling your orders</li>
              <li>Communicating with you about orders and services</li>
              <li>Improving our website and services</li>
              <li>Marketing (only with your consent)</li>
              <li>Complying with legal obligations</li>
            </ul>
          </section>

          <section>
            <h2 className="text-2xl font-bold mb-4">4. Legal Basis</h2>
            <p className="text-foreground/80 leading-relaxed">
              We process your personal data based on:
            </p>
            <ul className="list-disc list-inside space-y-2 text-foreground/80 ml-4">
              <li>Contract performance (processing orders)</li>
              <li>Legal obligations (tax, accounting)</li>
              <li>Legitimate interests (website security, fraud prevention)</li>
              <li>Your consent (marketing communications)</li>
            </ul>
          </section>

          <section>
            <h2 className="text-2xl font-bold mb-4">5. Data Sharing</h2>
            <p className="text-foreground/80 leading-relaxed">
              We may share your data with:
            </p>
            <ul className="list-disc list-inside space-y-2 text-foreground/80 ml-4">
              <li>Payment processors (for secure payment handling)</li>
              <li>Shipping companies (for order delivery)</li>
              <li>Legal authorities (when required by law)</li>
            </ul>
            <p className="text-foreground/80 leading-relaxed mt-4">
              We do not sell your personal data to third parties.
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-bold mb-4">6. Data Retention</h2>
            <p className="text-foreground/80 leading-relaxed">
              We retain your personal data only as long as necessary for the purposes
              outlined above or as required by law. Order data is typically retained
              for 10 years for tax and accounting purposes.
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-bold mb-4">7. Your Rights</h2>
            <p className="text-foreground/80 leading-relaxed">
              Under GDPR, you have the following rights:
            </p>
            <ul className="list-disc list-inside space-y-2 text-foreground/80 ml-4">
              <li>Right to access your personal data</li>
              <li>Right to rectification of inaccurate data</li>
              <li>Right to erasure ("right to be forgotten")</li>
              <li>Right to restrict processing</li>
              <li>Right to data portability</li>
              <li>Right to object to processing</li>
              <li>Right to withdraw consent</li>
            </ul>
            <p className="text-foreground/80 leading-relaxed mt-4">
              To exercise these rights, please contact us at{' '}
              <a href="mailto:info@teletrade-hub.com" className="text-accent hover:underline">
                info@teletrade-hub.com
              </a>
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-bold mb-4">8. Cookies</h2>
            <p className="text-foreground/80 leading-relaxed">
              Our website uses cookies to improve your browsing experience. Essential
              cookies are necessary for website functionality. You can control cookie
              settings in your browser.
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-bold mb-4">9. Security</h2>
            <p className="text-foreground/80 leading-relaxed">
              We implement appropriate technical and organizational measures to protect
              your personal data against unauthorized access, loss, or misuse.
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-bold mb-4">10. Changes to This Policy</h2>
            <p className="text-foreground/80 leading-relaxed">
              We may update this Privacy Policy from time to time. The latest version
              will always be available on this page.
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-bold mb-4">11. Contact</h2>
            <p className="text-foreground/80 leading-relaxed">
              If you have questions about this Privacy Policy or our data practices,
              please contact us at{' '}
              <a href="mailto:info@teletrade-hub.com" className="text-accent hover:underline">
                info@teletrade-hub.com
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

