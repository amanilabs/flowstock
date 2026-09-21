import { useEffect, useState } from 'react'
import { zodResolver } from '@hookform/resolvers/zod'
import { MoreHorizontal, Plus } from 'lucide-react'
import { useForm } from 'react-hook-form'
import { toast } from 'sonner'
import { z } from 'zod'
import { useAuth } from '@/contexts/AuthContext'
import { useDebouncedValue } from '@/hooks/useDebouncedValue'
import {
  type WarehouseInput,
  useCreateWarehouse,
  useDeleteWarehouse,
  useUpdateWarehouse,
  useWarehouses,
} from '@/hooks/queries/useWarehouses'
import { ApiError } from '@/lib/api'
import type { Warehouse } from '@/types/api'
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

const warehouseSchema = z.object({
  name: z.string().min(1, 'Required'),
  code: z.string().min(1, 'Required'),
  address_line1: z.string().min(1, 'Required'),
  address_line2: z.string().optional(),
  city: z.string().min(1, 'Required'),
  state: z.string().optional(),
  postal_code: z.string().min(1, 'Required'),
  country: z.string().min(1, 'Required'),
  contact_name: z.string().optional(),
  contact_phone: z.string().optional(),
  contact_email: z.string().email().optional().or(z.literal('')),
  is_active: z.boolean(),
})

type WarehouseFormValues = z.infer<typeof warehouseSchema>

