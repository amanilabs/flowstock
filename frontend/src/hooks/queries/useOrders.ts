import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'
import type { Order, OrderStatus, PaginatedResponse } from '@/types/api'

export interface CreateOrderInput {
  customer_id: number
  warehouse_id: number
  items: { product_id: number; quantity: number; unit_price?: number }[]
  notes?: string
}

export function useOrders(params: { page?: number; status?: OrderStatus | ''; customer_id?: number } = {}) {
  return useQuery({
    queryKey: ['orders', params],
    queryFn: () => api.get<PaginatedResponse<Order>>('/orders', params),
  })
}

export function useOrder(id: number | null) {
  return useQuery({
    queryKey: ['orders', id],
    queryFn: () => api.get<{ data: Order }>(`/orders/${id}`),
    enabled: id !== null,
  })
}

export function useCreateOrder() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (input: CreateOrderInput) => api.post<{ data: Order }>('/orders', input),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['orders'] }),
  })
}

function useOrderTransition(action: 'confirm' | 'process' | 'ship' | 'deliver' | 'cancel' | 'refund') {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, reason }: { id: number; reason?: string }) =>
      api.post<{ data: Order }>(`/orders/${id}/${action}`, reason ? { reason } : undefined),
    onSuccess: (_, { id }) => {
      queryClient.invalidateQueries({ queryKey: ['orders'] })
      queryClient.invalidateQueries({ queryKey: ['orders', id] })
    },
  })
}

export const useConfirmOrder = () => useOrderTransition('confirm')
export const useProcessOrder = () => useOrderTransition('process')
export const useShipOrder = () => useOrderTransition('ship')
export const useDeliverOrder = () => useOrderTransition('deliver')
export const useCancelOrder = () => useOrderTransition('cancel')
export const useRefundOrder = () => useOrderTransition('refund')
