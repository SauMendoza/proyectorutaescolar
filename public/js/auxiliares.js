// JavaScript del módulo Auxiliares (formulario "Nuevo auxiliar").
// 1) Mostrar / ocultar y generar la contraseña temporal, con su barra de seguridad.
// 2) Revisar mientras se escribe si el correo está libre.

(function () {
    const formulario = document.getElementById('formulario-auxiliar');
    if (!formulario) return;

    // -----------------------------------------------------------------------
    // 1) Contraseña temporal
    // -----------------------------------------------------------------------
    const contrasena = document.getElementById('contrasena-auxiliar');
    const mostrar = document.getElementById('mostrar-contrasena');
    const generar = document.getElementById('generar-contrasena');
    const barras = formulario.querySelector('.fuerza');
    const textoFuerza = document.getElementById('fuerza-texto');

    mostrar.addEventListener('click', function () {
        const visible = contrasena.type === 'text';
        contrasena.type = visible ? 'password' : 'text';
        mostrar.textContent = visible ? 'Mostrar' : 'Ocultar';
        mostrar.setAttribute('aria-pressed', String(!visible));
    });

    // Sin letras que se confunden al dictarla (l, I, 1, O, 0)
    const MINUSCULAS = 'abcdefghijkmnpqrstuvwxyz';
    const MAYUSCULAS = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    const NUMEROS = '23456789';
    const SIMBOLOS = '#$%*+-?!';

    function elegir(caracteres) {
        const azar = new Uint32Array(1);
        crypto.getRandomValues(azar);
        return caracteres[azar[0] % caracteres.length];
    }

    generar.addEventListener('click', function () {
        // 10 caracteres con al menos una mayúscula, un número y un símbolo
        const partes = [elegir(MAYUSCULAS), elegir(MINUSCULAS), elegir(NUMEROS), elegir(SIMBOLOS)];
        while (partes.length < 10) partes.push(elegir(MINUSCULAS + MAYUSCULAS + NUMEROS));
        for (let i = partes.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [partes[i], partes[j]] = [partes[j], partes[i]];
        }
        contrasena.value = partes.join('');
        contrasena.type = 'text';
        mostrar.textContent = 'Ocultar';
        mostrar.setAttribute('aria-pressed', 'true');
        medir();
    });

    // Nivel de 0 a 4: largo, mayúsculas y minúsculas, números, símbolos
    function medir() {
        const valor = contrasena.value;
        let nivel = 0;
        if (valor.length >= 8) {
            nivel = 1;
            if (/[a-z]/.test(valor) && /[A-Z]/.test(valor)) nivel++;
            if (/\d/.test(valor)) nivel++;
            if (/[^A-Za-z0-9]/.test(valor) || valor.length >= 12) nivel++;
        }
        barras.dataset.nivel = String(nivel);

        const nombres = ['', 'Débil', 'Regular', 'Segura', 'Muy segura'];
        textoFuerza.textContent = valor.length < 8
            ? 'Mínimo 8 caracteres' + (valor.length ? ' · lleva ' + valor.length + '.' : '.')
            : nombres[nivel] + ' · ' + valor.length + ' caracteres';
    }

    contrasena.addEventListener('input', medir);
    formulario.addEventListener('reset', function () {
        setTimeout(function () { medir(); revisarCorreo(); }, 0);
    });

    // -----------------------------------------------------------------------
    // 2) ¿El correo está libre?
    // -----------------------------------------------------------------------
    const correo = document.getElementById('correo-auxiliar');
    const caja = document.getElementById('caja-correo');
    const estado = document.getElementById('estado-correo');
    let espera = null;
    let ultimaConsulta = 0;

    function pintar(clase, texto) {
        caja.classList.remove('libre', 'ocupado');
        if (clase) caja.classList.add(clase);
        estado.textContent = texto;
    }

    function revisarCorreo() {
        clearTimeout(espera);
        const valor = correo.value.trim();
        if (!valor || !correo.checkValidity()) {
            pintar(null, '');
            return;
        }
        espera = setTimeout(function () {
            const numero = ++ultimaConsulta;
            fetch(correo.dataset.revisar + '?correo=' + encodeURIComponent(valor), { headers: { Accept: 'application/json' } })
                .then(function (respuesta) { return respuesta.json(); })
                .then(function (datos) {
                    if (numero !== ultimaConsulta) return; // llegó una respuesta vieja
                    if (!datos.valido) pintar(null, '');
                    else if (datos.disponible) pintar('libre', 'Disponible');
                    else pintar('ocupado', 'Ya está en uso');
                })
                .catch(function () { pintar(null, ''); });
        }, 350);
    }

    correo.addEventListener('input', revisarCorreo);
    medir();
    revisarCorreo();
})();
