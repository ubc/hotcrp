Running with Docker/Docker Compose
==================================

Copy `.env.default` to `.env` and update the values inside `.env` accordingly.

```
docker compose up
```

Once all containers are up and running, open a browser and go to `http://localhost:9001`.
The first default user is `user`/`pass` with `sysadmin` privilege.

Ports
-----

| port |container|
|------|:-------:|
| 9001 | hotcrp |
| 9002 | smtp/mailhog |
| 9003 | keycloak |
| 3306 | MariaDB  |

Default Credentials
-------------------
HotCRP:
* admin user: `user`/`pass`
Keycloak:
* Admin user: `admin`/`admin`
* Test user: `user`/`pass`
* OAuth client credential:
  * client_id: `hotcrp`
  * client_secret: `v2az66Huos6KwA65LOFfvJCPaqo5tUCq`
MariaDB:
* Root: `root`/`root`
* HotCRP: `hotcrp`/`hotcrppwd`

Tests
-----

To run tests in container, follow the steps below:
```bash
# create a port forwarding in hotcrp container to mysql as some of the places for db host are hardcoded as localhost
docker run --rm --name mysql-bridge --net=container:hotcrp alpine/socat TCP-LISTEN:3306,fork TCP:mysql:3306

# enter to the hotcrp container
docker exec -it hotcrp bash

# install db client
apt update && apt install -y mariadb-client procps

# create databases, users and schemas. use 127.0.0.1 to trigger mysql client to use tcp instead of socket and grant hotcrp.% host privileges.
lib/createdb.sh -u root -proot -c test/options.php --batch  --host 127.0.0.1 --grant-host hotcrp.% --replace
lib/createdb.sh -u root -proot -c test/cdb-options.php --no-dbuser --batch  --host 127.0.0.1 --grant-host hotcrp.% --replace

# run the tests
test/check.sh --all
```
