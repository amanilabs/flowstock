import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'
import type { InventoryRow, PaginatedResponse, ProductStock, StockMovement, StockMovementType } from '@/types/api'

export interface AdjustStockInput {
  warehouse_id: number
  quantity_change: number
  type: StockMovementType
  note?: string
}

export interface InventoryFilters {
  page?: number
  warehouse_id?: number
  product_id?: number
  category_id?: number
  low_stock?: boolean
  search?: string
}

export function useInventory(params: InventoryFilters = {}) {
  return useQuery({
    queryKey: ['inventory', params],
    queryFn: () => api.get<PaginatedResponse<InventoryRow>>('/stock', params),
  })
}

export function useProductStock(productId: number | null) {
  return useQuery({
    queryKey: ['stock', productId],
    queryFn: () => api.get<{ data: ProductStock[] }>(`/products/${productId}/stock`),
    enabled: productId !== null,
  })
}

export function useStockMovements(productId: number | null, warehouseId?: number) {
  return useQuery({
    queryKey: ['stock-movements', productId, warehouseId],
    queryFn: () =>
      api.get<PaginatedResponse<StockMovement>>(`/products/${productId}/stock/movements`, {
        warehouse_id: warehouseId,
      }),
    enabled: productId !== null,
  })
}

export function useAdjustStock(productId: number) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (input: AdjustStockInput) => api.post(`/products/${productId}/stock/adjust`, input),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['stock', productId] })
      queryClient.invalidateQueries({ queryKey: ['stock-movements', productId] })
      queryClient.invalidateQueries({ queryKey: ['inventory'] })
      // Only total_stock on the products list is affected, and it's not shown
      // anywhere on this page — mark it stale (refetches on next mount, since
      // staleTime defaults to 0) instead of forcing an immediate parallel
      // refetch of a list that likely isn't even mounted right now.
      queryClient.invalidateQueries({ queryKey: ['products'], refetchType: 'none' })
    },
  })
}

export function useUpdateReorderPoint(productId: number) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ warehouseId, reorderPoint }: { warehouseId: number; reorderPoint: number }) =>
      api.put<{ data: ProductStock }>(`/products/${productId}/stock/${warehouseId}`, {
        reorder_point: reorderPoint,
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['stock', productId] })
      queryClient.invalidateQueries({ queryKey: ['inventory'] })
    },
  })
}
