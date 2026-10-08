import React, { useState } from "react"
import { useForm } from "@inertiajs/react"
import { Wallet, ArrowRight, Activity } from "lucide-react"
import { Modal } from "@/components/tailadmin/Modal"
import { Button } from "@/components/tailadmin/Button"
import Input from "@/components/tailadmin/form/input/InputField"
import Label from "@/components/tailadmin/form/Label"
import { formatIDR } from "@/lib/utils"

interface Bank {
  id?: number
  bank_name: string
  account_number: string
  account_name: string
}

interface TopupModalProps {
  isOpen: boolean
  onClose: () => void
  currentSaldo?: number
  banks?: Bank[]
  timeoutMinutes?: number
}

export const TopupModal: React.FC<TopupModalProps> = ({
  isOpen,
  onClose,
  currentSaldo = 0,
  timeoutMinutes = 5,
}) => {
  const [amount, setAmount] = useState("")

  const { setData, post, processing, errors, reset } = useForm<{
    amount: string
    bank: string
  }>({
    amount: "",
    bank: "QRIS",
  })

  const quickAmounts = [20000, 50000, 100000, 200000, 500000]

  const handleAmountSelect = (val: number) => {
    const s = String(val)
    setAmount(s)
    setData("amount", s)
  }

  const handleClose = () => {
    reset()
    setAmount("")
    onClose()
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    post("/topup/store", {
      preserveScroll: true,
      onSuccess: () => {
        handleClose()
      },
    })
  }

  return (
    <Modal
      isOpen={isOpen}
      onClose={handleClose}
      title="Isi Saldo Master Wallet"
      description="Pilih nominal dan jalur pembayaran untuk isi saldo instan"
      maxWidth="lg"
    >
      <form onSubmit={handleSubmit} className="space-y-4">
        {/* Current Saldo Card */}
        <div className="rounded-xl border border-gray-200 bg-gray-50/50 p-3.5 dark:border-gray-800 dark:bg-white/[0.02] flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-500/10 text-brand-500 font-bold">
              <Wallet className="h-5 w-5" />
            </div>
            <div>
              <span className="text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider block">
                Saldo Anda Saat Ini
              </span>
              <span className="text-base font-black font-mono text-gray-900 dark:text-white">
                {formatIDR(Number(currentSaldo))}
              </span>
            </div>
          </div>
          <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-600 text-white uppercase">
            AKTIF
          </span>
        </div>

        {/* Amount Input */}
        <div>
          <div className="flex items-center justify-between mb-1">
            <Label htmlFor="topup_amount" className="text-xs font-bold text-gray-900 dark:text-white">
              Nominal Topup (Rp) <span className="text-rose-500">*</span>
            </Label>
            <span className="text-[11px] text-gray-500 dark:text-gray-400">
              Min. <strong className="text-brand-500 font-bold">Rp 20.000</strong>
            </span>
          </div>
          <Input
            id="topup_amount"
            type="number"
            value={amount}
            onChange={(e) => {
              setAmount(e.target.value)
              setData("amount", e.target.value)
            }}
            placeholder="20000"
            className="font-mono font-bold text-base h-11"
            required
          />
          {errors.amount && (
            <p className="mt-1 text-xs text-rose-500 font-medium">{errors.amount}</p>
          )}

          {/* Quick Nominal Chips */}
          <div className="flex flex-wrap gap-1.5 pt-2">
            {quickAmounts.map((q) => (
              <button
                key={q}
                type="button"
                onClick={() => handleAmountSelect(q)}
                className={`rounded-lg border px-2.5 py-1 text-xs font-mono font-bold transition cursor-pointer ${
                  amount === String(q)
                    ? "border-brand-500 bg-brand-500 text-white"
                    : "border-gray-200 bg-white hover:bg-gray-50 text-gray-700 dark:border-gray-800 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                }`}
              >
                {formatIDR(q)}
              </button>
            ))}
          </div>
        </div>

        {/* Submit */}
        <div className="pt-2 flex items-center justify-end gap-2">
          <Button
            type="button"
            variant="outline"
            onClick={handleClose}
            className="text-xs"
          >
            Batal
          </Button>
          <Button
            type="submit"
            disabled={processing || !amount || Number(amount) < 1000}
            className="bg-brand-500 hover:bg-brand-600 text-white text-xs"
          >
            {processing ? (
              "Memproses..."
            ) : (
              <>
                <Activity className="h-4 w-4 mr-1.5" />
                <span>Lanjutkan Pembayaran</span>
                <ArrowRight className="h-4 w-4 ml-1.5" />
              </>
            )}
          </Button>
        </div>
      </form>
    </Modal>
  )
}
