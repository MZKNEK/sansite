// Makes the crop of the card off the page, so moving it stays smooth while the
// real preview is being rendered. { forget: url } drops the pixels of a
// picture no longer shown (null: of all).
import { renderCrop, forgetPixels } from './render.js';

self.onmessage = async (e) => {
  if ('forget' in e.data) {
    forgetPixels(e.data.forget);
    return;
  }
  const { id, job } = e.data;
  try {
    const img = await renderCrop(job);
    self.postMessage({ id, img }, [img.data.buffer]);
  } catch (err) {
    self.postMessage({ id, error: String(err?.message || err) });
  }
};
