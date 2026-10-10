<script>
  // The header of the croppers, like on the subpages of sanakan.pl: back to the
  // home page and the account of the top right corner (its menu from the site,
  // or a Discord login), the scanner tag and the title.
  import { onMount } from 'svelte';
  import { account, loadAccount, loginUrl } from './account.js';

  export let title = '';

  let box;
  onMount(() => { loadAccount(box); });
</script>

<header class="page-head">
  <div class="page-top">
    <a class="back hud-corners" href="/" title="Strona główna">&larr; Sanakan</a>
    <div class="account-slot" bind:this={box} hidden></div>
    {#if $account && !$account.menu && $account.login}
      <a class="login hud-corners" href={loginUrl()}>Zaloguj przez Discord</a>
    {/if}
  </div>
  <div class="tag" aria-hidden="true">SAFEGUARD &middot; LV.9<span class="cursor">_</span></div>
  <h1 class="hud-title">{title}</h1>
</header>

<style>
  .page-top {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
  }

  .account-slot[hidden] {
    display: none;
  }

  .login {
    padding: 6px 13px;
    color: var(--text);
    font: 11px/1.5 "Sanakan Mono", monospace;
    letter-spacing: 0.14em;
    text-transform: uppercase;
  }

  /* the menu comes from the site (css/account.css); the buttons of the
     croppers are capitals in HUD corners, which the account's own is not */
  .account-slot :global(.account-toggle) {
    min-height: 0;
    letter-spacing: normal;
    text-transform: none;
  }

  .account-slot :global(.account-drop button) {
    min-height: 0;
  }
</style>
