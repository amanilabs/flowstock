import { useEffect, useState } from 'react'
import { zodResolver } from '@hookform/resolvers/zod'
import { MoreHorizontal, Plus } from 'lucide-react'
import { useForm } from 'react-hook-form'
import { toast } from 'sonner'
import { z } from 'zod'
import { useAuth } from '@/contexts/AuthContext'
import {
  type CategoryInput,
  useCategories,
  useCreateCategory,
  useDeleteCategory,
  useUpdateCategory,
} from '@/hooks/queries/useCategories'
import { useDebouncedValue } from '@/hooks/useDebouncedValue'
import { ApiError } from '@/lib/api'
import type { ProductCategory } from '@/types/api'
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

const categorySchema = z.object({
  name: z.string().min(1, 'Required'),
  slug: z.string().min(1, 'Required'),
  parent_id: z.string().optional(),
})

type CategoryFormValues = z.infer<typeof categorySchema>

export function CategoriesPage() {
  const { hasRole } = useAuth()
  const canManage = hasRole('Admin', 'Manager')

  const [page, setPage] = useState(1)
  const [searchInput, setSearchInput] = useState('')
  const search = useDebouncedValue(searchInput, 300)

  const [editing, setEditing] = useState<ProductCategory | null>(null)
  const [dialogOpen, setDialogOpen] = useState(false)
  const [deleting, setDeleting] = useState<ProductCategory | null>(null)

  useEffect(() => setPage(1), [search])

  const { data, isLoading, isError, refetch } = useCategories({ page, search: search || undefined })
  const { data: allCategoriesData } = useCategories({ page: 1 })
  const deleteCategory = useDeleteCategory()

  const categoryById = new Map((allCategoriesData?.data ?? []).map((c) => [c.id, c]))

  async function confirmDelete() {
    if (!deleting) return
    try {
      await deleteCategory.mutateAsync(deleting.id)
      toast.success('Category deleted')
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Failed to delete category')
    } finally {
      setDeleting(null)
    }
  }

  return (
    <div className="flex flex-col gap-4">
      <PageHeader
        title="Categories"
        description="Organize products into browsable groups."
        action={
          canManage && (
            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
              <DialogTrigger asChild>
                <Button onClick={() => setEditing(null)}>
                  <Plus className="size-4" />
                  New category
                </Button>
              </DialogTrigger>
              <CategoryFormDialog
                category={editing}
                categories={allCategoriesData?.data ?? []}
                onSaved={() => setDialogOpen(false)}
              />
            </Dialog>
          )
        }
      />

      <Input
        placeholder="Search by name…"
        className="w-full sm:w-64"
        value={searchInput}
        onChange={(e) => setSearchInput(e.target.value)}
      />

      <div className="overflow-x-auto rounded-md border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Name</TableHead>
              <TableHead>Slug</TableHead>
              <TableHead>Parent</TableHead>
              <TableHead>Products</TableHead>
              {canManage && <TableHead className="w-10" />}
            </TableRow>
          </TableHeader>
          <TableBody>
            {isLoading || isError || data?.data.length === 0 ? (
              <TableStateRow
                colSpan={5}
                isLoading={isLoading}
                isError={isError}
                isEmpty={data?.data.length === 0}
                emptyMessage="No categories match your search."
                onRetry={refetch}
              />
            ) : (
              data?.data.map((category) => (
                <TableRow key={category.id}>
                  <TableCell className="font-medium">{category.name}</TableCell>
                  <TableCell>{category.slug}</TableCell>
                  <TableCell>
                    {category.parent_id ? (categoryById.get(category.parent_id)?.name ?? '—') : '—'}
                  </TableCell>
                  <TableCell>
                    <Badge variant="secondary">{category.product_count ?? 0}</Badge>
                  </TableCell>
                  {canManage && (
                    <TableCell>
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="ghost" size="icon" aria-label={`Actions for ${category.name}`}>
                            <MoreHorizontal className="size-4" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                          <DropdownMenuItem
                            onClick={() => {
                              setEditing(category)
                              setDialogOpen(true)
                            }}
                          >
                            Edit
                          </DropdownMenuItem>
                          <DropdownMenuItem variant="destructive" onClick={() => setDeleting(category)}>
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
            <AlertDialogDescription>
              {deleting?.product_count
                ? `This category has ${deleting.product_count} product(s). This cannot be undone.`
                : 'This cannot be undone.'}
            </AlertDialogDescription>
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

function CategoryFormDialog({
  category,
  categories,
  onSaved,
}: {
  category: ProductCategory | null
  categories: ProductCategory[]
  onSaved: () => void
}) {
  const createCategory = useCreateCategory()
  const updateCategory = useUpdateCategory()

  const form = useForm({
    resolver: zodResolver(categorySchema),
    values: {
      name: category?.name ?? '',
      slug: category?.slug ?? '',
      parent_id: category?.parent_id ? String(category.parent_id) : '',
    },
  })

  // A category can't be its own parent.
  const parentOptions = categories.filter((c) => c.id !== category?.id)

  async function onSubmit(values: CategoryFormValues) {
    const input: CategoryInput = {
      ...values,
      parent_id: values.parent_id ? Number(values.parent_id) : null,
    }
    try {
      if (category) {
        await updateCategory.mutateAsync({ id: category.id, ...input })
        toast.success('Category updated')
      } else {
        await createCategory.mutateAsync(input)
        toast.success('Category created')
      }
      onSaved()
    } catch (error) {
      if (error instanceof ApiError && error.errors) {
        for (const [field, messages] of Object.entries(error.errors)) {
          form.setError(field as keyof CategoryFormValues, { message: messages[0] })
        }
      } else {
        toast.error(error instanceof ApiError ? error.message : 'Failed to save category')
      }
    }
  }

  return (
    <DialogContent>
      <DialogHeader>
        <DialogTitle>{category ? 'Edit category' : 'New category'}</DialogTitle>
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
                  <Input
                    {...field}
                    onChange={(e) => {
                      field.onChange(e)
                      if (!category) {
                        form.setValue(
                          'slug',
                          e.target.value
                            .toLowerCase()
                            .trim()
                            .replace(/[^a-z0-9]+/g, '-')
                            .replace(/(^-|-$)/g, ''),
                        )
                      }
                    }}
                  />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
          <FormField
            control={form.control}
            name="slug"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Slug</FormLabel>
                <FormControl>
                  <Input {...field} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
          <FormField
            control={form.control}
            name="parent_id"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Parent category</FormLabel>
                <Select
                  onValueChange={(v) => field.onChange(v === 'none' ? '' : v)}
                  value={field.value || 'none'}
                >
                  <FormControl>
                    <SelectTrigger className="w-full">
                      <SelectValue />
                    </SelectTrigger>
                  </FormControl>
                  <SelectContent>
                    <SelectItem value="none">No parent</SelectItem>
                    {parentOptions.map((c) => (
                      <SelectItem key={c.id} value={String(c.id)}>
                        {c.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <FormMessage />
              </FormItem>
            )}
          />
          <DialogFooter>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {category ? 'Save changes' : 'Create category'}
            </Button>
          </DialogFooter>
        </form>
      </Form>
    </DialogContent>
  )
}
