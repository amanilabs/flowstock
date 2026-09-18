import {
  Boxes,
  ChevronsUpDown,
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
import { Link, useLocation } from 'react-router-dom'
import { useAuth } from '@/contexts/AuthContext'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
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
  { to: '/', label: 'Dashboard', icon: LayoutDashboard, end: true },
  { to: '/products', label: 'Products', icon: Package, end: false },
  { to: '/categories', label: 'Categories', icon: Tags, end: false },
  { to: '/warehouses', label: 'Warehouses', icon: WarehouseIcon, end: false },
  { to: '/stock', label: 'Stock', icon: Boxes, end: false },
  { to: '/customers', label: 'Customers', icon: Users, end: false },
  { to: '/orders', label: 'Orders', icon: ShoppingCart, end: false },
]

const ADMIN_NAV_ITEMS = [{ to: '/audit-logs', label: 'Audit Logs', icon: FileClock, end: false }]

function initials(name: string) {
  return name
    .split(' ')
    .map((part) => part[0])
    .slice(0, 2)
    .join('')
    .toUpperCase()
}

export function AppSidebar() {
  const { user, hasRole, logout } = useAuth()
  const location = useLocation()

  function isItemActive(to: string, end: boolean) {
    return end ? location.pathname === to : location.pathname.startsWith(to)
  }

  return (
    <Sidebar>
      <SidebarHeader>
        <div className="flex items-center gap-2 px-2 py-2">
          <div className="bg-primary text-primary-foreground flex size-7 items-center justify-center rounded-md">
            <ClipboardList className="size-4" />
          </div>
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
                  <SidebarMenuButton asChild isActive={isItemActive(item.to, item.end)}>
                    <Link to={item.to}>
                      <item.icon />
                      <span>{item.label}</span>
                    </Link>
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
                    <SidebarMenuButton asChild isActive={isItemActive(item.to, item.end)}>
                      <Link to={item.to}>
                        <item.icon />
                        <span>{item.label}</span>
                      </Link>
                    </SidebarMenuButton>
                  </SidebarMenuItem>
                ))}
              </SidebarMenu>
            </SidebarGroupContent>
          </SidebarGroup>
        )}
      </SidebarContent>
      <SidebarFooter>
        <SidebarMenu>
          <SidebarMenuItem>
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <SidebarMenuButton size="lg">
                  <Avatar className="size-7 rounded-md">
                    <AvatarFallback className="bg-primary text-primary-foreground rounded-md text-xs">
                      {user ? initials(user.name) : '?'}
                    </AvatarFallback>
                  </Avatar>
                  <div className="flex flex-1 flex-col overflow-hidden text-left">
                    <span className="truncate text-sm font-medium">{user?.name}</span>
                    <span className="text-muted-foreground truncate text-xs">
                      {user?.roles.join(', ')}
                    </span>
                  </div>
                  <ChevronsUpDown className="text-muted-foreground size-4" />
                </SidebarMenuButton>
              </DropdownMenuTrigger>
              <DropdownMenuContent side="top" align="start" className="w-56">
                <DropdownMenuLabel className="font-normal">
                  <div className="flex flex-col">
                    <span className="font-medium">{user?.name}</span>
                    <span className="text-muted-foreground text-xs">{user?.email}</span>
                  </div>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem onClick={logout} variant="destructive">
                  <LogOut />
                  Log out
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarFooter>
    </Sidebar>
  )
}
