using System.Text.Json.Serialization;
using MySql.Data.MySqlClient;

var builder = WebApplication.CreateBuilder(args);

var apiUrl = Environment.GetEnvironmentVariable("API_URL")?.Trim();
if (string.IsNullOrWhiteSpace(apiUrl))
{
    apiUrl = "http://0.0.0.0:5050";
}

builder.WebHost.UseUrls(apiUrl);
builder.Services.AddEndpointsApiExplorer();
builder.Services.AddSwaggerGen();

var app = builder.Build();

if (app.Environment.IsDevelopment())
{
    app.UseSwagger();
    app.UseSwaggerUI();
}

app.UseHttpsRedirection();

async Task EnsureApiTableExistsAsync(IConfiguration configuration)
{
    var connectionString = configuration.GetConnectionString("DefaultConnection");
    if (string.IsNullOrWhiteSpace(connectionString))
    {
        throw new InvalidOperationException("Database connection string is missing.");
    }

    using var connection = new MySqlConnection(connectionString);
    await connection.OpenAsync();

    var sql = @"
        CREATE TABLE IF NOT EXISTS api_incoming_payloads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            source VARCHAR(255) NULL,
            message TEXT NULL,
            reference_id VARCHAR(255) NULL,
            received_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );";

    using var command = new MySqlCommand(sql, connection);
    await command.ExecuteNonQueryAsync();
}

await EnsureApiTableExistsAsync(app.Configuration);

