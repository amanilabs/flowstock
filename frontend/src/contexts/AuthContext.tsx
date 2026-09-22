import { createContext, useContext, useEffect, useState, type ReactNode } from 'react'
import { toast } from 'sonner'
import { api, clearSession, getToken, setToken as persistToken, USER_KEY } from '@/lib/api'
import { connectEcho, disconnectEcho } from '@/lib/echo'
import type { LoginResponse, LowStockAlert, Role, User } from '@/types/api'

interface AuthContextValue {
  user: User | null
  isAuthenticated: boolean
  login: (email: string, password: string) => Promise<void>
  logout: () => void
  hasRole: (...roles: Role[]) => boolean
}

const AuthContext = createContext<AuthContextValue | null>(null)

function loadStoredUser(): User | null {
  const raw = localStorage.getItem(USER_KEY)
  if (!raw) return null
  try {
    return JSON.parse(raw) as User
  } catch {
    return null
  }
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(loadStoredUser)

  async function login(email: string, password: string) {
    const response = await api.post<LoginResponse>('/login', { email, password })
    persistToken(response.token)
    localStorage.setItem(USER_KEY, JSON.stringify(response.user))
    setUser(response.user)
  }

  function logout() {
    api.post('/logout').catch(() => undefined)
    clearSession()
    setUser(null)
  }

  function hasRole(...roles: Role[]) {
    if (!user) return false
    return roles.some((role) => user.roles.includes(role))
  }

  // A cached user with no token (e.g. left behind after a 401 elsewhere)
  // must never count as authenticated — that's what caused the login page
  // and the protected dashboard to bounce off each other in a reload loop.
  const isAuthenticated = user !== null && getToken() !== null

  // Subscribe to this user's private channel for real-time low-stock
  // alerts once authenticated; tear the whole connection down on
  // logout/session cleanup so a stale socket never outlives the session.
  useEffect(() => {
    if (!isAuthenticated || !user) return

    const echo = connectEcho()
    const channel = echo.private(`App.Models.User.${user.id}`)

    channel.notification((notification: LowStockAlert) => {
      if (notification.type !== 'LowStockAlert') return
      toast.warning(`Low stock: ${notification.product.name}`, {
        description: `${notification.warehouse.name} — ${notification.available_quantity} left (reorder point ${notification.reorder_point})`,
      })
    })

    return () => {
      disconnectEcho()
    }
  }, [isAuthenticated, user])

  return (
    <AuthContext.Provider value={{ user, isAuthenticated, login, logout, hasRole }}>
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext)
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider')
  }
  return context
}
