/**
 * Synthesizer Suara & Getaran Haptic saat transaksi pembayaran berhasil.
 */

export function triggerPaymentSuccessFX() {
  if (typeof window === "undefined") return

  // 1. Double Haptic Pulse
  if ("navigator" in window && "vibrate" in navigator) {
    try {
      navigator.vibrate([15, 60, 25])
    } catch (e) {}
  }

  // 2. Web Audio API Chime Synth
  try {
    const AudioCtx = window.AudioContext || (window as any).webkitAudioContext
    if (!AudioCtx) return
    const ctx = new AudioCtx()

    const playTone = (freq: number, start: number, duration: number) => {
      const osc = ctx.createOscillator()
      const gain = ctx.createGain()

      osc.type = "sine"
      osc.frequency.setValueAtTime(freq, ctx.currentTime + start)

      gain.gain.setValueAtTime(0.18, ctx.currentTime + start)
      gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + start + duration)

      osc.connect(gain)
      gain.connect(ctx.destination)

      osc.start(ctx.currentTime + start)
      osc.stop(ctx.currentTime + start + duration)
    }

    // Modern Two-Tone POS Chime (E5 -> A5)
    playTone(659.25, 0, 0.15)
    playTone(880.0, 0.12, 0.35)
  } catch (e) {
    console.error("Audio FX error:", e)
  }
}
