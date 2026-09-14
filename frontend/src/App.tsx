import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { BrowserRouter, Route, Routes } from 'react-router-dom'
import { Toaster } from 'sonner'
import { AuthProvider } from '@/contexts/AuthContext'
import { ProtectedRoute } from '@/components/ProtectedRoute'
import { DashboardLayout } from '@/components/layout/DashboardLayout'
import { LoginPage } from '@/pages/LoginPage'
import { DashboardPage } from '@/pages/DashboardPage'
import { ProductsPage } from '@/pages/ProductsPage'
import { CategoriesPage } from '@/pages/CategoriesPage'
import { WarehousesPage } from '@/pages/WarehousesPage'
import { StockPage } from '@/pages/StockPage'
import { CustomersPage } from '@/pages/CustomersPage'
import { OrdersPage } from '@/pages/OrdersPage'
import { OrderDetailPage } from '@/pages/OrderDetailPage'
import { AuditLogsPage } from '@/pages/AuditLogsPage'

const queryClient = new QueryClient()

function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <BrowserRouter>
          <Routes>
            <Route path="/login" element={<LoginPage />} />

            <Route element={<ProtectedRoute />}>
              <Route element={<DashboardLayout />}>
                <Route path="/" element={<DashboardPage />} />
                <Route path="/products" element={<ProductsPage />} />
                <Route path="/categories" element={<CategoriesPage />} />
                <Route path="/warehouses" element={<WarehousesPage />} />
                <Route path="/stock" element={<StockPage />} />
                <Route path="/customers" element={<CustomersPage />} />
                <Route path="/orders" element={<OrdersPage />} />
                <Route path="/orders/:id" element={<OrderDetailPage />} />

                <Route element={<ProtectedRoute roles={['Admin']} />}>
                  <Route path="/audit-logs" element={<AuditLogsPage />} />
                </Route>
              </Route>
            </Route>
          </Routes>
          <Toaster richColors position="top-right" />
        </BrowserRouter>
      </AuthProvider>
    </QueryClientProvider>
  )
}

export default App
