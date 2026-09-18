import type { LucideIcon } from 'lucide-react'
import { useNavigate } from 'react-router-dom'
import { Card, CardAction, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card'

interface StatCard {
  label: string
  value: number | undefined
  caption: string
  icon: LucideIcon
  to: string
}

export function SectionCards({ stats }: { stats: StatCard[] }) {
  const navigate = useNavigate()

  return (
    <div className="*:data-[slot=card]:from-primary/5 *:data-[slot=card]:to-card dark:*:data-[slot=card]:bg-card grid grid-cols-1 gap-4 *:data-[slot=card]:bg-gradient-to-t *:data-[slot=card]:shadow-xs @xl/main:grid-cols-2 @5xl/main:grid-cols-4">
      {stats.map((stat) => (
        <Card
          key={stat.label}
          className="@container/card cursor-pointer"
          onClick={() => navigate(stat.to)}
        >
          <CardHeader>
            <CardDescription>{stat.label}</CardDescription>
            <CardTitle className="text-2xl font-semibold tabular-nums @[250px]/card:text-3xl">
              {stat.value ?? '—'}
            </CardTitle>
            <CardAction>
              <stat.icon className="text-muted-foreground size-5" />
            </CardAction>
          </CardHeader>
          <CardFooter className="text-muted-foreground text-sm">{stat.caption}</CardFooter>
        </Card>
      ))}
    </div>
  )
}
