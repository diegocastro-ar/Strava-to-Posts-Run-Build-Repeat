# Strava to Posts – Run Build Repeat

Plugin de WordPress que importa automáticamente tus actividades de Strava y las muestra en una página dedicada con un diseño minimalista.

## Descripción

Este plugin convierte las actividades de Strava en un Custom Post Type de WordPress, permitiendo mostrarlas en cualquier página mediante un shortcode. Las actividades se mantienen separadas de los posts del blog y no aparecen en el Home.

## Requisitos

- WordPress 5.0+
- PHP 7.4+
- Plugin [NMR Strava Activities](https://wordpress.org/plugins/nmr-strava-activities/) instalado y configurado con webhook activo
- Cuenta de Strava con API Application configurada

## Instalación

1. Descarga el archivo `strava-to-posts.php`
2. Súbelo a `/wp-content/plugins/`
3. Activa el plugin desde el panel de WordPress
4. Ve a **Ajustes → Importar Strava** para importar actividades existentes

## Configuración de NMR Strava Activities

Para que las actividades se importen automáticamente, necesitas configurar NMR Strava Activities:

1. Crea una [Strava API Application](https://www.strava.com/settings/api)
2. En WordPress, ve a **Ajustes → Strava NMR**
3. Configura:
   - Strava client id
   - Strava client secret
   - Redirect URI (página con shortcode `[strava_nmr]`)
   - Verify token
4. Activa el webhook

## Uso

### Shortcode

Agrega el shortcode en cualquier página o post:

```
[strava_ultimas_sesiones cantidad="10"]
```

**Parámetros:**
- `cantidad` - Número de actividades a mostrar (default: 10)

### Panel de administración

En **Ajustes → Importar Strava** puedes:

- Ver el estado de las actividades importadas
- Importar actividades existentes desde NMR Strava
- Actualizar el diseño de las actividades
- Eliminar todas las actividades importadas

Las actividades importadas también aparecen en el menú **Strava** del admin.

## Características

- ✅ Importación automática via webhook
- ✅ Custom Post Type separado (`strava_activity`)
- ✅ Diseño minimalista y responsive
- ✅ No aparecen en el Home del blog (Al menos asi lo queria para mi blog)
- ✅ Muestra: distancia, tiempo, ritmo, desnivel
- ✅ Link directo a cada actividad en Strava

## Datos mostrados

Para cada actividad se muestra:
- Nombre de la actividad
- Fecha
- Distancia (km)
- Tiempo
- Ritmo (min/km) para running/walking
- Desnivel (m)
- Link a Strava

Diego Castro - [diegocastro.ar](https://diegocastro.ar)
