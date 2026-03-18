const fs = require('fs');
const path = require('path');

const dir = 'c:/xampp/htdocs/mes_hermen/modules/inventarios/js';
const files = [
    'materias_primas_dinamico.js', 'materias_primas.js',
    'accesorios_dinamico.js', 'accesorios.js',
    'empaque_dinamico.js', 'empaque.js',
    'colorantes_quimicos_dinamico.js', 'colorantes_quimicos.js',
    'repuestos_dinamico.js', 'repuestos.js',
    'productos_terminados_dinamico.js', 'productos_terminados.js'
];

let successCount = 0;

files.forEach(file => {
    const filePath = path.join(dir, file);
    if (fs.existsSync(filePath)) {
        let content = fs.readFileSync(filePath, 'utf8');
        let modified = false;

        // Fix dinámicos
        if (file.includes('_dinamico')) {
            if (content.match(/if\s*\(\s*config\.requiere_autorizacion\s*\)\s*\{\s*mostrarSeccionAutorizacion\(\);\s*\}/)) {
                content = content.replace(/if\s*\(\s*config\.requiere_autorizacion\s*\)\s*\{\s*mostrarSeccionAutorizacion\(\);\s*\}/, "if (config.requiere_autorizacion && config.codigo !== 'AJUSTE_POS') {\n        mostrarSeccionAutorizacion();\n    }");
                modified = true;
            }
        } 
        // Fix principales
        else {
            // First block: Validation
            if (content.match(/if\s*\(\s*config\.requiere_autorizacion\s*\)\s*\{\s*const autorizadoPor/)) {
                content = content.replace(/if\s*\(\s*config\.requiere_autorizacion\s*\)\s*\{\s*const autorizadoPor/, "if (config.requiere_autorizacion && config.codigo !== 'AJUSTE_POS') {\n            const autorizadoPor");
                modified = true;
            }

            // Second block: Assignment
            if (content.match(/datosIngreso\.autorizado_por\s*=\s*parseInt\(document\.getElementById\('ingresoAutorizadoPor'\)\.value\);/)) {
                content = content.replace(/datosIngreso\.autorizado_por\s*=\s*parseInt\(document\.getElementById\('ingresoAutorizadoPor'\)\.value\);/g, "// No enviar autorizado_por");
                modified = true;
            }
        }

        if (modified) {
            fs.writeFileSync(filePath, content);
            console.log('Fixed', file);
            successCount++;
        } else {
            console.log('No matches found in:', file);
        }
    }
});
console.log(`Finished processing. Updated ${successCount} files.`);
