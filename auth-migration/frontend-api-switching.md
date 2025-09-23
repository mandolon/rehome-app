# Frontend API Base Switching Implementation

## API Configuration Service

### client/src/services/ApiConfigService.ts
```typescript
interface AuthMode {
  mode: 'session_only' | 'dual' | 'sanctum_only';
  guard: 'web' | 'session_bridge' | 'sanctum';
  migrationInProgress: boolean;
  canRollback: boolean;
}

interface ApiConfig {
  baseURL: string;
  authMode: AuthMode;
  useSessionCookies: boolean;
  useTokens: boolean;
  fallbackToExpress: boolean;
}

class ApiConfigService {
  private config: ApiConfig;
  private authMode: AuthMode | null = null;

  constructor() {
    this.config = this.getDefaultConfig();
    this.detectAuthMode();
  }

  private getDefaultConfig(): ApiConfig {
    return {
      baseURL: this.getBaseURL(),
      authMode: {
        mode: 'session_only',
        guard: 'web',
        migrationInProgress: false,
        canRollback: false,
      },
      useSessionCookies: true,
      useTokens: false,
      fallbackToExpress: false,
    };
  }

  private getBaseURL(): string {
    // Determine API base URL based on environment
    const isDevelopment = process.env.NODE_ENV === 'development';
    const laravelPort = process.env.REACT_APP_LARAVEL_PORT || '8000';
    const expressPort = process.env.REACT_APP_EXPRESS_PORT || '5000';
    
    if (isDevelopment) {
      return `http://localhost:${laravelPort}/api`;
    }
    
    // Production: use Laravel API
    return '/api';
  }

  private async detectAuthMode(): Promise<void> {
    try {
      const response = await fetch('/api/health', {
        method: 'GET',
        credentials: 'include',
      });
      
      if (response.ok) {
        const data = await response.json();
        this.authMode = {
          mode: response.headers.get('X-Auth-Mode') as any || 'session_only',
          guard: response.headers.get('X-Auth-Guard') as any || 'web',
          migrationInProgress: response.headers.get('X-Migration-In-Progress') === 'true',
          canRollback: false, // Will be updated from API response
        };
        
        this.updateConfig();
      }
    } catch (error) {
      console.warn('Failed to detect auth mode, using defaults:', error);
      this.fallbackToExpress();
    }
  }

  private updateConfig(): void {
    if (!this.authMode) return;

    switch (this.authMode.mode) {
      case 'sanctum_only':
        this.config.useSessionCookies = false;
        this.config.useTokens = true;
        this.config.fallbackToExpress = false;
        break;
        
      case 'dual':
        this.config.useSessionCookies = true;
        this.config.useTokens = false;
        this.config.fallbackToExpress = true;
        break;
        
      case 'session_only':
      default:
        this.config.useSessionCookies = true;
        this.config.useTokens = false;
        this.config.fallbackToExpress = false;
        break;
    }
    
    this.config.authMode = this.authMode;
  }

  private fallbackToExpress(): void {
    const expressPort = process.env.REACT_APP_EXPRESS_PORT || '5000';
    this.config.baseURL = `http://localhost:${expressPort}/api`;
    this.config.fallbackToExpress = true;
    this.config.authMode = {
      mode: 'session_only',
      guard: 'web',
      migrationInProgress: false,
      canRollback: false,
    };
  }

  public getConfig(): ApiConfig {
    return { ...this.config };
  }

  public getAuthMode(): AuthMode | null {
    return this.authMode;
  }

  public isMigrationInProgress(): boolean {
    return this.authMode?.migrationInProgress || false;
  }

  public canRollback(): boolean {
    return this.authMode?.canRollback || false;
  }

  public async refreshAuthMode(): Promise<void> {
    await this.detectAuthMode();
  }
}

export const apiConfigService = new ApiConfigService();
export type { AuthMode, ApiConfig };
```

## API Client with Dynamic Switching

### client/src/services/ApiClient.ts
```typescript
import axios, { AxiosInstance, AxiosRequestConfig, AxiosResponse } from 'axios';
import { apiConfigService, AuthMode } from './ApiConfigService';

interface ApiResponse<T = any> {
  success: boolean;
  data?: T;
  message?: string;
  error?: string;
  auth_mode?: string;
}

class ApiClient {
  private client: AxiosInstance;
  private token: string | null = null;
  private isRetrying = false;

  constructor() {
    this.client = this.createClient();
    this.setupInterceptors();
  }

  private createClient(): AxiosInstance {
    const config = apiConfigService.getConfig();
    
    return axios.create({
      baseURL: config.baseURL,
      timeout: 10000,
      withCredentials: config.useSessionCookies,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
    });
  }

