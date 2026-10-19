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

export default defineConfig(cropperConfig('uskalpel', {
  plugins: [svelte(svelteOptions), dropUnusedOrt],
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
