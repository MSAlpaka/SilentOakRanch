import { useState } from 'react'
import { useNavigate } from 'react-router-dom'

import { Card, Button, Input, Label } from '@/components/ui'
import { useAuth } from '@/hooks/useAuth'

export default function Login() {
  const { login, loading } = useAuth()
  const navigate = useNavigate()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState('')

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const ok = await login(email, password)
    if (ok) {
      navigate('/dashboard/bookings', { replace: true })
    } else {
      setError('Invalid credentials')
    }
  }

  return (
    <div className="flex h-screen items-center justify-center bg-[#f7f4ee] px-4">
      <Card className="w-full max-w-sm space-y-4 p-6">
        <h2 className="text-center text-2xl font-semibold text-[#385a3f]">Ranch Login</h2>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="email">E-Mail</Label>
            <Input
              id="email"
              type="email"
              value={email}
              onChange={(event) => {
                setEmail(event.target.value)
                if (error) setError('')
              }}
              required
              placeholder="you@example.com"
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="password">Passwort</Label>
            <Input
              id="password"
              type="password"
              value={password}
              onChange={(event) => {
                setPassword(event.target.value)
                if (error) setError('')
              }}
              required
            />
          </div>
          {error ? <p className="text-sm text-red-600">{error}</p> : null}
          <Button
            type="submit"
            disabled={loading}
            className="w-full bg-[#385a3f] text-white transition hover:bg-[#4d7352]"
          >
            {loading ? 'Anmelden…' : 'Login'}
          </Button>
        </form>
      </Card>
    </div>
  )
}
