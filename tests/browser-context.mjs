// Existing discovery regressions exercise a browser that opted into saving.
export async function discoveryContext(browser, options = {}) {
  // These regressions route API requests; the offline worker has its own tests.
  const context = await browser.newContext({ serviceWorkers: 'block', ...options });
  await context.addInitScript(() => localStorage.setItem('sanakan-discovery-choice', 'yes'));
  return context;
}
