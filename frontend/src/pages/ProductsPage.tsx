import { useEffect, useState } from 'react'
import { zodResolver } from '@hookform/resolvers/zod'
import { MoreHorizontal, Plus } from 'lucide-react'
import { useForm } from 'react-hook-form'
import { toast } from 'sonner'
import { z } from 'zod'
import { useAuth } from '@/contexts/AuthContext'
import { useCategories } from '@/hooks/queries/useCategories'
import { useDebouncedValue } from '@/hooks/useDebouncedValue'
import {
  type ProductInput,
  useCreateProduct,
  useDeleteProduct,
  useProducts,
  useUpdateProduct,
} from '@/hooks/queries/useProducts'
import { ApiError } from '@/lib/api'
import { formatCurrency, isValidCurrencyInput } from '@/lib/utils'
import type { Product, ProductCategory } from '@/types/api'
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
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
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
import { PageHeader } from '@/components/PageHeader'
import { PaginationBar } from '@/components/PaginationBar'
import { TableStateRow } from '@/components/TableStateRow'

const productSchema = z.object({
  name: z.string().min(1, 'Required'),
  sku: z.string().min(1, 'Required'),
  barcode: z.string().optional(),
  category_id: z.string(),
  unit_of_measure: z.string().min(1, 'Required'),
  cost_price: z.coerce.number().min(0),
  selling_price: z.coerce.number().min(0),
  description: z.string().optional(),
  is_active: z.boolean(),
})

type ProductFormValues = z.infer<typeof productSchema>

