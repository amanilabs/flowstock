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
import type { OrderStatus } from '@/types/api'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Textarea } from '@/components/ui/textarea'
import { OrderStatusBadge } from '@/components/StatusBadge'

const TRANSITIONS: Record<OrderStatus, OrderStatus[]> = {
  pending: ['confirmed', 'cancelled'],
  confirmed: ['processing', 'shipped', 'cancelled'],
  processing: ['shipped', 'cancelled'],
  shipped: ['delivered', 'refunded'],
  delivered: ['refunded'],
  cancelled: [],
  refunded: [],
}

export function OrderDetailPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const { hasRole } = useAuth()
  const orderId = id ? Number(id) : null
  const { data, isLoading } = useOrder(orderId)
  const [reasonDialog, setReasonDialog] = useState<'cancel' | 'refund' | null>(null)
  const [reason, setReason] = useState('')

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

      <div className="grid grid-cols-2 gap-4">
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

      <div className="rounded-md border">
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
                <TableCell>${item.unit_price}</TableCell>
                <TableCell>${item.subtotal}</TableCell>
                <TableCell className="text-muted-foreground">{item.reservation_status ?? '—'}</TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>

      <p className="text-right text-lg font-semibold">Total: ${order.total_amount}</p>

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
            <Button onClick={() => runAction(() => confirmOrder.mutateAsync({ id: order.id }), 'Order confirmed')}>
              Confirm
            </Button>
          )}
          {allowed.includes('processing') && canManage && (
            <Button
              variant="secondary"
              onClick={() => runAction(() => processOrder.mutateAsync({ id: order.id }), 'Order marked processing')}
            >
              Mark processing
            </Button>
          )}
          {allowed.includes('shipped') && canManage && (
            <Button onClick={() => runAction(() => shipOrder.mutateAsync({ id: order.id }), 'Order shipped')}>
              Ship
            </Button>
          )}
          {allowed.includes('delivered') && canManage && (
            <Button onClick={() => runAction(() => deliverOrder.mutateAsync({ id: order.id }), 'Order delivered')}>
              Mark delivered
            </Button>
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

      <Dialog open={reasonDialog !== null} onOpenChange={(open) => !open && setReasonDialog(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{reasonDialog === 'cancel' ? 'Cancel order' : 'Refund order'}</DialogTitle>
          </DialogHeader>
          <Textarea
            placeholder="Reason (optional)"
            value={reason}
            onChange={(e) => setReason(e.target.value)}
          />
          <DialogFooter>
            <Button variant="destructive" onClick={submitReason}>
              Confirm {reasonDialog}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
