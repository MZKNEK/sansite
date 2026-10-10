<script>
  // Saving the card into the account's folder in the gallery, next to saving
  // it as a file: a button for an account with a folder of its own, a login for
  // a visitor, and after saving the link of the picture to copy (for the bot)
  // and its folder in the gallery. The card goes as a lossless WebP, so the
  // gallery keeps it as it is and changes nothing in the background.
  import { account, cardName, loginUrl, saveToGallery } from './account.js';
  import { losslessWebp } from './webp.js';



  // pixels: the pixels of the card as it is saved (ImageData of its canvas);
  // prefix: the start of its file name, the time of saving goes after it
  let { pixels, prefix } = $props();

  let busy = $state(false);
  let result = $state(null);
  let error = $state('');
  let copied = $state(false);

  let link = $derived(result ? new URL(result.url, location.href).href : '');

  async function save() {
    busy = true;
    error = '';
    result = null;
    try {
      let blob;
      try {
        blob = await losslessWebp(await pixels());
      } catch {
        throw new Error('Nie udało się przygotować obrazka, spróbuj z innym lub użyj lokalnego pliku.');
      }
      result = await saveToGallery(blob, cardName(prefix, 'webp'));
    } catch (e) {
      error = e.message || 'Nie udało się zapisać w galerii.';
    }
    busy = false;
  }

  async function copy(e) {
    try {
      await navigator.clipboard.writeText(link);
    } catch {
      // without the clipboard (no https, or refused) the link is picked to copy by hand
      e.currentTarget.parentElement.querySelector('input').select();
      return;
    }
    copied = true;
    setTimeout(() => { copied = false; }, 1500);
  }
</script>

{#if $account?.menu}
  {#if $account.own}
    <button type="button" class="btn-save" onclick={save} disabled={busy}>{busy ? 'Zapisuję…' : 'Do galerii'}</button>
  {:else}
    <span class="note">Zapis w galerii: to konto nie ma w niej swojego folderu</span>
  {/if}
{:else if $account?.login}
  <a class="btn-save hud-corners" href={loginUrl()}>Zaloguj, żeby zapisać w galerii</a>
{/if}

{#if result || error}
  <span class="break" aria-hidden="true"></span>
{/if}
{#if result}
  <div class="saved" role="status">
    <span class="message">{result.message}</span>
    <div class="link">
      <input type="text" readonly value={link} aria-label="Link do obrazka" onfocus={(e) => e.currentTarget.select()} />
      <button type="button" onclick={copy}>{copied ? 'Skopiowano' : 'Kopiuj'}</button>
    </div>
    <a href={result.folder}>Otwórz folder w galerii &rarr;</a>
  </div>
{:else if error}
  <div class="saved bad" role="alert">{error}</div>
{/if}

<style>
  /* blue corners, apart from the green of the file */
  .btn-save {
    --corner: rgba(5, 217, 232, 0.85);
    --fill: rgba(5, 217, 232, 0.06);
    color: #b8f3f7;
  }

  a.btn-save {
    display: inline-block;
    padding: 8px 18px;
    font: 14px/1.5 "Sanakan Mono", monospace;
    letter-spacing: 0.14em;
    text-transform: uppercase;
  }

  .btn-save:hover:not(:disabled) {
    --fill: rgba(5, 217, 232, 0.18);
  }

  .note {
    align-self: center;
    font: 12px "Sanakan Mono", monospace;
    letter-spacing: 0.08em;
    color: rgba(220, 221, 222, 0.5);
  }

  /* under the buttons (the break ends their row), as wide as the card at
     most (.card-actions of the apps keeps it from widening the card's column) */
  .break {
    flex-basis: 100%;
    height: 0;
  }

  .saved {
    flex: 1 1 100%;
    max-width: 481px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 10px 12px;
    border: 1px solid rgba(5, 217, 232, 0.35);
    background: rgba(5, 217, 232, 0.05);
    font-size: 13px;
    text-align: left;
  }

  .saved.bad {
    border-color: rgba(232, 98, 98, 0.5);
    background: rgba(232, 98, 98, 0.06);
    color: #f0c4c2;
  }

  .link {
    display: flex;
    gap: 8px;
  }

  .link input {
    flex: 1;
    min-width: 0;
    padding: 6px 8px;
    border: 1px solid rgba(220, 221, 222, 0.2);
    background: rgba(0, 0, 0, 0.3);
    color: var(--text);
    font: 12px "JetBrains Mono", monospace;
  }

  .link button {
    padding: 6px 12px;
    font-size: 12px;
  }
</style>
