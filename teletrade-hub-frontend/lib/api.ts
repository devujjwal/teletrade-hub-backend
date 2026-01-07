import axios, { AxiosInstance, AxiosRequestConfig, AxiosResponse } from 'axios';

const API_URL = process.env.NEXT_PUBLIC_API_URL || 'https://api.vs-mjrinfotech.com';

/**
 * API Client
 */
class ApiClient {
  private client: AxiosInstance;

  constructor() {
    this.client = axios.create({
      baseURL: API_URL,
      timeout: 30000,
      headers: {
        'Content-Type': 'application/json',
      },
    });

    // Request interceptor
    this.client.interceptors.request.use(
      (config) => {
        // Add auth token if available
        if (typeof window !== 'undefined') {
          const token = localStorage.getItem('admin_token');
          if (token) {
            config.headers.Authorization = `Bearer ${token}`;
          }
        }
        return config;
      },
      (error) => Promise.reject(error)
    );

    // Response interceptor
    this.client.interceptors.response.use(
      (response) => response,
      (error) => {
        if (error.response?.status === 401) {
          // Handle unauthorized
          if (typeof window !== 'undefined') {
            localStorage.removeItem('admin_token');
            window.location.href = '/admin/login';
          }
        }
        return Promise.reject(error);
      }
    );
  }

  async get<T = any>(url: string, config?: AxiosRequestConfig): Promise<T> {
    const response: AxiosResponse<T> = await this.client.get(url, config);
    return response.data;
  }

  async post<T = any>(url: string, data?: any, config?: AxiosRequestConfig): Promise<T> {
    const response: AxiosResponse<T> = await this.client.post(url, data, config);
    return response.data;
  }

  async put<T = any>(url: string, data?: any, config?: AxiosRequestConfig): Promise<T> {
    const response: AxiosResponse<T> = await this.client.put(url, data, config);
    return response.data;
  }

  async delete<T = any>(url: string, config?: AxiosRequestConfig): Promise<T> {
    const response: AxiosResponse<T> = await this.client.delete(url, config);
    return response.data;
  }
}

export const api = new ApiClient();

/**
 * API Endpoints
 */
export const endpoints = {
  // Products
  products: {
    list: (params?: any) => api.get('/products', { params }),
    get: (id: number) => api.get(`/products/${id}`),
    search: (query: string, params?: any) => api.get('/products/search', { params: { q: query, ...params } }),
  },
  
  // Categories
  categories: {
    list: () => api.get('/categories'),
    products: (id: number, params?: any) => api.get(`/categories/${id}/products`, { params }),
  },
  
  // Brands
  brands: {
    list: () => api.get('/brands'),
    products: (id: number, params?: any) => api.get(`/brands/${id}/products`, { params }),
  },
  
  // Orders
  orders: {
    create: (data: any) => api.post('/orders', data),
    get: (orderNumber: string) => api.get(`/orders/${orderNumber}`),
    paymentSuccess: (orderNumber: string, data: any) => 
      api.post(`/orders/${orderNumber}/payment-success`, data),
    paymentFailed: (orderNumber: string, data: any) => 
      api.post(`/orders/${orderNumber}/payment-failed`, data),
  },
  
  // Auth
  auth: {
    register: (data: any) => api.post('/auth/register', data),
    login: (data: any) => api.post('/auth/login', data),
  },
  
  // Admin
  admin: {
    login: (data: any) => api.post('/admin/login', data),
    dashboard: () => api.get('/admin/dashboard'),
    orders: {
      list: (params?: any) => api.get('/admin/orders', { params }),
      get: (id: number) => api.get(`/admin/orders/${id}`),
      updateStatus: (id: number, status: string) => 
        api.put(`/admin/orders/${id}/status`, { status }),
    },
    products: {
      list: (params?: any) => api.get('/admin/products', { params }),
      update: (id: number, data: any) => api.put(`/admin/products/${id}`, data),
    },
    pricing: {
      get: () => api.get('/admin/pricing'),
      updateGlobal: (data: any) => api.put('/admin/pricing/global', data),
      updateCategory: (id: number, data: any) => 
        api.put(`/admin/pricing/category/${id}`, data),
    },
    sync: {
      products: () => api.post('/admin/sync/products'),
      status: () => api.get('/admin/sync/status'),
    },
    vendor: {
      createSalesOrder: () => api.post('/admin/vendor/create-sales-order'),
    },
  },
};

export default api;

