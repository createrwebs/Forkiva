import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { AuthAccount, User } from '@/modules/auth/contracts/Auth.ts'
import { useAuthStore } from './authStore.ts'
vi.mock('@/modules/cart/services/draftStorage', () => ({
  removeAccountDrafts: vi.fn().mockResolvedValue(undefined),
}))

const storage = vi.hoisted(() => ({ getItems: vi.fn(), setItems: vi.fn(), removeItems: vi.fn() }))
vi.mock('@/modules/core/services/IndexedDbEngine.ts', () => ({ indexedDbEngine: storage }))

function account(id: number): AuthAccount {
  const user: User = {
    id,
    name: `User ${id}`,
    username: `user-${id}`,
    email: '',
    profile_photo_url: '',
    branch_id: null,
    assigned_to_branch: false,
    role: { name: 'admin', display_name: 'Admin', permissions: [] },
  }
  return { user, token: `test-token-${id}` }
}

function saved(accounts: AuthAccount[], active = accounts[0]) {
  return {
    auth_accounts: JSON.stringify(accounts),
    auth_active_account_id: active ? String(active.user.id) : '',
    token: active?.token ?? '',
    user: active ? JSON.stringify(active.user) : '',
  }
}

describe('restoring stored accounts', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.resetAllMocks()
  })

  it('restores an unchanged multi-account session without re-encrypting it', async () => {
    const accounts = [account(1), account(2)]
    storage.getItems.mockResolvedValue(saved(accounts, accounts[1]))
    const auth = useAuthStore()
    await auth.restoreAuth()
    await auth.restoreAuth()

    expect(auth.accounts).toEqual(accounts)
    expect(auth.user?.id).toBe(2)
    expect(auth.token).toBe(accounts[1]?.token)
    expect(storage.setItems).not.toHaveBeenCalled()
  })

  it.each([{}, saved([])])(
    'does not create or rewrite empty guest records (%j)',
    async (values) => {
      storage.getItems.mockResolvedValue(values)
      const auth = useAuthStore()
      await auth.restoreAuth()
      expect(auth.isAuthenticated).toBe(false)
      expect(storage.setItems).not.toHaveBeenCalled()
    },
  )

  it('persists migration from a legacy single account', async () => {
    const legacy = account(3)
    storage.getItems.mockResolvedValue({
      token: legacy.token,
      user: JSON.stringify(legacy.user),
    })
    const auth = useAuthStore()
    await auth.restoreAuth()
    expect(auth.accounts).toEqual([legacy])
    expect(storage.setItems).toHaveBeenCalledExactlyOnceWith(saved([legacy]))
  })

  it('persists repairs to malformed stored accounts', async () => {
    storage.getItems.mockResolvedValue({
      auth_accounts: '{',
      user: '{',
      token: 'invalid-test-token',
    })
    const auth = useAuthStore()
    await auth.restoreAuth()
    expect(auth.isAuthenticated).toBe(false)
    expect(storage.setItems).toHaveBeenCalledExactlyOnceWith(saved([]))
  })

  it('still persists account switches and logs out only the active account', async () => {
    const accounts = [account(1), account(2)]
    storage.getItems.mockResolvedValue(saved(accounts))
    const auth = useAuthStore()
    await auth.restoreAuth()
    expect(auth.switchAccount(2)).toBe(true)
    expect(storage.setItems).toHaveBeenLastCalledWith(saved(accounts, accounts[1]))
    await auth.logout()
    expect(auth.user?.id).toBe(1)
    expect(storage.setItems).toHaveBeenLastCalledWith(saved([accounts[0]!]))
  })
})
