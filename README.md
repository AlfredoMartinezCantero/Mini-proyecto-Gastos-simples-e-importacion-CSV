# Mini proyecto Gastos simples e importacion CSV
 
# Gastos Simples (MVP)

Mini‑proyecto web sin frameworks para registrar gastos y ver balance mensual, con importación/exportación CSV y gráfico con Chart.js.

## ✅ Funcionalidades
- Alta / edición / borrado de gastos.
- Listado con filtros:
  - Mes (`YYYY-MM`)
  - Categoría (texto exacto)
  - Búsqueda por texto en concepto (LIKE)
  - Paginación (25 por página)
- Balance mensual (total gastado y total ingresos si negativos).
- Exportación CSV del filtro activo.
- Importación CSV con validación + vista previa (10 filas) + confirmación + resumen.
- Gráfico con Chart.js:
  - Gastos por **categoría** del mes (toggle)
  - Gastos por **día** del mes (toggle)
  - Datos cargados vía endpoint JSON (`fetch`)

## 🧱 Stack
- PHP 8.x (sin frameworks)
- MySQL/MariaDB
- PDO
- HTML/CSS/JS “vanilla”
- Chart.js (CDN)
- Sesiones PHP

## ✅ Requisitos
- PHP 8.x con extensiones:
  - `pdo_mysql`
- MySQL 8.x o MariaDB 10.x
- Servidor web (Apache/Nginx) o PHP built‑in server para desarrollo

## ⚙️ Instalación (MySQL)
1) Crea la base de datos y tablas:
- bash
- mysql -u TU_USUARIO -p < back/inc/schema.mysql.sql
- Configura credenciales de BD
- En back/inc/conexion_bd.php puedes:
- Usar valores por defecto (local) o definir variables de entorno:
    - DB_HOST
    - DB_NAME
    - DB_USER
    - DB_PASS
    - DB_CHARSET

2) Crea el usuario admin (seed CLI):
- php back/controllers/seed_admin.php --email "ejemplo@gmail.com" --password "TuContraseña!"

3) Abre el proyecto en el navegador:
- front/index.php

CSV (import/export)
Formato CSV (obligatorio)

Cabecera EXACTA:
- date,concept,category,amount
- Separador: coma ,
- Decimales: punto .
- Fecha: YYYY-MM-DD

Ejemplo:

date,concept,category,amount
2026-03-01,Café,Cafetería,1.80
2026-03-01,Metro,Transporte,1.50
2026-03-02,Compra super,Alimentación,34.25
