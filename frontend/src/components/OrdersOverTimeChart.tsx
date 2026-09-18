import { Area, AreaChart, CartesianGrid, XAxis } from 'recharts'
import { useOrders } from '@/hooks/queries/useOrders'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { type ChartConfig, ChartContainer, ChartTooltip, ChartTooltipContent } from '@/components/ui/chart'

const DAYS = 14

const chartConfig: ChartConfig = {
  orders: {
    label: 'Orders',
    color: 'var(--primary)',
  },
}

function buildDailyCounts(dates: string[]) {
  const counts = new Map<string, number>()
  const today = new Date()
  today.setHours(0, 0, 0, 0)

  for (let i = DAYS - 1; i >= 0; i--) {
    const day = new Date(today)
    day.setDate(day.getDate() - i)
    counts.set(day.toISOString().slice(0, 10), 0)
  }

  for (const date of dates) {
    const key = date.slice(0, 10)
    if (counts.has(key)) {
      counts.set(key, (counts.get(key) ?? 0) + 1)
    }
  }

  return Array.from(counts.entries()).map(([date, orders]) => ({ date, orders }))
}

export function OrdersOverTimeChart() {
  // Fetched at demo scale — a real high-volume deployment would want a
  // dedicated backend aggregation endpoint rather than paging through
  // every order client-side.
  const { data, isLoading } = useOrders({ page: 1, per_page: 100 })

  const chartData = buildDailyCounts(data?.data.map((order) => order.created_at) ?? [])

  return (
    <Card>
      <CardHeader>
        <CardTitle>Orders placed</CardTitle>
        <CardDescription>Last {DAYS} days</CardDescription>
      </CardHeader>
      <CardContent>
        {isLoading ? (
          <p className="text-muted-foreground text-sm">Loading…</p>
        ) : (
          <ChartContainer config={chartConfig} className="aspect-auto h-64 w-full">
            <AreaChart data={chartData} margin={{ top: 12, left: 12, right: 12 }}>
              <defs>
                <linearGradient id="fillOrders" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="5%" stopColor="var(--color-orders)" stopOpacity={0.4} />
                  <stop offset="95%" stopColor="var(--color-orders)" stopOpacity={0.05} />
                </linearGradient>
              </defs>
              <CartesianGrid vertical={false} />
              <XAxis
                dataKey="date"
                tickLine={false}
                axisLine={false}
                tickMargin={8}
                minTickGap={32}
                tickFormatter={(value) =>
                  new Date(value).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
                }
              />
              <ChartTooltip
                content={
                  <ChartTooltipContent
                    labelFormatter={(value) =>
                      new Date(value).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
                    }
                  />
                }
              />
              <Area
                dataKey="orders"
                type="monotone"
                fill="url(#fillOrders)"
                stroke="var(--color-orders)"
                baseValue={0}
              />
            </AreaChart>
          </ChartContainer>
        )}
      </CardContent>
    </Card>
  )
}
