# Youturn : Rotation app API

Team rotation management application that lets you create activities and stands, assign teams to stands and manage the progress of a game.

## Technologies

---

- Apache
- PHP ≤ 8.2
- MySQL
- Symfony 6.4

## Author

---

**Jonathan Loré**

Github :  https://github.com/JoAnisky

Website : https://www.jonathanlore.fr

## Project recovery and local launch

Clone this project using

```shell
git clone git@github.com:JoAnisky/rotation-app-api.git
```

Navigate into the folder

```shell
  cd rotation-app-api
```

### Setting up the application locally without Docker

---

- Apache server and PHP ≤ 8.2 required

### 1 - Install dependencies

```shell
composer install
```

### 2  - Configure your database access path and user in the  `.env`

For good practice, save this file as  `.env.local` (it will be ignored)

```php
DATABASE_URL="mysql://symfony:symfony@127.0.0.1:4306/rotation_app?serverVersion=8.0.32&charset=utf8mb4"
```

### 3 - Create database

```shell
php bin/console doctrine:database:create
```

### 4 - Make migrations

This command will create tables in your database corresponding to your entities.

```shell
php bin/console doctrine:migrations:migrate
```

### 5 -  Load Fixtures

```shell
php bin/console doctrine:fixtures:load
```

### 6 -  Launch the app

```shell
symfony serve
```

Application is accessible in the browser through 127.0.0.1:8000
Access PHPMyAdmin to view database

## Setting up the application locally with Docker

---

- Create a Docker container for PHP and PHPMyAdmin

Requirements : Docker Desktop or Docker Engine (CLI).

### 1 - Create a `mysql` folder in the root directory of the project

Database is stored in this folder for data persistence (ignored by .gitignore)

### 2 - Configure your database access path and user in the  `.env`

e.g :

```php
DATABASE_URL="mysql://symfony:symfony@127.0.0.1:4306/rotation_app?serverVersion=8.0.32&charset=utf8mb4"
```

For good practice, save this file as  `.env.local` (it will be ignored)

_IMPORTANT: If you want to containerize the Symfony app too, `127.0.0.1` does not function within containers. Instead, you need to use the database container name from the `docker-compose.yaml` file:_

e.g : ('127.0.0.1' must be replaced by 'database'  )

```yaml
# docker-compose.yaml
  database:
    container_name: database
```

```php
# .env
DATABASE_URL="username:password@database:port/rotation_app"
```

### 3 - At the project root, build and launch PHP and PHPMyAdmin containers

Database "rotation_app" will be created during this process

```shell
docker-compose up --build
```

### 4 - Install dependencies

```shell
composer install
```

### 5 - Make migrations

This command will create tables in your database corresponding to your entities.

```shell
php bin/console doctrine:migrations:migrate
```

### 6 -  Load Fixtures

```shell
php bin/console doctrine:fixtures:load
```

### 6 - Start the Symfony server (should be started after the Docker containers)

(add the -d flag to run in the background)

```shell
symfony serve
```

Finaly, you can access to the app and PHPMyAdmin in your browser :

__PHPMyAdmin__ : 127.0.0.1:8899
__Application__ : 127.0.0.1:8000
