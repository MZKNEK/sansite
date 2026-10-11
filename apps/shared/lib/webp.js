// The card as a WebP for the gallery: libwebp in WebAssembly (@jsquash/webp),
// loaded the first time a card is saved. Lossy at quality 98, which keeps the
// card looking as it is at a fraction of a lossless file's size; the browser's
// own canvas.toBlob('image/webp') lets nobody pick how (and in Safari is not
// there at all). Method 6 packs hardest, which for a card takes a moment
// only; sharp_yuv keeps the colours of thin edges (the lines of a drawing)
// from bleeding when the colour is halved. The alpha stays lossless.
export async function cardWebp(imageData) {
  const { default: encode } = await import('@jsquash/webp/encode')
  const buffer = await encode(imageData, { quality: 98, method: 6, use_sharp_yuv: 1, alpha_quality: 100 })
  return new Blob([buffer], { type: 'image/webp' })
}
