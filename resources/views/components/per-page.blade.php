<div class="form-field">
    <label for="filtro-per-page">Por página</label>
    <select class="input-text" id="filtro-per-page" name="per_page">
        @foreach(App\Support\Listing::options() as $opcion)
            <option value="{{ $opcion }}" @selected(App\Support\Listing::perPage(request()) === $opcion)>{{ $opcion }}</option>
        @endforeach
    </select>
</div>
