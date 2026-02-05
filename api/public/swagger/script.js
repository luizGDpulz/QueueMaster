// Wait for page to fully load before initializing
window.onload = function() {
    // Initialize Swagger UI
    const ui = SwaggerUIBundle({
        url: "/swagger/openapi.yaml",
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

    // Global window reference for debugging
    window.ui = ui;

    // Server selector functionality (only if element exists)
    const serverSelect = document.getElementById('server-select');
    if (serverSelect) {
        serverSelect.addEventListener('change', function(e) {
            const serverIndex = parseInt(e.target.value);
            const spec = ui.specSelectors.specJson();
            if (spec && spec.get('servers')) {
                ui.specActions.updateJsonSpec({
                    ...spec.toJS(),
                    servers: [spec.get('servers').get(serverIndex).toJS()]
                });
            }
        });
    }

    // Check for saved dark mode preference
    const darkModeToggle = document.querySelector('.dark-mode-toggle');
    if (localStorage.getItem('darkMode') === 'true') {
        document.body.classList.add('dark-mode');
        if (darkModeToggle) darkModeToggle.textContent = '☀️';
    }
};

// Dark mode toggle
function toggleDarkMode() {
    document.body.classList.toggle('dark-mode');
    const button = document.querySelector('.dark-mode-toggle');
    if (button) {
        if (document.body.classList.contains('dark-mode')) {
            button.textContent = '☀️';
            localStorage.setItem('darkMode', 'true');
        } else {
            button.textContent = '🌙';
            localStorage.setItem('darkMode', 'false');
        }
    }
}
