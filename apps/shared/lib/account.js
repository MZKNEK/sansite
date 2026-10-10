// The Discord account of the site, as the croppers see it: account.php through
// the site's own js/account.js (linked in their index.html), which puts the
// account menu of the top right corner into the header, and saving a card into
// the account's folder in the gallery (the "cropper" action of i/, which puts
// it in the Skalpelator folder there).
import { get, writable } from 'svelte/store'

// what account.php said ({menu, own, csrf, ...} logged in, {login} not), or
// null until it answered or when the site cannot be asked
export const account = writable(null)

const site = () => window.SanakanAccount

// puts the account menu into box and keeps what account.php said
export function loadAccount(box) {
  if (!site()) return Promise.resolve(null)
  return site().load(box).then((me) => {
    account.set(me)
    return me
  })
}

// a Discord login that comes back to this page
export function loginUrl() {
  return site() ? site().loginUrl() : '/account.php?login'
}

/**
 * Saves a card into the account's folder in the gallery.
 * @param {Blob} blob - the card (a lossless WebP from the croppers)
 * @param {string} name - its file name
 * @returns {Promise<{message: string, url: string, folder: string}>} what the
 *   gallery said: url is the picture's link (it opens without a login too),
 *   folder the folder in the gallery
 */
export async function saveToGallery(blob, name) {
  const me = get(account)
  const form = new FormData()
  form.append('action', 'cropper')
  form.append('csrf', me?.csrf ?? '')
  form.append('file', blob, name)

  let res
  try {
    res = await fetch('/i/', { method: 'POST', body: form, credentials: 'same-origin' })
  } catch {
    throw new Error('Nie udało się połączyć z galerią, spróbuj jeszcze raz.')
  }
  const answer = await res.json().catch(() => null)
  if (!answer) throw new Error(`Galeria nie odpowiedziała (HTTP ${res.status}).`)
  if (!answer.ok) throw new Error(answer.message)
  return answer
}

// a file name with the time it was saved, so cards never take each other's name
export function cardName(prefix, ext = 'png') {
  const d = new Date()
  const two = (n) => String(n).padStart(2, '0')
  return `${prefix}-${d.getFullYear()}-${two(d.getMonth() + 1)}-${two(d.getDate())}-${two(d.getHours())}${two(d.getMinutes())}${two(d.getSeconds())}.${ext}`
}
