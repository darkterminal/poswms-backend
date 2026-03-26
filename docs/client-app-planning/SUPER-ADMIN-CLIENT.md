# Super Admin Client - Frontend Application Specification

## Project Overview

**Super Admin Client** is a React-based web application for managing the POS WMS Backend SaaS platform. This application provides super administrators with tools to manage tenants, monitor system health, configure global settings, and perform cross-tenant operations.

### Application Type

- **Type**: Single Page Application (SPA)
- **Purpose**: Super Admin dashboard and management interface
- **API Consumer**: Consumes the POS WMS Backend API defined in `swagger/openapi.yaml`

---

## ⚠️ CORE ARCHITECTURE RULES

**These rules are non-negotiable. All development MUST follow these principles.**

### ⛔ DO NOT

| Rule | Description | Why | Example |
|------|-------------|-----|---------|
| **Mix API logic inside components** | Never call API directly inside components | Breaks separation of concerns, hard to test, no caching | ❌ `axios.get('/api/tenants')` inside component |
| **Store global state in React state** | Never use `useState` or `useReducer` for server state | No caching, no deduplication, no background refetch | ❌ `const [tenants, setTenants] = useState([])` |
| **Call API without tenant context** | Always include tenant_id when required | Multi-tenant system requires tenant scoping | ❌ Calling `/tenants/{id}/products` without tenant_id |
| **Trust UI for authorization** | Never rely on UI for security decisions | UI can be bypassed; backend must enforce permissions | ❌ Hiding buttons based on role without backend verification |

### ✅ ALWAYS

| Rule | Description | Why | Example |
|------|-------------|-----|---------|
| **Use TanStack Query for server state** | All API data fetching uses `useQuery`, `useMutation` | Caching, deduplication, background refetch, optimistic updates | ✅ `useQuery({ queryKey: ['tenants'], queryFn: api.tenants.list })` |
| **Use Zustand for client state** | Session, cart, UI preferences in Zustand stores | Persistent, reactive, no re-renders on unrelated updates | ✅ `useSessionStore(state => state.user)` |
| **Use Axios instance with interceptor** | Configure base URL, auth headers, error handling centrally | DRY, consistent error handling, automatic auth injection | ✅ `apiClient.interceptors.request.use(config => {...})` |
| **Use typed API client generated from OpenAPI** | Generate TypeScript types from OpenAPI spec | Type safety, auto-completion, API contract enforcement | ✅ `import { TenantsService } from '@/api/generated'` |

### Architecture Decision Record

```
┌─────────────────────────────────────────────────────────────────┐
│                     STATE MANAGEMENT                            │
├─────────────────────────────────────────────────────────────────┤
│  Server State (API Data)     → TanStack Query (React Query)     │
│  Client State (Session/Cart) → Zustand                          │
│  UI State (Local)            → useState/useReducer              │
│  Form State                  → React Hook Form + Zod            │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                     API LAYER ARCHITECTURE                      │
├─────────────────────────────────────────────────────────────────┤
│  Component → Hook → Service → Generated API Client → Axios      │
│                                                                     │
│  ❌ NEVER: Component → Axios                                      │
│  ✅ ALWAYS: Component → Hook → Service → Generated Client         │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                     AUTHORIZATION FLOW                          │
├─────────────────────────────────────────────────────────────────┤
│  1. Backend validates permissions on EVERY request              │
│  2. Frontend uses permissions for UX optimization only          │
│  3. Never trust frontend for security decisions                 │
│  4. Always handle 403 responses gracefully                      │
└─────────────────────────────────────────────────────────────────┘
```

---

## Technology Stack

