import { createContext, useContext, useState, type ReactNode } from 'react'
import { api, clearSession, getToken, setToken as persistToken, USER_KEY } from '@/lib/api'
import type { LoginResponse, Role, User } from '@/types/api'

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
