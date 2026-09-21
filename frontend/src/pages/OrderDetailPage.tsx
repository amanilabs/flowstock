import { useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { toast } from 'sonner'
import { useAuth } from '@/contexts/AuthContext'
import {
  useCancelOrder,
  useConfirmOrder,
  useDeliverOrder,
  useOrder,
  useProcessOrder,
  useRefundOrder,
  useShipOrder,
} from '@/hooks/queries/useOrders'
import { ApiError } from '@/lib/api'
import { formatCurrency } from '@/lib/utils'
import type { OrderStatus } from '@/types/api'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Textarea } from '@/components/ui/textarea'
import { OrderStatusBadge } from '@/components/StatusBadge'

// Mirrors OrderService::TRANSITIONS — a UI hint for which action buttons to
// offer. The backend is the sole enforcer (409 on an invalid transition).
const TRANSITIONS: Record<OrderStatus, OrderStatus[]> = {
  pending: ['confirmed', 'cancelled'],
  confirmed: ['processing', 'shipped', 'cancelled'],
  processing: ['shipped', 'cancelled'],
  shipped: ['delivered', 'refunded'],
  delivered: ['refunded'],
  cancelled: [],
  refunded: [],
}

type SimpleAction = 'confirm' | 'process' | 'ship' | 'deliver'

const SIMPLE_ACTION_COPY: Record<SimpleAction, { label: string; title: string; description: string }> = {
  confirm: {
    label: 'Confirm',
    title: 'Confirm this order?',
    description: 'This reserves stock for every item on the order.',
  },
  process: {
    label: 'Mark processing',
    title: 'Mark this order as processing?',
    description: 'This signals fulfillment has started.',
  },
  ship: {
    label: 'Ship',
    title: 'Ship this order?',
    description: 'This deducts the reserved stock from inventory and cannot be undone.',
  },
  deliver: {
    label: 'Mark delivered',
    title: 'Mark this order as delivered?',
    description: 'This closes out the fulfillment lifecycle for this order.',
  },
}

