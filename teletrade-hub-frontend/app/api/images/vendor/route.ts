import { NextRequest, NextResponse } from 'next/server';
import https from 'https';
import http from 'http';

/**
 * Image Proxy API Route
 * 
 * Proxies images from vendor domains through our domain for:
 * - Branding consistency (images appear as our domain)
 * - Better SEO
 * - No hotlinking issues
 * - Performance optimization with caching
 * 
 * Usage: /api/images/vendor?url=<encoded_vendor_image_url>
 */

// Runtime configuration - use nodejs runtime for better fetch support
export const runtime = 'nodejs';
// Allow caching via headers (not force-dynamic)
export const dynamic = 'auto';

// Allowed vendor domains (security measure)
const ALLOWED_DOMAINS = [
  'images.triel.sk',
  // Add more vendor domains as needed
];

// Cache duration: 7 days (604800 seconds)
const CACHE_MAX_AGE = 604800;

export async function GET(request: NextRequest) {
  try {
    const searchParams = request.nextUrl.searchParams;
    const vendorUrl = searchParams.get('url');

    if (!vendorUrl) {
      return new NextResponse('Missing url parameter', { status: 400 });
    }

    // Decode the URL - handle both single and double encoding
    let decodedUrl: string;
    try {
      decodedUrl = decodeURIComponent(vendorUrl);
      // Handle double encoding
      if (decodedUrl.includes('%')) {
        decodedUrl = decodeURIComponent(decodedUrl);
      }
    } catch (error) {
      console.error('URL decode error:', error, 'Original URL:', vendorUrl);
      return new NextResponse(`Invalid URL encoding: ${error instanceof Error ? error.message : 'Unknown'}`, { status: 400 });
    }

    // Validate URL format
    let parsedUrl: URL;
    try {
      parsedUrl = new URL(decodedUrl);
    } catch (error) {
      console.error('URL parse error:', error, 'Decoded URL:', decodedUrl);
      return new NextResponse(`Invalid URL format: ${error instanceof Error ? error.message : 'Unknown'}`, { status: 400 });
    }

    // Security: Only allow images from whitelisted domains
    const hostname = parsedUrl.hostname;
    const isAllowed = ALLOWED_DOMAINS.some(
      (domain) => hostname === domain || hostname.endsWith(`.${domain}`)
    );

    if (!isAllowed) {
      console.error(`Domain not allowed: ${hostname}`);
      return new NextResponse(
        `Domain ${hostname} is not allowed`,
        { status: 403 }
      );
    }

    console.log(`Fetching image from: ${decodedUrl}`);

    // Try fetch first, fallback to native Node.js if it fails
    let imageBuffer: Buffer;
    let contentType: string = '';

    try {
      // Use native fetch
      const fetchOptions: RequestInit = {
        method: 'GET',
        headers: {
          'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
          'Accept': 'image/*',
        },
        cache: 'no-store',
      };

      const imageResponse = await fetch(decodedUrl, fetchOptions);

      if (!imageResponse.ok) {
        throw new Error(`HTTP ${imageResponse.status}: ${imageResponse.statusText}`);
      }

      contentType = imageResponse.headers.get('content-type') || '';
      const arrayBuffer = await imageResponse.arrayBuffer();
      imageBuffer = Buffer.from(arrayBuffer);
      
      console.log(`Successfully fetched via fetch: ${imageBuffer.length} bytes`);
    } catch (fetchError) {
      console.warn('Fetch failed, trying Node.js https module:', fetchError);
      
      // Fallback to Node.js native https/http
      imageBuffer = await new Promise<Buffer>((resolve, reject) => {
        const urlObj = new URL(decodedUrl);
        const isHttps = urlObj.protocol === 'https:';
        
        // For https, configure SSL options to handle certificate issues
        const requestOptions: https.RequestOptions | http.RequestOptions = isHttps ? {
          rejectUnauthorized: false, // Disable SSL verification (OK for image proxying)
          // This allows us to fetch images even if there are certificate chain issues
        } : {};
        
        const request = isHttps 
          ? https.get(decodedUrl, requestOptions as https.RequestOptions, (response) => {
              handleResponse(response, resolve, reject);
            })
          : http.get(decodedUrl, requestOptions, (response) => {
              handleResponse(response, resolve, reject);
            });

        function handleResponse(
          response: http.IncomingMessage,
          resolve: (value: Buffer) => void,
          reject: (reason?: any) => void
        ) {
          if (response.statusCode && response.statusCode >= 400) {
            reject(new Error(`HTTP ${response.statusCode}: ${response.statusMessage}`));
            return;
          }

          contentType = response.headers['content-type'] || '';
          const chunks: Buffer[] = [];

          response.on('data', (chunk) => chunks.push(chunk));
          response.on('end', () => {
            resolve(Buffer.concat(chunks));
          });
          response.on('error', reject);
        }

        request.on('error', (err) => {
          console.error('Node.js request error:', err);
          reject(err);
        });
        
        request.setTimeout(30000, () => {
          request.destroy();
          reject(new Error('Request timeout'));
        });
      });

      console.log(`Successfully fetched via Node.js: ${imageBuffer.length} bytes`);
    }

    // Get content type from response, or infer from URL/file extension
    if (!contentType || !contentType.startsWith('image/')) {
      const urlPath = parsedUrl.pathname.toLowerCase();
      if (urlPath.endsWith('.jpg') || urlPath.endsWith('.jpeg')) {
        contentType = 'image/jpeg';
      } else if (urlPath.endsWith('.png')) {
        contentType = 'image/png';
      } else if (urlPath.endsWith('.gif')) {
        contentType = 'image/gif';
      } else if (urlPath.endsWith('.webp')) {
        contentType = 'image/webp';
      } else {
        contentType = 'image/jpeg';
      }
    }

    // Validate content type
    if (!contentType.startsWith('image/')) {
      console.warn(`Unexpected content type: ${contentType}, defaulting to image/jpeg`);
      contentType = 'image/jpeg';
    }

    console.log(`Returning image: ${imageBuffer.length} bytes, content-type: ${contentType}`);

    // Return image with aggressive caching headers
    // Convert Buffer to Uint8Array for NextResponse compatibility
    return new NextResponse(new Uint8Array(imageBuffer), {
      status: 200,
      headers: {
        'Content-Type': contentType,
        'Content-Length': imageBuffer.length.toString(),
        'Cache-Control': `public, max-age=${CACHE_MAX_AGE}, immutable`,
        'CDN-Cache-Control': `public, max-age=${CACHE_MAX_AGE}`,
        'Vercel-CDN-Cache-Control': `public, max-age=${CACHE_MAX_AGE}`,
        'Access-Control-Allow-Origin': '*',
        'Access-Control-Allow-Methods': 'GET',
      },
    });
  } catch (error) {
    console.error('Image proxy error:', error);
    
    // Handle timeout/abort errors
    if (error instanceof Error) {
      if (error.name === 'AbortError' || error.message.includes('aborted')) {
        return new NextResponse('Request timeout', { status: 504 });
      }
      // Log the actual error message for debugging
      console.error('Error details:', error.message, error.stack);
    }

    return new NextResponse(
      `Internal server error: ${error instanceof Error ? error.message : 'Unknown error'}`,
      { status: 500 }
    );
  }
}

// Handle OPTIONS for CORS (if needed)
export async function OPTIONS() {
  return new NextResponse(null, {
    status: 200,
    headers: {
      'Access-Control-Allow-Origin': '*',
      'Access-Control-Allow-Methods': 'GET, OPTIONS',
      'Access-Control-Allow-Headers': 'Content-Type',
    },
  });
}

