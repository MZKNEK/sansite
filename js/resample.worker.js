// The resampling of js/resample.js in a worker, for a page that frames its
// pictures again and again (Profilator): a picture's pixels come once under a
// key, then frames of it are asked for, so the page never waits. The page
// gives the address of resample.js with its ?v= (scripts are cached for a
// year), which a fixed import here would not have.
let resample = null;
const pictures = new Map();

self.onmessage = async ({ data }) => {
  if (data.type === 'init') {
    try {
      resample = (await import(data.url)).resample;
      self.postMessage({ type: 'ready' });
    } catch {
      self.postMessage({ type: 'failed' });
    }
  } else if (data.type === 'picture') {
    pictures.set(data.key, data.pixels);
  } else if (data.type === 'forget') {
    pictures.delete(data.key);
  } else if (data.type === 'frame') {
    const pixels = pictures.get(data.key);
    if (!resample || !pixels) {
      self.postMessage({ type: 'frame', id: data.id, out: null });
      return;
    }
    const out = resample(pixels, data.area, data.width, data.height);
    self.postMessage({ type: 'frame', id: data.id, out }, [out.data.buffer]);
  }
};
