import {
  Boxes,
  ClipboardList,
  FileClock,
  LayoutDashboard,
  LogOut,
  Package,
  ShoppingCart,
  Tags,
  Users,
  Warehouse as WarehouseIcon,
} from 'lucide-react'
import { NavLink } from 'react-router-dom'
import { useAuth } from '@/contexts/AuthContext'
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from '@/components/ui/sidebar'

const NAV_ITEMS = [
  { to: '/', label: 'Dashboard', icon: LayoutDashboard },
  { to: '/products', label: 'Products', icon: Package },
  { to: '/categories', label: 'Categories', icon: Tags },
  { to: '/warehouses', label: 'Warehouses', icon: WarehouseIcon },
  { to: '/stock', label: 'Stock', icon: Boxes },
  { to: '/customers', label: 'Customers', icon: Users },
  { to: '/orders', label: 'Orders', icon: ShoppingCart },
]

const ADMIN_NAV_ITEMS = [{ to: '/audit-logs', label: 'Audit Logs', icon: FileClock }]

export function AppSidebar() {
  const { user, hasRole, logout } = useAuth()

  return (
    <Sidebar>
      <SidebarHeader>
        <div className="flex items-center gap-2 px-2 py-1.5">
          <ClipboardList className="size-5" />
          <span className="text-sm font-semibold">FlowStock</span>
        </div>
      </SidebarHeader>
      <SidebarContent>
        <SidebarGroup>
          <SidebarGroupLabel>Operations</SidebarGroupLabel>
          <SidebarGroupContent>
            <SidebarMenu>
              {NAV_ITEMS.map((item) => (
                <SidebarMenuItem key={item.to}>
                  <SidebarMenuButton asChild>
                    <NavLink
                      to={item.to}
                      end={item.to === '/'}
                      className={({ isActive }) =>
                        isActive ? 'bg-sidebar-accent text-sidebar-accent-foreground' : ''
                      }
                    >
                      <item.icon />
                      <span>{item.label}</span>
                    </NavLink>
                  </SidebarMenuButton>
                </SidebarMenuItem>
              ))}
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>

        {hasRole('Admin') && (
          <SidebarGroup>
            <SidebarGroupLabel>Admin</SidebarGroupLabel>
            <SidebarGroupContent>
              <SidebarMenu>
                {ADMIN_NAV_ITEMS.map((item) => (
                  <SidebarMenuItem key={item.to}>
                    <SidebarMenuButton asChild>
                      <NavLink
                        to={item.to}
                        className={({ isActive }) =>
                          isActive ? 'bg-sidebar-accent text-sidebar-accent-foreground' : ''
                        }
                      >
                        <item.icon />
                        <span>{item.label}</span>
                      </NavLink>
                    </SidebarMenuButton>
                  </SidebarMenuItem>
                ))}
              </SidebarMenu>
            </SidebarGroupContent>
          </SidebarGroup>
        )}
      </SidebarContent>
      <SidebarFooter>
        <div className="flex flex-col gap-2 px-2 py-1.5 text-sm">
          <div className="flex flex-col">
            <span className="font-medium">{user?.name}</span>
            <span className="text-muted-foreground text-xs">{user?.roles.join(', ')}</span>
          </div>
          <SidebarMenuButton onClick={logout}>
            <LogOut />
            <span>Log out</span>
          </SidebarMenuButton>
        </div>
      </SidebarFooter>
    </Sidebar>
  )
}
