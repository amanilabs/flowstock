import { Button } from '@/components/ui/button'
import { TableCell, TableRow } from '@/components/ui/table'

/**
 * Shared loading/error/empty row for a table body, so failed queries never
 * render as an indistinguishable blank table.
 */
export function TableStateRow({
  colSpan,
  isLoading,
  isError,
  isEmpty,
  emptyMessage,
  onRetry,
}: {
  colSpan: number
  isLoading: boolean
  isError: boolean
  isEmpty: boolean
  emptyMessage: string
  onRetry: () => void
}) {
  if (isLoading) {
    return (
      <TableRow>
        <TableCell colSpan={colSpan} className="text-muted-foreground text-center">
          Loading…
        </TableCell>
      </TableRow>
    )
  }

  if (isError) {
    return (
      <TableRow>
        <TableCell colSpan={colSpan} className="text-center">
          <span className="text-destructive">Failed to load data.</span>{' '}
          <Button variant="link" className="h-auto p-0" onClick={onRetry}>
            Try again
          </Button>
        </TableCell>
      </TableRow>
    )
  }

  if (isEmpty) {
    return (
      <TableRow>
        <TableCell colSpan={colSpan} className="text-muted-foreground text-center">
          {emptyMessage}
        </TableCell>
      </TableRow>
    )
  }

  return null
}
