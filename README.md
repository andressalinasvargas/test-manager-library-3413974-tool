# test-manager-library-3413974-tool

> **Prueba técnica · Ficha ADSO 3413974**
> Horario: de **9:15 a. m. a 3:00 p. m.** (15:00). Después, **feria de presentación** de cada proyecto.

Este repositorio guarda las **utilidades** de *Manager Library*: el sembrador de datos de demostración (`seed.php`), que interactúa exclusivamente a través de la API REST sin inyecciones SQL directas.

## Uso del Sembrador (T-21)

El script `seed.php` ejecuta la siembra requerida por la especificación:
- **1 Usuario Bibliotecario:** crea al usuario `ana` con contraseña `Biblioteca2026*`.
- **40 Libros:** distribuidos equitativamente en las 5 categorías inmutables.
- **10 Socios Lectores:** con números de documento válidos.
- **15 Préstamos:** incluyendo préstamos activos, devoluciones parciales y 3 préstamos vencidos usando la cabecera `X-Simulated-Time`.

### Ejecución

```bash
# Con la API en ejecución en http://localhost:8000/api/v1
php seed.php
```

## El reto se desarrolla con React y PHP (Laravel)

El spec está escrito para Python y para .NET, pero el reto **no** se hace en esos lenguajes:

| Capa | Tecnología del reto |
|---|---|
| Frontend | React |
| Backend | PHP con Laravel |

Lo que el spec define sobre el negocio —historias, criterios de aceptación, reglas, contrato de la
API, modelo de datos— se respeta. Lo que define sobre la tecnología se traduce a React y Laravel.

## La prueba no consiste en escribir el código

El propósito principal es ver la **capacidad de desempeño con SDD** (*Spec-Driven Development*,
desarrollo guiado por especificación): cómo se lee, se interpreta y se aplica una especificación
para llevarla a un stack distinto. El código es el medio, no el fin.

## Feria de presentación

Al cerrar la prueba (3:00 p. m.) cada aprendiz **presenta su proyecto** al grupo: qué construyó del spec, qué dejó fuera y por qué,
y una demostración corta de lo que funciona (la aplicación y el sitio público). Lo que se presenta es lo que quedó **subido a los forks
antes de las 3:00 p. m.**: no se presenta nada que no esté en GitHub.

## Los seis repositorios

| Repositorio | Qué va ahí |
|---|---|
| [`test-manager-library-3413974-docs`](https://github.com/code-sena/test-manager-library-3413974-docs) | El spec: `spec-python/` y `spec-.net/` |
| [`test-manager-library-3413974-api`](https://github.com/code-sena/test-manager-library-3413974-api) | Backend en PHP (Laravel) |
| [`test-manager-library-3413974-app`](https://github.com/code-sena/test-manager-library-3413974-app) | Frontend en React |
| [`test-manager-library-3413974-page`](https://github.com/code-sena/test-manager-library-3413974-page) | Sitio público estático de presentación |
| [`test-manager-library-3413974-infra`](https://github.com/code-sena/test-manager-library-3413974-infra) | Contenedores, red, volúmenes y motor de base de datos vacío |
| [`test-manager-library-3413974-tool`](https://github.com/code-sena/test-manager-library-3413974-tool) | Utilidades: sembrador de datos de demostración |
