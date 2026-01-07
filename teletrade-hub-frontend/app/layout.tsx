import type { Metadata } from 'next';
import { Geist, Geist_Mono } from 'next/font/google';
import './globals.css';
import { ThemeProvider } from '@/components/providers/ThemeProvider';
import { ToastProvider } from '@/components/providers/ToastProvider';

const geistSans = Geist({
  variable: '--font-geist-sans',
  subsets: ['latin'],
});

const geistMono = Geist_Mono({
  variable: '--font-geist-mono',
  subsets: ['latin'],
});

export const metadata: Metadata = {
  title: {
    default: 'TeleTrade Hub - Premium Electronics & Telecommunications',
    template: '%s | TeleTrade Hub',
  },
  description:
    'Premium electronics and telecommunications equipment. Shop the latest smartphones, tablets, accessories, and more from top brands.',
  keywords: [
    'electronics',
    'telecommunications',
    'smartphones',
    'tablets',
    'accessories',
    'online shop',
  ],
  authors: [{ name: 'Telecommunication Trading e.K.' }],
  creator: 'Telecommunication Trading e.K.',
  publisher: 'Telecommunication Trading e.K.',
  metadataBase: new URL('https://teletrade-hub.vercel.app'),
  openGraph: {
    type: 'website',
    locale: 'en_US',
    url: 'https://teletrade-hub.vercel.app',
    title: 'TeleTrade Hub - Premium Electronics & Telecommunications',
    description:
      'Premium electronics and telecommunications equipment. Shop the latest smartphones, tablets, accessories, and more from top brands.',
    siteName: 'TeleTrade Hub',
  },
  twitter: {
    card: 'summary_large_image',
    title: 'TeleTrade Hub - Premium Electronics & Telecommunications',
    description:
      'Premium electronics and telecommunications equipment. Shop the latest smartphones, tablets, accessories, and more from top brands.',
  },
  robots: {
    index: true,
    follow: true,
    googleBot: {
      index: true,
      follow: true,
      'max-video-preview': -1,
      'max-image-preview': 'large',
      'max-snippet': -1,
    },
  },
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en" suppressHydrationWarning>
      <body
        className={`${geistSans.variable} ${geistMono.variable} antialiased`}
      >
        <ThemeProvider>
          <ToastProvider />
          {children}
        </ThemeProvider>
      </body>
    </html>
  );
}