| Layer | Technology | Version | Purpose |
|-------|------------|---------|---------|
| **Build Tool** | Vite | 5.x | Fast build tooling and dev server |
| **Framework** | React | 18.x | UI component library |
| **Language** | TypeScript | 5.x | Type safety and DX |
| **UI Components** | ShadcnUI | latest | Accessible component primitives |
| **Styling** | Tailwind CSS | 3.x | Utility-first CSS framework |
| **Server State** | TanStack Query | 5.x | Server state management (caching, syncing) |
| **Client State** | Zustand | 4.x | Client state (cart, session, UI) |
| **HTTP Client** | Axios | 1.x | HTTP requests with interceptors |
| **API Client** | openapi-typescript-codegen | latest | Auto-generated typed API client |
| **Forms** | React Hook Form | 7.x | Form handling and validation |
| **Validation** | Zod | 3.x | Schema validation |
| **Routing** | React Router | 6.x | Client-side routing |
| **Testing** | Vitest + React Testing Library | 2.x | Unit and integration testing |
| **E2E Testing** | Playwright | latest | End-to-end testing |
| **Linting** | ESLint + Prettier | 9.x / 3.x | Code quality and formatting |

---

## Core Architecture Rules

### ⛔ DO NOT

| Rule | Description | Example |
|------|-------------|---------|
| **No API logic in components** | Never call API directly inside components | ❌ `axios.get('/api/tenants')` inside component |
| **No global state in React state** | Never use `useState` or `useReducer` for server state | ❌ `const [tenants, setTenants] = useState([])` |
| **No API calls without tenant context** | Always include tenant_id when required | ❌ Calling `/tenants/{id}/products` without tenant_id |
| **No UI-based authorization** | Never trust UI for security decisions | ❌ Hiding buttons based on role without backend verification |

### ✅ ALWAYS

| Rule | Description | Example |
|------|-------------|---------|
| **Use TanStack Query for server state** | All API data fetching uses `useQuery`, `useMutation` | ✅ `useQuery({ queryKey: ['tenants'], queryFn: api.tenants.list })` |
| **Use Zustand for client state** | Session, cart, UI preferences in Zustand stores | ✅ `useSessionStore(state => state.user)` |
| **Use Axios instance with interceptor** | Configure base URL, auth headers, error handling centrally | ✅ `apiClient.interceptors.request.use(config => {...})` |
| **Use typed API client** | Generate TypeScript types from OpenAPI spec | ✅ `import { TenantsService } from '@/api/generated'` |

---

## Project Structure

```
super-admin-client/
├── public/                      # Static assets
│   ├── favicon.ico
│   └── logo.svg
├── src/
│   ├── api/                     # API layer
│   │   ├── generated/           # Auto-generated from OpenAPI (DO NOT EDIT)
│   │   │   ├── core/
│   │   │   ├── models/
│   │   │   ├── services/
│   │   │   └── index.ts
│   │   ├── client.ts            # Axios instance configuration
│   │   ├── interceptors.ts      # Request/response interceptors
│   │   └── index.ts             # API exports
│   ├── components/              # Reusable UI components
│   │   ├── ui/                  # ShadcnUI primitives
│   │   ├── layout/              # Layout components (Header, Sidebar, etc.)
│   │   ├── common/              # Common components (Button, Input, etc.)
│   │   └── features/            # Feature-specific components
│   ├── config/                  # Application configuration
│   │   ├── env.ts               # Environment variables validation
│   │   └── routes.tsx           # Route definitions
│   ├── features/                # Feature modules (domain-driven)
│   │   ├── auth/
│   │   │   ├── components/      # Auth-specific components
│   │   │   ├── hooks/           # Auth-specific hooks
│   │   │   ├── stores/          # Auth Zustand stores
│   │   │   └── services/        # Auth API service layer
│   │   ├── tenants/
│   │   │   ├── components/
│   │   │   ├── hooks/
│   │   │   ├── stores/
│   │   │   └── services/
│   │   ├── system-dashboard/
│   │   │   ├── components/
│   │   │   ├── hooks/
│   │   │   ├── stores/
│   │   │   └── services/
│   │   └── ... (other features)
│   ├── hooks/                   # Shared hooks
│   │   ├── useDebounce.ts
│   │   └── useLocalStorage.ts
│   ├── lib/                     # Utility libraries
│   │   ├── utils.ts             # cn() and other utilities
│   │   └── constants.ts         # App-wide constants
│   ├── pages/                   # Page components (route-level)
│   │   ├── Dashboard.tsx
│   │   ├── TenantsList.tsx
│   │   ├── TenantDetail.tsx
│   │   └── ...
│   ├── providers/               # React providers
│   │   ├── QueryProvider.tsx
│   │   ├── ThemeProvider.tsx
│   │   └── AuthProvider.tsx
│   ├── stores/                  # Global Zustand stores
│   │   ├── sessionStore.ts
│   │   ├── uiStore.ts
│   │   └── cartStore.ts
│   ├── types/                   # TypeScript type definitions
│   │   ├── api.ts               # API-related types
│   │   └── common.ts            # Common types
│   ├── utils/                   # Utility functions
│   │   ├── formatters.ts
│   │   ├── validators.ts
│   │   └── helpers.ts
│   ├── App.tsx                  # Root component
│   ├── main.tsx                 # Entry point
│   └── vite-env.d.ts            # Vite type declarations
├── tests/
│   ├── unit/                    # Unit tests
│   ├── integration/             # Integration tests
│   └── e2e/                     # E2E tests (Playwright)
├── .env                         # Environment variables
├── .env.example                 # Environment template
├── .eslintrc.cjs                # ESLint configuration
├── .prettierrc                  # Prettier configuration
├── components.json              # ShadcnUI configuration
├── index.html                   # HTML entry point
├── package.json                 # Dependencies and scripts
├── playwright.config.ts         # Playwright configuration
├── tailwind.config.js           # Tailwind configuration
├── tsconfig.json                # TypeScript configuration
├── vite.config.ts               # Vite configuration
└── vitest.config.ts             # Vitest configuration
```

