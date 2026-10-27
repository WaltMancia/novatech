import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react'
import { api } from './api'
import { useAuth } from './auth'
import type { Cart } from './types'

const EMPTY: Cart = { items: [], totalCents: 0 }

type CartState = {
  cart: Cart
  count: number
  add: (productId: number, quantity: number) => Promise<void>
  setQuantity: (productId: number, quantity: number) => Promise<void>
  remove: (productId: number) => Promise<void>
  refresh: () => Promise<void>
}

const CartContext = createContext<CartState | null>(null)

/** El carrito vive en la sesión del servidor; aquí solo se refleja (los precios nunca los decide el cliente). */
export function CartProvider({ children }: { children: ReactNode }) {
  const { user } = useAuth()
  const [cart, setCart] = useState<Cart>(EMPTY)

  const refresh = useCallback(async () => {
    setCart(user ? await api.get<Cart>('/cart') : EMPTY)
  }, [user])

  useEffect(() => {
    refresh().catch(() => setCart(EMPTY))
  }, [refresh])

  const value = useMemo<CartState>(
    () => ({
      cart,
      count: cart.items.reduce((n, i) => n + i.quantity, 0),
      add: async (productId, quantity) => setCart(await api.post<Cart>('/cart/items', { productId, quantity })),
      setQuantity: async (productId, quantity) => setCart(await api.put<Cart>(`/cart/items/${productId}`, { quantity })),
      remove: async (productId) => setCart(await api.delete<Cart>(`/cart/items/${productId}`)),
      refresh,
    }),
    [cart, refresh],
  )
  return <CartContext.Provider value={value}>{children}</CartContext.Provider>
}

export function useCart(): CartState {
  const ctx = useContext(CartContext)
  if (!ctx) throw new Error('useCart debe usarse dentro de <CartProvider>')
  return ctx
}
