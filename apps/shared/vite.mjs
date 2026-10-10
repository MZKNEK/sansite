// The Vite configuration both croppers share: served by the site at /<name>/,
// built straight into the site's <name>/ folder, which is committed and
// deployed with the rest of the site. `npm run dev` takes what belongs to the
// site itself (its fonts, icons, account.php, the gallery) from the site
// running locally (tools/preview.sh, see the README), or from the address
// in SANAKAN_SITE. The app passes its plugins, Svelte's among them, as this
// file has no packages of its own to import them from.

// Options for Svelte's plugin: the scoped class of a component's styles
// (svelte-<hash>) from its name and its CSS, not from its path, which for
// apps/shared/ differs between Windows and Linux, so CI builds the same files
export const svelteOptions = {
  compilerOptions: {
    cssHash: ({ hash, css, name }) => `svelte-${hash(name + css)}`,
  },
}

export function cropperConfig(name, rest = {}) {
  const site = process.env.SANAKAN_SITE || 'http://127.0.0.1:8765'
  const fromSite = ['/css/', '/fonts/', '/js/', '/favicon', '/apple-touch-icon', '/account.php', '/i/', '/__login', '/__preview.css']
  return {
    base: `/${name}/`,
    build: {
      outDir: `../../${name}`,
      emptyOutDir: true,
    },
    server: {
      // the code both croppers share is in apps/shared/, outside their folders
      fs: { allow: ['..'] },
      // the site's paths, also with the app's base in front, which the dev
      // server puts on the scripts and icons of index.html (the build does not)
      proxy: Object.fromEntries(fromSite.flatMap((path) => [
        [path, { target: site, changeOrigin: true }],
        [`/${name}${path}`, { target: site, changeOrigin: true, rewrite: (url) => url.slice(name.length + 1) }],
      ])),
    },
    ...rest,
  }
}
