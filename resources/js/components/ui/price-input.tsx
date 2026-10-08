import * as React from "react"
import { Input } from "@/components/ui/input"
import { formatPriceInput, stripPriceInput } from "@/lib/utils"

interface PriceInputProps extends Omit<React.InputHTMLAttributes<HTMLInputElement>, "value" | "onChange" | "type"> {
  value: string
  onChange: (raw: string) => void
}

/**
 * Input harga dengan auto-format ribuan saat mengetik.
 * Menyimpan nilai mentah (tanpa pemisah): ketik 10000 → tampil "10.000", value tetap "10000".
 */
export function PriceInput({ value, onChange, placeholder, className, ...props }: PriceInputProps) {
  return (
    <Input
      type="text"
      inputMode="numeric"
      autoComplete="off"
      value={formatPriceInput(value)}
      onChange={(e) => onChange(stripPriceInput(e.target.value))}
      placeholder={placeholder}
      className={className}
      {...props}
    />
  )
}