---

## Building and Running

### Initial Setup

```bash
# Clone and navigate to project
cd super-admin-client

# Install dependencies
npm install

# Copy environment template
cp .env.example .env

# Generate API client from OpenAPI spec
npm run generate:api

# Start development server
npm run dev
```

### Development Server

```bash
# Start Vite dev server
npm run dev

# Start dev server with specific port
npm run dev -- --port 3000
```

### Build Commands

```bash
# Production build
npm run build

# Preview production build
npm run preview

# Type checking
npm run type-check

# Linting
npm run lint

# Format code
npm run format
```

### Testing

```bash
# Run all tests
npm run test

# Run unit tests with coverage
npm run test:unit -- --coverage

# Run integration tests
npm run test:integration

# Run E2E tests
npm run test:e2e

# Run specific test file
npm run test -- src/features/tenants/__tests__/TenantsList.test.tsx
```

---

## Development Conventions

### TypeScript

- **Strict mode**: Enable all strict type-checking options
- **No implicit any**: Always define types explicitly
- **Interface over type**: Use `interface` for object shapes, `type` for unions
- **Generics**: Use generics for reusable utility functions

```typescript
// ✅ Good
interface User {
  id: number;
  name: string;
  email: string;
}

function fetchData<T>(url: string): Promise<T> {
  return axios.get(url).then(res => res.data);
}

// ❌ Avoid
function fetchData(url: string): Promise<any> {
  return axios.get(url).then(res => res.data);
}
```

### Component Structure

```typescript
import { useQuery } from '@tanstack/react-query';
import { TenantsService } from '@/api/generated';
import { TenantCard } from './TenantCard';
import { LoadingSpinner } from '@/components/common/LoadingSpinner';

// ✅ Component definition
export function TenantsList() {
  // 1. Hooks (custom hooks, then TanStack Query)
  const { data, isLoading, error } = useQuery({
    queryKey: ['tenants'],
    queryFn: () => TenantsService.listTenants(),
  });

  // 2. Early returns for loading/error states
  if (isLoading) return <LoadingSpinner />;
  if (error) return <ErrorMessage error={error} />;
  if (!data?.data?.tenants?.length) return <EmptyState />;

  // 3. Component JSX
  return (
    <div className="grid gap-4">
      {data.data.tenants.map(tenant => (
        <TenantCard key={tenant.id} tenant={tenant} />
      ))}
    </div>
  );
}
```

### API Service Layer

**Never call API directly in components.** Always use a service layer:

