// JavaScript del módulo de acudientes.
// 1) Listado: búsqueda, pestañas Todos / Principales / Suplentes y páginas.
// 2) Ventana "Nuevo acudiente": abrir y cerrar.

// Quita tildes y mayúsculas para comparar textos ("Gouriyú" = "gouriyu")
function normalizar(texto) {
    return texto.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
}

// ---------------------------------------------------------------------------
// 1) Listado
// ---------------------------------------------------------------------------
(function () {
    const tabla = document.getElementById('tabla-acudientes');
    if (!tabla) return;

    const POR_PAGINA = 8;
    const filas = Array.from(tabla.tBodies[0].rows);
    const texto = document.getElementById('filtro-texto');
    const pestanas = Array.from(document.querySelectorAll('.pestanas-linea button'));
    const resumen = document.getElementById('resumen-tabla');
    const paginas = document.getElementById('paginas');
    const sinResultados = document.getElementById('sin-resultados');
    let tipo = '';
    let pagina = 1;

    filas.forEach(function (fila) {
        fila.dataset.buscar = normalizar(fila.dataset.buscar);
    });

    function aplicar() {
        const buscado = normalizar(texto.value.trim());
        const visibles = filas.filter(function (fila) {
            return (!buscado || fila.dataset.buscar.includes(buscado))
                && (!tipo || fila.dataset.tipos.split(' ').includes(tipo));
        });

        const totalPaginas = Math.max(1, Math.ceil(visibles.length / POR_PAGINA));
        pagina = Math.min(pagina, totalPaginas);
        const desde = (pagina - 1) * POR_PAGINA;

        filas.forEach(function (fila) { fila.hidden = true; });
        visibles.slice(desde, desde + POR_PAGINA).forEach(function (fila) { fila.hidden = false; });

        sinResultados.hidden = visibles.length > 0;
        resumen.textContent = visibles.length === 0
            ? 'Mostrando 0 de ' + filas.length + ' acudientes'
            : (desde + 1) + '–' + Math.min(desde + POR_PAGINA, visibles.length) + ' de ' + visibles.length + ' acudientes';

        paginas.innerHTML = '';
        if (totalPaginas > 1) {
            for (let i = 1; i <= totalPaginas; i++) {
                const boton = document.createElement('button');
                boton.type = 'button';
                boton.textContent = i;
                if (i === pagina) boton.setAttribute('aria-current', 'page');
                boton.addEventListener('click', function () { pagina = i; aplicar(); });
                paginas.appendChild(boton);
            }
        }
    }

    pestanas.forEach(function (boton) {
        boton.addEventListener('click', function () {
            pestanas.forEach(function (otro) {
                otro.classList.toggle('activo', otro === boton);
                otro.setAttribute('aria-pressed', otro === boton ? 'true' : 'false');
            });
            tipo = boton.dataset.tipo;
            pagina = 1;
            aplicar();
        });
    });

    texto.addEventListener('input', function () { pagina = 1; aplicar(); });
    aplicar();
})();

// ---------------------------------------------------------------------------
// 2) Ventanas: botón con data-abrir-ventana="id" la abre; data-cerrar-ventana la cierra.
//    Si la ventana trae data-abrir (hubo errores al guardar), se abre sola.
// ---------------------------------------------------------------------------
document.querySelectorAll('[data-abrir-ventana]').forEach(function (boton) {
    boton.addEventListener('click', function () {
        document.getElementById(boton.dataset.abrirVentana).showModal();
    });
});

document.querySelectorAll('dialog.ventana').forEach(function (ventana) {
    ventana.querySelectorAll('[data-cerrar-ventana]').forEach(function (boton) {
        boton.addEventListener('click', function () { ventana.close(); });
    });
    if (ventana.hasAttribute('data-abrir')) ventana.showModal();
});
