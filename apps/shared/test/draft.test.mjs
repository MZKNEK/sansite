// The editors' draft (lib/draft.js) on a store in memory: what is kept, when a
// picture is written again, how long a draft lasts and what a clear leaves.
//   node --test apps/shared/test/
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { draftKeeper, memoryStore, DRAFT_DAYS } from '../lib/draft.js';

const PNG = 'data:image/png;base64,iVBORw0KGgo=';
const settings = (x) => ({ border: 'S', crop: { x, y: 0, zoom: 1 } });

test('the settings and the pictures come back as they were saved', async () => {
  const store = memoryStore();
  const keeper = draftKeeper('t1', { store, delay: 0 });
  assert.equal(await keeper.load(), null, 'nothing kept yet');
  keeper.save(settings(0), { image: 'https://sanakan.pl/i/a.webp', extra: null });
  keeper.save(settings(5), { image: PNG, extra: null });
  await keeper.flush();
  keeper.close();

  const again = draftKeeper('t1', { store });
  const kept = await again.load();
  assert.deepEqual(kept.settings, settings(5));
  assert.equal(kept.pictures.image.own, true, 'a picture of the visitor\'s own comes back as a blob: of its own');
  assert.match(kept.pictures.image.src, /^blob:/);
  assert.equal(kept.pictures.extra, undefined, 'no picture, none back');
  const blob = await fetch(kept.pictures.image.src).then((res) => res.blob());
  assert.equal(blob.type, 'image/png');
  URL.revokeObjectURL(kept.pictures.image.src);
  again.close();
});

test('a link stays a link and a picture is written only when another takes its place', async () => {
  const store = memoryStore();
  const { data } = await store;
  const keeper = draftKeeper('t2', { store, delay: 0 });
  await keeper.load();
  keeper.save(settings(0), { image: 'https://x/a.webp' });
  keeper.save(settings(1), { image: 'https://x/a.webp' });
  await keeper.flush();
  assert.deepEqual(data.pictures.get('t2:image'), { src: 'https://x/a.webp' });

  let puts = 0;
  const put = data.pictures.set.bind(data.pictures);
  data.pictures.set = (...args) => { puts++; return put(...args); };
  keeper.save(settings(2), { image: 'https://x/a.webp' });
  await keeper.flush();
  assert.equal(puts, 0, 'the same picture is not written again with every move of the crop');
  keeper.save(settings(3), { image: PNG });
  await keeper.flush();
  assert.equal(puts, 1);
  assert.ok(data.pictures.get('t2:image').blob);
  keeper.close();
});

test('an editor left as it opened keeps nothing', async () => {
  const store = memoryStore();
  const { data } = await store;
  const keeper = draftKeeper('t3', { store, delay: 0 });
  await keeper.load();
  keeper.save(settings(0), { image: 'https://x/a.webp' });
  keeper.save(settings(0), { image: 'https://x/a.webp' });
  await keeper.flush();
  assert.equal(data.state.size, 0, 'what it starts with is not a draft');
  keeper.save(settings(1), { image: 'https://x/a.webp' });
  await keeper.flush();
  keeper.save(settings(0), { image: 'https://x/a.webp' });
  await keeper.flush();
  assert.deepEqual(data.state.get('t3').settings, settings(0), 'once changed, going back is kept too');
  keeper.close();
});

test('an old draft is forgotten and a clear leaves nothing', async () => {
  const store = memoryStore();
  const { data } = await store;
  const keeper = draftKeeper('t4', { store, delay: 0 });
  await keeper.load();
  keeper.save(settings(1), { image: PNG });
  await keeper.flush();
  await keeper.clear();
  assert.equal(data.state.size + data.pictures.size, 0, 'cleared');

  keeper.save(settings(2), { image: PNG });
  keeper.save(settings(3), { image: PNG });
  await keeper.flush();
  data.state.get('t4').savedAt = Date.now() - (DRAFT_DAYS + 1) * 86400000;
  keeper.close();
  const later = draftKeeper('t4', { store });
  assert.equal(await later.load(), null, `older than ${DRAFT_DAYS} days`);
  assert.equal(data.state.size + data.pictures.size, 0, 'and gone');
  later.close();
});

test('a store that fails loses the draft, not the editor', async () => {
  const keeper = draftKeeper('t5', { store: Promise.reject(new Error('no IndexedDB')), delay: 0 });
  assert.equal(await keeper.load(), null);
  keeper.save(settings(1), { image: PNG });
  await keeper.flush();
  await keeper.clear();
  keeper.close();
});
