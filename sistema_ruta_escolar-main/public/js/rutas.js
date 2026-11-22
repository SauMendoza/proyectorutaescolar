// JavaScript del módulo de rutas (solo lo usa el administrador).
// 1) Abrir y cerrar las ventanas "Editar" y "Nueva ruta".
// 2) Mostrar los campos de "Registrar conductor".
// 3) Agregar y quitar paradas, y renumerarlas.
// La validación real la hace RutaController::guardar().

// ---------------------------------------------------------------------------
// 1) Ventanas
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

// ---------------------------------------------------------------------------
// 2) y 3) Formulario de cada ruta
// ---------------------------------------------------------------------------
document.querySelectorAll('.ventana-ruta form').forEach(function (formulario) {
    const conductor = formulario.querySelector('.elegir-conductor');
    const camposConductor = formulario.querySelector('.conductor-nuevo');
    const lista = formulario.querySelector('.editar-paradas');

    // Con "+ Registrar conductor" aparecen los campos del conductor nuevo
    conductor.addEventListener('change', function () {
        camposConductor.hidden = conductor.value !== 'nuevo';
        if (!camposConductor.hidden) camposConductor.querySelector('input').focus();
    });

    // Numera solo las paradas que se van a guardar
    function renumerar() {
        let numero = 1;
        lista.querySelectorAll('.fila-parada').forEach(function (fila) {
            if (!fila.hidden) fila.querySelector('.numero').textContent = numero++;
        });
    }

    // La "×" marca la parada para quitarla (si es nueva, se borra de la lista)
    function activarQuitar(fila) {
        fila.querySelector('.quitar-parada').addEventListener('click', function () {
            if (lista.querySelectorAll('.fila-parada').length === 1) {
                // Es la única fila: se vacía en vez de borrarla (sirve de modelo)
                fila.querySelectorAll('input[type="text"]').forEach(function (campo) { campo.value = ''; });
            } else if (fila.querySelector('input[name$="[id]"]').value === '') {
                fila.remove();
            } else {
                fila.querySelector('.quitar').checked = true;
                fila.hidden = true;
            }
            renumerar();
        });
    }

    lista.querySelectorAll('.fila-parada').forEach(activarQuitar);
    renumerar();

    // "+ Agregar parada" copia la primera fila con los campos vacíos
    formulario.querySelector('.agregar-parada').addEventListener('click', function () {
        const modelo = lista.querySelector('.fila-parada');
        const indice = Number(lista.dataset.siguiente);
        lista.dataset.siguiente = indice + 1;

        const fila = modelo.cloneNode(true);
        fila.hidden = false;
        fila.querySelectorAll('input').forEach(function (campo) {
            campo.name = campo.name.replace(/paradas\[\d+\]/, 'paradas[' + indice + ']');
            if (campo.type === 'checkbox') campo.checked = false;
            else campo.value = '';
        });
        fila.querySelector('.est').textContent = '0 est.';
        const quitar = fila.querySelector('.quitar-parada');
        quitar.disabled = false;
        quitar.removeAttribute('title');

        lista.appendChild(fila);
        activarQuitar(fila);
        renumerar();
        fila.querySelector('input[type="text"]').focus();
    });
});
