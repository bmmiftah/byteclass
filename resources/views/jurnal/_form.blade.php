@csrf

<div class="md:col-span-2">
    <label class="nb-label" for="tanggal">Tanggal</label>
    <input
        class="nb-input"
        type="date"
        id="tanggal"
        name="tanggal"
        value="{{ old('tanggal', $jurnal->tanggal?->format('Y-m-d')) }}"
        required
    >
</div>

<div class="md:col-span-2">
    <label class="nb-label" for="kelas_id">Kelas</label>
    <select class="nb-input" id="kelas_id" name="kelas_id" required>
        @foreach ($kelas as $k)
            <option value="{{ $k->id }}" @selected(old('kelas_id', $jurnal->kelas_id) == $k->id)>
                {{ $k->nama }}
            </option>
        @endforeach
    </select>
</div>

<div class="md:col-span-2">
    <label class="nb-label" for="mapel_id">Mapel</label>
    <select class="nb-input" id="mapel_id" name="mapel_id">
        <option value="">-</option>
        @foreach ($mapels as $m)
            <option value="{{ $m->id }}" @selected(old('mapel_id', $jurnal->mapel_id) == $m->id)>
                {{ $m->nama }}
            </option>
        @endforeach
    </select>
</div>

<div class="md:col-span-6">
    <label class="nb-label" for="topik">Topik / materi</label>
    <input
        class="nb-input"
        id="topik"
        name="topik"
        value="{{ old('topik', $jurnal->topik) }}"
        required
    >
    @error('topik')
        <p class="nb-error">{{ $message }}</p>
    @enderror
</div>

<div class="md:col-span-6">
    <label class="nb-label" for="kegiatan">Kegiatan pembelajaran</label>
    <textarea class="nb-input" id="kegiatan" name="kegiatan" rows="3">{{ old('kegiatan', $jurnal->kegiatan) }}</textarea>
</div>

<div class="md:col-span-3">
    <label class="nb-label" for="kendala">Kendala</label>
    <textarea class="nb-input" id="kendala" name="kendala" rows="3">{{ old('kendala', $jurnal->kendala) }}</textarea>
</div>

<div class="md:col-span-3">
    <label class="nb-label" for="tindak_lanjut">Tindak lanjut</label>
    <textarea class="nb-input" id="tindak_lanjut" name="tindak_lanjut" rows="3">{{ old('tindak_lanjut', $jurnal->tindak_lanjut) }}</textarea>
</div>