import axios from 'axios';
import https from 'https';

/**
 * Server-side fetch utility with SSL certificate handling for development
 * Uses axios for better HTTPS support
 */
export async function serverFetch(url: string, options: RequestInit = {}) {
  // In development, use axios with SSL verification disabled
  if (typeof process !== 'undefined' && process.env.NODE_ENV === 'development') {
    try {
      const httpsAgent = new https.Agent({  
        rejectUnauthorized: false
      });

      const axiosResponse = await axios({
        url,
        method: (options.method || 'GET') as string,
        headers: options.headers as any,
        data: options.body,
        httpsAgent,
        validateStatus: () => true, // Don't throw on any status
      });

      // Convert axios response to fetch-like Response
      const response = new Response(JSON.stringify(axiosResponse.data), {
        status: axiosResponse.status,
        statusText: axiosResponse.statusText,
        headers: new Headers(axiosResponse.headers as any),
      });

      // Add ok property
      Object.defineProperty(response, 'ok', {
        get: () => axiosResponse.status >= 200 && axiosResponse.status < 300
      });

      return response;
    } catch (error) {
      console.error('Server fetch error:', error);
      throw error;
    }
  }

  // In production or browser, use secure fetch
  return fetch(url, options);
}
