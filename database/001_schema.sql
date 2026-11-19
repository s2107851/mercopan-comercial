-- Mercopan COMERCIAL 1.0, base independiente del proyecto UTU.
-- Ejecutar sobre una BASE VACÍA. Nunca sobre una base con datos reales.
CREATE DATABASE IF NOT EXISTS mercopan_comercial CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mercopan_comercial;
SET NAMES utf8mb4;
SET time_zone='-03:00';
CREATE TABLE IF NOT EXISTS empleados (
 id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(120) NOT NULL UNIQUE,
 rol ENUM('operario','supervisor','administrador') NOT NULL DEFAULT 'operario',
 activo TINYINT(1) NOT NULL DEFAULT 1, creado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS proveedores (
 id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(120) NOT NULL UNIQUE,
 rut VARCHAR(24) NOT NULL DEFAULT '', telefono VARCHAR(60) NOT NULL DEFAULT '',
 activo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS ingredientes (
 id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(120) NOT NULL UNIQUE,
 unidad ENUM('kg','l','unidad') NOT NULL, categoria VARCHAR(80) NOT NULL DEFAULT 'General',
 icono VARCHAR(12) NOT NULL DEFAULT '📦', atajos VARCHAR(90) NOT NULL DEFAULT '1,5,10',
 stock_deposito DECIMAL(13,3) NOT NULL DEFAULT 0,
 stock_produccion DECIMAL(13,3) NOT NULL DEFAULT 0,
 minimo_deposito DECIMAL(13,3) NOT NULL DEFAULT 0,
 costo_promedio DECIMAL(14,4) NULL DEFAULT NULL,
 activo TINYINT(1) NOT NULL DEFAULT 1,
 CHECK(stock_deposito>=0), CHECK(stock_produccion>=0), CHECK(costo_promedio>=0)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS productos (
 id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(120) NOT NULL UNIQUE,
 categoria VARCHAR(80) NOT NULL DEFAULT 'General', unidad ENUM('kg','unidad') NOT NULL DEFAULT 'unidad',
 icono VARCHAR(12) NOT NULL DEFAULT '🥖', atajos VARCHAR(90) NOT NULL DEFAULT '1,2,5',
 precio DECIMAL(13,2) NULL,
 costo_estimado DECIMAL(13,4) NULL,
 stock DECIMAL(13,3) NOT NULL DEFAULT 0, activo TINYINT(1) NOT NULL DEFAULT 1,
 CHECK(stock>=0), CHECK(precio IS NULL OR precio>=0)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS recetas (
 id INT AUTO_INCREMENT PRIMARY KEY, producto_id INT NOT NULL UNIQUE, rendimiento DECIMAL(13,3) NOT NULL,
 actualizado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(producto_id) REFERENCES productos(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS receta_ingredientes (
 receta_id INT NOT NULL, ingrediente_id INT NOT NULL, cantidad DECIMAL(13,3) NOT NULL,
 PRIMARY KEY(receta_id,ingrediente_id),
 FOREIGN KEY(receta_id) REFERENCES recetas(id), FOREIGN KEY(ingrediente_id) REFERENCES ingredientes(id),
 CHECK(cantidad>0)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS facturas (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, proveedor_id INT NULL, proveedor_nombre VARCHAR(120) NOT NULL DEFAULT '',
 numero VARCHAR(100) NOT NULL DEFAULT '', numero_confirmado VARCHAR(100) NULL,
 fecha_emision DATE NULL, estado ENUM('borrador','confirmada','cancelada') NOT NULL DEFAULT 'borrador',
 version INT NOT NULL DEFAULT 1,
 total DECIMAL(13,2) NOT NULL DEFAULT 0,
 archivo VARCHAR(200) NULL, archivo_hash CHAR(64) NULL,
 ocr_texto MEDIUMTEXT NULL, ocr_estado ENUM('pendiente','procesado','no_disponible','error') NOT NULL DEFAULT 'pendiente',
 ocr_alertas TEXT NULL,
 creado_por INT NULL, confirmado_por INT NULL, creado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 confirmado DATETIME NULL,
 FOREIGN KEY(proveedor_id) REFERENCES proveedores(id), FOREIGN KEY(creado_por) REFERENCES empleados(id),
 FOREIGN KEY(confirmado_por) REFERENCES empleados(id),
 UNIQUE KEY factura_confirmada_unica(proveedor_id,numero_confirmado),
 INDEX idx_factura_fecha(fecha_emision), INDEX idx_factura_hash(archivo_hash)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS factura_renglones (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, factura_id BIGINT NOT NULL,
 ingrediente_id INT NOT NULL, ingrediente_nombre VARCHAR(120) NOT NULL,
 unidad VARCHAR(12) NOT NULL, cantidad DECIMAL(13,3) NOT NULL,
 costo_total DECIMAL(13,2) NOT NULL,
 FOREIGN KEY(factura_id) REFERENCES facturas(id), FOREIGN KEY(ingrediente_id) REFERENCES ingredientes(id),
 UNIQUE KEY renglon_producto(factura_id,ingrediente_id), CHECK(cantidad>0)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS operaciones (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, clave VARCHAR(64) NOT NULL UNIQUE,
 tipo VARCHAR(30) NOT NULL, empleado_id INT NOT NULL,
 fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(empleado_id) REFERENCES empleados(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS retiros (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, ingrediente_id INT NOT NULL,
 cantidad DECIMAL(13,3) NOT NULL, empleado_id INT NOT NULL,
 fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 revertido DATETIME NULL, revertido_por INT NULL,
 FOREIGN KEY(ingrediente_id) REFERENCES ingredientes(id), FOREIGN KEY(empleado_id) REFERENCES empleados(id),
 FOREIGN KEY(revertido_por) REFERENCES empleados(id), INDEX idx_retiros_fecha(fecha)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS producciones (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, producto_id INT NOT NULL, lotes INT NOT NULL,
 cantidad DECIMAL(13,3) NOT NULL, costo_total DECIMAL(13,2) NULL,
 costo_unitario DECIMAL(13,4) NULL,
 empleado_id INT NOT NULL, fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(producto_id) REFERENCES productos(id), FOREIGN KEY(empleado_id) REFERENCES empleados(id),
 INDEX idx_prod_fecha(fecha)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS consumos (
 produccion_id BIGINT NOT NULL, ingrediente_id INT NOT NULL,
 cantidad DECIMAL(13,3) NOT NULL, costo_unitario DECIMAL(13,4) NULL,
 PRIMARY KEY(produccion_id,ingrediente_id),
 FOREIGN KEY(produccion_id) REFERENCES producciones(id), FOREIGN KEY(ingrediente_id) REFERENCES ingredientes(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS ventas (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, producto_id INT NOT NULL, cantidad DECIMAL(13,3) NOT NULL,
 precio_unitario DECIMAL(13,2) NOT NULL, ingreso DECIMAL(13,2) NOT NULL,
 costo_unitario DECIMAL(13,4) NULL, empleado_id INT NOT NULL,
 fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, revertido DATETIME NULL, revertido_por INT NULL,
 FOREIGN KEY(producto_id) REFERENCES productos(id), FOREIGN KEY(empleado_id) REFERENCES empleados(id),
 FOREIGN KEY(revertido_por) REFERENCES empleados(id), INDEX idx_ventas_fecha(fecha)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS descartes (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, producto_id INT NOT NULL, cantidad DECIMAL(13,3) NOT NULL,
 motivo ENUM('vencido','sin_vender','error_elaboracion','quemado','danado','otro') NOT NULL,
 costo_unitario DECIMAL(13,4) NULL, costo_total DECIMAL(13,2) NULL,
 empleado_id INT NOT NULL, fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 revertido DATETIME NULL, revertido_por INT NULL,
 FOREIGN KEY(producto_id) REFERENCES productos(id), FOREIGN KEY(empleado_id) REFERENCES empleados(id),
 FOREIGN KEY(revertido_por) REFERENCES empleados(id), INDEX idx_descartes_fecha(fecha)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS movimientos (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, tipo VARCHAR(35) NOT NULL,
 ingrediente_id INT NULL, producto_id INT NULL,
 origen VARCHAR(30) NULL, destino VARCHAR(30) NULL,
 cantidad DECIMAL(13,3) NOT NULL, costo_unitario DECIMAL(13,4) NULL,
 referencia_tipo VARCHAR(30) NOT NULL, referencia_id BIGINT NOT NULL,
 empleado_id INT NULL, detalle VARCHAR(300) NOT NULL DEFAULT '',
 fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(ingrediente_id) REFERENCES ingredientes(id), FOREIGN KEY(producto_id) REFERENCES productos(id),
 FOREIGN KEY(empleado_id) REFERENCES empleados(id),
 INDEX idx_mov_fecha(fecha), INDEX idx_mov_referencia(referencia_tipo,referencia_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS auditoria (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, empleado_id INT NULL,
 evento VARCHAR(80) NOT NULL, entidad VARCHAR(80) NOT NULL, entidad_id BIGINT NOT NULL,
 antes JSON NULL, despues JSON NULL, ip VARCHAR(45) NOT NULL DEFAULT '',
 fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(empleado_id) REFERENCES empleados(id), INDEX idx_audit_fecha(fecha)
) ENGINE=InnoDB;
