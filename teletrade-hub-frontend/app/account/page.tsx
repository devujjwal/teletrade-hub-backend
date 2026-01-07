'use client';

import { MainLayout } from '@/components/layout/MainLayout';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { useState } from 'react';
import { useUserStore } from '@/lib/store';
import { endpoints } from '@/lib/api';
import toast from 'react-hot-toast';

export default function AccountPage() {
  const { user, setUser, logout } = useUserStore();
  const [isLogin, setIsLogin] = useState(true);
  const [loading, setLoading] = useState(false);
  const [formData, setFormData] = useState({
    email: '',
    password: '',
    first_name: '',
    last_name: '',
    phone: '',
  });

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);

    try {
      if (isLogin) {
        const response = await endpoints.auth.login({
          email: formData.email,
          password: formData.password,
        });
        if (response.success) {
          setUser(response.data.user);
          toast.success('Logged in successfully');
        }
      } else {
        const response = await endpoints.auth.register(formData);
        if (response.success) {
          setUser(response.data.user);
          toast.success('Account created successfully');
        }
      }
    } catch (error: any) {
      toast.error(error.response?.data?.message || 'Authentication failed');
    } finally {
      setLoading(false);
    }
  };

  if (user) {
    return (
      <MainLayout>
        <div className="container mx-auto px-4 py-16">
          <div className="max-w-2xl mx-auto">
            <div className="p-8 bg-muted rounded-lg space-y-6">
              <h1 className="text-3xl font-bold">My Account</h1>
              
              <div className="space-y-4">
                <div>
                  <p className="text-sm text-foreground/60">Name</p>
                  <p className="font-semibold">
                    {user.first_name} {user.last_name}
                  </p>
                </div>
                <div>
                  <p className="text-sm text-foreground/60">Email</p>
                  <p className="font-semibold">{user.email}</p>
                </div>
              </div>

              <Button variant="danger" onClick={logout} className="w-full">
                Logout
              </Button>
            </div>
          </div>
        </div>
      </MainLayout>
    );
  }

  return (
    <MainLayout>
      <div className="container mx-auto px-4 py-16">
        <div className="max-w-md mx-auto">
          <div className="p-8 bg-muted rounded-lg space-y-6">
            <div className="text-center">
              <h1 className="text-3xl font-bold mb-2">
                {isLogin ? 'Login' : 'Create Account'}
              </h1>
              <p className="text-foreground/70">
                {isLogin
                  ? 'Sign in to your account'
                  : 'Create a new account to get started'}
              </p>
            </div>

            <form onSubmit={handleSubmit} className="space-y-4">
              {!isLogin && (
                <>
                  <div className="grid grid-cols-2 gap-4">
                    <Input
                      label="First Name"
                      required
                      value={formData.first_name}
                      onChange={(e) =>
                        setFormData({ ...formData, first_name: e.target.value })
                      }
                    />
                    <Input
                      label="Last Name"
                      required
                      value={formData.last_name}
                      onChange={(e) =>
                        setFormData({ ...formData, last_name: e.target.value })
                      }
                    />
                  </div>
                  <Input
                    label="Phone"
                    type="tel"
                    value={formData.phone}
                    onChange={(e) =>
                      setFormData({ ...formData, phone: e.target.value })
                    }
                  />
                </>
              )}

              <Input
                label="Email"
                type="email"
                required
                value={formData.email}
                onChange={(e) =>
                  setFormData({ ...formData, email: e.target.value })
                }
              />

              <Input
                label="Password"
                type="password"
                required
                value={formData.password}
                onChange={(e) =>
                  setFormData({ ...formData, password: e.target.value })
                }
              />

              <Button
                type="submit"
                variant="primary"
                size="lg"
                className="w-full"
                isLoading={loading}
              >
                {isLogin ? 'Login' : 'Create Account'}
              </Button>
            </form>

            <div className="text-center">
              <button
                onClick={() => setIsLogin(!isLogin)}
                className="text-sm text-accent hover:underline"
              >
                {isLogin
                  ? "Don't have an account? Sign up"
                  : 'Already have an account? Login'}
              </button>
            </div>
          </div>
        </div>
      </div>
    </MainLayout>
  );
}

