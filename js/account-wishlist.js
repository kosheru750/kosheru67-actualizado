const KosheruAccountWishlist = {
    items: [],

    async load() {
        const status = document.getElementById('accountWishlistStatus');
        const carousel = document.getElementById('accountWishlistCarousel');
        const track = document.getElementById('accountWishlistTrack');
        const toggle = document.getElementById('accountWishlistToggle');
        if (!status || !carousel || !track || !toggle) return;

        try {
            const response = await fetch('api/wishlist.php');
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'No pudimos cargar tu wishlist.');

            this.items = data.items || [];
            status.hidden = true;

            if (this.items.length === 0) {
                status.innerHTML = 'Todavía no has guardado productos. <a href="tienda.php">Explorar productos</a>';
                status.hidden = false;
                carousel.hidden = true;
                toggle.hidden = true;
                document.getElementById('accountWishlistControls').hidden = true;
                return;
            }

            toggle.hidden = false;
            this.render();
        } catch (error) {
            status.textContent = 'No pudimos cargar tu wishlist. Intenta de nuevo más tarde.';
            status.hidden = false;
            carousel.hidden = true;
            toggle.hidden = true;
            document.getElementById('accountWishlistControls').hidden = true;
        }
    },

    createCard(product) {
        const card = document.createElement('article');
        card.className = 'account-wishlist-card';

        const image = document.createElement('img');
        image.src = product.image || 'img/Tofu Ahumado Premium.png';
        image.alt = product.name || 'Producto guardado';
        image.loading = 'lazy';
        image.addEventListener('error', () => {
            image.src = 'img/Tofu Ahumado Premium.png';
            image.onerror = null;
        });

        const body = document.createElement('div');
        body.className = 'account-wishlist-card-body';

        const origin = document.createElement('span');
        origin.className = 'account-wishlist-card-origin';
        origin.textContent = product.origin || '';

        const name = document.createElement('h3');
        name.textContent = product.name || 'Producto';

        const price = document.createElement('p');
        price.className = 'account-wishlist-card-price';
        price.textContent = product.price_formatted || '$' + new Intl.NumberFormat('es-CO').format(Number(product.price || 0)) + ' COP';

        const addButton = document.createElement('button');
        addButton.type = 'button';
        addButton.className = 'account-wishlist-remove';
        addButton.textContent = 'Agregar al carrito';
        addButton.addEventListener('click', () => {
            if (window.KosheruCart) KosheruCart.addToCart(product, addButton);
        });

        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'account-wishlist-remove';
        removeButton.textContent = 'Quitar de wishlist';
        removeButton.addEventListener('click', () => this.remove(product.id, removeButton));

        body.append(origin, name, price, addButton, removeButton);
        card.append(image, body);
        return card;
    },

    render() {
        const track = document.getElementById('accountWishlistTrack');
        const carousel = document.getElementById('accountWishlistCarousel');
        const allItems = document.getElementById('accountWishlistAll');
        const controls = document.getElementById('accountWishlistControls');
        if (!track || !carousel || !allItems || !controls) return;

        track.replaceChildren(...this.items.slice(0, 6).map(product => this.createCard(product)));
        allItems.replaceChildren(...this.items.map(product => this.createCard(product)));
        carousel.hidden = false;
        allItems.hidden = true;
        controls.hidden = this.items.length <= 3;
        track.scrollLeft = 0;
        this.updateControls();
    },

    updateControls() {
        const track = document.getElementById('accountWishlistCarousel');
        const previous = document.getElementById('accountWishlistPrevious');
        const next = document.getElementById('accountWishlistNext');
        if (!track || !previous || !next) return;

        previous.disabled = track.scrollLeft <= 1;
        next.disabled = track.scrollLeft + track.clientWidth >= track.scrollWidth - 1;
    },

    showAll(show) {
        const carousel = document.getElementById('accountWishlistCarousel');
        const allItems = document.getElementById('accountWishlistAll');
        const controls = document.getElementById('accountWishlistControls');
        const toggle = document.getElementById('accountWishlistToggle');
        if (!carousel || !allItems || !controls || !toggle) return;

        carousel.hidden = show;
        allItems.hidden = !show;
        controls.hidden = show || this.items.length <= 3;
        toggle.setAttribute('aria-expanded', String(show));
        toggle.textContent = show ? 'Ver últimos productos' : 'Ver toda mi wishlist';
    },

    async remove(productId, button) {
        button.disabled = true;
        const formData = new FormData();
        formData.append('product_id', productId);

        try {
            const response = await fetch('api/wishlist.php', { method: 'POST', body: formData });
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'No se pudo quitar el producto.');

            if (window.KosheruWishlist) window.KosheruWishlist.ids.delete(Number(productId));
            if (window.KosheruApp) KosheruApp.showToast(data.message, 'success');
            await this.load();
        } catch (error) {
            button.disabled = false;
            if (window.KosheruApp) KosheruApp.showToast(error.message || 'No se pudo actualizar tu wishlist.', 'error');
        }
    }
};

document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById('accountWishlistToggle');
    const carousel = document.getElementById('accountWishlistCarousel');
    const previous = document.getElementById('accountWishlistPrevious');
    const next = document.getElementById('accountWishlistNext');

    toggle?.addEventListener('click', () => {
        KosheruAccountWishlist.showAll(toggle.getAttribute('aria-expanded') !== 'true');
    });
    previous?.addEventListener('click', () => {
        carousel.scrollBy({ left: -carousel.clientWidth, behavior: 'smooth' });
    });
    next?.addEventListener('click', () => {
        carousel.scrollBy({ left: carousel.clientWidth, behavior: 'smooth' });
    });
    carousel?.addEventListener('scroll', () => KosheruAccountWishlist.updateControls(), { passive: true });
    window.addEventListener('resize', () => KosheruAccountWishlist.updateControls());

    KosheruAccountWishlist.load();
});