  private setupInterceptors(): void {
    // Request interceptor
    this.client.interceptors.request.use(
      (config) => {
        // Add token if available
        if (this.token && apiConfigService.getConfig().useTokens) {
          config.headers.Authorization = `Bearer ${this.token}`;
        }
        
        // Add CSRF token for session-based auth
        if (apiConfigService.getConfig().useSessionCookies) {
          const csrfToken = this.getCsrfToken();
          if (csrfToken) {
            config.headers['X-CSRF-TOKEN'] = csrfToken;
          }
        }
        
        return config;
      },
      (error) => Promise.reject(error)
    );

    // Response interceptor
    this.client.interceptors.response.use(
      (response) => {
        // Update auth mode from response headers
        this.updateAuthModeFromResponse(response);
        return response;
      },
      async (error) => {
        if (this.shouldRetryWithFallback(error)) {
          return this.retryWithFallback(error);
        }
        
        return Promise.reject(error);
      }
    );
  }

  private updateAuthModeFromResponse(response: AxiosResponse): void {
    const authMode = response.headers['x-auth-mode'];
    const migrationInProgress = response.headers['x-migration-in-progress'] === 'true';
    
    if (authMode) {
      // Update local auth mode if it changed
      const currentMode = apiConfigService.getAuthMode();
      if (!currentMode || currentMode.mode !== authMode) {
        apiConfigService.refreshAuthMode();
      }
    }
  }

  private shouldRetryWithFallback(error: any): boolean {
    if (this.isRetrying) return false;
    
    const config = apiConfigService.getConfig();
    if (!config.fallbackToExpress) return false;
    
    // Retry on network errors or 5xx errors
    return (
      !error.response || 
      error.response.status >= 500 ||
      error.code === 'NETWORK_ERROR' ||
      error.code === 'ECONNREFUSED'
    );
  }

  private async retryWithFallback(error: any): Promise<AxiosResponse> {
    this.isRetrying = true;
    
    try {
      // Switch to Express API
      const expressPort = process.env.REACT_APP_EXPRESS_PORT || '5000';
      const expressURL = `http://localhost:${expressPort}/api`;
      
      // Recreate client with Express URL
      const expressClient = axios.create({
        baseURL: expressURL,
        timeout: 10000,
        withCredentials: true,
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
      });
      
      // Retry the original request
      const originalConfig = error.config;
      const response = await expressClient.request({
        ...originalConfig,
        baseURL: expressURL,
      });
      
      console.warn('Request succeeded with Express fallback');
      return response;
      
    } catch (fallbackError) {
      console.error('Both Laravel and Express APIs failed:', fallbackError);
      throw error; // Throw original error
    } finally {
      this.isRetrying = false;
    }
  }

  private getCsrfToken(): string | null {
    // Get CSRF token from meta tag or cookie
    const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (metaToken) return metaToken;
    
    const cookieToken = document.cookie
      .split('; ')
      .find(row => row.startsWith('XSRF-TOKEN='))
      ?.split('=')[1];
    
    return cookieToken ? decodeURIComponent(cookieToken) : null;
  }

  public setToken(token: string | null): void {
    this.token = token;
  }

  public getToken(): string | null {
    return this.token;
  }

  public async login(credentials: { username: string; password: string }): Promise<ApiResponse> {
    try {
      const response = await this.client.post('/auth/login', credentials);
      
      // Handle different auth modes
      const authMode = response.data.auth_mode;
      
      if (authMode === 'sanctum' && response.data.token) {
        this.setToken(response.data.token);
      }
      
      return response.data;
    } catch (error: any) {
      throw this.handleError(error);
    }
  }

  public async logout(): Promise<void> {
    try {
      await this.client.post('/auth/logout');
      this.setToken(null);
    } catch (error) {
      console.error('Logout failed:', error);
    }
  }

  public async getCurrentUser(): Promise<ApiResponse> {
    try {
      const response = await this.client.get('/auth/me');
      return response.data;
    } catch (error: any) {
      throw this.handleError(error);
    }
  }

  public async migrateToSanctum(): Promise<ApiResponse> {
    try {
      const response = await this.client.post('/auth/migrate-to-sanctum');
      
      if (response.data.token) {
        this.setToken(response.data.token);
      }
      
      return response.data;
    } catch (error: any) {
      throw this.handleError(error);
    }
  }

  public async request<T = any>(config: AxiosRequestConfig): Promise<T> {
    try {
      const response = await this.client.request<T>(config);
      return response.data;
    } catch (error: any) {
      throw this.handleError(error);
    }
  }

