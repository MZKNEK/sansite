// The card as a lossless WebP for the gallery: libwebp in WebAssembly
// (@jsquash/webp), loaded the first time a card is saved. The browser's own
// canvas.toBlob('image/webp') is lossy (and in Safari not there at all), and
// the gallery would otherwise change the PNG to a lossy WebP in the background.
// exact keeps even the colour under the transparent pixels, so the file has
// the very pixels of the card. In lossless WebP quality is not the look but
// how hard it packs: 95 with method 5 comes within half a percent of the
// strongest (method 6, quality 100) in a tenth of its time.
export async function losslessWebp(imageData) {
  const { default: encode } = await import('@jsquash/webp/encode')
  const buffer = await encode(imageData, { lossless: 1, exact: 1, quality: 95, method: 5, near_lossless: 100 })
  return new Blob([buffer], { type: 'image/webp' })
}
