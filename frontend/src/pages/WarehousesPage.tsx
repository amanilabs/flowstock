import { useState } from 'react'
import { zodResolver } from '@hookform/resolvers/zod'
import { MoreHorizontal, Plus } from 'lucide-react'
import { useForm } from 'react-hook-form'
import { toast } from 'sonner'
import { z } from 'zod'
import {
  type WarehouseInput,
  useCreateWarehouse,
  useDeleteWarehouse,
  useUpdateWarehouse,
  useWarehouses,
} from '@/hooks/queries/useWarehouses'
import { ApiError } from '@/lib/api'
import type { Warehouse } from '@/types/api'
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
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { PaginationBar } from '@/components/PaginationBar'

const warehouseSchema = z.object({
  name: z.string().min(1, 'Required'),
  code: z.string().min(1, 'Required'),
  address_line1: z.string().min(1, 'Required'),
  city: z.string().min(1, 'Required'),
  postal_code: z.string().min(1, 'Required'),
  country: z.string().min(1, 'Required'),
})

type WarehouseFormValues = z.infer<typeof warehouseSchema>

export function WarehousesPage() {
  const [page, setPage] = useState(1)
  const [editing, setEditing] = useState<Warehouse | null>(null)
  const [dialogOpen, setDialogOpen] = useState(false)

  const { data, isLoading } = useWarehouses({ page })
  const deleteWarehouse = useDeleteWarehouse()

  async function handleDelete(warehouse: Warehouse) {
    if (!confirm(`Delete "${warehouse.name}"? This cannot be undone.`)) return
    try {
      await deleteWarehouse.mutateAsync(warehouse.id)
      toast.success('Warehouse deleted')
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Failed to delete warehouse')
    }
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold">Warehouses</h1>
        <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
          <DialogTrigger asChild>
            <Button onClick={() => setEditing(null)}>
              <Plus className="size-4" />
              New warehouse
            </Button>
          </DialogTrigger>
          <WarehouseFormDialog warehouse={editing} onSaved={() => setDialogOpen(false)} />
        </Dialog>
      </div>

      <div className="rounded-md border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Name</TableHead>
              <TableHead>Code</TableHead>
              <TableHead>City</TableHead>
              <TableHead>Country</TableHead>
              <TableHead>Status</TableHead>
              <TableHead className="w-10" />
            </TableRow>
          </TableHeader>
          <TableBody>
            {isLoading ? (
              <TableRow>
                <TableCell colSpan={6} className="text-muted-foreground text-center">
                  Loading…
                </TableCell>
              </TableRow>
            ) : data?.data.length === 0 ? (
              <TableRow>
                <TableCell colSpan={6} className="text-muted-foreground text-center">
                  No warehouses yet.
                </TableCell>
              </TableRow>
            ) : (
              data?.data.map((warehouse) => (
                <TableRow key={warehouse.id}>
                  <TableCell className="font-medium">{warehouse.name}</TableCell>
                  <TableCell>{warehouse.code}</TableCell>
                  <TableCell>{warehouse.city}</TableCell>
                  <TableCell>{warehouse.country}</TableCell>
                  <TableCell>
                    <Badge variant={warehouse.is_active ? 'default' : 'secondary'}>
                      {warehouse.is_active ? 'Active' : 'Inactive'}
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
                            setEditing(warehouse)
                            setDialogOpen(true)
                          }}
                        >
                          Edit
                        </DropdownMenuItem>
                        <DropdownMenuItem variant="destructive" onClick={() => handleDelete(warehouse)}>
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

function WarehouseFormDialog({
  warehouse,
  onSaved,
}: {
  warehouse: Warehouse | null
  onSaved: () => void
}) {
  const createWarehouse = useCreateWarehouse()
  const updateWarehouse = useUpdateWarehouse()

  const form = useForm<WarehouseFormValues>({
    resolver: zodResolver(warehouseSchema),
    values: {
      name: warehouse?.name ?? '',
      code: warehouse?.code ?? '',
      address_line1: warehouse?.address_line1 ?? '',
      city: warehouse?.city ?? '',
      postal_code: warehouse?.postal_code ?? '',
      country: warehouse?.country ?? '',
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
      toast.error(error instanceof ApiError ? error.message : 'Failed to save warehouse')
    }
  }

  return (
    <DialogContent>
      <DialogHeader>
        <DialogTitle>{warehouse ? 'Edit warehouse' : 'New warehouse'}</DialogTitle>
      </DialogHeader>
      <Form {...form}>
        <form onSubmit={form.handleSubmit(onSubmit)} className="flex flex-col gap-4">
          <div className="grid grid-cols-2 gap-4">
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
          <div className="grid grid-cols-3 gap-4">
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
