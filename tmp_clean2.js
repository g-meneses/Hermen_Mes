const fs = require('fs');
const files = [
    'materias_primas_dinamico.js',
    'colorantes_quimicos_dinamico.js',
    'empaque_dinamico.js',
    'accesorios_dinamico.js',
    'repuestos_dinamico.js',
    'productos_terminados_dinamico.js',
    'materias_primas.js',
    'colorantes_quimicos.js',
    'empaque.js',
    'accesorios.js',
    'repuestos.js',
    'productos_terminados.js'
];

let targetOriginal = `                data.tipos.forEach(tipo => {
                    select.innerHTML += \\\`
                        <option value="\\$\\{tipo.id_tipo_ingreso\\}" data-codigo="\\$\\{tipo.codigo\\}">
                            \\$\\{tipo.nombre\\}
                        </option>
                    \\\`;
                });`;

let replacement = `                data.tipos.forEach(tipo => {
                    if (tipo.codigo !== 'COMPRA') {
                        select.innerHTML += \`
                            <option value="\${tipo.id_tipo_ingreso}" data-codigo="\${tipo.codigo}">
                                \${tipo.nombre}
                            </option>
                        \`;
                    }
                });`;

files.forEach(f => {
    const p = 'c:\\xampp\\htdocs\\mes_hermen\\modules\\inventarios\\js\\' + f;
    if (fs.existsSync(p)) {
        let content = fs.readFileSync(p, 'utf8');

        // Match the blocks using a more flexible regex since whitespace might vary
        let regex = /data\.tipos\.forEach\(tipo\s*=>\s*\{[\s\S]*?select\.innerHTML\s*\+=\s*`[\s\S]*?<option value="\$\{tipo\.id_tipo_ingreso\}" data-codigo="\$\{tipo\.codigo\}"\>[\s\S]*?\$\{tipo\.nombre\}[\s\S]*?<\/option>[\s\S]*?`;\s*\}\);/g;

        if (regex.test(content)) {
            content = content.replace(regex, replacement);
            fs.writeFileSync(p, content, 'utf8');
            console.log('Processed', f);
        } else {
            console.log('Regex un-matched in', f);
        }
    }
});
