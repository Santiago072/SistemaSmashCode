/**
 * login.js — Lógica JS del módulo de autenticación
 * Maneja el cambio de tabs (rol / acción) sin recargar la página.
 */

document.addEventListener('DOMContentLoaded', () => {

  /* ── Tabs de ROL (Aprendiz / Instructor / Admin) ── */
  const tabsRol = document.querySelectorAll('.tab-rol');
  tabsRol.forEach(tab => {
    tab.addEventListener('click', () => {
      tabsRol.forEach(t => t.classList.remove('activo'));
      tab.classList.add('activo');

      /* Instructor y Admin solo tienen formulario de ingresar */
      if (tab.id !== 'tab-aprendiz') {
        document.getElementById('btn-registrar').style.display = 'none';
        activarAccion('ingresar');
      } else {
        document.getElementById('btn-registrar').style.display = 'block';
      }
    });
  });

  /* ── Tabs de ACCIÓN (Ingresar / Registrarse) ── */
  document.getElementById('btn-ingresar')?.addEventListener('click',  () => activarAccion('ingresar'));
  document.getElementById('btn-registrar')?.addEventListener('click', () => activarAccion('registrar'));

  /**
   * Muestra el formulario correspondiente a la acción seleccionada.
   * @param {string} accion 'ingresar' | 'registrar'
   */
  function activarAccion(accion) {
    const fIngresar  = document.getElementById('formulario-ingresar');
    const fRegistro  = document.getElementById('formulario-registro');
    const btnIng     = document.getElementById('btn-ingresar');
    const btnReg     = document.getElementById('btn-registrar');

    if (accion === 'ingresar') {
      fIngresar?.style && (fIngresar.style.display = 'block');
      fRegistro?.style && (fRegistro.style.display = 'none');
      btnIng?.classList.add('activo');
      btnReg?.classList.remove('activo');
    } else {
      fIngresar?.style && (fIngresar.style.display = 'none');
      fRegistro?.style && (fRegistro.style.display = 'block');
      btnIng?.classList.remove('activo');
      btnReg?.classList.add('activo');
    }
  }

  /* ── Validación básica del lado del cliente ── */
  document.getElementById('formulario-registro')?.addEventListener('submit', (e) => {
    const clave = document.getElementById('clave-registro')?.value ?? '';
    if (clave.length < 8 || !/[A-Z]/.test(clave) || !/[0-9]/.test(clave)) {
      e.preventDefault();
      alert('La contraseña debe tener mínimo 8 caracteres, una mayúscula y un número.');
    }
  });

});
