document.addEventListener("DOMContentLoaded", () => {
    const botonesHora = document.querySelectorAll(".btn-hora");

    botonesHora.forEach(boton => {
        boton.addEventListener("click", () => {
            // Quitar clase seleccionado de todos
            botonesHora.forEach(b => b.classList.remove("seleccionado"));
            // Agregar al presionado
            boton.classList.add("seleccionado");
        });
    });

    document.getElementById("form-cita").addEventListener("submit", (e) => {
        e.preventDefault();
        alert("¡Cita agendada con éxito!");
    });
});