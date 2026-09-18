import { useEffect, useState } from 'react'
import { useDebouncedValue } from '@/hooks/useDebouncedValue'
import { useAuditLogs } from '@/hooks/queries/useAuditLogs'
import type { AuditLog } from '@/types/api'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { PageHeader } from '@/components/PageHeader'
import { PaginationBar } from '@/components/PaginationBar'

const SUBJECT_TYPES = [
  { label: 'Product', value: 'App\\Models\\Product' },
  { label: 'Category', value: 'App\\Models\\ProductCategory' },
  { label: 'Warehouse', value: 'App\\Models\\Warehouse' },
  { label: 'Customer', value: 'App\\Models\\Customer' },
  { label: 'Order', value: 'App\\Models\\Order' },
  { label: 'User', value: 'App\\Models\\User' },
]

type ActionFilter = '' | 'created' | 'updated' | 'deleted' | 'login'

const ACTION_OPTIONS: { label: string; value: ActionFilter }[] = [
  { label: 'Created', value: 'created' },
  { label: 'Updated', value: 'updated' },
  { label: 'Deleted', value: 'deleted' },
  { label: 'Login', value: 'login' },
]

const ACTION_BADGE_VARIANT: Record<string, string> = {
  created: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
  updated: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
  deleted: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
}

function ActionBadge({ log }: { log: AuditLog }) {
  const className = (log.event && ACTION_BADGE_VARIANT[log.event]) || 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300'
  return (
    <Badge className={className} variant="secondary">
      {log.description}
    </Badge>
  )
}

