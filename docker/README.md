Running with Docker/Docker Compose
==================================

Copy `.env.default` to `.env` and update the values inside `.env` accordingly.

```
docker compose up
```

Once all containers are up and running, run the following command to provision the database.

```
docker exec -it hotcrp-hotcrp-1 bash
apt update && apt install -y mariadb-client
mysql -u hotcrp -p$MYSQL_PASSWORD -h $MYSQL_HOST hotcrp < src/schema.sql
```

Then open a browser and go to `http://localhost:9001`.


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
Keycloak:
* Admin user: admin/admin
* Test user: user/pass
* OAuth client credential:
  * client_id: hotcrp
  * client_secret: v2az66Huos6KwA65LOFfvJCPaqo5tUCq
MariaDB:
* Root: root/root
* HotCRP: hotcrp/hotcrppwd
