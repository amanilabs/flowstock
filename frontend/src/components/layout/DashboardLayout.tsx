import { Building2 } from 'lucide-react'
import { Outlet } from 'react-router-dom'
import { useAuth } from '@/contexts/AuthContext'
import { AppSidebar } from '@/components/layout/AppSidebar'
import { SidebarInset, SidebarProvider, SidebarTrigger } from '@/components/ui/sidebar'
import { Separator } from '@/components/ui/separator'
import { ThemeToggle } from '@/components/ThemeToggle'

export function DashboardLayout() {
  const { user } = useAuth()

  return (
    <SidebarProvider>
      <AppSidebar />
      <SidebarInset>
        <header className="bg-background/80 sticky top-0 z-10 flex h-14 items-center gap-2 border-b px-4 backdrop-blur-sm">
          <SidebarTrigger />
          <Separator orientation="vertical" className="h-4" />
          {user?.tenant_name && (
            <div className="text-muted-foreground flex items-center gap-1.5 text-sm">
              <Building2 className="size-4" />
              <span className="font-medium">{user.tenant_name}</span>
            </div>
          )}
          <div className="flex-1" />
          <ThemeToggle />
        </header>
        <main className="bg-muted/20 flex-1 overflow-y-auto p-6">
          <Outlet />
        </main>
      </SidebarInset>
    </SidebarProvider>
  )
}
