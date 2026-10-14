<script>
  // Above the editor's panel when it came back with the work of before
  // (draft.js): when that was saved, and a way to start again from nothing.

  // savedAt: when the draft was saved (ms); onreset: forgets it
  let { savedAt, onreset } = $props();

  const when = $derived.by(() => {
    const d = new Date(savedAt);
    const two = (n) => String(n).padStart(2, '0');
    const time = `${two(d.getHours())}:${two(d.getMinutes())}`;
    return d.toDateString() === new Date().toDateString() ? time : `${d.getDate()}.${two(d.getMonth() + 1)} ${time}`;
  });
</script>

<div class="draft" role="status">
  <span>Przywrócono pracę z {when}</span>
  <button type="button" onclick={onreset}>Zacznij od nowa</button>
</div>

<style>
  .draft {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 8px 12px;
    padding: 8px 12px;
    border: 1px solid color-mix(in srgb, var(--utility-tone) 35%, transparent);
    background: color-mix(in srgb, var(--utility-tone) 5%, transparent);
    font: 12px "Sanakan Mono", monospace;
    letter-spacing: 0.08em;
    color: var(--utility-ink);
  }

  button {
    padding: 4px 10px;
    font-size: 11px;
  }
</style>
