// JavaScript de asistencia.
// 1) Registro del auxiliar: buscar estudiante y confirmar antes de deshacer.
// 2) Asistencias del administrador: cambiar de fecha y llenar la ventana "Registrar a mano".
// 3) Abrir y cerrar ventanas.
// 4) Escáner QR del carné.

// Quita tildes y mayúsculas para comparar textos
function normalizar(texto) {
    return texto.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
}

// ---------------------------------------------------------------------------
// 1) Registro del auxiliar
// ---------------------------------------------------------------------------
(function () {
    const buscador = document.getElementById('buscar-estudiante');
    if (!buscador) return;

    const filas = Array.from(document.querySelectorAll('.fila-registro'));
    const grupos = Array.from(document.querySelectorAll('.grupo-parada'));
    const sinResultados = document.getElementById('sin-resultados');

    filas.forEach(function (fila) { fila.dataset.buscar = normalizar(fila.dataset.buscar); });

    buscador.addEventListener('input', function () {
        const buscado = normalizar(buscador.value.trim());
        let visibles = 0;

        filas.forEach(function (fila) {
            fila.hidden = Boolean(buscado) && !fila.dataset.buscar.includes(buscado);
            if (!fila.hidden) visibles++;
        });

        // Un título de parada se oculta si no quedó ningún estudiante debajo
        grupos.forEach(function (grupo) {
            let siguiente = grupo.nextElementSibling;
            let alguno = false;
            while (siguiente && !siguiente.classList.contains('grupo-parada')) {
                if (!siguiente.hidden) alguno = true;
                siguiente = siguiente.nextElementSibling;
            }
            grupo.hidden = !alguno;
        });

        sinResultados.hidden = visibles > 0;
    });
})();

document.querySelectorAll('form[data-confirmar]').forEach(function (formulario) {
    formulario.addEventListener('submit', function (evento) {
        if (!window.confirm(formulario.dataset.confirmar)) evento.preventDefault();
    });
});

// Evita marcar dos veces si se toca el botón muy rápido
document.querySelectorAll('.fila-registro form').forEach(function (formulario) {
    formulario.addEventListener('submit', function () {
        setTimeout(function () { formulario.querySelector('button').disabled = true; }, 0);
    });
});

// ---------------------------------------------------------------------------
// 2) Asistencias del administrador
// ---------------------------------------------------------------------------
(function () {
    const fecha = document.querySelector('.elegir-fecha input[type="date"]');
    if (fecha) {
        fecha.addEventListener('change', function () {
            if (fecha.value) fecha.form.submit();
        });
    }

    // "Registrar a mano" en una fila deja elegidos el estudiante y el recorrido que falta
    const ventana = document.getElementById('ventana-manual');
    if (!ventana) return;

    document.querySelectorAll('[data-abrir-ventana="ventana-manual"][data-documento]').forEach(function (boton) {
        boton.addEventListener('click', function () {
            ventana.querySelector('select[name="documento"]').value = boton.dataset.documento;
            const tipo = ventana.querySelector('input[name="tipo"][value="' + boton.dataset.tipo + '"]');
            if (tipo) tipo.checked = true;
        });
    });
})();

// ---------------------------------------------------------------------------
// 3) Ventanas: data-abrir-ventana="id" la abre; data-cerrar-ventana la cierra.
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

