import { defineConfig } from 'vite'
import { cropperConfig } from '../shared/vite.js'

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

export default defineConfig(cropperConfig('uskalpel', {
  plugins: [dropUnusedOrt],
  optimizeDeps: {
    exclude: ['@imgly/background-removal', 'onnxruntime-web'],
  },
}))
