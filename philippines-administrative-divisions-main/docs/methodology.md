# Methodology

## Data Source

This dataset is compiled from the Philippine Statistics Authority (PSA) Philippine Standard Geographic Code (PSGC), which provides the official classification of geographic areas in the Philippines.

## Processing

1. Source data is parsed and validated against the JSON Schema
2. Parent references, ancestor chains, and children counts are computed
3. English slugs are generated for URL-safe folder naming
4. Multi-format export: JSON, NDJSON, CSV
5. Hierarchy folder structure with READMEs generated via EJS templates

## Update Frequency

Data is updated when PSA releases new PSGC editions, typically annually.

## Accuracy

- All division names, codes, and hierarchy relationships come directly from source data
- Statistics are computed from data, never hardcoded
- Build script is idempotent: same input always produces same output