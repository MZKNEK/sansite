import { defineConfig } from 'vite'
import { svelte } from '@sveltejs/vite-plugin-svelte'

// served by the site at /skalpel/; the build goes straight into the site's
// skalpel/ folder, which is committed and deployed with the rest of the site
export default defineConfig({
  plugins: [svelte()],
  base: '/skalpel/',
  build: {
    outDir: '../../skalpel',
    emptyOutDir: true,
  },
})
