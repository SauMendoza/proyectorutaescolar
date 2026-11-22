// JavaScript del módulo de estudiantes.
// 1) Listado: filtros y páginas sin recargar.
// 2) Nuevo estudiante: un paso a la vez, búsqueda de acudiente y resumen.
// 3) Ficha: código QR del carné.
// La validación real la hace EstudianteController; aquí solo se mejora el uso.

// Quita tildes y mayúsculas para comparar textos ("María" = "maria")
function normalizar(texto) {
    return texto.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
}

// ---------------------------------------------------------------------------
// 1) Listado de estudiantes
// ---------------------------------------------------------------------------
(function () {
    const tabla = document.getElementById('tabla-estudiantes');
    if (!tabla) return;

    const POR_PAGINA = 8;
    const filas = Array.from(tabla.tBodies[0].rows);
    const texto = document.getElementById('filtro-texto');
    const ruta = document.getElementById('filtro-ruta');
    const parada = document.getElementById('filtro-parada');
    const curso = document.getElementById('filtro-curso');
    const resumen = document.getElementById('resumen-tabla');
    const paginas = document.getElementById('paginas');
    const sinResultados = document.getElementById('sin-resultados');
    let pagina = 1;

    filas.forEach(function (fila) {
        fila.dataset.buscar = normalizar(fila.dataset.buscar);
    });

    function aplicar() {
        const buscado = normalizar(texto.value.trim());

        const visibles = filas.filter(function (fila) {
            return (!buscado || fila.dataset.buscar.includes(buscado))
                && (!ruta.value || fila.dataset.ruta === ruta.value)
                && (!parada.value || fila.dataset.parada === parada.value)
                && (!curso.value || fila.dataset.curso === curso.value);
        });

        const totalPaginas = Math.max(1, Math.ceil(visibles.length / POR_PAGINA));
        pagina = Math.min(pagina, totalPaginas);
        const desde = (pagina - 1) * POR_PAGINA;

        filas.forEach(function (fila) { fila.hidden = true; });
        visibles.slice(desde, desde + POR_PAGINA).forEach(function (fila) { fila.hidden = false; });

        sinResultados.hidden = visibles.length > 0;
        resumen.textContent = visibles.length === 0
            ? 'Mostrando 0 de ' + filas.length + ' estudiantes'
            : 'Mostrando ' + (desde + 1) + '–' + Math.min(desde + POR_PAGINA, visibles.length)
              + ' de ' + visibles.length + ' estudiantes';

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

    // Al elegir una ruta, solo se muestran sus paradas
    ruta.addEventListener('change', function () {
        Array.from(parada.options).forEach(function (opcion) {
            opcion.hidden = Boolean(opcion.value && ruta.value && opcion.dataset.ruta !== ruta.value);
        });
        if (parada.selectedOptions[0] && parada.selectedOptions[0].hidden) parada.value = '';
    });

    [texto, ruta, parada, curso].forEach(function (campo) {
        campo.addEventListener('input', function () { pagina = 1; aplicar(); });
    });

    // Si se llega desde Rutas o Cursos (?ruta=Ruta 1 / ?curso=6A), el filtro ya viene puesto
    const parametros = new URLSearchParams(window.location.search);
    if (parametros.get('ruta')) {
        ruta.value = parametros.get('ruta');
        ruta.dispatchEvent(new Event('change'));
    }
    if (parametros.get('curso')) curso.value = parametros.get('curso');

    aplicar();
})();

// ---------------------------------------------------------------------------
// 2) Formulario de nuevo estudiante
// ---------------------------------------------------------------------------
(function () {
    const formulario = document.getElementById('form-nuevo');
    if (!formulario) return;

    const base = formulario.dataset.base;
    const secciones = Array.from(formulario.querySelectorAll('.paso'));
    const botonesPaso = Array.from(document.querySelectorAll('#pasos button'));
    const errorPaso = document.getElementById('error-paso');
    const botonAtras = formulario.querySelector('[data-accion="atras"]');
    const botonSiguiente = formulario.querySelector('[data-accion="siguiente"]');
    const botonGuardar = formulario.querySelector('[data-accion="guardar"]');
    const selectRuta = document.getElementById('campo-ruta');
    const selectParada = document.getElementById('campo-parada');
    const TOTAL = secciones.length;
    let actual = 1;
    let maximo = Number(formulario.dataset.pasoInicial) || 1;

    // ----- Pasos -----
    function mostrarPaso(numero) {
        actual = numero;
        maximo = Math.max(maximo, numero);
        secciones.forEach(function (seccion) {
            seccion.classList.toggle('activo', Number(seccion.dataset.paso) === numero);
        });
        botonesPaso.forEach(function (boton) {
            const n = Number(boton.dataset.ir);
            boton.parentElement.classList.toggle('activo', n === numero);
            boton.parentElement.classList.toggle('hecho', n < numero);
            boton.disabled = n > maximo;
        });
        botonAtras.hidden = numero === 1;
        botonSiguiente.hidden = numero === TOTAL;
        botonGuardar.hidden = numero !== TOTAL;
        botonSiguiente.textContent = numero === 3 ? 'Continuar con el suplente' : 'Continuar';
        ocultarError();
    }

    function mostrarError(mensaje) {
        errorPaso.textContent = mensaje;
        errorPaso.hidden = false;
    }

    function ocultarError() {
        errorPaso.hidden = true;
    }

    // Revisa los campos del paso antes de avanzar
    function pasoValido(numero) {
        const seccion = secciones[numero - 1];
        const acudiente = seccion.querySelector('.acudiente');
        if (acudiente) return acudienteValido(acudiente);

        const campos = Array.from(seccion.querySelectorAll('input, select'));
        for (const campo of campos) {
            if (!campo.checkValidity()) {
                campo.reportValidity();
                return false;
            }
        }
        return true;
    }

    botonesPaso.forEach(function (boton) {
        boton.addEventListener('click', function () { mostrarPaso(Number(boton.dataset.ir)); });
    });

    botonAtras.addEventListener('click', function () { mostrarPaso(actual - 1); });

    botonSiguiente.addEventListener('click', function () {
        if (pasoValido(actual)) mostrarPaso(actual + 1);
    });

    formulario.addEventListener('submit', function (evento) {
        for (let n = 1; n <= TOTAL; n++) {
            if (!pasoValido(n)) {
                evento.preventDefault();
                mostrarPaso(n);
                pasoValido(n);
                return;
            }
        }
        botonGuardar.disabled = true;
        botonGuardar.textContent = 'Guardando…';
    });

    // ----- Paradas de la ruta elegida -----
    function filtrarParadas() {
        Array.from(selectParada.querySelectorAll('optgroup')).forEach(function (grupo) {
            const visible = grupo.dataset.ruta === selectRuta.value;
            grupo.hidden = !visible;
            grupo.disabled = !visible;
        });
        const elegida = selectParada.selectedOptions[0];
        if (elegida && elegida.parentElement.disabled) selectParada.value = '';
    }

    selectRuta.addEventListener('change', filtrarParadas);

    // ----- Acudientes: buscar por documento, usar o crear -----
    // estado: 'vacio', 'existe' (encontrado sin usar), 'usado' o 'nuevo'
    const acudientes = Array.from(formulario.querySelectorAll('.acudiente'));

    acudientes.forEach(function (bloque) {
        const documento = bloque.querySelector('input[name$="_documento"]');
        const tarjeta = bloque.querySelector('.encontrado');
        const camposNuevo = bloque.querySelector('.campos-nuevo');
        const botonUsar = bloque.querySelector('[data-accion="usar"]');

        bloque.dataset.estado = 'vacio';
        camposNuevo.hidden = true;

        function reiniciar() {
            bloque.dataset.estado = 'vacio';
            tarjeta.hidden = true;
            camposNuevo.hidden = true;
            botonUsar.disabled = false;
            botonUsar.textContent = 'Usar';
            actualizarResumen();
        }

        async function buscar() {
            ocultarError();
            const valor = documento.value.trim();
            if (!/^[A-Za-z0-9]{4,20}$/.test(valor)) {
                mostrarError('Escribe un documento de 4 a 20 letras o números.');
                return;
            }
            try {
                const respuesta = await fetch(base + '/estudiantes/acudiente?documento=' + encodeURIComponent(valor));
                const datos = await respuesta.json();
                if (datos.encontrado) {
                    bloque.dataset.estado = 'existe';
                    bloque.dataset.nombre = datos.nombre;
                    tarjeta.querySelector('.inicial').textContent = datos.nombre.charAt(0).toUpperCase();
                    tarjeta.querySelector('.encontrado-nombre').textContent = datos.nombre;
                    tarjeta.querySelector('.encontrado-detalle').textContent = 'Ya registrado'
                        + (datos.telefono ? ' · tel. ' + datos.telefono : '')
                        + ' · representa a ' + datos.estudiantes
                        + (datos.estudiantes === 1 ? ' estudiante' : ' estudiantes');
                    tarjeta.hidden = false;
                    camposNuevo.hidden = true;
                } else {
                    bloque.dataset.estado = 'nuevo';
                    tarjeta.hidden = true;
                    camposNuevo.hidden = false;
                    camposNuevo.querySelector('input').focus();
                }
            } catch (error) {
                mostrarError('No se pudo buscar el acudiente. Revisa tu sesión e intenta de nuevo.');
            }
            actualizarResumen();
        }

        bloque.querySelector('[data-accion="buscar"]').addEventListener('click', buscar);

        documento.addEventListener('input', reiniciar);
        documento.addEventListener('keydown', function (evento) {
            if (evento.key === 'Enter') {
                evento.preventDefault();
                buscar();
            }
        });

        botonUsar.addEventListener('click', function () {
            bloque.dataset.estado = 'usado';
            botonUsar.disabled = true;
            botonUsar.textContent = 'En uso ✓';
            actualizarResumen();
        });

        // Si el formulario volvió con errores, se repite la búsqueda
        if (documento.value.trim() !== '') {
            buscar().then(function () {
                if (bloque.dataset.estado === 'existe') botonUsar.click();
            });
        }
    });

    function acudienteValido(bloque) {
        const tipo = bloque.dataset.tipo;
        const obligatorio = bloque.dataset.obligatorio === '1';
        const documento = bloque.querySelector('input[name$="_documento"]').value.trim();
        const estado = bloque.dataset.estado;

        if (documento === '') {
            if (obligatorio) {
                mostrarError('Escribe el documento del acudiente ' + tipo + ' y pulsa Buscar.');
                return false;
            }
            return true;
        }
        if (estado === 'vacio') {
            mostrarError('Pulsa Buscar para revisar si el acudiente ya existe.');
            return false;
        }
        if (estado === 'existe') {
            mostrarError('Pulsa Usar para elegir a este acudiente.');
            return false;
        }
        if (estado === 'nuevo') {
            const nombre = bloque.querySelector('input[name$="_nombre"]');
            const apellido = bloque.querySelector('input[name$="_apellido"]');
            if (nombre.value.trim() === '' || apellido.value.trim() === '') {
                mostrarError('Escribe el nombre y el apellido del acudiente.');
                return false;
            }
        }
        if (!bloque.querySelector('input[type="radio"]:checked')) {
            mostrarError('Elige el parentesco con el estudiante.');
            return false;
        }
        const principal = formulario.querySelector('input[name="principal_documento"]').value.trim();
        if (tipo === 'suplente' && documento === principal) {
            mostrarError('El suplente debe ser una persona distinta al principal.');
            return false;
        }
        return true;
    }

    // ----- Resumen -----
    function valor(nombre) {
        const campo = formulario.elements[nombre];
        return campo ? campo.value.trim() : '';
    }

    function textoElegido(select) {
        const opcion = select.selectedOptions[0];
        return opcion && opcion.value ? opcion : null;
    }

    function poner(id, texto) {
        document.getElementById(id).textContent = texto || '—';
    }

    function resumenAcudiente(tipo) {
        const bloque = formulario.querySelector('.acudiente[data-tipo="' + tipo + '"]');
        const parentesco = bloque.querySelector('input[type="radio"]:checked');
        let nombre = '';
        if (bloque.dataset.estado === 'usado' || bloque.dataset.estado === 'existe') {
            nombre = bloque.dataset.nombre;
        } else if (bloque.dataset.estado === 'nuevo') {
            nombre = (valor(tipo + '_nombre') + ' ' + valor(tipo + '_apellido')).trim();
        }
        if (!nombre) return tipo === 'suplente' && actual === TOTAL ? 'Sin suplente' : 'Pendiente';
        return nombre + (parentesco ? ' · ' + parentesco.dataset.nombre : '');
    }

    function actualizarResumen() {
        const nombre = [valor('nombre'), valor('segundo_nombre'), valor('apellido'), valor('segundo_apellido')]
            .filter(Boolean).join(' ');
        poner('r-estudiante', nombre);
        poner('r-documento', valor('documento') ? (valor('tipo_documento') + ' ' + valor('documento')).trim() : '');

        const fecha = valor('fecha_nacimiento');
        const curso = textoElegido(formulario.elements.curso);
        const partes = [];
        if (fecha) partes.push(fecha.split('-').reverse().join('/'));
        if (curso) partes.push(curso.textContent);
        poner('r-nacimiento', partes.join(' · '));

        const ruta = textoElegido(selectRuta);
        poner('r-ruta', ruta ? ruta.textContent : '');
        poner('r-conductor', ruta ? ruta.dataset.conductor : '');

        const parada = textoElegido(selectParada);
        poner('r-parada', parada ? parada.textContent.split(' · ')[0] + ' (' + parada.dataset.orden + ')' : '');

        poner('r-principal', resumenAcudiente('principal'));
        poner('r-suplente', resumenAcudiente('suplente'));
    }

    formulario.addEventListener('input', actualizarResumen);
    formulario.addEventListener('change', actualizarResumen);

    filtrarParadas();
    mostrarPaso(Number(formulario.dataset.pasoInicial) || 1);
    actualizarResumen();
})();

// ---------------------------------------------------------------------------
// 3) Ficha: dibuja el QR del carné (lleva el documento) y lo imprime
// ---------------------------------------------------------------------------
(function () {
    const caja = document.querySelector('.carne-qr[data-qr]');
    if (!caja || typeof qrcode !== 'function') return;

    const codigo = qrcode(0, 'M');
    codigo.addData(caja.dataset.qr);
    codigo.make();
    caja.innerHTML = codigo.createSvgTag({ cellSize: 6, margin: 2, scalable: true });

    // Al imprimir solo sale el carné (ver @media print en estudiantes.css)
    document.querySelector('[data-imprimir-carne]').addEventListener('click', function () {
        document.body.classList.add('imprimiendo-carne');
        window.print();
    });
    window.addEventListener('afterprint', function () {
        document.body.classList.remove('imprimiendo-carne');
    });
})();