app.MapGet("/law_Enforcement", async (IConfiguration configuration) =>
{
    using var connection = new MySqlConnection(configuration.GetConnectionString("DefaultConnection"));
    await connection.OpenAsync();

    using var command = new MySqlCommand(@"
        SELECT id, case_no, incident_type, reporter_name, reporter_email, location, incident_date, status
        FROM incidents
        ORDER BY id DESC
        LIMIT 20", connection);

    using var reader = await command.ExecuteReaderAsync();
    var incidents = new List<object>();
    while (await reader.ReadAsync())
    {
        var idOrdinal = reader.GetOrdinal("id");
        var caseNoOrdinal = reader.GetOrdinal("case_no");
        var incidentTypeOrdinal = reader.GetOrdinal("incident_type");
        var reporterNameOrdinal = reader.GetOrdinal("reporter_name");
        var reporterEmailOrdinal = reader.GetOrdinal("reporter_email");
        var locationOrdinal = reader.GetOrdinal("location");
        var incidentDateOrdinal = reader.GetOrdinal("incident_date");
        var statusOrdinal = reader.GetOrdinal("status");

        incidents.Add(new
        {
            id = reader.GetInt32(idOrdinal),
            caseNo = reader.IsDBNull(caseNoOrdinal) ? null : reader.GetString(caseNoOrdinal),
            incidentType = reader.IsDBNull(incidentTypeOrdinal) ? null : reader.GetString(incidentTypeOrdinal),
            reporterName = reader.IsDBNull(reporterNameOrdinal) ? null : reader.GetString(reporterNameOrdinal),
            reporterEmail = reader.IsDBNull(reporterEmailOrdinal) ? null : reader.GetString(reporterEmailOrdinal),
            location = reader.IsDBNull(locationOrdinal) ? null : reader.GetString(locationOrdinal),
            incidentDate = reader.IsDBNull(incidentDateOrdinal) ? null : reader.GetDateTime(incidentDateOrdinal).ToString("yyyy-MM-dd"),
            status = reader.IsDBNull(statusOrdinal) ? null : reader.GetString(statusOrdinal)
        });
    }

    return Results.Ok(new
    {
        message = "Data retrieved successfully",
        system = "Law Enforcement Incident Report",
        database = "Connected",
        recordCount = incidents.Count,
        data = incidents,
        timestamp = DateTime.UtcNow
    });
})
.WithName("LawEnforcementGet")
.WithOpenApi();

app.MapPost("/law_Enforcement/sendInfo", async (HttpContext context, IConfiguration configuration) =>
{
    try
    {
        using var reader = new StreamReader(context.Request.Body);
        var body = await reader.ReadToEndAsync();

        if (string.IsNullOrWhiteSpace(body))
        {
            return Results.BadRequest(new { message = "Request body is required." });
        }

        var payload = System.Text.Json.JsonSerializer.Deserialize<IncomingPayload>(body, new System.Text.Json.JsonSerializerOptions
        {
            PropertyNameCaseInsensitive = true
        });

        using var connection = new MySqlConnection(configuration.GetConnectionString("DefaultConnection"));
        await connection.OpenAsync();

        var sql = @"
            INSERT INTO incidents (
                case_no, incident_type, reporter_name, reporter_email, reporter_phone, reporter_type,
                incident_date, incident_time, location, narrative, status, created_at
            ) VALUES (
                @caseNo, @incidentType, @reporterName, @reporterEmail, @reporterPhone, @reporterType,
                @incidentDate, @incidentTime, @location, @narrative, @status, NOW()
            );";

        using var command = new MySqlCommand(sql, connection);
        command.Parameters.AddWithValue("@caseNo", string.IsNullOrWhiteSpace(payload?.CaseNo) ? (object)DBNull.Value : payload.CaseNo);
        command.Parameters.AddWithValue("@incidentType", string.IsNullOrWhiteSpace(payload?.IncidentType) ? "Other" : payload.IncidentType);
        command.Parameters.AddWithValue("@reporterName", string.IsNullOrWhiteSpace(payload?.ReporterName) ? (object)DBNull.Value : payload.ReporterName);
        command.Parameters.AddWithValue("@reporterEmail", string.IsNullOrWhiteSpace(payload?.ReporterEmail) ? (object)DBNull.Value : payload.ReporterEmail);
        command.Parameters.AddWithValue("@reporterPhone", string.IsNullOrWhiteSpace(payload?.ReporterPhone) ? (object)DBNull.Value : payload.ReporterPhone);
        command.Parameters.AddWithValue("@reporterType", string.IsNullOrWhiteSpace(payload?.ReporterType) ? "Citizen" : payload.ReporterType);
        command.Parameters.AddWithValue("@incidentDate", string.IsNullOrWhiteSpace(payload?.IncidentDate) ? DateTime.Today.ToString("yyyy-MM-dd") : payload.IncidentDate);
        command.Parameters.AddWithValue("@incidentTime", string.IsNullOrWhiteSpace(payload?.IncidentTime) ? (object)DBNull.Value : payload.IncidentTime);
        command.Parameters.AddWithValue("@location", string.IsNullOrWhiteSpace(payload?.Location) ? (object)DBNull.Value : payload.Location);
        command.Parameters.AddWithValue("@narrative", string.IsNullOrWhiteSpace(payload?.Narrative) ? "No narrative provided." : payload.Narrative);
        command.Parameters.AddWithValue("@status", string.IsNullOrWhiteSpace(payload?.Status) ? "Submitted" : payload.Status);
        await command.ExecuteNonQueryAsync();

        return Results.Ok(new
        {
            message = "Information received",
            stored = true,
            received = payload,
            timestamp = DateTime.UtcNow
        });
    }
    catch (Exception ex)
    {
        return Results.Problem(ex.Message);
    }
})
.WithName("LawEnforcementSend")
.WithOpenApi();

app.Run();

record IncomingPayload(
    [property: JsonPropertyName("caseNo")] string? CaseNo,
    [property: JsonPropertyName("incidentType")] string? IncidentType,
    [property: JsonPropertyName("reporterName")] string? ReporterName,
    [property: JsonPropertyName("reporterEmail")] string? ReporterEmail,
    [property: JsonPropertyName("reporterPhone")] string? ReporterPhone,
    [property: JsonPropertyName("reporterType")] string? ReporterType,
    [property: JsonPropertyName("incidentDate")] string? IncidentDate,
    [property: JsonPropertyName("incidentTime")] string? IncidentTime,
    [property: JsonPropertyName("location")] string? Location,
    [property: JsonPropertyName("narrative")] string? Narrative,
    [property: JsonPropertyName("status")] string? Status);
