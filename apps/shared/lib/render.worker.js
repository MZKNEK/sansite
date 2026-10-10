// Makes the crop of the card off the page, so moving it stays smooth while the
// real preview is being rendered.
import { renderCrop } from './render.js';

self.onmessage = async (e) => {
  const { id, job } = e.data;
  try {
    const img = await renderCrop(job);
    self.postMessage({ id, img }, [img.data.buffer]);
  } catch (err) {
    self.postMessage({ id, error: String(err?.message || err) });
  }
};
