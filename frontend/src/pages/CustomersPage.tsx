import { useEffect, useState } from 'react'
import { zodResolver } from '@hookform/resolvers/zod'
import { Eye, MoreHorizontal, Plus } from 'lucide-react'
import { type Control, useForm } from 'react-hook-form'
import { toast } from 'sonner'
import { z } from 'zod'
import { useAuth } from '@/contexts/AuthContext'
import { useDebouncedValue } from '@/hooks/useDebouncedValue'
import {
  type CustomerInput,
  useCreateCustomer,
  useCustomers,
  useDeleteCustomer,
  useUpdateCustomer,
} from '@/hooks/queries/useCustomers'
import { useOrders } from '@/hooks/queries/useOrders'
import { ApiError } from '@/lib/api'
import { formatCurrency } from '@/lib/utils'
import type { Customer } from '@/types/api'
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
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Textarea } from '@/components/ui/textarea'
import { PageHeader } from '@/components/PageHeader'
import { PaginationBar } from '@/components/PaginationBar'
import { OrderStatusBadge } from '@/components/StatusBadge'
import { TableStateRow } from '@/components/TableStateRow'

const addressFields = {
  address_line1: z.string().optional(),
  address_line2: z.string().optional(),
  city: z.string().optional(),
  state: z.string().optional(),
  postal_code: z.string().optional(),
  country: z.string().optional(),
}

const customerSchema = z.object({
  name: z.string().min(1, 'Required'),
  company_name: z.string().optional(),
  email: z.string().email().optional().or(z.literal('')),
  phone: z.string().optional(),
  billing_address_line1: addressFields.address_line1,
  billing_address_line2: addressFields.address_line2,
  billing_city: addressFields.city,
  billing_state: addressFields.state,
  billing_postal_code: addressFields.postal_code,
  billing_country: addressFields.country,
  shipping_address_line1: addressFields.address_line1,
  shipping_address_line2: addressFields.address_line2,
  shipping_city: addressFields.city,
  shipping_state: addressFields.state,
  shipping_postal_code: addressFields.postal_code,
  shipping_country: addressFields.country,
  notes: z.string().optional(),
})

type CustomerFormValues = z.infer<typeof customerSchema>

