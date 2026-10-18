// The beat maps of 0x40 (hues/), shared by its page (hues.js) and the maker
// of its songs (songs.js): what each character of a beat string does, the
// classic maps given the later effects (enrich()), a map counted from a
// decoded song with its characteristic hits (analyse()) and the FFT it
// counts them with, as window.HuesBeats.
(function () {
  'use strict';

  // What a character of the beat string does; the help of the page lists
  // the same. A held blackout or inversion lasts until the next character
  // that does anything.
  var EFFECTS = {
    'x': { blur: 'v', image: true, colour: 'cut' },
    'o': { blur: 'h', image: true, colour: 'cut' },
    'X': { blur: 'v', colour: 'cut' },
    'O': { blur: 'h', colour: 'cut' },
    '-': { image: true, colour: 'cut' },
    ':': { colour: 'cut' },
    '*': { image: true },
    '~': { colour: 'fade' },
    '=': { image: true, colour: 'fade' },
    '|': { blackout: 'short', colour: 'cut' },
    '+': { blackout: 'hold' },
    'i': { invert: true, colour: 'cut' },
    // a slice cuts from the picture there was to a new one
    's': { slice: 'h', image: true, colour: 'cut' },
    'v': { slice: 'v', image: true, colour: 'cut' },
    '#': { slice: 'both', image: true, colour: 'cut' }
  };

  // ---- the beat map ----------------------------------------------------

  // a second-order filter (RBJ's cookbook), low or high pass, Q of 1/sqrt 2
  function biquad(type, freq, rate) {
    var w = 2 * Math.PI * freq / rate, cos = Math.cos(w), alpha = Math.sin(w) * Math.SQRT1_2;
    var a0 = 1 + alpha;
    var b0 = type === 'low' ? (1 - cos) / 2 : (1 + cos) / 2;
    var b1 = type === 'low' ? 1 - cos : -(1 + cos);
    return { b0: b0 / a0, b1: b1 / a0, b2: b0 / a0, a1: -2 * cos / a0, a2: (1 - alpha) / a0 };
  }

  // the mean square of 10 ms frames of the whole song, of the low band (the
  // kick), the high one (snares, claps, hats) and all of it, in one pass
  function energies(buffer) {
    var rate = buffer.sampleRate, hop = Math.round(rate * 0.01);
    var frames = Math.floor(buffer.length / hop);
    var chans = [];
    for (var c = 0; c < buffer.numberOfChannels; c++) chans.push(buffer.getChannelData(c));
    var nc = chans.length;
    var lo = biquad('low', 150, rate), hi = biquad('high', 2500, rate);
    var all = new Float32Array(frames), low = new Float32Array(frames), high = new Float32Array(frames);
    var x1 = 0, x2 = 0, l1 = 0, l2 = 0, h1 = 0, h2 = 0, i = 0;
    for (var f = 0; f < frames; f++) {
      var ea = 0, el = 0, eh = 0;
      for (var end = i + hop; i < end; i++) {
        var x = 0;
        for (c = 0; c < nc; c++) x += chans[c][i];
        x /= nc;
        var yl = lo.b0 * x + lo.b1 * x1 + lo.b2 * x2 - lo.a1 * l1 - lo.a2 * l2;
        var yh = hi.b0 * x + hi.b1 * x1 + hi.b2 * x2 - hi.a1 * h1 - hi.a2 * h2;
        x2 = x1; x1 = x; l2 = l1; l1 = yl; h2 = h1; h1 = yh;
        ea += x * x; el += yl * yl; eh += yh * yh;
      }
      all[f] = ea / hop; low[f] = el / hop; high[f] = eh / hop;
    }
    return { all: all, low: low, high: high, frameSec: hop / rate };
  }

  function percentile(values, q) {
    var sorted = Array.prototype.slice.call(values).sort(function (a, b) { return a - b; });
    return sorted.length ? sorted[Math.min(sorted.length - 1, Math.floor(q * sorted.length))] : 0;
  }

  // How sharply a band grows louder in each frame: the rise of its compressed
  // energy, less the mean rise around it, so a busy passage does not read as
  // one long onset
  function novelty(energy) {
    var n = energy.length, scale = 100 / Math.max(1e-12, percentile(energy, 0.98));
    var rise = new Float32Array(n), out = new Float32Array(n);
    var prev = Math.log1p(energy[0] * scale);
    for (var i = 1; i < n; i++) {
      var now = Math.log1p(energy[i] * scale);
      rise[i] = now > prev ? now - prev : 0;
      prev = now;
    }
    var W = 20, sum = 0, count = 0;
    for (i = 0; i < Math.min(n, W); i++) { sum += rise[i]; count++; }
    for (i = 0; i < n; i++) {
      if (i + W < n) { sum += rise[i + W]; count++; }
      if (i - W - 1 >= 0) { sum -= rise[i - W - 1]; count--; }
      var d = rise[i] - sum / count;
      out[i] = d > 0 ? d : 0;
    }
    return out;
  }

  // The tempo: the lag at which the onsets repeat best, leaning towards 120
  // BPM (a log-normal weight), then the exact period and phase of the comb of
  // beats that falls on the most onsets. In frames.
  function tempo(nov, frameSec) {
    var n = nov.length;
    var minLag = Math.floor(60 / 200 / frameSec), maxLag = Math.ceil(60 / 60 / frameSec);
    var best = -1, lag = minLag;
    for (var l = minLag; l <= maxLag; l++) {
      var s = 0;
      for (var i = l; i < n; i++) s += nov[i] * nov[i - l];
      var bpm = 60 / (l * frameSec);
      var score = s / (n - l) * Math.exp(-0.5 * Math.pow(Math.log2(bpm / 120) / 0.9, 2));
      if (score > best) { best = score; lag = l; }
    }
    // a beat a frame off still counts
    var near = new Float32Array(n);
    for (i = 0; i < n; i++) near[i] = Math.max(nov[i], i > 0 ? nov[i - 1] : 0, i + 1 < n ? nov[i + 1] : 0);
    var period = lag, phase = 0;
    best = -1;
    for (var p = lag - 1.5; p <= lag + 1.5; p += 0.02) {
      for (var ph = 0; ph < p; ph += 0.5) {
        var sum = 0, beats = 0;
        for (var t = ph; t < n - 0.5; t += p) { sum += near[Math.round(t)]; beats++; }
        if (beats && sum / beats > best) { best = sum / beats; period = p; phase = ph; }
      }
    }
    // a character per half beat reads best between 85 and 175 BPM
    while (60 / (period * frameSec) < 85) period /= 2;
    while (60 / (period * frameSec) >= 175) period *= 2;
    return { period: period, phase: phase % period };
  }

  function strongest(values, from, to) {
    var top = 0;
    for (var i = Math.max(0, from); i <= to && i < values.length; i++) if (values[i] > top) top = values[i];
    return top;
  }
  function mean(values, from, to) {
    var sum = 0, count = 0;
    for (var i = Math.max(0, from); i < to && i < values.length; i++) { sum += values[i]; count++; }
    return count ? sum / count : 0;
  }

  // the onsets of a song's buffer, its bands and all of them together, each
  // band as loud as the others; counted once a buffer
  var featuresOf = typeof WeakMap === 'function' ? new WeakMap() : null;
  function features(buffer) {
    var kept = featuresOf && featuresOf.get(buffer);
    if (kept) return kept;
    var e = energies(buffer);
    var all = novelty(e.all), low = novelty(e.low), high = novelty(e.high);
    var n = all.length, mixed = new Float32Array(n);
    var na = percentile(all, 0.99) || 1, nl = percentile(low, 0.99) || 1, nh = percentile(high, 0.99) || 1;
    for (var i = 0; i < n; i++) mixed[i] = all[i] / na + low[i] / nl + high[i] / nh;
    kept = { e: e, all: all, low: low, high: high, mixed: mixed };
    if (featuresOf) featuresOf.set(buffer, kept);
    return kept;
  }

  // A radix-2 FFT of n points, in place, forward or back (back scaled by 1/n)
  function makeFft(n) {
    var rev = new Uint32Array(n), cos = new Float64Array(n / 2), sin = new Float64Array(n / 2), bits = Math.log2(n), i;
    for (i = 0; i < n; i++) {
      var r = 0;
      for (var b = 0; b < bits; b++) r = (r << 1) | ((i >> b) & 1);
      rev[i] = r;
    }
    for (i = 0; i < n / 2; i++) {
      cos[i] = Math.cos(2 * Math.PI * i / n);
      sin[i] = Math.sin(2 * Math.PI * i / n);
    }
    return function (re, im, back) {
      var i, t;
      for (i = 0; i < n; i++) {
        var j = rev[i];
        if (j > i) {
          t = re[i]; re[i] = re[j]; re[j] = t;
          t = im[i]; im[i] = im[j]; im[j] = t;
        }
      }
      var sign = back ? -1 : 1;
      for (var size = 2; size <= n; size *= 2) {
        var half = size / 2, step = n / size;
        for (i = 0; i < n; i += size) {
          for (var a = i, k = 0; a < i + half; a++, k += step) {
            var l = a + half, c = cos[k], s = sign * sin[k];
            var tre = re[l] * c + im[l] * s, tim = im[l] * c - re[l] * s;
            re[l] = re[a] - tre;
            im[l] = im[a] - tim;
            re[a] += tre;
            im[a] += tim;
          }
        }
      }
      if (back) for (i = 0; i < n; i++) { re[i] /= n; im[i] /= n; }
    };
  }

  // The spectrum of a song's mono mix every 20 ms (2048 points, Hann), in
  // the bands where its characteristic hits ring: a piano's (250 Hz to
  // 2 kHz), bells' and a glockenspiel's (2 to 8 kHz) and cymbals' (5 to
  // 16 kHz). For each, per frame of energies(): how sharply it grows (the
  // flux of its compressed spectrum), how loud it is, and how tonal (its
  // flatness: near 0 for a few clear partials, near 1 for noise). Counted
  // once a buffer, for analyse() only.
  var BANDS = { piano: [250, 2000], bells: [2000, 8000], air: [5000, 16000] };
  var spectraOf = typeof WeakMap === 'function' ? new WeakMap() : null;
  function spectra(buffer, frames, frameSec) {
    var kept = spectraOf && spectraOf.get(buffer);
    if (kept) return kept;
    var N = 2048, half = N / 2, every = 2, rate = buffer.sampleRate, hop = Math.round(rate * frameSec);
    var fft = makeFft(N), win = new Float64Array(N), re = new Float64Array(N), im = new Float64Array(N);
    var prev = new Float64Array(half + 1), now = new Float64Array(half + 1), power = new Float64Array(half + 1);
    var chans = [], nc = buffer.numberOfChannels, len = buffer.length, i, k, c;
    for (c = 0; c < nc; c++) chans.push(buffer.getChannelData(c));
    for (i = 0; i < N; i++) win[i] = 0.5 - 0.5 * Math.cos(2 * Math.PI * i / N);
    var names = Object.keys(BANDS), out = {};
    names.forEach(function (name) {
      out[name] = {
        k0: Math.max(1, Math.round(BANDS[name][0] * N / rate)), k1: Math.min(half, Math.round(BANDS[name][1] * N / rate)),
        flux: new Float32Array(frames), level: new Float32Array(frames), flat: new Float32Array(frames)
      };
    });
    // a full-scale sine under the window
    var full = N / 4;
    for (var f = 0; f < frames; f += every) {
      var start = Math.round(f * hop + hop / 2) - half;
      for (i = 0; i < N; i++) {
        var at = start + i, v = 0;
        if (at >= 0 && at < len) {
          for (c = 0; c < nc; c++) v += chans[c][at];
          v /= nc;
        }
        re[i] = v * win[i];
        im[i] = 0;
      }
      fft(re, im, false);
      for (k = 1; k <= half; k++) {
        power[k] = re[k] * re[k] + im[k] * im[k];
        now[k] = Math.log(1 + 100 * Math.sqrt(power[k]) / full);
      }
      for (var b = 0; b < names.length; b++) {
        var band = out[names[b]], flux = 0, level = 0, logs = 0, count = band.k1 - band.k0 + 1;
        for (k = band.k0; k <= band.k1; k++) {
          var d = now[k] - prev[k];
          if (d > 0) flux += d;
          level += power[k];
          logs += Math.log(power[k] + 1e-12);
        }
        var mean = level / count, flat = mean > 1e-9 ? Math.exp(logs / count) / mean : 1;
        for (var j = f; j < Math.min(frames, f + every); j++) {
          band.flux[j] = j === f ? flux / count : 0;
          band.level[j] = mean;
          band.flat[j] = flat;
        }
      }
      var swap = prev;
      prev = now;
      now = swap;
    }
    if (spectraOf) spectraOf.set(buffer, out);
    return out;
  }

  // The characteristic hits of a song, on its cells (at: the frame of each,
  // raw: the onsets of all its bands there): an accent, an onset far above
  // the two bars around it and among the song's strongest; a crash, the
  // cymbals' band growing as noise, ringing on (a snare stops short) and
  // far louder than before it (a reverb or an open hat-hat only goes on); a
  // bell's or a glockenspiel's note, its band growing more than on the
  // cells around it on the same side of the beat, with a few clear partials
  // (a piano's overtones reach its band too, but far weaker than its own); a piano's note, its band growing
  // far more than the bells' (a snare grows in both), not on a kick. Each
  // with how far it stands out.
  function characteristic(buffer, f, at, raw, lowOn) {
    var count = at.length, sp = spectra(buffer, f.all.length, f.e.frameSec);
    var pianoOn = new Float32Array(count), bellOn = new Float32Array(count), airOn = new Float32Array(count), c;
    for (c = 0; c < count; c++) {
      pianoOn[c] = strongest(sp.piano.flux, at[c] - 2, at[c] + 2);
      bellOn[c] = strongest(sp.bells.flux, at[c] - 2, at[c] + 2);
      airOn[c] = strongest(sp.air.flux, at[c] - 2, at[c] + 2);
    }
    [pianoOn, bellOn, airOn].forEach(function (values) {
      var top = percentile(values, 0.9) || 1;
      for (var k = 0; k < values.length; k++) values[k] /= top;
    });
    var loudest = percentile(raw, 0.9), found = [];
    for (c = 0; c < count; c++) {
      var a = at[c], kind = null;
      // the cells around on the same side of the beat, as the off-beats are
      // mostly weaker
      var same = [], bellsAround = [];
      for (var d = Math.max(c % 2, c - 16); d <= Math.min(count - 1, c + 16); d += 2) {
        if (d === c) continue;
        same.push(raw[d]);
        bellsAround.push(bellOn[d]);
      }
      var local = percentile(same, 0.75) || 1e-9;
      var after = mean(sp.air.level, a + 8, a + 30);
      var ring = after / (strongest(sp.air.level, a - 2, a + 4) || 1), lift = after / (mean(sp.air.level, a - 40, a - 4) || 1e-12);
      if (airOn[c] > 0.9 && ring > 0.4 && lift > 1.6 && mean(sp.air.flat, a, a + 4) > 0.2) kind = 'crash';
      else if (raw[c] >= loudest && raw[c] > 1.8 * local) kind = 'accent';
      else if (bellOn[c] > 0.2 && mean(sp.bells.flat, a, a + 4) < 0.12 && bellOn[c] > 1.5 * percentile(bellsAround, 0.5)
        && bellOn[c] > 0.3 * pianoOn[c]) kind = 'bells';
      else if (pianoOn[c] > 1.15 && pianoOn[c] > 1.4 * bellOn[c] && mean(sp.piano.flat, a, a + 4) < 0.3 && lowOn[c] < 0.8) kind = 'piano';
      if (kind) found.push({ c: c, kind: kind, strength: raw[c] / local });
    }
    return found;
  }

  // The extended map of a song with a classic one: the classic map, with the
  // later effects where its strongest beats fall. Of its blurs and cuts (x o
  // -), the strongest 8% become slices (v s #) and the strongest 2% an
  // inversion, never two within 16 characters, so only here and there.
  var SLICES = { 'x': 'v', 'o': 's', '-': '#' };
  function enrich(buffer, chars) {
    var f = features(buffer), step = buffer.duration / chars.length, strength = [], ranked = [];
    for (var c = 0; c < chars.length; c++) {
      var s = -1;
      if (SLICES[chars.charAt(c)]) {
        var at = Math.round(c * step / f.e.frameSec);
        s = strongest(f.mixed, at - 2, at + 2);
        ranked.push(s);
      }
      strength.push(s);
    }
    if (!ranked.length) return chars;
    ranked.sort(function (a, b) { return b - a; });
    var slice = ranked[Math.floor(ranked.length * 0.08)], invert = ranked[Math.floor(ranked.length * 0.02)];
    var out = chars.split(''), last = -16;
    for (c = 0; c < chars.length; c++) {
      if (strength[c] < 0 || c - last < 16 || strength[c] < slice) continue;
      out[c] = strength[c] >= invert ? 'i' : SLICES[chars.charAt(c)];
      last = c;
    }
    return out.join('');
  }

  // The beat string of a song: a character every half beat, from its onsets
  // (the kick a horizontal blur, a snare or clap a vertical one, weaker ones a
  // plain cut or a colour), quiet passages slow fades, the stronger effects
  // on its characteristic hits (characteristic(), given back as moments),
  // and a blackout before a drop, which opens with a blur. A buildup takes
  // the beat of its loop (fixed: the period and the first beat, in seconds),
  // so it runs into it.
  function analyse(buffer, fixed) {
    var f = features(buffer), e = f.e;
    var all = f.all, low = f.low, high = f.high, mixed = f.mixed, n = all.length;
    var beat = fixed ? { period: fixed.period / e.frameSec, phase: fixed.phase / e.frameSec } : tempo(mixed, e.frameSec);
    var step = beat.period / 2;
    var count = Math.max(0, Math.floor((n - beat.phase) / step));

    var lowOn = new Float32Array(count), highOn = new Float32Array(count), allOn = new Float32Array(count), loud = new Float32Array(count);
    var frameOf = new Int32Array(count), raw = new Float32Array(count);
    for (var c = 0; c < count; c++) {
      var at = Math.round(beat.phase + c * step);
      frameOf[c] = at;
      raw[c] = strongest(mixed, at - 2, at + 2);
      lowOn[c] = strongest(low, at - 2, at + 2);
      highOn[c] = strongest(high, at - 2, at + 2);
      allOn[c] = strongest(all, at - 2, at + 2);
      loud[c] = mean(e.all, at, Math.round(at + step));
    }
    function normalise(values) {
      var top = percentile(values, 0.9) || 1;
      for (var k = 0; k < values.length; k++) values[k] = Math.min(2, values[k] / top);
    }
    normalise(lowOn); normalise(highOn); normalise(allOn);

    // the first beat of a bar: the one of every four with the most kick
    var bars = [0, 0, 0, 0];
    for (c = 0; c < count; c += 2) bars[(c / 2) % 4] += lowOn[c];
    var barPhase = bars.indexOf(Math.max.apply(null, bars));
    // how loud the two bars around a character are, against the song's middle
    var around = new Float32Array(count);
    for (c = 0; c < count; c++) around[c] = mean(loud, c - 8, c + 8);
    var middle = percentile(around, 0.5) || 1e-9;

    var chars = [];
    for (c = 0; c < count; c++) {
      var on = c % 2 === 0, k = c >> 1;
      var inBar = ((k - barPhase) % 4 + 4) % 4, bar = Math.floor((k - barPhase) / 4);
      var ch = '.';
      if (around[c] < 0.3 * middle) {
        if (on && inBar === 0) ch = bar % 2 ? '=' : '~';
        else if (on && allOn[c] > 0.9) ch = ':';
      } else if (on) {
        // the backbeat's snare or clap over a kick on every beat
        if ((inBar & 1) && highOn[c] > 0.35 && (highOn[c] > 0.7 || highOn[c] > 1.2 * Math.max(highOn[c - 2] || 0, highOn[c + 2] || 0))) ch = 'x';
        else if (lowOn[c] > 0.55 && lowOn[c] >= highOn[c]) ch = 'o';
        else if (highOn[c] > 0.55) ch = 'x';
        else if (allOn[c] > 0.4) ch = '-';
        else if (allOn[c] > 0.2) ch = ':';
      } else {
        // between the beats only what stands out of both beats around it,
        // or every half beat of a pop song would flash with its hats
        var lowNear = Math.max(lowOn[c - 1] || 0, lowOn[c + 1] || 0);
        var highNear = Math.max(highOn[c - 1] || 0, highOn[c + 1] || 0);
        if (lowOn[c] > 1 && lowOn[c] > 1.2 * lowNear) ch = 'O';
        else if (highOn[c] > 1.3 && highOn[c] > 1.3 * highNear) ch = 'X';
        else if (allOn[c] > 1.2 && allOn[c] > Math.max(allOn[c - 1] || 0, allOn[c + 1] || 0)) ch = ':';
      }
      chars.push(ch);
    }
    // On the characteristic hits: a crash cuts both ways (#), an accent
    // inverts (the strongest quarter of them) or cuts (s and v by turns),
    // never two within 6 characters, the strongest kept; a piano's note
    // brings a new picture and colour (-), a bell's a new picture (*), where
    // nothing else happens
    var moments = characteristic(buffer, f, frameOf, raw, lowOn);
    var big = moments.filter(function (m) { return m.kind === 'crash' || m.kind === 'accent'; });
    var accents = big.filter(function (m) { return m.kind === 'accent'; }).map(function (m) { return m.strength; }).sort(function (a, b) { return b - a; });
    var invertFrom = accents.length ? accents[Math.floor((accents.length - 1) / 4)] : Infinity;
    var taken = new Uint8Array(count), turn = 0;
    big.slice().sort(function (a, b) { return (b.kind === 'crash') - (a.kind === 'crash') || b.strength - a.strength; }).forEach(function (m) {
      for (var d = Math.max(0, m.c - 6); d <= Math.min(count - 1, m.c + 6); d++) if (taken[d]) return;
      taken[m.c] = 1;
      m.effect = true;
    });
    moments.forEach(function (m) {
      if (m.effect) chars[m.c] = m.kind === 'crash' ? '#' : m.strength >= invertFrom ? 'i' : (turn++ % 2 ? 'v' : 's');
      else if ((m.kind === 'piano' || m.kind === 'bells') && !taken[m.c] && (chars[m.c] === '.' || chars[m.c] === ':')) chars[m.c] = m.kind === 'piano' ? '-' : '*';
    });
    // drops and the ends of loud passages, on the first beats of bars
    var lastDrop = -64;
    for (c = 16; c + 16 <= count; c += 2) {
      k = c >> 1;
      if (((k - barPhase) % 4 + 4) % 4 !== 0) continue;
      var before = mean(loud, c - 16, c), after = mean(loud, c, c + 16);
      if (after > 2.2 * before && after > 0.6 * middle && c - lastDrop >= 64) {
        chars[c - 4] = '+';
        chars[c - 3] = chars[c - 2] = chars[c - 1] = '.';
        chars[c] = 'x';
        lastDrop = c;
      } else if (before > 2.2 * after && before > 0.6 * middle) {
        chars[c - 2] = '|';
      }
    }
    return {
      bpm: 60 / (beat.period * e.frameSec),
      start: beat.phase * e.frameSec,
      step: step * e.frameSec,
      chars: chars.join(''),
      moments: moments.map(function (m) { return { c: m.c, kind: m.kind }; })
    };
  }

  window.HuesBeats = { EFFECTS: EFFECTS, analyse: analyse, enrich: enrich, makeFft: makeFft };
})();
