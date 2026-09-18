import { Package, ShoppingCart, Users, Warehouse as WarehouseIcon } from 'lucide-react'
import { useNavigate } from 'react-router-dom'
import { useAuth } from '@/contexts/AuthContext'
import { useCustomers } from '@/hooks/queries/useCustomers'
import { useOrders } from '@/hooks/queries/useOrders'
import { useProducts } from '@/hooks/queries/useProducts'
import { useWarehouses } from '@/hooks/queries/useWarehouses'
import { formatCurrency } from '@/lib/utils'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { OrderStatusBadge } from '@/components/StatusBadge'
import { OrdersOverTimeChart } from '@/components/OrdersOverTimeChart'
import { SectionCards } from '@/components/SectionCards'

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
      caption: 'In your catalog',
      icon: Package,
      to: '/products',
    },
    {
      label: 'Orders',
      value: orders?.meta.total,
      caption: 'Placed to date',
      icon: ShoppingCart,
      to: '/orders',
    },
    {
      label: 'Customers',
      value: customers?.meta.total,
      caption: 'Registered accounts',
      icon: Users,
      to: '/customers',
    },
    {
      label: 'Warehouses',
      value: warehouses?.meta.total,
      caption: 'Fulfillment locations',
      icon: WarehouseIcon,
      to: '/warehouses',
    },
  ]

  return (
    <div className="@container/main flex flex-col gap-6">
      <div>
        <h1 className="text-2xl font-semibold">Welcome back, {user?.name}</h1>
        <p className="text-muted-foreground">Here's what's happening at your company today.</p>
      </div>

      <SectionCards stats={stats} />

      <OrdersOverTimeChart />

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
                  <TableCell>${formatCurrency(order.total_amount)}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  )
}
