import { useState } from 'react'
import { zodResolver } from '@hookform/resolvers/zod'
import { Plus, Trash2 } from 'lucide-react'
import { useFieldArray, useForm } from 'react-hook-form'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { z } from 'zod'
import { useCustomers } from '@/hooks/queries/useCustomers'
import { useCreateOrder, useOrders } from '@/hooks/queries/useOrders'
import { useProducts } from '@/hooks/queries/useProducts'
import { useWarehouses } from '@/hooks/queries/useWarehouses'
import { ApiError } from '@/lib/api'
import type { OrderStatus } from '@/types/api'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { OrderStatusBadge } from '@/components/StatusBadge'
import { PaginationBar } from '@/components/PaginationBar'

const STATUS_OPTIONS: (OrderStatus | '')[] = [
  '',
  'pending',
  'confirmed',
  'processing',
  'shipped',
  'delivered',
  'cancelled',
  'refunded',
]

const orderSchema = z.object({
  customer_id: z.string().min(1, 'Required'),
  warehouse_id: z.string().min(1, 'Required'),
  items: z
    .array(
      z.object({
        product_id: z.string().min(1, 'Required'),
        quantity: z.coerce.number().min(1),
      }),
    )
    .min(1, 'Add at least one item'),
})

type OrderFormValues = z.infer<typeof orderSchema>

export function OrdersPage() {
  const [page, setPage] = useState(1)
  const [status, setStatus] = useState<OrderStatus | ''>('')
  const [dialogOpen, setDialogOpen] = useState(false)
  const navigate = useNavigate()

  const { data, isLoading } = useOrders({ page, status })

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold">Orders</h1>
        <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
          <DialogTrigger asChild>
            <Button>
              <Plus className="size-4" />
              New order
            </Button>
          </DialogTrigger>
          <CreateOrderDialog onSaved={() => setDialogOpen(false)} />
        </Dialog>
      </div>

      <Select
        value={status || 'all'}
        onValueChange={(value) => {
          setStatus(value === 'all' ? '' : (value as OrderStatus))
          setPage(1)
        }}
      >
        <SelectTrigger className="w-48">
          <SelectValue placeholder="All statuses" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">All statuses</SelectItem>
          {STATUS_OPTIONS.filter(Boolean).map((s) => (
            <SelectItem key={s} value={s}>
              {s}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>

      <div className="rounded-md border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Order #</TableHead>
              <TableHead>Customer</TableHead>
              <TableHead>Status</TableHead>
              <TableHead>Total</TableHead>
              <TableHead>Created</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {isLoading ? (
              <TableRow>
                <TableCell colSpan={5} className="text-muted-foreground text-center">
                  Loading…
                </TableCell>
              </TableRow>
            ) : data?.data.length === 0 ? (
              <TableRow>
                <TableCell colSpan={5} className="text-muted-foreground text-center">
                  No orders found.
                </TableCell>
              </TableRow>
            ) : (
              data?.data.map((order) => (
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
                  <TableCell>{new Date(order.created_at).toLocaleDateString()}</TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </div>

      {data?.meta && <PaginationBar meta={data.meta} onPageChange={setPage} />}
    </div>
  )
}

function CreateOrderDialog({ onSaved }: { onSaved: () => void }) {
  const { data: customersData } = useCustomers({ page: 1 })
  const { data: warehousesData } = useWarehouses({ page: 1, active_only: true })
  const { data: productsData } = useProducts({ page: 1, active_only: true })
  const createOrder = useCreateOrder()

  const form = useForm({
    resolver: zodResolver(orderSchema),
    defaultValues: {
      customer_id: '',
      warehouse_id: '',
      items: [{ product_id: '', quantity: 1 }],
    },
  })

  const { fields, append, remove } = useFieldArray({ control: form.control, name: 'items' })

  async function onSubmit(values: OrderFormValues) {
    try {
      await createOrder.mutateAsync({
        customer_id: Number(values.customer_id),
        warehouse_id: Number(values.warehouse_id),
        items: values.items.map((item) => ({
          product_id: Number(item.product_id),
          quantity: item.quantity,
        })),
      })
      toast.success('Order created')
      form.reset()
      onSaved()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Failed to create order')
    }
  }

  return (
    <DialogContent className="max-w-lg">
      <DialogHeader>
        <DialogTitle>New order</DialogTitle>
      </DialogHeader>
      <Form {...form}>
        <form onSubmit={form.handleSubmit(onSubmit)} className="flex flex-col gap-4">
          <div className="grid grid-cols-2 gap-4">
            <FormField
              control={form.control}
              name="customer_id"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Customer</FormLabel>
                  <Select onValueChange={field.onChange} value={field.value}>
                    <FormControl>
                      <SelectTrigger className="w-full">
                        <SelectValue placeholder="Select customer" />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {customersData?.data.map((customer) => (
                        <SelectItem key={customer.id} value={String(customer.id)}>
                          {customer.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="warehouse_id"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Warehouse</FormLabel>
                  <Select onValueChange={field.onChange} value={field.value}>
                    <FormControl>
                      <SelectTrigger className="w-full">
                        <SelectValue placeholder="Select warehouse" />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {warehousesData?.data.map((warehouse) => (
                        <SelectItem key={warehouse.id} value={String(warehouse.id)}>
                          {warehouse.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <FormMessage />
                </FormItem>
              )}
            />
          </div>

          <div className="flex flex-col gap-2">
            <FormLabel>Items</FormLabel>
            {fields.map((item, index) => (
              <div key={item.id} className="flex items-end gap-2">
                <FormField
                  control={form.control}
                  name={`items.${index}.product_id`}
                  render={({ field }) => (
                    <FormItem className="flex-1">
                      <Select onValueChange={field.onChange} value={field.value}>
                        <FormControl>
                          <SelectTrigger className="w-full">
                            <SelectValue placeholder="Select product" />
                          </SelectTrigger>
                        </FormControl>
                        <SelectContent>
                          {productsData?.data.map((product) => (
                            <SelectItem key={product.id} value={String(product.id)}>
                              {product.name}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <FormField
                  control={form.control}
                  name={`items.${index}.quantity`}
                  render={({ field }) => (
                    <FormItem className="w-20">
                      <FormControl>
                        <Input type="number" min={1} {...field} value={field.value as number} />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  disabled={fields.length === 1}
                  onClick={() => remove(index)}
                >
                  <Trash2 className="size-4" />
                </Button>
              </div>
            ))}
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => append({ product_id: '', quantity: 1 })}
            >
              <Plus className="size-4" />
              Add item
            </Button>
          </div>

          <DialogFooter>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              Create order
            </Button>
          </DialogFooter>
        </form>
      </Form>
    </DialogContent>
  )
}
