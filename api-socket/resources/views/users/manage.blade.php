@extends('layouts.app')

@section('content')
    <div class="container">
        @if (session('status'))
            <div class="alert alert-success" role="alert">
                {{ session('status') }}
            </div>
        @endif

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">Gestión de usuarios</h3>
            <a class="btn btn-secondary" href="{{ route('home') }}">Volver al inicio</a>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Email</th>
                                <th style="width: 180px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($users as $user)
                                <tr data-user-id="{{ $user->id }}">
                                    <td>{{ $user->id }}</td>
                                    <td>
                                        <form class="d-flex align-items-center gap-2 user-row-form" method="POST"
                                            action="{{ route('users.manage.update', $user) }}">
                                            @csrf
                                            @method('PATCH')
                                            <div class="flex-grow-1">
                                                <input type="text" name="name"
                                                    class="form-control form-control-sm user-field" data-field="name"
                                                    data-user-id="{{ $user->id }}"
                                                    value="{{ old('name', $user->name) }}" required disabled>
                                                <small class="text-muted lock-hint d-none"></small>
                                            </div>
                                    </td>
                                    <td>
                                        <div class="flex-grow-1">
                                            <input type="email" name="email"
                                                class="form-control form-control-sm user-field" data-field="email"
                                                data-user-id="{{ $user->id }}" value="{{ old('email', $user->email) }}"
                                                required disabled>
                                            <small class="text-muted lock-hint d-none"></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <button type="button"
                                                class="btn btn-sm btn-outline-primary btn-edit">Editar</button>
                                            <button type="button"
                                                class="btn btn-sm btn-outline-secondary btn-cancel d-none">Cancelar</button>
                                        </div>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-4">No hay usuarios registrados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer">
                {{ $users->links() }}
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                const me = {
                    id: @json(auth()->id()),
                    name: @json(auth()->user()->name),
                };

                const locks = new Map();
                const channel = window.Echo.channel('user-management');
                channel.listen('.App\\Events\\UserFieldLocked', handleLock)
                    .listen('.App\\Events\\UserFieldUnlocked', handleUnlock)
                    .listen('.App\\Events\\UserFieldUpdated', handleUpdated);

                function key(userId, field) {
                    return `${userId}:${field}`;
                }

                function handleLock(data) {
                    const k = key(data.userId, data.field);
                    locks.set(k, {
                        byId: data.byId,
                        byName: data.byName
                    });
                    updateAllFieldsDisplay(data.userId);
                }

                function handleUnlock(data) {
                    const k = key(data.userId, data.field);
                    locks.delete(k);
                    updateAllFieldsDisplay(data.userId);
                }

                function handleUpdated(data) {
                    if (data.byId === me.id) return;
                    ['name', 'email'].forEach(field => {
                        const input = document.querySelector(
                            `.user-field[data-user-id="${data.userId}"][data-field="${field}"]`);
                        if (!input) return;
                        const incoming = field === 'name' ? data.name : data.email;
                        input.value = incoming;
                        if (input.dataset.editing !== 'true') {
                            input.dataset.originalValue = incoming;
                        }
                    });
                }

                function updateAllFieldsDisplay(userId) {
                    document.querySelectorAll(`.user-field[data-user-id="${userId}"]`).forEach(input => {
                        updateFieldState(userId, input.dataset.field);
                    });
                }

                function updateFieldState(userId, field) {
                    const input = document.querySelector(
                        `.user-field[data-user-id="${userId}"][data-field="${field}"]`);
                    if (!input) return;
                    const hint = input.closest('div').querySelector('.lock-hint');
                    const k = key(userId, field);
                    const lock = locks.get(k);
                    const editing = input.dataset.editing === 'true';

                    if (lock && lock.byId !== me.id) {
                        input.disabled = true;
                        hint?.classList.remove('d-none');
                        if (hint) hint.textContent = `En edición por ${lock.byName}`;
                    } else if (editing) {
                        input.disabled = false;
                        hint?.classList.add('d-none');
                        if (hint) hint.textContent = '';
                    } else {
                        input.disabled = true;
                        hint?.classList.add('d-none');
                        if (hint) hint.textContent = '';
                    }
                }

                async function lockField(input) {
                    const userId = Number(input.dataset.userId);
                    const field = input.dataset.field;
                    const k = key(userId, field);
                    const lock = locks.get(k);
                    if (lock && lock.byId !== me.id) return false;

                    const res = await fetch('{{ route('users.manage.lock') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                .content,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({
                            user_id: userId,
                            field
                        }),
                    }).catch(() => null);

                    if (!res) return false;
                    if (res.status === 409) {
                        const data = await res.json();
                        locks.set(k, {
                            byId: data.byId,
                            byName: data.byName
                        });
                        updateAllFieldsDisplay(userId);
                        return false;
                    }
                    return true;
                }

                function unlockField(input) {
                    const userId = Number(input.dataset.userId);
                    const field = input.dataset.field;
                    fetch('{{ route('users.manage.unlock') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                .content,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({
                            user_id: userId,
                            field
                        }),
                    }).catch(() => {});
                }

                let currentEditingRow = null;

                document.querySelectorAll('tr[data-user-id]').forEach(row => {
                    const form = row.querySelector('form.user-row-form');
                    const inputs = Array.from(row.querySelectorAll('.user-field'));
                    const editBtn = row.querySelector('.btn-edit');
                    const cancelBtn = row.querySelector('.btn-cancel');
                    let isEditing = false;

                    const stopRow = (targetRow, resetValues = false) => {
                        const rowInputs = Array.from(targetRow.querySelectorAll('.user-field'));
                        rowInputs.forEach(input => {
                            if (resetValues && input.dataset.originalValue !==
                                undefined) {
                                input.value = input.dataset.originalValue;
                            }
                            input.dataset.editing = 'false';
                            input.disabled = true;
                            unlockField(input);
                        });
                        const rowEdit = targetRow.querySelector('.btn-edit');
                        const rowCancel = targetRow.querySelector('.btn-cancel');
                        rowCancel?.classList.add('d-none');
                        if (rowEdit) {
                            rowEdit.classList.remove('d-none');
                            rowEdit.disabled = false;
                        }
                        if (currentEditingRow === targetRow) currentEditingRow = null;
                        if (targetRow === row) isEditing = false;
                    };

                    const startEdit = async () => {
                        if (isEditing) return;

                        if (currentEditingRow && currentEditingRow !== row) {
                            stopRow(currentEditingRow, false);
                        }

                        const lockedInputs = [];
                        for (const input of inputs) {
                            const ok = await lockField(input);
                            if (!ok) {
                                lockedInputs.forEach(unlockField);
                                inputs.forEach(fieldInput => updateFieldState(Number(
                                        fieldInput.dataset.userId), fieldInput
                                    .dataset.field));
                                return;
                            }
                            lockedInputs.push(input);
                        }

                        isEditing = true;
                        currentEditingRow = row;

                        inputs.forEach(input => {
                            input.dataset.originalValue = input.value;
                            input.dataset.editing = 'true';
                            input.disabled = false;
                        });

                        if (editBtn) {
                            editBtn.classList.add('d-none');
                            editBtn.disabled = true;
                        }
                        cancelBtn?.classList.remove('d-none');
                        inputs[0]?.focus();
                    };

                    const stopEdit = (resetValues = false) => stopRow(row, resetValues);

                    editBtn?.addEventListener('click', startEdit);
                    cancelBtn?.addEventListener('click', () => stopEdit(true));

                    inputs.forEach(input => {
                        input.dataset.editing = 'false';
                        input.dataset.originalValue = input.value;
                        input.addEventListener('keydown', (e) => {
                            if (e.key === 'Enter') {
                                e.preventDefault();
                                form?.requestSubmit();
                            }
                        });
                    });

                    form?.addEventListener('submit', async (e) => {
                        e.preventDefault();
                        const formData = new FormData(form);
                        try {
                            const res = await fetch(form.action, {
                                method: form.method,
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector(
                                        'meta[name="csrf-token"]').content,
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                body: formData,
                            });
                            if (res.ok) {
                                inputs.forEach(unlockField);
                                stopEdit(false);
                            }
                        } catch (err) {
                            inputs.forEach(unlockField);
                            stopEdit(true);
                        }
                    });
                });
            }, 10);
        });
    </script>
@endpush
