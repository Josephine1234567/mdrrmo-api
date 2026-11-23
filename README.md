# MDRRMO incident API

PHP REST API for the MDRRMO Constituents Website. Hatchable sends test reports to this service on Render. PostgreSQL stores each accepted report and PostGIS stores its optional GPS position as a WGS 84 point (EPSG:4326).

## Repository structure

```text
mdrrmo-api/
├── public/api/incidents/
│   ├── create.php
│   ├── list.php
│   └── view.php
├── src/
│   ├── database.php
│   └── db_connection.php
├── .dockerignore
├── .gitignore
├── Dockerfile
├── README.md
├── render.yaml
└── test-db.php
```

`src/db_connection.php` creates a PDO connection from `DATABASE_URL`. `src/database.php` enables PostGIS, defines the reports table and spatial index, and shares JSON response and validation logic with the endpoints. The Docker image serves only `public/`.

## Deploy on Render

Create a Render Blueprint from this repository’s `main` branch. `render.yaml` provisions a free Docker web service and a free PostgreSQL database in the same region. Render supplies the internal database URL and generates the API key. The container initializes the schema at startup. Verify the deployment at `/health`. Render’s free database expires after 30 days. See [Docker on Render](https://render.com/docs/docker), [PostGIS support](https://render.com/docs/postgresql-extensions), and [free tier limits](https://render.com/docs/free).

The Hatchable project uses `https://mdrrmo-api-iyer.onrender.com` as its default Render origin. In Hatchable Setup, save the generated `API_KEY` from this Render service as `RENDER_API_KEY`. The `render_api_url` setting in Hatchable Configure can point to another Render deployment. Hatchable sends the key from its server-side routes. Keep it out of browser JavaScript and Git.

## API

| Method | Path | Response |
| --- | --- | --- |
| GET | `/health` | Service, database, and PostGIS health |
| POST | `/api/incidents/create.php` | 201 for a new report or 200 for an identical retry |
| GET | `/api/incidents/list.php?limit=20&offset=0` | Most recent reports first |
| GET | `/api/incidents/view.php?id=<UUID>` | One report, or 404 if the reference does not exist |

Every incident endpoint requires `Authorization: Bearer <API_KEY>`. The detail endpoint accepts `include_photo=1` to return the optional JPEG as base64. Unauthorized requests return 401.

Example test payload for `POST /api/incidents/create.php` with `Content-Type: application/json`:

```json
{
  "reference": "6792153b-427a-4f81-a000-089c46bc5de5",
  "category": "flood",
  "location": "Fictional training landmark",
  "details": "Synthetic report for the PHP and PostGIS activity.",
  "latitude": 10.39,
  "longitude": 124.98,
  "photo": null,
  "test": true
}
```

GPS coordinates must be supplied together and use decimal degrees. Accepted categories are `flood`, `landslide`, `fire`, `earthquake`, `road`, and `other`. An attachment must be a JPEG encoded as base64, up to 400,000 encoded characters. Each report uses a version 4 UUID so retries cannot create duplicate rows. The service accepts training submissions only.

## Check the database locally

With PHP and `pdo_pgsql` installed, set `DATABASE_URL` in the shell environment and run `php test-db.php --init`. Run `php test-db.php` later to check connectivity without initializing anything.
