import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  images: {
    // Allow external images from vendor API
    remotePatterns: [
      {
        protocol: 'https',
        hostname: 'images.triel.sk',
      },
      {
        protocol: 'http',
        hostname: 'localhost',
      },
      {
        protocol: 'https',
        hostname: '**',
      },
    ],
    // For development, you might want to disable optimization to avoid SSL issues
    unoptimized: process.env.NODE_ENV === 'development',
  },
};

export default nextConfig;
