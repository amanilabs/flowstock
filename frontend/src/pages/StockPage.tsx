import { useEffect, useState } from 'react'
import { zodResolver } from '@hookform/resolvers/zod'
import { History, MoreHorizontal, PackagePlus, SlidersHorizontal } from 'lucide-react'
import { useForm } from 'react-hook-form'
import { toast } from 'sonner'
import { z } from 'zod'
import { useCategories } from '@/hooks/queries/useCategories'
import { useProducts } from '@/hooks/queries/useProducts'
import { useAdjustStock, useInventory, useStockMovements, useUpdateReorderPoint } from '@/hooks/queries/useStock'
import { useWarehouses } from '@/hooks/queries/useWarehouses'
import { useDebouncedValue } from '@/hooks/useDebouncedValue'
import { ApiError } from '@/lib/api'
import { cn } from '@/lib/utils'
import type { InventoryRow, InventoryStatus } from '@/types/api'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Textarea } from '@/components/ui/textarea'
import { PageHeader } from '@/components/PageHeader'
import { PaginationBar } from '@/components/PaginationBar'
import { TableStateRow } from '@/components/TableStateRow'

const STATUS_LABEL: Record<InventoryStatus, string> = {
  in_stock: 'In stock',
  low_stock: 'Low stock',
  out_of_stock: 'Out of stock',
}

/**
 * Rows are grouped by product for display (Product/SKU cells span every
 * warehouse row for that product) — this only looks right because the API
 * orders rows by product, keeping a product's rows adjacent. Returns, per
 * row index, the rowSpan to render (0 means "this row is a continuation,
 * don't render the Product/SKU cells at all").
 */
function computeProductRowSpans(rows: InventoryRow[]): number[] {
  const spans = new Array(rows.length).fill(0)
  let i = 0
  while (i < rows.length) {
    let j = i + 1
    while (j < rows.length && rows[j].product.id === rows[i].product.id) j++
    spans[i] = j - i
    i = j
  }
  return spans
}

function InventoryStatusBadge({ status }: { status: InventoryStatus }) {
  if (status === 'out_of_stock') return <Badge variant="destructive">{STATUS_LABEL[status]}</Badge>
  if (status === 'low_stock') {
    return (
      <Badge className="bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300" variant="secondary">
        {STATUS_LABEL[status]}
      </Badge>
    )
  }
  return <Badge variant="secondary">{STATUS_LABEL[status]}</Badge>
}

