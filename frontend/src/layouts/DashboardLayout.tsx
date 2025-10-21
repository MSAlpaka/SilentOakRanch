import { useState, type PropsWithChildren, useEffect, useMemo } from 'react'
import { NavLink, useLocation, useNavigate } from 'react-router-dom'
import { CalendarDays, ListTree, Menu, Settings, X } from 'lucide-react'

import { Button } from '@/components/ui/button'
import { Separator } from '@/components/ui/separator'
import { useAuth } from '@/hooks/useAuth'

const NAV_ITEMS = [
  { label: 'Overview', to: '/dashboard', icon: ListTree },
  { label: 'Bookings', to: '/dashboard/bookings', icon: CalendarDays },
  { label: 'Settings', to: '/dashboard/settings', icon: Settings },
]

function MobileSidebar({ open, onClose }: { open: boolean; onClose: () => void }) {
  return (
    <div
      className={`fixed inset-0 z-40 lg:hidden ${open ? 'pointer-events-auto' : 'pointer-events-none'}`}
    >
      <div
        className={`absolute inset-0 bg-black/40 transition-opacity duration-200 ${open ? 'opacity-100' : 'opacity-0'}`}
        onClick={onClose}
      />
      <aside
        className={`absolute left-0 top-0 h-full w-72 transform bg-white shadow-xl transition-transform duration-300 ease-out ${
          open ? 'translate-x-0' : '-translate-x-full'
        }`}
      >
        <SidebarContent onNavigate={onClose} />
      </aside>
    </div>
  )
}

function SidebarContent({ onNavigate }: { onNavigate?: () => void }) {
  const location = useLocation()

  return (
    <div className="flex h-full flex-col gap-6 bg-white px-6 py-8">
      <div className="flex items-center justify-between">
        <div className="text-lg font-semibold text-primary">Silent Oak Ranch</div>
        {onNavigate ? (
          <Button variant="ghost" size="icon" onClick={onNavigate} className="text-primary">
            <X className="h-5 w-5" />
          </Button>
        ) : null}
      </div>
      <nav className="flex flex-1 flex-col gap-2">
        {NAV_ITEMS.map((item) => {
          const Icon = item.icon
          return (
            <NavLink
              key={item.to}
              to={item.to}
              onClick={onNavigate}
              className={({ isActive }) =>
                `flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors duration-200 ${
                  isActive || location.pathname.startsWith(item.to)
                    ? 'bg-[#385a3f] text-white shadow'
                    : 'text-[#385a3f] hover:bg-[#a6bfa7]/20'
                }`
              }
            >
              <Icon className="h-4 w-4" />
              <span>{item.label}</span>
            </NavLink>
          )
        })}
      </nav>
    </div>
  )
}

export default function DashboardLayout({ children }: PropsWithChildren) {
  const { user, logout } = useAuth()
  const [open, setOpen] = useState(false)
  const navigate = useNavigate()
  const location = useLocation()

  const displayName = useMemo(() => {
    const nameParts = [user?.firstName, user?.lastName].filter(Boolean)
    if (nameParts.length > 0) {
      return nameParts.join(' ')
    }
    return user?.email ?? 'Team member'
  }, [user])

  useEffect(() => {
    setOpen(false)
  }, [location.pathname])

  const currentNav = NAV_ITEMS.find((item) => location.pathname.startsWith(item.to))

  const handleLogout = async () => {
    await logout()
    navigate('/login', { replace: true })
  }

  return (
    <div className="min-h-screen bg-background text-[#222]">
      <MobileSidebar open={open} onClose={() => setOpen(false)} />
      <div className="flex min-h-screen flex-col lg:flex-row">
        <aside className="hidden w-72 shrink-0 border-r border-[#e0dacc] bg-white lg:block">
          <SidebarContent />
        </aside>
        <div className="flex-1">
          <header className="sticky top-0 z-30 flex items-center justify-between gap-4 border-b border-[#e0dacc] bg-white/80 px-4 py-4 backdrop-blur-sm lg:px-8">
            <div className="flex items-center gap-3">
              <Button variant="ghost" size="icon" className="text-primary lg:hidden" onClick={() => setOpen(true)}>
                <Menu className="h-5 w-5" />
              </Button>
              <div className="flex flex-col">
                <span className="text-xs uppercase tracking-wide text-primary/60 lg:text-sm">
                  Silent Oak Ranch
                </span>
                <span className="text-base font-semibold text-primary lg:text-lg">
                  {currentNav?.label ?? 'Dashboard'}
                </span>
              </div>
            </div>
            <div className="flex items-center gap-4">
              <div className="hidden flex-col text-right text-sm lg:flex">
                <span className="font-medium text-primary">{displayName}</span>
                <span className="text-xs text-primary/70">{user?.email}</span>
              </div>
              <Separator orientation="vertical" className="hidden h-10 lg:block" />
              <Button variant="outline" onClick={handleLogout} className="transition-all duration-200 hover:translate-y-[-1px]">
                Logout
              </Button>
            </div>
          </header>
          <main className="h-full w-full bg-background px-4 py-6 lg:px-8">
            <div className="mx-auto max-w-6xl space-y-6 pb-16">{children}</div>
          </main>
        </div>
      </div>
    </div>
  )
}