export function ProductsPage() {
  const { hasRole } = useAuth()
  const canManage = hasRole('Admin', 'Manager')

  const [page, setPage] = useState(1)
  const [searchInput, setSearchInput] = useState('')
  const search = useDebouncedValue(searchInput, 300)
  const [categoryId, setCategoryId] = useState<string>('all')
  const [status, setStatus] = useState<'all' | 'active' | 'inactive'>('all')

  const [editing, setEditing] = useState<Product | null>(null)
  const [dialogOpen, setDialogOpen] = useState(false)
  const [deleting, setDeleting] = useState<Product | null>(null)

  useEffect(() => setPage(1), [search, categoryId, status])

  const { data, isLoading, isError, refetch } = useProducts({
    page,
    search: search || undefined,
    category_id: categoryId !== 'all' ? Number(categoryId) : undefined,
    active_only: status === 'active' ? true : undefined,
  })
  const { data: categoriesData } = useCategories({ page: 1 })
  const deleteProduct = useDeleteProduct()

  const visibleProducts = status === 'inactive' ? data?.data.filter((p) => !p.is_active) : data?.data

  async function confirmDelete() {
    if (!deleting) return
    try {
      await deleteProduct.mutateAsync(deleting.id)
      toast.success('Product deleted')
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Failed to delete product')
    } finally {
      setDeleting(null)
    }
  }

  return (
    <div className="flex flex-col gap-4">
      <PageHeader
        title="Products"
        description="Manage your catalog, pricing, and reorder points."
        action={
          canManage && (
            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
              <DialogTrigger asChild>
                <Button onClick={() => setEditing(null)}>
                  <Plus className="size-4" />
                  New product
                </Button>
              </DialogTrigger>
              <ProductFormDialog
                product={editing}
                categories={categoriesData?.data ?? []}
                onSaved={() => setDialogOpen(false)}
              />
            </Dialog>
          )
        }
      />

      <div className="flex flex-wrap gap-3">
        <Input
          placeholder="Search by name or SKU…"
          className="w-full sm:w-64"
          value={searchInput}
          onChange={(e) => setSearchInput(e.target.value)}
        />
        <Select value={categoryId} onValueChange={setCategoryId}>
          <SelectTrigger className="w-full sm:w-48">
            <SelectValue placeholder="Category" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All categories</SelectItem>
            {categoriesData?.data.map((category) => (
              <SelectItem key={category.id} value={String(category.id)}>
                {category.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
        <Select value={status} onValueChange={(v) => setStatus(v as typeof status)}>
          <SelectTrigger className="w-full sm:w-40">
            <SelectValue placeholder="Status" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All statuses</SelectItem>
            <SelectItem value="active">Active</SelectItem>
            <SelectItem value="inactive">Inactive</SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div className="overflow-x-auto rounded-md border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Name</TableHead>
              <TableHead>SKU</TableHead>
              <TableHead>Category</TableHead>
              <TableHead>Selling Price</TableHead>
              <TableHead>Stock</TableHead>
              <TableHead>Status</TableHead>
              {canManage && <TableHead className="w-10" />}
            </TableRow>
          </TableHeader>
          <TableBody>
            {isLoading || isError || visibleProducts?.length === 0 ? (
              <TableStateRow
                colSpan={7}
                isLoading={isLoading}
                isError={isError}
                isEmpty={visibleProducts?.length === 0}
                emptyMessage="No products match your filters."
                onRetry={refetch}
              />
            ) : (
              visibleProducts?.map((product) => (
                <TableRow key={product.id}>
                  <TableCell className="font-medium">{product.name}</TableCell>
                  <TableCell>{product.sku}</TableCell>
                  <TableCell>{product.category?.name ?? '—'}</TableCell>
                  <TableCell>${formatCurrency(product.selling_price)}</TableCell>
                  <TableCell>
                    {product.total_stock === null ? (
                      '—'
                    ) : (
                      <Badge variant="secondary">{product.total_stock}</Badge>
                    )}
                  </TableCell>
                  <TableCell>
                    <Badge variant={product.is_active ? 'default' : 'secondary'}>
                      {product.is_active ? 'Active' : 'Inactive'}
                    </Badge>
                  </TableCell>
                  {canManage && (
                    <TableCell>
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="ghost" size="icon" aria-label={`Actions for ${product.name}`}>
                            <MoreHorizontal className="size-4" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                          <DropdownMenuItem
                            onClick={() => {
                              setEditing(product)
                              setDialogOpen(true)
                            }}
                          >
                            Edit
                          </DropdownMenuItem>
                          <DropdownMenuItem variant="destructive" onClick={() => setDeleting(product)}>
                            Delete
                          </DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </TableCell>
                  )}
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </div>

      {data?.meta && <PaginationBar meta={data.meta} onPageChange={setPage} />}

      <AlertDialog open={deleting !== null} onOpenChange={(open) => !open && setDeleting(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete "{deleting?.name}"?</AlertDialogTitle>
            <AlertDialogDescription>This cannot be undone.</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction onClick={confirmDelete}>Delete</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}

function ProductFormDialog({
  product,
  categories,
  onSaved,
}: {
  product: Product | null
  categories: ProductCategory[]
  onSaved: () => void
}) {
  const createProduct = useCreateProduct()
  const updateProduct = useUpdateProduct()

  const form = useForm({
    resolver: zodResolver(productSchema),
    values: {
      name: product?.name ?? '',
      sku: product?.sku ?? '',
      barcode: product?.barcode ?? '',
      category_id: product?.category ? String(product.category.id) : '',
      unit_of_measure: product?.unit_of_measure ?? 'pcs',
      cost_price: product ? Math.round(Number(product.cost_price) * 100) / 100 : 0,
      selling_price: product ? Math.round(Number(product.selling_price) * 100) / 100 : 0,
      description: product?.description ?? '',
      is_active: product?.is_active ?? true,
    },
  })

  async function onSubmit(values: ProductFormValues) {
    const input: ProductInput = {
      ...values,
      category_id: values.category_id ? Number(values.category_id) : null,
    }
    try {
      if (product) {
        await updateProduct.mutateAsync({ id: product.id, ...input })
        toast.success('Product updated')
      } else {
        await createProduct.mutateAsync(input)
        toast.success('Product created')
      }
      onSaved()
    } catch (error) {
      if (error instanceof ApiError && error.errors) {
        for (const [field, messages] of Object.entries(error.errors)) {
          form.setError(field as keyof ProductFormValues, { message: messages[0] })
        }
      } else {
        toast.error(error instanceof ApiError ? error.message : 'Failed to save product')
      }
    }
  }

  return (
    <DialogContent>
      <DialogHeader>
        <DialogTitle>{product ? 'Edit product' : 'New product'}</DialogTitle>
      </DialogHeader>
      <Form {...form}>
        <form onSubmit={form.handleSubmit(onSubmit)} className="flex flex-col gap-4">
          <FormField
            control={form.control}
            name="name"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Name</FormLabel>
                <FormControl>
                  <Input {...field} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <FormField
              control={form.control}
              name="sku"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>SKU</FormLabel>
                  <FormControl>
                    <Input {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="unit_of_measure"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Unit</FormLabel>
                  <FormControl>
                    <Input {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
          </div>
          <FormField
            control={form.control}
            name="barcode"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Barcode</FormLabel>
                <FormControl>
                  <Input {...field} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
          <FormField
            control={form.control}
            name="category_id"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Category</FormLabel>
                <Select onValueChange={field.onChange} value={field.value}>
                  <FormControl>
                    <SelectTrigger className="w-full">
                      <SelectValue placeholder="No category" />
                    </SelectTrigger>
                  </FormControl>
                  <SelectContent>
                    {categories.map((category) => (
                      <SelectItem key={category.id} value={String(category.id)}>
                        {category.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <FormMessage />
              </FormItem>
            )}
          />
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <FormField
              control={form.control}
              name="cost_price"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Cost price</FormLabel>
                  <FormControl>
                    <Input
                      type="number"
                      step="0.01"
                      {...field}
                      value={field.value as number}
                      onChange={(e) => {
                        if (isValidCurrencyInput(e.target.value)) field.onChange(e)
                      }}
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="selling_price"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Selling price</FormLabel>
                  <FormControl>
                    <Input
                      type="number"
                      step="0.01"
                      {...field}
                      value={field.value as number}
                      onChange={(e) => {
                        if (isValidCurrencyInput(e.target.value)) field.onChange(e)
                      }}
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
          </div>
          <FormField
            control={form.control}
            name="is_active"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Status</FormLabel>
                <Select
                  onValueChange={(v) => field.onChange(v === 'true')}
                  value={field.value ? 'true' : 'false'}
                >
                  <FormControl>
                    <SelectTrigger className="w-full sm:w-40">
                      <SelectValue />
                    </SelectTrigger>
                  </FormControl>
                  <SelectContent>
                    <SelectItem value="true">Active</SelectItem>
                    <SelectItem value="false">Inactive</SelectItem>
                  </SelectContent>
                </Select>
                <FormMessage />
              </FormItem>
            )}
          />
          <DialogFooter>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {product ? 'Save changes' : 'Create product'}
            </Button>
          </DialogFooter>
        </form>
      </Form>
    </DialogContent>
  )
}
