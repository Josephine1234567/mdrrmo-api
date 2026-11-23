# MDRRMO API

PHP REST API for the MDRRMO constituents website. It receives lab incident reports and stores them in PostgreSQL with PostGIS.

## Step 1 – Repository structure

```text
mdrrmo-api/
├── public/
│   └── api/
│       └── incidents/
│           ├── create.php
│           ├── list.php
│           └── view.php
├── src/
│   ├── database.php
│   └── db_connection.php
├── .dockerignore
├── .gitignore
├── Dockerfile
├── README.md
└── test-db.php
```

## Step 2 – Database connection files

- `src/db_connection.php` reads the `DATABASE_URL` environment variable and opens a PostgreSQL connection through PDO.
- `src/database.php` loads the connection, enables PostGIS, and defines the incident report table.

`test-db.php` checks the database connection. The `Dockerfile` packages the PHP API for Render.
