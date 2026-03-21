<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS WMS Backend API - Swagger UI</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.ico') }}">
    
    <!-- Swagger UI CSS -->
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
    
    <style>
        html {
            box-sizing: border-box;
        }
        
        *, *:before, *:after {
            box-sizing: inherit;
        }
        
        body {
            margin: 0;
            background: #fafafa;
        }
        
        .swagger-ui .topbar {
            background-color: #181818;
        }
        
        .swagger-ui .topbar .download-url-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .swagger-ui .topbar .download-url-wrapper input[type=text] {
            min-width: 350px;
            border: 2px solid #181818;
        }
        
        .swagger-ui .topbar .download-url-wrapper .download-url-button {
            background-color: #89bf04;
        }
        
        .swagger-ui .info .title {
            color: #181818;
        }
        
        .swagger-ui .info a {
            color: #4990d7;
        }
        
        .swagger-ui .scheme-container {
            box-shadow: 0 1px 2px 0 rgba(0,0,0,0.15);
        }
        
        /* Custom header */
        .api-header {
            background: linear-gradient(135deg, #181818 0%, #2d2d2d 100%);
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .api-header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        
        .api-header p {
            margin: 8px 0 0;
            opacity: 0.8;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <!-- Custom Header -->
    <div class="api-header">
        <h1>POS WMS Backend API Documentation</h1>
        <p>Point of Sale & Warehouse Management System</p>
    </div>
    
    <!-- Swagger UI Container -->
    <div id="swagger-ui"></div>
    
    <!-- Swagger UI JS -->
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js" charset="UTF-8"></script>
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-standalone-preset.js" charset="UTF-8"></script>
    
    <script>
        window.onload = function() {
            // Build a system
            const ui = SwaggerUIBundle({
                url: '{{ url("/api/v1/docs/openapi.json") }}',
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                plugins: [
                    SwaggerUIBundle.plugins.DownloadUrl
                ],
                layout: "StandaloneLayout",
                validatorUrl: "https://validator.swagger.io/validator",
                supportedSubmitMethods: ['get', 'post', 'put', 'delete', 'patch', 'options'],
                displayOperationId: false,
                filter: true,
                showExtensions: true,
                showCommonExtensions: true,
                defaultModelsExpandDepth: 1,
                defaultModelExpandDepth: 1,
                docExpansion: 'list',
                tryItOutEnabled: true
            });
            
            window.ui = ui;
        };
    </script>
</body>
</html>