```typescript
// ✅ features/tenants/services/tenantService.ts
import { TenantsService, type Tenant } from '@/api/generated';
import { apiClient } from '@/api/client';

export const tenantService = {
  async list(params?: { page?: number; perPage?: number }): Promise<Tenant[]> {
    const response = await TenantsService.listTenants(params);
    return response.data?.tenants ?? [];
  },

  async getById(tenantId: number): Promise<Tenant> {
    const response = await TenantsService.getTenant({ tenant_id: tenantId });
    return response.data?.tenant!;
  },

  async create(data: CreateTenantRequest): Promise<Tenant> {
    const response = await TenantsService.createTenant({ requestBody: data });
    return response.data?.tenant!;
  },

  async update(tenantId: number, data: UpdateTenantRequest): Promise<Tenant> {
    const response = await TenantsService.updateTenant({
      tenant_id: tenantId,
      requestBody: data,
    });
    return response.data?.tenant!;
  },

  async delete(tenantId: number): Promise<void> {
    await TenantsService.deleteTenant({ tenant_id: tenantId });
  },
};
```

```typescript
// ✅ Component using service layer
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { tenantService } from '../services/tenantService';

export function TenantsList() {
  const queryClient = useQueryClient();

  const { data: tenants } = useQuery({
    queryKey: ['tenants'],
    queryFn: () => tenantService.list(),
  });

  const deleteMutation = useMutation({
    mutationFn: (tenantId: number) => tenantService.delete(tenantId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['tenants'] });
    },
  });

  return (/* JSX */);
}
```

### Zustand Stores

**Use Zustand for client state only** (session, UI preferences, cart):

```typescript
// ✅ stores/sessionStore.ts
import { create } from 'zustand';
import { persist } from 'zustand/middleware';
import type { User, Tenant } from '@/api/generated';

interface SessionState {
  user: User | null;
  currentTenant: Tenant | null;
  token: string | null;
  isAuthenticated: boolean;
  setUser: (user: User | null) => void;
  setCurrentTenant: (tenant: Tenant | null) => void;
  setToken: (token: string | null) => void;
  logout: () => void;
}

export const useSessionStore = create<SessionState>()(
  persist(
    (set) => ({
      user: null,
      currentTenant: null,
      token: null,
      isAuthenticated: false,

      setUser: (user) => set({ user, isAuthenticated: !!user }),
      setCurrentTenant: (tenant) => set({ currentTenant: tenant }),
      setToken: (token) => set({ token }),
      logout: () => set({
        user: null,
        currentTenant: null,
        token: null,
        isAuthenticated: false,
      }),
    }),
    {
      name: 'session-storage',
      partialize: (state) => ({
        token: state.token,
        currentTenant: state.currentTenant,
      }),
    }
  )
);
```

```typescript
// ✅ stores/uiStore.ts
import { create } from 'zustand';

interface UIState {
  sidebarOpen: boolean;
  theme: 'light' | 'dark' | 'system';
  setSidebarOpen: (open: boolean) => void;
  setTheme: (theme: 'light' | 'dark' | 'system') => void;
  toggleSidebar: () => void;
}

export const useUIStore = create<UIState>((set, get) => ({
  sidebarOpen: true,
  theme: 'system',
  setSidebarOpen: (open) => set({ sidebarOpen: open }),
  setTheme: (theme) => set({ theme }),
  toggleSidebar: () => set({ sidebarOpen: !get().sidebarOpen }),
}));
```

### Axios Configuration

```typescript
// ✅ api/client.ts
import axios, { type AxiosInstance, type AxiosError } from 'axios';
import type { ApiError } from './generated';
import { useSessionStore } from '@/stores/sessionStore';

const BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000';

export const apiClient: AxiosInstance = axios.create({
  baseURL: BASE_URL,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
  timeout: 30000,
});

// Request interceptor: Add auth token
apiClient.interceptors.request.use(
  (config) => {
    const token = useSessionStore.getState().token;
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => Promise.reject(error)
);

// Response interceptor: Handle errors globally
apiClient.interceptors.response.use(
  (response) => response,
  (error: AxiosError<ApiError>) => {
    const status = error.response?.status;

    if (status === 401) {
      // Token expired or invalid - logout
      useSessionStore.getState().logout();
      window.location.href = '/login';
    }

    if (status === 403) {
      // Permission denied - show toast
      // toast.error('You do not have permission to perform this action');
    }

    return Promise.reject(error);
  }
);
```

