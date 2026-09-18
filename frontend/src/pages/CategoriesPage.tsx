import { useState } from 'react'
import { zodResolver } from '@hookform/resolvers/zod'
import { MoreHorizontal, Plus } from 'lucide-react'
import { useForm } from 'react-hook-form'
import { toast } from 'sonner'
import { z } from 'zod'
import {
  type CategoryInput,
  useCategories,
  useCreateCategory,
  useDeleteCategory,
  useUpdateCategory,
} from '@/hooks/queries/useCategories'
import { ApiError } from '@/lib/api'
import type { ProductCategory } from '@/types/api'
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
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { PageHeader } from '@/components/PageHeader'
import { PaginationBar } from '@/components/PaginationBar'

const categorySchema = z.object({
  name: z.string().min(1, 'Required'),
  slug: z.string().min(1, 'Required'),
})

type CategoryFormValues = z.infer<typeof categorySchema>

export function CategoriesPage() {
  const [page, setPage] = useState(1)
  const [editing, setEditing] = useState<ProductCategory | null>(null)
  const [dialogOpen, setDialogOpen] = useState(false)

  const { data, isLoading } = useCategories({ page })
  const deleteCategory = useDeleteCategory()

  async function handleDelete(category: ProductCategory) {
    if (!confirm(`Delete "${category.name}"? This cannot be undone.`)) return
    try {
      await deleteCategory.mutateAsync(category.id)
      toast.success('Category deleted')
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Failed to delete category')
    }
  }

  return (
    <div className="flex flex-col gap-4">
      <PageHeader
        title="Categories"
        description="Organize products into browsable groups."
        action={
          <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
            <DialogTrigger asChild>
              <Button onClick={() => setEditing(null)}>
                <Plus className="size-4" />
                New category
              </Button>
            </DialogTrigger>
            <CategoryFormDialog category={editing} onSaved={() => setDialogOpen(false)} />
          </Dialog>
        }
      />

      <div className="rounded-md border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Name</TableHead>
              <TableHead>Slug</TableHead>
              <TableHead className="w-10" />
            </TableRow>
          </TableHeader>
          <TableBody>
            {isLoading ? (
              <TableRow>
                <TableCell colSpan={3} className="text-muted-foreground text-center">
                  Loading…
                </TableCell>
              </TableRow>
            ) : data?.data.length === 0 ? (
              <TableRow>
                <TableCell colSpan={3} className="text-muted-foreground text-center">
                  No categories yet.
                </TableCell>
              </TableRow>
            ) : (
              data?.data.map((category) => (
                <TableRow key={category.id}>
                  <TableCell className="font-medium">{category.name}</TableCell>
                  <TableCell>{category.slug}</TableCell>
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
                            setEditing(category)
                            setDialogOpen(true)
                          }}
                        >
                          Edit
                        </DropdownMenuItem>
                        <DropdownMenuItem variant="destructive" onClick={() => handleDelete(category)}>
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

function CategoryFormDialog({
  category,
  onSaved,
}: {
  category: ProductCategory | null
  onSaved: () => void
}) {
  const createCategory = useCreateCategory()
  const updateCategory = useUpdateCategory()

  const form = useForm<CategoryFormValues>({
    resolver: zodResolver(categorySchema),
    values: { name: category?.name ?? '', slug: category?.slug ?? '' },
  })

  async function onSubmit(values: CategoryInput) {
    try {
      if (category) {
        await updateCategory.mutateAsync({ id: category.id, ...values })
        toast.success('Category updated')
      } else {
        await createCategory.mutateAsync(values)
        toast.success('Category created')
      }
      onSaved()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Failed to save category')
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
