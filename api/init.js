// Swagger UI on the spec of read_swagger.php, with the endpoint search of
// search.js. Kept out of index.php, so the security policy can allow only the
// site's own script files (server/nginx).
window.onload = function() {
  // Build a system
  const ui = SwaggerUIBundle({
    url: "./read_swagger.php",
    // no badge from online.swagger.io, which would also get the spec address
    validatorUrl: null,
    dom_id: '#swagger-ui',
    deepLinking: true,
    presets: [
      SwaggerUIBundle.presets.apis,
      SwaggerUIStandalonePreset
    ],
    plugins: [
      SwaggerUIBundle.plugins.DownloadUrl,
      SanakanApiSearch.plugin
    ],
    layout: "StandaloneLayout",
    onComplete: function () {
      // API version from the spec, next to the title
      var version = window.ui.specSelectors.info().get("version")
      if (version) {
        var badge = document.getElementById("api-version")
        badge.textContent = "v" + version
        badge.hidden = false
      }

      SanakanApiSearch.mount()
    }
  })

  window.ui = ui
  SanakanApiSearch.bind(ui)
}
