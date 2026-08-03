# Philippines Administrative Divisions / Pilipinas

Open dataset of the Philippines' complete administrative hierarchy — from regions down to barangays. This repository provides structured, bilingual (Filipino + English) reference data for all four levels of Philippine administrative divisions, including postal codes and geographic coordinates at every level. Designed for developers, researchers, government agencies, and AI agents.

Licensed under CC-BY-4.0. Browse the hierarchy through GitHub's folder navigation, download aggregate files in JSON/CSV/NDJSON, or integrate directly via raw URLs.

## Overview

| Item | Details |
|------|---------|
| Region | 17 |
| Province | 84 |
| Municipality | 1,634 |
| Barangay | 42,036 |
| Coordinates | ✅ Included (all levels) |
| Postal Codes | ✅ Included (barangay level) |
| Formats | JSON, NDJSON, CSV |
| License | CC-BY-4.0 |
| Last Updated | 2026-06-01 |
| Website | [openadmindata.org/ph](https://openadmindata.org/ph/) |
| API | [openadmindata.org/api/ph](https://openadmindata.org/api/ph/) |

## Browse by Region

| # | Region | Provinces | Municipalitys | Barangays | Link |
|---|----|----|----|----|------|
| 1 | Ilocos Region | 4 | 125 | 3,265 | [Browse](divisions/ilocos-region-R01/) |
| 2 | Cagayan Valley | 5 | 93 | 2,311 | [Browse](divisions/cagayan-valley-R02/) |
| 3 | Central Luzon | 7 | 130 | 3,102 | [Browse](divisions/central-luzon-R03/) |
| 4 | CALABARZON | 5 | 142 | 4,018 | [Browse](divisions/calabarzon-R04A/) |
| 5 | Bicol Region | 6 | 114 | 3,471 | [Browse](divisions/bicol-region-R05/) |
| 6 | Western Visayas | 6 | 133 | 4,051 | [Browse](divisions/western-visayas-R06/) |
| 7 | Central Visayas | 4 | 132 | 3,003 | [Browse](divisions/central-visayas-R07/) |
| 8 | Eastern Visayas | 6 | 143 | 4,390 | [Browse](divisions/eastern-visayas-R08/) |
| 9 | Zamboanga Peninsula | 4 | 72 | 1,904 | [Browse](divisions/zamboanga-peninsula-R09/) |
| 10 | Northern Mindanao | 5 | 93 | 2,022 | [Browse](divisions/northern-mindanao-R10/) |
| 11 | Davao Region | 5 | 49 | 1,162 | [Browse](divisions/davao-region-R11/) |
| 12 | SOCCSKSARGEN | 5 | 50 | 1,195 | [Browse](divisions/soccsksargen-R12/) |
| 13 | National Capital Region | 1 | 17 | 1,706 | [Browse](divisions/national-capital-region-NCR/) |
| 14 | Cordillera Administrative Region | 6 | 77 | 1,176 | [Browse](divisions/cordillera-administrative-region-CAR/) |
| 15 | Autonomous Region in Muslim Mindanao | 5 | 118 | 2,490 | [Browse](divisions/autonomous-region-in-muslim-mindanao-ARMM/) |
| 16 | Caraga | 5 | 73 | 1,311 | [Browse](divisions/caraga-R13/) |
| 17 | MIMAROPA Region | 5 | 73 | 1,459 | [Browse](divisions/mimaropa-region-R17/) |

## Data Files

| File | Format | Description |
|------|--------|-------------|
| [all-region.json](data/all-region.json) | JSON | All 17 region records |
| [all-province.json](data/all-province.json) | JSON | All 84 province records |
| [all-municipality.json](data/all-municipality.json) | JSON | All 1,634 municipality records |
| [barangay-by-region/](data/barangay-by-region/) | JSON | 42,036 barangays split by region |
| [all-flat.json](data/all-flat.json) | JSON | Levels 1-3 flat array |
| [all-flat.ndjson](data/all-flat.ndjson) | NDJSON | Streaming format |
| [all-flat.csv](data/all-flat.csv) | CSV | Spreadsheet format |
| [hierarchy.json](data/hierarchy.json) | JSON | Nested tree |
| [schema.json](data/schema.json) | JSON Schema | Data schema |

## Quick Start

### Python

```python
import json

with open("data/all-region.json", "r", encoding="utf-8") as f:
    data = json.load(f)

for r in data:
    print(f"{r['name']['local']} ({r['name']['en']}) — {r['children_count']['province']} provinces")
```

### JavaScript

```javascript
import { readFileSync } from "fs";

const data = JSON.parse(readFileSync("data/all-region.json", "utf-8"));
console.log(`Total: ${data.length} regions`);
```

## Schema

| Field | Type | Description |
|-------|------|-------------|
| `id` | string | Unique identifier |
| `level` | integer | 1=region, 2=province, 3=municipality, 4=barangay |
| `level_name` | object | Level label (local + English) |
| `name.local` | string | Name in local script |
| `name.en` | string | English name |
| `name.slug` | string | URL-safe slug |
| `parent` | object/null | Parent division reference |
| `ancestors` | array | Full ancestor chain |
| `children_count` | object | Count of children per level |
| `zip_codes` | array | Postal codes (where available) |
| `geo.lat` | string | Latitude (WGS84) |
| `geo.lon` | string | Longitude (WGS84) |

Full schema: [data/schema.json](data/schema.json)

## Hierarchy Browse

```
divisions/{region-slug}/
divisions/{region-slug}/{province-slug}/
divisions/{region-slug}/{province-slug}/{municipality-slug}/
```

Barangays are listed inline in each municipality's README.

## AI Integration

- [llms.txt](docs/llms.txt) — Quick reference for AI agents
- [llms-full.txt](docs/llms-full.txt) — Summary with per-region links
- [Per-region data](docs/llms-full/) — Full data by region

## Citation

```
Philippines Administrative Divisions Dataset (CC-BY-4.0)
URL: https://github.com/open-admin-data/philippines-administrative-divisions
```

See [CITATION.cff](CITATION.cff) for machine-readable citation.

## License

- **Data**: [CC-BY-4.0](LICENSE)

## Related

- [Open Admin Data](https://openadmindata.org) — Browse, search and explore administrative divisions for every country
- [open-admin-data](https://github.com/open-admin-data) — GitHub organization with all country repos
- [ListBase](https://www.listbase.org) — Structured reference data for every country
