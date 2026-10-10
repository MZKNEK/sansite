<script>
  // An on/off switch with its name; the change event goes to the parent.
  export let checked = false;
  export let label = '';
  export let title = undefined;
</script>

<label class="switch" {title}>
  <span class="switch-label">{label}</span>
  <input type="checkbox" bind:checked on:change />
  <span class="switch-state" aria-hidden="true">{checked ? 'WŁ' : 'WYŁ'}</span>
  <span class="switch-track" aria-hidden="true"></span>
</label>
