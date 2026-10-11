// Which pictures of the bot's cards make up a frame in USkalpelator: the frame
// itself, the part behind the picture, the dere and the stats, by the frame,
// the dere and the style picked. The pictures are the bot's, mirrored by the
// site under /pw/ (inc/pw.php); variants says how many styles each frame has,
// as { Delta: 8, ... } (/pw/variants.json).

// the frames with styles to pick from: '0' and then each of their styles
export function styleList(border, variants) {
  switch (border) {
    case 'Delta':
    case 'Eta':
    case 'Lambda':
      return Array.from({ length: (variants[border] || 0) + 1 }, (_, i) => i.toString());
    default:
      return null;
  }
}

// whether Scalp has to cover the whole opening of the frame's mask: not in
// Jota and Lambda, whose pictures are meant to be placed freely
export function scalpCoversOpening(border) {
  return border !== 'Jota' && border !== 'Lambda';
}

// The style as it goes into the names of the pictures: '' for none (a frame
// without styles, or style 0). fits is false when the frame has styles but
// not this one, which then goes back to '0'.
export function frameStyle(border, style, variants) {
  const count = variants[border] || 0;
  if (count === 0) return { uri: '', fits: true };

  const n = parseInt(style);
  if (n > count || n <= 0) return { uri: '', fits: false };
  return { uri: style, fits: true };
}

// the addresses of the frame's pictures, '' for a part it does not have
export function frameLayers(pw, border, dere, styleUri) {
  return {
    border: borderUrl(pw, border, styleUri),
    backBorder: backBorderUrl(pw, border, dere, styleUri),
    stats: statsUrl(pw, border, dere, styleUri),
    dere: dereUrl(pw, border, dere),
  };
}

function borderUrl(pw, border, styleUri) {
  switch (border) {
    case 'Beta':
    case 'Epsilon':
    case 'Gamma':
    case 'Theta':
      return '';
    case 'Omega':
      return `${pw}/CG/${border}/Border${styleUri}.webp`;
    default:
      return `${pw}/CG/${border}/Border${styleUri}.png`;
  }
}

function backBorderUrl(pw, border, dere, styleUri) {
  switch (border) {
    case 'Jota':
      return `${pw}/CG/${border}/Border/${dere}.png`;
    case 'Delta':
    case 'Eta':
    case 'Lambda':
      return `${pw}/CG/${border}/BorderBack${styleUri}.png`;
    case 'Omega':
      return `${pw}/CG/${border}/BorderBack${styleUri}.webp`;
    default:
      return '';
  }
}

function dereUrl(pw, border, dere) {
  switch (border) {
    case 'Beta':
    case 'Epsilon':
    case 'Gamma':
    case 'Theta':
      return `${pw}/CG/${border}/Border/${dere}.png`;
    default:
      return `${pw}/CG/${border}/Dere/${dere}.png`;
  }
}

function statsUrl(pw, border, dere, styleUri) {
  switch (border) {
    case 'Lambda':
    case 'Zeta':
      return '';
    case 'Gamma':
    case 'Jota':
    case 'Theta':
      return `${pw}/CG/${border}/Stats/${dere}.png`;
    case 'Beta':
    case 'Epsilon':
      if (dere === 'Yami' || dere === 'Raito' || dere === 'Yato')
        return `${pw}/CG/${border}/Stats/${dere}.png`;
    // falls through: the other deres of these frames have the common stats
    default:
      return `${pw}/CG/${border}/Stats${styleUri}.png`;
  }
}
