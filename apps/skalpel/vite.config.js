import { defineConfig } from 'vite'
import { svelte } from '@sveltejs/vite-plugin-svelte'
import { cropperConfig, svelteOptions } from '../shared/vite.mjs'

export default defineConfig(cropperConfig('skalpel', {
  plugins: [svelte(svelteOptions)],
}))
