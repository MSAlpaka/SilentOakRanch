import { useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'

import { acceptInvite } from '@/api/auth'
import { useAuth } from '@/hooks/useAuth'

export default function InviteAccept() {
  const [searchParams] = useSearchParams()
  const navigate = useNavigate()
  const { hydrate } = useAuth()
  const [password, setPassword] = useState('')
  const [error, setError] = useState<string | null>(null)
  const token = searchParams.get('token') ?? ''

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setError(null)
    try {
      const result = await acceptInvite(token, password)
      await hydrate(undefined, null, {
        role: result.role ?? null,
        roles: result.roles ?? null,
      })
      navigate('/dashboard/bookings', { replace: true })
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unable to accept invite')
    }
  }

  return (
    <div className="flex h-screen items-center justify-center bg-[#f7f4ee] px-4">
      <div className="w-full max-w-sm space-y-4 rounded-2xl border border-[#e0dacc] bg-white p-6 shadow-sm">
        <h1 className="text-center text-2xl font-semibold text-[#385a3f]">Accept your invite</h1>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="space-y-2">
            <label className="text-sm font-medium text-[#385a3f]" htmlFor="password">
              Set a password
            </label>
            <input
              id="password"
              type="password"
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              required
              className="flex h-10 w-full rounded-lg border border-[#d7d0bf] px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#385a3f]/60"
            />
          </div>
          {error ? <p className="text-sm text-red-600">{error}</p> : null}
          <button
            type="submit"
            className="w-full rounded-lg bg-[#385a3f] py-2 text-sm font-semibold text-white transition hover:bg-[#4d7352]"
          >
            Accept invite
          </button>
        </form>
      </div>
    </div>
  )
}
