import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'
import type { PaginatedResponse, ProductCategory } from '@/types/api'

export interface CategoryInput {
  name: string
  slug: string
  parent_id?: number | null
}

export interface CategoryFilters {
  page?: number
  search?: string
}

export function useCategories(params: CategoryFilters = {}) {
  return useQuery({
    queryKey: ['categories', params],
    queryFn: () => api.get<PaginatedResponse<ProductCategory>>('/product-categories', params),
  })
}

export function useCreateCategory() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (input: CategoryInput) => api.post<{ data: ProductCategory }>('/product-categories', input),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['categories'] }),
  })
}

export function useUpdateCategory() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, ...input }: Partial<CategoryInput> & { id: number }) =>
      api.put<{ data: ProductCategory }>(`/product-categories/${id}`, input),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['categories'] }),
  })
}

export function useDeleteCategory() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => api.delete(`/product-categories/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['categories'] }),
  })
}
