import { useState } from "react"
import { Megaphone, X, ShieldCheck } from "lucide-react"
import { Card, CardContent } from "@/components/ui/card"
import { cn, formatDate } from "@/lib/utils"

export interface AnnouncementListProps {
  id: number
  title: string
  content: string
  type: string
  published_at: string
}

export function AnnouncementList({ announcements, elseNormal = true }: { announcements: AnnouncementListProps[]; elseNormal?: boolean }) {
  const [selected, setSelected] = useState<AnnouncementListProps | null>(null)

  if (announcements.length === 0) {
    if (!elseNormal) return null
    return (
      <Card className="border-emerald-500/20">
        <CardContent className="flex items-center gap-3 p-4">
          <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-500">
            <ShieldCheck className="h-4 w-4" />
          </div>
          <div>
            <p className="text-sm font-semibold">Semua Server Normal</p>
            <p className="text-sm text-muted-foreground">Tidak ada pengumuman atau maintenance.</p>
          </div>
        </CardContent>
      </Card>
    )
  }

  return (
    <>
      <div className="space-y-2">
        {announcements.map((a) => {
          const tone = a.type === "warning"
            ? { card: "border-amber-500/30 bg-amber-500/[0.06]", icon: "bg-amber-500/10 text-amber-500" }
            : a.type === "maintenance"
              ? { card: "border-rose-500/30 bg-rose-500/[0.06]", icon: "bg-rose-500/10 text-rose-500" }
              : { card: "border-primary/20", icon: "bg-primary/10 text-primary" }
          return (
            <Card
              key={a.id}
              onClick={() => setSelected(a)}
              className={cn(tone.card, "cursor-pointer transition-shadow hover:shadow-md")}
            >
              <CardContent className="p-4">
                <div className="flex items-start justify-between gap-3">
                  <div className="flex min-w-0 items-center gap-2.5">
                    <div className={cn("flex h-9 w-9 shrink-0 items-center justify-center rounded-xl", tone.icon)}>
                      <Megaphone className="h-4 w-4" />
                    </div>
                    <p className="min-w-0 truncate text-sm font-semibold">{a.title}</p>
                  </div>
                  <span className="shrink-0 rounded-md bg-muted px-2 py-1 text-[11px] font-medium text-muted-foreground">
                    {formatDate(a.published_at)}
                  </span>
                </div>
                <div className="my-3 border-t border-border" />
                <p className="line-clamp-2 whitespace-pre-line text-sm text-muted-foreground">{a.content}</p>
                <p className="mt-2 text-xs font-medium text-primary">Lihat detail</p>
              </CardContent>
            </Card>
          )
        })}
      </div>

      {selected ? (
        <div className="fixed inset-0 z-[70] flex items-center justify-center p-4">
          <div className="fixed inset-0 bg-black/70 backdrop-blur-[2px]" onClick={() => setSelected(null)} />
          <div className="relative w-full max-w-md rounded-2xl border border-border bg-card p-5 shadow-xl">
            <div className="flex items-start justify-between gap-3">
              <div className="flex min-w-0 items-center gap-2.5">
                <div className={cn(
                  "flex h-10 w-10 shrink-0 items-center justify-center rounded-xl",
                  selected.type === "warning" ? "bg-amber-500/10 text-amber-500" : selected.type === "maintenance" ? "bg-rose-500/10 text-rose-500" : "bg-primary/10 text-primary",
                )}>
                  <Megaphone className="h-5 w-5" />
                </div>
                <div className="min-w-0">
                  <p className="truncate font-display text-base font-semibold">{selected.title}</p>
                  <p className="text-xs text-muted-foreground">{formatDate(selected.published_at)}</p>
                </div>
              </div>
              <button onClick={() => setSelected(null)} className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-muted-foreground hover:bg-accent" aria-label="Tutup">
                <X className="h-4 w-4" />
              </button>
            </div>

            <div className="mt-3 flex gap-1.5">
              <span className={cn(
                "rounded-md px-2 py-0.5 text-[11px] font-semibold capitalize",
                selected.type === "warning" ? "bg-amber-500/10 text-amber-500" : selected.type === "maintenance" ? "bg-rose-500/10 text-rose-500" : "bg-primary/10 text-primary",
              )}>
                {selected.type}
              </span>
            </div>

            <div className="my-4 border-t border-border" />
            <p className="whitespace-pre-line text-sm leading-relaxed text-muted-foreground">{selected.content}</p>
          </div>
        </div>
      ) : null}
    </>
  )
}