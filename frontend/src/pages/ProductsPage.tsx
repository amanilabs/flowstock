import { useState } from 'react'
import { zodResolver } from '@hookform/resolvers/zod'
import { MoreHorizontal, Plus } from 'lucide-react'
import { useForm } from 'react-hook-form'
import { toast } from 'sonner'
import { z } from 'zod'
import { useCategories } from '@/hooks/queries/useCategories'
import {
  type ProductInput,
  useCreateProduct,
  useDeleteProduct,
  useProducts,
  useUpdateProduct,
} from '@/hooks/queries/useProducts'
import { ApiError } from '@/lib/api'
import type { Product, ProductCategory } from '@/types/api'
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
import { PaginationBar } from '@/components/PaginationBar'

const productSchema = z.object({
  name: z.string().min(1, 'Required'),
  sku: z.string().min(1, 'Required'),
  category_id: z.string(),
  unit_of_measure: z.string().min(1, 'Required'),
  cost_price: z.coerce.number().min(0),
  selling_price: z.coerce.number().min(0),
  reorder_point: z.coerce.number().min(0),
  description: z.string().optional(),
})

type ProductFormValues = z.infer<typeof productSchema>

export function ProductsPage() {
  const [page, setPage] = useState(1)
  const [editing, setEditing] = useState<Product | null>(null)
  const [dialogOpen, setDialogOpen] = useState(false)

  const { data, isLoading } = useProducts({ page })
  const { data: categoriesData } = useCategories({ page: 1 })
  const deleteProduct = useDeleteProduct()

  async function handleDelete(product: Product) {
    if (!confirm(`Delete "${product.name}"? This cannot be undone.`)) return
    try {
      await deleteProduct.mutateAsync(product.id)
      toast.success('Product deleted')
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Failed to delete product')
    }
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold">Products</h1>
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
      </div>

      <div className="rounded-md border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Name</TableHead>
              <TableHead>SKU</TableHead>
              <TableHead>Category</TableHead>
              <TableHead>Cost</TableHead>
              <TableHead>Price</TableHead>
              <TableHead>Margin</TableHead>
              <TableHead>Status</TableHead>
              <TableHead className="w-10" />
            </TableRow>
          </TableHeader>
          <TableBody>
            {isLoading ? (
              <TableRow>
                <TableCell colSpan={8} className="text-muted-foreground text-center">
                  Loading…
                </TableCell>
              </TableRow>
            ) : data?.data.length === 0 ? (
              <TableRow>
                <TableCell colSpan={8} className="text-muted-foreground text-center">
                  No products yet.
                </TableCell>
              </TableRow>
            ) : (
              data?.data.map((product) => (
                <TableRow key={product.id}>
                  <TableCell className="font-medium">{product.name}</TableCell>
                  <TableCell>{product.sku}</TableCell>
                  <TableCell>{product.category?.name ?? '—'}</TableCell>
                  <TableCell>${product.cost_price}</TableCell>
                  <TableCell>${product.selling_price}</TableCell>
                  <TableCell>{product.margin_percentage}%</TableCell>
                  <TableCell>
                    <Badge variant={product.is_active ? 'default' : 'secondary'}>
                      {product.is_active ? 'Active' : 'Inactive'}
                    </Badge>
                  </TableCell>
                  <TableCell>
                    <DropdownMenu>
                      <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="icon">
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
                        <DropdownMenuItem variant="destructive" onClick={() => handleDelete(product)}>
                          Delete
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
      category_id: product?.category ? String(product.category.id) : '',
      unit_of_measure: product?.unit_of_measure ?? 'pcs',
      cost_price: product ? Number(product.cost_price) : 0,
      selling_price: product ? Number(product.selling_price) : 0,
      reorder_point: product?.reorder_point ?? 0,
      description: product?.description ?? '',
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
      toast.error(error instanceof ApiError ? error.message : 'Failed to save product')
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
          <div className="grid grid-cols-2 gap-4">
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
          <div className="grid grid-cols-3 gap-4">
            <FormField
              control={form.control}
              name="cost_price"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Cost price</FormLabel>
                  <FormControl>
                    <Input type="number" step="0.01" {...field} value={field.value as number} />
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
                    <Input type="number" step="0.01" {...field} value={field.value as number} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="reorder_point"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Reorder point</FormLabel>
                  <FormControl>
                    <Input type="number" {...field} value={field.value as number} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
          </div>
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
