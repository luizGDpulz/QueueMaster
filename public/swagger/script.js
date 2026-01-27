// Initialize Swagger UI
const ui = SwaggerUIBundle({
    url: "openapi.yaml",
    dom_id: '#swagger-ui',
    deepLinking: false,
    presets: [
        SwaggerUIBundle.presets.apis,
        SwaggerUIStandalonePreset
    ],
    plugins: [
        SwaggerUIBundle.plugins.DownloadUrl
    ],
    layout: "StandaloneLayout",
    persistAuthorization: true,
    displayRequestDuration: true,
    filter: true,
    showExtensions: true,
    showCommonExtensions: true,
    defaultModelsExpandDepth: -1,
    docExpansion: "none",
    syntaxHighlight: {
        activate: true,
        theme: "agate"
    },
    requestSnippetsEnabled: true,
    requestSnippets: {
        generators: {
            "curl_bash": {
                title: "cURL (bash)",
                syntax: "bash"
            },
            "curl_powershell": {
                title: "cURL (PowerShell)",
                syntax: "powershell"
            },
            "curl_cmd": {
                title: "cURL (CMD)",
                syntax: "bash"
            }
        },
        defaultExpanded: true,
        languagesMask: ["curl_bash", "curl_powershell"]
    },
    onComplete: function() {
        console.log("Swagger UI loaded successfully");
    }
});

// Server selector functionality
document.getElementById('server-select').addEventListener('change', function(e) {
    const serverIndex = parseInt(e.target.value);
    const spec = ui.specSelectors.specJson();
    if (spec && spec.get('servers')) {
        ui.specActions.updateJsonSpec({
            ...spec.toJS(),
            servers: [spec.get('servers').get(serverIndex).toJS()]
        });
    }
});

// Dark mode toggle
function toggleDarkMode() {
    document.body.classList.toggle('dark-mode');
    const button = document.querySelector('.dark-mode-toggle');
    if (document.body.classList.contains('dark-mode')) {
        button.textContent = '☀️';
        localStorage.setItem('darkMode', 'true');
    } else {
        button.textContent = '🌙';
        localStorage.setItem('darkMode', 'false');
    }
}

// Check for saved dark mode preference
if (localStorage.getItem('darkMode') === 'true') {
    document.body.classList.add('dark-mode');
    document.querySelector('.dark-mode-toggle').textContent = '☀️';
}

// Global window reference for debugging
window.ui = ui;
