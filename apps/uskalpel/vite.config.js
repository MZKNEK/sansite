import { defineConfig } from 'vite'
import { svelte } from '@sveltejs/vite-plugin-svelte'

// onnxruntime-web names its WebAssembly and the .mjs that loads it, so Vite
// copies them (24 MB) into the build; IMG.LY's background removal never asks
// for them, as it points onnxruntime at its own copies on the CDN (publicPath)
const dropUnusedOrt = {
  name: 'drop-unused-ort',
  generateBundle(_, bundle) {
    for (const name of Object.keys(bundle))
      if (/\/ort[^/]*\.(?:wasm|mjs)$/.test(name)) delete bundle[name]
  },
}

// served by the site at /uskalpel/; the build goes straight into the site's
// uskalpel/ folder, which is committed and deployed with the rest of the site
export default defineConfig({
  plugins: [svelte(), dropUnusedOrt],
  base: '/uskalpel/',
  build: {
    outDir: '../../uskalpel',
    emptyOutDir: true,
  },
  optimizeDeps: {
    exclude: ['@imgly/background-removal', 'onnxruntime-web'],
  },
})
