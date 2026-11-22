import type {
  PosExtensionMount,
  PosExtensionRegistration,
} from '@/modules/pos/contracts/PosExtension.ts'

class PosExtensionRegistry {
  private static instance: PosExtensionRegistry
  private items: PosExtensionRegistration[] = []

  static getInstance(): PosExtensionRegistry {
    if (!PosExtensionRegistry.instance) {
      PosExtensionRegistry.instance = new PosExtensionRegistry()
    }

    return PosExtensionRegistry.instance
  }

  register(item: PosExtensionRegistration): void {
    this.items = this.items.filter((existing) => existing.key !== item.key)
    this.items.push(item)
  }

  all(mount: PosExtensionMount): PosExtensionRegistration[] {
    return this.items.filter((item) => item.mount === mount)
  }
}

export const posExtensionRegistry = PosExtensionRegistry.getInstance()
