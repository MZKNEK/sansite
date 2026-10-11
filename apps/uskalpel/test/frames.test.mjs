// USkalpelator's frames: which pictures of the bot make up each of them.
//   node --test apps/uskalpel/test/
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { styleList, frameStyle, frameLayers, scalpCoversOpening } from '../src/lib/frames.js';

const variants = { Delta: 8, Eta: 3, Lambda: 2, Omega: 4 };

test('the styles to pick: only the frames that have them', () => {
  assert.deepEqual(styleList('Eta', variants), ['0', '1', '2', '3']);
  assert.deepEqual(styleList('Lambda', {}), ['0'], 'before the styles came');
  assert.equal(styleList('Omega', variants), null, 'Omega has styles, but no picking');
  assert.equal(styleList('Alpha', variants), null);
});

test('a style the frame does not have goes back to 0', () => {
  assert.deepEqual(frameStyle('Delta', '2', variants), { uri: '2', fits: true });
  assert.deepEqual(frameStyle('Delta', '9', variants), { uri: '', fits: false });
  assert.deepEqual(frameStyle('Delta', '0', variants), { uri: '', fits: false });
  assert.deepEqual(frameStyle('Alpha', '5', variants), { uri: '', fits: true }, 'a frame without styles keeps it');
  assert.deepEqual(frameStyle('Delta', '2', {}), { uri: '', fits: true }, 'nor while the styles have not come');
});

test('the pictures of a frame', () => {
  assert.deepEqual(frameLayers('/pw', 'Delta', 'Mayadere', '2'), {
    border: '/pw/CG/Delta/Border2.png',
    backBorder: '/pw/CG/Delta/BorderBack2.png',
    stats: '/pw/CG/Delta/Stats2.png',
    dere: '/pw/CG/Delta/Dere/Mayadere.png',
  });
  assert.deepEqual(frameLayers('/pw', 'Omega', 'Yami', ''), {
    border: '/pw/CG/Omega/Border.webp',
    backBorder: '/pw/CG/Omega/BorderBack.webp',
    stats: '/pw/CG/Omega/Stats.png',
    dere: '/pw/CG/Omega/Dere/Yami.png',
  });
  assert.deepEqual(frameLayers('/pw', 'Jota', 'Kuudere', ''), {
    border: '/pw/CG/Jota/Border.png',
    backBorder: '/pw/CG/Jota/Border/Kuudere.png',
    stats: '/pw/CG/Jota/Stats/Kuudere.png',
    dere: '/pw/CG/Jota/Dere/Kuudere.png',
  });
  // the frames drawn with the dere in them have no frame of their own
  assert.deepEqual(frameLayers('/pw', 'Gamma', 'Tsundere', ''), {
    border: '',
    backBorder: '',
    stats: '/pw/CG/Gamma/Stats/Tsundere.png',
    dere: '/pw/CG/Gamma/Border/Tsundere.png',
  });
  assert.equal(frameLayers('/pw', 'Lambda', 'Bodere', '1').stats, '', 'Lambda and Zeta have no stats');
  assert.equal(frameLayers('/pw', 'Zeta', 'Bodere', '').stats, '');
});

test('Beta and Epsilon have their own stats only for Yami, Raito and Yato', () => {
  assert.equal(frameLayers('/pw', 'Beta', 'Yato', '').stats, '/pw/CG/Beta/Stats/Yato.png');
  assert.equal(frameLayers('/pw', 'Epsilon', 'Raito', '').stats, '/pw/CG/Epsilon/Stats/Raito.png');
  assert.equal(frameLayers('/pw', 'Beta', 'Dandere', '').stats, '/pw/CG/Beta/Stats.png', 'the others the common one');
});

test('Scalp covers the opening of every frame but Jota and Lambda', () => {
  assert.equal(scalpCoversOpening('Delta'), true);
  assert.equal(scalpCoversOpening('Alpha'), true);
  assert.equal(scalpCoversOpening('Jota'), false);
  assert.equal(scalpCoversOpening('Lambda'), false);
});
