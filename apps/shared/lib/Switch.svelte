<script>
  // An on/off switch with its name; onchange is told when it is flipped.
  let { checked = $bindable(false), label = '', title = undefined, onchange } = $props();
</script>

<label class="switch" {title}>
  <span class="switch-label">{label}</span>
  <input type="checkbox" bind:checked {onchange} />
  <span class="switch-state" aria-hidden="true">{checked ? 'WŁ' : 'WYŁ'}</span>
  <span class="switch-track" aria-hidden="true"></span>
</label>
