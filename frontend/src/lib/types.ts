export type Role = 'customer' | 'admin'

export type Product = {
  id: number
  sku: string
  name: string
  description: string
  priceCents: number
  stock: number
  imagePath: string | null
}
export type AdminProduct = Product & { active: boolean }

export type CartItem = {
  productId: number
  name: string
  unitPriceCents: number
  quantity: number
  subtotalCents: number
  stock: number
}
export type Cart = { items: CartItem[]; totalCents: number }

export type OrderStatus = 'pending' | 'paid' | 'failed' | 'cancelled'
export type OrderItem = { productId: number; name: string; quantity: number; unitPriceCents: number; subtotalCents: number }
export type Order = {
  id: number
  status: OrderStatus
  totalCents: number
  paymentRef: string | null
  createdAt: string
  items: OrderItem[]
}
export type AdminOrder = Order & { userId: number }

export type AdminUser = { id: number; email: string; name: string; role: Role; locked: boolean }
export type AuditEvent = {
  id: number
  userId: number | null
  action: string
  ip: string
  metadata: Record<string, unknown> | null
  createdAt: string
}
export type Meta = { page: number; perPage: number; total: number }
