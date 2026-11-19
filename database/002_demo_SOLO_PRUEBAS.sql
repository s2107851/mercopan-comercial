-- ATENCIÓN: solo cargar en una instalación DE PRUEBAS con catálogos vacíos.
USE mercopan_comercial;
INSERT INTO empleados(nombre,rol) VALUES ('Operador demo','operario'),('Encargado demo','supervisor');
INSERT INTO proveedores(nombre,rut) VALUES ('Proveedor demo','');
INSERT INTO ingredientes(nombre,unidad,categoria,icono,atajos,minimo_deposito) VALUES
 ('Harina','kg','Secos','🌾','1,5,10,25',25),
 ('Huevos','unidad','Frescos','🥚','6,12,30',60),
 ('Aceite','l','Líquidos','🛢️','0.5,1,5',5),
 ('Levadura','kg','Secos','🍞','0.1,0.5,1',2),
 ('Sal','kg','Secos','🧂','0.1,0.5,1',2);
INSERT INTO productos(nombre,unidad,categoria,precio,icono,atajos) VALUES
 ('Pan flauta','unidad','Panadería',65,'🥖','1,2,5,10'),
 ('Bizcocho','unidad','Panadería',45,'🥐','1,6,12');
INSERT INTO recetas(producto_id,rendimiento) VALUES(1,20);
INSERT INTO receta_ingredientes(receta_id,ingrediente_id,cantidad) VALUES
 (1,1,5),(1,4,0.12),(1,5,0.08);
-- El inventario de demostración se inicia en cero: ingresá una factura en Administración.
