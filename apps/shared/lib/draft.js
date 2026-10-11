// A draft of an editor's work kept in the browser, so leaving the page for a
// Discord login, a reload or a closed tab does not lose it: the editor's
// settings and crops, and its pictures, a link as it is and a picture of the
// visitor's own (a file read as data:, a blob: the app made) as the picture
// itself. It stays only in this browser (IndexedDB), for DRAFT_DAYS.
//
// The settings are written a moment after the last change (a crop moves
// often), a picture only when another one takes its place; before a link
// leaves the page (leaveWithDrafts()) and when the page is hidden what waits
// is written at once.

export const DRAFT_DAYS = 7
const DAY = 86400000

// every keeper of the page, for writing what waits before it goes
const keepers = new Set()

/**
 * The draft of one app.
 * @param {string} app - its name, the key of its draft
 * @param {object} [options]
 * @param {number} [options.delay] - ms after the last change the settings wait
 * @param {object} [options.store] - where it is kept (indexedDbStore() by default)
 */
export function draftKeeper(app, { delay = 400, store = null } = {}) {
  let backend = store
  const db = () => (backend ??= indexedDbStore())
  // the picture of each slot as it was last written
  const written = new Map()
  let waiting = null
  let timer = 0
  let writing = Promise.resolve()
  let cleared = 0
  // with no draft read, what the editor starts with (the first save) is not
  // kept: only once something differs from it, so an editor opened and left
  // as it was does not come back "restored"
  let fresh = false
  let start = null

  const pictureKey = (slot) => `${app}:${slot}`

  async function write({ settings, pictures }) {
    const generation = cleared
    const ops = []
    const nowWritten = []
    for (const [slot, src] of Object.entries(pictures)) {
      if ((src || null) === (written.get(slot) ?? null)) continue
      if (!src) {
        ops.push(['pictures', 'delete', pictureKey(slot)])
      } else if (/^(?:data|blob):/.test(src)) {
        // gone meanwhile (another picture took its place): the newer save writes that one
        const blob = await fetch(src).then((res) => res.blob(), () => null)
        if (!blob) return
        ops.push(['pictures', 'put', pictureKey(slot), { blob }])
      } else {
        ops.push(['pictures', 'put', pictureKey(slot), { src }])
      }
      nowWritten.push([slot, src || null])
    }
    const slots = Object.fromEntries(Object.entries(pictures).map(([slot, src]) => [slot, !!src]))
    ops.push(['state', 'put', app, { settings, pictures: slots, savedAt: Date.now() }])
    // a clear while the pictures were being read wins
    if (generation !== cleared) return
    await (await db()).write(ops)
    for (const [slot, src] of nowWritten) written.set(slot, src)
  }

  // what waits written now, after what is being written
  function flush() {
    clearTimeout(timer)
    const next = waiting
    waiting = null
    if (next) writing = writing.then(() => write(next)).catch(() => {})
    return writing
  }

  const keeper = {
    /**
     * The draft kept, or null: { settings, pictures, savedAt }, each picture
     * { src, own } (own: a blob: address made for it, the app's to let go of).
     */
    async load() {
      try {
        const kept = await db()
        const state = await kept.get('state', app)
        if (!state || !(state.savedAt > Date.now() - DRAFT_DAYS * DAY)) {
          if (state) await keeper.clear()
          fresh = true
          return null
        }
        const pictures = {}
        for (const [slot, has] of Object.entries(state.pictures || {})) {
          const picture = has ? await kept.get('pictures', pictureKey(slot)) : null
          if (picture?.blob) pictures[slot] = { src: URL.createObjectURL(picture.blob), own: true }
          else if (picture?.src) pictures[slot] = { src: picture.src, own: false }
          written.set(slot, pictures[slot]?.src ?? null)
        }
        return { settings: state.settings || {}, pictures, savedAt: state.savedAt }
      } catch {
        fresh = true
        return null
      }
    },

    // keeps the settings (plain values) and the pictures ({ slot: src or null }) a moment later
    save(settings, pictures) {
      if (fresh) {
        const now = JSON.stringify([settings, pictures])
        start ??= now
        if (now === start) return
        fresh = false
      }
      waiting = { settings, pictures }
      clearTimeout(timer)
      timer = setTimeout(flush, delay)
    },

    flush,

    // the draft gone, also one being written
    async clear() {
      cleared++
      clearTimeout(timer)
      waiting = null
      await writing
      written.clear()
      fresh = true
      start = null
      try {
        const kept = await db()
        const state = await kept.get('state', app)
        await kept.write([
          ['state', 'delete', app],
          ...Object.keys(state?.pictures || {}).map((slot) => ['pictures', 'delete', pictureKey(slot)]),
        ])
      } catch {
        // nothing kept to clear
      }
    },

    // no longer kept for the page's leaving (the app went)
    close() {
      flush()
      keepers.delete(keeper)
    },
  }
  keepers.add(keeper)
  return keeper
}

// what every draft of the page has waiting, written
export function flushDrafts() {
  return Promise.all([...keepers].map((keeper) => keeper.flush()))
}

// A click on a link that leaves the page (the Discord login): the drafts are
// written first, for a second and a half at most, then the link is followed.
export function leaveWithDrafts(e) {
  if (!keepers.size || e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return
  const href = e.currentTarget.href
  e.preventDefault()
  Promise.race([flushDrafts(), new Promise((resolve) => setTimeout(resolve, 1500))])
    .finally(() => { location.href = href })
}

if (typeof document !== 'undefined')
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden') flushDrafts()
  })

// IndexedDB as the drafts' store: get(store, key) and write([[store, 'put'
// or 'delete', key, value], ...]) in one transaction
export function indexedDbStore(name = 'sanakan-drafts') {
  const open = new Promise((resolve, reject) => {
    const req = indexedDB.open(name, 1)
    req.onupgradeneeded = () => {
      req.result.createObjectStore('state')
      req.result.createObjectStore('pictures')
    }
    req.onsuccess = () => resolve(req.result)
    req.onerror = () => reject(req.error)
  })
  return open.then((db) => ({
    get(store, key) {
      return new Promise((resolve, reject) => {
        const req = db.transaction(store).objectStore(store).get(key)
        req.onsuccess = () => resolve(req.result ?? null)
        req.onerror = () => reject(req.error)
      })
    },
    write(ops) {
      return new Promise((resolve, reject) => {
        const tx = db.transaction(['state', 'pictures'], 'readwrite')
        for (const [store, op, key, value] of ops) {
          if (op === 'put') tx.objectStore(store).put(value, key)
          else tx.objectStore(store).delete(key)
        }
        tx.oncomplete = () => resolve()
        tx.onerror = tx.onabort = () => reject(tx.error)
      })
    },
  }))
}

// an in-memory store with the same calls, for the tests
export function memoryStore() {
  const data = { state: new Map(), pictures: new Map() }
  return Promise.resolve({
    data,
    async get(store, key) {
      return data[store].get(key) ?? null
    },
    async write(ops) {
      for (const [store, op, key, value] of ops) {
        if (op === 'put') data[store].set(key, value)
        else data[store].delete(key)
      }
    },
  })
}
