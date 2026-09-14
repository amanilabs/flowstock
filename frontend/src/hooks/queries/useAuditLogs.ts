import { useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'
import type { AuditLog, PaginatedResponse } from '@/types/api'

export interface AuditLogFilters {
  page?: number
  subject_type?: string
  log_name?: string
  from?: string
  to?: string
}

export function useAuditLogs(params: AuditLogFilters = {}) {
  return useQuery({
    queryKey: ['audit-logs', params],
    queryFn: () => api.get<PaginatedResponse<AuditLog>>('/audit-logs', params),
  })
}
