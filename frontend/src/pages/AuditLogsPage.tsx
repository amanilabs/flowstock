import { useState } from 'react'
import { useAuditLogs } from '@/hooks/queries/useAuditLogs'
import { Input } from '@/components/ui/input'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { PaginationBar } from '@/components/PaginationBar'

export function AuditLogsPage() {
  const [page, setPage] = useState(1)
  const [subjectType, setSubjectType] = useState('')
  const [logName, setLogName] = useState('')
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')

  const { data, isLoading } = useAuditLogs({
    page,
    subject_type: subjectType || undefined,
    log_name: logName || undefined,
    from: from || undefined,
    to: to || undefined,
  })

  return (
    <div className="flex flex-col gap-4">
      <h1 className="text-2xl font-semibold">Audit Logs</h1>

      <div className="flex flex-wrap gap-3">
        <Input
          placeholder="Subject type (e.g. Order)"
          className="w-56"
          value={subjectType}
          onChange={(e) => {
            setSubjectType(e.target.value)
            setPage(1)
          }}
        />
        <Input
          placeholder="Log name (e.g. auth)"
          className="w-48"
          value={logName}
          onChange={(e) => {
            setLogName(e.target.value)
            setPage(1)
          }}
        />
        <Input
          type="date"
          className="w-40"
          value={from}
          onChange={(e) => {
            setFrom(e.target.value)
            setPage(1)
          }}
        />
        <Input
          type="date"
          className="w-40"
          value={to}
          onChange={(e) => {
            setTo(e.target.value)
            setPage(1)
          }}
        />
      </div>

      <div className="rounded-md border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>When</TableHead>
              <TableHead>Log</TableHead>
              <TableHead>Description</TableHead>
              <TableHead>Subject</TableHead>
              <TableHead>Causer</TableHead>
              <TableHead>Changes</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {isLoading ? (
              <TableRow>
                <TableCell colSpan={6} className="text-muted-foreground text-center">
                  Loading…
                </TableCell>
              </TableRow>
            ) : data?.data.length === 0 ? (
              <TableRow>
                <TableCell colSpan={6} className="text-muted-foreground text-center">
                  No matching audit log entries.
                </TableCell>
              </TableRow>
            ) : (
              data?.data.map((log) => (
                <TableRow key={log.id}>
                  <TableCell className="text-muted-foreground text-xs">
                    {new Date(log.created_at).toLocaleString()}
                  </TableCell>
                  <TableCell>{log.log_name}</TableCell>
                  <TableCell>{log.description}</TableCell>
                  <TableCell>
                    {log.subject_type ? `${log.subject_type} #${log.subject_id}` : '—'}
                  </TableCell>
                  <TableCell>{log.causer?.name ?? '—'}</TableCell>
                  <TableCell className="max-w-xs truncate font-mono text-xs">
                    {Array.isArray(log.attribute_changes)
                      ? '—'
                      : JSON.stringify(log.attribute_changes.attributes ?? {})}
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </div>

      {data?.meta && <PaginationBar meta={data.meta} onPageChange={setPage} />}
    </div>
  )
}