---

## API Integration

### Generating Typed API Client

```bash
# Generate from OpenAPI spec
npm run generate:api

# This runs:
openapi --input ./swagger/openapi.yaml --output ./src/api/generated --client axios
```

### Query Keys Convention

```typescript
// ✅ lib/queryKeys.ts
export const queryKeys = {
  tenants: {
    all: ['tenants'] as const,
    lists: () => [...queryKeys.tenants.all, 'list'] as const,
    list: (filters: TenantFilters) => [...queryKeys.tenants.lists(), filters] as const,
    details: () => [...queryKeys.tenants.all, 'detail'] as const,
    detail: (id: number) => [...queryKeys.tenants.details(), id] as const,
  },
  users: {
    all: ['users'] as const,
    lists: () => [...queryKeys.users.all, 'list'] as const,
    list: (filters: UserFilters) => [...queryKeys.users.lists(), filters] as const,
    details: () => [...queryKeys.users.all, 'detail'] as const,
    detail: (id: number) => [...queryKeys.users.details(), id] as const,
  },
  // ... other entities
};
```

### Using TanStack Query

```typescript
// ✅ features/tenants/hooks/useTenants.ts
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { queryKeys } from '@/lib/queryKeys';
import { tenantService } from '../services/tenantService';
import type { TenantFilters, CreateTenantRequest, UpdateTenantRequest } from '../types';

export function useTenants(filters?: TenantFilters) {
  return useQuery({
    queryKey: queryKeys.tenants.list(filters ?? {}),
    queryFn: () => tenantService.list(filters),
    staleTime: 5 * 60 * 1000, // 5 minutes
  });
}

export function useTenant(tenantId: number) {
  return useQuery({
    queryKey: queryKeys.tenants.detail(tenantId),
    queryFn: () => tenantService.getById(tenantId),
    enabled: !!tenantId,
  });
}

export function useCreateTenant() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (data: CreateTenantRequest) => tenantService.create(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.tenants.all });
    },
  });
}

export function useUpdateTenant(tenantId: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (data: UpdateTenantRequest) => tenantService.update(tenantId, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.tenants.detail(tenantId) });
    },
  });
}

export function useDeleteTenant() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (tenantId: number) => tenantService.delete(tenantId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.tenants.all });
    },
  });
}
```

---

## Authentication Flow

### Login Flow

```typescript
// ✅ features/auth/services/authService.ts
import { AuthService as GeneratedAuthService } from '@/api/generated';
import type { LoginRequest, LoginResponse } from '@/api/generated';

export const authService = {
  async login(credentials: LoginRequest): Promise<LoginResponse> {
    const response = await GeneratedAuthService.loginUser({ requestBody: credentials });
    return response.data!;
  },

  async logout(tenantId: number): Promise<void> {
    await GeneratedAuthService.logoutUser({ tenant_id: tenantId });
  },

  async refreshToken(tenantId: number): Promise<void> {
    await GeneratedAuthService.refreshToken({ tenant_id: tenantId });
  },

  async getCurrentUser(tenantId: number) {
    const response = await GeneratedAuthService.getCurrentUser({ tenant_id: tenantId });
    return response.data?.user!;
  },
};
```

```typescript
// ✅ features/auth/hooks/useAuth.ts
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { authService } from '../services/authService';
import { useSessionStore } from '@/stores/sessionStore';
import type { LoginRequest } from '@/api/generated';

export function useLogin() {
  const queryClient = useQueryClient();
  const { setUser, setToken, setCurrentTenant } = useSessionStore();

  return useMutation({
    mutationFn: (credentials: LoginRequest) => authService.login(credentials),
    onSuccess: (data) => {
      const { user, token, tenant } = data.data!;
      setUser(user);
      setToken(token);
      setCurrentTenant(tenant);
      queryClient.setQueryData(['auth', 'me'], user);
    },
  });
}

export function useLogout(tenantId: number) {
  const queryClient = useQueryClient();
  const { logout: clearSession } = useSessionStore();

  return useMutation({
    mutationFn: () => authService.logout(tenantId),
    onSuccess: () => {
      clearSession();
      queryClient.clear();
    },
  });
}

export function useCurrentUser(tenantId: number) {
  return useQuery({
    queryKey: ['auth', 'me', tenantId],
    queryFn: () => authService.getCurrentUser(tenantId),
  });
}
```

