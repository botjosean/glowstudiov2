# Cómo levantar el proyecto en local

Guía para clonar y correr esta app (Laravel 13 + Inertia/Vue 3 + Postgres, vía Docker) en tu
propia máquina, sin tocar nada de producción. Con esto tenés que poder tenerla corriendo en
unos minutos, con datos de ejemplo para navegar.

> Qué es este proyecto, cómo se despliega y las reglas de colaboración (PR, ramas, etc.): eso
> vive en `COLABORACION.md`. Esta guía es solo la parte mecánica de "cómo lo prendo en mi
> máquina".

## 0. Antes de nada

- Necesitás ser colaborador del repo privado `botjosean/glowstudiov2` en GitHub. Si no tenés
  acceso, pedíselo a Josean.
- Requisitos: **Docker** (Desktop en Mac/Windows, o Engine + plugin Compose en Linux) y **Git**.
  PHP y Composer instalados en tu máquina son opcionales — el paso 2 explica cómo evitarlos.
- Todo lo que sigue es local: nada de esto toca el servidor de producción ni las claves reales
  del negocio.

## 1. Clonar y elegir la rama

```bash
git clone git@github.com:botjosean/glowstudiov2.git
cd glowstudiov2
git checkout docs/setup-local-dev   # esta rama, con esta guía y el .env.example correcto
```

`feature/frontend-v2` es la rama que corre en producción — una vez que tengas todo andando acá
podés pasarte a esa (`git checkout feature/frontend-v2`) para ver el código real desplegado.

## 2. Instalar las dependencias de PHP

Si ya tenés PHP y Composer instalados (cualquier versión — esto no ejecuta la app, solo resuelve
dependencias):

```bash
composer install --ignore-platform-reqs
```

Si no tenés PHP/Composer en tu máquina, corré ese mismo paso dentro de un contenedor
descartable — ver la sección "Installing Composer Dependencies For Existing Projects" en
[laravel.com/docs/sail](https://laravel.com/docs/sail#installing-composer-dependencies-for-existing-projects).

## 3. Variables de entorno

```bash
cp .env.example .env
```

Ya viene apuntando a la base Postgres que levanta Docker Compose (`compose.yaml`) — **no hace
falta tocar nada para que la app prenda**. Las secciones de Kapso / Asistente / Google / R2
quedan vacías a propósito: no son necesarias para correr la app. Ver el punto 9.

## 4. Levantar los contenedores

```bash
./vendor/bin/sail up -d
./vendor/bin/sail ps        # esperar a que app y pgsql estén healthy
```

(Tip opcional: `alias sail='sh vendor/bin/sail'` para escribir menos. Los comandos de esta guía
usan la ruta completa para que funcionen sin el alias.)

## 5. Base de datos

```bash
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
```

Esto crea el esquema (incluida la restricción de Postgres que impide doble reserva sobre un
mismo horario) y carga datos de ejemplo: 5 profesionales ficticios con servicios ya cargados.
Todos con password **`password`**:

| username   | email                       | perfil público      |
|------------|------------------------------|----------------------|
| `patib`    | pati@glowstudiovip.com       | http://localhost/pati    |
| `miguelb`  | miguel@glowstudiovip.com     | http://localhost/miguel  |
| `andresb`  | andres@glowstudiovip.com     | http://localhost/andres  |
| `luisb`    | luis@glowstudiovip.com       | http://localhost/luis    |
| `danielab` | daniela@glowstudiovip.com    | http://localhost/daniela |

(usuario y slug del perfil público son distintos a propósito — el username es para el login, el
slug es la URL pública). También se crea `test@example.com` / `password`, un usuario sin
profesional asociado (para ver qué pasa cuando alguien entra al panel sin tener perfil).

## 6. Frontend

```bash
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
```

Dejalo corriendo (Vite en modo watch). Abrí otra terminal para lo que sigue.

## 7. Entrar a la app

- App / login: http://localhost
- Iniciar sesión con cualquiera de las cuentas de la tabla de arriba.
- Para entrar a `/admin-general` (panel de superadmin), agregá tu username a
  `SUPERADMIN_USERNAMES` en `.env` (ej. `SUPERADMIN_USERNAMES=patib`) y refrescá — en local no
  hace falta reiniciar contenedores, `.env` se relee en cada request.

## 8. Correr los tests

```bash
./vendor/bin/sail artisan test --compact
```

Usan una base separada (`testing`, se crea sola al levantar los contenedores), así que no tocan
los datos de desarrollo del paso 5.

## 9. Qué funciona sin credenciales reales — y qué no

| Funciona 100% sin ninguna clave | Necesita que consigas tu propia cuenta |
|---|---|
| Reservar/cancelar citas, panel de administración, libro de clientas, caja de ventas, perfil público, horarios | **Bot de WhatsApp**: necesita `KAPSO_API_KEY` + `KAPSO_WEBHOOK_SECRET` propios (cuenta gratis en kapso.ai) y `ASSISTANT_API_KEY` propio (OpenRouter, tiene capa gratuita) |
| Fotos de perfil (se guardan en el contenedor, `FILESYSTEM_DISK=local`) | Fotos en la nube real: `R2_*` (Cloudflare) |
| Todo el resto del flujo de reservas | "Continuar con Google": `GOOGLE_CLIENT_ID`/`GOOGLE_CLIENT_SECRET` propios |

**Importante:** nunca pongas acá las claves de producción de Kapso/OpenRouter aunque las
tengas — mezclarías mensajes de prueba con clientas reales del negocio. Si en algún momento
querés probar el bot de punta a punta, avisale a Josean para coordinar credenciales de prueba
separadas.

## Si algo no prende

- Puerto 80 ocupado: agregá `APP_PORT=8080` a `.env`, entrá por http://localhost:8080.
- Cambios de frontend que no se ven: confirmá que `sail npm run dev` sigue corriendo en su
  terminal.
- Ver logs de la app: `./vendor/bin/sail logs -f laravel.test`.
- Arrancar de cero: `./vendor/bin/sail down -v` (borra los volúmenes, incluida la base) y volvé
  al paso 4.
