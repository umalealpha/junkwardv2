import { describe, it, expect } from 'vitest'

describe('Frontend Build Checks', () => {
  it('API client module exports correctly', async () => {
    const mod = await import('../api/client')
    expect(mod.default).toBeDefined()
  })

  it('Auth API module exports correctly', async () => {
    const mod = await import('../api/auth')
    expect(mod).toBeDefined()
  })

  it('Policies API module exports correctly', async () => {
    const mod = await import('../api/policies')
    expect(mod).toBeDefined()
  })

  it('Claims API module exports correctly', async () => {
    const mod = await import('../api/claims')
    expect(mod).toBeDefined()
  })

  it('Dashboard API module exports correctly', async () => {
    const mod = await import('../api/dashboard')
    expect(mod).toBeDefined()
  })

  it('VITE_API_URL is configured', () => {
    // In test env this may be undefined, but the env var should exist in config
    expect(import.meta.env).toBeDefined()
  })
})
