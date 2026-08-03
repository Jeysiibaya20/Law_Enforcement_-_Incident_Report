<!DOCTYPE html>
<html>
<head>
    <title>Law Enforcement - Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .info { background: #e3f2fd; border-left: 4px solid #2196F3; padding: 15px; margin: 20px 0; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Law Enforcement Incident Report - Dashboard</h1>
        <div class="info">
            <h2>✓ Laravel Setup Complete!</h2>
            <p>Your Laravel application is now running successfully.</p>
        </div>
        
        <h2>Available Routes:</h2>
        <ul>
            <li><strong>Dashboard:</strong> <a href="/dashboard">/dashboard</a></li>
            <li><strong>Incidents:</strong> <a href="/incidents">/incidents</a></li>
            <li><strong>Health Check:</strong> <a href="/up">/up</a></li>
        </ul>

        <h2>API Endpoints (v1):</h2>
        <ul>
            <li><strong>GET</strong> /api/v1/incidents - List incidents</li>
            <li><strong>POST</strong> /api/v1/incidents - Create incident</li>
            <li><strong>GET</strong> /api/v1/cases - List cases</li>
            <li><strong>GET</strong> /api/v1/reports - List reports</li>
            <li><strong>POST</strong> /api/v1/reports/export - Export reports</li>
            <li><strong>GET</strong> /api/v1/health - API health check</li>
        </ul>

        <h2>Integration with Existing System:</h2>
        <p>Your existing PHP files remain compatible and continue to work alongside Laravel.</p>
        
        <h2>Next Steps:</h2>
        <ol>
            <li>Configure your database in <code>.env</code> file</li>
            <li>Run migrations: <code>php artisan migrate</code></li>
            <li>Seed sample data: <code>php artisan db:seed</code></li>
            <li>Start development server: <code>php artisan serve</code></li>
        </ol>
    </div>
</body>
</html><?php /**PATH C:\xampp\htdocs\Law_Enforcement_-_Incident_Report\resources\views\welcome.blade.php ENDPATH**/ ?>