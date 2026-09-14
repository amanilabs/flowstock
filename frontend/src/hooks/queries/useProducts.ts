import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'
import type { PaginatedResponse, Product } from '@/types/api'

export interface ProductInput {
  category_id: number | null
  sku: string
  name: string
  description?: string | null
  barcode?: string | null
  unit_of_measure: string
  cost_price: number
  selling_price: number
  reorder_point?: number
  is_active?: boolean
}

export function useProducts(params: { page?: number; active_only?: boolean } = {}) {
  return useQuery({
    queryKey: ['products', params],
    queryFn: () => api.get<PaginatedResponse<Product>>('/products', params),
  })
}

export function useCreateProduct() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (input: ProductInput) => api.post<{ data: Product }>('/products', input),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['products'] }),
  })
}

export function useUpdateProduct() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, ...input }: Partial<ProductInput> & { id: number }) =>
      api.put<{ data: Product }>(`/products/${id}`, input),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['products'] }),
  })
}

export function useDeleteProduct() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => api.delete(`/products/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['products'] }),
  })
}
