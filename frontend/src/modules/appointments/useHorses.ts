import { useEffect, useState } from 'react'
import api from '../../axios'
import type { HorseSummary } from '../../api/appointments'

interface HorseState {
  horses: HorseSummary[]
  loading: boolean
  error?: string
}

const initialState: HorseState = {
  horses: [],
  loading: false,
}

export function useHorses() {
  const [{ horses, loading, error }, setState] = useState<HorseState>(initialState)

  useEffect(() => {
    let active = true
    setState({ horses: [], loading: true })

    api
      .get<HorseSummary[]>('/horses')
      .then(res => {
        if (!active) return
        setState({ horses: res.data, loading: false })
      })
      .catch(() => {
        if (!active) return
        setState({ horses: [], loading: false, error: 'horses.error_load' })
      })

    return () => {
      active = false
    }
  }, [])

  return { horses, loading, error }
}
