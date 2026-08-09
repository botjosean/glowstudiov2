# Cómo trabajamos en este repo

> Para Josean y Wilhge. Escrito el 2026-08-09, cuando el código de producción
> llegó por fin a GitHub con su historia completa.

## Qué es este repo

**La fuente de verdad de lo que está VIVO** en `citas.glowstudios.vip`: la app
Laravel + Inertia con el asistente de WhatsApp (Kapso), atendiendo clientas
reales de Patricia y Vanessa todos los días.

Historia: nació en `wilhwilh/glowstudiov2` (hasta `a2fdd7a`) y siguió
evolucionando 47+ commits directamente en el servidor — bot completo, rediseño
del panel, agenda manual, PWA. Este repo une las dos partes. El repo
`wilhwilh/glowstudio` (v1, NestJS/Next.js) queda como referencia de ideas; no
es lo que corre.

## Las reglas (las dos que evitan romper producción)

1. **Nadie trabaja en el servidor.** `/home/dev/apps/glowstudiov2` solo recibe
   merges `--ff-only` de código ya probado. Nada de editar, commitear ni correr
   tests ahí — un `php artisan test` corrido como root en ese directorio dejó
   archivos de root en `storage/` y **rompió el build del deploy** (2026-08-09).
   Para probar en el servidor existe `/home/dev/checkouts/frontend-v2`.
2. **Todo cambio entra por Pull Request.** Rama propia → push → PR → el otro
   lo revisa → merge a `feature/frontend-v2` (la rama desplegada). Nunca push
   directo a esa rama. (La protección automática de rama requiere GitHub Pro
   en repos privados; mientras tanto esta regla es por acuerdo.)

## Operación

- **Deploy:** solo con OK de Josean. La receta completa (build en segundo
  plano, `up -d`, verificación) vive en el `CLAUDE.md` y `WORKLOG.md` de la
  carpeta de operación de Josean.
- **El bot está vivo:** cada cambio lleva tests (la suite corre en ~18 s) y
  verificación real antes de desplegar. Hoy: 395 pasando + 3 ajenos conocidos
  (isMobile).
- **Ramas aquí:** `feature/frontend-v2` = producción · `main` y
  `deploy/production` = puntos históricos de rollback · `feature/whatsapp-assistant`
  = la era del bot antes del rediseño.
