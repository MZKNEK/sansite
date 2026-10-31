import { fileURLToPath } from 'node:url'
import { defineConfig } from 'vite'
import { svelte } from '@sveltejs/vite-plugin-svelte'
import { cropperConfig, svelteOptions } from '../shared/vite.mjs'

// onnxruntime-web names its WebAssembly and the .mjs that loads it, so Vite
// copies them (tens of MB) into the build; nothing asks for them, as the
// background removal gives onnxruntime the site's own copies from /models/
// (src/lib/background.js, tools/bg-models.mjs)
const dropUnusedOrt = {
  name: 'drop-unused-ort',
  generateBundle(_, bundle) {
    for (const name of Object.keys(bundle))
      if (/\/ort[^/]*\.(?:wasm|mjs)$/.test(name)) delete bundle[name]
  },
}

// besides the app, its background removal as a module of its own with its
// exports, assets/cutout-<hash>.js, which Profilator imports for its
// overlays (profilator/index.php finds it by that name)
const entry = (path) => fileURLToPath(new URL(path, import.meta.url))

export default defineConfig(cropperConfig('uskalpel', {
  plugins: [svelte(svelteOptions), dropUnusedOrt],
  build: {
    rolldownOptions: {
      input: { index: entry('./index.html'), cutout: entry('./src/lib/background.js') },
      preserveEntrySignatures: 'exports-only',
    },
  },
  resolve: {
    // onnxruntime-web on its own is the build with WebGPU, whose WebAssembly
    // is twice the size; the background removal runs on the CPU, for IMG.LY's
    // library too, so both get the build without it, whose WebAssembly
    // /models/ has
    alias: [{ find: /^onnxruntime-web$/, replacement: 'onnxruntime-web/wasm' }],
  },
  optimizeDeps: {
    exclude: ['@imgly/background-removal', 'onnxruntime-web'],
  },
}))
