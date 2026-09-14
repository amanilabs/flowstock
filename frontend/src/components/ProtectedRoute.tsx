import { Navigate, Outlet } from 'react-router-dom'
import { useAuth } from '@/contexts/AuthContext'
import type { Role } from '@/types/api'

export function ProtectedRoute({ roles }: { roles?: Role[] }) {
  const { isAuthenticated, hasRole } = useAuth()

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />
  }

  if (roles && !hasRole(...roles)) {
    return <Navigate to="/" replace />
  }

  return <Outlet />
}
