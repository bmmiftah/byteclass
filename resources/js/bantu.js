export const csrf = () => document.querySelector('meta[name="csrf-token"]').content;

export const kirim = (metode, url, body) =>
    fetch(url, {
        method: metode,
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrf(),
        },
        body: body ? JSON.stringify(body) : undefined,
    });

export const hasilJson = async (r) => {
    let d = {};

    try {
        d = await r.json();
    } catch (e) {
        /* kosong */
    }

    if (!r.ok) {
        throw new Error(d.pesan || d.message || 'Terjadi kesalahan (' + r.status + ')');
    }

    return d;
};