export function WarehousesPage() {
  const { hasRole } = useAuth()
  const canManage = hasRole('Admin')

  const [page, setPage] = useState(1)
  const [searchInput, setSearchInput] = useState('')
  const search = useDebouncedValue(searchInput, 300)
  const [status, setStatus] = useState<'all' | 'active' | 'inactive'>('all')

  const [editing, setEditing] = useState<Warehouse | null>(null)
  const [dialogOpen, setDialogOpen] = useState(false)
  const [deleting, setDeleting] = useState<Warehouse | null>(null)

  useEffect(() => setPage(1), [search, status])

  const { data, isLoading, isError, refetch } = useWarehouses({
    page,
    search: search || undefined,
    active_only: status === 'active' ? true : undefined,
  })
  const deleteWarehouse = useDeleteWarehouse()

  const visibleWarehouses = status === 'inactive' ? data?.data.filter((w) => !w.is_active) : data?.data

  async function confirmDelete() {
    if (!deleting) return
    try {
      await deleteWarehouse.mutateAsync(deleting.id)
      toast.success('Warehouse deleted')
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Failed to delete warehouse')
    } finally {
      setDeleting(null)
    }
  }

  return (
    <div className="flex flex-col gap-4">
      <PageHeader
        title="Warehouses"
        description="Locations that hold and fulfill your inventory."
        action={
          canManage && (
            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
              <DialogTrigger asChild>
                <Button onClick={() => setEditing(null)}>
                  <Plus className="size-4" />
                  New warehouse
                </Button>
              </DialogTrigger>
              <WarehouseFormDialog warehouse={editing} onSaved={() => setDialogOpen(false)} />
            </Dialog>
          )
        }
      />

      <div className="flex flex-wrap gap-3">
        <Input
          placeholder="Search by name, code, or city…"
          className="w-full sm:w-64"
          value={searchInput}
          onChange={(e) => setSearchInput(e.target.value)}
        />
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
              <TableHead>Code</TableHead>
              <TableHead>City</TableHead>
              <TableHead>Country</TableHead>
              <TableHead>Products</TableHead>
              <TableHead>Total Stock</TableHead>
              <TableHead>Status</TableHead>
              {canManage && <TableHead className="w-10" />}
            </TableRow>
          </TableHeader>
          <TableBody>
            {isLoading || isError || visibleWarehouses?.length === 0 ? (
              <TableStateRow
                colSpan={8}
                isLoading={isLoading}
                isError={isError}
                isEmpty={visibleWarehouses?.length === 0}
                emptyMessage="No warehouses match your filters."
                onRetry={refetch}
              />
            ) : (
              visibleWarehouses?.map((warehouse) => (
                <TableRow key={warehouse.id}>
                  <TableCell className="font-medium">{warehouse.name}</TableCell>
                  <TableCell>{warehouse.code}</TableCell>
                  <TableCell>{warehouse.city}</TableCell>
                  <TableCell>{warehouse.country}</TableCell>
                  <TableCell>
                    <Badge variant="secondary">{warehouse.product_count ?? 0}</Badge>
                  </TableCell>
                  <TableCell>{warehouse.total_stock ?? 0}</TableCell>
                  <TableCell>
                    <Badge variant={warehouse.is_active ? 'default' : 'secondary'}>
                      {warehouse.is_active ? 'Active' : 'Inactive'}
                    </Badge>
                  </TableCell>
                  {canManage && (
                    <TableCell>
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="ghost" size="icon" aria-label={`Actions for ${warehouse.name}`}>
                            <MoreHorizontal className="size-4" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                          <DropdownMenuItem
                            onClick={() => {
                              setEditing(warehouse)
                              setDialogOpen(true)
                            }}
                          >
                            Edit
                          </DropdownMenuItem>
                          <DropdownMenuItem variant="destructive" onClick={() => setDeleting(warehouse)}>
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
                ? `This warehouse holds stock for ${deleting.product_count} product(s). This cannot be undone.`
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

function WarehouseFormDialog({
  warehouse,
  onSaved,
}: {
  warehouse: Warehouse | null
  onSaved: () => void
}) {
  const createWarehouse = useCreateWarehouse()
  const updateWarehouse = useUpdateWarehouse()

  const form = useForm({
    resolver: zodResolver(warehouseSchema),
    values: {
      name: warehouse?.name ?? '',
      code: warehouse?.code ?? '',
      address_line1: warehouse?.address_line1 ?? '',
      address_line2: warehouse?.address_line2 ?? '',
      city: warehouse?.city ?? '',
      state: warehouse?.state ?? '',
      postal_code: warehouse?.postal_code ?? '',
      country: warehouse?.country ?? '',
      contact_name: warehouse?.contact_name ?? '',
      contact_phone: warehouse?.contact_phone ?? '',
      contact_email: warehouse?.contact_email ?? '',
      is_active: warehouse?.is_active ?? true,
    },
  })

  async function onSubmit(values: WarehouseInput) {
    try {
      if (warehouse) {
        await updateWarehouse.mutateAsync({ id: warehouse.id, ...values })
        toast.success('Warehouse updated')
      } else {
        await createWarehouse.mutateAsync(values)
        toast.success('Warehouse created')
      }
      onSaved()
    } catch (error) {
      if (error instanceof ApiError && error.errors) {
        for (const [field, messages] of Object.entries(error.errors)) {
          form.setError(field as keyof WarehouseFormValues, { message: messages[0] })
        }
      } else {
        toast.error(error instanceof ApiError ? error.message : 'Failed to save warehouse')
      }
    }
  }

  return (
    <DialogContent className="sm:max-w-2xl">
      <DialogHeader>
        <DialogTitle>{warehouse ? 'Edit warehouse' : 'New warehouse'}</DialogTitle>
      </DialogHeader>
      <Form {...form}>
        <form onSubmit={form.handleSubmit(onSubmit)} className="flex flex-col gap-4">
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
            <FormField
              control={form.control}
              name="code"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Code</FormLabel>
                  <FormControl>
                    <Input {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
          </div>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <FormField
              control={form.control}
              name="address_line1"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Address</FormLabel>
                  <FormControl>
                    <Input {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="address_line2"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Address line 2</FormLabel>
                  <FormControl>
                    <Input {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
          </div>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-4">
            <FormField
              control={form.control}
              name="city"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>City</FormLabel>
                  <FormControl>
                    <Input {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="state"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>State</FormLabel>
                  <FormControl>
                    <Input {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="postal_code"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Postal code</FormLabel>
                  <FormControl>
                    <Input {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="country"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Country</FormLabel>
                  <FormControl>
                    <Input {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
          </div>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <FormField
              control={form.control}
              name="contact_name"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Contact name</FormLabel>
                  <FormControl>
                    <Input {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="contact_phone"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Contact phone</FormLabel>
                  <FormControl>
                    <Input {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="contact_email"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Contact email</FormLabel>
                  <FormControl>
                    <Input type="email" {...field} />
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
              {warehouse ? 'Save changes' : 'Create warehouse'}
            </Button>
          </DialogFooter>
        </form>
      </Form>
    </DialogContent>
  )
}
