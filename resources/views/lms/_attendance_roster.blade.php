@if ($students->isEmpty())
    <p>Belum ada mahasiswa dengan KRS yang disahkan pada kelas ini.</p>
@else
    <div class="table-wrap"><table data-datatable="lms-attendance-{{ $record }}"><thead><tr><th>NPM</th><th>Nama</th><th>Status</th><th>Absensi</th></tr></thead><tbody>
        @foreach ($students as $student)
            <tr>
                <td>{{ $student->nim }}</td>
                <td>{{ $student->nama_mahasiswa }}</td>
                <td data-attendance-status>{{ $student->status_kehadiran ? ucfirst($student->status_kehadiran) : 'Belum absen' }}</td>
                <td>
                    <label class="lms-attendance-toggle">
                        <input type="checkbox"
                            data-lms-attendance-toggle
                            data-mark-url="{{ route('lms.attendance.mark', ['record' => $record, 'student' => $student->id_mahasiswa]) }}"
                            data-cancel-url="{{ route('lms.attendance.cancel', ['record' => $record, 'student' => $student->id_mahasiswa]) }}"
                            data-student-name="{{ $student->nama_mahasiswa }}"
                            @checked($student->status_kehadiran !== null)
                            aria-label="{{ $student->status_kehadiran ? 'Batalkan absensi' : 'Tandai hadir' }} {{ $student->nama_mahasiswa }}">
                        <span class="lms-attendance-slider" aria-hidden="true"></span>
                    </label>
                </td>
            </tr>
        @endforeach
    </tbody></table></div>
@endif
