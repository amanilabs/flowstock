import { useState } from 'react'
import { Boxes, ClipboardList, ShieldCheck, TrendingUp } from 'lucide-react'
import { Navigate, useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { useAuth } from '@/contexts/AuthContext'
import { ApiError } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'

const FEATURES = [
  { icon: Boxes, text: 'Real-time stock across every warehouse' },
  { icon: TrendingUp, text: 'Full order lifecycle, start to refund' },
  { icon: ShieldCheck, text: 'Role-aware access, tenant by tenant' },
]

const DEMO_ACCOUNTS = [
  { tenant: 'Acme Supply Co.', domain: 'acme.test' },
  { tenant: 'Northstar Office Supplies', domain: 'northstar.test' },
  { tenant: 'Urban Retail GmbH', domain: 'urbanretail.test' },
]

export function LoginPage() {
  const { isAuthenticated, login } = useAuth()
  const navigate = useNavigate()
  const [email, setEmail] = useState('admin@acme.test')
  const [password, setPassword] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)

  if (isAuthenticated) {
    return <Navigate to="/" replace />
  }

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault()
    setIsSubmitting(true)
    try {
      await login(email, password)
      navigate('/')
    } catch (error) {
      const message = error instanceof ApiError ? error.message : 'Unable to sign in. Please try again.'
      toast.error(message)
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <div className="grid min-h-svh lg:grid-cols-2">
      <div className="from-primary to-primary/70 relative hidden flex-col justify-between overflow-hidden bg-gradient-to-br p-10 text-white lg:flex">
        <div className="flex items-center gap-2">
          <div className="flex size-8 items-center justify-center rounded-md bg-white/15">
            <ClipboardList className="size-5" />
          </div>
          <span className="text-lg font-semibold">FlowStock</span>
        </div>

        <div className="flex flex-col gap-6">
          <h2 className="text-3xl font-semibold text-balance">
            Multi-tenant inventory & order management, in one dashboard.
          </h2>
          <ul className="flex flex-col gap-3">
            {FEATURES.map((feature) => (
              <li key={feature.text} className="flex items-center gap-3 text-white/90">
                <feature.icon className="size-5 shrink-0" />
                <span>{feature.text}</span>
              </li>
            ))}
          </ul>
        </div>

        <p className="text-sm text-white/70">Portfolio project — FlowStock backend + admin dashboard.</p>

        <div className="bg-primary-foreground/10 absolute -top-24 -right-24 size-72 rounded-full blur-3xl" />
        <div className="bg-primary-foreground/10 absolute -bottom-24 -left-12 size-72 rounded-full blur-3xl" />
      </div>

      <div className="flex items-center justify-center p-6">
        <div className="w-full max-w-sm">
          <div className="mb-8 flex flex-col gap-1 lg:hidden">
            <div className="flex items-center gap-2">
              <div className="bg-primary text-primary-foreground flex size-8 items-center justify-center rounded-md">
                <ClipboardList className="size-5" />
              </div>
              <span className="text-lg font-semibold">FlowStock</span>
            </div>
          </div>

          <h1 className="text-2xl font-semibold">Welcome back</h1>
          <p className="text-muted-foreground mt-1 text-sm">Sign in to the admin dashboard</p>

          <form onSubmit={handleSubmit} className="mt-6 flex flex-col gap-4">
            <div className="flex flex-col gap-2">
              <Label htmlFor="email">Email</Label>
              <Input
                id="email"
                type="email"
                required
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                autoComplete="email"
              />
            </div>
            <div className="flex flex-col gap-2">
              <Label htmlFor="password">Password</Label>
              <Input
                id="password"
                type="password"
                required
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                autoComplete="current-password"
              />
            </div>
            <Button type="submit" disabled={isSubmitting} className="mt-2">
              {isSubmitting ? 'Signing in…' : 'Sign in'}
            </Button>
          </form>

          <div className="bg-muted/50 mt-6 rounded-lg border p-3">
            <p className="text-center text-xs font-medium">Demo accounts — password is "password" for all</p>
            <p className="text-muted-foreground mt-1 text-center text-xs">
              Click a role to fill the sign-in form
            </p>
            <div className="mt-2 overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead className="h-7 text-xs">Tenant</TableHead>
                    <TableHead className="h-7 text-xs">Admin</TableHead>
                    <TableHead className="h-7 text-xs">Manager</TableHead>
                    <TableHead className="h-7 text-xs">Staff</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {DEMO_ACCOUNTS.map((account) => (
                    <TableRow key={account.domain}>
                      <TableCell className="text-xs font-medium whitespace-nowrap">{account.tenant}</TableCell>
                      {(['admin', 'manager', 'staff'] as const).map((role) => (
                        <TableCell key={role} className="p-1 text-xs">
                          <button
                            type="button"
                            className="text-muted-foreground hover:text-foreground underline-offset-2 hover:underline"
                            onClick={() => {
                              setEmail(`${role}@${account.domain}`)
                              setPassword('password')
                            }}
                          >
                            {role}
                          </button>
                        </TableCell>
                      ))}
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}
