const fs = require('fs');
const files = [
    'materias_primas_dinamico.js',
    'colorantes_quimicos_dinamico.js',
    'empaque_dinamico.js',
    'accesorios_dinamico.js',
    'repuestos_dinamico.js',
    'productos_terminados_dinamico.js'
];

let correctBlock = `            data.tipos.forEach(tipo => {
                tiposIngresoConfig[tipo.id_tipo_ingreso] = tipo;
            });

            const select = document.getElementById('ingresoTipoIngreso');
            if (select) {
                select.innerHTML = '<option value="">Seleccione tipo de ingreso...</option>';
                data.tipos.forEach(tipo => {
                    if (tipo.codigo !== 'COMPRA') {
                        select.innerHTML += \`
                            <option value="\${tipo.id_tipo_ingreso}" data-codigo="\${tipo.codigo}">
                                \${tipo.nombre}
                            </option>
                        \`;
                    }
                });
            }`;

files.forEach(f => {
    const p = 'c:\\xampp\\htdocs\\mes_hermen\\modules\\inventarios\\js\\' + f;
    if (fs.existsSync(p)) {
        let content = fs.readFileSync(p, 'utf8');

        let badRegex = /data\.tipos\.forEach\(tipo\s*=>\s*\{\s*if\s*\(tipo\.codigo\s*!==\s*'COMPRA'\)[\s\S]*?\}\s*\);\s*\}/g;

        if (badRegex.test(content)) {
            content = content.replace(badRegex, correctBlock);
            fs.writeFileSync(p, content, 'utf8');
            console.log('Fixed', f);
        } else {
            console.log('Un-matched in', f);
        }
    }
});
