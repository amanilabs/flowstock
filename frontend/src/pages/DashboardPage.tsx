import { Package, ShoppingCart, Users, Warehouse as WarehouseIcon } from 'lucide-react'
import { useNavigate } from 'react-router-dom'
import { useAuth } from '@/contexts/AuthContext'
import { useCustomers } from '@/hooks/queries/useCustomers'
import { useOrders } from '@/hooks/queries/useOrders'
import { useProducts } from '@/hooks/queries/useProducts'
import { useWarehouses } from '@/hooks/queries/useWarehouses'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { OrderStatusBadge } from '@/components/StatusBadge'

export function DashboardPage() {
  const { user } = useAuth()
  const navigate = useNavigate()

  const { data: products } = useProducts({ page: 1 })
  const { data: orders } = useOrders({ page: 1 })
  const { data: customers } = useCustomers({ page: 1 })
  const { data: warehouses } = useWarehouses({ page: 1 })

  const stats = [
    {
      label: 'Products',
      value: products?.meta.total,
      icon: Package,
      to: '/products',
      color: 'bg-blue-100 text-blue-600 dark:bg-blue-900/40 dark:text-blue-400',
    },
    {
      label: 'Orders',
      value: orders?.meta.total,
      icon: ShoppingCart,
      to: '/orders',
      color: 'bg-violet-100 text-violet-600 dark:bg-violet-900/40 dark:text-violet-400',
    },
    {
      label: 'Customers',
      value: customers?.meta.total,
      icon: Users,
      to: '/customers',
      color: 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/40 dark:text-emerald-400',
    },
    {
      label: 'Warehouses',
      value: warehouses?.meta.total,
      icon: WarehouseIcon,
      to: '/warehouses',
      color: 'bg-amber-100 text-amber-600 dark:bg-amber-900/40 dark:text-amber-400',
    },
  ]

  return (
    <div className="flex flex-col gap-6">
      <div>
        <h1 className="text-2xl font-semibold">Welcome back, {user?.name}</h1>
        <p className="text-muted-foreground">Here's what's happening at your company today.</p>
      </div>

      <div className="grid grid-cols-4 gap-4">
        {stats.map((stat) => (
          <Card
            key={stat.label}
            className="cursor-pointer transition-shadow hover:shadow-md"
            onClick={() => navigate(stat.to)}
          >
            <CardContent className="flex items-center justify-between p-6">
              <div>
                <p className="text-muted-foreground text-sm">{stat.label}</p>
                <p className="text-2xl font-bold">{stat.value ?? '—'}</p>
              </div>
              <div className={`flex size-11 items-center justify-center rounded-lg ${stat.color}`}>
                <stat.icon className="size-5" />
              </div>
            </CardContent>
          </Card>
        ))}
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Recent orders</CardTitle>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Order #</TableHead>
                <TableHead>Customer</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Total</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {orders?.data.slice(0, 5).map((order) => (
                <TableRow
                  key={order.id}
                  className="cursor-pointer"
                  onClick={() => navigate(`/orders/${order.id}`)}
                >
                  <TableCell className="font-medium">{order.order_number}</TableCell>
                  <TableCell>{order.customer.name}</TableCell>
                  <TableCell>
                    <OrderStatusBadge status={order.status} />
                  </TableCell>
                  <TableCell>${order.total_amount}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  )
}
