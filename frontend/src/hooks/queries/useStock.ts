import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'
import type { ProductStock, StockMovementType } from '@/types/api'

export interface AdjustStockInput {
  warehouse_id: number
  quantity_change: number
  type: StockMovementType
  note?: string
}

export function useProductStock(productId: number | null) {
  return useQuery({
    queryKey: ['stock', productId],
    queryFn: () => api.get<{ data: ProductStock[] }>(`/products/${productId}/stock`),
    enabled: productId !== null,
  })
}

export function useAdjustStock(productId: number) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (input: AdjustStockInput) => api.post(`/products/${productId}/stock/adjust`, input),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['stock', productId] })
      queryClient.invalidateQueries({ queryKey: ['products'] })
    },
  })
}
