# FMT SaaS Software

FMT is a modular business-management platform built on the
[eQual framework](https://github.com/equalframework/equal). It brings together the
packages required to operate FMT, including identity and access management,
communications, document management, finance, human resources, purchasing,
real-estate management, sales, reporting, and infrastructure services.

This repository contains the application packages only. It is intended to be
installed in the `packages/` directory of a working eQual environment; it is not
a standalone application and does not include the complete framework runtime.

FMT supports two instance types:

- **Global**: the central instance that owns shared reference data and
  synchronization policies.
- **Agency**: an operational instance that can run independently or synchronize
  with a global instance.

The project is licensed under the GNU Affero General Public License v3.0. See
[`LICENSE`](LICENSE) for details.

## Developer setup

### Prerequisites

Before initializing eQual and FMT, make sure that:

- a container with the eQual runtime is running;
- a complete `config/config.json` file, valid for the target environment, is
  available on the Docker host;
- the PHP CLI entry point (`equal.run`) is executable in the target container;
- Git is installed in the container and can access
  `https://github.com/fmt-saas/fmt.git`;
- `FMT_INSTANCE_TYPE` is configured as either `global` or `agency` before the
  corresponding initialization action is run;
- `BACKEND_URL` is configured for an agency instance;
- the FMT package has not already been initialized on the target instance.

The initialization actions are designed for a fresh FMT installation. They stop
with an error if FMT is already registered in `log/packages.json`.

### Initialize the eQual environment

eQual must be initialized before FMT. The configuration file is the most
important input to this process: it must contain all settings required by the
target environment, including its database connection, URLs, services, and
instance-specific constants. Do not reuse a configuration from another
environment without reviewing every value.

> **Important:** initialization should not start until `config/config.json` is
> complete, syntactically valid, and consistent with the target environment.
> This file can contain credentials and other environment-specific secrets: it
> must remain excluded from version control and must never be committed.

Copy the prepared configuration into the container, then initialize the
filesystem, database, and `core` package:

```bash
CONTAINER_NAME="my-fmt-container"
CONFIG_FILE="/path/to/config.json"

docker cp "$CONFIG_FILE" \
    "$CONTAINER_NAME":/var/www/html/config/config.json

docker exec "$CONTAINER_NAME" bash -c '
set -e
cd /var/www/html
./equal.run --do=init_fs
./equal.run --do=init_db
./equal.run --do=init_package --package=core --import=true
'
```

Set the initial password for the root and administrator accounts created by
eQual. Pass the password through the container environment so that special
characters are not interpreted by the shell command:

```bash
PASSWORD="replace-with-a-strong-password"

docker exec \
    -e FMT_INITIAL_PASSWORD="$PASSWORD" \
    "$CONTAINER_NAME" \
    bash -c '
set -e
cd /var/www/html
./equal.run --do=user_pass-update \
    --user_id=1 \
    --password="$FMT_INITIAL_PASSWORD" \
    --confirm="$FMT_INITIAL_PASSWORD"
./equal.run --do=user_pass-update \
    --user_id=2 \
    --password="$FMT_INITIAL_PASSWORD" \
    --confirm="$FMT_INITIAL_PASSWORD"
'
```

Use a deployment secret store for this password and remove the local shell
variable after initialization (`unset PASSWORD`).

### Install the FMT packages in a Docker instance

The following deployment pattern replaces the current package collection with
the `main` branch of this repository while retaining the environment-specific
`core` package:

```bash
docker exec "$CONTAINER_NAME" bash -c '
set -e
cd /var/www/html
mv packages packages.core
git clone --branch main --single-branch https://github.com/fmt-saas/fmt.git packages
cp -r packages.core/core packages/
rm -rf packages.core
'
```

Run this only on a fresh or disposable deployment, or after creating a verified
backup. The command replaces the existing `packages/` directory. If the
deployment process uses an update helper, it can be copied separately with:

```bash
docker cp "$UPDATE_FILE" "$CONTAINER_NAME":/var/www/html/update.sh
```

All examples explicitly switch to `/var/www/html`, the application root used by
the deployment script.

### Initialize a global instance

Configure `FMT_INSTANCE_TYPE=global`, then run:

```bash
docker exec "$CONTAINER_NAME" bash -c '
set -e
cd /var/www/html
./equal.run --do=fmt_init_instance_global
'
```

To load demonstration data as part of the initialization, add `--demo=true`.

### Initialize an agency instance without synchronization

Configure `FMT_INSTANCE_TYPE=agency` and `BACKEND_URL`, then run:

```bash
docker exec "$CONTAINER_NAME" bash -c '
set -e
cd /var/www/html
./equal.run --do=fmt_init_instance_agency --sync=false
'
```

### Initialize and synchronize an agency instance

A synchronized agency must already be registered on the global instance. Define
the following values in the deployment environment:

| Variable | Purpose |
| --- | --- |
| `INSTANCE_UUID` | UUID assigned to the agency by the global instance. |
| `GLOBAL_ACCESS_TOKEN` | API token authorized to access the global instance. |
| `GLOBAL_URL` | Base URL of the global instance API. |
| `SYNC_LEVEL` | Policy level to import: `required`, `recommended`, `optional`, or `demo`. |

The default synchronization level is `recommended`. Initialize the agency with:

```bash
docker exec \
    -e FMT_SYNC_LEVEL="${SYNC_LEVEL:-recommended}" \
    -e FMT_INSTANCE_UUID="$INSTANCE_UUID" \
    -e FMT_GLOBAL_ACCESS_TOKEN="$GLOBAL_ACCESS_TOKEN" \
    -e FMT_GLOBAL_URL="$GLOBAL_URL" \
    "$CONTAINER_NAME" \
    bash -c '
set -e
cd /var/www/html
./equal.run --do=fmt_init_instance_agency \
    --sync=true \
    --level="$FMT_SYNC_LEVEL" \
    --instance_uuid="$FMT_INSTANCE_UUID" \
    --global_access_token="$FMT_GLOBAL_ACCESS_TOKEN" \
    --global_instance_url="$FMT_GLOBAL_URL"
'
```

During this operation, FMT initializes the required base packages, records the
agency UUID, imports synchronization policies from the global instance, and
performs the initial data pull. Keep the access token in the deployment secret
store, avoid committing it to the repository, and clear it from the shell
environment after use.

### Build the application

After either instance initialization flow completes, initialize the FMT web
application:

```bash
docker exec "$CONTAINER_NAME" bash -c '
set -e
cd /var/www/html
./equal.run --do=init_app --package=fmt --app=app --force=true
'
```

The instance is ready when both the instance initialization action and the
application initialization action complete successfully. For more information
about synchronization behavior, see
[`DOC/infra/synchronisation.md`](DOC/infra/synchronisation.md).
