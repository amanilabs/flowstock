import { clsx, type ClassValue } from 'clsx'
import { twMerge } from 'tailwind-merge'

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}

/** The API returns money as decimal(x,4) strings; always display 2 places. */
export function formatCurrency(value: string | number): string {
  return Number(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

/** Rejects a keystroke that would produce a 3rd+ decimal digit in a currency input. */
const TWO_DECIMAL_PATTERN = /^\d*\.?\d{0,2}$/

export function isValidCurrencyInput(value: string): boolean {
  return value === '' || TWO_DECIMAL_PATTERN.test(value)
}
