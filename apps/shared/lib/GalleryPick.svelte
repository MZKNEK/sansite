<script>
  // "Z mojej galerii": the pictures of the account's own folder in the gallery
  // (i/?pick), newest first, to crop one of them. They are on the site itself,
  // so the crop reads them without asking another server. Shown only to an
  // account with a folder of its own.
  import { account } from './account.js';

  // called with the link of the chosen picture
  export let onpick;

  let open = false;
  let loading = false;
  let error = '';
  let pictures = [];

  async function show() {
    open = true;
    loading = true;
    error = '';
    try {
      const res = await fetch('/i/?pick', { credentials: 'same-origin', cache: 'no-store' });
      const answer = await res.json().catch(() => null);
      if (!answer?.ok) throw new Error(answer?.message || `Galeria nie odpowiedziała (HTTP ${res.status}).`);
      pictures = answer.pictures;
    } catch (e) {
      error = e.message;
    }
    loading = false;
  }

  function choose(picture) {
    open = false;
    onpick?.(picture.url);
  }

  function keydown(e) {
    if (open && e.key === 'Escape') open = false;
  }
</script>

<svelte:window on:keydown={keydown} />

{#if $account?.own}
  <button type="button" class="btn-pick" on:click={show}>Z mojej galerii</button>
{/if}

{#if open}
  <!-- svelte-ignore a11y-click-events-have-key-events a11y-no-static-element-interactions -->
  <div class="pick-back" on:click|self={() => { open = false; }}>
    <div class="pick hud-corners" role="dialog" aria-modal="true" aria-label="Obrazek z mojej galerii">
      <div class="pick-head">
        <span>Moja galeria{pictures.length && !loading ? ` · ${pictures.length}` : ''}</span>
        <button type="button" class="pick-close" title="Zamknij (Esc)" on:click={() => { open = false; }}>✕</button>
      </div>
      {#if loading}
        <p class="pick-note">Wczytuję…</p>
      {:else if error}
        <p class="pick-note bad">{error}</p>
      {:else if !pictures.length}
        <p class="pick-note">W twoim folderze nie ma jeszcze obrazków. Dodaj je w <a href="/i/">galerii</a> albo zapisz kartę przyciskiem „Do galerii”.</p>
      {:else}
        <div class="pick-grid">
          {#each pictures as picture (picture.url)}
            <button type="button" class="pick-item" title="{picture.folder}/{picture.name}" on:click={() => choose(picture)}>
              <img src={picture.thumb ?? picture.url} alt="" loading="lazy" />
              <span>{picture.name}</span>
            </button>
          {/each}
        </div>
      {/if}
    </div>
  </div>
{/if}

<style>
  .btn-pick {
    width: 100%;
  }

  .pick-back {
    position: fixed;
    inset: 0;
    z-index: 50;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    background: rgba(8, 9, 9, 0.75);
  }

  .pick {
    --fill: #18181e;
    display: flex;
    flex-direction: column;
    width: min(880px, 100%);
    max-height: min(720px, 100%);
    border: 1px solid rgba(var(--accent-rgb), 0.3);
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.6);
    text-align: left;
  }

  .pick-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 16px;
    border-bottom: 1px solid rgba(var(--accent-rgb), 0.22);
    font: 13px "Sanakan Mono", monospace;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--accent-light);
  }

  .pick-close {
    padding: 2px 10px;
  }

  .pick-note {
    margin: 0;
    padding: 24px 16px;
    color: rgba(220, 221, 222, 0.7);
  }

  .pick-note.bad {
    color: #f0c4c2;
  }

  .pick-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 10px;
    padding: 14px 16px 16px;
    overflow-y: auto;
  }

  /* a tile like the gallery's: the thumbnail and the name under it */
  .pick-item {
    --corner: rgba(var(--accent-light-rgb), 0.35);
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 6px;
    font: 11px/1.3 "JetBrains Mono", Consolas, monospace;
    letter-spacing: normal;
    text-transform: none;
  }

  .pick-item img {
    width: 100%;
    aspect-ratio: 448 / 650;
    object-fit: cover;
    background: rgba(0, 0, 0, 0.3);
  }

  .pick-item span {
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
  }
</style>
