import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'
import type { PaginatedResponse, Warehouse } from '@/types/api'

export interface WarehouseInput {
  name: string
  code: string
  address_line1: string
  address_line2?: string | null
  city: string
  state?: string | null
  postal_code: string
  country: string
  contact_name?: string | null
  contact_phone?: string | null
  contact_email?: string | null
  is_active?: boolean
}

export function useWarehouses(params: { page?: number; active_only?: boolean } = {}) {
  return useQuery({
    queryKey: ['warehouses', params],
    queryFn: () => api.get<PaginatedResponse<Warehouse>>('/warehouses', params),
  })
}

export function useCreateWarehouse() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (input: WarehouseInput) => api.post<{ data: Warehouse }>('/warehouses', input),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['warehouses'] }),
  })
}

export function useUpdateWarehouse() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, ...input }: Partial<WarehouseInput> & { id: number }) =>
      api.put<{ data: Warehouse }>(`/warehouses/${id}`, input),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['warehouses'] }),
  })
}

export function useDeleteWarehouse() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => api.delete(`/warehouses/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['warehouses'] }),
  })
}
