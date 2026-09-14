import { Badge } from '@/components/ui/badge'
import type { OrderStatus } from '@/types/api'

const STATUS_VARIANT: Record<OrderStatus, string> = {
  pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300',
  confirmed: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
  processing: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300',
  shipped: 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300',
  delivered: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
  cancelled: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
  refunded: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
}

export function OrderStatusBadge({ status }: { status: OrderStatus }) {
  return (
    <Badge className={STATUS_VARIANT[status]} variant="secondary">
      {status}
    </Badge>
  )
}
