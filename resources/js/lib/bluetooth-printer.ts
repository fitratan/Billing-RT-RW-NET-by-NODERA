/**
 * Utility Cetak Struk Bluetooth Thermal (ESC/POS) via Web Bluetooth API.
 * Mendukung printer thermal 58mm / 80mm Bluetooth di PWA.
 */

interface ReceiptData {
  companyName: string
  invoiceNumber: string
  customerName: string
  packageName: string
  amount: number
  paidAt: string
  collectorName: string
}

export async function printBluetoothReceipt(data: ReceiptData): Promise<boolean> {
  if (typeof window === "undefined" || !("bluetooth" in navigator)) {
    alert("Browser/HP Anda belum mendukung Web Bluetooth. Pastikan menggunakan Chrome/PWA dengan Bluetooth aktif.")
    return false
  }

  try {
    const device = await (navigator as any).bluetooth.requestDevice({
      filters: [{ services: ["000018f0-0000-1000-8000-00805f9b34fb"] }],
      optionalServices: [
        "000018f0-0000-1000-8000-00805f9b34fb",
        "0000ff00-0000-1000-8000-00805f9b34fb",
        "e7810a71-73ae-499d-8c15-faa9aef0c3f2",
      ],
      acceptAllDevices: true,
    })

    const server = await device.gatt?.connect()
    if (!server) throw new Error("Gagal terhubung ke printer GATT server.")

    const services = await server.getPrimaryServices()
    let writeChar: any = null

    for (const service of services) {
      const chars = await service.getCharacteristics()
      for (const char of chars) {
        if (char.properties.write || char.properties.writeWithoutResponse) {
          writeChar = char
          break
        }
      }
      if (writeChar) break
    }

    if (!writeChar) throw new Error("Karakteristik printer tidak ditemukan.")

    const encoder = new TextEncoder()
    const ESC = "\x1B"
    const GS = "\x1D"

    const init = ESC + "@"
    const center = ESC + "a" + "\x01"
    const left = ESC + "a" + "\x00"
    const boldOn = ESC + "E" + "\x01"
    const boldOff = ESC + "E" + "\x00"
    const doubleSize = GS + "!" + "\x11"
    const normalSize = GS + "!" + "\x00"

    const formattedAmount = "Rp " + data.amount.toLocaleString("id-ID")

    let text = init
    text += center + doubleSize + boldOn + (data.companyName || "NODERA ISP") + "\n" + normalSize + boldOff
    text += center + "BUKTI PEMBAYARAN TAGIHAN\n"
    text += center + "--------------------------------\n" + left
    text += `No. Invoice : ${data.invoiceNumber}\n`
    text += `Tanggal     : ${data.paidAt}\n`
    text += `Pelanggan   : ${data.customerName}\n`
    text += `Paket       : ${data.packageName}\n`
    text += `Petugas     : ${data.collectorName}\n`
    text += "--------------------------------\n"
    text += center + "TOTAL LUNAS:\n"
    text += center + doubleSize + boldOn + formattedAmount + "\n" + normalSize + boldOff
    text += center + "--------------------------------\n"
    text += center + "Terima kasih atas pembayaran Anda.\nSimpan struk ini sebagai bukti sah.\n\n\n\n"

    const payload = encoder.encode(text)

    const chunkSize = 512
    for (let i = 0; i < payload.length; i += chunkSize) {
      const chunk = payload.slice(i, i + chunkSize)
      await writeChar.writeValue(chunk)
    }

    return true
  } catch (err: any) {
    console.error("Bluetooth print error:", err)
    if (err.name !== "NotFoundError") {
      alert("Gagal mencetak Bluetooth: " + (err.message || err))
    }
    return false
  }
}