export function StockPage() {
  const [page, setPage] = useState(1)
  const [searchInput, setSearchInput] = useState('')
  const search = useDebouncedValue(searchInput, 300)
  const [warehouseId, setWarehouseId] = useState('all')
  const [productId, setProductId] = useState('all')
  const [categoryId, setCategoryId] = useState('all')
  const [lowStockOnly, setLowStockOnly] = useState('all')

  const [adjusting, setAdjusting] = useState<InventoryRow | null>(null)
  const [history, setHistory] = useState<InventoryRow | null>(null)
  const [editingReorderPoint, setEditingReorderPoint] = useState<InventoryRow | null>(null)

  useEffect(() => setPage(1), [search, warehouseId, productId, categoryId, lowStockOnly])

  const { data, isLoading, isError, refetch } = useInventory({
    page,
    search: search || undefined,
    warehouse_id: warehouseId !== 'all' ? Number(warehouseId) : undefined,
    product_id: productId !== 'all' ? Number(productId) : undefined,
    category_id: categoryId !== 'all' ? Number(categoryId) : undefined,
    low_stock: lowStockOnly === 'low' ? true : undefined,
  })
  const { data: warehousesData } = useWarehouses({ page: 1, active_only: true })
  const { data: productsData } = useProducts({ page: 1, active_only: true })
  const { data: categoriesData } = useCategories({ page: 1 })

  const productRowSpans = computeProductRowSpans(data?.data ?? [])

  return (
    <div className="flex flex-col gap-4">
      <PageHeader title="Stock" description="On-hand quantities, reservations, and low-stock alerts." />

      <div className="flex flex-wrap gap-3">
        <Input
          placeholder="Search by product name or SKU…"
          className="w-full sm:w-56"
          value={searchInput}
          onChange={(e) => setSearchInput(e.target.value)}
        />
        <Select value={warehouseId} onValueChange={setWarehouseId}>
          <SelectTrigger className="w-full sm:w-44">
            <SelectValue placeholder="Warehouse" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All warehouses</SelectItem>
            {warehousesData?.data.map((w) => (
              <SelectItem key={w.id} value={String(w.id)}>
                {w.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
        <Select value={productId} onValueChange={setProductId}>
          <SelectTrigger className="w-full sm:w-44">
            <SelectValue placeholder="Product" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All products</SelectItem>
            {productsData?.data.map((p) => (
              <SelectItem key={p.id} value={String(p.id)}>
                {p.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
        <Select value={categoryId} onValueChange={setCategoryId}>
          <SelectTrigger className="w-full sm:w-44">
            <SelectValue placeholder="Category" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All categories</SelectItem>
            {categoriesData?.data.map((c) => (
              <SelectItem key={c.id} value={String(c.id)}>
                {c.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
        <Select value={lowStockOnly} onValueChange={setLowStockOnly}>
          <SelectTrigger className="w-full sm:w-40">
            <SelectValue placeholder="Stock level" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All levels</SelectItem>
            <SelectItem value="low">Low stock only</SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div className="overflow-x-auto rounded-md border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Product</TableHead>
              <TableHead>SKU</TableHead>
              <TableHead>Warehouse</TableHead>
              <TableHead>Quantity</TableHead>
              <TableHead>Reserved</TableHead>
              <TableHead>Available</TableHead>
              <TableHead>Reorder Point</TableHead>
              <TableHead>Status</TableHead>
              <TableHead className="w-10" />
            </TableRow>
          </TableHeader>
          <TableBody>
            {isLoading || isError || data?.data.length === 0 ? (
              <TableStateRow
                colSpan={9}
                isLoading={isLoading}
                isError={isError}
                isEmpty={data?.data.length === 0}
                emptyMessage="No inventory matches your filters."
                onRetry={refetch}
              />
            ) : (
              data?.data.map((row, index) => (
                <TableRow
                  key={row.id}
                  className={cn(row.status === 'out_of_stock' && 'bg-destructive/5')}
                >
                  {productRowSpans[index] > 0 && (
                    <>
                      <TableCell className="font-medium align-top" rowSpan={productRowSpans[index]}>
                        {row.product.name}
                      </TableCell>
                      <TableCell className="align-top" rowSpan={productRowSpans[index]}>
                        {row.product.sku}
                      </TableCell>
                    </>
                  )}
                  <TableCell>{row.warehouse.name}</TableCell>
                  <TableCell>{row.quantity}</TableCell>
                  <TableCell>{row.reserved_quantity}</TableCell>
                  <TableCell>{row.available_quantity}</TableCell>
                  <TableCell>{row.reorder_point}</TableCell>
                  <TableCell>
                    <InventoryStatusBadge status={row.status} />
                  </TableCell>
                  <TableCell>
                    <DropdownMenu>
                      <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="icon" aria-label={`Actions for ${row.product.name}`}>
                          <MoreHorizontal className="size-4" />
                        </Button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent align="end">
                        <DropdownMenuItem onClick={() => setAdjusting(row)}>
                          <PackagePlus className="size-4" />
                          Adjust stock
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={() => setHistory(row)}>
                          <History className="size-4" />
                          View history
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={() => setEditingReorderPoint(row)}>
                          <SlidersHorizontal className="size-4" />
                          Edit reorder point
                        </DropdownMenuItem>
                      </DropdownMenuContent>
                    </DropdownMenu>
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </div>

      {data?.meta && <PaginationBar meta={data.meta} onPageChange={setPage} />}

      <Dialog open={adjusting !== null} onOpenChange={(open) => !open && setAdjusting(null)}>
        {adjusting && <AdjustStockDialog row={adjusting} onSaved={() => setAdjusting(null)} />}
      </Dialog>

      <Dialog open={history !== null} onOpenChange={(open) => !open && setHistory(null)}>
        {history && <StockHistoryDialog row={history} />}
      </Dialog>

      <Dialog open={editingReorderPoint !== null} onOpenChange={(open) => !open && setEditingReorderPoint(null)}>
        {editingReorderPoint && (
          <ReorderPointDialog row={editingReorderPoint} onSaved={() => setEditingReorderPoint(null)} />
        )}
      </Dialog>
    </div>
  )
}

const adjustSchema = z.object({
  direction: z.enum(['increase', 'decrease']),
  quantity: z.coerce.number().int().min(1, 'Must be at least 1'),
  note: z.string().optional(),
})

type AdjustFormValues = z.infer<typeof adjustSchema>

// The backend validates quantity_change (a computed value), not the
// direction/quantity fields the form actually shows — map its errors back
// onto the field the user can edit.
const ADJUST_FIELD_MAP: Partial<Record<string, keyof AdjustFormValues>> = {
  quantity_change: 'quantity',
  note: 'note',
}

function AdjustStockDialog({ row, onSaved }: { row: InventoryRow; onSaved: () => void }) {
  const adjustStock = useAdjustStock(row.product.id)

  const form = useForm({
    resolver: zodResolver(adjustSchema),
    defaultValues: { direction: 'increase' as const, quantity: 1, note: '' },
  })

  async function onSubmit(values: AdjustFormValues) {
    try {
      await adjustStock.mutateAsync({
        warehouse_id: row.warehouse.id,
        quantity_change: values.direction === 'increase' ? values.quantity : -values.quantity,
        type: 'adjustment',
        note: values.note,
      })
      toast.success('Stock adjusted')
      onSaved()
    } catch (error) {
      if (error instanceof ApiError && error.errors) {
        let mappedAny = false
        for (const [field, messages] of Object.entries(error.errors)) {
          const formField = ADJUST_FIELD_MAP[field]
          if (formField) {
            form.setError(formField, { message: messages[0] })
            mappedAny = true
          }
        }
        if (!mappedAny) toast.error(error.message)
      } else {
        toast.error(error instanceof ApiError ? error.message : 'Failed to adjust stock')
      }
    }
  }

  return (
    <DialogContent>
      <DialogHeader>
        <DialogTitle>Adjust stock — {row.product.name}</DialogTitle>
      </DialogHeader>
      <p className="text-muted-foreground -mt-2 text-sm">
        {row.warehouse.name} · currently {row.quantity} on hand
      </p>
      <Form {...form}>
        <form onSubmit={form.handleSubmit(onSubmit)} className="flex flex-col gap-4">
          <div className="grid grid-cols-2 gap-4">
            <FormField
              control={form.control}
              name="direction"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Direction</FormLabel>
                  <Select onValueChange={field.onChange} value={field.value}>
                    <FormControl>
                      <SelectTrigger className="w-full">
                        <SelectValue />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      <SelectItem value="increase">Increase</SelectItem>
                      <SelectItem value="decrease">Decrease</SelectItem>
                    </SelectContent>
                  </Select>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="quantity"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Quantity</FormLabel>
                  <FormControl>
                    <Input type="number" min={1} {...field} value={field.value as number} />
                  </FormControl>
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
                <FormLabel>Reason</FormLabel>
                <FormControl>
                  <Textarea placeholder="Why is this stock changing?" {...field} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
          <DialogFooter>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              Record adjustment
            </Button>
          </DialogFooter>
        </form>
      </Form>
    </DialogContent>
  )
}

function StockHistoryDialog({ row }: { row: InventoryRow }) {
  const { data, isLoading, isError, refetch } = useStockMovements(row.product.id, row.warehouse.id)

  return (
    <DialogContent className="max-w-2xl">
      <DialogHeader>
        <DialogTitle>History — {row.product.name}</DialogTitle>
      </DialogHeader>
      <p className="text-muted-foreground -mt-2 text-sm">{row.warehouse.name}</p>
      <div className="max-h-96 overflow-y-auto rounded-md border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Date</TableHead>
              <TableHead>Type</TableHead>
              <TableHead>Change</TableHead>
              <TableHead>Note</TableHead>
              <TableHead>By</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {isLoading || isError || data?.data.length === 0 ? (
              <TableStateRow
                colSpan={5}
                isLoading={isLoading}
                isError={isError}
                isEmpty={data?.data.length === 0}
                emptyMessage="No movements recorded yet."
                onRetry={refetch}
              />
            ) : (
              data?.data.map((movement) => (
                <TableRow key={movement.id}>
                  <TableCell className="text-xs whitespace-nowrap">
                    {new Date(movement.created_at).toLocaleString()}
                  </TableCell>
                  <TableCell className="capitalize">{movement.type}</TableCell>
                  <TableCell className={movement.quantity_change < 0 ? 'text-destructive' : 'text-emerald-600'}>
                    {movement.quantity_change > 0 ? '+' : ''}
                    {movement.quantity_change}
                  </TableCell>
                  <TableCell className="max-w-48 truncate">{movement.note ?? '—'}</TableCell>
                  <TableCell>{movement.user?.name ?? '—'}</TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </div>
    </DialogContent>
  )
}

const reorderPointSchema = z.object({
  reorder_point: z.coerce.number().int().min(0, 'Must be 0 or more'),
})

type ReorderPointFormValues = z.infer<typeof reorderPointSchema>

function ReorderPointDialog({ row, onSaved }: { row: InventoryRow; onSaved: () => void }) {
  const updateReorderPoint = useUpdateReorderPoint(row.product.id)

  const form = useForm({
    resolver: zodResolver(reorderPointSchema),
    defaultValues: { reorder_point: row.reorder_point },
  })

  async function onSubmit(values: ReorderPointFormValues) {
    try {
      await updateReorderPoint.mutateAsync({
        warehouseId: row.warehouse.id,
        reorderPoint: values.reorder_point,
      })
      toast.success('Reorder point updated')
      onSaved()
    } catch (error) {
      if (error instanceof ApiError && error.errors) {
        for (const [field, messages] of Object.entries(error.errors)) {
          form.setError(field as keyof ReorderPointFormValues, { message: messages[0] })
        }
      } else {
        toast.error(error instanceof ApiError ? error.message : 'Failed to update reorder point')
      }
    }
  }

  return (
    <DialogContent>
      <DialogHeader>
        <DialogTitle>Edit reorder point — {row.product.name}</DialogTitle>
      </DialogHeader>
      <p className="text-muted-foreground -mt-2 text-sm">{row.warehouse.name}</p>
      <Form {...form}>
        <form onSubmit={form.handleSubmit(onSubmit)} className="flex flex-col gap-4">
          <FormField
            control={form.control}
            name="reorder_point"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Reorder point</FormLabel>
                <FormControl>
                  <Input type="number" min={0} {...field} value={field.value as number} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
          <DialogFooter>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              Save
            </Button>
          </DialogFooter>
        </form>
      </Form>
    </DialogContent>
  )
}