---

## Multi-Tenancy Handling

### Tenant Context

```typescript
// ✅ providers/TenantProvider.tsx
import { createContext, useContext, useEffect } from 'react';
import { useSessionStore } from '@/stores/sessionStore';
import type { Tenant } from '@/api/generated';

interface TenantContextType {
  tenant: Tenant | null;
  tenantId: number | null;
  setTenant: (tenant: Tenant) => void;
}

const TenantContext = createContext<TenantContextType | undefined>(undefined);

export function TenantProvider({ children }: { children: React.ReactNode }) {
  const { currentTenant, setCurrentTenant } = useSessionStore();

  const setTenant = (tenant: Tenant) => {
    setCurrentTenant(tenant);
  };

  return (
    <TenantContext.Provider
      value={{
        tenant: currentTenant,
        tenantId: currentTenant?.id ?? null,
        setTenant,
      }}
    >
      {children}
    </TenantContext.Provider>
  );
}

export function useTenantContext() {
  const context = useContext(TenantContext);
  if (!context) {
    throw new Error('useTenantContext must be used within TenantProvider');
  }
  return context;
}
```

### API Calls with Tenant Context

```typescript
// ✅ Always include tenant_id in API calls
import { useQuery } from '@tanstack/react-query';
import { ProductsService } from '@/api/generated';
import { useTenantContext } from '@/providers/TenantProvider';

export function useProducts(filters?: ProductFilters) {
  const { tenantId } = useTenantContext();

  return useQuery({
    queryKey: ['products', tenantId, filters],
    queryFn: () => ProductsService.listProducts({
      tenant_id: tenantId!,
      ...filters,
    }),
    enabled: !!tenantId, // Only run query if tenantId exists
  });
}
```

---

## Authorization

### Role-Based Access Control

```typescript
// ✅ features/auth/hooks/usePermissions.ts
import { useCurrentUser } from './useAuth';
import { useTenantContext } from '@/providers/TenantProvider';

export function usePermissions() {
  const { tenantId } = useTenantContext();
  const { data: user } = useCurrentUser(tenantId!);

  const hasRole = (roles: string[]): boolean => {
    if (!user?.role) return false;
    return roles.includes(user.role.name);
  };

  const hasPermission = (permissions: string[]): boolean => {
    if (!user?.permissions) return false;
    return permissions.some(p => user.permissions.includes(p));
  };

  const can = hasPermission;
  const is = hasRole;

  return { hasRole, hasPermission, can, is, user };
}
```

```typescript
// ✅ components/features/auth/Can.tsx
import { usePermissions } from '@/features/auth/hooks/usePermissions';

interface CanProps {
  do?: string[]; // Permissions
  I?: string[];  // Roles
  children: React.ReactNode;
  fallback?: React.ReactNode;
}

export function Can({ do, I, children, fallback = null }: CanProps) {
  const { can, is } = usePermissions();

  const hasPermission = do ? can(do) : true;
  const hasRole = I ? is(I) : true;

  if (hasPermission && hasRole) {
    return <>{children}</>;
  }

  return <>{fallback}</>;
}
```

---

## Forms and Validation

### Using React Hook Form + Zod

```typescript
// ✅ features/tenants/schemas/tenantSchema.ts
import { z } from 'zod';

export const createTenantSchema = z.object({
  name: z.string().min(1, 'Name is required'),
  email: z.string().email('Invalid email address'),
  phone: z.string().optional(),
  address: z.string().optional(),
  city: z.string().optional(),
  country: z.string().optional(),
  postal_code: z.string().optional(),
  status: z.enum(['active', 'suspended', 'inactive']),
  subscription_plan: z.enum(['free', 'starter', 'professional', 'enterprise']),
});

export type CreateTenantFormData = z.infer<typeof createTenantSchema>;
```

