<!DOCTYPE html>
<html>
<head>
    <title>Dashboard - Law Enforcement Incident Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px 0; }
        .card { border: 1px solid #ddd; padding: 15px; border-radius: 8px; background: #fafafa; }
        .card h3 { margin-top: 0; color: #2196F3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Dashboard</h1>
        
        <div class="grid">
            <div class="card">
                <h3>Recent Incidents</h3>
                <p>Track and manage recent incident reports</p>
            </div>
            <div class="card">
                <h3>Cases</h3>
                <p>View and manage active cases</p>
            </div>
            <div class="card">
                <h3>Reports</h3>
                <p>Generate and export reports</p>
            </div>
        </div>
    </div>
</body>
</html>