// JavaScript del módulo de cursos.
// 1) Pestañas Todas / Ruta 1 / Ruta 2: muestran solo los estudiantes de esa ruta.
// 2) Ventanas (nuevo, editar, eliminar): abrir y cerrar.

// ---------------------------------------------------------------------------
// 1) Pestañas por ruta
// ---------------------------------------------------------------------------
(function () {
    const grupo = document.querySelector('.pestanas[data-total]');
    if (!grupo) return;

    const botones = Array.from(grupo.querySelectorAll('button'));
    const estudiantes = Array.from(document.querySelectorAll('.estudiante-curso'));
    const conteo = document.getElementById('conteo-curso');

    botones.forEach(function (boton) {
        boton.addEventListener('click', function () {
            botones.forEach(function (otro) {
                otro.classList.toggle('activo', otro === boton);
                otro.setAttribute('aria-pressed', otro === boton ? 'true' : 'false');
            });

            let visibles = 0;
            estudiantes.forEach(function (estudiante) {
                const ver = !boton.dataset.ruta || estudiante.dataset.ruta === boton.dataset.ruta;
                estudiante.hidden = !ver;
                if (ver) visibles++;
            });
            conteo.textContent = visibles + ' de ' + grupo.dataset.total;
        });
    });
})();

// ---------------------------------------------------------------------------
// 2) Ventanas: data-abrir-ventana="id" la abre; data-cerrar-ventana la cierra.
//    Si trae data-abrir (hubo un error al guardar), se abre sola.
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
