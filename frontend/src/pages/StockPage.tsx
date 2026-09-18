import { useState } from 'react'
import { zodResolver } from '@hookform/resolvers/zod'
import { toast } from 'sonner'
import { z } from 'zod'
import { useProducts } from '@/hooks/queries/useProducts'
import { useAdjustStock, useProductStock } from '@/hooks/queries/useStock'
import { ApiError } from '@/lib/api'
import type { StockMovementType } from '@/types/api'
import { useForm } from 'react-hook-form'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
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
import { Textarea } from '@/components/ui/textarea'
import { PageHeader } from '@/components/PageHeader'

const MOVEMENT_TYPES: StockMovementType[] = [
  'received',
  'sold',
  'adjustment',
  'damaged',
  'returned',
  'transfer_in',
  'transfer_out',
]

const adjustSchema = z.object({
  warehouse_id: z.string().min(1, 'Required'),
  quantity_change: z.coerce.number().refine((v) => v !== 0, 'Cannot be zero'),
  type: z.enum(MOVEMENT_TYPES),
  note: z.string().optional(),
})

type AdjustFormValues = z.infer<typeof adjustSchema>

export function StockPage() {
  const [productId, setProductId] = useState<number | null>(null)
  const [dialogOpen, setDialogOpen] = useState(false)

  const { data: productsData } = useProducts({ page: 1 })
  const { data: stockData, isLoading } = useProductStock(productId)

  const selectedProduct = productsData?.data.find((p) => p.id === productId) ?? null

  return (
    <div className="flex flex-col gap-4">
      <PageHeader title="Stock" description="On-hand quantities per warehouse, with low-stock alerts." />

      <Card>
        <CardContent className="flex items-end gap-4">
          <div className="flex flex-col gap-2">
            <label className="text-sm font-medium">Product</label>
            <Select
              value={productId ? String(productId) : ''}
              onValueChange={(value) => setProductId(Number(value))}
            >
              <SelectTrigger className="w-64">
                <SelectValue placeholder="Select a product" />
              </SelectTrigger>
              <SelectContent>
                {productsData?.data.map((product) => (
                  <SelectItem key={product.id} value={String(product.id)}>
                    {product.name} ({product.sku})
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          {selectedProduct && (
            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
              <DialogTrigger asChild>
                <Button>Adjust stock</Button>
              </DialogTrigger>
              <AdjustStockDialog
                productId={selectedProduct.id}
                warehouses={stockData?.data.map((s) => s.warehouse) ?? []}
                onSaved={() => setDialogOpen(false)}
              />
            </Dialog>
          )}
        </CardContent>
      </Card>

      {productId && (
        <div className="rounded-md border">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Warehouse</TableHead>
                <TableHead>Quantity on hand</TableHead>
                <TableHead>Last updated</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {isLoading ? (
                <TableRow>
                  <TableCell colSpan={3} className="text-muted-foreground text-center">
                    Loading…
                  </TableCell>
                </TableRow>
              ) : stockData?.data.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={3} className="text-muted-foreground text-center">
                    No stock recorded for this product yet.
                  </TableCell>
                </TableRow>
              ) : (
                stockData?.data.map((stock) => (
                  <TableRow key={stock.id}>
                    <TableCell className="font-medium">{stock.warehouse.name}</TableCell>
                    <TableCell>
                      <Badge
                        variant={
                          selectedProduct && stock.quantity <= selectedProduct.reorder_point
                            ? 'destructive'
                            : 'default'
                        }
                      >
                        {stock.quantity}
                      </Badge>
                    </TableCell>
                    <TableCell>{new Date(stock.updated_at).toLocaleString()}</TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </div>
      )}
    </div>
  )
}

function AdjustStockDialog({
  productId,
  warehouses,
  onSaved,
}: {
  productId: number
  warehouses: { id: number; name: string }[]
  onSaved: () => void
}) {
  const adjustStock = useAdjustStock(productId)

  const form = useForm({
    resolver: zodResolver(adjustSchema),
    defaultValues: { warehouse_id: '', quantity_change: 0, type: 'adjustment', note: '' },
  })

  async function onSubmit(values: AdjustFormValues) {
    try {
      await adjustStock.mutateAsync({
        warehouse_id: Number(values.warehouse_id),
        quantity_change: values.quantity_change,
        type: values.type,
        note: values.note,
      })
      toast.success('Stock adjusted')
      form.reset()
      onSaved()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Failed to adjust stock')
    }
  }

  return (
    <DialogContent>
      <DialogHeader>
        <DialogTitle>Adjust stock</DialogTitle>
      </DialogHeader>
      <Form {...form}>
        <form onSubmit={form.handleSubmit(onSubmit)} className="flex flex-col gap-4">
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
                    {warehouses.map((warehouse) => (
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
          <div className="grid grid-cols-2 gap-4">
            <FormField
              control={form.control}
              name="quantity_change"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Quantity change</FormLabel>
                  <FormControl>
                    <Input type="number" {...field} value={field.value as number} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="type"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Type</FormLabel>
                  <Select onValueChange={field.onChange} value={field.value}>
                    <FormControl>
                      <SelectTrigger className="w-full">
                        <SelectValue />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {MOVEMENT_TYPES.map((type) => (
                        <SelectItem key={type} value={type}>
                          {type}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <FormMessage />
                </FormItem>
              )}
            />
          </div>
          <FormField
            control={form.control}
            name="note"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Note</FormLabel>
                <FormControl>
                  <Textarea {...field} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
          <DialogFooter>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              Record movement
            </Button>
          </DialogFooter>
        </form>
      </Form>
    </DialogContent>
  )
}
