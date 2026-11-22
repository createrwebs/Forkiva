import { type RouteLocationRaw, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import * as auth from '@/modules/auth/api/auth.api.ts'
import * as twoFactor from '@/modules/auth/api/twoFactor.api.ts'
import type { TwoFactorChallenge } from '@/modules/auth/contracts/TwoFactor.ts'
import { loginWithPasskey as authenticateWithPasskey } from '@/modules/auth/api/passkey.api.ts'
import { useAuthStore } from '@/modules/auth/stores/authStore.ts'
import { getDefaultRoute as resolveDefaultRoute } from '@/modules/auth/utils/defaultRoute.ts'

export function useAuth() {
  const authStore = useAuthStore()
  const router = useRouter()

  const getDefaultRoute = (): RouteLocationRaw => resolveDefaultRoute(authStore, router)

  const login = async (identifier: string, password: string) => {
    const response = await auth.login(identifier, password)
    if (response.data.body.two_factor_required) {
      return response.data.body as TwoFactorChallenge
    }
    authStore.setAuth(response.data.body)
    await router.push(getDefaultRoute())
    return null
  }

  const verifyTwoFactor = async (challengeToken: string, code: string) => {
    const response = await twoFactor.verifyLogin(challengeToken, code)
    authStore.setAuth(response.data.body)
    await router.push(getDefaultRoute())
  }

  const loginWithPasskey = async () => {
    const response = await authenticateWithPasskey()
    authStore.setAuth(response.data.body)
    await router.push(getDefaultRoute())
  }

  const logout = async () => {
    const accountId = authStore.activeAccountId
    try {
      useToast().success((await auth.logout()).data.message)
    } catch {
      /* empty */
    }

    await authStore.logout(false, accountId)
    await (authStore.isAuthenticated
      ? router.push(getDefaultRoute())
      : router.push({ name: 'auth.login' } as unknown as RouteLocationRaw))
  }

  const logoutAll = async () => {
    const tokens = [
      ...new Set(authStore.getAccounts.map((account) => account.token).filter(Boolean)),
    ]
    await Promise.allSettled(tokens.map((token) => auth.logoutByToken(token)))
    await authStore.logoutAll()
    await router.push({ name: 'auth.login' } as unknown as RouteLocationRaw)
  }

  const switchAccount = async (accountId: number) => {
    const switched = authStore.switchAccount(accountId)
    if (!switched) {
      return
    }

    try {
      await auth.check()
      await router.push(getDefaultRoute())
    } catch {
      await authStore.logout(true)
      if (!authStore.isAuthenticated) {
        await router.push({ name: 'auth.login' } as unknown as RouteLocationRaw)
      }
    }
  }

  const goToAddAccount = async () => {
    await router.push({
      name: 'auth.login',
      query: { add_account: '1' },
    } as unknown as RouteLocationRaw)
  }

  const check = async () => {
    try {
      await auth.check()
      return true
    } catch {
      return false
    }
  }

  return {
    store: authStore,
    user: authStore.getUser,
    login,
    verifyTwoFactor,
    loginWithPasskey,
    logout,
    logoutAll,
    switchAccount,
    goToAddAccount,
    check,
    getDefaultRoute,
    can: authStore.can,
    canAny: authStore.canAny,
    hasPermission: authStore.hasPermission,
    hasRole: authStore.hasRole,
    hasAnyRoles: authStore.hasAnyRoles,
  }
}
