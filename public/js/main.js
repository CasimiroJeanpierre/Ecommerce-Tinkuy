document.addEventListener("DOMContentLoaded", function () {
    // Simulación de búsqueda de productos
    const searchInput = document.querySelector("#search");
    if (searchInput) {
        searchInput.addEventListener("input", function () {
            console.log("Buscando:", searchInput.value);
            // Aquí iría la lógica para filtrar productos
        });
    }

    // Funcionalidad del carrito
    const cart = [];
    function addToCart(productId) {
        cart.push(productId);
        console.log("Producto agregado:", productId);
    }
});
