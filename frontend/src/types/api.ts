export type Role = 'Admin' | 'Manager' | 'Staff'

export interface User {
  id: number
  name: string
  email: string
  tenant_id: number
  roles: Role[]
}

export interface LoginResponse {
  token: string
  token_type: string
  user: User
}

export interface ProductCategory {
  id: number
  name: string
  slug: string
  parent_id: number | null
  product_count: number | null
  created_at: string
  updated_at: string
}

export interface Product {
  id: number
  category: ProductCategory | null
  sku: string
  name: string
  description: string | null
  barcode: string | null
  unit_of_measure: string
  cost_price: string
  selling_price: string
  margin: number
  margin_percentage: number
  reorder_point: number
  total_stock: number | null
  is_active: boolean
  created_at: string
  updated_at: string
}

export interface Warehouse {
  id: number
  name: string
  code: string
  address_line1: string
  address_line2: string | null
  city: string
  state: string | null
  postal_code: string
  country: string
  contact_name: string | null
  contact_phone: string | null
  contact_email: string | null
  product_count: number | null
  total_stock: number | null
  is_active: boolean
  created_at: string
  updated_at: string
}

export interface ProductStock {
  id: number
  product_id: number
  warehouse: Warehouse
  quantity: number
  updated_at: string
}

export type InventoryStatus = 'out_of_stock' | 'low_stock' | 'in_stock'

export interface InventoryRow {
  id: number
  product: { id: number; name: string; sku: string }
  warehouse: Warehouse
  quantity: number
  reserved_quantity: number
  available_quantity: number
  reorder_point: number
  status: InventoryStatus
  updated_at: string
}

export type StockMovementType =
  | 'received'
  | 'sold'
  | 'adjustment'
  | 'damaged'
  | 'returned'
  | 'transfer_in'
  | 'transfer_out'

export interface StockMovement {
  id: number
  product_id: number
  warehouse_id: number
  type: StockMovementType
  quantity_change: number
  note: string | null
  user_id: number | null
  user: { id: number; name: string } | null
  reference_type: string | null
  reference_id: number | null
  created_at: string
}

export interface Customer {
  id: number
  name: string
  company_name: string | null
  email: string | null
  phone: string | null
  billing_address_line1: string | null
  billing_address_line2: string | null
  billing_city: string | null
  billing_state: string | null
  billing_postal_code: string | null
  billing_country: string | null
  shipping_address_line1: string | null
  shipping_address_line2: string | null
  shipping_city: string | null
  shipping_state: string | null
  shipping_postal_code: string | null
  shipping_country: string | null
  notes: string | null
  order_count: number | null
  total_spent: number | null
  created_at: string
  updated_at: string
}

export type OrderStatus =
  | 'pending'
  | 'confirmed'
  | 'processing'
  | 'shipped'
  | 'delivered'
  | 'cancelled'
  | 'refunded'

export interface OrderItem {
  id: number
  product: Product
  quantity: number
  unit_price: string
  subtotal: string
  reservation_status: 'active' | 'released' | 'fulfilled' | null
}

export interface Order {
  id: number
  order_number: string
  status: OrderStatus
  customer: Customer
  warehouse: Warehouse
  items?: OrderItem[]
  items_count: number | null
  total_amount: string
  notes: string | null
  confirmed_at: string | null
  shipped_at: string | null
  delivered_at: string | null
  cancelled_at: string | null
  refunded_at: string | null
  created_at: string
  updated_at: string
}

export interface AuditLogCauser {
  id: number
  name: string
  email: string
}

export interface AuditLog {
  id: number
  log_name: string | null
  description: string
  event: string | null
  subject_type: string | null
  subject_id: number | null
  causer: AuditLogCauser | null
  attribute_changes: { attributes?: Record<string, unknown>; old?: Record<string, unknown> } | []
  properties: Record<string, unknown> | []
  created_at: string
}

export interface PaginationMeta {
  current_page: number
  from: number | null
  last_page: number
  per_page: number
  to: number | null
  total: number
}

export interface PaginatedResponse<T> {
  data: T[]
  meta: PaginationMeta
}

export interface ApiErrorBody {
  message: string
  errors?: Record<string, string[]>
}