export function OrderDetailPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const { hasRole } = useAuth()
  const orderId = id ? Number(id) : null
  const { data, isLoading } = useOrder(orderId)
  const [reasonDialog, setReasonDialog] = useState<'cancel' | 'refund' | null>(null)
  const [reason, setReason] = useState('')
  const [simpleAction, setSimpleAction] = useState<SimpleAction | null>(null)

  const confirmOrder = useConfirmOrder()
  const processOrder = useProcessOrder()
  const shipOrder = useShipOrder()
  const deliverOrder = useDeliverOrder()
  const cancelOrder = useCancelOrder()
  const refundOrder = useRefundOrder()

  if (isLoading) return <p className="text-muted-foreground">Loading…</p>
  if (!data) return <p className="text-muted-foreground">Order not found.</p>

  const order = data.data
  const allowed = TRANSITIONS[order.status]

  async function runAction(action: () => Promise<unknown>, successMessage: string) {
    try {
      await action()
      toast.success(successMessage)
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Action failed')
    }
  }

  function submitReason() {
    if (!orderId) return
    if (reasonDialog === 'cancel') {
      runAction(() => cancelOrder.mutateAsync({ id: orderId, reason }), 'Order cancelled')
    } else if (reasonDialog === 'refund') {
      runAction(() => refundOrder.mutateAsync({ id: orderId, reason }), 'Order refunded')
    }
    setReasonDialog(null)
    setReason('')
  }

  const SIMPLE_ACTION_MUTATIONS: Record<SimpleAction, { run: () => Promise<unknown>; successMessage: string }> = {
    confirm: { run: () => confirmOrder.mutateAsync({ id: order.id }), successMessage: 'Order confirmed' },
    process: { run: () => processOrder.mutateAsync({ id: order.id }), successMessage: 'Order marked processing' },
    ship: { run: () => shipOrder.mutateAsync({ id: order.id }), successMessage: 'Order shipped' },
    deliver: { run: () => deliverOrder.mutateAsync({ id: order.id }), successMessage: 'Order delivered' },
  }

  function submitSimpleAction() {
    if (!simpleAction) return
    const { run, successMessage } = SIMPLE_ACTION_MUTATIONS[simpleAction]
    runAction(run, successMessage)
    setSimpleAction(null)
  }

  const canManage = hasRole('Admin', 'Manager', 'Staff')
  const canCancel = hasRole('Admin', 'Manager')
  const canRefund = hasRole('Admin')

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between">
        <div>
          <Button variant="link" className="h-auto p-0" onClick={() => navigate('/orders')}>
            ← Back to orders
          </Button>
          <h1 className="text-2xl font-semibold">{order.order_number}</h1>
        </div>
        <OrderStatusBadge status={order.status} />
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle className="text-base">Customer</CardTitle>
          </CardHeader>
          <CardContent className="text-sm">
            <p className="font-medium">{order.customer.name}</p>
            <p className="text-muted-foreground">{order.customer.email}</p>
          </CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle className="text-base">Warehouse</CardTitle>
          </CardHeader>
          <CardContent className="text-sm">
            <p className="font-medium">{order.warehouse.name}</p>
            <p className="text-muted-foreground">
              {order.warehouse.city}, {order.warehouse.country}
            </p>
          </CardContent>
        </Card>
      </div>

      <div className="overflow-x-auto rounded-md border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Product</TableHead>
              <TableHead>Quantity</TableHead>
              <TableHead>Unit price</TableHead>
              <TableHead>Subtotal</TableHead>
              <TableHead>Reservation</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {order.items?.map((item) => (
              <TableRow key={item.id}>
                <TableCell className="font-medium">{item.product.name}</TableCell>
                <TableCell>{item.quantity}</TableCell>
                <TableCell>${formatCurrency(item.unit_price)}</TableCell>
                <TableCell>${formatCurrency(item.subtotal)}</TableCell>
                <TableCell className="text-muted-foreground">{item.reservation_status ?? '—'}</TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>

      <p className="text-right text-lg font-semibold">Total: ${formatCurrency(order.total_amount)}</p>

      {order.notes && (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">Notes</CardTitle>
          </CardHeader>
          <CardContent className="text-sm whitespace-pre-line">{order.notes}</CardContent>
        </Card>
      )}

      {allowed.length > 0 && (canManage || canCancel || canRefund) && (
        <div className="flex gap-2 border-t pt-4">
          {allowed.includes('confirmed') && canManage && (
            <Button onClick={() => setSimpleAction('confirm')}>Confirm</Button>
          )}
          {allowed.includes('processing') && canManage && (
            <Button variant="secondary" onClick={() => setSimpleAction('process')}>
              Mark processing
            </Button>
          )}
          {allowed.includes('shipped') && canManage && (
            <Button onClick={() => setSimpleAction('ship')}>Ship</Button>
          )}
          {allowed.includes('delivered') && canManage && (
            <Button onClick={() => setSimpleAction('deliver')}>Mark delivered</Button>
          )}
          {allowed.includes('cancelled') && canCancel && (
            <Button variant="destructive" onClick={() => setReasonDialog('cancel')}>
              Cancel
            </Button>
          )}
          {allowed.includes('refunded') && canRefund && (
            <Button variant="destructive" onClick={() => setReasonDialog('refund')}>
              Refund
            </Button>
          )}
        </div>
      )}

      <AlertDialog open={reasonDialog !== null} onOpenChange={(open) => !open && setReasonDialog(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>
              {reasonDialog === 'cancel' ? 'Cancel this order?' : 'Refund this order?'}
            </AlertDialogTitle>
            <AlertDialogDescription>
              {reasonDialog === 'cancel'
                ? 'This releases any reserved stock. This cannot be undone.'
                : 'This marks the order refunded. This cannot be undone.'}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <Textarea
            placeholder="Reason (optional)"
            value={reason}
            onChange={(e) => setReason(e.target.value)}
          />
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction onClick={submitReason} variant="destructive">
              {reasonDialog === 'cancel' ? 'Cancel order' : 'Refund order'}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <AlertDialog open={simpleAction !== null} onOpenChange={(open) => !open && setSimpleAction(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{simpleAction && SIMPLE_ACTION_COPY[simpleAction].title}</AlertDialogTitle>
            <AlertDialogDescription>
              {simpleAction && SIMPLE_ACTION_COPY[simpleAction].description}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction onClick={submitSimpleAction}>
              {simpleAction && SIMPLE_ACTION_COPY[simpleAction].label}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
