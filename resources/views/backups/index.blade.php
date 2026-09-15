<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Backup Management</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>
        body {
            background: #f5f7fb;
        }

        .dashboard-card {
            border: none;
            border-radius: 14px;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.06);
        }

        .stat-card {
            border: none;
            border-radius: 14px;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.06);
            transition: 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            background: #eef2ff;
        }

        .table-card {
            border: none;
            border-radius: 14px;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.06);
        }

        .backup-name {
            font-weight: 600;
        }

        .action-button {
            min-width: 100px;
        }

        .empty-state {
            padding: 60px 20px;
            text-align: center;
        }

        .command-output {
            white-space: pre-wrap;
            max-height: 250px;
            overflow-y: auto;
            background: #111827;
            color: #f9fafb;
            border-radius: 8px;
            padding: 15px;
            font-size: 13px;
        }
    </style>

</head>

<body>

<div class="container py-4">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h2 class="fw-bold mb-1">
                Backup Management
            </h2>

            <p class="text-muted mb-0">
                Manage Laravel application and database backups
            </p>
        </div>

        <div class="d-flex gap-2">

            <form
                action="{{ route('backups.run') }}"
                method="POST"
            >

                @csrf

                <button
                    type="submit"
                    class="btn btn-primary action-button"
                    onclick="return confirm('Start a new backup now?')"
                >
                    ▶ Run Backup
                </button>

            </form>

            <form
                action="{{ route('backups.cleanup') }}"
                method="POST"
            >

                @csrf

                <button
                    type="submit"
                    class="btn btn-outline-danger action-button"
                    onclick="return confirm('Run backup cleanup now?')"
                >
                    🧹 Cleanup
                </button>

            </form>

        </div>

    </div>


    {{-- Success message --}}
    @if(session('success'))

        <div class="alert alert-success alert-dismissible fade show">

            <strong>Success!</strong>

            {{ session('success') }}

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    @endif


    {{-- Error message --}}
    @if(session('error'))

        <div class="alert alert-danger alert-dismissible fade show">

            <strong>Error!</strong>

            {{ session('error') }}

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    @endif


    {{-- Command output --}}
    @if(session('command_output'))

        <div class="card dashboard-card mb-4">

            <div class="card-header bg-dark text-white">
                Command Output
            </div>

            <div class="card-body">

                <div class="command-output">{{ session('command_output') }}</div>

            </div>

        </div>

    @endif


    {{-- Statistics --}}
    <div class="row g-4 mb-4">

        <div class="col-md-4">

            <div class="card stat-card h-100">

                <div class="card-body d-flex align-items-center">

                    <div class="stat-icon me-3">
                        📦
                    </div>

                    <div>

                        <div class="text-muted small">
                            Total Backups
                        </div>

                        <h3 class="fw-bold mb-0">
                            {{ $totalBackups }}
                        </h3>

                    </div>

                </div>

            </div>

        </div>


        <div class="col-md-4">

            <div class="card stat-card h-100">

                <div class="card-body d-flex align-items-center">

                    <div class="stat-icon me-3">
                        💾
                    </div>

                    <div>

                        <div class="text-muted small">
                            Total Storage Used
                        </div>

                        <h3 class="fw-bold mb-0">
                            {{ $totalSize }}
                        </h3>

                    </div>

                </div>

            </div>

        </div>


        <div class="col-md-4">

            <div class="card stat-card h-100">

                <div class="card-body d-flex align-items-center">

                    <div class="stat-icon me-3">
                        🕒
                    </div>

                    <div>

                        <div class="text-muted small">
                            Latest Backup
                        </div>

                        @if($latestBackupData)

                            <div class="fw-bold">
                                {{ $latestBackupData['size'] }}
                            </div>

                            <small class="text-muted">
                                {{ $latestBackupData['date'] }}
                            </small>

                        @else

                            <div class="text-muted">
                                No backups found
                            </div>

                        @endif

                    </div>

                </div>

            </div>

        </div>

    </div>


    {{-- Backup history --}}
    <div class="card table-card">

        <div class="card-body">

            <div class="d-flex justify-content-between align-items-center mb-3">

                <div>

                    <h5 class="fw-bold mb-1">
                        Backup History
                    </h5>

                    <small class="text-muted">
                        View, search, download and manage backup archives
                    </small>

                </div>

            </div>


            {{-- Search & Filters --}}
            <form
                action="{{ route('backups.index') }}"
                method="GET"
                class="row g-2 mb-4"
            >

                <div class="col-md-5">

                    <input
                        type="text"
                        name="search"
                        class="form-control"
                        placeholder="Search backup filename..."
                        value="{{ request('search') }}"
                    >

                </div>


                <div class="col-md-3">

                    <input
                        type="date"
                        name="date"
                        class="form-control"
                        value="{{ request('date') }}"
                    >

                </div>


                <div class="col-md-2">

                    <select
                        name="sort"
                        class="form-select"
                    >

                        <option
                            value="newest"
                            {{ request('sort', 'newest') === 'newest' ? 'selected' : '' }}
                        >
                            Newest First
                        </option>

                        <option
                            value="oldest"
                            {{ request('sort') === 'oldest' ? 'selected' : '' }}
                        >
                            Oldest First
                        </option>

                        <option
                            value="largest"
                            {{ request('sort') === 'largest' ? 'selected' : '' }}
                        >
                            Largest First
                        </option>

                        <option
                            value="smallest"
                            {{ request('sort') === 'smallest' ? 'selected' : '' }}
                        >
                            Smallest First
                        </option>

                    </select>

                </div>


                <div class="col-md-2 d-flex gap-2">

                    <button
                        type="submit"
                        class="btn btn-dark flex-grow-1"
                    >
                        🔎 Filter
                    </button>

                    <a
                        href="{{ route('backups.index') }}"
                        class="btn btn-outline-secondary"
                    >
                        Reset
                    </a>

                </div>

            </form>


            @if($backups->count())

                <div class="table-responsive">

                    <table class="table table-hover align-middle">

                        <thead class="table-light">

                        <tr>

                            <th>#</th>

                            <th>Backup File</th>

                            <th>Size</th>

                            <th>Created</th>

                            <th class="text-end">
                                Actions
                            </th>

                        </tr>

                        </thead>


                        <tbody>

                        @foreach($backups as $index => $backup)

                            <tr>

                                <td>
                                    {{ $index + 1 }}
                                </td>


                                <td>

                                    <div class="backup-name">
                                        📦 {{ $backup['name'] }}
                                    </div>

                                </td>


                                <td>
                                    {{ $backup['size_formatted'] }}
                                </td>


                                <td>

                                    <div>
                                        {{ date('d M Y', $backup['last_modified']) }}
                                    </div>

                                    <small class="text-muted">
                                        {{ date('h:i:s A', $backup['last_modified']) }}
                                    </small>

                                </td>


                                <td>

                                    <div class="d-flex justify-content-end gap-2">

                                        <a
                                            href="{{ route('backups.download', ['filename' => $backup['name']]) }}"
                                            class="btn btn-sm btn-outline-primary"
                                        >
                                            ⬇ Download
                                        </a>


                                        <form
                                            action="{{ route('backups.destroy', ['filename' => $backup['name']]) }}"
                                            method="POST"
                                        >

                                            @csrf

                                            @method('DELETE')

                                            <button
                                                type="submit"
                                                class="btn btn-sm btn-outline-danger"
                                                onclick="return confirm('Are you sure you want to permanently delete this backup?')"
                                            >
                                                🗑 Delete
                                            </button>

                                        </form>

                                    </div>

                                </td>

                            </tr>

                        @endforeach

                        </tbody>

                    </table>

                </div>

            @else

                <div class="empty-state">

                    <div style="font-size: 50px;">
                        📦
                    </div>

                    <h5 class="fw-bold mt-3">
                        No backups found
                    </h5>

                    <p class="text-muted">
                        Run your first backup to see it here.
                    </p>

                    <form
                        action="{{ route('backups.run') }}"
                        method="POST"
                    >

                        @csrf

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            ▶ Create First Backup
                        </button>

                    </form>

                </div>

            @endif

        </div>

    </div>

</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>