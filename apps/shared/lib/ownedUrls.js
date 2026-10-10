// The blob: addresses an app made itself (a crop, a mirrored picture, a
// picture without its background), let go once they are no longer shown, and
// only those: a link, a picture of the gallery or a file read as data: is not
// the app's to revoke. onRelease hears of each one let go, so the renderer can
// drop the pixels it decoded from it.
export function ownedUrls(onRelease = () => {}) {
  const owned = new Set()
  const release = (url) => {
    if (!url || !owned.delete(url)) return
    URL.revokeObjectURL(url)
    onRelease(url)
  }
  return {
    // the address, now the app's own
    own(url) {
      owned.add(url)
      return url
    },
    release,
    // $old gives way to $next: the old one goes when it was ours and is not the new one
    replace(old, next) {
      if (old !== next) release(old)
      return next
    },
    releaseAll() {
      for (const url of [...owned]) release(url)
    },
    get size() {
      return owned.size
    },
  }
}
