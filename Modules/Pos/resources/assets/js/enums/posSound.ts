export enum PosSound {
  Tap = 'tap',
  Pop = 'pop',
  Tick = 'tick',
  Remove = 'remove',
  Open = 'open',
  Success = 'success',
  Cash = 'cash',
  Warning = 'warning',
  Error = 'error',
}

export enum PosSoundAction {
  Interaction = 'interaction',
  ItemAdded = 'item_added',
  ItemUpdated = 'item_updated',
  ItemRemoved = 'item_removed',
  OrderSubmitted = 'order_submitted',
  PaymentCompleted = 'payment_completed',
  Cancelled = 'cancelled',
  Error = 'error',
}

export const POS_SOUND_DEFAULTS: Record<PosSoundAction, PosSound> = {
  [PosSoundAction.Interaction]: PosSound.Tap,
  [PosSoundAction.ItemAdded]: PosSound.Pop,
  [PosSoundAction.ItemUpdated]: PosSound.Tick,
  [PosSoundAction.ItemRemoved]: PosSound.Remove,
  [PosSoundAction.OrderSubmitted]: PosSound.Success,
  [PosSoundAction.PaymentCompleted]: PosSound.Cash,
  [PosSoundAction.Cancelled]: PosSound.Warning,
  [PosSoundAction.Error]: PosSound.Error,
}