export function CustomersPage() {
  const { hasRole } = useAuth()
  const canManage = hasRole('Admin', 'Manager')

  const [page, setPage] = useState(1)
  const [searchInput, setSearchInput] = useState('')
  const search = useDebouncedValue(searchInput, 300)

  const [editing, setEditing] = useState<Customer | null>(null)
  const [dialogOpen, setDialogOpen] = useState(false)
  const [deleting, setDeleting] = useState<Customer | null>(null)
  const [viewing, setViewing] = useState<Customer | null>(null)

  useEffect(() => setPage(1), [search])

  const { data, isLoading, isError, refetch } = useCustomers({ page, search: search || undefined })
  const deleteCustomer = useDeleteCustomer()

  async function confirmDelete() {
    if (!deleting) return
    try {
      await deleteCustomer.mutateAsync(deleting.id)
      toast.success('Customer deleted')
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Failed to delete customer')
    } finally {
      setDeleting(null)
    }
  }

  return (
    <div className="flex flex-col gap-4">
      <PageHeader
        title="Customers"
        description="People and companies you sell to."
        action={
          canManage && (
            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
              <DialogTrigger asChild>
                <Button onClick={() => setEditing(null)}>
                  <Plus className="size-4" />
                  New customer
                </Button>
              </DialogTrigger>
              <CustomerFormDialog customer={editing} onSaved={() => setDialogOpen(false)} />
            </Dialog>
          )
        }
      />

      <Input
        placeholder="Search by name or email…"
        className="w-full sm:w-64"
        value={searchInput}
        onChange={(e) => setSearchInput(e.target.value)}
      />

      <div className="overflow-x-auto rounded-md border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Name</TableHead>
              <TableHead>Company</TableHead>
              <TableHead>Email</TableHead>
              <TableHead>Phone</TableHead>
              <TableHead>Orders</TableHead>
              <TableHead>Total Spent</TableHead>
              <TableHead className="w-10" />
            </TableRow>
          </TableHeader>
          <TableBody>
            {isLoading || isError || data?.data.length === 0 ? (
              <TableStateRow
                colSpan={7}
                isLoading={isLoading}
                isError={isError}
                isEmpty={data?.data.length === 0}
                emptyMessage="No customers match your search."
                onRetry={refetch}
              />
            ) : (
              data?.data.map((customer) => (
                <TableRow key={customer.id}>
                  <TableCell className="font-medium">{customer.name}</TableCell>
                  <TableCell>{customer.company_name ?? '—'}</TableCell>
                  <TableCell>{customer.email ?? '—'}</TableCell>
                  <TableCell>{customer.phone ?? '—'}</TableCell>
                  <TableCell>
                    <Badge variant="secondary">{customer.order_count ?? 0}</Badge>
                  </TableCell>
                  <TableCell>${formatCurrency(customer.total_spent ?? 0)}</TableCell>
                  <TableCell>
                    <DropdownMenu>
                      <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="icon" aria-label={`Actions for ${customer.name}`}>
                          <MoreHorizontal className="size-4" />
                        </Button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent align="end">
                        <DropdownMenuItem onClick={() => setViewing(customer)}>
                          <Eye className="size-4" />
                          View
                        </DropdownMenuItem>
                        {canManage && (
                          <DropdownMenuItem
                            onClick={() => {
                              setEditing(customer)
                              setDialogOpen(true)
                            }}
                          >
                            Edit
                          </DropdownMenuItem>
                        )}
                        {canManage && (
                          <DropdownMenuItem variant="destructive" onClick={() => setDeleting(customer)}>
                            Delete
                          </DropdownMenuItem>
                        )}
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

      <AlertDialog open={deleting !== null} onOpenChange={(open) => !open && setDeleting(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete "{deleting?.name}"?</AlertDialogTitle>
            <AlertDialogDescription>
              {deleting?.order_count
                ? `This customer has ${deleting.order_count} order(s) on record. This cannot be undone.`
                : 'This cannot be undone.'}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction onClick={confirmDelete}>Delete</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <CustomerDetailSheet customer={viewing} onClose={() => setViewing(null)} />
    </div>
  )
}

function CustomerDetailSheet({ customer, onClose }: { customer: Customer | null; onClose: () => void }) {
  const { data } = useOrders({ customer_id: customer?.id, per_page: 10 })

  return (
    <Sheet open={customer !== null} onOpenChange={(open) => !open && onClose()}>
      <SheetContent className="overflow-y-auto sm:max-w-md">
        <SheetHeader>
          <SheetTitle>{customer?.name}</SheetTitle>
          <SheetDescription>{customer?.company_name || 'Customer details'}</SheetDescription>
        </SheetHeader>
        <div className="flex flex-col gap-4 px-4">
          <div className="grid grid-cols-2 gap-4 text-sm">
            <div>
              <div className="text-muted-foreground">Email</div>
              <div>{customer?.email ?? '—'}</div>
            </div>
            <div>
              <div className="text-muted-foreground">Phone</div>
              <div>{customer?.phone ?? '—'}</div>
            </div>
            <div>
              <div className="text-muted-foreground">Orders</div>
              <div>{customer?.order_count ?? 0}</div>
            </div>
            <div>
              <div className="text-muted-foreground">Total spent</div>
              <div>${formatCurrency(customer?.total_spent ?? 0)}</div>
            </div>
          </div>

          {(customer?.billing_address_line1 || customer?.billing_city) && (
            <div className="text-sm">
              <div className="text-muted-foreground">Billing address</div>
              <div>
                {[
                  customer?.billing_address_line1,
                  customer?.billing_city,
                  customer?.billing_state,
                  customer?.billing_postal_code,
                  customer?.billing_country,
                ]
                  .filter(Boolean)
                  .join(', ')}
              </div>
            </div>
          )}

          {customer?.notes && (
            <div className="text-sm">
              <div className="text-muted-foreground">Notes</div>
              <div>{customer.notes}</div>
            </div>
          )}

          <div>
            <div className="mb-2 text-sm font-medium">Recent orders</div>
            <div className="flex flex-col gap-2">
              {data?.data.length === 0 && (
                <div className="text-muted-foreground text-sm">No orders yet.</div>
              )}
              {data?.data.map((order) => (
                <div key={order.id} className="flex items-center justify-between rounded-md border p-2 text-sm">
                  <div>
                    <div className="font-medium">{order.order_number}</div>
                    <div className="text-muted-foreground">{new Date(order.created_at).toLocaleDateString()}</div>
                  </div>
                  <div className="flex items-center gap-2">
                    <OrderStatusBadge status={order.status} />
                    <span>${formatCurrency(order.total_amount)}</span>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>
      </SheetContent>
    </Sheet>
  )
}

function AddressFields({
  control,
  prefix,
}: {
  control: Control<CustomerFormValues>
  prefix: 'billing' | 'shipping'
}) {
  return (
    <div className="flex flex-col gap-4">
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <FormField
          control={control}
          name={`${prefix}_address_line1`}
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
          control={control}
          name={`${prefix}_address_line2`}
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
          control={control}
          name={`${prefix}_city`}
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
          control={control}
          name={`${prefix}_state`}
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
          control={control}
          name={`${prefix}_postal_code`}
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
          control={control}
          name={`${prefix}_country`}
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
    </div>
  )
}

function CustomerFormDialog({
  customer,
  onSaved,
}: {
  customer: Customer | null
  onSaved: () => void
}) {
  const createCustomer = useCreateCustomer()
  const updateCustomer = useUpdateCustomer()

  const form = useForm<CustomerFormValues>({
    resolver: zodResolver(customerSchema),
    values: {
      name: customer?.name ?? '',
      company_name: customer?.company_name ?? '',
      email: customer?.email ?? '',
      phone: customer?.phone ?? '',
      billing_address_line1: customer?.billing_address_line1 ?? '',
      billing_address_line2: customer?.billing_address_line2 ?? '',
      billing_city: customer?.billing_city ?? '',
      billing_state: customer?.billing_state ?? '',
      billing_postal_code: customer?.billing_postal_code ?? '',
      billing_country: customer?.billing_country ?? '',
      shipping_address_line1: customer?.shipping_address_line1 ?? '',
      shipping_address_line2: customer?.shipping_address_line2 ?? '',
      shipping_city: customer?.shipping_city ?? '',
      shipping_state: customer?.shipping_state ?? '',
      shipping_postal_code: customer?.shipping_postal_code ?? '',
      shipping_country: customer?.shipping_country ?? '',
      notes: customer?.notes ?? '',
    },
  })

  async function onSubmit(values: CustomerInput) {
    try {
      if (customer) {
        await updateCustomer.mutateAsync({ id: customer.id, ...values })
        toast.success('Customer updated')
      } else {
        await createCustomer.mutateAsync(values)
        toast.success('Customer created')
      }
      onSaved()
    } catch (error) {
      if (error instanceof ApiError && error.errors) {
        for (const [field, messages] of Object.entries(error.errors)) {
          form.setError(field as keyof CustomerFormValues, { message: messages[0] })
        }
      } else {
        toast.error(error instanceof ApiError ? error.message : 'Failed to save customer')
      }
    }
  }

  return (
    <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
      <DialogHeader>
        <DialogTitle>{customer ? 'Edit customer' : 'New customer'}</DialogTitle>
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
          <FormField
            control={form.control}
            name="company_name"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Company</FormLabel>
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
              name="email"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Email</FormLabel>
                  <FormControl>
                    <Input type="email" {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="phone"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Phone</FormLabel>
                  <FormControl>
                    <Input {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
          </div>

          <div className="text-sm font-medium">Billing address</div>
          <AddressFields control={form.control} prefix="billing" />

          <div className="text-sm font-medium">Shipping address</div>
          <AddressFields control={form.control} prefix="shipping" />

          <FormField
            control={form.control}
            name="notes"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Notes</FormLabel>
                <FormControl>
                  <Textarea {...field} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />

          <DialogFooter>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {customer ? 'Save changes' : 'Create customer'}
            </Button>
          </DialogFooter>
        </form>
      </Form>
    </DialogContent>
  )
}