```typescript
// ✅ features/tenants/components/CreateTenantForm.tsx
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useCreateTenant } from '../hooks/useTenants';
import { createTenantSchema, type CreateTenantFormData } from '../schemas/tenantSchema';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';

export function CreateTenantForm({ onSuccess }: { onSuccess?: () => void }) {
  const createTenant = useCreateTenant();

  const form = useForm<CreateTenantFormData>({
    resolver: zodResolver(createTenantSchema),
    defaultValues: {
      name: '',
      email: '',
      phone: '',
      address: '',
      city: '',
      country: '',
      postal_code: '',
      status: 'active',
      subscription_plan: 'starter',
    },
  });

  const onSubmit = (data: CreateTenantFormData) => {
    createTenant.mutate(data, {
      onSuccess: () => {
        form.reset();
        onSuccess?.();
      },
    });
  };

  return (
    <Form {...form}>
      <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
        <FormField
          control={form.control}
          name="name"
          render={({ field }) => (
            <FormItem>
              <FormLabel>Name</FormLabel>
              <FormControl>
                <Input {...field} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />

        <FormField
          control={form.control}
          name="email"
          render={({ field }) => (
            <FormItem>
              <FormLabel>Email</FormLabel>
              <FormControl>
                <Input type="email" {...field} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />

        {/* ... other fields ... */}

        <Button type="submit" disabled={createTenant.isPending}>
          {createTenant.isPending ? 'Creating...' : 'Create Tenant'}
        </Button>
      </form>
    </Form>
  );
}
```

---

## Testing

### Unit Tests

```typescript
// ✅ features/tenants/__tests__/tenantService.test.ts
import { tenantService } from '../services/tenantService';
import { TenantsService } from '@/api/generated';
import { apiClient } from '@/api/client';

vi.mock('@/api/generated');
vi.mock('@/api/client');

describe('tenantService', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('should list tenants', async () => {
    const mockTenants = [
      { id: 1, name: 'Tenant 1' },
      { id: 2, name: 'Tenant 2' },
    ];

    vi.mocked(TenantsService.listTenants).mockResolvedValue({
      data: { tenants: mockTenants },
    });

    const result = await tenantService.list();

    expect(result).toEqual(mockTenants);
    expect(TenantsService.listTenants).toHaveBeenCalled();
  });

  it('should handle empty tenant list', async () => {
    vi.mocked(TenantsService.listTenants).mockResolvedValue({
      data: { tenants: [] },
    });

    const result = await tenantService.list();

    expect(result).toEqual([]);
  });
});
```

### Component Tests

```typescript
// ✅ features/tenants/__tests__/TenantsList.test.tsx
import { render, screen, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { TenantsList } from '../components/TenantsList';
import { tenantService } from '../services/tenantService';

vi.mock('../services/tenantService');

function renderWithQueryClient(component: React.ReactElement) {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
    },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      {component}
    </QueryClientProvider>
  );
}

describe('TenantsList', () => {
  it('should show loading state', () => {
    vi.mocked(tenantService.list).mockImplementation(() => new Promise(() => {}));

    renderWithQueryClient(<TenantsList />);

    expect(screen.getByText(/loading/i)).toBeInTheDocument();
  });

  it('should display tenants', async () => {
    const mockTenants = [
      { id: 1, name: 'Tenant 1', email: 'tenant1@example.com' },
      { id: 2, name: 'Tenant 2', email: 'tenant2@example.com' },
    ];

    vi.mocked(tenantService.list).mockResolvedValue(mockTenants);

    renderWithQueryClient(<TenantsList />);

    await waitFor(() => {
      expect(screen.getByText('Tenant 1')).toBeInTheDocument();
      expect(screen.getByText('Tenant 2')).toBeInTheDocument();
    });
  });

  it('should show empty state when no tenants', async () => {
    vi.mocked(tenantService.list).mockResolvedValue([]);

    renderWithQueryClient(<TenantsList />);

    await waitFor(() => {
      expect(screen.getByText(/no tenants found/i)).toBeInTheDocument();
    });
  });
});
```

---

## Environment Variables

