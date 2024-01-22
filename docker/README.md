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
