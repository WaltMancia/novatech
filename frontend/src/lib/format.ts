const money = new Intl.NumberFormat('es-GT', { style: 'currency', currency: 'GTQ' })
const date = new Intl.DateTimeFormat('es-GT', { dateStyle: 'medium', timeStyle: 'short' })

export const formatMoney = (cents: number): string => money.format(cents / 100)
export const formatDate = (iso: string): string => date.format(new Date(iso))
