import { renderCrop } from './render.js'

const createImage = (url) =>
  new Promise((resolve, reject) => {
    const image = new Image()
    image.addEventListener('load', () => resolve(image))
    image.addEventListener('error', (error) => reject(error))
    image.setAttribute('crossOrigin', 'anonymous')
    image.src = url
  })

const createBlobUrl = (canvas) =>
  new Promise((resolve, reject) => {
    canvas.toBlob((file) => {
      if (!file) reject(new Error('Failed to create blob from canvas'))
      else resolve(URL.createObjectURL(file))
    }, 'image/png')
  })

// the crop is made in a worker when the browser has one, so the page does not
// stop while the real preview renders; otherwise on the page, with the same code
let worker = null
let workerFailed = false
let lastId = 0
const pending = new Map()

function getWorker() {
  if (worker || workerFailed) return worker
  try {
    worker = new Worker(new URL('./render.worker.js', import.meta.url), { type: 'module' })
    worker.onmessage = (e) => {
      const { id, img, error } = e.data
      const job = pending.get(id)
      pending.delete(id)
      if (error) job?.reject(new Error(error))
      else job?.resolve(img)
    }
    worker.onerror = () => {
      workerFailed = true
      worker = null
      for (const job of pending.values()) job.fallback()
      pending.clear()
    }
  } catch {
    workerFailed = true
  }
  return worker
}

function render(job) {
  // the worker resolves addresses against its own file, so they go there absolute
  job = { ...job, src: new URL(job.src, location.href).href, mask: job.mask && new URL(job.mask, location.href).href }
  const w = getWorker()
  if (!w) return renderCrop(job)
  return new Promise((resolve, reject) => {
    const id = ++lastId
    pending.set(id, { resolve, reject, fallback: () => renderCrop(job).then(resolve, reject) })
    w.postMessage({ id, job })
  })
}

/**
 * The crop exactly as the cropper shows it, in % of the picture. The cropper's
 * own numbers use the picture's size rounded to whole pixels, which shifts the
 * crop by up to a pixel (more when zoomed in); the screen has the exact one.
 * @param {HTMLElement} container - element holding the cropper
 * @returns {{x: number, y: number, width: number, height: number} | null}
 */
export function cropOnScreen(container) {
  const img = container?.querySelector('img')
  const area = container?.querySelector('.svelte-easy-crop-area')
  if (!img || !area) return null
  const i = img.getBoundingClientRect(), a = area.getBoundingClientRect()
  if (!i.width || !i.height) return null
  return {
    x: (a.x - i.x) / i.width * 100,
    y: (a.y - i.y) / i.height * 100,
    width: a.width / i.width * 100,
    height: a.height / i.height * 100,
  }
}

export async function getMirroredImg(imageSrc) {
  const image = await createImage(imageSrc)
  const canvas = document.createElement('canvas')
  const ctx = canvas.getContext('2d')
  ctx.imageSmoothingEnabled = false

  canvas.width = image.width
  canvas.height = image.height

  ctx.scale(-1, 1)
  ctx.drawImage(image, 0, 0, image.width * -1, image.height)

  return createBlobUrl(canvas)
}

/**
 * The picture of the card as the saved file and the real preview show it:
 * 448x650 in Skalpelator, 475x667 with a mask in USkalpelator.
 * @param {string} imageSrc - picture address
 * @param {Object} percent - crop from svelte-easy-crop, in % of the picture
 * @param {{width: number, height: number, mask?: string, sharpen?: number}} card
 *   size of the result; mask: PNG of that size, its alpha cuts the picture;
 *   sharpen: extra sharpening, 0 = none
 */
export async function getCroppedImg(imageSrc, percent, { width, height, mask = null, sharpen = 0 }) {
  const img = await render({ src: imageSrc, percent, width, height, mask, sharpen })
  const canvas = document.createElement('canvas')
  canvas.width = img.width
  canvas.height = img.height
  canvas.getContext('2d').putImageData(img, 0, 0)
  return createBlobUrl(canvas)
}