function humanize(key: string): string {
  return key.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

function formatValue(value: unknown): string {
  if (value === null || value === undefined || value === '') return '—'
  if (typeof value === 'boolean') return value ? 'Yes' : 'No'
  if (typeof value === 'object') return JSON.stringify(value)
  return String(value)
}

function AttributeChangesView({ changes }: { changes: AuditLog['attribute_changes'] }) {
  if (Array.isArray(changes)) return null

  const oldValues = changes.old
  const newValues = changes.attributes
  if (!oldValues && !newValues) return null

  const keys = Array.from(new Set([...Object.keys(oldValues ?? {}), ...Object.keys(newValues ?? {})]))
  if (keys.length === 0) return null

  const mode: 'diff' | 'new-only' | 'old-only' = oldValues && newValues ? 'diff' : newValues ? 'new-only' : 'old-only'

  return (
    <div className="overflow-x-auto rounded-md border">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Field</TableHead>
            {mode === 'diff' && <TableHead>Old value</TableHead>}
            <TableHead>{mode === 'old-only' ? 'Value at deletion' : mode === 'diff' ? 'New value' : 'Value'}</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {keys.map((key) => (
            <TableRow key={key}>
              <TableCell className="font-medium">{humanize(key)}</TableCell>
              {mode === 'diff' && (
                <TableCell className="text-muted-foreground">{formatValue(oldValues?.[key])}</TableCell>
              )}
              <TableCell>{formatValue(mode === 'old-only' ? oldValues?.[key] : newValues?.[key])}</TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  )
}

function PropertiesView({ properties }: { properties: AuditLog['properties'] }) {
  if (Array.isArray(properties) || Object.keys(properties).length === 0) return null
  return (
    <div className="flex flex-col gap-1 text-sm">
      {Object.entries(properties).map(([key, value]) => (
        <div key={key} className="flex items-center justify-between gap-4 border-b py-1.5 last:border-0">
          <span className="text-muted-foreground">{humanize(key)}</span>
          <span className="text-right">{formatValue(value)}</span>
        </div>
      ))}
    </div>
  )
}

export function AuditLogsPage() {
  const [page, setPage] = useState(1)
  const [searchInput, setSearchInput] = useState('')
  const search = useDebouncedValue(searchInput, 300)
  const [action, setAction] = useState<ActionFilter>('')
  const [subjectType, setSubjectType] = useState('')
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const [viewing, setViewing] = useState<AuditLog | null>(null)

  useEffect(() => setPage(1), [search, action, subjectType, from, to])

  const { data, isLoading } = useAuditLogs({
    page,
    causer_search: search || undefined,
    event: action && action !== 'login' ? action : undefined,
    log_name: action === 'login' ? 'auth' : undefined,
    subject_type: subjectType || undefined,
    from: from || undefined,
    to: to || undefined,
  })

  return (
    <div className="flex flex-col gap-4">
      <PageHeader title="Audit Logs" description="Every tracked change across your account, tenant-scoped." />

      <div className="flex flex-wrap gap-3">
        <Input
          placeholder="Search by user name or email…"
          className="w-full sm:w-64"
          value={searchInput}
          onChange={(e) => setSearchInput(e.target.value)}
        />
        <Select value={action || 'all'} onValueChange={(v) => setAction(v === 'all' ? '' : (v as ActionFilter))}>
          <SelectTrigger className="w-full sm:w-40">
            <SelectValue placeholder="All actions" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All actions</SelectItem>
            {ACTION_OPTIONS.map((opt) => (
              <SelectItem key={opt.value} value={opt.value}>
                {opt.label}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
        <Select value={subjectType || 'all'} onValueChange={(v) => setSubjectType(v === 'all' ? '' : v)}>
          <SelectTrigger className="w-full sm:w-40">
            <SelectValue placeholder="All entities" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All entities</SelectItem>
            {SUBJECT_TYPES.map((opt) => (
              <SelectItem key={opt.value} value={opt.value}>
                {opt.label}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
        <Input
          type="date"
          className="w-full sm:w-40"
          value={from}
          max={to || undefined}
          onChange={(e) => setFrom(e.target.value)}
        />
        <Input
          type="date"
          className="w-full sm:w-40"
          value={to}
          min={from || undefined}
          onChange={(e) => setTo(e.target.value)}
        />
      </div>

      <div className="overflow-x-auto rounded-md border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Date/time</TableHead>
              <TableHead>User</TableHead>
              <TableHead>Action</TableHead>
              <TableHead>Entity type</TableHead>
              <TableHead>Entity ID</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {isLoading ? (
              <TableRow>
                <TableCell colSpan={5} className="text-muted-foreground text-center">
                  Loading…
                </TableCell>
              </TableRow>
            ) : data?.data.length === 0 ? (
              <TableRow>
                <TableCell colSpan={5} className="text-muted-foreground text-center">
                  No matching audit log entries.
                </TableCell>
              </TableRow>
            ) : (
              data?.data.map((log) => (
                <TableRow key={log.id} className="cursor-pointer" onClick={() => setViewing(log)}>
                  <TableCell className="text-muted-foreground text-xs whitespace-nowrap">
                    {new Date(log.created_at).toLocaleString()}
                  </TableCell>
                  <TableCell>{log.causer?.name ?? '—'}</TableCell>
                  <TableCell>
                    <ActionBadge log={log} />
                  </TableCell>
                  <TableCell>{log.subject_type ?? '—'}</TableCell>
                  <TableCell>{log.subject_id ?? '—'}</TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </div>

      {data?.meta && <PaginationBar meta={data.meta} onPageChange={setPage} />}

      <Sheet open={viewing !== null} onOpenChange={(open) => !open && setViewing(null)}>
        <SheetContent className="overflow-y-auto sm:max-w-lg">
          <SheetHeader>
            <SheetTitle>
              {viewing?.subject_type ? `${viewing.subject_type} #${viewing.subject_id}` : 'Audit log entry'}
            </SheetTitle>
            <SheetDescription>{viewing?.description}</SheetDescription>
          </SheetHeader>
          {viewing && (
            <div className="flex flex-col gap-4 px-4">
              <div className="grid grid-cols-2 gap-4 text-sm">
                <div>
                  <div className="text-muted-foreground">Date/time</div>
                  <div>{new Date(viewing.created_at).toLocaleString()}</div>
                </div>
                <div>
                  <div className="text-muted-foreground">User</div>
                  <div>{viewing.causer?.name ?? '—'}</div>
                  {viewing.causer?.email && (
                    <div className="text-muted-foreground text-xs">{viewing.causer.email}</div>
                  )}
                </div>
                <div>
                  <div className="text-muted-foreground">Action</div>
                  <div>
                    <ActionBadge log={viewing} />
                  </div>
                </div>
                <div>
                  <div className="text-muted-foreground">Log</div>
                  <div>{viewing.log_name ?? '—'}</div>
                </div>
              </div>

              <div>
                <div className="mb-2 text-sm font-medium">Changes</div>
                <AttributeChangesView changes={viewing.attribute_changes} />
                <PropertiesView properties={viewing.properties} />
                {Array.isArray(viewing.attribute_changes) &&
                  Array.isArray(viewing.properties) && (
                    <p className="text-muted-foreground text-sm">No additional details recorded.</p>
                  )}
              </div>
            </div>
          )}
        </SheetContent>
      </Sheet>
    </div>
  )
}