  private handleError(error: any): Error {
    if (error.response) {
      // Server responded with error status
      const message = error.response.data?.message || error.response.data?.error || 'Request failed';
      return new Error(`${error.response.status}: ${message}`);
    } else if (error.request) {
      // Request was made but no response received
      return new Error('Network error: No response from server');
    } else {
      // Something else happened
      return new Error(error.message || 'Unknown error');
    }
  }

  public getClient(): AxiosInstance {
    return this.client;
  }
}

export const apiClient = new ApiClient();
```

## Authentication Context with Dynamic Switching

### client/src/contexts/AuthContext.tsx
```typescript
import React, { createContext, useContext, useEffect, useState, ReactNode } from 'react';
import { apiClient, apiConfigService, AuthMode } from '../services/ApiClient';

interface User {
  id: number;
  username: string;
  email?: string;
  role?: string;
}

interface AuthContextType {
  user: User | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  authMode: AuthMode | null;
  isMigrationInProgress: boolean;
  canRollback: boolean;
  login: (credentials: { username: string; password: string }) => Promise<void>;
  logout: () => Promise<void>;
  migrateToSanctum: () => Promise<void>;
  refreshAuthMode: () => Promise<void>;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

interface AuthProviderProps {
  children: ReactNode;
}

export const AuthProvider: React.FC<AuthProviderProps> = ({ children }) => {
  const [user, setUser] = useState<User | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [authMode, setAuthMode] = useState<AuthMode | null>(null);
  const [isMigrationInProgress, setIsMigrationInProgress] = useState(false);
  const [canRollback, setCanRollback] = useState(false);

  useEffect(() => {
    initializeAuth();
  }, []);

  const initializeAuth = async () => {
    try {
      setIsLoading(true);
      
      // Refresh auth mode
      await apiConfigService.refreshAuthMode();
      const currentAuthMode = apiConfigService.getAuthMode();
      setAuthMode(currentAuthMode);
      setIsMigrationInProgress(apiConfigService.isMigrationInProgress());
      setCanRollback(apiConfigService.canRollback());
      
      // Try to get current user
      try {
        const response = await apiClient.getCurrentUser();
        if (response.success && response.user) {
          setUser(response.user);
        }
      } catch (error) {
        // User not authenticated, that's okay
        console.log('User not authenticated');
      }
    } catch (error) {
      console.error('Auth initialization failed:', error);
    } finally {
      setIsLoading(false);
    }
  };

  const login = async (credentials: { username: string; password: string }) => {
    try {
      setIsLoading(true);
      const response = await apiClient.login(credentials);
      
      if (response.success && response.user) {
        setUser(response.user);
        
        // Update auth mode if it changed
        if (response.auth_mode) {
          await refreshAuthMode();
        }
      } else {
        throw new Error(response.message || 'Login failed');
      }
    } catch (error) {
      console.error('Login failed:', error);
      throw error;
    } finally {
      setIsLoading(false);
    }
  };

  const logout = async () => {
    try {
      await apiClient.logout();
      setUser(null);
    } catch (error) {
      console.error('Logout failed:', error);
    }
  };

  const migrateToSanctum = async () => {
    try {
      setIsLoading(true);
      const response = await apiClient.migrateToSanctum();
      
      if (response.success) {
        // Refresh auth mode after migration
        await refreshAuthMode();
      } else {
        throw new Error(response.message || 'Migration failed');
      }
    } catch (error) {
      console.error('Migration failed:', error);
      throw error;
    } finally {
      setIsLoading(false);
    }
  };

  const refreshAuthMode = async () => {
    try {
      await apiConfigService.refreshAuthMode();
      const currentAuthMode = apiConfigService.getAuthMode();
      setAuthMode(currentAuthMode);
      setIsMigrationInProgress(apiConfigService.isMigrationInProgress());
      setCanRollback(apiConfigService.canRollback());
    } catch (error) {
      console.error('Failed to refresh auth mode:', error);
    }
  };

  const value: AuthContextType = {
    user,
    isAuthenticated: !!user,
    isLoading,
    authMode,
    isMigrationInProgress,
    canRollback,
    login,
    logout,
    migrateToSanctum,
    refreshAuthMode,
  };

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = (): AuthContextType => {
  const context = useContext(AuthContext);
  if (context === undefined) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};
```

## Migration Status Component

### client/src/components/AuthMigrationStatus.tsx
```typescript
import React from 'react';
import { useAuth } from '../contexts/AuthContext';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';

export const AuthMigrationStatus: React.FC = () => {
  const { authMode, isMigrationInProgress, canRollback, migrateToSanctum } = useAuth();

  if (!authMode) {
    return null;
  }

  const getStatusColor = () => {
    switch (authMode.mode) {
      case 'sanctum_only':
        return 'bg-green-100 text-green-800';
      case 'dual':
        return 'bg-yellow-100 text-yellow-800';
      case 'session_only':
        return 'bg-blue-100 text-blue-800';
      default:
        return 'bg-gray-100 text-gray-800';
    }
  };

  const getStatusText = () => {
    switch (authMode.mode) {
      case 'sanctum_only':
        return 'Sanctum Only';
      case 'dual':
        return 'Dual Mode';
      case 'session_only':
        return 'Session Only';
      default:
        return 'Unknown';
    }
  };

  return (
    <div className="space-y-4">
      {/* Auth Mode Status */}
      <div className="flex items-center gap-2">
        <span className="text-sm font-medium">Auth Mode:</span>
        <Badge className={getStatusColor()}>
          {getStatusText()}
        </Badge>
        {isMigrationInProgress && (
          <Badge variant="outline" className="bg-orange-100 text-orange-800">
            Migration In Progress
          </Badge>
        )}
      </div>

      {/* Migration Alerts */}
      {authMode.mode === 'dual' && (
        <Alert>
          <AlertDescription>
            <div className="space-y-2">
              <p>
                The system is currently running in dual authentication mode. 
                This means you can use both session-based and token-based authentication.
              </p>
              <div className="flex gap-2">
                <Button 
                  size="sm" 
                  onClick={migrateToSanctum}
                  disabled={isMigrationInProgress}
                >
                  Migrate to Sanctum Tokens
                </Button>
                {canRollback && (
                  <Button 
                    size="sm" 
                    variant="outline"
                    onClick={() => {
                      // Implement rollback functionality
                      console.log('Rollback requested');
                    }}
                  >
                    Rollback
                  </Button>
                )}
              </div>
            </div>
          </AlertDescription>
        </Alert>
      )}

      {authMode.mode === 'sanctum_only' && (
        <Alert>
          <AlertDescription>
            Authentication has been fully migrated to Sanctum tokens. 
            Session-based authentication is no longer available.
          </AlertDescription>
        </Alert>
      )}

      {/* Migration Progress */}
      {isMigrationInProgress && (
        <Alert>
          <AlertDescription>
            <div className="space-y-2">
              <p>Authentication migration is in progress...</p>
              <div className="w-full bg-gray-200 rounded-full h-2">
                <div className="bg-blue-600 h-2 rounded-full animate-pulse" style={{ width: '60%' }}></div>
              </div>
              <p className="text-sm text-gray-600">
                Please do not refresh the page during migration.
              </p>
            </div>
          </AlertDescription>
        </Alert>
      )}
    </div>
  );
};
```

## Environment Configuration

### client/.env
```bash
# API Configuration
REACT_APP_LARAVEL_PORT=8000
REACT_APP_EXPRESS_PORT=5000
REACT_APP_API_TIMEOUT=10000

# Feature Flags
REACT_APP_AUTH_MIGRATION_ENABLED=true
REACT_APP_FALLBACK_TO_EXPRESS=true
REACT_APP_DEBUG_AUTH_MODE=false
```

### client/.env.development
```bash
# Development API URLs
REACT_APP_LARAVEL_API_URL=http://localhost:8000/api
REACT_APP_EXPRESS_API_URL=http://localhost:5000/api
REACT_APP_DEBUG_AUTH_MODE=true
```

### client/.env.production
```bash
# Production API URLs
REACT_APP_LARAVEL_API_URL=/api
REACT_APP_EXPRESS_API_URL=/api
REACT_APP_DEBUG_AUTH_MODE=false
```

## App Integration

### client/src/App.tsx (Updated)
```typescript
import React from 'react';
import { BrowserRouter, Routes, Route } from "react-router-dom";
import { ThemeProvider } from "next-themes";
import { QueryClientProvider } from "@tanstack/react-query";
import { AuthProvider } from './contexts/AuthContext';
import { AuthMigrationStatus } from './components/AuthMigrationStatus';

// ... other imports

const App = () => {
  return (
    <BrowserRouter>
      <ThemeProvider attribute="class" defaultTheme="system" enableSystem>
        <QueryClientProvider client={queryClient}>
          <AuthProvider>
            <div className="min-h-screen bg-background">
              {/* Migration Status Banner */}
              <AuthMigrationStatus />
              
              {/* Main App Content */}
              <Routes>
                {/* ... existing routes */}
              </Routes>
            </div>
          </AuthProvider>
        </QueryClientProvider>
      </ThemeProvider>
    </BrowserRouter>
  );
};

export default App;
```
