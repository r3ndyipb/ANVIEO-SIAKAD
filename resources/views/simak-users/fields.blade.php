@php
    $isEditing = $account !== null;
    $isProgramAccount = in_array($type, ['userprodi', 'usertu'], true);
    $selectedLevel = old('level', $account->level ?? ($type === 'userpim' ? 'pimuniv' : ''));
@endphp

<form method="POST" action="{{ $formAction }}" data-simak-user-form>
    @csrf
    @if ($formMethod === 'PUT') <input type="hidden" name="_method" value="PUT"> @endif

    <div class="field-grid">
        @if ($isEditing)
            <div>
                <label for="account-username">Username</label>
                <input id="account-username" value="{{ $account->username }}" disabled>
                <small class="subtitle">Username tidak diubah melalui form SIAKAD lama.</small>
            </div>
        @else
            <div>
                <label for="account-username">Username</label>
                <input id="account-username" name="username" value="{{ old('username') }}" maxlength="100" autocomplete="username" required>
                @error('username') <small class="field-error">{{ $message }}</small> @enderror
            </div>
        @endif
        <div>
            <label for="account-password">{{ $isEditing ? 'Password Baru' : 'Password' }}</label>
            <input id="account-password" type="password" name="password" maxlength="100" autocomplete="new-password" @required(! $isEditing)>
            @if ($isEditing) <small class="subtitle">Kosongkan jika tidak ingin mengganti password.</small> @endif
            @error('password') <small class="field-error">{{ $message }}</small> @enderror
        </div>
        <div>
            <label for="account-name">Nama Lengkap</label>
            <input id="account-name" name="nama_lengkap" value="{{ old('nama_lengkap', $account->nama_lengkap ?? '') }}" maxlength="100" required>
            @error('nama_lengkap') <small class="field-error">{{ $message }}</small> @enderror
        </div>
        <div>
            <label for="account-email">Email</label>
            <input id="account-email" type="email" name="email" value="{{ old('email', $account->email ?? '') }}" maxlength="50" required>
            @error('email') <small class="field-error">{{ $message }}</small> @enderror
        </div>
        <div>
            <label for="account-phone">Telepon</label>
            <input id="account-phone" name="no_telp" value="{{ old('no_telp', $account->no_telp ?? '') }}" maxlength="20">
            @error('no_telp') <small class="field-error">{{ $message }}</small> @enderror
        </div>

        @if ($type === 'userpim')
            <div>
                <label for="account-level">Jenis Pimpinan</label>
                <select id="account-level" name="level" required data-searchable-select="filter" data-account-level>
                    <option value="pimuniv" @selected($selectedLevel === 'pimuniv')>Pimpinan Universitas</option>
                    <option value="pimfak" @selected($selectedLevel === 'pimfak')>Pimpinan Fakultas</option>
                </select>
                @error('level') <small class="field-error">{{ $message }}</small> @enderror
            </div>
            <div data-account-faculty-field @if ($selectedLevel !== 'pimfak') hidden @endif>
                <label for="account-faculty">Fakultas</label>
                <select id="account-faculty" name="id_fakultas" data-searchable-select="filter" data-account-faculty @required($selectedLevel === 'pimfak')>
                    <option value="">Pilih fakultas</option>
                    @foreach ($faculties as $faculty)
                        <option value="{{ $faculty->id_fakultas }}" @selected((string) old('id_fakultas', $account->id_fakultas ?? '') === (string) $faculty->id_fakultas)>{{ $faculty->nama_fakultas }}</option>
                    @endforeach
                </select>
                @error('id_fakultas') <small class="field-error">{{ $message }}</small> @enderror
            </div>
        @elseif ($isProgramAccount)
            <div>
                <label for="account-program">Program Studi</label>
                <select id="account-program" name="id_prodi" required data-searchable-select="filter">
                    <option value="">Pilih program studi</option>
                    @foreach ($programs as $program)
                        <option value="{{ $program->id_prodi }}" @selected((string) old('id_prodi', $account->id_prodi ?? '') === (string) $program->id_prodi)>
                            {{ $program->nama_program_studi }}{{ $program->nama_jenjang_pendidikan ? ' ('.$program->nama_jenjang_pendidikan.')' : '' }} · {{ $program->nama_fakultas ?: 'Fakultas belum ditetapkan' }}
                        </option>
                    @endforeach
                </select>
                @error('id_prodi') <small class="field-error">{{ $message }}</small> @enderror
            </div>
        @endif

        @if ($isEditing)
            <div>
                <label for="account-blocked">Status Akun</label>
                <select id="account-blocked" name="blokir" required>
                    <option value="N" @selected(old('blokir', $account->blokir) === 'N')>Aktif</option>
                    <option value="Y" @selected(old('blokir', $account->blokir) === 'Y')>Diblokir</option>
                </select>
                @error('blokir') <small class="field-error">{{ $message }}</small> @enderror
            </div>
        @endif
    </div>

    <div class="calendar-modal-actions">
        @if ($modal)
            <button class="button" type="button" data-simak-user-close>Batal</button>
        @else
            <a class="button" href="{{ route('simak-users.index', ['type' => $type]) }}">Batal</a>
        @endif
        <button class="button button-primary" type="submit"><span data-lucide="save"></span> {{ $isEditing ? 'Simpan Perubahan' : 'Buat Akun' }}</button>
    </div>
</form>
