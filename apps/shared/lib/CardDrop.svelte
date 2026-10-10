<script>
  // Wraps the card so a picture file can be dropped straight onto it; hands
  // the file to onfile.
  let { children, onfile } = $props();

  let over = $state(false);
  let depth = 0;

  // only files, not pictures or text dragged from the page
  const hasFile = (e) => [...(e.dataTransfer?.types || [])].includes('Files');

  function onDragEnter(e) {
    if (!hasFile(e)) return;
    e.preventDefault();
    depth++;
    over = true;
  }

  function onDragOver(e) {
    if (hasFile(e)) e.preventDefault();
  }

  function onDragLeave() {
    if (--depth <= 0) {
      depth = 0;
      over = false;
    }
  }

  function onDrop(e) {
    if (!hasFile(e)) return;
    e.preventDefault();
    depth = 0;
    over = false;
    const file = e.dataTransfer.files[0];
    if (!file) return;
    if (!file.type.startsWith('image/')) {
      alert('Proszę przeciągnąć plik obrazu JPG lub PNG.');
      return;
    }
    onfile?.(file);
  }
</script>

<div class="card-drop" role="region" aria-label="Karta, można na nią upuścić obraz"
  ondragenter={onDragEnter} ondragover={onDragOver} ondragleave={onDragLeave} ondrop={onDrop}>
  {@render children?.()}
  {#if over}
    <div class="card-drop-over hud-corners">UPUŚĆ,<br />BY WCZYTAĆ</div>
  {/if}
</div>
