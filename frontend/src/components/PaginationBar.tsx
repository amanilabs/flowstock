import { Button } from '@/components/ui/button'
import type { PaginationMeta } from '@/types/api'

export function PaginationBar({
  meta,
  onPageChange,
}: {
  meta: PaginationMeta
  onPageChange: (page: number) => void
}) {
  return (
    <div className="flex items-center justify-between px-1 py-3">
      <p className="text-muted-foreground text-sm">
        {meta.total === 0 ? 'No results' : `Showing ${meta.from}–${meta.to} of ${meta.total}`}
      </p>
      <div className="flex gap-2">
        <Button
          variant="outline"
          size="sm"
          disabled={meta.current_page <= 1}
          onClick={() => onPageChange(meta.current_page - 1)}
        >
          Previous
        </Button>
        <Button
          variant="outline"
          size="sm"
          disabled={meta.current_page >= meta.last_page}
          onClick={() => onPageChange(meta.current_page + 1)}
        >
          Next
        </Button>
      </div>
    </div>
  )
}
