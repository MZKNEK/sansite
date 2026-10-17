// The title and the artist in a song file's own tags, read in the browser for
// the songs of the visitor's own folder (wap.js), the way tags.php reads the
// ones of .mu/: ID3v2 (2.2 to 2.4) and, without it, ID3v1 of an MP3, the
// Vorbis comments of Ogg (Vorbis, Opus) and FLAC, the iTunes atoms of M4A.
// Only the pieces that lead to the two are read from the file; a cover
// picture is stepped over. wapTags(file) gives a promise of
// { title, artist }, each '' when the file says nothing.
window.wapTags = (function () {
  'use strict';

  var TEXT_MAX = 65536;       // a text frame or atom longer than this is skipped
  var COMMENTS_MAX = 1048576; // the comments read at most (a cover may be among them)

  function read(file, start, length) {
    return file.slice(start, start + length).arrayBuffer().then(function (buffer) { return new Uint8Array(buffer); });
  }

  function decode(bytes, encoding) {
    try { return new TextDecoder(encoding).decode(bytes); } catch (_) { return ''; }
  }

  function ascii(bytes, start, length) {
    return String.fromCharCode.apply(null, bytes.subarray(start, start + length));
  }

  function bigEndian(bytes, start, length) {
    var value = 0;
    for (var i = 0; i < length; i++) value = value * 256 + bytes[start + i];
    return value;
  }

  function synchsafe(bytes, start) {
    var value = 0;
    for (var i = 0; i < 4; i++) value = value * 128 + (bytes[start + i] & 0x7f);
    return value;
  }

  function littleEndian32(bytes, at) {
    return at + 4 <= bytes.length ? bytes[at] + bytes[at + 1] * 256 + bytes[at + 2] * 65536 + bytes[at + 3] * 16777216 : -1;
  }

  // the first value of each tag counts; several in one (ID3v2.4 splits them
  // with NUL) are joined with commas
  function setTag(tags, key, text) {
    if (tags[key]) return;
    tags[key] = text.split('\0').map(function (part) { return part.trim(); }).filter(Boolean)
      .join(', ').replace(/[\x00-\x1f\x7f]+/g, ' ').trim();
  }

  // ---- ID3

  // the text of a frame by its first byte: Latin-1, UTF-16 with a BOM (each
  // string may have its own), UTF-16BE, UTF-8
  function id3Text(data) {
    var encoding = data[0], text = data.subarray(1);
    if (encoding !== 1 && encoding !== 2) return decode(text, encoding === 3 ? 'utf-8' : 'latin1');
    var parts = [], from = 0;
    for (var i = 0; i + 1 < text.length; i += 2) {
      if (text[i] === 0 && text[i + 1] === 0) {
        parts.push(text.subarray(from, i));
        from = i + 2;
      }
    }
    parts.push(text.subarray(from));
    return parts.map(function (part) {
      return decode(part, encoding === 2 || part[0] === 0xfe && part[1] === 0xff ? 'utf-16be' : 'utf-16le');
    }).join('\0');
  }

  function resynchronise(data) {
    var out = [];
    for (var i = 0; i < data.length; i++) {
      out.push(data[i]);
      if (data[i] === 0xff && data[i + 1] === 0) i++;
    }
    return new Uint8Array(out);
  }

  // the ID3v2 tag at the start of the file; gives where the audio begins. A
  // tag unsynchronised as a whole (v2.2, v2.3, rare) is not read.
  function id3v2Tags(file, tags) {
    return read(file, 0, 10).then(function (header) {
      if (header.length < 10) return 0;
      var version = header[3], flags = header[5];
      var end = 10 + synchsafe(header, 6);
      var audio = end + (version === 4 && flags & 0x10 ? 10 : 0);
      if (version < 2 || version > 4 || flags & 0x80 && version < 4) return audio;

      var wanted = version === 2
        ? { TT2: 'title', TP1: 'artist', TP2: 'band' }
        : { TIT2: 'title', TPE1: 'artist', TPE2: 'band' };
      var headerSize = version === 2 ? 6 : 10;
      var first = flags & 0x40 && version > 2
        ? read(file, 10, 4).then(function (size) { return 10 + (version === 4 ? synchsafe(size, 0) : 4 + bigEndian(size, 0, 4)); })
        : Promise.resolve(10);

      return first.then(function frame(at) {
        if (at + headerSize > end || tags.title && tags.artist) return audio;
        return read(file, at, headerSize).then(function (head) {
          if (head.length < headerSize || head[0] === 0) return audio;
          var id = ascii(head, 0, version === 2 ? 3 : 4);
          var size = version === 2 ? bigEndian(head, 3, 3) : version === 4 ? synchsafe(head, 4) : bigEndian(head, 4, 4);
          var frameFlags = version === 2 ? 0 : head[9];
          if (size <= 0 || at + headerSize + size > end) return audio;
          var next = at + headerSize + size;
          if (!wanted[id] || size > TEXT_MAX) return frame(next);
          return read(file, at + headerSize, size).then(function (data) {
            if (data.length < size) return audio;
            if (version === 3) {
              if (frameFlags & 0xc0) return frame(next);
              if (frameFlags & 0x20) data = data.subarray(1);
            } else if (version === 4) {
              if (frameFlags & 0x0c) return frame(next);
              if (frameFlags & 0x40) data = data.subarray(1);
              if (frameFlags & 0x01) data = data.subarray(4);
              if (frameFlags & 0x02) data = resynchronise(data);
            }
            setTag(tags, wanted[id], id3Text(data));
            return frame(next);
          });
        });
      });
    });
  }

  // the old 128 bytes at the end of an MP3
  function id3v1Tags(file, tags) {
    if (file.size < 128) return Promise.resolve();
    return read(file, file.size - 128, 128).then(function (tag) {
      if (ascii(tag, 0, 3) !== 'TAG') return;
      setTag(tags, 'title', decode(tag.subarray(3, 33), 'latin1').replace(/[\0 ]+$/, ''));
      setTag(tags, 'artist', decode(tag.subarray(33, 63), 'latin1').replace(/[\0 ]+$/, ''));
    });
  }

  // ---- Vorbis comments (Ogg, FLAC)

  // a vendor string, the comments' count, then "KEY=value" each with its
  // length; a list cut short (COMMENTS_MAX) is read as far as it goes
  function vorbisComments(data, tags) {
    var keys = { TITLE: 'title', ARTIST: 'artist', ALBUMARTIST: 'band', 'ALBUM ARTIST': 'band' };
    var vendor = littleEndian32(data, 0);
    if (vendor < 0) return;
    var at = 4 + vendor, count = littleEndian32(data, at);
    at += 4;
    for (var i = 0; i < count; i++) {
      var length = littleEndian32(data, at);
      if (length < 0 || at + 4 + length > data.length) return;
      var field = decode(data.subarray(at + 4, at + 4 + length), 'utf-8');
      at += 4 + length;
      var sign = field.indexOf('=');
      var key = sign > 0 ? keys[field.slice(0, sign).toUpperCase()] : null;
      if (key) setTag(tags, key, field.slice(sign + 1));
    }
  }

  // FLAC's metadata blocks from at, the comments in the one of type 4
  function flacTags(file, at, tags, block) {
    block = block || 0;
    if (block >= 64) return Promise.resolve();
    return read(file, at, 4).then(function (header) {
      if (header.length < 4) return;
      var length = bigEndian(header, 1, 3);
      if ((header[0] & 0x7f) === 4)
        return read(file, at + 4, Math.min(length, COMMENTS_MAX)).then(function (data) { vorbisComments(data, tags); });
      if (header[0] & 0x80) return;
      return flacTags(file, at + 4 + length, tags, block + 1);
    });
  }

  // An Ogg stream's second packet holds the comments ("\x03vorbis" or
  // "OpusTags" before them). Pages give packets in segments; one shorter
  // than 255 bytes ends a packet.
  function oggTags(file, at, tags) {
    var parts = [], size = 0, index = 0;
    function comments() {
      var packet = new Uint8Array(size), offset = 0;
      parts.forEach(function (part) { packet.set(part, offset); offset += part.length; });
      var skip = ascii(packet, 0, 7) === '\x03vorbis' ? 7 : ascii(packet, 0, 8) === 'OpusTags' ? 8 : -1;
      if (skip > 0) vorbisComments(packet.subarray(skip), tags);
    }
    function page(at, count) {
      if (count >= 512) return Promise.resolve();
      return read(file, at, 27 + 255).then(function (header) {
        if (header.length < 27 || ascii(header, 0, 4) !== 'OggS') return;
        var segments = Array.prototype.slice.call(header.subarray(27, 27 + header[26]));
        var length = segments.reduce(function (sum, n) { return sum + n; }, 0);
        var start = at + 27 + segments.length;
        return read(file, start, length).then(function (data) {
          var offset = 0;
          for (var i = 0; i < segments.length; i++) {
            if (index === 1) {
              parts.push(data.subarray(offset, offset + segments[i]));
              size += segments[i];
            }
            offset += segments[i];
            if (segments[i] < 255 || size >= COMMENTS_MAX) {
              if (index === 1 || size >= COMMENTS_MAX) {
                comments();
                return;
              }
              index++;
            }
          }
          return page(start + length, count + 1);
        });
      });
    }
    return page(at, 0);
  }

  // ---- MP4 (M4A)

  // [start, end] of the inside of the first type atom between start and end,
  // or null
  function mp4Atom(file, start, end, type) {
    if (start + 8 > end) return Promise.resolve(null);
    return read(file, start, 16).then(function (header) {
      if (header.length < 8) return null;
      var size = bigEndian(header, 0, 4), inside = 8;
      if (size === 1) {
        size = bigEndian(header, 8, 8);
        inside = 16;
      } else if (size === 0) {
        size = end - start;
      }
      if (size < inside) return null;
      if (ascii(header, 4, 4) === type) return [start + inside, Math.min(end, start + size)];
      return mp4Atom(file, start + size, end, type);
    });
  }

  // moov > udta > meta (4 bytes of version first) > ilst > ©nam, ©ART, aART,
  // each with a data atom: 8 bytes of type and locale, then the UTF-8 text
  function mp4Tags(file, tags) {
    var path = ['moov', 'udta', 'meta', 'ilst'];
    return path.reduce(function (box, type) {
      return box.then(function (inside) {
        if (!inside) return null;
        return mp4Atom(file, inside[0], inside[1], type).then(function (found) {
          if (found && type === 'meta') found[0] += 4;
          return found;
        });
      });
    }, Promise.resolve([0, file.size])).then(function (ilst) {
      if (!ilst) return;
      var items = [['\xa9nam', 'title'], ['\xa9ART', 'artist'], ['aART', 'band']];
      return items.reduce(function (done, item) {
        return done.then(function () {
          return mp4Atom(file, ilst[0], ilst[1], item[0]).then(function (atom) {
            return atom && mp4Atom(file, atom[0], atom[1], 'data');
          }).then(function (data) {
            if (!data || data[1] - data[0] <= 8 || data[1] - data[0] > TEXT_MAX) return;
            return read(file, data[0] + 8, data[1] - data[0] - 8).then(function (text) {
              setTag(tags, item[1], decode(text, 'utf-8'));
            });
          });
        });
      }, Promise.resolve());
    });
  }

  return function (file) {
    var tags = { title: '', artist: '', band: '' };
    return read(file, 0, 12).then(function (head) {
      return (ascii(head, 0, 3) === 'ID3' ? id3v2Tags(file, tags) : Promise.resolve(0)).then(function (at) {
        return read(file, at, 12).then(function (head) {
          if (ascii(head, 0, 4) === 'fLaC') return flacTags(file, at + 4, tags);
          if (ascii(head, 0, 4) === 'OggS') return oggTags(file, at, tags);
          if (ascii(head, 4, 4) === 'ftyp') return mp4Tags(file, tags);
        });
      });
    }).then(function () {
      if (!tags.title && !tags.artist) return id3v1Tags(file, tags);
    }).catch(function () { /* what was read so far */ }).then(function () {
      return { title: tags.title, artist: tags.artist || tags.band };
    });
  };
})();