// ---------------------------------------------------------------------------
// 4) Escáner QR del carné (auxiliar)
//    Lee el QR con la cámara (jsQR), lo manda a /asistencia/escanear y muestra
//    la ficha: verde se registró, amarillo ya estaba, rojo no se registró.
//    Sin cámara en vivo (por ejemplo, sin HTTPS) se puede tomar una foto del carné.
// ---------------------------------------------------------------------------
(function () {
    const escaner = document.getElementById('escaner');
    if (!escaner || typeof jsQR !== 'function') return;

    const video = document.getElementById('escaner-video');
    const visorTexto = document.getElementById('visor-texto');
    const fotoCarne = document.getElementById('foto-carne');
    const contador = document.getElementById('escaner-contador');
    const hojaFondo = document.getElementById('hoja-fondo');
    const hoja = document.getElementById('hoja');
    const lienzo = document.createElement('canvas');
    const pincel = lienzo.getContext('2d', { willReadFrequently: true });

    const esIda = escaner.dataset.tipo === 'IDA';
    const palabras = esIda
        ? { hizo: 'Subió', yaHizo: 'Ya subió', accion: 'la subida', grupo: 'a bordo' }
        : { hizo: 'Bajó', yaHizo: 'Ya bajó', accion: 'la bajada', grupo: 'bajaron' };

    let flujo = null;        // cámara encendida
    let ocupado = false;     // esperando respuesta o con la ficha abierta
    let hubCambios = false;  // al cerrar se recarga la lista
    let ultimoCodigo = '';
    let ultimoMomento = 0;

    // ----- Abrir y cerrar -----
    document.querySelectorAll('[data-abrir-escaner]').forEach(function (boton) {
        boton.addEventListener('click', function () {
            escaner.showModal();
            encenderCamara();
        });
    });

    escaner.querySelector('[data-cerrar-escaner]').addEventListener('click', function () { escaner.close(); });

    escaner.querySelector('[data-buscar-nombre]').addEventListener('click', function () {
        escaner.close();
        const buscador = document.getElementById('buscar-estudiante');
        if (buscador) buscador.focus();
    });

    // Escape cierra primero la ficha y después el escáner
    escaner.addEventListener('cancel', function (evento) {
        if (!hojaFondo.hidden) {
            evento.preventDefault();
            cerrarHoja();
        }
    });

    escaner.addEventListener('close', function () {
        apagarCamara();
        cerrarHoja();
        if (hubCambios) window.location.reload();
    });

    // ----- Cámara -----
    function encenderCamara() {
        escaner.classList.remove('con-video');
        fotoCarne.hidden = true;

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            sinCamara('Este navegador no deja usar la cámara en vivo aquí. Toma una foto del carné.');
            return;
        }

        visorTexto.textContent = 'Abriendo la cámara…';
        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false })
            .then(function (camara) {
                if (!escaner.open) {
                    camara.getTracks().forEach(function (pista) { pista.stop(); });
                    return;
                }
                flujo = camara;
                video.srcObject = camara;
                return video.play().then(function () {
                    escaner.classList.add('con-video');
                    visorTexto.textContent = 'Apunta al código del carné';
                    requestAnimationFrame(buscarCodigo);
                });
            })
            .catch(function () {
                sinCamara('No se pudo abrir la cámara. Revisa el permiso o toma una foto del carné.');
            });
    }

    function sinCamara(texto) {
        visorTexto.textContent = texto;
        fotoCarne.hidden = false;
    }

    function apagarCamara() {
        if (flujo) flujo.getTracks().forEach(function (pista) { pista.stop(); });
        flujo = null;
        video.srcObject = null;
        escaner.classList.remove('con-video');
    }

    // Revisa unas 6 imágenes por segundo, reducidas para que el celular no se canse
    let ultimaRevision = 0;
    function buscarCodigo(momento) {
        if (!flujo) return;
        requestAnimationFrame(buscarCodigo);
        if (ocupado || momento - ultimaRevision < 160 || video.readyState < 2) return;
        ultimaRevision = momento;

        const codigo = leerImagen(video, video.videoWidth, video.videoHeight);
        if (codigo) recibirCodigo(codigo);
    }

    function leerImagen(fuente, ancho, alto) {
        if (!ancho || !alto) return null;
        const escala = Math.min(1, 640 / Math.max(ancho, alto));
        lienzo.width = Math.round(ancho * escala);
        lienzo.height = Math.round(alto * escala);
        pincel.drawImage(fuente, 0, 0, lienzo.width, lienzo.height);
        const imagen = pincel.getImageData(0, 0, lienzo.width, lienzo.height);
        const resultado = jsQR(imagen.data, imagen.width, imagen.height, { inversionAttempts: 'attemptBoth' });
        return resultado && resultado.data.trim() ? resultado.data.trim() : null;
    }

    // Foto del carné (cuando no hay cámara en vivo)
    fotoCarne.querySelector('input').addEventListener('change', function (evento) {
        const archivo = evento.target.files[0];
        evento.target.value = '';
        if (!archivo) return;

        const imagen = new Image();
        imagen.onload = function () {
            const codigo = leerImagen(imagen, imagen.naturalWidth, imagen.naturalHeight);
            URL.revokeObjectURL(imagen.src);
            if (codigo) recibirCodigo(codigo, true);
            else visorTexto.textContent = 'No se encontró un código en la foto. Intenta más cerca y con buena luz.';
        };
        imagen.src = URL.createObjectURL(archivo);
    });

    // ----- Registrar -----
    function recibirCodigo(codigo, desdeFoto) {
        // El mismo carné apenas cerrada su ficha se ignora un momento
        if (!desdeFoto && codigo === ultimoCodigo && Date.now() - ultimoMomento < 2500) return;
        ocupado = true;
        ultimoCodigo = codigo;
        if (navigator.vibrate) navigator.vibrate(60);
        visorTexto.textContent = 'Leyendo carné…';

        enviar(escaner.dataset.escanear, { documento: codigo, tipo: escaner.dataset.tipo })
            .then(function (datos) {
                if (datos.resultado === 'registrado' || datos.resultado === 'cerrado') hubCambios = true;
                actualizarContador(datos);
                mostrarHoja(datos);
            })
            .catch(function () {
                ocupado = false;
                visorTexto.textContent = 'No se pudo registrar. Revisa la conexión e intenta de nuevo.';
            });
    }

    function enviar(direccion, campos) {
        const token = document.querySelector('input[name="csrf_token"]');
        if (token) campos.csrf_token = token.value;

        return fetch(direccion, {
            method: 'POST',
            headers: { Accept: 'application/json' },
            body: new URLSearchParams(campos),
        }).then(function (respuesta) {
            if (!respuesta.ok && respuesta.status !== 400) throw new Error('HTTP ' + respuesta.status);
            return respuesta.json();
        });
    }

    function actualizarContador(datos) {
        if (typeof datos.a_bordo === 'number') {
            contador.textContent = datos.a_bordo + ' de ' + datos.total + ' ' + palabras.grupo;
        }
    }

    // ----- Ficha del estudiante -----
    const ICONOS = {
        check: '<path d="m6 12.5 4 4 8-9"/>',
        alerta: '<path d="M12 6v8M12 18v.5"/>',
        reloj: '<circle cx="12" cy="12" r="8"/><path d="M12 8v4l3 2"/>',
        persona: '<circle cx="12" cy="9" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        parada: '<path d="M12 21s-7-6.5-7-11.5a7 7 0 0 1 14 0C19 14.5 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.5"/>',
        bus: '<rect x="4" y="4" width="16" height="14" rx="3"/><path d="M4 11h16M8 15h1.5M14.5 15H16M7 18v2M17 18v2"/>',
        acudiente: '<circle cx="9" cy="7" r="3"/><path d="M3 20v-3a6 6 0 0 1 12 0v3"/><circle cx="17.5" cy="10" r="2"/><path d="M15 20v-2a3 3 0 0 1 6 0v2"/>',
        telefono: '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>',
    };

    function icono(nombre) {
        return '<svg viewBox="0 0 24 24" aria-hidden="true">' + ICONOS[nombre] + '</svg>';
    }

    // Crea un elemento con texto (nunca HTML que venga del servidor)
    function crear(etiqueta, clase, texto) {
        const elemento = document.createElement(etiqueta);
        if (clase) elemento.className = clase;
        if (texto !== undefined) elemento.textContent = texto;
        return elemento;
    }

    function fila(nombreIcono, partes, telefono) {
        const contenedor = crear('div', 'hoja-fila');
        contenedor.insertAdjacentHTML('beforeend', icono(nombreIcono));
        const texto = crear('span', 'hoja-fila-texto');
        partes.forEach(function (parte) {
            texto.append(typeof parte === 'string' ? document.createTextNode(parte) : parte);
        });
        contenedor.append(texto);
        if (telefono) {
            const llamar = crear('a', 'llamar');
            llamar.href = 'tel:' + telefono.replace(/[^\d+]/g, '');
            llamar.setAttribute('aria-label', 'Llamar al acudiente');
            llamar.innerHTML = icono('telefono');
            contenedor.append(llamar);
        }
        return contenedor;
    }

    function textoParada(datos) {
        const partes = [datos.parada];
        if (datos.numero_parada) partes.push(datos.numero_parada + ' de ' + datos.total_paradas);
        return partes.join(' · ');
    }

    function filaAcudiente(datos) {
        if (!datos.acudiente) return null;
        return fila('acudiente', [datos.acudiente + (datos.parentesco ? ' · ' + datos.parentesco : '')], datos.telefono);
    }

    function mostrarHoja(datos) {
        const casos = {
            registrado: { tema: 'hoja-verde', insignia: 'check', estado: palabras.hizo + ' · ' + datos.hora },
            ya_registrado: { tema: 'hoja-amarilla', insignia: 'reloj', estado: palabras.yaHizo + ' · ' + datos.hora, aviso: 'No se duplicó el registro.' },
            otra_ruta: { tema: 'hoja-roja', insignia: 'alerta', estado: 'No es de tu ruta', aviso: 'No se registró ' + palabras.accion + '. Avisa a coordinación.' },
            desconocido: { tema: 'hoja-roja', insignia: 'alerta', estado: 'Carné no reconocido', aviso: 'Ese código no corresponde a ningún estudiante. Búscalo por nombre.' },
            cerrado: { tema: 'hoja-roja', insignia: 'alerta', estado: 'Recorrido cerrado', aviso: datos.mensaje },
            error: { tema: 'hoja-roja', insignia: 'alerta', estado: 'No se pudo registrar', aviso: datos.mensaje || 'Intenta de nuevo.' },
        };
        const caso = Object.assign({}, casos[datos.resultado] || casos.error);

        hoja.className = 'hoja ' + caso.tema;
        hoja.replaceChildren();

        const foto = crear('div', 'hoja-foto');
        foto.innerHTML = '<span class="hoja-circulo">' + icono('persona') + '</span><span class="hoja-insignia">' + icono(caso.insignia) + '</span>';
        hoja.append(foto);

        const titulo = crear('h3', 'hoja-nombre', datos.nombre || 'Estudiante desconocido');
        hoja.append(titulo);

        if (datos.documento) {
            const documento = (datos.tipo_documento ? datos.tipo_documento + ' ' : '') + datos.documento + (datos.curso ? ' · ' + datos.curso : '');
            hoja.append(crear('p', 'hoja-documento mono', documento));
        }
        hoja.append(crear('p', 'hoja-estado', caso.estado));

        const filas = crear('div', 'hoja-datos');
        if (datos.resultado === 'otra_ruta') {
            filas.append(fila('bus', ['Va en la ', crear('b', '', datos.ruta)]));
            filas.append(fila('parada', [datos.parada]));
        } else if (datos.resultado === 'registrado' || datos.resultado === 'ya_registrado') {
            filas.append(fila('parada', [textoParada(datos)]));
            if (datos.resultado === 'registrado') {
                filas.append(fila('bus', ['Ruta ' + datos.id_ruta + ' · ', crear('span', 'texto-ok', 'tu ruta')]));
            }
            const acudiente = filaAcudiente(datos);
            if (acudiente) filas.append(acudiente);
            else caso.aviso = (caso.aviso ? caso.aviso + ' ' : '') + 'No tiene acudiente registrado.';
        }
        if (filas.children.length) hoja.append(filas);

        if (caso.aviso) hoja.append(crear('p', 'hoja-aviso', caso.aviso));

        const siguiente = crear('button', 'boton boton-amarillo hoja-siguiente', 'Escanear siguiente');
        siguiente.type = 'button';
        siguiente.addEventListener('click', cerrarHoja);
        hoja.append(siguiente);

        if (datos.resultado === 'registrado') {
            const deshacer = crear('button', 'boton-texto hoja-secundario', 'Deshacer');
            deshacer.type = 'button';
            deshacer.addEventListener('click', function () {
                deshacer.disabled = true;
                enviar(escaner.dataset.deshacer, { documento: datos.documento, tipo: escaner.dataset.tipo })
                    .then(function (respuesta) {
                        actualizarContador(respuesta);
                        cerrarHoja();
                        visorTexto.textContent = 'Se deshizo el registro de ' + datos.nombre + '.';
                    })
                    .catch(function () {
                        deshacer.disabled = false;
                        deshacer.textContent = 'No se pudo deshacer. Toca para intentar de nuevo';
                    });
            });
            hoja.append(deshacer);
        } else if (datos.resultado === 'desconocido') {
            const buscar = crear('button', 'boton-texto hoja-secundario', 'Buscar por nombre');
            buscar.type = 'button';
            buscar.addEventListener('click', function () { escaner.querySelector('[data-buscar-nombre]').click(); });
            hoja.append(buscar);
        }

        hojaFondo.hidden = false;
        requestAnimationFrame(function () { hojaFondo.classList.add('visible'); });
        siguiente.focus();
    }

    function cerrarHoja() {
        if (hojaFondo.hidden) return;
        hojaFondo.classList.remove('visible');
        hojaFondo.hidden = true;
        ultimoMomento = Date.now();
        ocupado = false;
        visorTexto.textContent = flujo ? 'Apunta al código del carné' : visorTexto.textContent;
    }

    // Tocar fuera de la ficha la cierra
    hojaFondo.addEventListener('click', function (evento) {
        if (evento.target === hojaFondo) cerrarHoja();
    });
})();
