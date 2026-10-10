import { svelte } from '@sveltejs/vite-plugin-svelte'

// The Vite configuration both croppers share: served by the site at /<name>/,
// built straight into the site's <name>/ folder, which is committed and
// deployed with the rest of the site. `npm run dev` takes what belongs to the
// site itself (its fonts, icons, account.php, the gallery) from the site
// running locally (php -S 127.0.0.1:8765, see the README), or from the address
// in SANAKAN_SITE.
export function cropperConfig(name, { plugins = [], ...rest } = {}) {
  const site = process.env.SANAKAN_SITE || 'http://127.0.0.1:8765'
  const fromSite = ['/css/', '/fonts/', '/js/', '/favicon', '/apple-touch-icon', '/account.php', '/i/']
  return {
    plugins: [svelte(), ...plugins],
    base: `/${name}/`,
    build: {
      outDir: `../../${name}`,
      emptyOutDir: true,
    },
    server: {
      // the code both croppers share is in apps/shared/, outside their folders
      fs: { allow: ['..'] },
      proxy: Object.fromEntries(fromSite.map((path) => [path, { target: site, changeOrigin: true }])),
    },
    ...rest,
  }
}
