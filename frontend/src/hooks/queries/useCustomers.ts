import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'
import type { Customer, PaginatedResponse } from '@/types/api'

export interface CustomerInput {
  name: string
  company_name?: string | null
  email?: string | null
  phone?: string | null
  billing_address_line1?: string | null
  billing_address_line2?: string | null
  billing_city?: string | null
  billing_state?: string | null
  billing_postal_code?: string | null
  billing_country?: string | null
  shipping_address_line1?: string | null
  shipping_address_line2?: string | null
  shipping_city?: string | null
  shipping_state?: string | null
  shipping_postal_code?: string | null
  shipping_country?: string | null
  notes?: string | null
}

export function useCustomers(params: { page?: number } = {}) {
  return useQuery({
    queryKey: ['customers', params],
    queryFn: () => api.get<PaginatedResponse<Customer>>('/customers', params),
  })
}

export function useCreateCustomer() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (input: CustomerInput) => api.post<{ data: Customer }>('/customers', input),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['customers'] }),
  })
}

export function useUpdateCustomer() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, ...input }: Partial<CustomerInput> & { id: number }) =>
      api.put<{ data: Customer }>(`/customers/${id}`, input),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['customers'] }),
  })
}

export function useDeleteCustomer() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => api.delete(`/customers/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['customers'] }),
  })
}
