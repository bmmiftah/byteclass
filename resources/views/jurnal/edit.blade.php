<x-guru-layout judul="Edit Jurnal">
    <a href="{{ route('jurnal.index') }}" class="font-bold underline">&larr; Jurnal</a>
    <h1 class="text-3xl mt-3 mb-6">Edit Jurnal</h1>

    <form method="POST" action="{{ route('jurnal.update', $jurnal) }}" class="nb-card grid gap-4 md:grid-cols-6">
        @method('PUT')
        @include('jurnal._form')
        <button class="nb-btn md:col-span-6">Simpan perubahan</button>
    </form>
</x-guru-layout>