```bash
# .env.example

# API Configuration
VITE_API_BASE_URL=http://localhost:8000
VITE_API_VERSION=v1

# Application
VITE_APP_NAME="Super Admin Client"
VITE_APP_ENV=development

# Feature Flags
VITE_ENABLE_DEV_TOOLS=true
VITE_ENABLE_MOCK_API=false

# OAuth (if applicable)
VITE_OAUTH_CLIENT_ID=
VITE_OAUTH_REDIRECT_URI=
```

```typescript
// ✅ config/env.ts
import { z } from 'zod';

const envSchema = z.object({
  VITE_API_BASE_URL: z.string().url(),
  VITE_API_VERSION: z.string(),
  VITE_APP_NAME: z.string(),
  VITE_APP_ENV: z.enum(['development', 'staging', 'production']),
  VITE_ENABLE_DEV_TOOLS: z.string().transform(v => v === 'true'),
  VITE_ENABLE_MOCK_API: z.string().transform(v => v === 'true'),
});

export const env = envSchema.parse(import.meta.env);
```

---

## Debugging

### React DevTools

- Install React Developer Tools extension for Chrome/Firefox
- Use Components tab to inspect component tree
- Use Profiler tab to identify performance issues

### TanStack Query DevTools

```typescript
// ✅ providers/QueryProvider.tsx
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ReactQueryDevtools } from '@tanstack/react-query-devtools';

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 5 * 60 * 1000,
      retry: 1,
    },
  },
});

export function QueryProvider({ children }: { children: React.ReactNode }) {
  return (
    <QueryClientProvider client={queryClient}>
      {children}
      {import.meta.env.DEV && <ReactQueryDevtools initialIsOpen={false} />}
    </QueryClientProvider>
  );
}
```

### Network Debugging

```typescript
// Enable request/response logging in development
if (import.meta.env.DEV) {
  apiClient.interceptors.request.use((config) => {
    console.log('[API Request]', config.method?.toUpperCase(), config.url);
    return config;
  });

  apiClient.interceptors.response.use(
    (response) => {
      console.log('[API Response]', response.config.url, response.status);
      return response;
    },
    (error) => {
      console.error('[API Error]', error.config?.url, error.response?.status, error.message);
      return Promise.reject(error);
    }
  );
}
```

---

## Key Documentation

- `swagger/openapi.yaml` - OpenAPI specification for the backend API
- `API_DESIGN.md` - Backend API design principles
- `docs/client-app-planning/` - Frontend planning documentation
- `https://tanstack.com/query` - TanStack Query documentation
- `https://zustand-demo.pmnd.rs` - Zustand documentation
- `https://ui.shadcn.com` - ShadcnUI documentation
- `https://vitejs.dev` - Vite documentation

---

## Quick Reference

### Common Commands

```bash
# Development
npm run dev              # Start dev server
npm run build            # Production build
npm run preview          # Preview production build

# Code Quality
npm run lint             # Run ESLint
npm run format           # Format with Prettier
npm run type-check       # TypeScript type checking

# Testing
npm run test             # Run all tests
npm run test:unit        # Unit tests
npm run test:e2e         # E2E tests
```

### API Client Generation

```bash
# Regenerate API client from OpenAPI spec
npm run generate:api

# This command reads swagger/openapi.yaml and generates:
# - src/api/generated/core/
# - src/api/generated/models/
# - src/api/generated/services/
```

### State Management Decision Tree

```
Need to store data?
│
├─→ Server state (from API)?
│   └─→ Use TanStack Query (useQuery, useMutation)
│
├─→ Client state (UI, session, cart)?
│   └─→ Use Zustand (useSessionStore, useUIStore)
│
└─→ Form state?
    └─→ Use React Hook Form (useForm)
```

---

## Notes

- **Generated Code**: Never manually edit files in `src/api/generated/`. Always regenerate from OpenAPI spec.
- **Tenant Context**: All API calls (except auth) require `tenant_id`. Use `useTenantContext()` hook.
- **Authorization**: Always verify permissions on the backend. UI authorization is for UX only.
- **Error Handling**: Use global error handling in Axios interceptors. Show user-friendly messages with toasts.
