/**
 * Image Proxy Utility
 * 
 * Converts vendor image URLs to proxied URLs served through our domain
 */

/**
 * Check if a URL is from a vendor domain that should be proxied
 */
export function isVendorImage(url: string | null | undefined): boolean {
  if (!url) return false;
  
  const vendorDomains = ['images.triel.sk'];
  try {
    const parsedUrl = new URL(url);
    return vendorDomains.some(domain => 
      parsedUrl.hostname === domain || parsedUrl.hostname.endsWith(`.${domain}`)
    );
  } catch {
    return false;
  }
}

/**
 * Convert vendor image URL to proxied URL
 * 
 * @param vendorUrl - The original vendor image URL
 * @returns Proxied URL that serves through our domain, or original URL if not a vendor image
 */
export function getProxiedImageUrl(vendorUrl: string | null | undefined): string {
  if (!vendorUrl) return '';
  
  // If it's not a vendor image, return as-is
  if (!isVendorImage(vendorUrl)) {
    return vendorUrl;
  }

  // Encode the vendor URL and create proxy URL
  const encodedUrl = encodeURIComponent(vendorUrl);
  return `/api/images/vendor?url=${encodedUrl}`;
}

/**
 * Get proxied image URL with fallback
 * 
 * @param imageUrl - The image URL (vendor or local)
 * @param fallback - Fallback URL if imageUrl is empty
 * @returns Proxied URL or fallback
 */
export function getImageUrl(imageUrl: string | null | undefined, fallback?: string): string {
  if (!imageUrl) return fallback || '';
  return getProxiedImageUrl(imageUrl);
}

