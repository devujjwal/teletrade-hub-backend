import { MainLayout } from '@/components/layout/MainLayout';
import { EnvelopeIcon, PhoneIcon, MapPinIcon } from '@heroicons/react/24/outline';

export const metadata = {
  title: 'Contact Us',
  description: 'Get in touch with TeleTrade Hub',
};

export default function ContactPage() {
  return (
    <MainLayout>
      <div className="container mx-auto px-4 py-16 max-w-4xl">
        <h1 className="text-4xl font-bold mb-8">Contact Us</h1>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
          {/* Contact Information */}
          <div className="space-y-6">
            <div>
              <h2 className="text-2xl font-bold mb-4">Get in Touch</h2>
              <p className="text-foreground/80 leading-relaxed mb-6">
                Have questions about our products or services? We're here to help!
                Reach out to us using any of the methods below.
              </p>
            </div>

            <div className="space-y-4">
              <div className="flex items-start gap-4">
                <MapPinIcon className="w-6 h-6 text-accent flex-shrink-0 mt-1" />
                <div>
                  <h3 className="font-semibold mb-1">Address</h3>
                  <p className="text-foreground/70">
                    Telecommunication Trading e.K.<br />
                    Germany
                  </p>
                </div>
              </div>

              <div className="flex items-start gap-4">
                <EnvelopeIcon className="w-6 h-6 text-accent flex-shrink-0 mt-1" />
                <div>
                  <h3 className="font-semibold mb-1">Email</h3>
                  <a
                    href="mailto:info@teletrade-hub.com"
                    className="text-accent hover:underline"
                  >
                    info@teletrade-hub.com
                  </a>
                </div>
              </div>

              <div className="flex items-start gap-4">
                <PhoneIcon className="w-6 h-6 text-accent flex-shrink-0 mt-1" />
                <div>
                  <h3 className="font-semibold mb-1">Phone</h3>
                  <a
                    href="tel:+491234567890"
                    className="text-accent hover:underline"
                  >
                    +49 123 456 7890
                  </a>
                </div>
              </div>
            </div>

            <div className="pt-6">
              <h3 className="font-semibold mb-2">Business Hours</h3>
              <p className="text-foreground/70">
                Monday - Friday: 9:00 AM - 6:00 PM<br />
                Saturday: 10:00 AM - 4:00 PM<br />
                Sunday: Closed
              </p>
            </div>
          </div>

          {/* Contact Form */}
          <div className="p-6 bg-muted rounded-lg border border-border">
            <h2 className="text-2xl font-bold mb-4">Send us a Message</h2>
            <form className="space-y-4">
              <div>
                <label className="block text-sm font-medium mb-2">Name</label>
                <input
                  type="text"
                  required
                  className="w-full px-4 py-2 rounded-lg bg-background border border-border focus:outline-none focus:border-accent"
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-2">Email</label>
                <input
                  type="email"
                  required
                  className="w-full px-4 py-2 rounded-lg bg-background border border-border focus:outline-none focus:border-accent"
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-2">Subject</label>
                <input
                  type="text"
                  required
                  className="w-full px-4 py-2 rounded-lg bg-background border border-border focus:outline-none focus:border-accent"
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-2">Message</label>
                <textarea
                  required
                  rows={5}
                  className="w-full px-4 py-2 rounded-lg bg-background border border-border focus:outline-none focus:border-accent"
                />
              </div>
              <button
                type="submit"
                className="w-full px-6 py-3 bg-primary text-white rounded-lg hover:bg-primary-dark transition-colors"
              >
                Send Message
              </button>
            </form>
          </div>
        </div>
      </div>
    </MainLayout>
  );
}

